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
 * Definition list module
 */
class TexyDefinitionListModule extends TexyListModule
{
    protected $default = array('list/definition' => TRUE);

    public $bullets = array(
        '*' => array('\*'),
        '-' => array('[\x{2013}-]'),
        '+' => array('\+'),
    );



    public function begin()
    {
        $RE = array();
        foreach ($this->bullets as $desc)
            if (is_array($desc)) $RE[] = $desc[0];

        $this->texy->registerBlockPattern(
            array($this, 'patternDefList'),
            '#^(?:'.TEXY_MODIFIER_H.'\n)?'               // .{color:red}
          . '(\S.*)\:\ *'.TEXY_MODIFIER_H.'?\n'          // Term:
          . '(\ +)('.implode('|', $RE).')\ +\S.*$#mUu',  //    - description
            'list/definition'
        );
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

        $bullet = '';
        foreach ($this->bullets as $desc)
            if (is_array($desc) && preg_match('#'.$desc[0].'#Au', $mBullet)) {
                $bullet = $desc[0];
                break;
            }

        $el = TexyHtml::el('dl');
        $mod = new TexyModifier($mMod);
        $mod->decorate($tx, $el);
        $parser->moveBackward(2);

        $patternTerm = '#^\n?(\S.*)\:\ *'.TEXY_MODIFIER_H.'?()$#mUA';
        $bullet = preg_quote($mBullet);

        while (TRUE) {
            if ($elItem = $this->patternItem($parser, $bullet, TRUE, 'dd')) {
                $el->childNodes[] = $elItem;
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
                $el->childNodes[] = $elItem;
                continue;
            }

            break;
        }

        // event listener
        if (is_callable(array($tx->handler, 'afterDefinitionList')))
            $tx->handler->afterDefinitionList($parser, $el, $mod);

        return $el;
    }

} // TexyDefinitionListModule
