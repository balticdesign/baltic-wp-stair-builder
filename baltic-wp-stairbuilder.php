<?php
/*
Plugin Name:	Baltic Stairbuilder
Plugin URI:		https://balticdesign.uk/
Description:	A Staircase Builder Solution
Version:		1.1.0
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
  
	// Get the ACF field value for stair_type
	$stair_type = get_field('staircase_type', $post->ID);

// Get ACF colors
$acf_colours = array(
'treads_fill' => get_field('treads_fill', 'option'),
    'treads_outline' => get_field('treads_outline', 'option'),
    'treads_text' => get_field('treads_text','option'),
    'posts_fill' => get_field('posts_fill', 'option'),
    'posts_outline' => get_field('posts_outline', 'option'),
    'posts_text' => get_field('posts_text', 'option'),
    'stringer_outline' => get_field('stringer_outline', 'option'),
    'stringer_fill' => get_field('stringer_fill', 'option'),
    'spindles' => get_field('spindles', 'option'),
);

	// loads a CSS file in the head.
	// wp_enqueue_style( 'highlightjs-css', plugin_dir_url( __FILE__ ) . 'assets/css/style.css' );

	/**
	 * loads JS files in the footer.
	 */
	 wp_enqueue_script( 'builder-utils', plugin_dir_url( __FILE__ ) . 'assets/js/core/builderUtils.js', '', '1.1.0', true );
	 wp_enqueue_script( 'stairbuilder', plugin_dir_url( __FILE__ ) . 'assets/js/Stairs.js', 'builder-utils', '9.9.1', true );
	 
	 // Determine which flight script to load and set dependency for formLogic
	 $flight_script_handle = 'stairbuilder'; // default fallback
	 
	 if($stair_type ==='straight') 
	 {
	 	wp_enqueue_script( 'straightFlight', plugin_dir_url( __FILE__ ) . 'assets/js/straightFlight.js', 'stairbuilder', '10.1.0', true );
         wp_localize_script('straightFlight', 'acf_colours', $acf_colours);
         $flight_script_handle = 'straightFlight';
	 } 
	 elseif ($stair_type === 'quarter') 
	 {
		wp_enqueue_script( 'quarterTurn', plugin_dir_url( __FILE__ ) . 'assets/js/quarterTurn.js', 'stairbuilder', '9.9.1', true );
        wp_localize_script('quarterTurn', 'acf_colours', $acf_colours);
        $flight_script_handle = 'quarterTurn';
	 } 
	 elseif ($stair_type === 'half') {
		wp_enqueue_script( 'halfTurn', plugin_dir_url( __FILE__ ) . 'assets/js/halfTurn.js', 'stairbuilder', '9.9.1', true );
        wp_localize_script('halfTurn', 'acf_colours', $acf_colours);
        $flight_script_handle = 'halfTurn';
	 }
	 
	 wp_enqueue_script( 'formLogic', plugin_dir_url( __FILE__ ) . 'assets/js/formLogic.js', $flight_script_handle, '9.9.0', true );
	 wp_enqueue_script( 'priceCalc', plugin_dir_url( __FILE__ ) . 'assets/js/priceCalc.js', $flight_script_handle, '10.0.0', true );
	 $ajax_nonce = wp_create_nonce('sb-ajax-nonce');
	 wp_localize_script( 'formLogic', 'stairBuilderVars', array(
		'ajax_url' => admin_url( 'admin-ajax.php' ),
		'nonce' => $ajax_nonce, // Add the nonce
		'cart_url' => wc_get_cart_url(), // Cart URL
	  ));

	 wp_enqueue_style( 'builder-style', plugin_dir_url( __FILE__ ) . 'assets/css/builder.css', array(), '1.0.1' );

	  // Define plugin directory URL as a global variable
	  wp_add_inline_script('stairbuilder', 'var pluginDirUrl = "' . plugin_dir_url(__FILE__) . '";');

	// wp_enqueue_script( 'highlightjs-init', plugin_dir_url( __FILE__ ) . 'assets/js/highlight-init.js', '', '1.0.0', true );
}

require_once plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';
require plugin_dir_path( __FILE__ ) . 'includes/class-baltic-cpt.php';
require plugin_dir_path( __FILE__ ) . 'includes/class-stairbuilder-form.php';
require plugin_dir_path( __FILE__ ) . 'includes/stairbuilder-options.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/stairbuilder-pricing-settings.php';

function custom_plugin_init() {
	add_filter('woocommerce_locate_template', 'custom_woocommerce_locate_template', 10, 3);
	add_filter( 'woocommerce_get_item_data', 'display_custom_meta_in_cart', 10, 2 );
}

function on_plugin_activation() {
    $upload_dir = wp_upload_dir();
    $pdf_dir_path = $upload_dir['basedir'] . '/stairbuilder_PDFs/';

    if (!file_exists($pdf_dir_path)) {
        wp_mkdir_p($pdf_dir_path);
    }
}

register_activation_hook(__FILE__, 'on_plugin_activation');

function custom_woocommerce_locate_template($template, $template_name, $template_path) {
    $template_directory = plugin_dir_path( __FILE__ ) . 'woocommerce/';
    $template_path = $template_directory . $template_name;

    if (file_exists( $template_path )) {
        if (function_exists('wc_get_logger')) {
            $logger = wc_get_logger();
            $logger->info('Template found: ' . $template_name, array('source' => 'custom_woocommerce_locate_template'));
        }
        return $template_path;
    } else {
        if (function_exists('wc_get_logger')) {
            $logger = wc_get_logger();
            $logger->info('Template was not found: ' . $template_path );
        }
        return $template;
    }
}
function display_custom_meta_in_cart( $item_data, $cart_item ) {
    if ( is_cart() && isset( $cart_item['custom_meta'] ) ) {
        $decoded_meta = urldecode( $cart_item['custom_meta'] );
        $parsed_meta = [];
        parse_str( $decoded_meta, $parsed_meta );

        // Retrieve specific values from the parsed data
        $floor_height = isset( $parsed_meta['floor-height'] ) ? $parsed_meta['floor-height'] : '';
        $risers = isset( $parsed_meta['risers'] ) ? $parsed_meta['risers'] : '';

        // Add the extracted values to the item data
        $item_data[] = array(
            'key'     => 'Floor Height',
            'value'   => $floor_height,
            'display' => '',
        );
        $item_data[] = array(
            'key'     => 'Risers',
            'value'   => $risers,
            'display' => '',
        );
		$item_data[] = array(
            'key'     => '',
            'value'   => '<a href="#" id="view-details" data-details="' . htmlspecialchars(json_encode($parsed_meta)) . '">View Details</a>',
            'display' => '',
        );
    }

    return $item_data;
}
add_action( 'woocommerce_widget_shopping_cart_total', 'display_vat_in_mini_cart', 20 );
function display_vat_in_mini_cart() {
    if ( WC()->cart->tax_total > 0 ) {
        echo '<p class="woocommerce-mini-cart__total vat">';
        echo 'VAT: <bdi><strong>£' . number_format( WC()->cart->tax_total, 2 ).'</strong></bdi>';
        echo '</p>';
    }
}

add_action( 'woocommerce_widget_shopping_cart_total', 'display_total_in_mini_cart', 30 );
function display_total_in_mini_cart() {
    echo '<p class="woocommerce-mini-cart__total totals">';
    echo 'Total: <bdi><strong>£' . number_format( WC()->cart->total, 2 ).'</strong></bdi>';
    echo '</p>';
}
add_action( 'init', 'custom_plugin_init' );

function attach_pdf_to_email( $attachments, $email_id, $order ) {
    // If it's the new order email
    if ( 'new_order' == $email_id ) {
        // Get the PDF path
        $pdf_path = get_post_meta( $order->get_id(), '_order_pdf_path', true );
        
        // If a PDF was generated for this order, add it as an attachment
        if ( $pdf_path ) {
            $attachments[] = $pdf_path;
        }
    }
  
    return $attachments;
}
add_filter( 'woocommerce_email_attachments', 'attach_pdf_to_email', 10, 3 );