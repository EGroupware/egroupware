#!/usr/bin/php
<?php
/**
 * EGroupware setup - test or create the ldap connection and hierarchy
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package setup
 * @copyright (c) 2013 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

chdir(dirname(__FILE__));	// to enable our relative pathes to work

if (php_sapi_name() !== 'cli')	// security precaution: forbit calling as web-page
{
	die('<h1>setup/doc/chown.php must NOT be called as web-page --> exiting !!!</h1>');
}

$recursive = false;

$cmd = array_shift($_SERVER['argv']);

if ($_SERVER['argv'] && in_array($_SERVER['argv'][0], array('-R', '--recursive')))
{
	$recursive = true;
	array_shift($_SERVER['argv']);
}

if (count($_SERVER['argv']) != 2)
{
	usage();
}

function usage()
{
	die("\nUsage: $cmd [-R|--recursive] from-id,to-id[,from-id2,to-id2,...] path\n\nonly nummeric ids are allowed, group-ids have to be negative!\n\n");
}
$ids = explode(',', $_SERVER['argv'][0]);
$change = array();
while($ids)
{
	$from = (int)array_shift($ids);
	$to = (int)array_shift($ids);

	if (!$from || !$to || ($from < 0) != ($to < 0))
	{
		echo "from-id and to-id must be nummeric and have same sign (negative for groups)!\n\n";
		usage();
	}
	$change[$from] = $to;
}

$path = $_SERVER['argv'][1];
if (!file_exists($path))
{
	echo "File or directory '$path' not found!\n\n";
	usage();
}

if (posix_getuid())
{
	die("\nNeed to run as root, to be able to change owner and group!\n\n");
}

chown_grp($path, null, $recursive);

function chown_grp($path, ?array $stat=null, $recursive=false)
{
	global $change;

	if (is_null($stat) && !($stat = stat($path))) return false;

	if (isset($change[$stat['uid']]) && !chown($path, $uid=$change[$stat['uid']]))
	{
		echo "Faild to set new owner #$uid for $path\n";
	}
	if (isset($change[-$stat['gid']]) && !chgrp($path, $gid=-$change[-$stat['gid']]))
	{
		echo "Faild to set new group #$gid for $path\n";
	}

	if ($recursive && is_dir($path))
	{
		foreach(new DirectoryIterator($path) as $child)
		{
			if (!$child->isDot())
				chown_grp($child->getPathname(), array(
					'uid' => $child->getOwner(),
					'gid' => $child->getGroup(),
				), $recursive);
		}
	}
}