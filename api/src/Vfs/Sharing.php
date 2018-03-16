<?php
/**
 * EGroupware API: VFS sharing
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage Vfs
 * @author Ralf Becker <rb@stylite.de>
 * @copyright (c) 2014-16 by Ralf Becker <rb@stylite.de>
 * @version $Id$
 */

namespace EGroupware\Api\Vfs;

use EGroupware\Api;
use EGroupware\Api\Vfs;

use filemanager_ui;

/**
 * VFS sharing
 *
 * Token generation uses openssl_random_pseudo_bytes, if available, otherwise
 * mt_rand based Api\Auth::randomstring is used.
 *
 * Existing user sessions are kept whenever possible by an additional mount into regular VFS:
 * - share owner is current user (no problems with rights, they simply match)
 * - share owner has owner-right for share: we create a temp. eACL for current user
 * --> in all other cases session will be replaced with one of the anonymous user,
 *     as we dont support mounting with rights of share owner (VFS uses Vfs::$user!)
 *
 * @todo handle mounts of an entry directory /apps/$app/$id
 * @todo handle mounts inside shared directory (they get currently lost)
 * @todo handle absolute symlinks (wont work as we use share as root)
 */
class Sharing
{
	/**
	 * Length of base64 encoded token (real length is only 3/4 of it)
	 *
	 * Dropbox uses just 15 chars (letters/numbers 5-6 bit), php sessions use 32 chars (hex = 4bits),
	 * so 32 chars of base64 = 6bits should be plenty.
	 */
	const TOKEN_LENGTH = 32;

	/**
	 * Name of table used for storing tokens
	 */
	const TABLE = 'egw_sharing';

	/**
	 * Reference to global db object
	 *
	 * @var Api\Db
	 */
	protected static $db;

	/**
	 * Share we are instanciated for
	 *
	 * @var array
	 */
	protected $share;

	/**
	 * Modes ATTACH is NOT a sharing mode, but it is traditional mode in email
	 */
	const ATTACH = 'attach';
	const LINK = 'link';
	const READONLY = 'share_ro';
	const WRITABLE = 'share_rw';

 	/**
	 * Modes for sharing files
	 *
	 * @var array
	 */
	static $modes = array(
		self::ATTACH => array(
			'label' => 'Attachment',
			'title' => 'Works reliable for total size up to 1-2 MB, might work for 5-10 MB, most likely to fail for >10MB',
		),
		self::LINK => array(
			'label' => 'Download link',
			'title' => 'Link is appended to mail allowing recipients to download currently attached version of files',
		),
		self::READONLY => array(
			'label' => 'Readonly share',
			'title' => 'Link is appended to mail allowing recipients to download up to date version of files',
		),
		self::WRITABLE => array(
			'label' => 'Writable share',
			'title' => 'Link is appended to mail allowing recipients to download or modify up to date version of files (EPL only)'
		),
	);

	/**
	 * Protected constructor called via self::create_session
	 *
	 * @param string $token
	 * @param array $share
	 */
	protected function __construct(array $share)
	{
		self::$db = $GLOBALS['egw']->db;
		$this->share = $share;
	}

	/**
	 * Get token from url
	 */
	public static function get_token()
	{
        // WebDAV has no concept of a query string and clients (including cadaver)
        // seem to pass '?' unencoded, so we need to extract the path info out
        // of the request URI ourselves
        // if request URI contains a full url, remove schema and domain
		$matches = null;
        if (preg_match('|^https?://[^/]+(/.*)$|', $path_info=$_SERVER['REQUEST_URI'], $matches))
        {
        	$path_info = $matches[1];
        }
        $path_info = substr($path_info, strlen($_SERVER['SCRIPT_NAME']));
		list(, $token/*, $path*/) = preg_split('|[/?]|', $path_info, 3);

		return $token;
	}

	/**
	 * Get root of share
	 *
	 * @return string
	 */
	public function get_root()
	{
		return $this->share['share_root'];
	}

	/**
	 * Create sharing session
	 *
	 * Certain cases:
	 * a) there is not session $keep_session === null
	 *    --> create new anon session with just filemanager rights and share as fstab
	 * b) there is a session $keep_session === true
	 *  b1) current user is share owner (eg. checking the link)
	 *      --> mount share under token additionally
	 *  b2) current user not share owner
	 *  b2a) need/use filemanager UI (eg. directory)
	 *       --> destroy current session and continue with a)
	 *  b2b) single file or WebDAV
	 *       --> modify EGroupware enviroment for that request only, no change in session
	 *
	 * @param boolean $keep_session =null null: create a new session, true: try mounting it into existing (already verified) session
	 * @return string with sessionid, does NOT return if no session created
	 */
	public static function create_session($keep_session=null)
	{
		self::$db = $GLOBALS['egw']->db;

		$token = static::get_token();

		// are we called from header include, because session did not verify
		// --> check if it verifys for our token
		if ($token && !$keep_session)
		{
			$_SERVER['PHP_AUTH_USER'] = $token;
			if (!isset($_SERVER['PHP_AUTH_PW'])) $_SERVER['PHP_AUTH_PW'] = '';

			unset($GLOBALS['egw_info']['flags']['autocreate_session_callback']);
			if (isset($GLOBALS['egw']->session) && $GLOBALS['egw']->session->verify()
				&& isset($GLOBALS['egw']->sharing) && $GLOBALS['egw']->sharing->share['share_token'] === $token)
			{
				return $GLOBALS['egw']->session->sessionid;
			}
		}

		if (empty($token) || !($share = self::$db->select(self::TABLE, '*', array(
			'share_token' => $token,
			'(share_expires IS NULL OR share_expires > '.self::$db->quote(time(), 'date').')',
		), __LINE__, __FILE__)->fetch()) ||
			!$GLOBALS['egw']->accounts->exists($share['share_owner']))
		{
			sleep(1);
			$status = '404 Not Found';
			header("HTTP/1.1 $status");
			header("X-WebDAV-Status: $status", true);
			echo "Requested resource '/".htmlspecialchars($token)."' does NOT exist!\n";
			exit;
		}

		// check password, if required
		if ($share['share_passwd'] && (empty($_SERVER['PHP_AUTH_PW']) ||
			!(Api\Auth::compare_password($_SERVER['PHP_AUTH_PW'], $share['share_passwd'], 'crypt') ||
				Api\Header\Authenticate::decode_password($_SERVER['PHP_AUTH_PW']) &&
					Api\Auth::compare_password($_SERVER['PHP_AUTH_PW'], $share['share_passwd'], 'crypt'))))
		{
			$realm = 'EGroupware share '.$share['share_token'];
			header('WWW-Authenticate: Basic realm="'.$realm.'"');
			$status = '401 Unauthorized';
			header("HTTP/1.1 $status");
			header("X-WebDAV-Status: $status", true);
			echo "<html>\n<head>\n<title>401 Unauthorized</title>\n<body>\nAuthorization failed.\n</body>\n</html>\n";
			exit;
		}

		// need to reset fs_tab, as resolve_url does NOT work with just share mounted
		if (count($GLOBALS['egw_info']['server']['vfs_fstab']) <= 1)
		{
			unset($GLOBALS['egw_info']['server']['vfs_fstab']);	// triggers reset of fstab in mount()
			$GLOBALS['egw_info']['server']['vfs_fstab'] = Vfs::mount();
			Vfs::clearstatcache();
		}
		$share['resolve_url'] = Vfs::resolve_url($share['share_path'], true, true, true, true);	// true = fix evtl. contained url parameter
		// if share not writable append ro=1 to mount url to make it readonly
		if (!self::$db->from_bool($share['share_writable']))
		{
			$share['resolve_url'] .= (strpos($share['resolve_url'], '?') ? '&' : '?').'ro=1';
		}
		//_debug_array($share);

		if ($keep_session)	// add share to existing session
		{
			$share['share_root'] = '/'.$share['share_token'];

			// if current user is not the share owner, we cant just mount share
			if (Vfs::$user != $share['share_owner'])
			{
				$keep_session = false;
			}
		}
		if (!$keep_session)	// do NOT change to else, as we might have set $keep_session=false!
		{
			// only allow filemanager app
			$GLOBALS['egw_info']['user']['apps'] = array(
				'filemanager' => $GLOBALS['egw_info']['apps']['filemanager']
			);

			$share['share_root'] = '/';
			Vfs::$user = $share['share_owner'];

			// Need to re-init stream wrapper, as some of them look at
			// preferences or permissions
			$scheme = Vfs\StreamWrapper::scheme2class(Vfs::parse_url($share['resolve_url'],PHP_URL_SCHEME));
			if($scheme && method_exists($scheme, 'init_static'))
			{
				$scheme::init_static();
			}
		}

		// mounting share
		Vfs::$is_root = true;
		if (!Vfs::mount($share['resolve_url'], $share['share_root'], false, false, !$keep_session))
		{
			sleep(1);
			$status = '404 Not Found';
			header("HTTP/1.1 $status");
			header("X-WebDAV-Status: $status", true);
			echo "Requested resource '/".htmlspecialchars($token)."' does NOT exist!\n";
			exit;
		}
		Vfs::$is_root = false;
		Vfs::clearstatcache();
		// clear link-cache and load link registry without permission check to access /apps
		Api\Link::init_static(true);

		// update accessed timestamp
		self::$db->update(self::TABLE, array(
			'share_last_accessed' => $share['share_last_accessed']=time(),
		), array(
			'share_id' => $share['share_id'],
		), __LINE__, __FILE__);

		// store sharing object in egw object and therefore in session
		$GLOBALS['egw']->sharing = new Sharing($share);

		// we have a session we want to keep, but share owner is different from current user and we need filemanager UI, or no session
		// --> create a new anon session
		if ($keep_session === false && $GLOBALS['egw']->sharing->use_filemanager() || is_null($keep_session))
		{
			// create session without checking auth: create(..., false, false)
			if (!($sessionid = $GLOBALS['egw']->session->create('anonymous@'.$GLOBALS['egw_info']['user']['domain'],
				'', 'text', false, false)))
			{
				sleep(1);
				$status = '500 Internal Server Error';
				header("HTTP/1.1 $status");
				header("X-WebDAV-Status: $status", true);
				echo "Failed to create session: ".$GLOBALS['egw']->session->reason."\n";
				exit;
			}
			// only allow filemanager app (gets overwritten by session::create)
			$GLOBALS['egw_info']['user']['apps'] = array(
				'filemanager' => $GLOBALS['egw_info']['apps']['filemanager']
			);
			// check if sharee has Collabora run rights --> give is to share too
			$apps = $GLOBALS['egw']->acl->get_user_applications($share['share_owner']);
			if (!empty($apps['collabora']))
			{
				$GLOBALS['egw_info']['user']['apps']['collabora'] = $GLOBALS['egw_info']['apps']['collabora'];
			}
		}
		// we have a session we want to keep, but share owner is different from current user and we dont need filemanager UI
		// --> we dont need session and close it, to not modifiy it
		elseif ($keep_session === false)
		{
			if ($GLOBALS['egw']->sharing->use_collabora())
			{
				// check if sharee has Collabora run rights --> give is to share too
				// we need to have collabora app to avoid CSP error since redirect share-link
				// in collabora should get frame-src. e.g:(Collabora/Src/Ui.php:redirect(Sharing::share2link))
				$apps = $GLOBALS['egw']->acl->get_user_applications($share['share_owner']);
				if (!empty($apps['collabora']))
				{
					$GLOBALS['egw_info']['user']['apps']['collabora'] = $GLOBALS['egw_info']['apps']['collabora'];
				}
			}
			$GLOBALS['egw']->session->commit_session();
		}
		// need to store new fstab and vfs_user in session to allow GET requests / downloads via WebDAV
		$GLOBALS['egw_info']['user']['vfs_user'] = Vfs::$user;
		$GLOBALS['egw_info']['server']['vfs_fstab'] = Vfs::mount();

		// update modified egw and egw_info again in session, if neccessary
		if ($keep_session || $sessionid)
		{
			$_SESSION[Api\Session::EGW_INFO_CACHE] = $GLOBALS['egw_info'];
			unset($_SESSION[Api\Session::EGW_INFO_CACHE]['flags']);	// dont save the flags, they change on each request

			$_SESSION[Api\Session::EGW_OBJECT_CACHE] = serialize($GLOBALS['egw']);
		}

		return $sessionid;
	}

	/**
	 * Check if we use filemanager UI
	 *
	 * Only for directories, if browser supports it and filemanager is installed
	 *
	 * @return boolean
	 */
	public function use_filemanager()
	{
		return !(!Vfs::is_dir($this->share['share_root']) || $_SERVER['REQUEST_METHOD'] != 'GET' ||
			// or unsupported browsers like ie < 10
			Api\Header\UserAgent::type() == 'msie' && Api\Header\UserAgent::version() < 10.0 ||
			// or if no filemanager installed (WebDAV has own autoindex)
			!file_exists(__DIR__.'/../../../filemanager/inc/class.filemanager_ui.inc.php'));
	}

	/**
	 * Check if we should use Collabora UI
	 *
	 * Only for files, if URL says so, and Collabora & Stylite apps are installed
	 */
	public function use_collabora()
	{
		 return !Vfs::is_dir($this->share['share_root']) &&
				array_key_exists('edit', $_REQUEST) &&
				array_key_exists('collabora', $GLOBALS['egw_info']['apps']) &&
				array_key_exists('stylite', $GLOBALS['egw_info']['apps']);
	}

	/**
	 * Server a request on a share specified in REQUEST_URI
	 */
	public function ServeRequest()
	{
		// sharing is for a different share, change to current share
		if ($this->share['share_token'] !== self::get_token())
		{
			self::create_session($GLOBALS['egw']->session->session_flags === 'N');

			return $GLOBALS['egw']->sharing->ServeRequest();
		}

		// No extended ACL for readonly shares, disable eacl by setting session cache
		if(!$this->share['share_writable'])
		{
			Api\Cache::setSession(Api\Vfs\Sqlfs\StreamWrapper::EACL_APPNAME, 'extended_acl', array(
				'/' => 1,
				$this->share['share_path'] => 1
			));
		}
		if($this->use_collabora())
		{
			$ui = new \EGroupware\Collabora\Ui();
			return $ui->editor($this->share['share_path']);
		}
		// use pure WebDAV for everything but GET requests to directories
		else if (!$this->use_filemanager())
		{
			// send a content-disposition header, so browser knows how to name downloaded file
			if (!Vfs::is_dir($this->share['share_root']))
			{
				Api\Header\Content::disposition(Vfs::basename($this->share['share_path']), false);
			}
			//$GLOBALS['egw']->session->commit_session();
			$webdav_server = new Vfs\WebDAV();
			$webdav_server->ServeRequest(Vfs::concat($this->share['share_root'], $this->share['share_token']));
			return;
		}
		// run full eTemplate2 UI for directories
		$_GET['path'] = $this->share['share_root'];
		$GLOBALS['egw_info']['user']['preferences']['filemanager']['nm_view'] = 'tile';
		$_GET['cd'] = 'no';
		$GLOBALS['egw_info']['flags']['js_link_registry'] = true;
		Api\Framework::includeCSS('filemanager', 'sharing');
		$ui = new SharingUi();
		$ui->index();
	}

	/**
	 * Generate a new token
	 *
	 * @return string
	 */
	public static function token()
	{
		// generate random token (using oppenssl if available otherwise mt_rand based Api\Auth::randomstring)
		do {
			$token = function_exists('openssl_random_pseudo_bytes') ?
				base64_encode(openssl_random_pseudo_bytes(3*self::TOKEN_LENGTH/4)) :
				Api\Auth::randomstring(self::TOKEN_LENGTH);
			// base64 can contain chars not allowed in our vfs-urls eg. / or #
		} while ($token != urlencode($token));

		return $token;
	}

	/**
	 * Create a new share
	 *
	 * @param string $path either path in temp_dir or vfs with optional vfs scheme
	 * @param string $mode self::LINK: copy file in users tmp-dir or self::READABLE share given vfs file,
	 *	if no vfs behave as self::LINK
	 * @param string $name filename to use for $mode==self::LINK, default basename of $path
	 * @param string|array $recipients one or more recipient email addresses
	 * @param array $extra =array() extra data to store
	 * @throw Api\Exception\NotFound if $path not found
	 * @throw Api\Exception\AssertionFailed if user temp. directory does not exist and can not be created
	 * @return array with share data, eg. value for key 'share_token'
	 */
	public static function create($path, $mode, $name, $recipients, $extra=array())
	{
		if (!isset(self::$db)) self::$db = $GLOBALS['egw']->db;

		if (empty($name)) $name = $path;

		$path2tmp =& Api\Cache::getSession(__CLASS__, 'path2tmp');

		// allow filesystem path only for temp_dir
		$temp_dir = $GLOBALS['egw_info']['server']['temp_dir'].'/';
		if (substr($path, 0, strlen($temp_dir)) == $temp_dir)
		{
			$mode = self::LINK;
			$exists = file_exists($path) && is_readable($path);
		}
		else
		{
			$vfs_path = static::resolve_path($path);
			$exists = !!($vfs_path);
		}
		// check if file exists and is readable
		if (!$exists)
		{
			throw new Api\Exception\NotFound("'$path' NOT found!");
		}
		// check if file has been shared before, with identical attributes
		if (($mode != self::LINK || isset($path2tmp[$path])) &&
			($share = self::$db->select(self::TABLE, '*', $extra+array(
				'share_path' => $mode == 'link' ? $path2tmp[$path] : $vfs_path,
				'share_owner' => Vfs::$user,
				'share_expires' => null,
				'share_passwd'  => null,
				'share_writable'=> false,
			), __LINE__, __FILE__)->fetch()))
		{
			// if yes, just add additional recipients
			$share['share_with'] = $share['share_with'] ? explode(',', $share['share_with']) : array();
			$need_save = false;
			foreach((array)$recipients as $recipient)
			{
				if (!in_array($recipient, $share['share_with']))
				{
					$share['share_with'][] = $recipient;
					$need_save = true;
				}
			}
			$share['share_with'] = implode(',', $share['share_with']);
			if ($need_save)
			{
				self::$db->update(self::TABLE, array(
					'share_with' => $share['share_with'],
				), array(
					'share_id' => $share['share_id'],
				), __LINE__, __FILE__);
			}
		}
		else
		{
			// if not create new share
			if ($mode == 'link')
			{
				$user_tmp = '/home/'.$GLOBALS['egw_info']['user']['account_lid'].'/.tmp';
				if (!Vfs::file_exists($user_tmp) && !Vfs::mkdir($user_tmp, null, STREAM_MKDIR_RECURSIVE))
				{
					throw new Api\Exception\AssertionFailed("Could NOT create temp. directory '$user_tmp'!");
				}
				$n = 0;
				do {
					$tmp_file = Vfs::concat($user_tmp, ($n?$n.'.':'').Vfs::basename($name));
				}
				while(!(is_dir($path) && Vfs::mkdir($tmp_file, null, STREAM_MKDIR_RECURSIVE) ||
					!is_dir($path) && (!Vfs::file_exists($tmp_file) && ($fp = Vfs::fopen($tmp_file, 'x')) ||
						// do not copy identical files again to users tmp dir, just re-use them
						Vfs::file_exists($tmp_file) && Vfs::compare(Vfs::PREFIX.$tmp_file, $path))) && $n++ < 100);

				if ($n >= 100)
				{
					throw new Api\Exception\AssertionFailed("Could NOT create temp. file '$tmp_file'!");
				}
				if ($fp) fclose($fp);

				if (is_dir($path) && !Vfs::copy_files(array($path), $tmp_file) ||
					!is_dir($path) && !copy($path, Vfs::PREFIX.$tmp_file))
				{
					throw new Api\Exception\AssertionFailed("Could NOT create temp. file '$tmp_file'!");
				}
				// store temp. path in session, to be able to add more recipients
				$path2tmp[$path] = $tmp_file;

				$vfs_path = $tmp_file;

				// if not already installed, install periodic cleanup of tmp files
				$async = new Api\Asyncservice();
				if (!$async->read('egw_sharing-tmp-cleanup'))
				{
					$async->set_timer(array('day' => 28),'egw_sharing-tmp_cleanup','EGroupware\\Api\\Vfs\\Sharing::tmp_cleanup',null);
				}
			}

			$i = 0;
			while(true)	// self::token() can return an existing value
			{
				try {
					self::$db->insert(self::TABLE, $share = array(
						'share_token' => self::token(),
						'share_path' => $vfs_path,
						'share_owner' => Vfs::$user,
						'share_with' => implode(',', (array)$recipients),
						'share_created' => time(),
					)+$extra, false, __LINE__, __FILE__);

					$share['share_id'] = self::$db->get_last_insert_id(self::TABLE, 'share_id');
					break;
				}
				catch(Api\Db\Exception $e) {
					if ($i++ > 3) throw $e;
					unset($e);
				}
			}
		}
		return $share;
	}

	/**
	 * Get the actual VFS path for the given path
	 *
	 * We follow links & resolve whatever is possible so that when the share is
	 * mounted later (possibly by anonymous) the file can be found.
	 *
	 * @param string $path
	 * @return string
	 */
	public static function resolve_path($path)
	{
		$vfs_path = $path;
		if(parse_url($path, PHP_URL_SCHEME) !== 'vfs')
		{
			$path = 'vfs://default'.($path[0] == '/' ? '' : '/').$path;
		}
		if (($exists = ($stat = Vfs::stat($path)) && Vfs::check_access($path, Vfs::READABLE, $stat)))
		{
			if (!preg_match("/^(sqlfs|vfs|stylite\.versioning|stylite\.merge)/", $stat['url']))
			{
				$vfs_path = Vfs::parse_url($path, PHP_URL_PATH);
			}
			else
			{
				$vfs_path = Vfs::parse_url($stat['url'], PHP_URL_PATH);
			}
		}
		return $vfs_path;
	}

	/**
	 * Api\Storage\Base instance for egw_sharing table
	 *
	 * @var Api\Storage\Base
	 */
	protected static $so;

	/**
	 * Get a so_sql instance initialised for shares
	 */
	public static function so()
	{
		if (!isset(self::$so))
		{
			self::$so = new Api\Storage\Base('phpgwapi', self::TABLE, null, '', true);
			self::$so->set_times('string');
		}
		return self::$so;
	}

	/**
	 * Delete specified shares and unlink temp. files
	 *
	 * @param int|array $keys
	 * @return int number of deleted shares
	 */
	public static function delete($keys)
	{
		self::$db = $GLOBALS['egw']->db;

		if (is_scalar($keys)) $keys = array('share_id' => $keys);

		// get all temp. files, to be able to delete them
		$tmp_paths = array();
		foreach(self::$db->select(self::TABLE, 'share_path', array(
			"share_path LIKE '/home/%/.tmp/%'")+$keys, __LINE__, __FILE__, false) as $row)
		{
			$tmp_paths[] = $row['share_path'];
		}

		// delete specified shares
		self::$db->delete(self::TABLE, $keys, __LINE__, __FILE__);
		$deleted = self::$db->affected_rows();

		// check if temp. files are used elsewhere
		if ($tmp_paths)
		{
			foreach(self::$db->select(self::TABLE, 'share_path,COUNT(*) AS cnt', array(
				'share_path' => $tmp_paths,
			), __LINE__, __FILE__, false, 'GROUP BY share_path') as $row)
			{
				if (($key = array_search($row['share_path'], $tmp_paths)))
				{
					unset($tmp_paths[$key]);
				}
			}
			// if not delete them
			foreach($tmp_paths as $path)
			{
				Vfs::remove($path);
			}
		}
		return $deleted;
	}

	/**
	 * Home long to keep temp. files: 100 day
	 */
	const TMP_KEEP = 8640000;

	/**.
	 * Periodic (monthly) cleanup of temporary sharing files (download link)
	 *
	 * Exlicit expireds shares are delete, as ones created over 100 days ago and last accessed over 100 days ago.
	 */
	public static function tmp_cleanup()
	{
		if (!isset(self::$db)) self::$db = $GLOBALS['egw']->db;
		Vfs::$is_root = true;

		try {
			$cols = array(
				'share_path',
				'MAX(share_expires) AS share_expires',
				'MAX(share_created) AS share_created',
				'MAX(share_last_accessed) AS share_last_accessed',
			);
			if (($group_concat = self::$db->group_concat('share_id'))) $cols[] = $group_concat.' AS share_id';
			// remove expired tmp-files unconditionally
			$having = 'HAVING MAX(share_expires) < '.self::$db->quote(self::$db->to_timestamp(time())).' OR '.
				// remove without expiration date, when created over 100 days ago AND
				'MAX(share_expires) IS NULL AND MAX(share_created) < '.self::$db->quote(self::$db->to_timestamp(time()-self::TMP_KEEP)). ' AND '.
					// (last accessed over 100 days ago OR never)
					'(MAX(share_last_accessed) IS NULL OR MAX(share_last_accessed) < '.self::$db->quote(self::$db->to_timestamp(time()-self::TMP_KEEP)).')';

			foreach(self::$db->select(self::TABLE, $cols, array(
				"share_path LIKE '/home/%/.tmp/%'",
			), __LINE__, __FILE__, false, 'GROUP BY share_path '.$having) as $row)
			{
				Vfs::remove($row['share_path']);

				if ($group_concat)
				{
					$share_ids = $row['share_id'] ? explode(',', $row['share_id']) : array();
				}
				else
				{
					$share_ids = array();
					foreach(self::$db->selec(self::TABLE, 'share_id', array(
						'share_path' => $row['share_path'],
					), __LINE__, __FILE__) as $id)
					{
						$share_ids[] = $id['share_id'];
					}
				}
				if ($share_ids)
				{
					self::$db->delete(self::TABLE, array('share_id' => $share_ids), __LINE__, __FILE__);
				}
			}
		}
		catch (\Exception $e) {
			_egw_log_exception($e);
		}
		Vfs::$is_root = false;
	}

	/**
	 * Generate link from share or share-token
	 *
	 * @param string|array $share share or share-token
	 * @return string
	 */
	public static function share2link($share)
	{
		if (is_array($share)) $share = $share['share_token'];

		$link = Api\Framework::link('/share.php').'/'.$share;
		if ($link[0] == '/')
		{
			$link = ($_SERVER['HTTPS'] ? 'https://' : 'http://').
				($GLOBALS['egw_info']['server']['hostname'] ?
					$GLOBALS['egw_info']['server']['hostname'] : $_SERVER['HTTP_HOST']).
				$link;
		}
		return $link;
	}
}

if (file_exists(__DIR__.'/../../../filemanager/inc/class.filemanager_ui.inc.php'))
{
	require_once __DIR__.'/../../../filemanager/inc/class.filemanager_ui.inc.php';

	class SharingUi extends filemanager_ui
	{
		/**
		 * Get the configured start directory for the current user
		 *
		 * @return string
		 */
		static function get_home_dir()
		{
			return $GLOBALS['egw']->sharing->get_root();
		}

		/**
		 * Context menu
		 *
		 * @return array
		 */
		public static function get_actions()
		{
			$actions = parent::get_actions();
			$group = 1;
			if(Vfs::is_writable($GLOBALS['egw']->sharing->get_root()))
			{
				return $actions;
			}
			$actions+= array(
				'egw_copy' => array(
					'enabled' => false,
					'group' => $group + 0.5,
					'hideOnDisabled' => true
				),
				'egw_copy_add' => array(
					'enabled' => false,
					'group' => $group + 0.5,
					'hideOnDisabled' => true
				),
				'paste' => array(
					'enabled' => false,
					'group' => $group + 0.5,
					'hideOnDisabled' => true
				),
			);
			return $actions;
		}
	}
}