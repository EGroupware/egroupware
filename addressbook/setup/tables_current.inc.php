<?php
  /**************************************************************************\
  * phpGroupWare - Setup                                                     *
  * http://www.phpgroupware.org                                              *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /**************************************************************************\
  * This file should be generated for you. It should never be edited by hand *
  \**************************************************************************/

  /* $Id$ */

	$phpgw_baseline = array(
		'phpgw_addressbook' => array(
			'fd' => array(
				'id' => array('type' => 'auto','nullable' => False),
				'lid' => array('type' => 'varchar', 'precision' => 32,'nullable' => True),
				'tid' => array('type' => 'char', 'precision' => 1,'nullable' => True),
				'owner' => array('type' => 'int', 'precision' => 8,'nullable' => True),
				'access' => array('type' => 'varchar', 'precision' => 7,'nullable' => True),
				'cat_id' => array('type' => 'varchar', 'precision' => 32,'nullable' => True),
				'fn' => array('type' => 'varchar', 'precision' => 64,'nullable' => True),
				'n_family' => array('type' => 'varchar', 'precision' => 64,'nullable' => True),
				'n_given' => array('type' => 'varchar', 'precision' => 64,'nullable' => True),
				'n_middle' => array('type' => 'varchar', 'precision' => 64,'nullable' => True),
				'n_prefix' => array('type' => 'varchar', 'precision' => 64,'nullable' => True),
				'n_suffix' => array('type' => 'varchar', 'precision' => 64,'nullable' => True),
				'sound' => array('type' => 'varchar', 'precision' => 64,'nullable' => True),
				'bday' => array('type' => 'varchar', 'precision' => 32,'nullable' => True),
				'note' => array('type' => 'text','nullable' => True),
				'tz' => array('type' => 'varchar', 'precision' => 8,'nullable' => True),
				'geo' => array('type' => 'varchar', 'precision' => 32,'nullable' => True),
				'url' => array('type' => 'varchar', 'precision' => 128,'nullable' => True),
				'pubkey' => array('type' => 'text','nullable' => True),
				'org_name' => array('type' => 'varchar', 'precision' => 64,'nullable' => True),
				'org_unit' => array('type' => 'varchar', 'precision' => 64,'nullable' => True),
				'title' => array('type' => 'varchar', 'precision' => 64,'nullable' => True),
				'adr_one_street' => array('type' => 'varchar', 'precision' => 64,'nullable' => True),
				'adr_one_locality' => array('type' => 'varchar', 'precision' => 64,'nullable' => True),
				'adr_one_region' => array('type' => 'varchar', 'precision' => 64,'nullable' => True),
				'adr_one_postalcode' => array('type' => 'varchar', 'precision' => 64,'nullable' => True),
				'adr_one_countryname' => array('type' => 'varchar', 'precision' => 64,'nullable' => True),
				'adr_one_type' => array('type' => 'varchar', 'precision' => 32,'nullable' => True),
				'label' => array('type' => 'text','nullable' => True),
				'adr_two_street' => array('type' => 'varchar', 'precision' => 64,'nullable' => True),
				'adr_two_locality' => array('type' => 'varchar', 'precision' => 64,'nullable' => True),
				'adr_two_region' => array('type' => 'varchar', 'precision' => 64,'nullable' => True),
				'adr_two_postalcode' => array('type' => 'varchar', 'precision' => 64,'nullable' => True),
				'adr_two_countryname' => array('type' => 'varchar', 'precision' => 64,'nullable' => True),
				'adr_two_type' => array('type' => 'varchar', 'precision' => 32,'nullable' => True),
				'tel_work' => array('type' => 'varchar', 'precision' => 40,'nullable' => False,'default' => '+1 (000) 000-0000'),
				'tel_home' => array('type' => 'varchar', 'precision' => 40,'nullable' => False,'default' => '+1 (000) 000-0000'),
				'tel_voice' => array('type' => 'varchar', 'precision' => 40,'nullable' => False,'default' => '+1 (000) 000-0000'),
				'tel_fax' => array('type' => 'varchar', 'precision' => 40,'nullable' => False,'default' => '+1 (000) 000-0000'),
				'tel_msg' => array('type' => 'varchar', 'precision' => 40,'nullable' => False,'default' => '+1 (000) 000-0000'),
				'tel_cell' => array('type' => 'varchar', 'precision' => 40,'nullable' => False,'default' => '+1 (000) 000-0000'),
				'tel_pager' => array('type' => 'varchar', 'precision' => 40,'nullable' => False,'default' => '+1 (000) 000-0000'),
				'tel_bbs' => array('type' => 'varchar', 'precision' => 40,'nullable' => False,'default' => '+1 (000) 000-0000'),
				'tel_modem' => array('type' => 'varchar', 'precision' => 40,'nullable' => False,'default' => '+1 (000) 000-0000'),
				'tel_car' => array('type' => 'varchar', 'precision' => 40,'nullable' => False,'default' => '+1 (000) 000-0000'),
				'tel_isdn' => array('type' => 'varchar', 'precision' => 40,'nullable' => False,'default' => '+1 (000) 000-0000'),
				'tel_video' => array('type' => 'varchar', 'precision' => 40,'nullable' => False,'default' => '+1 (000) 000-0000'),
				'tel_prefer' => array('type' => 'varchar', 'precision' => 32,'nullable' => True),
				'email' => array('type' => 'varchar', 'precision' => 64,'nullable' => True),
				'email_type' => array('type' => 'varchar', 'precision' => 32,'nullable' => False,'default' => 'INTERNET'),
				'email_home' => array('type' => 'varchar', 'precision' => 64,'nullable' => True),
				'email_home_type' => array('type' => 'varchar', 'precision' => 32,'nullable' => False,'default' => 'INTERNET')
			),
			'pk' => array('id'),
			'fk' => array(),
			'ix' => array(),
			'uc' => array()
		),
		'phpgw_addressbook_extra' => array(
			'fd' => array(
				'contact_id' => array('type' => 'int', 'precision' => 4, 'nullable' => True),
				'contact_owner' => array('type' => 'int', 'precision' => 8,'nullable' => True),
				'contact_name' => array('type' => 'varchar', 'precision' => 255,'nullable' => True),
				'contact_value' => array('type' => 'text','nullable' => True)
			),
			'pk' => array(),
			'fk' => array(),
			'ix' => array(),
			'uc' => array()
		),
	);
?>
