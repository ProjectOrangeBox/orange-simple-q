<?php
/**
*
* This content is released under the MIT License (MIT)
*
* @author	Don Myers
* @license	http://opensource.org/licenses/MIT	MIT License
* @link	https://github.com/ProjectOrangeBox
*/

class ssl {
	public static function create($bits=2048,$folder=null) {
		$folder = ($folder) ? rtrim($folder,'/') : __DIR__;

		$public = $folder.'/public.key';
		$private = $folder.'/private.key';

		if (!is_writable($folder)) {
			throw new Exception('folder is not writable');
		}

		$config = [
			'private_key_bits' => $bits,
			'private_key_type' => OPENSSL_KEYTYPE_RSA,
		];

		$private_key = openssl_pkey_new($config);

		openssl_pkey_export_to_file($private_key,$private);

		$public_key = openssl_pkey_get_details($private_key);

		file_put_contents($public, $public_key['key']);

		openssl_free_key($private_key);
	}

	public static function encrypt($data,$key_file=null) {
		$file = ($key_file) ? $key_file : __DIR__.'/public.key';

		if (!file_exists($file)) {
			throw new Exception('Count not locate '.basename($file));
		}

		if (!$key = openssl_pkey_get_public('file://'.$file)) {
			throw new Exception('Could not get public key');
		}

		$details = openssl_pkey_get_details($key);

		$length = ceil($details['bits'] / 8) - 11;

		$output = '';

		while($data) {
			$chunk = substr($data, 0, $length);
			$data = substr($data, $length);
			$encrypted = '';

			if (!openssl_public_encrypt($chunk, $encrypted, $key)) {
				throw new Exception('Failed to encrypt data');
			}

			$output .= $encrypted;
		}

		openssl_free_key($key);

		return $output;
	}

	public static function decrypt($data,$key_file=null) {
		$file = ($key_file) ? $key_file : __DIR__.'/private.key';

		if (!file_exists($file)) {
			throw new Exception('Count not locate '.basename($file));
		}

		if (!$key = openssl_pkey_get_private('file://'.$file)) {
			throw new Exception('Could not get private key');
		}

		$details = openssl_pkey_get_details($key);

		$length = ceil($details['bits'] / 8);

		$output = '';

		while($data) {
			$chunk = substr($data, 0, $length);
			$data = substr($data, $length);
			$decrypted = '';

			if (!openssl_private_decrypt($chunk, $decrypted, $key)) {
				throw new Exception('Failed to decrypt data');
			}

			$output .= $decrypted;
		}

		openssl_free_key($key);

		return $output;
	}

} /* end class */
