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

// security - include texy.php, not this file
if (!defined('TEXY')) die();


TexyHtmlWellFormer::init();

class TexyHtmlWellFormer
{
    /** @var array */
    private $tagUsed;

    /** @var array */
    private $tagStack;

    /** @see http://www.w3.org/TR/xhtml1/prohibitions.html */
    static private $prohibits = array(
        'a' => array('a','button'),
        'img' => array('pre'),
        'object' => array('pre'),
        'big' => array('pre'),
        'small' => array('pre'),
        'sub' => array('pre'),
        'sup' => array('pre'),
        'input' => array('button'),
        'select' => array('button'),
        'textarea' => array('button'),
        'label' => array('button', 'label'),
        'button' => array('button'),
        'form' => array('button', 'form'),
        'fieldset' => array('button'),
        'iframe' => array('button'),
        'isindex' => array('button'),
    );

    /** @var array */
    static private $optional;

    /** @var array */
    static private $content;




    /**
     * Converts <strong><em> ... </strong> ... </em>
     * into <strong><em> ... </em></strong><em> ... </em>
     */
    public function process($text)
    {
        $this->tagStack = array();
        $this->tagUsed  = array();

        $text = preg_replace_callback('#<(/?)([a-z][a-z0-9._:-]*)(|\s.*)(/?)>|([^<]++)#Uis', array($this, 'cb'), $text);

        foreach ($this->tagStack as $item)
            if ($item['show']) $text .= '</'.$item['tag'].'>';

        return $text;
    }



    /**
     * Callback function: <tag> | </tag>
     * @return string
     */
    private function cb($matches)
    {
        // stuff between tags
        if (isset($matches[5]))
        {
            $item = reset($this->tagStack);
            if ($item && $item['content'] && !isset($item['content']['%DATA'])) return '';
            return $matches[5];
        }

        list(, $mEnd, $mTag, $mAttr, $mEmpty) = $matches;
        //    [1] => /
        //    [2] => TAG
        //    [3] => ... (attributes)
        //    [4] => /   (empty)

        //if (isset(TexyHtml::$emptyTags[$mTag]) || $mEmpty) return $mEnd ? '' : '<'.$mTag.$mAttr.$mEmpty.'>';
        $mEmpty = $mEmpty || isset(TexyHtml::$emptyTags[$mTag]);
        if ($mEmpty && $mEnd) return ''; // error


        if ($mEnd) {  // end tag

            // has start tag?
            if (empty($this->tagUsed[$mTag])) return '';

            // autoclose tags
            $s = '';
            $tmp = array();
            $back = TRUE;
            foreach ($this->tagStack as $i => $item)
            {
                $tag = $item['tag'];
                if ($item['show']) $s .= '</'.$tag.'>';
                $this->tagUsed[$tag]--;
                $back = $back && isset(TexyHtml::$inlineTags[$tag]);
                unset($this->tagStack[$i]);
                if ($tag === $mTag) break;
                array_unshift($tmp, $item);
            }

            if (!$back || !$tmp) return $s;

            // allowed-check (nejspis neni ani potreba)
            $item = reset($this->tagStack);
            if ($item && $item['content'] && !isset($item['content'][$tmp[0]['tag']])) return $s;

            // autoopen tags
            foreach ($tmp as $item)
            {
                if ($item['show']) $s .= '<'.$item['tag'].$item['attr'].'>';
                $this->tagUsed[$item['tag']]++;
                array_unshift($this->tagStack, $item);
            }

            return $s;


        } else { // start tag

            $show = TRUE;
            $content = NULL;
            $s = '';

            // check element deep prohibitions
            if (isset(self::$prohibits[$mTag])) {
                foreach (self::$prohibits[$mTag] as $pTag)
                    if (!empty($this->tagUsed[$pTag])) { $show = FALSE; break; }
            }

            foreach ($this->tagStack as $i => $item)
            {
                $content = $item['content'];

                if (!$show || !$item['content'] || isset($content[$mTag])) break;

                if (!$item['show'] || isset(self::$optional[ $item['tag'] ])) {
                    if ($item['show']) $s .= '</'.$item['tag'].'>';
                    $this->tagUsed[$item['tag']]--;
                    unset($this->tagStack[$i]);
                    $content = NULL;
                    continue;
                }

                $show = FALSE; break;
            }

            if ($mEmpty) {
                if ($show) $s .= $matches[0];
                return $s;
            }

            if ($show) {
                if ($mTag === 'ins' || $mTag === 'del') {
                    // special case, leave $content
                } else {
                    if (isset(self::$content[$mTag]))
                        $content = self::$content[$mTag];
                    else
                        $content = NULL;
                }
                $s .= '<'.$mTag.$mAttr.'>';
            }

            // open tag, put to stack, increase counter
            $item = array(
                'attr' => $mAttr,
                'tag' => $mTag,
                'show' => $show,
                'content' => $content,
            );
            array_unshift($this->tagStack, $item);
            $tmp = &$this->tagUsed[$mTag]; $tmp++;

            return $s;
        }
    }




    /**
     * Initializes self::$content & self::$optional arrays
     */
    static public function init()
    {
        self::$optional = array_flip(array('colgroup','dd','dt','li','option',
        'p','tbody','td','tfoot','th','thead','tr'));

        // %block;
        $b = array_flip(array('ins','del','p','h1','h2','h3','h4','h5','h6','ul','ol',
        'dir','menu','dl','pre','div','center','blockquote','iframe','noscript','noframes',
        'form','isindex','hr','table','address','fieldset'));

        // %inline;
        $i = array_flip(array('ins','del','tt','i','b','u','s','strike','big','small','font','em','strong',
        'dfn','code','samp','kbd','var','cite','abbr','acronym','sub','sup','q','span','bdo','a','object',
        'applet','img','basefont','br','script','map','input','select','textarea','label','button','%DATA'));

        // DTD - compromise between loose and strict
	self::$content = array(
        'html' => array('head'=>1, 'body'=>1),
        'head' => array('title'=>1, 'script'=>1, 'style'=>1, 'base'=>1, 'meta'=>1, 'link'=>1, 'object'=>1, 'isindex'=>1),
        'title' => array('%DATA'=>1),
	'body' => $b + $i, // HTML loose: array('script'=>1) + $b,
        'script' => NULL, //array('%DATA'=>1),
        'style' => NULL, //array('%DATA'=>1),
        'p' => $i,
        'h1' => $i,
        'h2' => $i,
        'h3' => $i,
        'h4' => $i,
        'h5' => $i,
        'h6' => $i,
        'ul' => array('li'=>1),
        'ol' => array('li'=>1),
        'li' => $b + $i,
        'dir' => array('li'=>1),
        'menu' => array('li'=>1), // inline li, ignored
        'dl' => array('dt'=>1,'dd'=>1),
        'dt' => $i,
        'dd' => $b + $i,
        'pre' => array_flip(array('tt','i','b','u','s','strike','em','strong','dfn','code',
            'samp','kbd','var','cite','abbr','acronym','q','span','bdo','a','br','script',
            'map','input','select','textarea','label','button','%DATA')),
        'div' => $b + $i,
        'center' => $b + $i,
        'blockquote' => array('script'=>1) + $b,
        'iframe' => $b + $i,
        'noscript' => $b + $i,
        'noframes' => $b + $i,
        'form' => array('script'=>1) + $b,
        'table' => array('caption'=>1,'colgroup'=>1,'col'=>1,'thead'=>1,'tbody'=>1,'tfoot'=>1,'tr'=>1),
        'caption' => $i,
        'colgroup' => array('col'=>1),
        'thead' => array('tr'=>1),
        'tbody' => array('tr'=>1),
        'tfoot' => array('tr'=>1),
        'tr' => array('td'=>1,'th'=>1),
        'td' => $b + $i,
        'th' => $b + $i,
        'address' => $i,
        'fieldset' => array('legend'=>1) + $b + $i,
        'legend' => $i,
        'tt' => $i,
        'i' => $i,
        'b' => $i,
        'u' => $i,
        's' => $i,
        'strike' => $i,
        'big' => $i,
        'small' => $i,
        'font' => $i,
        'em' => $i,
        'strong' => $i,
        'dfn' => $i,
        'code' => $i,
        'samp' => $i,
        'kbd' => $i,
        'var' => $i,
        'cite' => $i,
        'abbr' => $i,
        'acronym' => $i,
        'sub' => $i,
        'sup' => $i,
        'q' => $i,
        'span' => $i,
        'bdo' => $i,
        'a' => $i,
        'object' => array('param'=>1) + $b + $i,
        'applet' => array('param'=>1) + $b + $i,
        'map' => array('area'=>1) + $b,
        'select' => array('option'=>1,'optgroup'=>1),
        'optgroup' => array('option'=>1),
        'option' => array('%DATA'=>1),
        'textarea' => array('%DATA'=>1),
        'label' => $i, // - label by self::$prohibits
        'button' => $b + $i, // - a input select textarea label button form fieldset, by self::$prohibits
        );
    }


    /**
     * Undefined property usage prevention
     */
    function __get($nm) { throw new Exception("Undefined property '" . get_class($this) . "::$$nm'"); }
    function __set($nm, $val) { $this->__get($nm); }

}
