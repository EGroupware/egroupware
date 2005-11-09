<?php
	/**************************************************************************\
	* eGroupWare - Editable Templates: Example App of the tutorial             *
	* http://www.egroupware.org                                                *
	" Written by Ralf Becker <RalfBecker@outdoor-training.de>                  *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	$phpgw_baseline = array(
		'egw_et_media' => array(
			'fd' => array(
				'id' => array('type' => 'auto','nullable' => False),
				'name' => array('type' => 'varchar','precision' => '100','nullable' => False),
				'author' => array('type' => 'varchar','precision' => '100','nullable' => False),
				'descr' => array('type' => 'text','nullable' => False),
				'type' => array('type' => 'varchar','precision' => '20','nullable' => False)
			),
			'pk' => array('id'),
			'fk' => array(),
			'ix' => array(),
			'uc' => array()
		)
	);
