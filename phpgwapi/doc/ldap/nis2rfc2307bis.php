#!/usr/bin/php -qC
<?php
/**
 * API accounts - convert a slapcat file to the rfc2307bis schema (from nis or rfc2307bis without groupOfNames)
 * 
 * Only the groups get changed:
 *  - structural objectClass posixAccount (or SuSE's namedObject) get replaced with groupOfNames
 *  - SuSE's default structural objectClass namedObject get removed from the objectClass(es)
 *  - member attribute(s) of groupOfNames get set from the posixAccount memberUid and the account-dn
 *  - memberUid's not found in the whole file get removed!
 * 
 * Use it as filter: nis2rfc2307bis.php [--group2account-dn /cn=[^,]+,ou=groups/ou=accounts/] old.ldif > new.ldif
 * 
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de> complete rewrite in 6/2006 and earlier modifications
 * 
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage accounts
 * @access public
 * @version $Id: class.accounts.inc.php 22048 2006-07-08 21:41:42Z ralfbecker $
 */

if ($argc <= 1 || in_array($argv[1],array('-v','--help')) || $argv[1] == '--accounts-dn' && $argc <= 3|| 
	!is_readable($file = $argv[$argc-1]))
{
	if ($file)
	{
		echo "'$file' does NOT exist!!!\n";
	}
	die("Usage: nis2rfc2307bis.php [--group2account-dn /cn=[^,]+,ou=groups/ou=accounts/] old.ldif > new.ldif\n");
}

$lines = file($file);
foreach($lines as $l => $line)
{
	$lines[$l] = rtrim($line);
}

$group2account = '/cn=[^,]+,ou=groups/ou=accounts/';
if ($argv[1] == '--group2account-dn' && $argc > 3)
{
	$group2account = $argv[2];
}
$parts = explode('/',$group2account);
if (count($parts) != 4)
{
	die("Wrong format for --group2accounts-dn, use something like '/cn=[^,]+,ou=groups/ou=accounts/'\n");
}
$replace_with = $parts[2]; unset($parts[2]);
$replace = implode('/',$parts);

$block = array();
$i = 0;
$lines[] = '';	// extra empty line, if none is behind the last block
foreach($lines as $l => $line)
{
	if ($line)
	{
		@list($attr,$value) = explode(': ',$line,2);
		switch($attr)
		{
			case 'dn':
				$dn = $value;
				break;
			case 'objectClass':
				$objectclasses[] = $value;
				break;
			case 'structuralObjectClass':
				$structural = $value;
				break;
			case 'memberUid':
				$member_dn = 'uid='.$value.','.preg_replace($replace,$replace_with,$dn);
				if (!in_array('dn: '.$member_dn,$lines)) continue;	// member does not exist --> ignore him!
				$members[] = 'member: '.$member_dn;
				// fall-through
			default:
				$data[] = $line;
				break;
		}
		$block[] = $line;
		continue;
	}
	if (!$block) continue;

	// got a complete block
	if (in_array('posixGroup',$objectclasses))
	{
		switch($structural)
		{
			case 'namedObject':	// regular SuSE
				unset($objectclasses[array_search('namedObject',$objectclasses)]);
				// fall-through
			case 'posixGroup':	// nis
				$objectclasses[] = $structural = 'groupOfNames';
				if (!$members) $members[] = 'member: '.$dn;	// member is a required attribute!
				$data = array_merge($members,$data);
				break;
			case 'groupOfNames':	// ok, already what we want
				break;
			default:
				die("\nposixGroup dn: $dn has as structrualObjectClass $structural, not posixGroup, namedObject or groupOfNames!\n");
		}
		$block = array('dn: '.$dn,);
		foreach($objectclasses as $class)
		{
			$block[] = 'objectClass: '.$class;
		}
		$block[] = 'structuralObjectClass: '.$class;
		$block = array_merge($block,$data);
	}
	echo implode("\n",$block)."\n\n";

	// process next block
	$block = $objectclasses = $members = $data = array();
	$dn = $structural = null;
}