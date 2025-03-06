<?php declare(strict_types=1);

/**
 * Test: every example in examples/ runs without an error, a warning or a deprecation.
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


$examples = glob(__DIR__ . '/../../examples/*/*.php') ?: [];
Assert::true($examples !== []);

foreach ($examples as $file) {
	$process = proc_open(
		[PHP_BINARY, '-d', 'error_reporting=-1', '-d', 'display_errors=stderr', '-d', 'log_errors=0', $file],
		[1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
		$pipes,
		dirname($file),
	);
	stream_get_contents($pipes[1]);
	$errors = stream_get_contents($pipes[2]);
	$code = proc_close($process);

	$name = basename(dirname($file)) . '/' . basename($file);
	Assert::same('', trim((string) $errors), $name);
	Assert::same(0, $code, $name);
}
