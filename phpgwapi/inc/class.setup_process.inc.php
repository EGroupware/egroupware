<?php
  /**************************************************************************\
  * phpGroupWare - Setup                                                     *
  * http://www.phpgroupware.org                                              *
  * --------------------------------------------                             *
  * This file written by Miles Lott<milosch@phpgroupware.org>                *
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

	class setup_process
	{
		var $oProc;
		var $tables;
		var $updateincluded = array();
		var $translation;

 		function setup_process()
		{
			$this->translation = CreateObject('phpgwapi.setup_translation');
		}

		/*!
		@function init_process
		@abstract create schema_proc object
		@param none
		*/
		function init_process()
		{
			$ConfigDomain = get_var('ConfigDomain',array('COOKIE','POST'));
			$phpgw_domain = $GLOBALS['phpgw_domain'];

			$GLOBALS['phpgw_setup']->oProc = CreateObject('phpgwapi.schema_proc',$phpgw_domain[$ConfigDomain]['db_type']);
			$GLOBALS['phpgw_setup']->oProc->m_odb           = $GLOBALS['phpgw_setup']->db;
			$GLOBALS['phpgw_setup']->oProc->m_odb->Host     = $phpgw_domain[$ConfigDomain]['db_host'];
			$GLOBALS['phpgw_setup']->oProc->m_odb->Database = $phpgw_domain[$ConfigDomain]['db_name'];
			$GLOBALS['phpgw_setup']->oProc->m_odb->User     = $phpgw_domain[$ConfigDomain]['db_user'];
			$GLOBALS['phpgw_setup']->oProc->m_odb->Password = $phpgw_domain[$ConfigDomain]['db_pass'];
			$GLOBALS['phpgw_setup']->oProc->m_odb->Halt_On_Error = 'report';
			$GLOBALS['phpgw_setup']->oProc->m_odb->connect();
		}

		/*!
		@function pass
		@abstract the mother of all multipass upgrade parental loop functions
		@param $setup_info	array of application info from setup.inc.php files
		@param $type		optional, defaults to new(install), could also be 'upgrade'
		@param $DEBUG		optional, print debugging info
		@param $force_en	optional, install english language files
		*/
		function pass($setup_info,$method='new',$DEBUG=False,$force_en=False)
		{
			if(!$method)
			{
				return False;
			}
			// Place api first
			$pass['phpgwapi'] = $setup_info['phpgwapi'];
			@reset($setup_info);
			$setup_info = $GLOBALS['phpgw_setup']->detection->get_versions($setup_info);
			@reset($setup_info);

			$i = 1;
			$passed = array();
			$passing = array();
			$pass_string = implode (':', $pass);
			$passing_string = implode (':', $passing);
			while($pass_string != $passing_string)
			{
				$passing = array();
				if($DEBUG) { echo '<br>process->pass(): #' . $i . ' for ' . $method . ' processing' . "\n"; }
				/* Check current versions and dependencies */
				$setup_info = $GLOBALS['phpgw_setup']->detection->get_db_versions($setup_info);
				$setup_info = $GLOBALS['phpgw_setup']->detection->compare_versions($setup_info);
				//_debug_array($setup_info);exit;
				$setup_info = $GLOBALS['phpgw_setup']->detection->check_depends($setup_info);
				//if($i==2) { _debug_array($passed);exit; }

				/* stuff the rest of the apps, but only those with available upgrades */
				while(list($key,$value) = @each($setup_info))
				{
					if(($value['name'] != 'phpgwapi') && ($value['status'] == 'U'))
					{
						if(($passed[$value['name']]['status'] != 'F') && ($passed[$value['name']]['status'] != 'C'))
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
						$passing = $this->current($pass,$DEBUG);
						$passing = $this->default_records($passing,$DEBUG);
						$passing = $this->add_langs($passing,$DEBUG,$force_en);
						break;
					case 'upgrade':
						/* Run upgrade scripts on each app in the list */
						$passing = $this->upgrade($pass,$DEBUG);
						$passing = $this->upgrade_langs($passing,$DEBUG);
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
				$pass_string = implode (':', $pass);
				$passing_string = implode (':', $passing);
			}

			/* now return the list */
			@reset($passed);
			while(list($key,$value) = @each($passed))
			{
				$setup_info[$value['name']] = $passed[$value['name']];
			}

			return ($setup_info);
		}

		/*!
		@function droptables
		@abstract drop tables per application, check that they are in the db first
		@param $setup_info	array of application info from setup.inc.php files, etc.
		*/
		function droptables($setup_info,$DEBUG=False)
		{
			if(!@$GLOBALS['phpgw_setup']->oProc)
			{
				$this->init_process();
			}
			$GLOBALS['phpgw_setup']->oProc->m_bDeltaOnly = False;

			/* The following is built so below we won't try to drop a table that isn't there. */
			$tablenames = $GLOBALS['phpgw_setup']->db->table_names();
			while(list($key,$val) = @each($tablenames))
			{
				$tables[] = $val['table_name'];
			}

			@reset($setup_info);
			while(list($key,$null) = @each($setup_info))
			{
				if($setup_info[$key]['tables'])
				{
					while(list($a,$table) = @each($setup_info[$key]['tables']))
					{
						//echo $table;
						if(in_array($table,$tables))
						{
							if($DEBUG){ echo '<br>process->droptables(): Dropping :'. $setup_info[$key]['name'] . ' table: ' . $table; }
							$GLOBALS['phpgw_setup']->oProc->DropTable($table);
							// Update the array values for return below
							$setup_info[$key]['status'] = 'U';
						}
					}
				}
			}

			/* Done, return current status */
			return ($setup_info);
		}

		/*!
		@function current
		@abstract process current table setup in each application/setup dir
		@param $appinfo	array of application info from setup.inc.php files, etc.
		@discussion This duplicates the old newtables behavior, using schema_proc
		*/
		function current($setup_info,$DEBUG=False)
		{
			if(!@$GLOBALS['phpgw_setup']->oProc)
			{
				$this->init_process();
			}
			$GLOBALS['phpgw_setup']->oProc->m_bDeltaOnly = False;

			@reset($setup_info);
			while(list($key,$null) = @each($setup_info))
			{
				$enabled = False;
				$appname  = $setup_info[$key]['name'];
				$apptitle = $setup_info[$key]['title'];

				if($DEBUG) { echo '<br>process->current(): Incoming status: ' . $appname . ',status: '. $setup_info[$key]['status']; }

				$appdir  = PHPGW_SERVER_ROOT . SEP . $appname . SEP . 'setup' . SEP;

				if($setup_info[$key]['tables'] && file_exists($appdir.'tables_current.inc.php'))
				{
					if($DEBUG) { echo '<br>process->current(): Including: ' . $appdir.'tables_current.inc.php'; }
					include ($appdir.'tables_current.inc.php');
					$ret = $this->post_process($phpgw_baseline,$DEBUG);
					if($ret)
					{
						if($GLOBALS['phpgw_setup']->app_registered($appname))
						{
							$GLOBALS['phpgw_setup']->update_app($appname);
							$GLOBALS['phpgw_setup']->update_hooks($appname);
						}
						else
						{
							$GLOBALS['phpgw_setup']->register_app($appname);
							$GLOBALS['phpgw_setup']->register_hooks($appname);
						}
						// Update the array values for return below
						$setup_info[$key]['status'] = 'C';
					}
					else
					{
						/* script processing failed */
						if($DEBUG) { echo '<br>process->current(): Failed for ' . $appname . ',status: '. $setup_info[$key]['status']; }
						$setup_info[$key]['status'] = 'F';
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
					if($setup_info[$key]['tables'][0] != '')
					{
						$enabled = False;
					}
					if($GLOBALS['phpgw_setup']->app_registered($appname))
					{
						$GLOBALS['phpgw_setup']->update_app($appname);
						$GLOBALS['phpgw_setup']->update_hooks($appname);
					}
					else
					{
						$GLOBALS['phpgw_setup']->register_app($appname,$enabled);
						$GLOBALS['phpgw_setup']->register_hooks($appname);
					}
					$setup_info[$key]['status'] = 'C';
				}
				if($DEBUG) { echo '<br>process->current(): Outgoing status: ' . $appname . ',status: '. $setup_info[$key]['status']; }
			}

			/* Done, return current status */
			return ($setup_info);
		}

		/*!
		@function default_records
		@abstract process default_records.inc.php in each application/setup dir
		@param $setup_info	array of application info from setup.inc.php files, etc.
		*/
		function default_records($setup_info,$DEBUG=False)
		{
			if(!@$GLOBALS['phpgw_setup']->oProc)
			{
				$this->init_process();
			}
			$GLOBALS['phpgw_setup']->oProc->m_bDeltaOnly = False;
//			$oProc = $GLOBALS['phpgw_setup']->oProc;

			@reset($setup_info);
			while(list($key,$null) = @each($setup_info))
			{
				$appname = $setup_info[$key]['name'];
				$appdir  = PHPGW_SERVER_ROOT . SEP . $appname . SEP . 'setup' . SEP;

				if($setup_info[$key]['tables'] && file_exists($appdir.'default_records.inc.php'))
				{
					if($DEBUG)
					{
						echo '<br>process->default_records(): Including default records for ' . $appname . "\n";
					}
					$GLOBALS['phpgw_setup']->oProc->m_odb->transaction_begin();
					include ($appdir.'default_records.inc.php');
					$GLOBALS['phpgw_setup']->oProc->m_odb->transaction_commit();
				}
				/* $setup_info[$key]['status'] = 'C'; */
			}

			/* Done, return current status */
			return ($setup_info);
		}

		/*!
		@function add_langs
		@abstract process application lang files and uninstall
		@param $setup_info	array of application info from setup.inc.php files, etc.
		*/
		function add_langs($setup_info,$DEBUG=False,$force_en=False)
		{
			@reset($setup_info);
			while(list($key,$null) = @each($setup_info))
			{
				$appname = $setup_info[$key]['name'];
				$this->translation->add_langs($appname,$DEBUG,$force_en);
				if($DEBUG)
				{
					echo '<br>process->add_langs(): Translations added for ' . $appname . "\n";
				}
			}
			/* Done, return current status */
			return ($setup_info);
		}

		/*!
		@function drop_langs
		@abstract process application lang files and install
		@param $setup_info	array of application info from setup.inc.php files, etc.
		*/
		function drop_langs($setup_info,$DEBUG=False)
		{
			@reset($setup_info);
			while(list($key,$null) = @each($setup_info))
			{
				$appname = $setup_info[$key]['name'];
				$this->translation->drop_langs($appname,$DEBUG);
				if($DEBUG)
				{
					echo '<br>process->drop_langs():  Translations removed for ' . $appname . "\n";
				}
			}
			/* Done, return current status */
			return ($setup_info);
		}

		/*!
		@function upgrade_langs
		@abstract process application lang files and reinstall
		@param $setup_info	array of application info from setup.inc.php files, etc.
		*/
		function upgrade_langs($setup_info,$DEBUG=False)
		{
			@reset($setup_info);
			while(list($key,$null) = @each($setup_info))
			{
				/* Don't upgrade lang files in the middle of an upgrade */
				if($setup_info[$key]['status'] == 'R')
				{
					continue;
				}
				$appname = $setup_info[$key]['name'];
				$this->translation->drop_langs($appname,$DEBUG);
				$this->translation->add_langs($appname,$DEBUG);
				if($DEBUG)
				{
					echo '<br>process->upgrade_langs(): Translations reinstalled for ' . $appname . "\n";
				}
			}
			/* Done, return current status */
			return ($setup_info);
		}

		/*!
		@function test_data
		@abstract process test_data.inc.php in each application/setup dir for developer tests
		This data should work with the baseline tables
		@param $setup_info	array of application info from setup.inc.php files, etc.
		*/
		function test_data($setup_info,$DEBUG=False)
		{
			if(!@$GLOBALS['phpgw_setup']->oProc)
			{
				$this->init_process();
			}
			$GLOBALS['phpgw_setup']->oProc->m_bDeltaOnly = False;
//			$oProc = $GLOBALS['phpgw_setup']->oProc;

			@reset($setup_info);
			while(list($key,$null) = @each($setup_info))
			{
				$appname = $setup_info[$key]['name'];
				$appdir  = PHPGW_SERVER_ROOT . SEP . $appname . SEP . 'setup' . SEP;

				if(file_exists($appdir.'test_data.inc.php'))
				{
					if($DEBUG)
					{
						echo '<br>process->test_data(): Including baseline test data for ' . $appname . "\n";
					}
					$GLOBALS['phpgw_setup']->oProc->m_odb->transaction_begin();
					include ($appdir.'test_data.inc.php');
					$GLOBALS['phpgw_setup']->oProc->m_odb->transaction_commit();
				}
			}

			/* Done, return current status */
			return ($setup_info);
		}

		/*!
		@function baseline
		@abstract process baseline table setup in each application/setup dir
		@param $appinfo	array of application info from setup.inc.php files, etc.
		*/
		function baseline($setup_info,$DEBUG=False)
		{
			if(!@$GLOBALS['phpgw_setup']->oProc)
			{
				$this->init_process();
			}

			@reset($setup_info);
			while(list($key,$null) = @each($setup_info))
			{
				$appname = $setup_info[$key]['name'];
				$appdir  = PHPGW_SERVER_ROOT . SEP . $appname . SEP . 'setup' . SEP;

				if(file_exists($appdir.'tables_baseline.inc.php'))
				{
					if($DEBUG)
					{
						echo '<br>process->baseline(): Including baseline tables for ' . $appname . "\n";
					}
					include ($appdir.'tables_baseline.inc.php');
					$GLOBALS['phpgw_setup']->oProc->GenerateScripts($phpgw_baseline, $DEBUG);
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

			/* Done, return current status */
			return ($setup_info);
		}

		/*!
		@function upgrade
		@abstract process available upgrades in each application/setup dir
		@param $appinfo	array of application info from setup.inc.php files, etc.
		*/
		function upgrade($setup_info,$DEBUG=False)
		{
			if(!@$GLOBALS['phpgw_setup']->oProc)
			{
				$this->init_process();
			}
			$GLOBALS['phpgw_setup']->oProc->m_odb->HaltOnError = 'no';
			$GLOBALS['phpgw_setup']->oProc->m_bDeltaOnly = True;

			@reset($setup_info);
			while(list($key,$null) = @each($setup_info))
			{
				/* Don't try to upgrade an app that is not installed */
				if(!$GLOBALS['phpgw_setup']->app_registered($setup_info[$key]['name']))
				{
					if($DEBUG)
					{
						echo '<br>process->upgrade(): Application not installed: ' . $appname . "\n";
					}
					unset($setup_info[$key]);
					continue;
				}

				/* if upgrade required, or if we are running again after an upgrade or dependency failure */
				if($DEBUG) { echo '<br>process->upgrade(): Incoming : appname: '.$setup_info[$key]['name'] . ' status: ' . $setup_info[$key]['status']; }
				if($setup_info[$key]['status'] == 'U' ||
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
					$GLOBALS['phpgw_setup']->oProc->m_aTables = $phpgw_baseline = array();
/*
					$phpgw_baseline = array();

					$tmpapp = array();
					$tmpapp[] = $setup_info[$key];
					$this->baseline($tmpapp,$DEBUG);
					$GLOBALS['phpgw_setup']->oProc->m_aTables = $phpgw_baseline;
					// So far, including the baseline file is not helping.
					// Only AlterColumn/RenameColumn seem to be failing silently.
					// This is because we are not keeping up with table changes, so a table in baseline
					// either does not exist anymore, or the baseline is being lost.
*/
					if($setup_info[$key]['tables'] && file_exists($appdir.'tables_baseline.inc.php'))
					{
						if($DEBUG)
						{
							echo '<br>process->baseline(): Including baseline tables for ' . $appname . "\n";
						}
						include ($appdir.'tables_baseline.inc.php');
						$GLOBALS['phpgw_setup']->oProc->m_aTables = $phpgw_baseline;
						/* $GLOBALS['phpgw_setup']->oProc->GenerateScripts($phpgw_baseline, $DEBUG); */
					}
					else
					{
						if($DEBUG)
						{
							echo '<br>process->baseline(): No baseline tables for ' . $appname . "\n";
						}
						/* This should be a break with a status setting, or not at all
						break;
						*/
					}
					if(file_exists($appdir . 'tables_update.inc.php') && !@$this->updateincluded[$appname])
					{
						include ($appdir . 'tables_update.inc.php');
						$this->updateincluded[$appname] = True;

						/* $test array comes from update file.  It is a list of available upgrade functions */
						@reset($test);
						while(list($x,$value) = @each($test))
						{
							$currentver = $setup_info[$key]['currentver'];

							/* build upgrade function name */
							$function = $appname . '_upgrade' . ereg_replace("\.", '_', $value);

							if($DEBUG)
							{
								echo '<br>process->upgrade(): appname:    ' . $appname;
								echo '<br>process->upgrade(): currentver: ' . $currentver;
								echo '<br>process->upgrade(): targetver:  ' . $targetver;
								echo '<br>process->upgrade(): status:     ' . $setup_info[$key]['status'];
								echo '<br>process->upgrade(): checking:   ' . $value;
								echo '<br>process->upgrade(): function:   ' . $function;
							}

							if($value == $targetver)
							{
								$GLOBALS['phpgw_setup']->oProc->m_bDeltaOnly = False;
								/* Done upgrading */
								if($DEBUG)
								{
									echo '<br>process->upgrade(): Upgrade of ' . $appname . ' to ' . $targetver . ' is completed.' . "\n";
								}
								$appstatus = 'C';
								$setup_info[$key]['status']     = $appstatus;
								$setup_info[$key]['currentver'] = $targetver;
								if($GLOBALS['phpgw_setup']->app_registered($appname))
								{
									$GLOBALS['phpgw_setup']->update_app($appname);
									$GLOBALS['phpgw_setup']->update_hooks($appname);
								}
								else
								{
									$GLOBALS['phpgw_setup']->register_app($appname);
									$GLOBALS['phpgw_setup']->register_hooks($appname);
								}
								//break;
							}
							elseif(($value == $currentver) || !$currentver)
							{
								/* start upgrading db in addition to baseline */
								$GLOBALS['phpgw_setup']->oProc->m_bDeltaOnly = False;
								if($DEBUG) { echo '<br>process->upgrade(): running ' . $function; }
								/* run upgrade function */
								$success = $function();
								if($success != False)
								{
									$setup_info[$key]['currentver'] = $success;
									if($DEBUG)
									{
										echo '<br>process->upgrade(): Upgrade of ' . $appname
											. ' from ' . $value
											. ' to ' . $setup_info[$key]['currentver']
											. ' is completed.' . "\n";
									}
									$appstatus = 'R';
									$setup_info[$key]['status'] = $appstatus;
									if($GLOBALS['phpgw_setup']->app_registered($appname))
									{
										if($DEBUG)
										{
											echo '<br>process->upgrade(): Updating registration of ' . $appname . ', new version: ' . $setup_info[$key]['currentver'];
										}
										$GLOBALS['phpgw_setup']->update_app($appname);
										$GLOBALS['phpgw_setup']->update_hooks($appname);
									}
									else
									{
										if($DEBUG)
										{
											echo '<br>process->upgrade(): Registering ' . $appname . ', version: ' . $setup_info[$key]['currentver'];
										}
										$GLOBALS['phpgw_setup']->register_app($appname);
										$GLOBALS['phpgw_setup']->register_hooks($appname);
									}
								}
								else
								{
									if($DEBUG)
									{
										echo '<br>process->upgrade(): Upgrade of ' . $appname
											. ' from ' . $currentver
											. ' to ' . $value
											. ' failed!!!' . "\n";
									}
									$appstatus  = 'F';
									break;
								}
							}
							elseif ($GLOBALS['phpgw_setup']->alessthanb($value,$currentver))
							{
								if($DEBUG) { echo '<br>process->upgrade(): running baseline delta only: ' . $function . '...'; }
								$GLOBALS['phpgw_setup']->oProc->m_bDeltaOnly = True;
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
						if($setup_info[$appname]['tables'])
						{
							$appstatus  = 'F';

							if($DEBUG)
							{
								echo '<br>process->upgrade(): No table upgrade available for ' . $appname . "\n";
							}
						}
						else
						{
							$setup_info[$key]['currentver'] == $targetver;
							$appstatus  = 'C';
							if($GLOBALS['phpgw_setup']->app_registered($appname))
							{
								$GLOBALS['phpgw_setup']->update_app($appname);
								$GLOBALS['phpgw_setup']->update_hooks($appname);
							}
							else
							{
								$GLOBALS['phpgw_setup']->register_app($appname);
								$GLOBALS['phpgw_setup']->register_hooks($appname);
							}

							if($DEBUG)
							{
								echo '<br>process->upgrade(): No table upgrade required for ' . $appname . "\n";
							}
						}
					}
				}
				else
				{
					$appstatus  = 'C';
					if($DEBUG)
					{
						echo '<br>process->upgrade(): No upgrade required for ' . $appname . "\n";
					}
				}

				/* Done with this app, update status */
				$setup_info[$key]['status'] = $appstatus;
				if($DEBUG)
				{
					echo '<br>process->upgrade(): Outgoing : appname: '.$setup_info[$key]['name'] . ' status: ' . $setup_info[$key]['status'];
				}
			}

			/* Done, return current status */
			return ($setup_info);
		}

		/*!
		@function post_process
		@abstract commit above processing to the db
		*/
		function post_process($tables,$DEBUG=False)
		{
			if(!$tables)
			{
				return False;
			}

			$ret = $GLOBALS['phpgw_setup']->oProc->GenerateScripts($tables,$DEBUG);
			if($ret)
			{
				$oret = $GLOBALS['phpgw_setup']->oProc->ExecuteScripts($tables,$DEBUG);
				if($oret)
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
		@abstract send this a table name, returns printable column spec and keys for the table from schema_proc
		@param	$tablename	table whose array you want to see
		*/
		function sql_to_array($tablename='')
		{
			if(!$tablename)
			{
				return False;
			}

			if(!$GLOBALS['phpgw_setup']->oProc)
			{
				$this->init_process();
			}

			$GLOBALS['phpgw_setup']->oProc->m_oTranslator->_GetColumns($GLOBALS['phpgw_setup']->oProc, $tablename, $sColumns, $sColumnName);

			while(list($key,$tbldata) = each($GLOBALS['phpgw_setup']->oProc->m_oTranslator->sCol))
			{
				$arr .= $tbldata;
			}
			$pk = $GLOBALS['phpgw_setup']->oProc->m_oTranslator->pk;
			$fk = $GLOBALS['phpgw_setup']->oProc->m_oTranslator->fk;
			$ix = $GLOBALS['phpgw_setup']->oProc->m_oTranslator->ix;
			$uc = $GLOBALS['phpgw_setup']->oProc->m_oTranslator->uc;

			return array($arr,$pk,$fk,$ix,$uc);
		}
	}
?>