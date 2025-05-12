<?php declare(strict_types=1);

/**
 * CONTROLLING HEADING LEVELS
 *
 * Texy offers three ways to handle heading levels:
 * 1. DYNAMIC - Texy automatically balances headings based on document structure
 * 2. FIXED - Each heading syntax always produces the same level
 * 3. USER-DEFINED - You manually map heading syntax to specific levels
 *
 * WHAT YOU'LL LEARN:
 * - Three different approaches to heading level management
 * - How to generate automatic IDs for headings (useful for linking)
 * - How to extract a table of contents (TOC) from your document
 * - How to get the first heading as a page title
 *
 * TEXY HEADING SYNTAX:
 * Underlined:     Title        or    Title
 *                 =====             -----
 *
 * Surrounded:     ### Title ###  (more # = different level)
 */


if (@!include __DIR__ . '/../../vendor/autoload.php') { // @ dependencies may not be installed
	die('Install packages using `composer install`');
}


$texy = new Texy\Texy;
$text = file_get_contents(__DIR__ . '/sample.texy');


// ============================================================
// METHOD 1: DYNAMIC BALANCING
// Texy analyzes the document and automatically assigns heading
// levels to create a logical hierarchy.
// ============================================================

$texy->config->headingTop = 2;   // Start with <h2> (useful when <h1> is the page title)
$texy->config->headingBalancing = Texy\HeadingBalancing::Dynamic;

// Generate IDs for headings (creates id="toc-heading-text")
// This allows linking to specific sections: page.html#toc-heading-text
$texy->config->headingIds = true;

$document = $texy->transform($texy->parse($text));
$html = $texy->createHtmlRenderer()->render($document);

echo '<!doctype html><meta charset=utf-8>';
echo '<link rel="stylesheet" href="../style.css">';

// You can use the first heading as the page title
echo '<title>' . ($document->getHeadings()[0] ?? null)?->getPlainText() . '</title>';

echo '<strong>Dynamic method:</strong>';
echo '<pre>';
echo htmlspecialchars($html);
echo '</pre>';
echo '<hr>';


// ============================================================
// METHOD 2: FIXED LEVELS
// Each heading syntax always produces the same level,
// based on the number of characters used.
// ============================================================

$texy->config->headingTop = 1;   // Start with <h1>
$texy->config->headingBalancing = Texy\HeadingBalancing::Fixed;

$html = $texy->toHtml($text);

echo '<strong>Fixed method:</strong>';
echo '<pre>';
echo htmlspecialchars($html);
echo '</pre>';
echo '<hr>';


// ============================================================
// METHOD 3: USER-DEFINED LEVELS
// You manually define which syntax character produces which level.
// ============================================================

$texy->config->headingTop = 1;
$texy->config->headingBalancing = Texy\HeadingBalancing::Fixed;

// Define your own mapping:
// '=' underline produces level 1, with top 1 it is h1
// '-' underline produces level 2, with top 1 it is h2
$texy->config->headingLevels['='] = 1;
$texy->config->headingLevels['-'] = 2;

$html = $texy->toHtml($text);

echo '<strong>User-defined fixed method:</strong>';
echo '<pre>';
echo htmlspecialchars($html);
echo '</pre>';
echo '<hr>';


// ============================================================
// TABLE OF CONTENTS
// After processing, you can access all headings in the document.
// This is useful for generating navigation menus.
// ============================================================

echo '<h2>Table of contents</h2>';
echo '<pre>';
foreach ($document->getHeadings() as $heading) {
	echo str_repeat('  ', $heading->level - 1), htmlspecialchars(trim($heading->getPlainText())), ' #', $heading->modifier?->id, "\n";
}
echo '</pre>';
