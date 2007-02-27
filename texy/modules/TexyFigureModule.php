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
 * The captioned figures
 */
class TexyFigureModule extends TexyModule
{
    protected $allow = array('figure');

    /** @var string  non-floated box CSS class */
    public $class = 'figure';

    /** @var string  left-floated box CSS class */
    public $leftClass = 'figure-left';

    /** @var string  right-floated box CSS class */
    public $rightClass = 'figure-right';



    public function init()
    {
        $this->texy->registerBlockPattern(
            array($this, 'processBlock'),
            '#^'.TEXY_IMAGE.TEXY_LINK_N.'?? +\*\*\* +(.*)'.TEXY_MODIFIER_H.'?()$#mU',
            'figure'
        );
    }



    /**
     * Callback function (for blocks)
     *
     *   [*image*]:link *** .... .(title)[class]{style}>
     *
     */
    public function processBlock($parser, $matches)
    {
        list(, $mURLs, $mImgMod1, $mImgMod2, $mImgMod3, $mImgMod4, $mLink, $mContent, $mMod1, $mMod2, $mMod3, $mMod4) = $matches;
        //    [1] => URLs
        //    [2] => (title)
        //    [3] => [class]
        //    [4] => {style}
        //    [5] => >
        //    [6] => url | [ref] | [*image*]
        //    [7] => ...
        //    [8] => (title)
        //    [9] => [class]
        //    [10] => {style}
        //    [11] => >

        $tx = $this->texy;
        $el = new TexyBlockElement($tx);

        $req = $tx->imageModule->parse($mURLs, $mImgMod1, $mImgMod2, $mImgMod3, $mImgMod4);

        $mod = new TexyModifier;
        $mod->setProperties($mMod1, $mMod2, $mMod3, $mMod4);
        $hAlign = $req['modifier']->hAlign;
        $mod->hAlign = $req['modifier']->hAlign = NULL;

        $elImg = $tx->imageModule->factory($req, $mLink);
        if ($mLink) {
            if ($mLink === ':') {
                $reqL = array(
                    'URL' => empty($req['linkedURL']) ? $req['imageURL'] : $req['linkedURL'],
                    'image' => TRUE,
                    'modifier' => new TexyModifier,
                );
            } else {
                $reqL = $tx->linkModule->parse($mLink, NULL, NULL, NULL, NULL);
            }

            $elLink = $tx->linkModule->factory($reqL);
            $tx->summary['links'][] = $elLink->href;

            $elLink->addChild($elImg);
            $elImg = $elLink;
        }

        $el->tags[0] = $mod->generate($tx, 'div');
        $el->children[0] = new TexyTextualElement($tx);
        $el->children[0]->content = $elImg->toText($tx);

        $el->children[1] = new TexyBlockElement($tx);
        $el->children[1]->parse(ltrim($mContent));

        if ($hAlign === TexyModifier::HALIGN_LEFT) {
            $el->tags[0]->class[] = $this->leftClass;
        } elseif ($hAlign === TexyModifier::HALIGN_RIGHT)  {
            $el->tags[0]->class[] = $this->rightClass;
        } elseif ($this->class)
            $el->tags[0]->class[] = $this->class;

        if (is_callable(array($tx->handler, 'figure')))
            $tx->handler->figure($tx, $req, $el);

        $parser->children[] = $el;
    }

}
