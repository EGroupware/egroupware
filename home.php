<?php
	/**************************************************************************\
	* eGroupWare                                                               *
	* http://www.egroupware.org                                                *
	* The file written by Joseph Engo <jengo@phpgroupware.org>                 *
	* This file modified by Greg Haygood <shrykedude@bellsouth.net>            *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	$phpgw_info = array();
	if (!is_file('header.inc.php'))
	{
		Header('Location: setup/index.php');
		exit;
	}

	$GLOBALS['sessionid'] = @$_GET['sessionid'] ? $_GET['sessionid'] : $_COOKIE['sessionid'];
	if (!isset($GLOBALS['sessionid']) || !$GLOBALS['sessionid'])
	{
		Header('Location: login.php');
		exit;
	}

	$GLOBALS['phpgw_info']['flags'] = array(
		'noheader'                => True,
		'nonavbar'                => True,
		'currentapp'              => 'home',
		'enable_network_class'    => True,
		'enable_contacts_class'   => True,
		'enable_nextmatchs_class' => True
	);
	include('header.inc.php');

	if ($_GET['phpgw_forward'])
	{
		foreach($_GET as $name => $value)
		{
			if (ereg('phpgw_',$name))
			{
				$extra_vars .= '&' . $name . '=' . urlencode($value);
			}
		}
		$GLOBALS['phpgw']->redirect_link($_GET['phpgw_forward'],$extra_vars);
		exit;
	}

	if ($GLOBALS['phpgw_info']['server']['force_default_app'] && $GLOBALS['phpgw_info']['server']['force_default_app'] != 'user_choice')
	{
		$GLOBALS['phpgw_info']['user']['preferences']['common']['default_app'] = $GLOBALS['phpgw_info']['server']['force_default_app'];
	}

	if (($GLOBALS['phpgw_info']['user']['preferences']['common']['useframes'] &&
		$GLOBALS['phpgw_info']['server']['useframes'] == 'allowed') ||
		($GLOBALS['phpgw_info']['server']['useframes'] == 'always'))
		{
			if ($_GET['cd'] == 'yes')
			{
				if (! $navbarframe && ! $framebody)
				{
					$tpl = new Template(PHPGW_TEMPLATE_DIR);
					$tpl->set_file(array(
						'frames'       => 'frames.tpl',
						'frame_body'   => 'frames_body.tpl',
						'frame_navbar' => 'frames_navbar.tpl'
					));
					$tpl->set_var('navbar_link',$GLOBALS['phpgw']->link('index.php','navbarframe=True&cd=yes'));
					if ($GLOBALS['forward'])
					{
						$tpl->set_var('body_link',$GLOBALS['phpgw']->link($GLOBALS['forward']));
					}
					else
					{
						$tpl->set_var('body_link',$GLOBALS['phpgw']->link('index.php','framebody=True&cd=yes'));
					}

					if ($GLOBALS['phpgw_info']['user']['preferences']['common']['frame_navbar_location'] == 'bottom')
					{
						$tpl->set_var('frame_size','*,60');
						$tpl->parse('frames_','frame_body',True);
						$tpl->parse('frames_','frame_navbar',True);
					}
					else
					{
						$tpl->set_var('frame_size','60,*');
						$tpl->parse('frames_','frame_navbar',True);
						$tpl->parse('frames_','frame_body',True);
					}
					$tpl->pparse('out','frames');
				}
				if ($navbarframe)
				{
					$GLOBALS['phpgw']->common->phpgw_header();
					echo parse_navbar();
				}
			}
		}
		elseif ($_GET['cd']=='yes' && $GLOBALS['phpgw_info']['user']['preferences']['common']['default_app']
			&& $GLOBALS['phpgw_info']['user']['apps'][$GLOBALS['phpgw_info']['user']['preferences']['common']['default_app']])
		{
			$GLOBALS['phpgw']->redirect($GLOBALS['phpgw']->link('/' . $GLOBALS['phpgw_info']['user']['preferences']['common']['default_app'] . '/' . 'index.php'));
		}
		else
		{
			$GLOBALS['phpgw']->common->phpgw_header();
			echo parse_navbar();
		}

		$GLOBALS['phpgw']->translation->add_app('mainscreen');
		if (lang('mainscreen_message') != 'mainscreen_message*')
		{
			echo '<center>' . stripslashes(lang('mainscreen_message')) . '</center>';
		}

		if ((isset($GLOBALS['phpgw_info']['user']['apps']['admin']) &&
			$GLOBALS['phpgw_info']['user']['apps']['admin']) &&
			(isset($GLOBALS['phpgw_info']['server']['checkfornewversion']) &&
			$GLOBALS['phpgw_info']['server']['checkfornewversion']))
		{
			$GLOBALS['phpgw']->network->set_addcrlf(False);
			$lines = $GLOBALS['phpgw']->network->gethttpsocketfile('http://www.phpgroupware.org/currentversion');
			for ($i=0; $i<count($lines); $i++)
			{
				if (ereg('currentversion',$lines[$i]))
				{
					$line_found = explode(':',chop($lines[$i]));
				}
			}
			if($GLOBALS['phpgw']->common->cmp_version($GLOBALS['phpgw_info']['server']['versions']['phpgwapi'],$line_found[1]))
			{
				echo '<p>There is a new version of eGroupWare available. <a href="'
					. 'http://www.phpgroupware.org">http://www.phpgroupware.org</a>';
			}

			$_found = False;
			$GLOBALS['phpgw']->db->query("select app_name,app_version from phpgw_applications",__LINE__,__FILE__);
			while($GLOBALS['phpgw']->db->next_record())
			{
				$_db_version  = $GLOBALS['phpgw']->db->f('app_version');
				$_app_name    = $GLOBALS['phpgw']->db->f('app_name');
				$_versionfile = $GLOBALS['phpgw']->common->get_app_dir($_app_name) . '/setup/setup.inc.php';
				if(file_exists($_versionfile))
				{
					include($_versionfile);
					$_file_version = $setup_info[$_app_name]['version'];
					$_app_title    = $GLOBALS['phpgw_info']['apps'][$_app_name]['title'];
					unset($setup_info);

					if($GLOBALS['phpgw']->common->cmp_version_long($_db_version,$_file_version))
					{
						$_found = True;
						$_app_string .= '<br>' . $_app_title;
					}
					unset($_file_version);
					unset($_app_title);
				}
				unset($_db_version);
				unset($_versionfile);
			}
			if($_found)
			{
				echo '<br>' . lang('The following applications require upgrades') . ':' . "\n";
				echo $_app_string . "\n";
				echo '<br>' . lang('Please run setup to become current') . '.' . "\n";
				unset($_app_string);
			}
		}

	if (isset($GLOBALS['phpgw_info']['user']['apps']['notifywindow']) &&
		$GLOBALS['phpgw_info']['user']['apps']['notifywindow'])
	{
?>
<SCRIPT LANGUAGE="JavaScript" TYPE="text/javascript">
	var NotifyWindow;

	function opennotifywindow()
	{
		if (NotifyWindow)
		{
			if (NotifyWindow.closed)
			{
				NotifyWindow.stop;
				NotifyWindow.close;
			}
		}
		NotifyWindow = window.open("<?php echo $GLOBALS['phpgw']->link('/notify.php')?>", "NotifyWindow", "width=300,height=35,location=no,menubar=no,directories=no,toolbar=no,scrollbars=yes,resizable=yes,status=yes");
		if (NotifyWindow.opener == null)
		{
			NotifyWindow.opener = window;
		}
	}
</SCRIPT>

<?php
		echo '<a href="javascript:opennotifywindow()">' . lang('Open notify window') . '</a>';
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
			if(!isset($app_check[intval($app)]) || !$app_check[intval($app)])
			{
				$app_check[intval($app)] = True;
				$sorted_apps[] = $GLOBALS['phpgw']->applications->id2name(intval($app));
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
	while (list(,$p) = each($GLOBALS['phpgw_info']['user']['apps']))
	{
		$sorted_apps[] = $p['name'];

	}
	//$GLOBALS['phpgw']->hooks->process('home',$sorted_apps);
	
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
	// Display elements, within appropriate table cells
	print '<table border="0" cellpadding="5" cellspacing="0" width="100%">';
	$tropen=0;
	$tdopen=0;
	$lastd = 0;
	$numcols = 2;
	$curcol = 1;
	@reset($sorted_apps);
	foreach($sorted_apps as $appname)
	{
		if(intval($done[$appname])==1 || empty($appname))
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
				$_thisd = intval($GLOBALS['phpgw_info']['user']['preferences'][$appname][$varcheck]);
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
				print '</tr>';
				$tropen = 0;
				//$curcol = 1;
			}
			if(!$tropen)
			{
				print '<tr>';
				$tropen=1;
			}
			$tdwidth = ($thisd==2)?'50':'100';
			$colspan = ($thisd==2)?'1':'2';
			print '<td valign="top" colspan="'.$colspan.'" width="'.$tdwidth.'%">';
			$result = $GLOBALS['phpgw']->hooks->single('home',$appname);
			print '</td>';
			if(($thisd!=2 || ($thisd==2&&$lastd==2)) && $tropen)
			{
				print '</tr>';
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
	print '</table>';

	// Update stored value of order
	//_debug_array($neworder);
	if(count($neworder)>0)//$GLOBALS['portal_order'])
	{
		$GLOBALS['phpgw']->preferences->delete('portal_order');
		@reset($neworder);
		while(list($app_order,$app_name) = each($neworder))
		{
			$app_id = $GLOBALS['phpgw']->applications->name2id($app_name);
			$GLOBALS['phpgw']->preferences->add('portal_order',$app_order,$app_id);
		}
		$GLOBALS['phpgw_info']['user']['preferences'] = $GLOBALS['phpgw']->preferences->save_repository();
	}
	//_debug_array($GLOBALS['phpgw_info']['user']['preferences']);

	//$phpgw->common->debug_phpgw_info();
	//$phpgw->common->debug_list_core_functions();
	$GLOBALS['phpgw']->common->phpgw_footer();
?>
