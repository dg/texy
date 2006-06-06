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
 * URL storage
 *
 * Analyse type of URL and convert it to valid URL or textual representation
 */
class TexyURL
{
    var //protected
        $texy;  // root Texy object association

    var
        $value,
        $flags,
        $root,
        $URL;



    /**
     * Creates a new TexyURL object
     *
     * @param object  association with root Texy object
     */
    function __construct(&$texy)
    {
        $this->texy = & $texy;
    }


    /**
     * PHP4-only constructor
     * @see http://www.dgx.cz/trine/item/how-to-emulate-php5-object-model-in-php4
     */
    function TexyURL(&$texy)
    {
        // generate references
        foreach ($this as $key => $foo) $GLOBALS['$$HIDDEN$$'][] = & $this->$key;

        // call PHP5 constructor
        call_user_func_array(array(&$this, '__construct'), array(&$texy));
    }




    /**
     * Sets URL properties
     *
     * @param string  text written in document
     * @param string  root, for relative URL's
     * @param bool    image indicator (user usage)
     * @return void
     */
    function set($value, $root = '', $isImage = FALSE)
    {
        $this->value = trim($value);
        $this->root = (string) $root;

        // will be completed on demand
        $this->URL = NULL;

        // detect URL type
        if (preg_match('#^'.TEXY_PATTERN_EMAIL.'$#i', $this->value)) // email
            $this->flags = TEXY_URL_EMAIL;
        elseif (preg_match('#^(https?://|ftp://|www\.|ftp\.|/)#i', $this->value))  // absolute URL
            $this->flags = TEXY_URL_ABSOLUTE | ($isImage ? TEXY_URL_IMAGE : 0);
        else  // relative
            $this->flags = TEXY_URL_RELATIVE | ($isImage ? TEXY_URL_IMAGE : 0);
    }



    /**
     * Indicates whether URL is absolute
     * @return bool
     */
    function isAbsolute()
    {
        return (bool) ($this->flags & TEXY_URL_ABSOLUTE);
    }



    /**
     * Indicates whether URL is email address (mailto://)
     * @return bool
     */
    function isEmail()
    {
        return (bool) ($this->flags & TEXY_URL_EMAIL);
    }



    /**
     * Indicates whether URL is marked as 'image'
     * @return bool
     */
    function isImage()
    {
        return (bool) ($this->flags & TEXY_URL_IMAGE);
    }




    function copyFrom(&$obj)
    {
        $this->value = $obj->value;
        $this->flags = $obj->flags;
        $this->URL  = $obj->URL;
        $this->root = $obj->root;
    }



    /**
     * Returns URL formatted for HTML attributes etc.
     * @return string
     */
    function asURL()
    {
        // output is cached
        if ($this->URL !== NULL)
            return $this->URL;

        if ($this->value == '')
            return $this->URL = $this->value;

        // email URL
        if ($this->flags & TEXY_URL_EMAIL) {
            // obfuscating against spam robots
            if ($this->texy->obfuscateEmail) {
                $this->URL = 'mai';
                $s = 'lto:'.$this->value;
                for ($i=0; $i<strlen($s); $i++)
                    $this->URL .= '&#' . ord($s{$i}) . ';';

            } else {
                $this->URL = 'mailto:'.$this->value;
            }
            return $this->URL;
        }

        // absolute URL
        if ($this->flags & TEXY_URL_ABSOLUTE) {
            $lower = strtolower($this->value);

            // must begins with 'http://' or 'ftp://'
            if (substr($lower, 0, 4) == 'www.') {
                return $this->URL = 'http://'.$this->value;
            } elseif (substr($lower, 0, 4) == 'ftp.') {
                return $this->URL = 'ftp://'.$this->value;
            }
            return $this->URL = $this->value;
        }

        // relative URL
        if ($this->flags & TEXY_URL_RELATIVE) {
            return $this->URL = $this->root . $this->value;
        }
    }



    /**
     * Returns textual representation of URL
     * @return string
     */
    function asTextual()
    {
        if ($this->flags & TEXY_URL_EMAIL) {
            return $this->texy->obfuscateEmail
                   ? strtr($this->value, array('@' => "&#160;(at)&#160;"))
                   : $this->value;
        }

        if ($this->flags & TEXY_URL_ABSOLUTE) {
            $URL = $this->value;
            $lower = strtolower($URL);
            if (substr($lower, 0, 4) == 'www.') $URL = 'none://'.$URL;
            elseif (substr($lower, 0, 4) == 'ftp.') $URL = 'none://'.$URL;

            $parts = @parse_url($URL);
            if ($parts === FALSE) return $this->value;

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

        return $this->value;
    }



} // TexyURL




?>