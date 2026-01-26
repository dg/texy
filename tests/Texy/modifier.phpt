<?php declare(strict_types=1);

/**
 * Test: Modifiers
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


test('disabled classes and styles', function () {
	$texy = new Texy\Texy;
	$texy->htmlOutputModule->lineWrap = 0;
	$texy->allowedClasses = false;
	$texy->allowedStyles = false;

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
	$texy->htmlOutputModule->lineWrap = 0;
	$texy->allowedClasses = ['one', '#id'];
	$texy->allowedStyles = ['color'];

	Assert::match(
		'<p>This <strong style="color:red">text is formatted</strong> with <em>style</em> modifiers.</p>',
		$texy->process('This **text is formatted .{color:red; background: gray;}** with *style .{font-weight: bold}* modifiers.'),
	);

	Assert::match(
		'<p>And this <span class="one" id="id">text has defined</span> class and id names.</p>',
		$texy->process('And this "text has defined .[one two #id]" class and id names.'),
	);
});
