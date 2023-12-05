<?php
/**
 * EGroupware Setup - DB backup and restore
 *
 * @link http://www.egroupware.org
 * @package setup
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

use EGroupware\Api;
use EGroupware\Api\Framework;
use EGroupware\Api\Egw;
use EGroupware\Api\Vfs;
use EGroupware\Stylite\Vfs\S3;

if (!is_object(@$GLOBALS['egw']))	// called from outside EGw ==> setup
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
if (class_exists(S3\Backup::class) && S3\Backup::available())
{
	$db_backup = new S3\Backup();
}
else
{
	$db_backup = new Api\Db\Backup();
}
$asyncservice = new Api\Asyncservice();

// download a backup, has to be before any output !!!
if (!empty($_POST['download']))
{
	$filename = $db_backup->backup_dir.'/'.key($_POST['download']);
	$file = $db_backup->fopen_backup($filename, true, false);

	// FIRST: switch off zlib.output_compression, as this would limit downloads in size to memory_limit
	ini_set('zlib.output_compression',0);
	// SECOND: end all active output buffering
	while(ob_end_clean()) {}

	Api\Header\Content::type(basename($filename));
	fpassthru($file);
	fclose($file);
	$db_backup->log($filename, 'Downloaded');
	exit;
}
$setup_tpl = new Framework\Template($tpl_root);
$setup_tpl->set_file(array(
	'T_head' => 'head.tpl',
	'T_footer' => 'footer.tpl',
	'T_db_backup' => 'db_backup.tpl',
));
$setup_tpl->set_var('hidden_vars', Api\Html::input_hidden('csrf_token', Api\Csrf::token(__FILE__)));

// check CSRF token for POST requests with any content (setup uses empty POST to call it's modules!)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $_POST)
{
	Api\Csrf::validate($_POST['csrf_token'], __FILE__);
}
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
	echo $GLOBALS['egw']->framework->header();
	echo $GLOBALS['egw']->framework->navbar();
	$run_in_egw = true;
}
// save backup housekeeping settings
if (!empty($_POST['save_backup_settings']))
{
	$matches = array();
	preg_match('/^\d*$/', $_POST['backup_mincount'], $matches);
	$minCount = (int)$matches[0];
	$filesBackup = $_POST['backup_files'] === 'backup_files';
	if (empty($minCount) && $matches[0] != '0')
	{
		$minCount = 0;
		$setup_tpl->set_var('error_msg',htmlspecialchars(lang("'%1' must be integer", lang("backup min count"))));
	}
	$db_backup->saveConfig($minCount,!empty($is_setup) ? $filesBackup : null);

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
if (!empty($_POST['mount']))
{
	Vfs::$is_root = true;
	echo '<div align="center">'.
		(Vfs::mount(($db_backup->backup_dir[0] === '/' ? 'filesystem://default' : '').$db_backup->backup_dir.'?group=Admins&mode=070','/backup',false) ?
			lang('Backup directory %1 mounted as %2',$db_backup->backup_dir,'/backup') :
			lang('Failed to mount Backup directory!')).
		"</div>\n";
	Vfs::$is_root = false;
}
// create a backup now
if (!empty($_POST['backup']))
{
	try {
		$f = $db_backup->fopen_backup();
		$starttime = microtime(true);
		$db_backup->backup($f);
		if(is_resource($f))
		{
			fclose($f);
		}
		$setup_tpl->set_var('error_msg', lang('backup finished').': '. number_format(microtime(true)-$starttime, 1).'s');

		/* Remove old backups. */
		$cleaned_files = array();
		$db_backup->housekeeping($cleaned_files);
		foreach ($cleaned_files as $file)
		{
			echo '<div align="center">'.lang('entry has been deleted sucessfully').': '.$file."</div>\n";
		}
	}
	catch (\Exception $e) {
		$setup_tpl->set_var('error_msg', $e->getMessage());
	}
}
$setup_tpl->set_var('backup_now_button','<input type="submit" name="backup" title="'.
	htmlspecialchars(lang("back's up your DB now, this might take a few minutes")).'" value="'.htmlspecialchars(lang('backup now')).
	'" onclick="if (egw && egw.loading_prompt) egw.loading_prompt(\'db_backup\', true, \''.htmlspecialchars(lang('backup started, this might take a few minutes ...')).'\'); return true;" />');
$setup_tpl->set_var('upload','<input type="file" name="uploaded" /> &nbsp;'.
	'<input type="submit" name="upload" value="'.htmlspecialchars(lang('upload backup')).'" title="'.htmlspecialchars(lang("uploads a backup to the backup-dir, from where you can restore it")).'" />');
$setup_tpl->set_var('backup_mincount','<input type="text" name="backup_mincount" value="'.$db_backup->backup_mincount.'" size="3" maxlength="3"/>');
$setup_tpl->set_var('backup_files','<input type="checkbox" name="backup_files" value="backup_files"'.
	($db_backup->backup_files ? ' checked="true"':'').
// do NOT allow to change "backup files" outside of setup
	($is_setup ? '' : ' disabled="true" title="'.htmlspecialchars(lang('Can only be change via Setup!')).'"').'/>');
$setup_tpl->set_var('backup_save_settings','<input type="submit" name="save_backup_settings" value="'.htmlspecialchars(lang('save')).'" />');
$setup_tpl->set_var('backup_mount','<input type="submit" name="mount" value="'.htmlspecialchars(lang('Mount backup directory to %1','/backup')).'" />');

if (!empty($_POST['upload']) && is_array($_FILES['uploaded']) && !$_FILES['uploaded']['error'] &&
	is_uploaded_file($_FILES['uploaded']['tmp_name']) && ($msg = $db_backup->upload($_FILES['uploaded'])))
{
	$setup_tpl->set_var('error_msg', $msg);
}
// delete a backup
if (!empty($_POST['delete']) && ($msg = $db_backup->delete(key($_POST['delete']))))
{
	$setup_tpl->set_var('error_msg', $msg);
}
// rename a backup
if (!empty($_POST['rename']) && ($file = key($_POST['rename'])) && !empty($_POST['new_name'][$file]) &&
	($msg = $db_backup->rename($file, $_POST['new_name'][$file])))
{
	$setup_tpl->set_var('error_msg', $msg);
}
// restore a backup
if (!empty($_POST['restore']))
{
	$file = key($_POST['restore']);
	$file = $db_backup->backup_dir.'/'.basename($file);	// basename to not allow to change the dir

	try {
		$f = $db_backup->fopen_backup($file,true);
		$start = time();
		$db_backup->restore($f, true, $file);	// always convert to current system charset on restore
		$setup_tpl->set_var('error_msg',lang("backup '%1' restored",$file).' ('.(time()-$start).' s)');
		if (isset($run_in_egw))
		{
			// updating the backup
			$cmd = new setup_cmd_update($GLOBALS['egw']->session->account_domain,
				$GLOBALS['egw_info']['server']['header_admin_user']='admin',
				$GLOBALS['egw_info']['server']['header_admin_password']=uniqid('pw',true),false);
			echo $cmd->run()."\n";
			echo '<h3>'.lang('You should %1log out%2 and in again, to update your current session!','<a href="'.Egw::link('/logout.php').'" target="_parent">','</a>')."</h3>\n";
		}
	}
	catch (\Exception $e)
	{
		$setup_tpl->set_var('error_msg', $e->getMessage());
	}
}
// create a new scheduled backup
if (!empty($_POST['schedule']))
{
	$asyncservice->set_timer($_POST['times'],'db_backup-'.implode(':',$_POST['times']),'admin.admin_db_backup.do_backup','');
}
// cancel a scheduled backup
if (!empty($_POST['cancel']) && is_array($_POST['cancel']))
{
	$id = key($_POST['cancel']);
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

// listing the available backups
$setup_tpl->set_var('backup_dir',$db_backup->backup_dir);
$setup_tpl->set_var('set_rows','');

foreach($db_backup->index() as $file => $attrs)
{
	$setup_tpl->set_var(array(
		'filename'	=> $file,
		'mod'		=> date('Y-m-d H:i', $attrs['ctime']),
		'size'		=> sprintf('%3.1f MB (%d)',$attrs['size']/(1024*1024), $attrs['size']),
		'actions'	=> '<input type="submit" name="download['.$file.']" value="'.htmlspecialchars(lang('download')).'" />&nbsp;'."\n".
			($file === Api\Db\Backup::LOG_FILE ? '' :
			'<input type="submit" name="delete['.$file.']" value="'.htmlspecialchars(lang('delete')).'" onclick="return confirm(\''.
				htmlspecialchars(lang('Confirm to delete this backup?')).'\');" />&nbsp;'."\n".
			'<input name="new_name['.$file.']" value="" size="15" /><input type="submit" name="rename['.$file.']" value="'.htmlspecialchars(lang('rename')).'" />&nbsp;'."\n".
			'<input type="submit" name="restore['.$file.']" value="'.htmlspecialchars(lang('restore')).'" onclick="if (confirm(\''.
				htmlspecialchars(lang('Restoring a backup will delete/replace all content in your database. Are you sure?')).
				'\')) { if (egw && egw.loading_prompt) egw.loading_prompt(\'db_backup\', true, \''.htmlspecialchars(lang('restore started, this might take a few minutes ...')).
				'\'); return true; } else return false;" />'),
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

if (isset($run_in_egw))
{
	echo $GLOBALS['egw']->framework->footer();
}
else
{
	$GLOBALS['egw_setup']->html->show_footer();
}