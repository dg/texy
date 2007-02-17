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
 * URL storage
 *
 * Analyse type of URL and convert it to valid URL or textual representation
 */
class TexyUrl
{
    protected $texy;  // root Texy object association

    public $value;
    protected $flags;
    protected $root;
    protected $URL;

    const ABSOLUTE = 1;
    const RELATIVE = 2;
    const EMAIL = 4;
    const IMAGE = 8;




    /**
     * Creates a new TexyUrl object
     *
     * @param object  association with root Texy object
     */
    public function __construct($texy)
    {
        $this->texy =  $texy;
    }





    /**
     * Sets URL properties
     *
     * @param string  text written in document
     * @param string  root, for relative URL's
     * @param bool    image indicator (user usage)
     * @return void
     */
    public function set($value, $root = '', $isImage = FALSE)
    {
        $this->value = trim($value);
        if ($root <> NULL) $root = rtrim($root, '/\\') . '/';
        $this->root = $root;


        // will be completed on demand
        $this->URL = NULL;

        // detect URL type
        if (preg_match('#^'.TEXY_PATTERN_EMAIL.'$#i', $this->value)) // email
            $this->flags = self::EMAIL;
        elseif (preg_match('#^(https?://|ftp://|www\.|ftp\.|/)#i', $this->value))  // absolute URL
            $this->flags = self::ABSOLUTE | ($isImage ? self::IMAGE : 0);
        else  // relative
            $this->flags = self::RELATIVE | ($isImage ? self::IMAGE : 0);
    }



    /**
     * Indicates whether URL is absolute
     * @return bool
     */
    public function isAbsolute()
    {
        return (bool) ($this->flags & self::ABSOLUTE);
    }



    /**
     * Indicates whether URL is email address (mailto://)
     * @return bool
     */
    public function isEmail()
    {
        return (bool) ($this->flags & self::EMAIL);
    }



    /**
     * Indicates whether URL is marked as 'image'
     * @return bool
     */
    public function isImage()
    {
        return (bool) ($this->flags & self::IMAGE);
    }




    public function copyFrom($obj)
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
    public function asURL()
    {
        // output is cached
        if ($this->URL !== NULL)
            return $this->URL;

        if ($this->value == '')
            return $this->URL = $this->value;

        // email URL
        if ($this->flags & self::EMAIL) {
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
        if ($this->flags & self::ABSOLUTE) {
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
        if ($this->flags & self::RELATIVE) {
            return $this->URL = $this->root . $this->value;
        }
        return NULL;
    }



    /**
     * Returns textual representation of URL
     * @return string
     */
    public function asTextual()
    {
        if ($this->flags & self::EMAIL) {
            return $this->texy->obfuscateEmail
                   ? strtr($this->value, array('@' => "&#160;(at)&#160;"))
                   : $this->value;
        }

        if ($this->flags & self::ABSOLUTE) {
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



    /**
     * Undefined property usage prevention
     */
    function __get($nm) { throw new Exception("Undefined property '" . get_class($this) . "::$$nm'"); }
    function __set($nm, $val) { $this->__get($nm); }
    private function __unset($nm) { $this->__get($nm); }
    private function __isset($nm) { $this->__get($nm); }
} // TexyUrl
