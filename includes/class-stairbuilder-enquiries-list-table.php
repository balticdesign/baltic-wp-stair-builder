<?php
/**
 * WP_List_Table for the Enquiries screen.
 *
 * Read-only by design. This screen is a record, not a pipeline: no status, no
 * assignment, no notes, no editable field. Deal state lives in the CRM — two
 * systems holding it would disagree, and staff would update whichever one was
 * open. See BRIEF-enquiries-list-2026-09-01.md §3 before adding a column.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

// Cells reach for the screen class (page slug, the POA rule). In practice the
// screen loads first and includes this file, but the dependency is real, so
// declare it rather than rely on load order.
if ( ! class_exists( 'BD_Stair_Builder_Enquiries' ) ) {
	require_once plugin_dir_path( __FILE__ ) . 'class-stairbuilder-enquiries.php';
}

class BD_Stair_Builder_Enquiries_List_Table extends WP_List_Table {

	/** @var int Rows matching the current filters, before pagination. */
	private $total_items = 0;

	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'enquiry',
				'plural'   => 'enquiries',
				'ajax'     => false,
			)
		);
	}

	/* --------------------------------------------------------------------- */
	/* Columns                                                                */
	/* --------------------------------------------------------------------- */

	public function get_columns() {
		return array(
			'created_at' => __( 'Date', 'stairbuilder' ),
			'name'       => __( 'Name', 'stairbuilder' ),
			'email'      => __( 'Email', 'stairbuilder' ),
			'phone'      => __( 'Phone', 'stairbuilder' ),
			'postcode'   => __( 'Postcode', 'stairbuilder' ),
			'total'      => __( 'Total', 'stairbuilder' ),
			'type'       => __( 'Type', 'stairbuilder' ),
			'quote'      => __( 'Quote', 'stairbuilder' ),
		);
	}

	/**
	 * Only the three columns the model can actually sort on. Type and Quote
	 * come out of form_data and have no index behind them.
	 */
	public function get_sortable_columns() {
		return array(
			'created_at' => array( 'created_at', true ),
			'name'       => array( 'name', false ),
			'total'      => array( 'total', false ),
		);
	}

	/* --------------------------------------------------------------------- */
	/* Data                                                                   */
	/* --------------------------------------------------------------------- */

	/**
	 * Reads the filter state out of the request. Shared with the CSV export so
	 * "export what I am looking at" means exactly that.
	 */
	public static function request_args() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filters on a capability-gated screen.
		return array(
			'search'    => isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '',
			'date_from' => isset( $_REQUEST['date_from'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['date_from'] ) ) : '',
			'date_to'   => isset( $_REQUEST['date_to'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['date_to'] ) ) : '',
			'orderby'   => isset( $_REQUEST['orderby'] ) ? sanitize_key( wp_unslash( $_REQUEST['orderby'] ) ) : 'created_at',
			'order'     => isset( $_REQUEST['order'] ) ? sanitize_key( wp_unslash( $_REQUEST['order'] ) ) : 'desc',
		);
		// phpcs:enable
	}

	public function prepare_items() {
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );

		$per_page = $this->get_items_per_page( 'stairbuilder_enquiries_per_page', 20 );
		$paged    = $this->get_pagenum();

		$args = self::request_args();
		$args['per_page'] = $per_page;
		$args['offset']   = ( $paged - 1 ) * $per_page;

		$this->total_items = BD_Stair_Builder_Leads::count( $args );
		$this->items       = BD_Stair_Builder_Leads::query( $args );

		$this->set_pagination_args(
			array(
				'total_items' => $this->total_items,
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $this->total_items / max( 1, $per_page ) ),
			)
		);
	}

	public function no_items() {
		esc_html_e( 'No enquiries yet.', 'stairbuilder' );
	}

	/* --------------------------------------------------------------------- */
	/* Cells                                                                  */
	/* --------------------------------------------------------------------- */

	public function column_default( $item, $column_name ) {
		return isset( $item[ $column_name ] ) ? esc_html( $item[ $column_name ] ) : '';
	}

	public function column_created_at( $item ) {
		// created_at is written with current_time('mysql'), i.e. already site
		// local — format it, do not shift it again.
		$ts = strtotime( $item['created_at'] );
		return esc_html( $ts ? date_i18n( 'j M Y, H:i', $ts ) : $item['created_at'] );
	}

	public function column_name( $item ) {
		$url = add_query_arg(
			array(
				'page' => BD_Stair_Builder_Enquiries::PAGE_SLUG,
				'lead' => (int) $item['id'],
			),
			admin_url( 'admin.php' )
		);
		$name = ( '' !== trim( (string) $item['name'] ) ) ? $item['name'] : __( '(no name)', 'stairbuilder' );

		return sprintf(
			'<strong><a href="%s">%s</a></strong><div class="row-actions"><span>%s %d</span></div>',
			esc_url( $url ),
			esc_html( $name ),
			esc_html__( 'Ref', 'stairbuilder' ),
			(int) $item['id']
		);
	}

	public function column_email( $item ) {
		if ( '' === trim( (string) $item['email'] ) ) {
			return '—';
		}
		return sprintf( '<a href="mailto:%s">%s</a>', esc_attr( $item['email'] ), esc_html( $item['email'] ) );
	}

	public function column_phone( $item ) {
		if ( '' === trim( (string) $item['phone'] ) ) {
			return '—';
		}
		// tel: wants the digits, the cell shows what the customer typed.
		$tel = preg_replace( '/[^0-9+]/', '', $item['phone'] );
		return sprintf( '<a href="tel:%s">%s</a>', esc_attr( $tel ), esc_html( $item['phone'] ) );
	}

	public function column_total( $item ) {
		if ( BD_Stair_Builder_Enquiries::is_poa( $item ) ) {
			// No figure on the list, ever — see BD_Stair_Builder_Enquiries::is_poa().
			return '<span class="bd-enq-poa">' . esc_html__( 'POA', 'stairbuilder' ) . '</span>';
		}
		return esc_html( '£' . number_format( (float) $item['total'], 2 ) );
	}

	public function column_type( $item ) {
		$label = bd_staircase_type_label( isset( $item['form_data'] ) ? $item['form_data'] : array() );
		return '' !== $label ? esc_html( $label ) : '—';
	}

	public function column_quote( $item ) {
		$links = array();

		// Always through the token endpoint, never a direct uploads URL.
		if ( ! empty( $item['pdf_path'] ) && file_exists( $item['pdf_path'] ) ) {
			$pdf = add_query_arg(
				array(
					'action' => 'baltic_stair_download',
					'token'  => $item['token'],
				),
				admin_url( 'admin-post.php' )
			);
			$links[] = sprintf( '<a href="%s">%s</a>', esc_url( $pdf ), esc_html__( 'PDF', 'stairbuilder' ) );
		} else {
			// PDF generation can fail after the lead row is written. That state
			// is information, so say it rather than offering a broken link.
			$links[] = '<span class="bd-enq-muted">' . esc_html__( 'no PDF', 'stairbuilder' ) . '</span>';
		}

		if ( function_exists( 'baltic_stair_get_quote_view_url' ) && ! empty( $item['token'] ) ) {
			$links[] = sprintf(
				'<a href="%s">%s</a>',
				esc_url( baltic_stair_get_quote_view_url( $item['token'] ) ),
				esc_html__( 'View', 'stairbuilder' )
			);
		}

		return implode( ' <span class="bd-enq-sep">|</span> ', $links );
	}

	/* --------------------------------------------------------------------- */
	/* Filters above the table                                                */
	/* --------------------------------------------------------------------- */

	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}
		$args = self::request_args();
		?>
		<div class="alignleft actions bd-enq-filters">
			<label for="bd-enq-from" class="screen-reader-text"><?php esc_html_e( 'From date', 'stairbuilder' ); ?></label>
			<input type="date" id="bd-enq-from" name="date_from" value="<?php echo esc_attr( $args['date_from'] ); ?>" />
			<label for="bd-enq-to" class="screen-reader-text"><?php esc_html_e( 'To date', 'stairbuilder' ); ?></label>
			<input type="date" id="bd-enq-to" name="date_to" value="<?php echo esc_attr( $args['date_to'] ); ?>" />
			<?php submit_button( __( 'Filter', 'stairbuilder' ), '', 'filter_action', false ); ?>
		</div>
		<?php
	}
}
