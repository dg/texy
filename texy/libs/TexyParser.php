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
 * Parser for block structures
 */
class TexyBlockParser
{
    private $text;        // text splited in array of lines
    private $offset;
    private $texy;
    public $children = array();



    public function __construct($texy)
    {
    	$this->texy = $texy;
    }


    // match current line against RE.
    // if succesfull, increments current position and returns TRUE
    public function receiveNext($pattern, &$matches)
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
         if ($this->text{ $this->offset-1 } === "\n")
             if (--$linesCount < 1) break;

        $this->offset = max($this->offset, 0);
    }



    public function parse($text)
    {
        // initialization
        $tx = $this->texy;
        $this->text = $text;
        $this->offset = 0;

        $pb = $tx->getBlockPatterns();
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
                $tx->genericBlock->process($this, $str);
                continue;
            }

            $px = $pb[$minKey];
            $matches = $arrMatches[$minKey];
            $this->offset = $arrPos[$minKey] + strlen($matches[0]) + 1;   // 1 = \n
            $ok = call_user_func_array($px['handler'], array($this, $matches, $minKey));
            if ($ok === FALSE || ( $this->offset <= $arrPos[$minKey] )) { // module rejects text
                $this->offset = $arrPos[$minKey]; // turn offset back
                $arrPos[$minKey] = -2;
                continue;
            }

            $arrPos[$minKey] = -1;

        } while (1);

        return $this->children;
    }

    /**
     * Undefined property usage prevention
     */
    function __get($nm) { throw new Exception("Undefined property '" . get_class($this) . "::$$nm'"); }
    function __set($nm, $val) { $this->__get($nm); }

} // TexyBlockParser








/**
 * Parser for single line structures
 */
class TexyLineParser
{
    public $again;
    private $texy;


    public function __construct($texy)
    {
        $this->texy = $texy;
    }


    public function parse($text, $select=NULL)
    {
        $tx = $this->texy;

        // initialization
        $pl = $tx->getLinePatterns();
        if ($select) {
            foreach ($select as $name) if (isset($pl[$name])) $plX[$name] = $pl[$name];
            $pl = $plX;
            unset($plX);
        }
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
            $replacement = call_user_func_array(
                $px['handler'],
                array($this, $arrMatches[$minKey],
                $minKey)
            );

            if ($replacement instanceof TexyHtml) {
                $replacement = $replacement->toText($tx);
            } elseif ($replacement === FALSE) {
                $arrPos[$minKey] = -2;
                continue;
            } elseif (!is_string($replacement)) {
                $replacement = (string) $replacement;
            }

            $len = strlen($arrMatches[$minKey][0]);
            $text = substr_replace(
                $text,
                $replacement,
                $start,
                $len
            );

            $delta = strlen($replacement) - $len;
            foreach ($keys as $key) {
                if ($arrPos[$key] < $start + $len) $arrPos[$key] = -1;
                else $arrPos[$key] += $delta;
            }

            if ($this->again) {
                $arrPos[$minKey] = -2;
            } else {
                $arrPos[$minKey] = -1;
                $offset += strlen($replacement);
            }

        } while (1);

        return $text;
    }


    /**
     * Undefined property usage prevention
     */
    function __get($nm) { throw new Exception("Undefined property '" . get_class($this) . "::$$nm'"); }
    function __set($nm, $val) { $this->__get($nm); }

} // TexyLineParser
