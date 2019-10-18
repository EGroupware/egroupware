<?php
/**
 * API - Accounts backend for Univention
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <rb@stylite.de>
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage accounts
 */

namespace EGroupware\Api\Accounts;

use EGroupware\Api;

/**
 * Accounts backend for Univention
 *
 * This backend is mostly identical to LDAP backend and need to be configured in the same way.
 *
 * Only difference is that some actions are currently done directly via Univention UDM webservice:
 * - create new users: to generate necesary Kerberos stuff and all password hashes
 * - password change: to generate als Samba hashes
 * - create groups with given gidNumber/sambaRID
 * - rename / -position users or groups, as this is a remove and re-create
 *   (removing and adding entry under new dn via LDAP fails: "Type or value exists")
 *
 * Once UDM webservice is out of beta, we could think about replacing LDAP accounts stuff completly.
 * Possible problems to look out for:
 * - search with sorting
 * - caching done on LDAP level
 * - mail account and addressbook is also affected
 */
class Univention extends Ldap
{
	/**
	 * Name of mail attribute
	 */
	const MAIL_ATTR = 'mailprimaryaddress';

	/**
	 * Constructor
	 *
	 * @param Api\Accounts $frontend reference to the frontend class, to be able to call it's methods if needed
	 */
	function __construct(Api\Accounts $frontend)
	{
		parent::__construct($frontend);

		// remove not supported groupOfNames to skip parent::save() first tries with it, before trying without
		if (($groupOfNameKey = array_search('groupofnames', $this->requiredObjectClasses['group'])))
		{
			unset($this->requiredObjectClasses['group'][$groupOfNameKey]);
		}
	}

	/**
	 * Saves / adds the data of one account
	 *
	 * If no account_id is set in data the account is added and the new id is set in $data.
	 *
	 * @param array $data array with account-data
	 * @return int|boolean the account_id or false on error
	 * @throws Univention\UdmException on error
	 */
	function save(&$data)
	{
		// UCS lowercases email when storing
		$data['account_email'] = strtolower($data['account_email']);

		$config = $this->frontend->config && $this->frontend->config['ldap_context'] ?
			$this->frontend->config : $GLOBALS['egw_info']['server'];

		$udm = new Univention\Udm($config);

		if ($data['account_type'] !== 'g' && (empty($data['account_id']) || !$this->id2name($data['account_id'])))
		{
			// empty names give an error: The property lastname is required is not valid
			if (empty($data['account_lastname'])) $data['account_lastname'] = 'n/a';

			// we can't create a new user without a password, setting a randowm one for now
			$matches = null;
			if (empty($data['account_passwd']) || preg_match('/^{([a-z0-9_]+)}/i', $data['account_passwd'], $matches))
			{
				if ($matches && strtolower($matches[1]) === 'plain')
				{
					$data['account_passwd'] = substr($data['account_passwd'], 7);
				}
				else
				{
					$data['account_passwd'] = Api\Auth::randomstring(12);
					//file_put_contents('/tmp/passwords', "$data[account_lid]\t$data[account_passwd]\n", FILE_APPEND);
				}
			}

			// if account_id is given and bigger then 1000, set it to facilitate migration
			if (empty($data['account_id']) || $data['account_id'] < Ads::MIN_ACCOUNT_ID)
			{
				unset($data['account_id']);
			}

			$data['account_dn'] = $udm->createUser($data);
			$data['account_id'] = $this->name2id($data['account_lid'], 'account_lid', 'u');
		}
		// create new groups incl. Samba objectclass and SID
		elseif($data['account_type'] === 'g' && (empty($data['account_id']) ||
			$data['account_id'] >= Ads::MIN_ACCOUNT_ID && !$this->id2name($data['account_id'])))
		{
			// UCS 4.4 Rest API gives an error creating group "Default" claiming a user or group with that name exists
			if ($data['account_lid'] === 'Default')
			{
				// we work around that by creating the group as DefaultX and then rename it to Default in ldap
				$data['account_lid'] = 'DefaultX';
				if (($data['account_dn'] = $udm->createGroup($data)) &&
					ldap_rename($this->ds, $data['account_dn'], 'cn=Default',
						substr($data['account_dn'], 12), true))	// strlen("cn=DefaultX,")==12
				{
					$data['account_dn'] = str_replace('=DefaultX,', '=Default,', $data['account_dn']);
					$data['account_lid'] = 'Default';
				}
				else
				{
					throw new Exception("Creating Group DefaultX and renaming to Default failed!");
				}
			}
			else
			{
				$data['account_dn'] = $udm->createGroup($data);
			}
			$data['account_id'] = $this->name2id($data['account_lid'], 'account_lid', 'g');
		}
		// account_lid and password changes need to go through UDM too
		elseif($data['account_id'] && ($data['old_loginid'] || ($data['old_loginid'] = $this->id2name($data['account_id']))) &&
			($data['account_lid'] != $data['old_loginid'] || !empty($data['account_passwd'])) &&
			($data['account_dn'] = $this->id2name($data['account_id'], 'account_dn')))
		{
			if ($data['account_type'] !== 'g')
			{
				$data['account_dn'] = $udm->updateUser($data['account_dn'], $data);
			}
			else
			{
				$data['account_dn'] = $udm->updateGroup($data['account_dn'], $data);
			}
		}
		return parent::save($data);
	}

	/**
	 * convert an alphanumeric account-value (account_lid, account_email) to the account_id
	 *
	 * Reimplement to check for users outside regular user-dn eg. functional users
	 *
	 * @param string $_name value to convert
	 * @param string $which ='account_lid' type of $name: account_lid (default), account_email, person_id, account_fullname
	 * @param string $account_type u = user, g = group, default null = try both
	 * @return int|false numeric account_id or false on error ($name not found)
	 */
	function name2id($_name,$which='account_lid',$account_type=null)
	{
		if ((!$id = parent::name2id($_name, $which, $account_type)) && $account_type !== 'g')
		{
			$user_dn = $this->user_context;
			$this->user_context = preg_replace('/(cn|uid)=([^,]+),/i', '', $this->user_context);

			$id = parent::name2id($_name, $which, $account_type);

			$this->user_context = $user_dn;
		}
		return $id;
	}

	/**
	 * Convert an numeric account_id to any other value of that account (account_lid, account_email, ...)
	 *
	 * Reimplement to check for users outside regular user-dn eg. functional users
	 *
	 * @param int $account_id numerica account_id
	 * @param string $which ='account_lid' type to convert to: account_lid (default), account_email, ...
	 * @return string|false converted value or false on error ($account_id not found)
	 */
	function id2name($account_id,$which='account_lid')
	{
		if (($name = parent::id2name($account_id, $which)) === false)
		{
			if (!is_numeric($account_id)) $account_id = $this->name2id($account_id);

			$user_dn = $this->user_context;
			$this->user_context = preg_replace('/(cn|uid)=([^,]+),/i', '', $this->user_context);

			if ($account_id && ($data = $this->read($account_id)))
			{
				$name = $data[$which];
			}
			$this->user_context = $user_dn;
		}
		return $name;
	}

	/**
	 * Change password via UDM to update all hashes supported by Univention
	 *
	 * @param string $old_passwd must be cleartext or empty to not to be checked
	 * @param string $new_passwd must be cleartext
	 * @param int $account_id account id of user whose passwd should be changed
	 * @param boolean $update_lastchange =true
	 * @return boolean true if password successful changed, false otherwise
	 * @throws Univention\UdmException on error
	 */
	function change_password($old_passwd, $new_passwd, $account_id=0, $update_lastchange=true)
	{
		$dn = $this->id2name($account_id ? $account_id : $GLOBALS['egw_info']['user']['account_id'], 'account_dn');
		if ($this->debug) error_log(__METHOD__."('$old_passwd','$new_passwd',$account_id, $update_lastchange) db='$dn'");

		if($old_passwd)	// if old password given (not called by admin) --> bind as that user to change the pw
		{
			try {
				Api\Ldap::factory(true, '', $dn, $old_passwd);
			}
			catch (Api\Exception\NoPermission $e) {
				unset($e);
				return false;	// wrong old user password
			}
		}

		$config = $this->frontend->config && $this->frontend->config['ldap_context'] ?
			$this->frontend->config : $GLOBALS['egw_info']['server'];

		$udm = new Univention\Udm($config);

		$data = [
			'account_passwd' => $new_passwd
		];

		$udm->updateUser($dn, $data);

		if($old_passwd)	// if old password given (not called by admin) update the password in the session
		{
			// using time() is sufficient to represent the current time, we do not need the timestamp written to the storage
			Api\Cache::setSession('phpgwapi','auth_alpwchange_val',time());
		}
		return $new_passwd;
	}
}
