<?php
/**
 * eGroupWare API - Authentication baseclass
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <ralfbecker@outdoor-training.de>
 * @author Miles Lott <milos@groupwhere.org>
 * @copyright 2004 by Miles Lott <milos@groupwhere.org>
 * @license http://opensource.org/licenses/lgpl-license.php LGPL - GNU Lesser General Public License
 * @package api
 * @subpackage authentication
 * @version $Id$
 */

// allow to set an application depending authentication type (eg. for syncml, groupdav, ...)
if (isset($GLOBALS['egw_info']['server']['auth_type_'.$GLOBALS['egw_info']['flags']['currentapp']]) &&
	$GLOBALS['egw_info']['server']['auth_type_'.$GLOBALS['egw_info']['flags']['currentapp']])
{
	$GLOBALS['egw_info']['server']['auth_type'] = $GLOBALS['egw_info']['server']['auth_type_'.$GLOBALS['egw_info']['flags']['currentapp']];
}
if(empty($GLOBALS['egw_info']['server']['auth_type']))
{
	$GLOBALS['egw_info']['server']['auth_type'] = 'sql';
}
//error_log('using auth_type='.$GLOBALS['egw_info']['server']['auth_type'].', currentapp='.$GLOBALS['egw_info']['flags']['currentapp']);

/**
 * eGroupWare API - Authentication baseclass, password auth and crypt functions
 *
 * Many functions based on code from Frank Thomas <frank@thomas-alfeld.de>
 * which can be seen at http://www.thomas-alfeld.de/frank/
 *
 * Other functions from class.common.inc.php originally from phpGroupWare
 */
class auth
{
	static $error;

	/**
	 * Holds instance of backend
	 *
	 * @var auth_backend
	 */
	private $backend;

	function __construct()
	{
		$backend_class = 'auth_'.$GLOBALS['egw_info']['server']['auth_type'];

		$this->backend = new $backend_class;

		if (!($this->backend instanceof auth_backend))
		{
			throw new egw_exception_assertion_failed("Auth backend class $backend_class is NO auth_backend!");
		}
	}

	/**
	 * check if users are supposed to change their password every x sdays, then check if password is of old age
	 * or the devil-admin reset the users password and forced the user to change his password on next login.
	 *
	 * @param string $app to know where you are/ or where you want to go
	 * @param string $class to know where you are/ or where you want to go
	 * @param string $method to know where you are/ or where you want to go
	 * @return boolean true if check determined, that you passed the test, otherwise void, as we get redirected
	 */
	static function check_password_age($app='', $class='', $method='')
	{
		// dont check anything for anonymous sessions/ users that are flagged as anonymous
		if (is_object($GLOBALS['egw']->session) && $GLOBALS['egw']->session->session_flags == 'A') return true;
		// some statics (and initialisation to make information and timecalculation a) more readable in conditions b) persistent per request
		// if user has to be warned about an upcomming passwordchange, remember for the session, that he was informed
		static $UserKnowsAboutPwdChange=null;
		if (is_null($UserKnowsAboutPwdChange)) $UserKnowsAboutPwdChange =& egw_cache::getSession('phpgwapi','auth_UserKnowsAboutPwdChange');
		// retrieve the timestamp regarding the last change of the password from auth system and store it with the session
		static $alpwchange_val=null;
		static $pwdTsChecked=null;
		if (is_null($pwdTsChecked) && is_null($alpwchange_val) || (string)$alpwchange_val === '0')
		{
			$alpwchange_val =& egw_cache::getSession('phpgwapi','auth_alpwchange_val'); // set that one with the session stored value
			// initalize statics - better readability of conditions
			if (is_null($alpwchange_val) || (string)$alpwchange_val === '0')
			{
				$backend_class = 'auth_'.$GLOBALS['egw_info']['server']['auth_type'];
				$backend = new $backend_class;
				// this may change behavior, as it should detect forced PasswordChanges from your Authentication System too.
				// on the other side, if your auth system does not require an forcedPasswordChange, you will not be asked.
				if (method_exists($backend,'getLastPwdChange'))
				{
					$alpwchange_val = $backend->getLastPwdChange($GLOBALS['egw_info']['user']['account_lid']);
					$pwdTsChecked = true;
				}
				// if your authsystem does not provide that information, its likely, that you cannot change your password there,
				// thus checking for expiration, is not needed
				if ($alpwchange_val === false)
				{
					$alpwchange_val = null;
				}
				//error_log(__METHOD__.__LINE__.'#'.$alpwchange_val.'# is null:'.is_null($alpwchange_val).'# is empty:'.empty($alpwchange_val).'# is set:'.isset($alpwchange_val));
			}
		}
		static $passwordAgeBorder=null;
		static $daysLeftUntilChangeReq=null;
		// some debug output and develop options to move the horizons and warn levels around
		//$GLOBALS['egw_info']['server']['change_pwd_every_x_days'] =35;
		//$GLOBALS['egw_info']['server']['warn_about_upcoming_pwd_change']=5;
		//echo egw_time::to('now','ts').'<br>';
		//echo "User changed password at:".egw_time::to($GLOBALS['egw_info']['user'][$alpwchange]).'<br>';
		//echo "User password is ".((egw_time::to('now','ts')-$GLOBALS['egw_info']['user'][$alpwchange])/86400)." days old<br>";
		//echo "Users must change passwords every ".$GLOBALS['egw_info']['server']['change_pwd_every_x_days'].' days ('.($GLOBALS['egw_info']['server']['change_pwd_every_x_days']*86400).') seconds.<br>';
		//error_log(__METHOD__.__LINE__.'#'.$alpwchange_val.'# is null:'.is_null($alpwchange_val).'# is empty:'.empty($alpwchange_val).'# is set:'.isset($alpwchange_val));
		//echo egw_time::to('now','ts')-($GLOBALS['egw_info']['server']['change_pwd_every_x_days']*86400).'<br>';
		// if neither timestamp isset return true, nothing to do (exept this means the password is too old)
		if (is_null($alpwchange_val) &&
			empty($GLOBALS['egw_info']['server']['change_pwd_every_x_days'])
		) return true;
		if (is_null($passwordAgeBorder) && $GLOBALS['egw_info']['server']['change_pwd_every_x_days'])
		{
			$passwordAgeBorder = (egw_time::to('now','ts')-($GLOBALS['egw_info']['server']['change_pwd_every_x_days']*86400));
		}
		if (is_null($daysLeftUntilChangeReq) && $GLOBALS['egw_info']['server']['warn_about_upcoming_pwd_change'])
		{
			// maxage - passwordage = days left until change is required
			$daysLeftUntilChangeReq = ($GLOBALS['egw_info']['server']['change_pwd_every_x_days'] - ((egw_time::to('now','ts')-($alpwchange_val?$alpwchange_val:0))/86400));
		}
		//echo "Warn about the upcomming change ".$GLOBALS['egw_info']['server']['warn_about_upcoming_pwd_change'].' days before that time is reached<br>';
		//$result = $GLOBALS['egw_info']['server']['change_pwd_every_x_days'] - $daysLeftUntilChangeReq;
		//echo $GLOBALS['egw_info']['server']['change_pwd_every_x_days'].' - '.$daysLeftUntilChangeReq.'='. $result.'<br>';
		if  (!($app == 'preferences' && $class == 'preferences_password' && $method == 'change') &&
			 (
			  ($GLOBALS['egw_info']['server']['change_pwd_every_x_days'] &&
			   ($GLOBALS['egw_info']['user']['apps']['preferences'] || $GLOBALS['egw_info']['user']['apps']['password']) &&
			   (
				($passwordAgeBorder > $alpwchange_val) ||
				(
				 $GLOBALS['egw_info']['server']['warn_about_upcoming_pwd_change'] &&
				 $GLOBALS['egw_info']['server']['warn_about_upcoming_pwd_change'] > $daysLeftUntilChangeReq
				)
			   )
			  ) || $alpwchange_val==0
			 )
			)
		{
			if ($GLOBALS['egw']->acl->check('nopasswordchange', 1, 'preferences')) return true; // user has no rights to change password
			if ($UserKnowsAboutPwdChange === true && !($passwordAgeBorder > $alpwchange_val || $alpwchange_val==0)) return true; // user has already been informed about the upcomming password expiration
			if (!is_null($alpwchange_val))
			{
				if ($alpwchange_val == 0)
				{
					$message = lang('an admin required that you must change your password upon login.');
				}
				elseif (($passwordAgeBorder < $alpwchange_val) ||
						(
						 $GLOBALS['egw_info']['server']['warn_about_upcoming_pwd_change'] &&
						 $GLOBALS['egw_info']['server']['warn_about_upcoming_pwd_change'] > $daysLeftUntilChangeReq &&
						 $daysLeftUntilChangeReq > 0
						)
					   )
				{
					$UserKnowsAboutPwdChange = true;
					$message = lang('your password is about to expire in %1 days, you may change your password now',round($daysLeftUntilChangeReq));
				}
				elseif ($passwordAgeBorder > $alpwchange_val && $alpwchange_val > 0)
				{
					error_log(__METHOD__.' Password of '.$GLOBALS['egw_info']['user']['account_lid'].' ('.$GLOBALS['egw_info']['user']['account_fullname'].') is of old age.'.array2string(array(
						'ts'=> $alpwchange_val,
						'date'=>egw_time::to($alpwchange_val))));
					$message = lang('it has been more then %1 days since you changed your password',$GLOBALS['egw_info']['server']['change_pwd_every_x_days']);
				}
				egw::redirect_link('/index.php',array(
					'menuaction' => 'preferences.preferences_password.change',
					'message'    => $message,
					'nopopup'    => true,
				));
			}
		}
		return true;
	}

	/**
	 * fetch the last pwd change for the user
	 *
	 * @param string $username username of account to authenticate
	 * @return mixed false or shadowlastchange*24*3600
	 */
	function getLastPwdChange($username)
	{
		if (method_exists($this->backend,'getLastPwdChange')) return $this->backend->getLastPwdChange($username);
		return false;
	}

	/**
	 * changes account_lastpwd_change in ldap datababse
	 *
	 * @param int $account_id account id of user whose passwd should be changed
	 * @param string $passwd must be cleartext, usually not used, but may be used to authenticate as user to do the change -> ldap
	 * @param int $lastpwdchange must be a unixtimestamp
	 * @return boolean true if account_lastpwd_change successful changed, false otherwise
	 */
	function setLastPwdChange($account_id=0, $passwd=NULL, $lastpwdchange=NULL)
	{
		if (method_exists($this->backend,'setLastPwdChange')) return $this->backend->setLastPwdChange($account_id, $passwd, $lastpwdchange);
		return false;
	}

	/**
	 * password authentication against password stored in sql datababse
	 *
	 * @param string $username username of account to authenticate
	 * @param string $passwd corresponding password
	 * @param string $passwd_type ='text' 'text' for cleartext passwords (default)
	 * @return boolean true if successful authenticated, false otherwise
	 */
	function authenticate($username, $passwd, $passwd_type='text')
	{
		return $this->backend->authenticate($username, $passwd, $passwd_type);
	}

	/**
	 * Calls crackcheck to enforce password strength (if configured) and changes password
	 *
	 * @param string $old_passwd must be cleartext
	 * @param string $new_passwd must be cleartext
	 * @param int $account_id account id of user whose passwd should be changed
	 * @throws egw_exception_wrong_userinput if configured password strength is not meat
	 * @throws Exception from backends having extra requirements
	 * @return boolean true if password successful changed, false otherwise
	 */
	function change_password($old_passwd, $new_passwd, $account_id=0)
	{
		if (($err = self::crackcheck($new_passwd,null,null,null,$account_id)))
		{
			throw new egw_exception_wrong_userinput($err);
		}
		if (($ret = $this->backend->change_password($old_passwd, $new_passwd, $account_id)))
		{
			if ($account_id == $GLOBALS['egw_info']['user']['account_id'])
			{
				// need to change current users password in session
				egw_cache::setSession('phpgwapi', 'password', base64_encode($new_passwd));
				$GLOBALS['egw_info']['user']['passwd'] = $new_passwd;
				$GLOBALS['egw_info']['user']['account_lastpwd_change'] = egw_time::to('now','ts');
				// invalidate EGroupware session, as password is stored in egw_info in session
				egw::invalidate_session_cache();
			}
			accounts::cache_invalidate($account_id);
			// run changepwasswd hook
			$GLOBALS['hook_values'] = array(
				'account_id'  => $account_id,
				'account_lid' => accounts::id2name($account_id),
				'old_passwd'  => $old_passwd,
				'new_passwd'  => $new_passwd,
			);
			$GLOBALS['egw']->hooks->process($GLOBALS['hook_values']+array(
				'location' => 'changepassword'
			),False,True);	// called for every app now, not only enabled ones)
		}
		return $ret;
	}

	/**
	 * return a random string of letters [0-9a-zA-Z] of size $size
	 *
	 * @param $size int-size of random string to return
	 */
	static function randomstring($size)
	{
		static $random_char = array(
			'0','1','2','3','4','5','6','7','8','9','a','b','c','d','e','f',
			'g','h','i','j','k','l','m','n','o','p','q','r','s','t','u','v',
			'w','x','y','z','A','B','C','D','E','F','G','H','I','J','K','L',
			'M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z'
		);

		$s = '';
		for ($i=0; $i<$size; $i++)
		{
			$s .= $random_char[mt_rand(1,61)];
		}
		return $s;
	}

	/**
	 * encrypt password
	 *
	 * uses the encryption type set in setup and calls the appropriate encryption functions
	 *
	 * @param $password password to encrypt
	 */
	static function encrypt_password($password,$sql=False)
	{
		if($sql)
		{
			return self::encrypt_sql($password);
		}
		return self::encrypt_ldap($password);
	}

	/**
	 * compares an encrypted password
	 *
	 * encryption type set in setup and calls the appropriate encryption functions
	 *
	 * @param string $cleartext cleartext password
	 * @param string $encrypted encrypted password, can have a {hash} prefix, which overrides $type
	 * @param string $type_in type of encryption
	 * @param string $username used as optional key of encryption for md5_hmac
	 * @param string &$type =null on return detected type of hash
	 * @return boolean
	 */
	static function compare_password($cleartext, $encrypted, $type_in, $username='', &$type=null)
	{
		// allow to specify the hash type to prefix the hash, to easy migrate passwords from ldap
		$type = $type_in;
		$saved_enc = $encrypted;
		$matches = null;
		if (preg_match('/^\\{([a-z_5]+)\\}(.+)$/i',$encrypted,$matches))
		{
			$type = strtolower($matches[1]);
			$encrypted = $matches[2];

			switch($type)	// some hashs are specially "packed" in ldap
			{
				case 'md5':
					$encrypted = implode('',unpack('H*',base64_decode($encrypted)));
					break;
				case 'plain':
				case 'crypt':
					// nothing to do
					break;
				default:
					$encrypted = $saved_enc;
					break;
			}
		}
		elseif($encrypted[0] == '$')
		{
			$type = 'crypt';
		}

		switch($type)
		{
			case 'plain':
				$ret = $cleartext === $encrypted;
				break;
			case 'smd5':
				$ret = self::smd5_compare($cleartext,$encrypted);
				break;
			case 'sha':
				$ret = self::sha_compare($cleartext,$encrypted);
				break;
			case 'ssha':
				$ret = self::ssha_compare($cleartext,$encrypted);
				break;
			case 'crypt':
			case 'des':
			case 'md5_crypt':
			case 'blowish_crypt':	// was for some time a typo in setup
			case 'blowfish_crypt':
			case 'ext_crypt':
			case 'sha256_crypt':
			case 'sha512_crypt':
				$ret = self::crypt_compare($cleartext, $encrypted, $type);
				break;
			case 'md5_hmac':
				$ret = self::md5_hmac_compare($cleartext,$encrypted,$username);
				break;
			default:
				$type = 'md5';
				// fall through
			case 'md5':
				$ret = md5($cleartext) === $encrypted;
				break;
		}
		//error_log(__METHOD__."('$cleartext', '$encrypted', '$type_in', '$username') type='$type' returning ".array2string($ret));
		return $ret;
	}

	/**
	 * Parameters used for crypt: const name, salt prefix, len of random salt, postfix
	 *
	 * @var array
	 */
	static $crypt_params = array(	//
		'crypt' => array('CRYPT_STD_DES', '', 2, ''),
		'ext_crypt' => array('CRYPT_EXT_DES', '_J9..', 4, ''),
		'md5_crypt' => array('CRYPT_MD5', '$1$', 8, '$'),
		//'old_blowfish_crypt' => array('CRYPT_BLOWFISH', '$2$', 13, ''),	// old blowfish hash not in line with php.net docu, but could be in use
		'blowfish_crypt' => array('CRYPT_BLOWFISH', '$2a$12$', 22, ''),	// $2a$12$ = 2^12 = 4096 rounds
		'sha256_crypt' => array('CRYPT_SHA256', '$5$', 16, '$'),	// no "round=N$" --> default of 5000 rounds
		'sha512_crypt' => array('CRYPT_SHA512', '$6$', 16, '$'),	// no "round=N$" --> default of 5000 rounds
	);

	/**
	 * compare crypted passwords for authentication whether des,ext_des,md5, or blowfish crypt
	 *
	 * @param string $form_val user input value for comparison
	 * @param string $db_val   stored value / hash (from database)
	 * @param string &$type    detected crypt type on return
	 * @return boolean	 True on successful comparison
	*/
	static function crypt_compare($form_val, $db_val, &$type)
	{
		// detect type of hash by salt part of $db_val
		list($first, $dollar, $salt, $salt2) = explode('$', $db_val);
		foreach(self::$crypt_params as $type => $params)
		{
			list(,$prefix, $random, $postfix) = $params;
			list(,$d) = explode('$', $prefix);
			if ($dollar === $d || !$dollar && ($first[0] === $prefix[0] || $first[0] !== '_' && !$prefix))
			{
				$len = !$postfix ? strlen($prefix)+$random : strlen($prefix.$salt.$postfix);
				// sha(256|512) might contain options, explicit $rounds=N$ prefix in salt
				if (($type == 'sha256_crypt' || $type == 'sha512_crypt') && substr($salt, 0, 7) === 'rounds=')
				{
					$len += strlen($salt2)+1;
				}
				break;
			}
		}

		$full_salt = substr($db_val, 0, $len);
		$new_hash = crypt($form_val, $full_salt);
		//error_log(__METHOD__."('$form_val', '$db_val') type=$type --> len=$len --> salt='$full_salt' --> new_hash='$new_hash' returning ".array2string($db_val === $new_hash));

		return $db_val === $new_hash;
	}

	/**
	 * encrypt password for ldap
	 *
	 * uses the encryption type set in setup and calls the appropriate encryption functions
	 *
	 * @param string $password password to encrypt
	 * @param string $type =null default to $GLOBALS['egw_info']['server']['ldap_encryption_type']
	 * @return string
	 */
	static function encrypt_ldap($password, $type=null)
	{
		if (is_null($type)) $type = $GLOBALS['egw_info']['server']['ldap_encryption_type'];

		$salt = '';
		switch(strtolower($type))
		{
			default:	// eg. setup >> config never saved
			case 'des':
			case 'blowish_crypt':	// was for some time a typo in setup
				$type = $type == 'blowish_crypt' ? 'blowfish_crypt' : 'crypt';
				// fall through
			case 'crypt':
			case 'sha256_crypt':
			case 'sha512_crypt':
			case 'blowfish_crypt':
			case 'md5_crypt':
			case 'ext_crypt':
				list($const, $prefix, $len, $postfix) = self::$crypt_params[$type];
				if(defined($const) && constant($const) == 1)
				{
					$salt = $prefix.self::randomstring($len).$postfix;
					$e_password = '{crypt}'.crypt($password, $salt);
					break;
				}
				self::$error = 'no '.str_replace('_', ' ', $type);
				$e_password = false;
				break;
			case 'md5':
				/* New method taken from the openldap-software list as recommended by
				 * Kervin L. Pierre" <kervin@blueprint-tech.com>
				 */
				$e_password = '{md5}' . base64_encode(pack("H*",md5($password)));
				break;
			case 'smd5':
				$salt = self::randomstring(16);
				$hash = md5($password . $salt, true);
				$e_password = '{SMD5}' . base64_encode($hash . $salt);
				break;
			case 'sha':
				$e_password = '{SHA}' . base64_encode(sha1($password,true));
				break;
			case 'ssha':
				$salt = self::randomstring(16);
				$hash = sha1($password . $salt, true);
				$e_password = '{SSHA}' . base64_encode($hash . $salt);
				break;
			case 'plain':
				// if plain no type is prepended
				$e_password = $password;
				break;
		}
		//error_log(__METHOD__."('$password', ".array2string($type).") returning ".array2string($e_password).(self::$error ? ' error='.self::$error : ''));
		return $e_password;
	}

	/**
	 * Create a password for storage in the accounts table
	 *
	 * @param string $password
	 * @param string $type =null default $GLOBALS['egw_info']['server']['sql_encryption_type']
	 * @return string hash
	 */
	static function encrypt_sql($password, $type=null)
	{
		/* Grab configured type, or default to md5() (old method) */
		if (is_null($type))
		{
			$type = @$GLOBALS['egw_info']['server']['sql_encryption_type'] ?
				strtolower($GLOBALS['egw_info']['server']['sql_encryption_type']) : 'md5';
		}
		switch($type)
		{
			case 'plain':
				// since md5 is the default, type plain must be prepended, for eGroupware to understand
				$e_password = '{PLAIN}'.$password;
				break;

			case 'md5':
				/* This is the old standard for password storage in SQL */
				$e_password = md5($password);
				break;

			// all other types are identical to ldap, so no need to doublicate the code here
			case 'des':
			case 'blowish_crypt':	// was for some time a typo in setup
			case 'crypt':
			case 'sha256_crypt':
			case 'sha512_crypt':
			case 'blowfish_crypt':
			case 'md5_crypt':
			case 'ext_crypt':
			case 'smd5':
			case 'sha':
			case 'ssha':
				$e_password = self::encrypt_ldap($password, $type);
				break;

			default:
				self::$error = 'no valid encryption available';
				$e_password = false;
				break;
		}
		//error_log(__METHOD__."('$password') using '$type' returning ".array2string($e_password).(self::$error ? ' error='.self::$error : ''));
		return $e_password;
	}

	/**
	 * Get available password hashes sorted by securest first
	 *
	 * @param string &$securest =null on return securest available hash
	 * @return array hash => label
	 */
	public static function passwdhashes(&$securest=null)
	{
		$hashes = array();

		/* Check for available crypt methods based on what is defined by php */
		if(defined('CRYPT_BLOWFISH') && CRYPT_BLOWFISH == 1)
		{
			$hashes['blowfish_crypt'] = 'blowfish_crypt';
		}
		if(defined('CRYPT_SHA512') && CRYPT_SHA512 == 1)
		{
			$hashes['sha512_crypt'] = 'sha512_crypt';
		}
		if(defined('CRYPT_SHA256') && CRYPT_SHA256 == 1)
		{
			$hashes['sha256_crypt'] = 'sha256_crypt';
		}
		if(defined('CRYPT_MD5') && CRYPT_MD5 == 1)
		{
			$hashes['md5_crypt'] = 'md5_crypt';
		}
		if(defined('CRYPT_EXT_DES') && CRYPT_EXT_DES == 1)
		{
			$hashes['ext_crypt'] = 'ext_crypt';
		}
		$hashes += array(
			'ssha' => 'ssha',
			'smd5' => 'smd5',
			'sha'  => 'sha',
		);
		if(@defined('CRYPT_STD_DES') && CRYPT_STD_DES == 1)
		{
			$hashes['crypt'] = 'crypt';
		}

		$hashes += array(
			'md5' => 'md5',
			'plain' => 'plain',
		);

		// mark the securest algorithm for the user
		list($securest) = each($hashes); reset($hashes);
		$hashes[$securest] .= ' ('.lang('securest').')';

		return $hashes;
	}

	/**
	 * Checks if a given password is "safe"
	 *
	 * @link http://technet.microsoft.com/en-us/library/cc786468(v=ws.10).aspx
	 * In contrary to whats documented in above link, windows seems to treet numbers as delimiters too.
	 *
	 * Windows compatible check is $reqstrength=3, $minlength=7, $forbid_name=true
	 *
	 * @param string $passwd
	 * @param int $reqstrength =null defaults to whatever set in config for "force_pwd_strength"
	 * @param int $minlength =null defaults to whatever set in config for "check_save_passwd"
	 * @param string $forbid_name =null if "yes" username or full-name split by delimiters AND longer then 3 chars are
	 *  forbidden to be included in password, default to whatever set in config for "passwd_forbid_name"
	 * @param array|int $account =null array with account_lid and account_fullname or account_id for $forbid_name check
	 * @return mixed false if password is considered "safe" (or no requirements) or a string $message if "unsafe"
	 */
	static function crackcheck($passwd, $reqstrength=null, $minlength=null, $forbid_name=null, $account=null)
	{
		if (!isset($reqstrength)) $reqstrength = $GLOBALS['egw_info']['server']['force_pwd_strength'];
		if (!isset($minlength)) $minlength = $GLOBALS['egw_info']['server']['force_pwd_length'];
		if (!isset($forbid_name)) $forbid_name = $GLOBALS['egw_info']['server']['passwd_forbid_name'];

		// load preferences translations, as changepassword get's called from admin too
		translation::add_app('preferences');

		// check for and if necessary convert old values True and 5 to new separate values for length and char-classes
		if ($GLOBALS['egw_info']['server']['check_save_passwd'] || $reqstrength == 5)
		{
			if (!isset($reqstrength) || $reqstrength == 5)
			{
				config::save_value('force_pwd_strength', $reqstrength=4, 'phpgwapi');
			}
			if (!isset($minlength))
			{
				config::save_value('force_pwd_length', $minlength=7, 'phpgwapi');
			}
			config::save_value('check_save_passwd', null, 'phpgwapi');
		}

		$errors = array();

		if ($minlength && strlen($passwd) < $minlength)
		{
			$errors[] = lang('password must have at least %1 characters', $minlength);
		}

		if ($forbid_name === 'yes')
		{
			if (!$account || !is_array($account) && !($account = $GLOBALS['egw']->accounts->read($account)))
			{
				throw new egw_exception_wrong_parameter('crackcheck(..., forbid_name=true, account) requires account-data!');
			}
			$parts = preg_split("/[,._ \t0-9-]+/", $account['account_fullname'].','.$account['account_lid']);
			foreach($parts as $part)
			{
				if (strlen($part) > 2 && stripos($passwd, $part) !== false)
				{
					$errors[] = lang('password contains with "%1" a parts of your user- or full-name (3 or more characters long)', $part);
					break;
				}
			}
		}

		if ($reqstrength)
		{
			$missing = array();
			if (!preg_match('/(.*\d.*){'. ($non=1). ',}/',$passwd))
			{
				$missing[] = lang('numbers');
			}
			if (!preg_match('/(.*[[:upper:]].*){'. ($nou=1). ',}/',$passwd))
			{
				$missing[] = lang('uppercase letters');
			}
			if (!preg_match('/(.*[[:lower:]].*){'. ($nol=1). ',}/',$passwd))
			{
				$missing[] = lang('lowercase letters');
			}
			if (!preg_match('/['.preg_quote('~!@#$%^&*_-+=`|\(){}[]:;"\'<>,.?/', '/').']/', $passwd))
			{
				$missing[] = lang('special characters');
			}
			if (4 - count($missing) < $reqstrength)
			{
				$errors[] = lang('password contains only %1 of required %2 character classes: no %3',
					4-count($missing), $reqstrength, implode(', ', $missing));
			}
		}
		if ($errors)
		{
			return lang('Your password does not have required strength:').
				"<br/>\n- ".implode("<br/>\n- ", $errors);
		}
		return false;
	}

	/**
	 * compare SMD5-encrypted passwords for authentication
	 *
	 * @param string $form_val user input value for comparison
	 * @param string $db_val stored value (from database)
	 * @return boolean True on successful comparison
	*/
	static function smd5_compare($form_val,$db_val)
	{
		/* Start with the first char after {SMD5} */
		$hash = base64_decode(substr($db_val,6));

		/* SMD5 hashes are 16 bytes long */
		$orig_hash = cut_bytes($hash, 0, 16);	// binary string need to use cut_bytes, not mb_substr(,,'utf-8')!
		$salt = cut_bytes($hash, 16);

		$new_hash = md5($form_val . $salt,true);
		//echo '<br>  DB: ' . base64_encode($orig_hash) . '<br>FORM: ' . base64_encode($new_hash);

		return strcmp($orig_hash,$new_hash) == 0;
	}

	/**
	 * compare SHA-encrypted passwords for authentication
	 *
	 * @param string $form_val user input value for comparison
	 * @param string $db_val   stored value (from database)
	 * @return boolean True on successful comparison
	*/
	static function sha_compare($form_val,$db_val)
	{
		/* Start with the first char after {SHA} */
		$hash = base64_decode(substr($db_val,5));
		$new_hash = sha1($form_val,true);
		//echo '<br>  DB: ' . base64_encode($orig_hash) . '<br>FORM: ' . base64_encode($new_hash);

		return strcmp($hash,$new_hash) == 0;
	}

	/**
	 * compare SSHA-encrypted passwords for authentication
	 *
	 * @param string $form_val user input value for comparison
	 * @param string $db_val   stored value (from database)
	 * @return boolean	 True on successful comparison
	*/
	static function ssha_compare($form_val,$db_val)
	{
		/* Start with the first char after {SSHA} */
		$hash = base64_decode(substr($db_val, 6));

		// SHA-1 hashes are 160 bits long
		$orig_hash = cut_bytes($hash, 0, 20);	// binary string need to use cut_bytes, not mb_substr(,,'utf-8')!
		$salt = cut_bytes($hash, 20);
		$new_hash = sha1($form_val . $salt,true);

		//error_log(__METHOD__."('$form_val', '$db_val') hash='$hash', orig_hash='$orig_hash', salt='$salt', new_hash='$new_hash' returning ".array2string(strcmp($orig_hash,$new_hash) == 0));
		return strcmp($orig_hash,$new_hash) == 0;
	}

	/**
	 * compare md5_hmac-encrypted passwords for authentication (see RFC2104)
	 *
	 * @param string $form_val user input value for comparison
	 * @param string $db_val   stored value (from database)
	 * @param string $_key       key for md5_hmac-encryption (username for imported smf users)
	 * @return boolean	 True on successful comparison
	 */
	static function md5_hmac_compare($form_val,$db_val,$_key)
	{
		$key = str_pad(strlen($_key) <= 64 ? $_key : pack('H*', md5($_key)), 64, chr(0x00));
		$md5_hmac = md5(($key ^ str_repeat(chr(0x5c), 64)) . pack('H*', md5(($key ^ str_repeat(chr(0x36), 64)). $form_val)));

		return strcmp($md5_hmac,$db_val) == 0;
	}
}

/**
 * Interface for authentication backend
 */
interface auth_backend
{
	/**
	 * password authentication against password stored in sql datababse
	 *
	 * @param string $username username of account to authenticate
	 * @param string $passwd corresponding password
	 * @param string $passwd_type ='text' 'text' for cleartext passwords (default)
	 * @return boolean true if successful authenticated, false otherwise
	 */
	function authenticate($username, $passwd, $passwd_type='text');

	/**
	 * changes password in sql datababse
	 *
	 * @param string $old_passwd must be cleartext
	 * @param string $new_passwd must be cleartext
	 * @param int $account_id account id of user whose passwd should be changed
	 * @throws Exception to give a verbose error, why changing password failed
	 * @return boolean true if password successful changed, false otherwise
	 */
	function change_password($old_passwd, $new_passwd, $account_id=0);
}
