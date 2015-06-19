<?php

/**
 * Test: Modifiers
 */

require __DIR__ . '/../bootstrap.php';


test(function () {
	$texy = new Texy;
	$texy->allowedClasses = FALSE;
	$texy->allowedStyles = FALSE;

	Assert::match(
		"\n<p>This <strong>text is formatted</strong> with <em>style</em> modifiers.</p>",
		$texy->process('This **text is formatted .{color:red; background: gray;}** with *style .{font-weight: bold}* modifiers.')
	);

	Assert::match(
		"\n<p>And this <span>text has defined</span> class and id names.</p>",
		$texy->process('And this "text has defined .[one two #id]" class and id names.')
	);
});


test(function () {
	$texy = new Texy;
	$texy->htmlOutputModule->lineWrap = 180;
	$texy->allowedClasses = array('one', '#id');
	$texy->allowedStyles = array('color');

	Assert::match(
		"\n" . '<p>This <strong style="color:red">text is formatted</strong> with <em>style</em> modifiers.</p>',
		$texy->process('This **text is formatted .{color:red; background: gray;}** with *style .{font-weight: bold}* modifiers.')
	);

	Assert::match(
		"\n" . '<p>And this <span class="one" id="id">text has defined</span> class and id names.</p>',
		$texy->process('And this "text has defined .[one two #id]" class and id names.')
	);
});
