<?php

/**
 * Test initialization and helpers.
 */

if (@!include __DIR__ . '/../vendor/autoload.php') {
	echo 'Install Nette Tester using `composer update --dev`';
	exit(1);
}


Tester\Environment::setup();
date_default_timezone_set('Europe/Prague');
$_GET = $_POST = $_COOKIE = [];


function test(Closure $function)
{
	$function();
}
