<?php declare(strict_types=1);

/**
 * Test: Parsing\OffsetMap leads offsets in joined lines back to the source.
 */

use Tester\Assert;
use Texy\Parsing\OffsetMap;

require __DIR__ . '/../bootstrap.php';


test('an offset belongs to the last line starting at or before it', function () {
	$map = new OffsetMap([0 => 10, 4 => 20, 9 => 35]);

	Assert::same(10, $map->toAbsolute(0));
	Assert::same(13, $map->toAbsolute(3));
	Assert::same(20, $map->toAbsolute(4));
	Assert::same(24, $map->toAbsolute(8));
	Assert::same(35, $map->toAbsolute(9));
	Assert::same(135, $map->toAbsolute(109));
});


test('an offset before the first line is the start of that line', function () {
	Assert::same(10, new OffsetMap([2 => 10])->toAbsolute(0));
});


test('an empty map leaves offsets as they are', function () {
	Assert::same(7, (new OffsetMap)->toAbsolute(7));
});


test('a line added to the text is found, also after a lookup', function () {
	$map = new OffsetMap([0 => 10]);
	Assert::same(15, $map->toAbsolute(5));

	$map->add(4, 20);
	Assert::same(13, $map->toAbsolute(3));
	Assert::same(21, $map->toAbsolute(5));
});
