<?php
  /**************************************************************************\
  * phpGroupWare                                                             *
  * http://www.phpgroupware.org                                              *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */
	
	$GLOBALS['phpgw']->template->set_var('phpgw_left_table_width','5%');
	$GLOBALS['phpgw']->template->set_var('phpgw_body_table_height','85%');
	$GLOBALS['phpgw']->template->set_var('phpgw_bottom_table_height','5%');

	function parse_toppart($output)
	{
		$GLOBALS['phpgw']->template->set_file('parts','parts.tpl');
		$GLOBALS['phpgw']->template->set_block('parts','top_part');
		$var['img_root'] = PHPGW_IMAGES_DIR;

		$find_single = strrpos($GLOBALS['phpgw_info']['server']['webserver_url'],'/');
		$find_double = strpos(strrev($GLOBALS['phpgw_info']['server']['webserver_url'].' '),'//');
		if($find_double)
		{
			$find_double = strlen($GLOBALS['phpgw_info']['server']['webserver_url']) - $find_double - 1;
		}
		if($find_double)
		{
			if($find_single == $find_double + 1)
			{
				$GLOBALS['strip_portion'] = $GLOBALS['phpgw_info']['server']['webserver_url'];
			}
			else
			{
				$GLOBALS['strip_portion'] = substr($GLOBALS['phpgw_info']['server']['webserver_url'],0,$find_double + 1);
			}
		}
		else
		{
			$GLOBALS['strip_portion'] = $GLOBALS['phpgw_info']['server']['webserver_url'].'/';
		}

		$var['home_link'] = $GLOBALS['phpgw_info']['navbar']['home']['url'];
		$var['preferences_link'] = $GLOBALS['phpgw_info']['navbar']['preferences']['url'];
		$var['logout_link'] = $GLOBALS['phpgw_info']['navbar']['logout']['url'];
		$var['help_link'] = $GLOBALS['phpgw_info']['navbar']['about']['url'];

		if ($GLOBALS['phpgw_info']['flags']['currentapp'] != 'home')
		{
			$var['welcome_img'] = $GLOBALS['phpgw']->common->image('phpgwapi','welcome2');
			$GLOBALS['phpgw_info']['flags']['preload_images'][] = $GLOBALS['phpgw']->common->image_on('phpgwapi','welcome2','_over');
		}
		else
		{
			$var['welcome_img'] = $GLOBALS['phpgw']->common->image_on('phpgwapi','welcome2','_over');
			$GLOBALS['phpgw_info']['flags']['preload_images'][] = $GLOBALS['phpgw']->common->image('phpgwapi','welcome2');
		}

		if ($GLOBALS['phpgw_info']['flags']['currentapp'] != 'preferences')
		{
			$var['preferences_img'] = $GLOBALS['phpgw']->common->image('phpgwapi','preferences2');
			$GLOBALS['phpgw_info']['flags']['preload_images'][] = $GLOBALS['phpgw']->common->image_on('phpgwapi','preferences2','_over');
		}
		else
		{
			$var['preferences_img'] = $GLOBALS['phpgw']->common->image_on('phpgwapi','preferences2','_over');
			$GLOBALS['phpgw_info']['flags']['preload_images'][] = $GLOBALS['phpgw']->common->image('phpgwapi','preferences2');
		}

		$var['logout_img'] = $GLOBALS['phpgw']->common->image('phpgwapi','log_out2');
		$GLOBALS['phpgw_info']['flags']['preload_images'][] = $GLOBALS['phpgw']->common->image_on('phpgwapi','log_out2','_over');

		
		if ($GLOBALS['phpgw_info']['flags']['currentapp'] != 'about')
		{
			$var['about_img'] = $GLOBALS['phpgw']->common->image('phpgwapi','question_mark2');
			$var['about_img_hover'] = $GLOBALS['phpgw']->common->image_on('phpgwapi','question_mark2','_over');
		}
		else
		{
			$var['about_img'] = $GLOBALS['phpgw']->common->image_on('phpgwapi','question_mark2','_over');
			$var['about_img_hover'] = $GLOBALS['phpgw']->common->image('phpgwapi','question_mark2');
		}

		$var['logo_img'] = $GLOBALS['phpgw']->common->image('phpgwapi','logo2');

		// "powered_by_color" and "_size" are is also used by number of current users thing

		if (isset($GLOBALS['phpgw_info']['navbar']['admin']) && isset($GLOBALS['phpgw_info']['user']['preferences']['common']['show_currentusers']))
		{
			$db  = $GLOBALS['phpgw']->db;
			$db->query('select count(session_id) from phpgw_sessions');
			$db->next_record();
			$var['current_users'] = '<a href="' . $GLOBALS['phpgw']->link('/index.php','menuaction=admin.uicurrentsessions.list_sessions')
			 	. '">&nbsp;' . lang('Current users') . ': ' . $db->f(0) . '</a>';
			$GLOBALS['phpgw']->template->set_var($var);
		}
		else
		{
			$var['current_users'] = '';
			$GLOBALS['phpgw']->template->set_var($var);
		}

		$var['user_info_name'] = $GLOBALS['phpgw']->common->display_fullname();
		$now = time();
		$var['user_info_date'] =
				  lang($GLOBALS['phpgw']->common->show_date($now,'l')) . ' '
				. $GLOBALS['phpgw']->common->show_date($now,$GLOBALS['phpgw_info']['user']['preferences']['common']['dateformat']);
		$var['user_info'] = $var['user_info_name'] .' - ' .$var['user_info_date'];

		$GLOBALS['phpgw']->template->set_var($var);
		$GLOBALS['phpgw']->template->fp($output,'top_part');
	}

	function parse_leftpart($output)
	{
		$GLOBALS['phpgw']->template->set_file('parts','parts.tpl');
		$GLOBALS['phpgw']->template->set_block('parts','left_part');
		$applications = '';
		while ($app = each($GLOBALS['phpgw_info']['navbar']))
		{
			if ($app[1]['title'] != 'Home' && $app[1]['title'] != 'preferences' && !ereg('About',$app[1]['title']) && $app[1]['title'] != 'Logout')
			{
				$title = '<img src="' . $app[1]['icon'] . '" alt="' . $app[1]['title'] . '" title="'
					. lang($app[1]['title']) . '" border="0" name="' . str_replace('-','_',$app[0]) . '">';
				$img_src_over = $app[1]['icon_hover'];
				$img_src_out = $app[1]['icon'];

				$applications .= '<tr><td class="left"><a href="' . $app[1]['url'] . '"';
				if (isset($GLOBALS['phpgw_info']['flags']['navbar_target']))
				{
					$applications .= ' target="' . $GLOBALS['phpgw_info']['flags']['navbar_target'] . '"';
				}

				if($img_src_over != '')
				{
					$applications .= ' onMouseOver="' . str_replace('-','_',$app[0]) . ".src='" . $img_src_over . '\'"';
				}
				if($img_src_out != '')
				{
					$applications .= ' onMouseOut="' . str_replace('-','_',$app[0]) . ".src='" . $img_src_out . '\'"';
				}
				$applications .= '>'.$title.'</a></td></tr>'."\r\n";
			}
			else
			{
				$img_src_over = $GLOBALS['phpgw']->common->image_on($app[0],Array('navbar','nonav'),'-over');
			}
			if($img_src_over != '')
			{
//				if($GLOBALS['strip_portion'])
//				{
//					$img_src_over = str_replace($GLOBALS['strip_portion'],'',$img_src_over);
//				}
				$GLOBALS['phpgw_info']['flags']['preload_images'][] = $img_src_over;
			}
		}

		$var['applications'] = $applications;
		$var['nav_bar_left_top_bg_img'] = $GLOBALS['phpgw']->common->image('phpgwapi','nav_bar_left_top_bg');

		$GLOBALS['phpgw']->template->set_var($var);
		$GLOBALS['phpgw']->template->fp($output,'left_part');
	}

	function parse_bodypart()
 	{
	}
	
	function parse_bottompart($output)
	{
		$GLOBALS['phpgw']->template->set_file('parts','parts.tpl');
		$GLOBALS['phpgw']->template->set_block('parts','bottom_part');

		$var = Array
		(
			'powered'	=> lang('Powered by phpGroupWare version x', $GLOBALS['phpgw_info']['server']['versions']['phpgwapi']),
		);
		$var['top_spacer_middle_img'] = $GLOBALS['phpgw']->common->image('phpgwapi','top_spacer_middle');
		$GLOBALS['phpgw']->template->set_var($var);
		$GLOBALS['phpgw']->template->fp($output,'bottom_part');
	}
