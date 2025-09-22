<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

// Minimal REST endpoints if needed by the block editor.

function dq_register_rest_routes() {
	register_rest_route( 'daily-quotes/v1', '/sets', array(
		'methods' => 'GET',
		'permission_callback' => function() { return current_user_can( 'edit_posts' ); },
		'callback' => function() {
			$sets = get_posts( array(
				'post_type' => 'dq_set',
				'posts_per_page' => -1,
				'post_status' => array( 'publish', 'draft' ),
				'orderby' => 'title',
				'order' => 'ASC',
			) );
			return rest_ensure_response( array_map( function( $p ) {
				return array( 'id' => $p->ID, 'title' => get_the_title( $p ) );
			}, $sets ) );
		}
	) );
}
add_action( 'rest_api_init', 'dq_register_rest_routes' );
