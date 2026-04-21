<?php 

function my_portfolio_cpt() {
  $labels = array(
    'name'               => _x( 'Portfolio', 'post type general name' ),
    'singular_name'      => _x( 'Portfolio', 'post type singular name' ),
    'add_new'            => _x( 'Add New', 'book' ),
    'add_new_item'       => __( 'Add New Portfolio Item' ),
    'edit_item'          => __( 'Edit Portfolio Item' ),
    'new_item'           => __( 'New Portfolio Item' ),
    'all_items'          => __( 'All Portfolio Items' ),
    'view_item'          => __( 'View Portfolio Item' ),
    'search_items'       => __( 'Search Portfolio Items' ),
    'not_found'          => __( 'No portfolio items found' ),
    'not_found_in_trash' => __( 'No portfolio items found in the Trash' ), 
    'menu_name'          => 'Portfolio'
  );
  $args = array(
    'labels'        => $labels,
    'description'   => 'Holds our portfolio items data',
    'public'        => true,
    'menu_position' => 5,
    'supports'      => array( 'title', 'editor', 'thumbnail', 'excerpt', 'comments' ),
    'has_archive'   => true,
  );
  register_post_type( 'portfolio', $args ); 
}
add_action( 'init', 'my_portfolio_cpt' );

function portfolio_tax_cat() {
  $labels = array(
    'name'              => _x( 'Portfolio Categories', 'taxonomy general name' ),
    'singular_name'     => _x( 'Portfolio Category', 'taxonomy singular name' ),
    'search_items'      => __( 'Search Portfolio Categories' ),
    'all_items'         => __( 'All Portfolio Categories' ),
    'parent_item'       => __( 'Parent Portfolio Category' ),
    'parent_item_colon' => __( 'Parent Portfolio Category:' ),
    'edit_item'         => __( 'Edit Portfolio Category' ), 
    'update_item'       => __( 'Update Portfolio Category' ),
    'add_new_item'      => __( 'Add New Portfolio Category' ),
    'new_item_name'     => __( 'New Portfolio Category' ),
    'menu_name'         => __( 'Portfolio Categories' ),
  );
  $args = array(
    'labels' => $labels,
    'hierarchical' => true,
  );
  register_taxonomy( 'portfolio_category', 'portfolio', $args );
}
add_action( 'init', 'portfolio_tax_cat', 0 );

if( function_exists('acf_add_options_page') ) {
	
  acf_add_options_page(array(
      'page_title' 	=> 'Stair Builder Pricing',
      'menu_title'	=> 'Stair Builder Pricing',
      'menu_slug' 	=> 'stair-builder-pricing',
      'capability'	=> 'edit_posts',
      'redirect'		=> false
  ));
  
}