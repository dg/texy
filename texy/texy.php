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
 * @version    1.1 for PHP4 & PHP5 $Date$ $Revision$
 */


if (version_compare(PHP_VERSION , '4.3.3', '<'))
    die('Texy!: too old version of PHP!');

define('TEXY', 'Texy! (c) David Grudl, http://www.texy.info');

/**
 * Absolute filesystem path to the Texy package
 */
define('TEXY_DIR',  dirname(__FILE__).'/');

require_once TEXY_DIR.'libs/texy-constants.php';      // regular expressions & other constants
require_once TEXY_DIR.'libs/texy-modifier.php';       // modifier processor
require_once TEXY_DIR.'libs/texy-url.php';            // object encapsulate of URL
require_once TEXY_DIR.'libs/texy-dom.php';            // Texy! DOM element's base class
require_once TEXY_DIR.'libs/texy-module.php';         // Texy! module base class
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
        //$text = strtr($text, array('&amp;'=>'&','&quot;'=>'"','&lt;'=>'<','&gt;'=>'>'));  

        // entities -> chars
        if ((int) PHP_VERSION > 4 && $this->utf) { // fastest way for PHP 5 & UTF-8
            $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8'); 
        } else {        
            // named
//            $text = strtr($text, array('&amp;'=>'&','&quot;'=>'"','&lt;'=>'<','&gt;'=>'>'));  

            // numeric
            $text = preg_replace_callback(
                '#&(\\#x[0-9a-fA-F]+|\\#[0-9]+);#',
                array(&$this, '_entityCallback'),
                $text
            );
        }

        // convert nbsp to normal space and remove shy

        $text = strtr($text, array(
            $this->utf ? "\xC2\xAD" : "\xAD" => '',  // shy
            $this->utf ? "\xC2\xA0" : "\xA0" => ' ', // nbsp
        ));

        return $text;
    }





    /**
     * Callback for preg_replace_callback() in toText()
     *
     * @param array    matched entity
     * @return string  decoded entity
     */
    /*static private*/ function _entityCallback($matches)
    {
        list(, $entity) = $matches;

        $ord = ($entity{1} == 'x') 
             ? hexdec(substr($entity, 2)) 
             : (int) substr($entity, 1);
                
        if ($ord<128)  // ASCII
            return chr($ord);

        if ($this->utf) {
            if ($ord<2048) return chr(($ord>>6)+192) . chr(($ord&63)+128);
            if ($ord<65536) return chr(($ord>>12)+224) . chr((($ord>>6)&63)+128) . chr(($ord&63)+128);
            if ($ord<2097152) return chr(($ord>>18)+240) . chr((($ord>>12)&63)+128) . chr((($ord>>6)&63)+128) . chr(($ord&63)+128);
            return $match; // invalid entity
        }

        if (function_exists('iconv')) {
            return (string) iconv(
                'UCS-2', 
                'WINDOWS-1250//TRANSLIT', 
                pack('n', $ord)
            );
        }
        
        return '?';
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
     * Like htmlSpecialChars, but can preserve entities
     * @param  string  input string
     * @param  bool    for using inside quotes?
     * @param  bool    preserve entities? 
     * @return string
     * @static
     */
    function htmlChars($s, $inQuotes = false, $entity = false)
    {
        static $table = array(
            0 => array(
                '&'=>'&#38;',
                '<'=>'&#60;',
                '>'=>'&#62;',   // can be disabled - modify quickcorrect
            ),
            1 => array(
                '&'=>'&#38;',
                '"'=>'&#34;',
                '<'=>'&#60;',
                '>'=>'&#62;',
            ),     
        );

        // my version of htmlSpecialChars()
        $s = strtr($s, $table[$inQuotes ? 1 : 0]);

        if ($entity) // preserve numeric entities?
            return preg_replace('~&#38;([a-zA-Z0-9]+|#x[0-9a-fA-F]+|#[0-9]+);~', '&$1;', $s);
        else
            return $s;
    }




    /**
     * @return string
     * @static
     */
    function checkEntities($html)
    {
        static $entity=array('&AElig;'=>'&#198;','&Aacute;'=>'&#193;','&Acirc;'=>'&#194;','&Agrave;'=>'&#192;','&Alpha;'=>'&#913;','&Aring;'=>'&#197;','&Atilde;'=>'&#195;','&Auml;'=>'&#196;',
            '&Beta;'=>'&#914;','&Ccedil;'=>'&#199;','&Chi;'=>'&#935;','&Dagger;'=>'&#8225;','&Delta;'=>'&#916;','&ETH;'=>'&#208;','&Eacute;'=>'&#201;','&Ecirc;'=>'&#202;',
            '&Egrave;'=>'&#200;','&Epsilon;'=>'&#917;','&Eta;'=>'&#919;','&Euml;'=>'&#203;','&Gamma;'=>'&#915;','&Iacute;'=>'&#205;','&Icirc;'=>'&#206;','&Igrave;'=>'&#204;',
            '&Iota;'=>'&#921;','&Iuml;'=>'&#207;','&Kappa;'=>'&#922;','&Lambda;'=>'&#923;','&Mu;'=>'&#924;','&Ntilde;'=>'&#209;','&Nu;'=>'&#925;','&OElig;'=>'&#338;',
            '&Oacute;'=>'&#211;','&Ocirc;'=>'&#212;','&Ograve;'=>'&#210;','&Omega;'=>'&#937;','&Omicron;'=>'&#927;','&Oslash;'=>'&#216;','&Otilde;'=>'&#213;','&Ouml;'=>'&#214;',
            '&Phi;'=>'&#934;','&Pi;'=>'&#928;','&Prime;'=>'&#8243;','&Psi;'=>'&#936;','&Rho;'=>'&#929;','&Scaron;'=>'&#352;','&Sigma;'=>'&#931;','&THORN;'=>'&#222;',
            '&Tau;'=>'&#932;','&Theta;'=>'&#920;','&Uacute;'=>'&#218;','&Ucirc;'=>'&#219;','&Ugrave;'=>'&#217;','&Upsilon;'=>'&#933;','&Uuml;'=>'&#220;','&Xi;'=>'&#926;',
            '&Yacute;'=>'&#221;','&Yuml;'=>'&#376;','&Zeta;'=>'&#918;','&aacute;'=>'&#225;','&acirc;'=>'&#226;','&acute;'=>'&#180;','&aelig;'=>'&#230;','&agrave;'=>'&#224;',
            '&alefsym;'=>'&#8501;','&alpha;'=>'&#945;','&amp;'=>'&#38;','&and;'=>'&#8743;','&ang;'=>'&#8736;','&apos;'=>'&#39;','&aring;'=>'&#229;','&asymp;'=>'&#8776;',
            '&atilde;'=>'&#227;','&auml;'=>'&#228;','&bdquo;'=>'&#8222;','&beta;'=>'&#946;','&brvbar;'=>'&#166;','&bull;'=>'&#8226;','&cap;'=>'&#8745;','&ccedil;'=>'&#231;',
            '&cedil;'=>'&#184;','&cent;'=>'&#162;','&chi;'=>'&#967;','&circ;'=>'&#710;','&clubs;'=>'&#9827;','&cong;'=>'&#8773;','&copy;'=>'&#169;','&crarr;'=>'&#8629;',
            '&cup;'=>'&#8746;','&curren;'=>'&#164;','&dArr;'=>'&#8659;','&dagger;'=>'&#8224;','&darr;'=>'&#8595;','&deg;'=>'&#176;','&delta;'=>'&#948;','&diams;'=>'&#9830;',
            '&divide;'=>'&#247;','&eacute;'=>'&#233;','&ecirc;'=>'&#234;','&egrave;'=>'&#232;','&empty;'=>'&#8709;','&emsp;'=>'&#8195;','&ensp;'=>'&#8194;','&epsilon;'=>'&#949;',
            '&equiv;'=>'&#8801;','&eta;'=>'&#951;','&eth;'=>'&#240;','&euml;'=>'&#235;','&euro;'=>'&#8364;','&exist;'=>'&#8707;','&fnof;'=>'&#402;','&forall;'=>'&#8704;',
            '&frac12;'=>'&#189;','&frac14;'=>'&#188;','&frac34;'=>'&#190;','&frasl;'=>'&#8260;','&gamma;'=>'&#947;','&ge;'=>'&#8805;','&gt;'=>'&#62;','&hArr;'=>'&#8660;',
            '&harr;'=>'&#8596;','&hearts;'=>'&#9829;','&hellip;'=>'&#8230;','&iacute;'=>'&#237;','&icirc;'=>'&#238;','&iexcl;'=>'&#161;','&igrave;'=>'&#236;','&image;'=>'&#8465;',
            '&infin;'=>'&#8734;','&int;'=>'&#8747;','&iota;'=>'&#953;','&iquest;'=>'&#191;','&isin;'=>'&#8712;','&iuml;'=>'&#239;','&kappa;'=>'&#954;','&lArr;'=>'&#8656;',
            '&lambda;'=>'&#955;','&lang;'=>'&#9001;','&laquo;'=>'&#171;','&larr;'=>'&#8592;','&lceil;'=>'&#8968;','&ldquo;'=>'&#8220;','&le;'=>'&#8804;','&lfloor;'=>'&#8970;',
            '&lowast;'=>'&#8727;','&loz;'=>'&#9674;','&lrm;'=>'&#8206;','&lsaquo;'=>'&#8249;','&lsquo;'=>'&#8216;','&lt;'=>'&#60;','&macr;'=>'&#175;','&mdash;'=>'&#8212;',
            '&micro;'=>'&#181;','&middot;'=>'&#183;','&minus;'=>'&#8722;','&mu;'=>'&#956;','&nabla;'=>'&#8711;','&nbsp;'=>'&#160;','&ndash;'=>'&#8211;','&ne;'=>'&#8800;',
            '&ni;'=>'&#8715;','&not;'=>'&#172;','&notin;'=>'&#8713;','&nsub;'=>'&#8836;','&ntilde;'=>'&#241;','&nu;'=>'&#957;','&oacute;'=>'&#243;','&ocirc;'=>'&#244;',
            '&oelig;'=>'&#339;','&ograve;'=>'&#242;','&oline;'=>'&#8254;','&omega;'=>'&#969;','&omicron;'=>'&#959;','&oplus;'=>'&#8853;','&or;'=>'&#8744;','&ordf;'=>'&#170;',
            '&ordm;'=>'&#186;','&oslash;'=>'&#248;','&otilde;'=>'&#245;','&otimes;'=>'&#8855;','&ouml;'=>'&#246;','&para;'=>'&#182;','&part;'=>'&#8706;','&permil;'=>'&#8240;',
            '&perp;'=>'&#8869;','&phi;'=>'&#966;','&pi;'=>'&#960;','&piv;'=>'&#982;','&plusmn;'=>'&#177;','&pound;'=>'&#163;','&prime;'=>'&#8242;','&prod;'=>'&#8719;',
            '&prop;'=>'&#8733;','&psi;'=>'&#968;','&quot;'=>'&#34;','&rArr;'=>'&#8658;','&radic;'=>'&#8730;','&rang;'=>'&#9002;','&raquo;'=>'&#187;','&rarr;'=>'&#8594;',
            '&rceil;'=>'&#8969;','&rdquo;'=>'&#8221;','&real;'=>'&#8476;','&reg;'=>'&#174;','&rfloor;'=>'&#8971;','&rho;'=>'&#961;','&rlm;'=>'&#8207;','&rsaquo;'=>'&#8250;',
            '&rsquo;'=>'&#8217;','&sbquo;'=>'&#8218;','&scaron;'=>'&#353;','&sdot;'=>'&#8901;','&sect;'=>'&#167;','&shy;'=>'&#173;','&sigma;'=>'&#963;','&sigmaf;'=>'&#962;',
            '&sim;'=>'&#8764;','&spades;'=>'&#9824;','&sub;'=>'&#8834;','&sube;'=>'&#8838;','&sum;'=>'&#8721;','&sup1;'=>'&#185;','&sup2;'=>'&#178;','&sup3;'=>'&#179;',
            '&sup;'=>'&#8835;','&supe;'=>'&#8839;','&szlig;'=>'&#223;','&tau;'=>'&#964;','&there4;'=>'&#8756;','&theta;'=>'&#952;','&thetasym;'=>'&#977;','&thinsp;'=>'&#8201;',
            '&thorn;'=>'&#254;','&tilde;'=>'&#732;','&times;'=>'&#215;','&trade;'=>'&#8482;','&uArr;'=>'&#8657;','&uacute;'=>'&#250;','&uarr;'=>'&#8593;','&ucirc;'=>'&#251;',
            '&ugrave;'=>'&#249;','&uml;'=>'&#168;','&upsih;'=>'&#978;','&upsilon;'=>'&#965;','&uuml;'=>'&#252;','&weierp;'=>'&#8472;','&xi;'=>'&#958;','&yacute;'=>'&#253;',
            '&yen;'=>'&#165;','&yuml;'=>'&#255;','&zeta;'=>'&#950;','&zwj;'=>'&#8205;','&zwnj;'=>'&#8204;',
        );
        
        // preserve and decode(!) named entities
        $html = strtr($html, $entity);
        
        // preserve numeric entities
        return preg_replace('#&([a-zA-Z0-9]+);#', '&#38;$1;', $html);

        //strtr($html, array('&#38;'=>'&amp;','&#34;'=>'&quot;','&#60;'=>'&lt;','&#62;'=>'&gt;'));
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
                              . Texy::freezeSpaces(Texy::htmlChars($value, true, true))   // freezed spaces will be preserved during reformating
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

        $text = Texy::htmlChars($text, false, true);

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