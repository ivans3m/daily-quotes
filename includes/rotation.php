<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

// Core rotation selection and state storage.

/**
 * Get all item IDs belonging to a set ordered by menu_order then date.
 *
 * @param int $set_id
 * @return int[]
 */
function dq_get_set_items( $set_id ) {
    $items = get_posts( array(
        'post_type' => 'dq_item',
        'posts_per_page' => -1,
        'post_status' => array( 'publish' ),
        'orderby' => array( 'menu_order' => 'ASC', 'date' => 'ASC' ),
        'meta_query' => array(
            array(
                'key' => '_dq_set_id',
                'value' => (int) $set_id,
                'compare' => '=',
            ),
        ),
        'fields' => 'ids',
    ) );
    return array_map( 'intval', $items );
}

/**
 * Select next item ID from a set without repeating until all are shown.
 * If $randomize is true, pick a random remaining item; otherwise pick the next in order.
 * State is stored per-set in an option of shape: [ 'shown' => int[], 'last' => int ].
 *
 * @param int  $set_id
 * @param bool $randomize
 * @return int Item post ID or 0 if none
 */
function dq_select_next_item( $set_id, $randomize = true, $per_day = false ) {
    $set_id = (int) $set_id;
    if ( $set_id <= 0 ) { return 0; }

    $all_items = dq_get_set_items( $set_id );
    if ( empty( $all_items ) ) { return 0; }

    $state_key = 'dq_state_' . $set_id;
    $state = get_option( $state_key, array( 'shown' => array(), 'last' => 0, 'daily' => array() ) );
    $shown = array_map( 'intval', isset( $state['shown'] ) ? $state['shown'] : array() );

    // Per-day pinning: if we already chose for today, return it.
    if ( $per_day ) {
        $today = current_time( 'Y-m-d' );
        if ( isset( $state['daily'][ $today ] ) && $state['daily'][ $today ] ) {
            $chosen = (int) $state['daily'][ $today ];
            if ( in_array( $chosen, $all_items, true ) ) {
                return $chosen;
            }
        }
    }

    $remaining = array_values( array_diff( $all_items, $shown ) );
    if ( empty( $remaining ) ) {
        $shown = array();
        $remaining = $all_items;
    }

    if ( $randomize ) {
        $selected = $remaining[ array_rand( $remaining ) ];
    } else {
        $last = isset( $state['last'] ) ? (int) $state['last'] : 0;
        if ( $last && in_array( $last, $all_items, true ) ) {
            $pos = array_search( $last, $all_items, true );
            $cycle = array_merge( array_slice( $all_items, $pos + 1 ), array_slice( $all_items, 0, $pos + 1 ) );
            $ordered_remaining = array_values( array_diff( $cycle, $shown ) );
            $selected = ! empty( $ordered_remaining ) ? $ordered_remaining[0] : $cycle[0];
        } else {
            $selected = $all_items[0];
        }
    }

    $shown[] = (int) $selected;
    // Update state and pin for today if needed.
    if ( $per_day ) {
        $today = current_time( 'Y-m-d' );
        $daily = isset( $state['daily'] ) && is_array( $state['daily'] ) ? $state['daily'] : array();
        $daily[ $today ] = (int) $selected;
        // Keep only recent few days to avoid unbounded growth.
        if ( count( $daily ) > 7 ) {
            ksort( $daily );
            $daily = array_slice( $daily, -7, null, true );
        }
        $state['daily'] = $daily;
    }
    $state['shown'] = array_values( array_unique( $shown ) );
    $state['last'] = (int) $selected;
    update_option( $state_key, $state, false );

    return (int) $selected;
}
