<?php
/**
 * EGroupware API - Authentication against multiple backends
 *
 * @link https://www.egroupware.org
 * @author Ralf Becker <rb@egroupware.org>
 * @license http://opensource.org/licenses/lgpl-license.php LGPL - GNU Lesser General Public License
 * @package api
 * @subpackage auth
 */

namespace EGroupware\Api\Auth;

use EGroupware\Api;

/**
 * Authentication agains multiple backends
 *
 * The first backend against which authentication succeeds is used, so you either need *somehow* to make sure usernames are unique,
 * or the backends in the *right* order. The name of the succeeding backend is stored in the instance cache.
 *
 * Specified via auth_multiple config variable with either
 * - a comma-separated string, eg. "Ldap,Sql" to first try LDAP then SQL authentication configured directly in setup
 * - a JSON encoded object eg.
 * {
 * 		"Ads": null, <-- uses default Ads config from Setup
 * 		"Ads2": {    <-- 2nd Ads using given config (append a number to use backends multiple times)
 * 			"ads_host":"...",
 * 			"ads_domain":"...",
 * 			"ads_admin_user":"...",
 * 			"ads_admin_passwd":"...",
 * 			optional attributes like: "ads_connection":"tls"|"ssl", "ads_context", "ads_user_filter", "ads_group_filter"
 * 		},
 * 		optional further backend objects
 * }
 */
class Multiple implements Backend
{
	/**
	 * @var ?array[] with name as key
	 */
	private $config;
	/**
	 * @var Backend[] with name as key
	 */
	private $backends = [];

	/**
	 * Constructor
	 *
	 * @param string $config auth_multiple config variable
	 * @throws \Exception on invalid configuration
	 */
	function __construct($config=null)
	{
		if (!isset($config)) $config = $GLOBALS['egw_info']['server']['auth_multiple'];

		$this->config = self::parseConfig($config);
	}

	/**
	 * Parse configuration
	 *
	 * @param string|array $config auth_multiple configuration
	 * @param boolean $checks true: run some extra checks, used in setup to check config is sane
	 * @return array
	 * @throws \Exception on invalid configuration
	 */
	static public function parseConfig($config, $checks=false)
	{
		try
		{
			if (!is_array($config))
			{
				$config = $config[0] === '{' ? json_decode($config, true, 512, JSON_THROW_ON_ERROR) :
					array_combine($csv = preg_split('/,\s*/', $config), array_fill(0, count($csv), null));
			}
		}
		catch(\JsonException $e) {
			throw new \Exception('Invalid JSON: '.$e->getMessage());
		}
		if ($checks)
		{
			foreach($config as $name => $data)
			{
				if (!class_exists($class = __NAMESPACE__.'\\'.ucfirst(preg_replace('/\d+$/', '', $name))))
				{
					throw new \Exception("Invalid Backend name: '$name', no class $class found!");
				}
				if ($data !== null && !is_array($data))
				{
					throw new \Exception("Invalid Backend config: must by either null or an object!");
				}
			}
		}
		return $config;
	}

	/**
	 * Iterate over all backends
	 *
	 * @return \Generator $name => Backend
	 */
	protected function backends()
	{
		foreach($this->config as $name => $config)
		{
			yield $name => $this->backend($name);
		}
	}

	/**
	 * Get a given backend
	 *
	 * @param $name
	 * @return Backend
	 */
	protected function backend($name)
	{
		if (!isset($this->backends[$name]))
		{
			$class = __NAMESPACE__.'\\'.ucfirst(preg_replace('/\d+$/', '', $name));
			$this->backends[$name] = new $class($this->config[$name]);
		}
		return $this->backends[$name];
	}

	/**
	 * Authenticate
	 *
	 * @param string $username username of account to authenticate
	 * @param string $passwd corresponding password
	 * @return boolean true if successful authenticated, false otherwise
	 */
	function authenticate($username, $passwd, $passwd_type='text')
	{
		$ret = false;
		if (($name = Api\Cache::getInstance(__CLASS__,'backend_used-'.$username)))
		{
			$ret = $this->backend($name)->authenticate($username, $passwd, $passwd_type);
		}
		else
		{
			foreach ($this->backends() as $name => $backend)
			{
				if (($ret = $backend->authenticate($username, $passwd, $passwd_type)))
				{
					Api\Cache::setInstance(__CLASS__, 'backend_used-' . $username, $name);

					break;
				}
			}
		}
		//error_log(__METHOD__."('$username', \$passwd, '$passwd_type') backend=$name" returning ".array2string($ret));
		return $ret;
	}

	/**
	 * Changes password in authentication backend
	 *
	 * If $old_passwd is given, the password change is done binded as user and NOT with the
	 * "root" dn given in the configurations.
	 *
	 * @param string $old_passwd must be cleartext or empty to not to be checked
	 * @param string $new_passwd must be cleartext
	 * @param int $account_id account id of user whose passwd should be changed
	 * @return boolean true if password successful changed, false otherwise
	 */
	function change_password($old_passwd, $new_passwd, $account_id=0)
	{
		if (!$account_id)
		{
			$account_id = $GLOBALS['egw_info']['user']['account_id'];
			$username = $GLOBALS['egw_info']['user']['account_lid'];
		}
		else
		{
			$username = $GLOBALS['egw']->accounts->id2name($account_id);
		}
		$ret = false;
		if (($name = Api\Cache::getInstance(__CLASS__,'backend_used-'.$username)))
		{
			$ret = $this->backend($name)->change_password($old_passwd, $new_passwd, $account_id);
		}
		else
		{
			foreach($this->backends as $name => $backend)
			{
				if (($ret = $backend->change_password($old_passwd, $new_passwd, $account_id)))
				{
					break;
				}
			}
		}
		//error_log(__METHOD__."('$old_passwd', '$new_passwd', $account_id) username='$username', backend=$name" returning ".array2string($ret));
		return $ret;
	}

	/**
	 * Fetch the last pwd change for the user
	 *
	 * @param string $username username of account to authenticate
	 * @return mixed false or account_lastpwd_change
	 */
	function getLastPwdChange($username)
	{
		$ret = false;
		if (($name = Api\Cache::getInstance(__CLASS__,'backend_used-'.$username)))
		{
			$backend = $this->backend($name);
			if (method_exists($backend, 'getLastPwdChange'))
			{
				$ret = $backend->getLastPwdChange($username);
			}
		}
		else
		{
			foreach($this->backends as $name => $backend)
			{
				if (method_exists($backend, 'getLastPwdChange') &&
					($ret = $backend->getLastPwdChange($username)))
				{
					break;
				}
			}
		}
		//error_log(__METHOD__."('$username'), backend=$name" returning ".array2string($ret));
		return $ret;
	}

	/**
	 * Changes account_lastpwd_change in auth backend
	 *
	 * @param int $account_id account id of user whose passwd should be changed
	 * @param string $passwd must be cleartext, usually not used, but may be used to authenticate as user to do the change -> ldap
	 * @param int $lastpwdchange must be a unixtimestamp
	 * @return boolean true if account_lastpwd_change successful changed, false otherwise
	 */
	function setLastPwdChange($account_id=0, $passwd=NULL, $lastpwdchange=NULL, $return_mod=false)
	{
		if(!$account_id || $GLOBALS['egw_info']['flags']['currentapp'] == 'login')
		{
			$account_id = $GLOBALS['egw_info']['user']['account_id'];
			$username = $GLOBALS['egw_info']['user']['account_lid'];
		}
		else
		{
			$username = $GLOBALS['egw']->accounts->id2name($account_id);
		}
		$ret = false;
		if (($name = Api\Cache::getInstance(__CLASS__,'backend_used-'.$username)))
		{
			$backend = $this->backend($name);
			if (method_exists($backend, 'setLastPwdChange'))
			{
				$ret = $backend->setLastPwdChange($account_id, $passwd, $lastpwdchange, $return_mod);
			}
		}
		else
		{
			foreach($this->backends as $name => $backend)
			{
				if (method_exists($backend, 'setLastPwdChange') &&
					($ret = $backend->setLastPwdChange($account_id, $passwd, $lastpwdchange, $return_mod)))
				{
					break;
				}
			}
		}
		//error_log(__METHOD__."('$username'), backend=$name" returning ".array2string($ret));
		return $ret;
	}
}
