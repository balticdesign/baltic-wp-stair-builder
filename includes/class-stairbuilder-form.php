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

    /** Default (pre-selected but editable) value for #treadit / #treadit2 — used by
     *  winder configs. Empty string = template default (first visible option). */
    public static $current_treadit_default  = '';
    public static $current_treadit2_default = '';

    /** Option values to hide from #treadit / #treadit2 for the current config. */
    public static $current_treadit_hide  = array();
    public static $current_treadit2_hide = array();

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

        // Reset to "open form" defaults.
        self::$current_treadit          = self::$current_treadit2          = '';
        self::$current_treadit_default  = self::$current_treadit2_default  = '';
        self::$current_treadit_hide     = self::$current_treadit2_hide     = array();

        // Per-config behaviour for #treadit / #treadit2. Per field:
        //   lock    => option value to pre-select AND disable ('' = not locked)
        //   default => option value to pre-select but leave editable (winders)
        //   hide    => option values removed from the dropdown
        // Real option values: 1 = Quarter Landing, 2 = 2 Winders, 3 = 3 Winders,
        // 4 = Half Landing. half:landing locks treadit to 4; the half-turn JS then
        // overrides treadit2, so its locked value is a harmless 1 (row hidden).
        $map = array(
            'quarter:landing'     => array(
                'treadit' => array( 'lock' => '1' ),
            ),
            'quarter:winder'      => array(
                'treadit' => array( 'default' => '3', 'hide' => array( '1' ) ), // drop Quarter Landing
            ),
            'half:landing'        => array(
                'treadit'  => array( 'lock' => '4' ),
                'treadit2' => array( 'lock' => '1' ),
            ),
            'half:winder'         => array(
                'treadit'  => array( 'default' => '3', 'hide' => array( '4' ) ), // drop Half Landing
                'treadit2' => array( 'default' => '3' ),                          // Quarter Landing stays
            ),
            'half:double_quarter' => array(
                'treadit'  => array( 'lock' => '1' ),
                'treadit2' => array( 'lock' => '1' ),
            ),
        );

        $key = $stair_type . ':' . $config;
        if ( ! $config || ! isset( $map[ $key ] ) ) {
            return;
        }
        $spec = $map[ $key ];
        if ( isset( $spec['treadit'] ) ) {
            $t = $spec['treadit'];
            self::$current_treadit         = isset( $t['lock'] )    ? $t['lock']    : '';
            self::$current_treadit_default = isset( $t['default'] ) ? $t['default'] : '';
            self::$current_treadit_hide    = isset( $t['hide'] )    ? $t['hide']    : array();
        }
        if ( isset( $spec['treadit2'] ) ) {
            $t = $spec['treadit2'];
            self::$current_treadit2         = isset( $t['lock'] )    ? $t['lock']    : '';
            self::$current_treadit2_default = isset( $t['default'] ) ? $t['default'] : '';
            self::$current_treadit2_hide    = isset( $t['hide'] )    ? $t['hide']    : array();
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
