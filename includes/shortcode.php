<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function dq_shortcode( $atts ) {
	$atts = shortcode_atts( array(
		'set' => '',
		'randomize' => '1',
		'per_day' => '1',
	), $atts, 'daily_quotes' );

	$set_id = is_numeric( $atts['set'] ) ? (int) $atts['set'] : 0;
	if ( ! $set_id && $atts['set'] ) {
		$set = get_page_by_title( sanitize_text_field( $atts['set'] ), OBJECT, 'dq_set' );
		$set_id = $set ? (int) $set->ID : 0;
	}
	if ( ! $set_id ) { return ''; }

	$randomize = $atts['randomize'] === '1' || $atts['randomize'] === 'true' || $atts['randomize'] === 'on';
	$per_day = $atts['per_day'] === '1' || $atts['per_day'] === 'true' || $atts['per_day'] === 'on';
	$item_id = dq_select_next_item( $set_id, $randomize, $per_day );
	if ( ! $item_id ) { return ''; }

	$content_post = get_post( $item_id );
	if ( ! $content_post ) { return ''; }

	$content = apply_filters( 'the_content', $content_post->post_content );

	return '<div class="dq-quote">' . $content . '</div>';
}
add_shortcode( 'daily_quotes', 'dq_shortcode' );

/**
 * Hidden shortcode [thedate] - displays current date in dd.mm.YY format
 */
function dq_thedate_shortcode( $atts ) {
	$atts = shortcode_atts( array(
		'format' => 'dd.mm.YY',
	), $atts, 'thedate' );
	
	$format = $atts['format'];
	
	// Convert format to PHP date format
	$format = str_replace( 'dd', 'd', $format );
	$format = str_replace( 'mm', 'm', $format );
	$format = str_replace( 'YY', 'y', $format );
	
	return current_time( $format );
}
add_shortcode( 'thedate', 'dq_thedate_shortcode' );