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


class TexyHtmlFormatter
{
    /** @var bool  indent HTML code? */
    public $indent = TRUE;

    /** @var int  base indent level */
    public $baseIndent = 0;

    /** @var int  wrap width, doesn't include indent space */
    public $lineWrap = 80;



    /** @var array  DTD */
    static public $dtd;

    /** @var array  inline elements */
    static public $inline;

    /** @var array  elements with optional end tag */
    static private $optional;

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
    private $tagUsed;

    /** @var array */
    private $tagStack;

    /** @var int  indent space counter */
    private $space;


    public function __construct()
    {
        if (!self::$dtd) self::init();
    }


    /**
     * Converts <strong><em> ... </strong> ... </em>
     * into <strong><em> ... </em></strong><em> ... </em>
     */
    public function process($text)
    {
        $this->space = $this->baseIndent;
        $this->tagStack = array();
        $this->tagUsed  = array();

        // wellform and reformat
        $text = preg_replace_callback('#<(/?)([a-z][a-z0-9._:-]*)(|\s.*)(/?)>|([^<]++)#Uis', array($this, 'cb'), $text);

        foreach ($this->tagStack as $item) $text .= $item['close'];

        // right trim
        $text = preg_replace("#[\t ]+(\n|\r|$)#", '$1', $text); // right trim

        // join double \r to single \n
        $text = str_replace("\r\r", "\n", $text);
        $text = strtr($text, "\r", "\n");

        // greedy chars
        $text = preg_replace("#\\t? *\\x08 *#", '', $text);

        // line wrap
        if ($this->lineWrap > 0)
            $text = preg_replace_callback(
                '#^(\t*)(.*)$#m',
                array($this, 'wrap'),
                $text
            );

        return $text;
    }



    /**
     * Callback function: <tag> | </tag> | ....
     * @return string
     */
    private function cb($matches)
    {
        // stuff between tags
        if (isset($matches[5]))
        {
            $item = reset($this->tagStack);
            if ($item) {
                // text not allowed?
                if ($item['content'] && !isset($item['content']['%DATA'])) return '';

                // inside pre & textarea preserve spaces
                if (!empty($this->tagUsed['pre']) || !empty($this->tagUsed['textarea']))
                    return Texy::freezeSpaces($matches[5]);

            }

            // otherwise shrink multiple spaces
            return preg_replace('#[ \n]+#', ' ', $matches[5]);
        }

        // html tag
        list(, $mEnd, $mTag, $mAttr, $mEmpty) = $matches;
        //    [1] => /
        //    [2] => TAG
        //    [3] => ... (attributes)
        //    [4] => /   (empty)

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
                if ($item['close']) {
                    $s .= $item['close'];
                    if (!isset(self::$inline[$tag])) $this->space--;
                }
                $this->tagUsed[$tag]--;
                $back = $back && isset(self::$inline[$tag]);
                unset($this->tagStack[$i]);
                if ($tag === $mTag) break;
                array_unshift($tmp, $item);
            }

            if (!$back || !$tmp) return $s;

            // allowed-check (nejspis neni ani potreba)
            $item = reset($this->tagStack);
            if ($item) $content = $item['content'];
            else $content = self::$dtd[NULL];
            if ($content && !isset($content[$tmp[0]['tag']])) return $s;

            // autoopen tags
            foreach ($tmp as $item)
            {
                if ($item['close']) $s .= '<'.$item['tag'].$item['attr'].'>';
                $this->tagUsed[$item['tag']]++;
                array_unshift($this->tagStack, $item);
            }

            return $s;


        } else { // start tag

            $s = '';
            $content = self::$dtd[NULL];

            // optional end tag closing
            foreach ($this->tagStack as $i => $item)
            {
                // is tag allowed here?
                $content = $item['content'];
                if (!$content || isset($content[$mTag])) break;

                $tag = $item['tag'];

                // auto-close hidden, optional and inline tags
                if ($item['close'] && (!isset(self::$optional[$tag]) && !isset(self::$inline[$tag]))) break;

                // close it
                if ($item['close']) {
                    $s .= $item['close'];
                    if (!isset(self::$inline[$tag])) $this->space--;
                }
                $this->tagUsed[$tag]--;
                unset($this->tagStack[$i]);
                $content = self::$dtd[NULL];
            }

            // is tag allowed in this content?
            $allowed = !$content || isset($content[$mTag]);

            // check deep element prohibitions
            if ($allowed && isset(self::$prohibits[$mTag])) {
                foreach (self::$prohibits[$mTag] as $pTag)
                    if (!empty($this->tagUsed[$pTag])) { $allowed = FALSE; break; }
            }

            // empty elements se neukladaji do stack
            if ($mEmpty) {
                if (!$allowed) return $s;
                if ($this->indent && $mTag === 'br')
                    // formatting exception
                    return $s . "\n" . str_repeat("\t", max(0, $this->space - 1)) . $matches[0] . "\x08";
                elseif ($this->indent && !isset(self::$inline[$mTag])) {
                    $space = "\r" . str_repeat("\t", $this->space);
                    return $s . $space . $matches[0] . $space;
                } else
                    return $s . $matches[0];
            }

            if ($allowed) {
                // receive new content (ins & del are special cases)
                if (!empty(self::$dtd[$mTag])) $content = self::$dtd[$mTag];

                // format output
                if ($this->indent && !isset(self::$inline[$mTag])) {
                    $close = "\x08" . '</'.$mTag.'>' . "\n" . str_repeat("\t", $this->space);
                    $s .= "\n" . str_repeat("\t", $this->space++) . '<'.$mTag.$mAttr.'>' . "\x08";
                } else {
                    $close = '</'.$mTag.'>';
                    $s .= '<'.$mTag.$mAttr.'>';
                }

                // TODO: problematic formatting of select / options, object / params

            } else $close = '';

            // open tag, put to stack, increase counter
            $item = array(
                'tag' => $mTag,
                'attr' => $mAttr,
                'close' => $close,
                'content' => $content,
            );
            array_unshift($this->tagStack, $item);
            $tmp = &$this->tagUsed[$mTag]; $tmp++;

            return $s;
        }
    }



    /**
     * Callback function: wrap lines
     * @return string
     */
    private function wrap($m)
    {
        list(, $space, $s) = $m;
        return $space . wordwrap($s, $this->lineWrap, "\n" . $space);
    }



    /**
     * Initializes self::$dtd & self::$optional arrays
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
        self::$inline = $i = array_flip(array('ins','del','tt','i','b','u','s','strike','big','small','font','em','strong',
            'dfn','code','samp','kbd','var','cite','abbr','acronym','sub','sup','q','span','bdo','a','object',
            'applet','img','basefont','br','script','map','input','select','textarea','label','button','%DATA'));

        // DTD - compromise between loose and strict
        self::$dtd = array(
            NULL => $b + $i + array('html'=>1), // special "base content"

            'html' => array('head'=>1, 'body'=>1),
            'head' => array('title'=>1, 'script'=>1, 'style'=>1, 'base'=>1, 'meta'=>1, 'link'=>1, 'object'=>1, 'isindex'=>1),
            'title' => array('%DATA'=>1),
            'body' => Texy::$strictDTD ? array('script'=>1) + $b : $b + $i,
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
            'dl' => array('dt'=>1,'dd'=>1),
            'dt' => $i,
            'dd' => $b + $i,
            'pre' => array_flip(array('tt','i','b','u','s','strike','em','strong','dfn','code',
                'samp','kbd','var','cite','abbr','acronym','q','span','bdo','a','br','script',
                'map','input','select','textarea','label','button','%DATA')),
            'div' => $b + $i,
            'blockquote' => Texy::$strictDTD ? array('script'=>1) + $b : $b + $i,
            'noscript' => $b + $i,
            'form' => Texy::$strictDTD ? array('script'=>1) + $b : $b + $i,
            'table' => array('caption'=>1,'colgroup'=>1,'col'=>1,'thead'=>1,'tbody'=>1,'tfoot'=>1,'tr'=>1),
            'caption' => $i,
            'colgroup' => array('col'=>1),
            'thead' => array('tr'=>1),
            'tbody' => array('tr'=>1),
            'tfoot' => array('tr'=>1),
            'tr' => array('td'=>1,'th'=>1),
            'td' => $b + $i,
            'th' => $b + $i,
            'address' => Texy::$strictDTD ? $i : array('p'=>1) + $i,
            'fieldset' => array('legend'=>1) + $b + $i,
            'legend' => $i,
            'tt' => $i,
            'i' => $i,
            'b' => $i,
            'big' => $i,
            'small' => $i,
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
            'map' => array('area'=>1) + $b,
            'select' => array('option'=>1,'optgroup'=>1),
            'optgroup' => array('option'=>1),
            'option' => array('%DATA'=>1),
            'textarea' => array('%DATA'=>1),
            'label' => $i, // - label by self::$prohibits
            'button' => $b + $i, // - a input select textarea label button form fieldset, by self::$prohibits
            'ins' => NULL, // special
            'del' => NULL, // special
            // empty
            'img' => FALSE,
            'hr' => FALSE,
            'br' => FALSE,
            'input' => FALSE,
            'meta' => FALSE,
            'area' => FALSE,
            'base' => FALSE,
            'col' => FALSE,
            'link' => FALSE,
            'param' => FALSE,
        );

        if (!Texy::$strictDTD) self::$dtd += array(
            'dir' => array('li'=>1),
            'menu' => array('li'=>1), // it's inline-li, ignored
            'center' => $b + $i,
            'iframe' => $b + $i,
            'noframes' => $b + $i,
            'u' => $i,
            's' => $i,
            'strike' => $i,
            'font' => $i,
            'applet' => array('param'=>1) + $b + $i,
            'basefont' => FALSE,
            'frame' => FALSE,
            'isindex' => FALSE,
        );
    }


    /**
     * Undefined property usage prevention
     */
    function __get($nm) { throw new Exception("Undefined property '" . get_class($this) . "::$$nm'"); }
    function __set($nm, $val) { $this->__get($nm); }

}
