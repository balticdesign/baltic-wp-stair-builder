<?php
/**
 * Settings import/export (Phase 3, v2.37.0).
 *
 * Moves every price and setting between sites as one JSON file: export from
 * staging, import on production — or between any two licensee sites — without
 * re-keying anything. All settings already live in the single
 * `stairbuilder_options` blob and `Stairbuilder_Pricing_Settings::sanitize()`
 * rebuilds that blob from the schema, so an import is "decode, sanitise,
 * save" plus safety rails:
 *
 *   - upload → validate (format, checksum, schema version) → transient
 *   - preview (per-tab change counts, per-field diffs, warnings) → confirm
 *   - backup current settings → write through the registered sanitiser once
 *   - restore-from-backup escape hatch
 *
 * SaaS-safe: nothing SPD-specific. Site-local runtime state (migration
 * flags, the live reference counter, the quote page id, the backup itself)
 * is never exported — those describe THIS install, not the configuration.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BD_Stair_Builder_Import_Export {

	const PAGE_SLUG      = 'stairbuilder-import-export';
	const FORMAT         = 'baltic-stairbuilder-settings';
	const FORMAT_VERSION = 1;

	/** Options holding runtime state around an import. Autoload off on both. */
	const BACKUP_OPTION      = 'stairbuilder_options_backup';
	const LAST_IMPORT_OPTION = 'stairbuilder_last_import';

	/** Upload cap. Real exports are ~100KB; 2MB leaves room for growth only. */
	const MAX_UPLOAD_BYTES = 2097152;

	/** How long a validated upload waits for its preview to be confirmed. */
	const TRANSIENT_TTL = 900; // 15 minutes

	public function __construct() {
		// Priority 11: after Stairbuilder_Pricing_Settings::add_menu() has
		// created the parent menu this submenu attaches to.
		add_action( 'admin_menu', array( $this, 'add_menu' ), 11 );
		add_action( 'admin_post_baltic_stair_settings_export', array( $this, 'handle_export' ) );
		add_action( 'admin_post_baltic_stair_settings_import_upload', array( $this, 'handle_upload' ) );
		add_action( 'admin_post_baltic_stair_settings_import_apply', array( $this, 'handle_apply' ) );
		add_action( 'admin_post_baltic_stair_settings_import_cancel', array( $this, 'handle_cancel' ) );
		add_action( 'admin_post_baltic_stair_settings_restore', array( $this, 'handle_restore' ) );
		add_action( 'admin_post_baltic_stair_settings_backup_download', array( $this, 'handle_backup_download' ) );
	}

	public function add_menu() {
		add_submenu_page(
			Stairbuilder_Pricing_Settings::PAGE_SLUG,
			__( 'Import / Export Settings', 'baltic-wp-stair-builder' ),
			__( 'Import / Export', 'baltic-wp-stair-builder' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/* ---------------------------------------------------------------- */
	/* Shared helpers                                                     */
	/* ---------------------------------------------------------------- */

	private function page_url( $args = array() ) {
		return add_query_arg(
			array_merge( array( 'page' => self::PAGE_SLUG ), $args ),
			admin_url( 'admin.php' )
		);
	}

	private function transient_key() {
		return 'baltic_stair_import_' . get_current_user_id();
	}

	/** The canonical byte string the checksum is computed over. */
	private function checksum( $options ) {
		return hash( 'sha256', (string) wp_json_encode( $options ) );
	}

	/** Schema access — tabs of ['label' => ..., 'fields' => [...]]. */
	private function schema() {
		static $schema = null;
		if ( null === $schema ) {
			$ref = new ReflectionMethod( 'Stairbuilder_Pricing_Settings', 'get_schema' );
			$ref->setAccessible( true );
			$schema = $ref->invoke( ( new ReflectionClass( 'Stairbuilder_Pricing_Settings' ) )->newInstanceWithoutConstructor() );
		}
		return is_array( $schema ) ? $schema : array();
	}

	private function require_caps_and_nonce( $action ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage these settings.', 'baltic-wp-stair-builder' ), '', array( 'response' => 403 ) );
		}
		$nonce = isset( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, $action ) ) {
			wp_die( esc_html__( 'Security check failed — please go back and try again.', 'baltic-wp-stair-builder' ), '', array( 'response' => 403 ) );
		}
	}

	private function redirect_with_notice( $code, $args = array() ) {
		wp_safe_redirect( $this->page_url( array_merge( array( 'sb_ie_notice' => $code ), $args ) ) );
		exit;
	}

	/* ---------------------------------------------------------------- */
	/* Export                                                             */
	/* ---------------------------------------------------------------- */

	public function handle_export() {
		$this->require_caps_and_nonce( 'baltic_stair_settings_export' );

		$payload = $this->build_export_payload();

		if ( ! baltic_stair_prepare_raw_response( 'admin_post_baltic_stair_settings_export' ) ) {
			wp_die( esc_html__( 'Export unavailable: output already started. Reload and try again.', 'baltic-wp-stair-builder' ), '', array( 'response' => 500 ) );
		}

		$host     = wp_parse_url( home_url(), PHP_URL_HOST );
		$filename = sprintf( 'stairbuilder-settings-%s-%s.json', $host ? $host : 'site', gmdate( 'Ymd-Hi' ) );

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		echo wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		exit;
	}

	/** The export payload, exactly as it is written to the download. */
	public function build_export_payload() {
		$options = get_option( Stairbuilder_Pricing_Settings::OPTION_KEY, array() );
		if ( ! is_array( $options ) ) {
			$options = array();
		}

		$payload = array(
			'format'         => self::FORMAT,
			'format_version' => self::FORMAT_VERSION,
			'plugin_version' => BALTIC_STAIRBUILDER_VERSION,
			'schema_version' => Stairbuilder_Pricing_Settings::SCHEMA_VERSION,
			'exported_at'    => gmdate( 'Y-m-d\TH:i:s\Z' ),
			'source_site'    => home_url(),
			'options'        => $options,
			'extra'          => array(),
			'attachments'    => array(),
			'checksum'       => $this->checksum( $options ),
		);

		// Only carried when actually set — a clean install exports no extra.
		$vat = get_option( 'baltic_stair_vat_rate', null );
		if ( null !== $vat && false !== $vat ) {
			$payload['extra']['baltic_stair_vat_rate'] = (float) $vat;
		}

		// The logo travels as identity, not bytes: id + url + filename let the
		// importing site decide whether its attachment with that ID really is
		// the same image (no sideloading in format v1).
		$logo_id = isset( $options['pdf_logo'] ) ? absint( $options['pdf_logo'] ) : 0;
		if ( $logo_id ) {
			$file = get_attached_file( $logo_id );
			$payload['attachments']['pdf_logo'] = array(
				'id'       => $logo_id,
				'url'      => (string) wp_get_attachment_url( $logo_id ),
				'filename' => $file ? wp_basename( $file ) : '',
			);
		}

		return $payload;
	}

	/* ---------------------------------------------------------------- */
	/* Import step 1 — upload and validate                                */
	/* ---------------------------------------------------------------- */

	/**
	 * Structural validation of a decoded payload. Returns '' when valid,
	 * otherwise the notice code the upload handler redirects with.
	 */
	public function validate_payload( $payload ) {
		if ( ! is_array( $payload )
			|| ! isset( $payload['format'] ) || self::FORMAT !== $payload['format']
			|| ! isset( $payload['options'] ) || ! is_array( $payload['options'] ) ) {
			return 'bad_format';
		}
		if ( ! isset( $payload['checksum'] ) || ! hash_equals( $this->checksum( $payload['options'] ), (string) $payload['checksum'] ) ) {
			return 'bad_checksum';
		}
		$incoming_schema = isset( $payload['schema_version'] ) ? (int) $payload['schema_version'] : 0;
		if ( Stairbuilder_Pricing_Settings::SCHEMA_VERSION !== $incoming_schema ) {
			return 'schema_mismatch';
		}
		return '';
	}

	// phpcs:disable WordPress.Security.NonceVerification.Missing -- every handler's first call, require_caps_and_nonce(), wp_verify_nonce()s before any input is touched; the sniff cannot see through the helper.
	public function handle_upload() {
		$this->require_caps_and_nonce( 'baltic_stair_settings_import_upload' );

		if ( empty( $_FILES['sb_import_file'] ) || ! isset( $_FILES['sb_import_file']['tmp_name'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- existence check only; the upload is structurally validated below.
			$this->redirect_with_notice( 'no_file' );
		}
		// Structural validation below (size, extension, JSON shape, checksum)
		// is the sanitisation; the payload never touches the DB unsanitised.
		$file = $_FILES['sb_import_file']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		if ( ! empty( $file['error'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
			$this->redirect_with_notice( 'upload_error' );
		}
		if ( (int) $file['size'] > self::MAX_UPLOAD_BYTES ) {
			$this->redirect_with_notice( 'too_large' );
		}
		$name = isset( $file['name'] ) ? (string) $file['name'] : '';
		if ( strtolower( pathinfo( $name, PATHINFO_EXTENSION ) ) !== 'json' ) {
			$this->redirect_with_notice( 'not_json' );
		}

		$body    = (string) file_get_contents( $file['tmp_name'] );
		$payload = json_decode( $body, true, 32 );

		// v1 requires an exact schema match: an older payload would skip the
		// flag-keyed repeater migrations, a newer one carries fields this
		// site doesn't know. Update the older site first.
		$invalid = $this->validate_payload( $payload );
		if ( 'schema_mismatch' === $invalid ) {
			$this->redirect_with_notice( 'schema_mismatch', array( 'sb_ie_schema' => isset( $payload['schema_version'] ) ? (int) $payload['schema_version'] : 0 ) );
		}
		if ( '' !== $invalid ) {
			$this->redirect_with_notice( $invalid );
		}

		set_transient( $this->transient_key(), $payload, self::TRANSIENT_TTL );
		wp_safe_redirect( $this->page_url( array( 'step' => 'preview' ) ) );
		exit;
	}

	public function handle_cancel() {
		$this->require_caps_and_nonce( 'baltic_stair_settings_import_cancel' );
		delete_transient( $this->transient_key() );
		$this->redirect_with_notice( 'cancelled' );
	}

	/* ---------------------------------------------------------------- */
	/* Preview analysis (shared by preview render and apply)              */
	/* ---------------------------------------------------------------- */

	/**
	 * Everything the preview screen shows and the apply step acts on:
	 * per-tab diffs plus the pdf_logo / product-ID / email / reference
	 * warnings. Computed from the transient payload against live options.
	 */
	private function analyse( $payload ) {
		$current = get_option( Stairbuilder_Pricing_Settings::OPTION_KEY, array() );
		if ( ! is_array( $current ) ) {
			$current = array();
		}
		$incoming = $payload['options'];
		$a        = array(
			'tabs'            => array(),
			'total_changes'   => 0,
			'logo'            => null,
			'missing_products' => array(),
			'wc_inactive_ids' => 0,
			'emails'          => null,
			'reference'       => array(),
			'version_differs' => false,
		);

		foreach ( $this->schema() as $slug => $tab ) {
			$changes = array();
			foreach ( $tab['fields'] as $field ) {
				$id  = $field['id'];
				$cur = isset( $current[ $id ] ) ? $current[ $id ] : null;
				$new = isset( $incoming[ $id ] ) ? $incoming[ $id ] : null;
				if ( wp_json_encode( $cur ) === wp_json_encode( $new ) ) {
					continue;
				}
				if ( isset( $field['type'] ) && 'repeater' === $field['type'] ) {
					$changes[] = array(
						'label' => $field['label'],
						'from'  => sprintf( '%d rows', is_array( $cur ) ? count( $cur ) : 0 ),
						'to'    => sprintf( '%d rows', is_array( $new ) ? count( $new ) : 0 ),
					);
				} else {
					$changes[] = array(
						'label' => $field['label'],
						'from'  => $this->display_value( $cur ),
						'to'    => $this->display_value( $new ),
					);
				}
			}
			if ( $changes ) {
				$a['tabs'][ $slug ] = array( 'label' => $tab['label'], 'changes' => $changes );
				$a['total_changes'] += count( $changes );
			}
		}

		// VAT rate rides along outside the blob.
		if ( isset( $payload['extra']['baltic_stair_vat_rate'] ) ) {
			$cur_vat = get_option( 'baltic_stair_vat_rate', null );
			$new_vat = (float) $payload['extra']['baltic_stair_vat_rate'];
			if ( null === $cur_vat || (float) $cur_vat !== $new_vat ) {
				$a['tabs']['_extra'] = array(
					'label'   => __( 'Other', 'baltic-wp-stair-builder' ),
					'changes' => array( array(
						'label' => __( 'VAT rate', 'baltic-wp-stair-builder' ),
						'from'  => $this->display_value( null === $cur_vat ? null : (float) $cur_vat ),
						'to'    => $this->display_value( $new_vat ),
					) ),
				);
				$a['total_changes']++;
			}
		}

		// pdf_logo: keep the ID only when this site's attachment with that ID
		// is demonstrably the same file; otherwise clear and say so.
		$logo_id = isset( $incoming['pdf_logo'] ) ? absint( $incoming['pdf_logo'] ) : 0;
		if ( $logo_id ) {
			$meta     = isset( $payload['attachments']['pdf_logo'] ) && is_array( $payload['attachments']['pdf_logo'] )
				? $payload['attachments']['pdf_logo'] : array();
			$expected = isset( $meta['filename'] ) ? (string) $meta['filename'] : '';
			$local    = get_attached_file( $logo_id );
			$keep     = $local && '' !== $expected && wp_basename( $local ) === $expected;
			$a['logo'] = array( 'id' => $logo_id, 'keep' => $keep, 'filename' => $expected );
		}

		// Product IDs anywhere in the incoming blob — top-level product_id
		// fields and *_id subfields inside repeaters (Use-Product-ID rows).
		foreach ( $this->collect_product_ids( $incoming ) as $ref ) {
			if ( ! function_exists( 'wc_get_product' ) ) {
				$a['wc_inactive_ids']++;
				continue;
			}
			$product = wc_get_product( $ref['id'] );
			if ( ! $product ) {
				$a['missing_products'][] = $ref;
			}
		}

		// Notification emails: staging exports usually hold test addresses.
		$cur_emails = isset( $current['lead_notification_emails'] ) ? (string) $current['lead_notification_emails'] : '';
		$new_emails = isset( $incoming['lead_notification_emails'] ) ? (string) $incoming['lead_notification_emails'] : '';
		if ( $cur_emails !== $new_emails ) {
			$a['emails'] = array( 'current' => $cur_emails, 'incoming' => $new_emails );
		}

		$a['reference'] = array(
			'cur_prefix' => isset( $current['reference_prefix'] ) ? (string) $current['reference_prefix'] : '',
			'new_prefix' => isset( $incoming['reference_prefix'] ) ? (string) $incoming['reference_prefix'] : '',
			'cur_start'  => isset( $current['reference_start'] ) ? (string) $current['reference_start'] : '',
			'new_start'  => isset( $incoming['reference_start'] ) ? (string) $incoming['reference_start'] : '',
			'counter'    => get_option( 'baltic_stair_reference_next', false ),
		);

		$a['version_differs'] = isset( $payload['plugin_version'] )
			&& (string) $payload['plugin_version'] !== BALTIC_STAIRBUILDER_VERSION;

		return $a;
	}

	/** Every product ID present in an options blob, with a human label. */
	private function collect_product_ids( $options ) {
		$refs = array();
		foreach ( $this->schema() as $tab ) {
			foreach ( $tab['fields'] as $field ) {
				$id = $field['id'];
				if ( ! isset( $options[ $id ] ) ) {
					continue;
				}
				if ( isset( $field['type'] ) && 'product_id' === $field['type'] ) {
					$pid = absint( $options[ $id ] );
					if ( $pid ) {
						$refs[] = array( 'id' => $pid, 'label' => $field['label'] );
					}
				}
				if ( isset( $field['type'] ) && 'repeater' === $field['type'] && is_array( $options[ $id ] ) ) {
					$sub_ids = array();
					foreach ( ( isset( $field['subfields'] ) ? $field['subfields'] : array() ) as $sf ) {
						if ( isset( $sf['type'] ) && 'product_id' === $sf['type'] ) {
							$sub_ids[ $sf['id'] ] = $sf['label'];
						}
					}
					if ( ! $sub_ids ) {
						continue;
					}
					foreach ( $options[ $id ] as $row ) {
						if ( ! is_array( $row ) || empty( $row['use_product_id'] ) ) {
							continue;
						}
						$row_name = isset( $row['name'] ) && '' !== $row['name'] ? (string) $row['name'] : ( isset( $row['code'] ) ? (string) $row['code'] : '?' );
						foreach ( $sub_ids as $sid => $slabel ) {
							$pid = isset( $row[ $sid ] ) ? absint( $row[ $sid ] ) : 0;
							if ( $pid ) {
								$refs[] = array( 'id' => $pid, 'label' => $field['label'] . ' — ' . $row_name . ' — ' . $slabel );
							}
						}
					}
				}
			}
		}
		return $refs;
	}

	private function display_value( $value ) {
		if ( null === $value || '' === $value ) {
			return '—';
		}
		if ( is_bool( $value ) || ( is_numeric( $value ) && ( 0 === (int) $value || 1 === (int) $value ) && is_int( $value + 0 ) ) ) {
			// Toggles store 1/0.
		}
		if ( is_array( $value ) ) {
			return sprintf( '[%d items]', count( $value ) );
		}
		$s = (string) $value;
		return strlen( $s ) > 60 ? substr( $s, 0, 57 ) . '…' : $s;
	}

	/* ---------------------------------------------------------------- */
	/* Import step 3 — apply                                              */
	/* ---------------------------------------------------------------- */

	public function handle_apply() {
		$this->require_caps_and_nonce( 'baltic_stair_settings_import_apply' );

		$payload = get_transient( $this->transient_key() );
		if ( ! is_array( $payload ) || ! isset( $payload['options'] ) ) {
			$this->redirect_with_notice( 'expired' );
		}

		$this->apply_payload( $payload, ! empty( $_POST['sb_keep_emails'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified by require_caps_and_nonce() above.

		// Done with the staged payload.
		delete_transient( $this->transient_key() );

		$this->redirect_with_notice( 'imported' );
	}

	/**
	 * The write path: backup, adjust, sanitise once, record. Returns the
	 * sanitiser error messages (already stashed for the result screen).
	 */
	public function apply_payload( $payload, $keep_emails ) {
		$analysis = $this->analyse( $payload );
		$incoming = $payload['options'];
		$current  = get_option( Stairbuilder_Pricing_Settings::OPTION_KEY, array() );

		// 1. Backup first (latest only, autoload off). The restore path and
		//    the download link both read this.
		update_option(
			self::BACKUP_OPTION,
			array(
				'options'  => is_array( $current ) ? $current : array(),
				'vat_rate' => get_option( 'baltic_stair_vat_rate', null ),
				'time'     => time(),
				'user_id'  => get_current_user_id(),
			),
			false
		);

		// 2. Per-preview adjustments.
		if ( $keep_emails ) {
			$incoming['lead_notification_emails'] = isset( $current['lead_notification_emails'] )
				? $current['lead_notification_emails'] : '';
		}
		if ( is_array( $analysis['logo'] ) && ! $analysis['logo']['keep'] ) {
			$incoming['pdf_logo'] = '';
		}

		// 3. One pass through the schema sanitiser. register_setting() (on
		//    admin_init, which admin-post.php fires) attached sanitize() as the
		//    option's sanitize_callback, so update_option() runs it exactly
		//    once. The has_filter guard covers any context where it didn't.
		$sanitised_explicitly = false;
		if ( ! has_filter( 'sanitize_option_' . Stairbuilder_Pricing_Settings::OPTION_KEY ) ) {
			$settings = new ReflectionClass( 'Stairbuilder_Pricing_Settings' );
			$instance = $settings->newInstanceWithoutConstructor();
			$prop     = $settings->getProperty( 'schema' );
			$prop->setAccessible( true );
			$method   = $settings->getMethod( 'get_schema' );
			$method->setAccessible( true );
			$prop->setValue( $instance, $method->invoke( $instance ) );
			$incoming = $instance->sanitize( $incoming );
			$sanitised_explicitly = true;
		}
		update_option( Stairbuilder_Pricing_Settings::OPTION_KEY, $incoming );
		unset( $sanitised_explicitly );

		// 4. VAT rate from extra, cast to float.
		if ( isset( $payload['extra']['baltic_stair_vat_rate'] ) ) {
			update_option( 'baltic_stair_vat_rate', (float) $payload['extra']['baltic_stair_vat_rate'] );
		}

		// 5. Surface sanitiser rejections (e.g. out-of-range numbers kept at
		//    their current value) on the result screen rather than swallowing
		//    them. get_settings_errors() collects what sanitize() added.
		$errors   = array();
		foreach ( get_settings_errors( Stairbuilder_Pricing_Settings::OPTION_KEY ) as $err ) {
			if ( 'error' === $err['type'] ) {
				$errors[] = $err['message'];
			}
		}
		set_transient( $this->transient_key() . '_result', $errors, 300 );


		// 6. Provenance record shown on this page.
		update_option(
			self::LAST_IMPORT_OPTION,
			array(
				'user_id'        => get_current_user_id(),
				'time'           => time(),
				'source_site'    => isset( $payload['source_site'] ) ? (string) $payload['source_site'] : '',
				'plugin_version' => isset( $payload['plugin_version'] ) ? (string) $payload['plugin_version'] : '',
			),
			false
		);

		return $errors;
	}

	/* ---------------------------------------------------------------- */
	/* Restore                                                            */
	/* ---------------------------------------------------------------- */

	public function handle_restore() {
		$this->require_caps_and_nonce( 'baltic_stair_settings_restore' );

		$backup = get_option( self::BACKUP_OPTION );
		if ( ! is_array( $backup ) || ! isset( $backup['options'] ) || ! is_array( $backup['options'] ) ) {
			$this->redirect_with_notice( 'no_backup' );
		}

		// Same write path as an import: through the registered sanitiser.
		update_option( Stairbuilder_Pricing_Settings::OPTION_KEY, $backup['options'] );
		if ( array_key_exists( 'vat_rate', $backup ) && null !== $backup['vat_rate'] && false !== $backup['vat_rate'] ) {
			update_option( 'baltic_stair_vat_rate', (float) $backup['vat_rate'] );
		}

		$this->redirect_with_notice( 'restored' );
	}

	public function handle_backup_download() {
		$this->require_caps_and_nonce( 'baltic_stair_settings_backup_download' );

		$backup = get_option( self::BACKUP_OPTION );
		if ( ! is_array( $backup ) || ! isset( $backup['options'] ) ) {
			$this->redirect_with_notice( 'no_backup' );
		}
		if ( ! baltic_stair_prepare_raw_response( 'admin_post_baltic_stair_settings_backup_download' ) ) {
			wp_die( esc_html__( 'Download unavailable: output already started. Reload and try again.', 'baltic-wp-stair-builder' ), '', array( 'response' => 500 ) );
		}
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="stairbuilder-settings-backup.json"' );
		echo wp_json_encode( $backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		exit;
	}

	/* ---------------------------------------------------------------- */
	/* Screens                                                            */
	/* ---------------------------------------------------------------- */

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		echo '<div class="wrap"><h1>' . esc_html__( 'Stairbuilder — Import / Export Settings', 'baltic-wp-stair-builder' ) . '</h1>';

		$this->render_notice();

		$step    = isset( $_GET['step'] ) ? sanitize_key( wp_unslash( $_GET['step'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display state only
		$payload = get_transient( $this->transient_key() );

		if ( 'preview' === $step && is_array( $payload ) ) {
			$this->render_preview( $payload );
		} else {
			$this->render_main();
		}

		echo '</div>';
	}

	private function render_notice() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- notices are display state on redirects, no action taken.
		$code = isset( $_GET['sb_ie_notice'] ) ? sanitize_key( wp_unslash( $_GET['sb_ie_notice'] ) ) : '';
		// phpcs:enable
		if ( '' === $code ) {
			return;
		}
		$errors = array(
			'no_file'         => __( 'No file received — choose a settings JSON file first.', 'baltic-wp-stair-builder' ),
			'upload_error'    => __( 'The upload failed — please try again.', 'baltic-wp-stair-builder' ),
			'too_large'       => __( 'That file is larger than 2MB, which no settings export is. Rejected.', 'baltic-wp-stair-builder' ),
			'not_json'        => __( 'That is not a .json file.', 'baltic-wp-stair-builder' ),
			'bad_format'      => __( 'That file is not a Baltic Stairbuilder settings export.', 'baltic-wp-stair-builder' ),
			'bad_checksum'    => __( 'Checksum mismatch — the file has been edited or truncated since it was exported. Re-export it and try again.', 'baltic-wp-stair-builder' ),
			'expired'         => __( 'The staged import expired (15 minutes) — upload the file again.', 'baltic-wp-stair-builder' ),
			'no_backup'       => __( 'There is no import backup on this site.', 'baltic-wp-stair-builder' ),
		);
		if ( 'schema_mismatch' === $code ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$got = isset( $_GET['sb_ie_schema'] ) ? (int) $_GET['sb_ie_schema'] : 0;
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html( sprintf(
					/* translators: 1: schema version in the file, 2: this site's schema version */
					__( 'This file uses settings schema version %1$d but this site is on version %2$d. Both sites must run the same plugin version — update the older site first, then re-export.', 'baltic-wp-stair-builder' ),
					$got,
					Stairbuilder_Pricing_Settings::SCHEMA_VERSION
				) )
			);
			return;
		}
		if ( isset( $errors[ $code ] ) ) {
			printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html( $errors[ $code ] ) );
			return;
		}
		if ( 'imported' === $code ) {
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Settings imported.', 'baltic-wp-stair-builder' ) . '</p></div>';
			$errors_shown = get_transient( $this->transient_key() . '_result' );
			delete_transient( $this->transient_key() . '_result' );
			if ( is_array( $errors_shown ) && $errors_shown ) {
				echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'Some values were rejected by validation and kept at their previous setting:', 'baltic-wp-stair-builder' ) . '</strong></p><ul style="list-style:disc;margin-left:2em;">';
				foreach ( $errors_shown as $msg ) {
					echo '<li>' . wp_kses_post( $msg ) . '</li>';
				}
				echo '</ul></div>';
			}
		} elseif ( 'restored' === $code ) {
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Settings restored from the pre-import backup.', 'baltic-wp-stair-builder' ) . '</p></div>';
		} elseif ( 'cancelled' === $code ) {
			echo '<div class="notice notice-info"><p>' . esc_html__( 'Import cancelled — nothing was changed.', 'baltic-wp-stair-builder' ) . '</p></div>';
		}
	}

	private function render_main() {
		// -- Export ----------------------------------------------------
		echo '<h2>' . esc_html__( 'Export', 'baltic-wp-stair-builder' ) . '</h2>';
		echo '<p>' . esc_html__( 'Download every Stairbuilder price and setting as one JSON file, for import on another site running the same plugin version.', 'baltic-wp-stair-builder' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="baltic_stair_settings_export" />';
		wp_nonce_field( 'baltic_stair_settings_export' );
		submit_button( __( 'Download settings export', 'baltic-wp-stair-builder' ), 'primary', 'submit', false );
		echo '</form>';

		// -- Import ----------------------------------------------------
		echo '<hr /><h2>' . esc_html__( 'Import', 'baltic-wp-stair-builder' ) . '</h2>';
		echo '<p>' . esc_html__( 'Upload a settings export. Nothing is written at this step — you review every change on a preview screen first, and the current settings are backed up before anything is applied.', 'baltic-wp-stair-builder' ) . '</p>';
		echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="baltic_stair_settings_import_upload" />';
		wp_nonce_field( 'baltic_stair_settings_import_upload' );
		echo '<input type="file" name="sb_import_file" accept=".json,application/json" required /> ';
		submit_button( __( 'Upload and preview', 'baltic-wp-stair-builder' ), 'secondary', 'submit', false );
		echo '</form>';

		// -- Last import / restore --------------------------------------
		$last = get_option( self::LAST_IMPORT_OPTION );
		if ( is_array( $last ) && ! empty( $last['time'] ) ) {
			$user = get_userdata( (int) $last['user_id'] );
			echo '<hr /><h2>' . esc_html__( 'Last import', 'baltic-wp-stair-builder' ) . '</h2><p>';
			echo esc_html( sprintf(
				/* translators: 1: date, 2: username, 3: source site, 4: plugin version */
				__( '%1$s by %2$s — from %3$s (plugin %4$s).', 'baltic-wp-stair-builder' ),
				wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $last['time'] ),
				$user ? $user->user_login : '#' . (int) $last['user_id'],
				! empty( $last['source_site'] ) ? $last['source_site'] : '—',
				! empty( $last['plugin_version'] ) ? $last['plugin_version'] : '—'
			) );
			echo '</p>';
		}

		$backup = get_option( self::BACKUP_OPTION );
		if ( is_array( $backup ) && ! empty( $backup['time'] ) ) {
			echo '<h3>' . esc_html__( 'Backup from before the last import', 'baltic-wp-stair-builder' ) . '</h3>';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" onsubmit="return confirm(' . esc_attr( wp_json_encode( __( 'Replace the current settings with the backup taken before the last import? The settings as they are now will be lost.', 'baltic-wp-stair-builder' ) ) ) . ');" style="display:inline-block;margin-right:1em;">';
			echo '<input type="hidden" name="action" value="baltic_stair_settings_restore" />';
			wp_nonce_field( 'baltic_stair_settings_restore' );
			submit_button(
				sprintf(
					/* translators: %s: backup date */
					__( 'Restore settings from before the last import (%s)', 'baltic-wp-stair-builder' ),
					wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $backup['time'] )
				),
				'secondary',
				'submit',
				false
			);
			echo '</form>';
			$dl = wp_nonce_url( admin_url( 'admin-post.php?action=baltic_stair_settings_backup_download' ), 'baltic_stair_settings_backup_download' );
			echo '<a href="' . esc_url( $dl ) . '">' . esc_html__( 'Download backup', 'baltic-wp-stair-builder' ) . '</a>';
		}
	}

	private function render_preview( $payload ) {
		$a = $this->analyse( $payload );

		echo '<h2>' . esc_html__( 'Import preview', 'baltic-wp-stair-builder' ) . '</h2>';

		// Source and totals.
		echo '<p>';
		echo esc_html( sprintf(
			/* translators: 1: source site, 2: export date, 3: plugin version */
			__( 'Source: %1$s — exported %2$s — plugin %3$s.', 'baltic-wp-stair-builder' ),
			isset( $payload['source_site'] ) ? (string) $payload['source_site'] : '—',
			isset( $payload['exported_at'] ) ? (string) $payload['exported_at'] : '—',
			isset( $payload['plugin_version'] ) ? (string) $payload['plugin_version'] : '—'
		) );
		echo '<br /><strong>';
		echo esc_html( sprintf(
			/* translators: %d: number of changed fields */
			_n( '%d field will change.', '%d fields will change.', $a['total_changes'], 'baltic-wp-stair-builder' ),
			$a['total_changes']
		) );
		echo '</strong></p>';

		// Warnings.
		$warnings = array();
		if ( $a['version_differs'] ) {
			$warnings[] = esc_html( sprintf(
				/* translators: 1: file plugin version, 2: this site's plugin version */
				__( 'The file was exported from plugin %1$s; this site runs %2$s (same settings schema, so the import is allowed).', 'baltic-wp-stair-builder' ),
				(string) $payload['plugin_version'],
				BALTIC_STAIRBUILDER_VERSION
			) );
		}
		if ( is_array( $a['logo'] ) ) {
			$warnings[] = $a['logo']['keep']
				? esc_html__( 'PDF logo: an attachment with the same ID and filename exists here — it will be kept.', 'baltic-wp-stair-builder' )
				: esc_html__( 'PDF logo: no matching attachment on this site — the field will be cleared. Re-select the PDF logo after import.', 'baltic-wp-stair-builder' );
		}
		if ( $a['wc_inactive_ids'] > 0 ) {
			$warnings[] = esc_html( sprintf(
				/* translators: %d: number of product IDs */
				__( 'WooCommerce is not active here, so %d product ID(s) in the file cannot be checked. They are imported as-is.', 'baltic-wp-stair-builder' ),
				$a['wc_inactive_ids']
			) );
		}
		foreach ( $a['missing_products'] as $ref ) {
			$warnings[] = esc_html( sprintf(
				/* translators: 1: field/row label, 2: product id */
				__( '%1$s: product ID %2$d does not exist on this site. The value is kept — fix it by hand after import.', 'baltic-wp-stair-builder' ),
				$ref['label'],
				$ref['id']
			) );
		}
		$r = $a['reference'];
		if ( $r['cur_prefix'] !== $r['new_prefix'] || $r['cur_start'] !== $r['new_start'] ) {
			$msg = sprintf(
				/* translators: 1-4: reference prefix/start values */
				__( 'Customer reference: prefix "%1$s" → "%2$s", start "%3$s" → "%4$s".', 'baltic-wp-stair-builder' ),
				$r['cur_prefix'], $r['new_prefix'], $r['cur_start'], $r['new_start']
			);
			if ( false !== $r['counter'] ) {
				$msg .= ' ' . sprintf(
					/* translators: %s: counter value */
					__( 'Reference counter already running at %s — the start value can only move the sequence forward.', 'baltic-wp-stair-builder' ),
					(string) $r['counter']
				);
			}
			$warnings[] = esc_html( $msg );
		}
		if ( $warnings ) {
			echo '<div class="notice notice-warning inline"><ul style="list-style:disc;margin:0.5em 0 0.5em 2em;">';
			foreach ( $warnings as $w ) {
				echo '<li>' . wp_kses_post( $w ) . '</li>';
			}
			echo '</ul></div>';
		}

		// Change list.
		if ( ! $a['tabs'] ) {
			echo '<p>' . esc_html__( 'The file matches the current settings — importing it would change nothing.', 'baltic-wp-stair-builder' ) . '</p>';
		}
		foreach ( $a['tabs'] as $tab ) {
			echo '<h3>' . esc_html( $tab['label'] ) . ' <span style="font-weight:normal;color:#646970;">(' . esc_html( count( $tab['changes'] ) ) . ')</span></h3>';
			echo '<table class="widefat striped" style="max-width:900px;"><thead><tr>';
			echo '<th>' . esc_html__( 'Setting', 'baltic-wp-stair-builder' ) . '</th>';
			echo '<th>' . esc_html__( 'Current', 'baltic-wp-stair-builder' ) . '</th>';
			echo '<th>' . esc_html__( 'After import', 'baltic-wp-stair-builder' ) . '</th>';
			echo '</tr></thead><tbody>';
			foreach ( $tab['changes'] as $c ) {
				echo '<tr><td>' . esc_html( $c['label'] ) . '</td><td>' . esc_html( $c['from'] ) . '</td><td>' . esc_html( $c['to'] ) . '</td></tr>';
			}
			echo '</tbody></table>';
		}

		// Confirm / cancel.
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin-top:1.5em;">';
		echo '<input type="hidden" name="action" value="baltic_stair_settings_import_apply" />';
		wp_nonce_field( 'baltic_stair_settings_import_apply' );
		if ( is_array( $a['emails'] ) ) {
			echo '<p><label><input type="checkbox" name="sb_keep_emails" value="1" checked="checked" /> ';
			echo esc_html( sprintf(
				/* translators: 1: current emails, 2: incoming emails */
				__( 'Keep this site\'s notification emails ("%1$s") instead of the file\'s ("%2$s")', 'baltic-wp-stair-builder' ),
				'' !== $a['emails']['current'] ? $a['emails']['current'] : __( 'site admin email', 'baltic-wp-stair-builder' ),
				'' !== $a['emails']['incoming'] ? $a['emails']['incoming'] : __( 'site admin email', 'baltic-wp-stair-builder' )
			) );
			echo '</label></p>';
		}
		submit_button( __( 'Import these settings', 'baltic-wp-stair-builder' ), 'primary', 'submit', false );
		echo '</form>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin-top:0.5em;">';
		echo '<input type="hidden" name="action" value="baltic_stair_settings_import_cancel" />';
		wp_nonce_field( 'baltic_stair_settings_import_cancel' );
		submit_button( __( 'Cancel', 'baltic-wp-stair-builder' ), 'secondary', 'submit', false );
		echo '</form>';
	}
}
