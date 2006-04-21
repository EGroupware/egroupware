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

	$phpgw_baseline = array(
		'egw_resources' => array(
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
				'picture' => array('type' => 'blob'),
				'accessories' => array('type' => 'varchar','precision' => '50')
			),
			'pk' => array('id'),
			'fk' => array(),
			'ix' => array(),
			'uc' => array()
		)
	);
