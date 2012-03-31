<?php
/**
 * eGroupWare - resources
 * http://www.egroupware.org
 * Created by eTemplates DB-Tools written by ralfbecker@outdoor-training.de
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package resources
 * @subpackage setup
 * @author Cornelius Weiss <egw@von-und-zu-weiss.de>
 * @version $Id$
 */

function resources_upgrade0_0_1_008()
{
	$GLOBALS['phpgw_setup']->oProc->AddColumn('egw_resources','picture_src',array(
		'type' => 'varchar',
		'precision' => '20'
	));

	$GLOBALS['setup_info']['resources']['currentver'] = '0.0.1.012';
	return $GLOBALS['setup_info']['resources']['currentver'];
}


function resources_upgrade0_0_1_012()
{
	$GLOBALS['phpgw_setup']->oProc->AddColumn('egw_resources','picture_thumb',array(
		'type' => 'blob'
	));

	$GLOBALS['setup_info']['resources']['currentver'] = '0.0.1.013';
	return $GLOBALS['setup_info']['resources']['currentver'];
}


function resources_upgrade0_0_1_013()
{
	$GLOBALS['phpgw_setup']->oProc->DropColumn('egw_resources',array(
		'fd' => array(
			'id' => array('type' => 'auto'),
			'name' => array('type' => 'varchar','precision' => '100'),
			'short_description' => array('type' => 'varchar','precision' => '100'),
			'cat_id' => array('type' => 'int','precision' => '11','nullable' => False),
			'quantity' => array('type' => 'int','precision' => '11'),
			'useable' => array('type' => 'int','precision' => '11'),
			'location' => array('type' => 'varchar','precision' => '100'),
			'bookable' => array('type' => 'varchar','precision' => '1'),
			'buyable' => array('type' => 'varchar','precision' => '1'),
			'prize' => array('type' => 'varchar','precision' => '200'),
			'long_description' => array('type' => 'longtext'),
			'accessories' => array('type' => 'varchar','precision' => '50'),
			'picture_src' => array('type' => 'varchar','precision' => '20'),
			'picture_thumb' => array('type' => 'blob')
		),
		'pk' => array('id'),
		'fk' => array(),
		'ix' => array(),
		'uc' => array()
	),'picture');
	$GLOBALS['phpgw_setup']->oProc->DropColumn('egw_resources',array(
		'fd' => array(
			'id' => array('type' => 'auto'),
			'name' => array('type' => 'varchar','precision' => '100'),
			'short_description' => array('type' => 'varchar','precision' => '100'),
			'cat_id' => array('type' => 'int','precision' => '11','nullable' => False),
			'quantity' => array('type' => 'int','precision' => '11'),
			'useable' => array('type' => 'int','precision' => '11'),
			'location' => array('type' => 'varchar','precision' => '100'),
			'bookable' => array('type' => 'varchar','precision' => '1'),
			'buyable' => array('type' => 'varchar','precision' => '1'),
			'prize' => array('type' => 'varchar','precision' => '200'),
			'long_description' => array('type' => 'longtext'),
			'accessories' => array('type' => 'varchar','precision' => '50'),
			'picture_src' => array('type' => 'varchar','precision' => '20')
		),
		'pk' => array('id'),
		'fk' => array(),
		'ix' => array(),
		'uc' => array()
	),'picture_thumb');

	$GLOBALS['setup_info']['resources']['currentver'] = '0.0.1.014';
	return $GLOBALS['setup_info']['resources']['currentver'];
}


function resources_upgrade0_0_1_014()
{
	$GLOBALS['phpgw_setup']->oProc->AlterColumn('egw_resources','quantity',array(
		'type' => 'int',
		'precision' => '11',
		'default' => '1'
	));
	$GLOBALS['phpgw_setup']->oProc->AlterColumn('egw_resources','useable',array(
		'type' => 'int',
		'precision' => '11',
		'default' => '1'
	));

	$GLOBALS['setup_info']['resources']['currentver'] = '0.0.1.015';
	return $GLOBALS['setup_info']['resources']['currentver'];
}


function resources_upgrade0_0_1_015()
{
	$GLOBALS['phpgw_setup']->oProc->AlterColumn('egw_resources','accessories',array(
		'type' => 'varchar',
		'precision' => '100'
	));
	$GLOBALS['phpgw_setup']->oProc->AddColumn('egw_resources','accessory_only',array(
		'type' => 'varchar',
		'precision' => '1',
		'default' => '0'
	));
	$GLOBALS['phpgw_setup']->oProc->AddColumn('egw_resources','relatives',array(
		'type' => 'varchar',
		'precision' => '100'
	));

	$GLOBALS['setup_info']['resources']['currentver'] = '0.0.1.016';
	return $GLOBALS['setup_info']['resources']['currentver'];
}


function resources_upgrade0_0_1_016()
{
	$GLOBALS['phpgw_setup']->oProc->DropColumn('egw_resources',array(
		'fd' => array(
			'id' => array('type' => 'auto'),
			'name' => array('type' => 'varchar','precision' => '100'),
			'short_description' => array('type' => 'varchar','precision' => '100'),
			'cat_id' => array('type' => 'int','precision' => '11','nullable' => False),
			'quantity' => array('type' => 'int','precision' => '11','default' => '1'),
			'useable' => array('type' => 'int','precision' => '11','default' => '1'),
			'location' => array('type' => 'varchar','precision' => '100'),
			'bookable' => array('type' => 'varchar','precision' => '1'),
			'buyable' => array('type' => 'varchar','precision' => '1'),
			'prize' => array('type' => 'varchar','precision' => '200'),
			'long_description' => array('type' => 'longtext'),
			'accessories' => array('type' => 'varchar','precision' => '100'),
			'picture_src' => array('type' => 'varchar','precision' => '20'),
			'relatives' => array('type' => 'varchar','precision' => '100')
		),
		'pk' => array('id'),
		'fk' => array(),
		'ix' => array(),
		'uc' => array()
	),'accessory_only');
	$GLOBALS['phpgw_setup']->oProc->DropColumn('egw_resources',array(
		'fd' => array(
			'id' => array('type' => 'auto'),
			'name' => array('type' => 'varchar','precision' => '100'),
			'short_description' => array('type' => 'varchar','precision' => '100'),
			'cat_id' => array('type' => 'int','precision' => '11','nullable' => False),
			'quantity' => array('type' => 'int','precision' => '11','default' => '1'),
			'useable' => array('type' => 'int','precision' => '11','default' => '1'),
			'location' => array('type' => 'varchar','precision' => '100'),
			'bookable' => array('type' => 'varchar','precision' => '1'),
			'buyable' => array('type' => 'varchar','precision' => '1'),
			'prize' => array('type' => 'varchar','precision' => '200'),
			'long_description' => array('type' => 'longtext'),
			'accessories' => array('type' => 'varchar','precision' => '100'),
			'picture_src' => array('type' => 'varchar','precision' => '20')
		),
		'pk' => array('id'),
		'fk' => array(),
		'ix' => array(),
		'uc' => array()
	),'relatives');
	$GLOBALS['phpgw_setup']->oProc->DropColumn('egw_resources',array(
		'fd' => array(
			'id' => array('type' => 'auto'),
			'name' => array('type' => 'varchar','precision' => '100'),
			'short_description' => array('type' => 'varchar','precision' => '100'),
			'cat_id' => array('type' => 'int','precision' => '11','nullable' => False),
			'quantity' => array('type' => 'int','precision' => '11','default' => '1'),
			'useable' => array('type' => 'int','precision' => '11','default' => '1'),
			'location' => array('type' => 'varchar','precision' => '100'),
			'bookable' => array('type' => 'varchar','precision' => '1'),
			'buyable' => array('type' => 'varchar','precision' => '1'),
			'prize' => array('type' => 'varchar','precision' => '200'),
			'long_description' => array('type' => 'longtext'),
			'picture_src' => array('type' => 'varchar','precision' => '20')
		),
		'pk' => array('id'),
		'fk' => array(),
		'ix' => array(),
		'uc' => array()
	),'accessories');
	$GLOBALS['phpgw_setup']->oProc->AddColumn('egw_resources','accessory_of',array(
		'type' => 'int',
		'precision' => '11',
		'default' => '-1'
	));
	$GLOBALS['setup_info']['resources']['currentver'] = '0.0.1.017';
	return $GLOBALS['setup_info']['resources']['currentver'];
}


function resources_upgrade0_0_1_017()
{
	$GLOBALS['phpgw_setup']->oProc->RenameColumn('egw_resources','id','res_id');
	$GLOBALS['phpgw_setup']->oProc->RefreshTable('egw_resources',array(
		'fd' => array(
			'res_id' => array('type' => 'auto'),
			'name' => array('type' => 'varchar','precision' => '100'),
			'short_description' => array('type' => 'varchar','precision' => '100'),
			'cat_id' => array('type' => 'int','precision' => '11','nullable' => False),
			'quantity' => array('type' => 'int','precision' => '11','default' => '1'),
			'useable' => array('type' => 'int','precision' => '11','default' => '1'),
			'location' => array('type' => 'varchar','precision' => '100'),
			'bookable' => array('type' => 'varchar','precision' => '1'),
			'buyable' => array('type' => 'varchar','precision' => '1'),
			'prize' => array('type' => 'varchar','precision' => '200'),
			'long_description' => array('type' => 'longtext'),
			'picture_src' => array('type' => 'varchar','precision' => '20'),
			'accessory_of' => array('type' => 'int','precision' => '11','default' => '-1')
		),
		'pk' => array('res_id'),
		'fk' => array(),
		'ix' => array(),
		'uc' => array()
	));

	$GLOBALS['setup_info']['resources']['currentver'] = '0.0.1.018';
	return $GLOBALS['setup_info']['resources']['currentver'];
}


function resources_upgrade0_0_1_018()
{
	$GLOBALS['phpgw_setup']->oProc->AddColumn('egw_resources','storage_info',array(
		'type' => 'varchar',
		'precision' => '200'
	));

	$GLOBALS['setup_info']['resources']['currentver'] = '0.0.1.019';
	return $GLOBALS['setup_info']['resources']['currentver'];
}


function resources_upgrade0_0_1_019()
{
	$GLOBALS['phpgw_setup']->oProc->AddColumn('egw_resources','inventory_number',array(
		'type' => 'varchar',
		'precision' => '20'
	));

	$GLOBALS['setup_info']['resources']['currentver'] = '0.0.1.020';
	return $GLOBALS['setup_info']['resources']['currentver'];
}


function resources_upgrade0_0_1_020()
{
	$GLOBALS['egw_setup']->oProc->CreateTable('egw_resources_extra',array(
		'fd' => array(
			'extra_id' => array('type' => 'int','precision' => '4','nullable' => False),
			'extra_name' => array('type' => 'varchar','precision' => '40','nullable' => False),
			'extra_owner' => array('type' => 'int','precision' => '4','nullable' => False,'default' => '-1'),
			'extra_value' => array('type' => 'varchar','precision' => '255','nullable' => False,'default' => '')
		),
		'pk' => array('extra_id','extra_name','extra_owner'),
		'fk' => array(),
		'ix' => array(),
		'uc' => array()
	));

	return $GLOBALS['setup_info']['resources']['currentver'] = '0.0.1.021';
}


function resources_upgrade0_0_1_021()
{
	return $GLOBALS['setup_info']['resources']['currentver'] = '1.2';
}


function resources_upgrade1_2()
{
	return $GLOBALS['setup_info']['resources']['currentver'] = '1.4';
}


/**
 * Move resource pictures into the regular attachmen dirs with name .picture.jpg
 *
 * @return string
 */
function resources_upgrade1_4()
{
	egw_vfs::$is_root = true;
	egw_vfs::load_wrapper('sqlfs');
	if (egw_vfs::is_dir('/resources/pictures'))
	{
		egw_vfs::remove('/resources/pictures/thumbs');	// remove thumb dir incl. thumbnails
		foreach(egw_vfs::find('sqlfs://default/resources/pictures',array('url' => true)) as $url)
		{
			if (is_numeric($id = basename($url,'.jpg')))
			{
				if (!egw_vfs::is_dir($dir = "/apps/resources/$id"))
				{
					egw_vfs::mkdir($dir,0777,STREAM_MKDIR_RECURSIVE);
				}
				rename($url,'sqlfs://default'.$dir.'/.picture.jpg');	// we need to rename on the same wrapper!
			}
		}
		egw_vfs::rmdir('/resources/pictures',0);
		egw_vfs::rmdir('/resources',0);
	}
	return $GLOBALS['setup_info']['resources']['currentver'] = '1.6';
}


function resources_upgrade1_6()
{
	return $GLOBALS['setup_info']['resources']['currentver'] = '1.8';
}


function resources_upgrade1_8()
{
	// add location category required for CalDAV to distinguish between locations and resources
	$GLOBALS['egw_setup']->db->insert($GLOBALS['egw_setup']->cats_table,array('cat_parent' => 0, 'cat_owner' => categories::GLOBAL_ACCOUNT,'cat_access' => 'public','cat_appname' => 'resources','cat_name' => 'Locations','cat_description' => 'This category has been added by setup','last_mod' => time()),false,__LINE__,__FILE__);
	$locations_cat_id = $GLOBALS['egw_setup']->db->get_last_insert_id($GLOBALS['egw_setup']->cats_table,'cat_id');
	config::save_value('location_cats', $locations_cat_id, 'resources');

	// Give default group all rights to this general cat
	$defaultgroup = $GLOBALS['egw_setup']->add_account('Default','Default','Group',False,False);
	$GLOBALS['egw_setup']->add_acl('resources','run',$defaultgroup);
	$GLOBALS['egw_setup']->add_acl('resources',"L$cat_id",$defaultgroup,399);
	$GLOBALS['egw_setup']->add_acl('resources',"L$locations_cat_id",$defaultgroup,399);

	return $GLOBALS['setup_info']['resources']['currentver'] = '1.9.001';
}
