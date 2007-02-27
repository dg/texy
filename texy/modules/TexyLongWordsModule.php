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
 * Long words wrap module
 */
class TexyLongWordsModule extends TexyModule implements ITexyLineModule
{
    protected $allow = array('longWords');

    public $wordLimit = 20;


    const
        DONT = 0,   // don't hyphenate
        HERE = 1,   // hyphenate here
        AFTER = 2;  // hyphenate after


    private $consonants = array(
        'b','c','d','f','g','h','j','k','l','m','n','p','q','r','s','t','v','w','x','z',
        'B','C','D','F','G','H','J','K','L','M','N','P','Q','R','S','T','V','W','X','Z',
        "\xc4\x8d","\xc4\x8f","\xc5\x88","\xc5\x99","\xc5\xa1","\xc5\xa5","\xc5\xbe", //czech utf-8
        "\xc4\x8c","\xc4\x8e","\xc5\x87","\xc5\x98","\xc5\xa0","\xc5\xa4","\xc5\xbd");

    private $vowels = array(
        'a','e','i','o','u','y',
        'A','E','I','O','U','Y',
        "\xc3\xa1","\xc3\xa9","\xc4\x9b","\xc3\xad","\xc3\xb3","\xc3\xba","\xc5\xaf","\xc3\xbd", //czech utf-8
        "\xc3\x81","\xc3\x89","\xc4\x9a","\xc3\x8d","\xc3\x93","\xc3\x9a","\xc5\xae","\xc3\x9d");

    private $before_r = array(
        'b','B','c','C','d','D','f','F','g','G','k','K','p','P','r','R','t','T','v','V',
        "\xc4\x8d","\xc4\x8c","\xc4\x8f","\xc4\x8e","\xc5\x99","\xc5\x98","\xc5\xa5","\xc5\xa4"); //czech utf-8

    private $before_l = array(
        'b','B','c','C','d','D','f','F','g','G','k','K','l','L','p','P','t','T','v','V',
        "\xc4\x8d","\xc4\x8c","\xc4\x8f","\xc4\x8e","\xc5\xa5","\xc5\xa4"); //czech utf-8

    private $before_h = array('c','C','s','S');

    private $doubleVowels = array('a','A','o','O');




    public function __construct($texy)
    {
        parent::__construct($texy);

        $this->consonants = array_flip($this->consonants);
        $this->vowels = array_flip($this->vowels);
        $this->before_r = array_flip($this->before_r);
        $this->before_l = array_flip($this->before_l);
        $this->before_h = array_flip($this->before_h);
        $this->doubleVowels = array_flip($this->doubleVowels);
    }



    public function linePostProcess($text)
    {
        if (empty($this->texy->allowed['longWords'])) return $text;

        return preg_replace_callback(
            '#[^\ \n\t\-\x{2013}\x{ad}\x15\x16\x17'.TEXY_MARK_SPACES.']{'.$this->wordLimit.',}#u',
            array($this, '_replace'),
            $text);
    }



    /**
     * Callback function: rozdìlí dlouhá slova na slabiky - EXPERIMENTÁLNÍ
     * (c) David Grudl
     * @return string
     */
    private function _replace($matches)
    {
        list($mWord) = $matches;
        //    [0] => lllloooonnnnggggwwwoorrdddd

        $chars = array();
        preg_match_all(
            '#['.TEXY_MARK.']+|.#u',
            $mWord,
            $chars
        );

        $chars = $chars[0];
        if (count($chars) < $this->wordLimit) return $mWord;

        $consonants = $this->consonants;
        $vowels = $this->vowels;
        $before_r = $this->before_r;
        $before_l = $this->before_l;
        $before_h = $this->before_h;
        $doubleVowels = $this->doubleVowels;

        $s = array();
        $trans = array();

        $s[] = '';
        $trans[] = -1;
        foreach ($chars as $key => $char) {
            if (ord($char{0}) < 32) continue;
            $s[] = $char;
            $trans[] = $key;
        }
        $s[] = '';
        $len = count($s) - 2;

        $positions = array();
        $a = 1; $last = 1;

        while ($a < $len) {
            $hyphen = self::DONT; // Do not hyphenate
            do {
                if ($s[$a] === '.') { $hyphen = self::HERE; break; } // ???

                if (isset($consonants[$s[$a]])) {  // souhlásky

                    if (isset($vowels[$s[$a+1]])) {
                        if (isset($vowels[$s[$a-1]])) $hyphen = self::HERE;
                        break;
                    }

                    if (($s[$a] === 's') && ($s[$a-1] === 'n') && isset($consonants[$s[$a+1]])) { $hyphen = self::AFTER; break; }

                    if (isset($consonants[$s[$a+1]]) && isset($vowels[$s[$a-1]])) {
                        if ($s[$a+1] === 'r') {
                            $hyphen = isset($before_r[$s[$a]]) ? self::HERE : self::AFTER;
                            break;
                        }

                        if ($s[$a+1] === 'l') {
                            $hyphen = isset($before_l[$s[$a]]) ? self::HERE : self::AFTER;
                            break;
                        }

                        if ($s[$a+1] === 'h') { // CH
                            $hyphen = isset($before_h[$s[$a]]) ? self::DONT : self::AFTER;
                            break;
                        }

                        $hyphen = self::AFTER;
                        break;
                    }

                    break;
                }   // konec souhlasky

                if (($s[$a] === 'u') && isset($doubleVowels[$s[$a-1]])) { $hyphen = self::AFTER; break; }
                if (isset($vowels[$s[$a]]) && isset($vowels[$s[$a-1]])) { $hyphen = self::HERE; break; }

            } while(0);

            if ($hyphen === self::DONT && ($a - $last > $this->wordLimit*0.6)) $positions[] = $last = $a-1; // Hyphenate here
            if ($hyphen === self::HERE) $positions[] = $last = $a-1; // Hyphenate here
            if ($hyphen === self::AFTER) { $positions[] = $last = $a; $a++; } // Hyphenate after

            $a++;
        } // while


        $a = end($positions);
        if (($a === $len-1) && isset($consonants[$s[$len]]))
            array_pop($positions);


        $syllables = array();
        $last = 0;
        foreach ($positions as $pos) {
            if ($pos - $last > $this->wordLimit*0.6) {
                $syllables[] = implode('', array_splice($chars, 0, $trans[$pos] - $trans[$last]));
                $last = $pos;
            }
        }
        $syllables[] = implode('', $chars);

        return implode("\xC2\xAD", $syllables); // insert shy
    }

} // TexyLongWordsModule
