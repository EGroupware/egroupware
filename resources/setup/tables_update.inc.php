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
 */

use EGroupware\Api;
use EGroupware\Api\Vfs;

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
	Vfs::$is_root = true;
	Vfs::load_wrapper('sqlfs');
	if (Vfs::is_dir('/resources/pictures'))
	{
		Vfs::remove('/resources/pictures/thumbs');	// remove thumb dir incl. thumbnails
		foreach(Vfs::find('sqlfs://default/resources/pictures',array('url' => true)) as $url)
		{
			if (is_numeric($id = basename($url,'.jpg')))
			{
				if (!Vfs::is_dir($dir = "/apps/resources/$id"))
				{
					Vfs::mkdir($dir,0777,STREAM_MKDIR_RECURSIVE);
				}
				rename($url,'sqlfs://default'.$dir.'/.picture.jpg');	// we need to rename on the same wrapper!
			}
		}
		Vfs::rmdir('/resources/pictures',0);
		Vfs::rmdir('/resources',0);
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
	$GLOBALS['egw_setup']->db->insert($GLOBALS['egw_setup']->cats_table,array('cat_parent' => 0, 'cat_owner' => Api\Categories::GLOBAL_ACCOUNT,'cat_access' => 'public','cat_appname' => 'resources','cat_name' => 'Locations','cat_description' => 'This category has been added by setup','last_mod' => time()),false,__LINE__,__FILE__);
	$locations_cat_id = $GLOBALS['egw_setup']->db->get_last_insert_id($GLOBALS['egw_setup']->cats_table,'cat_id');
	Api\Config::save_value('location_cats', $locations_cat_id, 'resources');

	// Give default group all rights to this general cat
	$defaultgroup = $GLOBALS['egw_setup']->add_account('Default','Default','Group',False,False);
	$GLOBALS['egw_setup']->add_acl('resources','run',$defaultgroup);
	$GLOBALS['egw_setup']->add_acl('resources',"L$locations_cat_id",$defaultgroup,399);

	return $GLOBALS['setup_info']['resources']['currentver'] = '1.9.001';
}


function resources_upgrade1_9_001()
{
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_resources','deleted',array(
		'type' => 'int',
		'precision' => '8'
	));

	return $GLOBALS['setup_info']['resources']['currentver'] = '1.9.002';
}


function resources_upgrade1_9_002()
{
	return $GLOBALS['setup_info']['resources']['currentver'] = '1.9.003';
}


function resources_upgrade1_9_003()
{
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_resources','res_creator',array(
		'type' => 'int',
		'meta' => 'user',
		'precision' => '11'
	));
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_resources','res_created',array(
		'type' => 'int',
		'meta' => 'timestamp',
		'precision' => '8'
	));
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_resources','res_modifier',array(
		'type' => 'int',
		'meta' => 'user',
		'precision' => '11'
	));
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_resources','res_modified',array(
		'type' => 'int',
		'meta' => 'timestamp',
		'precision' => '8'
	));

	return $GLOBALS['setup_info']['resources']['currentver'] = '1.9.004';
}

function resources_upgrade1_9_004()
{
	return $GLOBALS['setup_info']['resources']['currentver'] = '14.1';
}

function resources_upgrade14_1()
{
	return $GLOBALS['setup_info']['resources']['currentver'] = '16.1';
}

function resources_upgrade16_1()
{
	return $GLOBALS['setup_info']['resources']['currentver'] = '17.1';
}

/**
 * Bump version to 19.1
 *
 * @return string
 */
function resources_upgrade17_1()
{
	return $GLOBALS['setup_info']['resources']['currentver'] = '19.1';
}

function resources_upgrade19_1()
{
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_resources_extra','extra_value',array(
		'type' => 'varchar',
		'meta' => 'cfvalue',
		'precision' => '16384',
		'nullable' => False,
		'default' => ''
	));

	return $GLOBALS['setup_info']['resources']['currentver'] = '19.1.001';
}

/**
 * Bump version to 20.1
 *
 * @return string
 */
function resources_upgrade19_1_001()
{
	return $GLOBALS['setup_info']['resources']['currentver'] = '20.1';
}

/**
 * Bump version to 21.1
 *
 * @return string
 */
function resources_upgrade20_1()
{
	return $GLOBALS['setup_info']['resources']['currentver'] = '21.1';
}

/**
 * Bump version to 23.1
 *
 * @return string
 */
function resources_upgrade21_1()
{
	return $GLOBALS['setup_info']['resources']['currentver'] = '23.1';
}