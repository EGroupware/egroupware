<?php
/**
 * EGroupware API: Caching provider storing data in memcached via PHP's memcached extension
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

/**
 * Caching provider storing data in memcached via PHP's memcached extension
 *
 * The provider concats all $keys with '::' to get a single string.
 *
 * To use this provider set in your header.inc.php:
 * $GLOBALS['egw_info']['server']['cache_provider_instance'] = array('EGroupware\Api\Cache\Memcached','localhost'[,'otherhost:port']);
 * and optional also $GLOBALS['egw_info']['server']['cache_provider_tree'] (defaults to instance)
 *
 * You can set more then one server and specify a port, if it's not the default one 11211.
 *
 * It allows addtional named parameters "timeout" (default 20ms), "retry" (default not) and "prefix".
 *
 * If igbinary extension is available, it is prefered over PHP (un)serialize.
 */
class Memcached extends Base implements ProviderMultiple
{
	/**
	 * Instance of \Memcached
	 *
	 * @var \Memcached
	 */
	private $memcache;

	/**
	 * Timeout in ms
	 */
	private $timeout = 20;

	/**
	 * Use Libketama consistent hashing
	 *
	 * Off by default as for just 2 Memcached servers it creates an extreme
	 * unbalanced distribution favoring the 2. server and has no benefits
	 * as requests to the failed node can only go to the other one anyway.
	 *
	 * @var boolean
	 */
	private $consistent = false;

	/**
	 * Retry on node failure: 0: no retry, 1: retry on set/add/delete, 2: always retry
	 *
	 * @var retry
	 */
	private $retry = 0;

	/**
	 * Constructor, eg. opens the connection to the backend
	 *
	 * @throws Exception if connection to backend could not be established
	 * @param array $params eg. array('localhost'[,'localhost:11211',...])
	 *	"timeout" in ms, "retry" on node failure 0: no retry (default), 1: retry on set/add/delete, 2: allways retry
	 *  "prefix" prefix for keys and "consistent=1" to enable (by default disabled) consistent caching
	 */
	function __construct(array $params=null)
	{
		$this->params = $params ? $params : array('localhost');	// some reasonable default

		if (isset($params['timeout']))
		{
			$this->timeout = (int)$params['timeout'];
			unset($params['timeout']);
		}
		if (isset($params['retry']))
		{
			$this->retry = (int)$params['retry'];
			unset($params['retry']);
		}
		if (isset($params['prefix']))
		{
			$prefix = $params['prefix'];
			unset($params['prefix']);
		}
		if (isset($params['consistent']))
		{
			$this->consistent = !empty($params['consistent']);
		}

		check_load_extension('memcached',true);
		// using a persitent connection for identical $params
		$this->memcache = new \Memcached(md5(serialize($params)));

		$this->memcache->setOptions(array(
			// setting a short timeout, to better kope with failed nodes
			\Memcached::OPT_CONNECT_TIMEOUT => $this->timeout,
			\Memcached::OPT_SEND_TIMEOUT => $this->timeout,
			\Memcached::OPT_RECV_TIMEOUT => $this->timeout,
			// use more efficient binary protocol (also required for consistent hashing)
			\Memcached::OPT_BINARY_PROTOCOL => true,
			// Libketama compatible consistent hashing
			\Memcached::OPT_LIBKETAMA_COMPATIBLE => $this->consistent,
			// automatic failover and disabling of failed nodes
			\Memcached::OPT_SERVER_FAILURE_LIMIT => 2,
			// setting a prefix for all keys
			\Memcached::OPT_PREFIX_KEY => $prefix,
		));
		// automatic disabling of failed nodes
		if (@constant('Memcached::OPT_AUTO_EJECT_HOSTS'))
		{
			$this->memcache->setOption(\Memcached::OPT_AUTO_EJECT_HOSTS, true);
		}
		// use igbinary, if available
		if (\Memcached::HAVE_IGBINARY)
		{
			$this->memcache->setOption(\Memcached::OPT_SERIALIZER, \Memcached::SERIALIZER_IGBINARY);
		}
		elseif(\Memcached::HAVE_JSON)
		{
			$this->memcache->setOption(\Memcached::OPT_SERIALIZER, \Memcached::SERIALIZER_JSON);
		}

		// with persistent connections, only add servers, if they not already added!
		if (!count($this->memcache->getServerList()))
		{
			$ok = false;
			foreach($params as $host_port)
			{
				$parts = explode(':',$host_port);
				$host = array_shift($parts);
				$port = $parts ? array_shift($parts) : 11211;	// default port

				$ok = $this->memcache->addServer($host,$port) || $ok;
				//error_log(__METHOD__."(".array2string($params).") memcache->addServer('$host',$port) = ".(int)$ok);
			}
			if (!$ok)
			{
				throw new Exception (__METHOD__.'('.array2string($params).") Can't open connection to any memcached server!");
			}
			//error_log(__METHOD__."(".array2string($params).") creating new pool / persitent connection");
		}
		//else error_log(__METHOD__."(".array2string($params).") using existing pool / persitent connection");
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
		return $this->memcache->add(self::key($keys), $data, $expiration) ||
			// if we have multiple nodes, retry on error, but not on data exists
			$this->retry > 0 && $this->memcache->getResultCode() !== \Memcached::RES_DATA_EXISTS &&
				$this->memcache->add(self::key($keys), $data, $expiration);
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
		return $this->memcache->set(self::key($keys), $data, $expiration) ||
			// if we have multiple nodes, retry on error
			$this->retry > 0 && $this->memcache->set(self::key($keys), $data, $expiration);
	}

	/**
	 * Get some data from the cache
	 *
	 * @param array $keys eg. array($level,$app,$location)
	 * @return mixed data stored or NULL if not found in cache
	 */
	function get(array $keys)
	{
		if (($data = $this->memcache->get($key=self::key($keys))) === false &&
			$this->memcache->getResultCode() !== \Memcached::RES_SUCCESS ||
			// if we have multiple nodes, retry on error, but not on not found
			$this->retry > 1 && $this->memcache->getResultCode() !== \Memcached::RES_NOTFOUND &&
			($data = $this->memcache->get($key=self::key($keys))) === false &&
			$this->memcache->getResultCode() !== \Memcached::RES_SUCCESS)
		{
			//error_log(__METHOD__."(".array2string($keys).") key='$key' NOT found!".' $this->memcache->getResultCode()='.$this->memcache->getResultCode().')');
			return null;
		}
		//error_log(__METHOD__."(".array2string($keys).") key='$key' found ".bytes($data)." bytes).");
		return $data;
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
		if (($multiple = $this->memcache->getMulti($locations)) === false ||
			// if we have multiple nodes, retry on error, but not on not found
			$this->retry > 1 && $this->memcache->getResultCode() !== \Memcached::RES_NOTFOUND &&
			($multiple = $this->memcache->getMulti($locations)) === false)
		{
			return array();
		}
		$ret = array();
		$prefix_len = strlen($prefix)+2;
		foreach($multiple as $location => $data)
		{
			$key = substr($location,$prefix_len);
			//error_log(__METHOD__."(".array2string($locations).") key='$key' found ".bytes($data)." bytes).");
			$ret[$key] = $data;
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
		return $this->memcache->delete(self::key($keys)) ||
			$this->retry > 0 && $this->memcache->getResultCode() !== \Memcached::RES_NOTFOUND &&
			$this->memcache->delete(self::key($keys));
	}

	/**
	 * Increments value in cache
	 *
	 * @param array $keys
	 * @param int $offset =1 how much to increment by
	 * @param int $intial_value =0 value to set and return, if not in cache
	 * @param int $expiration =0
	 * @return false|int new value on success, false on error
	 */
	function increment(array $keys, int $offset=1, int $intial_value=0, int $expiration=0)
	{
		$ret = $this->memcache->increment($key=self::key($keys), $offset, $intial_value, $expiration);
		if ($ret === false && $this->retry > 0)
		{
			$ret = $this->memcache->increment($key, $offset, $intial_value, $expiration);
		}
		// fix memcached returning strings
		return $ret !== false ? (int)$ret : $ret;
	}

	/**
	 * Decrements value in cache, but never below 0
	 *
	 * If new value would be below 0, 0 will be set as new value!
	 *
	 * @param array $keys
	 * @param int $offset =1 how much to increment by
	 * @param int $intial_value =0 value to set and return, if not in cache
	 * @param int $expiration =0
	 * @return false|int new value on success, false on error
	 */
	function decrement(array $keys, int $offset=1, int $intial_value=0, int $expiration=0)
	{
		$ret = $this->memcache->decrement($key=self::key($keys), $offset, $intial_value, $expiration);
		if ($ret === false && $this->retry > 0)
		{
			$ret = $this->memcache->decrement($key, $offset, $intial_value, $expiration);
		}
		// fix memcached returning strings
		return $ret !== false ? (int)$ret : $ret;
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
