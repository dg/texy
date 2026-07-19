<?php declare(strict_types=1);

/**
 * Test: TexyExtension
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

if (version_compare(Latte\Engine::VERSION, '3', '<')) {
	Tester\Environment::skip('Test for Latte 3');
}


$texy = new Texy\Texy;

$latte = new Latte\Engine;
$latte->setLoader(new Latte\Loaders\StringLoader);
$latte->addExtension(new Texy\Bridges\Latte\TexyExtension($texy));

$template = <<<'XX'
	{texy}
	static
	-----
	<x n:attr>
	{/texy}


	{texy}
	{='dynamic<>'}
	-----
	<x n:attr>
	{/texy}
	XX;


// Static content is pre-rendered
Assert::match(
	<<<'XX'
		%A%
				echo '<h1>static</h1>

		<p>&lt;x n:attr&gt;</p>


		';
				ob_start(fn() => '') /* %a% */;
				try {
					echo %a%'dynamic<>'%a% /* %a% */;
					echo '
		-----
		<x n:attr>
		';

				} finally {
					$ʟ_tmp = ob_get_clean();
				}
				echo ($this->global->texy)($ʟ_tmp, ...[]);
		%A%
		XX,
	$latte->compile($template),
);


Assert::match(
	<<<'XX'
		<h1>static</h1>

		<p>&lt;x n:attr&gt;</p>


		<h1>dynamic&lt;&gt;</h1>

		<p>&lt;x n:attr&gt;</p>
		XX,
	$latte->renderToString($template),
);


Assert::match(
	'<p>hello {$x}</p>',
	$latte->renderToString('{texy syntax: off} hello {$x} {/texy}'),
);


Assert::match(
	'<p>hello</p>',
	$latte->renderToString('{="hello"|texy}'),
);
