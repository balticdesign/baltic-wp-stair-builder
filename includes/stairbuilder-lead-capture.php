<?php
/**
 * Lead capture flow: AJAX submit → PDF → email customer + admin → DB write
 * → fires `baltic_stairbuilder_lead_captured` action.
 *
 * Replaces the legacy WooCommerce-coupled checkout/order pipeline.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * VAT rate used by the configurator. Reads `baltic_stair_vat_rate` option,
 * defaults to 20%. Replaces the old WC_Tax-backed [vat_rate] shortcode.
 */
function baltic_stair_get_vat_rate() {
	$rate = get_option( 'baltic_stair_vat_rate', 20 );
	return (float) $rate;
}
add_shortcode( 'vat_rate', 'baltic_stair_get_vat_rate' );

/**
 * Is this construction type priced on application?
 *
 * Mirrors the front-end `data-poa` flag (see priceCalc.js), read straight from
 * the admin repeater so the server never depends on the client's word for it.
 *
 * @param string $code Submitted construction_type code.
 * @return bool
 */
function baltic_stair_construction_is_poa( $code ) {
	if ( '' === $code ) {
		return false;
	}
	$rows = stairbuilder_get_option( 'construction_types', array() );
	if ( ! is_array( $rows ) ) {
		return false;
	}
	foreach ( $rows as $row ) {
		if ( is_array( $row ) && isset( $row['construction_code'] ) && (string) $row['construction_code'] === $code ) {
			return ! empty( $row['construction_poa'] );
		}
	}
	return false;
}

/**
 * AJAX: configurator submit. Captures lead → generates PDF → emails →
 * fires action hook → returns redirect URL to thank-you page.
 */
/**
 * Server-side availability revalidation (v2.16.0 Phase 2, §6.3).
 *
 * Re-checks the submitted construction+material combination against the SAME
 * settings the front-end filter uses (strict_for on the construction type,
 * available_for on each material/profile row), in BOTH directions:
 *   - strict repeater      → the row must be tagged for this construction.
 *   - permissive repeater  → a row with a NON-EMPTY available_for must include
 *                            this construction (untagged rows are available to all).
 *
 * Row addressing: material fields arrive (in revalidate_meta, un-stripped) as
 * code:price. code+price identifies the exact row in nearly every case, so the
 * check is ROW-granular for unique-code repeaters (tread_profiles) AND for
 * tread/riser wherever the price disambiguates the shared code.
 *
 * KNOWN GAP (documented, deferred to the stable-row-identity hardening release):
 * two rows sharing BOTH a code and a price are indistinguishable, so the check
 * falls back to code-granular (any such row available → allowed). Acceptable
 * pre-production: no payment, every quote is joiner-checked, and when code+price
 * collide the rows cost the same so the quote is correct regardless — only the
 * thickness label could differ, which the joiner catches. See Baltic Mind finding.
 *
 * FULLY GENERIC: no construction code is special-cased. A licensee with zero tags
 * has empty strict_for and untagged rows, so every combination passes — pre-v2.16
 * behaviour exactly.
 *
 * @param string $revalidate_raw URL-encoded un-stripped payload (revalidate_meta).
 * @return string '' when OK, else a customer-facing rejection message.
 */
function bd_stairbuilder_revalidate_availability( $revalidate_raw ) {
	if ( ! is_string( $revalidate_raw ) || '' === $revalidate_raw ) {
		return ''; // Nothing to check (e.g. an older cached client with no payload).
	}
	parse_str( $revalidate_raw, $rv );
	$ct_code = isset( $rv['construction_type'] ) ? (string) $rv['construction_type'] : '';
	if ( '' === $ct_code ) {
		return '';
	}

	// strict_for for the submitted construction type (and confirm it's real).
	$ct_valid   = false;
	$strict_for = array();
	foreach ( (array) stairbuilder_get_option( 'construction_types', array() ) as $r ) {
		if ( is_array( $r ) && isset( $r['construction_code'] ) && (string) $r['construction_code'] === $ct_code ) {
			$ct_valid   = true;
			$strict_for = ( isset( $r['strict_for'] ) && is_array( $r['strict_for'] ) ) ? array_map( 'strval', $r['strict_for'] ) : array();
			break;
		}
	}
	if ( ! $ct_valid ) {
		return 'Unrecognised construction type.';
	}

	// field name → [ repeater option key, code sub-field, price sub-field ].
	$map = array(
		'stringer_material' => array( 'stringer_types', 'stringer_code', 'stringer_value' ),
		'tread_material'    => array( 'tread_types', 'tread_code', 'tread_value' ),
		'riser_material'    => array( 'riser_types', 'riser_code', 'riser_value' ),
		'tread-profile'     => array( 'tread_profiles', 'tread_profile_code', 'tread_profile_value' ),
	);

	foreach ( $map as $field => $spec ) {
		if ( ! isset( $rv[ $field ] ) || '' === $rv[ $field ] ) {
			continue;
		}
		list( $repeater, $code_key, $value_key ) = $spec;
		$submitted = (string) $rv[ $field ];
		$sub_code  = $submitted;
		$sub_price = null;
		if ( false !== strpos( $submitted, ':' ) ) {
			list( $sub_code, $sub_price ) = explode( ':', $submitted, 2 );
		}

		$by_code       = array();
		$by_code_price = array();
		foreach ( (array) stairbuilder_get_option( $repeater, array() ) as $r ) {
			if ( ! is_array( $r ) || ! isset( $r[ $code_key ] ) || (string) $r[ $code_key ] !== (string) $sub_code ) {
				continue;
			}
			$by_code[] = $r;
			if ( null !== $sub_price && isset( $r[ $value_key ] ) && (string) $r[ $value_key ] === (string) $sub_price ) {
				$by_code_price[] = $r;
			}
		}
		if ( empty( $by_code ) ) {
			return 'A selected material is not available.'; // Unknown / tampered code.
		}
		// Prefer the code+price match (row-granular); fall back to code-granular only
		// when price didn't disambiguate (the documented gap above).
		$candidates = ! empty( $by_code_price ) ? $by_code_price : $by_code;
		$strict     = in_array( $repeater, $strict_for, true );
		$ok         = false;
		foreach ( $candidates as $r ) {
			$af = ( isset( $r['available_for'] ) && is_array( $r['available_for'] ) ) ? array_map( 'strval', $r['available_for'] ) : array();
			if ( $strict ) {
				if ( in_array( $ct_code, $af, true ) ) {
					$ok = true;
					break;
				}
			} elseif ( empty( $af ) || in_array( $ct_code, $af, true ) ) {
				$ok = true;
				break;
			}
		}
		if ( ! $ok ) {
			return 'A selected material is not available for the chosen construction type.';
		}
	}

	return '';
}

function baltic_stair_submit_lead() {
	if ( ! baltic_stair_prepare_raw_response( 'wp_ajax_baltic_stair_submit_lead' ) ) {
		wp_die( '', '', array( 'response' => 500 ) );
	}
	if ( ! isset( $_POST['security'] ) || ! wp_verify_nonce( $_POST['security'], 'sb-ajax-nonce' ) ) {
		wp_send_json_error( array( 'message' => 'Nonce verification failed' ), 403 );
	}

	$name  = isset( $_POST['contact_name'] ) ? sanitize_text_field( wp_unslash( $_POST['contact_name'] ) ) : '';
	$email = isset( $_POST['contact_email'] ) ? sanitize_email( wp_unslash( $_POST['contact_email'] ) ) : '';
	$phone = isset( $_POST['contact_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['contact_phone'] ) ) : '';

	if ( ! $name || ! is_email( $email ) ) {
		wp_send_json_error( array( 'message' => 'Name and a valid email are required.' ), 400 );
	}

	$custom_meta = isset( $_POST['custom_meta'] ) ? wp_unslash( $_POST['custom_meta'] ) : '';
	parse_str( $custom_meta, $form_data );

	// Availability revalidation (v2.16.0 Phase 2, §6.3). Price is client-side, so a
	// tampered POST can carry an impossible construction+material combination. Reject
	// it before the lead is written or a PDF generated — don't silently correct.
	$bd_revalidate_err = bd_stairbuilder_revalidate_availability(
		isset( $_POST['revalidate_meta'] ) ? wp_unslash( $_POST['revalidate_meta'] ) : ''
	);
	if ( '' !== $bd_revalidate_err ) {
		wp_send_json_error( array( 'message' => $bd_revalidate_err ), 422 );
	}

	$price = isset( $_POST['price'] ) ? (float) $_POST['price'] : 0;
	$vat   = isset( $_POST['vat'] ) ? (float) $_POST['vat'] : 0;
	$total = isset( $_POST['total'] ) ? (float) $_POST['total'] : 0;

	// Price on application, resolved from the admin setting rather than trusted
	// from the request — the client can't talk us into hiding (or revealing) a
	// price. The figures above are still stored: the customer's PDF withholds
	// them, but the lead keeps an internal baseline for whoever prices it.
	$poa = baltic_stair_construction_is_poa(
		isset( $form_data['construction_type'] ) ? (string) $form_data['construction_type'] : ''
	);
	// Persisted with the lead so the quote-view page stays faithful to what the
	// customer was actually shown, even if the type is later un-flagged.
	$form_data['_poa'] = $poa ? 1 : 0;

	$postcode = isset( $form_data['postcode'] ) ? sanitize_text_field( $form_data['postcode'] ) : '';

	$lead = BD_Stair_Builder_Leads::create(
		array(
			'name'      => $name,
			'email'     => $email,
			'phone'     => $phone,
			'postcode'  => $postcode,
			'price'     => $price,
			'vat'       => $vat,
			'total'     => $total,
			'form_data' => $form_data,
		)
	);

	if ( is_wp_error( $lead ) ) {
		wp_send_json_error( array( 'message' => $lead->get_error_message() ), 500 );
	}

	// Canvas write moved to AFTER lead creation (v2.24.1): both files now live
	// under the lead's own token directory, and the token does not exist until
	// create() has generated it. The alternative — minting the token in this
	// handler and passing it into create() — would change a method the brief
	// protects, and would invert where a lead's identity comes from. So the
	// canvas is written here and folded back into the stored row.
	$canvas_dataurl = isset( $_POST['canvas_image'] ) ? $_POST['canvas_image'] : '';
	$canvas_path    = baltic_stair_save_canvas_image( $canvas_dataurl, $lead['token'] );
	if ( $canvas_path ) {
		$form_data['canvas_image_path'] = $canvas_path;
		BD_Stair_Builder_Leads::update_form_data( $lead['id'], $form_data );
	}

	$lead_data = array(
		'lead_id'  => $lead['id'],
		'token'    => $lead['token'],
		'name'     => $name,
		'email'    => $email,
		'phone'    => $phone,
		'postcode' => $postcode,
		'price'    => $price,
		'vat'      => $vat,
		'total'    => $total,
		'poa'      => $poa,
		'form'     => $form_data,
	);

	$pdf_path = baltic_stair_generate_pdf( $lead_data );
	if ( $pdf_path ) {
		BD_Stair_Builder_Leads::set_pdf_path( $lead['id'], $pdf_path );
	}

	baltic_stair_send_lead_emails( $lead_data, $pdf_path );

	do_action( 'baltic_stairbuilder_lead_captured', $lead_data, $pdf_path );

	wp_send_json_success(
		array(
			'redirect_url' => baltic_stair_get_quote_view_url( $lead['token'] ),
			'token'        => $lead['token'],
		)
	);
}
add_action( 'wp_ajax_baltic_stair_submit_lead', 'baltic_stair_submit_lead' );
add_action( 'wp_ajax_nopriv_baltic_stair_submit_lead', 'baltic_stair_submit_lead' );

/**
 * Root of the quote-file tree: uploads/stairbuilder_PDFs/.
 */
function baltic_stair_pdf_basedir() {
	$upload = wp_upload_dir();
	return trailingslashit( $upload['basedir'] ) . 'stairbuilder_PDFs/';
}

/**
 * The directory holding one lead's files, created if absent.
 *
 * Named by the lead's token — 48 hex characters from random_bytes(24) — not by
 * its id. THE FILENAME IS THE SECRET. Under the old {lead_id}/quote_{lead_id}
 * scheme the path was sequential, so anyone could walk the ids and read every
 * customer's quote without a token, bypassing the download handler entirely.
 *
 * @return string|null Trailing-slashed path, or null if the token is unusable.
 */
function baltic_stair_lead_dir( $token ) {
	$token = (string) $token;
	// Belt and braces: the column is hex from generate_token(), but this value
	// becomes a filesystem path, so refuse anything that is not.
	if ( ! preg_match( '/^[a-f0-9]{32,64}$/i', $token ) ) {
		return null;
	}

	$dir = baltic_stair_pdf_basedir() . $token . '/';
	if ( ! file_exists( $dir ) ) {
		wp_mkdir_p( $dir );
	}
	baltic_stair_protect_pdf_dir();

	return $dir;
}

/**
 * Drops index.php and .htaccess into the quote-file root.
 *
 * DEFENCE IN DEPTH ONLY — NOT the mechanism. The protection that actually holds
 * is the unguessable token directory from baltic_stair_lead_dir(). These two
 * files do nothing on nginx, which is what SPD staging runs on Kinsta and what
 * a good share of licensees will be on, and a licensed plugin cannot assume the
 * licensee's server config is right. Do not remove the token scheme believing
 * these cover it.
 *
 * Idempotent: only writes what is missing.
 */
function baltic_stair_protect_pdf_dir() {
	$base = baltic_stair_pdf_basedir();
	if ( ! file_exists( $base ) ) {
		wp_mkdir_p( $base );
	}

	$index = $base . 'index.php';
	if ( ! file_exists( $index ) ) {
		file_put_contents( $index, "<?php\n// Silence is golden.\n" );
	}

	$htaccess = $base . '.htaccess';
	if ( ! file_exists( $htaccess ) ) {
		// Apache 2.2 and 2.4 syntax together, so this holds either side of the
		// mod_authz_core split without knowing which is loaded.
		$rules = "<IfModule mod_authz_core.c>\n"
			. "\tRequire all denied\n"
			. "</IfModule>\n"
			. "<IfModule !mod_authz_core.c>\n"
			. "\tOrder deny,allow\n"
			. "\tDeny from all\n"
			. "</IfModule>\n";
		file_put_contents( $htaccess, $rules );
	}
}

/**
 * Decodes a base64 canvas dataURL and writes it into the lead's token
 * directory. Returns absolute path or null if there is no usable image.
 *
 * Was uploads/stairbuilder_PDFs/img/{time}_canvas_{6}.png — already hard to
 * walk, but it sat in the same tree under a second scheme and carried the
 * customer's drawing. One path to reason about now, not two.
 */
function baltic_stair_save_canvas_image( $dataurl, $token ) {
	if ( ! $dataurl ) {
		return null;
	}
	$bytes = base64_decode( preg_replace( '#^data:image/\w+;base64,#i', '', $dataurl ) );
	if ( ! $bytes ) {
		return null;
	}

	$dir = baltic_stair_lead_dir( $token );
	if ( ! $dir ) {
		return null;
	}

	$filename = $dir . 'canvas.png';
	file_put_contents( $filename, $bytes );
	return $filename;
}

/**
 * Generates the PDF for a lead and returns the saved path.
 *
 * Decoupled from $order_id — takes the full lead_data array and renders
 * templates/stairbuilder_pdf.php.
 */
function baltic_stair_generate_pdf( array $lead_data ) {
	if ( ! class_exists( 'Mpdf\Mpdf' ) ) {
		require_once plugin_dir_path( __FILE__ ) . '../vendor/autoload.php';
	}

	// Zero page margins so the quote's header/footer bands run full-bleed.
	// Setting them here (not via `@page { margin: 0 }` in the template) avoids an
	// mPDF divide-by-zero in its nested-table column-width calc. Content columns
	// carry their own padding, so nothing is jammed to the page edge.
	$mpdf = new Mpdf\Mpdf( array(
		'margin_left'   => 0,
		'margin_right'  => 0,
		'margin_top'    => 0,
		'margin_bottom' => 0,
	) );

	$title   = 'Staircase Quote – Ref ' . $lead_data['lead_id'];
	$content = is_array( $lead_data['form'] ) ? $lead_data['form'] : array();
	$content['lead_id']  = $lead_data['lead_id'];
	$content['name']     = $lead_data['name'];
	$content['email']    = $lead_data['email'];
	$content['phone']    = $lead_data['phone'];
	$content['postcode'] = $lead_data['postcode'];
	$content['price']    = $lead_data['price'];
	$content['vat']      = $lead_data['vat'];
	$content['total']    = $lead_data['total'];
	$content['poa']      = ! empty( $lead_data['poa'] );

	ob_start();
	include plugin_dir_path( __FILE__ ) . '../templates/stairbuilder_pdf.php';
	$html = ob_get_clean();

	$mpdf->WriteHTML( $html );

	// Token directory, not lead id — see baltic_stair_lead_dir().
	$dir = baltic_stair_lead_dir( isset( $lead_data['token'] ) ? $lead_data['token'] : '' );
	if ( ! $dir ) {
		return null;
	}

	$pdf_path = $dir . 'quote.pdf';
	$mpdf->Output( $pdf_path, \Mpdf\Output\Destination::FILE );

	return $pdf_path;
}

/**
 * Sends the customer confirmation + admin notification emails. PDF attached
 * to both when available.
 */
function baltic_stair_send_lead_emails( array $lead_data, $pdf_path ) {
	$attachments = ( $pdf_path && file_exists( $pdf_path ) ) ? array( $pdf_path ) : array();
	$site_name   = get_bloginfo( 'name' );

	$customer_subject = sprintf( 'Your %s staircase quote', $site_name );
	$download_url     = baltic_stair_get_quote_view_url( $lead_data['token'] );
	$customer_body    = sprintf(
		"Hi %s,\n\n" .
		"Thanks for using our staircase configurator. Your indicative quote is attached as a PDF.\n\n" .
		"You can also view and re-download your quote here: %s\n\n" .
		"%s\n\n" .
		"We'll be in touch shortly to discuss your requirements.\n\n" .
		"— %s",
		$lead_data['name'],
		$download_url,
		// A POA staircase is quoted by hand, so the customer's copy carries no
		// figure anywhere — the PDF withholds it and so does this.
		empty( $lead_data['poa'] )
			? sprintf( 'Indicative total (inc VAT): £%s', number_format( (float) $lead_data['total'], 2 ) )
			: 'Total: price on application — we\'ll prepare your figure by hand and be in touch.',
		$site_name
	);

	wp_mail( $lead_data['email'], $customer_subject, $customer_body, array(), $attachments );

	// Recipients: the General-tab setting first, the site admin email when it is
	// blank or resolves to nothing usable. The filter runs last and unchanged,
	// so any bespoke code hooking it still wins over the setting.
	$admin_to             = apply_filters( 'baltic_stair_admin_notification_email', baltic_stair_notification_recipients(), $lead_data );
	$project_delivery     = isset( $lead_data['form']['project_delivery_date'] ) ? (string) $lead_data['form']['project_delivery_date'] : '';
	$urgency_line         = $project_delivery !== '' ? sprintf( "Project Delivery Date: %s\n\n", $project_delivery ) : '';
	// The admin copy keeps the computed figures — they're the internal baseline
	// for whoever prices it — but flags that the customer was shown none.
	$poa_flag             = empty( $lead_data['poa'] ) ? '' : ' [PRICE ON APPLICATION]';
	$admin_subject        = sprintf( 'New enquiry: %s — £%s%s', $lead_data['name'], number_format( (float) $lead_data['total'], 2 ), $poa_flag );
	$admin_body    = sprintf(
		"New staircase enquiry captured.\n\n" .
		"%s" .
		"Name: %s\nEmail: %s\nPhone: %s\nPostcode: %s\n\n" .
		"%s" .
		"Indicative subtotal: £%s\nVAT: £%s\nTotal: £%s\n\n" .
		"Lead ref: %d\nView: %s\n",
		$urgency_line,
		$lead_data['name'],
		$lead_data['email'],
		$lead_data['phone'],
		$lead_data['postcode'],
		empty( $lead_data['poa'] ) ? '' : "PRICE ON APPLICATION — the customer was shown no figures. Those below are the configurator's internal calculation only.\n\n",
		number_format( (float) $lead_data['price'], 2 ),
		number_format( (float) $lead_data['vat'], 2 ),
		number_format( (float) $lead_data['total'], 2 ),
		$lead_data['lead_id'],
		$download_url
	);

	wp_mail( $admin_to, $admin_subject, $admin_body, baltic_stair_admin_email_headers( $lead_data ), $attachments );
}

/**
 * Notification recipients for the admin copy.
 *
 * `lead_notification_emails` is a comma-separated list on the General tab.
 * Invalid addresses are dropped rather than sent to; if that leaves nothing,
 * we fall back to the site admin email. An enquiry going to the wrong inbox is
 * recoverable — an enquiry going nowhere is not.
 *
 * @return string|array One address, or an array of them for wp_mail().
 */
function baltic_stair_notification_recipients() {
	$fallback = get_option( 'admin_email' );
	$raw      = function_exists( 'stairbuilder_get_option' ) ? stairbuilder_get_option( 'lead_notification_emails', '' ) : '';

	if ( ! is_string( $raw ) || '' === trim( $raw ) ) {
		return $fallback;
	}

	$valid = array();
	foreach ( explode( ',', $raw ) as $candidate ) {
		$address = sanitize_email( trim( $candidate ) );
		if ( $address && is_email( $address ) ) {
			$valid[] = $address;
		}
	}

	return $valid ? $valid : $fallback;
}

/**
 * Headers for the admin notification.
 *
 * Both emails used to pass array(), so replying to a lead notification replied
 * to the site rather than to the person who sent the enquiry. Reply-To is on by
 * default; a lead with no usable email address gets no header rather than a
 * malformed one.
 */
function baltic_stair_admin_email_headers( array $lead_data ) {
	// Unset means "use the shipped default" (on); a stored 0 means the setting
	// was deliberately turned off. Same rule render_toggle() applies.
	$stored = function_exists( 'stairbuilder_get_option' ) ? stairbuilder_get_option( 'lead_notification_reply_to_customer', null ) : null;
	$enabled = ( null === $stored ) ? true : ! empty( $stored );

	if ( ! $enabled ) {
		return array();
	}

	$email = isset( $lead_data['email'] ) ? sanitize_email( $lead_data['email'] ) : '';
	if ( ! $email || ! is_email( $email ) ) {
		return array();
	}

	$name = isset( $lead_data['name'] ) ? trim( (string) $lead_data['name'] ) : '';
	// A name containing a comma or angle bracket would break the header, so
	// strip those rather than emit something a mail server may reject.
	$name = str_replace( array( ',', '<', '>', '"', "\r", "\n" ), '', $name );

	return array(
		'' !== $name
			? sprintf( 'Reply-To: %s <%s>', $name, $email )
			: sprintf( 'Reply-To: %s', $email ),
	);
}

/**
 * One-shot move of pre-v2.24.1 quote files onto the token path scheme.
 *
 * Leads captured before v2.24.1 have their PDF at {lead_id}/quote_{lead_id}.pdf
 * and their canvas at img/{time}_canvas_{6}.png — the sequential path anyone
 * could walk. Moves both under {token}/ and rewrites the stored paths.
 *
 * Safe to run repeatedly: rows already on the new scheme are skipped, the old
 * file is only unlinked once the new one is confirmed written at the same size,
 * and a row whose file has gone missing is left completely alone — pdf_path
 * intact, so the Enquiries list keeps rendering its muted "no PDF", which is
 * the correct visible state for it.
 *
 * @return array Counts, for the caller to log or assert on.
 */
function baltic_stair_migrate_pdf_paths() {
	global $wpdb;

	$stats = array( 'scanned' => 0, 'pdf_moved' => 0, 'canvas_moved' => 0, 'already' => 0, 'missing' => 0, 'failed' => 0 );

	$rows = $wpdb->get_results( 'SELECT id, token, pdf_path, form_data FROM ' . BD_Stair_Builder_Leads::table_name(), ARRAY_A );
	if ( ! is_array( $rows ) ) {
		return $stats;
	}

	foreach ( $rows as $row ) {
		$stats['scanned']++;

		$dir = baltic_stair_lead_dir( $row['token'] );
		if ( ! $dir ) {
			$stats['failed']++;
			continue;
		}

		$new_pdf    = $dir . 'quote.pdf';
		$new_canvas = $dir . 'canvas.png';

		$form_data = json_decode( (string) $row['form_data'], true );
		if ( ! is_array( $form_data ) ) {
			$form_data = array();
		}

		$old_pdf    = (string) $row['pdf_path'];
		$old_canvas = isset( $form_data['canvas_image_path'] ) ? (string) $form_data['canvas_image_path'] : '';

		$pdf_done    = ( '' !== $old_pdf && $old_pdf === $new_pdf );
		$canvas_done = ( '' === $old_canvas || $old_canvas === $new_canvas );

		if ( $pdf_done && $canvas_done ) {
			$stats['already']++;
			continue;
		}

		// PDF
		if ( ! $pdf_done && '' !== $old_pdf ) {
			if ( ! file_exists( $old_pdf ) ) {
				// Leave the row untouched. A dangling pdf_path is already
				// rendered as "no PDF"; rewriting it to another path that also
				// holds nothing would only move the confusion.
				$stats['missing']++;
			} elseif ( baltic_stair_move_quote_file( $old_pdf, $new_pdf ) ) {
				$wpdb->update(
					BD_Stair_Builder_Leads::table_name(),
					array( 'pdf_path' => $new_pdf ),
					array( 'id' => (int) $row['id'] ),
					array( '%s' ),
					array( '%d' )
				);
				$stats['pdf_moved']++;
			} else {
				$stats['failed']++;
			}
		}

		// Canvas
		if ( ! $canvas_done ) {
			if ( ! file_exists( $old_canvas ) ) {
				$stats['missing']++;
			} elseif ( baltic_stair_move_quote_file( $old_canvas, $new_canvas ) ) {
				$form_data['canvas_image_path'] = $new_canvas;
				BD_Stair_Builder_Leads::update_form_data( (int) $row['id'], $form_data );
				$stats['canvas_moved']++;
			} else {
				$stats['failed']++;
			}
		}

		// Only the directory this lead's PDF just vacated, and only if empty.
		// Nothing recursive: this must never remove something it did not move.
		$old_dir = trailingslashit( dirname( $old_pdf ) );
		if ( '' !== $old_pdf && $old_dir !== $dir && strpos( $old_dir, baltic_stair_pdf_basedir() ) === 0 ) {
			@rmdir( $old_dir );
		}
	}

	// The shared img/ directory goes only once it is genuinely empty.
	@rmdir( baltic_stair_pdf_basedir() . 'img/' );

	return $stats;
}

/**
 * Copy-verify-unlink. Deliberately not rename(): the old file survives until
 * the new one is confirmed present at the same size, so a failure mid-move
 * leaves the customer's quote where the database still points.
 */
function baltic_stair_move_quote_file( $from, $to ) {
	if ( $from === $to ) {
		return true;
	}
	if ( ! @copy( $from, $to ) ) {
		return false;
	}
	if ( ! file_exists( $to ) || filesize( $to ) !== filesize( $from ) ) {
		return false;
	}
	@unlink( $from );
	return true;
}

/**
 * Runs the migration once, then records that it has. The flag keeps it off
 * every subsequent admin request; the function itself is idempotent regardless.
 */
function baltic_stair_maybe_migrate_pdf_paths() {
	if ( get_option( 'baltic_stair_pdf_token_paths_migrated' ) ) {
		return;
	}
	baltic_stair_migrate_pdf_paths();
	update_option( 'baltic_stair_pdf_token_paths_migrated', 1 );
}
add_action( 'admin_init', 'baltic_stair_maybe_migrate_pdf_paths' );

/**
 * Returns the URL the configurator submits to for the thank-you / download
 * view. Looks up `baltic_stair_quote_page_id` option (set on activation).
 * Falls back to home_url() with the token query var so the shortcode can be
 * dropped on any page later.
 */
function baltic_stair_get_quote_view_url( $token ) {
	$page_id = (int) get_option( 'baltic_stair_quote_page_id' );
	if ( $page_id ) {
		$url = get_permalink( $page_id );
		if ( $url ) {
			return add_query_arg( 'baltic_lead', $token, $url );
		}
	}
	return add_query_arg( 'baltic_lead', $token, home_url( '/' ) );
}

/**
 * Shortcode for the thank-you / quote-view page. Reads `?baltic_lead=TOKEN`
 * and renders a simple summary + download button. Place [baltic_stair_quote_view]
 * on the page registered in `baltic_stair_quote_page_id`.
 */
function baltic_stair_quote_view_shortcode() {
	$token = isset( $_GET['baltic_lead'] ) ? sanitize_text_field( wp_unslash( $_GET['baltic_lead'] ) ) : '';
	if ( ! $token ) {
		return '<p>Sorry, we could not find your quote. Please run the configurator again.</p>';
	}

	$lead = BD_Stair_Builder_Leads::get_by_token( $token );
	if ( ! $lead ) {
		return '<p>Sorry, this quote link is no longer valid.</p>';
	}

	$download_url = add_query_arg(
		array(
			'action' => 'baltic_stair_download',
			'token'  => $token,
		),
		admin_url( 'admin-post.php' )
	);

	ob_start();
	?>
	<div class="baltic-stair-quote-view">
		<h2>Thanks, <?php echo esc_html( $lead['name'] ); ?> — your quote is ready.</h2>
		<p>We've also emailed a copy of your PDF quote to <strong><?php echo esc_html( $lead['email'] ); ?></strong>.</p>
		<?php // Mirrors the PDF and the customer email: a POA quote shows no figure. ?>
		<?php $bd_view_poa = ! empty( $lead['form_data']['_poa'] ); ?>
		<?php if ( $bd_view_poa ) : ?>
		<p><strong>Total:</strong> price on application — we'll prepare your figure by hand and be in touch.</p>
		<?php else : ?>
		<p><strong>Indicative total (inc VAT):</strong> £<?php echo esc_html( number_format( (float) $lead['total'], 2 ) ); ?></p>
		<?php endif; ?>
		<p>
			<a class="button button-primary" href="<?php echo esc_url( $download_url ); ?>">Download your PDF quote</a>
		</p>
		<p><small>Quote reference: <?php echo esc_html( $lead['id'] ); ?></small></p>
	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'baltic_stair_quote_view', 'baltic_stair_quote_view_shortcode' );

/**
 * Public download endpoint — streams the lead PDF, gated by token.
 */
function baltic_stair_download_handler() {
	$token = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';
	$lead  = $token ? BD_Stair_Builder_Leads::get_by_token( $token ) : null;
	if ( ! $lead || empty( $lead['pdf_path'] ) || ! file_exists( $lead['pdf_path'] ) ) {
		wp_die( 'Quote PDF not found.', 'Not found', array( 'response' => 404 ) );
	}

	// pdf_path is plugin-written today, so this is not a live hole. It is here
	// so the column can never become one: resolve the real path and refuse
	// anything outside the uploads directory before streaming it.
	$upload   = wp_upload_dir();
	$basedir  = realpath( $upload['basedir'] );
	$realpath = realpath( $lead['pdf_path'] );
	if ( ! $basedir || ! $realpath || strpos( $realpath, trailingslashit( $basedir ) ) !== 0 ) {
		wp_die( 'Quote PDF not found.', 'Not found', array( 'response' => 404 ) );
	}

	// Anything already buffered — a notice from any plugin on the site, a stray
	// newline from a badly closed PHP tag — would be streamed ahead of the PDF
	// AND counted out of Content-Length, so the file would arrive with junk on
	// the front and the same number of bytes missing off the back. Observed on
	// a WP_DEBUG install: 1,227 bytes of unrelated textdomain notices landed
	// before %PDF and every reader rejected the download.
	//
	// v2.24.2: was a bespoke while/ob_end_clean here. Now the shared helper, so
	// there is one implementation rather than two that drift apart.
	if ( ! baltic_stair_prepare_raw_response( 'admin_post_baltic_stair_download' ) ) {
		wp_die( 'Download unavailable.', 'Error', array( 'response' => 500 ) );
	}

	nocache_headers();
	header( 'Content-Type: application/pdf' );
	header( 'Content-Disposition: attachment; filename="quote_' . (int) $lead['id'] . '.pdf"' );
	// Only now that the buffer is confirmed clean is filesize() the true body
	// length. A wrong Content-Length truncates silently; no Content-Length just
	// ends the stream, which is the better failure — so send it only when the
	// figure is trustworthy.
	$size = filesize( $realpath );
	if ( false !== $size ) {
		header( 'Content-Length: ' . $size );
	}
	readfile( $realpath );
	exit;
}
add_action( 'admin_post_baltic_stair_download', 'baltic_stair_download_handler' );
add_action( 'admin_post_nopriv_baltic_stair_download', 'baltic_stair_download_handler' );

/**
 * Auto-create the thank-you page on activation if it doesn't exist yet,
 * and stash its ID. Idempotent.
 */
function baltic_stair_install_quote_page() {
	$existing = (int) get_option( 'baltic_stair_quote_page_id' );
	if ( $existing && get_post( $existing ) ) {
		return;
	}

	$page_id = wp_insert_post(
		array(
			'post_title'   => 'Your Staircase Quote',
			'post_content' => '[baltic_stair_quote_view]',
			'post_status'  => 'publish',
			'post_type'    => 'page',
			'post_name'    => 'staircase-quote',
		)
	);

	if ( $page_id && ! is_wp_error( $page_id ) ) {
		update_option( 'baltic_stair_quote_page_id', (int) $page_id );
	}
}
