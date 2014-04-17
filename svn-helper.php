#!/usr/bin/php
<?php
/**
 * helper for EGroupware SVN layout
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2007-14 by Ralf Becker <rb@stylite.de>
 * @version $Id$
 */

if (php_sapi_name() !== 'cli') die("This is a commandline ONLY tool!\n");

if ($_SERVER['argc'] <= 1) die('
Usage: ./svn-helper.php <svn-arguments>
Changes into the directory of each module and executes svn with the given arguments.
\\$module get\'s replaced with the module name, for every svn command but "merge", where log is queryed to avoid running merge on all modules.
Examples:
- to merge all changes from trunk between revision 123 and 456 into all modules in the workingcopy:
	./svn-helper.php merge (-c 123|-r 123:456)+ [^/trunk]		# multiple -c or -r are allowed, ^/trunk is the default
- to switch a workingcopy to the 1.8 branch:
	./svn-helper.php switch ^/branches/1.8/\\$module
- to switch an anonymous workingcopy to a developers one:
	./svn-helper.php switch --relocate http://svn.egroupware.org svn+ssh://svn@dev.egroupware.org
'."\n");

$args = $_SERVER['argv'];
array_shift($args);


switch ($args[0])
{
	case 'merge':
		do_merge($args);
		break;

	case 'up':
		if (count($args) == 1)	// run an svn up over all modules
		{
			$cmd = 'svn up '.implode(' ', get_app_dirs());
			echo $cmd."\n";
			system($cmd);
			break;
		}
		// fall through
	default:
		foreach(get_app_dirs() as $module => $dir)
		{
			chdir(__DIR__ . '/'. $dir);

			$args_m = str_replace('$module', $module, implode(' ',$args));
			echo "$module: svn $args_m\n";
			system('svn '.$args_m);
		}
		break;
}

/**
 * Get all EGroupware application directories including "."
 *
 * @return array module => relativ path pairs, "egroupware" => ".", "addressbook" => "addressbook", ...
 */
function get_app_dirs()
{
	$app_dirs = array();
	foreach(scandir(__DIR__) as $dir)
	{
		$path = __DIR__ . '/'. $dir;
		if (!is_dir($path) || in_array($dir, array('debian','home','doc','..','.svn')) ||
			!is_dir($path.'/setup') && $dir != 'setup') continue;
		$app_dirs[$dir == '.' ? 'egroupware' : $dir] = $dir;
	}
	//error_log(__METHOD__."() returning ".print_r($app_dirs, true));
	return $app_dirs;
}

function do_merge(array $args)
{
	chdir(dirname(__FILE__));	// go to EGroupware root

	array_shift($args);	// get ride of "merge" arg
	if (substr(end($args),0,2) !== '^/' && strpos(end($args),'://') === false)
	{
		array_push($args,'^/trunk');
	}
	// get xml log
	$cmd = "svn log --verbose --xml ".implode(' ',$args);
	//echo $cmd;
	$output_arr = $err = null;
	exec($cmd, $output_arr, $err);
	$output = implode("\n", $output_arr);
	if ($err) throw new Exception("'$cmd' returned $err\n$output");
	$log = new SimpleXMLElement($output);
	$modules = $messages = array();
	foreach($log->logentry as $logentry)
	{
		foreach($logentry->attributes() as $name => $rev)
		{
			if ($name == 'revision') break;
		}
		echo "r$rev: ".$logentry->msg."\n";
		$messages['r'.$rev] = (string)$logentry->msg;
		foreach($logentry->paths->path as $path)
		{
			//echo "\t".$path."\n";
			$matches = null;
			if (preg_match('#(/trunk/|/branches/[^/]+/)([^/]+)/#',$path,$matches))
			{
				if (!in_array($matches[2],$modules)) $modules[] = $matches[2];
			}
		}
	}
	//print_r($modules);
	//print_r($messages);
	$cmds = array();
	foreach($modules as $n => $module)
	{
		system('svn -q update '.($module == 'egroupware' ? '.' : $module));	// svn >= 1.7 brings an error otherwise
		$cmds[] = 'svn merge '.implode(' ',$args).'/'.$module.($module != 'egroupware'?' '.$module:'');
		if ($module == 'egroupware') $modules[$n] = '.';
	}
	$cmds[] = 'svn diff '.implode(' ',$modules);
	foreach($cmds as $n => $cmd)
	{
		echo "$cmd\n";
		passthru($cmd, $err);	// passthru allows to call editor on merge conflicts
		if ($err) exit;
	}
	$msg = str_replace("'", "\\'", array_shift($messages));
	// prefix further commit messages with "r$rev: "
	if ($messages)
	{
		foreach($messages as $rev => $message)
		{
			$msg .= "\n$rev: ".str_replace("'", "\\'", $message);
		}
	}
	echo "\nTo commit run:\n"."svn commit -m '$msg' ".implode(' ',$modules)."\n";
}
