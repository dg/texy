<?php

/**
 * This file is part of the Texy! formatter (http://texy.info/)
 *
 * @author     David Grudl
 * @copyright  Copyright (c) 2004-2007 David Grudl aka -dgx- (http://www.dgx.cz)
 * @license    GNU GENERAL PUBLIC LICENSE version 2 or 3
 * @version    $Revision$ $Date$
 * @category   Text
 * @package    Texy
 */

// security - include texy.php, not this file
if (!class_exists('Texy', FALSE)) die();



/**
 * Paragraph module
 */
final class TexyParagraphModule extends TexyModule
{
    /** @var bool  how split paragraphs (internal usage) */
    public $mode;



    public function __construct($texy)
    {
        $this->texy = $texy;
        $texy->addHandler('paragraph', array($this, 'solve'));
        $texy->addHandler('beforeParse', array($this, 'beforeParse'));
    }



    public function beforeParse()
    {
        $this->mode = TRUE;
    }



    /**
     * @param TexyBlockParser
     * @param string     text
     * @param array
     * @return vois
     */
    public function process($parser, $content, & $nodes)
    {
        $tx = $this->texy;

        if ($this->mode) {
            $parts = preg_split('#(\n{2,})#', $content, -1, PREG_SPLIT_NO_EMPTY);
        } else {
            $parts = preg_split('#(\n(?! )|\n{2,})#', $content, -1, PREG_SPLIT_NO_EMPTY);
        }

        foreach ($parts as $s)
        {
            $s = trim($s);
            if ($s === '') continue;

            // try to find modifier
            $mod = new TexyModifier;
            $mx = NULL;
            if (preg_match('#\A(.*)(?<=\A|\S)'.TEXY_MODIFIER_H.'(\n.*)?()\z#sUm', $s, $mx)) {
                list(, $mC1, $mMod, $mC2) = $mx;
                $s = trim($mC1 . $mC2);
                $mod->setProperties($mMod);
            }

            $el = $tx->invokeHandlers('paragraph', $this, array($s, $mod));
            if ($el) $nodes[] = $el;
        }
    }



    /**
     * Finish invocation
     *
     * @param TexyHandlerInvocation  handler invocation
     * @param string
     * @param TexyModifier
     * @return TexyHtml|FALSE
     */
    public function solve($invocation, $content, $mod)
    {
        $tx = $this->texy;

        // find hard linebreaks
        if ($tx->mergeLines) {
            // ....
            //  ...  => \r means break line
            $content = preg_replace('#\n (?=\S)#', "\r", $content);
        } else {
            $content = preg_replace('#\n#', "\r", $content);
        }

        $el = TexyHtml::el('p');
        $el->parseLine($tx, $content);
        $content = $el->getText(); // string

        // check content type
        // block contains block tag
        if (strpos($content, Texy::CONTENT_BLOCK) !== FALSE) {
            $el->setName(NULL);  // ignores modifier!

        // block contains text (protected)
        } elseif (strpos($content, Texy::CONTENT_TEXTUAL) !== FALSE) {
            // leave element p

        // block contains text
        } elseif (preg_match('#[^\s'.TEXY_MARK.']#u', $content)) {
            // leave element p

        // block contains only replaced element
        } elseif (strpos($content, Texy::CONTENT_REPLACED) !== FALSE) {
            $el->setName('div');

        // block contains only markup tags or spaces or nothig
        } else {
            if ($tx->ignoreEmptyStuff) return FALSE;
            if ($mod->empty) $el->setName(NULL);
        }

        if ($el->getName()) {
            // apply modifier
            $mod->decorate($tx, $el);

            // add <br />
            if (strpos($content, "\r") !== FALSE) {
                $key = $tx->protect('<br />', Texy::CONTENT_REPLACED);
                $content = str_replace("\r", $key, $content);
            };
        }

        $content = strtr($content, "\r\n", '  ');
        $el->setText($content);

        return $el;
    }

}
