<?php
/**
 * EGroupware EMailAdmin: generic base class for SMTP configuration via LDAP
 *
 * @link http://www.stylite.de
 * @package emailadmin
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2010-13 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * Generic base class for SMTP configuration via LDAP
 *
 * This class uses just inetOrgPerson schema to store primary mail address and aliases
 *
 * Aliases are stored as aditional mail Attributes. The primary mail address is the first one.
 * This schema does NOT support forwarding or disabling of an account for mail.
 *
 * Aliases, forwards, forward-only and quota attribute can be stored in same multivalued attribute
 * with different prefixes.
 *
 * Please do NOT copy this class! Extend it and set the constants different.
 *
 * Please note: schema names muse use correct case (eg. "inetOrgPerson"),
 * while attribute name muse use lowercase, as LDAP returns them as keys in lowercase!
 */
class emailadmin_smtp_ldap extends emailadmin_smtp
{
	/**
	 * Name of schema, has to be in the right case!
	 */
	const SCHEMA = 'inetOrgPerson';

	/**
	 * Filter for users
	 */
	const USER_FILTER = '(objectClass=posixAccount)';

	/**
	 * Name of schema for groups, has to be in the right case!
	 */
	const GROUP_SCHEMA = 'posixGroup';

	/**
	 * Attribute to enable mail for an account, OR false if existence of ALIAS_ATTR is enough for mail delivery
	 */
	const MAIL_ENABLE_ATTR = false;

	/**
	 * Value for MAIL_ENABLED to use local mail address
	 */
	const MAIL_ENABLED_USE_MAIL = '@mail';

	/**
	 * Attribute for aliases OR false to use mail
	 */
	const ALIAS_ATTR = false;

	/**
	 * Caseinsensitive prefix for aliases (eg. "smtp:"), aliases get added with it and only aliases with it are reported
	 */
	const ALIAS_PREFIX = '';

	/**
	 * Primary mail address required as an alias too: true or false
	 */
	const REQUIRE_MAIL_AS_ALIAS = false;

	/**
	 * Attribute for forwards OR false if not possible
	 */
	const FORWARD_ATTR = false;

	/**
	 * Caseinsensitive prefix for forwards (eg. "forward:"), forwards get added with it and only forwards with it are reported
	 */
	const FORWARD_PREFIX = '';

	/**
	 * Attribute to only forward mail, OR false if not available
	 */
	const FORWARD_ONLY_ATTR = false;

	/**
	 * Value of forward-only attribute, if empty any value will switch forward only on (checked with =*)
	 */
	const FORWARD_ONLY = 'forwardOnly';

	/**
	 * Attribute for mailbox, to which mail gets delivered OR false if not supported
	 */
	const MAILBOX_ATTR = false;

	/**
	 * Attribute for quota limit of user in MB
	 */
	const QUOTA_ATTR = false;

	/**
	 * Caseinsensitive prefix for quota (eg. "quota:"), quota get added with it and only quota with it are reported
	 */
	const QUOTA_PREFIX = '';

	/**
	 * Internal quota in MB is multiplicated with this factor before stored in LDAP
	 */
	const QUOTA_FACTOR = 1048576;

	/**
	 * Attribute for user name
	 */
	const USER_ATTR = 'uid';

	/**
	 * Attribute for numeric user id (optional)
	 */
	const USERID_ATTR = 'uidnumber';

	/**
	 * Base for all searches, defaults to $GLOBALS['egw_info']['server']['ldap_context'] and can be set via setBase($base)
	 *
	 * @var string
	 */
	protected $search_base;

	/**
	 * Special search filter for getUserData only
	 *
	 * @var string
	 */
	protected  $search_filter;

	/**
	 * Log all LDAP writes / actions to error_log
	 */
	var $debug = false;

	/**
	 * from here on implementation, please do NOT copy but extend it!
	 */

	/**
	 * Constructor
	 *
	 * @param string $defaultDomain=null
	 */
	function __construct($defaultDomain=null)
	{
		parent::__construct($defaultDomain);

		if (empty($this->search_base))
		{
			$this->setBase($GLOBALS['egw_info']['server']['ldap_context']);
		}
	}

	/**
	 * Return description for EMailAdmin
	 *
	 * @return string
	 */
	public static function description()
	{
		return 'LDAP ('.static::SCHEMA.')';
	}

	/**
	 * Set ldap search filter for aliases and forwards (getUserData)
	 *
	 * @param string $filter
	 */
	function setFilter($filter)
	{
		$this->search_filter = $filter;
	}

	/**
	 * Set ldap search base, default $GLOBALS['egw_info']['server']['ldap_context']
	 *
	 * @param string $base
	 */
	function setBase($base)
	{
		$this->search_base = $base;
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
			common::email_address($_hookValues['account_firstname'],
				$_hookValues['account_lastname'],$_hookValues['account_lid'],$this->defaultDomain);

		$ds = $this->getLdapConnection();

		$filter = static::USER_ATTR."=".ldap::quote($_hookValues['account_lid']);

		if (!($sri = @ldap_search($ds, $this->search_base, $filter)))
		{
			return false;
		}
		$allValues 	= ldap_get_entries($ds, $sri);
		$accountDN 	= $allValues[0]['dn'];
		$objectClasses	= $allValues[0]['objectclass'];
		unset($objectClasses['count']);

		// add our mail schema, if not already set
		if(!in_array(static::SCHEMA,$objectClasses) && !in_array(strtolower(static::SCHEMA),$objectClasses))
		{
			$objectClasses[]	= static::SCHEMA;
		}
		// the new code for postfix+cyrus+ldap
		$newData = array(
			'mail'			  => $mailLocalAddress,
			'objectclass'	  => $objectClasses
		);
		// does schema have explicit alias attribute AND require mail added as alias too
		if (static::ALIAS_ATTR && static::REQUIRE_MAIL_AS_ALIAS)
		{
			$newData[static::ALIAS_ATTR] = static::ALIAS_PREFIX.$mailLocalAddress;
		}
		// does schema support enabling/disabling mail via attribute
		if (static::MAIL_ENABLE_ATTR)
		{
			$newData[static::MAIL_ENABLE_ATTR] = static::MAIL_ENABLED == self::MAIL_ENABLED_USE_MAIL ?
				$mailLocalAddress : static::MAIL_ENABLE_ATTR;
		}
		// does schema support an explicit mailbox name --> set it
		if (static::MAILBOX_ATTR)
		{
			$newData[static::MAILBOX_ATTR] = self::mailbox_addr($_hookValues);
		}

		// allow extending classes to add extra data
		$this->addAccountExtra($_hookValues, $allValues[0], $newData);

		if (!($ret = ldap_mod_replace($ds, $accountDN, $newData)) || $this->debug)
		{
			error_log(__METHOD__.'('.array2string(func_get_args()).") --> ldap_mod_replace(,'$accountDN',".
				array2string($newData).') returning '.array2string($ret).
				(!$ret?' ('.ldap_error($ds).')':''));
		}
		return $ret;
	}

	/**
	 * Add additional values to addAccount and setUserData ($_hookValues['location'])
	 *
	 * @param array $_hookValues
	 * @param array $allValues existing data of account as returned by ldap query
	 * @param array $newData data to update
	 */
	function addAccountExtra(array $_hookValues, array $allValues, array &$newData)
	{
		unset($_hookValues, $allValues, $newData);	// not used, but required by function signature
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
		$ds = $this->getLdapConnection();
		$filter = '(&'.static::USER_FILTER.'('.static::USER_ATTR.'='.ldap::quote($_accountName).'))';
		$attributes	= array('dn', 'mail', static::ALIAS_ATTR);
		$sri = @ldap_search($ds, $this->search_base, $filter, $attributes);

		if ($sri)
		{
			$realName = trim($GLOBALS['egw_info']['user']['account_firstname'] . (!empty($GLOBALS['egw_info']['user']['account_firstname']) ? ' ' : '') . $GLOBALS['egw_info']['user']['account_lastname']);
			$allValues = ldap_get_entries($ds, $sri);

			if(isset($allValues[0]['mail']))
			{
				foreach($allValues[0]['mail'] as $key => $value)
				{
					if ($key === 'count') continue;

					$emailAddresses[] = array (
						'name'		=> $realName,
						'address'	=> $value,
						'type'		=> !$key ? 'default' : 'alternate',
					);
				}
			}
			if (static::ALIAS_ATTR && isset($allValues[0][static::ALIAS_ATTR]))
			{
				foreach(self::getAttributePrefix($allValues[0][static::ALIAS_ATTR], static::ALIAS_PREFIX) as $value)
				{
					$emailAddresses[] = array(
						'name'		=> $realName,
						'address'	=> $value,
						'type'		=> 'alternate'
					);
				}
			}
		}
		if ($this->debug) error_log(__METHOD__."('$_accountName') returning ".array2string($emailAddresses));

		return $emailAddresses;
	}

	/**
	 * Get the data of a given user
	 *
	 * Multiple accounts may match, if an email address is specified.
	 * In that case only mail routing fields "uid", "mailbox" and "forward" contain values
	 * from all accounts!
	 *
	 * @param int|string $user numerical account-id, account-name or email address
	 * @param boolean $match_uid_at_domain=true true: uid@domain matches, false only an email or alias address matches
	 * @return array with values for keys 'mailLocalAddress', 'mailAlternateAddress' (array), 'mailForwardingAddress' (array),
	 * 	'accountStatus' ("active"), 'quotaLimit' and 'deliveryMode' ("forwardOnly")
	 */
	function getUserData($user, $match_uid_at_domain=false)
	{
		$userData = array(
			'mailbox' => array(),
			'forward' => array(),

		);

		$ldap = $this->getLdapConnection();

		if (is_numeric($user) && static::USERID_ATTR)
		{
			$filter = '('.static::USERID_ATTR.'='.(int)$user.')';
		}
		elseif (strpos($user, '@') === false)
		{
			if (is_numeric($user)) $user = $GLOBALS['egw']->accounts->id2name($user);
			$filter = '(&'.static::USER_FILTER.'('.static::USER_ATTR.'='.ldap::quote($user).'))';
		}
		else	// email address --> build filter by attributes defined in config
		{
			list($namepart, $domain) = explode('@', $user);
			if (!empty($this->search_filter))
			{
				$filter = strtr($this->search_filter, array(
					'%s' => ldap::quote($user),
					'%u' => ldap::quote($namepart),
					'%d' => ldap::quote($domain),
				));
			}
			else
			{
				$to_or = array('(mail='.ldap::quote($user).')');
				if ($match_uid_at_domain) $to_or[] = '('.static::USER_ATTR.'='.ldap::quote($namepart).')';
				if (static::ALIAS_ATTR)
				{
					$to_or[] = '('.static::ALIAS_ATTR.'='.static::ALIAS_PREFIX.ldap::quote($user).')';
				}
				$filter = count($to_or) > 1 ? '(|'.explode('', $to_or).')' : $to_or[0];

				// if an enable attribute is set, only return enabled accounts
				if (static::MAIL_ENABLE_ATTR)
				{
					$filter = '(&('.static::MAIL_ENABLE_ATTR.'='.
						(static::MAIL_ENABLED ? static::MAIL_ENABLED : '*').")$filter)";
				}
			}
		}
		$attributes = array_values(array_diff(array(
			'mail', 'objectclass', static::USER_ATTR, static::MAIL_ENABLE_ATTR, static::ALIAS_ATTR,
			static::MAILBOX_ATTR, static::FORWARD_ATTR, static::FORWARD_ONLY_ATTR, static::QUOTA_ATTR,
		), array(false, '')));

		$sri = ldap_search($ldap, $this->search_base, $filter, $attributes);

		if ($sri)
		{
			$allValues = ldap_get_entries($ldap, $sri);
			if ($this->debug) error_log(__METHOD__."('$user') --> ldap_search(, '$this->search_base', '$filter') --> ldap_get_entries=".array2string($allValues[0]));

			foreach($allValues as $key => $values)
			{
				if ($key === 'count') continue;

				// groups are always active (if they have an email) and allways forwardOnly
				if (in_array(static::GROUP_SCHEMA, $values['objectclass']))
				{
					$accountStatus = emailadmin_smtp::MAIL_ENABLED;
					$deliveryMode = emailadmin_smtp::FORWARD_ONLY;
				}
				else	// for users we have to check the attributes
				{
					if (static::MAIL_ENABLE_ATTR)
					{
						$accountStatus = isset($values[static::MAIL_ENABLE_ATTR]) &&
							(static::MAIL_ENABLED === self::MAIL_ENABLED_USE_MAIL && !empty($values[static::MAIL_ENABLE_ATTR][0]) ||
							static::MAIL_ENABLED && !strcasecmp($values[static::MAIL_ENABLE_ATTR][0], static::MAIL_ENABLED) ||
							!static::MAIL_ENABLED && $values[static::ALIAS_ATTR ? static::ALIAS_ATTR : 'mail']['count'] > 0) ?
								emailadmin_smtp::MAIL_ENABLED : '';
					}
					else
					{
						$accountStatus = $values[static::ALIAS_ATTR ? static::ALIAS_ATTR : 'mail']['count'] > 0 ?
							emailadmin_smtp::MAIL_ENABLED : '';
					}
					if (static::FORWARD_ONLY_ATTR)
					{
						if (static::FORWARD_ONLY)	// check caseinsensitiv for existence of that value
						{
							$deliveryMode = self::getAttributePrefix($values[static::FORWARD_ONLY_ATTR], static::FORWARD_ONLY) ?
								emailadmin_smtp::FORWARD_ONLY : '';
						}
						else	// check for existence of any value
						{
							$deliveryMode = $values[static::FORWARD_ONLY_ATTR]['count'] > 0 ?
								emailadmin_smtp::FORWARD_ONLY : '';
						}
					}
					else
					{
						$deliveryMode = '';
					}
				}

				// collect mail routing data (can be from multiple (active) accounts and groups!)
				if ($accountStatus)
				{
					// groups never have a mailbox, accounts can have a deliveryMode of "forwardOnly"
					if ($deliveryMode != emailadmin_smtp::FORWARD_ONLY)
					{
						$userData[static::USER_ATTR][] = $values[static::USER_ATTR][0];
						if (static::MAILBOX_ATTR && isset($values[static::MAILBOX_ATTR]))
						{
							$userData['mailbox'][] = $values[static::MAILBOX_ATTR][0];
						}
					}
					if (static::FORWARD_ATTR && $values[static::FORWARD_ATTR])
					{
						$userData['forward'] = array_merge($userData['forward'],
							self::getAttributePrefix($values[static::FORWARD_ATTR], static::FORWARD_PREFIX, false));
					}
				}

				// regular user-data can only be from users, NOT groups
				if (in_array(static::GROUP_SCHEMA, $values['objectclass'])) continue;

				$userData['mailLocalAddress'] = $values['mail'][0];
				$userData['accountStatus'] = $accountStatus;

				if (static::ALIAS_ATTR)
				{
					$userData['mailAlternateAddress'] = self::getAttributePrefix($values[static::ALIAS_ATTR], static::ALIAS_PREFIX);
				}
				else
				{
					$userData['mailAlternateAddress']	= (array)$values['mail'];
					unset($userData['mailAlternateAddress']['count']);
					unset($userData['mailAlternateAddress'][0]);
					$userData['mailAlternateAddress']	= array_values($userData['mailAlternateAddress']);
				}

				if (static::FORWARD_ATTR)
				{
					$userData['mailForwardingAddress']	= self::getAttributePrefix($values[static::FORWARD_ATTR], static::FORWARD_PREFIX);
				}

				if (static::MAILBOX_ATTR) $userData['mailMessageStore']	= $values[static::MAILBOX_ATTR][0];

				$userData['deliveryMode'] = $deliveryMode;

				// eg. suse stores all email addresses as aliases
				if (static::REQUIRE_MAIL_AS_ALIAS &&
					($k = array_search($userData['mailLocalAddress'],$userData['mailAlternateAddress'])) !== false)
				{
					unset($userData['mailAlternateAddress'][$k]);
				}

				if (static::QUOTA_ATTR && isset($values[static::QUOTA_ATTR]))
				{
					$userData['quotaLimit'] = self::getAttributePrefix($values[static::QUOTA_ATTR], static::QUOTA_PREFIX);
					$userData['quotaLimit'] = array_shift($userData['quotaLimit']);
					$userData['quotaLimit'] = $userData['quotaLimit'] ? $userData['quotaLimit'] / static::QUOTA_FACTOR : null;
				}
			}
		}
		if ($this->debug) error_log(__METHOD__."('$user') returning ".array2string($userData));

		return $userData;
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
	 * @param boolean $_forwarding_only=false not used as we have our own addAccount method
	 * @param string $_setMailbox=null used only for account migration
	 * @return boolean true on success, false on error writing to ldap
	 */
	function setUserData($_uidnumber, array $_mailAlternateAddress, array $_mailForwardingAddress, $_deliveryMode,
		$_accountStatus, $_mailLocalAddress, $_quota, $_forwarding_only=false, $_setMailbox=null)
	{
		unset($_forwarding_only);	// not used

		if (static::USERID_ATTR)
		{
			$filter = static::USERID_ATTR.'='.(int)$_uidnumber;
		}
		else
		{
			$uid = $GLOBALS['egw']->accounts->id2name($_uidnumber);
			$filter = static::USER_ATTR.'='.ldap::quote($uid);
		}
		$ldap = $this->getLdapConnection();

		if (!($sri = @ldap_search($ldap, $this->search_base, $filter)))
		{
			return false;
		}
		$allValues 	= ldap_get_entries($ldap, $sri);

		$accountDN 	= $allValues[0]['dn'];
		$uid	   	= $allValues[0][static::USER_ATTR][0];
		$objectClasses	= $allValues[0]['objectclass'];

		unset($objectClasses['count']);

		if(!in_array(static::SCHEMA,$objectClasses) && !in_array(strtolower(static::SCHEMA),$objectClasses))
		{
			$objectClasses[]	= static::SCHEMA;
			$newData['objectclass']	= $objectClasses;
		}

		sort($_mailAlternateAddress);
		sort($_mailForwardingAddress);

		$newData['mail'] = $_mailLocalAddress;
		// does schema have explicit alias attribute
		if (static::ALIAS_ATTR)
		{
			self::setAttributePrefix($newData[static::ALIAS_ATTR], $_mailAlternateAddress, static::ALIAS_PREFIX);

			// all email must be stored as alias for suse
			if (static::REQUIRE_MAIL_AS_ALIAS && !in_array($_mailLocalAddress,(array)$_mailAlternateAddress))
			{
				self::setAttributePrefix($newData[static::ALIAS_ATTR], $_mailLocalAddress, static::ALIAS_PREFIX);
			}
		}
		// or de we add them - if existing - to mail attr
		elseif ($_mailAlternateAddress)
		{
			self::setAttributePrefix($newData['mail'], $_mailAlternateAddress, static::ALIAS_PREFIX);
		}
		// does schema support to store forwards
		if (static::FORWARD_ATTR)
		{
			self::setAttributePrefix($newData[static::FORWARD_ATTR], $_mailForwardingAddress, static::FORWARD_PREFIX);
		}
		// does schema support only forwarding incomming mail
		if (static::FORWARD_ONLY_ATTR)
		{
			self::setAttributePrefix($newData[static::FORWARD_ONLY_ATTR],
				$_deliveryMode ? (static::FORWARD_ONLY ? static::FORWARD_ONLY : 'forwardOnly') : array());
		}
		// does schema support an explicit mailbox name --> set it with $uid@$domain
		if (static::MAILBOX_ATTR && empty($allValues[0][static::MAILBOX_ATTR][0]))
		{
			$newData[static::MAILBOX_ATTR] = $this->mailbox_addr(array(
				'account_id' => $_uidnumber,
				'account_lid' => $uid,
				'account_email' => $_mailLocalAddress,
			));
		}
		if (static::QUOTA_ATTR)
		{
			self::setAttributePrefix($newData[static::QUOTA_ATTR],
				(int)$_quota > 0 ? (int)$_quota*static::QUOTA_FACTOR : array(), static::QUOTA_PREFIX);
		}
		// does schema support enabling/disabling mail via attribute
		if (static::MAIL_ENABLE_ATTR)
		{
			$newData[static::MAIL_ENABLE_ATTR]	= $_accountStatus ?
				(static::MAIL_ENABLED == self::MAIL_ENABLED_USE_MAIL ? $_mailLocalAddress : static::MAIL_ENABLED) : array();
		}
		// if we have no mail-enabled attribute, but require primary mail in aliases-attr
		// we do NOT write aliases, if mail is not enabled
		if (!$_accountStatus && !static::MAIL_ENABLE_ATTR && static::REQUIRE_MAIL_AS_ALIAS)
		{
			$newData[static::ALIAS_ATTR] = array();
		}
		// does schema support an explicit mailbox name --> set it, $_setMailbox is given
		if (static::MAILBOX_ATTR && $_setMailbox)
		{
			$newData[static::MAILBOX_ATTR] = $_setMailbox;
		}

		$this->addAccountExtra(array('location' => 'setUserData'), $allValues[0], $newData);

		if ($this->debug) error_log(__METHOD__.'('.array2string(func_get_args()).") --> ldap_mod_replace(,'$accountDN',".array2string($newData).')');

		return ldap_mod_replace($ldap, $accountDN, $newData);
	}

	/**
	 * Saves the forwarding information
	 *
	 * @param int $_accountID
	 * @param string $_forwardingAddress
	 * @param string $_keepLocalCopy 'yes'
	 * @return boolean true on success, false on error writing to ldap
	 */
	function saveSMTPForwarding($_accountID, $_forwardingAddress, $_keepLocalCopy)
	{
		$ds = $this->getLdapConnection();
		if (static::USERID_ATTR)
		{
			$filter = '(&'.static::USER_FILTER.'('.static::USERID_ATTR.'='.(int)$_accountID.'))';
		}
		else
		{
			$uid = $GLOBALS['egw']->accounts->id2name($_accountID);
			$filter = '(&'.static::USER_FILTER.'('.static::USER_ATTR.'='.ldap::quote($uid).'))';
		}
		$attributes	= array('dn', static::FORWARD_ATTR, 'objectclass');
		if (static::FORWARD_ONLY_ATTR)
		{
			$attributes[] = static::FORWARD_ONLY_ATTR;
		}
		$sri = ldap_search($ds, $this->search_base, $filter, $attributes);

		if ($sri)
		{
			$newData = array();
			$allValues = ldap_get_entries($ds, $sri);
			$objectClasses  = $allValues[0]['objectclass'];
			$newData['objectclass']	= $allValues[0]['objectclass'];

			unset($newData['objectclass']['count']);

			if(!in_array(static::SCHEMA,$objectClasses))
			{
				$newData['objectclass'][] = static::SCHEMA;
			}
			if (static::FORWARD_ATTR)
			{
				// copy all non-forward data (different prefix) to newData, all existing forwards to $forwards
				$newData[static::FORWARD_ATTR] = $allValues[0][static::FORWARD_ATTR];
				$forwards = self::getAttributePrefix($newData[static::FORWARD_ATTR], static::FORWARD_PREFIX);

				if(!empty($_forwardingAddress))
				{
					if($forwards)
					{
						if (!is_array($_forwardingAddress))
						{
							// replace the first forwarding address (old behavior)
							$forwards[0] = $_forwardingAddress;
						}
						else
						{
							// replace all forwarding Addresses
							$forwards = $_forwardingAddress;
						}
					}
					else
					{
						$forwards = (array)$_forwardingAddress;
					}
					if (static::FORWARD_ONLY_ATTR)
					{
						self::getAttributePrefix($newData[static::FORWARD_ONLY_ATTR], static::FORWARD_ONLY);
						self::setAttributePrefix($newData[static::FORWARD_ONLY_ATTR],
							$_keepLocalCopy == 'yes' ? array() : static::FORWARD_ONLY);
					}
				}
				else
				{
					$forwards = array();
				}
				// merge in again all new set forwards incl. opt. prefix
				self::getAttributePrefix($newData[static::FORWARD_ATTR], $forwards, static::FORWARD_PREFIX);
			}
			if ($this->debug) error_log(__METHOD__.'('.array2string(func_get_args()).") --> ldap_mod_replace(,'{$allValues[0]['dn']}',".array2string($newData).')');

			return ldap_modify ($ds, $allValues[0]['dn'], $newData);
		}
	}

	/**
	 * Get configured mailboxes of a domain
	 *
	 * @param boolean $return_inactive return mailboxes NOT marked as accountStatus=active too
	 * @return array uid => name-part of mailMessageStore
	 */
	function getMailboxes($return_inactive)
	{
		$ds = $this->getLdapConnection();
		$filter = array("(mail=*)");
		$attrs = array(static::USER_ATTR, 'mail');
		if (static::MAILBOX_ATTR)
		{
			$filter[] = '('.static::MAILBOX_ATTR.'=*)';
			$attrs[] = static::MAILBOX_ATTR;
		}
		if (!$return_inactive && static::MAIL_ENABLE_ATTR)
		{
			$filter[] = '('.static::MAIL_ENABLE_ATTR.'='.static::MAIL_ENABLED.')';
		}
		if (count($filter) > 1)
		{
			$filter = '(&'.implode('', $filter).')';
		}
		else
		{
			$filter = $filter[0];
		}
		if (!($sr = @ldap_search($ds, $this->search_base, $filter, $attrs)))
		{
			//error_log("Error ldap_search(\$ds, '$base', '$filter')!");
			return array();
		}
		$entries = ldap_get_entries($ds, $sr);

		unset($entries['count']);

		$mailboxes = array();
		foreach($entries as $entry)
		{
			if ($entry[static::USER_ATTR][0] == 'anonymous') continue;	// anonymous is never a mail-user!
			list($mailbox) = explode('@', $entry[static::MAILBOX_ATTR ? static::MAILBOX_ATTR : 'mail'][0]);
			$mailboxes[$entry[static::USER_ATTR][0]] = $mailbox;
		}
		return $mailboxes;
	}

	/**
	 * Set values in a given LDAP attribute using an optional prefix
	 *
	 * @param array &$attribute on return array with values set and existing values preseved
	 * @param string|array $values value(s) to set
	 * @param string $prefix='' prefix to use or ''
	 */
	protected static function setAttributePrefix(&$attribute, $values, $prefix='')
	{
		//$attribute_in = $attribute;
		if (!isset($attribute)) $attribute = array();
		if (!is_array($attribute)) $attribute = array($attribute);

		foreach((array)$values as $value)
		{
			$attribute[] = $prefix.$value;
		}
		//error_log(__METHOD__."(".array2string($attribute_in).", ".array2string($values).", '$prefix') attribute=".array2string($attribute));
	}

	/**
	 * Get values having an optional prefix from a given LDAP attribute
	 *
	 * @param array &$attribute only "count" and prefixed values get removed, get's reindexed, if values have been removed
	 * @param string $prefix='' prefix to use or ''
	 * @param boolean $remove=true remove returned values from $attribute
	 * @return array with values (prefix removed) or array() if nothing found
	 */
	protected static function getAttributePrefix(&$attribute, $prefix='', $remove=true)
	{
		//$attribute_in = $attribute;
		$values = array();

		if (isset($attribute))
		{
			unset($attribute['count']);

			foreach($attribute as $key => $value)
			{
				if (!$prefix || stripos($value, $prefix) === 0)
				{
					if ($remove) unset($attribute[$key]);
					$values[] = substr($value, strlen($prefix));
				}
			}
			// reindex $attribute, if neccessary
			if ($values && $attribute) $attribute = array_values($attribute);
		}
		//error_log(__METHOD__."(".array2string($attribute_in).", '$prefix', $remove) attribute=".array2string($attribute).' returning '.array2string($values));
		return $values;
	}

	/**
	 * Return LDAP connection
	 */
	protected function getLdapConnection()
	{
		static $ldap=null;

		if (is_null($ldap)) $ldap = $GLOBALS['egw']->ldap->ldapConnect();

		return $ldap;
	}
}
