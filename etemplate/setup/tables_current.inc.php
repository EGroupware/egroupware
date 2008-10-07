<?php
/**
 * eTemplates - DB schema
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package etemplate
 * @subpackage setup
 * @copyright (c) 200s-8 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

$phpgw_baseline = array(
	'egw_etemplate' => array(
		'fd' => array(
			'et_name' => array('type' => 'varchar','precision' => '80','nullable' => False),
			'et_template' => array('type' => 'varchar','precision' => '20','nullable' => False,'default' => ''),
			'et_lang' => array('type' => 'varchar','precision' => '5','nullable' => False,'default' => ''),
			'et_group' => array('type' => 'int','precision' => '4','nullable' => False,'default' => '0'),
			'et_version' => array('type' => 'varchar','precision' => '20','nullable' => False,'default' => ''),
			'et_data' => array('type' => 'longtext','nullable' => True),
			'et_size' => array('type' => 'varchar','precision' => '128','nullable' => True),
			'et_style' => array('type' => 'text','nullable' => True),
			'et_modified' => array('type' => 'int','precision' => '4','nullable' => False,'default' => '0')
		),
		'pk' => array('et_name','et_template','et_lang','et_group','et_version'),
		'fk' => array(),
		'ix' => array(),
		'uc' => array()
	)
);
