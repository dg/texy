<?php declare(strict_types=1);

/**
 * Test: v3 compatibility for properties that moved or were removed.
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


test('options of a removed module are forwarded to htmlOutput', function () {
	$texy = new Texy\Texy;

	Assert::error(
		function () use ($texy) {
			$texy->figureModule->class = 'photo';
		},
		E_USER_DEPRECATED,
		'Property $texy->figureModule->class is deprecated, use $texy->htmlOutput->figureClass instead.',
	);
	Assert::same('photo', $texy->htmlOutput->figureClass);

	Assert::error(
		function () use ($texy) {
			Assert::same('photo', $texy->figureModule->class);
		},
		E_USER_DEPRECATED,
		'Property $texy->figureModule->class is deprecated, use $texy->htmlOutput->figureClass instead.',
	);
});


test('writing into an array property works through the proxy', function () {
	$texy = new Texy\Texy;

	Assert::error(function () use ($texy) {
		$texy->horizLineModule->classes['-'] = 'my-hr';
	}, E_USER_DEPRECATED, '%a%horizontalRuleClasses%a%');
	Assert::same('my-hr', $texy->htmlOutput->horizontalRuleClasses['-']);

	Assert::error(function () use ($texy) {
		$texy->htmlOutputModule->preserveSpaces[] = 'output';
	}, E_USER_DEPRECATED, '%a%preserveSpaces%a%');
	Assert::contains('output', $texy->htmlOutput->preserveSpaces);
});


test('options of living modules are forwarded too', function () {
	$texy = new Texy\Texy;

	Assert::error(function () use ($texy) {
		$texy->imageModule->root = 'img/';
	}, E_USER_DEPRECATED, '%a%$texy->htmlOutput->imageRoot instead.');
	Assert::same('img/', $texy->htmlOutput->imageRoot);

	Assert::error(function () use ($texy) {
		$texy->linkModule->shorten = false;
	}, E_USER_DEPRECATED, '%a%$texy->htmlOutput->shortenUrls instead.');
	Assert::false($texy->htmlOutput->shortenUrls);

	Assert::error(function () use ($texy) {
		$texy->phraseModule->tags[Texy\Syntax::Strong] = 'b';
	}, E_USER_DEPRECATED, '%a%$texy->htmlOutput->phraseTags instead.');
	Assert::same('b', $texy->htmlOutput->phraseTags[Texy\Syntax::Strong]);

	Assert::error(function () use ($texy) {
		$texy->emoticonModule->class = 'emo';
	}, E_USER_DEPRECATED, '%a%$texy->htmlOutput->emoticonClass instead.');
	Assert::same('emo', $texy->htmlOutput->emoticonClass);
});


test('Texy properties that moved to htmlPolicy/htmlOutput', function () {
	$texy = new Texy\Texy;

	Assert::error(function () use ($texy) {
		$texy->allowedClasses = false;
	}, E_USER_DEPRECATED, 'Property $texy->allowedClasses is deprecated, use $texy->htmlPolicy->allowedClasses instead.');
	Assert::false($texy->htmlPolicy->allowedClasses);

	Assert::error(function () use ($texy) {
		unset($texy->allowedTags['a']);
	}, E_USER_DEPRECATED, '%a%$texy->htmlPolicy->allowedTags instead.');
	Assert::false(isset($texy->htmlPolicy->allowedTags['a']));

	Assert::error(function () use ($texy) {
		$texy->obfuscateEmail = false;
	}, E_USER_DEPRECATED, '%a%$texy->htmlOutput->obfuscateEmail instead.');
	Assert::false($texy->htmlOutput->obfuscateEmail);
});


test('removed properties warn and writes are ignored', function () {
	$texy = new Texy\Texy;

	Assert::error(function () use ($texy) {
		$texy->tableModule->oddClass = 'odd';
	}, E_USER_WARNING, 'Property $texy->tableModule->oddClass has been removed and has no replacement.');

	Assert::error(function () use ($texy) {
		Assert::null($texy->figureModule->widthDelta);
	}, E_USER_WARNING, 'Property $texy->figureModule->widthDelta has been removed and has no replacement.');

	Assert::error(function () use ($texy) {
		$texy->summary = [];
	}, E_USER_WARNING, 'Property $texy->summary has been removed and has no replacement.');
});


test('unknown properties throw', function () {
	$texy = new Texy\Texy;

	Assert::exception(
		fn() => $texy->fooModule,
		LogicException::class,
		'Cannot read an undeclared property $texy->$fooModule.',
	);

	Assert::exception(function () use ($texy) {
		$texy->foo = 1;
	}, LogicException::class, 'Cannot write to an undeclared property $texy->$foo.');

	Assert::exception(
		fn() => @$texy->figureModule->unknown,
		LogicException::class,
		'Cannot read an undeclared property $texy->figureModule->$unknown.',
	);
});
