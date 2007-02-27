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
    protected $allow = array('blocks', 'blockPre', 'blockCode', 'blockHtml', 'blockText', 'blockTexysource', 'blockComment');


    public function init()
    {
        $this->texy->registerBlockPattern(
            $this,
            'processBlock',
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
        list(, $mType, $mSecond, $mMod1, $mMod2, $mMod3, $mMod4, $mContent) = $matches;
        //    [1] => code
        //    [2] => lang ?
        //    [3] => (title)
        //    [4] => [class]
        //    [5] => {style}
        //    [6] => >
        //    [7] => .... content

        $tx = $this->texy;
        $mType = trim(strtolower($mType));
        $mSecond = trim(strtolower($mSecond));
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

        $mod = new TexyModifier($tx);
        $mod->setProperties($mMod1, $mMod2, $mMod3, $mMod4);

        switch ($mType) {
        case 'div':
            $el = new TexyBlockElement($tx);
            $el->tags[0] = $mod->generate('div');

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
            $html = str_replace(array('&', '<', '>'), array('&amp;', '&lt;', '&gt;'), $html);

            $el = new TexyTextualElement($tx);
            $el->tags[0] = $mod->generate('pre');
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
            if (strpos($mContent, '&') !== FALSE) // speed-up
                $mContent = html_entity_decode($mContent, ENT_QUOTES, 'UTF-8');
            $el->content = str_replace(array('&', '<', '>'), array('&amp;', '&lt;', '&gt;'), $mContent);
            $el->protect = TRUE;
            $parser->children[] = $el;
            break;


        case 'text':
            $el = new TexyTextualElement($tx);
            $el->content = nl2br( str_replace(array('&', '<', '>'), array('&amp;', '&lt;', '&gt;'), $mContent) );
            $el->protect = TRUE;
            $parser->children[] = $el;
            break;



        default: // pre | code | samp
            $el = new TexyTextualElement($tx);
            $el->tags[0] = $mod->generate('pre');
            $el->tags[0]->class[] = $mSecond; // lang

            if ($mType !== 'pre') {
                $el->tags[1] = TexyHtml::el($mType); // code | samp
            }

            // outdent
            if ($spaces = strspn($mContent, ' '))
                $mContent = preg_replace("#^ {1,$spaces}#m", '', $mContent);

            $el->content = str_replace(array('&', '<', '>'), array('&amp;', '&lt;', '&gt;'), $mContent);
            $el->protect = TRUE;

            if (is_callable(array($tx->handler, $type)))
                $tx->handler->$type($tx, $mContent, $el, $mSecond, $mod);

            $parser->children[] = $el;

        } // switch

    } // function processBlock

} // TexyBlockModule