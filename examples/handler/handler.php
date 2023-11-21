<?php

/**
 * TEXY! USER HANDLER DEMO
 */

declare(strict_types=1);



$texy = new Texy;
$handler = new myHandler;
$texy->addHandler('emoticon', $handler->emoticon(...));
$texy->addHandler('image', $handler->image(...));
$texy->addHandler('linkReference', $handler->linkReference(...));
$texy->addHandler('linkEmail', $handler->linkEmail(...));
$texy->addHandler('linkURL', $handler->linkURL(...));
$texy->addHandler('phrase', $handler->phrase(...));
$texy->addHandler('newReference', $handler->newReference(...));
$texy->addHandler('htmlComment', $handler->htmlComment(...));
$texy->addHandler('htmlTag', $handler->htmlTag(...));
$texy->addHandler('script', $handler->script(...));
//$texy->addHandler('paragraph', array($handler, 'paragraph'));
$texy->addHandler('figure', $handler->figure(...));
$texy->addHandler('heading', $handler->heading(...));
$texy->addHandler('horizline', $handler->horizline(...));
$texy->addHandler('block', $handler->block(...));
$texy->addHandler('afterList', $handler->afterList(...));
$texy->addHandler('afterDefinitionList', $handler->afterDefinitionList(...));
$texy->addHandler('afterTable', $handler->afterTable(...));
$texy->addHandler('afterBlockquote', $handler->afterBlockquote(...));
$texy->addHandler('beforeParse', $handler->beforeParse(...));
$texy->addHandler('afterParse', $handler->afterParse(...));


class myHandler
{
	/** Line parsing */


	/** @return Texy\HtmlElement|string|null */
	public function emoticon(Texy\HandlerInvocation $invocation, $emoticon, $rawEmoticon)
	{
		return $invocation->proceed();
	}


	/** @return Texy\HtmlElement|string|null */
	public function image(Texy\HandlerInvocation $invocation, Texy\Image $image, Texy\Link $link = null)
	{
		return $invocation->proceed();
	}


	public function linkReference(
		Texy\HandlerInvocation $invocation,
		Texy\Link $link,
		string $content
	): Texy\HtmlElement|string|null
	{
		return $invocation->proceed();
	}


	/** @return Texy\HtmlElement|string|null */
	public function linkEmail(Texy\HandlerInvocation $invocation, Texy\Link $link)
	{
		return $invocation->proceed();
	}


	/** @return Texy\HtmlElement|string|null */
	public function linkURL(Texy\HandlerInvocation $invocation, Texy\Link $link)
	{
		return $invocation->proceed();
	}


	/** @return Texy\HtmlElement|string|null */
	public function phrase(
		Texy\HandlerInvocation $invocation,
		$phrase,
		$content,
		Texy\Modifier $modifier,
		Texy\Link $link = null,
	) {
		return $invocation->proceed();
	}


	/** @return Texy\HtmlElement|string|null */
	public function newReference(Texy\HandlerInvocation $invocation, $name)
	{
		return $invocation->proceed();
	}


	/** @return Texy\HtmlElement|string|null */
	public function htmlComment(Texy\HandlerInvocation $invocation, $content)
	{
		return $invocation->proceed();
	}


	/** @return Texy\HtmlElement|string|null */
	public function htmlTag(Texy\HandlerInvocation $invocation, Texy\HtmlElement $el, $isStart, $forceEmpty = null)
	{
		return $invocation->proceed();
	}


	/** @return Texy\HtmlElement|string|null */
	public function script(Texy\HandlerInvocation $invocation, $command, array $args, $rawArgs)
	{
		return $invocation->proceed();
	}


	/** Blocks */

	/** @return Texy\HtmlElement|string|null */
	/*
	function paragraph(Texy\HandlerInvocation $invocation, $content, Texy\Modifier $modifier)
	{
		return $invocation->proceed();
	}
	*/


	/** @return Texy\HtmlElement|string|null */
	public function figure(
		Texy\HandlerInvocation $invocation,
		Texy\Image $image,
		Texy\Link $link = null,
		$content,
		Texy\Modifier $modifier,
	) {
		return $invocation->proceed();
	}


	/** @return Texy\HtmlElement|string|null */
	public function heading(
		Texy\HandlerInvocation $invocation,
		$level,
		$content,
		Texy\Modifier $modifier,
		$isSurrounded,
	) {
		return $invocation->proceed();
	}


	/** @return Texy\HtmlElement|string|null */
	public function horizline(Texy\HandlerInvocation $invocation, $type, Texy\Modifier $modifier)
	{
		return $invocation->proceed();
	}


	/** @return Texy\HtmlElement|string */
	public function block(Texy\HandlerInvocation $invocation, $blocktype, $content, $param, Texy\Modifier $modifier)
	{
		return $invocation->proceed();
	}


	public function afterList(Texy\BlockParser $parser, Texy\HtmlElement $element, Texy\Modifier $modifier): void
	{
	}


	public function afterDefinitionList(
		Texy\BlockParser $parser,
		Texy\HtmlElement $element,
		Texy\Modifier $modifier,
	): void
	{
	}


	public function afterTable(Texy\BlockParser $parser, Texy\HtmlElement $element, Texy\Modifier $modifier): void
	{
	}


	public function afterBlockquote(Texy\BlockParser $parser, Texy\HtmlElement $element, Texy\Modifier $modifier): void
	{
	}


	/** Special */


	public function beforeParse(Texy\Texy $texy, &$text, $isSingleLine): void
	{
	}


	public function afterParse(Texy\Texy $texy, Texy\HtmlElement $DOM, $isSingleLine): void
	{
	}
}
