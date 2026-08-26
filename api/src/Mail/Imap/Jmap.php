<?php
/**
 * EGroupware Api: Support for Jmap e.g. Stalwart mail-server
 *
 * @link https://www.egroupware.org
 * @package api
 * @subpackage mail
 * @author Ralf Becker <rb-AT-egroupware.org>
 * @copyright (c) 2025 by Ralf Becker <rb-AT-egroupware.org>
 * @license https://opensource.org/license/gpl-2-0 GPL 2.0+ - GNU General Public License 2.0 or any higher version of your choice
 */

namespace EGroupware\Api\Mail\Imap;

use EGroupware\Api;
use EGroupware\Api\Mail;
use EGroupware\SwoolePush\Tokens;
use EGroupware\Api\Mail\Jmap\Imap as JmapImap;
use EGroupware\Api\Mail\Jmap\Http as JmapHttp;

/**
 * Manages connection to Jmap e.g. Stalwart mail-server
 *
 * Currently, JMAP is only partially used:
 * - Push notifications
 * - Sieve script access
 * --> everything else still uses an IMAP connection
 *
 * @ToDo replace everything with JMAP no longer using / extending IMAP
 */
class Jmap extends Mail\Imap
{
	/**
	 * Label shown in EMailAdmin
	 */
	const DESCRIPTION = 'Stalwart/JMAP';
	/**
	 * Capabilities of this class (pipe-separated): default, sieve, admin, logintypeemail
	 */
	const CAPABILITIES = 'default|sieve|timedsieve|admin|logintypeemail';

	/**
	 * Class used to implement Sieve implement the Sieve\Logic
	 */
	const SIEVE_CLASS = Mail\Sieve\Jmap::class;

	/**
	 * prefix for groupnames, when using groups in ACL Management
	 */
	const ACL_GROUP_PREFIX = '$';

	// mailbox delimiter
	var $mailboxDelimiter = '.';

	// mailbox prefix
	var $mailboxPrefix = '';

	/**
	 * @var int accountId of Stalwart (not EGroupware!), stored in session, see __construct()
	 */
	protected $jmap_accountId;
	/**
	 * @var string states of Stalwart, stored in session
	 */
	protected $jmap_states;
	/**
	 * @var string current folder, stored in session and updated by self::enablePush() (called
	 *  from mail_ui::ajax_enablePush(), triggered client-side by mail/js/jmap.ts)
	 */
	protected $current_folder;

	/**
	 * To enable deleting of a mailbox user_home has to be set and be writable by webserver
	 *
	 * Supported placeholders are:
	 * - %d domain
	 * - %u username part of email
	 * - %s email address
	 *
	 * @var string
	 */
	var $user_home;	// = '/var/dovecot/imap/%d/%u';

	/**
	 * Constructor
	 *
	 * @param array $params
	 * @param bool|int|string $_adminConnection create admin connection if true or account_id or imap username
	 * @param int $_timeout =null timeout in secs, if none given fmail pref or default of 20 is used
	 * @return void
	 */
	public function __construct(array $params, $_adminConnection=false, $_timeout=null)
	{
		parent::__construct($params, $_adminConnection, $_timeout);

		$this->jmap_accountId = Api\Cache::getSession(__CLASS__, 'accountId:'.$this->acc_id);
		$this->jmap_states = Api\Cache::getSession(__CLASS__, 'states:'.$this->acc_id);
		$this->current_folder = Api\Cache::getSession(__CLASS__, 'currentFolder:'.$this->acc_id);
	}

	/**
	 * Separator for Stalwart master user: <username>%<master>
	 */
	const MASTER_SEPARATOR = '%';

	/**
	 * Ensure we use an admin connection
	 *
	 * Prefixes adminUsername with real username (separated by an asterisk)
	 *
	 * @param string $_username =true create an admin connection for given user or $this->acc_imap_username
	 */
	function adminConnection($_username=true)
	{
		// generate admin user name of $username
		if (($pos = strpos($this->acc_imap_admin_username, self::MASTER_SEPARATOR)) !== false)	// remove evtl. set username
		{
			$this->params['acc_imap_admin_username'] = substr($this->acc_imap_admin_username, $pos+1);
		}
		$this->params['acc_imap_admin_username'] = (is_string($_username) ? $_username : $this->acc_imap_username).
			self::MASTER_SEPARATOR.$this->params['acc_imap_admin_username'];

		parent::adminConnection($_username);
	}

	/**
	 * Create mailbox string from given mailbox-name and user-name
	 *
	 * Admin connection in Dovecot is always for a given user, we can simply use INBOX here.
	 *
	 * @param string $_username
	 * @param string $_folderName =''
	 * @return string utf-7 encoded (done in getMailboxName)
	 */
	function getUserMailboxString($_username, $_folderName='')
	{
		unset($_username);	// not used, but required by function signature

		$mailboxString = 'INBOX';

		if (!empty($_folderName))
		{
			$nameSpaces = $this->getNameSpaceArray();
			$mailboxString .= $nameSpaces['others'][0]['delimiter'] . $_folderName;
		}
		return $mailboxString;
	}

	/**
	 * Updates an account
	 *
	 * @param array $_hookValues only value for key 'account_lid' and 'new_passwd' is used
	 */
	function addAccount($_hookValues)
	{
		return $this->updateAccount($_hookValues);
	}

	/**
	 * Delete an account
	 *
	 * @param array $_hookValues only value for key 'account_lid' is used
	 */
	function deleteAccount($_hookValues)
	{
		return false;
	}

	/**
	 * Delete multiple (user-)mailboxes via a wildcard, eg. '%' for whole domain
	 *
	 * Domain is the configured domain and it uses the Cyrus admin user
	 *
	 * @return string $username='%' username containing wildcards, default '%' for all users of a domain
	 * @return int|boolean number of deleted mailboxes on success or false on error
	 */
	function deleteUsers($username='%')
	{
		return false;
	}

	/**
	 * returns information about a user
	 * currently only supported information is the current quota
	 *
	 * @param string $_username
	 * @return array userdata
	 */
	function getUserData($_username)
	{
		if (isset($this->username)) $bufferUsername = $this->username;
		if (isset($this->loginName)) $bufferLoginName = $this->loginName;
		$this->username = $this->loginName = $_username;

		// now disconnect to be able to reestablish the connection with the targetUser while we go on
		try
		{
			$this->adminConnection();
		}
		catch (\Exception $e)
		{
			// error_log(__METHOD__.__LINE__." Could not establish admin Connection!".$e->getMessage());
			unset($e);
			return array();
		}

		$userData = array();
		// we are authenticated with master but for current user
		if(($quota = $this->getStorageQuotaRoot('INBOX')))
		{
			$userData['quotaLimit'] = (int) ($quota['QMAX'] / 1024);
			$userData['quotaUsed'] = (int) ($quota['USED'] / 1024);
		}
		$this->username = $bufferUsername;
		$this->loginName = $bufferLoginName;
		$this->disconnect();

		//error_log(__METHOD__."('$_username') getStorageQuotaRoot('INBOX')=".array2string($quota).' returning '.array2string($userData));
		return $userData;
	}

	/**
	 * Set information about a user
	 * currently only supported information is the current quota
	 *
	 * Dovecot gets quota from it's user-db, but cant set it --> ignored
	 *
	 * @param string $_username
	 * @param int $_quota
	 * @return boolean
	 */
	function setUserData($_username, $_quota)
	{
		unset($_username); unset($_quota);	// not used, but required by function signature

		return true;
	}

	/**
	 * Updates an account
	 *
	 * @param array $_hookValues only value for key 'account_lid' and 'new_passwd' is used
	 */
	function updateAccount($_hookValues)
	{
		unset($_hookValues);	// not used, but required by function signature

		if(!$this->acc_imap_administration)
		{
			return false;
		}
		// mailbox get's automatic created with full rights for user
		return true;
	}
	/**
	 * Generate token / user-information for push to be stored by Dovecot
	 *
	 * The user information has the form "$account_id::$acc_id;$token@$host"
	 *
	 * @param null $account_id
	 * @param string $token =null default push token of instance ($account_id=='0') or user
	 * @return string
	 * @throws Api\Exception\AssertionFailed
	 */
	protected function pushToken($account_id=null, $token=null)
	{
		if (!isset($token)) $token = ((string)$account_id === '0' ? Tokens::instance() : Tokens::user($account_id));

		return $GLOBALS['egw_info']['user']['account_id'].'::'.$this->acc_id.';'.
			$token . '@' . Api\Header\Http::host();
	}

	/**
	 * @var Api\Mail\Jmap
	 */
	protected $jmap;

	/**
	 * Create or return a unique client id for push notifications
	 *
	 * @param int $acc_id
	 * @param int $account_id
	 * @param bool $create =false true: create a new client id if not found in cache, else return null
	 * @return array|string with values for keys "client_id", "acc_id", "account_id" and "sessionid" or just the client id as string
	 */
	protected static function jmapClientId(int $acc_id, int $account_id, bool $create = false)
	{
		if (!($ret = Api\Cache::getTree(__CLASS__, $location = $GLOBALS['egw_info']['server']['install_id'].'::'.$acc_id.':'.$account_id)) && $create ||
			// if we have a real user-session, update the sessionid, it might have changed, but keep client_id
			!empty($GLOBALS['egw']->session->sessionid) && !empty($GLOBALS['egw']->session->account_id) && $ret['sessionid'] !== $GLOBALS['egw']->session->sessionid)
		{
			Api\Cache::setTree(__CLASS__, $location, $ret = [
				'client_id' => $ret['client_id'] ?? Api\CalDAV::_new_uuid(),
				'acc_id' => $acc_id,
				'account_id' => $account_id,
				// we store the sessionid to be able to get the user-password, if needed
				'sessionid' => $GLOBALS['egw']->session->sessionid,
			]);
		}
		return $ret;
	}

	/**
	 * Return Jmap client
	 *
	 * @return JmapHttp
	 */
	public function jmapClient()
	{
		if (!isset($this->jmap))
		{
			$ssl = (int)$this->acc_imap_ssl;
			$undecided = ($ssl & Mail\Account::VERIFY_MASK) === Mail\Account::VERIFY_UNDECIDED;
			// curl (used for all JMAP HTTP calls, see RestClientTrait) verifies by default
			// already - unlike IMAP/SMTP/Sieve, an UNDECIDED account gets a real strict attempt
			// first, not an unverified one, so there's no separate raw-socket probe needed here
			$verify = $undecided || ($ssl & Mail\Account::VERIFY_MASK) === Mail\Account::VERIFY_ENABLED;
			try {
				$this->jmap = new JmapHttp($this->jmapUrl(), $this->acc_imap_username, $this->acc_imap_password, $this->jmap_accountId, $verify);
				if ($undecided && $this->acc_id)
				{
					// the strict connection just succeeded - verification confirmed possible
					Mail\Account::persistVerification($this->acc_id, 'acc_imap_ssl',
						($ssl & ~Mail\Account::VERIFY_MASK) | Mail\Account::VERIFY_ENABLED);
				}
			}
			catch (Api\Exception\Http $e) {
				// only a plausible certificate-verification failure on a still-undecided
				// account gets the fallback retry - any other failure (wrong credentials, host
				// down, ...) must still surface normally
				if (!$undecided || !preg_match('/certificate|ssl|tls/i', $e->getMessage()))
				{
					throw $e;
				}
				$this->jmap = new JmapHttp($this->jmapUrl(), $this->acc_imap_username, $this->acc_imap_password, $this->jmap_accountId, false);
				if ($this->acc_id)
				{
					Mail\Account::persistVerification($this->acc_id, 'acc_imap_ssl',
						($ssl & ~Mail\Account::VERIFY_MASK) | Mail\Account::VERIFY_DISABLED);
				}
			}
			// $this->jmap_accountId is passed as Mail\Jmap::__construct()'s by-reference $accountId
			// param, so bootstrap() resolving it (when it was previously empty) already mutated it
			// in-place above - it's no longer a live session reference itself, so persist explicitly
			$this->persist_jmap_state();
		}
		return $this->jmap;
	}

	/**
	 * Persist $jmap_accountId/$jmap_states/$current_folder (mutated in-place, no longer live
	 * session references) back to the session
	 */
	private function persist_jmap_state()
	{
		Api\Cache::setSession(__CLASS__, 'accountId:'.$this->acc_id, $this->jmap_accountId);
		Api\Cache::setSession(__CLASS__, 'states:'.$this->acc_id, $this->jmap_states);
		Api\Cache::setSession(__CLASS__, 'currentFolder:'.$this->acc_id, $this->current_folder);
	}

	/**
	 * Build the JMAP endpoint URL from acc_imap_host/acc_imap_ssl/acc_imap_port
	 *
	 * acc_imap_host is stored as a bare hostname - Mail\Jmap otherwise always defaults to
	 * https, so an explicit "JMAP (http)" protocol choice (Mail\Account::JMAP_HTTP) needs the
	 * scheme spelled out here to actually take effect for real (not just wizard-time) usage.
	 *
	 * @return string
	 */
	protected function jmapUrl() : string
	{
		// bare sentinel service-names Mail\Jmap's own constructor special-cases (the EGroupware
		// "mail" docker-compose service, and the "stalwart"/hosting-internal shortcuts) - must be
		// passed through UNCHANGED, never turned into eg. "https://mail", which is not a real,
		// resolvable host and broke acc_id=1 (found live 2026-08-24)
		if (in_array($this->acc_imap_host, ['mail', 'stalwart', 'internal.k8s.farm.egroupware.org'], true) ||
			preg_match('#^https?://#', $this->acc_imap_host))
		{
			return $this->acc_imap_host;
		}
		$scheme = ((int)$this->acc_imap_ssl & Mail\Account::PROTOCOL_MASK) === Mail\Account::JMAP_HTTP ? 'http' : 'https';
		$default_port = $scheme === 'http' ? 80 : 443;
		$port = $this->acc_imap_port && (int)$this->acc_imap_port !== $default_port ? ':'.(int)$this->acc_imap_port : '';
		return $scheme.'://'.$this->acc_imap_host.$port;
	}

	/**
	 * JMAP-native S/MIME resolution for Stalwart - fetches the raw message via a real JMAP Blob
	 * download (Mail\Jmap::downloadBlob(), the Email's own top-level blobId) instead of an IMAP
	 * FETCH, decrypts/verifies via the existing Mail\Smime::resolveMessage() (shared with
	 * JmapShim's local-shim equivalent), and renders via JmapImap::structureToHtml() - no
	 * mail_ui/Api\Mail\Imap IMAP connection involved at all for this path.
	 *
	 * @param string $emailId JMAP Email id
	 * @param string $topLevelType see Mail\Smime::resolveMessage()
	 * @param string $fromAddress
	 * @param string $htmlOptions
	 * @param string $passphrase
	 * @return array{body: string, smime: ?array} sanitized HTML body, plus the decrypt/verify
	 *  metadata (Mail\Smime::resolveMessage()'s 'X-EGroupware-Smime' convention) for the caller to
	 *  push to the client (app.mail.setSmimeFlags) - never sent to the client itself
	 * @throws Mail\Smime\PassphraseMissing
	 * @throws Api\Exception
	 */
	public function resolveSmimeJmap(string $emailId, string $topLevelType, string $fromAddress,
		string $htmlOptions='', string $passphrase='') : array
	{
		$client = $this->jmapClient();
		$email = $client->emailGet($emailId, ['blobId']);
		$raw = $client->downloadBlob($email['blobId'], 'message.eml', 'message/rfc822');
		$structure = Mail\Smime::resolveMessage($this->acc_id, $raw, $topLevelType, $passphrase, $fromAddress);
		return [
			'body' => JmapImap::structureToHtml($structure, $htmlOptions),
			'smime' => $structure->getMetadata('X-EGroupware-Smime'),
		];
	}

	/**
	 * JMAP-native TNEF resolution for Stalwart, for the case where the *entire* message is a
	 * TNEF/winmail.dat blob (see JmapImap::resolveTnef()'s docblock - same scope, IMAP shim
	 * equivalent). Downloads that one part's blob (its blobId, from the same "attachments" JMAP
	 * already returned) instead of an IMAP FETCH, decodes via the existing (now static, since it
	 * never touched $this/IMAP) Mail::tnef_decoder().
	 *
	 * @param string $emailId JMAP Email id
	 * @param string $partId the whole-message TNEF part's JMAP partId
	 * @param string $htmlOptions
	 * @return string sanitized HTML body
	 * @throws Api\Exception part not found, or TNEF decoding failed
	 */
	public function resolveTnefJmap(string $emailId, string $partId, string $htmlOptions='') : string
	{
		$client = $this->jmapClient();
		$email = $client->emailGet($emailId, ['attachments']);
		$attachment = current(array_filter($email['attachments'] ?? [], static fn($a) => ($a['partId'] ?? null) === $partId)) ?: null;
		if (!$attachment)
		{
			throw new Api\Exception("Part '$partId' not found on Email '$emailId'");
		}
		$raw = $client->downloadBlob($attachment['blobId'], $attachment['name'] ?? 'winmail.dat', $attachment['type'] ?? 'application/ms-tnef');
		$decoded = Mail::tnef_decoder($raw);
		if (!$decoded)
		{
			throw new Api\Exception('Could not decode TNEF data');
		}
		return JmapImap::structureToHtml($decoded, $htmlOptions);
	}

	/**
	 * MailboxRights (RFC 8621 §2 "myRights", writable per-principal via the mail:share
	 * extension's "shareWith") mapped to RFC 4314 IMAP ACL letters, so the existing ACL UI
	 * (mail_acl.inc.php/acl.xet) keeps working unchanged against JMAP-native (Stalwart)
	 * accounts too - see doc/ai memory "mail-jmap-acl-plan" for the full mapping rationale.
	 *
	 * JMAP has no separate rename vs delete mailbox right (both collapse onto 'x'), and no
	 * lookup-only right (mayReadItems covers both IMAP 'l' and 'r') - real granularity losses
	 * vs IMAP, not implementation gaps.
	 *
	 * @link https://www.rfc-editor.org/rfc/rfc8621#section-2 MailboxRights
	 * @link https://www.rfc-editor.org/rfc/rfc4314#section-2.1 IMAP ACL rights
	 */
	const JMAP_RIGHT_TO_IMAP = [
		'mayReadItems'   => 'lr',
		'mayAddItems'    => 'i',
		'mayRemoveItems' => 'te',
		'maySetSeen'     => 's',
		'maySetKeywords' => 'w',
		'mayCreateChild' => 'k',
		'mayRename'      => 'x',
		'mayDelete'      => 'x',
		'maySubmit'      => 'p',
		'mayShare'       => 'a',
	];

	/**
	 * Whether this account's Stalwart/JMAP session advertises the mail:share capability
	 * (draft-ietf-jmap-mail-sharing) - if not, ACL falls back to classic IMAP
	 * GETACL/SETACL/DELETEACL (eg. older Stalwart versions without the extension enabled).
	 *
	 * @return bool
	 */
	public function mailShareSupported() : bool
	{
		return array_key_exists(JmapHttp::JMAP_MAIL_SHARE, $this->jmapClient()->accountCapabilities ?? []);
	}

	/**
	 * @param array $rights MailboxRights, eg. from Mailbox/get's myRights or a shareWith entry
	 * @return string RFC 4314 IMAP ACL letters
	 */
	protected function jmapRightsToImap(array $rights) : string
	{
		$letters = '';
		foreach (self::JMAP_RIGHT_TO_IMAP as $right => $imapLetters)
		{
			if (!empty($rights[$right]))
			{
				$letters .= $imapLetters;
			}
		}
		return $letters;
	}

	/**
	 * @param string $imapRights RFC 4314 IMAP ACL letters
	 * @return array full MailboxRights object (every key set true/false, as required for a
	 *  shareWith entry - JMAP has no "add"/"remove" patch semantics for individual rights)
	 */
	protected function imapRightsToJmap(string $imapRights) : array
	{
		$letters = str_split($imapRights);
		$rights = [];
		foreach (self::JMAP_RIGHT_TO_IMAP as $right => $imapLetters)
		{
			$rights[$right] = (bool)array_intersect(str_split($imapLetters), $letters);
		}
		return $rights;
	}

	/**
	 * Resolve a JMAP Principal id to its email address (the identifier mail_acl.inc.php /
	 * getMailBoxUserName() otherwise use for this account, which uses loginType 'email')
	 *
	 * @ToDo verify the exact Principal object shape (property name(s)) against a live Stalwart
	 *  session - spec reading alone wasn't enough to confirm this, see doc/ai memory
	 *  "mail-jmap-acl-plan"
	 * @param string $principalId
	 * @return ?string null if not found
	 */
	protected function jmapPrincipalEmail(string $principalId) : ?string
	{
		static $cache = [];
		if (!array_key_exists($principalId, $cache))
		{
			$response = $this->jmapClient()->jmapCall([
				['Principal/get', [
					'ids' => [$principalId],
					'properties' => ['email'],
				], '0'],
			], [JmapHttp::JMAP_CORE, JmapHttp::JMAP_PRINCIPALS]);
			$cache[$principalId] = $response['methodResponses'][0][1]['list'][0]['email'] ?? null;
		}
		return $cache[$principalId];
	}

	/**
	 * Resolve an email address to its JMAP Principal id (shareWith's key, NOT the IMAP
	 * username/email itself)
	 *
	 * @ToDo verify the exact Principal/query filter shape against a live Stalwart session -
	 *  spec reading alone wasn't enough to confirm this, see doc/ai memory "mail-jmap-acl-plan"
	 * @param string $email
	 * @return ?string null if not found
	 */
	protected function jmapPrincipalId(string $email) : ?string
	{
		static $cache = [];
		if (!array_key_exists($email, $cache))
		{
			$response = $this->jmapClient()->jmapCall([
				['Principal/query', [
					'filter' => ['email' => $email],
				], '0'],
			], [JmapHttp::JMAP_CORE, JmapHttp::JMAP_PRINCIPALS]);
			$cache[$email] = $response['methodResponses'][0][1]['ids'][0] ?? null;
		}
		return $cache[$email];
	}

	/**
	 * JMAP-native getACL() - Mailbox myRights/shareWith instead of IMAP GETACL, for accounts
	 * whose session advertises mail:share. Falls back to the classic IMAP implementation
	 * (inherited from Horde_Imap_Client_Base) otherwise.
	 *
	 * Unlike classic IMAP GETACL, shareWith never contains the mailbox owner's own entry (JMAP
	 * has no concept of an explicit owner ACL row) - mail_acl.inc.php's owner-readonly handling
	 * simply finds no matching row, which is a UI nuance (no owner row shown), not a functional
	 * break.
	 *
	 * @param mixed $mailbox a mailbox, string (UTF-8)
	 * @return \Horde_Imap_Client_Data_Acl[]|false identifiers (emails) as keys - same shape
	 *  parent::getACL() returns, so mail_acl.inc.php needs no changes to consume either
	 */
	public function getACL($mailbox)
	{
		if (!$this->mailShareSupported())
		{
			return parent::getACL($mailbox);
		}
		$client = $this->jmapClient();
		if (!($mailboxId = $client->getMailboxId((string)$mailbox)))
		{
			return false;
		}
		$response = $client->jmapCall([
			['Mailbox/get', [
				'accountId' => $client->accountId,
				'ids' => [$mailboxId],
				'properties' => ['myRights', 'shareWith'],
			], '0'],
		], JmapHttp::JMAP_MAIL);
		if (!($mbox = $response['methodResponses'][0][1]['list'][0] ?? null))
		{
			return false;
		}
		$acls = [];
		foreach ((array)($mbox['shareWith'] ?? []) as $principalId => $rights)
		{
			if (($email = $this->jmapPrincipalEmail($principalId)))
			{
				$acls[$email] = new \Horde_Imap_Client_Data_Acl($this->jmapRightsToImap($rights));
			}
		}
		return $acls;
	}

	/**
	 * JMAP-native setACL() - patches Mailbox shareWith[principalId] instead of IMAP SETACL, for
	 * accounts whose session advertises mail:share. Falls back to the classic IMAP
	 * implementation otherwise.
	 *
	 * @param mixed $mailbox a mailbox, string (UTF-8)
	 * @param string $identifier the identifier to alter - an email address (UTF-8), resolved to
	 *  a Principal id via jmapPrincipalId()
	 * @param array $options see parent::setACL()
	 * @throws \Horde_Imap_Client_Exception mailbox/principal not found, or on any JMAP error
	 */
	public function setACL($mailbox, $identifier, $options)
	{
		if (!$this->mailShareSupported())
		{
			parent::setACL($mailbox, $identifier, $options);
			return;
		}
		if (empty($options['rights']))
		{
			$this->deleteACL($mailbox, $identifier);
			return;
		}
		$client = $this->jmapClient();
		if (!($mailboxId = $client->getMailboxId((string)$mailbox)))
		{
			throw new \Horde_Imap_Client_Exception("Mailbox '$mailbox' not found");
		}
		if (!($principalId = $this->jmapPrincipalId($identifier)))
		{
			throw new \Horde_Imap_Client_Exception("No JMAP principal found for '$identifier'");
		}
		$rights = ($options['rights'] instanceof \Horde_Imap_Client_Data_Acl)
			? $options['rights']
			: new \Horde_Imap_Client_Data_Acl(strval($options['rights']));
		$response = $client->jmapCall([
			['Mailbox/set', [
				'accountId' => $client->accountId,
				'update' => [
					$mailboxId => [
						'shareWith/'.$principalId => $this->imapRightsToJmap(
							$rights->getString(\Horde_Imap_Client_Data_AclCommon::RFC_4314)),
					],
				],
			], '0'],
		], [...JmapHttp::JMAP_MAIL, JmapHttp::JMAP_MAIL_SHARE]);
		if (!empty($response['methodResponses'][0][1]['notUpdated'][$mailboxId]))
		{
			throw new \Horde_Imap_Client_Exception('Mailbox/set shareWith failed: '.
				json_encode($response['methodResponses'][0][1]['notUpdated'][$mailboxId]));
		}
	}

	/**
	 * JMAP-native deleteACL() - clears Mailbox shareWith[principalId] instead of IMAP DELETEACL,
	 * for accounts whose session advertises mail:share. Falls back to the classic IMAP
	 * implementation otherwise.
	 *
	 * @param mixed $mailbox a mailbox, string (UTF-8)
	 * @param string $identifier the identifier to delete - an email address (UTF-8), resolved
	 *  to a Principal id via jmapPrincipalId()
	 * @throws \Horde_Imap_Client_Exception mailbox not found, or on any JMAP error
	 */
	public function deleteACL($mailbox, $identifier)
	{
		if (!$this->mailShareSupported())
		{
			parent::deleteACL($mailbox, $identifier);
			return;
		}
		$client = $this->jmapClient();
		if (!($mailboxId = $client->getMailboxId((string)$mailbox)))
		{
			throw new \Horde_Imap_Client_Exception("Mailbox '$mailbox' not found");
		}
		if (!($principalId = $this->jmapPrincipalId($identifier)))
		{
			return;	// nothing to delete
		}
		$response = $client->jmapCall([
			['Mailbox/set', [
				'accountId' => $client->accountId,
				'update' => [
					$mailboxId => [
						'shareWith/'.$principalId => null,
					],
				],
			], '0'],
		], [...JmapHttp::JMAP_MAIL, JmapHttp::JMAP_MAIL_SHARE]);
		if (!empty($response['methodResponses'][0][1]['notUpdated'][$mailboxId]))
		{
			throw new \Horde_Imap_Client_Exception('Mailbox/set shareWith removal failed: '.
				json_encode($response['methodResponses'][0][1]['notUpdated'][$mailboxId]));
		}
	}

	/**
	 * JMAP push subscription types to request
	 */
	const SUBSCRIBTION_TYPES = ['Email', 'Mailbox'];

	/**
	 * Enable push notifications for the current connection and given account_id
	 *
	 * @param ?int $account_id =null 0=everyone on the instance
	 * @param ?string $acc_id_folder current acc_id and folder, ::-delimited
	 * @return bool true on success, false on failure
	 */
	function enablePush(?int $account_id=null, ?string $acc_id_folder=null)
	{
		try {
			if (!$this->jmap) $this->jmap = $this->jmapClient();
			$client_id = $this->jmapClientId($this->acc_id, $account_id ?: $GLOBALS['egw_info']['user']['account_id'], true)['client_id'];
			$expires = new Api\DateTime('+2days', new \DateTimeZone('UTC'));
			if (!($subscriptions=array_values(array_filter($this->jmap->getPushSubscriptions()['list']??[],
				static function(array $pushSubscription) use ($client_id)
			{
				return $pushSubscription['deviceClientId'] === $client_id;
			}))))
			{
				$url = Api\Framework::getUrl(Api\Framework::link('/api/jmapPush.php', [
					'acc_id' => $this->acc_id,
					'account_id' => $account_id ?: $GLOBALS['egw_info']['user']['account_id'],
				]));
				$subscription_id = $this->jmap->createPushSubscription($client_id, $url, self::SUBSCRIBTION_TYPES, $expires, $this->jmap_sessionState)['id'];
			}
			// check the subscription is about to expire --> renew/extend it
			elseif ((new Api\DateTime($subscriptions[0]['expires'])) < (new Api\DateTime('+1day')))
			{
				$this->jmap->updatePushSubscription($subscriptions[0]['id'], [
					'expires' => $expires->format(JmapHttp::DATETIME_UTC_FORMAT),
				]);
			}

			// get states to calculate changes
			$this->jmap_states[$this->jmap_accountId] = $this->jmap->getStates(
				$this->current_folder = explode('::', $acc_id_folder)[1] ?? 'INBOX',
				$this->jmap_accountId
			);
			$this->persist_jmap_state();
		}
		catch (\Exception $e) {
			_egw_log_exception($e);
			return false;
		}
		return true;
	}

	/**
	 * Callback for push subscriptions (/api/jmapPush.php)
	 *
	 * We're currently emulating IMAP/Dovecot push events.
	 *
	 * @return void
	 * @throws Api\Exception
	 * @throws Api\Exception\NotFound
	 * @throws \JsonException
	 */
	public static function pushCallback()
	{
		$data = json_decode(file_get_contents('php://input'), true, 10, JSON_THROW_ON_ERROR);
		if (!($client_data = self::jmapClientId($_GET['acc_id'], $_GET['account_id'])))
		{
			throw new Api\Exception\NotFound('deviceClientId not found!');
		}
		if (empty($client_data['sessionid']))
		{
			throw new Api\Exception('No sessionid!');
		}
		// validating the session but must NOT check the IP, as call is from Stalwart not client/browser!
		unset($GLOBALS['egw_info']['server']['sessions_checkip']);
		if (!$GLOBALS['egw']->session->verify($client_data['sessionid']))
		{
			// session that registered this push has since expired - unsubscribe instead of erroring on
			// every future retry (Stalwart otherwise keeps calling us until the subscription itself
			// expires); best-effort only, quietly log instead of throwing to avoid error-log noise
			try
			{
				$jmap = Mail\Account::read($client_data['acc_id'], $client_data['account_id'])->imapServer()->jmapClient();
				$subscription = current(array_filter($jmap->getPushSubscriptions()['list'] ?? [],
					static fn($s) => $s['deviceClientId'] === $client_data['client_id'])) ?: null;
				if ($subscription)
				{
					$jmap->destroyPushSubscription($subscription['id']);
				}
				error_log(__METHOD__."() sessionid expired for acc_id={$client_data['acc_id']}, account_id={$client_data['account_id']}".
					($subscription ? ', unsubscribed push' : ', no matching subscription found'));
			}
			catch (\Exception $e)
			{
				error_log(__METHOD__."() sessionid expired for acc_id={$client_data['acc_id']}, account_id={$client_data['account_id']}".
					', failed to unsubscribe push: '.$e->getMessage());
			}
			return;
		}
		// finish the request towards the mail server but continue processing it
		if (function_exists('fastcgi_finish_request'))
		{
			fastcgi_finish_request();
		}
		$mail_account = Mail\Account::read($client_data['acc_id'], $client_data['account_id']);
		// go through imapServer()->jmapClient() (not a plain "new JmapHttp(...)"), so accounts using
		// Imap\Stalwart authenticate with a cached Bearer token instead of checking the password again
		$stalwart = $mail_account->imapServer();
		$jmap = $stalwart->jmapClient();
		$old_states = Api\Cache::getSession(__CLASS__, 'states:'.$mail_account->acc_id);
		$sessionState = Api\Cache::getSession(__CLASS__, 'sessionState:'.$mail_account->acc_id);
		$currentFolder = Api\Cache::getSession(__CLASS__, 'currentFolder:'.$mail_account->acc_id);
		//error_log(__METHOD__."() client_data=".json_encode($client_data).", old_states=".json_encode($old_states).", current_folder='$currentFolder', data=".json_encode($data));
		switch($data['@type'])
		{
			case 'PushVerification':
				$jmap->updatePushSubscription($data['pushSubscriptionId'], [
					'verificationCode' => $data['verificationCode'],
				], $sessionState);
				break;
			case 'StateChange':
				// new mail: EmailDelivery, Email, Mailbox, Thread
				// flag change: Email, Mailbox
				// change is an object with possible attributes: EMail, EmailDelivery, Mailbox, Thread, ...
				foreach($data['changed'] as $accountId => $states)
				{
					$changes = $jmap->getChanges($accountId, array_combine(array_keys($states), array_map(static function($state, $name) use($old_states, $accountId)
					{
						return $old_states[$accountId][$name] ?? null;
					}, $states, array_keys($states))), $currentFolder, $sessionState);
					//error_log(__METHOD__."() data=".json_encode($data)." --> changes=".json_encode($changes));
				}
				// NOTE: was previously checking non-existent "email-deleted"/"mailbox-deleted" keys
				// (typo for "-destroyed", itself gone now - see getChanges()) - always vacuously
				// true, so this never actually short-circuited anything; fixed to check the real
				// "destroyed" id lists too, now that they can produce a push on their own.
				if (empty($changes['email-created']['list']) && empty($changes['email-updated']['list']) && empty($changes['email-changes']['destroyed']) &&
					empty($changes['mailbox-created']['list']) && empty($changes['mailbox-updated']['list']) && empty($changes['mailbox-changes']['destroyed']))
				{
					break;  // no change or nothing we're interested in
				}
				$push_payload = [];
				foreach($changes as $type => $change)
				{
					if (empty($change['list'])) continue;
					[$what, $type] = explode('-', $type);   // "mailbox-created", "email-updated", ...
					foreach($change['list'] ?? [] as $i => $item)
					{
						if ($what !== 'email' && $what !== 'mailbox')
						{
							continue;
						}
						$folderId = $what === 'email' ? key($item['mailboxIds']) : $item['id'];
						try
						{
							// only needed for acl.folder (badge/special-folder use, see below) - the
							// row id itself uses $folderId directly, see $id below
							$folder = $jmap->folderId2path($folderId);
						}
						catch (\Horde_Imap_Client_Exception $e)
						{
							// the mailbox behind this push item's mailboxId/folderId is not (or no longer)
							// a real, selectable IMAP mailbox (eg. a JMAP-only virtual mailbox like Outbox,
							// or a genuine delete/rename race) - skip just this item, not the whole batch
							error_log(__METHOD__."() skipping $what-$type change[list][$i]=".json_encode($item).
								", folderId=$folderId: ".$e->getMessage());
							continue;
						}
						// native JMAP row-id shape (mail_ui::generateJmapRowID()): account::acc_id::
						// folderId::emailId - NOT the classic account::acc_id::base64(folder)::uid shape
						// - this is what NextMatch's nm.refresh() looks the row up by, and Stalwart rows
						// are cached client-side under the native JMAP shape (see
						// mail-jmap-modernization.md's "Row-id scheme"; mirrors MailJmap.
						// buildWsPushPayload()'s client-side equivalent of this same code)
						$id = $client_data['account_id'].'::'.$client_data['acc_id'].'::'.$folderId;
						// check if we can combine with the last change into a single push
						if (!isset($push) || !str_starts_with($push['id'], $id))
						{
							if (isset($push))
							{
								$push_payload[] = $push;
							}
							$push = [
								'app' => 'mail',
								'id' => $id,
								'acl' => [
									'folder' => $folder,
								]
							];
						}
						if ($what === 'email')
						{
							$push['id'] = $id.'::'.$item['id'];
							switch ($type)
							{
								case 'created':
									$push['type'] = 'add';
									$push['acl']['event'] = 'MessageNew';
									$push['acl']['from'] = empty($item['from'][0]['name']) ? $item['from'][0]['email'] :
										$item['from'][0]['name'] . ' <' . $item['from'][0]['email'] . '>';
									$push['acl']['subject'] = $item['subject'];
									$push['acl']['snippet'] = trim($item['preview']);
									break;
								case 'updated':
									$push['type'] = 'update';
									// as we can't figure out the old flags, we send a new event "Flags" with the currently set flags
									$push['acl']['event'] = 'Flags';
									$push['acl']['flags'] = array_keys($item['keywords'] ?? []);
									break;
							}
						}
						else    // mailbox
						{
							switch ($type)
							{
								case 'created':
									$push['type'] = 'add';
									break;
								case 'updated':
									$push['type'] = 'update';
									$push['acl']['unseen'] = $item['unreadEmails'];
									break;
							}
						}
						//error_log(__METHOD__."() $what-$type change[list][$i]=".json_encode($item).' --> push='.json_encode($push));
					}
				}
				if (isset($push))
				{
					$push_payload[] = $push;
				}
				// "destroyed" ids come straight from the *-changes responses above (see
				// Api\Mail\Jmap::getChanges()'s own comments for why there's no "*-destroyed"
				// Foo/get call to loop over instead). A destroyed email's folder can never be
				// resolved via JMAP after the fact - MailApp.push() (mail/js/app.ts) resolves it
				// client-side instead, via a wildcard egw.data search (email ids are unique per
				// account), matching MailJmap.buildEmailDeletePush()'s WS-push equivalent.
				foreach($changes['email-changes']['destroyed'] ?? [] as $emailId)
				{
					$push_payload[] = [
						'app' => 'mail',
						'id' => $client_data['account_id'].'::'.$client_data['acc_id'].'::*::'.$emailId,
						'type' => 'delete',
						'acl' => [],
					];
				}
				// A destroyed mailbox's path, unlike an email's, is at least *sometimes* still
				// resolvable here (no per-client egw.data cache to fall back on server-side) -
				// try, and skip (same as any other stale-folder race) if it no longer resolves.
				foreach($changes['mailbox-changes']['destroyed'] ?? [] as $folderId)
				{
					try
					{
						$folder = $jmap->folderId2path($folderId);
					}
					catch (\Horde_Imap_Client_Exception $e)
					{
						continue;
					}
					$push_payload[] = [
						'app' => 'mail',
						'id' => $client_data['account_id'].'::'.$client_data['acc_id'].'::'.$folderId,
						'type' => 'delete',
						'acl' => ['folder' => $folder],
					];
				}
				$push = new Api\Json\Push($client_data['account_id']);
				$push->call('egw.push', $push_payload);
				break;
			case 'EmailDelivery':   // extension is not supported
			default:
				error_log(__METHOD__.' Unknown push type: '.json_encode($data));
		}
	}

	/**
	 * Reimplemented to push UIDs of deleted mails, as we can't get their UIDs after they have been deleted :(
	 *
	 * This will only help if the same user is logged in on multiple devices.
	 *
	 * @param string $mailbox
	 * @param array $options values for keys "add", "remove", "ids", see parent class
	 * @return \Horde_Imap_Client_Ids
	 * @return \Horde_Imap_Client_Ids
	 * @throws \Horde_Imap_Client_Exception
	 * @throws \Horde_Imap_Client_Exception_NoSupportExtension
	 */
	public function store($mailbox, array $options = array())
	{
		if (isset($options['add']) && $options['add'] == ['\\Deleted'] && is_a($options['ids'], \Horde_Imap_Client_Ids::class) &&
			($uids = $options['ids']->ids))
		{
			$push = new Api\Json\Push($GLOBALS['egw_info']['user']['account_id']);
			$push->apply('egw.push', [[
				'app' => 'mail',
				'id'  => array_map(function($uid) use($mailbox)
					{
						return $GLOBALS['egw_info']['user']['account_id'].'::'.$this->acc_id.'::'.base64_encode($mailbox).'::'.$uid;
					}, $uids),
				'type' => 'delete',
				'acl'  => [
					'folder' => $mailbox,
				],
			]]);
		}
		return parent::store($mailbox, $options);
	}

	/**
	 * Reimplemented to push UIDs of moved mails, as we can't get their UIDs after they have been moved :(
	 *
	 * This will only help if the same user is logged in on multiple devices.
	 *
	 * @param string $source
	 * @param string $dest
	 * @param array $options values for keys "move", "ids", see parent class
	 * @return \Horde_Imap_Client_Ids
	 * @return \Horde_Imap_Client_Ids
	 * @throws \Horde_Imap_Client_Exception
	 * @throws \Horde_Imap_Client_Exception_NoSupportExtension
	 */
	public function copy($source, $dest, array $options = array())
	{
		if (!empty($options['move']) && is_a($options['ids'], \Horde_Imap_Client_Ids::class) &&
			($uids = $options['ids']->ids))
		{
			$push = new Api\Json\Push($GLOBALS['egw_info']['user']['account_id']);
			$push->apply('egw.push', [[
				'app' => 'mail',
				'id'  => array_map(function($uid) use($source)
				{
					return $GLOBALS['egw_info']['user']['account_id'].'::'.$this->acc_id.'::'.base64_encode($source).'::'.$uid;
				}, $uids),
				'type' => 'delete',
				'acl' => [
					'folder' => $source,
				]
			]]);
		}
		return parent::copy($source, $dest, $options);
	}

	/**
	 * Convert Stalwart/JMAP ID to IMAP UID
	 *
	 * Using Message-ID indexing of headers to be switched on in Stalwart v0.16: Settings > Search in WebUI
	 *
	 * @param string $emailId JMAP ID
	 * @param string $messageId Message-ID header, used only if NO Horde_Imap_Client_Search_Query->emailIds()
	 * @param string $folderId
	 * @param string|null &$folder =null folder name on return
	 * @return ?int
	 * @throws \Horde_Imap_Client_Exception
	 */
	protected function emailId2uid(string $emailId, string $messageId, string $folderId, ?string &$folder=null)
	{
		$query = new \Horde_Imap_Client_Search_Query();
		if (method_exists($query, 'emailIds'))
		{
			$query->emailIds($emailId);
		}
		else
		{
			$query->headerText('Message-ID', $messageId);
		}
		foreach($this->search($folder=$this->jmapClient()->folderId2path($folderId), $query) as $uid)
		{
			return (int)(string)$uid ?: null;    // casting direct to (int) does NOT work / gives always 1!
		}
		return null;
	}

	/**
	 * Resolve a JMAP Email.id to a real IMAP UID, given the folder as a real IMAP path (not a
	 * JMAP mailbox id, unlike emailId2uid()) - used by Api\Mail's JMAP-native method overrides
	 * (see Mail.php's jmapResolveUid()) when their JMAP-native attempt bails to the classic
	 * implementation for a reason unrelated to the id itself (a sub-part request, a text/calendar
	 * part, a TNEF attachment, ...) - the classic body needs a real UID to do anything useful
	 * with, since Horde_Imap_Client_Ids silently treats a non-numeric id as an empty id set
	 * rather than erroring, which would otherwise make the "fallback" silently return wrong
	 * (empty) data instead of a real fallback result.
	 *
	 * @param string $emailId
	 * @param string $folderPath real IMAP folder path e.g. "INBOX/Sent"
	 * @return ?int real IMAP UID, or null if not found
	 */
	public function emailId2uidByPath(string $emailId, string $folderPath) : ?int
	{
		$query = new \Horde_Imap_Client_Search_Query();
		if (!method_exists($query, 'emailIds'))
		{
			return null;	// no Message-ID fallback available here (no $messageId at this point)
		}
		$query->emailIds($emailId);
		foreach ($this->search($folderPath, $query) as $uid)
		{
			return (int)(string)$uid ?: null;
		}
		return null;
	}

	/**
	 * Resolve the folder+uid tail of a row-id (see Api\Mail::splitRowID(), the caller).
	 *
	 * A numeric $uid means this row was produced by this account's classic ajax_get_rows()
	 * fallback (still real IMAP UIDs, same as any other IMAP account) - handle it exactly like
	 * the base class. Otherwise $uid is one of Stalwart's own opaque JMAP Email ids, resolved
	 * via emailId2uid().
	 *
	 * Known limitation, not a regression (this row-id shape isn't resolvable at all today): a
	 * bare row-id carries no Message-ID header value, so this only succeeds when the IMAP
	 * backend supports the EMAILID search extension - without it, emailId2uid()'s Message-ID
	 * fallback searches for an empty header and (safely) returns null, same "not found" handling
	 * every splitRowID() caller already has for a missing/stale row.
	 *
	 * @param string $folder JMAP Mailbox id, or '' if not present in the row-id
	 * @param string $uid JMAP Email id (or numeric IMAP UID for classic-fallback rows), or '' if not present
	 * @return RowIdParts with values for keys "folder", "msgUID", "folderID", "emailID", "is_jmap" -
	 *  "folder"/"msgUID" are only actually resolved (real IMAP EMAILID search via emailId2uid())
	 *  the first time either is read - many callers only need "emailID"/"folderID" and never touch
	 *  the numeric UID at all, so they no longer pay for that search
	 */
	public function splitRowID(string $folder, string $uid) : Mail\RowIdParts
	{
		if ($uid === '' || is_numeric($uid))
		{
			return parent::splitRowID($folder, $uid);
		}
		return new Mail\RowIdParts(['folderID' => $folder, 'emailID' => $uid, 'is_jmap' => true], function() use ($folder, $uid)
		{
			$realFolder = null;
			$realUid = $this->emailId2uid($uid, '', $folder, $realFolder);
			return [
				'folder' => $realFolder,
				'msgUID' => $realUid !== null ? (string)$realUid : null,
			];
		});
	}

	/**
	 * Check if push is available / configured for given server
	 *
	 * @return bool
	 */
	function pushAvailable()
	{
		return true;
	}

	/**
	 * JMAP-FALLTHROUGH-GUARD (see [[project_jmap_imap_fallthrough_cleanup]]):
	 * JMAP has no IMAP CAPABILITY concept - without this override, any hasCapability() call
	 * falls through to Horde_Imap_Client_Socket's raw-socket capability query (Mail\Imap::
	 * hasCapability() calls examineMailbox()/capability(), neither overridden here), which hangs
	 * for the full connect timeout against a JMAP(S) endpoint instead of a real IMAP server -
	 * found live 2026-08-24 (mail_ui::index()/get_actions()/get_tree_actions() all query
	 * 'SUPPORTS_KEYWORDS' unconditionally, several times, none of them JMAP-aware).
	 *
	 * @param string $capability
	 * @return bool
	 */
	function hasCapability($capability)
	{
		switch ($capability)
		{
			// JMAP natively supports arbitrary keywords/flags, unlike classic IMAP which needs
			// this specific extension
			case 'SUPPORTS_KEYWORDS':
				return true;
		}
		return false;
	}
}