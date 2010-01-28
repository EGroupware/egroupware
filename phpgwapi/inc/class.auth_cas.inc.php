<?php
/**
 * eGroupWare API - Authentication from CAS
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/lgpl-license.php LGPL - GNU Lesser General Public License
 * @package api
 * @subpackage authentication
 * @version $Id$
 */

/**
 * eGroupWare API - Authentication based on CAS (Central Authetication Service)
 */
class auth_cas implements auth_backend
{
	var $previous_login = -1;

	/**
	 * authentication against CAS
	 *
	 * @param string $username username of account to authenticate
	 * @param string $passwd corresponding password
	 * @param string $passwd_type='text' 'text' for cleartext passwords (default)
	 * @return boolean true if successful authenticated, false otherwise
	 */
	function authenticate($username, $passwd, $passwd_type='text')
	{
		/* if program goes here, authenticate is, normaly, already verified by CAS */
		if ($GLOBALS['egw_info']['server']['account_repository'] != 'ldap' &&
			$GLOBALS['egw_info']['server']['account_repository'] != 'ldsq') /* For anonymous LDAP connection */
		{
			if (!($id = $GLOBALS['egw']->accounts->name2id($username,'account_lid','u')) &&
				$GLOBALS['egw_info']['server']['auto_create_acct'])
			{
				// create a global array with all availible info about that account
				$GLOBALS['auto_create_acct'] = array();
				foreach(array(
					'givenname' => 'firstname',
					'sn'        => 'lastname',
					'uidnumber' => 'id',
					'mail'      => 'email',
					'gidnumber' => 'primary_group',
					) as $ldap_name => $acct_name)
				{
					$GLOBALS['auto_create_acct'][$acct_name] = $GLOBALS['egw']->translation->convert($allValues[0][$ldap_name][0],'utf-8');
				}
				return True;
			}
			return $id && $GLOBALS['egw']->accounts->id2name($id,'account_status') == 'A' && phpCAS::checkAuthentication();
		}
		return phpCAS::checkAuthentication();
	}

	/**
	 * changes password in CAS
	 *
	 * @param string $old_passwd must be cleartext or empty to not to be checked
	 * @param string $new_passwd must be cleartext
	 * @param int $account_id=0 account id of user whose passwd should be changed
	 * @return boolean true if password successful changed, false otherwise
	 */
	function change_password($old_passwd, $new_passwd, $account_id=0)
	{
		/* Not allowed */
		return false;
	}
}