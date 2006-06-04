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
 * @version    1.2 for PHP4 & PHP5 (released 2006/06/01)
 */

// security - include texy.php, not this file
if (!defined('TEXY')) die();




define('TEXY_HEADING_DYNAMIC',         1);  // auto-leveling
define('TEXY_HEADING_FIXED',           2);  // fixed-leveling




/**
 * HEADING MODULE CLASS
 */
class TexyHeadingModule extends TexyModule
{
    /** @var callback    Callback that will be called with newly created element */
    var $handler;

    var $allowed;

    // options
    var $top    = 1;                      // number of top heading, 1 - 6
    var $title;                           // textual content of first heading
    var $balancing = TEXY_HEADING_DYNAMIC;
    var $levels = array(                  // when $balancing = TEXY_HEADING_FIXED
        '#' => 0,      //   #  -->  $levels['#'] + $top = 0 + 1 = 1  --> <h1> ... </h1>
        '*' => 1,
        '=' => 2,
        '-' => 3,
    );

    // private
    var $_rangeUnderline;
    var $_deltaUnderline;
    var $_rangeSurround;
    var $_deltaSurround;



    function __construct(&$texy)
    {
        parent::__construct($texy);

        $this->allowed = (object) NULL;
        $this->allowed->surrounded  = TRUE;
        $this->allowed->underlined  = TRUE;
    }


    /**
     * Module initialization.
     */
    function init()
    {
        if ($this->allowed->underlined)
            $this->texy->registerBlockPattern(
                $this,
                'processBlockUnderline',
                '#^(\S.*)<MODIFIER_H>?\n'
              . '(\#|\*|\=|\-){3,}$#mU'
            );

        if ($this->allowed->surrounded)
            $this->texy->registerBlockPattern(
                $this,
                'processBlockSurround',
                '#^((\#|\=){2,})(?!\\2)(.+)\\2*<MODIFIER_H>?()$#mU'
            );
    }



    function preProcess(&$text)
    {
        $this->_rangeUnderline = array(10, 0);
        $this->_rangeSurround    = array(10, 0);
        $this->title = NULL;

        $foo = NULL; $this->_deltaUnderline = & $foo;
        $bar = NULL; $this->_deltaSurround = & $bar;
    }




    /**
     * Callback function (for blocks)
     *
     *            Heading .(title)[class]{style}>
     *            -------------------------------
     *
     */
    function processBlockUnderline(&$parser, $matches)
    {
        list(, $mContent, $mMod1, $mMod2, $mMod3, $mMod4, $mLine) = $matches;
        //  $matches:
        //    [1] => ...
        //    [2] => (title)
        //    [3] => [class]
        //    [4] => {style}
        //    [5] => >
        //
        //    [6] => ...

        $el = &new TexyHeadingElement($this->texy);
        $el->level = $this->levels[$mLine];
        if ($this->balancing == TEXY_HEADING_DYNAMIC)
            $el->deltaLevel = & $this->_deltaUnderline; /* & !!! */

        $el->modifier->setProperties($mMod1, $mMod2, $mMod3, $mMod4);
        $el->parse(trim($mContent));

        if ($this->handler)
            if (call_user_func_array($this->handler, array(&$el)) === FALSE) return;

        $parser->element->appendChild($el);

        // document title
        if ($this->title === NULL) $this->title = strip_tags($el->toHTML());

        // dynamic headings balancing
        $this->_rangeUnderline[0] = min($this->_rangeUnderline[0], $el->level);
        $this->_rangeUnderline[1] = max($this->_rangeUnderline[1], $el->level);
        $this->_deltaUnderline    = -$this->_rangeUnderline[0];
        $this->_deltaSurround     = -$this->_rangeSurround[0] + ($this->_rangeUnderline[1] ? ($this->_rangeUnderline[1] - $this->_rangeUnderline[0] + 1) : 0);
    }



    /**
     * Callback function (for blocks)
     *
     *            ### Heading .(title)[class]{style}>
     *
     */
    function processBlockSurround(&$parser, $matches)
    {
        list(, $mLine, $mChar, $mContent, $mMod1, $mMod2, $mMod3, $mMod4) = $matches;
        //    [1] => ###
        //    [2] => ...
        //    [3] => (title)
        //    [4] => [class]
        //    [5] => {style}
        //    [6] => >

        $el = &new TexyHeadingElement($this->texy);
        $el->level = 7 - min(7, max(2, strlen($mLine)));
        if ($this->balancing == TEXY_HEADING_DYNAMIC)
            $el->deltaLevel = & $this->_deltaSurround;  /* & !!! */

        $el->modifier->setProperties($mMod1, $mMod2, $mMod3, $mMod4);
        $el->parse(trim($mContent));

        if ($this->handler)
            if (call_user_func_array($this->handler, array(&$el)) === FALSE) return;

        $parser->element->appendChild($el);

        // document title
        if ($this->title === NULL) $this->title = strip_tags($el->toHTML());

        // dynamic headings balancing
        $this->_rangeSurround[0] = min($this->_rangeSurround[0], $el->level);
        $this->_rangeSurround[1] = max($this->_rangeSurround[1], $el->level);
        $this->_deltaSurround    = -$this->_rangeSurround[0] + ($this->_rangeUnderline[1] ? ($this->_rangeUnderline[1] - $this->_rangeUnderline[0] + 1) : 0);

    }




} // TexyHeadingModule









/**
 * HTML ELEMENT H1-6
 */
class TexyHeadingElement extends TexyTextualElement
{
    var $level = 0;        // 0 .. ?
    var $deltaLevel = 0;


    function generateTags(&$tags)
    {
        $this->tag = 'h' . min(6, max(1, $this->level + $this->deltaLevel + $this->texy->headingModule->top));
        parent::generateTags($tags);
    }


} // TexyHeadingElement









?>