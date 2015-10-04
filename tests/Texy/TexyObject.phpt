<?php

/**
 * Test: TexyObject
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


class TestClass extends TexyObject
{
	public function callParent()
	{
		parent::callParent();
	}

	public function getBar()
	{
		return 123;
	}
}


// calling
Assert::exception(function () {
	$obj = new TestClass;
	$obj->undeclared();
}, 'LogicException', 'Call to undefined method TestClass::undeclared().');

Assert::exception(function () {
	TestClass::undeclared();
}, 'LogicException', 'Call to undefined static method TestClass::undeclared().');

Assert::exception(function () {
	$obj = new TestClass;
	$obj->callParent();
}, 'LogicException', 'Call to undefined method parent::callParent().');


// writing
Assert::exception(function () {
	$obj = new TestClass;
	$obj->undeclared = 'value';
}, 'LogicException', 'Attempt to write to undeclared property TestClass::$undeclared.');


// property getter
$obj = new TestClass;
Assert::false(isset($obj->bar));
Assert::same(123, $obj->bar);


// reading
Assert::exception(function () {
	$obj = new TestClass;
	$val = $obj->undeclared;
}, 'LogicException', 'Attempt to read undeclared property TestClass::$undeclared.');


// unset/isset
Assert::exception(function () {
	$obj = new TestClass;
	unset($obj->undeclared);
}, 'LogicException', 'Attempt to unset undeclared property TestClass::$undeclared.');

Assert::false(isset($obj->undeclared));
