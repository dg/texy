<?php

/**
 * Texy! universal text -> html converter
 * --------------------------------------
 *
 * This source file is subject to the GNU GPL license.
 *
 * @author     David Grudl aka -dgx- <dave@dgx.cz>
 * @link       http://texy.info/
 * @copyright  Copyright (c) 2004-2006 David Grudl
 * @license    GNU GENERAL PUBLIC LICENSE v2
 * @package    Texy
 * @category   Text
 * @version    $Revision$ $Date$
 */

// security - include texy.php, not this file
if (!defined('TEXY')) die();






/**
 * LONG WORDS WRAP MODULE CLASS
 */
class TexyLongWordsModule extends TexyModule
{
    public $wordLimit = 20;
    public $shy       = '&#173;'; // eq &shy;
    public $nbsp      = '&#160;'; // eq &nbsp;




    public function linePostProcess($text)
    {
        if (!$this->allowed) return $text;

        $charShy  = $this->texy->utf ? "\xC2\xAD" : "\xAD";
        $charNbsp = $this->texy->utf ? "\xC2\xA0" : "\xA0";

        // convert nbsp + shy to single chars
        $text = strtr($text, array(
                            '&shy;'  => $charShy,
                            '&#173;' => $charShy,  // and &#xAD;, &#xad;, ...

                            '&nbsp;' => $charNbsp,
                            '&#160;' => $charNbsp, // and &#xA0;
                     ));

        $text = preg_replace_callback(
                            $this->texy->translatePattern('#[^\ \n\t\-\xAD'.TEXY_HASH_SPACES.']{'.$this->wordLimit.',}#<UTF>'),
                            array($this, '_replace'),
                            $text);

        // revert nbsp + shy back to user defined entities
        $text = strtr($text, array(
                            $charShy  => $this->shy,
                            $charNbsp => $this->nbsp,
                     ));
        return $text;
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
                         $this->texy->translatePattern('#&\\#?[a-z0-9]+;|[:HASH:]+|.#<UTF>'),
                         $mWord,
                         $chars
        );

        $chars = $chars[0];
        if (count($chars) < $this->wordLimit) return $mWord;

                // little trick - isset($array[$item]) is much faster than in_array($item, $array)
        $consonants = array_flip(array(
                        'b','c','d','f','g','h','j','k','l','m','n','p','q','r','s','t','v','w','x','z',
                        'B','C','D','F','G','H','J','K','L','M','N','P','Q','R','S','T','V','W','X','Z',
                        "\xe8","\xef","\xf2","\xf8","\x9a","\x9d","\x9e",  //czech windows-1250
                        "\xc8","\xcf","\xd2","\xd8","\x8a","\x8d","\x8e",
                        "\xc4\x8d","\xc4\x8f","\xc5\x88","\xc5\x99","\xc5\xa1","\xc5\xa5","\xc5\xbe", //czech utf-8
                        "\xc4\x8c","\xc4\x8e","\xc5\x87","\xc5\x98","\xc5\xa0","\xc5\xa4","\xc5\xbd"));

        $vowels     = array_flip(array(
                        'a','e','i','o','u','y',
                        'A','E','I','O','U','Y',
                        "\xe1","\xe9","\xec","\xed","\xf3","\xfa","\xf9","\xfd",  //czech windows-1250
                        "\xc1","\xc9","\xcc","\xcd","\xd3","\xda","\xd9","\xdd",
                        "\xc3\xa1","\xc3\xa9","\xc4\x9b","\xc3\xad","\xc3\xb3","\xc3\xba","\xc5\xaf","\xc3\xbd", //czech utf-8
                        "\xc3\x81","\xc3\x89","\xc4\x9a","\xc3\x8d","\xc3\x93","\xc3\x9a","\xc5\xae","\xc3\x9d"));

        $before_r   = array_flip(array(
                        'b','B','c','C','d','D','f','F','g','G','k','K','p','P','r','R','t','T','v','V',
                        "\xe8","\xc8","\xef","\xcf","\xf8","\xd8","\x9d","\x8d",  //czech windows-1250
                        "\xc4\x8d","\xc4\x8c","\xc4\x8f","\xc4\x8e","\xc5\x99","\xc5\x98","\xc5\xa5","\xc5\xa4"));

        $before_l   = array_flip(array(
                        'b','B','c','C','d','D','f','F','g','G','k','K','l','L','p','P','t','T','v','V',
                        "\xe8","\xc8","\xef","\xcf","\x9d","\x8d",  //czech windows-1250
                        "\xc4\x8d","\xc4\x8c","\xc4\x8f","\xc4\x8e","\xc5\xa5","\xc5\xa4")); //czech utf-8

        $before_h   = array_flip(array('c', 'C', 's', 'S'));

        $doubleVowels = array_flip(array('a', 'A','o', 'O'));

        // consts
        $DONT = 0;   // don't hyphenate
        $HERE = 1;   // hyphenate here
        $AFTER = 2;  // hyphenate after

        $s = array();
        $trans = array();

        $s[] = '';
        $trans[] = -1;
        $hashCounter = $len = $counter = 0;
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
            $hyphen = $DONT; // Do not hyphenate
            do {
                if ($s[$a] == '.') { $hyphen = $HERE; break; }   // ???

                if (isset($consonants[$s[$a]])) {  // souhlásky

                    if (isset($vowels[$s[$a+1]])) {
                        if (isset($vowels[$s[$a-1]])) $hyphen = $HERE;
                        break;
                    }

                    if (($s[$a] == 's') && ($s[$a-1] == 'n') && isset($consonants[$s[$a+1]])) { $hyphen = $AFTER; break; }

                    if (isset($consonants[$s[$a+1]]) && isset($vowels[$s[$a-1]])) {
                        if ($s[$a+1] == 'r') {
                            $hyphen = isset($before_r[$s[$a]]) ? $HERE : $AFTER;
                            break;
                        }

                        if ($s[$a+1] == 'l') {
                            $hyphen = isset($before_l[$s[$a]]) ? $HERE : $AFTER;
                            break;
                        }

                        if ($s[$a+1] == 'h') { // CH
                            $hyphen = isset($before_h[$s[$a]]) ? $DONT : $AFTER;
                            break;
                        }

                        $hyphen = $AFTER;
                        break;
                    }

                    break;
                }   // konec souhlasky


                if (($s[$a] == 'u') && isset($doubleVowels[$s[$a-1]])) { $hyphen = $AFTER; break; }
                if (in_array($s[$a], $vowels) && isset($vowels[$s[$a-1]])) { $hyphen = $HERE; break; }

            } while(0);

            if ($hyphen == $DONT && ($a - $last > $this->wordLimit*0.6)) $positions[] = $last = $a-1; // Hyphenate here
            if ($hyphen == $HERE) $positions[] = $last = $a-1; // Hyphenate here
            if ($hyphen == $AFTER) { $positions[] = $last = $a; $a++; } // Hyphenate after

            $a++;
        } // while


        $a = end($positions);
        if (($a == $len-1) && isset($consonants[$s[$len]]))
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


        $charShy  = $this->texy->utf ? "\xC2\xAD" : "\xAD";
        $charNbsp = $this->texy->utf ? "\xC2\xA0" : "\xA0";

        $text = implode($charShy, $syllables);
        $text = strtr($text, array($charShy.$charNbsp => ' ', $charNbsp.$charShy => ' '));

        return $text;
    }




} // TexyLongWordsModule
