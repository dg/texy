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
     * Build string which represents (X)HTML opening tag
     * @param string   tag
     * @param array    associative array of attributes and values ( / mean empty tag, arrays are imploded )
     * @return string
     * @static
     */
    static public function openingTag($tag, $attrs)
    {
        if ($tag == NULL) return '';

        $empty = isset(self::$empty[$tag]) || isset($attrs[self::EMPTYTAG]);

        $attrStr = '';
        if (is_array($attrs)) {
            unset($attrs[self::EMPTYTAG]);

            foreach (array_change_key_case($attrs, CASE_LOWER) as $name => $value) {
                if (is_array($value)) {
                    if ($name === 'style') {
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

        return '<' . $tag . $attrStr . ($empty ? ' /' : '') . '>';
    }



    /**
     * Build string which represents (X)HTML opening tag
     * @return string
     * @static
     */
    static public function closingTag($tag)
    {
        if ($tag == NULL || isset(self::$empty[$tag]) || isset($attrs[self::EMPTYTAG])) return '';

        return '</'.$tag.'>';
    }



    /**
     * Undefined property usage prevention
     */
    function __get($nm) { throw new Exception("Undefined property '" . get_class($this) . "::$$nm'"); }
    function __set($nm, $val) { $this->__get($nm); }
    private function __unset($nm) { $this->__get($nm); }
    private function __isset($nm) { $this->__get($nm); }
}