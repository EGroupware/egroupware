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
				'info_type' => array('type' => 'varchar', 'precision' => 25), 
				'info_id_parent' => array('type' => 'int', 'precision' => 4, 'nullable' => false),
				'info_owner' => array('type' => 'varchar', 'precision' => 25),
				'info_access' => array('type' => 'varchar', 'precision' => 10),
				'info_cat' => array('type' => 'int', 'precision' => 4),
				'info_des' => array('type' => 'text'),
				'info_pri' => array('type' => 'int', 'precision' => 4),
				'info_status' => array('type' => 'int', 'precision' => 4),
				'info_datecreated' => array('type' => 'int', 'precision' => 4),
				'info_startdate' => array('type' => 'int', 'precision' => 4),
				'info_enddata' => array('type' => 'int', 'precision' => 4)
			),
			'pk' => array('info_id'),
			'fk' => array(),
			'ix' => array(),
			'uc' => array()
		)
	);
?>
