<?php
/**
 * EGroupware API: VFS - new DB based VFS stream wrapper
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage vfs
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2008-20 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 */

namespace EGroupware\Api\Vfs\Sqlfs;

use EGroupware\Api\Vfs;
use EGroupware\Api;

/**
 * EGroupware API: VFS - new DB based VFS stream wrapper
 *
 * The sqlfs stream wrapper has 2 operation modi:
 * - content of files is stored in the filesystem (eGW's files_dir) (default)
 * - content of files is stored as BLOB in the DB (can be enabled by mounting sqlfs://...?storage=db)
 *   please note the current (php5.2.6) problems:
 *   a) retriving files via streams does NOT work for PDO_mysql (bindColum(,,\PDO::PARAM_LOB) does NOT work, string returned)
 * 		(there's a workaround implemented, but it requires to allocate memory for the whole file!)
 *   b) uploading/writing files > 1M fail on PDOStatement::execute() (setting \PDO::MYSQL_ATTR_MAX_BUFFER_SIZE does NOT help)
 *      (not sure if that's a bug in PDO/PDO_mysql or an accepted limitation)
 *
 * I use the PDO DB interface, as it allows to access BLOB's as streams (avoiding to hold them complete in memory).
 *
 * The stream wrapper interface is according to the docu on php.net
 *
 * @link http://www.php.net/manual/en/function.stream-wrapper-register.php
 */
class StreamWrapper extends Api\Db\Pdo implements Vfs\StreamWrapperIface
{
	use Vfs\UserContextTrait;

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
	 * Initial size of opened file for adjustDirSize call
	 *
	 * @var int
	 */
	protected $opened_size;
	/**
	 * Cache containing stat-infos from previous url_stat calls AND dir_opendir calls
	 *
	 * It's values are the columns read from the DB (fs_*), not the ones returned by url_stat!
	 *
	 * @var array $path => info-array pairs
	 */
	static protected $stat_cache = array();
	/**
	 * Array with filenames of dir opened with dir_opendir
	 *
	 * @var array
	 */
	protected $opened_dir;

	/**
	 * Extra columns added since the intitial introduction of sqlfs
	 *
	 * Can be set to empty, so get queries running on old versions of sqlfs, eg. for schema updates
	 *
	 * @var string;
	 */
	static public $extra_columns = ',fs_link';

	/**
	 * @var array $overwrite_new =null if set create new file with values overwriten by the given ones
	 */
	protected $overwrite_new;

	/**
	 * Clears our stat-cache
	 *
	 * Normaly not necessary, as it is automatically cleared/updated, UNLESS Vfs::$user changes!
	 *
	 * @param string $path ='/'
	 */
	public static function clearstatcache($path='/')
	{
		//error_log(__METHOD__."('$path')");
		unset($path);	// not used

		self::$stat_cache = array();

		Api\Cache::setSession(self::EACL_APPNAME, 'extended_acl', self::$extended_acl = null);
	}

	/**
	 * This method is called immediately after your stream object is created.
	 *
	 * @param string $url URL that was passed to fopen() and that this object is expected to retrieve
	 * @param string $mode mode used to open the file, as detailed for fopen()
	 * @param int $options additional flags set by the streams API (or'ed together):
	 * - STREAM_USE_PATH      If path is relative, search for the resource using the include_path.
	 * - STREAM_REPORT_ERRORS If this flag is set, you are responsible for raising errors using trigger_error() during opening of the stream.
	 *                        If this flag is not set, you should not raise any errors.
	 * @param string &$opened_path full path of the file/resource, if the open was successfull and STREAM_USE_PATH was set
	 * @return boolean true if the ressource was opened successful, otherwise false
	 */
	function stream_open ($url, $mode, $options, &$opened_path)
	{
		if (self::LOG_LEVEL > 1) error_log(__METHOD__."($url,$mode,$options)");

		$path = Vfs::parse_url($url,PHP_URL_PATH);
		$this->operation = self::url2operation($url);
		$dir = Vfs::dirname($url);

		$this->opened_path = $opened_path = $path;
		$this->opened_mode = $mode = str_replace('b','',$mode);	// we are always binary, like every Linux system
		$this->opened_stream = null;

		parse_str(parse_url($url, PHP_URL_QUERY), $this->dir_url_params);

		if (!is_null($this->overwrite_new) || !($stat = $this->url_stat($path,STREAM_URL_STAT_QUIET)) || $mode[0] == 'x')	// file not found or file should NOT exist
		{
			if (!$dir || $mode[0] == 'r' ||	// does $mode require the file to exist (r,r+)
				$mode[0] == 'x' && $stat ||	// or file should not exist, but does
				!($dir_stat=$this->url_stat($dir,STREAM_URL_STAT_QUIET)) ||	// or parent dir does not exist																																			create it
				!$this->check_access($dir,Vfs::WRITABLE, $dir_stat))	// or we are not allowed to 																																			create it
			{
				self::_remove_password($url);
				if (self::LOG_LEVEL) error_log(__METHOD__."($url,$mode,$options) file does not exist or can not be created!");
				if (($options & STREAM_REPORT_ERRORS))
				{
					trigger_error(__METHOD__."($url,$mode,$options) file does not exist or can not be created!",E_USER_WARNING);
				}
				$this->opened_stream = $this->opened_path = $this->opened_mode = null;
				return false;
			}
			// new file --> create it in the DB
			$new_file = true;
			$query = 'INSERT INTO '.self::TABLE.' (fs_name,fs_dir,fs_mode,fs_uid,fs_gid,fs_created,fs_modified,fs_creator,fs_mime,fs_size,fs_active'.
				') VALUES (:fs_name,:fs_dir,:fs_mode,:fs_uid,:fs_gid,:fs_created,:fs_modified,:fs_creator,:fs_mime,:fs_size,:fs_active)';
			if (self::LOG_LEVEL > 2) $query = '/* '.__METHOD__.': '.__LINE__.' */ '.$query;
			$stmt = self::$pdo->prepare($query);
			$values = array(
				'fs_name' => self::limit_filename(Vfs::basename($path)),
				'fs_dir'  => $dir_stat['ino'],
				// we use the mode of the dir, so files in group dirs stay accessible by all members
				'fs_mode' => $dir_stat['mode'] & 0666,
				// for the uid we use the uid of the dir if not 0=root or the current user otherwise
				'fs_uid'  => $dir_stat['uid'] ? $dir_stat['uid'] : $this->user,
				// we allways use the group of the dir
				'fs_gid'  => $dir_stat['gid'],
				'fs_created'  => self::_pdo_timestamp(time()),
				'fs_modified' => self::_pdo_timestamp(time()),
				'fs_creator'  => Vfs::$user,	// real user, not effective one / $this->user
				'fs_mime'     => 'application/octet-stream',	// required NOT NULL!
				'fs_size'     => 0,
				'fs_active'   => self::_pdo_boolean(true),
			);
			if ($this->overwrite_new) $values = array_merge($values, $this->overwrite_new);
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
			elseif(!file_exists($fs_dir=Vfs::dirname(self::_fs_path($this->opened_fs_id))))
			{
				$umaskbefore = umask();
				if (self::LOG_LEVEL > 1) error_log(__METHOD__." about to call mkdir for $fs_dir # Present UMASK:".decoct($umaskbefore)." called from:".function_backtrace());
				// if running as root eg. via (docker exec) filemanager/cli.php do NOT create dirs not readable by webserver
				self::mkdir_recursive($fs_dir,function_exists('posix_getuid') && !posix_getuid() ? 0777 : 0700,true);
			}
		}
		// check if opened file is a directory
		elseif($stat && ($stat['mode'] & self::MODE_DIR) == self::MODE_DIR)
		{
				if (self::LOG_LEVEL) error_log(__METHOD__."($url,$mode,$options) Is a directory!");
				if (($options & STREAM_REPORT_ERRORS))
				{
					trigger_error(__METHOD__."($url,$mode,$options) Is a directory!",E_USER_WARNING);
				}
				$this->opened_stream = $this->opened_path = $this->opened_mode = null;
				return false;
		}
		else
		{
			if ($mode == 'r' && !$this->check_access($url,Vfs::READABLE , $stat) ||// we are not allowed to read
				$mode != 'r' && !$this->check_access($url,Vfs::WRITABLE, $stat))	// or edit it
			{
				self::_remove_password($url);
				$op = $mode == 'r' ? 'read' : 'edited';
				if (self::LOG_LEVEL) error_log(__METHOD__."($url,$mode,$options) file can not be $op!");
				if (($options & STREAM_REPORT_ERRORS))
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
				$stmt->bindColumn(1,$this->opened_stream,\PDO::PARAM_LOB);
				$stmt->fetch(\PDO::FETCH_BOUND);
				// hack to work around a current php bug (http://bugs.php.net/bug.php?id=40913)
				// PDOStatement::bindColumn(,,\PDO::PARAM_LOB) is not working for MySQL, content is returned as string :-(
				if (is_string($this->opened_stream))
				{
					$tmp = fopen('php://temp', 'wb');
					fwrite($tmp, $this->opened_stream);
					fseek($tmp, 0, SEEK_SET);
					unset($this->opened_stream);
					$this->opened_stream = $tmp;
				}
				//echo 'gettype($this->opened_stream)='; var_dump($this->opened_stream);
			}
		}
		// do we operate directly on the filesystem --> open file from there
		if ($this->operation == self::STORE2FS)
		{
			if (self::LOG_LEVEL > 1) error_log(__METHOD__." fopen (may create a directory? mkdir) ($this->opened_fs_id,$mode,$options)");
			// if creating a new file as root eg. via (docker exec) filemanager/cli.php do NOT create files unreadable by webserver
			if (!empty($new_file) && function_exists('posix_getuid') && !posix_getuid())
			{
				umask(0666);
			}
			if (!($this->opened_stream = fopen(self::_fs_path($this->opened_fs_id),$mode)) && $new_file)
			{
				// delete db entry again, if we are not able to open a new(!) file
				unset($stmt);
				$stmt = self::$pdo->prepare('DELETE FROM '.self::TABLE.' WHERE fs_id=:fs_id');
				$stmt->execute(array('fs_id' => $this->opened_fs_id));
			}
		}
		if ($mode[0] == 'a')	// append modes: a, a+
		{
			$this->stream_seek(0,SEEK_END);
		}
		// remember initial size and directory for adjustDirSize call in close
		if (is_resource($this->opened_stream))
		{
			$this->opened_size = !empty($stat) ? $stat['size'] : 0;
			if (empty($dir_stat))
			{
				$dir_stat = $this->url_stat($dir,STREAM_URL_STAT_QUIET);
			}
			$this->opened_dir = $dir_stat['ino'];
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
			$size = $this->stream_tell();

			// we need to update the mime-type, size and content (if STORE2DB)
			$values = array(
				'fs_size' => $size,
				// todo: analyse the file for the mime-type
				'fs_mime' => Api\MimeMagic::filename2mime($this->opened_path),
				'fs_id'   => $this->opened_fs_id,
				'fs_modifier' => $this->user,
				'fs_modified' => self::_pdo_timestamp(time()),
			);

			if ($this->operation == self::STORE2FS)
			{
				$stmt = self::$pdo->prepare('UPDATE '.self::TABLE.' SET fs_size=:fs_size,fs_mime=:fs_mime,fs_modifier=:fs_modifier,fs_modified=:fs_modified WHERE fs_id=:fs_id');
				if (!($ret = $stmt->execute($values)))
				{
					error_log(__METHOD__."() execute() failed! errorInfo()=".array2string(self::$pdo->errorInfo()));
				}
			}
			else
			{
				$stmt = self::$pdo->prepare('UPDATE '.self::TABLE.' SET fs_size=:fs_size,fs_mime=:fs_mime,fs_modifier=:fs_modifier,fs_modified=:fs_modified,fs_content=:fs_content WHERE fs_id=:fs_id');
				$this->stream_seek(0,SEEK_SET);	// rewind to the start
				foreach($values as $name => &$value)
				{
					$stmt->bindParam($name,$value);
				}
				$stmt->bindParam('fs_content', $this->opened_stream, \PDO::PARAM_LOB);
				if (!($ret = $stmt->execute()))
				{
					error_log(__METHOD__."() execute() failed! errorInfo()=".array2string(self::$pdo->errorInfo()));
				}
			}
			// adjust directory size, if changed
			if ($ret && $size != $this->opened_size && $this->opened_dir)
			{
				$this->adjustDirSize($this->opened_dir, $size-$this->opened_size);
			}
		}
		else
		{
			$ret = true;
		}
		$ret = fclose($this->opened_stream) && $ret;

		unset(self::$stat_cache[$this->opened_path]);
		$this->opened_stream = $this->opened_path = $this->opened_mode = $this->opened_fs_id = null;
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
  	 * @param integer $whence	SEEK_SET - 0 - Set position equal to offset bytes
 	 * 							SEEK_CUR - 1 - Set position to current location plus offset.
 	 * 							SEEK_END - 2 - Set position to end-of-file plus offset. (To move to a position before the end-of-file, you need to pass a negative value in offset.)
 	 * @return boolean TRUE if the position was updated, FALSE otherwise.
 	 */
	function stream_seek ( $offset, $whence )
	{
		if (self::LOG_LEVEL > 1) error_log(__METHOD__."($offset,$whence)");

		if (is_resource($this->opened_stream))
		{
			return !fseek($this->opened_stream,$offset,$whence);	// fseek returns 0 on success and -1 on failure
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
		if (self::LOG_LEVEL > 1) error_log(__METHOD__."() opened_path=$this->opened_path, context=".json_encode(stream_context_get_options($this->context)));

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
	function unlink ( $url )
	{
		if (self::LOG_LEVEL > 1) error_log(__METHOD__."($url)");

		$path = Vfs::parse_url($url,PHP_URL_PATH);

		// need to get parent stat from Sqlfs, not Vfs
		$parent_stat = !($dir = Vfs::dirname($path)) ? false :
			$this->url_stat($dir, STREAM_URL_STAT_LINK);

		if (!$parent_stat || !($stat = $this->url_stat($path,STREAM_URL_STAT_LINK)) ||
			!$dir || !$this->check_access($dir, Vfs::WRITABLE, $parent_stat))
		{
			self::_remove_password($url);
			if (self::LOG_LEVEL) error_log(__METHOD__."($url) permission denied!");
			return false;	// no permission or file does not exist
		}
		if ($stat['mime'] == self::DIR_MIME_TYPE)
		{
			self::_remove_password($url);
			if (self::LOG_LEVEL) error_log(__METHOD__."($url) is NO file!");
			return false;	// no permission or file does not exist
		}
		$stmt = self::$pdo->prepare('DELETE FROM '.self::TABLE.' WHERE fs_id=:fs_id');
		unset(self::$stat_cache[$path]);

		if (($ret = $stmt->execute(array('fs_id' => $stat['ino']))))
		{
			if (self::url2operation($url) == self::STORE2FS &&
				($stat['mode'] & self::MODE_LINK) != self::MODE_LINK)
			{
				unlink(self::_fs_path($stat['ino']));
			}
			// delete props
			unset($stmt);
			$stmt = self::$pdo->prepare('DELETE FROM '.self::PROPS_TABLE.' WHERE fs_id=?');
			$stmt->execute(array($stat['ino']));

			if ($stat['mime'] !== self::SYMLINK_MIME_TYPE)
			{
				$this->adjustDirSize($parent_stat['ino'], -$stat['size']);
			}
		}
		return $ret;
	}

	/**
	 * Adjust directory sizes
	 *
	 * Adjustment is always relative, so concurrency does not matter.
	 * Adjustment is made to all parent directories too!
	 *
	 * @param int $fs_id fs_id of directory to adjust
	 * @param int $fs_size size adjustment
	 * @param bool $fs_id_is_dir=true false: $fs_id is the file causing the change (only adjust its directories)
	 */
	protected function adjustDirSize(int $fs_id, int $fs_size, bool $fs_id_is_dir=true)
	{
		if (!$fs_size) return;  // nothing to do

		static $stmt=null,$parent_stmt;
		if (!isset($stmt))
		{
			$stmt = self::$pdo->prepare('UPDATE '.self::TABLE.' SET fs_size=fs_size+:fs_size WHERE fs_id=:fs_id');
			$parent_stmt = self::$pdo->prepare('SELECT fs_dir FROM '.self::TABLE.' WHERE fs_id=:fs_id');
		}

		$max_depth = 100;
		do
		{
			if ($fs_id_is_dir || $max_depth < 100)
			{
				$stmt->execute([
					'fs_id' => $fs_id,
					'fs_size' => $fs_size,
				]);
			}
			$parent_stmt->execute(['fs_id' => $fs_id]);
		}
		while (($fs_id = $parent_stmt->fetchColumn()) && --$max_depth > 0);
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
	function rename ( $url_from, $url_to)
	{
		if (self::LOG_LEVEL > 1) error_log(__METHOD__."($url_from,$url_to)");

		$path_from = Vfs::parse_url($url_from,PHP_URL_PATH);
		$from_dir = Vfs::dirname($path_from);
		$path_to = Vfs::parse_url($url_to,PHP_URL_PATH);
		$to_dir = Vfs::dirname($path_to);

		if (!($from_stat = $this->url_stat($path_from, 0)) || !$from_dir ||
			!$this->check_access($from_dir, Vfs::WRITABLE, $from_dir_stat = $this->url_stat($from_dir, 0)))
		{
			self::_remove_password($url_from);
			self::_remove_password($url_to);
			if (self::LOG_LEVEL) error_log(__METHOD__."($url_from,$url_to): $path_from permission denied!");
			return false;	// no permission or file does not exist
		}
		if (!$to_dir || !$this->check_access($to_dir, Vfs::WRITABLE, $to_dir_stat = $this->url_stat($to_dir, 0)))
		{
			self::_remove_password($url_from);
			self::_remove_password($url_to);
			if (self::LOG_LEVEL) error_log(__METHOD__."($url_from,$url_to): $path_to permission denied!");
			return false;	// no permission or parent-dir does not exist
		}
		// the filesystem stream-wrapper does NOT allow to rename files to directories, as this makes problems
		// for our vfs too, we abort here with an error, like the filesystem one does
		if (($to_stat = $this->url_stat($path_to, 0)) &&
			($to_stat['mime'] === self::DIR_MIME_TYPE) !== ($from_stat['mime'] === self::DIR_MIME_TYPE))
		{
			self::_remove_password($url_from);
			self::_remove_password($url_to);
			$is_dir = $to_stat['mime'] === self::DIR_MIME_TYPE ? 'a' : 'no';
			if (self::LOG_LEVEL) error_log(__METHOD__."($url_to,$url_from) $path_to is $is_dir directory!");
			return false;	// no permission or file does not exist
		}
		// if destination file already exists, delete it
		if ($to_stat && !$this->unlink($url_to))
		{
			self::_remove_password($url_to);
			if (self::LOG_LEVEL) error_log(__METHOD__."($url_to,$url_from) can't unlink existing $url_to!");
			return false;
		}
		unset(self::$stat_cache[$path_from]);
		unset(self::$stat_cache[$path_to]);

		$stmt = self::$pdo->prepare('UPDATE '.self::TABLE.' SET fs_dir=:fs_dir,fs_name=:fs_name'.
			' WHERE fs_dir=:old_dir AND fs_name'.self::$case_sensitive_equal.':old_name');
		$ok = $stmt->execute(array(
			'fs_dir'   => $to_dir_stat['ino'],
			'fs_name' => self::limit_filename(Vfs::basename($path_to)),
			'old_dir'  => $from_dir_stat['ino'],
			'old_name' => $from_stat['name'],
		));
		unset($stmt);

		// check if extension changed and update mime-type in that case (as we currently determine mime-type by it's extension!)
		// fixes eg. problems with MsWord storing file with .tmp extension and then renaming to .doc
		if ($ok && ($new_mime = Vfs::mime_content_type($url_to,true)) != Vfs::mime_content_type($url_to))
		{
			//echo "<p>Vfs::nime_content_type($url_to,true) = $new_mime</p>\n";
			$stmt = self::$pdo->prepare('UPDATE '.self::TABLE.' SET fs_mime=:fs_mime WHERE fs_id=:fs_id');
			$stmt->execute(array(
				'fs_mime' => $new_mime,
				'fs_id'   => $from_stat['ino'],
			));
			unset(self::$stat_cache[$path_to]);
		}

		if ($ok && $to_dir_stat['ino'] !== $from_dir_stat['ino'] && $new_mime !== self::SYMLINK_MIME_TYPE)
		{
			$this->adjustDirSize($from_dir_stat['ino'], -$from_stat['size']);
			$this->adjustDirSize($to_dir_stat['ino'], $from_stat['size']);
		}
		return $ok;
	}

	/**
	 * due to problems with recursive directory creation, we have our own here
	 */
	protected static function mkdir_recursive($pathname, $mode, $depth=0)
	{
		$maxdepth=10;
		$depth2propagate = (int)$depth + 1;
		if ($depth2propagate > $maxdepth) return is_dir($pathname);
    	is_dir(Vfs::dirname($pathname)) || self::mkdir_recursive(Vfs::dirname($pathname), $mode, $depth2propagate);
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
	function mkdir ( $url, $mode, $options )
	{
		if (self::LOG_LEVEL > 1) error_log(__METHOD__."($url,$mode,$options)");
		if (self::LOG_LEVEL > 1) error_log(__METHOD__." called from:".function_backtrace());
		$path = Vfs::parse_url($url,PHP_URL_PATH);

		if ($this->url_stat($url,STREAM_URL_STAT_QUIET))
		{
			self::_remove_password($url);
			if (self::LOG_LEVEL) error_log(__METHOD__."('$url',$mode,$options) already exist!");
			if (!($options & STREAM_REPORT_ERRORS))
			{
				trigger_error(__METHOD__."('$url',$mode,$options) already exist!",E_USER_WARNING);
			}
			return false;
		}
		if (!($parent_path = Vfs::dirname($path)))
		{
			self::_remove_password($url);
			if (self::LOG_LEVEL) error_log(__METHOD__."('$url',$mode,$options) dirname('$path')===false!");
			if (!($options & STREAM_REPORT_ERRORS))
			{
				trigger_error(__METHOD__."('$url',$mode,$options) dirname('$path')===false!", E_USER_WARNING);
			}
			return false;
		}
		if (($query = Vfs::parse_url($url,PHP_URL_QUERY))) $parent_path .= '?'.$query;
		$parent = $this->url_stat($parent_path,STREAM_URL_STAT_QUIET);

		// check if we should also create all non-existing path components and our parent does not exist,
		// if yes call ourself recursive with the parent directory
		if (($options & STREAM_MKDIR_RECURSIVE) && $parent_path != '/' && !$parent)
		{
			if (self::LOG_LEVEL > 1) error_log(__METHOD__." creating parents: $parent_path, $mode");
			if (!$this->mkdir($parent_path,$mode,$options))
			{
				return false;
			}
			$parent = $this->url_stat($parent_path,0);
		}
		if (!$parent || !$this->check_access($parent_path,Vfs::WRITABLE, $parent))
		{
			self::_remove_password($url);
			if (self::LOG_LEVEL) error_log(__METHOD__."('$url',$mode,$options) permission denied!");
			if (!($options & STREAM_REPORT_ERRORS))
			{
				trigger_error(__METHOD__."('$url',$mode,$options) permission denied!",E_USER_WARNING);
			}
			return false;	// no permission or file does not exist
		}
		unset(self::$stat_cache[$path]);
		$stmt = self::$pdo->prepare('INSERT INTO '.self::TABLE.' (fs_name,fs_dir,fs_mode,fs_uid,fs_gid,fs_size,fs_mime,fs_created,fs_modified,fs_creator'.
					') VALUES (:fs_name,:fs_dir,:fs_mode,:fs_uid,:fs_gid,:fs_size,:fs_mime,:fs_created,:fs_modified,:fs_creator)');
		if (($ok = $stmt->execute(array(
			'fs_name' => self::limit_filename(Vfs::basename($path)),
			'fs_dir'  => $parent['ino'],
			'fs_mode' => $parent['mode'],
			'fs_uid'  => $parent['uid'],
			'fs_gid'  => $parent['gid'],
			'fs_size' => 0,
			'fs_mime' => self::DIR_MIME_TYPE,
			'fs_created'  => self::_pdo_timestamp(time()),
			'fs_modified' => self::_pdo_timestamp(time()),
			'fs_creator'  => Vfs::$user,
		))))
		{
			// check if some other process created the directory parallel to us (sqlfs would gives SQL errors later!)
			$new_fs_id = self::$pdo->lastInsertId('egw_sqlfs_fs_id_seq');

			unset($stmt);	// free statement object, on some installs a new prepare fails otherwise!

			$stmt = self::$pdo->prepare($q='SELECT COUNT(*) FROM '.self::TABLE.
				' WHERE fs_dir=:fs_dir AND fs_active=:fs_active AND fs_name'.self::$case_sensitive_equal.':fs_name');
			if ($stmt->execute(array(
				'fs_dir'  => $parent['ino'],
				'fs_active' => self::_pdo_boolean(true),
				'fs_name' => self::limit_filename(Vfs::basename($path)),
			)) && $stmt->fetchColumn() > 1)	// if there's more then one --> remove our new dir
			{
				self::$pdo->query('DELETE FROM '.self::TABLE.' WHERE fs_id='.$new_fs_id);
			}
		}
		return $ok;
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
		if (self::LOG_LEVEL > 1) error_log(__METHOD__."($url)");

		$path = Vfs::parse_url($url,PHP_URL_PATH);

		if (!($parent = Vfs::dirname($path)) ||
			!($stat = $this->url_stat($path, 0)) || $stat['mime'] != self::DIR_MIME_TYPE ||
			!$this->check_access($parent, Vfs::WRITABLE))
		{
			self::_remove_password($url);
			$err_msg = __METHOD__."($url,$options) ".(!$stat ? 'not found!' :
				($stat['mime'] != self::DIR_MIME_TYPE ? 'not a directory!' : 'permission denied!'));
			if (self::LOG_LEVEL) error_log($err_msg);
			if (!($options & STREAM_REPORT_ERRORS))
			{
				trigger_error($err_msg,E_USER_WARNING);
			}
			return false;	// no permission or file does not exist
		}
		$stmt = self::$pdo->prepare('SELECT COUNT(*) FROM '.self::TABLE.' WHERE fs_dir=?');
		$stmt->execute(array($stat['ino']));
		if ($stmt->fetchColumn())
		{
			self::_remove_password($url);
			if (self::LOG_LEVEL) error_log(__METHOD__."($url,$options) dir is not empty!");
			if (!($options & STREAM_REPORT_ERRORS))
			{
				trigger_error(__METHOD__."('$url',$options) dir is not empty!",E_USER_WARNING);
			}
			return false;
		}
		unset(self::$stat_cache[$path]);
		unset($stmt);	// free statement object, on some installs a new prepare fails otherwise!

		$del_stmt = self::$pdo->prepare('DELETE FROM '.self::TABLE.' WHERE fs_id=?');
		if (($ret = $del_stmt->execute(array($stat['ino']))))
		{
			self::eacl($path,null,false,$stat['ino']);	// remove all (=false) evtl. existing extended acl for that dir
			// delete props
			unset($del_stmt);
			$del_stmt = self::$pdo->prepare('DELETE FROM '.self::PROPS_TABLE.' WHERE fs_id=?');
			$del_stmt->execute(array($stat['ino']));
		}
		return $ret;
	}

	/**
	 * StreamWrapper method (PHP 5.4+) for touch, chmod, chown and chgrp
	 *
	 * We use protected helper methods touch, chmod, chown and chgrp to implement the functionality.
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
		if (self::LOG_LEVEL > 1) error_log(__METHOD__."($path, $option, ".array2string($value).")");

		switch($option)
		{
			case STREAM_META_TOUCH:
				return $this->touch($path, $value[0]);	// atime is not supported

			case STREAM_META_ACCESS:
				return $this->chmod($path, $value);

			case STREAM_META_OWNER_NAME:
				if (($value = $GLOBALS['egw']->accounts->name2id($value, 'account_lid', 'u')) === false)
					return false;
				// fall through
			case STREAM_META_OWNER:
				return $this->chown($path, $value);

			case STREAM_META_GROUP_NAME:
				if (($value = $GLOBALS['egw']->accounts->name2id($value, 'account_lid', 'g')) === false)
					return false;
				// fall through
			case STREAM_META_GROUP:
				return $this->chgrp($path, $value);
		}
		return false;
	}

	/**
	 * This is not (yet) a stream-wrapper function, but it's necessary and can be used static
	 *
	 * @param string $url
	 * @param int $time =null modification time (unix timestamp), default null = current time
	 * @param int $atime =null access time (unix timestamp), default null = current time, not implemented in the vfs!
	 */
	protected function touch($url,$time=null,$atime=null)
	{
		unset($atime);	// not used
		if (self::LOG_LEVEL > 1) error_log(__METHOD__."($url, $time)");

		$path = Vfs::parse_url($url,PHP_URL_PATH);

		$vfs = new self();
		if (!($stat = $vfs->url_stat($path,STREAM_URL_STAT_QUIET)))
		{
			// file does not exist --> create an empty one
			if (!($f = fopen(self::SCHEME.'://default'.$path,'w')) || !fclose($f))
			{
				return false;
			}
			if (!$time)
			{
				return true;	// new (empty) file created with current mod time
			}
			$stat = $vfs->url_stat($path,0);
		}
		unset(self::$stat_cache[$path]);
		$stmt = self::$pdo->prepare('UPDATE '.self::TABLE.' SET fs_modified=:fs_modified,fs_modifier=:fs_modifier WHERE fs_id=:fs_id');

		return $stmt->execute(array(
			'fs_modified' => self::_pdo_timestamp($time ? $time : time()),
			'fs_modifier' => $this->user,
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
	protected function chown($url,$owner)
	{
		if (self::LOG_LEVEL > 1) error_log(__METHOD__."($url,$owner)");

		$path = Vfs::parse_url($url,PHP_URL_PATH);

		$vfs = new self();
		if (!($stat = $vfs->url_stat($path,0)))
		{
			if (self::LOG_LEVEL) error_log(__METHOD__."($url,$owner) no such file or directory!");
			trigger_error("No such file or directory $url !",E_USER_WARNING);
			return false;
		}
		if (!Vfs::$is_root)
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

		// update stat-cache
		if ($path != '/' && substr($path,-1) == '/') $path = substr($path, 0, -1);
		self::$stat_cache[$path]['fs_uid'] = $owner;

		return $stmt->execute(array(
			'fs_uid' => (int) $owner,
			'fs_id' => $stat['ino'],
		));
	}

	/**
	 * chown but for all files a user owns
	 *
	 * @param $old_uid
	 * @param $new_uid
	 */
	public static function chownAll($old_uid, $new_uid)
	{
		$stmt = self::$pdo->prepare('UPDATE '.self::TABLE.' SET fs_uid=:fs_uid WHERE fs_uid=:old_uid');
		return $stmt->execute(array(
			'fs_uid' => (int) $new_uid,
			'old_uid' => $old_uid,
		));
	}

	/**
	 * Chgrp command, not yet a stream-wrapper function, but necessary
	 *
	 * @param string $url
	 * @param int $owner
	 * @return boolean
	 */
	protected function chgrp($url,$owner)
	{
		if (self::LOG_LEVEL > 1) error_log(__METHOD__."($url,$owner)");

		$path = Vfs::parse_url($url,PHP_URL_PATH);

		$vfs = new self();
		if (!($stat = $vfs->url_stat($path,0)))
		{
			if (self::LOG_LEVEL) error_log(__METHOD__."($url,$owner) no such file or directory!");
			trigger_error("No such file or directory $url !",E_USER_WARNING);
			return false;
		}
		if (!Vfs::has_owner_rights($path,$stat))
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

		// update stat-cache
		if ($path != '/' && substr($path,-1) == '/') $path = substr($path, 0, -1);
		self::$stat_cache[$path]['fs_gid'] = $owner;

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
	protected function chmod($url,$mode)
	{
		if (self::LOG_LEVEL > 1) error_log(__METHOD__."($url, $mode)");

		$path = Vfs::parse_url($url,PHP_URL_PATH);

		$vfs = new self();
		if (!($stat = $vfs->url_stat($path,0)))
		{
			if (self::LOG_LEVEL) error_log(__METHOD__."($url, $mode) no such file or directory!");
			trigger_error("No such file or directory $url !",E_USER_WARNING);
			return false;
		}
		if (!Vfs::has_owner_rights($path,$stat))
		{
			if (self::LOG_LEVEL) error_log(__METHOD__."($url, $mode) only owner or root can do that!");
			trigger_error("Only owner or root can do that!",E_USER_WARNING);
			return false;
		}
		if (!is_numeric($mode))	// not a mode
		{
			if (self::LOG_LEVEL) error_log(__METHOD__."($url, $mode) no (numeric) mode!");
			trigger_error("No (numeric) mode!",E_USER_WARNING);
			return false;
		}
		$stmt = self::$pdo->prepare('UPDATE '.self::TABLE.' SET fs_mode=:fs_mode WHERE fs_id=:fs_id');

		// update stat cache
		if ($path != '/' && substr($path,-1) == '/') $path = substr($path, 0, -1);
		self::$stat_cache[$path]['fs_mode'] = ((int) $mode) & 0777;

		return $stmt->execute(array(
			'fs_mode' => ((int) $mode) & 0777,		// we dont store the file and dir bits, give int overflow!
			'fs_id' => $stat['ino'],
		));
	}


	/**
	 * This method is called immediately when your stream object is created for examining directory contents with opendir().
	 *
	 * @param string $url URL that was passed to opendir() and that this object is expected to explore.
	 * @param int $options
	 * @return booelan
	 */
	function dir_opendir ( $url, $options )
	{
		$this->opened_dir = null;

		$path = Vfs::parse_url($url,PHP_URL_PATH);

		if (!($stat = $this->url_stat($url,0)) || 		// dir not found
			!($stat['mode'] & self::MODE_DIR) && $stat['mime'] != self::DIR_MIME_TYPE ||		// no dir
			!$this->check_access($url,Vfs::EXECUTABLE|Vfs::READABLE, $stat))	// no access
		{
			self::_remove_password($url);
			$msg = !($stat['mode'] & self::MODE_DIR) && $stat['mime'] != self::DIR_MIME_TYPE ?
				"$url is no directory" : 'permission denied';
			if (self::LOG_LEVEL) error_log(__METHOD__."('$url',$options) $msg!");
			$this->opened_dir = null;
			return false;
		}
		$this->opened_dir = array();
		$query = 'SELECT fs_id,fs_name,fs_mode,fs_uid,fs_gid,fs_size,fs_mime,fs_created,fs_modified'.self::$extra_columns.
			' FROM '.self::TABLE.' WHERE fs_dir=? AND fs_active='.self::_pdo_boolean(true).
			" ORDER BY fs_mime='httpd/unix-directory' DESC, fs_name ASC";
		//if (self::LOG_LEVEL > 2) $query = '/* '.__METHOD__.': '.__LINE__.' */ '.$query;
		if (self::LOG_LEVEL > 2) $query = '/* '.__METHOD__."($url,$options)".' */ '.$query;

		$stmt = self::$pdo->prepare($query);
		$stmt->setFetchMode(\PDO::FETCH_ASSOC);
		if ($stmt->execute(array($stat['ino'])))
		{
			foreach($stmt as $file)
			{
				$this->opened_dir[] = $file['fs_name'];
				self::$stat_cache[Vfs::concat($path,$file['fs_name'])] = $file;
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
		static $max_subquery_depth=null;
		if (is_null($max_subquery_depth))
		{
			$max_subquery_depth = $GLOBALS['egw_info']['server']['max_subquery_depth'];
			if (!$max_subquery_depth) $max_subquery_depth = 7;	// setting current default of 7, if nothing set
		}
		if (self::LOG_LEVEL > 1) error_log(__METHOD__."('$url',$flags)");

		$path = Vfs::parse_url($url,PHP_URL_PATH);

		$this->check_set_context($url);

		// webdav adds a trailing slash to dirs, which causes url_stat to NOT find the file otherwise
		if ($path != '/' && substr($path,-1) == '/')
		{
			$path = substr($path,0,-1);
		}
		if (empty($path))
		{
			return false;	// is invalid and gives sql error
		}
		// check if we already have the info from the last dir_open call, as the old vfs reads it anyway from the db
		if (self::$stat_cache && isset(self::$stat_cache[$path]) && self::$stat_cache[$path] !== false)
		{
			return self::$stat_cache[$path] ? self::_vfsinfo2stat(self::$stat_cache[$path]) : false;
		}

		if (!is_object(self::$pdo))
		{
			self::_pdo();
		}
		$base_query = 'SELECT fs_id,fs_name,fs_mode,fs_uid,fs_gid,fs_size,fs_mime,fs_created,fs_modified'.self::$extra_columns.
			' FROM '.self::TABLE.' WHERE fs_active='.self::_pdo_boolean(true).
			' AND fs_name'.self::$case_sensitive_equal.'? AND fs_dir=';
		$parts = explode('/',$path);

		// if we have extended acl access to the url, we dont need and can NOT include the sql for the readable check
		$eacl_access = $this->check_extended_acl($path,Vfs::READABLE);

		try {
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
					// --> depth limit of subqueries is now dynamicly decremented in catch
					if ($n > 1 && !(($n-1) % $max_subquery_depth) && !($query = self::$pdo->query($query)->fetchColumn()))
					{
						if (self::LOG_LEVEL > 1)
						{
							self::_remove_password($url);
							error_log(__METHOD__."('$url',$flags) file or directory not found!");
						}
						// we also store negatives (all methods creating new files/dirs have to unset the stat-cache!)
						return self::$stat_cache[$path] = false;
					}
					$query = 'SELECT fs_id FROM '.self::TABLE.' WHERE fs_dir=('.$query.') AND fs_active='.
						self::_pdo_boolean(true).' AND fs_name'.self::$case_sensitive_equal.self::$pdo->quote($name);

					// if we are not root AND have no extended acl access, we need to make sure the user has the right to tranverse all parent directories (read-rights)
					if (!Vfs::$is_root && !$eacl_access)
					{
						if (!$this->user)
						{
							self::_remove_password($url);
							if (self::LOG_LEVEL > 1) error_log(__METHOD__."('$url',$flags) permission denied, no user-id and not root!");
							return false;
						}
						$query .= ' AND '.$this->_sql_readable();
					}
				}
				else
				{
					$query = str_replace('fs_name'.self::$case_sensitive_equal.'?','fs_name'.self::$case_sensitive_equal.self::$pdo->quote($name),$base_query).'('.$query.')';
				}
			}
			if (self::LOG_LEVEL > 2) $query = '/* '.__METHOD__."($url,$flags) eacl_access=$eacl_access".' */ '.$query;
			//if (self::LOG_LEVEL > 2) $query = '/* '.__METHOD__.': '.__LINE__.' */ '.$query;

			if (!($result = self::$pdo->query($query)) || !($info = $result->fetch(\PDO::FETCH_ASSOC)))
			{
				if (self::LOG_LEVEL > 1)
				{
					self::_remove_password($url);
					error_log(__METHOD__."('$url',$flags) file or directory not found!");
				}
				// we also store negatives (all methods creating new files/dirs have to unset the stat-cache!)
				return self::$stat_cache[$path] = false;
			}
		}
		catch (\PDOException $e) {
			// decrement subquery limit by 1 and try again, if not already smaller then 3
			if ($max_subquery_depth < 3)
			{
				throw new Api\Db\Exception($e->getMessage());
			}
			$GLOBALS['egw_info']['server']['max_subquery_depth'] = --$max_subquery_depth;
			error_log(__METHOD__."() decremented max_subquery_depth to $max_subquery_depth");
			Api\Config::save_value('max_subquery_depth', $max_subquery_depth, 'phpgwapi');
			if (method_exists($GLOBALS['egw'],'invalidate_session_cache')) $GLOBALS['egw']->invalidate_session_cache();
			return $this->url_stat($url, $flags);
		}
		self::$stat_cache[$path] = $info;

		if (self::LOG_LEVEL > 1) error_log(__METHOD__."($url,$flags)=".array2string($info));
		return self::_vfsinfo2stat($info);
	}

	/**
	 * Return readable check as sql (to be AND'ed into the query), only use if !Vfs::$is_root
	 *
	 * @return string
	 */
	protected function _sql_readable()
	{
		static $sql_read_acl=[];

		if (!isset($sql_read_acl[$user = $this->user]))
		{
			foreach($GLOBALS['egw']->accounts->memberships($user, true) as $gid)
			{
				$memberships[] = abs($gid);	// sqlfs stores the gid's positiv
			}
			// using octal numbers with mysql leads to funny results (select 384 & 0400 --> 384 not 256=0400)
			// 256 = 0400, 32 = 040
			$sql_read_acl[$user] = '((fs_mode & 4)=4 OR (fs_mode & 256)=256 AND fs_uid='.$user.
				($memberships ? ' OR (fs_mode & 32)=32 AND fs_gid IN('.implode(',',$memberships).')' : '').')';
			//error_log(__METHOD__."() user=".array2string($user).' --> memberships='.array2string($memberships).' --> '.$sql_read_acl.($memberships?'':': '.function_backtrace()));
		}
		return $sql_read_acl[$user];
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
	 * @param string $path
	 * @return string|boolean content of the symlink or false if $url is no symlink (or not found)
	 */
	function readlink($path)
	{
		$link = !($lstat = $this->url_stat($path,STREAM_URL_STAT_LINK)) || is_null($lstat['readlink']) ? false : $lstat['readlink'];

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
	function symlink($target, $link)
	{
		if (self::LOG_LEVEL > 1) error_log(__METHOD__."('$target','$link')");

		if ($this->url_stat($link,0))
		{
			if (self::LOG_LEVEL > 0) error_log(__METHOD__."('$target','$link') $link exists, returning false!");
			return false;	// $link already exists
		}
		if (!($dir = Vfs::dirname($link)) ||
			!$this->check_access($dir,Vfs::WRITABLE, $dir_stat=$this->url_stat($dir,0)))
		{
			if (self::LOG_LEVEL > 0) error_log(__METHOD__."('$target','$link') returning false! (!is_writable('$dir'), dir_stat=".array2string($dir_stat).")");
			return false;	// parent dir does not exist or is not writable
		}
		$query = 'INSERT INTO '.self::TABLE.' (fs_name,fs_dir,fs_mode,fs_uid,fs_gid,fs_created,fs_modified,fs_creator,fs_mime,fs_size,fs_link'.
			') VALUES (:fs_name,:fs_dir,:fs_mode,:fs_uid,:fs_gid,:fs_created,:fs_modified,:fs_creator,:fs_mime,:fs_size,:fs_link)';
		if (self::LOG_LEVEL > 2) $query = '/* '.__METHOD__.': '.__LINE__.' */ '.$query;
		$stmt = self::$pdo->prepare($query);
		unset(self::$stat_cache[Vfs::parse_url($link,PHP_URL_PATH)]);

		return !!$stmt->execute(array(
			'fs_name' => self::limit_filename(Vfs::basename($link)),
			'fs_dir'  => $dir_stat['ino'],
			'fs_mode' => ($dir_stat['mode'] & 0666),
			'fs_uid'  => $dir_stat['uid'] ? $dir_stat['uid'] : $this->user,
			'fs_gid'  => $dir_stat['gid'],
			'fs_created'  => self::_pdo_timestamp(time()),
			'fs_modified' => self::_pdo_timestamp(time()),
			'fs_creator'  => Vfs::$user,
			'fs_mime'     => self::SYMLINK_MIME_TYPE,
			'fs_size'     => 0,
			'fs_link'     => $target,
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
	function check_extended_acl($url,$check)
	{
		$url_path = Vfs::parse_url($url,PHP_URL_PATH);

		if (is_null(self::$extended_acl))
		{
			$this->_read_extended_acl();
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
	protected function _read_extended_acl()
	{
		if ((self::$extended_acl = Api\Cache::getSession(self::EACL_APPNAME, 'extended_acl')))
		{
			return;		// ext. ACL read from session.
		}
		self::$extended_acl = array();
		if (($rights = $GLOBALS['egw']->acl->get_all_location_rights($this->user, self::EACL_APPNAME)))
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
		uksort(self::$extended_acl, function($a,$b) {
			return strlen($b)-strlen($a);
		});
		Api\Cache::setSession(self::EACL_APPNAME, 'extended_acl', self::$extended_acl);
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
	 * @param int $rights =null rights to set, or null to delete the entry
	 * @param int|boolean $owner =null owner for whom to set the rights, null for the current user, or false to delete all rights for $path
	 * @param int $fs_id =null fs_id to use, to not query it again (eg. because it's already deleted)
	 * @return boolean true if acl is set/deleted, false on error
	 */
	static function eacl($path,$rights=null,$owner=null,$fs_id=null)
	{
		if ($path[0] != '/')
		{
			$path = Vfs::parse_url($path,PHP_URL_PATH);
		}
		if (is_null($fs_id))
		{
			$vfs = new self();
			if (!($stat = $vfs->url_stat($path,0)))
			{
				if (self::LOG_LEVEL) error_log(__METHOD__."($path,$rights,$owner,$fs_id) no such file or directory!");
				return false;	// $path not found
			}
			if (!Vfs::has_owner_rights($path,$stat))		// not group dir and user is eGW admin
			{
				if (self::LOG_LEVEL) error_log(__METHOD__."($path,$rights,$owner,$fs_id) permission denied!");
				return false;	// permission denied
			}
			$fs_id = $stat['ino'];
		}
		if (is_null($owner))
		{
			$owner = Vfs::$user;
		}
		if (is_null($rights) || $owner === false)
		{
			// delete eacl
			if (is_null($owner) || $owner == Vfs::$user ||
				$owner < 0 && Vfs::$user && in_array($owner,$GLOBALS['egw']->accounts->memberships(Vfs::$user,true)))
			{
				self::$extended_acl = null;	// force new read of eACL, as there could be multiple eACL for that path
			}
			$ret = $GLOBALS['egw']->acl->delete_repository(self::EACL_APPNAME, $fs_id, (int)$owner, false);
		}
		else
		{
			if (isset(self::$extended_acl) && ($owner == Vfs::$user ||
				$owner < 0 && Vfs::$user && in_array($owner,$GLOBALS['egw']->accounts->memberships(Vfs::$user,true))))
			{
				// set rights for this class, if applicable
				self::$extended_acl[$path] |= $rights;
			}
			$ret = $GLOBALS['egw']->acl->add_repository(self::EACL_APPNAME, $fs_id, $owner, $rights, false);
		}
		if ($ret)
		{
			Api\Cache::setSession(self::EACL_APPNAME, 'extended_acl', self::$extended_acl);
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
	 * @return array|boolean array with array('path'=>$path,'owner'=>$owner,'rights'=>$rights) or false if $path not found
	 */
	static function get_eacl($path)
	{
		$inst = new static();
		if (!($stat = $inst->url_stat($path, STREAM_URL_STAT_QUIET)))
		{
			error_log(__METHOD__.__LINE__.' '.array2string($path).' not found!');
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
		if (($path = Vfs::dirname($path)))
		{
			$eacls = array_merge((array)self::get_eacl($path),$eacls);
		}
		// sort by length descending, to show precedence
		usort($eacls, function($a, $b) {
			return strlen($b['path']) - strlen($a['path']);
		});
		//error_log(__METHOD__."('$_path') returning ".array2string($eacls));
		return $eacls;
	}

	/**
	 * Get the lowest file id (fs_id) for a given path
	 *
	 * @param string $path
	 * @return ?integer null if path not found
	 */
	static function get_minimum_file_id($path)
	{
		$vfs = new self();
		$stat = $vfs->url_stat($path, 0);
		if ($stat['readlink'])
		{
			$stat = $vfs->url_stat($stat['readlink'], 0);
		}
		$fs_id = $stat['ino'];

		//error_log(__METHOD__."('$path') stat[ino]=$fs_id");
		return $fs_id ? self::get_minimum_fs_id($fs_id) : null;
	}

	/**
	 * Get the lowest file id (fs_id) for a given fs_id
	 *
	 * @param string $path
	 * @return ?integer null if fs_id is NOT found
	 */
	static function get_minimum_fs_id($fs_id)
	{
		$query = 'SELECT MIN(B.fs_id)
FROM ' . self::TABLE . ' as A
JOIN ' . self::TABLE . ' AS B ON A.fs_name = B.fs_name AND A.fs_dir = B.fs_dir
WHERE A.fs_id=?
GROUP BY A.fs_id';
		if (self::LOG_LEVEL > 2)
		{
			$query = '/* '.__METHOD__.': '.__LINE__.' */ '.$query;
		}
		$stmt = self::$pdo->prepare($query);

		$stmt->execute(array($fs_id));
		$min = $stmt->fetchColumn();

		//error_log(__METHOD__."($fs_id) returning ".($min ?: null));
		return $min ?: null;
	}

	/**
	 * Max allowed sub-directory depth, to be able to break infinit recursion by wrongly linked directories
	 */
	const MAX_ID2PATH_RECURSION = 100;

	/**
	 * Return the path of given fs_id(s)
	 *
	 * Searches the stat_cache first and then the db.
	 * Calls itself recursive to to determine the path of the parent/directory
	 *
	 * @param int|array $fs_ids integer fs_id or array of them
	 * @param int $recursion_count =0 internally used to break infinit recursions
	 * @return false|string|array path or array or pathes indexed by fs_id, or false on error
	 */
	static function id2path($fs_ids, $recursion_count=0)
	{
		if (self::LOG_LEVEL > 1) error_log(__METHOD__.'('.array2string($fs_ids).')');
		if ($recursion_count > self::MAX_ID2PATH_RECURSION)
		{
			error_log(__METHOD__."(".array2string($fs_ids).", $recursion_count) max recursion depth reached, probably broken filesystem!");
			return false;
		}
		$ids = (array)$fs_ids;
		$pathes = array();
		// first check our stat-cache for the ids
		foreach(self::$stat_cache as $path => $stat)
		{
			if ($stat && ($key = array_search($stat['fs_id'],$ids)) !== false)
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
		if (count($ids) > 1) $ids = array_map(function($v) { return (int)$v; }, $ids);
		$query = 'SELECT fs_id,fs_dir,fs_name FROM '.self::TABLE.' WHERE fs_id'.
			(count($ids) == 1 ? '='.(int)$ids[0] : ' IN ('.implode(',',$ids).')');
		if (self::LOG_LEVEL > 2) $query = '/* '.__METHOD__.': '.__LINE__.' */ '.$query;

		if (!is_object(self::$pdo))
		{
			self::_pdo();
		}
		$stmt = self::$pdo->prepare($query);
		$stmt->setFetchMode(\PDO::FETCH_ASSOC);
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

		if ($parents && !($parents = self::id2path($parents, $recursion_count+1)))
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
	 * Limit filename to precision of column while keeping the extension
	 *
	 * @param string $name
	 * @return string
	 */
	static protected function limit_filename($name)
	{
		static $fs_name_precision = null;
		if (!isset($fs_name_precision))
		{
			$fs_name_precision = $GLOBALS['egw']->db->get_column_attribute('fs_name', self::TABLE, 'phpgwapi', 'precision');
		}
		if (mb_strlen($name) > $fs_name_precision)
		{
			$parts = explode('.', $name);
			if ($parts > 1 && mb_strlen($extension = '.'.array_pop($parts)) <= $fs_name_precision)
			{
				$name = mb_substr(implode('.', $parts), 0, $fs_name_precision-mb_strlen($extension)).$extension;
			}
			else
			{
				$name = mb_substr(implode('.', $parts), 0, $fs_name_precision);
			}
		}
		return $name;
	}

	/**
	 * Convert a sqlfs-file-info into a stat array
	 *
	 * @param array $info
	 * @return array
	 */
	static protected function _vfsinfo2stat($info)
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
			'readlink' => $info['fs_link'],
		);
		if (self::LOG_LEVEL > 1) error_log(__METHOD__."($info[name]) = ".array2string($stat));
		return $stat;
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
			throw new Api\Exception\WrongParameter(__METHOD__."(id=$id) id has to be an integer!");
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
			throw  new Api\Exception\AssertionFailed("\$GLOBALS['egw_info']['server']['files_dir'] not set!");
		}
		$hash = array();
		$n = $id;
		while(($n = (int) ($n / self::HASH_MAX)))
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
	static protected function _remove_password(&$url)
	{
		$parts = Vfs::parse_url($url);

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
			$query = null;
			parse_str(is_array($url) ? $url['query'] : Vfs::parse_url($url,PHP_URL_QUERY), $query);
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
	 * @param array $props array of array with values for keys 'name', 'ns', 'val' (null to delete the prop)
	 * @return boolean true if props are updated, false otherwise (eg. ressource not found)
	 */
	function proppatch($path,array $props)
	{
		if (self::LOG_LEVEL > 1) error_log(__METHOD__."(".array2string($path).','.array2string($props));
		if (!is_numeric($path))
		{
			if (!($stat = $this->url_stat($path,0)))
			{
				return false;
			}
			$id = $stat['ino'];
		}
		elseif(!($path = self::id2path($id=$path)))
		{
			return false;
		}
		if (!$this->check_access($path,Vfs::WRITABLE, $stat))
		{
			return false;	// permission denied
		}
		$ins_stmt = $del_stmt = null;

		try {
			foreach ($props as &$prop)
			{
				if (!array_key_exists('name', $prop))
				{
					return false; // Name is missing
				}
				if (!isset($prop['ns'])) $prop['ns'] = Vfs::DEFAULT_PROP_NAMESPACE;

				if (!isset($prop['val']) || self::$pdo_type != 'mysql')    // for non mysql, we have to delete the prop anyway, as there's no REPLACE!
				{
					if (!isset($del_stmt))
					{
						$del_stmt = self::$pdo->prepare('DELETE FROM ' . self::PROPS_TABLE . ' WHERE fs_id=:fs_id AND prop_namespace=:prop_namespace AND prop_name=:prop_name');
					}
					$del_stmt->execute(array(
						'fs_id' => $id,
						'prop_namespace' => $prop['ns'],
						'prop_name' => $prop['name'],
					));
				}
				if (isset($prop['val']))
				{
					if (!isset($ins_stmt))
					{
						$ins_stmt = self::$pdo->prepare((self::$pdo_type == 'mysql' ? 'REPLACE' : 'INSERT') .
							' INTO ' . self::PROPS_TABLE . ' (fs_id,prop_namespace,prop_name,prop_value) VALUES (:fs_id,:prop_namespace,:prop_name,:prop_value)');
					}
					if (!$ins_stmt->execute(array(
						'fs_id' => $id,
						'prop_namespace' => $prop['ns'],
						'prop_name' => $prop['name'],
						'prop_value' => $prop['val'],
					)))
					{
						return false;
					}
				}
			}
		}
		// catch exception for inserting or deleting non-ascii prop_names
		catch (\PDOException $e) {
			_egw_log_exception($e);
			return false;
		}
		return true;
	}

	/**
	 * Read properties for a ressource (file, dir or all files of a dir)
	 *
	 * @param array|string|int $path_ids (array of) string with path or integer fs_id
	 * @param string $ns ='http://egroupware.org/' namespace if propfind should be limited to a single one, use null for all
	 * @return array|boolean false on error ($path_ids does not exist), array with props (values for keys 'name', 'ns', 'value'), or
	 * 	fs_id/path => array of props for $depth==1 or is_array($path_ids)
	 */
	function propfind($path_ids,$ns=Vfs::DEFAULT_PROP_NAMESPACE)
	{
		$ids = is_array($path_ids) ? $path_ids : array($path_ids);
		foreach($ids as &$id)
		{
			if (!is_numeric($id))
			{
				if (!($stat = $this->url_stat($id,0)))
				{
					if (self::LOG_LEVEL) error_log(__METHOD__."(".array2string($path_ids).",$ns) path '$id' not found!");
					return false;
				}
				$id = $stat['ino'];
			}
		}
		if (count($ids) >= 1) $ids = array_map(function($v) { return (int)$v; }, $ids);
		$query = 'SELECT * FROM '.self::PROPS_TABLE.' WHERE (fs_id'.
			(count($ids) == 1 ? '='.(int)implode('',$ids) : ' IN ('.implode(',',$ids).')').')'.
			(!is_null($ns) ? ' AND prop_namespace=?' : '');
		if (self::LOG_LEVEL > 2) $query = '/* '.__METHOD__.': '.__LINE__.' */ '.$query;

		try {
			$stmt = self::$pdo->prepare($query);
			$stmt->setFetchMode(\PDO::FETCH_ASSOC);
			$stmt->execute(!is_null($ns) ? array($ns) : array());
		}
		// cat exception trying to search for non-ascii prop_name
		catch (\PDOException $e) {
			_egw_log_exception($e);
			return [];
		}
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
			$props = isset($row) && !empty($props[$row['fs_id']]) ? $props[$row['fs_id']] : [];	// return empty array for no props
		}
		elseif ($props && isset($stat) && is_array($id2path = self::id2path(array_keys($props))))	// need to map fs_id's to pathes
		{
			foreach($id2path as $id => $path)
			{
				$props[$path] =& $props[$id];
				unset($props[$id]);
			}
		}
		if (self::LOG_LEVEL > 1)
		{
			foreach((array)$props as $k => $v)
			{
				error_log(__METHOD__."($path_ids,$ns) $k => ".array2string($v));
			}
		}
		return $props;
	}

	/**
	 * Register __CLASS__ for self::SCHEMA
	 */
	public static function register()
	{
		stream_wrapper_register(self::SCHEME, __CLASS__);
	}
}

StreamWrapper::register();