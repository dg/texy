<?php declare(strict_types=1);

/**
 * Test: Output\Html\Element
 */

use Tester\Assert;
use Texy\Output\Html\Element;

require __DIR__ . '/../bootstrap.php';


test('addClass() appends to the classes the element has', function () {
	Assert::same(['a', 'b'], new Element('pre')->addClass('a')->addClass('b')->attrs['class']);
	Assert::same(['x', 'y'], new Element('p', ['class' => 'x'])->addClass('y')->attrs['class']);
});


test('a void element follows its current name', function () {
	$el = new Element('br');
	Assert::true($el->isVoid());
	Assert::same('', $el->endTag());

	$el->name = 'span';
	Assert::false($el->isVoid());
	Assert::same('</span>', $el->endTag());
});
