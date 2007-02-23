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
 * Texy! parser base class
 */
abstract class TexyParser
{
    /** @var TexyDomElement */
    public $element;



    /**
     * @param TexyDomElement
     */
    public function __construct($element)
    {
        $this->element = $element;
    }



    /**
     * @param string
     */
    abstract public function parse($text);



    /**
     * Undefined property usage prevention
     */
    function __get($nm) { throw new Exception("Undefined property '" . get_class($this) . "::$$nm'"); }
    function __set($nm, $val) { $this->__get($nm); }
    private function __unset($nm) { $this->__get($nm); }
    private function __isset($nm) { $this->__get($nm); }

}







/**
 * Parser for block structures
 */
class TexyBlockParser extends TexyParser
{
    private $text;        // text splited in array of lines
    private $offset;



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
        $texy = $this->element->texy;
        $this->text = $text;
        $this->offset = 0;

        $pb = $texy->getBlockPatterns();
        $keys = array_keys($pb);
        $arrMatches = $arrPos = array();
        foreach ($keys as $key) $arrPos[$key] = -1;


        // parse loop
        do {
            $minKey = -1;
            $minPos = strlen($text);
            if ($this->offset >= $minPos) break;

            foreach ($keys as $index => $key) {
                if ($arrPos[$key] === FALSE) continue;

                if ($arrPos[$key] < $this->offset) {
                    $delta = ($arrPos[$key] === -2) ? 1 : 0;
                    if (preg_match(
                            $pb[$key]['pattern'],
                            $text,
                            $arrMatches[$key],
                            PREG_OFFSET_CAPTURE,
                            $this->offset + $delta)) {
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

            $next = ($minKey === -1) ? strlen($text) : $arrPos[$minKey];

            if ($next > $this->offset) {
                $str = substr($text, $this->offset, $next - $this->offset);
                $this->offset = $next;
                $texy->genericBlock->process($this, $str);
                continue;
            }

            $px = $pb[$minKey];
            $matches = $arrMatches[$minKey];
            $this->offset = $arrPos[$minKey] + strlen($matches[0]) + 1;   // 1 = \n
            $ok = call_user_func_array($px['handler'], array($this, $matches, $px['name']));
            if ($ok === FALSE || ( $this->offset <= $arrPos[$minKey] )) { // module rejects text
                $this->offset = $arrPos[$minKey]; // turn offset back
                $arrPos[$minKey] = -2;
                continue;
            }

            $arrPos[$minKey] = -1;

        } while (1);
    }

} // TexyBlockParser








/**
 * Parser for single line structures
 */
class TexyLineParser extends TexyParser
{

    public function parse($text)
    {
        // initialization
        $element = $this->element;
        $texy = $element->texy;

        $offset = 0;
        $pl = $texy->getLinePatterns();
        $keys = array_keys($pl);
        $arrMatches = $arrPos = array();
        foreach ($keys as $key) $arrPos[$key] = -1;


        // parse loop
        do {
            $minKey = -1;
            $minPos = strlen($text);

            foreach ($keys as $index => $key) {
                if ($arrPos[$key] < $offset) {
                    $delta = ($arrPos[$key] === -2) ? 1 : 0;

                    if (preg_match($pl[$key]['pattern'],
                            $text,
                            $arrMatches[$key],
                            PREG_OFFSET_CAPTURE,
                            $offset + $delta)) {

                        $m = & $arrMatches[$key];
                        if (!strlen($m[0][0])) continue;
                        $arrPos[$key] = $m[0][1];
                        foreach ($m as $keyx => $value) $m[$keyx] = $value[0];

                    } else {

                        unset($keys[$index]);
                        continue;
                    }
                } // if

                if ($arrPos[$key] === $offset) { $minKey = $key; break; }

                if ($arrPos[$key] < $minPos) { $minPos = $arrPos[$key]; $minKey = $key; }

            } // foreach

            if ($minKey === -1) break;

            $px = $pl[$minKey];
            $offset = $arrPos[$minKey];
            $replacement = call_user_func_array($px['handler'], array($this, $arrMatches[$minKey], $px['name']));
            $len = strlen($arrMatches[$minKey][0]);
            $text = substr_replace(
                $text,
                $replacement,
                $offset,
                $len);

            $delta = strlen($replacement) - $len;
            foreach ($keys as $key) {
                if ($arrPos[$key] < $offset + $len) $arrPos[$key] = -1;
                else $arrPos[$key] += $delta;
            }

            $arrPos[$minKey] = -2;

        } while (1);

        if (strpos($text, '&') !== FALSE) // speed-up
            $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');

        foreach ($texy->getLineModules() as $module)
            $text = $module->linePostProcess($text);

        $element->content = $text;
    }

} // TexyLineParser
