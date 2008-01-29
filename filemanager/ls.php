#!/usr/bin/php -qC 
<?php
/**
 * Filemanager - Command line interface: ls
 *
 * @link http://www.egroupware.org
 * @package admin
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2006 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

chdir(dirname(__FILE__));	// to enable our relative pathes to work

if (isset($_SERVER['HTTP_HOST']))	// security precaution: forbit calling ls as web-page
{
	die('<h1>'.basename(__FILE__).' must NOT be called as web-page --> exiting !!!</h1>');
}

/*
// this is kind of a hack, as the autocreate_session_callback can not change the type of the loaded account-class
// so we need to make sure the right one is loaded by setting the domain before the header gets included.
$arg0s = explode(',',@$arguments[0]);
@list(,$_GET['domain']) = explode('@',$arg0s[0]);

if (is_dir('/tmp')) ini_set('session.save_path','/tmp');	// regular users may have no rights to apache's session dir

$GLOBALS['egw_info'] = array(
	'flags' => array(
		'currentapp' => 'admin',
		'noheader' => true,
		'autocreate_session_callback' => 'user_pass_from_argv',
	)
);

include('../header.inc.php');
*/

/**
 * callback if the session-check fails, redirects via xajax to login.php
 * 
 * @param array &$account account_info with keys 'login', 'passwd' and optional 'passwd_type'
 * @return boolean/string true if we allow the access and account is set, a sessionid or false otherwise
 */
function user_pass_from_argv(&$account)
{
	$account = $GLOBALS['egw_login_data'];
	//print_r($account);
	if (!($sessionid = $GLOBALS['egw']->session->create($account)))
	{
		echo "Wrong admin-account or -password !!!\n\n";
		usage('',1);
	}
	return $sessionid;
}

/**
 * Give a usage message and exit
 *
 * @param string $action=null
 * @param int $ret=0 exit-code
 */
function usage($action=null,$ret=0)
{
	$cmd = basename(__FILE__);
	echo "Usage: $cmd URL\n";
	echo "\t$cmd --cat URL\n";
	echo "\t$cmd --cp URL-from URL-to\n";
	echo "\t$cmd --cp URL-from1 [URL-from2 ...] URL-to-directory\n";
	
	exit;	
}
$long = $numeric = false;
$argv = $_SERVER['argv'];
$cmd = basename(array_shift($argv),'.php');

if (!$argv) $argv = array('-h');

foreach($argv as $key => $option)
{
	if ($option[0] != '-') continue;
	
	unset($argv[$key]);

	switch($option)
	{
		default:
		case '-h': case '--help':
			usage();

		case '-l': case '--long':
			$long = true;
			continue 2;		// switch is counting too!

		case '-n': case '--numeric':
			$numeric = true;
			continue 2;		// switch is counting too!

		case '--cat':	// cat files (!) to stdout
		case '--cp':	// cp files
		case '--rm':	// rm files
			$cmd = substr($option,2);
			continue 2;		// switch is counting too!
	}
}
$argv = array_values($argv);
$argc = count($argv);

switch($cmd)
{
	case 'cp':
		do_cp($argv);
		break;
		
	default:
		while($url = array_shift($argv))
		{
			load_wrapper($url);
			//echo "$cmd $url (long=".(int)$long.", numeric=".(int)$numeric.")\n";
			
			if ($cmd == 'rm')
			{
				unlink($url);
			}
			elseif (is_dir($url) && ($dir = opendir($url)))
			{
				if ($argc)
				{
					echo "\n".basename(parse_url($url,PHP_URL_PATH)).":\n";
				}
				while(($file = readdir($dir)) !== false)
				{
					do_stat($url.'/'.$file,$long,$numeric);
				}
				closedir($dir);
			}
			elseif ($cmd == 'cat')
			{
				if (!($f = fopen($url,'r')))
				{
					echo "File $url not found !!!\n\n";
				}
				else
				{
					if ($argc)
					{
						echo "\n".basename(parse_url($url,PHP_URL_PATH)).":\n";
					}
					fpassthru($f);
					fclose($f);
				}
			}
			else
			{
				do_stat($url,$long,$numeric);
			}
			if (!$long && $cmd == 'ls') echo "\n";
		}
}

/**
 * Load the necessary wrapper for an url
 *
 * @param string $url
 */
function load_wrapper($url)
{
	switch(parse_url($url,PHP_URL_SCHEME))
	{
		case 'webdav':
			require_once('HTTP/WebDAV/Client.php');
			break;
		case 'oldvfs':
			if (!isset($GLOBALS['egw_info']))
			{
				$_GET['domain'] = parse_url($url,PHP_URL_HOST);
				$GLOBALS['egw_login_data'] = array(
					'login'  => parse_url($url,PHP_URL_USER),
					'passwd' => parse_url($url,PHP_URL_PASS),
					'passwd_type' => 'text',
				);
				
				if (is_dir('/tmp')) ini_set('session.save_path','/tmp');	// regular users may have no rights to apache's session dir
				
				$GLOBALS['egw_info'] = array(
					'flags' => array(
						'currentapp' => 'filemanager',
						'noheader' => true,
						'autocreate_session_callback' => 'user_pass_from_argv',
					)
				);
				
				include('../header.inc.php');
			}
			require_once(EGW_API_INC.'/class.oldvfs_stream_wrapper.inc.php');
			break;
		case '':
		case 'ftp':
			break;
		default:
			die("Unknown scheme in $url !!!\n\n");
	}
}

/**
 * Give the stats for one file
 *
 * @param string $url
 * @param boolean $long=false true=long listing with owner,group,size,perms, default false only filename
 * @param boolean $numeric=false true=give numeric uid&gid, else resolve the id to a name
 */
function do_stat($url,$long=false,$numeric=false)
{
	//echo "do_stat($url,$long,$numeric)\n";
	$bname = basename(parse_url($url,PHP_URL_PATH));
	
	if ($long)
	{
		$stat = stat($url);
		//print_r($stat);
		
		$perms = verbosePerms($stat['mode']);
		if ($numeric)
		{
			$uid = $stat['uid'];
			$gid = $stat['gid'];
		}
		else
		{
			if ($stat['uid'])
			{
				$uid = isset($GLOBALS['egw']) ? $GLOBALS['egw']->accounts->id2name($stat['uid']) : posix_getpwuid($stat['uid']); 
				if (is_array($uid)) $uid = $uid['name'];
			}
			if (!isset($uid)) $uid = 'none';
			if ($stat['gid'])
			{
				$gid = isset($GLOBALS['egw']) ? $GLOBALS['egw']->accounts->id2name($stat['gid']) : posix_getgrgid($stat['gid']); 
				if (is_array($gid)) $gid = $gid['name'];
			}
			if (!isset($gid)) $gid = 'none';
		}
		$size = hsize($stat['size']);
		$mtime = date('Y-m-d H:i:s',$stat['mtime']);
		$nlink = $stat['nlink'];
		
		echo "$perms $nlink\t$uid\t$gid\t$size\t$mtime\t$bname\n";
	}
	else
	{
		echo "$bname\t";
	}
}

function hsize($size)
{
	if ($size < 1024) return $size;
	if ($size < 1024*1024) return sprintf('%3.1lfk',(float)$size/1024);
	return sprintf('%3.1lfM',(float)$size/(1024*1024));
}

function verbosePerms( $in_Perms )
{
	if($in_Perms & 0x1000)     // FIFO pipe
	{
		$sP = 'p';
	}
	elseif($in_Perms & 0x2000) // Character special
	{
		$sP = 'c';
	}
	elseif($in_Perms & 0x4000) // Directory
	{
		$sP = 'd';
	}
	elseif($in_Perms & 0x6000) // Block special
	{
		$sP = 'b';
	}
	elseif($in_Perms & 0x8000) // Regular
	{
		$sP = '-';
	}
	elseif($in_Perms & 0xA000) // Symbolic Link
	{
		$sP = 'l';
	}
	elseif($in_Perms & 0xC000) // Socket
	{
		$sP = 's';
	}
	else                         // UNKNOWN
	{
		$sP = 'u';
	}

	// owner
	$sP .= (($in_Perms & 0x0100) ? 'r' : '-') .
	(($in_Perms & 0x0080) ? 'w' : '-') .
	(($in_Perms & 0x0040) ? (($in_Perms & 0x0800) ? 's' : 'x' ) :
	(($in_Perms & 0x0800) ? 'S' : '-'));

	// group
	$sP .= (($in_Perms & 0x0020) ? 'r' : '-') .
	(($in_Perms & 0x0010) ? 'w' : '-') .
	(($in_Perms & 0x0008) ? (($in_Perms & 0x0400) ? 's' : 'x' ) :
	(($in_Perms & 0x0400) ? 'S' : '-'));

	// world
	$sP .= (($in_Perms & 0x0004) ? 'r' : '-') .
	(($in_Perms & 0x0002) ? 'w' : '-') .
	(($in_Perms & 0x0001) ? (($in_Perms & 0x0200) ? 't' : 'x' ) :
	(($in_Perms & 0x0200) ? 'T' : '-'));
	return $sP;
}

function do_cp($argv)
{
	$to = array_pop($argv);
	load_wrapper($to);
	
	if (count($argv) > 1 && !is_dir($to))
	{
		die ("Usage: cp from-file to-file | cp file1 [file2 ...] dir\n\n");
	}
	if (count($argv) > 1)
	{
		foreach($argv as $from)
		{
			do_cp(array($from,$to));
		}
		return;
	}
	$from = array_shift($argv);
	load_wrapper($from);

	if (!($from_fp = fopen($from,'r')))
	{
		die("File $from not found!");
	}
	if (is_dir($to))
	{
		$path = parse_url($from,PHP_URL_PATH);
		$to .= '/'.basename($path);
	}
	if (!($to_fp = fopen($to,'w')))
	{
		die("Can't open $to from writing!");
	}
	$count = stream_copy_to_stream($from_fp,$to_fp);
	
	echo hsize($count)." bytes written to $to\n";
	
	fclose($from_fp);
	
	if (!fclose($to_fp))
	{
		die("Error closing $to!\n");
	}
}
