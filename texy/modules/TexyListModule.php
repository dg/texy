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
                    //  rexexp           list-style-type  tag
        '*'  => array('\*',              '',              'ul'),
        '-'  => array('[\x{2013}-]',     '',              'ul'),
        '+'  => array('\+',              '',              'ul'),
        '1.' => array('\d{1,3}\.\ ',     '',              'ol'),
        '1)' => array('\d{1,3}\)',       '',              'ol'),
        'I.' => array('[IVX]{1,4}\.\ ',  'upper-roman',   'ol'),
        'I)' => array('[IVX]+\)',        'upper-roman',   'ol'), // before A) !
        'a)' => array('[a-z]\)',         'lower-alpha',   'ol'),
        'A)' => array('[A-Z]\)',         'upper-alpha',   'ol'),
    );



    public function init(&$text)
    {
        $RE = array();
        foreach ($this->bullets as $desc)
            if (is_array($desc)) $RE[] = $desc[0];

        $this->texy->registerBlockPattern(
            array($this, 'patternList'),
            '#^(?:'.TEXY_MODIFIER_H.'\n)?'               // .{color: red}
          . '('.implode('|', $RE).')(\n?)\ +\S.*$#mUu',  // item (unmatched)
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
        list(, $mMod, $mBullet, $mNewLine) = $matches;
        //    [1] => .(title)[class]{style}<>
        //    [2] => bullet * + - 1) a) A) IV)
        //    [3] => \n

        $tx = $this->texy;

        $el = TexyHtml::el();

        $bullet = '';
        foreach ($this->bullets as $type => $desc)
            if (preg_match('#'.$desc[0].'#Au', $mBullet)) {
                $bullet = $desc[0];
                $el->elName = $desc[2];
                $el->style['list-style-type'] = $desc[1];
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

        $parser->moveBackward($mNewLine ? 2 : 1);

        $count = 0;
        while ($elItem = $this->patternItem($parser, $bullet, FALSE, 'li')) {
            $el->childNodes[] = $elItem;
            $count++;
        }

        if (!$count) return FALSE; // nemelo by nikdy nastat

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
        $patternItem = "#^\n?($spacesBase)$bullet(\n?)(\\ +)(\\S.*)?".TEXY_MODIFIER_H."?()$#mAUu";

        // first line (with bullet)
        if (!$parser->next($patternItem, $matches)) {
            return FALSE;
        }
        list(, $mIndent, $mNewLine, $mSpace, $mContent, $mMod) = $matches;
            //    [1] => indent
            //    [2] => \n
            //    [3] => space
            //    [4] => ...
            //    [5] => .(title)[class]{style}<>

        $elItem = TexyHtml::el($tag);
        $mod = new TexyModifier($mMod);
        $mod->decorate($tx, $elItem);

        // next lines
        $spaces = $mNewLine ? strlen($mSpace) : '';
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

        if ($elItem->childNodes[0] instanceof TexyHtml) {
            $elItem->childNodes[0]->elName = '';
        }

        return $elItem;
    }

} // TexyListModule
