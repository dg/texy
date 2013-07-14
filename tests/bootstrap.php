<?php

/**
 * Test initialization and helpers.
 */

if (@!include __DIR__ . '/../vendor/autoload.php') {
	echo 'Install Nette Tester using `composer update --dev`';
	exit(1);
}


if (PCRE_VERSION == 8.34 && PHP_VERSION_ID < 50513) {
	Tester\Environment::skip('PCRE 8.34 is not supported due to bug #1451');
}


Tester\Environment::setup();
date_default_timezone_set('Europe/Prague');
$_GET = $_POST = $_COOKIE = [];


function test(Closure $function)
{
	$function();
}
