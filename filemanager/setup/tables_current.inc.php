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

  // table array for phpwebhosting
	$phpgw_baseline = array(
		'phpgw_vfs' => array(
			'fd' => array(
				'file_id' => array('type' => 'auto','nullable' => False),
				'owner_id' => array('type' => 'int', 'precision' => 4,'nullable' => False),
				'createdby_id' => array('type' => 'int', 'precision' => 4,'nullable' => True),
				'modifiedby_id' => array('type' => 'int', 'precision' => 4,'nullable' => True),
				'created' => array('type' => 'date','nullable' => False,'default' => '1970-01-01'),
				'modified' => array('type' => 'date','nullable' => True),
				'size' => array('type' => 'int', 'precision' => 4,'nullable' => True),
				'mime_type' => array('type' => 'varchar', 'precision' => 150,'nullable' => True),
				'deleteable' => array('type' => 'char', 'precision' => 1,'nullable' => True,'default' => 'Y'),
				'comment' => array('type' => 'text','nullable' => True),
				'app' => array('type' => 'varchar', 'precision' => 25,'nullable' => True),
				'directory' => array('type' => 'text','nullable' => True),
				'name' => array('type' => 'text','nullable' => False),
				'link_directory' => array('type' => 'text','nullable' => True),
				'link_name' => array('type' => 'text','nullable' => True),
				'version' => array('type' => 'varchar', 'precision' => 30,'nullable' => False,'default' => '0.0.0.0')
			),
			'pk' => array('file_id'),
			'fk' => array(),
			'ix' => array(),
			'uc' => array()
		),
	);

?>
