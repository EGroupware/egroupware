<?php
/**
 * EGroupware Setup - Install, update & remove single apps
 *
 * @link http://www.egroupware.org
 * @package setup
 * @author Miles Lott <milos@groupwhere.org>
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

use EGroupware\Api;
use EGroupware\Api\Framework;

$DEBUG = !empty($_REQUEST['debug']);
/*
 TODO: We allow a user to hose their setup here, need to make use
 of dependencies so they are warned that they are pulling the rug
 out from under other apps.  e.g. if they select to uninstall the api
 this will happen without further warning.
*/

include ('./inc/functions.inc.php');

@set_time_limit(0);

// Check header and authentication
if (!$GLOBALS['egw_setup']->auth('Config'))
{
	Header('Location: index.php');
	exit;
}
// Does not return unless user is authorized

$tpl_root = $GLOBALS['egw_setup']->html->setup_tpl_dir('setup');
$setup_tpl = new Framework\Template($tpl_root);
$setup_tpl->set_file(array(
	'T_head' => 'head.tpl',
	'T_footer' => 'footer.tpl',
	'T_alert_msg' => 'msg_alert_msg.tpl',
	'T_login_main' => 'login_main.tpl',
	'T_login_stage_header' => 'login_stage_header.tpl',
	'T_setup_main' => 'applications.tpl'
));
$setup_tpl->set_var('hidden_vars', Api\Html::input_hidden('csrf_token', Api\Csrf::token(__FILE__)));

// check CSRF token for POST requests with any content (setup uses empty POST to call it's modules!)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $_POST)
{
	Api\Csrf::validate($_POST['csrf_token'], __FILE__);
}

$setup_tpl->set_block('T_login_stage_header','B_multi_domain','V_multi_domain');
$setup_tpl->set_block('T_login_stage_header','B_single_domain','V_single_domain');
$setup_tpl->set_block('T_setup_main','header','header');
$setup_tpl->set_block('T_setup_main','app_header','app_header');
$setup_tpl->set_block('T_setup_main','apps','apps');
$setup_tpl->set_block('T_setup_main','detail','detail');
$setup_tpl->set_block('T_setup_main','table','table');
$setup_tpl->set_block('T_setup_main','hook','hook');
$setup_tpl->set_block('T_setup_main','dep','dep');
$setup_tpl->set_block('T_setup_main','app_footer','app_footer');
$setup_tpl->set_block('T_setup_main','submit','submit');
$setup_tpl->set_block('T_setup_main','footer','footer');

$bgcolor = array('#DDDDDD','#EEEEEE');

function parsedep($depends,$main=True)
{
	$depstring = '(';
	foreach($depends as $b)
	{
		foreach($b as $c => $d)
		{
			if (is_array($d))
			{
				$depstring .= $c . ': ' .implode(',',$d) . '; ';
				$depver[] = $d;
			}
			else
			{
				$depstring .= $c . ': ' . $d . '; ';
				$depapp[] = $d;
			}
		}
	}
	$depstring .= ')';
	if ($main)
	{
		return $depstring;
	}
	else
	{
		return array($depapp,$depver);
	}
}

$GLOBALS['egw_setup']->loaddb();
$GLOBALS['egw_info']['setup']['stage']['db'] = $GLOBALS['egw_setup']->detection->check_db();

$setup_info = $GLOBALS['egw_setup']->detection->check_depends(
	$GLOBALS['egw_setup']->detection->compare_versions(
		$GLOBALS['egw_setup']->detection->get_db_versions(
			$GLOBALS['egw_setup']->detection->get_versions())));

@ksort($setup_info);

if(@$_POST['cancel'])
{
	Header("Location: index.php");
	exit;
}

if(@$_POST['submit'])
{
	$GLOBALS['egw_setup']->html->show_header(lang('Application Management'),False,'config',$GLOBALS['egw_setup']->ConfigDomain . '(' . $GLOBALS['egw_domain'][$GLOBALS['egw_setup']->ConfigDomain]['db_type'] . ')');
	$setup_tpl->set_var('description',lang('App install/remove/upgrade') . ':');
	$setup_tpl->pparse('out','header');

	$appname = $_POST['appname'];
	$remove  = $_POST['remove'];
	$install = $_POST['install'];
	$upgrade = $_POST['upgrade'];

	$register_hooks = false;

	if(!empty($remove) && is_array($remove))
	{
		$register_hooks = $GLOBALS['egw_setup']->process->remove(array_keys($remove), $setup_info, $DEBUG) > 0;
	}

	if(!empty($install) && is_array($install))
	{
		foreach($install as $appname => $key)
		{
			$app_title = $setup_info[$appname]['title'] ? $setup_info[$appname]['title'] : $setup_info[$appname]['name'];
			$terror = array();
			$terror[$appname] = $setup_info[$appname];

			if ($setup_info[$appname]['tables'])
			{
				$terror_c = $GLOBALS['egw_setup']->process->current($terror, $DEBUG);
				$terror = $GLOBALS['egw_setup']->process->default_records($terror_c, $DEBUG);
				echo '<br />' . $app_title . ' '
					. lang('tables installed, unless there are errors printed above') . '.';
			}
			else
			{
				// check default_records for apps without tables, they might need some initial work too
				$terror = $GLOBALS['egw_setup']->process->default_records($terror,$DEBUG);
				if ($GLOBALS['egw_setup']->app_registered($setup_info[$appname]['name']))
				{
					$GLOBALS['egw_setup']->update_app($setup_info[$appname]['name']);
				}
				else
				{
					$GLOBALS['egw_setup']->register_app($setup_info[$appname]['name']);
				}
				echo '<br />' . $app_title . ' ' . lang('registered') . '.';

				if ($setup_info[$appname]['hooks'])
				{
					$register_hooks = true;
				}
			}
		}
	}

	if(!empty($upgrade) && is_array($upgrade))
	{
		foreach($upgrade as $appname => $key)
		{
			$app_title = $setup_info[$appname]['title'] ? $setup_info[$appname]['title'] : $setup_info[$appname]['name'];
			$terror = array();
			$terror[$appname] = $setup_info[$appname];

			$GLOBALS['egw_setup']->process->upgrade($terror,$DEBUG);
			if ($setup_info[$appname]['tables'])
			{
				echo '<br />' . $app_title . ' ' . lang('tables upgraded') . '.';
				// The process_upgrade() function also handles registration
			}
			else
			{
				echo '<br />' . $app_title . ' ' . lang('upgraded') . '.';
			}
			$register_hooks = true;
		}
	}

	if ($register_hooks)
	{
		Api\Hooks::read(true);
		echo '<br />' . $app_title . ' ' . lang('hooks registered') . '.';
	}

	//$setup_tpl->set_var('goback',
	echo '<br /><a href="applications.php?debug='.$DEBUG.'">' . lang('Go back') . '</a>';
	//$setup_tpl->pparse('out','submit');
	$setup_tpl->pparse('out','footer');
	exit;
}
else
{
	$GLOBALS['egw_setup']->html->show_header(lang('Application Management'),False,'config',$GLOBALS['egw_setup']->ConfigDomain . '(' . $GLOBALS['egw_domain'][$GLOBALS['egw_setup']->ConfigDomain]['db_type'] . ')');
}

if(@$_GET['hooks'])
{
	Api\Cache::flush(Api\Cache::INSTANCE);

	echo lang('Cached cleared') . '<br />';
}
if (!empty($detail = $_GET['detail']))
{
	@ksort($setup_info[$detail]);
	$setup_tpl->set_var('description',lang('App details') . ':');
	$setup_tpl->pparse('out','header');

	$setup_tpl->set_var('name','application');
	$setup_tpl->set_var('details', lang($setup_info[$detail]['title']));
	$setup_tpl->pparse('out','detail');

	$i = 0;
	foreach($setup_info[$detail] as $key => $val)
	{
		switch($key)
		{
			case 'autoinstall':
				$val = json_encode($val);
				break;
			case 'title':
				continue 2;
			case 'tables':
				$tblcnt = count($setup_info[$detail][$key]);
				if(is_array($val))
				{
					$key = '<a href="sqltoarray.php?appname=' . $detail . '&submit=True&apps=True">' . $key . '(' . $tblcnt . ')</a>' . "\n";
					$val = implode(',' . "\n",$val);
				}
				break;
			case 'hooks':
				foreach($val as &$hooks)
				{
					$hooks = (array)$hooks;
				}
				$val = str_replace('EGroupware\\', '', json_encode($val));
				break;
			case 'depends':
				foreach($val as &$dep)
				{
					$dep = $dep['appname'].': '.implode(', ', $dep['versions']);
				}
				$val = implode('; ', $val);
				break;
			case 'check_install':
				$val = json_encode($val, JSON_UNESCAPED_SLASHES);
				break;
			default:
				if (is_array($val)) $val = implode(', ', $val);
				break;
		}
		$setup_tpl->set_var('bg_color', $bgcolor[++$i & 1]);
		$setup_tpl->set_var('name',$key);
		$setup_tpl->set_var('details',$val);
		$setup_tpl->pparse('out','detail');
	}

	echo '<br /><a href="applications.php?debug='.$DEBUG.'">' . lang('Go back') . '</a>';
	$setup_tpl->pparse('out','footer');
	exit;
}
elseif (!empty($resolve = $_GET['resolve']))
{
	$version  = $_GET['version'];
	$notables = $_GET['notables'];
	$setup_tpl->set_var('description',lang('Problem resolution'). ':');
	$setup_tpl->pparse('out','header');
	$app_title = $setup_info[$resolve]['title'] ?: $setup_info[$resolve]['name'];

	if($_GET['post'])
	{
		echo '"' . $app_title . '" ' . lang('may be broken') . ' ';
		echo lang('because an application it depends upon was upgraded');
		echo '<br />';
		echo lang('to a version it does not know about') . '.';
		echo '<br />';
		echo lang('However, the application may still work') . '.';
	}
	elseif($_GET['badinstall'])
	{
		echo '"' . $app_title . '" ' . lang('is broken') . ' ';
		echo lang('because of a failed upgrade or install') . '.';
		echo '<br />';
		echo lang('Some or all of its tables are missing') . '.';
		echo '<br />';
		echo lang('You should either uninstall and then reinstall it, or attempt manual repairs') . '.';
	}
	elseif($_GET['deleted'])
	{
		echo '"' . $app_title . '" ' . lang('is broken') . ' ';
		echo lang('because its sources are missing') . '!';
		echo '<br />';
		echo lang('However the tables are still in the database') . '.';
		echo '<br />';
		echo lang('You should either install the sources or uninstall it, to get rid of the tables') . '.';
	}
	elseif (!$version)
	{
		if($setup_info[$resolve]['enabled'])
		{
			echo '"' . $app_title . '" ' . lang('is broken') . ' ';
		}
		else
		{
			echo '"' . $app_title . '" ' . lang('is disabled') . ' ';
		}

		if (!$notables)
		{
			if($setup_info[$resolve]['status'] == 'D')
			{
				echo lang('because it depends upon') . ':<br />' . "\n";
				list($depapp,$depver) = parsedep($setup_info[$resolve]['depends'],False);
                            $depapp_count = count($depapp);
				for ($i=0; $i<$depapp_count; $i++)
				{
					echo '<br />' . $depapp[$i] . ': ';
					$list = '';
					foreach($depver[$i] as $x => $y)
					{
						$list .= $y . ', ';
					}
					echo substr($list,0,-2)."\n";
				}
				echo '<br /><br />' . lang('The table definition was correct, and the tables were installed') . '.';
			}
			else
			{
				echo lang('because it was manually disabled') . '.';
			}
		}
		elseif($setup_info[$resolve]['enable'] == 2)
		{
			echo lang('because it is not a user application, or access is controlled via acl') . '.';
		}
		elseif($setup_info[$resolve]['enable'] == 0)
		{
			echo lang('because the enable flag for this app is set to 0, or is undefined') . '.';
		}
		else
		{
			echo lang('because it requires manual table installation, <br />or the table definition was incorrect') . ".\n"
				. lang("Please check for sql scripts within the application's directory") . '.';
		}
		echo '<br />' . lang('However, the application is otherwise installed') . '.';
	}
	else
	{
		echo $app_title . ' ' . lang('has a version mismatch') . ' ';
		echo lang('because of a failed upgrade, or the database is newer than the installed version of this app') . '.';
		echo '<br />';
		echo lang('If the application has no defined tables, selecting upgrade should remedy the problem') . '.';
		echo '<br />' . lang('However, the application is otherwise installed') . '.';
	}

	echo '<br /><a href="applications.php?debug='.$DEBUG.'">' . lang('Go back') . '</a>';
	$setup_tpl->pparse('out','footer');
	exit;
}
else
{
	$setup_tpl->set_var('description',lang('Select the desired action(s) from the available choices'));
	$setup_tpl->set_var('action_url','applications.php');
	$setup_tpl->pparse('out','header');

	$setup_tpl->set_var('appdata',lang('Application Data'));
	$setup_tpl->set_var('actions',lang('Actions'));
	$setup_tpl->set_var('app_info',lang('Application Name and Status Information'));
	$setup_tpl->set_var('app_title',lang('Application Title'));
	$setup_tpl->set_var('app_currentver',lang('Current Version'));
	$setup_tpl->set_var('app_version',lang('Available Version'));
	$setup_tpl->set_var('app_install',lang('Install'));
	$setup_tpl->set_var('app_remove',lang('Remove'));
	$setup_tpl->set_var('app_upgrade',lang('Upgrade'));
	$setup_tpl->set_var('app_resolve',lang('Resolve'));
	$setup_tpl->set_var('check','check.png');
	$setup_tpl->set_var('install_all',lang('Install All'));
	$setup_tpl->set_var('upgrade_all',lang('Upgrade All'));
	$setup_tpl->set_var('remove_all',lang('Remove All'));
	$setup_tpl->set_var('lang_debug',lang('enable for extra debug-messages'));
	$setup_tpl->set_var('debug','<input type="checkbox" name="debug" value="True"' .($DEBUG ? ' checked="checked"' : '') . ' />');
	$setup_tpl->set_var('bg_color',$bgcolor[0]);

	$setup_tpl->pparse('out','app_header');

	$i = 0;
	foreach($setup_info as $key => $value)
	{
		if(@$value['name'])
		{
			$i = ($i ? 0 : 1);
			$setup_tpl->set_var('apptitle',$value['title']?$value['title']:lang($value['name']));
			$setup_tpl->set_var('currentver',@$value['currentver']);
			$setup_tpl->set_var('version',$value['version']);
			$setup_tpl->set_var('bg_color',$bgcolor[$i]);

			switch($value['status'])
			{
				case 'C':
					$setup_tpl->set_var('remove', $key == 'api' ? '&nbsp;' : '<input type="checkbox" name="remove[' . $value['name'] . ']" />');
					$setup_tpl->set_var('upgrade','&nbsp;');
					if (!$GLOBALS['egw_setup']->detection->check_app_tables($value['name']))
					{
						// App installed and enabled, but some tables are missing
						$setup_tpl->set_var('instimg','table.png');
						$setup_tpl->set_var('bg_color','FFCCAA');
						$setup_tpl->set_var('instalt',lang('Not Completed'));
						$setup_tpl->set_var('resolution','<a href="applications.php?resolve=' . $value['name'] . '&badinstall=True">' . lang('Potential Problem') . '</a>');
						$status = lang('Requires reinstall or manual repair') . ' - ' . $value['status'];
					}
					else
					{
						$setup_tpl->set_var('instimg','completed.png');
						$setup_tpl->set_var('instalt',lang('Completed'));
						$setup_tpl->set_var('install','&nbsp;');
						if($value['enabled'])
						{
							$setup_tpl->set_var('resolution','');
							$status = lang('OK') . ' - ' . $value['status'];
						}
						else
						{
							if ($value['tables'][0] != '')
							{
								$notables = '&notables=True';
							}
							$setup_tpl->set_var('bg_color','CCCCFF');
							$setup_tpl->set_var('resolution',
								'<a href="applications.php?resolve=' . $value['name'] .  $notables . '">' . lang('Possible Reasons') . '</a>'
							);
							$status = lang('Disabled') . ' - ' . $value['status'];
						}
					}
					break;
				case 'U':
					$setup_tpl->set_var('instimg','incomplete.png');
					$setup_tpl->set_var('instalt',lang('Not Completed'));
					if (!@$value['currentver'])
					{
						if ($value['tables'] && $GLOBALS['egw_setup']->detection->check_app_tables($value['name'],True))
						{
							// Some tables missing
							$setup_tpl->set_var('remove',$key == 'api' ? '&nbsp;' : '<input type="checkbox" name="remove[' . $value['name'] . ']" />');
							$setup_tpl->set_var('resolution','<a href="applications.php?resolve=' . $value['name'] . '&badinstall=True">' . lang('Potential Problem') . '</a>');
							$status = lang('Requires reinstall or manual repair') . ' - ' . $value['status'];
						}
						else
						{
							$setup_tpl->set_var('remove','&nbsp;');
							$setup_tpl->set_var('resolution','');
							$status = lang('Requires upgrade') . ' - ' . $value['status'];
						}
						// show not installed apps without icon
						$setup_tpl->set_var('instimg','spacer.png');
						$setup_tpl->set_var('instalt','');
						$setup_tpl->set_var('bg_color','CCFFCC');
						$setup_tpl->set_var('install','<input type="checkbox" name="install[' . $value['name'] . ']" />');
						$setup_tpl->set_var('upgrade','&nbsp;');
						$status = lang('Please install') . ' - ' . $value['status'];
					}
					else
					{
						$setup_tpl->set_var('bg_color','CCCCFF');
						$setup_tpl->set_var('install','&nbsp;');
						// TODO display some info about breakage if you mess with this app
						$setup_tpl->set_var('upgrade','<input type="checkbox" name="upgrade[' . $value['name'] . ']" />');
						$setup_tpl->set_var('remove',$key == 'api' ? '&nbsp;' : '<input type="checkbox" name="remove[' . $value['name'] . ']" />');
						$setup_tpl->set_var('resolution','');
						$status = lang('Requires upgrade') . ' - ' . $value['status'];
					}
					break;
				case 'V':
					$setup_tpl->set_var('instimg','incomplete.png');
					$setup_tpl->set_var('instalt',lang('Not Completed'));
					$setup_tpl->set_var('install','&nbsp;');
					$setup_tpl->set_var('remove',$key == 'api' ? '&nbsp;' : '<input type="checkbox" name="remove[' . $value['name'] . ']" />');
					if ($value['version'] == 'deleted')
					{
						$setup_tpl->set_var('bg_color','CCAAAA');
						$setup_tpl->set_var('upgrade','&nbsp;');
						$setup_tpl->set_var('resolution','<a href="applications.php?resolve=' . $value['name'] . '&deleted=True">' . lang('Possible Solutions') . '</a>');
						$status = lang('Sources deleted/missing') . ' - ' . $value['status'];
					}
					else
					{
						$setup_tpl->set_var('upgrade','<input type="checkbox" name="upgrade[' . $value['name'] . ']" />');
						$setup_tpl->set_var('resolution','<a href="applications.php?resolve=' . $value['name'] . '&version=True">' . lang('Possible Solutions') . '</a>');
						$status = lang('Version Mismatch') . ' - ' . $value['status'];
					}
					break;
				case 'D':
					$setup_tpl->set_var('bg_color','FFCCCC');
					$depstring = parsedep($value['depends']);
					$depstring .= ')';
					$setup_tpl->set_var('instimg','dep.png');
					$setup_tpl->set_var('instalt',lang('Dependency Failure'));
					$setup_tpl->set_var('install','&nbsp;');
					if ($value['currentver'])
					{
						$setup_tpl->set_var('remove',$key == 'api' ? '&nbsp;' : '<input type="checkbox" name="remove[' . $value['name'] . ']" />');
						$setup_tpl->set_var('resolution','<a href="applications.php?resolve=' . $value['name'] . '">' . lang('Possible Solutions') . '</a>');
					}
					else
					{
						$setup_tpl->set_var('remove','&nbsp;');
						$setup_tpl->set_var('resolution','&nbsp;');
					}
					$setup_tpl->set_var('upgrade','&nbsp;');
					$status = lang('Dependency Failure') . ':' . $depstring . $value['status'];
					break;
				case 'P':
					$setup_tpl->set_var('bg_color','FFCCFF');
					$depstring = parsedep($value['depends']);
					$depstring .= ')';
					$setup_tpl->set_var('instimg','dep.png');
					$setup_tpl->set_var('instalt',lang('Post-install Dependency Failure'));
					$setup_tpl->set_var('install','&nbsp;');
					$setup_tpl->set_var('remove','&nbsp;');
					$setup_tpl->set_var('upgrade','&nbsp;');
					$setup_tpl->set_var('resolution','<a href="applications.php?resolve=' . $value['name'] . '&post=True">' . lang('Possible Solutions') . '</a>');
					$status = lang('Post-install Dependency Failure') . ':' . $depstring . $value['status'];
					break;
				default:
					$setup_tpl->set_var('instimg','incomplete.png');
					$setup_tpl->set_var('instalt',lang('Not Completed'));
					$setup_tpl->set_var('install','&nbsp;');
					$setup_tpl->set_var('remove','&nbsp;');
					$setup_tpl->set_var('upgrade','&nbsp;');
					$setup_tpl->set_var('resolution','');
					$status = '';
					break;
			}
			//$setup_tpl->set_var('appname',$value['name'] . '-' . $status . ',' . $value['filename']);
			$setup_tpl->set_var('appinfo',$value['name'] . '-' . $status);
			$setup_tpl->set_var('appname',$value['name']);

			$setup_tpl->pparse('out','apps',True);
		}
	}

	$setup_tpl->set_var('submit',lang('Save'));
	$setup_tpl->set_var('cancel',lang('Cancel'));
	$setup_tpl->pparse('out','app_footer');
	$setup_tpl->pparse('out','footer');
	$GLOBALS['egw_setup']->html->show_footer();
}
