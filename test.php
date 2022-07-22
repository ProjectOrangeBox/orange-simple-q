<?php

declare(strict_types=1);

require __DIR__ . '/configMerge.php';

class foobar
{
}

$stdClass = new \stdClass;
$x = new \foobar;

$config = [
	'name' => 'Don',
	'age' => 23,
	'pet' => ['dog', 'Jake'],
	'food' => 'pizza',
	'male' => true,
	'remove' => true,
	'something' => $stdClass,
	'foobar' => $x,
];

$options = [
	'name' => ['Don', 'is_string'],
	'age' => [18, 'is_int'],
	'pet' => [['cat', 'Meow'], 'is_array'],
	'food' => ['cookie', 'is_string'],
	'friend' => ['Jane', 'is_string'],
	'male' => [null, 'is_bool'],
	'something' => [null, '\stdClass'],
	'foobar' => [null, '\foobar'],
];

$config = configMerge($config, $options);

var_dump($config);
