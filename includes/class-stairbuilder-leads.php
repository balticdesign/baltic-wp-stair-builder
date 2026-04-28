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
}
