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
 * @version    $Revision$ $Date$
 */

// security - include texy.php, not this file
if (!defined('TEXY')) die();





/**
 * ORDERED / UNORDERED NESTED LIST MODULE CLASS
 */
class TexyListModule extends TexyModule
{
    /** @var callback    Callback that will be called with newly created element */
    var $handler;

    var $allowed = array(
        '*'            => TRUE,
        '-'            => TRUE,
        '+'            => TRUE,
        '1.'           => TRUE,
        '1)'           => TRUE,
        'I.'           => TRUE,
        'I)'           => TRUE,
        'a)'           => TRUE,
        'A)'           => TRUE,
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


    /**
     * Module initialization.
     */
    function init()
    {
        $bullets = array();
        foreach ($this->allowed as $bullet => $allowed)
            if ($allowed) $bullets[] = $this->translate[$bullet][0];

        $this->texy->registerBlockPattern(
            $this,
            'processBlock',
            '#^(?:<MODIFIER_H>\n)?'                         // .{color: red}
          . '('.implode('|', $bullets).')(\n?)\ +\S.*$#mU'  // item (unmatched)
        );
    }





    /**
     * Callback function (for blocks)
     *
     *            1) .... .(title)[class]{style}>
     *            2) ....
     *                + ...
     *                + ...
     *            3) ....
     *
     */
    function processBlock(&$parser, $matches)
    {
        list(, $mMod1, $mMod2, $mMod3, $mMod4, $mBullet, $mNewLine) = $matches;
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

        $parser->moveBackward($mNewLine ? 2 : 1);

        $count = 0;
        while ($elItem = &$this->processItem($parser, $bullet)) {
            $el->appendChild($elItem);
            $count++;
        }

        if (!$count) return FALSE;

        if ($this->handler)
            if (call_user_func_array($this->handler, array(&$el)) === FALSE) return;

        $parser->element->appendChild($el);
    }








    function &processItem(&$parser, $bullet, $indented = FALSE) {
        $texy = & $this->texy;
        $spacesBase = $indented ? ('\ {1,}') : '';
        $patternItem = $texy->translatePattern("#^\n?($spacesBase)$bullet(\n?)(\ +)(\S.*)?<MODIFIER_H>?()$#mAU");

        // first line (with bullet)
        if (!$parser->receiveNext($patternItem, $matches)) {
            $FALSE = FALSE; // php4_sucks
            return $FALSE;
        }
        list(, $mIndent, $mNewLine, $mSpace, $mContent, $mMod1, $mMod2, $mMod3, $mMod4) = $matches;
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
        while ($parser->receiveNext('#^(\n*)'.$mIndent.'(\ {1,'.$spaces.'})(.*)()$#Am', $matches)) {
            list(, $mBlank, $mSpaces, $mContent) = $matches;
            //    [1] => blank line?
            //    [2] => spaces
            //    [3] => ...

            if ($spaces === '') $spaces = strlen($mSpaces);
            $content .= TEXY_NEWLINE . $mBlank . $mContent;
        }

        // parse content
        $mergeMode = & $texy->genericBlock[0]->mergeMode;
        $tmp = $mergeMode;
        $mergeMode = FALSE;

        $elItem->parse($content);
        $mergeMode = $tmp;

        // !!! children is protected
        if (is_a($elItem->_children[0], 'TexyGenericBlockElement'))
            $elItem->_children[0]->tag = '';

        return $elItem;
    }





} // TexyListModule




/**
 * HTML ELEMENT OL / UL / DL
 */
class TexyListElement extends TexyBlockElement
{
}





/**
 * HTML ELEMENT LI / DL
 */
class TexyListItemElement extends TexyBlockElement
{
}



?>