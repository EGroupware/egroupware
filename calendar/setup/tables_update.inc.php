<?php
/**
 * EGroupware - Calendar setup
 *
 * @link http://www.egroupware.org
 * @package calendar
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

function calendar_v0_9_2to0_9_3update_owner($table, $field)
{
	$GLOBALS['egw_setup']->oProc->query("select distinct($field) from $table");
	if ($GLOBALS['egw_setup']->oProc->num_rows())
	{
		while ($GLOBALS['egw_setup']->oProc->next_record())
		{
			$owner[count($owner)] = $GLOBALS['egw_setup']->oProc->f($field);
		}
		if($GLOBALS['egw_setup']->alessthanb($GLOBALS['setup_info']['phpgwapi']['currentver'],'0.9.10pre4'))
		{
			$acctstbl = 'accounts';
		}
		else
		{
			$acctstbl = 'phpgw_accounts';
		}
		for($i=0;$i<count($owner);$i++)
		{
			$GLOBALS['egw_setup']->oProc->query("SELECT account_id FROM $acctstbl WHERE account_lid='".$owner[$i]."'");
			$GLOBALS['egw_setup']->oProc->next_record();
			$GLOBALS['egw_setup']->oProc->query("UPDATE $table SET $field=".$GLOBALS['egw_setup']->oProc->f('account_id')." WHERE $field='".$owner[$i]."'");
		}
	}
	$GLOBALS['egw_setup']->oProc->AlterColumn($table, $field, array('type' => 'int', 'precision' => 4, 'nullable' => false, 'default' => 0));
}


function calendar_upgrade0_9_3pre1()
{
	calendar_v0_9_2to0_9_3update_owner('webcal_entry','cal_create_by');
	calendar_v0_9_2to0_9_3update_owner('webcal_entry_user','cal_login');
	$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.3pre2';
	return $GLOBALS['setup_info']['calendar']['currentver'];
}


function calendar_upgrade0_9_3pre2()
{
	$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.3pre3';
	return $GLOBALS['setup_info']['calendar']['currentver'];
}


function calendar_upgrade0_9_3pre3()
{
	$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.3pre4';
	return $GLOBALS['setup_info']['calendar']['currentver'];
}


function calendar_upgrade0_9_3pre4()
{
	$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.3pre5';
	return $GLOBALS['setup_info']['calendar']['currentver'];
}


function calendar_upgrade0_9_3pre5()
{
	$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.3pre6';
	return $GLOBALS['setup_info']['calendar']['currentver'];
}


function calendar_upgrade0_9_3pre6()
{
	$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.3pre7';
	return $GLOBALS['setup_info']['calendar']['currentver'];
}


function calendar_upgrade0_9_3pre7()
{
	$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.3pre8';
	return $GLOBALS['setup_info']['calendar']['currentver'];
}


function calendar_upgrade0_9_3pre8()
{
	$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.3pre9';
	return $GLOBALS['setup_info']['calendar']['currentver'];
}


function calendar_upgrade0_9_3pre9()
{
	$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.3pre10';
	return $GLOBALS['setup_info']['calendar']['currentver'];
}


function calendar_upgrade0_9_3pre10()
{
	$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.3';
	return $GLOBALS['setup_info']['calendar']['currentver'];
}


function calendar_upgrade0_9_3()
{
	$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.4pre1';
	return $GLOBALS['setup_info']['calendar']['currentver'];
}


function calendar_upgrade0_9_4pre1()
{
	$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.4pre2';
	return $GLOBALS['setup_info']['calendar']['currentver'];
}


function calendar_upgrade0_9_4pre2()
{
	$GLOBALS['egw_setup']->oProc->RenameColumn('webcal_entry', 'cal_create_by', 'cal_owner');
	$GLOBALS['egw_setup']->oProc->AlterColumn('webcal_entry', 'cal_owner', array('type' => 'int', 'precision' => 4, 'nullable' => false));
	$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.4pre3';
	return $GLOBALS['setup_info']['calendar']['currentver'];
}


function calendar_upgrade0_9_4pre3()
{
	$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.4pre4';
	return $GLOBALS['setup_info']['calendar']['currentver'];
}

function calendar_upgrade0_9_4pre4()
{
	$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.4pre5';
	return $GLOBALS['setup_info']['calendar']['currentver'];
}

function calendar_upgrade0_9_4pre5()
{
	$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.4';
	return $GLOBALS['setup_info']['calendar']['currentver'];
}

function calendar_upgrade0_9_4()
{
	$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.5pre1';
	return $GLOBALS['setup_info']['calendar']['currentver'];
}

function calendar_upgrade0_9_5pre1()
{
	$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.5pre2';
	return $GLOBALS['setup_info']['calendar']['currentver'];
}

function calendar_upgrade0_9_5pre2()
{
	$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.5pre3';
	return $GLOBALS['setup_info']['calendar']['currentver'];
}

function calendar_upgrade0_9_5()
{
	$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.6';
	return $GLOBALS['setup_info']['calendar']['currentver'];
}

function calendar_upgrade0_9_6()
{
	$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.7pre1';
	return $GLOBALS['setup_info']['calendar']['currentver'];
}


function calendar_upgrade0_9_7pre1()
{
	$db2 = clone($GLOBALS['egw_setup']->db);

	$GLOBALS['egw_setup']->oProc->CreateTable('calendar_entry',
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

	$GLOBALS['egw_setup']->oProc->query('SELECT count(*) FROM webcal_entry',__LINE__,__FILE__);
	$GLOBALS['egw_setup']->oProc->next_record();
	if($GLOBALS['egw_setup']->oProc->f(0))
	{
		$GLOBALS['egw_setup']->oProc->query('SELECT cal_id,cal_owner,cal_duration,cal_priority,cal_type,cal_access,cal_name,cal_description,cal_id,cal_date,cal_time,cal_mod_date,cal_mod_time FROM webcal_entry ORDER BY cal_id',__LINE__,__FILE__);
		while($GLOBALS['egw_setup']->oProc->next_record())
		{
			$cal_id = $GLOBALS['egw_setup']->oProc->f('cal_id');
			$cal_owner = $GLOBALS['egw_setup']->oProc->f('cal_owner');
			$cal_duration = $GLOBALS['egw_setup']->oProc->f('cal_duration');
			$cal_priority = $GLOBALS['egw_setup']->oProc->f('cal_priority');
			$cal_type = $GLOBALS['egw_setup']->oProc->f('cal_type');
			$cal_access = $GLOBALS['egw_setup']->oProc->f('cal_access');
			$cal_name = $GLOBALS['egw_setup']->oProc->f('cal_name');
			$cal_description = $GLOBALS['egw_setup']->oProc->f('cal_description');
			$datetime = mktime(intval(strrev(substr(strrev($GLOBALS['egw_setup']->oProc->f('cal_time')),4))),intval(strrev(substr(strrev($GLOBALS['egw_setup']->oProc->f('cal_time')),2,2))),intval(strrev(substr(strrev($GLOBALS['egw_setup']->oProc->f('cal_time')),0,2))),intval(substr($GLOBALS['egw_setup']->oProc->f('cal_date'),4,2)),intval(substr($GLOBALS['egw_setup']->oProc->f('cal_date'),6,2)),intval(substr($GLOBALS['egw_setup']->oProc->f('cal_date'),0,4)));
			$moddatetime = mktime(intval(strrev(substr(strrev($GLOBALS['egw_setup']->oProc->f('cal_mod_time')),4))),intval(strrev(substr(strrev($GLOBALS['egw_setup']->oProc->f('cal_mod_time')),2,2))),intval(strrev(substr(strrev($GLOBALS['egw_setup']->oProc->f('cal_mod_time')),0,2))),intval(substr($GLOBALS['egw_setup']->oProc->f('cal_mod_date'),4,2)),intval(substr($GLOBALS['egw_setup']->oProc->f('cal_mod_date'),6,2)),intval(substr($GLOBALS['egw_setup']->oProc->f('cal_mod_date'),0,4)));
			$db2->query('SELECT groups FROM webcal_entry_groups WHERE cal_id='.$cal_id,__LINE__,__FILE__);
			$db2->next_record();
			$cal_group = $db2->f('groups');
			$db2->query('INSERT INTO calendar_entry(cal_id,cal_owner,cal_group,cal_datetime,cal_mdatetime,cal_duration,cal_priority,cal_type,cal_access,cal_name,cal_description) '
				.'VALUES('.$cal_id.",'".$cal_owner."','".$cal_group."',".$datetime.",".$moddatetime.",".$cal_duration.",".$cal_priority.",'".$cal_type."','".$cal_access."','".$cal_name."','".$cal_description."')",__LINE__,__FILE__);
		}
	}

	$GLOBALS['egw_setup']->oProc->DropTable('webcal_entry_groups');
	$GLOBALS['egw_setup']->oProc->DropTable('webcal_entry');

	$GLOBALS['egw_setup']->oProc->CreateTable('calendar_entry_user',
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

	$GLOBALS['egw_setup']->oProc->query('SELECT count(*) FROM webcal_entry_user',__LINE__,__FILE__);
	$GLOBALS['egw_setup']->oProc->next_record();
	if($GLOBALS['egw_setup']->oProc->f(0))
	{
		$GLOBALS['egw_setup']->oProc->query('SELECT cal_id,cal_login,cal_status FROM webcal_entry_user ORDER BY cal_id',__LINE__,__FILE__);
		while($GLOBALS['egw_setup']->oProc->next_record())
		{
			$cal_id = $GLOBALS['egw_setup']->oProc->f('cal_id');
			$cal_login = $GLOBALS['egw_setup']->oProc->f('cal_login');
			$cal_status = $GLOBALS['egw_setup']->oProc->f('cal_status');
			$db2->query('INSERT INTO calendar_entry_user(cal_id,cal_login,cal_status) VALUES('.$cal_id.','.$cal_login.",'".$cal_status."')",__LINE__,__FILE__);
		}
	}

	$GLOBALS['egw_setup']->oProc->DropTable('webcal_entry_user');

	$GLOBALS['egw_setup']->oProc->CreateTable('calendar_entry_repeats',
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

	$GLOBALS['egw_setup']->oProc->query('SELECT count(*) FROM webcal_entry_repeats',__LINE__,__FILE__);
	$GLOBALS['egw_setup']->oProc->next_record();
	if($GLOBALS['egw_setup']->oProc->f(0))
	{
		$GLOBALS['egw_setup']->oProc->query('SELECT cal_id,cal_type,cal_end,cal_frequency,cal_days FROM webcal_entry_repeats ORDER BY cal_id',__LINE__,__FILE__);
		while($GLOBALS['egw_setup']->oProc->next_record())
		{
			$cal_id = $GLOBALS['egw_setup']->oProc->f('cal_id');
			$cal_type = $GLOBALS['egw_setup']->oProc->f('cal_type');
			if(isset($GLOBALS['egw_setup']->oProc->Record['cal_end']))
			{
				$enddate = mktime(0,0,0,intval(substr($GLOBALS['egw_setup']->oProc->f('cal_end'),4,2)),intval(substr($GLOBALS['egw_setup']->oProc->f('cal_end'),6,2)),intval(substr($GLOBALS['egw_setup']->oProc->f('cal_end'),0,4)));
				$useend = 1;
			}
			else
			{
				$enddate = 0;
				$useend = 0;
			}
			$cal_frequency = $GLOBALS['egw_setup']->oProc->f('cal_frequency');
			$cal_days = $GLOBALS['egw_setup']->oProc->f('cal_days');
			$db2->query('INSERT INTO calendar_entry_repeats(cal_id,cal_type,cal_use_end,cal_end,cal_frequency,cal_days) VALUES('.$cal_id.",'".$cal_type."',".$useend.",".$enddate.",".$cal_frequency.",'".$cal_days."')",__LINE__,__FILE__);
		}
	}

	$GLOBALS['egw_setup']->oProc->DropTable('webcal_entry_repeats');
	$GLOBALS['egw_setup']->oProc->query("UPDATE {$GLOBALS['egw_setup']->applications_table} SET app_tables='calendar_entry,calendar_entry_user,calendar_entry_repeats' WHERE app_name='calendar'",__LINE__,__FILE__);

	$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.7pre2';
	return $GLOBALS['setup_info']['calendar']['currentver'];
}


function calendar_upgrade0_9_7pre2()
{
	$db2 = $GLOBALS['egw_setup']->db;

	$GLOBALS['egw_setup']->oProc->RenameColumn('calendar_entry', 'cal_duration', 'cal_edatetime');
	$GLOBALS['egw_setup']->oProc->query('SELECT cal_id,cal_datetime,cal_owner,cal_edatetime,cal_mdatetime FROM calendar_entry ORDER BY cal_id',__LINE__,__FILE__);
	if($GLOBALS['egw_setup']->oProc->num_rows())
	{
		while($GLOBALS['egw_setup']->oProc->next_record())
		{
			$db2->query("SELECT preference_value FROM preferences WHERE preference_name='tz_offset' AND preference_appname='common' AND preference_owner=".$GLOBALS['egw_setup']->db->f('cal_owner'),__LINE__,__FILE__);
			$db2->next_record();
			$tz = $db2->f('preference_value');
			$cal_id = $GLOBALS['egw_setup']->oProc->f('cal_id');
			$datetime = $GLOBALS['egw_setup']->oProc->f('cal_datetime') - ((60 * 60) * $tz);
			$mdatetime = $GLOBALS['egw_setup']->oProc->f('cal_mdatetime') - ((60 * 60) * $tz);
			$edatetime = $datetime + (60 * $GLOBALS['egw_setup']->oProc->f('cal_edatetime'));
			$db2->query('UPDATE calendar_entry SET cal_datetime='.$datetime.', cal_edatetime='.$edatetime.', cal_mdatetime='.$mdatetime.' WHERE cal_id='.$cal_id,__LINE__,__FILE__);
		}
	}

	$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.7pre3';
	return $GLOBALS['setup_info']['calendar']['currentver'];
}


function calendar_upgrade0_9_7pre3()
{
	$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.7';
	return $GLOBALS['setup_info']['calendar']['currentver'];
}

function calendar_upgrade0_9_7()
{
	$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.8pre1';
	return $GLOBALS['setup_info']['calendar']['currentver'];
}

function calendar_upgrade0_9_8pre1()
{
	$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.8pre2';
	return $GLOBALS['setup_info']['calendar']['currentver'];
}

function calendar_upgrade0_9_8pre2()
{
	$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.8pre3';
	return $GLOBALS['setup_info']['calendar']['currentver'];
}

function calendar_upgrade0_9_8pre3()
{
	$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.8pre4';
	return $GLOBALS['setup_info']['calendar']['currentver'];
}

function calendar_upgrade0_9_8pre4()
{
	$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.8pre5';
	return $GLOBALS['setup_info']['calendar']['currentver'];
}

function calendar_upgrade0_9_8pre5()
{
	$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.9pre1';
	return $GLOBALS['setup_info']['calendar']['currentver'];
}

function calendar_upgrade0_9_9pre1()
{
	$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.9';
	return $GLOBALS['setup_info']['calendar']['currentver'];
}

function calendar_upgrade0_9_9()
{
	$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.10pre1';
	return $GLOBALS['setup_info']['calendar']['currentver'];
}

function calendar_upgrade0_9_10pre1()
{
	$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.10pre2';
	return $GLOBALS['setup_info']['calendar']['currentver'];
}

function calendar_upgrade0_9_10pre2()
{
	$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.10pre3';
	return $GLOBALS['setup_info']['calendar']['currentver'];
}

function calendar_upgrade0_9_10pre3()
{
	$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.10pre4';
	return $GLOBALS['setup_info']['calendar']['currentver'];
}

function calendar_upgrade0_9_10pre4()
{
	$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.10pre5';
	return $GLOBALS['setup_info']['calendar']['currentver'];
}

function calendar_upgrade0_9_10pre5()
{
	$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.10pre6';
	return $GLOBALS['setup_info']['calendar']['currentver'];
}

function calendar_upgrade0_9_10pre6()
{
	$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.10pre7';
	return $GLOBALS['setup_info']['calendar']['currentver'];
}

function calendar_upgrade0_9_10pre7()
{
	$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.10pre8';
	return $GLOBALS['setup_info']['calendar']['currentver'];
}

function calendar_upgrade0_9_10pre8()
{
	$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.10pre9';
	return $GLOBALS['setup_info']['calendar']['currentver'];
}

function calendar_upgrade0_9_10pre9()
{
	$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.10pre10';
	return $GLOBALS['setup_info']['calendar']['currentver'];
}

function calendar_upgrade0_9_10pre10()
{
	$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.10pre11';
	return $GLOBALS['setup_info']['calendar']['currentver'];
}

function calendar_upgrade0_9_10pre11()
{
	$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.10pre12';
	return $GLOBALS['setup_info']['calendar']['currentver'];
}

function calendar_upgrade0_9_10pre12()
{
	$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.10pre13';
	return $GLOBALS['setup_info']['calendar']['currentver'];
}

function calendar_upgrade0_9_10pre13()
{
	$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.10pre14';
	return $GLOBALS['setup_info']['calendar']['currentver'];
}

function calendar_upgrade0_9_10pre14()
{
	$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.10pre15';
	return $GLOBALS['setup_info']['calendar']['currentver'];
}

function calendar_upgrade0_9_10pre15()
{
	$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.10pre16';
	return $GLOBALS['setup_info']['calendar']['currentver'];
}

function calendar_upgrade0_9_10pre16()
{
	$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.10pre17';
	return $GLOBALS['setup_info']['calendar']['currentver'];
}

function calendar_upgrade0_9_10pre17()
{
	$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.10pre18';
	return $GLOBALS['setup_info']['calendar']['currentver'];
}

function calendar_upgrade0_9_10pre18()
{
	$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.10pre19';
	return $GLOBALS['setup_info']['calendar']['currentver'];
}

function calendar_upgrade0_9_10pre19()
{
	$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.10pre20';
	return $GLOBALS['setup_info']['calendar']['currentver'];
}

function calendar_upgrade0_9_10pre20()
{
	$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.10pre21';
	return $GLOBALS['setup_info']['calendar']['currentver'];
}

function calendar_upgrade0_9_10pre21()
{
	$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.10pre22';
	return $GLOBALS['setup_info']['calendar']['currentver'];
}

function calendar_upgrade0_9_10pre22()
{
	$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.10pre23';
	return $GLOBALS['setup_info']['calendar']['currentver'];
}

function calendar_upgrade0_9_10pre23()
{
	$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.10pre24';
	return $GLOBALS['setup_info']['calendar']['currentver'];
}

function calendar_upgrade0_9_10pre24()
{
	$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.10pre25';
	return $GLOBALS['setup_info']['calendar']['currentver'];
}

function calendar_upgrade0_9_10pre25()
{
	$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.10pre26';
	return $GLOBALS['setup_info']['calendar']['currentver'];
}

function calendar_upgrade0_9_10pre26()
{
	$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.10pre27';
	return $GLOBALS['setup_info']['calendar']['currentver'];
}

function calendar_upgrade0_9_10pre27()
{
	$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.10pre28';
	return $GLOBALS['setup_info']['calendar']['currentver'];
}

function calendar_upgrade0_9_10pre28()
{
	$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.10';
	return $GLOBALS['setup_info']['calendar']['currentver'];
}

function calendar_upgrade0_9_10()
{
	$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.11.001';
	return $GLOBALS['setup_info']['calendar']['currentver'];
}

function calendar_upgrade0_9_11()
{
	$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.11.001';
	return $GLOBALS['setup_info']['calendar']['currentver'];
}


function calendar_upgrade0_9_11_001()
{
	$db2 = $GLOBALS['egw_setup']->db;

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
	$GLOBALS['egw_setup']->oProc->CreateTable('phpgw_cal',
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

	$GLOBALS['egw_setup']->oProc->query('SELECT * FROM calendar_entry',__LINE__,__FILE__);
	while($GLOBALS['egw_setup']->oProc->next_record())
	{
		$id = $GLOBALS['egw_setup']->oProc->f('cal_id');
		$owner = $GLOBALS['egw_setup']->oProc->f('cal_owner');
		$access = $GLOBALS['egw_setup']->oProc->f('cal_access');
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
		$groups = $GLOBALS['egw_setup']->oProc->f('cal_group');
		$datetime = $GLOBALS['egw_setup']->oProc->f('cal_datetime');
		$mdatetime = $GLOBALS['egw_setup']->oProc->f('cal_mdatetime');
		$edatetime = $GLOBALS['egw_setup']->oProc->f('cal_edatetime');
		$priority = $GLOBALS['egw_setup']->oProc->f('cal_priority');
		$type = $GLOBALS['egw_setup']->oProc->f('cal_type');
		$title = $GLOBALS['egw_setup']->oProc->f('cal_name');
		$description = $GLOBALS['egw_setup']->oProc->f('cal_description');

		$db2->query("INSERT INTO phpgw_cal(cal_id,owner,groups,datetime,mdatetime,edatetime,priority,cal_type,is_public,title,description) "
			. "VALUES($id,$owner,'$groups',$datetime,$mdatetime,$edatetime,$priority,'$type',$is_public,'$title','$description')",__LINE__,__FILE__);
	}
	$GLOBALS['egw_setup']->oProc->DropTable('calendar_entry');

// calendar_entry_repeats => phpgw_cal_repeats
	$GLOBALS['egw_setup']->oProc->CreateTable('phpgw_cal_repeats',
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
	$GLOBALS['egw_setup']->oProc->query('SELECT * FROM calendar_entry_repeats',__LINE__,__FILE__);
	while($GLOBALS['egw_setup']->oProc->next_record())
	{
		$id = $GLOBALS['egw_setup']->oProc->f('cal_id');
		$recur_type = $GLOBALS['egw_setup']->oProc->f('cal_type');
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
		$recur_end_use = $GLOBALS['egw_setup']->oProc->f('cal_use_end');
		$recur_end = $GLOBALS['egw_setup']->oProc->f('cal_end');
		$recur_interval = $GLOBALS['egw_setup']->oProc->f('cal_frequency');
		$days = strtoupper($GLOBALS['egw_setup']->oProc->f('cal_days'));
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
	$GLOBALS['egw_setup']->oProc->DropTable('calendar_entry_repeats');

// calendar_entry_user => phpgw_cal_user
	$GLOBALS['egw_setup']->oProc->RenameTable('calendar_entry_user','phpgw_cal_user');

	$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.11.002';
	return $GLOBALS['setup_info']['calendar']['currentver'];
}


function calendar_upgrade0_9_11_002()
{
	$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.11.003';
	return $GLOBALS['setup_info']['calendar']['currentver'];
}


function calendar_upgrade0_9_11_003()
{
	$GLOBALS['egw_setup']->oProc->CreateTable('phpgw_cal_holidays',
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


function calendar_upgrade0_9_11_004()
{
	$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.11.005';
	return $GLOBALS['setup_info']['calendar']['currentver'];
}


function calendar_upgrade0_9_11_005()
{
	$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.11.006';
	return $GLOBALS['setup_info']['calendar']['currentver'];
}


function calendar_upgrade0_9_11_006()
{
	$GLOBALS['egw_setup']->oProc->DropTable('phpgw_cal_holidays');
	$GLOBALS['egw_setup']->oProc->CreateTable('phpgw_cal_holidays',
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


function calendar_upgrade0_9_11_007()
{
	$GLOBALS['egw_setup']->oProc->query('DELETE FROM phpgw_cal_holidays');
	$GLOBALS['egw_setup']->oProc->AddColumn('phpgw_cal_holidays','mday',array('type' => 'int', 'precision' => 8,'nullable' => False, 'default' => '0'));
	$GLOBALS['egw_setup']->oProc->AddColumn('phpgw_cal_holidays','month_num',array('type' => 'int', 'precision' => 8,'nullable' => False, 'default' => '0'));
	$GLOBALS['egw_setup']->oProc->AddColumn('phpgw_cal_holidays','occurence',array('type' => 'int', 'precision' => 8,'nullable' => False, 'default' => '0'));
	$GLOBALS['egw_setup']->oProc->AddColumn('phpgw_cal_holidays','dow',array('type' => 'int', 'precision' => 8,'nullable' => False, 'default' => '0'));

	$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.11.008';
	return $GLOBALS['setup_info']['calendar']['currentver'];
}


function calendar_upgrade0_9_11_008()
{
	$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.11.009';
	return $GLOBALS['setup_info']['calendar']['currentver'];
}


function calendar_upgrade0_9_11_009()
{
	$GLOBALS['egw_setup']->oProc->query('DELETE FROM phpgw_cal_holidays');
	$GLOBALS['egw_setup']->oProc->AddColumn('phpgw_cal_holidays','observance_rule',array('type' => 'int', 'precision' => 8,'nullable' => False, 'default' => '0'));

	$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.11.010';
	return $GLOBALS['setup_info']['calendar']['currentver'];
}


function calendar_upgrade0_9_11_010()
{
	$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.11.011';
	return $GLOBALS['setup_info']['calendar']['currentver'];
}


function calendar_upgrade0_9_11_011()
{
	$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.13.001';
	return $GLOBALS['setup_info']['calendar']['currentver'];
}

function calendar_upgrade0_9_12()
{
	$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.13.001';
	return $GLOBALS['setup_info']['calendar']['currentver'];
}

function calendar_upgrade0_9_13_001()
{
	$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.13.002';
	return $GLOBALS['setup_info']['calendar']['currentver'];
}


function calendar_upgrade0_9_13_002()
{
	$GLOBALS['egw_setup']->oProc->AddColumn('phpgw_cal','reference',array('type' => 'int', 'precision' => 8,'nullable' => False, 'default' => '0'));

	$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.13.003';
	return $GLOBALS['setup_info']['calendar']['currentver'];
}


function calendar_upgrade0_9_13_003()
{
	$GLOBALS['egw_setup']->oProc->CreateTable('phpgw_cal_alarm',
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

	$GLOBALS['egw_setup']->oProc->AddColumn('phpgw_cal','uid',array('type' => 'varchar', 'precision' => 255,'nullable' => False));
	$GLOBALS['egw_setup']->oProc->AddColumn('phpgw_cal','location',array('type' => 'varchar', 'precision' => 255,'nullable' => True));

	$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.13.004';
	return $GLOBALS['setup_info']['calendar']['currentver'];
}


function calendar_upgrade0_9_13_004()
{
	$GLOBALS['egw_setup']->oProc->AddColumn('phpgw_cal_alarm','alarm_enabled',array('type' => 'int', 'precision' => 4,'nullable' => False, 'default' => '1'));

	$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.13.005';
	return $GLOBALS['setup_info']['calendar']['currentver'];
}


function calendar_upgrade0_9_13_005()
{
	$calendar_data = Array();
	$GLOBALS['egw_setup']->oProc->query('SELECT cal_id, category FROM phpgw_cal',__LINE__,__FILE__);
	while($GLOBALS['egw_setup']->oProc->next_record())
	{
		$calendar_data[$GLOBALS['egw_setup']->oProc->f('cal_id')] = $GLOBALS['egw_setup']->oProc->f('category');
	}

	$GLOBALS['egw_setup']->oProc->AlterColumn('phpgw_cal','category',array('type' => 'varchar', 'precision' => 30,'nullable' => True));

	@reset($calendar_data);
	while($calendar_data && list($cal_id,$category) = each($calendar_data))
	{
		$GLOBALS['egw_setup']->oProc->query("UPDATE phpgw_cal SET category='".$category."' WHERE cal_id=".$cal_id,__LINE__,__FILE__);
	}
	$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.13.006';
	return $GLOBALS['setup_info']['calendar']['currentver'];
}


function calendar_upgrade0_9_13_006()
{
	$GLOBALS['egw_setup']->oProc->AddColumn('phpgw_cal_repeats','recur_exception',array('type' => 'varchar', 'precision' => 255, 'nullable' => True, 'default' => ''));

	$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.13.007';
	return $GLOBALS['setup_info']['calendar']['currentver'];
}



function calendar_upgrade0_9_13_007()
{
	$GLOBALS['egw_setup']->oProc->AddColumn('phpgw_cal_user','cal_type',array(
		'type' => 'varchar',
		'precision' => '1',
		'nullable' => False,
		'default' => 'u'
	));

	$GLOBALS['egw_setup']->oProc->CreateTable('phpgw_cal_extra',array(
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

	$GLOBALS['egw_setup']->oProc->DropTable('phpgw_cal_alarm');

	$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.16.002';
	return $GLOBALS['setup_info']['calendar']['currentver'];
}



function calendar_upgrade0_9_16_001()
{
	// this is to set the default as schema_proc was not setting an empty default
	$GLOBALS['egw_setup']->oProc->AlterColumn('phpgw_cal_user','cal_type',array(
		'type' => 'varchar',
		'precision' => '1',
		'nullable' => False,
		'default' => 'u'
	));

	$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.16.002';
	return $GLOBALS['setup_info']['calendar']['currentver'];
}


// the following series of updates add some indices, to speedup the selects


function calendar_upgrade0_9_16_002()
{
	$GLOBALS['egw_setup']->oProc->RefreshTable('phpgw_cal_repeats',array(
		'fd' => array(
			'cal_id' => array('type' => 'int','precision' => '8','nullable' => False),
			'recur_type' => array('type' => 'int','precision' => '8','nullable' => False),
			'recur_use_end' => array('type' => 'int','precision' => '8','default' => '0'),
			'recur_enddate' => array('type' => 'int','precision' => '8'),
			'recur_interval' => array('type' => 'int','precision' => '8','default' => '1'),
			'recur_data' => array('type' => 'int','precision' => '8','default' => '1'),
			'recur_exception' => array('type' => 'varchar','precision' => '255','default' => '')
		),
		'pk' => array(),
		'fk' => array(),
		'ix' => array('cal_id'),
		'uc' => array()
	));

	$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.16.003';
	return $GLOBALS['setup_info']['calendar']['currentver'];
}



function calendar_upgrade0_9_16_003()
{
	$GLOBALS['egw_setup']->oProc->RefreshTable('phpgw_cal_user',array(
		'fd' => array(
			'cal_id' => array('type' => 'int','precision' => '8','nullable' => False,'default' => '0'),
			'cal_login' => array('type' => 'int','precision' => '8','nullable' => False,'default' => '0'),
			'cal_status' => array('type' => 'char','precision' => '1','default' => 'A'),
			'cal_type' => array('type' => 'varchar','precision' => '1','nullable' => False,'default' => 'u')
		),
		'pk' => array('cal_id','cal_login','cal_type'),
		'fk' => array(),
		'ix' => array(),
		'uc' => array()
	));

	$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.16.004';
	return $GLOBALS['setup_info']['calendar']['currentver'];
}



function calendar_upgrade0_9_16_004()
{
	$GLOBALS['egw_setup']->oProc->RefreshTable('phpgw_cal_holidays',array(
		'fd' => array(
			'hol_id' => array('type' => 'auto','nullable' => False),
			'locale' => array('type' => 'char','precision' => '2','nullable' => False),
			'name' => array('type' => 'varchar','precision' => '50','nullable' => False),
			'mday' => array('type' => 'int','precision' => '8','nullable' => False,'default' => '0'),
			'month_num' => array('type' => 'int','precision' => '8','nullable' => False,'default' => '0'),
			'occurence' => array('type' => 'int','precision' => '8','nullable' => False,'default' => '0'),
			'dow' => array('type' => 'int','precision' => '8','nullable' => False,'default' => '0'),
			'observance_rule' => array('type' => 'int','precision' => '8','nullable' => False,'default' => '0')
		),
		'pk' => array('hol_id'),
		'fk' => array(),
		'ix' => array('locale'),
		'uc' => array()
	));

	$GLOBALS['setup_info']['calendar']['currentver'] = '0.9.16.005';
	return $GLOBALS['setup_info']['calendar']['currentver'];
}



function calendar_upgrade0_9_16_005()
{
	// creates uid's for all entries which do not have unique ones, they are '-@domain.com'
	// very old entries even have an empty uid, see 0.9.16.006 update
	$GLOBALS['egw_setup']->oProc->query("SELECT config_name,config_value FROM {$GLOBALS['egw_setup']->config_table} WHERE config_name IN ('install_id','mail_suffix') AND config_app='phpgwapi'",__LINE__,__FILE__);
	while ($GLOBALS['egw_setup']->oProc->next_record())
	{
		$config[$GLOBALS['egw_setup']->oProc->f(0)] = $GLOBALS['egw_setup']->oProc->f(1);
	}
	$GLOBALS['egw_setup']->oProc->query('UPDATE phpgw_cal SET uid='.
		$GLOBALS['egw_setup']->db->concat($GLOBALS['egw_setup']->db->quote('cal-'),'cal_id',
			$GLOBALS['egw_setup']->db->quote('-'.$config['install_id'].'@'.
			($config['mail_suffix'] ? $config['mail_suffix'] : 'local'))).
		" WHERE uid LIKE '-@%' OR uid=''");

	// we dont need to do update 0.9.16.007, as UpdateSequenze is called now by RefreshTable
	$GLOBALS['setup_info']['calendar']['currentver'] = '1.0.0';
	return $GLOBALS['setup_info']['calendar']['currentver'];
}



function calendar_upgrade0_9_16_006()
{
	// re-run the update as very old entries only have an empty uid
	return calendar_upgrade0_9_16_005();
}



function calendar_upgrade0_9_16_007()
{
	// update the sequenzes for refreshed tables (postgres only)
	$GLOBALS['egw_setup']->oProc->UpdateSequence('phpgw_cal_holidays','hol_id');

	$GLOBALS['setup_info']['calendar']['currentver'] = '1.0.0';
	return $GLOBALS['setup_info']['calendar']['currentver'];
}



function calendar_upgrade1_0_0()
{
	$GLOBALS['egw_setup']->oProc->RenameColumn('phpgw_cal','uid','cal_uid');
	$GLOBALS['egw_setup']->oProc->RenameColumn('phpgw_cal','owner','cal_owner');
	$GLOBALS['egw_setup']->oProc->RenameColumn('phpgw_cal','category','cal_category');
	$GLOBALS['egw_setup']->oProc->RenameColumn('phpgw_cal','groups','cal_groups');
	$GLOBALS['egw_setup']->oProc->RenameColumn('phpgw_cal','datetime','cal_starttime');
	$GLOBALS['egw_setup']->oProc->RenameColumn('phpgw_cal','mdatetime','cal_modified');
	$GLOBALS['egw_setup']->oProc->RenameColumn('phpgw_cal','edatetime','cal_endtime');
	$GLOBALS['egw_setup']->oProc->RenameColumn('phpgw_cal','priority','cal_priority');
	$GLOBALS['egw_setup']->oProc->RenameColumn('phpgw_cal','is_public','cal_public');
	$GLOBALS['egw_setup']->oProc->RenameColumn('phpgw_cal','title','cal_title');
	$GLOBALS['egw_setup']->oProc->RenameColumn('phpgw_cal','description','cal_description');
	$GLOBALS['egw_setup']->oProc->RenameColumn('phpgw_cal','location','cal_location');
	$GLOBALS['egw_setup']->oProc->RenameColumn('phpgw_cal','reference','cal_reference');

	$GLOBALS['setup_info']['calendar']['currentver'] = '1.0.0.001';
	return $GLOBALS['setup_info']['calendar']['currentver'];
}



function calendar_upgrade1_0_0_001()
{
	$GLOBALS['egw_setup']->oProc->RenameColumn('phpgw_cal_holidays','locale','hol_locale');
	$GLOBALS['egw_setup']->oProc->RenameColumn('phpgw_cal_holidays','name','hol_name');
	$GLOBALS['egw_setup']->oProc->RenameColumn('phpgw_cal_holidays','mday','hol_mday');
	$GLOBALS['egw_setup']->oProc->RenameColumn('phpgw_cal_holidays','month_num','hol_month_num');
	$GLOBALS['egw_setup']->oProc->RenameColumn('phpgw_cal_holidays','occurence','hol_occurence');
	$GLOBALS['egw_setup']->oProc->RenameColumn('phpgw_cal_holidays','dow','hol_dow');
	$GLOBALS['egw_setup']->oProc->RenameColumn('phpgw_cal_holidays','observance_rule','hol_observance_rule');

	$GLOBALS['setup_info']['calendar']['currentver'] = '1.0.0.002';
	return $GLOBALS['setup_info']['calendar']['currentver'];
}



function calendar_upgrade1_0_0_002()
{
	$GLOBALS['egw_setup']->oProc->RenameColumn('phpgw_cal_user','cal_login','cal_user_id');
	$GLOBALS['egw_setup']->oProc->RenameColumn('phpgw_cal_user','cal_type','cal_user_type');

	$GLOBALS['setup_info']['calendar']['currentver'] = '1.0.0.003';
	return $GLOBALS['setup_info']['calendar']['currentver'];
}



function calendar_upgrade1_0_0_003()
{
	$GLOBALS['egw_setup']->oProc->AlterColumn('phpgw_cal','cal_title',array(
		'type' => 'varchar',
		'precision' => '255',
		'nullable' => False,
		'default' => '1'
	));

	$GLOBALS['setup_info']['calendar']['currentver'] = '1.0.0.004';
	return $GLOBALS['setup_info']['calendar']['currentver'];
}



function calendar_upgrade1_0_0_004()
{
	$GLOBALS['egw_setup']->oProc->RefreshTable('phpgw_cal_repeats',array(
		'fd' => array(
			'cal_id' => array('type' => 'int','precision' => '8','nullable' => False),
			'recur_type' => array('type' => 'int','precision' => '8','nullable' => False),
			'recur_use_end' => array('type' => 'int','precision' => '8','default' => '0'),
			'recur_enddate' => array('type' => 'int','precision' => '8'),
			'recur_interval' => array('type' => 'int','precision' => '8','default' => '1'),
			'recur_data' => array('type' => 'int','precision' => '8','default' => '1'),
			'recur_exception' => array('type' => 'varchar','precision' => '255','default' => '')
		),
		'pk' => array('cal_id'),
		'fk' => array(),
		'ix' => array(),
		'uc' => array()
	));

	$GLOBALS['setup_info']['calendar']['currentver'] = '1.0.0.005';
	return $GLOBALS['setup_info']['calendar']['currentver'];
}



function calendar_upgrade1_0_0_005()
{
	// change prefix of all calendar tables to egw_
	foreach(array('cal_user','cal_repeats','cal_extra','cal_holidays','cal') as $name)
	{
		$GLOBALS['egw_setup']->oProc->RenameTable('phpgw_'.$name,'egw_'.$name);
	}

	// create new dates table, with content from the egw_cal table
	$GLOBALS['egw_setup']->oProc->CreateTable('egw_cal_dates',array(
		'fd' => array(
			'cal_id' => array('type' => 'int','precision' => '4','nullable' => False),
			'cal_start' => array('type' => 'int','precision' => '8','nullable' => False),
			'cal_end' => array('type' => 'int','precision' => '8','nullable' => False)
		),
		'pk' => array('cal_id','cal_start'),
		'fk' => array(),
		'ix' => array(),
		'uc' => array()
	));
	$GLOBALS['egw_setup']->oProc->query("INSERT INTO egw_cal_dates SELECT cal_id,cal_starttime,cal_endtime FROM egw_cal");

	// drop the fields transfered to the dates table
	$GLOBALS['egw_setup']->oProc->DropColumn('egw_cal',array(
		'fd' => array(
			'cal_id' => array('type' => 'auto','nullable' => False),
			'cal_uid' => array('type' => 'varchar','precision' => '255','nullable' => False),
			'cal_owner' => array('type' => 'int','precision' => '8','nullable' => False),
			'cal_category' => array('type' => 'varchar','precision' => '30'),
			'cal_groups' => array('type' => 'varchar','precision' => '255'),
			'cal_modified' => array('type' => 'int','precision' => '8'),
			'cal_endtime' => array('type' => 'int','precision' => '8'),
			'cal_priority' => array('type' => 'int','precision' => '8','nullable' => False,'default' => '2'),
			'cal_type' => array('type' => 'varchar','precision' => '10'),
			'cal_public' => array('type' => 'int','precision' => '8','nullable' => False,'default' => '1'),
			'cal_title' => array('type' => 'varchar','precision' => '255','nullable' => False,'default' => '1'),
			'cal_description' => array('type' => 'text'),
			'cal_location' => array('type' => 'varchar','precision' => '255'),
			'cal_reference' => array('type' => 'int','precision' => '8','nullable' => False,'default' => '0')
		),
		'pk' => array('cal_id'),
		'fk' => array(),
		'ix' => array(),
		'uc' => array()
	),'cal_starttime');
	$GLOBALS['egw_setup']->oProc->DropColumn('egw_cal',array(
		'fd' => array(
			'cal_id' => array('type' => 'auto','nullable' => False),
			'cal_uid' => array('type' => 'varchar','precision' => '255','nullable' => False),
			'cal_owner' => array('type' => 'int','precision' => '8','nullable' => False),
			'cal_category' => array('type' => 'varchar','precision' => '30'),
			'cal_groups' => array('type' => 'varchar','precision' => '255'),
			'cal_modified' => array('type' => 'int','precision' => '8'),
			'cal_priority' => array('type' => 'int','precision' => '8','nullable' => False,'default' => '2'),
			'cal_type' => array('type' => 'varchar','precision' => '10'),
			'cal_public' => array('type' => 'int','precision' => '8','nullable' => False,'default' => '1'),
			'cal_title' => array('type' => 'varchar','precision' => '255','nullable' => False,'default' => '1'),
			'cal_description' => array('type' => 'text'),
			'cal_location' => array('type' => 'varchar','precision' => '255'),
			'cal_reference' => array('type' => 'int','precision' => '8','nullable' => False,'default' => '0')
		),
		'pk' => array('cal_id'),
		'fk' => array(),
		'ix' => array(),
		'uc' => array()
	),'cal_endtime');

	$GLOBALS['setup_info']['calendar']['currentver'] = '1.0.1.001';
	return $GLOBALS['setup_info']['calendar']['currentver'];
}



function calendar_upgrade1_0_1_001()
{
	/* done by RefreshTable() anyway
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_cal_user','cal_recur_date',array(
		'type' => 'int',
		'precision' => '8',
		'default' => '0'
	));*/
	$GLOBALS['egw_setup']->oProc->RefreshTable('egw_cal_user',array(
		'fd' => array(
			'cal_id' => array('type' => 'int','precision' => '8','nullable' => False,'default' => '0'),
			'cal_recur_date' => array('type' => 'int','precision' => '8','default' => '0'),
			'cal_user_type' => array('type' => 'varchar','precision' => '1','nullable' => False,'default' => 'u'),
			'cal_user_id' => array('type' => 'int','precision' => '8','nullable' => False,'default' => '0'),
			'cal_status' => array('type' => 'char','precision' => '1','default' => 'A')
		),
		'pk' => array('cal_id','cal_recur_date','cal_user_type','cal_user_id'),
		'fk' => array(),
		'ix' => array(),
		'uc' => array()
	));

	$GLOBALS['setup_info']['calendar']['currentver'] = '1.0.1.002';
	return $GLOBALS['setup_info']['calendar']['currentver'];
}



function calendar_upgrade1_0_1_002()
{
	$GLOBALS['egw_setup']->oProc->DropColumn('egw_cal',array(
		'fd' => array(
			'cal_id' => array('type' => 'auto','nullable' => False),
			'cal_uid' => array('type' => 'varchar','precision' => '255','nullable' => False),
			'cal_owner' => array('type' => 'int','precision' => '8','nullable' => False),
			'cal_category' => array('type' => 'varchar','precision' => '30'),
			'cal_groups' => array('type' => 'varchar','precision' => '255'),
			'cal_modified' => array('type' => 'int','precision' => '8'),
			'cal_priority' => array('type' => 'int','precision' => '8','nullable' => False,'default' => '2'),
			'cal_public' => array('type' => 'int','precision' => '8','nullable' => False,'default' => '1'),
			'cal_title' => array('type' => 'varchar','precision' => '255','nullable' => False,'default' => '1'),
			'cal_description' => array('type' => 'text'),
			'cal_location' => array('type' => 'varchar','precision' => '255'),
			'cal_reference' => array('type' => 'int','precision' => '8','nullable' => False,'default' => '0')
		),
		'pk' => array('cal_id'),
		'fk' => array(),
		'ix' => array(),
		'uc' => array()
	),'cal_type');
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_cal','cal_owner',array(
		'type' => 'int',
		'precision' => '4',
		'nullable' => False
	));
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_cal','cal_priority',array(
		'type' => 'int',
		'precision' => '2',
		'nullable' => False,
		'default' => '2'
	));
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_cal','cal_public',array(
		'type' => 'int',
		'precision' => '2',
		'nullable' => False,
		'default' => '1'
	));
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_cal','cal_reference',array(
		'type' => 'int',
		'precision' => '4',
		'nullable' => False,
		'default' => '0'
	));
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_cal','cal_modifier',array(
		'type' => 'int',
		'precision' => '4'
	));

	$GLOBALS['setup_info']['calendar']['currentver'] = '1.0.1.003';
	return $GLOBALS['setup_info']['calendar']['currentver'];
}



function calendar_upgrade1_0_1_003()
{
	$GLOBALS['egw_setup']->oProc->DropColumn('egw_cal_repeats',array(
		'fd' => array(
			'cal_id' => array('type' => 'int','precision' => '8','nullable' => False),
			'recur_type' => array('type' => 'int','precision' => '8','nullable' => False),
			'recur_enddate' => array('type' => 'int','precision' => '8'),
			'recur_interval' => array('type' => 'int','precision' => '8','default' => '1'),
			'recur_data' => array('type' => 'int','precision' => '8','default' => '1'),
			'recur_exception' => array('type' => 'varchar','precision' => '255','default' => '')
		),
		'pk' => array('cal_id'),
		'fk' => array(),
		'ix' => array(),
		'uc' => array()
	),'recur_use_end');
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_cal_repeats','cal_id',array(
		'type' => 'int',
		'precision' => '4',
		'nullable' => False
	));
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_cal_repeats','recur_type',array(
		'type' => 'int',
		'precision' => '2',
		'nullable' => False
	));
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_cal_repeats','recur_interval',array(
		'type' => 'int',
		'precision' => '2',
		'default' => '1'
	));
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_cal_repeats','recur_data',array(
		'type' => 'int',
		'precision' => '2',
		'default' => '1'
	));

	$GLOBALS['setup_info']['calendar']['currentver'] = '1.0.1.004';
	return $GLOBALS['setup_info']['calendar']['currentver'];
}



function calendar_upgrade1_0_1_004()
{
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_cal_user','cal_id',array(
		'type' => 'int',
		'precision' => '4',
		'nullable' => False,
		'default' => '0'
	));
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_cal_user','cal_user_id',array(
		'type' => 'int',
		'precision' => '4',
		'nullable' => False,
		'default' => '0'
	));

	$GLOBALS['setup_info']['calendar']['currentver'] = '1.0.1.005';
	return $GLOBALS['setup_info']['calendar']['currentver'];
}



function calendar_upgrade1_0_1_005()
{
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_cal_user','cal_quantity',array(
		'type' => 'int',
		'precision' => '4',
		'default' => '1'
	));

	$GLOBALS['setup_info']['calendar']['currentver'] = '1.0.1.006';
	return $GLOBALS['setup_info']['calendar']['currentver'];
}



function calendar_upgrade1_0_1_006()
{
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_cal','cal_non_blocking',array(
		'type' => 'int',
		'precision' => '2',
		'default' => '0'
	));

	$GLOBALS['setup_info']['calendar']['currentver'] = '1.0.1.007';
	return $GLOBALS['setup_info']['calendar']['currentver'];
}



function calendar_upgrade1_0_1_007()
{
	$GLOBALS['egw_setup']->db->update('egw_cal_repeats',array('recur_exception' => null),array('recur_exception' => ''),__LINE__,__FILE__,'calendar');

	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_cal_repeats','recur_exception',array(
		'type' => 'text'
	));

	$GLOBALS['setup_info']['calendar']['currentver'] = '1.0.1.008';
	return $GLOBALS['setup_info']['calendar']['currentver'];
}



function calendar_upgrade1_0_1_008()
{
	$config_data = config::read('calendar');
	if (isset($config_data['fields']))	// old custom fields
	{
		$customfields = array();
		$order = 0;
		foreach($config_data['fields'] as $name => $data)
		{
			if ($name{0} == '#' && !$data['disabled'])	// real not-disabled custom field
			{
				$customfields[substr($name,1)] = array(
					'type'  => 'text',
					'len'   => $data['length'].($data['shown'] ? ','.$data['shown'] : ''),
					'label' => $data['name'],
					'order' => ($order += 10),
				);
			}
		}
		if (count($customfields))
		{
			config::save_value('customfields', $customfields, 'calendar');
		}
		config::save_value('fields', null, 'calendar');
	}
	$GLOBALS['setup_info']['calendar']['currentver'] = '1.0.1.009';
	return $GLOBALS['setup_info']['calendar']['currentver'];
}



function calendar_upgrade1_0_1_009()
{
	$db2 = clone($GLOBALS['egw_setup']->db);
	$add_groups = array();
	$GLOBALS['egw_setup']->db->select('egw_cal','DISTINCT egw_cal.cal_id,cal_groups,cal_recur_date',"cal_groups != ''",__LINE__,__FILE__,
		False,'','calendar',0,',egw_cal_user WHERE egw_cal.cal_id=egw_cal_user.cal_id');
	while(($row = $GLOBALS['egw_setup']->db->row(true)))
	{
		$row['cal_user_type'] = 'u';
		foreach(explode(',',$row['cal_groups']) as $group)
		{
			$row['cal_user_id'] = $group;
			$db2->insert('egw_cal_user',array('cal_status' => 'U'),$row,__LINE__,__FILE__,'calendar');
		}
	}
	$GLOBALS['egw_setup']->oProc->DropColumn('egw_cal',array(
		'fd' => array(
			'cal_id' => array('type' => 'auto','nullable' => False),
			'cal_uid' => array('type' => 'varchar','precision' => '255','nullable' => False),
			'cal_owner' => array('type' => 'int','precision' => '4','nullable' => False),
			'cal_category' => array('type' => 'varchar','precision' => '30'),
			'cal_modified' => array('type' => 'int','precision' => '8'),
			'cal_priority' => array('type' => 'int','precision' => '2','nullable' => False,'default' => '2'),
			'cal_public' => array('type' => 'int','precision' => '2','nullable' => False,'default' => '1'),
			'cal_title' => array('type' => 'varchar','precision' => '255','nullable' => False,'default' => '1'),
			'cal_description' => array('type' => 'text'),
			'cal_location' => array('type' => 'varchar','precision' => '255'),
			'cal_reference' => array('type' => 'int','precision' => '4','nullable' => False,'default' => '0'),
			'cal_modifier' => array('type' => 'int','precision' => '4'),
			'cal_non_blocking' => array('type' => 'int','precision' => '2','default' => '0')
		),
		'pk' => array('cal_id'),
		'fk' => array(),
		'ix' => array(),
		'uc' => array()
	),'cal_groups');

	return $GLOBALS['setup_info']['calendar']['currentver'] = '1.2';
}



function calendar_upgrade1_2()
{
	// get old alarms (saved before 1.2) working again
	$GLOBALS['egw_setup']->db->query("UPDATE egw_async SET async_method ='calendar.bocalupdate.send_alarm' WHERE async_method ='calendar.bocalendar.send_alarm'",__LINE__,__FILE__);

	return $GLOBALS['setup_info']['calendar']['currentver'] = '1.2.001';
}


function calendar_upgrade1_2_001()
{
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_cal','cal_special',array(
		'type' => 'int',
		'precision' => '2',
		'default' => '0'
	));

	return $GLOBALS['setup_info']['calendar']['currentver'] = '1.3.001';
}


function calendar_upgrade1_3_001()
{
	return $GLOBALS['setup_info']['calendar']['currentver'] = '1.4';
}


function calendar_upgrade1_4()
{
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_cal','cal_etag',array(
		'type' => 'int',
		'precision' => '4',
		'default' => '0'
	));
	// as we no longer create cal_edit_time|user and already set default 0 for cal_etag, we skip the 1.5 update
	return $GLOBALS['setup_info']['calendar']['currentver'] = '1.5.001';
}


function calendar_upgrade1_5()
{
	$GLOBALS['egw_setup']->oProc->DropColumn('egw_cal',array(
		'fd' => array(
			'cal_id' => array('type' => 'auto','nullable' => False),
			'cal_uid' => array('type' => 'varchar','precision' => '255','nullable' => False),
			'cal_owner' => array('type' => 'int','precision' => '4','nullable' => False),
			'cal_category' => array('type' => 'varchar','precision' => '30'),
			'cal_modified' => array('type' => 'int','precision' => '8'),
			'cal_priority' => array('type' => 'int','precision' => '2','nullable' => False,'default' => '2'),
			'cal_public' => array('type' => 'int','precision' => '2','nullable' => False,'default' => '1'),
			'cal_title' => array('type' => 'varchar','precision' => '255','nullable' => False,'default' => '1'),
			'cal_description' => array('type' => 'text'),
			'cal_location' => array('type' => 'varchar','precision' => '255'),
			'cal_reference' => array('type' => 'int','precision' => '4','nullable' => False,'default' => '0'),
			'cal_modifier' => array('type' => 'int','precision' => '4'),
			'cal_non_blocking' => array('type' => 'int','precision' => '2','default' => '0'),
			'cal_special' => array('type' => 'int','precision' => '2','default' => '0'),
			'cal_etag' => array('type' => 'int','precision' => '4'),
			'cal_edit_time' => array('type' => 'int','precision' => '8')
		),
		'pk' => array('cal_id'),
		'fk' => array(),
		'ix' => array(),
		'uc' => array()
	),'cal_edit_user');
	$GLOBALS['egw_setup']->oProc->DropColumn('egw_cal',array(
		'fd' => array(
			'cal_id' => array('type' => 'auto','nullable' => False),
			'cal_uid' => array('type' => 'varchar','precision' => '255','nullable' => False),
			'cal_owner' => array('type' => 'int','precision' => '4','nullable' => False),
			'cal_category' => array('type' => 'varchar','precision' => '30'),
			'cal_modified' => array('type' => 'int','precision' => '8'),
			'cal_priority' => array('type' => 'int','precision' => '2','nullable' => False,'default' => '2'),
			'cal_public' => array('type' => 'int','precision' => '2','nullable' => False,'default' => '1'),
			'cal_title' => array('type' => 'varchar','precision' => '255','nullable' => False,'default' => '1'),
			'cal_description' => array('type' => 'text'),
			'cal_location' => array('type' => 'varchar','precision' => '255'),
			'cal_reference' => array('type' => 'int','precision' => '4','nullable' => False,'default' => '0'),
			'cal_modifier' => array('type' => 'int','precision' => '4'),
			'cal_non_blocking' => array('type' => 'int','precision' => '2','default' => '0'),
			'cal_special' => array('type' => 'int','precision' => '2','default' => '0'),
			'cal_etag' => array('type' => 'int','precision' => '4')
		),
		'pk' => array('cal_id'),
		'fk' => array(),
		'ix' => array(),
		'uc' => array()
	),'cal_edit_time');
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_cal','cal_etag',array(
		'type' => 'int',
		'precision' => '4',
		'default' => '0'
	));
	$GLOBALS['egw_setup']->db->query('UPDATE egw_cal SET cal_etag=0 WHERE cal_etag IS NULL',__LINE__,__FILE__);

	return $GLOBALS['setup_info']['calendar']['currentver'] = '1.5.001';
}


function calendar_upgrade1_5_001()
{
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_cal_user','cal_id',array(
		'type' => 'int',
		'precision' => '4',
		'nullable' => False
	));
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_cal_user','cal_user_id',array(
		'type' => 'varchar',
		'precision' => '128',
		'nullable' => False
	));

	return $GLOBALS['setup_info']['calendar']['currentver'] = '1.5.002';
}


function calendar_upgrade1_5_002()
{
	// update the alarm methods
	$async = new asyncservice();
	foreach((array)$async->read('cal:%') as $id => $job)
	{
		if ($job['method'] == 'calendar.bocalupdate.send_alarm')
		{
			$job['method'] = 'calendar.calendar_boupdate.send_alarm';
			$async->write($job,true);
		}
	}
	return $GLOBALS['setup_info']['calendar']['currentver'] = '1.6';
}


/**
 * Adjust UIDs of series exceptions to RFC standard
 * Propagate cal_reference field to temporarily contain RECURRENCE-ID
 *
 * @return string
 */
function calendar_upgrade1_6()
{
	// Set UID of series exception to UID of series master
	// update cal_etag, cal_modified and cal_modifier to distribute changes on GroupDAV devices
	foreach($GLOBALS['egw_setup']->db->query('
		SELECT cal_ex.cal_id,cal_ex.cal_uid AS cal_uid_ex,cal_master.cal_uid AS cal_uid_master
		FROM egw_cal cal_ex
		JOIN egw_cal cal_master ON cal_ex.cal_reference=cal_master.cal_id
		WHERE cal_ex.cal_reference != 0',__LINE__,__FILE__) as $row)
	{
		if (strlen($row['cal_uid_master']) > 0 && $row['cal_uid_ex'] != $row['cal_uid_master'])
		{
			$GLOBALS['egw_setup']->db->query('UPDATE egw_cal SET cal_uid=\''.$row['cal_uid_master'].
				'\',cal_etag=cal_etag+1,cal_modified='.time().
				',cal_modifier=NULL WHERE cal_id='.(int)$row['cal_id'],__LINE__,__FILE__);
		}
	}

	// Search series exception for nearest exception in series master and add that RECURRENCE-ID
	// as cal_reference (for 1.6.003 and move it to new field cal_recurrence in 1.7.001)
	foreach($GLOBALS['egw_setup']->db->query('SELECT egw_cal.cal_id,cal_start,recur_exception FROM egw_cal
		JOIN egw_cal_dates ON egw_cal.cal_id=egw_cal_dates.cal_id
		JOIN egw_cal_repeats ON cal_reference=egw_cal_repeats.cal_id
		WHERE cal_reference != 0',__LINE__,__FILE__) as $row)
	{
		$recurrence = null;
		foreach(explode(',',$row['recur_exception']) as $ts)
		{
			if (is_null($recurrence) || abs($ts-$row['cal_start']) < $diff)
			{
				$recurrence = $ts;
				$diff = abs($ts-$row['cal_start']);
			}
		}
		if ($recurrence)
		{
			$GLOBALS['egw_setup']->db->query('UPDATE egw_cal SET cal_reference='.(int)$recurrence.
				' WHERE cal_id='.(int)$row['cal_id'],__LINE__,__FILE__);
		}
		else
		{
			// if we cannot determine the RECURRENCE-ID use cal_start
			// because RECURRENCE-ID must be present
			$GLOBALS['egw_setup']->db->query('UPDATE egw_cal SET cal_reference='.(int)$row['cal_start'].
				' WHERE cal_id='.(int)$row['cal_id'],__LINE__,__FILE__);
		}
	}

	return $GLOBALS['setup_info']['calendar']['currentver'] = '1.6.003';
}


/**
 * Adding column for RECURRENCE-ID of master event to improve iCal handling of exceptions
 *
 * @return string
 */
function calendar_upgrade1_6_003()
{
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_cal','cal_creator',array(
		'type' => 'int',
		'precision' => '4',
		'comment' => 'creating user'
	));
	$GLOBALS['egw_setup']->db->query('UPDATE egw_cal SET cal_creator=cal_owner',__LINE__,__FILE__);
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_cal','cal_creator',array(
		'type' => 'int',
		'precision' => '4',
		'nullable' => False,
		'comment' => 'creating user'
	));

	$GLOBALS['egw_setup']->oProc->AddColumn('egw_cal','cal_created',array(
		'type' => 'int',
		'precision' => '8',
		'comment' => 'creation time of event'
	));
	$GLOBALS['egw_setup']->db->query('UPDATE egw_cal SET cal_created=cal_modified',__LINE__,__FILE__);
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_cal','cal_created',array(
		'type' => 'int',
		'precision' => '8',
		'nullable' => False,
		'comment' => 'creation time of event'
	));

	$GLOBALS['egw_setup']->oProc->AddColumn('egw_cal','cal_recurrence',array(
		'type' => 'int',
		'precision' => '8',
		'nullable' => False,
		'default' => '0',
		'comment' => 'cal_start of original recurrence for exception'
	));

	// move RECURRENCE-ID from temporarily (1.6.003)
	// used field cal_reference to new field cal_recurrence
	// and restore cal_reference field of series exceptions with id of the series master
	foreach($GLOBALS['egw_setup']->db->query('
		SELECT cal_ex.cal_id AS cal_id_ex,cal_master.cal_id AS cal_id_master,
		cal_ex.cal_reference AS cal_reference_ex,cal_ex.cal_uid AS cal_uid_ex,
		cal_master.cal_uid AS cal_uid_master
		FROM egw_cal cal_ex
		JOIN egw_cal cal_master
		ON cal_ex.cal_uid=cal_master.cal_uid AND cal_master.cal_reference = 0 AND cal_ex.cal_owner = cal_master.cal_owner
		WHERE cal_ex.cal_reference !=0 AND cal_master.cal_id IS NOT NULL',__LINE__,__FILE__) as $row)
	{
		$GLOBALS['egw_setup']->db->query('UPDATE egw_cal SET cal_recurrence='.(int)$row['cal_reference_ex'].
			', cal_reference='.(int)$row['cal_id_master'].
			' WHERE cal_id='.(int)$row['cal_id_ex']);
	}

	return $GLOBALS['setup_info']['calendar']['currentver'] = '1.7.001';
}

/**
 * Adding participant roles table to improve iCal support
 *
 * @return string
 */
function calendar_upgrade1_7_001()
{
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_cal_user','cal_role',array(
		'type' => 'varchar',
		'precision' => '64',
		'default' => 'REQ-PARTICIPANT'
	));

	return $GLOBALS['setup_info']['calendar']['currentver'] = '1.7.002';
}

/**
 * Adding timezones table egw_cal_timezones
 *
 * @return string
 */
function calendar_upgrade1_7_002()
{
	$GLOBALS['egw_setup']->oProc->CreateTable('egw_cal_timezones',array(
		'fd' => array(
			'tz_id' => array('type' => 'auto','nullable' => False),
			'tz_tzid' => array('type' => 'varchar','precision' => '128','nullable' => False),
			'tz_alias' => array('type' => 'int','precision' => '4','comment' => 'tz_id for data'),
			'tz_latitude' => array('type' => 'int','precision' => '4'),
			'tz_longitude' => array('type' => 'int','precision' => '4'),
			'tz_component' => array('type' => 'text','comment' => 'iCal VTIMEZONE component')
		),
		'pk' => array('tz_id'),
		'fk' => array(),
		'ix' => array('tz_alias'),
		'uc' => array('tz_tzid')
	));
	// import timezone data, throw exception if no PDO sqlite support
	calendar_timezones::import_sqlite();

	return $GLOBALS['setup_info']['calendar']['currentver'] = '1.7.003';
}

/**
 * Adding automatic timestamp for participant table, maximum can be used as part of a ctag for CalDAV
 *
 * @return string
 */
function calendar_upgrade1_7_003()
{
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_cal_user','cal_user_type',array(
		'type' => 'varchar',
		'precision' => '1',
		'nullable' => False,
		'default' => 'u',
		'comment' => 'u=user, g=group, c=contact, r=resource, e=email'
	));
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_cal_user','cal_user_id',array(
		'type' => 'varchar',
		'precision' => '128',
		'nullable' => False,
		'comment' => 'id or email-address for type=e'
	));
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_cal_user','cal_status',array(
		'type' => 'char',
		'precision' => '1',
		'default' => 'A',
		'comment' => 'U=unknown, A=accepted, R=rejected, T=tentative'
	));
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_cal_user','cal_quantity',array(
		'type' => 'int',
		'precision' => '4',
		'default' => '1',
		'comment' => 'only for certain types (eg. resources)'
	));
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_cal_user','cal_role',array(
		'type' => 'varchar',
		'precision' => '64',
		'default' => 'REQ-PARTICIPANT',
		'comment' => 'CHAIR, REQ-PARTICIPANT, OPT-PARTICIPANT, NON-PARTICIPANT, X-CAT-$cat_id'
	));
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_cal_user','cal_user_modified',array(
		'type' => 'timestamp',
		'default' => 'current_timestamp',
		'comment' => 'automatic timestamp of last update'
	));

	return $GLOBALS['setup_info']['calendar']['currentver'] = '1.7.004';
}

/**
 * Adding timezone id column, to fully support timezones in calendar
 *
 * @return string
 */
function calendar_upgrade1_7_004()
{
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_cal_dates','cal_start',array(
		'type' => 'int',
		'precision' => '8',
		'nullable' => False,
		'comment' => 'starttime in server time'
	));
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_cal_dates','cal_end',array(
		'type' => 'int',
		'precision' => '8',
		'nullable' => False,
		'comment' => 'endtime in server time'
	));
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_cal','tz_id',array(
		'type' => 'int',
		'precision' => '4',
		'comment' => 'key into egw_cal_timezones'
	));

	// set id of server timezone for existing events, as that's the timezone their recurrences are using
	if (($tzid = date_default_timezone_get()) && ($tz_id = calendar_timezones::tz2id($tzid)))
	{
		$GLOBALS['egw_setup']->db->query('UPDATE egw_cal SET tz_id='.(int)$tz_id,__LINE__,__FILE__);
	}
	return $GLOBALS['setup_info']['calendar']['currentver'] = '1.7.005';
}

/**
 * Adding Windows timezones as alias to standard TZID's
 *
 * @link http://unicode.org/repos/cldr-tmp/trunk/diff/supplemental/windows_tzid.html
 *
 * @return string
 */
function calendar_upgrade1_7_005()
{
	calendar_timezones::import_tz_aliases();

	return $GLOBALS['setup_info']['calendar']['currentver'] = '1.7.006';
}

/**
 * // Fix whole day event cal_end times which are set to 23:59:00 or 00:00 instead of 23:59:59
 * // Fix recur_interval from 0 to 1 for event series
 *
 * @return string
 */
function calendar_upgrade1_7_006()
{
	foreach($GLOBALS['egw_setup']->db->query('SELECT * FROM egw_cal_dates
		WHERE (cal_end-cal_start)%86400=86340',__LINE__,__FILE__) as $row)
	{
		$GLOBALS['egw_setup']->db->query('UPDATE egw_cal_dates SET cal_end=cal_end+59
			WHERE cal_id='.(int)$row['cal_id'].' AND cal_start='.(int)$row['cal_start'],__LINE__,__FILE__);
	}

	foreach($GLOBALS['egw_setup']->db->query('SELECT * FROM egw_cal_dates
		WHERE cal_end-cal_start>0 AND (cal_end-cal_start)%86400=0',__LINE__,__FILE__) as $row)
	{
		$GLOBALS['egw_setup']->db->query('UPDATE egw_cal_dates SET cal_end=cal_end-1
			WHERE cal_id='.(int)$row['cal_id'].' AND cal_start='.(int)$row['cal_start'],__LINE__,__FILE__);
	}

    $GLOBALS['egw_setup']->db->query('UPDATE egw_cal_repeats SET recur_interval=1
			WHERE recur_interval=0',__LINE__,__FILE__);

	return $GLOBALS['setup_info']['calendar']['currentver'] = '1.7.007';
}

/**
 * Adjust UIDs of series exceptions to RFC standard
 * this was already done in upgrade to 1.6.003 but we repeat it here in a non-destructive
 * way to catch installations which already used versions > 1.6.003 before we added this to setup
 *
 * @return string
 */
function calendar_upgrade1_7_007()
{
	// Set UID of series exception to UID of series master
	// update cal_etag,cal_modified and cal_modifier to distribute changes on GroupDAV devices
	foreach($GLOBALS['egw_setup']->db->query('
		SELECT cal_ex.cal_id,cal_ex.cal_uid AS cal_uid_ex,cal_master.cal_uid AS cal_uid_master
		FROM egw_cal cal_ex
		JOIN egw_cal cal_master ON cal_ex.cal_reference=cal_master.cal_id
		WHERE cal_ex.cal_reference != 0',__LINE__,__FILE__) as $row)
	{
		if (strlen($row['cal_uid_master']) > 0 && $row['cal_uid_ex'] != $row['cal_uid_master'])
		{
			$GLOBALS['egw_setup']->db->query('UPDATE egw_cal SET cal_uid=\''.$row['cal_uid_master'].
				'\',cal_etag=cal_etag+1,cal_modified='.time().
				',cal_modifier=NULL WHERE cal_id='.(int)$row['cal_id'],__LINE__,__FILE__);
		}
	}
	return $GLOBALS['setup_info']['calendar']['currentver'] = '1.7.008';
}

/**
 * Create an index over egw_cal_user.cal_user_type and cal_user_id, to speed up calendar queries
 *
 * @return string
 */
function calendar_upgrade1_7_008()
{
	$GLOBALS['egw_setup']->oProc->CreateIndex('egw_cal_user',array('cal_user_type','cal_user_id'));

	return $GLOBALS['setup_info']['calendar']['currentver'] = '1.7.009';
}

/**
 * Create an index over egw_cal.cal_uid and cal_owner, to speed up calendar queries specially sync
 *
 * @return string
 */
function calendar_upgrade1_7_009()
{
	$GLOBALS['egw_setup']->oProc->CreateIndex('egw_cal','cal_uid');
	$GLOBALS['egw_setup']->oProc->CreateIndex('egw_cal','cal_owner');

	return $GLOBALS['setup_info']['calendar']['currentver'] = '1.7.010';
}

function calendar_upgrade1_7_010()
{
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_cal','cal_deleted',array(
		'type' => 'bool',
		'nullable' => False,
		'default' => '0',
		'comment' => '1 if the event has been deleted, but you want to keep it around'
	));

	return $GLOBALS['setup_info']['calendar']['currentver'] = '1.9.001';	// was 1.7.011
}

function calendar_upgrade1_7_011()
{
	return $GLOBALS['setup_info']['calendar']['currentver'] = '1.9.001';
}

function calendar_upgrade1_8()
{
	calendar_upgrade1_7_010();

	return $GLOBALS['setup_info']['calendar']['currentver'] = '1.9.001';
}

/**
 * Convert bool column cal_deleted with egw_api_content_history table to a unix timestamp
 *
 * Using cal_modified as deleted-timestamp, as querying it from SyncML tables creates too many problems (refresh table stops before copying all rows!)
 *
 * @return string
 */
function calendar_upgrade1_9_001()
{
	// delete in the past wrongly created entries for a single recurrence, which mess up the update, beside being wrong anyway
	$GLOBALS['egw_setup']->db->delete('egw_api_content_history',array(
		'sync_appname' => 'calendar',
		"sync_contentid LIKE '%:%'",
	), __LINE__, __FILE__);

	/* done by RefreshTable() anyway
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_cal','cal_deleted',array(
		'type' => 'int',
		'precision' => '8',
		'comment' => 'ts when event was deleted'
	));*/
	$GLOBALS['egw_setup']->oProc->RefreshTable('egw_cal',array(
		'fd' => array(
			'cal_id' => array('type' => 'auto','nullable' => False),
			'cal_uid' => array('type' => 'varchar','precision' => '255','nullable' => False,'comment' => 'unique id of event(-series)'),
			'cal_owner' => array('type' => 'int','precision' => '4','nullable' => False,'comment' => 'event owner / calendar'),
			'cal_category' => array('type' => 'varchar','precision' => '30','comment' => 'category id'),
			'cal_modified' => array('type' => 'int','precision' => '8','comment' => 'ts of last modification'),
			'cal_priority' => array('type' => 'int','precision' => '2','nullable' => False,'default' => '2'),
			'cal_public' => array('type' => 'int','precision' => '2','nullable' => False,'default' => '1','comment' => '1=public, 0=private event'),
			'cal_title' => array('type' => 'varchar','precision' => '255','nullable' => False,'default' => '1'),
			'cal_description' => array('type' => 'text'),
			'cal_location' => array('type' => 'varchar','precision' => '255'),
			'cal_reference' => array('type' => 'int','precision' => '4','nullable' => False,'default' => '0','comment' => 'cal_id of series for exception'),
			'cal_modifier' => array('type' => 'int','precision' => '4','comment' => 'user who last modified event'),
			'cal_non_blocking' => array('type' => 'int','precision' => '2','default' => '0','comment' => '1 for non-blocking events'),
			'cal_special' => array('type' => 'int','precision' => '2','default' => '0'),
			'cal_etag' => array('type' => 'int','precision' => '4','default' => '0','comment' => 'etag for optimistic locking'),
			'cal_creator' => array('type' => 'int','precision' => '4','nullable' => False,'comment' => 'creating user'),
			'cal_created' => array('type' => 'int','precision' => '8','nullable' => False,'comment' => 'creation time of event'),
			'cal_recurrence' => array('type' => 'int','precision' => '8','nullable' => False,'default' => '0','comment' => 'cal_start of original recurrence for exception'),
			'tz_id' => array('type' => 'int','precision' => '4','comment' => 'key into egw_cal_timezones'),
			'cal_deleted' => array('type' => 'int','precision' => '8','comment' => 'ts when event was deleted')
		),
		'pk' => array('cal_id'),
		'fk' => array(),
		'ix' => array('cal_uid','cal_owner','cal_deleted'),
		'uc' => array()
	),array(
		// for deleted rows use cal_modified as deleted date, NULL for not deleted ones
		'cal_deleted' => 'CASE cal_deleted WHEN '.$GLOBALS['egw_setup']->db->quote(true,'bool').' THEN cal_modified ELSE NULL END',
	));

	return $GLOBALS['setup_info']['calendar']['currentver'] = '1.9.002';
}


/**
 * Add column to store CalDAV name given by client
 */
function calendar_upgrade1_9_002()
{
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_cal','caldav_name',array(
		'type' => 'varchar',
		'precision' => '64',
		'comment' => 'name part of CalDAV URL, if specified by client'
	));
	$GLOBALS['egw_setup']->db->query($sql='UPDATE egw_cal SET caldav_name='.
		$GLOBALS['egw_setup']->db->concat(
			$GLOBALS['egw_setup']->db->to_varchar('cal_id'),"'.ics'"),__LINE__,__FILE__);

	$GLOBALS['egw_setup']->oProc->CreateIndex('egw_cal','caldav_name');

	return $GLOBALS['setup_info']['calendar']['currentver'] = '1.9.003';
}


/**
 * Add index for cal_modified and cal_user_modified to improve ctag and etag generation on big installtions
 */
function calendar_upgrade1_9_003()
{
	$GLOBALS['egw_setup']->oProc->CreateIndex('egw_cal','cal_modified');
	$GLOBALS['egw_setup']->oProc->CreateIndex('egw_cal_user','cal_user_modified');

	return $GLOBALS['setup_info']['calendar']['currentver'] = '1.9.004';
}

/**
 * Store exceptions as flag in egw_cal_dates.recur_exception, instead of egw_cal_repleats.recur_exception
 *
 * Keeps information of original start in egw_cal_dates (if first recurrance got deleted) and allows for unlimited number of exceptions.
 */
function calendar_upgrade1_9_004()
{
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_cal_dates','recur_exception',array(
		'type' => 'bool',
		'default' => '',
		'null' => false,
		'comment' => 'date is an exception'
	));

	// migrate existing exceptions to egw_cal_dates
	foreach($GLOBALS['egw_setup']->db->select('egw_cal_repeats',
		'egw_cal_repeats.cal_id AS cal_id,egw_cal_repeats.recur_exception AS recur_exception,MIN(cal_start) AS cal_start,MIN(cal_end) AS cal_end',
		'egw_cal_repeats.recur_exception IS NOT NULL', __LINE__, __FILE__, false,
		'GROUP BY egw_cal_repeats.cal_id,egw_cal_repeats.recur_exception', 'calendar', '',
		'JOIN egw_cal_dates ON egw_cal_repeats.cal_id=egw_cal_dates.cal_id') as $row)
	{
		foreach($row['recur_exception'] ? explode(',', $row['recur_exception']) : array() as $recur_exception)
		{
			$GLOBALS['egw_setup']->db->insert('egw_cal_dates', array(
				'cal_id' => $row['cal_id'],
				'cal_start' => $recur_exception,
				'cal_end' => $recur_exception+$row['cal_end']-$row['cal_start'],
				'recur_exception' => true,
			), false, __LINE__, __FILE__, 'calendar');
		}
	}
	$GLOBALS['egw_setup']->oProc->CreateIndex('egw_cal_dates', array('recur_exception', 'cal_id'));

	$GLOBALS['egw_setup']->oProc->DropColumn('egw_cal_repeats', array(
		'fd' => array(
			'cal_id' => array('type' => 'int','precision' => '4','nullable' => False),
			'recur_type' => array('type' => 'int','precision' => '2','nullable' => False),
			'recur_enddate' => array('type' => 'int','precision' => '8'),
			'recur_interval' => array('type' => 'int','precision' => '2','default' => '1'),
			'recur_data' => array('type' => 'int','precision' => '2','default' => '1'),
		),
		'pk' => array('cal_id'),
		'fk' => array(),
		'ix' => array(),
		'uc' => array()
	), 'recur_exception');

	return $GLOBALS['setup_info']['calendar']['currentver'] = '1.9.005';
}

/**
 * Try alter description to varchar(16384), to not force temp. tables to disk on MySQL (because of text columns)
 */
function calendar_upgrade1_9_005()
{
	// only alter description to varchar(16384), if it does NOT contain longer input and it can be stored as varchar
	$max_description_length = $GLOBALS['egw']->db->query('SELECT MAX(CHAR_LENGTH(cal_description)) FROM egw_cal')->fetchColumn();
	// returns NULL, if there are no rows!
	if ((int)$max_description_length <= 16384 && $GLOBALS['egw_setup']->oProc->max_varchar_length >= 16384)
	{
		$GLOBALS['egw_setup']->oProc->AlterColumn('egw_cal','cal_description',array(
			'type' => 'varchar',
			'precision' => '16384'
		));
	}
	// allow more categories
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_cal','cal_category',array(
		'type' => 'varchar',
		'precision' => '64',
		'comment' => 'category id(s)'
	));
	// remove silly default of 1
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_cal','cal_title',array(
		'type' => 'varchar',
		'precision' => '255',
		'nullable' => False
	));
	return $GLOBALS['setup_info']['calendar']['currentver'] = '1.9.006';
}

/**
 * Add range_start and range_end columns, drop egw_cal_repeats.recur_enddate columnd
 */
function calendar_upgrade1_9_006()
{
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_cal','range_start',array(
		'type' => 'int',
		'precision' => '8',
		'nullable' => False,
		'comment' => 'startdate (of range)'
	));
	$GLOBALS['egw_setup']->db->query('UPDATE egw_cal SET range_start = (SELECT MIN(cal_start) FROM egw_cal_dates WHERE egw_cal_dates.cal_id=egw_cal.cal_id)', __LINE__, __FILE__);

	$GLOBALS['egw_setup']->oProc->AddColumn('egw_cal','range_end',array(
		'type' => 'int',
		'precision' => '8',
		'comment' => 'enddate (of range, UNTIL of RRULE)'
	));
	$GLOBALS['egw_setup']->db->query('UPDATE egw_cal SET range_end = (SELECT MIN(cal_end) FROM egw_cal_dates WHERE egw_cal_dates.cal_id=egw_cal.cal_id)', __LINE__, __FILE__);
	$GLOBALS['egw_setup']->db->query('UPDATE egw_cal_repeats SET recur_enddate=null WHERE recur_enddate=0', __LINE__, __FILE__);
	$GLOBALS['egw_setup']->db->query('UPDATE egw_cal,egw_cal_repeats SET egw_cal.range_end=egw_cal_repeats.recur_enddate WHERE egw_cal.cal_id=egw_cal_repeats.cal_id', __LINE__, __FILE__);

	$GLOBALS['egw_setup']->oProc->DropColumn('egw_cal_repeats',array(
		'fd' => array(
			'cal_id' => array('type' => 'int','precision' => '4','nullable' => False),
			'recur_type' => array('type' => 'int','precision' => '2','nullable' => False),
			'recur_interval' => array('type' => 'int','precision' => '2','default' => '1'),
			'recur_data' => array('type' => 'int','precision' => '2','default' => '1')
		),
		'pk' => array('cal_id'),
		'fk' => array(),
		'ix' => array(),
		'uc' => array()
	),'recur_enddate');

	return $GLOBALS['setup_info']['calendar']['currentver'] = '1.9.007';
}

/**
 * Add cal_rrule columns, drop egw_cal_repeats table
 */
/*function calendar_upgrade1_9_007()
{
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_cal','cal_rrule',array(
		'type' => 'varchar',
		'precision' => '255',
		'comment' => 'RRULE for recuring events or NULL'
	));
	foreach($GLOBALS['egw_setup']->db->query('SELECT egw_cal.cal_id AS cal_id,range_start AS start,tz_tzid AS tzid,recur_type,recur_interval,recur_data FROM egw_cal_repeats JOIN egw_cal ON egw_cal.cal_id=egw_cal_repeats.cal_id LEFT JOIN egw_cal_timezones ON egw_cal.tz_id=egw_cal_timezones.tz_id', __LINE__, __FILE__) as $event)
	{
		if (!$event['tzid']) $event['tzid'] = egw_time::$server_timezone->getName();
		$rrule = calendar_rrule::event2rrule($event);
		$rrule_str = '';
		foreach($rrule->generate_rrule('2.0') as $name => $value)
		{
			$rrule_str .= ($rrule_str ? ';' : '').$name.'='.$value;
		}
		//error_log($rrule_str.' '.array2string($event));
		$GLOBALS['egw_setup']->db->update('egw_cal', array(
			'cal_rrule' => $rrule_str,
		), array(
			'cal_id' => $event['cal_id'],
		), __LINE__, __FILE__, 'calendar');
	}
	$GLOBALS['egw_setup']->oProc->DropTable('egw_cal_repeats');

	return $GLOBALS['setup_info']['calendar']['currentver'] = '1.9.008';
}
*/
