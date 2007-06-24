<?php

/**
 * This file is part of the Texy! formatter (http://texy.info/)
 *
 * @author     David Grudl
 * @copyright  Copyright (c) 2004-2007 David Grudl aka -dgx- (http://www.dgx.cz)
 * @license    GNU GENERAL PUBLIC LICENSE version 2
 * @version    $Revision$ $Date$
 * @category   Text
 * @package    Texy
 */

// security - include texy.php, not this file
if (!class_exists('Texy', FALSE)) die();



/**
 * Ordered / unordered nested list module
 */
class TexyListModule extends TexyModule
{
    protected $syntax = array('list' => TRUE, 'list/definition' => TRUE);

    public $bullets = array(
                  //  first-rexexp          ordered   list-style-type   next-regexp
        '*'  => array('\*\ ',               0, ''),
        '-'  => array('[\x{2013}-](?![>-])',0, ''),
        '+'  => array('\+\ ',               0, ''),
        '1.' => array('1\.\ ', /*\d{1,3}*/  1, '',             '\d{1,3}\.\ '),
        '1)' => array('\d{1,3}\)\ ',        1, ''),
        'I.' => array('I\.\ ',              1, 'upper-roman',  '[IVX]{1,4}\.\ '),
        'I)' => array('[IVX]+\)\ ',         1, 'upper-roman'), // before A) !
        'a)' => array('[a-z]\)\ ',          1, 'lower-alpha'),
        'A)' => array('[A-Z]\)\ ',          1, 'upper-alpha'),
    );



    public function begin()
    {
        $RE = $REul = array();
        foreach ($this->bullets as $desc) {
            $RE[] = $desc[0];
            if (!$desc[1]) $REul[] = $desc[0];
        }

        $this->texy->registerBlockPattern(
            array($this, 'patternList'),
            '#^(?:'.TEXY_MODIFIER_H.'\n)?'          // .{color: red}
          . '('.implode('|', $RE).')\ *\S.*$#mUu',  // item (unmatched)
            'list'
        );

        $this->texy->registerBlockPattern(
            array($this, 'patternDefList'),
            '#^(?:'.TEXY_MODIFIER_H.'\n)?'               // .{color:red}
          . '(\S.*)\:\ *'.TEXY_MODIFIER_H.'?\n'          // Term:
          . '(\ ++)('.implode('|', $REul).')\ *\S.*$#mUu',  // - description
            'list/definition'
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
                $el->setName($desc[1] ? 'ol' : 'ul');
                $el->attrs['style']['list-style-type'] = $desc[2];
                if ($desc[1]) { // ol
                    if ($type[0] === '1' && (int) $mBullet > 1)
                        $el->attrs['start'] = (int) $mBullet;
                    elseif ($type[0] === 'a' && $mBullet[0] > 'a')
                        $el->attrs['start'] = ord($mBullet[0]) - 96;
                    elseif ($type[0] === 'A' && $mBullet[0] > 'A')
                        $el->attrs['start'] = ord($mBullet[0]) - 64;
                }
                break;
            }

        $mod = new TexyModifier($mMod);
        $mod->decorate($tx, $el);

        $parser->moveBackward(1);

        while ($elItem = $this->patternItem($parser, $bullet, FALSE, 'li'))
            $el->addChild($elItem);

        if (count($el->children) < $min) return FALSE;

        // event listener
        if (is_callable(array($tx->handler, 'afterList')))
            $tx->handler->afterList($parser, $el, $mod);

        return $el;
    }



    /**
     * Callback for:
     *
     *  Term: .(title)[class]{style}>
     *    - description 1
     *    - description 2
     *    - description 3
     *
     * @param TexyBlockParser
     * @param array      regexp matches
     * @param string     pattern name
     * @return TexyHtml
     */
    public function patternDefList($parser, $matches)
    {
        list(, $mMod, , , , $mBullet) = $matches;
        //   [1] => .(title)[class]{style}<>
        //   [2] => ...
        //   [3] => .(title)[class]{style}<>
        //   [4] => space
        //   [5] => - * +

        $tx = $this->texy;

        $bullet = NULL;
        foreach ($this->bullets as $type => $desc)
            if (preg_match('#'.$desc[0].'#Au', $mBullet)) {
                $bullet = isset($desc[3]) ? $desc[3] : $desc[0];
                break;
            }

        $el = TexyHtml::el('dl');
        $mod = new TexyModifier($mMod);
        $mod->decorate($tx, $el);
        $parser->moveBackward(2);

        $patternTerm = '#^\n?(\S.*)\:\ *'.TEXY_MODIFIER_H.'?()$#mUA';

        while (TRUE) {
            if ($elItem = $this->patternItem($parser, $bullet, TRUE, 'dd')) {
                $el->addChild($elItem);
                continue;
            }

            if ($parser->next($patternTerm, $matches)) {
                list(, $mContent, $mMod) = $matches;
                //    [1] => ...
                //    [2] => .(title)[class]{style}<>

                $elItem = TexyHtml::el('dt');
                $mod = new TexyModifier($mMod);
                $mod->decorate($tx, $elItem);

                $elItem->parseLine($tx, $mContent);
                $el->addChild($elItem);
                continue;
            }

            break;
        }

        // event listener
        if (is_callable(array($tx->handler, 'afterDefinitionList')))
            $tx->handler->afterDefinitionList($parser, $el, $mod);

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
        $patternItem = "#^\n?($spacesBase)$bullet\\ *(\\S.*)?".TEXY_MODIFIER_H."?()$#mAUu";

        // first line with bullet
        $matches = NULL;
        if (!$parser->next($patternItem, $matches)) return FALSE;

        list(, $mIndent, $mContent, $mMod) = $matches;
            //    [1] => indent
            //    [2] => ...
            //    [3] => .(title)[class]{style}<>

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
        $tmp = $tx->paragraphModule->mode;
        $tx->paragraphModule->mode = FALSE;
        $elItem->parseBlock($tx, $content);
        $tx->paragraphModule->mode = $tmp;

        if ($elItem->getChild(0) instanceof TexyHtml) {
            $elItem->getChild(0)->setName(NULL);
        }

        return $elItem;
    }

}
