#!/usr/bin/php -qC
<?php
/**
 * Filemanager - Command line interface
 *
 * @link http://www.egroupware.org
 * @package filemanager
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2007-10 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 *
 * @todo --domain does NOT work with --user root_* for domains other then the first in header.inc.php
 */

chdir(dirname(__FILE__));	// to enable our relative pathes to work

error_reporting(error_reporting() & ~E_NOTICE);

if (isset($_SERVER['HTTP_HOST']))	// security precaution: forbit calling ls as web-page
{
	die('<h1>'.basename(__FILE__).' must NOT be called as web-page --> exiting !!!</h1>');
}

/**
 * callback if the session-check fails, creates session from user/passwd in $GLOBALS['egw_login_data']
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
		echo "Wrong user-account or -password !!!\n\n";
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
	echo "Usage:\t$cmd ls [-r|--recursive|-l|--long|-i|--inode] URL [URL2 ...]\n";
	echo "\t$cmd cat URL [URL2 ...]\n";
	echo "\t$cmd cp [-r|--recursive] [-p|--perms] URL-from URL-to\n";
	echo "\t$cmd cp [-r|--recursive] [-p|--perms] URL-from [URL-from2 ...] URL-to-directory\n";
	echo "\t$cmd rm [-r|--recursive] URL [URL2 ...]\n";
	echo "\t$cmd mkdir [-p|--parents] URL [URL2 ...]\n";
	echo "\t$cmd rmdir URL [URL2 ...]\n";
	echo "\t$cmd touch [-r|--recursive] [-d|--date time] URL [URL2 ...]\n";
	echo "\t$cmd chmod [-r|--recursive] [ugoa]*[+-=][rwx]+,... URL [URL2 ...]\n";
	echo "\t$cmd chown [-r|--recursive] user URL [URL2 ...]\n";
	echo "\t$cmd chgrp [-r|--recursive] group URL [URL2 ...]\n";
	echo "\t$cmd find URL [URL2 ...] [-type (d|f)][-depth][-mindepth n][-maxdepth n][-mime type[/sub]][-name pattern][-path pattern][-uid id][-user name][-nouser][-gid id][-group name][-nogroup][-size N][-cmin N][-ctime N][-mmin N][-mtime N] (N: +n --> >n, -n --> <n, n --> =n) [-limit N[,n]][-order (name|size|...)][-sort (ASC|DESC)]\n";
	echo "\t$cmd mount URL [path] (without path prints out the mounts)\n";
	echo "\t$cmd umount [-a|--all (restores default mounts)] URL|path\n";
	echo "\t$cmd eacl URL [rwx-] [user or group]\n";
	echo "\tsudo -u apache $cmd migrate-db2fs --user root_admin --passwd password [--domain default] (migrates sqlfs content from DB to filesystem)\n";

	echo "\nCommon options: --user user --password password [--domain domain] can be used to pass eGW credentials without using the URL writing.\n";
	echo "\nURL: {vfs|sqlfs|filesystem}://user:password@domain/home/user/file[?option=value&...], /dir/file, ...\n";

	echo "\nUse root_{header-admin|config-user} as user and according password for root access (no user specific access control and chown).\n\n";

	exit;
}
$long = $numeric = $recursive = $perms = $all = $inode = false;
$argv = $_SERVER['argv'];
$cmd = basename(array_shift($argv),'.php');
if ($argv[0][0] != '-' && $argv[0][0] != '/' && strpos($argv[0],'://') === false)
{
	$cmd = array_shift($argv);
}

if (!$argv) $argv = array('-h');

$args = $find_options = array();
while(!is_null($option = array_shift($argv)))
{
	if ($option == '-' || $option[0] != '-')	// no option --> argument
	{
		$args[] = $option;
		continue;
	}

	switch($option)
	{
		default:
			if ($cmd == 'find')
			{
				if (!in_array($option,array('-type','-depth','-mindepth','-maxdepth','-name','-path',
					'-uid','-user','-nouser','-gid','-group','-nogroup','-mime',
					'-empty','-size','-cmin','-ctime','-mmin','-mtime','-limit','-order','-sort')))
				{
					usage();
				}
				if (in_array($option,array('-empty','-depth','-nouser','-nogroup')))
				{
					$find_options[substr($option,1)] = true;
				}
				else
				{
					$find_options[substr($option,1)] = array_shift($argv);
				}
				break;
			}
			// multiple options, eg. -rp --> -r -p
			elseif($option[0] == '-' && $option[1] != '-' && strlen($option) > 2)
			{
				for($i = 1; $i < strlen($option); ++$i)
				{
					array_unshift($argv,'-'.$option[$i]);
				}
				break;
			}
		case '-h': case '--help':
			usage();

		case '-l': case '--long':
			$long = true;
			break;

		case '-n': case '--numeric':
			$numeric = true;
			break;

		case '-r': case '--recursive':
			$recursive = true;
			break;

		case '-i': case '--inode':
			$inode = true;
			break;

		case '-p': case '--parents': case '--perms':
			if ($cmd == 'cp')
			{
				$perms = true;
			}
			else
			{
				$recursive = true;
			}
			break;

		case '-d': case '--date':
			$time = strtotime(array_shift($argv));
			break;

		case '-a': case '--all':
			$all = true;
			break;

		case '--user':
			$user = array_shift($argv);
			break;
		case '--password':
		case '--passwd':
			$passwd = array_shift($argv);
			break;
		case '--domain':
			$domain = array_shift($argv);
			break;
	}
}
if ($user && $passwd)
{
	load_egw($user,$passwd,$domain ? $domain : 'default');
}
$argv = $args;
$argc = count($argv);

switch($cmd)
{
	case 'umount':
		if ($argc != 1 && !$all)
		{
			usage();
		}
		if (($url = $argv[0])) load_wrapper($url);
		if(!egw_vfs::$is_root)
		{
			die("You need to be root to do that!\n");
		}
		if ($all)
		{
			config::save_value('vfs_fstab',$GLOBALS['egw_info']['server']['vfs_fstab']='','phpgwapi');
			echo "Restored default mounts:\n";
		}
		elseif (!egw_vfs::umount($url))
		{
			die("$url is NOT mounted!\n");
		}
		else
		{
			echo "Successful unmounted $url:\n";
		}
		// fall trough to output current mount table
	case 'mount':
		if ($argc > 2)
		{
			usage();
		}
		load_wrapper($url=$argv[0]);
		if($argc > 1 && !egw_vfs::$is_root)
		{
			die("You need to be root to do that!\n");
		}
		$fstab = egw_vfs::mount($url,$path=$argv[1]);
		if (is_array($fstab))
		{
			foreach($fstab as $path => $url)
			{
				echo "$url\t$path\n";
			}
		}
		elseif ($fstab === false)
		{
			echo "URL '$url' not found or permission denied (are your root?)!\n";
		}
		else
		{
			echo "$url successful mounted to $path\n";
		}
		break;

	case 'eacl':
		do_eacl($argv);
		break;

	case 'find':
		do_find($argv,$find_options);
		break;

	case 'cp':
		do_cp($argv,$recursive,$perms);
		break;

	case 'rename':
		if (count($argv) != 2) usage(null,3);
		load_wrapper($argv[0]);
		load_wrapper($argv[1]);
		rename($argv[0],$argv[1]);
		break;

	case 'migrate-db2fs':
		if (empty($user) || empty($passwd) || !egw_vfs::$is_root)
		{
			die("\nYou need to be root to do that!\n\n");
		}
		if (!is_writable($GLOBALS['egw_info']['server']['files_dir'])) exit;	// we need write access, error msg already given
		$fstab = egw_vfs::mount();
		if (!is_array($fstab) || !isset($fstab['/']) || strpos($fstab['/'],'storage=db') === false)
		{
			foreach($fstab as $path => $url)
			{
				echo "$url\t$path\n";
			}
			die("\n/ NOT mounted with 'storage=db' --> no need to convert!\n\n");
		}
		$num_files = sqlfs_stream_wrapper::migrate_db2fs();	// throws exception on error
		echo "\n$num_files files migrated from DB to filesystem.\n";
		$new_url = preg_replace('/storage=db&?/','',$fstab['/']);
		if (substr($new_url,-1) == '?') $new_url = substr($new_url,0,-1);
		if (egw_vfs::mount($new_url,'/'))
		{
			echo "/ successful re-mounted on $new_url\n";
		}
		else
		{
			echo "\nre-mounting $new_url on / failed!\n\n";
		}
		break;

	default:
		while($argv)
		{
			$url = array_shift($argv);

			load_wrapper($url);
			echo "$cmd $url (long=".(int)$long.", numeric=".(int)$numeric.", recursive=".(int)$recursive.")\n";

			switch($cmd)
			{
				case 'rm':
					if ($recursive)
					{
						if (!class_exists('egw_vfs'))
						{
							die("rm -r only implemented for eGW streams!");	// dont want to repeat the code here
						}
						array_unshift($argv,$url);
						egw_vfs::remove($argv,true);
						$argv = array();
					}
					else
					{
						unlink($url);
					}
					break;

				case 'rmdir':
					rmdir($url);
					break;

				case 'mkdir':
					if (!mkdir($url,null,$recursive)) echo "Can't create directory, permission denied!\n";
					break;

				case 'touch':
				case 'chmod':
				case 'chown':
				case 'chgrp':
					switch($cmd)
					{
						case 'touch':
							$params = array($url,$time);
							break;
						case 'chmod':
							if (!isset($mode))
							{
								$mode = $url;	// first param is mode
								$url = array_shift($argv);
							}
							if (parse_url($url,PHP_URL_SCHEME)) load_wrapper($url);	// cant use stat or egw_vfs::mode2int otherwise!

							if (strpos($mode,'+') !== false || strpos($mode,'-') !== false)
							{
								$stat = stat($url);
								$set = $stat['mode'];
							}
							else
							{
								$set = 0;
							}
							if (!class_exists('egw_vfs'))
							{
								die("chmod only implemented for eGW streams!");	// dont want to repeat the code here
							}
							$set = egw_vfs::mode2int($mode,$set);
							$params = array($url,$set);
							break;
						case 'chown':
						case 'chgrp':
							$type = $cmd == 'chgrp' ? 'group' : 'user';
							if (!isset($owner))
							{
								$owner = $url;	// first param is owner/group
								$url = array_shift($argv);
								if (parse_url($url,PHP_URL_SCHEME)) load_wrapper($url);	// we need the header loaded
								if ($owner == 'root')
								{
									$owner = 0;
								}
								elseif (!is_numeric($owner))
								{
									if (!is_object($GLOBALS['egw']))
									{
										die("only numeric user/group-id's allowed for non eGW streams!");
									}
									if (!($owner = $GLOBALS['egw']->accounts->name2id($owner_was=$owner,'account_lid',$type[0])) ||
										($owner < 0) != ($cmd == 'chgrp'))
									{
										die("Unknown $type '$owner_was'!");
									}
								}
								elseif($owner && is_object($GLOBALS['egw']) && (!$GLOBALS['egw']->accounts->id2name($owner) ||
										($owner < 0) != ($cmd == 'chgrp')))
								{
									die("Unknown $type '$owner_was'!");
								}
							}
							$params = array($url,$owner);
							break;
					}
					if (($scheme = parse_url($url,PHP_URL_SCHEME)))
					{
						load_wrapper($url);
					}
					if ($recursive && class_exists('egw_vfs'))
					{
						array_unshift($argv,$url);
						$params = array($argv,null,$cmd,$params[1]);
						$cmd = array('egw_vfs','find');
						$argv = array();	// we processed all url's
					}
					//echo "calling cmd=".print_r($cmd,true).", params=".print_r($params,true)."\n";
					call_user_func_array($cmd,$params);
					break;

				case 'cat':
				case 'ls':
				default:
					// recursive ls atm only for vfs://
					if ($cmd != 'cat' && $recursive && class_exists('egw_vfs'))
					{
						load_wrapper($url);
						array_unshift($argv,$url);
						egw_vfs::find($argv,array('url'=>true,),'do_stat',array($long,$numeric,true,$inode));
						$argv = array();
					}
					elseif (is_dir($url) && ($dir = opendir($url)))
					{
						if ($argc)
						{
							if (!($name = basename(parse_url($url,PHP_URL_PATH)))) $name = '/';
							echo "\n$name:\n";
						}
						// separate evtl. query part, to re-add it after the file-name
						$query = '';
						list($url,$query) = explode('?',$url,2);
						if ($query) $query = '?'.$query;

						if (substr($url,-1) == '/')
						{
							$url = substr($url,0,-1);
						}
						while(($file = readdir($dir)) !== false)
						{
							do_stat($url.'/'.$file.$query,$long,$numeric,false,$inode);
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
						do_stat($url,$long,$numeric,false,$inode);
					}
					if (!$long && $cmd == 'ls') echo "\n";
					break;
			}
		}
}

/**
 * Load the necessary wrapper for an url or die if it cant be loaded
 *
 * @param string $url
 */
function load_wrapper($url)
{
	$scheme = parse_url($url,PHP_URL_SCHEME);

	if (!in_array($scheme,stream_get_wrappers()))
	{
		switch($scheme)
		{
			case 'webdav':
			case 'webdavs':
				require_once('HTTP/WebDAV/Client.php');
				break;
			case '':	// default scheme is file and alsways available
				break;
			default:
				if (!isset($GLOBALS['egw']) && !in_array($scheme,array('smb','imap')))
				{
					load_egw(parse_url($url,PHP_URL_USER),parse_url($url,PHP_URL_PASS),parse_url($url,PHP_URL_HOST));
				}
				// get eGW's __autoload() function
				include_once(EGW_API_INC.'/common_functions.inc.php');

				if (!class_exists(str_replace('.','_',$scheme).'_stream_wrapper'))
				{
					die("Unknown scheme '$scheme' in $url !!!\n\n");
				}
				break;
		}
	}
}

/**
 * Start the eGW session, exits on wrong credintials
 *
 * @param string $user
 * @param string $passwd
 * @param string $domain
 */
function load_egw($user,$passwd,$domain='default')
{
	//echo "load_egw($user,$passwd,$domain)\n";
	$_REQUEST['domain'] = $domain;
	$GLOBALS['egw_login_data'] = array(
		'login'  => $user,
		'passwd' => $passwd,
		'passwd_type' => 'text',
	);

	if (ini_get('session.save_handler') == 'files' && !is_writable(ini_get('session.save_path')) && is_dir('/tmp') && is_writable('/tmp'))
	{
		ini_set('session.save_path','/tmp');	// regular users may have no rights to apache's session dir
	}

	$GLOBALS['egw_info'] = array(
		'flags' => array(
			'currentapp' => 'filemanager',
			'noheader' => true,
			'autocreate_session_callback' => 'user_pass_from_argv',
			'no_exception_handler' => 'cli',
		)
	);

	if (substr($user,0,5) != 'root_')
	{
		include('../header.inc.php');
	}
	else
	{
		$GLOBALS['egw_info']['flags']['currentapp'] = 'login';
		include('../header.inc.php');

		if ($user == 'root_'.$GLOBALS['egw_info']['server']['header_admin_user'] &&
			_check_pw($GLOBALS['egw_info']['server']['header_admin_password'],$passwd) ||
			$user == 'root_'.$GLOBALS['egw_domain'][$_GET['domain']]['config_user'] &&
			_check_pw($GLOBALS['egw_domain'][$_GET['domain']]['config_passwd'],$passwd))
		{
			echo "\nRoot access granted!\n";
			egw_vfs::$is_root = true;
		}
		else
		{
			die("Unknown user or password!\n");
		}
	}

	$cmd = $GLOBALS['cmd'];
	if (!in_array($cmd,array('ls','find','mount','umount','eacl')) && $GLOBALS['egw_info']['server']['files_dir'] && !is_writable($GLOBALS['egw_info']['server']['files_dir']))
	{
		echo "\nError: eGroupWare's files directory {$GLOBALS['egw_info']['server']['files_dir']} is NOT writable by the user running ".basename(__FILE__)."!\n".
			"--> Please run it as the same user the webserver uses or root, otherwise the $cmd command will fail!\n\n";
	}
}

/**
 * Check password against a md5 hash or cleartext password
 *
 * @param string $hash_or_cleartext
 * @param string $pw
 * @return boolean
 */
function _check_pw($hash_or_cleartext,$pw)
{
	//echo "_check_pw($hash_or_cleartext,$pw) md5=".md5($pw)."\n";
	if (preg_match('/^[0-9a-f]{32}$/',$hash_or_cleartext))
	{
		return $hash_or_cleartext == md5($pw);
	}
	return $hash_or_cleartext == $pw;
}

/**
 * Set, delete or show the extended acl for a given path
 *
 * @param array $argv
 */
function do_eacl(array $argv)
{
	$argc = count($argv);

	if ($argc < 1 || $argc > 3)
	{
		usage();
	}
	load_wrapper($url = $argv[0]);
	if (!class_exists('egw_vfs'))
	{
		die('eacl only implemented for eGW streams!');
	}
	if (!file_exists($url))
	{
		die("$url: no such file our directory!\n");
	}
	if ($argc == 1)
	{
		foreach(egw_vfs::get_eacl($url) as $acl)
		{
			$mode = ($acl['rights'] & egw_vfs::READABLE ? 'r' : '-').
				($acl['rights'] & egw_vfs::WRITABLE ? 'w' : '-').
				($acl['rights'] & egw_vfs::EXECUTABLE ? 'x' : '-');
			echo $acl['path']."\t$mode\t".$GLOBALS['egw']->accounts->id2name($acl['owner'])."\n";
		}
		return;
	}
	if ($argc > 1 && !is_numeric($argv[1]))
	{
		$mode=$argv[1];
		$argv[1] = null;
		for($i = 0; $mode[$i]; ++$i)
		{
			switch($mode[$i])
			{
				case 'x': $argv[1] |= egw_vfs::EXECUTABLE; break;
				case 'w': $argv[1] |= egw_vfs::WRITABLE; break;
				case 'r': $argv[1] |= egw_vfs::READABLE; break;
			}
		}
	}
	if (!egw_vfs::eacl($url,$argv[1],$argc > 2 && !is_numeric($argv[2]) ? $GLOBALS['egw']->accounts->name2id($argv[2]) : $argv[2]))
	{
		echo "Error setting extended acl for $argv[0]!\n";
	}
}

/**
 * Give the stats for one file
 *
 * @param string $url
 * @param boolean $long=false true=long listing with owner,group,size,perms, default false only filename
 * @param boolean $numeric=false true=give numeric uid&gid, else resolve the id to a name
 * @param boolean $full_path=false true=give full path instead of just filename
 * @param boolean $inode=false true=display inode (sqlfs id)
 */
function do_stat($url,$long=false,$numeric=false,$full_path=false,$inode=false)
{
	//echo "do_stat($url,$long,$numeric,$full_path)\n";
	$bname = parse_url($url,PHP_URL_PATH);

	if (!$full_path)
	{
		$bname = basename($bname);
	}
	if (!($stat = @lstat($url)))
	{
		echo "$bname: no such file or directory!\n";
	}
	elseif ($long)
	{
		//echo $url; print_r($stat);

		if (class_exists('egw_vfs'))
		{
			$perms = egw_vfs::int2mode($stat['mode']);
		}
		else
		{
			$perms = int2mode($stat['mode']);
		}
		if ($numeric)
		{
			$uid = $stat['uid'];
			$gid = $stat['gid'];
		}
		else
		{
			if ($stat['uid'])
			{
				$uid = isset($GLOBALS['egw']) ? $GLOBALS['egw']->accounts->id2name($stat['uid']) :
					(function_exists('posix_getpwuid') ? posix_getpwuid($stat['uid']) : $stat['uid']);
				if (is_array($uid)) $uid = $uid['name'];
				if (empty($uid)) $uid = $stat['uid'];
			}
			if (!isset($uid)) $uid = 'root';
			if ($stat['gid'])
			{
				$gid = isset($GLOBALS['egw']) ? $GLOBALS['egw']->accounts->id2name(-abs($stat['gid'])) :
					(function_exists('posix_getgrgid') ? posix_getgrgid($stat['gid']) : $stat['gid']);
				if (is_array($gid)) $gid = $gid['name'];
				if (empty($gid)) $gid = $stat['gid'];
			}
			if (!isset($gid)) $gid = 'root';
		}
		$size = hsize($stat['size']);
		$mtime = date('Y-m-d H:i:s',$stat['mtime']);
		$nlink = $stat['nlink'];
		if (($stat['mode'] & 0xA000) == 0xA000)
		{
			$symlink = " -> ".(class_exists('egw_vfs') ? egw_vfs::readlink($url) : readlink($url));
		}
		if ($inode)
		{
			echo $stat['ino']."\t";
		}
		echo "$perms $nlink\t$uid\t$gid\t$size\t$mtime\t$bname$symlink\n";
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


function do_cp($argv,$recursive=false,$perms=false)
{
	$to = array_pop($argv);
	load_wrapper($to);

	$to_exists = file_exists($to);

	if (count($argv) > 1 && $to_exists && !is_dir($to))
	{
		usage(null,4);
	}
	foreach($argv as $from)
	{
		if (is_dir($from) && (!file_exists($to) || is_dir($to)) && $recursive && class_exists('egw_vfs'))
		{
			foreach(egw_vfs::find($from,array('url' => true)) as $f)
			{
				$t = $to.substr($f,strlen($from));
				if (is_dir($f))
				{
					++$anz_dirs;
					mkdir($t);
				}
				else
				{
					++$anz_files;
					_cp($f,$t);
				}
				if ($perms) _cp_perms($f,$t);
			}
			echo ($anz_dirs?"$anz_dirs dir(s) created and ":'')."$anz_files file(s) copied.\n";
		}
		else
		{
			_cp($from,$to,true);
			if ($perms) _cp_perms($from,$to);
		}
	}
}

function _cp($from,$to,$verbose=false,$perms=false)
{
	load_wrapper($from);

	if (is_dir($to) || !file_exists($to) && is_dir($from) && $mkdir)
	{
		$path = parse_url($from,PHP_URL_PATH);
		if (is_dir($to))
		{
			list($to,$query) = explode('?',$to,2);
			$to .= '/'.basename($path).($query ? '?'.$query : '');
		}
	}
	if (!($from_fp = fopen($from,'r')))
	{
		die("File $from not found!\n");
	}
	if (!($to_fp = fopen($to,'w')))
	{
		die("Can't open $to for writing!\n");
	}
	//stream_filter_append($from_fp,'convert.base64-decode');
	$count = stream_copy_to_stream($from_fp,$to_fp);

	if ($verbose) echo hsize($count)." bytes written to $to\n";

	fclose($from_fp);

	if (!fclose($to_fp))
	{
		die("Error closing $to!\n");
	}
}


function _cp_perms($from,$to)
{
	if (($from_stat = stat($from)) && ($to_stat = stat($to)))
	{
		foreach(array(
			'mode' => 'chmod',
			'uid'  => 'chown',
			'gid'  => 'chgrp',
		) as $perm => $cmd)
		{
			if ($from_stat[$perm] != $to_stat[$perm])
			{
				//echo "egw_vfs::$cmd($to,{$from_stat[$perm]}\n";
				call_user_func(array('egw_vfs',$cmd),$to,$from_stat[$perm]);
			}
		}
	}
}

function do_find($bases,$options)
{
	foreach($bases as $url)
	{
		load_wrapper($url);
	}
	$options['url'] = true;	// we use url's not vfs pathes in filemanager/cli.php

	foreach(egw_vfs::find($bases,$options) as $path)
	{
		echo "$path\n";
	}
}

/**
 * Convert a numerical mode to a symbolic mode-string
 *
 * @param int $mode
 * @return string
 */
function int2mode( $mode )
{
	if(($mode & 0xA000) == 0xA000) // Symbolic Link
	{
		$sP = 'l';
	}
	elseif(($mode & 0xC000) == 0xC000) // Socket
	{
		$sP = 's';
	}
	elseif($mode & 0x1000)     // FIFO pipe
	{
		$sP = 'p';
	}
	elseif($mode & 0x2000) // Character special
	{
		$sP = 'c';
	}
	elseif($mode & 0x4000) // Directory
	{
		$sP = 'd';
	}
	elseif($mode & 0x6000) // Block special
	{
		$sP = 'b';
	}
	elseif($mode & 0x8000) // Regular
	{
		$sP = '-';
	}
	else                         // UNKNOWN
	{
		$sP = 'u';
	}

	// owner
	$sP .= (($mode & 0x0100) ? 'r' : '-') .
	(($mode & 0x0080) ? 'w' : '-') .
	(($mode & 0x0040) ? (($mode & 0x0800) ? 's' : 'x' ) :
	(($mode & 0x0800) ? 'S' : '-'));

	// group
	$sP .= (($mode & 0x0020) ? 'r' : '-') .
	(($mode & 0x0010) ? 'w' : '-') .
	(($mode & 0x0008) ? (($mode & 0x0400) ? 's' : 'x' ) :
	(($mode & 0x0400) ? 'S' : '-'));

	// world
	$sP .= (($mode & 0x0004) ? 'r' : '-') .
	(($mode & 0x0002) ? 'w' : '-') .
	(($mode & 0x0001) ? (($mode & 0x0200) ? 't' : 'x' ) :
	(($mode & 0x0200) ? 'T' : '-'));

	return $sP;
}
