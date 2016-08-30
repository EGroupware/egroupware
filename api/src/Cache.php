<?php
/**
 * EGroupware API: Caching data
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage cache
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2009-16 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @version $Id$
 */

namespace EGroupware\Api;

/**
 * Class to manage caching in eGroupware.
 *
 * It allows to cache on 4 levels:
 * a) tree:     for all instances/domains runining on a certain source path
 * b) instance: for all sessions on a given instance
 * c) session:  for all requests of a session, same as deprecated egw_session::appsession()
 * d) request:  just for this request (same as using a static variable)
 *
 * There's a get, a set and a unset method for each level: eg. getTree() or setInstance(),
 * as well as a variant allowing to specify the level as first parameter: eg. unsetCache()
 *
 * getXXX($app,$location,$callback=null,array $callback_params,$expiration=0)
 * has three optional parameters allowing to specify:
 * 3. a callback if requested data is not yes stored. In that case the callback is called
 *    and it's value is stored in the cache AND retured
 * 4. parameters to pass to the callback as array, see call_user_func_array
 * 5. an expiration time in seconds to specify how long data should be cached,
 *    default 0 means infinit (this time is not garantied and not supported for all levels!)
 *
 * Data is stored under an application name and a location, like egw_session::appsession().
 * In fact data stored at cache level Api\Cache::SESSION, is stored in the same way as
 * egw_session::appsession() so both methods can be used with each other.
 *
 * The $app parameter should be either the app or the class name, which both are unique.
 *
 * The tree and instance wide cache uses a certain provider class, to store the data
 * eg. in memcached or if there's nothing else configured in the filesystem (eGW's temp_dir).
 *
 * "Admin >> clear cache and register hooks" allways only clears instance level cache of
 * calling instance. It never clears tree level cache, which makes it important to set
 * resonable expiry times or think about an other means of clearing that particular item.
 * (Not clearing of tree-level cache is important, as regenerating it is an expensive
 * operation for a huge scale EGroupware hosting operation.)
 *
 * Apps needing to talk to multiple EGroupware instances (eg. Stylite Managementserver)
 * can use install_id of instance as $level parameter to (set|get|unset)Cache method.
 */
class Cache
{
	/**
	 * tree-wide storage
	 */
	const TREE = 'Tree';
	/**
	 * instance-wide storage
	 */
	const INSTANCE = 'Instance';
	/**
	 * session-wide storage
	 */
	const SESSION = 'Session';
	/**
	 * request-wide storage
	 */
	const REQUEST = 'Request';

	/**
	 * Default provider for tree and instance data
	 *
	 * Can be specified eg. in the header.inc.php by setting:
	 * $GLOBALS['egw_info']['server']['cache_provider_instance'] and optional
	 * $GLOBALS['egw_info']['server']['cache_provider_tree'] (defaults to instance)
	 *
	 * Default is set (if not set here) after class definition to Cache\Apc or Cache\Files,
	 * depending on function 'apc_fetch' exists or not
	 *
	 * @var array
	 */
	static $default_provider;	// = array('EGroupware\Api\Cache\Files');// array('EGroupware\Api\Cache\Memcache','localhost');

	/**
	 * Maximum expiration time, if set unlimited expiration (=0) or bigger expiration times are replaced with that time
	 *
	 * @var int
	 */
	static $max_expiration;

	/**
	 * Used to determine keys for tree- and instance-level caches
	 *
	 * @var string
	 */
	static $egw_server_root = EGW_SERVER_ROOT;

	/**
	 * Add some data in the cache, only if the key does not yet exist
	 *
	 * @param string $level use Api\Cache::(TREE|INSTANCE)
	 * @param string $app application storing data
	 * @param string $location location name for data
	 * @param mixed $data
	 * @param int $expiration =0 expiration time in seconds, default 0 = never
	 * @return boolean true if data could be stored, false otherwise incl. key already existed
	 */
	static public function addCache($level,$app,$location,$data,$expiration=0)
	{
		//error_log(__METHOD__."('$level','$app','$location',".array2string($data).",$expiration)");
		switch($level)
		{
			case self::SESSION:
			case self::REQUEST:
				throw new Exception\WrongParameter(__METHOD__."('$level', ...) unsupported level parameter!");

			case self::INSTANCE:
			case self::TREE:
			default:
				if (!($provider = self::get_provider($level)))
				{
					return false;
				}
				// limit expiration to configured maximum time
				if (isset(self::$max_expiration) && (!$expiration || $expiration > self::$max_expiration))
				{
					$expiration = self::$max_expiration;
				}
				return $provider->add(self::keys($level,$app,$location),$data,$expiration);
		}
		throw new Exception\WrongParameter(__METHOD__."() unknown level '$level'!");
	}

	/**
	 * Set some data in the cache
	 *
	 * @param string $level use Cache::(TREE|INSTANCE|SESSION|REQUEST)
	 * @param string $app application storing data
	 * @param string $location location name for data
	 * @param mixed $data
	 * @param int $expiration =0 expiration time in seconds, default 0 = never
	 * @return boolean true if data could be stored, false otherwise
	 */
	static public function setCache($level,$app,$location,$data,$expiration=0)
	{
		//error_log(__METHOD__."('$level','$app','$location',".array2string($data).",$expiration)");
		switch($level)
		{
			case self::SESSION:
			case self::REQUEST:
				return call_user_func(array(__CLASS__,'set'.$level),$app,$location,$data,$expiration);

			case self::INSTANCE:
			case self::TREE:
			default:
				if (!($provider = self::get_provider($level)))
				{
					return false;
				}
				// limit expiration to configured maximum time
				if (isset(self::$max_expiration) && (!$expiration || $expiration > self::$max_expiration))
				{
					$expiration = self::$max_expiration;
				}
				return $provider->set(self::keys($level,$app,$location),$data,$expiration);
		}
		throw new Exception\WrongParameter(__METHOD__."() unknown level '$level'!");
	}

	/**
	 * Get some data from the cache
	 *
	 * @param string $level use Api\Cache::(TREE|INSTANCE|SESSION|REQUEST)
	 * @param string $app application storing data
	 * @param string|array $location location(s) name for data
	 * @param callback $callback =null callback to get/create the value, if it's not cache
	 * @param array $callback_params =array() array with parameters for the callback
	 * @param int $expiration =0 expiration time in seconds, default 0 = never
	 * @return mixed NULL if data not found in cache (and no callback specified) or
	 * 	if $location is an array: location => data pairs for existing location-data, non-existing is not returned
	 */
	static public function getCache($level,$app,$location,$callback=null,array $callback_params=array(),$expiration=0)
	{
		switch($level)
		{
			case self::SESSION:
			case self::REQUEST:
				foreach((array)$location as $l)
				{
					$data[$l] = call_user_func(array(__CLASS__,'get'.$level),$app,$l,$callback,$callback_params,$expiration);
				}
				return is_array($location) ? $data : $data[$l];

			case self::INSTANCE:
			case self::TREE:
			default:
				if (!($provider = self::get_provider($level)))
				{
					return null;
				}
				try {
					if (is_array($location))
					{
						if (!is_null($callback))
						{
							throw new Exception\WrongParameter(__METHOD__."() you can NOT use multiple locations (\$location parameter is an array) together with a callback!");
						}
						if (is_a($provider, 'EGroupware\Api\Cache\ProviderMultiple'))
						{
							$data = $provider->mget($keys=self::keys($level,$app,$location));
						}
						else	// default implementation calls get multiple times
						{
							$data = array();
							foreach($location as $l)
							{
								$data[$l] = $provider->get($keys=self::keys($level,$app,$l));
								if (!isset($data[$l])) unset($data[$l]);
							}
						}
					}
					else
					{
						$data = $provider->get($keys=self::keys($level,$app,$location));
						if (is_null($data) && !is_null($callback))
						{
							$data = call_user_func_array($callback,$callback_params);
							// limit expiration to configured maximum time
							if (isset(self::$max_expiration) && (!$expiration || $expiration > self::$max_expiration))
							{
								$expiration = self::$max_expiration;
							}
							$provider->set($keys,$data,$expiration);
						}
					}
				}
				catch(Exception $e) {
					unset($e);
					$data = null;
				}
				return $data;
		}
		throw new Exception\WrongParameter(__METHOD__."() unknown level '$level'!");
	}

	/**
	 * Unset some data in the cache
	 *
	 * @param string $level use Api\Cache::(TREE|INSTANCE|SESSION|REQUEST)
	 * @param string $app application storing data
	 * @param string $location location name for data
	 * @return boolean true if data was set, false if not (like isset())
	 */
	static public function unsetCache($level,$app,$location)
	{
		switch($level)
		{
			case self::SESSION:
			case self::REQUEST:
				return call_user_func(array(__CLASS__,'unset'.$level),$app,$location);

			case self::INSTANCE:
			case self::TREE:
			default:
				if (!($provider = self::get_provider($level, false)))
				{
					return false;
				}
				return $provider->delete(self::keys($level,$app,$location));
		}
		throw new Exception\WrongParameter(__METHOD__."() unknown level '$level'!");
	}

	/**
	 * Set some data in the cache for the whole source tree (all instances)
	 *
	 * @param string $app application storing data
	 * @param string $location location name for data
	 * @param mixed $data
	 * @param int $expiration =0 expiration time in seconds, default 0 = never
	 * @return boolean true if data could be stored, false otherwise
	 */
	static public function setTree($app,$location,$data,$expiration=0)
	{
		//error_log(__METHOD__."('$app','$location',".array2string($data).",$expiration)");
		return self::setCache(self::TREE,$app,$location,$data,$expiration);
	}

	/**
	 * Get some data from the cache for the whole source tree (all instances)
	 *
	 * @param string $app application storing data
	 * @param string $location location name for data
	 * @param callback $callback =null callback to get/create the value, if it's not cache
	 * @param array $callback_params =array() array with parameters for the callback
	 * @param int $expiration =0 expiration time in seconds, default 0 = never
	 * @return mixed NULL if data not found in cache (and no callback specified)
	 */
	static public function getTree($app,$location,$callback=null,array $callback_params=array(),$expiration=0)
	{
		return self::getCache(self::TREE,$app,$location,$callback,$callback_params,$expiration);
	}

	/**
	 * Unset some data in the cache for the whole source tree (all instances)
	 *
	 * @param string $app application storing data
	 * @param string $location location name for data
	 * @return boolean true if data was set, false if not (like isset())
	 */
	static public function unsetTree($app,$location)
	{
		return self::unsetCache(self::TREE,$app,$location);
	}

	/**
	 * Set some data in the cache for the whole source tree (all instances)
	 *
	 * @param string $app application storing data
	 * @param string $location location name for data
	 * @param mixed $data
	 * @param int $expiration =0 expiration time in seconds, default 0 = never
	 * @return boolean true if data could be stored, false otherwise
	 */
	static public function setInstance($app,$location,$data,$expiration=0)
	{
		return self::setCache(self::INSTANCE,$app,$location,$data,$expiration);
	}

	/**
	 * Get some data from the cache for the whole source tree (all instances)
	 *
	 * @param string $app application storing data
	 * @param string $location location name for data
	 * @param callback $callback =null callback to get/create the value, if it's not cache
	 * @param array $callback_params =array() array with parameters for the callback
	 * @param int $expiration =0 expiration time in seconds, default 0 = never
	 * @return mixed NULL if data not found in cache (and no callback specified)
	 */
	static public function getInstance($app,$location,$callback=null,array $callback_params=array(),$expiration=0)
	{
		return self::getCache(self::INSTANCE,$app,$location,$callback,$callback_params,$expiration);
	}

	/**
	 * Unset some data in the cache for the whole source tree (all instances)
	 *
	 * @param string $app application storing data
	 * @param string $location location name for data
	 * @return boolean true if data was set, false if not (like isset())
	 */
	static public function unsetInstance($app,$location)
	{
		return self::unsetCache(self::INSTANCE,$app,$location);
	}

	/**
	 * Set some data in the cache for the whole source tree (all instances)
	 *
	 * @param string $app application storing data
	 * @param string $location location name for data
	 * @param mixed $data
	 * @param int $expiration =0 expiration time in seconds, default 0 = never
	 * @return boolean true if data could be stored, false otherwise
	 */
	static public function setSession($app,$location,$data,$expiration=0)
	{
		unset($expiration);	// not used, but required by function signature
		if (isset($_SESSION[Session::EGW_SESSION_ENCRYPTED]))
		{
			if (Session::ERROR_LOG_DEBUG) error_log(__METHOD__.' called after session was encrypted --> ignored!');
			return false;	// can no longer store something in the session, eg. because commit_session() was called
		}
		$_SESSION[Session::EGW_APPSESSION_VAR][$app][$location] = $data;

		return true;
	}

	/**
	 * Get some data from the cache for the whole source tree (all instances)
	 *
	 * Returns a reference to the var in the session!
	 *
	 * @param string $app application storing data
	 * @param string $location location name for data
	 * @param callback $callback =null callback to get/create the value, if it's not cache
	 * @param array $callback_params =array() array with parameters for the callback
	 * @param int $expiration =0 expiration time in seconds, default 0 = never
	 * @return mixed NULL if data not found in cache (and no callback specified)
	 */
	static public function &getSession($app,$location,$callback=null,array $callback_params=array(),$expiration=0)
	{
		unset($expiration);	// not used, but required by function signature
		if (isset($_SESSION[Session::EGW_SESSION_ENCRYPTED]))
		{
			if (Session::ERROR_LOG_DEBUG) error_log(__METHOD__.' called after session was encrypted --> ignored!');
			return null;	// can no longer store something in the session, eg. because commit_session() was called
		}
		if (!isset($_SESSION[Session::EGW_APPSESSION_VAR][$app][$location]) && !is_null($callback))
		{
			$_SESSION[Session::EGW_APPSESSION_VAR][$app][$location] = call_user_func_array($callback,$callback_params);
		}
		return $_SESSION[Session::EGW_APPSESSION_VAR][$app][$location];
	}

	/**
	 * Unset some data in the cache for the whole source tree (all instances)
	 *
	 * @param string $app application storing data
	 * @param string $location location name for data
	 * @return boolean true if data was set, false if not (like isset())
	 */
	static public function unsetSession($app,$location)
	{
		if (isset($_SESSION[Session::EGW_SESSION_ENCRYPTED]))
		{
			if (Session::ERROR_LOG_DEBUG) error_log(__METHOD__.' called after session was encrypted --> ignored!');
			return false;	// can no longer store something in the session, eg. because commit_session() was called
		}
		if (!isset($_SESSION[Session::EGW_APPSESSION_VAR][$app][$location]))
		{
			return false;
		}
		unset($_SESSION[Session::EGW_APPSESSION_VAR][$app][$location]);

		return true;
	}

	/**
	 * Static varible to cache request wide
	 *
	 * @var array
	 */
	private static $request_cache = array();

	/**
	 * Set some data in the cache for the whole source tree (all instances)
	 *
	 * @param string $app application storing data
	 * @param string $location location name for data
	 * @param mixed $data
	 * @param int $expiration =0 expiration time is NOT used for REQUEST!
	 * @return boolean true if data could be stored, false otherwise
	 */
	static public function setRequest($app,$location,$data,$expiration=0)
	{
		unset($expiration);	// not used, but required by function signature
		self::$request_cache[$app][$location] = $data;

		return true;
	}

	/**
	 * Get some data from the cache for the whole source tree (all instances)
	 *
	 * @param string $app application storing data
	 * @param string $location location name for data
	 * @param callback $callback =null callback to get/create the value, if it's not cache
	 * @param array $callback_params =array() array with parameters for the callback
	 * @param int $expiration =0 expiration time is NOT used for REQUEST!
	 * @return mixed NULL if data not found in cache (and no callback specified)
	 */
	static public function getRequest($app,$location,$callback=null,array $callback_params=array(),$expiration=0)
	{
		unset($expiration);	// not used, but required by function signature
		if (!isset(self::$request_cache[$app][$location]) && !is_null($callback))
		{
			self::$request_cache[$app][$location] = call_user_func_array($callback,$callback_params);
		}
		return self::$request_cache[$app][$location];
	}

	/**
	 * Unset some data in the cache for the whole source tree (all instances)
	 *
	 * @param string $app application storing data
	 * @param string $location location name for data
	 * @return boolean true if data was set, false if not (like isset())
	 */
	static public function unsetRequest($app,$location)
	{
		if (!isset(self::$request_cache[$app][$location]))
		{
			return false;
		}
		unset(self::$request_cache[$app][$location]);

		return true;
	}

	/**
	 * Get a caching provider for tree or instance level
	 *
	 * The returned provider already has an opened connection
	 *
	 * @param string $level Api\Cache::(TREE|INSTANCE) or install_id
	 * @param boolean $log_not_found =true false do not log if no provider found, used eg. to supress error via unsetCache during installation
	 * @return Api\Cache\Provider
	 */
	static protected function get_provider($level, $log_not_found=true)
	{
		static $providers = array();

		if ($level != self::TREE) $level = self::INSTANCE;

		if (!isset($providers[$level]))
		{
			$params = $GLOBALS['egw_info']['server']['cache_provider_'.strtolower($level)];
			if (!isset($params) && $level == self::INSTANCE && isset(self::$default_provider))
			{
				$params = self::$default_provider;
			}
			if (!isset($params))
			{
				if ($level == self::TREE)	// if no tree level provider use the instance level one
				{
					$providers[$level] = self::get_provider(self::INSTANCE);
				}
				else
				{
					$providers[$level] = false;	// no provider specified
					$reason = 'no provider specified';
				}
			}
			elseif (!$params)
			{
					$providers[$level] = false;	// cache for $level disabled
					$reason = "cache for $level disabled";
			}
			else
			{
				if (!is_array($params)) $params = (array)$params;

				$class = array_shift($params);
				if (!class_exists($class))
				{
					$providers[$level] = false;	// provider class not found
					$reason = "provider $class not found";
				}
				else
				{
					try
					{
						$providers[$level] = new $class($params);
					}
					catch(Exception $e)
					{
						$providers[$level] = false;	// eg. could not open connection to backend
						$reason = "error instanciating provider $class: ".$e->getMessage();
					}
				}
			}
			if (!$providers[$level] && $log_not_found) error_log(__METHOD__."($level) no provider found ($reason)!".function_backtrace());
		}
		//error_log(__METHOD__."($level) = ".array2string($providers[$level]).', cache_provider='.array2string($GLOBALS['egw_info']['server']['cache_provider_'.strtolower($level)]));
		return $providers[$level];
	}

	/**
	 * Get class-name of caching provider
	 *
	 * @param string $level
	 * @return string class-name of provider
	 */
	public static function getProvider($level=self::INSTANCE)
	{
		$provider = self::get_provider($level);

		return get_class($provider);
	}

	/**
	 * Get a system configuration, even if in setup and it's not read
	 *
	 * @param string $name
	 * @param boolean $throw =true throw an exception, if we can't retriev the value
	 * @return string|boolean string with config or false if not found and !$throw
	 */
	static public function get_system_config($name,$throw=true)
	{
		if(!isset($GLOBALS['egw_info']['server'][$name]))
		{
			if (isset($GLOBALS['egw_setup']) && isset($GLOBALS['egw_setup']->db) || $GLOBALS['egw']->db)
			{
				$db = $GLOBALS['egw']->db ? $GLOBALS['egw']->db : $GLOBALS['egw_setup']->db;

				try {
					if (($rs = $db->select(Config::TABLE,'config_value',array(
						'config_app'	=> 'phpgwapi',
						'config_name'	=> $name,
					),__LINE__,__FILE__)))
					{
						$GLOBALS['egw_info']['server'][$name] = $rs->fetchColumn();
					}
					else
					{
						error_log(__METHOD__."('$name', $throw) config value NOT found!");//.function_backtrace());
					}
				}
				catch(Db\Exception $e)
				{
					if ($throw) error_log(__METHOD__."('$name', $throw) cound NOT query value: ".$e->getMessage());//.function_backtrace());
				}
			}
			if (!$GLOBALS['egw_info']['server'][$name] && $throw)
			{
				throw new Exception (__METHOD__."($name) \$GLOBALS['egw_info']['server']['$name'] is NOT set!");
			}
		}
		return $GLOBALS['egw_info']['server'][$name];
	}

	/**
	 * Flush (delete) whole (instance) cache or application/class specific part of it
	 *
	 * @param string $level =self::INSTANCE
	 * @param string $app =null app-name or "all" to empty complete cache
	 */
	static public function flush($level=self::INSTANCE, $app=null)
	{
		$ret = true;
		if (!($provider = self::get_provider($level)))
		{
			$ret = false;
		}
		else
		{
			if (!$provider->flush($app !== "all" ? self::keys($level, $app) : array()))
			{
				if ($level == self::INSTANCE)
				{
					self::generate_instance_key();
				}
				else
				{
					$ret = false;
				}
			}
		}
		//error_log(__METHOD__."('$level', '$app') returning ".array2string($ret));
		return $ret;
	}

	/**
	 * Unset instance key, so it get read again and re-read install_id from database
	 */
	static public function unset_instance_key()
	{
		self::$instance_key = null;
		$GLOBALS['egw_info']['server']['install_id'] = self::get_system_config('install_id', false);
	}

	/**
	 * Key used for instance specific data
	 *
	 * @var string
	 */
	private static $instance_key;

	/**
	 * Generate a new instance key and by doing so effectivly flushes whole instance cache
	 *
	 * @param string $install_id =null default use install_id of current instance
	 * @return string new key also stored in self::$instance_key
	 */
	static public function generate_instance_key($install_id=null)
	{
		if (!isset($install_id))
		{
			self::$instance_key = null;
			$install_id = self::get_system_config('install_id');
		}
		$instance_key = self::INSTANCE.'-'.$install_id.'-'.microtime(true);
		self::setTree(__CLASS__, $install_id, $instance_key);

		//error_log(__METHOD__."(install_id='$install_id') returning '".$instance_key."'");
		return $instance_key;
	}

	/**
	 * Get keys array from $level, $app and $location
	 *
	 * @param string $level Api\Cache::(TREE|INSTANCE) or instance_id
	 * @param string $app =null
	 * @param string $location =null
	 * @return array
	 */
	static public function keys($level, $app=null, $location=null)
	{
		static $tree_key = null;

		switch($level)
		{
			case self::TREE:
				if (!isset($tree_key))
				{
					$tree_key = $level.'-'.str_replace(array(':','/','\\'),'-', self::$egw_server_root);
					// add charset to key, if not utf-8 (as everything we store depends on charset!)
					if (($charset = self::get_system_config('system_charset',false)) && $charset != 'utf-8')
					{
						$tree_key .= '-'.$charset;
					}
				}
				$level_key = $tree_key;
				break;
			default:	// arbitrary install_id given --> check for current instance
				if ($level !== $GLOBALS['egw_info']['server']['install_id'])
				{
					$level_key = self::getTree(__CLASS__, $level);
					if (!isset($level_key)) $level_key = self::generate_instance_key($level);
					break;
				}
				// fall-through for current instance
			case self::INSTANCE:
				if (!isset(self::$instance_key))
				{
					self::$instance_key = self::getTree(__CLASS__, self::get_system_config('install_id'));
					//error_log(__METHOD__."('$level',...) instance_key read from tree-cache=".array2string(self::$instance_key));
					if (!isset(self::$instance_key)) self::$instance_key = self::generate_instance_key();
				}
				$level_key = self::$instance_key;
				break;
		}
		$keys = array($level_key);
		if (isset($app))
		{
			$keys[] = $app;
			if (isset($location)) $keys[] = $location;
		}
		return $keys;
	}

	/**
	 * Let everyone know the methods of this class should be used only statically
	 *
	 */
	function __construct()
	{
		throw new Exception\WrongParameter("All methods of class ".__CLASS__." should be called static!");
	}
}

// setting apc(u) as default provider, if apc(u)_fetch function exists AND further checks in Api\Cache\Apc(u) recommed it
if (is_null(Cache::$default_provider))
{
	Cache::$default_provider =
		function_exists('apcu_fetch') && Cache\Apcu::available() ? 'EGroupware\Api\Cache\Apcu' :
			(function_exists('apc_fetch') && Cache\Apc::available() ? 'EGroupware\Api\Cache\Apc' :
				'EGroupware\Api\Cache\Files');
}

//error_log('Cache::$default_provider='.array2string(Cache::$default_provider));
