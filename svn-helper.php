#!/usr/bin/php
<?php
/**
 * helper for EGroupware SVN layout
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2007-12 by Ralf Becker <rb@stylite.de>
 * @version $Id$
 */

if (isset($_SERVER['HTTP_HOST'])) die("This is a commandline ONLY tool!\n");

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

	default:
		$d = opendir($dir=dirname(__FILE__));

		while (($file = readdir($d)) !== false)
		{
			$path = $dir . '/'. $file;
			if (!is_dir($path) || in_array($file,array('debian','home','doc','..','.svn'))) continue;

			chdir($path);

			$args_m = str_replace('$module',$file == '.' ? 'egroupware' : $file,implode(' ',$args));
			echo "$file: svn $args_m\n";
			system('svn '.$args_m);
		}
		break;
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
	exec($cmd, $output, $err);
	$output = implode("\n",$output);
	if ($err) throw new Exception("'$cmd' returned $err\n$output");
	$log = new SimpleXMLElement($output);
	$modules = $messages = array();
	foreach($log->logentry as $logentry)
	{
		foreach($logentry->attributes() as $name => $rev) if ($name == 'revision') break;
		echo "r$rev: ".$logentry->msg."\n";
		$messages['r'.$rev] = (string)$logentry->msg;
		foreach($logentry->paths->path as $path)
		{
			//echo "\t".$path."\n";
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
