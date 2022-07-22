<?php

namespace simpleq;

use DateTime;
use simpleq\Exceptions\SimpleQException;

class SimpleQ
{
	const NEW = 10;
	const TAGGED = 20;
	const COMPLETE = 30;
	const ERROR = 40;

	protected $db = null;

	protected $table = 'simpleq';
	protected $cleanUpHours = 168; /* 7 days */
	protected $retagHours = 0; /* retag as new incase a process died while working on the data */
	protected $tokenHash = 'sha1';
	protected $currentQueue = 'default';
	protected $autoComplete = true;
	protected $salt = 'ABC123';

	protected $currentToken = '';

	/**
	 * Method __construct
	 *
	 * @param array $config [explicite description]
	 *
	 * @return void
	 */
	public function __construct(array $config)
	{
		$this->db = $config['db'];

		$this->table = isset($config['table']) ? $config['table'] : $this->table;
		$this->cleanUpHours = isset($config['clean up hours']) ? (int)$config['clean up hours'] : $this->cleanUpHours;
		$this->retagHours = isset($config['requeue tagged hours']) ? (int)$config['requeue tagged hours'] : $this->retagHours;
		$this->tokenHash = isset($config['token hash']) ? $config['token hash'] : $this->tokenHash;
		$this->currentQueue = isset($config['default queue']) ? $config['default queue'] : $this->currentQueue;
		$this->autoComplete = isset($config['auto complete']) ? $config['auto complete'] : $this->autoComplete;
		$this->salt = isset($config['salt']) ? $config['salt'] : $this->salt;

		$garagePickUp = isset($config['garbage collection percent']) ? $config['garbage collection percent'] : 10;

		if (mt_rand(0, 99) < $garagePickUp) {
			$this->garagePickUp();
		}
	}

	/**
	 * Method queue
	 *
	 * @param string $queue [explicite description]
	 *
	 * @return SimpleQ
	 */
	public function queue(string $queue): self
	{
		$this->currentQueue = $queue;

		return $this;
	}

	public function getToken(): string
	{
		return $this->currentToken;
	}

	/**
	 * Method push
	 *
	 * @param $data $data [explicite description]
	 * @param string $queue [explicite description]
	 *
	 * @return bool
	 */
	public function push($data, string $queue = null): bool
	{
		/* change queue if it's sent in */
		if ($queue !== null) {
			$this->queue($queue);
		}

		$serialized = base64_encode(serialize($data));

		$dbc = $this->db->insert($this->table, [
			'created' => $this->now(),
			'status' => SELF::NEW,
			'payload' => $serialized,
			'queue' => $this->getQueue(),
			'checksum' => crc32($serialized . $this->salt),
		]);

		return ($dbc->rowCount() == 1);
	}

	/**
	 * Method pull
	 *
	 * @param $queue $queue [explicite description]
	 *
	 * @return void
	 */
	public function pull(string $queue = null) /* record or false if nothing found */
	{
		if ($this->autoComplete && !empty($this->currentToken)) {
			$this->complete();
		}

		$data = false;

		/* tag one */
		$token = $this->makeHash(uniqid('', true));

		$stmt = $this->db->update($this->table, [
			'token' => $token,
			'status' => SELF::TAGGED,
			'updated' => $this->now()
		], [
			'status' => SELF::NEW,
			'token is null' => null,
			'queue' => $this->getQueue($queue),
		], 'limit 1');

		if ($stmt->rowCount() > 0) {
			$stmt = $this->db->select($this->table, ['token', 'payload', 'checksum'], ['token' => $token]);

			$record = $stmt->fetchObject();

			if (crc32($record->payload . $this->salt) != $record->checksum) {
				throw new SimpleQException('Checksum failed');
			}

			$this->currentToken = $record->token;

			$data = unserialize(base64_decode($record->payload));
		}

		return $data;
	}

	/**
	 * Method complete
	 *
	 * @param string $token [explicite description]
	 *
	 * @return bool
	 */
	public function complete(): bool
	{
		$success = $this->update($this->currentToken, self::COMPLETE);

		$this->currentToken = null;

		return $success;
	}

	/**
	 * Method error
	 *
	 * @param string $token [explicite description]
	 *
	 * @return bool
	 */
	public function error(): bool
	{
		$success = $this->update($this->currentToken, self::ERROR);

		$this->currentToken = null;

		return $success;
	}

	/** PROTECTED */

	/**
	 * Method makeHash
	 *
	 * @param string $string [explicite description]
	 *
	 * @return string
	 */
	protected function makeHash(string $value): string
	{
		return hash($this->tokenHash, $value);
	}

	/**
	 * Method getQueue
	 *
	 * @return void
	 */
	protected function getQueue(): string
	{
		return $this->makeHash($this->currentQueue);
	}

	/**
	 * Method update
	 *
	 * @param $token $token [explicite description]
	 * @param $status $status [explicite description]
	 *
	 * @return bool
	 */
	protected function update(string $token, int $status): bool
	{
		$dbc = $this->db->update($this->table, [
			'token' => null,
			'updated' => $this->now(),
			'status' => $status,
		], [
			'token' => $token
		]);

		return ($dbc->rowCount() == 1);
	}

	protected function garagePickUp(): self
	{
		if ($this->retagHours > 0) {
			$this->db->update($this->table, [
				'token' => null,
				'status' => self::NEW,
				'updated' => $this->now()
			], [
				'updated < now() - interval ' . $this->retagHours . ' hour' => null,
				'status' => self::TAGGED,
			]);
		}

		if ($this->cleanUpHours > 0) {
			$this->db->delete($this->table, [
				'updated < now() - interval ' . $this->cleanUpHours . ' hour' => null,
				'status' => self::COMPLETE,
			]);
		}

		return $this;
	}

	protected function now(): string
	{
		$now = DateTime::createFromFormat('U.u', microtime(true));

		return $now->format('Y-m-d H:i:s.u');
	}
} /* end class */
