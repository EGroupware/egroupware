<?php
/**
 * eGroupWare Setup - System configuration
 *
 * @link http://www.egroupware.org
 * @package setup
 * @author Miles Lott <milos@groupwhere.org>
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

include('./inc/functions.inc.php');

/*
Authorize the user to use setup app and load the database
Does not return unless user is authorized
*/
if(!$GLOBALS['egw_setup']->auth('Config') || @$_POST['cancel'])
{
	Header('Location: index.php');
	exit;
}

$tpl_root = $GLOBALS['egw_setup']->html->setup_tpl_dir('setup');
$setup_tpl = CreateObject('phpgwapi.Template',$tpl_root);

$setup_tpl->set_file(array(
	'T_head' => 'head.tpl',
	'T_footer' => 'footer.tpl',
	'T_alert_msg' => 'msg_alert_msg.tpl',
	'T_config_pre_script' => 'config_pre_script.tpl',
	'T_config_post_script' => 'config_post_script.tpl'
));

/* Following to ensure windows file paths are saved correctly */
if (function_exists('get_magic_quotes_runtime') && get_magic_quotes_runtime())
{
	set_magic_quotes_runtime(0);
}
$GLOBALS['egw_setup']->loaddb();

/* Check api version, use correct table */
$setup_info = $GLOBALS['egw_setup']->detection->get_db_versions();

$newsettings = $_POST['newsettings'];

if(@get_var('submit',Array('POST')) && @$newsettings)
{
	/* Load hook file with functions to validate each config (one/none/all) */
	$GLOBALS['egw_setup']->hook('config_validate','setup');

	$datetime = CreateObject('phpgwapi.egw_datetime');
	switch((int)$newsettings['daytime_port'])
	{
		case 13:
			$newsettings['tz_offset'] = $datetime->getntpoffset();
			break;
		case 80:
			$newsettings['tz_offset'] = $datetime->gethttpoffset();
			break;
		default:
			$newsettings['tz_offset'] = $datetime->getbestguess();
			break;
	}
	unset($datetime);

	print_debug('TZ_OFFSET',$newsettings['tz_offset']);

	$GLOBALS['egw_setup']->db->transaction_begin();
	foreach($newsettings as $setting => $value)
	{
		if($GLOBALS['egw_info']['server']['found_validation_hook'] && @function_exists($setting))
		{
			$setting($newsettings);
			if($GLOBALS['config_error'])
			{
				$GLOBALS['error'] .= '<b>'.$GLOBALS['config_error'] ."</b><br />\n";
				$GLOBALS['config_error'] = '';
				/* Bail out, stop writing config data */
				break;
			}
			$value = $newsettings[$setting];	// it might be changed by the validation hook
		}
		/* Don't erase passwords, since we also do not print them below */
		if(empty($value) && !(stristr($setting,'passwd') || stristr($setting,'password') || stristr($setting,'root_pw')))
		{
			$GLOBALS['egw_setup']->db->delete($GLOBALS['egw_setup']->config_table,array(
				'config_name' => $setting,
				'config_app'  => 'phpgwapi',
			),__LINE__,__FILE__);
			unset($newsettings[$setting]);
		}
		elseif($value)
		{
			$GLOBALS['egw_setup']->db->insert($GLOBALS['egw_setup']->config_table,array(
				'config_value' => $value,
			),array(
				'config_name' => $setting,
				'config_app'  => 'phpgwapi',
			),__LINE__,__FILE__);
		}
	}
	if(!$GLOBALS['error'])
	{
		$GLOBALS['egw_setup']->db->transaction_commit();
		// unset cached config, as this is the primary source for configuration now
		egw_cache::unsetInstance('config', 'configs');

		Header('Location: index.php');
		exit;
	}
}

$GLOBALS['egw_setup']->html->show_header(lang('Configuration'),False,'config',$GLOBALS['egw_setup']->ConfigDomain . '(' . $GLOBALS['egw_domain'][$GLOBALS['egw_setup']->ConfigDomain]['db_type'] . ')');

// if we have an validation error, use the new settings made by the user and not the stored config
if($GLOBALS['error'] && is_array($newsettings))
{
	$GLOBALS['current_config'] = $newsettings;
}
else
{
	$GLOBALS['egw_setup']->db->select($GLOBALS['egw_setup']->config_table,'*',false,__LINES__,__FILES__);
	while($GLOBALS['egw_setup']->db->next_record())
	{
		$GLOBALS['current_config'][$GLOBALS['egw_setup']->db->f('config_name')] = $GLOBALS['egw_setup']->db->f('config_value');
	}
}
$setup_tpl->pparse('out','T_config_pre_script');

/* Now parse each of the templates we want to show here */
class phpgw
{
	var $common;
	var $accounts;
	var $applications;
	var $db;
}
$GLOBALS['egw'] = new phpgw;
$GLOBALS['egw']->common =& CreateObject('phpgwapi.common');
$GLOBALS['egw']->db     =& $GLOBALS['egw_setup']->db;

$t = CreateObject('phpgwapi.Template',$GLOBALS['egw']->common->get_tpl_dir('setup'));

$t->set_unknowns('keep');
$t->set_file(array('config' => 'config.tpl'));
$t->set_block('config','body','body');

$vars = $t->get_undefined('body');
$GLOBALS['egw_setup']->hook('config','setup');

foreach($vars as $value)
{
	$valarray = explode('_',$value);
	$type = $valarray[0];
	$new = $newval = '';

	while($chunk = next($valarray))
	{
		$new[] = $chunk;
	}
	$newval = implode(' ',$new);

	switch ($type)
	{
		case 'lang':
			$t->set_var($value,lang($newval));
			break;
		case 'value':
			$newval = str_replace(' ','_',$newval);
			/* Don't show passwords in the form */
			if(strpos($value,'passwd') !== false || strpos($value,'password') !== false || strpos($value,'root_pw') !== false)
			{
				$t->set_var($value,'');
			}
			else
			{
				$t->set_var($value,@$current_config[$newval]);
			}
			break;
		case 'selected':
			$configs = array();
			$config  = '';
			$newvals = explode(' ',$newval);
			$setting = end($newvals);
			for($i=0;$i<(count($newvals) - 1); $i++)
			{
				$configs[] = $newvals[$i];
			}
			$config = implode('_',$configs);
			/* echo $config . '=' . $current_config[$config]; */
			if(@$current_config[$config] == $setting)
			{
				$t->set_var($value,' selected');
			}
			else
			{
				$t->set_var($value,'');
			}
			break;
		case 'hook':
			$newval = str_replace(' ','_',$newval);
			$t->set_var($value,$newval($current_config));
			break;
		default:
			$t->set_var($value,'');
			break;
	}
}

if($GLOBALS['error'])
{
	if($GLOBALS['error'] == 'badldapconnection')
	{
		/* Please check the number and dial again :) */
		$GLOBALS['egw_setup']->html->show_alert_msg('Error',
			lang('There was a problem trying to connect to your LDAP server. <br />'
				.'please check your LDAP server configuration') . '.');
	}

	$GLOBALS['egw_setup']->html->show_alert_msg('Error',$GLOBALS['error'].'<p>');
}

$t->pfp('out','body');
unset($t);

$setup_tpl->set_var('more_configs',lang('Please login to egroupware and run the admin application for additional site configuration') . '.');

$setup_tpl->set_var('lang_submit',lang('Save'));
$setup_tpl->set_var('lang_cancel',lang('Cancel'));
$setup_tpl->pparse('out','T_config_post_script');

$GLOBALS['egw_setup']->html->show_footer();
