<?php

declare(strict_types=1);

use simpleq\SimpleQ;

date_default_timezone_set('America/New_York');

require __DIR__ . '/Db.php';
require __DIR__ . '/src/SimpleQ.php';
require __DIR__ . '/src/Exceptions/SimpleQException.php';

$config = [
	'db' => new Db('simpleq', 'root', 'root', 'localhost'),
	'garbage collection percent' => 100,
];

$simpleQ = new SimpleQ($config);

$count = 10;

while (1 == 1) {
	/* add some */
	for ($i = 0; $i < mt_rand(1, $count); $i++) {
		$obj = new stdClass;

		$obj->name = 'Don Myers';
		$obj->date =  date('H:i:s');
		$obj->rando = mt_rand(1000, 9999);
		$obj->string = RandomString(mt_rand(10, 100));

		$simpleQ->push($obj);
	}

	/* pull some */
	for ($i = 0; $i < mt_rand(1, $count); $i++) {
		$data = $simpleQ->pull();

		if ($data) {
			var_dump($data);

			/* threw random error */
			if (mt_rand(1, 99) > 75) {
				$simpleQ->error();
			} else {
				$simpleQ->complete();
			}
		}
	}
}

function RandomString(int $length)
{
	$characters = ' 0123456 789abcdefghijk lmnopqrstuvwxyzABC DEFGHIJKLMNOPQRSTUVWXYZ ';
	$randstring = '';

	for ($i = 0; $i < $length; $i++) {
		$randstring .= $characters[rand(0, strlen($characters) - 1)];
	}

	return $randstring;
}
