<?php
  /**************************************************************************\
  * phpGroupWare - Setup                                                     *
  * http://www.phpgroupware.org                                              *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

	/* app status values:
	U	Upgrade required/available
	R	upgrade in pRogress
	C	upgrade Completed successfully
	D	Dependency failure
	F	upgrade Failed
	V	Version mismatch at end of upgrade (Not used, proposed only)
	M	Missing files at start of upgrade (Not used, proposed only)
	*/

	class phpgw_setup_process extends phpgw_setup_detection
	{
		var $db;
		var $oProc;
		var $tables;
		var $updateincluded = array();

		/*!
		@function init_process
		@abstract create schema_proc object
		@param none
		*/
		function init_process()
		{
			$ConfigDomain = $GLOBALS['HTTP_COOKIE_VARS']['ConfigDomain'] ? $GLOBALS['HTTP_COOKIE_VARS']['ConfigDomain'] : $GLOBALS['HTTP_POST_VARS']['ConfigDomain'];
			$phpgw_domain = $GLOBALS['phpgw_domain'];
			$phpgw_info   = $GLOBALS['phpgw_info'];

			$this->oProc = CreateObject('setup.schema_proc',$phpgw_domain[$ConfigDomain]['db_type']);
			//$this->oProc = CreateObject('setup.schema_proc','pgsql');
			$this->oProc->m_odb           = $this->db;
			$this->oProc->m_odb->Host     = $phpgw_domain[$ConfigDomain]['db_host'];
			$this->oProc->m_odb->Database = $phpgw_domain[$ConfigDomain]['db_name'];
			$this->oProc->m_odb->User     = $phpgw_domain[$ConfigDomain]['db_user'];
			$this->oProc->m_odb->Password = $phpgw_domain[$ConfigDomain]['db_pass'];
			$this->oProc->m_odb->Halt_On_Error = 'report';
			$this->oProc->m_odb->connect();
		}

		/*!
		@function process_pass
		@abstract the mother of all multipass upgrade parental loop functions
		@param $setup_info	array of application info from setup.inc.php files
		@param $type		optional, defaults to new(install), could also be 'upgrade'
		@param $DEBUG		optional, print debugging info
		@param $force_en	optional, install english language files
		*/
		function process_pass($setup_info,$method='new',$DEBUG=False,$force_en=False)
		{
			if (!$method)
			{
				return False;
			}
			// Place api first
			$pass['phpgwapi'] = $setup_info['phpgwapi'];
			@reset($setup_info);
			$setup_info = $this->get_versions($setup_info);
			@reset($setup_info);

			$i = 1;
			$passed = array();
			$passing = array();
			$pass_string = implode (':', $pass);
			$passing_string = implode (':', $passing);
			while ($pass_string != $passing_string)
			{
				$passing = array();
				if ($DEBUG) { echo '<br>process_pass(): #' . $i . ' for ' . $method . ' processing' . "\n"; }
				/* Check current versions and dependencies */
				$setup_info = $this->get_db_versions($setup_info);
				$setup_info = $this->compare_versions($setup_info);
				//_debug_array($setup_info);exit;
				$setup_info = $this->check_depends($setup_info);
				//if($i==2) { _debug_array($passed);exit; }

				/* stuff the rest of the apps, but only those with available upgrades */
				while(list($key,$value) = @each($setup_info))
				{
					if (($value['name'] != 'phpgwapi') && ($value['status'] == 'U'))
					{
						if ( ($passed[$value['name']]['status'] != 'F') && ($passed[$value['name']]['status'] != 'C') )
						{
							$pass[$value['name']] = $setup_info[$value['name']];
						}
					}
					/*
					Now if we are on the 2nd or more passes, add api in
					if (!$pass['phpgwapi'])
					{
						$pass['phpgwapi'] = $setup_info['phpgwapi'];
					}
					*/
				}

				switch ($method)
				{
					case 'new':
						/* Create tables and insert new records for each app in this list */
						$passing = $this->process_current($pass,$DEBUG);
						$passing = $this->process_default_records($passing,$DEBUG);
						$passing = $this->process_add_langs($passing,$DEBUG,$force_en);
						break;
					case 'upgrade':
						/* Run upgrade scripts on each app in the list */
						$passing = $this->process_upgrade($pass,$DEBUG);
						$passing = $this->process_upgrade_langs($passing,$DEBUG);
						//_debug_array($pass);exit;
						break;
					default:
						/* What the heck are you doing? */
						return False;
						break;
				}

				$pass = array();
				@reset($passing);
				while(list($key,$value) = @each($passing))
				{
					if($value['status'] == 'C')
					{
						$passed[$value['name']] = $passing[$value['name']];
						if ($DEBUG) { echo '<br>process_pass(): '.$passed[$value['name']]['name'] . ' install completed'."\n"; }
					}
					elseif($value['status'] == 'F')
					{
						$setup_info[$value['name']] = $passing[$value['name']];
						if ($DEBUG) { echo '<br>process_pass(): '.$setup_info[$value['name']]['name'] . ' install failed'."\n"; }
					}
					elseif($value['status'] == 'D')
					{
						$pass[$value['name']] = $setup_info[$value['name']];
						if ($DEBUG) { echo '<br>process_pass(): '.$pass[$value['name']]['name'] . ' fails dependency check on this pass'."\n"; }
					}
					else
					{
						$tmp = $passing[$value['name']]['name'];
						if ($DEBUG) { echo '<br>process_pass(): '.$tmp . ' skipped on this pass'."\n"; }
					}
				}

				$i++;
				if ($i == 20) // Then oops it broke
				{
					echo '<br>Setup failure: excess looping in process_pass():'."\n";
					echo '<br>Pass:<br>'."\n";
					_debug_array($pass);
					echo '<br>Passed:<br>'."\n";
					_debug_array($passed);
					exit;
				}
				$pass_string = implode (':', $pass);
				$passing_string = implode (':', $passing);
			}

			// now return the list
			@reset($passed);
			while(list($key,$value) = @each($passed))
			{
				$setup_info[$value['name']] = $passed[$value['name']];
			}

			return ($setup_info);
		}

		/*!
		@function process_droptables
		@abstract drop tables per application, check that they are in the db first
		@param $setup_info	array of application info from setup.inc.php files, etc.
		*/
		function process_droptables($setup_info,$DEBUG=False)
		{
			if (!$this->oProc)
			{
				$this->init_process();
			}
			$this->oProc->m_bDeltaOnly = False;

			// The following is built so below we won't try to drop a table that isn't there
			$tablenames = $this->db->table_names();
			while(list($key,$val) = @each($tablenames))
			{
				$tables[] = $val['table_name'];
			}

			@reset($setup_info);
			while (list($key,$null) = @each($setup_info))
			{
				if ($setup_info[$key]['tables'])
				{
					while (list($a,$table) = @each($setup_info[$key]['tables']))
					{
						//echo $table;
						if ($this->isinarray($table,$tables))
						{
							if ($DEBUG){ echo '<br>process_droptables(): Dropping :'. $setup_info[$key]['name'] . ' table: ' . $table; }
							$this->oProc->DropTable($table);
							// Update the array values for return below
							$setup_info[$key]['status'] = 'U';
						}
					}
				}
			}

			// Done, return current status
			return ($setup_info);
		}

		// NOTE: This duplicates the old newtables behavior, using schema_proc
		/*!
		@function process_current
		@abstract process current table setup in each application/setup dir
		@param $appinfo	array of application info from setup.inc.php files, etc.
		*/
		function process_current($setup_info,$DEBUG=False)
		{
			if (!$this->oProc)
			{
				$this->init_process();
			}
			$this->oProc->m_bDeltaOnly = False;

			@reset($setup_info);
			while (list($key,$null) = @each($setup_info))
			{
				$enabled = False;
				$appname  = $setup_info[$key]['name'];
				$apptitle = $setup_info[$key]['title'];

				if($DEBUG) { echo '<br>process_current(): Incoming status: ' . $appname . ',status: '. $setup_info[$key]['status']; }

				$appdir  = PHPGW_SERVER_ROOT . SEP . $appname . SEP . 'setup' . SEP;

				if ($setup_info[$key]['tables'] && file_exists($appdir.'tables_current.inc.php'))
				{
					if($DEBUG) { echo '<br>process_current(): Including: ' . $appdir.'tables_current.inc.php'; }
					include ($appdir.'tables_current.inc.php');
					$ret = $this->post_process($phpgw_baseline,$DEBUG);
					if($ret)
					{
						if ($this->app_registered($appname))
						{
							$this->update_app($appname);
							$this->update_hooks($appname);
						}
						else
						{
							$this->register_app($appname);
							$this->register_hooks($appname);
						}
						// Update the array values for return below
						$setup_info[$key]['status'] = 'C';
					}
					else
					{
						// script processing failed
						if($DEBUG) { echo '<br>process_current(): Failed for ' . $appname . ',status: '. $setup_info[$key]['status']; }
						$setup_info[$key]['status'] = 'F';
					}
				}
				else
				{
					if($DEBUG) { echo '<br>process_current(): No current tables for ' . $apptitle . "\n"; }
					// add the app, but disable it if it has tables defined
					// a manual sql script install is needed, but we do add the hooks
					$enabled = 99;
					if ($setup_info[$key]['tables'][0] != '')
					{
						$enabled = False;
					}
					if ($this->app_registered($appname))
					{
						$this->update_app($appname);
						$this->update_hooks($appname);
					}
					else
					{
						$this->register_app($appname,$enabled);
						$this->register_hooks($appname);
					}
					$setup_info[$key]['status'] = 'C';
				}
				if($DEBUG) { echo '<br>process_current(): Outgoing status: ' . $appname . ',status: '. $setup_info[$key]['status']; }
			}

			// Done, return current status
			return ($setup_info);
		}

		/*!
		@function process_default_records
		@abstract process default_records.inc.php in each application/setup dir
		@param $setup_info	array of application info from setup.inc.php files, etc.
		*/
		function process_default_records($setup_info,$DEBUG=False)
		{
			if (!$this->oProc)
			{
				$this->init_process();
			}
			$this->oProc->m_bDeltaOnly = False;
			$oProc = $this->oProc;

			@reset($setup_info);
			while (list($key,$null) = @each($setup_info))
			{
				$appname = $setup_info[$key]['name'];
				$appdir  = PHPGW_SERVER_ROOT . SEP . $appname . SEP . 'setup' . SEP;

				if ($setup_info[$key]['tables'] && file_exists($appdir.'default_records.inc.php'))
				{
					if ($DEBUG)
					{
						echo '<br>process_default_records(): Including default records for ' . $appname . "\n";
					}
					$this->oProc->m_odb->transaction_begin();
					include ($appdir.'default_records.inc.php');
					$this->oProc->m_odb->transaction_commit();
				}
				//$setup_info[$key]['status'] = 'C';
			}

			// Done, return current status
			return ($setup_info);
		}

		/*!
		@function process_add_langs
		@abstract process application lang files and uninstall
		@param $setup_info	array of application info from setup.inc.php files, etc.
		*/
		function process_add_langs($setup_info,$DEBUG=False,$force_en=False)
		{
			@reset($setup_info);
			while (list($key,$null) = @each($setup_info))
			{
				$appname = $setup_info[$key]['name'];
				/* This is in the setup_lang class */
				$this->add_langs($appname,$DEBUG,$force_en);
				if($DEBUG)
				{
					echo '<br>process_add_langs(): Translations added for ' . $appname . "\n";
				}
			}
			// Done, return current status
			return ($setup_info);
		}

		/*!
		@function process_drop_langs
		@abstract process application lang files and install
		@param $setup_info	array of application info from setup.inc.php files, etc.
		*/
		function process_drop_langs($setup_info,$DEBUG=False)
		{
			@reset($setup_info);
			while (list($key,$null) = @each($setup_info))
			{
				$appname = $setup_info[$key]['name'];
				/* This is in the setup_lang class */
				$this->drop_langs($appname,$DEBUG);
				if($DEBUG)
				{
					echo '<br>process_drop_langs():  Translations removed for ' . $appname . "\n";
				}
			}
			// Done, return current status
			return ($setup_info);
		}

		/*!
		@function process_upgrade_langs
		@abstract process application lang files and reinstall
		@param $setup_info	array of application info from setup.inc.php files, etc.
		*/
		function process_upgrade_langs($setup_info,$DEBUG=False)
		{
			@reset($setup_info);
			while (list($key,$null) = @each($setup_info))
			{
				/* Don't upgrade lang files in the middle of an upgrade */
				if($setup_info[$key]['status'] == 'R')
				{
					continue;
				}
				$appname = $setup_info[$key]['name'];
				/* These are in the setup_lang class */
				$this->drop_langs($appname,$DEBUG);
				$this->add_langs($appname,$DEBUG);
				if($DEBUG)
				{
					echo '<br>process_upgrade_langs(): Translations reinstalled for ' . $appname . "\n";
				}
			}
			// Done, return current status
			return ($setup_info);
		}
		/*!
		@function process_test_data
		@abstract process test_data.inc.php in each application/setup dir for developer tests
		This data should work with the baseline tables
		@param $setup_info	array of application info from setup.inc.php files, etc.
		*/
		function process_test_data($setup_info,$DEBUG=False)
		{
			if (!$this->oProc)
			{
				$this->init_process();
			}
			$this->oProc->m_bDeltaOnly = False;
			$oProc = $this->oProc;

			@reset($setup_info);
			while (list($key,$null) = @each($setup_info))
			{
				$appname = $setup_info[$key]['name'];
				$appdir  = PHPGW_SERVER_ROOT . SEP . $appname . SEP . 'setup' . SEP;

				if (file_exists($appdir.'test_data.inc.php'))
				{
					if ($DEBUG)
					{
						echo '<br>process_test_data(): Including baseline test data for ' . $appname . "\n";
					}
					$this->oProc->m_odb->transaction_begin();
					include ($appdir.'test_data.inc.php');
					$this->oProc->m_odb->transaction_commit();
				}
			}

			// Done, return current status
			return ($setup_info);
		}

		/*!
		@function process_baseline
		@abstract process baseline table setup in each application/setup dir
		@param $appinfo	array of application info from setup.inc.php files, etc.
		*/
		function process_baseline($setup_info,$DEBUG=False)
		{
			if (!$this->oProc)
			{
				$this->init_process();
			}

			@reset($setup_info);
			while (list($key,$null) = @each($setup_info))
			{
				$appname = $setup_info[$key]['name'];
				$appdir  = PHPGW_SERVER_ROOT . SEP . $appname . SEP . 'setup' . SEP;

				if (file_exists($appdir.'tables_baseline.inc.php'))
				{
					if ($DEBUG)
					{
						echo '<br>process_baseline(): Including baseline tables for ' . $appname . "\n";
					}
					include ($appdir.'tables_baseline.inc.php');
					$this->oProc->GenerateScripts($phpgw_baseline, $DEBUG);
					$this->post_process($phpgw_baseline,$DEBUG);

					// Update the array values for return below
					//$setup_info[$key]['status'] = 'R';
				}
				else
				{
					if ($DEBUG)
					{
						echo '<br>process_baseline(): No baseline tables for ' . $appname . "\n";
					}
					//$setup_info[$key]['status'] = 'C';
				}
			}

			// Done, return current status
			return ($setup_info);
		}

		/*!
		@function process_upgrade
		@abstract process available upgrades in each application/setup dir
		@param $appinfo	array of application info from setup.inc.php files, etc.
		*/
		function process_upgrade($setup_info,$DEBUG=False)
		{
			if (!@$this->oProc)
			{
				$this->init_process();
			}
			$this->oProc->m_odb->HaltOnError = 'no';
			$this->oProc->m_bDeltaOnly = True;

			@reset($setup_info);
			while (list($key,$null) = @each($setup_info))
			{
				/* Don't try to upgrade an app that is not installed */
				if(!$this->app_registered($setup_info[$key]['name']))
				{
					if ($DEBUG)
					{
						echo '<br>process_upgrade(): Application not installed: ' . $appname . "\n";
					}
					continue;
				}

				/* if upgrade required, or if we are running again after an upgrade or dependency failure */
				if ($DEBUG) { echo '<br>process_upgrade(): Incoming : appname: '.$setup_info[$key]['name'] . ' status: ' . $setup_info[$key]['status']; }
				if ($setup_info[$key]['status'] == 'U' ||
					$setup_info[$key]['status'] == 'D' ||
					$setup_info[$key]['status'] == 'V' ||
					$setup_info[$key]['status'] == '') // TODO this is not getting set for api upgrade, sometimes ???
				{
					$appname    = $setup_info[$key]['name'];
					$apptitle   = $setup_info[$key]['title'];
					$currentver = $setup_info[$key]['currentver'];
					$targetver  = $setup_info[$key]['version'];	// The version we need to match when done
					$appdir     = PHPGW_SERVER_ROOT . SEP . $appname . SEP . 'setup' . SEP;

					$test   = array();
					$this->oProc->m_aTables = $phpgw_baseline = array();
/*
					$phpgw_baseline = array();

					$tmpapp = array();
					$tmpapp[] = $setup_info[$key];
					$this->process_baseline($tmpapp,$DEBUG);
					$this->oProc->m_aTables = $phpgw_baseline;
					// So far, including the baseline file is not helping.
					// Only AlterColumn/RenameColumn seem to be failing silently.
					// This is because we are not keeping up with table changes, so a table in baseline
					// either does not exist anymore, or the baseline is being lost.
*/
					if ($setup_info[$key]['tables'] && file_exists($appdir.'tables_baseline.inc.php'))
					{
						if ($DEBUG)
						{
							echo '<br>process_baseline(): Including baseline tables for ' . $appname . "\n";
						}
						include ($appdir.'tables_baseline.inc.php');
						$this->oProc->m_aTables = $phpgw_baseline;
						//$this->oProc->GenerateScripts($phpgw_baseline, $DEBUG);
					}
					else
					{
						if ($DEBUG)
						{
							echo '<br>process_baseline(): No baseline tables for ' . $appname . "\n";
						}
						/* This should be a break with a status setting, or not at all
						break;
						*/
					}
					if (file_exists($appdir . 'tables_update.inc.php') && !@$this->updateincluded[$appname])
					{
						include ($appdir . 'tables_update.inc.php');
						$this->updateincluded[$appname] = True;

						// $test array comes from update file, it is a list of available upgrade functions
						@reset($test);
						while (list($x,$value) = @each($test))
						{
							$currentver = $setup_info[$key]['currentver'];

							/* build upgrade function name */
							$function = $appname . '_upgrade' . ereg_replace("\.", '_', $value);

							if ($DEBUG)
							{
								echo '<br>process_upgrade(): appname:    ' . $appname;
								echo '<br>process_upgrade(): currentver: ' . $currentver;
								echo '<br>process_upgrade(): targetver:  ' . $targetver;
								echo '<br>process_upgrade(): status:     ' . $setup_info[$key]['status'];
								echo '<br>process_upgrade(): checking:   ' . $value;
								echo '<br>process_upgrade(): function:   ' . $function;
							}

							if ($value == $targetver)
							{
								$this->oProc->m_bDeltaOnly = False;
								// Done upgrading
								if($DEBUG)
								{
									echo '<br>process_upgrade(): Upgrade of ' . $appname . ' to ' . $targetver . ' is completed.' . "\n";
								}
								$appstatus = 'C';
								$setup_info[$key]['status']     = $appstatus;
								$setup_info[$key]['currentver'] = $targetver;
								if ($this->app_registered($appname))
								{
									$this->update_app($appname);
									$this->update_hooks($appname);
								}
								else
								{
									$this->register_app($appname);
									$this->register_hooks($appname);
								}
								//break;
							}
							elseif (($value == $currentver) || !$currentver)
							//elseif ($this->alessthanb($value,$targetver,True) &&
							//		$this->alessthanb($currentver,$value,True))
							{
								// start upgrading db in addition to baseline
								$this->oProc->m_bDeltaOnly = False;
								if ($DEBUG) { echo '<br>process_upgrade(): running ' . $function; }
								// run upgrade function
								$success = $function();
								if ($success != False)
								{
									$setup_info[$key]['currentver'] = $success;
									if ($DEBUG)
									{
										echo '<br>process_upgrade(): Upgrade of ' . $appname
											. ' from ' . $value
											. ' to ' . $setup_info[$key]['currentver']
											. ' is completed.' . "\n";
									}
									$appstatus = 'R';
									$setup_info[$key]['status'] = $appstatus;
									if ($this->app_registered($appname))
									{
										if($DEBUG)
										{
											echo '<br>process_upgrade(): Updating registration of ' . $appname . ', new version: ' . $setup_info[$key]['currentver'];
										}
										$this->update_app($appname);
										$this->update_hooks($appname);
									}
									else
									{
										if($DEBUG)
										{
											echo '<br>process_upgrade(): Registering ' . $appname . ', version: ' . $setup_info[$key]['currentver'];
										}
										$this->register_app($appname);
										$this->register_hooks($appname);
									}
								}
								else
								{
									if($DEBUG)
									{
										echo '<br>process_upgrade(): Upgrade of ' . $appname
											. ' from ' . $currentver
											. ' to ' . $value
											. ' failed!!!' . "\n";
									}
									$appstatus  = 'F';
									break;
								}
							}
							elseif ($this->alessthanb($value,$currentver))
							{
								if ($DEBUG) { echo '<br>process_upgrade(): running baseline delta only: ' . $function . '...'; }
								$this->oProc->m_bDeltaOnly = True;
								$success = $function();
							}
							else
							{
								break;
							}
						}
					}
					else
					{
						if ($setup_info[$appname]['tables'])
						{
							$appstatus  = 'F';

							if ($DEBUG)
							{
								echo '<br>process_upgrade(): No table upgrade available for ' . $appname . "\n";
							}
						}
						else
						{
							$setup_info[$key]['currentver'] == $targetver;
							$appstatus  = 'C';
							if ($this->app_registered($appname))
							{
								$this->update_app($appname);
								$this->update_hooks($appname);
							}
							else
							{
								$this->register_app($appname);
								$this->register_hooks($appname);
							}

							if ($DEBUG)
							{
								echo '<br>process_upgrade(): No table upgrade required for ' . $appname . "\n";
							}
						}
					}
				}
				else
				{
					$appstatus  = 'C';
					if ($DEBUG)
					{
						echo '<br>process_upgrade(): No upgrade required for ' . $appname . "\n";
					}
				}

				// Done with this app, update status
				$setup_info[$key]['status'] = $appstatus;
				if ($DEBUG)
				{
					echo '<br>process_upgrade(): Outgoing : appname: '.$setup_info[$key]['name'] . ' status: ' . $setup_info[$key]['status'];
				}
			}

			// Done, return current status

			return ($setup_info);
		}

		/*!
		@function post_process
		@abstract commit above processing to the db
		*/
		function post_process($tables,$DEBUG)
		{
			if (!$tables)
			{
				return False;
			}

			$ret = $this->oProc->GenerateScripts($tables,$DEBUG);
			if ($ret)
			{
				$oret = $this->oProc->ExecuteScripts($tables,$DEBUG);
				if ($oret)
				{
					return True;
				}
				else
				{
					return False;
				}
			}
			else
			{
				return False;
			}
		}

		/*!
		@function sql_to_array
		@abstract send this a table name, returns printable column spec and keys for the table from
			schema_proc
		@param	$tablename	table whose array you want to see
		*/
		function sql_to_array($tablename = '')
		{
			if (!$tablename)
			{
				return False;
			}

			if (!$this->oProc)
			{
				$this->init_process();
			}

			$this->oProc->m_oTranslator->_GetColumns($this->oProc, $tablename, $sColumns, $sColumnName);

			while (list($key,$tbldata) = each ($this->oProc->m_oTranslator->sCol))
			{
				$arr .= $tbldata;
			}
			$pk = $this->oProc->m_oTranslator->pk;
			$fk = $this->oProc->m_oTranslator->fk;
			$ix = $this->oProc->m_oTranslator->ix;
			$uc = $this->oProc->m_oTranslator->uc;

			return array($arr,$pk,$fk,$ix,$uc);
		}
	}
?>
