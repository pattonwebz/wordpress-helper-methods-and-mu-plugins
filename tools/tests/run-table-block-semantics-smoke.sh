#!/usr/bin/env bash
# Smoke test for mu-plugins/table-block-semantics.php.
#
# Loads the plugin with stand-ins for the few WordPress functions it touches, then feeds the
# render filter table markup shaped like what the core Table block outputs. Re-run it after a
# WordPress update: if core changes the table markup, this is where it shows up.
#
# Needs PHP with the dom and libxml extensions. No WordPress install required.
set -e
ROOT_DIR="$(cd "$(dirname "$0")/../.." && pwd)"
TMP_DIR="$(mktemp -d)"
trap 'rm -rf "$TMP_DIR"' EXIT

cat > "$TMP_DIR/test.php" <<'PHP'
<?php
define( 'ABSPATH', __DIR__ . '/' );
$GLOBALS['hooks']  = array();
$GLOBALS['styles'] = array();
function add_action( $tag, $fn, $priority = 10, $args = 1 ) { $GLOBALS['hooks'][ $tag ][] = $fn; }
function add_filter( $tag, $fn, $priority = 10, $args = 1 ) { $GLOBALS['hooks'][ $tag ][] = $fn; }
function register_block_style( $block, $args ) { $GLOBALS['styles'][] = array( $block, $args['name'] ); }
function __( $text, $domain = '' ) { return $text; }

require $argv[1];
call_user_func( $GLOBALS['hooks']['init'][0] );

$pass = 0;
$fail = 0;
function check( $name, $ok ) {
	global $pass, $fail;
	if ( $ok ) { $pass++; echo "PASS  $name\n"; } else { $fail++; echo "FAIL  $name\n"; }
}
function render( $html, $class ) {
	$filter = $GLOBALS['hooks']['render_block_core/table'][0];
	$attrs  = '' === $class ? array() : array( 'className' => $class );
	return $filter( $html, array( 'blockName' => 'core/table', 'attrs' => $attrs ) );
}
function xpath( $html ) {
	$doc = new DOMDocument();
	libxml_use_internal_errors( true );
	$doc->loadHTML( '<?xml encoding="utf-8"?>' . $html );
	libxml_clear_errors();
	return new DOMXPath( $doc );
}
function count_q( $html, $query ) { return xpath( $html )->query( $query )->length; }
// Undo the transformation, to prove nothing else changed.
function undo( $html ) {
	$html = str_replace( ' role="presentation"', '', $html );
	$html = preg_replace( '/ scope="(row|col)"/', '', $html );
	return preg_replace_callback( '#<tbody\b.*?</tbody>#is', function ( $m ) {
		return str_replace( array( '<th>', '</th>' ), array( '<td>', '</td>' ), $m[0] );
	}, $html );
}

// A data table, shaped like the core Table block's output.
$data = '<figure class="wp-block-table"><table class="has-fixed-layout"><thead><tr><th>Item</th><th>2026</th><th>2027</th></tr></thead><tbody>'
	. '<tr><td>Alpha limit</td><td>$100</td><td>$110</td></tr>'
	. '<tr><td>Beta threshold</td><td>$50</td><td>$55</td></tr>'
	. '<tr><td>Gamma rate</td><td>0.40%</td><td>0.38%</td></tr>'
	. '</tbody></table></figure>';

// A layout table: a two-column list of links, two identical headers, <em> cells, one empty cell.
$rows = '';
for ( $i = 1; $i <= 4; $i++ ) {
	$rows .= '<tr><td><a href="/forms/f' . $i . '.pdf" target="_blank" rel="noreferrer noopener">Form ' . $i . '</a></td><td><em>Other form ' . $i . '</em></td></tr>';
}
$rows  .= '<tr><td><a href="/forms/last.pdf">Last form</a></td><td></td></tr>';
$layout = '<figure class="wp-block-table"><table class="has-fixed-layout"><thead><tr><th>FORMS</th><th>FORMS</th></tr></thead><tbody>' . $rows . '</tbody></table></figure>';

check( 'both styles registered for core/table', 2 === count( $GLOBALS['styles'] ) && 'core/table' === $GLOBALS['styles'][0][0] && 'row-headers' === $GLOBALS['styles'][0][1] && 'layout-only' === $GLOBALS['styles'][1][1] );

// Row headers.
$out = render( $data, 'is-style-row-headers' );
check( 'row-headers: 3 column headers have scope=col', 3 === count_q( $out, '//thead//th[@scope="col"]' ) );
check( 'row-headers: first cell of every body row is <th scope=row>', 3 === count_q( $out, '//tbody/tr/*[1][self::th and @scope="row"]' ) );
check( 'row-headers: other cells stay <td>', 6 === count_q( $out, '//tbody//td' ) );
check( 'row-headers: row header text preserved', 'Alpha limit' === trim( xpath( $out )->query( '//tbody/tr[1]/th' )->item( 0 )->textContent ) );
check( 'row-headers: nothing else changed', undo( $out ) === $data );
check( 'row-headers: idempotent', render( $out, 'is-style-row-headers' ) === $out );
check( 'no style class: untouched', render( $data, '' ) === $data );
check( 'unrelated style class: untouched', render( $data, 'is-style-stripes' ) === $data );
check( 'class that only contains the name does not match', render( $data, 'not-is-style-row-headers-really' ) === $data );
check( 'style class among other classes still matches', false !== strpos( render( $data, 'my-class is-style-row-headers other' ), 'scope="row"' ) );

// Layout only.
$out = render( $layout, 'is-style-layout-only' );
check( 'layout-only: table gets role=presentation', 1 === count_q( $out, '//table[@role="presentation"]' ) );
check( 'layout-only: role added to the table only', 1 === substr_count( $out, 'role="presentation"' ) );
check( 'layout-only: nothing else changed', undo( $out ) === $layout );
check( 'layout-only: link attributes intact', false !== strpos( $out, '<a href="/forms/f2.pdf" target="_blank" rel="noreferrer noopener">Form 2</a>' ) );
check( 'layout-only: idempotent', render( $out, 'is-style-layout-only' ) === $out );
check( 'layout-only: does not overwrite an existing role', '<figure class="wp-block-table"><table role="grid"><tbody><tr><td>a</td></tr></tbody></table></figure>' === render( '<figure class="wp-block-table"><table role="grid"><tbody><tr><td>a</td></tr></tbody></table></figure>', 'is-style-layout-only' ) );

// Row headers on the layout-shaped table (links, <em>, an empty cell).
$out = render( $layout, 'is-style-row-headers' );
check( 'row-headers on links table: link kept inside the row header', false !== strpos( $out, '<th scope="row"><a href="/forms/f1.pdf" target="_blank" rel="noreferrer noopener">Form 1</a></th>' ) );
check( 'row-headers on links table: empty cell stays an empty <td>', false !== strpos( $out, '<td></td></tr></tbody>' ) );

// Edge cases.
$both = render( $data, 'is-style-row-headers is-style-layout-only' );
check( 'both classes: layout-only wins, no scope added', false !== strpos( $both, 'role="presentation"' ) && 0 === substr_count( $both, 'scope=' ) );
$attrs = '<figure class="wp-block-table"><table><thead><tr><th class="a">H1</th><th scope="col">H2</th></tr></thead><tbody><tr class="r"><td class="c1" colspan="1">x</td><td>y</td></tr></tbody></table></figure>';
$out   = render( $attrs, 'is-style-row-headers' );
check( 'attributes kept; existing scope kept; scope added otherwise', false !== strpos( $out, '<th class="a" scope="col">' ) && false !== strpos( $out, '<th scope="col">H2' ) );
check( 'cell attributes preserved on the new row header', false !== strpos( $out, '<th class="c1" colspan="1" scope="row">x</th>' ) && false !== strpos( $out, '<tr class="r">' ) );
$already = '<figure class="wp-block-table"><table><tbody><tr><th>Already</th><td>v</td></tr><tr><th scope="row">Has scope</th><td>v</td></tr></tbody></table></figure>';
$out     = render( $already, 'is-style-row-headers' );
check( 'existing <th> gets a scope; one that has it is left alone', false !== strpos( $out, '<th scope="row">Already</th>' ) && 1 === substr_count( $out, '<th scope="row">Has scope</th>' ) && 2 === substr_count( $out, 'scope="row"' ) );
$foot = '<figure class="wp-block-table"><table><thead><tr><th>A</th><th>B</th></tr></thead><tbody><tr><td>r1</td><td>1</td></tr></tbody><tfoot><tr><td>Total</td><td>1</td></tr></tfoot></table><figcaption>Cap</figcaption></figure>';
$out  = render( $foot, 'is-style-row-headers' );
check( 'tfoot and figcaption left as they were', false !== strpos( $out, '<tfoot><tr><td>Total</td><td>1</td></tr></tfoot>' ) && false !== strpos( $out, '<figcaption>Cap</figcaption>' ) );
$nohead = '<figure class="wp-block-table"><table><tbody><tr><td>a</td><td>b</td></tr><tr><td>c</td><td>d</td></tr></tbody></table></figure>';
check( 'table with no thead: body rows still get row headers', 2 === count_q( render( $nohead, 'is-style-row-headers' ), '//tbody//th[@scope="row"]' ) );
$out = render( '<figure class="wp-block-table"><TABLE><TBODY><TR><TD>a</TD><TD>b</TD></TR></TBODY></TABLE></figure>', 'is-style-row-headers' );
check( 'upper-case tags are handled', 1 === count_q( $out, '//tbody//th[@scope="row"]' ) && false !== stripos( $out, '</th>' ) );
$broken = '<figure class="wp-block-table"><table><tbody><tr><td>no close</tr></tbody></table></figure>';
check( 'malformed row (no closing </td>) is left alone', $broken === render( $broken, 'is-style-row-headers' ) );
check( 'a non-table string is returned unchanged', 'hello' === render( 'hello', 'is-style-row-headers' ) && 'hello' === render( 'hello', 'is-style-layout-only' ) );
check( 'empty string is returned unchanged', '' === render( '', 'is-style-row-headers' ) );
$cell = '<figure class="wp-block-table"><table><tbody><tr><td><a href="/x">link</a> and <strong>bold</strong> &amp; text</td><td>v</td></tr></tbody></table></figure>';
check( 'inline markup and entities inside the cell are preserved', false !== strpos( render( $cell, 'is-style-row-headers' ), '<th scope="row"><a href="/x">link</a> and <strong>bold</strong> &amp; text</th>' ) );

$quoted = '<figure><table title="a > b"><thead><tr><th title="a > b">H</th><th title=\'c > d\' scope="col">H2</th></tr></thead><tbody><tr title="a > b"><td title=\'c > d\'>x</td><td>y</td></tr></tbody></table></figure>';
$out    = render( $quoted, 'is-style-row-headers' );
check( 'quoted greater-than signs in headers, rows and cells are preserved', false !== strpos( $out, '<th title="a > b" scope="col">H</th>' ) && false !== strpos( $out, '<th title=\'c > d\' scope="col">H2</th>' ) && false !== strpos( $out, '<th title=\'c > d\' scope="row">x</th><td>y</td>' ) );
check( 'quoted greater-than sign in table attribute is preserved', false !== strpos( render( $quoted, 'is-style-layout-only' ), '<table role="presentation" title="a > b">' ) );
$attr_text = '<table title="other role=grid"><thead><tr><th title="other scope=row">H</th></tr></thead><tbody><tr><td title="other scope=col">x</td></tr></tbody></table>';
check( 'attribute-like text inside quoted values is not an attribute', false !== strpos( render( $attr_text, 'is-style-layout-only' ), '<table role="presentation" title="other role=grid">' ) && false !== strpos( render( $attr_text, 'is-style-row-headers' ), '<th title="other scope=row" scope="col">' ) && false !== strpos( render( $attr_text, 'is-style-row-headers' ), '<th title="other scope=col" scope="row">' ) );
$broken = '<table><tbody><tr><td>Label<td>Value</td></tr><tr><td>Label<th>Value</th></tr></tbody></table>';
check( 'rows with another cell before the first closing tag are left alone', $broken === render( $broken, 'is-style-row-headers' ) );
$embedded = '<table><tbody><tr><td><span title="</td>">x</span><!-- <td> --></td><td>y</td></tr></tbody></table>';
check( 'cell-like text in quoted attributes and comments is not a cell tag', false !== strpos( render( $embedded, 'is-style-row-headers' ), '<th scope="row"><span title="</td>">x</span><!-- <td> --></th><td>y</td>' ) );
$embedded = '<table><tbody><tr title="<td>"><!-- <td> --><td>x</td><td>y</td></tr></tbody></table>';
check( 'cell-like text before the first cell is ignored', false !== strpos( render( $embedded, 'is-style-row-headers' ), '<tr title="<td>"><!-- <td> --><th scope="row">x</th><td>y</td>' ) );

$large = '<table><thead><tr><th>Heading</th></tr></thead><tbody>' . str_repeat( '<tr>' . str_repeat( '<td>' . str_repeat( 'x', 65 ) . '</td>', 8 ) . '</tr>', 2048 ) . '</tbody></table>';
$out   = render( $large, 'is-style-row-headers' );
check( 'large tables are transformed or preserved in full', $out === $large || ( false !== strpos( $out, '<th scope="col">Heading</th>' ) && 2048 === substr_count( $out, '<th scope="row">' ) && 16384 === substr_count( $out, str_repeat( 'x', 65 ) ) ) );
$limit = ini_get( 'pcre.backtrack_limit' );
ini_set( 'pcre.backtrack_limit', '30' );
$long_head = '<table><thead><tr><th>' . str_repeat( 'h', 100 ) . '</th></tr></thead><tbody><tr><td>x</td></tr></tbody></table>';
$short_head = '<table><thead><tr><th>H</th></tr></thead></table>';
$long_body = '<table><thead><tr><th>H</th></tr></thead><tbody><tr><td>' . str_repeat( 'b', 500 ) . '</td></tr></tbody></table>';
$long_table = '<table ' . str_repeat( 'data-x="x" ', 100 ) . '><tbody></tbody></table>';
check( 'PCRE failure in header rewrite preserves original HTML', $long_head === render( $long_head, 'is-style-row-headers' ) );
check( 'short header can be rewritten under the same PCRE limit', false !== strpos( render( $short_head, 'is-style-row-headers' ), '<th scope="col">H</th>' ) );
check( 'PCRE failure in body rewrite also rolls back header rewrite', $long_body === render( $long_body, 'is-style-row-headers' ) );
check( 'PCRE failure in layout rewrite preserves original HTML', $long_table === render( $long_table, 'is-style-layout-only' ) );
ini_set( 'pcre.backtrack_limit', '200' );
$bad_header = '<table><thead><tr><th>H</th><th ' . str_repeat( 'a', 50 ) . '"bad>H</th></tr></thead></table>';
$bad_row = '<table><thead><tr><th>H</th></tr></thead><tbody><tr ' . str_repeat( 'a', 50 ) . '"bad><td>x</td></tr></tbody></table>';
check( 'nested header replacement failure rolls back the section', $bad_header === render( $bad_header, 'is-style-row-headers' ) );
check( 'nested row replacement failure rolls back the header', $bad_row === render( $bad_row, 'is-style-row-headers' ) );
ini_set( 'pcre.backtrack_limit', $limit );

echo "\n$pass passed, $fail failed\n";
exit( $fail ? 1 : 0 );
PHP

php -d error_reporting='E_ALL & ~E_DEPRECATED' "$TMP_DIR/test.php" "$ROOT_DIR/mu-plugins/table-block-semantics.php"
