<?php

/**
 * ----------------------------------------------------------
 *   ORDERED / UNORDERED NESTED LIST - TEXY! DEFAULT MODULE
 * ----------------------------------------------------------
 *
 * Version 1 Release Candidate
 *
 * Copyright (c) 2005, David Grudl <dave@dgx.cz>
 * Web: http://www.texy.info/
 *
 * For the full copyright and license information, please view the COPYRIGHT
 * file that was distributed with this source code. If the COPYRIGHT file is
 * missing, please visit the Texy! homepage: http://www.texy.info
 *
 * @package Texy
 */

// security - include texy.php, not this file
if (!defined('TEXY')) die();





/**
 * ORDERED / UNORDERED NESTED LIST MODULE CLASS
 */
class TexyListModule extends TexyModule {
    var $allowed = array(
                 '*'            => true,
                 '-'            => true,
                 '+'            => true,
                 '1.'           => true,
                 '1)'           => true,
                 'I.'           => true,
                 'I)'           => true,
                 'a)'           => true,
                 'A)'           => true,
    );

    // private
    var $translate = array(    //  rexexp       class   list-style-type  tag
                 '*'            => array('\*',          '',    '',              'ul'),
                 '-'            => array('\-',          '',    '',              'ul'),
                 '+'            => array('\+',          '',    '',              'ul'),
                 '1.'           => array('\d+\.\ ',     '',    '',              'ol'),
                 '1)'           => array('\d+\)',       '',    '',              'ol'),
                 'I.'           => array('[IVX]+\.\ ',  '',    'upper-roman',   'ol'),   // place romans before alpha
                 'I)'           => array('[IVX]+\)',    '',    'upper-roman',   'ol'),
                 'a)'           => array('[a-z]\)',     '',    'lower-alpha',   'ol'),
                 'A)'           => array('[A-Z]\)',     '',    'upper-alpha',   'ol'),
            );


    /***
     * Module initialization.
     */
    function init()
    {
        $bullets = array();
        foreach ($this->allowed as $bullet => $allowed)
            if ($allowed) $bullets[] = $this->translate[$bullet][0];

        $this->registerBlockPattern('processBlock', '#^(?:MODIFIER_H\n)?'                             // .{color: red}
                                                                                            . '('.implode('|', $bullets).')(\n?)\ +\S.*$#mU');  // item (unmatched)
    }





    /***
     * Callback function (for blocks)
     *
     *            1) .... .(title)[class]{style}>
     *            2) ....
     *                + ...
     *                + ...
     *            3) ....
     *
     */
    function processBlock(&$blockParser, &$matches)
    {
        list($match, $mMod1, $mMod2, $mMod3, $mMod4, $mBullet, $mNewLine) = $matches;
        //    [1] => (title)
        //    [2] => [class]
        //    [3] => {style}
        //    [4] => >
        //    [5] => bullet * + - 1) a) A) IV)

        $texy = & $this->texy;
        $el = &new TexyListElement($texy);
        $el->modifier->setProperties($mMod1, $mMod2, $mMod3, $mMod4);

        $bullet = '';
        foreach ($this->translate as $type)
            if (preg_match('#'.$type[0].'#A', $mBullet)) {
                $bullet = $type[0];
                $el->tag = $type[3];
                $el->modifier->styles['list-style-type'] = $type[2];
                $el->modifier->classes[] = $type[1];
                break;
            }

        $blockParser->moveBackward($mNewLine ? 2 : 1);

        $count = 0;
        while ($elItem = &$this->processItem($blockParser, $bullet)) {
            $el->children[] = & $elItem;
            $count++;
        }

        if (!$count) return false;
        else $blockParser->addChildren($el);
    }








    function &processItem(&$blockParser, $bullet, $indented = false) {
        $texy = & $this->texy;
        $spacesBase = $indented ? ('\ {1,}') : '';
        $patternItem = $texy->translatePattern('#^\n?(@1)@2(\n?)(\ +)(\S.*)?MODIFIER_H?()$#mAU', $spacesBase, $bullet);

        // first line (with bullet)
        if (!$blockParser->receiveNext($patternItem, $matches)) {
            $false = false; // php4_sucks
            return $false;
        }
        list($match, $mIndent, $mNewLine, $mSpace, $mContent, $mMod1, $mMod2, $mMod3, $mMod4) = $matches;
            //    [1] => indent
            //    [2] => \n
            //    [3] => space
            //    [4] => ...
            //    [5] => (title)
            //    [6] => [class]
            //    [7] => {style}
            //    [8] => >

        $elItem = &new TexyListItemElement($texy);
        $elItem->tag = 'li';
        $elItem->modifier->setProperties($mMod1, $mMod2, $mMod3, $mMod4);

        // next lines
        $spaces = $mNewLine ? strlen($mSpace) : '';
        $content = ' ' . $mContent; // trick
        while ($blockParser->receiveNext('#^(\n*)'.$mIndent.'(\ {1,'.$spaces.'})(.*)()$#Am', $matches)) {
            list($match, $mBlank, $mSpaces, $mContent) = $matches;
            //    [1] => blank line?
            //    [2] => spaces
            //    [3] => ...

            if ($spaces === '') $spaces = strlen($mSpaces);
            $content .= TEXY_NEWLINE . $mBlank . $mContent;
        }

        // parse content
        $mergeLines = & $texy->genericBlock[0]->mergeLines;
        $tmp = $mergeLines;
        $mergeLines = false;

        $elItem->parse($content);
        $mergeLines = true;

        if (is_a($elItem->children[0], 'TexyGenericBlockElement'))
            $elItem->children[0]->tag = '';

        return $elItem;
    }





} // TexyListModule






/****************************************************************************
                                                             TEXY! DOM ELEMENTS                          */




/**
 * HTML ELEMENT OL / UL / DL
 */
class TexyListElement extends TexyBlockElement {

} // TexyListElement





/**
 * HTML ELEMENT LI / DL
 */
class TexyListItemElement extends TexyBlockElement {

} // TexyListItemElement





?>