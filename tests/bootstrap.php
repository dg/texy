<?php declare(strict_types=1);

/**
 * Test initialization and helpers.
 */

if (@!include __DIR__ . '/../vendor/autoload.php') { // @ dependencies may not be installed
	echo 'Install Nette Tester using `composer update --dev`';
	exit(1);
}

Tester\Environment::setup();
date_default_timezone_set('Europe/Prague');
$_GET = $_POST = $_COOKIE = [];


function test(string $title, Closure $function): void
{
	$function();
}


function normalizeNewlines(string $s): string
{
	return str_replace("\r\n", "\n", $s);
}


/**
 * Returns the command of the CLI interpreter with the ini file of the running test, which may run under php-cgi.
 * @return list<string>
 */
function getPhpCommand(): array
{
	$binary = PHP_SAPI === 'cli'
		? PHP_BINARY
		: dirname(PHP_BINARY) . '/php' . (PHP_OS_FAMILY === 'Windows' ? '.exe' : '');
	if (php_ini_scanned_files()) {
		return [$binary];
	}

	$ini = php_ini_loaded_file();
	return [$binary, '-n', ...($ini ? ['-c', $ini] : [])];
}


/**
 * Asserts that the time of $run grows linearly with its size, not quadratically.
 * CPU time of four times the input is compared, so neither a slow machine nor a busy one fails it.
 * @param  Closure(int): mixed  $run
 */
function assertLinearTime(Closure $run, int $size): void
{
	$small = measureCpuTime(fn() => $run($size));
	$large = measureCpuTime(fn() => $run(4 * $size));
	Tester\Assert::true($large < 8 * $small + 0.1, sprintf('%.3f s for size %d, %.3f s for size %d', $small, $size, $large, 4 * $size));
}


function measureCpuTime(Closure $fn): float
{
	$sum = fn(array $r): float => $r['ru_utime.tv_sec']
		+ $r['ru_utime.tv_usec'] / 1e6
		+ $r['ru_stime.tv_sec']
		+ $r['ru_stime.tv_usec'] / 1e6;
	$start = getrusage();
	$fn();
	return $sum(getrusage()) - $sum($start);
}
