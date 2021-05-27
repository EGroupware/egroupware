<?php
/**
 * EGroupware API: Caching provider storing data in PHP's APCu extension
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage cache
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2010-16 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @version $Id$
 */

namespace EGroupware\Api\Cache;

/**
 * Caching provider storing data in PHP's APCu extension / shared memory.
 *
 * The provider concats all $keys with '::' to get a single string.
 *
 * This provider is used by default, if it is available or explicit enabled in your header.inc.php:
 * $GLOBALS['egw_info']['server']['cache_provider_instance'] = array('EGroupware\Api\Cache\Apc');
 * and optional also $GLOBALS['egw_info']['server']['cache_provider_tree'] (defaults to instance)
 *
 * APC(u) and CLI:
 * --------------
 * APC(u) is not enabled by default for CLI (apc.enable_cli), nor would it access same shared memory!
 * It makes no sense to fall back to files cache, as this is probably quite outdated,
 * if PHP via Webserver uses APC. Better to use no cache at all.
 * Api\Cache::get*() will return NULL for not found and Api\Cache::[un]set*()
 * false for not being able to (un)set anything.
 * It also does not make sense to report failure by throwing an Exception and filling
 * up cron logs.
 * --> if APC(u) is available for Webserver, we report availability for CLI too,
 *     but use no cache at all!
 */
class Apcu extends Base implements Provider
{
	/**
	 * Constructor, eg. opens the connection to the backend
	 *
	 * @throws Exception if connection to backend could not be established
	 * @param array $params eg. array('localhost'[,'localhost:11211',...])
	 */
	function __construct(array $params)
	{
		if (!function_exists('apcu_fetch'))	// apc >= 3.0
		{
			throw new Exception (__METHOD__.'('.array2string($params).") No function apcu_fetch()!");
		}
	}

	/**
	 * Check if APC is available for caching user data
	 *
	 * Default shared memory size of 32M, which is used only for user data in APCu.
	 * Unlike APC which shares the total memory with it's opcode cache 32M is ok
	 * for a small install.
	 *
	 * @return boolean true: apc available, false: not
	 */
	public static function available()
	{
		if (($available = (bool)ini_get('apc.enabled') && function_exists('apcu_fetch')))
		{
			$size = ini_get('apc.shm_size');

			switch(strtoupper(substr($size, -1)))
			{
				case 'G':
					$size *= 1024;
				case 'M':
					$size *= 1024;
				case 'K':
					$size *= 1024;
			}
			$size *= ini_get('apc.shm_segments');

			// only cache in APCu, if we have at least 32M available (default is 32M)
			$available = $size >= 33554432;
		}
		//error_log(__METHOD__."() size=$size returning ".array2string($available));
		return $available;
	}

	/**
	 * Stores some data in the cache, if it does NOT already exists there
	 *
	 * @param array $keys eg. array($level,$app,$location)
	 * @param mixed $data
	 * @param int $expiration =0
	 * @return boolean true on success, false on error, incl. key already exists in cache
	 */
	function add(array $keys,$data,$expiration=0)
	{
		return apcu_add(self::key($keys),$data,$expiration);
	}

	/**
	 * Stores some data in the cache
	 *
	 * @param array $keys eg. array($level,$app,$location)
	 * @param mixed $data
	 * @param int $expiration =0
	 * @return boolean true on success, false on error
	 */
	function set(array $keys,$data,$expiration=0)
	{
		return apcu_store(self::key($keys),$data,$expiration);
	}

	/**
	 * Get some data from the cache
	 *
	 * @param array $keys eg. array($level,$app,$location)
	 * @return mixed data stored or NULL if not found in cache
	 */
	function get(array $keys)
	{
		$success = null;
		$data = apcu_fetch($key=self::key($keys),$success);

		if (!$success)
		{
			//error_log(__METHOD__."(".array2string($keys).") key='$key' NOT found!");
			return null;
		}
		//error_log(__METHOD__."(".array2string($keys).") key='$key' found ".bytes(serialize($data))." bytes).");
		return $data;
	}

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
		$key = self::key($keys);
		if ($intial_value !== 0 && !apcu_exists($key))
		{
			return apcu_store($key, $intial_value, $expiration) ? $intial_value : false;
		}
		return apcu_inc($key, $offset, $success, $expiration);
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
		$key = self::key($keys);
		if ($intial_value !== 0 && !apcu_exists($key))
		{
			return apcu_store($key, $intial_value, $expiration) ? $intial_value : false;
		}
		if (($value = apcu_dec($key, $offset, $success, $expiration)) < 0)
		{
			$value = apcu_store($key, 0, $expiration) ? 0 : false;
		}
		return $value;
	}

	/**
	 * Delete some data from the cache
	 *
	 * @param array $keys eg. array($level,$app,$location)
	 * @return boolean true on success, false on error (eg. $key not set)
	 */
	function delete(array $keys)
	{
		return apcu_delete(self::key($keys));
	}

	/**
	 * Delete all data under given keys
	 *
	 * If no keys are given whole APCu cache is cleared, which should allways
	 * work and can not run out of memory as the iterator sometimes does.
	 *
	 * @param array $keys eg. array($level,$app,$location) or array() to clear whole cache
	 * @return boolean true on success, false on error (eg. on iterator available)
	 */
	function flush(array $keys)
	{
		// do NOT try instanciating APCuIterator, if APCu is not enabled, as it gives a PHP Fatal Error
		if (!ini_get('apc.enabled') || php_sapi_name() === 'cli' && !ini_get('apc.cli_enabled'))
		{
			return false;
		}
		if (!$keys && function_exists('apcu_clear_cache'))
		{
			apcu_clear_cache();

			return true;
		}
		// APCu > 5 has APCUIterator
		if (class_exists('APCUIterator'))
		{
			$iterator = new \APCUIterator($preg='/^'.preg_quote(self::key($keys), '/').'/');
		}
		// APC >= 3.1.1, but also seems to be missing if apc is disabled eg. for cli
		elseif(class_exists('APCIterator'))
		{
			$iterator = new \APCIterator('user', $preg='/^'.preg_quote(self::key($keys), '/'), '/');
		}
		else
		{
			if (function_exists('apcu_clear_cache')) apcu_clear_cache();

			return false;
		}
		foreach($iterator as $item)
		{
			//error_log(__METHOD__."(".array2string($keys).") preg='$preg': calling apcu_delete('$item[key]')");
			apcu_delete($item['key']);
		}
		return true;
	}

	/**
	 * Create a single key from $keys
	 *
	 * @param array $keys
	 * @return string
	 */
	private static function key(array $keys)
	{
		return implode('::',$keys);
	}
}
