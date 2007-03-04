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
     * @param string   content
     * @param string   doctype - document/div
     * @param string   --
     * @param TexyModifier
     * @param string   two character flag
     * @return TexyHtml|string|FALSE
     */
    public function patternDiv($parser, $s, $doctype, $param, $mod, $flag)
    {
        $tx = $this->texy;
        $el = TexyHtml::el();
        $s = $this->outdent($s);
        $el->parseDocument($tx, $s);
        if ($flag[0] === '<') {
            $elX = TexyHtml::el('div');
            $mod->decorate($tx, $elX);
            array_unshift($el->childNodes, $tx->protect($elX->startTag()));
        }
        if ($flag[1] === '>') {
            $el->childNodes[] = $tx->protect('</div>');
        }
        return $el;
    }


    /**
     * Callback for: /--- ???
     *
     * @param TexyDocumentParser
     * @param string   content
     * @param string   doctype
     * @param string   additional parameter
     * @param TexyModifier
     * @return TexyHtml|string|FALSE
     */
    public function pattern($parser, $s, $doctype, $param, $mod, $flag)
    {
        // event wrapper
        $method = str_replace('/', '', $doctype);
        if (is_callable(array($this->texy->handler, $method))) {
            $res = $this->texy->handler->$method($this->texy, $s, $param, $mod, $doctype);
            if ($res !== NULL) return $res;
        }

        return $this->factory($s, $doctype, $param, $mod);
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
     * @param string   content
     * @param string   doctype
     * @param string   additional parameter
     * @param TexyModifier
     * @return TexyHtml|string|FALSE
     */
    public function factory($s, $doctype, $param=NULL, $mod=NULL)
    {
        $tx = $this->texy;

        if ($doctype === 'document/texysource') {
            $s = $this->outdent($s);
            $el = TexyHtml::el();
            $el->parseBlock($tx, $s);
            $s = $tx->export($el);
            $doctype = 'document/code'; $param = 'html'; // continue...
        }

        if ($doctype === 'document/code') {
            $el = TexyHtml::el('pre');
            $mod->decorate($tx, $el);
            $el->class[] = $param; // lang
            $el->childNodes[0] = TexyHtml::el('code');
            $s = $this->outdent($s);
            $s = Texy::encode($s);
            $s = $tx->protect($s);
            $el->childNodes[0]->setContent($s);
            return $el;
        }

        if ($doctype === 'document/pre') {
            $el = TexyHtml::el('pre');
            $mod->decorate($tx, $el);
            $el->class[] = $param; // lang
            $s = $this->outdent($s);
            $s = Texy::encode($s);
            $s = $tx->protect($s);
            $el->setContent($s);
            return $el;
        }

        if ($doctype === 'document/html') {
            $lineParser = new TexyLineParser($tx);
            $lineParser->onlyHtml = TRUE;
            $s = trim($s, "\n");
            $s = $lineParser->parse($s);
            $s = Texy::decode($s);
            $s = Texy::encode($s);
            $s = $tx->unprotect($s);
            return $tx->protect($s) . "\n";
        }

        if ($doctype === 'document/text') {
            $s = trim($s, "\n");
            $s = Texy::encode($s);
            $s = str_replace("\n", TexyHtml::el('br')->startTag() , $s); // nl2br
            return $tx->protect($s) . "\n";
        }

        if ($doctype === 'document/comment') {
            return "\n";
        }
    }

} // TexyDocumentModule