<?php

/**
 * Test: parse - Tables.
 */

declare(strict_types=1);

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
		$texy->process(<<<'XX'
			| A | B |
			| C | D |
			XX),
	);
});
