<?php
/**
 * Stairbuilder shortcode handler.
 *
 * Usage:
 *   [stairbuilder_form stair_type="straight"]
 *   [stairbuilder_form stair_type="quarter"]
 *   [stairbuilder_form stair_type="half"]
 *
 * `stair_type` defaults to "straight" if omitted or invalid.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Stairbuilder_Plugin {

    const ALLOWED_TYPES = array( 'straight', 'quarter', 'half' );
    const DEFAULT_TYPE  = 'straight';

    // Resolved staircase type for the current shortcode render. Populated by
    // generate_shortcode() and consumed by the form template.
    public static $current_stair_type = '';

    public function __construct() {
        add_shortcode( 'stairbuilder_form', array( $this, 'generate_shortcode' ) );
    }

    /**
     * Resolve the staircase type from shortcode attributes, with a temporary
     * backwards-compat fallback to the legacy ACF field.
     */
    public static function resolve_stair_type( $atts, $page_id = 0 ) {
        $atts = shortcode_atts(
            array( 'stair_type' => '' ),
            is_array( $atts ) ? $atts : array(),
            'stairbuilder_form'
        );

        $type = sanitize_key( $atts['stair_type'] );

        // BC: if no shortcode attribute provided, try the legacy ACF field.
        // Remove this fallback once all client sites have updated their shortcodes.
        if ( ! $type && function_exists( 'get_field' ) && $page_id ) {
            $legacy = get_field( 'staircase_type', $page_id );
            if ( is_string( $legacy ) ) {
                $type = sanitize_key( $legacy );
            }
        }

        if ( ! in_array( $type, self::ALLOWED_TYPES, true ) ) {
            $type = self::DEFAULT_TYPE;
        }

        return $type;
    }

    public function generate_shortcode( $atts = array(), $content = '', $tag = '' ) {
        global $post;
        $page_id = isset( $post->ID ) ? (int) $post->ID : 0;

        self::$current_stair_type = self::resolve_stair_type( $atts, $page_id );

        ob_start();
        include plugin_dir_path( __FILE__ ) . '../front/form-template.php';
        return ob_get_clean();
    }
}

new Stairbuilder_Plugin();
