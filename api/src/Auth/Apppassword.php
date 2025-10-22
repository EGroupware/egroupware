<?php
/**
 * EGroupware API - Token/Application Password Authentication
 *
 * This auth-backend denies authentication with the real password, and only allows application-passwords.
 *
 * @link https://www.egroupware.org
 * @author Ralf Becker <ralfbecker@outdoor-training.de>
 * @license https://opensource.org/licenses/lgpl-license.php LGPL - GNU Lesser General Public License
 * @package api
 * @subpackage auth
 */

namespace EGroupware\Api\Auth;

/**
 * Authentication with application password / token
 *
 * This auth-backend denies authentication with the real password, and only allows application-passwords.
 * It's meant to be used with CalDAV/CardDAV or eSync to force the use of application-passwords.
 */
class Apppassword implements Backend
{
	/**
	 * authentication method
	 *
	 * This method always returns false, and therefore denying authentication with a password!
	 *
	 * @param string $username username of account to authenticate
	 * @param string $passwd corresponding password
	 * @return boolean true if successful authenticated, false otherwise
	 */
	function authenticate($username, $passwd, $passwd_type='text')
	{
		return false;
	}

	/**
	 * Required changes password dummy method
	 *
	 * @param string $old_passwd must be cleartext or empty to not be checked
	 * @param string $new_passwd must be cleartext
	 * @param int $account_id account id of user whose passwd should be changed
	 * @return boolean true if password successful changed, false otherwise
	 */
	function change_password($old_passwd, $new_passwd, $account_id=0)
	{
		return false;
	}
}