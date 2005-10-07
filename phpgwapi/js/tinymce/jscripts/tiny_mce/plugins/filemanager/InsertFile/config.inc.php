<?php
	/**************************************************************************\
	* eGroupWare - Insert File Dialog, File Manager -plugin for tinymce        *
	* http://www.eGroupWare.org                                                *
	* Authors Al Rashid <alrashid@klokan.sk>                                   *
	*     and Xiang Wei ZHUO <wei@zhuo.org>                                    *
	* Version.......:    1.1                                                   *
	* Modified for eGW by Cornelius Weiss <egw@von-und-zu-weiss.de>            *
	* --------------------------------------------                             *
	* This program is free software; you can redistribute it and/or modify it  *
	* under the terms of the GNU General Public License as published by the    *
	* Free Software Foundation; version 2 of the License.                      *
	\**************************************************************************/
	
	/* $Id$ */
	
	/**
	*	USAGE: If you like to use this plugin, insinde the eGW framework, you have to do two things
	*	1.	Add 'plugins : "filemanager",theme_advanced_buttons3_add : "filemanager"' to the $plugins variable on tinymce call
	*	2.	supply an array in the session with like this example shows:
	*			$UploadImage = array(
	*				'app' => 'news_admin',
	*				'upload_dir' => $GLOBALS['phpgw_info']['user']['preferences']['news_admin']['uploaddir'],
	*				'admin_method' => $GLOBALS['phpgw']->link('/index.php', 'menuaction=app.file.method');; 
	*			$GLOBALS['phpgw']->session->appsession('UploadImage','phpgwapi',$UploadImage);
	*		
	**/
	
	$phpgw_flags = Array(
		'currentapp'	=>	'home',
		'noheader'	=>	True,
		'nonavbar'	=>	True,
		'noappheader'	=>	True,
		'noappfooter'	=>	True,
		'nofooter'	=>	True
	);
	
	$GLOBALS['phpgw_info']['flags'] = $phpgw_flags;
	
	if(!is_object($GLOBALS['egw']))
	{
		if(@include('../../../../../../../../header.inc.php'))
		{
			// I know this is very ugly
		}
		else
		{
			@include('../../../../../../../../../header.inc.php');
		}
	}
	
	$sessdata = $GLOBALS['phpgw']->session->appsession('UploadImage','phpgwapi');
	if(is_writeable($sessdata['upload_dir']))
	{
		$MY_DOCUMENT_ROOT = $BASE_DIR = $sessdata['upload_dir'];
		$MY_BASE_URL = $MY_URL_TO_OPEN_FILE = str_replace($GLOBALS['_SERVER']['DOCUMENT_ROOT'],'',$sessdata['upload_dir']);
		$BASE_URL = '/'.$MY_BASE_URL;
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
	
define('IMAGE_CLASS', 'GD');
/* MY_ALLOW_CREATE   Boolean (false or true) whether creating folders is allowed or not. */
$MY_ALLOW_CREATE     = true;
/* $MY_ALLOW_DELETE  Boolean (false or true) whether deleting files and folders is allowed or not. */
$MY_ALLOW_DELETE     = true;
/* $MY_ALLOW_RENAME  Boolean (false or true) whether renaming files and folders is allowed or not. */
$MY_ALLOW_RENAME     = ture;
/* $MY_ALLOW_MOVE    Boolean (false or true) whether moving files and folders is allowed or not. */
$MY_ALLOW_MOVE       = true;
/* $MY_ALLOW_UPLOAD  Boolean (false or true) whether uploading files is allowed or not. */
$MY_ALLOW_UPLOAD     = true;
/* MY_LIST_EXTENSIONS This array specifies which files are listed in dialog. Setting to null causes that all files are listed,case insensitive. */
$MY_LIST_EXTENSIONS  = array('html', 'doc', 'xls', 'txt', 'gif', 'jpeg', 'jpg', 'png', 'pdf', 'zip', 'pdf');
/*
 MY_ALLOW_EXTENSIONS
 MY_DENY_EXTENSIONS
 MY_ALLOW_EXTENSIONS and MY_DENY_EXTENSIONS arrays specify which file types can be uploaded.
 Setting to null skips this check. The scheme is:
 1) If MY_DENY_EXTENSIONS is not null check if it does _not_ contain file extension of the file to be uploaded.
    If it does skip the upload procedure.
 2) If MY_ALLOW_EXTENSIONS is not null check if it _does_ contain file extension of the file to be uploaded.
    If it doesn't skip the upload procedure.
 3) Upload file.
 NOTE: File extensions arrays are case insensitive.
        You should always include server side executable file types in MY_DENY_EXTENSIONS !!!
*/
$MY_ALLOW_EXTENSIONS = array('html', 'doc', 'xls', 'txt', 'gif', 'jpeg', 'jpg', 'png', 'pdf', 'zip', 'pdf');
$MY_DENY_EXTENSIONS  = array('php', 'php3', 'php4', 'phtml', 'shtml', 'cgi', 'pl');
/*
 $MY_ALLOW_UPLOAD
 Maximum allowed size for uploaded files (in bytes).
 NOTE2: see also upload_max_filesize setting in your php.ini file
 NOTE: 2*1024*1024 means 2 MB (megabytes) which is the default php.ini setting
*/
$MY_MAX_FILE_SIZE                 = 2*1024*1024;

/*
 $MY_LANG
 Interface language. See the lang directory for translation files.
 NOTE: You should set appropriately MY_CHARSET and $MY_DATETIME_FORMAT variables
*/
$MY_LANG                = 'en';

/*
 $MY_CHARSET
 Character encoding for all Insert File dialogs.
 WARNING: For non english and non iso-8859-1 / utf8 users mostly !!!
 This setting affect also how the name of folder you create via Insert File Dialog
 and the name of file uploaded via Insert File Dialog will be encoded on your remote
 server filesystem. Note also the difference between how file names in multipart/data
 form are encoded by Internet Explorer (plain text depending on the webpage charset)
 and Mozilla (encoded according to RFC 1738).
 This should be fixed in next versions. Any help is VERY appreciated.
*/
$MY_CHARSET             = 'iso-8859-1';

/*
 MY_DATETIME_FORMAT
 Datetime format for displaying file modification time in Insert File Dialog and in inserted link, see MY_LINK_FORMAT
*/
$MY_DATETIME_FORMAT                = "d.m.Y H:i";

/*
 MY_LINK_FORMAT
 The string to be inserted into textarea.
 This is the most crucial setting. I apologize for not using the DOM functions any more,
 but inserting raw string allow more customization for everyone.
 The following strings are replaced by corresponding values of selected files/folders:
 _editor_url  the url of htmlarea root folder - you should set it in your document (see htmlarea help)
 IF_ICON      file type icon filename (see plugins/InsertFile/images/ext directory)
 IF_URL       relative path to file relative to $MY_DOCUMENT_ROOT
 IF_CAPTION   file/folder name
 IF_SIZE      file size in (B, kB, or MB)
 IF_DATE      last modification time acording to $MY_DATETIME_FORMAT format
*/
// $MY_LINK_FORMAT         = '<span class="filelink"><img src="editor_url/plugins/filemanager/InsertFile/IF_ICON" alt="IF_URL" border="0">&nbsp;<a href="IF_URL">IF_CAPTION</a> &nbsp;<span style="font-size:70%">IF_SIZE &nbsp;IF_DATE</span></span>&nbsp;';

/* parse_icon function  please insert additional file types (extensions) and theis corresponding icons in switch statement */
function parse_icon($ext) {
        switch (strtolower($ext)) {
                case 'doc': return 'doc_small.gif';
                case 'rtf': return 'doc_small.gif';
                case 'txt': return 'txt_small.gif';
                case 'xls': return 'xls_small.gif';
                case 'csv': return 'xls_small.gif';
                case 'ppt': return 'ppt_small.gif';
                case 'html': return 'html_small.gif';
                case 'htm': return 'html_small.gif';
                case 'php': return 'script_small.gif';
                case 'php3': return 'script_small.gif';
                case 'cgi': return 'script_small.gif';
                case 'pdf': return 'pdf_small.gif';
                case 'rar': return 'rar_small.gif';
                case 'zip': return 'zip_small.gif';
                case 'gz': return 'gz_small.gif';
                case 'jpg': return 'jpg_small.gif';
                case 'gif': return 'gif_small.gif';
                case 'png': return 'png_small.gif';
                case 'bmp': return 'image_small.gif';
                case 'exe': return 'binary_small.gif';
                case 'bin': return 'binary_small.gif';
                case 'avi': return 'mov_small.gif';
                case 'mpg': return 'mov_small.gif';
                case 'moc': return 'mov_small.gif';
                case 'asf': return 'mov_small.gif';
                case 'mp3': return 'sound_small.gif';
                case 'wav': return 'sound_small.gif';
                case 'org': return 'sound_small.gif';
        default:
                return 'def_small.gif';
        }
}

// DO NOT EDIT BELOW
$MY_NAME = 'insertfiledialog';
$lang_file = 'lang/lang-'.$MY_LANG.'.php';
if (is_file($lang_file)) require($lang_file);
else require('lang/lang-en.php');
$MY_PATH = '/';
$MY_UP_PATH = '/';

?>