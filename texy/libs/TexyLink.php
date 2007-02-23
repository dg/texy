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
class TexyLink
{
    protected $value;
    protected $type;
    protected $source;
    protected $root;
    protected $url;

    // types
    const ABSOLUTE = 1;
    const RELATIVE = 2;
    const EMAIL = 3;

    // sources
    const DIRECT = 1;
    const REFERENCE = 2;
    const IMAGE = 3;



    /**
     * Creates a new TexyLink object
     *
     * @param string  text written in document
     * @param string  root, for relative URL's
     * @param int     source (DIRECT, IMAGE, REFERENCE)
     * @return void
     */
    public function __construct($value, $root, $source, $label=NULL)
    {
        $value = trim($value);

        $this->source = $source;
        $this->value = $value;

        // detect URL type
        if ($source !== self::IMAGE && preg_match('#^'.TEXY_EMAIL.'$#i', $value)) { // email
            $this->type = self::EMAIL;
            // obfuscating against spam robots
            if (Texy::$obfuscateEmail) {
                $s = 'mai';
                $s2 = 'lto:' . $value;
                for ($i=0; $i<strlen($s2); $i++)
                    $s .= '&#' . ord($s2{$i}) . ';';

                $this->url = $s;
            } else {
                $this->url = 'mailto:' . $value;
            }

        } elseif (preg_match('#^(https?://|ftp://|www\.|ftp\.|/)#i', $value)) {
            // absolute URL
            $this->type = self::ABSOLUTE;
            $value = str_replace('%s', urlencode(Texy::wash($label)), $value);

            // must begins with 'http://' or 'ftp://'
            $lower = strtolower($value);
            if (substr($lower, 0, 4) === 'www.') {
                $this->url = 'http://' . $value;
            } elseif (substr($lower, 0, 4) === 'ftp.') {
                $this->url = 'ftp://' . $value;
            } else {
                $this->url = $value;
            }

        } else {
            // relative
            $this->type = self::RELATIVE;
            $value = str_replace('%s', urlencode(Texy::wash($label)), $value);
            if ($root == NULL) $this->url = $value;
            else $this->url = rtrim($root, '/\\') . '/' . $value;
        }
    }



    static public function adjustURL($value, $root, $isImage)
    {
        $link = new self($value, $root, $isImage);
        return $link->url;
    }



    /**
     * Indicates whether URL is absolute
     * @return bool
     */
    public function isAbsolute()
    {
        return $this->type === self::ABSOLUTE;
    }



    /**
     * Indicates whether URL is email address (mailto://)
     * @return bool
     */
    public function isEmail()
    {
        return $this->type === self::EMAIL;
    }



    /**
     * Indicates whether URL is marked as 'image'
     * @return bool
     */
    public function isImage()
    {
        return $this->source === self::IMAGE;
    }



    /**
     * Returns URL formatted for HTML attributes etc.
     * @return string
     */
    public function asURL()
    {
        return $this->url;
    }



    /**
     * Returns textual representation of URL
     * @return string
     */
    public function asTextual()
    {
        if ($this->type === self::EMAIL) {
            return Texy::$obfuscateEmail
                   ? strtr($this->value, array('@' => "&#160;(at)&#160;"))
                   : $this->value;
        }

        if ($this->type === self::ABSOLUTE) {
            $URL = $this->value;
            $lower = strtolower($URL);
            if (substr($lower, 0, 4) === 'www.') $URL = 'none://'.$URL;
            elseif (substr($lower, 0, 4) === 'ftp.') $URL = 'none://'.$URL;

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

} // TexyLink
