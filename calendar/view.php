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

	$phpgw_flags = array(
  		'currentapp'		=> 'calendar'
	);
	$phpgw_info['flags'] = $phpgw_flags;
  
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

	$phpgw->calendar->open('INBOX',$owner,'');
	$event = $phpgw->calendar->fetch_event(intval($id));

	echo '<center>';

	if(isset($event->id))
	{
		echo $phpgw->calendar->view_event($event);

		$thisyear	= $event->start->year;
		$thismonth	= $event->start->month;
		$thisday 	= $event->start->mday;
	
		$p = CreateObject('phpgwapi.Template',$phpgw->calendar->template_dir);

		$templates = Array(
			'form_button'	=> 'form_button_script.tpl'
		);
		$p->set_file($templates);

		if (($event->owner == $owner) && ($phpgw->calendar->check_perms(PHPGW_ACL_EDIT) == True))
		{
			$var = Array(
				'action_url_button'	=> $phpgw->link('/calendar/edit_entry.php','id='.$id.'&owner='.$owner),
				'action_text_button'	=> lang('Edit'),
				'action_confirm_button'	=> '',
				'action_extra_field'	=> ''
			);
			$p->set_var($var);
			echo $p->finish($p->parse('out','form_button'));
		}

		if (($event->owner == $owner) && ($phpgw->calendar->check_perms(PHPGW_ACL_DELETE) == True))
		{
			$var = Array(
				'action_url_button'	=> $phpgw->link('/'.$phpgw_info['flags']['currentapp'].'/delete.php','id='.$id.'&owner='.$owner),
				'action_text_button'	=> lang('Delete'),
				'action_confirm_button'	=> "onClick=\"return confirm('".lang("Are you sure\\nyou want to\\ndelete this entry ?\\n\\nThis will delete\\nthis entry for all users.")."')\"",
				'action_extra_field'	=> ''
			);
			$p->set_var($var);
			echo $p->finish($p->parse('out','form_button'));
		}
	}
	else
	{
		echo lang("Sorry, the owner has just deleted this event").'.';
	}
	echo '</center>';
	
	$phpgw->common->phpgw_footer();
?>
