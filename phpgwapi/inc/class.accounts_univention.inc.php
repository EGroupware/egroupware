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
 * @version $Id$
 */

/**
 * Univention LDAP Backend for accounts
 *
 * This backend is mostly identical to LDAP backend and need to be configured in the same way.
 *
 * Only difference is that new users get created via univention-directory-manager CLI program,
 * to generate necesary Kerberos stuff.
 *
 * Existing users and groups need to be renamed via same CLI, as removing and
 * adding entry under new dn via LDAP fails (Type or value exists).
 */
class accounts_univention extends accounts_ldap
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
			$config = $this->frontend->config && $this->frontend->config['ldap_context'] ?
				$this->frontend->config : $GLOBALS['egw_info']['server'];

			if (!$data['account_id'] && $data['account_type'] !== 'g')
			{
				$params = array(
					'users/user','create',
					'--binddn', $config['ldap_root_dn'],
					'--bindpwd', 5=>$config['ldap_root_pw'],
					'--position', $config['ldap_context'],
					'--set', 'username='.$data['account_lid'],
					'--set', 'firstname='.$data['account_firstname'],
					'--set', 'lastname='.$data['account_lastname'],
				);
				if ($data['account_email'])
				{
					$params[] = '--set'; $params[] = 'mailPrimaryAddress='.$data['account_email'];
				}
				if (!empty($data['account_passwd']))
				{
					$params[] = '--set'; $params[] = 'password='.$data['account_passwd'];
					// we need to set mailHomeServer, so mailbox gets created for Dovecot
					// get_default() does not work for Adminstrator, try acc_id=1 instead
					// if everything fails try hostname ...
					try {
						if (!($account = emailadmin_account::get_default(false, false, false)))
						{
							$account = emailadmin_account::read(1);
						}
						$hostname = $account->acc_imap_host;
					}
					catch(Exception $e) {
						unset($e);
					}
					if (empty($hostname)) $hostname = trim(system('hostname -f'));
					$params[] = '--set'; $params[] = 'mailHomeServer='.$hostname;
				}
				$cmd = self::DIRECTORY_MANAGER_BIN.' '.implode(' ', array_map('escapeshellarg', $params));
				$output_arr = $ret = $matches = null;
				exec($cmd, $output_arr, $ret);
				$output = implode("\n", $output_arr);
				if ($ret || !preg_match('/^Object created: (uid=.*)$/mui', $output, $matches))
				{
					$params[5] = '********';	// mask out password!
					$cmd = self::DIRECTORY_MANAGER_BIN.' '.implode(' ', array_map('escapeshellarg', $params));
					throw new egw_exception_wrong_userinput($cmd."\nreturned\n".$output);
				}
				$data['account_dn'] = $matches[1];
				$data['account_id'] = $this->name2id($data['account_lid'], 'account_lid', 'u');
			}
			elseif($data['account_id'] && ($data['old_loginid'] || ($data['old_loginid'] = $this->id2name($data['account_id']))) &&
				$data['account_lid'] != $data['old_loginid'] &&
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
				$output_arr = $ret = $matches = null;
				exec($cmd, $output_arr, $ret);
				$output = implode("\n", $output_arr);
				if ($ret || !preg_match('/^Object modified: ((uid|cn)=.*)$/mui', $output, $matches))
				{
					$params[5] = '********';	// mask out password!
					$cmd = self::DIRECTORY_MANAGER_BIN.' '.implode(' ', array_map('escapeshellarg', $params));
					throw new egw_exception_wrong_userinput($cmd."\nreturned\n".$output);
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
	 * Check if our function depending on an external binary is available
	 *
	 * @return boolean
	 */
	public static function available()
	{
		return file_exists(self::DIRECTORY_MANAGER_BIN) && is_executable(self::DIRECTORY_MANAGER_BIN);
	}
}
