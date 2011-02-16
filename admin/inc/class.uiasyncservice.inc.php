<?php
/**
 * eGgroupWare admin - Timed Asynchron Services
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package admin
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * Class to admin cron-job like timed calls of eGroupWare methods
 */
class uiasyncservice
{
	var $public_functions = array(
		'index' => True,
	);

	function index()
	{
		if ($GLOBALS['egw']->acl->check('asyncservice_access',1,'admin'))
		{
			$GLOBALS['egw']->redirect_link('/index.php');
		}
		$GLOBALS['egw_info']['flags']['app_header'] = lang('Admin').' - '.lang('Asynchronous timed services');

		$GLOBALS['egw']->js->validate_file('jscode','openwindow','admin');
		$GLOBALS['egw']->common->egw_header();
		echo parse_navbar();

		$async = $GLOBALS['egw']->asyncservice;	// use an own instance, as we might set debug=True

		$async->debug = !!$_POST['debug'];

		$units = array(
			'year'  => lang('Year'),
			'month' => lang('Month'),
			'day'   => lang('Day'),
			'dow'   => lang('Day of week<br>(0-6, 0=Sun)'),
			'hour'  => lang('Hour<br>(0-23)'),
			'min'   => lang('Minute')
		);

		if ($_POST['send'] || $_POST['test'] || $_POST['cancel'] || $_POST['install'] || $_POST['deinstall'] || $_POST['update'] || isset($_POST['asyncservice']))
		{
			$times = array();
			foreach($units as $u => $ulabel)
			{
				if ($_POST[$u] !== '')
				{
					$times[$u] = $_POST[$u];
				}
			}

			if ($_POST['test'])
			{
				if (strpos($GLOBALS['egw_info']['user']['email'],'@') === false)
				{
					echo '<p><b>'.lang("You have no email address for your user set !!!")."</b></p>\n";
				}
				elseif (!$async->set_timer($times,'test','admin.uiasyncservice.test',$GLOBALS['egw_info']['user']['email']))
				{
					echo '<p><b>'.lang("Error setting timer, wrong syntax or maybe there's one already running !!!")."</b></p>\n";
				}
			}
			if ($_POST['cancel'])
			{
				if (!$async->cancel_timer('test'))
				{
					echo '<p><b>'.lang("Error canceling timer, maybe there's none set !!!")."</b></p>\n";
				}
			}
			if ($_POST['install'] || $_POST['deinstall'])
			{
				if (!($install = $async->install($_POST['install'] ? $times : False)))
				{
					echo '<p><b>'.lang('Error: %1 not found or other error !!!',$async->crontab)."</b></p>\n";
				}
				$_POST['asyncservice'] = $_POST['deinstall'] ? 'fallback' : 'crontab';
			}
		}
		else
		{
			$times = array('min' => '*/5');		// set some default
		}
		echo '<form action="'.$GLOBALS['egw']->link('/index.php',array('menuaction'=>'admin.uiasyncservice.index')).'" method="POST">'."\n<p>";
		echo '<div style="text-align: left; margin: 10px;">'."\n";

		$last_run = $async->last_check_run();
		$lr_date = $last_run['end'] ? $GLOBALS['egw']->common->show_date($last_run['end']) : lang('never');
		echo '<p><b>'.lang('Async services last executed').'</b>: '.$lr_date.' ('.$last_run['run_by'].")</p>\n<hr>\n";

		if (isset($_POST['asyncservice']) && $_POST['asyncservice'] != $GLOBALS['egw_info']['server']['asyncservice'])
		{
			$config =& CreateObject('phpgwapi.config','phpgwapi');
			$config->read_repository();
			$config->value('asyncservice',$GLOBALS['egw_info']['server']['asyncservice']=$_POST['asyncservice']);
			$config->save_repository();
			unset($config);
		}
		if (!$async->only_fallback)
		{
			$installed = $async->installed();
			if (is_array($installed) && isset($installed['cronline']))
			{
				$async_use['cron'] = lang('crontab only (recomended)');
			}
		}
		$async_use['']    = lang('fallback (after each pageview)');
		$async_use['off'] = lang('disabled (not recomended)');
		echo '<p><b>'.lang('Run Asynchronous services').'</b>'.
			' <select name="asyncservice" onChange="this.form.submit();">';
		foreach ($async_use as $key => $label)
		{
			$selected = $key == $GLOBALS['egw_info']['server']['asyncservice'] ? ' selected' : '';
			echo "<option value=\"$key\"$selected>$label</option>\n";
		}
		echo "</select>\n";

		if (is_array($installed) && isset($installed['cronline']))
		{
			echo ' &nbsp; <input type="submit" name="deinstall" value="'.lang('Deinstall crontab')."\">\n";
		}
		echo "</p>\n";

		if ($async->only_fallback)
		{
			echo '<p>'.lang('Under windows you need to install the asyncservice %1manually%2 or use the fallback mode. Fallback means the jobs get only checked after each page-view !!!','<a href="http://www.egroupware.org/wiki/TimedAsyncServicesWindows" target="_blank">','</a>')."</p>\n";
		}
		else
		{
			echo '<p>'.lang('Installed crontab').": \n";

			if (is_array($installed) && isset($installed['cronline']))
			{
				echo "$installed[cronline]</p>";
			}
			elseif ($installed === 0)
			{
				echo '<b>'.lang('%1 not found or not executable !!!',$async->crontab)."</b></p>\n";
			}
			else
			{
				echo '<b>'.lang('asyncservices not yet installed or other error (%1) !!!',$installed['error'])."</b></p>\n";
			}
			echo '<p><input type="submit" name="install" value="'.lang('Install crontab')."\">\n".
				lang("for the times below (empty values count as '*', all empty = every minute)")."</p>\n";
		}

		echo "<hr><table border=0><tr>\n";
		foreach ($units as $u => $ulabel)
		{
			echo " <td>$ulabel</td><td><input name=\"$u\" value=\"$times[$u]\" size=5> &nbsp; </td>\n";
		}
		echo "</tr><tr>\n <td colspan=4>\n";
		echo ' <input type="submit" name="send" value="'.lang('Calculate next run').'"></td>'."\n";
		echo ' <td colspan="8"><input type="checkbox" name="debug" value="1"'.($_POST['debug'] ? ' checked' : '')."> \n".
			lang('Enable debug-messages')."</td>\n</tr></table>\n";

		if ($_POST['send'])
		{
			$next = $async->next_run($times,True);

			echo "<p>asyncservice::next_run(";print_r($times);echo")=".($next === False ? 'False':"'$next'=".$GLOBALS['egw']->common->show_date($next))."</p>\n";
		}
		echo '<hr><p><input type="submit" name="cancel" value="'.lang('Cancel TestJob!')."\"> &nbsp;\n";
		echo '<input type="submit" name="test" value="'.lang('Start TestJob!')."\">\n";
		echo lang('for the times above')."</p>\n";
		echo '<p>'.lang('The TestJob sends you a mail everytime it is called.')."</p>\n";

		echo '<hr><p><b>'.lang('Jobs').":</b>\n";
		if ($jobs = $async->read('%'))
		{
			echo "<table border=1>\n<tr>\n<th>Id</th><th>".lang('Next run').'</th><th>'.lang('Times').'</th><th>'.lang('Method').'</th><th>'.lang('Data')."</th><th>".lang('LoginID')."</th></tr>\n";
			foreach($jobs as $job)
			{
				echo "<tr>\n<td>$job[id]</td><td>".$GLOBALS['egw']->common->show_date($job['next'])."</td><td>";
				print_r($job['times']);
				echo "</td><td>$job[method]</td><td>";
				print_r($job['data']);
				echo "</td><td align=\"center\">".$GLOBALS['egw']->accounts->id2name($job[account_id])."</td></tr>\n";
			}
			echo "</table>\n";
		}
		else
		{
			echo lang('No jobs in the database !!!')."</p>\n";
		}
		echo '<p><input type="submit" name="update" value="'.lang('Update').'"></p>'."\n";
		echo "</form>\n";

	}

	function test($to)
	{
		$returncode = $GLOBALS['egw']->send->msg('email',$to,$subject='Asynchronous timed services','Greetings from cron ;-)');

		if (!$returncode)	// not nice, but better than failing silently
		{
			echo "<p>bocalendar::send_update: sending message to '$to' subject='$subject' failed !!!<br>\n";
			echo $GLOBALS['egw']->send->err['desc']."</p>\n";
		}
		//print_r($GLOBALS['egw_info']['user']);
	}
}
