<?php
/**
 * EGroupware API: session handling
 *
 * This class is based on the old phpgwapi/inc/class.sessions(_php4).inc.php:
 * (c) 1998-2000 NetUSE AG Boris Erdmann, Kristian Koehntopp
 * (c) 2003 FreeSoftware Foundation
 * Not sure how much the current code still has to do with it.
 *
 * Former authers were:
 * - NetUSE AG Boris Erdmann, Kristian Koehntopp
 * - Dan Kuykendall <seek3r@phpgroupware.org>
 * - Joseph Engo <jengo@phpgroupware.org>
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage session
 * @author Ralf Becker <ralfbecker@outdoor-training.de> since 2003 on
 * @version $Id$
 */

namespace EGroupware\Api;

/**
 * Create, verifies or destroys an EGroupware session
 *
 * If you want to analyse the memory usage in the session, you can uncomment the following call:
 *
 * 	static function encrypt($kp3)
 *	{
 *		// switch that on to analyse memory usage in the session
 *		//self::log_session_usage($_SESSION[self::EGW_APPSESSION_VAR],'_SESSION['.self::EGW_APPSESSION_VAR.']',true,5000);
 */
class Session
{
	/**
	 * Write debug messages about session verification and creation to the error_log
	 *
	 * This will contain passwords! Don't leave it permanently switched on!
	 */
	const ERROR_LOG_DEBUG = false;

	/**
	 * key of eGW's session-data in $_SESSION
	 */
	const EGW_SESSION_VAR = 'egw_session';

	/**
	 * key of eGW's application session-data in $_SESSION
	 */
	const EGW_APPSESSION_VAR = 'egw_app_session';

	/**
	 * key of eGW's required files in $_SESSION
	 *
	 * These files get set by Db and Egw class, for classes which get not autoloaded (eg. ADOdb, idots_framework)
	 */
	const EGW_REQUIRED_FILES = 'egw_required_files';

	/**
	 * key of  eGW's egw_info cached in $_SESSION
	 */
	const EGW_INFO_CACHE = 'egw_info_cache';

	/**
	 * key of  eGW's egw object cached in $_SESSION
	 */
	const EGW_OBJECT_CACHE = 'egw_object_cache';

	/**
	 * Name of cookie or get-parameter with session-id
	 */
	const EGW_SESSION_NAME = 'sessionid';

	/**
	 * Used mcrypt algorithm and mode
	 */
	const MCRYPT_ALGO = MCRYPT_TRIPLEDES;
	const MCRYPT_MODE = MCRYPT_MODE_ECB;

	/**
	* current user login (account_lid@domain)
	*
	* @var string
	*/
	var $login;

	/**
	* current user password
	*
	* @var string
	*/
	var $passwd;

	/**
	* current user db/ldap account id
	*
	* @var int
	*/
	var $account_id;

	/**
	* current user account login id (without the eGW-domain/-instance part
	*
	* @var string
	*/
	var $account_lid;

	/**
	* domain for current user
	*
	* @var string
	*/
	var $account_domain;

	/**
	* type flag, A - anonymous session, N - None, normal session
	*
	* @var string
	*/
	var $session_flags;

	/**
	* current user session id
	*
	* @var string
	*/
	var $sessionid;

	/**
	* an other session specific id (md5 from a random string),
	* used together with the sessionid for xmlrpc basic auth and the encryption of session-data (if that's enabled)
	*
	* @var string
	*/
	var $kp3;

	/**
	 * Primary key of egw_access_log row for updates
	 *
	 * @var int
	 */
	var $sessionid_access_log;

	/**
	* name of XML-RPC/SOAP method called
	*
	* @var string
	*/
	var $xmlrpc_method_called;

	/**
	* Array with the name of the system domains
	*
	* @var array
	*/
	private $egw_domains;

	/**
	 * $_SESSION at the time the constructor was called
	 *
	 * @var array
	 */
	var $required_files;

	/**
	 * Nummeric code why session creation failed
	 *
	 * @var int
	 */
	var $cd_reason;
	const CD_BAD_LOGIN_OR_PASSWORD = 5;
	const CD_FORCE_PASSWORD_CHANGE = 97;
	const CD_ACCOUNT_EXPIRED = 98;
	const CD_BLOCKED = 99;	// to many failed attempts to loing

	/**
	 * Verbose reason why session creation failed
	 *
	 * @var string
	 */
	var $reason;

	/**
	 * Session action set by update_dla or set_action and stored in __destruct
	 *
	 * @var string
	 */
	protected $action;

	/**
	 * Constructor just loads up some defaults from cookies
	 *
	 * @param array $domain_names =null domain-names used in this install
	 */
	function __construct(array $domain_names=null)
	{
		$this->required_files = $_SESSION[self::EGW_REQUIRED_FILES];

		$this->sessionid = self::get_sessionid();
		$this->kp3       = self::get_request('kp3');

		$this->egw_domains = $domain_names;

		if (!isset($GLOBALS['egw_setup']))
		{
			// verfiy and if necessary create and save our config settings
			//
			$save_rep = false;
			if (!isset($GLOBALS['egw_info']['server']['max_access_log_age']))
			{
				$GLOBALS['egw_info']['server']['max_access_log_age'] = 90;	// default 90 days
				$save_rep = true;
			}
			if (!isset($GLOBALS['egw_info']['server']['block_time']))
			{
				$GLOBALS['egw_info']['server']['block_time'] = 1;	// default 1min, its enough to slow down brute force attacks
				$save_rep = true;
			}
			if (!isset($GLOBALS['egw_info']['server']['num_unsuccessful_id']))
			{
				$GLOBALS['egw_info']['server']['num_unsuccessful_id']  = 3;	// default 3 trys per id
				$save_rep = true;
			}
			if (!isset($GLOBALS['egw_info']['server']['num_unsuccessful_ip']))
			{
				$GLOBALS['egw_info']['server']['num_unsuccessful_ip']  = $GLOBALS['egw_info']['server']['num_unsuccessful_id'] * 5;	// default is 5 times as high as the id default; since accessing via proxy is quite common
				$save_rep = true;
			}
			if (!isset($GLOBALS['egw_info']['server']['install_id']))
			{
				$GLOBALS['egw_info']['server']['install_id']  = md5(Auth::randomstring(15));
			}
			if (!isset($GLOBALS['egw_info']['server']['max_history']))
			{
				$GLOBALS['egw_info']['server']['max_history'] = 20;
				$save_rep = true;
			}

			if ($save_rep)
			{
				$config = new Config('phpgwapi');
				$config->read_repository();
				$config->value('max_access_log_age',$GLOBALS['egw_info']['server']['max_access_log_age']);
				$config->value('block_time',$GLOBALS['egw_info']['server']['block_time']);
				$config->value('num_unsuccessful_id',$GLOBALS['egw_info']['server']['num_unsuccessful_id']);
				$config->value('num_unsuccessful_ip',$GLOBALS['egw_info']['server']['num_unsuccessful_ip']);
				$config->value('install_id',$GLOBALS['egw_info']['server']['install_id']);
				$config->value('max_history',$GLOBALS['egw_info']['server']['max_history']);
				$config->save_repository();
			}
		}
		self::set_cookiedomain();

		// set session_timeout from global php.ini and default to 14400=4h, if not set
		if (!($GLOBALS['egw_info']['server']['sessions_timeout'] = ini_get('session.gc_maxlifetime')))
      	{
      		ini_set('session.gc_maxlifetime', $GLOBALS['egw_info']['server']['sessions_timeout']=14400);
      	}
	}

	/**
	 * Magic function called when this class get's restored from the session
	 *
	 */
	function __wakeup()
	{
		ini_set('session.gc_maxlifetime', $GLOBALS['egw_info']['server']['sessions_timeout']);

		$this->action = null;
	}

	/**
	 * Destructor: update access-log and encrypt session
	 */
	function __destruct()
	{
		// write dla update on destruct, allows to modify session action by calling Session::set_action()
		if (!isset($GLOBALS['egw_info']['flags']['no_dla_update']) || !$GLOBALS['egw_info']['flags']['no_dla_update'])
		{
			$this->update_dla(true);
		}

		self::encrypt($this->kp3);
	}

	/**
	 * commit the sessiondata to storage
	 *
	 * It's necessary to use this function instead of session_write_close() direct, as otherwise the session is not encrypted!
	 */
	function commit_session()
	{
		if (self::ERROR_LOG_DEBUG) error_log(__METHOD__."() sessionid=$this->sessionid, _SESSION[".self::EGW_SESSION_VAR.']='.array2string($_SESSION[self::EGW_SESSION_VAR]).' '.function_backtrace());
		self::encrypt($this->kp3);

		session_write_close();
	}

	/**
	 * Keys of session variables which get encrypted
	 *
	 * @var array
	 */
	static $egw_session_vars = array(
		//self::EGW_SESSION_VAR, no need to encrypt and required by the session list
		self::EGW_APPSESSION_VAR,
		self::EGW_INFO_CACHE,
		self::EGW_OBJECT_CACHE,
	);

	static $mcrypt;

	/**
	 * Name of flag in session to signal it is encrypted or not
	 */
	const EGW_SESSION_ENCRYPTED = 'egw_session_encrypted';

	/**
	 * Encrypt the variables in the session
	 *
	 * Is called by self::__destruct().
	 */
	static function encrypt($kp3)
	{
		// switch that on to analyse memory usage in the session
		//self::log_session_usage($_SESSION[self::EGW_APPSESSION_VAR],'_SESSION['.self::EGW_APPSESSION_VAR.']',true,5000);

		if (!isset($_SESSION[self::EGW_SESSION_ENCRYPTED]) && self::init_crypt($kp3))
		{
			foreach(self::$egw_session_vars as $name)
			{
				if (isset($_SESSION[$name]))
				{
					$_SESSION[$name] = mcrypt_generic(self::$mcrypt,serialize($_SESSION[$name]));
					//error_log(__METHOD__."() 'encrypting' session var: $name, len=".strlen($_SESSION[$name]));
				}
			}
			$_SESSION[self::EGW_SESSION_ENCRYPTED] = true;	// flag session as encrypted

			mcrypt_generic_deinit(self::$mcrypt);
			self::$mcrypt = null;
		}
	}

	/**
	 * Log the usage of session-vars
	 *
	 * @param array &$arr
	 * @param string $label
	 * @param boolean $recursion =true if true call itself for every item > $limit
	 * @param int $limit =1000 log only differences > $limit
	 */
	static function log_session_usage(&$arr,$label,$recursion=true,$limit=1000)
	{
		if (!is_array($arr)) return;

		$sizes = array();
		foreach($arr as $key => &$data)
		{
			$sizes[$key] = strlen(serialize($data));
		}
		arsort($sizes,SORT_NUMERIC);
		foreach($sizes as $key => $size)
		{
			$diff = $size - (int)$_SESSION[$label.'-sizes'][$key];
			$_SESSION[$label.'-sizes'][$key] = $size;
			if ($diff > $limit)
			{
				error_log("strlen({$label}[$key])=".Vfs::hsize($size).", diff=".Vfs::hsize($diff));
				if ($recursion) self::log_session_usage($arr[$key],$label.'['.$key.']',$recursion,$limit);
			}
		}
	}

	/**
	 * Decrypt the variables in the session
	 *
	 * Is called by self::init_handler from api/src/loader.php (called from the header.inc.php)
	 * before the restore of the eGW enviroment takes place, so that the whole thing can be encrypted
	 */
	static function decrypt()
	{
		if ($_SESSION[self::EGW_SESSION_ENCRYPTED] && self::init_crypt(self::get_request('kp3')))
		{
			foreach(self::$egw_session_vars as $name)
			{
				if (isset($_SESSION[$name]))
				{
					$_SESSION[$name] = unserialize(trim(mdecrypt_generic(self::$mcrypt,$_SESSION[$name])));
					//error_log(__METHOD__."() 'decrypting' session var $name: gettype($name) = ".gettype($_SESSION[$name]));
				}
			}
			unset($_SESSION[self::EGW_SESSION_ENCRYPTED]);	// delete encryption flag
		}
	}

	/**
	 * Check if session encryption is configured, possible and initialise it
	 *
	 * @param string $kp3 mcrypt key transported via cookie or get parameter like the session id,
	 *	unlike the session id it's not know on the server, so only the client-request can decrypt the session!
	 * @param string $algo =self::MCRYPT_ALGO
	 * @param string $mode =self::MCRYPT_MODE
	 * @return boolean true if encryption is used, false otherwise
	 */
	static private function init_crypt($kp3,$algo=self::MCRYPT_ALGO,$mode=self::MCRYPT_MODE)
	{
		if(!$GLOBALS['egw_info']['server']['mcrypt_enabled'])
		{
			return false;	// session encryption is switched off
		}
		if ($GLOBALS['egw_info']['currentapp'] == 'syncml' || !$kp3)
		{
			$kp3 = 'staticsyncmlkp3';	// syncml has no kp3!
		}
		if (is_null(self::$mcrypt))
		{
			if (!check_load_extension('mcrypt'))
			{
				error_log(__METHOD__."() required PHP extension mcrypt not loaded and can not be loaded, sessions get NOT encrypted!");
				return false;
			}
			if (!(self::$mcrypt = mcrypt_module_open($algo, '', $mode, '')))
			{
				error_log(__METHOD__."() could not mcrypt_module_open(algo='$algo','',mode='$mode',''), sessions get NOT encrypted!");
				return false;
			}
			$iv_size = mcrypt_enc_get_iv_size(self::$mcrypt);
			$iv = !isset($GLOBALS['egw_info']['server']['mcrypt_iv']) || strlen($GLOBALS['egw_info']['server']['mcrypt_iv']) < $iv_size ?
				mcrypt_create_iv ($iv_size, MCRYPT_RAND) : substr($GLOBALS['egw_info']['server']['mcrypt_iv'],0,$iv_size);

			if (mcrypt_generic_init(self::$mcrypt,$kp3, $iv) < 0)
			{
				error_log(__METHOD__."() could not initialise mcrypt, sessions get NOT encrypted!");
				return self::$mcrypt = false;
			}
		}
		return is_resource(self::$mcrypt);
	}

	/**
	 * Create a new eGW session
	 *
	 * @param string $login user login
	 * @param string $passwd user password
	 * @param string $passwd_type type of password being used, ie plaintext, md5, sha1
	 * @param boolean $no_session =false dont create a real session, eg. for GroupDAV clients using only basic auth, no cookie support
	 * @param boolean $auth_check =true if false, the user is loged in without checking his password (eg. for single sign on), default = true
	 * @param boolean $fail_on_forced_password_change =false true: do NOT create session, if password change requested
	 * @return string|boolean session id or false if session was not created, $this->(cd_)reason contains cause
	 */
	function create($login,$passwd = '',$passwd_type = '',$no_session=false,$auth_check=true,$fail_on_forced_password_change=false)
	{
		try {
			if (is_array($login))
			{
				$this->login       = $login['login'];
				$this->passwd      = $login['passwd'];
				$this->passwd_type = $login['passwd_type'];
				$login             = $this->login;
			}
			else
			{
				$this->login       = $login;
				$this->passwd      = $passwd;
				$this->passwd_type = $passwd_type;
			}
			if (self::ERROR_LOG_DEBUG) error_log(__METHOD__."($this->login,$this->passwd,$this->passwd_type,$no_session,$auth_check) starting ...");

			self::split_login_domain($login,$this->account_lid,$this->account_domain);
			// add domain to the login, if not already there
			if (substr($this->login,-strlen($this->account_domain)-1) != '@'.$this->account_domain)
			{
				$this->login .= '@'.$this->account_domain;
			}
			$now = time();
			//error_log(__METHOD__."($login,$passwd,$passwd_type,$no_session,$auth_check) account_lid=$this->account_lid, account_domain=$this->account_domain, default_domain={$GLOBALS['egw_info']['server']['default_domain']}, user/domain={$GLOBALS['egw_info']['user']['domain']}");

			// This is to ensure that we authenticate to the correct domain (might not be default)
			// if no domain is given we use the default domain, so we dont need to re-create everything
			if (!$GLOBALS['egw_info']['user']['domain'] && $this->account_domain == $GLOBALS['egw_info']['server']['default_domain'])
			{
				$GLOBALS['egw_info']['user']['domain'] = $this->account_domain;
			}
			elseif (!$this->account_domain && $GLOBALS['egw_info']['user']['domain'])
			{
				$this->account_domain = $GLOBALS['egw_info']['user']['domain'];
			}
			elseif($this->account_domain != $GLOBALS['egw_info']['user']['domain'])
			{
				throw new Exception("Wrong domain! '$this->account_domain' != '{$GLOBALS['egw_info']['user']['domain']}'");
			}
			unset($GLOBALS['egw_info']['server']['default_domain']); // we kill this for security reasons

			$user_ip = self::getuser_ip();

			$this->account_id = $GLOBALS['egw']->accounts->name2id($this->account_lid,'account_lid','u');

			if (($blocked = $this->login_blocked($login,$user_ip)) ||	// too many unsuccessful attempts
				$GLOBALS['egw_info']['server']['global_denied_users'][$this->account_lid] ||
				$auth_check && !$GLOBALS['egw']->auth->authenticate($this->account_lid, $this->passwd, $this->passwd_type) ||
				$this->account_id && $GLOBALS['egw']->accounts->get_type($this->account_id) == 'g')
			{
				$this->reason = $blocked ? 'blocked, too many attempts' : 'bad login or password';
				$this->cd_reason = $blocked ? self::CD_BLOCKED : self::CD_BAD_LOGIN_OR_PASSWORD;

				// we dont log anon users as it would block the website
				if (!$GLOBALS['egw']->acl->get_specific_rights_for_account($this->account_id,'anonymous','phpgwapi'))
				{
					$this->log_access($this->reason,$login,$user_ip,0);	// log unsuccessfull login
				}
				if (self::ERROR_LOG_DEBUG) error_log(__METHOD__."($this->login,$this->passwd,$this->passwd_type,$no_session,$auth_check) UNSUCCESSFULL ($this->reason)");
				return false;
			}
			if ($fail_on_forced_password_change && Auth::check_password_change($this->reason) === false)
			{
				$this->cd_reason = self::CD_FORCE_PASSWORD_CHANGE;
				return false;
			}
			if (!$this->account_id && $GLOBALS['egw_info']['server']['auto_create_acct'])
			{
				if ($GLOBALS['egw_info']['server']['auto_create_acct'] == 'lowercase')
				{
					$this->account_lid = strtolower($this->account_lid);
				}
				$this->account_id = $GLOBALS['egw']->accounts->auto_add($this->account_lid, $passwd);
			}
			// fix maybe wrong case in username, it makes problems eg. in filemanager (name of homedir)
			if ($this->account_lid != ($lid = $GLOBALS['egw']->accounts->id2name($this->account_id)))
			{
				$this->account_lid = $lid;
				$this->login = $lid.substr($this->login,strlen($lid));
			}

			$GLOBALS['egw_info']['user']['account_id'] = $this->account_id;

			// for *DAV and eSync we use a pseudo sessionid created from md5(user:passwd)
			// --> allows this stateless protocolls which use basic auth to use sessions!
			if (($this->sessionid = self::get_sessionid(true)))
			{
				session_id($this->sessionid);
			}
			else
			{
				self::cache_control();
				session_start();
				// set a new session-id, if not syncml (already done in Horde code and can NOT be changed)
				if (!$no_session && $GLOBALS['egw_info']['flags']['currentapp'] != 'syncml')
				{
					session_regenerate_id(true);
				}
				$this->sessionid = session_id();
			}
			$this->kp3       = Auth::randomstring(24);

			$GLOBALS['egw_info']['user'] = $this->read_repositories();
			if ($GLOBALS['egw']->accounts->is_expired($GLOBALS['egw_info']['user']))
			{
				$this->reason = 'account is expired';
				$this->cd_reason = self::CD_ACCOUNT_EXPIRED;

				if (self::ERROR_LOG_DEBUG) error_log(__METHOD__."($this->login,$this->passwd,$this->passwd_type,$no_session,$auth_check) UNSUCCESSFULL ($this->reason)");
				return false;
			}

			Cache::setSession('phpgwapi', 'password', base64_encode($this->passwd));

			if ($GLOBALS['egw']->acl->check('anonymous',1,'phpgwapi'))
			{
				$this->session_flags = 'A';
			}
			else
			{
				$this->session_flags = 'N';
			}

			if (($hook_result = Hooks::process(array(
				'location'       => 'session_creation',
				'sessionid'      => $this->sessionid,
				'session_flags'  => $this->session_flags,
				'account_id'     => $this->account_id,
				'account_lid'    => $this->account_lid,
				'passwd'         => $this->passwd,
				'account_domain' => $this->account_domain,
				'user_ip'        => $user_ip,
			),'',true)))	// true = run hooks from all apps, not just the ones the current user has perms to run
			{
				foreach($hook_result as $reason)
				{
					if ($reason)	// called hook requests to deny the session
					{
						$this->reason = $this->cd_reason = $reason;
						$this->log_access($this->reason,$login,$user_ip,0);		// log unsuccessfull login
						if (self::ERROR_LOG_DEBUG) error_log(__METHOD__."($this->login,$this->passwd,$this->passwd_type,$no_session,$auth_check) UNSUCCESSFULL ($this->reason)");
						return false;
					}
				}
			}
			$GLOBALS['egw']->db->transaction_begin();
			$this->register_session($this->login,$user_ip,$now,$this->session_flags);
			if ($this->session_flags != 'A')		// dont log anonymous sessions
			{
				$this->sessionid_access_log = $this->log_access($this->sessionid,$login,$user_ip,$this->account_id);
			}
			Cache::setSession('phpgwapi', 'account_previous_login', $GLOBALS['egw']->auth->previous_login);
			$GLOBALS['egw']->accounts->update_lastlogin($this->account_id,$user_ip);
			$GLOBALS['egw']->db->transaction_commit();

			if ($GLOBALS['egw_info']['server']['usecookies'] && !$no_session)
			{
				self::egw_setcookie(self::EGW_SESSION_NAME,$this->sessionid);
				self::egw_setcookie('kp3',$this->kp3);
				self::egw_setcookie('domain',$this->account_domain);
			}
			if ($GLOBALS['egw_info']['server']['usecookies'] && !$no_session || isset($_COOKIE['last_loginid']))
			{
				self::egw_setcookie('last_loginid', $this->account_lid ,$now+1209600); /* For 2 weeks */
				self::egw_setcookie('last_domain',$this->account_domain,$now+1209600);
			}
			if (self::ERROR_LOG_DEBUG) error_log(__METHOD__."($this->login,$this->passwd,$this->passwd_type,$no_session,$auth_check) successfull sessionid=$this->sessionid");

			return $this->sessionid;
		}
		// catch all exceptions, as their (allways logged) trace (eg. on a database error) would contain the user password
		catch(Exception $e) {
			$this->reason = $this->cd_reason = $e->getMessage();
			error_log(__METHOD__."('$login', ".array2string(str_repeat('*', strlen($passwd))).
				", '$passwd_type', no_session=".array2string($no_session).
				", auth_check=".array2string($auth_check).
				", fail_on_forced_password_change=".array2string($fail_on_forced_password_change).
				") Exception ".$e->getMessage());
			return false;
		}
	}

	/**
	 * Store eGW specific session-vars
	 *
	 * @param string $login
	 * @param string $user_ip
	 * @param int $now
	 * @param string $session_flags
	 */
	private function register_session($login,$user_ip,$now,$session_flags)
	{
		// restore session vars set before session was started
		if (is_array($this->required_files))
		{
			$_SESSION[self::EGW_REQUIRED_FILES] = !is_array($_SESSION[self::EGW_REQUIRED_FILES]) ? $this->required_files :
				array_unique(array_merge($_SESSION[self::EGW_REQUIRED_FILES],$this->required_files));
			unset($this->required_files);
		}
		$_SESSION[self::EGW_SESSION_VAR] = array(
			'session_id'     => $this->sessionid,
			'session_lid'    => $login,
			'session_ip'     => $user_ip,
			'session_logintime' => $now,
			'session_dla'    => $now,
			'session_action' => $_SERVER['PHP_SELF'],
			'session_flags'  => $session_flags,
			// we need the install-id to differ between serveral installs shareing one tmp-dir
			'session_install_id' => $GLOBALS['egw_info']['server']['install_id']
		);
	}

	/**
	 * name of access-log table
	 */
	const ACCESS_LOG_TABLE = 'egw_access_log';

	/**
    * Write or update (for logout) the access_log
	*
	* @param string|int $sessionid nummeric or PHP session id or 0 for unsuccessful logins
	* @param string $login ='' account_lid (evtl. with domain) or '' for setting the logout-time
	* @param string $user_ip ='' ip to log
	* @param int $account_id =0 numerical account_id
	* @return int $sessionid primary key of egw_access_log for login, null otherwise
	*/
	private function log_access($sessionid,$login='',$user_ip='',$account_id=0)
	{
		$now = time();

		if ($login)
		{
			$GLOBALS['egw']->db->insert(self::ACCESS_LOG_TABLE,array(
				'session_php' => $sessionid,
				'loginid'   => $login,
				'ip'        => $user_ip,
				'li'        => $now,
				'account_id'=> $account_id,
				'user_agent'=> $_SERVER['HTTP_USER_AGENT'],
				'session_dla'    => $now,
				'session_action' => $this->update_dla(false),	// dont update egw_access_log
			),false,__LINE__,__FILE__);

			$ret = $GLOBALS['egw']->db->get_last_insert_id(self::ACCESS_LOG_TABLE,'sessionid');
		}
		else
		{
			if (!is_numeric($sessionid) && $sessionid == $this->sessionid && $this->sessionid_access_log)
			{
				$sessionid = $this->sessionid_access_log;
			}
			$GLOBALS['egw']->db->update(self::ACCESS_LOG_TABLE,array(
				'lo' => $now
			),is_numeric($sessionid) ? array(
				'sessionid' => $sessionid,
			) : array(
				'session_php' => $sessionid,
			),__LINE__,__FILE__);

			// run maintenance only on logout, to not delay login
			if ($GLOBALS['egw_info']['server']['max_access_log_age'])
			{
				$max_age = $now - $GLOBALS['egw_info']['server']['max_access_log_age'] * 24 * 60 * 60;

				$GLOBALS['egw']->db->delete(self::ACCESS_LOG_TABLE,"li < $max_age",__LINE__,__FILE__);
			}
		}
		//error_log(__METHOD__."('$sessionid', '$login', '$user_ip', $account_id) returning ".array2string($ret));
		return $ret;
	}

	/**
	 * Protect against brute force attacks, block login if too many unsuccessful login attmepts
     *
	 * @param string $login account_lid (evtl. with domain)
	 * @param string $ip ip of the user
	 * @returns bool login blocked?
	 */
	private function login_blocked($login,$ip)
	{
		$block_time = time() - $GLOBALS['egw_info']['server']['block_time'] * 60;

		$false_id = $false_ip = 0;
		foreach($GLOBALS['egw']->db->union(array(
			array(
				'table' => self::ACCESS_LOG_TABLE,
				'cols'  => "'false_ip' AS name,COUNT(*) AS num",
				'where' => array(
					'account_id' => 0,
					'ip' => $ip,
					"li > $block_time",
				),
			),
			array(
				'table' => self::ACCESS_LOG_TABLE,
				'cols'  => "'false_id' AS name,COUNT(*) AS num",
				'where' => array(
					'account_id' => 0,
					'loginid' => $login,
					"li > $block_time",
				),
			),
			array(
				'table' => self::ACCESS_LOG_TABLE,
				'cols'  => "'false_id' AS name,COUNT(*) AS num",
				'where' => array(
					'account_id' => 0,
					'loginid LIKE '.$GLOBALS['egw']->db->quote($login.'@%'),
					"li > $block_time",
				)
			),
		), __LINE__, __FILE__) as $row)
		{
			${$row['name']} += $row['num'];
		}
		$blocked = $false_ip > $GLOBALS['egw_info']['server']['num_unsuccessful_ip'] ||
			$false_id > $GLOBALS['egw_info']['server']['num_unsuccessful_id'];
		//error_log(__METHOD__."('$login', '$ip') false_ip=$false_ip, false_id=$false_id --> blocked=".array2string($blocked));

		if ($blocked && $GLOBALS['egw_info']['server']['admin_mails'] &&
			$GLOBALS['egw_info']['server']['login_blocked_mail_time'] < time()-5*60)	// max. one mail every 5mins
		{
			try {
				$mailer = new Mailer();
				// notify admin(s) via email
				$mailer->setFrom('eGroupWare@'.$GLOBALS['egw_info']['server']['mail_suffix']);
				$mailer->addHeader('Subject', lang("eGroupWare: login blocked for user '%1', IP %2",$login,$ip));
				$mailer->setBody(lang("Too many unsucessful attempts to login: %1 for the user '%2', %3 for the IP %4",$false_id,$login,$false_ip,$ip));
				foreach(preg_split('/,\s*/',$GLOBALS['egw_info']['server']['admin_mails']) as $mail)
				{
					$mailer->addAddress($mail);
				}
				$mailer->send();
			}
			catch(Exception $e) {
				// ignore exception, but log it, to block the account and give a correct error-message to user
				error_log(__METHOD__."('$login', '$ip') ".$e->getMessage());
			}
			// save time of mail, to not send to many mails
			$config = new Config('phpgwapi');
			$config->read_repository();
			$config->value('login_blocked_mail_time',time());
			$config->save_repository();
		}
		return $blocked;
	}

	/**
	 * Basename of scripts for which we create a pseudo session-id based on user-credentials
	 *
	 * @var array
	 */
	static $pseudo_session_scripts = array(
		'webdav.php', 'groupdav.php', 'remote.php', 'share.php'
	);

	/**
	 * Get the sessionid from Cookie, Get-Parameter or basic auth
	 *
	 * @param boolean $only_basic_auth =false return only a basic auth pseudo sessionid, default no
	 * @return string
	 */
	static function get_sessionid($only_basic_auth=false)
	{
		// for WebDAV and GroupDAV we use a pseudo sessionid created from md5(user:passwd)
		// --> allows this stateless protocolls which use basic auth to use sessions!
		if (isset($_SERVER['PHP_AUTH_USER']) && isset($_SERVER['PHP_AUTH_PW']) &&
			(in_array(basename($_SERVER['SCRIPT_NAME']), self::$pseudo_session_scripts) ||
				$_SERVER['SCRIPT_NAME'] === '/Microsoft-Server-ActiveSync'))
		{
			// we generate a pseudo-sessionid from the basic auth credentials
			$sessionid = md5($_SERVER['PHP_AUTH_USER'].':'.$_SERVER['PHP_AUTH_PW'].':'.$_SERVER['HTTP_HOST'].':'.
				EGW_SERVER_ROOT.':'.self::getuser_ip().':'.filemtime(EGW_SERVER_ROOT.'/api/setup/setup.inc.php').
				// for ActiveSync we add the DeviceID
				(isset($_GET['DeviceId']) && $_SERVER['SCRIPT_NAME'] === '/Microsoft-Server-ActiveSync' ? ':'.$_GET['DeviceId'] : '').
				':'.$_SERVER['HTTP_USER_AGENT']);
			//error_log(__METHOD__."($only_basic_auth) HTTP_HOST=$_SERVER[HTTP_HOST], PHP_AUTH_USER=$_SERVER[PHP_AUTH_USER], DeviceId=$_GET[DeviceId]: sessionid=$sessionid");
		}
		// same for digest auth
		elseif (isset($_SERVER['PHP_AUTH_DIGEST']) &&
			in_array(basename($_SERVER['SCRIPT_NAME']), self::$pseudo_session_scripts))
		{
			// we generate a pseudo-sessionid from the digest username, realm and nounce
			// can't use full $_SERVER['PHP_AUTH_DIGEST'], as it changes (contains eg. the url)
			$data = Header\Authenticate::parse_digest($_SERVER['PHP_AUTH_DIGEST']);
			$sessionid = md5($data['username'].':'.$data['realm'].':'.$data['nonce'].':'.$_SERVER['HTTP_HOST'].
				EGW_SERVER_ROOT.':'.self::getuser_ip().':'.filemtime(EGW_SERVER_ROOT.'/api/setup/setup.inc.php').
				':'.$_SERVER['HTTP_USER_AGENT']);
		}
		elseif(!$only_basic_auth && isset($_REQUEST[self::EGW_SESSION_NAME]))
		{
			$sessionid = $_REQUEST[self::EGW_SESSION_NAME];
		}
		elseif(!$only_basic_auth && isset($_COOKIE[self::EGW_SESSION_NAME]))
		{
			$sessionid = $_COOKIE[self::EGW_SESSION_NAME];
		}
		else
		{
			$sessionid = false;
		}
		if (self::ERROR_LOG_DEBUG) error_log(__METHOD__."() _SERVER[REQUEST_URI]='$_SERVER[REQUEST_URI]' returning ".print_r($sessionid,true));
		return $sessionid;
	}

	/**
	 * Get request or cookie variable with higher precedence to $_REQUEST then $_COOKIE
	 *
	 * In php < 5.3 that's identical to $_REQUEST[$name], but php5.3+ does no longer register cookied in $_REQUEST by default
	 *
	 * As a workaround for a bug in Safari Version 3.2.1 (5525.27.1), where cookie first letter get's upcased, we check that too.
	 *
	 * @param string $name eg. 'kp3' or domain
	 * @return mixed null if it's neither set in $_REQUEST or $_COOKIE
	 */
	static function get_request($name)
	{
		return isset($_REQUEST[$name]) ? $_REQUEST[$name] :
			(isset($_COOKIE[$name]) ? $_COOKIE[$name] :
			(isset($_COOKIE[$name=ucfirst($name)]) ? $_COOKIE[$name] : null));
	}

	/**
	 * Check to see if a session is still current and valid
	 *
	 * @param string $sessionid session id to be verfied
	 * @param string $kp3 ?? to be verified
	 * @return bool is the session valid?
	 */
	function verify($sessionid=null,$kp3=null)
	{
		if (self::ERROR_LOG_DEBUG) error_log(__METHOD__."('$sessionid','$kp3') ".function_backtrace());

		$fill_egw_info_and_repositories = !$GLOBALS['egw_info']['flags']['restored_from_session'];

		if(!$sessionid)
		{
			$sessionid = self::get_sessionid();
			$kp3       = self::get_request('kp3');
		}

		$this->sessionid = $sessionid;
		$this->kp3       = $kp3;


		if (!$this->sessionid)
		{
			if (self::ERROR_LOG_DEBUG) error_log(__METHOD__."('$sessionid') get_sessionid()='".self::get_sessionid()."' No session ID");
			return false;
		}

		session_name(self::EGW_SESSION_NAME);
		session_id($this->sessionid);
		self::cache_control();
		session_start();

		// check if we have a eGroupware session --> return false if not (but dont destroy it!)
		if (is_null($_SESSION) || !isset($_SESSION[self::EGW_SESSION_VAR]))
		{
			if (self::ERROR_LOG_DEBUG) error_log(__METHOD__."('$sessionid') session does NOT exist!");
			return false;
		}
		$session =& $_SESSION[self::EGW_SESSION_VAR];

		if ($session['session_dla'] <= time() - $GLOBALS['egw_info']['server']['sessions_timeout'])
		{
			if (self::ERROR_LOG_DEBUG) error_log(__METHOD__."('$sessionid') session timed out!");
			$this->destroy($sessionid,$kp3);
			return false;
		}

		$this->session_flags = $session['session_flags'];

		$this->split_login_domain($session['session_lid'],$this->account_lid,$this->account_domain);

		// This is to ensure that we authenticate to the correct domain (might not be default)
		if($GLOBALS['egw_info']['user']['domain'] && $this->account_domain != $GLOBALS['egw_info']['user']['domain'])
		{
			return false;	// session not verified, domain changed
		}
		$GLOBALS['egw_info']['user']['kp3'] = $this->kp3;

		// allow xajax / notifications to not update the dla, so sessions can time out again
		if (!isset($GLOBALS['egw_info']['flags']['no_dla_update']) || !$GLOBALS['egw_info']['flags']['no_dla_update'])
		{
			$this->update_dla();
		}
		elseif ($GLOBALS['egw_info']['flags']['currentapp'] == 'notifications')
		{
			$this->update_notification_heartbeat();
		}
		$this->account_id = $GLOBALS['egw']->accounts->name2id($this->account_lid,'account_lid','u');
		if (!$this->account_id)
		{
			if (self::ERROR_LOG_DEBUG) error_log("*** Session::verify($sessionid) !accounts::name2id('$this->account_lid')");
			return false;
		}

		$GLOBALS['egw_info']['user']['account_id'] = $this->account_id;

		if ($fill_egw_info_and_repositories)
		{
			$GLOBALS['egw_info']['user'] = $this->read_repositories();
		}
		else
		{
			// update prefs, which might be changed by an other session
			$GLOBALS['egw_info']['user']['preferences'] = $GLOBALS['egw']->preferences->read_repository();
		}

		if ($GLOBALS['egw']->accounts->is_expired($GLOBALS['egw_info']['user']))
		{
			if (self::ERROR_LOG_DEBUG) error_log("*** Session::verify($sessionid) accounts is expired");
			return false;
		}
		$this->passwd = base64_decode(Cache::getSession('phpgwapi', 'password'));
		if ($fill_egw_info_and_repositories)
		{
			$GLOBALS['egw_info']['user']['session_ip'] = $session['session_ip'];
			$GLOBALS['egw_info']['user']['passwd']     = $this->passwd;
		}
		if ($this->account_domain != $GLOBALS['egw_info']['user']['domain'])
		{
			if (self::ERROR_LOG_DEBUG) error_log("*** Session::verify($sessionid) wrong domain");
			return false;
		}

		if ($GLOBALS['egw_info']['server']['sessions_checkip'])
		{
			if (strtoupper(substr(PHP_OS,0,3)) != 'WIN' && (!$GLOBALS['egw_info']['user']['session_ip'] ||
				$GLOBALS['egw_info']['user']['session_ip'] != $this->getuser_ip()))
			{
				if (self::ERROR_LOG_DEBUG) error_log("*** Session::verify($sessionid) wrong IP");
				return false;
			}
		}

		if ($fill_egw_info_and_repositories)
		{
			$GLOBALS['egw']->acl->__construct($this->account_id);
			$GLOBALS['egw']->preferences->__construct($this->account_id);
			$GLOBALS['egw']->applications->__construct($this->account_id);
		}
		if (!$this->account_lid)
		{
			if (self::ERROR_LOG_DEBUG) error_log("*** Session::verify($sessionid) !account_lid");
			return false;
		}

		// query accesslog-id, if not set in session (session is made persistent after login!)
		if (!$this->sessionid_access_log && $this->session_flags != 'A')
		{
			$this->sessionid_access_log = $GLOBALS['egw']->db->select(self::ACCESS_LOG_TABLE,'sessionid',array(
				'session_php' => $this->sessionid,
			),__LINE__,__FILE__)->fetchColumn();
			//error_log(__METHOD__."() sessionid=$this->sessionid --> sessionid_access_log=$this->sessionid_access_log");
		}

		// check if we use cookies for the session, but no cookie set
		// happens eg. in sitemgr (when redirecting to a different domain) or with new java notification app
		if ($GLOBALS['egw_info']['server']['usecookies'] && isset($_REQUEST[self::EGW_SESSION_NAME]) &&
			$_REQUEST[self::EGW_SESSION_NAME] === $this->sessionid &&
			(!isset($_COOKIE[self::EGW_SESSION_NAME]) || $_COOKIE[self::EGW_SESSION_NAME] !== $_REQUEST[self::EGW_SESSION_NAME]))
		{
			if (self::ERROR_LOG_DEBUG) error_log("--> Session::verify($sessionid) SUCCESS, but NO required cookies set --> setting them now");
			self::egw_setcookie(self::EGW_SESSION_NAME,$this->sessionid);
			self::egw_setcookie('kp3',$this->kp3);
			self::egw_setcookie('domain',$this->account_domain);
		}

		if (self::ERROR_LOG_DEBUG) error_log("--> Session::verify($sessionid) SUCCESS");

		return true;
	}

	/**
	 * Terminate a session
	 *
	 * @param int|string $sessionid nummeric or php session id of session to be terminated
	 * @param string $kp3
	 * @return boolean true on success, false on error
	 */
	function destroy($sessionid, $kp3='')
	{
		if (!$sessionid && $kp3)
		{
			return false;
		}
		$this->log_access($sessionid);	// log logout-time

		if (self::ERROR_LOG_DEBUG) error_log(__METHOD__."($sessionid,$kp3)");

		if (is_numeric($sessionid))	// do we have a access-log-id --> get PHP session id
		{
			$sessionid = $GLOBALS['egw']->db->select(self::ACCESS_LOG_TABLE,'session_php',array(
					'sessionid' => $sessionid,
				),__LINE__,__FILE__)->fetchColumn();
		}

		Hooks::process(array(
			'location'  => 'session_destroyed',
			'sessionid' => $sessionid,
		),'',true);	// true = run hooks from all apps, not just the ones the current user has perms to run

		// Only do the following, if where working with the current user
		if (!$GLOBALS['egw_info']['user']['sessionid'] || $sessionid == $GLOBALS['egw_info']['user']['sessionid'])
		{
			if (self::ERROR_LOG_DEBUG) error_log(__METHOD__." ********* about to call session_destroy!");
			session_unset();
			@session_destroy();
			// we need to (re-)load the eGW session-handler, as session_destroy unloads custom session-handlers
			if (function_exists('init_session_handler'))
			{
				init_session_handler();
			}

			if ($GLOBALS['egw_info']['server']['usecookies'])
			{
				self::egw_setcookie(session_name());
			}
		}
		else
		{
			$this->commit_session();	// close our own session

			session_id($sessionid);
			if (session_start())
			{
				session_destroy();
			}
		}
		return true;
	}

	/**
	 * Generate a url which supports url or cookies based sessions
	 *
	 * Please note, the values of the query get url encoded!
	 *
	 * @param string $url a url relative to the egroupware install root, it can contain a query too
	 * @param array|string $extravars query string arguements as string or array (prefered)
	 * 	if string is used ambersands in vars have to be already urlencoded as '%26', function ensures they get NOT double encoded
	 * @return string generated url
	 */
	public static function link($url, $extravars = '')
	{
		//error_log(_METHOD__."(url='$url',extravars='".array2string($extravars)."')");

		if ($url[0] != '/')
		{
			$app = $GLOBALS['egw_info']['flags']['currentapp'];
			if ($app != 'login' && $app != 'logout')
			{
				$url = $app.'/'.$url;
			}
		}

		// append the url to the webserver url, but avoid more then one slash between the parts of the url
		$webserver_url = $GLOBALS['egw_info']['server']['webserver_url'];
		// patch inspired by vladimir kolobkov -> we should not try to match the webserver url against the url without '/' as delimiter,
		// as $webserver_url may be part of $url (as /egw is part of phpgwapi/js/egw_instant_load.html)
		if (($url[0] != '/' || $webserver_url != '/') && (!$webserver_url || strpos($url, $webserver_url.'/') === false))
		{
			if($url[0] != '/' && substr($webserver_url,-1) != '/')
			{
				$url = $webserver_url .'/'. $url;
			}
			else
			{
				$url = $webserver_url . $url;
			}
		}

		if(isset($GLOBALS['egw_info']['server']['enforce_ssl']) && $GLOBALS['egw_info']['server']['enforce_ssl'])
		{
			if(substr($url ,0,4) != 'http')
			{
				$url = 'https://'.$_SERVER['HTTP_HOST'].$url;
			}
			else
			{
				$url = str_replace ( 'http:', 'https:', $url);
			}
		}
		$vars = array();
		// add session params if not using cookies
		if (!$GLOBALS['egw_info']['server']['usecookies'])
		{
			$vars[self::EGW_SESSION_NAME] = $GLOBALS['egw']->session->sessionid;
			$vars['kp3'] = $GLOBALS['egw']->session->kp3;
			$vars['domain'] = $GLOBALS['egw']->session->account_domain;
		}

		// check if the url already contains a query and ensure that vars is an array and all strings are in extravars
		list($ret_url,$othervars) = explode('?', $url, 2);
		if ($extravars && is_array($extravars))
		{
			$vars += $extravars;
			$extravars = $othervars;
		}
		else
		{
			if ($othervars) $extravars .= ($extravars?'&':'').$othervars;
		}

		// parse extravars string into the vars array
		if ($extravars)
		{
			foreach(explode('&',$extravars) as $expr)
			{
				list($var,$val) = explode('=', $expr,2);
				if (strpos($val,'%26') != false) $val = str_replace('%26','&',$val);	// make sure to not double encode &
				if (substr($var,-2) == '[]')
				{
					$vars[substr($var,0,-2)][] = $val;
				}
				else
				{
					$vars[$var] = $val;
				}
			}
		}

		// if there are vars, we add them urlencoded to the url
		if (count($vars))
		{
			$query = array();
			foreach($vars as $key => $value)
			{
				if (is_array($value))
				{
					foreach($value as $val)
					{
						$query[] = $key.'[]='.urlencode($val);
					}
				}
				else
				{
					$query[] = $key.'='.urlencode($value);
				}
			}
			$ret_url .= '?' . implode('&',$query);
		}
		return $ret_url;
	}

	/**
	 * Get the ip address of current users
	 *
	 * @return string ip address
	 */
	public static function getuser_ip()
	{
		return isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : $_SERVER['REMOTE_ADDR'];
	}

	/**
	 * domain for cookies
	 *
	 * @var string
	 */
	private static $cookie_domain = '';

	/**
	 * path for cookies
	 *
	 * @var string
	 */
	private static $cookie_path = '/';

	/**
	 * iOS web-apps will loose cookie if set with a livetime of 0 / session-cookie
	 *
	 * Therefore we set a fixed lifetime of 24h from session-start instead.
	 * Server-side session will timeout earliert anyway, if there's no activity.
	 */
	const IOS_SESSION_COOKIE_LIFETIME = 86400;

	/**
	 * Set a cookie with eGW's cookie-domain and -path settings
	 *
	 * @param string $cookiename name of cookie to be set
	 * @param string $cookievalue ='' value to be used, if unset cookie is cleared (optional)
	 * @param int $cookietime =0 when cookie should expire, 0 for session only (optional)
	 * @param string $cookiepath =null optional path (eg. '/') if the eGW install-dir should not be used
	 */
	public static function egw_setcookie($cookiename,$cookievalue='',$cookietime=0,$cookiepath=null)
	{
		if (empty(self::$cookie_domain) || empty(self::$cookie_path))
		{
			self::set_cookiedomain();
		}
		if (self::ERROR_LOG_DEBUG) error_log(__METHOD__."($cookiename,$cookievalue,$cookietime,$cookiepath,".self::$cookie_domain.")");

		// if we are installed in iOS as web-app, we must not set a cookietime==0 (session-cookie),
		// as every change between apps will cause the cookie to get lost
		static $is_iOS = null;
		if (!$cookietime && !isset($is_iOS)) $is_iOS = (bool)preg_match('/^(iPhone|iPad|iPod)/i', Header\UserAgent::mobile());

		if(!headers_sent())	// gives only a warning, but can not send the cookie anyway
		{
			setcookie($cookiename, $cookievalue,
				!$cookietime && $is_iOS ? time()+self::IOS_SESSION_COOKIE_LIFETIME : $cookietime,
				is_null($cookiepath) ? self::$cookie_path : $cookiepath,self::$cookie_domain,
				// if called via HTTPS, only send cookie for https and only allow cookie access via HTTP (true)
				empty($GLOBALS['egw_info']['server']['insecure_cookies']) && !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', true);
		}
	}

	/**
	 * Set the domain and path used for cookies
	 */
	private static function set_cookiedomain()
	{
		if ($GLOBALS['egw_info']['server']['cookiedomain'])
		{
			// Admin set domain, eg. .domain.com to allow egw.domain.com and www.domain.com
			self::$cookie_domain = $GLOBALS['egw_info']['server']['cookiedomain'];
		}
		else
		{
			// Use HTTP_X_FORWARDED_HOST if set, which is the case behind a none-transparent proxy
			self::$cookie_domain = isset($_SERVER['HTTP_X_FORWARDED_HOST']) ?  $_SERVER['HTTP_X_FORWARDED_HOST'] : $_SERVER['HTTP_HOST'];
		}
		// remove port from HTTP_HOST
		$arr = null;
		if (preg_match("/^(.*):(.*)$/",self::$cookie_domain,$arr))
		{
			self::$cookie_domain = $arr[1];
		}
		if (count(explode('.',self::$cookie_domain)) <= 1)
		{
			// setcookie dont likes domains without dots, leaving it empty, gets setcookie to fill the domain in
			self::$cookie_domain = '';
		}
		if (!$GLOBALS['egw_info']['server']['cookiepath'] ||
			!(self::$cookie_path = parse_url($GLOBALS['egw_info']['server']['webserver_url'],PHP_URL_PATH)))
		{
			self::$cookie_path = '/';
		}

		session_set_cookie_params(0, self::$cookie_path, self::$cookie_domain,
			// if called via HTTPS, only send cookie for https and only allow cookie access via HTTP (true)
			empty($GLOBALS['egw_info']['server']['insecure_cookies']) && !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', true);
	}

	/**
	 * Search the instance matching the request
	 *
	 * @param string $login on login $_POST['login'], $_SERVER['PHP_AUTH_USER'] or $_SERVER['REMOTE_USER']
	 * @param string $domain_requested usually self::get_request('domain')
	 * @param string &$default_domain usually $default_domain get's set eg. by sitemgr
	 * @param string|array $server_names usually array($_SERVER['HTTP_HOST'], $_SERVER['SERVER_NAME'])
	 * @param array $domains =null defaults to $GLOBALS['egw_domain'] from the header
	 * @return string $GLOBALS['egw_info']['user']['domain'] set with the domain/instance to use
	 */
	public static function search_instance($login,$domain_requested,&$default_domain,$server_names,array $domains=null)
	{
		if (self::ERROR_LOG_DEBUG) error_log(__METHOD__."('$login','$domain_requested',".array2string($default_domain).".".array2string($server_names).".".array2string($domains).")");

		if (is_null($domains)) $domains = $GLOBALS['egw_domain'];

		if (!isset($default_domain) || !isset($domains[$default_domain]))	// allow to overwrite the default domain
		{
			foreach((array)$server_names as $server_name)
			{
				list($server_name) = explode(':', $server_name);	// remove port from HTTP_HOST
				if(isset($domains[$server_name]))
				{
					$default_domain = $server_name;
					break;
				}
				else
				{
					$parts = explode('.', $server_name);
					array_shift($parts);
					$domain_part = implode('.', $parts);
					if(isset($domains[$domain_part]))
					{
						$default_domain = $domain_part;
						break;
					}
					else
					{
						reset($domains);
						list($default_domain) = each($domains);
					}
					unset($domain_part);
				}
			}
		}
		if (isset($login))	// on login
		{
			if (strpos($login,'@') === false || count($domains) == 1)
			{
				$login .= '@' . (isset($_POST['logindomain']) ? $_POST['logindomain'] : $default_domain);
			}
			$parts = explode('@',$login);
			$domain = array_pop($parts);
			$GLOBALS['login'] = $login;
		}
		else	// on "normal" pageview
		{
			$domain = $domain_requested;
		}
		if (!isset($domains[$domain]))
		{
			$domain = $default_domain;
		}
		if (self::ERROR_LOG_DEBUG) error_log(__METHOD__."() default_domain=".array2string($default_domain).', login='.array2string($login)." returning ".array2string($domain));

		return $domain;
	}

	/**
	 * Set action logged in access-log
	 *
	 * @param string $action
	 */
	public function set_action($action)
	{
		$this->action = $action;
	}

	/**
	 * Update session_action and session_dla (session last used time)
	 *
	 * @param boolean $update_access_log =false false: dont update egw_access_log table, but set $this->action
	 * @return string action as written to egw_access_log.session_action
	 */
	private function update_dla($update_access_log=false)
	{
		// This way XML-RPC users aren't always listed as xmlrpc.php
		if (!$update_access_log)
		{
			if ($this->xmlrpc_method_called)
			{
				$action = $this->xmlrpc_method_called;
			}
			elseif (isset($_GET['menuaction']))
			{
				$action = $_GET['menuaction'];
			}
			else
			{
				$action = $_SERVER['PHP_SELF'];
				// remove EGroupware path, if not installed in webroot
				$egw_path = $GLOBALS['egw_info']['server']['webserver_url'];
				if ($egw_path[0] != '/') $egw_path = parse_url($egw_path,PHP_URL_PATH);
				if ($action == '/Microsoft-Server-ActiveSync')
				{
					$action .= '?Cmd='.$_GET['Cmd'].'&DeviceId='.$_GET['DeviceId'];
				}
				elseif ($egw_path)
				{
					list(,$action) = explode($egw_path,$action,2);
				}
			}
			$this->set_action($action);
		}
		// update dla in access-log table, if we have an access-log row (non-anonymous session)
		if ($this->sessionid_access_log && $update_access_log && is_object($GLOBALS['egw']->db))
		{
			$GLOBALS['egw']->db->update(self::ACCESS_LOG_TABLE,array(
				'session_dla' => time(),
				'session_action' => $this->action,
				'lo' => null,	// just in case it was (automatic) timed out before
			),array(
				'sessionid' => $this->sessionid_access_log,
			),__LINE__,__FILE__);
		}

		$_SESSION[self::EGW_SESSION_VAR]['session_dla'] = time();
		$_SESSION[self::EGW_SESSION_VAR]['session_action'] = $this->action;
		if (self::ERROR_LOG_DEBUG) error_log(__METHOD__.'() _SESSION['.self::EGW_SESSION_VAR.']='.array2string($_SESSION[self::EGW_SESSION_VAR]));

		return $this->action;
	}

	/**
	 * Update notification_heartbeat time of session
	 */
	private function update_notification_heartbeat()
	{
		// update dla in access-log table, if we have an access-log row (non-anonymous session)
		if ($this->sessionid_access_log)
		{
			$GLOBALS['egw']->db->update(self::ACCESS_LOG_TABLE,array(
				'notification_heartbeat' => time(),
			),array(
				'sessionid' => $this->sessionid_access_log,
				'lo IS NULL',
			),__LINE__,__FILE__);
		}
	}

	/**
	 * Read the diverse repositories / init classes with data from the just loged in user
	 *
	 * @return array used to assign to $GLOBALS['egw_info']['user']
	 */
	public function read_repositories()
	{
		$GLOBALS['egw']->acl->__construct($this->account_id);
		$GLOBALS['egw']->preferences->__construct($this->account_id);
		$GLOBALS['egw']->applications->__construct($this->account_id);

		$user = $GLOBALS['egw']->accounts->read($this->account_id);
		// set homedirectory from auth_ldap or auth_ads, to be able to use it in vfs
		if (!isset($user['homedirectory']))
		{
			// authentication happens in login.php, which does NOT yet create egw-object in session
			// --> need to store homedirectory in session
			if(isset($GLOBALS['auto_create_acct']['homedirectory']))
			{
				Cache::setSession(__CLASS__, 'homedirectory',
					$user['homedirectory'] = $GLOBALS['auto_create_acct']['homedirectory']);
			}
			else
			{
				$user['homedirectory'] = Cache::getSession(__CLASS__, 'homedirectory');
			}
		}
		$user['preferences'] = $GLOBALS['egw']->preferences->read_repository();
		if (is_object($GLOBALS['egw']->datetime))
		{
			$GLOBALS['egw']->datetime->__construct();		// to set tz_offset from the now read prefs
		}
		$user['apps']        = $GLOBALS['egw']->applications->read_repository();
		$user['domain']      = $this->account_domain;
		$user['sessionid']   = $this->sessionid;
		$user['kp3']         = $this->kp3;
		$user['session_ip']  = $this->getuser_ip();
		$user['session_lid'] = $this->account_lid.'@'.$this->account_domain;
		$user['account_id']  = $this->account_id;
		$user['account_lid'] = $this->account_lid;
		$user['userid']      = $this->account_lid;
		$user['passwd']      = $this->passwd;

		return $user;
	}

	/**
	 * Splits a login-name into account_lid and eGW-domain/-instance
	 *
	 * @param string $login login-name (ie. user@default)
	 * @param string &$account_lid returned account_lid (ie. user)
	 * @param string &$domain returned domain (ie. domain)
	 */
	private function split_login_domain($login,&$account_lid,&$domain)
	{
		$parts = explode('@',$login);

		//conference - for strings like vinicius@thyamad.com@default ,
		//allows that user have a login that is his e-mail. (viniciuscb)
		if (count($parts) > 1)
		{
			$probable_domain = array_pop($parts);
			//Last part of login string, when separated by @, is a domain name
			if (in_array($probable_domain,$this->egw_domains))
			{
				$got_login = true;
				$domain = $probable_domain;
				$account_lid = implode('@',$parts);
			}
		}

		if (!$got_login)
		{
			$domain = $GLOBALS['egw_info']['server']['default_domain'];
			$account_lid = $login;
		}
	}

	/**
	 * Create a hash from user and pw
	 *
	 * Can be used to check setup config user/password inside egroupware:
	 *
	 * if (Api\Session::user_pw_hash($user,$pw) === $GLOBALS['egw_info']['server']['config_hash'])
	 *
	 * @param string $user username
	 * @param string $password password or md5 hash of password if $allow_password_md5
	 * @param boolean $allow_password_md5 =false can password alread be an md5 hash
	 * @return string
	 */
	static function user_pw_hash($user,$password,$allow_password_md5=false)
	{
		$password_md5 = $allow_password_md5 && preg_match('/^[a-f0-9]{32}$/',$password) ? $password : md5($password);

		$hash = sha1(strtolower($user).$password_md5);

		return $hash;
	}

	/**
	 * Initialise the used session handler
	 */
	public static function init_handler()
	{
		if (self::ERROR_LOG_DEBUG) error_log(__METHOD__.'() session_handler='.self::$session_handler.', egw_info[server][session_handler]='.$GLOBALS['egw_info']['server']['session_handler'].' called from:'.function_backtrace());

		ini_set('session.use_cookies',0);	// disable the automatic use of cookies, as it uses the path / by default
		session_name(self::EGW_SESSION_NAME);
		if (($sessionid = self::get_sessionid()))
		{
		 	session_id($sessionid);
		 	self::cache_control();
			$ok = session_start();
			self::decrypt();
			if (self::ERROR_LOG_DEBUG) error_log(__METHOD__."() sessionid=$sessionid, _SESSION[".self::EGW_SESSION_VAR.']='.array2string($_SESSION[self::EGW_SESSION_VAR]));
			return $ok;
		}
		if (self::ERROR_LOG_DEBUG) error_log(__METHOD__."() no active session!");

		return false;
	}

	/**
	 * Controling caching and expires header
	 *
	 * Headers are send based on given parameters or $GLOBALS['egw_info']['flags']['nocachecontrol']:
	 * - not set of false --> no caching (default)
	 * - true --> private caching by browser (no expires header)
	 * - "public" or integer --> public caching with given cache_expire in minutes or php.ini default session_cache_expire
	 *
	 * @param int $expire =null expiration time in seconds, default $GLOBALS['egw_info']['flags']['nocachecontrol'] or php.ini session.cache_expire
	 * @param int $private =null allows to set private caching with given expiration time, by setting it to true
	 */
	public static function cache_control($expire=null, $private=null)
	{
		if (is_null($expire) && isset($GLOBALS['egw_info']['flags']['nocachecontrol']) && is_int($GLOBALS['egw_info']['flags']['nocachecontrol']))
		{
			$expire = $GLOBALS['egw_info']['flags']['nocachecontrol'];
		}
		// session not yet started: use PHP session_cache_limiter() and session_cache_expires() functions
		if (!isset($_SESSION))
		{
			// controling caching and expires header
			if(!isset($expire) && (!isset($GLOBALS['egw_info']['flags']['nocachecontrol']) ||
				!$GLOBALS['egw_info']['flags']['nocachecontrol']))
			{
				session_cache_limiter('nocache');
			}
			elseif (isset($expire) || $GLOBALS['egw_info']['flags']['nocachecontrol'] === 'public' || is_int($GLOBALS['egw_info']['flags']['nocachecontrol']))
			{
				// allow public caching: proxys, cdns, ...
				if (isset($expire))
				{
					session_cache_expire((int)ceil($expire/60));	// in minutes
				}
				session_cache_limiter($private ? 'private' : 'public');
			}
			else
			{
				// allow caching by browser
				session_cache_limiter('private_no_expire');
			}
		}
		// session already started
		if (isset($_SESSION))
		{
			if ($expire && (session_cache_limiter() !== ($expire===true?'private_no_expire':'public') ||
				is_int($expire) && $expire/60 !== session_cache_expire()))
			{
				$file = $line = null;
				if (headers_sent($file, $line))
				{
					error_log(__METHOD__."($expire) called, but header already sent in $file: $line");
					return;
				}
				if($expire === true)	// same behavior as session_cache_limiter('private_no_expire')
				{
					header('Cache-Control: private, max-age='.(60*session_cache_expire()));
					header_remove('Expires');
				}
				elseif ($private)
				{
					header('Cache-Control: private, max-age='.$expire);
					header('Expires: ' . gmdate('D, d M Y H:i:s', time()+$expire) . ' GMT');
				}
				else
				{
					header('Cache-Control: public, max-age='.$expire);
					header('Expires: ' . gmdate('D, d M Y H:i:s', time()+$expire) . ' GMT');
				}
				// remove Pragma header, might be set by old header
				if (function_exists('header_remove'))	// PHP 5.3+
				{
					header_remove('Pragma');
				}
				else
				{
					header('Pragma:');
				}
			}
		}
	}

	/**
	 * Get a session list (of the current instance)
	 *
	 * @param int $start
	 * @param string $sort ='DESC' ASC or DESC
	 * @param string $order ='session_dla' session_lid, session_id, session_started, session_logintime, session_action, or (default) session_dla
	 * @param boolean $all_no_sort =False skip sorting and limiting to maxmatchs if set to true
	 * @param array $filter =array() extra filter for sessions
	 * @return array with sessions (values for keys as in $sort)
	 */
	public static function session_list($start,$sort='DESC',$order='session_dla',$all_no_sort=False,array $filter=array())
	{
		$sessions = array();
		if (!preg_match('/^[a-z0-9_ ,]+$/i',$order_by=$order.' '.$sort) || $order_by == ' ')
		{
			$order_by = 'session_dla DESC';
		}
		$filter['lo'] = null;
		$filter[] = 'account_id>0';
		$filter[] = 'session_dla > '.(int)(time() - $GLOBALS['egw_info']['server']['sessions_timeout']);
		$filter[] = '(notification_heartbeat IS NULL OR notification_heartbeat > '.self::heartbeat_limit().')';
		foreach($GLOBALS['egw']->db->select(self::ACCESS_LOG_TABLE, '*', $filter, __LINE__, __FILE__,
			$all_no_sort ? false : $start, 'ORDER BY '.$order_by) as $row)
		{
			$sessions[$row['sessionid']] = $row;
		}
		return $sessions;
	}

	/**
	 * Query number of sessions (not more then once every N secs)
	 *
	 * @param array $filter =array() extra filter for sessions
	 * @return int number of active sessions
	 */
	public static function session_count(array $filter=array())
	{
		$filter['lo'] = null;
		$filter[] = 'account_id>0';
		$filter[] = 'session_dla > '.(int)(time() - $GLOBALS['egw_info']['server']['sessions_timeout']);
		$filter[] = '(notification_heartbeat IS NULL OR notification_heartbeat > '.self::heartbeat_limit().')';
		return $GLOBALS['egw']->db->select(self::ACCESS_LOG_TABLE, 'COUNT(*)', $filter, __LINE__, __FILE__)->fetchColumn();
	}

	/**
	 * Get limit / latest time of heartbeat for session to be active
	 *
	 * @return int TS in server-time
	 */
	public static function heartbeat_limit()
	{
		static $limit=null;

		if (is_null($limit))
		{
			$config = Config::read('notifications');
			if (!($popup_poll_interval  = $config['popup_poll_interval']))
			{
				$popup_poll_interval = 60;
			}
			$limit = (int)(time() - $popup_poll_interval-10);	// 10s grace periode
		}
		return $limit;
	}

	/**
	 * Check if given user can be reached via notifications
	 *
	 * Checks if notifications callback checked in not more then heartbeat_limit() seconds ago
	 *
	 * @param int $account_id
	 * @param int number of active sessions of given user with notifications running
	 */
	public static function notifications_active($account_id)
	{
		return $GLOBALS['egw']->db->select(self::ACCESS_LOG_TABLE, 'COUNT(*)', array(
				'lo' => null,
				'session_dla > '.(int)(time() - $GLOBALS['egw_info']['server']['sessions_timeout']),
				'account_id' => $account_id,
				'notification_heartbeat > '.self::heartbeat_limit(),
		), __LINE__, __FILE__)->fetchColumn();
	}
}
