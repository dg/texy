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
 * @version    $Revision$ $Date$
 */

// security - include texy.php, not this file
if (!defined('TEXY')) die();






/**
 * LONG WORDS WRAP MODULE CLASS
 */
class TexyLongWordsModule extends TexyModule
{
    var $wordLimit = 20;
    var $shy       = '&#173;'; // eq &shy;
    var $nbsp      = '&#160;'; // eq &nbsp;




    function linePostProcess(&$text)
    {
        if (!$this->allowed) return;

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
                            array(&$this, '_replace'),
                            $text);

        // revert nbsp + shy back to user defined entities
        $text = strtr($text, array(
                            $charShy  => $this->shy,
                            $charNbsp => $this->nbsp,
                     ));
    }


    /**
     * Callback function: rozdìlí dlouhá slova na slabiky - EXPERIMENTÁLNÍ
     * (c) David Grudl
     * @return string
     */
    function _replace($matches)
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
                        'b','c','d','f','g','h','j','k','l','m','n','p','q','r','s','t','v','w','x','y','z',
                        'B','C','D','F','G','H','J','K','L','M','N','P','Q','R','S','T','V','W','X','Y','Z',
                        'è','ï','ò','ø','š','','ý','ž',            //czech windows-1250
                        'È','Ï','Ò','Ø','Š','','Ý','Ž',
                        'Ä','Ä','Åˆ','Å™','Å¡','Å¥','Ã½','Å¾',    //czech utf-8
                        'ÄŒ','ÄŽ','Å‡','Å˜','Å ','Å¤','Ã','Å½'));
        $vowels     = array_flip(array(
                        'a','e','i','o','y','u',
                        'A','E','I','O','Y','U',
                        'á','é','ì','í','ó','ý','ú','ù',            //czech windows-1250
                        'Á','É','Ì','Í','Ó','Ý','Ú','Ù',
                        'Ã¡','Ã©','Ä›','Ã­','Ã³','Ã½','Ãº','Å¯',    //czech utf-8
                        'Ã','Ã‰','Äš','Ã','Ã“','Ã','Ãš','Å®'));

        $before_r   = array_flip(array(
                        'b','B','c','C','d','D','f','F','g','G','k','K','p','P','r','R','t','T','v','V',
                        'è','È','ï','Ï','ø','Ø','','',                  //czech windows-1250
                        'Ä','ÄŒ','Ä','ÄŽ','Åt','Å_','Å¥','Å¤',          //czech utf-8
                        ));

        $before_l   = array_flip(array(
                        'b','B','c','C','d','D','f','F','g','G','k','K','l','L','p','P','t','T','v','V',
                        'è','È','ï','Ï','','',                          //czech windows-1250
                        'Ä','ÄŒ','Ä','ÄŽ','Å¥','Å¤',                    //czech utf-8
                        ));

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


?>