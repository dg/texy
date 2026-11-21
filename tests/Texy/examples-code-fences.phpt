<?php declare(strict_types=1);

/**
 * Test: the example "code blocks to fences" converts /--php blocks to fences without changing the HTML.
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


$dir = sys_get_temp_dir() . '/texy-code-fences-' . getmypid();
Tester\Helpers::purge($dir);


/**
 * Runs the script in $dir, returns its exit code and output.
 * @return array{int, string}
 */
function convert(string $dir, string ...$args): array
{
	$process = proc_open(
		[...getPhpCommand(), __DIR__ . '/../../examples/code blocks to fences/convert.php', ...$args],
		[1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
		$pipes,
		$dir,
	);
	$output = (string) stream_get_contents($pipes[1]);
	Assert::same('', stream_get_contents($pipes[2]));
	return [proc_close($process), $output];
}


$original = <<<'XX'
	/--php
	echo 1;
	\--

	/--code php .[foo]
	echo 2;
	\--

	/--js
		if (a) {
			b();
		}
	\--

	- item

	  /--php
	  echo 3;

	  echo 4;
	  \--

	> quote
	>
	> /--neon
	> a: 1
	> \--

	```php
	echo 'fence';
	```

	/--pre
	built-in
	\--

	/--.[x]
	bare
	\--

	/--foo bar
	not a language
	\--

	XX;

$converted = <<<'XX'
	```php
	echo 1;
	```

	```php .[foo]
	echo 2;
	```

	```js
	if (a) {
		b();
	}
	```

	- item

	  ```php
	  echo 3;

	  echo 4;
	  ```

	> quote
	>
	> ```neon
	> a: 1
	> ```

	```php
	echo 'fence';
	```

	/--pre
	built-in
	\--

	/--.[x]
	bare
	\--

	/--foo bar
	not a language
	\--

	XX;


// the texts are written with the line endings of this file, the script writes \n
[$original, $converted] = str_replace("\r\n", "\n", [$original, $converted]);


test('converts code blocks, nested ones included, and leaves the others alone', function () use ($dir, $original, $converted) {
	file_put_contents("$dir/a.texy", $original);
	Assert::same([0, "a.texy: converted: 5, left alone: 0\n"], convert($dir, 'a.texy'));
	Assert::same($converted, file_get_contents("$dir/a.texy"));
});


test('a second run finds nothing', function () use ($dir, $converted) {
	Assert::same([0, "a.texy: nothing to do\n"], convert($dir, 'a.texy'));
	Assert::same($converted, file_get_contents("$dir/a.texy"));
});


test('--check writes nothing and fails on a /-- code block', function () use ($dir, $original) {
	file_put_contents("$dir/b.texy", $original);
	Assert::same([1, "a.texy: nothing to do\nb.texy: to convert: 5, left alone: 0\n"], convert($dir, '--check', 'a.texy', 'b.texy'));
	Assert::same($original, file_get_contents("$dir/b.texy"));
	Assert::same([0, "a.texy: nothing to do\n"], convert($dir, '--check', 'a.texy'));
});


test('a file whose HTML would change is left alone', function () use ($dir) {
	// a fence carries only the first word of the language
	$text = "/--code php strict\necho 1;\n\\--\n";
	file_put_contents("$dir/c.texy", $text);
	Assert::same([1, "c.texy: skipped, the HTML would change\n"], convert($dir, 'c.texy'));
	Assert::same($text, file_get_contents("$dir/c.texy"));
});


test('a block starting after a bullet is left alone', function () use ($dir) {
	$text = "- /--php\n  echo 1;\n  \\--\n";
	file_put_contents("$dir/d.texy", $text);
	Assert::same([1, "d.texy: converted: 0, left alone: 1\n"], convert($dir, 'd.texy'));
	Assert::same($text, file_get_contents("$dir/d.texy"));
});


test('without files it prints the conversion of the sample', function () use ($dir) {
	[$code, $output] = convert($dir);
	Assert::same(0, $code);
	Assert::contains("```php\n\$texy = new Texy\\Texy;", $output);
	Assert::notContains('/--php', $output);
});


Tester\Helpers::purge($dir);
rmdir($dir);
