<?php
/**
 * eGroupWare API: VFS - new DB based VFS stream wrapper
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage vfs
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2008-9 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @version $Id$
 */

/**
 * eGroupWare API: VFS - new DB based VFS stream wrapper
 *
 * The sqlfs stream wrapper has 3 operation modi:
 * - content of files is stored in the filesystem (eGW's files_dir) (default)
 * - content of files is stored as BLOB in the DB (can be enabled by mounting sqlfs://...?storage=db)
 *   please note the current (php5.2.6) problems:
 *   a) retriving files via streams does NOT work for PDO_mysql (bindColum(,,PDO::PARAM_LOB) does NOT work, string returned)
 * 		(there's a workaround implemented, but it requires to allocate memory for the whole file!)
 *   b) uploading/writing files > 1M fail on PDOStatement::execute() (setting PDO::MYSQL_ATTR_MAX_BUFFER_SIZE does NOT help)
 *      (not sure if that's a bug in PDO/PDO_mysql or an accepted limitation)
 * - content of files is versioned (and stored in the DB) NOT YET IMPLEMENTED
 * In the future it will be possible to activate eg. versioning in parts of the filesystem, via mount options in the vfs
 *
 * I use the PDO DB interface, as it allows to access BLOB's as streams (avoiding to hold them complete in memory).
 *
 * The stream wrapper interface is according to the docu on php.net
 *
 * @link http://www.php.net/manual/en/function.stream-wrapper-register.php
 * @ToDo versioning
 */
class sqlfs_stream_wrapper implements iface_stream_wrapper
{
	/**
	 * Mime type of directories, the old vfs uses 'Directory', while eg. WebDAV uses 'httpd/unix-directory'
	 */
	const DIR_MIME_TYPE = 'httpd/unix-directory';
	/**
	 * Mime type for symlinks
	 */
	const SYMLINK_MIME_TYPE = 'application/x-symlink';
	/**
	 * Scheme / protocoll used for this stream-wrapper
	 */
	const SCHEME = 'sqlfs';
	/**
	 * Does url_stat returns a mime type, or has it to be determined otherwise (string with attribute name)
	 */
	const STAT_RETURN_MIME_TYPE = 'mime';
	/**
	 * Our tablename
	 */
	const TABLE = 'egw_sqlfs';
	/**
	 * Name of our property table
	 */
	const PROPS_TABLE = 'egw_sqlfs_props';
	/**
	 * mode-bits, which have to be set for files
	 */
	const MODE_FILE = 0100000;
	/**
	 * mode-bits, which have to be set for directories
	 */
	const MODE_DIR =   040000;
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
	 * 3 = log line numbers in sql statements
	 */
	const LOG_LEVEL = 1;

	/**
	 * We do versioning AND store the content in the db, NOT YET IMPLEMENTED
	 */
	const VERSIONING = 0;
	/**
	 * We store the content in the DB (no versioning)
	 */
	const STORE2DB = 1;
	/**
	 * We store the content in the filesystem (egw_info/server/files_dir) (no versioning)
	 */
	const STORE2FS = 2;
	/**
	 * default for operation, change that if you want to test with STORE2DB atm
	 */
	const DEFAULT_OPERATION = self::STORE2FS;

	/**
	 * operation mode of the opened file
	 *
	 * @var int
	 */
	protected $operation = self::DEFAULT_OPERATION;

	/**
	 * optional context param when opening the stream, null if no context passed
	 *
	 * @var mixed
	 */
	var $context;

	/**
	 * Path off the file opened by stream_open
	 *
	 * @var string
	 */
	protected $opened_path;
	/**
	 * Mode of the file opened by stream_open
	 *
	 * @var int
	 */
	protected $opened_mode;
	/**
	 * Stream of the opened file, either from the DB via PDO or the filesystem
	 *
	 * @var resource
	 */
	protected $opened_stream;
	/**
	 * fs_id of opened file
	 *
	 * @var int
	 */
	protected $opened_fs_id;
	/**
	 * Cache containing stat-infos from previous url_stat calls AND dir_opendir calls
	 *
	 * It's values are the columns read from the DB (fs_*), not the ones returned by url_stat!
	 *
	 * @var array $path => info-array pairs
	 */
	static private $stat_cache = array();
	/**
	 * Reference to the PDO object we use
	 *
	 * @var PDO
	 */
	static private $pdo;
	/**
	 * Array with filenames of dir opened with dir_opendir
	 *
	 * @var array
	 */
	protected $opened_dir;

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
		if (self::LOG_LEVEL > 1) error_log(__METHOD__."($url,$mode,$options)");

		$path = parse_url($url,PHP_URL_PATH);
		$this->operation = self::url2operation($url);
		$dir = egw_vfs::dirname($url);

		$this->opened_path = $path;
		$this->opened_mode = $mode = str_replace('b','',$mode);	// we are always binary, like every Linux system
		$this->opened_stream = null;

		if (!($stat = self::url_stat($path,STREAM_URL_STAT_QUIET)) || $mode[0] == 'x')	// file not found or file should NOT exist
		{
			if ($mode[0] == 'r' ||	// does $mode require the file to exist (r,r+)
				$mode[0] == 'x' ||	// or file should not exist, but does
				!($dir_stat=self::url_stat($dir,STREAM_URL_STAT_QUIET)) ||	// or parent dir does not exist																																			create it
				!egw_vfs::check_access($dir,egw_vfs::WRITABLE,$dir_stat))	// or we are not allowed to 																																			create it
			{
				self::_remove_password($url);
				if (self::LOG_LEVEL) error_log(__METHOD__."($url,$mode,$options) file does not exist or can not be created!");
				if (!($options & STREAM_URL_STAT_QUIET))
				{
					trigger_error(__METHOD__."($url,$mode,$options) file does not exist or can not be created!",E_USER_WARNING);
				}
				$this->opened_stream = $this->opened_path = $this->opened_mode = null;
				return false;
			}
			// new file --> create it in the DB
			$query = 'INSERT INTO '.self::TABLE.' (fs_name,fs_dir,fs_mode,fs_uid,fs_gid,fs_created,fs_modified,fs_creator,fs_mime,fs_size'.
				') VALUES (:fs_name,:fs_dir,:fs_mode,:fs_uid,:fs_gid,:fs_created,:fs_modified,:fs_creator,:fs_mime,:fs_size)';
			if (self::LOG_LEVEL > 2) $query = '/* '.__METHOD__.': '.__LINE__.' */ '.$query;
			$stmt = self::$pdo->prepare($query);
			$values = array(
				'fs_name' => egw_vfs::basename($path),
				'fs_dir'  => $dir_stat['ino'],
				// we use the mode of the dir, so files in group dirs stay accessible by all members
				'fs_mode' => $dir_stat['mode'] & 0666,
				// for the uid we use the uid of the dir if not 0=root or the current user otherwise
				'fs_uid'  => $dir_stat['uid'] ? $dir_stat['uid'] : egw_vfs::$user,
				// we allways use the group of the dir
				'fs_gid'  => $dir_stat['gid'],
				'fs_created'  => self::_pdo_timestamp(time()),
				'fs_modified' => self::_pdo_timestamp(time()),
				'fs_creator'  => egw_vfs::$user,
				'fs_mime'     => 'application/octet-stream',	// required NOT NULL!
				'fs_size'     => 0,
			);
			if (!$stmt->execute($values) || !($this->opened_fs_id = self::$pdo->lastInsertId('egw_sqlfs_fs_id_seq')))
			{
				$this->opened_stream = $this->opened_path = $this->opened_mode = null;
				if (self::LOG_LEVEL) error_log(__METHOD__."($url,$mode,$options) execute() failed: ".self::$pdo->errorInfo());
				return false;
			}
			if ($this->operation == self::STORE2DB)
			{
				// we buffer all write operations in a temporary file, which get's written on close
				$this->opened_stream = tmpfile();
			}
			// create the hash-dirs, if they not yet exist
			elseif(!file_exists($fs_dir=dirname(self::_fs_path($this->opened_fs_id))))
			{
				$umaskbefore = umask();
				if (self::LOG_LEVEL > 1) error_log(__METHOD__." about to call mkdir for $fs_dir # Present UMASK:".decoct($umaskbefore)." called from:".function_backtrace());
				self::mkdir_recursive($fs_dir,0700,true);
			}
		}
		else
		{
			if ($mode == 'r' && !egw_vfs::check_access($url,egw_vfs::READABLE ,$stat) ||// we are not allowed to read
				$mode != 'r' && !egw_vfs::check_access($url,egw_vfs::WRITABLE,$stat))	// or edit it
			{
				self::_remove_password($url);
				$op = $mode == 'r' ? 'read' : 'edited';
				if (self::LOG_LEVEL) error_log(__METHOD__."($url,$mode,$options) file can not be $op!");
				if (!($options & STREAM_URL_STAT_QUIET))
				{
					trigger_error(__METHOD__."($url,$mode,$options) file can not be $op!",E_USER_WARNING);
				}
				$this->opened_stream = $this->opened_path = $this->opened_mode = null;
				return false;
			}
			$this->opened_fs_id = $stat['ino'];

			if ($this->operation == self::STORE2DB)
			{
				$stmt = self::$pdo->prepare($sql='SELECT fs_content FROM '.self::TABLE.' WHERE fs_id=?');
				$stmt->execute(array($stat['ino']));
				$stmt->bindColumn(1,$this->opened_stream,PDO::PARAM_LOB);
				$stmt->fetch(PDO::FETCH_BOUND);
				// hack to work around a current php bug (http://bugs.php.net/bug.php?id=40913)
				// PDOStatement::bindColumn(,,PDO::PARAM_LOB) is not working for MySQL, content is returned as string :-(
				if (is_string($this->opened_stream))
				{
					$name = md5($url);
					$GLOBALS[$name] =& $this->opened_stream; unset($this->opened_stream);
					require_once(EGW_API_INC.'/class.global_stream_wrapper.inc.php');
					$this->opened_stream = fopen('global://'.$name,'r');
					unset($GLOBALS[$name]);	// unset it, so it does not use up memory, once the stream is closed
				}
				//echo 'gettype($this->opened_stream)='; var_dump($this->opened_stream);
			}
		}
		// do we operate directly on the filesystem
		if ($this->operation == self::STORE2FS)
		{
			if (self::LOG_LEVEL > 1) error_log(__METHOD__." fopen (may create a directory? mkdir) ($this->opened_fs_id,$mode,$options)");
			$this->opened_stream = fopen(self::_fs_path($this->opened_fs_id),$mode);
		}
		if ($mode[0] == 'a')	// append modes: a, a+
		{
			$this->stream_seek(0,SEEK_END);
		}
		if (!is_resource($this->opened_stream)) error_log(__METHOD__."($url,$mode,$options) NO stream, returning false!");

		return is_resource($this->opened_stream);
	}

	/**
	 * This method is called when the stream is closed, using fclose().
	 *
	 * You must release any resources that were locked or allocated by the stream.
	 */
	function stream_close ( )
	{
		if (self::LOG_LEVEL > 1) error_log(__METHOD__."()");

		if (is_null($this->opened_path) || !is_resource($this->opened_stream) || !$this->opened_fs_id)
		{
			return false;
		}

		if ($this->opened_mode != 'r')
		{
			$this->stream_seek(0,SEEK_END);

			// we need to update the mime-type, size and content (if STORE2DB)
			$values = array(
				'fs_size' => $this->stream_tell(),
				// todo: analyse the file for the mime-type
				'fs_mime' => mime_magic::filename2mime($this->opened_path),
				'fs_id'   => $this->opened_fs_id,
				'fs_modifier' => egw_vfs::$user,
				'fs_modified' => self::_pdo_timestamp(time()),
			);

			if ($this->operation == self::STORE2FS)
			{
				$stmt = self::$pdo->prepare('UPDATE '.self::TABLE.' SET fs_size=:fs_size,fs_mime=:fs_mime,fs_modifier=:fs_modifier,fs_modified=:fs_modified WHERE fs_id=:fs_id');
			}
			else
			{
				$stmt = self::$pdo->prepare('UPDATE '.self::TABLE.' SET fs_size=:fs_size,fs_mime=:fs_mime,fs_modifier=:fs_modifier,fs_modified=:fs_modified,fs_content=:fs_content WHERE fs_id=:fs_id');
				$this->stream_seek(0,SEEK_SET);	// rewind to the start
				$stmt->bindParam('fs_content', $this->opened_stream, PDO::PARAM_LOB);
			}
			if (!($ret = $stmt->execute($values)))
			{
				error_log(__METHOD__."() execute() failed! errorInfo()=".array2string(self::$pdo->errorInfo()));
			}
		}
		$ret = fclose($this->opened_stream) && $ret;

		unset(self::$stat_cache[$this->opened_path]);
		$this->opened_stream = $this->opened_path = $this->opened_mode = $this->opend_fs_id = null;
		$this->operation = self::DEFAULT_OPERATION;

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
		if (self::LOG_LEVEL > 1) error_log(__METHOD__."($count) pos=$this->opened_pos");

		if (is_resource($this->opened_stream))
		{
			return fread($this->opened_stream,$count);
		}
		return false;
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
		if (self::LOG_LEVEL > 1) error_log(__METHOD__."($data)");

		if (is_resource($this->opened_stream))
		{
			return fwrite($this->opened_stream,$data);
		}
		return false;
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
		if (is_resource($this->opened_stream))
		{
			return feof($this->opened_stream);
		}
		return false;
	}

	/**
	 * This method is called in response to ftell() calls on the stream.
	 *
	 * @return integer current read/write position of the stream
	 */
 	function stream_tell ( )
 	{
		if (self::LOG_LEVEL > 1) error_log(__METHOD__."()");

		if (is_resource($this->opened_stream))
		{
			return ftell($this->opened_stream);
		}
		return false;
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
		if (self::LOG_LEVEL > 1) error_log(__METHOD__."($offset,$whence)");

		if (is_resource($this->opened_stream))
		{
			return fseek($this->opened_stream,$offset,$whence);
		}
		return false;
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
		if (self::LOG_LEVEL > 1) error_log(__METHOD__."()");

		if (is_resource($this->opened_stream))
		{
			return fflush($this->opened_stream);
		}
		return false;
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
		if (self::LOG_LEVEL > 1) error_log(__METHOD__."($this->opened_path)");

		return $this->url_stat($this->opened_path,0);
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
	static function unlink ( $url )
	{
		if (self::LOG_LEVEL > 1) error_log(__METHOD__."($url)");

		$path = parse_url($url,PHP_URL_PATH);

		if (!($stat = self::url_stat($path,STREAM_URL_STAT_LINK)) || !egw_vfs::check_access(dirname($path),egw_vfs::WRITABLE))
		{
			self::_remove_password($url);
			if (self::LOG_LEVEL) error_log(__METHOD__."($url) (type=$type) permission denied!");
			return false;	// no permission or file does not exist
		}
		$stmt = self::$pdo->prepare('DELETE FROM '.self::TABLE.' WHERE fs_id=:fs_id');
		unset(self::$stat_cache[$path]);

		if (($ret = $stmt->execute(array('fs_id' => $stat['ino']))))
		{
			if (self::url2operation($url) == self::STORE2FS && !($stat['mode'] & self::MODE_LINK))
			{
				unlink(self::_fs_path($stat['ino']));
			}
			// delete props
			unset($stmt);
			$stmt = self::$pdo->prepare('DELETE FROM '.self::PROPS_TABLE.' WHERE fs_id=?');
			$stmt->execute(array($stat['ino']));
		}
		return $ret;
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
	static function rename ( $url_from, $url_to )
	{
		if (self::LOG_LEVEL > 1) error_log(__METHOD__."($url_from,$url_to)");

		$path_from = parse_url($url_from,PHP_URL_PATH);
		$path_to = parse_url($url_to,PHP_URL_PATH);
		$to_dir = dirname($path_to);
		$operation = self::url2operation($url_from);

		if (!($from_stat = self::url_stat($path_from,0)) || !egw_vfs::check_access(dirname($path_from),egw_vfs::WRITABLE))
		{
			self::_remove_password($url_from);
			self::_remove_password($url_to);
			if (self::LOG_LEVEL) error_log(__METHOD__."($url_from,$url_to): $path_from permission denied!");
			return false;	// no permission or file does not exist
		}
		if (!egw_vfs::check_access($to_dir,egw_vfs::WRITABLE,$to_dir_stat = self::url_stat($to_dir,0)))
		{
			self::_remove_password($url_from);
			self::_remove_password($url_to);
			if (self::LOG_LEVEL) error_log(__METHOD__."($url_from,$url_to): $path_to permission denied!");
			return false;	// no permission or parent-dir does not exist
		}
		// the filesystem stream-wrapper does NOT allow to rename files to directories, as this makes problems
		// for our vfs too, we abort here with an error, like the filesystem one does
		if (($to_stat = self::url_stat($path_to,0)) &&
			($to_stat['mime'] === self::DIR_MIME_TYPE) !== ($from_stat['mime'] === self::DIR_MIME_TYPE))
		{
			self::_remove_password($url_from);
			self::_remove_password($url_to);
			$is_dir = $to_stat['mime'] === self::DIR_MIME_TYPE ? 'a' : 'no';
			if (self::LOG_LEVEL) error_log(__METHOD__."($url_to,$url_from) $path_to is $is_dir directory!");
			return false;	// no permission or file does not exist
		}
		// if destination file already exists, delete it
		if ($to_stat && !self::unlink($url_to,$operation))
		{
			self::_remove_password($url_to);
			if (self::LOG_LEVEL) error_log(__METHOD__."($url_to,$url_from) can't unlink existing $url_to!");
			return false;
		}
		unset(self::$stat_cache[$path_from]);
		unset(self::$stat_cache[$path_to]);

		$stmt = self::$pdo->prepare('UPDATE '.self::TABLE.' SET fs_dir=:fs_dir,fs_name=:fs_name WHERE fs_id=:fs_id');
		return $stmt->execute(array(
			'fs_dir'  => $to_dir_stat['ino'],
			'fs_name' => egw_vfs::basename($path_to),
			'fs_id'   => $from_stat['ino'],
		));
	}

	/**
	 * due to problems with recursive directory creation, we have our own here
	 */
	function mkdir_recursive($pathname, $mode, $depth=0)
	{
		$maxdepth=10;
		$depth2propagate = (int)$depth + 1;
		if ($depth2propagate > $maxdepth) return is_dir($pathname);
    	is_dir(dirname($pathname)) || self::mkdir_recursive(dirname($pathname), $mode, $depth2propagate);
    	return is_dir($pathname) || @mkdir($pathname, $mode);
	}

	/**
	 * This method is called in response to mkdir() calls on URL paths associated with the wrapper.
	 *
	 * It should attempt to create the directory specified by path.
	 * In order for the appropriate error message to be returned, do not define this method if your wrapper does not support creating directories.
	 *
	 * @param string $url
	 * @param int $mode
	 * @param int $options Posible values include STREAM_REPORT_ERRORS and STREAM_MKDIR_RECURSIVE
	 * @return boolean TRUE on success or FALSE on failure
	 */
	static function mkdir ( $url, $mode, $options )
	{
		if (self::LOG_LEVEL > 1) error_log(__METHOD__."($url,$mode,$options)");
		if (self::LOG_LEVEL > 1) error_log(__METHOD__." called from:".function_backtrace());
		$path = parse_url($url,PHP_URL_PATH);

		if (self::url_stat($path,STREAM_URL_STAT_QUIET))
		{
			self::_remove_password($url);
			if (self::LOG_LEVEL) error_log(__METHOD__."('$url',$mode,$options) already exist!");
			if (!($options & STREAM_URL_STAT_QUIET))
			{
				//throw new Exception(__METHOD__."('$url',$mode,$options) already exist!");
				trigger_error(__METHOD__."('$url',$mode,$options) already exist!",E_USER_WARNING);
			}
			return false;
		}
		$parent_path = dirname($path);
		if (($query = parse_url($url,PHP_URL_QUERY))) $parent_path .= '?'.$query;
		$parent = self::url_stat($parent_path,STREAM_URL_STAT_QUIET);

		// check if we should also create all non-existing path components and our parent does not exist,
		// if yes call ourself recursive with the parent directory
		if (($options & STREAM_MKDIR_RECURSIVE) && $parent_path != '/' && !$parent)
		{
			if (self::LOG_LEVEL > 1) error_log(__METHOD__." creating parents: $parent_path, $mode");
			if (!self::mkdir($parent_path,$mode,$options))
			{
				return false;
			}
			$parent = self::url_stat($parent_path,0);
		}
		if (!$parent || !egw_vfs::check_access($parent_path,egw_vfs::WRITABLE,$parent))
		{
			self::_remove_password($url);
			if (self::LOG_LEVEL) error_log(__METHOD__."('$url',$mode,$options) permission denied!");
			if (!($options & STREAM_URL_STAT_QUIET))
			{
				trigger_error(__METHOD__."('$url',$mode,$options) permission denied!",E_USER_WARNING);
			}
			return false;	// no permission or file does not exist
		}
		unset(self::$stat_cache[$path]);
		$stmt = self::$pdo->prepare('INSERT INTO '.self::TABLE.' (fs_name,fs_dir,fs_mode,fs_uid,fs_gid,fs_size,fs_mime,fs_created,fs_modified,fs_creator'.
					') VALUES (:fs_name,:fs_dir,:fs_mode,:fs_uid,:fs_gid,:fs_size,:fs_mime,:fs_created,:fs_modified,:fs_creator)');
		return $stmt->execute(array(
			'fs_name' => egw_vfs::basename($path),
			'fs_dir'  => $parent['ino'],
			'fs_mode' => $parent['mode'],
			'fs_uid'  => $parent['uid'],
			'fs_gid'  => $parent['gid'],
			'fs_size' => 0,
			'fs_mime' => self::DIR_MIME_TYPE,
			'fs_created'  => self::_pdo_timestamp(time()),
			'fs_modified' => self::_pdo_timestamp(time()),
			'fs_creator'  => egw_vfs::$user,
		));
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
	static function rmdir ( $url, $options )
	{
		if (self::LOG_LEVEL > 1) error_log(__METHOD__."($url)");

		$path = parse_url($url,PHP_URL_PATH);
		$parent = dirname($path);

		if (!($stat = self::url_stat($path,0)) || $stat['mime'] != self::DIR_MIME_TYPE ||
			!egw_vfs::check_access($parent,egw_vfs::WRITABLE))
		{
			self::_remove_password($url);
			if (self::LOG_LEVEL) error_log(__METHOD__."($url,$options) (type=$type) permission denied!");
			if (!($options & STREAM_URL_STAT_QUIET))
			{
				trigger_error(__METHOD__."('$url',$options) (type=$type) permission denied!",E_USER_WARNING);
			}
			return false;	// no permission or file does not exist
		}
		$stmt = self::$pdo->prepare('SELECT COUNT(*) FROM '.self::TABLE.' WHERE fs_dir=?');
		$stmt->execute(array($stat['ino']));
		if ($stmt->fetchColumn())
		{
			self::_remove_password($url);
			if (self::LOG_LEVEL) error_log(__METHOD__."($url,$options) dir is not empty!");
			if (!($options & STREAM_URL_STAT_QUIET))
			{
				trigger_error(__METHOD__."('$url',$options) dir is not empty!",E_USER_WARNING);
			}
			return false;
		}
		unset(self::$stat_cache[$path]);
		unset($stmt);	// free statement object, on some installs a new prepare fails otherwise!

		$stmt = self::$pdo->prepare('DELETE FROM '.self::TABLE.' WHERE fs_id=?');
		if (($ret = $stmt->execute(array($stat['ino']))))
		{
			self::eacl($path,null,false,$stat['ino']);	// remove all (=false) evtl. existing extended acl for that dir
			// delete props
			unset($stmt);
			$stmt = self::$pdo->prepare('DELETE FROM '.self::PROPS_TABLE.' WHERE fs_id=?');
			$stmt->execute(array($stat['ino']));
		}
		return $ret;
	}

	/**
	 * This is not (yet) a stream-wrapper function, but it's necessary and can be used static
	 *
	 * @param string $url
	 * @param int $time=null modification time (unix timestamp), default null = current time
	 * @param int $atime=null access time (unix timestamp), default null = current time, not implemented in the vfs!
	 */
	static function touch($url,$time=null,$atime=null)
	{
		if (self::LOG_LEVEL > 1) error_log(__METHOD__."($url)");

		$path = parse_url($url,PHP_URL_PATH);

		if (!($stat = self::url_stat($path,STREAM_URL_STAT_QUIET)))
		{
			// file does not exist --> create an empty one
			if (!($f = fopen(self::SCHEME.'://default'.$path,'w')) || !fclose($f))
			{
				return false;
			}
			if (is_null($time))
			{
				return true;	// new (empty) file created with current mod time
			}
			$stat = self::url_stat($path,0);
		}
		unset(self::$stat_cache[$path]);
		$stmt = self::$pdo->prepare('UPDATE '.self::TABLE.' SET fs_modified=:fs_modified,fs_modifier=:fs_modifier WHERE fs_id=:fs_id');

		return $stmt->execute(array(
			'fs_modified' => self::_pdo_timestamp($time ? $time : time()),
			'fs_modifier' => egw_vfs::$user,
			'fs_id' => $stat['ino'],
		));
	}

	/**
	 * Chown command, not yet a stream-wrapper function, but necessary
	 *
	 * @param string $url
	 * @param int $owner
	 * @return boolean
	 */
	static function chown($url,$owner)
	{
		if (self::LOG_LEVEL > 1) error_log(__METHOD__."($url,$owner)");

		$path = parse_url($url,PHP_URL_PATH);

		if (!($stat = self::url_stat($path,0)))
		{
			if (self::LOG_LEVEL) error_log(__METHOD__."($url,$owner) no such file or directory!");
			trigger_error("No such file or directory $url !",E_USER_WARNING);
			return false;
		}
		if (!egw_vfs::$is_root)
		{
			if (self::LOG_LEVEL) error_log(__METHOD__."($url,$owner) only root can do that!");
			trigger_error("Only root can do that!",E_USER_WARNING);
			return false;
		}
		if ($owner < 0 || $owner && !$GLOBALS['egw']->accounts->id2name($owner))	// not a user (0 == root)
		{
			if (self::LOG_LEVEL) error_log(__METHOD__."($url,$owner) unknown (numeric) user id!");
			trigger_error(__METHOD__."($url,$owner) Unknown (numeric) user id!",E_USER_WARNING);
			//throw new Exception(__METHOD__."($url,$owner) Unknown (numeric) user id!");
			return false;
		}
		$stmt = self::$pdo->prepare('UPDATE '.self::TABLE.' SET fs_uid=:fs_uid WHERE fs_id=:fs_id');

		return $stmt->execute(array(
			'fs_uid' => (int) $owner,
			'fs_id' => $stat['ino'],
		));
	}

	/**
	 * Chgrp command, not yet a stream-wrapper function, but necessary
	 *
	 * @param string $url
	 * @param int $group
	 * @return boolean
	 */
	static function chgrp($url,$owner)
	{
		if (self::LOG_LEVEL > 1) error_log(__METHOD__."($url,$owner)");

		$path = parse_url($url,PHP_URL_PATH);

		if (!($stat = self::url_stat($path,0)))
		{
			if (self::LOG_LEVEL) error_log(__METHOD__."($url,$owner) no such file or directory!");
			trigger_error("No such file or directory $url !",E_USER_WARNING);
			return false;
		}
		if (!egw_vfs::has_owner_rights($path,$stat))
		{
			if (self::LOG_LEVEL) error_log(__METHOD__."($url,$owner) only owner or root can do that!");
			trigger_error("Only owner or root can do that!",E_USER_WARNING);
			return false;
		}
		if ($owner < 0) $owner = -$owner;	// sqlfs uses a positiv group id's!

		if ($owner && !$GLOBALS['egw']->accounts->id2name(-$owner))	// not a group
		{
			if (self::LOG_LEVEL) error_log(__METHOD__."($url,$owner) unknown (numeric) group id!");
			trigger_error("Unknown (numeric) group id!",E_USER_WARNING);
			return false;
		}
		$stmt = self::$pdo->prepare('UPDATE '.self::TABLE.' SET fs_gid=:fs_gid WHERE fs_id=:fs_id');

		return $stmt->execute(array(
			'fs_gid' => $owner,
			'fs_id' => $stat['ino'],
		));
	}

	/**
	 * Chmod command, not yet a stream-wrapper function, but necessary
	 *
	 * @param string $url
	 * @param int $mode
	 * @return boolean
	 */
	static function chmod($url,$mode)
	{
		if (self::LOG_LEVEL > 1) error_log(__METHOD__."($url,$owner)");

		$path = parse_url($url,PHP_URL_PATH);

		if (!($stat = self::url_stat($path,0)))
		{
			if (self::LOG_LEVEL) error_log(__METHOD__."($url,$owner) no such file or directory!");
			trigger_error("No such file or directory $url !",E_USER_WARNING);
			return false;
		}
		if (!egw_vfs::has_owner_rights($path,$stat))
		{
			if (self::LOG_LEVEL) error_log(__METHOD__."($url,$owner) only owner or root can do that!");
			trigger_error("Only owner or root can do that!",E_USER_WARNING);
			return false;
		}
		if (!is_numeric($mode))	// not a mode
		{
			if (self::LOG_LEVEL) error_log(__METHOD__."($url,$owner) no (numeric) mode!");
			trigger_error("No (numeric) mode!",E_USER_WARNING);
			return false;
		}
		$stmt = self::$pdo->prepare('UPDATE '.self::TABLE.' SET fs_mode=:fs_mode WHERE fs_id=:fs_id');

		return $stmt->execute(array(
			'fs_mode' => ((int) $mode) & 0777,		// we dont store the file and dir bits, give int overflow!
			'fs_id' => $stat['ino'],
		));
	}


	/**
	 * This method is called immediately when your stream object is created for examining directory contents with opendir().
	 *
	 * @param string $path URL that was passed to opendir() and that this object is expected to explore.
	 * @param $options
	 * @return booelan
	 */
	function dir_opendir ( $url, $options )
	{
		$this->opened_dir = null;

		$path = parse_url($url,PHP_URL_PATH);

		if (!($stat = self::url_stat($url,0)) || 		// dir not found
			$stat['mime'] != self::DIR_MIME_TYPE ||		// no dir
			!egw_vfs::check_access($url,egw_vfs::EXECUTABLE|egw_vfs::READABLE,$stat))	// no access
		{
			self::_remove_password($url);
			$msg = $stat['mime'] != self::DIR_MIME_TYPE ? "$url is no directory" : 'permission denied';
			if (self::LOG_LEVEL) error_log(__METHOD__."('$url',$options) $msg!");
			$this->opened_dir = null;
			return false;
		}
		$this->opened_dir = array();
		$query = 'SELECT fs_id,fs_name,fs_mode,fs_uid,fs_gid,fs_size,fs_mime,fs_created,fs_modified'.
			",CASE fs_mime WHEN '".self::SYMLINK_MIME_TYPE."' THEN fs_content ELSE NULL END AS readlink FROM ".self::TABLE.
			" WHERE fs_dir=? ORDER BY fs_mime='httpd/unix-directory' DESC, fs_name ASC";
		//if (self::LOG_LEVEL > 2) $query = '/* '.__METHOD__.': '.__LINE__.' */ '.$query;
		if (self::LOG_LEVEL > 2) $query = '/* '.__METHOD__."($url,$options)".' */ '.$query;

		$stmt = self::$pdo->prepare($query);
		$stmt->setFetchMode(PDO::FETCH_ASSOC);
		if ($stmt->execute(array($stat['ino'])))
		{
			foreach($stmt as $file)
			{
				$this->opened_dir[] = $file['fs_name'];
				self::$stat_cache[egw_vfs::concat($path,$file['fs_name'])] = $file;
			}
		}
		if (self::LOG_LEVEL > 1) error_log(__METHOD__."($url,$options): ".implode(', ',$this->opened_dir));
		reset($this->opened_dir);

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
	 * @param boolean $eacl_access=null allows extending classes to pass the value of their check_extended_acl() method (no lsb!)
	 * @return array
	 */
	static function url_stat ( $url, $flags, $eacl_access=null )
	{
		if (self::LOG_LEVEL > 1) error_log(__METHOD__."('$url',$flags)");

		$path = parse_url($url,PHP_URL_PATH);

		// webdav adds a trailing slash to dirs, which causes url_stat to NOT find the file otherwise
		if ($path != '/' && substr($path,-1) == '/')
		{
			$path = substr($path,0,-1);
		}
		// check if we already have the info from the last dir_open call, as the old vfs reads it anyway from the db
		if (self::$stat_cache && isset(self::$stat_cache[$path]) && (is_null($eacl_access) || self::$stat_cache[$path] !== false))
		{
			return self::$stat_cache[$path] ? self::_vfsinfo2stat(self::$stat_cache[$path]) : false;
		}

		if (!is_object(self::$pdo))
		{
			self::_pdo();
		}
		$base_query = 'SELECT fs_id,fs_name,fs_mode,fs_uid,fs_gid,fs_size,fs_mime,fs_created,fs_modified'.
			",CASE fs_mime WHEN '".self::SYMLINK_MIME_TYPE."' THEN fs_content ELSE NULL END AS readlink FROM ".self::TABLE.
			' WHERE fs_name'.self::$case_sensitive_equal.'? AND fs_dir=';
		$parts = explode('/',$path);

		// if we have extendes acl access to the url, we dont need and can NOT include the sql for the readable check
		if (is_null($eacl_access))
		{
			$eacl_access = self::check_extended_acl($path,egw_vfs::READABLE);	// should be static::check_extended_acl, but no lsb!
		}
		foreach($parts as $n => $name)
		{
			if ($n == 0)
			{
				$query = (int) ($path != '/');	// / always has fs_id == 1, no need to query it ($path=='/' needs fs_dir=0!)
			}
			elseif ($n < count($parts)-1)
			{
				// MySQL 5.0 has a nesting limit for subqueries
				// --> we replace the so far cumulated subqueries with their result
				// no idea about the other DBMS, but this does NOT hurt ...
				// setting the value to 7, after reports on the user list, thought MySQL 5.0.51 with MyISAM engine works up to 10
				if ($n > 1 && !(($n-1) % 7) && !($query = self::$pdo->query($query)->fetchColumn()))
				{
					if (self::LOG_LEVEL > 1)
					{
						self::_remove_password($url);
						error_log(__METHOD__."('$url',$flags) file or directory not found!");
					}
					// we also store negatives (all methods creating new files/dirs have to unset the stat-cache!)
					return self::$stat_cache[$path] = false;
				}
				$query = 'SELECT fs_id FROM '.self::TABLE.' WHERE fs_dir=('.$query.') AND fs_name'.self::$case_sensitive_equal.self::$pdo->quote($name);

				// if we are not root AND have no extended acl access, we need to make sure the user has the right to tranverse all parent directories (read-rights)
				if (!egw_vfs::$is_root && !$eacl_access)
				{
					if (!egw_vfs::$user)
					{
						self::_remove_password($url);
						if (self::LOG_LEVEL > 1) error_log(__METHOD__."('$url',$flags) permission denied, no user-id and not root!");
						return false;
					}
					$query .= ' AND '.self::_sql_readable();
				}
			}
			else
			{
				$query = str_replace('fs_name'.self::$case_sensitive_equal.'?','fs_name'.self::$case_sensitive_equal.self::$pdo->quote($name),$base_query).'('.$query.')';
			}
		}
		if (self::LOG_LEVEL > 2) $query = '/* '.__METHOD__."($url,$flags,$eacl_access)".' */ '.$query;
		//if (self::LOG_LEVEL > 2) $query = '/* '.__METHOD__.': '.__LINE__.' */ '.$query;

		if (!($result = self::$pdo->query($query)) || !($info = $result->fetch(PDO::FETCH_ASSOC)))
		{
			if (self::LOG_LEVEL > 1)
			{
				self::_remove_password($url);
				error_log(__METHOD__."('$url',$flags) file or directory not found!");
			}
			// we also store negatives (all methods creating new files/dirs have to unset the stat-cache!)
			return self::$stat_cache[$path] = false;
		}
		self::$stat_cache[$path] = $info;

		if (self::LOG_LEVEL > 1) error_log(__METHOD__."($url,$flags)=".array2string($info));
		return self::_vfsinfo2stat($info);
	}

	/**
	 * Return readable check as sql (to be AND'ed into the query), only use if !egw_vfs::$is_root
	 *
	 * @return string
	 */
	private function _sql_readable()
	{
		static $sql_read_acl;

		if (is_null($sql_read_acl))
		{
			foreach($GLOBALS['egw']->accounts->memberships(egw_vfs::$user,true) as $gid)
			{
				$memberships[] = abs($gid);	// sqlfs stores the gid's positiv
			}
			// using octal numbers with mysql leads to funny results (select 384 & 0400 --> 384 not 256=0400)
			// 256 = 0400, 32 = 040
			$sql_read_acl = '((fs_mode & 4)=4 OR (fs_mode & 256)=256 AND fs_uid='.(int)egw_vfs::$user.
				' OR (fs_mode & 32)=32 AND fs_gid IN('.implode(',',$memberships).'))';
		}
		return $sql_read_acl;
	}

	/**
	 * This method is called in response to readdir().
	 *
	 * It should return a string representing the next filename in the location opened by dir_opendir().
	 *
	 * @return string
	 */
	function dir_readdir ( )
	{
		if (self::LOG_LEVEL > 1) error_log(__METHOD__."( )");

		if (!is_array($this->opened_dir)) return false;

		$file = current($this->opened_dir); next($this->opened_dir);

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
		if (self::LOG_LEVEL > 1) error_log(__METHOD__."( )");

		if (!is_array($this->opened_dir)) return false;

		reset($this->opened_dir);

		return true;
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
		if (self::LOG_LEVEL > 1) error_log(__METHOD__."( )");

		if (!is_array($this->opened_dir)) return false;

		$this->opened_dir = null;

		return true;
	}

	/**
	 * This method is called in response to readlink().
	 *
	 * The readlink value is read by url_stat or dir_opendir and therefore cached in the stat-cache.
	 *
	 * @param string $url
	 * @return string|boolean content of the symlink or false if $url is no symlink (or not found)
	 */
	static function readlink($path)
	{
		$link = !($lstat = self::url_stat($path,STREAM_URL_STAT_LINK)) || is_null($lstat['readlink']) ? false : $lstat['readlink'];

		if (self::LOG_LEVEL > 1) error_log(__METHOD__."('$path') = $link");

		return $link;
	}

	/**
	 * Method called for symlink()
	 *
	 * @param string $target
	 * @param string $link
	 * @return boolean true on success false on error
	 */
	static function symlink($target,$link)
	{
		if (self::LOG_LEVEL > 1) error_log(__METHOD__."('$target','$link')");

		if (self::url_stat($link,0) || !($dir = dirname($link)) ||
			!egw_vfs::check_access($dir,egw_vfs::WRITABLE,$dir_stat=self::url_stat($dir,0)))
		{
			if (self::LOG_LEVEL > 0) error_log(__METHOD__."('$target','$link') returning false! (!stat('$link') || !is_writable('$dir'))");
			return false;	// $link already exists or parent dir does not
		}
		$query = 'INSERT INTO '.self::TABLE.' (fs_name,fs_dir,fs_mode,fs_uid,fs_gid,fs_created,fs_modified,fs_creator,fs_mime,fs_size,fs_content'.
			') VALUES (:fs_name,:fs_dir,:fs_mode,:fs_uid,:fs_gid,:fs_created,:fs_modified,:fs_creator,:fs_mime,:fs_size,:fs_content)';
		if (self::LOG_LEVEL > 2) $query = '/* '.__METHOD__.': '.__LINE__.' */ '.$query;
		$stmt = self::$pdo->prepare($query);
		unset(self::$stat_cache[$link]);

		return !!$stmt->execute(array(
			'fs_name' => egw_vfs::basename($link),
			'fs_dir'  => $dir_stat['ino'],
			'fs_mode' => ($dir_stat['mode'] & 0666),
			'fs_uid'  => $dir_stat['uid'] ? $dir_stat['uid'] : egw_vfs::$user,
			'fs_gid'  => $dir_stat['gid'],
			'fs_created'  => self::_pdo_timestamp(time()),
			'fs_modified' => self::_pdo_timestamp(time()),
			'fs_creator'  => egw_vfs::$user,
			'fs_mime'     => self::SYMLINK_MIME_TYPE,
			'fs_size'     => bytes($target),
			'fs_content'  => $target,
		));
	}

	private static $extended_acl;

	/**
	 * Check if extendes ACL (stored in eGW's ACL table) grants access
	 *
	 * The extended ACL is inherited, so it's valid for all subdirs and the included files!
	 * The used algorithm break on the first match. It could be used, to disallow further access.
	 *
	 * @param string $url url to check
	 * @param int $check mode to check: one or more or'ed together of: 4 = read, 2 = write, 1 = executable
	 * @return boolean
	 */
	static function check_extended_acl($url,$check)
	{
		$url_path = parse_url($url,PHP_URL_PATH);

		if (is_null(self::$extended_acl))
		{
			self::_read_extended_acl();
		}
		$access = false;
		foreach(self::$extended_acl as $path => $rights)
		{
			if ($path == $url_path || substr($url_path,0,strlen($path)+1) == $path.'/')
			{
				$access = ($rights & $check) == $check;
				break;
			}
		}
		if (self::LOG_LEVEL > 1) error_log(__METHOD__."($url,$check) ".($access?"access granted by $path=$rights":'no access!!!'));
		return $access;
	}

	/**
	 * Read the extended acl via acl::get_grants('sqlfs')
	 *
	 */
	private static function _read_extended_acl()
	{
		if ((self::$extended_acl = $GLOBALS['egw']->session->appsession('extended_acl',self::EACL_APPNAME)) != false)
		{
			return;		// ext. ACL read from session.
		}
		self::$extended_acl = array();
		if (($rights = $GLOBALS['egw']->acl->get_all_location_rights(egw_vfs::$user,self::EACL_APPNAME)))
		{
			$pathes = self::id2path(array_keys($rights));
		}
		foreach($rights as $fs_id => $right)
		{
			$path = $pathes[$fs_id];
			if (isset($path))
			{
				self::$extended_acl[$path] = (int)$right;
			}
		}
		// sort by length descending, to allow more specific pathes to have precedence
		uksort(self::$extended_acl,create_function('$a,$b','return strlen($b)-strlen($a);'));
		$GLOBALS['egw']->session->appsession('extended_acl',self::EACL_APPNAME,self::$extended_acl);
		if (self::LOG_LEVEL > 1) error_log(__METHOD__.'() '.array2string(self::$extended_acl));
	}

	/**
	 * Appname used with the acl class to store the extended acl
	 */
	const EACL_APPNAME = 'sqlfs';

	/**
	 * Set or delete extended acl for a given path and owner (or delete  them if is_null($rights)
	 *
	 * Only root, the owner of the path or an eGW admin (only if there's no owner but a group) are allowd to set eACL's!
	 *
	 * @param string $path string with path
	 * @param int $rights=null rights to set, or null to delete the entry
	 * @param int/boolean $owner=null owner for whom to set the rights, null for the current user, or false to delete all rights for $path
	 * @param int $fs_id=null fs_id to use, to not query it again (eg. because it's already deleted)
	 * @return boolean true if acl is set/deleted, false on error
	 */
	static function eacl($path,$rights=null,$owner=null,$fs_id=null)
	{
		if ($path[0] != '/')
		{
			$path = parse_url($path,PHP_URL_PATH);
		}
		if (is_null($fs_id))
		{
			if (!($stat = self::url_stat($path,0)))
			{
				if (self::LOG_LEVEL) error_log(__METHOD__."($path,$rights,$owner,$fs_id) no such file or directory!");
				return false;	// $path not found
			}
			if (!egw_vfs::has_owner_rights($path,$stat))		// not group dir and user is eGW admin
			{
				if (self::LOG_LEVEL) error_log(__METHOD__."($path,$rights,$owner,$fs_id) permission denied!");
				return false;	// permission denied
			}
			$fs_id = $stat['ino'];
		}
		if (is_null($owner))
		{
			$owner = egw_vfs::$user;
		}
		if (is_null($rights) || $owner === false)
		{
			// delete eacl
			if (is_null($owner) || $owner == egw_vfs::$user)
			{
				unset(self::$extended_acl[$path]);
			}
			$ret = $GLOBALS['egw']->acl->delete_repository(self::EACL_APPNAME,$fs_id,(int)$owner);
		}
		else
		{
			if (isset(self::$extended_acl) && ($owner == egw_vfs::$user ||
				$owner < 0 && egw_vfs::$user && in_array($owner,$GLOBALS['egw']->accounts->memberships(egw_vfs::$user,true))))
			{
				// set rights for this class, if applicable
				self::$extended_acl[$path] = $rights;
			}
			$ret = $GLOBALS['egw']->acl->add_repository(self::EACL_APPNAME,$fs_id,$owner,$rights);
		}
		if ($ret)
		{
			$GLOBALS['egw']->session->appsession('extended_acl',self::EACL_APPNAME,self::$extended_acl);
		}
		if (self::LOG_LEVEL > 1) error_log(__METHOD__."($path,$rights,$owner,$fs_id)=".(int)$ret);
		return $ret;
	}

	/**
	 * Get all ext. ACL set for a path
	 *
	 * Calls itself recursive, to get the parent directories
	 *
	 * @param string $path
	 * @return array/boolean array with array('path'=>$path,'owner'=>$owner,'rights'=>$rights) or false if $path not found
	 */
	function get_eacl($path)
	{
		if (!($stat = self::url_stat($path,STREAM_URL_STAT_QUIET)))
		{
			return false;	// not found
		}
		$eacls = array();
		foreach($GLOBALS['egw']->acl->get_all_rights($stat['ino'],self::EACL_APPNAME) as $owner => $rights)
		{
			$eacls[] = array(
				'path'   => $path,
				'owner'  => $owner,
				'rights' => $rights,
				'ino'    => $stat['ino'],
			);
		}
		if (($path = egw_vfs::dirname($path)))
		{
			return array_merge(self::get_eacl($path),$eacls);
		}
		// sort by length descending, to show precedence
		usort($eacls,create_function('$a,$b','return strlen($b["path"])-strlen($a["path"]);'));

		return $eacls;
	}

	/**
	 * Return the path of given fs_id(s)
	 *
	 * Searches the stat_cache first and then the db.
	 * Calls itself recursive to to determine the path of the parent/directory
	 *
	 * @param int/array $fs_ids integer fs_id or array of them
	 * @return string/array path or array or pathes indexed by fs_id
	 */
	static function id2path($fs_ids)
	{
		if (self::LOG_LEVEL > 1) error_log(__METHOD__.'('.array2string($fs_id).')');
		$ids = (array)$fs_ids;
		$pathes = array();
		// first check our stat-cache for the ids
		foreach(self::$stat_cache as $path => $stat)
		{
			if (($key = array_search($stat['fs_id'],$ids)) !== false)
			{
				$pathes[$stat['fs_id']] = $path;
				unset($ids[$key]);
				if (!$ids)
				{
					if (self::LOG_LEVEL > 1) error_log(__METHOD__.'('.array2string($fs_ids).')='.array2string($pathes).' *from stat_cache*');
					return is_array($fs_ids) ? $pathes : array_shift($pathes);
				}
			}
		}
		// now search via the database
		if (count($ids) > 1) array_map(create_function('&$v','$v = (int)$v;'),$ids);
		$query = 'SELECT fs_id,fs_dir,fs_name FROM '.self::TABLE.' WHERE fs_id'.
			(count($ids) == 1 ? '='.(int)$ids[0] : ' IN ('.implode(',',$ids).')');
		if (self::LOG_LEVEL > 2) $query = '/* '.__METHOD__.': '.__LINE__.' */ '.$query;

		if (!is_object(self::$pdo))
		{
			self::_pdo();
		}
		$stmt = self::$pdo->prepare($query);
		$stmt->setFetchMode(PDO::FETCH_ASSOC);
		if (!$stmt->execute())
		{
			return false;	// not found
		}
		$parents = array();
		foreach($stmt as $row)
		{
			if ($row['fs_dir'] > 1 && !in_array($row['fs_dir'],$parents))
			{
				$parents[] = $row['fs_dir'];
			}
			$rows[$row['fs_id']] = $row;
		}
		unset($stmt);

		if ($parents && !($parents = self::id2path($parents)))
		{
			return false;	// parent not found, should never happen ...
		}
		if (self::LOG_LEVEL > 1) error_log(__METHOD__." trying foreach with:".print_r($rows,true)."#");
		foreach((array)$rows as $fs_id => $row)
		{
			$parent = $row['fs_dir'] > 1 ? $parents[$row['fs_dir']] : '';

			$pathes[$fs_id] = $parent . '/' . $row['fs_name'];
		}
		if (self::LOG_LEVEL > 1) error_log(__METHOD__.'('.array2string($fs_ids).')='.array2string($pathes));
		return is_array($fs_ids) ? $pathes : array_shift($pathes);
	}

	/**
	 * Convert a sqlfs-file-info into a stat array
	 *
	 * @param array $info
	 * @return array
	 */
	static private function _vfsinfo2stat($info)
	{
		$stat = array(
			'ino'   => $info['fs_id'],
			'name'  => $info['fs_name'],
			'mode'  => $info['fs_mode'] |
				($info['fs_mime'] == self::DIR_MIME_TYPE ? self::MODE_DIR :
				($info['fs_mime'] == self::SYMLINK_MIME_TYPE ? self::MODE_LINK : self::MODE_FILE)),	// required by the stream wrapper
			'size'  => $info['fs_size'],
			'uid'   => $info['fs_uid'],
			'gid'   => $info['fs_gid'],
			'mtime' => strtotime($info['fs_modified']),
			'ctime' => strtotime($info['fs_created']),
			'nlink' => $info['fs_mime'] == self::DIR_MIME_TYPE ? 2 : 1,
			// eGW addition to return some extra values
			'mime'  => $info['fs_mime'],
			'readlink' => $info['readlink'],
		);
		if (self::LOG_LEVEL > 1) error_log(__METHOD__."($info[name]) = ".array2string($stat));
		return $stat;
	}

	private static $pdo_type;
	/**
	 * Case sensitive comparison operator, for mysql we use ' COLLATE utf8_bin ='
	 *
	 * @var string
	 */
	public static $case_sensitive_equal = '=';

	/**
	 * Create pdo object / connection, as long as pdo is not generally used in eGW
	 *
	 * @return PDO
	 */
	static private function _pdo()
	{
		$egw_db = isset($GLOBALS['egw_setup']) ? $GLOBALS['egw_setup']->db : $GLOBALS['egw']->db;

		switch($egw_db->Type)
		{
			case 'mysqli':
			case 'mysqlt':
			case 'mysql':
				self::$case_sensitive_equal = '= BINARY ';
				self::$pdo_type = 'mysql';
				break;
			default:
				self::$pdo_type = $egw_db->Type;
				break;
		}
		switch($type)
		{
			default:
				$dsn = self::$pdo_type.':host='.$egw_db->Host.';port='.$egw_db->Port.';dbname='.$egw_db->Database;
				break;
		}
		// check once if pdo extension and DB specific driver is loaded or can be loaded
		static $pdo_available;
		if (is_null($pdo_available))
		{
			foreach(array('pdo','pdo_'.self::$pdo_type) as $ext)
			{
				check_load_extension($ext,true);	// true = throw Exception
			}
			$pdo_available = true;
		}
		try {
			self::$pdo = new PDO($dsn,$egw_db->User,$egw_db->Password);
		} catch(Exception $e) {

			// Exception reveals password, so we ignore the exception and connect again without pw, to get the right exception without pw
			self::$pdo = new PDO($dsn,$egw_db->User,'$egw_db->Password');
		}
		// set client charset of the connection
		$charset = $GLOBALS['egw']->translation->charset();
		switch(self::$pdo_type)
		{
			case 'mysql':
				if (isset($egw_db->Link_ID->charset2mysql[$charset])) $charset = $egw_db->Link_ID->charset2mysql[$charset];
				// fall throught
			case 'pgsql':
				$query = "SET NAMES '$charset'";
				break;
		}
		if ($query)
		{
			self::$pdo->exec($query);
		}
		return self::$pdo;
	}

	/**
	 * Just a little abstration 'til I know how to organise stuff like that with PDO
	 *
	 * @param mixed $time
	 * @return string Y-m-d H:i:s
	 */
	static private function _pdo_timestamp($time)
	{
		if (is_numeric($time))
		{
			$time = date('Y-m-d H:i:s',$time);
		}
		return $time;
	}

	/**
	 * Maximum value for a single hash element (should be 10^N): 10, 100 (default), 1000, ...
	 *
	 * DONT change this value, once you have files stored, they will no longer be found!
	 */
	const HASH_MAX = 100;

	/**
	 * Return the path of the stored content of a file if $this->operation == self::STORE2FS
	 *
	 * To limit the number of files stored in one directory, we create a hash from the fs_id:
	 * 	1     --> /00/1
	 * 	34    --> /00/34
	 * 	123   --> /01/123
	 * 	4567  --> /45/4567
	 * 	99999 --> /09/99/99999
	 * --> so one directory contains maximum 2 * HASH_MAY entries (HASH_MAX dirs + HASH_MAX files)
	 * @param int $id id of the file
	 * @return string
	 */
	static function _fs_path($id)
	{
		if (!is_numeric($id))
		{
			throw new egw_exception_wrong_parameter(__METHOD__."(id=$id) id has to be an integer!");
		}
		if (!isset($GLOBALS['egw_info']['server']['files_dir']))
		{
			if (is_object($GLOBALS['egw_setup']->db))	// if we run under setup, query the db for the files dir
			{
				$GLOBALS['egw_info']['server']['files_dir'] = $GLOBALS['egw_setup']->db->select('egw_config','config_value',array(
					'config_name' => 'files_dir',
					'config_app' => 'phpgwapi',
				),__LINE__,__FILE__)->fetchColumn();
			}
		}
		if (!$GLOBALS['egw_info']['server']['files_dir'])
		{
			throw  new egw_exception_assertion_failed("\$GLOBALS['egw_info']['server']['files_dir'] not set!");
		}
		$hash = array();
		for ($n = $id; $n = (int) ($n / self::HASH_MAX); )
		{
			$hash[] = sprintf('%02d',$n % self::HASH_MAX);
		}
		if (!$hash) $hash[] = '00';		// we need at least one directory, to not conflict with the dir-names
		array_unshift($hash,$id);

		$path = '/sqlfs/'.implode('/',array_reverse($hash));
		//error_log(__METHOD__."($id) = '$path'");
		return $GLOBALS['egw_info']['server']['files_dir'].$path;
	}

	/**
	 * Replace the password of an url with '...' for error messages
	 *
	 * @param string &$url
	 */
	static private function _remove_password(&$url)
	{
		$parts = parse_url($url);

		if ($parts['pass'] || $parts['scheme'])
		{
			$url = $parts['scheme'].'://'.($parts['user'] ? $parts['user'].($parts['pass']?':...':'').'@' : '').
				$parts['host'].$parts['path'];
		}
	}

	/**
	 * Get storage mode from url (get parameter 'storage', eg. ?storage=db)
	 *
	 * @param string|array $url complete url or array of url-parts from parse_url
	 * @return int self::STORE2FS or self::STORE2DB
	 */
	static function url2operation($url)
	{
		$operation = self::DEFAULT_OPERATION;

		if (strpos(is_array($url) ? $url['query'] : $url,'storage=') !== false)
		{
			parse_str(is_array($url) ? $url['query'] : parse_url($url,PHP_URL_QUERY),$query);
			switch ($query['storage'])
			{
				case 'db':
					$operation = self::STORE2DB;
					break;
				case 'fs':
				default:
					$operation = self::STORE2FS;
					break;
			}
		}
		//error_log(__METHOD__."('$url') = $operation (1=DB, 2=FS)");
		return $operation;
	}

	/**
	 * Store properties for a single ressource (file or dir)
	 *
	 * @param string|int $path string with path or integer fs_id
	 * @param array $props array or array with values for keys 'name', 'ns', 'val' (null to delete the prop)
	 * @return boolean true if props are updated, false otherwise (eg. ressource not found)
	 */
	static function proppatch($path,array &$props)
	{
		if (self::LOG_LEVEL > 1) error_log(__METHOD__."(".array2string($path).','.array2string($props));
		if (!is_numeric($path))
		{
			if (!($stat = self::url_stat($path,0)))
			{
				return false;
			}
			$id = $stat['ino'];
		}
		elseif(!($path = self::id2path($id=$path)))
		{
			return false;
		}
		if (!egw_vfs::check_access($path,EGW_ACL_EDIT,$stat))
		{
			return false;	// permission denied
		}
		foreach($props as &$prop)
		{
			if (!isset($prop['ns'])) $prop['ns'] = egw_vfs::DEFAULT_PROP_NAMESPACE;

			if (!isset($prop['val']) || self::$pdo_type != 'mysql')	// for non mysql, we have to delete the prop anyway, as there's no REPLACE!
			{
				if (!isset($del_stmt))
				{
					$del_stmt = self::$pdo->prepare('DELETE FROM '.self::PROPS_TABLE.' WHERE fs_id=:fs_id AND prop_namespace=:prop_namespace AND prop_name=:prop_name');
				}
				$del_stmt->execute(array(
					'fs_id'          => $id,
					'prop_namespace' => $prop['ns'],
					'prop_name'      => $prop['name'],
				));
			}
			if (isset($prop['val']))
			{
				if (!isset($ins_stmt))
				{
					$ins_stmt = self::$pdo->prepare((self::$pdo_type == 'mysql' ? 'REPLACE' : 'INSERT').
						' INTO '.self::PROPS_TABLE.' (fs_id,prop_namespace,prop_name,prop_value) VALUES (:fs_id,:prop_namespace,:prop_name,:prop_value)');
				}
				if (!$ins_stmt->execute(array(
					'fs_id'          => $id,
					'prop_namespace' => $prop['ns'],
					'prop_name'      => $prop['name'],
					'prop_value'     => $prop['val'],
				)))
				{
					return false;
				}
			}
		}
		return true;
	}

	/**
	 * Read properties for a ressource (file, dir or all files of a dir)
	 *
	 * @param array|string|int $path_ids (array of) string with path or integer fs_id
	 * @param string $ns='http://egroupware.org/' namespace if propfind should be limited to a single one, use null for all
	 * @return array|boolean false on error ($path_ids does not exist), array with props (values for keys 'name', 'ns', 'value'), or
	 * 	fs_id/path => array of props for $depth==1 or is_array($path_ids)
	 */
	static function propfind($path_ids,$ns=egw_vfs::DEFAULT_PROP_NAMESPACE)
	{
		$ids = is_array($path_ids) ? $path_ids : array($path_ids);
		foreach($ids as &$id)
		{
			if (!is_numeric($id))
			{
				if (!($stat = self::url_stat($id,0)))
				{
					if (self::LOG_LEVEL) error_log(__METHOD__."(".array2string($path_ids).",$ns) path '$id' not found!");
					return false;
				}
				$id = $stat['ino'];
			}
		}
		if (count($ids) > 1) array_map(create_function('&$v','$v = (int)$v;'),$ids);
		$query = 'SELECT * FROM '.self::PROPS_TABLE.' WHERE (fs_id'.
			(count($ids) == 1 ? '='.(int)$ids[0] : ' IN ('.implode(',',$ids).')').')'.
			(!is_null($ns) ? ' AND prop_namespace=?' : '');
		if (self::LOG_LEVEL > 2) $query = '/* '.__METHOD__.': '.__LINE__.' */ '.$query;

		$stmt = self::$pdo->prepare($query);
		$stmt->setFetchMode(PDO::FETCH_ASSOC);
		$stmt->execute(!is_null($ns) ? array($ns) : array());

		$props = array();
		foreach($stmt as $row)
		{
			$props[$row['fs_id']][] = array(
				'val'  => $row['prop_value'],
				'name' => $row['prop_name'],
				'ns'   => $row['prop_namespace'],
			);
		}
		if (!is_array($path_ids))
		{
			$props = $props[$row['fs_id']];
		}
		elseif ($props && isset($stat))	// need to map fs_id's to pathes
		{
			foreach(self::id2path(array_keys($props)) as $id => $path)
			{
				$props[$path] =& $props[$id];
				unset($props[$id]);
			}
		}
		if (self::LOG_LEVEL > 1) foreach((array)$props as $k => $v) error_log(__METHOD__."($path_ids,$ns) $k => ".array2string($v));
		return $props;
	}
}

stream_register_wrapper(sqlfs_stream_wrapper::SCHEME ,'sqlfs_stream_wrapper');
