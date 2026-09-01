<?php
/**
 * Enquiries admin screen — the record of captured leads.
 *
 * Leads were written to wp_baltic_stair_leads and surfaced nowhere in admin;
 * the only evidence an enquiry existed was the notification email. If wp_mail()
 * failed, was throttled, or landed in spam, the lead sat in the database and
 * nobody knew. This is the screen to check against.
 *
 * Read-only throughout. See BRIEF-enquiries-list-2026-09-01.md §3 for what
 * deliberately is not here and why.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BD_Stair_Builder_Enquiries {

	const PAGE_SLUG = 'stairbuilder-enquiries';

	/** Screen option name for rows-per-page. */
	const PER_PAGE_OPTION = 'stairbuilder_enquiries_per_page';

	/** admin_post action for the CSV export. */
	const EXPORT_ACTION = 'bd_stair_export_enquiries';

	/** @var string Hook suffix for the Enquiries page (set in add_menu). */
	private $hook = '';

	public function __construct() {
		// Priority 20: the parent menu is registered by Stairbuilder_Pricing_Settings
		// on the default priority, and add_submenu_page() needs it to exist first.
		add_action( 'admin_menu', array( $this, 'add_menu' ), 20 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_filter( 'set-screen-option', array( $this, 'save_screen_option' ), 10, 3 );
		add_filter( 'set_screen_option_' . self::PER_PAGE_OPTION, array( $this, 'save_screen_option' ), 10, 3 );
		// Logged-in only. No admin_post_nopriv counterpart, deliberately.
		add_action( 'admin_post_' . self::EXPORT_ACTION, array( $this, 'handle_export' ) );
	}

	/* --------------------------------------------------------------------- */
	/* Registration                                                           */
	/* --------------------------------------------------------------------- */

	public function add_menu() {
		$this->hook = add_submenu_page(
			Stairbuilder_Pricing_Settings::PAGE_SLUG,
			__( 'Enquiries', 'stairbuilder' ),
			__( 'Enquiries', 'stairbuilder' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);

		if ( $this->hook ) {
			add_action( 'load-' . $this->hook, array( $this, 'add_screen_options' ) );
		}
	}

	public function add_screen_options() {
		// The detail view is a single record — a per-page control there is noise.
		if ( $this->current_lead_id() ) {
			return;
		}
		add_screen_option(
			'per_page',
			array(
				'label'   => __( 'Enquiries per page', 'stairbuilder' ),
				'default' => 20,
				'option'  => self::PER_PAGE_OPTION,
			)
		);
	}

	public function save_screen_option( $status, $option, $value ) {
		return ( self::PER_PAGE_OPTION === $option ) ? max( 1, (int) $value ) : $status;
	}

	/**
	 * Styles only, and only on this screen — matching how the pricing screen
	 * gates on its own captured hook suffix.
	 */
	public function enqueue( $hook ) {
		if ( ! $this->hook || $hook !== $this->hook ) {
			return;
		}
		wp_register_style( 'bd-stair-enquiries', false, array(), BALTIC_STAIRBUILDER_VERSION );
		wp_enqueue_style( 'bd-stair-enquiries' );
		wp_add_inline_style(
			'bd-stair-enquiries',
			'.bd-enq-muted { color: #8c8f94; }
			.bd-enq-sep { color: #c3c4c7; }
			.bd-enq-poa { font-weight: 600; letter-spacing: .04em; }
			.bd-enq-filters input[type="date"] { height: 30px; margin-right: 4px; vertical-align: top; }
			.bd-enq-detail { max-width: 900px; }
			.bd-enq-card { background: #fff; border: 1px solid #c3c4c7; padding: 4px 20px 16px; margin-bottom: 20px; }
			.bd-enq-card h2 { font-size: 14px; margin: 16px 0 8px; }
			.bd-enq-card table { width: 100%; border-collapse: collapse; }
			.bd-enq-card th { text-align: left; width: 240px; padding: 6px 12px 6px 0; vertical-align: top; font-weight: 600; color: #50575e; }
			.bd-enq-card td { padding: 6px 0; vertical-align: top; }
			.bd-enq-card tr + tr th, .bd-enq-card tr + tr td { border-top: 1px solid #f0f0f1; }
			.bd-enq-total td { font-size: 15px; font-weight: 700; }
			.bd-enq-poa-note { background: #fcf9e8; border-left: 4px solid #dba617; padding: 10px 12px; margin: 12px 0; }
			.bd-enq-raw th { font-family: Consolas, Monaco, monospace; font-weight: 400; color: #646970; }'
		);
	}

	/* --------------------------------------------------------------------- */
	/* Shared helpers                                                         */
	/* --------------------------------------------------------------------- */

	/**
	 * Price-on-application, read from the lead as captured.
	 *
	 * A POA quote carries no figure on the list, only in the detail view and
	 * labelled as internal. The list is the surface most likely to be exported,
	 * screenshotted, or turned round on a desk towards a customer, and a number
	 * there is a number the customer was deliberately never shown. Same rule
	 * the PDF, the customer email and the quote-view page already follow.
	 */
	public static function is_poa( $lead ) {
		$fd = isset( $lead['form_data'] ) && is_array( $lead['form_data'] ) ? $lead['form_data'] : array();
		return ! empty( $fd['_poa'] );
	}

	private function current_lead_id() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view selector.
		return isset( $_GET['lead'] ) ? absint( wp_unslash( $_GET['lead'] ) ) : 0;
	}

	/* --------------------------------------------------------------------- */
	/* Render                                                                 */
	/* --------------------------------------------------------------------- */

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$lead_id = $this->current_lead_id();
		if ( $lead_id ) {
			$this->render_detail( $lead_id );
			return;
		}
		$this->render_list();
	}

	private function render_list() {
		require_once plugin_dir_path( __FILE__ ) . 'class-stairbuilder-enquiries-list-table.php';

		$table = new BD_Stair_Builder_Enquiries_List_Table();
		$table->prepare_items();
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Enquiries', 'stairbuilder' ); ?></h1>
			<a href="<?php echo esc_url( $this->export_url() ); ?>" class="page-title-action"><?php esc_html_e( 'Export CSV', 'stairbuilder' ); ?></a>
			<hr class="wp-header-end" />

			<form method="get">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>" />
				<?php
				$table->search_box( __( 'Search enquiries', 'stairbuilder' ), 'bd-enq-search' );
				$table->display();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * The spec rows, in the order the PDF presents them. Each entry is
	 * [ label, form_data key, option repeater, code sub-key, name sub-key ] —
	 * a null repeater means the value is shown as stored.
	 */
	private function spec_map() {
		return array(
			array( 'Building Regulations', 'building_regs',     'building_regs',      'building_reg_value',  'building_reg_name' ),
			array( 'Construction Type',    'construction_type', 'construction_types', 'construction_code',   'construction_name' ),
			array( 'Tread Profile',        'tread-profile',     'tread_profiles',     'tread_profile_code',  'tread_profile_name' ),
			array( 'String Material',      'stringer_material', 'stringer_types',     'stringer_code',       'stringer_name' ),
			array( 'Tread Material',       'tread_material',    'tread_types',        'tread_code',          'tread_name' ),
			array( 'Riser Material',       'riser_material',    'riser_types',        'riser_code',          'riser_name' ),
			array( 'Newel Type',           'newel_type',        'newel_types',        'code',                'name' ),
			array( 'Newel Caps',           'newel_cap',         'cap_types',          'code',                'name' ),
			array( 'Handrail Type',        'handrail_type',     'handrail_types',     'code',                'name' ),
			array( 'Spindles',             'spindle_type',      'spindle_types',      'code',                'name' ),
		);
	}

	private function render_detail( $lead_id ) {
		$lead = BD_Stair_Builder_Leads::get( $lead_id );
		if ( ! $lead ) {
			?>
			<div class="wrap">
				<h1><?php esc_html_e( 'Enquiry', 'stairbuilder' ); ?></h1>
				<div class="notice notice-error"><p><?php esc_html_e( 'That enquiry no longer exists.', 'stairbuilder' ); ?></p></div>
				<p><a href="<?php echo esc_url( $this->list_url() ); ?>">&larr; <?php esc_html_e( 'Back to Enquiries', 'stairbuilder' ); ?></a></p>
			</div>
			<?php
			return;
		}

		$fd  = is_array( $lead['form_data'] ) ? $lead['form_data'] : array();
		$poa = self::is_poa( $lead );
		$ts  = strtotime( $lead['created_at'] );
		?>
		<div class="wrap bd-enq-detail">
			<h1 class="wp-heading-inline">
				<?php echo esc_html( '' !== trim( (string) $lead['name'] ) ? $lead['name'] : __( '(no name)', 'stairbuilder' ) ); ?>
			</h1>
			<a href="<?php echo esc_url( $this->list_url() ); ?>" class="page-title-action"><?php esc_html_e( 'Back to Enquiries', 'stairbuilder' ); ?></a>
			<hr class="wp-header-end" />

			<div class="bd-enq-card">
				<h2><?php esc_html_e( 'Contact', 'stairbuilder' ); ?></h2>
				<table>
					<tr><th><?php esc_html_e( 'Name', 'stairbuilder' ); ?></th><td><?php echo esc_html( $lead['name'] ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Email', 'stairbuilder' ); ?></th><td>
						<?php if ( '' !== trim( (string) $lead['email'] ) ) : ?>
							<a href="mailto:<?php echo esc_attr( $lead['email'] ); ?>"><?php echo esc_html( $lead['email'] ); ?></a>
						<?php else : ?>—<?php endif; ?>
					</td></tr>
					<tr><th><?php esc_html_e( 'Phone', 'stairbuilder' ); ?></th><td>
						<?php if ( '' !== trim( (string) $lead['phone'] ) ) : ?>
							<a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $lead['phone'] ) ); ?>"><?php echo esc_html( $lead['phone'] ); ?></a>
						<?php else : ?>—<?php endif; ?>
					</td></tr>
					<tr><th><?php esc_html_e( 'Postcode', 'stairbuilder' ); ?></th><td><?php echo esc_html( $lead['postcode'] ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Received', 'stairbuilder' ); ?></th><td><?php echo esc_html( $ts ? date_i18n( 'j F Y, H:i', $ts ) : $lead['created_at'] ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Reference', 'stairbuilder' ); ?></th><td><?php echo (int) $lead['id']; ?></td></tr>
				</table>
			</div>

			<div class="bd-enq-card">
				<h2><?php esc_html_e( 'Pricing', 'stairbuilder' ); ?></h2>
				<?php if ( $poa ) : ?>
					<p class="bd-enq-poa-note">
						<strong><?php esc_html_e( 'Price on application.', 'stairbuilder' ); ?></strong>
						<?php esc_html_e( 'The customer was shown no figures — not on the quote page, not in the PDF, not in their email. The figures below are the configurator\'s internal calculation, for whoever prices this by hand.', 'stairbuilder' ); ?>
					</p>
				<?php endif; ?>
				<table>
					<tr><th><?php esc_html_e( 'Subtotal', 'stairbuilder' ); ?></th><td><?php echo esc_html( '£' . number_format( (float) $lead['price'], 2 ) ); ?></td></tr>
					<tr><th><?php esc_html_e( 'VAT', 'stairbuilder' ); ?></th><td><?php echo esc_html( '£' . number_format( (float) $lead['vat'], 2 ) ); ?></td></tr>
					<tr class="bd-enq-total"><th><?php esc_html_e( 'Total', 'stairbuilder' ); ?></th><td><?php echo esc_html( '£' . number_format( (float) $lead['total'], 2 ) ); ?></td></tr>
				</table>
			</div>

			<div class="bd-enq-card">
				<h2><?php esc_html_e( 'Configuration', 'stairbuilder' ); ?></h2>
				<table>
					<?php
					$consumed = array( '_poa' );

					$type_label = bd_staircase_type_label( $fd );
					if ( '' !== $type_label ) {
						$this->row( __( 'Staircase Type', 'stairbuilder' ), $type_label );
					}
					$consumed[] = 'stair_type';
					$consumed[] = 'stair_config';

					foreach ( $this->spec_map() as $spec ) {
						list( $label, $key, $repeater, $code_key, $name_key ) = $spec;
						$consumed[] = $key;
						if ( ! isset( $fd[ $key ] ) || '' === (string) $fd[ $key ] ) {
							continue;
						}
						$this->row( $label, bd_code_label( $repeater, $fd[ $key ], $code_key, $name_key ) );
					}

					// Featured step: the same decomposition the PDF applies, so a
					// pre-v2.23.0 lead reads correctly here too.
					if ( isset( $fd['left-featured-step'] ) || isset( $fd['right-featured-step'] ) ) {
						$pair = array( (string) ( $fd['left-featured-step'] ?? '0' ), (string) ( $fd['right-featured-step'] ?? '0' ) );
					} else {
						$pair = bd_featured_step_from_legacy( $fd['featured_step'] ?? '' );
					}
					$consumed[] = 'left-featured-step';
					$consumed[] = 'right-featured-step';
					$consumed[] = 'featured_step';
					$this->row( __( 'Featured Step', 'stairbuilder' ), bd_featured_step_label( $pair[0], $pair[1] ) );
					?>
				</table>

				<?php
				// Everything else, raw. A field with no resolver is still
				// information the workshop may need — show it rather than hide it.
				$rest = array_diff_key( $fd, array_flip( $consumed ) );
				if ( $rest ) :
					?>
					<h2><?php esc_html_e( 'Other submitted fields', 'stairbuilder' ); ?></h2>
					<table class="bd-enq-raw">
						<?php foreach ( $rest as $key => $value ) : ?>
							<tr>
								<th><?php echo esc_html( $key ); ?></th>
								<td><?php echo esc_html( $this->scalarise( $value ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</table>
				<?php endif; ?>
			</div>

			<div class="bd-enq-card">
				<h2><?php esc_html_e( 'Quote', 'stairbuilder' ); ?></h2>
				<table>
					<tr><th><?php esc_html_e( 'PDF', 'stairbuilder' ); ?></th><td>
						<?php if ( ! empty( $lead['pdf_path'] ) && file_exists( $lead['pdf_path'] ) ) : ?>
							<a href="<?php echo esc_url( add_query_arg( array( 'action' => 'baltic_stair_download', 'token' => $lead['token'] ), admin_url( 'admin-post.php' ) ) ); ?>"><?php esc_html_e( 'Download PDF', 'stairbuilder' ); ?></a>
						<?php else : ?>
							<span class="bd-enq-muted"><?php esc_html_e( 'No PDF was generated for this enquiry.', 'stairbuilder' ); ?></span>
						<?php endif; ?>
					</td></tr>
					<?php if ( function_exists( 'baltic_stair_get_quote_view_url' ) ) : ?>
						<tr><th><?php esc_html_e( 'Customer quote page', 'stairbuilder' ); ?></th><td>
							<a href="<?php echo esc_url( baltic_stair_get_quote_view_url( $lead['token'] ) ); ?>"><?php esc_html_e( 'Open the page the customer sees', 'stairbuilder' ); ?></a>
						</td></tr>
					<?php endif; ?>
				</table>
			</div>
		</div>
		<?php
	}

	private function row( $label, $value ) {
		if ( '' === (string) $value ) {
			return;
		}
		printf(
			'<tr><th>%s</th><td>%s</td></tr>',
			esc_html( $label ),
			esc_html( $value )
		);
	}

	/**
	 * form_data is whatever parse_str() made of the submitted query string, so
	 * a value can be an array (a checkbox group). Flatten for display rather
	 * than printing "Array".
	 */
	private function scalarise( $value ) {
		if ( is_array( $value ) ) {
			return implode( ', ', array_map( array( $this, 'scalarise' ), $value ) );
		}
		if ( is_bool( $value ) ) {
			return $value ? 'true' : 'false';
		}
		return (string) $value;
	}

	private function list_url() {
		return add_query_arg( 'page', self::PAGE_SLUG, admin_url( 'admin.php' ) );
	}

	/* --------------------------------------------------------------------- */
	/* CSV export                                                             */
	/* --------------------------------------------------------------------- */

	/**
	 * Export link carrying the filters currently in force, so "Export CSV"
	 * means "export what I am looking at" rather than "export everything".
	 */
	private function export_url() {
		require_once plugin_dir_path( __FILE__ ) . 'class-stairbuilder-enquiries-list-table.php';
		$args = BD_Stair_Builder_Enquiries_List_Table::request_args();

		$query = array( 'action' => self::EXPORT_ACTION );
		foreach ( array( 'search' => 's', 'date_from' => 'date_from', 'date_to' => 'date_to', 'orderby' => 'orderby', 'order' => 'order' ) as $key => $param ) {
			if ( '' !== (string) $args[ $key ] ) {
				$query[ $param ] = $args[ $key ];
			}
		}

		return wp_nonce_url( add_query_arg( $query, admin_url( 'admin-post.php' ) ), self::EXPORT_ACTION );
	}

	/**
	 * A cell beginning = + - or @ is a formula to Excel, Numbers and Sheets.
	 * A customer controls their own name field, so prefix those with a single
	 * quote: an export opened on the sales desk must not execute anything that
	 * arrived through the public form.
	 */
	private function csv_cell( $value ) {
		$value = (string) $value;
		if ( '' !== $value && strpos( "=+-@", $value[0] ) !== false ) {
			return "'" . $value;
		}
		return $value;
	}

	public function handle_export() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to export enquiries.', 'stairbuilder' ), 403 );
		}
		check_admin_referer( self::EXPORT_ACTION );

		require_once plugin_dir_path( __FILE__ ) . 'class-stairbuilder-enquiries-list-table.php';
		$args = BD_Stair_Builder_Enquiries_List_Table::request_args();

		// per_page 0 = the whole filtered set. Exporting one screen's worth
		// under a button labelled "Export CSV" would be a silent truncation.
		$args['per_page'] = 0;
		$args['offset']   = 0;
		$rows = BD_Stair_Builder_Leads::query( $args );

		$filename = 'enquiries-' . gmdate( 'Y-m-d-His' ) . '.csv';

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

		$out = fopen( 'php://output', 'w' );

		fputcsv(
			$out,
			array( 'Lead ref', 'Date', 'Name', 'Email', 'Phone', 'Postcode', 'Total', 'Type', 'Quote URL' )
		);

		foreach ( $rows as $row ) {
			$ts = strtotime( $row['created_at'] );

			$total = self::is_poa( $row )
				? 'POA' // Consistent with the list: a POA quote carries no figure here either.
				: number_format( (float) $row['total'], 2, '.', '' );

			$quote_url = ( function_exists( 'baltic_stair_get_quote_view_url' ) && ! empty( $row['token'] ) )
				? baltic_stair_get_quote_view_url( $row['token'] )
				: '';

			fputcsv(
				$out,
				array_map(
					array( $this, 'csv_cell' ),
					array(
						(int) $row['id'],
						$ts ? date_i18n( 'Y-m-d H:i', $ts ) : $row['created_at'],
						$row['name'],
						$row['email'],
						$row['phone'],
						$row['postcode'],
						$total,
						bd_staircase_type_label( isset( $row['form_data'] ) ? $row['form_data'] : array() ),
						$quote_url,
					)
				)
			);
		}

		fclose( $out );
		exit;
	}
}
