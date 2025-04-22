<?php declare(strict_types=1);

/**
 * TYPOGRAPHY-ONLY PROCESSING
 *
 * Sometimes you don't need full Texy markup processing - you just want
 * the typography fixes: smart quotes, proper dashes, non-breaking spaces,
 * and hyphenation of long words.
 *
 * WHAT YOU'LL LEARN:
 * - How to use processTypo() instead of process()
 * - Texy's typographic improvements (quotes, dashes, spaces)
 *
 * WHAT processTypo() DOES:
 * - Replaces straight quotes with typographic quotes: "text" → „text"
 * - Replaces double hyphens with en-dash: -- → –
 * - Replaces triple dots with ellipsis: ... → …
 * - Inserts non-breaking spaces after short words
 * - Hyphenates long words for better text wrapping
 *
 * Note: Typography rules depend on the locale setting.
 * Default is Czech (cs), can be changed via $texy->config->locale
 */


if (@!include __DIR__ . '/../../vendor/autoload.php') { // @ dependencies may not be installed
	die('Install packages using `composer install`');
}


$texy = new Texy\Texy;

// Long words get soft hyphens for better text wrapping (on by default)
// $texy->config->hyphenation = false;

// Optional: Change the locale for typography rules
// $texy->config->locale = 'en';  // English quotes: "text"
// $texy->config->locale = 'cs';  // Czech quotes: „text" (default)
// $texy->config->locale = 'de';  // German quotes: „text"
// $texy->config->locale = 'fr';  // French quotes: «text»


// Use processTypo() instead of process()
// This applies only typography fixes, not full Texy markup
$text = <<<'TEXY'
	"Hello"

	(c) John Doe

	tohlejevelmidlouheslovo

	Nedělitelná mezera ve spojení slov v lese.
	TEXY;
$html = $texy->processTypo($text);


// Display the result
echo '<!doctype html><meta charset=utf-8>';
echo '<link rel="stylesheet" href="../style.css">';
echo $html;
