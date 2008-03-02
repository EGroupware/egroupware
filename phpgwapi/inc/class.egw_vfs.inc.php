<?php
/**
 * eGroupWare API: VFS - static methods to use the new eGW virtual file system
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage vfs
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2008 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @version $Id$
 */

/**
 * Class containing static methods to use the new eGW virtual file system
 *
 * This extension of the vfs stream-wrapper allows to use the following static functions, 
 * which only allow access to the eGW VFS and need no 'vfs://default' prefix for filenames:
 * 
 * - resource egw_vfs::fopen($path,$mode) like fopen, returned resource can be used with fwrite etc.
 * - resource egw_vfs::opendir($path) like opendir, returned resource can be used with readdir etc.
 * - boolean egw_vfs::copy($from,$to) like copy
 * - boolean egw_vfs::rename($old,$new) renaming or moving a file in the vfs
 * - boolean egw_vfs::mkdir($path) creating a new dir in the vfs
 * - boolean egw_vfs::rmdir($path) removing (an empty) directory
 * - boolean egw_vfs::unlink($path) removing a file
 * - boolean egw_vfs::touch($path,$mtime=null) touch a file
 * - boolean egw_vfs::stat($path) returning status of file like stat(), but only with string keys (no numerical indexes)! 
 * 
 * With the exception of egw_vfs::touch() (not yet part of the stream_wrapper interface) 
 * you can always use the standard php functions, if you add a 'vfs://default' prefix
 * to every filename or path. Be sure to always add the prefix, as the user otherwise gains
 * access to the real filesystem of the server!
 * 
 * The two following methods can be used to persitently mount further filesystems (without editing the code):
 * 
 * - boolean/array egw_vfs::mount($url,$path) to mount $ur on $path or to return the fstab when called without argument
 * - boolean egw_vfs::umount($path) to unmount a path or url
 * 
 * The stream wrapper interface allows to access hugh files in junks to not be limited by the 
 * memory_limit setting of php. To do you should path a resource to the opened file and not the content:
 * 
 * 		$file = egw_vfs::fopen('/home/user/somefile','r');
 * 		$content = fread($file,1024);
 * 
 * You can also attach stream filters, to eg. base64 encode or compress it on the fly, 
 * without the need to hold the content of the whole file in memmory. 
 * 
 * If you want to copy a file, you can use stream_copy_to_stream to do a copy of a file far bigger then 
 * php's memory_limit:
 * 
 * 		$from = egw_vfs::fopen('/home/user/fromfile','r');
 * 		$to = egw_vfs::fopen('/home/user/tofile','w');
 * 
 * 		stream_copy_to_stream($from,$to);
 * 
 * The static egw_vfs::copy() method does exactly that, but you have to do it eg. on your own, if
 * you want to copy eg. an uploaded file into the vfs.
 */
class egw_vfs extends vfs_stream_wrapper
{
	const PREFIX = 'vfs://default';
	/**
	 * Readable bit, for dirs traversable
	 */
	const READABLE = 4;
	/**
	 * Writable bit, for dirs delete or create files in that dir
	 */
	const WRITABLE = 2;
	/**
	 * Excecutable bit, here only use to check if user is allowed to search dirs
	 */
	const EXECUTABLE = 1;
	/**
	 * Current user has root rights, no access checks performed!
	 *
	 * @var boolean
	 */
	static $is_root = false;
	/**
	 * Current user id, in case we ever change if away from $GLOBALS['egw_info']['user']['account_id']
	 *
	 * @var int
	 */
	static $user;

	/**
	 * fopen working on just the eGW VFS
	 *
	 * @param string $path filename with absolute path in the eGW VFS
	 * @param string $mode 'r', 'w', ... like fopen
	 * @return resource
	 */
	static function fopen($path,$mode)
	{
		if ($path[0] != '/')
		{
			throw new egw_exception_assertion_failed("Filename '$path' is not an absolute path!");
		}
		return fopen(self::SCHEME.'://default'.$path,$mode);
	}
	
	/**
	 * opendir working on just the eGW VFS
	 *
	 * @param string $path filename with absolute path in the eGW VFS
	 * @return resource
	 */
	static function opendir($path)
	{
		if ($path[0] != '/')
		{
			throw new egw_exception_assertion_failed("Directory '$path' is not an absolute path!");
		}
		return opendir(self::SCHEME.'://default'.$path);
	}

	/**
	 * copy working on just the eGW VFS
	 *
	 * @param string $from
	 * @param string $to
	 * @return boolean
	 */
	static function copy($from,$to)
	{
		$ret = false;

		if (($from_fp = self::fopen($from,'r')) && 
			($to_fp = self::fopen($to,'w')))
		{
			$ret = stream_copy_to_stream($from_fp,$to_fp) !== false;
		}
 		if ($from_fp)
 		{
 			fclose($from_fp);
 		}
 		if ($to_fp)
 		{
 			fclose($to_fp);
 		}
 		return $ret;		
	}

	/**
	 * stat working on just the eGW VFS (alias of url_stat)
	 *
	 * @param string $path filename with absolute path in the eGW VFS
	 * @return resource
	 */
	static function stat($path)
	{
		if ($path[0] != '/')
		{
			throw new egw_exception_assertion_failed("File '$path' is not an absolute path!");
		}
		if (($stat = self::url_stat($path,0)))
		{
			$stat = array_slice($stat,13);	// remove numerical indices 0-12
		}
		return $stat;
	}
	
	/**
	 * is_dir() version working only inside the vfs
	 *
	 * @param string $path
	 * @return boolean
	 */
	static function is_dir($path)
	{
		return $path[0] == '/' && is_dir(self::SCHEME.'://default'.$path);
	}
	
	
	/**
	 * Mounts $url under $path in the vfs, called without parameter it returns the fstab
	 * 
	 * The fstab is stored in the eGW configuration and used for all eGW users.
	 *
	 * @param string $url=null url of the filesystem to mount, eg. oldvfs://default/
	 * @param string $path=null path to mount the filesystem in the vfs, eg. /
	 * @return array/boolean array with fstab, if called without parameter or true on successful mount
	 */
	static function mount($url=null,$path=null)
	{
		if (is_null($url) || is_null($path))
		{
			return self::$fstab;
		}
		if (!self::$is_root)
		{
			return false;	// only root can mount
		}
		if (isset(self::$fstab[$path]))
		{
			return true;	// already mounted
		}
		if (stat($url) === false && opendir($url) === false)
		{
			return false;	// url does not exist
		}
		self::$fstab[$path] = $url;
		
		uksort(self::$fstab,create_function('$a,$b','return strlen($a)-strlen($b);'));
		
		config::save_value('vfs_fstab',self::$fstab,'phpgwapi');
		$GLOBALS['egw_info']['server']['vfs_fstab'] = self::$fstab;
		
		return true;
	}
	
	/**
	 * Unmounts a filesystem part of the vfs
	 *
	 * @param string $path url or path of the filesystem to unmount
	 */
	static function umount($path)
	{
		if (!self::$is_root)
		{
			return false;	// only root can mount
		}
		if (!isset(self::$fstab[$path]) && ($path = array_search($path,self::$fstab)) === false)
		{
			return false;	// $path not mounted
		}
		unset(self::$fstab[$path]);

		config::save_value('vfs_fstab',self::$fstab,'phpgwapi');
		$GLOBALS['egw_info']['server']['vfs_fstab'] = self::$fstab;
		
		return true;
	}
	
	/**
	 * find = recursive search over the filesystem
	 *
	 * @param string/array $base base of the search
	 * @param array $options=null the following keys are allowed:
	 * - type => {d|f} d=dirs, f=files, default both
	 * - depth => {true|false(default)} put the contents of a dir before the dir itself
	 * - mindepth,maxdepth minimal or maximal depth to be returned
	 * - name,path => pattern with *,? wildcards, eg. "*.php"
	 * - name_preg,path_preg => preg regular expresion, eg. "/(vfs|wrapper)/"
	 * - uid,user,gid,group,nouser,nogroup file belongs to user/group with given name or (numerical) id
	 * - mime => type[/subtype]
	 * - empty,size => (+|-|)N
	 * - cmin/mmin => (+|-|)N file/dir create/modified in the last N minutes
	 * - ctime/mtime => (+|-|)N file/dir created/modified in the last N days
	 * - depth => (+|-)N
	 * - url => false(default),true allow (and return) full URL's instead of VFS pathes (only set it, if you know what you doing securitywise!)
	 * @param string/array/true $exec=null function to call with each found file or dir as first param or 
	 * 	true to return file => stat pairs
	 * @param array $exec_params=null further params for exec as array, path is always the first param!
	 * @return array of pathes if no $exec, otherwise path => stat pairs
	 */
	static function find($base,$options=null,$exec=null,$exec_params=null)
	{
		//error_log(__METHOD__."(".print_r($base,true).",".print_r($options,true).",".print_r($exec,true).",".print_r($exec_params,true).")\n");

		$type = $options['type'];	// 'd' or 'f'
		$dirs_last = $options['depth'];	// put content of dirs before the dir itself
		
		// process some of the options (need to be done only once)
		if (isset($options['name']) && !isset($options['name_preg']))	// change from simple *,? wildcards to preg regular expression once
		{
			$options['name_preg'] = '/^'.str_replace(array('\\?','\\*'),array('.{1}','.*'),preg_quote($options['name'])).'$/';
		}
		if (isset($options['path']) && !isset($options['preg_path']))	// change from simple *,? wildcards to preg regular expression once
		{
			$options['path_preg'] = '/^'.str_replace(array('\\?','\\*'),array('.{1}','.*'),preg_quote($options['path'])).'$/';
		}
		if (!isset($options['uid']))
		{
			if (isset($options['user']))
			{
				$options['uid'] = $GLOBALS['egw']->accounts->name2id($options['user'],'account_lid','u');
			}
			elseif (isset($options['nouser']))
			{
				$options['uid'] = 0;
			}
		}
		if (!isset($options['gid']))
		{
			if (isset($options['group']))
			{
				$options['gid'] = abs($GLOBALS['egw']->accounts->name2id($options['group'],'account_lid','g'));
			}
			elseif (isset($options['nogroup']))
			{
				$options['gid'] = 0;
			}
		}
		$url = $options['url'];

		if (!is_array($base))
		{
			$base = array($base);
		}
		foreach($base as $path)
		{
			// check our fstab if we need to add some of the mountpoints
			$basepath = parse_url($path,PHP_URL_PATH);
			foreach(self::$fstab as $mounted => $src_url)
			{
				if (dirname($mounted) == $basepath)
				{
					$base[] = $mounted;
				}
			}
		}
		$result = array();
		foreach($base as $path)
		{
			if (!$url) $path = egw_vfs::PREFIX . $path;

			$is_dir = is_dir($path);
			
			if ((int)$options['mindepth'] == 0 && (!$dirs_last || !$is_dir))
			{
				self::_check_add($options,$path,$result);
			}
			if ($is_dir && (!isset($options['maxdepth']) || $options['maxdepth'] > 0) && ($dir = @opendir($path)))
			{
				while($file = readdir($dir))
				{
					$file = $path.'/'.$file;
					
					if ((int)$options['mindepth'] <= 1)
					{
						self::_check_add($options,$file,$result,1);
					}
					if (is_dir($file) && (!isset($options['maxdepth']) || $options['maxdepth'] > 1))
					{
						$opts = $options;
						if ($opts['mindepth']) $opts['mindepth']--;
						if ($opts['maxdepth']) $opts['maxdepth']++;
						foreach(self::find($options['url']?$file:parse_url($file,PHP_URL_PATH),$opts,true) as $p => $s)
						{
							unset($result[$p]);
							$result[$p] = $s;
						}
					}
				}
				closedir($dir);
			}
			if ($is_dir && (int)$options['mindepth'] == 0 && $dirs_last)
			{
				self::_check_add($options,$path,$result);
			}
		}
		//echo $path; _debug_array($result);
		if ($exec !== true && is_callable($exec))
		{
			if (!is_array($exec_params))
			{
				$exec_params = is_null($exec_params) ? array() : array($exec_params);
			}
			foreach($result as $path => &$stat)
			{
				$options = $exec_params;
				array_unshift($options,$path);
				//echo "calling ".print_r($exec,true).print_r($options,true)."\n";
				$stat = call_user_func_array($exec,$options);
			}
			return $result;
		}
		//echo "egw_vfs::find($path)="; _debug_array(array_keys($result));
		if ($exec !== true)
		{
			return array_keys($result);
		}
		return $result;
	}
	
	/**
	 * Function carying out the various (optional) checks, before files&dirs get returned as result of find
	 *
	 * @param array $options options, see egw_vfs::find(,$options)
	 * @param string $path name of path to add
	 * @param array &$result here we add the stat for the key $path, if the checks are successful
	 */
	private static function _check_add($options,$path,&$result)
	{
		$type = $options['type'];	// 'd' or 'f'
		
		if ($type && ($type == 'd') !== is_dir($path))
		{
			return;	// wrong type
		}
		if (!($stat = self::url_stat($path,0)))
		{
			return;	// not found, should not happen
		}
		if (isset($options['name_preg']) && !preg_match($options['name_preg'],self::basename($path)) ||
			isset($options['path_preg']) && !preg_match($options['path_preg'],$path))
		{
			return;	// wrong name or path
		}
		if (isset($options['gid']) && $stat['gid'] != $options['gid'] ||
			isset($options['uid']) && $stat['uid'] != $options['uid'])
		{
			return;	// wrong user or group
		}
		if (isset($options['mime']) && $options['mime'] != ($mime = self::mime_content_type($path)))
		{
			list($type,$subtype) = explode('/',$options['mime']);
			// no subtype (eg. 'image') --> check only the main type
			if ($sub_type || substr($mime,0,strlen($type)+1) != $type.'/')
			{
				return;	// wrong mime-type
			}
		}
		if (isset($options['size']) && !self::_check_num($stat['size'],$options['size']) ||
			(isset($options['empty']) && !!$options['empty'] !== !$stat['size']))
		{
			return;	// wrong size
		}
		if (isset($options['cmin']) && !self::_check_num(round((time()-$stat['ctime'])/60),$options['cmin']) ||
			isset($options['mmin']) && !self::_check_num(round((time()-$stat['mtime'])/60),$options['mmin']) ||
			isset($options['ctime']) && !self::_check_num(round((time()-$stat['ctime'])/86400),$options['ctime']) ||
			isset($options['mtime']) && !self::_check_num(round((time()-$stat['ctime'])/86400),$options['mtime']))
		{
			return;	// not create/modified in the spezified time
		}
		// do we return url or just vfs pathes
		if (!$options['url'])
		{
			$path = parse_url($path,PHP_URL_PATH);
		}
		$result[$path] = $stat;
	}
	
	private static function _check_num($value,$argument)
	{
		if (is_int($argument) && $argument >= 0 || $argument[0] != '-' && $argument[0] != '+')
		{
			//echo "_check_num($value,$argument) check = == ".(int)($value == $argument)."\n";
			return $value == $argument;
		}
		if ($argument < 0)
		{
			//echo "_check_num($value,$argument) check < == ".(int)($value < abs($argument))."\n";
			return $value < abs($argument);
		}
		//echo "_check_num($value,$argument) check > == ".(int)($value > (int)substr($argument,1))."\n";
		return $value > (int) substr($argument,1);
	}
	
	/**
	 * Recursiv remove all given url's, including it's content if they are files
	 *
	 * @param string/array $urls url or array of url's
	 * @return array
	 */
	static function remove($urls)
	{
		return self::find($urls,array('depth'=>true),array(__CLASS__,'_rm_rmdir'));
	}
	
	/**
	 * Helper function for remove: either rmdir or unlink given url (depending if it's a dir or file)
	 *
	 * @param string $url
	 * @return boolean
	 */
	static function _rm_rmdir($url)
	{
		if (is_dir($url))
		{
			return rmdir($url);
		}
		return unlink($url);
	}

	/**
	 * The stream_wrapper interface checks is_{readable|writable|executable} against the webservers uid,
	 * which is wrong in case of our vfs, as we use the current users id and memberships
	 *
	 * @param string $path
	 * @param int $check=4 mode to check: 4 = read, 2 = write, 1 = executable
	 * @return boolean
	 */
	static function is_readable($path,$check = 4)
	{
		if (!($stat = self::stat($path)))
		{
			return false;
		}
		return self::check_access($stat,$check);
	}

	/**
	 * The stream_wrapper interface checks is_{readable|writable|executable} against the webservers uid,
	 * which is wrong in case of our vfs, as we use the current users id and memberships
	 *
	 * @param array $stat
	 * @param int $check mode to check: one or more or'ed together of: 4 = read, 2 = write, 1 = executable
	 * @return boolean
	 */
	static function check_access($stat,$check)
	{
		//error_log(__METHOD__."(stat[name]={$stat['name']},stat[mode]=".sprintf('%o',$stat['mode']).",$check)");
		
		if (self::$is_root)
		{
			return true;
		}

		if (!$stat)
		{
			//error_log(__METHOD__."(stat[name]={$stat['name']},stat[mode]=".sprintf('%o',$stat['mode']).",$check) no stat array!");
			return false;	// file not found
		}
		// check if other rights grant access
		if (($stat['mode'] & $check) == $check)
		{
			//error_log(__METHOD__."(stat[name]={$stat['name']},stat[mode]=".sprintf('%o',$stat['mode']).",$check) access via other rights!");
			return true;
		}
		// check if there's owner access and we are the owner
		if (($stat['mode'] & ($check << 6)) == ($check << 6) && $stat['uid'] && $stat['uid'] == self::$user)
		{
			//error_log(__METHOD__."(stat[name]={$stat['name']},stat[mode]=".sprintf('%o',$stat['mode']).",$check) access via owner rights!");
			return true;
		}
		// check if there's a group access and we have the right membership
		if (($stat['mode'] & ($check << 3)) == ($check << 3) && $stat['gid'])
		{
			static $memberships;
			if (is_null($memberships))
			{
				$memberships = $GLOBALS['egw']->accounts->memberships(self::$user,true);
			}
			if (in_array(-abs($stat['gid']),$memberships))
			{
				//error_log(__METHOD__."(stat[name]={$stat['name']},stat[mode]=".sprintf('%o',$stat['mode']).",$check) access via group rights!");
				return true;
			}
		}
		// here could be a check for extended acls ...

		//error_log(__METHOD__."(stat[name]={$stat['name']},stat[mode]=".sprintf('%o',$stat['mode']).",$check) no access!");
		return false;
	}
	
	/**
	 * The stream_wrapper interface checks is_{readable|writable|executable} against the webservers uid,
	 * which is wrong in case of our vfs, as we use the current users id and memberships
	 *
	 * @param string $path
	 * @return boolean
	 */
	static function is_writable($path)
	{
		return self::is_readable($path,2);
	}
	
	/**
	 * The stream_wrapper interface checks is_{readable|writable|executable} against the webservers uid,
	 * which is wrong in case of our vfs, as we use the current users id and memberships
	 *
	 * @param string $path
	 * @return boolean
	 */
	static function is_executable($path)
	{
		return self::is_readable($path,1);
	}
	
	/**
	 * Private constructor to prevent instanciating this class, only it's static methods should be used
	 */
	private function __construct()
	{
		
	}
	
	/**
	 * Convert a symbolic mode string or octal mode to an integer
	 *
	 * @param string/int $set comma separated mode string to set [ugo]+[+=-]+[rwx]+
	 * @param int $mode=0 current mode of the file, necessary for +/- operation
	 * @return int
	 */
	static function mode2int($set,$mode=0)
	{
		if (is_int($set))		// already an integer
		{
			return $set;
		}
		if (is_numeric($set))	// octal string
		{
			//error_log(__METHOD__."($set,$mode) returning ".(int)base_convert($set,8,10));
			return (int)base_convert($set,8,10);	// convert octal to decimal
		}
		foreach(explode(',',$set) as $s)
		{
			if (!preg_match($use='/^([ugoa]*)([+=-]+)([rwx]+)$/',$s,$matches))
			{
				$use = str_replace(array('/','^','$','(',')'),'',$use);
				throw new egw_exception_wrong_userinput("$s is not an allowed mode, use $use !");
			}
			$base = (strpos($matches[3],'r') !== false ? self::READABLE : 0) |
				(strpos($matches[3],'w') !== false ? self::WRITABLE : 0) |
				(strpos($matches[3],'x') !== false ? self::EXECUTABLE : 0);
			
			for($n = $m = 0; $n < strlen($matches[1]); $n++)
			{
				switch($matches[1][$n])
				{
					case 'o':
						$m |= $base;
						break;
					case 'g':
						$m |= $base << 3;
						break;
					case 'u':
						$m |= $base << 6;
						break;
					default:
					case 'a':
						$m = $base | ($base << 3) | ($base << 6);
				}
			}
			switch($matches[2])
			{
				case '+':
					$mode |= $m;
					break;
				case '=':
					$mode = $m;
					break;
				case '-':
					$mode &= ~$m;
			}
		}
		//error_log(__METHOD__."($set,) returning ".sprintf('%o',$mode));
		return $mode;
	}
	
	/**
	 * Convert a numerical mode to a symbolic mode-string
	 *
	 * @param int $mode
	 * @return string
	 */
	static function int2mode( $mode )
	{
		if($mode & 0x1000)     // FIFO pipe
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
		elseif($mode & 0xA000) // Symbolic Link
		{
			$sP = 'l';
		}
		elseif($mode & 0xC000) // Socket
		{
			$sP = 's';
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
	
	/**
	 * Human readable size values in k or M
	 *
	 * @param int $size
	 * @return string
	 */
	static function hsize($size)
	{
		if ($size < 1024) return $size;
		if ($size < 1024*1024) return sprintf('%3.1lfk',(float)$size/1024);
		return sprintf('%3.1lfM',(float)$size/(1024*1024));
	}

	/**
	 * like basename($path), but also working if the 1. char of the basename is non-ascii
	 *
	 * @param string $path
	 * @return string
	 */
	static function basename($path)
	{
		$parts = explode('/',$path);
		
		return array_pop($parts);
	}
}

egw_vfs::$user = (int) $GLOBALS['egw_info']['user']['account_id'];
