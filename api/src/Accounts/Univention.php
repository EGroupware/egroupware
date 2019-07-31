<?php
/**
 * API - accounts Univention LDAP backend
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
 * Univention LDAP Backend for accounts
 *
 * This backend is mostly identical to LDAP backend and need to be configured in the same way.
 *
 * Only difference is that new users get created via univention-directory-manager CLI program,
 * to generate necesary Kerberos stuff.
 *
 * New groups are generated via same CLI, if we have an ID/RID to set.
 *
 * Existing users and groups need to be renamed via same CLI, as removing and
 * adding entry under new dn via LDAP fails (Type or value exists).
 */
class Univention extends Ldap
{
	/**
	 * Attribute with mail address
	 */
	const MAIL_ATTR = 'mailprimaryaddress';

	/**
	 * Name of binary to call
	 *
	 * It is a symlink to /usr/share/univention-directory-manager-tools/directory-manager-cli.
	 * Both directories must be included in open_basedir!
	 */
	const DIRECTORY_MANAGER_BIN = '/usr/sbin/univention-directory-manager';

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
		// UCS lowercases email when storing
		$data['account_email'] = strtolower($data['account_email']);

		if (self::available())
		{
			$ssh = null;//'/usr/bin/ssh -o "UserKnownHostsFile=/dev/null" -o "StrictHostKeyChecking=no" -i /var/lib/egroupware/id_rsa root@10.44.22.194';
			$config = $this->frontend->config && $this->frontend->config['ldap_context'] ?
				$this->frontend->config : $GLOBALS['egw_info']['server'];

			if ($data['account_type'] !== 'g' && (empty($data['account_id']) || !$this->id2name($data['account_id'])))
			{
				// empty names give an error: The property Last/First name is required is not valid
				if (empty($data['account_firstname'])) $data['account_firstname'] = 'n/a';
				if (empty($data['account_lastname'])) $data['account_lastname'] = 'n/a';

				$params = array(
					'users/user','create',
					'--binddn', $config['ldap_root_dn'],
					'--bindpwd', 5=>$config['ldap_root_pw'],
					'--position', $config['ldap_context'],
					'--set', 'username='.$data['account_lid'],
					'--set', 'firstname='.$data['account_firstname'],
					'--set', 'lastname='.$data['account_lastname'],
				);

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
				$params[] = '--set'; $params[] = 'password='.$data['account_passwd'];

				// if account_id is given and bigger then 1000, set it to facilitate migration
				if (!empty($data['account_id']) && $data['account_id'] >= Ads::MIN_ACCOUNT_ID)
				{
					$params[] = '--set'; $params[] = 'uidNumber='.(int)$data['account_id'];
					$params[] = '--set'; $params[] = 'sambaRID='.(int)$data['account_id'];
				}

				if (!empty($data['account_email']))
				{
					$params[] = '--set'; $params[] = 'mailPrimaryAddress='.$data['account_email'];

					// we need to set mailHomeServer, so mailbox gets created for Dovecot
					// get_default() does not work for Adminstrator, try acc_id=1 instead
					// if everything fails try hostname ...
					try {
						if (!($account = Api\Mail\Account::get_default(false, false, false)))
						{
							$account = Api\Mail\Account::read(1);
						}
						$hostname = $account->acc_imap_host;
					}
					catch(\Exception $e) {
						unset($e);
					}
					//$hostname='master.test-org.intranet';
					if (empty($hostname)) $hostname = trim(exec('hostname -f'));
					$params[] = '--set'; $params[] = 'mailHomeServer='.$hostname;
				}
				$cmd = self::DIRECTORY_MANAGER_BIN.' '.implode(' ', array_map('escapeshellarg', $params));
				if (isset($ssh)) $cmd = $ssh.' bash -c "\\"'.$cmd.'\\""';
				$output_arr = $ret = $matches = null;
				exec($cmd, $output_arr, $ret);
				$output = implode("\n", $output_arr);
				if ($ret || !preg_match('/^Object created: (uid=.*)$/mui', $output, $matches))
				{
					$params[5] = '********';	// mask out password!
					$cmd = self::DIRECTORY_MANAGER_BIN.' '.implode(' ', array_map('escapeshellarg', $params));
					throw new Api\Exception\WrongUserinput($cmd."\nreturned\n".$output);
				}
				$data['account_dn'] = $matches[1];
				$data['account_id'] = $this->name2id($data['account_lid'], 'account_lid', 'u');
			}
			// create new groups with given account_id via directory-manager too, to be able to set the RID
			elseif($data['account_type'] === 'g' && !empty($data['account_id']) &&
				$data['account_id'] >= Ads::MIN_ACCOUNT_ID && !$this->id2name($data['account_id']))
			{
				$params = array(
					'groups/group', 'create',
					'--binddn', $config['ldap_root_dn'],
					'--bindpwd', 5=>$config['ldap_root_pw'],
					'--position', empty($config['ldap_group_context']) ? $config['ldap_context'] : $config['ldap_group_context'],
					'--set', 'name='.$data['account_lid'],
					'--set', 'gidNumber='.(int)$data['account_id'],
					'--set', 'sambaRID='.(int)$data['account_id'],
				);

				$cmd = self::DIRECTORY_MANAGER_BIN.' '.implode(' ', array_map('escapeshellarg', $params));
				if (isset($ssh)) $cmd = $ssh.' bash -c "\\"'.$cmd.'\\""';
				$output_arr = $ret = $matches = null;
				exec($cmd, $output_arr, $ret);
				$output = implode("\n", $output_arr);
				if ($ret || !preg_match('/^Object created: (cn=.*)$/mui', $output, $matches))
				{
					$params[5] = '********';	// mask out password!
					$cmd = self::DIRECTORY_MANAGER_BIN.' '.implode(' ', array_map('escapeshellarg', $params));
					throw new Api\Exception\WrongUserinput($cmd."\nreturned\n".$output);
				}
				$data['account_dn'] = $matches[1];
				$data['account_id'] = $this->name2id($data['account_lid'], 'account_lid', 'g');
			}
			// account_lid and password changes need to go through UDM too
			elseif($data['account_id'] && ($data['old_loginid'] || ($data['old_loginid'] = $this->id2name($data['account_id']))) &&
				($data['account_lid'] != $data['old_loginid'] || !empty($data['account_passwd'])) &&
				($data['account_dn'] = $this->id2name($data['account_id'], 'account_dn')))
			{
				$params = array(
					$data['account_type'] !== 'g' ? 'users/user' : 'groups/group', 'modify',
					'--binddn', $config['ldap_root_dn'],
					'--bindpwd', 5=>$config['ldap_root_pw'],
					'--dn', $data['account_dn'],
					'--set', ($data['account_type'] !== 'g' ? 'username' : 'name').'='.$data['account_lid'],
				);
				$cmd = self::DIRECTORY_MANAGER_BIN.' '.implode(' ', array_map('escapeshellarg', $params));
				if (isset($ssh)) $cmd = $ssh.' bash -c "\\"'.$cmd.'\\""';
				$output_arr = $ret = $matches = null;
				exec($cmd, $output_arr, $ret);
				$output = implode("\n", $output_arr);
				if ($ret || !preg_match('/^Object modified: ((uid|cn)=.*)$/mui', $output, $matches))
				{
					$params[5] = '********';	// mask out password!
					$cmd = self::DIRECTORY_MANAGER_BIN.' '.implode(' ', array_map('escapeshellarg', $params));
					throw new Api\Exception\WrongUserinput($cmd."\nreturned\n".$output);
				}
				$data['account_dn'] = $data['account_type'] !== 'g' ? $matches[1] :
					// duno why but directory-manager returns old dn for groups ...
					preg_replace('/^cn=[^,]+,/', 'cn='.$data['account_lid'].',', $data['account_dn']);
			}
		}
		//else error_log(__METHOD__."() ".self::DIRECTORY_MANAGER_BIN." is NOT available!");
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
		if ((!$id = parent::name2id($_name, $which, $account_type)))
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
	 * Check if our function depending on an external binary is available
	 *
	 * @return boolean
	 */
	public static function available()
	{
		//return true;
		return file_exists(self::DIRECTORY_MANAGER_BIN) && is_executable(self::DIRECTORY_MANAGER_BIN);
	}

	/**
	 * changes password in LDAP
	 *
	 * If $old_passwd is given, the password change is done binded as user and NOT with the
	 * "root" dn given in the configurations.
	 *
	 * @param string $old_passwd must be cleartext or empty to not to be checked
	 * @param string $new_passwd must be cleartext
	 * @param int $account_id account id of user whose passwd should be changed
	 * @param boolean $update_lastchange =true
	 * @return boolean true if password successful changed, false otherwise
	 */
	function change_password($old_passwd, $new_passwd, $account_id=0, $update_lastchange=true)
	{
		if (!self::available())
		{
			return false;
		}
		if (!$account_id)
		{
			$username = $GLOBALS['egw_info']['user']['account_lid'];
		}
		else
		{
			$username = Api\Translation::convert($GLOBALS['egw']->accounts->id2name($account_id),
				Api\Translation::charset(),'utf-8');
		}
		if ($this->debug) error_log(__METHOD__."('$old_passwd','$new_passwd',$account_id, $update_lastchange) username='$username'");

		$filter = str_replace(array('%user','%domain'),array($username,$GLOBALS['egw_info']['user']['domain']),
			$GLOBALS['egw_info']['server']['ldap_search_filter'] ? $GLOBALS['egw_info']['server']['ldap_search_filter'] : '(uid=%user)');

		$ds = $ds_admin = Api\Ldap::factory();
		$sri = ldap_search($ds, $GLOBALS['egw_info']['server']['ldap_context'], $filter);
		$allValues = ldap_get_entries($ds, $sri);

		if ($update_lastchange)
		{
			// ToDo: $entry['shadowlastchange'] = round((time()-date('Z')) / (24*3600));
		}

		$dn = $allValues[0]['dn'];

		if($old_passwd)	// if old password given (not called by admin) --> bind as that user to change the pw
		{
			try {
				$ds = Api\Ldap::factory(true, '', $dn, $old_passwd);
			}
			catch (Api\Exception\NoPermission $e) {
				unset($e);
				return false;	// wrong old user password
			}
		}
		$ssh = null;//'/usr/bin/ssh -o "UserKnownHostsFile=/dev/null" -o "StrictHostKeyChecking=no" -i /var/lib/egroupware/id_rsa root@10.44.22.194';
		$config = $this->frontend->config && $this->frontend->config['ldap_context'] ?
			$this->frontend->config : $GLOBALS['egw_info']['server'];

		$params = array(
			'users/user','modify',
			'--binddn', $config['ldap_root_dn'],
			'--bindpwd', 5=>$config['ldap_root_pw'],
			'--dn', $dn,
			'--set', 'password='.$new_passwd,
		);
		if ($old_passwd)
		{
			$params[] = '--set';
			$params[] = 'pwdChangeNextLogin=0';
		}
		$cmd = self::DIRECTORY_MANAGER_BIN.' '.implode(' ', array_map('escapeshellarg', $params));
		if (isset($ssh)) $cmd = $ssh.' bash -c "\\"'.$cmd.'\\""';
		$output_arr = $ret = $matches = null;
		exec($cmd, $output_arr, $ret);
		$output = implode("\n", $output_arr);
		if ($ret || !preg_match('/^Object modified: ((uid|cn)=.*)$/mui', $output, $matches))
		{
			$params[5] = '********';	// mask out password!
			$cmd = self::DIRECTORY_MANAGER_BIN.' '.implode(' ', array_map('escapeshellarg', $params));
			throw new Api\Exception\WrongUserinput($cmd."\nreturned\n".$output);
		}
		if($old_passwd)	// if old password given (not called by admin) update the password in the session
		{
			// using time() is sufficient to represent the current time, we do not need the timestamp written to the storage
			Api\Cache::setSession('phpgwapi','auth_alpwchange_val',time());
		}
		return $new_passwd;
	}
}
