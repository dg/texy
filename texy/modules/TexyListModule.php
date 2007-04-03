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
 * Ordered / unordered nested list module
 */
class TexyListModule extends TexyModule
{
    protected $default = array('list' => TRUE);

    public $bullets = array(
                  //  first rexexp          tag   list-style-type   next regexp
        '*'  => array('\*\ ',               'ul', ''),
        '-'  => array('[\x{2013}-](?![>-])','ul', ''),
        '+'  => array('\+\ ',               'ul', ''),
        '1.' => array('1\.\ ',              'ol', '',             '\d{1,3}\.\ '),
//        '1.' => array('\d{1,3}\.\ ',        'ol', ''),
        '1)' => array('\d{1,3}\)\ ',        'ol', ''),
        'I.' => array('I\.\ ',              'ol', 'upper-roman',  '[IVX]{1,4}\.\ '),
        'I)' => array('[IVX]+\)\ ',         'ol', 'upper-roman'), // before A) !
        'a)' => array('[a-z]\)\ ',          'ol', 'lower-alpha'),
        'A)' => array('[A-Z]\)\ ',          'ol', 'upper-alpha'),
    );



    public function begin()
    {
        $RE = array();
        foreach ($this->bullets as $desc)
            if (is_array($desc)) $RE[] = $desc[0];

        $this->texy->registerBlockPattern(
            array($this, 'patternList'),
            '#^(?:'.TEXY_MODIFIER_H.'\n)?'          // .{color: red}
          . '('.implode('|', $RE).')\ *\S.*$#mUu',  // item (unmatched)
            'list'
        );
    }



    /**
     * Callback for:
     *
     *            1) .... .(title)[class]{style}>
     *            2) ....
     *                + ...
     *                + ...
     *            3) ....
     *
     * @param TexyBlockParser
     * @param array      regexp matches
     * @param string     pattern name
     * @return TexyHtml|FALSE
     */
    public function patternList($parser, $matches)
    {
        list(, $mMod, $mBullet) = $matches;
        //    [1] => .(title)[class]{style}<>
        //    [2] => bullet * + - 1) a) A) IV)

        $tx = $this->texy;

        $el = TexyHtml::el();

        $bullet = $min = NULL;
        foreach ($this->bullets as $type => $desc)
            if (preg_match('#'.$desc[0].'#Au', $mBullet)) {
                $bullet = isset($desc[3]) ? $desc[3] : $desc[0];
                $min = isset($desc[3]) ? 2 : 1;
                $el->elName = $desc[1];
                $el->style['list-style-type'] = $desc[2];
		        if ($el->elName === 'ol') {
                    if ($type[0] === '1' && (int) $mBullet > 1)
                        $el->start = (int) $mBullet;
                    elseif ($type[0] === 'a' && $mBullet[0] > 'a')
                        $el->start = ord($mBullet[0]) - 96;
                    elseif ($type[0] === 'A' && $mBullet[0] > 'A')
                        $el->start = ord($mBullet[0]) - 64;
                }
                break;
            }

        $mod = new TexyModifier($mMod);
        $mod->decorate($tx, $el);

        $parser->moveBackward(1);

        while ($elItem = $this->patternItem($parser, $bullet, FALSE, 'li'))
            $el->childNodes[] = $elItem;

        if (count($el->childNodes) < $min) return FALSE;

        // event listener
        if (is_callable(array($tx->handler, 'afterList')))
            $tx->handler->afterList($parser, $el, $mod);

        return $el;
    }



    /**
     * Callback for single list item
     *
     * @param TexyBlockParser
     * @param string  bullet type
     * @param string  left space
     * @param string  html tag
     * @return TexyHtml|FALSE
     */
    public function patternItem($parser, $bullet, $indented, $tag)
    {
        $tx =  $this->texy;
        $spacesBase = $indented ? ('\ {1,}') : '';
        $patternItem = "#^\n?($spacesBase)$bullet(\\ *)(\\S.*)?".TEXY_MODIFIER_H."?()$#mAUu";

        // first line with bullet
        $matches = NULL;
        if (!$parser->next($patternItem, $matches)) return FALSE;

        list(, $mIndent, $mSpace, $mContent, $mMod) = $matches;
            //    [1] => indent
            //    [2] => space
            //    [3] => ...
            //    [4] => .(title)[class]{style}<>

        $elItem = TexyHtml::el($tag);
        $mod = new TexyModifier($mMod);
        $mod->decorate($tx, $elItem);

        // next lines
        $spaces = '';
        $content = ' ' . $mContent; // trick
        while ($parser->next('#^(\n*)'.$mIndent.'(\ {1,'.$spaces.'})(.*)()$#Am', $matches)) {
            list(, $mBlank, $mSpaces, $mContent) = $matches;
            //    [1] => blank line?
            //    [2] => spaces
            //    [3] => ...

            if ($spaces === '') $spaces = strlen($mSpaces);
            $content .= "\n" . $mBlank . $mContent;
        }

        // parse content
        $tmp = $tx->_paragraphMode;
        $tx->_paragraphMode = FALSE;
        $elItem->parseBlock($tx, $content);
        $tx->_paragraphMode = $tmp;

        if (isset($elItem->childNodes[0]) && $elItem->childNodes[0] instanceof TexyHtml) {
            $elItem->childNodes[0]->elName = '';
        }

        return $elItem;
    }

} // TexyListModule
