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
 * Special blocks module
 */
class TexyBlockModule extends TexyModule
{
    protected $default = array(
        'blocks' => TRUE,
        'blockPre' => TRUE,
        'blockCode' => TRUE,
        'blockHtml' => TRUE,
        'blockText' => TRUE,
        'blockTexysource' => TRUE,
        'blockComment' => TRUE,
    );


    public function init()
    {
        $this->texy->registerBlockPattern(
            array($this, 'processBlock'),
            '#^/--+ *(?:(code|text|html|div|texysource|comment)( .*)?|) *'.TEXY_MODIFIER_H.'?\n(.*\n)?(?:\\\\--+ *\\1?|\z)()$#mUsi',
            'blocks'
        );
    }



    /**
     * Callback function (for blocks)
     * @return object
     *
     *   /-----code html .(title)[class]{style}
     *     ....
     *     ....
     *   \----
     *
     */
    public function processBlock($parser, $matches, $name)
    {
        list(, $mType, $mLang, $mMod1, $mMod2, $mMod3, $mMod4, $mContent) = $matches;
        //    [1] => code
        //    [2] => lang ?
        //    [3] => (title)
        //    [4] => [class]
        //    [5] => {style}
        //    [6] => >
        //    [7] => .... content

        $tx = $this->texy;
        $mType = trim(strtolower($mType));
        $mLang = trim(strtolower($mLang));
        $mContent = trim($mContent, "\n");

        if (!$mType) $mType = 'pre';                // default type
        if ($mType === 'html' && empty($tx->allowed['blockHtml'])) $mType = 'text';
        if ($mType === 'texysource' && empty($tx->allowed['blockTexysource'])) $mType = 'pre';
        if ($mType === 'code' && empty($tx->allowed['blockCode'])) $mType = 'pre';
        if ($mType === 'pre' && empty($tx->allowed['blockPre'])) $mType = 'div';

        $type = 'block' . ucfirst($mType);
        if (empty($tx->allowed[$type])) {
            $mType = 'div';
            $type = 'blockDiv';
        }

        $mod = new TexyModifier;
        $mod->setProperties($mMod1, $mMod2, $mMod3, $mMod4);

        switch ($mType) {
        case 'div':
            $el = new TexyBlockElement($tx);
            $el->tags[0] = $mod->generate($tx, 'div');

            // outdent
            if ($spaces = strspn($mContent, ' '))
                $mContent = preg_replace("#^ {1,$spaces}#m", '', $mContent);

            $el->parse($mContent);
            $parser->children[] = $el;
            break;


        case 'texysource':
            // outdent
            if ($spaces = strspn($mContent, ' '))
                $mContent = preg_replace("#^ {1,$spaces}#m", '', $mContent);

            $el = new TexyBlockElement($tx);
            $el->parse($mContent);

            $html = $el->toHtml();
            $html = $tx->unMarks($html);
            $html = $tx->wellForm->process($html);
            $html = $tx->formatter->process($html);
            $html = Texy::encode($html);

            $el = new TexyTextualElement($tx);
            $el->tags[0] = $mod->generate($tx, 'pre');
            $el->tags[1] = TexyHtml::el('code')->class('html');
            $el->content = $html;
            $el->protect = TRUE;
            $parser->children[] = $el;
            break;


        case 'comment':
            break;


        case 'html':
            $el = new TexyTextualElement($tx);
            $lineParser = new TexyLineParser($this->texy);
            $mContent = $lineParser->parse($mContent, array('html'));
            $mContent = Texy::decode($mContent);
            $el->content = Texy::encode($mContent);
            $el->protect = TRUE;
            $parser->children[] = $el;
            break;


        case 'text':
            $el = new TexyTextualElement($tx);
            $el->content = nl2br( Texy::encode($mContent) );
            $el->protect = TRUE;
            $parser->children[] = $el;
            break;


        case 'pre':
        case 'code':
            $el = new TexyTextualElement($tx);
            $el->tags[0] = $mod->generate($tx, 'pre');
            $el->tags[0]->class[] = $mLang; // lang

            if ($mType === 'code') {
                $el->tags[1] = TexyHtml::el('code');
            }

            // outdent
            if ($spaces = strspn($mContent, ' '))
                $mContent = preg_replace("#^ {1,$spaces}#m", '', $mContent);

            $el->content = Texy::encode($mContent);
            $el->protect = TRUE;

            if (is_callable(array($tx->handler, $type)))
                $tx->handler->$type($tx, $mLang, $mod, $mContent, $el);

            $parser->children[] = $el;

        } // switch

    } // function processBlock

} // TexyBlockModule