<?php

/**
 * Texy! universal text -> html converter
 * --------------------------------------
 *
 * This source file is subject to the GNU GPL license.
 *
 * @author     David Grudl aka -dgx- <dave@dgx.cz>
 * @link       http://www.texy.info/
 * @copyright  Copyright (c) 2004-2006 David Grudl
 * @license    GNU GENERAL PUBLIC LICENSE
 * @package    Texy
 * @category   Text
 * @version    1.2 for PHP4 & PHP5 (released 2006/06/01)
 */

// security - include texy.php, not this file
if (!defined('TEXY')) die();








/**
 * BLOCK MODULE CLASS
 */
class TexyBlockModule extends TexyModule
{
    var $allowed;

    /**
     * Callback that will be called with newly created element: function &myUserFunc(&$element, $type, $content)
     * @var callback
     */
    var $handler;


    function __construct(&$texy)
    {
        parent::__construct($texy);

        $this->allowed = (object) NULL;
        $this->allowed->pre  = TRUE;
        $this->allowed->text = TRUE;  // if FALSE, /--html blocks are parsed as /--text block
        $this->allowed->html = TRUE;
        $this->allowed->div  = TRUE;
        $this->allowed->source = TRUE;
        $this->allowed->comment = TRUE;
    }


    /**
     * Module initialization.
     */
    function init()
    {
        $this->texy->registerBlockPattern(
            $this,
            'processBlock',
            '#^/--+ *(?:(code|samp|text|html|div|notexy|source|comment)( .*)?|) *<MODIFIER_H>?\n(.*\n)?\\\\--+ *\\1?()$#mUsi'
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
    function processBlock(&$parser, $matches)
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
        if ($mType == 'html' && !$this->allowed->html) $mType = 'text';
        if ($mType == 'code' || $mType == 'samp')
            $mType = $this->allowed->pre ? $mType : 'none';
        elseif (!$this->allowed->$mType) $mType = 'none'; // transparent block

        switch ($mType) {
        case 'none':
        case 'div':
                $el = &new TexyBlockElement($this->texy);
                $el->tag = 'div';
                $el->modifier->setProperties($mMod1, $mMod2, $mMod3, $mMod4);
                // outdent
                if ($spaces = strspn($mContent, ' '))
                    $mContent = preg_replace("#^ {1,$spaces}#m", '', $mContent);

                $el->parse($mContent);

                if ($this->handler)
                    if (call_user_func_array($this->handler, array(&$el, $mType, $mContent)) === FALSE) return;

                $parser->element->appendChild($el);

                break;


        case 'source':
                $el = &new TexySourceBlockElement($this->texy);
                $el->modifier->setProperties($mMod1, $mMod2, $mMod3, $mMod4);
                // outdent
                if ($spaces = strspn($mContent, ' '))
                    $mContent = preg_replace("#^ {1,$spaces}#m", '', $mContent);

                $el->parse($mContent);

                if ($this->handler)
                    if (call_user_func_array($this->handler, array(&$el, $mType, $mContent)) === FALSE) return;

                $parser->element->appendChild($el);
                break;


        case 'comment':
                break;


        case 'html':
                $el = &new TexyTextualElement($this->texy);

                $old = $this->texy->patternsLine;
                $this->texy->patternsLine = array();
                $this->texy->htmlModule->init();
                $el->parse($mContent, FALSE);
                $this->texy->patternsLine = $old;

                if ($this->handler)
                    if (call_user_func_array($this->handler, array(&$el, $mType, $mContent)) === FALSE) return;

                $parser->element->appendChild($el);
                break;


        case 'text':
                $el = &new TexyTextualElement($this->texy);
                $el->setContent(
                               (
                                    nl2br(
                                        TexyHTML::htmlChars($mContent)
                                    )
                               ),
                               TRUE);

                if ($this->handler)
                    if (call_user_func_array($this->handler, array(&$el, $mType, $mContent)) === FALSE) return;

                $parser->element->appendChild($el);
                break;



        default: // pre | code | samp
                $el = &new TexyCodeBlockElement($this->texy);
                $el->modifier->setProperties($mMod1, $mMod2, $mMod3, $mMod4);
                $el->type = $mType;
                $el->lang = $mSecond;

                // outdent
                if ($spaces = strspn($mContent, ' '))
                    $mContent = preg_replace("#^ {1,$spaces}#m", '', $mContent);

                $el->setContent($mContent, FALSE); // not html-safe content

                if ($this->handler)
                    if (call_user_func_array($this->handler, array(&$el, $mType, $mContent)) === FALSE) return;

                $parser->element->appendChild($el);

        } // switch

    } // function processBlock




} // TexyBlockModule












/**
 * HTML ELEMENT PRE + CODE
 */
class TexyCodeBlockElement extends TexyTextualElement
{
    var $tag = 'pre';
    var $lang;
    var $type;


    function generateTags(&$tags)
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
    var $tag  = 'pre';


    function generateContent()
    {
        $html = parent::generateContent();
        if ($this->texy->formatterModule)
            $this->texy->formatterModule->indent($html);

        $el = &new TexyCodeBlockElement($this->texy);
        $el->lang = 'html';
        $el->type = 'code';
        $el->setContent($html, FALSE);

        if ($this->texy->blockModule->codeHandler)
            call_user_func_array($this->texy->blockModule->codeHandler, array(&$el));

        return $el->safeContent();
    }

} // TexySourceBlockElement






?>