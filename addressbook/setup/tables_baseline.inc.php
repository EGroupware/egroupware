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

	// $Id$
	// $Source$

	$phpgw_baseline = array(
		'egw_addressbook' => array(
			'fd' => array(
				'id' => array('type' => 'auto','nullable' => False),
				'lid' => array('type' => 'varchar','precision' => '32'),
				'tid' => array('type' => 'char','precision' => '1'),
				'owner' => array('type' => 'int','precision' => '8'),
				'access' => array('type' => 'varchar','precision' => '7'),
				'cat_id' => array('type' => 'varchar','precision' => '32'),
				'fn' => array('type' => 'varchar','precision' => '64'),
				'n_family' => array('type' => 'varchar','precision' => '64'),
				'n_given' => array('type' => 'varchar','precision' => '64'),
				'n_middle' => array('type' => 'varchar','precision' => '64'),
				'n_prefix' => array('type' => 'varchar','precision' => '64'),
				'n_suffix' => array('type' => 'varchar','precision' => '64'),
				'sound' => array('type' => 'varchar','precision' => '64'),
				'bday' => array('type' => 'varchar','precision' => '32'),
				'note' => array('type' => 'text'),
				'tz' => array('type' => 'varchar','precision' => '8'),
				'geo' => array('type' => 'varchar','precision' => '32'),
				'url' => array('type' => 'varchar','precision' => '128'),
				'pubkey' => array('type' => 'text'),
				'org_name' => array('type' => 'varchar','precision' => '64'),
				'org_unit' => array('type' => 'varchar','precision' => '64'),
				'title' => array('type' => 'varchar','precision' => '64'),
				'adr_one_street' => array('type' => 'varchar','precision' => '64'),
				'adr_one_locality' => array('type' => 'varchar','precision' => '64'),
				'adr_one_region' => array('type' => 'varchar','precision' => '64'),
				'adr_one_postalcode' => array('type' => 'varchar','precision' => '64'),
				'adr_one_countryname' => array('type' => 'varchar','precision' => '64'),
				'adr_one_type' => array('type' => 'varchar','precision' => '32'),
				'label' => array('type' => 'text'),
				'adr_two_street' => array('type' => 'varchar','precision' => '64'),
				'adr_two_locality' => array('type' => 'varchar','precision' => '64'),
				'adr_two_region' => array('type' => 'varchar','precision' => '64'),
				'adr_two_postalcode' => array('type' => 'varchar','precision' => '64'),
				'adr_two_countryname' => array('type' => 'varchar','precision' => '64'),
				'adr_two_type' => array('type' => 'varchar','precision' => '32'),
				'tel_work' => array('type' => 'varchar','precision' => '40','nullable' => False,'default' => '+1 (000) 000-0000'),
				'tel_home' => array('type' => 'varchar','precision' => '40','nullable' => False,'default' => '+1 (000) 000-0000'),
				'tel_voice' => array('type' => 'varchar','precision' => '40','nullable' => False,'default' => '+1 (000) 000-0000'),
				'tel_fax' => array('type' => 'varchar','precision' => '40','nullable' => False,'default' => '+1 (000) 000-0000'),
				'tel_msg' => array('type' => 'varchar','precision' => '40','nullable' => False,'default' => '+1 (000) 000-0000'),
				'tel_cell' => array('type' => 'varchar','precision' => '40','nullable' => False,'default' => '+1 (000) 000-0000'),
				'tel_pager' => array('type' => 'varchar','precision' => '40','nullable' => False,'default' => '+1 (000) 000-0000'),
				'tel_bbs' => array('type' => 'varchar','precision' => '40','nullable' => False,'default' => '+1 (000) 000-0000'),
				'tel_modem' => array('type' => 'varchar','precision' => '40','nullable' => False,'default' => '+1 (000) 000-0000'),
				'tel_car' => array('type' => 'varchar','precision' => '40','nullable' => False,'default' => '+1 (000) 000-0000'),
				'tel_isdn' => array('type' => 'varchar','precision' => '40','nullable' => False,'default' => '+1 (000) 000-0000'),
				'tel_video' => array('type' => 'varchar','precision' => '40','nullable' => False,'default' => '+1 (000) 000-0000'),
				'tel_prefer' => array('type' => 'varchar','precision' => '32'),
				'email' => array('type' => 'varchar','precision' => '64'),
				'email_type' => array('type' => 'varchar','precision' => '32','default' => 'INTERNET'),
				'email_home' => array('type' => 'varchar','precision' => '64'),
				'email_home_type' => array('type' => 'varchar','precision' => '32','default' => 'INTERNET'),
				'last_mod' => array('type' => 'int','precision' => '8','nullable' => False)
			),
			'pk' => array('id'),
			'fk' => array(),
			'ix' => array(array('tid','owner','access','n_family','n_given'),array('tid','cat_id','owner','access','n_family')),
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
		),
	);
