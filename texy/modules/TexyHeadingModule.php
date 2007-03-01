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



/**
 * Heading module
 */
class TexyHeadingModule extends TexyModule
{
    const
        DYNAMIC = 1,  // auto-leveling
        FIXED =   2;  // fixed-leveling

    protected $default = array('headingSurrounded' => TRUE, 'headingUnderlined' => TRUE);

    /** @var int  level of top heading, 1..6 */
    public $top = 1;

    /** @var string  textual content of first heading */
    public $title;

    /** @var int  balancing mode */
    public $balancing = TexyHeadingModule::DYNAMIC;

    /** @var array  when $balancing = TexyHeadingModule::FIXED */
    public $levels = array(
        '#' => 0,  //  #  -->  $levels['#'] + $top = 0 + 1 = 1  --> <h1> ... </h1>
        '*' => 1,
        '=' => 2,
        '-' => 3,
    );

    private $_rangeUnderline;
    private $_deltaUnderline;
    private $_rangeSurround;
    private $_deltaSurround;



    public function init()
    {
        $this->texy->registerBlockPattern(
            array($this, 'processBlockUnderline'),
            '#^(\S.*)'.TEXY_MODIFIER_H.'?\n'
          . '(\#|\*|\=|\-){3,}$#mU',
            'headingUnderlined'
        );

        $this->texy->registerBlockPattern(
            array($this, 'processBlockSurround'),
            '#^((\#|\=){2,})(?!\\2)(.+)\\2*'.TEXY_MODIFIER_H.'?()$#mU',
            'headingSurrounded'
        );
    }



    public function preProcess($text)
    {
        $this->_rangeUnderline = array(10, 0);
        $this->_rangeSurround = array(10, 0);
        $this->title = NULL;

        $foo = NULL; $this->_deltaUnderline = & $foo;
        $bar = NULL; $this->_deltaSurround = & $bar;
        return $text;
    }



    /**
     * Callback function (for blocks)
     *
     *  Heading .(title)[class]{style}>
     *  -------------------------------
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

        $el = new TexyHeadingElement;
        $mod = new TexyModifier;
        $mod->setProperties($mMod1, $mMod2, $mMod3, $mMod4);
        $mod->decorate($this->texy, $el);

        $level = $this->levels[$mLine];
        $el->eXtra['level'] = $level;
        $el->eXtra['top'] = $this->top;
        $el->eXtra['deltaLevel'] = 0;
        if ($this->balancing === self::DYNAMIC)
            $el->eXtra['deltaLevel'] = & $this->_deltaUnderline;

        $el->parseLine($this->texy, trim($mContent));

        $parser->children[] = $el;

        // document title
        if ($this->title === NULL) $this->title = Texy::wash($el->getContent());

        // dynamic headings balancing
        $this->_rangeUnderline[0] = min($this->_rangeUnderline[0], $level);
        $this->_rangeUnderline[1] = max($this->_rangeUnderline[1], $level);
        $this->_deltaUnderline = -$this->_rangeUnderline[0];
        $this->_deltaSurround = -$this->_rangeSurround[0] +
            ($this->_rangeUnderline[1] ? ($this->_rangeUnderline[1] - $this->_rangeUnderline[0] + 1) : 0);
    }



    /**
     * Callback function (for blocks)
     *
     *   ### Heading .(title)[class]{style}>
     */
    public function processBlockSurround($parser, $matches)
    {
        list(, $mLine, , $mContent, $mMod1, $mMod2, $mMod3, $mMod4) = $matches;
        //    [1] => ###
        //    [2] => ...
        //    [3] => (title)
        //    [4] => [class]
        //    [5] => {style}
        //    [6] => >

        $el = new TexyHeadingElement;
        $mod = new TexyModifier;
        $mod->setProperties($mMod1, $mMod2, $mMod3, $mMod4);
        $mod->decorate($this->texy, $el);

        $level = 7 - min(7, max(2, strlen($mLine)));
        $el->eXtra['level'] = $level;
        $el->eXtra['top'] = $this->top;
        $el->eXtra['deltaLevel'] = 0;
        if ($this->balancing === self::DYNAMIC)
            $el->eXtra['deltaLevel'] = & $this->_deltaSurround;

        $el->parseLine($this->texy, trim($mContent));

        $parser->children[] = $el;

        // document title
        if ($this->title === NULL) $this->title = Texy::wash($el->getContent());

        // dynamic headings balancing
        $this->_rangeSurround[0] = min($this->_rangeSurround[0], $level);
        $this->_rangeSurround[1] = max($this->_rangeSurround[1], $level);
        $this->_deltaSurround  = -$this->_rangeSurround[0] +
            ($this->_rangeUnderline[1] ? ($this->_rangeUnderline[1] - $this->_rangeUnderline[0] + 1) : 0);

    }

} // TexyHeadingModule







/**
 * HTML ELEMENT H1-6
 */
class TexyHeadingElement extends TexyHtml
{

    public function startTag()
    {
        $level = $this->eXtra['level'] + $this->eXtra['deltaLevel'] + $this->eXtra['top'];
        $this->elName = 'h' . min(6, max(1, $level));
        return parent::startTag();
    }

} // TexyHeadingElement
