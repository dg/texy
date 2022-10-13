<?php

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

if (version_compare(Latte\Engine::VERSION, '3', '>')) {
	Tester\Environment::skip('Test for Latte 2');
}


$texy = new Texy\Texy;

$latte = new Latte\Engine;
$latte->setLoader(new Latte\Loaders\StringLoader);
$macro = new Texy\Bridges\Latte\TexyMacro($latte, $texy);
$macro->install();

$template = <<<'XX'
{texy}
static
-----
{/texy}
XX;


Assert::match(
	<<<'XX'
%A%
		echo '<h1>static</h1>
';
	%A%
XX
	,
	$latte->compile($template)
);


Assert::match(
	'<h1>static</h1>',
	$latte->renderToString($template)
);
