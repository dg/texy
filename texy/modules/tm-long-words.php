<?php

/**
 * ------------------------------------------
 *   LONG WORDS WRAP - TEXY! DEFAULT MODULE
 * ------------------------------------------
 *
 * Version 1 Release Candidate
 *
 * Copyright (c) 2004-2005, David Grudl <dave@dgx.cz>
 * Web: http://www.texy.info/
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 */

// security - include texy.php, not this file
if (!defined('TEXY')) die();






/**
 * LONG WORDS WRAP MODULE CLASS
 */
class TexyLongWordsModule extends TexyModule {
  var $wordLimit = 20;
  var $shy;
  var $nbsp;

  function linePostProcess(&$text)
  {
    $this->shy = TEXY_UTF8 ? "\xC2\xAD" : "\xAD";
    $this->nbsp = TEXY_UTF8 ? "\xC2\xA0" : "\xA0";
    $text = strtr($text, array('&shy;'  => $this->shy, '&nbsp;' => $this->nbsp));
    $text = preg_replace_callback('#[^\ \n\t\-\xAD'.TEXY_HASH_SPACES.']{'.$this->wordLimit.',}#'.TEXY_PATTERN_UTF, array(&$this, '_replace'), $text);
    $text = strtr($text, array($this->shy => '&shy;', $this->nbsp => '&nbsp;'));
  }


  /***
   * Callback function: rozdìlí dlouhá slova na slabiky - EXPERIMENTÁLNÍ
   * (c) David Grudl
   * @return string
   */
  function _replace(&$matches)
  {
    list($mWord) = $matches;
    //    [0] => lllloooonnnnggggwwwoorrdddd

    $chars = array();
    preg_match_all('#&\\#?[a-z0-9]+;|['.TEXY_HASH.']+|.#'.TEXY_PATTERN_UTF, $mWord, $chars);
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

    $text = implode($this->shy, $syllables);
    $text = strtr($text, array($this->shy.$this->nbsp => ' ', $this->nbsp.$this->shy => ' '));

    return $text;
  }




} // TexyLongWordsModule


?>