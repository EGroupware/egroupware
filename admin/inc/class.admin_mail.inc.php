<?php
/**
 * EGroupware EMailAdmin: Wizard to create mail accounts
 *
 * @link http://www.egroupware.org
 * @package emailadmin
 * @author Ralf Becker <rb@egroupware.org>
 * @copyright (c) 2013-18 by Ralf Becker <rb@egroupware.org>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

use EGroupware\Api;
use EGroupware\Api\Framework;
use EGroupware\Api\Acl;
use EGroupware\Api\Etemplate;
use EGroupware\Api\Mail;
use EGroupware\Api\Auth\OpenIDConnectClient;
use Jumbojett\OpenIDConnectClientException;

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
	const DEBUG_LOG = null; //'/var/lib/egroupware/imap.log';
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
	 * 4: JMAP over plain http (no encryption)
	 */
	const JMAP_HTTP = Mail\Account::JMAP_HTTP;
	/**
	 * 6: JMAP over https
	 */
	const JMAP_HTTPS = Mail\Account::JMAP_HTTPS;
	/**
	 * 8: if set, verify certifcate - kept for backwards compatibility, see VERIFY_ENABLED
	 */
	const SSL_VERIFY = Mail\Account::SSL_VERIFY;
	/**
	 * Mask for the protocol/encryption portion (bits 0-2) of acc_(imap|sieve|smtp)_ssl
	 */
	const PROTOCOL_MASK = Mail\Account::PROTOCOL_MASK;
	/**
	 * 3-state certificate-verification field (bits 3-4) - see Mail\Account's docblock for the
	 * full design (undecided/enabled/disabled, safe one-time transition for existing accounts)
	 */
	const VERIFY_UNDECIDED = Mail\Account::VERIFY_UNDECIDED;
	const VERIFY_ENABLED = Mail\Account::VERIFY_ENABLED;
	const VERIFY_DISABLED = Mail\Account::VERIFY_DISABLED;
	const VERIFY_MASK = Mail\Account::VERIFY_MASK;

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
		'ajax_activeAccounts' => true
	);

	/**
	 * Supported ssl types including none
	 *
	 * Kept for backwards compatibility (eg. default-value lookups); values are bare protocol
	 * values (PROTOCOL_MASK only) - certificate verification is a separate checkbox now, NOT
	 * baked into this dropdown's value space (that caused every option to visually appear up to
	 * 3 times, once per verification state, found live 2026-08-24). Use self::sslTypes() to build
	 * the actual selectbox options, which also gives each field (IMAP/Sieve/SMTP) its own labels
	 * and, for SMTP, omits the JMAP entries entirely (not supported there yet).
	 *
	 * @var array
	 */
	public static $ssl_types = array(
		self::JMAP_HTTPS => 'JMAP (https)',
		self::SSL_TLS => 'TLS/SSL',	// SSL with minimum TLS (no SSL v.2 or v.3), requires Horde_Imap_Client-2.16.0/Horde_Socket_Client-1.1.0
		self::SSL_SSL => 'SSL',	// deprecated legacy alias for TLS, kept only for internal trial-loop label lookups, never shown in a selectbox (normalizeAccountType() rewrites it to SSL_TLS on every save)
		self::SSL_STARTTLS => 'STARTTLS',
		self::JMAP_HTTP => 'JMAP (http, no encryption)',
		'no' => 'no encryption',
	);

	/**
	 * Build the protocol/encryption selectbox options for one field
	 *
	 * @param string $protocol_name eg. 'IMAP', 'Sieve', 'SMTP' - substituted into the non-JMAP labels
	 * @param bool $with_jmap =true include the JMAP (https)/JMAP (http) entries - false for SMTP,
	 *      which has no JMAP transport (yet), and for a classic (non-JMAP) Sieve account, where
	 *      JMAP is not a meaningful manual choice (JMAP Sieve is always tied to the account's own
	 *      JMAP session, never an independently configured host/port)
	 * @return array value (int|'no') => label, in the order: JMAP (https), TLS/SSL, StartTLS,
	 *      JMAP (http, no encryption), no encryption
	 */
	public static function sslTypes(string $protocol_name, bool $with_jmap=true) : array
	{
		$types = [];
		if ($with_jmap) $types[self::JMAP_HTTPS] = lang('JMAP (https)');
		$types[self::SSL_TLS] = lang('%1 (TLS/SSL)', $protocol_name);
		$types[self::SSL_STARTTLS] = lang('%1 (StartTLS)', $protocol_name);
		if ($with_jmap) $types[self::JMAP_HTTP] = lang('JMAP (http, no encryption)');
		$types['no'] = lang('%1 (no encryption)', $protocol_name);
		return $types;
	}

	/**
	 * Merge a submitted "disable certificate validation" checkbox back into the combined
	 * acc_(imap|sieve|smtp)_ssl value, before any other code in this request reads that field
	 *
	 * The checkbox is a synthetic UI-only field (acc_X_ssl_noverify), not a real DB column - it
	 * exists only so the protocol dropdown does not have to carry the certificate-verification
	 * state baked into its own value space (that caused every option to visually appear up to 3
	 * times, once per verification state, found live 2026-08-24).
	 *
	 * Checked --> VERIFY_DISABLED (skip verification). Unchecked --> VERIFY_UNDECIDED, so the
	 * connection-test code below decides ENABLED/DISABLED itself from the actual probe outcome -
	 * a user cannot manually claim "verified" without EGroupware itself having confirmed it.
	 *
	 * @param array $content
	 * @param string $field eg. 'acc_imap_ssl', 'acc_sieve_ssl', 'acc_smtp_ssl'
	 * @return array $content with $field updated and $field.'_noverify' removed
	 */
	protected static function mergeVerifyCheckbox(array $content, string $field) : array
	{
		if (isset($content[$field.'_noverify']))
		{
			$noverify = (bool)$content[$field.'_noverify'];
			if (isset($content[$field]) && $content[$field] !== 'no')
			{
				$content[$field] = ((int)$content[$field] & self::PROTOCOL_MASK) |
					($noverify ? self::VERIFY_DISABLED : self::VERIFY_UNDECIDED);
			}
		}
		unset($content[$field.'_noverify']);
		return $content;
	}

	/**
	 * Split the combined acc_(imap|sieve|smtp)_ssl value into a bare-protocol dropdown value
	 * plus a "disable certificate validation" checkbox boolean, for display
	 *
	 * Counterpart of self::mergeVerifyCheckbox() - call right before rendering (each
	 * $tpl->exec() call), after all connection-test logic has finished updating $field.
	 *
	 * @param array $content
	 * @param string $field eg. 'acc_imap_ssl', 'acc_sieve_ssl', 'acc_smtp_ssl'
	 * @return array $content with $field masked to a bare protocol value and $field.'_noverify' set
	 */
	protected static function splitVerifyCheckbox(array $content, string $field) : array
	{
		$ssl = $content[$field] ?? null;
		if ($ssl !== null && $ssl !== 'no')
		{
			$content[$field.'_noverify'] = ((int)$ssl & self::VERIFY_MASK) === self::VERIFY_DISABLED;
			$protocol = (int)$ssl & self::PROTOCOL_MASK;
			// legacy SSL_SSL is displayed identically to SSL_TLS, never written again; SSL_NONE
			// is represented by the string 'no' throughout this class, not the int 0, matching
			// sslTypes()'s option key - an int 0 would not match any selectbox option and show blank
			$content[$field] = $protocol === self::SSL_SSL ? self::SSL_TLS :
				($protocol === self::SSL_NONE ? 'no' : $protocol);
		}
		else
		{
			$content[$field.'_noverify'] = false;
		}
		return $content;
	}

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
		'JMAP (https)' => self::JMAP_HTTPS,
		'JMAP (http)' => self::JMAP_HTTP,
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
		'domain/username' => 'Exchange: domain/username',
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
	 * Used to switch Sieve off by default, thought users can always try switching it on.
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
	 * @param string $msg
	 */
	public function add(array $content=array(), $msg='', $msg_type='success')
	{
		$tpl = new Etemplate('admin.mailwizard');
		if (empty($content['account_id']))
		{
			$content['account_id'] = $GLOBALS['egw_info']['user']['account_id'];
		}
		// add some defaults if not already set (+= does not overwrite existing values!)
		$content += array(
			'ident_realname' => $GLOBALS['egw']->accounts->id2name($content['account_id'], 'account_fullname'),
			'ident_email' => $GLOBALS['egw']->accounts->id2name($content['account_id'], 'account_email'),
			// explicit default protocol, so the pre-selected dropdown value (TLS/SSL) always
			// matches the default port below - the dropdown itself lists JMAP (https) first
			// (see self::sslTypes()), but that is a display-order choice, not the default pick
			'acc_imap_ssl' => self::SSL_TLS,
			'acc_imap_port' => 993,
			'manual_class' => 'emailadmin_manual',
		);
		Framework::message($msg ? $msg : (string)$_GET['msg'], $msg_type);

		if (!empty($content['acc_imap_host']) || !empty($content['acc_imap_username']))
		{
			$readonlys['button[manual]'] = true;
			unset($content['manual_class']);
		}
		$content = self::splitVerifyCheckbox($content, 'acc_imap_ssl');
		$tpl->exec(static::APP_CLASS.'autoconfig', $content, array(
			'acc_imap_ssl' => self::sslTypes('IMAP'),
		), $readonlys, $content, 2);
	}

	/**
	 * Try to autoconfig an account
	 *
	 * @param array $content
	 */
	public function autoconfig(array $content)
	{
		$content = self::mergeVerifyCheckbox($content, 'acc_imap_ssl');

		// user pressed [Skip IMAP] --> jump to SMTP config
		if (!empty($content['button']) && key($content['button']) === 'skip_imap')
		{
			unset($content['button']);
			if (!isset($content['acc_smtp_host'])) $content['acc_smtp_host'] = '';	// do manual mode right away
			return $this->smtp($content, lang('Skipping IMAP configuration!'));
		}
		$tpl = new Etemplate('admin.mailwizard');
		$sel_options = $readonlys = $hosts = [];

		$connected = $content['connected'] ?? null;
		if (empty($content['acc_imap_username']))
		{
			$content['acc_imap_username'] = $content['ident_email'];
		}
		// supported oauth provider or mail-server of them for custom domains
		if (($oauth = OpenIDConnectClient::providerByDomain($content['acc_imap_username'], $content['acc_imap_host'])))
		{
			$content['output'] .= lang('Using IMAP:%1, SMTP:%2, OAUTH:%3:', $oauth['imap'], $oauth['smtp'], $oauth['provider'])."\n";
			$hosts[$oauth['imap']] = true;
			$content += self::oauth2content($oauth);
		}
		elseif (!empty($content['acc_imap_host']))
		{
			$hosts = array($content['acc_imap_host'] => true);
			if ($content['acc_imap_port'] > 0 && !in_array($content['acc_imap_port'], array(143,993)))
			{
				$ssl_type = (string)array_search((int)$content['acc_imap_ssl'] & self::PROTOCOL_MASK, self::$ssl2type);
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

		// check if support OAuth for that domain or we have a password
		if (empty($oauth) && empty($content['acc_oauth_provider_url']) && empty($content['acc_imap_password']))
		{
			Etemplate::set_validation_error('acc_imap_password', lang('Field must not be empty!'));
			$connected = false;
		}

		// try JMAP first: most JMAP servers also speak IMAP, so this MUST run before the IMAP
		// trial below, or a JMAP-capable server would always get misclassified as IMAP-only.
		if (!isset($connected) && !empty($content['acc_imap_password']) && $this->tryJmap($content))
		{
			$connected = $content['connected'];
		}

		// captured BEFORE the trial loop overwrites acc_imap_ssl with each bare candidate
		// protocol value - a manually pre-checked "disable certificate validation" checkbox
		// must still apply to every candidate tried below
		$initial_verify_state = (int)($content['acc_imap_ssl'] ?? 0) & self::VERIFY_MASK;

		// iterate over all hosts and try to connect
		foreach(!isset($connected) ? $hosts : [] as $host => $data)
		{
			// check if we support OAuth for the (manual) configured mail-server
			if (empty($content['acc_oauth_provider_url']) && ($oauth = OpenIDConnectClient::providerByDomain($content['acc_imap_username'], $host)))
			{
				$content += self::oauth2content($oauth);
			}
			$content['acc_imap_host'] = $host;
			// by default we check SSL, STARTTLS and at last an insecure connection
			if (!is_array($data)) $data = array('TLS' => 993, 'SSL' => 993, 'STARTTLS' => 143, 'insecure' => 143);

			foreach($data as $ssl => $port)
			{
				if ($ssl === 'username') continue;

				$content['acc_imap_ssl'] = (int)self::$ssl2type[$ssl] | $initial_verify_state;

				$e = null;
				try {
					$content['output'] .= "\n".Api\DateTime::to('now', 'H:i:s').": Trying $ssl connection to $host:$port ...\n";
					$content['acc_imap_port'] = $port;

					// optimistic cert verification: an undecided account tries strict
					// verification as part of THIS SAME connection attempt first, falling back
					// to a lenient retry only on an actual certificate failure - no separate
					// probe connection (which risks colliding with a real mail server's per-IP
					// concurrent-connection limits, found live 2026-08-24)
					$verify_undecided = $initial_verify_state === self::VERIFY_UNDECIDED;
					$attempt_verify = $verify_undecided ? true : $initial_verify_state === self::VERIFY_ENABLED;
					try {
						$imap = self::imap_client($content, self::TIMEOUT, $attempt_verify);
						$imap->login();
					}
					catch (Horde_Imap_Client_Exception $cert_e) {
						if (!$verify_undecided || !Mail\Account::isCertificateError($cert_e))
						{
							throw $cert_e;
						}
						$content['output'] .= "\n".lang('Certificate could NOT be verified - retrying without certificate verification.')."\n";
						$attempt_verify = false;
						$imap = self::imap_client($content, self::TIMEOUT, false);
						$imap->login();
					}
					$content['output'] .= "\n".lang('Successful connected to %1 server%2.', 'IMAP', ' '.lang('and logged in'))."\n";
					if (!$imap->isSecureConnection())
					{
						$content['output'] .= lang('Connection is NOT secure! Everyone can read eg. your credentials.')."\n";
						$content['acc_imap_ssl'] = 'no';
					}
					elseif ($verify_undecided)
					{
						$content['acc_imap_ssl'] = ((int)$content['acc_imap_ssl'] & ~self::VERIFY_MASK) |
							($attempt_verify ? self::VERIFY_ENABLED : self::VERIFY_DISABLED);
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
		if ($connected === 'jmap')	// continue with next wizard step: define folders, JMAP-natively
		{
			unset($content['button']);
			return $this->folder($content, lang('Successful connected to %1 server%2.', 'JMAP', ' '.lang('and logged in')));
		}
		if ($connected)	// continue with next wizard step: define folders
		{
			unset($content['button']);
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
		unset($content['manual_class'], $content['button']);
		$content = self::splitVerifyCheckbox($content, 'acc_imap_ssl');
		$sel_options['acc_imap_ssl'] = self::sslTypes('IMAP');
		$tpl->exec(static::APP_CLASS.'autoconfig', $content, $sel_options, $readonlys,
			array_diff_key($content, ['output'=>true]), 2);
	}

	/**
	 * Convert OAuth provider data to our content-names
	 *
	 * @param array $oauth
	 * @return array
	 */
	protected static function oauth2content(array $oauth)
	{
		return [
			'acc_smpt_host' => $oauth['smtp'],
			'acc_sieve_enabled' => false,
			'acc_oauth_provider_url' => $oauth['provider'],
			'acc_oauth_client_id' => $oauth['client'],
			'acc_oauth_client_secret' => $oauth['secret'],
			'acc_oauth_scopes' => $oauth['scopes'],
			OpenIDConnectClient::ADD_CLIENT_TO_WELL_KNOWN => $oauth[OpenIDConnectClient::ADD_CLIENT_TO_WELL_KNOWN] ?? null,
			OpenIDConnectClient::ADD_AUTH_PARAM => $oauth[OpenIDConnectClient::ADD_AUTH_PARAM] ?? null,
		];
	}

	/**
	 * Step 2: Folder - let user select trash, sent, drafs and template folder
	 *
	 * @param ?array $content
	 * @param string $msg =''
	 * @param Horde_Imap_Client_Socket $imap =null
	 */
	public function folder(?array $content, $msg='', ?Horde_Imap_Client_Socket $imap=null)
	{
		if (!empty($content['button']))
		{
			$button = key($content['button']);
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

		try {
			//_debug_array($content);
			if (is_a($content['acc_imap_type'] ?? '', Mail\Imap\Jmap::class, true))
			{
				$jmap = static::jmapClient($content['acc_imap_host'], $content['acc_imap_username'], $content['acc_imap_password']);
				$folders = self::jmapMailboxes($jmap, $content);
			}
			else
			{
				if (!isset($imap)) $imap = self::imap_client ($content);
				$folders = self::mailboxes($imap, $content);
			}
			$sel_options['acc_folder_sent'] = $sel_options['acc_folder_trash'] =
				$sel_options['acc_folder_draft'] = $sel_options['acc_folder_template'] =
					$sel_options['acc_folder_junk'] = $sel_options['acc_folder_archive'] =
						$sel_options['acc_folder_ham'] = $folders;
		}
		catch(Exception $e) {
			$content['msg'] = $e->getMessage();
			if (self::$debug) _egw_log_exception($e);
		}

		$tpl = new Etemplate('admin.mailwizard.folder');
		$tpl->exec(static::APP_CLASS.'folder', $content, $sel_options, array(), $content, 2);
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
			unset($content[$name]);
			// first check special-use attributes
			if (($special_use = array_shift($common_names)))
			{
				foreach((array)$attributes[$special_use] as $mailbox)
				{
					if (empty($content[$name]) || is_string($mailbox) && strlen($mailbox) < strlen($content[$name]))
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
						(empty($content[$name]) || is_string($mailbox) && strlen($mailbox) < strlen($content[$name]) && substr($content[$name], 0, 6) != 'INBOX'.$delimiter))
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
	 * Query JMAP mailboxes and detect special folders - JMAP-native equivalent of mailboxes()
	 *
	 * Special-use folders are matched via the standard JMAP Mailbox "role" (RFC 8621) where one
	 * exists (sent/trash/drafts/junk/archive); "template" and "ham" have no standard role and are
	 * matched by common name only, same as mailboxes()'s IMAP fallback.
	 *
	 * @param Mail\Jmap $jmap
	 * @param array &$content=null on return values for acc_folder_(sent|trash|draft|template|junk|ham|archive)
	 * @return array with mailbox-names as key AND value
	 */
	protected static function jmapMailboxes(Mail\Jmap $jmap, array &$content=null)
	{
		$response = $jmap->jmapCall([['Mailbox/get', ['accountId' => $jmap->accountId, 'ids' => null], '0']], Mail\Jmap::JMAP_MAIL);
		$mailboxes = $response['methodResponses'][0][1]['list'] ?? [];

		// pre-select send, trash, ... folder for user, by checking the JMAP role or common name(s)
		foreach(array(
			'acc_folder_sent'     => array('sent'),
			'acc_folder_trash'    => array('trash'),
			'acc_folder_draft'    => array('drafts'),
			'acc_folder_template' => array('', 'templates'),
			'acc_folder_junk'     => array('junk'),
			'acc_folder_ham'      => array('', 'ham'),
			'acc_folder_archive'  => array('archive'),
		) as $name => $matches)
		{
			unset($content[$name]);
			list($role, $common_name) = $matches + [null, null];
			foreach($mailboxes as $mailbox)
			{
				if (empty($content[$name]) &&
					(($role && ($mailbox['role'] ?? null) === $role) ||
					 ($common_name && strtolower($mailbox['name']) === $common_name)))
				{
					$content[$name] = $mailbox['name'];
				}
			}
		}
		return array_combine(array_column($mailboxes, 'name'), array_column($mailboxes, 'name'));
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
		$content = self::mergeVerifyCheckbox($content, 'acc_sieve_ssl');
		$is_jmap = is_a($content['acc_imap_type'] ?? '', Mail\Imap\Jmap::class, true);

		if (!empty($content['button']))
		{
			$button = key($content['button']);
			unset($content['button']);
			switch($button)
			{
				case 'back':
					return $this->folder($content);

				case 'continue':
					// JMAP: nothing to test, capability was already established in autoconfig()
					// (kept in $content, not unset - needed again if the user steps back here
					// from smtp(), found live 2026-08-24: stepping back re-ran this JMAP branch
					// with the capability gone, showing a false "Sieve not supported")
					if ($is_jmap)
					{
						return $this->smtp($content);
					}
					if (!$content['acc_sieve_enabled'])
					{
						return $this->smtp($content);
					}
					break;
			}
		}

		// JMAP accounts: Sieve support/config comes from the JMAP session's capabilities
		// (fetched during autoconfig()), not from a separate ManageSieve probe - Mail\Sieve\Jmap
		// composes off the same JMAP connection at usage time, there is no separate host/port.
		// Still rendered (not skipped) so the user sees the detection result and can turn it off
		// if unwanted - it can never be turned ON if the capability wasn't detected.
		if ($is_jmap)
		{
			$detected = isset($content['_jmap_account_capabilities']['urn:ietf:params:jmap:sieve']);
			$content['acc_sieve_enabled'] = $detected &&
				(!isset($content['acc_sieve_enabled']) || $content['acc_sieve_enabled']) ? 1 : 0;
			$content['acc_sieve_host'] = $content['acc_imap_host'];
			$content['acc_sieve_port'] = $content['acc_imap_port'];
			$content['acc_sieve_ssl'] = $content['acc_imap_ssl'];
			$readonlys['acc_sieve_host'] = $readonlys['acc_sieve_port'] = $readonlys['acc_sieve_ssl'] =
				$readonlys['acc_sieve_ssl_noverify'] = true;
			$readonlys['button[manual]'] = true;
			unset($content['manual_class']);
			if (empty($content['msg']))
			{
				$content['msg'] = $detected ? lang('Sieve filters are supported via JMAP.') :
					lang('This JMAP server does not support Sieve filters.');
			}

			$content = self::splitVerifyCheckbox($content, 'acc_sieve_ssl');
			$sel_options['acc_sieve_ssl'] = self::sslTypes('Sieve');
			$tpl = new Etemplate('admin.mailwizard.sieve');
			$tpl->exec(static::APP_CLASS.'sieve', $content, $sel_options, $readonlys, $content, 2);
			return;
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
		if (!isset($content['acc_sieve_ssl'])) $content['acc_sieve_ssl'] = self::SSL_TLS;
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
			// captured BEFORE the trial loop overwrites acc_sieve_ssl with each bare candidate
			// protocol value - a manually pre-checked "disable certificate validation" checkbox
			// must still apply to every candidate tried below
			$verify_undecided = ((int)$content['acc_sieve_ssl'] & self::VERIFY_MASK) === self::VERIFY_UNDECIDED;
			$decided_verify_enabled = ((int)$content['acc_sieve_ssl'] & self::VERIFY_MASK) === self::VERIFY_ENABLED;
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
						// optimistic cert verification: an undecided account tries strict
						// verification as part of THIS SAME connection attempt first, falling
						// back to a lenient retry only on an actual certificate failure - no
						// separate probe connection (which risks colliding with a real mail
						// server's per-IP concurrent-connection limits, found live 2026-08-24)
						$attempt_verify = $verify_undecided ? true : $decided_verify_enabled;
						$sieve_config = array(
							'host' => $content['acc_sieve_host'],
							'port' => $content['acc_sieve_port'],
							'secure' => self::$ssl2secure[(string)array_search((int)$content['acc_sieve_ssl'] & self::PROTOCOL_MASK, self::$ssl2type)],
							'context' => ['ssl' => ['verify_peer' => $attempt_verify, 'verify_peer_name' => $attempt_verify]],
							'timeout' => self::TIMEOUT,
							'logger' => self::DEBUG_LOG ? new admin_mail_logger(self::DEBUG_LOG) : null,
						);
						try {
							$sieve = new Horde\ManageSieve($sieve_config);
							// connect to sieve server
							$sieve->connect();
						}
						catch (Exception $cert_e) {
							if (!$verify_undecided || !Mail\Account::isCertificateError($cert_e))
							{
								throw $cert_e;
							}
							$content['sieve_output'] .= "\n".lang('Certificate could NOT be verified - retrying without certificate verification.')."\n";
							$attempt_verify = false;
							$sieve_config['context'] = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]];
							$sieve = new Horde\ManageSieve($sieve_config);
							$sieve->connect();
						}
						$content['sieve_output'] .= "\n".lang('Successful connected to %1 server%2.', 'Sieve','');
						// and log in
						$sieve->login($content['acc_imap_username'], $content['acc_imap_password']);
						$content['sieve_output'] .= ' '.lang('and logged in')."\n";
						$content['sieve_connected'] = true;

						// record the (newly resolved, or already pre-decided eg. via the
						// "disable certificate validation" checkbox) verification state - the
						// trial loop above overwrote acc_sieve_ssl with a bare candidate value
						$content['acc_sieve_ssl'] = ((int)$content['acc_sieve_ssl'] & ~self::VERIFY_MASK) |
							($verify_undecided ?
								($attempt_verify ? self::VERIFY_ENABLED : self::VERIFY_DISABLED) :
								($decided_verify_enabled ? self::VERIFY_ENABLED : self::VERIFY_DISABLED));

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
				$content['acc_sieve_ssl'] = self::SSL_TLS;
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
		$content = self::splitVerifyCheckbox($content, 'acc_sieve_ssl');
		$sel_options['acc_sieve_ssl'] = self::sslTypes('Sieve', false);
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
		$content = self::mergeVerifyCheckbox($content, 'acc_smtp_ssl');

		if (!empty($content['button']))
		{
			$button = key($content['button']);
			unset($content['button']);
			switch($button)
			{
				case 'back':
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
		if (!isset($content['acc_smtp_ssl'])) $content['acc_smtp_ssl'] = self::SSL_TLS;
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
					$ssl_type = (string)array_search((int)$content['acc_smtp_ssl'] & self::PROTOCOL_MASK, self::$ssl2type);
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
			// captured BEFORE the trial loop overwrites acc_smtp_ssl with each bare candidate
			// protocol value - a manually pre-checked "disable certificate validation" checkbox
			// must still apply to every candidate tried below
			$initial_verify_state = (int)($content['acc_smtp_ssl'] ?? 0) & self::VERIFY_MASK;

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

					$content['acc_smtp_ssl'] = (int)self::$ssl2type[$ssl] | $initial_verify_state;

					$e = null;
					try {
						$content['smtp_output'] .= "\n".Api\DateTime::to('now', 'H:i:s').": Trying $ssl connection to $host:$port ...\n";
						$content['acc_smtp_port'] = $port;

						// optimistic cert verification: an undecided account tries strict
						// verification as part of THIS SAME connection attempt first, falling
						// back to a lenient retry only on an actual certificate failure - no
						// separate probe connection (which risks colliding with a real mail
						// server's per-IP concurrent-connection limits, found live 2026-08-24)
						$verify_undecided = $initial_verify_state === self::VERIFY_UNDECIDED;
						$attempt_verify = $verify_undecided ? true : $initial_verify_state === self::VERIFY_ENABLED;
						$params = [
							'username' => $content['acc_smtp_username'],
							'password' => $content['acc_smtp_password'],
							'host' => $content['acc_smtp_host'],
							'port' => $content['acc_smtp_port'],
							'secure' => self::$ssl2secure[(string)array_search((int)$content['acc_smtp_ssl'] & self::PROTOCOL_MASK, self::$ssl2type)],
							'context' => ['ssl' => ['verify_peer' => $attempt_verify, 'verify_peer_name' => $attempt_verify]],
							'timeout' => self::TIMEOUT,
							'debug' => self::DEBUG_LOG,
						];
						if (!empty($content['acc_oauth_provider_url']))
						{
							$params['xoauth2_token'] = self::oauthToken($content, true);
						}
						try {
							$mail = new Horde_Mail_Transport_Smtphorde($params);
							// create smtp connection and authenticate, if credentials given
							$smtp = $mail->getSMTPObject();
						}
						catch (Horde_Exception_Wrapped $cert_e) {
							if (!$verify_undecided || !Mail\Account::isCertificateError($cert_e))
							{
								throw $cert_e;
							}
							$content['smtp_output'] .= "\n".lang('Certificate could NOT be verified - retrying without certificate verification.')."\n";
							$attempt_verify = false;
							$params['context'] = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]];
							$mail = new Horde_Mail_Transport_Smtphorde($params);
							$smtp = $mail->getSMTPObject();
						}
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
						else
						{
							// Horde_Smtp always try to use STARTTLS, adjust our ssl-parameter if successful
							if (((int)$content['acc_smtp_ssl'] & self::PROTOCOL_MASK) <= self::SSL_NONE)
							{
								//error_log(__METHOD__."() new Horde_Mail_Transport_Smtphorde(".array2string($params).")->getSMTPObject()->isSecureConnection()=".array2string($smtp->isSecureConnection()));
								$content['acc_smtp_ssl'] = self::SSL_STARTTLS | ((int)$content['acc_smtp_ssl'] & self::VERIFY_MASK);
							}
							// record the (newly resolved, or already pre-decided eg. via the
							// "disable certificate validation" checkbox) verification state
							$content['acc_smtp_ssl'] = ((int)$content['acc_smtp_ssl'] & ~self::VERIFY_MASK) |
								($verify_undecided ?
									($attempt_verify ? self::VERIFY_ENABLED : self::VERIFY_DISABLED) :
									$initial_verify_state);
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
		$content = self::splitVerifyCheckbox($content, 'acc_smtp_ssl');
		$sel_options['acc_smtp_ssl'] = self::sslTypes('SMTP', false);
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
	 * @param ?array $content =null
	 * @param string $msg =''
	 * @param string $msg_type ='success'
	 */
	public function edit(?array $content=null, $msg='', $msg_type='success')
	{
		// app is trying to tell something, while redirecting to wizard
		if (empty($content) && $_GET['acc_id'] && empty($msg) && !empty( $_GET['msg']))
		{
			if (stripos($_GET['msg'],'fatal error:')!==false || $_GET['msg_type'] == 'error') $msg_type = 'error';
			$msg = $_GET['msg'];
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
				if (!empty($content['accounts']))
				{
					$content['acc_id'] = key($content['accounts']);
					//error_log(__METHOD__.__LINE__.'.'.array2string($content['acc_id']));
					// test if the "to be selected" account is imap or not
					if (is_array($content['accounts']) && count($content['accounts'])>1 && Mail\Account::is_multiple($content['acc_id']))
					{
						try {
							$account = Mail\Account::read($content['acc_id'], $content['called_for']);
							//try to select the first account that is of type imap
							if (!$account->is_imap())
							{
								$content['acc_id'] = key($content['accounts']);
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

			if ($content['acc_id'] === 'new')
			{
				$content['account_id'] = $content['called_for'];
				$content['old_acc_id'] = $content['acc_id'];	// to not call add/wizard, if we return from to
				unset($content['tabs']);
				return $this->add($content);
			}
			elseif ($content['acc_id'] > 0)
			{
				try {
					$account = Mail\Account::read($content['acc_id'], $this->is_admin && !empty($content['called_for']) ?
						$content['called_for'] : $GLOBALS['egw_info']['user']['account_id']);
					try {
						$account->getUserData();	// quota, aliases, forwards etc.
					}
					catch (\Throwable $ex) {
						// connection-dependent info (quota/aliases/forwards) is not available if
						// the account can't connect right now (eg. mail server down) - the wizard's
						// whole purpose is letting the user fix that, so it must not abort/close
						// itself over this the way the outer catch below does for a real failure.
						// Deliberately NOT fixed inside getUserData() itself: mail_tree's account
						// enumeration (mail_tree::getAccountsRootNode()) relies on this same kind
						// of failure propagating out of it to render a broken-account error leaf -
						// this needs to stay scoped to just this wizard call site.
						if (self::$debug) _egw_log_exception($ex);
						Framework::message($ex->getMessage(), 'error');
					}
					$content += $account->params;
					foreach(['acc_imap_password', 'acc_smtp_password'] as $n)
					{
						if (isset($content['acc_oauth_username']) && $content[$n] === Mail\Credentials::UNAVAILABLE)
						{
							unset($content[$n]);
						}
					}
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
			$readonlys = self::adminReadonlyFields();
		}
		// ensure correct values for single user mail accounts (we only hide them client-side)
		$is_multiple = Mail\Account::is_multiple($content);
		$content = self::normalizeAccountType($content, $is_multiple);
		$edit_access = Mail\Account::check_access(Acl::EDIT, $content);

		// disable notification save-default and use-default, if only one account or no edit-rights
		$tpl->disableElement('notify_save_default', !$is_multiple || !$edit_access);
		$tpl->disableElement('notify_use_default', !$is_multiple);

		// merge the "disable certificate validation" checkboxes back into their combined
		// acc_(imap|sieve|smtp)_ssl value, before eg. the 'save'/'apply' case below persists it
		foreach (['acc_imap_ssl', 'acc_sieve_ssl', 'acc_smtp_ssl'] as $ssl_field)
		{
			$content = self::mergeVerifyCheckbox($content, $ssl_field);
		}

		if (!empty($content['button']))
		{
			$button = key($content['button']);
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
								try {
									$imap = $account->imapServer(true);
									if ($imap) $imap->checkAdminConnection();
								}
								catch(\Horde_Imap_Client_Exception $e) {
									Api\Json\Response::get()->message(lang('Checking admin credentials failed').': '.$e->getMessage(), 'info');
								}
							}
							// test sieve connection, if not called for other user, enabled and credentials available
							if (!$content['called_for'] && $account->acc_sieve_enabled && $account->acc_imap_username)
							{
								$account->imapServer()->retrieveRules();
							}
							$new_account = !((int)$content['acc_id'] > 0);
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
							// SMIME SAVE
							if (isset($content['smimeKeyUpload']))
							{
								$content['acc_smime_cred_id'] = self::save_smime_key($content, $tpl, $content['called_for']);
								unset($content['smimeKeyUpload']);
							}
							self::fix_account_id_0($content['account_id'], true);
							$content = Mail\Account::write($content, !empty($content['called_for']) && $this->is_admin ?
								$content['called_for'] : $GLOBALS['egw_info']['user']['account_id']);
							self::fix_account_id_0($content['account_id']);
							// self-heal our own csp-connect-src hook registration right away, so THIS
							// account's real JMAP host is already covered by the time the client-side
							// stale-CSP recovery (MailJmap.recoverFromStaleCsp(), mail/js/jmap.ts) reloads
							// the page - hooks purely live in a cache, never need a schema change or
							// admin action, same self-healing pattern already used by
							// mail_integration.inc.php for the 'mail_import' hook
							if (is_a($content['acc_imap_type'] ?? '', Mail\Imap\Stalwart::class, true) &&
								!Api\Hooks::exists('csp-connect-src', 'mail'))
							{
								Api\Hooks::read(true);
							}
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
							// smime (private) key uploaded by user himself
							if (!empty($content['smimeKeyUpload']))
							{
								$content['acc_smime_cred_id'] = self::save_smime_key($content, $tpl);
								unset($content['smimeKeyUpload']);
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
					Framework::refresh_opener($msg, 'mail-account', $content['acc_id'], $new_account ? 'add' : 'update', null, null, null, $msg_type);
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
						Framework::refresh_opener(lang('Account deleted.'), 'mail-account', $content['acc_id'], 'delete');
						Framework::window_close();
					}
					else
					{
						$msg = lang('Failed to delete account!');
						$msg_type = 'error';
					}
			}
		}
		$account_id = $content['called_for'] ? $content['called_for'] : $GLOBALS['egw_info']['user']['account_id'];

		// SMIME EXPORT (p12 or CSR): flat-id buttons (no "button[...]" bracket notation) submit
		// their own clicked state directly as $content[id], same convention as smime_delete_p12
		// below - NOT via the bracket-notation $content['button'][...] dispatch further up.
		// Reached via a real browser form POST (Etemplate2::postSubmit(), not ajax), so the
		// server can exit() with the file directly - a synthetic <a download> click triggered
		// from JS turned out to be unreliable (request not even reaching the server in some
		// browser/popup contexts).
		if (!empty($content['smime_export_p12']) || !empty($content['smime_export_csr']))
		{
			if (($error = $this->smimeExportFile($content, $tpl, $account_id, !empty($content['smime_export_csr']))))
			{
				$msg = $error;
				$msg_type = 'error';
			}
			unset($content['smime_export_p12'], $content['smime_export_csr']);
		}

		// SMIME IMPORT: CA-signed certificate for the already stored private key
		if (!empty($content['smimeCertUpload']['tmp_name']) &&
			($cred_id = self::import_smime_cert($content, $tpl, $account_id)))
		{
			$content['acc_smime_cred_id'] = $cred_id;
			$msg = lang('Certificate imported.');
		}
		unset($content['smimeCertUpload'], $content['smimeIntermediateUpload'], $content['smime_passphrase']);

		// SMIME UPLOAD/DELETE/EXPORT control
		$content['hide_smime_upload'] = false;
		if (!empty($content['acc_smime_cred_id']))
		{
			if (!empty($content['smime_delete_p12']) &&
					Mail\Credentials::delete (
						$content['acc_id'],
						$account_id,
						Mail\Credentials::SMIME
				))
			{
				unset($content['acc_smime_password'], $content['smimeKeyUpload'], $content['smime_delete_p12'], $content['acc_smime_cred_id']);
				$content['hide_smime_upload'] = false;
			}
			else
			{
				// proactively tell the user their key needs a passphrase (Export CSR/p12, Import
				// certificate) BEFORE they hit a confusing error, instead of only after the fact
				$content['smime_needs_passphrase'] = !empty($content['acc_smime_password']) &&
					Mail\Smime::isPassphraseProtected($content['acc_smime_password']);

				// do NOT send smime private key to client side, it's unnecessary and binary blob breaks json encoding
				$content['acc_smime_password'] = Mail\Credentials::UNAVAILABLE;

				$content['hide_smime_upload'] = true;
			}
		}
		if ($content['smime_needs_passphrase'] ?? false)
		{
			$tpl->setElementAttribute('smime_passphrase', 'placeholder',
				lang('Required to export or import a certificate for this key'));
		}

		// Uploading a p12 only makes sense without an existing key - smimeGenerate itself is
		// fully hidden (not just grayed) once readonly, via its hideOnReadonly="true" template
		// attribute (Et2Button/ButtonMixin.ts); export/delete only make sense with one; export
		// CSR always makes sense (creates the key first if needed, see ajax_smimeCreateKeypair()).
		// Using $readonlys, as it stays in sync client-side after ajax_smimeCreateKeypair() via
		// Et2Widget::set_readonly() - an explicit false here also exempts these widgets from
		// readonlys['__ALL__'] below for non-edit-access users.
		$readonlys['smimeGenerate'] = $readonlys['smimeKeyUpload'] = $content['hide_smime_upload'];
		$readonlys['smime_export_p12'] = $readonlys['smime_delete_p12'] = $readonlys['smimeCertUpload'] =
		$readonlys['smimeIntermediateUpload'] = !$content['hide_smime_upload'];
		// smime_passphrase is shared by both states (unlocks a file being uploaded now, or the
		// already-stored key for import/export), so unlike the fields above it's never readonly
		$readonlys['smime_export_csr'] = $readonlys['smime_passphrase'] = false;
		// only enabled once a certificate file was actually chosen, see smime_certFileChanged()
		$readonlys['smime_import_cert'] = true;

		// disable delete button for new, not yet saved entries, if no delete rights or a non-standard identity selected
		$readonlys['button[delete]'] = empty($content['acc_id']) ||
			!Mail\Account::check_access(Acl::DELETE, $content) ||
			$content['ident_id'] != $content['std_ident_id'];

		// if account is for multiple user, change delete confirmation to reflect that
		$tpl->setElementAttribute('button[delete]', 'onclick', !Mail\Account::is_multiple($content) ?
			"Et2Dialog.confirm(widget,'Delete this account','Delete')" :
			"Et2Dialog.confirm(widget,'This is NOT a personal mail account!\\n\\nAccount will be deleted for ALL users!\\n\\nAre you really sure you want to do that?','Delete this account')");

		// if no edit access, make whole dialog readonly
		if (!$edit_access)
		{
			$readonlys['__ALL__'] = true;
			$readonlys['button[cancel]'] = false;
			// allow to edit notification-folders
			$readonlys['button[save]'] = $readonlys['button[apply]'] =
			$readonlys['notify_folders'] = $readonlys['notify_use_default'] = false;
			// SMIME widgets are already explicitly true/false (not unset) above, based on account
			// state - an explicit false there already exempts them from __ALL__, so nothing to do here.
		}

		$sel_options['acc_imap_ssl'] = self::sslTypes('IMAP');
		$sel_options['acc_sieve_ssl'] = self::sslTypes('Sieve');
		$sel_options['acc_smtp_ssl'] = self::sslTypes('SMTP', false);

		// admin access to account with no credentials available
		if ($this->is_admin && (!empty($content['called_for']) || empty($content['acc_imap_host']) || $content['called_for']) ||
			// if OAuth failed, do not try to connect and trigger next authentication(-failure), but show failure message
			!empty($content['oauth_failure']))
		{
			// can't connection to imap --> allow free entries in taglists
			foreach(array('acc_folder_sent', 'acc_folder_trash', 'acc_folder_draft', 'acc_folder_template', 'acc_folder_junk') as $folder)
			{
				$tpl->setElementAttribute($folder, 'allowFreeEntries', true);
			}
		}
		else
		{
			try {
				if (($oauth = OpenIDConnectClient::providerByDomain(
					$content['acc_oauth_username'] ?? $content['acc_imap_username'] ?? $content['ident_email'], $content['acc_imap_host'])))
				{
					$content += self::oauth2content($oauth);
				}
				// a JMAP account has no raw IMAP socket to guess folders on - acc_imap_host/port
				// point at the JMAP(S) endpoint, not an IMAP server, so the classic path below
				// would hang for the full IMAP connect-timeout trying to speak IMAP to eg. a
				// JMAP-over-https port 443 (found live 2026-08-24, a personal single-user Stalwart
				// account reaching edit() for the first time - previously only acc_id=1, a
				// multi-user account, ever had a JMAP acc_imap_type, and multi-user accounts take
				// the OTHER (allowFreeEntries) branch above, never reaching this code at all)
				if (is_a($content['acc_imap_type'] ?? '', Mail\Imap\Jmap::class, true))
				{
					$jmap = static::jmapClient($content['acc_imap_host'], $content['acc_imap_username'], $content['acc_imap_password']);
					$folders = self::jmapMailboxes($jmap, $content);
				}
				else
				{
					$folders = self::mailboxes(self::imap_client($content));
				}
				$sel_options['acc_folder_sent'] = $sel_options['acc_folder_trash'] =
					$sel_options['acc_folder_draft'] = $sel_options['acc_folder_template'] =
					$sel_options['acc_folder_junk'] = $sel_options['acc_folder_archive'] =
					$sel_options['notify_folders'] = $sel_options['acc_folder_ham'] = $folders;
				// Allow folder notification on INBOX for popup_only
				if ($GLOBALS['egw_info']['user']['preferences']['notifications']['notification_chain'] == 'popup_only')
				{
					$sel_options['notify_folders']['INBOX'] = lang('INBOX');
				}
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
				if ((int)$content['ident_id'] > 0)
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
				array_combine($content['mailAlternateAddress'] ?? [], $content['mailAlternateAddress'] ?? []));
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

		// when called by admin for existing accounts, display further administrative actions
		if ($content['called_for'] && (int)$content['acc_id'] > 0)
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

		//try to fix identities with no domain part set e.g. alias as identity
		if (!strpos($content['ident_email'], '@'))
		{
			$content['ident_email'] = Mail::fixInvalidAliasAddress (Api\Accounts::id2name($content['acc_imap_account_id'], 'account_email'), $content['ident_email']);
		}

		// If no EPL available, show that in spamtitan blur
		$content['spamtitan_blur'] = $GLOBALS['egw_info']['user']['apps']['stylite'] ? '' : lang('SpamTitan integration requires EPL version');

		foreach (['acc_imap_ssl', 'acc_sieve_ssl', 'acc_smtp_ssl'] as $ssl_field)
		{
			$content = self::splitVerifyCheckbox($content, $ssl_field);
		}
		$tpl->exec(static::APP_CLASS.'edit', $content, $sel_options, $readonlys, $content, 2);
	}

	/**
	 * Build a clear error message for a failed private-key decryption attempt (Export CSR, Import
	 * certificate), distinguishing "no passphrase was submitted at all" (likely just needs one -
	 * the user may not have realised, despite the proactive hint set on the field, see edit()'s
	 * smime_needs_passphrase handling) from "a passphrase was submitted but didn't work" (likely
	 * just wrong) - both previously showed the same generic "wrong passphrase?" message.
	 *
	 * @param string $passphrase whatever was actually submitted (possibly empty)
	 * @return string translated message
	 */
	private static function smimePassphraseError($passphrase)
	{
		return $passphrase === ''
			? lang('This S/MIME private key is passphrase-protected. Please enter the passphrase above and try again.')
			: lang('The passphrase entered was not correct, please try again.');
	}

	/**
	 * Export the stored S/MIME certificate as p12, or a CSR for it, as a file download and exit()
	 *
	 * Called from edit()'s flat-id button handling (smime_export_p12/smime_export_csr), reached
	 * via a real browser form POST (Etemplate2::postSubmit(), not ajax), so the server can
	 * respond with the file directly.
	 *
	 * @param array $content current (posted) form content, 'acc_id' used to look up the key,
	 *  'smime_passphrase' (shared with the certificate-upload/import rows) used to unlock it if
	 *  the stored p12 is itself passphrase-protected - needed for the CSR branch, which has to
	 *  actually extract the private key (the p12 export branch just streams the stored blob
	 *  as-is, so it works even without a passphrase).
	 * @param Etemplate $tpl used to highlight the passphrase field on failure, same as import_smime_cert()
	 * @param int $account_id already resolved from $content['called_for'] by the caller
	 * @param bool $csr true: export a CSR generated from the stored key, false: export the p12 itself
	 * @return string|null translated error message, or null on success (exit()s, does not return)
	 */
	private function smimeExportFile(array $content, Etemplate $tpl, $account_id, $csr)
	{
		$passphrase = $content['smime_passphrase'] ?: '';
		$acc_smime = Mail\Smime::get_acc_smime($content['acc_id'], $passphrase, $account_id);

		if ($csr)
		{
			if ($acc_smime === false)
			{
				return lang('No S/MIME private key stored for this account.');
			}
			if (empty($acc_smime['pkey']))
			{
				$msg = self::smimePassphraseError($passphrase);
				$tpl->set_validation_error('smime_passphrase', $msg);
				return $msg;
			}
			$dn = !empty($acc_smime['cert']) ? Mail\Smime::dn_from_cert($acc_smime['cert']) : array();
			if (!($data = Mail\Smime::generate_csr($acc_smime['pkey'], $dn, $passphrase)))
			{
				return lang('Could not generate CSR.');
			}
			$filename = 'certificate.csr';
			$mime = 'application/pkcs10';
		}
		else
		{
			if (empty($acc_smime['acc_smime_password']))
			{
				return lang('No S/MIME private key stored for this account.');
			}
			$data = $acc_smime['acc_smime_password'];
			$filename = 'certificate.p12';
			$mime = 'application/x-pkcs12';
		}
		$length = 0;
		Api\Header\Content::safe($data, $filename, $mime, $length, true, true);
		echo $data;
		exit();
	}

	/**
	 * Saves the smime key
	 *
	 * @param array $content
	 * @param Etemplate $tpl
	 * @param int $account_id =null account to save smime key for, default current user
	 * @return int cred_id or null on error
	 */
	private static function save_smime_key(array $content, Etemplate $tpl, $account_id=null)
	{
		if (($pkcs12 = file_get_contents($content['smimeKeyUpload']['tmp_name'])))
		{
			$cert_info = Mail\Smime::extractCertPKCS12($pkcs12, $content['smime_passphrase']);
			if (is_array($cert_info) && !empty($cert_info['cert']))
			{
				// save public key
				$smime = new Mail\Smime;
				$email = $smime->getEmailFromKey($cert_info['cert']);
				$AB_bo = new addressbook_bo();
				$AB_bo->set_smime_keys(array(
					$email => $cert_info['cert']
				));
				// save private key
				if (!isset($account_id)) $account_id = $GLOBALS['egw_info']['user']['account_id'];
				return Mail\Credentials::write($content['acc_id'], $email, $pkcs12, Mail\Credentials::SMIME, $account_id);
			}
			$tpl->set_validation_error('smimeKeyUpload', lang('Could not extract private key from given p12 file. Either the p12 file is broken or password is wrong!'));
		}
		return null;
	}

	/**
	 * Ajax entry point for the "Create self-signed certificate" and "Export
	 * CSR" (when no key is stored yet) buttons: generates a new private key
	 * and (self-signed) certificate for the given DN and stores it right
	 * away, so the caller can proceed to download a CSR for it without a
	 * second, separate save step.
	 *
	 * Runs outside the normal edit()/save flow (no full template
	 * submit+redraw), so the client updates its own button states via
	 * Et2Widget::set_readonly() based on the returned cred_id instead of
	 * relying on a server-rendered $readonlys.
	 *
	 * @param array $_data DN fields (countryName, stateOrProvinceName,
	 *  localityName, organizationName, organizationalUnitName, commonName,
	 *  emailAddress), plus validity, passphrase, passphraseConf, acc_id,
	 *  called_for
	 * @param string $etemplate_exec_id
	 */
	public function ajax_smimeCreateKeypair($_data, $etemplate_exec_id)
	{
		Api\Etemplate\Request::csrfCheck($etemplate_exec_id, __METHOD__, func_get_args());

		$response = Api\Json\Response::get();
		if (empty($_data['acc_id']))
		{
			$response->message(lang('No mail account given!'), 'error');
			return;
		}
		if (!($account_id = self::verifySmimeAccountAccess($_data['acc_id'], $_data['called_for'] ?? null, $this->is_admin)))
		{
			$response->message(lang('Permission denied!'), 'error');
			return;
		}
		$content = array('acc_id' => $_data['acc_id'], 'smime_gen_dn' => json_encode($_data));
		$tpl = new Etemplate();
		if (!($cred_id = self::generate_smime_key($content, $tpl, $account_id)))
		{
			$response->message(Etemplate::get_validation_errors('smimeGenerate') ?:
				lang('Could not generate certificate!'), 'error');
			return;
		}
		$response->data(array('acc_smime_cred_id' => $cred_id));
	}

	/**
	 * Verify the current session may act on behalf of $called_for for the given mail account
	 *
	 * Guards ajax_smimeCreateKeypair() against a client submitting an arbitrary acc_id/called_for
	 * in its (otherwise untrusted) ajax payload to act on someone else's mail account: only admins
	 * may act on behalf of a DIFFERENT user, and $acc_id must actually belong to / be usable by
	 * $called_for (or the current user, if $called_for is empty) regardless.
	 *
	 * @param int $acc_id untrusted, from the ajax payload
	 * @param int|string|null $called_for untrusted, from the ajax payload
	 * @param bool $is_admin whether the CURRENT (session) user has admin app rights
	 * @return int|null verified account_id, or null if not authorized / acc_id does not belong to it
	 */
	public static function verifySmimeAccountAccess($acc_id, $called_for, $is_admin)
	{
		$account_id = !empty($called_for) ? (int)$called_for : $GLOBALS['egw_info']['user']['account_id'];
		if ($account_id != $GLOBALS['egw_info']['user']['account_id'] && !$is_admin)
		{
			return null;
		}
		try
		{
			// verify acc_id actually belongs to / is usable by account_id, throws NotFound otherwise
			Mail\Account::read($acc_id, $account_id);
		}
		catch (Api\Exception\NotFound $e)
		{
			return null;
		}
		return $account_id;
	}

	/**
	 * Generate a new S/MIME private key and (self-signed) certificate
	 *
	 * Used both to create a self-signed certificate right away, and to create
	 * a placeholder key+certificate for which a CSR can then be exported and
	 * sent to a CA - the real certificate later replaces the placeholder via
	 * import_smime_cert(), reusing this very same private key.
	 *
	 * @param array $content 'smime_gen_dn' holds the JSON-encoded DN fields
	 *  (countryName, stateOrProvinceName, localityName, organizationName,
	 *  organizationalUnitName, commonName, emailAddress, validity, passphrase)
	 *  as collected by the "Create certificate" dialog
	 * @param Etemplate $tpl
	 * @param int $account_id account to store the key for
	 * @return int|null cred_id or null on error
	 */
	private static function generate_smime_key(array $content, Etemplate $tpl, $account_id)
	{
		$dn = json_decode($content['smime_gen_dn'] ?? '', true) ?: array();
		$passphrase = !empty($dn['passphrase']) ? $dn['passphrase'] : null;
		$validity = (int)($dn['validity'] ?? 0) ?: 365;
		unset($dn['passphrase'], $dn['passphraseConf'], $dn['validity']);
		$dn = array_filter($dn);

		if (empty($dn['commonName']) || empty($dn['emailAddress']))
		{
			$tpl->set_validation_error('smimeGenerate', lang('Common name and email address are required!'));
			return null;
		}
		$smime = new Mail\Smime();
		$cert_data = $smime->generate_certificate($dn, null, $passphrase, $validity);
		// both args must be the same passphrase: privPassphrase unlocks the just-generated privkey
		// PEM (encrypted with it above), exportPassword protects the resulting p12 container itself -
		// without the latter, the p12 ends up with NO container password even though the user chose
		// one, and a later get_acc_smime() (which checks the CONTAINER password) fails to open it.
		if (empty($cert_data['cert']) || empty($cert_data['privkey']) ||
			!($p12 = Mail\Smime::build_pkcs12($cert_data['privkey'], $cert_data['cert'], $passphrase ?: '', $passphrase ?: '')))
		{
			$tpl->set_validation_error('smimeGenerate', lang('Could not generate certificate!'));
			return null;
		}
		$AB_bo = new addressbook_bo();
		$AB_bo->set_smime_keys(array($dn['emailAddress'] => $cert_data['cert']));
		return Mail\Credentials::write($content['acc_id'], $dn['emailAddress'], $p12, Mail\Credentials::SMIME, $account_id);
	}

	/**
	 * Import a CA-signed certificate (optionally with a separately provided intermediate/CA
	 * certificate) for the already stored S/MIME private key
	 *
	 * Combines the newly uploaded certificate with the private key extracted
	 * from the currently stored PKCS12 (self-signed or CA-issued) and
	 * re-stores the result, so message signing/decryption keeps using the
	 * very same private key. Accepts PEM or DER, a single certificate, several
	 * certificates concatenated, or a PKCS#7 (.p7b) bundle - see
	 * Mail\Smime::normalize_cert_pem(). Whichever certificate actually
	 * matches the stored private key is used as the leaf; any others (eg. an
	 * intermediate CA certificate, from the same upload or the separate
	 * smimeIntermediateUpload field) are bundled into the p12 as extracerts,
	 * so outgoing signed mail includes them (see build_pkcs12()). The
	 * certificate being replaced is also kept as an extracert (not sent with
	 * outgoing mail, see Smime::isOwnCertificate()), so messages received
	 * under it can still be decrypted after renewal, see
	 * Smime::decryptWithCandidates().
	 *
	 * @param array $content 'smimeCertUpload' file upload, optional 'smimeIntermediateUpload'
	 *  file upload, optional 'smime_passphrase' to unlock the stored private key, needs existing
	 *  'acc_smime_cred_id'
	 * @param Etemplate $tpl
	 * @param int $account_id
	 * @return int|null new cred_id or null on error
	 */
	private static function import_smime_cert(array $content, Etemplate $tpl, $account_id)
	{
		if (empty($content['acc_smime_cred_id']))
		{
			$tpl->set_validation_error('smimeCertUpload', lang('No private key stored to import a certificate for!'));
			return null;
		}
		$certs = array();
		foreach (array('smimeCertUpload', 'smimeIntermediateUpload') as $field)
		{
			if (!empty($content[$field]['tmp_name']) && ($data = file_get_contents($content[$field]['tmp_name'])))
			{
				$certs = array_merge($certs, Mail\Smime::normalize_cert_pem($data));
			}
		}
		if (!$certs)
		{
			$tpl->set_validation_error('smimeCertUpload', lang('Could not read uploaded certificate!'));
			return null;
		}
		$passphrase = $content['smime_passphrase'] ?: '';
		$acc_smime = Mail\Smime::get_acc_smime($content['acc_id'], $passphrase, $account_id);
		if (empty($acc_smime['pkey']) || !($key = openssl_pkey_get_private($acc_smime['pkey'], $passphrase)))
		{
			$tpl->set_validation_error('smime_passphrase', self::smimePassphraseError($passphrase));
			return null;
		}
		// whichever uploaded certificate actually matches the stored key is the leaf, any others
		// (eg. an intermediate CA certificate) are bundled alongside it, not stored as "the" cert
		$cert = null;
		$extracerts = array();
		foreach ($certs as $candidate)
		{
			if (!$cert && openssl_x509_check_private_key($candidate, $key))
			{
				$cert = $candidate;
			}
			else
			{
				$extracerts[] = $candidate;
			}
		}
		if (!$cert)
		{
			$tpl->set_validation_error('smimeCertUpload', lang('Certificate does not match the stored private key!'));
			return null;
		}
		// Keep the certificate being replaced around too (same private key, so still usable to
		// decrypt messages that were encrypted under it) - see Smime::decryptWithCandidates().
		// Deliberately keeps the FULL history across repeated renewals, not just the last one -
		// losing an older certificate means every message ever encrypted under it becomes
		// permanently unreadable, which is worse than a large stored credential. The size check
		// below fails loudly instead of silently truncating if that history ever doesn't fit in
		// cred_password (egw_ea_credentials) - see Credentials::maxPasswordLength().
		if (!empty($acc_smime['cert']) && strcasecmp(trim($acc_smime['cert']), trim($cert)) !== 0)
		{
			$extracerts[] = $acc_smime['cert'];
		}
		$extracerts = array_values(array_unique(array_merge($extracerts, $acc_smime['extracerts'] ?? [])));
		// re-apply the same passphrase as the container password too, so the re-combined p12 keeps
		// the same protection level it had before (see matching comment in generate_smime_key())
		if (!($p12 = Mail\Smime::build_pkcs12($acc_smime['pkey'], $cert, $passphrase, $passphrase, $extracerts)))
		{
			$tpl->set_validation_error('smimeCertUpload', lang('Certificate does not match the stored private key!'));
			return null;
		}
		// egw_ea_credentials.cred_password has a limited size - fail loudly with all certs still
		// intact in storage, instead of Credentials::write() silently truncating/corrupting the
		// blob (which would break both signing AND decrypting) - see Credentials::encrypt() for
		// the base64+AES overhead this estimates (salt + up to one block of padding). Checks the
		// actual column size (Credentials::maxPasswordLength()), not a value fixed in code, so a
		// site that enlarged the column is recognised without needing a matching code change.
		if ((strlen(base64_encode($p12)) + 32) > Mail\Credentials::maxPasswordLength())
		{
			$tpl->set_validation_error('smimeCertUpload',
				lang('Certificate chain too large to store (%1 certificates incl. retired ones) - contact an administrator.',
					1 + count($extracerts)));
			return null;
		}
		$smime = new Mail\Smime;
		$email = $smime->getEmailFromKey($cert);
		$AB_bo = new addressbook_bo();
		$AB_bo->set_smime_keys(array($email => $cert));
		return Mail\Credentials::write($content['acc_id'], $email, $p12, Mail\Credentials::SMIME, $account_id,
			$content['acc_smime_cred_id']);
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
			$account_id = $account_id ? explode(',', $account_id) : [];
		}
		if ($back && !$account_id)
		{
			$account_id = 0;
		}
		if (!$back && count($account_id) === 1 && !current($account_id))
		{
			$account_id = [];
		}
	}

	/**
	 * Try to connect via JMAP, before falling back to IMAP
	 *
	 * Sources tried: DNS SRV (_jmap._tcp.$domain, not commonly published yet) and an explicitly
	 * entered host (manual setup) - unlike IMAP, we don't guess JMAP hostnames via ISPDB/MX.
	 * On success sets $content['acc_imap_host'/'acc_imap_type'/'connected'] and stashes the
	 * bootstrapped JMAP session's accountCapabilities into $content['_jmap_account_capabilities']
	 * (a transient wizard-state key, not a persisted account field) for sieve() to read.
	 *
	 * @param array &$content requires 'ident_email', 'acc_imap_username', 'acc_imap_password',
	 *  optionally an explicit 'acc_imap_host'
	 * @return bool true if connected via JMAP
	 */
	protected function tryJmap(array &$content) : bool
	{
		list(, $domain) = explode('@', $content['ident_email']);
		$jmap_hosts = [];
		if (($srv = static::dnsQuery('_jmap._tcp.'.$domain, DNS_SRV)))
		{
			foreach($srv as $record)
			{
				$jmap_hosts[$record['target']] = true;
			}
		}
		if (!empty($content['acc_imap_host']))
		{
			$jmap_hosts[$content['acc_imap_host']] = true;
		}
		// manual protocol selection: respect an explicit "JMAP (http)" choice and/or a custom
		// port, so a user can point the wizard at a non-standard JMAP endpoint (default: https).
		// acc_imap_port may still hold add()'s IMAP-oriented seed default (993) at this point -
		// only ever treat it as a deliberate custom JMAP port if it isn't a well-known IMAP or
		// default-JMAP port, to avoid trying eg. "https://host:993" on the very first attempt.
		$scheme = (int)($content['acc_imap_ssl'] ?? self::JMAP_HTTPS) === self::JMAP_HTTP ? 'http' : 'https';
		$default_port = $scheme === 'http' ? 80 : 443;
		$custom_port = !empty($content['acc_imap_port']) &&
			!in_array((int)$content['acc_imap_port'], [80, 443, 993, 143], true) ? (int)$content['acc_imap_port'] : null;
		// a manually pre-checked "disable certificate validation" checkbox skips the strict
		// attempt entirely - a user cannot manually claim "verified", only "don't verify"
		$initial_verify_state = (int)($content['acc_imap_ssl'] ?? 0) & self::VERIFY_MASK;

		foreach($jmap_hosts as $host => $data)
		{
			$url = preg_match('#^https?://#', $host) ? $host : $scheme.'://'.$host.($custom_port ? ':'.$custom_port : '');
			$content['output'] .= "\n".Api\DateTime::to('now', 'H:i:s').": Trying JMAP connection to $url ...\n";
			$accountId = null;
			$verify_ssl = null;
			try {
				if ($initial_verify_state === self::VERIFY_DISABLED)
				{
					$jmap = static::jmapClient($url, $content['acc_imap_username'], $content['acc_imap_password'], $accountId, false);
					$verify_ssl = self::VERIFY_DISABLED;
				}
				else
				{
					try {
						$jmap = static::jmapClient($url, $content['acc_imap_username'], $content['acc_imap_password'], $accountId);
						$verify_ssl = self::VERIFY_ENABLED;	// strict connection succeeded
					}
					catch (Api\Exception\Http $e) {
						// only a plausible certificate-verification failure gets a lenient retry -
						// any other failure (wrong credentials, host down, ...) must still surface
						if (!preg_match('/certificate|ssl|tls/i', $e->getMessage()))
						{
							throw $e;
						}
						$jmap = static::jmapClient($url, $content['acc_imap_username'], $content['acc_imap_password'], $accountId, false);
						$verify_ssl = self::VERIFY_DISABLED;
						$content['output'] .= "\n".lang('Certificate could NOT be verified - continuing without certificate verification for this account.')."\n";
					}
				}
				$content['output'] .= "\n".lang('Successful connected to %1 server%2.', 'JMAP', ' '.lang('and logged in'))."\n";

				// live-validate the Stalwart OAuth-login workaround now, rather than only
				// discovering a problem later at first real mail-usage - a real Stalwart server
				// is the only thing that can succeed here (it's a Stalwart-specific proprietary
				// endpoint, see Mail\Jmap::passwordGrant()'s docblock), so the result doubles as a
				// first, cheap way to tell a real Stalwart server apart from a generic JMAP server
				// (ralf, 2026-08-24) - Phase 2 will replace this with the same "leave the password
				// empty to trigger a real OAuth flow" pattern already used for Google/Microsoft
				// 365, verified against a general JMAP provider (FastMail)
				$oauthWorked = (bool)$jmap->passwordGrant($content['acc_imap_username'], $content['acc_imap_password']);
				if (!$oauthWorked)
				{
					$content['output'] .= "\n".lang('Could not obtain an OAuth token via the Stalwart login workaround, account will use plain password authentication.')."\n";
				}
				$content['acc_imap_host'] = $host;
				$content['acc_imap_ssl'] = ($scheme === 'http' ? self::JMAP_HTTP : self::JMAP_HTTPS) | $verify_ssl;
				$content['acc_imap_port'] = $custom_port ?: $default_port;
				$content['acc_imap_type'] = $oauthWorked ? Mail\Imap\Stalwart::class : Mail\Imap\Jmap::class;
				$content['_jmap_account_capabilities'] = $jmap->accountCapabilities;
				$content['connected'] = 'jmap';
				return true;
			}
			catch (\Throwable $e) {
				$content['output'] .= "\n".get_class($e).': '.$e->getMessage()."\n";
				if (self::$debug) _egw_log_exception($e);
			}
		}
		return false;
	}

	/**
	 * Instanciate imap-client
	 *
	 * @param array $content
	 * @param int $timeout =null default use value returned by Mail\Imap::getTimeOut()
	 * @return Horde_Imap_Client_Socket
	 */
	protected static function imap_client(array &$content, $timeout=null, ?bool $forceVerify=null)
	{
		$verify = $forceVerify ?? ((int)$content['acc_imap_ssl'] & self::VERIFY_MASK) === self::VERIFY_ENABLED;
		$config = [
			'username' => $content['acc_imap_username'],
			'password' => $content['acc_imap_password'],
			'hostspec' => $content['acc_imap_host'],
			'port' => $content['acc_imap_port'],
			'secure' => self::$ssl2secure[(string)array_search((int)$content['acc_imap_ssl'] & self::PROTOCOL_MASK, self::$ssl2type)],
			'context' => ['ssl' => ['verify_peer' => $verify, 'verify_peer_name' => $verify]],
			'timeout' => $timeout > 0 ? $timeout : Mail\Imap::getTimeOut(),
			'debug' => self::DEBUG_LOG,
		];
		if (!empty($content['acc_oauth_provider_url']) || !empty($content['acc_oauth_access_token']))
		{
			$config['xoauth2_token'] = self::oauthToken($content);
			$config['username'] = $content['acc_oauth_username'] ?? $content['acc_imap_username'];
			if (empty($config['password'])) $config['password'] = '**oauth**';    // some password is required, even if not used
		}
		return new Horde_Imap_Client_Socket($config);
	}

	/**
	 * Acquire OAuth access (and refresh) token
	 */
	protected static function oauthToken(array &$content, bool $smtp=false)
	{
		if (empty($content['acc_oauth_access_token']))
		{
			if (empty($content['acc_oauth_client_secret']) &&
				($oauth = OpenIDConnectClient::providerByDomain($content['acc_oauth_username'] ?? $content['acc_imap_username'] ?? $content['ident_email'], $content['acc_imap_host'])))
			{
				$content += self::oauth2content($oauth);
			}
			if (empty($content['acc_oauth_client_secret']))
			{
				throw new Exception(lang("No OAuth client secret for provider '%1'!", $content['acc_oauth_provider_url']));
			}
			$oidc = new OpenIDConnectClient($content['acc_oauth_provider_url'],
				$content['acc_oauth_client_id'], $content['acc_oauth_client_secret']);

			// Office365 requires client-ID as appid GET parameter (https://github.com/jumbojett/OpenID-Connect-PHP/issues/190)
			if (!empty($content[OpenIDConnectClient::ADD_CLIENT_TO_WELL_KNOWN]))
			{
				$oidc->setWellKnownConfigParameters([$content[OpenIDConnectClient::ADD_CLIENT_TO_WELL_KNOWN] => $content['acc_oauth_client_id']]);
			}
			// Google requires access_type=offline&prompt=consent to return a refresh-token
			if (!empty($content[OpenIDConnectClient::ADD_AUTH_PARAM]))
			{
				$oidc->addAuthParam(str_replace('$username', $content['acc_oauth_username'] ?? $content['acc_imap_username'] ?? $content['ident_email'], $content[OpenIDConnectClient::ADD_AUTH_PARAM]));
			}

			// we need to use response_code=query / GET request to keep our session token!
			$oidc->setResponseTypes(['code']);  // to be able to use query, not 'id_token'
			//$oidc->setAllowImplicitFlow(true);
			$oidc->addScope($content['acc_oauth_scopes']);
		}

		if (!empty($content['acc_oauth_access_token']) ||
			!empty($content['acc_oauth_refresh_token']) && $content['acc_oauth_refresh_token'] !== Mail\Credentials::UNAVAILABLE)
		{
			if (empty($content['acc_oauth_access_token']))
			{
				$content['acc_oauth_access_token'] = $oidc->refreshToken($content['acc_oauth_refresh_token'])->access_token;
			}
			if (!empty($content['acc_oauth_access_token']))
			{
				if ($smtp)
				{
					return new Horde_Smtp_Password_Xoauth2($content['acc_oauth_username'] ?? $content['acc_smtp_username'], $content['acc_oauth_access_token']);
				}
				return new Horde_Imap_Client_Password_Xoauth2($content['acc_oauth_username'] ?? $content['acc_imap_username'], $content['acc_oauth_access_token']);
			}
		}
		// Run OAuth authentication, will NOT return, but call success or failure callbacks below
		$oidc->authenticateThen(__CLASS__.'::oauthAuthenticated', [$content], __CLASS__.'::oauthFailure', [$content]);
	}

	/**
	 * Oauth success callback calling autoconfig again
	 *
	 * @param OpenIDConnectClient $oidc
	 * @param array $content
	 * @return void
	 */
	public static function oauthAuthenticated(OpenIDConnectClient $oidc, array $content)
	{
		if (empty($content['acc_oauth_username']))
		{
			$content['acc_oauth_username'] = $content['acc_imap_username'] ?? $oidc->getVerifiedClaims('email') ?? $content['ident_email'];
		}
		if (empty($content['acc_oauth_refresh_token'] = $oidc->getRefreshToken()))
		{
			$content['output'] .= lang('OAuth Authentication').': '.lang('Successful, but NO refresh-token received!');
			$content['connected'] = false;
		}
		$content['acc_oauth_access_token'] = $oidc->getAccessToken();

		if (empty($GLOBALS['egw_info']['user']['apps']['admin']))
		{
			$GLOBALS['egw_info']['flags']['currentapp'] = 'mail';
			$obj = new mail_wizard();
		}
		else
		{
			$GLOBALS['egw_info']['flags']['currentapp'] = 'admin';
			$obj = new self;
		}
		unset($content['oauth_failure']);
		if (!empty($content['acc_id']))
		{
			$content['button'] = ['save' => true];  // automatic save token, refresh mail app and close popup
			$obj->edit($content, lang('Use save or apply to store the received OAuth token!'), 'info');
		}
		else
		{
			$obj->autoconfig($content);
		}
	}

	/**
	 * Oauth failure callback calling autoconfig again
	 *
	 * @param OpenIDConnectClientException|null $exception
	 * @param array $content
	 */
	public static function oauthFailure(Throwable  $exception=null, array $content)
	{
		if (empty($GLOBALS['egw_info']['user']['apps']['admin']))
		{
			$GLOBALS['egw_info']['flags']['currentapp'] = 'mail';
			$obj = new mail_wizard();
		}
		else
		{
			$GLOBALS['egw_info']['flags']['currentapp'] = 'admin';
			$obj = new self;
		}
		$content['oauth_failure'] = $exception ?: true;
		if (!empty($content['acc_id']))
		{
			$obj->edit($content, lang('OAuth Authentiction').': '.($exception ? $exception->getMessage() : lang('failed')), 'error');
		}
		else
		{
			$content['output'] .= lang('OAuth Authentiction').': '.($exception ? $exception->getMessage() : lang('failed'));
			$content['connected'] = false;

			$obj->autoconfig($content);
		}
		$obj->autoconfig($content);
	}

	/**
	 * Readonly fields for the account edit dialog, when the current user is NOT a mail-admin
	 *
	 * All values are preserved server-side, this only prevents the client from submitting changes.
	 *
	 * @return array field-name => true pairs, suitable as Etemplate readonlys
	 */
	protected static function adminReadonlyFields()
	{
		return array(
			'account_id' => true, 'button[multiple]' => true, 'acc_user_editable' => true,
			'acc_further_identities' => true,
			'acc_imap_type' => true, 'acc_imap_logintype' => true, 'acc_domain' => true,
			'acc_imap_admin_username' => true, 'acc_imap_admin_password' => true, 'acc_imap_admin_use_without_pw' => true,
			'acc_smtp_type' => true, 'acc_smtp_auth_session' => true,
		);
	}

	/**
	 * Ensure correct acc_imap_type/acc_smtp_type etc. values for single- vs. multi-user mail accounts
	 *
	 * For a single user account we only hide the type-selection on the client, so make sure the
	 * values are actually forced server-side too (unless it's a JMAP account, which single
	 * connections are allowed to use as well, to be able to use JMAP and push).
	 *
	 * @param array $content
	 * @param bool $is_multiple result of Mail\Account::is_multiple($content)
	 * @return array $content with acc_imap_type/acc_smtp_type/... normalized
	 */
	protected static function normalizeAccountType(array $content, bool $is_multiple)
	{
		// legacy SSL_SSL(3) is unified with SSL_TLS(2) (see Mail\Account::SSL_SSL's docblock) -
		// never persist 3 again, for single- or multi-user accounts alike; any certificate-
		// verification bits (outside PROTOCOL_MASK) are preserved unchanged
		foreach(['acc_imap_ssl', 'acc_sieve_ssl', 'acc_smtp_ssl'] as $ssl_field)
		{
			if (isset($content[$ssl_field]) &&
				((int)$content[$ssl_field] & Mail\Account::PROTOCOL_MASK) === Mail\Account::SSL_SSL)
			{
				$content[$ssl_field] = ((int)$content[$ssl_field] & ~Mail\Account::PROTOCOL_MASK) | Mail\Account::SSL_TLS;
			}
		}

		if (!$is_multiple)
		{
			// we need to allow to use JMAP for single connections too, to be able to use JMAP and push
			// deliberately a plain string comparison, NOT is_a(): normalizeAccountType() must stay
			// callable without the Mail\Imap class hierarchy loaded (it's exercised as pure logic
			// in AdminMailPureLogicTest without any DB session, and merely autoloading Mail\Imap
			// eagerly touches the DB via its trailing Imap::init_static() call) - extend this list
			// when Milestone B (general-JMAP vs. Stalwart split) adds further Imap\Jmap subclasses
			if (!in_array($content['acc_imap_type'] ?? '', [Mail\Imap\Jmap::class, Mail\Imap\Stalwart::class], true))
			{
				$content['acc_imap_type'] = 'EGroupware\\Api\\Mail\\Imap';
			}
			unset($content['acc_imap_login_type']);
			// acc_smtp_type is ALWAYS reset to plain SMTP here, including for JMAP/Stalwart accounts:
			// Smtp\Stalwart is the admin-automation class for administrating a Stalwart server
			// (user/alias/quota management), never a personal account's SMTP transport.
			$content['acc_smtp_type'] = 'EGroupware\\Api\\Mail\\Smtp';
			unset($content['acc_smtp_auth_session']);
			unset($content['notify_use_default']);
		}
		// copy ident_email_alias selectbox back to regular name
		elseif (isset($content['ident_email_alias']) && !empty ($content['ident_email_alias']))
		{
			$content['ident_email'] = $content['ident_email_alias'];
		}
		return $content;
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
			$xml = simplexml_load_string(static::ispdbHttpGet($url) ?: '');
			if (!$xml || !$xml->emailProvider) throw new Api\Exception\NotFound();
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

			if ($try_mx && ($dns = static::dnsQuery($domain, DNS_MX)))
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

		if (($dns = static::dnsQuery($domain, DNS_MX)))
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
			if (!static::dnsQuery($host, DNS_A)) unset($hosts[$host]);
		}
		//error_log(__METHOD__."('$email') returning ".array2string($hosts));
		return $hosts;
	}

	/**
	 * DNS lookup, thin wrapper around dns_get_record() to allow tests to fake DNS responses
	 *
	 * @param string $hostname
	 * @param int $type one of the DNS_* constants, eg. DNS_MX, DNS_A, DNS_SRV
	 * @return array|false see dns_get_record()
	 */
	protected static function dnsQuery(string $hostname, int $type)
	{
		return dns_get_record($hostname, $type);
	}

	/**
	 * HTTP GET, thin wrapper around file_get_contents() to allow tests to fake the ISPDB response
	 *
	 * @param string $url
	 * @return string|false
	 */
	protected static function ispdbHttpGet(string $url)
	{
		return file_get_contents($url);
	}

	/**
	 * Create and bootstrap a JMAP client, thin wrapper to allow tests to inject a stub
	 *
	 * @param string $host hostname or URL to bootstrap via "https://$host/.well-known/jmap"
	 * @param string $username
	 * @param string $password
	 * @param string|null &$accountId on return the JMAP accountId
	 * @param bool $verify =true false: disable TLS certificate verification for this attempt
	 * @return Mail\Jmap
	 * @throws Api\Exception if $host is NOT a JMAP server
	 * @throws Api\Exception\Http on connection or authentication failure
	 */
	protected static function jmapClient(string $host, string $username, string $password, ?string &$accountId=null, bool $verify=true) : Mail\Jmap
	{
		return new Mail\Jmap($host, $username, $password, $accountId, $verify);
	}

	/**
	 * Set mail account status wheter to 'active' or '' (inactive)
	 *
	 * @param array $_data account an array of data called via long task running dialog
	 *	$_data:array (
	 *		id => account_id,
	 *		quota => quotaLimit,
	 *		domain => mailLocalAddress,
	 *		status => mail activation status('active'|'')
	 *	)
	 * @param string $etemplate_exec_id to check against CSRF
	 * @return json response
	 */
	public function ajax_activeAccounts($_data, $etemplate_exec_id)
	{
		Api\Etemplate\Request::csrfCheck($etemplate_exec_id, __METHOD__, func_get_args());

		if (!$this->is_admin) die('no rights to be here!');
		$response = Api\Json\Response::get();
		if (($account = $GLOBALS['egw']->accounts->read($_data['id'])))
		{
			if ($_data['quota'] !== '' || $_data['accountStatus'] !== '' || strpos($_data['domain'], '.'))
			{
				$ea_account = Mail\Account::get_default(false, false, false, true, $_data['id'], true);
				if (!$ea_account || !Mail\Account::is_multiple($ea_account))
				{
					$msg = $account['account_fullname'].' (#'.$_data['id'].'): '.lang('No default account found!');
					return $response->data($msg);
				}

				if ($ea_account && ($userData = $ea_account->getUserData()))
				{
					$userData = array(
						'acc_smtp_type' => $ea_account->acc_smtp_type,
						'accountStatus' => $_data['status'],
						'quotaLimit' => $_data['quota'] ?: $userData['quotaLimit'],
						'mailLocalAddress' => $userData['mailLocalAddress'],
					);

					if (strpos($_data['domain'], '.') !== false)
					{
						$userData['mailLocalAddress'] = preg_replace('/@'.preg_quote($ea_account->acc_domain, '/').'$/', '@'.$_data['domain'], $userData['mailLocalAddress']);

						foreach($userData['mailAlternateAddress'] as &$alias)
						{
							$alias = preg_replace('/@'.preg_quote($ea_account->acc_domain, '/').'$/', '@'.$_data['domain'], $alias);
						}
					}
					// fulfill the saveUserData requirements
					$userData += $ea_account->params;
					$ea_account->saveUserData($_data['id'], $userData);
					$msg = $account['account_fullname'].' (#'.$_data['id'].'): '.
						($userData['accountStatus'] === 'active' ? lang('activated') : lang('deactivated'));
				}
				else
				{
					$msg = lang('No profile defined for user %1', $account['account_fullname'].' (#'.$_data['id'].")\n");
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