<?php
/**
 * eGroupWare API: VFS - old (until eGW 1.4 inclusive) VFS stream wrapper
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage vfs
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2007-8 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @version $Id$
 */

/**
 * eGroupWare API: VFS -  old (until eGW 1.4 inclusive) VFS stream wrapper
 * 
 * This class uses eGW's vfs_home class to access the vfs.
 *
 * The interface is according to the docu on php.net
 *  
 * @link http://de.php.net/manual/de/function.stream-wrapper-register.php
 */
class oldvfs_stream_wrapper implements iface_stream_wrapper 
{
	/**
	 * If this class should do the operations direct in the filesystem, instead of going through the vfs
	 */
	const USE_FILESYSTEM_DIRECT = true;
	/**
	 * Mime type of directories, the old vfs uses 'Directory', while eg. WebDAV uses 'httpd/unix-directory'
	 */
	const DIR_MIME_TYPE = 'Directory';
	/**
	 * Scheme / protocoll used for this stream-wrapper
	 */
	const SCHEME = 'oldvfs';
	/**
	 * Does url_stat returns a mime type, or has it to be determined otherwise (string with attribute name)
	 */
	const STAT_RETURN_MIME_TYPE = 'mime';
	/**
	 * How much should be logged to the apache error-log
	 *
	 * 0 = Nothing
	 * 1 = only errors
	 * 2 = all function calls and errors (contains passwords too!)
	 */
	const LOG_LEVEL = 1;
	
	/**
	 * optional context param when opening the stream, null if no context passed
	 *
	 * @var mixed
	 */
	var $context;
	
	/**
	 * Instance of the old vfs class
	 *
	 * @var vfs_home
	 */
	static protected $old_vfs;
	
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
	 * Content of the opened file, as the old vfs can only read/write the whole file
	 *
	 * @var string
	 */
	protected $opened_data;
	/**
	 * Position in the opened file
	 *
	 * @var int
	 */
	protected $opened_pos;
	/**
	 * Directory vfs::ls() of dir opened with dir_opendir()
	 * 
	 * This static var gets overwritten by each new dir_opendir, it helps to not read the entries twice.
	 *
	 * @var array $path => info-array pairs
	 */
	static private $stat_cache;
	/**
	 * Array with filenames of dir opened with dir_opendir
	 *
	 * @var array
	 */
	protected $opened_dir;
	
	/**
	 * Constructor, only called for the non-static stream_* methods!
	 *
	 * @return oldvfs_stream_wrapper
	 */
	function __construct()
	{
		if (self::LOG_LEVEL > 1) error_log('oldvfs_stream_wrapper::__construct()');

		if (!is_object(self::$old_vfs))
		{
			self::$old_vfs =& new vfs_home();
		}
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
	 * @param string $opened_path full path of the file/resource, if the open was successfull and STREAM_USE_PATH was set
	 * @return boolean true if the ressource was opened successful, otherwise false
	 */
	function stream_open ( $url, $mode, $options, &$opened_path )
	{
		if (self::LOG_LEVEL > 1) error_log(__METHOD__."($url,$mode,$options)");
		
		$path = parse_url($url,PHP_URL_PATH);
		
		$this->opened_path = $path;
		$this->opened_mode = $mode;
		$this->opened_data = null;
		
		if (!self::$old_vfs->file_exists($data = array(	// file does not exists
			'string'    	=> $path,
			'relatives'		=> array(RELATIVE_ROOT),
		)) || $mode[0] == 'x')							// or file should NOT exist
		{
			if ($mode[0] == 'r' ||	// does $mode require the file to exist (r,r+)
				$mode[0] == 'x' ||	// or file should not exist, but does
				!self::$old_vfs->acl_check($data+array(	// or we are not allowed to 																																			create it
					'operation'		=> EGW_ACL_ADD,
				)))
			{
				self::_remove_password($url);
				if (self::LOG_LEVEL) error_log(__METHOD__."($url,$mode,$options) file does not exist or can not be created!");
				if (!($options & STREAM_URL_STAT_QUIET))
				{
					trigger_error(__METHOD__."($url,$mode,$options) file does not exist or can not be created!",E_USER_WARNING);
				}
				$this->opened_data = $this->opened_path = $this->opened_mode = null;
				return false;
			}
			// new file
			$this->opened_data = '';
			$this->opened_pos = 0;
		}
		else
		{
			if ($mode != 'r' && !self::$old_vfs->acl_check($data+array(	// we are not allowed to edit it
				'operation'		=> EGW_ACL_EDIT,
			)))
			{
				self::_remove_password($url);
				if (self::LOG_LEVEL) error_log(__METHOD__."($url,$mode,$options) file can not be edited!");
				if (!($options & STREAM_URL_STAT_QUIET))
				{
					trigger_error(__METHOD__."($url,$mode,$options) file can not be edited!",E_USER_WARNING);
				}
				$this->opened_data = $this->opened_path = $this->opened_mode = null;
				return false;				
			}
			if ($mode[0] == 'w')		// w or w+ truncates the file to 0 size
			{
				$this->opened_data = '';
				$this->opened_pos = 0;			
			}
		}
		// can we operate directly on a filesystem stream?
		if (self::$old_vfs->file_actions && self::USE_FILESYSTEM_DIRECT)
		{
			$p = self::$old_vfs->path_parts($data);
			
			if (!($this->opened_data = fopen($p->real_full_path,$mode,false)))
			{
				if (self::LOG_LEVEL) error_log(__METHOD__."($url,$mode,$options) file $p->real_full_path can not be opened!");
				return false;
			}
		}
		if ($mode[0] == 'a')	// append modes: a, a+
		{
			$this->stream_seek(0,SEEK_END);
		}
		return true;
	}
	
	/**
	 * This method is called when the stream is closed, using fclose(). 
	 * 
	 * You must release any resources that were locked or allocated by the stream.
	 */
	function stream_close ( )
	{
		if (self::LOG_LEVEL > 1) error_log(__METHOD__."()");
		
		if (is_null($this->opened_path)) return false;
		
		$ret = true;
		if (is_resource($this->opened_data))
		{
			$ret = fclose($this->opened_data);
			
			if ($this->opened_mode != 'r')
			{
				// touch creates file (if necessary) and sets the modification or creation time and owner
				self::$old_vfs->touch($data = array(
					'string'    	=> $this->opened_path,
					'relatives'		=> array(RELATIVE_ROOT),	// filename is relative to the vfs-root
				));
				// we read the size from the filesystem and write it to our db-based metadata
				$p = self::$old_vfs->path_parts($data);
				$data['attributes'] = array(
					'size' => filesize($p->real_full_path),
				);
				self::$old_vfs->set_attributes($data);
			}
		}
		elseif ($this->opened_mode != 'r')
		{
			// store the changes
			self::$old_vfs->write($this->opened_data);
		}
		$this->opened_data = $this->opened_path = $this->opened_mode = null;

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
		
		if (is_resource($this->opened_data))
		{
			return fread($this->opened_data,$count);
		}
		if (is_null($this->opened_data))
		{
			if (($this->opened_data = self::$old_vfs->read(array(
				'string'    	=> $this->opened_path,
				'relatives'		=> array(RELATIVE_ROOT),	// filename is relative to the vfs-root
			))) === false)
			{
				return false;
			}
			$this->opened_pos = 0;
		}
		if ($this->stream_eof()) return false;	// nothing more to read

		$start = $this->opened_pos;
		$this->opened_pos += $count;
		
		// multibyte save substr!
		return self::substr($this->opened_data,$start,$count);
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
		
		if (is_resource($this->opened_data))
		{
			return fwrite($this->opened_data,$data);
		}
		$len = bytes($data);
		// multibyte save substr!
		self::substr($this->opened_data,$this->opened_pos);
		
		$this->opened_pos += $len;
		
		return $len;
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
		if (is_resource($this->opened_data))
		{
			return feof($this->opened_data);
		}
		if (!is_null($this->opened_data))
		{
			$len = bytes($this->opened_data);
			$eof = $this->opened_pos >= $len;
		}
		if (self::LOG_LEVEL > 1)
		{
			error_log(__METHOD__."() pos=$this->opened_pos >= $len=len --> ".($eof ? 'true' : 'false'));
		}
		return $eof;
	}

	/**
	 * This method is called in response to ftell() calls on the stream.
	 * 
	 * @return integer current read/write position of the stream
	 */
 	function stream_tell ( ) 
 	{
		if (self::LOG_LEVEL > 1) error_log(__METHOD__."()");
		
		if (is_resource($this->opened_data))
		{
			return ftell($this->opened_data);
		}
		return $this->opened_pos;
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
		
		if (is_resource($this->opened_data))
		{
			return fseek($this->opened_data,$offset,$whence);
		}
		if (is_null($this->opened_path)) return false;
		
		switch($whence)
		{
			default:
			case SEEK_SET:
				$this->opened_pos = $offset;
				break;
			case SEEK_END:
				$this->opened_pos = bytes($this->opened_data);
				// fall through
			case SEEK_CUR:
				$this->opened_pos += $offset;
				break;
		}
		return true;
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
		
		if (is_resource($this->opened_data))
		{
			return fflush($this->opened_data);
		}
		// we cant flush, as the old vfs can only write the whole data as once
		return true;
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

		if (!is_object(self::$old_vfs))
		{
			self::$old_vfs =& new vfs_home();
		}
		$data=array(
			'string'    	=> $path,
			'relatives'		=> array(RELATIVE_ROOT),	// filename is relative to the vfs-root
		);
		if (!self::$old_vfs->acl_check($data+array(
				'operation'  => EGW_ACL_DELETE,
				'must_exist' => true,
			)) || ($type = self::$old_vfs->file_type($data)) === self::DIR_MIME_TYPE)
		{
			self::_remove_password($url);
			if (self::LOG_LEVEL) error_log(__METHOD__."($url) (type=$type) permission denied!");
			return false;	// no permission or file does not exist
		}
		unset(self::$stat_cache[$path]);

		return self::$old_vfs->rm($data);
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

		if (!is_object(self::$old_vfs))
		{
			self::$old_vfs =& new vfs_home();
		}
		$data_from = array(
			'string'	=> $path_from,
			'relatives'	=> array(RELATIVE_ROOT),	// filename is relative to the vfs-root
		);
		if (!self::$old_vfs->acl_check($data_from+array(
			'operation'	=> EGW_ACL_DELETE,
			'must_exist'=> true,
		)))
		{
			if (self::LOG_LEVEL) error_log(__METHOD__."($url_from,$url_to): $path_from permission denied!");
			return false;	// no permission or file does not exist
		}
		$data_to = array(
			'string'    => $path_to,
			'relatives'	=> array(RELATIVE_ROOT),	// filename is relative to the vfs-root
		);
		if (!self::$old_vfs->acl_check($data_to+array(
			'operation'	=> EGW_ACL_ADD,
		)))
		{
			self::_remove_password($url_from);
			self::_remove_password($url_to);
			if (self::LOG_LEVEL) error_log(__METHOD__."($url_from,$url_to): $path_to permission denied!");
			return false;	// no permission or file does not exist
		}
		// the filesystem stream-wrapper does NOT allow to rename files to directories, as this makes problems
		// for our vfs too, we give abort here with an error, like the filesystem one does
		if (($type_to = self::$old_vfs->file_type($data_to)) && 
			($type_to === self::DIR_MIME_TYPE) !== (self::$old_vfs->file_type($data_from) === self::DIR_MIME_TYPE))
		{
			$is_dir = $type_to === self::DIR_MIME_TYPE ? 'a' : 'no';
			self::_remove_password($url_from);
			self::_remove_password($url_to);
			if (self::LOG_LEVEL) error_log(__METHOD__."($url_to,$url_from) $path_to is $is_dir directory!");
			return false;	// no permission or file does not exist
		}
		unset(self::$stat_cache[$path_from]);

		return self::$old_vfs->mv(array(
			'from' => $path_from,
			'to' => $path_to,
			'relatives' => array(RELATIVE_ROOT,RELATIVE_ROOT),
		));
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
		
		$path = parse_url($url,PHP_URL_PATH);

		if (!is_object(self::$old_vfs))
		{
			self::$old_vfs =& new vfs_home();
		}
		// check if we should also create all non-existing path components and our parent does not exist, 
		// if yes call ourself recursive with the parent directory
		if (($options & STREAM_MKDIR_RECURSIVE) && $path != '/' && !self::$old_vfs->file_exists(array(
			'string' => dirname($path),
			'relatives'		=> array(RELATIVE_ROOT),	// filename is relative to the vfs-root
		)))
		{
			if (!self::mkdir(dirname($path),$mode,$options))
			{
				return false;
			}
		}
		$data=array(
			'string'    	=> $path,
			'relatives'		=> array(RELATIVE_ROOT),	// filename is relative to the vfs-root
		);
		if (!self::$old_vfs->acl_check($data+array(
				'operation'  => EGW_ACL_ADD,
				'must_exist' => false,
			)))
		{
			self::_remove_password($url);
			if (self::LOG_LEVEL) error_log(__METHOD__."($url) permission denied!");
			if (!($options & STREAM_URL_STAT_QUIET))
			{
				trigger_error(__METHOD__."('$url',$mode,$options) permission denied!",E_USER_WARNING);
			}
			return false;	// no permission or file does not exist
		}
		return self::$old_vfs->mkdir($data);
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

		if (!is_object(self::$old_vfs))
		{
			self::$old_vfs =& new vfs_home();
		}
		$data=array(
			'string'    	=> $path,
			'relatives'		=> array(RELATIVE_ROOT),	// filename is relative to the vfs-root
		);
		if (!self::$old_vfs->acl_check($data+array(
				'operation'  => EGW_ACL_DELETE,
				'must_exist' => true,
			)) || ($type = self::$old_vfs->file_type($data)) !== self::DIR_MIME_TYPE)
		{
			self::_remove_password($url);
			if (self::LOG_LEVEL) error_log(__METHOD__."($url,$options) (type=$type) permission denied!");
			if (!($options & STREAM_URL_STAT_QUIET))
			{
				trigger_error(__METHOD__."('$url',$options) (type=$type) permission denied!",E_USER_WARNING);
			}
			return false;	// no permission or file does not exist
		}
		// abort with an error, if the dir is not empty
		// our vfs deletes recursive, while the stream-wrapper interface does not!
		if (($files = self::$old_vfs->ls($data)))
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

		return self::$old_vfs->rm($data);
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

		if (!is_object(self::$old_vfs))
		{
			self::$old_vfs =& new vfs_home();
		}
		$data=array(
			'string'    	=> $path,
			'relatives'		=> array(RELATIVE_ROOT),	// filename is relative to the vfs-root
		);
		if (!is_null($time)) $data['time'] = $time;

		return self::$old_vfs->touch($data);
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
		if (self::LOG_LEVEL > 1) error_log(__METHOD__."('$url',$options)");
		
		$this->opened_dir = null;

		if (!is_object(self::$old_vfs))
		{
			self::$old_vfs =& new vfs_home();
		}
		$path = parse_url($url,PHP_URL_PATH);

		$files = self::$old_vfs->ls(array(
			'string'    	=> $path,
			'relatives'		=> array(RELATIVE_ROOT),	// filename is relative to the vfs-root
			'checksubdirs'	=> false,
			'nofiles'		=> false,
			'orderby'       => 'name',
			//'mime_type'     => '',
		));
		if (!is_array($files) || 
			// we also need to return false, if $url is not a directory!
			count($files) == 1 && $path == $files[0]['directory'].'/'.$files[0]['name'] &&
			$files[0]['mime_type'] != self::DIR_MIME_TYPE)
		{
			self::_remove_password($url);
			if (self::LOG_LEVEL) error_log(__METHOD__."('$url',$options) $url is not directory!");
			$this->opened_dir = null;
			return false;
		}
		self::$stat_cache = $this->opened_dir = array();
		foreach($files as $file)
		{
			$this->opened_dir[] = $file['name'];
			self::$stat_cache[$file['directory'].'/'.$file['name']] = $file;
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
		
		// check if we already have the info from the last dir_open call, as the old vfs reads it anyway from the db
		if (self::$stat_cache && isset(self::$stat_cache[$path]))
		{
			return self::_vfsinfo2stat(self::$stat_cache[$path]);
		}

		if (!is_object(self::$old_vfs))
		{
			self::$old_vfs =& new vfs_home();
		}
		list($info) = self::$old_vfs->ls(array(
			'string'    	=> $path,
			'relatives'		=> array(RELATIVE_ROOT),	// filename is relative to the vfs-root
			'checksubdirs'	=> false,
			'nofiles'		=> true,
			//'orderby'       => 'name',
			//'mime_type'     => '',
		));
		//print_r($info);
		if (!$info)
		{
			self::_remove_password($url);
			if (self::LOG_LEVEL) error_log(__METHOD__."('$url',$flags) file or directory not found!");
			return false;
		}
		
		return self::_vfsinfo2stat($info);
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
	 * Convert a vfs-file-info into a stat array
	 *
	 * @param array $info
	 * @return array
	 */
	static private function _vfsinfo2stat($info)
	{
		$stat = array(
			'ino'   => $info['file_id'],
			'name'  => $info['name'],
			'mode'  => $info['owner_id'] > 0 ? 
				($info['mime_type'] == self::DIR_MIME_TYPE ? 040700 : 0100600) :
				($info['mime_type'] == self::DIR_MIME_TYPE ? 040070 : 0100060),
			'size'  => $info['size'],
			'uid'   => $info['owner_id'] > 0 ? $info['owner_id'] : 0,
			'gid'   => $info['owner_id'] < 0 ? -$info['owner_id'] : 0,
			'mtime' => strtotime($info['modified'] ? $info['modified'] : $info['created']),
			'ctime' => strtotime($info['created']),
			'nlink' => $info['mime_type'] == self::DIR_MIME_TYPE ? 2 : 1,
			// eGW addition to return the mime type
			'mime'  => $info['mime_type'],
		);
		//error_log(__METHOD__."($info[name]) = ".print_r($stat,true));
		return $stat;
	}

	/**
	 * Multibyte save substr
	 *
	 * @param string $str
	 * @param int $start
	 * @param int $length=null
	 * @return string
	 */
	static function substr($str,$start,$length=null)
	{
		static $func_overload;
		
		if (is_null($func_overload)) $func_overload = extension_loaded('mbstring') ? ini_get('mbstring.func_overload') : 0;
		
		return $func_overload & 2 ? mb_substr($str,$start,$length,'ascii') : substr($str,$start,$length);
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

stream_register_wrapper(oldvfs_stream_wrapper::SCHEME ,'oldvfs_stream_wrapper');
