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
    die('Texy! needs PHP version 5');

define('TEXY', 'Version 2.0beta $Revision$');


/**
 * Absolute filesystem path to the Texy package
 */
define('TEXY_DIR',  dirname(__FILE__).'/');

require_once TEXY_DIR.'libs/RegExp.Patterns.php';
require_once TEXY_DIR.'libs/TexyDomElement.php';
require_once TEXY_DIR.'libs/TexyGenericBlock.php';
require_once TEXY_DIR.'libs/TexyHtml.php';
require_once TEXY_DIR.'libs/TexyHtmlFormatter.php';
require_once TEXY_DIR.'libs/TexyHtmlWellForm.php';
require_once TEXY_DIR.'libs/TexyUrl.php';
require_once TEXY_DIR.'libs/TexyModifier.php';
require_once TEXY_DIR.'libs/TexyModule.php';
require_once TEXY_DIR.'libs/TexyParser.php';
require_once TEXY_DIR.'modules/TexyBlockModule.php';
require_once TEXY_DIR.'modules/TexyHeadingModule.php';
require_once TEXY_DIR.'modules/TexyHorizLineModule.php';
require_once TEXY_DIR.'modules/TexyHtmlModule.php';
require_once TEXY_DIR.'modules/TexyImageDescModule.php';
require_once TEXY_DIR.'modules/TexyImageModule.php';
require_once TEXY_DIR.'modules/TexyLinkModule.php';
require_once TEXY_DIR.'modules/TexyListModule.php';
require_once TEXY_DIR.'modules/TexyDefinitionListModule.php';
require_once TEXY_DIR.'modules/TexyLongWordsModule.php';
require_once TEXY_DIR.'modules/TexyPhraseModule.php';
require_once TEXY_DIR.'modules/TexyQuoteModule.php';
require_once TEXY_DIR.'modules/TexyScriptModule.php';
require_once TEXY_DIR.'modules/TexySmiliesModule.php';
require_once TEXY_DIR.'modules/TexyTableModule.php';
require_once TEXY_DIR.'modules/TexyTypographyModule.php';


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

    // types of marks
    const CONTENT_NONE =    1;
    const CONTENT_INLINE =  2;
    const CONTENT_TEXTUAL = 3;
    const CONTENT_BLOCK =   4;

    /** @var bool  use XHTML? */
    static public $xhtml = TRUE;

    /** @var boolean  Do obfuscate e-mail addresses? */
    static public $obfuscateEmail = TRUE;

    /** @var string  input & output text encoding */
    public $encoding = 'utf-8';

    /** @var array  Texy! syntax configuration */
    public $allowed = array();

    /** @var TRUE|FALSE|array  Allowed HTML tags */
    public $allowedTags;

    /** @var TRUE|FALSE|array  Allowed classes */
    public $allowedClasses = Texy::ALL;

    /** @var TRUE|FALSE|array  Allowed inline CSS style */
    public $allowedStyles = Texy::ALL;

    /** @var int  TAB width (for converting tabs to spaces) */
    public $tabWidth = 8;

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

    /** @var TexyModule[]  default modules */
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
        $typographyModule,
        $longWordsModule;

    public
        $genericBlock,
        $formatter,
        $formatterModule, // back compatibility
        $wellForm;


    /**
     * Registered regexps and associated handlers for inline parsing
     * @var array of ('handler' => callback
     *                'pattern' => regular expression
     *                'name'    => pattern's name)
     */
    private $linePatterns = array();

    /**
     * Registered regexps and associated handlers for block parsing
     * @var array of ('handler' => callback
     *                'pattern' => regular expression
     *                'name'    => pattern's name)
     */
    private $blockPatterns = array();


    /** @var TexyDomElement  DOM structure for parsed text */
    private $DOM;

    /** @var TexyModule[]  List of all modules */
    private $modules, $lineModules;

    /** @var array  Texy internal markup */
    private $marks = array();

    /** @var bool  for internal usage */
    public $_mergeMode;

    /** @var array  for internal usage */
    public $_classes, $_styles;





    public function __construct()
    {
        // full support for valid HTML tags by default
        $this->allowedTags = TexyHtml::$valid;

        // load all modules
        $this->loadModules();

        // load routines
        $this->formatter = new TexyHtmlFormatter();
        $this->wellForm = new TexyHtmlWellForm();
        $this->genericBlock = new TexyGenericBlock($this);

        $this->formatterModule = $this->formatter; // back compatibility

        // examples of link reference ;-)
        $mod = new TexyModifier($this);
        $mod->title = 'The best text -> HTML converter and formatter';
        $this->linkModule->addReference('texy', 'http://texy.info/', 'Texy!', $mod);
        $this->linkModule->addReference('google', 'http://www.google.com/search?q=%s');
        $this->linkModule->addReference('wikipedia', 'http://en.wikipedia.org/wiki/Special:Search?search=%s');
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
        $this->linkModule = new TexyLinkModule($this);
        $this->phraseModule = new TexyPhraseModule($this);
        $this->smiliesModule = new TexySmiliesModule($this);

        // block parsing - order is not important
        $this->blockModule = new TexyBlockModule($this);
        $this->headingModule = new TexyHeadingModule($this);
        $this->horizLineModule = new TexyHorizLineModule($this);
        $this->quoteModule = new TexyQuoteModule($this);
        $this->listModule = new TexyListModule($this);
        $this->definitionListModule = new TexyDefinitionListModule($this);
        $this->tableModule = new TexyTableModule($this);
        $this->imageDescModule = new TexyImageDescModule($this);

        // post process - order is not important
        $this->typographyModule = new TexyTypographyModule($this);
        $this->longWordsModule = new TexyLongWordsModule($this);
    }



    public function registerModule($module)
    {
        $this->modules[] = $module;

        if ($module instanceof ITexyLineModule)
            $this->lineModules[] = $module;
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
     * Initialization - called before every use
     */
    protected function init()
    {
        if ($this->handler && !is_object($this->handler))
            throw new Exception('$texy->handler must be object. See documentation.');

        $this->_mergeMode = TRUE;
        $this->marks = array();

        // speed-up
        if (is_array($this->allowedClasses)) $this->_classes = array_flip($this->allowedClasses);
        else $this->_classes = $this->allowedClasses;

        if (is_array($this->allowedStyles)) $this->_styles = array_flip($this->allowedStyles);
        else $this->_styles = $this->allowedStyles;

        // init modules
        $this->linePatterns  = array();
        $this->blockPatterns = array();
        foreach ($this->modules as $module) $module->init();
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
        if ($singleLine)
            $this->parseLine($text);
        else
            $this->parse($text);

        return $this->toHtml();
    }



    /**
     * Converts Texy! document into internal DOM structure ($this->DOM)
     * Before converting it normalize text and call all pre-processing modules
     *
     * @param string
     * @return void
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
     * Converts Texy! single line text into internal DOM structure ($this->DOM)
     * @param string
     * @return void
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

        // parse
        $this->DOM = new TexyTextualElement($this);
        $this->DOM->parse($text);
    }



    /**
     * Converts internal DOM structure to final HTML code
     * @return string
     */
    public function toHtml()
    {
        if (!$this->DOM) throw new Exception('Call $texy->parse() first.');

        // Convert DOM structure to (X)HTML code
        $html = $this->DOM->__toString();

        // replace marks
        $html = strtr($html, $this->marks);

        // BACK COMPATIBILITY HACK !!!
        //$html = strtr($html, array("\xc2\xa0"=>'&#160;',"\xc2\xad"=>'&#173;',"\xe2\x80\x9e"=>'&#8222;',"\xe2\x80\x9c"=>'&#8220;',"\xe2\x80\x9a"=>'&#8218;',"\xe2\x80\x98"=>'&#8216;',"\xe2\x80\xa6"=>'&#8230;',"\xe2\x80\x93"=>'&#8211;'));

        // wellform and reformat HTML
        $html = $this->wellForm->process($html);
        $html = $this->formatter->process($html);

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
            $html = preg_replace_callback('#[\x80-\x{FFFF}]#u', array($this, 'utfconv'), $html);
        }

        return $html;
    }



    /**
     * Converts internal DOM structure to pure Text
     * @return string
     */
    public function toText()
    {
        if (!$this->DOM) throw new Exception('Call $texy->parse() first.');

        $html = $this->DOM->__toString();

        // replace marks
        $html = strtr($html, $this->marks);

        // wellform and reformat HTML
        $html = $this->wellForm->process($html);
        $saveLineWrap = $this->formatter->lineWrap;
        $this->formatter->lineWrap = FALSE;
        $html = $this->formatter->process($html);
        $this->formatter->lineWrap = $saveLineWrap;

        // unfreeze spaces
        $html = Texy::unfreezeSpaces($html);

        // remove tags
        $html = preg_replace('#<(script|style)(.*)</\\1>#Uis', '', $html);
        $html = strip_tags($html);
        $html = preg_replace('#\n\s*\n\s*\n[\n\s]*\n#', "\n\n", $html);

        // entities -> chars
        $html = html_entity_decode($html, ENT_QUOTES, 'UTF-8');

        // convert nbsp to normal space and remove shy
        $html = strtr($html, array(
            "\xC2\xAD" => '',  // shy
            "\xC2\xA0" => ' ', // nbsp
        ));

        if (strcasecmp($this->encoding, 'utf-8') !== 0)
            $html = iconv('utf-8', $this->encoding.'//TRANSLIT', $html);

        return $html;
    }



    /**
     * Switch Texy! configuration to the safe mode
     * Suitable for web comments and other usages, where input text may insert attacker
     */
    public function safeMode()
    {
        $this->allowedClasses = Texy::NONE;                 // no class or ID are allowed
        $this->allowedStyles  = Texy::NONE;                 // style modifiers are disabled
        $this->htmlModule->safeMode();                      // only HTML tags and attributes specified in $safeTags array are allowed
        $this->allowed['Image'] = FALSE;                    // disable images
        $this->allowed['LinkDefinition'] = FALSE;           // disable [ref]: URL  reference definitions
        $this->linkModule->forceNoFollow = TRUE;            // force rel="nofollow"
        $this->mergeLines = FALSE;                          // enter means <BR>
    }



    /**
     * Switch Texy! configuration to the (default) trust mode
     */
    public function trustMode()
    {
        $this->allowedClasses = Texy::ALL;                  // classes and id are allowed
        $this->allowedStyles  = Texy::ALL;                  // inline styles are allowed
        $this->htmlModule->trustMode();                     // full support for HTML tags
        $this->allowed['Image'] = TRUE;                     // enable images
        $this->allowed['LinkDefinition'] = TRUE;            // enable [ref]: URL  reference definitions
        $this->linkModule->forceNoFollow = FALSE;           // disable automatic rel="nofollow"
        $this->mergeLines = TRUE;                           // enter doesn't means <BR>
    }



    /**
     * Translate all white spaces (\t \n \r space) to meta-spaces \x01-\x04
     * which are ignored by TexyHtmlFormatter routine
     * @param string
     * @return string
     */
    static public function freezeSpaces($s)
    {
        return strtr($s, " \t\r\n", "\x01\x02\x03\x04");
    }



    /**
     * Reverts meta-spaces back to normal spaces
     * @param string
     * @return string
     */
    static public function unfreezeSpaces($s)
    {
        return strtr($s, "\x01\x02\x03\x04", " \t\r\n");
    }



    /**
     * Removes special controls characters used by Texy!
     * @param string
     * @return string
     */
    static public function wash($text)
    {
        return preg_replace('#[\x01-\x04\x14-\x1F]+#', '', $text);
    }



    /**
     * Generate unique mark - useful for freezing (folding) some substrings
     * @param string
     * @param int
     * @return string
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

        $this->marks[$key] = $child;

        return $key;
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



    public function getLineModules()
    {
        return $this->lineModules;
    }



    /** @var array */
    static private $charTables;

    /** @var array */
    private $_chars;

    /**
     * Converts UTF-8 to a) HTML entity or b) character in dest encoding
     */
    private function utfconv($m)
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