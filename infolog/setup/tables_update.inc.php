<?php
/**
 * EGroupware - InfoLog - Setup
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package infolog
 * @subpackage setup
 * @copyright (c) 2003-10 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

function infolog_upgrade0_9_11()
{
	$GLOBALS['egw_setup']->oProc->RenameColumn('phpgw_infolog','info_datecreated','info_datemodified');
	$GLOBALS['egw_setup']->oProc->AddColumn('phpgw_infolog','info_event_id',array(
		'type' => 'int',
		'precision' => '4',
		'default' => '0',
		'nullable' => False
	));

	return $GLOBALS['setup_info']['infolog']['currentver'] = '0.9.15.001';
}


function infolog_upgrade0_9_15_001()
{
	$GLOBALS['egw_setup']->oProc->CreateTable('phpgw_links',array(
		'fd' => array(
			'link_id' => array('type' => 'auto','nullable' => False),
			'link_app1' => array('type' => 'varchar','precision' => '25','nullable' => False),
			'link_id1' => array('type' => 'varchar','precision' => '50','nullable' => False),
			'link_app2' => array('type' => 'varchar','precision' => '25','nullable' => False),
			'link_id2' => array('type' => 'varchar','precision' => '50','nullable' => False),
			'link_remark' => array('type' => 'varchar','precision' => '50','nullable' => True),
			'link_lastmod' => array('type' => 'int','precision' => '4','nullable' => False),
			'link_owner' => array('type' => 'int','precision' => '4','nullable' => False)
		),
		'pk' => array('link_id'),
		'fk' => array(),
		'ix' => array(),
		'uc' => array()
	));

	return $GLOBALS['setup_info']['infolog']['currentver'] = '0.9.15.002';
}


function infolog_upgrade0_9_15_002()
{
	//echo "<p>infolog_upgrade0_9_15_002</p>\n";
	$insert = 'INSERT INTO phpgw_links (link_app1,link_id1,link_app2,link_id2,link_remark,link_lastmod,link_owner) ';
	$select = "SELECT 'infolog',info_id,'addressbook',info_addr_id,info_from,info_datemodified,info_owner FROM phpgw_infolog WHERE info_addr_id != 0";
	//echo "<p>copying address-links: $insert.$select</p>\n";
	$GLOBALS['egw_setup']->oProc->query($insert.$select);
	$select = "SELECT 'infolog',info_id,'projects',info_proj_id,'',info_datemodified,info_owner FROM phpgw_infolog WHERE info_proj_id != 0";
	//echo "<p>copying projects-links: $insert.$select</p>\n";
	$GLOBALS['egw_setup']->oProc->query($insert.$select);
	$select = "SELECT 'infolog',info_id,'calendar',info_event_id,'',info_datemodified,info_owner FROM phpgw_infolog WHERE info_event_id != 0";
	//echo "<p>copying calendar-links: $insert.$select</p>\n";
	$GLOBALS['egw_setup']->oProc->query($insert.$select);

	$GLOBALS['egw_setup']->oProc->DropColumn('phpgw_infolog',array(
		'fd' => array(
			'info_id' => array('type' => 'auto','nullable' => False),
			'info_type' => array('type' => 'varchar','precision' => '255','default' => 'task','nullable' => False),
			'info_proj_id' => array('type' => 'int','precision' => '4','default' => '0','nullable' => False),
			'info_from' => array('type' => 'varchar','precision' => '64','nullable' => True),
			'info_addr' => array('type' => 'varchar','precision' => '64','nullable' => True),
			'info_subject' => array('type' => 'varchar','precision' => '64','nullable' => True),
			'info_des' => array('type' => 'text','nullable' => True),
			'info_owner' => array('type' => 'int','precision' => '4','nullable' => False),
			'info_responsible' => array('type' => 'int','precision' => '4','default' => '0','nullable' => False),
			'info_access' => array('type' => 'varchar','precision' => '10','nullable' => True,'default' => 'public'),
			'info_cat' => array('type' => 'int','precision' => '4','default' => '0','nullable' => False),
			'info_datemodified' => array('type' => 'int','precision' => '4','nullable' => False),
			'info_startdate' => array('type' => 'int','precision' => '4','default' => '0','nullable' => False),
			'info_enddate' => array('type' => 'int','precision' => '4','default' => '0','nullable' => False),
			'info_id_parent' => array('type' => 'int','precision' => '4','default' => '0','nullable' => False),
			'info_pri' => array('type' => 'varchar','precision' => '255','nullable' => True,'default' => 'normal'),
			'info_time' => array('type' => 'int','precision' => '4','default' => '0','nullable' => False),
			'info_bill_cat' => array('type' => 'int','precision' => '4','default' => '0','nullable' => False),
			'info_status' => array('type' => 'varchar','precision' => '255','nullable' => True,'default' => 'done'),
			'info_confirm' => array('type' => 'varchar','precision' => '255','nullable' => True,'default' => 'not'),
			'info_event_id' => array('type' => 'int','precision' => '4','default' => '0','nullable' => False)
		),
		'pk' => array('info_id'),
		'fk' => array(),
		'ix' => array(),
		'uc' => array()
	),'info_addr_id');
	$GLOBALS['egw_setup']->oProc->DropColumn('phpgw_infolog',array(
		'fd' => array(
			'info_id' => array('type' => 'auto','nullable' => False),
			'info_type' => array('type' => 'varchar','precision' => '255','default' => 'task','nullable' => False),
			'info_from' => array('type' => 'varchar','precision' => '64','nullable' => True),
			'info_addr' => array('type' => 'varchar','precision' => '64','nullable' => True),
			'info_subject' => array('type' => 'varchar','precision' => '64','nullable' => True),
			'info_des' => array('type' => 'text','nullable' => True),
			'info_owner' => array('type' => 'int','precision' => '4','nullable' => False),
			'info_responsible' => array('type' => 'int','precision' => '4','default' => '0','nullable' => False),
			'info_access' => array('type' => 'varchar','precision' => '10','nullable' => True,'default' => 'public'),
			'info_cat' => array('type' => 'int','precision' => '4','default' => '0','nullable' => False),
			'info_datemodified' => array('type' => 'int','precision' => '4','nullable' => False),
			'info_startdate' => array('type' => 'int','precision' => '4','default' => '0','nullable' => False),
			'info_enddate' => array('type' => 'int','precision' => '4','default' => '0','nullable' => False),
			'info_id_parent' => array('type' => 'int','precision' => '4','default' => '0','nullable' => False),
			'info_pri' => array('type' => 'varchar','precision' => '255','nullable' => True,'default' => 'normal'),
			'info_time' => array('type' => 'int','precision' => '4','default' => '0','nullable' => False),
			'info_bill_cat' => array('type' => 'int','precision' => '4','default' => '0','nullable' => False),
			'info_status' => array('type' => 'varchar','precision' => '255','nullable' => True,'default' => 'done'),
			'info_confirm' => array('type' => 'varchar','precision' => '255','nullable' => True,'default' => 'not'),
			'info_event_id' => array('type' => 'int','precision' => '4','default' => '0','nullable' => False)
		),
		'pk' => array('info_id'),
		'fk' => array(),
		'ix' => array(),
		'uc' => array()
	),'info_proj_id');
	$GLOBALS['egw_setup']->oProc->DropColumn('phpgw_infolog',array(
		'fd' => array(
			'info_id' => array('type' => 'auto','nullable' => False),
			'info_type' => array('type' => 'varchar','precision' => '255','default' => 'task','nullable' => False),
			'info_from' => array('type' => 'varchar','precision' => '64','nullable' => True),
			'info_addr' => array('type' => 'varchar','precision' => '64','nullable' => True),
			'info_subject' => array('type' => 'varchar','precision' => '64','nullable' => True),
			'info_des' => array('type' => 'text','nullable' => True),
			'info_owner' => array('type' => 'int','precision' => '4','nullable' => False),
			'info_responsible' => array('type' => 'int','precision' => '4','default' => '0','nullable' => False),
			'info_access' => array('type' => 'varchar','precision' => '10','nullable' => True,'default' => 'public'),
			'info_cat' => array('type' => 'int','precision' => '4','default' => '0','nullable' => False),
			'info_datemodified' => array('type' => 'int','precision' => '4','nullable' => False),
			'info_startdate' => array('type' => 'int','precision' => '4','default' => '0','nullable' => False),
			'info_enddate' => array('type' => 'int','precision' => '4','default' => '0','nullable' => False),
			'info_id_parent' => array('type' => 'int','precision' => '4','default' => '0','nullable' => False),
			'info_pri' => array('type' => 'varchar','precision' => '255','nullable' => True,'default' => 'normal'),
			'info_time' => array('type' => 'int','precision' => '4','default' => '0','nullable' => False),
			'info_bill_cat' => array('type' => 'int','precision' => '4','default' => '0','nullable' => False),
			'info_status' => array('type' => 'varchar','precision' => '255','nullable' => True,'default' => 'done'),
			'info_confirm' => array('type' => 'varchar','precision' => '255','nullable' => True,'default' => 'not')
		),
		'pk' => array('info_id'),
		'fk' => array(),
		'ix' => array(),
		'uc' => array()
	),'info_event_id');

	return $GLOBALS['setup_info']['infolog']['currentver'] = '0.9.15.003';
}


function infolog_upgrade0_9_15_003()
{
	$GLOBALS['egw_setup']->oProc->AlterColumn('phpgw_infolog','info_type',array(
		'type' => 'varchar',
		'precision' => '10',
		'nullable' => False,
		'default' => 'task'
	));
	$GLOBALS['egw_setup']->oProc->AlterColumn('phpgw_infolog','info_pri',array(
		'type' => 'varchar',
		'precision' => '10',
		'nullable' => True,
		'default' => 'normal'
	));
	$GLOBALS['egw_setup']->oProc->AlterColumn('phpgw_infolog','info_status',array(
		'type' => 'varchar',
		'precision' => '10',
		'nullable' => True,
		'default' => 'done'
	));
	$GLOBALS['egw_setup']->oProc->AlterColumn('phpgw_infolog','info_confirm',array(
		'type' => 'varchar',
		'precision' => '10',
		'nullable' => True,
		'default' => 'not'
	));
	$GLOBALS['egw_setup']->oProc->AddColumn('phpgw_infolog','info_modifier',array(
		'type' => 'int',
		'precision' => '4',
		'nullable' => False,
		'default' => '0'
	));
	$GLOBALS['egw_setup']->oProc->AddColumn('phpgw_infolog','info_link_id',array(
		'type' => 'int',
		'precision' => '4',
		'nullable' => False,
		'default' => '0'
	));

	// ORDER BY link_app2 DESC gives addressbook the highes precedens, use ASC for projects
	$GLOBALS['egw_setup']->oProc->query("SELECT link_id,link_id1 FROM phpgw_links WHERE link_app1='infolog' ORDER BY link_app2 DESC");
	$links = array();
	while ($GLOBALS['egw_setup']->oProc->next_record())
	{
		$links[$GLOBALS['egw_setup']->oProc->f(1)] = $GLOBALS['egw_setup']->oProc->f(0);
	}
	reset($links);
	while (list($info_id,$link_id) = each($links))
	{
		$GLOBALS['egw_setup']->oProc->query("UPDATE phpgw_infolog SET info_link_id=$link_id WHERE info_id=$info_id");
	}

	return $GLOBALS['setup_info']['infolog']['currentver'] = '0.9.15.004';
}


function infolog_upgrade0_9_15_004()
{
	// this update correctes wrong escapes of ' and " in the past
	//
	$db2 = $GLOBALS['egw_setup']->db;	// we need a 2. result-set

	$to_correct = array('info_from','info_subject','info_des');
	foreach ($to_correct as $col)
	{
		$GLOBALS['egw_setup']->oProc->query("SELECT info_id,$col FROM phpgw_infolog WHERE $col LIKE '%\\'%' OR $col LIKE '%\"%'");
		while ($GLOBALS['egw_setup']->oProc->next_record())
		{
			$db2->query("UPDATE phpgw_infolog SET $col='".$db2->db_addslashes(stripslashes($GLOBALS['egw_setup']->oProc->f($col))).
				"' WHERE info_id=".$GLOBALS['egw_setup']->oProc->f('info_id'));
		}
	}

	return $GLOBALS['setup_info']['infolog']['currentver'] = '0.9.15.005';
}


function infolog_upgrade0_9_15_005()
{
	$GLOBALS['egw_setup']->oProc->CreateTable('phpgw_infolog_extra',array(
		'fd' => array(
			'info_id' => array('type' => 'int','precision' => '4','nullable' => False),
			'info_extra_name' => array('type' => 'varchar','precision' => '32','nullable' => False),
			'info_extra_value' => array('type' => 'varchar','precision' => '255','nullable' => False,'default' => '')
		),
		'pk' => array('info_id','info_extra_name'),
		'fk' => array(),
		'ix' => array(),
		'uc' => array()
	));

	return $GLOBALS['setup_info']['infolog']['currentver'] = '0.9.15.006';
}


// the following series of updates add some indices, to speedup the selects
function infolog_upgrade0_9_15_006()
{
	$GLOBALS['egw_setup']->oProc->RefreshTable('phpgw_links',array(
		'fd' => array(
			'link_id' => array('type' => 'auto','nullable' => False),
			'link_app1' => array('type' => 'varchar','precision' => '25','nullable' => False),
			'link_id1' => array('type' => 'varchar','precision' => '50','nullable' => False),
			'link_app2' => array('type' => 'varchar','precision' => '25','nullable' => False),
			'link_id2' => array('type' => 'varchar','precision' => '50','nullable' => False),
			'link_remark' => array('type' => 'varchar','precision' => '50'),
			'link_lastmod' => array('type' => 'int','precision' => '4','nullable' => False),
			'link_owner' => array('type' => 'int','precision' => '4','nullable' => False)
		),
		'pk' => array('link_id'),
		'fk' => array(),
		'ix' => array(array('link_app1','link_id1','link_lastmod'),array('link_app2','link_id2','link_lastmod')),
		'uc' => array()
	));

	return $GLOBALS['setup_info']['infolog']['currentver'] = '0.9.15.007';
}


function infolog_upgrade0_9_15_007()
{
	$GLOBALS['egw_setup']->oProc->RefreshTable('phpgw_infolog',array(
		'fd' => array(
			'info_id' => array('type' => 'auto','nullable' => False),
			'info_type' => array('type' => 'varchar','precision' => '10','nullable' => False,'default' => 'task'),
			'info_from' => array('type' => 'varchar','precision' => '64'),
			'info_addr' => array('type' => 'varchar','precision' => '64'),
			'info_subject' => array('type' => 'varchar','precision' => '64'),
			'info_des' => array('type' => 'text'),
			'info_owner' => array('type' => 'int','precision' => '4','nullable' => False),
			'info_responsible' => array('type' => 'int','precision' => '4','nullable' => False,'default' => '0'),
			'info_access' => array('type' => 'varchar','precision' => '10','default' => 'public'),
			'info_cat' => array('type' => 'int','precision' => '4','nullable' => False,'default' => '0'),
			'info_datemodified' => array('type' => 'int','precision' => '4','nullable' => False),
			'info_startdate' => array('type' => 'int','precision' => '4','nullable' => False,'default' => '0'),
			'info_enddate' => array('type' => 'int','precision' => '4','nullable' => False,'default' => '0'),
			'info_id_parent' => array('type' => 'int','precision' => '4','nullable' => False,'default' => '0'),
			'info_pri' => array('type' => 'varchar','precision' => '10','default' => 'normal'),
			'info_time' => array('type' => 'int','precision' => '4','nullable' => False,'default' => '0'),
			'info_bill_cat' => array('type' => 'int','precision' => '4','nullable' => False,'default' => '0'),
			'info_status' => array('type' => 'varchar','precision' => '10','default' => 'done'),
			'info_confirm' => array('type' => 'varchar','precision' => '10','default' => 'not'),
			'info_modifier' => array('type' => 'int','precision' => '4','nullable' => False,'default' => '0'),
			'info_link_id' => array('type' => 'int','precision' => '4','nullable' => False,'default' => '0')
		),
		'pk' => array('info_id'),
		'fk' => array(),
		'ix' => array(array('info_owner','info_responsible','info_status','info_startdate'),array('info_id_parent','info_owner','info_responsible','info_status','info_startdate')),
		'uc' => array()
	));

	// we dont need to do update 0.9.15.008, as UpdateSequenze is called now by RefreshTable
	return $GLOBALS['setup_info']['infolog']['currentver'] = '1.0.0';
}


function infolog_upgrade0_9_15_008()
{
	// update the sequenzes for refreshed tables (postgres only)
	$GLOBALS['egw_setup']->oProc->UpdateSequence('phpgw_infolog','info_id');
	$GLOBALS['egw_setup']->oProc->UpdateSequence('phpgw_links','link_id');

	return $GLOBALS['setup_info']['infolog']['currentver'] = '1.0.0';
}


function infolog_upgrade1_0_0()
{
	// longer columns to cope with multibyte charsets
	$GLOBALS['egw_setup']->oProc->AlterColumn('phpgw_infolog','info_type',array(
		'type' => 'varchar',
		'precision' => '40',
		'nullable' => False,
		'default' => 'task'
	));
	$GLOBALS['egw_setup']->oProc->AlterColumn('phpgw_infolog','info_from',array(
		'type' => 'varchar',
		'precision' => '255'
	));
	$GLOBALS['egw_setup']->oProc->AlterColumn('phpgw_infolog','info_addr',array(
		'type' => 'varchar',
		'precision' => '255'
	));
	$GLOBALS['egw_setup']->oProc->AlterColumn('phpgw_infolog','info_subject',array(
		'type' => 'varchar',
		'precision' => '255'
	));
	$GLOBALS['egw_setup']->oProc->AlterColumn('phpgw_infolog','info_status',array(
		'type' => 'varchar',
		'precision' => '40',
		'default' => 'done'
	));

	return $GLOBALS['setup_info']['infolog']['currentver'] = '1.0.0.001';
}


function infolog_upgrade1_0_0_001()
{
	$GLOBALS['egw_setup']->oProc->RenameColumn('phpgw_infolog','info_time','info_planned_time');
	$GLOBALS['egw_setup']->oProc->RenameColumn('phpgw_infolog','info_bill_cat','info_used_time');
	// timestamps have to be 8byte ints
	$GLOBALS['egw_setup']->oProc->AlterColumn('phpgw_infolog','info_datemodified',array(
		'type' => 'int',
		'precision' => '8',
		'nullable' => False
	));
	$GLOBALS['egw_setup']->oProc->AlterColumn('phpgw_infolog','info_startdate',array(
		'type' => 'int',
		'precision' => '8',
		'nullable' => False,
		'default' => '0'
	));
	$GLOBALS['egw_setup']->oProc->AlterColumn('phpgw_infolog','info_enddate',array(
		'type' => 'int',
		'precision' => '8',
		'nullable' => False,
		'default' => '0'
	));

	// setting numerical priority 3=urgent, 2=high, 1=normal, 0=
	$GLOBALS['egw_setup']->oProc->AddColumn('phpgw_infolog','info_priority',array(
		'type' => 'int',
		'precision' => '2',
		'default' => '1'
	));
	$GLOBALS['egw_setup']->oProc->query("UPDATE phpgw_infolog SET info_priority=(CASE WHEN info_pri='urgent' THEN 3 WHEN info_pri='high' THEN 2 WHEN info_pri='low' THEN 0 ELSE 1 END)",__LINE__,__FILE__);

	$GLOBALS['egw_setup']->oProc->DropColumn('phpgw_infolog',array(
		'fd' => array(
			'info_id' => array('type' => 'auto','nullable' => False),
			'info_type' => array('type' => 'varchar','precision' => '40','nullable' => False,'default' => 'task'),
			'info_from' => array('type' => 'varchar','precision' => '255'),
			'info_addr' => array('type' => 'varchar','precision' => '255'),
			'info_subject' => array('type' => 'varchar','precision' => '255'),
			'info_des' => array('type' => 'text'),
			'info_owner' => array('type' => 'int','precision' => '4','nullable' => False),
			'info_responsible' => array('type' => 'int','precision' => '4','nullable' => False,'default' => '0'),
			'info_access' => array('type' => 'varchar','precision' => '10','default' => 'public'),
			'info_cat' => array('type' => 'int','precision' => '4','nullable' => False,'default' => '0'),
			'info_datemodified' => array('type' => 'int','precision' => '8','nullable' => False),
			'info_startdate' => array('type' => 'int','precision' => '8','nullable' => False,'default' => '0'),
			'info_enddate' => array('type' => 'int','precision' => '8','nullable' => False,'default' => '0'),
			'info_id_parent' => array('type' => 'int','precision' => '4','nullable' => False,'default' => '0'),
			'info_planned_time' => array('type' => 'int','precision' => '4','nullable' => False,'default' => '0'),
			'info_used_time' => array('type' => 'int','precision' => '4','nullable' => False,'default' => '0'),
			'info_status' => array('type' => 'varchar','precision' => '40','default' => 'done'),
			'info_confirm' => array('type' => 'varchar','precision' => '10','default' => 'not'),
			'info_modifier' => array('type' => 'int','precision' => '4','nullable' => False,'default' => '0'),
			'info_link_id' => array('type' => 'int','precision' => '4','nullable' => False,'default' => '0'),
			'info_priority' => array('type' => 'int','precision' => '2','default' => '1')
		),
		'pk' => array('info_id'),
		'fk' => array(),
		'ix' => array(array('info_owner','info_responsible','info_status','info_startdate'),array('info_id_parent','info_owner','info_responsible','info_status','info_startdate')),
		'uc' => array()
	),'info_pri');

	$GLOBALS['egw_setup']->oProc->RenameTable('phpgw_infolog','egw_infolog');
	$GLOBALS['egw_setup']->oProc->RenameTable('phpgw_infolog_extra','egw_infolog_extra');
	// only rename links table, if it has not been moved into the API and therefor been already renamed by the API update
	if ($GLOBALS['egw_setup']->oProc->GetTableDefinition('phpgw_links'))
	{
		$GLOBALS['egw_setup']->oProc->RenameTable('phpgw_links','egw_links');
	}

	return $GLOBALS['setup_info']['infolog']['currentver'] = '1.0.1.001';
}


function infolog_upgrade1_0_1_001()
{
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_infolog','info_responsible',array(
		'type' => 'varchar',
		'precision' => '255',
		'nullable' => False,
		'default' => '0'
	));

	return $GLOBALS['setup_info']['infolog']['currentver'] = '1.0.1.002';
}


function infolog_upgrade1_0_1_002()
{
	return $GLOBALS['setup_info']['infolog']['currentver'] = '1.2';
}


function infolog_upgrade1_2()
{
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_infolog','pl_id',array(
		'type' => 'int',
		'precision' => '4'
	));

	$GLOBALS['egw_setup']->oProc->AddColumn('egw_infolog','info_price',array(
		'type' => 'float',
		'precision' => '8'
	));

	return $GLOBALS['setup_info']['infolog']['currentver'] = '1.2.001';
}


function infolog_upgrade1_2_001()
{
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_infolog','info_percent',array(
		'type' => 'int',
		'precision' => '2',
		'default' => '0'
	));
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_infolog','info_datecompleted',array(
		'type' => 'int',
		'precision' => '8'
	));
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_infolog','info_location',array(
		'type' => 'varchar',
		'precision' => '255'
	));

	// all not explicit named stati have the default percent 0
	$GLOBALS['egw_setup']->oProc->query("UPDATE egw_infolog SET info_percent=10 WHERE info_status='ongoing'",__LINE__,__FILE__);
	$GLOBALS['egw_setup']->oProc->query("UPDATE egw_infolog SET info_percent=50 WHERE info_status='will-call'",__LINE__,__FILE__);

	for($p = 0; $p <= 90; $p += 10)
	{
		$GLOBALS['egw_setup']->oProc->query("UPDATE egw_infolog SET info_percent=$p,info_status='".(!$p ? 'not-started' : 'ongoing').
			"' WHERE info_status = '$p%'",__LINE__,__FILE__);
	}
	$GLOBALS['egw_setup']->oProc->query("UPDATE egw_infolog SET info_datecompleted=info_datemodified,info_percent=100 WHERE info_status IN ('done','billed','100%')",__LINE__,__FILE__);

	// remove the percentages from the custom stati, if they exist
	$config =& CreateObject('phpgwapi.config','infolog');
	$config->read_repository();
	if (is_array($config->config_data['status']['task']))
	{
		$config->config_data['status']['task'] = array_diff($config->config_data['status']['task'],
			array('0%','10%','20%','30%','40%','50%','60%','70%','80%','90%','100%'));
		$config->save_repository();
	}
	return $GLOBALS['setup_info']['infolog']['currentver'] = '1.2.002';
}


function infolog_upgrade1_2_002()
{
	// change the phone-status: call --> not-started, will-call --> ongoing to be able to sync them
	$GLOBALS['egw_setup']->oProc->query("UPDATE egw_infolog SET info_status='not-started' WHERE info_status='call'",__LINE__,__FILE__);
	$GLOBALS['egw_setup']->oProc->query("UPDATE egw_infolog SET info_status='ongoing' WHERE info_status='will-call'",__LINE__,__FILE__);

	// remove the call and will-call from the custom stati, if they exist
	$config =& CreateObject('phpgwapi.config','infolog');
	$config->read_repository();
	if (is_array($config->config_data['status']['phone']))
	{
		unset($config->config_data['status']['phone']['call']);
		unset($config->config_data['status']['phone']['will-call']);

		$config->save_repository();
	}
	return $GLOBALS['setup_info']['infolog']['currentver'] = '1.2.003';
}


function infolog_upgrade1_2_003()
{
	// fix wrong info_responsible='' --> '0'
	$GLOBALS['egw_setup']->oProc->query("UPDATE egw_infolog SET info_responsible='0' WHERE info_responsible=''",__LINE__,__FILE__);

	return $GLOBALS['setup_info']['infolog']['currentver'] = '1.2.004';
}


function infolog_upgrade1_2_004()
{
	// column to save if from contains a custom text
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_infolog','info_custom_from',array(
		'type' => 'int',
		'precision' => '1',
	));

	return $GLOBALS['setup_info']['infolog']['currentver'] = '1.3.001';
}


function infolog_upgrade1_3_001()
{
	return $GLOBALS['setup_info']['infolog']['currentver'] = '1.4';
}


function infolog_upgrade1_4()
{
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_infolog','info_uid',array(
		'type' => 'varchar',
		'precision' => '255'
	));
	$GLOBALS['egw_setup']->db->query("SELECT config_value FROM egw_config WHERE config_app='phpgwapi' AND config_name='install_id'",__LINE__,__FILE__);
	$install_id = $GLOBALS['egw_setup']->db->next_record() ? $GLOBALS['egw_setup']->db->f(0) : md5(time());
	$GLOBALS['egw_setup']->db->query('UPDATE egw_infolog SET info_uid='.$GLOBALS['egw_setup']->db->concat("'infolog-'",'info_id',"'-$install_id'"),__LINE__,__FILE__);

	return $GLOBALS['setup_info']['infolog']['currentver'] = '1.5.001';
}


/**
 * Fix missing info_from values, caused by a (fixed) bug
 *
 * @return string version
 */
function infolog_upgrade1_5_001()
{
	foreach($GLOBALS['egw_setup']->db->select('egw_infolog','info_id,info_link_id,org_name,adr_one_locality,n_family,n_given',array(
		'info_from' => '',
		'info_link_id > 0',
	),__LINE__,__FILE__,false,'','infolog',0,
	" JOIN egw_links ON info_link_id=link_id AND link_app2='addressbook'".
	" JOIN egw_addressbook ON contact_id=link_id2") as $row)
	{
		$from = ($row['org_name'] ? $row['org_name'].', '.$row['adr_one_locality'].': ' : '').
			$row['n_family'].($row['n_given'] ? ', '.$row['n_given'] : '');
		$GLOBALS['egw_setup']->db->update('egw_infolog',array('info_from' => $from),
			array('info_id' => $row['info_id']),__LINE__,__FILE__,'infolog');
	}
	return $GLOBALS['setup_info']['infolog']['currentver'] = '1.5.002';
}


/**
 * make customfield names varchar(64) and values text
 *
 * @return string version
 */
function infolog_upgrade1_5_002()
{
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_infolog_extra','info_extra_name',array(
		'type' => 'varchar',
		'precision' => '64',
		'nullable' => False
	));
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_infolog_extra','info_extra_value',array(
		'type' => 'text',
		'nullable' => False
	));

	return $GLOBALS['setup_info']['infolog']['currentver'] = '1.5.003';
}


/**
 * Add re-planned time column
 *
 * @return string version
 */
function infolog_upgrade1_5_003()
{
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_infolog','info_replanned_time',array(
		'type' => 'int',
		'precision' => '4',
		'nullable' => False,
		'default' => '0'
	));

	return $GLOBALS['setup_info']['infolog']['currentver'] = '1.5.004';
}


/**
 * 1.6 release
 *
 * @return string
 */
function infolog_upgrade1_5_004()
{
	return $GLOBALS['setup_info']['infolog']['currentver'] = '1.6';
}


function infolog_upgrade1_6()
{
	return $GLOBALS['setup_info']['infolog']['currentver'] = '1.8';
}

function infolog_upgrade1_8()
{
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_infolog','info_cc',array(
		'type' => 'varchar',
		'precision' => '255'
	));

	return $GLOBALS['setup_info']['infolog']['currentver'] = '1.9.001';
}

