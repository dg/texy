<?php

/**
 * This file is part of the Texy! formatter (http://texy.info/)
 *
 * @author     David Grudl
 * @copyright  Copyright (c) 2004-2007 David Grudl aka -dgx- (http://www.dgx.cz)
 * @license    GNU GENERAL PUBLIC LICENSE version 2 or 3
 * @version    $Revision$ $Date$
 * @category   Text
 * @package    Texy
 */



$GLOBALS['TexyConfigurator::$safeTags'] = array(
    'a'         => array('href', 'title'),
    'acronym'   => array('title'),
    'b'         => array(),
    'br'        => array(),
    'cite'      => array(),
    'code'      => array(),
    'em'        => array(),
    'i'         => array(),
    'strong'    => array(),
    'sub'       => array(),
    'sup'       => array(),
    'q'         => array(),
    'small'     => array(),
); /* class static property */


/**
 * Texy basic configurators
 *
 * <code>
 *     $texy = new Texy();
 *     TexyConfigurator::safeMode($texy);
 * </code>
 */
class TexyConfigurator
{
    /**
     * Configure Texy! for web comments and other usages, where input text may insert attacker
     *
     * @param Texy  object to configure
     * @return void
     */
    function safeMode(/*Texy*/ $texy) /* static */
    {
        $texy->allowedClasses = TEXY_NONE;                 // no class or ID are allowed
        $texy->allowedStyles  = TEXY_NONE;                 // style modifiers are disabled
        $texy->allowedTags = $GLOBALS['TexyConfigurator::$safeTags'];               // only some "safe" HTML tags and attributes are allowed
        $texy->urlSchemeFilters['a'] = '#https?:|ftp:|mailto:#A';
        $texy->urlSchemeFilters['i'] = '#https?:#A';
        $texy->urlSchemeFilters['c'] = '#http:#A';
        $texy->allowed['image'] = FALSE;                    // disable images
        $texy->allowed['link/definition'] = FALSE;          // disable [ref]: URL  reference definitions
        $texy->allowed['html/comment'] = FALSE;             // disable HTML comments
        $texy->linkModule->forceNoFollow = TRUE;            // force rel="nofollow"
    }



    /**
     * Switch Texy! configuration to the (default) trust mode
     *
     * @param Texy  object to configure
     * @return void
     * @deprecated
     */
    function trustMode(/*Texy*/ $texy) /* static */
    {
        trigger_error('trustMode() is deprecated. This is default configuration.', E_USER_WARNING);
    }



    /**
     * Disable all links
     *
     * @param Texy  object to configure
     * @return void
     */
    function disableLinks($texy)
    {
        $texy->allowed['link/reference'] = FALSE;
        $texy->allowed['link/email'] = FALSE;
        $texy->allowed['link/url'] = FALSE;
        $texy->allowed['link/definition'] = FALSE;
        $texy->phraseModule->linksAllowed = FALSE;

        if (is_array($texy->allowedTags))
            unset($texy->allowedTags['a']);
        // TODO: else...
    }



    /**
     * Disable all images
     *
     * @param Texy  object to configure
     * @return void
     */
    function disableImages($texy)
    {
        $texy->allowed['image'] = FALSE;
        $texy->allowed['figure'] = FALSE;
        $texy->allowed['image/definition'] = FALSE;

        if (is_array($texy->allowedTags))
            unset($texy->allowedTags['img'], $texy->allowedTags['object'], $texy->allowedTags['embed'], $texy->allowedTags['applet']);
        // TODO: else...
    }

}
