<?php declare(strict_types=1);

/**
 * Test: Paragraphs.
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


test('paragraphs with modifiers', function () {
	$texy = new Texy\Texy;
	$texy->htmlOutput->linkRoot = 'xxx/';
	$texy->htmlOutput->lineWrap = 180;

	Assert::matchFile(
		__DIR__ . '/expected/paragraph.html',
		$texy->process(file_get_contents(__DIR__ . '/sources/paragraph.texy')),
	);
});


test('line break between block HTML tags', function () {
	$expected = <<<'HTML'
		<table>
			<tr>
				<td>
					<pre class="neon"><code>items: 1</code></pre>
				</td>

				<td>
					<pre class="neon"><code>items: 2</code></pre>
				</td>
			</tr>
		</table>
		HTML;

	Assert::match($expected, (new Texy\Texy)->process(
		<<<'TEXY'
			<table>
			<tr>
				<td>
			```neon
			items: 1
			```
				</td>
				<td>
			```neon
			items: 2
			```
				</td>
			</tr>
			</table>
			TEXY,
	));

	Assert::match($expected, (new Texy\Texy)->process(
		<<<'TEXY'
			<table>
			<tr>
				<td>
			/--code neon
			items: 1
			\--
				</td>
				<td>
			/--code neon
			items: 2
			\--
				</td>
			</tr>
			</table>
			TEXY,
	));
});


test('line break in a paragraph without <p>', function () {
	$texy = new Texy\Texy;
	$texy->htmlOutput->indent = false;

	Assert::same(
		'<div><pre><code>a</code></pre></div> <div>b</div>',
		$texy->process("<div>\n/--code\na\n\\--\n  </div>\n  <div>b</div>"),
	);
	Assert::same('text <div>a b</div>', $texy->process("text\n  <div>a\n  b</div>"));
	Assert::same('<strong>a b</strong> <div>c</div>', $texy->process("**a\n  b** <div>c</div>"));
	Assert::same('<span></span> <span></span>', $texy->process("<span></span>\n  <span></span>"));
	Assert::same('<!-- a --> <!-- b -->', $texy->process("<!-- a -->\n  <!-- b -->"));

	$texy->mergeLines = false;
	Assert::same('text <div>a</div>', $texy->process("text\n<div>a</div>"));
});


test('line break in a paragraph with <p> or its substitute', function () {
	$texy = new Texy\Texy;
	$texy->htmlOutput->indent = false;

	Assert::same('<p>a<br>b</p>', $texy->process("a\n  b"));
	Assert::same('<p><b>a</b><br><i>b</i></p>', $texy->process("<b>a</b>\n  <i>b</i>"));
	Assert::same('<p>a<br>b</p>', $texy->process("a\n  b</div>"));
	Assert::same('<p class="c"><span></span><br><span></span></p>', $texy->process("<span></span>\n  <span></span> .[c]"));
	Assert::same('<div><img src="a" alt=""><br><img src="b" alt=""></div>', $texy->process("<img src=a alt=''>\n  <img src=b alt=''>"));

	Texy\Configurator::safeMode($texy);
	Assert::same('<p>a<br>&lt;div&gt;b&lt;/div&gt;</p>', $texy->process("a\n  <div>b</div>"));
});


test('line break between replaced content and markup', function () {
	$texy = new Texy\Texy;
	$texy->htmlOutput->indent = false;
	$texy->htmlOutput->lineWrap = 0;

	Assert::same(
		'<div><a href="x"><img src="a" alt=""></a><br><a href="y"><img src="b" alt=""></a></div>',
		$texy->process("<a href=x><img src=a alt=''></a>\n  <a href=y><img src=b alt=''></a>"),
	);
	Assert::same(
		'<div><a href="x"><img src="a" alt=""><br><img src="b" alt=""></a></div>',
		$texy->process("<a href=x><img src=a alt=''>\n  <img src=b alt=''></a>"),
	);
	Assert::same('<div><img src="a" alt=""><br><!-- b --></div>', $texy->process("<img src=a alt=''>\n  <!-- b -->"));
	Assert::same('<div><span><input></span><br><span><input></span></div>', $texy->process("<span><input></span>\n  <span><input></span>"));

	// without a line break, or beside a block tag
	Assert::same('<a href="x"><img src="a" alt=""></a>', $texy->process("<a href=x><img src=a alt=''></a>"));
	Assert::same(' <img src="a" alt="">', $texy->process("</div>\n\t<img src=a alt=''>"));
	Assert::same(' <img src="images/a" alt="">', $texy->process("</div>\n\t[* a *]"));
});


test('line break in the paragraph opening a list item', function () {
	$texy = new Texy\Texy;
	$texy->htmlOutput->indent = false;
	$texy->htmlOutput->lineWrap = 0;

	Assert::same(
		'<ul><li>text <table> <tr> <td>a</td> <td>b</td> </tr> </table></li></ul>',
		$texy->process("- text\n  <table>\n    <tr>\n      <td>a</td>\n      <td>b</td>\n    </tr>\n  </table>"),
	);
	Assert::same(
		'<ol><li>text <table> <tr> <td>a</td> <td>b</td> </tr> </table></li></ol>',
		$texy->process("1) text\n   <table>\n     <tr>\n       <td>a</td>\n       <td>b</td>\n     </tr>\n   </table>"),
	);
	Assert::same('<ul><li>text <div>a</div> <div>b</div></li></ul>', $texy->process("- text\n  <div>a</div>\n    <div>b</div>"));
	Assert::same(
		'<ul><li><span></span> <span></span> <span></span></li></ul>',
		$texy->process("- <span></span>\n  <span></span>\n    <span></span>"),
	);
	Assert::same('<ul><li><!-- a --> <!-- b --> <!-- c --></li></ul>', $texy->process("- <!-- a -->\n  <!-- b -->\n    <!-- c -->"));

	// the item drops the wrapper, the line break stays
	Assert::same('<ul><li>a b<br>c</li></ul>', $texy->process("- a\n  b\n    c"));
	Assert::same(
		'<ul><li><img src="a" alt=""> <img src="b" alt=""><br><img src="c" alt=""></li></ul>',
		$texy->process("- <img src=a alt=''>\n  <img src=b alt=''>\n    <img src=c alt=''>"),
	);
	Assert::same(
		'<ul><li><a href="x"><img src="a" alt=""></a> <a href="y"><img src="b" alt=""></a><br><a href="z"><img src="c" alt=""></a></li></ul>',
		$texy->process("- <a href=x><img src=a alt=''></a>\n  <a href=y><img src=b alt=''></a>\n    <a href=z><img src=c alt=''></a>"),
	);
});


test('line break in the single paragraph of a definition', function () {
	$texy = new Texy\Texy;
	$texy->htmlOutput->indent = false;
	$texy->htmlOutput->lineWrap = 0;

	Assert::same(
		'<dl><dt>Term</dt><dd>text <table> <tr> <td>a</td> <td>b</td> </tr> </table></dd></dl>',
		$texy->process("Term:\n  - text\n    <table>\n      <tr>\n        <td>a</td>\n        <td>b</td>\n      </tr>\n    </table>"),
	);
	Assert::same('<dl><dt>Term</dt><dd>a b<br>c</dd></dl>', $texy->process("Term:\n  - a\n    b\n      c"));
	Assert::same(
		'<dl><dt>Term</dt><dd><span></span> <span></span> <span></span></dd></dl>',
		$texy->process("Term:\n  - <span></span>\n    <span></span>\n      <span></span>"),
	);
});


test('line break between block HTML tags in a list item', function () {
	$expected = <<<'HTML'
		<ul>
			<li>text
				<table>
					<tr>
						<td>
							<pre class="neon"><code>a</code></pre>
						</td>

						<td>
							<pre class="neon"><code>b</code></pre>
						</td>
					</tr>
				</table>
			</li>

			<li>two</li>
		</ul>
		HTML;

	Assert::match($expected, (new Texy\Texy)->process(
		<<<'TEXY'
			- text
			  <table><tr>
			    <td>
			  /--code neon
			  a
			  \--
			    </td>
			    <td>
			  /--code neon
			  b
			  \--
			    </td>
			  </tr></table>
			- two
			TEXY,
	));
});
