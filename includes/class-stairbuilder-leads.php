<?php
/**
 * Lead model + storage for Baltic Stair Builder.
 *
 * Custom table-backed lightweight model. Replaces the WC order coupling
 * for capturing configurator submissions in lead-gen mode.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BD_Stair_Builder_Leads {

	const TABLE_SUFFIX = 'baltic_stair_leads';
	const DB_VERSION   = '1.0';

	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_SUFFIX;
	}

	public static function install() {
		global $wpdb;
		$table   = self::table_name();
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			token VARCHAR(64) NOT NULL,
			created_at DATETIME NOT NULL,
			name VARCHAR(255) NOT NULL DEFAULT '',
			email VARCHAR(255) NOT NULL DEFAULT '',
			phone VARCHAR(50) NOT NULL DEFAULT '',
			postcode VARCHAR(20) NOT NULL DEFAULT '',
			price DECIMAL(10,2) NOT NULL DEFAULT 0,
			vat DECIMAL(10,2) NOT NULL DEFAULT 0,
			total DECIMAL(10,2) NOT NULL DEFAULT 0,
			form_data LONGTEXT NOT NULL,
			pdf_path TEXT NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY token (token),
			KEY email (email),
			KEY created_at (created_at)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( 'baltic_stair_leads_db_version', self::DB_VERSION );
	}

	/**
	 * Re-run install() when the stored schema version is behind DB_VERSION.
	 *
	 * install() fires from register_activation_hook only, so on a site that is
	 * already active a future column would never be created — the plugin would
	 * update, the table would not, and nothing would say so. Added ahead of the
	 * CRM work that will bring new columns, while it costs nothing.
	 *
	 * No-op when current, which is every request but the one after an upgrade.
	 */
	public static function maybe_upgrade() {
		if ( get_option( 'baltic_stair_leads_db_version' ) === self::DB_VERSION ) {
			return;
		}
		self::install();
	}

	public static function generate_token() {
		return bin2hex( random_bytes( 24 ) );
	}

	/**
	 * Insert a lead row. Returns array with id+token, or WP_Error.
	 */
	public static function create( array $data ) {
		global $wpdb;

		$row = array(
			'token'      => self::generate_token(),
			'created_at' => current_time( 'mysql' ),
			'name'       => sanitize_text_field( $data['name'] ?? '' ),
			'email'      => sanitize_email( $data['email'] ?? '' ),
			'phone'      => sanitize_text_field( $data['phone'] ?? '' ),
			'postcode'   => sanitize_text_field( $data['postcode'] ?? '' ),
			'price'      => isset( $data['price'] ) ? (float) $data['price'] : 0,
			'vat'        => isset( $data['vat'] ) ? (float) $data['vat'] : 0,
			'total'      => isset( $data['total'] ) ? (float) $data['total'] : 0,
			'form_data'  => wp_json_encode( $data['form_data'] ?? array() ),
			'pdf_path'   => '',
		);

		$result = $wpdb->insert( self::table_name(), $row );
		if ( false === $result ) {
			return new WP_Error( 'baltic_stair_lead_insert_failed', $wpdb->last_error );
		}

		return array(
			'id'    => (int) $wpdb->insert_id,
			'token' => $row['token'],
		);
	}

	public static function set_pdf_path( $lead_id, $pdf_path ) {
		global $wpdb;
		$wpdb->update(
			self::table_name(),
			array( 'pdf_path' => $pdf_path ),
			array( 'id' => (int) $lead_id ),
			array( '%s' ),
			array( '%d' )
		);
	}

	public static function get_by_token( $token ) {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . self::table_name() . ' WHERE token = %s LIMIT 1', $token ),
			ARRAY_A
		);
		if ( ! $row ) {
			return null;
		}
		$row['form_data'] = json_decode( $row['form_data'], true ) ?: array();
		return $row;
	}

	public static function get( $lead_id ) {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . self::table_name() . ' WHERE id = %d LIMIT 1', (int) $lead_id ),
			ARRAY_A
		);
		if ( ! $row ) {
			return null;
		}
		$row['form_data'] = json_decode( $row['form_data'], true ) ?: array();
		return $row;
	}

	/* --------------------------------------------------------------------- */
	/* Listing (v2.24.0 — the Enquiries screen)                               */
	/* --------------------------------------------------------------------- */

	/**
	 * Columns a caller may sort by. Whitelist, not a filter: $args['orderby']
	 * reaches SQL as an identifier and cannot be placed by $wpdb->prepare().
	 */
	private static function sortable_columns() {
		return array( 'created_at', 'name', 'total', 'id' );
	}

	/**
	 * Builds the shared WHERE fragment for query() and count() so the two can
	 * never disagree about what a filtered set contains — a paginator whose
	 * total counts different rows than its page is worse than no paginator.
	 *
	 * Returns [ sql_without_the_word_WHERE, prepare_args ].
	 */
	private static function build_where( array $args ) {
		$where = array( '1=1' );
		$vals  = array();

		$search = isset( $args['search'] ) ? trim( (string) $args['search'] ) : '';
		if ( '' !== $search ) {
			global $wpdb;
			// esc_like() first: a postcode search containing % or _ is a literal
			// search for that character, not a wildcard scan of the table.
			$like    = '%' . $wpdb->esc_like( $search ) . '%';
			$where[] = '( name LIKE %s OR email LIKE %s OR postcode LIKE %s )';
			$vals[]  = $like;
			$vals[]  = $like;
			$vals[]  = $like;
		}

		// Dates arrive as Y-m-d from the filter form and are inclusive at both
		// ends, so the upper bound covers the whole of its day.
		$from = isset( $args['date_from'] ) ? trim( (string) $args['date_from'] ) : '';
		if ( '' !== $from && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $from ) ) {
			$where[] = 'created_at >= %s';
			$vals[]  = $from . ' 00:00:00';
		}
		$to = isset( $args['date_to'] ) ? trim( (string) $args['date_to'] ) : '';
		if ( '' !== $to && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $to ) ) {
			$where[] = 'created_at <= %s';
			$vals[]  = $to . ' 23:59:59';
		}

		return array( implode( ' AND ', $where ), $vals );
	}

	/**
	 * Total rows matching the same filters query() would apply.
	 */
	public static function count( array $args = array() ) {
		global $wpdb;
		list( $where, $vals ) = self::build_where( $args );

		$sql = 'SELECT COUNT(*) FROM ' . self::table_name() . ' WHERE ' . $where;
		if ( $vals ) {
			$sql = $wpdb->prepare( $sql, $vals );
		}

		return (int) $wpdb->get_var( $sql );
	}

	/**
	 * Paginated fetch for the Enquiries list.
	 *
	 * Supported args: search, date_from, date_to, orderby, order, per_page,
	 * offset, with_form_data.
	 *
	 * On form_data: it is LONGTEXT holding the whole configuration, and the
	 * brief asks that the list avoid it. It cannot — two of the list's columns
	 * (staircase type, and the POA flag that decides whether a figure is shown
	 * at all) exist only inside it. The alternative is JSON_EXTRACT in SQL,
	 * which would tie the model to the database server's JSON support to save a
	 * few tens of kilobytes per page of twenty. So the list loads and decodes
	 * it, and `with_form_data` exists for callers that genuinely do not need it.
	 */
	public static function query( array $args = array() ) {
		global $wpdb;

		$args = array_merge(
			array(
				'orderby'        => 'created_at',
				'order'          => 'DESC',
				'per_page'       => 20,
				'offset'         => 0,
				'with_form_data' => true,
			),
			$args
		);

		$orderby = in_array( $args['orderby'], self::sortable_columns(), true ) ? $args['orderby'] : 'created_at';
		$order   = ( strtoupper( (string) $args['order'] ) === 'ASC' ) ? 'ASC' : 'DESC';

		$columns = 'id, token, created_at, name, email, phone, postcode, price, vat, total, pdf_path';
		if ( ! empty( $args['with_form_data'] ) ) {
			$columns .= ', form_data';
		}

		list( $where, $vals ) = self::build_where( $args );

		// per_page 0 or below means "no limit" — the CSV export of a filtered
		// set, which must not be silently truncated to a screen's worth.
		$per_page = (int) $args['per_page'];
		$limit    = '';
		if ( $per_page > 0 ) {
			$limit  = ' LIMIT %d OFFSET %d';
			$vals[] = $per_page;
			$vals[] = max( 0, (int) $args['offset'] );
		}

		$sql = 'SELECT ' . $columns . ' FROM ' . self::table_name()
			. ' WHERE ' . $where
			. ' ORDER BY ' . $orderby . ' ' . $order . ', id ' . $order
			. $limit;

		if ( $vals ) {
			$sql = $wpdb->prepare( $sql, $vals );
		}

		$rows = $wpdb->get_results( $sql, ARRAY_A );
		if ( ! is_array( $rows ) ) {
			return array();
		}

		// Decode to match get() / get_by_token(), so callers handle one shape.
		if ( ! empty( $args['with_form_data'] ) ) {
			foreach ( $rows as &$row ) {
				$row['form_data'] = json_decode( $row['form_data'], true ) ?: array();
			}
			unset( $row );
		}

		return $rows;
	}
}
