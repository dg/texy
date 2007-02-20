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
    protected $allow = array('Block', 'Block.pre', 'Block.text', 'Block.html', 'Block.div', 'Block.source', 'Block.comment');


    public function init()
    {
        $this->texy->registerBlockPattern(
            $this,
            'processBlock',
            '#^/--+ *(?:(code|samp|text|html|div|notexy|source|comment)( .*)?|) *'.TEXY_MODIFIER_H.'?\n(.*\n)?(?:\\\\--+ *\\1?|\z)()$#mUsi',
            'Block'
        );
    }



    /**
     * Callback function (for blocks)
     * @return object
     *
     *            /-----code html .(title)[class]{style}
     *              ....
     *              ....
     *            \----
     *
     */
    public function processBlock($parser, $matches)
    {
        list(, $mType, $mSecond, $mMod1, $mMod2, $mMod3, $mMod4, $mContent) = $matches;
        //    [1] => code
        //    [2] => lang ?
        //    [3] => (title)
        //    [4] => [class]
        //    [5] => {style}
        //    [6] => >
        //    [7] => .... content

        $mType = trim(strtolower($mType));
        $mSecond = trim(strtolower($mSecond));
        $mContent = trim($mContent, "\n");

        if (!$mType) $mType = 'pre';                // default type
        if ($mType === 'notexy') $mType = 'html'; // backward compatibility
        if ($mType === 'html' && !$this->texy->allowed['Block.html']) $mType = 'text';
        if ($mType === 'code' || $mType === 'samp')
            $mType = $this->texy->allowed['Block.pre'] ? $mType : 'none';
        elseif (empty($this->texy->allowed['Block.' . $mType])) $mType = 'none'; // transparent block

        $mod = new TexyModifier($this->texy);
        $mod->setProperties($mMod1, $mMod2, $mMod3, $mMod4);

        switch ($mType) {
        case 'none':
        case 'div':
            $el = new TexyBlockElement($this->texy);
            $el->tags[0] = $mod->generate('div');

            // outdent
            if ($spaces = strspn($mContent, ' '))
                $mContent = preg_replace("#^ {1,$spaces}#m", '', $mContent);

            $el->parse($mContent);

            $parser->element->children[] = $el;

            break;


        case 'source':
            $el = new TexyBlockElement($this->texy);
            $el->tags[0] = $mod->generate('pre');

            // outdent
            if ($spaces = strspn($mContent, ' '))
                $mContent = preg_replace("#^ {1,$spaces}#m", '', $mContent);

            $el->parse($mContent);

            $parser->element->children[] = $el;
            // TODO !!!
            /*
        $html = parent::generateContent();
        if ($this->texy->formatterModule)
            $html = $this->texy->formatterModule->postProcess($html);

        $el = new TexyCodeBlockElement($this->texy);
        $el->lang = 'html';
        $el->type = 'code';
        $el->content = $html;
        */
            break;


        case 'comment':
            break;


        case 'html':
            $el = new TexyTextualElement($this->texy);

            preg_match_all(
                '#<(/?)([a-z][a-z0-9_:-]*)(|\s(?:[\sa-z0-9:-]|=\s*"[^"'.TEXY_MARK.']*"|=\s*\'[^\''.TEXY_MARK.']*\'|=[^>'.TEXY_MARK.']*)*)(/?)>|<!--([^'.TEXY_MARK.']*?)-->#is',
                $mContent,
                $matches,
                PREG_OFFSET_CAPTURE | PREG_SET_ORDER
            );

            foreach(array_reverse($matches) as $m) {
                $offset = $m[0][1];
                foreach ($m as $key => $val) $m[$key] = $val[0];

                $mContent = substr_replace(
                    $mContent,
                    $this->texy->htmlModule->process($this, $m),
                    $offset,
                    strlen($m[0])
                );
            }
            $el->content = html_entity_decode($mContent, ENT_QUOTES, 'UTF-8');

            $parser->element->children[] = $el;
            break;


        case 'text':
            $el = new TexyTextualElement($this->texy);
            $mContent = nl2br( htmlSpecialChars($mContent, ENT_NOQUOTES) );
            $mContent = $this->texy->mark($mContent, Texy::CONTENT_BLOCK);
            $el->content = $mContent;

            $parser->element->children[] = $el;
            break;



        default: // pre | code | samp
            $el = new TexyTextualElement($this->texy);
            $el->tags[0] = $mod->generate('pre');

            $el->tags[0]->class[] = $mSecond; // lang
            if ($mType && $mType !== 'pre') $el->tags[1] = TexyHtml::el($mType); // type

            // outdent
            if ($spaces = strspn($mContent, ' '))
                $mContent = preg_replace("#^ {1,$spaces}#m", '', $mContent);

            $mContent = htmlSpecialChars($mContent, ENT_NOQUOTES);
            $mContent = $this->texy->mark($mContent, Texy::CONTENT_BLOCK);
            $el->content = $mContent;

            $parser->element->children[] = $el;

        } // switch

    } // function processBlock




} // TexyBlockModule




            