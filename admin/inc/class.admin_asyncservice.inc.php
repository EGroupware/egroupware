<?php
/**
 * EGgroupware admin - Timed Asynchron Services
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package admin
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

use EGroupware\Api;
use EGroupware\Api\Egw;

/**
 * Class to admin cron-job like timed calls of eGroupWare methods
 */
class admin_asyncservice
{
	var $public_functions = array(
		'index' => True,
	);

	function index()
	{
		if ($GLOBALS['egw']->acl->check('asyncservice_acc',1,'admin'))
		{
			Egw::redirect_link('/index.php');
		}
		$GLOBALS['egw_info']['flags']['app_header'] = lang('Admin').' - '.lang('Asynchronous timed services');

		Api\Framework::bodyClass('scrollVertical');
		echo $GLOBALS['egw']->framework->header();

		$async = new Api\Asyncservice();	// use an own instance, as we might set debug=True

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
				if (strpos($GLOBALS['egw_info']['user']['account_email'],'@') === false)
				{
					echo '<p><b>'.htmlspecialchars(lang("You have no email address for your user set !!!"))."</b></p>\n";
				}
				elseif (!$async->set_timer($times,'test','admin.admin_asyncservice.test',$GLOBALS['egw_info']['user']['account_email']))
				{
					echo '<p><b>'.htmlspecialchars(lang("Error setting timer, wrong syntax or maybe there's one already running !!!"))."</b></p>\n";
				}
			}
			if ($_POST['cancel'])
			{
				if (!$async->cancel_timer('test'))
				{
					echo '<p><b>'.htmlspecialchars(lang("Error canceling timer, maybe there's none set !!!"))."</b></p>\n";
				}
			}
			if ($_POST['install'] || $_POST['deinstall'])
			{
				if (!($install = $async->install($_POST['install'] ? $times : False)))
				{
					echo '<p><b>'.htmlspecialchars(lang('Error: %1 not found or other error !!!',$async->crontab))."</b></p>\n";
				}
				$_POST['asyncservice'] = $_POST['deinstall'] ? 'fallback' : 'crontab';
			}
		}
		else
		{
			$times = array('min' => '*/5');		// set some default
		}
		echo '<form action="'.$GLOBALS['egw']->link('/index.php',array('menuaction'=>'admin.admin_asyncservice.index')).'" method="POST">'."\n<p>";
		echo '<div style="text-align: left; margin: 10px;">'."\n";

		$last_run = $async->last_check_run();
		$lr_date = $last_run['end'] ? Api\DateTime::server2user($last_run['end'],'') : lang('never');
		echo '<p><b>'. htmlspecialchars(lang('Async services last executed')).'</b>: '.
			$lr_date.' ('.htmlspecialchars($last_run['run_by']).")</p>\n<hr>\n";

		if (isset($_POST['asyncservice']) && $_POST['asyncservice'] != $GLOBALS['egw_info']['server']['asyncservice'])
		{
			Api\Config::save_value('asyncservice', $GLOBALS['egw_info']['server']['asyncservice']=$_POST['asyncservice'], 'phpgwapi');
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
		echo '<p><b>'.htmlspecialchars(lang('Run Asynchronous services')).'</b>'.
			' <select name="asyncservice" onChange="this.form.submit();">';
		foreach ($async_use as $key => $label)
		{
			$selected = $key == $GLOBALS['egw_info']['server']['asyncservice'] ? ' selected' : '';
			echo "<option value=\"$key\"$selected>".htmlspecialchars($label)."</option>\n";
		}
		echo "</select>\n";

		if (is_array($installed) && isset($installed['cronline']))
		{
			echo ' &nbsp; <input type="submit" name="deinstall" value="'.htmlspecialchars(lang('Deinstall crontab'))."\">\n";
		}
		echo "</p>\n";

		if ($async->only_fallback)
		{
			echo '<p>'.htmlspecialchars(lang('Under windows you need to install the asyncservice %1manually%2 or use the fallback mode. Fallback means the jobs get only checked after each page-view !!!','<a href="http://www.egroupware.org/wiki/TimedAsyncServicesWindows" target="_blank">','</a>'))."</p>\n";
		}
		else
		{
			echo '<p>'.htmlspecialchars(lang('Installed crontab')).": \n";

			if (is_array($installed) && isset($installed['cronline']))
			{
				echo "$installed[cronline]</p>";
			}
			elseif ($installed === 0)
			{
				echo '<b>'.htmlspecialchars(lang('%1 not found or not executable !!!',$async->crontab))."</b></p>\n";
			}
			else
			{
				echo '<b>'.htmlspecialchars(lang('asyncservices not yet installed or other error (%1) !!!',$installed['error']))."</b></p>\n";
			}
			echo '<p><input type="submit" name="install" value="'.htmlspecialchars(lang('Install crontab'))."\">\n".
				htmlspecialchars(lang("for the times below (empty values count as '*', all empty = every minute)"))."</p>\n";
		}

		echo "<hr><table border=0><tr>\n";
		foreach ($units as $u => $ulabel)
		{
			echo " <td>$ulabel</td><td><input name=\"$u\" value=\"".htmlspecialchars($times[$u])."\" size=5> &nbsp; </td>\n";
		}
		echo "</tr><tr>\n <td colspan=4>\n";
		echo ' <input type="submit" name="send" value="'.htmlspecialchars(lang('Calculate next run')).'"></td>'."\n";
		echo ' <td colspan="8"><input type="checkbox" name="debug" value="1"'.($_POST['debug'] ? ' checked' : '')."> \n".
			htmlspecialchars(lang('Enable debug-messages'))."</td>\n</tr></table>\n";

		if ($_POST['send'])
		{
			$next = $async->next_run($times,True);

			echo "<p>asyncservice::next_run(". htmlspecialchars(json_encode($times, JSON_UNESCAPED_SLASHES)).")=".($next === False ? 'False':"$next=".Api\DateTime::server2user($next,''))."</p>\n";
		}
		echo '<hr><p><input type="submit" name="cancel" value="'.htmlspecialchars(lang('Cancel TestJob!'))."\"> &nbsp;\n";
		echo '<input type="submit" name="test" value="'.htmlspecialchars(lang('Start TestJob!'))."\">\n";
		echo lang('for the times above')."</p>\n";
		echo '<p>'.lang('The TestJob sends you a mail everytime it is called.')."</p>\n";

		echo '<hr><p><b>'.lang('Jobs').":</b>\n";
		if (($jobs = $async->read('%')))
		{
			echo "<table border=1>\n<tr>\n<th>Id</th><th style='width:18ex;'>".lang('Next run').'</th><th>'.lang('Times').'</th><th>'.lang('Method').'</th><th>'.lang('Data')."</th><th>".lang('LoginID')."</th></tr>\n";
			foreach($jobs as $job)
			{
				echo "<tr>\n<td>$job[id]</td><td>".Api\DateTime::server2user($job['next'],'')."</td>\n";
				echo "<td>".htmlspecialchars(json_encode($job['times'], JSON_UNESCAPED_SLASHES))."</td>\n";
				echo "</td><td>".htmlspecialchars(str_replace('EGroupware\\', '', $job['method']))."</td>\n<td";
				$data = is_array($job['data']) ? json_encode($job['data'], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) : $job['data'];
				if (strlen($data) >= 64)
				{
					echo ' title="'.htmlspecialchars(json_encode($job['data'], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)).'"';
					$data = substr($data, 0, 60).'...';
				}
				echo ">". htmlspecialchars($data)."</td>\n";
				echo "<td align=\"center\">".htmlspecialchars($GLOBALS['egw']->accounts->id2name($job['account_id']))."</td></tr>\n";
			}
			echo "</table>\n";
		}
		else
		{
			echo lang('No jobs in the database !!!')."</p>\n";
		}
		echo '<p><input type="submit" name="update" value="'.htmlspecialchars(lang('Update')).'"></p>'."\n";
		echo "</form>\n";
		echo $GLOBALS['egw']->framework->footer();
	}

	/**
	 * Callback for test-job
	 *
	 * @param string $to email address to send mail to
	 */
	function test($to)
	{
		try {
			$mail = new Api\Mailer();
			$mail->setBody('Greetings from cron ;-)');
			$mail->addHeader('Subject', 'Asynchronous timed services');
			$mail->addAddress($to);
			$mail->send();
		}
		catch (Exception $e) {
			_egw_log_exception($e);
		}
	}
}