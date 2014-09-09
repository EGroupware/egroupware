<?php
/**
 * eGgroupWare setup - create a first eGroupWare user / admin and our two standard groups: Default & Admins
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package setup
 * @copyright (c) 2007-10 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * setup command: create a first eGroupWare user / admin and our two standard groups: Default & Admins
 *
 * @ToDo: get rid of the ugly setup_admin.php include
 */
class setup_cmd_admin extends setup_cmd
{
	/**
	 * Allow to run this command via setup-cli
	 */
	const SETUP_CLI_CALLABLE = true;

	/**
	 * Constructor
	 *
	 * @param string|array $domain domain-name or array with all parameters
	 * @param string $config_user=null user to config the domain (or header_admin_user)
	 * @param string $config_passwd=null pw of above user
	 * @param string $admin_user=null
	 * @param string $admin_password=null
	 * @param string $admin_firstname=null
	 * @param string $admin_lastname=null
	 * @param string $admin_email=null
	 * @param array $config=array() extra config for the account object: account_repository, ldap_*
	 * @param string $lang='en'
	 */
	function __construct($domain,$config_user=null,$config_passwd=null,$admin_user=null,$admin_password=null,
		$admin_firstname=null,$admin_lastname=null,$admin_email=null,array $config=array(),$lang='en')
	{
		if (!is_array($domain))
		{
			$domain = array(
				'domain'          => $domain,
				'config_user'     => $config_user,
				'config_passwd'   => $config_passwd,
				'admin_user'      => $admin_user,
				'admin_password'  => $admin_password,
				'admin_firstname' => $admin_firstname,
				'admin_lastname'  => $admin_lastname,
				'admin_email'     => $admin_email,
				'config'          => $config,
				'lang'            => $lang,
			);
		}
		//echo __CLASS__.'::__construct()'; _debug_array($domain);
		admin_cmd::__construct($domain);
	}

	/**
	 * run the command: create eGW admin and standard groups
	 *
	 * @param boolean $check_only=false only run the checks (and throw the exceptions), but not the command itself
	 * @return string success message
	 * @throws Exception(lang('Wrong credentials to access the header.inc.php file!'),2);
	 * @throws Exception('header.inc.php not found!');
	 */
	protected function exec($check_only=false)
	{
		if ($check_only && $this->remote_id)
		{
			return true;	// can only check locally
		}
		$this->check_installed($this->domain,15);

		if (!$this->admin_firstname) $this->set_defaults['admin_firstname'] = $this->admin_firstname = lang('Admin');
		if (!$this->admin_lastname) $this->set_defaults['admin_lastname'] = $this->admin_lastname = lang('User');
		if (strpos($this->admin_email,'$') !== false)
		{
			$this->set_defaults['email'] = $this->admin_email = str_replace(
				array('$domain','$uid','$account_lid'),
				array($this->domain,$this->admin_user,$this->admin_user),$this->admin_email);
		}
		$_POST['username'] = $this->admin_user;
		$_POST['passwd2']  = $_POST['passwd'] = $this->admin_password;
		$_POST['fname']    = $this->admin_firstname;
		$_POST['lname']    = $this->admin_lastname;
		$_POST['email']    = $this->admin_email;
		$_POST['ConfigLang'] = $this->lang;

		$_POST['submit'] = true;
		$error = include(dirname(__FILE__).'/../admin_account.php');

		$this->restore_db();

		switch ($error)
		{
			case 41:
				throw new egw_exception_wrong_userinput(lang('Error in admin-creation !!!'),41);
			case 42:
				throw new egw_exception_wrong_userinput(lang('Error in group-creation !!!'),42);
		}
		$this->restore_db();

		// run admin/admin-cli.php --add-user to store the new accounts once in EGroupware
		// to run all hooks (some of them can NOT run inside setup)
		$cmd = EGW_SERVER_ROOT.'/admin/admin-cli.php --add-user '.
			escapeshellarg($this->admin_user.'@'.$this->domain.','.$this->admin_password.','.$this->admin_user);
		if (php_sapi_name() !== 'cli' || !file_exists(EGW_SERVER_ROOT.'/stylite') || file_exists(EGW_SERVER_ROOT.'/managementserver'))
		{
			exec($cmd,$output,$ret);
		}
		$output = implode("\n",$output);
		//echo "ret=$ret\n".$output;
		if ($ret)
		{
			throw new egw_exception ($output,$ret);
		}
		return lang('Admin account successful created.');
	}
}
