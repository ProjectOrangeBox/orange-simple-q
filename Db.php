<?php

declare(strict_types=1);

class Db
{
	public $pdo;

	public function __construct(string $databasename, string $username, string $password, string $host = '127.0.0.1', int $port = 3306, array $options = [])
	{
		$defaultOptions = [
			PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
			PDO::ATTR_EMULATE_PREPARES => false,
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
		];

		$options = array_replace($defaultOptions, $options);
		$dsn = "mysql:host=$host;dbname=$databasename;port=$port;charset=utf8mb4";

		try {
			$this->pdo = new \PDO($dsn, $username, $password, $options);
		} catch (\PDOException $e) {
			throw new \PDOException($e->getMessage(), (int)$e->getCode());
		}

		$this->pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
	}

	public function insert(string $table, array $kv, string $append = '')
	{
		$fields = [];
		$values = [];

		foreach ($kv as $key => $value) {
			$fields[] = '`' . $key . '`';
			$values[] = "'" . $value . "'";
		}

		return $this->pdo->query('insert into ' . $table . ' (' . implode(' , ', $fields) . ') values (' . implode(' , ', $values) . ') ' . $append);
	}

	public function update(string $table, array $kv, array $wkv = null, string $append = '')
	{
		$fields = [];

		foreach ($kv as $key => $value) {
			if ($value == null) {
				$fields[] = "`" . $key . "`= null";
			} else {
				$fields[] = "`" . $key . "`= '" . $value . "'";
			}
		}

		$where = [];

		foreach ($wkv as $key => $value) {
			if ($value === null) {
				$where[] = $key;
			} else {
				$where[] = "`" . $key . "`= '" . $value . "'";
			}
		}

		return $this->pdo->query('update ' . $table . ' set ' . implode(' , ', $fields) . ' where ' . implode(' and ', $where) . ' ' . $append);
	}

	public function delete(string $table, array $wkv = null, string $append = '')
	{
		$where = [];

		foreach ($wkv as $key => $value) {
			if ($value === null) {
				$where[] = $key;
			} else {
				$where[] = "`" . $key . "`= '" . $value . "'";
			}
		}

		return $this->pdo->query('delete from `' . $table . '` where ' . implode(' and ', $where) . ' ' . $append);
	}


	public function select(string $table, array $kv, array $wkv = null, string $append = '')
	{
		$fields = [];

		foreach ($kv as $value) {
			$fields[] = "`" . $value . "`";
		}

		$where = [];

		foreach ($wkv as $key => $value) {
			if ($value === null) {
				$where[] = $key;
			} else {
				$where[] = "`" . $key . "`= '" . $value . "'";
			}
		}

		return $this->pdo->query('select ' . implode(' , ', $fields) . ' from `' . $table . '` where ' . implode(' and ', $where) . ' ' . $append);
	}
} /* end class */
