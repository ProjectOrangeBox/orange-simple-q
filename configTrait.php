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

trait configTrait
{
	protected $config = [];

	public function mergeConfiguration(array $config, array $options): void
	{
		$notRealValue = '##__NOT_REAL_VALUE__##';

		$options = array_replace(['is_string', null, $notRealValue], $options);

		$validationFunction = 0;
		$realClassProperty = 1;
		$defaultValueIfEmpty = 2;

		foreach ($options as $configKeyName => $options) {
			$realName = ($options[$realClassProperty] === true) ? $configKeyName : $options[$realClassProperty];

			/* fill in missing */
			if (!isset($config[$configKeyName])) {
				if ($options[$defaultValueIfEmpty] != $notRealValue) {
					$config[$configKeyName] = $options[$defaultValueIfEmpty];
				} else {
					if (!property_exists($this, $realName)) {
						throw new \Exception('Class property "' . $realName . '" does not exist');
					}

					$config[$configKeyName] = $this->$realName;
				}
			}

			/* validate data */
			$valadateRule = $options[$validationFunction];

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
				if (!property_exists($this, $realName)) {
					throw new \Exception('Class property "' . $realName . '" does not exist');
				}

				$this->$realName = $config[$configKeyName];
			}
		}

		$this->config = $config;
	}
} /* end class */
