<?php
/**
 * eGgroupWare setup - abstract baseclass for all setup commands, extending admin_cmd
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package setup
 * @copyright (c) 2007 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id: class.admin_cmd_check_acl.inc.php 24709 2007-11-27 03:20:28Z ralfbecker $ 
 */

/**
 * setup command: abstract baseclass for all setup commands, extending admin_cmd
 */
abstract class setup_cmd extends admin_cmd 
{
	/**
	 * Defaults set for empty options while running the command
	 *
	 * @var array
	 */
	public $set_defaults = array();

	/**
	 * Should be called by every command usually requiring header admin rights
	 *
	 * @throws Exception(lang('Wrong credentials to access the header.inc.php file!'),2);
	 */
	protected function _check_header_access()
	{
		if ($this->header_secret != ($secret = $this->_calc_header_secret($GLOBALS['egw_info']['server']['header_admin_user'],
				$GLOBALS['egw_info']['server']['header_admin_password'])))
		{
			//echo "header_secret='$this->header_secret' != '$secret'=_calc_header_secret({$GLOBALS['egw_info']['server']['header_admin_user']},{$GLOBALS['egw_info']['server']['header_admin_password']})\n";
			throw new Exception (lang('Wrong credentials to access the header.inc.php file!'),2);
		}
	}
	
	/**
	 * Set the user and pw required for any operation on the header file
	 *
	 * @param string $user
	 * @param string $pw password or md5 hash of it
	 */
	public function set_header_secret($user,$pw)
	{
		if ($this->uid || parent::save(false))	// we need to save first, to get the uid
		{
			$this->header_secret = $this->_calc_header_secret($user,$pw);
		}
		else
		{
			throw new Exception ('failed to set header_secret!');
		}
	}
	
	/**
	 * Calculate the header_secret used to access the header from this command
	 * 
	 * It's an md5 over the uid, header-admin-user and -password.
	 *
	 * @param string $header_admin_user
	 * @param string $header_admin_password
	 * @return string
	 */
	private function _calc_header_secret($header_admin_user=null,$header_admin_password=null)
	{
		if (!self::is_md5($header_admin_password)) $header_admin_password = md5($header_admin_password);

		$secret = md5($this->uid.$header_admin_user.$header_admin_password);
		//echo "header_secret='$secret' = md5('$this->uid'.'$header_admin_user'.'$header_admin_password')\n";
		return $secret;
	}
	
	/**
	 * Restore our db connection
	 *
	 */
	static protected function restore_db()
	{
		$GLOBALS['egw']->db->disconnect();
		$GLOBALS['egw']->db->connect();
		
	}
	
	/**
	 * Saving the object to the database, reimplemented to not do it in setup context
	 *
	 * @param boolean $set_modifier=true set the current user as modifier or 0 (= run by the system)
	 * @return boolean true on success, false otherwise
	 */
	function save($set_modifier=true)
	{
		if (is_object($GLOBALS['egw']->db))
		{
			return parent::save($set_modifier);
		}
		return true;
	}
}
