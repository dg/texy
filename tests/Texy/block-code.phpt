<?php declare(strict_types=1);

/**
 * Test: parse - Code Blocks.
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


test('code block', function () {
	$texy = new Texy\Texy;
	Assert::match(
		'<pre class="php"><code>echo \'hello\';</code></pre>',
		$texy->process(<<<'XX'
			/--code php
			echo 'hello';
			\--
			XX),
	);
});


test('default block', function () {
	$texy = new Texy\Texy;
	Assert::match(
		'<pre>preformatted</pre>',
		$texy->process(<<<'XX'
			/--
			preformatted
			\--
			XX),
	);
});


test('comment block', function () {
	$texy = new Texy\Texy;
	Assert::same('', trim($texy->process(<<<'XX'
		/--comment
		This is hidden
		\--
		XX)));
});
