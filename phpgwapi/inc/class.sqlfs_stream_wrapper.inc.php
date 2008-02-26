<?php
/**
 * eGroupWare API: VFS - new DB based VFS stream wrapper
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
 * eGroupWare API: VFS - new DB based VFS stream wrapper
 * 
 * The sqlfs stream wrapper has 3 operation modi:
 * - content of files is stored in the filesystem (eGW's files_dir) (default)
 * - content of files is stored as BLOB in the DB
 * - content of files is versioned (and stored in the DB) NOT YET IMPLEMENTED
 * In the future it will be possible to activate eg. versioning in parts of the filesystem, via mount options in the vfs
 * 
 * I use the PDO DB interface, as it allows to access BLOB's as streams (avoiding to hold them complete in memory).
 * 
 * The interface is according to the docu on php.net
 *  
 * @link http://de.php.net/manual/de/function.stream-wrapper-register.php
 * @ToDo compare (and optimize) performance with old vfs system (eg. via webdav)
 * @ToDo pass $operation parameter via context from vfs stream-wrappers mount table, to eg. allow to mount parts with(out) versioning
 * @ToDo versioning
 */
class sqlfs_stream_wrapper implements iface_stream_wrapper 
{
	/**
	 * Mime type of directories, the old vfs uses 'Directory', while eg. WebDAV uses 'httpd/unix-directory'
	 */
	const DIR_MIME_TYPE = 'httpd/unix-directory';
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
	 * mode-bits, which have to be set for files
	 */
	const MODE_FILE = 0100000;
	/**
	 * mode-bits, which have to be set for directories
	 */
	const MODE_DIR =   040000;
	/**
	 * How much should be logged to the apache error-log
	 *
	 * 0 = Nothing
	 * 1 = only errors
	 * 2 = all function calls and errors (contains passwords too!)
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
	const DEFAULT_OPERATION = 2;

	var $operation = self::DEFAULT_OPERATION;
	
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
	 * Directory vfs::ls() of dir opened with dir_opendir()
	 * 
	 * This static var gets overwritten by each new dir_opendir, it helps to not read the entries twice.
	 *
	 * @var array $path => info-array pairs
	 */
	static private $stat_cache;
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
		
		$this->opened_path = $path;
		$this->opened_mode = $mode;
		$this->opened_stream = null;
		
		$stat = self::url_stat($url,0);
		
		if (!($stat = self::url_stat($path,0)) || $mode[0] == 'x')	// file not found or file should NOT exist
		{
			if ($mode[0] == 'r' ||	// does $mode require the file to exist (r,r+)
				$mode[0] == 'x' ||	// or file should not exist, but does
				!egw_vfs::check_access(($dir_stat = self::url_stat(dirname($path),0)),egw_vfs::WRITABLE))	// or we are not allowed to 																																			create it
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
			if ($this->operation == self::STORE2FS)
			{
				$stmt = self::$pdo->prepare('INSERT INTO '.self::TABLE.' (fs_name,fs_dir,fs_mode,fs_uid,fs_gid,fs_created,fs_modified,fs_creator'.
					') VALUES (:fs_name,:fs_dir,:fs_mode,:fs_uid,:fs_gid,:fs_created,:fs_modified,:fs_creator)');
			}
			else
			{
				$stmt = self::$pdo->prepare('INSERT INTO '.self::TABLE.' (fs_name,fs_dir,fs_mode,fs_uid,fs_gid,fs_created,fs_modified,fs_creator,fs_content'.
					') VALUES (:fs_name,:fs_dir,:fs_mode,:fs_uid,:fs_gid,:fs_created,:fs_modified,:fs_creator,:fs_content)');
				$stmt->bindParam(':fs_content',$this->open_stream,PDO::PARAM_LOB);
			}
			$values = array(
				'fs_name' => basename($path),
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
			);
			foreach($values as $name => &$val)
			{
				$stmt->bindParam(':'.$name,$val);
			}
			$stmt->execute();
			$this->opened_fs_id = self::$pdo->lastInsertId('fs_id');
		}
		else
		{
			if ($mode != 'r' && !egw_vfs::check_access($stat,egw_vfs::WRITABLE))	// we are not allowed to edit it
			{
				self::_remove_password($url);
				if (self::LOG_LEVEL) error_log(__METHOD__."($url,$mode,$options) file can not be edited!");
				if (!($options & STREAM_URL_STAT_QUIET))
				{
					trigger_error(__METHOD__."($url,$mode,$options) file can not be edited!",E_USER_WARNING);
				}
				$this->opened_stream = $this->opened_path = $this->opened_mode = null;
				return false;				
			}
			$this->opened_fs_id = $stat['fs_id'];
		}
		// do we operate directly on the filesystem
		if ($this->operation == self::STORE2FS)
		{
			$this->opened_stream = fopen(self::_fs_path($path),$mode);
		}
		if ($mode[0] == 'a')	// append modes: a, a+
		{
			$this->stream_seek(0,SEEK_END);
		}
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
		
		if (is_null($this->opened_path) || !is_resource($this->opened_stream))
		{
			return false;
		}
		
		if ($this->opened_mode != 'r')
		{
			$this->stream_seek(0,SEEK_END);
			
			static $mime_magic;
			if (is_null($mime_magic))
			{
				$mime_magic = new mime_magic();
			}

			// we need to update the mime-type and size
			$values = array(
				':fs_size' => $this->stream_tell(),
				// todo: analyse the file for the mime-type
				':fs_mime' => $mime_magic->filename2mime($this->opened_path),
				':fs_id'   => $this->opened_fs_id,
			);
			$ret = fclose($this->opened_stream);
			
			$stmt = self::$pdo->prepare('UPDATE '.self::TABLE.' SET fs_size=:fs_size,fs_mime=:fs_mime WHERE fs_id=:fs_id');
			$stmt->execute($values);
		}
		else
		{
			$ret = fclose($this->opened_stream);
		}
		$this->opened_stream = $this->opened_path = $this->opened_mode = $this->opend_fs_id = null;

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
	static function unlink ( $url,$operation=self::DEFAULT_OPERATION )
	{
		if (self::LOG_LEVEL > 1) error_log(__METHOD__."($url)");
		
		$path = parse_url($url,PHP_URL_PATH);
		
		if (!($stat = self::url_stat($path,0)) || !($dir_stat = self::url_stat(dirname($path),0)) ||
			!egw_vfs::check_access($dir_stat,egw_vfs::WRITABLE))
		{
			self::_remove_password($url);
			if (self::LOG_LEVEL) error_log(__METHOD__."($url) (type=$type) permission denied!");
			return false;	// no permission or file does not exist
		}
		$stmt = self::$pdo->prepare('DELETE FROM '.self::TABLE.' WHERE fs_id=:fs_id');
		unset(self::$stat_cache[$path]);

		if (($ret = $stmt->execute(array(':fs_id' => $stat['ino']))) && $operation == self::STORE2FS)
		{
			unlink(self::_fs_path($path));
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
	static function rename ( $url_from, $url_to, $operation=self::DEFAULT_OPERATION )
	{
		if (self::LOG_LEVEL > 1) error_log(__METHOD__."($url_from,$url_to)");
		
		$path_from = parse_url($url_from,PHP_URL_PATH);
		$path_to = parse_url($url_to,PHP_URL_PATH);
		
		if (!($from_stat = self::url_stat($path_from,0)) ||
			!($from_dir_stat = self::url_stat(dirname($path_from),0)) || !egw_vfs::check_access($from_dir_stat,egw_vfs::WRITABLE))
		{
			self::_remove_password($url_from);
			self::_remove_password($url_to);
			if (self::LOG_LEVEL) error_log(__METHOD__."($url_from,$url_to): $path_from permission denied!");
			return false;	// no permission or file does not exist
		}
		if (!($to_dir_stat = self::url_stat(dirname($path_to),0)) || !egw_vfs::check_access($to_dir_stat,egw_vfs::WRITABLE))
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
		$stmt = self::$pdo->prepare('UPDATE '.self::TABLE.' SET fs_dir=:fs_dir,fs_name=:fs_name WHERE fs_id=:fs_id');
		if (($ret = $stmt->execute(array(
			':fs_dir'  => $to_dir_stat['ino'],
			':fs_name' => basename($path_to),
			':fs_id'   => $from_stat['ino'],
		))) && $operation == self::STORE2FS)
		{
			rename(self::_fs_path($path_from),self::_fs_path($path_to));
		}
		unset(self::$stat_cache[$path_from]);

		return $ret;
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
	static function mkdir ( $url, $mode, $options, $operation=self::DEFAULT_OPERATION )
	{
		if (self::LOG_LEVEL > 1) error_log(__METHOD__."($url,$mode,$options)");
		
		$path = parse_url($url,PHP_URL_PATH);
		
		if (self::url_stat($path,STREAM_URL_STAT_QUIET))
		{
			self::_remove_password($url);
			if (self::LOG_LEVEL) error_log(__METHOD__."('$url',$mode,$options) already exist!");
			if (!($options & STREAM_URL_STAT_QUIET))
			{
				trigger_error(__METHOD__."('$url',$mode,$options) already exist!",E_USER_WARNING);
			}
			return false;
		}

		$parent = self::url_stat(dirname($path),STREAM_URL_STAT_QUIET);

		// check if we should also create all non-existing path components and our parent does not exist, 
		// if yes call ourself recursive with the parent directory
		if (($options & STREAM_MKDIR_RECURSIVE) && $path != '/' && !$parent)
		{
			if (!self::mkdir(dirname($path),$mode,$options))
			{
				return false;
			}
			$parent = self::url_stat(dirname($path),0);
		}
		if (!$parent || !egw_vfs::check_access($parent,egw_vfs::WRITABLE))
		{
			self::_remove_password($url);
			if (self::LOG_LEVEL) error_log(__METHOD__."('$url',$mode,$options) permission denied!");
			if (!($options & STREAM_URL_STAT_QUIET))
			{
				trigger_error(__METHOD__."('$url',$mode,$options) permission denied!",E_USER_WARNING);
			}
			return false;	// no permission or file does not exist
		}
		$stmt = self::$pdo->prepare('INSERT INTO '.self::TABLE.' (fs_name,fs_dir,fs_mode,fs_uid,fs_gid,fs_size,fs_mime,fs_created,fs_modified,fs_creator'.
					') VALUES (:fs_name,:fs_dir,:fs_mode,:fs_uid,:fs_gid,:fs_size,:fs_mime,:fs_created,:fs_modified,:fs_creator)');
		if (($ret = $stmt->execute(array(
			':fs_name' => basename($path),
			':fs_dir'  => $parent['ino'],
			':fs_mode' => $parent['mode'],
			':fs_uid'  => $parent['uid'],
			':fs_gid'  => $parent['gid'],
			':fs_size' => 0,
			':fs_mime' => self::DIR_MIME_TYPE,
			':fs_created'  => self::_pdo_timestamp(time()),
			':fs_modified' => self::_pdo_timestamp(time()),
			':fs_creator'  => egw_vfs::$user,
		))) && $operation == self::STORE2FS)
		{
			mkdir(self::_fs_path($path));
		}
		return $ret;
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
	static function rmdir ( $url, $options, $operation=self::DEFAULT_OPERATION )
	{
		if (self::LOG_LEVEL > 1) error_log(__METHOD__."($url)");
		
		$path = parse_url($url,PHP_URL_PATH);
		
		if (!($stat = self::url_stat($path,0)) || $stat['mime'] != self::DIR_MIME_TYPE ||
			!($parent = self::url_stat(dirname($path),0)) || !egw_vfs::check_access($parent,egw_vfs::WRITABLE))
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

		$stmt = self::$pdo->prepare('DELETE FROM '.self::TABLE.' WHERE fs_id=?');
		if (($ret = $stmt->execute(array($stat['ino']))) &&  $operation == self::STORE2FS)
		{
			rmdir(self::_fs_path($path));
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
			if (($f = fopen(self::SCHEME.'://default'.$path,'w')) && fclose($f))
			{
				if (!is_null($time))
				{
					$stat = self::url_stat($path,0);
				}
			}
			else
			{
				return false;
			}
		}
		$stmt = self::$pdo->prepare('UPDATE '.self::TABLE.' SET fs_modified=:fs_modified WHERE fs_id=:fs_id');

		return $stmt->execute(array(
			':fs_modified' => self::_pdo_timestamp($time ? $time : time()),
			':fs_id' => $stat['ino'],
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
		if ($owner < 0 || !$GLOBALS['egw']->accounts->id2name($owner))	// not a user
		{
			if (self::LOG_LEVEL) error_log(__METHOD__."($url,$owner) unknown (numeric) user id!");
			trigger_error("Unknown (numeric) user id!",E_USER_WARNING);
			return false;
		}
		$stmt = self::$pdo->prepare('UPDATE '.self::TABLE.' SET fs_uid=:fs_uid WHERE fs_id=:fs_id');

		return $stmt->execute(array(
			':fs_uid' => (int) $owner,
			':fs_id' => $stat['ino'],
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
		if (!egw_vfs::$is_root && $stat['uid'] != egw_vfs::$user)
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
			':fs_gid' => $owner,
			':fs_id' => $stat['ino'],
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
		if (!egw_vfs::$is_root && $stat['uid'] != egw_vfs::$user)
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
			':fs_mode' => ((int) $mode) & 0777,		// we dont store the file and dir bits, give int overflow!
			':fs_id' => $stat['ino'],
		));
	}


	/**
	 * This method is called immediately when your stream object is created for examining directory contents with opendir(). 
	 * 
	 * @ToDo check all parent dirs for readable (fastest would be with sql query) !!!
	 * @param string $path URL that was passed to opendir() and that this object is expected to explore.
	 * @param $options
	 * @return booelan 
	 */
	function dir_opendir ( $url, $options )
	{
		if (self::LOG_LEVEL > 1) error_log(__METHOD__."('$url',$options)");
		
		$this->opened_dir = null;

		$path = parse_url($url,PHP_URL_PATH);
		
		if (!($stat = self::url_stat($url,0)) || 		// dir not found
			$stat['mime'] != self::DIR_MIME_TYPE ||		// no dir
			!egw_vfs::check_access($stat,egw_vfs::EXECUTABLE|egw_vfs::READABLE))	// no access
		{
			self::_remove_password($url);
			$msg = $stat['mime'] != self::DIR_MIME_TYPE ? "$url is no directory" : 'permission denied';
			if (self::LOG_LEVEL) error_log(__METHOD__."('$url',$options) $msg!");
			$this->opened_dir = null;
			return false;
		}
		self::$stat_cache = $this->opened_dir = array();
		$query = 'SELECT fs_id,fs_name,fs_mode,fs_uid,fs_gid,fs_size,fs_mime,fs_created,fs_modified FROM '.self::TABLE.' WHERE fs_dir=?';
		// only return readable files, if dir is not writable by user
		if (!egw_vfs::check_access($stat,egw_vfs::WRITABLE))
		{
			$query .= ' AND '.self::_sql_readable();
		}
		$stmt = self::$pdo->prepare($query);
		$stmt->setFetchMode(PDO::FETCH_ASSOC);
		if ($stmt->execute(array($stat['ino'])))
		{
			foreach($stmt as $file)
			{
				$this->opened_dir[] = $file['fs_name'];
				self::$stat_cache[$path.'/'.$file['fs_name']] = $file;
			}
		}
		//print_r($this->opened_dir);
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
	 * @return array 
	 */
	static function url_stat ( $url, $flags )
	{
		if (self::LOG_LEVEL > 1) error_log(__METHOD__."('$url',$flags)");
		
		$path = parse_url($url,PHP_URL_PATH);
		
		// webdav adds a trailing slash to dirs, which causes url_stat to NOT find the file otherwise
		if ($path != '/' && substr($path,-1) == '/')
		{
			$path = substr($path,0,-1);
		}
		// check if we already have the info from the last dir_open call, as the old vfs reads it anyway from the db
		if (self::$stat_cache && isset(self::$stat_cache[$path]))
		{
			return self::_vfsinfo2stat(self::$stat_cache[$path]);
		}

		if (!is_object(self::$pdo))
		{
			self::_pdo();
		}
		$base_query = 'SELECT fs_id,fs_name,fs_mode,fs_uid,fs_gid,fs_size,fs_mime,fs_created,fs_modified FROM '.self::TABLE.' WHERE fs_name=? AND fs_dir=';
		$parts = explode('/',$path);
		foreach($parts as $n => $name)
		{
			if ($n == 0)
			{
				$query = (int) ($path != '/');	// / always has fs_id == 1, no need to query it ($path=='/' needs fs_dir=0!)
			}
			elseif ($n < count($parts)-1)
			{
				$query = 'SELECT fs_id FROM '.self::TABLE.' WHERE fs_dir=('.$query.') AND fs_name='.self::$pdo->quote($name);

				// if we are not root, we need to make sure the user has the right to tranverse all partent directories (read-rights)
				if (!egw_vfs::$is_root)
				{
					$query .= ' AND '.$sql_read_acl;
				}
			}
			else
			{
				$query = str_replace('fs_name=?','fs_name='.self::$pdo->quote($name),$base_query).'('.$query.')';
			}
		}
		//echo "query=$query\n";
		
		if (!($result = self::$pdo->query($query)) || !($info = $result->fetch(PDO::FETCH_ASSOC)))
		{
			self::_remove_password($url);
			if (self::LOG_LEVEL > 1) error_log(__METHOD__."('$url',$flags) file or directory not found!");
			return false;
		}
		self::$stat_cache[$path] = $info;

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
			$sql_read_acl = '(fs_mode & 04 OR fs_mode & 0400 AND fs_uid='.(int)egw_vfs::$user.
				' OR fs_mode & 040 AND fs_gid IN('.implode(',',$memberships).'))';
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
		
		return $file ? $file : false;
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
				($info['fs_mime'] == self::DIR_MIME_TYPE ? self::MODE_DIR : self::MODE_FILE),	// required by the stream wrapper
			'size'  => $info['fs_size'],
			'uid'   => $info['fs_uid'],
			'gid'   => $info['fs_gid'],
			'mtime' => strtotime($info['fs_modified']),
			'ctime' => strtotime($info['fs_created']),
			'nlink' => $info['fs_mime'] == self::DIR_MIME_TYPE ? 2 : 1,
			// eGW addition to return the mime type
			'mime'  => $info['fs_mime'],
		);
		//error_log(__METHOD__."($info[name]) = ".print_r($stat,true));
		return $stat;
	}
	
	/**
	 * Create pdo object / connection, as long as pdo is not generally used in eGW
	 *
	 * @return PDO
	 */
	static private function _pdo()
	{
		$server =& $GLOBALS['egw_info']['server'];
		
		switch($server['db_type'])
		{
			default:
				$dsn = $server['db_type'].':host='.$server['db_host'].';dbname='.$server['db_name'];
				break;
		}
		return self::$pdo = new PDO($dsn,$server['db_user'],$server['db_pass']);
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
	 * Return the path of the stored content of a file if $this->operation == self::STORE2FS
	 *
	 * @param string $path
	 * @return string
	 */
	static private function _fs_path($path)
	{
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
}

stream_register_wrapper(sqlfs_stream_wrapper::SCHEME ,'sqlfs_stream_wrapper');
