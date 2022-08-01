<?php

declare(strict_types=1);

class Db
{
	public $pdo;
	public $append = '';
	public $tablename = '';

	public function __construct(string $databasename, string $username, string $password, string $host = '127.0.0.1', int $port = 3306, array $options = [])
	{
		$defaultOptions = [
			PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
			PDO::ATTR_EMULATE_PREPARES => false,
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
		];

		$options = array_replace($defaultOptions, $options);

		$dsn = "mysql:host=$host;dbname=$databasename;port=$port;charset=utf8";

		try {
			$this->pdo = new \PDO($dsn, $username, $password, $options);
		} catch (\PDOException $e) {
			throw new \PDOException($e->getMessage(), (int)$e->getCode());
		}
	}

	public function tablename(string $tablename): self
	{
		$this->tablename = $tablename;

		return $this;
	}

	public function append(string $append): self
	{
		$this->append = $append;

		return $this;
	}

	public function query(string $sql, array $values = []): \PDOStatement
	{
		$stmt = $this->pdo->prepare(str_replace('__tablename__', ' `' . $this->tablename . '` ', $sql));

		$stmt->execute($values);

		return $stmt;
	}

	public function insert(string $table, array $kv = []): int
	{
		$append = $this->getAppend();

		$fields = [];
		$values = [];
		$placeHolders = [];

		foreach ($kv as $key => $value) {
			$fields[] = '`' . $key . '`';
			$values[] = ":" . $key;
			$placeHolders[$key] = $value;
		}

		$stmt = $this->pdo->prepare('insert into ' . $table . ' (' . implode(' , ', $fields) . ') values (' . implode(' , ', $values) . ') ' . $append);

		$stmt->execute($placeHolders);

		$lastId = (int)$this->pdo->lastInsertId();

		return ($lastId != 0) ? $lastId : $stmt->rowCount();
	}

	public function update(string $table, array $kv = [], array $wkv = []): int
	{
		$append = $this->getAppend();

		$fields = [];
		$placeHolders = [];

		foreach ($kv as $key => $value) {
			if ($value == null) {
				$fields[] = "`" . $key . "`= null";
			} else {
				$fields[] = "`" . $key . "`= :set_" . $key;
				$placeHolders['set_' . $key] = $value;
			}
		}

		$where = [];

		foreach ($wkv as $key => $value) {
			if ($value === null) {
				$where[] = $key;
			} else {
				$where[] = "`" . $key . "`= :where_" . $key;
				$placeHolders['where_' . $key] = $value;
			}
		}

		$stmt = $this->pdo->prepare('update ' . $table . ' set ' . implode(' , ', $fields) . ' where ' . implode(' and ', $where) . ' ' . $append);

		$stmt->execute($placeHolders);

		return $stmt->rowCount();
	}

	public function delete(string $table, array $wkv = []): int
	{
		$append = $this->getAppend();

		$where = [];
		$placeHolders = [];

		foreach ($wkv as $key => $value) {
			if ($value === null) {
				$where[] = $key;
			} else {
				$where[] = "`" . $key . "`= :" . $key;
				$placeHolders[$key] = $value;
			}
		}

		$stmt = $this->pdo->prepare('delete from `' . $table . '` where ' . implode(' and ', $where) . ' ' . $append);

		$stmt->execute($placeHolders);

		return $stmt->rowCount();
	}

	public function select(string $table, array $selectFields, array $wkv = [])
	{
		$append = $this->getAppend();

		$fields = [];

		foreach ($selectFields as $value) {
			$fields[] = "`" . $value . "`";
		}

		$where = [];
		$placeHolders = [];

		foreach ($wkv as $key => $value) {
			if ($value === null) {
				$where[] = $key;
			} else {
				$where[] = "`" . $key . "`= :" . $key;
				$placeHolders[$key] = $value;
			}
		}

		$stmt = $this->pdo->prepare('select ' . implode(' , ', $fields) . ' from `' . $table . '` where ' . implode(' and ', $where) . ' ' . $append);

		$stmt->execute($placeHolders);

		return $stmt->fetchObject();
	}

	protected function getAppend(): string
	{
		$append = $this->append;

		$this->append = '';

		return $append;
	}
} /* end class */
