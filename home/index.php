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



/*
** Checking the security before anything is displayed
*/
$GLOBALS['sessionid'] = @$_GET['sessionid'] ? $_GET['sessionid'] : @$_COOKIE['sessionid'];
if (!isset($GLOBALS['sessionid']) || !$GLOBALS['sessionid'])
{
	Header('Location: ../login.php');
	exit;
}


/*
** Initializing the home application
*/
$GLOBALS['phpgw_info']['flags'] = array(
	'noheader'                => False,
	'nonavbar'                => False,
	'currentapp'              => 'home',
	'enable_network_class'    => False,
	'enable_contacts_class'   => False,
	'enable_nextmatchs_class' => False
);

include('../header.inc.php');

$GLOBALS['phpgw_info']['flags']['app_header']=lang('home');


/*
** Initializing the template
*/

$GLOBALS['tpl'] = CreateObject('phpgwapi.Template',$GLOBALS['phpgw']->common->get_tpl_dir('home'));
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

	


// Commented by alpeb: The following prevented anonymous users to get a home page. Perhaps it was done with anonymous users such as the ones
// used by  wiki and sitemgr in mind. However, if you mark a normal user as anonymous just to avoid being shown in sessions and access log (like you would for an admin that doesn't want to be noticed), the user won't be able to login anymore. That's why I commented the code.
/*if ($GLOBALS['phpgw']->session->session_flags == 'A')
{
	if ($_SERVER['HTTP_REFERER'] && strstr($_SERVER['HTTP_REFERER'],'home.php') === False)
	{
		$GLOBALS['phpgw']->redirect($_SERVER['HTTP_REFERER']);
	}
	else
	{
		// redirect to the login-page, better then giving an empty page
		$GLOBALS['phpgw']->redirect('login.php');
	}
	exit;
}*/


/*
** Show the updates
*/	
$GLOBALS['phpgw']->hooks->single('showUpdates','home');

	
/*
** Display the notification window
*/
if (isset($GLOBALS['phpgw_info']['user']['apps']['notifywindow']) && $GLOBALS['phpgw_info']['user']['apps']['notifywindow'])
{
	$var['link'] = $GLOBALS['phpgw']->link('/notify.php');
	$var['notifywindow'] = lang('Open notify window');
	$GLOBALS['tpl']->set_var($var);
	$GLOBALS['tpl']->pfp('out','notify_window');
}
	

/* This initializes the users portal_order preference if it does not exist. */
if(!is_array($GLOBALS['phpgw_info']['user']['preferences']['portal_order']) && $GLOBALS['phpgw_info']['apps'])
{
	$GLOBALS['phpgw']->preferences->delete('portal_order');
	@reset($GLOBALS['phpgw_info']['apps']);
	$order = 0;
	while (list(,$p) = each($GLOBALS['phpgw_info']['apps']))
	{
		if($GLOBALS['phpgw_info']['user']['apps'][$p['name']])
		{
			$GLOBALS['phpgw']->preferences->add('portal_order',$order++,$p['id']);
		}
	}
	$GLOBALS['phpgw_info']['user']['preferences'] = $GLOBALS['phpgw']->preferences->save_repository();
}

if(is_array($GLOBALS['phpgw_info']['user']['preferences']['portal_order']))
{
	$app_check = Array();
	@ksort($GLOBALS['phpgw_info']['user']['preferences']['portal_order']);
	while(list($order,$app) = each($GLOBALS['phpgw_info']['user']['preferences']['portal_order']))
	{
		if(!isset($app_check[(int)$app]) || !$app_check[(int)$app])
		{
			$app_check[(int)$app] = True;
			$sorted_apps[] = $GLOBALS['phpgw']->applications->id2name((int)$app);
		}
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
@reset($GLOBALS['phpgw_info']['user']['apps']);
while(list(,$p) = each($GLOBALS['phpgw_info']['user']['apps']))
{
	$sorted_apps[] = $p['name'];
}
//$GLOBALS['phpgw']->hooks->process('home',$sorted_apps);


/*
** Migrate preferences
** @param $appname, $var_old, $var_new, $type='user'
**  
*/
function migrate_pref($appname,$var_old,$var_new,$type='user')
{
	if(empty($appname) || empty($var_old) || empty($var_new))
	{
		return false;
	}
	$allowedtypes = array('user','default','forced');
	if($type=='all')
	{
		$types = $allowedtypes;
	}
	elseif(in_array($type,$allowedtypes)) 
	{
		$types[] = $type;
	}
	else
	{
		return false;
	}
	$result = false;
	foreach($types as $_type)
	{
		if(isset($GLOBALS['phpgw']->preferences->$_type[$appname][$var_old]))
		{
			$GLOBALS['phpgw']->preferences->$_type[$appname][$var_new] =
				$GLOBALS['phpgw']->preferences->$_type[$appname][$var_old];
			$result = true;
			$GLOBALS['phpgw_info']['user']['preferences'] =
				$GLOBALS['phpgw']->preferences->save_repository(false,$_type);
		}
	}
	return $result;
}

$portal_oldvarnames = array('mainscreen_showevents', 'homeShowEvents','homeShowLatest','mainscreen_showmail','mainscreen_showbirthdays','mainscreen_show_new_updated');
$migrate_oldvarnames = false;
if($migrate_oldvarnames)
{
	$_apps = $GLOBALS['phpgw_info']['user']['apps'];
	@reset($_apps);
	foreach($_apps as $_appname)
	{
		@reset($portal_oldvarnames);
		foreach($portal_oldvarnames as $varname)
		{
			//echo "Want to migrate '$appname' from '$varname' to 'homepage_display'.<br>";
			//migrate_pref($appname,$varname,'homepage_display','all');
		}
	}
}

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
@reset($sorted_apps);
foreach($sorted_apps as $appname)
{
	if((int)$done[$appname] == 1 || empty($appname))
	{
		continue;
	}
	$varnames = $portal_oldvarnames;
	$varnames[] = 'homepage_display';
	$thisd = 0;
	foreach($varnames as $varcheck)
	{
		//echo "$appname:$varcheck=".$GLOBALS['phpgw_info']['user']['preferences'][$appname][$varcheck]."<br>";
		if($GLOBALS['phpgw_info']['user']['preferences'][$appname][$varcheck]=='True')
		{
			$thisd = 1;
			break;
		}
		else 
		{
			$_thisd = (int)$GLOBALS['phpgw_info']['user']['preferences'][$appname][$varcheck];
			if($_thisd>0)
			{
				//echo "Found $appname=$_thisd through $varcheck<br>";
				$thisd = $_thisd;
				break;
			}
		}
	}
	//echo "$appname: $thisd<br>";
	if($thisd>0)
	{
		if((($curcol++>$numcols) || ($thisd+$lastd==3)) && $tropen==1)
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
		$var['tdwidth'] = ($thisd==2)?'50':'100';
		$var['colspan'] = ($thisd==2)?'1':'2';
		
		ob_start();
		$var['content'] = $GLOBALS['phpgw']->hooks->single('home',$appname);
		if (!$var['content'] || $var['content'] == 1)	// content has been echoed and not returned
		{
			$var['content'] = ob_get_contents();
			ob_end_clean();
		}
		$GLOBALS['tpl']->set_var($var);
				
		$GLOBALS['tpl']->pfp('out','cell');
		
		if(($thisd!=2 || ($thisd==2&&$lastd==2)) && $tropen)
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
		$neworder[] = $appname;
	}
	$done[$appname] = 1;
}


$GLOBALS['tpl']->pfp('out','end_table');

// Update stored value of order
//_debug_array($neworder);
if(count($neworder)>0)//$GLOBALS['portal_order'])
{
	$GLOBALS['phpgw']->preferences->delete('portal_order');
	@reset($neworder);
	while(list($app_order,$app_name) = each($neworder))
	{
		$app_id = $GLOBALS['phpgw']->applications->name2id($app_name);
		//echo "neworder: $app_order=$app_id:$app_name<br>";
		$GLOBALS['phpgw']->preferences->add('portal_order',$app_order,$app_id);
	}
	$GLOBALS['phpgw_info']['user']['preferences'] = $GLOBALS['phpgw']->preferences->save_repository();
}
//_debug_array($GLOBALS['phpgw_info']['user']['preferences']);

//$phpgw->common->debug_phpgw_info();
//$phpgw->common->debug_list_core_functions(); 
$GLOBALS['phpgw']->common->phpgw_footer(); 
?>


