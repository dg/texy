<?php

namespace Texy;

# Minimal PSR-4 Autoloader
spl_autoload_register(function($class) {
	if ($class !== strstr($class, __NAMESPACE__)) {
		return;
	}
	$file = substr($class, strlen(__NAMESPACE__));
	$file = str_replace('\\', DIRECTORY_SEPARATOR, $file) . '.php';
	$file = __DIR__ . $file;
	if (file_exists($file)) {
		require $file;
	}
});

