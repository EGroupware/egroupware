<?php
/**
 * EGroupware Api: Support for Cyrus IMAP
 *
 * @link http://www.stylite.de
 * @package api
 * @subpackage mail
 * @author Ralf Becker <rb@stylite.de>
 * @author Klaus Leithoff <kl@stylite.de>
 * @author Lars Kneschke
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

namespace EGroupware\Api\Mail\Imap;

use EGroupware\Api\Mail;

use Horde_Imap_Client_Exception;

/**
 * Manages connection to Cyrus IMAP server
 */
class Cyrus extends Mail\Imap
{
	/**
	 * Label shown in EMailAdmin
	 */
	const DESCRIPTION = 'Cyrus';

	/**
	 * Capabilities of this class (pipe-separated): default, sieve, admin, logintypeemail
	 */
	const CAPABILITIES = 'default|sieve|timedsieve|admin|logintypeemail';

	/**
	 * prefix for groupnames, when using groups in ACL Management
	 */
	const ACL_GROUP_PREFIX = 'group:';

	// mailbox delimiter
	var $mailboxDelimiter = '.';

	// mailbox prefix
	var $mailboxPrefix = '';

	/**
	 * Updates an account
	 *
	 * @param array $_hookValues only value for key 'account_lid' and 'account_passwd' is used
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
		// some precaution to really delete just _one_ account
		if (strpos($_hookValues['account_lid'],'%') !== false ||
			strpos($_hookValues['account_lid'],'*') !== false)
		{
			return false;
		}
		return !!$this->deleteUsers($_hookValues['account_lid']);
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
		if(!$this->acc_imap_administration || empty($username))
		{
			return false;
		}

		// we need a admin connection
		$this->adminConnection();

		$mailboxName = $this->getUserMailboxString($username);

		try {
			$mboxes = (array)$this->getMailboxes($mailboxName, 1);
			//error_log(__METHOD__."('$username') getMailboxes('$reference','$username$restriction') = ".array2string($mboxes));

			foreach(array_keys($mboxes) as $mbox)
			{
				// give the admin account the rights to delete this mailbox
				$this->setACL($mbox, $this->acc_imap_admin_username, array('rights' => 'aeiklprstwx'));
				$this->deleteMailbox($mbox);
			}
		}
		catch(Horde_Imap_Client_Exception $e) {
			_egw_log_exception($e);
			$this->disconnect();
			return false;
		}
		$this->disconnect();

		return count($mboxes);
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
		// no need to switch to admin-connection for reading quota of current user
		if ($_username !== $GLOBALS['egw_info']['user']['account_lid']) $this->adminConnection();
		$userData = array();

		if(($quota = $this->getQuotaByUser($_username,'ALL')))
		{
			$userData['quotaLimit'] = (int)($quota['limit'] / 1024);
			$userData['quotaUsed'] = (int)($quota['usage'] / 1024);
		}
		//error_log(__LINE__.': '.__METHOD__."('$_username') quota=".array2string($quota).' returning '.array2string($userData));

		if ($_username !== $GLOBALS['egw_info']['user']['account_lid']) $this->disconnect();

		return $userData;
	}

	/**
	 * Set information about a user
	 * currently only supported information is the current quota
	 *
	 * @param string $_username
	 * @param int $_quota
	 */
	function setUserData($_username, $_quota)
	{
		if(!$this->acc_imap_administration)
		{
			return false;
		}

		// create a admin connection
		$this->adminConnection();

		$mailboxName = $this->getUserMailboxString($_username);

		$this->setQuota($mailboxName, array('STORAGE' => (int)$_quota > 0 ? (int)$_quota*1024 : -1));

		$this->disconnect();

		return true;
	}

	/**
	 * Updates an account
	 *
	 * @param array $_hookValues only value for key 'account_lid' and 'account_passwd' is used
	 */
	function updateAccount($_hookValues)
	{
		if(!$this->acc_imap_administration)
		{
			return false;
		}

		// we need a admin connection
		$this->adminConnection();

		// create the mailbox, with the account_lid, as it is passed from the hook values (gets transformed there if needed)
		$mailboxName = $this->getUserMailboxString($_hookValues['account_lid'], '');
		// make sure we use the correct username here.
		$username = $this->getMailBoxUserName($_hookValues['account_lid']);
		$folderInfo = $this->getMailboxes('', $mailboxName, true);
		if(empty($folderInfo))
		{
			try {
				$this->createMailbox($mailboxName);
				$this->setACL($mailboxName, $username, array('rights' => 'aeiklprstwx'));
				// create defined folders and subscribe them (have to use user-credentials to subscribe!)
				$userimap = null;
				foreach($this->params as $name => $value)
				{
					if (substr($name, 0, 11) == 'acc_folder_' && !empty($value))
					{
						if (!isset($userimap))
						{
							$params = $this->params;
							$params['acc_imap_username'] = $username;
							$params['acc_imap_password'] = $_hookValues['account_passwd'];
							$userimap = new Cyrus($params);
						}
						$userimap->createMailbox($value);
						$userimap->subscribeMailbox($value);
					}
				}
				if (isset($userimap)) $userimap->logout();
			}
			catch(Horde_Imap_Client_Exception $e) {
				_egw_log_exception($e);
			}
		}
		$this->disconnect();
	}

	/**
	 * Proxy former bosieve methods to internal Mail\Sieve instance
	 *
	 * @param string $name
	 * @param ?array $params
	 * @throws \Exception
	 */
	public function __call($name, ?array $params=null)
	{
		switch($name)
		{
			case 'setRules':	// call setRules with 3. param of true, to enable utf7imap fileinto for Cyrus
				$params += array(null, null, true);
				break;
		}
		return parent::__call($name, $params);
	}
}