<?php
  /**************************************************************************\
  * phpGroupWare - Editable Templates                                        *
  * http://www.phpgroupware.org                                              *
  " Written by Ralf Becker <RalfBecker@outdoor-training.de>                  *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

	$phpgw_baseline = array(
		'phpgw_etemplate' => array(
			'fd' => array(
				'et_name' => array('type' => 'varchar','precision' => '80','nullable' => False),
				'et_template' => array('type' => 'varchar','precision' => '20','nullable' => False,'default' => ''),
				'et_lang' => array('type' => 'varchar','precision' => '5','nullable' => False,'default' => ''),
				'et_group' => array('type' => 'int','precision' => '4','nullable' => False,'default' => '0'),
				'et_version' => array('type' => 'varchar','precision' => '20','nullable' => False,'default' => ''),
				'et_data' => array('type' => 'text','nullable' => True),
				'et_size' => array('type' => 'varchar','precision' => '128','nullable' => True),
				'et_style' => array('type' => 'text','nullable' => True),
				'et_modified' => array('type' => 'int','precision' => '4','nullable' => False,'default' => '0')
			),
			'pk' => array('et_name','et_template','et_lang','et_group','et_version'),
			'fk' => array(),
			'ix' => array(),
			'uc' => array()
		)
	);
