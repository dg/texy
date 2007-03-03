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
        $user = NULL;
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
            $el = TexyHtml::el('div');
            $mod->decorate($tx, $el);

            // outdent
            if ($spaces = strspn($mContent, ' '))
                $mContent = preg_replace("#^ {1,$spaces}#m", '', $mContent);

            $el->parseBlock($tx, $mContent);
            $parser->children[] = $el;
            break;


        case 'texysource':
            // outdent
            if ($spaces = strspn($mContent, ' '))
                $mContent = preg_replace("#^ {1,$spaces}#m", '', $mContent);

            $el = TexyHtml::el();
            $el->parseBlock($tx, $mContent);

            $html = $tx->export($el);
            $html = Texy::encode($html);

            $el = TexyHtml::el('pre')->class('html');
            $mod->decorate($tx, $el);
            $el2 = TexyHtml::el('code');
            $el->childNodes[] = $el2;
            $el2->childNodes[] = $tx->protect($html);
            $parser->children[] = $el;
            break;


        case 'comment':
            break;


        case 'html':
            $lineParser = new TexyLineParser($tx);
            $lineParser->onlyHtml = TRUE;
            $mContent = $lineParser->parse($mContent);
            $mContent = Texy::decode($mContent);
            $mContent = Texy::encode($mContent);
            $mContent = $tx->unprotect($mContent);
            $el = TexyHtml::el();
            $el->setContent( $tx->protect($mContent) );
            $parser->children[] = $el;
            break;


        case 'text':
            $el = TexyHtml::el('');
            $el->childNodes[] = $tx->protect( nl2br( Texy::encode($mContent) ) );
            $parser->children[] = $el;
            break;


        case 'pre':
        case 'code':
            // outdent
            if ($spaces = strspn($mContent, ' '))
                $mContent = preg_replace("#^ {1,$spaces}#m", '', $mContent);

            if (is_callable(array($tx->handler, $type))) {
                $el = $tx->handler->$type($tx, $mLang, $mContent, $mod, $user);
                if ($el) {
                    $parser->children[] = $el;
                    return;
                }
            }

            $el = TexyHtml::el('pre');
            $mod->decorate($tx, $el);
            $el->class[] = $mLang; // lang

            if ($mType === 'code') {
                $el->childNodes[0] = TexyHtml::el('code');
                $el->childNodes[0]->setContent( $tx->protect( Texy::encode($mContent) ) );
            } else {
                $el->setContent( $tx->protect( Texy::encode($mContent) ) );
            }

            $type .= '2';
            if (is_callable(array($tx->handler, $type)))
                $tx->handler->$type($tx, $el, $user);

            $parser->children[] = $el;

        } // switch

    } // function processBlock

} // TexyBlockModule