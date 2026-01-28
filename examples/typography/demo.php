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
 * - Enable automatic hyphenation of long words
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
 * Default is Czech (cs), can be changed via $texy->typographyModule->locale
 */


if (@!include __DIR__ . '/../../vendor/autoload.php') {
	die('Install packages using `composer install`');
}


$texy = new Texy;

// Enable hyphenation of long words
// Long words will get soft hyphens (­) for better text wrapping
$texy->allowed['longwords'] = true;

// Optional: Change the locale for typography rules
// $texy->typographyModule->locale = 'en';  // English quotes: "text"
// $texy->typographyModule->locale = 'cs';  // Czech quotes: „text" (default)
// $texy->typographyModule->locale = 'de';  // German quotes: „text"
// $texy->typographyModule->locale = 'fr';  // French quotes: «text»


// Use processTypo() instead of process()
// This applies only typography fixes, not full Texy markup
$text = file_get_contents(__DIR__ . '/sample.texy');
$html = $texy->processTypo($text);


// Display the result
echo '<!doctype html><meta charset=utf-8>';
echo '<link rel="stylesheet" href="../style.css">';
echo $html;
