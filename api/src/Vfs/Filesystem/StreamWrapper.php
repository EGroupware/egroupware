<?php
/**
 * EGroupware API: VFS - stream wrapper to access the regular filesystem (setting a given user, group and mode)
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage vfs
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2008-15 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @version $Id$
 */

namespace EGroupware\Api\Vfs\Filesystem;

use EGroupware\Api;
use EGroupware\Api\Vfs;

/**
 * eGroupWare API: VFS - stream wrapper to access the regular filesystem (setting a given user, group and mode)
 *
 * This stream wrapper allows to mount parts of the regular filesystem, under specified permissions.
 * You can eg. mount an directory in the docroot to allow the Admin group to upload files there.
 *
 * This stream wrapper uses query parameters to pass certain options to it:
 * - user:  uid or user-name owning the path, default root
 * - group: gid or group-name owning the path, default root
 * - mode:  mode bit for the path, default 0005 (read and execute for nobody)
 * - exec:	false (default) = do NOT allow to upload or modify scripts, true = allow it (if docroot is mounted, this allows to run scripts!)
 * 			scripts are considered every file having a script-extension (eg. .php, .pl, .py), defined with SCRIPT_EXTENSION_PREG constant
 * - url:   download url, if NOT a regular webdav.php download should be used, eg. because directory already
 *          lies within the docroot or is mapped via an alias
 *
 * Example mount command for an uploads directory in the docroot (needs to be writable by webserver!):
 *
 * filemanager/cli.php mount --user root_admin --password secret --domain default \
 * 	'filesystem://egal/var/www/html/uploads?group=Admins&mode=075&url=http://domain.com/uploads' /uploads
 *
 * (admin / secret is username / password of setup user, "root_" prefix differenciate from regular EGw-user!)
 *
 * To correctly support characters with special meaning in url's (#?%), we urlencode them with Vfs::encodePathComponent
 * and urldecode all path again, before passing them to php's filesystem functions.
 *
 * @link http://www.php.net/manual/en/function.stream-wrapper-register.php
 */
class StreamWrapper implements Vfs\StreamWrapperIface
{

	use Vfs\UserContextTrait;

	/**
	 * Scheme / protocol used for this stream-wrapper
	 */
	const SCHEME = 'filesystem';
	/**
	 * Mime type of directories, the old vfs used 'Directory', while eg. WebDAV uses 'httpd/unix-directory'
	 */
	const DIR_MIME_TYPE = Vfs::DIR_MIME_TYPE;

	/**
	 * mode-bits, which have to be set for files
	 */
	const MODE_LINK = 0120000;
	/**
	 * mode-bits, which have to be set for files
	 */
	const MODE_FILE = 0100000;
	/**
	 * mode-bits, which have to be set for directories
	 */
	const MODE_DIR =   040000;

	/**
	 * optional context param when opening the stream, null if no context passed
	 *
	 * @var mixed
	 */
	var $context;

	/**
	 * stream / ressouce this class is opened for by stream_open
	 *
	 * @var resource
	 */
	private $opened_stream;

	/**
	 * URL of the opened stream, used to build the complete URL of files in the dir
	 *
	 * @var string
	 */
	private $opened_stream_url;

	/**
	 * directory-ressouce this class is opened for by dir_open
	 *
	 * @var resource
	 */
	private $opened_dir;

	/**
	 * URL of the opened dir, used to build the complete URL of files in the dir
	 *
	 * @var string
	 */
	private $opened_dir_url;

	/**
	 * How much should be logged to the apache error-log
	 *
	 * 0 = Nothing
	 * 1 = only errors
	 * 2 = all function calls and errors (contains passwords too!)
	 */
	const LOG_LEVEL = 1;

	/**
	 * Regular expression identifying scripts, to NOT allow updating them if exec mount option is NOT set
	 */
	const SCRIPT_EXTENSIONS_PREG = '/\.(php[0-9]*|pl|py)$/';

	/**
	 * This method is called immediately after your stream object is created.
	 *
	 * @param string $url URL that was passed to fopen() and that this object is expected to retrieve
	 * @param string $mode mode used to open the file, as detailed for fopen()
	 * @param int $options additional flags set by the streams API (or'ed together):
	 * - STREAM_USE_PATH      If path is relative, search for the resource using the include_path.
	 * - STREAM_REPORT_ERRORS If this flag is set, you are responsible for raising errors using trigger_error() during opening of the stream.
	 *                        If this flag is not set, you should not raise any errors.
	 * @param string $opened_path full path of the file/resource, if the open was successfull and STREAM_USE_PATH was set
	 * @return boolean true if the ressource was opened successful, otherwise false
	 */
	function stream_open ( $url, $mode, $options, &$opened_path )
	{
		unset($opened_path);	// not used, but required by interface

		$this->opened_stream = $this->opened_stream_url = null;
		$read_only = str_replace('b','',$mode) == 'r';

		// check access rights, based on the eGW mount perms
		if (!($stat = $this->url_stat($url,0)) || $mode[0] == 'x')	// file not found or file should NOT exist
		{
			if ($mode[0] == 'r' ||	// does $mode require the file to exist (r,r+)
				$mode[0] == 'x' ||	// or file should not exist, but does
				!($dir = Vfs::dirname($url)) ||
				!Vfs::check_access($dir,Vfs::WRITABLE,$dir_stat=$this->url_stat($dir,0)))	// or we are not allowed to 																																			create it
			{
				if (self::LOG_LEVEL) error_log(__METHOD__."($url,$mode,$options) file does not exist or can not be created!");
				if (!($options & STREAM_URL_STAT_QUIET))
				{
					trigger_error(__METHOD__."($url,$mode,$options) file does not exist or can not be created!",E_USER_WARNING);
				}
				return false;
			}
		}
		elseif (!$read_only && !Vfs::check_access($url,Vfs::WRITABLE,$stat))	// we are not allowed to edit it
		{
			if (self::LOG_LEVEL) error_log(__METHOD__."($url,$mode,$options) file can not be edited!");
			if (!($options & STREAM_URL_STAT_QUIET))
			{
				trigger_error(__METHOD__."($url,$mode,$options) file can not be edited!",E_USER_WARNING);
			}
			return false;
		}
		if (!$read_only && self::deny_script($url))
		{
			if (self::LOG_LEVEL) error_log(__METHOD__."($url,$mode,$options) permission denied, file is a script!");
			if (!($options & STREAM_URL_STAT_QUIET))
			{
				trigger_error(__METHOD__."($url,$mode,$options) permission denied, file is a script!",E_USER_WARNING);
			}
			return false;
		}

		// open the "real" file
		if (!($this->opened_stream = fopen($path=Vfs::decodePath(Vfs::parse_url($url,PHP_URL_PATH)),$mode,$options)))
		{
			if (self::LOG_LEVEL) error_log(__METHOD__."($url,$mode,$options) fopen('$path','$mode',$options) returned false!");
			return false;
		}
		$this->opened_stream_url = $url;

		return true;
	}

	/**
	 * This method is called when the stream is closed, using fclose().
	 *
	 * You must release any resources that were locked or allocated by the stream.
	 */
	function stream_close ( )
	{
		$ret = fclose($this->opened_stream);

		$this->opened_stream = null;

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
	 * @return boolean TRUE if the cached data was successfully stored (or if there was no data to store), or FALSE if the data could not be stored.
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
		return $this->url_stat($this->opened_stream_url,0);
	}

	/**
	 * This method is called in response to readlink().
	 *
	 * The readlink value is read by url_stat or dir_opendir and therefore cached in the stat-cache.
	 *
	 * @param string $path
	 * @return string|boolean content of the symlink or false if $url is no symlink (or not found)
	 */
	function readlink($path)
	{
		if (!($url = Vfs::resolve_url($path)))
		{
			return false;
		}
		return readlink(Vfs::parse_url($url, PHP_URL_PATH));
	}

	/**
	 * Method called for symlink()
	 *
	 * @param string $target
	 * @param string $link
	 * @return boolean true on success false on error
	 */
	public static function symlink($target,$link)
	{
		$_target = Vfs::resolve_url($target);
		$_link = Vfs::resolve_url($link);
		// Check target & link are both filesystem, otherwise it needs to be in Sqlfs
		$target_scheme = Vfs::parse_url($_target, PHP_URL_SCHEME);
		$link_scheme = Vfs::parse_url($_link, PHP_URL_SCHEME);
		if($target_scheme == static::SCHEME && $link_scheme == static::SCHEME)
		{
			$linked = symlink(
				Vfs::parse_url($_target,PHP_URL_PATH),
				Vfs::parse_url($_link, PHP_URL_PATH)
			);
			clearstatcache();
			return $linked && is_link($_link);
		}
		else
		{
			$sqlfs = new Vfs\Sqlfs\StreamWrapper();
			return $sqlfs->symlink($target, $link);
		}
	}

	/**
	 * This method is called in response to unlink() calls on URL paths associated with the wrapper.
	 *
	 * It should attempt to delete the item specified by path.
	 * In order for the appropriate error message to be returned, do not define this method if your wrapper does not support unlinking!
	 *
	 * @param string $url
	 * @return boolean TRUE on success or FALSE on failure
	 */
	function unlink ( $url )
	{
		$path = Vfs::decodePath(Vfs::parse_url($url,PHP_URL_PATH));

		// check access rights (file need to exist and directory need to be writable
		if (!file_exists($path) || is_dir($path) || !($dir = Vfs::dirname($url)) || !Vfs::check_access($dir,Vfs::WRITABLE))
		{
			if (self::LOG_LEVEL) error_log(__METHOD__."($url) permission denied!");
			return false;	// no permission or file does not exist
		}
		return unlink($path);
	}

	/**
	 * This method is called in response to rename() calls on URL paths associated with the wrapper.
	 *
	 * It should attempt to rename the item specified by path_from to the specification given by path_to.
	 * In order for the appropriate error message to be returned, do not define this method if your wrapper does not support renaming.
	 *
	 * The regular filesystem stream-wrapper returns an error, if $url_from and $url_to are not either both files or both dirs!
	 *
	 * @param string $url_from
	 * @param string $url_to
	 * @return boolean TRUE on success or FALSE on failure
	 */
	function rename ( $url_from, $url_to )
	{
		$from = Vfs::parse_url($url_from);
		$to   = Vfs::parse_url($url_to);

		// check access rights
		if (!($from_stat = $this->url_stat($url_from,0)) || !($dir = Vfs::dirname($url_from)) || !Vfs::check_access($dir,Vfs::WRITABLE))
		{
			if (self::LOG_LEVEL) error_log(__METHOD__."($url_from,$url_to): $from[path] permission denied!");
			return false;	// no permission or file does not exist
		}
		if (!($to_dir = Vfs::dirname($url_to)) || !Vfs::check_access($to_dir,Vfs::WRITABLE,$to_dir_stat = $this->url_stat($to_dir,0)))
		{
			if (self::LOG_LEVEL) error_log(__METHOD__."($url_from,$url_to): $to_dir permission denied!");
			return false;	// no permission or parent-dir does not exist
		}
		if (self::deny_script($url_to))
		{
			if (self::LOG_LEVEL) error_log(__METHOD__."($url_from,$url_to) permission denied, file is a script!");
			return false;
		}
		// the filesystem stream-wrapper does NOT allow to rename files to directories, as this makes problems
		// for our vfs too, we abort here with an error, like the filesystem one does
		if (($to_stat = $this->url_stat($to['path'],0)) &&
			($to_stat['mime'] === self::DIR_MIME_TYPE) !== ($from_stat['mime'] === self::DIR_MIME_TYPE))
		{
			$is_dir = $to_stat['mime'] === self::DIR_MIME_TYPE ? 'a' : 'no';
			if (self::LOG_LEVEL) error_log(__METHOD__."($url_to,$url_from) $to[path] is $is_dir directory!");
			return false;	// no permission or file does not exist
		}
		// if destination file already exists, delete it
		if ($to_stat && !self::unlink($url_to))
		{
			if (self::LOG_LEVEL) error_log(__METHOD__."($url_to,$url_from) can't unlink existing $url_to!");
			return false;
		}
		return rename(Vfs::decodePath($from['path']),Vfs::decodePath($to['path']));
	}

	/**
	 * This method is called in response to mkdir() calls on URL paths associated with the wrapper.
	 *
	 * It should attempt to create the directory specified by path.
	 * In order for the appropriate error message to be returned, do not define this method if your wrapper does not support creating directories.
	 *
	 * @param string $url
	 * @param int $mode not used, as we dont allow to change mode
	 * @param int $options Posible values include STREAM_REPORT_ERRORS and STREAM_MKDIR_RECURSIVE
	 * @return boolean TRUE on success or FALSE on failure
	 */
	function mkdir ( $url, $mode, $options )
	{
		unset($mode);	// not used, but required by interface

		$path = Vfs::decodePath(Vfs::parse_url($url,PHP_URL_PATH));
		$recursive = (bool)($options & STREAM_MKDIR_RECURSIVE);

		// find the real parent (might be more then one level if $recursive!)
		$parent = null;
		do {
			$parent = dirname($parent ?? $path);
			$parent_url = Vfs::dirname($parent_url ? $parent_url : $url);
		}
		while ($recursive && $parent != '/' && !file_exists($parent));
		//echo __METHOD__."($url,$mode,$options) path=$path, recursive=$recursive, parent=$parent, Vfs::check_access(parent_url=$parent_url,Vfs::WRITABLE)=".(int)Vfs::check_access($parent_url,Vfs::WRITABLE)."\n";

		// check access rights (in real filesystem AND by mount perms)
		if (!$parent_url || file_exists($path) || !file_exists($parent) || !is_writable($parent) || !Vfs::check_access($parent_url,Vfs::WRITABLE))
		{
			if (self::LOG_LEVEL) error_log(__METHOD__."($url) permission denied!");
			return false;
		}
		return mkdir($path, 0700, $recursive);	// setting mode 0700 allows (only) apache to write into the dir
	}

	/**
	 * This method is called in response to rmdir() calls on URL paths associated with the wrapper.
	 *
	 * It should attempt to remove the directory specified by path.
	 * In order for the appropriate error message to be returned, do not define this method if your wrapper does not support removing directories.
	 *
	 * @param string $url
	 * @param int $options Possible values include STREAM_REPORT_ERRORS.
	 * @return boolean TRUE on success or FALSE on failure.
	 */
	function rmdir ( $url, $options )
	{
		unset($options);	// not used, but required by interface

		$path = Vfs::decodePath(Vfs::parse_url($url,PHP_URL_PATH));
		$parent = dirname($path);

		// check access rights (in real filesystem AND by mount perms)
		if (!file_exists($path) || !is_writable($parent) || !($dir = Vfs::dirname($url)) || !Vfs::check_access($dir, Vfs::WRITABLE))
		{
			if (self::LOG_LEVEL) error_log(__METHOD__."($url) permission denied!");
			return false;
		}
		return rmdir($path);
	}

	/**
	 * StreamWrapper method (PHP 5.4+) for touch, chmod, chown and chgrp
	 *
	 * We only implement touch, as other functionality would require webserver to run as root.
	 *
	 * @param string $url
	 * @param int $option STREAM_META_(TOUCH|ACCESS|((OWNER|GROUP)(_NAME)?))
	 * @param array|int|string $value
	 * - STREAM_META_TOUCH array($time, $atime)
	 * - STREAM_META_ACCESS int
	 * - STREAM_(OWNER|GROUP) int
	 * - STREAM_(OWNER|GROUP)_NAME string
	 * @return boolean true on success, false on failure
	 */
	function stream_metadata($url, $option, $value)
	{
 		if ($option != STREAM_META_TOUCH)
		{
			return false;	// not implemented / supported
		}

		$path = Vfs::decodePath(Vfs::parse_url($url, PHP_URL_PATH));
		$parent = dirname($path);

		// check access rights (in real filesystem AND by mount perms)
		if (!file_exists($path) || !is_writable($parent) || !($dir = Vfs::dirname($url)) || !Vfs::check_access($dir, Vfs::WRITABLE))
		{
			if (self::LOG_LEVEL) error_log(__METHOD__."($url) permission denied!");
			return false;
		}

		array_unshift($value, $path);
		return call_user_func_array('touch', $value);
	}

	/**
	 * This method is called immediately when your stream object is created for examining directory contents with opendir().
	 *
	 * @param string $url URL that was passed to opendir() and that this object is expected to explore.
	 * @param int $options
	 * @return boolean
	 */
	function dir_opendir ( $url, $options )
	{
		if (self::LOG_LEVEL > 1) error_log(__METHOD__."($url,$options)");

		$this->opened_dir = null;

		$path = Vfs::decodePath(Vfs::parse_url($this->opened_dir_url = $url,PHP_URL_PATH));

		// ToDo: check access rights

		if (!($this->opened_dir = opendir($path)))
		{
			if (self::LOG_LEVEL > 0) error_log(__METHOD__."($url,$options) opendir('$path') failed!");
			return false;
		}
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
	 * @param string $url
	 * @param int $flags holds additional flags set by the streams API. It can hold one or more of the following values OR'd together:
	 * - STREAM_URL_STAT_LINK	For resources with the ability to link to other resource (such as an HTTP Location: forward,
	 *                          or a filesystem symlink). This flag specified that only information about the link itself should be returned,
	 *                          not the resource pointed to by the link.
	 *                          This flag is set in response to calls to lstat(), is_link(), or filetype().
	 * - STREAM_URL_STAT_QUIET	If this flag is set, your wrapper should not raise any errors. If this flag is not set,
	 *                          you are responsible for reporting errors using the trigger_error() function during stating of the path.
	 *                          stat triggers it's own warning anyway, so it makes no sense to trigger one by our stream-wrapper!
	 * @return array
	 */
	function url_stat ( $url, $flags )
	{
		$this->check_set_context($url);
		$parts = Vfs::parse_url($url);
		$path = Vfs::decodePath($parts['path']);

		$stat = $flags & STREAM_URL_STAT_LINK ? @lstat($path) : @stat($path);	// suppressed the stat failed warnings

		if ($stat)
		{
			// set owner, group and mode from mount options
			$uid = $gid = $mode = null;
			if (!self::parse_query($parts['query'],$uid,$gid,$mode))
			{
				return false;
			}
			$stat['uid'] = $stat[4] = $uid;
			$stat['gid'] = $stat[5] = $gid;
			$stat['mode'] = $stat[2] = $stat['mode'] & self::MODE_DIR ? self::MODE_DIR | $mode :
				((($stat['mode'] & self::MODE_LINK) === self::MODE_LINK ? self::MODE_LINK : self::MODE_FILE) | ($mode & ~0111));
			// write rights also depend on the write rights of the webserver
			if (!is_writable($path))
			{
				$stat['mode'] = $stat[2] = $stat['mode'] & ~0222;
			}
		}
		if (self::LOG_LEVEL > 1) error_log(__METHOD__."($url,$flags) path=$path, mount_mode=".sprintf('0%o',$mode).", mode=".sprintf('0%o',$stat['mode']).'='.Vfs::int2mode($stat['mode']));
		return $stat;
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
		do {
			$file = readdir($this->opened_dir);

			$ignore = !($file === false ||							// stop if no more dirs or
				($file != '.' && $file != '..' ));					// file not . or ..
			if (self::LOG_LEVEL > 1 && $ignore) error_log(__METHOD__.'() ignoring '.array2string($file));
		}
		while ($ignore);

		// encode special chars messing up url's
		if ($file !== false) $file = Vfs::encodePathComponent($file);

		if (self::LOG_LEVEL > 1) error_log(__METHOD__.'() returning '.array2string($file));

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
		closedir($this->opened_dir);

		$this->opened_dir = $this->extra_dirs = null;

		return true;
	}

	/**
	 * parse a query containing mount parameters: user, uid, group, gid or mode
	 *
	 * @param string|array $query query string or array returned by parse_url (key 'query' holds the value)
	 * @param int &$uid default if not set is 0=root
	 * @param int &$gid default if not set is 0=root
	 * @param int &$mode default if not set is 05 r-x for others
	 * @return boolean true on successfull parse, false on error
	 */
	static function parse_query($query,&$uid,&$gid,&$mode)
	{
		$params = null;
		parse_str(is_array($query) ? $query['query'] : $query,$params);

		// setting the default perms root.root r-x for other
		$uid = $gid = 0;
		$mode = 05;

		foreach($params as $name => $value)
		{
			switch($name)
			{
				case 'user':
					if (!is_numeric($value))
					{
						if ($name === 'root')
						{
							$value = 0;
						}
						elseif (($value = $GLOBALS['egw']->accounts->name2id($value,'account_lid','u')) === false)
						{
							error_log(__METHOD__."('$query') unknown user-name '$value'!");
							return false;	// wrong user-name
						}
					}
					// fall-through
				case 'uid':
					if (!is_numeric($value) || $value < 0 || !is_int($value) && !$GLOBALS['egw']->accounts->id2name($value))
					{
						error_log(__METHOD__."('$query') wrong numeric user-id '$value'!");
						return false;
					}
					$uid = (int)$value;
					break;
				case 'group':
					if (!is_numeric($value))
					{
						if ($name === 'root')
						{
							$value = 0;
						}
						elseif (($value = $GLOBALS['egw']->accounts->name2id($value,'account_lid','g')) === false)
						{
							error_log(__METHOD__."('$query') unknown group-name '$value'!");
							return false;	// wrong group-name
						}
						$value = -$value;	// vfs uses positiv gid's!
					}
					// fall-through
				case 'gid':
					if (!is_numeric($value) || $value < 0 || !is_int($value) && !$GLOBALS['egw']->accounts->id2name(-$value))
					{
						error_log(__METHOD__."('$query') wrong numeric group-id '$value'!");
						return false;
					}
					$gid = (int)$value;
					break;
				case 'mode':
					$mode = Vfs::mode2int($value);
					break;
				case 'url':
					// ignored, only used for download_url method
					break;
				case 'ro':
					// ignored here, as it is handled by vfs itself
					break;
				default:
					error_log(__METHOD__."('$query') unknown option '$name'!");
					break;
			}
		}
		//echo __METHOD__.'('.print_r($query,true).") uid=$uid, gid=$gid, mode=".sprintf('0%o',$mode)."\n";
		return true;
	}

	/**
	 * Check if url is a script (self::$script_extentions) and exec mount option is NOT set
	 *
	 * @param string $url
	 * @return boolean true if $url is a script AND exec is NOT set, false otherwise
	 */
	static function deny_script($url)
	{
		$parts = Vfs::parse_url($url);
		$get = null;
		parse_str($parts['query'],$get);

		$deny = !$get['exec'] && preg_match(self::SCRIPT_EXTENSIONS_PREG,$parts['path']);

		if (self::LOG_LEVEL > 1 || self::LOG_LEVEL > 0 && $deny)
		{
			error_log(__METHOD__."($url) returning ".array2string($deny));
		}
		return $deny;
	}

	/**
	 * URL to download a file
	 *
	 * We use our webdav handler as download url instead of an own download method.
	 * The webdav hander (filemanager/webdav.php) recognices eGW's session cookie and of cause understands regular GET requests.
	 *
	 * @param string $_url
	 * @param boolean $force_download =false add header('Content-disposition: filename="' . basename($path) . '"'), currently not supported!
	 * @todo get $force_download working through webdav
	 * @return string|false string with full download url or false to use default webdav.php url
	 */
	static function download_url($_url,$force_download=false)
	{
		unset($force_download);	// not used, but required by interface

		list($url,$query) = explode('?',$_url,2);
		$get = null;
		parse_str($query,$get);
		if (empty($get['url'])) return false;	// no download url given for this mount-point

		if (!($mount_url = Vfs::mount_url($_url))) return false;	// no mount url found, should not happen
		list($mount_url) = explode('?',$mount_url);

		$relpath = substr($url,strlen($mount_url));

		return Api\Framework::getUrl(Vfs::concat($get['url'],$relpath));
	}

	/**
	 * Register our stream-wrapper
	 */
	public static function register()
	{
		stream_register_wrapper(self::SCHEME, __CLASS__);
	}
}

StreamWrapper::register();
