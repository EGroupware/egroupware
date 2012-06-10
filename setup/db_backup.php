<?php
/**
 * eGroupWare Setup - DB backup and restore
 *
 * @link http://www.egroupware.org
 * @package setup
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

if (!is_object(@$GLOBALS['egw']))	// called from outside eGW ==> setup
{
	include ('./inc/functions.inc.php');

	@set_time_limit(0);

	// Check header and authentication
	if (!$GLOBALS['egw_setup']->auth('Config'))
	{
		Header('Location: index.php');
		exit;
	}
	// Does not return unless user is authorized

	$GLOBALS['egw_setup']->loaddb();

	$tpl_root = $GLOBALS['egw_setup']->html->setup_tpl_dir('setup');
	$self = 'db_backup.php';
	$is_setup = true;
}
$db_backup = new db_backup();
$asyncservice = new asyncservice();

// download a backup, has to be before any output !!!
if ($_POST['download'])
{
	list($file) = each($_POST['download']);
	$file = $db_backup->backup_dir.'/'.basename($file);	// basename to now allow to change the dir
	while (@ob_end_clean()) ;       // end all active output buffering
	ini_set('zlib.output_compression',0);   // switch off zlib.output_compression, as this would limit downloads in size to memory_limit
	html::content_header(basename($file));
	readfile($file);
	exit;
}
$setup_tpl = CreateObject('phpgwapi.Template',$tpl_root);
$setup_tpl->set_file(array(
	'T_head' => 'head.tpl',
	'T_footer' => 'footer.tpl',
	'T_db_backup' => 'db_backup.tpl',
));
$setup_tpl->set_block('T_db_backup','schedule_row','schedule_rows');
$setup_tpl->set_block('T_db_backup','set_row','set_rows');

$setup_tpl->set_var('stage_title',$stage_title = lang('DB backup and restore'));
$setup_tpl->set_var('stage_desc',lang('This program lets you backup your database, schedule a backup or restore it.'));
$setup_tpl->set_var('error_msg','');

$bgcolor = array('#DDDDDD','#EEEEEE');

if (is_object($GLOBALS['egw_setup']->html))
{
	$GLOBALS['egw_setup']->html->show_header($stage_title,False,'config',$GLOBALS['egw_setup']->ConfigDomain . '(' . $GLOBALS['egw_domain'][$GLOBALS['egw_setup']->ConfigDomain]['db_type'] . ')');
}
else
{
	$setup_tpl->set_block('T_db_backup','setup_header');
	$setup_tpl->set_var('setup_header','');
	$GLOBALS['egw_info']['flags']['app_header'] = $stage_title;
	common::egw_header();
	parse_navbar();
	$run_in_egw = true;
}
// save backup housekeeping settings
if ($_POST['save_backup_settings'])
{
	$matches = array();
	preg_match('/^[1-9][0-9]*$/', $_POST['backup_mincount'], $matches);
	$minCount = $matches[0];
	$filesBackup = $_POST['backup_files'] === 'backup_files';
	if (empty($minCount))
	{
		$minCount = 0;
		$setup_tpl->set_var('error_msg',htmlspecialchars(lang("'%1' must be integer", lang("backup min count"))));
	}
	$db_backup->saveConfig($minCount,$is_setup ? (boolean)$filesBackup : null);

	if (is_int($minCount) && $minCount > 0)
	{
		$cleaned_files = array();
		/* Remove old backups. */
		$db_backup->housekeeping($cleaned_files);
		foreach ($cleaned_files as $file)
		{
			echo '<div align="center">'.lang('entry has been deleted sucessfully').': '.$file."</div>\n";
		}
	}
}
if ($_POST['mount'])
{
	egw_vfs::$is_root = true;
	echo '<div align="center">'.
		(egw_vfs::mount('filesystem://default'.$db_backup->backup_dir.'?group=Admins&mode=070','/backup',false) ?
			lang('Backup directory %1 mounted as %2',$db_backup->backup_dir,'/backup') :
			lang('Failed to mount Backup directory!')).
		"</div>\n";
	egw_vfs::$is_root = false;
}
// create a backup now
if($_POST['backup'])
{
	if (is_resource($f = $db_backup->fopen_backup()))
	{
		echo '<p align="center">'.lang('backup started, this might take a few minutes ...')."</p>\n".str_repeat(' ',4096);
		$db_backup->backup($f);
		if(is_resource($f))
			fclose($f);
		$setup_tpl->set_var('error_msg',lang('backup finished'));

		/* Remove old backups. */
		$cleaned_files = array();
		$db_backup->housekeeping($cleaned_files);
		foreach ($cleaned_files as $file)
		{
			echo '<div align="center">'.lang('entry has been deleted sucessfully').': '.$file."</div>\n";
		}
	}
	else
	{
		$setup_tpl->set_var('error_msg',$f);
	}
}
$setup_tpl->set_var('backup_now_button','<input type="submit" name="backup" title="'.htmlspecialchars(lang("back's up your DB now, this might take a few minutes")).'" value="'.htmlspecialchars(lang('backup now')).'" />');
$setup_tpl->set_var('upload','<input type="file" name="uploaded" /> &nbsp;'.
	'<input type="submit" name="upload" value="'.htmlspecialchars(lang('upload backup')).'" title="'.htmlspecialchars(lang("uploads a backup to the backup-dir, from where you can restore it")).'" />');
$setup_tpl->set_var('backup_mincount','<input type="text" name="backup_mincount" value="'.$db_backup->backup_mincount.'" size="3" maxlength="3"/>');
$setup_tpl->set_var('backup_files','<input type="checkbox" name="backup_files" value="backup_files"'.
	($db_backup->backup_files ? ' checked="true"':'').
// do NOT allow to change "backup files" outside of setup
	($is_setup ? '' : ' disabled="true" title="'.htmlspecialchars(lang('Can only be change via Setup!')).'"').'/>');
$setup_tpl->set_var('backup_save_settings','<input type="submit" name="save_backup_settings" value="'.htmlspecialchars(lang('save')).'" />');
$setup_tpl->set_var('backup_mount','<input type="submit" name="mount" value="'.htmlspecialchars(lang('Mount backup directory to %1','/backup')).'" />');

if ($_POST['upload'] && is_array($_FILES['uploaded']) && !$_FILES['uploaded']['error'] &&
	is_uploaded_file($_FILES['uploaded']['tmp_name']))
{
	move_uploaded_file($_FILES['uploaded']['tmp_name'],$db_backup->backup_dir.'/'.$_FILES['uploaded']['name']);

	if (function_exists('md5_file'))	// php4.2+
	{
		$md5 = ', md5='.md5_file($db_backup->backup_dir.'/'.$_FILES['uploaded']['name']);
	}
	$setup_tpl->set_var('error_msg',lang("succesfully uploaded file %1",$_FILES['uploaded']['name'].', '.
		sprintf('%3.1lf MB (%d)',$_FILES['uploaded']['size']/(1024*1024),$_FILES['uploaded']['size']).$md5));
}
// delete a backup
if ($_POST['delete'])
{
	list($file) = each($_POST['delete']);
	$file = $db_backup->backup_dir.'/'.basename($file);	// basename to not allow to change the dir

	if (unlink($file)) $setup_tpl->set_var('error_msg',lang("backup '%1' deleted",$file));
}
// rename a backup
if ($_POST['rename'])
{
	list($file) = each($_POST['rename']);
	$new_name = $_POST['new_name'][$file];
	if (!empty($new_name))
	{
		list($ending) = array_reverse(explode('.', $file));
		list($new_ending, $has_ending) = array_reverse(explode('.', $new_name));
		if(!$has_ending || $new_ending != $ending) $new_name .= '.'.$ending;
		$file = $db_backup->backup_dir.'/'.basename($file);	// basename to not allow to change the dir
		$ext = preg_match('/(\.gz|\.bz2)+$/i',$file,$matches) ? $matches[1] : '';
		$new_file = $db_backup->backup_dir.'/'.preg_replace('/(\.gz|\.bz2)+$/i','',basename($new_name)).$ext;
		if (rename($file,$new_file)) $setup_tpl->set_var('error_msg',lang("backup '%1' renamed to '%2'",basename($file),basename($new_file)));
	}
}
// restore a backup
if ($_POST['restore'])
{
	list($file) = each($_POST['restore']);
	$file = $db_backup->backup_dir.'/'.basename($file);	// basename to not allow to change the dir

	if (is_resource($f = $db_backup->fopen_backup($file,true)))
	{
		echo '<p align="center">'.lang('restore started, this might take a few minutes ...')."</p>\n".str_repeat(' ',4096);
		$start = time();
		$db_backup->restore($f, true, $file);	// allways convert to current system charset on restore
		$setup_tpl->set_var('error_msg',lang("backup '%1' restored",$file).' ('.(time()-$start).' s)');
		if ($run_in_egw)
		{
			// updating the backup
			$cmd = new setup_cmd_update($GLOBALS['egw']->session->account_domain,
				$GLOBALS['egw_info']['server']['header_admin_user']='admin',
				$GLOBALS['egw_info']['server']['header_admin_password']=uniqid('pw',true),false,true);
			echo $cmd->run()."\n";
			echo '<h3>'.lang('You should %1log out%2 and in again, to update your current session!','<a href="'.egw::link('/logout.php').'" target="_parent">','</a>')."</h3>\n";
		}
	}
	else
	{
		$setup_tpl->set_var('error_msg',$f);
	}
}
// create a new scheduled backup
if ($_POST['schedule'])
{
	$asyncservice->set_timer($_POST['times'],'db_backup-'.implode(':',$_POST['times']),'admin.admin_db_backup.do_backup','');
}
// cancel a scheduled backup
if (is_array($_POST['cancel']))
{
	list($id) = each($_POST['cancel']);
	$asyncservice->cancel_timer($id);
}
// list scheduled backups
if (($jobs = $asyncservice->read('db_backup-%')))
{
	foreach($jobs as $job)
	{
		$setup_tpl->set_var($job['times']);
		$setup_tpl->set_var('next_run',date('Y-m-d H:i',$job['next']));
		$setup_tpl->set_var('actions','<input type="submit" name="cancel['.$job['id'].']" value="'.htmlspecialchars(lang('delete')).'" />');
		$setup_tpl->parse('schedule_rows','schedule_row',true);
	}
}
// input-fields to create a new scheduled backup
foreach($times=array('year'=>'*','month'=>'*','day'=>'*','dow'=>'2-6','hour'=>3,'min'=>0) as $name => $default)
{
	$setup_tpl->set_var($name,'<input name="times['.$name.']" size="5" value="'.$default.'" />');
}
$setup_tpl->set_var('next_run','&nbsp;');
$setup_tpl->set_var('actions','<input type="submit" name="schedule" value="'.htmlspecialchars(lang('schedule')).'" />');
$setup_tpl->parse('schedule_rows','schedule_row',true);

// listing the availible backup sets
$setup_tpl->set_var('backup_dir',$db_backup->backup_dir);
$setup_tpl->set_var('set_rows','');
$handle = @opendir($db_backup->backup_dir);
$files = array();
while($handle && ($file = readdir($handle)))
{
	if ($file != '.' && $file != '..')
	{
		$files[$file] = filectime($db_backup->backup_dir.'/'.$file);
	}
}
if ($handle) closedir($handle);

arsort($files);
foreach($files as $file => $ctime)
{
	$size = filesize($db_backup->backup_dir.'/'.$file);
	$setup_tpl->set_var(array(
		'filename'	=> $file,
		'mod'		=> date('Y-m-d H:i',$ctime),
		'size'		=> sprintf('%3.1lf MB (%d)',$size/(1024*1024),$size),
		'actions'	=> '<input type="submit" name="download['.$file.']" value="'.htmlspecialchars(lang('download')).'" />&nbsp;'."\n".
			'<input type="submit" name="delete['.$file.']" value="'.htmlspecialchars(lang('delete')).'" onclick="return confirm(\''.
				htmlspecialchars(lang('Confirm to delete this backup?')).'\');" />&nbsp;'."\n".
			'<input name="new_name['.$file.']" value="" size="15" /><input type="submit" name="rename['.$file.']" value="'.htmlspecialchars(lang('rename')).'" />&nbsp;'."\n".
			'<input type="submit" name="restore['.$file.']" value="'.htmlspecialchars(lang('restore')).'" onclick="return confirm(\''.
				htmlspecialchars(lang('Restoring a backup will delete/replace all content in your database. Are you sure?')).'\');" />',
	));
	$setup_tpl->parse('set_rows','set_row',true);
}

$setup_tpl->set_var(array(
	'lang_scheduled_backups'=> lang('scheduled backups'),
	'lang_year'				=> lang('year'),
	'lang_month'			=> lang('month'),
	'lang_day'				=> lang('day'),
	'lang_dow'				=> lang('day of week<br />(0-6, 0=sunday)'),
	'lang_hour'				=> lang('hour (0-24)'),
	'lang_minute'			=> lang('minute'),
	'lang_next_run'			=> lang('next run'),
	'lang_actions'			=> lang('actions'),
	'lang_backup_sets'		=> lang('backup sets'),
	'lang_backup_cleanup'	=> lang('backup housekeeping'),
	'lang_backup_mincount'	=> lang('min backup count'),
	'lang_backup_files_info'  => lang('backup files (needs ZipArchive)'),
	'lang_backup_files'  => lang('check to backup and restore the files directory (may use a lot of space, make sure to configure housekeeping accordingly)'),
	'lang_filename'			=> lang('filename'),
	'lang_date'				=> lang('created'),
	'lang_mod'				=> lang('modified'),
	'lang_size'				=> lang('size'),
));

$setup_tpl->set_var('self',$self);
$setup_tpl->pparse('out','T_db_backup');

if ($run_in_egw)
{
	common::egw_footer();
}
else
{
	$GLOBALS['egw_setup']->html->show_footer();
}
