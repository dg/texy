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



class TexyHtmlWellForm
{
    /** @var array */
    private $tagUsed;

    /** @var array */
    private $tagStack;

    /** @see http://www.w3.org/TR/xhtml1/prohibitions.html */
    private $prohibits = array(
        'a' => array('a'),
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
        'tbody'    => array('thead'=>1,'tbody'=>1,'tfoot'=>1,'colgoup'=>1),
        'colgroup' => array('thead'=>1,'tbody'=>1,'tfoot'=>1,'colgoup'=>1),
        'dd'       => array('dt'=>1,'dd'=>1),
        'dt'       => array('dt'=>1,'dd'=>1),
        'li'       => array('li'=>1),
        'option'   => array('option'=>1),
        'p'        => array('address'=>1,'applet'=>1,'blockquote'=>1,'center'=>1,'dir'=>1,'div'=>1,'dl'=>1,'fieldset'=>1,'form'=>1,'h1'=>1,'h2'=>1,
                        'h3'=>1,'h4'=>1,'h5'=>1,'h6'=>1,'hr'=>1,'isindex'=>1,'menu'=>1,'object'=>1,'ol'=>1,'p'=>1,'pre'=>1,'table'=>1,'ul'=>1),
        'td'       => array('th'=>1,'td'=>1,'tr'=>1,'thead'=>1,'tbody'=>1,'tfoot'=>1,'colgoup'=>1),
        'tfoot'    => array('thead'=>1,'tbody'=>1,'tfoot'=>1,'colgoup'=>1),
        'th'       => array('th'=>1,'td'=>1,'tr'=>1,'thead'=>1,'tbody'=>1,'tfoot'=>1,'colgoup'=>1),
        'thead'    => array('thead'=>1,'tbody'=>1,'tfoot'=>1,'colgoup'=>1),
        'tr'       => array('tr'=>1,'thead'=>1,'tbody'=>1,'tfoot'=>1,'colgoup'=>1),
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
                $s .= '</'.$tag.'>';
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
                $s .= '<'.$pair['tag'].$pair['attr'].'>';
                $this->tagUsed[$pair['tag']]++;
                $pair = next($this->tagStack);
            }
            return $s;

        } else { // start tag

            // check element prohibitions
            if (isset($this->prohibits[$mTag])) {
                foreach ($this->prohibits[$mTag] as $pTag)
                    if (!empty($this->tagUsed[$pTag])) return '';
            }

            // check optional end tags (autoclose)
            $s = '';
            $pair = end($this->tagStack);
            while ($pair &&
                    isset($this->autoClose[$pair['tag']]) &&
                    isset($this->autoClose[$pair['tag']][$mTag]) ) {

                $tag = $pair['tag'];
                $s .= '</'.$tag.'>';
                $this->tagUsed[$tag]--;
                unset($this->tagStack[key($this->tagStack)]);

                $pair = end($this->tagStack);
            }

            // open tag, put to stack, increase counter
            $pair = array(
                'attr' => $mAttr,
                'tag' => $mTag,
            );
            $this->tagStack[] = $pair;
            $tmp = &$this->tagUsed[$mTag]; $tmp++;

            return $s . '<'.$mTag.$mAttr.'>';
        }
    }



    /**
     * Undefined property usage prevention
     */
    function __get($nm) { throw new Exception("Undefined property '" . get_class($this) . "::$$nm'"); }
    function __set($nm, $val) { $this->__get($nm); }

}
