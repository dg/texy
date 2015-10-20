<?php

/**
 * TEXY! USER HANDLER DEMO
 */


// include Texy!
require_once __DIR__ . '/../../src/texy.php';


$texy = new Texy();
$texy->addHandler('emoticon', ['myHandler', 'emoticon']);
$texy->addHandler('image', ['myHandler', 'image']);
$texy->addHandler('linkReference', ['myHandler', 'linkReference']);
$texy->addHandler('linkEmail', ['myHandler', 'linkEmail']);
$texy->addHandler('linkURL', ['myHandler', 'linkURL']);
$texy->addHandler('phrase', ['myHandler', 'phrase']);
$texy->addHandler('newReference', ['myHandler', 'newReference']);
$texy->addHandler('htmlComment', ['myHandler', 'htmlComment']);
$texy->addHandler('htmlTag', ['myHandler', 'htmlTag']);
$texy->addHandler('script', ['myHandler', 'script']);
//$texy->addHandler('paragraph', array('myHandler', 'paragraph'));
$texy->addHandler('figure', ['myHandler', 'figure']);
$texy->addHandler('heading', ['myHandler', 'heading']);
$texy->addHandler('horizline', ['myHandler', 'horizline']);
$texy->addHandler('block', ['myHandler', 'block']);
$texy->addHandler('afterList', ['myHandler', 'afterList']);
$texy->addHandler('afterDefinitionList', ['myHandler', 'afterDefinitionList']);
$texy->addHandler('afterTable', ['myHandler', 'afterTable']);
$texy->addHandler('afterBlockquote', ['myHandler', 'afterBlockquote']);
$texy->addHandler('beforeParse', ['myHandler', 'beforeParse']);
$texy->addHandler('afterParse', ['myHandler', 'afterParse']);


class myHandler
{


	/** Line parsing */

	/**
	 * @param  Texy\HandlerInvocation  handler invocation
	 * @param  string
	 * @param  string
	 * @return Texy\HtmlElement|string|FALSE
	 */
	function emoticon($invocation, $emoticon, $rawEmoticon)
	{
		return $invocation->proceed();
	}


	/**
	 * @param  Texy\HandlerInvocation  handler invocation
	 * @param  Texy\Image
	 * @param  Texy\Link|NULL
	 * @return Texy\HtmlElement|string|FALSE
	 */
	function image($invocation, $image, $link)
	{
		return $invocation->proceed();
	}


	/**
	 * @param  Texy\HandlerInvocation  handler invocation
	 * @param  Texy\Link
	 * @param  string
	 * @return Texy\HtmlElement|string|FALSE
	 */
	function linkReference($invocation, $link, $content)
	{
		return $invocation->proceed();
	}


	/**
	 * @param  Texy\HandlerInvocation  handler invocation
	 * @param  Texy\Link
	 * @return Texy\HtmlElement|string|FALSE
	 */
	function linkEmail($invocation, $link)
	{
		return $invocation->proceed();
	}


	/**
	 * @param  Texy\HandlerInvocation  handler invocation
	 * @param  Texy\Link
	 * @return Texy\HtmlElement|string|FALSE
	 */
	function linkURL($invocation, $link)
	{
		return $invocation->proceed();
	}


	/**
	 * @param  Texy\HandlerInvocation  handler invocation
	 * @param  string
	 * @param  string
	 * @param  Texy\Modifier
	 * @param  Texy\Link|NULL
	 * @return Texy\HtmlElement|string|FALSE
	 */
	function phrase($invocation, $phrase, $content, $modifier, $link)
	{
		return $invocation->proceed();
	}


	/**
	 * @param  Texy\HandlerInvocation  handler invocation
	 * @param  string
	 * @return Texy\HtmlElement|string|FALSE
	 */
	function newReference($invocation, $name)
	{
		return $invocation->proceed();
	}


	/**
	 * @param  Texy\HandlerInvocation  handler invocation
	 * @param  string
	 * @return Texy\HtmlElement|string|FALSE
	 */
	function htmlComment($invocation, $content)
	{
		return $invocation->proceed();
	}


	/**
	 * @param  Texy\HandlerInvocation  handler invocation
	 * @param  Texy\HtmlElement
	 * @param  bool
	 * @param  bool
	 * @return Texy\HtmlElement|string|FALSE
	 */
	function htmlTag($invocation, $el, $isStart, $forceEmpty = NULL)
	{
		return $invocation->proceed();
	}


	/**
	 * @param  Texy\HandlerInvocation  handler invocation
	 * @param  string  command
	 * @param  array   arguments
	 * @param  string  arguments in raw format
	 * @return Texy\HtmlElement|string|FALSE
	 */
	function script($invocation, $cmd, $args, $raw)
	{
		return $invocation->proceed();
	}


	/** Blocks */

	/**
	 * @param  Texy\HandlerInvocation  handler invocation
	 * @param  string
	 * @param  Texy\Modifier|NULL
	 * @return Texy\HtmlElement|string|FALSE
	 */
/*
	function paragraph($invocation, $content, $modifier)
	{
		return $invocation->proceed();
	}
*/


	/**
	 * @param  Texy\HandlerInvocation  handler invocation
	 * @param  Texy\Image
	 * @param  Texy\Link|NULL
	 * @param  string
	 * @param  Texy\Modifier
	 * @return Texy\HtmlElement|string|FALSE
	 */
	function figure($invocation, $image, $link, $content, $modifier)
	{
		return $invocation->proceed();
	}


	/**
	 * @param  Texy\HandlerInvocation  handler invocation
	 * @param  int
	 * @param  string
	 * @param  Texy\Modifier
	 * @param  bool
	 * @return Texy\HtmlElement|string|FALSE
	 */
	function heading($invocation, $level, $content, $modifier, $isSurrounded)
	{
		return $invocation->proceed();
	}


	/**
	 * @param  Texy\HandlerInvocation  handler invocation
	 * @param  string
	 * @param  Texy\Modifier
	 * @return Texy\HtmlElement|string|FALSE
	 */
	function horizline($invocation, $type, $modifier)
	{
		return $invocation->proceed();
	}


	/**
	 * @param  Texy\HandlerInvocation  handler invocation
	 * @param  string
	 * @param  string
	 * @param  string
	 * @param  Texy\Modifier
	 * @return Texy\HtmlElement|string
	 */
	function block($invocation, $blocktype, $content, $param, $modifier)
	{
		return $invocation->proceed();
	}


	/**
	 * @param  Texy\BlockParser
	 * @param  Texy\HtmlElement
	 * @param  Texy\Modifier
	 * @return void
	 */
	function afterList($parser, $element, $modifier)
	{
	}


	/**
	 * @param  Texy\BlockParser
	 * @param  Texy\HtmlElement
	 * @param  Texy\Modifier
	 * @return void
	 */
	function afterDefinitionList($parser, $element, $modifier)
	{
	}


	/**
	 * @param  Texy\BlockParser
	 * @param  Texy\HtmlElement
	 * @param  Texy\Modifier
	 * @return void
	 */
	function afterTable($parser, $element, $modifier)
	{
	}


	/**
	 * @param  Texy\BlockParser
	 * @param  Texy\HtmlElement
	 * @param  Texy\Modifier
	 * @return void
	 */
	function afterBlockquote($parser, $element, $modifier)
	{
	}


	/** Special */

	/**
	 * @param  Texy
	 * @param  string
	 * @param  bool
	 * @return void
	 */
	function beforeParse($texy, & $text, $isSingleLine)
	{
	}


	/**
	 * @param  Texy
	 * @param  Texy\HtmlElement
	 * @param  bool
	 * @return void
	 */
	function afterParse($texy, $DOM, $isSingleLine)
	{
	}
}
