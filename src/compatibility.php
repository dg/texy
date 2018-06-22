<?php

/**
 * For Texy 1 backward compatibility.
 */
define('TEXY_ALL', Texy::ALL);
define('TEXY_NONE', Texy::NONE);
define('TEXY_CONTENT_MARKUP', Texy::CONTENT_MARKUP);
define('TEXY_CONTENT_REPLACED', Texy::CONTENT_REPLACED);
define('TEXY_CONTENT_TEXTUAL', Texy::CONTENT_TEXTUAL);
define('TEXY_CONTENT_BLOCK', Texy::CONTENT_BLOCK);
define('TEXY_VERSION', Texy::VERSION);
define('TEXY_HEADING_DYNAMIC', 1);
define('TEXY_HEADING_FIXED', 2);

/**
 * For Texy 2.2 compatibility
 */
define('TEXY_CHAR', TexyPatterns::CHAR);
define('TEXY_MARK', TexyPatterns::MARK);
define('TEXY_MODIFIER', TexyPatterns::MODIFIER);
define('TEXY_MODIFIER_H', TexyPatterns::MODIFIER_H);
define('TEXY_MODIFIER_HV', TexyPatterns::MODIFIER_HV);
define('TEXY_IMAGE', TexyPatterns::IMAGE);
define('TEXY_LINK_URL', TexyPatterns::LINK_URL);
define('TEXY_LINK', '(?::(' . TEXY_LINK_URL . '))');
define('TEXY_LINK_N', '(?::(' . TEXY_LINK_URL . '|:))');
define('TEXY_EMAIL', '[' . TEXY_CHAR . '][0-9.+_' . TEXY_CHAR . '-]{0,63}@[0-9.+_' . TEXY_CHAR . '\x{ad}-]{1,252}\.[' . TEXY_CHAR . '\x{ad}]{2,19}');
define('TEXY_URLSCHEME', '[a-z][a-z0-9+.-]{0,20}:');
