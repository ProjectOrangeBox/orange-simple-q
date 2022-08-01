<?php

declare(strict_types=1);

namespace simpleq;

use DateTime;
use simpleq\Exceptions\SimpleQException;

class SimpleQ
{
	const NEW = 1;
	const TAGGED = 2;
	const COMPLETE = 3;
	const ERROR = 4;

	protected $db = null;

	protected $table = 'simpleq';
	protected $garagePickUp = 10;
	protected $cleanUpHours = 24 * 7; /* 7 days */
	protected $retagHours = 1; /* retag as "new" incase a process died while working on the data */
	protected $tokenHash = 'sha1';
	protected $currentQueue = 'default';
	protected $autoComplete = true;

	protected $currentToken = null;

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

		$table = isset($config['table']) ? $config['table'] : $this->table;

		$this->db->tablename($table);

		$this->cleanUpHours = isset($config['clean up hours']) ? (int)$config['clean up hours'] : $this->cleanUpHours;
		$this->retagHours = isset($config['requeue tagged hours']) ? (int)$config['requeue tagged hours'] : $this->retagHours;
		$this->tokenHash = isset($config['token hash']) ? $config['token hash'] : $this->tokenHash;
		$this->garagePickUp = isset($config['garbage collection percent']) ? $config['garbage collection percent'] : $this->garagePickUp;
		$this->currentQueue = isset($config['default queue']) ? $config['default queue'] : $this->currentQueue;
		$this->autoComplete = isset($config['auto complete']) ? (bool)$config['auto complete'] : $this->autoComplete;

		$this->garagePickUp();
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

	/**
	 * Method getQueue
	 *
	 * @return void
	 */
	public function getQueue(): string
	{
		return $this->currentQueue;
	}

	/**
	 * Method getToken
	 *
	 * @return string
	 */
	public function getToken(): string
	{
		return ($this->currentToken) ? $this->currentToken : '';
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

		$now = $this->now();

		return ($this->db->query('insert into __tablename__ (new,status,payload,queue,checksum) values (?,?,?,?,?)', [$now, SELF::NEW, $serialized, hash($this->tokenHash, $this->currentQueue), crc32($serialized . $now)])->rowCount() == 1);
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
		if ($this->autoComplete && $this->currentToken !== null) {
			$this->complete();
		}

		$queue = ($queue) ?? $this->currentQueue;

		$data = false;

		/* tag one */
		$token = hash($this->tokenHash, uniqid('', true));

		if ($this->db->query('update __tablename__ set token = ?, status = ?, tagged = ? where status = ? and token is null and queue = ? limit 1', [$token, SELF::TAGGED, $this->now(), SELF::NEW, hash($this->tokenHash, $queue)])->rowCount() > 0) {
			$cursor = $this->db->query('select new, token, payload, checksum from __tablename__ where token = ?', [$token]);

			$record = $cursor->fetchObject();

			if (crc32($record->payload . $record->new) != $record->checksum) {
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
		return $this->changeStatus('complete', self::COMPLETE);
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
		return $this->changeStatus('error', self::ERROR);
	}

	/** PROTECTED */

	/**
	 * Method changeStatus
	 *
	 * @param string $datetimeColumnName [explicite description]
	 * @param int $status [explicite description]
	 *
	 * @return bool
	 */
	protected function changeStatus(string $datetimeColumnName, int $status): bool
	{
		if ($this->currentToken == null) {
			throw new SimpleQException('Change status failed. No record token loaded');
		}

		$token = $this->currentToken;

		$this->currentToken = null;

		return ($this->db->query('update __tablename__ set token = null, status = ?, ' . $datetimeColumnName . ' = ? where token = ?', [$status, $this->now(), $token])->rowCount() == 1);
	}

	/**
	 * Method garagePickUp
	 *
	 * @return self
	 */
	protected function garagePickUp(): void
	{
		if (mt_rand(1, 99) < $this->garagePickUp) {
			/* retag "tagged" to "new" if they got stuck because a process never completed them */
			if ($this->retagHours > 0) {
				$this->db->query('update __tablename__ set token = null, status = ' . self::NEW . ', tagged = null where tagged < now() - interval ' . $this->retagHours . ' hour and status = ' . self::TAGGED);
			}

			/* delete any complete */
			if ($this->cleanUpHours > 0) {
				$this->db->query('delete from __tablename__ where complete < now() - interval ' . $this->cleanUpHours . ' hour and status = ' . self::COMPLETE);
			}
		}
	}

	/**
	 * Method now
	 *
	 * @return string
	 */
	protected function now(): string
	{
		$now = false;

		/* sometimes now is null */
		do {
			$now = DateTime::createFromFormat('U.u', (string)microtime(true));
		} while (!$now);

		return $now->format('Y-m-d H:i:s.u');
	}
} /* end class */
