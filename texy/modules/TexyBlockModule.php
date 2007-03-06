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
    protected $default = array(
        'blocks' => TRUE,
        'block/pre' => TRUE,
        'block/code' => TRUE,
        'block/html' => TRUE,
        'block/text' => TRUE,
        'block/texysource' => TRUE,
        'block/comment' => TRUE,
        'block/div' => TRUE,
    );

    /** @var string */
    public $defaultType = 'pre';


    public function init(&$text)
    {
        $this->texy->registerBlockPattern(
            array($this, 'pattern'),
            '#^(?>/--+? *?([^\n]*)'.TEXY_MODIFIER_H.'?$)((?:\n(?0)|\n.*)*)(?:\n\\\\--.*$|\z)#mUsi',
            'blocks'
        );
    }


    /**
     * Full text pre-processing
     * @param string
     * @return string
     */
    public function preBlock($text)
    {
        // autoclose exclusive blocks
        $text = preg_replace(
            '#^((?>/--+? *?)(?!div|texysource)[^\n]*)$((?:\n.*)*)(?:\n\\\\--.*$|\z|(?=(\n/--.*$)))#mUsi',
            "\$1\$2\n\--",
            $text
        );
        return $text;
    }


    /**
     * Callback for:
     *   /-----code html .(title)[class]{style}
     *     ....
     *     ....
     *   \----
     *
     * @param TexyBlockParser
     * @param array      regexp matches
     * @param string     pattern name
     * @return TexyHtml|string|FALSE
     */
    public function pattern($parser, $matches, $name)
    {
        list(, $mParam, $mMod, $mContent) = $matches;
        //    [1] => code | text | ...
        //    [2] => ... additional parameters
        //    [3] => .(title)[class]{style}<>
        //    [4] => ... content

        $mod = new TexyModifier($mMod);
        $param = trim($mParam);
        if ($param === '') {
            $blocktype = 'block/' . $this->defaultType;
        } else {
            $parts = preg_split('#\s+#', $param, 2);
            $blocktype = 'block/' . $parts[0];
            $param = isset($parts[1]) ? $parts[1] : NULL;
        }

        // event wrapper
        if (is_callable(array($this->texy->handler, 'block'))) {
            $res = $this->texy->handler->block($parser, $blocktype, $mContent, $param, $mod);
            if ($res !== NULL) return $res;
        }

        return $this->solve($blocktype, $mContent, $param, $mod);
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
     * @param string   blocktype
     * @param string   content
     * @param string   additional parameter
     * @param TexyModifier
     * @return TexyHtml|string|FALSE
     */
    public function solve($blocktype, $s, $param, $mod)
    {
        $tx = $this->texy;

        if (empty($tx->allowed[$blocktype])) return FALSE;

        if ($blocktype === 'block/texysource') {
            $s = $this->outdent($s);
            $el = TexyHtml::el();
            $el->parseBlock($tx, $s);
            $s = $tx->export($el);
            $blocktype = 'block/code'; $param = 'html'; // continue...
        }

        if ($blocktype === 'block/code') {
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

        if ($blocktype === 'block/pre') {
            $el = TexyHtml::el('pre');
            $mod->decorate($tx, $el);
            $el->class[] = $param; // lang
            $s = $this->outdent($s);
            $s = Texy::encode($s);
            $s = $tx->protect($s);
            $el->setContent($s);
            return $el;
        }

        if ($blocktype === 'block/html') {
            $lineParser = new TexyLineParser($tx);
            $lineParser->onlyHtml = TRUE;
            $s = trim($s, "\n");
            $s = $lineParser->parse($s);
            $s = Texy::decode($s);
            $s = Texy::encode($s);
            $s = $tx->unprotect($s);
            return $tx->protect($s) . "\n";
        }

        if ($blocktype === 'block/text') {
            $s = trim($s, "\n");
            $s = Texy::encode($s);
            $s = str_replace("\n", TexyHtml::el('br')->startTag() , $s); // nl2br
            return $tx->protect($s) . "\n";
        }

        if ($blocktype === 'block/comment') {
            return "\n";
        }

        if ($blocktype === 'block/div') {
            $el = TexyHtml::el('div');
            $mod->decorate($tx, $el);
            $s = $this->outdent($s);
            $el->parseBlock($tx, $s);
            return $el;
        }

        return FALSE;
    }

} // TexyBlockModule