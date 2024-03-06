<?php
/**
 * EGroupware setup - update / install an EGroupware instance
 *
 * @link http://www.egroupware.org
 * @author Miles Lott <milos@groupwhere.org>
 * @copyright 2001-2004 Miles Lott <milos@groupwhere.org>
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * Upgrade process rewritten by <RalfBecker@outdoor-training.de> to no longer require tables_baseline files and delta-upgrades
 * @package setup
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

use EGroupware\Api;

/**
 * Update / install an EGroupware instance
 *
 * app status values:
 *	U	Upgrade required/available
 *	R	upgrade in progress
 *	C	upgrade Completed successfully
 *	D	Dependency failure
 * 	P	Post-install dependency failure
 *	F	upgrade Failed
 *	V	Version mismatch at end of upgrade (Not used, proposed only)
 *	M	Missing files at start of upgrade (Not used, proposed only)
 */
class setup_process
{
	var $tables;
	var $updateincluded = array();

	/**
	 * Target version of a complete upgrade, set by pass()
	 *
	 * @var string
	 */
	var $api_version_target;

	/**
	 * @var Api\Db\Schema
	 */
	protected $oProc;
	/**
	 * @var Api\Db
	 */
	protected $db;

	/**
	 * create schema_proc object
	 */
	function init_process()
	{
		if (empty($GLOBALS['egw_setup']->oProc))
		{
			$this->oProc = $GLOBALS['egw_setup']->oProc = new Api\Db\Schema();
		}
		else
		{
			$this->oProc = $GLOBALS['egw_setup']->oProc;
		}
		$this->db = $this->oProc->m_odb ?? $GLOBALS['egw_setup']->db;
	}

	/**
	 * the mother of all multipass upgrade parental loop functions
	 *
	 * @param array $setup_info array of application info from setup.inc.php files
	 * @param string $type='new' defaults to new(install), could also be 'upgrade'
	 * @param boolean $DEBUG=false print debugging info
	 * @param boolean $force_en=false install english language files, not used anymore
	 * @param string $system_charset=null charset to use
	 * @param array $preset_config=array()
	 */
	function pass(array $setup_info,$method='new',$DEBUG=False,$force_en=False,$preset_config=array())
	{
		unset($force_en);	// no longer used

		if(!$method)
		{
			return False;
		}

		// update to 16.x with no api installed yet
		if ($method == 'upgrade' && !isset($setup_info['api']['currentver']))
		{
			// remove api dependency
			unset($setup_info['phpgwapi']['depends']['api']);

			$pass['phpgwapi'] = $setup_info['phpgwapi'];
			$pass['emailadmin'] = $setup_info['emailadmin'];
			$pass['api'] = $setup_info['api'];

			// mark api as already installed in version 14.3.907
			$setup_info['api']['version'] = $setup_info['api']['currentversion'] = '14.3.907';
			$GLOBALS['egw_setup']->register_app('api', 99, $setup_info);
			$setup_info['api']['version'] = $pass['api']['version'];
		}
		// new install or upgrade after 16.x api is installed
		else
		{
			$pass['api'] = $setup_info['api'];
			if (file_exists(EGW_SERVER_ROOT.'/phpgwapi') && is_readable(EGW_SERVER_ROOT.'/phpgwapi'))
			{
				$pass['phpgwapi'] = $setup_info['phpgwapi'];
			}
			// ignore emailadmin, it's only there for updates
			unset($setup_info['emailadmin']);
		}
		$pass['admin']    = $setup_info['admin'];
		$pass['preferences'] = $setup_info['preferences'];
		if (file_exists(EGW_SERVER_ROOT.'/etemplate'))
		{
			$pass['etemplate'] = $setup_info['etemplate'];	// helps to minimize passes, as many apps depend on it
		}
		$this->api_version_target = $setup_info['api']['version'];

		$i = 1;
		$passed = array();
		$passing = array();
		$pass_string = implode (':', array_keys($pass));
		$passing_string = implode (':', array_keys($passing));
		while($pass_string != $passing_string)
		{
			$passing = array();
			if($DEBUG) { echo '<br>process->pass(): #' . $i . ' for ' . $method . ' processing' . "\n"; }

			// Check current versions and dependencies
			$setup_info = $GLOBALS['egw_setup']->detection->check_depends(
				$GLOBALS['egw_setup']->detection->compare_versions(
					$GLOBALS['egw_setup']->detection->get_db_versions($setup_info), true));

			// stuff the rest of the apps, but only those with available upgrades
			foreach($setup_info as $key => $value)
			{
				// check if app is either installed or supports the used database
				if (!isset($value['currentver']) && isset($value['only_db']) && (
					is_array($value['only_db']) && !in_array($GLOBALS['egw_setup']->db->Type,$value['only_db']) ||
					!is_array($value['only_db']) && $GLOBALS['egw_setup']->db->Type != $value['only_db']))
				{
					continue;	// app does not support this db-type, dont try installing it
				}
				if(/*$value['name'] != 'phpgwapi' &&*/ $value['status'] == 'U')
				{
					if($passed[$value['name']]['status'] != 'F' && $passed[$value['name']]['status'] != 'C')
					{
						$pass[$value['name']] = $setup_info[$value['name']];
					}
				}
			}

			switch($method)
			{
				case 'new':
					if (empty($GLOBALS['egw_info']['server']['temp_dir']))
					{
						$GLOBALS['egw_info']['server']['temp_dir'] = sys_get_temp_dir();
					}
					/* Create tables and insert new records for each app in this list */
					$passing_c = $this->current($pass,$DEBUG);
					if (isset($pass['api'])) $this->save_minimal_config($preset_config);
					$passing = $this->default_records($passing_c,$DEBUG);
					break;
				case 'upgrade':
					/* Run upgrade scripts on each app in the list */
					$passing = $this->upgrade($pass,$DEBUG);
					//_debug_array($pass);exit;
					break;
				default:
					/* What the heck are you doing? */
					return False;
			}

			$pass = array();
			foreach($passing as $key => $value)
			{
				if($value['status'] == 'C')
				{
					$passed[$value['name']] = $passing[$value['name']];
					if($DEBUG) { echo '<br>process->pass(): '.$passed[$value['name']]['name'] . ' install completed'."\n"; }
				}
				elseif($value['status'] == 'F')
				{
					$setup_info[$value['name']] = $passing[$value['name']];
					if($DEBUG) { echo '<br>process->pass(): '.$setup_info[$value['name']]['name'] . ' install failed'."\n"; }
				}
				elseif($value['status'] == 'D')
				{
					$pass[$value['name']] = $setup_info[$value['name']];
					if($DEBUG) { echo '<br>process->pass(): '.$pass[$value['name']]['name'] . ' fails dependency check on this pass'."\n"; }
				}
				else
				{
					$tmp = $passing[$value['name']]['name'];
					if($DEBUG) { echo '<br>process->pass(): '.$tmp . ' skipped on this pass'."\n"; }
				}
			}

			$i++;
			if($i == 20) /* Then oops it broke */
			{
				echo '<br>Setup failure: excess looping in process->pass():'."\n";
				echo '<br>Pass:<br>'."\n";
				_debug_array($pass);
				echo '<br>Passed:<br>'."\n";
				_debug_array($passed);
				exit;
			}
			$pass_string = implode (':', array_keys($pass));
			$passing_string = implode (':', array_keys($passing));
		}

		// remove all apps which should be automatic deinstalled
		if (($deinstall = setup_cmd::check_autodeinstall()))
		{
			$this->remove($deinstall, $setup_info, $DEBUG);
		}

		try {
			// flush instance cache: also registers hooks and flushes image cache
			Api\Cache::flush(Api\Cache::INSTANCE);
		}
		catch(Exception $e) {
			unset($e);
			// ignore exception, as during a new install, there's no cache configured and therefore no need to unset
		}
		/* now return the list */
		return array_merge($setup_info,$passed);
	}

	/**
	 * saves a minimal default config, so you get a running install without entering and saveing Step #2 config
	 *
	 * @param array $preset_config =array()
	 */
	function save_minimal_config(array $preset_config=array())
	{
		$is_windows = strtoupper(substr(PHP_OS,0,3)) == 'WIN';

		$current_config['site_title'] = 'EGroupware';
		$current_config['hostname']  = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';

		// guessing the EGw url
		if (isset($_SERVER['HTTP_HOST']))
		{
			$parts = explode('/',$_SERVER['PHP_SELF']);
			array_pop($parts);	// remove config.php
			array_pop($parts);	// remove setup
			$current_config['webserver_url'] = implode('/',$parts);
			$egroupwareDirName = end($parts);
		}
		else	// eg. cli install --> use defaults
		{
			$current_config['webserver_url'] = '/egroupware';
			$egroupwareDirName = 'egroupware';
		}
		if(!$is_windows)
		{
			if(@is_dir('/tmp'))
			{
				$current_config['temp_dir'] = '/tmp';
			}
			else
			{
				$current_config['temp_dir'] = '/path/to/temp/dir';
			}
			$current_config['files_dir'] = '/var/lib/'.$egroupwareDirName.'/'.$GLOBALS['egw_setup']->ConfigDomain.'/files';
			$current_config['backup_dir'] = '/var/lib/'.$egroupwareDirName.'/'.$GLOBALS['egw_setup']->ConfigDomain.'/backup';
			$current_config['aspell_path'] = '/usr/bin/aspell';
		}
		else
		{
			if(@is_dir('c:\\windows\\temp'))
			{
				$current_config['temp_dir'] = 'c:\\windows\\temp';
			}
			else
			{
				$current_config['temp_dir'] = 'c:\\path\\to\\temp\\dir';
			}
			$current_config['files_dir'] = 'C:\\Program Files\\'.$egroupwareDirName.'\\'.$GLOBALS['egw_setup']->ConfigDomain.'\\files';
			$current_config['backup_dir'] = 'C:\\Program Files\\'.$egroupwareDirName.'\\'.$GLOBALS['egw_setup']->ConfigDomain.'\\backup';
			$current_config['aspell_path'] = 'C:\\Program Files\\Aspell\\bin\\aspell.exe';
		}
		// only set aspell path, if it's installed
		if (!is_executable($current_config['aspell_path']))
		{
			unset($current_config['aspell_path']);
		}
		// always enable browser based spellchecker
		$current_config['enabled_spellcheck'] = 'YesBrowserBased';

		// always enable history logging for calendar, addressbook and infolog
		$current_config['history'] = 'history';	// addressbook: only admin
		$current_config['calendar_delete_history'] = 'history';	// only admins
		// infolog does NOT use config_app='phpgwapi', but 'infolog'
		$GLOBALS['egw_setup']->db->insert($GLOBALS['egw_setup']->config_table,array(
			'config_value' => 'history_admin_delete',	// only admins
		),array(
			'config_app' => 'infolog',
			'config_name' => 'history',
		),__FILE__,__LINE__);


		// RalfBecker: php.net recommend this for security reasons, it should be our default too
		$current_config['usecookies'] = 'True';

		if ($GLOBALS['egw_setup']->system_charset)
		{
			$current_config['system_charset'] = $GLOBALS['egw_setup']->system_charset;
		}
		// storing default timezone as server timezone
		try
		{
			$tz = new DateTimeZone(date_default_timezone_get());
			$current_config['server_timezone'] = $tz->getName();
		}
		catch(Exception $e)
		{
			unset($e);
			// do nothing if new DateTimeZone fails (eg. 'System/Localtime' returned), specially do NOT store it!
			error_log(__METHOD__."() NO valid 'date.timezone' set in your php.ini!");
		}
		$current_config['install_id'] = md5($_SERVER['HTTP_HOST'].microtime(true).$GLOBALS['egw_setup']->ConfigDomain);

		$current_config['postpone_statistics_submit'] = time() + 2 * 30 * 86400;	// ask user in 2 month from now, when he has something to report

		// use securest password hash by default
		require_once EGW_SERVER_ROOT.'/setup/inc/hook_config.inc.php';	// for sql_passwdhashes, to get securest available password hash
		$securest = null;
		sql_passwdhashes(array(), true, $securest);
		$current_config['sql_encryption_type'] = $current_config['ldap_encryption_type'] = $securest;

		if ($preset_config)
		{
			$current_config = array_merge($current_config,$preset_config);
		}

		foreach($current_config as $name => $value)
		{
			$app = 'phpgwapi';
			if ($name == 'postpone_statistics_submit')
			{
				$app = 'admin';
			}
			elseif(strpos($name, '/') !== false)
			{
				list($app, $name) = explode('/', $name);
			}
			$GLOBALS['egw_setup']->db->insert($GLOBALS['egw_setup']->config_table,array(
				'config_value' => $value,
			),array(
				'config_app' => $app,
				'config_name' => $name,
			),__FILE__,__LINE__);
		}

		// so the default_records use the current data
		$GLOBALS['egw_info']['server'] = array_merge((array)$GLOBALS['egw_info']['server'], $current_config);
		Api\Cache::flush();	// flush whole instance cache
		Api\Config::init_static();	// flush internal cache of Api\Config class
		$GLOBALS['egw_setup']->setup_account_object($current_config);
	}

	/**
	 * drop tables per application, check that they are in the db first
	 *
	 * @param $setup_info	array of application info from setup.inc.php files, etc.
	 */
	function droptables(array $setup_info,$DEBUG=False)
	{
		if(!$this->oProc)
		{
			$this->init_process();
		}
		/* The following is built so below we won't try to drop a table that isn't there. */
		$tables = $this->db->table_names(true);
		$views = $this->db->Link_ID->MetaTables('VIEWS');
		if (!is_array($setup_info) || !$tables && !$views)
		{
			return $setup_info;	// nothing to do
		}

		foreach($setup_info as $app_name => $data)
		{
			// drop views first
			foreach($data['views'] ?? [] as $view)
			{
				if (in_array($view, $views))
				{
					if($DEBUG){ echo '<br>process->droptables(): Dropping :'. $app_name . ' view: ' . $view; }
					$this->db->query("DROP VIEW $view", __LINE__, __FILE__);
				}
			}
			// drop them in reverse order, in case the have constrains
			foreach(array_reverse((array)($data['tables'] ?? [])) as $table)
			{
				//echo $table;
				if (in_array($table, $tables))
				{
					if($DEBUG){ echo '<br>process->droptables(): Dropping :'. $app_name . ' table: ' . $table; }
					$this->oProc->DropTable($table);
					// Update the array values for return below
					$setup_info[$app_name]['status'] = 'U';
				}
			}
		}

		/* Done, return current status */
		return $setup_info;
	}

	/**
	 * process current table setup in each application/setup dir
	 *
	 * @param array $setup_info	array of application info from setup.inc.php files, etc.
	 * @param boolean $DEBUG =false output further diagnostics
	 * @return array $setup_info
	 */
	function current(array $setup_info,$DEBUG=False)
	{
		//echo __METHOD__; _debug_array($setup_info);
		if(!isset($this->oProc))
		{
			$this->init_process();
		}
		foreach($setup_info as $appname => &$appdata)
		{
			$enabled = False;
			$apptitle = $appdata['title'];

			if($DEBUG) { echo '<br>process->current(): Incoming status: ' . $appname . ',status: '. $appdata['status']; }

			$appdir  = EGW_SERVER_ROOT . '/' . $appname . '/setup/';

			if ($appdata['tables'] && file_exists($appdir.'tables_current.inc.php') && empty($appdata['skip_create_tables']))
			{
				if($DEBUG) { echo '<br>process->current(): Including: ' . $appdir.'tables_current.inc.php'; }
				$phpgw_baseline = null;
				include ($appdir.'tables_current.inc.php');
				$ret = $this->post_process($phpgw_baseline,$DEBUG);
				if($ret)
				{
					if($GLOBALS['egw_setup']->app_registered($appname))
					{
						$GLOBALS['egw_setup']->update_app($appname);
					}
					else
					{
						$GLOBALS['egw_setup']->register_app($appname);
						$GLOBALS['egw_setup']->set_default_preferences($appname);
					}
					// Update the array values for return below
					$appdata['status'] = 'C';
				}
				else
				{
					/* script processing failed */
					if($DEBUG) { echo '<br>process->current(): Failed for ' . $appname . ',status: '. $appdata['status']; }
					$appdata['status'] = 'F';
				}
			}
			else
			{
				if($DEBUG) { echo '<br>process->current(): No current tables for ' . $apptitle . "\n"; }
				/*
				 Add the app, but disable it if it has tables defined.
				 A manual sql script install is needed, but we do add the hooks
				*/
				$enabled = 99;
				if (!empty($appdata['tables']) && empty($appdata['skip_create_tables']))
				{
					$enabled = False;
				}
				if($GLOBALS['egw_setup']->app_registered($appname))
				{
					$GLOBALS['egw_setup']->update_app($appname);
				}
				else
				{
					$GLOBALS['egw_setup']->register_app($appname,$enabled);
					$GLOBALS['egw_setup']->set_default_preferences($appname);
				}
				$appdata['status'] = 'C';
			}
			if($DEBUG) { echo '<br>process->current(): Outgoing status: ' . $appname . ',status: '. $appdata['status']; }
		}

		// update hooks
		Api\Hooks::read(true);

		/* Done, return current status */
		return $setup_info;
	}

	/**
	 * process default_records.inc.php in each application/setup dir
	 *
	 * @param array $setup_info	array of application info from setup.inc.php files, etc.
	 * @param boolean $DEBUG =false output further diagnostics
	 * @return array $setup_info
	 */
	function default_records(array $setup_info,$DEBUG=False)
	{
		//echo __METHOD__; _debug_array($setup_info);
		if(!$this->oProc)
		{
			$this->init_process();
		}
		foreach($setup_info as $appname => &$appdata)
		{
			$appdir  = EGW_SERVER_ROOT . '/' . $appname . '/setup/';

			if(file_exists($appdir.'default_records.inc.php'))
			{
				if($DEBUG)
				{
					echo '<br>process->default_records(): Including default records for ' . $appname . "\n";
				}
				$oProc = &$this->oProc;	// to be compatible with old apps
				include ($appdir.'default_records.inc.php');
			}
			/* $appdata['status'] = 'C'; */
		}
		unset($appdata, $oProc);

		// Clear categories cache in case app adds categories
		Api\Categories::invalidate_cache();

		/* Done, return current status */
		return ($setup_info);
	}

	/**
	 * process test_data.inc.php in each application/setup dir for developer tests
	 *
	 * This data should work with the baseline tables
	 *
	 * @param array $setup_info	array of application info from setup.inc.php files, etc.
	 * @param boolean $DEBUG =false output further diagnostics
	 * @return array $setup_info
	 */
	function test_data(array $setup_info,$DEBUG=False)
	{
		if(!$this->oProc)
		{
			$this->init_process();
		}
		foreach($setup_info as $appname => &$appdata)
		{
			$appdir  = EGW_SERVER_ROOT . '/' . $appname . '/setup/';

			if(file_exists($appdir.'test_data.inc.php'))
			{
				if($DEBUG)
				{
					echo '<br>process->test_data(): Including baseline test data for ' . $appname . "\n";
				}
				$this->oProc->m_odb->transaction_begin();
				include ($appdir.'test_data.inc.php');
				$this->oProc->m_odb->transaction_commit();
			}
		}
		unset($appdata);

		/* Done, return current status */
		return ($setup_info);
	}

	/**
	 * process baseline table setup in each application/setup dir
	 *
	 * @param array $setup_info	array of application info from setup.inc.php files, etc.
	 * @param boolean $DEBUG =false output further diagnostics
	 * @return array $setup_info
	 */
	function baseline(array $setup_info,$DEBUG=False)
	{
		if(!$this->oProc)
		{
			$this->init_process();
		}
		foreach($setup_info as $appname => &$appdata)
		{
			$appdir  = EGW_SERVER_ROOT . '/' . $appname . '/setup/';

			if(file_exists($appdir.'tables_baseline.inc.php'))
			{
				if($DEBUG)
				{
					echo '<br>process->baseline(): Including baseline tables for ' . $appname . "\n";
				}
				$phpgw_baseline = null;
				include ($appdir.'tables_baseline.inc.php');
				$this->oProc->GenerateScripts($phpgw_baseline, $DEBUG);
				$this->post_process($phpgw_baseline,$DEBUG);

				/* Update the array values for return below */
				/* $setup_info[$key]['status'] = 'R'; */
			}
			else
			{
				if($DEBUG)
				{
					echo '<br>process->baseline(): No baseline tables for ' . $appname . "\n";
				}
				//$setup_info[$key]['status'] = 'C';
			}
		}
		unset($appdata);

		/* Done, return current status */
		return ($setup_info);
	}

	/**
	 * process available upgrades in each application/setup dir
	 *
	 * @param array $setup_info array of application info from setup.inc.php files, etc.
	 * @param boolean $DEBUG =false output further diagnostics
	 * @return array $setup_info
	 */
	function upgrade($setup_info,$DEBUG=False)
	{
		//echo __METHOD__; _debug_array($setup_info);
		if(!$this->oProc)
		{
			$this->init_process();
		}

		foreach($setup_info as $appname => &$appdata)
		{
			// check if app is NOT installed
			if (!($registered = $GLOBALS['egw_setup']->app_registered($appname)))
			{
				// check if app wants to be automatically installed on update to version x or always (unless uninstalled prior)
				if (isset($appdata['autoinstall']) && ($appdata['autoinstall'] === true && $registered !== null ||
					$appdata['autoinstall'] === $this->api_version_target))
				{
					$info_c = $this->current(array($appname => $appdata), $DEBUG);
					$info = $this->default_records($info_c, $DEBUG);
					$appdata = $info[$appname];
					continue;
				}
				/* Don't try to upgrade an app that is not installed */
				if($DEBUG)
				{
					echo "<p>process->upgrade(): Application not installed: $appname</p>\n";
				}
				unset($setup_info[$appname]);
				continue;
			}

			/* if upgrade required, or if we are running again after an upgrade or dependency failure */
			if($DEBUG)
			{
				echo '<div style="text-align: left; border: thin dashed black; margin-top: 5px;">'."process->upgrade(): Incoming : appname: $appname, version: $appdata[currentver], status: $appdata[status]\n";
			}
			if($appdata['status'] == 'U' || $appdata['status'] == 'D' ||$appdata['status'] == 'V' || $appdata['status'] == '') // TODO this is not getting set for api upgrade, sometimes ???
			{
				$currentver = $appdata['currentver'];
				$targetver  = $appdata['version'];	// The version we need to match when done
				$appdir     = EGW_SERVER_ROOT . '/' . $appname . '/setup/';

				if(file_exists($appdir . 'tables_update.inc.php'))
				{
					if (!@$this->updateincluded[$appname])
					{
						include ($appdir . 'tables_update.inc.php');
						$this->updateincluded[$appname] = True;
					}
					while ($currentver && $currentver != $targetver &&
						function_exists($function = $appname . '_upgrade' . str_replace('.','_',$currentver)))
					{
						if($DEBUG)
						{
							echo "<br>process->upgrade(): $appname($currentver --> $targetver): running $function()\n";
						}
						if (!($currentver = $function()))
						{
							if($DEBUG)
							{
								echo "<b>failed!!!</b>\n";
							}
							$appstatus  = 'F';
						}
						else
						{
							if($DEBUG)
							{
								echo "--> $currentver\n";
							}
						}
					}
					if ($currentver == $targetver)	// upgrades successful
					{
						if($DEBUG)
						{
							echo "<br>process->upgrade(): Upgrade of $appname to $targetver is completed.\n";
						}
						$appstatus = 'C';
					}
					elseif ($currentver)
					{
						if($DEBUG)
						{
							echo "<br><b>process->upgrade(): No table upgrade available for appname: $appname, version: $currentver</b>\n";
						}
						$appdate['currentver'] = $targetver;
						$appstatus  = 'F';
					}
				}
				else
				{
					if($DEBUG)
					{
						echo "<br>process->upgrade(): No table upgrade required/availible for $appname\n";
					}
					$appstatus  = 'C';
				}
				if ($appstatus == 'C')	// update successful completed
				{
					$appdata['currentver'] = $targetver;

					if($GLOBALS['egw_setup']->app_registered($appname))
					{
						$GLOBALS['egw_setup']->update_app($appname);
					}
					else
					{
						$GLOBALS['egw_setup']->register_app($appname);
					}
				}

			}
			else
			{
				if($DEBUG)
				{
					echo "<br>process->upgrade(): No upgrade required for $appname\n";
				}
				$appstatus  = 'C';
			}
			/* Done with this app, update status */
			if($DEBUG)
			{
				echo "<br>process->upgrade(): Outgoing : appname: $appname, status: $appstatus</div>\n";
			}
			$appdate['status'] = $appstatus;
		}

		// update hooks
		Api\Hooks::read(true);

		/* Done, return current status */
		return $setup_info;
	}

	/**
	 * commit above processing to the db
	 *
	 */
	function post_process($tables,$DEBUG=False)
	{
		if(!$tables)
		{
			return False;
		}
		return $this->oProc->ExecuteScripts($tables,$DEBUG);
	}

	/**
	 * send this a table name, returns printable column spec and keys for the table from schema_proc
	 *
	 * @param	$tablename	table whose array you want to see
	 */
	function sql_to_array($tablename='')
	{
		if(!$tablename)
		{
			return False;
		}

		if(!$this->oProc)
		{
			$this->init_process();
		}

		$sColumns = null;
		$this->oProc->m_oTranslator->_GetColumns($this->oProc, $tablename, $sColumns);

		foreach($this->oProc->m_oTranslator->sCol as $tbldata)
		{
			$arr .= $tbldata;
		}
		$pk = $this->oProc->m_oTranslator->pk;
		$fk = $this->oProc->m_oTranslator->fk;
		$ix = $this->oProc->m_oTranslator->ix;
		$uc = $this->oProc->m_oTranslator->uc;

		return array($arr,$pk,$fk,$ix,$uc);
	}

	/**
	 * Deinstall given apps
	 *
	 * @param array $apps name of apps to deinstall
	 * @param array $setup_info
	 * @param bool $DEBUG =false
	 * @return int
	 */
	function remove(array $apps, array $setup_info, $DEBUG=false)
	{
		$historylog = new Api\Storage\History();
		$historylog->db = $this->db = $GLOBALS['egw_setup']->db;

		foreach($apps as $appname)
		{
			$app_title = $setup_info[$appname]['title'] ? $setup_info[$appname]['title'] : $setup_info[$appname]['name'];
			$terror = array();
			$terror[$appname] = $setup_info[$appname];

			if ($setup_info[$appname]['tables'])
			{
				$this->droptables($terror,$DEBUG);
				echo '<br />' . $app_title . ' ' . lang('tables dropped') . '.';
			}

			$GLOBALS['egw_setup']->deregister_app($setup_info[$appname]['name']);
			echo '<br />' . $app_title . ' ' . lang('deregistered') . '.';

			$historylog->appname = $appname;
			if ($historylog->delete(null))
			{
				echo '<br />' . $app_title . ' ' . lang('Historylog removed') . '.';
			}

			// delete all application categories and ACL
			$this->db->delete($GLOBALS['egw_setup']->cats_table,array('cat_appname' => $appname),__LINE__,__FILE__);
			$this->db->delete($GLOBALS['egw_setup']->acl_table,array('acl_appname' => $appname),__LINE__,__FILE__);
		}
		return count($apps);
	}
}