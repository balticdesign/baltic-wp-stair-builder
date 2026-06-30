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

    const ALLOWED_TYPES   = array( 'straight', 'quarter', 'half' );
    const DEFAULT_TYPE    = 'straight';
    const ALLOWED_CONFIGS = array( 'landing', 'winder', 'double_quarter' );

    // Resolved staircase type for the current shortcode render. Populated by
    // generate_shortcode() and consumed by the form template.
    public static $current_stair_type = '';

    /** stair_config value for the current render. Empty string = no config set. */
    public static $current_stair_config = '';

    /** Pre-select value for #treadit. Empty string = not locked. Real option
     *  values: 1 = Quarter Landing, 2 = 2 Winders, 3 = 3 Winders, 4 = Half Landing. */
    public static $current_treadit = '';

    /** Pre-select value for #treadit2. Empty string = not locked. */
    public static $current_treadit2 = '';

    public function __construct() {
        add_shortcode( 'stairbuilder_form', array( $this, 'generate_shortcode' ) );
    }

    /**
     * Resolve the stair_config attribute + its locked #treadit/#treadit2 values,
     * setting the $current_stair_config / $current_treadit / $current_treadit2
     * statics.
     *
     * Values are the REAL <option value> integers from form-template.php, not the
     * brief's descriptive labels. half:landing locks #treadit to 4 (Half Landing);
     * the half-turn JS then overrides treadit2 internally, so its locked value is a
     * harmless 1 and the template hides that now-N/A row.
     */
    public static function resolve_stair_config( $atts, $stair_type ) {
        $atts   = is_array( $atts ) ? $atts : array();
        $config = isset( $atts['stair_config'] ) ? sanitize_key( $atts['stair_config'] ) : '';
        if ( $config && ! in_array( $config, self::ALLOWED_CONFIGS, true ) ) {
            $config = '';
        }
        self::$current_stair_config = $config;

        // "{stair_type}:{stair_config}" => array( treadit, treadit2 ); '' = no lock.
        $treadit_map = array(
            'quarter:landing'     => array( '1', '' ),  // Quarter Landing
            'quarter:winder'      => array( '', '' ),   // user choice (2/3 winders)
            'half:landing'        => array( '4', '1' ), // Half Landing; treadit2 hidden/N-A
            'half:winder'         => array( '', '' ),   // user choice
            'half:double_quarter' => array( '1', '1' ), // two 90° quarter landings
        );

        $key = $stair_type . ':' . $config;
        if ( $config && isset( $treadit_map[ $key ] ) ) {
            self::$current_treadit  = $treadit_map[ $key ][0];
            self::$current_treadit2 = $treadit_map[ $key ][1];
        } else {
            self::$current_treadit  = '';
            self::$current_treadit2 = '';
        }
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
        self::resolve_stair_config( $atts, self::$current_stair_type );

        ob_start();
        include plugin_dir_path( __FILE__ ) . '../front/form-template.php';
        return ob_get_clean();
    }
}

new Stairbuilder_Plugin();
