<?php
/**
 * EGgroupware admin - submit EGroupware usage statistic
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package admin
 * @copyright (c) 2009-18 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

use EGroupware\Api;
use EGroupware\Api\Egw;
use EGroupware\Api\Etemplate;

/**
 * Submit statistical data to egroupware.org
 *
 * Usage statistic is disabled, as there is no more backend currently.
 */
class admin_statistics
{
	const CONFIG_APP = 'admin';
	const CONFIG_LAST_SUBMIT = 'last_statistics_submit';
	const CONFIG_POSTPONE_SUBMIT = 'postpone_statistics_submit';
	const CONFIG_SUBMIT_ID = 'statistics_submit_id';
	const CONFIG_COUNTRY = 'country_submit';
	const CONFIG_USAGE_TYPE = 'usage_type_submit';
	const CONFIG_INSTALL_TYPE = 'install_type_submit';

	const SUBMIT_URL = 'https://www.egroupware.org/usage-statistic';
	const STATISTIC_URL = 'https://www.egroupware.org/usage-statistic';

	const SUBMISION_RATE = 2592000;	// 30 days

	/**
	 * Which methods of this class can be called as menuation
	 *
	 * @var array
	 */
	public $public_functions = array(
		'submit' => true,
	);

	/**
	 * Display and allow to submit statistical data
	 *
	 * @param array $_content =null
	 */
	public function submit($_content=null)
	{
		if (is_array($_content))
		{
			$config = new Api\Config(self::CONFIG_APP);
			if ($_content['postpone'])
			{
				Api\Config::save_value(self::CONFIG_POSTPONE_SUBMIT,time()+$_content['postpone'],self::CONFIG_APP);
				$what = 'postpone';
			}
			elseif(!$_content['cancel'])
			{
				Api\Config::save_value(self::CONFIG_LAST_SUBMIT,time(),self::CONFIG_APP);
				Api\Config::save_value(self::CONFIG_SUBMIT_ID,empty($_content['submit_id']) ? '***none***' : $_content['submit_id'],self::CONFIG_APP);
				Api\Config::save_value(self::CONFIG_COUNTRY,empty($_content['country']) ? '***multinational***' : $_content['country'],self::CONFIG_APP);
				Api\Config::save_value(self::CONFIG_USAGE_TYPE,$_content['usage_type'],self::CONFIG_APP);
				Api\Config::save_value(self::CONFIG_INSTALL_TYPE,$_content['install_type'],self::CONFIG_APP);
				Api\Config::save_value(self::CONFIG_POSTPONE_SUBMIT,null,self::CONFIG_APP);	// remove evtl. postpone time
				$what = 'submitted';
			}
			Egw::redirect_link('/admin/index.php','ajax=true&statistics='.($what ? $what : 'canceled'),'admin');
		}
		$sel_options['usage_type'] = array(
			'commercial'   => lang('Commercial: all sorts of companies'),
			'governmental' => lang('Governmental: incl. state or municipal authorities or services'),
			'educational' => lang('Educational: Universities, Schools, ...'),
			'non-profit'  => lang('Non profit: Clubs, associations, ...'),
			'personal'    => lang('Personal: eg. within a family'),
			'other'       => lang('Other'),
		);
		$sel_options['install_type'] = array(
			'docker'  => lang('Docker'),
			'package' => lang('RPM or Debian package'),
			'git'     => lang('Git clone'),
			'archive' => lang('Archive: zip or tar'),
			'other'   => lang('Other'),
		);
		$sel_options['postpone'] = array(
			//10 => '10 secs',
			3600 => lang('one hour'),
			2*3600 => lang('two hours'),
			24*3600 => lang('one day'),
			2*24*3600 => lang('two days'),
			7*24*3600 => lang('one week'),
			14*24*3600 => lang('two weeks'),
			30*24*3600 => lang('one month'),
			60*24*3600 => lang('two months'),
		);
		$config = Api\Config::read(self::CONFIG_APP);
		//_debug_array($config);
		$content = array_merge(self::gather_data(),array(
			'statistic_url' => self::STATISTIC_URL,
			'submit_host' => parse_url(self::SUBMIT_URL,PHP_URL_HOST),
			'submit_url'  => self::SUBMIT_URL,
			'last_submitted' => $config[self::CONFIG_LAST_SUBMIT],
		));
		//_debug_array($content);

		// show previous submit ID
		if ($config['statistics_submit_id'])
		{
			$content['submit_id'] = $config['statistics_submit_id'] == '***none***' ? '' : $config['statistics_submit_id'];
		}
		// show previous Api\Country
		if ($config[self::CONFIG_COUNTRY])
		{
			$content['country'] = $config[self::CONFIG_COUNTRY] == '***multinational***' ? '' : $config[self::CONFIG_COUNTRY];
		}
		// show previous usage_type
		if ($config[self::CONFIG_USAGE_TYPE])
		{
			$content['usage_type'] = $config[self::CONFIG_USAGE_TYPE];
		}
		// check if we detected svn or rpm/deb packages --> readonly
		if ($content['install_type'] && isset($sel_options['install_type'][$content['install_type']]))
		{
			$sel_options['install_type'] = array($content['install_type'] => $sel_options['install_type'][$content['install_type']]);
		}
		// else default to previous type
		elseif($config[self::CONFIG_INSTALL_TYPE])
		{
			$content['install_type'] = $config[self::CONFIG_INSTALL_TYPE];
		}
		// check if we are due for a new submission
		if (!isset($config[self::CONFIG_LAST_SUBMIT]) || $config[self::CONFIG_LAST_SUBMIT ] <= time()-self::SUBMISION_RATE)
		{
			// clear etemplate_exec_id and replace form.action, before submitting the form
			$content['onclick'] = "return app.admin.submit_statistic(this.form,'$content[submit_url]');";
		}
		else	// we are not due --> tell it the user
		{
			$readonlys['submit'] = $readonlys['postpone'] = true;
			$content['msg'] = lang('Your last submission was less then %1 days ago!',
				ceil((time()-$config[self::CONFIG_LAST_SUBMIT])/24/3600));
		}
		$GLOBALS['egw_info']['flags']['app_header'] = lang('Submit statistic information');
		$tmpl = new Etemplate('admin.statistics');
		$tmpl->exec('admin.admin_statistics.submit',$content,$sel_options,$readonlys);
	}

	/**
	 * Gather statistical data to submit
	 *
	 * @return array key => value pairs
	 */
	protected static function gather_data()
	{
		// submit id is sha1 hash from install_id
		$data['submit_id'] = sha1($GLOBALS['egw_info']['server']['install_id']);

		$data['country'] = $GLOBALS['egw_info']['user']['preferences']['common']['country'];

		// maintenance release (incl. EPL)
		$data['version'] = $GLOBALS['egw_info']['server']['versions']['maintenance_release'];

		// sessions in the last 30 days
		$data['sessions'] = $GLOBALS['egw']->db->query('SELECT COUNT(*) FROM egw_access_log WHERE li > '.(time()-30*24*3600))->fetchColumn();

		// total accounts from accounts table or ldap
		$GLOBALS['egw']->accounts->search(array(
			'type' => 'accounts',
			'start' => 0,
		));
		$data['users'] = $GLOBALS['egw']->accounts->total;

		$data['php'] = PHP_VERSION.': '.PHP_SAPI;
		$data['os'] = PHP_OS;
		// @ required to get ride of warning, if files are outside of open_basedir
		$matches = null;
		if (@file_exists($file = '/etc/lsb-release') && preg_match('/^DISTRIB_DESCRIPTION="?([^"]+)"?/mi', file_get_contents($file), $matches))
		{
			$data['os'] .= ': '.$matches[1];
		}
		elseif (@file_exists($file = '/etc/SuSE-release') || @file_exists($file = '/etc/redhat-release') || @file_exists($file = '/etc/debian_version'))
		{
			$data['os'] .= ': '.str_replace(array("\n","\r"),'',implode(',',file($file)));
		}
		if(file_exists(EGW_INCLUDE_ROOT.'/.git'))
		{
			$data['install_type'] = 'git';
		}
		elseif (file_exists('/entrypoint.sh'))
		{
			$data['install_type'] = 'docker';
		}
		elseif(EGW_INCLUDE_ROOT == '/usr/share/egroupware' && PHP_OS == 'Linux' && is_link('/usr/share/egroupware/header.inc.php'))
		{
			$data['install_type'] = 'package';
		}
		foreach(array_keys($GLOBALS['egw_info']['apps']) as $app)
		{
			if (in_array($app,array(
				'admin','phpgwapi','api','sambaadmin','developer_tools',
				'home','preferences','etemplate','registration','manual',
			)))
			{
				continue;	// --> ignore to not submit too much
			}
			if (($users = self::gather_app_users($app)))	// ignore apps noone is allowed to run
			{
				$data['apps'][$app] = $app.':'.round(100.0*$users/$data['users']).'%';
				if (($entries = self::gather_app_entries($app)))
				{
					$data['apps'][$app] .= ':'.$entries;
				}
			}
		}
		ksort($data['apps']);
		$data['apps'] = implode("\n",$data['apps']);

		return $data;
	}

	/**
	 * Get percentage of users allowed to use an application
	 *
	 * @param string $app
	 * @return int number of users allowed to run application
	 */
	static function gather_app_users($app)
	{
		$users = array();
		if (($access = $GLOBALS['egw']->acl->get_ids_for_location('run',1,$app)))
		{
			foreach($access as $uid)
			{
				if ($uid > 0)
				{
					$users[] = $uid;
				}
				elseif (($members = $GLOBALS['egw']->accounts->members($uid,true)))
				{
					$users = array_merge($users,$members);
				}
			}
			$users = array_unique($users);
		}
		return count($users);
	}

	/**
	 * Get percentage of users allowed to use an application
	 *
	 * @param string $app
	 * @return int
	 */
	static function gather_app_entries($app)
	{
		// main table for each application
		static $app2table = array(
			'addressbook' => 'egw_addressbook',
			'bookmarks'   => 'egw_bookmarks',
			'calendar'    => 'egw_cal_dates',
			'infolog'     => 'egw_infolog',
			'filemanager' => 'egw_sqlfs',
			'gallery'     => 'g2_Item',
			'news_admin'  => 'egw_news WHERE news_submittedby > 0',	// exclude imported rss feeds
			'polls'       => 'egw_polls',
			'projectmanager' => 'egw_pm_projects',
			'phpbrain'    => 'egw_kb_articles',
			'resources'   => 'egw_resources',
			'sitemgr'     => 'egw_sitemgr_pages',
			'syncml'      => 'egw_syncmlsummary',
			'timesheet'   => 'egw_timesheet',
			'tracker'     => 'egw_tracker',
			'wiki'        => 'egw_wiki_pages',
			'mydms'       => 'phpgw_mydms_Documents',
		);
		if (($table = $app2table[$app]))
		{
			try {
				$entries = (int)$GLOBALS['egw']->db->query('SELECT COUNT(*) FROM '.$table)->fetchColumn();
				//echo "$app ($table): $entries<br />\n";
			}
			catch(Api\Db\Exception $e) {
				unset($e);
				$entries = null;
			}
		}
		return $entries;
	}

	/**
	 * Check if next submission is due, in which case we call submit and NOT return to the admin hook
	 *
	 * @param boolean $redirect should we redirect or return true
	 * @return boolean true if statistic submission is due
	 */
	public static function check($redirect=true)
	{
		$config = Api\Config::read(self::CONFIG_APP);

		if (isset($config[self::CONFIG_POSTPONE_SUBMIT]) && $config[self::CONFIG_POSTPONE_SUBMIT] > time() ||
			isset($config[self::CONFIG_LAST_SUBMIT ]) && $config[self::CONFIG_LAST_SUBMIT ] > time()-self::SUBMISION_RATE)
		{
			return false;
		}
		if (!$redirect) return true;

		//die('Due for new statistics submission: last_submit='.$config[self::CONFIG_LAST_SUBMIT ].', postpone='.$config[self::CONFIG_POSTPONE_SUBMIT].', '.function_backtrace());
		Egw::redirect_link('/index.php',array(
			'menuaction' => 'admin.admin_ui.index',
			'ajax' => 'true',
			'load' => 'admin.admin_statistics.submit',
		));
	}
}