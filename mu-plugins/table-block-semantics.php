<?php
/**
 * Plugin Name: Table Block Semantics
 * Description: Adds two block styles to the core Table block so editors can give a table proper accessibility semantics: "First column as row headers" and "Layout only (no table semantics)".
 * Version: 1.0.0
 * Author: William Patton
 *
 * The problem
 * -----------
 * The core Table block can mark up column headers but not row headers, so a data table whose
 * first column labels each row ("Net family asset limitation | $105,574 | $109,797") reads as a
 * grid of unrelated cells to a screen reader. The same block is also commonly used as a visual
 * layout (a two-column list of links), where announcing rows, columns and headers is noise or
 * plain wrong. Accessibility audits flag both, and core gives editors no way to fix either.
 *
 * What this does
 * --------------
 * Registers two block styles for core/table, so they appear in the block's Styles panel:
 *
 * - "First column as row headers" (is-style-row-headers): the first cell of every body row
 *   becomes <th scope="row"> and the header cells get scope="col".
 * - "Layout only (no table semantics)" (is-style-layout-only): adds role="presentation" to the
 *   table. Use it only for tables that are purely visual. A real list is still the better fix
 *   when you can restructure the content.
 *
 * The change is made when the block is rendered. The saved block markup is never touched, so
 * existing tables are not invalidated, and removing this plugin just leaves tables as plain
 * tables (the style class stays in the content and does nothing).
 *
 * Why block styles and not a sidebar control
 * ------------------------------------------
 * register_block_style() and the is-style-* class are long-standing public APIs and need no
 * build step. A sidebar toggle needs editor JavaScript that leans on editor internals which
 * change more often. The trade-off: styles are pick-one, so a table cannot use these together
 * with core's "Stripes" style.
 *
 * Using it
 * --------
 * Drop this file into wp-content/mu-plugins/. In the editor, select the table, open the Block
 * tab of the sidebar, open Styles, and pick one. These are database changes: on a site with
 * staging and production, repeat the choice on each table on both.
 *
 * Keeping it working
 * ------------------
 * The rewriting depends on the markup core renders for the table (figure > table >
 * thead/tbody > tr > td). That has been stable for years but is not guaranteed. The code is
 * deliberately defensive: it only edits markup it recognises, never converts a cell twice
 * (running it again changes nothing), and leaves anything unusual as it found it. After a major
 * WordPress update, run tools/tests/run-table-block-semantics-smoke.sh, and rescan a page that
 * uses these styles with an accessibility checker.
 *
 * @package table-block-semantics
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the two Table block styles.
 */
function pw_table_semantics_register_styles() {
	register_block_style(
		'core/table',
		array(
			'name'  => 'row-headers',
			'label' => __( 'First column as row headers', 'table-block-semantics' ),
		)
	);

	register_block_style(
		'core/table',
		array(
			'name'  => 'layout-only',
			'label' => __( 'Layout only (no table semantics)', 'table-block-semantics' ),
		)
	);
}
add_action( 'init', 'pw_table_semantics_register_styles' );

/**
 * Apply the chosen semantics when a Table block is rendered.
 *
 * @param string $block_content The rendered block HTML.
 * @param array  $block         The parsed block, including its attributes.
 * @return string
 */
function pw_table_semantics_render( $block_content, $block ) {
	$class_name = isset( $block['attrs']['className'] ) ? (string) $block['attrs']['className'] : '';
	$classes    = preg_split( '/\s+/', trim( $class_name ) );

	// If both somehow end up set, layout-only wins: a presentational table has no headers to mark.
	if ( in_array( 'is-style-layout-only', $classes, true ) ) {
		return pw_table_semantics_make_presentational( $block_content );
	}

	if ( in_array( 'is-style-row-headers', $classes, true ) ) {
		return pw_table_semantics_add_row_headers( $block_content );
	}

	return $block_content;
}
add_filter( 'render_block_core/table', 'pw_table_semantics_render', 10, 2 );

/**
 * Add role="presentation" to the table, unless it already has a role.
 *
 * @param string $html The rendered block HTML.
 * @return string
 */
function pw_table_semantics_make_presentational( $html ) {
	$result = preg_replace_callback(
		'#<table\b((?:[^>"\']+|"[^"]*"|\'[^\']*\')*)>#i',
		function ( $match ) {
			if ( pw_table_semantics_has_attribute( $match[1], 'role' ) ) {
				return $match[0];
			}
			return '<table role="presentation"' . $match[1] . '>';
		},
		$html,
		1
	);
	return null === $result ? $html : $result;
}

/**
 * Check for an attribute without mistaking text inside another attribute's value for its name.
 *
 * @param string $attributes The attribute string from the opening tag.
 * @param string $name       The attribute name.
 * @return bool
 */
function pw_table_semantics_has_attribute( $attributes, $name ) {
	$result = preg_match_all( '#(?:^|\s)([^\s=/>]+)(?:\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+))?#', $attributes, $matches );
	if ( false === $result ) {
		return true;
	}
	return in_array( strtolower( $name ), array_map( 'strtolower', $matches[1] ), true );
}

/**
 * Return a cell's attribute string with a scope attribute, keeping any scope already there.
 *
 * @param string $attributes The attribute string from the opening tag (may be empty).
 * @param string $scope      The scope to add: 'col' or 'row'.
 * @return string
 */
function pw_table_semantics_with_scope( $attributes, $scope ) {
	if ( pw_table_semantics_has_attribute( $attributes, 'scope' ) ) {
		return $attributes;
	}
	return rtrim( $attributes ) . ' scope="' . $scope . '"';
}

/**
 * Mark column headers with scope="col" and turn each body row's first cell into a row header.
 *
 * @param string $html The rendered block HTML.
 * @return string
 */
function pw_table_semantics_add_row_headers( $html ) {
	$original = $html;
	$failed   = false;

	// Column headers in <thead>.
	$html = preg_replace_callback(
		'#<thead\b.*?</thead>#is',
		function ( $section ) use ( &$failed ) {
			$result = preg_replace_callback(
				'#<th\b((?:[^>"\']+|"[^"]*"|\'[^\']*\')*)>#i',
				function ( $cell ) {
					return '<th' . pw_table_semantics_with_scope( $cell[1], 'col' ) . '>';
				},
				$section[0]
			);
			if ( null === $result ) {
				$failed = true;
				return $section[0];
			}
			return $result;
		},
		$html
	);
	if ( null === $html || $failed ) {
		return $original;
	}

	// First cell of every row in <tbody>. <tfoot> rows are left alone.
	$result = preg_replace_callback(
		'#<tbody\b.*?</tbody>#is',
		function ( $section ) use ( &$failed ) {
			$result = preg_replace_callback( '#<tr\b(?:[^>"\']+|"[^"]*"|\'[^\']*\')*>.*?</tr>#is', 'pw_table_semantics_row_first_cell', $section[0] );
			if ( null === $result ) {
				$failed = true;
				return $section[0];
			}
			return $result;
		},
		$html
	);
	return null === $result || $failed ? $original : $result;
}

/**
 * Turn a table row's first cell into <th scope="row">.
 *
 * @param array $row_match The regex match for one <tr>...</tr>.
 * @return string
 */
function pw_table_semantics_row_first_cell( $row_match ) {
	$row = $row_match[0];

	if ( ! preg_match( '#<!--.*?-->(*SKIP)(*F)|<(td|th)\b((?:[^>"\']+|"[^"]*"|\'[^\']*\')*)>|<(?:[^>"\']+|"[^"]*"|\'[^\']*\')*>(*SKIP)(*F)#is', $row, $first, PREG_OFFSET_CAPTURE ) ) {
		return $row;
	}

	$tag    = strtolower( $first[1][0] );
	$start  = $first[0][1];
	$length = strlen( $first[0][0] );
	$open   = '<th' . pw_table_semantics_with_scope( $first[2][0], 'row' ) . '>';

	// Find the next cell tag, ignoring tags and comments inside the cell.
	$after  = substr( $row, $start + $length );
	$offset = 0;
	while ( preg_match( '#<!--.*?-->|<(/?)([a-z][a-z0-9:-]*)\b(?:[^>"\']+|"[^"]*"|\'[^\']*\')*>#is', $after, $next, PREG_OFFSET_CAPTURE, $offset ) ) {
		$offset = $next[0][1] + strlen( $next[0][0] );
		if ( isset( $next[2] ) && in_array( strtolower( $next[2][0] ), array( 'td', 'th' ), true ) ) {
			if ( '/' !== $next[1][0] || $tag !== strtolower( $next[2][0] ) ) {
				return $row;
			}
			if ( 'th' === $tag ) {
				return substr( $row, 0, $start ) . $open . $after;
			}
			return substr( $row, 0, $start ) . $open . substr( $after, 0, $next[0][1] ) . '</th>' . substr( $after, $offset );
		}
	}

	return $row;
}
