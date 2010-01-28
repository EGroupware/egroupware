<?php
/**
 * eGroupWare API - LDAP Authentication with fallback to SQL
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <ralfbecker@outdoor-training.de>
 * @license http://opensource.org/licenses/lgpl-license.php LGPL - GNU Lesser General Public License
 * @package api
 * @subpackage authentication
 * @version $Id$
 */

/**
 * Authentication agains a LDAP Server with fallback to SQL
 * 
 * For other fallback types, simply change auth backends in constructor call
 */
class auth_fallback implements auth_backend
{
	/**
	 * Primary auth backend
	 * 
	 * @var auth_backend
	 */
	private $primary_backend;
	
	/**
	 * Fallback auth backend
	 * 
	 * @var auth_backend
	 */
	private $fallback_backend;
	
	/**
	 * Constructor
	 */
	function __construct($primary='auth_ldap',$fallback='auth_sql')
	{
		$this->primary_backend = new $primary;
		
		$this->fallback_backend = new $fallback;
	}

	/**
	 * authentication against LDAP with fallback to SQL
	 *
	 * @param string $username username of account to authenticate
	 * @param string $passwd corresponding password
	 * @return boolean true if successful authenticated, false otherwise
	 */
	function authenticate($username, $passwd, $passwd_type='text')
	{
		if ($this->primary_backend->authenticate($username, $passwd, $passwd_type))
		{
			egw_cache::setSession(__CLASS__,'backend_used','primary');
			return true;
		}
		if ($this->fallback_backend->authenticate($username,$passwd, $passwd_type))
		{
			egw_cache::setSession(__CLASS__,'backend_used','fallback');
			return true;
		}
		return false;
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
	 * @return boolean true if password successful changed, false otherwise
	 */
	function change_password($old_passwd, $new_passwd, $account_id=0)
	{
		if (egw_cache::getSession(__CLASS__,'backend_used') == 'primary')
		{
			return $this->primary_backend->change_password($old_passwd, $new_passwd, $account_id);
		}
		return $this->fallback_backend->change_password($old_passwd, $new_passwd, $account_id);
	}
}
