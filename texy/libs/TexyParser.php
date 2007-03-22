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



class TexyParser
{
    /** @var Texy  READONLY */
    public $texy;

    /** @var TexyHtml  READONLY */
    public $parentNode;


    /**
     * @param Texy
     * @param TexyHtml
     */
    public function __construct(Texy $texy, $element=NULL)
    {
        $this->texy = $texy;
        $this->parentNode = $element;
    }

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
            if ($this->text{ $this->offset-1 } === "\n")
                if (--$linesCount < 1) break;

        $this->offset = max($this->offset, 0);
    }



    private function genericBlock($content)
    {
        $tx = $this->texy;

        $content = trim($content);

        // try to find modifier
        $mMod = $matches = NULL;
        if (preg_match('#\A(.*)'.TEXY_MODIFIER_H.'(\n.*)?()\z#sUm', $content, $matches)) {
            list(, $mC1, $mMod, $mC2) = $matches;
            $content = trim($mC1 . $mC2);
        }

        // find hard linebreaks
        if ($tx->mergeLines) {
            // ....
            //  ...  => \r means break line
            $content = preg_replace('#\n (?=\S)#', "\r", $content);
        }

        $el = TexyHtml::el('p');
        $el->parseLine($tx, $content);
        $content = $el->childNodes; // string

        // check content type
        // block contains block tag
        if (strpos($content, Texy::CONTENT_BLOCK) !== FALSE) {
            $el->elName = '';  // ignores modifier!

        // block contains text (protected)
        } elseif (strpos($content, Texy::CONTENT_TEXTUAL) !== FALSE) {
            // leave element p

        // block contains text
        } elseif (preg_match('#[^\s'.TEXY_MARK.']#', $content)) {
            // leave element p

        // block contains only replaced element
        } elseif (strpos($content, Texy::CONTENT_REPLACED) !== FALSE) {
            $el->elName = 'div';

        // block contains only markup tags or spaces or nothig
        } else {
            if ($tx->ignoreEmptyStuff) return FALSE;
            if (!$mMod) $el->elName = '';
        }

        // apply modifier
        if ($el->elName) {
            $mod = new TexyModifier($mMod);
            $mod->decorate($tx, $el);
        }

        // add <br />
        if ($el->elName && (strpos($content, "\r") !== FALSE)) {
            $key = $tx->protect('<br />', Texy::CONTENT_REPLACED);
            $content = str_replace("\r", $key, $content);
        };
        $content = strtr($content, "\r\n", '  ');
        $el->childNodes = $content;

        return $el;
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

                if ($tx->_paragraphMode)
                    $parts = preg_split('#(\n{2,})#', $str);
                else
                    $parts = preg_split('#(\n(?! )|\n{2,})#', $str);

                foreach ($parts as $str) {
                    $el = $this->genericBlock($str);
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

        if ($this->parentNode)
            $this->parentNode->childNodes = $nodes;

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

    /** @var bool */
    public $onlyHtml;


    /**
     * @param string
     * @return string
     */
    public function parse($text)
    {
        $tx = $this->texy;

        // initialization
        if ($this->onlyHtml) {
            // special mode - parse only html tags
            $tmp = $tx->getLinePatterns();
            if (isset($tmp['html/tag'])) $pl['html/tag'] = $tmp['html/tag'];
            if (isset($tmp['html/comment'])) $pl['html/comment'] = $tmp['html/comment'];
            unset($tmp);
        } else {
            // normal mode
            $pl = $tx->getLinePatterns();
            // special escape sequences
            $text = str_replace(array('\)', '\*'), array('&#x29;', '&#x2A;'), $text);
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

        if ($this->parentNode)
            $this->parentNode->childNodes = $text;

        return $text;
    }

} // TexyLineParser
