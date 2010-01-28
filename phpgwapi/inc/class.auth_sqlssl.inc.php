<?php
/**
 * eGroupWare API - Authentication based on SQL table and X.509 certificates
 *
 * @link http://www.egroupware.org
 * @author Andreas 'Count' Kotes <count@flatline.de>
 * @license http://opensource.org/licenses/lgpl-license.php LGPL - GNU Lesser General Public License
 * @package api
 * @subpackage authentication
 * @version $Id$
 */

/**
 * Authentication based on SQL table and X.509 certificates
 * 
 * @todo rewrite using auth_sql backend class
 */
class auth_sqlssl implements auth_backend
{
	/**
	 * @var egw_db
	 */
	var $db;
	var $table = 'egw_accounts';
	var $previous_login = -1;

	/**
	 * Constructor
	 */
	function __construct()
	{
		$this->db = $GLOBALS['egw']->db;
	}

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
		$local_debug = False;

		if($local_debug)
		{
			echo "<b>Debug SQL: uid - $username passwd - $passwd</b>";
		}
		$this->db->select($this->table,'account_lid,account_pwd',array(
			'account_lid' => $username,
			'account_status' => 'A',
			'account_type'   => 'u',
		),__LINE__,__FILE__);
		
		if (!$this->db->next_record() || $GLOBALS['egw_info']['server']['case_sensitive_username'] && $this->db->f('account_lid') != $username)
		{
			return false;
		}

		# Apache + mod_ssl provide the data in the environment
		# Certificate (chain) verification occurs inside mod_ssl
		# see http://www.modssl.org/docs/2.8/ssl_howto.html#ToC6
		if(!isset($_SERVER['SSL_CLIENT_S_DN']))
		{
			# if we're not doing SSL authentication, behave like auth_sql
			return auth::compare_password($passwd,$this->db->f('account_pwd'),$this->type,strtolower($username));
		}
		return True;
	}

	/**
	 * changes password
	 *
	 * @param string $old_passwd must be cleartext or empty to not to be checked
	 * @param string $new_passwd must be cleartext
	 * @param int $account_id=0 account id of user whose passwd should be changed
	 * @return boolean true if password successful changed, false otherwise
	 */
	function change_password($old_passwd, $new_passwd, $account_id = 0)
	{
		if(!$account_id)
		{
			$account_id = $GLOBALS['egw_info']['user']['account_id'];
		}

		$encrypted_passwd = auth::encrypt_sql($new_passwd);

		$GLOBALS['egw']->db->update($this->table,array(
			'account_pwd' => $encrypted_passwd,
			'account_lastpwd_change' => time(),
		),array(
			'account_id' => $account_id,
		),__LINE__,__FILE__);

		$GLOBALS['egw']->session->appsession('password','phpgwapi',$new_passwd);

		return $encrypted_passwd;
	}
}
