<?php

/**
 * Texy! universal text -> html converter
 * --------------------------------------
 *
 * This source file is subject to the GNU GPL license.
 *
 * @link       http://www.texy.info/
 * @author     David Grudl aka -dgx- <dave@dgx.cz>
 * @copyright  Copyright (c) 2004-2006 David Grudl
 * @license    GNU GENERAL PUBLIC LICENSE
 * @package    Texy
 * @category   Text
 * @version    1.0 for PHP4 & PHP5 (released 2006/04/18)
 */


if (version_compare(PHP_VERSION , '4.3.3', '<'))
    die('Texy!: too old version of PHP!');

define('TEXY', 'Version 1.0 (c) David Grudl, http://www.texy.info');

/**
 * Absolute filesystem path to the Texy package
 */
define('TEXY_DIR',  dirname(__FILE__).'/');

require_once TEXY_DIR.'libs/texy-constants.php';      // regular expressions & other constants
require_once TEXY_DIR.'libs/texy-modifier.php';       // modifier processor
require_once TEXY_DIR.'libs/texy-url.php';            // object encapsulate of URL
require_once TEXY_DIR.'libs/texy-dom.php';            // Texy! DOM element's base class
require_once TEXY_DIR.'libs/texy-module.php';         // Texy! module base class
require_once TEXY_DIR.'libs/texy-entity.php';         // HTML entity => chars
require_once TEXY_DIR.'modules/tm-block.php';
require_once TEXY_DIR.'modules/tm-definition-list.php';
require_once TEXY_DIR.'modules/tm-formatter.php';
require_once TEXY_DIR.'modules/tm-generic-block.php';
require_once TEXY_DIR.'modules/tm-heading.php';
require_once TEXY_DIR.'modules/tm-horiz-line.php';
require_once TEXY_DIR.'modules/tm-html-tag.php';
require_once TEXY_DIR.'modules/tm-image.php';
require_once TEXY_DIR.'modules/tm-image-description.php';
require_once TEXY_DIR.'modules/tm-link.php';
require_once TEXY_DIR.'modules/tm-list.php';
require_once TEXY_DIR.'modules/tm-long-words.php';
require_once TEXY_DIR.'modules/tm-phrase.php';
require_once TEXY_DIR.'modules/tm-quick-correct.php';
require_once TEXY_DIR.'modules/tm-quote.php';
require_once TEXY_DIR.'modules/tm-script.php';
require_once TEXY_DIR.'modules/tm-table.php';
require_once TEXY_DIR.'modules/tm-smilies.php';


/**
 * Texy! - Convert plain text to XHTML format using {@link process()}
 *
 * <code>
 *     $texy = new Texy();
 *     $html = $texy->process($text);
 * </code>
 */
class Texy {

    /**
     * Use UTF-8? (texy configuration)
     * @var boolean
     */
    var $utf = false;

    /**
     * TAB width (for converting tabs to spaces)
     * @var int
     */
    var $tabWidth = 8;

    /**
     * Allowed classes
     * @var true|false|array
     */
    var $allowedClasses;

    /**
     * Allowed inline CSS style
     * @var true|false|array
     */
    var $allowedStyles;

    /**
     * Allowed HTML tags
     * @var true|false|array
     */
    var $allowedTags;

    /**
     * Do obfuscate e-mail addresses?
     * @var boolean
     */
    var $obfuscateEmail = true;

    /**
     * Reference handler
     * @var callback function &myUserFunc($refName, &$texy): returns object or false
     */
    var $referenceHandler;

    /**
     * List of all used modules
     * @var object
     */
    var $modules;

    /**
     * DOM structure for parsed text
     * @var object
     */
    var $DOM;

    /**
     * Parsing summary
     * @var object
     */
    var $summary;

    /**
     * Generated stylesheet
     * @var string
     */
    var $styleSheet;

    /**
     * Merge lines mode
     * @var bool
     */
    var $mergeLines = true;


    /**
     * Is already initialized?
     * @var boolean
     * @private
     */
    var $inited;

    /**
     * Registered regexps and associated handlers for inline parsing
     * @var array Format: ('handler' => callback,
     *                     'pattern' => regular expression,
     *                     'user'    => user arguments)
     * @private
     */
    var $patternsLine     = array();

    /**
     * Registered regexps and associated handlers for block parsing
     * @var array Format: ('handler' => callback,
     *                     'pattern' => regular expression,
     *                     'user'    => user arguments)
     * @private
     */
    var $patternsBlock    = array();

    /**
     * Handler for generic block (not matched by any regexp from $patternsBlock
     * @var callback
     * @private
     */
    var $genericBlock;

    /**
     * Reference stack
     * @var array Format: ('home' => TexyLinkReference, ...)
     * @private
     */
    var $references       = array();

    /**
     * prevent recursive calling
     * @var boolean
     * @private
     */
    var $_preventCycling  = false;





    /**
     * PHP4 & PHP5 constructor
     */
    function __construct()
    {
        // init some other variables
        $this->summary->images  = array();
        $this->summary->links   = array();
        $this->summary->preload = array();
        $this->styleSheet = '';

        $this->allowedClasses = TEXY_ALL;                   // classes and id are allowed
        $this->allowedStyles  = TEXY_ALL;                   // inline styles are allowed
        $this->allowedTags    = unserialize(TEXY_VALID_ELEMENTS); // full support for HTML tags

        // load all modules
        $this->loadModules();

        // example of link reference ;-)
        $elRef = &new TexyLinkReference($this, 'http://www.texy.info/', 'Texy!');
        $elRef->modifier->title = 'Text to HTML converter and formatter';
        $this->addReference('texy', $elRef);
    }



    /**
     * PHP4 constructor
     */
    function Texy()
    {
        // call php5 constructor
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
        $this->registerModule('TexyScriptModule');
        $this->registerModule('TexyHtmlModule');
        $this->registerModule('TexyImageModule');
        $this->registerModule('TexyLinkModule');
        $this->registerModule('TexyPhraseModule');
        $this->registerModule('TexySmiliesModule');

        // block parsing - order is not much important
        $this->registerModule('TexyBlockModule');
        $this->registerModule('TexyHeadingModule');
        $this->registerModule('TexyHorizLineModule');
        $this->registerModule('TexyQuoteModule');
        $this->registerModule('TexyListModule');
        $this->registerModule('TexyDefinitionListModule');
        $this->registerModule('TexyTableModule');
        $this->registerModule('TexyImageDescModule');
        $this->registerModule('TexyGenericBlockModule');

        // post process
        $this->registerModule('TexyQuickCorrectModule');
        $this->registerModule('TexyLongWordsModule');
        $this->registerModule('TexyFormatterModule');  // should be last post-processing module!
    }



    function registerModule($className, $shortName = null)
    {
        if (isset($this->modules->$className)) return false;

        $this->modules->$className = &new $className($this);

        // universal shortcuts
        if ($shortName === null) {
            $shortName = (substr($className, 0, 4) === 'Texy') ? substr($className, 4) : $className;
            $shortName{0} = strtolower($shortName{0});
        }
        if (!isset($this->$shortName)) $this->$shortName = & $this->modules->$className;
    }



    /**
     * Initialization
     * It is called between constructor and first use (method parse)
     */
    function init()
    {
        $GLOBALS['Texy__$hashCounter'] = 0;
        $this->refQueries = array();

        if ($this->inited) return;

        if (!$this->modules) die('Texy: No modules installed');

        // init modules
        foreach ($this->modules as $name => $foo)
            $this->modules->$name->init();

        $this->inited = true;
    }




    /**
     * Re-Initialization
     */
    function reinit()
    {
        $this->patternsLine   = array();
        $this->patternsBlock  = array();
        $this->genericBlock   = null;
        $this->inited = false;
        $this->init();
    }



    /**
     * Convert Texy! document in (X)HTML code
     * This is shortcut for parse() & DOM->toHTML()
     * @return string
     */
    function process($text, $singleLine = false)
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
        $saveLineWrap = $this->formatterModule->lineWrap = false;
        $this->formatterModule->lineWrap = false;

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
        $this->blockModule->safeMode();                     // make /--html blocks HTML safe
        $this->imageModule->allowed = false;                // disable images
        $this->linkModule->forceNoFollow = true;            // force rel="nofollow"
    }




    /**
     * Switch Texy and default modules to (default) trust mode
     */
    function trustMode()
    {
        $this->allowedClasses = TEXY_ALL;                   // classes and id are allowed
        $this->allowedStyles  = TEXY_ALL;                   // inline styles are allowed
        $this->htmlModule->trustMode();                     // full support for HTML tags
        $this->blockModule->trustMode();                    // no-texy blocks are free of use
        $this->imageModule->allowed = true;                 // enable images
        $this->linkModule->forceNoFollow = false;           // disable automatic rel="nofollow"
    }







    /**
     * Like htmlSpecialChars, but preserve entities
     * @return string
     * @static
     */
    function htmlChars($s, $quotes = ENT_NOQUOTES)
    {
        return preg_replace('#'.TEXY_PATTERN_ENTITY.'#i', '&$1;', htmlSpecialChars($s, $quotes));
    }





    /**
     * Create a URL class and return it. Subclasses can override
     * this method to return an instance of inherited URL class.
     * @return TexyURL
     */
    function &createURL()
    {
        $php4_sucks = &new TexyURL($this);
        return $php4_sucks;
    }



    /**
     * Create a modifier class and return it. Subclasses can override
     * this method to return an instance of inherited modifier class.
     * @return TexyModifier
     */
    function &createModifier()
    {
        $php4_sucks = &new TexyModifier($this);
        return $php4_sucks;
    }




    /**
     * Build string which represents (X)HTML opening tag
     * @param string   tag
     * @param array    associative array of attributes and values ( / mean empty tag, arrays are imploded )
     * @return string
     * @static
     */
    function openingTags($tags)
    {
        static $TEXY_EMPTY_ELEMENTS;
        if (!$TEXY_EMPTY_ELEMENTS) $TEXY_EMPTY_ELEMENTS = unserialize(TEXY_EMPTY_ELEMENTS);

        $result = '';
        foreach ((array)$tags as $tag => $attrs) {

            if ($tag == null) continue;

            $empty = isset($TEXY_EMPTY_ELEMENTS[$tag]) || isset($attrs[TEXY_EMPTY]);

            $attrStr = '';
            if (is_array($attrs)) {
                unset($attrs[TEXY_EMPTY]);

                foreach (array_change_key_case($attrs, CASE_LOWER) as $name => $value) {
                    if (is_array($value)) {
                        if ($name == 'style') {
                            $style = array();
                            foreach (array_change_key_case($value, CASE_LOWER) as $keyS => $valueS)
                                if ($keyS && ($valueS !== '') && ($valueS !== null)) $style[] = $keyS.':'.$valueS;
                            $value = implode(';', $style);
                        } else $value = implode(' ', array_unique($value));
                        if ($value == '') continue;
                    }

                    if ($value === null || $value === false) continue;
                    $value = trim($value);
                    $attrStr .= ' '
                              . Texy::htmlChars($name)
                              . '="'
                              . Texy::freezeSpaces(Texy::htmlChars($value, ENT_COMPAT))   // freezed spaces will be preserved during reformating
                              . '"';
                }
            }

            $result .= '<' . $tag . $attrStr . ($empty ? ' /' : '') . '>';
        }

        return $result;
    }



    /**
     * Build string which represents (X)HTML opening tag
     * @return string
     * @static
     */
    function closingTags($tags)
    {
        static $TEXY_EMPTY_ELEMENTS;
        if (!$TEXY_EMPTY_ELEMENTS) $TEXY_EMPTY_ELEMENTS = unserialize(TEXY_EMPTY_ELEMENTS);

        $result = '';
        foreach (array_reverse((array) $tags, true) as $tag => $attrs) {
            if ($tag == '') continue;
            if ( isset($TEXY_EMPTY_ELEMENTS[$tag]) || isset($attrs[TEXY_EMPTY]) ) continue;

            $result .= '</'.$tag.'>';
        }

        return $result;
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
     * Generate unique HASH key - useful for freezing (folding) some substrings
     * Key consist of unique chars \x19, \x1B-\x1E (noncontent) (or \x1F detect opening tag)
     *                             \x1A, \x1B-\x1E (with content)
     * @return string
     * @static
     */
    function hashKey($contentType = null, $opening = null)
    {
        $border = ($contentType == TEXY_CONTENT_NONE) ? "\x19" : "\x1A";
        return $border . ($opening ? "\x1F" : "") . strtr(base_convert(++$GLOBALS['Texy__$hashCounter'], 10, 4), '0123', "\x1B\x1C\x1D\x1E") . $border;
    }


    /**
     * @static
     */
    function isHashOpening($hash) {
        return $hash{1} == "\x1F";
    }


    /**
     * Add new named reference
     */
    function addReference($name, &$obj)
    {
        $name = strtolower($name);
        $this->references[$name] = &$obj;
    }




    /**
     * Receive new named link. If not exists, try
     * call user function to create one.
     */
    var $refQueries;
    function &getReference($name)
    {
        $name = strtolower($name);
        $false = false; // php4_sucks

        if ($this->_preventCycling) {
            if (isset($this->refQueries[$name])) return $false;
            $this->refQueries[$name] = true;
        } else $this->refQueries = array();


        if (isset($this->references[$name]))
            return $this->references[$name];


        if ($this->referenceHandler) {
            $this->_disableReferences = true;
            $this->references[$name] = call_user_func_array(
                                     $this->referenceHandler,
                                     array($name, &$this)
            );
            $this->_disableReferences = false;

            return $this->references[$name];
        }

        return $false;
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




    function free()
    {
        foreach (array_keys(get_object_vars($this)) as $key)
            $this->$key = null;

        if (PHP_VERSION < 5) ${'this'.''} = null;
    }



} // Texy















/**
 * INTERNAL PARSING BLOCK STRUCTURE
 * --------------------------------
 */
class TexyBlockParser {
    var $element;     // TexyBlockElement
    var $text;        // text splited in array of lines
    var $offset;


    // constructor
    function TexyBlockParser(& $element)
    {
        $this->element = &$element;
    }


    // match current line against RE.
    // if succesfull, increments current position and returns true
    function receiveNext($pattern, &$matches)
    {
        $ok = preg_match(
                   $pattern . 'Am', // anchored & multiline
                   $this->text,
                   $matches,
                   PREG_OFFSET_CAPTURE,
                   $this->offset);
        if ($ok) {
            $this->offset += strlen($matches[0][0]) + 1;  // 1 = "\n"
            foreach ($matches as $key => $value) $matches[$key] = $value[0];
        }
        return $ok;
    }



    function moveBackward($linesCount = 1)
    {
        while (--$this->offset > 0)
         if ($this->text{ $this->offset-1 } == TEXY_NEWLINE)
             if (--$linesCount < 1) break;

        $this->offset = max($this->offset, 0);
    }




    function parse($text)
    {
            ///////////   INITIALIZATION
        $texy = &$this->element->texy;
        $this->text = & $text;
        $this->offset = 0;
        $this->element->children = array();

        $patternKeys = array_keys($texy->patternsBlock);
        $arrMatches = $arrPos = array();
        foreach ($patternKeys as $key) $arrPos[$key] = -1;


            ///////////   PARSING
        do {
            $minKey = -1;
            $minPos = strlen($this->text);
            if ($this->offset >= $minPos) break;

            foreach ($patternKeys as $index => $key) {
                if ($arrPos[$key] === false) continue;

                if ($arrPos[$key] < $this->offset) {
                    $delta = ($arrPos[$key] == -2) ? 1 : 0;
                    $matches = & $arrMatches[$key];
                    if (preg_match(
                            $texy->patternsBlock[$key]['pattern'],
                            $text,
                            $matches,
                            PREG_OFFSET_CAPTURE,
                            $this->offset + $delta)) {

                        $arrPos[$key] = $matches[0][1];
                        foreach ($matches as $keyX => $valueX) $matches[$keyX] = $valueX[0];

                    } else {
                        unset($patternKeys[$index]);
                        continue;
                    }
                }

                if ($arrPos[$key] === $this->offset) { $minKey = $key; break; }

                if ($arrPos[$key] < $minPos) { $minPos = $arrPos[$key]; $minKey = $key; }
            } // foreach

            $next = ($minKey == -1) ? strlen($text) : $arrPos[$minKey];

            if ($next > $this->offset) {
                $str = substr($text, $this->offset, $next - $this->offset);
                $this->offset = $next;
                call_user_func_array($texy->genericBlock, array(&$this, $str));
                continue;
            }

            $px = & $texy->patternsBlock[$minKey];
            $matches = & $arrMatches[$minKey];
            $this->offset = $arrPos[$minKey] + strlen($matches[0]) + 1;   // 1 = \n
            $ok = call_user_func_array($px['handler'], array(&$this, $matches, $px['user']));
            if ($ok === false || ( $this->offset <= $arrPos[$minKey] )) { // module rejects text
                $this->offset = $arrPos[$minKey]; // turn offset back
                $arrPos[$minKey] = -2;
                continue;
            }

            $arrPos[$minKey] = -1;

        } while (1);
    }

} // TexyBlockParser








/**
 * INTERNAL PARSING LINE STRUCTURE
 * -------------------------------
 */
class TexyLineParser {
    var $element;   // TexyTextualElement


    // constructor
    function TexyLineParser(& $element)
    {
        $this->element = &$element;
    }



    function parse($text, $postProcess = true)
    {
            ///////////   INITIALIZATION
        $element = &$this->element;
        $texy = &$element->texy;

        $offset = 0;
        $hashStrLen = 0;
        $patternKeys = array_keys($texy->patternsLine);
        $arrMatches = $arrPos = array();
        foreach ($patternKeys as $key) $arrPos[$key] = -1;


            ///////////   PARSING
        do {
            $minKey = -1;
            $minPos = strlen($text);

            foreach ($patternKeys as $index => $key) {
                if ($arrPos[$key] < $offset) {
                    $delta = ($arrPos[$key] == -2) ? 1 : 0;
                    $matches = & $arrMatches[$key];
                    if (preg_match($texy->patternsLine[$key]['pattern'],
                                     $text,
                                     $matches,
                                     PREG_OFFSET_CAPTURE,
                                     $offset+$delta)) {
                        if (!strlen($matches[0][0])) continue;
                        $arrPos[$key] = $matches[0][1];
                        foreach ($matches as $keyx => $value) $matches[$keyx] = $value[0];

                    } else {

                        unset($patternKeys[$index]);
                        continue;
                    }
                } // if

                if ($arrPos[$key] == $offset) { $minKey = $key; break; }

                if ($arrPos[$key] < $minPos) { $minPos = $arrPos[$key]; $minKey = $key; }

            } // foreach

            if ($minKey == -1) break;

            $px = & $texy->patternsLine[$minKey];
            $offset = $arrPos[$minKey];
            $replacement = call_user_func_array($px['handler'], array(&$this, $arrMatches[$minKey], $px['user']));
            $len = strlen($arrMatches[$minKey][0]);
            $text = substr_replace(
                        $text,
                        $replacement,
                        $offset,
                        $len);

            $delta = strlen($replacement) - $len;
            foreach ($patternKeys as $key) {
                if ($arrPos[$key] < $offset + $len) $arrPos[$key] = -1;
                else $arrPos[$key] += $delta;
            }

            $arrPos[$minKey] = -2;

        } while (1);

        $text = Texy::htmlChars($text);

        if ($postProcess)
            foreach ($texy->modules as $name => $foo)
                $texy->modules->$name->linePostProcess($text);

        $element->setContent($text, true);

        if ($element->contentType == TEXY_CONTENT_NONE) {
            $s = trim( preg_replace('#['.TEXY_HASH.']+#', '', $text) );
            if (strlen($s)) $element->contentType = TEXY_CONTENT_TEXTUAL;
        }
    }

} // TexyLineParser





?>