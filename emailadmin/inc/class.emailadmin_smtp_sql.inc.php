<?php
/**
 * EGroupware EMailAdmin: SMTP configuration / mail accounts via SQL
 *
 * @link http://www.stylite.de
 * @package emailadmin
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2012-13 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * SMTP configuration / mail accounts via SQL
 */
class emailadmin_smtp_sql extends emailadmin_smtp
{
	/**
	 * Label shown in EMailAdmin
	 */
	const DESCRIPTION = 'SQL';

	/**
	 * Capabilities of this class (pipe-separated): default, forward
	 */
	const CAPABILITIES = 'default|forward';

	/**
	 * Reference to global db object
	 *
	 * @var egw_db
	 */
	protected $db;

	/**
	 * Name of table
	 */
	const TABLE = 'egw_mailaccounts';
	/**
	 * Name of app our table belongs to
	 */
	const APP = 'emailadmin';
	/**
	 * Values for mail_type column
	 *
	 * enabled and delivery must have smaller values then alias, forward or mailbox (getUserData depend on it)!
	 */
	const TYPE_ENABLED = 0;
	const TYPE_DELIVERY = 1;
	const TYPE_QUOTA = 2;
	const TYPE_ALIAS = 3;
	const TYPE_FORWARD = 4;
	const TYPE_MAILBOX = 5;

	/**
	 * Constructor
	 *
	 * @param string $defaultDomain=null
	 */
	function __construct($defaultDomain=null)
	{
		parent::__construct($defaultDomain);

		$this->db = $GLOBALS['egw']->db;
	}

	/**
	 * Get all email addresses of an account
	 *
	 * @param string $_accountName
	 * @return array
	 */
	function getAccountEmailAddress($_accountName)
	{
		$emailAddresses	= parent::getAccountEmailAddress($_accountName);

		if (($account_id = $this->accounts->name2id($_accountName, 'account_lid', 'u')))
		{
			foreach($this->db->select(self::TABLE, 'mail_value', array(
				'account_id' => $account_id,
				'mail_type' => self::TYPE_ALIAS,
			), __LINE__, __FILE__, false, 'ORDER BY mail_value', self::APP) as $row)
			{
				$emailAddresses[] = array (
					'name'		=> $emailAddresses[0]['name'],
					'address'	=> $row['mail_value'],
					'type'		=> 'alternate',
				);
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

		if (is_numeric($user) && $this->accounts->exists($user))
		{
			$account_id = $user;
		}
		elseif (strpos($user, '@') === false)
		{
			$account_id = $this->accounts->name2id($user, 'account_lid', 'u');
		}
		else	// email address
		{
			// check with primary email address
			if (($account_id = $this->accounts->name2id($user, 'account_email')))
			{
				$account_id = array($account_id);
			}
			else
			{
				$account_id = array();
			}
			// always allow username@domain
			list($account_lid) = explode('@', $user);
			if ($match_uid_at_domain && ($id = $this->accounts->name2id($account_lid, 'account_lid')) && !in_array($id, $account_id))
			{
				$account_id[] = $id;
			}
			foreach($this->db->select(self::TABLE, 'account_id', array(
				'mail_type' => self::TYPE_ALIAS,
				'mail_value' => $user,
			), __LINE__, __FILE__, false, '', self::APP) as $row)
			{
				if (!in_array($row['account_id'], $account_id)) $account_id[] = $row['account_id'];
			}
			//error_log(__METHOD__."('$user') account_id=".array2string($account_id));
		}
		if ($account_id)
		{
			if (!is_array($account_id))
			{
				$userData['mailLocalAddress'] = $this->accounts->id2name($account_id, 'account_email');
			}
			$enabled = $forwardOnly = array();
			foreach($this->db->select(self::TABLE, '*', array(
				'account_id' => $account_id,
			), __LINE__, __FILE__, false, 'ORDER BY mail_type,mail_value', self::APP) as $row)
			{
				switch($row['mail_type'])
				{
					case self::TYPE_ENABLED:
						$userData['accountStatus'] = $row['mail_value'];
						$enabled[$row['account_id']] = $row['mail_value'] == self::MAIL_ENABLED;
						break;

					case self::TYPE_DELIVERY:
						$userData['deliveryMode'] = !strcasecmp($row['mail_value'], self::FORWARD_ONLY) ? emailadmin_smtp::FORWARD_ONLY : '';
						$forwardOnly[$row['account_id']] = !strcasecmp($row['mail_value'], self::FORWARD_ONLY);
						break;

					case self::TYPE_QUOTA:
						$userData['quotaLimit'] = $row['mail_value'];
						break;

					case self::TYPE_ALIAS:
						$userData['mailAlternateAddress'][] = $row['mail_value'];
						break;

					case self::TYPE_FORWARD:
						$userData['mailForwardingAddress'][] = $row['mail_value'];
						if ($row['account_id'] < 0 || $enabled[$row['account_id']])
						{
							$userData['forward'][] = $row['mail_value'];
						}
						break;

					case self::TYPE_MAILBOX:
						$userData['mailMessageStore'] = $row['mail_value'];
						//error_log(__METHOD__."('$user') row=".array2string($row).', enabled[$row[account_id]]='.array2string($enabled[$row['account_id']]).', forwardOnly[$row[account_id]]='.array2string($forwardOnly[$row['account_id']]));
						if ($row['account_id'] > 0 && $enabled[$row['account_id']] && !$forwardOnly[$row['account_id']])
						{
							$userData['uid'][] = $this->accounts->id2name($row['account_id'], 'account_lid');
							$userData['mailbox'][] = $row['mail_value'];
						}
						break;
				}
			}
			// if query by email-address (not a regular call from fmail)
			if (is_array($account_id))
			{
				// add group-members for groups as forward (that way we dont need to store&update them)
				foreach($account_id as $id)
				{
					if ($id < 0 && ($members = $this->accounts->members($id, true)))
					{
						foreach($members as $member)
						{
							if (($email = $this->accounts->id2name($member, 'account_email')) && !in_array($email, (array)$userData['forward']))
							{
								$userData['forward'][] = $email;
							}
						}
					}
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
	 * @param boolean $_forwarding_only=false true: store only forwarding info, used internally by saveSMTPForwarding
	 * @param string $_setMailbox=null used only for account migration
	 * @return boolean true on success, false on error writing to ldap
	 */
	function setUserData($_uidnumber, array $_mailAlternateAddress, array $_mailForwardingAddress, $_deliveryMode,
		$_accountStatus, $_mailLocalAddress, $_quota, $_forwarding_only=false, $_setMailbox=null)
	{
		if ($this->debug) error_log(__METHOD__."($_uidnumber, ".array2string($_mailAlternateAddress).', '.array2string($_mailForwardingAddress).", '$_deliveryMode', '$_accountStatus', '$_mailLocalAddress', $_quota, forwarding_only=".array2string($_forwarding_only).') '.function_backtrace());

		if (!$_forwarding_only && $this->accounts->id2name($_uidnumber, 'account_email') !== $_mailLocalAddress)
		{
			$account = $this->accounts->read($_uidnumber);
			$account['account_email'] = $_mailLocalAddress;
			$this->accounts->save($account);
		}
		$flags = array(
			self::TYPE_DELIVERY => $_deliveryMode,
			self::TYPE_ENABLED => $_accountStatus,
			self::TYPE_QUOTA => $_quota,
		);
		$where = array('account_id' => $_uidnumber);
		if ($_forwarding_only) $where['mail_type'] = array(self::TYPE_FORWARD, self::TYPE_DELIVERY);
		// find all invalid values: either delete or update them
		$delete_ids = array();
		foreach($this->db->select(self::TABLE, '*', $where, __LINE__, __FILE__, false, '', self::APP) as $row)
		{
			switch($row['mail_type'])
			{
				case self::TYPE_ALIAS:
					$new_addresses =& $_mailAlternateAddress;
					// fall-throught
				case self::TYPE_FORWARD:
					if ($row['mail_type'] == self::TYPE_FORWARD) $new_addresses =& $_mailForwardingAddress;
					if (($key = array_search($row['mail_value'], $new_addresses)) === false)
					{
						$delete_ids[] = $row['mail_id'];
					}
					else
					{
						unset($new_addresses[$key]);	// no need to store
					}
					break;

				case self::TYPE_MAILBOX:
					$mailbox = $row['mail_value'];
					break;

				case self::TYPE_QUOTA:
				case self::TYPE_DELIVERY:
				case self::TYPE_ENABLED:
					//error_log(__METHOD__.": ".__LINE__." row=".array2string($row).", flags['$row[mail_type]']=".array2string($flags[$row['mail_type']]));
					if ($row['mail_value'] != $flags[$row['mail_type']])
					{
						if ($flags[$row['mail_type']])
						{
							$this->db->update(self::TABLE, array(
								'mail_value' => $flags[$row['mail_type']],
							), array(
								'mail_id' => $row['mail_id'],
							), __LINE__, __FILE__, self::APP);
						}
						else
						{
							$delete_ids[] = $row['mail_id'];
						}
					}
					unset($flags[$row['mail_type']]);
					break;
			}
		}
		if ($delete_ids)
		{
			$this->db->delete(self::TABLE, array('mail_id' => $delete_ids), __LINE__, __FILE__, self::APP);
		}
		// set mailbox address, if explicitly requested by $_setMailbox parameter
		if ($_setMailbox)
		{
			$flags[self::TYPE_MAILBOX] = $_setMailbox;
		}
		// set mailbox address, if not yet set
		elseif (!$_forwarding_only && empty($mailbox))
		{
			$flags[self::TYPE_MAILBOX] = $this->mailbox_addr(array(
				'account_id' => $_uidnumber,
				'account_lid' => $this->accounts->id2name($_uidnumber, 'account_lid'),
				'account_email' => $_mailLocalAddress,
			));
		}
		// store all new values
		foreach($flags+array(
			self::TYPE_ALIAS => $_mailAlternateAddress,
			self::TYPE_FORWARD => $_mailForwardingAddress,
		) as $type => $values)
		{
			if ($values && (!$_forwarding_only || in_array($type, array(self::TYPE_FORWARD, self::TYPE_DELIVERY))))
			{
				foreach((array)$values as $value)
				{
					$this->db->insert(self::TABLE, array(
						'account_id' => $_uidnumber,
						'mail_type' => $type,
						'mail_value' => $value,
					), false, __LINE__, __FILE__, self::APP);
				}
			}
		}
		return true;
	}

	/**
	 * Hook called on account deletion
	 *
	 * @param array $_hookValues values for keys 'account_lid', 'account_id'
	 * @return boolean true on success, false on error
	 */
	function deleteAccount($_hookValues)
	{
		$this->db->delete(self::TABLE, array('account_id' => $_hookValues['account_id']), __LINE__, __FILE__);

		return true;
	}
}
