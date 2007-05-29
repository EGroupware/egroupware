#!/usr/bin/php -qC 
<?php
/**
 * Admin - Command line interface
 *
 * @link http://www.egroupware.org
 * @package admin
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2006 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

chdir(dirname(__FILE__));	// to enable our relative pathes to work

if (isset($_SERVER['HTTP_HOST']))	// security precaution: forbit calling admin-cli as web-page
{
	die('<h1>ls.php must NOT be called as web-page --> exiting !!!</h1>');
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
	if (!$GLOBALS['egw_info']['user']['apps']['admin'])	// will be tested by the header too, but whould give html error-message
	{
		echo "Permission denied !!!\n\n";
		usage('',2);
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
	$cmd = basename($_SERVER['argv'][0]);
	echo "Usage: $cmd URL\n\n";
	
	exit;	
}
$long=false;
array_shift($_SERVER['argv']);
while(($url = array_shift($_SERVER['argv'])))
{
	if ($url == '-l')
	{
		$long = true;
		continue;
	}
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
						'currentapp' => 'admin',
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
	if (($dir = opendir($url)))
	{
		if ($_SERVER['argc'] > 2)
		{
			echo "\n".basename(parse_url($url,PHP_URL_PATH)).":\n";
		}
		while(($file = readdir($dir)) !== false)
		{
			do_stat($url.'/'.$file,$long);
		}
		closedir($dir);
	}
	else
	{
		do_stat($url,$long);
	}
/*	else
	{
		echo "File or directory not found !!!\n\n";
	}*/
	if (!$long) echo "\n";
}

function do_stat($url,$long=false)
{
	$bname = basename(parse_url($url,PHP_URL_PATH));
	
	if ($long)
	{
		$stat = stat($url);
		//print_r($stat);
		
		$perms = verbosePerms($stat['mode']);
		$uid = isset($GLOBALS['egw']) && $stat['uid'] ? $GLOBALS['egw']->accounts->id2name($stat['uid']) : posix_getpwuid($stat['uid']); 
		if (is_array($uid)) $uid = $uid['name'];
		$gid = isset($GLOBALS['egw']) && $stat['gid'] ? $GLOBALS['egw']->accounts->id2name($stat['gid']) : posix_getgrgid($stat['gid']); 
		if (is_array($gid)) $gid = $gid['name'];
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

