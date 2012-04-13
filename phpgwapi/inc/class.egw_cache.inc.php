<?php
/**
 * EGroupware API: Caching data
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
 * Class to manage caching in eGroupware.
 *
 * It allows to cache on 4 levels:
 * a) tree:     for all instances/domains runining on a certain source path
 * b) instance: for all sessions on a given instance
 * c) session:  for all requests of a session, same as egw_session::appsession()
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
 * In fact data stored at cache level egw_cache::SESSION, is stored in the same way as
 * egw_session::appsession() so both methods can be used with each other.
 *
 * The $app parameter should be either the app or the class name, which both are unique.
 *
 * The tree and instance wide cache uses a certain provider class, to store the data
 * eg. in memcached or if there's nothing else configured in the filesystem (eGW's temp_dir).
 */
class egw_cache
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
	 * Default is set (if not set here) after class definition to egw_cache_apc or egw_cache_files,
	 * depending on function 'apc_fetch' exists or not
	 *
	 * @var array
	 */
	static $default_provider;	// = array('egw_cache_files');// array('egw_cache_memcache','localhost');

	/**
	 * Set some data in the cache
	 *
	 * @param string $level use egw_cache::(TREE|INSTANCE|SESSION|REQUEST)
	 * @param string $app application storing data
	 * @param string $location location name for data
	 * @param mixed $data
	 * @param int $expiration=0 expiration time in seconds, default 0 = never
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
				if (!($provider = self::get_provider($level)))
				{
					return false;
				}
				return $provider->set(self::keys($level,$app,$location),$data,$expiration);
		}
		throw new egw_exception_wrong_parameter(__METHOD__."() unknown level '$level'!");
	}

	/**
	 * Get some data from the cache
	 *
	 * @param string $level use egw_cache::(TREE|INSTANCE|SESSION|REQUEST)
	 * @param string $app application storing data
	 * @param string $location location name for data
	 * @param callback $callback=null callback to get/create the value, if it's not cache
	 * @param array $callback_params=array() array with parameters for the callback
	 * @param int $expiration=0 expiration time in seconds, default 0 = never
	 * @return mixed NULL if data not found in cache (and no callback specified)
	 */
	static public function getCache($level,$app,$location,$callback=null,array $callback_params=array(),$expiration=0)
	{
		switch($level)
		{
			case self::SESSION:
			case self::REQUEST:
				return call_user_func(array(__CLASS__,'get'.$level),$app,$location,$callback,$callback_params,$expiration);

			case self::INSTANCE:
			case self::TREE:
				if (!($provider = self::get_provider($level)))
				{
					return null;
				}
				try {
					$data = $provider->get($keys=self::keys($level,$app,$location));
				}
				catch(Exception $e) {
					$data = null;
				}
				if (is_null($data) && !is_null($callback))
				{
					//error_log(__METHOD__."($level,$app,$location,".array2string($callback).','.array2string($callback_params).",$expiration) calling calback to create data.");
					$data = call_user_func_array($callback,$callback_params);
					$provider->set($keys,$data,$expiration);
				}
				return $data;
		}
		throw new egw_exception_wrong_parameter(__METHOD__."() unknown level '$level'!");
	}

	/**
	 * Unset some data in the cache
	 *
	 * @param string $level use egw_cache::(TREE|INSTANCE|SESSION|REQUEST)
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
				if (!($provider = self::get_provider($level)))
				{
					return false;
				}
				return $provider->delete(self::keys($level,$app,$location));
		}
		throw new egw_exception_wrong_parameter(__METHOD__."() unknown level '$level'!");
	}

	/**
	 * Set some data in the cache for the whole source tree (all instances)
	 *
	 * @param string $app application storing data
	 * @param string $location location name for data
	 * @param mixed $data
	 * @param int $expiration=0 expiration time in seconds, default 0 = never
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
	 * @param callback $callback=null callback to get/create the value, if it's not cache
	 * @param array $callback_params=array() array with parameters for the callback
	 * @param int $expiration=0 expiration time in seconds, default 0 = never
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
	 * @param int $expiration=0 expiration time in seconds, default 0 = never
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
	 * @param callback $callback=null callback to get/create the value, if it's not cache
	 * @param array $callback_params=array() array with parameters for the callback
	 * @param int $expiration=0 expiration time in seconds, default 0 = never
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
	 * @param int $expiration=0 expiration time in seconds, default 0 = never
	 * @return boolean true if data could be stored, false otherwise
	 */
	static public function setSession($app,$location,$data,$expiration=0)
	{
		if (isset($_SESSION[egw_session::EGW_SESSION_ENCRYPTED]))
		{
			if (egw_session::ERROR_LOG_DEBUG) error_log(__METHOD__.' called after session was encrypted --> ignored!');
			return false;	// can no longer store something in the session, eg. because commit_session() was called
		}
		$_SESSION[egw_session::EGW_APPSESSION_VAR][$app][$location] = $data;

		return true;
	}

	/**
	 * Get some data from the cache for the whole source tree (all instances)
	 *
	 * Returns a reference to the var in the session!
	 *
	 * @param string $app application storing data
	 * @param string $location location name for data
	 * @param callback $callback=null callback to get/create the value, if it's not cache
	 * @param array $callback_params=array() array with parameters for the callback
	 * @param int $expiration=0 expiration time in seconds, default 0 = never
	 * @return mixed NULL if data not found in cache (and no callback specified)
	 */
	static public function &getSession($app,$location,$callback=null,array $callback_params=array(),$expiration=0)
	{
		if (isset($_SESSION[egw_session::EGW_SESSION_ENCRYPTED]))
		{
			if (egw_session::ERROR_LOG_DEBUG) error_log(__METHOD__.' called after session was encrypted --> ignored!');
			return null;	// can no longer store something in the session, eg. because commit_session() was called
		}
		if (!isset($_SESSION[egw_session::EGW_APPSESSION_VAR][$app][$location]) && !is_null($callback))
		{
			$_SESSION[egw_session::EGW_APPSESSION_VAR][$app][$location] = call_user_func_array($callback,$callback_params);
		}
		return $_SESSION[egw_session::EGW_APPSESSION_VAR][$app][$location];
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
		if (isset($_SESSION[egw_session::EGW_SESSION_ENCRYPTED]))
		{
			if (egw_session::ERROR_LOG_DEBUG) error_log(__METHOD__.' called after session was encrypted --> ignored!');
			return false;	// can no longer store something in the session, eg. because commit_session() was called
		}
		if (!isset($_SESSION[egw_session::EGW_APPSESSION_VAR][$app][$location]))
		{
			return false;
		}
		unset($_SESSION[egw_session::EGW_APPSESSION_VAR][$app][$location]);

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
	 * @param int $expiration=0 expiration time is NOT used for REQUEST!
	 * @return boolean true if data could be stored, false otherwise
	 */
	static public function setRequest($app,$location,$data,$expiration=0)
	{
		self::$request_cache[$app][$location] = $data;

		return true;
	}

	/**
	 * Get some data from the cache for the whole source tree (all instances)
	 *
	 * @param string $app application storing data
	 * @param string $location location name for data
	 * @param callback $callback=null callback to get/create the value, if it's not cache
	 * @param array $callback_params=array() array with parameters for the callback
	 * @param int $expiration=0 expiration time is NOT used for REQUEST!
	 * @return mixed NULL if data not found in cache (and no callback specified)
	 */
	static public function getRequest($app,$location,$callback=null,array $callback_params=array(),$expiration=0)
	{
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

		return $ret;
	}

	/**
	 * Get a caching provider for tree or instance level
	 *
	 * The returned provider already has an opened connection
	 *
	 * @param string $level egw_cache::(TREE|INSTANCE)
	 * @return egw_cache_provider
	 */
	static protected function get_provider($level)
	{
		static $providers = array();

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
			if (!$providers[$level]) error_log(__METHOD__."($level) no provider found ($reason)!");
		}
		//error_log(__METHOD__."($level) = ".array2string($providers[$level]).', cache_provider='.array2string($GLOBALS['egw_info']['server']['cache_provider_'.strtolower($level)]));
		return $providers[$level];
	}

	/**
	 * Get a system configuration, even if in setup and it's not read
	 *
	 * @param string $name
	 * @param boolean $throw=true throw an exception, if we can't retriev the value
	 * @return string|boolean string with config or false if not found and !$throw
	 */
	static protected function get_system_config($name,$throw=true)
	{
		if(!isset($GLOBALS['egw_info']['server'][$name]))
		{
			if (isset($GLOBALS['egw_setup']) && isset($GLOBALS['egw_setup']->db))
			{
				$GLOBALS['egw_info']['server'][$name] = $GLOBALS['egw_setup']->db->select(config::TABLE,'config_value',array(
					'config_app'	=> 'phpgwapi',
					'config_name'	=> $name,
				),__LINE__,__FILE__)->fetchColumn();
			}
			if (!$GLOBALS['egw_info']['server'][$name] && $throw)
			{
				throw new Exception (__METHOD__."($name) \$GLOBALS['egw_info']['server']['$name'] is NOT set!");
			}
		}
		return $GLOBALS['egw_info']['server'][$name];
	}

	/**
	 * Get keys array from $level, $app and $location
	 *
	 * @param string $level egw_cache::(TREE|INSTANCE)
	 * @param string $app
	 * @param string $location
	 * @return array
	 */
	static public function keys($level,$app,$location)
	{
		static $bases = array();

		if (!isset($bases[$level]))
		{
			switch($level)
			{
				case self::TREE:
					$bases[$level] = $level.'-'.str_replace(array(':','/','\\'),'-',EGW_SERVER_ROOT);
					// add charset to key, if not utf-8 (as everything we store depends on charset!)
					if (($charset = self::get_system_config('system_charset',false)) && $charset != 'utf-8')
					{
						$bases[$level] .= '-'.$charset;
					}
					break;
				case self::INSTANCE:
					$bases[$level] = $level.'-'.self::get_system_config('install_id');
					break;
			}
		}
		return array($bases[$level],$app,$location);
	}

	/**
	 * Let everyone know the methods of this class should be used only statically
	 *
	 */
	function __construct()
	{
		throw new egw_exception_wrong_parameter("All methods of class ".__CLASS__." should be called static!");
	}
}

// setting apc as default provide, if apc_fetch function exists AND not cli or apc enabled for cli
if (is_null(egw_cache::$default_provider))
{
	egw_cache::$default_provider = function_exists('apc_fetch') && (PHP_SAPI != 'cli' || ini_get('apc.enable_cli')) ?
		'egw_cache_apc' : 'egw_cache_files';
}

/**
 * Interface for a caching provider for tree and instance level
 *
 * The provider can eg. create subdirs under /tmp for each key
 * to store data as a file or concat them with a separator to
 * get a single string key to eg. store data in memcached
 */
interface egw_cache_provider
{
	/**
	 * Constructor, eg. opens the connection to the backend
	 *
	 * @throws Exception if connection to backend could not be established
	 * @param array $params eg. array(host,port) or array(directory) depending on the provider
	 */
	function __construct(array $params);

	/**
	 * Stores some data in the cache
	 *
	 * @param array $keys eg. array($level,$app,$location)
	 * @param mixed $data
	 * @param int $expiration=0
	 * @return boolean true on success, false on error
	 */
	function set(array $keys,$data,$expiration=0);

	/**
	 * Get some data from the cache
	 *
	 * @param array $keys eg. array($level,$app,$location)
	 * @return mixed data stored or NULL if not found in cache
	 */
	function get(array $keys);

	/**
	 * Delete some data from the cache
	 *
	 * @param array $keys eg. array($level,$app,$location)
	 * @return boolean true on success, false on error (eg. $key not set)
	 */
	function delete(array $keys);
}

abstract class egw_cache_provider_check implements egw_cache_provider
{
	/**
	 * Run several checks on a caching provider
	 *
	 * @param boolean $verbose=false true: echo failed checks
	 * @return int number of failed checks
	 */
	function check($verbose=false)
	{
		$failed = 0;
		foreach(array(
			egw_cache::TREE => 'tree',
			egw_cache::INSTANCE => 'instance',
		) as $level => $label)
		{
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
			}
		}

		return $failed;
	}
}

// some testcode, if this file is called via it's URL
// can be run on command-line: sudo php -d apc.enable_cli=1 -f phpgwapi/inc/class.egw_cache.inc.php
/*if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) == __FILE__)
{
	if (!isset($_SERVER['HTTP_HOST']))
	{
		chdir(dirname(__FILE__));	// to enable our relative pathes to work
	}
	$GLOBALS['egw_info'] = array(
		'flags' => array(
			'noapi' => true,
		),
	);
	include_once '../../header.inc.php';

	if (isset($_SERVER['HTTP_HOST'])) echo "<pre>\n";

	foreach(array(
		'egw_cache_memcache' => array('localhost'),
		'egw_cache_apc' => array(),
		'egw_cache_files' => array('/tmp'),
	) as $class => $param)
	{
		echo "Checking $class:\n";
		$start = microtime(true);
		$provider = new $class($param);
		$n = 100;
		for($i=1; $i <= $n; ++$i)
		{
			$failed = $provider->check($i == 1);
		}
		printf("$failed checks failed, $n iterations took %5.3f sec\n\n", microtime(true)-$start);
	}
}*/
