<?php
/**
 * EGroupware API: VFS - stream wrapper interface
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage vfs
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2008-16 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @version $Id$
 */

namespace EGroupware\Api\Vfs;

use EGroupware\Api\Vfs;
use EGroupware\Api;

/**
 * eGroupWare API: VFS - stream wrapper interface
 *
 * The new vfs stream wrapper uses a kind of fstab to mount different filesystems / stream wrapper types
 * together for eGW's virtual file system.
 *
 * @link http://www.php.net/manual/en/function.stream-wrapper-register.php
 */
class StreamWrapper implements StreamWrapperIface
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
	 * Maximum depth of symlinks, if exceeded url_stat will return false
	 *
	 * Used to prevent infinit recursion by circular links
	 */
	const MAX_SYMLINK_DEPTH = 10;

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
	 * Options for the opened directory
	 * (backup, etc.)
	 */
	protected $dir_url_params = array();

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
	 * @param string $_path
	 * @param boolean $file_exists =true true if file needs to exists, false if not
	 * @param boolean $resolve_last_symlink =true
	 * @param array|boolean &$stat=null on return: stat of existing file or false for non-existing files
	 * @return string|boolean false if the url cant be resolved, should not happen if fstab has a root entry
	 */
	function resolve_url_symlinks($_path,$file_exists=true,$resolve_last_symlink=true,&$stat=null)
	{
		$path = self::get_path($_path);

		if (!($stat = $this->url_stat($path,$resolve_last_symlink?0:STREAM_URL_STAT_LINK)) && !$file_exists)
		{
			$url = null;
			$stat = self::check_symlink_components($path,0,$url);
			if (self::LOG_LEVEL > 1) $log = " (check_symlink_components('$path',0,'$url') = $stat)";
		}
		else
		{
			$url = $stat['url'];
		}
		// if the url resolves to a symlink to the vfs, resolve this vfs:// url direct
		if ($url && Vfs::parse_url($url,PHP_URL_SCHEME) == self::SCHEME)
		{
			$url = self::resolve_url(Vfs::parse_url($url,PHP_URL_PATH));
		}
		if (self::LOG_LEVEL > 1) error_log(__METHOD__."($path,file_exists=$file_exists,resolve_last_symlink=$resolve_last_symlink) = '$url'$log");
		return $url;
	}

	/**
	 * Cache of already resolved urls
	 *
	 * @var array with path => target
	 */
	private static $resolve_url_cache = array();

	/**
	 * Resolve the given path according to our fstab
	 *
	 * @param string $_path
	 * @param boolean $do_symlink =true is a direct match allowed, default yes (must be false for a lstat or readlink!)
	 * @param boolean $use_symlinkcache =true
	 * @param boolean $replace_user_pass_host =true replace $user,$pass,$host in url, default true, if false result is not cached
	 * @param boolean $fix_url_query =false true append relativ path to url query parameter, default not
	 * @return string|boolean false if the url cant be resolved, should not happen if fstab has a root entry
	 */
	static function resolve_url($_path,$do_symlink=true,$use_symlinkcache=true,$replace_user_pass_host=true,$fix_url_query=false)
	{
		$path = self::get_path($_path);

		// we do some caching here
		if (isset(self::$resolve_url_cache[$path]) && $replace_user_pass_host)
		{
			if (self::LOG_LEVEL > 1) error_log(__METHOD__."('$path') = '".self::$resolve_url_cache[$path]."' (from cache)");
			return self::$resolve_url_cache[$path];
		}
		// check if we can already resolve path (or a part of it) with a known symlinks
		if ($use_symlinkcache)
		{
			$path = self::symlinkCache_resolve($path,$do_symlink);
		}
		// setting default user, passwd and domain, if it's not contained int the url
		static $defaults=null;
		if (is_null($defaults))
		{
			$defaults = array(
				'user' => $GLOBALS['egw_info']['user']['account_lid'],
				'pass' => urlencode($GLOBALS['egw_info']['user']['passwd']),
				'host' => $GLOBALS['egw_info']['user']['domain'],
				'home' => str_replace(array('\\\\','\\'),array('','/'),$GLOBALS['egw_info']['user']['homedirectory']),
			);
		}
		$parts = array_merge(Vfs::parse_url($path),$defaults);
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
				$scheme = Vfs::parse_url($url,PHP_URL_SCHEME);
				if (is_null(self::$wrappers) || !in_array($scheme,self::$wrappers))
				{
					self::load_wrapper($scheme);
				}
				if (($relative = substr($parts['path'],strlen($mounted))))
				{
					$url = Vfs::concat($url,$relative);
				}
				// if url contains url parameter, eg. from filesystem streamwrapper, we need to append relative path here too
				$matches = null;
				if ($fix_url_query && preg_match('|([?&]url=)([^&]+)|', $url, $matches))
				{
					$url = str_replace($matches[0], $matches[1].Vfs::concat($matches[2], substr($parts['path'],strlen($mounted))), $url);
				}

				if ($replace_user_pass_host)
				{
					$url = str_replace(array('$user','$pass','$host','$home'),array($parts['user'],$parts['pass'],$parts['host'],$parts['home']),$url);
				}
				if ($parts['query']) $url .= '?'.$parts['query'];
				if ($parts['fragment']) $url .= '#'.$parts['fragment'];

				if (self::LOG_LEVEL > 1) error_log(__METHOD__."('$path') = '$url'");

				if ($replace_user_pass_host) self::$resolve_url_cache[$path] = $url;

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
		foreach(array_reverse(self::$fstab) as $url)
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
		unset($options,$opened_path);	// not used but required by function signature
		$this->opened_stream = null;

		$stat = null;
		if (!($url = $this->resolve_url_symlinks($path,$mode[0]=='r',true,$stat)))
		{
			return false;
		}
		if (str_replace('b', '', $mode) != 'r' && self::url_is_readonly($url))
		{
			return false;
		}
		if (!($this->opened_stream = $this->context ?
			fopen($url, $mode, false, $this->context) : fopen($url, $mode, false)))
		{
			return false;
		}
		$this->opened_stream_mode = $mode;
		$this->opened_stream_path = $path[0] == '/' ? $path : Vfs::parse_url($path, PHP_URL_PATH);
		$this->opened_stream_url = $url;
		$this->opened_stream_is_new = !$stat;

		// are we requested to treat the opened file as new file (only for files opened NOT for reading)
		if ($mode[0] != 'r' && !$this->opened_stream_is_new && $this->context &&
			($opts = stream_context_get_params($this->context)) &&
			$opts['options'][self::SCHEME]['treat_as_new'])
		{
			$this->opened_stream_is_new = true;
			//error_log(__METHOD__."($path,$mode,...) stat=$stat, context=".array2string($opts)." --> ".array2string($this->opened_stream_is_new));
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
		// clear PHP's stat cache, it contains wrong size of just closed file,
		// causing eg. notifications to be ignored, because of previous size 0, when using WebDAV
		clearstatcache(false);

		if (!class_exists('setup_process', false))
		{
			Api\Hooks::process(array(
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
 	 * @param integer $whence	SEEK_SET - 0 - Set position equal to offset bytes
 	 * 							SEEK_CUR - 1 - Set position to current location plus offset.
 	 * 							SEEK_END - 2 - Set position to end-of-file plus offset. (To move to a position before the end-of-file, you need to pass a negative value in offset.)
 	 * @return boolean TRUE if the position was updated, FALSE otherwise.
 	 */
	function stream_seek ( $offset, $whence )
	{
		return !fseek($this->opened_stream,$offset,$whence);	// fseek returns 0 on success and -1 on failure
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
	 * StreamWrapper method (PHP 5.4+) for touch, chmod, chown and chgrp
	 *
	 * @param string $path
	 * @param int $option STREAM_META_(TOUCH|ACCESS|((OWNER|GROUP)(_NAME)?))
	 * @param array|int|string $value
	 * - STREAM_META_TOUCH array($time, $atime)
	 * - STREAM_META_ACCESS int
	 * - STREAM_(OWNER|GROUP) int
	 * - STREAM_(OWNER|GROUP)_NAME string
	 * @return boolean true on success, false on failure
	 */
	function stream_metadata($path, $option, $value)
	{
		if (!($url = $this->resolve_url_symlinks($path, $option != STREAM_META_TOUCH, false)))	// true,false file need to exist, but do not resolve last component
		{
			return false;
		}
		if (self::url_is_readonly($url))
		{
			return false;
		}
		if (self::LOG_LEVEL > 1) error_log(__METHOD__."('$path', $option, ".array2string($value).") url=$url");

		switch($option)
		{
			case STREAM_META_TOUCH:
				return touch($url, $value[0]);	// atime is not supported

			case STREAM_META_ACCESS:
				return chmod($url, $value);

			case STREAM_META_OWNER_NAME:
				if (($value = $GLOBALS['egw']->accounts->name2id($value, 'account_lid', 'u')) === false)
					return false;
				// fall through
			case STREAM_META_OWNER:
				return chown($url, $value);

			case STREAM_META_GROUP_NAME:
				if (($value = $GLOBALS['egw']->accounts->name2id($value, 'account_lid', 'g')) === false)
					return false;
				// fall through
			case STREAM_META_GROUP:
				return chgrp($url, $value);
		}
		return false;
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
	function unlink ( $path )
	{
		if (!($url = $this->resolve_url_symlinks($path,true,false)))	// true,false file need to exist, but do not resolve last component
		{
			return false;
		}
		if (self::url_is_readonly($url))
		{
			return false;
		}
		$stat = $this->url_stat($path, STREAM_URL_STAT_LINK);

		self::symlinkCache_remove($path);
		$ok = unlink($url);

		// call "vfs_unlink" hook only after successful unlink, with data from (not longer possible) stat call
		if ($ok && !class_exists('setup_process', false))
		{
			Api\Hooks::process(array(
				'location' => 'vfs_unlink',
				'path' => $path[0] == '/' ? $path : Vfs::parse_url($path, PHP_URL_PATH),
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
	 * @throws Exception\ProtectedDirectory if trying to delete a protected directory, see Vfs::isProtected()
	 */
	function rename ( $path_from, $path_to )
	{
		if (Vfs::isProtectedDir($path_from))
		{
			throw new Exception\ProtectedDirectory("Renaming protected directory '$path_from' rejected!");
		}
		if (!($url_from = $this->resolve_url_symlinks($path_from,true,false)) ||
			!($url_to = $this->resolve_url_symlinks($path_to,false)))
		{
			return false;
		}
		// refuse to modify readonly target (eg. readonly share)
		if (self::url_is_readonly($url_to))
		{
			return false;
		}
		// if file is moved from one filesystem / wrapper to an other --> copy it (rename fails cross wrappers)
		if (Vfs::parse_url($url_from,PHP_URL_SCHEME) == Vfs::parse_url($url_to,PHP_URL_SCHEME))
		{
			self::symlinkCache_remove($path_from);
			$ret = rename($url_from,$url_to);
		}
		elseif (($from = fopen($url_from,'r')) && ($to = fopen($url_to,'w')))
		{
			$ret = stream_copy_to_stream($from,$to) !== false;
			fclose($from);
			fclose($to);
			if ($ret) $this->unlink($path_from);
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
		if ($ret && !class_exists('setup_process', false))
		{
			Api\Hooks::process(array(
				'location' => 'vfs_rename',
				'from' => $path_from[0] == '/' ? $path_from : Vfs::parse_url($path_from, PHP_URL_PATH),
				'to' => $path_to[0] == '/' ? $path_to : Vfs::parse_url($path_to, PHP_URL_PATH),
				'url_from' => $url_from,
				'url_to' => $url_to,
			),'',true);
		}
		return $ret;
	}

	/**
	 * This method is called in response to mkdir() calls on URL paths associated with the wrapper.
	 *
	 * Not all wrappers, eg. smb(client) support recursive directory creation.
	 * Therefore we handle that here instead of passing the options to underlaying wrapper.
	 *
	 * @param string $path
	 * @param int $mode
	 * @param int $options Posible values include STREAM_REPORT_ERRORS and STREAM_MKDIR_RECURSIVE
	 * @return boolean TRUE on success or FALSE on failure
	 */
	function mkdir ( $path, $mode, $options )
	{
		if (!($url = $this->resolve_url_symlinks($path,false)))	// false = directory does not need to exists
		{
			return false;
		}
		// refuse to modify readonly target (eg. readonly share)
		if (self::url_is_readonly($url))
		{
			return false;
		}
		// check if recursive option is set and needed
		if (($options & STREAM_MKDIR_RECURSIVE) &&
			($parent_url = Vfs::dirname($url)) &&
			!($this->url_stat($parent_url, STREAM_URL_STAT_QUIET)) &&
			Vfs::parse_url($parent_url, PHP_URL_PATH) !== '/')
		{
			if (!mkdir($parent_url, $mode, $options)) return false;
		}
		// unset it now, as it was handled above
		$options &= ~STREAM_MKDIR_RECURSIVE;

		$ret = mkdir($url,$mode,$options);

		// call "vfs_mkdir" hook
		if ($ret && !class_exists('setup_process', false))
		{
			Api\Hooks::process(array(
				'location' => 'vfs_mkdir',
				'path' => $path[0] == '/' ? $path : Vfs::parse_url($path, PHP_URL_PATH),
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
	 * @throws Exception\ProtectedDirectory if trying to delete a protected directory, see Vfs::isProtected()
	 */
	function rmdir ( $path, $options )
	{
		if (Vfs::isProtectedDir($path))
		{
			throw new Exception\ProtectedDirectory("Deleting protected directory '$path' rejected!");
		}
		unset($options);	// not uses but required by function signature
		if (!($url = $this->resolve_url_symlinks($path)))
		{
			return false;
		}
		if (self::url_is_readonly($url))
		{
			return false;
		}
		$stat = $this->url_stat($path, STREAM_URL_STAT_LINK);

		self::symlinkCache_remove($path);
		$ok = rmdir($url);

		// call "vfs_rmdir" hook, only after successful rmdir
		if ($ok && !class_exists('setup_process', false))
		{
			Api\Hooks::process(array(
				'location' => 'vfs_rmdir',
				'path' => $path[0] == '/' ? $path : Vfs::parse_url($path, PHP_URL_PATH),
				'url' => $url,
				'stat' => $stat,
			),'',true);
		}
		return $ok;
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
		$this->dir_url_params = array();
		$this->extra_dir_ptr = 0;

		if (!($this->opened_dir_url = $this->resolve_url_symlinks($path)))
		{
			if (self::LOG_LEVEL > 0) error_log(__METHOD__."( $path,$options) resolve_url_symlinks() failed!");
			return false;
		}
		if (!($this->opened_dir = $this->context ?
			opendir($this->opened_dir_url, $this->context) : opendir($this->opened_dir_url)))
		{
			if (self::LOG_LEVEL > 0) error_log(__METHOD__."( $path,$options) opendir($this->opened_dir_url) failed!");
			return false;
		}
		$this->opened_dir_writable = Vfs::check_access($this->opened_dir_url,Vfs::WRITABLE);
		// check our fstab if we need to add some of the mountpoints
		$basepath = Vfs::parse_url($path,PHP_URL_PATH);
		foreach(array_keys(self::$fstab) as $mounted)
		{
			if (((Vfs::dirname($mounted) == $basepath || Vfs::dirname($mounted).'/' == $basepath) && $mounted != '/') &&
				// only return children readable by the user, if dir is not writable
				(!self::HIDE_UNREADABLES || $this->opened_dir_writable ||
					Vfs::check_access($mounted,Vfs::READABLE)))
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
	 * @param boolean $try_create_home =false should a user home-directory be created automatic, if it does not exist
	 * @param boolean $check_symlink_components =true check if path contains symlinks in path components other then the last one
	 * @return array
	 */
	function url_stat ( $path, $flags, $try_create_home=false, $check_symlink_components=true, $check_symlink_depth=self::MAX_SYMLINK_DEPTH, $try_reconnect=true )
	{
		if (!($url = self::resolve_url($path,!($flags & STREAM_URL_STAT_LINK), $check_symlink_components)))
		{
			if (self::LOG_LEVEL > 0) error_log(__METHOD__."('$path',$flags) can NOT resolve path!");
			return false;
		}

		try {
			if ($flags & STREAM_URL_STAT_LINK)
			{
				$stat = @lstat($url);	// suppressed the stat failed warnings
			}
			else
			{
				$stat = @stat($url);	// suppressed the stat failed warnings

				if ($stat && ($stat['mode'] & self::MODE_LINK))
				{
					if (!$check_symlink_depth)
					{
						if (self::LOG_LEVEL > 0) error_log(__METHOD__."('$path',$flags) maximum symlink depth exceeded, might be a circular symlink!");
						$stat = false;
					}
					elseif (($lpath = Vfs::readlink($url)))
					{
						if ($lpath[0] != '/')	// concat relative path
						{
							$lpath = Vfs::concat(Vfs::parse_url($path,PHP_URL_PATH),'../'.$lpath);
						}
						$u_query = parse_url($url,PHP_URL_QUERY);
						$url = Vfs::PREFIX.$lpath;
						if (self::LOG_LEVEL > 1) error_log(__METHOD__."($path,$flags) symlif (substr($path,-1) == '/' && $path != '/') $path = substr($path,0,-1);	// remove trailing slash eg. added by WebDAVink found and resolved to $url");
						// try reading the stat of the link
						if (($stat = $this->url_stat($lpath, STREAM_URL_STAT_QUIET, false, true, $check_symlink_depth-1)))
						{
							$stat_query = parse_url($stat['url'], PHP_URL_QUERY);
							if($u_query || $stat_query)
							{
								$stat_url = parse_url($stat['url']);
								parse_str($stat_query,$stat_query);
								parse_str($u_query, $u_query);
								$stat_query = http_build_query(array_merge($stat_query, $u_query));
								$stat['url'] = $stat_url['scheme'].'://'.$stat_url['host'].$stat_url['path'].'?'.$stat_query;
							}
							if(isset($stat['url'])) $url = $stat['url'];	// if stat returns an url use that, as there might be more links ...
							self::symlinkCache_add($path,$url);
						}
					}
				}
			}
		}
		catch (Api\Db\Exception $e) {
			// some long running operations, eg. merge-print, run into situation that DB closes our separate sqlfs connection
			// we try now to reconnect Vfs\Sqlfs\StreamWrapper once
			// it's done here in vfs_stream_wrapper as situation can happen in sqlfs, links, stylite.links or stylite.versioning
			if ($try_reconnect)
			{
				// reconnect to db
				Vfs\Sqlfs\StreamWrapper::reconnect();
				return $this->url_stat($path, $flags, $try_create_home, $check_symlink_components, $check_symlink_depth, false);
			}
			// if numer of tries is exceeded, re-throw exception
			throw $e;
		}
		// check if a failed url_stat was for a home dir, in that case silently create it
		if (!$stat && $try_create_home && Vfs::dirname(Vfs::parse_url($path,PHP_URL_PATH)) == '/home' &&
			($id = $GLOBALS['egw']->accounts->name2id(basename($path))) &&
			$GLOBALS['egw']->accounts->id2name($id) == basename($path))	// make sure path has the right case!
		{
			$hook_data = array(
				'location' => $GLOBALS['egw']->accounts->get_type($id) == 'g' ? 'addgroup' : 'addaccount',
				'account_id' => $id,
				'account_lid' => basename($path),
				'account_name' => basename($path),
			);
			call_user_func(array(__NAMESPACE__.'\\Hooks',$hook_data['location']),$hook_data);
			unset($hook_data);
			$stat = $this->url_stat($path,$flags,false);
		}
		$query = parse_url($url, PHP_URL_QUERY);
		if (!$stat && $check_symlink_components)	// check if there's a symlink somewhere inbetween the path
		{
			$stat = self::check_symlink_components($path,$flags,$url);
			if ($stat && isset($stat['url']) && !$query) self::symlinkCache_add($path,$stat['url']);
		}
		elseif(is_array($stat) && !isset($stat['url']))
		{
			$stat['url'] = $url;
		}
		if (($stat['mode'] & 0222) && self::url_is_readonly($stat['url']))
		{
			$stat['mode'] &= ~0222;
		}
		if($stat['url'] && $query && strpos($stat['url'],'?'.$query)===false)
		{
			$stat['url'] .= '?'.$query;
		}

		if (self::LOG_LEVEL > 1) error_log(__METHOD__."('$path',$flags,try_create_home=$try_create_home,check_symlink_components=$check_symlink_components) returning ".array2string($stat));

		return $stat;

		/* Todo: if we hide non readables, we should return false on url_stat for consitency (if dir is not writabel)
		// Problem: this does NOT stop (calles itself infinit recursive)!
		if (self::HIDE_UNREADABLES && !Vfs::check_access($path,Vfs::READABLE,$stat) &&
			!Vfs::check_access(Vfs::dirname($path,Vfs::WRITABLE)))
		{
			return false;
		}
		return $stat;*/
	}

	/**
	 * Check if path (which fails the stat call) contains symlinks in path-components other then the last one
	 *
	 * @param string $path
	 * @param int $flags =0 see url_stat
	 * @param string &$url=null already resolved path
	 * @return array|boolean stat array or false if not found
	 */
	private function check_symlink_components($path,$flags=0,&$url=null)
	{
		if (is_null($url) && !($url = self::resolve_url($path)))
		{
			if (self::LOG_LEVEL > 0) error_log(__METHOD__."('$path',$flags,'$url') can NOT resolve path: ".function_backtrace(1));
			return false;
		}
		if (self::LOG_LEVEL > 1) error_log(__METHOD__."('$path',$flags,'$url'): ".function_backtrace(1));

		while (($rel_path = Vfs::basename($url).($rel_path ? '/'.$rel_path : '')) &&
		       ($url = Vfs::dirname($url)))
		{
			if (($stat = $this->url_stat($url, 0, false, false)))
			{
				if (is_link($url) && ($lpath = Vfs::readlink($url)))
				{
					if (self::LOG_LEVEL > 1) $log = "rel_path='$rel_path', url='$url': lpath='$lpath'";

					if ($lpath[0] != '/')
					{
						$lpath = Vfs::concat(Vfs::parse_url($url,PHP_URL_PATH),'../'.$lpath);
					}
					//self::symlinkCache_add($path,Vfs::PREFIX.$lpath);
					$url = Vfs::PREFIX.Vfs::concat($lpath,$rel_path);
					if (self::LOG_LEVEL > 1) error_log("$log --> lpath='$lpath', url='$url'");
					return $this->url_stat($url,$flags);
				}
				$url = Vfs::concat($url,$rel_path);
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
	 * @param string $_path vfs path
	 * @param string $target target path
	 */
	static protected function symlinkCache_add($_path,$target)
	{
		$path = self::get_path($_path);

		if (isset(self::$symlink_cache[$path])) return;	// nothing to do

		if ($target[0] != '/') $target = Vfs::parse_url($target,PHP_URL_PATH);

		self::$symlink_cache[$path] = $target;

		// sort longest path first
		uksort(self::$symlink_cache, function($b, $a)
		{
			return strlen($a) - strlen($b);
		});
		if (self::LOG_LEVEL > 1) error_log(__METHOD__."($path,$target) cache now ".array2string(self::$symlink_cache));
	}

	/**
	 * Remove a resolved symlink from cache
	 *
	 * @param string $_path vfs path
	 */
	static public function symlinkCache_remove($_path)
	{
		$path = self::get_path($_path);

		unset(self::$symlink_cache[$path]);
		if (self::LOG_LEVEL > 1) error_log(__METHOD__."($path) cache now ".array2string(self::$symlink_cache));
	}

	/**
	 * Resolve a path from our symlink cache
	 *
	 * The cache is sorted from longer to shorter pathes.
	 *
	 * @param string $_path
	 * @param boolean $do_symlink =true is a direct match allowed, default yes (must be false for a lstat or readlink!)
	 * @return string target or path, if path not found
	 */
	static public function symlinkCache_resolve($_path,$do_symlink=true)
	{
		// remove vfs scheme, but no other schemes (eg. filesystem!)
		$path = self::get_path($_path);

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
	 * Normaly not necessary, as it is automatically cleared/updated, UNLESS Vfs::$user changes!
	 */
	static function clearstatcache()
	{
		self::$symlink_cache = self::$resolve_url_cache = array();
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
				!Vfs::check_access(Vfs::concat($this->opened_dir_url,$file),Vfs::READABLE)));
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
		list($app, $app_scheme) = explode('.', $scheme);
		foreach(array(
			empty($app_scheme) ? 'EGroupware\\Api\\Vfs\\'.ucfirst($scheme).'\\StreamWrapper' :	// streamwrapper in Api\Vfs
				'EGroupware\\'.ucfirst($app).'\\Vfs\\'.ucfirst($app_scheme).'\\StreamWrapper', // streamwrapper in $app\Vfs
			str_replace('.','_',$scheme).'_stream_wrapper',	// old (flat) name
		) as $class)
		{
			//error_log(__METHOD__."('$scheme') class_exists('$class')=".array2string(class_exists($class)));
			if (class_exists($class))  return $class;
		}
	}

	/**
	 * Getting the path from an url (or path) AND removing trailing slashes
	 *
	 * @param string $path url or path (might contain trailing slash from WebDAV!)
	 * @param string $only_remove_scheme =self::SCHEME if given only that scheme get's removed
	 * @return string path without training slash
	 */
	static protected function get_path($path,$only_remove_scheme=self::SCHEME)
	{
		if ($path[0] != '/' && (!$only_remove_scheme || Vfs::parse_url($path, PHP_URL_SCHEME) == $only_remove_scheme))
		{
			$path = Vfs::parse_url($path, PHP_URL_PATH);
		}
		// remove trailing slashes eg. added by WebDAV, but do NOT remove / from "sqlfs://default/"!
		if ($path != '/')
		{
			while (mb_substr($path, -1) == '/' && $path != '/' && ($path[0] == '/' || Vfs::parse_url($path, PHP_URL_PATH) != '/'))
			{
				$path = mb_substr($path,0,-1);
			}
		}
		return $path;
	}

	/**
	 * Check if url contains ro=1 parameter to mark mount readonly
	 *
	 * @param string $url
	 * @return boolean
	 */
	static function url_is_readonly($url)
	{
		static $cache = array();
		$ret =& $cache[$url];
		if (!isset($ret))
		{
			$matches = null;
			$ret = preg_match('/\?(.*&)?ro=([^&]+)/', $url, $matches) && $matches[2];
		}
		return $ret;
	}

	/**
	 * Mounts $url under $path in the vfs, called without parameter it returns the fstab
	 *
	 * The fstab is stored in the eGW configuration and used for all eGW users.
	 *
	 * @param string $url =null url of the filesystem to mount, eg. oldvfs://default/
	 * @param string $path =null path to mount the filesystem in the vfs, eg. /
	 * @param boolean $check_url =null check if url is an existing directory, before mounting it
	 * 	default null only checks if url does not contain a $ as used in $user or $pass
	 * @param boolean $persitent_mount =true create a persitent mount, or only a temprary for current request
	 * @param boolean $clear_fstab =false true clear current fstab, false (default) only add given mount
	 * @return array|boolean array with fstab, if called without parameter or true on successful mount
	 */
	static function mount($url=null,$path=null,$check_url=null,$persitent_mount=true,$clear_fstab=false)
	{
		if (is_null($check_url)) $check_url = strpos($url,'$') === false;

		if (!isset($GLOBALS['egw_info']['server']['vfs_fstab']))	// happens eg. in setup
		{
			$api_config = Api\Config::read('phpgwapi');
			if (isset($api_config['vfs_fstab']) && is_array($api_config['vfs_fstab']))
			{
				self::$fstab = $api_config['vfs_fstab'];
			}
			else
			{
				self::$fstab = array(
					'/' => 'sqlfs://$host/',
					'/apps' => 'links://$host/apps',
				);
			}
			unset($api_config);
		}
		if (is_null($url) || is_null($path))
		{
			if (self::LOG_LEVEL > 1) error_log(__METHOD__.'('.array2string($url).','.array2string($path).') returns '.array2string(self::$fstab));
			return self::$fstab;
		}
		if (!Vfs::$is_root)
		{
			if (self::LOG_LEVEL > 0) error_log(__METHOD__.'('.array2string($url).','.array2string($path).') permission denied, you are NOT root!');
			return false;	// only root can mount
		}
		if ($clear_fstab)
		{
			self::$fstab = array();
		}
		if (isset(self::$fstab[$path]) && self::$fstab[$path] === $url)
		{
			if (self::LOG_LEVEL > 0) error_log(__METHOD__.'('.array2string($url).','.array2string($path).') already mounted.');
			return true;	// already mounted
		}
		self::load_wrapper(Vfs::parse_url($url,PHP_URL_SCHEME));

		if ($check_url && (!file_exists($url) || opendir($url) === false))
		{
			if (self::LOG_LEVEL > 0) error_log(__METHOD__.'('.array2string($url).','.array2string($path).') url does NOT exist!');
			return false;	// url does not exist
		}
		self::$fstab[$path] = $url;

		uksort(self::$fstab, function($a, $b)
		{
			return strlen($a) - strlen($b);
		});

		if ($persitent_mount)
		{
			Api\Config::save_value('vfs_fstab',self::$fstab,'phpgwapi');
			$GLOBALS['egw_info']['server']['vfs_fstab'] = self::$fstab;
			// invalidate session cache
			if (method_exists($GLOBALS['egw'],'invalidate_session_cache'))	// egw object in setup is limited
			{
				$GLOBALS['egw']->invalidate_session_cache();
			}
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
		if (!Vfs::$is_root)
		{
			if (self::LOG_LEVEL > 0) error_log(__METHOD__.'('.array2string($path).','.array2string($path).') permission denied, you are NOT root!');
			return false;	// only root can mount
		}
		if (!isset(self::$fstab[$path]) && ($path = array_search($path,self::$fstab)) === false)
		{
			if (self::LOG_LEVEL > 0) error_log(__METHOD__.'('.array2string($path).') NOT mounted!');
			return false;	// $path not mounted
		}
		unset(self::$fstab[$path]);

		Api\Config::save_value('vfs_fstab',self::$fstab,'phpgwapi');
		$GLOBALS['egw_info']['server']['vfs_fstab'] = self::$fstab;
		// invalidate session cache
		if (method_exists($GLOBALS['egw'],'invalidate_session_cache'))	// egw object in setup is limited
		{
			$GLOBALS['egw']->invalidate_session_cache();
		}
		if (self::LOG_LEVEL > 1) error_log(__METHOD__.'('.array2string($path).') returns true (successful unmount).');
		return true;
	}

	/**
	 * Init our static properties and register this wrapper
	 *
	 */
	static function init_static()
	{
		stream_register_wrapper(self::SCHEME,__CLASS__);

		if (($fstab = $GLOBALS['egw_info']['server']['vfs_fstab']) && is_array($fstab) && count($fstab))
		{
			self::$fstab = $fstab;
		}
	}
}

StreamWrapper::init_static();
