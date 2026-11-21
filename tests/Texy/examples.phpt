<?php declare(strict_types=1);

/**
 * Test: every example in examples/ runs without an error, a warning or a deprecation.
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


$examples = glob(__DIR__ . '/../../examples/*/*.php') ?: [];
Assert::true($examples !== []);

foreach ($examples as $file) {
	$name = basename(dirname($file)) . '/' . basename($file);
	if ($name === 'headings/demo.php' && !extension_loaded('intl')) {
		continue;
	}

	$process = proc_open(
		[...getPhpCommand(), '-d', 'error_reporting=-1', '-d', 'display_errors=stderr', '-d', 'log_errors=0', $file],
		[1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
		$pipes,
		dirname($file),
	);
	stream_get_contents($pipes[1]);
	$errors = stream_get_contents($pipes[2]);
	$code = proc_close($process);
	Assert::same('', trim((string) $errors), $name);
	Assert::same(0, $code, $name);
}
