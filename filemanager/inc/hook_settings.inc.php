<?php
	/**************************************************************************\
	* eGroupWare - Filemanager Preferences                                     *
	* http://egroupware.org                                                    *
	* Modified by Pim Snel <pim@egroupware.org>                                *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option)                                                                 *
	\**************************************************************************/

	/* $Id$ */

	//ExecMethod('filemanager.bofilemanager.check_set_default_prefs');

	/*create_section('TESTING');

	create_check_box('Use new experimental Filemanager?','experimental_new_code','The future filemanager, now for TESTING PURPOSES ONLY, please send bugreports');

	*/
	settype($GLOBALS['settings'],'array');

	$GLOBALS['settings']['display_attrs'] = array(
		'type'   => 'section',
		'title'  => 'Display attributes',
		'name'   => 'display_attrs',
		'xmlrpc' => True,
		'admin'  => False
	);

	$file_attributes = Array(
		'name' => 'File Name',
		'mime_type' => 'MIME Type',
		'size' => 'Size',
		'created' => 'Created',
		'modified' => 'Modified',
		'owner' => 'Owner',
		'createdby_id' => 'Created by',
		'modifiedby_id' => 'Created by',
		'modifiedby_id' => 'Modified by',
		'app' => 'Application',
		'comment' => 'Comment',
		'version' => 'Version'
	);

	foreach($file_attributes as $key => $value)
	{
		$GLOBALS['settings'][$key] = array(
			'type'  => 'check',
			'label' => "$value",
			'name'  => $key,
			'xmlrpc' => True,
			'admin'  => False
		);
	}

	$GLOBALS['settings']['other_settings'] = array(
		'type'   => 'section',
		'title'  => 'Other settings',
		'name'   => 'other_settings',
		'xmlrpc' => True,
		'admin'  => False
	);

	$other_checkboxes = array (
		"viewinnewwin" => "View documents in new window", 
		"viewonserver" => "View documents on server (if available)", 
		"viewtextplain" => "Unknown MIME-type defaults to text/plain when viewing", 
		"dotdot" => "Show ..", 
		"dotfiles" => "Show .files", 
	);

	foreach($other_checkboxes as $key => $value)
	{
		$GLOBALS['settings'][$key] = array(
			'type'  => 'check',
			'label' => "$value",
			'name'  => $key,
			'xmlrpc' => True,
			'admin'  => False
		);
	}

	$upload_boxes = array(
		'1'  => '1',
		'5'  => '5',
		'10' => '10',
		'20' => '20',
		'30' => '30'
	);

	$GLOBALS['settings']['show_upload_boxes'] = array(
		'label'  => 'Default number of upload fields to show',
		'name'   => 'show_upload_boxes',
		'values' => $upload_boxes,
		'xmlrpc' => True,
		'admin'  => False
	);
