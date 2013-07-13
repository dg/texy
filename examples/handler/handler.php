<?php

/**
 * TEXY! USER HANDLER DEMO
 */


// include Texy!
require_once dirname(__FILE__) . '/../../src/texy.php';


$texy = new Texy();
$texy->addHandler('emoticon', array('myHandler', 'emoticon'));
$texy->addHandler('image', array('myHandler', 'image'));
$texy->addHandler('linkReference', array('myHandler', 'linkReference'));
$texy->addHandler('linkEmail', array('myHandler', 'linkEmail'));
$texy->addHandler('linkURL', array('myHandler', 'linkURL'));
$texy->addHandler('phrase', array('myHandler', 'phrase'));
$texy->addHandler('newReference', array('myHandler', 'newReference'));
$texy->addHandler('htmlComment', array('myHandler', 'htmlComment'));
$texy->addHandler('htmlTag', array('myHandler', 'htmlTag'));
$texy->addHandler('script', array('myHandler', 'script'));
//$texy->addHandler('paragraph', array('myHandler', 'paragraph'));
$texy->addHandler('figure', array('myHandler', 'figure'));
$texy->addHandler('heading', array('myHandler', 'heading'));
$texy->addHandler('horizline', array('myHandler', 'horizline'));
$texy->addHandler('block', array('myHandler', 'block'));
$texy->addHandler('afterList', array('myHandler', 'afterList'));
$texy->addHandler('afterDefinitionList', array('myHandler', 'afterDefinitionList'));
$texy->addHandler('afterTable', array('myHandler', 'afterTable'));
$texy->addHandler('afterBlockquote', array('myHandler', 'afterBlockquote'));
$texy->addHandler('beforeParse', array('myHandler', 'beforeParse'));
$texy->addHandler('afterParse', array('myHandler', 'afterParse'));


class myHandler
{


	/** Line parsing */

	/**
	 * @param  TexyHandlerInvocation  handler invocation
	 * @param  string
	 * @param  string
	 * @return TexyHtml|string|FALSE
	 */
	function emoticon($invocation, $emoticon, $rawEmoticon)
	{
		return $invocation->proceed();
	}


	/**
	 * @param  TexyHandlerInvocation  handler invocation
	 * @param  TexyImage
	 * @param  TexyLink|NULL
	 * @return TexyHtml|string|FALSE
	 */
	function image($invocation, $image, $link)
	{
		return $invocation->proceed();
	}


	/**
	 * @param  TexyHandlerInvocation  handler invocation
	 * @param  TexyLink
	 * @param  string
	 * @return TexyHtml|string|FALSE
	 */
	function linkReference($invocation, $link, $content)
	{
		return $invocation->proceed();
	}


	/**
	 * @param  TexyHandlerInvocation  handler invocation
	 * @param  TexyLink
	 * @return TexyHtml|string|FALSE
	 */
	function linkEmail($invocation, $link)
	{
		return $invocation->proceed();
	}


	/**
	 * @param  TexyHandlerInvocation  handler invocation
	 * @param  TexyLink
	 * @return TexyHtml|string|FALSE
	 */
	function linkURL($invocation, $link)
	{
		return $invocation->proceed();
	}


	/**
	 * @param  TexyHandlerInvocation  handler invocation
	 * @param  string
	 * @param  string
	 * @param  TexyModifier
	 * @param  TexyLink|NULL
	 * @return TexyHtml|string|FALSE
	 */
	function phrase($invocation, $phrase, $content, $modifier, $link)
	{
		return $invocation->proceed();
	}


	/**
	 * @param  TexyHandlerInvocation  handler invocation
	 * @param  string
	 * @return TexyHtml|string|FALSE
	 */
	function newReference($invocation, $name)
	{
		return $invocation->proceed();
	}


	/**
	 * @param  TexyHandlerInvocation  handler invocation
	 * @param  string
	 * @return TexyHtml|string|FALSE
	 */
	function htmlComment($invocation, $content)
	{
		return $invocation->proceed();
	}


	/**
	 * @param  TexyHandlerInvocation  handler invocation
	 * @param  TexyHtml
	 * @param  bool
	 * @param  bool
	 * @return TexyHtml|string|FALSE
	 */
	function htmlTag($invocation, $el, $isStart, $forceEmpty=NULL)
	{
		return $invocation->proceed();
	}


	/**
	 * @param  TexyHandlerInvocation  handler invocation
	 * @param  string  command
	 * @param  array   arguments
	 * @param  string  arguments in raw format
	 * @return TexyHtml|string|FALSE
	 */
	function script($invocation, $cmd, $args, $raw)
	{
		return $invocation->proceed();
	}


	/** Blocks */

	/**
	 * @param  TexyHandlerInvocation  handler invocation
	 * @param  string
	 * @param  TexyModifier|NULL
	 * @return TexyHtml|string|FALSE
	 */
/*
	function paragraph($invocation, $content, $modifier)
	{
		return $invocation->proceed();
	}
*/


	/**
	 * @param  TexyHandlerInvocation  handler invocation
	 * @param  TexyImage
	 * @param  TexyLink|NULL
	 * @param  string
	 * @param  TexyModifier
	 * @return TexyHtml|string|FALSE
	 */
	function figure($invocation, $image, $link, $content, $modifier)
	{
		return $invocation->proceed();
	}


	/**
	 * @param  TexyHandlerInvocation  handler invocation
	 * @param  int
	 * @param  string
	 * @param  TexyModifier
	 * @param  bool
	 * @return TexyHtml|string|FALSE
	 */
	function heading($invocation, $level, $content, $modifier, $isSurrounded)
	{
		return $invocation->proceed();
	}


	/**
	 * @param  TexyHandlerInvocation  handler invocation
	 * @param  string
	 * @param  TexyModifier
	 * @return TexyHtml|string|FALSE
	 */
	function horizline($invocation, $type, $modifier)
	{
		return $invocation->proceed();
	}


	/**
	 * @param  TexyHandlerInvocation  handler invocation
	 * @param  string
	 * @param  string
	 * @param  string
	 * @param  TexyModifier
	 * @return TexyHtml|string
	 */
	function block($invocation, $blocktype, $content, $param, $modifier)
	{
		return $invocation->proceed();
	}


	/**
	 * @param  TexyBlockParser
	 * @param  TexyHtml
	 * @param  TexyModifier
	 * @return void
	 */
	function afterList($parser, $element, $modifier)
	{
	}


	/**
	 * @param  TexyBlockParser
	 * @param  TexyHtml
	 * @param  TexyModifier
	 * @return void
	 */
	function afterDefinitionList($parser, $element, $modifier)
	{
	}


	/**
	 * @param  TexyBlockParser
	 * @param  TexyHtml
	 * @param  TexyModifier
	 * @return void
	 */
	function afterTable($parser, $element, $modifier)
	{
	}


	/**
	 * @param  TexyBlockParser
	 * @param  TexyHtml
	 * @param  TexyModifier
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
	 * @param  TexyHtml
	 * @param  bool
	 * @return void
	 */
	function afterParse($texy, $DOM, $isSingleLine)
	{
	}
}
