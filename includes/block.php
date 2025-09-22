<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function dq_register_block() {
	// Editor script with minimal dependencies
    wp_register_script( 'daily-quotes-block', '', array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-editor', 'wp-data', 'wp-core-data' ), '0.2.0', true );
	
	$script = "
(function(wp) {
	const { registerBlockType } = wp.blocks;
	const { PanelBody, SelectControl, ToggleControl } = wp.components;
	const { InspectorControls } = wp.blockEditor || wp.editor;
	const { useSelect } = wp.data;
	
	registerBlockType('daily-quotes/block', {
		title: 'Daily Quote',
		icon: 'editor-quote',
		category: 'widgets',
		
		attributes: {
			setId: {
				type: 'integer',
				default: 0
			},
			randomize: {
				type: 'boolean',
				default: true
			},
			perDay: {
				type: 'boolean',
				default: true
			},
			className: {
				type: 'string',
				default: ''
			}
		},
		
		edit: function(props) {
			const { attributes, setAttributes, isSelected } = props;
			const { setId, randomize, perDay } = attributes;
			
			const sets = useSelect(function(select) {
				return select('core').getEntityRecords('postType', 'dq_set', { per_page: -1 });
			}, []);
			
			const options = [
				{ label: 'Select a Set', value: 0 }
			].concat((sets || []).map(function(set) {
				return { label: set.title.rendered, value: set.id };
			}));
			
			// Set default to /Default set/ if no set is selected
			if (setId === 0 && sets && sets.length > 0) {
				const defaultSet = sets.find(function(set) {
					return set.title.rendered === 'Default set';
				});
				if (defaultSet) {
					setAttributes({ setId: defaultSet.id });
				}
			}
			
			return wp.element.createElement(
				wp.element.Fragment,
				null,
				wp.element.createElement(
					InspectorControls,
					null,
					wp.element.createElement(
						PanelBody,
						{ title: 'Quote Settings', initialOpen: true },
						wp.element.createElement(SelectControl, {
							label: 'Set',
							value: setId,
							options: options,
							onChange: function(val) {
								setAttributes({ setId: parseInt(val, 10) });
							}
						}),
						wp.element.createElement(ToggleControl, {
							label: 'Randomize',
							checked: !!randomize,
							onChange: function(val) {
								setAttributes({ randomize: !!val });
							}
						}),
						wp.element.createElement(ToggleControl, {
							label: 'Pin One Per Day',
							checked: !!perDay,
							onChange: function(val) {
								setAttributes({ perDay: !!val });
							}
						})
					)
				),
				wp.element.createElement('div', {
					className: 'dq-block-editor',
					style: {
						border: '1px dashed #ccc',
						padding: '20px',
						minHeight: '50px',
						backgroundColor: isSelected ? '#eee' : 'transparent'
					}
				}, 
					wp.element.createElement('div', {
						style: { marginBottom: '10px' }
					}, 'Daily Quote Block'),
					wp.element.createElement(SelectControl, {
						label: 'Select Set:',
						value: setId,
						options: options,
						onChange: function(val) {
							setAttributes({ setId: parseInt(val, 10) });
						}
					}),
					wp.element.createElement('div', {
						style: { 
							marginTop: '10px', 
							fontSize: '12px', 
							color: '#666',
							fontStyle: 'italic'
						}
					}, 'Quote will render on the front end.')
				)
			);
		},
		
		save: function() {
			return null;
		}
	});
})(window.wp);
";
	
	wp_add_inline_script( 'daily-quotes-block', $script );
    wp_register_style( 'daily-quotes-block', false );
    $editor_css = '.block-editor .wp-block[data-type="daily-quotes/block"]{border:1px dashed #ccc;padding:10px;min-height:50px;}.block-editor .wp-block[data-type="daily-quotes/block"]:hover{border-color:#999;}';
    wp_add_inline_style( 'daily-quotes-block', $editor_css );

	register_block_type( 'daily-quotes/block', array(
		'api_version' => 2,
		'attributes' => array(
			'setId' => array( 'type' => 'integer', 'default' => 0 ),
			'randomize' => array( 'type' => 'boolean', 'default' => true ),
			'perDay' => array( 'type' => 'boolean', 'default' => true ),
			'className' => array( 'type' => 'string', 'default' => '' ),
		),
		'editor_script' => 'daily-quotes-block',
		'editor_style' => 'daily-quotes-block',
		'render_callback' => function( $attributes ) {
			$set_id = isset( $attributes['setId'] ) ? (int) $attributes['setId'] : 0;
			$randomize = ! empty( $attributes['randomize'] );
			$per_day = ! empty( $attributes['perDay'] );
			$className = isset( $attributes['className'] ) ? sanitize_html_class( $attributes['className'] ) : '';
			
			if ( ! $set_id ) { 
				return ''; 
			}
			
			$item_id = dq_select_next_item( $set_id, $randomize, $per_day );
			if ( ! $item_id ) { 
				return ''; 
			}
			
			$post = get_post( $item_id );
			if ( ! $post ) { 
				return ''; 
			}
			
			$content = apply_filters( 'the_content', $post->post_content );
			
			// Build CSS classes
			$classes = array( 'dq-quote' );
			if ( ! empty( $className ) ) {
				$classes[] = $className;
			}
			
			return '<div class="' . esc_attr( implode( ' ', $classes ) ) . '">' . $content . '</div>';
		},
	) );
}
add_action( 'init', 'dq_register_block' );