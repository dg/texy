<?php declare(strict_types=1);

/**
 * Test: Modifiers
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


test('disabled classes and styles', function () {
	$texy = new Texy\Texy;
	$texy->htmlOutput->lineWrap = 0;
	$texy->htmlPolicy->allowedClasses = false;
	$texy->htmlPolicy->allowedStyles = false;

	Assert::match(
		'<p>This <strong>text is formatted</strong> with <em>style</em> modifiers.</p>',
		$texy->process('This **text is formatted .{color:red; background: gray;}** with *style .{font-weight: bold}* modifiers.'),
	);

	Assert::match(
		'<p>And this <span>text has defined</span> class and id names.</p>',
		$texy->process('And this "text has defined .[one two #id]" class and id names.'),
	);
});


test('filtered classes and styles', function () {
	$texy = new Texy\Texy;
	$texy->htmlOutput->lineWrap = 0;
	$texy->htmlPolicy->allowedClasses = ['one', '#id'];
	$texy->htmlPolicy->allowedStyles = ['color'];

	Assert::match(
		'<p>This <strong style="color:red">text is formatted</strong> with <em>style</em> modifiers.</p>',
		$texy->process('This **text is formatted .{color:red; background: gray;}** with *style .{font-weight: bold}* modifiers.'),
	);

	Assert::match(
		'<p>And this <span class="one" id="id">text has defined</span> class and id names.</p>',
		$texy->process('And this "text has defined .[one two #id]" class and id names.'),
	);
});


test('attribute vs. style disambiguation', function () {
	$texy = new Texy\Texy;
	$texy->htmlOutput->lineWrap = 0;

	Assert::match(
		'<p role="note" dir="rtl" tabindex="0" style="width:50px">Text</p>',
		$texy->process('Text .{role: note; dir: rtl; tabindex: 0; width: 50px}'),
	);

	Assert::match(
		'<p><strong loading="lazy" spellcheck="false" style="color:red">bold</strong></p>',
		$texy->process('**bold .{loading: lazy; spellcheck: false; color: red}**'),
	);

	// individual transform properties are styles, none of them an attribute
	Assert::match(
		'<p style="translate:10px;rotate:45deg;scale:2">Text</p>',
		$texy->process('Text .{translate: 10px; rotate: 45deg; scale: 2}'),
	);
});
