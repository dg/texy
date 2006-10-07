<?php

/**
 * Texy! universal text -> html converter
 * --------------------------------------
 *
 * This source file is subject to the GNU GPL license.
 *
 * @author     David Grudl aka -dgx- <dave@dgx.cz>
 * @link       http://texy.info/
 * @copyright  Copyright (c) 2004-2006 David Grudl
 * @license    GNU GENERAL PUBLIC LICENSE v2
 * @package    Texy
 * @category   Text
 * @version    $Revision$ $Date$
 */

// security - include texy.php, not this file
if (!defined('TEXY')) die();








/**
 * HEADING MODULE CLASS
 */
class TexyHeadingModule extends TexyModule
{
    const
        DYNAMIC = 1,  // auto-leveling
        FIXED =   2;  // fixed-leveling


    /** @var callback    Callback that will be called with newly created element */
    public $handler;

    public $allowed;

    // options
    public $top    = 1;                      // number of top heading, 1 - 6
    public $title;                           // textual content of first heading
    public $balancing = TexyHeadingModule::DYNAMIC;
    public $levels = array(                  // when $balancing = TexyHeadingModule::FIXED
        '#' => 0,      //   #  -->  $levels['#'] + $top = 0 + 1 = 1  --> <h1> ... </h1>
        '*' => 1,
        '=' => 2,
        '-' => 3,
    );

    private $_rangeUnderline;
    private $_deltaUnderline;
    private $_rangeSurround;
    private $_deltaSurround;



    public function __construct($texy)
    {
        parent::__construct($texy);

        $this->allowed = (object) NULL;
        $this->allowed->surrounded  = TRUE;
        $this->allowed->underlined  = TRUE;
    }


    /**
     * Module initialization.
     */
    public function init()
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



    public function preProcess(&$text)
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
    public function processBlockUnderline($parser, $matches)
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

        $el = new TexyHeadingElement($this->texy);
        $el->level = $this->levels[$mLine];
        if ($this->balancing == self::DYNAMIC)
            $el->deltaLevel = & $this->_deltaUnderline; /* & !!! */

        $el->modifier->setProperties($mMod1, $mMod2, $mMod3, $mMod4);
        $el->parse(trim($mContent));

        if ($this->handler)
            if (call_user_func_array($this->handler, array($el)) === FALSE) return;

        $parser->element->appendChild($el);

        // document title
        if ($this->title === NULL) $this->title = strip_tags($el->toHtml());

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
    public function processBlockSurround($parser, $matches)
    {
        list(, $mLine, $mChar, $mContent, $mMod1, $mMod2, $mMod3, $mMod4) = $matches;
        //    [1] => ###
        //    [2] => ...
        //    [3] => (title)
        //    [4] => [class]
        //    [5] => {style}
        //    [6] => >

        $el = new TexyHeadingElement($this->texy);
        $el->level = 7 - min(7, max(2, strlen($mLine)));
        if ($this->balancing == self::DYNAMIC)
            $el->deltaLevel = & $this->_deltaSurround;  /* & !!! */

        $el->modifier->setProperties($mMod1, $mMod2, $mMod3, $mMod4);
        $el->parse(trim($mContent));

        if ($this->handler)
            if (call_user_func_array($this->handler, array($el)) === FALSE) return;

        $parser->element->appendChild($el);

        // document title
        if ($this->title === NULL) $this->title = strip_tags($el->toHtml());

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
    public $level = 0;        // 0 .. ?
    public $deltaLevel = 0;


    protected function generateTags(&$tags)
    {
        $this->tag = 'h' . min(6, max(1, $this->level + $this->deltaLevel + $this->texy->headingModule->top));
        parent::generateTags($tags);
    }


} // TexyHeadingElement
