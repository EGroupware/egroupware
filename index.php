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

	if (!is_file('header.inc.php'))
	{
		echo '<center>It appears that phpGroupWare is not setup yet, please click <a href="setup/index.php">'
			. 'here</a>.</center>';
		exit;
	}

	if (!isset($sessionid) || !$sessionid)
	{
		Header('Location: login.php');
		exit;
	}

	$phpgw_info['flags'] = array(
		'noheader'                => True,
		'nonavbar'                => True,
		'currentapp'              => 'home',
		'enable_network_class'    => True,
		'enable_contacts_class'   => True,
		'enable_nextmatchs_class' => True
	);
	include('header.inc.php');
	// Note: I need to add checks to make sure these apps are installed.

	if ($phpgw_forward)
	{
		// Why again?
		if ($phpgw_forward)
		{
			while (list($name,$value) = each($HTTP_GET_VARS))
			{
				if (ereg('phpgw_',$name))
				{
					$extra_vars .= '&' . $name . '=' . urlencode($value);
				}
			}
		}
		$phpgw->redirect($phpgw->link($phpgw_forward,$extra_vars));
	}

	if (($phpgw_info['user']['preferences']['common']['useframes'] &&
		$phpgw_info['server']['useframes'] == 'allowed') ||
		($phpgw_info['server']['useframes'] == 'always'))
		{
			if ($cd == 'yes')
			{
				if (! $navbarframe && ! $framebody)
				{
					$tpl = new Template($phpgw_info['server']['template_dir']);
					$tpl->set_file(array(
						'frames'       => 'frames.tpl',
						'frame_body'   => 'frames_body.tpl',
						'frame_navbar' => 'frames_navbar.tpl'
					));
					$tpl->set_var('navbar_link',$phpgw->link('index.php','navbarframe=True&cd=yes'));
					if ($forward)
					{
						$tpl->set_var('body_link',$phpgw->link($forward));
					}
					else
					{
						$tpl->set_var('body_link',$phpgw->link('index.php','framebody=True&cd=yes'));
					}

					if ($phpgw_info['user']['preferences']['common']['frame_navbar_location'] == 'bottom')
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
					$phpgw->common->phpgw_header();
					echo parse_navbar();
				}
			}
		}
		elseif ($cd=='yes' && $phpgw_info['user']['preferences']['common']['default_app']
			&& $phpgw_info['user']['apps'][$phpgw_info['user']['preferences']['common']['default_app']])
		{
			$phpgw->redirect($phpgw->link('/' . $phpgw_info['user']['preferences']['common']['default_app'] . '/' . 'index.php'));
			$phpgw->common->phpgw_exit();
		}
		else
		{
			$phpgw->common->phpgw_header();
			echo parse_navbar();
		}

		// $phpgw->hooks->proccess("location","mainscreen");
		// $phpgw->preferences->read_preferences("addressbook");
		// $phpgw->preferences->read_preferences("email");
		// $phpgw->preferences->read_preferences("calendar");
		// $phpgw->preferences->read_preferences("stocks");

		$phpgw->db->query("select app_version from phpgw_applications where app_name='phpgwapi'",__LINE__,__FILE__);
		if($phpgw->db->next_record())
		{
			$apiversion = $phpgw->db->f('app_version');
		}
		else
		{
			$phpgw->db->query("select app_version from phpgw_applications where app_name='admin'",__LINE__,__FILE__);
			$phpgw->db->next_record();
			$apiversion = $phpgw->db->f('app_version');
		}

		if ($phpgw_info['server']['versions']['phpgwapi'] > $apiversion)
		{
			echo '<p><b>' . lang('You are running a newer version of phpGroupWare than your database is setup for') . '.'
				. '<br>' . lang('It is recommended that you run setup to upgrade your tables to the current version') . '.'
				. '</b>';
		}

		$phpgw->translation->add_app('mainscreen');
		if (lang('mainscreen_message') != 'mainscreen_message*')
		{
			echo '<center>' . stripslashes(lang('mainscreen_message')) . '</center>';
		}

		if ((isset($phpgw_info['user']['apps']['admin']) &&
			$phpgw_info['user']['apps']['admin']) && 
			(isset($phpgw_info['server']['checkfornewversion']) &&
			$phpgw_info['server']['checkfornewversion']))
		{
			$phpgw->network->set_addcrlf(False);
			$lines = $phpgw->network->gethttpsocketfile('http://www.phpgroupware.org/currentversion');
			for ($i=0; $i<count($lines); $i++)
			{
				if (ereg("currentversion",$lines[$i]))
				{
					$line_found = explode(":",chop($lines[$i]));
				}
			}
			if($phpgw->common->cmp_version($phpgw_info['server']['versions']['phpgwapi'],$line_found[1]))
			{
				echo '<p>There is a new version of phpGroupWare available. <a href="'
					. 'http://www.phpgroupware.org">http://www.phpgroupware.org</a>';
			}
		}
?>
<SCRIPT LANGUAGE="JavaScript" TYPE="text/javascript">
	var NotifyWindow;

	function opennotifywindow()
	{
		if (NotifyWindow)
		{
			if (NotifyWindow.closed)
			{
				NotifyWindow.stop();
				NotifyWindow.close();
			}
		}
		NotifyWindow = window.open("<?php echo $phpgw->link('/notify.php')?>", "NotifyWindow", "width=300,height=35,location=no,menubar=no,directories=no,toolbar=no,scrollbars=yes,resizable=yes,status=yes");
		if (NotifyWindow.opener == null)
		{
			NotifyWindow.opener = window;
		}
	}
</SCRIPT>

<?php
	echo '<p><table border="0" width="100%" align="center">';
	//Uncomment the next line to enable the notify window.  It will not work until a notifywindow app is added.
	//echo '<a href="javascript:opennotifywindow()">Open notify window</a>';

	$phpgw->common->hook('',array('email','calendar','news','addressbook'));

	//$phpgw->common->debug_phpgw_info();
	//$phpgw->common->debug_list_core_functions();
?>
<TR><TD></TD></TR>
</TABLE>
<?php
	$phpgw->common->phpgw_footer();
?>
