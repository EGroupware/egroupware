#!/usr/bin/php
<?php

if (isset($_SERVER['HTTP_HOST'])) die("This is a commandline ONLY tool!\n");

if ($_SERVER['argc'] <= 1) die('
Usage: ./svn-helper.php <svn-arguments>
Changes into the directory of each module and executes svn with the given arguments. \\$module get\'s replaced with the module name. 
Examples:
- to merge all changes from trunk between revision 123 and 456 into all modules in the workingcopy:
	./svn-helper.php merge -r 123:456 http://svn.egroupware.org/egroupware/trunk/\\$module
- to switch a workingcopy to the 1.4 branch:
	./svn-helper.php switch http://svn.egroupware.org/egroupware/branches/1.4/\\$module
- to switch an anonymous workingcopy to a developers one:
	./svn-helper.php switch --relocate http://svn.egroupware.org svn+ssh://svn@dev.egroupware.org
'."\n");

$d = opendir($dir=dirname(__FILE__));

while (($file = readdir($d)) !== false) 
{
	$path = $dir . '/'. $file;
	if (!is_dir($path) || in_array($file,array('debian','home','doc','..','.svn'))) continue;

	chdir($path);
	
	$args = $_SERVER['argv'];
	array_shift($args);
	$args = implode(' ',$args);
	$args = str_replace('$module',$file == '.' ? 'egroupware' : $file,$args);
	
	echo "$file: svn $args\n";
	system('svn '.$args);
}
