<?php

declare(strict_types=1);

use simpleq\SimpleQ;

date_default_timezone_set('America/New_York');

require __DIR__ . '/src/SimpleQ.php';
require __DIR__ . '/src/Exceptions/SimpleQException.php';

$simpleQ = new SimpleQ([
	'garbage collection percent' => 100,
], getConnection('simpleq', 'root', 'root', 'localhost'));

$simpleQ->queue('hot');

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

function getConnection(string $databasename, string $username, string $password, string $host = '127.0.0.1', int $port = 3306, array $options = []): PDO
{
	$defaultOptions = [
		PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
		PDO::ATTR_EMULATE_PREPARES => false,
		PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
	];

	$options = array_replace($defaultOptions, $options);

	$dsn = "mysql:host=$host;dbname=$databasename;port=$port;charset=utf8";

	try {
		$pdo = new \PDO($dsn, $username, $password, $options);
	} catch (\PDOException $e) {
		throw new \PDOException($e->getMessage(), (int)$e->getCode());
	}

	return $pdo;
}
