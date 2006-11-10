<?php
	/**
	 * eGroupWare - importexport
	 *
	 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
	 * @package importexport
	 * @link http://www.egroupware.org
	 * @author Cornelius Weiss <nelius@cwtech.de>
	 * @version $Id:  $
	 */

	$definition_table = 'egw_importexport_definitions';
	
	// Add two rooms to give user an idea of what resources is...
	$plugin_options = serialize(array(
		'fieldsep' => ',',
		'charset' => 'ISO-8859-1',
		'addressbook' => 'n',
		'owner' => 5,
		'field_mapping' => array(
			 0 => '#kundennummer',
			 1 => 'n_given',
			 2 => 'n_family',
			 3 => 'adr_one_street',
			 4 => 'adr_one_countryname',
			 5 => 'adr_one_postalcode',
			 6 => 'adr_one_locality',
			 7 => 'tel_work',
			 8 => '',
			 9 => '',
			10 => '',
			11 => '',
		),
		'field_tanslation' => array(),
		'has_header_line' => false,
		'max' => false,
		'conditions' => array(
			0 => array(
				'type' => 0, // exists
				'string' => '#kundennummer',
				'true' => array(
					'action' => 1, // update
					'last' => true,
				),
				'false' => array(
					'action' => 2, // insert
					'last' => true,
				),
				
		)),
	));
	$oProc->query("INSERT INTO {$definition_table } (name,application,plugin,type,allowed_users,plugin_options) VALUES ( 'oelheld','addressbook','addressbook_csv_import','import','5','$plugin_options')");

