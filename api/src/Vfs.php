<?php /** @noinspection ALL */

/**
 * EGroupware API: VFS - static methods to use the new eGW virtual file system
 *
 * @link https://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage vfs
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2008-20 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 */

namespace EGroupware\Api;

// explicitly import old phpgwapi classes used:
use HTTP_WebDAV_Server;

/**
 * Class containing static methods to use the new eGW virtual file system
 *
 * This extension of the vfs stream-wrapper allows to use the following static functions,
 * which only allow access to the eGW VFS and need no 'vfs://default' prefix for filenames:
 *
 * All examples require a: use EGroupware\Api\Vfs;
 *
 * - resource Vfs::fopen($path,$mode) like fopen, returned resource can be used with fwrite etc.
 * - resource Vfs::opendir($path) like opendir, returned resource can be used with readdir etc.
 * - boolean Vfs::copy($from,$to) like copy
 * - boolean Vfs::rename($old,$new) renaming or moving a file in the vfs
 * - boolean Vfs::mkdir($path) creating a new dir in the vfs
 * - boolean Vfs::rmdir($path) removing (an empty) directory
 * - boolean Vfs::unlink($path) removing a file
 * - boolean Vfs::touch($path,$mtime=null) touch a file
 * - boolean Vfs::stat($path) returning status of file like stat(), but only with string keys (no numerical indexes)!
 *
 * With the exception of Vfs::touch() (not yet part of the stream_wrapper interface)
 * you can always use the standard php functions, if you add a 'vfs://default' prefix
 * to every filename or path. Be sure to always add the prefix, as the user otherwise gains
 * access to the real filesystem of the server!
 *
 * The two following methods can be used to persitently mount further filesystems (without editing the code):
 *
 * - boolean|array Vfs::mount($url,$path) to mount $ur on $path or to return the fstab when called without argument
 * - boolean Vfs::umount($path) to unmount a path or url
 *
 * The stream wrapper interface allows to access hugh files in junks to not be limited by the
 * memory_limit setting of php. To do you should pass the opened file as resource and not the content:
 *
 * 		$file = Vfs::fopen('/home/user/somefile','r');
 * 		$content = fread($file,1024);
 *
 * You can also attach stream filters, to eg. base64 encode or compress it on the fly,
 * without the need to hold the content of the whole file in memmory.
 *
 * If you want to copy a file, you can use stream_copy_to_stream to do a copy of a file far bigger then
 * php's memory_limit:
 *
 * 		$from = Vfs::fopen('/home/user/fromfile','r');
 * 		$to = Vfs::fopen('/home/user/tofile','w');
 *
 * 		stream_copy_to_stream($from,$to);
 *
 * The static Vfs::copy() method does exactly that, but you have to do it eg. on your own, if
 * you want to copy eg. an uploaded file into the vfs.
 *
 * Vfs::parse_url($url, $component=-1), Vfs::dirname($url) and Vfs::basename($url) work
 * on urls containing utf-8 characters, which get NOT urlencoded in our VFS!
 */
class Vfs extends Vfs\Base
{
	const PREFIX = Vfs\StreamWrapper::PREFIX;

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
	 * Current Vfs user id, set from $GLOBALS['egw_info']['user']['account_id'] by self::init_static()
	 *
	 * Should be protected and moved to Vfs\Base plus a getter and setter method added for public access,
	 * as after setting it in 21.1+, Api\Vfs\StreamWrapper::init_static() need to be called to set the default user context!
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
	 * @var Db
	 */
	static $db;

	/**
	 * fopen working on just the eGW VFS
	 *
	 * @param string $path filename with absolute path in the eGW VFS
	 * @param string $mode 'r', 'w', ... like fopen
	 * @param resource $context =null context to pass to stream-wrapper
	 * @return resource
	 */
	static function fopen($path, $mode, $context=null)
	{
		if ($path[0] != '/')
		{
			throw new Exception\AssertionFailed("Filename '$path' is not an absolute path!");
		}
		return $context ? fopen(self::PREFIX.$path, $mode, false, $context) : fopen(self::PREFIX.$path, $mode);
	}

	/**
	 * opendir working on just the eGW VFS: returns resource for readdir() etc.
	 *
	 * @param string $path filename with absolute path in the eGW VFS
	 * @param resource $context =null context to pass to stream-wrapper
	 * @return resource
	 */
	static function opendir($path, $context=null)
	{
		if ($path[0] != '/')
		{
			throw new Exception\AssertionFailed("Directory '$path' is not an absolute path!");
		}
		return $context ? opendir(self::PREFIX.$path, $context) : opendir(self::PREFIX.$path);
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
			throw new Exception\AssertionFailed("Directory '$path' is not an absolute path!");
		}
		return dir(self::PREFIX.$path);
	}

	/**
	 * scandir working on just the eGW VFS: returns array with filenames as values
	 *
	 * @param string $path filename with absolute path in the eGW VFS
	 * @param int $sorting_order =0 !$sorting_order (default) alphabetical in ascending order, $sorting_order alphabetical in descending order.
	 * @return array
	 */
	static function scandir($path,$sorting_order=0)
	{
		if ($path[0] != '/')
		{
			throw new Exception\AssertionFailed("Directory '$path' is not an absolute path!");
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
		$old_props = self::file_exists($to) ? self::propfind($to,null) : array();
		// copy properties (eg. file comment), if there are any and evtl. existing old properties
		$props = self::propfind($from,null);
		if(!$props)
		{
			$props = array();
		}
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
	 * @param string $ns =self::DEFAULT_PROP_NAMESPACE namespace, only if $prop is no array
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
		$ret = null;
		return $ret;
	}

	/**
	 * stat working on just the eGW VFS (alias of url_stat)
	 *
	 * @param string $path filename with absolute path in the eGW VFS
	 * @param boolean $try_create_home =false should a non-existing home-directory be automatically created
	 * @return array
	 */
	static function stat($path,$try_create_home=false)
	{
		if ($path[0] != '/' && strpos($path, self::PREFIX.'/') !== 0)
		{
			throw new Exception\AssertionFailed("File '$path' is not an absolute path!");
		}
		$vfs = new Vfs\StreamWrapper();
		if (($stat = $vfs->url_stat($path,0,$try_create_home)))
		{
			$stat = array_slice($stat,13);	// remove numerical indices 0-12
		}
		return $stat;
	}

	/**
	 * lstat (not resolving symbolic links) working on just the eGW VFS (alias of url_stat)
	 *
	 * @param string $path filename with absolute path in the eGW VFS
	 * @param boolean $try_create_home =false should a non-existing home-directory be automatically created
	 * @return array
	 */
	static function lstat($path,$try_create_home=false)
	{
		if ($path[0] != '/' && strpos($path, self::PREFIX.'/') !== 0)
		{
			throw new Exception\AssertionFailed("File '$path' is not an absolute path!");
		}
		$vfs = new Vfs\StreamWrapper();
		if (($stat = $vfs->url_stat($path,STREAM_URL_STAT_LINK,$try_create_home)))
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
	 * Check if file is hidden: name starts with a '.' or is Thumbs.db or _gsdata_
	 *
	 * @param string $path
	 * @param boolean $allow_versions =false allow .versions or .attic
	 * @return boolean
	 */
	public static function is_hidden($path, $allow_versions=false)
	{
		$file = self::basename($path);

		return $file[0] == '.' && (!$allow_versions || !in_array($file, array('.versions', '.attic'))) ||
			$file == 'Thumbs.db' || $file == '_gsdata_';
	}

	/**
	 * find = recursive search over the filesystem
	 *
	 * @param string|array $base base of the search
	 * @param array $options =null the following keys are allowed:
	 * <code>
	 * - type => {d|f|F|!l} d=dirs, f=files (incl. symlinks), F=files (incl. symlinks to files), !l=no symlinks, default all
	 * - depth => {true|false(default)} put the contents of a dir before the dir itself
	 * - dirsontop => {true(default)|false} allways return dirs before the files (two distinct blocks)
	 * - mindepth,maxdepth minimal or maximal depth to be returned
	 * - name,path => pattern with *,? wildcards, eg. "*.php"
	 * - name_preg,path_preg => preg regular expresion, eg. "/(vfs|wrapper)/"
	 * - uid,user,gid,group,nouser,nogroup file belongs to user/group with given name or (numerical) id
	 * - mime => type[/subtype] or perl regular expression starting with a "/" eg. "/^(image|video)\\//i"
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
	 * - show-deleted => {true|false(default)} get also set by hidden, if not explicitly set otherwise (requires versioning!)
	 * </code>
	 * @param string|array|true $exec =null function to call with each found file/dir as first param and stat array as last param or
	 * 	true to return file => stat pairs
	 * @param array $exec_params =null further params for exec as array, path is always the first param and stat the last!
	 * @return array of pathes if no $exec, otherwise path => stat pairs
	 */
	static function find($base,$options=null,$exec=null,$exec_params=null)
	{
		//error_log(__METHOD__."(".print_r($base,true).",".print_r($options,true).",".print_r($exec,true).",".print_r($exec_params,true).")\n");

		$type = $options['type'] ?? null;	// 'd', 'f' or 'F'
		$dirs_last = !empty($options['depth']);	// put content of dirs before the dir itself
		// show dirs on top by default, if no recursive listing (allways disabled if $type specified, as unnecessary)
		$dirsontop = !$type && (isset($options['dirsontop']) ? (boolean)$options['dirsontop'] : isset($options['maxdepth'])&&$options['maxdepth']>0);
		if ($dirsontop) $options['need_mime'] = true;	// otherwise dirsontop can NOT work

		// process some of the options (need to be done only once)
		if (isset($options['name']) && !isset($options['name_preg']))	// change from simple *,? wildcards to preg regular expression once
		{
			$options['name_preg'] = '/^'.str_replace(array('\\?','\\*'),array('.{1}','.*'),preg_quote($options['name'], '/')).'$/i';
		}
		if (isset($options['path']) && !isset($options['preg_path']))	// change from simple *,? wildcards to preg regular expression once
		{
			$options['path_preg'] = '/^'.str_replace(array('\\?','\\*'),array('.{1}','.*'),preg_quote($options['path'], '/')).'$/i';
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
		if (isset($options['order']) && $options['order'] === 'mime')
		{
			$options['need_mime'] = true;	// we need to return the mime colum
		}
		// implicit show deleted files, if hidden is enabled (requires versioning!)
		if (!empty($options['hidden']) && !isset($options['show-deleted']))
		{
			$options['show-deleted'] = true;
		}

		// make all find options available as stream context option "find", to allow plugins to use them
		$context = stream_context_create([
			self::SCHEME => [
				'find' => $options,
			],
		]);

		$url = $options['url'] ?? null;

		if (!is_array($base))
		{
			$base = array($base);
		}
		$result = array();
		foreach($base as $path)
		{
			if (!$url)
			{
				if ($path[0] != '/' || !self::stat($path)) continue;
				$path = self::PREFIX . $path;
			}
			if (!isset($options['remove']))
			{
				$options['remove'] = count($base) == 1 ? count(explode('/',$path))-3+(int)(substr($path,-1)!='/') : 0;
			}
			$is_dir = is_dir($path);
			if (empty($options['mindepth']) && (!$dirs_last || !$is_dir))
			{
				self::_check_add($options,$path,$result);
			}
			if ($is_dir && (!isset($options['maxdepth']) || ($options['maxdepth'] > 0 &&
				($options['depth'] ?? 0) < $options['maxdepth'])) &&
				($dir = @opendir($path, $context)))
			{
				while(($fname = readdir($dir)) !== false)
				{
					if ($fname == '.' || $fname == '..') continue;	// ignore current and parent dir!

					if (self::is_hidden($fname, $options['show-deleted'] ?? false) && !$options['hidden']) continue;	// ignore hidden files

					$file = self::concat($path, $fname);

					if (!isset($options['mindepth']) || (int)$options['mindepth'] <= 1)
					{
						self::_check_add($options,$file,$result);
					}
					// only descend into subdirs, if it's a real dir (no link to a dir) or we should follow symlinks
					if (is_dir($file) && ($options['follow'] || !is_link($file)) && (!isset($options['maxdepth']) || $options['maxdepth'] > 1))
					{
						$opts = $options;
						if ($opts['mindepth']) $opts['mindepth']--;
						if ($opts['maxdepth']) $opts['depth']++;
						unset($opts['order']);
						unset($opts['limit']);
						foreach(self::find($options['url']?$file:self::parse_url($file,PHP_URL_PATH),$opts,true) as $p => $s)
						{
							unset($result[$p]);
							$result[$p] = $s;
						}
					}
				}
				closedir($dir);
			}
			if ($is_dir && empty($options['mindepth']) && $dirs_last)
			{
				self::_check_add($options,$path,$result);
			}
		}
		// ordering of the rows
		if (isset($options['order']))
		{
			$sort_desc = strtolower($options['sort']) == 'desc';
			switch($order = $options['order'])
			{
				// sort numerical
				case 'size':
				case 'uid':
				case 'gid':
				case 'mode':
				case 'ctime':
				case 'mtime':
					$ok = uasort($result, function($a, $b) use ($dirsontop, $sort_desc, $order)
					{
						$cmp = $a[$order] - $b[$order];
						// sort code, to place directories before files, if $dirsontop enabled
						if ($dirsontop && ($a['mime'] == self::DIR_MIME_TYPE) !== ($b['mime'] == self::DIR_MIME_TYPE))
						{
							$cmp = $a['mime' ] == self::DIR_MIME_TYPE ? -1 : 1;
						}
						// reverse sort for descending, if no directory sorted to top
						elseif ($sort_desc)
						{
							 $cmp *= -1;
						}
						// always use name as second sort criteria
						if (!$cmp) $cmp = strcasecmp($a['name'], $b['name']);
						return $cmp;
					});
					break;

				// sort alphanumerical
				default:
					$order = 'name';
					// fall throught
				case 'name':
				case 'mime':
					$ok = uasort($result, function($a, $b) use ($dirsontop, $order, $sort_desc)
					{
						$cmp = strcasecmp($a[$order], $b[$order]);
						// sort code, to place directories before files, if $dirsontop enabled
						if ($dirsontop && ($a['mime'] == self::DIR_MIME_TYPE) !== ($b['mime'] == self::DIR_MIME_TYPE))
						{
							$cmp = $a['mime' ] == self::DIR_MIME_TYPE ? -1 : 1;
						}
						// reverse sort for descending
						elseif ($sort_desc)
						{
							$cmp *= -1;
						}
						// always use name as second sort criteria
						if (!$cmp && $order != 'name') $cmp = strcasecmp($a['name'], $b['name']);
						return $cmp;
					});
					break;
			}
		}
		// limit resultset
		self::$find_total = count($result);
		if (isset($options['limit']))
		{
			list($limit,$start) = explode(',',$options['limit']);
			if (!$limit && !($limit = $GLOBALS['egw_info']['user']['preferences']['common']['maxmatches'])) $limit = 15;
			//echo "total=".self::$find_total.", limit=$options[limit] --> start=$start, limit=$limit<br>\n";

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
		//error_log("self::find($path)=".print_r(array_keys($result),true));
		if ($exec !== true)
		{
			return array_keys($result);
		}
		return $result;
	}

	/**
	 * Function carying out the various (optional) checks, before files&dirs get returned as result of find
	 *
	 * @param array $options options, see self::find(,$options)
	 * @param string $path name of path to add
	 * @param array &$result here we add the stat for the key $path, if the checks are successful
	 */
	private static function _check_add($options,$path,&$result)
	{
		$type = $options['type'] ?? null;	// 'd' or 'f'

		if (!empty($options['url']))
		{
			if (($stat = @lstat($path)))
			{
				$stat = array_slice($stat,13);	// remove numerical indices 0-12
			}
		}
		else
		{
			$stat = self::lstat($path);
		}
		if (!$stat)
		{
			return;	// not found, should not happen
		}
		if ($type && (($type == 'd') == !($stat['mode'] & Vfs\Sqlfs\StreamWrapper::MODE_DIR) ||	// != is_dir() which can be true for symlinks
		    $type == 'F' && is_dir($path)) ||	// symlink to a directory
			$type == '!l' && ($stat['mode'] & Vfs::MODE_LINK)) // Symlink
		{
			return;	// wrong type
		}
		$stat['path'] = self::parse_url($path,PHP_URL_PATH);
		$stat['name'] = $options['remove'] > 0 ? implode('/',array_slice(explode('/',$stat['path']),$options['remove'])) : self::basename($path);

		if (!empty($options['mime']) || !empty($options['need_mime']))
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
			if ($options['mime'][0] == '/')	// perl regular expression given
			{
				if (!preg_match($options['mime'], $stat['mime']))
				{
					return;	// wrong mime-type
				}
			}
			else
			{
				list($type,$subtype) = explode('/',$options['mime']);
				// no subtype (eg. 'image') --> check only the main type
				if ($subtype || substr($stat['mime'],0,strlen($type)+1) != $type.'/')
				{
					return;	// wrong mime-type
				}
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
		if (empty($options['url']))
		{
			$path = self::parse_url($path,PHP_URL_PATH);
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
	 * Check if given directory is protected (user not allowed to remove or rename)
	 *
	 * Following directorys are protected:
	 * - /
	 * - /apps incl. subdirectories
	 * - /home
	 * - /templates incl. subdirectories
	 *
	 * @param string $dir path or url
	 * @return boolean true for protected dirs, false otherwise
	 */
	static function isProtectedDir($dir)
	{
		if ($dir[0] != '/') $dir = self::parse_url($dir, PHP_URL_PATH);

		return preg_match('#^/(apps(/[^/]+)?|home|templates(/[^/]+)?)?/*$#', $dir) > 0;
	}

	/**
	 * Recursiv remove all given url's, including it's content if they are files
	 *
	 * @param string|array $urls url or array of url's
	 * @param boolean $allow_urls =false allow to use url's, default no only pathes (to stay within the vfs)
	 * @throws Vfs\Exception\ProtectedDirectory if trying to delete a protected directory, see Vfs::isProtected()
	 * @return array
	 */
	static function remove($urls,$allow_urls=false)
	{
		//error_log(__METHOD__.'('.array2string($urls).')');
		foreach((array)$urls as $url)
		{
			// some precaution to never allow to (recursivly) remove /, /apps or /home, see Vfs::isProtected()
			if (self::isProtectedDir($url))
			{
				throw new Vfs\Exception\ProtectedDirectory("Deleting protected directory '$url' rejected!");
			}
		}
		return self::find($urls, array('depth'=>true,'url'=>$allow_urls,'hidden'=>true), __CLASS__.'::_rm_rmdir');
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
		$vfs = new Vfs\StreamWrapper();
		if (is_dir($url) && !is_link($url))
		{
			return $vfs->rmdir($url,0);
		}
		return $vfs->unlink($url);
	}

	/**
	 * The stream_wrapper interface checks is_{readable|writable|executable} against the webservers uid,
	 * which is wrong in case of our vfs, as we use the current users id and memberships
	 *
	 * @param string $path or url
	 * @param int $check mode to check: one or more or'ed together of: 4 = self::READABLE,
	 * 	2 = self::WRITABLE, 1 = self::EXECUTABLE
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
	 * @param string $path path or url
	 * @param int $check mode to check: one or more or'ed together of: 4 = self::READABLE,
	 * 	2 = self::WRITABLE, 1 = self::EXECUTABLE
	 * @param array|boolean $stat =null stat array or false, to not query it again
	 * @param int $user =null user used for check, if not current user (self::$user)
	 * @return boolean
	 * @todo deprecated or even remove $user parameter and code
	 */
	static function check_access($path, $check, $stat=null, int $user=null)
	{
		static $vfs = null;

		if (is_null($stat) && $user && $user !== self::$user)
		{
			static $path_user_stat = array();

			$backup_user = self::$user;
			self::$user = $user;
			Vfs\StreamWrapper::init_static();
			self::clearstatcache($path);

			if (!isset($path_user_stat[$path]) || !isset($path_user_stat[$path][$user]))
			{
				$vfs = new Vfs\StreamWrapper();
				$path_user_stat[$path][$user] = $vfs->url_stat($path, 0);

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
			Vfs\StreamWrapper::init_static();
			$vfs = null;

			// we need to clear stat-cache again, after restoring original user, as eg. eACL is stored in session
			self::clearstatcache($path);

			//error_log(__METHOD__."(path=$path||stat[name]={$stat['name']},stat[mode]=".sprintf('%o',$stat['mode']).",$check,$user) ".array2string($ret));
			return $ret;
		}

		if (!isset($vfs)) $vfs = new Vfs\StreamWrapper($path);
		return $vfs->check_access($path, $check, $stat);
	}

	/**
	 * The stream_wrapper interface checks is_{readable|writable|executable} against the webservers uid,
	 * which is wrong in case of our vfs, as we use the current users id and memberships
	 *
	 * @param string $path or url
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
	 * @param string $path or url
	 * @return boolean
	 */
	static function is_executable($path)
	{
		return self::is_readable($path,self::EXECUTABLE);
	}

	/**
	 * Check if path is a script and write access would be denied by backend
	 *
	 * @param string $path or url
	 * @return boolean true if $path is a script AND exec mount-option is NOT set, false otherwise
	 */
	static function deny_script($path)
	{
		return self::_call_on_backend('deny_script',array($path),true);
	}

	/**
	 * Name of EACL array in session
	 */
	const SESSION_EACL = 'session-eacl';

	/**
	 * Set or delete extended acl for a given path and owner (or delete  them if is_null($rights)
	 *
	 * Does NOT check if user has the rights to set the extended acl for the given url/path!
	 *
	 * @param string $url string with path
	 * @param int $rights =null rights to set, or null to delete the entry
	 * @param int|boolean $owner =null owner for whom to set the rights, null for the current user, or false to delete all rights for $path
	 * @param boolean $session_only =false true: set eacl only for this session, does NO further checks currently!
	 * @return boolean true if acl is set/deleted, false on error
	 */
	static function eacl($url,$rights=null,$owner=null,$session_only=false)
	{
		if ($session_only)
		{
			$session_eacls =& Cache::getSession(__CLASS__, self::SESSION_EACL);
			$session_eacls[] = array(
				'path'   => $url[0] == '/' ? $url : self::parse_url($url, PHP_URL_PATH),
				'owner'  => $owner ? $owner : self::$user,
				'rights' => $rights,
			);
			return true;
		}
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
		$eacls = self::_call_on_backend('get_eacl',array($path),true);	// true = fail silent (no PHP Warning)

		$session_eacls =& Cache::getSession(__CLASS__, self::SESSION_EACL);
		if ($session_eacls)
		{
			// eacl is recursive, therefore we have to match all parent-dirs too
			$paths = array($path);
			while ($path && $path != '/')
			{
				$paths[] = $path = self::dirname($path);
			}
			foreach((array)$session_eacls as $eacl)
			{
				if (in_array($eacl['path'], $paths))
				{
					$eacls[] = $eacl;
				}
			}

			// sort by length descending, to show precedence
			usort($eacls, function($a, $b) {
				return strlen($b['path']) - strlen($a['path']);
			});
		}
		return $eacls;
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
		return self::_call_on_backend('proppatch', [$path,$props], false, 0, true);
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
	 * @param string $ns ='http://egroupware.org/' namespace if propfind should be limited to a single one, otherwise use null
	 * @return array|boolean array with props (values for keys 'name', 'ns', 'val'), or path => array of props for is_array($path)
	 * 	false if $path does not exist
	 */
	static function propfind($path,$ns=self::DEFAULT_PROP_NAMESPACE)
	{
		return self::_call_on_backend('propfind', [$path, $ns],true, 0, true);	// true = fail silent (no PHP Warning)
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
	 * @param int $mode =0 current mode of the file, necessary for +/- operation
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
			$matches = null;
			if (!preg_match($use='/^([ugoa]*)([+=-]+)([rwx]+)$/',$s,$matches))
			{
				$use = str_replace(array('/','^','$','(',')'),'',$use);
				throw new Exception\WrongUserinput("$s is not an allowed mode, use $use !");
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
	 * @param boolean $et_image =true return $app/$icon string for etemplate (default) or url for false
	 * @param int $size =128
	 * @return string
	 */
	static function mime_icon($mime_type, $et_image=true, $size=128)
	{
		if ($mime_type == self::DIR_MIME_TYPE)
		{
			$mime_type = 'Directory';
		}
		if(!$mime_type)
		{
			$mime_type = 'unknown';
		}
		$mime_full = strtolower(str_replace	('/','_',$mime_type));
		list($mime_part) = explode('_',$mime_full);

		if (!($img=Image::find('etemplate',$icon='mime'.$size.'_'.$mime_full)) &&
			// check mime-alias-map before falling back to more generic icons
			!(isset(MimeMagic::$mime_alias_map[$mime_type]) &&
				($img=Image::find('etemplate',$icon='mime'.$size.'_'.str_replace('/','_',MimeMagic::$mime_alias_map[$mime_full])))) &&
			!($img=Image::find('etemplate',$icon='mime'.$size.'_'.$mime_part)))
		{
			$img = Image::find('etemplate',$icon='mime'.$size.'_unknown');
		}
		return $et_image ? 'etemplate/'.$icon : $img;
	}

	/**
	 * Human readable size values in k, M or G
	 *
	 * @param int $size
	 * @return string
	 */
	static function hsize($size)
	{
		if($size < 1024)
		{
			return $size;
		}
		if($size < 1024 * 1024)
		{
			return sprintf('%3.2fk', (float)$size / 1024);
		}
		if($size < 1024 * 1024 * 1024)
		{
			return sprintf('%3.4fM', (float)$size / (1024 * 1024));
		}
		return sprintf('%3.4fG', (float)$size / (1024 * 1024 * 1024));
	}

	/**
	 * Size in bytes, from human readable
	 *
	 * From PHP ini_get docs, Ivo Mandalski 15-Nov-2011 08:27
	 */
	static function int_size($_val)
	{
		if(empty($_val))return 0;

		$val = trim($_val);

		$matches = null;
		preg_match('#([0-9.]+)[\s]*([a-z]+)#i', $val, $matches);

		$last = '';
		if(isset($matches[2]))
		{
			$last = $matches[2];
		}

		if(isset($matches[1]))
		{
			$val = (float)$matches[1];
		}

		switch(strtolower($last))
		{
			case 'g':
			case 'gb':
				$val *= 1024;
			case 'm':
			case 'mb':
				$val *= 1024;
			case 'k':
			case 'kb':
			$val *= 1024;
		}

		return (int) $val;
	}

	/**
	 * like basename($path), but also working if the 1. char of the basename is non-ascii
	 *
	 * @param string $_path
	 * @return string
	 */
	static function basename($_path)
	{
		list($path) = explode('?',$_path);	// remove query
		$parts = explode('/',$path);

		return array_pop($parts);
	}

	/**
	 * Utf-8 save version of parse_url
	 *
	 * Does caching withing request, to not have to parse urls over and over again.
	 *
	 * @param string $url
	 * @param int $component =-1 PHP_URL_* constants
	 * @return array|string|boolean on success array or string, if $component given, or false on failure
	 */
	static function parse_url($url, $component=-1)
	{
		static $component2str = array(
			PHP_URL_SCHEME => 'scheme',
			PHP_URL_HOST => 'host',
			PHP_URL_PORT => 'port',
			PHP_URL_USER => 'user',
			PHP_URL_PASS => 'pass',
			PHP_URL_PATH => 'path',
			PHP_URL_QUERY => 'query',
			PHP_URL_FRAGMENT => 'fragment',
		);
		static $cache = array();	// some caching

		$result =& $cache[$url];

		if (!isset($result))
		{
			// Build arrays of values we need to decode before parsing
			static $entities = array('%21', '%2A', '%27', '%28', '%29', '%3B', '%3A', '%40', '%26', '%3D', '%24', '%2C', '%2F', '%3F', '%23', '%5B', '%5D');
			static $replacements = array('!', '*', "'", "(", ")", ";", ":", "@", "&", "=", "$", ",", "/", "?", "#", "[", "]");
			static $str_replace = null;
			if (!isset($str_replace)) $str_replace = function_exists('mb_str_replace') ? 'mb_str_replace' : 'str_replace';

			// Create encoded URL with special URL characters decoded so it can be parsed
			// All other characters will be encoded
			$encodedURL = $str_replace($entities, $replacements, urlencode($url));

			// Parse the encoded URL
			$result = $encodedParts = parse_url($encodedURL);

			// check if parsing failed because of an url like "<scheme>://<user>@/path" (no host) --> add "default"
			// that kind of url can get constructed in VFS when adding user-context (host has no meaning in Vfs)
			if ($result === false && strpos($encodedURL, '@/') !== false)
			{
				$result = $encodedParts = parse_url(str_replace('@/', '@default/', $encodedURL));
			}

			// Now, decode each value of the resulting array
			if ($encodedParts)
			{
				$result = array();
				foreach ($encodedParts as $key => $value)
				{
					$result[$key] = urldecode($str_replace($replacements, $entities, $value));
				}
			}
		}
		return $component >= 0 ? ($result[$component2str[$component]] ?? null) : $result;
	}

	/**
	 * Get the directory / parent of a given path or url(!), return false for '/'!
	 *
	 * Also works around PHP under Windows returning dirname('/something') === '\\', which is NOT understood by EGroupware's VFS!
	 *
	 * @param string $_url path or url
	 * @return string|boolean parent or false if there's none ($path == '/')
	 */
	static function dirname($_url)
	{
		if (strpos($url=$_url, '?') !== false) list($url, $query) = explode('?',$_url,2);	// strip the query first, as it can contain slashes

		if ($url == '/' || $url[0] != '/' && self::parse_url($url,PHP_URL_PATH) == '/')
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
		return implode('/',$parts).(!empty($query) ? '?'.$query : '');
	}

	/**
	 * Check if the current use has owner rights for the given path or stat
	 *
	 * We define all eGW admins the owner of the group directories!
	 *
	 * @param string $path
	 * @param ?array $stat =null stat for path, default queried by this function
	 * @return boolean
	 */
	static function has_owner_rights($path,array $stat=null)
	{
		return (new Vfs\StreamWrapper())->has_owner_rights($path, $stat);
	}

	/**
	 * Concat a relative path to an url, taking into account, that the url might already end with a slash or the path starts with one or is empty
	 *
	 * Also normalizing the path, as the relative path can contain ../
	 *
	 * @param string $_url base url or path, might end in a /
	 * @param string $relative relative path to add to $url
	 * @return string
	 */
	static function concat($_url,$relative)
	{
		if (strpos($url=$_url, '?') !== false) list($url, $query) = explode('?',$_url,2);
		if (substr($url,-1) == '/') $url = substr($url,0,-1);
		$ret = ($relative === '' || $relative[0] == '/' ? $url.$relative : $url.'/'.$relative);

		// now normalize the path (remove "/something/..")
		while (strpos($ret,'/../') !== false)
		{
			list($a_str,$b_str) = explode('/../',$ret,2);
			$a = explode('/',$a_str);
			array_pop($a);
			$b = explode('/',$b_str);
			$ret = implode('/',array_merge($a,$b));
		}
		return $ret.(isset($query) ? (strpos($url,'?')===false ? '?' : '&').$query : '');
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
	 * @param boolean $force_download =false add header('Content-disposition: filename="' . basename($path) . '"'), currently not supported!
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
			$path = self::parse_url($path,PHP_URL_PATH);
		}
		// we do NOT need to encode % itself, as our path are already url encoded, with the exception of ' ' and '+'
		// we urlencode double quotes '"', as that fixes many problems in html markup
		return '/webdav.php'.strtr($path,array('+' => '%2B',' ' => '%20','"' => '%22')).($force_download ? '?download' : '');
	}

	/**
	 * Download the given file list as a ZIP
	 *
	 * @param array $_files List of files to include in the zip
	 * @param string $name optional Zip file name.  If not provided, it will be determined automatically from the files
	 *
	 * @todo use https://github.com/maennchen/ZipStream-PHP to not assamble all files in memmory
	 */
	public static function download_zip(Array $_files, $name = false)
	{
		//error_log(__METHOD__ . ': '.implode(',',$_files));

		// Create zip file
		$zip_file = tempnam($GLOBALS['egw_info']['server']['temp_dir'], 'zip');

		$zip = new \ZipArchive();
		if (!$zip->open($zip_file, \ZipArchive::OVERWRITE))
		{
			throw new Exception("Cannot open zip file for writing.");
		}

		// Find lowest common directory, to use relative paths
		// eg: User selected /home/nathan/picture.jpg, /home/Pictures/logo.jpg
		// We want /home
		$dirs = array();
		foreach($_files as $file)
		{
			$dirs[] = self::dirname($file);
		}
		$paths = array_unique($dirs);
		if(count($paths) > 0)
		{
			// Shortest to longest
			usort($paths, function($a, $b) {
				return strlen($a) - strlen($b);
			});

			// Start with shortest, pop off sub-directories that don't match
			$parts = explode('/',$paths[0]);
			foreach($paths as $path)
			{
				$dirs = explode('/',$path);
				foreach($dirs as $dir_index => $dir)
				{
					if($parts[$dir_index] && $parts[$dir_index] != $dir)
					{
						unset($parts[$dir_index]);
					}
				}
			}
			$base_dir = implode('/', $parts);
		}
		else
		{
			$base_dir = $paths[0];
		}

		// Remove 'unsafe' filename characters
		// (en.wikipedia.org/wiki/Filename#Reserved_characters_and_words)
		$replace = array(
			// Linux
			'/',
			// Windows
			'\\','?','%','*',':','|',/*'.',*/ '"','<','>'
		);

		// A nice name for the user,
		$filename = $GLOBALS['egw_info']['server']['site_title'] . '_' .
			str_replace($replace,'_',(
			$name ? $name : (
			count($_files) == 1 ?
			// Just one file (hopefully a directory?) selected
			self::basename($_files[0]) :
			// Use the lowest common directory (eg: Infolog, Open, nathan)
			self::basename($base_dir))
		)) . '.zip';

		// Make sure basename is a dir
		if(substr($base_dir, -1) != '/')
		{
			$base_dir .='/';
		}

		// Go into directories, find them all
		$files = self::find($_files);
		$links = array();

		// We need to remove them _after_ we're done
		$tempfiles = array();

		// Give 1 second per file, but try to allow more time for big files when amount of files is low
		set_time_limit((count($files)<=9?10:count($files)));

		// Add files to archive
		foreach($files as &$addfile)
		{
			// Use relative paths inside zip
			$relative = substr($addfile, strlen($base_dir));

			// Use safe names - replace unsafe chars, convert to ASCII (ZIP spec says CP437, but we'll try)
			$path = explode('/',$relative);
			$_name = Translation::convert(Translation::to_ascii(implode('/', str_replace($replace,'_',$path))),false,'ASCII');

			// Don't go infinite with app entries
			if(self::is_link($addfile))
			{
				if(in_array($addfile, $links)) continue;
				$links[] = $addfile;
			}
			// Add directory - if empty, client app might not show it though
			if(self::is_dir($addfile))
			{
				// Zip directories
				$zip->addEmptyDir($addfile);
			}
			else if(self::is_readable($addfile))
			{
				// Copy to temp file, as ZipArchive fails to read VFS
				$temp = tempnam($GLOBALS['egw_info']['server']['temp_dir'], 'zip_');
				$from = self::fopen($addfile,'r');
		 		$to = fopen($temp,'w');
				if(!stream_copy_to_stream($from,$to) || !$zip->addFile($temp, $_name))
				{
					unlink($temp);
					trigger_error("Could not add $addfile to ZIP file", E_USER_ERROR);
					continue;
				}
				// Keep temp file until _after_ zipping is done
				$tempfiles[] = $temp;

				// Add comment in
				$props = self::propfind($addfile);
				if($props)
				{
					$comment = self::find_prop($props,'comment');
					if($comment)
					{
						$zip->setCommentName($_name, $comment['val']);
					}
				}
				unset($props);
			}
		}

		// Set a comment to help tell them apart
		$zip->setArchiveComment(lang('Created by %1', $GLOBALS['egw_info']['user']['account_lid']) . ' ' .DateTime::to());

		// Record total for debug, not available after close()
		//$total_files = $zip->numFiles;

		$result = $zip->close();
		if(!$result || !filesize($zip_file))
		{
			error_log('close() result: '.array2string($result));
			return 'Error creating zip file';
		}

		//error_log("Total files: " . $total_files . " Peak memory to zip: " . self::hsize(memory_get_peak_usage(true)));

		// FIRST: switch off zlib.output_compression, as this would limit downloads in size to memory_limit
		ini_set('zlib.output_compression',0);
		// SECOND: end all active output buffering
		while(ob_end_clean()) {}

		// Stream the file to the client
		header("Content-Type: application/zip");
		header("Content-Length: " . filesize($zip_file));
		header("Content-Disposition: attachment; filename=\"$filename\"");
		readfile($zip_file);

		unlink($zip_file);
		foreach($tempfiles as $temp_file)
		{
			unlink($temp_file);
		}

		// Make sure to exit after, if you don't want to add to the ZIP
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
	 * @param string $url url or path, lock is granted for the path only, but url is used for access checks
	 * @param string &$token
	 * @param int &$timeout
	 * @param int|string &$owner account_id, account_lid or mailto-url
	 * @param string &$scope
	 * @param string &$type
	 * @param boolean $update =false
	 * @param boolean $check_writable =true should we check if the ressource is writable, before granting locks, default yes
	 * @return boolean true on success
	 */
	static function lock($url, &$token, &$timeout, &$owner, &$scope, &$type, $update=false, $check_writable=true)
	{
		// we require write rights to lock/unlock a resource
		if (!$url || $update && !$token || $check_writable &&
			!(self::is_writable($url) || !self::file_exists($url) && ($dir=self::dirname($url)) && self::is_writable($dir)))
		{
			return false;
		}
		$path = self::parse_url($url, PHP_URL_PATH);
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
				$scope = Db::from_bool($row['lock_exclusive']) ? 'exclusive' : 'shared';
				$type  = Db::from_bool($row['lock_write']) ? 'write' : 'read';

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
		elseif(($lock = self::checkLock($url)) && ($lock['scope'] == 'exclusive' || $scope == 'exclusive'))
		{
			$ret = false;	// there's alread a lock
		}
		else
		{
			// HTTP_WebDAV_Server sets owner and token, but we want to be complete and usable outside WebDAV
			if (!$owner || $owner === 'unknown')
			{
				$owner = 'mailto:'.$GLOBALS['egw_info']['user']['account_email'];
			}
			elseif (($email = Accounts::id2name($owner, 'account_email')))
			{
				$owner = 'mailto:'.$email;
			}
			if (!$token)
			{
				require_once(__DIR__.'/WebDAV/Server.php');
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
			catch(Db\Exception $e) {
				unset($e);
				$ret = false;	// there's already a lock
			}
		}
		if (self::LOCK_DEBUG) error_log(__METHOD__."($url,$token,$timeout,$owner,$scope,$type,update=$update,check_writable=$check_writable) returns ".($ret ? 'true' : 'false'));
		return $ret;
	}

    /**
     * unlock a ressource/path
     *
	 * @param string $url url or path, lock is granted for the path only, but url is used for access checks
     * @param string $token locktoken
	 * @param boolean $check_writable =true should we check if the ressource is writable, before granting locks, default yes
     * @return boolean true on success
     */
    static function unlock($url,$token,$check_writable=true)
    {
		// we require write rights to lock/unlock a resource
		if ($check_writable && !self::is_writable($url))
		{
			return false;
		}
		$path = self::parse_url($url, PHP_URL_PATH);
		if (($ret = self::$db->delete(self::LOCK_TABLE,array(
			'lock_path' => $path,
			'lock_token' => $token,
		),__LINE__,__FILE__) && self::$db->affected_rows()))
		{
			// remove the lock from the cache too
			unset(self::$lock_cache[$path]);
		}
		if (self::LOCK_DEBUG) error_log(__METHOD__."($url,$token,$check_writable) returns ".($ret ? 'true' : 'false'));
		return $ret;
    }

	/**
	 * checkLock() helper
	 *
	 * @param string $url url or path, lock is granted for the path only, but url is used for access checks
	 * @param int $depth=0 currently only 0 or >0 = infinit/whole tree is evaluated
	 * @return array[]|array|boolean $depth > 0: array of path => lock info arrays for $depth > 0
	 *  $depth=0: false if there's no lock, else array with lock info
	 */
	static function checkLock($url, int $depth=0)
	{
		$path = self::parse_url($url, PHP_URL_PATH);
		if (!$depth && isset(self::$lock_cache[$path]))
		{
			if (self::LOCK_DEBUG) error_log(__METHOD__."($url) returns from CACHE ".str_replace(array("\n",'    '),'',print_r(self::$lock_cache[$url],true)));
			return self::$lock_cache[$path];
		}
		if ($depth > 0)
		{
			$where = ['lock_path LIKE '.self::$db->quote($path.'%')];
		}
		else
		{
			$where = 'lock_path='.self::$db->quote($path);
		}
		// ToDo: additional check parent dirs for locks and children of the requested directory
		//$where .= ' OR '.self::$db->quote($path).' LIKE '.self::$db->concat('lock_path',"'%'").' OR lock_path LIKE '.self::$db->quote($path.'%');
		// ToDo: shared locks can return multiple rows
		$results = [];
		foreach(self::$db->select(self::LOCK_TABLE,'*',$where,__LINE__,__FILE__) as $result)
		{
			$result = Db::strip_array_keys($result, 'lock_');
			$result['type'] = Db::from_bool($result['write']) ? 'write' : 'read';
			$result['scope'] = Db::from_bool($result['exclusive']) ? 'exclusive' : 'shared';
			$result['depth'] = Db::from_bool($result['recursive']) ? 'infinite' : 0;
			if ($result['expires'] < time())    // lock is expired --> remove it
			{
				self::$db->delete(self::LOCK_TABLE, array(
					'lock_path' => $result['path'],
					'lock_token' => $result['token'],
				), __LINE__, __FILE__);

				if (self::LOCK_DEBUG) error_log(__METHOD__ . "($url) lock is expired at " . date('Y-m-d H:i:s', $result['expires']) . " --> removed");
				$result = false;
			}
			else
			{
				if ($result['path'] === $path || str_starts_with($result['path'], $path))
				{
					$results[$result['path']] = $result;
				}
				self::$lock_cache[$result['path']] = $result;
			}
		}
		if (self::LOCK_DEBUG) error_log(__METHOD__."($url, $depth) returns ".array2string($depth ? $result : ($result ?? false)));
		return $depth ? $results : ($result ?? false);
	}

	/**
	 * Get backend specific information (data and etemplate), to integrate as tab in filemanagers settings dialog
	 *
	 * @param string $path
	 * @param array $content =null
	 * @return array|boolean array with values for keys 'data','etemplate','name','label','help' or false if not supported by backend
	 */
	static function getExtraInfo($path,array $content=null)
	{
		$extra = array();
		if (($extra_info = self::_call_on_backend('extra_info',array($path,$content),true)))	// true = fail silent if backend does NOT support it
		{
			$extra[] = $extra_info;
		}

		if (($vfs_extra = Hooks::process(array(
			'location' => 'vfs_extra',
			'path' => $path,
			'content' => $content,
		))))
		{
			foreach($vfs_extra as $data)
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
	 * To reverse the encoding, eg. to display a filename to the user, you have to use self::decodePath()
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
	 * To reverse the encoding, eg. to display a filename to the user, you have to use self::decodePath()
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
		self::$user = (int)$GLOBALS['egw_info']['user']['account_id'];
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
			$mime = self::mime_content_type($file);
		}

		$image = "";

		// Seperate the mime type into the primary and the secondary part
		list($mime_main, $mime_sub) = explode('/', $mime);

		if ($mime_main == 'egw')
		{
			$image = Image::find($mime_sub, 'navbar');
		}
		else if ($file && $mime_main == 'image' && in_array($mime_sub, array('png','jpeg','jpg','gif','bmp')) &&
		         (string)$GLOBALS['egw_info']['server']['link_list_thumbnail'] != '0' &&
		         (string)$GLOBALS['egw_info']['user']['preferences']['common']['link_list_thumbnail'] != '0' &&
		         ($stat = self::stat($file)) && $stat['size'] < 1500000)
		{
			if (substr($file, 0, 6) == '/apps/')
			{
				$file = self::parse_url(self::resolve_url_symlinks($file), PHP_URL_PATH);
			}

			//Assemble the thumbnail parameters
			$thparams = array();
			$thparams['path'] = $file;
			if ($thsize)
			{
				$thparams['thsize'] = $thsize;
			}
			$image = $GLOBALS['egw']->link('/api/thumbnail.php', $thparams);
		}
		else
		{
			list($app, $name) = explode("/", self::mime_icon($mime), 2);
			$image = Image::find($app, $name);
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
		// with sharing active we have no home, use /
		if ($GLOBALS['egw_info']['user']['account_id'] != self::$user)
		{
			return '/';
		}
		$start = '/home/'.$GLOBALS['egw_info']['user']['account_lid'];

		// check if user specified a valid startpath in his prefs --> use it
		if (($path = $GLOBALS['egw_info']['user']['preferences']['filemanager']['startfolder']) &&
			$path[0] == '/' && self::is_dir($path) && self::check_access($path, self::READABLE))
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
	 * @param int& $errs =null on return number of errors happened
	 * @param array& $copied =null on return files copied
	 * @return boolean true for no errors, false otherwise
	 */
	static public function copy_files(array $src, $dst, &$errs=null, array &$copied=null)
	{
		if (!is_array($copied))
		{
			$copied = [];
		}
		if (self::is_dir($dst))
		{
			foreach ($src as $file)
			{
				// Check whether the file has already been copied - prevents from
				// recursion
				if (!in_array($file, $copied))
				{
					// Calculate the target filename
					$target = self::concat($dst, self::basename($file));

					if (self::is_dir($file))
					{
						if ($file !== $target)
						{
							// Create the target directory
							self::mkdir($target,null,STREAM_MKDIR_RECURSIVE);

							$copied[] = $file;
							$copied[] = $target; // < newly created folder must not be copied again!
							if (self::copy_files(self::find($file), $target,
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
						if ($target !== $file && self::copy($file, $target))
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
		if (self::is_dir($dst))
		{
			$vfs = new Vfs\StreamWrapper();
			foreach($src as $file)
			{
				$target = self::concat($dst, self::basename($file));

				if ($file != $target && $vfs->rename($file, $target))
				{
					$moved[] = $file;
				}
				else
				{
					++$errs;
				}
			}

			return $errs == 0;
		}

		return false;
	}

	/**
	 * Copy an uploaded file into the vfs, optionally set some properties (eg. comment or other cf's)
	 *
	 * Treat copying incl. properties as atomar operation in respect of notifications (one notification about an added file).
	 *
	 * @param array|string|resource $src path to uploaded file or etemplate file array (value for key 'tmp_name'), or resource with opened file
	 * @param string $target path or directory to copy uploaded file
	 * @param array|string $props =null array with properties (name => value pairs, eg. 'comment' => 'FooBar','#cfname' => 'something'),
	 * 	array as for proppatch (array of array with values for keys 'name', 'val' and optional 'ns') or string with comment
	 * @param boolean $check_is_uploaded_file =true should method perform an is_uploaded_file check, default yes
	 * @return boolean|array stat array on success, false on error
	 */
	static public function copy_uploaded($src,$target,$props=null,$check_is_uploaded_file=true)
	{
		$tmp_name = is_array($src) ? $src['tmp_name'] : $src;

		if (self::stat($target) && self::is_dir($target))
		{
			$target = self::concat($target, self::encodePathComponent(is_array($src) ? $src['name'] : basename($tmp_name)));
		}
		if ($check_is_uploaded_file && !is_resource($tmp_name) && !is_uploaded_file($tmp_name))
		{
			if (self::LOG_LEVEL) error_log(__METHOD__."($tmp_name, $target, ".array2string($props).",$check_is_uploaded_file) returning FALSE !is_uploaded_file()");
			return false;
		}
		if (!self::is_writable($target) && !(($dir = self::dirname($target)) && self::is_writable($dir)))
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
		}
		if ($props)
		{
			// set props before copying the file, so notifications already contain them
			if (!self::stat($target))
			{
				self::touch($target);	// create empty file, to be able to attach properties
				// tell vfs stream-wrapper to treat file in following copy as a new file notification-wises
				$context = stream_context_create(array(
					self::SCHEME => array('treat_as_new' => true)
				));
			}
			self::proppatch($target, $props);
		}
		if (is_resource($tmp_name))
		{
			$ret = ($dest = self::fopen($target, 'w', $context)) &&
				stream_copy_to_stream($tmp_name, $dest) !== false &&
				fclose($dest) ? self::stat($target) : false;

			fclose($tmp_name);
		}
		else
		{
			$ret = (isset($context) ? copy($tmp_name, self::PREFIX.$target, $context) :
				copy($tmp_name, self::PREFIX.$target)) ?
				self::stat($target) : false;
		}
		if (self::LOG_LEVEL > 1 || !$ret && self::LOG_LEVEL) error_log(__METHOD__."($tmp_name, $target, ".array2string($props).") returning ".array2string($ret));
		return $ret;
	}

	/**
	 * Compare two files from vfs or local file-system for identical content
	 *
	 * VFS files must use URL, to be able to distinguish them eg. from temp. files!
	 *
	 * @param string $file1 vfs-url or local path, eg. /tmp/some-file.txt or vfs://default/home/user/some-file.txt
	 * @param string $file2 -- " --
	 * @return boolean true: if files are identical, false: if not or file not found
	 */
	public static function compare($file1, $file2)
	{
		if (filesize($file1) != filesize($file2) ||
			!($fp1 = fopen($file1, 'r')) || !($fp2 = fopen($file2, 'r')))
		{
			//error_log(__METHOD__."($file1, $file2) returning FALSE (different size)");
			return false;
		}
		while (($read1 = fread($fp1, 8192)) !== false &&
			($read2 = fread($fp2, 8192)) !== false &&
			$read1 === $read2 && !feof($fp1) && !feof($fp2))
		{
			// just loop until we find a difference
		}

		fclose($fp1);
		fclose($fp2);
		//error_log(__METHOD__."($file1, $file2) returning ".array2string($read1 === $read2)." (content differs)");
		return $read1 === $read2;
	}

	/**
	 * Resolve the given path according to our fstab AND symlinks
	 *
	 * @param string $_path
	 * @param boolean $file_exists =true true if file needs to exists, false if not
	 * @param boolean $resolve_last_symlink =true
	 * @param array|boolean &$stat=null on return: stat of existing file or false for non-existing files
	 * @return string|boolean false if the url cant be resolved, should not happen if fstab has a root entry
	 */
	static function resolve_url_symlinks($_path,$file_exists=true,$resolve_last_symlink=true,&$stat=null)
	{
		$vfs = new Vfs\StreamWrapper($_path);
		return $vfs->resolve_url_symlinks($_path, $file_exists, $resolve_last_symlink, $stat);
	}

	/**
	 * This method is called in response to mkdir() calls on URL paths associated with the wrapper.
	 *
	 * It should attempt to create the directory specified by path.
	 * In order for the appropriate error message to be returned, do not define this method if your wrapper does not support creating directories.
	 *
	 * @param string $path
	 * @param int $mode =0750
	 * @param boolean $recursive =false true: create missing parents too
	 * @return boolean TRUE on success or FALSE on failure
	 */
	static function mkdir ($path, $mode=0750, $recursive=false)
	{
		return $path[0] == '/' && mkdir(self::PREFIX.$path, $mode, $recursive);
	}

	/**
	 * This method is called in response to rmdir() calls on URL paths associated with the wrapper.
	 *
	 * It should attempt to remove the directory specified by path.
	 * In order for the appropriate error message to be returned, do not define this method if your wrapper does not support removing directories.
	 *
	 * @param string $path
	 * @param int $options Possible values include STREAM_REPORT_ERRORS.
	 * @return boolean TRUE on success or FALSE on failure.
	 */
	static function rmdir($path)
	{
		return $path[0] == '/' && rmdir(self::PREFIX.$path);
	}

	/**
	 * This method is called in response to unlink() calls on URL paths associated with the wrapper.
	 *
	 * It should attempt to delete the item specified by path.
	 * In order for the appropriate error message to be returned, do not define this method if your wrapper does not support unlinking!
	 *
	 * @param string $path
	 * @return boolean TRUE on success or FALSE on failure
	 */
	static function unlink ( $path )
	{
		return $path[0] == '/' && unlink(self::PREFIX.$path);
	}

	/**
	 * touch just running on VFS path
	 *
	 * @param string $path
	 * @param int $time =null modification time (unix timestamp), default null = current time
	 * @param int $atime =null access time (unix timestamp), default null = current time, not implemented in the vfs!
	 * @return boolean true on success, false otherwise
	 */
	static function touch($path,$time=null,$atime=null)
	{
		return $path[0] == '/' && touch(self::PREFIX.$path, $time, $atime);
	}

	/**
	 * chmod just running on VFS path
	 *
	 * Requires owner or root rights!
	 *
	 * @param string $path
	 * @param string $mode mode string see Vfs::mode2int
	 * @return boolean true on success, false otherwise
	 */
	static function chmod($path,$mode)
	{
		return $path[0] == '/' && chmod(self::PREFIX.$path, $mode);
	}

	/**
	 * chmod just running on VFS path
	 *
	 * Requires root rights!
	 *
	 * @param string $path
	 * @param int|string $owner numeric user id or account-name
	 * @return boolean true on success, false otherwise
	 */
	static function chown($path,$owner)
	{
		return $path[0] == '/' && chown(self::PREFIX.$path, is_numeric($owner) ? abs($owner) : $owner);
	}

	/**
	 * chgrp just running on VFS path
	 *
	 * Requires owner or root rights!
	 *
	 * @param string $path
	 * @param int|string $group numeric group id or group-name
	 * @return boolean true on success, false otherwise
	 */
	static function chgrp($path,$group)
	{
		return $path[0] == '/' && chgrp(self::PREFIX.$path, is_numeric($group) ? abs($group) : $group);
	}

	/**
	 * Returns the target of a symbolic link
	 *
	 * This is not (yet) a stream-wrapper function, but it's necessary and can be used static
	 *
	 * @param string $path
	 * @return string|boolean link-data or false if no link
	 */
	static function readlink($path)
	{
		$ret = self::_call_on_backend('readlink', [$path],true, 0, true);	// true = fail silent, if backend does not support readlink
		//error_log(__METHOD__."('$path') returning ".array2string($ret).' '.function_backtrace());
		return $ret;
	}

	/**
	 * Creates a symbolic link
	 *
	 * This is not (yet) a stream-wrapper function, but it's necessary and can be used static
	 *
	 * @param string $target target of the link
	 * @param string $link path of the link to create
	 * @return boolean true on success, false on error
	 */
	static function symlink($target,$link)
	{
		if (($ret = self::_call_on_backend('symlink', [$target, $link],false,1, true)))	// 1=path is in $link!
		{
			Vfs\StreamWrapper::symlinkCache_remove($link);
		}
		return $ret;
	}

	/**
	 * This is not (yet) a stream-wrapper function, but it's necessary and can be used static
	 *
	 * The methods use the following ways to get the mime type (in that order)
	 * - directories (is_dir()) --> self::DIR_MIME_TYPE
	 * - stream implemented by class defining the STAT_RETURN_MIME_TYPE constant --> use mime-type returned by url_stat
	 * - for regular filesystem use mime_content_type function if available
	 * - use eGW's mime-magic class
	 *
	 * @param string $path
	 * @param boolean $recheck =false true = do a new check, false = rely on stored mime type (if existing)
	 * @return string mime-type (self::DIR_MIME_TYPE for directories)
	 */
	static function mime_content_type($path,$recheck=false)
	{
		if (!($url = self::resolve_url_symlinks($path)))
		{
			return false;
		}
		if (($scheme = self::parse_url($url,PHP_URL_SCHEME)) && !$recheck)
		{
			// check it it's an eGW stream wrapper returning mime-type via url_stat
			// we need to first check if the constant is defined, as we get a fatal error in php5.3 otherwise
			if (class_exists($class = self::scheme2class($scheme)) &&
				defined($class.'::STAT_RETURN_MIME_TYPE') &&
				($mime_attr = constant($class.'::STAT_RETURN_MIME_TYPE')))
			{
				$inst = new $class;
				$stat = $inst->url_stat(self::parse_url($url,PHP_URL_PATH),0);
				if ($stat && $stat[$mime_attr])
				{
					$mime = $stat[$mime_attr];
				}
			}
		}
		if (empty($mime) && is_dir($url))
		{
			$mime = self::DIR_MIME_TYPE;
		}
		// if we operate on the regular filesystem and the mime_content_type function is available --> use it
		if (empty($mime) && !$scheme && function_exists('mime_content_type'))
		{
			$mime = mime_content_type($path);
		}
		// using EGw's own mime magic (currently only checking the extension!)
		if (empty($mime))
		{
			$mime = MimeMagic::filename2mime(self::parse_url($url,PHP_URL_PATH));
		}
		//error_log(__METHOD__."($path,$recheck) mime=$mime");
		return $mime;
	}

	/**
	 * Get the class-name for a scheme
	 *
	 * A scheme is not allowed to contain an underscore, but allows a dot and a class names only allow or need underscores, but no dots
	 * --> we replace dots in scheme with underscored to get the class-name
	 *
	 * @param string $scheme eg. vfs
	 * @return string
	 */
	static function scheme2class($scheme)
	{
		return Vfs\StreamWrapper::scheme2class($scheme);
	}

	/**
	 * Clears our internal stat and symlink cache
	 *
	 * Normaly not necessary, as it is automatically cleared/updated, UNLESS Vfs::$user changes!
	 *
	 * We have to clear the symlink cache before AND after calling the backend,
	 * because auf traversal rights may be different when Vfs::$user changes!
	 *
	 * @param string $path ='/' path of backend, whos cache to clear
	 */
	static function clearstatcache($path='/')
	{
		//error_log(__METHOD__."('$path')");
		parent::clearstatcache($path);
		self::_call_on_backend('clearstatcache', array($path), true, 0);
		parent::clearstatcache($path);
	}

	/**
	 * This method is called in response to rename() calls on URL paths associated with the wrapper.
	 *
	 * It should attempt to rename the item specified by path_from to the specification given by path_to.
	 * In order for the appropriate error message to be returned, do not define this method if your wrapper does not support renaming.
	 *
	 * The regular filesystem stream-wrapper returns an error, if $url_from and $url_to are not either both files or both dirs!
	 *
	 * @param string $path_from
	 * @param string $path_to
	 * @return boolean TRUE on success or FALSE on failure
	 */
	static function rename ( $path_from, $path_to )
	{
		$vfs = new Vfs\StreamWrapper();
		return $vfs->rename($path_from, $path_to);
	}

	/**
	 * Load stream wrapper for a given schema
	 *
	 * @param string $scheme
	 * @return boolean
	 */
	static function load_wrapper($scheme)
	{
		return Vfs\StreamWrapper::load_wrapper($scheme);
	}

	/**
	 * Return stream with given string as content
	 *
	 * @param string $string
	 * @return boolean|resource stream or false on error
	 */
	static function string_stream($string)
	{
		if (!($fp = fopen('php://temp', 'rw')))
		{
			return false;
		}
		$pos = 0;
		$len = strlen($string);
		do {
			if (!($written = fwrite($fp, substr($string, $pos))))
			{
				return false;
			}
			$pos += $written;
		}
		while ($len < $pos);

		rewind($fp);

		return $fp;
	}

	/**
	 * Get the lowest fs_id for a given path
	 *
	 * @param string $path
	 *
	 * @return integer|boolean Lowest fs_id for that path, or false
	 */
	static function get_minimum_file_id($path)
	{
		if(!self::file_exists($path))
		{
			return false;
		}
		return self::_call_on_backend('get_minimum_file_id', array($path));
	}

	/**
	 * Make sure the path is unique, by appending (#) to the filename if it already exists
	 *
	 * @param string $path
	 *
	 * @return string The same path, but modified if it exists
	 */
	static function make_unique($path)
	{
		$filename = Vfs::basename($path);
		$dupe_count = 0;
		while(is_file(Vfs::PREFIX . $path))
		{
			$dupe_count++;
			$path = Vfs::dirname($path) . '/' .
				pathinfo($filename, PATHINFO_FILENAME) .
				' (' . ($dupe_count + 1) . ')' . '.' .
				pathinfo($filename, PATHINFO_EXTENSION);
		}
		return $path;
	}
}

Vfs::init_static();