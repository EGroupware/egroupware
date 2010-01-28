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
 * Requires php_pam extension!
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
		if (pam_auth($username, get_magic_quotes_gpc() ? stripslashes($passwd) : $passwd, &$error)) 
		{
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
