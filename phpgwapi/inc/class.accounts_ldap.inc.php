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
 * @version $Id$
 */

/**
 * LDAP Backend for accounts
 *
 * The LDAP backend of the accounts class now stores accounts, groups and the memberships completly in LDAP.
 * It does NO longer use the ACL class/table for group membership information.
 * Nor does it use the phpgwAcounts schema (part of that information is stored via shadowAccount now).
 *
 * A user is recogniced by eGW, if he's in the user_context tree AND has the posixAccount object class AND
 * matches the LDAP search filter specified in setup >> configuration.
 * A group is recogniced by eGW, if it's in the group_context tree AND has the posixGroup object class.
 * The group members are stored as memberuid's.
 *
 * The (positive) group-id's (gidnumber) of LDAP groups are mapped in this class to negative numeric
 * account_id's to not conflict with the user-id's, as both share in eGW internaly the same numberspace!
 *
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage accounts
 * @access internal only use the interface provided by the accounts class
 */
class accounts_ldap
{
	/**
	 * resource with connection to the ldap server
	 *
	 * @var resource
	 */
	var $ds;
	/**
	 * LDAP context for users, eg. ou=account,dc=domain,dc=com
	 *
	 * @var string
	 */
	var $user_context;
	/**
	 * LDAP search filter for user accounts, eg. (uid=%name)
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
	 * total number of found entries from get_list method
	 *
	 * @var int
	 */
	var $total;

	var $ldapServerInfo;

	/**
	 * required classe for user and groups
	 *
	 * @var array
	 */
	var $requiredObjectClasses = array(
		'user' => array(
			'top','person','organizationalperson','inetorgperson','posixaccount','shadowaccount'
		),
		'user-if-supported' => array(	// these classes get added, only if the server supports them
			'mozillaabpersonalpha','mozillaorgperson','evolutionperson'
		),
		'group' => array(
			'top','posixgroup','groupofnames'
		)
	);
	/**
	 * Classes allowing to set a mail-address for a group and specify the memberaddresses as forwarding addresses
	 *
	 * @var array
	 */
	var $group_mail_classes = array(
		'dbmailforwardingaddress' => 'mailforwardingaddress',
		'dbmailuser' => array('mailforwardingaddress','uid'),
		'qmailuser' => array('mailforwardingaddress','uid'),
		'mailaccount' => 'mailalias',
	);

	/**
	 * Reference to our frontend
	 *
	 * @var accounts
	 */
	private $frontend;

	/**
	 * Instance of the ldap class
	 *
	 * @var ldap
	 */
	private $ldap;

	/**
	 * Constructor
	 *
	 * @param accounts $frontend reference to the frontend class, to be able to call it's methods if needed
	 * @return accounts_ldap
	 */
	function __construct(accounts $frontend)
	{
		$this->frontend = $frontend;

		// enable the caching in the session, done by the accounts class extending this class.
		$this->use_session_cache = true;

		$this->ldap = new ldap();
		$this->ds = $this->ldap->ldapConnect($this->frontend->config['ldap_host'],
			$this->frontend->config['ldap_root_dn'],$this->frontend->config['ldap_root_pw']);

		$this->user_context  = $this->frontend->config['ldap_context'];
		$this->account_filter = $this->frontend->config['ldap_search_filter'];
		$this->group_context = $this->frontend->config['ldap_group_context'] ?
			$this->frontend->config['ldap_group_context'] : $this->frontend->config['ldap_context'];
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

		$data_utf8 = translation::convert($data,translation::charset(),'utf-8');
		$members = $data['account_members'];

		if (!is_object($this->ldapServerInfo))
		{
			$this->ldapServerInfo = $this->ldap->getLDAPServerInfo($this->frontend->config['ldap_host']);
		}
		// common code for users and groups
		// checks if accout_lid (dn) has been changed or required objectclass'es are missing
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
				$old = $this->_ldap2array($old[0]);
				foreach($old['objectclass'] as $n => $class)
				{
					$old['objectclass'][$n] = strtolower($class);
				}
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
			if (!$old && !$is_group)	// for new accounts add additional addressbook object classes, if supported by server
			{			// as setting them later might loose eg. password, if we are not allowed to read them
				foreach($this->requiredObjectClasses['user-if-supported'] as $additional)
				{
					if ($this->ldapServerInfo->supportsObjectClass($additional))
					{
						$to_write['objectclass'][] = $additional;
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
			$to_write = $this->_merge_group($to_write,$data_utf8);
			$data['account_type'] = 'g';

			$groupOfNames = in_array('groupofnames',$old ? $old['objectclass'] : $to_write['objectclass']);
			if (!$old && $groupOfNames || $members)
			{
				$to_write = array_merge($to_write,$this->set_members($members,
					$data['account_id'],$groupOfNames,$dn));
			}
			// check if we should set a mail address and forwards for each member
			foreach($this->group_mail_classes as $objectclass => $forward)
			{
				if ($this->ldapServerInfo->supportsObjectClass($objectclass) &&
					($old && in_array($objectclass,$old['objectclass']) || $data_utf8['account_email'] || $old['mail']))
				{
					$extra_attr = false;
					if (is_array($forward)) list($forward,$extra_attr) = $forward;
					if ($data_utf8['account_email'])	// setting an email
					{
						if (!in_array($objectclass,$old ? $old['objectclass'] : $to_write['objectclass']))
						{
							if ($old) $to_write['objectclass'] = $old['objectclass'];
							$to_write['objectclass'][] = $objectclass;
						}
						if ($extra_attr) $to_write[$extra_attr] = $data_utf8['account_lid'];
						$to_write['mail'] = $data_utf8['account_email'];

						if (!$members) $members = $this->members($data['account_id']);
						$to_write[$forward] = array();
						foreach ($members as $member)
						{
							if (($email = $this->id2name($member,'account_email')))
							{
								$to_write[$forward][] = $email;
							}
						}
					}
					elseif($old)	// remove the mail and forwards only for existing entries
					{
						$to_write['mail'] = $to_write[$forward] = array();
						if ($extra_attr) $to_write[$extra_attr] = array();
						if (($key = array_search($objectclass,$old['objectclass'])))
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
			if (isset($to_write['mail']) && count($old['mail']) > 1)
			{
				$mail = $old['mail'];
				$mail[0] = $to_write['mail'];
				$to_write['mail'] = array_values(array_unique($mail));
			}
			$data['account_type'] = 'u';

			// Check if an account already exists as system user, and if it does deny creation
			if (!$GLOBALS['egw_info']['server']['ldap_allow_systemusernames'] &&
				function_exists('posix_getpwnam') && posix_getpwnam($data['account_lid']))
			{
				throw new egw_exception_wrong_userinput(lang('There already is a system-user with this name. User\'s should not have the same name as a systemuser'));
			}
		}

		// remove memberuid when adding a group
		if(!$old && is_array($to_write['memberuid']) && empty($to_write['memberuid'])) {
			unset($to_write['memberuid']);
		}
		//echo "<p>ldap_".($old ? 'modify' : 'add')."(,$dn,".print_r($to_write,true).")</p>\n";
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
				echo "ldap_".($old ? 'modify' : 'add')."(,$dn,".print_r($to_write,true).")\n";
				echo ldap_error($this->ds);
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
	 * Convert a single ldap value into a associative array
	 *
	 * @param array $ldap array with numerical and associative indexes and count's
	 * @return array with only associative index and no count's
	 */
	function _ldap2array($ldap)
	{
		if (!is_array($ldap)) return false;

		$arr = array();
		foreach($ldap as $var => $val)
		{
			if (is_int($var) || $var == 'count') continue;

			if (is_array($val) && $val['count'] == 1)
			{
				$arr[$var] = $val[0];
			}
			else
			{
				if (is_array($val)) unset($val['count']);

				$arr[$var] = $val;
			}
		}
		return $arr;
	}


	/**
	 * Delete one account, deletes also all acl-entries for that account
	 *
	 * @param int $id numeric account_id
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
		$sri = ldap_search($this->ds, $this->group_context,'(&(objectClass=posixGroup)(gidnumber=' . abs($account_id).'))',
			array('dn','gidnumber','cn','objectclass','mail'));

		$data = ldap_get_entries($this->ds, $sri);
		if (!$data['count'])
		{
			return false;	// group not found
		}
		$data = translation::convert($data[0],'utf-8');

		$group = array(
			'account_dn'        => $data['dn'],
			'account_id'        => -$data['gidnumber'][0],
			'account_lid'       => $data['cn'][0],
			'account_type'      => 'g',
			'account_firstname' => $data['cn'][0],
			'account_lastname'  => lang('Group'),
			'account_fullname'  => lang('Group').' '.$data['cn'][0],
			'groupOfNames'      => in_array('groupOfNames',$data['objectclass']),
			'account_email'     => $data['mail'][0],
		);
		if (!is_object($this->ldapServerInfo))
		{
			$this->ldapServerInfo = $this->ldap->getLDAPServerInfo($this->frontend->config['ldap_host']);
		}
		foreach($this->group_mail_classes as $objectclass => $forward)
		{
			if ($this->ldapServerInfo->supportsObjectClass($objectclass))
			{
				$group['mailAllowed'] = $objectclass;
				break;
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
		$sri = ldap_search($this->ds, $this->user_context, '(&(objectclass=posixAccount)(uidnumber=' . (int)$account_id.'))',
			array('dn','uidnumber','uid','gidnumber','givenname','sn','cn','mail','userpassword','telephonenumber',
				'shadowexpire','shadowlastchange','homedirectory','loginshell','createtimestamp','modifytimestamp'));

		$data = ldap_get_entries($this->ds, $sri);
		if (!$data['count'])
		{
			return false;	// user not found
		}
		$data = translation::convert($data[0],'utf-8');

		$utc_diff = date('Z');
		$user = array(
			'account_dn'        => $data['dn'],
			'account_id'        => (int)$data['uidnumber'][0],
			'account_lid'       => $data['uid'][0],
			'account_type'      => 'u',
			'account_primary_group' => -$data['gidnumber'][0],
			'account_firstname' => $data['givenname'][0],
			'account_lastname'  => $data['sn'][0],
			'account_email'     => $data['mail'][0],
			'account_fullname'  => $data['cn'][0],
			'account_pwd'       => $data['userpassword'][0],
			'account_phone'     => $data['telephonenumber'][0],
			// both status and expires are encoded in the single shadowexpire value in LDAP
			// - if it's unset an account is enabled AND does never expire
			// - if it's set to 0, the account is disabled
			// - if it's set to > 0, it will or already has expired --> acount is active if it not yet expired
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
		//echo "<p align=right>accounts_ldap::_read_user($account_id): shadowexpire={$data['shadowexpire'][0]} --> account_expires=$user[account_expires]=".date('Y-m-d H:i',$user['account_expires'])."</p>\n";
		if ($this->frontend->config['ldap_extra_attributes'])
		{
			$user['homedirectory']  = $data['homedirectory'][0];
			$user['loginshell']     = $data['loginshell'][0];
		}
		return $user;
	}

	/**
	 * Merges the group releavant account data from $data into $to_write
	 *
	 * @internal
	 * @param array $to_write data to write to ldap incl. objectclass ($data is NOT yet merged)
	 * @param array $data array with account-data in utf-8
	 * @return array merged data
	 */
	protected function _merge_group($to_write,$data)
	{
		$to_write['gidnumber'] = abs($data['account_id']);
		$to_write['cn'] = $data['account_lid'];

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
		//echo "<p>accounts_ldap::_merge_user(".print_r($to_write,true).','.print_r($data,true).",$new_entry)</p>\n";

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
			$to_write['mail']  = $data['account_email'] ? $data['account_email'] : array();
		}
		$to_write['cn']        = $data['account_fullname'] ? $data['account_fullname'] : $data['account_firstname'].' '.$data['account_lastname'];

		$utc_diff = date('Z');
		if (isset($data['account_passwd']) && $data['account_passwd'])
		{
			if (!preg_match('/^\\{[a-z5]{3,5}\\}.+/i',$data['account_passwd']))	// if it's not already entcrypted, do so now
			{
				$data['account_passwd'] = auth::encrypt_ldap($data['account_passwd']);
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
		$account_expire = $shadowexpire*3600*24+$utc_diff;
		//echo "<p align=right>account_expires=".date('Y-m-d H:i',$data['account_expires'])." --> $shadowexpire --> ".date('Y-m-d H:i',$account_expire)."</p>\n";
		$to_write['shadowexpire'] = !$data['account_status'] ?
			($data['account_expires'] != -1 && $data['account_expires'] < time() ? round($shadowexpire) : 0) :
			($data['account_expires'] != -1 ? round($shadowexpire) : array());	// array() = unset value

		if ($new_entry && is_array($to_write['shadowexpire']) && !count($to_write['shadowexpire']))
		{
			unset($to_write['shadowexpire']);	// gives protocoll error otherwise
		}
		//error_log(__METHOD__.__LINE__.$data['account_lid'].'#'.$data['account_lastpwd_change'].'#');
		if ($data['account_lastpwd_change']) $to_write['shadowlastchange'] = round(($data['account_lastpwd_change']-$utc_diff)/(24*3600));
		if (isset($data['account_lastpwd_change']) && $data['account_lastpwd_change']==0) $to_write['shadowlastchange'] = 0;
		// lastlogin and lastlogin from are not availible via the shadowAccount object class
		// $to_write['phpgwaccountlastlogin'] = $data['lastlogin'];
		// $to_write['phpgwaccountlastloginfrom'] = $data['lastloginfrom'];

		if ($this->frontend->config['ldap_extra_attributes'])
		{
			if (isset($data['homedirectory'])) $to_write['homedirectory']  = $data['homedirectory'];
			if (isset($data['loginshell'])) $to_write['loginshell'] = $data['loginshell'] ? $data['loginshell'] : array();
		}
		if ($new_entry && !isset($to_write['homedirectory']))
		{
			$to_write['homedirectory']  = '/dev/null';	// is a required attribute of posixAccount
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
	 * @param $param['sort'] string 'ASC' or 'DESC', default 'DESC' if not set
	 * @param $param['query'] string to search for, no search if unset or empty
	 * @param $param['query_type'] string:
	 *	'all'   - query all fields for containing $param[query]
	 *	'start' - query all fields starting with $param[query]
	 *	'exact' - query all fields for exact $param[query]
	 *	'lid','firstname','lastname','email' - query only the given field for containing $param[query]
	 * @param $param['offset'] int - number of matches to return if start given, default use the value in the prefs
	 * @return array with account_id => data pairs, data is an array with account_id, account_lid, account_firstname,
	 *	account_lastname, person_id (id of the linked addressbook entry), account_status, account_expires, account_primary_group
	 */
	function search($param)
	{
		//echo "<p>accounts_ldap::search(".print_r($param,true)."): ".microtime()."</p>\n";
		$account_search = &$this->cache['account_search'];

		// check if the query is cached
		$serial = serialize($param);
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
		$unl_serial = serialize($param);
		if (isset($account_search[$unl_serial]))
		{
			$this->total = $account_search[$unl_serial]['total'];
			$sortedAccounts = $account_search[$unl_serial]['data'];
		}
		else	// we need to run the unlimited query
		{
			$query = ldap::quote(strtolower($param['query']));

			$accounts = array();
			if($param['type'] != 'groups')
			{
				$filter = "(&(objectclass=posixaccount)";
				if (!empty($query) && $query != '*')
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
							$filter .= "(|(uid=$query)(sn=$query)(cn=$query)(givenname=$query)(mail=$query))";
							break;
						case 'firstname':
						case 'lastname':
						case 'lid':
						case 'email':
							$to_ldap = array(
								'firstname' => 'givenname',
								'lastname'  => 'sn',
								'lid'       => 'uid',
								'email'     => 'mail',
							);
							$filter .= '('.$to_ldap[$param['query_type']].'=*'.$query.'*)';
							break;
					}
				}
				// add account_filter to filter (user has to be '*', as we otherwise only search uid's)
				$filter .= $this->account_filter;
				$filter = str_replace(array('%user','%domain'),array('*',$GLOBALS['egw_info']['user']['domain']),$filter);
				$filter .= ')';

				if ($param['type'] != 'both')
				{
					// folw:
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
					);
					$orders = explode(',',$param['order']);
					$order = isset($propertyMap[$orders[0]]) ? $propertyMap[$orders[0]] : 'uid';
					$sri = ldap_search($this->ds, $this->user_context, $filter,array('uid', $order));
					$fullSet = array();
					foreach ((array)ldap_get_entries($this->ds, $sri) as $key => $entry)
					{
						if ($key !== 'count') $fullSet[$entry['uid'][0]] = $entry[$order][0];
					}

					if (is_numeric($param['type'])) // return only group-members
					{
						$relevantAccounts = array();
						$sri = ldap_search($this->ds,$this->group_context,"(&(objectClass=posixGroup)(gidnumber=" . abs($param['type']) . "))",array('memberuid'));
						$group = ldap_get_entries($this->ds, $sri);

						if (isset($group[0]['memberuid']))
						{
							$fullSet = array_intersect_key($fullSet, array_flip($group[0]['memberuid']));
						}
					}
					$totalcount = count($fullSet);

					$sortFn = $param['sort'] == 'DESC' ? 'arsort' : 'asort';
					$sortFn($fullSet);
					$relevantAccounts = is_numeric($start) ? array_slice(array_keys($fullSet), $start, $offset) : array_keys($fullSet);
					$filter = '(&(objectclass=posixaccount)(|(uid='.implode(')(uid=',$relevantAccounts).'))' . $this->account_filter.')';
					$filter = str_replace(array('%user','%domain'),array('*',$GLOBALS['egw_info']['user']['domain']),$filter);
				}
				$sri = ldap_search($this->ds, $this->user_context, $filter,array('uid','uidNumber','givenname','sn','mail','shadowExpire','createtimestamp','modifytimestamp'));
				//echo "<p>ldap_search(,$this->user_context,'$filter',) ".($sri ? '' : ldap_error($this->ds)).microtime()."</p>\n";

				$utc_diff = date('Z');
				foreach(ldap_get_entries($this->ds, $sri) as $allVals)
				{
					settype($allVals,'array');
					$test = @$allVals['uid'][0];
					if (!$this->frontend->config['global_denied_users'][$test] && $allVals['uid'][0])
					{
						$account = Array(
							'account_id'        => $allVals['uidnumber'][0],
							'account_lid'       => translation::convert($allVals['uid'][0],'utf-8'),
							'account_type'      => 'u',
							'account_firstname' => translation::convert($allVals['givenname'][0],'utf-8'),
							'account_lastname'  => translation::convert($allVals['sn'][0],'utf-8'),
							'account_status'    => isset($allVals['shadowexpire'][0]) && $allVals['shadowexpire'][0]*24*3600-$utc_diff < time() ? false : 'A',
							'account_email'     => $allVals['mail'][0],
							'account_created' => isset($allVals['createtimestamp'][0]) ? self::accounts_ldap2ts($allVals['createtimestamp'][0]) : null,
							'account_modified' => isset($allVals['modifytimestamp'][0]) ? self::accounts_ldap2ts($allVals['modifytimestamp'][0]) : null,
						);
						$account['account_fullname'] = common::display_fullname($account['account_lid'],$account['account_firstname'],$account['account_lastname']);
						$accounts[$account['account_id']] = $account;
					}
				}
			}
			if ($param['type'] == 'groups' || $param['type'] == 'both')
			{
				if(empty($query) || $query == '*')
				{
					$filter = '(objectclass=posixgroup)';
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
					$filter = "(&(objectclass=posixgroup)(cn=$query))";
				}
				$sri = ldap_search($this->ds, $this->group_context, $filter,array('cn','gidNumber'));
				foreach((array)ldap_get_entries($this->ds, $sri) as $allVals)
				{
					settype($allVals,'array');
					$test = $allVals['cn'][0];
					if (!$this->frontend->config['global_denied_groups'][$test] && $allVals['cn'][0])
					{
						$accounts[(string)-$allVals['gidnumber'][0]] = Array(
							'account_id'        => -$allVals['gidnumber'][0],
							'account_lid'       => translation::convert($allVals['cn'][0],'utf-8'),
							'account_type'      => 'g',
							'account_firstname' => translation::convert($allVals['cn'][0],'utf-8'),
							'account_lastname'  => lang('Group'),
							'account_status'    => 'A',
							'account_fullname'  => translation::convert($allVals['cn'][0],'utf-8'),
						);
						if (isset($totalcount)) ++$totalcount;
					}
				}
			}
			// sort the array
			$this->_callback_sort = strtoupper($param['sort']);
			$this->_callback_order = empty($param['order']) ? array('account_lid') : explode(',',$param['order']);
			$sortedAccounts = $accounts;
			uasort($sortedAccounts,array($this,'_sort_callback'));
			$account_search[$unl_serial]['data'] = $sortedAccounts;

			$account_search[$unl_serial]['total'] = $this->total = isset($totalcount) ? $totalcount : count($accounts);
		}
		//echo "<p>accounts_ldap::search() found $this->total: ".microtime()."</p>\n";
		// return only the wanted accounts
		reset($sortedAccounts);
		if(is_numeric($start) && is_numeric($offset))
		{
			$account_search[$serial]['total'] = $this->total;
			return $account_search[$serial]['data'] = isset($totalcount) ? $sortedAccounts : array_slice($sortedAccounts, $start, $offset);
		}
		return $sortedAccounts;
	}

	/**
	 * DESC or ASC
	 *
	 * @var string
	 */
	private $_callback_sort = 'DESC';
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
			if($this->_callback_sort == 'ASC')
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
	 * @param string $name value to convert
	 * @param string $which='account_lid' type of $name: account_lid (default), account_email, person_id, account_fullname
	 * @param string $account_type u = user, g = group, default null = try both
	 * @return int|false numeric account_id or false on error ($name not found)
	 */
	function name2id($name,$which='account_lid',$account_type=null)
	{
		$name = ldap::quote(translation::convert($name,translation::charset(),'utf-8'));

		if ($which == 'account_lid' && $account_type !== 'u') // groups only support account_lid
		{

			$sri = ldap_search($this->ds, $this->group_context, '(&(cn=' . $name . ')(objectclass=posixgroup))');
			$allValues = ldap_get_entries($this->ds, $sri);

			if (@$allValues[0]['gidnumber'][0])
			{
				return -$allValues[0]['gidnumber'][0];
			}
		}
		$to_ldap = array(
			'account_lid'   => 'uid',
			'account_email' => 'mail',
			'account_fullname' => 'cn',
		);
		if (!isset($to_ldap[$which]) || $account_type === 'g') {
		    return False;
		}

		$sri = ldap_search($this->ds, $this->user_context, '(&('.$to_ldap[$which].'=' . $name . ')(objectclass=posixaccount))');

		$allValues = ldap_get_entries($this->ds, $sri);

		if (@$allValues[0]['uidnumber'][0])
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
	 * @param string $which='account_lid' type to convert to: account_lid (default), account_email, ...
	 * @return string/false converted value or false on error ($account_id not found)
	 */
	function id2name($account_id,$which='account_lid')
	{
		return $this->frontend->id2name($account_id,$which);
	}

	/**
	 * Update the last login timestamps and the IP
	 *
	 * @param int $account_id
	 * @param string $ip
	 * @return int lastlogin time
	 */
	function update_lastlogin($_account_id, $ip)
	{
		return false;	// not longer supported

		$entry['phpgwaccountlastlogin']     = time();
		$entry['phpgwaccountlastloginfrom'] = $ip;

		$sri = ldap_search($this->ds, $this->frontend->config['ldap_context'], 'uidnumber=' . (int)$_account_id);
		$allValues = ldap_get_entries($this->ds, $sri);

		$dn = $allValues[0]['dn'];
		@ldap_modify($this->ds, $dn, $entry);

		return $allValues[0]['phpgwaccountlastlogin'][0];
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

		$sri = ldap_search($this->ds,$this->group_context,'(&(objectClass=posixGroup)(memberuid='.ldap::quote($account_lid).'))',array('cn','gidnumber'));
		$memberships = array();
		foreach((array)ldap_get_entries($this->ds, $sri) as $key => $data)
		{
			if ($key === 'count') continue;

			$memberships[(string) -$data['gidnumber'][0]] = $data['cn'][0];
		}
		//echo "accounts::memberships($account_id)"; _debug_array($memberships);
		return $memberships;
	}

	/**
	 * Query the members of a group
	 *
	 * @param int $gid
	 * @return array with uidnumber => uid pairs
	 */
	function members($gid)
	{
		if (!is_numeric($gid))
		{
			// try to recover
			$gid = $this->name2id($gid,'account_lid','g');
			if (!is_numeric($gid)) return false;
		}

		$gid = abs($gid);	// our gid is negative!

		$sri = ldap_search($this->ds,$this->group_context,"(&(objectClass=posixGroup)(gidnumber=$gid))",array('memberuid'));
		$group = ldap_get_entries($this->ds, $sri);

		$members = array();
		if (isset($group[0]['memberuid']))
		{
			foreach($group[0]['memberuid'] as $lid)
			{
				if (($id = $this->name2id($lid)))
				{
					$members[$id] = $lid;
				}
			}
		}
		//echo "accounts_ldap::members($gid)"; _debug_array($members);
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
		//echo "<p>accounts_ldap::set_memberships(".print_r($groups,true).",$account_id)</p>\n";

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
			$members = $this->members($gid);
			$members[$account_id] = $this->id2name($account_id);
			$this->set_members($members,$gid);
		}
	}

	/**
	 * Set the members of a group
	 *
	 * @param array $members array with uidnumber or uid's
	 * @param int $gid gidnumber of group to set
	 * @param boolean $groupOfNames=null should we set the member attribute of groupOfNames (default detect it)
	 * @param string $use_cn=null if set $cn is used instead $gid and the attributes are returned, not written to ldap
	 * @return boolean/array false on failure, array or true otherwise
	 */
	function set_members($members,$gid,$groupOfNames=null,$use_cn=null)
	{
		//echo "<p>accounts_ldap::set_members(".print_r($members,true).",$gid)</p>\n";
		if (!($cn = $use_cn) && !($cn = $this->id2name($gid))) return false;

		// do that group is a groupOfNames?
		if (is_null($groupOfNames)) $groupOfNames = $this->id2name($gid,'groupOfNames');

		$to_write = array('memberuid' => array());
		foreach((array)$members as $key => $member)
		{
			if (is_numeric($member)) $member = $this->id2name($member);

			if ($member)
			{
				$to_write['memberuid'][] = $member;
				if ($groupOfNames) $to_write['member'][] = 'uid='.$member.','.$this->user_context;
			}
		}
		if ($groupOfNames && !$to_write['member'])
		{
			// hack as groupOfNames requires the member attribute
			$to_write['member'][] = 'uid=dummy'.','.$this->user_context;
		}
		if ($use_cn) return $to_write;

		// set the member email addresses as forwards
		if ($this->id2name($gid,'account_email') &&	($objectclass = $this->id2name($gid,'mailAllowed')))
		{
			$forward = $this->group_mail_classes[$objectclass];
			if (is_array($forward)) list($forward,$extra_attr) = $forward;
			if ($extra_attr && ($uid = $this->id2name($gid))) $to_write[$extra_attr] = $uid;

			$to_write[$forward] = array();
			foreach($members as $key => $member)
			{
				if (($email = $this->id2name($member,'account_email')))	$to_write[$forward][] = $email;
			}
		}
		if (!ldap_modify($this->ds,'cn='.ldap::quote($cn).','.$this->group_context,$to_write))
		{
			echo "ldap_modify(,'cn=$cn,$this->group_context',".print_r($to_write,true)."))\n";
			return false;
		}
		return true;
	}

	/**
	 * Using the common functions next_id and last_id, find the next available account_id
	 *
	 * @internal
	 * @param $string $account_type='u' (optional, default to 'u')
	 * @return int|boolean integer account_id (negative for groups) or false if none is free anymore
	 */
	protected function _get_nextid($account_type='u')
	{
		$min = $this->frontend->config['account_min_id'] ? $this->frontend->config['account_min_id'] : 0;
		$max = $this->frontend->config['account_max_id'] ? $this->frontend->config['account_max_id'] : 0;

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
			$account_id = (int) $GLOBALS['egw']->common->next_id($type,$min,$max);
		}
		while ($account_id && ($this->frontend->exists($sign * $account_id) || $this->frontend->exists(-1 * $sign * $account_id)));	// check need to include the sign!

		if	(!$account_id || $this->frontend->config['account_max_id'] &&
			$account_id > $this->frontend->config['account_max_id'])
		{
			return False;
		}
		return $sign * $account_id;
	}

	/**
	 * __wakeup function gets called by php while unserializing the object to reconnect with the ldap server
	 */
	function __wakeup()
	{
		$this->ds = $this->ldap->ldapConnect($this->frontend->config['ldap_host'],
			$this->frontend->config['ldap_root_dn'],$this->frontend->config['ldap_root_pw']);
	}
}
