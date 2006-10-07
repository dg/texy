<?php

/**
 * Texy! universal text -> html converter
 * --------------------------------------
 *
 * This source file is subject to the GNU GPL license.
 *
 * @author     David Grudl aka -dgx- <dave@dgx.cz>
 * @link       http://texy.info/
 * @copyright  Copyright (c) 2004-2006 David Grudl
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
    public $allowed;
    public $codeHandler;               // function &myUserFunc($element)
    public $divHandler;                // function &myUserFunc($element, $nonParsedContent)
    public $htmlHandler;               // function &myUserFunc($element, $isHtml)


    public function __construct($texy)
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
    public function init()
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
        if ($mType == 'html' && !$this->allowed->html) $mType = 'text';
        if ($mType == 'code' || $mType == 'samp')
            $mType = $this->allowed->pre ? $mType : 'none';
        elseif (!$this->allowed->$mType) $mType = 'none'; // transparent block

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
                $el = new TexyTextualElement($this->texy);

                // strasny hnusny hack !!!
                $old = $this->texy->patternsLine;
                $oldm = $this->texy->modules;
                foreach ($this->texy->modules as $key => $module) {
                        if ($module instanceof TexyLongWordsModule) unset($this->texy->modules[$key]);
                        if ($module instanceof TexyQuickCorrectModule) unset($this->texy->modules[$key]);
                }
                $this->texy->patternsLine = array();
                $this->texy->htmlModule->init();

                $el->parse($mContent);

                $this->texy->patternsLine = $old;
                $this->texy->modules = $oldm;

                if ($this->htmlHandler)
                    if (call_user_func_array($this->htmlHandler, array($el, true)) === FALSE) return;

                $parser->element->appendChild($el);
                break;


        case 'text':
                $el = new TexyTextualElement($this->texy);
                $el->setContent(
                               (
                                    nl2br(
                                        TexyHtml::htmlChars($mContent)
                                    )
                               ),
                               TRUE);

                if ($this->htmlHandler)
                    if (call_user_func_array($this->htmlHandler, array($el, false)) === FALSE) return;

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

                $el->setContent($mContent, FALSE); // not html-safe content

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
            $this->texy->formatterModule->indent($html);

        $el = new TexyCodeBlockElement($this->texy);
        $el->lang = 'html';
        $el->type = 'code';
        $el->setContent($html, FALSE);

        if ($this->texy->blockModule->codeHandler)
            call_user_func_array($this->texy->blockModule->codeHandler, array($el));

        return $el->safeContent();
    }

} // TexySourceBlockElement
