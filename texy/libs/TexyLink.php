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
    protected $texy;  // root Texy object association

    public $value;
    protected $source;
    protected $type;
    protected $root;
    protected $URL;

    // types
    const ABSOLUTE = 1;
    const RELATIVE = 2;
    const EMAIL = 3;

    // sources
    const DIRECT = 1;  // -> absolute, relative, email
    const REFERENCE = 2; // -> resolve: absolute, relative, email
    const IMAGE = 3; // -> (resolve): absolute, relative
    const LINKED_IMAGE = 4; // -> (resolve): absolute, relative




    /**
     * Creates a new TexyLink object
     *
     * @param object  association with root Texy object
     * @param string  text written in document
     * @param int     direct/reference/image/linked_image indicator
     */
    public function __construct($texy, $value, $source)
    {
        $this->texy = $texy;
        $this->source = $source;
        $this->value = trim($value);

        if ($source === self::DIRECT) $root = $texy->linkModule->root;
        elseif ($source === self::REFERENCE) $root = $texy->linkModule->root;
        elseif ($source === self::IMAGE) $root = $texy->imageModule->root;
        elseif ($source === self::LINKED_IMAGE) $root = $texy->imageModule->linkedRoot;
        else $root = NULL;

        if ($root <> NULL) $this->root = rtrim($root, '/\\') . '/';
        /*
        if ($source === self::REFERENCE) {
            $elRef = $texy->linkModule->getReference($value);
            if ($elRef) {
                $modifier->copyFrom($elRef->modifier);
                $loc = $elRef->URL . $elRef->query;
                $loc = str_replace('%s', urlencode(Texy::wash($text)), $loc);
            }
        }

        if ($source === self::IMAGE || $source === self::LINKED_IMAGE) {
            $elImage = new TexyImageElement($this->texy);
            $elImage->setImagesRaw(substr($loc, 2, -2));
            $elImage->requireLinkImage();
            $link->copyFrom($elImage->linkImage);
        }
        */

        // detect URL type
        if (preg_match('#^'.TEXY_EMAIL.'$#i', $this->value)) // email
            $this->type = self::EMAIL;
        elseif (preg_match('#^(https?://|ftp://|www\.|ftp\.|/)#i', $this->value))  // absolute URL
            $this->type = self::ABSOLUTE;
        else  // relative
            $this->type = self::RELATIVE;
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
        return $this->source === self::IMAGE || $this->source === self::LINKED_IMAGE;
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
        if ($this->type === self::EMAIL) {
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
        if ($this->type === self::ABSOLUTE) {
            $lower = strtolower($this->value);

            // must begins with 'http://' or 'ftp://'
            if (substr($lower, 0, 4) === 'www.') {
                return $this->URL = 'http://'.$this->value;
            } elseif (substr($lower, 0, 4) === 'ftp.') {
                return $this->URL = 'ftp://'.$this->value;
            }
            return $this->URL = $this->value;
        }

        // relative URL
        if ($this->type === self::RELATIVE) {
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
        if ($this->type === self::EMAIL) {
            return $this->texy->obfuscateEmail
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
