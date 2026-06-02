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
 * AJAX: configurator submit. Captures lead → generates PDF → emails →
 * fires action hook → returns redirect URL to thank-you page.
 */
function baltic_stair_submit_lead() {
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

	$price = isset( $_POST['price'] ) ? (float) $_POST['price'] : 0;
	$vat   = isset( $_POST['vat'] ) ? (float) $_POST['vat'] : 0;
	$total = isset( $_POST['total'] ) ? (float) $_POST['total'] : 0;

	$canvas_dataurl = isset( $_POST['canvas_image'] ) ? $_POST['canvas_image'] : '';
	$canvas_path    = baltic_stair_save_canvas_image( $canvas_dataurl );
	if ( $canvas_path ) {
		$form_data['canvas_image_path'] = $canvas_path;
	}

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
 * Decodes a base64 canvas dataURL and writes it into uploads/stairbuilder_PDFs/img/.
 * Returns absolute path or null if no image.
 */
function baltic_stair_save_canvas_image( $dataurl ) {
	if ( ! $dataurl ) {
		return null;
	}
	$bytes = base64_decode( preg_replace( '#^data:image/\w+;base64,#i', '', $dataurl ) );
	if ( ! $bytes ) {
		return null;
	}

	$upload   = wp_upload_dir();
	$dir      = trailingslashit( $upload['basedir'] ) . 'stairbuilder_PDFs/img/';
	if ( ! file_exists( $dir ) ) {
		wp_mkdir_p( $dir );
	}
	$filename = $dir . time() . '_canvas_' . wp_generate_password( 6, false ) . '.png';
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

	$mpdf = new Mpdf\Mpdf();

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

	ob_start();
	include plugin_dir_path( __FILE__ ) . '../templates/stairbuilder_pdf.php';
	$html = ob_get_clean();

	$mpdf->WriteHTML( $html );

	$upload   = wp_upload_dir();
	$dir      = trailingslashit( $upload['basedir'] ) . 'stairbuilder_PDFs/' . $lead_data['lead_id'] . '/';
	if ( ! file_exists( $dir ) ) {
		wp_mkdir_p( $dir );
	}
	$pdf_path = $dir . 'quote_' . $lead_data['lead_id'] . '.pdf';
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
		"Indicative total (inc VAT): £%s\n\n" .
		"We'll be in touch shortly to discuss your requirements.\n\n" .
		"— %s",
		$lead_data['name'],
		$download_url,
		number_format( (float) $lead_data['total'], 2 ),
		$site_name
	);

	wp_mail( $lead_data['email'], $customer_subject, $customer_body, array(), $attachments );

	$admin_to             = apply_filters( 'baltic_stair_admin_notification_email', get_option( 'admin_email' ), $lead_data );
	$project_delivery     = isset( $lead_data['form']['project_delivery_date'] ) ? (string) $lead_data['form']['project_delivery_date'] : '';
	$urgency_line         = $project_delivery !== '' ? sprintf( "Project Delivery Date: %s\n\n", $project_delivery ) : '';
	$admin_subject        = sprintf( 'New enquiry: %s — £%s', $lead_data['name'], number_format( (float) $lead_data['total'], 2 ) );
	$admin_body    = sprintf(
		"New staircase enquiry captured.\n\n" .
		"%s" .
		"Name: %s\nEmail: %s\nPhone: %s\nPostcode: %s\n\n" .
		"Indicative subtotal: £%s\nVAT: £%s\nTotal: £%s\n\n" .
		"Lead ref: %d\nView: %s\n",
		$urgency_line,
		$lead_data['name'],
		$lead_data['email'],
		$lead_data['phone'],
		$lead_data['postcode'],
		number_format( (float) $lead_data['price'], 2 ),
		number_format( (float) $lead_data['vat'], 2 ),
		number_format( (float) $lead_data['total'], 2 ),
		$lead_data['lead_id'],
		$download_url
	);

	wp_mail( $admin_to, $admin_subject, $admin_body, array(), $attachments );
}

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
		<p><strong>Indicative total (inc VAT):</strong> £<?php echo esc_html( number_format( (float) $lead['total'], 2 ) ); ?></p>
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

	nocache_headers();
	header( 'Content-Type: application/pdf' );
	header( 'Content-Disposition: attachment; filename="quote_' . (int) $lead['id'] . '.pdf"' );
	header( 'Content-Length: ' . filesize( $lead['pdf_path'] ) );
	readfile( $lead['pdf_path'] );
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
