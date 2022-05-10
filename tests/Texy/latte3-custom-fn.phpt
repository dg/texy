<?php

/**
 * Test: TexyExtension
 * @phpVersion 8.0
 */

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

if (version_compare(Latte\Engine::VERSION, '3', '<')) {
	Tester\Environment::skip('Test for Latte 3');
}

$fn = function (string $text, int $heading = 1, string $locale = 'cs'): string {
	$texy = new Texy\Texy;
	$texy->headingModule->top = $heading;
	$texy->typographyModule->locale = $locale;
	return $texy->process($text);
};

$latte = new Latte\Engine;
$latte->setLoader(new Latte\Loaders\StringLoader);
$latte->addExtension(new Texy\Bridges\Latte\TexyExtension($fn));

$template = <<<'XX'
	{texy}
	default
	-----
	{/texy}

	{texy heading: 3}
	"heading"
	-----
	{/texy}

	{texy locale: en, heading: 3}
	"both"
	-----
	{/texy}
	XX;


Assert::match(
	<<<'XX'
		<h1>default</h1>

		<h3>„heading“</h3>

		<h3>“both”</h3>
		XX,
	$latte->renderToString($template),
);
