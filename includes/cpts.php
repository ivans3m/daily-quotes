<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function dq_register_cpts() {
	// Register Sets (dq_set)
    register_post_type( 'dq_set', array(
		'labels' => array(
			'name' => __( 'Daily Sets', 'daily-quotes' ),
			'singular_name' => __( 'Daily Set', 'daily-quotes' ),
			'add_new_item' => __( 'Add New Set', 'daily-quotes' ),
			'edit_item' => __( 'Edit Set', 'daily-quotes' ),
		),
		'public' => false,
		'show_ui' => true,
        'show_in_menu' => false,
		'show_in_rest' => true,
		'menu_icon' => 'dashicons-welcome-write-blog',
		'supports' => array( 'title' ),
		'capability_type' => 'page',
		'map_meta_cap' => true,
	) );

	// Register Items (dq_item)
    register_post_type( 'dq_item', array(
		'labels' => array(
			'name' => __( 'Daily Items', 'daily-quotes' ),
			'singular_name' => __( 'Daily Item', 'daily-quotes' ),
			'add_new_item' => __( 'Add New Item', 'daily-quotes' ),
			'edit_item' => __( 'Edit Item', 'daily-quotes' ),
		),
		'public' => false,
		'show_ui' => true,
        'show_in_menu' => false,
		'show_in_rest' => true,
		'menu_icon' => 'dashicons-editor-quote',
		'supports' => array( 'title', 'editor', 'page-attributes' ), // page-attributes provides menu_order for ordering
		'capability_type' => 'post',
		'map_meta_cap' => true,
	) );
}
add_action( 'init', 'dq_register_cpts' );

/**
 * Meta box to assign an Item to a Set and manage ordering help.
 */
function dq_item_add_meta_boxes() {
	add_meta_box( 'dq_item_set', __( 'Select Set', 'daily-quotes' ), 'dq_item_set_metabox', 'dq_item', 'side', 'default' );
}
add_action( 'add_meta_boxes', 'dq_item_add_meta_boxes' );

function dq_item_set_metabox( $post ) {
	// Nonce
	wp_nonce_field( 'dq_item_set_save', 'dq_item_set_nonce' );

	$selected_set = (int) get_post_meta( $post->ID, '_dq_set_id', true );
	$sets = get_posts( array(
		'post_type' => 'dq_set',
		'numberposts' => -1,
		'orderby' => 'title',
		'order' => 'ASC',
		'post_status' => array( 'publish', 'draft' ),
	) );

	echo '<p><label for="dq_set_id"><strong>' . esc_html__( 'Set', 'daily-quotes' ) . '</strong></label></p>';
	echo '<select name="dq_set_id" id="dq_set_id" class="">';
	echo '<option value="" disabled>' . esc_html__( '— Select a Set —', 'daily-quotes' ) . '</option>';
	foreach ( $sets as $set ) {
		printf( '<option value="%1$d" %2$s>%3$s</option>', $set->ID, selected( $selected_set, $set->ID, false ), esc_html( get_the_title( $set ) ) );
	}
	echo '</select>';
}

function dq_item_save_post( $post_id, $post ) {
	if ( $post->post_type !== 'dq_item' ) {
		return;
	}
	if ( ! isset( $_POST['dq_item_set_nonce'] ) || ! wp_verify_nonce( $_POST['dq_item_set_nonce'], 'dq_item_set_save' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

    $set_id = isset( $_POST['dq_set_id'] ) ? (int) $_POST['dq_set_id'] : 0;
    if ( $set_id > 0 ) {
		update_post_meta( $post_id, '_dq_set_id', $set_id );
	} else {
		delete_post_meta( $post_id, '_dq_set_id' );
	}
}
add_action( 'save_post', 'dq_item_save_post', 10, 2 );

/**
 * Default lowest available order on new item creation.
 */
function dq_item_default_order_on_insert( $post_id, $post, $update ) {
	if ( $post->post_type !== 'dq_item' || $update ) { return; }
	// Only set if menu_order is zero.
	if ( (int) $post->menu_order !== 0 ) { return; }
	$set_id = (int) get_post_meta( $post_id, '_dq_set_id', true );
	$max = 0;
	if ( $set_id ) {
		$others = get_posts( array(
			'post_type' => 'dq_item',
			'posts_per_page' => -1,
			'fields' => 'ids',
			'post_status' => array( 'publish', 'draft' ),
			'meta_query' => array(
				array(
					'key' => '_dq_set_id',
					'value' => $set_id,
					'compare' => '=',
				),
			),
		) );
		foreach ( $others as $oid ) {
			$mo = (int) get_post_field( 'menu_order', $oid );
			if ( $mo > $max ) { $max = $mo; }
		}
	}
	wp_update_post( array( 'ID' => $post_id, 'menu_order' => $max + 1 ) );
}
add_action( 'wp_insert_post', 'dq_item_default_order_on_insert', 10, 3 );

/**
 * Admin list table columns for Items: show Set and Order.
 */
function dq_item_columns( $columns ) {
	$columns['dq_shown'] = __( 'Shown', 'daily-quotes' );
	$columns['dq_set'] = __( 'Set', 'daily-quotes' );
	$columns['menu_order'] = __( 'Order', 'daily-quotes' );
	return $columns;
}
add_filter( 'manage_dq_item_posts_columns', 'dq_item_columns' );

function dq_item_custom_column( $column, $post_id ) {
    if ( $column === 'dq_shown' ) {
        $set_id = (int) get_post_meta( $post_id, '_dq_set_id', true );
        if ( ! $set_id ) { echo '—'; return; }
        $state = get_option( 'dq_state_' . $set_id, array( 'shown' => array() ) );
        $shown = isset( $state['shown'] ) && is_array( $state['shown'] ) ? array_map( 'intval', $state['shown'] ) : array();
        $checked = in_array( (int) $post_id, $shown, true ) ? ' checked' : '';
        printf( '<input type="checkbox" class="dq-shown-toggle" data-post="%1$d" data-set="%2$d" %3$s />', (int) $post_id, (int) $set_id, $checked );
        return;
    }
	if ( $column === 'dq_set' ) {
		$set_id = (int) get_post_meta( $post_id, '_dq_set_id', true );
		echo $set_id ? esc_html( get_the_title( $set_id ) ) : '—';
	} elseif ( $column === 'menu_order' ) {
		$post = get_post( $post_id );
		echo isset( $post->menu_order ) ? (int) $post->menu_order : 0;
	}
}
add_action( 'manage_dq_item_posts_custom_column', 'dq_item_custom_column', 10, 2 );

function dq_item_sortable_columns( $columns ) {
	$columns['menu_order'] = 'menu_order';
	return $columns;
}
add_filter( 'manage_edit-dq_item_sortable_columns', 'dq_item_sortable_columns' );

/**
 * Sort admin list by menu_order ascending and add Set filter dropdown.
 */
function dq_items_pre_get_posts( $query ) {
	if ( is_admin() && $query->is_main_query() && $query->get( 'post_type' ) === 'dq_item' ) {
		$query->set( 'orderby', array( 'menu_order' => 'ASC', 'date' => 'ASC' ) );
		$set = isset( $_GET['dq_set_filter'] ) ? (int) $_GET['dq_set_filter'] : 0;
		if ( $set ) {
			$meta = (array) $query->get( 'meta_query' );
			$meta[] = array(
				'key' => '_dq_set_id',
				'value' => $set,
				'compare' => '=',
			);
			$query->set( 'meta_query', $meta );
		}
	}
}
add_action( 'pre_get_posts', 'dq_items_pre_get_posts' );

function dq_items_filters() {
	global $typenow;
	if ( $typenow !== 'dq_item' ) { return; }
	$selected = isset( $_GET['dq_set_filter'] ) ? (int) $_GET['dq_set_filter'] : 0;
	$sets = get_posts( array( 'post_type' => 'dq_set', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
	echo '<select name="dq_set_filter" class="postform">';
	echo '<option value="0">' . esc_html__( 'All Sets', 'daily-quotes' ) . '</option>';
	foreach ( $sets as $set ) {
		printf( '<option value="%1$d" %2$s>%3$s</option>', $set->ID, selected( $selected, $set->ID, false ), esc_html( get_the_title( $set ) ) );
	}
	echo '</select>';
}
add_action( 'restrict_manage_posts', 'dq_items_filters' );

/**
 * Enqueue admin JS/CSS for drag-and-drop ordering on Items list.
 */
function dq_admin_enqueue( $hook ) {
	global $typenow;
	if ( $typenow === 'dq_item' && $hook === 'edit.php' ) {
		wp_enqueue_script( 'jquery-ui-sortable' );
		wp_enqueue_script( 'dq-admin-order', DQ_PLUGIN_URL . 'assets/admin-order.js', array( 'jquery', 'jquery-ui-sortable' ), '0.2.0', true );
        wp_localize_script( 'dq-admin-order', 'DQOrder', array(
			'nonce' => wp_create_nonce( 'dq_order_nonce' ),
			'ajax' => admin_url( 'admin-ajax.php' ),
            'toggleNonce' => wp_create_nonce( 'dq_toggle_shown' )
		) );
	}
}
add_action( 'admin_enqueue_scripts', 'dq_admin_enqueue' );

/**
 * AJAX handler to save new order.
 */
function dq_ajax_save_order() {
	check_ajax_referer( 'dq_order_nonce', 'nonce' );
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( 'forbidden', 403 );
	}
	$ids = isset( $_POST['ids'] ) && is_array( $_POST['ids'] ) ? array_map( 'intval', $_POST['ids'] ) : array();
	if ( empty( $ids ) ) { wp_send_json_success(); }
	$menu_order = 1;
	foreach ( $ids as $id ) {
		wp_update_post( array( 'ID' => $id, 'menu_order' => $menu_order++ ) );
	}
	wp_send_json_success();
}
add_action( 'wp_ajax_dq_save_order', 'dq_ajax_save_order' );

/**
 * AJAX: toggle shown for an item within its set.
 */
function dq_ajax_toggle_shown() {
    check_ajax_referer( 'dq_toggle_shown', 'nonce' );
    if ( ! current_user_can( 'edit_posts' ) ) { wp_send_json_error( 'forbidden', 403 ); }
    $post_id = isset( $_POST['post'] ) ? (int) $_POST['post'] : 0;
    $set_id  = isset( $_POST['set'] ) ? (int) $_POST['set'] : 0;
    $value   = isset( $_POST['value'] ) ? (bool) $_POST['value'] : false;
    if ( ! $post_id || ! $set_id ) { wp_send_json_error( 'bad_request', 400 ); }
    $state_key = 'dq_state_' . $set_id;
    $state = get_option( $state_key, array( 'shown' => array(), 'last' => 0, 'daily' => array() ) );
    $shown = isset( $state['shown'] ) && is_array( $state['shown'] ) ? array_map( 'intval', $state['shown'] ) : array();
    if ( $value ) {
        $shown[] = $post_id;
        $shown = array_values( array_unique( $shown ) );
    } else {
        $shown = array_values( array_diff( $shown, array( $post_id ) ) );
        // Also unpin if it was today
        $today = current_time( 'Y-m-d' );
        if ( isset( $state['daily'][ $today ] ) && (int) $state['daily'][ $today ] === $post_id ) {
            unset( $state['daily'][ $today ] );
        }
        if ( isset( $state['last'] ) && (int) $state['last'] === $post_id ) {
            $state['last'] = 0;
        }
    }
    $state['shown'] = $shown;
    update_option( $state_key, $state, false );
    wp_send_json_success( array( 'shown' => $shown ) );
}
add_action( 'wp_ajax_dq_toggle_shown', 'dq_ajax_toggle_shown' );

/**
 * Add small inline JS to make the rows sortable in Items list table.
 */
function dq_admin_inline_js() {
	global $typenow;
	if ( $typenow !== 'dq_item' ) { return; }
	?>
	<script type="text/javascript">
		jQuery(function($){
			var $tbody = $('#the-list');
			$tbody.sortable({
				items: 'tr.type-dq_item',
				cursor: 'move',
				helpers: 'clone',
				axis: 'y',
				update: function(){
					var ids = [];
					$tbody.find('tr.type-dq_item').each(function(){ ids.push(parseInt($(this).attr('id').replace('post-',''),10)); });
					$.post(ajaxurl, { action: 'dq_save_order', nonce: DQOrder.nonce, ids: ids }, function(){
						// Update the visible Order column numbers after save
						var order = 1;
						$tbody.find('tr.type-dq_item').each(function(){
							$(this).find('td.column-menu_order, td.menu_order').text(order++);
						});
					});
				}
			});
		});
	</script>
	<?php
}
add_action( 'admin_footer-edit.php', 'dq_admin_inline_js' );

/**
 * Admin menu: top-level Dailies menu with Items first and Sets second.
 */
function dq_register_admin_menu() {
	add_menu_page( __( 'Dailies', 'daily-quotes' ), __( 'Dailies', 'daily-quotes' ), 'edit_posts', 'daily-quotes', 'dq_render_about_page', 'dashicons-welcome-write-blog', 26 );
	// Submenu: only two entries, Items first, Sets second
	add_submenu_page( 'daily-quotes', __( 'Daily Items', 'daily-quotes' ), __( 'Daily Items', 'daily-quotes' ), 'edit_posts', 'edit.php?post_type=dq_item' );
	add_submenu_page( 'daily-quotes', __( 'Daily Sets', 'daily-quotes' ), __( 'Daily Sets', 'daily-quotes' ), 'edit_posts', 'edit.php?post_type=dq_set' );
}
add_action( 'admin_menu', 'dq_register_admin_menu' );

function dq_render_about_page() {
	if ( ! current_user_can( 'edit_posts' ) ) { return; }
	if ( ! function_exists( 'get_plugin_data' ) ) { require_once ABSPATH . 'wp-admin/includes/plugin.php'; }
	$plugin = get_plugin_data( DQ_PLUGIN_FILE, false, false );
	$version = isset( $plugin['Version'] ) ? $plugin['Version'] : '';
	$author = isset( $plugin['AuthorName'] ) && $plugin['AuthorName'] ? $plugin['AuthorName'] : ( isset( $plugin['Author'] ) ? wp_strip_all_tags( $plugin['Author'] ) : '' );
	$last_updated = current_time( 'Y-m-d' );
	?>
	<div class="wrap">
		<h1><?php echo esc_html__( 'Dailies — Daily Quotes', 'daily-quotes' ); ?></h1>
		<p><?php echo esc_html__( 'Display non‑repeating daily text or HTML from named sets, via Gutenberg block or shortcode. Rotation avoids repeats until all items are shown; optionally pin one item per day site‑wide.', 'daily-quotes' ); ?></p>

		<h2><?php echo esc_html__( 'How it works', 'daily-quotes' ); ?></h2>
		<ol>
			<li><?php echo esc_html__( 'Create a Set under Daily Sets.', 'daily-quotes' ); ?></li>
			<li><?php echo esc_html__( 'Create Items under Daily Items and assign them to a Set. Use the Order field to arrange; you can drag-and-drop in the Items list.', 'daily-quotes' ); ?></li>
			<li><?php echo esc_html__( 'Insert the Gutenberg block or use the shortcode to display a quote.', 'daily-quotes' ); ?></li>
		</ol>

		<h2><?php echo esc_html__( 'Shortcode', 'daily-quotes' ); ?></h2>
		<pre><code>[daily_quotes set="Your Set Title" randomize="1" per_day="1"]</code></pre>
		<ul>
			<li><code>set</code> — <?php echo esc_html__( 'Set title or ID', 'daily-quotes' ); ?></li>
			<li><code>randomize</code> — <?php echo esc_html__( '1 to pick randomly among remaining; 0 for next in order', 'daily-quotes' ); ?></li>
			<li><code>per_day</code> — <?php echo esc_html__( '1 to pin one item per day site‑wide; 0 to rotate on each render', 'daily-quotes' ); ?></li>
		</ul>

		<h2><?php echo esc_html__( 'Block', 'daily-quotes' ); ?></h2>
		<p><?php echo esc_html__( 'Add the “Daily Quote” block. In the sidebar, choose Set, Randomize, and Pin One Per Day. Optional styles: text/background color, font size/family, padding, margin, text alignment, border (color/width/style/radius), and wide/full alignment.', 'daily-quotes' ); ?></p>

		<h2><?php echo esc_html__( 'Rotation rules', 'daily-quotes' ); ?></h2>
		<ul>
			<li><?php echo esc_html__( 'No repeats until all items in the set are shown, then cycle restarts.', 'daily-quotes' ); ?></li>
			<li><?php echo esc_html__( 'When “Pin One Per Day” is enabled, today’s item is fixed using the site timezone.', 'daily-quotes' ); ?></li>
		</ul>

		<hr />
		<p><strong><?php echo esc_html__( 'Author', 'daily-quotes' ); ?>:</strong> <?php echo esc_html( $author ); ?> &nbsp; | &nbsp; <strong><?php echo esc_html__( 'Version', 'daily-quotes' ); ?>:</strong> <?php echo esc_html( $version ); ?> &nbsp; | &nbsp; <strong><?php echo esc_html__( 'Last updated', 'daily-quotes' ); ?>:</strong> <?php echo esc_html( $last_updated ); ?></p>
	</div>
	<?php
}

/**
 * Ensure Default set exists; return its ID.
 */
function dq_get_default_set_id() {
	$default = get_page_by_title( 'Default set', OBJECT, 'dq_set' );
	if ( $default ) { return (int) $default->ID; }
	$set_id = wp_insert_post( array(
		'post_type' => 'dq_set',
		'post_title' => 'Default set',
		'post_status' => 'publish',
	) );
	return (int) $set_id;
}

/**
 * Auto-assign Default set if none selected on save.
 */
function dq_item_default_set_autoassign( $post_id, $post ) {
	if ( $post->post_type !== 'dq_item' ) { return; }
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return; }
	if ( ! current_user_can( 'edit_post', $post_id ) ) { return; }
	$set_id = (int) get_post_meta( $post_id, '_dq_set_id', true );
	if ( ! $set_id ) {
		$default_set = dq_get_default_set_id();
		if ( $default_set ) {
			update_post_meta( $post_id, '_dq_set_id', (int) $default_set );
		}
	}
}
add_action( 'save_post', 'dq_item_default_set_autoassign', 20, 2 );

/**
 * Add shortcode column to Sets list table.
 */
function dq_set_columns( $columns ) {
	$columns['dq_shortcode'] = __( 'Shortcode', 'daily-quotes' );
	return $columns;
}
add_filter( 'manage_dq_set_posts_columns', 'dq_set_columns' );

function dq_set_custom_column( $column, $post_id ) {
	if ( $column === 'dq_shortcode' ) {
		echo '<code>[daily_quotes set="' . esc_html( get_the_title( $post_id ) ) . '"]</code>';
	}
}
add_action( 'manage_dq_set_posts_custom_column', 'dq_set_custom_column', 10, 2 );
