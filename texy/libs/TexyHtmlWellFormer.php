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



class TexyHtmlWellFormer
{
    /** @var array */
    private $tagUsed;

    /** @var array */
    private $tagStack;

    /** @see http://www.w3.org/TR/xhtml1/prohibitions.html */
    private $prohibits = array(
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

    private $autoClose = array(
        'thead' => array('tbody'=>1,'colgroup'=>1,'td'=>1,'tfoot'=>1,'th'=>1,'thead'=>1,'tr'=>1),
        'tbody' => array('tbody'=>1,'colgroup'=>1,'td'=>1,'tfoot'=>1,'th'=>1,'thead'=>1,'tr'=>1),
        'tfoot' => array('tbody'=>1,'colgroup'=>1,'td'=>1,'tfoot'=>1,'th'=>1,'thead'=>1,'tr'=>1),
        'colgoup' => array('tbody'=>1,'colgroup'=>1,'td'=>1,'tfoot'=>1,'th'=>1,'thead'=>1,'tr'=>1),
        'dt' => array('dd'=>1,'dt'=>1),
        'dd' => array('dd'=>1,'dt'=>1),
        'li' => array('li'=>1),
        'option' => array('option'=>1),
        'address' => array('p'=>1),
        'applet' => array('p'=>1),
        'blockquote' => array('p'=>1),
        'center' => array('p'=>1),
        'dir' => array('p'=>1),
        'div' => array('p'=>1),
        'dl' => array('p'=>1),
        'fieldset' => array('p'=>1),
        'form' => array('p'=>1),
        'h1' => array('p'=>1),
        'h2' => array('p'=>1),
        'h3' => array('p'=>1),
        'h4' => array('p'=>1),
        'h5' => array('p'=>1),
        'h6' => array('p'=>1),
        'hr' => array('p'=>1),
        'isindex' => array('p'=>1),
        'menu' => array('p'=>1),
        'object' => array('p'=>1),
        'ol' => array('p'=>1),
        'p' => array('p'=>1),
        'pre' => array('p'=>1),
        'table' => array('p'=>1),
        'ul' => array('p'=>1),
        'th' => array('td'=>1,'th'=>1),
        'td' => array('td'=>1,'th'=>1),
        'tr' => array('td'=>1,'th'=>1,'tr'=>1),
    );




    /**
     * Converts <strong><em> ... </strong> ... </em>
     * into <strong><em> ... </em></strong><em> ... </em>
     */
    public function process($text)
    {
        $this->tagStack = array();
        $this->tagUsed  = array();
        $text = preg_replace_callback('#<(/?)([a-z][a-z0-9._:-]*)(|\s.*)(/?)>()#Uis', array($this, 'cb'), $text);
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
    private function cb($matches)
    {
        list(, $mEnd, $mTag, $mAttr, $mEmpty) = $matches;
        //    [1] => /
        //    [2] => TAG
        //    [3] => ... (attributes)
        //    [4] => /   (empty)

        if (isset(TexyHtml::$emptyTags[$mTag]) || $mEmpty) return $mEnd ? '' : '<'.$mTag.$mAttr.$mEmpty.'>';

        if ($mEnd) {  // end tag

            // has start tag?
            if (empty($this->tagUsed[$mTag])) return '';

            // autoclose tags
            $pair = end($this->tagStack);
            $s = '';
            $i = 1;
            while ($pair !== FALSE) {
                $tag = $pair['tag'];
                if ($pair['show']) $s .= '</'.$tag.'>';
                $this->tagUsed[$tag]--;
                if ($tag === $mTag) break;
                $pair = prev($this->tagStack);
                $i++;
            }

            if (isset(TexyHtml::$blockTags[$mTag])) {
                array_splice($this->tagStack, -$i);
                return $s;
            }

            // autoopen inline tags
            // not work in PHP 4.4.1 due bug #35063
            unset($this->tagStack[key($this->tagStack)]);
            $pair = current($this->tagStack);
            while ($pair !== FALSE) {
                if ($pair['show']) $s .= '<'.$pair['tag'].$pair['attr'].'>';
                $this->tagUsed[$pair['tag']]++;
                $pair = next($this->tagStack);
            }
            return $s;

        } else { // start tag

            $show = TRUE;
            $s = '';

            // check element prohibitions
            if (isset($this->prohibits[$mTag])) {
                foreach ($this->prohibits[$mTag] as $pTag)
                    if (!empty($this->tagUsed[$pTag])) { $show = FALSE; break; }
            }

            // check optional end tags (autoclose)
            if ($show && isset($this->autoClose[$mTag])) {
                $auto = $this->autoClose[$mTag];
                $pair = end($this->tagStack);
                while ($pair && isset($auto[ $pair['tag'] ])) {
                    if ($pair['show']) $s .= '</'.$pair['tag'].'>';
                    $this->tagUsed[$pair['tag']]--;
                    unset($this->tagStack[key($this->tagStack)]);

                    $pair = end($this->tagStack);
                }
            }

            // open tag, put to stack, increase counter
            $pair = array(
                'attr' => $mAttr,
                'tag' => $mTag,
                'show' => $show,
            );
            $this->tagStack[] = $pair;
            $tmp = &$this->tagUsed[$mTag]; $tmp++;

            return $show ? $s . '<'.$mTag.$mAttr.'>' : '';
        }
    }



    /**
     * Undefined property usage prevention
     */
    function __get($nm) { throw new Exception("Undefined property '" . get_class($this) . "::$$nm'"); }
    function __set($nm, $val) { $this->__get($nm); }

}
