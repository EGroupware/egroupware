<?php
/**
 * EGroupware EMailAdmin: generic base class for SMTP configuration via LDAP
 *
 * @link http://www.stylite.de
 * @package emailadmin
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2010-12 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
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
 * Please do NOT copy this class! Extend it and set the constants different
 * (incl. protected config var as long as we can not require PHP5.3 for LSB).
 */
class emailadmin_smtp_ldap extends emailadmin_smtp
{
	/**
	 * Name of schema, has to be in the right case!
	 */
	const SCHEMA = 'inetOrgPerson';

	/**
	 * Attribute to enable mail for an account, OR false if existence of ALIAS_ATTR is enough for mail delivery
	 */
	const MAIL_ENABLE_ATTR = false;

	/**
	 * Attribute for aliases OR false to use mail
	 */
	const ALIAS_ATTR = false;

	/**
	 * Primary mail address required as an alias too: true or false
	 */
	const REQUIRE_MAIL_AS_ALIAS=false;

	/**
	 * Attribute for forwards OR false if not possible
	 */
	const FORWARD_ATTR = false;

	/**
	 * Attribute to only forward mail, OR false if not available
	 */
	const FORWARD_ONLY_ATTR = false;

	/**
	 * Attribute for mailbox, to which mail gets delivered OR false if not supported
	 */
	const MAILBOX_ATTR = false;

	/**
	 * Attribute for quota limit of user in MB
	 */
	const QUOTA_ATTR = false;

	/**
	 * Log all LDAP writes / actions to error_log
	 */
	var $debug = false;

	/**
	 * LDAP schema configuration
	 *
	 * Parent can NOT use constants direct as we have no late static binding in currenlty required PHP 5.2
	 *
	 * @var array
	 */
	protected $config = array(
		'schema' => self::SCHEMA,
		'mail_enable_attr' => self::MAIL_ENABLE_ATTR,
		'mail_enabled' => self::MAIL_ENABLED,
		'alias_attr' => self::ALIAS_ATTR,
		'require_mail_as_alias' => self::REQUIRE_MAIL_AS_ALIAS,
		'forward_attr' => self::FORWARD_ATTR,
		'forward_only_attr' => self::FORWARD_ONLY_ATTR,
		'forward_only' => self::FORWARD_ONLY,
		'mailbox_attr' => self::MAILBOX_ATTR,
		'quota_attr' => self::QUOTA_ATTR,
		'search_filter' => null,
		'search_base' => null,
	);

	/**
	 * from here on implementation, please do NOT copy but extend it!
	 */

	/**
	 * Set ldap search filter for aliases and forwards
	 *
	 * @param string $filter
	 */
	function setFilter($filter)
	{
		$this->config['search_filter'] = $filter;
	}

	/**
	 * Set ldap search base for aliases and forwards
	 *
	 * @param string $base
	 */
	function setBase($base)
	{
		$this->config['search_base'] = $base;
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

		$ds = $GLOBALS['egw']->ldap->ldapConnect();

		$filter = "uid=".$_hookValues['account_lid'];

		if (!($sri = @ldap_search($ds,$GLOBALS['egw_info']['server']['ldap_context'],$filter)))
		{
			return false;
		}
		$allValues 	= ldap_get_entries($ds, $sri);
		$accountDN 	= $allValues[0]['dn'];
		$objectClasses	= $allValues[0]['objectclass'];
		unset($objectClasses['count']);

		// add our mail schema, if not already set
		if(!in_array($this->config['schema'],$objectClasses) && !in_array(strtolower($this->config['schema']),$objectClasses))
		{
			$objectClasses[]	= $this->config['schema'];
		}
		// the new code for postfix+cyrus+ldap
		$newData = array(
			'mail'			  => $mailLocalAddress,
			'objectclass'	  => $objectClasses
		);
		// does schema have explicit alias attribute AND require mail added as alias too
		if ($this->config['alias_attr'] && $this->config['require_mail_as_alias'] && $this->config['alias_attr'])
		{
			$newData[$this->config['alias_attr']] = $mailLocalAddress;
		}
		// does schema support enabling/disabling mail via attribute
		if ($this->config['mail_enable_attr'])
		{
			$newData[$this->config['mail_enable_attr']] = $this->config['mail_enabled'];
		}
		// does schema support an explicit mailbox name --> set it
		if ($this->config['mailbox_attr'])
		{
			$newData[$this->config['mailbox_attr']] = self::mailbox_addr($_hookValues);
		}

		if (!($ret = ldap_mod_replace($ds, $accountDN, $newData)) || $this->debug)
		{
			error_log(__METHOD__.'('.array2string(func_get_args()).") --> ldap_mod_replace(,'$accountDN',".
				array2string($newData).') returning '.array2string($ret).
				(!$ret?' ('.ldap_error($ds).')':''));
		}
		return $ret;
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
		$ds = $GLOBALS['egw']->ldap->ldapConnect();
		$filter 	= sprintf("(&(uid=%s)(objectclass=posixAccount))",$_accountName);
		$attributes	= array('dn','mail',$this->config['alias_attr']);
		$sri = @ldap_search($ds, $GLOBALS['egw_info']['server']['ldap_context'], $filter, $attributes);

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
			if ($this->config['alias_attr'] && isset($allValues[0][$this->config['alias_attr']]))
			{
				foreach($allValues[0][$this->config['alias_attr']] as $key => $value)
				{
					if ($key === 'count') continue;

					$emailAddresses[] = array(
						'name'		=> $realName,
						'address'	=> $value,
						'type'		=> 'alternate'
					);
				}
			}
		}
		if ($this->debug) error_log(__METHOD__."('$_acountName') returning ".array2string($emailAddresses));

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
		$userData = array();

		$ldap = $GLOBALS['egw']->ldap->ldapConnect();

		if (is_numeric($user))
		{
			$filter = '(uidnumber='.(int)$_uidnumber.')';
		}
		elseif (strpos($user, '@') === false)
		{
			$filter = '(uid='.ldap::quote($user).')';
		}
		else	// email address --> build filter by attributes defined in config
		{
			list($namepart, $domain) = explode('@', $user);
			if (!empty($this->config['search_filter']))
			{
				$filter = strtr($this->config['search_filter'], array(
					'%s' => ldap::quote($user),
					'%u' => ldap::quote($namepart),
					'%d' => ldap::quote($domain),
				));
			}
			else
			{
				$filter = array('(mail='.ldap::quote($user).')');
				if ($match_uid_at_domain) $filter[] = '(uid='.ldap::quote($namepart).')';
				if ($this->config['alias_attr'])
				{
					$filter[] = '('.$this->config['alias_attr'].'='.ldap::quote($user).')';
				}
				$filter = count($filter) > 1 ? '(|'.explode('', $filter).')' : $filter[0];

				// if an enable attribute is set, only return enabled accounts
				if ($this->config['mail_enable_attr'])
				{
					$filter = '(&('.$this->config['mail_enable_attr'].'='.
						($this->config['mail_enabled'] ? $this->config['mail_enabled'] : '*').")$filter)";
				}
			}
		}
		$base = empty($this->config['search_base']) ?
			$GLOBALS['egw_info']['server']['ldap_context'] : $this->config['search_base'];
		$sri = ldap_search($ldap, $base, $filter, array($this->config['schema']));

		if ($sri)
		{
			$allValues = ldap_get_entries($ldap, $sri);
			if ($this->debug) error_log(__METHOD__."('$user') --> ldap_search(, '$base', '$filter') --> ldap_get_entries=".array2string($allValues[0]));

			foreach($allValues as $key => $values)
			{
				if ($key === 'count') continue;

				// groups are always active (if they have an email) and allways forwardOnly
				if (in_array('posixGroup', $values['objectclass']))
				{
					$accountStatus = 'active';
					$deliveryMode = 'forwardOnly';
				}
				else	// for users we have to check the attributes
				{
					if ($this->config['mail_enable_attr'])
					{
						$accountStatus = isset($values[$this->config['mail_enable_attr']]) &&
							($this->config['mail_enabled'] && $values[$this->config['mail_enable_attr']][0] == $this->config['mail_enabled'] ||
							!$this->config['mail_enabled'] && $values[$this->config['alias_attr']]['count'] > 0) ? 'active' : '';
					}
					else
					{
						$accountStatus = $values[$this->config['alias_attr']]['count'] > 0 ? 'active' : '';
					}
					if ($this->config['forward_only_attr'])
					{
						$deliveryMode = isset($values[$this->config['forward_only_attr']]) &&
							($this->config['forward_only'] && $values[$this->config['forward_only_attr']][0] == $this->config['forward_only'] ||
							!$this->config['forward_only'] && $values[$this->config['forward_only_attr']]['count'] > 0) ? 'forwardOnly' : '';
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
					if ($deliveryMode != 'forwardOnly')
					{
						$userData['uid'][] = $values['uid'][0];
						if ($this->config['mailbox_attr'] && isset($values[$this->config['mailbox_attr']]))
						{
							$userData['mailbox'][] = $values[$this->config['mailbox_attr']][0];
						}
					}
					if ($this->config['forward_attr'] && $values[$this->config['forward_attr']])
					{
						$userData['forward'] = array_merge((array)$userData['forward'], $values[$this->config['forward_attr']]);
						unset($userData['forward']['count']);
					}
				}

				// regular user-data can only be from users, NOT groups
				if (in_array('posixGroup', $values['objectclass'])) continue;

				$userData['mailLocalAddress'] = $values['mail'][0];
				$userData['accountStatus'] = $accountStatus;

				if ($this->config['alias_attr'])
				{
					$userData['mailAlternateAddress']	= (array)$values[$this->config['alias_attr']];
					unset($userData['mailAlternateAddress']['count']);
				}
				else
				{
					$userData['mailAlternateAddress']	= (array)$values['mail'];
					unset($userData['mailAlternateAddress']['count']);
					unset($userData['mailAlternateAddress'][0]);
					$userData['mailAlternateAddress']	= array_values($userData['mailAlternateAddress']);
				}

				$userData['mailForwardingAddress']	= $this->config['forward_attr'] ? $values[$this->config['forward_attr']] : array();
				unset($userData['mailForwardingAddress']['count']);

				if ($this->config['mailbox_attr']) $userData[$this->config['mailbox_attr']]	= $values[$this->config['mailbox_attr']][0];

				if ($this->config['forward_only_attr'])
				{
					$userData['deliveryMode'] = isset($values[$this->config['forward_only_attr']]) &&
						($this->config['forward_only'] && $values[$this->config['forward_only_attr']][0] == $this->config['forward_only'] ||
						!$this->config['forward_only'] && $values[$this->config['forward_only_attr']]['count'] > 0) ? 'forwardOnly' : '';
				}
				else
				{
					$userData['deliveryMode'] = '';
				}
				// eg. suse stores all email addresses as aliases
				if ($this->config['require_mail_as_alias'] &&
					($k = array_search($userData['mailLocalAddress'],$userData['mailAlternateAddress'])) !== false)
				{
					unset($userData['mailAlternateAddress'][$k]);
				}

				if ($this->config['quota_attr'] && isset($values[$this->config['quota_attr']]))
				{
					$userData['quotaLimit'] = $values[$this->config['quota_attr']][0] / 1048576;
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
	 * @return boolean true on success, false on error writing to ldap
	 */
	function setUserData($_uidnumber, array $_mailAlternateAddress, array $_mailForwardingAddress, $_deliveryMode, $_accountStatus, $_mailLocalAddress, $_quota)
	{
		$filter = 'uidnumber='.(int)$_uidnumber;

		$ldap = $GLOBALS['egw']->ldap->ldapConnect();

		if (!($sri = @ldap_search($ldap,$GLOBALS['egw_info']['server']['ldap_context'],$filter)))
		{
			return false;
		}
		$allValues 	= ldap_get_entries($ldap, $sri);

		$accountDN 	= $allValues[0]['dn'];
		$uid	   	= $allValues[0]['uid'][0];
		$objectClasses	= $allValues[0]['objectclass'];

		unset($objectClasses['count']);

		if(!in_array($this->config['schema'],$objectClasses) && !in_array(strtolower($this->config['schema']),$objectClasses))
		{
			$objectClasses[]	= $this->config['schema'];
			$newData['objectclass']	= $objectClasses;
		}

		sort($_mailAlternateAddress);
		sort($_mailForwardingAddress);

		$newData['mail'] = $_mailLocalAddress;
		// does schema have explicit alias attribute
		if ($this->config['alias_attr'])
		{
			$newData[$this->config['alias_attr']] = (array)$_mailAlternateAddress;

			// all email must be stored as alias for suse
			if ($this->config['require_mail_as_alias'] && !in_array($_mailLocalAddress,(array)$_mailAlternateAddress))
			{
				$newData[$this->config['alias_attr']][] = $_mailLocalAddress;
			}
		}
		// or de we add them - if existing - to mail attr
		elseif ($_mailAlternateAddress)
		{
			$newData['mail'] = array_merge((array)$newData['mail'],(array)$_mailAlternateAddress);
		}
		// does schema support to store forwards
		if ($this->config['forward_attr'])
		{
			$newData[$this->config['forward_attr']] = (array)$_mailForwardingAddress;
		}
		// does schema support only forwarding incomming mail
		if ($this->config['forward_only_attr'])
		{
			$newData[$this->config['forward_only_attr']]	= $_deliveryMode ? $this->config['forward_only'] : array();
		}
		// does schema support enabling/disabling mail via attribute
		if ($this->config['mail_enable_attr'])
		{
			$newData[$this->config['mail_enable_attr']]	= $_accountStatus ? $this->config['mail_enabled'] : array();
		}
		// does schema support an explicit mailbox name --> set it with $uid@$domain
		if ($this->config['mailbox_attr'] && empty($allValues[0][$this->config['mailbox_attr']][0]))
		{
			$newData[$this->config['mailbox_attr']] = $this->mailbox_addr(array(
				'account_id' => $_uidnumber,
				'account_lid' => $uid,
				'account_email' => $_mailLocalAddress,
			));
		}
		if ($this->config['quota_attr'])
		{
			$newData[$this->config['quota_attr']]	= (int)$_quota >= 0 ? (int)$_quota*1048576 : array();
		}
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
		$ds = $GLOBALS['egw']->ldap->ldapConnect();
		$filter 	= sprintf('(&(uidnumber=%d)(objectclass=posixAccount))',$_accountID);
		$attributes	= array('dn',$this->config['forward_attr'],'objectclass');
		if ($this->config['forward_only_attr'])
		{
			$attributes[] = $this->config['forward_only_attr'];
		}
		$sri = ldap_search($ds, $GLOBALS['egw_info']['server']['ldap_context'], $filter, $attributes);

		if ($sri)
		{
			$newData = array();
			$allValues = ldap_get_entries($ds, $sri);
			$objectClasses  = $allValues[0]['objectclass'];
			$newData['objectclass']	= $allValues[0]['objectclass'];

			unset($newData['objectclass']['count']);

			if(!in_array($this->config['schema'],$objectClasses))
			{
				$newData['objectclass'][] = $this->config['schema'];
			}
			if ($this->config['forward_attr'])
			{
				if(!empty($_forwardingAddress))
				{
					if(is_array($allValues[0][$this->config['forward_attr']]))
					{
						$newData[$this->config['forward_attr']] = $allValues[0][$this->config['forward_attr']];
						unset($newData[$this->config['forward_attr']]['count']);
						$newData[$this->config['forward_attr']][0] = $_forwardingAddress;
					}
					else
					{
						$newData[$this->config['forward_attr']] = (array)$_forwardingAddress;
					}
					if ($this->config['forward_only_attr'])
					{
						$newData['deliverymode'] = $_keepLocalCopy == 'yes' ? array() : $this->config['forward_only'];
					}
				}
				else
				{
					$newData[$this->config['forward_attr']] = array();
				}
			}
			if ($this->debug) error_log(__METHOD__.'('.array2string(func_get_args()).") --> ldap_mod_replace(,'$accountDN',".array2string($newData).')');

			return ldap_modify ($ds, $allValues[0]['dn'], $newData);
		}
	}
}
