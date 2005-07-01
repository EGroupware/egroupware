<?php
	/**************************************************************************\
	* eGroupWare - UploadImage-plugin for htmlArea                             *
	* http://www.eGroupWare.org                                                *
	* Written and (c) by Xiang Wei ZHUO <wei@zhuo.org>                         *
	* Modified for eGW by and (c) by Pim Snel <pim@lingewoud.nl>               *
	* --------------------------------------------                             *
	* This program is free software; you can redistribute it and/or modify it  *
	* under the terms of the GNU General Public License as published by the    *
	* Free Software Foundation; version 2 of the License.                      *
	\**************************************************************************/
	
	/* $Id$ */
	
	// FIXME: remove imageMagick shit, we only use gdlib
	// FIXME: autodetect safe_mode
	// FIXME include header nicer
	
	/*
		USAGE: If you like to use this plugin, insinde the eGW framework, you have to do two things
		1.	Add 'UploadImage' to the $plugins variable on htmlarea call
		2.	supply an array in the session with like this example shows:
				$UploadImage = array(
					'app' => 'news_admin',
					'upload_dir' => $GLOBALS['phpgw_info']['user']['preferences']['news_admin']['uploaddir'],
					'admin_method' => $GLOBALS['phpgw']->link('/index.php', 'menuaction=app.file.method');; 
				$GLOBALS['phpgw']->session->appsession('UploadImage','phpgwapi',$UploadImage);
			
	*/
	
	$phpgw_flags = Array(
		'currentapp'	=>	'home',
		'noheader'	=>	True,
		'nonavbar'	=>	True,
		'noappheader'	=>	True,
		'noappfooter'	=>	True,
		'nofooter'	=>	True
	);
	
	$GLOBALS['phpgw_info']['flags'] = $phpgw_flags;
	
	if(@include('../../../../../../header.inc.php'))
	{
		// I know this is very ugly
	}
	else
	{
		@include('../../../../../../../header.inc.php');
	}
	
	$sessdata = $GLOBALS['phpgw']->session->appsession('UploadImage','phpgwapi');
	$phpgw_flags['currentapp'] = $sessdata['app'] ? $sessdata['app'] : 'jinn';
	
	define('IMAGE_CLASS', 'GD');  
	
	//In safe mode, directory creation is not permitted.
	$SAFE_MODE = false;
	
	switch ($phpgw_flags['currentapp'])
	{
		case 'jinn' :
			$BASE_DIR = $sessdata[UploadImageBaseDir];
			$BASE_URL = $sessdata[UploadImageBaseURL];
			$MAX_HEIGHT = $sessdata[UploadImageMaxHeight];
			$MAX_WIDTH = $sessdata[UploadImageMaxWidth];
			//   _debug_array($sessdata);
			//die();
			break;
		default : 
			if(is_writeable($sessdata['upload_dir']))
			{
				$BASE_DIR = $sessdata['upload_dir'];
				$BASE_URL = str_replace($GLOBALS['_SERVER']['DOCUMENT_ROOT'],'',$sessdata['upload_dir']);
				break;
			}
			else
			{
				echo '<p><b>Error</b></p>';
				echo '<p>Upload directory does not exist, or is not writeable by webserver</p>';
				echo $GLOBALS['egw_info']['user']['apps']['admin'] ? 
					'<a href="'. $sessdata['admin_method']. '">Choose an other directory</a><br>
					or make "'. $sessdata['upload_dir']. '" writeable by webserver' : 
					'Notify your Administrator to correct this Situation';
				die();
			}
	}
	
	if(!$MAX_HEIGHT) $MAX_HEIGHT = 10000;
	if(!$MAX_WIDTH) $MAX_WIDTH = 10000;
	
	
	//After defining which library to use, if it is NetPBM or IM, you need to
	//specify where the binary for the selected library are. And of course
	//your server and PHP must be able to execute them (i.e. safe mode is OFF).
	//If you have safe mode ON, or don't have the binaries, your choice is
	//GD only. GD does not require the following definition.
	//define('IMAGE_TRANSFORM_LIB_PATH', '/usr/bin/netpbm/');
	//define('IMAGE_TRANSFORM_LIB_PATH', '"D:\\Program Files\\ImageMagick\\');
	
	$BASE_ROOT = '';
	$IMG_ROOT = $BASE_ROOT;
	
	// this seems to make no sense... maybe jinn needs it for some reason
	if ($phpgw_flags['app'] == 'jinn')
	{
		if(strrpos($BASE_DIR, '/')!= strlen($BASE_DIR)-1) 
		$BASE_DIR .= '/';
		
		if(strrpos($BASE_URL, '/')!= strlen($BASE_URL)-1) 
		$BASE_URL .= '/';
	}

	//Built in function of dirname is faulty
	//It assumes that the directory nane can not contain a . (period)
	function dir_name($dir) 
	{
		$lastSlash = intval(strrpos($dir, '/'));
		if($lastSlash == strlen($dir)-1){
			return substr($dir, 0, $lastSlash);
		}
		else
		return dirname($dir);
	}

?>
