<?php
	/**************************************************************************\
	* phpGroupWare - Info Log administration                                   *
	* http://www.phpgroupware.org                                              *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	$save_app = $GLOBALS['phpgw_info']['flags']['currentapp']; 
	$GLOBALS['phpgw_info']['flags']['currentapp'] = 'infolog'; 

	$GLOBALS['phpgw']->translation->add_app('infolog');

	/* echo "<p>hook_addressbook_view(ab_id=$ab_id)</p>"; */

	$link = CreateObject('infolog.uilink');
	$out = '<table>'.$link->getEntry('entry','addressbook',$GLOBALS['ab_id'])."\n".
	                 $link->showLinks('links','addressbook',$GLOBALS['ab_id'],'!infolog')."</table>\n";
	$html = CreateObject('infolog.html');
	$out = $html->form($out,'','/index.php',array('menuaction'=>'addressbook.uiaddressbook.view','ab_id'=>$GLOBALS['ab_id']));
	$GLOBALS['phpgw']->template->set_var('phpgw_body',$out,True);

	$infolog = CreateObject('infolog.uiinfolog');
	$infolog->get_list(True,'addr',$GLOBALS['ab_id']);

	$GLOBALS['phpgw_info']['flags']['currentapp'] = $save_app;
