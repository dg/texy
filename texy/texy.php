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
require_once TEXY_DIR.'libs/TexyGenericBlock.php';
require_once TEXY_DIR.'libs/TexyHtml.php';
require_once TEXY_DIR.'libs/TexyHtmlFormatter.php';
require_once TEXY_DIR.'libs/TexyHtmlWellForm.php';
require_once TEXY_DIR.'libs/TexyModifier.php';
require_once TEXY_DIR.'libs/TexyModule.php';
require_once TEXY_DIR.'libs/TexyParser.php';
require_once TEXY_DIR.'modules/TexyBlockModule.php';
require_once TEXY_DIR.'modules/TexyHeadingModule.php';
require_once TEXY_DIR.'modules/TexyHorizLineModule.php';
require_once TEXY_DIR.'modules/TexyHtmlModule.php';
require_once TEXY_DIR.'modules/TexyFigureModule.php';
require_once TEXY_DIR.'modules/TexyImageModule.php';
require_once TEXY_DIR.'modules/TexyLinkModule.php';
require_once TEXY_DIR.'modules/TexyListModule.php';
require_once TEXY_DIR.'modules/TexyDefinitionListModule.php';
require_once TEXY_DIR.'modules/TexyLongWordsModule.php';
require_once TEXY_DIR.'modules/TexyPhraseModule.php';
require_once TEXY_DIR.'modules/TexyQuoteModule.php';
require_once TEXY_DIR.'modules/TexyScriptModule.php';
require_once TEXY_DIR.'modules/TexyEmoticonModule.php';
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

    // types of protection marks
    const CONTENT_NONE =    "\x17";
    const CONTENT_INLINE =  "\x16";
    const CONTENT_TEXTUAL = "\x15";
    const CONTENT_BLOCK =   "\x14";

    /** @var bool  use XHTML? */
    static public $xhtml = TRUE;

    /** @var string  input & output text encoding */
    public $encoding = 'utf-8';

    /** @var array  Texy! syntax configuration */
    public $allowed = array();

    /** @var TRUE|FALSE|array  Allowed HTML tags */
    public $allowedTags;

    /** @var TRUE|FALSE|array  Allowed classes */
    public $allowedClasses;

    /** @var TRUE|FALSE|array  Allowed inline CSS style */
    public $allowedStyles;

    /** @var boolean  Do obfuscate e-mail addresses? */
    public $obfuscateEmail = TRUE;

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
        $emoticonModule,
        $blockModule,
        $headingModule,
        $horizLineModule,
        $quoteModule,
        $listModule,
        $definitionListModule,
        $tableModule,
        $figureModule,
        $typographyModule,
        $longWordsModule;

    public
        $genericBlock,
        $formatter,
        $formatterModule, // back compatibility
        $wellForm;


    /**
     * HTML tags definitions
     * notice: I use a little trick - isset($array[$item]) is much faster than in_array($item, $array)
     */
    static public $blockTags = array(
        'address'=>1,'blockquote'=>1,'caption'=>1,'col'=>1,'colgroup'=>1,'dd'=>1,'div'=>1,'dl'=>1,'dt'=>1,
        'fieldset'=>1,'form'=>1,'h1'=>1,'h2'=>1,'h3'=>1,'h4'=>1,'h5'=>1,'h6'=>1,'hr'=>1,'iframe'=>1,'legend'=>1,
        'li'=>1,'object'=>1,'ol'=>1,'p'=>1,'param'=>1,'pre'=>1,'table'=>1,'tbody'=>1,'td'=>1,'tfoot'=>1,
        'th'=>1,'thead'=>1,'tr'=>1,'ul'=>1,/*'embed'=>1,*/);
    // todo: iframe, object, are block?

    static public $inlineTags = array(
        'a'=>1,'abbr'=>1,'acronym'=>1,'area'=>1,'b'=>1,'big'=>1,'br'=>1,'button'=>1,'cite'=>1,'code'=>1,
        'del'=>1,'dfn'=>1,'em'=>1,'i'=>1,'img'=>1,'input'=>1,'ins'=>1,'kbd'=>1,'label'=>1,'map'=>1,'noscript'=>1,
        'optgroup'=>1,'option'=>1,'q'=>1,'samp'=>1,'script'=>1,'select'=>1,'small'=>1,'span'=>1,'strong'=>1,
        'sub'=>1,'sup'=>1,'textarea'=>1,'tt'=>1,'var'=>1,);

    static public $inlineCont = array(
        'br'=>1,'button'=>1,'iframe'=>1,'img'=>1,'input'=>1,'object'=>1,'script'=>1,'select'=>1,'textarea'=>1,
        'applet'=>1,'isindex'=>1,);
    // todo: use applet, isindex?

    static public $emptyTags = array('img'=>1,'hr'=>1,'br'=>1,'input'=>1,'meta'=>1,'area'=>1,'base'=>1,'col'=>1,
        'link'=>1,'param'=>1,);

    //static public $metaTag = array('html'=>1,'head'=>1,'body'=>1,'base'=>1,'meta'=>1,'link'=>1,'title'=>1,);


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
    private $modules;

    /** @var array  Texy protect markup table */
    private $marks = array();

    /** @var bool  how split paragraphs (internal usage) */
    public $_paragraphMode;

    /** @var array  for internal usage */
    public $_classes, $_styles;





    public function __construct()
    {
        // load all modules
        $this->loadModules();

        // load routines
        $this->formatter = new TexyHtmlFormatter();
        $this->wellForm = new TexyHtmlWellForm();
        $this->genericBlock = new TexyGenericBlock($this);

        $this->formatterModule = $this->formatter; // back compatibility

        // default configuration
        $this->trustMode();

        // examples of link reference ;-)
        $mod = new TexyModifier;
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
        $this->emoticonModule = new TexyEmoticonModule($this);

        // block parsing - order is not important
        $this->blockModule = new TexyBlockModule($this);
        $this->headingModule = new TexyHeadingModule($this);
        $this->horizLineModule = new TexyHorizLineModule($this);
        $this->quoteModule = new TexyQuoteModule($this);
        $this->listModule = new TexyListModule($this);
        $this->definitionListModule = new TexyDefinitionListModule($this);
        $this->tableModule = new TexyTableModule($this);
        $this->figureModule = new TexyFigureModule($this);

        // post process - order is not important
        $this->typographyModule = new TexyTypographyModule($this);
        $this->longWordsModule = new TexyLongWordsModule($this);
    }



    public function registerModule($module)
    {
        $this->modules[] = $module;
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
        if (empty($this->allowed[$name])) return;

        // if (!preg_match('#(.)\^.*\$\\1[a-z]*#is', $pattern)) die('Texy: Not a block pattern. Module '.get_class($module).', pattern '.htmlSpecialChars($pattern));

        $this->blockPatterns[$name] = array(
            'handler'     => $handler,
            'pattern'     => $pattern  . 'm',  // force multiline!
        );
    }



    /**
     * Initialization - called before every use
     */
    protected function init()
    {
        if ($this->handler && !is_object($this->handler))
            throw new Exception('$texy->handler must be object. See documentation.');

        $this->_paragraphMode = TRUE;
        $this->marks = array();

        // speed-up
        if (is_array($this->allowedClasses)) $this->_classes = array_flip($this->allowedClasses);
        else $this->_classes = $this->allowedClasses;

        if (is_array($this->allowedStyles)) $this->_styles = array_flip($this->allowedStyles);
        else $this->_styles = $this->allowedStyles;

        // init modules
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
        $text = self::wash($text);

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
        $this->DOM = TexyHtml::el();
        $this->DOM->parseBlock($this, $text);

        // clean-up
        $this->linePatterns = $this->blockPatterns = array();
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
        $text = self::wash($text);
        $text = rtrim(strtr($text, array("\n" => ' ', "\r" => '')));

        // parse
        $this->DOM = TexyHtml::el();
        $this->DOM->parseLine($this, $text);

        // clean-up
        $this->linePatterns = $this->blockPatterns = array();
    }



    /**
     * Converts internal DOM structure to final HTML code
     * @return string
     */
    public function toHtml()
    {
        // Convert DOM structure to (X)HTML code
        if (!$this->DOM) throw new Exception('Call $texy->parse() first.');
        $html = $this->export($this->DOM);

        // this notice should remain!
        if (!defined('TEXY_NOTICE_SHOWED')) {
            $html .= "\n<!-- by Texy2! -->";
            define('TEXY_NOTICE_SHOWED', TRUE);
        }

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
        // Convert DOM structure to (X)HTML code
        if (!$this->DOM) throw new Exception('Call $texy->parse() first.');
        $save = $this->formatter->lineWrap;
        $this->formatter->lineWrap = FALSE;
        $html = $this->export($this->DOM);
        $this->formatter->lineWrap = $save;

        // remove tags
        $html = preg_replace('#<(script|style)(.*)</\\1>#Uis', '', $html);
        $html = strip_tags($html);
        $html = preg_replace('#\n\s*\n\s*\n[\n\s]*\n#', "\n\n", $html);

        // entities -> chars
        $html = Texy::decode($html);

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
     * Converts internal DOM structure to final HTML code in UTF-8
     * @return string
     */
    public function export($el)
    {
        $s = $el->export($this);

        // decode HTML entities to UTF-8
        $s = self::decode($s);

        // postprocessing
        $blocks = explode(self::CONTENT_BLOCK, $s);
        foreach ($this->modules as $module) {
            if ($module instanceof ITexyLineModule) {
                foreach ($blocks as $n => $s) {
                    if ($n % 2 === 0 && $s !== '')
                        $blocks[$n] = $module->linePostProcess($s);
                }
            }
        }
        $s = implode(self::CONTENT_BLOCK, $blocks);

        // encode < > &
        $s = self::encode($s);

        // replace protected marks
        $s = $this->unProtect($s);

        // wellform and reformat HTML
        $s = $this->wellForm->process($s);
        $s = $this->formatter->process($s);

        // remove HTML 4.01 optional tags
        if (!self::$xhtml)
            $html = preg_replace('#\\s*</(colgroup|dd|dt|li|option|p|td|tfoot|th|thead|tr)>#', '', $html);

        // unfreeze spaces
        $s = self::unfreezeSpaces($s);

        return $s;
    }



    /**
     * Switch Texy! configuration to the safe mode
     * Suitable for web comments and other usages, where input text may insert attacker
     */
    public function safeMode()
    {
        $this->allowedClasses = self::NONE;                 // no class or ID are allowed
        $this->allowedStyles  = self::NONE;                 // style modifiers are disabled
        $this->allowedTags = array(                         // only some "safe" HTML tags and attributes are allowed
            'a'         => array('href'),
            'acronym'   => array('title'),
            'b'         => array(),
            'br'        => array(),
            'cite'      => array(),
            'code'      => array(),
            'em'        => array(),
            'i'         => array(),
            'strong'    => array(),
            'sub'       => array(),
            'sup'       => array(),
            'q'         => array(),
            'small'     => array(),
        );
        $this->allowed['image'] = FALSE;                    // disable images
        $this->allowed['linkDefinition'] = FALSE;           // disable [ref]: URL  reference definitions
        $this->linkModule->forceNoFollow = TRUE;            // force rel="nofollow"
        //$this->mergeLines = FALSE;                          // enter means <BR>
    }



    /**
     * Switch Texy! configuration to the (default) trust mode
     */
    public function trustMode()
    {
        $this->allowedClasses = self::ALL;                  // classes and id are allowed
        $this->allowedStyles  = self::ALL;                  // inline styles are allowed
        $this->allowedTags = array_merge(self::$blockTags, self::$inlineTags); // full support for valid HTML tags
        $this->allowed['image'] = TRUE;                     // enable images
        $this->allowed['linkDefinition'] = TRUE;            // enable [ref]: URL  reference definitions
        $this->linkModule->forceNoFollow = FALSE;           // disable automatic rel="nofollow"
        //$this->mergeLines = TRUE;                           // enter doesn't means <BR>
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
    static public function wash($s)
    {
        return preg_replace('#[\x01-\x04\x14-\x1F]+#', '', $s);
    }



    /**
     * Texy! version of htmlSpecialChars (much faster than htmlSpecialChars!)
     * @param string
     * @return string
     */
    static public function encode($s)
    {
        return str_replace(array('&', '<', '>'), array('&amp;', '&lt;', '&gt;'), $s);
    }


    /**
     * Texy! version of html_entity_decode (always UTF-8, much faster than original!)
     * @param string
     * @return string
     */
    static public function decode($s)
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
    public function protect($child, $contentType=self::CONTENT_BLOCK)
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



    static public function completeURL($URL, $root, &$isAbsolute=NULL)
    {
        if (preg_match('#^(https?://|ftp://|www\\.|ftp\\.|/)#i', $URL)) {
            // absolute URL
            $isAbsolute = TRUE;
            $URL = str_replace('&amp;', '&', $URL); // replace unwanted &amp;
            if (strncasecmp($URL, 'www.', 4) === 0) return 'http://' . $URL;
            elseif (strncasecmp($URL, 'ftp.', 4) === 0) return 'ftp://' . $URL;
            return $URL;
        }

        // relative
        $isAbsolute = FALSE;
        if ($root == NULL) return $URL;
        return rtrim($root, '/\\') . '/' . $URL;
    }



    static public function completePath($path, $root)
    {
        if (preg_match('#^(https?://|ftp://|www\\.|ftp\\.|/)#i', $path)) return FALSE;
        if (strpos($path, '..')) return FALSE;
        if ($root == NULL) return $path;
        return rtrim($root, '/\\') . '/' . $path;
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

} // Texy