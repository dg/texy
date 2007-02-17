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

require_once TEXY_DIR.'NHtml.php';
require_once TEXY_DIR.'libs/constants.php';      // regular expressions & other constants
require_once TEXY_DIR.'libs/modifier.php';       // modifier processor
require_once TEXY_DIR.'libs/url.php';            // object encapsulate of URL
require_once TEXY_DIR.'libs/dom.php';            // Texy! DOM element's base class
require_once TEXY_DIR.'libs/module.php';         // Texy! module base class
require_once TEXY_DIR.'libs/parser.php';         // Texy! parser
require_once TEXY_DIR.'libs/html.php';
require_once TEXY_DIR.'libs/html.wellform.php';
require_once TEXY_DIR.'modules/block.php';
require_once TEXY_DIR.'modules/formatter.php';
require_once TEXY_DIR.'modules/generic-block.php';
require_once TEXY_DIR.'modules/heading.php';
require_once TEXY_DIR.'modules/horiz-line.php';
require_once TEXY_DIR.'modules/html-tag.php';
require_once TEXY_DIR.'modules/image.php';
require_once TEXY_DIR.'modules/image-description.php';
require_once TEXY_DIR.'modules/link.php';
require_once TEXY_DIR.'modules/list.php';
require_once TEXY_DIR.'modules/definition-list.php';
require_once TEXY_DIR.'modules/long-words.php';
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

    public $encoding = 'utf-8';
    public $utf = TRUE;

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

    /** @var TexyDom  DOM structure for parsed text */
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
     *                     'user'    => user arguments)
     */
    private $linePatterns = array();

    /**
     * Registered regexps and associated handlers for block parsing
     * @var array Format: ('handler' => callback,
     *                     'pattern' => regular expression,
     *                     'user'    => user arguments)
     */
    private $blockPatterns = array();


    /** @var TexyModule[]  List of all used modules */
    private $modules;

    /**
     * Reference stack
     * @var array Format: ('home' => TexyLinkReference, ...)
     */
    private $references = array();



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



    public function registerLinePattern($module, $method, $pattern, $user_args = NULL)
    {
        $this->linePatterns[] = array(
            'handler'     => array($module, $method),
            'pattern'     => $this->translatePattern($pattern),
            'user'        => $user_args
        );
    }


    public function registerBlockPattern($module, $method, $pattern, $user_args = NULL)
    {
//    if (!preg_match('#(.)\^.*\$\\1[a-z]*#is', $pattern)) die('Texy: Not a block pattern. Module '.get_class($module).', pattern '.htmlSpecialChars($pattern));

        $this->blockPatterns[] = array(
            'handler'     => array($module, $method),
            'pattern'     => $this->translatePattern($pattern)  . 'm',  // force multiline!
            'user'        => $user_args
        );
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


    /**
     * Initialization
     * It is called between constructor and first use (method parse)
     */
    protected function init()
    {
        $this->hashes = array();
        $this->cache = array();
        $this->linePatterns  = array();
        $this->blockPatterns = array();

        if (!$this->modules) die('Texy: No modules installed');

        // init modules
        foreach ($this->modules as $module)
            $module->init();
    }



    /**
     * Convert Texy! document in (X)HTML code
     * This is shortcut for parse() & DOM->toHtml()
     * @return string
     */
    public function process($text, $singleLine = FALSE)
    {
        if (strcasecmp($this->encoding, 'utf-8') !== 0)
            $text = iconv($this->encoding, 'utf-8', $text);

        if ($singleLine)
            $this->parseLine($text);
        else
            $this->parse($text);


        $html = $this->DOM->__toString();

        if (strcasecmp($this->encoding, 'utf-8') !== 0) {
            $html = mb_convert_encoding($html, 'HTML-ENTITIES', "UTF-8");

            /*
            foreach ($i=128; $i<256; $i++) {
                $s = iconv($this->encoding, 'UCS-2', chr($i));
            }
            */
        }

        return $html;
 }






    /**
     * Convert Texy! document into internal DOM structure ($this->DOM)
     * Before converting it normalize text and call all pre-processing modules
     */
    public function parse($text)
    {
            // initialization
        $this->init();

            ///////////   PROCESS
        $this->DOM = new TexyDom($this);
        $this->DOM->parse($text);
    }





    /**
     * Convert Texy! single line text into internal DOM structure ($this->DOM)
     */
    public function parseLine($text)
    {
            // initialization
        $this->init();

            ///////////   PROCESS
        $this->DOM = new TexyDomLine($this);
        $this->DOM->parse($text);
    }





    /**
     * Convert internal DOM structure ($this->DOM) to pure Text
     * @return string
     */
    public function toText()
    {
        // generate output
        $saveLineWrap = $this->formatterModule->lineWrap;
        $this->formatterModule->lineWrap = FALSE;

        $text = $this->DOM->__toString();

        $this->formatterModule->lineWrap = $saveLineWrap;

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
            $text = iconv('utf-8', $this->encoding, $text);

        return $text;
    }






    /**
     * Switch Texy and default modules to safe mode
     * Suitable for 'comments' and other usages, where input text may insert attacker
     */
    public function safeMode()
    {
        $this->allowedClasses = Texy::NONE;                  // no class or ID are allowed
        $this->allowedStyles  = Texy::NONE;                  // style modifiers are disabled
        $this->htmlModule->safeMode();                      // only HTML tags and attributes specified in $safeTags array are allowed
        $this->allowed['Image.normal'] = FALSE;                // disable images
        $this->linkModule->forceNoFollow = TRUE;            // force rel="nofollow"
    }




    /**
     * Switch Texy and default modules to (default) trust mode
     */
    public function trustMode()
    {
        $this->allowedClasses = Texy::ALL;                   // classes and id are allowed
        $this->allowedStyles  = Texy::ALL;                   // inline styles are allowed
        $this->htmlModule->trustMode();                     // full support for HTML tags
        $this->allowed['Image.normal'] = TRUE;                 // enable images
        $this->linkModule->forceNoFollow = FALSE;           // disable automatic rel="nofollow"
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



    /**
     * For easier regular expression writing
     * @return string
     */
    private $cache;
    public function translatePattern($re)
    {
        if (isset($this->cache[$re])) return $this->cache[$re];

        return $this->cache[$re] = strtr($re, array(
            '<MODIFIER_HV>' => TEXY_PATTERN_MODIFIER_HV,
            '<MODIFIER_H>'  => TEXY_PATTERN_MODIFIER_H,
            '<MODIFIER>'    => TEXY_PATTERN_MODIFIER,
            '<LINK>'        => TEXY_PATTERN_LINK,
            ':CHAR:'        => TEXY_CHAR_UTF,
            ':HASH:'        => TEXY_HASH,
            ':HASH_N:'      => TEXY_HASH_N,
        ));
    }



    public function getModules()
    {
        return $this->modules;
    }


    /**
     * experimental
     */
    public function free()
    {
        foreach (array_keys(get_object_vars($this)) as $key)
            $this->$key = NULL;
    }


    public function handle($class, $el)
    {
    }

    private $hashes = array();

    /**
     * Generate unique HASH key - useful for freezing (folding) some substrings
     * @return string
     * @static
     */
    public function hashKey($child, $contentType)
    {
        static $borders = array(
            TexyDomElement::CONTENT_NONE => "\x14",
            TexyDomElement::CONTENT_INLINE => "\x15",
            TexyDomElement::CONTENT_TEXTUAL => "\x16",
            TexyDomElement::CONTENT_BLOCK => "\x17",
        );
        $key = $borders[$contentType]
            . strtr(base_convert(count($this->hashes), 10, 8), '01234567', "\x18\x19\x1A\x1B\x1C\x1D\x1E\x1F")
            . $borders[$contentType];

        $this->hashes[$key] = $child->__toString();

        return $key;
    }


    public function hashReplace($text)
    {
        return strtr($text, $this->hashes);
    }



    /**
     * Undefined property usage prevention
     */
    function __get($nm) { throw new Exception("Undefined property '" . get_class($this) . "::$$nm'"); }
    function __set($nm, $val) { $this->__get($nm); }
    private function __unset($nm) { $this->__get($nm); }
    private function __isset($nm) { $this->__get($nm); }

} // Texy