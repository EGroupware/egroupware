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

	//ExecMethod('filemanager.bofilemanager.check_set_default_prefs');

	/*create_section('TESTING');

	create_check_box('Use new experimental Filemanager?','experimental_new_code','The future filemanager, now for TESTING PURPOSES ONLY, please send bugreports');

	*/
	create_section('Display attributes');

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

	while (list ($key, $value) = each ($file_attributes))
	{
		create_check_box($value,$key);
	}
	
	create_section('Other settings');

	$other_checkboxes = array (
		"viewinnewwin" => "View documents in new window", 
		"viewonserver" => "View documents on server (if available)", 
		"viewtextplain" => "Unknown MIME-type defaults to text/plain when viewing", 
		"dotdot" => "Show ..", 
		"dotfiles" => "Show .files", 
	);

	while (list ($key, $value) = each ($other_checkboxes))
	{
		create_check_box($value,$key);
	}

	$upload_boxes=array(
		"1"=>"1",
		"5"=>"5",
		"10"=>"10",
		"20"=>"20",
		"30"=>"30"
	);
	
	create_select_box('Default number of upload fields to show','show_upload_boxes',$upload_boxes);
