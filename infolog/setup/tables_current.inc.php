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
		'phpgw_infolog' => array(
			'fd' => array(
				'info_id' => array('type' => 'auto', 'nullable' => false),
				'info_type' => array('type' => 'varchar', 'precision' => 10), 
				'info_addr_id' => array('type' => 'int', 'precision' => 11, 'nullable' => false),
				'info_proj_id' => array('type' => 'int', 'precision' => 11, 'nullable' => false),
				'info_from' => array('type' => 'varchar', 'precision' => 64),
				'info_addr' => array('type' => 'varchar', 'precision' => 64),
				'info_subject' => array('type' => 'varchar', 'precision' => 64, 'nullable' => false),
				'info_des' => array('type' => 'text'),
				'info_owner' => array('type' => 'int', 'precision' => 11, 'nullable' => false),
				'info_responsible' => array('type' => 'int', 'precision' => 11, 'nullable' => false),
				'info_access' => array('type' => 'varchar', 'precision' => 10),
				'info_cat' => array('type' => 'int', 'precision' => 11, 'nullable' => false),
				'info_datecreated' => array('type' => 'int', 'precision' => 11, 'nullable' => false),
				'info_startdate' => array('type' => 'int', 'precision' => 11, 'nullable' => false),
				'info_enddata' => array('type' => 'int', 'precision' => 11, 'nullable' => false)
				'info_id_parent' => array('type' => 'int', 'precision' => 11, 'nullable' => false),
				'info_pri' => array('type' => 'varchar', 'precision' => 10),
				'info_time' => array('type' => 'int', 'precision' => 11, 'nullable' => false),
				'info_bill_cat' => array('type' => 'int', 'precision' => 11, 'nullable' => false),
				'info_status' => array('type' => 'varchar', 'precision' => 10),
				'info_confirm' => array('type' => 'varchar', 'precision' => 10)
			),
			'pk' => array('info_id'),
			'fk' => array(),
			'ix' => array(),
			'uc' => array()
		)
	);
?>
