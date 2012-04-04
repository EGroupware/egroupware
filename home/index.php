<?php
  /**************************************************************************\
  * eGroupWare                                                               *
  * http://www.egroupware.org                                                *
  * The file written by Joseph Engo <jengo@phpgroupware.org>                 *
  * This file modified by Greg Haygood <shrykedude@bellsouth.net>            *
  * This file modified by Edo van Bruggend <edovanbruggen@raketnet.nl>       *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

	/*
	** Initializing the home application
	*/
	$GLOBALS['egw_info'] = array(
		'flags' => array(
			'noheader'                => true,
			'nonavbar'                => true,
			'currentapp'              => 'home',
		)
	);

	include('../header.inc.php');
	auth::check_password_age('home','index');
	$GLOBALS['egw_info']['flags']['nonavbar']=false;

	/*
	** Initializing the template
	*/

	$GLOBALS['tpl'] = new Template(common::get_tpl_dir('home'));
	$GLOBALS['tpl']->set_unknowns('remove');

	$GLOBALS['tpl']->set_file(
		array(
			'home' => 'home.tpl'
		)
	);

	$GLOBALS['tpl']->set_block('home','notify_window','notify_window');
	$GLOBALS['tpl']->set_block('home','begin_table','begin_table');
	$GLOBALS['tpl']->set_block('home','end_table','end_table');
	$GLOBALS['tpl']->set_block('home','begin_row','begin_row');
	$GLOBALS['tpl']->set_block('home','end_row','end_row');
	$GLOBALS['tpl']->set_block('home','cell','cell');

	if ($GLOBALS['egw']->session->session_flags == 'A')
	{
		if ($_SERVER['HTTP_REFERER'] && strstr($_SERVER['HTTP_REFERER'],'home.php') === False)
		{
			$GLOBALS['egw']->redirect($_SERVER['HTTP_REFERER']);
		}
		else
		{
			// redirect to the login-page, better then giving an empty page
			$GLOBALS['egw']->redirect('login.php');
		}
		exit;
	}

	/*
	** Show the updates
	*/
	$GLOBALS['egw']->hooks->single('showUpdates','home');

	/*
	** Display the mainscreen message
	*/
	translation::add_app('mainscreen');
	$greeting = translation::translate('mainscreen_message',false,'');

	if($greeting == 'mainscreen_message'|| empty($greeting))
	{
		translation::add_app('mainscreen','en');    // trying the en one
		$greeting = translation::translate('mainscreen_message',false,'');
	}
	if(!($greeting == 'mainscreen_message'|| empty($greeting)))
	{
		echo '<div style="text-align: center;">' . $greeting . "</div>\n";
	}

	/*
	** Display the notification window
	*/
	if (isset($GLOBALS['egw_info']['user']['apps']['notifywindow']) && $GLOBALS['egw_info']['user']['apps']['notifywindow'])
	{
		$var['link'] = $GLOBALS['egw']->link('/notify.php');
		$var['notifywindow'] = lang('Open notify window');
		$GLOBALS['tpl']->set_var($var);
		$GLOBALS['tpl']->pfp('out','notify_window');
	}

	if (is_array($GLOBALS['egw_info']['user']['preferences']['portal_order']))
	{
		$sorted_apps = array();
		ksort($GLOBALS['egw_info']['user']['preferences']['portal_order']);
		foreach($GLOBALS['egw_info']['user']['preferences']['portal_order'] as $order => $app)
		{
			$sorted_apps[$app] = $GLOBALS['egw']->applications->id2name((int)$app);
		}
	}
	else
	{
		$sorted_apps = Array(
			'calendar',
			'email',
			'infolog',
			'news_admin'
		);
	}

	// Now add the rest of the user's apps, to make sure we pick up any additions to the home display
	foreach($GLOBALS['egw_info']['user']['apps'] as $app)
	{
		if(!in_array($app['name'], $sorted_apps))
		{
			$sorted_apps[] = $app['name'];
		}
	}

	// make sure to show only apps user has rights to
	$sorted_apps = array_intersect($sorted_apps, array_keys($GLOBALS['egw_info']['user']['apps']));

	$portal_oldvarnames = array('mainscreen_showevents', 'homeShowEvents','homeShowLatest','mainscreen_showmail','mainscreen_showbirthdays','mainscreen_show_new_updated');
	$neworder = array();
	$done = array();

	/*
	** Display application hooks
	**
	*/

	$GLOBALS['tpl']->pfp('out','begin_table');
	$tropen=0;
	$tdopen=0;
	$lastd = 0;
	$numcols = 2;
	$curcol = 1;
	$searchlist = array();
	foreach($sorted_apps as $appname)
	{
		if((int)$done[$appname] == 1 || empty($appname))
		{
			continue;
		}
		$varnames = $portal_oldvarnames;
		$varnames[] = 'homepage_display';
		foreach($varnames as $varcheck)
		{
			//echo "$appname:$varcheck=".$GLOBALS['egw_info']['user']['preferences'][$appname][$varcheck]."<br>";
			$thisd = (int)$GLOBALS['egw_info']['user']['preferences'][$appname][$varcheck];
			if (!$thisd && $GLOBALS['egw_info']['user']['preferences'][$appname][$varcheck]) $thisd = 1;
			if($thisd>0)
			{
				//echo "Found $appname=$_thisd through $varcheck<br>";
				break;
			}
		}
		//echo "$appname: $thisd<br>";
		if($thisd>0)
		{
			$searchlist[$appname] = $thisd;
			$neworder[] = $appname;
			$done[$appname] = 1;
		}
	}
	foreach($neworder as $index => $appname)
	{
		$thisd = $searchlist[$appname];
		//echo "$appname: $thisd<br>";
		if((($curcol++>$numcols) || ($thisd==1)) && $tropen==1)
		{
			$GLOBALS['tpl']->pfp('out','end_row');
			$tropen = 0;
			//$curcol = 1;
		}
		if(!$tropen)
		{
			$GLOBALS['tpl']->pfp('out','begin_row');
			$tropen=1;
		}

		$small = ($thisd>1) && (count($neworder) >= $index + 1) && ($searchlist[$neworder[$index + 1]] > 1 || $searchlist[$neworder[$index - 1]] > 1);
		$var['tdwidth'] = $small ? '50' : '100';
		$var['colspan'] = $small ? '1' : '2';

		ob_start();
		$var['content'] = $GLOBALS['egw']->hooks->single('home',$appname);
		if (!$var['content'] || $var['content'] == 1)	// content has been echoed and not returned
		{
			$var['content'] = ob_get_contents();
			ob_end_clean();
		}
		$GLOBALS['tpl']->set_var($var);

		$GLOBALS['tpl']->pfp('out','cell');

		if(($thisd<=1 || ($thisd>1&&$lastd>1)) && $tropen && $var['content'])
		{
			$GLOBALS['tpl']->pfp('out','end_row');
			$tropen = 0;
			$lastd = 0;
			$curcol = 1;
 		}
		else
		{
			$lastd = $thisd;
		}
	}

	$GLOBALS['tpl']->pfp('out','end_table');

	// Update stored value of order
	//_debug_array($neworder);
	if(count($neworder)>0)//$GLOBALS['portal_order'])
	{
		$GLOBALS['egw']->preferences->delete('portal_order');
		foreach($neworder as $app_order => $app_name)
		{
			$app_id = $GLOBALS['egw']->applications->name2id($app_name);
			//echo "neworder: $app_order=$app_id:$app_name<br>";
			$GLOBALS['egw']->preferences->add('portal_order',$app_order,$app_id);
		}
		$GLOBALS['egw_info']['user']['preferences'] = $GLOBALS['egw']->preferences->save_repository();
	}
	//_debug_array($GLOBALS['egw_info']['user']['preferences']);

	$GLOBALS['egw']->framework->render(ob_get_clean());
