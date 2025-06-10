<?php declare(strict_types=1);

/**
 * Test: parse - Tables.
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


test('simple table', function () {
	$texy = new Texy\Texy;

	Assert::match(
		<<<'HTML'
			<table>
				<tbody>
					<tr>
						<td>A</td>

						<td>B</td>
					</tr>

					<tr>
						<td>C</td>

						<td>D</td>
					</tr>
				</tbody>
			</table>
			HTML,
		$texy->toHtml(<<<'XX'
			| A | B |
			| C | D |
			XX),
	);
});


test('a | divides cells unless it is escaped or stands inside brackets', function () {
	$cells = function (string $row): array {
		$table = new Texy\Texy()->parse($row)->findFirst(Texy\Nodes\TableNode::class);
		return array_map(fn($cell) => $cell->getPlainText(), $table->rows[0]->cells);
	};

	Assert::same(['a | b', 'c'], $cells('| a \| b | c |'));
	Assert::same(['[a|b|c]', 'd'], $cells('| [a|b|c] | d |'));
	Assert::same(['a [ b', 'c', 'd'], $cells('| a [ b | c | d |'));

	Assert::contains('<td title="a | b">x</td>', new Texy\Texy()->toHtml('| .(a \| b) x | y |'));
	Assert::contains('<td title="a | b">x</td>', new Texy\Texy()->toHtml('| x .(a \| b) | y |'));
});
