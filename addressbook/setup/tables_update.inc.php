<?php
	/**************************************************************************\
	* eGroupWare - Setup                                                       *
	* http://www.eGroupWare.org                                                *
	* Created by eTemplates DB-Tools written by ralfbecker@outdoor-training.de *
	* --------------------------------------------                             *
	* This program is free software; you can redistribute it and/or modify it  *
	* under the terms of the GNU General Public License as published by the    *
	* Free Software Foundation; either version 2 of the License, or (at your   *
	* option) any later version.                                               *
	\**************************************************************************/
	
	/* $Id$ */

	$test[] = '1.2';
	function addressbook_upgrade1_2()
	{
		$GLOBALS['egw_setup']->oProc->RefreshTable('egw_addressbook',array(
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
			'ix' => array('cat_id','contact_owner','n_fileas',array('n_family','n_given'),array('n_given','n_family'),array('org_name','n_family','n_given')),
			'uc' => array()
		),array(
			// new colum prefix
			'contact_id' => 'id',
			'contact_tid' => 'tid',
			'contact_owner' => 'owner',
			'contact_private' => "CASE access WHEN 'private' THEN 1 ELSE 0 END",
			'n_fn' => 'fn',
			'contact_title' => 'title',
			'contact_bday' => 'bday',
			'contact_note' => 'note',
			'contact_tz' => 'tz',
			'contact_geo' => 'geo',
			'contact_url' => 'url',
			'contact_pubkey' => 'pubkey',
			'contact_label' => 'label',
			'contact_email' => 'email',
			'contact_email_home' => 'email_home',
			'contact_modified' => 'last_mod',
			// remove stupid old default values, rename phone-numbers, tel_bbs and tel_video are droped
			'tel_work' => "CASE tel_work WHEN '+1 (000) 000-0000' THEN NULL ELSE tel_work END",
			'tel_cell' => "CASE tel_cell WHEN '+1 (000) 000-0000' THEN NULL ELSE tel_cell END",
			'tel_fax' => "CASE tel_fax WHEN '+1 (000) 000-0000' THEN NULL ELSE tel_fax END",
			'tel_assistent' => "CASE tel_msg WHEN '+1 (000) 000-0000' THEN NULL ELSE tel_msg END",
			'tel_car' => "CASE tel_car WHEN '+1 (000) 000-0000' THEN NULL ELSE tel_car END",
			'tel_pager' => "CASE tel_pager WHEN '+1 (000) 000-0000' THEN NULL ELSE tel_pager END",
			'tel_home' => "CASE tel_home WHEN '+1 (000) 000-0000' THEN NULL ELSE tel_home END",
			'tel_fax_home' => "CASE tel_modem WHEN '+1 (000) 000-0000' THEN NULL ELSE tel_modem END",
			'tel_cell_private' => "CASE tel_isdn WHEN '+1 (000) 000-0000' THEN NULL ELSE tel_isdn END",
			'tel_other' => "CASE tel_voice WHEN '+1 (000) 000-0000' THEN NULL ELSE tel_voice END",
			'tel_prefer' => "CASE tel_prefer WHEN 'tel_voice' THEN 'tel_other' WHEN 'tel_msg' THEN 'tel_assistent' WHEN 'tel_modem' THEN 'tel_fax_home' WHEN 'tel_isdn' THEN 'tel_cell_private' WHEN 'ophone' THEN 'tel_other' ELSE tel_prefer END",
			// set creator from owner
			'contact_creator' => 'owner',
			// set contact_fileas from org_name, n_family and n_given
			'n_fileas' => "CASE WHEN org_name='' THEN (".
				($name_sql = "CASE WHEN n_given='' THEN n_family ELSE ".$GLOBALS['egw_setup']->db->concat('n_family',"', '",'n_given').' END').
				") ELSE (CASE WHEN n_family='' THEN org_name ELSE ".$GLOBALS['egw_setup']->db->concat('org_name',"': '",$name_sql).' END) END',

		));

		// migrate values saved in custom fields to the new table
		$db2 = clone($GLOBALS['egw_setup']->db);
		$GLOBALS['egw_setup']->db->select('egw_addressbook_extra','contact_id,contact_name,contact_value',
			"contact_name IN ('ophone','address2','address3','freebusy_url') AND contact_value != '' AND NOT contact_value IS NULL"
			,__LINE__,__FILE__,false,'','addressbook');
		$old2new = array(
			'ophone'   => 'tel_other',
			'address2' => 'adr_one_street2',
			'address3' => 'adr_two_street2',
			'freebusy_url' => 'contact_freebusy_uri',
		);
		while (($row = $GLOBALS['egw_setup']->db->row(true)))
		{
			$db2->update('egw_addressbook',array($old2new[$row['contact_name']] => $row['contact_value']),array(
				'contact_id' => $row['contact_id'],
				'('.$old2new[$row['contact_name']].'IS NULL OR '.$old2new[$row['contact_name']]."='')",
			),__LINE__,__FILE__,'addressbook');
		}
		// delete the not longer used custom fields plus rubish from old bugs
		$GLOBALS['egw_setup']->db->delete('egw_addressbook_extra',"contact_name IN ('ophone','address2','address3','freebusy_url','cat_id','tid','lid','id','ab_id','access','owner','rights')".
			" OR contact_value='' OR contact_value IS NULL".
			($db2->capabilities['subqueries'] ? " OR contact_id NOT IN (SELECT contact_id FROM egw_addressbook)" : ''),
			__LINE__,__FILE__,'addressbook');
			
		// change the m/d/Y birthday format to Y-m-d
		$GLOBALS['egw_setup']->db->select('egw_addressbook','contact_id,contact_bday',"contact_bday != ''",
			__LINE__,__FILE__,false,'','addressbook');
		while (($row = $GLOBALS['egw_setup']->db->row(true)))
		{
			list($m,$d,$y) = explode('/',$row['contact_bday']);
			$db2->update('egw_addressbook',array(
				'contact_bday' => sprintf('%04d-%02d-%02d',$y,$m,$d)
			),array(
				'contact_id' => $row['contact_id'],
			),__LINE__,__FILE__,'addressbook');
		}
		return $GLOBALS['setup_info']['addressbook']['currentver'] = '1.3.001';
	}
