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

	$phpgw_info = array();
	if (!is_file('header.inc.php'))
	{
		echo '<center>It appears that phpGroupWare is not setup yet, please click <a href="setup/index.php">'
			. 'here</a>.</center>';
		exit;
	}

	$GLOBALS['sessionid'] = $GLOBALS['HTTP_GET_VARS']['sessionid'] ? $GLOBALS['HTTP_GET_VARS']['sessionid'] : $GLOBALS['HTTP_COOKIE_VARS']['sessionid'];
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

	if ($GLOBALS['phpgw_forward'])
	{
		while (list($name,$value) = each($GLOBALS['HTTP_GET_VARS']))
		{
			if (ereg('phpgw_',$name))
			{
				$extra_vars .= '&' . $name . '=' . urlencode($value);
			}
		}
		$GLOBALS['phpgw']->redirect($GLOBALS['phpgw']->link($GLOBALS['phpgw_forward'],$extra_vars));
	}

	if ($GLOBALS['phpgw_info']['server']['force_default_app'] && $GLOBALS['phpgw_info']['server']['force_default_app'] != 'user_choice')
	{
		$GLOBALS['phpgw_info']['user']['preferences']['common']['default_app'] = $GLOBALS['phpgw_info']['server']['force_default_app'];
	}

		if ($GLOBALS['HTTP_GET_VARS']['cd']=='yes' && $GLOBALS['phpgw_info']['user']['preferences']['common']['default_app']
			&& $GLOBALS['phpgw_info']['user']['apps'][$GLOBALS['phpgw_info']['user']['preferences']['common']['default_app']])
		{
			$GLOBALS['phpgw']->redirect($GLOBALS['phpgw']->link('/' . $GLOBALS['phpgw_info']['user']['preferences']['common']['default_app'] . '/' . 'index.php'));
		}

		$GLOBALS['phpgw']->translation->add_app('mainscreen');
		if (lang('mainscreen_message') != 'mainscreen_message'.lang_char())
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
				if(@ereg('currentversion',$lines[$i]))
				{
					$line_found = explode(':',chop($lines[$i]));
				}
			}
			if($GLOBALS['phpgw']->common->cmp_version($GLOBALS['phpgw_info']['server']['versions']['phpgwapi'],$line_found[1]))
			{
				$message = '<p>There is a new version of phpGroupWare available. <a href="'
					. 'http://www.phpgroupware.org">http://www.phpgroupware.org</a>';
				$GLOBALS['phpgw_info']['flags']['msgbox_data'][$message]=True;
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
					$_app_title    = $setup_info[$_app_name]['title'];
					unset($setup_info);

					if($GLOBALS['phpgw']->common->cmp_version_long($_db_version,$_file_version))
					{
						$_found = True;
						$_app_string .= '<br>' . lang($_app_title);
					}
					unset($_file_version);
					unset($_app_title);
				}
				unset($_db_version);
				unset($_versionfile);
			}
			if($_found)
			{
				$message = '<br>' . lang('The following applications require upgrades') . ':' . "\n";
				$message .= $_app_string . "\n";
				$message .= '<br>' . lang('Please run setup to become current') . '.' . "\n";

				$GLOBALS['phpgw_info']['flags']['msgbox_data'][$message]=False;
				unset($message);
				unset($_app_string);
			}
		}

	if (isset($GLOBALS['phpgw_info']['user']['apps']['notifywindow']) &&
		$GLOBALS['phpgw_info']['user']['apps']['notifywindow'])
	{
/* need to figure out how to implement this properly
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

		echo '<a href="javascript:opennotifywindow()">' . lang('Open notify window') . '</a>';
*/
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
			'email',
			'calendar',
			'news',
			'addressbook',
			'squirrelmail'
		);
	}
	@reset($sorted_apps);
	$GLOBALS['phpgw']->hooks->process('home',$sorted_apps);

	if($GLOBALS['portal_order'])
	{
		$GLOBALS['phpgw']->preferences->delete('portal_order');
		@reset($GLOBALS['portal_order']);
		while(list($app_order,$app_id) = each($GLOBALS['portal_order']))
		{
			$GLOBALS['phpgw']->preferences->add('portal_order',$app_order,$app_id);
		}
		$GLOBALS['phpgw']->preferences->save_repository();
	}
	$GLOBALS['phpgw']->common->phpgw_footer();
?>
