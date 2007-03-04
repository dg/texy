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
 * Special document types
 */
class TexyDocumentModule extends TexyModule
{
    protected $default = array(
        'document/pre' => TRUE,
        'document/code' => TRUE,
        'document/html' => TRUE,
        'document/text' => TRUE,
        'document/texysource' => TRUE,
        'document/comment' => TRUE,
        'document/div' => TRUE,
    );


    public function init()
    {
        $tx = $this->texy;
        $tx->registerDocType(array($this, 'pattern'), 'document/pre');
        $tx->registerDocType(array($this, 'pattern'), 'document/code');
        $tx->registerDocType(array($this, 'pattern'), 'document/html');
        $tx->registerDocType(array($this, 'pattern'), 'document/text');
        $tx->registerDocType(array($this, 'pattern'), 'document/texysource');
        $tx->registerDocType(array($this, 'pattern'), 'document/comment');
        $tx->registerDocType(array($this, 'patternDiv'), 'document/div');
    }


    /**
     * Callback for: /--- div
     *
     * @param TexyDocumentParser
     * @param string
     * @param string
     * @param string
     * @param TexyModifier
     * @return TexyHtml|FALSE
     */
    public function patternDiv($parser, $content, $doctype, $desc, $mod)
    {
        $el = TexyHtml::el('div');
        $mod->decorate($this->texy, $el);
        $el->parseDocument($this->texy, $this->outdent($content));
        return $el;
    }


    /**
     * Callback for: /--- ???
     *
     * @param TexyDocumentParser
     * @param string
     * @param string
     * @param string
     * @param TexyModifier
     * @return TexyHtml|FALSE
     */
    public function pattern($parser, $content, $doctype, $desc, $mod)
    {
        // event wrapper
        $methods = array(
            'document/pre' => 'wrapPreDocument',
            'document/code' => 'wrapCodeDocument',
            'document/html' => 'wrapHtmlDocument',
            'document/text' => 'wrapTextDocument',
            'document/texysource' => 'wrapTexySourceDocument',
            'document/comment' => 'wrapCommentDocument',
        );
        if (isset($methods[$doctype])) {
            $method = $methods[$doctype];
            if (is_callable(array($this->texy->handler, $method))) {
                $res = $this->texy->handler->$method($this->texy, $content, $doctype, $desc, $mod);
                if ($res !== NULL) return $res;
            }
        }

        return $this->proceed($content, $doctype, $desc, $mod);
    }


    public function outdent($s)
    {
        $s = trim($s, "\n");
        $spaces = strspn($s, ' ');
        if ($spaces) return preg_replace("#^ {1,$spaces}#m", '', $s);
        return $s;
    }


    /**
     * Finish invocation
     *
     * @param string
     * @param string
     * @param string
     * @param TexyModifier
     * @return TexyHtml
     */
    public function proceed($content, $doctype, $desc=NULL, $mod=NULL)
    {
        $tx = $this->texy;
        if ($doctype === 'document/code') {
            $el = TexyHtml::el('pre');
            $mod->decorate($tx, $el);
            $el->class[] = $desc; // lang
            $el->childNodes[0] = TexyHtml::el('code');
            $el->childNodes[0]->setContent( $tx->protect( Texy::encode($this->outdent($content)) ) );
            return $el;
        }

        if ($doctype === 'document/pre') {
            $el = TexyHtml::el('pre');
            $mod->decorate($tx, $el);
            $el->class[] = $desc; // lang
            $el->setContent( $tx->protect( Texy::encode($this->outdent($content)) ) );
            return $el;
        }

        if ($doctype === 'document/html') {
            $lineParser = new TexyLineParser($tx);
            $lineParser->onlyHtml = TRUE;
            $content = $lineParser->parse($content);
            $content = Texy::decode($content);
            $content = Texy::encode($content);
            $content = $tx->unprotect($content);
            return TexyHtml::el()->setContent( $tx->protect($content) );
        }

        if ($doctype === 'document/text') {
            return TexyHtml::el('')->setContent( $tx->protect( nl2br( Texy::encode($content) ) ) );
        }

        if ($doctype === 'document/texysource') {
            $el = TexyHtml::el();
            $el->parseBlock($tx, $this->outdent($content));

            $html = $tx->export($el);
            $html = Texy::encode($html);

            $el = TexyHtml::el('pre')->class('html');
            $mod->decorate($tx, $el);
            $el2 = TexyHtml::el('code');
            $el->childNodes[] = $el2;
            $el2->childNodes[] = $tx->protect($html);
            return $el;
        }

        if ($doctype === 'document/comment') {
        }
    }

} // TexyDocumentModule