<?php

/**
 * This file is part of the Texy! formatter (http://texy.info/)
 *
 * Copyright (c) 2004-2007 David Grudl aka -dgx- <dave@dgx.cz>
 *
 * @version  $Revision$ $Date$
 * @package  Texy
 */

// security - include texy.php, not this file
if (!class_exists('Texy', FALSE)) die();


class TexyHtmlFormatter
{
    /** @var bool  indent HTML code? */
    public $indent = TRUE;

    /** @var int  base indent level */
    public $baseIndent = 0;

    /** @var int  wrap width, doesn't include indent space */
    public $lineWrap = 80;

    /** @var int  indent space counter */
    private $space;



    /** @var array  DTD */
    static public $dtd;

    /** @var array  elements with optional end tag in HTML */
    static private $optional = array('colgroup'=>1,'dd'=>1,'dt'=>1,'li'=>1,'option'=>1,
        'p'=>1,'tbody'=>1,'td'=>1,'tfoot'=>1,'th'=>1,'thead'=>1,'tr'=>1);

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

    /** @var array  %block; elements */
    static public $block = array('ins'=>1,'del'=>1,'p'=>1,'h1'=>1,'h2'=>1,'h3'=>1,'h4'=>1,
        'h5'=>1,'h6'=>1,'ul'=>1,'ol'=>1,'dl'=>1,'pre'=>1,'div'=>1,'blockquote'=>1,'noscript'=>1,
        'noframes'=>1,'form'=>1,'hr'=>1,'table'=>1,'address'=>1,'fieldset'=>1);

    static public $_blockLoose = array(
        'dir'=>1,'menu'=>1,'center'=>1,'iframe'=>1,'isindex'=>1, // transitional
        'marquee'=>1, // proprietary
    );

    /** @var array  %inline; elements */
    static public $inline = array('ins'=>1,'del'=>1,'tt'=>1,'i'=>1,'b'=>1,'big'=>1,'small'=>1,'em'=>1,
        'strong'=>1,'dfn'=>1,'code'=>1,'samp'=>1,'kbd'=>1,'var'=>1,'cite'=>1,'abbr'=>1,'acronym'=>1,
        'sub'=>1,'sup'=>1,'q'=>1,'span'=>1,'bdo'=>1,'a'=>1,'object'=>1,'img'=>1,'br'=>1,'script'=>1,
        'map'=>1,'input'=>1,'select'=>1,'textarea'=>1,'label'=>1,'button'=>1,'%DATA'=>1);

    static public $_inlineLoose = array(
        'u'=>1,'s'=>1,'strike'=>1,'font'=>1,'applet'=>1,'basefont'=>1, // transitional
        'embed'=>1,'wbr'=>1,'nobr'=>1,'canvas'=>1, // proprietary
    );

    /** @var array */
    private $tagUsed;

    /** @var array */
    private $tagStack;

    /** @var Texy */
    private $texy;



    public function __construct($texy)
    {
        $this->texy = $texy;
        if (!self::$dtd) self::initDTD();
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
        $text = preg_replace_callback(
            '#(.*)<(?:(!--.*--)|(/?)([a-z][a-z0-9._:-]*)(|[ \n].*)(/?))>()#Uis',
            array($this, 'cb'),
            $text . '</end/>'
        );

        // empty out stack
        foreach ($this->tagStack as $item) $text .= $item['close'];

        // right trim
        $text = preg_replace("#[\t ]+(\n|\r|$)#", '$1', $text); // right trim

        // join double \r to single \n
        $text = str_replace("\r\r", "\n", $text);
        $text = strtr($text, "\r", "\n");

        // greedy chars
        $text = preg_replace("#\\x07 *#", '', $text);
        // back-tabs
        $text = preg_replace("#\\t? *\\x08#", '', $text);

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
        // html tag
        list(, $mText, $mComment, $mEnd, $mTag, $mAttr, $mEmpty) = $matches;
        //    [1] => text
        //    [1] => !-- comment --
        //    [2] => /
        //    [3] => TAG
        //    [4] => ... (attributes)
        //    [5] => /   (empty)

        $s = '';

        // phase #1 - stuff between tags
        if ($mText !== '') {
            $item = reset($this->tagStack);
            // text not allowed?
            if ($item && !isset($item['content']['%DATA'])) { }

            // inside pre & textarea preserve spaces
            elseif (!empty($this->tagUsed['pre']) || !empty($this->tagUsed['textarea']))
                $s = Texy::freezeSpaces($mText);

            // otherwise shrink multiple spaces
            else $s = preg_replace('#[ \n]+#', ' ', $mText);
        }


        // phase #2 - HTML comment
        if ($mComment) return $s . '<' . Texy::freezeSpaces($mComment) . '>';


        // phase #3 - HTML tag
        $mEmpty = $mEmpty || isset(TexyHtml::$emptyTags[$mTag]);
        if ($mEmpty && $mEnd) return $s; // bad tag; /end/


        if ($mEnd) {  // end tag

            // has start tag?
            if (empty($this->tagUsed[$mTag])) return $s;

            // autoclose tags
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
            if (!isset($content[$tmp[0]['tag']])) return $s;

            // autoopen tags
            foreach ($tmp as $item)
            {
                if ($item['close']) $s .= '<'.$item['tag'].$item['attr'].'>';
                $this->tagUsed[$item['tag']]++;
                array_unshift($this->tagStack, $item);
            }


        } else { // start tag

            $content = self::$dtd[NULL];

            if (!isset(self::$dtd[$mTag])) {
                // unknown (non-html) tag
                $allowed = $this->texy->allowedTags === Texy::ALL;
                $item = reset($this->tagStack);
                if ($item) $content = $item['content'];


            } else {
                // optional end tag closing
                foreach ($this->tagStack as $i => $item)
                {
                    // is tag allowed here?
                    $content = $item['content'];
                    if (isset($content[$mTag])) break;

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
                $allowed = isset($content[$mTag]);

                // check deep element prohibitions
                if ($allowed && isset(self::$prohibits[$mTag])) {
                    foreach (self::$prohibits[$mTag] as $pTag)
                        if (!empty($this->tagUsed[$pTag])) { $allowed = FALSE; break; }
                }
            }

            // empty elements se neukladaji do zasobniku
            if ($mEmpty) {
                if (!$allowed) return $s;

                if ($this->indent && $mTag === 'br')
                    // formatting exception
                    return rtrim($s) .  '<' . $mTag . $mAttr . "/>\n" . str_repeat("\t", max(0, $this->space - 1)) . "\x07";

                if ($this->indent && !isset(self::$inline[$mTag])) {
                    $space = "\r" . str_repeat("\t", $this->space);
                    return $s . $space . '<' . $mTag . $mAttr . '/>' . $space;
                }

                return $s . '<' . $mTag . $mAttr . '/>';
            }


            if ($allowed) {
                // receive new content (ins & del are special cases)
                if (!empty(self::$dtd[$mTag])) $content = self::$dtd[$mTag];

                // format output
                if ($this->indent && !isset(self::$inline[$mTag])) {
                    $close = "\x08" . '</'.$mTag.'>' . "\n" . str_repeat("\t", $this->space);
                    $s .= "\n" . str_repeat("\t", $this->space++) . '<'.$mTag.$mAttr.'>' . "\x07";
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
        }

        return $s;
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
     * Initializes self::$dtd array
     */
    static public function initDTD()
    {
        // %block;
        if (!Texy::$strictDTD) self::$block += self::$_blockLoose;
        $b = self::$block;

        // %inline;
        if (!Texy::$strictDTD) self::$inline += self::$_inlineLoose;
        $i = self::$inline;

        // DTD - compromise between loose and strict
        self::$dtd = array(
            NULL => $b + $i + array('html'=>1), // special "base content"

            'html' => array('head'=>1, 'body'=>1),
            'head' => array('title'=>1, 'script'=>1, 'style'=>1, 'base'=>1, 'meta'=>1, 'link'=>1, 'object'=>1, 'isindex'=>1),
            'title' => array('%DATA'=>1),
            'body' => Texy::$strictDTD ? array('script'=>1) + $b : $b + $i,
            'script' => array('%DATA'=>1),
            'style' => array('%DATA'=>1),
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
            'pre' => array_flip(array_diff(array_keys($i), array('img','object','applet','big','small','sub','sup','font','basefont'))),
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
            // special cases
            'ins' => 0,
            'del' => 0,
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
            // transitional
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

            // proprietary
            'marquee'=> $b + $i,
            'nobr'=> $i,
            'canvas'=> $i,
            'embed'=> FALSE,
            'wbr' => FALSE,
        );

        // missing: FRAMESET, FRAME, BGSOUND, XMP, ...
    }


    /**
     * Undefined property usage prevention
     */
    function __get($nm) { throw new Exception("Undefined property '" . get_class($this) . "::$$nm'"); }
    function __set($nm, $val) { $this->__get($nm); }

}
