<?php

/**
 * This file is part of the Texy! (https://texy.info)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy;


/**
 * Texy! - Convert plain text to HTML format using {@link process()}.
 *
 * <code>
 * $texy = new Texy();
 * $html = $texy->process($text);
 * </code>
 */
class Texy
{
	use Strict;

	// Texy version
	public const VERSION = '3.1.2';

	// configuration directives
	public const
		ALL = true,
		NONE = false;

	// types of protection marks
	public const
		CONTENT_MARKUP = "\x17",
		CONTENT_REPLACED = "\x16",
		CONTENT_TEXTUAL = "\x15",
		CONTENT_BLOCK = "\x14";

	// url filters
	public const
		FILTER_ANCHOR = 'anchor',
		FILTER_IMAGE = 'image';

	/** @deprecated  */
	public const
		HTML4_TRANSITIONAL = 0,
		HTML4_STRICT = 1,
		HTML5 = 4,
		XHTML1_TRANSITIONAL = 2,
		XHTML1_STRICT = 3,
		XHTML5 = 6,
		XML = 2;

	/** @var array<string, bool>  Texy! syntax configuration */
	public $allowed = [];

	/** @var bool|array<string, bool|array<int, string>>  Allowed HTML tags */
	public $allowedTags;

	/** @var bool|array<int, string>  Allowed classes */
	public $allowedClasses = self::ALL; // all classes and id are allowed

	/** @var bool|array<int, string>  Allowed inline CSS style */
	public $allowedStyles = self::ALL;  // all inline styles are allowed

	/** @var int  TAB width (for converting tabs to spaces) */
	public $tabWidth = 8;

	/** @var bool  Do obfuscate e-mail addresses? */
	public $obfuscateEmail = true;

	/** @var array<string|string>  regexps to check URL schemes */
	public $urlSchemeFilters; // disable URL scheme filter

	/** @var bool  Paragraph merging mode */
	public $mergeLines = true;

	/** @var array<string, string[]>  Parsing summary */
	public $summary = [
		'images' => [],
		'links' => [],
	];

	/** @var array<string, ?string>  CSS classes for align modifiers */
	public $alignClasses = [
		'left' => null,
		'right' => null,
		'center' => null,
		'justify' => null,
		'top' => null,
		'middle' => null,
		'bottom' => null,
	];

	/** @var bool  remove soft hyphens (SHY)? */
	public $removeSoftHyphens = true;

	/** @var string|HtmlElement */
	public $nontextParagraph = 'div';

	/** @var Modules\ScriptModule */
	public $scriptModule;

	/** @var Modules\ParagraphModule */
	public $paragraphModule;

	/** @var Modules\HtmlModule */
	public $htmlModule;

	/** @var Modules\ImageModule */
	public $imageModule;

	/** @var Modules\LinkModule */
	public $linkModule;

	/** @var Modules\PhraseModule */
	public $phraseModule;

	/** @var Modules\EmoticonModule */
	public $emoticonModule;

	/** @var Modules\BlockModule */
	public $blockModule;

	/** @var Modules\HeadingModule */
	public $headingModule;

	/** @var Modules\HorizLineModule */
	public $horizLineModule;

	/** @var Modules\BlockQuoteModule */
	public $blockQuoteModule;

	/** @var Modules\ListModule */
	public $listModule;

	/** @var Modules\TableModule */
	public $tableModule;

	/** @var Modules\FigureModule */
	public $figureModule;

	/** @var Modules\TypographyModule */
	public $typographyModule;

	/** @var Modules\LongWordsModule */
	public $longWordsModule;

	/** @var Modules\HtmlOutputModule */
	public $htmlOutputModule;


	/**
	 * Registered regexps and associated handlers for inline parsing.
	 * @var array<string, array{handler: callable, pattern: string, again: ?string}>
	 */
	private $linePatterns = [];

	/** @var array<string, array{handler: callable, pattern: string, again: ?string}> */
	private $_linePatterns;

	/**
	 * Registered regexps and associated handlers for block parsing.
	 * @var array<string, array{handler: callable, pattern: string}>
	 */
	private $blockPatterns = [];

	/** @var array<string, array{handler: callable, pattern: string}> */
	private $_blockPatterns;

	/** @var array<string, callable> */
	private $postHandlers = [];

	/** @var HtmlElement|null  DOM structure for parsed text */
	private $DOM;

	/** @var array  Texy protect markup table */
	private $marks = [];

	/** @var bool|array  for internal usage */
	private $_classes;

	/** @var bool|array  for internal usage */
	private $_styles;

	/** @var bool */
	private $processing = false;

	/** @var array<string, array<int, callable>> of events and registered handlers */
	private $handlers = [];

	/**
	 * DTD descriptor.
	 *   $dtd[element][0] - allowed attributes (as array keys)
	 *   $dtd[element][1] - allowed content for an element (content model) (as array keys)
	 *                    - array of allowed elements (as keys)
	 *                    - false - empty element
	 *                    - 0 - transparent
	 * @var array<string, array{array<string, int>, array<string, int>}>
	 */
	private static $dtd;


	public function __construct()
	{
		if (extension_loaded('mbstring') && mb_get_info('func_overload') & 2 && substr(mb_get_info('internal_encoding'), 0, 1) === 'U') {
			mb_internal_encoding('pass');
			trigger_error("Texy: mb_internal_encoding changed to 'pass'", E_USER_WARNING);
		}

		$this->loadModules();

		$this->initDTD();

		// examples of link references ;-)
		$link = new Link('https://texy.info/');
		$link->modifier->title = 'The best text -> HTML converter and formatter';
		$link->label = 'Texy!';
		$this->linkModule->addReference('texy', $link);

		$link = new Link('https://www.google.com/search?q=%s');
		$this->linkModule->addReference('google', $link);

		$link = new Link('https://en.wikipedia.org/wiki/Special:Search?search=%s');
		$this->linkModule->addReference('wikipedia', $link);
	}


	private function initDTD(): void
	{
		if (!self::$dtd) {
			self::$dtd = require __DIR__ . '/DTD.php';
		}

		// accept all valid HTML tags and attributes by default
		$this->allowedTags = [];
		foreach (self::$dtd as $tag => $dtd) {
			$this->allowedTags[$tag] = self::ALL;
		}
	}


	/** @deprecated */
	public function setOutputMode(int $mode): void
	{
		trigger_error('Texy::setOutputMode() is deprecated, only HTML5 mode is supported.', E_USER_DEPRECATED);
	}


	/** @deprecated */
	public function getOutputMode(): int
	{
		trigger_error('Texy::getOutputMode() is deprecated, only HTML5 mode is supported.', E_USER_DEPRECATED);
		return self::HTML5;
	}


	/**
	 * Create array of all used modules ($this->modules).
	 * This array can be changed by overriding this method (by subclasses)
	 */
	protected function loadModules(): void
	{
		// line parsing
		$this->scriptModule = new Modules\ScriptModule($this);
		$this->htmlModule = new Modules\HtmlModule($this);
		$this->imageModule = new Modules\ImageModule($this);
		$this->phraseModule = new Modules\PhraseModule($this);
		$this->linkModule = new Modules\LinkModule($this);
		$this->emoticonModule = new Modules\EmoticonModule($this);

		// block parsing
		$this->paragraphModule = new Modules\ParagraphModule($this);
		$this->blockModule = new Modules\BlockModule($this);
		$this->figureModule = new Modules\FigureModule($this);
		$this->horizLineModule = new Modules\HorizLineModule($this);
		$this->blockQuoteModule = new Modules\BlockQuoteModule($this);
		$this->tableModule = new Modules\TableModule($this);
		$this->headingModule = new Modules\HeadingModule($this);
		$this->listModule = new Modules\ListModule($this);

		// post process
		$this->typographyModule = new Modules\TypographyModule($this);
		$this->longWordsModule = new Modules\LongWordsModule($this);
		$this->htmlOutputModule = new Modules\HtmlOutputModule($this);
	}


	final public function registerLinePattern(callable $handler, string $pattern, string $name, string $againTest = null): void
	{
		if (!isset($this->allowed[$name])) {
			$this->allowed[$name] = true;
		}

		$this->linePatterns[$name] = [
			'handler' => $handler,
			'pattern' => $pattern,
			'again' => $againTest,
		];
	}


	final public function registerBlockPattern(callable $handler, string $pattern, string $name): void
	{
		// if (!preg_match('#(.)\^.*\$\1[a-z]*#is', $pattern)) die("Texy: Not a block pattern $name");
		if (!isset($this->allowed[$name])) {
			$this->allowed[$name] = true;
		}

		$this->blockPatterns[$name] = [
			'handler' => $handler,
			'pattern' => $pattern . 'm', // force multiline
		];
	}


	final public function registerPostLine(callable $handler, string $name): void
	{
		if (!isset($this->allowed[$name])) {
			$this->allowed[$name] = true;
		}

		$this->postHandlers[$name] = $handler;
	}


	/**
	 * Converts document in Texy! to (X)HTML code.
	 */
	public function process(string $text, bool $singleLine = false): string
	{
		if ($this->processing) {
			throw new \RuntimeException('Processing is in progress yet.');
		}

		// initialization
		$this->marks = [];
		$this->processing = true;

		// speed-up
		if (is_array($this->allowedClasses)) {
			$this->_classes = array_flip($this->allowedClasses);
		} else {
			$this->_classes = $this->allowedClasses;
		}
		if (is_array($this->allowedStyles)) {
			$this->_styles = array_flip($this->allowedStyles);
		} else {
			$this->_styles = $this->allowedStyles;
		}

		if ($this->removeSoftHyphens) {
			$text = str_replace("\u{AD}", '', $text);
		}

		// standardize line endings and spaces
		$text = Helpers::normalize($text);

		// replace tabs with spaces
		if ($this->tabWidth) {
			while (strpos($text, "\t") !== false) {
				$text = Regexp::replace($text, '#^([^\t\n]*+)\t#mU', function ($m) {
					return $m[1] . str_repeat(' ', $this->tabWidth - strlen($m[1]) % $this->tabWidth);
				});
			}
		}

		// user before handler
		$this->invokeHandlers('beforeParse', [$this, &$text, $singleLine]);

		// select patterns
		$this->_linePatterns = $this->linePatterns;
		$this->_blockPatterns = $this->blockPatterns;
		foreach ($this->_linePatterns as $name => $foo) {
			if (empty($this->allowed[$name])) {
				unset($this->_linePatterns[$name]);
			}
		}
		foreach ($this->_blockPatterns as $name => $foo) {
			if (empty($this->allowed[$name])) {
				unset($this->_blockPatterns[$name]);
			}
		}

		// parse Texy! document into internal DOM structure
		$this->DOM = new HtmlElement;
		if ($singleLine) {
			$this->DOM->parseLine($this, $text);
		} else {
			$this->DOM->parseBlock($this, $text);
		}

		// user after handler
		$this->invokeHandlers('afterParse', [$this, $this->DOM, $singleLine]);

		// converts internal DOM structure to final HTML code
		$html = $this->DOM->toHtml($this);

		// created by ParagraphModule and then protected
		$html = str_replace("\r", "\n", $html);

		$this->processing = false;
		return $html;
	}


	/**
	 * Converts single line in Texy! to (X)HTML code.
	 */
	public function processLine(string $text): string
	{
		return $this->process($text, true);
	}


	/**
	 * Makes only typographic corrections.
	 */
	public function processTypo(string $text): string
	{
		// standardize line endings and spaces
		$text = Helpers::normalize($text);

		$this->typographyModule->beforeParse($this, $text);
		$text = $this->typographyModule->postLine($text, true);

		if (!empty($this->allowed['longwords'])) {
			$text = $this->longWordsModule->postLine($text);
		}

		return $text;
	}


	/**
	 * Converts DOM structure to pure text.
	 */
	public function toText(): string
	{
		if (!$this->DOM) {
			throw new \RuntimeException('Call $texy->process() first.');
		}

		return $this->DOM->toText($this);
	}


	/**
	 * Converts internal string representation to final HTML code.
	 */
	final public function stringToHtml(string $s): string
	{
		// decode HTML entities to UTF-8
		$s = Helpers::unescapeHtml($s);

		// line-postprocessing
		$blocks = explode(self::CONTENT_BLOCK, $s);
		foreach ($this->postHandlers as $name => $handler) {
			if (empty($this->allowed[$name])) {
				continue;
			}
			foreach ($blocks as $n => $s) {
				if ($n % 2 === 0 && $s !== '') {
					$blocks[$n] = $handler($s);
				}
			}
		}
		$s = implode(self::CONTENT_BLOCK, $blocks);

		// encode < > &
		$s = htmlspecialchars($s, ENT_NOQUOTES, 'UTF-8');

		// replace protected marks
		$s = $this->unProtect($s);

		// wellform and reformat HTML
		$this->invokeHandlers('postProcess', [$this, &$s]);

		// unfreeze spaces
		$s = Helpers::unfreezeSpaces($s);
		$s = ltrim($s, "\n");

		return $s;
	}


	/**
	 * Converts internal string representation to final HTML code.
	 */
	final public function stringToText(string $s): string
	{
		$s = $this->stringToHtml($s);

		// remove tags
		$s = Regexp::replace($s, '#<(script|style)(.*)</\1>#Uis', '');
		$s = strip_tags($s);
		$s = Regexp::replace($s, '#\n\s*\n\s*\n[\n\s]*\n#', "\n\n");

		// entities -> chars
		$s = Helpers::unescapeHtml($s);

		// convert nbsp to normal space and remove shy
		$s = strtr($s, [
			"\u{AD}" => '', // shy
			"\u{A0}" => ' ', // nbsp
		]);

		return $s;
	}


	/**
	 * Add new event handler.
	 */
	final public function addHandler(string $event, callable $callback): void
	{
		$this->handlers[$event][] = $callback;
	}


	/**
	 * Invoke registered around-handlers.
	 * @return mixed
	 */
	final public function invokeAroundHandlers(string $event, Parser $parser, array $args)
	{
		if (!isset($this->handlers[$event])) {
			return;
		}

		$invocation = new HandlerInvocation($this->handlers[$event], $parser, $args);
		return $invocation->proceed();
	}


	/**
	 * Invoke registered after-handlers.
	 */
	final public function invokeHandlers(string $event, array $args): void
	{
		if (!isset($this->handlers[$event])) {
			return;
		}

		foreach ($this->handlers[$event] as $handler) {
			$handler(...$args);
		}
	}


	/**
	 * Generate unique mark - useful for freezing (folding) some substrings.
	 */
	final public function protect(string $child, string $contentType): string
	{
		if ($child === '') {
			return '';
		}

		$key = $contentType
			. strtr(base_convert(count($this->marks), 10, 8), '01234567', "\x18\x19\x1A\x1B\x1C\x1D\x1E\x1F")
			. $contentType;

		$this->marks[$key] = $child;

		return $key;
	}


	/**
	 * Filters bad URLs.
	 * @param  string  $type  Texy::FILTER_ANCHOR | Texy::FILTER_IMAGE
	 */
	final public function checkURL(string $URL, string $type): bool
	{
		// absolute URL with scheme? check scheme!
		return empty($this->urlSchemeFilters[$type])
			|| !preg_match('#\s*[a-z][a-z0-9+.-]{0,20}:#Ai', $URL) // http: | mailto:
			|| preg_match($this->urlSchemeFilters[$type], $URL);
	}


	final public function unProtect(string $html): string
	{
		return strtr($html, $this->marks);
	}


	/** @return array<string, array{handler: callable, pattern: string, again: ?string}> */
	final public function getLinePatterns(): array
	{
		return $this->_linePatterns;
	}


	/** @return array<string, array{handler: callable, pattern: string}> */
	final public function getBlockPatterns(): array
	{
		return $this->_blockPatterns;
	}


	final public function getDOM(): HtmlElement
	{
		return $this->DOM;
	}


	/**
	 * @internal
	 * @return array<string, array{array<string, int>, array<string, int>}>
	 */
	public static function getDTD(): array
	{
		return self::$dtd;
	}


	/** @internal */
	final public function getAllowedProps(): array
	{
		return [$this->_classes, $this->_styles];
	}


	final public function __clone()
	{
		throw new \Exception('Clone is not supported.');
	}


	/** @deprecated */
	final public static function freezeSpaces(string $s): string
	{
		trigger_error(__METHOD__ . '() is deprecated, use Texy\Helpers::freezeSpaces()', E_USER_DEPRECATED);
		return Helpers::freezeSpaces($s);
	}


	/** @deprecated */
	final public static function unfreezeSpaces(string $s): string
	{
		trigger_error(__METHOD__ . '() is deprecated, use Texy\Helpers::unfreezeSpaces()', E_USER_DEPRECATED);
		return Helpers::unfreezeSpaces($s);
	}


	/** @deprecated */
	final public static function normalize(string $s): string
	{
		trigger_error(__METHOD__ . '() is deprecated, use Texy\Helpers::normalize()', E_USER_DEPRECATED);
		return Helpers::normalize($s);
	}


	/** @deprecated */
	final public static function webalize(string $s, string $charlist = ''): string
	{
		trigger_error(__METHOD__ . '() is deprecated, use Texy\Helpers::webalize()', E_USER_DEPRECATED);
		return Helpers::webalize($s, $charlist);
	}


	/** @deprecated */
	final public static function escapeHtml(string $s): string
	{
		trigger_error(__METHOD__ . '() is deprecated, use htmlspecialchars()', E_USER_DEPRECATED);
		return htmlspecialchars($s, ENT_NOQUOTES, 'UTF-8');
	}


	/** @deprecated */
	final public static function unescapeHtml(string $s): string
	{
		trigger_error(__METHOD__ . '() is deprecated, use Texy\Helpers::unescapeHtml()', E_USER_DEPRECATED);
		return Helpers::unescapeHtml($s);
	}


	/** @deprecated */
	final public static function outdent(string $s): string
	{
		trigger_error(__METHOD__ . '() is deprecated, use Texy\Helpers::outdent()', E_USER_DEPRECATED);
		return Helpers::outdent($s);
	}


	/** @deprecated */
	final public static function isRelative(string $URL): bool
	{
		trigger_error(__METHOD__ . '() is deprecated, use Texy\Helpers::isRelative()', E_USER_DEPRECATED);
		return Helpers::isRelative($URL);
	}


	/** @deprecated */
	final public static function prependRoot(string $URL, string $root): string
	{
		trigger_error(__METHOD__ . '() is deprecated, use Texy\Helpers::prependRoot()', E_USER_DEPRECATED);
		return Helpers::prependRoot($URL, $root);
	}
}


class_exists(\Texy::class);
