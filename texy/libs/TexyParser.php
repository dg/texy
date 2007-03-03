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
    /** @var Texy */
    protected $texy;


    public function __construct($texy)
    {
        $this->texy = $texy;
    }

    /**
     * Undefined property usage prevention
     */
    function __get($nm) { throw new Exception("Undefined property '" . get_class($this) . "::$$nm'"); }
    function __set($nm, $val) { $this->__get($nm); }

}





/**
 * Parser for document types
 */
class TexyDocumentParser extends TexyParser
{
    /** @var array */
    public $children = array();


    public function parse($text)
    {
        $tx = $this->texy;
        $dt = $tx->getDocTypes();

        preg_match_all(
            '#^(?>([/\\\\])--+? *?)((?>\S*?)) *(\S*)'.TEXY_MODIFIER_H.'?$#mU',
            $text,
            $matches,
            PREG_OFFSET_CAPTURE | PREG_SET_ORDER
        );

        // initialization
        $i = 0;
        $docType = $tx->defaultDocument;
        $docStack = array();
        $desc = NULL;
        $mod = new TexyModifier;
        $offset = 0;
        do {
            $end = isset($matches[$i]) ? $matches[$i][0][1] - 1 : strlen($text);

            if ($end > $offset) {
                $s = substr($text, $offset, $end - $offset);

                if (isset($dt[$docType])) {
                    call_user_func_array($dt[$docType], array($this, $s, $docType, $desc, $mod));

                } else { // handle as 'texy'
                    $el = TexyHtml::el();
                    $el->parseBlock($tx, $s);
                    $this->children[] = $el;
                }
            }

            // get next document block
            if ($i == count($matches)) break;
            $match = $matches[$i];
            $i++;

            $offset = $match[0][1] + strlen($match[0][0])+1;

            if ($match[1][0] === '/') { // is opening
                $docStack[] = $docType;
                $docType = 'document/' . $match[2][0];
                $desc = isset($match[3]) ? $match[3][0] : NULL;
                $mod = isset($match[4]) ? new TexyModifier($match[4][0]) : new TexyModifier;

            } else { // closing
                $desc = NULL;
                $mod = new TexyModifier;
                $docType = $docStack ? array_pop($docStack) : $tx->defaultDocument;
            }

        } while (1);

        return $this->children;
    }

} // TexyDocumentParser












/**
 * Parser for block structures
 */
class TexyBlockParser extends TexyParser
{
    /** @var string */
    private $text;

    /** @var int */
    private $offset;

    /** @var array */
    public $children = array();



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



    private function genericBlock($content)
    {
        $tx = $this->texy;
        if ($tx->_paragraphMode)
            $parts = preg_split('#(\n{2,})#', $content);
        else
            $parts = preg_split('#(\n(?! )|\n{2,})#', $content);

        foreach ($parts as $content) {
            $content = trim($content);
            if ($content === '') continue;

            preg_match('#^(.*)'.TEXY_MODIFIER_H.'?(\n.*)?()$#sU', $content, $matches);
            list(, $mContent, $mMod, $mContent2) = $matches;

            // ....
            //  ...  => \n
            $mContent = trim($mContent . $mContent2);
            if ($tx->mergeLines) {
                // \r means break line
                $mContent = preg_replace('#\n (?=\S)#', "\r", $mContent);
            }

            $lineParser = new TexyLineParser($tx);
            $content = $lineParser->parse($mContent);

            // check content type
            $contentType = Texy::CONTENT_NONE;
            if (strpos($content, Texy::CONTENT_BLOCK) !== FALSE) {
                $contentType = Texy::CONTENT_BLOCK;
            } elseif (strpos($content, Texy::CONTENT_TEXTUAL) !== FALSE) {
                $contentType = Texy::CONTENT_TEXTUAL;
            } else {
                if (strpos($content, Texy::CONTENT_INLINE) !== FALSE) $contentType = Texy::CONTENT_INLINE;
                $s = trim( preg_replace('#['.TEXY_MARK.']+#', '', $content) );
                if (strlen($s)) $contentType = Texy::CONTENT_TEXTUAL;
            }

            // specify tag
            if ($contentType === Texy::CONTENT_TEXTUAL) $tag = 'p';
            elseif ($mMod) $tag = 'div';
            elseif ($contentType === Texy::CONTENT_BLOCK) $tag = '';
            else $tag = 'div';

            // add <br />
            if ($tag && (strpos($content, "\r") !== FALSE)) {
                $key = $tx->protect('<br />', Texy::CONTENT_INLINE);
                $content = str_replace("\r", $key, $content);
            };
            $content = strtr($content, "\r\n", '  ');

            $mod = new TexyModifier($mMod);
            $el = TexyHtml::el($tag);
            $mod->decorate($tx, $el);
            $el->childNodes[] = $content;

            $this->children[] = $el;
        }
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
                $this->genericBlock($str);
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


    public function parse($text)
    {
        $tx = $this->texy;

        // initialization
        if ($this->onlyHtml) {
            // special mode - parse only html tags
            $tmp = $tx->getLinePatterns();
            $pl['htmlTag'] = $tmp['htmlTag'];
            $pl['htmlComment'] = $tmp['htmlComment'];
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
                array($this, $arrMatches[$minKey],
                $minKey)
            );

            if ($res instanceof TexyHtml) {
                $res = $res->export($tx);
            } elseif ($res === FALSE) {
                $arrPos[$minKey] = -2;
                continue;
            } elseif (!is_string($res)) {
                $res = (string) $res;
            }

            $len = strlen($arrMatches[$minKey][0]);
            $text = substr_replace(
                $text,
                $res,
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

        return $text;
    }

} // TexyLineParser
