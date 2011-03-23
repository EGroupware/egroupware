<?php
/**
 * eGroupWare API - Auth from PAM
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/lgpl-license.php LGPL - GNU Lesser General Public License
 * @package api
 * @subpackage authentication
 * @version $Id$
 */

/**
 * Auth from PAM
 *
 * Requires PHP PAM extension: pecl install pam
 *
 * To read full name from password file PHP's posix extension is needed (sometimes in package php_process)
 */
class auth_pam implements auth_backend
{
	/**
	 * password authentication
	 *
	 * @param string $username username of account to authenticate
	 * @param string $passwd corresponding password
	 * @param string $passwd_type='text' 'text' for cleartext passwords (default)
	 * @return boolean true if successful authenticated, false otherwise
	 */
	function authenticate($username, $passwd, $passwd_type='text')
	{
		if (pam_auth($username, get_magic_quotes_gpc() ? stripslashes($passwd) : $passwd))
		{
			// for new accounts read full name from password file and pass it to EGroupware
			if (!$GLOBALS['egw']->accounts->name2id($username) &&
				function_exists('posix_getpwnam') && ($data = posix_getpwnam($username)))
			{
				list($fullname) = explode(',',$data['gecos']);
				$parts = explode(' ',$fullname);
				if (count($parts) > 1)
				{
					$lastname = array_pop($parts);
					$firstname = implode(' ',$parts);
					$email = common::email_address($firstname, $lastname, $username);

					$GLOBALS['auto_create_acct'] = array(
						'firstname' => $firstname,
						'lastname' => $lastname,
						'email' => $email,
						'account_id' => $data['uid'],
					);
				}
			}
			return True;
		}
		return False;
	}

	/**
	 * changes password
	 *
	 * @param string $old_passwd must be cleartext or empty to not to be checked
	 * @param string $new_passwd must be cleartext
	 * @param int $account_id=0 account id of user whose passwd should be changed
	 * @return boolean true if password successful changed, false otherwise
	 */
	function change_password($old_passwd, $new_passwd, $account_id=0)
	{
		// deny password changes.
		return False;
	}
}
