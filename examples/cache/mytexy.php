<?php

/**
 * TEXY! CACHE DEMO
 */


// include libs
require_once __DIR__ . '/../../src/texy.php';


// MY OWN TEXY! OBJECT

class mytexy extends Texy
{
	public $cachePath = './cache/';
	public $time;


	public function __construct()
	{
		parent::__construct();

		// some configurations
		$this->alignClasses['left'] = 'left';
		$this->alignClasses['right'] = 'right';
	}


	public function cachedProcess($text, $useCache = true)
	{
		$this->time = -microtime(true);

		if ($useCache) {
			$md5 = md5($text); // md5 is key for caching

			// check, if cached file exists
			$cacheFile = $this->cachePath . $md5 . '.html';
			$content = is_file($cacheFile) ? unserialize(file_get_contents($cacheFile)) : null;
			if ($content) {         // read from cache
				list($html, $this->styleSheet, $this->headingModule->title) = $content;

			} else {                           // doesn't exists
				$html = parent::process($text);
				file_put_contents($cacheFile,
					serialize([$html, $this->styleSheet, $this->headingModule->title])
				);
			}

		} else { // if caching is disabled
			$html = $this->process($text);
		}

		$this->time += microtime(true);
		return $html;
	}
}
