<?php

/**
 * This file is part of the Texy! (https://texy.info)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy;


/**
 * Texy! - Convert plain text to XHTML format using {@link process()}.
 *
 * <code>
 * $texy = new Texy();
 * $html = $texy->process($text);
 * </code>
 */
class Texy
{
	use Strict;

	// configuration directives
	const ALL = true;
	const NONE = false;

	// Texy version
	const VERSION = '2.9.3';
	const REVISION = 'released on 2018-06-22';

	// types of protection marks
	const CONTENT_MARKUP = "\x17";
	const CONTENT_REPLACED = "\x16";
	const CONTENT_TEXTUAL = "\x15";
	const CONTENT_BLOCK = "\x14";

	// url filters
	const FILTER_ANCHOR = 'anchor';
	const FILTER_IMAGE = 'image';

	// HTML minor-modes
	const XML = 2;

	// HTML modes
	const HTML4_TRANSITIONAL = 0;
	const HTML4_STRICT = 1;
	const HTML5 = 4;
	const XHTML1_TRANSITIONAL = 2; // Texy::HTML4_TRANSITIONAL | Texy::XML;
	const XHTML1_STRICT = 3; // Texy::HTML4_STRICT | Texy::XML;
	const XHTML5 = 6; // Texy::HTML5 | Texy::XML;

	/** @var string  input & output text encoding */
	public $encoding = 'utf-8';

	/** @var array  Texy! syntax configuration */
	public $allowed = [];

	/** @var true|false|array  Allowed HTML tags */
	public $allowedTags;

	/** @var true|false|array  Allowed classes */
	public $allowedClasses = self::ALL; // all classes and id are allowed

	/** @var true|false|array  Allowed inline CSS style */
	public $allowedStyles = self::ALL;  // all inline styles are allowed

	/** @var int  TAB width (for converting tabs to spaces) */
	public $tabWidth = 8;

	/** @var bool  Do obfuscate e-mail addresses? */
	public $obfuscateEmail = true;

	/** @var array  regexps to check URL schemes */
	public $urlSchemeFilters; // disable URL scheme filter

	/** @var bool  Paragraph merging mode */
	public $mergeLines = true;

	/** @var array  Parsing summary */
	public $summary = [
		'images' => [],
		'links' => [],
		'preload' => [],
	];

	/** @var string  Generated stylesheet */
	public $styleSheet = '';

	/** @var array  CSS classes for align modifiers */
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

	/** @deprecated */
	public static $advertisingNotice = false;

	/** @var string */
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
	 * @var array of ('handler' => callback, 'pattern' => regular expression)
	 */
	private $linePatterns = [];
	private $_linePatterns;

	/**
	 * Registered regexps and associated handlers for block parsing.
	 * @var array of ('handler' => callback, 'pattern' => regular expression)
	 */
	private $blockPatterns = [];
	private $_blockPatterns;

	/** @var array */
	private $postHandlers = [];

	/** @var HtmlElement  DOM structure for parsed text */
	private $DOM;

	/** @var array  Texy protect markup table */
	private $marks = [];

	/** @var array  for internal usage */
	public $_classes, $_styles;

	/** @var bool */
	private $processing;

	/** @var array of events and registered handlers */
	private $handlers = [];

	/**
	 * DTD descriptor.
	 *   $dtd[element][0] - allowed attributes (as array keys)
	 *   $dtd[element][1] - allowed content for an element (content model) (as array keys)
	 *                    - array of allowed elements (as keys)
	 *                    - false - empty element
	 *                    - 0 - special case for ins & del
	 * @var array
	 */
	public $dtd;

	/** @var array */
	private static $dtdCache;

	/** @var int  HTML mode */
	private $mode;


	/** DEPRECATED */
	public static $strictDTD;
	public $cleaner;
	public $xhtml;


	public function __construct()
	{
		if (defined('PCRE_VERSION') && PCRE_VERSION == 8.34 && PHP_VERSION_ID < 50513) {
			trigger_error('Texy: PCRE 8.34 is not supported due to bug #1451', E_USER_WARNING);
		}

		if (extension_loaded('mbstring') && mb_get_info('func_overload') & 2 && substr(mb_get_info('internal_encoding'), 0, 1) === 'U') {
			mb_internal_encoding('pass');
			trigger_error("Texy: mb_internal_encoding changed to 'pass'", E_USER_WARNING);
		}

		// load all modules
		$this->loadModules();

		// DEPRECATED
		if (self::$strictDTD !== null) {
			$this->setOutputMode(self::$strictDTD ? self::XHTML1_STRICT : self::XHTML1_TRANSITIONAL);
		} else {
			$this->setOutputMode(self::XHTML1_TRANSITIONAL);
		}

		// DEPRECATED
		$this->cleaner = &$this->htmlOutputModule;

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


	/**
	 * Set HTML/XHTML output mode (overwrites self::$allowedTags)
	 * @param  int
	 * @return void
	 */
	public function setOutputMode($mode)
	{
		if (!in_array($mode, [self::HTML4_TRANSITIONAL, self::HTML4_STRICT,
			self::HTML5, self::XHTML1_TRANSITIONAL, self::XHTML1_STRICT, self::XHTML5, ], true)
		) {
			throw new \InvalidArgumentException('Invalid mode.');
		}

		if (!isset(self::$dtdCache[$mode])) {
			require __DIR__ . '/DTD.php';
			self::$dtdCache[$mode] = $dtd;
		}

		$this->mode = $mode;
		$this->dtd = self::$dtdCache[$mode];
		HtmlElement::$xhtml = (bool) ($mode & self::XML); // TODO: remove?

		// accept all valid HTML tags and attributes by default
		$this->allowedTags = [];
		foreach ($this->dtd as $tag => $dtd) {
			$this->allowedTags[$tag] = self::ALL;
		}
	}


	/**
	 * Get HTML/XHTML output mode
	 * @return int
	 */
	public function getOutputMode()
	{
		return $this->mode;
	}


	/**
	 * Create array of all used modules ($this->modules).
	 * This array can be changed by overriding this method (by subclasses)
	 */
	protected function loadModules()
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


	final public function registerLinePattern($handler, $pattern, $name, $againTest = null)
	{
		if (!is_callable($handler)) {
			$able = is_callable($handler, true, $textual);
			throw new \InvalidArgumentException("Handler '$textual' is not " . ($able ? 'callable.' : 'valid PHP callback.'));
		}

		if (!isset($this->allowed[$name])) {
			$this->allowed[$name] = true;
		}

		$this->linePatterns[$name] = [
			'handler' => $handler,
			'pattern' => $pattern,
			'again' => $againTest,
		];
	}


	final public function registerBlockPattern($handler, $pattern, $name)
	{
		if (!is_callable($handler)) {
			$able = is_callable($handler, true, $textual);
			throw new \InvalidArgumentException("Handler '$textual' is not " . ($able ? 'callable.' : 'valid PHP callback.'));
		}

		// if (!preg_match('#(.)\^.*\$\1[a-z]*#is', $pattern)) die("Texy: Not a block pattern $name");
		if (!isset($this->allowed[$name])) {
			$this->allowed[$name] = true;
		}

		$this->blockPatterns[$name] = [
			'handler' => $handler,
			'pattern' => $pattern . 'm', // force multiline
		];
	}


	final public function registerPostLine($handler, $name)
	{
		if (!is_callable($handler)) {
			$able = is_callable($handler, true, $textual);
			throw new \InvalidArgumentException("Handler '$textual' is not " . ($able ? 'callable.' : 'valid PHP callback.'));
		}

		if (!isset($this->allowed[$name])) {
			$this->allowed[$name] = true;
		}

		$this->postHandlers[$name] = $handler;
	}


	/**
	 * Converts document in Texy! to (X)HTML code.
	 *
	 * @param  string   input text
	 * @param  bool     is single line?
	 * @return string   output HTML code
	 */
	public function process($text, $singleLine = false)
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

		// convert to UTF-8 (and check source encoding)
		$text = Utf::toUtf($text, $this->encoding);

		if ($this->removeSoftHyphens) {
			$text = str_replace("\xC2\xAD", '', $text);
		}

		// standardize line endings and spaces
		$text = Helpers::normalize($text);

		// replace tabs with spaces
		$this->tabWidth = max(1, (int) $this->tabWidth);
		while (strpos($text, "\t") !== false) {
			$text = Regexp::replace($text, '#^([^\t\n]*+)\t#mU', function ($m) {
				return $m[1] . str_repeat(' ', $this->tabWidth - strlen($m[1]) % $this->tabWidth);
			});
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

		return Utf::utf2html($html, $this->encoding);
	}


	/**
	 * Converts single line in Texy! to (X)HTML code.
	 *
	 * @param  string   input text
	 * @return string   output HTML code
	 */
	public function processLine($text)
	{
		return $this->process($text, true);
	}


	/**
	 * Makes only typographic corrections.
	 * @param  string   input text (in encoding defined by Texy::$encoding)
	 * @return string   output text (in UTF-8)
	 */
	public function processTypo($text)
	{
		// convert to UTF-8 (and check source encoding)
		$text = Utf::toUtf($text, $this->encoding);

		// standardize line endings and spaces
		$text = Helpers::normalize($text);

		$this->typographyModule->beforeParse($this, $text);
		$text = $this->typographyModule->postLine($text, true);

		if (!empty($this->allowed['longwords'])) {
			$text = $this->longWordsModule->postLine($text);
		}

		return Utf::utf2html($text, $this->encoding);
	}


	/**
	 * Converts DOM structure to pure text.
	 * @return string
	 */
	public function toText()
	{
		if (!$this->DOM) {
			throw new \RuntimeException('Call $texy->process() first.');
		}

		return Utf::utfTo($this->DOM->toText($this), $this->encoding);
	}


	/**
	 * Converts internal string representation to final HTML code in UTF-8.
	 * @return string
	 */
	final public function stringToHtml($s)
	{
		// decode HTML entities to UTF-8
		$s = html_entity_decode($s, ENT_QUOTES, 'UTF-8');

		// line-postprocessing
		$blocks = explode(self::CONTENT_BLOCK, $s);
		foreach ($this->postHandlers as $name => $handler) {
			if (empty($this->allowed[$name])) {
				continue;
			}
			foreach ($blocks as $n => $s) {
				if ($n % 2 === 0 && $s !== '') {
					$blocks[$n] = call_user_func($handler, $s);
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
	 * Converts internal string representation to final HTML code in UTF-8.
	 * @return string
	 */
	final public function stringToText($s)
	{
		$save = $this->htmlOutputModule->lineWrap;
		$this->htmlOutputModule->lineWrap = false;
		$s = $this->stringToHtml($s);
		$this->htmlOutputModule->lineWrap = $save;

		// remove tags
		$s = Regexp::replace($s, '#<(script|style)(.*)</\1>#Uis', '');
		$s = strip_tags($s);
		$s = Regexp::replace($s, '#\n\s*\n\s*\n[\n\s]*\n#', "\n\n");

		// entities -> chars
		$s = html_entity_decode($s, ENT_QUOTES, 'UTF-8');

		// convert nbsp to normal space and remove shy
		$s = strtr($s, [
			"\xC2\xAD" => '', // shy
			"\xC2\xA0" => ' ', // nbsp
		]);

		return $s;
	}


	/**
	 * Add new event handler.
	 *
	 * @param  string   event name
	 * @param  callback
	 * @return void
	 */
	final public function addHandler($event, $callback)
	{
		if (!is_callable($callback)) {
			$able = is_callable($callback, true, $textual);
			throw new \InvalidArgumentException("Handler '$textual' is not " . ($able ? 'callable.' : 'valid PHP callback.'));
		}

		$this->handlers[$event][] = $callback;
	}


	/**
	 * Invoke registered around-handlers.
	 * @return mixed
	 */
	final public function invokeAroundHandlers($event, Parser $parser, array $args)
	{
		if (!isset($this->handlers[$event])) {
			return false;
		}

		$invocation = new HandlerInvocation($this->handlers[$event], $parser, $args);
		return $invocation->proceed();
	}


	/**
	 * Invoke registered after-handlers.
	 * @return void
	 */
	final public function invokeHandlers($event, array $args)
	{
		if (!isset($this->handlers[$event])) {
			return;
		}

		foreach ($this->handlers[$event] as $handler) {
			call_user_func_array($handler, $args);
		}
	}


	/**
	 * Generate unique mark - useful for freezing (folding) some substrings.
	 * @param  string   any string to froze
	 * @param  int      Texy::CONTENT_* constant
	 * @return string  internal mark
	 */
	final public function protect($child, $contentType)
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
	 * @param  string   user URL
	 * @param  string   FILTER_ANCHOR | FILTER_IMAGE
	 * @return bool
	 */
	final public function checkURL($URL, $type)
	{
		// absolute URL with scheme? check scheme!
		return empty($this->urlSchemeFilters[$type])
			|| !preg_match('#\s*[a-z][a-z0-9+.-]{0,20}:#Ai', $URL) // http: | mailto:
			|| preg_match($this->urlSchemeFilters[$type], $URL);
	}


	final public function unProtect($html)
	{
		return strtr($html, $this->marks);
	}


	final public function getLinePatterns()
	{
		return $this->_linePatterns;
	}


	final public function getBlockPatterns()
	{
		return $this->_blockPatterns;
	}


	final public function getDOM()
	{
		return $this->DOM;
	}


	final public function __clone()
	{
		throw new \Exception('Clone is not supported.');
	}


	/** @deprecated */
	final public static function freezeSpaces($s)
	{
		return Helpers::freezeSpaces($s);
	}


	/** @deprecated */
	final public static function unfreezeSpaces($s)
	{
		return Helpers::unfreezeSpaces($s);
	}


	/** @deprecated */
	final public static function normalize($s)
	{
		return Helpers::normalize($s);
	}


	/** @deprecated */
	final public static function webalize($s, $charlist = null)
	{
		return Helpers::webalize($s, $charlist);
	}


	/** @deprecated */
	final public static function escapeHtml($s)
	{
		return htmlspecialchars($s, ENT_NOQUOTES, 'UTF-8');
	}


	/** @deprecated */
	final public static function unescapeHtml($s)
	{
		return html_entity_decode($s, ENT_QUOTES, 'UTF-8');
	}


	/** @deprecated */
	final public static function outdent($s)
	{
		return Helpers::outdent($s);
	}


	/** @deprecated */
	final public static function isRelative($URL)
	{
		return Helpers::isRelative($URL);
	}


	/** @deprecated */
	final public static function prependRoot($URL, $root)
	{
		return Helpers::prependRoot($URL, $root);
	}


	/** @deprecated */
	final public function free()
	{
	}
}
