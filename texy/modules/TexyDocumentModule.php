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
        'document/' => TRUE,
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
        $tx->registerDocType(array($this, 'processPre'), 'document/');
        $tx->registerDocType(array($this, 'processPre'), 'document/pre');
        $tx->registerDocType(array($this, 'processPre'), 'document/code');
        $tx->registerDocType(array($this, 'processHtml'), 'document/html');
        $tx->registerDocType(array($this, 'processText'), 'document/text');
        $tx->registerDocType(array($this, 'processTexySource'), 'document/texysource');
        $tx->registerDocType(array($this, 'processComment'), 'document/comment');
        $tx->registerDocType(array($this, 'processDiv'), 'document/div');
    }


    public function processDiv($parser, $content, $name, $desc, $mod)
    {
        $tx = $this->texy;
        $el = TexyHtml::el('div');
        $mod->decorate($tx, $el);

        // outdent
        $content = trim($content, "\n");
        if ($spaces = strspn($content, ' '))
            $content = preg_replace("#^ {1,$spaces}#m", '', $content);

        $el->parseBlock($tx, $content);
        $parser->children[] = $el;
    }



    public function processPre($parser, $content, $name, $lang, $mod)
    {
        $tx = $this->texy;
        $type = str_replace('/', '', $name);
        $user = NULL;

        // outdent
        $content = trim($content, "\n");
        if ($spaces = strspn($content, ' '))
            $content = preg_replace("#^ {1,$spaces}#m", '', $content);

        if (is_callable(array($tx->handler, $type))) {
            $el = $tx->handler->$type($tx, $lang, $content, $mod, $user);
            if ($el) {
                $parser->children[] = $el;
                return;
            }
        }

        $el = TexyHtml::el('pre');
        $mod->decorate($tx, $el);
        $el->class[] = $lang; // lang

        if ($name === 'document/code') {
            $el->childNodes[0] = TexyHtml::el('code');
            $el->childNodes[0]->setContent( $tx->protect( Texy::encode($content) ) );
        } else {
            $el->setContent( $tx->protect( Texy::encode($content) ) );
        }

        $type .= '2';
        if (is_callable(array($tx->handler, $type)))
            $tx->handler->$type($tx, $el, $user);

        $parser->children[] = $el;
    }


    public function processHtml($parser, $content)
    {
        $tx = $this->texy;
        $lineParser = new TexyLineParser($tx);
        $lineParser->onlyHtml = TRUE;
        $content = $lineParser->parse($content);
        $content = Texy::decode($content);
        $content = Texy::encode($content);
        $content = $tx->unprotect($content);
        $el = TexyHtml::el();
        $el->setContent( $tx->protect($content) );
        $parser->children[] = $el;
    }


    public function processText($parser, $content)
    {
        $tx = $this->texy;
        $el = TexyHtml::el('');
        $el->childNodes[] = $tx->protect( nl2br( Texy::encode($content) ) );
        $parser->children[] = $el;
    }


    public function processTexySource($parser, $content, $name, $desc, $mod)
    {
        $tx = $this->texy;
        // outdent
        $content = trim($content, "\n");
        if ($spaces = strspn($content, ' '))
            $content = preg_replace("#^ {1,$spaces}#m", '', $content);

        $el = TexyHtml::el();
        $el->parseBlock($tx, $content);

        $html = $tx->export($el);
        $html = Texy::encode($html);

        $el = TexyHtml::el('pre')->class('html');
        $mod->decorate($tx, $el);
        $el2 = TexyHtml::el('code');
        $el->childNodes[] = $el2;
        $el2->childNodes[] = $tx->protect($html);
        $parser->children[] = $el;
    }


    public function processComment($parser, $content)
    {
    }


} // TexyDocumentModule