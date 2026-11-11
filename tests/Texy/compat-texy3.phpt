<?php declare(strict_types=1);

/**
 * Test: configuration code written for Texy 3 keeps running.
 */

use Tester\Assert;
use Texy\Compat\Texy3;
use Texy\{HeadingBalancing, Syntax, Texy};
use Texy\Nodes\DocumentNode;

require __DIR__ . '/../bootstrap.php';


/** Public names of the last release of Texy 3, as v3.2.10 declares them. */
const Texy3Properties = [
	'allowed', 'allowedTags', 'allowedClasses', 'allowedStyles', 'tabWidth', 'obfuscateEmail', 'urlSchemeFilters',
	'mergeLines', 'summary', 'alignClasses', 'removeSoftHyphens', 'nontextParagraph',
];
const Texy3Modules = [
	'scriptModule' => ['separator'],
	'paragraphModule' => [],
	'htmlModule' => ['passComment'],
	'imageModule' => ['root', 'linkedRoot', 'fileRoot', 'leftClass', 'rightClass', 'defaultAlt'],
	'linkModule' => ['root', 'imageClass', 'forceNoFollow', 'shorten'],
	'phraseModule' => ['tags', 'linksAllowed'],
	'emoticonModule' => ['icons', 'class', 'root', 'fileRoot'],
	'blockModule' => [],
	'headingModule' => ['generateID', 'idPrefix', 'top', 'moreMeansHigher', 'balancing', 'levels'],
	'horizLineModule' => ['classes'],
	'blockQuoteModule' => [],
	'listModule' => ['bullets'],
	'tableModule' => ['oddClass', 'evenClass'],
	'figureModule' => ['tagName', 'class', 'leftClass', 'rightClass', 'widthDelta', 'requireCaption'],
	'typographyModule' => ['locale'],
	'longWordsModule' => ['wordLimit'],
	'htmlOutputModule' => ['indent', 'preserveSpaces', 'baseIndent', 'lineWrap'],
];


function capture(Closure $function): array
{
	$errors = [];
	set_error_handler(function (int $severity, string $message) use (&$errors) {
		$errors[] = [$severity, $message];
		return true;
	});
	try {
		$function();
	} finally {
		restore_error_handler();
	}

	return $errors;
}


test('every public name of Texy 3 is known, its target exists', function () {
	$texy = new Texy;
	$map = (new ReflectionClass(Texy3::class));
	$properties = $map->getConstant('Properties');
	$modules = $map->getConstant('Modules');

	foreach (Texy3Properties as $name) {
		Assert::true(array_key_exists($name, $properties), $name);
		if (is_array($properties[$name])) {
			$holder = $properties[$name][0] === null ? $texy : $texy->{$properties[$name][0]};
			Assert::true(property_exists($holder, $properties[$name][1]), $name);
		}
	}

	foreach (Texy3Modules as $module => $names) {
		Assert::true(array_key_exists($module, $modules), $module);
		foreach ($names as $name) {
			Assert::true(array_key_exists($name, $modules[$module]), "$module->$name");
			if (is_array($modules[$module][$name])) {
				Assert::true(property_exists($texy->{$modules[$module][$name][0]}, $modules[$module][$name][1]), "$module->$name");
			}
		}
	}
});


test('a property of Texy goes to its new place, by value and by element', function () {
	$texy = new Texy;
	$errors = capture(function () use ($texy) {
		$texy->allowedTags = ['b' => []];
		$texy->allowedTags['i'] = [];
		$texy->mergeLines = false;
	});

	Assert::same(['b' => [], 'i' => []], $texy->htmlPolicy->allowedTags);
	Assert::false($texy->config->mergeLines);
	Assert::same([
		[E_USER_DEPRECATED, '$texy->allowedTags is deprecated, use $texy->htmlPolicy->allowedTags instead.'],
		[E_USER_DEPRECATED, '$texy->mergeLines is deprecated, use $texy->config->mergeLines instead.'],
	], $errors);
});


test('a property of a module goes to its new place', function () {
	$texy = new Texy;
	$errors = capture(function () use ($texy) {
		$texy->headingModule->top = 2;
		$texy->typographyModule->locale = 'en';
		$texy->figureModule->leftClass = 'left';
		$texy->imageModule->root = '/img/';
		$texy->htmlOutputModule->lineWrap = 0;
		$texy->phraseModule->tags['phrase/em'] = 'i';
		$texy->emoticonModule->icons += [':x' => 'x'];
	});

	Assert::same(2, $texy->config->headingTop);
	Assert::same('en', $texy->config->locale);
	Assert::same('left', $texy->htmlOutput->floatClasses['left']);
	Assert::same('/img/', $texy->htmlOutput->imageBaseUrl);
	Assert::same(0, $texy->htmlOutput->lineWrap);
	Assert::same('i', $texy->htmlOutput->phraseTags['phrase/em']);
	Assert::same('x', $texy->config->emoticons[':x']);
	Assert::count(7, $errors);
});


test('a deprecation is reported once per name and instance', function () {
	$texy = new Texy;
	Assert::count(1, capture(function () use ($texy) {
		$texy->headingModule->top = 2;
		$texy->headingModule->top = 3;
		$x = $texy->headingModule->top;
	}));
	Assert::count(1, capture(function () {
		$other = new Texy;
		$other->headingModule->top = 2;
	}));
});


test('a value that changed its meaning is converted when written, reading it is refused', function () {
	$texy = new Texy;
	capture(function () use ($texy) {
		$texy->headingModule->balancing = 2;
		$texy->headingModule->levels = ['#' => 0, '*' => 1, '=' => 2, '-' => 3];
	});

	Assert::same(HeadingBalancing::Fixed, $texy->config->headingBalancing);
	Assert::same(['#' => 1, '*' => 2, '=' => 3, '-' => 4], $texy->config->headingLevels);

	capture(fn() => $texy->headingModule->balancing = HeadingBalancing::Dynamic);
	Assert::same(HeadingBalancing::Dynamic, $texy->config->headingBalancing);

	Assert::exception(
		fn() => $texy->headingModule->levels,
		LogicException::class,
		'Reading $texy->headingModule->levels is not possible, its values changed. Read $texy->config->headingLevels instead.',
	);
});


test('the quote table of Texy 3 is converted to lists of four', function () {
	$texy = new Texy;
	capture(fn() => $texy->typographyModule->locales = ['xx' => ['singleQuotes' => ['<', '>'], 'doubleQuotes' => ['[', ']']]]);
	Assert::same(['xx' => ['[', ']', '<', '>']], $texy->config->locales);
});


test('what has no replacement warns and is dropped', function () {
	$texy = new Texy;
	Assert::same(
		[[E_USER_WARNING, '$texy->tableModule->oddClass is gone, style the rows by CSS.']],
		capture(fn() => $texy->tableModule->oddClass = 'x'),
	);
	Assert::same(
		[[E_USER_WARNING, '$texy->tabWidth is gone, tabs are plain whitespace now.']],
		capture(fn() => $texy->tabWidth = 4),
	);
	Assert::false(isset($texy->tabWidth));
});


test('reading a result of parsing throws with guidance', function () {
	Assert::exception(
		fn() => (new Texy)->headingModule->title,
		LogicException::class,
		'$texy->headingModule->title is gone, use $document->getHeadings()[0]?->getTitle().',
	);
	Assert::exception(
		fn() => (new Texy)->headingModule->TOC,
		LogicException::class,
		'$texy->headingModule->TOC is gone, use $document->getHeadings().',
	);
});


test('renamed methods are emulated', function () {
	$texy = new Texy;
	$result = [];
	$errors = capture(function () use ($texy, &$result) {
		$result[] = $texy->process('a');
		$result[] = $texy->process('a');
		$result[] = $texy->process('**a**', true);
		$result[] = $texy->processLine('**a**');
		$result[] = $texy->processTypo('a...');
		$result[] = $texy->parseLine('a')::class;
	});

	Assert::same("<p>a</p>\n", $result[0]);
	Assert::same('<strong>a</strong>', $result[2]);
	Assert::same('<strong>a</strong>', $result[3]);
	Assert::same("a\u{2026}", $result[4]);
	Assert::same(DocumentNode::class, $result[5]);
	Assert::same([E_USER_DEPRECATED, 'Texy::process() is deprecated, use Texy::toHtml() instead.'], $errors[0]);
	Assert::count(4, $errors);
});


test('operations of modules go to the configuration', function () {
	$texy = new Texy;
	capture(function () use ($texy) {
		$texy->linkModule->addDefinition('home', 'https://example.com');
		$texy->imageModule->addDefinition('logo', 'logo.png');
		$texy->directiveModule->addDirective('x', fn() => null);
		$texy->directiveModule->setFallback(fn() => null);
		$texy->blockModule->addType('x', fn() => null);
	});

	Assert::contains('https://example.com', $texy->toHtml('"a":[home]'));
	Assert::contains('logo.png', $texy->toHtml('[* logo *]'));
	Assert::same(['x'], array_keys($texy->config->blockTypes));
});


test('removed methods throw with guidance', function () {
	Assert::exception(
		fn() => (new Texy)->addHandler('a', 'b'),
		LogicException::class,
		'Texy::addHandler() is gone, use addPass() for the document and htmlOutput->addRenderer() for the output.',
	);
	Assert::exception(
		fn() => (new Texy)->checkURL('a'),
		LogicException::class,
		'Texy::checkURL() is gone, use urlPolicy->isLinkAllowed() or isImageAllowed().',
	);
});


test('switches of passes in $allowed move to the configuration once', function () {
	$texy = new Texy;
	$errors = capture(function () use ($texy) {
		$texy->allowed['typography'] = false;
		$texy->allowed[Syntax::Image] = false;
		$texy->toHtml('a');
		$texy->config->typography = true;
		$texy->toHtml('a');
	});

	Assert::true($texy->config->typography);
	Assert::false($texy->allowedSyntax[Syntax::Image]);
	Assert::false(isset($texy->allowedSyntax['typography']));
	Assert::same([
		[E_USER_DEPRECATED, '$texy->allowed is deprecated, use $texy->allowedSyntax instead.'],
		[E_USER_DEPRECATED, "\$texy->allowed['typography'] is deprecated, use \$texy->config->typography instead."],
	], $errors);
});


test('phrase tags keyed by syntax move to the keys of what the phrase means', function () {
	$texy = new Texy;
	$errors = capture(function () use ($texy) {
		$texy->phraseModule->tags[Syntax::EmphasisSingleAsterisk] = 'i';
		$texy->htmlOutput->phraseTags[Syntax::QuickLink] = 'a';
		Assert::same('<p><i>a</i></p>', trim($texy->toHtml('*a*')));
	});

	Assert::same('i', $texy->htmlOutput->phraseTags['emphasis']);
	Assert::false(isset($texy->htmlOutput->phraseTags[Syntax::EmphasisSingleAsterisk]));
	Assert::false(isset($texy->htmlOutput->phraseTags[Syntax::QuickLink]));
	Assert::same([
		[E_USER_DEPRECATED, '$texy->phraseModule->tags is deprecated, use $texy->htmlOutput->phraseTags instead.'],
		[E_USER_DEPRECATED, "phraseTags['phrase/em-alt'] is deprecated, use phraseTags['emphasis'] instead."],
		[E_USER_DEPRECATED, "phraseTags['phrase/quicklink'] is deprecated, it has no effect."],
	], $errors);

	// set after parsing, before rendering
	$document = $texy->transform($texy->parse('//a//'));
	$errors = capture(function () use ($texy, $document) {
		$texy->htmlOutput->phraseTags[Syntax::Emphasis] = 'cite';
		Assert::same('<p><cite>a</cite></p>', trim($texy->renderHtml($document)));
	});
	Assert::count(1, $errors);
});


test('an unknown name behaves as without the layer', function () {
	$texy = new Texy;
	Assert::error(fn() => $texy->unknown, E_USER_WARNING, 'Undefined property: Texy\Texy::$unknown');
	Assert::error(fn() => $texy->unknown = 1, E_DEPRECATED, 'Creation of dynamic property Texy\Texy::$unknown is deprecated');
	Assert::exception(fn() => $texy->unknownMethod(), Error::class, 'Call to undefined method Texy\Texy::unknownMethod()');
	Assert::false(isset($texy->foo));
});


test('the global class of Texy 3 works and is deprecated', function () {
	$errors = capture(fn() => new class extends \Texy {
	});
	Assert::same([[E_USER_DEPRECATED, 'Class Texy is deprecated, use Texy\Texy instead.']], $errors);
	Assert::same("<p>a</p>\n", (@new \Texy)->toHtml('a')); // @ the deprecation was checked above
});
