<?php
/**
 * Snippet: Build valid block markup from PHP.
 *
 * Problem: Patterns, render callbacks and migrations all need to produce block markup, and
 *          hand-writing the HTML comments gets attribute escaping and nesting wrong almost
 *          every time.
 * Trap:    Do not build the comment string yourself. Attributes are JSON inside the delimiter and
 *          must be slashed and sorted exactly the way parse_blocks() would emit them, which is what
 *          serialize_block() already does. Also: a dynamic block has no innerHTML — leave it empty
 *          and let the render callback fill it at display time.
 * Paste:   In a plugin, theme or mu-plugin when generating pattern content.
 * Verify:  parse_blocks() the output and confirm it round-trips: blockName, attrs and innerBlocks
 *          should match what you passed in.
 */

/**
 * Build a single block, given a name, attributes and optional inner blocks.
 *
 * @param string $name        Block name, e.g. 'core/paragraph'.
 * @param array  $attrs       Block attributes.
 * @param array  $inner_blocks Inner block arrays, already built by this function.
 * @param string $inner_html  Static inner HTML. Leave empty for dynamic blocks.
 * @return string Serialized block markup.
 */
function build_block( string $name, array $attrs = array(), array $inner_blocks = array(), string $inner_html = '' ): string {
	$block = array(
		'blockName'   => $name,
		'attrs'       => $attrs,
		'innerBlocks' => $inner_blocks,
		'innerHTML'   => $inner_html,
		'innerContent' => array(),
	);

	// A wrapper with children needs inner content placeholders in the right positions.
	if ( ! empty( $inner_blocks ) ) {
		$block['innerContent'] = array_fill( 0, count( $inner_blocks ) + 1, null );
	} elseif ( '' !== $inner_html ) {
		$block['innerContent'] = array( $inner_html );
	}

	return serialize_block( $block );
}

/*
 * Example:
 *
 * build_block(
 *     'core/group',
 *     array( 'layout' => array( 'type' => 'constrained' ) ),
 *     array(
 *         build_block( 'core/heading', array( 'level' => 2 ), array(), 'A heading' ),
 *         build_block( 'core/paragraph', array(), array(), 'Body copy.' ),
 *     )
 * );
 */
