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
 * @version    1.5 for PHP4 & PHP5 $Date$ $Revision$
 */


/**
 * HTML support for Texy!
 *
 */


class TexyHTML
{
/*
    public static $block;
    public static $inline;
    public static $empty;
    public static $meta;
    public static $accepted_attrs;


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
        $result = '';
        foreach ((array)$tags as $tag => $attrs) {

            if ($tag == NULL) continue;

            $empty = isset($GLOBALS['TexyHTML::$empty'][$tag]) || isset($attrs[TEXY_EMPTY]);

            $attrStr = '';
            if (is_array($attrs)) {
                unset($attrs[TEXY_EMPTY]);

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
                              . TexyHTML::htmlChars($name)
                              . '="'
                              . Texy::freezeSpaces(TexyHTML::htmlChars($value, true, true))   // freezed spaces will be preserved during reformating
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
        $result = '';
        foreach (array_reverse((array) $tags, TRUE) as $tag => $attrs) {
            if ($tag == '') continue;
            if ( isset($GLOBALS['TexyHTML::$empty'][$tag]) || isset($attrs[TEXY_EMPTY]) ) continue;

            $result .= '</'.$tag.'>';
        }

        return $result;
    }




/*
    var $tagUsed;
    var $dontNestElements  = array('a'          => array('a'),
                                   'pre'        => array('img', 'object', 'big', 'small', 'sub', 'sup'),
                                   'button'     => array('input', 'select', 'textarea', 'label', 'button', 'form', 'fieldset', 'iframe', 'isindex'),
                                   'label'      => array('label'),
                                   'form'       => array('form'),
                                   );
*/

    // internal
    var $tagStack;
    var $autoCloseElements = array('tbody'      => array('thead'=>1, 'tbody'=>1, 'tfoot'=>1, 'colgoup'=>1),
                                   'colgroup'   => array('thead'=>1, 'tbody'=>1, 'tfoot'=>1, 'colgoup'=>1),
                                   'dd'         => array('dt'=>1, 'dd'=>1),
                                   'dt'         => array('dt'=>1, 'dd'=>1),
                                   'li'         => array('li'=>1),
                                   'option'     => array('option'=>1),
                                   'p'          => array('address'=>1, 'applet'=>1, 'blockquote'=>1, 'center'=>1, 'dir'=>1, 'div'=>1, 'dl'=>1, 'fieldset'=>1, 'form'=>1, 'h1'=>1, 'h2'=>1, 'h3'=>1, 'h4'=>1, 'h5'=>1, 'h6'=>1, 'hr'=>1, 'isindex'=>1, 'menu'=>1, 'object'=>1, 'ol'=>1, 'p'=>1, 'pre'=>1, 'table'=>1, 'ul'=>1),
                                   'td'         => array('th'=>1, 'td'=>1, 'tr'=>1, 'thead'=>1, 'tbody'=>1, 'tfoot'=>1, 'colgoup'=>1),
                                   'tfoot'      => array('thead'=>1, 'tbody'=>1, 'tfoot'=>1, 'colgoup'=>1),
                                   'th'         => array('th'=>1, 'td'=>1, 'tr'=>1, 'thead'=>1, 'tbody'=>1, 'tfoot'=>1, 'colgoup'=>1),
                                   'thead'      => array('thead'=>1, 'tbody'=>1, 'tfoot'=>1, 'colgoup'=>1),
                                   'tr'         => array('tr'=>1, 'thead'=>1, 'tbody'=>1, 'tfoot'=>1, 'colgoup'=>1),
                                   );




    /**
     * Convert <strong><em> ... </strong> ... </em>
     *    into <strong><em> ... </em></strong><em> ... </em>
     */
    function wellForm($text)
    {
        $this->tagStack = array();
//        $this->tagUsed  = array();
        $text = preg_replace_callback('#<(/?)([a-z_:][a-z0-9._:-]*)(|\s.*)(/?)>()#Uis', array(&$this, '_replaceWellForm'), $text);
        if ($this->tagStack) {
            $pair = end($this->tagStack);
            while ($pair !== FALSE) {
                $text .= '</'.$pair['tag'].'>';
                $pair = prev($this->tagStack);
            }
        }
        return $text;
    }



    /**
     * Callback function: <tag> | </tag>
     * @return string
     */
    function _replaceWellForm($matches)
    {
        list(, $mClosing, $mTag, $mAttr, $mEmpty) = $matches;
        //    [1] => /
        //    [2] => TAG
        //    [3] => ... (attributes)
        //    [4] => /   (empty)

        if (isset($GLOBALS['TexyHTML::$empty'][$mTag]) || $mEmpty) return $mClosing ? '' : '<'.$mTag.$mAttr.' />';

        if ($mClosing) {  // closing
            $pair = end($this->tagStack);
            $s = '';
            $i = 1;
            while ($pair !== FALSE) {
                $s .= '</'.$pair['tag'].'>';
                if ($pair['tag'] == $mTag) break;
                $pair = prev($this->tagStack);
                $i++;
            }
            if ($pair === FALSE) return '';

            if (isset($GLOBALS['TexyHTML::$block'][$mTag])) {
                array_splice($this->tagStack, -$i);
                return $s;
            }

            // not work in PHP 4.4.1 due bug #35063
            unset($this->tagStack[key($this->tagStack)]);
            $pair = current($this->tagStack);
            while ($pair !== FALSE) {
                $s .= '<'.$pair['tag'].$pair['attr'].'>';
                $pair = next($this->tagStack);
            }
            return $s;

        } else {        // opening

            $s = '';

            $pair = end($this->tagStack);
            while ($pair &&
                    isset($this->autoCloseElements[$pair['tag']]) &&
                    isset($this->autoCloseElements[$pair['tag']][$mTag]) ) {

                $s .= '</'.$pair['tag'].'>';
                unset($this->tagStack[key($this->tagStack)]);

                $pair = end($this->tagStack);
            }

            $pair = array(
                'attr' => $mAttr,
                'tag' => $mTag,
            );
            $this->tagStack[] = $pair;


            $s .= '<'.$mTag.$mAttr.'>';
            return $s;
        }
    }


}


?>