<?php

/**
 * Test initialization and helpers.
 */


if (@!include __DIR__ . '/../vendor/autoload.php') {
	echo 'Install Nette Tester using `composer update --dev`';
	exit(1);
}


// configure environment
Tester\Environment::setup();
class_alias('Tester\Assert', 'Assert');
date_default_timezone_set('Europe/Prague');
$_GET = $_POST = $_COOKIE = array();
Texy::$advertisingNotice = FALSE;


if (extension_loaded('xdebug')) {
	xdebug_disable();
	Tester\CodeCoverage\Collector::start(__DIR__ . '/coverage.dat');
}


function test(\Closure $function)
{
	$function();
}
