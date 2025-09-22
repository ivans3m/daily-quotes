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
	add_meta_box( 'dq_item_set', __( 'Daily Set & Position', 'daily-quotes' ), 'dq_item_set_metabox', 'dq_item', 'side', 'default' );
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
	echo '<select name="dq_set_id" id="dq_set_id" class="widefat">';
	echo '<option value="">' . esc_html__( '— Select a Set —', 'daily-quotes' ) . '</option>';
	foreach ( $sets as $set ) {
		printf( '<option value="%1$d" %2$s>%3$s</option>', $set->ID, selected( $selected_set, $set->ID, false ), esc_html( get_the_title( $set ) ) );
	}
	echo '</select>';

	echo '<p style="margin-top:10px;"><strong>' . esc_html__( 'Position in Set', 'daily-quotes' ) . '</strong><br />';
	echo esc_html__( 'Use "Order" field under Page Attributes to arrange.', 'daily-quotes' ) . '</p>';
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

/**
 * Render about page by reading README.md content
 */
function dq_render_about_page() {
	if ( ! current_user_can( 'edit_posts' ) ) { return; }
	
	// Get plugin info
	if ( ! function_exists( 'get_plugin_data' ) ) { require_once ABSPATH . 'wp-admin/includes/plugin.php'; }
	$plugin = get_plugin_data( DQ_PLUGIN_FILE, false, false );
	$version = isset( $plugin['Version'] ) ? $plugin['Version'] : '';
	$author = isset( $plugin['AuthorName'] ) && $plugin['AuthorName'] ? $plugin['AuthorName'] : ( isset( $plugin['Author'] ) ? wp_strip_all_tags( $plugin['Author'] ) : '' );
	
	// Read README.md content
	$readme_path = DQ_PLUGIN_DIR . 'README.md';
	$readme_content = '';
	if ( file_exists( $readme_path ) ) {
		$readme_content = file_get_contents( $readme_path );
	}
	
	// If README doesn't exist, show fallback content
	if ( empty( $readme_content ) ) {
		$readme_content = "# Daily Quotes WordPress Plugin\n\nPlugin documentation not available.";
	}
	
	// Convert Markdown to HTML (basic conversion)
	$html_content = dq_convert_markdown_to_html( $readme_content );
	
	?>
	<div class="wrap">
		<div class="dq-about-page">
			<?php echo $html_content; ?>
			
			<hr style="margin: 30px 0;" />
		</div>
	</div>
	
	<style>
	.dq-about-page h1 { color: #23282d; border-bottom: 1px solid #ddd; padding-bottom: 10px; }
	.dq-about-page h2 { color: #0073aa; margin-top: 25px; }
	.dq-about-page h3 { color: #23282d; }
	.dq-about-page code { background: #fff; padding: 2px 6px; font-family: monospace; }
	.dq-about-page pre { background: #fff; padding: 15px; border-radius: 5px; overflow-x: auto; border: 1px solid #ddd; }
	.dq-about-page pre code { padding: 0; }
	.dq-about-page ul, .dq-about-page ol { margin-left: 0px; }
	.dq-about-page li { margin-bottom: 5px; }
	.dq-about-page blockquote { border-left: 4px solid #0073aa; padding-left: 15px; margin-left: 0; font-style: italic; }
	</style>
	<?php
}

/**
 * Basic Markdown to HTML converter for README content
 */
function dq_convert_markdown_to_html( $markdown ) {
	// Convert headers
	$markdown = preg_replace( '/^### (.*$)/m', '<h3>$1</h3>', $markdown );
	$markdown = preg_replace( '/^## (.*$)/m', '<h2>$1</h2>', $markdown );
	$markdown = preg_replace( '/^# (.*$)/m', '<h1>$1</h1>', $markdown );
	
	// Convert code blocks
	$markdown = preg_replace( '/```(.*?)```/s', '<pre><code>$1</code></pre>', $markdown );
	
	// Convert inline code
	$markdown = preg_replace( '/`([^`]+)`/', '<code>$1</code>', $markdown );
	
	// Convert bold
	$markdown = preg_replace( '/\*\*(.*?)\*\*/', '<strong>$1</strong>', $markdown );
	
	// Convert italic
	$markdown = preg_replace( '/\*(.*?)\*/', '<em>$1</em>', $markdown );
	
	// Convert lists
	$markdown = preg_replace( '/^\- (.*$)/m', '<li>$1</li>', $markdown );
	$markdown = preg_replace( '/^(\d+)\. (.*$)/m', '<li>$2</li>', $markdown );

	//$markdown = preg_replace( '/^\- (.*$)/m', '$1<br />', $markdown );
	//$markdown = preg_replace( '/^(\d+)\. (.*$)/m', '$2<BR />', $markdown );

	
	// Wrap consecutive list items in ul/ol
	//$markdown = preg_replace( '/(<li>.*<\/li>)/s', '<ul>$1</ul>', $markdown );
	
	// Convert line breaks to paragraphs
	$markdown = preg_replace( '/\n\n/', '</p><p>', $markdown );
	$markdown = '<p>' . $markdown . '</p>';
	
	// Clean up empty paragraphs
	$markdown = preg_replace( '/<p><\/p>/', '', $markdown );
	$markdown = preg_replace( '/<p>\s*<\/p>/', '', $markdown );
	
	return $markdown;
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