<?php
	/**************************************************************************\
	* eGroupWare                                                               *
	* http://www.egroupware.org                                                *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	$phpgw_baseline = array(
		'egw_addressbook' => array(
			'fd' => array(
				'contact_id' => array('type' => 'auto','nullable' => False),
				'contact_tid' => array('type' => 'char','precision' => '1','default' => 'n'),
				'contact_owner' => array('type' => 'int','precision' => '8','nullable' => False),
				'contact_private' => array('type' => 'int','precision' => '1','default' => '0'),
				'cat_id' => array('type' => 'varchar','precision' => '32'),
				'n_family' => array('type' => 'varchar','precision' => '64'),
				'n_given' => array('type' => 'varchar','precision' => '64'),
				'n_middle' => array('type' => 'varchar','precision' => '64'),
				'n_prefix' => array('type' => 'varchar','precision' => '64'),
				'n_suffix' => array('type' => 'varchar','precision' => '64'),
				'n_fn' => array('type' => 'varchar','precision' => '128'),
				'n_fileas' => array('type' => 'varchar','precision' => '255'),
				'contact_bday' => array('type' => 'varchar','precision' => '10'),
				'org_name' => array('type' => 'varchar','precision' => '64'),
				'org_unit' => array('type' => 'varchar','precision' => '64'),
				'contact_title' => array('type' => 'varchar','precision' => '64'),
				'contact_role' => array('type' => 'varchar','precision' => '64'),
				'contact_assistent' => array('type' => 'varchar','precision' => '64'),
				'contact_room' => array('type' => 'varchar','precision' => '64'),
				'adr_one_street' => array('type' => 'varchar','precision' => '64'),
				'adr_one_street2' => array('type' => 'varchar','precision' => '64'),
				'adr_one_locality' => array('type' => 'varchar','precision' => '64'),
				'adr_one_region' => array('type' => 'varchar','precision' => '64'),
				'adr_one_postalcode' => array('type' => 'varchar','precision' => '64'),
				'adr_one_countryname' => array('type' => 'varchar','precision' => '64'),
				'contact_label' => array('type' => 'text'),
				'adr_two_street' => array('type' => 'varchar','precision' => '64'),
				'adr_two_street2' => array('type' => 'varchar','precision' => '64'),
				'adr_two_locality' => array('type' => 'varchar','precision' => '64'),
				'adr_two_region' => array('type' => 'varchar','precision' => '64'),
				'adr_two_postalcode' => array('type' => 'varchar','precision' => '64'),
				'adr_two_countryname' => array('type' => 'varchar','precision' => '64'),
				'tel_work' => array('type' => 'varchar','precision' => '40'),
				'tel_cell' => array('type' => 'varchar','precision' => '40'),
				'tel_fax' => array('type' => 'varchar','precision' => '40'),
				'tel_assistent' => array('type' => 'varchar','precision' => '40'),
				'tel_car' => array('type' => 'varchar','precision' => '40'),
				'tel_pager' => array('type' => 'varchar','precision' => '40'),
				'tel_home' => array('type' => 'varchar','precision' => '40'),
				'tel_fax_home' => array('type' => 'varchar','precision' => '40'),
				'tel_cell_private' => array('type' => 'varchar','precision' => '40'),
				'tel_other' => array('type' => 'varchar','precision' => '40'),
				'tel_prefer' => array('type' => 'varchar','precision' => '32'),
				'contact_email' => array('type' => 'varchar','precision' => '64'),
				'contact_email_home' => array('type' => 'varchar','precision' => '64'),
				'contact_url' => array('type' => 'varchar','precision' => '128'),
				'contact_url_home' => array('type' => 'varchar','precision' => '128'),
				'contact_freebusy_uri' => array('type' => 'varchar','precision' => '128'),
				'contact_calendar_uri' => array('type' => 'varchar','precision' => '128'),
				'contact_note' => array('type' => 'text'),
				'contact_tz' => array('type' => 'varchar','precision' => '8'),
				'contact_geo' => array('type' => 'varchar','precision' => '32'),
				'contact_pubkey' => array('type' => 'text'),
				'contact_created' => array('type' => 'int','precision' => '8'),
				'contact_creator' => array('type' => 'int','precision' => '4','nullable' => False),
				'contact_modified' => array('type' => 'int','precision' => '8','nullable' => False),
				'contact_modifier' => array('type' => 'int','precision' => '4'),
				'contact_jpegphoto' => array('type' => 'blob'),
			),
			'pk' => array('contact_id'),
			'fk' => array(),
			'ix' => array('cat_id','contact_owner','contact_fileas',array('n_family','n_given'),array('n_given','n_family'),array('org_name','n_family','n_given')),
			'uc' => array()
		),
		'egw_addressbook_extra' => array(
			'fd' => array(
				'contact_id' => array('type' => 'int','precision' => '4','nullable' => False),
				'contact_owner' => array('type' => 'int','precision' => '8'),
				'contact_name' => array('type' => 'varchar','precision' => '255','nullable' => False),
				'contact_value' => array('type' => 'text')
			),
			'pk' => array('contact_id','contact_name'),
			'fk' => array(),
			'ix' => array(),
			'uc' => array()
		)
	);
