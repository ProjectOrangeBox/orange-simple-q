<?php

declare(strict_types=1);

namespace base;

/**
 * $config is a key / value pair of configuration values
 *
 * $options are a key value pair for the configuration values
 *
 * The key matches the configuration key to test
 *
 * The value is an array which contains between 1 and 3 additional values
 *
 * index 0 is a PHP function to validate the value such as is_array, is_bool, is_string, is_int
 * this may also contain a namespaced class such as \PDO
 *
 * index 1 is the name of real class property to set this value for ie. 'tablename'
 * if this contains a boolean true it will use the array key
 * if this contains null it will not be attached to a class property but will still be in the configuration array
 *
 * index 2 is a default value. If this is provided this will
 * override any other value set if this configuration key is not included
 *
 */

trait traitConfigMerge
{
	protected $config = [];

	public function mergeConfiguration(array $config, array $options): void
	{
		foreach ($options as $configKeyName => $options) {
			$realName = null;

			if (isset($options[1])) {
				if ($options[1] === true) {
					$realName = $configKeyName;
				} else {
					$realName = $options[1];
				}
			}

			$defaultValue = ($options[2]) ?? null;

			/* fill in missing */
			if (!isset($config[$configKeyName])) {
				$config[$configKeyName] = ($defaultValue) ? $defaultValue : $this->$realName;
			}

			/* validate data */
			$valadateRule = ($options[0]) ?? null;

			if (substr($valadateRule, 0, 1) == '\\') {
				$class = substr($valadateRule, 1);

				if (!($config[$configKeyName] instanceof $class)) {
					throw new \Exception($configKeyName . ' not instance of ' . $class . ' validation failed.');
				}
			} else {
				if (!$valadateRule($config[$configKeyName])) {
					throw new \Exception($configKeyName . ' ' . $valadateRule . ' validation failed.');
				}
			}

			/* attach to class properties */
			if ($realName) {
				$this->$realName = $config[$configKeyName];
			}
		}

		$this->config = $config;
	}
} /* end class */
