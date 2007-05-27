<?php

/**
 * This file is part of the Texy! formatter (http://texy.info/)
 *
 * Copyright (c) 2004-2007 David Grudl aka -dgx- <dave@dgx.cz>
 *
 * @version  $Revision$ $Date$
 * @package  Texy
 */


// security - include texy.php, not this file
if (!class_exists('Texy', FALSE)) die();



/**
 * UTF-8 helper
 */
class TexyUtf
{
    /** @var array */
    static private $xlat;

    /** @var array */
    static private $xlatCache;



    /**
     * Converts from source encoding to UTF-8
     */
    static public function toUtf($s, $encoding)
    {
        return iconv($encoding, 'UTF-8', $s);
    }


    /**
     * Converts from UTF-8 to dest encoding
     */
    static public function utfTo($s, $encoding)
    {
        return iconv('utf-8', $encoding.'//TRANSLIT', $s);
    }


    /**
     * StrToLower in UTF-8
     */
    static public function strtolower($s)
    {
        if (function_exists('mb_strtolower'))
            return mb_strtolower($s, 'UTF-8');

        return strtr(
            iconv('UTF-8', 'WINDOWS-1250//IGNORE', $s),
            "ABCDEFGHIJKLMNOPQRSTUVWXYZ\x8a\x8c\x8d\x8e\x8f\xa3\xa5\xaa\xaf\xbc\xc0\xc1\xc2\xc3\xc4\xc5\xc6\xc7\xc8\xc9\xca\xcb\xcc\xcd\xce\xcf\xd0\xd1\xd2\xd3\xd4\xd5\xd6\xd8\xd9\xda\xdb\xdc\xdd\xde",
            "abcdefghijklmnopqrstuvwxyz\x9a\x9c\x9d\x9e\x9f\xb3\xb9\xba\xbf\xbe\xe0\xe1\xe2\xe3\xe4\xe5\xe6\xe7\xe8\xe9\xea\xeb\xec\xed\xee\xef\xf0\xf1\xf2\xf3\xf4\xf5\xf6\xf8\xf9\xfa\xfb\xfc\xfd\xfe"
        );
    }


    /**
     * Converts UTF-8 to ASCII
     * iconv('UTF-8', 'ASCII//TRANSLIT', ...) has problem with glibc!
     */
    static public function utf2ascii($s)
    {
        $s = iconv('UTF-8', 'WINDOWS-1250//IGNORE', $s);
        return strtr($s, "\xa5\xa3\xbc\x8c\xa7\x8a\xaa\x8d\x8f\x8e\xaf\xb9\xb3\xbe\x9c\x9a\xba\x9d\x9f\x9e\xbf\xc0\xc1\xc2\xc3\xc4\xc5\xc6\xc7\xc8\xc9\xca\xcb\xcc\xcd\xce\xcf\xd0\xd1\xd2\xd3\xd4\xd5\xd6\xd7\xd8\xd9\xda\xdb\xdc\xdd\xde\xdf\xe0\xe1\xe2\xe3\xe4\xe5\xe6\xe7\xe8\xe9\xea\xeb\xec\xed\xee\xef\xf0\xf1\xf2\xf3\xf4\xf5\xf6\xf8\xf9\xfa\xfb\xfc\xfd\xfe",
            "ALLSSSSTZZZallssstzzzRAAAALCCCEEEEIIDDNNOOOOxRUUUUYTsraaaalccceeeeiiddnnooooruuuuyt");
    }



    /**
     * Converts UTF-8 to dest encoding + html entities
     */
    static public function utf2html($s, $encoding)
    {
        // convert from UTF-8
        if (strcasecmp($encoding, 'utf-8') === 0) return $s;

        // prepare UTF-8 -> charset table
        self::$xlat = & self::$xlatCache[strtolower($encoding)];
        if (!self::$xlat) {
            for ($i=128; $i<256; $i++) {
                $ch = iconv($encoding, 'UTF-8//IGNORE', chr($i));
                if ($ch) self::$xlat[$ch] = chr($i);
            }
        }

        // convert
        return preg_replace_callback('#[\x80-\x{FFFF}]#u', array(__CLASS__, 'cb'), $s);
    }



    /**
     * Callback; converts UTF-8 to HTML entity OR character in dest encoding
     */
    static private function cb($m)
    {
        $m = $m[0];
        if (isset(self::$xlat[$m])) return self::$xlat[$m];

        $ch1 = ord($m[0]);
        $ch2 = ord($m[1]);
        if (($ch2 >> 6) !== 2) return '';

        if (($ch1 & 0xE0) === 0xC0)
            return '&#' . ((($ch1 & 0x1F) << 6) + ($ch2 & 0x3F)) . ';';

        if (($ch1 & 0xF0) === 0xE0) {
            $ch3 = ord($m[2]);
            if (($ch3 >> 6) !== 2) return '';
            return '&#' . ((($ch1 & 0xF) << 12) + (($ch2 & 0x3F) << 06) + (($ch3 & 0x3F))) . ';';
        }

        return '';
    }

}
