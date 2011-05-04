<?php
/**
 * Setup
 *
 * @link http://www.egroupware.org
 * @package setup
 * @author Dan Kuykendall <seek3r@phpgroupware.org>
 * @author Miles Lott <milos@groupwhere.org>
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * Class detecting the current installation status of EGroupware
 */
class setup_detection
{
	/**
	 * Get available application versions and data from filesystem
	 *
	 * @return array $setup_info
	 */
	function get_versions()
	{
		$d = dir(EGW_SERVER_ROOT);
		while($entry=$d->read())
		{
			if($entry != ".." && $entry != 'setup' && is_dir(EGW_SERVER_ROOT . '/' . $entry))
			{
				$f = EGW_SERVER_ROOT . '/' . $entry . '/setup/setup.inc.php';
				if (@file_exists ($f))
				{
					include($f);
					$setup_info[$entry]['filename'] = $f;
				}
			}
		}
		$d->close();

		// _debug_array($setup_info);
		@ksort($setup_info);
		return $setup_info;
	}

	/**
	 * Get versions of installed applications from database
	 *
	 * @param array $setup_info
	 * @return array $setup_info
	 */
	function get_db_versions($setup_info=null)
	{
		$tname = Array();
		$GLOBALS['egw_setup']->db->Halt_On_Error = 'no';

		$GLOBALS['egw_setup']->set_table_names();

		if($GLOBALS['egw_setup']->table_exist(array($GLOBALS['egw_setup']->applications_table),true))
		{
			/* one of these tables exists. checking for post/pre beta version */
			if($GLOBALS['egw_setup']->applications_table != 'applications')
			{
				$GLOBALS['egw_setup']->db->select($GLOBALS['egw_setup']->applications_table,'*',false,__LINE__,__FILE__);
				while(@$GLOBALS['egw_setup']->db->next_record())
				{
					$app = $GLOBALS['egw_setup']->db->f('app_name');
					if (!isset($setup_info[$app]))	// app source no longer there
					{
						$setup_info[$app] = array(
							'name' => $app,
							'tables' => $GLOBALS['egw_setup']->db->f('app_tables'),
							'version' => 'deleted',
						);
					}
					$setup_info[$app]['currentver'] = $GLOBALS['egw_setup']->db->f('app_version');
					$setup_info[$app]['enabled'] = $GLOBALS['egw_setup']->db->f('app_enabled');
				}
				/* This is to catch old setup installs that did not have phpgwapi listed as an app */
				$tmp = @$setup_info['phpgwapi']['version']; /* save the file version */
				if(!@$setup_info['phpgwapi']['currentver'])
				{
					$setup_info['phpgwapi']['currentver'] = $setup_info['admin']['currentver'];
					$setup_info['phpgwapi']['version'] = $setup_info['admin']['currentver'];
					$setup_info['phpgwapi']['enabled'] = $setup_info['admin']['enabled'];
					// _debug_array($setup_info['phpgwapi']);exit;
					// There seems to be a problem here.  If ['phpgwapi']['currentver'] is set,
					// The GLOBALS never gets set.
					$GLOBALS['setup_info'] = $setup_info;
					$GLOBALS['egw_setup']->register_app('phpgwapi');
				}
				else
				{
					$GLOBALS['setup_info'] = $setup_info;
				}
				$setup_info['phpgwapi']['version'] = $tmp; /* restore the file version */
			}
			else
			{
				$GLOBALS['egw_setup']->db->query('select * from applications');
				while(@$GLOBALS['egw_setup']->db->next_record())
				{
					if($GLOBALS['egw_setup']->db->f('app_name') == 'admin')
					{
						$setup_info['phpgwapi']['currentver'] = $GLOBALS['egw_setup']->db->f('app_version');
					}
					$setup_info[$GLOBALS['egw_setup']->db->f('app_name')]['currentver'] = $GLOBALS['egw_setup']->db->f('app_version');
				}
			}
		}
		// _debug_array($setup_info);
		return $setup_info;
	}

	/**
	 * Compare versions from filesystem and database and set status:
	 * 	U	Upgrade required/available
	 * 	R	upgrade in pRogress
	 * 	C	upgrade Completed successfully
	 * 	D	Dependency failure
	 * 	P	Post-install dependency failure
	 * 	F	upgrade Failed
	 * 	V	Version mismatch at end of upgrade (Not used, proposed only)
	 * 	M	Missing files at start of upgrade (Not used, proposed only)
	 */
	function compare_versions($setup_info,$try_downgrade=false)
	{
		foreach($setup_info as $key => $value)
		{
			//echo '<br>'.$value['name'].'STATUS: '.$value['status'];
			/* Only set this if it has not already failed to upgrade - Milosch */
			if(!( (@$value['status'] == 'F') || (@$value['status'] == 'C') ))
			{
				//if ($setup_info[$key]['currentver'] > $setup_info[$key]['version'])
				if(!$try_downgrade && $GLOBALS['egw_setup']->amorethanb($value['currentver'],@$value['version']) ||
					$value['version'] == 'deleted')
				{
					$setup_info[$key]['status'] = 'V';
				}
				elseif(@$value['currentver'] == @$value['version'])
				{
					$setup_info[$key]['status'] = 'C';
				}
				elseif($GLOBALS['egw_setup']->alessthanb(@$value['currentver'],@$value['version']))
				{
					$setup_info[$key]['status'] = 'U';
				}
				else
				{
					$setup_info[$key]['status'] = 'U';
				}
			}
		}
		// _debug_array($setup_info);
		return $setup_info;
	}

	function check_depends($setup_info)
	{
		/* Run the list of apps */
		foreach($setup_info as $key => $value)
		{
			/* Does this app have any depends */
			if(isset($value['depends']))
			{
				/* If so find out which apps it depends on */
				foreach($value['depends'] as $depkey => $depvalue)
				{
					/* I set this to False until we find a compatible version of this app */
					$setup_info['depends'][$depkey]['status'] = False;
					/* Now we loop thru the versions looking for a compatible version */

					foreach($depvalue['versions'] as $depskey => $depsvalue)
					{
						$currentver = $setup_info[$depvalue['appname']]['currentver'];
						if ($depvalue['appname'] == 'phpgwapi' && substr($currentver,0,6) == '0.9.99')
						{
							$currentver = '0.9.14.508';
						}
						$major = $GLOBALS['egw_setup']->get_major($currentver);
						if ($major == $depsvalue || substr($major,0,strlen($depsvalue)+1) == $depsvalue.'.')
						{
							$setup_info['depends'][$depkey]['status'] = True;
						}
						else	// check if majors are equal and minors greater or equal
						{
							$major_depsvalue = $GLOBALS['egw_setup']->get_major($depsvalue);
							$tmp = explode('.',$depsvalue); $minor_depsvalue = array_pop($tmp);
							$tmp = explode('.',$currentver); $minor = array_pop($tmp);
							if ($major == $major_depsvalue && $minor <= $minor_depsvalue)
							{
								$setup_info['depends'][$depkey]['status'] = True;
							}
						}
						//echo "<p>app=$key depends on $depvalue[appname](".implode(',',$depvalue['versions']).") current=$currentver, major=$major, depsvalue=$depsvalue, major_depsvalue=$major_depsvalue, minor_depsvalue=$minor_depsvalue, minor=$minor ==> ".(int)$setup_info['depends'][$depkey]['status']."</p>\n";
					}
				}
				/*
				 Finally, we loop through the dependencies again to look for apps that still have a failure status
				 If we find one, we set the apps overall status as a dependency failure.
				*/
				foreach($value['depends'] as $depkey => $depvalue)
				{
					if ($setup_info['depends'][$depkey]['status'] == False)
					{
						/* Only set this if it has not already failed to upgrade - Milosch */
						if($setup_info[$key]['status'] != 'F')//&& $setup_info[$key]['status'] != 'C')
						{
							/* Added check for status U - uninstalled apps carry this flag (upgrade from nothing == install).
							 * This should fix apps showing post-install dep failure when they are not yet installed.
							 */
							if($setup_info[$key]['status'] == 'C' || $setup_info[$key]['status'] == 'U')
							{
								$setup_info[$key]['status'] = 'D';
							}
							else
							{
								$setup_info[$key]['status'] = 'P';
							}
						}
					}
				}
			}
		}
		return $setup_info;
	}

	/**
	 * Called during the mass upgrade routine (Stage 1) to check for apps
	 * that wish to be excluded from this process.
	 */
	function upgrade_exclude($setup_info)
	{
		foreach($setup_info as $key => $value)
		{
			if(isset($value['no_mass_update']))
			{
				unset($setup_info[$key]);
			}
		}
		return $setup_info;
	}

	/**
	 * Check if header exists and is up to date
	 *
	 * @return int 1=no header.inc.php, 2=no header admin pw, 3=no instances, 4=need upgrade, 10=ok
	 */
	function check_header()
	{
		if(!file_exists(EGW_SERVER_ROOT.'/header.inc.php'))
		{
			$GLOBALS['egw_info']['setup']['header_msg'] = 'Stage One';
			return '1';
		}
		else
		{
			if(!@isset($GLOBALS['egw_info']['server']['header_admin_password']))
			{
				$GLOBALS['egw_info']['setup']['header_msg'] = 'Stage One (No header admin password set)';
				return '2';
			}
			elseif(!@isset($GLOBALS['egw_domain']))
			{
				$GLOBALS['egw_info']['setup']['header_msg'] = 'Stage One (Add domains to your header.inc.php)';
				return '3';
			}
			elseif(@$GLOBALS['egw_info']['server']['versions']['header'] != @$GLOBALS['egw_info']['server']['versions']['current_header'])
			{
				$GLOBALS['egw_info']['setup']['header_msg'] = 'Stage One (Upgrade your header.inc.php)';
				return '4';
			}
		}
		/* header.inc.php part settled. Moving to authentication */
		$GLOBALS['egw_info']['setup']['header_msg'] = 'Stage One (Completed)';
		return '10';
	}

	/**
	 * Check if database exists
	 *
	 * @param array $setup_info
	 * @return int 1=no database, 3=empty, 4=need upgrade, 10=complete
	 */
	function check_db($setup_info='')
	{
		$setup_info = $setup_info ? $setup_info : $GLOBALS['setup_info'];

		$GLOBALS['egw_setup']->db->Halt_On_Error = 'no';
		// _debug_array($setup_info);

		if (!$GLOBALS['egw_setup']->db->Link_ID)
		{
			$old = error_reporting();
			error_reporting($old & ~E_WARNING);	// no warnings
			$GLOBALS['egw_setup']->db->connect();
			error_reporting($old);
		}
		$GLOBALS['egw_setup']->set_table_names();

		if (!$GLOBALS['egw_setup']->db->Link_ID || !$GLOBALS['egw_setup']->db->Link_ID->_connectionID)
		{
			$GLOBALS['egw_info']['setup']['header_msg'] = 'Stage 1 (Create Database)';
			return 1;
		}
		if(!isset($setup_info['phpgwapi']['currentver']))
		{
			$setup_info = $this->get_db_versions($setup_info);
		}
		//_debug_array($setup_info);
		if (isset($setup_info['phpgwapi']['currentver']))
		{
			if(@$setup_info['phpgwapi']['currentver'] == @$setup_info['phpgwapi']['version'])
			{
				$GLOBALS['egw_info']['setup']['header_msg'] = 'Stage 1 (Tables Complete)';
				return 10;
			}
			else
			{
				$GLOBALS['egw_info']['setup']['header_msg'] = 'Stage 1 (Tables need upgrading)';
				return 4;
			}
		}
		else
		{
			/* no tables, so checking if we can create them */
			$GLOBALS['egw_setup']->db->query('CREATE TABLE egw_testrights ( testfield varchar(5) NOT NULL )');
			if(!$GLOBALS['egw_setup']->db->Errno)
			{
				$GLOBALS['egw_setup']->db->query('DROP TABLE egw_testrights');
				$GLOBALS['egw_info']['setup']['header_msg'] = 'Stage 3 (Install Applications)';
				return 3;
			}
			else
			{
				$GLOBALS['egw_info']['setup']['header_msg'] = 'Stage 1 (Create Database)';
				return 1;
			}
		}
	}

	/**
	 * Check if eGW configuration exists
	 *
	 * @return int 1 = Needs config, ..., 10 = Config Ok
	 */
	function check_config()
	{
		$GLOBALS['egw_setup']->db->Halt_On_Error = 'no';
		if(@$GLOBALS['egw_info']['setup']['stage']['db'] != 10)
		{
			return '';
		}

		$GLOBALS['egw_setup']->db->select($GLOBALS['egw_setup']->config_table,'config_name,config_value',array('config_app' => 'phpgwapi'),__LINE__,__FILE__);
		while($GLOBALS['egw_setup']->db->next_record())
		{
			$config[$GLOBALS['egw_setup']->db->f(0)] = $GLOBALS['egw_setup']->db->f(1);
		}

		$GLOBALS['egw_info']['setup']['header_msg'] = 'Stage 2 (Needs Configuration)';
		if(!count($config))
		{
			return 1;
		}
		$config_errors =& $GLOBALS['egw_info']['setup']['config_errors'];
		$config_errors = array();
		if (!$this->check_dir($config['temp_dir'],$error_msg))
		{
			$config_errors[] = lang("Your temporary directory '%1' %2",$config['temp_dir'],$error_msg);
		}

		if ((!isset($config['file_repository']) || $config['file_repository'] == 'sql') &&
			(!isset($config['file_store_contents']) || $config['file_store_contents'] == 'filesystem') &&
			!$this->check_dir($config['files_dir'],$error_msg,true))
		{
			$config_errors[] = lang("Your files directory '%1' %2",$config['files_dir'],$error_msg);
		}
		// set and create the default backup_dir
		if (@is_writeable($config['files_dir']) && !$config['backup_dir'] && $config['file_store_contents'] == 'filesystem')
		{
			$config['backup_dir'] = $config['files_dir'].'/db_backup';
			if (!is_dir($config['backup_dir']) && mkdir($config['backup_dir']))
			{
				$GLOBALS['egw_setup']->db->insert($GLOBALS['egw_setup']->config_table,array(
					'config_value' => $config['backup_dir'],
				),array(
					'config_app'  => 'phpgwapi',
					'config_name' => 'backup_dir',
				),__LINE__,__FILE__);
			}
		}
		if (isset($config['backup_mincount']))
		{
			$GLOBALS['egw_setup']->db->insert($GLOBALS['egw_setup']->config_table,array(
				'config_value' => $config['backup_mincount'],
				),array(
				'config_app'  => 'phpgwapi',
				'config_name' => 'backup_mincount',
			),__LINE__,__FILE__);
		}
		if (isset($config['backup_files']))
		{
			$GLOBALS['egw_setup']->db->insert($GLOBALS['egw_setup']->config_table,array(
				'config_value' => (int)$config['backup_files'],
				),array(
				'config_app'  => 'phpgwapi',
				'config_name' => 'backup_files',
			),__LINE__,__FILE__);
		};
		if (!$this->check_dir($config['backup_dir'],$error_msg,true))
		{
			$config_errors[] = lang("Your backup directory '%1' %2",$config['backup_dir'],$error_msg);
		}
		if (!$config['smtp_server'])
		{
			$config_errors[] = lang('Missing or uncomplete mailserver configuration');
		}
		if ($config_errors)
		{
			return 2;
		}
		$GLOBALS['egw_info']['setup']['header_msg'] = 'Stage 2 (Configuration OK)';
		return 10;
	}

	function check_lang($check = True)
	{
		$GLOBALS['egw_setup']->db->Halt_On_Error = 'no';
		if($check && $GLOBALS['egw_info']['setup']['stage']['db'] != 10)
		{
			return '';
		}
		if (!$check)
		{
			$GLOBALS['setup_info'] = $GLOBALS['egw_setup']->detection->get_db_versions($GLOBALS['setup_info']);
		}
		$GLOBALS['egw_setup']->db->query($q = "SELECT DISTINCT lang FROM {$GLOBALS['egw_setup']->lang_table}",__LINE__,__FILE__);
		if($GLOBALS['egw_setup']->db->num_rows() == 0)
		{
			$GLOBALS['egw_info']['setup']['header_msg'] = 'Stage 3 (No languages installed)';
			return 1;
		}
		else
		{
			while(@$GLOBALS['egw_setup']->db->next_record())
			{
				$GLOBALS['egw_info']['setup']['installed_langs'][$GLOBALS['egw_setup']->db->f('lang')] = $GLOBALS['egw_setup']->db->f('lang');
			}
			foreach($GLOBALS['egw_info']['setup']['installed_langs'] as $key => $value)
			{
				$sql = "SELECT lang_name FROM {$GLOBALS['egw_setup']->languages_table} WHERE lang_id = '".$value."'";
				$GLOBALS['egw_setup']->db->query($sql);
				if ($GLOBALS['egw_setup']->db->next_record())
				{
					$GLOBALS['egw_info']['setup']['installed_langs'][$value] = $GLOBALS['egw_setup']->db->f('lang_name');
				}
			}
			$GLOBALS['egw_info']['setup']['header_msg'] = 'Stage 3 (Completed)';
			return 10;
		}
	}

	/**
	 * Verify that all of an app's tables exist in the db
	 * @param $appname
	 * @param $any		optional, set to True to see if any of the apps tables are installed
	 */
	function check_app_tables($appname,$any=False)
	{
		$none = 0;
		$setup_info = $GLOBALS['setup_info'];

		if(@$setup_info[$appname]['tables'])
		{
			/* Make a copy, else we send some callers into an infinite loop */
			$copy = $setup_info;
			$GLOBALS['egw_setup']->db->Halt_On_Error = 'no';
			$table_names = $GLOBALS['egw_setup']->db->table_names();
			$tables = Array();
			foreach($table_names as $key => $val)
			{
				$tables[] = $val['table_name'];
			}
			foreach($copy[$appname]['tables'] as $key => $val)
			{
				if($GLOBALS['DEBUG'])
				{
					echo '<br>check_app_tables(): Checking: ' . $appname . ',table: ' . $val;
				}
				if(!in_array($val,$tables) && !in_array(strtolower($val),$tables))	// names in tables might be lowercase
				{
					if($GLOBALS['DEBUG'])
					{
						echo '<br>check_app_tables(): ' . $val . ' missing!';
					}
					if(!$any)
					{
						return False;
					}
					else
					{
						$none++;
					}
				}
				else
				{
					if($any)
					{
						if($GLOBALS['DEBUG'])
						{
							echo '<br>check_app_tables(): Some tables installed';
						}
						return True;
					}
				}
			}
		}
		if($none && $any)
		{
			if($GLOBALS['DEBUG'])
			{
				echo '<br>check_app_tables(): No tables installed';
			}
			return False;
		}
		else
		{
			if($GLOBALS['DEBUG'])
			{
				echo '<br>check_app_tables(): All tables installed';
			}
			return True;
		}
	}

	/**
	 * Checks if a directory exists, is writable by the webserver and optionaly is in the docroot
	 *
	 * @param string $dir path
	 * @param string &$msg error-msg: 'does not exist', 'is not writeable by the webserver' or 'is in the webservers docroot' (run through lang)
	 * @param boolean $check_in_docroot=false run an optional in docroot check
	 * @return boolean
	 */
	static function check_dir($dir,&$msg,$check_in_docroot=false)
	{
		if (!@is_dir($dir) && !@mkdir($dir,0700,true))
		{
			$msg = lang('does not exist');
			return false;
		}
		if (!@is_writeable($dir) && $_SERVER['HTTP_HOST'])	// only do the check if we run by the webserver
		{
			$msg = lang('is not writeable by the webserver');
			return false;
		}
		if ($check_in_docroot)
		{
			$docroots = array(realpath(EGW_SERVER_ROOT),realpath($_SERVER['DOCUMENT_ROOT']));
			$dir = realpath($dir);

			foreach ($docroots as $docroot)
			{
				$len = strlen($docroot);

				if ($docroot == substr($dir,0,$len) && $len>0)
				{
					$rest = substr($dir,$len);

					if (!strlen($rest) || $rest[0] == DIRECTORY_SEPARATOR)
					{
						$msg = lang('is in the webservers docroot');
						return false;
					}
				}
			}
		}
		return true;
	}
}
