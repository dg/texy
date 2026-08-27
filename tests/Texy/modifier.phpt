<?php declare(strict_types=1);

/**
 * Test: Modifiers
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


test('a modifier built in code equals the parsed one', function () {
	Assert::equal(
		Texy\Modifier::parse('[gallery #top]'),
		new Texy\Modifier(id: 'top', classes: ['gallery' => true]),
	);
	Assert::same(['a' => true, 'b' => true], new Texy\Modifier(classes: ['a', 'b'])->classes);
});


test('disabled classes and styles', function () {
	$texy = new Texy\Texy;
	$texy->htmlOutput->lineWrap = 0;
	$texy->htmlPolicy->allowedClasses = false;
	$texy->htmlPolicy->allowedStyles = false;

	Assert::match(
		'<p>This <strong>text is formatted</strong> with <em>style</em> modifiers.</p>',
		$texy->toHtml('This **text is formatted .{color:red; background: gray;}** with *style .{font-weight: bold}* modifiers.'),
	);

	Assert::match(
		'<p>And this <span>text has defined</span> class and id names.</p>',
		$texy->toHtml('And this "text has defined .[one two #id]" class and id names.'),
	);
});


test('filtered classes and styles', function () {
	$texy = new Texy\Texy;
	$texy->htmlOutput->lineWrap = 0;
	$texy->htmlPolicy->allowedClasses = ['one', '#id'];
	$texy->htmlPolicy->allowedStyles = ['color'];

	Assert::match(
		'<p>This <strong style="color:red">text is formatted</strong> with <em>style</em> modifiers.</p>',
		$texy->toHtml('This **text is formatted .{color:red; background: gray;}** with *style .{font-weight: bold}* modifiers.'),
	);

	Assert::match(
		'<p>And this <span class="one" id="id">text has defined</span> class and id names.</p>',
		$texy->toHtml('And this "text has defined .[one two #id]" class and id names.'),
	);
});


test('styles in the whitelist are compared regardless of case, as in CSS', function () {
	$texy = new Texy\Texy;
	$texy->htmlOutput->lineWrap = 0;
	$texy->htmlPolicy->allowedStyles = ['Color'];

	Assert::match(
		'<p><strong style="color:red">text</strong></p>',
		$texy->toHtml('**text .{COLOR: red; background: gray}**'),
	);
});


test('attribute vs. style disambiguation', function () {
	$texy = new Texy\Texy;
	$texy->htmlOutput->lineWrap = 0;

	Assert::match(
		'<p role="note" dir="rtl" tabindex="0" style="width:50px">Text</p>',
		$texy->toHtml('Text .{role: note; dir: rtl; tabindex: 0; width: 50px}'),
	);

	Assert::match(
		'<p><strong loading="lazy" spellcheck="false" style="color:red">bold</strong></p>',
		$texy->toHtml('**bold .{loading: lazy; spellcheck: false; color: red}**'),
	);

	// individual transform properties are styles, none of them an attribute
	Assert::match(
		'<p style="translate:10px;rotate:45deg;scale:2">Text</p>',
		$texy->toHtml('Text .{translate: 10px; rotate: 45deg; scale: 2}'),
	);
});
