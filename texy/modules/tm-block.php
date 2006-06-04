<?php

/**
 * --------------------------------
 *   BLOCK - TEXY! DEFAULT MODULE
 * --------------------------------
 *
 * Version 1 Release Candidate
 *
 * Copyright (c) 2005, David Grudl <dave@dgx.cz>
 * Web: http://www.texy.info/
 *
 * For the full copyright and license information, please view the COPYRIGHT
 * file that was distributed with this source code. If the COPYRIGHT file is
 * missing, please visit the Texy! homepage: http://www.texy.info
 *
 * @package Texy
 */

// security - include texy.php, not this file
if (!defined('TEXY')) die();








/**
 * BLOCK MODULE CLASS
 */
class TexyBlockModule extends TexyModule {
    var $allowed;
    var $codeHandler;               // function &myUserFunc(&$element)
    var $divHandler;                // function &myUserFunc(&$element, $nonParsedContent)
    var $htmlHandler;               // function &myUserFunc(&$element, $isHtml)


    // constructor
    function TexyBlockModule(&$texy)
    {
        parent::TexyModule($texy);

        $this->allowed->pre  = true;
        $this->allowed->text = true;  // if false, /--html blocks are parsed as /--text block
        $this->allowed->html = true;
        $this->allowed->div  = true;
        $this->allowed->form = true;
        $this->allowed->source = true;
        $this->allowed->comment = true;
    }


    /***
     * Module initialization.
     */
    function init()
    {
        if (isset($this->userFunction)) $this->codeHandler = $this->userFunction;  // !!! back compatibility

        $this->registerBlockPattern('processBlock',   '#^/--+ *(?:(code|samp|text|html|div|form|notexy|source|comment)( .*)?|) *MODIFIER_H?\n(.*\n)?\\\\--+ *\\1?()$#mUsi');
    }



    /***
     * Callback function (for blocks)
     * @return object
     *
     *            /-----code html .(title)[class]{style}
     *              ....
     *              ....
     *            \----
     *
     */
    function processBlock(&$blockParser, &$matches)
    {
        list($match, $mType, $mSecond, $mMod1, $mMod2, $mMod3, $mMod4, $mContent) = $matches;
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

                 if ($this->divHandler)
                     call_user_func_array($this->divHandler, array(&$el, &$mContent));

                 $el->parse($mContent);
                 $blockParser->addChildren($el);

                 break;


         case 'source':
                 $el = &new TexySourceBlockElement($this->texy);
                 $el->modifier->setProperties($mMod1, $mMod2, $mMod3, $mMod4);
                 // outdent
                 if ($spaces = strspn($mContent, ' '))
                     $mContent = preg_replace("#^ {1,$spaces}#m", '', $mContent);

                 $el->parse($mContent);
                 $blockParser->addChildren($el);
                 break;


         case 'form':
                 $el = &new TexyFormElement($this->texy);
                 $el->action->set($mSecond);
                 $el->modifier->setProperties($mMod1, $mMod2, $mMod3, $mMod4);
                 // outdent
                 if ($spaces = strspn($mContent, ' '))
                     $mContent = preg_replace("#^ {1,$spaces}#m", '', $mContent);

                 $el->parse($mContent);
                 $blockParser->addChildren($el);
                 break;

         case 'comment':
                 break;


         case 'html':
                 $el = &new TexyTextualElement($this->texy);
//         $el->setContent($mContent, true);

                 if ($this->htmlHandler)
                     call_user_func_array($this->htmlHandler, array(&$el, true));

                 $old = $this->texy->patternsLine;
                 $this->texy->patternsLine = array();
                 $this->texy->htmlModule->init();
                 $el->parse($mContent, false);
                 $this->texy->patternsLine = $old;

                 $blockParser->addChildren($el);
                 break;


         case 'text':
                 $el = &new TexyTextualElement($this->texy);
                 $el->setContent(
                                (
                                     nl2br(
                                         Texy::htmlChars($mContent)
                                     )
                                ),
                                true);
                 $blockParser->addChildren($el);

                 if ($this->htmlHandler)
                     call_user_func_array($this->htmlHandler, array(&$el, false));
                 break;



         default: // pre | code | samp
                 $el = &new TexyCodeBlockElement($this->texy);
                 $el->modifier->setProperties($mMod1, $mMod2, $mMod3, $mMod4);
                 $el->type = $mType;
                 $el->lang = $mSecond;

                 // outdent
                 if ($spaces = strspn($mContent, ' '))
                     $mContent = preg_replace("#^ {1,$spaces}#m", '', $mContent);

                 $el->setContent($mContent, false); // not html-safe content
                 $blockParser->addChildren($el);

                 if ($this->codeHandler)
                     call_user_func_array($this->codeHandler, array(&$el));
        } // switch
    }



    function trustMode()
    {
        $this->allowed->form = true;
    }



    function safeMode()
    {
        $this->allowed->form = false;
    }


} // TexyBlockModule







/****************************************************************************
                                                             TEXY! DOM ELEMENTS                          */





/**
 * HTML ELEMENT PRE + CODE
 */
class TexyCodeBlockElement extends TexyTextualElement {
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







class TexySourceBlockElement extends TexyBlockElement {
    var $tag  = 'pre';


    function generateContent()
    {
        $html = parent::generateContent();
        if ($this->texy->formatterModule)
            $this->texy->formatterModule->indent($html);

        $el = &new TexyCodeBlockElement($this->texy);
        $el->lang = 'html';
        $el->type = 'code';
        $el->setContent($html, false);

        if ($this->texy->blockModule->codeHandler)
            call_user_func_array($this->texy->blockModule->codeHandler, array(&$el));

        return $el->safeContent();
    }

} // TexySourceBlockElement






/**
 * HTML ELEMENT FORM
 */
class TexyFormElement extends TexyBlockElement {
    var $tag = 'form';
    var $action;
    var $post = true;


    function TexyFormElement(&$texy)
    {
        parent::TexyBlockElement($texy);
        $this->action = & $texy->createURL();
    }


    function generateTags(&$tags)
    {
        parent::generateTags($tags);
        $attr = & $tags['form'];

        if ($this->action->URL) $attr['action'] = $this->action->URL;
        $attr['method'] = $this->post ? 'post' : 'get';
        $attr['enctype'] = $this->post ? 'multipart/form-data' : '';
    }




} // TexyFormElement



?>