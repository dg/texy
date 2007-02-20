<?php

/**
 * Texy! universal text -> html converter
 * --------------------------------------
 *
 * This source file is subject to the GNU GPL license.
 *
 * @author     David Grudl aka -dgx- <dave@dgx.cz>
 * @link       http://texy.info/
 * @copyright  Copyright (c) 2004-2007 David Grudl
 * @license    GNU GENERAL PUBLIC LICENSE v2
 * @package    Texy
 * @category   Text
 * @version    2.0beta for PHP5 $Revision$ $Date$
 */


if (version_compare(PHP_VERSION , '5.0.0', '<'))
    die('Texy!: too old version of PHP!');

define('TEXY', 'Version 2.0beta for PHP5 $Revision$');

/**
 * Absolute filesystem path to the Texy package
 */
define('TEXY_DIR',  dirname(__FILE__).'/');

require_once TEXY_DIR.'libs/re.patterns.php';    // regular expressions
require_once TEXY_DIR.'libs/modifier.php';       // modifier processor
require_once TEXY_DIR.'libs/url.php';           // object encapsulate of URL
require_once TEXY_DIR.'libs/dom.php';            // Texy! DOM element's base class
require_once TEXY_DIR.'libs/module.php';         // Texy! module base class
require_once TEXY_DIR.'libs/parser.php';         // Texy! parser
require_once TEXY_DIR.'libs/html.php';
require_once TEXY_DIR.'libs/html.wellform.php';
require_once TEXY_DIR.'modules/block.php';
require_once TEXY_DIR.'modules/formatter.php';
require_once TEXY_DIR.'modules/generic.block.php';
require_once TEXY_DIR.'modules/heading.php';
require_once TEXY_DIR.'modules/horiz.line.php';
require_once TEXY_DIR.'modules/html.tag.php';
require_once TEXY_DIR.'modules/image.php';
require_once TEXY_DIR.'modules/image.description.php';
require_once TEXY_DIR.'modules/link.php';
require_once TEXY_DIR.'modules/list.php';
require_once TEXY_DIR.'modules/definition.list.php';
require_once TEXY_DIR.'modules/long.words.php';
require_once TEXY_DIR.'modules/phrase.php';
require_once TEXY_DIR.'modules/typography.php';
require_once TEXY_DIR.'modules/quote.php';
require_once TEXY_DIR.'modules/script.php';
require_once TEXY_DIR.'modules/table.php';
require_once TEXY_DIR.'modules/smilies.php';


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

    const CONTENT_NONE =    1;
    const CONTENT_INLINE =  2;
    const CONTENT_TEXTUAL = 3;
    const CONTENT_BLOCK =   4;

    /** @var bool use XHTML? */
    static public $xhtml = TRUE;

    public $encoding = 'utf-8';

    /** @var int  TAB width (for converting tabs to spaces) */
    public $tabWidth = 8;

    /** @var TRUE|FALSE|array  Allowed classes */
    public $allowedClasses = Texy::ALL;

    /** @var TRUE|FALSE|array  Allowed inline CSS style */
    public $allowedStyles = Texy::ALL;

    /** @var TRUE|FALSE|array  Allowed HTML tags */
    public $allowedTags;

    public $allowed = array();

    /** @var boolean  Do obfuscate e-mail addresses? */
    public $obfuscateEmail = TRUE;

    /** @var TexyDomElement  DOM structure for parsed text */
    private $DOM;

    /** @var array  Parsing summary */
    public $summary;

    /** @var string  Generated stylesheet */
    public $styleSheet = '';

    /** @var bool  Merge lines mode */
    public $mergeLines = TRUE;


    public $referenceHandler;

    /** @var TexyModule Default modules */
    public
        $scriptModule,
        $htmlModule,
        $imageModule,
        $linkModule,
        $phraseModule,
        $smiliesModule,
        $blockModule,
        $headingModule,
        $horizLineModule,
        $quoteModule,
        $listModule,
        $definitionListModule,
        $tableModule,
        $imageDescModule,
        $genericBlockModule,
        $typographyModule,
        $longWordsModule,
        $formatterModule;




    /**
     * Registered regexps and associated handlers for inline parsing
     * @var array Format: ('handler' => callback,
     *                     'pattern' => regular expression,
     *                     'name'    => pattern's name)
     */
    private $linePatterns = array();

    /**
     * Registered regexps and associated handlers for block parsing
     * @var array Format: ('handler' => callback,
     *                     'pattern' => regular expression,
     *                     'name'    => pattern's name)
     */
    private $blockPatterns = array();


    /** @var TexyModule[]  List of all used modules */
    private $modules;

    /**
     * Reference stack
     * @var array Format: ('home' => TexyLinkReference, ...)
     */
    private $references = array();

    private $marks = array();



    public function __construct()
    {
        // init some other variables
        $this->summary['images']  = array();
        $this->summary['links']   = array();
        $this->summary['preload'] = array();

        $this->allowedTags = TexyHtml::$valid; // full support for HTML tags

        // load all modules
        $this->loadModules();

        // example of link reference ;-)
        /*
        $elRef = new TexyLinkReference($this, 'http://texy.info/', 'Texy!');
        $elRef->modifier->title = 'Text to HTML converter and formatter';
        $this->addReference('Texy', $elRef);

        $elRef = new TexyLinkReference($this, 'http://www.google.com/search?q=%s');
        $this->addReference('google', $elRef);

        $elRef = new TexyLinkReference($this, 'http://en.wikipedia.org/wiki/Special:Search?search=%s');
        $this->addReference('wikipedia', $elRef);
        */
    }




    /**
     * Create array of all used modules ($this->modules)
     * This array can be changed by overriding this method (by subclasses)
     * or directly in main code
     */
    protected function loadModules()
    {
        // Line parsing - order is not much important
        $this->scriptModule = new TexyScriptModule($this);
        $this->htmlModule = new TexyHtmlModule($this);
        $this->imageModule = new TexyImageModule($this);
        $this->linkModule = new TexyLinkModule($this);
        $this->phraseModule = new TexyPhraseModule($this);
        $this->smiliesModule = new TexySmiliesModule($this);

        // block parsing - order is not much important
        $this->blockModule = new TexyBlockModule($this);
        $this->headingModule = new TexyHeadingModule($this);
        $this->horizLineModule = new TexyHorizLineModule($this);
        $this->quoteModule = new TexyQuoteModule($this);
        $this->listModule = new TexyListModule($this);
        $this->definitionListModule = new TexyDefinitionListModule($this);
        $this->tableModule = new TexyTableModule($this);
        $this->imageDescModule = new TexyImageDescModule($this);
        $this->genericBlockModule = new TexyGenericBlockModule($this);

        // post process
        $this->typographyModule = new TexyTypographyModule($this);
        $this->longWordsModule = new TexyLongWordsModule($this);
        $this->formatterModule = new TexyFormatterModule($this);
    }



    public function registerModule($module)
    {
        $this->modules[] = $module;
    }



    public function registerLinePattern($module, $method, $pattern, $name)
    {
        if (empty($this->allowed[$name])) return;

        $this->linePatterns[] = array(
            'handler'     => array($module, $method),
            'pattern'     => $pattern,
            'name'        => $name
        );
    }


    public function registerBlockPattern($module, $method, $pattern, $name)
    {
        if (empty($this->allowed[$name])) return;

        // if (!preg_match('#(.)\^.*\$\\1[a-z]*#is', $pattern)) die('Texy: Not a block pattern. Module '.get_class($module).', pattern '.htmlSpecialChars($pattern));

        $this->blockPatterns[] = array(
            'handler'     => array($module, $method),
            'pattern'     => $pattern  . 'm',  // force multiline!
            'name'        => $name
        );
    }



    /**
     * Initialization
     * It is called between constructor and first use (method parse)
     */
    protected function init()
    {
        $this->marks = array();
        $this->linePatterns  = array();
        $this->blockPatterns = array();

        if (!$this->modules) die('Texy: No modules installed');

        // init modules
        foreach ($this->modules as $module) $module->init();
    }



    /**
     * Convert Texy! document in (X)HTML code
     * This is shortcut for parse() & DOM->toHtml()
     * @return string
     */
    public function process($text, $singleLine = FALSE)
    {
        if ($singleLine)
            $this->parseLine($text);
        else
            $this->parse($text);

        return $this->toHtml();
    }



    /**
     * Convert Texy! document into internal DOM structure ($this->DOM)
     * Before converting it normalize text and call all pre-processing modules
     */
    public function parse($text)
    {
         // initialization
        $this->init();

        // convert to UTF-8
        if (strcasecmp($this->encoding, 'utf-8') !== 0)
            $text = iconv($this->encoding, 'utf-8', $text);

        // remove special chars, normalize lines
        $text = Texy::wash($text);

        // standardize line endings to unix-like  (dos, mac)
        $text = str_replace("\r\n", "\n", $text); // DOS
        $text = strtr($text, "\r", "\n"); // Mac

        // replace tabs with spaces
        while (strpos($text, "\t") !== FALSE)
            $text = preg_replace_callback('#^(.*)\t#mU',
                       create_function('$matches', "return \$matches[1] . str_repeat(' ', $this->tabWidth - strlen(\$matches[1]) % $this->tabWidth);"),
                       $text);

        // remove texy! comments
        $text = preg_replace('#\xC2\xA7{2,}(?!\xC2\xA7).*(\xC2\xA7{2,}|$)(?!\xC2\xA7)#mU', '', $text);

        // right trim
        $text = preg_replace("#[\t ]+$#m", '', $text); // right trim

        // pre-processing
        foreach ($this->modules as $module)
            $text = $module->preProcess($text);

        // process
        $this->DOM = new TexyBlockElement($this);
        $this->DOM->parse($text);
    }




    /**
     * Convert Texy! single line text into internal DOM structure ($this->DOM)
     */
    public function parseLine($text)
    {
        // initialization
        $this->init();

        // convert to UTF-8
        if (strcasecmp($this->encoding, 'utf-8') !== 0)
            $text = iconv($this->encoding, 'utf-8', $text);

        // remove special chars and line endings
        $text = Texy::wash($text);
        $text = rtrim(strtr($text, array("\n" => ' ', "\r" => '')));

            ///////////   PROCESS
        $this->DOM = new TexyTextualElement($this);
        $this->DOM->parse($text);
    }



    public function toHtml()
    {
        if (!$this->DOM) throw new Exception('Call $texy->parse() first.');

        // Convert DOM structure to (X)HTML code
        $html = $this->DOM->__toString();

        // replace marks
        $html = strtr($html, $this->marks);

        // wellform HTML
        $wf = new TexyHtmlWellForm();
        $html = $wf->process($html);

        // post-process
        foreach ($this->modules as $module)
            $html = $module->postProcess($html);  // existuje pouze formatovani

        // this notice should remain!
        if (!defined('TEXY_NOTICE_SHOWED')) {
            $html .= "\n<!-- generated by Texy! -->";
            define('TEXY_NOTICE_SHOWED', TRUE);
        }

        // unfreeze spaces
        $html = Texy::unfreezeSpaces($html);

        // convert from UTF-8
        if (strcasecmp($this->encoding, 'utf-8') !== 0)
        {
            // prepare UTF-8 -> charset table
            $this->_chars = & self::$charTables[strtolower($this->encoding)];
            if (!$this->_chars) {
                for ($i=128; $i<256; $i++) {
                    $ch = iconv($this->encoding, 'UTF-8//IGNORE', chr($i));
                    if ($ch) $this->_chars[$ch] = chr($i);
                }
            }

            // convert
            $html = preg_replace_callback('#[\x80-\x{FFFF}]#u', array($this, 'iconv'), $html);
        }

        return $html;
    }




    /**
     * Convert internal DOM structure ($this->DOM) to pure Text
     * @return string
     */
    public function toText()
    {
        if (!$this->DOM) throw new Exception('Call $texy->parse() first.');

        // generate output
        $saveLineWrap = $this->formatterModule->lineWrap;
        $this->formatterModule->lineWrap = FALSE;

        $text = $this->DOM->__toString();

        // replace marks
        $text = strtr($text, $this->marks);

        // wellform HTML
        $wf = new TexyHtmlWellForm();
        $text = $wf->process($text);

        // post-process
        foreach ($this->modules as $module) $text = $module->postProcess($text);  // existuje pouze formatovani
        $this->formatterModule->lineWrap = $saveLineWrap;

        // unfreeze spaces
        $text = Texy::unfreezeSpaces($text);

        // remove tags
        $text = preg_replace('#<(script|style)(.*)</\\1>#Uis', '', $text);
        $text = strip_tags($text);
        $text = preg_replace('#\n\s*\n\s*\n[\n\s]*\n#', "\n\n", $text);

        // entities -> chars
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');

        // convert nbsp to normal space and remove shy
        $text = strtr($text, array(
            "\xC2\xAD" => '',  // shy
            "\xC2\xA0" => ' ', // nbsp
        ));

        if (strcasecmp($this->encoding, 'utf-8') !== 0)
            $text = iconv('utf-8', $this->encoding.'//TRANSLIT', $text);

        return $text;
    }



    public function handle($class, $el)
    {
    }



    /**
     * Switch Texy and default modules to safe mode
     * Suitable for 'comments' and other usages, where input text may insert attacker
     */
    public function safeMode()
    {
        $this->allowedClasses = Texy::NONE;                 // no class or ID are allowed
        $this->allowedStyles  = Texy::NONE;                 // style modifiers are disabled
        $this->htmlModule->safeMode();                      // only HTML tags and attributes specified in $safeTags array are allowed
        $this->allowed['Image.normal'] = FALSE;             // disable images
        $this->allowed['Link.definition'] = FALSE;          // disable [ref]: URL  reference definitions
        $this->linkModule->forceNoFollow = TRUE;            // force rel="nofollow"
        $this->mergeLines = FALSE;                          // enter means <BR>
    }




    /**
     * Switch Texy and default modules to (default) trust mode
     */
    public function trustMode()
    {
        $this->allowedClasses = Texy::ALL;                  // classes and id are allowed
        $this->allowedStyles  = Texy::ALL;                  // inline styles are allowed
        $this->htmlModule->trustMode();                     // full support for HTML tags
        $this->allowed['Image.normal'] = TRUE;              // enable images
        $this->allowed['Link.definition'] = TRUE;           // enable [ref]: URL  reference definitions
        $this->linkModule->forceNoFollow = FALSE;           // disable automatic rel="nofollow"
        $this->mergeLines = TRUE;                           // enter doesn't means <BR>
    }







    /**
     * Translate all white spaces (\t \n \r space) to meta-spaces \x01-\x04
     * which are ignored by some formatting functions
     * @return string
     * @static
     */
    static public function freezeSpaces($s)
    {
        return strtr($s, " \t\r\n", "\x01\x02\x03\x04");
    }


    /**
     * Revert meta-spaces back to normal spaces
     * @return string
     * @static
     */
    static public function unfreezeSpaces($s)
    {
        return strtr($s, "\x01\x02\x03\x04", " \t\r\n");
    }



    /**
     * remove special controls chars used by Texy!
     * @return string
     * @static
     */
    static public function wash($text)
    {
        return preg_replace('#[\x01-\x04\x14-\x1F]+#', '', $text);
    }




    /**
     * Generate unique MARK key - useful for freezing (folding) some substrings
     * @return string
     * @static
     */
    public function mark($child, $contentType)
    {
        static $borders = array(
            Texy::CONTENT_NONE => "\x14",
            Texy::CONTENT_INLINE => "\x15",
            Texy::CONTENT_TEXTUAL => "\x16",
            Texy::CONTENT_BLOCK => "\x17",
        );

        $key = $borders[$contentType]
            . strtr(base_convert(count($this->marks), 10, 8), '01234567', "\x18\x19\x1A\x1B\x1C\x1D\x1E\x1F")
            . $borders[$contentType];

        if (is_object($child)) $this->marks[$key] = $child->__toString();
        else $this->marks[$key] = $child;

        return $key;
    }






    /**
     * Add new named reference
     */
    public function addReference($name, $obj)
    {
        $name = strtolower($name); // pozor na UTF8 !
        $this->references[$name] = $obj;
    }




    /**
     * Receive new named link. If not exists, try
     * call user function to create one.
     */
    function getReference($name)
    {
        $lowName = strtolower($name); // pozor na UTF8 !

        if (isset($this->references[$lowName]))
            return $this->references[$lowName];


        if ($this->referenceHandler)
            return call_user_func_array($this->referenceHandler, array($name, $this));

        return FALSE;
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


    public function getModules()
    {
        return $this->modules;
    }




    static private $charTables;
    private $_chars;

    /**
     * Converts from UTF-8 to HTML entity or character in dest encoding
     */
    private function iconv($m)
    {
        $m = $m[0];
        if (isset($this->_chars[$m])) return $this->_chars[$m];

        $ch1 = ord($m[0]);
        $ch2 = ord($m[1]);
        if (($ch2 >> 6) !== 2) return '';

        if (($ch1 & 0xE0) === 0xC0)
            return '&#' . ((($ch1 & 0x1F) << 6) + ($ch2 & 0x3F)) . ';';

        if (($ch1 & 0xF0) === 0xE0) {
            $ch3 = ord($m[2]);
            if (($ch3 >> 6) !== 2) return '';
            return '&#' . ((($ch1 & 0xF) << 12) + (($ch2 & 0x3F) << 06) + (($ch3 & 0x3F))) . ';';
        }

        return '';
    }


    /**
     * experimental
     */
    public function free()
    {
        foreach (array_keys(get_object_vars($this)) as $key)
            $this->$key = NULL;
    }


    /**
     * Undefined property usage prevention
     */
    function __get($nm) { throw new Exception("Undefined property '" . get_class($this) . "::$$nm'"); }
    function __set($nm, $val) { $this->__get($nm); }
    private function __unset($nm) { $this->__get($nm); }
    private function __isset($nm) { $this->__get($nm); }

} // Texy