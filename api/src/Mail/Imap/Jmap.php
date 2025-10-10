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
	const DESCRIPTION = 'Stalwart';
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
	 * @var string current folder, stored in session and updated by mail_ui::get_rows() calling self::enablePush()
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

		$this->jmap_accountId =& Api\Cache::getSession(__CLASS__, 'accountId:'.$this->acc_id);
		$this->jmap_states =& Api\Cache::getSession(__CLASS__, 'states:'.$this->acc_id);
		$this->current_folder =& Api\Cache::getSession(__CLASS__, 'currentFolder:'.$this->acc_id);
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
	 * @return Mail\Jmap
	 */
	public function jmapClient()
	{
		return $this->jmap ?? ($this->jmap = new Mail\Jmap($this->acc_imap_host, $this->acc_imap_username, $this->acc_imap_password, $this->jmap_accountId));
	}

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
			if (!array_filter($this->jmap->getPushSubscriptions()['list']??[], static function(array $pushSubscription) use ($client_id)
			{
				return $pushSubscription['deviceClientId'] === $client_id && !empty($pushSubscription['verificationCode']);
			}))
			{
				$url = Api\Framework::getUrl(Api\Framework::link('/api/jmapPush.php', [
					'acc_id' => $this->acc_id,
					'account_id' => $account_id ?: $GLOBALS['egw_info']['user']['account_id'],
				]));
				$subscription_id = $this->jmap->createPushSubscription($client_id, $url, null, null, $this->jmap_sessionState)['id'];
			}

			// get states to calculate changes
			$this->jmap_states[$this->jmap_accountId] = $this->jmap->getStates(
				$this->current_folder = explode('::', $acc_id_folder)[1] ?? 'INBOX',
				$this->jmap_accountId
			);
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
			throw new Api\Exception('Invalid sessionid!');
		}
		// finish the request towards the mail server but continue processing it
		if (function_exists('fastcgi_finish_request'))
		{
			fastcgi_finish_request();
		}
		$mail_account = Mail\Account::read($client_data['acc_id'], $client_data['account_id']);
		$jmap = new Mail\Jmap($mail_account->acc_imap_host, $mail_account->acc_imap_username, $mail_account->acc_imap_password,
			Api\Cache::getSession(__CLASS__, 'accountId:'.$mail_account->acc_id));
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
				if (empty($changes['email-created']['list']) && empty($changes['email-updated']['list']) && empty($changes['email-deleted']['list']) &&
					empty($changes['mailbox-created']['list']) && empty($changes['mailbox-updated']['list']) && empty($changes['mailbox-deleted']['list']))
				{
					break;  // no change or nothing we're interested in
				}
				$stalwart = $mail_account->imapServer();
				$stalwart->jmap = $jmap;
				$push_payload = [];
				foreach($changes as $type => $change)
				{
					if (empty($change['list'])) continue;
					[$what, $type] = explode('-', $type);   // "mailbox-created", "email-updated", ...
					foreach($change['list'] ?? [] as $i => $item)
					{
						if ($what === 'email')
						{
							$uid = $stalwart->messageId2uid($item['messageId'][0], key($item['mailboxIds']), $folder);
						}
						elseif ($what === 'mailbox')
						{
							$folder = $jmap->folderId2path($item['id']);
						}
						else
						{
							continue;
						}
						$id = $client_data['account_id'].'::'.$client_data['acc_id'].'::'.base64_encode($folder);
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
							$push['id'] = $id.'::'.$uid;
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
								case 'destroyed':
									$push['type'] = 'delete';
									$push['acl']['event'] = 'MessageDeleted';   // no used, not sure about Dovecot/Imap event-name
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
								case'destroyed':
									$push['type'] = 'delete';
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
				$push = new Api\Json\Push($client_data['account_id']);
				$push->apply('egw.push', $push_payload);
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
	 * Convert Message-ID to IMAP UID
	 *
	 * @param string $messageId
	 * @param string $folderId
	 * @param string|null &$folder =null folder name on return
	 * @return ?int
	 */
	protected function messageId2uid($messageId, string $folderId, ?string &$folder=null)
	{
		$query = new \Horde_Imap_Client_Search_Query();
		$query->headerText('Message-ID', $messageId);
		foreach($this->search($folder=$this->jmap->folderId2path($folderId), $query) as $uid)
		{
			return (string)$uid;    // casting to (int) does NOT work / gives always 1!
		}
		return null;
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
}