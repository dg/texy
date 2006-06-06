<?php

/**
 * Texy! universal text -> html converter
 * --------------------------------------
 *
 * This source file is subject to the GNU GPL license.
 *
 * @author     David Grudl aka -dgx- <dave@dgx.cz>
 * @link       http://www.texy.info/
 * @copyright  Copyright (c) 2004-2006 David Grudl
 * @license    GNU GENERAL PUBLIC LICENSE
 * @package    Texy
 * @category   Text
 * @version    1.5 for PHP4 & PHP5 $Date$ $Revision$
 */

// security - include texy.php, not this file
if (!defined('TEXY')) die();



/**
 * INTERNAL PARSING BLOCK STRUCTURE
 * --------------------------------
 */
class TexyBlockParser
{
    var $element;     // TexyBlockElement
    var $text;        // text splited in array of lines
    var $offset;


    function __construct(&$element)
    {
        $this->element = &$element;
    }


    /**
     * PHP4-only constructor
     * @see http://www.dgx.cz/trine/item/how-to-emulate-php5-object-model-in-php4
     */
    function TexyBlockParser(&$element)
    {
        // generate references
        foreach ($this as $key => $foo) $GLOBALS['$$HIDDEN$$'][] = & $this->$key;

        // call PHP5 constructor
        call_user_func_array(array(&$this, '__construct'), array(&$element));
    }


    // match current line against RE.
    // if succesfull, increments current position and returns TRUE
    function receiveNext($pattern, &$matches)
    {
        $ok = preg_match(
                   $pattern . 'Am', // anchored & multiline
                   $this->text,
                   $matches,
                   PREG_OFFSET_CAPTURE,
                   $this->offset);
        if ($ok) {
            $this->offset += strlen($matches[0][0]) + 1;  // 1 = "\n"
            foreach ($matches as $key => $value) $matches[$key] = $value[0];
        }
        return $ok;
    }



    function moveBackward($linesCount = 1)
    {
        while (--$this->offset > 0)
         if ($this->text{ $this->offset-1 } == TEXY_NEWLINE)
             if (--$linesCount < 1) break;

        $this->offset = max($this->offset, 0);
    }




    function parse($text)
    {
            ///////////   INITIALIZATION
        $texy = &$this->element->texy;
        $this->text = & $text;
        $this->offset = 0;

        $patternKeys = array_keys($texy->patternsBlock);
        $arrMatches = $arrPos = array();
        foreach ($patternKeys as $key) $arrPos[$key] = -1;


            ///////////   PARSING
        do {
            $minKey = -1;
            $minPos = strlen($this->text);
            if ($this->offset >= $minPos) break;

            foreach ($patternKeys as $index => $key) {
                if ($arrPos[$key] === FALSE) continue;

                if ($arrPos[$key] < $this->offset) {
                    $delta = ($arrPos[$key] == -2) ? 1 : 0;
                    $matches = & $arrMatches[$key];
                    if (preg_match(
                            $texy->patternsBlock[$key]['pattern'],
                            $text,
                            $matches,
                            PREG_OFFSET_CAPTURE,
                            $this->offset + $delta)) {

                        $arrPos[$key] = $matches[0][1];
                        foreach ($matches as $keyX => $valueX) $matches[$keyX] = $valueX[0];

                    } else {
                        unset($patternKeys[$index]);
                        continue;
                    }
                }

                if ($arrPos[$key] === $this->offset) { $minKey = $key; break; }

                if ($arrPos[$key] < $minPos) { $minPos = $arrPos[$key]; $minKey = $key; }
            } // foreach

            $next = ($minKey == -1) ? strlen($text) : $arrPos[$minKey];

            if ($next > $this->offset) {
                $str = substr($text, $this->offset, $next - $this->offset);
                $this->offset = $next;
                call_user_func_array($texy->genericBlock, array(&$this, $str));
                continue;
            }

            $px = & $texy->patternsBlock[$minKey];
            $matches = & $arrMatches[$minKey];
            $this->offset = $arrPos[$minKey] + strlen($matches[0]) + 1;   // 1 = \n
            $ok = call_user_func_array($px['handler'], array(&$this, $matches, $px['user']));
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
 * INTERNAL PARSING LINE STRUCTURE
 * -------------------------------
 */
class TexyLineParser
{
    var $element;   // TexyTextualElement


    function __construct(&$element)
    {
        $this->element = &$element;
    }


    /**
     * PHP4-only constructor
     * @see http://www.dgx.cz/trine/item/how-to-emulate-php5-object-model-in-php4
     */
    function TexyLineParser(&$element)
    {
        // generate references
        foreach ($this as $key => $foo) $GLOBALS['$$HIDDEN$$'][] = & $this->$key;

        // call PHP5 constructor
        call_user_func_array(array(&$this, '__construct'), array(&$element));
    }



    function parse($text, $postProcess = TRUE)
    {
            ///////////   INITIALIZATION
        $element = &$this->element;
        $texy = &$element->texy;

        $offset = 0;
        $hashStrLen = 0;
        $patternKeys = array_keys($texy->patternsLine);
        $arrMatches = $arrPos = array();
        foreach ($patternKeys as $key) $arrPos[$key] = -1;


            ///////////   PARSING
        do {
            $minKey = -1;
            $minPos = strlen($text);

            foreach ($patternKeys as $index => $key) {
                if ($arrPos[$key] < $offset) {
                    $delta = ($arrPos[$key] == -2) ? 1 : 0;
                    $matches = & $arrMatches[$key];
                    if (preg_match($texy->patternsLine[$key]['pattern'],
                                     $text,
                                     $matches,
                                     PREG_OFFSET_CAPTURE,
                                     $offset+$delta)) {
                        if (!strlen($matches[0][0])) continue;
                        $arrPos[$key] = $matches[0][1];
                        foreach ($matches as $keyx => $value) $matches[$keyx] = $value[0];

                    } else {

                        unset($patternKeys[$index]);
                        continue;
                    }
                } // if

                if ($arrPos[$key] == $offset) { $minKey = $key; break; }

                if ($arrPos[$key] < $minPos) { $minPos = $arrPos[$key]; $minKey = $key; }

            } // foreach

            if ($minKey == -1) break;

            $px = & $texy->patternsLine[$minKey];
            $offset = $arrPos[$minKey];
            $replacement = call_user_func_array($px['handler'], array(&$this, $arrMatches[$minKey], $px['user']));
            $len = strlen($arrMatches[$minKey][0]);
            $text = substr_replace(
                        $text,
                        $replacement,
                        $offset,
                        $len);

            $delta = strlen($replacement) - $len;
            foreach ($patternKeys as $key) {
                if ($arrPos[$key] < $offset + $len) $arrPos[$key] = -1;
                else $arrPos[$key] += $delta;
            }

            $arrPos[$minKey] = -2;

        } while (1);

        $text = TexyHTML::htmlChars($text, false, true);

        if ($postProcess)
            foreach ($texy->modules as $id => $foo)
                $texy->modules[$id]->linePostProcess($text);

        $element->setContent($text, TRUE);

        if ($element->contentType == TEXY_CONTENT_NONE) {
            $s = trim( preg_replace('#['.TEXY_HASH.']+#', '', $text) );
            if (strlen($s)) $element->contentType = TEXY_CONTENT_TEXTUAL;
        }
    }

} // TexyLineParser




?>