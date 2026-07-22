<?php
/*
Plugin Name:	Baltic Stairbuilder
Plugin URI:		https://balticdesign.uk/
Description:	A Staircase Builder Solution
Version:		2.20.0
Author:			Dan Cotugno-Cregin
Author URI:		https://balticdesign.uk/
License:		GPL-2.0+
License URI:	http://www.gnu.org/licenses/gpl-2.0.txt

This plugin is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 2 of the License, or
any later version.

This plugin is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with This plugin. If not, see {URI to Plugin License}.
*/

if ( ! defined( 'WPINC' ) ) {
	die;
}

define( 'BALTIC_STAIRBUILDER_VERSION', '2.20.0' );

require_once plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';
// Pricing settings first — defines stairbuilder_get_option() used by other modules.
require_once plugin_dir_path( __FILE__ ) . 'includes/stairbuilder-pricing-settings.php';
require plugin_dir_path( __FILE__ ) . 'includes/class-baltic-cpt.php';
require plugin_dir_path( __FILE__ ) . 'includes/class-stairbuilder-form.php';
require plugin_dir_path( __FILE__ ) . 'includes/class-stairbuilder-leads.php';
require plugin_dir_path( __FILE__ ) . 'includes/stairbuilder-options.php';
require plugin_dir_path( __FILE__ ) . 'includes/stairbuilder-lead-capture.php';
if ( is_admin() ) {
	require plugin_dir_path( __FILE__ ) . 'includes/stairbuilder-debug.php';
}

add_action( 'wp_enqueue_scripts', 'custom_enqueue_files' );
function custom_enqueue_files() {

	global $post;

	// Bail unless the current page actually uses [stairbuilder_form].
	// Side benefit: plugin assets stop loading on every page of the site.
	if ( ! isset( $post->post_content ) || ! has_shortcode( $post->post_content, 'stairbuilder_form' ) ) {
		return;
	}

	// Resolve staircase type by parsing the shortcode out of post content.
	$stair_type = Stairbuilder_Plugin::DEFAULT_TYPE;
	if ( preg_match( '/\[stairbuilder_form\b([^\]]*)\]/', $post->post_content, $m ) ) {
		$atts       = shortcode_parse_atts( $m[1] );
		$stair_type = Stairbuilder_Plugin::resolve_stair_type( $atts, (int) $post->ID );
	}

	$bd_diagram_colours = array(
		'canvas_bg'        => stairbuilder_get_option( 'canvas_bg' ),
		'treads_fill'      => stairbuilder_get_option( 'treads_fill' ),
		'treads_outline'   => stairbuilder_get_option( 'treads_outline' ),
		'treads_text'      => stairbuilder_get_option( 'treads_text' ),
		'posts_fill'       => stairbuilder_get_option( 'posts_fill' ),
		'posts_outline'    => stairbuilder_get_option( 'posts_outline' ),
		'posts_text'       => stairbuilder_get_option( 'posts_text' ),
		'stringer_outline' => stairbuilder_get_option( 'stringer_outline' ),
		'stringer_fill'    => stairbuilder_get_option( 'stringer_fill' ),
		'spindles'         => stairbuilder_get_option( 'spindles' ),
	);

	wp_enqueue_script( 'builder-utils', plugin_dir_url( __FILE__ ) . 'assets/js/core/builderUtils.js', '', BALTIC_STAIRBUILDER_VERSION, true );
	wp_enqueue_script( 'stairbuilder', plugin_dir_url( __FILE__ ) . 'assets/js/Stairs.js', 'builder-utils', BALTIC_STAIRBUILDER_VERSION, true );
	// 1.7.0: pan/zoom input handler. Mutates Stairs.viewport — must load
	// after Stairs.js (where viewport state is defined) and before the
	// flight scripts (which trigger Stairs.init via form change handlers).
	wp_enqueue_script( 'stairbuilder-viewport', plugin_dir_url( __FILE__ ) . 'assets/js/viewport.js', 'stairbuilder', BALTIC_STAIRBUILDER_VERSION, true );

	$flight_script_handle = 'stairbuilder';

	if ( $stair_type === 'straight' ) {
		wp_enqueue_script( 'straightFlight', plugin_dir_url( __FILE__ ) . 'assets/js/straightFlight.js', 'stairbuilder', BALTIC_STAIRBUILDER_VERSION, true );
		wp_localize_script( 'straightFlight', 'bd_diagram_colours', $bd_diagram_colours );
		$flight_script_handle = 'straightFlight';
	} elseif ( $stair_type === 'quarter' ) {
		wp_enqueue_script( 'quarterTurn', plugin_dir_url( __FILE__ ) . 'assets/js/quarterTurn.js', 'stairbuilder', BALTIC_STAIRBUILDER_VERSION, true );
		wp_localize_script( 'quarterTurn', 'bd_diagram_colours', $bd_diagram_colours );
		$flight_script_handle = 'quarterTurn';
	} elseif ( $stair_type === 'half' ) {
		wp_enqueue_script( 'halfTurn', plugin_dir_url( __FILE__ ) . 'assets/js/halfTurn.js', 'stairbuilder', BALTIC_STAIRBUILDER_VERSION, true );
		wp_localize_script( 'halfTurn', 'bd_diagram_colours', $bd_diagram_colours );
		$flight_script_handle = 'halfTurn';
	}

	wp_enqueue_script( 'formLogic', plugin_dir_url( __FILE__ ) . 'assets/js/formLogic.js', $flight_script_handle, BALTIC_STAIRBUILDER_VERSION, true );
	wp_enqueue_script( 'priceCalc', plugin_dir_url( __FILE__ ) . 'assets/js/priceCalc.js', $flight_script_handle, BALTIC_STAIRBUILDER_VERSION, true );
	$ajax_nonce = wp_create_nonce( 'sb-ajax-nonce' );

	// Construction Settings — building-regs warning + hard maximums for the
	// front-end form. (bd_stairbuilder_is_enabled() isn't loaded yet at enqueue
	// time, so resolve the toggle's null/empty-as-enabled default inline here.)
	$cs_warn_raw = stairbuilder_get_option( 'going_regs_warning_enabled', null );
	$cs_warn_on  = ( $cs_warn_raw === null || $cs_warn_raw === '' ) ? true : ! empty( $cs_warn_raw );
	$cs_min_raw  = stairbuilder_get_option( 'going_regs_warning_min', null );
	$cs_max_raw  = stairbuilder_get_option( 'going_regs_warning_max', null );

	// Building-regs regime table (v2.16.0 Phase 1) — keyed by the regime code
	// (building_reg_value), consumed by getStaircaseConfig()'s rise/pitch gate and
	// the going soft-warning/hard-max logic. Numeric columns are passed through as
	// stored: '' means "no constraint" (JS parseFloat('') === NaN → skipped).
	$bd_regs_table  = array();
	$bd_regs_rows   = stairbuilder_get_option( 'building_regs', array() );
	$bd_regs_cols   = array( 'min_going', 'max_rise', 'min_rise', 'min_width', 'max_pitch', 'two_r_g_min', 'two_r_g_max', 'max_open_gap', 'max_risers_run', 'on_exceed_mode', 'on_exceed_message', 'on_exceed_url_quarter', 'on_exceed_url_half' );
	if ( is_array( $bd_regs_rows ) ) {
		foreach ( $bd_regs_rows as $bd_reg ) {
			if ( ! is_array( $bd_reg ) ) {
				continue;
			}
			$bd_code = isset( $bd_reg['building_reg_value'] ) ? (string) $bd_reg['building_reg_value'] : '';
			if ( '' === $bd_code ) {
				continue;
			}
			$bd_entry = array( 'name' => isset( $bd_reg['building_reg_name'] ) ? (string) $bd_reg['building_reg_name'] : $bd_code );
			foreach ( $bd_regs_cols as $bd_col ) {
				$bd_entry[ $bd_col ] = isset( $bd_reg[ $bd_col ] ) ? $bd_reg[ $bd_col ] : '';
			}
			$bd_regs_table[ $bd_code ] = $bd_entry;
		}
	}

	// Availability enforcement (v2.16.0 Phase 2). strict_for keyed by construction
	// code; available_for travels on each <option> as data-available-for so the
	// filter needs no row identity. FULLY GENERIC — no construction code is
	// special-cased; a licensee with zero tags gets pre-v2.16 behaviour exactly.
	$bd_strict_for  = array();
	$bd_ct_meta     = array();
	$bd_ct_rows     = stairbuilder_get_option( 'construction_types', array() );
	if ( is_array( $bd_ct_rows ) ) {
		foreach ( $bd_ct_rows as $bd_ct ) {
			if ( ! is_array( $bd_ct ) ) {
				continue;
			}
			$bd_ct_code = isset( $bd_ct['construction_code'] ) ? (string) $bd_ct['construction_code'] : '';
			if ( '' === $bd_ct_code ) {
				continue;
			}
			$bd_strict_for[ $bd_ct_code ] = ( isset( $bd_ct['strict_for'] ) && is_array( $bd_ct['strict_for'] ) ) ? array_map( 'strval', $bd_ct['strict_for'] ) : array();
			// Open-riser geometry meta (Phase 3). '' default_open_gap = not set → the
			// board-height helper suppresses under No Building Regs (both-empty case).
			$bd_ct_meta[ $bd_ct_code ] = array(
				'derives_riser_height' => ! empty( $bd_ct['derives_riser_height'] ) ? 1 : 0,
				'default_open_gap'     => isset( $bd_ct['default_open_gap'] ) ? $bd_ct['default_open_gap'] : '',
			);
		}
	}
	$bd_availability = array(
		'strict_for'      => $bd_strict_for,
		'construction_meta' => $bd_ct_meta,
		// repeater id → the DOM select it drives.
		'repeater_select' => array(
			'stringer_types' => '#stringer_material',
			'tread_types'    => '#tread_material',
			'riser_types'    => '#riser_material',
			'tread_profiles' => '#tread-profile',
		),
	);

	wp_localize_script( 'formLogic', 'stairBuilderVars', array(
		'ajax_url' => admin_url( 'admin-ajax.php' ),
		'nonce'    => $ajax_nonce,
		// Spindle balustrading is Material-first (Pine/Oak/Metal/Glass → filtered
		// Style list), resolved client-side from this catalogue.
		'spindles' => function_exists( 'bd_stairbuilder_spindle_frontend_rows' ) ? bd_stairbuilder_spindle_frontend_rows() : array(),
		'construction' => array(
			'going_warning_enabled' => $cs_warn_on ? 1 : 0,
			'going_warning_min'     => ( $cs_min_raw === null || $cs_min_raw === '' ) ? 220 : (float) $cs_min_raw,
			'going_warning_max'     => ( $cs_max_raw === null || $cs_max_raw === '' ) ? 250 : (float) $cs_max_raw,
			// Empty string = no hard limit (JS parseFloat('') is NaN).
			'going_max'             => stairbuilder_get_option( 'going_max', '' ),
			'going_max_message'     => stairbuilder_get_option( 'going_max_message', '' ),
			'width_max'             => stairbuilder_get_option( 'width_max', '' ),
			'width_max_message'     => stairbuilder_get_option( 'width_max_message', '' ),
		),
		// Regime constraints keyed by building_reg_value (Phase 1). The active
		// regime is the current #building_regs selection.
		'regs' => $bd_regs_table,
		// Geometry defaults (Phase 1). riser_search_* bound the riser-height
		// search when the active regime supplies no min/max rise (e.g. No Building
		// Regs). Default 150/220 = Approved Document K non-domestic min / domestic
		// max — configurable so a licensee building loft ladders can widen them.
		'geometry' => array(
			'riser_search_min' => stairbuilder_get_option( 'riser_search_min', 150 ),
			'riser_search_max' => stairbuilder_get_option( 'riser_search_max', 220 ),
			// Top lip (Phase 4) — display/drawing only, never priced. Default 0 =
			// pre-v2.16 geometry. SPD enters 43. See §5.2 boundary.
			'top_lip_mm'       => stairbuilder_get_option( 'top_lip_mm', 0 ),
		),
		'availability' => $bd_availability,
	) );

	wp_enqueue_style( 'builder-style', plugin_dir_url( __FILE__ ) . 'assets/css/builder.css', array(), BALTIC_STAIRBUILDER_VERSION );
	wp_enqueue_style( 'baltic-stair-layout', plugin_dir_url( __FILE__ ) . 'assets/css/layout.css', array(), BALTIC_STAIRBUILDER_VERSION );

	// Brand Colours — emit per-box CSS custom properties from the admin
	// settings. Only set vars override layout.css's built-in fallbacks, so
	// an unconfigured box keeps its default appearance.
	$bd_brand_colour_vars = array(
		// Configure panel (left)
		'--bd-form-bg'          => stairbuilder_get_option( 'form_bg' ),
		'--bd-form-text'        => stairbuilder_get_option( 'form_text' ),
		'--bd-form-link'        => stairbuilder_get_option( 'form_link' ),
		'--bd-section-open-bg'  => stairbuilder_get_option( 'section_open_bg' ),
		'--bd-line'             => stairbuilder_get_option( 'panel_hairline' ),
		'--bd-muted'            => stairbuilder_get_option( 'panel_muted' ),
		// Measurements panel (right)
		'--bd-mm-bg'      => stairbuilder_get_option( 'measurements_bg' ),
		'--bd-mm-text'    => stairbuilder_get_option( 'measurements_text' ),
		'--bd-mm-link'    => stairbuilder_get_option( 'measurements_link' ),
		// Primary action (Get free quote button + mobile Configure bar).
		// Kept under the legacy 'quote_*' keys — that box moved into the footer.
		'--bd-quote-bg'   => stairbuilder_get_option( 'quote_bg' ),
		'--bd-quote-text' => stairbuilder_get_option( 'quote_text' ),
		// Selected delivery / packaging pills
		'--bd-tab-active-bg'   => stairbuilder_get_option( 'tab_active_bg' ),
		'--bd-tab-active-text' => stairbuilder_get_option( 'tab_active_text' ),
	);
	$bd_brand_colour_decls = '';
	foreach ( $bd_brand_colour_vars as $var => $val ) {
		$hex = sanitize_hex_color( (string) $val );
		if ( $hex ) {
			$bd_brand_colour_decls .= $var . ':' . $hex . ';';
		}
	}
	if ( $bd_brand_colour_decls !== '' ) {
		wp_add_inline_style( 'baltic-stair-layout', '.bd-stairbuilder-layout{' . $bd_brand_colour_decls . '}' );
	}
	wp_enqueue_script( 'baltic-stair-layout', plugin_dir_url( __FILE__ ) . 'assets/js/layout.js', array(), BALTIC_STAIRBUILDER_VERSION, true );

	wp_add_inline_script( 'stairbuilder', 'var pluginDirUrl = "' . plugin_dir_url( __FILE__ ) . '";' );

	// Diagnostics — only loaded when ?sb_debug=1 or SB_DEBUG constant is set in wp-config.
	if (
		( isset( $_GET['sb_debug'] ) && $_GET['sb_debug'] === '1' ) ||
		( defined( 'SB_DEBUG' ) && SB_DEBUG )
	) {
		wp_enqueue_script(
			'stairbuilder-diagnostics',
			plugin_dir_url( __FILE__ ) . 'assets/js/diagnostics.js',
			array( 'jquery' ),
			BALTIC_STAIRBUILDER_VERSION,
			true
		);
	}
}

function on_plugin_activation() {
    $upload_dir = wp_upload_dir();
    $pdf_dir_path = $upload_dir['basedir'] . '/stairbuilder_PDFs/';

    if (!file_exists($pdf_dir_path)) {
        wp_mkdir_p($pdf_dir_path);
    }

    BD_Stair_Builder_Leads::install();
    baltic_stair_install_quote_page();
}
register_activation_hook(__FILE__, 'on_plugin_activation');
