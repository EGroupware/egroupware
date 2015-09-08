<?php
/**
 * eGroupWare API - Auth from NIS
 *
 * @link http://www.egroupware.org
 * @author 	* by Dylan Adams <dadams@jhu.edu>
 * Copyright (C) 2001 Dylan Adams
 * @license http://opensource.org/licenses/lgpl-license.php LGPL - GNU Lesser General Public License
 * @package api
 * @subpackage authentication
 * @version $Id$
 */

/**
 * Auth from NIS
 */
class auth_nis implements auth_backend
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
		$domain = yp_get_default_domain();
		if(!empty($GLOBALS['egw_info']['server']['nis_domain']))
		{
			$domain = $GLOBALS['egw_info']['server']['nis_domain'];
		}

		$map = "passwd.byname";
		if(!empty($GLOBALS['egw_info']['server']['nis_map']))
		{
			$map = $GLOBALS['egw_info']['server']['nis_map'];
		}
		$entry = yp_match( $domain, $map, $username );

		/*
		 * we assume that the map is structured in the usual
		 * unix passwd flavor
		 */
		$entry_array = explode(':', $entry);
		$stored_passwd = $entry_array[1];

		$encrypted_passwd = crypt($passwd, $stored_passwd);

		return($encrypted_passwd == $stored_passwd);
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
		// can't change passwords unless server runs as root (bad idea)
		return( False );
	}
}
