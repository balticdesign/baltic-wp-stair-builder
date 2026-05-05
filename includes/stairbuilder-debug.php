<?php
/**
 * Stairbuilder admin diagnostics page.
 *
 * Adds wp-admin → Tools → Stairbuilder Debug. Shows:
 *  - the migration flag value
 *  - every page using the [stairbuilder_form] shortcode, with its actual
 *    attributes side-by-side with the legacy ACF staircase_type value
 *  - the full stairbuilder_options blob
 *
 * Read-only. Capability gated to manage_options.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'admin_menu', function () {
    add_management_page(
        'Stairbuilder Debug',
        'Stairbuilder Debug',
        'manage_options',
        'sb-debug-options',
        'bd_stairbuilder_render_debug_page'
    );
} );

function bd_stairbuilder_render_debug_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $opts     = get_option( 'stairbuilder_options', '<<NOT SET>>' );
    $migrated = get_option( 'stairbuilder_migrated_from_acf', '<<flag not set>>' );

    echo '<div class="wrap"><h1>Stairbuilder Options Debug</h1>';
    echo '<p><strong>Migration flag:</strong> ' . esc_html( var_export( $migrated, true ) ) . '</p>';

    echo '<h2>Pages using [stairbuilder_form]</h2>';
    echo '<table class="widefat"><thead><tr>';
    echo '<th>ID</th><th>Title</th><th>Has shortcode?</th><th>Shortcode atts</th><th>Legacy ACF value</th>';
    echo '</tr></thead><tbody>';

    $pages = get_posts( array( 'post_type' => 'page', 'numberposts' => -1 ) );
    foreach ( $pages as $p ) {
        $has  = has_shortcode( $p->post_content, 'stairbuilder_form' );
        $atts = '';
        if ( $has && preg_match( '/\[stairbuilder_form\b([^\]]*)\]/', $p->post_content, $m ) ) {
            $atts = trim( $m[1] );
        }
        $acf = function_exists( 'get_field' )
            ? get_field( 'staircase_type', $p->ID )
            : '<i>ACF not active</i>';

        echo '<tr>';
        echo '<td>' . esc_html( $p->ID ) . '</td>';
        echo '<td>' . esc_html( $p->post_title ) . '</td>';
        echo '<td>' . ( $has ? 'yes' : '—' ) . '</td>';
        echo '<td><code>' . esc_html( $atts ) . '</code></td>';
        echo '<td>' . esc_html( is_string( $acf ) ? $acf : var_export( $acf, true ) ) . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';

    echo '<h2>stairbuilder_options blob</h2>';
    echo '<pre style="background:#fff;padding:12px;border:1px solid #ccc;max-height:600px;overflow:auto;">';
    echo esc_html( print_r( $opts, true ) );
    echo '</pre></div>';
}
