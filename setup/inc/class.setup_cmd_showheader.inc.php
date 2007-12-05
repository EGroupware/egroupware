<?php
/**
 * eGgroupWare setup - show/return the header.inc.php
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package setup
 * @copyright (c) 2007 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id: class.admin_cmd_check_acl.inc.php 24709 2007-11-27 03:20:28Z ralfbecker $ 
 */

/**
 * setup command: show/return the header.inc.php
 * 
 * Has no constructor, as we have no arguments beside the header admin user and password,
 * which get set via setup_cmd::set_header_secret($user,$pw)
 */
class setup_cmd_showheader extends setup_cmd 
{
	/**
	 * Constructor
	 *
	 * @param array $data=array() default parm from parent class, no real parameters
	 */
	function __construct($data=array())
	{
		//echo __CLASS__.'::__construct()'; _debug_array($data);
		admin_cmd::__construct($data);
	}

	/**
	 * show/return the header.inc.php
	 * 
	 * @param boolean $check_only=false only run the checks (and throw the exceptions), but not the command itself
	 * @return string serialized $GLOBALS defined in the header.inc.php
	 * @throws Exception(lang('Wrong credentials to access the header.inc.php file!'),2);
	 * @throws Exception('header.inc.php not found!');
	 */
	function exec($check_only=false)
	{
		if ($this->remote_id && $check_only) return true;	// cant check for the remote site locally!
		
		$this->_check_header_access();

		if ($check_only) return true;

		$egw_info_backup = $GLOBALS['egw_info'];
		$GLOBALS['egw_info'] = array (
			'flags' => array(
				'noapi' => true,
			),
		);
		if (!($header = file_get_contents(EGW_SERVER_ROOT.'/header.inc.php')))
		{
			throw new Exception('header.inc.php not found!');
		}
		eval(str_replace(array('<?php','perfgetmicrotime'),array('','perfgetmicrotime2'),$header));

		// unset the flags, they are not part of  the header
		unset($GLOBALS['egw_info']['flags']);

		// include the api version of this instance
		$GLOBALS['egw_info']['server']['versions']['phpgwapi'] = $egw_info_backup['server']['versions']['phpgwapi'];

		$ret = serialize(array(
			'egw_info' => $GLOBALS['egw_info'],
			'egw_domain' => $GLOBALS['egw_domain'],
			'EGW_SERVER_ROOT' => EGW_SERVER_ROOT,
			'EGW_INCLUDE_ROOT' => EGW_INCLUDE_ROOT,
		));

		$GLOBALS['egw_info'] = $egw_info_backup;
		
		return $ret;
	}
}
