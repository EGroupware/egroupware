<?php
/**
 * Setup - Manage the eGW config file header.inc.php
 *
 * @link http://www.egroupware.org
 * @package setup
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @author Miles Lott <milos@groupwhere.org>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

include('./inc/functions.inc.php');

require_once('./inc/class.setup_header.inc.php');
$GLOBALS['egw_setup']->header = new setup_header();

$setup_tpl = CreateObject('phpgwapi.Template','./templates/default');
$setup_tpl->set_file(array(
	'T_head' => 'head.tpl',
	'T_footer' => 'footer.tpl',
	'T_alert_msg' => 'msg_alert_msg.tpl',
	'T_login_main' => 'login_main.tpl',
	'T_login_stage_header' => 'login_stage_header.tpl',
	'T_setup_manage' => 'manageheader.tpl'
));
$setup_tpl->set_block('T_login_stage_header','B_multi_domain','V_multi_domain');
$setup_tpl->set_block('T_login_stage_header','B_single_domain','V_single_domain');
$setup_tpl->set_block('T_setup_manage','manageheader','manageheader');
$setup_tpl->set_block('T_setup_manage','domain','domain');

// authentication phase
$GLOBALS['egw_info']['setup']['stage']['header'] = $GLOBALS['egw_setup']->detection->check_header();

if ($GLOBALS['egw_info']['setup']['stage']['header'] > 2 && !$GLOBALS['egw_setup']->auth('Header'))
{
	$GLOBALS['egw_setup']->html->show_header('Please login',True);
	$GLOBALS['egw_setup']->html->login_form();
	$GLOBALS['egw_setup']->html->show_footer();
	exit;
}
// Detect current mode
switch($GLOBALS['egw_info']['setup']['stage']['header'])
{
	case '1':
		$GLOBALS['egw_info']['setup']['HeaderFormMSG'] = lang('Create your header.inc.php');
		$GLOBALS['egw_info']['setup']['PageMSG'] = lang('You have not created your header.inc.php yet!<br /> You can create it now.');
		break;
	case '2':
		$GLOBALS['egw_info']['setup']['HeaderFormMSG'] = $GLOBALS['egw_info']['setup']['PageMSG'] =
			lang('Your header admin password is NOT set. Please set it now!');
		break;
	case '3':
		$GLOBALS['egw_info']['setup']['HeaderFormMSG'] = $GLOBALS['egw_info']['setup']['PageMSG'] =
			$GLOBALS['egw_info']['setup']['HeaderLoginMSG'] =
			lang('You need to add at least one eGroupWare domain / database instance.');
		break;
	case '4':
		$GLOBALS['egw_info']['setup']['HeaderFormMSG'] = $GLOBALS['egw_info']['setup']['HeaderLoginMSG'] =
			lang('Your header.inc.php needs upgrading.');
		$GLOBALS['egw_info']['setup']['PageMSG'] = lang('Your header.inc.php needs upgrading.<br /><blink><b class="msg">WARNING!</b></blink><br /><b>MAKE BACKUPS!</b>');
		break;
	case '10':
		$GLOBALS['egw_info']['setup']['HeaderFormMSG'] = lang('Edit your header.inc.php');
		$GLOBALS['egw_info']['setup']['PageMSG'] = lang('Edit your existing header.inc.php');
		break;
}

if (!file_exists('../header.inc.php') || !is_readable('../header.inc.php') || !defined('EGW_SERVER_ROOT') || EGW_SERVER_ROOT == '..')
{
	$GLOBALS['egw_setup']->header->defaults();
}
else
{
	$GLOBALS['egw_info']['server']['server_root'] = EGW_SERVER_ROOT;
}
if (isset($_POST['setting']))	// Post of the header-form
{
	$validation_errors = check_header_form();	// validate the submitted form
}
if (!isset($_POST['action']) || $validation_errors)	// generate form to edit the header
{
	show_header_form($validation_errors);
}
else
{
	$newheader = $GLOBALS['egw_setup']->header->generate($GLOBALS['egw_info'],$GLOBALS['egw_domain']);

	list($action) = @each($_POST['action']);
	switch($action)
	{
		case 'download':
			$browser = CreateObject('phpgwapi.browser');
			$browser->content_header('header.inc.php','application/octet-stream');
			echo $newheader;
			break;

		case 'view':
			$GLOBALS['egw_setup']->html->show_header('Generated header.inc.php', False, 'header');
			echo '<table width="90%"><tr><td>';
			echo '<br />' . lang('Save this text as contents of your header.inc.php') . '<br /><hr />';
			echo "<pre>\n";
			echo htmlentities($newheader);
			echo "\n</pre><hr />\n";
			echo '<form action="index.php" method="post">';
			echo '<br />' . lang('After retrieving the file, put it into place as the header.inc.php.  Then, click "continue".') . '<br />';
			echo '<input type="hidden" name="FormLogout" value="header" />';
			echo '<input type="submit" name="junk" value="'.lang('Continue').'" />';
			echo '</form>';
			echo '</td></tr></table>';
			$GLOBALS['egw_setup']->html->show_footer();
			break;

		case 'write':
			if ((is_writeable('../header.inc.php') || !file_exists('../header.inc.php') && is_writeable('../')) &&
				($f = fopen('../header.inc.php','wb')))
			{
				fwrite($f,$newheader);
				fclose($f);
				$GLOBALS['egw_setup']->html->show_header('Saved header.inc.php', False, 'header');
				echo '<form action="index.php" method="post">';
					echo '<br />' . lang('Created header.inc.php!');
				echo '<input type="hidden" name="FormLogout" value="header" />';
				echo '<input type="submit" name="junk" value="'.lang('Continue').'" />';
				echo '</form>';
				$GLOBALS['egw_setup']->html->show_footer();
				break;
			}
			else
			{
				$GLOBALS['egw_setup']->html->show_header('Error generating header.inc.php', False, 'header');
				echo lang('Could not open header.inc.php for writing!') . '<br />' . "\n";
				echo lang('Please check read/write permissions on directories, or back up and use another option.') . '<br />';
				$GLOBALS['egw_setup']->html->show_footer();
			}
			break;
	}
}

/**
 * Validate the posted form and place the content again in $GLOBALS['egw_info'] and $GLOBALS['egw_domain']
 *
 * @return array with validation errors, see setup_header::validation_errors
 */
function check_header_form()
{
	// setting the non-domain settings from the posted content
	foreach($_POST['setting'] as $name => $value)
	{
		if (get_magic_quotes_gpc()) $value = stripslashes($value);

		switch($name)
		{
			case 'show_domain_selectbox':
			case 'mcrypt_enabled':
			case 'db_persistent':
				$GLOBALS['egw_info']['server'][$name] = $value == 'True';
				break;
			case 'new_admin_password':
				if ($value) $GLOBALS['egw_info']['server']['header_admin_password'] = md5($value);
				break;
			default:
				$GLOBALS['egw_info']['server'][$name] = $value;
				break;
		}
	}

	// setting the domain settings from the posted content
	foreach($_POST['domains'] as $key => $domain)
	{
		if ($_POST['deletedomain'][$key])
		{
			// Need to actually remove the domain.  Drop the DB manually.
			unset($GLOBALS['egw_domain'][$domain]);
			continue;
		}

		foreach($_POST['setting_'.$key] as $name => $value)
		{
			if (get_magic_quotes_gpc()) $value = stripslashes($value);

			if ($name == 'new_config_passwd')
			{
				if ($value) $GLOBALS['egw_domain'][$domain]['config_passwd'] = md5($value);
				continue;
			}
			$GLOBALS['egw_domain'][$domain][$name] = $value;
		}
	}

	// validate the input and return errors
	$validation_errors = $GLOBALS['egw_setup']->header->validation_errors($GLOBALS['egw_info']['server']['server_root']);

	//echo "egw_info[server]=<pre>".print_r($GLOBALS['egw_info']['server'],true)."</pre>\n";
	//echo "egw_domain=<pre>".print_r($GLOBALS['egw_domain'],true)."</pre>\n";
	//if ($validation_errors) echo "validation_errors=<pre>".print_r($validation_errors,true)."</pre>\n";

	return $validation_errors;
}

/**
 * Display the form to edit the configuration
 *
 * @param array $validation_errors to display
 */
function show_header_form($validation_errors)
{
	global $setup_tpl;

	$GLOBALS['egw_setup']->html->show_header($GLOBALS['egw_info']['setup']['HeaderFormMSG'], False, 'header');

	if(!get_var('ConfigLang',array('POST','COOKIE')))
	{
		$setup_tpl->set_var('lang_select','<tr><td colspan="2"><form action="manageheader.php" method="post">Please Select your language '.setup_html::lang_select(True,'en')."</form></td></tr>");
	}

	$setup_tpl->set_var('pagemsg',$GLOBALS['egw_info']['setup']['PageMSG']);

	// checking required PHP version
	if ((float) PHP_VERSION < $GLOBALS['egw_setup']->required_php_version)
	{
		$GLOBALS['egw_setup']->html->show_header($GLOBALS['egw_info']['setup']['header_msg'],True);
		$GLOBALS['egw_setup']->html->show_alert_msg('Error',
			lang('You are using PHP version %1. eGroupWare now requires %2 or later, recommended is PHP %3.',
			PHP_VERSION,$GLOBALS['egw_setup']->required_php_version,$GLOBALS['egw_setup']->recommended_php_version));
		$GLOBALS['egw_setup']->html->show_footer();
		exit;
	}
	$supported_db = $GLOBALS['egw_setup']->header->check_db_support($detected);

	if (!count($supported_db))
	{
		echo '<p align="center" class="msg"><b>'
			. lang('Did not find any valid DB support!')
			. "<br />\n"
			. lang('Try to configure your php to support one of the above mentioned DBMS, or install eGroupWare by hand.')
			. '</b></p>';
		$GLOBALS['egw_setup']->html->show_footer();
		exit;
	}
	$js_default_db_ports = 'var default_db_ports = new Array();'."\n";
	foreach($GLOBALS['egw_setup']->header->default_db_ports as $db => $port)
	{
		$js_default_db_ports .= '  default_db_ports["'.$db.'"]="'.$port.'";'."\n";
	}
	$setup_tpl->set_var('js_default_db_ports',$js_default_db_ports);

	if ($validation_errors) $setup_tpl->set_var('detected','<ul><li>'.implode("</li>\n<li>",$validation_errors)."</li>\n</ul>\n");

	if ($_POST['adddomain'])
	{
		$GLOBALS['egw_domain'][lang('new')] = $GLOBALS['egw_setup']->header->domain_defaults(
			$GLOBALS['egw_info']['server']['header_admin_user'],
			$GLOBALS['egw_info']['server']['header_admin_password'],$supported_db);
	}
	// show the non-domain settings
	//echo "<pre>".print_r($GLOBALS['egw_info']['server'],true)."</pre>\n";
	foreach($GLOBALS['egw_info']['server'] as $name => $value)
	{
		switch($name)
		{
			case 'db_persistent':
			case 'show_domain_selectbox':
			case 'mcrypt_enabled':
				$setup_tpl->set_var($name.($GLOBALS['egw_info']['server'][$name] ? '_yes' : '_no'),' selected="selected"');
				break;
			default:
				if (!is_array($value)) $setup_tpl->set_var($name,htmlspecialchars($value));
				break;
		}
	}
	$supported_session_handler = array(
		'egw_session_files' => lang('PHP session handler enabled in php.ini'),
	);
	if ($GLOBALS['egw_info']['server']['session_handler'] && !isset($supported_session_handler[$GLOBALS['egw_info']['server']['session_handler']]))
	{
		$supported_session_handler[$GLOBALS['egw_info']['server']['session_handler']] = lang("Custom handler: %1",$GLOBALS['egw_info']['server']['session_handler']);
	}
	$options = array();
	foreach($supported_session_handler as $type => $label)
	{
		$options[] = '<option ' . ($type == $GLOBALS['egw_info']['server']['session_handler'] ?
			'selected="selected" ' : '') . 'value="' . $type . '">' . $label . '</option>';
	}
	$setup_tpl->set_var('session_options',implode("\n",$options));

	// showing the settings of all domains
	foreach($GLOBALS['egw_domain'] as $domain => $data)
	{
		$setup_tpl->set_var('db_domain',htmlspecialchars($domain));
		foreach($data as $name => $value)
		{
			if ($name == 'db_port' && !$value)	// Set default here if the admin didn't set a port yet
			{
				$value = $GLOBALS['egw_setup']->header->default_db_ports[$data['db_type']];
			}
			$setup_tpl->set_var($name,htmlspecialchars($value));
		}
		$dbtype_options = '';
		foreach($supported_db as $db)
		{
			$dbtype_options .= '<option ' . ($db == $data['db_type'] ? 'selected="selected" ' : '').
				'value="' . $db . '">' . $GLOBALS['egw_setup']->header->db_fullnames[$db] . "</option>\n";
		}
		$setup_tpl->set_var('dbtype_options',$dbtype_options);

		$setup_tpl->parse('domains','domain',True);
	}
	if(is_writeable('../header.inc.php') || !file_exists('../header.inc.php') && is_writeable('../'))
	{
		$setup_tpl->set_var('actions',lang('%1, %2 or %3 the configuration file.',
			'<input type="submit" name="action[write]" value="'.htmlspecialchars(lang('Write')).'" />',
			'<input type="submit" name="action[download]" value="'.htmlspecialchars(lang('Download')).'" />',
			'<input type="submit" name="action[view]" value="'.htmlspecialchars(lang('View')).'" />'));
	}
	else
	{
		$setup_tpl->set_var('actions',lang('Cannot create the header.inc.php due to file permission restrictions.<br /> Instead you can %1 or %2 the file.',
			'<input type="submit" name="action[download]" value="'.htmlspecialchars(lang('Download')).'" />',
			'<input type="submit" name="action[view]" value="'.htmlspecialchars(lang('View')).'" />'));
	}
	// set domain and password for the continue button
	@reset($GLOBALS['egw_domain']);
	list($firstDomain) = @each($GLOBALS['egw_domain']);

	$setup_tpl->set_var(array(
		'FormDomain' => $firstDomain,
		'FormUser'   => $GLOBALS['egw_domain'][$firstDomain]['config_user'],
		'FormPW'     => $GLOBALS['egw_domain'][$firstDomain]['config_passwd']
	));

	$setup_tpl->set_var(array(
		'lang_analysis'        => $validation_errors ? lang('Validation errors') : '',
		'lang_settings'        => lang('Settings'),
		'lang_domain'          => lang('Database instance (eGW domain)'),
		'lang_delete'          => lang('Delete'),
		'lang_adddomain'       => lang('Add new database instance (eGW domain)'),
		'lang_serverroot'      => lang('Server Root'),
		'lang_serverroot_descr'=> lang('Path (not URL!) to your eGroupWare installation.'),
		'lang_adminuser'       => lang('Header username'),
		'lang_adminuser_descr' => lang('Admin user for header manager'),
		'lang_adminpass'       => lang('Header password'),
		'lang_adminpass_descr' => lang('Admin password to header manager').'.',
		'lang_leave_empty'     => lang('Leave empty to keep current.'),
		'lang_setup_acl'       => lang('Limit access'),
		'lang_setup_acl_descr' => lang('Limit access to setup to the following addresses, networks or hostnames (e.g. 127.0.0.1,10.1.1,myhost.dnydns.org)'),
		'lang_dbhost'          => lang('DB Host'),
		'lang_dbhostdescr'     => lang('Hostname/IP of database server').'<br />'.
			lang('Postgres: Leave it empty to use the prefered unix domain sockets instead of a tcp/ip connection').'<br />'.
			lang('ODBC / MaxDB: DSN (data source name) to use'),
		'lang_dbport'          => lang('DB Port'),
		'lang_dbportdescr'     => lang('TCP port number of database server'),
		'lang_dbname'          => lang('DB Name'),
		'lang_dbnamedescr'     => lang('Name of database'),
		'lang_dbuser'          => lang('DB User'),
		'lang_dbuserdescr'     => lang('Name of db user eGroupWare uses to connect'),
		'lang_dbpass'          => lang('DB Password'),
		'lang_dbpassdescr'     => lang('Password of db user'),
		'lang_dbtype'          => lang('DB Type'),
		'lang_whichdb'         => lang('Which database type do you want to use with eGroupWare?'),
		'lang_configuser'      => lang('Configuration User'),
		'lang_configuser_descr'=> lang('Loginname needed for domain configuration'),
		'lang_configpass'      => lang('Configuration Password'),
		'lang_passforconfig'   => lang('Password needed for domain configuration.'),
		'lang_persist'         => lang('Persistent connections'),
		'lang_persistdescr'    => lang('Do you want persistent connections (higher performance, but consumes more resources)'),
		'lang_session'         => lang('Sessions Handler'),
		'lang_session_descr'   => lang('Session handler class used.'),
		'lang_enablemcrypt'    => lang('Enable MCrypt'),
		'lang_mcrypt_warning'  => lang('Not all mcrypt algorithms and modes work with eGroupWare. If you experience problems try switching it off.'),
		'lang_mcryptiv'        => lang('MCrypt initialization vector'),
		'lang_mcryptivdescr'   => lang('This should be around 30 bytes in length.<br />Note: The default has been randomly generated.'),
		'lang_domselect'       => lang('Domain select box on login'),
		'lang_domselect_descr' => lang('Alternatively domains can be accessed by logging in with <i>username@domain</i>.'),
		'lang_finaldescr'      => lang('After retrieving the file, put it into place as the header.inc.php.  Then, click "continue".'),
		'lang_continue'        => lang('Continue'),
		'lang_Yes'             => lang('Yes'),
		'lang_No'              => lang('No')
	));
	$setup_tpl->pfp('out','manageheader');

	$GLOBALS['egw_setup']->html->show_footer();
}
