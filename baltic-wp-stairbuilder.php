<?php
/*
Plugin Name:	Baltic Stairbuilder
Plugin URI:		https://balticdesign.uk/
Description:	A Staircase Builder Solution
Version:		1.5.2
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

add_action( 'wp_enqueue_scripts', 'custom_enqueue_files' );
/**
 * Loads <list assets here>.
 */
function custom_enqueue_files() {

	global $post;

	// Page-level staircase_type stays ACF-bound for now (per-page meta, not options).
	// Defensive guard so the front-end doesn't fatal if ACF is deactivated.
	$stair_type = function_exists('get_field') && isset($post->ID)
		? get_field('staircase_type', $post->ID)
		: '';

// Diagram colours (now sourced from wp_options blob via stairbuilder_get_option)
$bd_diagram_colours = array(
    'treads_fill'      => stairbuilder_get_option('treads_fill'),
    'treads_outline'   => stairbuilder_get_option('treads_outline'),
    'treads_text'      => stairbuilder_get_option('treads_text'),
    'posts_fill'       => stairbuilder_get_option('posts_fill'),
    'posts_outline'    => stairbuilder_get_option('posts_outline'),
    'posts_text'       => stairbuilder_get_option('posts_text'),
    'stringer_outline' => stairbuilder_get_option('stringer_outline'),
    'stringer_fill'    => stairbuilder_get_option('stringer_fill'),
    'spindles'         => stairbuilder_get_option('spindles'),
);

	// loads a CSS file in the head.
	// wp_enqueue_style( 'highlightjs-css', plugin_dir_url( __FILE__ ) . 'assets/css/style.css' );

	/**
	 * loads JS files in the footer.
	 */
	 wp_enqueue_script( 'builder-utils', plugin_dir_url( __FILE__ ) . 'assets/js/core/builderUtils.js', '', '1.5.2', true );
	 wp_enqueue_script( 'stairbuilder', plugin_dir_url( __FILE__ ) . 'assets/js/Stairs.js', 'builder-utils', '1.5.2', true );

	 // Determine which flight script to load and set dependency for formLogic
	 $flight_script_handle = 'stairbuilder'; // default fallback

	 if($stair_type ==='straight')
	 {
	 	wp_enqueue_script( 'straightFlight', plugin_dir_url( __FILE__ ) . 'assets/js/straightFlight.js', 'stairbuilder', '1.5.2', true );
         wp_localize_script('straightFlight', 'bd_diagram_colours', $bd_diagram_colours);
         $flight_script_handle = 'straightFlight';
	 }
	 elseif ($stair_type === 'quarter')
	 {
		wp_enqueue_script( 'quarterTurn', plugin_dir_url( __FILE__ ) . 'assets/js/quarterTurn.js', 'stairbuilder', '1.5.2', true );
        wp_localize_script('quarterTurn', 'bd_diagram_colours', $bd_diagram_colours);
        $flight_script_handle = 'quarterTurn';
	 }
	 elseif ($stair_type === 'half') {
		wp_enqueue_script( 'halfTurn', plugin_dir_url( __FILE__ ) . 'assets/js/halfTurn.js', 'stairbuilder', '1.5.2', true );
        wp_localize_script('halfTurn', 'bd_diagram_colours', $bd_diagram_colours);
        $flight_script_handle = 'halfTurn';
	 }

	 wp_enqueue_script( 'formLogic', plugin_dir_url( __FILE__ ) . 'assets/js/formLogic.js', $flight_script_handle, '1.5.2', true );
	 wp_enqueue_script( 'priceCalc', plugin_dir_url( __FILE__ ) . 'assets/js/priceCalc.js', $flight_script_handle, '1.5.2', true );
	 $ajax_nonce = wp_create_nonce('sb-ajax-nonce');
	 wp_localize_script( 'formLogic', 'stairBuilderVars', array(
		'ajax_url' => admin_url( 'admin-ajax.php' ),
		'nonce'    => $ajax_nonce,
	  ));

	 wp_enqueue_style( 'builder-style', plugin_dir_url( __FILE__ ) . 'assets/css/builder.css', array(), '1.5.2' );
	 wp_enqueue_style( 'baltic-stair-layout', plugin_dir_url( __FILE__ ) . 'assets/css/layout.css', array(), '1.5.2' );
	 wp_enqueue_script( 'baltic-stair-layout', plugin_dir_url( __FILE__ ) . 'assets/js/layout.js', array(), '1.5.2', true );

	  // Define plugin directory URL as a global variable
	  wp_add_inline_script('stairbuilder', 'var pluginDirUrl = "' . plugin_dir_url(__FILE__) . '";');

	// wp_enqueue_script( 'highlightjs-init', plugin_dir_url( __FILE__ ) . 'assets/js/highlight-init.js', '', '1.0.0', true );
}

require_once plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';
// Pricing settings first — defines stairbuilder_get_option() used by other modules.
require_once plugin_dir_path( __FILE__ ) . 'includes/stairbuilder-pricing-settings.php';
require plugin_dir_path( __FILE__ ) . 'includes/class-baltic-cpt.php';
require plugin_dir_path( __FILE__ ) . 'includes/class-stairbuilder-form.php';
require plugin_dir_path( __FILE__ ) . 'includes/class-stairbuilder-leads.php';
require plugin_dir_path( __FILE__ ) . 'includes/stairbuilder-options.php';
require plugin_dir_path( __FILE__ ) . 'includes/stairbuilder-lead-capture.php';

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
