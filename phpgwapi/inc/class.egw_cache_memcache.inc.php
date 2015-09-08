<?php
/**
 * EGroupware API: Caching provider storing data in memcached via PHP's memcache extension
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage cache
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2009-12 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @version $Id$
 */

/**
 * Caching provider storing data in memcached via PHP's memcache extension
 *
 * The provider concats all $keys with '::' to get a single string.
 *
 * To use this provider set in your header.inc.php:
 * $GLOBALS['egw_info']['server']['cache_provider_instance'] = array('egw_cache_memcache','localhost'[,'otherhost:port']);
 * and optional also $GLOBALS['egw_info']['server']['cache_provider_tree'] (defaults to instance)
 *
 * You can set more then one server and specify a port, if it's not the default one 11211.
 */
class egw_cache_memcache extends egw_cache_provider_check implements egw_cache_provider_multiple
{
	/**
	 * Instance of Memcache
	 *
	 * @var Memcache
	 */
	private $memcache;

	/**
	 * Flags used to store content
	 *
	 */
	const STORE_FLAGS = MEMCACHE_COMPRESSED;

	/**
	 * Constructor, eg. opens the connection to the backend
	 *
	 * @throws Exception if connection to backend could not be established
	 * @param array $params eg. array('localhost'[,'localhost:11211',...])
	 */
	function __construct(array $params)
	{
		check_load_extension('memcache',true);
		$this->memcache = new Memcache();

		if (!$params) $params = array('localhost');	// some reasonable default

		$ok = false;
		foreach($params as $host_port)
		{
			list($host,$port) = explode(':',$host_port);
			if (!$port) $port = 11211;	// default port

			$ok = $this->memcache->addServer($host,$port) || $ok;
			//error_log(__METHOD__."(".array2string($params).") memcache->addServer('$host',$port) = ".(int)$ok);
		}
		if (!$ok)
		{
			throw new Exception (__METHOD__.'('.array2string($params).") Can't open connection to any memcached server!");
		}
	}

	/**
	 * Stores some data in the cache
	 *
	 * @param array $keys eg. array($level,$app,$location)
	 * @param mixed $data
	 * @param int $expiration=0
	 * @return boolean true on success, false on error
	 */
	function set(array $keys,$data,$expiration=0)
	{
		return $this->memcache->set(self::key($keys),serialize($data),self::STORE_FLAGS,$expiration);
	}

	/**
	 * Get some data from the cache
	 *
	 * @param array $keys eg. array($level,$app,$location)
	 * @return mixed data stored or NULL if not found in cache
	 */
	function get(array $keys)
	{
		if (($data = $this->memcache->get($key=self::key($keys))) === false)
		{
			//error_log(__METHOD__."(".array2string($keys).") key='$key' NOT found!");
			return null;
		}
		//error_log(__METHOD__."(".array2string($keys).") key='$key' found ".bytes($data)." bytes).");
		return unserialize($data);
	}

	/**
	 * Get multiple data from the cache
	 *
	 * @param array $keys eg. array of array($level,$app,array $locations)
	 * @return array key => data stored, not found keys are NOT returned
	 */
	function mget(array $keys)
	{
		$locations = array_pop($keys);
		$prefix = self::key($keys);
		foreach($locations as &$location)
		{
			$location = $prefix.'::'.$location;
		}
		if (($multiple = $this->memcache->get($locations)) === false)
		{
			return array();
		}
		$ret = array();
		$prefix_len = strlen($prefix)+2;
		foreach($multiple as $location => $data)
		{
			$key = substr($location,$prefix_len);
			//error_log(__METHOD__."(".array2string($locations).") key='$key' found ".bytes($data)." bytes).");
			$ret[$key] = unserialize($data);
		}
		return $ret;
	}

	/**
	 * Delete some data from the cache
	 *
	 * @param array $keys eg. array($level,$app,$location)
	 * @return boolean true on success, false on error (eg. $key not set)
	 */
	function delete(array $keys)
	{
		// ,0 required to fix a bug in memcache extension < 3.1.1 with memcache > 1.4.3, eg. Debian 6
		// see https://bugs.php.net/bug.php?id=58943
		return $this->memcache->delete(self::key($keys), 0);
	}

	/**
	 * Create a single key from $keys
	 *
	 * @param array $keys
	 * @return string
	 */
	private function key(array $keys)
	{
		return implode('::',$keys);
	}
}
