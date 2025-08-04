<?php declare(strict_types=1);

/**
 * Test: the engine does not depend on the Texy language.
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


const EngineDirectories = ['Parsing', 'Nodes', 'Output'];


function isForbidden(string $class): bool
{
	$class = strtolower(ltrim($class, '\\'));
	return $class === 'texy\texy'
		|| $class === 'texy\texyconfig'
		|| $class === 'texy\modules'
		|| str_starts_with($class, 'texy\modules\\');
}


/**
 * Finds the references to the language in PHP code, imported or written out.
 * @return list<string>
 */
function findForbiddenReferences(string $code): array
{
	$tokens = array_values(array_filter(
		PhpToken::tokenize($code),
		fn(PhpToken $token) => !$token->is([T_WHITESPACE, T_COMMENT, T_DOC_COMMENT]),
	));
	$namespace = '';
	$imports = [];
	$found = [];
	$depth = 0;

	for ($i = 0; $i < count($tokens); $i++) {
		$token = $tokens[$i];
		$depth += match ($token->text) {
			'{' => 1,
			'}' => -1,
			default => 0,
		};

		if ($token->is(T_NAMESPACE) && $tokens[$i + 1]->is([T_STRING, T_NAME_QUALIFIED])) {
			$namespace = $tokens[++$i]->text;

		} elseif ($token->is(T_USE) && $depth === 0) {
			$importsClasses = !$tokens[$i + 1]->is([T_FUNCTION, T_CONST]);
			$statement = '';
			while ($tokens[++$i]->text !== ';') {
				$statement .= $tokens[$i]->text . ($tokens[$i]->is([T_AS]) || $tokens[$i + 1]->is([T_AS]) ? ' ' : '');
			}

			if (!$importsClasses) {
				continue;
			}

			foreach (expandImports($statement) as $alias => $class) {
				$imports[strtolower($alias)] = $class;
				if (isForbidden($class)) {
					$found[] = $class;
				}
			}

		} elseif ($token->is(T_NAME_FULLY_QUALIFIED)) {
			if (isForbidden($token->text)) {
				$found[] = ltrim($token->text, '\\');
			}

		} elseif ($token->is(T_NAME_QUALIFIED)) {
			[$first, $rest] = explode('\\', $token->text, 2);
			$class = isset($imports[strtolower($first)])
				? $imports[strtolower($first)] . '\\' . $rest
				: ltrim($namespace . '\\' . $token->text, '\\');
			if (isForbidden($class)) {
				$found[] = $class;
			}

		} elseif (
			$token->is(T_STRING)
			&& isset($imports[strtolower($token->text)])
			&& !$tokens[$i - 1]->is([T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_CONST])
			&& isForbidden($imports[strtolower($token->text)])
		) {
			$found[] = $imports[strtolower($token->text)];
		}
	}

	return array_values(array_unique($found));
}


/** @return array<string, string>  alias => class */
function expandImports(string $statement): array
{
	$res = [];
	$statement = preg_replace('~^([^{]*)\{(.*)\}$~', '$1$2', $statement, count: $grouped);
	$prefix = '';
	if ($grouped) {
		$prefix = substr($statement, 0, strrpos($statement, '\\') + 1);
	}

	foreach (explode(',', $grouped ? substr($statement, strlen($prefix)) : $statement) as $item) {
		$item = trim($item);
		if ($item !== '') {
			[$class, $alias] = explode(' as ', $item) + [1 => null];
			$class = $prefix . trim($class);
			$res[$alias === null ? substr(strrchr('\\' . $class, '\\'), 1) : trim($alias)] = $class;
		}
	}

	return $res;
}


test('a reference is found however it is written', function () {
	$read = fn(string $code) => findForbiddenReferences("<?php\nnamespace Texy\\Passes;\n" . $code);

	Assert::same([Texy\Texy::class], $read('use Texy\Texy; class A { function f(Texy $texy) {} }'));
	Assert::same([Texy\Texy::class], $read('use Texy\{Node, Texy}; class A {}'));
	Assert::same([Texy\Texy::class], $read('use Texy\Texy as T; class A {}'));
	Assert::same([Texy\Texy::class], $read('class A { function f(\Texy\Texy $texy) {} }'));
	Assert::same([Texy\TexyConfig::class], $read('class A { function f(\Texy\TexyConfig $config) {} }'));
	Assert::same([Texy\Modules\ImageModule::class], $read('class A { function f(\Texy\Modules\ImageModule $m) {} }'));
	Assert::same([Texy\Modules\ImageModule::class], $read('use Texy; class A { function f(Texy\Modules\ImageModule $m) {} }'));
	Assert::same(
		['Texy\Modules', Texy\Modules\ImageModule::class],
		$read('use Texy\Modules; class A { function f(Modules\ImageModule $m) {} }'),
	);
});


test('what merely looks like a reference is not one', function () {
	$read = fn(string $code) => findForbiddenReferences("<?php\nnamespace Texy\\Passes;\n" . $code);

	Assert::same([], $read('// use Texy\Texy;' . "\n" . '/** @var \Texy\Texy */ class A {}'));
	Assert::same([], $read('$s = "\Texy\Texy";'));
	Assert::same([], $read('use Texy\Node; class A { function f() { return $this->Texy; } }'));
	Assert::same([], $read('use Texy; class A { function f(Texy\Node $node) {} }'));
	Assert::same([], $read('use function Texy\strlen; class A {}'));
});


test('the engine reads nothing of the language', function () {
	$root = dirname(__DIR__, 2) . '/src/';
	$files = 0;
	foreach (EngineDirectories as $directory) {
		foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
			$root . $directory,
			FilesystemIterator::SKIP_DOTS,
		)) as $file) {
			$relative = strtr(substr($file->getPathname(), strlen($root)), '\\', '/');
			if ($file->getExtension() === 'php') {
				$files++;
				Assert::same([], findForbiddenReferences(file_get_contents($file->getPathname())), $relative);
			}
		}
	}

	Assert::true($files > 50);
});
