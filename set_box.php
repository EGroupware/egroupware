<?php
  /**************************************************************************\
  * phpGroupWare                                                             *
  * http://www.phpgroupware.org                                              *
  * The file written by Joseph Engo <jengo@phpgroupware.org>                 *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

	$GLOBALS['phpgw_info']['flags'] = Array(
		'noheader'	=> True,
		'nofooter'	=> True,
		'currentapp'	=> 'home'
	);
	include('header.inc.php');

//	if(basename($HTTP_REFERER) != 'home.php')
//	{
//		Header('Location: '.$GLOBALS['phpgw']->link('/home.php'));
//		$GLOBALS['phpgw']->common->phpgw_exit();
//	}

//	$GLOBALS['phpgw']->preferences->delete('portal_order');
//	unset($GLOBALS['phpgw_info']['user']['preferences']['portal_order']);
//	$GLOBALS['phpgw']->preferences->save_repository();

	switch($GLOBALS['HTTP_GET_VARS']['control'])
	{
		case 'up':
			if(is_array($GLOBALS['phpgw_info']['user']['preferences']['portal_order']))
			{
				if($GLOBALS['HTTP_GET_VARS']['order'] == 0)
				{
					@ksort($GLOBALS['phpgw_info']['user']['preferences']['portal_order']);
					list($seq_order,$appid) = each($GLOBALS['phpgw_info']['user']['preferences']['portal_order']);
					while(list($seq_order,$appid) = each($GLOBALS['phpgw_info']['user']['preferences']['portal_order']))
					{
						$GLOBALS['phpgw']->preferences->add('portal_order',intval($seq_order) - 1,intval($appid));
					}
					$GLOBALS['phpgw']->preferences->add('portal_order',intval($seq_order),intval($GLOBALS['HTTP_GET_VARS']['app']));
				}
				else
				{
					$old_app = intval($GLOBALS['phpgw_info']['user']['preferences']['portal_order'][intval($GLOBALS['HTTP_GET_VARS']['order']) - 1]);
					$GLOBALS['phpgw']->preferences->add('portal_order',intval($GLOBALS['HTTP_GET_VARS']['order']) - 1,intval($GLOBALS['HTTP_GET_VARS']['app']));
					$GLOBALS['phpgw']->preferences->add('portal_order',intval($GLOBALS['HTTP_GET_VARS']['order']),$old_app);
				}
			}
			else
			{
				if($GLOBALS['HTTP_GET_VARS']['order'] == 0)
				{
					$new_order = 999;
				}
				else
				{
					$new_order = intval($GLOBALS['HTTP_GET_VARS']['order']) - 1;
				}
				$GLOBALS['phpgw']->preferences->add('portal_order',$new_order,intval($GLOBALS['HTTP_GET_VARS']['app']));
			}
			$GLOBALS['phpgw']->preferences->save_repository();
			break;
		case 'down':
		case 'edit':
		case 'question':
		case 'close':
		default:
	}

	Header('Location: '.$GLOBALS['phpgw']->link('/home.php'));
	$GLOBALS['phpgw']->common->phpgw_exit();
?>

