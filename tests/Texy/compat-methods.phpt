<?php declare(strict_types=1);

/**
 * Test: v3 methods are served by the __call bridge.
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


test('removed methods throw with a pointer to the successor', function () {
	$texy = new Texy\Texy;

	Assert::exception(
		fn() => $texy->addHandler('image', fn() => null),
		LogicException::class,
		'Method Texy::addHandler() has been removed; %a%addPass()%a%addRenderer()%a%',
	);

	Assert::exception(
		fn() => $texy->registerLinePattern(fn() => null, '~x~', 'name'),
		LogicException::class,
		'Method Texy::registerLinePattern() has been removed; use $texy->addLineSyntax() %a%',
	);

	Assert::exception(
		fn() => $texy->protect('<b>x</b>', 'block'),
		LogicException::class,
		'%a%Texy\Output\Html\Element or Raw%a%',
	);

	Assert::exception(
		fn() => $texy->checkURL('http://example.com', 'a'),
		LogicException::class,
		'%a%urlPolicy->isLinkAllowed()%a%',
	);

	Assert::exception(
		fn() => $texy->getDOM(),
		LogicException::class,
		'%a%use parse(), which returns the document AST%a%',
	);
});


test('unknown methods report a plain undefined-method error', function () {
	$texy = new Texy\Texy;

	Assert::exception(
		fn() => $texy->frobnicate(),
		LogicException::class,
		'Call to undefined method Texy\Texy::frobnicate().',
	);
});
