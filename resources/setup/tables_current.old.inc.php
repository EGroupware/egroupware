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


	$phpgw_baseline = array(
		'egw_resources' => array(
			'fd' => array(
				'id' => array('type' => 'auto'),
				'name' => array('type' => 'varchar','precision' => '100'),
				'short_description' => array('type' => 'varchar','precision' => '100'),
				'cat_id' => array('type' => 'int','precision' => '11','nullable' => False),
				'quantity' => array('type' => 'int','precision' => '11'),
				'useable' => array('type' => 'int','precision' => '11'),
				'location' => array('type' => 'varchar','precision' => '100'),
				'bookable' => array('type' => 'varchar','precision' => '1'),
				'buyable' => array('type' => 'varchar','precision' => '1'),
				'prize' => array('type' => 'varchar','precision' => '200'),
				'long_description' => array('type' => 'longtext'),
				'accessories' => array('type' => 'varchar','precision' => '50'),
				'picture_src' => array('type' => 'varchar','precision' => '20')
			),
			'pk' => array('id'),
			'fk' => array(),
			'ix' => array(),
			'uc' => array()
		)
	);
