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
		global $phpgw_setup, $phpgw_setup;

		$phpgw_setup->oProc->query("select distinct($field) from $table");
		if ($phpgw_setup->oProc->num_rows())
		{
			while ($phpgw_setup->oProc->next_record())
			{
				$owner[count($owner)] = $phpgw_setup->oProc->f($field);
			}
			if($phpgw_setup->alessthanb($setup_info['phpgwapi']['currentver'],'0.9.10pre4'))
			{
				$acctstbl = 'accounts';
			}
			else
			{
				$acctstbl = 'phpgw_accounts';
			}
			for($i=0;$i<count($owner);$i++)
			{
				$phpgw_setup->oProc->query("SELECT account_id FROM $acctstbl WHERE account_lid='".$owner[$i]."'");
				$phpgw_setup->oProc->next_record();
				$phpgw_setup->oProc->query("UPDATE $table SET $field=".$phpgw_setup->oProc->f("account_id")." WHERE $field='".$owner[$i]."'");
			}
		}
		$phpgw_setup->oProc->AlterColumn($table, $field, array('type' => 'int', 'precision' => 4, 'nullable' => false, 'default' => 0));
	}


	$test[] = '0.9.3pre1';
	function calendar_upgrade0_9_3pre1()
	{
		global $setup_info;
		calendar_v0_9_2to0_9_3update_owner('webcal_entry','cal_create_by');
		calendar_v0_9_2to0_9_3update_owner('webcal_entry_user','cal_login');
		$setup_info['calendar']['currentver'] = '0.9.3pre2';
		return $setup_info['calendar']['currentver'];
	}
	$test[] = "0.9.3pre2";
	function calendar_upgrade0_9_3pre2()
	{
		global $setup_info;
		$setup_info['calendar']['currentver'] = '0.9.3pre3';
		return $setup_info['calendar']['currentver'];
	}
	$test[] = "0.9.3pre3";
	function calendar_upgrade0_9_3pre3()
	{
		global $setup_info;
		$setup_info['calendar']['currentver'] = '0.9.3pre4';
		return $setup_info['calendar']['currentver'];
	}
	$test[] = "0.9.3pre4";
	function calendar_upgrade0_9_3pre4()
	{
		global $setup_info;
		$setup_info['calendar']['currentver'] = '0.9.3pre5';
		return $setup_info['calendar']['currentver'];
	}
	$test[] = "0.9.3pre5";
	function calendar_upgrade0_9_3pre5()
	{
		global $setup_info;
		$setup_info['calendar']['currentver'] = '0.9.3pre6';
		return $setup_info['calendar']['currentver'];
	}
	$test[] = "0.9.3pre6";
	function calendar_upgrade0_9_3pre6()
	{
		global $setup_info;
		$setup_info['calendar']['currentver'] = '0.9.3pre7';
		return $setup_info['calendar']['currentver'];
	}
	$test[] = "0.9.3pre7";
	function calendar_upgrade0_9_3pre7()
	{
		global $setup_info;
		$setup_info['calendar']['currentver'] = '0.9.3pre8';
		return $setup_info['calendar']['currentver'];
	}
	$test[] = "0.9.3pre8";
	function calendar_upgrade0_9_3pre8()
	{
		global $setup_info;
		$setup_info['calendar']['currentver'] = '0.9.3pre9';
		return $setup_info['calendar']['currentver'];
	}
	$test[] = "0.9.3pre9";
	function calendar_upgrade0_9_3pre9()
	{
		global $setup_info;
		$setup_info['calendar']['currentver'] = '0.9.3pre10';
		return $setup_info['calendar']['currentver'];
	}
	$test[] = "0.9.3pre10";
	function calendar_upgrade0_9_3pre10()
	{
		global $setup_info;
		$setup_info['calendar']['currentver'] = '0.9.3';
		return $setup_info['calendar']['currentver'];
	}
	$test[] = "0.9.3";
	function calendar_upgrade0_9_3()
	{
		global $setup_info;
		$setup_info['calendar']['currentver'] = '0.9.4pre1';
		return $setup_info['calendar']['currentver'];
	}
	$test[] = "0.9.4pre1";
	function calendar_upgrade0_9_4pre1()
	{
		global $setup_info;
		$setup_info['calendar']['currentver'] = '0.9.4pre2';
		return $setup_info['calendar']['currentver'];
	}

	$test[] = '0.9.4pre2';
	function calendar_upgrade0_9_4pre2()
	{
		global $setup_info, $phpgw_setup;

		$phpgw_setup->oProc->RenameColumn('webcal_entry', 'cal_create_by', 'cal_owner');
		$phpgw_setup->oProc->AlterColumn('webcal_entry', 'cal_owner', array('type' => 'int', 'precision' => 4, 'nullable' => false));
		$setup_info['calendar']['currentver'] = '0.9.4pre3';
		return $setup_info['calendar']['currentver'];
	}

	$test[] = "0.9.4pre3";
	function calendar_upgrade0_9_4pre3()
	{
		global $setup_info;
		$setup_info['calendar']['currentver'] = '0.9.4pre4';
		return $setup_info['calendar']['currentver'];
	}
	$test[] = "0.9.4pre4";
	function calendar_upgrade0_9_4pre4()
	{
		global $setup_info;
		$setup_info['calendar']['currentver'] = '0.9.4pre5';
		return $setup_info['calendar']['currentver'];
	}
	$test[] = "0.9.4pre5";
	function calendar_upgrade0_9_4pre5()
	{
		global $setup_info;
		$setup_info['calendar']['currentver'] = '0.9.4';
		return $setup_info['calendar']['currentver'];
	}
	$test[] = "0.9.4";
	function calendar_upgrade0_9_4()
	{
		global $setup_info;
		$setup_info['calendar']['currentver'] = '0.9.5pre1';
		return $setup_info['calendar']['currentver'];
	}
	$test[] = "0.9.5pre1";
	function calendar_upgrade0_9_5pre1()
	{
		global $setup_info;
		$setup_info['calendar']['currentver'] = '0.9.5pre2';
		return $setup_info['calendar']['currentver'];
	}
	$test[] = "0.9.5pre2";
	function calendar_upgrade0_9_5pre2()
	{
		global $setup_info;
		$setup_info['calendar']['currentver'] = '0.9.5pre3';
		return $setup_info['calendar']['currentver'];
	}
	$test[] = "0.9.5";
	function calendar_upgrade0_9_5()
	{
		global $setup_info;
		$setup_info['calendar']['currentver'] = '0.9.6';
		return $setup_info['calendar']['currentver'];
	}
	$test[] = "0.9.6";
	function calendar_upgrade0_9_6()
	{
		global $setup_info;
		$setup_info['calendar']['currentver'] = '0.9.7pre1';
		return $setup_info['calendar']['currentver'];
	}

	$test[] = '0.9.7pre1';
	function calendar_upgrade0_9_7pre1()
	{
		global $setup_info, $phpgw_setup;

		$db2 = $phpgw_setup->db;

		if($phpgw_setup->alessthanb($setup_info['phpgwapi']['currentver'],'0.9.10pre8'))
		{
			$appstable = 'applications';
		}
		else
		{
			$appstable = 'phpgw_applications';
		}

		$phpgw_setup->oProc->CreateTable('calendar_entry', array(
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
		));
	
		$phpgw_setup->oProc->query('SELECT count(*) FROM webcal_entry',__LINE__,__FILE__);
		$phpgw_setup->oProc->next_record();
		if($phpgw_setup->oProc->f(0))
		{
			$phpgw_setup->oProc->query('SELECT cal_id,cal_owner,cal_duration,cal_priority,cal_type,cal_access,cal_name,cal_description,cal_id,cal_date,cal_time,cal_mod_date,cal_mod_time FROM webcal_entry ORDER BY cal_id',__LINE__,__FILE__);
			while($phpgw_setup->oProc->next_record())
			{
				$cal_id = $phpgw_setup->oProc->f('cal_id');
				$cal_owner = $phpgw_setup->oProc->f('cal_owner');
				$cal_duration = $phpgw_setup->oProc->f('cal_duration');
				$cal_priority = $phpgw_setup->oProc->f('cal_priority');
				$cal_type = $phpgw_setup->oProc->f('cal_type');
				$cal_access = $phpgw_setup->oProc->f('cal_access');
				$cal_name = $phpgw_setup->oProc->f('cal_name');
				$cal_description = $phpgw_setup->oProc->f('cal_description');
				$datetime = mktime(intval(strrev(substr(strrev($phpgw_setup->oProc->f('cal_time')),4))),intval(strrev(substr(strrev($phpgw_setup->oProc->f('cal_time')),2,2))),intval(strrev(substr(strrev($phpgw_setup->oProc->f('cal_time')),0,2))),intval(substr($phpgw_setup->oProc->f('cal_date'),4,2)),intval(substr($phpgw_setup->oProc->f('cal_date'),6,2)),intval(substr($phpgw_setup->oProc->f('cal_date'),0,4)));
				$moddatetime = mktime(intval(strrev(substr(strrev($phpgw_setup->oProc->f('cal_mod_time')),4))),intval(strrev(substr(strrev($phpgw_setup->oProc->f('cal_mod_time')),2,2))),intval(strrev(substr(strrev($phpgw_setup->oProc->f('cal_mod_time')),0,2))),intval(substr($phpgw_setup->oProc->f('cal_mod_date'),4,2)),intval(substr($phpgw_setup->oProc->f('cal_mod_date'),6,2)),intval(substr($phpgw_setup->oProc->f('cal_mod_date'),0,4)));
				$db2->query('SELECT groups FROM webcal_entry_groups WHERE cal_id='.$cal_id,__LINE__,__FILE__);
				$db2->next_record();
				$cal_group = $db2->f('groups');
				$db2->query('INSERT INTO calendar_entry(cal_id,cal_owner,cal_group,cal_datetime,cal_mdatetime,cal_duration,cal_priority,cal_type,cal_access,cal_name,cal_description) '
					.'VALUES('.$cal_id.",'".$cal_owner."','".$cal_group."',".$datetime.",".$moddatetime.",".$cal_duration.",".$cal_priority.",'".$cal_type."','".$cal_access."','".$cal_name."','".$cal_description."')",__LINE__,__FILE__);
			}
		}
	
		$phpgw_setup->oProc->DropTable('webcal_entry_groups');
		$phpgw_setup->oProc->DropTable('webcal_entry');
	
		$phpgw_setup->oProc->CreateTable('calendar_entry_user', array(
			'fd' => array(
				'cal_id' => array('type' => 'int', 'precision' => 4, 'nullable' => false, 'default' => '0'),
				'cal_login' => array('type' => 'int', 'precision' => 4, 'nullable' => false, 'default' => '0'),
				'cal_status' => array('type' => 'char', 'precision' => 1, 'default' => 'A')
			),
			'pk' => array('cal_id', 'cal_login'),
			'ix' => array(),
			'fk' => array(),
			'uc' => array()
		));
	
		$phpgw_setup->oProc->query('SELECT count(*) FROM webcal_entry_user',__LINE__,__FILE__);
		$phpgw_setup->oProc->next_record();
		if($phpgw_setup->oProc->f(0))
		{
			$phpgw_setup->oProc->query('SELECT cal_id,cal_login,cal_status FROM webcal_entry_user ORDER BY cal_id',__LINE__,__FILE__);
			while($phpgw_setup->oProc->next_record())
			{
				$cal_id = $phpgw_setup->oProc->f('cal_id');
				$cal_login = $phpgw_setup->oProc->f('cal_login');
				$cal_status = $phpgw_setup->oProc->f('cal_status');
				$db2->query('INSERT INTO calendar_entry_user(cal_id,cal_login,cal_status) VALUES('.$cal_id.','.$cal_login.",'".$cal_status."')",__LINE__,__FILE__);
			}
		}
	
		$phpgw_setup->oProc->DropTable('webcal_entry_user');
	
		$phpgw_setup->oProc->CreateTable('calendar_entry_repeats', array(
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
		));
	
		$phpgw_setup->oProc->query('SELECT count(*) FROM webcal_entry_repeats',__LINE__,__FILE__);
		$phpgw_setup->oProc->next_record();
		if($phpgw_setup->oProc->f(0))
		{
			$phpgw_setup->oProc->query('SELECT cal_id,cal_type,cal_end,cal_frequency,cal_days FROM webcal_entry_repeats ORDER BY cal_id',__LINE__,__FILE__);
			while($phpgw_setup->oProc->next_record())
			{
				$cal_id = $phpgw_setup->oProc->f('cal_id');
				$cal_type = $phpgw_setup->oProc->f('cal_type');
				if(isset($phpgw_setup->oProc->Record['cal_end']))
				{
					$enddate = mktime(0,0,0,intval(substr($phpgw_setup->oProc->f('cal_end'),4,2)),intval(substr($phpgw_setup->oProc->f('cal_end'),6,2)),intval(substr($phpgw_setup->oProc->f('cal_end'),0,4)));
					$useend = 1;
				}
				else
				{
					$enddate = 0;
					$useend = 0;
				}
				$cal_frequency = $phpgw_setup->oProc->f('cal_frequency');
				$cal_days = $phpgw_setup->oProc->f('cal_days');
				$db2->query('INSERT INTO calendar_entry_repeats(cal_id,cal_type,cal_use_end,cal_end,cal_frequency,cal_days) VALUES('.$cal_id.",'".$cal_type."',".$useend.",".$enddate.",".$cal_frequency.",'".$cal_days."')",__LINE__,__FILE__);
			}
		}
	
		$phpgw_setup->oProc->DropTable('webcal_entry_repeats');
		$phpgw_setup->oProc->query("UPDATE $appstable SET app_tables='calendar_entry,calendar_entry_user,calendar_entry_repeats' WHERE app_name='calendar'",__LINE__,__FILE__);
	
		$setup_info['calendar']['currentver'] = '0.9.7pre2';
		return $setup_info['calendar']['currentver'];
	}

	$test[] = "0.9.7pre2";
	function calendar_upgrade0_9_7pre2()
	{
		global $oldversion, $setup_info, $phpgw_setup, $oDelta;

		$db2 = $phpgw_setup->db;
	
		$phpgw_setup->oProc->RenameColumn('calendar_entry', 'cal_duration', 'cal_edatetime');
		$phpgw_setup->oProc->query('SELECT cal_id,cal_datetime,cal_owner,cal_edatetime,cal_mdatetime FROM calendar_entry ORDER BY cal_id',__LINE__,__FILE__);
		if($phpgw_setup->oProc->num_rows())
		{
			while($phpgw_setup->oProc->next_record())
			{
				$db2->query("SELECT preference_value FROM preferences WHERE preference_name='tz_offset' AND preference_appname='common' AND preference_owner=".$phpgw_setup->db->f('cal_owner'),__LINE__,__FILE__);
				$db2->next_record();
				$tz = $db2->f('preference_value');
				$cal_id = $phpgw_setup->oProc->f('cal_id');
				$datetime = $phpgw_setup->oProc->f("cal_datetime") - ((60 * 60) * $tz);
				$mdatetime = $phpgw_setup->oProc->f("cal_mdatetime") - ((60 * 60) * $tz);
				$edatetime = $datetime + (60 * $phpgw_setup->oProc->f("cal_edatetime"));
				$db2->query("UPDATE calendar_entry SET cal_datetime=".$datetime.", cal_edatetime=".$edatetime.", cal_mdatetime=".$mdatetime." WHERE cal_id=".$cal_id,__LINE__,__FILE__);
			}
		}
	
		$setup_info['calendar']['currentver'] = '0.9.7pre3';
		return $setup_info['calendar']['currentver'];
	}

	$test[] = "0.9.7pre3";
	function calendar_upgrade0_9_7pre3()
	{
		global $setup_info;
		$setup_info['calendar']['currentver'] = '0.9.7';
		return $setup_info['calendar']['currentver'];
	}
	$test[] = "0.9.7";
	function calendar_upgrade0_9_7()
	{
		global $setup_info;
		$setup_info['calendar']['currentver'] = '0.9.8pre1';
		return $setup_info['calendar']['currentver'];
	}
	$test[] = "0.9.8pre1";
	function calendar_upgrade0_9_8pre1()
	{
		global $setup_info;
		$setup_info['calendar']['currentver'] = '0.9.8pre2';
		return $setup_info['calendar']['currentver'];
	}
	$test[] = "0.9.8pre2";
	function calendar_upgrade0_9_8pre2()
	{
		global $setup_info;
		$setup_info['calendar']['currentver'] = '0.9.8pre3';
		return $setup_info['calendar']['currentver'];
	}
	$test[] = "0.9.8pre3";
	function calendar_upgrade0_9_8pre3()
	{
		global $setup_info;
		$setup_info['calendar']['currentver'] = '0.9.8pre4';
		return $setup_info['calendar']['currentver'];
	}
	$test[] = "0.9.8pre4";
	function calendar_upgrade0_9_8pre4()
	{
		global $setup_info;
		$setup_info['calendar']['currentver'] = '0.9.8pre5';
		return $setup_info['calendar']['currentver'];
	}
	$test[] = '0.9.8pre5';
	function calendar_upgrade0_9_8pre5()
	{
		global $setup_info;
		$setup_info['calendar']['currentver'] = '0.9.9pre1';
		return $setup_info['calendar']['currentver'];
	}
	$test[] = "0.9.9pre1";
	function calendar_upgrade0_9_9pre1()
	{
		global $setup_info;
		$setup_info['calendar']['currentver'] = '0.9.9';
		return $setup_info['calendar']['currentver'];
	}
	$test[] = "0.9.9";
	function calendar_upgrade0_9_9()
	{
		global $setup_info;
		$setup_info['calendar']['currentver'] = '0.9.10pre1';
		return $setup_info['calendar']['currentver'];
	}
	$test[] = "0.9.10pre1";
	function calendar_upgrade0_9_10pre1()
	{
		global $setup_info;
		$setup_info['calendar']['currentver'] = '0.9.10pre2';
		return $setup_info['calendar']['currentver'];
	}
	$test[] = "0.9.10pre2";
	function calendar_upgrade0_9_10pre2()
	{
		global $setup_info;
		$setup_info['calendar']['currentver'] = '0.9.10pre3';
		return $setup_info['calendar']['currentver'];
	}
	$test[] = "0.9.10pre3";
	function calendar_upgrade0_9_10pre3()
	{
		global $setup_info;
		$setup_info['calendar']['currentver'] = '0.9.10pre4';
		return $setup_info['calendar']['currentver'];
	}
	$test[] = "0.9.10pre4";
	function calendar_upgrade0_9_10pre4()
	{
		global $setup_info;
		$setup_info['calendar']['currentver'] = '0.9.10pre5';
		return $setup_info['calendar']['currentver'];
	}
	$test[] = "0.9.10pre5";
	function calendar_upgrade0_9_10pre5()
	{
		global $setup_info;
		$setup_info['calendar']['currentver'] = '0.9.10pre6';
		return $setup_info['calendar']['currentver'];
	}
	$test[] = "0.9.10pre6";
	function calendar_upgrade0_9_10pre6()
	{
		global $setup_info;
		$setup_info['calendar']['currentver'] = '0.9.10pre7';
		return $setup_info['calendar']['currentver'];
	}
	$test[] = "0.9.10pre7";
	function calendar_upgrade0_9_10pre7()
	{
		global $setup_info;
		$setup_info['calendar']['currentver'] = '0.9.10pre8';
		return $setup_info['calendar']['currentver'];
	}
	$test[] = "0.9.10pre8";
	function calendar_upgrade0_9_10pre8()
	{
		global $setup_info;
		$setup_info['calendar']['currentver'] = '0.9.10pre9';
		return $setup_info['calendar']['currentver'];
	}
	$test[] = '0.9.10pre9';
	function calendar_upgrade0_9_10pre9()
	{
		global $setup_info;
		$setup_info['calendar']['currentver'] = '0.9.10pre10';
		return $setup_info['calendar']['currentver'];
	}
	$test[] = '0.9.10pre10';
	function calendar_upgrade0_9_10pre10()
	{
		global $setup_info;
		$setup_info['calendar']['currentver'] = '0.9.10pre11';
		return $setup_info['calendar']['currentver'];
	}
	$test[] = '0.9.10pre11';
	function calendar_upgrade0_9_10pre11()
	{
		global $setup_info;
		$setup_info['calendar']['currentver'] = '0.9.10pre12';
		return $setup_info['calendar']['currentver'];
	}
	$test[] = '0.9.10pre12';
	function calendar_upgrade0_9_10pre12()
	{
		global $setup_info;
		$setup_info['calendar']['currentver'] = '0.9.10pre13';
		return $setup_info['calendar']['currentver'];
	}
	$test[] = '0.9.10pre13';
	function calendar_upgrade0_9_10pre13()
	{
		global $setup_info;
		$setup_info['calendar']['currentver'] = '0.9.10pre14';
		return $setup_info['calendar']['currentver'];
	}
	$test[] = '0.9.10pre14';
	function calendar_upgrade0_9_10pre14()
	{
		global $setup_info;
		$setup_info['calendar']['currentver'] = '0.9.10pre15';
		return $setup_info['calendar']['currentver'];
	}
	$test[] = '0.9.10pre15';
	function calendar_upgrade0_9_10pre15()
	{
		global $setup_info;
		$setup_info['calendar']['currentver'] = '0.9.10pre16';
		return $setup_info['calendar']['currentver'];
	}
	$test[] = '0.9.10pre16';
	function calendar_upgrade0_9_10pre16()
	{
		global $setup_info;
		$setup_info['calendar']['currentver'] = '0.9.10pre17';
		return $setup_info['calendar']['currentver'];
	}
	$test[] = '0.9.10pre17';
	function calendar_upgrade0_9_10pre17()
	{
		global $setup_info;
		$setup_info['calendar']['currentver'] = '0.9.10pre18';
		return $setup_info['calendar']['currentver'];
	}
	$test[] = '0.9.10pre18';
	function calendar_upgrade0_9_10pre18()
	{
		global $setup_info;
		$setup_info['calendar']['currentver'] = '0.9.10pre19';
		return $setup_info['calendar']['currentver'];
	}
	$test[] = '0.9.10pre19';
	function calendar_upgrade0_9_10pre19()
	{
		global $setup_info;
		$setup_info['calendar']['currentver'] = '0.9.10pre20';
		return $setup_info['calendar']['currentver'];
	}
	$test[] = '0.9.10pre20';
	function calendar_upgrade0_9_10pre20()
	{
		global $setup_info;
		$setup_info['calendar']['currentver'] = '0.9.10pre21';
		return $setup_info['calendar']['currentver'];
	}
	$test[] = '0.9.10pre21';
	function calendar_upgrade0_9_10pre21()
	{
		global $setup_info;
		$setup_info['calendar']['currentver'] = '0.9.10pre22';
		return $setup_info['calendar']['currentver'];
	}
	$test[] = '0.9.10pre22';
	function calendar_upgrade0_9_10pre22()
	{
		global $setup_info;
		$setup_info['calendar']['currentver'] = '0.9.10pre23';
		return $setup_info['calendar']['currentver'];
	}
	$test[] = '0.9.10pre23';
	function calendar_upgrade0_9_10pre23()
	{
		global $setup_info;
		$setup_info['calendar']['currentver'] = '0.9.10pre24';
		return $setup_info['calendar']['currentver'];
	}
	$test[] = '0.9.10pre24';
	function calendar_upgrade0_9_10pre24()
	{
		global $setup_info;
		$setup_info['calendar']['currentver'] = '0.9.10pre25';
		return $setup_info['calendar']['currentver'];
	}
	$test[] = '0.9.10pre25';
	function calendar_upgrade0_9_10pre25()
	{
		global $setup_info;
		$setup_info['calendar']['currentver'] = '0.9.10pre26';
		return $setup_info['calendar']['currentver'];
	}
	$test[] = '0.9.10pre26';
	function calendar_upgrade0_9_10pre26()
	{
		global $setup_info;
		$setup_info['calendar']['currentver'] = '0.9.10pre27';
		return $setup_info['calendar']['currentver'];
	}
	$test[] = '0.9.10pre27';
	function calendar_upgrade0_9_10pre27()
	{
		global $setup_info;
		$setup_info['calendar']['currentver'] = '0.9.10pre28';
		return $setup_info['calendar']['currentver'];
	}
	$test[] = '0.9.10pre28';
	function calendar_upgrade0_9_10pre28()
	{
		global $setup_info;
		$setup_info['calendar']['currentver'] = '0.9.10';
		return $setup_info['calendar']['currentver'];
	}
	$test[] = '0.9.10';
	function calendar_upgrade0_9_10()
	{
		global $setup_info;
		$setup_info['calendar']['currentver'] = '0.9.11.001';
		return $setup_info['calendar']['currentver'];
	}
	$test[] = '0.9.11';
	function calendar_upgrade0_9_11()
	{
		global $setup_info;
		$setup_info['calendar']['currentver'] = '0.9.11.001';
		return $setup_info['calendar']['currentver'];
	}

	$test[] = '0.9.11.001';
	function calendar_upgrade0_9_11_001()
	{
		global $setup_info, $phpgw_setup;

		$db2 = $phpgw_setup->db;

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
		$phpgw_setup->oProc->CreateTable(
			'phpgw_cal', array(
				'fd' => array(
					'cal_id' => array('type' => 'auto', 'nullable' => False),
					'owner' => array('type' => 'int', 'precision' => 8, 'nullable' => False),
					'category' => array('type' => 'int', 'precision' => 8, 'default' => '0', 'nullable' => False),
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

		$phpgw_setup->oProc->query('SELECT * FROM calendar_entry',__LINE__,__FILE__);
		while($phpgw_setup->oProc->next_record())
		{
			$id = $phpgw_setup->oProc->f('cal_id');
			$owner = $phpgw_setup->oProc->f('cal_owner');
			$access = $phpgw_setup->oProc->f('cal_access');
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
			$groups = $phpgw_setup->oProc->f('cal_group');
			$datetime = $phpgw_setup->oProc->f('cal_datetime');
			$mdatetime = $phpgw_setup->oProc->f('cal_mdatetime');
			$edatetime = $phpgw_setup->oProc->f('cal_edatetime');
			$priority = $phpgw_setup->oProc->f('cal_priority');
			$type = $phpgw_setup->oProc->f('cal_type');
			$title = $phpgw_setup->oProc->f('cal_name');
			$description = $phpgw_setup->oProc->f('cal_description');

			$db2->query("INSERT INTO phpgw_cal(cal_id,owner,groups,datetime,mdatetime,edatetime,priority,cal_type,is_public,title,description) "
				. "VALUES($id,$owner,'$groups',$datetime,$mdatetime,$edatetime,$priority,'$type',$is_public,'$title','$description')",__LINE__,__FILE__);
		}
		$phpgw_setup->oProc->DropTable('calendar_entry');

// calendar_entry_repeats => phpgw_cal_repeats
		$phpgw_setup->oProc->CreateTable('phpgw_cal_repeats', array(
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
		));
		$phpgw_setup->oProc->query('SELECT * FROM calendar_entry_repeats',__LINE__,__FILE__);
		while($phpgw_setup->oProc->next_record())
		{
			$id = $phpgw_setup->oProc->f('cal_id');
			$recur_type = $phpgw_setup->oProc->f('cal_type');
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
			$recur_end_use = $phpgw_setup->oProc->f('cal_use_end');
			$recur_end = $phpgw_setup->oProc->f('cal_end');
			$recur_interval = $phpgw_setup->oProc->f('cal_frequency');
			$days = strtoupper($phpgw_setup->oProc->f('cal_days'));
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
		$phpgw_setup->oProc->DropTable('calendar_entry_repeats');

// calendar_entry_user => phpgw_cal_user
		$phpgw_setup->oProc->RenameTable('calendar_entry_user','phpgw_cal_user');

		$setup_info['calendar']['currentver'] = '0.9.11.002';
		return $setup_info['calendar']['currentver'];
	}

	$test[] = '0.9.11.002';
	function calendar_upgrade0_9_11_002()
	{
		global $setup_info;
		$setup_info['calendar']['currentver'] = '0.9.11.003';
		return $setup_info['calendar']['currentver'];
	}

	$test[] = '0.9.11.003';
	function calendar_upgrade0_9_11_003()
	{
		global $setup_info, $phpgw_setup;

		$phpgw_setup->oProc->CreateTable(
			'phpgw_cal_holidays', array(
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

		$setup_info['calendar']['currentver'] = '0.9.11.004';
		return $setup_info['calendar']['currentver'];
	}

	$test[] = '0.9.11.004';
	function calendar_upgrade0_9_11_004()
	{
		global $setup_info;
		$setup_info['calendar']['currentver'] = '0.9.11.005';
		return $setup_info['calendar']['currentver'];
	}

	$test[] = '0.9.11.005';
	function calendar_upgrade0_9_11_005()
	{
		global $setup_info;
		$setup_info['calendar']['currentver'] = '0.9.11.006';
		return $setup_info['calendar']['currentver'];
	}

	$test[] = '0.9.11.006';
	function calendar_upgrade0_9_11_006()
	{
		global $setup_info, $phpgw_setup;

		$phpgw_setup->oProc->DropTable('phpgw_cal_holidays');
		$phpgw_setup->oProc->CreateTable('phpgw_cal_holidays', array(
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
		));

		$setup_info['calendar']['currentver'] = '0.9.11.007';
		return $setup_info['calendar']['currentver'];
	}

	$test[] = '0.9.11.007';
	function calendar_upgrade0_9_11_007()
	{
		global $setup_info, $phpgw_setup;

		$phpgw_setup->oProc->query('DELETE FROM phpgw_cal_holidays');
		$phpgw_setup->oProc->AddColumn('phpgw_cal_holidays','mday',array('type' => 'int', 'precision' => 8,'nullable' => False, 'default' => '0'));
		$phpgw_setup->oProc->AddColumn('phpgw_cal_holidays','month_num',array('type' => 'int', 'precision' => 8,'nullable' => False, 'default' => '0'));
		$phpgw_setup->oProc->AddColumn('phpgw_cal_holidays','occurence',array('type' => 'int', 'precision' => 8,'nullable' => False, 'default' => '0'));
		$phpgw_setup->oProc->AddColumn('phpgw_cal_holidays','dow',array('type' => 'int', 'precision' => 8,'nullable' => False, 'default' => '0'));

		$setup_info['calendar']['currentver'] = '0.9.11.008';
		return $setup_info['calendar']['currentver'];
	}

	$test[] = '0.9.11.008';
	function calendar_upgrade0_9_11_008()
	{
		global $setup_info, $phpgw_setup;
		$setup_info['calendar']['currentver'] = '0.9.11.009';
		return $setup_info['calendar']['currentver'];
	}

	$test[] = '0.9.11.009';
	function calendar_upgrade0_9_11_009()
	{
		global $setup_info, $phpgw_setup;

		$phpgw_setup->oProc->query('DELETE FROM phpgw_cal_holidays');
		$phpgw_setup->oProc->AddColumn('phpgw_cal_holidays','observance_rule',array('type' => 'int', 'precision' => 8,'nullable' => False, 'default' => '0'));

		$setup_info['calendar']['currentver'] = '0.9.11.010';
		return $setup_info['calendar']['currentver'];
	}

	$test[] = '0.9.11.010';
	function calendar_upgrade0_9_11_010()
	{
		global $setup_info;
		$setup_info['calendar']['currentver'] = '0.9.11.011';
		return $setup_info['calendar']['currentver'];
	}

	$test[] = '0.9.11.011';
	function calendar_upgrade0_9_11_011()
	{
		global $setup_info;
		$setup_info['calendar']['currentver'] = '0.9.13.001';
		return $setup_info['calendar']['currentver'];
	}
	$test[] = '0.9.13.001';
	function calendar_upgrade0_9_13_001()
	{
		global $setup_info;
		$setup_info['calendar']['currentver'] = '0.9.13.002';
		return $setup_info['calendar']['currentver'];
	}

	$test[] = '0.9.13.002';
	function calendar_upgrade0_9_13_002()
	{
		global $setup_info, $phpgw_setup;

		$phpgw_setup->oProc->AddColumn('phpgw_cal','reference',array('type' => 'int', 'precision' => 8,'nullable' => False, 'default' => '0'));

		$setup_info['calendar']['currentver'] = '0.9.13.003';
		return $setup_info['calendar']['currentver'];
	}

?>
