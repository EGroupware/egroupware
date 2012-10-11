<?php
/**
 * EGroupware EMailAdmin: Support for Dovecot IMAP
 *
 * @link http://www.stylite.de
 * @package emailadmin
 * @author Ralf Becker <rb@stylite.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

include_once(EGW_SERVER_ROOT."/emailadmin/inc/class.defaultimap.inc.php");

/**
 * Manages connection to Dovecot IMAP server
 *
 * Basic differences to cyrusimap:
 * - no real admin user, but master user, whos password can be used to connect instead of real user
 * - mailboxes have to be deleted in filesystem (no IMAP command for that)
 *   --> require by webserver writable user_home to be configured, otherwise deleting get ignored like with defaultimap
 * - quota can be read, but not set
 */
class emailadmin_dovecot extends defaultimap
{
	/**
	 * Capabilities of this class (pipe-separated): default, sieve, admin, logintypeemail
	 */
	const CAPABILITIES = 'default|sieve|timedsieve|admin|logintypeemail';

	// mailbox delimiter
	var $mailboxDelimiter = '.';

	// mailbox prefix
	var $mailboxPrefix = '';

	var $enableCyrusAdmin = false;

	var $cyrusAdminUsername;

	var $cyrusAdminPassword;

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
	 * Opens a connection to a imap server
	 *
	 * Reimplemented to prefix adminUsername with real username (separated by an asterisk)
	 *
	 * @param bool $_adminConnection create admin connection if true
	 * @return resource the imap connection
	 */
	function openConnection($_adminConnection=false, $_timeout=20)
	{
		if ($_adminConnection)
		{
			if (($pos = strpos($this->adminUsername, '*')) !== false)	// remove evtl. set username
			{
				$this->adminUsername = substr($this->adminUsername, $pos+1);
			}
			$this->adminUsername = $this->username.'*'.$this->adminUsername;
		}
		return parent::openConnection($_adminConnection, $_timeout);
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
		// some precausion to really delete just _one_ account
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
		if(!$this->enableCyrusAdmin || empty($username))
		{
			return false;
		}

		// dovecot can not delete mailbox, they need to be physically deleted in filesystem (webserver needs write-rights to do so!)
		if (empty($this->user_home))
		{
			return false;
		}
		$replace = array('%d' => $this->domainName, '%u' => $username, '%s' => $username.'@'.$this->domainName);

		if ($username == '%')
		{
			if (($pos = strpos($this->user_home, '%d')) === false)
			{
				throw new egw_exception_assertion_failed("user_home='$this->user_home' contains no domain-part '%d'!");
			}
			$home = strtr(substr($this->user_home, 0, $pos+2), $replace);

			$ret = count(scandir($home))-2;
		}
		else
		{
			$home = strtr($this->user_home, $replace);

			$ret = 1;
		}
		if (!is_writable(dirname($home)) || !self::_rm_recursive($home))
		{
			error_log(__METHOD__."('$username') Failed to delete $home!");
			return false;
		}
		return $ret;
	}

	/**
	 * Recursively delete a directory (or file)
	 *
	 * @param string $path
	 * @return boolean true on success, false on failure
	 */
	private function _rm_recursive($path)
	{
		if (is_dir($path))
		{
			foreach(scandir($path) as $file)
			{
				if ($file == '.' || $file == '..') continue;

				if (is_dir($path))
				{
					self::_rm_recursive($path.'/'.$file);
				}
				elseif (!unlink($path.'/'.$file))
				{
					return false;
				}
			}
			if (!rmdir($path))
			{
				return false;
			}
		}
		elseif(!unlink($path))
		{
			return false;
		}
		return true;
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
		if($this->_connected === true)
		{
			//error_log(__METHOD__."try to disconnect");
			$this->disconnect();
		}

		$this->openConnection(true);
		$userData = array();

		// we are authenticated with master but for current user
		if(($quota = $this->getStorageQuotaRoot('INBOX')) && !PEAR::isError($quota))
		{
			$userData['quotaLimit'] = $quota['QMAX'] / 1024;
		}

		$this->disconnect();

		return $userData;
	}

	/**
	 * Set information about a user
	 * currently only supported information is the current quota
	 *
	 * Dovecot get's quota from it's user-db, but cant set it --> ignored
	 *
	 * @param string $_username
	 * @param int $_quota
	 * @return boolean
	 */
	function setUserData($_username, $_quota)
	{
		return true;
	}

	/**
	 * Updates an account
	 *
	 * @param array $_hookValues only value for key 'account_lid' and 'new_passwd' is used
	 */
	function updateAccount($_hookValues)
	{
		if(!$this->enableCyrusAdmin)
		{
			return false;
		}
		// mailbox get's automatic created with full rights for user
		return true;
	}
}
