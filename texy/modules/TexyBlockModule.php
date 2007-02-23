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
    protected $allow = array('blocks', 'blockPre', 'blockCode', 'blockHtml', 'blockText', 'blockSource', 'blockComment');


    public function init()
    {
        $this->texy->registerBlockPattern(
            $this,
            'processBlock',
            '#^/--+ *(?:(code|text|html|div|notexy|source|comment)( .*)?|) *'.TEXY_MODIFIER_H.'?\n(.*\n)?(?:\\\\--+ *\\1?|\z)()$#mUsi',
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
        if ($mType === 'notexy') $mType = 'html'; // backward compatibility
        if ($mType === 'html' && !$tx->allowed['blockHtml']) $mType = 'text';
        if ($mType === 'source' && !$tx->allowed['blockSource']) $mType = 'pre';
        if ($mType === 'code' && !$tx->allowed['blockCode']) $mType = 'pre';
        if ($mType === 'pre' && !$tx->allowed['blockPre']) $mType = 'div';

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
            $parser->element->children[] = $el;
            break;


        case 'source':
            // outdent
            if ($spaces = strspn($mContent, ' '))
                $mContent = preg_replace("#^ {1,$spaces}#m", '', $mContent);

            $el = new TexyBlockElement($tx);
            $el->parse($mContent);

            $html = $el->toHtml();
            $html = $tx->unMarks($html);
            $html = $tx->wellForm->process($html);
            $html = $tx->formatter->process($html);

            $el = new TexyTextualElement($tx);
            $el->tags[0] = $mod->generate('pre');
            $el->tags[1] = TexyHtml::el('code')->class('html');
            $el->content = $html;
            $parser->element->children[] = $el;
            break;


        case 'comment':
            break;


        case 'html':
            preg_match_all(
                '#<(/?)([a-z][a-z0-9_:-]*)((?:\s+[a-z0-9:-]+|=\s*"[^"'.TEXY_MARK.']*"|=\s*\'[^\''.TEXY_MARK.']*\'|=[^\s>'.TEXY_MARK.']+)*)\s*(/?)>|<!--([^'.TEXY_MARK.']*?)-->#is',
                $mContent,
                $matches,
                PREG_OFFSET_CAPTURE | PREG_SET_ORDER
            );

            foreach (array_reverse($matches) as $m) {
                $offset = $m[0][1];
                foreach ($m as $key => $val) $m[$key] = $val[0];

                $mContent = substr_replace(
                    $mContent,
                    $tx->htmlModule->process($this, $m),
                    $offset,
                    strlen($m[0])
                );
            }

            $el = new TexyTextualElement($tx);
            $el->content = html_entity_decode($mContent, ENT_QUOTES, 'UTF-8');
            $parser->element->children[] = $el;
            break;


        case 'text':
            $el = new TexyTextualElement($tx);
            $mContent = nl2br( htmlSpecialChars($mContent, ENT_NOQUOTES) );
            $el->content = $tx->mark($mContent, Texy::CONTENT_BLOCK);
            $parser->element->children[] = $el;
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

            $el->content = $mContent;

            if (is_callable(array($tx->handler, $type)))
                $tx->handler->$type($tx, $el, $mSecond, $mod);

            $parser->element->children[] = $el;

        } // switch

    } // function processBlock

} // TexyBlockModule