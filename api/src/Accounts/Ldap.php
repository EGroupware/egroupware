<?php
/**
 * API - accounts LDAP backend
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de> complete rewrite in 6/2006
 *
 * This class replaces the former accounts_ldap class written by
 * Joseph Engo <jengo@phpgroupware.org>, Lars Kneschke <lkneschke@phpgw.de>,
 * Miles Lott <milos@groupwhere.org> and Bettina Gille <ceb@phpgroupware.org>.
 * Copyright (C) 2000 - 2002 Joseph Engo, Lars Kneschke
 * Copyright (C) 2003 Lars Kneschke, Bettina Gille
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage accounts
 */

namespace EGroupware\Api\Accounts;

use EGroupware\Api;

// explicitly reference classes still in phpgwapi or old structure
use setup_cmd_ldap;

/**
 * LDAP Backend for accounts
 *
 * The LDAP backend of the accounts class now stores accounts, groups and the memberships completely in LDAP.
 * It does NO longer use the ACL class/table for group membership information.
 * Nor does it use the phpgwAcounts schema (part of that information is stored via shadowAccount now).
 *
 * A user is recognised by eGW, if he's in the user_context tree AND has the posixAccount object class AND
 * matches the LDAP search filter specified in setup >> configuration.
 * A group is recognised by eGW, if it's in the group_context tree AND has the posixGroup object class AND
 * - if specified - matches the LDAP group filter.
 * The group members are stored as memberuid's.
 *
 * The (positive) group-id's (gidnumber) of LDAP groups are mapped in this class to negative numeric
 * account_id's to not conflict with the user-id's, as both share in eGW internally the same numberspace!
 *
 * @author Ralf Becker <rb@egroupware.org>
 * @license https://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @access internal only use the interface provided by the accounts class
 */
class Ldap
{
	use LdapVlvSortRequestTrait;

	/**
	 * Timestamps ldap => egw used in several places
	 *
	 * @var string[]
	 */
	public $timestamps2egw = [
		'createtimestamp' => 'account_created',
		'modifytimestamp' => 'account_modified',
		'shadowexpire' => 'account_expires',
		'shadowlastchange' => 'account_lastpwd_change',
		//'lastlogon' => 'account_lastlogin',
	];

	/**
	 * Other attributes sorted by their default matching rule
	 */
	public $other2egw = [
		'uidnumber' => 'account_id',
		'gidnumber' => 'account_primary_group',
	];

	/**
	 * String attributes which can be sorted by caseIgnoreMatch ldap => egw
	 *
	 * @var string[]
	 */
	public $attributes2egw = [
		'uid' => 'account_lid',
		'sn'  => 'account_lastname',
		'givenname' => 'account_firstname',
		'cn'  => 'account_fullname',
		'email' => 'account_email',
	];

	/**
	 * Name of mail attribute
	 */
	const MAIL_ATTR = 'mail';
	/**
	 * resource with connection to the ldap server
	 *
	 * @var resource|object
	 */
	var $ds;
	/**
	 * LDAP context for users, eg. ou=account,dc=domain,dc=com
	 *
	 * @var string
	 */
	var $user_context;
	/**
	 * LDAP search filter for user accounts, eg. (uid=%user)
	 *
	 * @var string
	 */
	var $account_filter;
	/**
	 * LDAP context for groups, eg. ou=groups,dc=domain,dc=com
	 *
	 * @var string
	 */
	var $group_context;
	/**
	 * Additional LDAP search filter for groups
	 *
	 * @var string
	 */
	var $group_filter;
	/**
	 * total number of found entries from get_list method
	 *
	 * @var int
	 */
	var $total;

	/**
	 * required object-classes for user and groups
	 *
	 * @var array
	 */
	var $requiredObjectClasses = array(
		'user' => array(
			'top','person','organizationalperson','inetorgperson','posixaccount','shadowaccount'
		),
		'user-if-supported' => array(	// these classes get added, if server supports them
			'mozillaabpersonalpha', 'mozillaorgperson', 'evolutionperson',
			'univentionperson', 'univentionmail', array('univentionobject', 'univentionObjectType' => 'users/user'),
		),
		'group' => array(
			'top','posixgroup','groupofnames'
		),
		'group-if-supported' => array(	// these classes get added, if servers supports them
			'univentiongroup', array('univentionobject', 'univentionObjectType' => 'groups/group'),
		)
	);
	/**
	 * Classes allowing to set a mail-address for a group and specify the memberaddresses as forwarding addresses
	 *
	 * $objectclass => $forward
	 * $objectclass => [$forward, $extra_attr, $keep_objectclass]
	 * $forward          : name of attribute to set forwards for members mail addresses, false if not used/required
	 * $extra_attr       : required attribute (eg. 'uid'), which need to be set, default none
	 * $keep_objectclass : true to not remove objectclass, if not mail set
	 *
	 * @var array
	 */
	var $group_mail_classes = array(
		'dbmailforwardingaddress' => 'mailforwardingaddress',
		'dbmailuser' => array('mailforwardingaddress','uid'),
		'qmailuser' => array('mailforwardingaddress','uid'),
		'mailaccount' => 'mailalias',
		'univentiongroup' => array(false, false, true),
	);

	/**
	 * Reference to our frontend
	 *
	 * @var Api\Accounts
	 */
	protected $frontend;

	/**
	 * Instance of the ldap class
	 *
	 * @var Api\Ldap
	 */
	private $ldap;

	/**
	 * does backend allow to change account_lid
	 */
	const CHANGE_ACCOUNT_LID = true;

	/**
	 * does backend require password to be set, before allowing to enable an account
	 */
	const REQUIRE_PASSWORD_FOR_ENABLE = false;

	/**
	 * Constructor
	 *
	 * @param Api\Accounts $frontend reference to the frontend class, to be able to call it's methods if needed
	 */
	function __construct(Api\Accounts $frontend)
	{
		$this->frontend = $frontend;

		$this->ds = $this->ldap_connection();

		$this->user_context  = $this->frontend->config['ldap_context'];
		$this->account_filter = $this->frontend->config['ldap_search_filter'];
		$this->group_context = $this->frontend->config['ldap_group_context'] ?: $this->frontend->config['ldap_context'];
		$this->group_filter = $this->frontend->config['ldap_group_filter'];
		if (!empty($this->group_filter) && !($this->group_filter[0] === '(' && substr($this->group_filter, -1) === ')'))
		{
			$this->group_filter = '('.$this->group_filter.')';
		}
	}

	/**
	 * Get connection to ldap server and optionally reconnect
	 *
	 * @param boolean $reconnect =false true: reconnect even if already connected
	 * @return resource|object
	 * @throws Api\Exception\AssertionFailed
	 * @throws Api\Exception\NoPermission
	 */
	function ldap_connection(bool $reconnect = false)
	{
		$this->ldap = Api\Ldap::factory(false, $this->frontend->config['ldap_host'],
			$this->frontend->config['ldap_root_dn'],$this->frontend->config['ldap_root_pw'], $reconnect);

		$this->serverinfo = $this->ldap->getLDAPServerInfo();

		return $this->ldap->ds;
	}

	/**
	 * Reads the data of one account
	 *
	 * @param int $account_id numeric account-id
	 * @return array|boolean array with account data (keys: account_id, account_lid, ...) or false if account not found
	 */
	function read($account_id)
	{
		if (!(int)$account_id) return false;

		if ($account_id < 0)
		{
			return $this->_read_group($account_id);
		}
		return $this->_read_user($account_id);
	}

	/**
	 * Saves / adds the data of one account
	 *
	 * If no account_id is set in data the account is added and the new id is set in $data.
	 *
	 * @param array $data array with account-data
	 * @return int|boolean the account_id or false on error
	 */
	function save(&$data)
	{
		$is_group = $data['account_id'] < 0 || $data['account_type'] === 'g';

		$data_utf8 = Api\Translation::convert($data,Api\Translation::charset(),'utf-8');
		$members = $data['account_members'];

		if (!is_object($this->serverinfo))
		{
			$this->serverinfo = $this->ldap->getLDAPServerInfo();
		}
		// common code for users and groups
		// checks if account_lid (dn) has been changed or required objectclass'es are missing
		if ($data_utf8['account_id'] && $data_utf8['account_lid'])
		{
			// read the entry first, to check if the dn (account_lid) has changed
			$sri = $is_group ? ldap_search($this->ds,$this->group_context,'gidnumber='.abs($data['account_id'])) :
				ldap_search($this->ds,$this->user_context,'uidnumber='.$data['account_id']);
			$old = ldap_get_entries($this->ds, $sri);

			if (!$old['count'])
			{
				unset($old);
			}
			else
			{
				$old = Api\Ldap::result2array($old[0]);
				$old['objectclass'] = array_map('strtolower', $old['objectclass']);
				$key = false;
				if ($is_group && ($key = array_search('namedobject',$old['objectclass'])) !== false ||
					$is_group && ($old['cn'] != $data_utf8['account_lid'] || substr($old['dn'],0,3) != 'cn=') ||
					!$is_group && ($old['uid'] != $data_utf8['account_lid'] || substr($old['dn'],0,4) != 'uid='))
				{
					// query the memberships to set them again later
					if (!$is_group)
					{
						$memberships = $this->memberships($data['account_id']);
					}
					else
					{
						$members = $old ? $old['memberuid'] : $this->members($data['account_id']);
					}
					// if dn has changed --> delete the old entry, as we cant rename the dn
					$this->delete($data['account_id']);
					unset($old['dn']);
					// removing the namedObject object-class, if it's included
					if ($key !== false) unset($old['objectclass'][$key]);
					$to_write = $old;
					unset($old);
				}
			}
		}
		if (!$data['account_id'])	// new account
		{
			if (!($data['account_id'] = $data_utf8['account_id'] = $this->_get_nextid($is_group ? 'g' : 'u')))
			{
				return false;
			}
		}
		// check if we need to write the objectclass: new entry or required object classes are missing
		if (!$old || array_diff($this->requiredObjectClasses[$is_group ? 'group' : 'user'],$old['objectclass']))
		{
			// additional objectclasse might be already set in $to_write or $old
			if (!is_array($to_write['objectclass']))
			{
				$to_write['objectclass'] = $old ? $old['objectclass'] : array();
			}
			if (!$old)	// for new accounts add additional addressbook object classes, if supported by server
			{			// as setting them later might loose eg. password, if we are not allowed to read them
				foreach($this->requiredObjectClasses[$is_group?'group-if-supported':'user-if-supported'] as $additional)
				{
					$add = array();
					if (is_array($additional))
					{
						$add = $additional;
						$additional = array_shift($add);
					}
					if ($this->serverinfo->supportsObjectClass($additional))
					{
						$to_write['objectclass'][] = $additional;
						if ($add) $to_write += $add;
					}
				}
			}
			$to_write['objectclass'] = array_values(array_unique(array_merge($to_write['objectclass'],
				$this->requiredObjectClasses[$is_group ? 'group' : 'user'])));
		}
		if (!($dn = $old['dn']))
		{
			if (!$data['account_lid']) return false;

			$dn = $is_group ? 'cn='.$data_utf8['account_lid'].','.$this->group_context :
				'uid='.$data_utf8['account_lid'].','.$this->user_context;
		}
		// now we merge the user or group data
		if ($is_group)
		{
			$to_write = $this->_merge_group($to_write, $data_utf8, $old);
			$data['account_type'] = 'g';

			$objectclass = $old ? $old['objectclass'] : $to_write['objectclass'];
			if ($members || !$old && array_intersect(array('groupofnames','groupofuniquenames','univentiongroup'), $objectclass))
			{
				$to_write = array_merge($to_write, $this->set_members($members, $data['account_id'], $objectclass, $dn));
			}
			// check if we should set a mail address and forwards for each member
			foreach($this->group_mail_classes as $objectclass => $forward)
			{
				$extra_attr = false;
				$keep_objectclass = false;
				if (is_array($forward)) list($forward,$extra_attr,$keep_objectclass) = $forward;

				if ($this->serverinfo->supportsObjectClass($objectclass) &&
					($old && in_array($objectclass,$old['objectclass']) || $data_utf8['account_email'] || $old[static::MAIL_ATTR]))
				{
					if ($data_utf8['account_email'])	// setting an email
					{
						if (!in_array($objectclass,$old ? $old['objectclass'] : $to_write['objectclass']))
						{
							if ($old) $to_write['objectclass'] = $old['objectclass'];
							$to_write['objectclass'][] = $objectclass;
						}
						if ($extra_attr) $to_write[$extra_attr] = $data_utf8['account_lid'];
						$to_write[static::MAIL_ATTR] = $data_utf8['account_email'];

						if ($forward)
						{
							if (!$members) $members = $this->members($data['account_id']);
							$to_write[$forward] = array();
							foreach (array_keys($members) as $member)
							{
								if (($email = $this->id2name($member,'account_email')))
								{
									$to_write[$forward][] = $email;
								}
							}
						}
					}
					elseif($old)	// remove the mail and forwards only for existing entries
					{
						$to_write[static::MAIL_ATTR] = array();
						if ($forward) $to_write[$forward] = array();
						if ($extra_attr) $to_write[$extra_attr] = array();
						if (!$keep_objectclass && ($key = array_search($objectclass,$old['objectclass'])))
						{
							$to_write['objectclass'] = $old['objectclass'];
							unset($to_write['objectclass'][$key]);
							$to_write['objectclass'] = array_values($to_write['objectclass']);
						}
					}
					break;
				}
			}

		}
		else
		{
			$to_write = $this->_merge_user($to_write,$data_utf8,!$old);
			// make sure multiple email-addresses in the mail attribute "survive"
			if (isset($to_write[static::MAIL_ATTR]) && is_array($old[static::MAIL_ATTR]) && count($old[static::MAIL_ATTR]) > 1)
			{
				$mail = $old[static::MAIL_ATTR];
				$mail[0] = $to_write[static::MAIL_ATTR];
				$to_write[static::MAIL_ATTR] = array_values(array_unique($mail));
			}
			$data['account_type'] = 'u';

			// Check if an account already exists as system user, and if it does deny creation
			if (!$GLOBALS['egw_info']['server']['ldap_allow_systemusernames'] && !$old &&
				function_exists('posix_getpwnam') && posix_getpwnam($data['account_lid']))
			{
				throw new Api\Exception\WrongUserinput(lang('There already is a system-user with this name. User\'s should not have the same name as a systemuser'));
			}
		}

		// remove memberuid when adding a group
		if(!$old && is_array($to_write['memberuid']) && empty($to_write['memberuid'])) {
			unset($to_write['memberuid']);
		}
		// modifying or adding the entry
		if ($old && !@ldap_modify($this->ds,$dn,$to_write) ||
			!$old && !@ldap_add($this->ds,$dn,$to_write))
		{
			$err = true;
			if ($is_group && ($key = array_search('groupofnames',$to_write['objectclass'])) !== false)
			{
				// try again with removed groupOfNames stuff, as I cant detect if posixGroup is a structural object
				unset($to_write['objectclass'][$key]);
				$to_write['objectclass'] = array_values($to_write['objectclass']);
				unset($to_write['member']);
				$err = $old ? !ldap_modify($this->ds,$dn,$to_write) : !ldap_add($this->ds,$dn,$to_write);
			}
			if ($err)
			{
				error_log(__METHOD__."() ldap_".($old ? 'modify' : 'add')."(,'$dn',".array2string($to_write).") --> ldap_error()=".ldap_error($this->ds));
				return false;
			}
		}
		if ($memberships)
		{
			$this->set_memberships($memberships,$data['account_id']);
		}
		return $data['account_id'];
	}

	/**
	 * Delete one account, deletes also all acl-entries for that account
	 *
	 * @param int $account_id numeric account_id
	 * @return boolean true on success, false otherwise
	 */
	function delete($account_id)
	{
		if (!(int)$account_id) return false;

		if ($account_id < 0)
		{
			$sri = ldap_search($this->ds, $this->group_context, 'gidnumber=' . abs($account_id));
		}
		else
		{
			// remove the user's memberships
			$this->set_memberships(array(),$account_id);

			$sri = ldap_search($this->ds, $this->user_context, 'uidnumber=' . $account_id);
		}
		if (!$sri) return false;

		$allValues = ldap_get_entries($this->ds, $sri);
		if (!$allValues['count']) return false;

		return ldap_delete($this->ds, $allValues[0]['dn']);
	}

	/**
	 * Reads the data of one group
	 *
	 * @internal
	 * @param int $account_id numeric account-id (< 0 as it's for a group)
	 * @return array|boolean array with account data (keys: account_id, account_lid, ...) or false if account not found
	 */
	protected function _read_group($account_id)
	{
		$group = array();
		if (!is_object($this->serverinfo))
		{
			$this->serverinfo = $this->ldap->getLDAPServerInfo($this->frontend->config['ldap_host']);
		}
		foreach(array_keys($this->group_mail_classes) as $objectclass)
		{
			if ($this->serverinfo->supportsObjectClass($objectclass))
			{
				$group['mailAllowed'] = $objectclass;
				break;
			}
		}
		$sri = ldap_search($this->ds, $this->group_context,'(&(objectClass=posixGroup)(gidnumber=' . abs($account_id).'))',
			array('dn', 'gidnumber', 'cn', 'objectclass', static::MAIL_ATTR, 'memberuid', 'description'));

		$ldap_data = ldap_get_entries($this->ds, $sri);
		if (!$ldap_data['count'])
		{
			return false;	// group not found
		}
		$data = Api\Translation::convert($ldap_data[0],'utf-8');
		unset($data['objectclass']['count']);

		$group += array(
			'account_dn'        => $data['dn'],
			'account_id'        => -$data['gidnumber'][0],
			'account_lid'       => $data['cn'][0],
			'account_type'      => 'g',
			'account_firstname' => $data['cn'][0],
			'account_lastname'  => lang('Group'),
			'account_fullname'  => lang('Group').' '.$data['cn'][0],
			'objectclass'       => array_map('strtolower', $data['objectclass']),
			'account_email'     => $data[static::MAIL_ATTR][0],
			'members'           => array(),
			'account_description' => $data['description'][0],
		);

		if (isset($data['memberuid']))
		{
			unset($data['memberuid']['count']);

			foreach($data['memberuid'] as $lid)
			{
				if (($id = $this->name2id($lid, 'account_lid', 'u')))
				{
					$group['members'][$id] = $lid;
				}
			}
		}

		return $group;
	}

	/**
	 * Reads the data of one user
	 *
	 * @internal
	 * @param int $account_id numeric account-id
	 * @return array|boolean array with account data (keys: account_id, account_lid, ...) or false if account not found
	 */
	protected function _read_user($account_id)
	{
		// add account_filter to filter (user has to be '*', as we otherwise only search uid's)
		$account_filter = str_replace(array('%user', '%domain'), array('*', $GLOBALS['egw_info']['user']['domain']), $this->account_filter);

		$sri = ldap_search($this->ds, $this->user_context, '(&(objectclass=posixAccount)(uidnumber=' . (int)$account_id.")$account_filter)",
			array('dn','uidnumber','uid','gidnumber','givenname','sn','cn',static::MAIL_ATTR,'userpassword','telephonenumber',
				'shadowexpire','shadowlastchange','homedirectory','loginshell','createtimestamp','modifytimestamp'));

		$ldap_data = ldap_get_entries($this->ds, $sri);
		if (!$ldap_data['count'])
		{
			return false;	// user not found
		}
		$data = Api\Translation::convert($ldap_data[0],'utf-8');

		$utc_diff = date('Z');
		$user = array(
			'account_dn'        => $data['dn'],
			'account_id'        => (int)$data['uidnumber'][0],
			'account_lid'       => $data['uid'][0],
			'account_type'      => 'u',
			'account_primary_group' => -$data['gidnumber'][0],
			'account_firstname' => $data['givenname'][0],
			'account_lastname'  => $data['sn'][0],
			'account_email'     => $data[static::MAIL_ATTR][0],
			'account_fullname'  => $data['cn'][0],
			'account_pwd'       => $data['userpassword'][0],
			'account_phone'     => $data['telephonenumber'][0],
			// both status and expires are encoded in the single shadowexpire value in LDAP
			// - if it's unset an account is enabled AND does never expire
			// - if it's set to 0, the account is disabled
			// - if it's set to > 0, it will or already has expired --> account is active if it not yet expired
			// shadowexpire is in days since 1970/01/01 (equivalent to a timestamp (int UTC!) / (24*60*60)
			'account_status'    => isset($data['shadowexpire']) && $data['shadowexpire'][0]*24*3600+$utc_diff < time() ? false : 'A',
			'account_expires'   => isset($data['shadowexpire']) && $data['shadowexpire'][0] ? $data['shadowexpire'][0]*24*3600+$utc_diff : -1, // LDAP date is in UTC
			'account_lastpwd_change' => isset($data['shadowlastchange']) ? $data['shadowlastchange'][0]*24*3600+($data['shadowlastchange'][0]!=0?$utc_diff:0) : null,
			// lastlogin and lastlogin from are not availible via the shadowAccount object class
			// 'account_lastlogin' => $data['phpgwaccountlastlogin'][0],
			// 'account_lastloginfrom' => $data['phpgwaccountlastloginfrom'][0],
			'person_id'         => $data['uid'][0],	// id of associated contact
			'account_created' => isset($data['createtimestamp'][0]) ? self::accounts_ldap2ts($data['createtimestamp'][0]) : null,
			'account_modified' => isset($data['modifytimestamp'][0]) ? self::accounts_ldap2ts($data['modifytimestamp'][0]) : null,
		);

		if ($this->frontend->config['ldap_extra_attributes'])
		{
			$user['homedirectory']  = $data['homedirectory'][0];
			$user['loginshell']     = $data['loginshell'][0];
		}
		return $user;
	}

	/**
	 * Merges the group relevant account data from $data into $to_write
	 *
	 * @internal
	 * @param array $to_write data to write to ldap incl. objectclass ($data is NOT yet merged)
	 * @param array $data array with account-data in utf-8
	 * @return array merged data
	 */
	protected function _merge_group($to_write,$data,$old=null)
	{
		$to_write['gidnumber'] = abs($data['account_id']);
		$to_write['cn'] = $data['account_lid'];
		// do not overwrite existing description, if non is given
		if (isset($data['account_description']) &&
			// make sure NOT to unset description for new groups, give a "Protocol error"
			($old || !empty($data['account_description'])))
		{
			$to_write['description'] = !empty($data['account_description']) ? $data['account_description'] : array();
		}
		// to kope with various dependencies / requirements of objectclasses, simply write everything again
		foreach($old as $name => $value)
		{
			if (!isset($to_write[$name]) && !in_array($name, ['dn', 'objectclass']))
			{
				$to_write[$name] = $value;
			}
		}
		return $to_write;
	}

	/**
	 * Merges the user releavant account data from $data into $to_write
	 *
	 * @internal
	 * @param array $to_write data to write to ldap incl. objectclass ($data is NOT yet merged)
	 * @param array $data array with account-data in utf-8
	 * @param boolean $new_entry
	 * @return array merged data
	 */
	protected function _merge_user($to_write,$data,$new_entry)
	{
		$to_write['uidnumber'] = $data['account_id'];
		$to_write['uid']       = $data['account_lid'];
		$to_write['gidnumber'] = abs($data['account_primary_group']);
		if (!$new_entry || $data['account_firstname'])
		{
			$to_write['givenname'] = $data['account_firstname'] ? $data['account_firstname'] : array();
		}
		$to_write['sn']        = $data['account_lastname'];
		if (!$new_entry || $data['account_email'])
		{
			$to_write[static::MAIL_ATTR]  = $data['account_email'] ? $data['account_email'] : array();
		}
		$to_write['cn']        = $data['account_fullname'] ? $data['account_fullname'] : $data['account_firstname'].' '.$data['account_lastname'];

		$utc_diff = date('Z');
		if (isset($data['account_passwd']) && $data['account_passwd'])
		{
			if (preg_match('/^[a-f0-9]{32}$/', $data['account_passwd']))	// md5 --> ldap md5
			{
				$data['account_passwd'] = setup_cmd_ldap::hash_sql2ldap($data['account_passwd']);
			}
			elseif (!preg_match('/^\\{[a-z5]{3,5}\\}.+/i',$data['account_passwd']))	// if it's not already entcrypted, do so now
			{
				$data['account_passwd'] = Api\Auth::encrypt_ldap($data['account_passwd']);
			}
			$to_write['userpassword'] = $data['account_passwd'];
			$to_write['shadowlastchange'] = round((time()-$utc_diff) / (24*3600));
		}
		// both status and expires are encoded in the single shadowexpire value in LDAP
		// - if it's unset an account is enabled AND does never expire
		// - if it's set to 0, the account is disabled
		// - if it's set to > 0, it will or already has expired --> acount is active if it not yet expired
		// shadowexpire is in days since 1970/01/01 (equivalent to a timestamp (int UTC!) / (24*60*60)
		$shadowexpire = ($data['account_expires']-$utc_diff) / (24*3600);

		$to_write['shadowexpire'] = !$data['account_status'] ?
			($data['account_expires'] != -1 && $data['account_expires'] < time() ? round($shadowexpire) : 0) :
			($data['account_expires'] != -1 ? round($shadowexpire) : array());	// array() = unset value

		if ($new_entry && is_array($to_write['shadowexpire']) && !count($to_write['shadowexpire']))
		{
			unset($to_write['shadowexpire']);	// gives protocoll error otherwise
		}
		//error_log(__METHOD__.__LINE__.$data['account_lid'].'#'.$data['account_lastpwd_change'].'#');
		if ($data['account_lastpwd_change']) $to_write['shadowlastchange'] = round(($data['account_lastpwd_change']-$utc_diff)/(24*3600));
		if ($data['mustchangepassword'] == 1 || isset($data['account_lastpwd_change']) && $data['account_lastpwd_change'] == 0)
		{
			$to_write['shadowlastchange'] = 0;
		}
		// lastlogin and lastlogin from are not availible via the shadowAccount object class
		// $to_write['phpgwaccountlastlogin'] = $data['lastlogin'];
		// $to_write['phpgwaccountlastloginfrom'] = $data['lastloginfrom'];

		if ($this->frontend->config['ldap_extra_attributes'])
		{
			if (isset($data['homedirectory'])) $to_write['homedirectory']  = $data['homedirectory'];
			if (isset($data['loginshell'])) $to_write['loginshell'] = $data['loginshell'] ? $data['loginshell'] : array();
		}
		if (($new_entry || isset($to_write['homedirectory'])) && empty($to_write['homedirectory']))
		{
			$to_write['homedirectory']  = '/dev/null';	// is a required attribute of posixAccount
		}
		if ($new_entry && empty($to_write['loginshell']))
		{
			unset($to_write['loginshell']);	// setting array() for new entry gives "Protocol error", must not set it
		}
		return $to_write;
	}

	/**
	 * Searches / lists accounts: users and/or groups
	 *
	 * @param array with the following keys:
	 * @param $param['type'] string/int 'accounts', 'groups', 'owngroups' (groups the user is a member of), 'both'
	 *	or integer group-id for a list of members of that group
	 * @param $param['start'] int first account to return (returns offset or max_matches entries) or all if not set
	 * @param $param['order'] string column to sort after, default account_lid if unset
	 * @param $param['sort'] string 'ASC' or 'DESC', default 'ASC' if not set
	 * @param $param['query'] string to search for, no search if unset or empty
	 * @param $param['query_type'] string:
	 *	'all'   - query all fields for containing $param[query]
	 *	'start' - query all fields starting with $param[query]
	 *	'exact' - query all fields for exact $param[query]
	 *	'lid','firstname','lastname','email' - query only the given field for containing $param[query]
	 * @param $param['offset'] int - number of matches to return if start given, default use the value in the prefs
	 * @param $param['objectclass'] boolean return objectclass(es) under key 'objectclass' in each account
	 * @param $param['modified'] int if given minimum modification time
	 * @param $param['account_id'] int[] return only given account_id's
	 * @return array with account_id => data pairs, data is an array with account_id, account_lid, account_firstname,
	 *	account_lastname, person_id (id of the linked addressbook entry), account_status, account_expires, account_primary_group
	 */
	function search($param)
	{
		//error_log(__METHOD__."(".array2string($param).")");
		$account_search =& Api\Accounts::$cache['account_search'];

		// check if the query is cached
		$serial = Api\Accounts::cacheKey($param, $unl_serial);
		if (isset($account_search[$serial]))
		{
			$this->total = $account_search[$serial]['total'];
			return $account_search[$serial]['data'];
		}
		// if it's a limited query, check if the unlimited query is cached
		$start = $param['start'];
		if (!($maxmatchs = $GLOBALS['egw_info']['user']['preferences']['common']['maxmatchs'])) $maxmatchs = 15;
		if (!($offset = $param['offset'])) $offset = $maxmatchs;
		unset($param['start']);
		unset($param['offset']);

		if (isset($account_search[$unl_serial]))
		{
			$this->total = $account_search[$unl_serial]['total'];
			$sortedAccounts = $account_search[$unl_serial]['data'];
		}
		else	// we need to run the unlimited query
		{
			$query = Api\Ldap::quote(strtolower($param['query']));
			$order_by = ($param['order'] ?? null ? explode(',', $param['order'])[0] : 'account_lid').' '.($param['sort'] ?? null ?: 'ASC');

			$accounts = array();
			if($param['type'] !== 'groups')
			{
				$filter = "(&(objectclass=posixaccount)";
				if (!empty($query) && $query != '*')
				{
					switch ($param['query_type'])
					{
						case 'all':
						default:
							$query = '*' . $query;
						// fall-through
						case 'start':
							$query .= '*';
							// use now exact, as otherwise groups have "**pattern**", which dont match anything
							$param['query_type'] = 'exact';
						// fall-through
						case 'exact':
							$filter .= "(|(uid=$query)(sn=$query)(cn=$query)(givenname=$query)(mail=$query))";
							break;
						case 'firstname':
						case 'lastname':
						case 'lid':
						case 'email':
							$to_ldap = array(
								'firstname' => 'givenname',
								'lastname' => 'sn',
								'lid' => 'uid',
								'email' => static::MAIL_ATTR,
							);
							$filter .= '(' . $to_ldap[$param['query_type']] . '=*' . $query . '*)';
							break;
					}
				}
				// add account_filter to filter (user has to be '*', as we otherwise only search uid's)
				$filter .= str_replace(array('%user', '%domain'), array('*', $GLOBALS['egw_info']['user']['domain']), $this->account_filter);

				// return only group-members
				if (is_numeric($param['type']))
				{
					if (($members = Api\Accounts::getInstance()->members($param['type'], true)))
					{
						$filter .= '(|(uidNumber=' . implode(')(uidNumber=', $members) . '))';
					}
					else
					{
						$filter .= '(uidNumber=0)'; // to NOT find any user
					}
				}
				// only return given account_id's
				if (!empty($param['account_id']))
				{
					$filter .= '(|(uidNumber=' . implode(')(uidNumber=', (array)$param['account_id']) . '))';
				}
				if (!empty($param['modified']))
				{
					$filter .= "(modifytimestamp>=".gmdate('YmdHis', $param['modified']).".0Z)";
				}
				$filter .= ')';

				/*if ($param['type'] != 'both')
				{
					// follow:
					// - first query only few attributes for sorting and throwing away not needed results
					// - throw away & sort
					// - fetch relevant accounts with full information
					// - map and resolve
					$propertyMap = array(
						'account_id'        => 'uidnumber',
						'account_lid'       => 'uid',
						'account_firstname' => 'givenname',
						'account_lastname'  => 'sn',
						'account_email'     => 'email',
						'account_fullname'  => 'cn',
						'account_primary_group' => 'gidnumber',
					);
					$orders = explode(',',$param['order']);
					$order = isset($propertyMap[$orders[0]]) ? $propertyMap[$orders[0]] : 'uid';
					$sri = ldap_search($this->ds, $this->user_context, $filter,array('uid', $order));
					$fullSet = array();
					foreach (ldap_get_entries($this->ds, $sri) ?: [] as $key => $entry)
					{
						if ($key !== 'count') $fullSet[$entry['uid'][0]] = $entry[$order][0];
					}

					if (is_numeric($param['type'])) // return only group-members
					{
						$sri = ldap_search($this->ds,$this->group_context,"(&(objectClass=posixGroup)(gidnumber=" .
							abs($param['type']) . "))",array('memberuid'));
						$group = ldap_get_entries($this->ds, $sri);
						$fullSet = $group[0]['memberuid'] ? array_intersect_key($fullSet, array_flip($group[0]['memberuid'])) : array();
					}
					$totalcount = count($fullSet);

					$sortFn = $param['sort'] == 'DESC' ? 'arsort' : 'asort';
					$sortFn($fullSet);
					$relevantAccounts = is_numeric($start) ? array_slice(array_keys($fullSet), $start, $offset) : array_keys($fullSet);
					$filter = '(&(objectclass=posixaccount)(|(uid='.implode(')(uid=',$relevantAccounts).'))' . $this->account_filter.')';
					$filter = str_replace(array('%user','%domain'),array('*',$GLOBALS['egw_info']['user']['domain']),$filter);
				}*/

				if ($param['type'] !== 'groups')
				{
					$allValues = $this->vlvSortQuery($this->user_context, $filter,
						['uid', 'uidNumber', 'givenname', 'sn', static::MAIL_ATTR, 'shadowExpire', 'createtimestamp',
							'modifytimestamp', 'objectclass', 'gidNumber', 'jpegphoto'],
						$order_by, $start, $offset, $totalcount);

					$utc_diff = date('Z');
					foreach ($allValues ?: [] as $allVals)
					{
						$test = $allVals['uid'][0];
						if (!$this->frontend->config['global_denied_users'][$test] && $allVals['uid'][0])
						{
							$account = array(
								'account_dn' => $allVals['dn'],
								'account_id' => $allVals['uidnumber'][0],
								'account_lid' => Api\Translation::convert($allVals['uid'][0], 'utf-8'),
								'account_type' => 'u',
								'account_firstname' => Api\Translation::convert($allVals['givenname'][0], 'utf-8'),
								'account_lastname' => Api\Translation::convert($allVals['sn'][0], 'utf-8'),
								'account_status' => isset($allVals['shadowexpire'][0]) && $allVals['shadowexpire'][0] * 24 * 3600 - $utc_diff < time() ? false : 'A',
								'account_expires' => isset($allVals['shadowexpire']) && $allVals['shadowexpire'][0] ? $allVals['shadowexpire'][0] * 24 * 3600 + $utc_diff : -1, // LDAP date is in UTC
								'account_email' => $allVals[static::MAIL_ATTR][0],
								'account_created' => isset($allVals['createtimestamp'][0]) ? self::accounts_ldap2ts($allVals['createtimestamp'][0]) : null,
								'account_modified' => isset($allVals['modifytimestamp'][0]) ? self::accounts_ldap2ts($allVals['modifytimestamp'][0]) : null,
								'account_primary_group' => (string)-$allVals['gidnumber'][0],
								'account_has_photo' => !empty($allVals['jpegphoto'][0])
							);
							//error_log(__METHOD__."() ldap=".array2string($allVals)." --> account=".array2string($account));
							if ($param['active'] && !$this->frontend->is_active($account))
							{
								if (isset($totalcount)) --$totalcount;
								continue;
							}
							$account['account_fullname'] = Api\Accounts::format_username($account['account_lid'],
								$account['account_firstname'], $account['account_lastname'], $allVals['uidnumber'][0]);
							// return objectclass(es)
							if ($param['objectclass'])
							{
								$account['objectclass'] = array_map('strtolower', $allVals['objectclass']);
								unset($account['objectclass']['count']);
							}
							$accounts[$account['account_id']] = $account;
						}
					}
				}
			}
			if ($param['type'] === 'groups' || $param['type'] === 'both')
			{
				if(empty($query) || $query === '*')
				{
					$filter = "(&(objectclass=posixgroup)$this->group_filter";
				}
				else
				{
					switch($param['query_type'])
					{
						case 'all':
						default:
							$query = '*'.$query;
							// fall-through
						case 'start':
							$query .= '*';
							// fall-through
						case 'exact':
							break;
					}
					$filter = "(&(objectclass=posixgroup)(cn=$query)$this->group_filter";
				}
				if (!empty($param['modified']))
				{
					$filter .= "(modifytimestamp>=".gmdate('YmdHis', $param['modified']).".0Z)";
				}
				$filter .= ')';
				$allValues = $this->vlvSortQuery($this->group_context, $filter, ['cn', 'gidNumber'], $order_by, $start, $offset, $group_total);
				$totalcount += $group_total;
				foreach($allValues ?: [] as $allVals)
				{
					$test = $allVals['cn'][0];
					if (!$this->frontend->config['global_denied_groups'][$test] && $allVals['cn'][0])
					{
						$accounts[(string)-$allVals['gidnumber'][0]] = Array(
							'account_dn'        => $allVals['dn'],
							'account_id'        => -$allVals['gidnumber'][0],
							'account_lid'       => Api\Translation::convert($allVals['cn'][0],'utf-8'),
							'account_type'      => 'g',
							'account_firstname' => Api\Translation::convert($allVals['cn'][0],'utf-8'),
							'account_lastname'  => lang('Group'),
							'account_status'    => 'A',
							'account_fullname'  => Api\Translation::convert($allVals['cn'][0],'utf-8'),
						);
						//if (isset($totalcount)) ++$totalcount;
					}
				}
			}
			// sort the array
			$this->_callback_sort = strtoupper($param['sort']);
			$this->_callback_order = empty($param['order']) ? array('account_lid') : explode(',',$param['order']);
			foreach($this->_callback_order as &$col)
			{
				if (substr($col, 0, 8) !== 'account_') $col = 'account_'.$col;
			}
			$sortedAccounts = $accounts;
			uasort($sortedAccounts,array($this,'_sort_callback'));
			$this->total = isset($totalcount) ? $totalcount : count($accounts);

			// if totalcount is set, $sortedAccounts is NOT the full set, but already a limited set!
			if (!isset($totalcount))
			{
				$account_search[$unl_serial]['data'] = $sortedAccounts;
				$account_search[$unl_serial]['total'] = $this->total;
			}
		}
		// return only the wanted accounts
		reset($sortedAccounts);
		if(is_numeric($start) && is_numeric($offset))
		{
			$account_search[$serial]['total'] = $this->total;
			return $account_search[$serial]['data'] = isset($totalcount) || !isset($start) ? $sortedAccounts : array_slice($sortedAccounts, $start, $offset, true);
		}
		return $sortedAccounts;
	}

	/**
	 * DESC or ASC
	 *
	 * @var string
	 */
	private $_callback_sort = 'ASC';
	/**
	 * column_names to sort by
	 *
	 * @var array
	 */
	private $_callback_order = array('account_lid');

	/**
	 * Sort callback for uasort
	 *
	 * @param array $a
	 * @param array $b
	 * @return int
	 */
	function _sort_callback($a,$b)
	{
		foreach($this->_callback_order as $col )
		{
			if($this->_callback_sort != 'DESC')
			{
				$cmp = strcasecmp( $a[$col], $b[$col] );
			}
			else
			{
				$cmp = strcasecmp( $b[$col], $a[$col] );
			}
			if ( $cmp != 0 )
			{
				return $cmp;
			}
		}
		return 0;
	}

	/**
	 * Creates a timestamp from the date returned by the ldap server
	 *
	 * @internal
	 * @param string $date YYYYmmddHHiiss
	 * @return int
	 */
	protected static function accounts_ldap2ts($date)
	{
		if (!empty($date))
		{
			return gmmktime(substr($date,8,2),substr($date,10,2),substr($date,12,2),
				substr($date,4,2),substr($date,6,2),substr($date,0,4));
		}
		return NULL;
	}

	/**
	 * convert an alphanumeric account-value (account_lid, account_email) to the account_id
	 *
	 * Please note:
	 * - if a group and an user have the same account_lid the group will be returned (LDAP only)
	 * - if multiple user have the same email address, the returned user is undefined
	 *
	 * @param string $_name value to convert
	 * @param string $which ='account_lid' type of $name: account_lid (default), account_email, person_id, account_fullname
	 * @param string $account_type u = user, g = group, default null = try both
	 * @return int|false numeric account_id or false on error ($name not found)
	 */
	function name2id($_name,$which='account_lid',$account_type=null)
	{
		$name = Api\Ldap::quote(Api\Translation::convert($_name,Api\Translation::charset(),'utf-8'));

		if (in_array($which, array('account_lid','account_email')) && $account_type !== 'u') // groups only support account_(lid|email)
		{
			$attr = $which == 'account_lid' ? 'cn' : static::MAIL_ATTR;

			if (($sri = ldap_search($this->ds, $this->group_context, '(&('.$attr.'=' . $name . ")(objectclass=posixgroup)$this->group_filter)", array('gidNumber'))) &&
				($allValues = ldap_get_entries($this->ds, $sri)) &&
				!empty($allValues[0]['gidnumber'][0]))
			{
				return -$allValues[0]['gidnumber'][0];
			}
		}
		$to_ldap = array(
			'account_lid'   => 'uid',
			'account_email' => static::MAIL_ATTR,
			'account_fullname' => 'cn',
		);
		if (!isset($to_ldap[$which]) || $account_type === 'g')
		{
		    return False;
		}

		if (($sri = ldap_search($this->ds, $this->user_context, '(&('.$to_ldap[$which].'=' . $name . ')(objectclass=posixaccount))', array('uidNumber'))) &&
			($allValues = ldap_get_entries($this->ds, $sri)) &&
			!empty($allValues[0]['uidnumber'][0]))
		{
			return (int)$allValues[0]['uidnumber'][0];
		}
		return False;
	}

	/**
	 * Convert an numeric account_id to any other value of that account (account_lid, account_email, ...)
	 *
	 * Uses the read method to fetch all data.
	 *
	 * @param int $account_id numerica account_id
	 * @param string $which ='account_lid' type to convert to: account_lid (default), account_email, ...
	 * @return string|false converted value or false on error ($account_id not found)
	 */
	function id2name($account_id,$which='account_lid')
	{
		return $this->frontend->id2name($account_id,$which);
	}

	/**
	 * Update the last login timestamps and the IP
	 *
	 * @param int $_account_id
	 * @param string $ip
	 * @return int lastlogin time
	 */
	function update_lastlogin($_account_id, $ip)
	{
		unset($_account_id, $ip);
		return false;	// not longer supported
	}

	/**
	 * Query memberships of a given account
	 *
	 * @param int $account_id
	 * @return array|boolean array with account_id => account_lid pairs or false if account not found
	 */
	function memberships($account_id)
	{
		if (!(int) $account_id || !($account_lid = $this->id2name($account_id))) return false;

		$sri = ldap_search($this->ds,$this->group_context,'(&(objectClass=posixGroup)(memberuid='.Api\Ldap::quote($account_lid).'))',array('cn','gidnumber'));
		$memberships = array();
		foreach((array)ldap_get_entries($this->ds, $sri) as $key => $data)
		{
			if ($key === 'count') continue;

			$memberships[(string) -$data['gidnumber'][0]] = $data['cn'][0];
		}
		return $memberships;
	}

	/**
	 * Query the members of a group
	 *
	 * @param int $_gid
	 * @return array|boolean array with uidnumber => uid pairs,
	 *	false if $_gid is not numeric and can't be resolved to a numeric gid
	 */
	function members($_gid)
	{
		if (!is_numeric($_gid))
		{
			// try to recover
			$_gid = $this->name2id($_gid,'account_lid','g');
			if (!is_numeric($_gid)) return false;
		}

		$gid = abs($_gid);	// our gid is negative!

		$sri = ldap_search($this->ds,$this->group_context,"(&(objectClass=posixGroup)(gidnumber=$gid))",array('memberuid'));
		$group = ldap_get_entries($this->ds, $sri);

		$members = array();
		if (isset($group[0]['memberuid']))
		{
			unset($group[0]['memberuid']['count']);

			foreach($group[0]['memberuid'] as $lid)
			{
				if (($id = $this->name2id($lid, 'account_lid')))	// also return groups!
				{
					$members[$id] = $lid;
				}
			}
		}
		return $members;
	}

	/**
	 * Sets the memberships of the given account
	 *
	 * @param array $groups array with gidnumbers
	 * @param int $account_id uidnumber
	 */
	function set_memberships($groups,$account_id)
	{
		// remove not longer existing memberships
		if (($old_memberships = $this->memberships($account_id)))
		{
			$old_memberships = array_keys($old_memberships);
			foreach(array_diff($old_memberships,$groups) as $gid)
			{
				if (($members = $this->members($gid)))
				{
					unset($members[$account_id]);
					$this->set_members($members,$gid);
				}
			}
		}
		// adding new memberships
		foreach($old_memberships ? array_diff($groups,$old_memberships) : $groups as $gid)
		{
			if (!($members = $this->members($gid))) $members = array();
			$members[$account_id] = $this->id2name($account_id);
			$this->set_members($members,$gid);
		}
	}

	/**
	 * Set the members of a group
	 *
	 * @param array $members array with uidnumber or uid's
	 * @param int $gid gidnumber of group to set
	 * @param array $objectclass =null should we set the member and uniqueMember attributes (groupOf(Unique)Names|univentionGroup) (default detect it)
	 * @param string $use_cn =null if set $cn is used instead $gid and the attributes are returned, not written to ldap
	 * @return boolean/array false on failure, array or true otherwise
	 */
	function set_members($members, $gid, array $objectclass=null, $use_cn=null)
	{
		if (!($cn = $use_cn) && !($cn = $this->id2name($gid))) return false;

		// do that group is a groupOf(Unique)Names or univentionGroup?
		if (!isset($objectclass))
		{
			$objectclass = $this->id2name($gid, 'objectclass');
			// if we can't find objectclass, we might ge in the middle of a migration
			if (!isset($objectclass))
			{
				Api\Accounts::cache_invalidate($gid);
				if (!($objectclass = $this->id2name($gid, 'objectclass')))
				{
					// group does not yet exist --> return false
					return false;
				}
			}
		}

		$to_write = array('memberuid' => array());
		foreach((array)$members as $member)
		{
			if (!$member) continue;

			$member_dn = $this->id2name($member, 'account_dn');
			if (is_numeric($member)) $member = $this->id2name($member);

			// only add a member, if we have the necessary info / he already exists in migration
			if ($member && ($member_dn || !array_intersect(array('groupofnames','groupofuniquenames','univentiongroup'), $objectclass)))
			{
				$to_write['memberuid'][] = $member;
				if (in_array('groupofnames', $objectclass))
				{
					$to_write['member'][] = $member_dn;
				}
				if (array_intersect(array('groupofuniquenames','univentiongroup'), $objectclass))
				{
					$to_write['uniquemember'][] = $member_dn;
				}
			}
		}
		// hack as groupOfNames requires the member attribute
		if (in_array('groupofnames', $objectclass) && !$to_write['member'])
		{
			$to_write['member'][] = 'uid=dummy'.','.$this->user_context;
		}
		if (array_intersect(array('groupofuniquenames','univentiongroup'), $objectclass) && !$to_write['uniquemember'])
		{
			$to_write['uniquemember'][] = 'uid=dummy'.','.$this->user_context;
		}
		if ($use_cn) return $to_write;

		// set the member email addresses as forwards
		if ($this->id2name($gid,'account_email') &&	($objectclass = $this->id2name($gid,'mailAllowed')))
		{
			$forward = $this->group_mail_classes[$objectclass];
			if (is_array($forward)) list($forward,$extra_attr) = $forward;
			if ($extra_attr && ($uid = $this->id2name($gid))) $to_write[$extra_attr] = $uid;

			if ($forward)
			{
				$to_write[$forward] = array();
				foreach($members as $member)
				{
					if (($email = $this->id2name($member,'account_email')))	$to_write[$forward][] = $email;
				}
			}
		}
		if (!ldap_modify($this->ds,'cn='.Api\Ldap::quote($cn).','.$this->group_context,$to_write))
		{
			error_log(__METHOD__."(members=".array2string($members).", gid=$gid, objectclass=".array2string($objectclass).", use_cn=$use_cn) !ldap_modify(,'cn=$cn,$this->group_context', ".array2string($to_write).") --> ldap_error()=".ldap_error($this->ds));
			return false;
		}
		return true;
	}

	/**
	 * Using the common functions next_id and last_id, find the next available account_id
	 *
	 * @internal
	 * @param string $account_type ='u' (optional, default to 'u')
	 * @return int|boolean integer account_id (negative for groups) or false if none is free anymore
	 */
	protected function _get_nextid($account_type='u')
	{
		$min = $this->frontend->config['account_min_id'] ? $this->frontend->config['account_min_id'] : 0;
		$max = $this->frontend->config['account_max_id'] ? $this->frontend->config['account_max_id'] : 0;

		// prefer ids above 1000 (below reserved for system users under AD or Linux),
		// if that's possible within what is configured, or nothing is configured
		if ($min < 1000 && (!$max || $max > 1000)) $min = 1000;

		if ($account_type == 'g')
		{
			$type = 'groups';
			$sign = -1;
		}
		else
		{
			$type = 'accounts';
			$sign = 1;
		}
		/* Loop until we find a free id */
		do
		{
			$account_id = (int) self::next_id($type,$min,$max);
		}
		while ($account_id && ($this->frontend->exists($sign * $account_id) ||	// check need to include the sign!
			$this->frontend->exists(-1 * $sign * $account_id) ||
			// if sambaadmin is installed, call it to check there's not yet a relative id (last part of SID) with that number
			// to ease migration to AD or Samba4
			file_exists(EGW_SERVER_ROOT.'/sambaadmin') && $GLOBALS['egw_info']['apps']['sambaadmin'] &&
				ExecMethod2('sambaadmin.sosambaadmin.sidExists', $account_id)));

		if	(!$account_id || $max && $account_id > $max)
		{
			return False;
		}
		return $sign * $account_id;
	}

	/**
	 * Return a value for the next id an app/class may need to insert values into LDAP
	 *
	 * @param string $location name for id eg. "groups" or "accounts"
	 * @param int $min =0 if != 0 minimum id
	 * @param int $max =0 if != 0 maximum id allowed, if it would be exceeded we return false
	 * @return int|boolean the next id or false if $max given and exceeded
	 */
	static function next_id($location,$min=0,$max=0)
	{
		if (!$location)
		{
			return -1;
		}

		$id = (int)$GLOBALS['egw_info']['server'][$key='last_id_'.$location];

		if ($max && $id >= $max)
		{
			return False;
		}
		++$id;

		if($id < $min) $id = $min;

		Api\Config::save_value($key, $id, 'phpgwapi', true);
		$GLOBALS['egw_info']['server'][$key='last_id_'.$location] = $id;

		return (int)$id;
	}

	/**
	 * Return a value for the last id entered, which an app may need to check values for LDAP
	 *
	 * @param string $location name for id eg. "groups" or "accounts"
	 * @param int $min =0 if != 0 minimum id
	 * @param int $max =0 if != 0 maximum id allowed, if it would be exceeded we return false
	 * @return int|boolean current id in the next_id table for a particular app/class or -1 for no app and false if $max is exceeded.
	 */
	static function last_id($location,$min=0,$max=0)
	{
		if (!$location)
		{
			return -1;
		}

		$id = (int)$GLOBALS['egw_info']['server']['last_id_'.$location];

		if (!$id || $id < $min)
		{
			return self::next_id($location,$min,$max);
		}
		if ($max && $id > $max)
		{
			return False;
		}
		return $id;
	}

	/**
	 * Magic method called when object gets serialized
	 *
	 * We do NOT store ldapConnection, as we need to reconnect anyway.
	 * PHP 8.1 gives an error when trying to serialize LDAP\Connection object!
	 *
	 * @return array
	 */
	function __sleep()
	{
		$vars = get_object_vars($this);
		unset($vars['ds']);
		return array_keys($vars);
	}

	/**
	 * __wakeup function gets called by php while unserializing the object to reconnect with the ldap server
	 */
	function __wakeup()
	{
		$this->ds = $this->ldap_connection();
	}
}