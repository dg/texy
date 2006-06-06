<?php

/**
 * Texy! universal text -> html converter
 * --------------------------------------
 *
 * This source file is subject to the GNU GPL license.
 *
 * @link       http://www.texy.info/
 * @author     David Grudl aka -dgx- <dave@dgx.cz>
 * @copyright  Copyright (c) 2004-2006 David Grudl
 * @license    GNU GENERAL PUBLIC LICENSE
 * @package    Texy
 * @category   Text
 * @version    1.1 for PHP4 & PHP5 $Date$ $Revision$
 */

// security - include texy.php, not this file
if (!defined('TEXY')) die();






/**
 * TEXY! STRUCTURE FOR STORING URL
 * -------------------------------
 *
 * Analyse input text, detect type of url ($type)
 * and convert to final URL ($this->URL)
 *
 */
class TexyURL {
    var $texy;
    var $URL;
    var $type;
    var $text;
    var $root = '';    // root of relative link


    function TexyURL(&$texy)
    {
        $this->texy = & $texy;
    }


    function set($text, $type = null)
    {
        if ($type !== null)
            $this->type = $type;

        $this->text = trim($text);
        $this->analyse();
        $this->toURL();
    }


    function clear()
    {
        $this->text = '';
        $this->type = 0;
        $this->URL = '';
    }


    function copyFrom(&$obj)
    {
        $this->text = $obj->text;
        $this->type = $obj->type;
        $this->URL  = $obj->URL;
        $this->root = $obj->root;
    }


    function analyse()
    {
        if (preg_match('#^'.TEXY_PATTERN_EMAIL.'$#i', $this->text)) $this->type |= TEXY_URL_EMAIL;
        elseif (preg_match('#(https?://|ftp://|www\.|ftp\.|/)#Ai', $this->text)) $this->type |= TEXY_URL_ABSOLUTE;
        else $this->type |= TEXY_URL_RELATIVE;
    }



    function toURL()
    {
        if ($this->text == null)
            return $this->URL = '';

        if ($this->type & TEXY_URL_EMAIL) {
            if ($this->texy->obfuscateEmail) {
                $this->URL = 'mai';
                $s = 'lto:'.$this->text;
                for ($i=0; $i<strlen($s); $i++)  $this->URL .= '&#' . ord($s{$i}) . ';';

            } else {
                $this->URL = 'mailto:'.$this->text;
            }
            return $this->URL;
        }

        if ($this->type & TEXY_URL_ABSOLUTE) {
            $textX = strtolower($this->text);

            if (substr($textX, 0, 4) == 'www.') {
                return $this->URL = 'http://'.$this->text;
            } elseif (substr($textX, 0, 4) == 'ftp.') {
                return $this->URL = 'ftp://'.$this->text;
            }
            return $this->URL = $this->text;
        }

        if ($this->type & TEXY_URL_RELATIVE) {
            return $this->URL = $this->root . $this->text;
        }
    }



    function toString()
    {
        if ($this->type & TEXY_URL_EMAIL) {
            return $this->texy->obfuscateEmail ? strtr($this->text, array('@' => '&#160;(at)&#160;')) : $this->text;
        }

        if ($this->type & TEXY_URL_ABSOLUTE) {
            $url = $this->text;
            $urlX = strtolower($url);
            if (substr($urlX, 0, 4) == 'www.') $url = 'none://'.$url;
            elseif (substr($urlX, 0, 4) == 'ftp.') $url = 'none://'.$url;

            $parts = @parse_url($url);
            if ($parts === false) return $this->text;

            $res = '';
            if (isset($parts['scheme']) && $parts['scheme'] !== 'none')
                $res .= $parts['scheme'] . '://';

            if (isset($parts['host']))
                $res .= $parts['host'];

            if (isset($parts['path']))
                $res .=  (strlen($parts['path']) > 16 ? ('/...' . preg_replace('#^.*(.{0,12})$#U', '$1', $parts['path'])) : $parts['path']);

            if (isset($parts['query'])) {
                $res .= strlen($parts['query']) > 4 ? '?...' : ('?'.$parts['query']);
            } elseif (isset($parts['fragment'])) {
                $res .= strlen($parts['fragment']) > 4 ? '#...' : ('#'.$parts['fragment']);
            }
            return $res;
        }

        return $this->text;
    }


} // TexyURL






?>