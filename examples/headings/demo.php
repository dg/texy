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


if (@!include __DIR__ . '/../vendor/autoload.php') {
	die('Install packages using `composer install`');
}


$texy = new Texy;
$text = file_get_contents(__DIR__ . '/sample.texy');


// ============================================================
// METHOD 1: DYNAMIC BALANCING
// Texy analyzes the document and automatically assigns heading
// levels to create a logical hierarchy.
// ============================================================

$texy->headingModule->top = 2;   // Start with <h2> (useful when <h1> is the page title)
$texy->headingModule->balancing = Texy\Modules\HeadingModule::DYNAMIC;

// Generate IDs for headings (creates id="toc-heading-text")
// This allows linking to specific sections: page.html#toc-heading-text
$texy->headingModule->generateID = true;

$html = $texy->process($text);

echo '<!doctype html><meta charset=utf-8>';
echo '<link rel="stylesheet" href="../style.css">';

// You can use the first heading as the page title
echo '<title>' . $texy->headingModule->title . '</title>';

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

$texy->headingModule->top = 1;   // Start with <h1>
$texy->headingModule->balancing = Texy\Modules\HeadingModule::FIXED;

$html = $texy->process($text);

echo '<strong>Fixed method:</strong>';
echo '<pre>';
echo htmlspecialchars($html);
echo '</pre>';
echo '<hr>';


// ============================================================
// METHOD 3: USER-DEFINED LEVELS
// You manually define which syntax character produces which level.
// ============================================================

$texy->headingModule->top = 1;
$texy->headingModule->balancing = Texy\Modules\HeadingModule::FIXED;

// Define your own mapping:
// '=' underline produces level 0 + top (1) = h1
// '-' underline produces level 1 + top (1) = h2
$texy->headingModule->levels['='] = 0;
$texy->headingModule->levels['-'] = 1;

$html = $texy->process($text);

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
//print_r($texy->headingModule->TOC);
echo '</pre>';
