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
 * BLOCK MODULE CLASS
 */
class TexyBlockModule extends TexyModule
{
    public $codeHandler;               // function myUserFunc($element)
    public $divHandler;                // function myUserFunc($element, $nonParsedContent)
    public $htmlHandler;               // function myUserFunc($element, $isHtml)


    public function __construct($texy)
    {
        parent::__construct($texy);

        $allowed = & $this->texy->allowed;
        $allowed['Block.pre']  = TRUE;
        $allowed['Block.text'] = TRUE;  // if FALSE, /--html blocks are parsed as /--text block
        $allowed['Block.html'] = TRUE;
        $allowed['Block.div']  = TRUE;
        $allowed['Block.source'] = TRUE;
        $allowed['Block.comment'] = TRUE;
    }


    /**
     * Module initialization.
     */
    public function init()
    {
        $this->texy->registerBlockPattern(
            $this,
            'processBlock',
            '#^/--+ *(?:(code|samp|text|html|div|notexy|source|comment)( .*)?|) *'.TEXY_MODIFIER_H.'?\n(.*\n)?(?:\\\\--+ *\\1?|\z)()$#mUsi'
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
        if ($mType == 'notexy') $mType = 'html'; // backward compatibility
        if ($mType == 'html' && !$this->texy->allowed['Block.html']) $mType = 'text';
        if ($mType == 'code' || $mType == 'samp')
            $mType = $this->texy->allowed['Block.pre'] ? $mType : 'none';
        elseif (!$this->texy->allowed['Block.' . $mType]) $mType = 'none'; // transparent block

        switch ($mType) {
        case 'none':
        case 'div':
            $el = new TexyBlockElement($this->texy);
            $el->tag = 'div';
            $el->modifier->setProperties($mMod1, $mMod2, $mMod3, $mMod4);
            // outdent
            if ($spaces = strspn($mContent, ' '))
                $mContent = preg_replace("#^ {1,$spaces}#m", '', $mContent);

            if ($this->divHandler)
                 if (call_user_func_array($this->divHandler, array($el, &$mContent)) === FALSE) return;

            $el->parse($mContent);

            $parser->element->appendChild($el);

            break;


        case 'source':
            $el = new TexySourceBlockElement($this->texy);
            $el->modifier->setProperties($mMod1, $mMod2, $mMod3, $mMod4);
            // outdent
            if ($spaces = strspn($mContent, ' '))
                $mContent = preg_replace("#^ {1,$spaces}#m", '', $mContent);

            $el->parse($mContent);

            $parser->element->appendChild($el);
            break;


        case 'comment':
            break;


        case 'html':
            ///////////   INITIALIZATION
            $el = new TexyTextualElement($this->texy);

            preg_match_all(
                '#<(/?)([a-z][a-z0-9_:-]*)(|\s(?:[\sa-z0-9:-]|=\s*"[^"'.TEXY_HASH.']*"|=\s*\'[^\''.TEXY_HASH.']*\'|=[^>'.TEXY_HASH.']*)*)(/?)>|<!--([^'.TEXY_HASH.']*?)-->#is',
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
            $mContent = html_entity_decode($mContent, ENT_QUOTES, 'UTF-8');
            $mContent = htmlspecialchars($mContent);
            $mContent = $this->texy->hashReplace($mContent);
            $mContent = $this->texy->hash($mContent, TexyDomElement::CONTENT_BLOCK);
            $el->content = $mContent;

            if ($this->htmlHandler)
                if (call_user_func_array($this->htmlHandler, array($el, TRUE)) === FALSE) return;

            $parser->element->appendChild($el);
            break;


        case 'text':
            $el = new TexyTextualElement($this->texy);
            $mContent = nl2br(TexyHtml::htmlChars($mContent));
            $mContent = $this->texy->hash($mContent, TexyDomElement::CONTENT_BLOCK);
            $el->content = $mContent;

            if ($this->htmlHandler)
                if (call_user_func_array($this->htmlHandler, array($el, FALSE)) === FALSE) return;

            $parser->element->appendChild($el);
            break;



        default: // pre | code | samp
            $el = new TexyCodeBlockElement($this->texy);
            $el->modifier->setProperties($mMod1, $mMod2, $mMod3, $mMod4);
            $el->type = $mType;
            $el->lang = $mSecond;

            // outdent
            if ($spaces = strspn($mContent, ' '))
                $mContent = preg_replace("#^ {1,$spaces}#m", '', $mContent);

            $mContent = htmlSpecialChars($mContent);
            $mContent = $this->texy->hash($mContent, TexyDomElement::CONTENT_BLOCK);
            $el->content = $mContent;

            if ($this->codeHandler)
                if (call_user_func_array($this->codeHandler, array($el)) === FALSE) return;

            $parser->element->appendChild($el);

        } // switch

    } // function processBlock




} // TexyBlockModule












/**
 * HTML ELEMENT PRE + CODE
 */
class TexyCodeBlockElement extends TexyTextualElement
{
    public $tag = 'pre';
    public $lang;
    public $type;


    protected function generateTags(&$tags)
    {
        parent::generateTags($tags);

        if ($this->tag) {
            $tags[$this->tag]['class'][] = $this->lang;

            if ($this->type)
                $tags[$this->type] = array();
        }
    }


} // TexyCodeBlockElement




class TexySourceBlockElement extends TexyBlockElement
{
    public $tag  = 'pre';


    protected function generateContent()
    {
        $html = parent::generateContent();
        if ($this->texy->formatterModule)
            $html = $this->texy->formatterModule->postProcess($html);

        $el = new TexyCodeBlockElement($this->texy);
        $el->lang = 'html';
        $el->type = 'code';
        $el->content = $html;

        if ($this->texy->blockModule->codeHandler)
            call_user_func_array($this->texy->blockModule->codeHandler, array($el));

        return $el->safeContent();
    }

} // TexySourceBlockElement
