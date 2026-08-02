<?php declare(strict_types=1);

/**
 * Runs the parser against the official CommonMark test suite and prints a per-section
 * score. This is what makes the example a claim that can be checked rather than a demo.
 *
 * Usage:
 *   php "markdown parser/spec-test.php"                 all sections, summary only
 *   php "markdown parser/spec-test.php" Emphasis        one section
 *   php "markdown parser/spec-test.php" Emphasis -v     with a diff of every failure
 *
 * The suite is downloaded on first run and cached next to this file.
 */

use Texy\Examples\Markdown\HtmlRenderer;
use Texy\Examples\Markdown\Markdown;

if (@!include __DIR__ . '/../../vendor/autoload.php') {
	die('Install packages using `composer install`');
}

require __DIR__ . '/nodes.php';
require __DIR__ . '/Markdown.php';
require __DIR__ . '/HtmlRenderer.php';

const SpecUrl = 'https://spec.commonmark.org/0.31.2/spec.json';
const SpecFile = __DIR__ . '/spec.json';

if (!is_file(SpecFile)) {
	echo 'Downloading ' . SpecUrl . " ...\n";
	$data = @file_get_contents(SpecUrl);
	if ($data === false) {
		die('Download failed. Fetch ' . SpecUrl . " manually and save it as spec.json here.\n");
	}
	file_put_contents(SpecFile, $data);
}

$tests = json_decode(file_get_contents(SpecFile), true);
$filter = ($argv[1] ?? null) === '-v' ? null : ($argv[1] ?? null);
$verbose = in_array('-v', $argv, true);

$markdown = new Markdown;
$renderer = new HtmlRenderer;
$sections = $failures = [];

foreach ($tests as $test) {
	if ($filter !== null && stripos($test['section'], $filter) === false) {
		continue;
	}

	$sections[$test['section']] ??= ['pass' => 0, 'total' => 0];
	$sections[$test['section']]['total']++;

	try {
		$actual = $renderer->render($markdown->parse($test['markdown']));
	} catch (Throwable $e) {
		$actual = $e::class . ': ' . $e->getMessage();
	}

	if ($actual === $test['html']) {
		$sections[$test['section']]['pass']++;
	} else {
		$failures[] = $test + ['actual' => $actual];
	}
}

if (!$sections) {
	die("No section matches '$filter'.\n");
}

$passed = $total = 0;
printf("%-42s %5s %6s %7s\n", 'SECTION', 'PASS', 'TOTAL', '');
foreach ($sections as $section => $score) {
	$passed += $score['pass'];
	$total += $score['total'];
	printf("%-42s %5d %6d %6.0f%%\n", $section, $score['pass'], $score['total'], $score['pass'] / $score['total'] * 100);
}
printf("%-42s %5d %6d %6.1f%%\n", 'TOTAL', $passed, $total, $passed / $total * 100);

if ($verbose) {
	foreach ($failures as $failure) {
		echo str_repeat('=', 72), "\n";
		echo "example {$failure['example']} [{$failure['section']}]\n";
		echo "--- source ---\n{$failure['markdown']}";
		echo "--- expected ---\n{$failure['html']}";
		echo "--- actual ---\n{$failure['actual']}";
	}
}
