<?php
  /**************************************************************************\
  * phpGroupWare - Notes eTemplate Port                                      *
  * http://www.phpgroupware.org                                              *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

	$phpgw_baseline = array(
		'phpgw_et_notes' => array(
			'fd' => array(
				'note_id' => array('type' => 'auto', 'nullable' => false),
				'note_owner' => array('type' => 'int', 'precision' => 4),
				'note_access' => array('type' => 'varchar', 'precision' => 7),
				'note_date' => array('type' => 'int', 'precision' => 4),
				'note_category' => array('type' => 'int', 'precision' => 4),
				'note_content' => array('type' => 'text')
			),
			'pk' => array('note_id'),
			'fk' => array(),
			'ix' => array(),
			'uc' => array()
		)
	);
?>
