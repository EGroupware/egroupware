<?php
/**
 * EGroupware API: Base class for all caching providers
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage cache
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2009-16 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @version $Id$
 */

namespace EGroupware\Api\Cache;

/*if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__)
{
	require_once dirname(__DIR__).'/loader/common.php';
}*/
use EGroupware\Api;

/**
 * Base class for all caching providers
 *
 * Implements some checks used for testing providers.
 */
abstract class Base implements Provider
{
	/**
	 * Run several checks on a caching provider
	 *
	 * @param boolean $verbose =false true: echo failed checks
	 * @return int number of failed checks
	 */
	function check($verbose=false)
	{
		// set us up as provider for Api\Cache class
		$GLOBALS['egw_info']['server']['install_id'] = md5(microtime(true));
		unset($GLOBALS['egw_info']['server']['cache_provider_instance']);
		unset($GLOBALS['egw_info']['server']['cache_provider_tree']);
		Api\Cache::$default_provider = get_class($this);

		$failed = 0;
		foreach(array(
			Api\Cache::TREE => 'tree',
			Api\Cache::INSTANCE => 'instance',
		) as $level => $label)
		{
			$locations = array();
			foreach(array('string',123,true,false,null,array(),array(1,2,3)) as $data)
			{
				$location = md5(microtime(true).$label.serialize($data));
				$get_before_set = $this->get(array($level,__CLASS__,$location));
				if (!is_null($get_before_set))
				{
					if ($verbose) echo "$label: get_before_set=".array2string($get_before_set)." != NULL\n";
					++$failed;
				}
				if (($set = $this->set(array($level,__CLASS__,$location), $data, 10)) !== true)
				{
					if ($verbose) echo "$label: set returned ".array2string($set)." !== TRUE\n";
					++$failed;
				}
				$get_after_set = $this->get(array($level,__CLASS__,$location));
				if ($get_after_set !== $data)
				{
					if ($verbose) echo "$label: get_after_set=".array2string($get_after_set)." !== ".array2string($data)."\n";
					++$failed;
				}
				if (is_a($this, 'EGroupware\Api\Cache\ProviderMultiple'))
				{
					$mget_after_set = $this->mget(array($level,__CLASS__,array($location)));
					if ($mget_after_set[$location] !== $data)
					{
						if ($verbose) echo "$label: mget_after_set['$location']=".array2string($mget_after_set[$location])." !== ".array2string($data)."\n";
						++$failed;
					}
				}
				$add_after_set = $this->add(array($level,__CLASS__,$location), 'other-data');
				if ($add_after_set !== false)
				{
					if ($verbose) echo "$label: add_after_set=".array2string($add_after_set)."\n";
					++$failed;
				}
				if (($delete = $this->delete(array($level,__CLASS__,$location))) !== true)
				{
					if ($verbose) echo "$label: delete returned ".array2string($delete)." !== TRUE\n";
					++$failed;
				}
				$get_after_delete = $this->get(array($level,__CLASS__,$location));
				if (!is_null($get_after_delete))
				{
					if ($verbose) echo "$label: get_after_delete=".array2string($get_after_delete)." != NULL\n";
					++$failed;
				}
				// prepare for mget of everything
				if (is_a($this, 'EGroupware\Api\Cache\ProviderMultiple'))
				{
					$locations[$location] = $data;
					$mget_after_delete = $this->mget(array($level,__CLASS__,array($location)));
					if (isset($mget_after_delete[$location]))
					{
						if ($verbose) echo "$label: mget_after_delete['$location']=".array2string($mget_after_delete[$location])." != NULL\n";
						++$failed;
					}
				}
				elseif (!is_null($data))	// emulation can NOT distinguish between null and not set
				{
					$locations[$location] = $data;
				}
				$add_after_delete = $this->add(array($level,__CLASS__,$location), $data, 10);
				if ($add_after_delete !== true)
				{
					if ($verbose) echo "$label: add_after_delete=".array2string($add_after_delete)."\n";
					++$failed;
				}
				else
				{
					$get_after_add = $this->get(array($level,__CLASS__,$location));
					if ($get_after_add !== $data)
					{
						if ($verbose) echo "$label: get_after_add=".array2string($get_after_add)." !== ".array2string($data)."\n";
						++$failed;
					}
				}
			}
			// get all above in one request
			$keys = array_keys($locations);
			$keys_bogus = array_merge(array('not-set'),array_keys($locations),array('not-set-too'));
			if (is_a($this, 'EGroupware\Api\Cache\ProviderMultiple'))
			{
				$mget = $this->mget(array($level,__CLASS__,$keys));
				$mget_bogus = $this->mget(array($level,__CLASS__,$keys_bogus));
			/* Api\Cache::getCache() gives a different result, as it does NOT use $level direkt
			}
			else
			{
				$mget = Api\Cache::getCache($level, __CLASS__, $keys);
				$mget_bogus = Api\Cache::getCache($level, __CLASS__, $keys_bogus);
			}*/
				if ($mget !== $locations)
				{
					if ($verbose) echo "$label: mget=\n".array2string($mget)." !==\n".array2string($locations)."\n";
					++$failed;
				}
				if ($mget_bogus !== $locations)
				{
					if ($verbose) echo "$label: mget(".array2string($keys_bogus).")=\n".array2string($mget_bogus)." !==\n".array2string($locations)."\n";
					++$failed;
				}
			}

			// test increment
			$keys = [$level, __CLASS__, 'increment'];
			$this->delete($keys);

			if (($val=$this->increment($keys, 3, 8)) !== 8)
			{
				if ($verbose) echo "$label: increment(\$keys, 3, 8)=".array2string($val)." !== 8 for initial/unset \$keys\n";
				++$failed;
			}
			if (($val=$this->get($keys)) != 8)	// get always returns string!
			{
				if ($verbose) echo "$label: get(\$keys)=".array2string($val)." != 8 for reading back incremented value\n";
				++$failed;
			}
			if (($val=$this->increment($keys, 2, 5)) !== 10)
			{
				if ($verbose) echo "$label: increment(\$keys, 2, 5)=".array2string($val)." !== 10 for current \$keys === 8\n";
				++$failed;
			}
			if (($val=$this->get($keys)) != 10)
			{
				if ($verbose) echo "$label: get(\$keys)=".array2string($val)." != 10 for reading back incremented value\n";
				++$failed;
			}

			// test decrement
			$keys = [$level, __CLASS__, 'decrement'];
			$this->delete($keys);

			if (($val=$this->decrement($keys, 3, 2)) !== 2)
			{
				if ($verbose) echo "$label: decrement(\$keys, 3, 2)=".array2string($val)." !== 2 for initial/unset \$keys\n";
				++$failed;
			}
			if (($val=$this->get($keys)) != 2)
			{
				if ($verbose) echo "$label: get(\$keys)=".array2string($val)." != 2 for reading back decremented value\n";
				++$failed;
			}
			if (($val=$this->decrement($keys, 2, 5)) !== 0)
			{
				if ($verbose) echo "$label: decrement(\$keys, 2, 5)=".array2string($val)." !== 0 for current \$keys === 2\n";
				++$failed;
			}
			if (($val=$this->get($keys)) != 0)
			{
				if ($verbose) echo "$label: get(\$keys)=".array2string($val)." != 0 for reading back decremented value\n";
				++$failed;
			}
			if (($val=$this->decrement($keys, 2, 5)) !== 0)
			{
				if ($verbose) echo "$label: decrement(\$keys, 2, 5)=".array2string($val)." !== 0 for current \$keys === 0 (value never less then 0!)\n";
				++$failed;
			}
		}

		return $failed;
	}

	/**
	 * Delete all data under given keys
	 *
	 * Providers can return false, if they do not support flushing part of the cache (eg. memcache)
	 *
	 * @param array $keys eg. array($level,$app,$location)
	 * @return boolean true on success, false on error (eg. $key not set)
	 */
	function flush(array $keys)
	{
		unset($keys);	// required by function signature
		return false;
	}

	/**
	 * Stores some data in the cache
	 *
	 * @param array $keys eg. array($level,$app,$location)
	 * @param mixed $data
	 * @param int $expiration =0
	 * @return boolean true on success, false on error
	 */
	abstract function set(array $keys, $data, $expiration=0);

	/**
	 * Get some data from the cache
	 *
	 * @param array $keys eg. array($level,$app,$location)
	 * @return mixed data stored or NULL if not found in cache
	 */
	abstract function get(array $keys);

	/**
	 * Increments value in cache
	 *
	 * Default implementation emulating increment using get & set.
	 *
	 * @param array $keys
	 * @param int $offset =1 how much to increment by
	 * @param int $intial_value =0 value to set and return, if not in cache
	 * @param int $expiration =0
	 * @return false|int new value on success, false on error
	 */
	function increment(array $keys, int $offset=1, int $intial_value=0, int $expiration=0)
	{
		if (($value = $this->get($keys)) === false)
		{
			return $value;
		}
		if (!isset($value))
		{
			$value = $intial_value;
		}
		else
		{
			$value += $offset;
		}
		return $this->set($keys, $value, $expiration) ? $value : false;
	}

	/**
	 * Decrements value in cache, but never below 0
	 *
	 * If new value would be below 0, 0 will be set as new value!
	 * Default implementation emulating decrement using get & set.
	 *
	 * @param array $keys
	 * @param int $offset =1 how much to increment by
	 * @param int $intial_value =0 value to set and return, if not in cache
	 * @param int $expiration =0
	 * @return false|int new value on success, false on error
	 */
	function decrement(array $keys, int $offset=1, int $intial_value=0, int $expiration=0)
	{
		if (($value = $this->get($keys)) === false)
		{
			return $value;
		}
		if (!isset($value))
		{
			$value = $intial_value;
		}
		else
		{
			if (($value -= $offset) < 0) $value = 0;
		}
		return $this->set($keys, $value, $expiration) ? $value : false;
	}
}

// some testcode, if this file is called via it's URL
// can be run on command-line: sudo php -d apc.enable_cli=1 -f api/src/Cache/Base.php
/*if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__)
{
	if (!isset($_SERVER['HTTP_HOST']))
	{
		chdir(__DIR__);	// to enable our relative pathes to work
	}
	$GLOBALS['egw_info'] = array(
		'flags' => array(
			'noapi' => true,
		),
	);
	include_once '../../../header.inc.php';

	if (isset($_SERVER['HTTP_HOST'])) echo "<pre style='whitespace: nowrap'>\n";

	foreach(array(
		'EGroupware\Api\Cache\Apcu' => array(),
		'EGroupware\Api\Cache\Apc' => array(),
		'EGroupware\Api\Cache\Memcached' => array('localhost'),
		'EGroupware\Api\Cache\Memcache' => array('localhost'),
		'EGroupware\Api\Cache\Files' => array('/tmp'),
	) as $class => $param)
	{
		echo "Checking $class:\n";
		try {
			$start = microtime(true);
			$provider = new $class($param);
			$n = 100;
			for($i=1; $i <= $n; ++$i)
			{
				$failed = $provider->check($i == 1);
			}
			printf("$failed checks failed, $n iterations took %5.3f sec\n\n", microtime(true)-$start);
		}
		catch (\Exception $e) {
			printf($e->getMessage()."\n\n");
		}
	}
}*/
