<?php
/**
 * eGroupWare API: VFS - stream wrapper interface
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
 * eGroupWare API: VFS - stream wrapper interface
 *
 * The new vfs stream wrapper uses a kind of fstab to mount different filesystems / stream wrapper types
 * together for eGW's virtual file system.
 *
 * @link http://www.php.net/manual/en/function.stream-wrapper-register.php
 */
class vfs_stream_wrapper implements iface_stream_wrapper
{
	/**
	 * Scheme / protocol used for this stream-wrapper
	 */
	const SCHEME = 'vfs';
	/**
	 * Mime type of directories, the old vfs used 'Directory', while eg. WebDAV uses 'httpd/unix-directory'
	 */
	const DIR_MIME_TYPE = 'httpd/unix-directory';
	/**
	 * Should unreadable entries in a not writable directory be hidden, default yes
	 */
	const HIDE_UNREADABLES = true;

	/**
	 * optional context param when opening the stream, null if no context passed
	 *
	 * @var mixed
	 */
	var $context;
	/**
	 * mode-bits, which have to be set for links
	 */
	const MODE_LINK = 0120000;

	/**
	 * How much should be logged to the apache error-log
	 *
	 * 0 = Nothing
	 * 1 = only errors
	 * 2 = all function calls and errors (contains passwords too!)
	 */
	const LOG_LEVEL = 1;

	/**
	 * Our fstab in the form mount-point => url
	 *
	 * The entry for root has to be the first, or more general if you mount into subdirs the parent has to be before!
	 *
	 * @var array
	 */
	protected static $fstab = array(
		'/' => 'sqlfs://$host/',
		'/apps' => 'links://$host/apps',
	);

	/**
	 * stream / ressouce this class is opened for by stream_open
	 *
	 * @var ressource
	 */
	private $opened_stream;
	/**
	 * Mode of opened stream, eg. "r" or "w"
	 *
	 * @var string
	 */
	private $opened_stream_mode;
	/**
	 * Path of opened stream
	 *
	 * @var string
	 */
	private $opened_stream_path;
	/**
	 * URL of opened stream
	 *
	 * @var string
	 */
	private $opened_stream_url;
	/**
	 * Opened stream is a new file, false for existing files
	 *
	 * @var boolean
	 */
	private $opened_stream_is_new;
	/**
	 * directory-ressouce this class is opened for by dir_open
	 *
	 * @var ressource
	 */
	private $opened_dir;
	/**
	 * URL of the opened dir, used to build the complete URL of files in the dir
	 *
	 * @var string
	 */
	private $opened_dir_url;
	/**
	 * Flag if opened dir is writable, in which case we return un-readable entries too
	 *
	 * @var boolean
	 */
	private $opened_dir_writable;
	/**
	 * Extra dirs from our fstab in the current opened dir
	 *
	 * @var array
	 */
	private $extra_dirs;
	/**
	 * Pointer in the extra dirs
	 *
	 * @var int
	 */
	private $extra_dir_ptr;

	private static $wrappers;

	/**
	 * Resolve the given path according to our fstab AND symlinks
	 *
	 * @param string $path
	 * @param boolean $file_exists=true true if file needs to exists, false if not
	 * @param boolean $resolve_last_symlink=true
	 * @param array|boolean &$stat=null on return: stat of existing file or false for non-existing files
	 * @return string|boolean false if the url cant be resolved, should not happen if fstab has a root entry
	 */
	static function resolve_url_symlinks($path,$file_exists=true,$resolve_last_symlink=true,&$stat=null)
	{
		$path = self::get_path($path);

		if (!($stat = self::url_stat($path,$resolve_last_symlink?0:STREAM_URL_STAT_LINK)) && !$file_exists)
		{
			$stat = self::check_symlink_components($path,0,$url);
			if (self::LOG_LEVEL > 1) $log = " (check_symlink_components('$path',0,'$url') = $stat)";
		}
		else
		{
			$url = $stat['url'];
		}
		// if the url resolves to a symlink to the vfs, resolve this vfs:// url direct
		if ($url && parse_url($url,PHP_URL_SCHEME) == self::SCHEME)
		{
			$url = self::resolve_url(parse_url($url,PHP_URL_PATH));
		}
		if (self::LOG_LEVEL > 1) error_log(__METHOD__."($path,file_exists=$file_exists,resolve_last_symlink=$resolve_last_symlink) = '$url'$log");
		return $url;
	}

	/**
	 * Resolve the given path according to our fstab
	 *
	 * @param string $path
	 * @param boolean $do_symlink=true is a direct match allowed, default yes (must be false for a lstat or readlink!)
	 * @param boolean $use_symlinkcache=true
	 * @param boolean $replace_user_pass_host=true replace $user,$pass,$host in url, default true, if false result is not cached
	 * @return string|boolean false if the url cant be resolved, should not happen if fstab has a root entry
	 */
	static function resolve_url($path,$do_symlink=true,$use_symlinkcache=true,$replace_user_pass_host=true)
	{
		static $cache = array();

		$path = self::get_path($path);

		// we do some caching here
		if (isset($cache[$path]) && $replace_user_pass_host)
		{
			if (self::LOG_LEVEL > 1) error_log(__METHOD__."('$path') = '{$cache[$path]}' (from cache)");
			return $cache[$path];
		}
		// check if we can already resolve path (or a part of it) with a known symlinks
		if ($use_symlinkcache)
		{
			$path = self::symlinkCache_resolve($path,$do_symlink);
		}
		// setting default user, passwd and domain, if it's not contained int the url
		static $defaults;
		if (is_null($defaults))
		{
			$defaults = array(
				'user' => $GLOBALS['egw_info']['user']['account_lid'],
				'pass' => $GLOBALS['egw_info']['user']['passwd'],
				'host' => $GLOBALS['egw_info']['user']['domain'],
				'home' => str_replace(array('\\\\','\\'),array('','/'),$GLOBALS['egw_info']['user']['homedirectory']),
			);
		}
		$parts = array_merge(parse_url($path),$defaults);
		if (!$parts['host']) $parts['host'] = 'default';	// otherwise we get an invalid url (scheme:///path/to/something)!

		if (!empty($parts['scheme']) && $parts['scheme'] != self::SCHEME)
		{
			if (self::LOG_LEVEL > 1) error_log(__METHOD__."('$path') = '$path' (path is already an url)");
			return $path;	// path is already a non-vfs url --> nothing to do
		}
		if (empty($parts['path'])) $parts['path'] = '/';

		foreach(array_reverse(self::$fstab) as $mounted => $url)
		{
			if ($mounted == '/' || $mounted == $parts['path'] || $mounted.'/' == substr($parts['path'],0,strlen($mounted)+1))
			{
				$scheme = parse_url($url,PHP_URL_SCHEME);
				if (is_null(self::$wrappers) || !in_array($scheme,self::$wrappers))
				{
					self::load_wrapper($scheme);
				}
				$url = egw_vfs::concat($url,substr($parts['path'],strlen($mounted)));

				if ($replace_user_pass_host)
				{
					$url = str_replace(array('$user','$pass','$host','$home'),array($parts['user'],$parts['pass'],$parts['host'],$parts['home']),$url);
				}
				if ($parts['query']) $url .= '?'.$parts['query'];
				if ($parts['fragment']) $url .= '#'.$parts['fragment'];

				if (self::LOG_LEVEL > 1) error_log(__METHOD__."('$path') = '$url'");

				if ($replace_user_pass_host) $cache[$path] = $url;

				return $url;
			}
		}
		if (self::LOG_LEVEL > 0) error_log(__METHOD__."('$path') can't resolve path!\n");
		trigger_error(__METHOD__."($path) can't resolve path!\n",E_USER_WARNING);
		return false;
	}

	/**
	 * Returns mount url of a full url returned by resolve_url
	 *
	 * @param string $fullurl full url returned by resolve_url
	 * @return string|NULL mount url or null if not found
	 */
	static function mount_url($fullurl)
	{
		foreach(array_reverse(self::$fstab) as $mounted => $url)
		{
			list($url_no_query) = explode('?',$url);
			if (substr($fullurl,0,1+strlen($url_no_query)) === $url_no_query.'/')
			{
				return $url;
			}
		}
		return null;
	}

	/**
	 * Can be used from egw_vfs to tell vfs notifications to treat an opened file as a new file
	 *
	 * @var boolean
	 */
	static protected $treat_as_new;

	/**
	 * This method is called immediately after your stream object is created.
	 *
	 * @param string $path URL that was passed to fopen() and that this object is expected to retrieve
	 * @param string $mode mode used to open the file, as detailed for fopen()
	 * @param int $options additional flags set by the streams API (or'ed together):
	 * - STREAM_USE_PATH      If path is relative, search for the resource using the include_path.
	 * - STREAM_REPORT_ERRORS If this flag is set, you are responsible for raising errors using trigger_error() during opening of the stream.
	 *                        If this flag is not set, you should not raise any errors.
	 * @param string $opened_path full path of the file/resource, if the open was successfull and STREAM_USE_PATH was set
	 * @return boolean true if the ressource was opened successful, otherwise false
	 */
	function stream_open ( $path, $mode, $options, &$opened_path )
	{
		$this->opened_stream = null;

		if (!($url = self::resolve_url_symlinks($path,$mode[0]=='r',true,$stat)))
		{
			return false;
		}
		if (!($this->opened_stream = fopen($url,$mode,$options)))
		{
			return false;
		}
		$this->opened_stream_mode = $mode;
		$this->opened_stream_path = $path[0] == '/' ? $path : parse_url($path, PHP_URL_PATH);
		$this->opened_stream_url = $url;
		$this->opened_stream_is_new = !$stat;

		// are we requested to treat the opened file as new file (only for files opened NOT for reading)
		if ($mode[0] != 'r' && !$this->opened_stream_is_new && self::$treat_as_new)
		{
			$this->opened_stream_is_new = true;
			//error_log(__METHOD__."($path,$mode,...) stat=$stat, treat_as_new=".self::$treat_as_new." --> ".array2string($this->opened_stream_is_new));
			self::$treat_as_new = null;
		}
		return true;
	}

	/**
	 * This method is called when the stream is closed, using fclose().
	 *
	 * You must release any resources that were locked or allocated by the stream.
	 *
	 * VFS calls either "vfs_read", "vfs_added" or "vfs_modified" hook
	 */
	function stream_close ( )
	{
		$ret = fclose($this->opened_stream);

		if (isset($GLOBALS['egw']) && isset($GLOBALS['egw']->hooks))
		{
			$GLOBALS['egw']->hooks->process(array(
				'location' => str_replace('b','',$this->opened_stream_mode) == 'r' ? 'vfs_read' :
					($this->opened_stream_is_new ? 'vfs_added' : 'vfs_modified'),
				'path' => $this->opened_stream_path,
				'mode' => $this->opened_stream_mode,
				'url'  => $this->opened_stream_url,
			),'',true);
		}
		$this->opened_stream = $this->opened_stream_mode = $this->opened_stream_path = $this->opened_stream_url = $this->opened_stream_is_new = null;

		return $ret;
	}

	/**
	 * This method is called in response to fread() and fgets() calls on the stream.
	 *
	 * You must return up-to count bytes of data from the current read/write position as a string.
	 * If there are less than count bytes available, return as many as are available.
	 * If no more data is available, return either FALSE or an empty string.
	 * You must also update the read/write position of the stream by the number of bytes that were successfully read.
	 *
	 * @param int $count
	 * @return string/false up to count bytes read or false on EOF
	 */
	function stream_read ( $count )
	{
		return fread($this->opened_stream,$count);
	}

	/**
	 * This method is called in response to fwrite() calls on the stream.
	 *
	 * You should store data into the underlying storage used by your stream.
	 * If there is not enough room, try to store as many bytes as possible.
	 * You should return the number of bytes that were successfully stored in the stream, or 0 if none could be stored.
	 * You must also update the read/write position of the stream by the number of bytes that were successfully written.
	 *
	 * @param string $data
	 * @return integer
	 */
	function stream_write ( $data )
	{
		return fwrite($this->opened_stream,$data);
	}

 	/**
 	 * This method is called in response to feof() calls on the stream.
 	 *
 	 * Important: PHP 5.0 introduced a bug that wasn't fixed until 5.1: the return value has to be the oposite!
 	 *
 	 * if(version_compare(PHP_VERSION,'5.0','>=') && version_compare(PHP_VERSION,'5.1','<'))
  	 * {
 	 * 		$eof = !$eof;
 	 * }
  	 *
 	 * @return boolean true if the read/write position is at the end of the stream and no more data availible, false otherwise
 	 */
	function stream_eof ( )
	{
		return feof($this->opened_stream);
	}

	/**
	 * This method is called in response to ftell() calls on the stream.
	 *
	 * @return integer current read/write position of the stream
	 */
 	function stream_tell ( )
 	{
 		return ftell($this->opened_stream);
 	}

 	/**
 	 * This method is called in response to fseek() calls on the stream.
 	 *
 	 * You should update the read/write position of the stream according to offset and whence.
 	 * See fseek() for more information about these parameters.
 	 *
 	 * @param integer $offset
 	 * @param integer $whence	SEEK_SET - Set position equal to offset bytes
 	 * 							SEEK_CUR - Set position to current location plus offset.
 	 * 							SEEK_END - Set position to end-of-file plus offset. (To move to a position before the end-of-file, you need to pass a negative value in offset.)
 	 * @return boolean TRUE if the position was updated, FALSE otherwise.
 	 */
	function stream_seek ( $offset, $whence )
	{
		return fseek($this->opened_stream,$offset,$whence);
	}

	/**
	 * This method is called in response to fflush() calls on the stream.
	 *
	 * If you have cached data in your stream but not yet stored it into the underlying storage, you should do so now.
	 *
	 * @return booelan TRUE if the cached data was successfully stored (or if there was no data to store), or FALSE if the data could not be stored.
	 */
	function stream_flush ( )
	{
		return fflush($this->opened_stream);
	}

	/**
	 * This method is called in response to fstat() calls on the stream.
	 *
	 * If you plan to use your wrapper in a require_once you need to define stream_stat().
	 * If you plan to allow any other tests like is_file()/is_dir(), you have to define url_stat().
	 * stream_stat() must define the size of the file, or it will never be included.
	 * url_stat() must define mode, or is_file()/is_dir()/is_executable(), and any of those functions affected by clearstatcache() simply won't work.
	 * It's not documented, but directories must be a mode like 040777 (octal), and files a mode like 0100666.
	 * If you wish the file to be executable, use 7s instead of 6s.
	 * The last 3 digits are exactly the same thing as what you pass to chmod.
	 * 040000 defines a directory, and 0100000 defines a file.
	 *
	 * @return array containing the same values as appropriate for the stream.
	 */
	function stream_stat ( )
	{
		return fstat($this->opened_stream);
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
		if (!($url = self::resolve_url_symlinks($path,true,false)))	// true,false file need to exist, but do not resolve last component
		{
			return false;
		}
		$stat = self::url_stat($path, STREAM_URL_STAT_LINK);

		self::symlinkCache_remove($path);
		$ok = unlink($url);

		// call "vfs_unlink" hook only after successful unlink, with data from (not longer possible) stat call
		if ($ok && isset($GLOBALS['egw']) && isset($GLOBALS['egw']->hooks))
		{
			$GLOBALS['egw']->hooks->process(array(
				'location' => 'vfs_unlink',
				'path' => $path[0] == '/' ? $path : parse_url($path, PHP_URL_PATH),
				'url'  => $url,
				'stat' => $stat,
			),'',true);
		}
		return $ok;
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
		if (!($url_from = self::resolve_url_symlinks($path_from,true,false)) ||
			!($url_to = self::resolve_url_symlinks($path_to,false)))
		{
			return false;
		}
		// if file is moved from one filesystem / wrapper to an other --> copy it (rename fails cross wrappers)
		if (parse_url($url_from,PHP_URL_SCHEME) == parse_url($url_to,PHP_URL_SCHEME))
		{
			self::symlinkCache_remove($path_from);
			$ret = rename($url_from,$url_to);
		}
		elseif (($from = fopen($url_from,'r')) && ($to = fopen($url_to,'w')))
		{
			$ret = stream_copy_to_stream($from,$to) !== false;
			fclose($from);
			fclose($to);
			if ($ret) $ru = self::unlink($path_from);
		}
		else
		{
			$ret = false;
		}
		if (self::LOG_LEVEL > 1 || self::LOG_LEVEL && !$ret)
		{
			error_log(__METHOD__."('$path_from','$path_to') url_from='$url_from', url_to='$url_to' returning ".array2string($ret));
		}
		// call "vfs_rename" hook
		if ($ret && isset($GLOBALS['egw']) && isset($GLOBALS['egw']->hooks))
		{
			$GLOBALS['egw']->hooks->process(array(
				'location' => 'vfs_rename',
				'from' => $path_from[0] == '/' ? $path_from : parse_url($path_from, PHP_URL_PATH),
				'to' => $path_to[0] == '/' ? $path_to : parse_url($path_to, PHP_URL_PATH),
				'url_from' => $url_from,
				'url_to' => $url_to,
			),'',true);
		}
		return $ret;
	}

	/**
	 * This method is called in response to mkdir() calls on URL paths associated with the wrapper.
	 *
	 * It should attempt to create the directory specified by path.
	 * In order for the appropriate error message to be returned, do not define this method if your wrapper does not support creating directories.
	 *
	 * @param string $path
	 * @param int $mode
	 * @param int $options Posible values include STREAM_REPORT_ERRORS and STREAM_MKDIR_RECURSIVE
	 * @return boolean TRUE on success or FALSE on failure
	 */
	static function mkdir ( $path, $mode, $options )
	{
		if (!($url = self::resolve_url_symlinks($path,false)))	// false = directory does not need to exists
		{
			return false;
		}
		$ret = mkdir($url,$mode,$options);

		// call "vfs_mkdir" hook
		if ($ret && isset($GLOBALS['egw']) && isset($GLOBALS['egw']->hooks))
		{
			$GLOBALS['egw']->hooks->process(array(
				'location' => 'vfs_mkdir',
				'path' => $path[0] == '/' ? $path : parse_url($path, PHP_URL_PATH),
				'url' => $url,
			),'',true);
		}
		return $ret;
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
	static function rmdir ( $path, $options )
	{
		if (!($url = self::resolve_url_symlinks($path)))
		{
			return false;
		}
		$stat = self::url_stat($path, STREAM_URL_STAT_LINK);

		self::symlinkCache_remove($path);
		$ok = rmdir($url);

		// call "vfs_rmdir" hook, only after successful rmdir
		if ($ok && isset($GLOBALS['egw']) && isset($GLOBALS['egw']->hooks))
		{
			$GLOBALS['egw']->hooks->process(array(
				'location' => 'vfs_rmdir',
				'path' => $path[0] == '/' ? $path : parse_url($path, PHP_URL_PATH),
				'url' => $url,
				'stat' => $stat,
			),'',true);
		}
		return $ok;
	}

	/**
	 * Allow to call methods of the underlying stream wrapper: touch, chmod, chgrp, chown, ...
	 *
	 * We cant use a magic __call() method, as it does not work for static methods!
	 *
	 * @param string $name
	 * @param array $params first param has to be the path, otherwise we can not determine the correct wrapper
	 * @param boolean $fail_silent=false should only false be returned if function is not supported by the backend,
	 * 	or should an E_USER_WARNING error be triggered (default)
	 * @param int $path_param_key=0 key in params containing the path, default 0
	 * @return mixed return value of backend or false if function does not exist on backend
	 */
	static protected function _call_on_backend($name,$params,$fail_silent=false,$path_param_key=0)
	{
		$pathes = $params[$path_param_key];

		$scheme2urls = array();
		foreach(is_array($pathes) ? $pathes : array($pathes) as $path)
		{
			if (!($url = self::resolve_url_symlinks($path,false,false)))
			{
				return false;
			}
			$k=(string)parse_url($url,PHP_URL_SCHEME);
			if (!(is_array($scheme2urls[$k]))) $scheme2urls[$k] = array();
			$scheme2urls[$k][$path] = $url;
		}
		$ret = array();
		foreach($scheme2urls as $scheme => $urls)
		{
			if ($scheme)
			{
				if (!class_exists($class = self::scheme2class($scheme)) || !method_exists($class,$name))
				{
					if (!$fail_silent) trigger_error("Can't $name for scheme $scheme!\n",E_USER_WARNING);
					return false;
				}
				if (!is_array($pathes))
				{
					$params[$path_param_key] = $url;

					return call_user_func_array(array($class,$name),$params);
				}
				$params[$path_param_key] = $urls;
				if (!is_array($r = call_user_func_array(array($class,$name),$params)))
				{
					return $r;
				}
				// we need to re-translate the urls to pathes, as they can eg. contain symlinks
				foreach($urls as $path => $url)
				{
					if (isset($r[$url]) || isset($r[$url=parse_url($url,PHP_URL_PATH)]))
					{
						$ret[$path] = $r[$url];
					}
				}
			}
			// call the filesystem specific function (dont allow to use arrays!)
			elseif(!function_exists($name) || is_array($pathes))
			{
				return false;
			}
			else
			{
				return $name($url,$time);
			}
		}
		return $ret;
	}

	/**
	 * This is not (yet) a stream-wrapper function, but it's necessary and can be used static
	 *
	 * @param string $path
	 * @param int $time=null modification time (unix timestamp), default null = current time
	 * @param int $atime=null access time (unix timestamp), default null = current time, not implemented in the vfs!
	 * @return boolean true on success, false otherwise
	 */
	static function touch($path,$time=null,$atime=null)
	{
		return self::_call_on_backend('touch',array($path,$time,$atime));
	}

	/**
	 * This is not (yet) a stream-wrapper function, but it's necessary and can be used static
	 *
	 * Requires owner or root rights!
	 *
	 * @param string $path
	 * @param string $mode mode string see egw_vfs::mode2int
	 * @return boolean true on success, false otherwise
	 */
	static function chmod($path,$mode)
	{
		return self::_call_on_backend('chmod',array($path,$mode));
	}

	/**
	 * This is not (yet) a stream-wrapper function, but it's necessary and can be used static
	 *
	 * Requires root rights!
	 *
	 * @param string $path
	 * @param int $owner numeric user id
	 * @return boolean true on success, false otherwise
	 */
	static function chown($path,$owner)
	{
		return self::_call_on_backend('chown',array($path,$owner));
	}

	/**
	 * This is not (yet) a stream-wrapper function, but it's necessary and can be used static
	 *
	 * Requires owner or root rights!
	 *
	 * @param string $path
	 * @param int $group numeric group id
	 * @return boolean true on success, false otherwise
	 */
	static function chgrp($path,$group)
	{
		return self::_call_on_backend('chgrp',array($path,$group));
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
		return self::_call_on_backend('readlink',array($path),true);	// true = fail silent, if backend does not support readlink
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
		if (($ret = self::_call_on_backend('symlink',array($target,$link),false,1)))	// 1=path is in $link!
		{
			self::symlinkCache_remove($link);
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
	 * @param boolean $recheck=false true = do a new check, false = rely on stored mime type (if existing)
	 * @return string mime-type (self::DIR_MIME_TYPE for directories)
	 */
	static function mime_content_type($path,$recheck=false)
	{
		if (!($url = self::resolve_url_symlinks($path)))
		{
			return false;
		}
		if (($scheme = parse_url($url,PHP_URL_SCHEME)) && !$recheck)
		{
			// check it it's an eGW stream wrapper returning mime-type via url_stat
			// we need to first check if the constant is defined, as we get a fatal error in php5.3 otherwise
			if (class_exists($class = self::scheme2class($scheme)) &&
				defined($class.'::STAT_RETURN_MIME_TYPE') &&
				($mime_attr = constant($class.'::STAT_RETURN_MIME_TYPE')))
			{
				$stat = call_user_func(array($class,'url_stat'),parse_url($url,PHP_URL_PATH),0);
				if ($stat && $stat[$mime_attr])
				{
					$mime = $stat[$mime_attr];
				}
			}
		}
		if (!$mime && is_dir($url))
		{
			$mime = self::DIR_MIME_TYPE;
		}
		// if we operate on the regular filesystem and the mime_content_type function is available --> use it
		if (!$mime && !$scheme && function_exists('mime_content_type'))
		{
			$mime = mime_content_type($path);
		}
		// using EGw's own mime magic (currently only checking the extension!)
		if (!$mime)
		{
			$mime = mime_magic::filename2mime(parse_url($url,PHP_URL_PATH));
		}
		//error_log(__METHOD__."($path,$recheck) mime=$mime");
		return $mime;
	}

	/**
	 * This method is called immediately when your stream object is created for examining directory contents with opendir().
	 *
	 * @param string $path URL that was passed to opendir() and that this object is expected to explore.
	 * @return booelan
	 */
	function dir_opendir ( $path, $options )
	{
		$this->opened_dir = $this->extra_dirs = null;
		$this->extra_dir_ptr = 0;

		if (!($this->opened_dir_url = self::resolve_url_symlinks($path)))
		{
			if (self::LOG_LEVEL > 0) error_log(__METHOD__."( $path,$options) resolve_url_symlinks() failed!");
			return false;
		}
		if (!($this->opened_dir = opendir($this->opened_dir_url)))
		{
			if (self::LOG_LEVEL > 0) error_log(__METHOD__."( $path,$options) opendir($this->opened_dir_url) failed!");
			return false;
		}
		$this->opened_dir_writable = egw_vfs::check_access($this->opened_dir_url,egw_vfs::WRITABLE);
		// check our fstab if we need to add some of the mountpoints
		$basepath = parse_url($path,PHP_URL_PATH);
		foreach(self::$fstab as $mounted => $url)
		{
			if (((egw_vfs::dirname($mounted) == $basepath || egw_vfs::dirname($mounted).'/' == $basepath) && $mounted != '/') &&
				// only return children readable by the user, if dir is not writable
				(!self::HIDE_UNREADABLES || $this->opened_dir_writable ||
					egw_vfs::check_access($mounted,egw_vfs::READABLE)))
			{
				$this->extra_dirs[] = basename($mounted);
			}
		}
		if (self::LOG_LEVEL > 1) error_log(__METHOD__."( $path,$options): opendir($this->opened_dir_url)=$this->opened_dir, extra_dirs=".array2string($this->extra_dirs).', '.function_backtrace());
		return true;
	}

	/**
	 * This method is called in response to stat() calls on the URL paths associated with the wrapper.
	 *
	 * It should return as many elements in common with the system function as possible.
	 * Unknown or unavailable values should be set to a rational value (usually 0).
	 *
	 * If you plan to use your wrapper in a require_once you need to define stream_stat().
	 * If you plan to allow any other tests like is_file()/is_dir(), you have to define url_stat().
	 * stream_stat() must define the size of the file, or it will never be included.
	 * url_stat() must define mode, or is_file()/is_dir()/is_executable(), and any of those functions affected by clearstatcache() simply won't work.
	 * It's not documented, but directories must be a mode like 040777 (octal), and files a mode like 0100666.
	 * If you wish the file to be executable, use 7s instead of 6s.
	 * The last 3 digits are exactly the same thing as what you pass to chmod.
	 * 040000 defines a directory, and 0100000 defines a file.
	 *
	 * @param string $path
	 * @param int $flags holds additional flags set by the streams API. It can hold one or more of the following values OR'd together:
	 * - STREAM_URL_STAT_LINK	For resources with the ability to link to other resource (such as an HTTP Location: forward,
	 *                          or a filesystem symlink). This flag specified that only information about the link itself should be returned,
	 *                          not the resource pointed to by the link.
	 *                          This flag is set in response to calls to lstat(), is_link(), or filetype().
	 * - STREAM_URL_STAT_QUIET	If this flag is set, your wrapper should not raise any errors. If this flag is not set,
	 *                          you are responsible for reporting errors using the trigger_error() function during stating of the path.
	 *                          stat triggers it's own warning anyway, so it makes no sense to trigger one by our stream-wrapper!
	 * @param boolean $try_create_home=false should a user home-directory be created automatic, if it does not exist
	 * @param boolean $check_symlink_components=true check if path contains symlinks in path components other then the last one
	 * @return array
	 */
	static function url_stat ( $path, $flags, $try_create_home=false, $check_symlink_components=true )
	{
		if (self::LOG_LEVEL > 1) error_log(__METHOD__."('$path',$flags,try_create_home=$try_create_home,check_symlink_components=$check_symlink_components)");

		if (!($url = self::resolve_url($path,!($flags & STREAM_URL_STAT_LINK), $check_symlink_components)))
		{
			if (self::LOG_LEVEL > 0) error_log(__METHOD__."('$path',$flags) can NOT resolve path!");
			return false;
		}
		if ($flags & STREAM_URL_STAT_LINK)
		{
			$stat = @lstat($url);	// suppressed the stat failed warnings
		}
		else
		{
			$stat = @stat($url);	// suppressed the stat failed warnings

			if ($stat && ($stat['mode'] & self::MODE_LINK) &&  ($lpath = self::readlink($url)))
			{
				if ($lpath[0] != '/')	// concat relative path
				{
					$lpath = egw_vfs::concat(parse_url($path,PHP_URL_PATH),'../'.$lpath);
				}
				$url = egw_vfs::PREFIX.$lpath;
				if (self::LOG_LEVEL > 1) error_log(__METHOD__."($path,$flags) symlif (substr($path,-1) == '/' && $path != '/') $path = substr($path,0,-1);	// remove trailing slash eg. added by WebDAVink found and resolved to $url");
				// try reading the stat of the link
				if ($stat = self::url_stat($lpath,STREAM_URL_STAT_QUIET))
				{
					if(isset($stat['url'])) $url = $stat['url'];	// if stat returns an url use that, as there might be more links ...
					self::symlinkCache_add($path,$url);
				}
			}
		}
		// check if a failed url_stat was for a home dir, in that case silently create it
		if (!$stat && $try_create_home && egw_vfs::dirname(parse_url($path,PHP_URL_PATH)) == '/home' &&
			($id = $GLOBALS['egw']->accounts->name2id(basename($path))) &&
			$GLOBALS['egw']->accounts->id2name($id) == basename($path))	// make sure path has the right case!
		{
			$hook_data = array(
				'location' => $GLOBALS['egw']->accounts->get_type($id) == 'g' ? 'addgroup' : 'addaccount',
				'account_id' => $id,
				'account_lid' => basename($path),
				'account_name' => basename($path),
			);
			call_user_func(array('vfs_home_hooks',$hook_data['location']),$hook_data);
			$hook_data = null;
			$stat = self::url_stat($path,$flags,false);
		}
		if (!$stat && $check_symlink_components)	// check if there's a symlink somewhere inbetween the path
		{
			$stat = self::check_symlink_components($path,$flags,$url);
			if ($stat && isset($stat['url'])) self::symlinkCache_add($path,$stat['url']);
		}
		elseif(is_array($stat) && !isset($stat['url']))
		{
			$stat['url'] = $url;
		}
		return $stat;

		// Todo: if we hide non readables, we should return false on url_stat for consitency (if dir is not writabel)
		// Problem: this does NOT stop (calles itself infinit recursive)!
		if (self::HIDE_UNREADABLES && !egw_vfs::check_access($path,egw_vfs::READABLE,$stat) &&
			!egw_vfs::check_access(egw_vfs::dirname($path,egw_vfs::WRITABLE)))
		{
			return false;
		}
		return $stat;
	}

	/**
	 * Check if path (which fails the stat call) contains symlinks in path-components other then the last one
	 *
	 * @param string $path
	 * @param int $flags=0 see url_stat
	 * @param string &$url=null already resolved path
	 * @return array|boolean stat array or false if not found
	 */
	static private function check_symlink_components($path,$flags=0,&$url=null)
	{
		if (is_null($url) && !($url = self::resolve_url($path)))
		{
			if (self::LOG_LEVEL > 0) error_log(__METHOD__."('$path',$flags,'$url') can NOT resolve path: ".function_backtrace(1));
			return false;
		}
		if (self::LOG_LEVEL > 1) error_log(__METHOD__."('$path',$flags,'$url'): ".function_backtrace(1));

		while (($rel_path = egw_vfs::basename($url).($rel_path ? '/'.$rel_path : '')) &&
		       ($url = egw_vfs::dirname($url)))
		{
			if (($stat = self::url_stat($url,0,false,false)))
			{
				if (is_link($url) && ($lpath = self::readlink($url)))
				{
					if (self::LOG_LEVEL > 1) $log = "rel_path='$rel_path', url='$url': lpath='$lpath'";

					if ($lpath[0] != '/')
					{
						$lpath = egw_vfs::concat(parse_url($url,PHP_URL_PATH),'../'.$lpath);
					}
					//self::symlinkCache_add($path,egw_vfs::PREFIX.$lpath);
					$url = egw_vfs::PREFIX.egw_vfs::concat($lpath,$rel_path);
					if (self::LOG_LEVEL > 1) error_log("$log --> lpath='$lpath', url='$url'");
					return self::url_stat($url,$flags);
				}
				$url = egw_vfs::concat($url,$rel_path);
				if (self::LOG_LEVEL > 1) error_log(__METHOD__."('$path',$flags,'$url') returning null");
				return null;
			}
		}
		if (self::LOG_LEVEL > 1) error_log(__METHOD__."('$path',$flags,'$url') returning false");
		return false;	// $path does not exist
	}

	/**
	 * Cache of already resolved symlinks
	 *
	 * @var array with path => target
	 */
	private static $symlink_cache = array();

	/**
	 * Add a resolved symlink to cache
	 *
	 * @param string $path vfs path
	 * @param string $target target path
	 */
	static protected function symlinkCache_add($path,$target)
	{
		$path = self::get_path($path);

		if (isset(self::$symlink_cache[$path])) return;	// nothing to do

		if ($target[0] != '/') $target = parse_url($target,PHP_URL_PATH);

		self::$symlink_cache[$path] = $target;

		// sort longest path first
		uksort(self::$symlink_cache,create_function('$b,$a','return strlen($a)-strlen($b);'));
		if (self::LOG_LEVEL > 1) error_log(__METHOD__."($path,$target) cache now ".array2string(self::$symlink_cache));
	}

	/**
	 * Remove a resolved symlink from cache
	 *
	 * @param string $path vfs path
	 */
	static protected function symlinkCache_remove($path)
	{
		$path = self::get_path($path);

		unset(self::$symlink_cache[$path]);
		if (self::LOG_LEVEL > 1) error_log(__METHOD__."($path) cache now ".array2string(self::$symlink_cache));
	}

	/**
	 * Resolve a path from our symlink cache
	 *
	 * The cache is sorted from longer to shorter pathes.
	 *
	 * @param string $path
	 * @param boolean $do_symlink=true is a direct match allowed, default yes (must be false for a lstat or readlink!)
	 * @return string target or path, if path not found
	 */
	static public function symlinkCache_resolve($path,$do_symlink=true)
	{
		// remove vfs scheme, but no other schemes (eg. filesystem!)
		$path = self::get_path($path);

		$strlen_path = strlen($path);

		foreach(self::$symlink_cache as $p => $t)
		{
			if (($strlen_p = strlen($p)) > $strlen_path) continue;	// $path can NOT start with $p

			if ($path == $p)
			{
				if ($do_symlink) $target = $t;
				break;
			}
			elseif (substr($path,0,$strlen_p+1) == $p.'/')
			{
				$target = $t . substr($path,$strlen_p);
				break;
			}
		}
		if (self::LOG_LEVEL > 1 && isset($target)) error_log(__METHOD__."($path) = $target");
		return isset($target) ? $target : $path;
	}

	/**
	 * Clears our internal stat and symlink cache
	 *
	 * Normaly not necessary, as it is automatically cleared/updated, UNLESS egw_vfs::$user changes!
	 *
	 * We have to clear the symlink cache before AND after calling the backend,
	 * because auf traversal rights may be different when egw_vfs::$user changes!
	 *
	 * @param string $path='/' path of backend, whos cache to clear
	 */
	static function clearstatcache($path='/')
	{
		//error_log(__METHOD__."('$path')");
		self::$symlink_cache = array();
		self::_call_on_backend('clearstatcache', array($path), true, 0);
		self::$symlink_cache = array();
	}

	/**
	 * This method is called in response to readdir().
	 *
	 * It should return a string representing the next filename in the location opened by dir_opendir().
	 *
	 * Unless other filesystem, we only return files readable by the user, if the dir is not writable for him.
	 * This is done to hide files and dirs not accessible by the user (eg. other peoples home-dirs in /home).
	 *
	 * @return string
	 */
	function dir_readdir ( )
	{
		if ($this->extra_dirs && count($this->extra_dirs) > $this->extra_dir_ptr)
		{
			$file = $this->extra_dirs[$this->extra_dir_ptr++];
		}
		else
		{
			 // only return children readable by the user, if dir is not writable
			do {
				$file = readdir($this->opened_dir);
			}
			while($file !== false &&
				(is_array($this->extra_dirs) && in_array($file,$this->extra_dirs) || // do NOT return extra_dirs twice
				self::HIDE_UNREADABLES && !$this->opened_dir_writable &&
				!egw_vfs::check_access(egw_vfs::concat($this->opened_dir_url,$file),egw_vfs::READABLE)));
		}
		if (self::LOG_LEVEL > 1) error_log(__METHOD__."( $this->opened_dir ) = '$file'");
		return $file;
	}

	/**
	 * This method is called in response to rewinddir().
	 *
	 * It should reset the output generated by dir_readdir(). i.e.:
	 * The next call to dir_readdir() should return the first entry in the location returned by dir_opendir().
	 *
	 * @return boolean
	 */
	function dir_rewinddir ( )
	{
		$this->extra_dir_ptr = 0;

		return rewinddir($this->opened_dir);
	}

	/**
	 * This method is called in response to closedir().
	 *
	 * You should release any resources which were locked or allocated during the opening and use of the directory stream.
	 *
	 * @return boolean
	 */
	function dir_closedir ( )
	{
		$ret = closedir($this->opened_dir);

		$this->opened_dir = $this->extra_dirs = null;

		return $ret;
	}

	/**
	 * Load stream wrapper for a given schema
	 *
	 * @param string $scheme
	 * @return boolean
	 */
	static function load_wrapper($scheme)
	{
		if (!in_array($scheme,self::get_wrappers()))
		{
			switch($scheme)
			{
				case 'webdav':
				case 'webdavs':
					require_once('HTTP/WebDAV/Client.php');
					self::$wrappers[] = $scheme;
					break;
				case '':
					break;	// default file, always loaded
				default:
					// check if scheme is buildin in php or one of our own stream wrappers
					if (in_array($scheme,stream_get_wrappers()) || class_exists(self::scheme2class($scheme)))
					{
						self::$wrappers[] = $scheme;
					}
					else
					{
						trigger_error("Can't load stream-wrapper for scheme '$scheme'!",E_USER_WARNING);
						return false;
					}
			}
		}
		return true;
	}

	/**
	 * Return already loaded stream wrappers
	 *
	 * @return array
	 */
	static function get_wrappers()
	{
		if (is_null(self::$wrappers))
		{
			self::$wrappers = stream_get_wrappers();
		}
		return self::$wrappers;
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
		return str_replace('.','_',$scheme).'_stream_wrapper';
	}

	/**
	 * Getting the path from an url (or path) AND removing trailing slashes
	 *
	 * @param string $path url or path (might contain trailing slash from WebDAV!)
	 * @param string $only_remove_scheme=self::SCHEME if given only that scheme get's removed
	 * @return string path without training slash
	 */
	static protected function get_path($path,$only_remove_scheme=self::SCHEME)
	{
		$url_parts = parse_url($path);
		if ($path[0] != '/' && (!$only_remove_scheme || $url_parts['scheme'] == $only_remove_scheme))
		{
			$path = $url_parts['path'];
		}
		// remove trailing slashes eg. added by WebDAV
		if ($url_parts['path'] != '/')
		{
			while (substr($path,-1) == '/' && $path != '/')
			{
				$path = substr($path,0,-1);
			}
		}
		return $path;
	}

	/**
	 * Init our static properties and register this wrapper
	 *
	 */
	static function init_static()
	{
		stream_register_wrapper(self::SCHEME,__CLASS__);

		if ($GLOBALS['egw_info']['server']['vfs_fstab'] &&
			is_array($fstab = unserialize($GLOBALS['egw_info']['server']['vfs_fstab'])) && count($fstab))
		{
			self::$fstab = $fstab;
		}
	}
}

vfs_stream_wrapper::init_static();
