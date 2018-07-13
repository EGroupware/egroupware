<?php
/**
 * API - Auth Univention LDAP backend
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <rb@stylite.de>
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage auth
 */

namespace EGroupware\Api\Auth;

use EGroupware\Api;

/**
 * Univention LDAP Backend for auth
 *
 * This backend is mostly identical to LDAP backend and need to be configured in the same way.
 *
 * Only difference is that passwords are changed via univention-directory-manager CLI program,
 * to generate necesary hashes and Kerberos stuff.
 */
class Univention extends Ldap
{
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
		return Api\Accounts::getInstance()->backend->change_password($old_passwd, $new_passwd, $account_id, $update_lastchange);
	}
}