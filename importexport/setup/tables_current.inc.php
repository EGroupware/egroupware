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
	'egw_importexport_definitions' => array(
		'fd' => array(
			'definition_id' => array('type' => 'auto','nullable' => False),
			'name' => array('type' => 'varchar','precision' => '255'),
			'application' => array('type' => 'varchar','precision' => '50'),
			'plugin' => array('type' => 'varchar','precision' => '100'),
			'type' => array('type' => 'varchar','precision' => '20'),
			'allowed_users' => array('type' => 'varchar','precision' => '255'),
			'plugin_options' => array('type' => 'longtext'),
			'owner' => array('type' => 'int','precision' => '20'),
			'description' => array('type' => 'varchar','precision' => '255'),
			'modified' => array('type' => 'timestamp')
		),
		'pk' => array('definition_id'),
		'fk' => array(),
		'ix' => array('name'),
		'uc' => array('name')
	)
);
