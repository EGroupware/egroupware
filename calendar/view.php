<?php
  /**************************************************************************\
  * phpGroupWare - Calendar                                                  *
  * http://www.phpgroupware.org                                              *
  * Based on Webcalendar by Craig Knudsen <cknudsen@radix.net>               *
  *          http://www.radix.net/~cknudsen                                  *
  * Modified by Mark Peters <skeeter@phpgroupware.org>                       *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

	/* $Id$ */

//	$phpgw_flags = array(
//  		'currentapp'		=> 'calendar',
//  		'enable_nextmatchs_class'	=> True
//	);
//	$phpgw_info['flags'] = $phpgw_flags;
	$phpgw_info['flags']['currentapp'] = 'calendar';
  
	include('../header.inc.php');

	if ($id < 1)
	{
		echo lang('Invalid entry id.');
		$phpgw->common->phpgw_footer();
		$phpgw->common->phpgw_exit();
	}

	if($phpgw->calendar->check_perms(PHPGW_ACL_READ) == False)
	{
		echo lang('You do not have permission to read this record!');
		$phpgw->common->phpgw_footer();
		$phpgw->common->phpgw_exit();    
	}

	$cal_stream = $phpgw->calendar->open('INBOX',$owner,'');
	$event = $phpgw->calendar->fetch_event($cal_stream,$id);

	echo $phpgw->calendar->view_event($event);

	$thisyear	= $event->start->year;
	$thismonth	= $event->start->month;
	$thisday 	= $event->start->mday;
	
	$p = CreateObject('phpgwapi.Template',$phpgw->calendar->template_dir);

	$templates = Array(
		'form_button'	=> 'form_button_script.tpl'
	);
	$p->set_file($templates);

	echo '<center>';

	if (($event->owner == $owner) && ($phpgw->calendar->check_perms(PHPGW_ACL_EDIT) == True))
	{
		$p->set_var('action_url_button',$phpgw->link('/'.$phpgw_info['flags']['currentapp'].'/edit_entry.php','id='.$id.'&owner='.$owner));
		$p->set_var('action_text_button','  '.lang('Edit').'  ');
		$p->set_var('action_confirm_button','');
		echo $p->finish($p->parse('out','form_button'));
	}

	if (($event->owner == $owner) && ($phpgw->calendar->check_perms(PHPGW_ACL_DELETE) == True))
	{
		$p->set_var('action_url_button',$phpgw->link('/'.$phpgw_info['flags']['currentapp'].'/delete.php','id='.$id.'&owner='.$owner));
		$p->set_var('action_text_button',lang('Delete'));
		$p->set_var('action_confirm_button',"onClick=\"return confirm('".lang("Are you sure\\nyou want to\\ndelete this entry ?\\n\\nThis will delete\\nthis entry for all users.")."')\"");
		echo $p->finish($p->parse('out','form_button'));
	}

	echo '</center>';
	
	$phpgw->common->phpgw_footer();
?>
