<?php
  /**************************************************************************\
  * phpGroupWare                                                             *
  * This file written by Jonathon Sim <sim@zeald.com> for Zeald Ltd          *
  * View interface for the filemanager                                       *
  * Copyright (C) 2003 Zeald Ltd                                             *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

/*Due to incredibly annoying aspects of the XSLT template system, to be able to
output binary data you need to bypass XSLT altogether.  Hence this file. */

	$phpgw_flags = Array(
		'currentapp'	=>	'filemanager',
		'noheader'	=>	True,
		'nonavbar'	=>	True,
		'noappheader'	=>	True,
		'noappfooter'	=>	True,
		'nofooter'	=>	True
	);
	$GLOBALS['phpgw_info']['flags'] = $phpgw_flags;
	
	include('../header.inc.php');

	$ui = CreateObject($phpgw_flags['currentapp'].'.uifilemanager');
	$ui->bo->vfs->view(array (
				'string'	=> $ui->bo->path.'/'.$ui->bo->file,
				'relatives'	=> array (RELATIVE_NONE)
			));

?>
