<?php

/**
 * Texy! - plain text to html converter
 * ------------------------------------
 *
 * Copyright (c) 2004-2007 David Grudl aka -dgx- (http://www.dgx.cz)
 *
 * This source file is subject to the GNU GPL license that is bundled
 * with this package in the file license.txt.
 *
 * for PHP 5.0.0 and newer
 *
 * @author     David Grudl
 * @copyright  Copyright (c) 2004-2007 David Grudl aka -dgx- (http://www.dgx.cz)
 * @license    GNU GENERAL PUBLIC LICENSE version 2 or 3
 * @version    2.0 BETA 2 (Revision: $WCREV$, Date: $WCDATE$)
 * @category   Text
 * @package    Texy
 * @link       http://texy.info/
 */


/**
 * This file is part of the Texy! formatter (http://texy.info/)
 *
 * @version    $Revision$ $Date$
 */



/**
 * Absolute filesystem path to the Texy package
 */
define('TEXY_DIR',  dirname(__FILE__).'/');

require_once TEXY_DIR.'libs/RegExp.Patterns.php';
require_once TEXY_DIR.'libs/TexyHtml.php';
require_once TEXY_DIR.'libs/TexyHtmlCleaner.php';
require_once TEXY_DIR.'libs/TexyModifier.php';
require_once TEXY_DIR.'libs/TexyModule.php';
require_once TEXY_DIR.'libs/TexyParser.php';
require_once TEXY_DIR.'libs/TexyUtf.php';
require_once TEXY_DIR.'libs/TexyConfigurator.php';
require_once TEXY_DIR.'modules/TexyParagraphModule.php';
require_once TEXY_DIR.'modules/TexyBlockModule.php';
require_once TEXY_DIR.'modules/TexyHeadingModule.php';
require_once TEXY_DIR.'modules/TexyHorizLineModule.php';
require_once TEXY_DIR.'modules/TexyHtmlModule.php';
require_once TEXY_DIR.'modules/TexyFigureModule.php';
require_once TEXY_DIR.'modules/TexyImageModule.php';
require_once TEXY_DIR.'modules/TexyLinkModule.php';
require_once TEXY_DIR.'modules/TexyListModule.php';
require_once TEXY_DIR.'modules/TexyLongWordsModule.php';
require_once TEXY_DIR.'modules/TexyPhraseModule.php';
require_once TEXY_DIR.'modules/TexyQuoteModule.php';
require_once TEXY_DIR.'modules/TexyScriptModule.php';
require_once TEXY_DIR.'modules/TexyEmoticonModule.php';
require_once TEXY_DIR.'modules/TexyTableModule.php';
require_once TEXY_DIR.'modules/TexyTypographyModule.php';




// for PHP 4 backward compatibility
define('TEXY_ALL',  Texy::ALL);
define('TEXY_NONE',  Texy::NONE);
define('TEXY_VERSION',  Texy::VERSION);
define('TEXY_CONTENT_MARKUP', Texy::CONTENT_MARKUP);
define('TEXY_CONTENT_REPLACED', Texy::CONTENT_REPLACED);
define('TEXY_CONTENT_TEXTUAL', Texy::CONTENT_TEXTUAL);
define('TEXY_CONTENT_BLOCK', Texy::CONTENT_BLOCK);
define('TEXY_PROCEED',  Texy::PROCEED);




/**
 * Texy! - Convert plain text to XHTML format using {@link process()}
 *
 * <code>
 *     $texy = new Texy();
 *     $html = $texy->process($text);
 * </code>
 */
class Texy
{
    // configuration directives
    const ALL = TRUE;
    const NONE = FALSE;

    // Texy version
    const VERSION = '2.0 BETA 2 (Revision: $WCREV$, Date: $WCDATE$)';

    // types of protection marks
    const CONTENT_MARKUP = "\x17";
    const CONTENT_REPLACED = "\x16";
    const CONTENT_TEXTUAL = "\x15";
    const CONTENT_BLOCK = "\x14";

    // for event handlers
    const PROCEED = NULL;

    /** @var string  input & output text encoding */
    public $encoding = 'utf-8';

    /** @var array  Texy! syntax configuration */
    public $allowed = array();

     /** @var TRUE|FALSE|array  Allowed HTML tags */
    public $allowedTags;

    /** @var TRUE|FALSE|array  Allowed classes */
    public $allowedClasses = Texy::ALL; // all classes and id are allowed

    /** @var TRUE|FALSE|array  Allowed inline CSS style */
    public $allowedStyles = Texy::ALL;  // all inline styles are allowed

    /** @var int  TAB width (for converting tabs to spaces) */
    public $tabWidth = 8;

    /** @var boolean  Do obfuscate e-mail addresses? */
    public $obfuscateEmail = TRUE;

    /** @var array  regexps to check URL schemes */
    public $urlSchemeFilters = NULL; // disable URL scheme filter

    /** @var array  Parsing summary */
    public $summary = array(
        'images' => array(),
        'links' => array(),
        'preload' => array(),
    );

    /** @var string  Generated stylesheet */
    public $styleSheet = '';

    /** @var bool  Paragraph merging mode */
    public $mergeLines = TRUE;

    /** @var object  User handler object */
    public $handler;

    /** @var bool  ignore stuff with only markup and spaecs? */
    public $ignoreEmptyStuff = TRUE;

    /** @var bool  use Strict of Transitional DTD? */
    public static $strictDTD = FALSE;

    /** @var bool  use XHTML syntax? */
    public $xhtml = TRUE;


    public
        /** @var TexyScriptModule */
        $scriptModule,
        /** @var TexyParagraphModule */
        $paragraphModule,
        /** @var TexyHtmlModule */
        $htmlModule,
        /** @var TexyImageModule */
        $imageModule,
        /** @var TexyLinkModule */
        $linkModule,
        /** @var TexyPhraseModule */
        $phraseModule,
        /** @var TexyEmoticonModule */
        $emoticonModule,
        /** @var TexyBlockModule */
        $blockModule,
        /** @var TexyHeadingModule */
        $headingModule,
        /** @var TexyHorizLineModule */
        $horizLineModule,
        /** @var TexyQuoteModule */
        $quoteModule,
        /** @var TexyListModule */
        $listModule,
        /** @var TexyTableModule */
        $tableModule,
        /** @var TexyFigureModule */
        $figureModule,
        /** @var TexyTypographyModule */
        $typographyModule,
        /** @var TexyLongWordsModule */
        $longWordsModule;

    public
        $cleaner;


    /**
     * Registered regexps and associated handlers for inline parsing
     * @var array of ('handler' => callback
     *                'pattern' => regular expression)
     */
    private $linePatterns = array();

    /**
     * Registered regexps and associated handlers for block parsing
     * @var array of ('handler' => callback
     *                'pattern' => regular expression)
     */
    private $blockPatterns = array();


    /** @var TexyDomElement  DOM structure for parsed text */
    private $DOM;

    /** @var TexyModule[]  List of all modules */
    private $modules;

    /** @var array  Texy protect markup table */
    private $marks = array();

    /** @var array  for internal usage */
    public $_classes, $_styles;

    /** @var array of TexyPreBlockInterface for internal parser usage */
    public $_preBlockModules;

    /** @var int internal state (0=new, 1=parsing, 2=parsed) */
    private $state = 0;



    public function __construct()
    {
        // load all modules
        $this->loadModules();

        // load routines
        $this->cleaner = new TexyHtmlCleaner($this);

        // accepts all valid HTML tags and attributes by default
        foreach ($this->cleaner->dtd as $tag => $dtd)
            $this->allowedTags[$tag] = is_array($dtd[0]) ? array_keys($dtd[0]) : $dtd[0];

        // examples of link references ;-)
        $link = new TexyLink('http://texy.info/');
        $link->modifier->title = 'The best text -> HTML converter and formatter';
        $link->label = 'Texy!';
        $this->linkModule->addReference('texy', $link);

        $link = new TexyLink('http://www.google.com/search?q=%s');
        $this->linkModule->addReference('google', $link);

        $link = new TexyLink('http://en.wikipedia.org/wiki/Special:Search?search=%s');
        $this->linkModule->addReference('wikipedia', $link);

        // mbstring.func_overload fix
        if (function_exists('mb_get_info')) {
            $mb = mb_get_info('all'); // all for PHP < 4.4.3
            if ($mb['func_overload'] & 2 && $mb['internal_encoding'][0] === 'U') { // U??
                mb_internal_encoding('pass');
                trigger_error("Texy: mb_internal_encoding changed to 'pass'", E_USER_WARNING);
            }
        }
    }



    /**
     * Create array of all used modules ($this->modules)
     * This array can be changed by overriding this method (by subclasses)
     */
    protected function loadModules()
    {
        // Line parsing - order is not important
        $this->scriptModule = new TexyScriptModule($this);
        $this->htmlModule = new TexyHtmlModule($this);
        $this->imageModule = new TexyImageModule($this);
        $this->phraseModule = new TexyPhraseModule($this);
        $this->linkModule = new TexyLinkModule($this);
        $this->emoticonModule = new TexyEmoticonModule($this);

        // block parsing - order is not important
        $this->paragraphModule = new TexyParagraphModule($this);
        $this->blockModule = new TexyBlockModule($this);
        $this->headingModule = new TexyHeadingModule($this);
        $this->horizLineModule = new TexyHorizLineModule($this);
        $this->quoteModule = new TexyQuoteModule($this);
        $this->listModule = new TexyListModule($this);
        $this->tableModule = new TexyTableModule($this);
        $this->figureModule = new TexyFigureModule($this);

        // post process - order is not important
        $this->typographyModule = new TexyTypographyModule($this);
        $this->longWordsModule = new TexyLongWordsModule($this);
    }



    public function registerModule(TexyModule $module)
    {
        $this->modules[] = $module;
        $this->allowed = array_merge($this->allowed, $module->syntax);
    }



    public function registerLinePattern($handler, $pattern, $name)
    {
        if (empty($this->allowed[$name])) return;
        $this->linePatterns[$name] = array(
            'handler'     => $handler,
            'pattern'     => $pattern,
        );
    }



    public function registerBlockPattern($handler, $pattern, $name)
    {
        // if (!preg_match('#(.)\^.*\$\\1[a-z]*#is', $pattern)) die('Texy: Not a block pattern. Module '.get_class($module).', pattern '.htmlSpecialChars($pattern));
        if (empty($this->allowed[$name])) return;
        $this->blockPatterns[$name] = array(
            'handler'     => $handler,
            'pattern'     => $pattern  . 'm',  // force multiline
        );
    }




    /**
     * Convert Texy! document in (X)HTML code
     * This is shortcut for parse() & toHtml()
     *
     * @param string   input text
     * @param bool     is block or single line?
     * @return string  output html code
     */
    public function process($text, $singleLine = FALSE)
    {
        $this->parse($text, $singleLine);
        return $this->toHtml();
    }



    /**
     * Makes only typographic corrections
     * @param string   input text
     * @return string  output code (in UTF!)
     */
    public function processTypo($text)
    {
        // convert to UTF-8 (and check source encoding)
        $text = TexyUtf::toUtf($text, $this->encoding);

        // standardize line endings and spaces
        $text = self::normalize($text);

        $this->typographyModule->begin();
        $text = $this->typographyModule->postLine($text);

        return $text;
    }


    /**
     * Converts Texy! document into internal DOM structure ($this->DOM)
     * Before converting it normalize text and call all pre-processing modules
     *
     * @param string
     * @param bool     is block or single line?
     * @return void
     */
    public function parse($text, $singleLine = FALSE)
    {
        if ($this->state === 1)
            throw new Exception('Parsing is in progress yet.');

         // initialization
        if ($this->handler && !is_object($this->handler))
            throw new Exception('$texy->handler must be object. See documentation.');

        $this->marks = array();
        $this->state = 1;

        // speed-up
        if (is_array($this->allowedClasses)) $this->_classes = array_flip($this->allowedClasses);
        else $this->_classes = $this->allowedClasses;

        if (is_array($this->allowedStyles)) $this->_styles = array_flip($this->allowedStyles);
        else $this->_styles = $this->allowedStyles;

        $tmp = array($this->linePatterns, $this->blockPatterns);

        // convert to UTF-8 (and check source encoding)
        $text = TexyUtf::toUtf($text, $this->encoding);

        // standardize line endings and spaces
        $text = self::normalize($text);

        // replace tabs with spaces
        while (strpos($text, "\t") !== FALSE)
            $text = preg_replace_callback('#^(.*)\t#mU', array($this, 'tabCb'), $text);


        // init modules
        $this->_preBlockModules = array();
        foreach ($this->modules as $module) {
            $module->begin();

            if ($module instanceof TexyPreBlockInterface) $this->_preBlockModules[] = $module;
        }

        // parse!
        $this->DOM = TexyHtml::el();
        if ($singleLine)
            $this->DOM->parseLine($this, $text);
        else
            $this->DOM->parseBlock($this, $text, TRUE);

        // user handler
        if (is_callable(array($this->handler, 'afterParse')))
            $this->handler->afterParse($this, $this->DOM, $singleLine);

        // clean-up
        list($this->linePatterns, $this->blockPatterns) = $tmp;
        $this->state = 2;
    }





    /**
     * Converts internal DOM structure to final HTML code
     * @return string
     */
    public function toHtml()
    {
        if ($this->state !== 2) throw new Exception('Call $texy->parse() first.');

        $html = $this->_toHtml( $this->DOM->export($this) );

        // this notice should remain!
        if (!defined('TEXY_NOTICE_SHOWED')) {
            $html .= "\n<!-- by Texy2! -->";
            define('TEXY_NOTICE_SHOWED', TRUE);
        }

        $html = TexyUtf::utf2html($html, $this->encoding);

        return $html;
    }



    /**
     * Converts internal DOM structure to pure Text
     * @return string
     */
    public function toText()
    {
        if ($this->state !== 2) throw new Exception('Call $texy->parse() first.');

        $text = $this->_toText( $this->DOM->export($this) );

        $text = TexyUtf::utfTo($text, $this->encoding);

        return $text;
    }



    /**
     * Converts internal DOM structure to final HTML code in UTF-8
     * @return string
     */
    public function _toHtml($s)
    {
        // decode HTML entities to UTF-8
        $s = self::unescapeHtml($s);

        // line-postprocessing
        $blocks = explode(self::CONTENT_BLOCK, $s);
        foreach ($this->modules as $module) {
            if ($module instanceof TexyPostLineInterface) {
                foreach ($blocks as $n => $s) {
                    if ($n % 2 === 0 && $s !== '')
                        $blocks[$n] = $module->postLine($s);
                }
            }
        }
        $s = implode(self::CONTENT_BLOCK, $blocks);

        // encode < > &
        $s = self::escapeHtml($s);

        // replace protected marks
        $s = $this->unProtect($s);

        // wellform and reformat HTML
        $s = $this->cleaner->process($s);

        // unfreeze spaces
        $s = self::unfreezeSpaces($s);

        return $s;
    }



    /**
     * Converts internal DOM structure to final HTML code in UTF-8
     * @return string
     */
    public function _toText($s)
    {
        $save = $this->cleaner->lineWrap;
        $this->cleaner->lineWrap = FALSE;
        $s = $this->_toHtml( $s );
        $this->cleaner->lineWrap = $save;

        // remove tags
        $s = preg_replace('#<(script|style)(.*)</\\1>#Uis', '', $s);
        $s = strip_tags($s);
        $s = preg_replace('#\n\s*\n\s*\n[\n\s]*\n#', "\n\n", $s);

        // entities -> chars
        $s = Texy::unescapeHtml($s);

        // convert nbsp to normal space and remove shy
        $s = strtr($s, array(
            "\xC2\xAD" => '',  // shy
            "\xC2\xA0" => ' ', // nbsp
        ));

        return $s;
    }




    /**
     * Translate all white spaces (\t \n \r space) to meta-spaces \x01-\x04
     * which are ignored by TexyHtmlCleaner routine
     * @param string
     * @return string
     */
    public static function freezeSpaces($s)
    {
        return strtr($s, " \t\r\n", "\x01\x02\x03\x04");
    }



    /**
     * Reverts meta-spaces back to normal spaces
     * @param string
     * @return string
     */
    public static function unfreezeSpaces($s)
    {
        return strtr($s, "\x01\x02\x03\x04", " \t\r\n");
    }



    /**
     * Removes special controls characters and normalizes line endings and spaces
     * @param string
     * @return string
     */
    public static function normalize($s)
    {
        // remove special chars
        $s = preg_replace('#[\x01-\x04\x14-\x1F]+#', '', $s);

        // standardize line endings to unix-like
        $s = str_replace("\r\n", "\n", $s); // DOS
        $s = strtr($s, "\r", "\n"); // Mac

        // right trim
        $s = preg_replace("#[\t ]+$#m", '', $s); // right trim

        // trailing spaces
        $s = trim($s, "\n");

        return $s;
    }



    /**
     * Converts to web safe characters [a-z0-9-] text
     * @param string
     * @param string
     * @return string
     */
    public static function webalize($s, $charlist = NULL)
    {
        $s = TexyUtf::utf2ascii($s);
        $s = strtolower($s);
        if ($charlist) $charlist = preg_quote($charlist, '#');
        $s = preg_replace('#[^a-z0-9'.$charlist.']+#', '-', $s);
        $s = trim($s, '-');
        return $s;
    }



    /**
     * Texy! version of htmlSpecialChars (much faster than htmlSpecialChars!)
     * note: &quot; is not encoded!
     * @param string
     * @return string
     */
    public static function escapeHtml($s)
    {
        return str_replace(array('&', '<', '>'), array('&amp;', '&lt;', '&gt;'), $s);
    }



    /**
     * Texy! version of html_entity_decode (always UTF-8, much faster than original!)
     * @param string
     * @return string
     */
    public static function unescapeHtml($s)
    {
        if (strpos($s, '&') === FALSE) return $s;
        return html_entity_decode($s, ENT_QUOTES, 'UTF-8');
    }



    /**
     * Generate unique mark - useful for freezing (folding) some substrings
     * @param string   any string to froze
     * @param int      Texy::CONTENT_* constant
     * @return string  internal mark
     */
    public function protect($child, $contentType = self::CONTENT_BLOCK)
    {
        if ($child==='') return '';

        $key = $contentType
            . strtr(base_convert(count($this->marks), 10, 8), '01234567', "\x18\x19\x1A\x1B\x1C\x1D\x1E\x1F")
            . $contentType;

        $this->marks[$key] = $child;

        return $key;
    }



    public function unProtect($html)
    {
        return strtr($html, $this->marks);
    }



    /**
     * Filters bad URLs
     * @param string   user URL
     * @param string   type: a-anchor, i-image, c-cite
     * @return bool
     */
    public function checkURL($URL, $type)
    {
        // absolute URL with scheme? check scheme!
        if (!empty($this->urlSchemeFilters[$type])
            && preg_match('#'.TEXY_URLSCHEME.'#iA', $URL)
            && !preg_match($this->urlSchemeFilters[$type], $URL))
            return FALSE;

        return TRUE;
    }



    /**
     * Is given URL relative?
     * @param string  URL
     * @return bool
     */
    public static function isRelative($URL)
    {
        // check for scheme, or absolute path, or absolute URL
        return !preg_match('#'.TEXY_URLSCHEME.'|[\#/?]#iA', $URL);
    }



    /**
     * Prepends root to URL, if possible
     * @param string  URL
     * @param string  root
     * @return string
     */
    public static function prependRoot($URL, $root)
    {
        if ($root == NULL || !self::isRelative($URL)) return $URL;
        return rtrim($root, '/\\') . '/' . $URL;
    }



    public function getLinePatterns()
    {
        return $this->linePatterns;
    }



    public function getBlockPatterns()
    {
        return $this->blockPatterns;
    }



    public function getDOM()
    {
        return $this->DOM;
    }



    private function tabCb($m)
    {
        return $m[1] . str_repeat(' ', $this->tabWidth - strlen($m[1]) % $this->tabWidth);
    }



    public function free()
    {
        foreach (array_keys(get_object_vars($this)) as $key)
            $this->$key = NULL;
    }



    public function __clone() { throw new Exception("Clone is not supported."); }


    /**#@+
     * Access to undeclared property
     * @throws Exception
     */
    function __get($name) { throw new Exception("Access to undeclared property: " . get_class($this) . "::$$name"); }
    function __set($name, $value) { throw new Exception("Access to undeclared property: " . get_class($this) . "::$$name"); }
    function __unset($name) { throw new Exception("Access to undeclared property: " . get_class($this) . "::$$name"); }
    /**#@-*/

}
