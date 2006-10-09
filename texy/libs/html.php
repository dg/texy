<?php

/**
 * Texy! universal text -> html converter
 * --------------------------------------
 *
 * This source file is subject to the GNU GPL license.
 *
 * @author     David Grudl aka -dgx- <dave@dgx.cz>
 * @link       http://texy.info/
 * @copyright  Copyright (c) 2004-2006 David Grudl
 * @license    GNU GENERAL PUBLIC LICENSE v2
 * @package    Texy
 * @category   Text
 * @version    $Revision$ $Date$
 */


// static variable initialization
TexyHtml::$valid = array_merge(TexyHtml::$block, TexyHtml::$inline);


/**
 * HTML support for Texy!
 *
 */
class TexyHtml
{
    const EMPTYTAG = '/';

    // notice: I use a little trick - isset($array[$item]) is much faster than in_array($item, $array)
    static public $block = array(
        'address'=>1, 'blockquote'=>1, 'caption'=>1, 'col'=>1, 'colgroup'=>1, 'dd'=>1, 'div'=>1, 'dl'=>1, 'dt'=>1, 'fieldset'=>1, 'form'=>1,
        'h1'=>1, 'h2'=>1, 'h3'=>1, 'h4'=>1, 'h5'=>1, 'h6'=>1, 'hr'=>1, 'iframe'=>1, 'legend'=>1, 'li'=>1, 'object'=>1, 'ol'=>1, 'p'=>1,
        'param'=>1, 'pre'=>1, 'table'=>1, 'tbody'=>1, 'td'=>1, 'tfoot'=>1, 'th'=>1, 'thead'=>1, 'tr'=>1, 'ul'=>1,/*'embed'=>1,*/);

    static public $inline = array(
        'a'=>1, 'abbr'=>1, 'acronym'=>1, 'area'=>1, 'b'=>1, 'big'=>1, 'br'=>1, 'button'=>1, 'cite'=>1, 'code'=>1, 'del'=>1, 'dfn'=>1,
        'em'=>1, 'i'=>1, 'img'=>1, 'input'=>1, 'ins'=>1, 'kbd'=>1, 'label'=>1, 'map'=>1, 'noscript'=>1, 'optgroup'=>1, 'option'=>1, 'q'=>1,
        'samp'=>1, 'script'=>1, 'select'=>1, 'small'=>1, 'span'=>1, 'strong'=>1, 'sub'=>1, 'sup'=>1, 'textarea'=>1, 'tt'=>1, 'var'=>1,);

    static public $empty = array('img'=>1, 'hr'=>1, 'br'=>1, 'input'=>1, 'meta'=>1, 'area'=>1, 'base'=>1, 'col'=>1, 'link'=>1, 'param'=>1,);

//  static public $meta = array('html'=>1, 'head'=>1, 'body'=>1, 'base'=>1, 'meta'=>1, 'link'=>1, 'title'=>1,);

    static public $accepted_attrs = array(
        'abbr'=>1, 'accesskey'=>1, 'align'=>1, 'alt'=>1, 'archive'=>1, 'axis'=>1, 'bgcolor'=>1, 'cellpadding'=>1, 'cellspacing'=>1, 'char'=>1,
        'charoff'=>1, 'charset'=>1, 'cite'=>1, 'classid'=>1, 'codebase'=>1, 'codetype'=>1, 'colspan'=>1, 'compact'=>1, 'coords'=>1, 'data'=>1,
        'datetime'=>1, 'declare'=>1, 'dir'=>1, 'face'=>1, 'frame'=>1, 'headers'=>1, 'href'=>1, 'hreflang'=>1, 'hspace'=>1, 'ismap'=>1,
        'lang'=>1, 'longdesc'=>1, 'name'=>1, 'noshade'=>1, 'nowrap'=>1, 'onblur'=>1, 'onclick'=>1, 'ondblclick'=>1, 'onkeydown'=>1,
        'onkeypress'=>1, 'onkeyup'=>1, 'onmousedown'=>1, 'onmousemove'=>1, 'onmouseout'=>1, 'onmouseover'=>1, 'onmouseup'=>1, 'rel'=>1,
        'rev'=>1, 'rowspan'=>1, 'rules'=>1, 'scope'=>1, 'shape'=>1, 'size'=>1, 'span'=>1, 'src'=>1, 'standby'=>1, 'start'=>1, 'summary'=>1,
        'tabindex'=>1, 'target'=>1, 'title'=>1, 'type'=>1, 'usemap'=>1, 'valign'=>1, 'value'=>1, 'vspace'=>1,);

    static public $valid; /* array_merge(TexyHtml::$block, TexyHtml::$inline); */



    /**
     * Like htmlSpecialChars, but can preserve entities
     * @param  string  input string
     * @param  bool    for using inside quotes?
     * @param  bool    preserve entities?
     * @return string
     * @static
     */
    static public function htmlChars($s, $inQuotes = FALSE, $entity = FALSE)
    {
        $s = htmlSpecialChars($s, $inQuotes ? ENT_COMPAT : ENT_NOQUOTES);

        if ($entity) // preserve numeric entities?
            return preg_replace('~&amp;([a-zA-Z0-9]+|#x[0-9a-fA-F]+|#[0-9]+);~', '&$1;', $s);
        else
            return $s;
    }




    /**
     * @return string
     * @static
     */
    static public function checkEntities($html)
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

        static $allowed = array('&#38;'=>'&amp;', '&#34;'=>'&quot;', '&#60;'=>'&lt;', '&#62;'=>'&gt;');

        // decode(!) named entities to numeric
//        $html = strtr($html, $entity);
        $html = str_replace(array_keys($entity), array_values($entity), $html);

        // preserve numeric entities
        $html = preg_replace('#&([a-zA-Z0-9]+);#', '&amp;$1;', $html);

        // only allowed named entites are these:
        return strtr($html, $allowed);
    }




    /**
     * Build string which represents (X)HTML opening tag
     * @param string   tag
     * @param array    associative array of attributes and values ( / mean empty tag, arrays are imploded )
     * @return string
     * @static
     */
    static public function openingTags($tags)
    {
        $result = '';
        foreach ((array)$tags as $tag => $attrs) {

            if ($tag == NULL) continue;

            $empty = isset(self::$empty[$tag]) || isset($attrs[self::EMPTYTAG]);

            $attrStr = '';
            if (is_array($attrs)) {
                unset($attrs[self::EMPTYTAG]);

                foreach (array_change_key_case($attrs, CASE_LOWER) as $name => $value) {
                    if (is_array($value)) {
                        if ($name == 'style') {
                            $style = array();
                            foreach (array_change_key_case($value, CASE_LOWER) as $keyS => $valueS)
                                if ($keyS && ($valueS !== '') && ($valueS !== NULL)) $style[] = $keyS.':'.$valueS;
                            $value = implode(';', $style);
                        } else $value = implode(' ', array_unique($value));
                        if ($value == '') continue;
                    }

                    if ($value === NULL || $value === FALSE) continue;
                    $value = trim($value);
                    $attrStr .= ' '
                              . self::htmlChars($name)
                              . '="'
                              . Texy::freezeSpaces(self::htmlChars($value, TRUE, TRUE))   // freezed spaces will be preserved during reformating
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
    static public function closingTags($tags)
    {
        $result = '';
        foreach (array_reverse((array) $tags, TRUE) as $tag => $attrs) {
            if ($tag == '') continue;
            if ( isset(self::$empty[$tag]) || isset($attrs[self::EMPTYTAG]) ) continue;

            $result .= '</'.$tag.'>';
        }

        return $result;
    }



    /**
     * Undefined property usage prevention
     */
    function __set($nm, $val)     { $c=get_class($this); trigger_error("Undefined property '$c::$$nm'", E_USER_ERROR); }
    function __get($nm)           { $c=get_class($this); trigger_error("Undefined property '$c::$$nm'", E_USER_ERROR); }
    private function __unset($nm) { $c=get_class($this); trigger_error("Undefined property '$c::$$nm'", E_USER_ERROR); }
    private function __isset($nm) { return FALSE; }
}