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

	function calendar_v0_9_2to0_9_3update_owner($table, $field)
	{
		$GLOBALS['phpgw_setup']->oProc->query("select distinct($field) from $table");
		if ($GLOBALS['phpgw_setup']->oProc->num_rows())
		{
			while ($GLOBALS['phpgw_setup']->oProc->next_record())
			{
				$owner[count($owner)] = $GLOBALS['phpgw_setup']->oProc->f($field);
			}
			if($GLOBALS['phpgw_setup']->alessthanb($GLOBALS['setup_info']['phpgwapi']['currentver'],'0.9.10pre4'))
			{
				$acctstbl = 'accounts';
			}
			else
			{
				$acctstbl = 'phpgw_accounts';
			}
			for($i=0;$i<count($owner);$i++)
			{
				$GLOBALS['phpgw_setup']->oProc->query("SELECT account_id FROM $acctstbl WHERE account_lid='".$owner[$i]."'");
				$GLOBALS['phpgw_setup']->oProc->next_record();
				$GLOBALS['phpgw_setup']->oProc->query("UPDATE $table SET $field=".$GLOBALS['phpgw_setup']->oProc->f('account_id')." WHERE $field='".$owner[$i]."'");
			}
		}
		$GLOBALS['phpgw_setup']->oProc->AlterColumn($table, $field, array('type' => 'int', 'precision' => 4, 'nullable' => false, 'default' => 0));
	}


	$test[] = '0.9.3pre1';
	function calendar_upgrade0_9_3pre1()
	{
		calendar_v0_9_2to0_9_3update_owner('webcal_entry','cal_create_by');
		calendar_v0_9_2to0_9_3update_owner('webcal_entry_user','cal_login');
		$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.3pre2';
		return $GLOBALS['setup_info']['calendar']['currentver'];
	}
	$test[] = "0.9.3pre2";
	function calendar_upgrade0_9_3pre2()
	{
		$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.3pre3';
		return $GLOBALS['setup_info']['calendar']['currentver'];
	}
	$test[] = "0.9.3pre3";
	function calendar_upgrade0_9_3pre3()
	{
		$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.3pre4';
		return $GLOBALS['setup_info']['calendar']['currentver'];
	}
	$test[] = "0.9.3pre4";
	function calendar_upgrade0_9_3pre4()
	{
		$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.3pre5';
		return $GLOBALS['setup_info']['calendar']['currentver'];
	}
	$test[] = "0.9.3pre5";
	function calendar_upgrade0_9_3pre5()
	{
		$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.3pre6';
		return $GLOBALS['setup_info']['calendar']['currentver'];
	}
	$test[] = "0.9.3pre6";
	function calendar_upgrade0_9_3pre6()
	{
		$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.3pre7';
		return $GLOBALS['setup_info']['calendar']['currentver'];
	}
	$test[] = "0.9.3pre7";
	function calendar_upgrade0_9_3pre7()
	{
		$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.3pre8';
		return $GLOBALS['setup_info']['calendar']['currentver'];
	}
	$test[] = "0.9.3pre8";
	function calendar_upgrade0_9_3pre8()
	{
		$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.3pre9';
		return $GLOBALS['setup_info']['calendar']['currentver'];
	}
	$test[] = "0.9.3pre9";
	function calendar_upgrade0_9_3pre9()
	{
		$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.3pre10';
		return $GLOBALS['setup_info']['calendar']['currentver'];
	}
	$test[] = "0.9.3pre10";
	function calendar_upgrade0_9_3pre10()
	{
		$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.3';
		return $GLOBALS['setup_info']['calendar']['currentver'];
	}
	$test[] = "0.9.3";
	function calendar_upgrade0_9_3()
	{
		$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.4pre1';
		return $GLOBALS['setup_info']['calendar']['currentver'];
	}
	$test[] = "0.9.4pre1";
	function calendar_upgrade0_9_4pre1()
	{
		$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.4pre2';
		return $GLOBALS['setup_info']['calendar']['currentver'];
	}

	$test[] = '0.9.4pre2';
	function calendar_upgrade0_9_4pre2()
	{
		$GLOBALS['phpgw_setup']->oProc->RenameColumn('webcal_entry', 'cal_create_by', 'cal_owner');
		$GLOBALS['phpgw_setup']->oProc->AlterColumn('webcal_entry', 'cal_owner', array('type' => 'int', 'precision' => 4, 'nullable' => false));
		$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.4pre3';
		return $GLOBALS['setup_info']['calendar']['currentver'];
	}

	$test[] = "0.9.4pre3";
	function calendar_upgrade0_9_4pre3()
	{
		$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.4pre4';
		return $GLOBALS['setup_info']['calendar']['currentver'];
	}
	$test[] = "0.9.4pre4";
	function calendar_upgrade0_9_4pre4()
	{
		$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.4pre5';
		return $GLOBALS['setup_info']['calendar']['currentver'];
	}
	$test[] = "0.9.4pre5";
	function calendar_upgrade0_9_4pre5()
	{
		$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.4';
		return $GLOBALS['setup_info']['calendar']['currentver'];
	}
	$test[] = "0.9.4";
	function calendar_upgrade0_9_4()
	{
		$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.5pre1';
		return $GLOBALS['setup_info']['calendar']['currentver'];
	}
	$test[] = "0.9.5pre1";
	function calendar_upgrade0_9_5pre1()
	{
		$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.5pre2';
		return $GLOBALS['setup_info']['calendar']['currentver'];
	}
	$test[] = "0.9.5pre2";
	function calendar_upgrade0_9_5pre2()
	{
		$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.5pre3';
		return $GLOBALS['setup_info']['calendar']['currentver'];
	}
	$test[] = "0.9.5";
	function calendar_upgrade0_9_5()
	{
		$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.6';
		return $GLOBALS['setup_info']['calendar']['currentver'];
	}
	$test[] = "0.9.6";
	function calendar_upgrade0_9_6()
	{
		$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.7pre1';
		return $GLOBALS['setup_info']['calendar']['currentver'];
	}

	$test[] = '0.9.7pre1';
	function calendar_upgrade0_9_7pre1()
	{
		$db2 = $GLOBALS['phpgw_setup']->db;

		if($GLOBALS['phpgw_setup']->alessthanb($GLOBALS['setup_info']['phpgwapi']['currentver'],'0.9.10pre8'))
		{
			$appstable = 'applications';
		}
		else
		{
			$appstable = 'phpgw_applications';
		}

		$GLOBALS['phpgw_setup']->oProc->CreateTable('calendar_entry',
			Array(
				'fd' => array(
					'cal_id' => array('type' => 'auto', 'nullable' => false),
					'cal_owner' => array('type' => 'int', 'precision' => 4, 'nullable' => false, 'default' => '0'),
					'cal_group' => array('type' => 'varchar', 'precision' => 255),
					'cal_datetime' => array('type' => 'int', 'precision' => 4),
					'cal_mdatetime' => array('type' => 'int', 'precision' => 4),
					'cal_duration' => array('type' => 'int', 'precision' => 4, 'nullable' => false, 'default' => '0'),
					'cal_priority' => array('type' => 'int', 'precision' => 4, 'nullable' => false, 'default' => '2'),
					'cal_type' => array('type' => 'varchar', 'precision' => 10),
					'cal_access' => array('type' => 'varchar', 'precision' => 10),
					'cal_name' => array('type' => 'varchar', 'precision' => 80, 'nullable' => false),
					'cal_description' => array('type' => 'text')
				),
				'pk' => array("cal_id"),
				'ix' => array(),
				'fk' => array(),
				'uc' => array()
			)
		);
	
		$GLOBALS['phpgw_setup']->oProc->query('SELECT count(*) FROM webcal_entry',__LINE__,__FILE__);
		$GLOBALS['phpgw_setup']->oProc->next_record();
		if($GLOBALS['phpgw_setup']->oProc->f(0))
		{
			$GLOBALS['phpgw_setup']->oProc->query('SELECT cal_id,cal_owner,cal_duration,cal_priority,cal_type,cal_access,cal_name,cal_description,cal_id,cal_date,cal_time,cal_mod_date,cal_mod_time FROM webcal_entry ORDER BY cal_id',__LINE__,__FILE__);
			while($GLOBALS['phpgw_setup']->oProc->next_record())
			{
				$cal_id = $GLOBALS['phpgw_setup']->oProc->f('cal_id');
				$cal_owner = $GLOBALS['phpgw_setup']->oProc->f('cal_owner');
				$cal_duration = $GLOBALS['phpgw_setup']->oProc->f('cal_duration');
				$cal_priority = $GLOBALS['phpgw_setup']->oProc->f('cal_priority');
				$cal_type = $GLOBALS['phpgw_setup']->oProc->f('cal_type');
				$cal_access = $GLOBALS['phpgw_setup']->oProc->f('cal_access');
				$cal_name = $GLOBALS['phpgw_setup']->oProc->f('cal_name');
				$cal_description = $GLOBALS['phpgw_setup']->oProc->f('cal_description');
				$datetime = mktime(intval(strrev(substr(strrev($GLOBALS['phpgw_setup']->oProc->f('cal_time')),4))),intval(strrev(substr(strrev($GLOBALS['phpgw_setup']->oProc->f('cal_time')),2,2))),intval(strrev(substr(strrev($GLOBALS['phpgw_setup']->oProc->f('cal_time')),0,2))),intval(substr($GLOBALS['phpgw_setup']->oProc->f('cal_date'),4,2)),intval(substr($GLOBALS['phpgw_setup']->oProc->f('cal_date'),6,2)),intval(substr($GLOBALS['phpgw_setup']->oProc->f('cal_date'),0,4)));
				$moddatetime = mktime(intval(strrev(substr(strrev($GLOBALS['phpgw_setup']->oProc->f('cal_mod_time')),4))),intval(strrev(substr(strrev($GLOBALS['phpgw_setup']->oProc->f('cal_mod_time')),2,2))),intval(strrev(substr(strrev($GLOBALS['phpgw_setup']->oProc->f('cal_mod_time')),0,2))),intval(substr($GLOBALS['phpgw_setup']->oProc->f('cal_mod_date'),4,2)),intval(substr($GLOBALS['phpgw_setup']->oProc->f('cal_mod_date'),6,2)),intval(substr($GLOBALS['phpgw_setup']->oProc->f('cal_mod_date'),0,4)));
				$db2->query('SELECT groups FROM webcal_entry_groups WHERE cal_id='.$cal_id,__LINE__,__FILE__);
				$db2->next_record();
				$cal_group = $db2->f('groups');
				$db2->query('INSERT INTO calendar_entry(cal_id,cal_owner,cal_group,cal_datetime,cal_mdatetime,cal_duration,cal_priority,cal_type,cal_access,cal_name,cal_description) '
					.'VALUES('.$cal_id.",'".$cal_owner."','".$cal_group."',".$datetime.",".$moddatetime.",".$cal_duration.",".$cal_priority.",'".$cal_type."','".$cal_access."','".$cal_name."','".$cal_description."')",__LINE__,__FILE__);
			}
		}
	
		$GLOBALS['phpgw_setup']->oProc->DropTable('webcal_entry_groups');
		$GLOBALS['phpgw_setup']->oProc->DropTable('webcal_entry');
	
		$GLOBALS['phpgw_setup']->oProc->CreateTable('calendar_entry_user',
			Array(
				'fd' => array(
					'cal_id' => array('type' => 'int', 'precision' => 4, 'nullable' => false, 'default' => '0'),
					'cal_login' => array('type' => 'int', 'precision' => 4, 'nullable' => false, 'default' => '0'),
					'cal_status' => array('type' => 'char', 'precision' => 1, 'default' => 'A')
				),
				'pk' => array('cal_id', 'cal_login'),
				'ix' => array(),
				'fk' => array(),
				'uc' => array()
			)
		);
	
		$GLOBALS['phpgw_setup']->oProc->query('SELECT count(*) FROM webcal_entry_user',__LINE__,__FILE__);
		$GLOBALS['phpgw_setup']->oProc->next_record();
		if($GLOBALS['phpgw_setup']->oProc->f(0))
		{
			$GLOBALS['phpgw_setup']->oProc->query('SELECT cal_id,cal_login,cal_status FROM webcal_entry_user ORDER BY cal_id',__LINE__,__FILE__);
			while($GLOBALS['phpgw_setup']->oProc->next_record())
			{
				$cal_id = $GLOBALS['phpgw_setup']->oProc->f('cal_id');
				$cal_login = $GLOBALS['phpgw_setup']->oProc->f('cal_login');
				$cal_status = $GLOBALS['phpgw_setup']->oProc->f('cal_status');
				$db2->query('INSERT INTO calendar_entry_user(cal_id,cal_login,cal_status) VALUES('.$cal_id.','.$cal_login.",'".$cal_status."')",__LINE__,__FILE__);
			}
		}
	
		$GLOBALS['phpgw_setup']->oProc->DropTable('webcal_entry_user');
	
		$GLOBALS['phpgw_setup']->oProc->CreateTable('calendar_entry_repeats',
			Array(
				'fd' => array(
					'cal_id' => array('type' => 'int', 'precision' => 4, 'default' => '0', 'nullable' => false),
					'cal_type' => array('type' => 'varchar', 'precision' => 20, 'default' => 'daily', 'nullable' => false),
					'cal_use_end' => array('type' => 'int', 'precision' => 4, 'default' => '0'),
					'cal_end' => array('type' => 'int', 'precision' => 4),
					'cal_frequency' => array('type' => 'int', 'precision' => 4, 'default' => '1'),
					'cal_days' => array('type' => 'char', 'precision' => 7)
				),
				'pk' => array(),
				'ix' => array(),
				'fk' => array(),
				'uc' => array()
			)
		);
	
		$GLOBALS['phpgw_setup']->oProc->query('SELECT count(*) FROM webcal_entry_repeats',__LINE__,__FILE__);
		$GLOBALS['phpgw_setup']->oProc->next_record();
		if($GLOBALS['phpgw_setup']->oProc->f(0))
		{
			$GLOBALS['phpgw_setup']->oProc->query('SELECT cal_id,cal_type,cal_end,cal_frequency,cal_days FROM webcal_entry_repeats ORDER BY cal_id',__LINE__,__FILE__);
			while($GLOBALS['phpgw_setup']->oProc->next_record())
			{
				$cal_id = $GLOBALS['phpgw_setup']->oProc->f('cal_id');
				$cal_type = $GLOBALS['phpgw_setup']->oProc->f('cal_type');
				if(isset($GLOBALS['phpgw_setup']->oProc->Record['cal_end']))
				{
					$enddate = mktime(0,0,0,intval(substr($GLOBALS['phpgw_setup']->oProc->f('cal_end'),4,2)),intval(substr($GLOBALS['phpgw_setup']->oProc->f('cal_end'),6,2)),intval(substr($GLOBALS['phpgw_setup']->oProc->f('cal_end'),0,4)));
					$useend = 1;
				}
				else
				{
					$enddate = 0;
					$useend = 0;
				}
				$cal_frequency = $GLOBALS['phpgw_setup']->oProc->f('cal_frequency');
				$cal_days = $GLOBALS['phpgw_setup']->oProc->f('cal_days');
				$db2->query('INSERT INTO calendar_entry_repeats(cal_id,cal_type,cal_use_end,cal_end,cal_frequency,cal_days) VALUES('.$cal_id.",'".$cal_type."',".$useend.",".$enddate.",".$cal_frequency.",'".$cal_days."')",__LINE__,__FILE__);
			}
		}
	
		$GLOBALS['phpgw_setup']->oProc->DropTable('webcal_entry_repeats');
		$GLOBALS['phpgw_setup']->oProc->query("UPDATE $appstable SET app_tables='calendar_entry,calendar_entry_user,calendar_entry_repeats' WHERE app_name='calendar'",__LINE__,__FILE__);
	
		$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.7pre2';
		return $GLOBALS['setup_info']['calendar']['currentver'];
	}

	$test[] = "0.9.7pre2";
	function calendar_upgrade0_9_7pre2()
	{
		$db2 = $GLOBALS['phpgw_setup']->db;
	
		$GLOBALS['phpgw_setup']->oProc->RenameColumn('calendar_entry', 'cal_duration', 'cal_edatetime');
		$GLOBALS['phpgw_setup']->oProc->query('SELECT cal_id,cal_datetime,cal_owner,cal_edatetime,cal_mdatetime FROM calendar_entry ORDER BY cal_id',__LINE__,__FILE__);
		if($GLOBALS['phpgw_setup']->oProc->num_rows())
		{
			while($GLOBALS['phpgw_setup']->oProc->next_record())
			{
				$db2->query("SELECT preference_value FROM preferences WHERE preference_name='tz_offset' AND preference_appname='common' AND preference_owner=".$GLOBALS['phpgw_setup']->db->f('cal_owner'),__LINE__,__FILE__);
				$db2->next_record();
				$tz = $db2->f('preference_value');
				$cal_id = $GLOBALS['phpgw_setup']->oProc->f('cal_id');
				$datetime = $GLOBALS['phpgw_setup']->oProc->f('cal_datetime') - ((60 * 60) * $tz);
				$mdatetime = $GLOBALS['phpgw_setup']->oProc->f('cal_mdatetime') - ((60 * 60) * $tz);
				$edatetime = $datetime + (60 * $GLOBALS['phpgw_setup']->oProc->f('cal_edatetime'));
				$db2->query('UPDATE calendar_entry SET cal_datetime='.$datetime.', cal_edatetime='.$edatetime.', cal_mdatetime='.$mdatetime.' WHERE cal_id='.$cal_id,__LINE__,__FILE__);
			}
		}
	
		$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.7pre3';
		return $GLOBALS['setup_info']['calendar']['currentver'];
	}

	$test[] = "0.9.7pre3";
	function calendar_upgrade0_9_7pre3()
	{
		$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.7';
		return $GLOBALS['setup_info']['calendar']['currentver'];
	}
	$test[] = "0.9.7";
	function calendar_upgrade0_9_7()
	{
		$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.8pre1';
		return $GLOBALS['setup_info']['calendar']['currentver'];
	}
	$test[] = "0.9.8pre1";
	function calendar_upgrade0_9_8pre1()
	{
		$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.8pre2';
		return $GLOBALS['setup_info']['calendar']['currentver'];
	}
	$test[] = "0.9.8pre2";
	function calendar_upgrade0_9_8pre2()
	{
		$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.8pre3';
		return $GLOBALS['setup_info']['calendar']['currentver'];
	}
	$test[] = "0.9.8pre3";
	function calendar_upgrade0_9_8pre3()
	{
		$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.8pre4';
		return $GLOBALS['setup_info']['calendar']['currentver'];
	}
	$test[] = "0.9.8pre4";
	function calendar_upgrade0_9_8pre4()
	{
		$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.8pre5';
		return $GLOBALS['setup_info']['calendar']['currentver'];
	}
	$test[] = '0.9.8pre5';
	function calendar_upgrade0_9_8pre5()
	{
		$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.9pre1';
		return $GLOBALS['setup_info']['calendar']['currentver'];
	}
	$test[] = "0.9.9pre1";
	function calendar_upgrade0_9_9pre1()
	{
		$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.9';
		return $GLOBALS['setup_info']['calendar']['currentver'];
	}
	$test[] = "0.9.9";
	function calendar_upgrade0_9_9()
	{
		$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.10pre1';
		return $GLOBALS['setup_info']['calendar']['currentver'];
	}
	$test[] = "0.9.10pre1";
	function calendar_upgrade0_9_10pre1()
	{
		$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.10pre2';
		return $GLOBALS['setup_info']['calendar']['currentver'];
	}
	$test[] = "0.9.10pre2";
	function calendar_upgrade0_9_10pre2()
	{
		$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.10pre3';
		return $GLOBALS['setup_info']['calendar']['currentver'];
	}
	$test[] = "0.9.10pre3";
	function calendar_upgrade0_9_10pre3()
	{
		$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.10pre4';
		return $GLOBALS['setup_info']['calendar']['currentver'];
	}
	$test[] = "0.9.10pre4";
	function calendar_upgrade0_9_10pre4()
	{
		$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.10pre5';
		return $GLOBALS['setup_info']['calendar']['currentver'];
	}
	$test[] = "0.9.10pre5";
	function calendar_upgrade0_9_10pre5()
	{
		$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.10pre6';
		return $GLOBALS['setup_info']['calendar']['currentver'];
	}
	$test[] = "0.9.10pre6";
	function calendar_upgrade0_9_10pre6()
	{
		$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.10pre7';
		return $GLOBALS['setup_info']['calendar']['currentver'];
	}
	$test[] = "0.9.10pre7";
	function calendar_upgrade0_9_10pre7()
	{
		$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.10pre8';
		return $GLOBALS['setup_info']['calendar']['currentver'];
	}
	$test[] = "0.9.10pre8";
	function calendar_upgrade0_9_10pre8()
	{
		$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.10pre9';
		return $GLOBALS['setup_info']['calendar']['currentver'];
	}
	$test[] = '0.9.10pre9';
	function calendar_upgrade0_9_10pre9()
	{
		$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.10pre10';
		return $GLOBALS['setup_info']['calendar']['currentver'];
	}
	$test[] = '0.9.10pre10';
	function calendar_upgrade0_9_10pre10()
	{
		$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.10pre11';
		return $GLOBALS['setup_info']['calendar']['currentver'];
	}
	$test[] = '0.9.10pre11';
	function calendar_upgrade0_9_10pre11()
	{
		$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.10pre12';
		return $GLOBALS['setup_info']['calendar']['currentver'];
	}
	$test[] = '0.9.10pre12';
	function calendar_upgrade0_9_10pre12()
	{
		$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.10pre13';
		return $GLOBALS['setup_info']['calendar']['currentver'];
	}
	$test[] = '0.9.10pre13';
	function calendar_upgrade0_9_10pre13()
	{
		$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.10pre14';
		return $GLOBALS['setup_info']['calendar']['currentver'];
	}
	$test[] = '0.9.10pre14';
	function calendar_upgrade0_9_10pre14()
	{
		$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.10pre15';
		return $GLOBALS['setup_info']['calendar']['currentver'];
	}
	$test[] = '0.9.10pre15';
	function calendar_upgrade0_9_10pre15()
	{
		$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.10pre16';
		return $GLOBALS['setup_info']['calendar']['currentver'];
	}
	$test[] = '0.9.10pre16';
	function calendar_upgrade0_9_10pre16()
	{
		$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.10pre17';
		return $GLOBALS['setup_info']['calendar']['currentver'];
	}
	$test[] = '0.9.10pre17';
	function calendar_upgrade0_9_10pre17()
	{
		$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.10pre18';
		return $GLOBALS['setup_info']['calendar']['currentver'];
	}
	$test[] = '0.9.10pre18';
	function calendar_upgrade0_9_10pre18()
	{
		$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.10pre19';
		return $GLOBALS['setup_info']['calendar']['currentver'];
	}
	$test[] = '0.9.10pre19';
	function calendar_upgrade0_9_10pre19()
	{
		$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.10pre20';
		return $GLOBALS['setup_info']['calendar']['currentver'];
	}
	$test[] = '0.9.10pre20';
	function calendar_upgrade0_9_10pre20()
	{
		$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.10pre21';
		return $GLOBALS['setup_info']['calendar']['currentver'];
	}
	$test[] = '0.9.10pre21';
	function calendar_upgrade0_9_10pre21()
	{
		$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.10pre22';
		return $GLOBALS['setup_info']['calendar']['currentver'];
	}
	$test[] = '0.9.10pre22';
	function calendar_upgrade0_9_10pre22()
	{
		$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.10pre23';
		return $GLOBALS['setup_info']['calendar']['currentver'];
	}
	$test[] = '0.9.10pre23';
	function calendar_upgrade0_9_10pre23()
	{
		$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.10pre24';
		return $GLOBALS['setup_info']['calendar']['currentver'];
	}
	$test[] = '0.9.10pre24';
	function calendar_upgrade0_9_10pre24()
	{
		$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.10pre25';
		return $GLOBALS['setup_info']['calendar']['currentver'];
	}
	$test[] = '0.9.10pre25';
	function calendar_upgrade0_9_10pre25()
	{
		$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.10pre26';
		return $GLOBALS['setup_info']['calendar']['currentver'];
	}
	$test[] = '0.9.10pre26';
	function calendar_upgrade0_9_10pre26()
	{
		$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.10pre27';
		return $GLOBALS['setup_info']['calendar']['currentver'];
	}
	$test[] = '0.9.10pre27';
	function calendar_upgrade0_9_10pre27()
	{
		$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.10pre28';
		return $GLOBALS['setup_info']['calendar']['currentver'];
	}
	$test[] = '0.9.10pre28';
	function calendar_upgrade0_9_10pre28()
	{
		$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.10';
		return $GLOBALS['setup_info']['calendar']['currentver'];
	}
	$test[] = '0.9.10';
	function calendar_upgrade0_9_10()
	{
		$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.11.001';
		return $GLOBALS['setup_info']['calendar']['currentver'];
	}
	$test[] = '0.9.11';
	function calendar_upgrade0_9_11()
	{
		$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.11.001';
		return $GLOBALS['setup_info']['calendar']['currentver'];
	}

	$test[] = '0.9.11.001';
	function calendar_upgrade0_9_11_001()
	{
		$db2 = $GLOBALS['phpgw_setup']->db;

		if(extension_loaded('mcal') == False)
		{
			define(RECUR_NONE,0);
			define(RECUR_DAILY,1);
			define(RECUR_WEEKLY,2);
			define(RECUR_MONTHLY_MDAY,3);
			define(RECUR_MONTHLY_WDAY,4);
			define(RECUR_YEARLY,5);
	
			define(M_SUNDAY,1);
			define(M_MONDAY,2);
			define(M_TUESDAY,4);
			define(M_WEDNESDAY,8);
			define(M_THURSDAY,16);
			define(M_FRIDAY,32);
			define(M_SATURDAY,64);
		}

// calendar_entry => phpgw_cal
		$GLOBALS['phpgw_setup']->oProc->CreateTable('phpgw_cal',
			Array(
				'fd' => array(
					'cal_id' => array('type' => 'auto', 'nullable' => False),
					'owner' => array('type' => 'int', 'precision' => 8, 'nullable' => False),
					'category' => array('type' => 'int', 'precision' => 8, 'default' => '0', 'nullable' => True),
					'groups' => array('type' => 'varchar', 'precision' => 255, 'nullable' => True),
					'datetime' => array('type' => 'int', 'precision' => 8, 'nullable' => True),
					'mdatetime' => array('type' => 'int', 'precision' => 8, 'nullable' => True),
					'edatetime' => array('type' => 'int', 'precision' => 8, 'nullable' => True),
					'priority' => array('type' => 'int', 'precision' => 8, 'nullable' => False, 'default' => '2'),
					'cal_type' => array('type' => 'varchar', 'precision' => 10, 'nullable' => True),
					'is_public' => array('type' => 'int', 'precision' => 8, 'nullable' => False, 'default' => '1'),
					'title' => array('type' => 'varchar', 'precision' => 80, 'nullable' => False, 'default' => '1'),
					'description' => array('type' => 'text', 'nullable' => True)
				),
				'pk' => array('cal_id'),
				'fk' => array(),
				'ix' => array(),
				'uc' => array()
			)
		);

		$GLOBALS['phpgw_setup']->oProc->query('SELECT * FROM calendar_entry',__LINE__,__FILE__);
		while($GLOBALS['phpgw_setup']->oProc->next_record())
		{
			$id = $GLOBALS['phpgw_setup']->oProc->f('cal_id');
			$owner = $GLOBALS['phpgw_setup']->oProc->f('cal_owner');
			$access = $GLOBALS['phpgw_setup']->oProc->f('cal_access');
			switch($access)
			{
				case 'private':
					$is_public = 0;
					break;
				case 'public':
					$is_public = 1;
					break;
				case 'group':
					$is_public = 2;
					break;
			}
			$groups = $GLOBALS['phpgw_setup']->oProc->f('cal_group');
			$datetime = $GLOBALS['phpgw_setup']->oProc->f('cal_datetime');
			$mdatetime = $GLOBALS['phpgw_setup']->oProc->f('cal_mdatetime');
			$edatetime = $GLOBALS['phpgw_setup']->oProc->f('cal_edatetime');
			$priority = $GLOBALS['phpgw_setup']->oProc->f('cal_priority');
			$type = $GLOBALS['phpgw_setup']->oProc->f('cal_type');
			$title = $GLOBALS['phpgw_setup']->oProc->f('cal_name');
			$description = $GLOBALS['phpgw_setup']->oProc->f('cal_description');

			$db2->query("INSERT INTO phpgw_cal(cal_id,owner,groups,datetime,mdatetime,edatetime,priority,cal_type,is_public,title,description) "
				. "VALUES($id,$owner,'$groups',$datetime,$mdatetime,$edatetime,$priority,'$type',$is_public,'$title','$description')",__LINE__,__FILE__);
		}
		$GLOBALS['phpgw_setup']->oProc->DropTable('calendar_entry');

// calendar_entry_repeats => phpgw_cal_repeats
		$GLOBALS['phpgw_setup']->oProc->CreateTable('phpgw_cal_repeats',
			Array(
				'fd' => array(
					'cal_id' => array('type' => 'int', 'precision' => 8,'nullable' => False),
					'recur_type' => array('type' => 'int', 'precision' => 8,'nullable' => False),
					'recur_use_end' => array('type' => 'int', 'precision' => 8,'nullable' => True),
					'recur_enddate' => array('type' => 'int', 'precision' => 8,'nullable' => True),
					'recur_interval' => array('type' => 'int', 'precision' => 8,'nullable' => True,'default' => '1'),
					'recur_data' => array('type' => 'int', 'precision' => 8,'nullable' => True,'default' => '1')
				),
				'pk' => array(),
				'fk' => array(),
				'ix' => array(),
				'uc' => array()
			)
		);
		$GLOBALS['phpgw_setup']->oProc->query('SELECT * FROM calendar_entry_repeats',__LINE__,__FILE__);
		while($GLOBALS['phpgw_setup']->oProc->next_record())
		{
			$id = $GLOBALS['phpgw_setup']->oProc->f('cal_id');
			$recur_type = $GLOBALS['phpgw_setup']->oProc->f('cal_type');
			switch($recur_type)
			{
				case 'daily':
					$recur_type_num = RECUR_DAILY;
					break;
				case 'weekly':
					$recur_type_num = RECUR_WEEKLY;
					break;
				case 'monthlybydate':
					$recur_type_num = RECUR_MONTHLY_MDAY;
					break;
				case 'monthlybyday':
					$recur_type_num = RECUR_MONTHLY_WDAY;
					break;
				case 'yearly':
					$recur_type_num = RECUR_YEARLY;
					break;
			}
			$recur_end_use = $GLOBALS['phpgw_setup']->oProc->f('cal_use_end');
			$recur_end = $GLOBALS['phpgw_setup']->oProc->f('cal_end');
			$recur_interval = $GLOBALS['phpgw_setup']->oProc->f('cal_frequency');
			$days = strtoupper($GLOBALS['phpgw_setup']->oProc->f('cal_days'));
			$recur_data = 0;
			$recur_data += (substr($days,0,1)=='Y'?M_SUNDAY:0);
			$recur_data += (substr($days,1,1)=='Y'?M_MONDAY:0);
			$recur_data += (substr($days,2,1)=='Y'?M_TUESDAY:0);
			$recur_data += (substr($days,3,1)=='Y'?M_WEDNESDAY:0);
			$recur_data += (substr($days,4,1)=='Y'?M_THURSDAY:0);
			$recur_data += (substr($days,5,1)=='Y'?M_FRIDAY:0);
			$recur_data += (substr($days,6,1)=='Y'?M_SATURDAY:0);
			$db2->query("INSERT INTO phpgw_cal_repeats(cal_id,recur_type,recur_use_end,recur_enddate,recur_interval,recur_data) "
				. "VALUES($id,$recur_type_num,$recur_use_end,$recur_end,$recur_interval,$recur_data)",__LINE__,__FILE__);
		}
		$GLOBALS['phpgw_setup']->oProc->DropTable('calendar_entry_repeats');

// calendar_entry_user => phpgw_cal_user
		$GLOBALS['phpgw_setup']->oProc->RenameTable('calendar_entry_user','phpgw_cal_user');

		$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.11.002';
		return $GLOBALS['setup_info']['calendar']['currentver'];
	}

	$test[] = '0.9.11.002';
	function calendar_upgrade0_9_11_002()
	{
		$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.11.003';
		return $GLOBALS['setup_info']['calendar']['currentver'];
	}

	$test[] = '0.9.11.003';
	function calendar_upgrade0_9_11_003()
	{
		$GLOBALS['phpgw_setup']->oProc->CreateTable('phpgw_cal_holidays',
			Array(
				'fd' => array(
					'locale' => array('type' => 'char', 'precision' => 2,'nullable' => False),
					'name' => array('type' => 'varchar', 'precision' => 50,'nullable' => False),
					'date_time' => array('type' => 'int', 'precision' => 8,'nullable' => False, 'default' => '0')
				),
				'pk' => array('locale','name'),
				'fk' => array(),
				'ix' => array(),
				'uc' => array()
			)
		);

		$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.11.004';
		return $GLOBALS['setup_info']['calendar']['currentver'];
	}

	$test[] = '0.9.11.004';
	function calendar_upgrade0_9_11_004()
	{
		$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.11.005';
		return $GLOBALS['setup_info']['calendar']['currentver'];
	}

	$test[] = '0.9.11.005';
	function calendar_upgrade0_9_11_005()
	{
		$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.11.006';
		return $GLOBALS['setup_info']['calendar']['currentver'];
	}

	$test[] = '0.9.11.006';
	function calendar_upgrade0_9_11_006()
	{
		$GLOBALS['phpgw_setup']->oProc->DropTable('phpgw_cal_holidays');
		$GLOBALS['phpgw_setup']->oProc->CreateTable('phpgw_cal_holidays',
			Array(
				'fd' => array(
					'hol_id' => array('type' => 'auto','nullable' => False),
					'locale' => array('type' => 'char', 'precision' => 2,'nullable' => False),
					'name' => array('type' => 'varchar', 'precision' => 50,'nullable' => False),
					'date_time' => array('type' => 'int', 'precision' => 8,'nullable' => False, 'default' => '0')
				),
				'pk' => array('hol_id'),
				'fk' => array(),
				'ix' => array(),
				'uc' => array()
			)
		);

		$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.11.007';
		return $GLOBALS['setup_info']['calendar']['currentver'];
	}

	$test[] = '0.9.11.007';
	function calendar_upgrade0_9_11_007()
	{
		$GLOBALS['phpgw_setup']->oProc->query('DELETE FROM phpgw_cal_holidays');
		$GLOBALS['phpgw_setup']->oProc->AddColumn('phpgw_cal_holidays','mday',array('type' => 'int', 'precision' => 8,'nullable' => False, 'default' => '0'));
		$GLOBALS['phpgw_setup']->oProc->AddColumn('phpgw_cal_holidays','month_num',array('type' => 'int', 'precision' => 8,'nullable' => False, 'default' => '0'));
		$GLOBALS['phpgw_setup']->oProc->AddColumn('phpgw_cal_holidays','occurence',array('type' => 'int', 'precision' => 8,'nullable' => False, 'default' => '0'));
		$GLOBALS['phpgw_setup']->oProc->AddColumn('phpgw_cal_holidays','dow',array('type' => 'int', 'precision' => 8,'nullable' => False, 'default' => '0'));

		$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.11.008';
		return $GLOBALS['setup_info']['calendar']['currentver'];
	}

	$test[] = '0.9.11.008';
	function calendar_upgrade0_9_11_008()
	{
		$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.11.009';
		return $GLOBALS['setup_info']['calendar']['currentver'];
	}

	$test[] = '0.9.11.009';
	function calendar_upgrade0_9_11_009()
	{
		$GLOBALS['phpgw_setup']->oProc->query('DELETE FROM phpgw_cal_holidays');
		$GLOBALS['phpgw_setup']->oProc->AddColumn('phpgw_cal_holidays','observance_rule',array('type' => 'int', 'precision' => 8,'nullable' => False, 'default' => '0'));

		$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.11.010';
		return $GLOBALS['setup_info']['calendar']['currentver'];
	}

	$test[] = '0.9.11.010';
	function calendar_upgrade0_9_11_010()
	{
		$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.11.011';
		return $GLOBALS['setup_info']['calendar']['currentver'];
	}

	$test[] = '0.9.11.011';
	function calendar_upgrade0_9_11_011()
	{
		$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.13.001';
		return $GLOBALS['setup_info']['calendar']['currentver'];
	}
	$test[] = '0.9.12';
	function calendar_upgrade0_9_12()
	{
		$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.13.001';
		return $GLOBALS['setup_info']['calendar']['currentver'];
	}
	$test[] = '0.9.13.001';
	function calendar_upgrade0_9_13_001()
	{
		$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.13.002';
		return $GLOBALS['setup_info']['calendar']['currentver'];
	}

	$test[] = '0.9.13.002';
	function calendar_upgrade0_9_13_002()
	{
		$GLOBALS['phpgw_setup']->oProc->AddColumn('phpgw_cal','reference',array('type' => 'int', 'precision' => 8,'nullable' => False, 'default' => '0'));

		$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.13.003';
		return $GLOBALS['setup_info']['calendar']['currentver'];
	}

	$test[] = '0.9.13.003';
	function calendar_upgrade0_9_13_003()
	{
		$GLOBALS['phpgw_setup']->oProc->CreateTable('phpgw_cal_alarm',
			Array(
				'fd' => array(
					'alarm_id' => array('type' => 'auto','nullable' => False),		
					'cal_id'   => array('type' => 'int', 'precision' => 8, 'nullable' => False),
					'cal_owner'	=> array('type' => 'int', 'precision' => 8, 'nullable' => False),
					'cal_time' => array('type' => 'int', 'precision' => 8, 'nullable' => False),
					'cal_text' => array('type' => 'varchar', 'precision' => 50, 'nullable' => False)
				),
				'pk' => array('alarm_id'),
				'fk' => array(),
				'ix' => array(),
				'uc' => array()
			)
		);

		$GLOBALS['phpgw_setup']->oProc->AddColumn('phpgw_cal','uid',array('type' => 'varchar', 'precision' => 255,'nullable' => False));
		$GLOBALS['phpgw_setup']->oProc->AddColumn('phpgw_cal','location',array('type' => 'varchar', 'precision' => 255,'nullable' => True));

		$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.13.004';
		return $GLOBALS['setup_info']['calendar']['currentver'];
	}

	$test[] = '0.9.13.004';
	function calendar_upgrade0_9_13_004()
	{
		$GLOBALS['phpgw_setup']->oProc->AddColumn('phpgw_cal_alarm','alarm_enabled',array('type' => 'int', 'precision' => 4,'nullable' => False, 'default' => '1'));

		$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.13.005';
		return $GLOBALS['setup_info']['calendar']['currentver'];
	}

	$test[] = '0.9.13.005';
	function calendar_upgrade0_9_13_005()
	{
		$calendar_data = Array();
		$GLOBALS['phpgw_setup']->oProc->query('SELECT cal_id, category FROM phpgw_cal',__LINE__,__FILE__);
		while($GLOBALS['phpgw_setup']->oProc->next_record())
		{
			$calendar_data[$GLOBALS['phpgw_setup']->oProc->f('cal_id')] = $GLOBALS['phpgw_setup']->oProc->f('category');
		}

		$GLOBALS['phpgw_setup']->oProc->AlterColumn('phpgw_cal','category',array('type' => 'varchar', 'precision' => 30,'nullable' => True));

		@reset($calendar_data);
		while($calendar_data && list($cal_id,$category) = each($calendar_data))
		{
			$GLOBALS['phpgw_setup']->oProc->query("UPDATE phpgw_cal SET category='".$category."' WHERE cal_id=".$cal_id,__LINE__,__FILE__);		
		}
		$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.13.006';
		return $GLOBALS['setup_info']['calendar']['currentver'];
	}

	$test[] = '0.9.13.006';
	function calendar_upgrade0_9_13_006()
	{
		$GLOBALS['phpgw_setup']->oProc->AddColumn('phpgw_cal_repeats','recur_exception',array('type' => 'varchar', 'precision' => 255, 'nullable' => True, 'default' => ''));

		$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.13.007';
		return $GLOBALS['setup_info']['calendar']['currentver'];
	}


	$test[] = '0.9.13.007';
	function calendar_upgrade0_9_13_007()
	{
		$GLOBALS['phpgw_setup']->oProc->AddColumn('phpgw_cal_user','cal_type',array(
			'type' => 'varchar',
			'precision' => '1',
			'nullable' => False,
			'default' => ''
		));

		$GLOBALS['phpgw_setup']->oProc->CreateTable('phpgw_cal_extra',array(
			'fd' => array(
				'cal_id' => array('type' => 'int','precision' => '4','nullable' => False),
				'cal_extra_name' => array('type' => 'varchar','precision' => '40','nullable' => False),
				'cal_extra_value' => array('type' => 'varchar','precision' => '255','nullable' => False,'default' => '')
			),
			'pk' => array('cal_id','cal_extra_name'),
			'fk' => array(),
			'ix' => array(),
			'uc' => array()
		));

		$GLOBALS['phpgw_setup']->oProc->DropTable('phpgw_cal_alarm');

		$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.16.001';
		return $GLOBALS['setup_info']['calendar']['currentver'];
	}
?>
