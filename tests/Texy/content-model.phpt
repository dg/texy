<?php declare(strict_types=1);

/**
 * Test: Content model validation and tag nesting
 */

use Tester\Assert;
use Texy\Texy;

require __DIR__ . '/../bootstrap.php';


// Helper function to process HTML through Texy (typography disabled for clean tests)
function processHtml(string $html, ?array $allowedTags = null): string
{
	$texy = new Texy;
	$texy->htmlOutputModule->indent = false;
	$texy->htmlOutputModule->lineWrap = 0;
	$texy->allowed['typography'] = false;

	if ($allowedTags !== null) {
		$texy->allowedTags = $allowedTags;
	}

	return trim($texy->process($html));
}


// =============================================================================
// TABLE CONTENT MODEL
// =============================================================================

test('table: tr allowed inside table', function () {
	Assert::same(
		'<table><tr><td>cell</td></tr></table>',
		processHtml('<table><tr><td>cell</td></tr></table>'),
	);
});


test('table: td/th only allowed inside tr', function () {
	// td outside tr is rejected (tag and content removed)
	Assert::same(
		'<table></table>',
		processHtml('<table><td>cell</td></table>'),
	);
});


test('table: thead/tbody/tfoot contain tr', function () {
	Assert::same(
		'<table><thead><tr><th>H</th></tr></thead><tbody><tr><td>D</td></tr></tbody></table>',
		processHtml('<table><thead><tr><th>H</th></tr></thead><tbody><tr><td>D</td></tr></tbody></table>'),
	);
});


test('table: caption allowed', function () {
	Assert::same(
		'<table><caption>T</caption><tr><td>D</td></tr></table>',
		processHtml('<table><caption>T</caption><tr><td>D</td></tr></table>'),
	);
});


// =============================================================================
// LIST CONTENT MODEL
// =============================================================================

test('list: li only inside ul/ol', function () {
	Assert::same(
		'<ul><li>A</li><li>B</li></ul>',
		processHtml('<ul><li>A</li><li>B</li></ul>'),
	);
});


test('list: li outside list rejected', function () {
	Assert::same(
		'<div>X</div>',
		processHtml('<div><li>X</li></div>'),
	);
});


test('list: dl with dt and dd', function () {
	Assert::same(
		'<dl><dt>T</dt><dd>D</dd></dl>',
		processHtml('<dl><dt>T</dt><dd>D</dd></dl>'),
	);
});


test('list: nested lists', function () {
	Assert::same(
		'<ul><li>A<ul><li>B</li></ul></li></ul>',
		processHtml('<ul><li>A<ul><li>B</li></ul></li></ul>'),
	);
});


// =============================================================================
// PROHIBITS - NESTED ANCHORS AND FORMS
// =============================================================================

test('prohibits: nested anchors rejected', function () {
	$html = processHtml('<a href="x"><a href="y">L</a></a>');
	Assert::same(1, substr_count($html, '<a '));
});


test('prohibits: nested forms rejected', function () {
	$html = processHtml('<form><form><input></form></form>');
	Assert::same(1, substr_count($html, '<form>'));
});


test('prohibits: button inside anchor rejected', function () {
	Assert::same(
		'<p><a href="#">C</a></p>',
		processHtml('<a href="#"><button>C</button></a>'),
	);
});


// =============================================================================
// PHRASING CONTENT - P, H1-H6, PRE
// =============================================================================

test('phrasing: p cannot contain div', function () {
	$html = processHtml('<p>A<div>B</div>C</p>');
	// p auto-closes before div, trailing text not wrapped
	Assert::same('<p>A</p><div>B</div>C', $html);
});


test('phrasing: p can contain inline elements', function () {
	Assert::same(
		'<p>A <strong>B</strong> <em>C</em></p>',
		processHtml('<p>A <strong>B</strong> <em>C</em></p>'),
	);
});


test('phrasing: nested p closes outer p', function () {
	Assert::same(
		'<p>A</p><p>B</p>',
		processHtml('<p>A<p>B</p></p>'),
	);
});


test('phrasing: heading can contain inline', function () {
	Assert::same(
		'<h1>A <strong>B</strong></h1>',
		processHtml('<h1>A <strong>B</strong></h1>'),
	);
});


// =============================================================================
// TRANSPARENT CONTENT MODEL
// =============================================================================

test('transparent: a can contain div (flow context)', function () {
	Assert::same(
		'<a href="#"><div>X</div></a>',
		processHtml('<a href="#"><div>X</div></a>'),
	);
});


test('transparent: a inside p cannot contain div', function () {
	$html = processHtml('<p><a href="#">A<div>B</div>C</a></p>');
	// a inherits phrasing from p, div not allowed, trailing text not wrapped
	Assert::same('<p><a href="#">A</a></p><div>B</div>C', $html);
});


test('transparent: ins/del can contain div', function () {
	Assert::same(
		'<ins><div>X</div></ins>',
		processHtml('<ins><div>X</div></ins>'),
	);
	Assert::same(
		'<del><div>X</div></del>',
		processHtml('<del><div>X</div></del>'),
	);
});


test('transparent: figure with figcaption', function () {
	Assert::same(
		'<figure><img src="x"><figcaption>C</figcaption></figure>',
		processHtml('<figure><img src="x"><figcaption>C</figcaption></figure>'),
	);
});


test('transparent: fieldset with legend', function () {
	Assert::same(
		'<fieldset><legend>T</legend><input></fieldset>',
		processHtml('<fieldset><legend>T</legend><input></fieldset>'),
	);
});


// =============================================================================
// EMPTY/TEXT-ONLY CONTENT MODEL
// =============================================================================

test('empty: iframe content stripped', function () {
	$html = processHtml('<iframe src="x">F</iframe>');
	Assert::same('<p><iframe src="x"></iframe></p>', $html);
});


test('text-only: textarea content as text', function () {
	$html = processHtml('<textarea><b>X</b></textarea>');
	// content treated as text, tags not processed as HTML
	Assert::contains('<textarea>', $html);
});


// =============================================================================
// UNKNOWN/CUSTOM TAGS - INHERIT FROM PARENT
// =============================================================================

test('unknown: inherits flow content', function () {
	$texy = new Texy;
	$texy->allowedTags = Texy::ALL;
	$texy->htmlOutputModule->indent = false;
	$texy->htmlOutputModule->lineWrap = 0;

	Assert::same(
		'<div><x-a><p>T</p></x-a></div>',
		trim($texy->process('<div><x-a><p>T</p></x-a></div>')),
	);
});


test('unknown: inherits phrasing content', function () {
	$texy = new Texy;
	$texy->allowedTags = Texy::ALL;
	$texy->htmlOutputModule->indent = false;
	$texy->htmlOutputModule->lineWrap = 0;

	Assert::same(
		'<p><x-b><strong>B</strong></x-b></p>',
		trim($texy->process('<p><x-b><strong>B</strong></x-b></p>')),
	);
});


test('unknown: inherits table restrictions', function () {
	$texy = new Texy;
	$texy->allowedTags = Texy::ALL;
	$texy->htmlOutputModule->indent = false;
	$texy->htmlOutputModule->lineWrap = 0;

	// x-c inside tr, only td/th allowed, so p is rejected
	$html = trim($texy->process('<table><tr><x-c><p>T</p></x-c></tr></table>'));
	Assert::contains('<x-c>', $html);
	Assert::notContains('<p>', $html);
});


// =============================================================================
// ALLOWED TAGS CONFIGURATION
// =============================================================================

test('allowedTags: NONE disables all tags', function () {
	$texy = new Texy;
	$texy->allowedTags = Texy::NONE;
	$texy->allowed['typography'] = false;
	$html = trim($texy->process('<strong>Bold</strong>'));
	Assert::notContains('<strong>', $html);
	Assert::contains('Bold', $html);
});


test('allowedTags: selective', function () {
	$texy = new Texy;
	$texy->allowedTags = ['strong' => Texy::ALL, 'em' => Texy::ALL];
	$texy->htmlOutputModule->indent = false;

	$html = trim($texy->process('<strong>A</strong> <b>B</b> <em>C</em>'));
	Assert::contains('<strong>A</strong>', $html);
	Assert::contains('<em>C</em>', $html);
	Assert::notContains('<b>', $html);
});


test('allowedTags: ALL enables everything', function () {
	$texy = new Texy;
	$texy->allowedTags = Texy::ALL;
	$texy->htmlOutputModule->indent = false;
	$texy->htmlOutputModule->lineWrap = 0;

	Assert::same(
		'<x-el>C</x-el>',
		trim($texy->process('<x-el>C</x-el>')),
	);
});


// =============================================================================
// WELL-FORMING - AUTO-CLOSING
// =============================================================================

test('well-forming: unclosed tags', function () {
	$html = processHtml('<div><p>T');
	Assert::contains('</p>', $html);
	Assert::contains('</div>', $html);
});


test('well-forming: misnested inline', function () {
	$html = processHtml('<strong><em>T</strong></em>');
	Assert::same('<p><strong><em>T</em></strong><em></em></p>', $html);
});


test('well-forming: orphan end tags', function () {
	// orphan end tags are ignored, remaining text is output
	Assert::same('T', processHtml('</div>T</p>'));
});


// =============================================================================
// EDGE CASES
// =============================================================================

test('edge: empty element end tag ignored', function () {
	$html = processHtml('<br></br>');
	Assert::contains('<br>', $html);
	Assert::notContains('</br>', $html);
});


test('edge: self-closing syntax', function () {
	$html = processHtml('<br/>T');
	Assert::contains('<br>', $html);
	Assert::contains('T', $html);
});


test('edge: deeply nested', function () {
	Assert::same(
		'<div><div><div><p>D</p></div></div></div>',
		processHtml('<div><div><div><p>D</p></div></div></div>'),
	);
});


// =============================================================================
// TAGS NOT IN DEFAULT ALLOWED TAGS
// =============================================================================

test('head: allowed by default', function () {
	// head is now allowed by default
	$html = processHtml('<head><title>T</title></head>');
	Assert::same('<head><title>T</title></head>', $html);
});


test('not-allowed: custom element escaped', function () {
	$html = processHtml('<x-w>C</x-w>');
	Assert::contains('&lt;x-w&gt;', $html);
});


test('allowed: head when added to allowedTags', function () {
	$texy = new Texy;
	$texy->allowedTags['head'] = Texy::ALL;
	$texy->allowedTags['title'] = Texy::ALL;
	$texy->htmlOutputModule->indent = false;
	$texy->htmlOutputModule->lineWrap = 0;

	Assert::same(
		'<head><title>T</title></head>',
		trim($texy->process('<head><title>T</title></head>')),
	);
});


test('allowed: custom element when added', function () {
	$texy = new Texy;
	$texy->allowedTags['x-w'] = Texy::ALL;
	$texy->htmlOutputModule->indent = false;
	$texy->htmlOutputModule->lineWrap = 0;

	Assert::same(
		'<x-w>C</x-w>',
		trim($texy->process('<x-w>C</x-w>')),
	);
});
