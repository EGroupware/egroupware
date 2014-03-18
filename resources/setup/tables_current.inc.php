<?php
/**
 * EGroupware - resources
 * http://www.egroupware.org
 * Created by eTemplates DB-Tools written by ralfbecker@outdoor-training.de
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package resources
 * @subpackage setup
 * @author Cornelius Weiss <egw@von-und-zu-weiss.de>
 * @version $Id$
 */

$phpgw_baseline = array(
	'egw_resources' => array(
		'fd' => array(
			'res_id' => array('type' => 'auto','nullable' => False),
			'name' => array('type' => 'varchar','precision' => '100'),
			'short_description' => array('type' => 'varchar','precision' => '100'),
			'cat_id' => array('type' => 'int','meta' => 'category','precision' => '11','nullable' => False),
			'quantity' => array('type' => 'int','precision' => '11','default' => '1'),
			'useable' => array('type' => 'int','precision' => '11','default' => '1'),
			'location' => array('type' => 'varchar','precision' => '100'),
			'bookable' => array('type' => 'varchar','precision' => '1'),
			'buyable' => array('type' => 'varchar','precision' => '1'),
			'prize' => array('type' => 'varchar','precision' => '200'),
			'long_description' => array('type' => 'longtext'),
			'picture_src' => array('type' => 'varchar','precision' => '20'),
			'accessory_of' => array('type' => 'int','precision' => '11','default' => '-1'),
			'storage_info' => array('type' => 'varchar','precision' => '200'),
			'inventory_number' => array('type' => 'varchar','precision' => '20'),
			'deleted' => array('type' => 'int','meta' => 'timestamp','precision' => '8'),
			'res_creator' => array('type' => 'int','meta' => 'user','precision' => '11'),
			'res_created' => array('type' => 'int','meta' => 'timestamp','precision' => '8'),
			'res_modifier' => array('type' => 'int','meta' => 'user','precision' => '11'),
			'res_modified' => array('type' => 'int','meta' => 'timestamp','precision' => '8')
		),
		'pk' => array('res_id'),
		'fk' => array(),
		'ix' => array(),
		'uc' => array()
	),
	'egw_resources_extra' => array(
		'fd' => array(
			'extra_id' => array('type' => 'int','precision' => '4','nullable' => False),
			'extra_name' => array('type' => 'varchar','meta' => 'cfname','precision' => '40','nullable' => False),
			'extra_owner' => array('type' => 'int','meta' => 'account','precision' => '4','nullable' => False,'default' => '-1'),
			'extra_value' => array('type' => 'varchar','meta' => 'cfvalue','precision' => '255','nullable' => False,'default' => '')
		),
		'pk' => array('extra_id','extra_name','extra_owner'),
		'fk' => array(),
		'ix' => array(),
		'uc' => array()
	)
);
