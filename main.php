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

while (1 == 1) {

	for ($i = 0; $i < mt_rand(100, 9999); $i++) {
		$success = $simpleQ->push(['name' => 'Don Myers ' . date('H:i:s') . '.' . mt_rand(1000, 9999)]);
	}

	for ($i = 0; $i < mt_rand(1, 10); $i++) {
		$data = $simpleQ->pull();

		echo $data['name'] . chr(10);

		/* threw random error */
		if (mt_rand(1, 99) > 75) {
			$simpleQ->error();
		} else {
			$simpleQ->complete();
		}
	}
}
