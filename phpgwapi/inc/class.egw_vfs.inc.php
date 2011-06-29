<?php
/**
 * eGroupWare API: VFS - static methods to use the new eGW virtual file system
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage vfs
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2008-10 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
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
 * - boolean|array egw_vfs::mount($url,$path) to mount $ur on $path or to return the fstab when called without argument
 * - boolean egw_vfs::umount($path) to unmount a path or url
 *
 * The stream wrapper interface allows to access hugh files in junks to not be limited by the
 * memory_limit setting of php. To do you should pass the opened file as resource and not the content:
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
	 * Name of the lock table
	 */
	const LOCK_TABLE = 'egw_locks';
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
	 * Current user is an eGW admin
	 *
	 * @var boolean
	 */
	static $is_admin = false;
	/**
	 * Total of last find call
	 *
	 * @var int
	 */
	static $find_total;
	/**
	 * Reference to the global db object
	 *
	 * @var egw_db
	 */
	static $db;

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
		return fopen(self::PREFIX.$path,$mode);
	}

	/**
	 * opendir working on just the eGW VFS: returns resource for readdir() etc.
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
		return opendir(self::PREFIX.$path);
	}

	/**
	 * dir working on just the eGW VFS: returns directory object
	 *
	 * @param string $path filename with absolute path in the eGW VFS
	 * @return Directory
	 */
	static function dir($path)
	{
		if ($path[0] != '/')
		{
			throw new egw_exception_assertion_failed("Directory '$path' is not an absolute path!");
		}
		return dir(self::PREFIX.$path);
	}

	/**
	 * scandir working on just the eGW VFS: returns array with filenames as values
	 *
	 * @param string $path filename with absolute path in the eGW VFS
	 * @param int $sorting_order=0 !$sorting_order (default) alphabetical in ascending order, $sorting_order alphabetical in descending order.
	 * @return array
	 */
	static function scandir($path,$sorting_order=0)
	{
		if ($path[0] != '/')
		{
			throw new egw_exception_assertion_failed("Directory '$path' is not an absolute path!");
		}
		return scandir(self::PREFIX.$path,$sorting_order);
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

		$old_props = self::file_exists($to) ? self::propfind($to,null) : array();
		// copy properties (eg. file comment), if there are any and evtl. existing old properties
		$props = self::propfind($from,null);

		foreach($old_props as $prop)
		{
			if (!self::find_prop($props,$prop))
			{
				$prop['val'] = null;	// null = delete prop
				$props[] = $prop;
			}
		}
		// using self::copy_uploaded() to treat copying incl. properties as atomar operation in respect of notifications
		return self::copy_uploaded(self::PREFIX.$from,$to,$props,false);	// false = no is_uploaded_file check!
	}

	/**
	 * Find a specific property in an array of properties (eg. returned by propfind)
	 *
	 * @param array &$props
	 * @param array|string $name property array or name
	 * @param string $ns=self::DEFAULT_PROP_NAMESPACE namespace, only if $prop is no array
	 * @return &array reference to property in $props or null if not found
	 */
	static function &find_prop(array &$props,$name,$ns=self::DEFAULT_PROP_NAMESPACE)
	{
		if (is_array($name))
		{
			$ns = $name['ns'];
			$name = $name['name'];
		}
		foreach($props as &$prop)
		{
			if ($prop['name'] == $name && $prop['ns'] == $ns) return $prop;
		}
		return null;
	}

	/**
	 * stat working on just the eGW VFS (alias of url_stat)
	 *
	 * @param string $path filename with absolute path in the eGW VFS
	 * @param boolean $try_create_home=false should a non-existing home-directory be automatically created
	 * @return array
	 */
	static function stat($path,$try_create_home=false)
	{
		if ($path[0] != '/')
		{
			throw new egw_exception_assertion_failed("File '$path' is not an absolute path!");
		}
		if (($stat = self::url_stat($path,0,$try_create_home)))
		{
			$stat = array_slice($stat,13);	// remove numerical indices 0-12
		}
		return $stat;
	}

	/**
	 * lstat (not resolving symbolic links) working on just the eGW VFS (alias of url_stat)
	 *
	 * @param string $path filename with absolute path in the eGW VFS
	 * @param boolean $try_create_home=false should a non-existing home-directory be automatically created
	 * @return array
	 */
	static function lstat($path,$try_create_home=false)
	{
		if ($path[0] != '/')
		{
			throw new egw_exception_assertion_failed("File '$path' is not an absolute path!");
		}
		if (($stat = self::url_stat($path,STREAM_URL_STAT_LINK,$try_create_home)))
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
		return $path[0] == '/' && is_dir(self::PREFIX.$path);
	}

	/**
	 * is_link() version working only inside the vfs
	 *
	 * @param string $path
	 * @return boolean
	 */
	static function is_link($path)
	{
		return $path[0] == '/' && is_link(self::PREFIX.$path);
	}

	/**
	 * file_exists() version working only inside the vfs
	 *
	 * @param string $path
	 * @return boolean
	 */
	static function file_exists($path)
	{
		return $path[0] == '/' && file_exists(self::PREFIX.$path);
	}

	/**
	 * Mounts $url under $path in the vfs, called without parameter it returns the fstab
	 *
	 * The fstab is stored in the eGW configuration and used for all eGW users.
	 *
	 * @param string $url=null url of the filesystem to mount, eg. oldvfs://default/
	 * @param string $path=null path to mount the filesystem in the vfs, eg. /
	 * @param boolean $check_url=null check if url is an existing directory, before mounting it
	 * 	default null only checks if url does not contain a $ as used in $user or $pass
	 * @return array|boolean array with fstab, if called without parameter or true on successful mount
	 */
	static function mount($url=null,$path=null,$check_url=null)
	{
		if (is_null($check_url)) $check_url = strpos($url,'$') === false;

		if (!isset($GLOBALS['egw_info']['server']['vfs_fstab']))	// happens eg. in setup
		{
			$api_config = config::read('phpgwapi');
			if (isset($api_config['vfs_fstab']) && is_array($api_config['vfs_fstab']))
			{
				self::$fstab = $api_config['vfs_fstab'];
			}
			unset($api_config);
		}
		if (is_null($url) || is_null($path))
		{
			if (self::LOG_LEVEL > 1) error_log(__METHOD__.'('.array2string($url).','.array2string($path).') returns '.array2string(self::$fstab));
			return self::$fstab;
		}
		if (!self::$is_root)
		{
			if (self::LOG_LEVEL > 0) error_log(__METHOD__.'('.array2string($url).','.array2string($path).') permission denied, you are NOT root!');
			return false;	// only root can mount
		}
		if (isset(self::$fstab[$path]) && self::$fstab[$path] === $url)
		{
			if (self::LOG_LEVEL > 0) error_log(__METHOD__.'('.array2string($url).','.array2string($path).') already mounted.');
			return true;	// already mounted
		}
		self::load_wrapper(parse_url($url,PHP_URL_SCHEME));

		if ($check_url && (!file_exists($url) || opendir($url) === false))
		{
			if (self::LOG_LEVEL > 0) error_log(__METHOD__.'('.array2string($url).','.array2string($path).') url does NOT exist!');
			return false;	// url does not exist
		}
		self::$fstab[$path] = $url;

		uksort(self::$fstab,create_function('$a,$b','return strlen($a)-strlen($b);'));

		config::save_value('vfs_fstab',self::$fstab,'phpgwapi');
		$GLOBALS['egw_info']['server']['vfs_fstab'] = self::$fstab;
		// invalidate session cache
		if (method_exists($GLOBALS['egw'],'invalidate_session_cache'))	// egw object in setup is limited
		{
			$GLOBALS['egw']->invalidate_session_cache();
		}
		if (self::LOG_LEVEL > 1) error_log(__METHOD__.'('.array2string($url).','.array2string($path).') returns true (successful new mount).');
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
			if (self::LOG_LEVEL > 0) error_log(__METHOD__.'('.array2string($url).','.array2string($path).') permission denied, you are NOT root!');
			return false;	// only root can mount
		}
		if (!isset(self::$fstab[$path]) && ($path = array_search($path,self::$fstab)) === false)
		{
			if (self::LOG_LEVEL > 0) error_log(__METHOD__.'('.array2string($url).','.array2string($path).') NOT mounted!');
			return false;	// $path not mounted
		}
		unset(self::$fstab[$path]);

		config::save_value('vfs_fstab',self::$fstab,'phpgwapi');
		$GLOBALS['egw_info']['server']['vfs_fstab'] = self::$fstab;
		// invalidate session cache
		if (method_exists($GLOBALS['egw'],'invalidate_session_cache'))	// egw object in setup is limited
		{
			$GLOBALS['egw']->invalidate_session_cache();
		}
		if (self::LOG_LEVEL > 1) error_log(__METHOD__.'('.array2string($url).','.array2string($path).') returns true (successful unmount).');
		return true;
	}

	/**
	 * find = recursive search over the filesystem
	 *
	 * @param string|array $base base of the search
	 * @param array $options=null the following keys are allowed:
	 * - type => {d|f|F} d=dirs, f=files (incl. symlinks), F=files (incl. symlinks to files), default all
	 * - depth => {true|false(default)} put the contents of a dir before the dir itself
	 * - dirsontop => {true(default)|false} allways return dirs before the files (two distinct blocks)
	 * - mindepth,maxdepth minimal or maximal depth to be returned
	 * - name,path => pattern with *,? wildcards, eg. "*.php"
	 * - name_preg,path_preg => preg regular expresion, eg. "/(vfs|wrapper)/"
	 * - uid,user,gid,group,nouser,nogroup file belongs to user/group with given name or (numerical) id
	 * - mime => type[/subtype]
	 * - empty,size => (+|-|)N
	 * - cmin/mmin => (+|-|)N file/dir create/modified in the last N minutes
	 * - ctime/mtime => (+|-|)N file/dir created/modified in the last N days
	 * - url => false(default),true allow (and return) full URL's instead of VFS pathes (only set it, if you know what you doing securitywise!)
	 * - need_mime => false(default),true should we return the mime type
	 * - order => name order rows by name column
	 * - sort => (ASC|DESC) sort, default ASC
	 * - limit => N,[n=0] return N entries from position n on, which defaults to 0
	 * - follow => {true|false(default)} follow symlinks
	 * - hidden => {true|false(default)} include hidden files (name starts with a '.' or is Thumbs.db)
	 * @param string|array/true $exec=null function to call with each found file/dir as first param and stat array as last param or
	 * 	true to return file => stat pairs
	 * @param array $exec_params=null further params for exec as array, path is always the first param and stat the last!
	 * @return array of pathes if no $exec, otherwise path => stat pairs
	 */
	static function find($base,$options=null,$exec=null,$exec_params=null)
	{
		//error_log(__METHOD__."(".print_r($base,true).",".print_r($options,true).",".print_r($exec,true).",".print_r($exec_params,true).")\n");

		$type = $options['type'];	// 'd', 'f' or 'F'
		$dirs_last = $options['depth'];	// put content of dirs before the dir itself
		// show dirs on top by default, if no recursive listing (allways disabled if $type specified, as unnecessary)
		$dirsontop = !$type && (isset($options['dirsontop']) ? (boolean)$options['dirsontop'] : isset($options['maxdepth'])&&$options['maxdepth']>0);
		if ($dirsontop) $options['need_mime'] = true;	// otherwise dirsontop can NOT work

		// process some of the options (need to be done only once)
		if (isset($options['name']) && !isset($options['name_preg']))	// change from simple *,? wildcards to preg regular expression once
		{
			$options['name_preg'] = '/^'.str_replace(array('\\?','\\*'),array('.{1}','.*'),preg_quote($options['name'])).'$/i';
		}
		if (isset($options['path']) && !isset($options['preg_path']))	// change from simple *,? wildcards to preg regular expression once
		{
			$options['path_preg'] = '/^'.str_replace(array('\\?','\\*'),array('.{1}','.*'),preg_quote($options['path'])).'$/i';
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
		if ($options['order'] == 'mime')
		{
			$options['need_mime'] = true;	// we need to return the mime colum
		}
		$url = $options['url'];

		if (!is_array($base))
		{
			$base = array($base);
		}
		$result = array();
		foreach($base as $path)
		{
			if (!$url) $path = egw_vfs::PREFIX . $path;

			if (!isset($options['remove']))
			{
				$options['remove'] = count($base) == 1 ? count(explode('/',$path))-3+(int)(substr($path,-1)!='/') : 0;
			}
			$is_dir = is_dir($path);
			if ((int)$options['mindepth'] == 0 && (!$dirs_last || !$is_dir))
			{
				self::_check_add($options,$path,$result);
			}
			if ($is_dir && (!isset($options['maxdepth']) || $options['maxdepth'] > 0) && ($dir = @opendir($path)))
			{
				while(($file = readdir($dir)) !== false)
				{
					if ($file == '.' || $file == '..') continue;	// ignore current and parent dir!

					if (($file[0] == '.' || $file == 'Thumbs.db') && !$options['hidden']) continue;	// ignore hidden files

					$file = self::concat($path,$file);

					if ((int)$options['mindepth'] <= 1)
					{
						self::_check_add($options,$file,$result);
					}
					// only descend into subdirs, if it's a real dir (no link to a dir) or we should follow symlinks
					if (is_dir($file) && ($options['follow'] || !is_link($file)) && (!isset($options['maxdepth']) || $options['maxdepth'] > 1))
					{
						$opts = $options;
						if ($opts['mindepth']) $opts['mindepth']--;
						if ($opts['maxdepth']) $opts['maxdepth']++;
						unset($opts['order']);
						unset($opts['limit']);
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
		// sort code, to place directories before files, if $dirsontop enabled
		$dirsfirst = $dirsontop ? '($a[mime]==\''.self::DIR_MIME_TYPE.'\')!==($b[mime]==\''.self::DIR_MIME_TYPE.'\')?'.
			'($a[mime]==\''.self::DIR_MIME_TYPE.'\'?-1:1):' : '';
		// ordering of the rows
		if (isset($options['order']))
		{
			$sort = strtolower($options['sort']) == 'desc' ? '-' : '';
			switch($options['order'])
			{
				// sort numerical
				case 'size':
				case 'uid':
				case 'gid':
				case 'mode':
				case 'ctime':
				case 'mtime':
					$code = $dirsfirst.$sort.'($a[\''.$options['order'].'\']-$b[\''.$options['order'].'\']);';
					// always use name as second sort criteria
					$code = '$cmp = '.$code.' return $cmp ? $cmp : strcasecmp($a[\'name\'],$b[\'name\']);';
					$ok = uasort($result,create_function('$a,$b',$code));
					break;

				// sort alphanumerical
				default:
					$options['order'] = 'name';
					// fall throught
				case 'name':
				case 'mime':
					$code = $dirsfirst.$sort.'strcasecmp($a[\''.$options['order'].'\'],$b[\''.$options['order'].'\']);';
					if ($options['order'] != 'name')
					{
						// always use name as second sort criteria
						$code = '$cmp = '.$code.' return $cmp ? $cmp : strcasecmp($a[\'name\'],$b[\'name\']);';
					}
					else
					{
						$code = 'return '.$code;
					}
					$ok = uasort($result,create_function('$a,$b',$code));
					break;
			}
			//echo "<p>order='$options[order]', sort='$options[sort]' --> uasort($result,create_function(,'$code'))=".array2string($ok)."</p>>\n";
		}
		// limit resultset
		self::$find_total = count($result);
		if (isset($options['limit']))
		{
			list($limit,$start) = explode(',',$options['limit']);
			if (!$limit && !($limit = $GLOBALS['egw_info']['user']['preferences']['comman']['maxmatches'])) $limit = 15;
			//echo "total=".egw_vfs::$find_total.", limit=$options[limit] --> start=$start, limit=$limit<br>\n";

			if ((int)$start || self::$find_total > $limit)
			{
				$result = array_slice($result,(int)$start,(int)$limit,true);
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
				array_push($options,$stat);
				//echo "calling ".print_r($exec,true).print_r($options,true)."\n";
				$stat = call_user_func_array($exec,$options);
			}
			return $result;
		}
		//error_log("egw_vfs::find($path)=".print_r(array_keys($result),true));
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

		if ($options['url'])
		{
			$stat = @lstat($path);
		}
		else
		{
			$stat = self::url_stat($path,STREAM_URL_STAT_LINK);
		}
		if (!$stat)
		{
			return;	// not found, should not happen
		}
		if ($type && (($type == 'd') == !($stat['mode'] & sqlfs_stream_wrapper::MODE_DIR) ||	// != is_dir() which can be true for symlinks
		    $type == 'F' && is_dir($path)))	// symlink to a directory
		{
			return;	// wrong type
		}
		$stat = array_slice($stat,13);	// remove numerical indices 0-12
		$stat['path'] = parse_url($path,PHP_URL_PATH);
		$stat['name'] = $options['remove'] > 0 ? implode('/',array_slice(explode('/',$stat['path']),$options['remove'])) : self::basename($path);

		if ($options['mime'] || $options['need_mime'])
		{
			$stat['mime'] = self::mime_content_type($path);
		}
		if (isset($options['name_preg']) && !preg_match($options['name_preg'],$stat['name']) ||
			isset($options['path_preg']) && !preg_match($options['path_preg'],$path))
		{
			//echo "<p>!preg_match('{$options['name_preg']}','{$stat['name']}')</p>\n";
			return;	// wrong name or path
		}
		if (isset($options['gid']) && $stat['gid'] != $options['gid'] ||
			isset($options['uid']) && $stat['uid'] != $options['uid'])
		{
			return;	// wrong user or group
		}
		if (isset($options['mime']) && $options['mime'] != $stat['mime'])
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
			isset($options['mtime']) && !self::_check_num(round((time()-$stat['mtime'])/86400),$options['mtime']))
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
	 * @param string|array $urls url or array of url's
	 * @param boolean $allow_urls=false allow to use url's, default no only pathes (to stay within the vfs)
	 * @return array
	 */
	static function remove($urls,$allow_urls=false)
	{
		//error_log(__METHOD__.'('.array2string($urls).')');
		// some precaution to never allow to (recursivly) remove /, /apps or /home
		foreach((array)$urls as $url)
		{
			if (preg_match('/^\/?(home|apps|)\/*$/',parse_url($url,PHP_URL_PATH)))
			{
				throw new egw_exception_assertion_failed(__METHOD__.'('.array2string($urls).") Cautiously rejecting to remove folder '$url'!");
			}
		}
		return self::find($urls,array('depth'=>true,'url'=>$allow_urls,'hidden'=>true),array(__CLASS__,'_rm_rmdir'));
	}

	/**
	 * Helper function for remove: either rmdir or unlink given url (depending if it's a dir or file)
	 *
	 * @param string $url
	 * @return boolean
	 */
	static function _rm_rmdir($url)
	{
		if ($url[0] == '/')
		{
			$url = self::PREFIX . $url;
		}
		if (is_dir($url) && !is_link($url))
		{
			return egw_vfs::rmdir($url,0);
		}
		return egw_vfs::unlink($url);
	}

	/**
	 * The stream_wrapper interface checks is_{readable|writable|executable} against the webservers uid,
	 * which is wrong in case of our vfs, as we use the current users id and memberships
	 *
	 * @param string $path
	 * @param int $check mode to check: one or more or'ed together of: 4 = egw_vfs::READABLE,
	 * 	2 = egw_vfs::WRITABLE, 1 = egw_vfs::EXECUTABLE
	 * @return boolean
	 */
	static function is_readable($path,$check = self::READABLE)
	{
		return self::check_access($path,$check);
	}

	/**
	 * The stream_wrapper interface checks is_{readable|writable|executable} against the webservers uid,
	 * which is wrong in case of our vfs, as we use the current users id and memberships
	 *
	 * @param string $path path
	 * @param int $check mode to check: one or more or'ed together of: 4 = egw_vfs::READABLE,
	 * 	2 = egw_vfs::WRITABLE, 1 = egw_vfs::EXECUTABLE
	 * @param array $stat=null stat array, to not query it again
	 * @param int $user=null user used for check, if not current user (egw_vfs::$user)
	 * @return boolean
	 */
	static function check_access($path, $check, array $stat=null, $user=null)
	{
		if (!$stat && $user && $user != self::$user)
		{
			static $path_user_stat = array();

			$backup_user = self::$user;
			self::$user = $user;

			if (!isset($path_user_stat[$path]) || !isset($path_user_stat[$path][$user]))
			{
				self::clearstatcache($path);

				$path_user_stat[$path][$user] = self::url_stat($path, 0);

				self::clearstatcache($path);	// we need to clear the stat-cache after the call too, as the next call might be the regular user again!
			}
			if (($stat = $path_user_stat[$path][$user]))
			{
				// some backend mounts use $user:$pass in their url, for them we have to deny access!
				if (strpos(self::resolve_url($path, false, false, false), '$user') !== false)
				{
					$ret = false;
				}
				else
				{
					$ret = self::check_access($path, $check, $stat);
				}
			}
			else
			{
				$ret = false;	// no access, if we can not stat the file
			}
			self::$user = $backup_user;

			//error_log(__METHOD__."(path=$path||stat[name]={$stat['name']},stat[mode]=".sprintf('%o',$stat['mode']).",$check,$user) ".array2string($ret));
			return $ret;
		}

		if (self::$is_root)
		{
			return true;
		}

		// throw exception if stat array is used insead of path, can be removed soon
		if (is_array($path))
		{
			throw new egw_exception_wrong_parameter('path has to be string, use check_acces($path,$check,$stat=null)!');
		}
		// query stat array, if not given
		if (is_null($stat))
		{
			$stat = self::url_stat($path,0);
		}
		//error_log(__METHOD__."(path=$path||stat[name]={$stat['name']},stat[mode]=".sprintf('%o',$stat['mode']).",$check)");

		if (!$stat)
		{
			//error_log(__METHOD__."(path=$path||stat[name]={$stat['name']},stat[mode]=".sprintf('%o',$stat['mode']).",$check) no stat array!");
			return false;	// file not found
		}
		// check if we use an EGroupwre stream wrapper, or a stock php one
		// if it's not an EGroupware one, we can NOT use uid, gid and mode!
		if (($scheme = parse_url($stat['url'],PHP_URL_SCHEME)) && !(class_exists(self::scheme2class($scheme))))
		{
			switch($check)
			{
				case self::READABLE:
					return is_readable($stat['url']);
				case self::WRITABLE:
					return is_writable($stat['url']);
				case self::EXECUTABLE:
					return is_executable($stat['url']);
			}
		}
		// check if other rights grant access
		if (($stat['mode'] & $check) == $check)
		{
			//error_log(__METHOD__."(path=$path||stat[name]={$stat['name']},stat[mode]=".sprintf('%o',$stat['mode']).",$check) access via other rights!");
			return true;
		}
		// check if there's owner access and we are the owner
		if (($stat['mode'] & ($check << 6)) == ($check << 6) && $stat['uid'] && $stat['uid'] == self::$user)
		{
			//error_log(__METHOD__."(path=$path||stat[name]={$stat['name']},stat[mode]=".sprintf('%o',$stat['mode']).",$check) access via owner rights!");
			return true;
		}
		// check if there's a group access and we have the right membership
		if (($stat['mode'] & ($check << 3)) == ($check << 3) && $stat['gid'])
		{
			if (in_array(-abs($stat['gid']), $GLOBALS['egw']->accounts->memberships(self::$user, true)))
			{
				//error_log(__METHOD__."(path=$path||stat[name]={$stat['name']},stat[mode]=".sprintf('%o',$stat['mode']).",$check) access via group rights!");
				return true;
			}
		}
		// check backend for extended acls (only if path given)
		$ret = $path && self::_call_on_backend('check_extended_acl',array(isset($stat['url'])?$stat['url']:$path,$check),true);	// true = fail silent if backend does not support

		//error_log(__METHOD__."(path=$path||stat[name]={$stat['name']},stat[mode]=".sprintf('%o',$stat['mode']).",$check) ".($ret ? 'backend extended acl granted access.' : 'no access!!!'));
		return $ret;
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
		return self::is_readable($path,self::WRITABLE);
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
		return self::is_readable($path,self::EXECUTABLE);
	}

	/**
	 * Check if path is a script and write access would be denied by backend
	 *
	 * @param string $path
	 * @return boolean true if $path is a script AND exec mount-option is NOT set, false otherwise
	 */
	static function deny_script($path)
	{
		return self::_call_on_backend('deny_script',array($path),true);
	}

	/**
	 * Set or delete extended acl for a given path and owner (or delete  them if is_null($rights)
	 *
	 * Does NOT check if user has the rights to set the extended acl for the given url/path!
	 *
	 * @param string $path string with path
	 * @param int $rights=null rights to set, or null to delete the entry
	 * @param int|boolean $owner=null owner for whom to set the rights, null for the current user, or false to delete all rights for $path
	 * @return boolean true if acl is set/deleted, false on error
	 */
	static function eacl($url,$rights=null,$owner=null)
	{
		return self::_call_on_backend('eacl',array($url,$rights,$owner));
	}

	/**
	 * Get all ext. ACL set for a path
	 *
	 * Calls itself recursive, to get the parent directories
	 *
	 * @param string $path
	 * @return array|boolean array with array('path'=>$path,'owner'=>$owner,'rights'=>$rights) or false if $path not found
	 */
	static function get_eacl($path)
	{
		return self::_call_on_backend('get_eacl',array($path),true);	// true = fail silent (no PHP Warning)
	}

	/**
	 * Store properties for a single ressource (file or dir)
	 *
	 * @param string $path string with path
	 * @param array $props array of array with values for keys 'name', 'ns', 'val' (null to delete the prop)
	 * @return boolean true if props are updated, false otherwise (eg. ressource not found)
	 */
	static function proppatch($path,array $props)
	{
		return self::_call_on_backend('proppatch',array($path,$props));
	}

	/**
	 * Default namespace for properties set by eGroupware: comment or custom fields (leading #)
	 *
	 */
	const DEFAULT_PROP_NAMESPACE = 'http://egroupware.org/';

	/**
	 * Read properties for a ressource (file, dir or all files of a dir)
	 *
	 * @param array|string $path (array of) string with path
	 * @param string $ns='http://egroupware.org/' namespace if propfind should be limited to a single one, otherwise use null
	 * @return array|boolean array with props (values for keys 'name', 'ns', 'val'), or path => array of props for is_array($path)
	 * 	false if $path does not exist
	 */
	static function propfind($path,$ns=self::DEFAULT_PROP_NAMESPACE)
	{
		return self::_call_on_backend('propfind',array($path,$ns),true);	// true = fail silent (no PHP Warning)
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
	 * @param string|int $set comma separated mode string to set [ugo]+[+=-]+[rwx]+
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
		if(($mode & self::MODE_LINK) == self::MODE_LINK) // Symbolic Link
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

	/**
	 * Get the closest mime icon
	 *
	 * @param string $mime_type
	 * @param boolean $et_image=true return $app/$icon string for etemplate (default) or html img tag if false
	 * @param int $size=16
	 * @return string
	 */
	static function mime_icon($mime_type, $et_image=true, $size=16)
	{
		if ($mime_type == egw_vfs::DIR_MIME_TYPE)
		{
			$mime_type = 'Directory';
		}
		if(!$mime_type)
		{
			$mime_type = 'unknown';
		}
		$mime_full = strtolower(str_replace	('/','_',$mime_type));
		list($mime_part) = explode('_',$mime_full);

		if (!($img=$GLOBALS['egw']->common->image('etemplate',$icon='mime'.$size.'_'.$mime_full)) &&
			!($img=$GLOBALS['egw']->common->image('etemplate',$icon='mime'.$size.'_'.$mime_part)))
		{
			$img = $GLOBALS['egw']->common->image('etemplate',$icon='mime'.$size.'_unknown');
		}
		if ($et_image === 'url')
		{
			return $img;
		}
		if ($et_image)
		{
			return 'etemplate/'.$icon;
		}
		return html::image('etemplate',$icon,mime_magic::mime2label($mime_type));
	}

	/**
	 * Human readable size values in k, M or G
	 *
	 * @param int $size
	 * @return string
	 */
	static function hsize($size)
	{
		if ($size < 1024) return $size;
		if ($size < 1024*1024) return sprintf('%3.1lfk',(float)$size/1024);
		if ($size < 1024*1024*1024) return sprintf('%3.1lfM',(float)$size/(1024*1024));
		return sprintf('%3.1lfG',(float)$size/(1024*1024*1024));
	}

	/**
	 * like basename($path), but also working if the 1. char of the basename is non-ascii
	 *
	 * @param string $path
	 * @return string
	 */
	static function basename($path)
	{
		list($path,$query) = explode('?',$path);	// remove query
		$parts = explode('/',$path);

		return array_pop($parts);
	}

	/**
	 * Get the directory / parent of a given path or url(!), return false for '/'!
	 *
	 * Also works around PHP under Windows returning dirname('/something') === '\\', which is NOT understood by EGroupware's VFS!
	 *
	 * @param string $path path or url
	 * @return string|boolean parent or false if there's none ($path == '/')
	 */
	static function dirname($url)
	{
		list($url,$query) = explode('?',$url,2);	// strip the query first, as it can contain slashes

		if ($url == '/' || $url[0] != '/' && parse_url($url,PHP_URL_PATH) == '/')
		{
			//error_log(__METHOD__."($url) returning FALSE: already in root!");
			return false;
		}
		$parts = explode('/',$url);
		if (substr($url,-1) == '/') array_pop($parts);
		array_pop($parts);
		if ($url[0] != '/' && count($parts) == 3 || count($parts) == 1 && $parts[0] === '')
		{
			array_push($parts,'');	// scheme://host is wrong (no path), has to be scheme://host/
		}
		//error_log(__METHOD__."($url)=".implode('/',$parts).($query ? '?'.$query : ''));
		return implode('/',$parts).($query ? '?'.$query : '');
	}

	/**
	 * Check if the current use has owner rights for the given path or stat
	 *
	 * We define all eGW admins the owner of the group directories!
	 *
	 * @param string $path
	 * @param array $stat=null stat for path, default queried by this function
	 * @return boolean
	 */
	static function has_owner_rights($path,array $stat=null)
	{
		if (!$stat) $stat = self::url_stat($path,0);

		return $stat['uid'] == self::$user ||	// user is the owner
			self::$is_root ||					// class runs with root rights
			!$stat['uid'] && $stat['gid'] && self::$is_admin;	// group directory and user is an eGW admin
	}

	/**
	 * Concat a relative path to an url, taking into account, that the url might already end with a slash or the path starts with one or is empty
	 *
	 * Also normalizing the path, as the relative path can contain ../
	 *
	 * @param string $url base url or path, might end in a /
	 * @param string $relative relative path to add to $url
	 * @return string
	 */
	static function concat($url,$relative)
	{
		list($url,$query) = explode('?',$url,2);
		if (substr($url,-1) == '/') $url = substr($url,0,-1);
		$url = ($relative === '' || $relative[0] == '/' ? $url.$relative : $url.'/'.$relative);

		// now normalize the path (remove "/something/..")
		while (strpos($url,'/../') !== false)
		{
			list($a,$b) = explode('/../',$url,2);
			$a = explode('/',$a);
			array_pop($a);
			$b = explode('/',$b);
			$url = implode('/',array_merge($a,$b));
		}
		return $url.($query ? (strpos($url,'?')===false ? '?' : '&').$query : '');
	}

	/**
	 * Build an url from it's components (reverse of parse_url)
	 *
	 * @param array $url_parts values for keys 'scheme', 'host', 'user', 'pass', 'query', 'fragment' (all but 'path' are optional)
	 * @return string
	 */
	static function build_url(array $url_parts)
	{
		$url = (!isset($url_parts['scheme'])?'':$url_parts['scheme'].'://'.
			(!isset($url_parts['user'])?'':$url_parts['user'].(!isset($url_parts['pass'])?'':':'.$url_parts['pass']).'@').
			$url_parts['host']).$url_parts['path'].
			(!isset($url_parts['query'])?'':'?'.$url_parts['query']).
			(!isset($url_parts['fragment'])?'':'?'.$url_parts['fragment']);
		//error_log(__METHOD__.'('.array2string($url_parts).") = '".$url."'");
		return $url;
	}

	/**
	 * URL to download a file
	 *
	 * We use our webdav handler as download url instead of an own download method.
	 * The webdav hander (filemanager/webdav.php) recognices eGW's session cookie and of cause understands regular GET requests.
	 *
	 * Please note: If you dont use eTemplate or the html class, you have to run this url throught egw::link() to get a full url
	 *
	 * @param string $path
	 * @param boolean $force_download=false add header('Content-disposition: filename="' . basename($path) . '"'), currently not supported!
	 * @todo get $force_download working through webdav
	 * @return string
	 */
	static function download_url($path,$force_download=false)
	{
		if (($url = self::_call_on_backend('download_url',array($path,$force_download),true)))
		{
			return $url;
		}
		if ($path[0] != '/')
		{
			$path = parse_url($path,PHP_URL_PATH);
		}
		// we do NOT need to encode % itself, as our path are already url encoded, with the exception of ' ' and '+'
		// we urlencode double quotes '"', as that fixes many problems in html markup
		return '/webdav.php'.strtr($path,array('+' => '%2B',' ' => '%20','"' => '%22'));
	}

	/**
	 * We cache locks within a request, as HTTP_WebDAV_Server generates so many, that it can be a bottleneck
	 *
	 * @var array
	 */
	static protected $lock_cache;

	/**
	 * Log (to error log) all calls to lock(), unlock() or checkLock()
	 *
	 */
	const LOCK_DEBUG = false;

	/**
	 * lock a ressource/path
	 *
	 * @param string $path path or url
	 * @param string &$token
	 * @param int &$timeout
	 * @param string &$owner
	 * @param string &$scope
	 * @param string &$type
	 * @param boolean $update=false
	 * @param boolean $check_writable=true should we check if the ressource is writable, before granting locks, default yes
	 * @return boolean true on success
	 */
	static function lock($path,&$token,&$timeout,&$owner,&$scope,&$type,$update=false,$check_writable=true)
	{
		// we require write rights to lock/unlock a resource
		if (!$path || $update && !$token || $check_writable && !egw_vfs::is_writable($path))
		{
			return false;
		}
    	// remove the lock info evtl. set in the cache
    	unset(self::$lock_cache[$path]);

    	if ($timeout < 1000000)	// < 1000000 is a relative timestamp, so we add the current time
    	{
    		$timeout += time();
    	}

		if ($update)	// Lock Update
		{
			if (($ret = (boolean)($row = self::$db->select(self::LOCK_TABLE,array('lock_owner','lock_exclusive','lock_write'),array(
				'lock_path' => $path,
				'lock_token' => $token,
			),__LINE__,__FILE__)->fetch())))
			{
				$owner = $row['lock_owner'];
				$scope = egw_db::from_bool($row['lock_exclusive']) ? 'exclusive' : 'shared';
				$type  = egw_db::from_bool($row['lock_write']) ? 'write' : 'read';

				self::$db->update(self::LOCK_TABLE,array(
					'lock_expires' => $timeout,
					'lock_modified' => time(),
				),array(
					'lock_path' => $path,
					'lock_token' => $token,
				),__LINE__,__FILE__);
			}
		}
		// HTTP_WebDAV_Server does this check before calling LOCK, but we want to be complete and usable outside WebDAV
		elseif(($lock = self::checkLock($path)) && ($lock['scope'] == 'exclusive' || $scope == 'exclusive'))
		{
			$ret = false;	// there's alread a lock
		}
		else
		{
			// HTTP_WebDAV_Server sets owner and token, but we want to be complete and usable outside WebDAV
			if (!$owner || $owner == 'unknown')
			{
				$owner = 'mailto:'.$GLOBALS['egw_info']['user']['account_email'];
			}
			if (!$token)
			{
				require_once('HTTP/WebDAV/Server.php');
				$token = HTTP_WebDAV_Server::_new_locktoken();
			}
			try {
				self::$db->insert(self::LOCK_TABLE,array(
					'lock_token' => $token,
					'lock_path'  => $path,
					'lock_created' => time(),
					'lock_modified' => time(),
					'lock_owner' => $owner,
					'lock_expires' => $timeout,
					'lock_exclusive' => $scope == 'exclusive',
					'lock_write' => $type == 'write',
				),false,__LINE__,__FILE__);
				$ret = true;
			}
			catch(egw_exception_db $e) {
				$ret = false;	// there's already a lock
			}
		}
		if (self::LOCK_DEBUG) error_log(__METHOD__."($path,$token,$timeout,$owner,$scope,$type,update=$update,check_writable=$check_writable) returns ".($ret ? 'true' : 'false'));
		return $ret;
	}

    /**
     * unlock a ressource/path
     *
     * @param string $path path to unlock
     * @param string $token locktoken
	 * @param boolean $check_writable=true should we check if the ressource is writable, before granting locks, default yes
     * @return boolean true on success
     */
    static function unlock($path,$token,$check_writable=true)
    {
		// we require write rights to lock/unlock a resource
		if ($check_writable && !egw_vfs::is_writable($path))
		{
			return false;
		}
        if (($ret = self::$db->delete(self::LOCK_TABLE,array(
        	'lock_path' => $path,
        	'lock_token' => $token,
        ),__LINE__,__FILE__) && self::$db->affected_rows()))
        {
        	// remove the lock from the cache too
        	unset(self::$lock_cache[$path]);
        }
		if (self::LOCK_DEBUG) error_log(__METHOD__."($path,$token,$check_writable) returns ".($ret ? 'true' : 'false'));
		return $ret;
    }

	/**
	 * checkLock() helper
	 *
	 * @param  string resource path to check for locks
	 * @return array|boolean false if there's no lock, else array with lock info
	 */
	static function checkLock($path)
	{
		if (isset(self::$lock_cache[$path]))
		{
			if (self::LOCK_DEBUG) error_log(__METHOD__."($path) returns from CACHE ".str_replace(array("\n",'    '),'',print_r(self::$lock_cache[$path],true)));
			return self::$lock_cache[$path];
		}
		$where = 'lock_path='.self::$db->quote($path);
		// ToDo: additional check parent dirs for locks and children of the requested directory
		//$where .= ' OR '.self::$db->quote($path).' LIKE '.self::$db->concat('lock_path',"'%'").' OR lock_path LIKE '.self::$db->quote($path.'%');
		// ToDo: shared locks can return multiple rows
		if (($result = self::$db->select(self::LOCK_TABLE,'*',$where,__LINE__,__FILE__)->fetch()))
		{
			$result = egw_db::strip_array_keys($result,'lock_');
			$result['type']  = egw_db::from_bool($result['write']) ? 'write' : 'read';
			$result['scope'] = egw_db::from_bool($result['exclusive']) ? 'exclusive' : 'shared';
			$result['depth'] = egw_db::from_bool($result['recursive']) ? 'infinite' : 0;
		}
		if ($result && $result['expires'] < time())	// lock is expired --> remove it
		{
	        self::$db->delete(self::LOCK_TABLE,array(
	        	'lock_path' => $result['path'],
	        	'lock_token' => $result['token'],
	        ),__LINE__,__FILE__);

			if (self::LOCK_DEBUG) error_log(__METHOD__."($path) lock is expired at ".date('Y-m-d H:i:s',$result['expires'])." --> removed");
	        $result = false;
		}
		if (self::LOCK_DEBUG) error_log(__METHOD__."($path) returns ".($result?array2string($result):'false'));
		return self::$lock_cache[$path] = $result;
	}

	/**
	 * Get backend specific information (data and etemplate), to integrate as tab in filemanagers settings dialog
	 *
	 * @param string $path
	 * @param array $content=null
	 * @return array|boolean array with values for keys 'data','etemplate','name','label','help' or false if not supported by backend
	 */
	static function getExtraInfo($path,array $content=null)
	{
		$extra = array();
		if (($extra_info = self::_call_on_backend('extra_info',array($path,$content),true)))	// true = fail silent if backend does NOT support it
		{
			$extra[] = $extra_info;
		}

		if (($vfs_extra = $GLOBALS['egw']->hooks->process(array(
			'location' => 'vfs_extra',
			'path' => $path,
			'content' => $content,
		))))
		{
			foreach($vfs_extra as $app => $data)
			{
				$extra = $extra ? array_merge($extra, $data) : $data;
			}
		}
		return $extra;
	}

	/**
	 * Mapps entries of applications to a path for the locking
	 *
	 * @param string $app
	 * @param int|string $id
	 * @return string
	 */
	static function app_entry_lock_path($app,$id)
	{
		return "/apps/$app/entry/$id";
	}

	/**
	 * Encoding of various special characters, which can NOT be unencoded in file-names, as they have special meanings in URL's
	 *
	 * @var array
	 */
	static public $encode = array(
		'%' => '%25',
		'#' => '%23',
		'?' => '%3F',
		'/' => '',	// better remove it completly
	);

	/**
	 * Encode a path component: replacing certain chars with their urlencoded counterparts
	 *
	 * Not all chars get encoded, slashes '/' are silently removed!
	 *
	 * To reverse the encoding, eg. to display a filename to the user, you have to use egw_vfs::decodePath()
	 *
	 * @param string|array $component
	 * @return string|array
	 */
	static public function encodePathComponent($component)
	{
		return str_replace(array_keys(self::$encode),array_values(self::$encode),$component);
	}

	/**
	 * Encode a path: replacing certain chars with their urlencoded counterparts
	 *
	 * To reverse the encoding, eg. to display a filename to the user, you have to use egw_vfs::decodePath()
	 *
	 * @param string $path
	 * @return string
	 */
	static public function encodePath($path)
	{
		return implode('/',self::encodePathComponent(explode('/',$path)));
	}

	/**
	 * Decode a path: rawurldecode(): mostly urldecode(), but do NOT decode '+', as we're NOT encoding it!
	 *
	 * Used eg. to translate a path for displaying to the User.
	 *
	 * @param string $path
	 * @return string
	 */
	static public function decodePath($path)
	{
		return rawurldecode($path);
	}

	/**
	 * Initialise our static vars
	 */
	static function init_static()
	{
		self::$user = (int) $GLOBALS['egw_info']['user']['account_id'];
		self::$is_admin = isset($GLOBALS['egw_info']['user']['apps']['admin']);
		self::$db = isset($GLOBALS['egw_setup']->db) ? $GLOBALS['egw_setup']->db : $GLOBALS['egw']->db;
		self::$lock_cache = array();
	}

	/**
	 * Returns the URL to the thumbnail of the given file. The thumbnail may simply
	 * be the mime-type icon, or - if activated - the preview with the given thsize.
	 *
	 * @param string $file name of the file
	 * @param int $thsize the size of the preview - false if the default should be used.
	 * @param string $mime if you already know the mime type of the file, you can supply
	 * 	it here. Otherwise supply "false".
	 */
	public static function thumbnail_url($file, $thsize = false, $mime = false)
	{
		// Retrive the mime-type of the file
		if (!$mime)
		{
			$mime = egw_vfs::mime_content_type($file);
		}

		$image = "";

		// Seperate the mime type into the primary and the secondary part
		list($mime_main, $mime_sub) = explode('/', $mime);

		if ($mime_main == 'egw')
		{
			$image = $GLOBALS['egw']->common->image($mime_sub, 'navbar');
		}
		else if ($file && $mime_main == 'image' && in_array($mime_sub, array('png','jpeg','jpg','gif','bmp')) &&
		         (string)$GLOBALS['egw_info']['server']['link_list_thumbnail'] != '0' &&
		         (string)$GLOBALS['egw_info']['user']['preferences']['common']['link_list_thumbnail'] != '0' &&
		         (!is_array($value) && ($stat = egw_vfs::stat($file)) ? $stat['size'] : $value['size']) < 1500000)
		{
			if (substr($file, 0, 6) == '/apps/')
			{
				$file = parse_url(egw_vfs::resolve_url_symlinks($path), PHP_URL_PATH);
			}

			//Assemble the thumbnail parameters
			$thparams = array();
			$thparams['path'] = $file;
			if ($thsize)
			{
				$thparams['thsize'] = $thsize;
			}
			$image = $GLOBALS['egw']->link('/etemplate/thumbnail.php', $thparams);
		}
		else
		{
			list($app, $name) = explode("/", egw_vfs::mime_icon($mime), 2);
			$image = $GLOBALS['egw']->common->image($app, $name);
		}

		return $image;
	}

	/**
	 * Get the configured start directory for the current user
	 *
	 * @return string
	 */
	static public function get_home_dir()
	{
		$start = '/home/'.$GLOBALS['egw_info']['user']['account_lid'];

		// check if user specified a valid startpath in his prefs --> use it
		if (($path = $GLOBALS['egw_info']['user']['preferences']['filemanager']['startfolder']) &&
			$path[0] == '/' && egw_vfs::is_dir($path) && egw_vfs::check_access($path, egw_vfs::READABLE))
		{
			$start = $path;
		}
		return $start;
	}

	/**
	 * Copies the files given in $src to $dst.
	 *
	 * @param array $src contains the source file
	 * @param string $dst is the destination directory
	 */
	static public function copy_files(array $src, $dst, &$errs, array &$copied)
	{
		if (self::is_dir($dst))
		{
			foreach ($src as $file)
			{
				// Check whether the file has already been copied - prevents from
				// recursion
				if (!in_array($file, $copied))
				{
					// Calculate the target filename
					$target = egw_vfs::concat($dst, egw_vfs::basename($file));

					if (self::is_dir($file))
					{
						if ($file !== $target)
						{
							// Create the target directory
							egw_vfs::mkdir($target,null,STREAM_MKDIR_RECURSIVE);

							$files = egw_vfs::find($file, array(
								"hidden" => true
							));

							$copied[] = $file;
							$copied[] = $target; // < newly created folder must not be copied again!
							if (egw_vfs::copy_files(egw_vfs::find($file), $target,
								$errs, $copied))
							{
								continue;
							}
						}

						$errs++;
					}
					else
					{
						// Copy a single file - check whether the file should be
						// copied onto itself.
						// TODO: Check whether target file already exists and give
						// return those files so that a dialog might be displayed
						// on the client side which lets the user decide.
						if ($target !== $file && egw_vfs::copy($file, $target))
						{
							$copied[] = $file;
						}
						else
						{
							$errs++;
						}
					}
				}
			}
		}

		return $errs == 0;
	}

	/**
	 * Moves the files given in src to dst
	 */
	static public function move_files(array $src, $dst, &$errs, array &$moved)
	{
		if (egw_vfs::is_dir($dst))
		{
			foreach($src as $file)
			{
				$target = egw_vfs::concat($dst, egw_vfs::basename($file));

				if ($file != $target && egw_vfs::rename($file, $target))
				{
					$moved[] = $file;
				}
				else
				{
					++$errs;
				}
			}
		}
	}

	/**
	 * Copy an uploaded file into the vfs, optionally set some properties (eg. comment or other cf's)
	 *
	 * Treat copying incl. properties as atomar operation in respect of notifications (one notification about an added file).
	 *
	 * @param array|string $src path to uploaded file or etemplate file array (value for key 'tmp_name')
	 * @param string $target path or directory to copy uploaded file
	 * @param array|string $props=null array with properties (name => value pairs, eg. 'comment' => 'FooBar','#cfname' => 'something'),
	 * 	array as for proppatch (array of array with values for keys 'name', 'val' and optional 'ns') or string with comment
	 * @param boolean $check_is_uploaded_file=true should method perform an is_uploaded_file check, default yes
	 * @return boolean|array stat array on success, false on error
	 */
	static public function copy_uploaded($src,$target,$props=null,$check_is_uploaded_file=true)
	{
		$tmp_name = is_array($src) ? $src['tmp_name'] : $src;

		if (self::stat($target) && self::is_dir($target))
		{
			$target = self::concat($target, self::encodePathComponent(is_array($src) ? $src['name'] : basename($tmp_name)));
		}
		if ($check_is_uploaded_file && !is_uploaded_file($tmp_name))
		{
			if (self::LOG_LEVEL) error_log(__METHOD__."($tmp_name, $target, ".array2string($props).",$check_is_uploaded_file) returning FALSE !is_uploaded_file()");
			return false;
		}
		if (!(self::is_writable($target) || self::is_writable(self::dirname($target))))
		{
			if (self::LOG_LEVEL) error_log(__METHOD__."($tmp_name, $target, ".array2string($props).",$check_is_uploaded_file) returning FALSE !writable");
			return false;
		}
		if ($props)
		{
			if (!is_array($props)) $props = array(array('name' => 'comment','val' => $props));

			// if $props is name => value pairs, convert it to internal array or array with values for keys 'name', 'val' and optional 'ns'
			if (!isset($props[0]))
			{
				foreach($props as $name => $val)
				{
					if (($name == 'comment' || $name[0] == '#') && $val)	// only copy 'comment' and cfs
					{
						$vfs_props[] = array(
							'name' => $name,
							'val'  => $val,
						);
					}
				}
				$props = $vfs_props;
			}
			// set props before copying the file, so notifications already contain them
			if (!self::stat($target))
			{
				self::touch($target);	// create empty file, to be able to attach properties
				self::$treat_as_new = true;	// notify as new
			}
			self::proppatch($target, $props);
		}
		$ret = copy($tmp_name,self::PREFIX.$target) ? self::stat($target) : false;
		if (self::LOG_LEVEL > 1 || !$ret && self::LOG_LEVEL) error_log(__METHOD__."($tmp_name, $target, ".array2string($props).") returning ".array2string($ret));
		return $ret;
	}
}

egw_vfs::init_static();
