<?php
/**
 * EGroupware - Filemanager - test script
 *
 * @link http://www.egroupware.org
 * @package filemanager
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2009-16 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

use EGroupware\Api;
use EGroupware\Api\Vfs;

$GLOBALS['egw_info']['flags'] = array(
	'currentapp' => 'filemanager'
);
include('../header.inc.php');

if (!($path = Api\Cache::getSession('filemanger','test')))
{
	$path = '/home/'.$GLOBALS['egw_info']['user']['account_lid'];
}
if (isset($_REQUEST['path'])) $path = $_REQUEST['path'];
echo Api\Html::form("<p>Path: ".Api\Html::input('path',$path,'text','size="40"').
	Api\Html::input_hidden(['cd'=>'no']).
	Api\Html::submit_button('',lang('Submit'))."</p>\n",array(),'','','','','GET');

if (isset($path) && !empty($path))
{
	if ($path[0] != '/')
	{
		throw new Api\Exception\WrongUserinput('Not an absolute path!');
	}
	Api\Cache::setSession('filemanger','test',$path);

	echo "<h2>";
	foreach(explode('/',$path) as $n => $part)
	{
		$p .= ($p != '/' ? '/' : '').$part;
		echo ($n > 1 ? ' / ' : '').Api\Html::a_href($n ? $part : ' / ','/filemanager/test.php',array('path'=>$p,'cd'=>'no'));
	}
	echo "</h2>\n";

	echo "<p><b>Vfs::propfind('$path')</b>=".array2string(Vfs::propfind($path))."</p>\n";
	echo "<p><b>Vfs::resolve_url('$path')</b>=".array2string(Vfs::resolve_url($path))."</p>\n";

	$is_dir = Vfs::is_dir($path);
	echo "<p><b>is_dir('$path')</b>=".array2string($is_dir)."</p>\n";

	$time = microtime(true);
	$stat = Vfs::stat($path);
	$stime = number_format(1000*(microtime(true)-$time),1);

	$time2 = microtime(true);
	if ($is_dir)// && ($d = Vfs::opendir($path)))
	{
		$files = array();
		//while(($file = readdir($d)))
		foreach(Vfs::scandir($path) as $file)
		{
			if (Vfs::is_readable($fpath=Vfs::concat($path,$file)))
			{
				$file = Api\Html::a_href($file,'/filemanager/test.php',array('path'=>$fpath,'cd'=>'no'));
			}
			$file .= ' ('.Vfs::mime_content_type($fpath).')';
			$files[] = $file;
		}
		//closedir($d);
		$time2f = number_format(1000*(microtime(true)-$time2),1);
		echo "<p>".($files ? 'Directory' : 'Empty directory')." took $time2f ms</p>\n";
		if($files) echo '<ol><li>'.implode("</li>\n<li>",$files).'</ol>'."\n";
	}

	echo "<p><b>stat('$path')</b> took $stime ms (mode = ".(isset($stat['mode'])?sprintf('%o',$stat['mode']).' = '.Vfs::int2mode($stat['mode']):'NULL').')';
	if (is_array($stat))
	{
		_debug_array($stat);
	}
	else
	{
		echo "<p>".array2string($stat)."</p>\n";
	}

	echo "<p><b>Vfs::is_readable('$path')</b>=".array2string(Vfs::is_readable($path))."</p>\n";
	echo "<p><b>Vfs::is_writable('$path')</b>=".array2string(Vfs::is_writable($path))."</p>\n";

	echo "<p><b>is_link('$path')</b>=".array2string(Vfs::is_link($path))."</p>\n";
	echo "<p><b>readlink('$path')</b>=".array2string(Vfs::readlink($path))."</p>\n";
	$time3 = microtime(true);
	$lstat = Vfs::lstat($path);
	$time3f = number_format(1000*(microtime(true)-$time3),1);
	echo "<p><b>lstat('$path')</b> took $time3f ms (mode = ".(isset($lstat['mode'])?sprintf('%o',$lstat['mode']).' = '.Vfs::int2mode($lstat['mode']):'NULL').')';
	if (is_array($lstat))
	{
		_debug_array($lstat);
	}
	else
	{
		echo "<p>".array2string($lstat)."</p>\n";
	}
	if (!$is_dir && $stat)
	{
		echo "<p><b>Vfs::mime_content_type('$path')</b>=".array2string(Vfs::mime_content_type($path))."</p>\n";
		echo "<p><b>filesize(Vfs::PREFIX.'$path')</b>=".array2string(filesize(Vfs::PREFIX.$path))."</p>\n";
		echo "<p><b>bytes(file_get_contents(Vfs::PREFIX.'$path'))</b>=".array2string(bytes(file_get_contents(Vfs::PREFIX.$path)))."</p>\n";
	}
}
echo $GLOBALS['egw']->framework->footer();
