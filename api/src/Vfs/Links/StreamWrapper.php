<?php
/**
 * EGroupware API: VFS - stream wrapper for linked files
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage vfs
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2008-20 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 */

namespace EGroupware\Api\Vfs\Links;

use EGroupware\Api\Vfs;
use EGroupware\Api;

// explicitly import old phpgwapi classes used:
use addressbook_vcal;

/**
 * Define parent for Vfs\Links\StreamWrapper, if not already defined
 *
 * Allows to base Vfs\Links\StreamWrapper on an other wrapper
 */
if (!class_exists('EGroupware\\Api\\Vfs\\Links\\LinksParent', false))
{
	class LinksParent extends Vfs\Sqlfs\StreamWrapper {}
}

/**
 * EGroupware API: stream wrapper for linked files
 *
 * The files stored by the sqlfs_stream_wrapper in a /apps/$app/$id directory
 *
 * The links stream wrapper extends the sqlfs one, to implement an own ACL based on the access
 * of the entry the files are linked to.
 *
 * Applications can define a 'file_access' method in the link registry with the following signature:
 *
 * 		boolean function file_access(string $id,int $check,string $rel_path)
 *
 * If the do not implement such a function the title function is used to test if the user has
 * at least read access to an entry, and if true full (write) access to the files is granted.
 *
 * Entry directories are always reported existing and empty, if not existing in sqlfs.
 *
 * The stream wrapper interface is according to the docu on php.net
 *
 * @link http://www.php.net/manual/en/function.stream-wrapper-register.php
 */
class StreamWrapper extends LinksParent
{
	/**
	 * Scheme / protocoll used for this stream-wrapper
	 */
	const SCHEME = 'links';
	/**
	 * Prefix to predend to get an url from a path
	 */
	const PREFIX = 'links://default';
	/**
	 * Base url to store links
	 */
	const BASEURL = 'links://default/apps';
	/**
	 * Enable some debug output to the error_log
	 */
	const DEBUG = false;

	/**
	 * Implements ACL based on the access of the user to the entry the files are linked to.
	 *
	 * @param string $url url to check
	 * @param int $check mode to check: one or more or'ed together of: 4 = read, 2 = write, 1 = executable
	 * @return boolean
	 */
	function check_extended_acl($url,$check)
	{
		if (Vfs::$is_root)
		{
			return true;
		}
		$path = Vfs::parse_url($url,PHP_URL_PATH);

		list(,$apps,$app,$id,$rel_path) = array_pad(explode('/',$path,5), 5, null);

		if ($apps != 'apps')
		{
			$access = false;							// no access to anything, but /apps
			$what = '!= apps';
		}
		elseif (!$app)
		{
			$access = !($check & Vfs::WRITABLE);	// always grant read access to /apps
			$what = '!$app';
		}
		elseif (!$this->check_app_rights($app))
		{
			$access = false;							// user has no access to the $app application
			$what = 'no app-rights';
		}
		elseif (!$id)
		{
			$access = true;								// grant read&write access to /apps/$app
			$what = 'app dir';
		}
		// allow applications to implement their own access control to the file storage
		// otherwise use the title method to check if user has (at least read access) to the entry
		// which gives him then read AND write access to the file store of the entry
		else
		{
			// vfs & stream-wrapper use posix rights, Api\Link::file_access uses Api\Acl::{EDIT|READ}!
			$required = $check & Vfs::WRITABLE ? Api\Acl::EDIT : Api\Acl::READ;
			$access = Api\Link::file_access($app, $id, $required, $rel_path, $this->user);
			$what = "from Api\Link::file_access('$app', $id, $required, '$rel_path', ".$this->user.")";
		}
		if (self::DEBUG) error_log(__METHOD__."($url,$check) user=".Vfs::$user." ($what) ".($access?"access granted ($app:$id:$rel_path)":'no access!!!'));
		return $access;
	}

	/**
	 * Check app-rights for current Vfs::$user
	 *
	 * @param string $app
	 * @return boolean
	 */
	public function check_app_rights($app)
	{
		if ($GLOBALS['egw_info']['user']['account_id'] == $this->user && isset($GLOBALS['egw_info']['user']['apps']))
		{
			return isset($GLOBALS['egw_info']['user']['apps'][$app]);
		}
		static $user_apps = array();
		if (!isset($user_apps[$this->user]))
		{
			$user_apps[$this->user] = $GLOBALS['egw']->acl->get_user_applications($this->user);
		}
		return !empty($user_apps[$this->user][$app]);
	}

	/**
	 * This method is called in response to stat() calls on the URL paths associated with the wrapper.
	 *
	 * Reimplemented from sqlfs, as we have to pass the value of check_extends_acl(), due to the lack of late static binding.
	 * And to return vcard for url /apps/addressbook/$id/.entry
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

		$ret = false;
		if (($eacl_check = $this->check_extended_acl($url,Vfs::READABLE)))
		{
			// return vCard as /.entry
			if (substr($url, -7) == '/.entry' &&
				(list($app) = array_slice(explode('/', $url), -3, 1)) && $app === 'addressbook')
			{
				$ret = array(
					'ino' => '#' . md5($url),
					'name' => '.entry',
					'mode' => self::MODE_FILE | Vfs::READABLE,    // required by the stream wrapper
					'size' => 1024,    // email does NOT attach files with size 0!
					'uid' => 0,
					'gid' => 0,
					'mtime' => time(),
					'ctime' => time(),
					'nlink' => 1,
					// eGW addition to return some extra values
					'mime' => $app == 'addressbook' ? 'text/vcard' : 'text/calendar',
				);
			}
			// if entry directory does not exist --> return fake directory
			elseif (!($ret = parent::url_stat($url, $flags)))
			{
				list(,/*$apps*/,/*$app*/, $id, $rel_path) = array_pad(explode('/', Vfs::parse_url($url, PHP_URL_PATH), 5), 5, null);
				if ($id && !isset($rel_path))
				{
					$ret = array(
						'ino' => '#' . md5($url),
						'name' => $id,
						'mode' => self::MODE_DIR,    // required by the stream wrapper
						'size' => 0,
						'uid' => 0,
						'gid' => 0,
						'mtime' => time(),
						'ctime' => time(),
						'nlink' => 2,
						// eGW addition to return some extra values
						'mime' => Vfs::DIR_MIME_TYPE,
					);
				}
			}
		}
		if (self::DEBUG) error_log(__METHOD__."('$url', $flags) eacl_check=".array2string($eacl_check).' returning '.array2string($ret));
		return $ret;
	}

	/**
	 * Set or delete extended acl for a given path and owner (or delete  them if is_null($rights)
	 *
	 * Reimplemented, to NOT call the sqlfs functions, as we dont allow to modify the ACL (defined by the apps)
	 *
	 * @param string $path string with path
	 * @param int $rights =null rights to set, or null to delete the entry
	 * @param int/boolean $owner =null owner for whom to set the rights, null for the current user, or false to delete all rights for $path
	 * @param int $fs_id =null fs_id to use, to not query it again (eg. because it's already deleted)
	 * @return boolean true if acl is set/deleted, false on error
	 */
	static function eacl($path,$rights=null,$owner=null,$fs_id=null)
	{
		unset($path, $rights, $owner, $fs_id);	// not used, but required by function signature

		return false;
	}

	/**
	 * Get all ext. ACL set for a path
	 *
	 * Reimplemented, to NOT call the sqlfs functions, as we dont allow to modify the ACL (defined by the apps)
	 *
	 * @param string $path
	 * @return array|boolean array with array('path'=>$path,'owner'=>$owner,'rights'=>$rights) or false if $path not found
	 */
	static function get_eacl($path)
	{
		unset($path);	// not used, but required by function signature

		return false;
	}

	/**
	 * mkdir for links
	 *
	 * Reimplemented as we have no static late binding to allow the extended sqlfs to call our eacl and to set no default rights for entry dirs
	 *
	 * This method is called in response to mkdir() calls on URL paths associated with the wrapper.
	 *
	 * It should attempt to create the directory specified by path.
	 * In order for the appropriate error message to be returned, do not define this method if your wrapper does not support creating directories.
	 *
	 * @param string $path
	 * @param int $mode not used(!), we inherit 005 for /apps/$app and set 000 for /apps/$app/$id
	 * @param int $options Posible values include STREAM_REPORT_ERRORS and STREAM_MKDIR_RECURSIVE, we allways use recursive!
	 * @return boolean TRUE on success or FALSE on failure
	 */
	function mkdir($path,$mode,$options)
	{
		unset($mode);	// not used, but required by function signature

		if($path[0] != '/')
		{
			if (strpos($path,'?') !== false) $query = Vfs::parse_url($path,PHP_URL_QUERY);
			$path = Vfs::parse_url($path,PHP_URL_PATH).(!empty($query) ? '?'.$query : '');
		}
		list(,$apps,$app,$id) = explode('/',$path);

		$ret = false;
		if ($apps == 'apps' && $app && !$id || $this->check_extended_acl($path,Vfs::WRITABLE))	// app directory itself is allways ok
		{
			$current_is_root = Vfs::$is_root; Vfs::$is_root = true;
			$current_user = Vfs::$user; Vfs::$user = 0;

			$sqlfs = new parent();
			$ret = $sqlfs->mkdir($path,0,$options|STREAM_MKDIR_RECURSIVE);
			if ($id) $sqlfs->chmod($path,0);	// no other rights

			Vfs::$user = $current_user;
			Vfs::$is_root = $current_is_root;
		}
		//error_log(__METHOD__."($path,$mode,$options) apps=$apps, app=$app, id=$id: returning $ret");
		return $ret;
	}

	/**
	 * This method is called in response to rmdir() calls on URL paths associated with the wrapper.
	 *
	 * Reimplemented to do nothing (specially not complain), if an entry directory does not exist,
	 * as we always report them as existing!
	 *
	 * @param string $url
	 * @param int $options Possible values include STREAM_REPORT_ERRORS.
	 * @return boolean TRUE on success or FALSE on failure.
	 */
	function rmdir ( $url, $options )
	{
		$path = $url != '/' ? Vfs::parse_url($url,PHP_URL_PATH) : $url;

		list(,/*$apps*/,/*$app*/,/*$id*/,$rest) = explode('/',$path);

		// never delete entry-dir, as it makes attic inaccessible
		if (empty($rest))
		{
			return true;
		}
		return parent::rmdir( $path, $options );
	}

	/**
	 * This method is called immediately after your stream object is created.
	 *
	 * Reimplemented from sqlfs to ensure $this->url_stat is called, to fill sqlfs stat cache with our eacl!
	 * And to return vcard for url /apps/addressbook/$id/.entry
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
		// the following call is necessary to fill sqlfs_stream_wrapper::$stat_cache, WITH the extendes ACL!
		$stat = $this->url_stat($url,0);
		//error_log(__METHOD__."('$url', '$mode', $options) stat=".array2string($stat));

		// return vCard as /.entry
		if ($stat && $mode[0] == 'r' && substr($url,-7) === '/.entry' &&
			(list($app) = array_slice(explode('/',$url),-3,1)) && $app === 'addressbook')
		{
			list($id) = array_slice(explode('/',$url),-2,1);
			$ab_vcard = new addressbook_vcal('addressbook','text/vcard');
			if (!($charset = $GLOBALS['egw_info']['user']['preferences']['addressbook']['vcard_charset']))
			{
				$charset = 'utf-8';
			}
			if (!($vcard = $ab_vcard->getVCard($id, $charset)))
			{
				error_log(__METHOD__."('$url', '$mode', $options) addressbook_vcal::getVCard($id) returned false!");
				return false;
			}
			//error_log(__METHOD__."('$url', '$mode', $options) addressbook_vcal::getVCard($id) returned ".$GLOBALS[$name]);
			$this->opened_stream = fopen('php://temp', 'wb');
			fwrite($this->opened_stream, $vcard);
			fseek($this->opened_stream, 0, SEEK_SET);
			return true;
		}
		// create not existing entry directories on the fly
		if ($mode[0] != 'r' && ($dir = Vfs::dirname($url)) &&
			!parent::url_stat($dir, 0) && $this->check_extended_acl($dir, Vfs::WRITABLE))
		{
			$this->mkdir($dir,0,STREAM_MKDIR_RECURSIVE);
		}
		return parent::stream_open($url,$mode,$options,$opened_path);
	}

	/**
	 * This method is called immediately when your stream object is created for examining directory contents with opendir().
	 *
	 * Reimplemented to give no error, if entry directory does not exist.
	 *
	 * @param string $url URL that was passed to opendir() and that this object is expected to explore.
	 * @param $options
	 * @return boolean
	 */
	function dir_opendir ( $url, $options )
	{
		if (!parent::url_stat($url, STREAM_URL_STAT_QUIET) && $this->url_stat($url, STREAM_URL_STAT_QUIET))
		{
			$this->opened_dir = array();
			return true;
		}
		return parent::dir_opendir($url, $options);
	}

	/**
	 * Reimplemented to create an entry directory on the fly AND delete our stat cache!
	 *
	 * @param string $url
	 * @param int $time =null modification time (unix timestamp), default null = current time
	 * @param int $atime =null access time (unix timestamp), default null = current time, not implemented in the vfs!
	 */
	protected function touch($url,$time=null,$atime=null)
	{
		if (self::LOG_LEVEL > 1) error_log(__METHOD__."($url,$time,$atime)");

 		if (!($stat = $this->url_stat($url,STREAM_URL_STAT_QUIET)))
		{
			// file does not exist --> create an empty one
			if (!($f = fopen(self::SCHEME.'://default'.Vfs::parse_url($url,PHP_URL_PATH),'w')) || !fclose($f))
			{
				return false;
			}
		}

		return is_null($time) ? true : parent::touch($url,$time,$atime);
	}

	/**
	 * This method is called in response to rename() calls on URL paths associated with the wrapper.
	 *
	 * Reimplemented to create the entry directory, in case it's only faked to be there.
	 *
	 * @param string $path_from
	 * @param string $path_to
	 * @return boolean TRUE on success or FALSE on failure
	 */
	function rename ( $path_from, $path_to )
	{
		if (self::LOG_LEVEL > 1) error_log(__METHOD__."($path_from,$path_to)");

		// Check to make sure target _really_ exists, not just fake dir from Links/StreamWrapper
		$path = Vfs::parse_url($path_to, PHP_URL_PATH);
		list(,/*$apps*/,/*$app*/,$id) = explode('/', $path);

		if($id && !parent::url_stat(Vfs::dirname($path_to),STREAM_URL_STAT_QUIET))
		{
			$this->mkdir(Vfs::dirname($path), 0, STREAM_MKDIR_RECURSIVE );
		}

		return parent::rename($path_from,$path_to);
	}

	/**
	 * Method called for symlink()
	 *
	 * Reimplemented to really create (not just fake) an entry directory on the fly
	 *
	 * @param string $target
	 * @param string $link
	 * @return boolean true on success false on error
	 */
	function symlink($target,$link)
	{
		$parent = new \EGroupware\Api\Vfs\Links\LinksParent($target);
		if (!$parent->url_stat($dir = Vfs::dirname($link),0) && $this->check_extended_acl($dir,Vfs::WRITABLE))
		{
			$this->mkdir($dir,0,STREAM_MKDIR_RECURSIVE);
		}
		return parent::symlink($target,$link);
	}

	/**
	 * Register this stream-wrapper
	 */
	public static function register()
	{
		stream_wrapper_register(self::SCHEME, __CLASS__);
	}
}

StreamWrapper::register();