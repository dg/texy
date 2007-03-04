<?php

/**
 * TEXY! USER HANDLER DEMO
 * -----------------------
 *
 * This source file is subject to the GNU GPL license.
 *
 * @link       http://texy.info/
 * @author     David Grudl aka -dgx- <dave@dgx.cz>
 * @copyright  Copyright (c) 2004-2007 David Grudl
 * @license    GNU GENERAL PUBLIC LICENSE v2
 */



// include Texy!
require_once dirname(__FILE__).'/../../texy/texy.php';



class myHandler
{

    public function afterParse($texy, $DOM, $isSingleLine)
    {}

    public function documentPre($texy, $content, $param, $modifier, $doctype)
    {}

    public function documentCode($texy, $content, $param, $modifier, $doctype)
    {}

    public function documentHtml($texy, $content, $param, $modifier, $doctype)
    {}

    public function documentText($texy, $content, $param, $modifier, $doctype)
    {}

    public function documentTexySource($texy, $content, $param, $modifier, $doctype)
    {}

    public function documentComment($texy, $content, $param, $modifier, $doctype)
    {}

    public function emoticon($texy, $emoticon, $match, $file)
    {}

    public function figure($texy, $image, $link, $content, $modifier)
    {}

    public function heading($texy, $level, $content, $modifier, $isSurrounded)
    {}

    public function htmlComment($texy, $match)
    {}

    public function htmlTag($texy, $el, $isOpening)
    {}

    public function image($texy, $image, $link)
    {}

    public function newReference($texy, $name)
    {}

    public function linkReference($texy, $link, $content)
    {}

    public function linkEmail($texy, $link, $content)
    {}

    public function linkURL($texy, $link, $content)
    {}

    public function phrase($texy, $phrase, $content, $modifier, $link)
    {}

}




$texy = new Texy();
$texy->handler = new myHandler;
