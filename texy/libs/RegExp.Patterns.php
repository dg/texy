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



// Regular expression patterns

// Unicode character classes
define('TEXY_CHAR',        'A-Za-z\x{c0}-\x{02af}\x{0370}-\x{1eff}');

// marking meta-characters
// any mark:               \x14-\x1F
// CONTENT_NONE mark:      \x17-\x1F
// CONTENT_INLINE mark:    \x16-\x1F
// CONTENT_TEXTUAL mark:   \x17-\x1F
// CONTENT_BLOCK:          \x18-\x1F
define('TEXY_MARK',        "\x14-\x1F");


// modifier .(title)[class]{style}
define('TEXY_MODIFIER',    '(?:\ *(?<= |^)\.(\([^\)]+\)|\[[^\]]+\]|\{[^\}]+\})(\([^\)]+\)|\[[^\]]+\]|\{[^\}]+\})??(\([^\)]+\)|\[[^\]]+\]|\{[^\}]+\})??)');

// modifier .(title)[class]{style}<>
define('TEXY_MODIFIER_H',  '(?:\ *(?<= |^)\.(\([^\)]+\)|\[[^\]]+\]|\{[^\}]+\}|(?:<>|>|=|<))(\([^\)]+\)|\[[^\]]+\]|\{[^\}]+\}|(?:<>|>|=|<))??(\([^\)]+\)|\[[^\]]+\]|\{[^\}]+\}|(?:<>|>|=|<))??(\([^\)]+\)|\[[^\]]+\]|\{[^\}]+\}|(?:<>|>|=|<))??)');

// modifier .(title)[class]{style}<>^
define('TEXY_MODIFIER_HV', '(?:\ *(?<= |^)\.(\([^\)]+\)|\[[^\]]+\]|\{[^\}]+\}|(?:<>|>|=|<)|(?:\^|\-|\_))(\([^\)]+\)|\[[^\]]+\]|\{[^\}]+\}|(?:<>|>|=|<)|(?:\^|\-|\_))??(\([^\)]+\)|\[[^\]]+\]|\{[^\}]+\}|(?:<>|>|=|<)|(?:\^|\-|\_))??(\([^\)]+\)|\[[^\]]+\]|\{[^\}]+\}|(?:<>|>|=|<)|(?:\^|\-|\_))??(\([^\)]+\)|\[[^\]]+\]|\{[^\}]+\}|(?:<>|>|=|<)|(?:\^|\-|\_))??)');


// images   [* urls .(title)[class]{style} >]
define('TEXY_IMAGE',       '\[\*([^\n'.TEXY_MARK.']+)'.TEXY_MODIFIER.'? *(\*|>|<)\]');


// links
define('TEXY_LINK_REF',    '\[[^\[\]\*\n'.TEXY_MARK.']+\]');  // reference  [refName]
define('TEXY_LINK_URL',    '(?:\[[^\]\n]+\]|(?!\[)[^\s'.TEXY_MARK.']*?[^:);,.!?\s'.TEXY_MARK.'])'); // any url (nekonèí :).,!?
define('TEXY_LINK',        '(?::('.TEXY_LINK_URL.'))');       // any link
define('TEXY_LINK_N',      '(?::('.TEXY_LINK_URL.'|:))');     // any link (also unstated)
define('TEXY_EMAIL',       '[a-z0-9.+_-]+@[a-z0-9.+_-]+\.[a-z]{2,}');    // name@exaple.com
