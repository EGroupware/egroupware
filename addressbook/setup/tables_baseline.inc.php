<?php
  /**************************************************************************\
  * phpGroupWare                                                             *
  * http://www.phpgroupware.org                                              *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

	$phpgw_baseline = array(
		'addressbook' => array(
			'fd' => array(
				'ab_id' => array('type' => 'auto', 'nullable' => false),
				'ab_owner' => array('type' => 'varchar', 'precision' => 25),
				'ab_access' => array('type' => 'varchar', 'precision' => 10),
				'ab_firstname' => array('type' => 'varchar', 'precision' => 255),
				'ab_lastname' => array('type' => 'varchar', 'precision' => 255),
				'ab_email' => array('type' => 'varchar', 'precision' => 255),
				'ab_hphone' => array('type' => 'varchar', 'precision' => 255),
				'ab_wphone' => array('type' => 'varchar', 'precision' => 255),
				'ab_fax' => array('type' => 'varchar', 'precision' => 255),
				'ab_pager' => array('type' => 'varchar', 'precision' => 255),
				'ab_mphone' => array('type' => 'varchar', 'precision' => 255),
				'ab_ophone' => array('type' => 'varchar', 'precision' => 255),
				'ab_street' => array('type' => 'varchar', 'precision' => 255),
				'ab_city' => array('type' => 'varchar', 'precision' => 255),
				'ab_state' => array('type' => 'varchar', 'precision' => 255),
				'ab_zip' => array('type' => 'varchar', 'precision' => 255),
				'ab_bday' => array('type' => 'varchar', 'precision' => 255),
				'ab_notes' => array('type' => 'text'),
				'ab_company' => array('type' => 'varchar', 'precision' => 255),
			),
			'pk' => array('ab_id'),
			'fk' => array(),
			'ix' => array(),
			'uc' => array()
		)
	);
?>
