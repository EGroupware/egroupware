<?php
/**
 * EGroupware Api: Mail accounts
 *
 * @link http://www.stylite.de
 * @package api
 * @subpackage mail
 * @author Ralf Becker <rb@stylite.de>
 * @author Stylite AG <info@stylite.de>
 * @copyright (c) 2013-16 by Ralf Becker <rb@stylite.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

namespace EGroupware\Api\Mail;

use EGroupware\Api;

use Horde_Imap_Client_Exception;
use Horde_Mail_Transport_Smtphorde;

/**
 * Mail accounts supports 3 types of accounts:
 *
 * a) personal mail accounts either created by admin or user themselves
 * b) accounts for multiple users or groups created by admin
 * c) configuration to administrate a mail-server
 *
 * To store the accounts 4 tables are used
 * - egw_ea_accounts all data except credentials and identities (incl. signature)
 * - egw_ea_valid for which users an account is valid 1:N relation to accounts table
 * - egw_ea_credentials username/password for various accounts and types (imap, smtp, admin)
 * - egw_ea_identities identities of given account and user incl. standard identity of account
 * - egw_ea_notifications folders a user wants to be notified about new mails
 *
 * Most methods return iterators: use iterator_to_array() to cast them to an array eg. for eTemplate use.
 *
 * @property-read int $acc_id id
 * @property-read string $acc_name description / display name
 * @property-read string $acc_imap_host imap hostname
 * @property-read int $acc_imap_ssl 0=none, 1=starttls, 2=tls, 3=ssl, &8=validate certificate
 * @property-read int $acc_imap_port imap port, default 143 or for ssl 993
 * @property-read string $acc_imap_username
 * @property-read string $acc_imap_password
 * @property-read string $acc_imap_pw_enc Credentials::(CLEARTEXT|USER|SYSTEM)
 * @property-read boolean $acc_sieve_enabled sieve enabled
 * @property-read string $acc_sieve_host sieve host, default imap_host
 * @property-read int $acc_sieve_ssl 0=none, 1=starttls, 2=tls, 3=ssl, &8=validate certificate
 * @property-read int $acc_sieve_port sieve port, default 4190, old non-ssl port 2000 or ssl 5190
 * @property-read string $acc_folder_sent sent folder
 * @property-read string $acc_folder_trash trash folder
 * @property-read string $acc_folder_draft draft folder
 * @property-read string $acc_folder_template template folder
 * @property-read string $acc_folder_junk junk/spam folder
 * @property-read string $acc_smtp_host smtp hostname
 * @property-read int $acc_smtp_ssl 0=none, 1=starttls, 2=tls, 3=ssl, &8=validate certificate
 * @property-read int $acc_smtp_port smtp port
 * @property-read string $acc_smtp_username if smtp auth required
 * @property-read string $acc_smtp_password
 * @property-read string $acc_smtp_pw_enc Credentials::(CLEARTEXT|USER|SYSTEM)
 * @property-read string $acc_smtp_type smtp class to use, default Smtp
 * @property-read string $acc_imap_type imap class to use, default Imap
 * @property-read string $acc_imap_logintype how to construct login-name standard, vmailmgr, admin, uidNumber
 * @property-read string $acc_domain domain name
 * @property-read boolean $acc_imap_administration enable administration
 * @property-read string $acc_imap_admin_username
 * @property-read string $acc_imap_admin_password
 * @property-read boolean $acc_further_identities are non-admin users allowed to create further identities
 * @property-read boolean $acc_user_editable are non-admin users allowed to edit this account, if it is for them
 * @property-read boolean $acc_user_forward are non-admin users allowed change forwards
 * @property-read int $acc_modified timestamp of last modification
 * @property-read int $acc_modifier account_id of last modifier
 * @property-read int $ident_id standard identity
 * @property-read string $ident_name name of identity
 * @property-read string $ident_realname real name
 * @property-read string $ident_email email address
 * @property-read string $ident_org organisation
 * @property-read string $ident_signature signature text (html)
 * @property-read array $params parameters passed to constructor (all above as array)
 * @property-read array $account_id account-ids this mail account is valid for, 0=everyone
 * @property-read int $user account-id class is instanciated for
 * @property-read string $mailLocalAddress mail email address
 * @property-read array $mailAlternateAddress further email addresses
 * @property-read array $mailForwardingAddress address(es) to forward to
 * @property-read string $accountStatus "active", if account is enabled to receive mail
 * @property-read string $deliveryMode "forwardOnly", if account only forwards (no imap account!)
 * @property-read int $quotaLimit quota limit in MB
 * @property-read int $quotaUsed quota usage in MB
 * @property-read int $acc_imap_default_quota quota in MB, if no user specific one set
 * @property-read int $acc_imap_timeout timeout for imap connection, default 20s
 * @property-read array $notif_folders folders user wants to be notified about new mails
 * @property-read bool $acc_admin_use_without_pw use admin credentials for users personal mail account, if user password is not in session eg. SSO
 *
 * You can overwrite values in all mail accounts by creating a file /var/www/mail-overwrites.inc.php, see method getParamOverwrites.
 */
class Account implements \ArrayAccess
{
	/**
	 * App tables belong to
	 */
	const APP = 'api';
	/**
	 * Table with mail-accounts
	 */
	const TABLE = 'egw_ea_accounts';
	/**
	 * Table holding 1:N relation for which EGroupware accounts a mail-account is valid
	 */
	const VALID_TABLE = 'egw_ea_valid';
	/**
	 * Join with egw_ea_valid
	 */
	const VALID_JOIN = 'JOIN egw_ea_valid ON egw_ea_valid.acc_id=egw_ea_accounts.acc_id ';
	/**
	 * Join with egw_ea_valid
	 */
	const ALL_VALID_JOIN = 'LEFT JOIN egw_ea_valid all_valid ON all_valid.acc_id=egw_ea_accounts.acc_id ';
	/**
	 * Table with identities and signatures
	 */
	const IDENTITIES_TABLE = 'egw_ea_identities';
	/**
	 * Join with standard identity of main-account
	 */
	const IDENTITY_JOIN = 'JOIN egw_ea_identities ON egw_ea_identities.ident_id=egw_ea_accounts.ident_id';
	/**
	 * Order for search: first group-profiles, then general profiles, then personal profiles
	 */
	const DEFAULT_ORDER = 'egw_ea_valid.account_id ASC,ident_org ASC,ident_realname ASC,acc_name ASC';

	/**
	 * JOIN to join in admin user, eg. to check if we have admin credentials
	 */
	const ADMIN_JOIN = 'LEFT JOIN egw_ea_credentials ON egw_ea_credentials.acc_id=egw_ea_accounts.acc_id AND cred_type=8';
	const ADMIN_COL = 'cred_username AS acc_imap_admin_username';

	/**
	 * No SSL
	 */
	const SSL_NONE = 0;
	/**
	 * STARTTLS on regular tcp connection/port
	 */
	const SSL_STARTTLS = 1;
	/**
	 * SSL (inferior to TLS!)
	 */
	const SSL_SSL = 3;
	/**
	 * require TLS version 1+, no SSL version 2 or 3
	 */
	const SSL_TLS = 2;
	/**
	 * if set, verify certifcate (currently not implemented in Horde_Imap_Client!)
	 */
	const SSL_VERIFY = 8;

	/**
	 * Default timeout, if no account specific one is set
	 */
	const DEFAULT_TIMEOUT = 20;

	/**
	 * Reference to global db object
	 *
	 * @var Api\Db
	 */
	static protected $db;

	/**
	 * Parameters passed to contructor
	 *
	 * @var array
	 */
	protected $params = array();

	/**
	 * Instance of imap server
	 *
	 * @var Imap
	 */
	protected $imapServer;

	/**
	 * Instance of smtp server
	 *
	 * @var Smtp
	 */
	protected $smtpServer;

	/**
	 * Instance of Horde mail transport
	 *
	 * @var Horde_Mail_Transport_Smtphorde
	 */
	protected $smtpTransport;

	/**
	 * Path to log smtp comunication to or null to not log
	 */
	const SMTP_DEBUG_LOG = null;//'/tmp/smtp.log';

	/**
	 * Instanciated account object by acc_id, read acts as singelton
	 *
	 * @var array
	 */
	protected static $instances = array();

	/**
	 * Cache for Account::read() to minimize database access
	 *
	 * @var array
	 */
	protected static $cache = array();

	/**
	 * Cache for Account::search() to minimize database access
	 */
	protected static $search_cache = array();

	/**
	 * account_id class was instanciated for ($called_for parameter of constructor or current user)
	 *
	 * @var int
	 */
	protected $user;

	/**
	 * Name of certain user-data fields which need to get queried by imap or smtp backends
	 *
	 * @var array
	 */
	static public $user_data = array(
		'mailLocalAddress', 'mailAlternateAddress', 'mailForwardingAddress',
		'accountStatus', 'deliveryMode', 'quotaLimit', 'quotaUsed',
	);

	/**
	 * Callable to run on successful login to eg. run Credentials::migrate
	 *
	 * @var array with callable and further arguments
	 */
	protected $on_login;

	/**
	 * Constructor
	 *
	 * @param array $params
	 * @param int $called_for=null if set access to given user (without smtp credentials!),
	 *	default current user AND read username/password from current users session
	 */
	public function __construct(array $params, $called_for=null)
	{
		// tracker_mailhandling instantiates class without our database (acc_id==="tracker*")
		if ((int)$params['acc_id'] > 0)
		{
			// read credentials from database
			$params += Credentials::read($params['acc_id'], null, $called_for ? array(0, $called_for) : $called_for,
				$this->on_login, $params['acc_imap_host']);

			if (isset($params['acc_imap_admin_username']) && $params['acc_imap_admin_username'][0] === '*')
			{
				$params['acc_admin_use_without_pw'] = true;
				$params['acc_imap_admin_username'] = substr($params['acc_imap_admin_username'], 1);
			}

			if (!isset($params['notify_folders']))
			{
				$params += Notifications::read($params['acc_id'], $called_for ? array(0, $called_for) : $called_for);
			}
			if (!empty($params['acc_imap_logintype']) && empty($params['acc_imap_username']) &&
				$GLOBALS['egw_info']['user']['account_id'] &&
				(!isset($called_for) || $called_for == $GLOBALS['egw_info']['user']['account_id']))
			{
				// get usename/password from current user, let it overwrite credentials for all/no session
				$params = Credentials::from_session(
						(!isset($called_for) ? array() : array('acc_smtp_auth_session' => false)) + $params, !isset($called_for)
					) + $params;

				// check if we should use admin-credentials, if no session password exists, eg. SSO without password
				if (!empty($params['acc_admin_use_without_pw']) && empty($params['acc_imap_password']))
				{
					$params['acc_imap_username'] .= '*'.$params['acc_imap_admin_username'];
					$params['acc_imap_password'] = $params['acc_imap_admin_password'];
				}
			}
		}
		$this->params = $params;
		unset($this->imapServer);
		unset($this->smtpServer);
		unset($this->smtpTransport);

		$this->user = $called_for ? $called_for : $GLOBALS['egw_info']['user']['account_id'];
	}

	public static function ssl2secure($ssl)
	{
		$secure = false;
		switch((int)$ssl & ~self::SSL_VERIFY)
		{
			case self::SSL_STARTTLS:
				$secure = 'tls';	// Horde uses 'tls' for STARTTLS, not ssl connection with tls version >= 1 and no sslv2/3
				break;
			case self::SSL_SSL:
				$secure = 'ssl';
				break;
			case self::SSL_TLS:
				$secure = 'tlsv1';	// since Horde_Imap_Client-1.16.0 requiring Horde_Socket_Client-1.1.0
				break;
		}
		return $secure;
	}

	/**
	 * Query quota, aliases, forwards, ... from imap and smtp backends and sets them as parameters on current object
	 *
	 * @param boolean $need_quota =true false: qutoa not needed, do NOT query IMAP server
	 * @return array with values for keys in self::$user_data
	 */
	public function getUserData($need_quota=true)
	{
		if ($this->acc_smtp_type != __NAMESPACE__.'\\Smtp' && $this->smtpServer() &&
			($smtp_data = $this->smtpServer->getUserData($this->user)))
		{
			$this->params += $smtp_data;
		}
		// if we manage the mail-account, include that data too (imap has higher precedence)
		try {
			if ($this->acc_imap_type != __NAMESPACE__.'\\Imap' &&
				// do NOT query IMAP server, if we are in forward-only delivery-mode, imap will NOT answer, as switched off for that account!
				($this->params['deliveryMode'] ?? null) != Smtp::FORWARD_ONLY && $need_quota &&
				$this->imapServer($this->user) && is_a($this->imapServer, __NAMESPACE__.'\\Imap') &&
				($data = $this->imapServer->getUserData($GLOBALS['egw']->accounts->id2name($this->user))))
			{
				// give quota-limit from SMTP/SQL precedence over (cached) quota from Dovecot
				if (isset($this->params['quotaLimit']) && is_a($this->imapServer, __NAMESPACE__.'\\Imap\\Dovecot'))
				{
					unset($data['quotaLimit']);
				}
				$this->params = array_merge($this->params, $data);
			}
		}
		catch(Horde_Imap_Client_Exception $e) {
			unset($e);
			// ignore eg. connection errors
		}
		catch(\InvalidArgumentException $e) {
			unset($e);
			// ignore eg. missing admin user
		}
		$this->params += array_fill_keys(self::$user_data, null);	// make sure all keys exist now

		return ($data ?? []) + ($smtp_data ?? []);
	}

	/**
	 * Query quota, aliases, forwards, ... from imap and smtp backends and sets them as parameters on current object
	 *
	 * @param array with values for keys in self::$user_data
	 */
	public function saveUserData($user, array $data)
	{
		$data += $this->params;	// in case only user-data has been specified

		// store account-information of managed mail server
		if ($user > 0 && $data['acc_smtp_type'] && $data['acc_smtp_type'] != __NAMESPACE__.'\\Smtp')
		{
			$smtp = $this->smtpServer($data);
			$smtp->setUserData($user, (array)$data['mailAlternateAddress'], (array)$data['mailForwardingAddress'],
				$data['deliveryMode'], $data['accountStatus'], $data['mailLocalAddress'], $data['quotaLimit']);
		}
		if ($user > 0 && $data['acc_imap_type'] && $data['acc_imap_type'] != __NAMESPACE__.'\\Imap')
		{
			$class = $data['acc_imap_type'];
			$imap = new $class($data, true);
			$imap->setUserData($GLOBALS['egw']->accounts->id2name($user), $data['quotaLimit']);
		}
	}

	/**
	 * Get params incl. overwrites from /var/www/mail-overwrites.inc.php:
	 *
	 * $overwrites = [
	 *  'mail.mycompany.com' => [   // overwrites all mail-accounts with acc_imap_host='mail.mycompany.com'
	 *      'acc_imap_host' => 'other host or IP',
	 *      // further imap, smtp or mail settings to use, instead what's in the DB
	 *  ],
	 *  // other imap-server to overwrite ...
	 * ];
	 *
	 * // or you can provide a function, which gets passed all acc_* parameters and can modify them:
	 * function _mail_overwrites(array $params)
	 * {
	 *      switch($params['acc_imap_host'])
	 *      {
	 *          case 'mail':
	 *              $params['acc_imap_host'] = 'other host or IP';
	 *              break;
	 *      }
	 *      return $params;
	 * }
	 *
	 * @return array
	 */
	function getParamOverwrites()
	{
		static $overwrites = null;
		if (!isset($overwrites))
		{
			if (file_exists($f='/var/www/mail-overwrites.inc.php'))
			{
				include($f);
			}
			if (!isset($overwrites))
			{
				$overwrites = [];
			}
		}
		if (isset($overwrites[$this->acc_imap_host]))
		{
			return array_merge($this->params, $overwrites[$this->acc_imap_host]);
		}
		elseif (function_exists('_mail_overwrites'))
		{
			return _mail_overwrites($this->params);
		}
		return $this->params;
	}

	/**
	 * Get new Horde_Imap_Client imap server object
	 *
	 * @param bool|int|string $_adminConnection create admin connection if true or account_id or imap username
	 * @param int $_timeout =null timeout in secs, if none given fmail pref or default of 20 is used
	 * @return Imap
	 */
	public function imapServer($_adminConnection=false, $_timeout=null)
	{
		if (!isset($this->imapServer) || $this->imapServer->isAdminConnection !== $_adminConnection)
		{
			// make sure mbstring.func_overload=0
			static $func_overload = null;
			if (is_null($func_overload)) $func_overload = extension_loaded('mbstring') ? ini_get('mbstring.func_overload') : 0;
			if ($func_overload) throw new Api\Exception\AssertionFailed('Fatal Error: EGroupware requires mbstring.func_overload=0 set in your php.ini!');

			$params = $this->getParamOverwrites();
			$class = $params['acc_imap_type'];
			$this->imapServer = new $class($params, $_adminConnection, $_timeout);

			// if Credentials class told us to run something on successful login, tell it to Imap class
			if ($this->on_login)
			{
				$func = array_shift($this->on_login);
				$this->imapServer->runOnLogin($func, $this->on_login);
				unset($this->on_login);
			}
		}
		return $this->imapServer;
	}

	/**
	 * Check if account is an imap account
	 *
	 * Checks if an imap host, username and for managaged mail-servers accountStatus="active" and NOT deliveryMode="forwardOnly" is set
	 *
	 * @param boolean $try_connect =true true: try connecting for validation, false: read user-data to determine it's imap
	 *	(matters only for imap servers managed by EGroupware!)
	 * @return boolean
	 */
	public function is_imap($try_connect=true)
	{
		if (empty($this->acc_imap_host) ||
			empty($this->acc_imap_username) && empty($this->acc_imap_password) &&
				!($oauth = Api\Auth\OpenIDConnectClient::providerByDomain($this->acc_imap_username ?: $this->ident_email, $this->acc_imap_host)))
		{
			return false;	// no imap host or credentials
		}
		if (isset($oauth))
		{
			$this->params['acc_imap_username'] = $this->acc_imap_username ?: $this->ident_email;
			$this->params['acc_imap_password'] = '**oauth**';
		}
		// if we are not managing the mail-server, we do NOT need to check deliveryMode and accountStatus
		if ($this->acc_smtp_type == __NAMESPACE__.'\\Smtp')
		{
			return true;
		}
		// it is quicker to try connection, assuming we want to do that anyway, instead of reading user-data
		if ($try_connect)
		{
			// as querying user-data is a lot slower then just trying to connect, and we need probably need to connect anyway, we try that
			$imap = $this->imapserver();
			try {
				$imap->login();
				return true;
			}
			catch (\Exception $ex) {
				unset($ex);
			}
		}
		return $this->deliveryMode != Smtp::FORWARD_ONLY && $this->accountStatus == Smtp::MAIL_ENABLED;
	}

	/**
	 * Factory method to instantiate smtp server object
	 *
	 * @return Smtp
	 */
	public function smtpServer()
	{
		if (!isset($this->smtpServer))
		{
			$params = $this->getParamOverwrites();
			$class = $params['acc_smtp_type'];
			$this->smtpServer = new $class($params);
			$this->smtpServer->editForwardingAddress = false;
			$this->smtpServer->host = $params['acc_smtp_host'];
			$this->smtpServer->port = $params['acc_smtp_port'];
			switch($params['acc_smtp_ssl'])
			{
				case self::SSL_TLS:
					$this->smtpServer->host = 'tlsv1://'.$this->smtpServer->host;
					break;
				case self::SSL_SSL:
					$this->smtpServer->host = 'ssl://'.$this->smtpServer->host;
					break;
				case self::SSL_STARTTLS:
					$this->smtpServer->host = 'tls://'.$this->smtpServer->host;
			}
			$this->smtpServer->smtpAuth = !empty($params['acc_smtp_username']);
			$this->smtpServer->username = $params['acc_smtp_username'] ?? null;
			$this->smtpServer->password = $params['acc_smtp_password'] ?? null;
			$this->smtpServer->defaultDomain = $params['acc_domain'];
			$this->smtpServer->loginType = $params['acc_imap_login_type'] ?? null;
		}
		return $this->smtpServer;
	}

	/**
	 * Get Horde mail transport object
	 *
	 * @return Horde_Mail_Transport_Smtphorde
	 */
	public function smtpTransport()
	{
		if (!isset($this->smtpTransport))
		{
			$params = $this->getParamOverwrites();
			$secure = false;
			switch($params['acc_smtp_ssl'] & ~self::SSL_VERIFY)
			{
				case self::SSL_STARTTLS:
					$secure = 'tls';	// Horde uses 'tls' for STARTTLS, not ssl connection with tls version >= 1 and no sslv2/3
					break;
				case self::SSL_SSL:
					$secure = 'ssl';
					break;
				case self::SSL_TLS:
					$secure = 'tlsv1';	// since Horde_Smtp-1.3.0 requiring Horde_Socket_Client-1.1.0
					break;
			}
			// Horde use locale for translation of error messages
			Api\Preferences::setlocale(LC_MESSAGES);

			$config = [
				'username' => $params['acc_smtp_username'] ?? null,
				'password' => $params['acc_smtp_password'] ?? null,
				'host' => $params['acc_smtp_host'],
				'port' => $params['acc_smtp_port'],
				'secure' => $secure,
				'debug' => self::SMTP_DEBUG_LOG,
				//'timeout' => self::TIMEOUT,
			];
			// if we have an OAuth access-token for the user, pass it on
			if (!empty($params['acc_oauth_access_token']) && $config['username'] === $params['acc_oauth_username'])
			{
				$config['xoauth2_token'] = new \Horde_Smtp_Password_Xoauth2($params['acc_oauth_username'], $params['acc_oauth_access_token']);
			}
			$this->smtpTransport = new Horde_Mail_Transport_Smtphorde($config);
		}
		return $this->smtpTransport;
	}

	/**
	 * Get identities of given or current account (for current user!)
	 *
	 * Standard identity is always first (as it has account_id=0 and we order account_id ASC).
	 *
	 * @param int|array|Account $account default this account, empty array() to get all identities of current user
	 * @param boolean $replace_placeholders =false should placeholders like {{n_fn}} be replaced
	 * @param string $field ='name' what to return as value: "ident_(realname|org|email|signature)" or default "name"=result from identity_name
	 * @param int $user =null account_id to use if not current user
	 * @return \Iterator ident_id => identity_name of identity
	 */
	public static function identities($account, $replace_placeholders=true, $field='name', $user=null)
	{
		if (!isset($user)) $user = $GLOBALS['egw_info']['user']['account_id'];
		$acc_id = is_scalar($account) ? $account : $account['acc_id'];

		$cols = array('ident_id', 'ident_name', 'ident_realname', 'ident_org', 'ident_email', 'ident_signature', 'acc_id', 'acc_imap_username', 'acc_imap_logintype', 'acc_domain');
		if (!in_array($field, array_merge($cols, array('name', 'params'))))
		{
			$cols[] = $field;
		}
		$cols[array_search('ident_id', $cols)] = self::IDENTITIES_TABLE.'.ident_id AS ident_id';
		$cols[array_search('acc_id', $cols)] = self::IDENTITIES_TABLE.'.acc_id AS acc_id';
		$cols[array_search('acc_imap_username', $cols)] = Credentials::TABLE.'.cred_username AS acc_imap_username';

		$where[] = self::$db->expression(self::IDENTITIES_TABLE, self::IDENTITIES_TABLE.'.', array('account_id' => self::memberships($user)));
		if ($acc_id)
		{
			$where[] = self::$db->expression(self::IDENTITIES_TABLE, self::IDENTITIES_TABLE.'.', array('acc_id' => $acc_id));
		}
		$rs = self::$db->select(self::IDENTITIES_TABLE, $cols, $where, __LINE__, __FILE__, false,
			'ORDER BY '.self::IDENTITIES_TABLE.'.account_id,ident_realname,ident_org,ident_email', self::APP, null,
			' JOIN '.self::TABLE.' ON '.self::TABLE.'.acc_id='.self::IDENTITIES_TABLE.'.acc_id'.
			' LEFT JOIN '.Credentials::TABLE.' ON '.self::TABLE.'.acc_id='.Credentials::TABLE.'.acc_id AND '.
				Credentials::TABLE.'.account_id='.(int)$user.' AND '.
				'(cred_type&'.Credentials::IMAP.') > 0');
		//error_log(__METHOD__."(acc_id=$acc_id, replace_placeholders=$replace_placeholders, field='$field') sql=".$rs->sql);

		return new Api\Db\CallbackIterator($rs,
			// process each row
			function($row) use ($replace_placeholders, $field, $user)
			{
				// set email from imap-username (evtl. set from session, if acc_imap_logintype specified)
				if (in_array($field, array('name', 'ident_email', 'params')) &&
					empty($row['ident_email']) && empty($row['acc_imap_username']) && $row['acc_imap_logintype'])
				{
					$row = array_merge($row, Credentials::from_session($row));
				}
				// fill an empty ident_realname or ident_email of current user with data from user account
				if ($replace_placeholders && (!isset($user) || $user == $GLOBALS['egw_info']['user']['account_id']))
				{
					if (empty($row['ident_realname'])) $row['ident_realname'] = $GLOBALS['egw_info']['user']['account_fullname'];
					if (empty($row['ident_email'])) $row['ident_email'] = $GLOBALS['egw_info']['user']['account_email'];
				}
				if ($field != 'name')
				{
					$data = $replace_placeholders ? array_merge($row, self::replace_placeholders($row)) : $row;
					return $field == 'params' ? $data : $data[$field];
				}
				return self::identity_name($row, $replace_placeholders);
			}, array(),
			function($row) { return $row['ident_id'];});
	}

	/**
	 * Get rfc822 email address from given identity or account
	 *
	 * @param array|Account $identity
	 * @return string rfc822 email address from given identity or account
	 */
	public static function rfc822($identity)
	{
		$address = $identity['ident_realname'];
		if ($identity['ident_org'])
		{
			$address .= ($address && $identity['ident_org'] ? ' ' : '').$identity['ident_org'];
		}
		if (strpos($address, ',') !== false)	// need to quote comma
		{
			$address = '"'.str_replace('"', '\\"', $address).'"';
		}
		if (!strpos($identity['ident_email'], '@'))
		{
			$address = null;
		}
		elseif ($address)
		{
			$address = $address.' <'.$identity['ident_email'].'>';
		}
		else
		{
			$address = $identity['ident_email'];
		}
		//error_log(__METHOD__."(acc_id=$identity[acc_id], ident_id=$identity[ident_id], realname=$identity[ident_realname], org=$identity[ident_org], email=$identity[ident_email]) returning ".array2string($address));
		return $address;
	}

	/**
	 * Get list of rfc822 addresses for current user eg. to use as from address selection when sending mail
	 *
	 * @param callback $formatter =null function to format identity as rfc822 address, default self::rfc822(),
	 * @return array acc_id:ident_id:email => rfc822 address pairs, eg. '1:1:rb@stylite.de' => 'Ralf Becker Stylite AG <rb@stylite.de>'
	 * @todo add aliases for manged mail servers
	 */
	public static function rfc822_addresses($formatter=null)
	{
		if (!$formatter || !is_callable($formatter))
		{
			$formatter = 'self::rfc822';
		}
		$addresses = array();
		foreach(self::search(true, false) as $acc_id => $account)
		{
			$added = false;	// make sure each account get's at least added once, even if it uses an identical email address
			foreach(self::identities($account, true, 'params') as $identity)
			{
				if (($address = call_user_func($formatter, $identity)) && (!$added || !in_array($address, $addresses)))
				{
					$addresses[$acc_id.':'.$identity['ident_id'].':'.$identity['ident_email']] = $address;
					$added = true;
				}
			}
		}
		// sort caseinsensitiv alphabetical
		uasort($addresses, 'strcasecmp');
		//error_log(__METHOD__."() returning ".array2string($addresses));
		return $addresses;
	}

	/**
	 * Return list of identities/signatures for given account ordered by give email on top and then by identity name
	 *
	 * @param int|array|Account $account default this account, empty array() to get all identities of current user
	 * @param string $order_email_top email address to order top
	 * @return array ident_id => ident_name pairs
	 */
	public static function identities_ordered($account, $order_email_top)
	{
		$identities = iterator_to_array(self::identities($account, true, 'params'));
		uasort($identities, function($a, $b) use ($order_email_top)
		{
			$cmp = !strcasecmp($order_email_top, $a['ident_email']) - !strcasecmp($order_email_top, $b['ident_email']);
			if (!$cmp)
			{
				$cmp = strcasecmp($a['ident_name'], $b['ident_name']);
			}
			return $cmp;
		});
		foreach($identities as &$identity)
		{
			$identity = self::identity_name($identity);
		}
		//error_log(__METHOD__."(".array2string($account).", '$order_email_top') returning ".array2string($identities));
		return $identities;
	}

	/**
	 * Replace placeholders like {{n_fn}} in an identity
	 *
	 * For full list of placeholders see Api\Contacts\Merge.
	 *
	 * @param array|Account $identity
	 * @param int $account_id =null account_id of user, or current user
	 * @return array with modified fields
	 */
	public static function replace_placeholders($identity, $account_id=null)
	{
		static $fields = array('ident_name','ident_realname','ident_org','ident_email','ident_signature');

		if (!is_array($identity) && !is_a($identity, 'Account'))
		{
			throw new Api\Exception\WrongParameter(__METHOD__."() requires an identity or account as first parameter!");
		}
		$to_replace = array();
		foreach($fields as $name)
		{
			if (!empty($identity[$name]) && (strpos($identity[$name], '{{') !== false || strpos($identity[$name], '$$') !== false))
			{
				$to_replace[$name] = $identity[$name];
			}
		}
		if ($to_replace)
		{
			static $merge=null;
			if (!isset($merge)) $merge = new Api\Contacts\Merge();
			if (!isset($account_id)) $account_id = $GLOBALS['egw_info']['user']['account_id'];
			foreach($to_replace as $name => &$value)
			{
				$err = null;
				$value = $merge->merge_string($value,
					(array)Api\Accounts::id2name($account_id, 'person_id'),
					$err, $name == 'ident_signature' ? 'text/html' : 'text/plain', null, 'utf-8');
			}
		}
		//error_log(__METHOD__."(".array2string($identity).") returning ".array2string($to_replace));
		return $to_replace;
	}

	/**
	 * Read an identity
	 *
	 * @param int $ident_id
	 * @param boolean $replace_placeholders =false should placeholders like {{n_fn}} be replaced
	 * @param int $user =null account_id to use, default current user
	 * @param array|Account $account =null account array or object, to not read it again from database
	 * @return array
	 * @throws Api\Exception\NotFound
	 */
	public static function read_identity($ident_id, $replace_placeholders=false, $user=null, $account=null)
	{
		if (($account && $account['ident_id'] == $ident_id))
		{
			$data = array_intersect_key(is_array($account) ? $account : $account->params,
				array_flip(array('indent_id', 'ident_name', 'ident_email', 'ident_realname', 'ident_org', 'ident_signature', 'acc_id')));
		}
		elseif (!($data = self::$db->select(self::IDENTITIES_TABLE, '*', array(
			'ident_id' => $ident_id,
			'account_id' => self::memberships($user),
		), __LINE__, __FILE__, false, '', self::APP)->fetch()))
		{
			throw new Api\Exception\NotFound();
		}
		if ($replace_placeholders)
		{
			// set empty email&realname from session / account
			if (empty($data['ident_email']) || empty($data['ident_realname']))
			{
				if (is_array($account) || ($account = self::read($data['acc_id'])))
				{
					$is_current_user = !isset($user) || $user == $GLOBALS['egw_info']['user']['account_id'];
					if (empty($data['ident_email']))
					{
						$data['ident_email'] = $account->ident_email || strpos($account->acc_imap_username, '@') === false ?
							$account->ident_email : $account->acc_imap_username;

						if (empty($data['ident_email']) && $is_current_user)
						{
							$data['ident_email'] = $GLOBALS['egw_info']['user']['account_email'] ?? null;
						}
					}
					if (empty($data['ident_realname']))
					{
						$data['ident_realname'] = $account->ident_realname || !$is_current_user ?
							$account->ident_realname : ($GLOBALS['egw_info']['user']['account_fullname'] ?? null);
					}
				}
			}
			// replace placeholders
			$data = array_merge($data, self::replace_placeholders($data));
		}
		return $data;
	}

	/**
	 * Store an identity in database
	 *
	 * Can be called static, if identity is given as parameter
	 *
	 * @param array|Account $identity default standard identity of current account
	 * @return int ident_id of new/updated identity
	 */
	public static function save_identity($identity)
	{
		if (!is_array($identity) && !is_a($identity, 'Account'))
		{
			throw new Api\Exception\WrongParameter(__METHOD__."() requires an identity or account as first parameter!");
		}
		if (!($identity['acc_id'] > 0))
		{
			throw new Api\Exception\WrongParameter(__METHOD__."() no account / acc_id specified in identity!");
		}
		$data = array(
			'acc_id' => $identity['acc_id'],
			'ident_name' => $identity['ident_name'] ? $identity['ident_name'] : null,
			'ident_realname' => $identity['ident_realname'],
			'ident_org' => $identity['ident_org'],
			'ident_email' => $identity['ident_email'],
			'ident_signature' => $identity['ident_signature'],
			'account_id' => self::is_multiple($identity) ? 0 :
				(is_array($identity['account_id']) ? $identity['account_id'][0] : $identity['account_id']),
		);
		if ($identity['ident_id'] !== 'new' && (int)$identity['ident_id'] > 0)
		{
			self::$db->update(self::IDENTITIES_TABLE, $data, array(
				'ident_id' => $identity['ident_id'],
			), __LINE__, __FILE__, self::APP);

			return $identity['ident_id'];
		}
		self::$db->insert(self::IDENTITIES_TABLE, $data, false, __LINE__, __FILE__, self::APP);

		return self::$db->get_last_insert_id(self::IDENTITIES_TABLE, 'ident_id');
	}

	/**
	 * Delete given identity
	 *
	 * @param int $ident_id
	 * @return int number off affected rows
	 * @throws Api\Exception\WrongParameter if identity is standard identity of existing account
	 */
	public static function delete_identity($ident_id)
	{
		if (($acc_id = self::$db->select(self::TABLE, 'acc_id', array('ident_id' => $ident_id),
			__LINE__, __FILE__, 0, '', self::APP, 1)->fetchColumn()))
		{
			throw new Api\Exception\WrongParameter("Can not delete identity #$ident_id used as standard identity in account #$acc_id!");
		}
		self::$db->delete(self::IDENTITIES_TABLE, array('ident_id' => $ident_id), __LINE__, __FILE__, self::APP);

		return self::$db->affected_rows();
	}

	/**
	 * Give read access to protected parameters in $this->params
	 *
	 * To get $this->params you need to call getUserData before! It is never automatically loaded.
	 *
	 * @param string $name
	 * @return mixed
	 */
	public function __get($name)
	{
		switch($name)
		{
			case 'acc_imap_administration':	// no longer stored in database
				return !empty($this->params['acc_imap_admin_username']);

			case 'params':	// does NOT return user-data, unless $this->getUserData was called before!
				return $this->params;
		}
		// if user-data is requested, check if it is already loaded and load it if not
		if (in_array($name, self::$user_data) && !array_key_exists($name, $this->params))
		{
			// let getUserData "know" if we are interested in quota (requiring IMAP login) or not
			$this->getUserData(substr($name, 0, 5) === 'quota');
		}
		return $this->params[$name] ?? null;
	}

	/**
	 * Give read access to protected parameters in $this->params
	 *
	 * @param string $name
	 * @return mixed
	 */
	public function __isset($name)
	{
		switch($name)
		{
			case 'acc_imap_administration':	// no longer stored in database
				return true;

			case 'params':
				return isset($this->params);
		}
		// if user-data is requested, check if it is already loaded and load it if not
		if (in_array($name, self::$user_data) && !array_key_exists($name, $this->params))
		{
			$this->getUserData();
		}
		return isset($this->params[$name]);
	}

	/**
	 * ArrayAccess to Account
	 *
	 * @param string $offset
	 * @return mixed
	 */
	public function offsetGet($offset): mixed
	{
		return $this->__get($offset);
	}

	/**
	 * ArrayAccess to Account
	 *
	 * @param string $offset
	 * @return boolean
	 */
	public function offsetExists($offset): bool
	{
		return $this->__isset($offset);
	}

	/**
	 * ArrayAccess requires it but we dont want to give public write access
	 *
	 * Protected access has to use protected attributes!
	 *
	 * @param string $offset
	 * @param mixed $value
	 * @throws Api\Exception\WrongParameter
	 */
	public function offsetSet($offset, $value): void
	{
		throw new Api\Exception\WrongParameter(__METHOD__."($offset, $value) No write access through ArrayAccess interface of Account!");
	}

	/**
	 * ArrayAccess requires it but we dont want to give public write access
	 *
	 * Protected access has to use protected attributes!
	 *
	 * @param string $offset
	 * @throws Api\Exception\WrongParameter
	 */
	public function offsetUnset($offset): void
	{
		throw new Api\Exception\WrongParameter(__METHOD__."($offset) No write access through ArrayAccess interface of Account!");
	}

	/**
	 * Check which rights current user has on mail-account
	 *
	 * @param int $rights Api\Acl::(READ|EDIT|DELETE)
	 * @param array|Account $account account array or object
	 * @return boolean
	 */
	public static function check_access($rights, $account)
	{
		if (!is_array($account) && !($account instanceof Account))
		{
			throw new Api\Exception\WrongParameter('$account must be either an array or an Account object!');
		}

		$access = false;
		// Admin app has all rights
		if (isset($GLOBALS['egw_info']['user']['apps']['admin']))
		{
			$access = true;
			$reason = 'user is EMailAdmin';
		}
		else
		{
			// check if account is for current user, if not deny access
			$memberships = self::memberships();
			$memberships[] = '';	// edit uses '' for everyone

			if (array_intersect((array)$account['account_id'], $memberships))
			{
				switch($rights)
				{
					case Api\Acl::READ:
						$access = true;
						break;

					case Api\Acl::EDIT:
					case Api\Acl::DELETE:
						// users have only edit/delete rights on accounts marked as user-editable AND belonging to them personally
						if (!$account['acc_user_editable'])
						{
							$access = false;
							$reason = 'account not user editable';
						}
						elseif (!in_array($GLOBALS['egw_info']['user']['account_id'], (array)$account['account_id']))
						{
							$access = false;
							$reason = 'no edit/delete for public (not personal) account';
						}
						else
						{
							$access = true;
							$reason = 'user editable personal account';
						}
						break;
				}
			}
			else
			{
				$reason = 'account not valid for current user'.array2string($account['account_id']);
			}
		}
		//error_log(__METHOD__."($rights, $account[acc_id]: $account[acc_name]) returning ".array2string($access).' '.$reason);
		return $access;
	}

	/**
	 * Read/return account object for given $acc_id
	 *
	 * @param int $acc_id
	 * @param int $called_for =null if set admin access to given user, default current user
	 *	AND read username/password from current users session, 0: find accounts from all users
	 * @return self
	 * @throws Api\Exception\NotFound if account was not found (or not valid for current user)
	 */
	public static function read($acc_id, $called_for=null)
	{
		//error_log(__METHOD__."($acc_id, ".array2string($called_for).")");
		// some caching, but only for regular usage/users
		if (!isset($called_for))
		{
			// act as singleton: if we already have an instance, return it
			if (isset(self::$instances[$acc_id]))
			{
				//error_log(__METHOD__."($acc_id) returned existing instance");
				return self::$instances[$acc_id];
			}
			// not yet an instance, create one
			if (isset(self::$cache[$acc_id]) && is_array(self::$cache[$acc_id]))
			{
				//error_log(__METHOD__."($acc_id) created instance from cached data");
				return self::$instances[$acc_id] = new Account(self::$cache[$acc_id]);
			}
			$data =& self::$cache[$acc_id];
		}
		$where = array(self::TABLE.'.acc_id='.(int)$acc_id);
		if (!isset($called_for) || $called_for !== '0')
		{
			$where[] = self::$db->expression(self::VALID_TABLE, self::VALID_TABLE.'.', array('account_id' => self::memberships($called_for)));
		}
		$cols = array(self::TABLE.'.*', self::IDENTITIES_TABLE.'.*');
		$group_by = 'GROUP BY '.self::TABLE.'.acc_id,'.self::IDENTITIES_TABLE.'.ident_id';
		$join = self::IDENTITY_JOIN.' '.self::VALID_JOIN;
		if (($valid_account_id_sql = self::$db->group_concat('all_valid.account_id')))
		{
			$cols[] = $valid_account_id_sql.' AS account_id';
			$join .= ' '.self::ALL_VALID_JOIN;
		}
		if (!($data = self::$db->select(self::TABLE, $cols, $where, __LINE__, __FILE__,
			false, Api\Storage::fix_group_by_columns($group_by, $cols, self::TABLE, 'acc_id'),
			self::APP, 0, $join)->fetch()))
		{
			throw new Api\Exception\NotFound(lang('Account not found!').' (acc_id='.array2string($acc_id).')');
		}
		if (!$valid_account_id_sql)
		{
			$data['account_id'] = array();
			foreach(self::$db->select(self::VALID_TABLE, 'account_id', array('acc_id' => $acc_id),
				__LINE__, __FILE__, false, '', self::APP) as $row)
			{
				$data['account_id'][] = $row['account_id'];
			}
		}
		$data = self::db2data($data);
		//error_log(__METHOD__."($acc_id, $only_current_user) returning ".array2string($data));

		if (!isset($called_for))
		{
			//error_log(__METHOD__."($acc_id) creating instance and caching data read from db");
			$ret =& self::$instances[$acc_id];
		}
		return $ret = new Account($data, $called_for);
	}

	/**
	 * Transform data returned from database (currently only fixing bool values)
	 *
	 * @param array $data
	 * @return array
	 */
	protected static function db2data(array $data)
	{
		foreach(array('acc_sieve_enabled','acc_user_editable','acc_smtp_auth_session','acc_user_forward') as $name)
		{
			if (isset($data[$name]))
			{
				$data[$name] = Api\Db::from_bool($data[$name]);
			}
		}
		if (isset($data['account_id']) && !is_array($data['account_id']))
		{
			$data['account_id'] = explode(',', $data['account_id']);
		}
		// convert old plugin names and readd namespace
		if ($data['acc_imap_type'])
		{
			if (substr($data['acc_imap_type'], 0, 4) == 'Imap')
			{
				$data['acc_imap_type'] = __NAMESPACE__.'\\'.$data['acc_imap_type'];
			}
			else
			{
				$data['acc_imap_type'] = self::getIcClass($data['acc_imap_type']);
			}
		}
		if ($data['acc_smtp_type'])
		{
			if (substr($data['acc_smtp_type'], 0, 4) == 'Smtp')
			{
				$data['acc_smtp_type'] = __NAMESPACE__.'\\'.$data['acc_smtp_type'];
			}
			else
			{
				/* static requires PHP5.6+ */ $old2new_smtp = array(
					'defaultsmtp'                => __NAMESPACE__.'\\Smtp',
					'emailadmin_smtp'            => __NAMESPACE__.'\\Smtp',
					'emailadmin_smtp_sql'        => __NAMESPACE__.'\\Smtp\\Sql',
					'emailadmin_smtp_ldap'       => __NAMESPACE__.'\\Smtp\\Ldap',
					'postfixinetorgperson'       => __NAMESPACE__.'\\Smtp\\Ldap',
					'emailadmin_smtp_ads'        => __NAMESPACE__.'\\Smtp\\Ads',
					'emailadmin_smtp_mandriva'   => __NAMESPACE__.'\\Smtp\\Mandriva',
					'emailadmin_smtp_qmail'      => __NAMESPACE__.'\\Smtp\\Qmail',
					'postfixldap'                => __NAMESPACE__.'\\Smtp\\Oldqmailuser',
					'emailadmin_smtp_suse'       => __NAMESPACE__.'\\Smtp\\Suse',
					'emailadmin_smtp_univention' => __NAMESPACE__.'\\Smtp\\Univention',
					'postfixdbmailuser'          => __NAMESPACE__.'\\Smtp\\Dbmailuser',
				);

				// convert smtp-class to new name
				if (isset($old2new_smtp[$data['acc_smtp_type']]))
				{
					$data['acc_smtp_type'] = $old2new_smtp[$data['acc_smtp_type']];
				}

				// fetch the IMAP / incomming server data
				if (!class_exists($data['acc_smtp_type'])) $data['acc_smtp_type'] = __NAMESPACE__.'\\Smtp';
			}
		}
		return $data;
	}

	/**
	 * Get name and evtl. autoload incomming server class
	 *
	 * @param string $imap_type
	 * @return string
	 */
	public static function getIcClass($imap_type)
	{
		/* static requires PHP5.6+ */ $old2new_imap = array(
			'defaultimap'             => __NAMESPACE__.'\\Imap',
			'emailadmin_imap'         => __NAMESPACE__.'\\Imap',
			'cyrusimap'               => __NAMESPACE__.'\\Imap\\Cyrus',
			'emailadmin_imap_cyrus'   => __NAMESPACE__.'\\Imap\\Cyrus',
			'emailadmin_dovecot'      => __NAMESPACE__.'\\Imap\\Dovecot',
			'emailadmin_imap_dovecot' => __NAMESPACE__.'\\Imap\\Dovecot',
			'dbmaildbmailuser'        => __NAMESPACE__.'\\Imap\\Dbmailuser',
			'dbmailqmailuser'         => __NAMESPACE__.'\\Imap\\Dbmailqmailuser',
		);

		// convert icClass to new name
		if (isset($old2new_imap[$imap_type]))
		{
			$imap_type = $old2new_imap[$imap_type];
		}

		// fetch the IMAP / incomming server data
		if (!class_exists($imap_type)) $imap_type = __NAMESPACE__.'\\Imap';

		return $imap_type;
	}

	/**
	 * Save account data to db
	 *
	 * @param array $data
	 * @param int $user =null account-id to store account-infos of managed mail-server
	 * @return array $data plus added values for keys acc_id, ident_id from insert
	 * @throws Api\Exception\WrongParameter if called static without data-array
	 * @throws Api\Db\Exception
	 */
	public static function write(array $data, $user=null)
	{
		//error_log(__METHOD__."(".array2string($data).")");
		$data['acc_modifier'] = $GLOBALS['egw_info']['user']['account_id'];
		$data['acc_modified'] = time();

		// remove redundant namespace to fit into column
		$ns_len = strlen(__NAMESPACE__)+1;
		$backup = array();
		foreach(array('acc_smtp_type', 'acc_imap_type') as $attr)
		{
			if (substr($data[$attr] ?? '', 0, $ns_len) == __NAMESPACE__.'\\')
			{
				$backup[$attr] = $data[$attr];
				$data[$attr] = substr($data[$attr], $ns_len);
			}
		}

		// store account data
		if (!(int)$data['acc_id'])
		{
			// set not set values which, are NOT NULL and therefore would give an SQL error
			$td = self::$db->get_table_definitions('api', self::TABLE);
			foreach($td['fd'] as $col => $def)
			{
				if (!isset($data[$col]) && $def['nullable'] === false && !isset($def['default']))
				{
					$data[$col] = null;
				}
			}
			unset($data['acc_id']);
		}
		$where = $data['acc_id'] > 0 ? array('acc_id' => $data['acc_id']) : false;
		self::$db->insert(self::TABLE, $data, $where, __LINE__, __FILE__, self::APP);
		if (!($data['acc_id'] > 0))
		{
			$data['acc_id'] = self::$db->get_last_insert_id(self::TABLE, 'acc_id');
		}
		// restore namespace in class-names
		if ($backup) $data = array_merge($data, $backup);

		// store identity
		$new_ident_id = self::save_identity($data);
		if ($data['ident_id'] === 'new' || empty($data['ident_id']))
		{
			$data['ident_id'] = $new_ident_id;
			self::$db->update(self::TABLE, array(
				'ident_id' => $data['ident_id'],
			), array(
				'acc_id' => $data['acc_id'],
			), __LINE__, __FILE__, self::APP);
		}
		// make account valid for given owner
		if (!isset($data['account_id']))
		{
			$data['account_id'] = $GLOBALS['egw_info']['user']['account_id'];
		}
		$old_account_ids = array();
		if ($where)
		{
			foreach(self::$db->select(self::VALID_TABLE, 'account_id', $where,
				__LINE__, __FILE__, false, '', self::APP) as $row)
			{
				$old_account_ids[] = $row['account_id'];
			}
			if ($data['account_id'] && ($ids_to_remove = array_diff($old_account_ids, (array)$data['account_id'])))
			{
				self::$db->delete(self::VALID_TABLE, $where+array(
					'account_id' => $ids_to_remove,
				), __LINE__, __FILE__, self::APP);
			}
		}
		foreach((array)$data['account_id'] as $account_id)
		{
			if (!in_array($account_id, $old_account_ids))
			{
				self::$db->insert(self::VALID_TABLE, array(
					'acc_id' => $data['acc_id'],
					'account_id' => $account_id,
				), false, __LINE__, __FILE__, self::APP);
			}
		}
		// check for whom we have to store credentials
		$valid_for = self::credentials_valid_for($data, $user);
		// add oauth credentials
		if (!empty($data['acc_oauth_username'] ?? $data['acc_imap_username']) && !empty($data['acc_oauth_refresh_token']))
		{
			Credentials::write($data['acc_id'], $data['acc_oauth_username'] ?? $data['acc_imap_username'], $data['acc_oauth_refresh_token'],
				$cred_type=Credentials::OAUTH_REFRESH_TOKEN, $valid_for, $data['acc_oauth_cred_id']);
		}
		else
		{
			// add imap credentials
			$cred_type = $data['acc_imap_username'] == $data['acc_smtp_username'] &&
			$data['acc_imap_password'] == $data['acc_smtp_password'] ? 3 : 1;
			// if both passwords are unavailable, they seem identical, do NOT store them together, as they are not!
			if ($cred_type == 3 && $data['acc_imap_password'] == Credentials::UNAVAILABLE &&
				$data['acc_imap_password'] == Credentials::UNAVAILABLE &&
				$data['acc_imap_cred_id'] != $data['acc_smtp_cred_id'])
			{
				$cred_type = 1;
			}
			Credentials::write($data['acc_id'], $data['acc_imap_username'], $data['acc_imap_password'],
				$cred_type, $valid_for, $data['acc_imap_cred_id']);
			// add smtp credentials if necessary and different from imap
			if ($data['acc_smtp_username'] && $cred_type != 3)
			{
				Credentials::write($data['acc_id'], $data['acc_smtp_username'], $data['acc_smtp_password'],
					2, $valid_for, $data['acc_smtp_cred_id'] != $data['acc_imap_cred_id'] ?
						$data['acc_smtp_cred_id'] : null);
			}
			// delete evtl. existing SMTP credentials, after storing IMAP&SMTP together now
			elseif ($data['acc_smtp_cred_id'])
			{
				Credentials::delete($data['acc_id'], $valid_for, Credentials::SMTP, true);
			}
		}

		// store or delete admin credentials
		if ($data['acc_imap_admin_username'] && $data['acc_imap_admin_password'])
		{
			Credentials::write($data['acc_id'], (!empty($data['acc_admin_use_without_pw'])?'*':'').$data['acc_imap_admin_username'],
				$data['acc_imap_admin_password'], Credentials::ADMIN, 0,
				$data['acc_imap_admin_cred_id']);
		}
		else
		{
			Credentials::delete($data['acc_id'], 0, Credentials::ADMIN);
		}

		// store or delete SpamTitan credentials
		if ($data['acc_spam_api'] && $data['acc_spam_password'])
		{
			Credentials::write($data['acc_id'], $data['acc_spam_api'],
				$data['acc_spam_password'], Credentials::SPAMTITAN, 0,
				$data['acc_spam_cred_id']);
		}
		else
		{
			Credentials::delete($data['acc_id'], 0, Credentials::SPAMTITAN);
		}

		// store notification folders
		Notifications::write($data['acc_id'], $data['notify_save_default'] ? 0 :
			($data['called_for'] ?: $GLOBALS['egw_info']['user']['account_id']),
			(array)$data['notify_folders']);

		// store domain of an account for all user like before as "mail_suffix" config
		if ($data['acc_domain'] && (!$data['account_id'] || $data['account_id'] == array(0)))
		{
			Api\Config::save_value('mail_suffix', $data['acc_domain'], 'phpgwapi', true);
		}

		if ($user > 0)
		{
			$emailadmin = new Account($data, $user);
			$emailadmin->saveUserData($user, $data);
		}
		self::cache_invalidate($data['acc_id']);
		//error_log(__METHOD__."() returning ".array2string($data));
		return $data;
	}

	/**
	 * Check for whom given credentials are to be stored
	 *
	 * @param array|Account $account
	 * @param int $account_id =null
	 * @return int account_id for whom credentials are valid or 0 for all
	 */
	protected static function credentials_valid_for($account, $account_id=null)
	{
		if (!isset($account_id)) $account_id = $GLOBALS['egw_info']['user']['account_id'];

		// if account valid for multiple users
		if (self::is_multiple($account))
		{
			// if imap login-name get constructed --> store credentials only for current user
			if ($account['acc_imap_logintype'])
			{
				return $account_id;
			}
			// store credentials for all users
			return 0;
		}
		// account is valid for a single user
		return is_array($account['account_id']) ? $account['account_id'][0] : $account['account_id'];
	}

	/**
	 * Delete accounts or account related data belonging to given mail or user account
	 *
	 * @param int|array $acc_id mail account
	 * @param int $account_id =null user or group
	 * @return int number of deleted mail accounts or null if only user-data was deleted and no full mail accounts
	 */
	public static function delete($acc_id, $account_id=null)
	{
		if (is_array($acc_id) || $acc_id > 0)
		{
			self::$db->delete(self::VALID_TABLE, array('acc_id' => $acc_id), __LINE__, __FILE__, self::APP);
			self::$db->delete(self::IDENTITIES_TABLE, array('acc_id' => $acc_id), __LINE__, __FILE__, self::APP);
			Credentials::delete($acc_id);
			Notifications::delete($acc_id);
			self::$db->delete(self::TABLE, array('acc_id' => $acc_id), __LINE__, __FILE__, self::APP);

			// invalidate caches
			foreach((array)$acc_id as $id)
			{
				self::cache_invalidate($id);
			}
			return self::$db->affected_rows();
		}
		if (!$account_id)
		{
			throw new Api\Exception\WrongParameter(__METHOD__."() no acc_id AND no account_id parameter!");
		}
		// delete all credentials belonging to given account(s)
		Credentials::delete(0, $account_id);
		Notifications::delete(0, $account_id);
		// delete all pointers to mail accounts belonging to given user accounts
		self::$db->delete(self::VALID_TABLE, array('account_id' => $account_id), __LINE__, __FILE__, self::APP);
		// delete all identities belonging to given user accounts
		self::$db->delete(self::IDENTITIES_TABLE, array('account_id' => $account_id), __LINE__, __FILE__, self::APP);
		// find profiles not belonging to anyone else and delete them
		$acc_ids = array();
		foreach(self::$db->select(self::TABLE, self::TABLE.'.acc_id', 'account_id IS NULL', __LINE__, __FILE__,
			false, 'GROUP BY '.self::TABLE.'.acc_id', self::APP, 0, 'LEFT '.self::VALID_JOIN) as $row)
		{
			$acc_ids[] = $row['acc_id'];
		}
		if ($acc_ids)
		{
			return self::delete($acc_ids);
		}
		return null;
	}

	/**
	 * Return array with acc_id => acc_name or account-object pairs
	 *
	 * @param boolean|int $only_current_user =true return only accounts for current user or integer account_id of a user
	 * @param boolean|string $just_name =true true: return self::identity_name, false: return Account objects,
	 *	string with attribute-name: return that attribute, eg. acc_imap_host or 'params' to return all attributes as array
	 * @param string $order_by ='acc_name ASC'
	 * @param int|boolean $offset =false offset or false to return all
	 * @param int $num_rows =0 number of rows to return, 0=default from prefs (if $offset !== false)
	 * @param boolean $replace_placeholders =true should placeholders like {{n_fn}} be replaced
	 * @return \Iterator with acc_id => acc_name or Account objects
	 */
	public static function search($only_current_user=true, $just_name=true, $order_by=null, $offset=false, $num_rows=0, $replace_placeholders=true)
	{
		//error_log(__METHOD__."($only_current_user, $just_name, '$order_by', $offset, $num_rows)");
		$where = array();
		if ($only_current_user)
		{
			$account_id = $only_current_user === true ? $GLOBALS['egw_info']['user']['account_id'] : $only_current_user;
			// no account_id happens eg. for notifications during login
			if ($account_id && !is_numeric($account_id))
			{
				throw new Api\Exception\WrongParameter(__METHOD__."(".array2string($only_current_user).") is NO valid account_id");
			}
			$where[] = self::$db->expression(self::VALID_TABLE, self::VALID_TABLE.'.', array(
				'account_id' => $account_id ? self::memberships($account_id) : 0
			));
		}
		if (empty($order_by) || !preg_match('/^[a-z_]+ (ASC|DESC)$/i', $order_by))
		{
			// for current user prefer account with ident_email matching user email or domain
			// (this also helps notifications to account allowing to send with from address of current user / account_email)
			if ($only_current_user && !empty($GLOBALS['egw_info']['user']['account_email']))
			{
				list(,$domain) = explode('@', $account_email = $GLOBALS['egw_info']['user']['account_email']);
				// empty ident_email will be replaced with account_email!
				$order_by = "(ident_email='' OR ident_email=".self::$db->quote($account_email).
					') DESC,ident_email LIKE '.self::$db->quote('%@'.$domain).' DESC,';
			}
			$order_by .= self::DEFAULT_ORDER;
		}
		$cache_key = json_encode($where).$order_by;

		if (!$only_current_user || !isset(self::$search_cache[$cache_key]))
		{
			$cols = array(self::TABLE.'.*', self::IDENTITIES_TABLE.'.*');
			$group_by = 'GROUP BY '.self::TABLE.'.acc_id,'.self::IDENTITIES_TABLE.'.ident_id,'.self::VALID_TABLE.'.account_id';
			$join = self::IDENTITY_JOIN.' '.self::VALID_JOIN;
			if (($valid_account_id_sql = self::$db->group_concat('all_valid.account_id')))
			{
				$cols[] = $valid_account_id_sql.' AS account_id';
				$join .= ' '.self::ALL_VALID_JOIN;
			}
			if ($just_name == 'params')	// join in acc_imap_admin_username
			{
				$cols[] = self::ADMIN_COL;
				$join .= ' '.self::ADMIN_JOIN;
			}
			$rs = self::$db->select(self::TABLE, $cols,	$where, __LINE__, __FILE__,
				$offset, Api\Storage::fix_group_by_columns($group_by, $cols, self::TABLE, 'acc_id').' ORDER BY '.$order_by,
				self::APP, $num_rows, $join);

			$ids = array();
			foreach($rs as $row)
			{
				$row = self::db2data($row);

				if ($only_current_user === true)
				{
					//error_log(__METHOD__."(TRUE, $just_name) caching data for acc_id=$row[acc_id]");
					self::$search_cache[$cache_key][$row['acc_id']] =& self::$cache[$row['acc_id']];
					self::$cache[$row['acc_id']] = $row;
				}
				else
				{
					self::$search_cache[$cache_key][$row['acc_id']] = $row;
				}
				$ids[] = $row['acc_id'];
			}
			// fetch valid_id, if not yet fetched
			if (!$valid_account_id_sql && $ids)
			{
				foreach(self::$db->select(self::VALID_TABLE, 'account_id', array('acc_id' => $ids),
					__LINE__, __FILE__, false, '', self::APP) as $row)
				{
					self::$cache[$row['acc_id']]['account_id'][] = $row['account_id'];
				}
			}
		}
		if (is_null(self::$search_cache[$cache_key])) self::$search_cache[$cache_key]=array();
		return new Api\Db\CallbackIterator(new \ArrayIterator(self::$search_cache[$cache_key]),
			// process each row
			function($row) use ($just_name, $replace_placeholders, $account_id)
			{
				if ($replace_placeholders)
				{
					$row = array_merge($row, self::replace_placeholders($row, $account_id));
				}
				if (is_string($just_name))
				{
					return $just_name == 'params' ? $row : $row[$just_name];
				}
				if ($just_name)
				{
					return self::identity_name($row, false, $account_id);
				}
				return new Account($row, $account_id);
			}, array(),
			// return acc_id as key
			function($row)
			{
				return $row['acc_id'];
			});
	}

	/**
	 * Get default mail account object either for IMAP or SMTP
	 *
	 * @param boolean $smtp =false false: usable for IMAP, true: usable for SMTP
	 * @param boolean $return_id =false true: return acc_id, false return account object
	 * @param boolean $log_no_default =true true: error_log if no default found, false be silent
	 * @return Account|int|null
	 */
	static function get_default($smtp=false, $return_id=false, $log_no_default=true)
	{
		try
		{
			foreach(self::search(true, 'params') as $acc_id => $params)
			{
				if ($smtp)
				{
					if (!$params['acc_smtp_host'] || !$params['acc_smtp_port']) continue;
					// check requirement of session, which is not available in async service!
					if (isset($GLOBALS['egw_info']['flags']['async-service']) ||
						empty($GLOBALS['egw_info']['user']['account_id']))	// happens during login when notifying about blocked accounts
					{
						if ($params['acc_smtp_auth_session']) continue;
						// may fail because of smtp only profile, or no session password, etc
						try
						{
							$account = new Account($params);
						}
						catch (\Exception $x)
						{
							unset($x);
							continue;
						}
						if (Credentials::isUser($account->acc_smtp_pw_enc)) continue;
					}
				}
				else
				{
					if (!$params['acc_imap_host'] || !$params['acc_imap_port']) continue;
					$account = new Account($params);
					// continue if we have either no imap username or password
					if (!$account->is_imap()) continue;
				}
				return $return_id ? $acc_id : (isset($account) && $account->acc_id == $acc_id ?
					$account : new Account($params));
			}
		}
		catch (\Exception $e)
		{
			if ($log_no_default) error_log(__METHOD__.__LINE__.' Error no Default available.'.$e->getMessage());
		}
		return null;
	}

	/**
	 * Get ID of default mail account for either IMAP or SMTP
	 *
	 * @param boolean $smtp =false false: usable for IMAP, true: usable for SMTP
	 * @return int
	 */
	static function get_default_acc_id($smtp=false)
	{
		return self::get_default($smtp, true);
	}

	/**
	 * build an identity name
	 *
	 * @param array|Account $account object or values for keys 'ident_(realname|org|email)', 'acc_(id|name|imap_username)'
	 * @param boolean $replace_placeholders =true should placeholders like {{n_fn}} be replaced
	 * @param int $account_id =null account_id of user we are called for
	 * @return string|array with htmlencoded angle brackets, returns account details as array if return_array is true
	 */
	public static function identity_name($account, $replace_placeholders=true, $account_id=null, $return_array=false)
	{
		if ($replace_placeholders)
		{
			$data = array(
				'ident_name' => $account['ident_name'],
				'ident_realname' => $account['ident_realname'],
				'ident_org' => $account['ident_org'],
				'ident_email' => $account['ident_email'],
				'acc_name' => $account['acc_name'] ?? null,
				'acc_imap_username' => $account['acc_imap_username'],
				'acc_imap_logintype' => $account['acc_imap_logintype'],
				'acc_domain' => $account['acc_domain'],
				'acc_id' => $account['acc_id'],
			);
			unset($account);
			//$start = microtime(true);
			$account = array_merge($data, self::replace_placeholders($data));
			//error_log(__METHOD__."() account=".array2string($account).' took '.number_format(microtime(true)-$start,3));
		}
		if (empty($account['ident_email']))
		{
			try {
				if (is_array($account) && empty($account['acc_imap_username']) && $account['acc_id'])
				{
					if (!isset($account['acc_imap_username']))
					{
						$account += Credentials::read($account['acc_id'], null, ($account_id?array($account_id, 0):null));
					}
					if (empty($account['acc_imap_username']) && $account['acc_imap_logintype'] &&
						(!isset($account_id) || $account_id == $GLOBALS['egw_info']['user']['account_id']))
					{
						$account = array_merge($account, Credentials::from_session($account));
					}
				}
				// fill an empty ident_realname or ident_email of current user with data from user account
				if ($replace_placeholders && (!isset($account_id) || $account_id == $GLOBALS['egw_info']['user']['account_id']))
				{
					if (empty($account['ident_realname'])) $account['ident_realname'] = $GLOBALS['egw_info']['user']['account_fullname'];
					if (empty($account['ident_email'])) $account['ident_email'] = $GLOBALS['egw_info']['user']['account_email'];
				}
				if (empty($account['ident_email']) && !empty($account['acc_imap_username']) && strpos($account['acc_imap_username'], '@') !== false)
				{
					$account['ident_email'] = $account['acc_imap_username'];
				}
			}
			catch(\Exception $e) {
				_egw_log_exception($e);
			}
		}
		if ($return_array)
		{
			return $account;
		}
		if (strlen(trim($account['ident_realname'].$account['ident_org'])))
		{
			$name = $account['ident_realname'].' '.$account['ident_org'];
		}
		else
		{
			$name = $account['acc_name'];
		}
		if (strpos($account['ident_email'], '@') !== false || trim($account['ident_email']) !='')
		{
			$name .= ' <'.$account['ident_email'].'>';
		}
		elseif(strpos($account['acc_imap_username'], '@') !== false || trim($account['acc_imap_username']) !='')
		{
			$name .= ' <'.$account['acc_imap_username'].'>';
		}
		// if user added a name of this identity, append it in brackets
		if (!empty($account['ident_name']))
		{
			$name .= ' ('.$account['ident_name'].')';
		}
		//error_log(__METHOD__."(".array2string($account).", $replace_placeholders) returning ".array2string($name));
		return $name;
	}

	/**
	 * Check if account is for multiple users
	 *
	 * account_id == 0 == everyone, is multiple too!
	 *
	 * @param array|Account|Imap $account value for key account_id (can be an array too!)
	 * @return boolean
	 */
	public static function is_multiple($account)
	{
		$is_multiple = !is_array($account['account_id']) ? $account['account_id'] <= 0 :
			(count($account['account_id']) > 1 || $account['account_id'][0] <= 0);
		//error_log(__METHOD__."(account_id=".array2string($account['account_id']).") returning ".array2string($is_multiple));
		return $is_multiple;
	}

	/**
	 * Magic method to convert account to a string: identity_name
	 *
	 * @return string
	 */
	public function __toString()
	{
		return self::identity_name($this);
	}

	/**
	 * Get memberships of current or given user incl. our 0=Everyone
	 *
	 * @param int $user=null
	 * @return array
	 */
	protected static function memberships($user=null)
	{
		if (!$user) $user = $GLOBALS['egw_info']['user']['account_id'];

		$memberships = $GLOBALS['egw']->accounts->memberships($user, true);
		$memberships[] = $user;
		$memberships[] = 0;	// marks account valid for everyone

		return $memberships;
	}

	/**
	 * Invalidate various caches
	 *
	 * @param int $acc_id
	 */
	protected static function cache_invalidate($acc_id)
	{
		//error_log(__METHOD__."($acc_id) invalidating cache");
		unset(self::$cache[$acc_id]);
		unset(self::$instances[$acc_id]);
		self::$search_cache = array();
	}

	/**
	 * Init our static properties
	 */
	static public function init_static()
	{
		self::$db = $GLOBALS['egw']->db;
	}
}

// some testcode, if this file is called via it's URL (you need to uncomment!)
/*if (isset($_SERVER['SCRIPT_FILENAME']) && $_SERVER['SCRIPT_FILENAME'] == __FILE__)
{
	$GLOBALS['egw_info'] = array(
		'flags' => array(
			'currentapp' => 'home',
			'nonavbar' => true,
		),
	);
	include_once '../../header.inc.php';

	Account::init_static();

	foreach(Account::search(true, false) as $acc_id => $account)
	{
		echo "<p>$acc_id: <a href='{$_SERVER['PHP_SELF']}?acc_id=$acc_id'>$account</a></p>\n";
	}
	if (isset($_GET['acc_id']) && (int)$_GET['acc_id'] > 0)
	{
		$account = Account::read((int)$_GET['acc_id']);
		_debug_array($account);
	}
}*/

// need to be after test-code!
Account::init_static();