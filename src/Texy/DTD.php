<?php

/**
 * This file is part of the Texy! (https://texy.info)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Texy;

$globalAttrs = array_fill_keys([
	'data-*',
	'xml:lang',


	'accesskey',
	'autocapitalize',
	'autofocus',
	'contenteditable',
	'dir',
	'draggable',
	'enterkeyhint',
	'hidden',
	'inputmode',
	'is',
	'itemid',
	'itemprop',
	'itemref',
	'itemscope',
	'itemtype',
	'lang',
	'nonce',
	'spellcheck',
	'style',
	'tabindex',
	'title',
	'translate',
	'class',
	'id',
	'slot',


	'onabort',
	'onauxclick',
	'onblur',
	'oncancel',
	'oncanplay',
	'oncanplaythrough',
	'onchange',
	'onclick',
	'onclose',
	'oncontextmenu',
	'oncopy',
	'oncuechange',
	'oncut',
	'ondblclick',
	'ondrag',
	'ondragend',
	'ondragenter',
	'ondragexit',
	'ondragleave',
	'ondragover',
	'ondragstart',
	'ondrop',
	'ondurationchange',
	'onemptied',
	'onended',
	'onerror',
	'onfocus',
	'onformdata',
	'oninput',
	'oninvalid',
	'onkeydown',
	'onkeypress',
	'onkeyup',
	'onload',
	'onloadeddata',
	'onloadedmetadata',
	'onloadstart',
	'onmousedown',
	'onmouseenter',
	'onmouseleave',
	'onmousemove',
	'onmouseout',
	'onmouseover',
	'onmouseup',
	'onpaste',
	'onpause',
	'onplay',
	'onplaying',
	'onprogress',
	'onratechange',
	'onreset',
	'onresize',
	'onscroll',
	'onsecuritypolicyviolation',
	'onseeked',
	'onseeking',
	'onselect',
	'onslotchange',
	'onstalled',
	'onsubmit',
	'onsuspend',
	'ontimeupdate',
	'ontoggle',
	'onvolumechange',
	'onwaiting',
	'onwheel',
], 1);


$metadataContent = array_fill_keys([
	'base',
	'link',
	'meta',
	'noscript',
	'script',
	'style',
	'template',
	'title',
], 1);


$flowContent = array_fill_keys([
	'a',
	'abbr',
	'address',
	'area',
	'article',
	'aside',
	'audio',
	'b',
	'bdi',
	'bdo',
	'blockquote',
	'br',
	'button',
	'canvas',
	'cite',
	'code',
	'data',
	'datalist',
	'del',
	'details',
	'dfn',
	'dialog',
	'div',
	'dl',
	'em',
	'embed',
	'fieldset',
	'figure',
	'footer',
	'form',
	'h1',
	'h2',
	'h3',
	'h4',
	'h5',
	'h6',
	'header',
	'hgroup',
	'hr',
	'i',
	'iframe',
	'img',
	'input',
	'ins',
	'kbd',
	'label',
	'link',
	'main',
	'map',
	'mark',
	'menu',
	'meta',
	'meter',
	'nav',
	'noscript',
	'object',
	'ol',
	'output',
	'p',
	'picture',
	'pre',
	'progress',
	'q',
	'ruby',
	's',
	'samp',
	'script',
	'section',
	'select',
	'slot',
	'small',
	'span',
	'strong',
	'sub',
	'sup',
	'svg',
	'table',
	'template',
	'textarea',
	'time',
	'u',
	'ul',
	'var',
	'video',
	'wbr',
	HtmlElement::INNER_TEXT,
], 1);


$phrasingContent = array_fill_keys([
	'a',
	'abbr',
	'area',
	'audio',
	'b',
	'bdi',
	'bdo',
	'br',
	'button',
	'canvas',
	'cite',
	'code',
	'data',
	'datalist',
	'del',
	'dfn',
	'em',
	'embed',
	'i',
	'iframe',
	'img',
	'input',
	'ins',
	'kbd',
	'label',
	'link',
	'map',
	'mark',
	'math',
	'meta',
	'meter',
	'noscript',
	'object',
	'output',
	'picture',
	'progress',
	'q',
	'ruby',
	's',
	'samp',
	'script',
	'select',
	'slot',
	'small',
	'span',
	'strong',
	'sub',
	'sup',
	'svg',
	'template',
	'textarea',
	'time',
	'u',
	'var',
	'video',
	'wbr',
	HtmlElement::INNER_TEXT,
], 1);


$scriptSupportingElements = array_fill_keys([
	'script',
	'template',
], 1);


return [/*
	element => [
		allowed attributes
		allowed children content model | false for empty elements
	],*/
	'a' => [
		$globalAttrs + array_fill_keys(['href', 'target', 'download', 'ping', 'rel', 'hreflang', 'type', 'referrerpolicy'], 1),
		[HtmlElement::INNER_TRANSPARENT => 1],
	],
	'abbr' => [
		$globalAttrs,
		$phrasingContent,
	],
	'address' => [
		$globalAttrs,
		$flowContent,
	],
	'area' => [
		$globalAttrs + array_fill_keys(['alt', 'coords', 'shape', 'href', 'target', 'download', 'ping', 'rel', 'referrerpolicy'], 1),
		false,
	],
	'article' => [
		$globalAttrs,
		$flowContent,
	],
	'aside' => [
		$globalAttrs,
		$flowContent,
	],
	'audio' => [
		$globalAttrs + array_fill_keys(['src', 'crossorigin', 'preload', 'autoplay', 'loop', 'muted', 'controls'], 1),
		['source' => 1, 'track' => 1, HtmlElement::INNER_TRANSPARENT => 1],
	],
	'b' => [
		$globalAttrs,
		$phrasingContent,
	],
	'base' => [
		$globalAttrs + ['href' => 1, 'target' => 1],
		false,
	],
	'bdi' => [
		$globalAttrs,
		$phrasingContent,
	],
	'bdo' => [
		$globalAttrs,
		$phrasingContent,
	],
	'blockquote' => [
		$globalAttrs + ['cite' => 1],
		$flowContent,
	],
	'body' => [
		$globalAttrs + array_fill_keys(['onafterprint', 'onbeforeprint', 'onbeforeunload', 'onhashchange', 'onlanguagechange', 'onmessage', 'onmessageerror', 'onoffline', 'ononline', 'onpagehide', 'onpageshow', 'onpopstate', 'onrejectionhandled', 'onstorage', 'onunhandledrejection', 'onunload'], 1),
		$flowContent,
	],
	'br' => [
		$globalAttrs,
		false,
	],
	'button' => [
		$globalAttrs + array_fill_keys(['disabled', 'form', 'formaction', 'formenctype', 'formmethod', 'formnovalidate', 'formtarget', 'name', 'type', 'value'], 1),
		$phrasingContent,
	],
	'canvas' => [
		$globalAttrs + ['width' => 1, 'height' => 1],
		[HtmlElement::INNER_TRANSPARENT => 1],
	],
	'caption' => [
		$globalAttrs,
		$flowContent,
	],
	'cite' => [
		$globalAttrs,
		$phrasingContent,
	],
	'code' => [
		$globalAttrs,
		$phrasingContent,
	],
	'col' => [
		$globalAttrs + ['span' => 1],
		false,
	],
	'colgroup' => [
		$globalAttrs + ['span' => 1],
		['col' => 1, 'template' => 1],
	],
	'data' => [
		$globalAttrs + ['value' => 1],
		$phrasingContent,
	],
	'datalist' => [
		$globalAttrs,
		$phrasingContent + $scriptSupportingElements + ['option' => 1],
	],
	'dd' => [
		$globalAttrs,
		$flowContent,
	],
	'del' => [
		$globalAttrs + ['cite' => 1, 'datetime' => 1],
		[HtmlElement::INNER_TRANSPARENT => 1],
	],
	'details' => [
		$globalAttrs + ['open' => 1],
		$flowContent + ['summary' => 1],
	],
	'dfn' => [
		$globalAttrs,
		$phrasingContent,
	],
	'dialog' => [
		$globalAttrs + ['open' => 1],
		$flowContent,
	],
	'div' => [
		$globalAttrs,
		$flowContent,
	],
	'dl' => [
		$globalAttrs,
		$scriptSupportingElements + ['dt' => 1, 'dd' => 1, 'div' => 1],
	],
	'dt' => [
		$globalAttrs,
		$flowContent,
	],
	'em' => [
		$globalAttrs,
		$phrasingContent,
	],
	'embed' => [
		$globalAttrs + ['src' => 1, 'type' => 1, 'width' => 1, 'height' => 1],
		false,
	],
	'fieldset' => [
		$globalAttrs + ['disabled' => 1, 'form' => 1, 'name' => 1],
		$flowContent + ['legend' => 1],
	],
	'figcaption' => [
		$globalAttrs,
		$flowContent,
	],
	'figure' => [
		$globalAttrs,
		$flowContent + ['figcaption' => 1],
	],
	'footer' => [
		$globalAttrs,
		$flowContent,
	],
	'form' => [
		$globalAttrs + array_fill_keys(['accept-charset', 'action', 'autocomplete', 'enctype', 'method', 'name', 'novalidate', 'target'], 1),
		$flowContent,
	],
	'h1' => [
		$globalAttrs,
		$phrasingContent,
	],
	'h2' => [
		$globalAttrs,
		$phrasingContent,
	],
	'h3' => [
		$globalAttrs,
		$phrasingContent,
	],
	'h4' => [
		$globalAttrs,
		$phrasingContent,
	],
	'h5' => [
		$globalAttrs,
		$phrasingContent,
	],
	'h6' => [
		$globalAttrs,
		$phrasingContent,
	],
	'head' => [
		$globalAttrs,
		$metadataContent,
	],
	'header' => [
		$globalAttrs,
		$flowContent,
	],
	'hgroup' => [
		$globalAttrs,
		$scriptSupportingElements + array_fill_keys(['h1', 'h2', 'h3', 'h4', 'h5', 'h6'], 1),
	],
	'hr' => [
		$globalAttrs,
		false,
	],
	'html' => [
		$globalAttrs + ['manifest' => 1],
		['head' => 1, 'body' => 1],
	],
	'i' => [
		$globalAttrs,
		$phrasingContent,
	],
	'iframe' => [
		$globalAttrs + array_fill_keys(['src', 'srcdoc', 'name', 'sandbox', 'allow', 'allowfullscreen', 'allowpaymentrequest', 'width', 'height', 'referrerpolicy'], 1),
		[],
	],
	'img' => [
		$globalAttrs + array_fill_keys(['alt', 'src', 'srcset', 'crossorigin', 'usemap', 'ismap', 'width', 'height', 'decoding', 'referrerpolicy'], 1),
		false,
	],
	'input' => [
		$globalAttrs + array_fill_keys(['accept', 'alt', 'autocomplete', 'checked', 'dirname', 'disabled', 'form', 'formaction', 'formenctype', 'formmethod', 'formnovalidate', 'formtarget', 'height', 'list', 'max', 'maxlength', 'min', 'minlength', 'multiple', 'name', 'pattern', 'placeholder', 'readonly', 'required', 'size', 'src', 'step', 'type', 'value', 'width'], 1),
		false,
	],
	'ins' => [
		$globalAttrs + ['cite' => 1, 'datetime' => 1],
		[HtmlElement::INNER_TRANSPARENT => 1],
	],
	'kbd' => [
		$globalAttrs,
		$phrasingContent,
	],
	'label' => [
		$globalAttrs + ['for' => 1],
		$phrasingContent,
	],
	'legend' => [
		$globalAttrs,
		$phrasingContent,
	],
	'li' => [
		$globalAttrs + ['value' => 1],
		$flowContent,
	],
	'link' => [
		$globalAttrs + array_fill_keys(['href', 'crossorigin', 'rel', 'as', 'media', 'hreflang', 'type', 'sizes', 'imagesrcset', 'imagesizes', 'referrerpolicy', 'integrity'], 1),
		false,
	],
	'main' => [
		$globalAttrs,
		$flowContent,
	],
	'map' => [
		$globalAttrs + ['name' => 1],
		[HtmlElement::INNER_TRANSPARENT => 1, 'area' => 1],
	],
	'mark' => [
		$globalAttrs,
		$phrasingContent,
	],
	'menu' => [
		$globalAttrs,
		$scriptSupportingElements + ['li' => 1],
	],
	'meta' => [
		$globalAttrs + ['name' => 1, 'http-equiv' => 1, 'content' => 1, 'charset' => 1],
		false,
	],
	'meter' => [
		$globalAttrs + array_fill_keys(['value', 'min', 'max', 'low', 'high', 'optimum'], 1),
		$phrasingContent,
	],
	'nav' => [
		$globalAttrs,
		$flowContent,
	],
	'noscript' => [
		$globalAttrs,
		[HtmlElement::INNER_TRANSPARENT => 1],
	],
	'object' => [
		$globalAttrs + array_fill_keys(['data', 'type', 'name', 'usemap', 'form', 'width', 'height'], 1),
		['param' => 1, HtmlElement::INNER_TRANSPARENT => 1],
	],
	'ol' => [
		$globalAttrs + ['reversed' => 1, 'start' => 1, 'type' => 1],
		$scriptSupportingElements + ['li' => 1],
	],
	'optgroup' => [
		$globalAttrs + ['disabled' => 1, 'label' => 1],
		$scriptSupportingElements + ['option' => 1],
	],
	'option' => [
		$globalAttrs + ['disabled' => 1, 'label' => 1, 'selected' => 1, 'value' => 1],
		[HtmlElement::INNER_TEXT => 1],
	],
	'output' => [
		$globalAttrs + ['for' => 1, 'form' => 1, 'name' => 1],
		$phrasingContent,
	],
	'p' => [
		$globalAttrs,
		$phrasingContent,
	],
	'param' => [
		$globalAttrs + ['name' => 1, 'value' => 1],
		false,
	],
	'picture' => [
		$globalAttrs,
		$scriptSupportingElements + ['source' => 1, 'img' => 1],
	],
	'pre' => [
		$globalAttrs,
		$phrasingContent,
	],
	'progress' => [
		$globalAttrs + ['value' => 1, 'max' => 1],
		$phrasingContent,
	],
	'q' => [
		$globalAttrs + ['cite' => 1],
		$phrasingContent,
	],
	'rp' => [
		$globalAttrs,
		[HtmlElement::INNER_TEXT => 1],
	],
	'rt' => [
		$globalAttrs,
		$phrasingContent,
	],
	'ruby' => [
		$globalAttrs,
		$phrasingContent + ['rt' => 1, 'rp' => 1],
	],
	's' => [
		$globalAttrs,
		$phrasingContent,
	],
	'samp' => [
		$globalAttrs,
		$phrasingContent,
	],
	'script' => [
		$globalAttrs + array_fill_keys(['src', 'type', 'async', 'defer', 'crossorigin', 'integrity', 'referrerpolicy'], 1),
		[HtmlElement::INNER_TEXT => 1],
	],
	'section' => [
		$globalAttrs,
		$flowContent,
	],
	'select' => [
		$globalAttrs + array_fill_keys(['autocomplete', 'disabled', 'form', 'multiple', 'name', 'required', 'size'], 1),
		$scriptSupportingElements + ['option' => 1, 'optgroup' => 1],
	],
	'slot' => [
		$globalAttrs + ['name' => 1],
		[HtmlElement::INNER_TRANSPARENT => 1],
	],
	'small' => [
		$globalAttrs,
		$phrasingContent,
	],
	'source' => [
		$globalAttrs + array_fill_keys(['src', 'type', 'srcset', 'sizes', 'media'], 1),
		false,
	],
	'span' => [
		$globalAttrs,
		$phrasingContent,
	],
	'strong' => [
		$globalAttrs,
		$phrasingContent,
	],
	'style' => [
		$globalAttrs + ['media' => 1],
		[HtmlElement::INNER_TEXT => 1],
	],
	'sub' => [
		$globalAttrs,
		$phrasingContent,
	],
	'summary' => [
		$globalAttrs,
		$phrasingContent,
	],
	'sup' => [
		$globalAttrs,
		$phrasingContent,
	],
	'table' => [
		$globalAttrs,
		$scriptSupportingElements + array_fill_keys(['caption', 'colgroup', 'thead', 'tbody', 'tfoot', 'tr'], 1),
	],
	'tbody' => [
		$globalAttrs,
		$scriptSupportingElements + ['tr' => 1],
	],
	'td' => [
		$globalAttrs + ['colspan' => 1, 'rowspan' => 1, 'headers' => 1],
		$flowContent,
	],
	'template' => [
		$globalAttrs,
		[],
	],
	'textarea' => [
		$globalAttrs + array_fill_keys(['cols', 'dirname', 'disabled', 'form', 'maxlength', 'minlength', 'name', 'placeholder', 'readonly', 'required', 'rows', 'wrap'], 1),
		[HtmlElement::INNER_TEXT => 1],
	],
	'tfoot' => [
		$globalAttrs,
		$scriptSupportingElements + ['tr' => 1],
	],
	'th' => [
		$globalAttrs + array_fill_keys(['colspan', 'rowspan', 'headers', 'scope', 'abbr'], 1),
		$flowContent,
	],
	'thead' => [
		$globalAttrs,
		$scriptSupportingElements + ['tr' => 1],
	],
	'time' => [
		$globalAttrs + ['datetime' => 1],
		$phrasingContent,
	],
	'title' => [
		$globalAttrs,
		[HtmlElement::INNER_TEXT => 1],
	],
	'tr' => [
		$globalAttrs,
		$scriptSupportingElements + ['th' => 1, 'td' => 1],
	],
	'track' => [
		$globalAttrs + array_fill_keys(['default', 'kind', 'label', 'src', 'srclang'], 1),
		false,
	],
	'u' => [
		$globalAttrs,
		$phrasingContent,
	],
	'ul' => [
		$globalAttrs,
		$scriptSupportingElements + ['li' => 1],
	],
	'var' => [
		$globalAttrs,
		$phrasingContent,
	],
	'video' => [
		$globalAttrs + array_fill_keys(['src', 'crossorigin', 'poster', 'preload', 'autoplay', 'playsinline', 'loop', 'muted', 'controls', 'width', 'height'], 1),
		['source' => 1, 'track' => 1, HtmlElement::INNER_TRANSPARENT => 1],
	],
	'wbr' => [
		$globalAttrs,
		false,
	],
];
