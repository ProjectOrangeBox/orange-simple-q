<?php

declare(strict_types=1);

use simpleq\SimpleQ;

date_default_timezone_set('America/New_York');

require __DIR__ . '/Db.php';
require __DIR__ . '/src/SimpleQ.php';
require __DIR__ . '/src/Exceptions/SimpleQException.php';


$config = [
	'db' => new Db('simpleq', 'root', 'root', 'localhost'),
];

$simpleQ = new SimpleQ($config);

for ($i = 0; $i < mt_rand(100, 9999); $i++) {
	$success = $simpleQ->push(['name' => 'Don Myers ' . date('H:i:s') . '.' . mt_rand(1000, 9999)]);
}

$data = $simpleQ->pull();

var_dump($data);

/* do something with data */
$simpleQ->complete();
