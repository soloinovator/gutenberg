<?php
/**
 * Elements styles block support.
 *
 * @package gutenberg
 */

/**
 * Get the elements class names.
 *
 * @param  array  $block         Block object.
 * @return string                The unique class name.
 */
function gutenberg_get_elements_class_name ( $block ) {
	return 'wp-elements-' . md5( serialize( $block ) );
}

/**
 * Update the block content with elements class names.
 *
 * @param  string $block_content Rendered block content.
 * @param  array  $block         Block object.
 * @return string                Filtered block content.
 */
function gutenberg_render_elements_support( $block_content, $block ) {
	if ( ! $block_content ) {
		return $block_content;
	}

	$block_type                    = WP_Block_Type_Registry::get_instance()->get_registered( $block['blockName'] );
	$skip_link_color_serialization = gutenberg_should_skip_block_supports_serialization( $block_type, 'color', 'link' );

	if ( $skip_link_color_serialization ) {
		return $block_content;
	}

	$link_color = null;
	if ( ! empty( $block['attrs'] ) ) {
		$link_color = _wp_array_get( $block['attrs'], array( 'style', 'elements', 'link', 'color', 'text' ), null );
	}

	/*
	* For now we only care about link color.
	* This code in the future when we have a public API
	* should take advantage of WP_Theme_JSON_Gutenberg::compute_style_properties
	* and work for any element and style.
	*/
	if ( null === $link_color ) {
		return $block_content;
	}

	$class_name = gutenberg_get_elements_class_name( $block );

	// Like the layout hook this assumes the hook only applies to blocks with a single wrapper.
	// Retrieve the opening tag of the first HTML element.
	$html_element_matches = array();
	preg_match( '/<[^>]+>/', $block_content, $html_element_matches, PREG_OFFSET_CAPTURE );
	$first_element = $html_element_matches[0][0];
	// If the first HTML element has a class attribute just add the new class
	// as we do on layout and duotone.
	if ( strpos( $first_element, 'class="' ) !== false ) {
		$content = preg_replace(
			'/' . preg_quote( 'class="', '/' ) . '/',
			'class="' . $class_name . ' ',
			$block_content,
			1
		);
	} else {
		// If the first HTML element has no class attribute we should inject the attribute before the attribute at the end.
		$first_element_offset = $html_element_matches[0][1];
		$content              = substr_replace( $block_content, ' class="' . $class_name . '"', $first_element_offset + strlen( $first_element ) - 1, 0 );
	}

	gutenberg_enqueue_block_support_styles( $style );

	return $content;
}

/**
 * Render the elements stylesheet.
 *
 * In the case of nested blocks we want the parent element styles to be rendered before their descendants.
 * This solves the issue of an element (e.g.: link color) being styled in both the parent and a descendant:
 * we want the descendant style to take priority, and this is done by loading it after, in DOM order.
 *
 * @param string|null   $pre_render   The pre-rendered content. Default null.
 * @param array         $parsed_block The block being rendered.
 *
 * @return null
 */
function gutenberg_render_elements_support_footer( $pre_render, $block ) {
	$link_color = null;
	if ( ! empty( $block['attrs'] ) ) {
		$link_color = _wp_array_get( $block['attrs'], array( 'style', 'elements', 'link', 'color', 'text' ), null );
	}

	/*
	* For now we only care about link color.
	* This code in the future when we have a public API
	* should take advantage of WP_Theme_JSON_Gutenberg::compute_style_properties
	* and work for any element and style.
	*/
	if ( null === $link_color ) {
		return null;
	}

	$class_name = gutenberg_get_elements_class_name( $block );

	if ( strpos( $link_color, 'var:preset|color|' ) !== false ) {
		// Get the name from the string and add proper styles.
		$index_to_splice = strrpos( $link_color, '|' ) + 1;
		$link_color_name = substr( $link_color, $index_to_splice );
		$link_color      = "var(--wp--preset--color--$link_color_name)";
	}
	$link_color_declaration = esc_html( safecss_filter_attr( "color: $link_color" ) );

	$style = "<style>.$class_name a{" . $link_color_declaration . ";}</style>\n";

	// Ideally styles should be loaded in the head, but blocks may be parsed
	// after that, so loading in the footer for now.
	// See https://core.trac.wordpress.org/ticket/53494.
	add_action(
		'wp_footer',
		function () use ( $style ) {
			echo $style;
		}
	);
}

// Remove WordPress core filter to avoid rendering duplicate elements stylesheet.
remove_filter( 'render_block', 'wp_render_elements_support', 10, 2 );
add_filter( 'render_block', 'gutenberg_render_elements_support', 10, 2 );
add_filter( 'pre_render_block', 'gutenberg_render_elements_support_footer', 10, 2 );
