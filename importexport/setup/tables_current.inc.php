<?php
/**
 * EGroupware - Setup
 *
 * @link http://www.egroupware.org
 * @package importexport
 * @subpackage setup
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

$phpgw_baseline = array(
	'egw_importexport_definitions' => array(
		'fd' => array(
			'definition_id' => array('type' => 'auto','nullable' => False),
			'name' => array('type' => 'varchar','precision' => '255'),
			'application' => array('type' => 'varchar','precision' => '50'),
			'plugin' => array('type' => 'varchar','precision' => '100'),
			'type' => array('type' => 'varchar','precision' => '20'),
			'allowed_users' => array('type' => 'varchar','meta' => 'account-commasep','precision' => '255'),
			'plugin_options' => array('type' => 'longtext'),
			'owner' => array('type' => 'int','meta' => 'account','precision' => '4'),
			'description' => array('type' => 'varchar','precision' => '255'),
			'modified' => array('type' => 'timestamp'),
			'filter' => array('type' => 'longtext')
		),
		'pk' => array('definition_id'),
		'fk' => array(),
		'ix' => array(),
		'uc' => array('name')
	)
);
