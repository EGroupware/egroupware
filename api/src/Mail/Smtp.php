<?php
/**
 * EGroupware Api: generic base class for SMTP
 *
 * @link http://www.egroupware.org
 * @package api
 * @subpackage mail
 * @author Lars Kneschke <lkneschke@linux-at-work.de>
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License Version 2+
 * @version $Id$
 */

namespace EGroupware\Api\Mail;

use EGroupware\Api;

/**
 * EMailAdmin generic base class for SMTP
 */
class Smtp
{
	/**
	 * Label shown in EMailAdmin
	 */
	const DESCRIPTION = 'standard SMTP-Server';

	/**
	 * Capabilities of this class (pipe-separated): default, forward
	 */
	const CAPABILITIES = 'default';

	/**
	 * Attribute value to enable mail for an account, OR false if existense of attribute is enough to enable account
	 *
	 * Logical values uses inside EGroupware, different classes might store different values internally
	 */
	const MAIL_ENABLED = 'active';

	/**
	 * Attribute value to only forward mail
	 *
	 * Logical values uses inside EGroupware, different classes might store different values internally
	 */
	const FORWARD_ONLY = 'forwardOnly';

	/**
	 * Reference to global account object
	 *
	 * @var Api\Accounts
	 */
	protected $accounts;

	/**
	 * SmtpServerId
	 *
	 * @var int
	 */
	var $SmtpServerId;

	var $smtpAuth = false;

	var $editForwardingAddress = false;

	var $host;

	var $port;

	var $username;

	var $password;

	var $defaultDomain;

	var $loginType;

	/**
	 * Constructor
	 *
	 * @param string $defaultDomain =null
	 */
	function __construct($defaultDomain=null)
	{
		$this->defaultDomain = $defaultDomain ? $defaultDomain : $GLOBALS['egw_info']['server']['mail_suffix'];

		$this->accounts = $GLOBALS['egw']->accounts;
	}

	/**
	 * Return description for EMailAdmin
	 *
	 * @return string
	 */
	public static function description()
	{
		return static::DESCRIPTION;
	}

	/**
	 * Hook called on account creation
	 *
	 * @param array $_hookValues values for keys 'account_email', 'account_firstname', 'account_lastname', 'account_lid'
	 * @return boolean true on success, false on error writing to ldap
	 */
	function addAccount($_hookValues)
	{
		$mailLocalAddress = $_hookValues['account_email'] ? $_hookValues['account_email'] :
			Api\Accounts::email($_hookValues['account_firstname'],
				$_hookValues['account_lastname'],$_hookValues['account_lid'],$this->defaultDomain);

		$account_id = !empty($_hookValues['account_id']) ? $_hookValues['account_id'] :
			$this->accounts->name2id($_hookValues['account_lid'], 'account_lid', 'u');

		if ($this->accounts->exists($account_id) != 1)
		{
			throw new Api\Exception\AssertionFailed("Account #$account_id ({$_hookValues['account_lid']}) does NOT exist!");
		}
		return $this->setUserData($account_id, array(), array(), null, self::MAIL_ENABLED, $mailLocalAddress, null);
	}

	/**
	 * Hook called on account deletion
	 *
	 * @param array $_hookValues values for keys 'account_lid', 'account_id'
	 * @return boolean true on success, false on error writing to ldap
	 */
	function deleteAccount($_hookValues)
	{
		unset($_hookValues);	// not used, but required by function signature

		return true;
	}

	/**
	 * Get all email addresses of an account
	 *
	 * @param string $_accountName
	 * @return array
	 */
	function getAccountEmailAddress($_accountName)
	{
		$emailAddresses	= array();

		if (($account_id = $this->accounts->name2id($_accountName, 'account_lid', 'u')))
		{
			$realName = trim($GLOBALS['egw_info']['user']['account_firstname'] . (!empty($GLOBALS['egw_info']['user']['account_firstname']) ? ' ' : '') . $GLOBALS['egw_info']['user']['account_lastname']);
			$emailAddresses[] = array (
				'name'		=> $realName,
				'address'	=> $this->accounts->id2name($account_id, 'account_email'),
				'type'		=> 'default',
			);
		}
		return $emailAddresses;
	}

	/**
	 * Get the data of a given user
	 *
	 * @param int|string $user numerical account-id, account-name or email address
	 * @param boolean $match_uid_at_domain =true true: uid@domain matches, false only an email or alias address matches
	 * @return array with values for keys 'mailLocalAddress', 'mailAlternateAddress' (array), 'mailForwardingAddress' (array),
	 * 	'accountStatus' ("active"), 'quotaLimit' and 'deliveryMode' ("forwardOnly")
	 */
	function getUserData($user, $match_uid_at_domain=false)
	{
		unset($user, $match_uid_at_domain);	// not used, but required by function signature

		$userData = array();

		return $userData;
	}


	/**
	 * Saves the forwarding information
	 *
	 * @param int $_accountID
	 * @param string|array $_forwardingAddress
	 * @param string $_keepLocalCopy 'yes'
	 * @return boolean true on success, false on error writing
	 */
	function saveSMTPForwarding($_accountID, $_forwardingAddress, $_keepLocalCopy)
	{
		return $this->setUserData($_accountID, array(),
			$_forwardingAddress ? (array)$_forwardingAddress : array(),
			$_keepLocalCopy != 'yes' ? self::FORWARD_ONLY : null, null, null, null, true);
	}

	/**
	 * Set the data of a given user
	 *
	 * @param int $_uidnumber numerical user-id
	 * @param array $_mailAlternateAddress
	 * @param array $_mailForwardingAddress
	 * @param string $_deliveryMode
	 * @param string $_accountStatus
	 * @param string $_mailLocalAddress
	 * @param int $_quota in MB
	 * @param boolean $_forwarding_only =false true: store only forwarding info, used internally by saveSMTPForwarding
	 * @param string $_setMailbox =null used only for account migration
	 * @return boolean true on success, false on error writing to ldap
	 */
	function setUserData($_uidnumber, array $_mailAlternateAddress, array $_mailForwardingAddress, $_deliveryMode,
		$_accountStatus, $_mailLocalAddress, $_quota, $_forwarding_only=false, $_setMailbox=null)
	{
		unset($_uidnumber, $_mailAlternateAddress, $_mailForwardingAddress,	// not used, but require by function signature
			$_deliveryMode, $_accountStatus, $_mailLocalAddress, $_quota, $_forwarding_only, $_setMailbox);

		return true;
	}

	/**
	 * Hook called on account update
	 *
	 * @param array $_hookValues values for keys 'account_email', 'account_firstname', 'account_lastname', 'account_lid', 'account_id'
	 * @return boolean true on success, false on error writing to ldap
	 */
	function updateAccount($_hookValues)
	{
		unset($_hookValues);	// not used, but required by function signature

		return true;
	}

	/**
	 * Build mailbox address for given account and mail_addr_type
	 *
	 * If $account is an array (with values for keys account_(id|lid|email), it does NOT call accounts class
	 *
	 * @param int|array $account account_id or whole account array with values for keys
	 * @param string $domain=null domain, default use $this->defaultDomain
	 * @param string $mail_login_type=null standard(uid), vmailmgr(uid@domain), email or uidNumber,
	 * 	default use $this->loginType
	 * @return string
	 */
	public function mailbox_addr($account, $domain=null, $mail_login_type=null)
	{
		return self::mailbox_address($account, $domain ?? $this->defaultDomain, $mail_login_type ?? $this->loginType);
	}

	/**
	 * Build mailbox address for given account and mail_addr_type
	 *
	 * If $account is an array (with values for keys account_(id|lid|email), it does NOT call accounts class
	 *
	 * @param int|array $account account_id or whole account array with values for keys
	 * @param string $domain|null domain
	 * @param string $mail_login_type=null standard(uid), vmailmgr(uid@domain), email or uidNumber
	 * @return string|null null if no domain given but required by $mail_login_type
	 */
	static public function mailbox_address($account, string $domain=null, string $mail_login_type=null)
	{
		switch($mail_login_type)
		{
			case 'email':
				$mbox = is_array($account) ? $account['account_email'] : $GLOBALS['egw']->accounts->id2name($account,'account_email');
				break;

			case 'uidNumber':
				if (empty($domain)) return null;
				if (is_array($account)) $account = $account['account_id'];
				$mbox = 'u'.$account.'@'.$domain;
				break;

			case 'standard':
				$mbox = is_array($account) ? $account['account_lid'] : $GLOBALS['egw']->accounts->id2name($account);
				break;

			case 'vmailmgr':
			default:
				if (empty($domain)) return null;
				$mbox = is_array($account) ? $account['account_lid'] : $GLOBALS['egw']->accounts->id2name($account);
				$mbox .= '@'.$domain;
				break;
		}
		//error_log(__METHOD__."(".array2string($account).",'$domain','$mail_login_type') = '$mbox'");

		return $mbox;
	}
}