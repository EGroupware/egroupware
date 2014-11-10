<?php
/**
 * EGroupware EMailAdmin: Mail accounts
 *
 * @link http://www.stylite.de
 * @package emailadmin
 * @author Ralf Becker <rb@stylite.de>
 * @author Stylite AG <info@stylite.de>
 * @copyright (c) 2013-14 by Ralf Becker <rb@stylite.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * Mail accounts supports 3 types of accounts:
 *
 * a) personal mail accounts either created by admin or user themselfs
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
 * @property-read string $acc_smtp_type smtp class to use, default emailadmin_smtp
 * @property-read string $acc_imap_type imap class to use, default emailadmin_imap
 * @property-read string $acc_imap_logintype how to construct login-name standard, vmailmgr, admin, uidNumber
 * @property-read string $acc_domain domain name
 * @property-read boolean $acc_imap_administration enable administration
 * @property-read string $acc_admin_username
 * @property-read string $acc_admin_password
 * @property-read boolean $acc_further_identities are non-admin users allowed to create further identities
 * @property-read boolean $acc_user_editable are non-admin users allowed to edit this account, if it is for them
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
 *
 * @todo remove comments from protected in __construct and db2data, once we require PHP 5.4 (keeping class contect in closures)
 */
class emailadmin_account implements ArrayAccess
{
	const APP = 'emailadmin';
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
	 * @var egw_db
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
	 * @var emailadmin_imap
	 */
	protected $imapServer;

	/**
	 * Instance of smtp server
	 *
	 * @var emailadmin_smtp
	 */
	protected $smtpServer;

	/**
	 * Instanciated account object by acc_id, read acts as singelton
	 *
	 * @var array
	 */
	protected static $instances = array();

	/**
	 * Cache for emailadmin_account::read() to minimize database access
	 *
	 * @var array
	 */
	protected static $cache = array();

	/**
	 * Cache for emailadmin_account::search() to minimize database access
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
	 * Constructor
	 *
	 * Should be protected, but php5.3 does NOT keep class context in closures.
	 * So 'til we require 5.4, it is public BUT SHOULD NOT BE USED!
	 *
	 * @param array $params
	 * @param int $called_for=null if set access to given user (without smtp credentials!),
	 *	default current user AND read username/password from current users session
	 */
	/*protected*/ function __construct(array $params, $called_for=null)
	{
		// read credentials from database
		$params += emailadmin_credentials::read($params['acc_id'], null, $called_for ? array(0, $called_for) : $called_for);

		if (!isset($params['notify_folders']))
		{
			$params += emailadmin_notifications::read($params['acc_id'], $called_for ? array(0, $called_for) : $called_for);
		}
		if (!empty($params['acc_imap_logintype']) && empty($params['acc_imap_username']) &&
			$GLOBALS['egw_info']['user']['account_id'] &&
			(!isset($called_for) || $called_for == $GLOBALS['egw_info']['user']['account_id']))
		{
			// get usename/password from current user, let it overwrite credentials for all/no session
			$params = emailadmin_credentials::from_session(
				(!isset($called_for) ? array() : array('acc_smtp_auth_session' => false)) + $params, !isset($called_for)
			) + $params;
		}
		$this->params = $params;

		unset($this->imapServer);
		unset($this->smtpServer);

		$this->user = $called_for ? $called_for : $GLOBALS['egw_info']['user']['account_id'];
	}

	/**
	 * Query quota, aliases, forwards, ... from imap and smtp backends and sets them as parameters on current object
	 *
	 * @return array with values for keys in self::$user_data
	 */
	public function getUserData()
	{
		if ($this->acc_smtp_type != 'emailadmin_smtp' && $this->smtpServer() &&
			($smtp_data = $this->smtpServer->getUserData($this->user)))
		{
			$this->params += $smtp_data;
		}
		// if we manage the mail-account, include that data too (imap has higher precedence)
		try {
			if ($this->acc_imap_type != 'emailadmin_imap' &&
				// do NOT query IMAP server, if we are in forward-only delivery-mode, imap will NOT answer, as switched off for that account!
				$this->params['deliveryMode'] != emailadmin_smtp::FORWARD_ONLY &&
				$this->imapServer() && is_a($this->imapServer, 'emailadmin_imap') &&
				($data = $this->imapServer->getUserData($GLOBALS['egw']->accounts->id2name($this->user))))
			{
				$this->params = array_merge($this->params, $data);
			}
		}
		catch(Horde_Imap_Client_Exception $e) {
			unset($e);
			// ignore eg. connection errors
		}
		catch(InvalidArgumentException $e) {
			unset($e);
			// ignore eg. missing admin user
		}
		$this->params += array_fill_keys(self::$user_data, null);	// make sure all keys exist now

		return (array)$data + (array)$smtp_data;
	}

	/**
	 * Get new Horde_Imap_Client imap server object
	 *
	 * @param bool|int|string $_adminConnection create admin connection if true or account_id or imap username
	 * @param int $_timeout =null timeout in secs, if none given fmail pref or default of 20 is used
	 * @return emailadmin_imap
	 */
	public function imapServer($_adminConnection=false, $_timeout=null)
	{
		if (!isset($this->imapServer))
		{
			// make sure mbstring.func_overload=0
			static $func_overload = null;
			if (is_null($func_overload)) $func_overload = extension_loaded('mbstring') ? ini_get('mbstring.func_overload') : 0;
			if ($func_overload) throw new egw_exception_assertion_failed('Fatal Error: EGroupware requires mbstring.func_overload=0 set in your php.ini!');

			$class = self::getIcClass($this->params['acc_imap_type']);
			$this->imapServer = new $class($this->params, $_adminConnection, $_timeout);
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
		if (empty($this->acc_imap_host) || empty($this->acc_imap_username) || empty($this->acc_imap_password))
		{
			return false;	// no imap host or credentials
		}
		// if we are not managing the mail-server, we do NOT need to check deliveryMode and accountStatus
		if ($this->acc_smtp_type == 'emailadmin_smtp')
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
			catch (Exception $ex) {
				unset($ex);
			}
		}
		return $this->deliveryMode != emailadmin_smtp::FORWARD_ONLY && $this->accountStatus == emailadmin_smtp::MAIL_ENABLED;
	}

	/**
	 * Get name and evtl. autoload incomming server class
	 *
	 * @param string $imap_type
	 * @param boolean $old_ic_server =false true: return emailadmin_oldimap as icServer, false: use new emailadmin_imap
	 * @return string
	 */
	public static function getIcClass($imap_type, $old_ic_server=false)
	{
		static $old2new_icClass = array(
			'defaultimap' => 'emailadmin_imap',
			'cyrusimap' => 'emailadmin_imap_cyrus',
			'emailadmin_dovecot' => 'emailadmin_imap_dovecot',
			'dbmaildbmailuser' => 'emailadmin_imap_dbmail',
			'dbmailqmailuser' => 'emailadmin_imap_dbmail_qmail',
		);

		// convert icClass to new name
		$icClass = $imap_type;
		if (isset($old2new_icClass[$icClass]))
		{
			$icClass = $old2new_icClass[$icClass];
		}
		// if old Net_IMAP based class requested, always return emailadmin_oldimap
		if ($old_ic_server)
		{
			$icClass = 'emailadmin_oldimap';
		}

		// fetch the IMAP / incomming server data
		if (!class_exists($icClass))
		{
			if (file_exists($file=EGW_INCLUDE_ROOT.'/emailadmin/inc/class.'.$icClass.'.inc.php'))
			{
				include_once($file);
			}
			else	// use default imap classes
			{
				$icClass = $old_ic_server ? 'emailadmin_oldimap' : 'emailadmin_imap';
			}
		}
		return $icClass;
	}

	/**
	 * Get smtp server object
	 *
	 * @return emailadmin_smtp
	 */
	public function smtpServer()
	{
		if (!isset($this->smtpServer))
		{
			$this->smtpServer = self::_smtp($this->params);
		}
		return $this->smtpServer;
	}

	/**
	 * Factory method to instanciate smtp server object
	 *
	 * @param array $params
	 * @return emailadmin_smtp
	 * @throws egw_exception_wrong_parameter
	 */
	protected static function _smtp(array $params)
	{
		$class = $params['acc_smtp_type'];
		if ($class=='defaultsmtp') $class='emailadmin_smtp';
		// not all smtp plugins are autoloadable eg. postifxldap (qmailUser)
		if (!class_exists($class) && file_exists($file=EGW_INCLUDE_ROOT.'/emailadmin/inc/class.'.$class.'.inc.php'))
		{
			require_once($file);
		}
		$smtp = new $class($params);
		$smtp->editForwardingAddress = false;
		$smtp->host = $params['acc_smtp_host'];
		$smtp->port = $params['acc_smtp_port'];
		switch($params['acc_smtp_ssl'])
		{
			case self::SSL_TLS:			// requires modified PHPMailer, or comment next two lines to use just ssl!
				$smtp->host = 'tlsv1://'.$smtp->host;
				break;
			case self::SSL_SSL:
				$smtp->host = 'ssl://'.$smtp->host;
				break;
			case self::SSL_STARTTLS:	// PHPMailer uses 'tls' for STARTTLS, not ssl connection with tls version >= 1 and no sslv2/3
				$smtp->host = 'tls://'.$smtp->host;
		}
		$smtp->smtpAuth = !empty($params['acc_smtp_username']);
		$smtp->username = $params['acc_smtp_username'];
		$smtp->password = $params['acc_smtp_password'];
		$smtp->defaultDomain = $params['acc_domain'];

		return $smtp;
	}

	/**
	 * Get identities of given or current account (for current user!)
	 *
	 * Standard identity is always first (as it has account_id=0 and we order account_id ASC).
	 *
	 * @param int|array|emailadmin_account $account=null default this account, empty array() to get all identities of current user
	 * @param boolean $replace_placeholders=false should placeholders like {{n_fn}} be replaced
	 * @param string $field='name' what to return as value: "ident_(realname|org|email|signature)" or default "name"=result from identity_name
	 * @return Iterator ident_id => identity_name of identity
	 */
	public /*static*/ function identities($account=null, $replace_placeholders=true, $field='name')
	{
		if (is_null($account)) $account = $this;
		$acc_id = is_scalar($account) ? $account : $account['acc_id'];

		$cols = array('ident_id', 'ident_name', 'ident_realname', 'ident_org', 'ident_email', 'acc_id', 'acc_imap_username', 'acc_imap_logintype', 'acc_domain');
		if (!in_array($field, array_merge($cols, array('name', 'params'))))
		{
			$cols[] = $field;
		}
		$cols[array_search('ident_id', $cols)] = self::IDENTITIES_TABLE.'.ident_id AS ident_id';
		$cols[array_search('acc_id', $cols)] = self::IDENTITIES_TABLE.'.acc_id AS acc_id';
		$cols[array_search('acc_imap_username', $cols)] = emailadmin_credentials::TABLE.'.cred_username AS acc_imap_username';

		$where[] = self::$db->expression(self::IDENTITIES_TABLE, self::IDENTITIES_TABLE.'.', array('account_id' => self::memberships()));
		if ($acc_id)
		{
			$where[] = self::$db->expression(self::IDENTITIES_TABLE, self::IDENTITIES_TABLE.'.', array('acc_id' => $acc_id));
		}
		$rs = self::$db->select(self::IDENTITIES_TABLE, $cols, $where, __LINE__, __FILE__, false,
			'ORDER BY '.self::IDENTITIES_TABLE.'.account_id,ident_realname,ident_org,ident_email', self::APP, null,
			' JOIN '.self::TABLE.' ON '.self::TABLE.'.acc_id='.self::IDENTITIES_TABLE.'.acc_id'.
			' LEFT JOIN '.emailadmin_credentials::TABLE.' ON '.self::TABLE.'.acc_id='.emailadmin_credentials::TABLE.'.acc_id AND '.
				emailadmin_credentials::TABLE.'.account_id='.(int)$GLOBALS['egw_info']['user']['account_id'].' AND '.
				'(cred_type&'.emailadmin_credentials::IMAP.') > 0');
		//error_log(__METHOD__."(acc_id=$acc_id, replace_placeholders=$replace_placeholders, field='$field') sql=".$rs->sql);

		return new egw_db_callback_iterator($rs,
			// process each row
			function($row) use ($replace_placeholders, $field)
			{
				// set email from imap-username (evtl. set from session, if acc_imap_logintype specified)
				if (in_array($field, array('name', 'ident_email', 'params')) &&
					empty($row['ident_email']) && empty($row['acc_imap_username']) && $row['acc_imap_logintype'])
				{
					$row = array_merge($row, emailadmin_credentials::from_session($row));
				}
				if (empty($row['ident_email'])) $row['ident_email'] = $row['acc_imap_username'];

				if ($field != 'name')
				{
					$data = $replace_placeholders ? array_merge($row, emailadmin_account::replace_placeholders($row)) : $row;
					return $field == 'params' ? $data : $data[$field];
				}
				return emailadmin_account::identity_name($row, $replace_placeholders);
			}, array(),
			function($row) { return $row['ident_id'];});
	}

	/**
	 * Get rfc822 email address from given identity or account
	 *
	 * @param array|emailadmin_account $identity
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
			$formatter = __CLASS__.'::rfc822';
		}
		$addresses = array();
		foreach(self::search(true, false) as $acc_id => $account)
		{
			$added = false;	// make sure each account get's at least added once, even if it uses an identical email address
			foreach($account->identities(null, true, 'params') as $identity)
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
	 * @param int|array|emailadmin_account $account=null default this account, empty array() to get all identities of current user
	 * @param string $order_email_top email address to order top
	 * @return array ident_id => ident_name pairs
	 */
	public /*static*/ function identities_ordered($account, $order_email_top)
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
	 * For full list of placeholders see addressbook_merge.
	 *
	 * @param array|emailadmin_account $identity=null
	 * @param int $account_id=null account_id of user, or current user
	 * @return array with modified fields
	 */
	public /*static*/ function replace_placeholders($identity=null, $account_id=null)
	{
		static $fields = array('ident_name','ident_realname','ident_org','ident_email','ident_signature');

		if (!$identity && isset($this)) $identity = $this;
		if (!is_array($identity) && !is_a($identity, 'emailadmin_account'))
		{
			throw new egw_exception_wrong_parameter(__METHOD__."() requires an identity or account as first parameter!");
		}
		$to_replace = array();
		foreach($fields as $name)
		{
			if (strpos($identity[$name], '{{') !== false || strpos($identity[$name], '$$') !== false)
			{
				$to_replace[$name] = $identity[$name];
			}
		}
		if ($to_replace)
		{
			static $merge=null;
			if (!isset($merge)) $merge = new addressbook_merge();
			if (!isset($account_id)) $account_id = $GLOBALS['egw_info']['user']['account_id'];
			foreach($to_replace as $name => &$value)
			{
				$err = null;
				$value = $merge->merge_string($value,
					(array)accounts::id2name($account_id, 'person_id'),
					$err, $name == 'ident_signature' ? 'text/html' : 'text/plain');
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
	 * @return array
	 * @throws egw_exception_not_found
	 */
	public static function read_identity($ident_id, $replace_placeholders=false)
	{
		if (!($data = self::$db->select(self::IDENTITIES_TABLE, '*', array(
			'ident_id' => $ident_id,
			'account_id' => self::memberships(),
		), __LINE__, __FILE__, false, '', self::APP)->fetch()))
		{
			throw new egw_exception_not_found();
		}
		if ($replace_placeholders)
		{
			$data = array_merge($data, self::replace_placeholders($data));

			// set empty email&realname from session / account
			if (empty($data['ident_email']) || empty($data['ident_realname']))
			{
				if (($account = self::read($data['acc_id'])))
				{
					if (empty($data['ident_email'])) $data['ident_email'] = $account->ident_email;
					if (empty($data['ident_realname'])) $data['ident_realname'] = $account->ident_realname;
				}
			}
			if (empty($data['ident_name']))
			{
				$data['ident_name'] = self::identity_name($data);
			}
		}
		return $data;
	}

	/**
	 * Store an identity in database
	 *
	 * Can be called static, if identity is given as parameter
	 *
	 * @param array|emailadmin_account $identity=null default standard identity of current account
	 * @return int ident_id of new/updated identity
	 */
	public /*static*/ function save_identity($identity=null)
	{
		if (!$identity && isset($this)) $identity = $this;
		if (!is_array($identity) && !is_a($identity, 'emailadmin_account'))
		{
			throw new egw_exception_wrong_parameter(__METHOD__."() requires an identity or account as first parameter!");
		}
		if (!($identity['acc_id'] > 0))
		{
			throw new egw_exception_wrong_parameter(__METHOD__."() no account / acc_id specified in identity!");
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
		if ($identity['ident_id'] > 0)
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
	 * @throws egw_exception_wrong_parameter if identity is standard identity of existing account
	 */
	public static function delete_identity($ident_id)
	{
		if (($acc_id = self::$db->select(self::TABLE, 'acc_id', array('ident_id' => $ident_id),
			__LINE__, __FILE__, 0, '', self::APP, 1)->fetchColumn()))
		{
			throw new egw_exception_wrong_parameter("Can not delete identity #$ident_id used as standard identity in account #$acc_id!");
		}
		self::$db->delete(self::IDENTITIES_TABLE, array('ident_id' => $ident_id), __LINE__, __FILE__, self::APP);

		return self::$db->affected_rows();
	}

	/**
	 * Give read access to protected parameters in $this->params
	 *
	 * To get $this->params you need to call getUserData before! It is never automatically loaded.
	 *
	 * @param type $name
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
			$this->getUserData();
		}
		return $this->params[$name];
	}

	/**
	 * Give read access to protected parameters in $this->params
	 *
	 * @param type $name
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
	 * ArrayAccess to emailadmin_account
	 *
	 * @param string $offset
	 * @return mixed
	 */
	public function offsetGet($offset)
	{
		return $this->__get($offset);
	}

	/**
	 * ArrayAccess to emailadmin_account
	 *
	 * @param string $offset
	 * @return boolean
	 */
	public function offsetExists($offset)
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
	 * @throws egw_exception_wrong_parameter
	 */
	public function offsetSet($offset, $value)
	{
		throw new egw_exception_wrong_parameter(__METHOD__."($offset, $value) No write access through ArrayAccess interface of emailadmin_account!");
	}

	/**
	 * ArrayAccess requires it but we dont want to give public write access
	 *
	 * Protected access has to use protected attributes!
	 *
	 * @param string $offset
	 * @throws egw_exception_wrong_parameter
	 */
	public function offsetUnset($offset)
	{
		throw new egw_exception_wrong_parameter(__METHOD__."($offset) No write access through ArrayAccess interface of emailadmin_account!");
	}

	/**
	 * Check which rights current user has on mail-account
	 *
	 * @param int $rights EGW_ACL_(READ|EDIT|DELETE)
	 * @param array|emailadmin_account $account=null default use this
	 * @return boolean
	 */
	public /*static*/ function check_access($rights, $account=null)
	{
		if (!isset($account)) $account = $this;

		if (!is_array($account) && !is_a($account, 'emailadmin_account'))
		{
			throw new egw_exception_wrong_parameter('$account must be either an array or an emailadmin_account object!');
		}

		$access = false;
		// emailadmin has all rights
		if (isset($GLOBALS['egw_info']['user']['apps']['emailadmin']))
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
					case EGW_ACL_READ:
						$access = true;
						break;

					case EGW_ACL_EDIT:
					case EGW_ACL_DELETE:
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
	 * @return email_account
	 * @throws egw_exception_not_found if account was not found (or not valid for current user)
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
				return self::$instances[$acc_id] = new emailadmin_account(self::$cache[$acc_id]);
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
			false, so_sql::fix_group_by_columns($group_by, $cols, self::TABLE, 'acc_id'),
			self::APP, 0, $join)->fetch()))
		{
			throw new egw_exception_not_found(lang('Account not found!').' (acc_id='.array2string($acc_id).')');
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
		return $ret = new emailadmin_account($data, $called_for);
	}

	/**
	 * Transform data returned from database (currently only fixing bool values)
	 *
	 * @param array $data
	 * @return array
	 */
	protected static function db2data(array $data)
	{
		foreach(array('acc_sieve_enabled','acc_further_identities','acc_user_editable','acc_smtp_auth_session') as $name)
		{
			if (isset($data[$name]))
			{
				$data[$name] = self::$db->from_bool($data[$name]);
			}
		}
		if (isset($data['account_id']) && !is_array($data['account_id']))
		{
			$data['account_id'] = explode(',', $data['account_id']);
		}
		return $data;
	}

	/**
	 * Save account data to db
	 *
	 * @param array $data
	 * @param int $user =null account-id to store account-infos of managed mail-server
	 * @return array $data plus added values for keys acc_id, ident_id from insert
	 * @throws egw_exception_wrong_parameter if called static without data-array
	 * @throws egw_exception_db
	 */
	public static function write(array $data, $user=null)
	{
		//error_log(__METHOD__."(".array2string($data).")");
		$data['acc_modifier'] = $GLOBALS['egw_info']['user']['account_id'];
		$data['acc_modified'] = time();

		// store account data
		if (!($data['acc_id'] > 0))
		{
			// set not set values which, are NOT NULL and therefore would give an SQL error
			$td = self::$db->get_table_definitions('emailadmin', self::TABLE);
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
		// store identity
		$new_ident_id = self::save_identity($data);
		if (!($data['ident_id'] > 0))
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
			if (($ids_to_remove = array_diff($old_account_ids, (array)$data['account_id'])))
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
		$valid_for = self::credentials_valid_for($data);
		// add imap credentials
		$cred_type = $data['acc_imap_username'] == $data['acc_smtp_username'] &&
			$data['acc_imap_password'] == $data['acc_smtp_password'] ? 3 : 1;
		emailadmin_credentials::write($data['acc_id'], $data['acc_imap_username'], $data['acc_imap_password'],
			$cred_type, $valid_for, $data['acc_imap_cred_id']);
		// add smtp credentials if necessary and different from imap
		if ($data['acc_smtp_username'] && $cred_type != 3)
		{
			emailadmin_credentials::write($data['acc_id'], $data['acc_smtp_username'], $data['acc_smtp_password'],
				2, $valid_for, $data['acc_smtp_cred_id'] != $data['acc_imap_cred_id'] ?
					$data['acc_smtp_cred_id'] : null);
		}
		// store or delete admin credentials
		if ($data['acc_imap_admin_username'] && $data['acc_imap_admin_password'])
		{
			emailadmin_credentials::write($data['acc_id'], $data['acc_imap_admin_username'],
				$data['acc_imap_admin_password'], emailadmin_credentials::ADMIN, 0,
				$data['acc_imap_admin_cred_id']);
		}
		else
		{
			emailadmin_credentials::delete($data['acc_id'], 0, emailadmin_credentials::ADMIN);
		}

		// store notification folders
		emailadmin_notifications::write($data['acc_id'], $data['notify_save_default'] ? 0 :
			($data['called_for'] ? $data['called_for'] : $GLOBALS['egw_info']['user']['account_id']),
			(array)$data['notify_folders']);

		// store account-information of managed mail server
		if ($user > 0 && $data['acc_smtp_type'] && $data['acc_smtp_type'] != 'emailadmin_smtp')
		{
			$smtp = self::_smtp($data);
			$smtp->setUserData($user, (array)$data['mailAlternateAddress'], (array)$data['mailForwardingAddress'],
				$data['deliveryMode'], $data['accountStatus'], $data['mailLocalAddress'], $data['quotaLimit']);
		}
		if ($user > 0 && $data['acc_imap_type'] && $data['acc_imap_type'] != 'emailadmin_imap')
		{
			$class = self::getIcClass($data['acc_imap_type']);
			$imap = new $class($data, true);
			$imap->setUserData($GLOBALS['egw']->accounts->id2name($user), $data['quotaLimit']);
		}

		// store domain of an account for all user like before as "mail_suffix" config
		if ($data['acc_domain'] && (!$data['account_id'] || $data['account_id'] == array(0)))
		{
			config::save_value('mail_suffix', $data['acc_domain'], 'phpgwapi', true);
		}

		self::cache_invalidate($data['acc_id']);
		//error_log(__METHOD__."() returning ".array2string($data));
		return $data;
	}

	/**
	 * Check for whom given credentials are to be stored
	 *
	 * @param array|emailadmin_account $account
	 * @param int $account_id =null
	 * @return boolean
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
			emailadmin_credentials::delete($acc_id);
			emailadmin_notifications::delete($acc_id);
			self::$db->delete(self::TABLE, array('acc_id' => $acc_id), __LINE__, __FILE__, self::APP);

			// invalidate caches
			foreach((array)$acc_id as $acc_id)
			{
				self::cache_invalidate($acc_id);
			}
			return self::$db->affected_rows();
		}
		if (!$account_id)
		{
			throw new egw_exception_wrong_parameter(__METHOD__."() no acc_id AND no account_id parameter!");
		}
		// delete all credentials belonging to given account(s)
		emailadmin_credentials::delete(0, $account_id);
		emailadmin_notifications::delete(0, $account_id);
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
	 * @param boolean|string $just_name =true true: return self::identity_name, false: return emailadmin_account objects,
	 *	string with attribute-name: return that attribute, eg. acc_imap_host or 'params' to return all attributes as array
	 * @param string $order_by ='acc_name ASC'
	 * @param int|boolean $offset =false offset or false to return all
	 * @param int $num_rows =0 number of rows to return, 0=default from prefs (if $offset !== false)
	 * @param boolean $replace_placeholders =true should placeholders like {{n_fn}} be replaced
	 * @return Iterator with acc_id => acc_name or emailadmin_account objects
	 */
	public static function search($only_current_user=true, $just_name=true, $order_by=null, $offset=false, $num_rows=0, $replace_placeholders=true)
	{
		//error_log(__METHOD__."($only_current_user, $just_name, '$order_by', $offset, $num_rows)");
		$where = array();
		if ($only_current_user)
		{
			$account_id = $only_current_user === true ? $GLOBALS['egw_info']['user']['account_id'] : $only_current_user;
			if (!is_numeric($account_id))
			{
				throw new egw_exception_wrong_parameter(__METHOD__."(".array2string($only_current_user).") is NO valid account_id");
			}
			$where[] = self::$db->expression(self::VALID_TABLE, self::VALID_TABLE.'.', array('account_id' => self::memberships($account_id)));
		}
		if (empty($order_by) || !preg_match('/^[a-z_]+ (ASC|DESC)$/i', $order_by))
		{
			// for current user prefer account with ident_email matching user email or domain
			// (this also helps notifications to account allowing to send with from address of current user / account_email)
			if ($only_current_user && $GLOBALS['egw_info']['user']['account_email'])
			{
				list(,$domain) = $account_email = $GLOBALS['egw_info']['user']['account_email'];
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
				$offset, so_sql::fix_group_by_columns($group_by, $cols, self::TABLE, 'acc_id').' ORDER BY '.$order_by,
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
		return new egw_db_callback_iterator(new ArrayIterator(self::$search_cache[$cache_key]),
			// process each row
			function($row) use ($just_name, $replace_placeholders, $account_id)
			{
				if ($replace_placeholders)
				{
					$row = array_merge($row, emailadmin_account::replace_placeholders($row, $account_id));
				}
				if (is_string($just_name))
				{
					return $just_name == 'params' ? $row : $row[$just_name];
				}
				elseif ($just_name)
				{
					return emailadmin_account::identity_name($row, false, $account_id);
				}
				return new emailadmin_account($row, $account_id);
			}, array(),
			// return acc_id as key
			function($row)
			{
				return $row['acc_id'];
			});
	}

	/**
	 * Get ID of default mail account for either IMAP or SMTP
	 *
	 * @param boolean $smtp =false false: usable for IMAP, true: usable for SMTP
	 * @return int
	 */
	static function get_default_acc_id($smtp=false)
	{
		try
		{
			foreach(emailadmin_account::search(true, 'params') as $acc_id => $params)
			{
				if ($smtp)
				{
					if (!$params['acc_smtp_host'] || !$params['acc_smtp_port']) continue;
					// check requirement of session, which is not available in async service!
					if (isset($GLOBALS['egw_info']['flags']['async-service']) && $params['acc_smtp_auth_session']) continue;
				}
				else
				{
					if (!$params['acc_imap_host'] || !$params['acc_imap_port']) continue;
					$account = new emailadmin_account($params);
					//error_log(__METHOD__.__LINE__.' '.$acc_id.':'.array2string($account));
					// continue if we have either no imap username or password
					if (!$account->is_imap()) continue;
				}
				return $acc_id;
			}
		}
		catch (Exception $e)
		{
			error_log(__METHOD__.__LINE__.' Error no Default available.'.$e->getMessage());
		}
		return null;
	}

	/**
	 * build an identity name
	 *
	 * @param array|emailadmin_account $account object or values for keys 'ident_(realname|org|email)', 'acc_(id|name|imap_username)'
	 * @param boolean $replace_placeholders =true should placeholders like {{n_fn}} be replaced
	 * @param int $account_id =null account_id of user we are called for
	 * @return string with htmlencoded angle brackets
	 */
	public static function identity_name($account, $replace_placeholders=true, $account_id=null)
	{
		if ($replace_placeholders)
		{
			$data = array(
				'ident_name' => $account['ident_name'],
				'ident_realname' => $account['ident_realname'],
				'ident_org' => $account['ident_org'],
				'ident_email' => $account['ident_email'],
				'acc_name' => $account['acc_name'],
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
		// user specified an own name --> use just it
		if (!empty($account['ident_name']))
		{
			$name = $account['ident_name'];
		}
		else
		{
			if (empty($account['ident_email']))
			{
				try {
					if (is_array($account) && empty($account['acc_imap_username']) && $account['acc_id'])
					{
						if (!isset($account['acc_imap_username']))
						{
							$account += emailadmin_credentials::read($account['acc_id'], null, array($account_id, 0));
						}
						if (empty($account['acc_imap_username']) && $account['acc_imap_logintype'] &&
							(!isset($account_id) || $account_id == $GLOBALS['egw_info']['user']['account_id']))
						{
							$account = array_merge($account, emailadmin_credentials::from_session($account));
						}
					}
					if (empty($account['ident_email']) && !empty($account['acc_imap_username']))
					{
						$account['ident_email'] = $account['acc_imap_username'];
					}
				}
				catch(Exception $e) {
					_egw_log_exception($e);
				}

			}
			if (strlen(trim($account['ident_realname'].$account['ident_org'])))
			{
				$name = $account['ident_realname'].' '.$account['ident_org'];
			}
			else
			{
				$name = $account['acc_name'];
			}
			if ($account['ident_email'])
			{
				$name .= ' <'.$account['ident_email'].'>';
			}
			if (stripos($name, $account['acc_name']) === false)
			{
				$name .= ' '.$account['acc_name'];
			}
		}
		//error_log(__METHOD__."(".array2string($account).", $replace_placeholders) returning ".array2string($name));
		return $name;
	}

	/**
	 * Check if account is for multiple users
	 *
	 * account_id == 0 == everyone, is multiple too!
	 *
	 * @param array|emailadmin_account $account value for key account_id (can be an array too!)
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
	 * @param type $user
	 * @return array
	 */
	protected static function memberships($user=null)
	{
		if (!$user) $user = $GLOBALS['egw_info']['user']['account_id'];

		$memberships = $GLOBALS['egw']->accounts->memberships($user, true);
		$memberships[] = $user;
		$memberships[] = 0;	// marks accounts valid for everyone

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

	emailadmin_account::init_static();

	foreach(emailadmin_account::search(true, false) as $acc_id => $account)
	{
		echo "<p>$acc_id: <a href='{$_SERVER['PHP_SELF']}?acc_id=$acc_id'>$account</a></p>\n";
	}
	if (isset($_GET['acc_id']) && (int)$_GET['acc_id'] > 0)
	{
		$account = emailadmin_account::read((int)$_GET['acc_id']);
		_debug_array($account);
	}
}*/

// need to be after test-code!
emailadmin_account::init_static();
