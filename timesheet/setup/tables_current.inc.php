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
		'egw_timesheet' => array(
			'fd' => array(
				'ts_id' => array('type' => 'auto','nullable' => False),
				'ts_project' => array('type' => 'varchar','precision' => '80'),
				'ts_title' => array('type' => 'varchar','precision' => '80','nullable' => False),
				'ts_description' => array('type' => 'text'),
				'ts_start' => array('type' => 'int','precision' => '8','nullable' => False),
				'ts_duration' => array('type' => 'int','precision' => '8','nullable' => False,'default' => '0'),
				'ts_quantity' => array('type' => 'float','precision' => '8','nullable' => False),
				'ts_unitprice' => array('type' => 'float','precision' => '4'),
				'cat_id' => array('type' => 'int','precision' => '4','default' => '0'),
				'ts_owner' => array('type' => 'int','precision' => '4','nullable' => False),
				'ts_modified' => array('type' => 'int','precision' => '8','nullable' => False),
				'ts_modifier' => array('type' => 'int','precision' => '4','nullable' => False)
			),
			'pk' => array('ts_id'),
			'fk' => array(),
			'ix' => array('ts_project','ts_owner'),
			'uc' => array()
		)
	);
