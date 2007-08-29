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



class TexyParser extends TexyBase
{
    /** @var Texy */
    protected $texy;

    /** @var array */
    public $patterns;



    /**
     * @return Texy
     */
    public function getTexy()
    {
        return $this->texy;
    }

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

    /** @var int  0-separated, 1-child level indented, 2-child level, 3-top level */
    private $level;



    /**
     * @param Texy
     * @param TexyHtml
     */
    public function __construct(Texy $texy, $level = 0)
    {
        $this->texy = $texy;
        $this->level = $level;
        $this->patterns = $texy->getBlockPatterns();
    }



    public function getLevel()
    {
        return $this->level;
    }



    // match current line against RE.
    // if succesfull, increments current position and returns TRUE
    final public function next($pattern, &$matches)
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



    final public function moveBackward($linesCount = 1)
    {
        while (--$this->offset > 0)
            if ($this->text{ $this->offset-1 } === "\n") {
                $linesCount--;
                if ($linesCount < 1) break;
            }

        $this->offset = max($this->offset, 0);
    }



    public static function cmp($a, $b)
    {
        if ($a[0] === $b[0]) return $a[3] < $b[3] ? -1 : 1;
        if ($a[0] < $b[0]) return -1;
        return 1;
    }



    /**
     * @param string
     * @return array
     */
    public function parse($text)
    {
        $tx = $this->texy;

        $tx->invokeHandlers('beforeBlockParse', array($this, & $text));

        // parser initialization
        $this->text = $text;
        $this->offset = 0;
        $nodes = array();

        // parse loop
        $matches = array();
        $priority = 0;
        foreach ($this->patterns as $name => $pattern)
        {
            preg_match_all(
                $pattern['pattern'],
                $text,
                $ms,
                PREG_OFFSET_CAPTURE | PREG_SET_ORDER
            );

            foreach ($ms as $m) {
                $offset = $m[0][1];
                foreach ($m as $k => $v) $m[$k] = $v[0];
                $matches[] = array($offset, $name, $m, $priority);
            }
            $priority++;
        }
        unset($name, $pattern, $ms, $m, $k, $v);

        usort($matches, array(__CLASS__, 'cmp')); // generates strict error in PHP 5.1.2
        $matches[] = array(strlen($text), NULL, NULL); // terminal cap


        // process loop
        $cursor = 0;
        do {
            do {
                list($mOffset, $mName, $mMatches) = $matches[$cursor];
                $cursor++;
                if ($mName === NULL) break;
                if ($mOffset >= $this->offset) break;
            } while (1);

            // between-matches content
            if ($mOffset > $this->offset) {
                $s = trim(substr($text, $this->offset, $mOffset - $this->offset));
                if ($s !== '') {
                    $tx->paragraphModule->process($this, $s, $nodes);
                }
            }

            if ($mName === NULL) break; // finito

            $this->offset = $mOffset + strlen($mMatches[0]) + 1;   // 1 = \n

            $res = call_user_func_array(
                $this->patterns[$mName]['handler'],
                array($this, $mMatches, $mName)
            );

            if ($res === FALSE || $this->offset <= $mOffset) { // module rejects text
                // asi by se nemelo stat, rozdeli generic block
                $this->offset = $mOffset; // turn offset back
                continue;

            } elseif ($res instanceof TexyHtml) {
                $nodes[] = $res;

            } elseif (is_string($res)) {
                $res = TexyHtml::text($res);
                $nodes[] = $res;
            }

        } while (1);

        return $nodes;
    }

}








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
    public function __construct(Texy $texy)
    {
        $this->texy = $texy;
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
        $names = array_keys($pl);
        $arrMatches = $arrOffset = array();
        foreach ($names as $name) $arrOffset[$name] = -1;


        // parse loop
        do {
            $min = NULL;
            $minOffset = strlen($text);

            foreach ($names as $index => $name)
            {
                if ($arrOffset[$name] < $offset) {
                    $delta = ($arrOffset[$name] === -2) ? 1 : 0;

                    if (preg_match($pl[$name]['pattern'],
                            $text,
                            $arrMatches[$name],
                            PREG_OFFSET_CAPTURE,
                            $offset + $delta)
                    ) {
                        $m = & $arrMatches[$name];
                        if (!strlen($m[0][0])) continue;
                        $arrOffset[$name] = $m[0][1];
                        foreach ($m as $keyx => $value) $m[$keyx] = $value[0];

                    } else {
                        unset($names[$index]);
                        continue;
                    }
                } // if

                if ($arrOffset[$name] < $minOffset) {
                    $minOffset = $arrOffset[$name];
                    $min = $name;
                }
            } // foreach

            if ($min === NULL) break;

            $px = $pl[$min];
            $offset = $start = $arrOffset[$min];

            $this->again = FALSE;
            $res = call_user_func_array(
                $px['handler'],
                array($this, $arrMatches[$min], $min)
            );

            if ($res instanceof TexyHtml) {
                $res = $res->toString($tx);
            } elseif ($res === FALSE) {
                $arrOffset[$min] = -2;
                continue;
            }

            $len = strlen($arrMatches[$min][0]);
            $text = substr_replace(
                $text,
                (string) $res,
                $start,
                $len
            );

            $delta = strlen($res) - $len;
            foreach ($names as $name) {
                if ($arrOffset[$name] < $start + $len) $arrOffset[$name] = -1;
                else $arrOffset[$name] += $delta;
            }

            if ($this->again) {
                $arrOffset[$min] = -2;
            } else {
                $arrOffset[$min] = -1;
                $offset += strlen($res);
            }

        } while (1);

        return $text;
    }

}
