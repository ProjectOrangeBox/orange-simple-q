<?php
/*
$config = [
	'name' => 'Don',
	'age' => 23,
	'pet' => ['dog', 'Jake'],
	'food' => 'pizza',
	'male' => true,
	'remove' => true,
];

$options = [
	'name' => ['Don', 'is_string'],
	'age' => [18, 'is_int'],
	'pet' => [['cat', 'Meow'], 'is_array'],
	'food' => ['cookie', 'is_string'],
	'friend' => ['Jane', 'is_string'],
	'male' => [null, 'is_bool'],
];

$config = configMerge($config, $options);

var_dump($config);
*/

function configMerge(array &$config, array $options, $attach = false): array
{
	/* remove unknown */
	$config = array_intersect_key($config, array_flip(array_keys($options)));

	foreach ($options as $name => $option) {
		/* fill in missing */
		if (!isset($config[$name])) {
			$config[$name] = $option[0];
		}

		/* validate data */
		if (isset($option[1])) {
			$function = $option[1];

			if (substr($function, 0, 1) == '\\') {
				$class = substr($function, 1);

				if (!($config[$name] instanceof $class)) {
					throw new \Exception($name . ' not instance of ' . $class . ' validation failed.');
				}
			} else {
				if (!$function($config[$name])) {
					throw new \Exception($name . ' ' . $function . ' validation failed.');
				}
			}
		}

		if (isset($option[2])) {
			$name = $option[2];
			$this->$name = $config[$name];
		}
	}


	return $config;
}
