<?php
/**
 * EGroupware EMailAdmin: Wizard to create mail Api\Accounts
 *
 * @link http://www.stylite.de
 * @package emailadmin
 * @author Ralf Becker <rb@stylite.de>
 * @copyright (c) 2013-16 by Ralf Becker <rb@stylite.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

use EGroupware\Api;
use EGroupware\Api\Framework;
use EGroupware\Api\Acl;
use EGroupware\Api\Etemplate;
use EGroupware\Api\Mail;

/**
 * Wizard to create mail accounts
 *
 * Wizard uses follow heuristic to search for IMAP accounts:
 * 1. query Mozilla ISPDB for domain from email (perfering SSL over STARTTLS over insecure connection)
 * 2. guessing and verifying in DNS server-names based on domain from email:
 *	- (imap|smtp).$domain, mail.$domain
 *  - MX is *.mail.protection.outlook.com use (outlook|smtp).office365.com
 *  - MX for $domain
 *  - replace host in MX with (imap|smtp) or mail
 */
class admin_mail
{
	/**
	 * Enable logging of IMAP communication to given path, eg. /tmp/autoconfig.log
	 */
	const DEBUG_LOG = null;
	/**
	 * Connection timeout in seconds used in autoconfig, can and should be really short!
	 */
	const TIMEOUT = 3;
	/**
	 * Prefix for callback names
	 *
	 * Used as static::APP_CLASS in etemplate::exec(), to allow mail app extending this class.
	 */
	const APP_CLASS = 'admin.admin_mail.';

	/**
	 * 0: No SSL
	 */
	const SSL_NONE = Mail\Account::SSL_NONE;
	/**
	 * 1: STARTTLS on regular tcp connection/port
	 */
	const SSL_STARTTLS = Mail\Account::SSL_STARTTLS;
	/**
	 * 3: SSL (inferior to TLS!)
	 */
	const SSL_SSL = Mail\Account::SSL_SSL;
	/**
	 * 2: require TLS version 1+, no SSL version 2 or 3
	 */
	const SSL_TLS = Mail\Account::SSL_TLS;
	/**
	 * 8: if set, verify certifcate (currently not implemented in Horde_Imap_Client!)
	 */
	const SSL_VERIFY = Mail\Account::SSL_VERIFY;

	/**
	 * Log exception including trace to error-log, instead of just displaying the message.
	 *
	 * @var boolean
	 */
	public static $debug = false;

	/**
	 * Methods callable via menuaction
	 *
	 * @var array
	 */
	public $public_functions = array(
		'add' => true,
		'edit' => true,
		'ajax_activeAccounts' => true,
		'ews_custom_permissions' => true
	);

	/**
	 * Supported ssl types including none
	 *
	 * @var array
	 */
	public static $ssl_types = array(
		self::SSL_TLS => 'TLS',	// SSL with minimum TLS (no SSL v.2 or v.3), requires Horde_Imap_Client-2.16.0/Horde_Socket_Client-1.1.0
		self::SSL_SSL => 'SSL',
		self::SSL_STARTTLS => 'STARTTLS',
		'no' => 'no',
	);
	/**
	 * Convert ssl-type to Horde secure parameter
	 *
	 * @var array
	 */
	public static $ssl2secure = array(
		'SSL' => 'ssl',
		'STARTTLS' => 'tls',
		'TLS' => 'tlsv1',	// SSL with minimum TLS (no SSL v.2 or v.3), requires Horde_Imap_Client-2.16.0/Horde_Socket_Client-1.1.0
	);
	/**
	 * Convert ssl-type to eMailAdmin acc_(imap|sieve|smtp)_ssl integer value
	 *
	 * @var array
	 */
	public static $ssl2type = array(
		'TLS' => self::SSL_TLS,
		'SSL' => self::SSL_SSL,
		'STARTTLS' => self::SSL_STARTTLS,
		'no' => self::SSL_NONE,
	);

	/**
	 * Available IMAP login types
	 *
	 * @var array
	 */
	public static $login_types = array(
		'' => 'Username specified below for all',
		'standard'	=> 'username from account',
		'vmailmgr'	=> 'username@domainname',
		//'admin'		=> 'Username/Password defined by admin',
		'uidNumber' => 'UserId@domain eg. u1234@domain',
		'email'	    => 'EMail-address from account',
	);

	/**
	 * Options for further identities
	 *
	 * @var array
	 */
	public static $further_identities = array(
		0 => 'Forbid users to create identities',
		1 => 'Allow users to create further identities',
		2 => 'Allow users to create identities for aliases',
	);

	/**
	 * List of domains know to not support Sieve
	 *
	 * Used to switch Sieve off by default, thought users can allways try switching it on.
	 * Testing not existing Sieve with google takes a long time, as ports are open,
	 * but not answering ...
	 *
	 * @var array
	 */
	public static $no_sieve_blacklist = array('gmail.com', 'googlemail.com', 'outlook.office365.com');

	/**
	 * Is current use a mail administrator / has run rights for EMailAdmin
	 *
	 * @var boolean
	 */
	protected $is_admin = false;

	/**
	 * Constructor
	 */
	public function __construct()
	{
		$this->is_admin = isset($GLOBALS['egw_info']['user']['apps']['admin']);

		// for some reason most translation for account-wizard are in mail
		Api\Translation::add_app('mail');

		// Horde use locale for translation of error messages
		Api\Preferences::setlocale(LC_MESSAGES);
	}

	/**
	 * Step 1: IMAP account
	 *
	 * @param array $content
	 * @param type $msg
	 */
	public function add(array $content=array(), $msg='', $msg_type='success')
	{
		// otherwise we cant switch to ckeditor in edit
		Api\Html\CkEditorConfig::set_csp_script_src_attrs();

		$tpl = new Etemplate('admin.mailwizard');
		if (empty($content['account_id']))
		{
			$content['account_id'] = $GLOBALS['egw_info']['user']['account_id'];
		}
		// add some defaults if not already set (+= does not overwrite existing values!)
		$content += array(
			'ident_realname' => $GLOBALS['egw']->accounts->id2name($content['account_id'], 'account_fullname'),
			'ident_email' => $GLOBALS['egw']->accounts->id2name($content['account_id'], 'account_email'),
			'acc_imap_port' => 993,
			'manual_class' => 'emailadmin_manual',
		);
		// Select Options
		$sel_options['acc_imap_ssl'] = self::$ssl_types;
		// Not listing other server types, since this could be single account
		// EGroupware only allows different types for multiple accounts
		$sel_options['acc_imap_type'] = Mail\Types::getIMAPServerTypes(false);
		Framework::message($msg ? $msg : (string)$_GET['msg'], $msg_type);

		if (!empty($content['acc_imap_host']) || !empty($content['acc_imap_username']))
		{
			$readonlys['button[manual]'] = true;
			unset($content['manual_class']);
		}
		$tpl->exec(static::APP_CLASS.'autoconfig', $content, $sel_options, $readonlys, $content, 2);
	}

	/**
	 * Try to autoconfig an account
	 *
	 * @param array $content
	 */
	public function autoconfig(array $content)
	{
		// user pressed [Skip IMAP] --> jump to SMTP config
		if ($content['button'] && key($content['button']) == 'skip_imap')
		{
			unset($content['button']);
			if (!isset($content['acc_smtp_host'])) $content['acc_smtp_host'] = '';	// do manual mode right away
			return $this->smtp($content, lang('Skipping IMAP configuration!'));
		}
		$content['output'] = '';
		$sel_options = $readonlys = array();

		$content['connected'] = $connected = false;
		if (empty($content['acc_imap_username']))
		{
			$content['acc_imap_username'] = $content['ident_email'];
		}
		if (!empty($content['acc_imap_host']))
		{
			$hosts = array($content['acc_imap_host'] => true);
			if ($content['acc_imap_port'] > 0 && !in_array($content['acc_imap_port'], array(143,993)))
			{
				$ssl_type = (string)array_search($content['acc_imap_ssl'], self::$ssl2type);
				if ($ssl_type === '') $ssl_type = 'insecure';
				$hosts[$content['acc_imap_host']] = array(
					$ssl_type => $content['acc_imap_port'],
				);
			}
		}
		elseif (($ispdb = self::mozilla_ispdb($content['ident_email'])) && count($ispdb['imap']))
		{
			$content['ispdb'] = $ispdb;
			$content['output'] .= lang('Using data from Mozilla ISPDB for provider %1', $ispdb['displayName'])."\n";
			$hosts = array();
			foreach($ispdb['imap'] as $server)
			{
				if (!isset($hosts[$server['hostname']]))
				{
					$hosts[$server['hostname']] = array('username' => $server['username']);
				}
				if (strtoupper($server['socketType']) == 'SSL')	// try TLS first
				{
					$hosts[$server['hostname']]['TLS'] = $server['port'];
				}
				$hosts[$server['hostname']][strtoupper($server['socketType'])] = $server['port'];
				// make sure we prefer SSL over STARTTLS over insecure
				if (count($hosts[$server['hostname']]) > 2)
				{
					$hosts[$server['hostname']] = self::fix_ssl_order($hosts[$server['hostname']]);
				}
			}
		}
		else
		{
			$hosts = $this->guess_hosts($content['ident_email'], 'imap');
		}

		// iterate over all hosts and try to connect
		foreach($hosts as $host => $data)
		{
			$content['acc_imap_host'] = $host;
			// by default we check SSL, STARTTLS and at last an insecure connection
			if (!is_array($data)) $data = array('TLS' => 993, 'SSL' => 993, 'STARTTLS' => 143, 'insecure' => 143);

			foreach($data as $ssl => $port)
			{
				if ($ssl === 'username') continue;

				$content['acc_imap_ssl'] = (int)self::$ssl2type[$ssl];

				$e = null;
				try {
					$content['output'] .= "\n".Api\DateTime::to('now', 'H:i:s').": Trying $ssl connection to $host:$port ...\n";
					$content['acc_imap_port'] = $port;

					$imap = self::imap_client($content, self::TIMEOUT);

					//$content['output'] .= array2string($imap->capability());
					$imap->login();
					$content['output'] .= "\n".lang('Successful connected to %1 server%2.', 'IMAP', ' '.lang('and logged in'))."\n";
					if (!$imap->isSecureConnection())
					{
						$content['output'] .= lang('Connection is NOT secure! Everyone can read eg. your credentials.')."\n";
						$content['acc_imap_ssl'] = 'no';
					}
					//$content['output'] .= "\n\n".array2string($imap->capability());
					$content['connected'] = $connected = true;
					break 2;
				}
				catch(Horde_Imap_Client_Exception $e)
				{
					switch($e->getCode())
					{
					case Horde_Imap_Client_Exception::LOGIN_AUTHENTICATIONFAILED:
						$content['output'] .= "\n".$e->getMessage()."\n";
						break 3;	// no need to try other SSL or non-SSL connections, if auth failed

					case Horde_Imap_Client_Exception::SERVER_CONNECT:
						$content['output'] .= "\n".$e->getMessage()."\n";
						if ($ssl == 'STARTTLS') break 2;	// no need to try insecure connection on same port
						break;

					default:
						$content['output'] .= "\n".get_class($e).': '.$e->getMessage().' ('.$e->getCode().')'."\n";
						//$content['output'] .= $e->getTraceAsString()."\n";
					}
					if (self::$debug) _egw_log_exception($e);
				}
				catch(Exception $e) {
					$content['output'] .= "\n".get_class($e).': '.$e->getMessage().' ('.$e->getCode().')'."\n";
					//$content['output'] .= $e->getTraceAsString()."\n";
					if (self::$debug) _egw_log_exception($e);
				}
			}
		}
		if ($connected)	// continue with next wizard step: define folders
		{
			unset($content['button']);
			//EWS: skip steps
			if ( Mail\Account::is_ews_type( $content['acc_imap_type'] ) ) 
				return $this->smtp($content, lang('Successful connected to %1 server%2.', 'EWS', ' '.lang('and logged in')).
				($imap->isSecureConnection() ? '' : "\n".lang('Connection is NOT secure! Everyone can read eg. your credentials.')));
			else
				return $this->folder($content, lang('Successful connected to %1 server%2.', 'IMAP', ' '.lang('and logged in')).
				($imap->isSecureConnection() ? '' : "\n".lang('Connection is NOT secure! Everyone can read eg. your credentials.')));
		}
		// add validation error, if we can identify a field
		if (!$connected && $e instanceof Horde_Imap_Client_Exception)
		{
			switch($e->getCode())
			{
			case Horde_Imap_Client_Exception::LOGIN_AUTHENTICATIONFAILED:
				Etemplate::set_validation_error('acc_imap_username', lang($e->getMessage()));
				Etemplate::set_validation_error('acc_imap_password', lang($e->getMessage()));
				break;

			case Horde_Imap_Client_Exception::SERVER_CONNECT:
				Etemplate::set_validation_error('acc_imap_host', lang($e->getMessage()));
				break;
			}
		}
		$readonlys['button[manual]'] = true;
		unset($content['manual_class']);
		$sel_options['acc_imap_ssl'] = self::$ssl_types;
		// Not listing other server types, since this could be single account
		// EGroupware only allows different types for multiple accounts
		$sel_options['acc_imap_type'] = Mail\Types::getIMAPServerTypes(false);
		$tpl = new Etemplate('admin.mailwizard');
		$tpl->exec(static::APP_CLASS.'autoconfig', $content, $sel_options, $readonlys, $content, 2);
	}

	/**
	 * Step 2: Folder - let user select trash, sent, drafs and template folder
	 *
	 * @param array $content
	 * @param string $msg =''
	 * @param Horde_Imap_Client_Socket $imap =null
	 */
	public function folder(array $content, $msg='', Horde_Imap_Client_Socket $imap=null)
	{
		if (isset($content['button']))
		{
			list($button) = each($content['button']);
			unset($content['button']);
			switch($button)
			{
			case 'back':
				return $this->add($content);

			case 'continue':
				return $this->sieve($content);
			}
		}
		$content['msg'] = $msg;
		if (!isset($imap)) $imap = self::imap_client ($content);

		try {
			//_debug_array($content);
			$sel_options['acc_folder_sent'] = $sel_options['acc_folder_trash'] =
				$sel_options['acc_folder_draft'] = $sel_options['acc_folder_template'] =
				$sel_options['acc_folder_junk'] = $sel_options['acc_folder_archive'] =
				$sel_options['acc_folder_ham'] = self::mailboxes($imap, $content);
		}
		catch(Exception $e) {
			$content['msg'] = $e->getMessage();
			if (self::$debug) _egw_log_exception($e);
		}

		$tpl = new Etemplate('admin.mailwizard.folder');
		$tpl->exec(static::APP_CLASS.'folder', $content, $sel_options, array(), $content);
	}

	/**
	 * Query mailboxes and (optional) detect special folders
	 *
	 * @param Horde_Imap_Client_Socket $imap
	 * @param array &$content=null on return values for acc_folder_(sent|trash|draft|template)
	 * @return array with folders as key AND value
	 * @throws Horde_Imap_Client_Exception
	 */
	public static function mailboxes(Horde_Imap_Client_Socket $imap, array &$content=null)
	{
		// query all subscribed mailboxes
		$mailboxes = $imap->listMailboxes('*', Horde_Imap_Client::MBOX_SUBSCRIBED, array(
			'special_use' => true,
			'attributes' => true,	// otherwise special_use is only queried, but not returned ;-)
			'delimiter' => true,
		));
		//_debug_array($mailboxes);
		// list mailboxes by special-use attributes
		$folders = $attributes = $all = array();
		foreach($mailboxes as $mailbox => $data)
		{
			foreach($data['attributes'] as $attribute)
			{
				$attributes[$attribute][] = $mailbox;
			}
			$folders[$mailbox] = $mailbox.': '.implode(', ', $data['attributes']);
		}
		// pre-select send, trash, ... folder for user, by checking special-use attributes or common name(s)
		foreach(array(
			'acc_folder_sent'  => array('\\sent', 'sent'),
			'acc_folder_trash' => array('\\trash', 'trash'),
			'acc_folder_draft' => array('\\drafts', 'drafts'),
			'acc_folder_template' => array('', 'templates'),
			'acc_folder_junk'  => array('\\junk', 'junk', 'spam'),
			'acc_folder_ham'   => array('', 'ham'),
			'acc_folder_archive' => array('', 'archive'),
		) as $name => $common_names)
		{
			// first check special-use attributes
			if (($special_use = array_shift($common_names)))
			{
				foreach((array)$attributes[$special_use] as $mailbox)
				{
					if (empty($content[$name]) || strlen($mailbox) < strlen($content[$name]))
					{
						$content[$name] = $mailbox;
					}
				}
			}
			// no special use folder found, try common names
			if (empty($content[$name]))
			{
				foreach($mailboxes as $mailbox => $data)
				{
					$delimiter = !empty($data['delimiter']) ? $data['delimiter'] : '.';
					$name_parts = explode($delimiter, strtolower($mailbox));
					if (array_intersect($name_parts, $common_names) &&
						(empty($content[$name]) || strlen($mailbox) < strlen($content[$name]) && substr($content[$name], 0, 6) != 'INBOX'.$delimiter))
					{
						//error_log(__METHOD__."() $mailbox --> ".substr($name, 11).' folder');
						$content[$name] = $mailbox;
					}
					//else error_log(__METHOD__."() $mailbox does NOT match array_intersect(".array2string($name_parts).', '.array2string($common_names).')='.array2string(array_intersect($name_parts, $common_names)));
				}
			}
			$folders[(string)$content[$name]] .= ' --> '.substr($name, 11).' folder';
		}
		// uncomment for infos about selection process
		//$content['folder_output'] = implode("\n", $folders);

		return array_combine(array_keys($mailboxes), array_keys($mailboxes));
	}

	/**
	 * Step 3: Sieve
	 *
	 * @param array $content
	 * @param string $msg =''
	 */
	public function sieve(array $content, $msg='')
	{
		static $sieve_ssl2port = array(
			self::SSL_TLS => 5190,
			self::SSL_SSL => 5190,
			self::SSL_STARTTLS => array(4190, 2000),
			self::SSL_NONE => array(4190, 2000),
		);
		$content['msg'] = $msg;

		if (isset($content['button']))
		{
			list($button) = each($content['button']);
			unset($content['button']);
			switch($button)
			{
			case 'back':
				return $this->folder($content);

			case 'continue':
				if (!$content['acc_sieve_enabled'])
				{
					return $this->smtp($content);
				}
				break;
			}
		}
		// first try: hide manual config
		if (!isset($content['acc_sieve_enabled']))
		{
			list(, $domain) = explode('@', $content['acc_imap_username']);
			$content['acc_sieve_enabled'] = (int)!in_array($domain, self::$no_sieve_blacklist);
			$content['manual_class'] = 'emailadmin_manual';
		}
		else
		{
			unset($content['manual_class']);
			$readonlys['button[manual]'] = true;
		}
		// set default ssl and port
		if (!isset($content['acc_sieve_ssl'])) list($content['acc_sieve_ssl']) = each(self::$ssl_types);
		if (empty($content['acc_sieve_port'])) $content['acc_sieve_port'] = $sieve_ssl2port[$content['acc_sieve_ssl']];

		// check smtp connection
		if ($button == 'continue')
		{
			$content['sieve_connected'] = false;
			$content['sieve_output'] = '';
			unset($content['manual_class']);

			if (empty($content['acc_sieve_host']))
			{
				$content['acc_sieve_host'] = $content['acc_imap_host'];
			}
			// if use set non-standard port, use it
			if (!in_array($content['acc_sieve_port'], (array)$sieve_ssl2port[$content['acc_sieve_ssl']]))
			{
				$data = array($content['acc_sieve_ssl'] => $content['acc_sieve_port']);
			}
			else	// otherwise try all standard ports
			{
				$data = $sieve_ssl2port;
			}
			foreach($data as $ssl => $ports)
			{
				foreach((array)$ports as $port)
				{
					$content['acc_sieve_ssl'] = $ssl;
					$ssl_label = self::$ssl_types[$ssl];

					$e = null;
					try {
						$content['sieve_output'] .= "\n".Api\DateTime::to('now', 'H:i:s').": Trying $ssl_label connection to $content[acc_sieve_host]:$port ...\n";
						$content['acc_sieve_port'] = $port;
						$sieve = new Horde\ManageSieve(array(
							'host' => $content['acc_sieve_host'],
							'port' => $content['acc_sieve_port'],
							'secure' => self::$ssl2secure[(string)array_search($content['acc_sieve_ssl'], self::$ssl2type)],
							'timeout' => self::TIMEOUT,
							'logger' => self::DEBUG_LOG ? new admin_mail_logger(self::DEBUG_LOG) : null,
						));
						// connect to sieve server
						$sieve->connect();
						$content['sieve_output'] .= "\n".lang('Successful connected to %1 server%2.', 'Sieve','');
						// and log in
						$sieve->login($content['acc_imap_username'], $content['acc_imap_password']);
						$content['sieve_output'] .= ' '.lang('and logged in')."\n";
						$content['sieve_connected'] = true;

						unset($content['button']);
						return $this->smtp($content, lang('Successful connected to %1 server%2.', 'Sieve',
							' '.lang('and logged in')));
					}
					catch(Horde\ManageSieve\Exception\ConnectionFailed $e) {
						$content['sieve_output'] .= "\n".$e->getMessage().' '.$e->details."\n";
					}
					catch(Exception $e) {
						$content['sieve_output'] .= "\n".get_class($e).': '.$e->getMessage().
							($e->details ? ' '.$e->details : '').' ('.$e->getCode().')'."\n";
						$content['sieve_output'] .= $e->getTraceAsString()."\n";
						if (self::$debug) _egw_log_exception($e);
					}
				}
			}
			// not connected, and default ssl/port --> reset again to secure settings
			if ($data == $sieve_ssl2port)
			{
				list($content['acc_sieve_ssl']) = each(self::$ssl_types);
				$content['acc_sieve_port'] = $sieve_ssl2port[$content['acc_sieve_ssl']];
			}
		}
		// add validation error, if we can identify a field
		if (!$content['sieve_connected'] && $e instanceof Exception)
		{
			switch($e->getCode())
			{
			case 61:	// connection refused
			case 60:	// connection timed out (imap.googlemail.com returns that for none-ssl/4190/2000)
			case 65:	// no route ot host (imap.googlemail.com returns that for ssl/5190)
				Etemplate::set_validation_error('acc_sieve_host', lang($e->getMessage()));
				Etemplate::set_validation_error('acc_sieve_port', lang($e->getMessage()));
				break;
			}
			$content['msg'] = lang('No sieve support detected, either fix configuration manually or leave it switched off.');
			$content['acc_sieve_enabled'] = 0;
		}
		$sel_options['acc_sieve_ssl'] = self::$ssl_types;
		$tpl = new Etemplate('admin.mailwizard.sieve');
		$tpl->exec(static::APP_CLASS.'sieve', $content, $sel_options, $readonlys, $content, 2);
	}

	/**
	 * Step 4: SMTP
	 *
	 * @param array $content
	 * @param string $msg =''
	 */
	public function smtp(array $content, $msg='')
	{
		static $smtp_ssl2port = array(
			self::SSL_NONE => 25,
			self::SSL_SSL => 465,
			self::SSL_TLS => 465,
			self::SSL_STARTTLS => 587,
		);
		$content['msg'] = $msg;

		if (isset($content['button']))
		{
			list($button) = each($content['button']);
			unset($content['button']);
			switch($button)
			{
			case 'back':
				if ( Mail\Account::is_ews_type( $content['acc_imap_type'] ) ) 
					return $this->add($content);
				else
					return $this->sieve($content);
			}
		}
		// first try: hide manual config
		if (!isset($content['acc_smtp_host']))
		{
			$content['manual_class'] = 'emailadmin_manual';
		}
		else
		{
			unset($content['manual_class']);
			$readonlys['button[manual]'] = true;
		}
		// copy username/password from imap
		if (!isset($content['acc_smtp_username'])) $content['acc_smtp_username'] = $content['acc_imap_username'];
		if (!isset($content['acc_smtp_password'])) $content['acc_smtp_password'] = $content['acc_imap_password'];
		// set default ssl
		if (!isset($content['acc_smtp_ssl'])) list($content['acc_smtp_ssl']) = each(self::$ssl_types);
		if (empty($content['acc_smtp_port'])) $content['acc_smtp_port'] = $smtp_ssl2port[$content['acc_smtp_ssl']];

		// check smtp connection
		if ($button == 'continue')
		{
			$content['smtp_connected'] = false;
			$content['smtp_output'] = '';
			unset($content['manual_class']);

			if (!empty($content['acc_smtp_host']))
			{
				$hosts = array($content['acc_smtp_host'] => true);
				if ((string)$content['acc_smtp_ssl'] !== (string)self::SSL_TLS || $content['acc_smtp_port'] != $smtp_ssl2port[$content['acc_smtp_ssl']])
				{
					$ssl_type = (string)array_search($content['acc_smtp_ssl'], self::$ssl2type);
					$hosts[$content['acc_smtp_host']] = array(
						$ssl_type => $content['acc_smtp_port'],
					);
				}
			}
			elseif($content['ispdb'] && !empty($content['ispdb']['smtp']))
			{
				$content['smtp_output'] .= lang('Using data from Mozilla ISPDB for provider %1', $content['ispdb']['displayName'])."\n";
				$hosts = array();
				foreach($content['ispdb']['smtp'] as $server)
				{
					if (!isset($hosts[$server['hostname']]))
					{
						$hosts[$server['hostname']] = array('username' => $server['username']);
					}
					if (strtoupper($server['socketType']) == 'SSL')	// try TLS first
					{
						$hosts[$server['hostname']]['TLS'] = $server['port'];
					}
					$hosts[$server['hostname']][strtoupper($server['socketType'])] = $server['port'];
					// make sure we prefer SSL over STARTTLS over insecure
					if (count($hosts[$server['hostname']]) > 2)
					{
						$hosts[$server['hostname']] = self::fix_ssl_order($hosts[$server['hostname']]);
					}
				}
			}
			else
			{
				$hosts = $this->guess_hosts($content['ident_email'], 'smtp');
			}
			foreach($hosts as $host => $data)
			{
				$content['acc_smtp_host'] = $host;
				if (!is_array($data))
				{
					$data = array('TLS' => 465, 'SSL' => 465, 'STARTTLS' => 587, '' => 25);
				}
				foreach($data as $ssl => $port)
				{
					if ($ssl === 'username') continue;

					$content['acc_smtp_ssl'] = (int)self::$ssl2type[$ssl];

					$e = null;
					try {
						$content['smtp_output'] .= "\n".Api\DateTime::to('now', 'H:i:s').": Trying $ssl connection to $host:$port ...\n";
						$content['acc_smtp_port'] = $port;

						$mail = new Horde_Mail_Transport_Smtphorde($params=array(
							'username' => $content['acc_smtp_username'],
							'password' => $content['acc_smtp_password'],
							'host' => $content['acc_smtp_host'],
							'port' => $content['acc_smtp_port'],
							'secure' => self::$ssl2secure[(string)array_search($content['acc_smtp_ssl'], self::$ssl2type)],
							'timeout' => self::TIMEOUT,
							'debug' => self::DEBUG_LOG,
						));
						// create smtp connection and authenticate, if credentials given
						$smtp = $mail->getSMTPObject();
						$content['smtp_output'] .= "\n".lang('Successful connected to %1 server%2.', 'SMTP',
							(!empty($content['acc_smtp_username']) ? ' '.lang('and logged in') : ''))."\n";
						if (!$smtp->isSecureConnection())
						{
							if (!empty($content['acc_smtp_username']))
							{
								$content['smtp_output'] .= lang('Connection is NOT secure! Everyone can read eg. your credentials.')."\n";
							}
							$content['acc_smtp_ssl'] = 'no';
						}
						// Horde_Smtp always try to use STARTTLS, adjust our ssl-parameter if successful
						elseif (!($content['acc_smtp_ssl'] > self::SSL_NONE))
						{
							//error_log(__METHOD__."() new Horde_Mail_Transport_Smtphorde(".array2string($params).")->getSMTPObject()->isSecureConnection()=".array2string($smtp->isSecureConnection()));
							$content['acc_smtp_ssl'] = self::SSL_STARTTLS;
						}
						// try sending a mail to a different domain, if not authenticated, to see if that's required
						if (empty($content['acc_smtp_username']))
						{
							$smtp->send($content['ident_email'], 'noreply@example.com', '');
							$content['smtp_output'] .= "\n".lang('Relay access checked')."\n";
						}
						$content['smtp_connected'] = true;
						unset($content['button']);
						return $this->edit($content, lang('Successful connected to %1 server%2.', 'SMTP',
							empty($content['acc_smtp_username']) ? ' - '.lang('Relay access checked') : ' '.lang('and logged in')));
					}
					// unfortunately LOGIN_AUTHENTICATIONFAILED and SERVER_CONNECT are thrown as Horde_Mail_Exception
					// while others are thrown as Horde_Smtp_Exception --> using common base Horde_Exception_Wrapped
					catch(Horde_Exception_Wrapped $e)
					{
						switch($e->getCode())
						{
						case Horde_Smtp_Exception::LOGIN_AUTHENTICATIONFAILED:
						case Horde_Smtp_Exception::LOGIN_REQUIREAUTHENTICATION:
						case Horde_Smtp_Exception::UNSPECIFIED:
							$content['smtp_output'] .= "\n".$e->getMessage()."\n";
							break;
						case Horde_Smtp_Exception::SERVER_CONNECT:
							$content['smtp_output'] .= "\n".$e->getMessage()."\n";
							break;
						default:
							$content['smtp_output'] .= "\n".$e->getMessage().' ('.$e->getCode().')'."\n";
							break;
						}
						if (self::$debug) _egw_log_exception($e);
					}
					catch(Horde_Smtp_Exception $e)
					{
						// prever $e->details over $e->getMessage() as it contains original message from SMTP server (eg. relay access denied)
						$content['smtp_output'] .= "\n".(empty($e->details) ? $e->getMessage().' ('.$e->getCode().')' : $e->details)."\n";
						//$content['smtp_output'] .= $e->getTraceAsString()."\n";
						if (self::$debug) _egw_log_exception($e);
					}
					catch(Exception $e) {
						$content['smtp_output'] .= "\n".get_class($e).': '.$e->getMessage().' ('.$e->getCode().')'."\n";
						//$content['smtp_output'] .= $e->getTraceAsString()."\n";
						if (self::$debug) _egw_log_exception($e);
					}
				}
			}
		}
		// add validation error, if we can identify a field
		if (!$content['smtp_connected'] && $e instanceof Horde_Exception_Wrapped)
		{
			switch($e->getCode())
			{
			case Horde_Smtp_Exception::LOGIN_AUTHENTICATIONFAILED:
			case Horde_Smtp_Exception::LOGIN_REQUIREAUTHENTICATION:
			case Horde_Smtp_Exception::UNSPECIFIED:
				Etemplate::set_validation_error('acc_smtp_username', lang($e->getMessage()));
				Etemplate::set_validation_error('acc_smtp_password', lang($e->getMessage()));
				break;

			case Horde_Smtp_Exception::SERVER_CONNECT:
				Etemplate::set_validation_error('acc_smtp_host', lang($e->getMessage()));
				Etemplate::set_validation_error('acc_smtp_port', lang($e->getMessage()));
				break;
			}
		}
		$sel_options['acc_smtp_ssl'] = self::$ssl_types;
		$tpl = new Etemplate('admin.mailwizard.smtp');
		$tpl->exec(static::APP_CLASS.'smtp', $content, $sel_options, $readonlys, $content, 2);
	}

	/**
	 * Edit mail account(s)
	 *
	 * Gets either called with GET parameter:
	 *
	 * a) account_id from admin >> Manage users to edit / add mail accounts for a user
	 *    --> shows selectbox to switch between different mail accounts of user and "create new account"
	 *
	 * b) via mail_wizard proxy class by regular mail user to edit (acc_id GET parameter) or create new mail account
	 *
	 * @param array $content =null
	 * @param string $msg =''
	 * @param string $msg_type ='success'
	 */
	public function edit(array $content=null, $msg='', $msg_type='success')
	{
		unset($content['manual_class']);
		// app is trying to tell something, while redirecting to wizard
		if (empty($content) && $_GET['acc_id'] && empty($msg) && !empty( $_GET['msg']))
		{
			if (stripos($_GET['msg'],'fatal error:')!==false || $_GET['msg_type'] == 'error') $msg_type = 'error';
		}
		if ($content['acc_id'] || (isset($_GET['acc_id']) && (int)$_GET['acc_id'] > 0) ) Mail::unsetCachedObjects($content['acc_id']?$content['acc_id']:$_GET['acc_id']);
		$tpl = new Etemplate('admin.mailaccount');

		if (!is_array($content) || !empty($content['acc_id']) && isset($content['old_acc_id']) && $content['acc_id'] != $content['old_acc_id'])
		{
			if (!is_array($content)) $content = array();
			if ($this->is_admin && isset($_GET['account_id']))
			{
				$content['called_for'] = (int)$_GET['account_id'];
				$content['accounts'] = iterator_to_array(Mail\Account::search($content['called_for']));
				if ($content['accounts'])
				{
					list($content['acc_id']) = each($content['accounts']);
					//error_log(__METHOD__.__LINE__.'.'.array2string($content['acc_id']));
					// test if the "to be selected" acccount is imap or not
					if (count($content['accounts'])>1 && Mail\Account::is_multiple($content['acc_id']))
					{
						try {
							$account = Mail\Account::read($content['acc_id'], $content['called_for']);
							//try to select the first account that is of type imap
							if (!$account->is_imap())
							{
								list($content['acc_id']) = each($content['accounts']);
								//error_log(__METHOD__.__LINE__.'.'.array2string($content['acc_id']));
							}
						}
						catch(Api\Exception\NotFound $e) {
							if (self::$debug) _egw_log_exception($e);
						}
					}
				}
				if (!$content['accounts'])	// no email account, call wizard
				{
					return $this->add(array('account_id' => (int)$_GET['account_id']));
				}
				$content['accounts']['new'] = lang('Create new account');
			}
			if (isset($_GET['acc_id']) && (int)$_GET['acc_id'] > 0)
			{
				$content['acc_id'] = (int)$_GET['acc_id'];
			}
			// clear current account-data, as account has changed and we going to read selected one
			$content = array_intersect_key($content, array_flip(array('called_for', 'accounts', 'acc_id', 'tabs')));

			if ($content['acc_id'] > 0)
			{
				try {
					$account = Mail\Account::read($content['acc_id'], $this->is_admin && $content['called_for'] ?
						$content['called_for'] : $GLOBALS['egw_info']['user']['account_id']);
					$account->getUserData();	// quota, aliases, forwards etc.
					$content += $account->params;
					$content['acc_sieve_enabled'] = (string)($content['acc_sieve_enabled']);
					$content['notify_use_default'] = !$content['notify_account_id'];
					self::fix_account_id_0($content['account_id']);

					// read identities (of current user) and mark std identity
					$content['identities'] = iterator_to_array(Mail\Account::identities($account, true, 'name', $content['called_for']));
					$content['std_ident_id'] = $content['ident_id'];
					$content['identities'][$content['std_ident_id']] = lang('Standard identity');
					// change self::SSL_NONE (=0) to "no" used in sel_options
					foreach(array('imap','smtp','sieve') as $type)
					{
						if (!$content['acc_'.$type.'_ssl']) $content['acc_'.$type.'_ssl'] = 'no';
					}

				}
				catch(Api\Exception\NotFound $e) {
					if (self::$debug) _egw_log_exception($e);
					Framework::window_close(lang('Account not found!'));
				}
				catch(Exception $e) {
					if (self::$debug) _egw_log_exception($e);
					Framework::window_close($e->getMessage().' ('.get_class($e).': '.$e->getCode().')');
				}
			}
			elseif ($content['acc_id'] === 'new')
			{
				$content['account_id'] = $content['called_for'];
				$content['old_acc_id'] = $content['acc_id'];	// to not call add/wizard, if we return from to
				unset($content['tabs']);
				return $this->add($content);
			}
		}
		// some defaults for new accounts
		if (!isset($content['account_id']) || empty($content['acc_id']) || $content['acc_id'] === 'new')
		{
			if (!isset($content['account_id'])) $content['account_id'] = array($GLOBALS['egw_info']['user']['account_id']);
			$content['acc_user_editable'] = $content['acc_further_identities'] = true;
			$readonlys['ident_id'] = true;	// need to create standard identity first
		}
		if (empty($content['acc_name']))
		{
			$content['acc_name'] = $content['ident_email'];
		}
		// disable some stuff for non-emailadmins (all values are preserved!)
		if (!$this->is_admin)
		{
			$readonlys = array(
				'account_id' => true, 'button[multiple]' => true, 'acc_user_editable' => true,
				'acc_further_identities' => true,
				'acc_imap_type' => true, 'acc_imap_logintype' => true, 'acc_domain' => true,
				'acc_imap_admin_username' => true, 'acc_imap_admin_password' => true,
				'acc_smtp_type' => true, 'acc_smtp_auth_session' => true,
			);
		}

		// ensure correct values for single user mail accounts (we only hide them client-side)
		if (!($is_multiple = Mail\Account::is_multiple($content)) && $content['acc_imap_type'] != 'EGroupware\Api\Mail\Imap' && !Mail\Account::is_ews_type( $content['acc_imap_type'] ) )
		{
			$content['acc_imap_type'] = 'EGroupware\\Api\\Mail\\Imap';
			unset($content['acc_imap_login_type']);
			$content['acc_smtp_type'] = 'EGroupware\\Api\\Mail\\Smtp';
			unset($content['acc_smtp_auth_session']);
			unset($content['notify_use_default']);
		}
		// copy ident_email_alias selectbox back to regular name
		elseif (isset($content['ident_email_alias']) && !empty ($content['ident_email_alias']))
		{
			$content['ident_email'] = $content['ident_email_alias'];
		}
		$edit_access = Mail\Account::check_access(Acl::EDIT, $content);

		// disable notification save-default and use-default, if only one account or no edit-rights
		$tpl->disableElement('notify_save_default', !$is_multiple || !$edit_access);
		$tpl->disableElement('notify_use_default', !$is_multiple);

		if (isset($content['smimeKeyUpload'])
			&& ($pkcs12 = file_get_contents($content['smimeKeyUpload']['tmp_name'])))
		{
			$smime = new Mail\Smime;
			switch($content['smimeKeyUpload']['type'])
			{
			case 'application/x-pkcs12':
				$cert_info = $smime->extractCertPKCS12($pkcs12, $content['smime_pkcs12_password']);
				if (is_array($cert_info))
				{
					$content['acc_smime_password'] = $cert_info['pkey'];
					if ($cert_info['cert'])
					{
						$AB_bo = new addressbook_bo();
						$AB_bo->set_smime_keys(array(
							$content['ident_email'] => $cert_info['cert']
						));
					}
				}
				else
				{
					$tpl->set_validation_error('smimeKeyUpload', lang('Could not extract private key from given p12 file. Either the p12 file is broken or password is wrong!'));
				}
				break;
			case 'application/x-iwork-keynote-sffkey':
				$content['acc_smime_password'] = $pkcs12;
				break;
			}
		}
		if (isset($content['button']))
		{
			list($button) = each($content['button']);
			unset($content['button']);
			switch($button)
			{
			case 'wizard':
				// if we just came from wizard, go back to last page/step
				if (isset($content['smtp_connected']))
				{
					return $this->smtp($content);
				}
				// otherwise start with first step
				return $this->autoconfig($content);

			case 'delete_identity':
				// delete none-standard identity of current user
				if (($this->is_admin || $content['acc_further_identities']) &&
					$content['ident_id'] > 0 && $content['std_ident_id'] != $content['ident_id'])
				{
					Mail\Account::delete_identity($content['ident_id']);
					$msg = lang('Identity deleted');
					unset($content['identities'][$content['ident_id']]);
					$content['ident_id'] = $content['std_ident_id'];
				}
				break;

			case 'save':
			case 'apply':
				try {
					// save none-standard identity for current user
					if ($content['acc_id'] && $content['acc_id'] !== 'new' &&
						($this->is_admin || $content['acc_further_identities']) &&
						$content['std_ident_id'] != $content['ident_id'])
					{
						$content['ident_id'] = Mail\Account::save_identity(array(
							'account_id' => $content['called_for'] ? $content['called_for'] : $GLOBALS['egw_info']['user']['account_id'],
						)+$content);
						$content['identities'][$content['ident_id']] = Mail\Account::identity_name($content);
						$msg = lang('Identity saved.');
						if ($edit_access) $msg .= ' '.lang('Switch back to standard identity to save account.');
					}
					elseif ($edit_access)
					{
						// if admin username/password given, check if it is valid
						$account = new Mail\Account($content);
						if ($account->acc_imap_administration)
						{
							$imap = $account->imapServer(true);
							if ($imap) $imap->checkAdminConnection();
						}
						// test sieve connection, if not called for other user, enabled and credentials available
						if (!$content['called_for'] && $account->acc_sieve_enabled && $account->acc_imap_username)
						{
							$account->imapServer()->retrieveRules();
						}
						$new_account = !($content['acc_id'] > 0);
						// check for deliveryMode="forwardOnly", if a forwarding-address is given
						if ($content['acc_smtp_type'] != 'EGroupware\\Api\\Mail\\Smtp' &&
							$content['deliveryMode'] == Mail\Smtp::FORWARD_ONLY &&
							empty($content['mailForwardingAddress']))
						{
							Etemplate::set_validation_error('mailForwardingAddress', lang('Field must not be empty !!!'));
							throw new Api\Exception\WrongUserinput(lang('You need to specify a forwarding address, when checking "%1"!', lang('Forward only')));
						}
						// set notifications to store according to checkboxes
						if ($content['notify_save_default'])
						{
							$content['notify_account_id'] = 0;
						}
						elseif (!$content['notify_use_default'])
						{
							$content['notify_account_id'] = $content['called_for'] ?
								$content['called_for'] : $GLOBALS['egw_info']['user']['account_id'];
						}
						self::fix_account_id_0($content['account_id'], true);
						$content = Mail\Account::write($content, $content['called_for'] || !$this->is_admin ?
							$content['called_for'] : $GLOBALS['egw_info']['user']['account_id']);
						self::fix_account_id_0($content['account_id']);
						$msg = lang('Account saved.');
						// user wants default notifications
						if ($content['acc_id'] && $content['notify_use_default'])
						{
							// delete own ones
							Mail\Notifications::delete($content['acc_id'], $content['called_for'] ?
								$content['called_for'] : $GLOBALS['egw_info']['user']['account_id']);
							// load default ones
							$content = array_merge($content, Mail\Notifications::read($content['acc_id'], 0));
						}
						// add new std identity entry
						if ($new_account)
						{
							$content['std_ident_id'] = $content['ident_id'];
							$content['identities'] = array(
								$content['std_ident_id'] => lang('Standard identity'));
						}
						if (isset($content['accounts']))
						{
							if (!isset($content['accounts'][$content['acc_id']]))	// insert new account as top, not bottom
							{
								$content['accounts'] = array($content['acc_id'] => '') + $content['accounts'];
							}
							$content['accounts'][$content['acc_id']] = Mail\Account::identity_name($content, false);
						}
						if ( $content['acc_imap_type'] &&  Mail\Account::is_ews_type( $content['acc_imap_type'] ) ) {
							if ( $content['clear_grid'] ) {
								$content['ews_permissions'] = array();
								$content['clear_grid'] = false;
								Api\Mail_EWS::storeFolderPermissions( $content['ews_permissions'], $content['acc_id'] );
							}
						}
					}
					else
					{
						if ($content['notify_use_default'] && $content['notify_account_id'])
						{
							// delete own ones
							if (Mail\Notifications::delete($content['acc_id'], $content['called_for'] ?
								$content['called_for'] : $GLOBALS['egw_info']['user']['account_id']))
							{
								$msg = lang('Notification folders updated.');
							}
							// load default ones
							$content = array_merge($content, Mail\Notifications::read($content['acc_id'], 0));
						}
						if (!$content['notify_use_default'] && is_array($content['notify_folders']))
						{
							$content['notify_account_id'] = $content['called_for'] ?
								$content['called_for'] : $GLOBALS['egw_info']['user']['account_id'];
							if (Mail\Notifications::write($content['acc_id'], $content['notify_account_id'],
								$content['notify_folders']))
							{
								$msg = lang('Notification folders updated.');
							}
						}
						if ($content['acc_user_forward'] && !empty($content['acc_smtp_type']) && $content['acc_smtp_type'] != 'EGroupware\\Api\\Mail\\Smtp')
						{
							$account = new Mail\Account($content);
							$account->smtpServer()->saveSMTPForwarding($content['called_for'] ?
								$content['called_for'] : $GLOBALS['egw_info']['user']['account_id'],
								$content['mailForwardingAddress'],
								$content['forwardOnly'] ? null : 'yes');
						}
					}
				}
				catch (Horde_Imap_Client_Exception $e)
				{
					_egw_log_exception($e);
					$tpl->set_validation_error('acc_imap_admin_username', $msg=lang($e->getMessage()).($e->details?', '.lang($e->details):''));
					$msg_type = 'error';
					$content['tabs'] = 'admin.mailaccount.imap';	// should happen automatic
					break;
				}
				catch (Horde\ManageSieve\Exception\ConnectionFailed $e)
				{
					_egw_log_exception($e);
					$tpl->set_validation_error('acc_sieve_port', $msg=lang($e->getMessage()));
					$msg_type = 'error';
					$content['tabs'] = 'admin.mailaccount.sieve';	// should happen automatic
					break;
				}
				catch (Exception $e) {
					$msg = lang('Error saving account!')."\n".$e->getMessage();
					$button = 'apply';
					$msg_type = 'error';
				}
				if ($content['acc_id']) Mail::unsetCachedObjects($content['acc_id']);
				if (stripos($msg,'fatal error:')!==false) $msg_type = 'error';
				Framework::refresh_opener($msg, 'emailadmin', $content['acc_id'], $new_account ? 'add' : 'update', null, null, null, $msg_type);
				if ($button == 'save') Framework::window_close();
				break;

			case 'delete':
				if (!Mail\Account::check_access(Acl::DELETE, $content))
				{
					$msg = lang('Permission denied!');
					$msg_type = 'error';
				}
				elseif (Mail\Account::delete($content['acc_id']) > 0)
				{
					if ($content['acc_id']) Mail::unsetCachedObjects($content['acc_id']);
					Framework::refresh_opener(lang('Account deleted.'), 'emailadmin', $content['acc_id'], 'delete');
					Framework::window_close();
				}
				else
				{
					$msg = lang('Failed to delete account!');
					$msg_type = 'error';
				}
			}
		}

		// disable delete button for new, not yet saved entries, if no delete rights or a non-standard identity selected
		$readonlys['button[delete]'] = empty($content['acc_id']) ||
			!Mail\Account::check_access(Acl::DELETE, $content) ||
			$content['ident_id'] != $content['std_ident_id'];

		// if account is for multiple user, change delete confirmation to reflect that
		if (Mail\Account::is_multiple($content))
		{
			$tpl->setElementAttribute('button[delete]', 'onclick', "et2_dialog.confirm(widget,'This is NOT a personal mail account!\\n\\nAccount will be deleted for ALL users!\\n\\nAre you really sure you want to do that?','Delete this account')");
		}

		// if no edit access, make whole dialog readonly
		if (!$edit_access)
		{
			$readonlys['__ALL__'] = true;
			$readonlys['button[cancel]'] = false;
			// allow to edit notification-folders
			$readonlys['button[save]'] = $readonlys['button[apply]'] =
				$readonlys['notify_folders'] = $readonlys['notify_use_default'] = false;
		}

		$sel_options['acc_imap_ssl'] = $sel_options['acc_sieve_ssl'] =
			$sel_options['acc_smtp_ssl'] = self::$ssl_types;

		// admin access to account with no credentials available
		if ($this->is_admin && (empty($content['acc_imap_username']) || empty($content['acc_imap_host']) || $content['called_for']))
		{
			// cant connection to imap --> allow free entries in taglists
			foreach(array('acc_folder_sent', 'acc_folder_trash', 'acc_folder_draft', 'acc_folder_template', 'acc_folder_junk') as $folder)
			{
				$tpl->setElementAttribute($folder, 'allowFreeEntries', true);
			}
		}
		elseif ( Mail\Account::is_ews_type( $content['acc_imap_type'] ) ) 
		{
			try {

				$sel_options['acc_folder_sent'] = $sel_options['acc_folder_trash'] =
					$sel_options['acc_folder_draft'] = $sel_options['acc_folder_template'] =
					$sel_options['acc_folder_junk'] = $sel_options['acc_folder_archive'] =
					$sel_options['notify_folders'] = $sel_options['acc_folder_ham'] =
					Api\Mail\EWS\Lib::getFoldersSelOptions( $content['acc_id'], true );
			}
			catch(Exception $e) {
				if (self::$debug) _egw_log_exception($e);
				// let user know what the problem is and that he can fix it using wizard or deleting
				$msg = lang($e->getMessage())."\n\n".lang('You can use wizard to fix account settings or delete account.');
				$msg_type = 'error';
			}
		}
		else
		{
			try {
				$sel_options['acc_folder_sent'] = $sel_options['acc_folder_trash'] =
					$sel_options['acc_folder_draft'] = $sel_options['acc_folder_template'] =
					$sel_options['acc_folder_junk'] = $sel_options['acc_folder_archive'] =
					$sel_options['notify_folders'] = $sel_options['acc_folder_ham'] =
					self::mailboxes(self::imap_client ($content));
			}
			catch(Exception $e) {
				if (self::$debug) _egw_log_exception($e);
				// let user know what the problem is and that he can fix it using wizard or deleting
				$msg = lang($e->getMessage())."\n\n".lang('You can use wizard to fix account settings or delete account.');
				$msg_type = 'error';
				// cant connection to imap --> allow free entries in taglists
				foreach(array('acc_folder_sent', 'acc_folder_trash', 'acc_folder_draft', 'acc_folder_template', 'acc_folder_junk') as $folder)
				{
					$tpl->setElementAttribute($folder, 'allowFreeEntries', true);
				}
			}
		}

		$sel_options['acc_imap_type'] = Mail\Types::getIMAPServerTypes(false);
		$sel_options['acc_smtp_type'] = Mail\Types::getSMTPServerTypes(false);
		$sel_options['acc_imap_logintype'] = self::$login_types;
		$sel_options['ident_id'] = $content['identities'];
		$sel_options['acc_id'] = $content['accounts'];
		$sel_options['acc_further_identities'] = self::$further_identities;
		$sel_options['acc_ews_type'] = array(
			'inbox' => 'Inbox',
			'public_folders' => 'Public Folders'
		);

		// user is allowed to create or edit further identities
		if ($edit_access || $content['acc_further_identities'])
		{
			$sel_options['ident_id']['new'] = lang('Create new identity');
			$readonlys['ident_id'] = false;

			// if no edit-access and identity is not standard identity --> allow to edit identity
			if (!$edit_access && $content['ident_id'] != $content['std_ident_id'])
			{
				$readonlys += array(
					'button[save]' => false, 'button[apply]' => false,
					'button[placeholders]' => false,
					'ident_name' => false,
					'ident_realname' => false, 'ident_email' => false, 'ident_email_alias' => false,
					'ident_org' => false, 'ident_signature' => false,
				);
			}
			if ($content['ident_id'] != $content['old_ident_id'] &&
				($content['old_ident_id'] || $content['ident_id'] != $content['std_ident_id']))
			{
				if ($content['ident_id'] > 0)
				{
					$identity = Mail\Account::read_identity($content['ident_id'], false, $content['called_for']);
					unset($identity['account_id']);
					$content = array_merge($content, $identity, array('ident_email_alias' => $identity['ident_email']));
				}
				else
				{
					$content['ident_name'] = $content['ident_realname'] = $content['ident_email'] =
						$content['ident_email_alias'] = $content['ident_org'] = $content['ident_signature'] = '';
				}
				if (empty($msg) && $edit_access && $content['ident_id'] && $content['ident_id'] != $content['std_ident_id'])
				{
					$msg = lang('Switch back to standard identity to save other account data.');
					$msg_type = 'help';
				}
				$content['old_ident_id'] = $content['ident_id'];
			}
		}
		$content['old_acc_id'] = $content['acc_id'];

		// if only aliases are allowed for futher identities, add them as options
		// allow admins to always add arbitrary aliases
		if ($content['acc_further_identities'] == 2 && !$this->is_admin)
		{
			$sel_options['ident_email_alias'] = array_merge(
				array('' => $content['mailLocalAddress'].' ('.lang('Default').')'),
				array_combine($content['mailAlternateAddress'], $content['mailAlternateAddress']));
			// if admin explicitly set a non-alias, we need to add it to aliases to keep it after storing signature by user
			if ($content['ident_email'] !== $content['mailLocalAddress'] && !isset($sel_options['ident_email_alias'][$content['ident_email']]))
			{
				$sel_options['ident_email_alias'][$content['ident_email']] = $content['ident_email'];
			}
			// copy ident_email to select-box ident_email_alias, as et2 requires unique ids
			$content['ident_email_alias'] = $content['ident_email'];
			$content['select_ident_mail'] = true;
		}

		// only allow to delete further identities, not a standard identity
		$readonlys['button[delete_identity]'] = !($content['ident_id'] > 0 && $content['ident_id'] != $content['std_ident_id']);

		// disable aliases tab for default smtp class EGroupware\Api\Mail\Smtp
		$readonlys['tabs']['admin.mailaccount.aliases'] = !$content['acc_smtp_type'] ||
			$content['acc_smtp_type'] == 'EGroupware\\Api\\Mail\\Smtp';
		if ($readonlys['tabs']['admin.mailaccount.aliases'])
		{
			unset($sel_options['acc_further_identities'][2]);	// can limit identities to aliases without aliases ;-)
		}

		// allow smtp class to disable certain features in alias tab
		if ($content['acc_smtp_type'] && class_exists($content['acc_smtp_type']) &&
			is_a($content['acc_smtp_type'], 'EGroupware\\Api\\Mail\\Smtp\\Ldap', true))
		{
			$content['no_forward_available'] = !constant($content['acc_smtp_type'].'::FORWARD_ATTR');
			if (!constant($content['acc_smtp_type'].'::FORWARD_ONLY_ATTR'))
			{
				$readonlys['deliveryMode'] = true;
			}
		}

		// Disable EWS tab for other types
		if ( $content['acc_imap_type'] && !Mail\Account::is_ews_type( $content['acc_imap_type'] ) ) 
		{
			$readonlys['tabs']['admin.mailaccount.ews'] = true;
		}

		// account allows users to change forwards
		if (!$edit_access && !$readonlys['tabs']['admin.mailaccount.aliases'] && $content['acc_user_forward'])
		{
			$readonlys['mailForwardingAddress'] = false;
		}

		// allow imap classes to disable certain tabs or fields
		if (($class = Mail\Account::getIcClass($content['acc_imap_type'])) && class_exists($class) &&
			($imap_ro = call_user_func(array($class, 'getUIreadonlys'))))
		{
			$readonlys = array_merge($readonlys, $imap_ro, array(
				'tabs' => array_merge((array)$readonlys['tabs'], (array)$imap_ro['tabs']),
			));
		}
		Framework::message($msg ? $msg : (string)$_GET['msg'], $msg_type);

		if (count($content['account_id']) > 1)
		{
			$tpl->setElementAttribute('account_id', 'multiple', true);
			$readonlys['button[multiple]'] = true;
		}
		// when called by admin for existing accounts, display further administrative actions
		if ($content['called_for'] && $content['acc_id'] > 0)
		{
			$admin_actions = array();
			foreach(Api\Hooks::process(array(
				'location' => 'emailadmin_edit',
				'account_id' => $content['called_for'],
				'acc_id' => $content['acc_id'],
			)) as $actions)
			{
				if ($actions) $admin_actions = array_merge($admin_actions, $actions);
			}
			if ($admin_actions) $tpl->setElementAttribute('admin_actions', 'actions', $admin_actions);
		}
		$content['admin_actions'] = (bool)$admin_actions;

		if ( $content['acc_imap_type'] &&  Mail\Account::is_ews_type( $content['acc_imap_type'] ) ) {
			try {
				$content['acc_ews_apply_permissions'] = (int) $content['acc_ews_apply_permissions'];
			}
			catch(Exception $e) {
				if (self::$debug) _egw_log_exception($e);
				// let user know what the problem is and that he can fix it using wizard or deleting
				$msg = lang($e->getMessage())."\n\n".lang('You can use wizard to fix account settings or delete account.');
				$msg_type = 'error';
			}
		}

		//try to fix identities with no domain part set e.g. alias as identity
		if (!strpos($content['ident_email'], '@'))
		{
			$content['ident_email'] = Mail::fixInvalidAliasAddress (Api\Accounts::id2name($content['acc_imap_account_id'], 'account_email'), $content['ident_email']);
		}

		$tpl->exec(static::APP_CLASS.'edit', $content, $sel_options, $readonlys, $content, 2);
	}

	public function ews_custom_permissions( $content = array() ) 
	{
		$dtmpl = new Etemplate('admin.mailaccount.permissions');
		$acc_id = $_GET['acc_id']? $_GET['acc_id']: $content['acc_id'];
		$content['acc_id'] = $acc_id;

		$sel_options['ews_permissions'] = Api\Mail_EWS::getFolderPermissionsSelOptions( $content['acc_id'] );

		if ( $content['save'] || $content['apply'] ) {
			$res = Api\Mail_EWS::storeFolderPermissions( $content['ews_permissions'], $content['acc_id'] );
			$msg = lang('Operation Successful');
			if ( $res && $content['save'] ) {
				Framework::message( $msg );
				Framework::window_close();
			}
			$content['msg'] = $msg;
		}

		$content['ews_permissions'] = Api\Mail_EWS::getFolderPermissions( $content['acc_id'] );
		$readonlys = array();
		$dtmpl->exec('admin.admin_mail.ews_custom_permissions', $content,$sel_options,$readonlys,$content,2);
	}

	/**
	 * Replace 0 with '' or back
	 *
	 * @param string|array &$account_id on return always array
	 * @param boolean $back =false
	 */
	private static function fix_account_id_0(&$account_id=null, $back=false)
	{
		if (!isset($account_id)) return;

		if (!is_array($account_id))
		{
			$account_id = explode(',', $account_id);
		}
		if (($k = array_search($back?'':'0', $account_id)) !== false)
		{
			$account_id[$k] = $back ? '0' : '';
		}
	}

	/**
	 * Instanciate imap-client
	 *
	 * @param array $content
	 * @param int $timeout =null default use value returned by Mail\Imap::getTimeOut()
	 * @return Horde_Imap_Client_Socket
	 */
	protected static function imap_client(array $content, $timeout=null)
	{
		//EWS: Instantiate different object
		if ( Mail\Account::is_ews_type( $content['acc_imap_type'] ) ) {
			$class = $content['acc_imap_type'];
			return new $class($content);
		}
		else {
			return new Horde_Imap_Client_Socket(array(
				'username' => $content['acc_imap_username'],
				'password' => $content['acc_imap_password'],
				'hostspec' => $content['acc_imap_host'],
				'port' => $content['acc_imap_port'],
				'secure' => self::$ssl2secure[(string)array_search($content['acc_imap_ssl'], self::$ssl2type)],
				'timeout' => $timeout > 0 ? $timeout : Mail\Imap::getTimeOut(),
				'debug' => self::DEBUG_LOG,
			));
		}
	}

	/**
	 * Reorder SSL types to make sure we start with TLS, SSL, STARTTLS and insecure last
	 *
	 * @param array $data ssl => port pairs plus other data like value for 'username'
	 * @return array
	 */
	protected static function fix_ssl_order($data)
	{
		$ordered = array();
		foreach(array_merge(array('TLS', 'SSL', 'STARTTLS'), array_keys($data)) as $key)
		{
			if (array_key_exists($key, $data)) $ordered[$key] = $data[$key];
		}
		return $ordered;
	}

	/**
	 * Query Mozilla's ISPDB
	 *
	 * Some providers eg. 1-and-1 do not report their hosted domains to ISPDB,
	 * therefore we try it with the found MX and it's domain-part (host-name removed).
	 *
	 * @param string $domain domain or email
	 * @param boolean $try_mx =true if domain itself is not found, try mx or domain-part (host removed) of mx
	 * @return array with values for keys 'displayName', 'imap', 'smtp', 'pop3', which each contain
	 *	array of arrays with values for keys 'hostname', 'port', 'socketType'=(SSL|STARTTLS), 'username'=%EMAILADDRESS%
	 */
	protected static function mozilla_ispdb($domain, $try_mx=true)
	{
		if (strpos($domain, '@') !== false) list(,$domain) = explode('@', $domain);

		$url = 'https://autoconfig.thunderbird.net/v1.1/'.$domain;
		try {
			$xml = @simplexml_load_file($url);
			if (!$xml->emailProvider) throw new Api\Exception\NotFound();
			$provider = array(
				'displayName' => (string)$xml->emailProvider->displayName,
			);
			foreach($xml->emailProvider->children() as $tag => $server)
			{
				if (!in_array($tag, array('incomingServer', 'outgoingServer'))) continue;
				foreach($server->attributes() as $name => $value)
				{
					if ($name == 'type') $type = (string)$value;
				}
				$data = array();
				foreach($server as $name => $value)
				{
					foreach($value->children() as $tag => $val)
					{
						$data[$name][$tag] = (string)$val;
					}
					if (!isset($data[$name])) $data[$name] = (string)$value;
				}
				$provider[$type][] = $data;
			}
		}
		catch(Exception $e) {
			// ignore own not-found exception or xml parsing execptions
			unset($e);

			if ($try_mx && ($dns = dns_get_record($domain, DNS_MX)))
			{
				$domain = $dns[0]['target'];
				if (!($provider = self::mozilla_ispdb($domain, false)))
				{
					list(,$domain) = explode('.', $domain, 2);
					$provider = self::mozilla_ispdb($domain, false);
				}
			}
			else
			{
				$provider = array();
			}
		}
		//error_log(__METHOD__."('$email') returning ".array2string($provider));
		return $provider;
	}

	/**
	 * Guess possible server hostnames from email address:
	 *	- $type.$domain, mail.$domain
	 *  - replace host in MX with imap or mail
	 *  - MX for $domain
	 *
	 * @param string $email email address
	 * @param string $type ='imap' 'imap' or 'smtp', used as hostname beside 'mail'
	 * @return array of hostname => true pairs
	 */
	protected function guess_hosts($email, $type='imap')
	{
		list(,$domain) = explode('@', $email);

		$hosts = array();

		// try usuall names
		$hosts[$type.'.'.$domain] = true;
		$hosts['mail.'.$domain] = true;
		if ($type == 'smtp') $hosts['send.'.$domain] = true;

		if (($dns = dns_get_record($domain, DNS_MX)))
		{
			//error_log(__METHOD__."('$email') dns_get_record('$domain', DNS_MX) returned ".array2string($dns));
			// hosts for office365 are outlook|smpt.office365.com for MX *.mail.protection.outlook.com
			if (substr($dns[0]['target'], -28) == '.mail.protection.outlook.com')
			{
				$hosts[($type == 'imap' ? 'outlook' : 'smtp').'.office365.com'] = true;
			}
			$hosts[preg_replace('/^[^.]+/', $type, $dns[0]['target'])] = true;
			$hosts[preg_replace('/^[^.]+/', 'mail', $dns[0]['target'])] = true;
			if ($type == 'smtp') $hosts[preg_replace('/^[^.]+/', 'send', $dns[0]['target'])] = true;
			$hosts[$dns[0]['target']] = true;
		}

		// verify hosts in dns
		foreach(array_keys($hosts) as $host)
		{
			if (!dns_get_record($host, DNS_A)) unset($hosts[$host]);
		}
		//error_log(__METHOD__."('$email') returning ".array2string($hosts));
		return $hosts;
	}

	/**
	 * Set mail account status wheter to 'active' or '' (inactive)
	 *
	 * @param array $_data account an array of data called via long task running dialog
	 *	$_data:array (
	 *		id => account_id,
	 *		qouta => quotaLimit,
	 *		domain => mailLocalAddress,
	 *		status => mail activation status('active'|'')
	 *	)
	 * @return json response
	 */
	public function ajax_activeAccounts($_data)
	{
		if (!$this->is_admin) die('no rights to be here!');
		$response = Api\Json\Response::get();
		if (($account = $GLOBALS['egw']->accounts->read($_data['id'])))
		{
			if ($_data['quota'] !== '' || $_data['accountStatus'] !== ''
				|| strpos($_data['domain'], '.'))
			{
				$emailadmin = Mail\Account::get_default();
				if (!Mail\Account::is_multiple($emailadmin))
				{
					$msg = lang('No default account found!');
					return $response->data($msg);
				}

				$ea_account = Mail\Account::read($emailadmin->acc_id, $_data['id']);
				if (($userData = $ea_account->getUserData ()))
				{
					$userData = array(
						'acc_smtp_type' => $ea_account->acc_smtp_type,
						'accountStatus' => $_data['status'],
						'quotaLimit' => $_data['qouta']? $_data['qouta']: $userData['qoutaLimit'],
						'mailLocalAddress' => $userData['mailLocalAddress']
					);

					if (strpos($_data['domain'], '.') !== false)
					{
						$userData['mailLocalAddress'] = preg_replace('/@'.preg_quote($ea_account->acc_domain).'$/', '@'.$_data['domain'], $userData['mailLocalAddress']);

						foreach($userData['mailAlternateAddress'] as &$alias)
						{
							$alias = preg_replace('/@'.preg_quote($ea_account->acc_domain).'$/', '@'.$_data['domain'], $alias);
						}
					}
					// fullfill the saveUserData requirements
					$userData += $ea_account->params;
					$ea_account->saveUserData($_data['id'], $userData);
					$msg = '#'.$_data['id'].' '.$account['account_fullname']. ' '.($userData['accountStatus'] == 'active'? lang('activated'):lang('deactivated'));
				}
				else
				{
					$msg .= lang('No profile defined for user %1', '#'.$_data['id'].' '.$account['account_fullname']."\n");

				}
			}
		}
		$response->data($msg);
	}
}

/**
 * Trivial file logger, as Horde\ManageSieve does not support just a file
 */
class admin_mail_logger
{
	private $fp;

	public function __construct($log)
	{
		$this->fp = is_resource($log) ? $log : fopen($log, 'a');
	}

	public function debug($msg)
	{
		fwrite($this->fp, $msg."\n");
	}
}
