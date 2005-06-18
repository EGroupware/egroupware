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

   /* $id$ */

   // FIXME: remove imageMagick shit, we only use gdlib
   // FIXME: autodetect safe_mode
   // FIXME set current app to the calling app
   // FIXME include header nicer

   $phpgw_flags = Array(
	  'currentapp'	=>	'jinn',
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

   define('IMAGE_CLASS', 'GD');  

   //In safe mode, directory creation is not permitted.
   $SAFE_MODE = false;

   $sessdata =	  $GLOBALS['phpgw']->session->appsession('UploadImage','phpgwapi');

   $BASE_DIR = $sessdata[UploadImageBaseDir];
   $BASE_URL = $sessdata[UploadImageBaseURL];
   $MAX_HEIGHT = $sessdata[UploadImageMaxHeight];
   $MAX_WIDTH = $sessdata[UploadImageMaxWidth];

   if(!$MAX_HEIGHT) $MAX_HEIGHT = 10000;
   if(!$MAX_WIDTH) $MAX_WIDTH = 10000;
//   _debug_array($sessdata);
   //die();


   //After defining which library to use, if it is NetPBM or IM, you need to
   //specify where the binary for the selected library are. And of course
   //your server and PHP must be able to execute them (i.e. safe mode is OFF).
   //If you have safe mode ON, or don't have the binaries, your choice is
   //GD only. GD does not require the following definition.
   //define('IMAGE_TRANSFORM_LIB_PATH', '/usr/bin/netpbm/');
   //define('IMAGE_TRANSFORM_LIB_PATH', '"D:\\Program Files\\ImageMagick\\');

   $BASE_ROOT = '';
   $IMG_ROOT = $BASE_ROOT;

   if(strrpos($BASE_DIR, '/')!= strlen($BASE_DIR)-1) 
   $BASE_DIR .= '/';

   if(strrpos($BASE_URL, '/')!= strlen($BASE_URL)-1) 
   $BASE_URL .= '/';

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
