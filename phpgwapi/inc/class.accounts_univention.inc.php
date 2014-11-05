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
 * Only difference is that new users get created via univention-directory-manager CLI program,
 * to generate necesary Kerberos stuff.
 */
class accounts_univention extends accounts_ldap
{
	/**
	 * Name of binary to call
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
		if (!$data['account_id'] && $data['account_type'] !== 'g' && self::available())
		{
			$params = array(
				'users/user','create',
				'--binddn', $this->frontend->config['ldap_root_dn'],
				'--bindpwd', 5=>$this->frontend->config['ldap_root_pw'],
				'--position', $this->frontend->config['ldap_context'],
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
			}
			$cmd = self::DIRECTORY_MANAGER_BIN.' '.implode(' ', array_map('escapeshellarg', $params));
			$output_arr = $ret = $matches = null;
			exec($cmd, $output_arr, $ret);
			$output = explode("\n", $output_arr);
			if ($ret || !preg_match('/^Object created: (uid=.*)$/mui', $output, $matches))
			{
				$params[5] = '********';	// mask out password!
				$cmd = self::DIRECTORY_MANAGER_BIN.' '.implode(' ', array_map('escapeshellarg', $params));
				throw new egw_exception_wrong_userinput($cmd."\nreturned\n".$output);
			}
			$data['account_dn'] = $matches[1];
			$data['account_id'] = $this->name2id($matches[1], 'account_dn', 'u');
		}
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
