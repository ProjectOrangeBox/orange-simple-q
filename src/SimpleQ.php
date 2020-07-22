<?php

namespace simpleq;

use stdClass;
use CI_Model;
use simpleq\SimpleQrecord;
use simpleq\Exceptions\SimpleQException;

class SimpleQ extends CI_Model
{
	protected $table = 'simple_q';
	protected $status_map = ['new' => 10, 'tagged' => 20, 'processed' => 30, 'error' => 40];
	protected $status_map_flipped;
	protected $db;
	protected $clean_up_hours;
	protected $retag_hours;
	protected $token_hash;
	protected $token_length;
	protected $queue = '';
	protected $json_options = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK | JSON_PRESERVE_ZERO_FRACTION;

	public function __construct(array $config = [])
	{
		$this->status_map_flipped = array_flip($this->status_map);

		$config = \configMerge('simple_q', [
			'tablename' => $this->table,
			'clean up hours' => 168 /* 7 days */,
			'requeue tagged hours' => 1 /* 1 hour */,
			'token hash' => 'sha1' /* sha1 */,
			'token length' => 40 /* sha1 length */,
			'database group' => 'default',
			'garbage collection percent' => 50 /* percent */
		], $config);

		$this->clean_up_hours = $config['clean up hours'];
		$this->retag_hours = $config['requeue tagged hours'];

		$this->token_hash = $config['token hash'];
		$this->token_length = $config['token length'];

		$this->db = $this->load->database($config['database group'], true);

		if (mt_rand(0, 99) < $config['garbage collection percent']) {
			$this->cleanup();
		}
	}

	public function queue(string $queue): SimpleQ
	{
		$this->queue = $queue;

		return $this;
	}

	protected function get_queue()
	{
		if (empty($this->queue)) {
			throw new SimpleQException('Simple Q default queue not set.');
		}

		return md5($this->queue);
	}

	public function push($data, string $queue = null): bool
	{
		if ($queue !== null) {
			$this->queue($queue);
		}

		return $this->db->insert($this->table, ['created' => date('Y-m-d H:i:s'), 'status' => $this->status_map['new'], 'payload' => $this->encode($data), 'queue' => $this->get_queue(), 'token' => null]);
	}

	public function pull($queue = null) /* record or false if nothing found */
	{
		$token = hash($this->token_hash, uniqid('', true));

		$this->db->set(['token' => $token, 'status' => $this->status_map['tagged'], 'updated' => date('Y-m-d H:i:s')])->where(['status' => $this->status_map['new'], 'token is null' => null, 'queue' => $this->get_queue($queue)])->limit(1)->update($this->table);

		if ($success = (bool) $this->db->affected_rows()) {
			$record = $this->db->limit(1)->where(['token' => $token])->get($this->table)->row();

			$record->status_raw = $record->status;
			$record->status = $this->status_map_flipped[$record->status];
			$record->payload = $this->decode($record);

			$success = new SimpleQrecord($record, $this);
		}

		return $success;
	}

	public function cleanup(): SimpleQ
	{
		if ($this->retag_hours > 0) {
			$this->db->set(['token' => null, 'status' => $this->status_map['new'], 'updated' => date('Y-m-d H:i:s')])->where(['updated < now() - interval ' . (int) $this->retag_hours . ' hour' => null, 'status' => $this->status_map['tagged']])->update($this->table);
		}

		if ($this->clean_up_hours > 0) {
			$this->db->where(['updated < now() - interval ' . (int) $this->clean_up_hours . ' hour' => null, 'status' => $this->status_map['processed']])->delete($this->table);
		}

		return $this;
	}

	/* internally used by simple q record */
	public function update($token, $status): bool
	{
		if (!array_key_exists($status, $this->status_map)) {
			throw new SimpleQException('Unknown Simple Q record status "' . $status . '".');
		}

		return $this->db->limit(1)->update($this->table, ['token' => null, 'updated' => date('Y-m-d H:i:s'), 'status' => $this->status_map[$status]], ['token' => $token]);
	}

	public function complete(string $token): bool
	{
		return $this->update($token, 'processed');
	}

	public function new(string $token): bool
	{
		return $this->update($token, 'new');
	}

	public function error(string $token): bool
	{
		return $this->update($token, 'error');
	}

	/* protected */

	protected function encode($data): string
	{
		$payload = new stdClass;

		if (is_object($data)) {
			$payload->type = 'object';
		} elseif (is_scalar($data)) {
			$payload->type = 'scalar';
		} elseif (is_array($data)) {
			$payload->type = 'array';
		} else {
			throw new SimpleQException('Could not encode Simple Q data.');
		}

		$payload->data = $data;
		$payload->checksum = $this->create_checksum($data);

		return json_encode($payload, $this->json_options);
	}

	protected function decode($record)
	{
		$payload_record = json_decode($record->payload, false);

		switch ($payload_record->type) {
			case 'object':
				$data = $payload_record->data;
				break;
			case 'array':
				$data = (array) $payload_record->data;
				break;
			case 'scalar':
				$data = $payload_record->data;
				break;
			default:
				throw new SimpleQException('Could not determine Simple Q data type.');
		}

		if (!$this->check_checksum($payload_record->checksum, $data)) {
			throw new SimpleQException('Simple Q data checksum failed.');
		}

		return $data;
	}

	protected function create_checksum($payload)
	{
		return crc32(json_encode($payload, $this->json_options));
	}

	protected function check_checksum(string $checksum, $payload)
	{
		return ($this->create_checksum($payload) == $checksum);
	}
} /* end class */
