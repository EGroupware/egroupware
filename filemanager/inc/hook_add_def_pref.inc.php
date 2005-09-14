<?php
  /**************************************************************************\
  * eGroupWare - Filemanager                                                 *
  * http://www.egroupware.org                                                *
  * ------------------------------------------------------------------------ *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

	global $pref;

	$pref->change('filemanager', 'name', 'True');
	$pref->change('filemanager', 'mime_type', 'True');
	$pref->change('filemanager', 'size', 'True');
	$pref->change('filemanager', 'created', 'True');
	$pref->change('filemanager', 'modified', 'True');
	//$pref->change('filemanager', 'owner', 'False');
	$pref->change('filemanager', 'createdby_id', 'True');
	$pref->change('filemanager', 'modifiedby_id', 'True');
	//$pref->change('filemanager', 'app', 'False');
	$pref->change('filemanager', 'comment', 'True');
	//$pref->change('filemanager', 'viewinnewwin', 'False');
	//$pref->change('filemanager', 'viewonserver', 'False');
	$pref->change('filemanager', 'viewtextplain', True);
	//$pref->change('filemanager', 'dotdot', 'False');
	//$pref->change('filemanager', 'dotfiles', 'False');
	//$pref->change('filemanager', 'show_help', 'False');
	$pref->change('filemanager', 'show_upload_boxes', '5');

?>
