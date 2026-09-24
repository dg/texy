<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Compat;

use Texy\{HeadingBalancing, Texy};
use function array_key_exists, is_array, is_string, sprintf;


/**
 * Keeps configuration code written for Texy 3 running, with a deprecation pointing to the new
 * name. A target is [holder, property], [holder, property, key] or [holder, property, convert: method],
 * where the holder is a property of Texy or null for Texy itself; a string says why there is no
 * target. Code extending Texy 3 (handlers, patterns, value objects) is not covered.
 * @internal
 */
final class Texy3
{
	/** properties of Texy */
	private const Properties = [
		'allowed' => [null, 'allowedSyntax'],
		'allowedTags' => ['htmlPolicy', 'allowedTags'],
		'allowedClasses' => ['htmlPolicy', 'allowedClasses'],
		'allowedStyles' => ['htmlPolicy', 'allowedStyles'],
		'obfuscateEmail' => ['htmlOutput', 'obfuscateEmail'],
		'nontextParagraph' => ['htmlOutput', 'imageParagraphTag'],
		'mergeLines' => ['config', 'mergeLines'],
		'alignClasses' => 'use $texy->htmlOutput->textAlignClasses and verticalAlignClasses',
		'urlSchemeFilters' => 'use $texy->urlPolicy',
		'summary' => 'read the document returned by parse()',
		'tabWidth' => 'tabs are plain whitespace now',
		'removeSoftHyphens' => 'soft hyphens are always removed from the source',
	];

	/** modules of Texy and their properties */
	private const Modules = [
		'scriptModule' => [
			'separator' => 'directives are read by their handlers now',
		],
		'paragraphModule' => [
			'mergeLines' => ['config', 'mergeLines'],
		],
		'htmlModule' => [
			'passComment' => ['htmlOutput', 'passHtmlComments'],
		],
		'imageModule' => [
			'root' => ['htmlOutput', 'imageBaseUrl'],
			'fileRoot' => ['htmlOutput', 'imageBasePath'],
			'leftClass' => ['htmlOutput', 'floatClasses', 'left'],
			'rightClass' => ['htmlOutput', 'floatClasses', 'right'],
			'linkedRoot' => 'there is no separate root for linked images',
			'defaultAlt' => 'there is no default alternative text',
		],
		'linkModule' => [
			'root' => ['htmlOutput', 'linkBaseUrl'],
			'forceNoFollow' => ['htmlOutput', 'linkNoFollow'],
			'shorten' => ['htmlOutput', 'shortenUrls'],
			'imageClass' => 'there is no class for linked images',
		],
		'phraseModule' => [
			'tags' => ['htmlOutput', 'phraseTags'],
			'linksAllowed' => ['config', 'phraseLinks'],
		],
		'emoticonModule' => [
			'icons' => ['config', 'emoticons'],
			'class' => ['htmlOutput', 'emoticonClass'],
			'root' => 'emoticons are Unicode characters',
			'fileRoot' => 'emoticons are Unicode characters',
		],
		'blockModule' => [],
		'directiveModule' => [],
		'headingModule' => [
			'top' => ['config', 'headingTop'],
			'generateID' => ['config', 'headingIds'],
			'idPrefix' => ['config', 'headingIdPrefix'],
			'moreMeansHigher' => ['config', 'headingMoreMeansHigher'],
			'balancing' => ['config', 'headingBalancing', 'convert' => 'convertBalancing'],
			'levels' => ['config', 'headingLevels', 'convert' => 'convertLevels'],
		],
		'horizLineModule' => [
			'classes' => ['htmlOutput', 'horizontalRuleClasses'],
		],
		'blockQuoteModule' => [],
		'listModule' => [
			'bullets' => ['config', 'listBullets'],
		],
		'tableModule' => [
			'oddClass' => 'style the rows by CSS',
			'evenClass' => 'style the rows by CSS',
		],
		'figureModule' => [
			'tagName' => ['htmlOutput', 'figureTag'],
			'class' => ['htmlOutput', 'figureClass'],
			'leftClass' => ['htmlOutput', 'floatClasses', 'left'],
			'rightClass' => ['htmlOutput', 'floatClasses', 'right'],
			'widthDelta' => 'the width of a figure is not computed',
			'requireCaption' => 'a figure needs no caption',
		],
		'typographyModule' => [
			'locale' => ['config', 'locale'],
			'locales' => ['config', 'locales', 'convert' => 'convertLocales'],
		],
		'longWordsModule' => [
			'wordLimit' => ['config', 'hyphenationWordLimit'],
		],
		'hyphenationModule' => [
			'wordLimit' => ['config', 'hyphenationWordLimit'],
		],
		'htmlOutputModule' => [
			'indent' => ['htmlOutput', 'indent'],
			'preserveSpaces' => ['htmlOutput', 'preserveSpaces'],
			'baseIndent' => ['htmlOutput', 'baseIndent'],
			'lineWrap' => ['htmlOutput', 'lineWrap'],
		],
	];

	/** results of parsing a module used to hold */
	private const Results = [
		'headingModule' => [
			'title' => 'use $document->getHeadings()[0]?->getTitle()',
			'TOC' => 'use $document->getHeadings()',
		],
	];

	/** operations of modules, method => method of TexyConfig */
	private const Operations = [
		'directiveModule' => ['addDirective' => 'addDirective', 'setFallback' => 'setDirectiveFallback'],
		'blockModule' => ['addType' => 'addBlockType'],
		'imageModule' => ['addDefinition' => 'addImageDefinition'],
		'linkModule' => ['addDefinition' => 'addLinkDefinition'],
	];

	/** renamed methods of Texy */
	private const Methods = [
		'parseLine' => 'parseInline',
		'processLine' => 'toInlineHtml',
		'processTypo' => 'applyTypography',
		'stringToHtml' => 'toHtml',
		'stringToText' => 'toText',
	];

	/** removed methods of Texy, method => what to use instead */
	private const RemovedMethods = [
		'addHandler' => 'use addPass() for the document and htmlOutput->addRenderer() for the output',
		'registerLinePattern' => 'use addInlineSyntax(), the name comes first',
		'registerBlockPattern' => 'use addBlockSyntax(), the name comes first',
		'registerPostLine' => 'typography runs as a pass, use addPass()',
		'protect' => 'return Output\Html\Element or Raw from a renderer',
		'unprotect' => 'return Output\Html\Element or Raw from a renderer',
		'checkURL' => 'use urlPolicy->isLinkAllowed() or isImageAllowed()',
		'getDOM' => 'use parse()',
		'invokeHandlers' => 'the event system was removed',
		'invokeAroundHandlers' => 'the event system was removed',
		'getLinePatterns' => 'it was internal',
		'getBlockPatterns' => 'it was internal',
		'getAllowedProps' => 'it was internal',
	];

	private const PassSwitches = ['typography' => 'typography', 'longwords' => 'hyphenation'];

	/** @var array<string, ModuleProxy> */
	private array $proxies = [];

	/** @var array<string, true> */
	private array $reported = [];


	public function __construct(
		private Texy $texy,
	) {
	}


	/** Moves the switches of passes, which $allowedSyntax does not hold any more, to the configuration. */
	public static function moveSwitches(Texy $texy): void
	{
		foreach (self::PassSwitches as $key => $property) {
			if (array_key_exists($key, $texy->allowedSyntax)) {
				trigger_error("\$texy->allowed['$key'] is deprecated, use \$texy->config->$property instead.", E_USER_DEPRECATED);
				$texy->config->$property = (bool) $texy->allowedSyntax[$key];
				unset($texy->allowedSyntax[$key]);
			}
		}
	}


	public function &getProperty(string $name): mixed
	{
		if (isset(self::Modules[$name])) {
			$proxy = $this->proxies[$name] ??= new ModuleProxy($this, $name);
			return $proxy;
		}

		if (array_key_exists($name, self::Properties)) {
			return $this->read("\$texy->$name", self::Properties[$name]);
		}

		trigger_error('Undefined property: ' . Texy::class . "::\$$name", E_USER_WARNING);
		$value = null;
		return $value;
	}


	public function setProperty(string $name, mixed $value): void
	{
		if (array_key_exists($name, self::Properties)) {
			$this->write("\$texy->$name", self::Properties[$name], $value);
		} else {
			$this->texy->$name = $value;
		}
	}


	public function hasProperty(string $name): bool
	{
		return isset(self::Modules[$name])
			|| (is_array(self::Properties[$name] ?? null) && isset($this->resolve(self::Properties[$name])[0]));
	}


	public function &getModuleProperty(string $module, string $name): mixed
	{
		if (isset(self::Results[$module][$name])) {
			throw new \LogicException(sprintf('$texy->%s->%s is gone, %s.', $module, $name, self::Results[$module][$name]));
		}

		if (!array_key_exists($name, self::Modules[$module])) {
			trigger_error("Undefined property: \$texy->$module->$name", E_USER_WARNING);
			$value = null;
			return $value;
		}

		return $this->read("\$texy->$module->$name", self::Modules[$module][$name]);
	}


	public function setModuleProperty(string $module, string $name, mixed $value): void
	{
		if (array_key_exists($name, self::Modules[$module])) {
			$this->write("\$texy->$module->$name", self::Modules[$module][$name], $value);
		} else {
			trigger_error("Undefined property: \$texy->$module->$name", E_USER_WARNING);
		}
	}


	public function hasModuleProperty(string $module, string $name): bool
	{
		$target = self::Modules[$module][$name] ?? null;
		return is_array($target) && isset($this->resolve($target)[0]);
	}


	/** @param  array<mixed>  $args */
	public function callMethod(string $name, array $args): mixed
	{
		if (isset(self::RemovedMethods[$name])) {
			throw new \LogicException(sprintf('Texy::%s() is gone, %s.', $name, self::RemovedMethods[$name]));
		}

		$method = self::Methods[$name] ?? ($name === 'process'
			? (empty($args[1]) ? 'toHtml' : 'toInlineHtml')
			: null) ?? throw new \Error('Call to undefined method ' . Texy::class . "::$name()");

		$this->report("Texy::$name()", "Texy::$method()");
		return $this->texy->$method($args[0], ...array_slice($args, 2));
	}


	/** @param  array<mixed>  $args */
	public function callModuleMethod(string $module, string $name, array $args): void
	{
		$method = self::Operations[$module][$name] ?? throw new \Error("Call to undefined method \$texy->$module->$name()");

		$this->report("\$texy->$module->$name()", "\$texy->config->$method()");
		$this->texy->config->$method(...$args);
	}


	/** @param  string|array{?string, string, 2?: string|int, convert?: string}  $target */
	private function &read(string $label, string|array $target): mixed
	{
		if (is_string($target)) {
			trigger_error("$label is gone, $target.", E_USER_WARNING);
			$value = null;
			return $value;
		}

		if (isset($target['convert'])) {
			throw new \LogicException("Reading $label is not possible, its values changed. Read " . self::describe($target) . ' instead.');
		}

		$this->report($label, self::describe($target));
		$ref = &$this->resolve($target)[0];
		return $ref;
	}


	/** @param  string|array{?string, string, 2?: string|int, convert?: string}  $target */
	private function write(string $label, string|array $target, mixed $value): void
	{
		if (is_string($target)) {
			trigger_error("$label is gone, $target.", E_USER_WARNING);
			return;
		}

		$this->report($label, self::describe($target));
		$ref = &$this->resolve($target)[0];
		$ref = isset($target['convert']) ? self::{$target['convert']}($value) : $value;
	}


	/**
	 * @param  array{?string, string, 2?: string|int, convert?: string}  $target
	 * @return array{mixed}  a reference to the target as the only item
	 */
	private function resolve(array $target): array
	{
		$holder = $target[0] === null ? $this->texy : $this->texy->{$target[0]};
		$ref = &$holder->{$target[1]};
		if (isset($target[2])) {
			$ref = &$ref[$target[2]];
		}

		return [&$ref];
	}


	/** @param  array{?string, string, 2?: string|int, convert?: string}  $target */
	private static function describe(array $target): string
	{
		return '$texy->' . ($target[0] === null ? '' : $target[0] . '->') . $target[1] . (isset($target[2]) ? "['$target[2]']" : '');
	}


	private function report(string $label, string $replacement): void
	{
		if (!isset($this->reported[$label])) {
			$this->reported[$label] = true;
			trigger_error("$label is deprecated, use $replacement instead.", E_USER_DEPRECATED);
		}
	}


	private static function convertBalancing(mixed $value): HeadingBalancing
	{
		return $value instanceof HeadingBalancing
			? $value
			: ($value === 2 ? HeadingBalancing::Fixed : HeadingBalancing::Dynamic);
	}


	/**
	 * @param  array<string, array<mixed>>  $locales  with quotes as singleQuotes and doubleQuotes pairs
	 * @return array<string, array<mixed>>
	 */
	private static function convertLocales(array $locales): array
	{
		return array_map(
			fn(array $quotes) => isset($quotes['doubleQuotes'], $quotes['singleQuotes'])
				? [$quotes['doubleQuotes'][0], $quotes['doubleQuotes'][1], $quotes['singleQuotes'][0], $quotes['singleQuotes'][1]]
				: $quotes,
			$locales,
		);
	}


	/**
	 * @param  array<string, int>  $levels  counted from 0
	 * @return array<string, int>
	 */
	private static function convertLevels(array $levels): array
	{
		return array_map(fn($level) => $level + 1, $levels);
	}
}
