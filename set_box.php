<?php
  /**************************************************************************\
  * eGroupWare                                                               *
  * http://www.egroupware.org                                                *
  * The file written by Joseph Engo <jengo@phpgroupware.org>                 *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

	$GLOBALS['egw_info'] = array(
		'flags' => Array(
			'noheader' => True,
			'nofooter' => True,
			'currentapp' => 'home'
		)
	);
	include('./header.inc.php');

	@reset($GLOBALS['egw_info']['user']['preferences']);

	function move_boxes($curr_position,$new_order,$offset,$value_to_check,$max_num)
	{
		//error_log(__METHOD__."MOVE: $curr_position,$new_order,$offset,$value_to_check,$max_num<br>");
		$move_app_id = (int)$GLOBALS['egw_info']['user']['preferences']['portal_order'][$curr_position];
		if(isset($GLOBALS['egw_info']['user']['preferences']['portal_order'][$new_order]))
		{
			if($new_order == $max_num)
			{
				if($offset < 0)
				{
					@ksort($GLOBALS['egw_info']['user']['preferences']['portal_order']);
				}
				else
				{
					@krsort($GLOBALS['egw_info']['user']['preferences']['portal_order']);
				}
				foreach($GLOBALS['egw_info']['user']['preferences']['portal_order'] as $seq_order => $appid)
				{
					if($seq_order != $value_to_check)
					{
						$prev_seq = $seq_order + $offset;
						$GLOBALS['egw']->preferences->add('portal_order',$prev_seq,$appid);
					}
				}
			}
			else
			{
				$GLOBALS['egw']->preferences->add('portal_order',$curr_position,(int)$GLOBALS['egw_info']['user']['preferences']['portal_order'][$new_order]);
			}
		}
		$GLOBALS['egw']->preferences->add('portal_order',$new_order,$move_app_id);

		$GLOBALS['egw']->preferences->save_repository();
	}

	//error_reporting(E_ALL);
	switch($_GET['control'])
	{
		//print_debug('set_box', $GLOBALS['egw_info']['user']['preferences']['portal_order']),'app',5);
		case 'up':
			$curr_position = $GLOBALS['egw']->common->find_portal_order((int)$_GET['app']);
			$max_count = count($GLOBALS['egw_info']['user']['preferences']['portal_order']) - 1;
			$offset = -1;
			if($curr_position <= 0)
			{
				$new_order = $max_count;
			}
			else
			{
				$new_order = $curr_position + $offset;
			}
			move_boxes($curr_position,$new_order,$offset,0,$max_count);
			break;
		case 'down':
			$curr_position = $GLOBALS['egw']->common->find_portal_order((int)$_GET['app']);
			$max_count = count($GLOBALS['egw_info']['user']['preferences']['portal_order']) - 1;
			$offset = 1;
			if($curr_position >= $max_count)
			{
				$new_order = 0;
			}
			else
			{
				$new_order = $curr_position + $offset;
			}
			move_boxes($curr_position,$new_order,$offset,$max_count,0);
			break;
		case 'close':
			$portal_varnames = array('mainscreen_showevents', 'homeShowEvents','homeShowLatest','mainscreen_showmail','mainscreen_showbirthdays','mainscreen_show_new_updated','homepage_display');
			$appname = $GLOBALS['egw']->applications->id2name($_GET['app']);
			foreach($portal_varnames as $varname)
				$GLOBALS['egw']->preferences->add($appname, $varname, 0);
			$GLOBALS['egw_info']['user']['preferences'] = $GLOBALS['egw']->preferences->save_repository();
			break;
		case 'edit':
		case 'question':
		default:
	}

	$GLOBALS['egw']->redirect_link('/home/index.php');
