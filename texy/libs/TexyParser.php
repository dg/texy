<?php

/**
 * This file is part of the Texy! formatter (http://texy.info/)
 *
 * Copyright (c) 2004-2007 David Grudl aka -dgx- <dave@dgx.cz>
 *
 * @version  $Revision$ $Date$
 * @package  Texy
 */

// security - include texy.php, not this file
if (!defined('TEXY')) die();



class TexyParser
{
    /** @var Texy  READONLY */
    public $texy;

    /** @var TexyHtml  READONLY */
    public $parent;

    /** @var array */
    public $patterns;



    /**
     * Undefined property usage prevention
     */
    function __get($nm) { throw new Exception("Undefined property '" . get_class($this) . "::$$nm'"); }
    function __set($nm, $val) { $this->__get($nm); }

}






/**
 * Parser for block structures
 */
class TexyBlockParser extends TexyParser
{
    /** @var string */
    private $text;

    /** @var int */
    private $offset;


    /**
     * @param Texy
     * @param TexyHtml
     */
    public function __construct(Texy $texy, $element=NULL)
    {
        $this->texy = $texy;
        $this->parent = $element;
        $this->patterns = $texy->getBlockPatterns();
    }


    // match current line against RE.
    // if succesfull, increments current position and returns TRUE
    public function next($pattern, &$matches)
    {
        $matches = NULL;
        $ok = preg_match(
            $pattern . 'Am', // anchored & multiline
            $this->text,
            $matches,
            PREG_OFFSET_CAPTURE,
            $this->offset
        );

        if ($ok) {
            $this->offset += strlen($matches[0][0]) + 1;  // 1 = "\n"
            foreach ($matches as $key => $value) $matches[$key] = $value[0];
        }
        return $ok;
    }



    public function moveBackward($linesCount = 1)
    {
        while (--$this->offset > 0)
            if ($this->text{ $this->offset-1 } === "\n") {
                $linesCount--;
                if ($linesCount < 1) break;
            }

        $this->offset = max($this->offset, 0);
    }



    /**
     * @param string
     * @return array
     */
    public function parse($text)
    {
        $tx = $this->texy;

        // pre-processing
        foreach ($tx->_preBlockModules as $module)
            $text = $module->preBlock($text);


        // parser initialization
        $this->text = $text;
        $this->offset = 0;
        $nodes = array();
        $hasHandler = is_callable(array($tx->handler, 'paragraph'));

        $pb = $this->patterns;
        if (!$pb) return array(); // nothing to do

        $keys = array_keys($pb);
        $arrMatches = $arrPos = array();
        foreach ($keys as $key) $arrPos[$key] = -1;


        // parse loop
        do {
            $minKey = NULL;
            $minPos = strlen($text);
            if ($this->offset >= $minPos) break;

            foreach ($keys as $index => $key)
            {
                if ($arrPos[$key] < $this->offset) {
                    $delta = ($arrPos[$key] === -2) ? 1 : 0;
                    if (preg_match(
                            $pb[$key]['pattern'],
                            $text,
                            $arrMatches[$key],
                            PREG_OFFSET_CAPTURE,
                            $this->offset + $delta)
                    ) {
                        $m = & $arrMatches[$key];
                        $arrPos[$key] = $m[0][1];
                        foreach ($m as $keyX => $valueX) $m[$keyX] = $valueX[0];

                    } else {
                        unset($keys[$index]);
                        continue;
                    }
                }

                if ($arrPos[$key] === $this->offset) { $minKey = $key; break; }

                if ($arrPos[$key] < $minPos) { $minPos = $arrPos[$key]; $minKey = $key; }
            } // foreach

            $next = ($minKey === NULL) ? strlen($text) : $arrPos[$minKey];

            if ($next > $this->offset) {
                $str = substr($text, $this->offset, $next - $this->offset);
                $this->offset = $next;

                if ($tx->paragraphModule->mode)
                    $parts = preg_split('#(\n{2,})#', $str);
                else
                    $parts = preg_split('#(\n(?! )|\n{2,})#', $str);


                foreach ($parts as $str)
                {
                    $str = trim($str);

                    // try to find modifier
                    $mod = new TexyModifier;
                    $matches = NULL;
                    if (preg_match('#\A(.*)(?<=\A|\S)'.TEXY_MODIFIER_H.'(\n.*)?()\z#sUm', $str, $matches)) {
                        list(, $mC1, $mMod, $mC2) = $matches;
                        $str = trim($mC1 . $mC2);
                        $mod->setProperties($mMod);
                    }

                    // event wrapper
                    $el = Texy::PROCEED;
                    if ($hasHandler) $el = $tx->handler->paragraph($this, $str, $mod);

                    if ($el === Texy::PROCEED) $el = $tx->paragraphModule->solve($str, $mod);

                    if ($el) $nodes[] = $el;
                }
                continue;
            }

            $px = $pb[$minKey];
            $this->offset = $arrPos[$minKey] + strlen($arrMatches[$minKey][0]) + 1;   // 1 = \n

            $res = call_user_func_array(
                $px['handler'],
                array($this, $arrMatches[$minKey], $minKey)
            );

            if ($res === FALSE || $this->offset <= $arrPos[$minKey]) { // module rejects text
                // nemelo by se stat, rozdeli generic block
                $this->offset = $arrPos[$minKey]; // turn offset back
                $arrPos[$minKey] = -2;
                continue;
            } elseif ($res instanceof TexyHtml || is_string($res)) {
                $nodes[] = $res;
            }

            $arrPos[$minKey] = -1;

        } while (1);

        if ($this->parent)
            $this->parent->children = $nodes;

        return $nodes;
    }

} // TexyBlockParser








/**
 * Parser for single line structures
 */
class TexyLineParser extends TexyParser
{
    /** @var bool */
    public $again;



    /**
     * @param Texy
     * @param TexyHtml
     */
    public function __construct(Texy $texy, $element=NULL)
    {
        $this->texy = $texy;
        $this->parent = $element;
        $this->patterns = $texy->getLinePatterns();
    }


    /**
     * @param string
     * @return string
     */
    public function parse($text)
    {
        $tx = $this->texy;

        // initialization
        $pl = $this->patterns;
        if (!$pl) return $text; // nothing to do

        $offset = 0;
        $keys = array_keys($pl);
        $arrMatches = $arrPos = array();
        foreach ($keys as $key) $arrPos[$key] = -1;


        // parse loop
        do {
            $minKey = NULL;
            $minPos = strlen($text);

            foreach ($keys as $index => $key)
            {
                if ($arrPos[$key] < $offset) {
                    $delta = ($arrPos[$key] === -2) ? 1 : 0;

                    if (preg_match($pl[$key]['pattern'],
                            $text,
                            $arrMatches[$key],
                            PREG_OFFSET_CAPTURE,
                            $offset + $delta)
                    ) {
                        $m = & $arrMatches[$key];
                        if (!strlen($m[0][0])) continue;
                        $arrPos[$key] = $m[0][1];
                        foreach ($m as $keyx => $value) $m[$keyx] = $value[0];

                    } else {
                        unset($keys[$index]);
                        continue;
                    }
                } // if

                if ($arrPos[$key] < $minPos) {
                    $minPos = $arrPos[$key];
                    $minKey = $key;
                }
            } // foreach

            if ($minKey === NULL) break;

            $px = $pl[$minKey];
            $offset = $start = $arrPos[$minKey];

            $this->again = FALSE;
            $res = call_user_func_array(
                $px['handler'],
                array($this, $arrMatches[$minKey], $minKey)
            );

            if ($res instanceof TexyHtml) {
                $res = $res->export($tx);
            } elseif ($res === FALSE) {
                $arrPos[$minKey] = -2;
                continue;
            }

            $len = strlen($arrMatches[$minKey][0]);
            $text = substr_replace(
                $text,
                (string) $res,
                $start,
                $len
            );

            $delta = strlen($res) - $len;
            foreach ($keys as $key) {
                if ($arrPos[$key] < $start + $len) $arrPos[$key] = -1;
                else $arrPos[$key] += $delta;
            }

            if ($this->again) {
                $arrPos[$minKey] = -2;
            } else {
                $arrPos[$minKey] = -1;
                $offset += strlen($res);
            }

        } while (1);

        if ($this->parent)
            $this->parent->setText($text);

        return $text;
    }

} // TexyLineParser
