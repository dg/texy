<?php

/**
 * Texy! universal text -> html converter
 * --------------------------------------
 *
 * This source file is subject to the GNU GPL license.
 *
 * @author     David Grudl aka -dgx- <dave@dgx.cz>
 * @link       http://www.texy.info/
 * @copyright  Copyright (c) 2004-2006 David Grudl
 * @license    GNU GENERAL PUBLIC LICENSE
 * @package    Texy
 * @category   Text
 * @version    1.2 for PHP4 & PHP5 (released 2006/06/01)
 */


if (version_compare(PHP_VERSION , '4.3.3', '<'))
    die('Texy!: too old version of PHP!');

define('TEXY', 'Version 1.2 (c) David Grudl, http://www.texy.info');

/**
 * Absolute filesystem path to the Texy package
 */
define('TEXY_DIR',  dirname(__FILE__).'/');

require_once TEXY_DIR.'libs/constants.php';      // regular expressions & other constants
require_once TEXY_DIR.'libs/modifier.php';       // modifier processor
require_once TEXY_DIR.'libs/url.php';            // object encapsulate of URL
require_once TEXY_DIR.'libs/dom.php';            // Texy! DOM element's base class
require_once TEXY_DIR.'libs/module.php';         // Texy! module base class
require_once TEXY_DIR.'libs/entity.php';         // HTML entity => chars
require_once TEXY_DIR.'libs/parser.php';         // Texy! parser
require_once TEXY_DIR.'libs/html.php';
require_once TEXY_DIR.'libs/wellform.php';
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
require_once TEXY_DIR.'modules/quick-correct.php';
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

    /** @var boolean    Use UTF-8? (texy configuration) */
    var $utf = FALSE;

    /** @var int    TAB width (for converting tabs to spaces) */
    var $tabWidth = 8;

    /** @var TRUE|FALSE|array    Allowed classes */
    var $allowedClasses = TEXY_ALL;

    /** @var TRUE|FALSE|array    Allowed inline CSS style */
    var $allowedStyles = TEXY_ALL;

    /** @var TRUE|FALSE|array    Allowed HTML tags */
    var $allowedTags;

    /** @var boolean    Do obfuscate e-mail addresses? */
    var $obfuscateEmail = TRUE;

    /** @var array    function &myUserFunc($refName, $isImage, &$contentEl, &$texy): returns object or FALSE    Reference handler */
    var $referenceHandlers = array();

    var $elementHandlers = array();

    /** @var object    DOM structure for parsed text */
    var $DOM;

    /** @var object    Parsing summary */
    var $summary;

    /** @var string    Generated stylesheet */
    var $styleSheet = '';

    /** @var bool    Merge lines mode */
    var $mergeLines = TRUE;

    /** @var mixed    User data */
    var $tag;


    /**
     * Registered regexps and associated handlers for inline parsing
     * @var array Format: ('handler' => callback,
     *                     'pattern' => regular expression,
     *                     'user'    => user arguments)
     * @private
     */
    var $patternsLine;

    /**
     * Registered regexps and associated handlers for block parsing
     * @var array Format: ('handler' => callback,
     *                     'pattern' => regular expression,
     *                     'user'    => user arguments)
     * @private
     */
    var $patternsBlock;

    /**
     * Handler for generic block (not matched by any regexp from $patternsBlock
     * @var callback
     * @private
     */
    var $genericBlock;

    /** @var array    List of all used modules */
    var $modules;

    /** @var object    Default modules */
    var
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
        $quickCorrectModule,
        $longWordsModule,
        $formatterModule;



    function __construct()
    {
        // init some other variables
        $this->summary          = (object) NULL;
        $this->summary->images  = array();
        $this->summary->links   = array();
        $this->summary->preload = array();

        $this->allowedTags    = $GLOBALS['TexyHTML::$valid']; // full support for HTML tags

        // load all modules
        $this->loadModules();

/*
        // example of link reference ;-)
        $elRef = &new TexyLinkReference($this, 'http://www.texy.info/', 'Texy!');
        $elRef->modifier->title = 'Text to HTML converter and formatter';
        $this->addReference('texy', $elRef);
*/
    }


    /**
     * PHP4-only constructor
     * @see http://www.dgx.cz/trine/item/how-to-emulate-php5-object-model-in-php4
     */
    function Texy()
    {
        // generate references
        foreach ($this as $key => $foo) $GLOBALS['$$HIDDEN$$'][] = & $this->$key;

        // call PHP5 constructor
        $args = func_get_args();
        call_user_func_array(array(&$this, '__construct'), $args);
    }





    /**
     * Create array of all used modules ($this->modules)
     * This array can be changed by overriding this method (by subclasses)
     * or directly in main code
     */
    function loadModules()
    {
        // Line parsing - order is not much important
        $this->scriptModule = &new TexyScriptModule($this);
        $this->htmlModule = &new TexyHtmlModule($this);
        $this->imageModule = &new TexyImageModule($this);
        $this->linkModule = &new TexyLinkModule($this);
        $this->phraseModule = &new TexyPhraseModule($this);
        $this->smiliesModule = &new TexySmiliesModule($this);

        // block parsing - order is not much important
        $this->blockModule = &new TexyBlockModule($this);
        $this->headingModule = &new TexyHeadingModule($this);
        $this->horizLineModule = &new TexyHorizLineModule($this);
        $this->quoteModule = &new TexyQuoteModule($this);
        $this->listModule = &new TexyListModule($this);
        $this->definitionListModule = &new TexyDefinitionListModule($this);
        $this->tableModule = &new TexyTableModule($this);
        $this->imageDescModule = &new TexyImageDescModule($this);
        $this->genericBlockModule = &new TexyGenericBlockModule($this);

        // post process
        $this->quickCorrectModule = &new TexyQuickCorrectModule($this);
        $this->longWordsModule = &new TexyLongWordsModule($this);
        $this->formatterModule = &new TexyFormatterModule($this);  // should be last post-processing module!
    }



    function registerModule(&$module)
    {
        $this->modules[] = &$module;
    }



    function registerLinePattern(&$module, $method, $pattern, $user_args = NULL)
    {
        $this->patternsLine[] = array(
            'handler'     => array(&$module, $method),
            'pattern'     => $this->translatePattern($pattern),
            'user'        => $user_args
        );
    }


    function registerBlockPattern(&$module, $method, $pattern, $user_args = NULL)
    {
//    if (!preg_match('#(.)\^.*\$\\1[a-z]*#is', $pattern)) die('Texy: Not a block pattern. Module '.get_class($module).', pattern '.htmlSpecialChars($pattern));

        $this->patternsBlock[] = array(
            'handler'     => array(&$module, $method),
            'pattern'     => $this->translatePattern($pattern)  . 'm',  // force multiline!
            'user'        => $user_args
        );
    }

    /**
     * Initialization
     * It is called between constructor and first use (method parse)
     */
    function init()
    {
        $this->patternsLine   = array();
        $this->patternsBlock  = array();
        $this->genericBlock   = NULL;

        if (!$this->modules) die('Texy: No modules installed');

        // init modules
        foreach ($this->modules as $id => $foo)
            $this->modules[$id]->init();
    }



    /**
     * Convert Texy! document in (X)HTML code
     * This is shortcut for parse() & DOM->toHTML()
     * @return string
     */
    function process($text, $singleLine = FALSE)
    {
        if ($singleLine)
            $this->parseLine($text);
        else
            $this->parse($text);

        return $this->DOM->toHTML();
 }






    /**
     * Convert Texy! document into internal DOM structure ($this->DOM)
     * Before converting it normalize text and call all pre-processing modules
     */
    function parse($text)
    {
            // initialization
        $this->init();

            ///////////   PROCESS
        $this->DOM = &new TexyDOM($this);
        $this->DOM->parse($text);
    }





    /**
     * Convert Texy! single line text into internal DOM structure ($this->DOM)
     */
    function parseLine($text)
    {
            // initialization
        $this->init();

            ///////////   PROCESS
        $this->DOM = &new TexyDOMLine($this);
        $this->DOM->parse($text);
    }




    /**
     * Convert internal DOM structure ($this->DOM) to (X)HTML code
     * and call all post-processing modules
     * @return string
     */
    function toHTML()
    {
        return $this->DOM->toHTML();
    }



    /**
     * Convert internal DOM structure ($this->DOM) to pure Text
     * @return string
     */
    function toText()
    {
        // generate output
        $saveLineWrap = $this->formatterModule->lineWrap = FALSE;
        $this->formatterModule->lineWrap = FALSE;

        $text = $this->toHTML();

        $this->formatterModule->lineWrap = $saveLineWrap;

        // remove tags
        $text = preg_replace('#<(script|style)(.*)</\\1>#Uis', '', $text);
        $text = strip_tags($text);
        $text = preg_replace('#\n\s*\n\s*\n[\n\s]*\n#', "\n\n", $text);

        // entities -> chars
        $entity = new TexyHtmlEntity();
        $text = $entity->decode($text, $this->utf ? 'UTF-8' : 'CP1250');

        return $text;
    }


    /**
     * Switch Texy and default modules to safe mode
     * Suitable for 'comments' and other usages, where input text may insert attacker
     */
    function safeMode()
    {
        $this->allowedClasses = TEXY_NONE;                  // no class or ID are allowed
        $this->allowedStyles  = TEXY_NONE;                  // style modifiers are disabled
        $this->htmlModule->safeMode();                      // only HTML tags and attributes specified in $safeTags array are allowed
        $this->imageModule->allowed = FALSE;                // disable images
        $this->linkModule->forceNoFollow = TRUE;            // force rel="nofollow"
    }




    /**
     * Switch Texy and default modules to (default) trust mode
     */
    function trustMode()
    {
        $this->allowedClasses = TEXY_ALL;                   // classes and id are allowed
        $this->allowedStyles  = TEXY_ALL;                   // inline styles are allowed
        $this->htmlModule->trustMode();                     // full support for HTML tags
        $this->imageModule->allowed = TRUE;                 // enable images
        $this->linkModule->forceNoFollow = FALSE;           // disable automatic rel="nofollow"
    }






    /**
     * Add right slash
     * @static
     */
    function adjustDir(&$name)
    {
        if ($name) $name = rtrim($name, '/\\') . '/';
    }



    /**
     * Translate all white spaces (\t \n \r space) to meta-spaces \x15-\x18
     * which are ignored by some formatting functions
     * @return string
     * @static
     */
    function freezeSpaces($s)
    {
        return strtr($s, " \t\r\n", "\x15\x16\x17\x18");
    }


    /**
     * Revert meta-spaces back to normal spaces
     * @return string
     * @static
     */
    function unfreezeSpaces($s)
    {
        return strtr($s, "\x15\x16\x17\x18", " \t\r\n");
    }



    /**
     * remove special controls chars used by Texy!
     * @return string
     * @static
     */
    function wash($text)
    {
            ///////////   REMOVE SPECIAL CHARS (used by Texy!)
        return strtr($text, "\x15\x16\x17\x18\x19\x1A\x1B\x1C\x1D\x1E\x1F", '           ');
    }







    /**
     * For easier regular expression writing
     * @return string
     */
    function translatePattern($pattern)
    {
        return strtr($pattern, array(
            '<MODIFIER_HV>' => TEXY_PATTERN_MODIFIER_HV,
            '<MODIFIER_H>'  => TEXY_PATTERN_MODIFIER_H,
            '<MODIFIER>'    => TEXY_PATTERN_MODIFIER,
            '<LINK>'        => TEXY_PATTERN_LINK,
            '<UTF>'         => ($this->utf ? 'u' : ''),
            ':CHAR:'        => ($this->utf ? TEXY_CHAR_UTF : TEXY_CHAR),
            ':HASH:'        => TEXY_HASH,
            ':HASHSOFT:'    => TEXY_HASH_NC,
        ));
    }





    /**
     * experimental
     */
    function free()
    {
        foreach (array_keys(get_object_vars($this)) as $key)
            $this->$key = NULL;

        if (PHP_VERSION < 5) ${'this'.''} = NULL;
    }



} // Texy


?>