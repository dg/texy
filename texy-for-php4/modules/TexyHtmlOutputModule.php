<?php

/**
 * Texy! - web text markup-language (for PHP 4)
 * --------------------------------------------
 *
 * Copyright (c) 2004, 2007 David Grudl aka -dgx- (http://www.dgx.cz)
 *
 * This source file is subject to the GNU GPL license that is bundled
 * with this package in the file license.txt.
 *
 * For more information please see http://texy.info/
 *
 * @author     David Grudl
 * @copyright  Copyright (c) 2004, 2007 David Grudl
 * @license    GNU GENERAL PUBLIC LICENSE version 2 or 3
 * @category   Text
 * @package    Texy
 * @link       http://texy.info/
 */



/**
 * @package Texy
 * @version $Revision$ $Date$
 */
class TexyHtmlOutputModule extends TexyModule
{
    /** @var bool  use XHTML syntax? */
    var $xhtml = TRUE;

    /** @var bool  indent HTML code? */
    var $indent = TRUE;

    /** @var int  base indent level */
    var $baseIndent = 0;

    /** @var int  wrap width, doesn't include indent space */
    var $lineWrap = 80;

    /** @var bool  remove optional HTML end tags? */
    var $removeOptional = TRUE;

    /** @var int  indent space counter */
    var $space; /* private */

    /** @var array */
    var $tagUsed; /* private */

    /** @var array */
    var $tagStack; /* private */

    /** @var array  content DTD used, when context is not defined */
    var $baseDTD; /* private */



    function __construct($texy)
    {
        $this->texy = $texy;
        $texy->addHandler('postProcess', array($this, 'postProcess'));
    }



    /**
     * Converts <strong><em> ... </strong> ... </em>
     * into <strong><em> ... </em></strong><em> ... </em>
     */
    function postProcess($texy, & $s)
    {
        $this->space = $this->baseIndent;
        $this->tagStack = array();
        $this->tagUsed  = array();

        // special "base content"
        $dtd = $GLOBALS['TexyHtml::$dtd'];
        $this->baseDTD = $dtd['div'][1] + $dtd['html'][1] /*+ $dtd['head'][1]*/ + $dtd['body'][1] + array('html'=>1);

        // wellform and reformat
        $s = preg_replace_callback(
            '#(.*)<(?:(!--.*--)|(/?)([a-z][a-z0-9._:-]*)(|[ \n].*)\s*(/?))>()#Uis',
            array($this, 'cb'),
            $s . '</end/>'
        );

        // empty out stack
        foreach ($this->tagStack as $item) $s .= $item['close'];

        // right trim
        $s = preg_replace("#[\t ]+(\n|\r|$)#", '$1', $s); // right trim

        // join double \r to single \n
        $s = str_replace("\r\r", "\n", $s);
        $s = strtr($s, "\r", "\n");

        // greedy chars
        $s = preg_replace("#\\x07 *#", '', $s);
        // back-tabs
        $s = preg_replace("#\\t? *\\x08#", '', $s);

        // line wrap
        if ($this->lineWrap > 0)
            $s = preg_replace_callback(
                '#^(\t*)(.*)$#m',
                array($this, 'wrap'),
                $s
            );

        // remove HTML 4.01 optional end tags
        if (!$this->xhtml && $this->removeOptional)
            $s = preg_replace('#\\s*</(colgroup|dd|dt|li|option|p|td|tfoot|th|thead|tr)>#u', '', $s);
    }



    /**
     * Callback function: <tag> | </tag> | ....
     * @return string
     */
    function cb($matches) /* private */
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
            if ($item && !isset($item['dtdContent']['%DATA'])) { }

            // inside pre & textarea preserve spaces
            elseif (!empty($this->tagUsed['pre']) || !empty($this->tagUsed['textarea']) || !empty($this->tagUsed['script']))
                $s = Texy::freezeSpaces($mText);

            // otherwise shrink multiple spaces
            else $s = preg_replace('#[ \n]+#', ' ', $mText);
        }


        // phase #2 - HTML comment
        if ($mComment) return $s . '<' . Texy::freezeSpaces($mComment) . '>';


        // phase #3 - HTML tag
        $mEmpty = $mEmpty || isset($GLOBALS['TexyHtml::$emptyElements'][$mTag]);
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
                $s .= $item['close'];
                $this->space -= $item['indent'];
                $this->tagUsed[$tag]--;
                $back = $back && isset($GLOBALS['TexyHtml::$inlineElements'][$tag]);
                unset($this->tagStack[$i]);
                if ($tag === $mTag) break;
                array_unshift($tmp, $item);
            }

            if (!$back || !$tmp) return $s;

            // allowed-check (nejspis neni ani potreba)
            $item = reset($this->tagStack);
            if ($item) $dtdContent = $item['dtdContent'];
            else $dtdContent = $this->baseDTD;
            if (!isset($dtdContent[$tmp[0]['tag']])) return $s;

            // autoopen tags
            foreach ($tmp as $item)
            {
                $s .= $item['open'];
                $this->space += $item['indent'];
                $this->tagUsed[$item['tag']]++;
                array_unshift($this->tagStack, $item);
            }


        } else { // start tag

            $dtdContent = $this->baseDTD;
            $dtd = $GLOBALS['TexyHtml::$dtd'];

            if (!isset($dtd[$mTag])) {
                // unknown (non-html) tag
                $allowed = TRUE;
                $item = reset($this->tagStack);
                if ($item) $dtdContent = $item['dtdContent'];


            } else {
                // optional end tag closing
                foreach ($this->tagStack as $i => $item)
                {
                    // is tag allowed here?
                    $dtdContent = $item['dtdContent'];
                    if (isset($dtdContent[$mTag])) break;

                    $tag = $item['tag'];

                    // auto-close hidden, optional and inline tags
                    if ($item['close'] && (!isset($GLOBALS['TexyHtml::$optionalEnds'][$tag]) && !isset($GLOBALS['TexyHtml::$inlineElements'][$tag]))) break;

                    // close it
                    $s .= $item['close'];
                    $this->space -= $item['indent'];
                    $this->tagUsed[$tag]--;
                    unset($this->tagStack[$i]);
                    $dtdContent = $this->baseDTD;
                }

                // is tag allowed in this content?
                $allowed = isset($dtdContent[$mTag]);

                // check deep element prohibitions
                if ($allowed && isset($GLOBALS['TexyHtml::$prohibits'][$mTag])) {
                    foreach ($GLOBALS['TexyHtml::$prohibits'][$mTag] as $pTag)
                        if (!empty($this->tagUsed[$pTag])) { $allowed = FALSE; break; }
                }
            }

            // empty elements se neukladaji do zasobniku
            if ($mEmpty) {
                if (!$allowed) return $s;

                if ($this->xhtml) $mAttr .= " /";

                if ($this->indent && $mTag === 'br')
                    // formatting exception
                    return rtrim($s) .  '<' . $mTag . $mAttr . ">\n" . str_repeat("\t", max(0, $this->space - 1)) . "\x07";

                if ($this->indent && !isset($GLOBALS['TexyHtml::$inlineElements'][$mTag])) {
                    $space = "\r" . str_repeat("\t", $this->space);
                    return $s . $space . '<' . $mTag . $mAttr . '>' . $space;
                }

                return $s . '<' . $mTag . $mAttr . '>';
            }

            $open = NULL;
            $close = NULL;
            $indent = 0;

            /*
            if (!isset($GLOBALS['TexyHtml::$inlineElements'][$mTag])) {
                // block tags always decorate with \n
                $s .= "\n";
                $close = "\n";
            }
            */

            if ($allowed) {
                $open = '<' . $mTag . $mAttr . '>';

                // receive new content (ins & del are special cases)
                if (!empty($dtd[$mTag][1])) $dtdContent = $dtd[$mTag][1];

                // format output
                if ($this->indent && !isset($GLOBALS['TexyHtml::$inlineElements'][$mTag])) {
                    $close = "\x08" . '</'.$mTag.'>' . "\n" . str_repeat("\t", $this->space);
                    $s .= "\n" . str_repeat("\t", $this->space++) . $open . "\x07";
                    $indent = 1;
                } else {
                    $close = '</'.$mTag.'>';
                    $s .= $open;
                }

                // TODO: problematic formatting of select / options, object / params
            }


            // open tag, put to stack, increase counter
            $item = array(
                'tag' => $mTag,
                'open' => $open,
                'close' => $close,
                'dtdContent' => $dtdContent,
                'indent' => $indent,
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
    function wrap($m) /* private */
    {
        list(, $space, $s) = $m;
        return $space . wordwrap($s, $this->lineWrap, "\n" . $space);
    }

}
