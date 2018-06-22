<?php

/**
 * This file is part of the Texy! (https://texy.info)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy;


// @param $mode
// @return $dtd

$strict = $mode === Texy::HTML4_STRICT || $mode === Texy::XHTML1_STRICT;


// attributes
$coreattrs = ['id' => 1, 'class' => 1, 'style' => 1, 'title' => 1, 'xml:id' => 1]; // extra: xml:id
$i18n = ['lang' => 1, 'dir' => 1, 'xml:lang' => 1]; // extra: xml:lang
$attrs = $coreattrs + $i18n + ['onclick' => 1, 'ondblclick' => 1, 'onmousedown' => 1, 'onmouseup' => 1,
	'onmouseover' => 1, 'onmousemove' => 1, 'onmouseout' => 1, 'onkeypress' => 1, 'onkeydown' => 1, 'onkeyup' => 1, ];
if ($mode & Texy::HTML5) {
	$attrs += ['data-*' => 1];
}
$cellalign = $attrs + ['align' => 1, 'char' => 1, 'charoff' => 1, 'valign' => 1];

// content elements

// %block;
$b = ['ins' => 1, 'del' => 1, 'p' => 1, 'h1' => 1, 'h2' => 1, 'h3' => 1, 'h4' => 1,
	'h5' => 1, 'h6' => 1, 'ul' => 1, 'ol' => 1, 'dl' => 1, 'pre' => 1, 'div' => 1, 'blockquote' => 1, 'noscript' => 1,
	'noframes' => 1, 'form' => 1, 'hr' => 1, 'table' => 1, 'address' => 1, 'fieldset' => 1, ];

if (!$strict) {
	$b += [
		'dir' => 1, 'menu' => 1, 'center' => 1, 'iframe' => 1, 'isindex' => 1, // transitional
		'marquee' => 1, // proprietary
	];
}

// %inline;
$i = ['ins' => 1, 'del' => 1, 'tt' => 1, 'i' => 1, 'b' => 1, 'big' => 1, 'small' => 1, 'em' => 1,
	'strong' => 1, 'dfn' => 1, 'code' => 1, 'samp' => 1, 'kbd' => 1, 'var' => 1, 'cite' => 1, 'abbr' => 1, 'acronym' => 1,
	'sub' => 1, 'sup' => 1, 'q' => 1, 'span' => 1, 'bdo' => 1, 'a' => 1, 'object' => 1, 'img' => 1, 'br' => 1, 'script' => 1,
	'map' => 1, 'input' => 1, 'select' => 1, 'textarea' => 1, 'label' => 1, 'button' => 1, '%DATA' => 1, ];

if (!$strict) {
	$i += [
		'u' => 1, 's' => 1, 'strike' => 1, 'font' => 1, 'applet' => 1, 'basefont' => 1, // transitional
		'embed' => 1, 'wbr' => 1, 'nobr' => 1, 'canvas' => 1, // proprietary
	];
}


$bi = $b + $i;

// build DTD
$dtd = [
'html' => [
	$strict ? $i18n + ['xmlns' => 1] : $i18n + ['version' => 1, 'xmlns' => 1], // extra: xmlns
	['head' => 1, 'body' => 1],
],
'head' => [
	$i18n + ['profile' => 1],
	['title' => 1, 'script' => 1, 'style' => 1, 'base' => 1, 'meta' => 1, 'link' => 1, 'object' => 1, 'isindex' => 1],
],
'title' => [
	[],
	['%DATA' => 1],
],
'body' => [
	$attrs + ['onload' => 1, 'onunload' => 1],
	$strict ? ['script' => 1] + $b : $bi,
],
'script' => [
	['charset' => 1, 'type' => 1, 'src' => 1, 'defer' => 1, 'event' => 1, 'for' => 1],
	['%DATA' => 1],
],
'style' => [
	$i18n + ['type' => 1, 'media' => 1, 'title' => 1],
	['%DATA' => 1],
],
'p' => [
	$strict ? $attrs : $attrs + ['align' => 1],
	$i,
],
'h1' => [
	$strict ? $attrs : $attrs + ['align' => 1],
	$i,
],
'h2' => [
	$strict ? $attrs : $attrs + ['align' => 1],
	$i,
],
'h3' => [
	$strict ? $attrs : $attrs + ['align' => 1],
	$i,
],
'h4' => [
	$strict ? $attrs : $attrs + ['align' => 1],
	$i,
],
'h5' => [
	$strict ? $attrs : $attrs + ['align' => 1],
	$i,
],
'h6' => [
	$strict ? $attrs : $attrs + ['align' => 1],
	$i,
],
'ul' => [
	$strict ? $attrs : $attrs + ['type' => 1, 'compact' => 1],
	['li' => 1],
],
'ol' => [
	$strict ? $attrs : $attrs + ['type' => 1, 'compact' => 1, 'start' => 1],
	['li' => 1],
],
'li' => [
	$strict ? $attrs : $attrs + ['type' => 1, 'value' => 1],
	$bi,
],
'dl' => [
	$strict ? $attrs : $attrs + ['compact' => 1],
	['dt' => 1, 'dd' => 1],
],
'dt' => [
	$attrs,
	$i,
],
'dd' => [
	$attrs,
	$bi,
],
'pre' => [
	$strict ? $attrs : $attrs + ['width' => 1],
	array_flip(array_diff(array_keys($i), ['img', 'object', 'applet', 'big', 'small', 'sub', 'sup', 'font', 'basefont'])),
],
'div' => [
	$strict ? $attrs : $attrs + ['align' => 1],
	$bi,
],
'blockquote' => [
	$attrs + ['cite' => 1],
	$strict ? ['script' => 1] + $b : $bi,
],
'noscript' => [
	$attrs,
	$bi,
],
'form' => [
	$attrs + ['action' => 1, 'method' => 1, 'enctype' => 1, 'accept' => 1, 'name' => 1, 'onsubmit' => 1, 'onreset' => 1, 'accept-charset' => 1],
	$strict ? ['script' => 1] + $b : $bi,
],
'table' => [
	$attrs + ['summary' => 1, 'width' => 1, 'border' => 1, 'frame' => 1, 'rules' => 1, 'cellspacing' => 1, 'cellpadding' => 1, 'datapagesize' => 1],
	['caption' => 1, 'colgroup' => 1, 'col' => 1, 'thead' => 1, 'tbody' => 1, 'tfoot' => 1, 'tr' => 1],
],
'caption' => [
	$strict ? $attrs : $attrs + ['align' => 1],
	$i,
],
'colgroup' => [
	$cellalign + ['span' => 1, 'width' => 1],
	['col' => 1],
],
'thead' => [
	$cellalign,
	['tr' => 1],
],
'tbody' => [
	$cellalign,
	['tr' => 1],
],
'tfoot' => [
	$cellalign,
	['tr' => 1],
],
'tr' => [
	$strict ? $cellalign : $cellalign + ['bgcolor' => 1],
	['td' => 1, 'th' => 1],
],
'td' => [
	$cellalign + ['abbr' => 1, 'axis' => 1, 'headers' => 1, 'scope' => 1, 'rowspan' => 1, 'colspan' => 1],
	$bi,
],
'th' => [
	$cellalign + ['abbr' => 1, 'axis' => 1, 'headers' => 1, 'scope' => 1, 'rowspan' => 1, 'colspan' => 1],
	$bi,
],
'address' => [
	$attrs,
	$strict ? $i : ['p' => 1] + $i,
],
'fieldset' => [
	$attrs,
	['legend' => 1] + $bi,
],
'legend' => [
	$strict ? $attrs + ['accesskey' => 1] : $attrs + ['accesskey' => 1, 'align' => 1],
	$i,
],
'tt' => [
	$attrs,
	$i,
],
'i' => [
	$attrs,
	$i,
],
'b' => [
	$attrs,
	$i,
],
'big' => [
	$attrs,
	$i,
],
'small' => [
	$attrs,
	$i,
],
'em' => [
	$attrs,
	$i,
],
'strong' => [
	$attrs,
	$i,
],
'dfn' => [
	$attrs,
	$i,
],
'code' => [
	$attrs,
	$i,
],
'samp' => [
	$attrs,
	$i,
],
'kbd' => [
	$attrs,
	$i,
],
'var' => [
	$attrs,
	$i,
],
'cite' => [
	$attrs,
	$i,
],
'abbr' => [
	$attrs,
	$i,
],
'acronym' => [
	$attrs,
	$i,
],
'sub' => [
	$attrs,
	$i,
],
'sup' => [
	$attrs,
	$i,
],
'q' => [
	$attrs + ['cite' => 1],
	$i,
],
'span' => [
	$attrs,
	$i,
],
'bdo' => [
	$coreattrs + ['lang' => 1, 'dir' => 1],
	$i,
],
'a' => [
	$attrs + ['charset' => 1, 'type' => 1, 'name' => 1, 'href' => 1, 'hreflang' => 1, 'rel' => 1, 'rev' => 1, 'accesskey' => 1, 'shape' => 1, 'coords' => 1, 'tabindex' => 1, 'onfocus' => 1, 'onblur' => 1],
	$i,
],
'object' => [
	$attrs + ['declare' => 1, 'classid' => 1, 'codebase' => 1, 'data' => 1, 'type' => 1, 'codetype' => 1, 'archive' => 1, 'standby' => 1, 'height' => 1, 'width' => 1, 'usemap' => 1, 'name' => 1, 'tabindex' => 1],
	['param' => 1] + $bi,
],
'map' => [
	$attrs + ['name' => 1],
	['area' => 1] + $b,
],
'select' => [
	$attrs + ['name' => 1, 'size' => 1, 'multiple' => 1, 'disabled' => 1, 'tabindex' => 1, 'onfocus' => 1, 'onblur' => 1, 'onchange' => 1],
	['option' => 1, 'optgroup' => 1],
],
'optgroup' => [
	$attrs + ['disabled' => 1, 'label' => 1],
	['option' => 1],
],
'option' => [
	$attrs + ['selected' => 1, 'disabled' => 1, 'label' => 1, 'value' => 1],
	['%DATA' => 1],
],
'textarea' => [
	$attrs + ['name' => 1, 'rows' => 1, 'cols' => 1, 'disabled' => 1, 'readonly' => 1, 'tabindex' => 1, 'accesskey' => 1, 'onfocus' => 1, 'onblur' => 1, 'onselect' => 1, 'onchange' => 1],
	['%DATA' => 1],
],
'label' => [
	$attrs + ['for' => 1, 'accesskey' => 1, 'onfocus' => 1, 'onblur' => 1],
	$i, // - label by HtmlElement::$prohibits
],
'button' => [
	$attrs + ['name' => 1, 'value' => 1, 'type' => 1, 'disabled' => 1, 'tabindex' => 1, 'accesskey' => 1, 'onfocus' => 1, 'onblur' => 1],
	$bi, // - a input select textarea label button form fieldset, by HtmlElement::$prohibits
],
'ins' => [
	$attrs + ['cite' => 1, 'datetime' => 1],
	0, // special case
],
'del' => [
	$attrs + ['cite' => 1, 'datetime' => 1],
	0, // special case
],

// empty elements
'img' => [
	$attrs + ['src' => 1, 'alt' => 1, 'longdesc' => 1, 'name' => 1, 'height' => 1, 'width' => 1, 'usemap' => 1, 'ismap' => 1],
	false,
],
'hr' => [
	$strict ? $attrs : $attrs + ['align' => 1, 'noshade' => 1, 'size' => 1, 'width' => 1],
	false,
],
'br' => [
	$strict ? $coreattrs : $coreattrs + ['clear' => 1],
	false,
],
'input' => [
	$attrs + ['type' => 1, 'name' => 1, 'value' => 1, 'checked' => 1, 'disabled' => 1, 'readonly' => 1, 'size' => 1, 'maxlength' => 1, 'src' => 1, 'alt' => 1, 'usemap' => 1, 'ismap' => 1, 'tabindex' => 1, 'accesskey' => 1, 'onfocus' => 1, 'onblur' => 1, 'onselect' => 1, 'onchange' => 1, 'accept' => 1],
	false,
],
'meta' => [
	$i18n + ['http-equiv' => 1, 'name' => 1, 'content' => 1, 'scheme' => 1],
	false,
],
'area' => [
	$attrs + ['shape' => 1, 'coords' => 1, 'href' => 1, 'nohref' => 1, 'alt' => 1, 'tabindex' => 1, 'accesskey' => 1, 'onfocus' => 1, 'onblur' => 1],
	false,
],
'base' => [
	$strict ? ['href' => 1] : ['href' => 1, 'target' => 1],
	false,
],
'col' => [
	$cellalign + ['span' => 1, 'width' => 1],
	false,
],
'link' => [
	$attrs + ['charset' => 1, 'href' => 1, 'hreflang' => 1, 'type' => 1, 'rel' => 1, 'rev' => 1, 'media' => 1],
	false,
],
'param' => [
	['id' => 1, 'name' => 1, 'value' => 1, 'valuetype' => 1, 'type' => 1],
	false,
],

// special "base content"
'%BASE' => [
	null,
	['html' => 1, 'head' => 1, 'body' => 1, 'script' => 1] + $bi,
],
];


if ($strict) {
	return $dtd;
}


// LOOSE DTD
$dtd += [
// transitional
'dir' => [
	$attrs + ['compact' => 1],
	['li' => 1],
],
'menu' => [
	$attrs + ['compact' => 1],
	['li' => 1], // it's inline-li, ignored
],
'center' => [
	$attrs,
	$bi,
],
'iframe' => [
	$coreattrs + ['longdesc' => 1, 'name' => 1, 'src' => 1, 'frameborder' => 1, 'marginwidth' => 1, 'marginheight' => 1, 'scrolling' => 1, 'align' => 1, 'height' => 1, 'width' => 1],
	$bi,
],
'noframes' => [
	$attrs,
	$bi,
],
'u' => [
	$attrs,
	$i,
],
's' => [
	$attrs,
	$i,
],
'strike' => [
	$attrs,
	$i,
],
'font' => [
	$coreattrs + $i18n + ['size' => 1, 'color' => 1, 'face' => 1],
	$i,
],
'applet' => [
	$coreattrs + ['codebase' => 1, 'archive' => 1, 'code' => 1, 'object' => 1, 'alt' => 1, 'name' => 1, 'width' => 1, 'height' => 1, 'align' => 1, 'hspace' => 1, 'vspace' => 1],
	['param' => 1] + $bi,
],
'basefont' => [
	['id' => 1, 'size' => 1, 'color' => 1, 'face' => 1],
	false,
],
'isindex' => [
	$coreattrs + $i18n + ['prompt' => 1],
	false,
],

// proprietary
'marquee' => [
	Texy::ALL,
	$bi,
],
'nobr' => [
	[],
	$i,
],
'canvas' => [
	Texy::ALL,
	$i,
],
'embed' => [
	Texy::ALL,
	false,
],
'wbr' => [
	[],
	false,
],
];

// transitional modified
$dtd['a'][0] += ['target' => 1];
$dtd['area'][0] += ['target' => 1];
$dtd['body'][0] += ['background' => 1, 'bgcolor' => 1, 'text' => 1, 'link' => 1, 'vlink' => 1, 'alink' => 1];
$dtd['form'][0] += ['target' => 1];
$dtd['img'][0] += ['align' => 1, 'border' => 1, 'hspace' => 1, 'vspace' => 1];
$dtd['input'][0] += ['align' => 1];
$dtd['link'][0] += ['target' => 1];
$dtd['object'][0] += ['align' => 1, 'border' => 1, 'hspace' => 1, 'vspace' => 1];
$dtd['script'][0] += ['language' => 1];
$dtd['table'][0] += ['align' => 1, 'bgcolor' => 1];
$dtd['td'][0] += ['nowrap' => 1, 'bgcolor' => 1, 'width' => 1, 'height' => 1];
$dtd['th'][0] += ['nowrap' => 1, 'bgcolor' => 1, 'width' => 1, 'height' => 1];

// missing: FRAMESET, FRAME, BGSOUND, XMP, ...
