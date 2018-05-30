<?php

/**
 * EGroupware Sharing base class
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <rb@stylite.de>
 * @copyright (c) 2014-16 by Ralf Becker <rb@stylite.de>
 * @package api
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api;

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

	const READONLY = 'share_ro';
	const WRITABLE = 'share_rw';

 	/**
	 * Modes for sharing files
	 *
	 * @var array
	 */
	static $modes = array(
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
		static::$db = $GLOBALS['egw']->db;
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
	 * @return string with sessionid
	 */
	public static function create_session($keep_session=null)
	{
		$share = array();
		static::check_token($keep_session, $share);
		if($share)
		{
			$classname = static::get_share_class($share);
			$classname::setup_share($keep_session, $share);
			return $classname::login($keep_session, $share);
		}
		return '';
	}

	protected static function check_token($keep_session, &$share)
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

			return static::share_fail(
				'404 Not Found',
				"Requested resource '/".htmlspecialchars($token)."' does NOT exist!\n"
			);
		}

		// check password, if required
		if ($share['share_passwd'] && (empty($_SERVER['PHP_AUTH_PW']) ||
			!(Api\Auth::compare_password($_SERVER['PHP_AUTH_PW'], $share['share_passwd'], 'crypt') ||
				Api\Header\Authenticate::decode_password($_SERVER['PHP_AUTH_PW']) &&
					Api\Auth::compare_password($_SERVER['PHP_AUTH_PW'], $share['share_passwd'], 'crypt'))))
		{
			$realm = 'EGroupware share '.$share['share_token'];
			header('WWW-Authenticate: Basic realm="'.$realm.'"');
			return static::share_fail(
				'401 Unauthorized',
				"<html>\n<head>\n<title>401 Unauthorized</title>\n<body>\nAuthorization failed.\n</body>\n</html>\n"
			);
		}

	}

	/**
	 * Sub-class specific things needed to be done to the share before we try
	 * to login
	 *
	 * @param boolean $keep_session
	 * @param Array $share
	 */
	protected static function setup_share($keep_session, &$share) {}
	/**
	 * Sub-class specific things needed to be done to the share (or session)
	 * after we login but before we start actually doing anything
	 */
	protected function after_login() {}


	protected static function login($keep_session, &$share)
	{
		// update accessed timestamp
		self::$db->update(self::TABLE, array(
			'share_last_accessed' => $share['share_last_accessed']=time(),
		), array(
			'share_id' => $share['share_id'],
		), __LINE__, __FILE__);

		// store sharing object in egw object and therefore in session
		$class = self::get_share_class($share);
		$GLOBALS['egw']->sharing = new $class($share);

		// we have a session we want to keep, but share owner is different from current user and we need filemanager UI, or no session
		// --> create a new anon session
		if ($keep_session === false && $GLOBALS['egw']->sharing->need_session() || is_null($keep_session))
		{
			// create session without checking auth: create(..., false, false)
			if (!($sessionid = $GLOBALS['egw']->session->create('anonymous@'.$GLOBALS['egw_info']['user']['domain'],
				'', 'text', false, false)))
			{
				sleep(1);
				return static::share_fail(
					'500 Internal Server Error',
					"Failed to create session: ".$GLOBALS['egw']->session->reason."\n"
				);
			}
			$GLOBALS['egw']->sharing->after_login();
		}
		// we have a session we want to keep, but share owner is different from current user and we dont need filemanager UI
		// --> we dont need session and close it, to not modifiy it
		elseif ($keep_session === false)
		{
			$GLOBALS['egw']->session->commit_session();
		}
		// need to store new fstab and vfs_user in session to allow GET requests / downloads via WebDAV
		$GLOBALS['egw_info']['user']['vfs_user'] = Vfs::$user;
		$GLOBALS['egw_info']['server']['vfs_fstab'] = Vfs::mount();

		// update modified egw and egw_info again in session, if neccessary
		if ($keep_session || $sessionid)
		{
			$_SESSION[Session::EGW_INFO_CACHE] = $GLOBALS['egw_info'];
			unset($_SESSION[Session::EGW_INFO_CACHE]['flags']);	// dont save the flags, they change on each request

			$_SESSION[Session::EGW_OBJECT_CACHE] = serialize($GLOBALS['egw']);
		}

		return $sessionid;
	}

	/**
	 * Get the namespaced class for the given share
	 *
	 * @param string $share
	 */
	protected static function get_share_class($share)
	{
		try
		{
			if(self::is_entry($share) && class_exists('\EGroupware\Stylite\Link\Sharing'))
			{
				return '\\EGroupware\\Stylite\\Link\\Sharing';
			}
		}
		catch(Exception $e){throw $e;}
		return '\\'.__NAMESPACE__ . '\\'. (self::is_entry($share) ? 'Link' : 'Vfs'). '\\Sharing';
	}

	/**
	 * Something failed, stop everything
	 *
	 * @param String $status
	 * @param String $message
	 */
	public static function share_fail($status, $message)
	{
		header("HTTP/1.1 $status");
		header("X-WebDAV-Status: $status", true);
		echo $message;

		$class = strpos($status, '404') === 0 ? 'EGroupware\Api\Exception\NotFound' :
				strpos($status, '401') === 0 ? 'EGroupware\Api\Exception\NoPermission' :
				'EGroupware\Api\Exception';
		throw new $class($message);
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
			Header\UserAgent::type() == 'msie' && Header\UserAgent::version() < 10.0 ||
			// or if no filemanager installed (WebDAV has own autoindex)
			!file_exists(__DIR__.'/../../filemanager/inc/class.filemanager_ui.inc.php'));
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

	public function is_entry($share = false)
	{
		if(!$share) $share = $this->share;
		list($app, $id) = explode('::', $share['share_path']);
		return $share && $share['share_path'] &&
				$app && $id && $app !== 'vfs' ;//&& array_key_exists($app, $GLOBALS['egw_info']['apps']);
	}

	public function need_session()
	{
		return $this->use_filemanager() || $this->is_entry();
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
		if(!($this->share['share_writable'] & 1))
		{
			Cache::setSession(Vfs\Sqlfs\StreamWrapper::EACL_APPNAME, 'extended_acl', array(
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
		else if (!$this->use_filemanager() && !$this->is_entry())
		{
			// send a content-disposition header, so browser knows how to name downloaded file
			if (!Vfs::is_dir($this->share['share_root']))
			{
				Header\Content::disposition(Vfs::basename($this->share['share_path']), false);
			}
			//$GLOBALS['egw']->session->commit_session();
			$webdav_server = new Vfs\WebDAV();
			$webdav_server->ServeRequest(Vfs::concat($this->share['share_root'], $this->share['share_token']));
			return;
		}
		return $this->get_ui();
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
	 * Name of the async job for cleaning up shares
	 */
	const ASYNC_JOB_ID = 'egw_sharing-tmp_cleanup';

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
		if (!isset(static::$db)) static::$db = $GLOBALS['egw']->db;

		if (empty($name)) $name = $path;

		// check if file has been shared before, with identical attributes
		if (($share = static::$db->select(static::TABLE, '*', $extra+array(
				'share_path' => $path,
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
				static::$db->update(static::TABLE, array(
					'share_with' => $share['share_with'],
				), array(
					'share_id' => $share['share_id'],
				), __LINE__, __FILE__);
			}
		}
		else
		{
			$i = 0;
			while(true)	// self::token() can return an existing value
			{
				try {
					static::$db->insert(static::TABLE, $share = array(
						'share_token' => self::token(),
						'share_path' => $path,
						'share_owner' => Vfs::$user,
						'share_with' => implode(',', (array)$recipients),
						'share_created' => time(),
					)+$extra, false, __LINE__, __FILE__);

					$share['share_id'] = static::$db->get_last_insert_id(static::TABLE, 'share_id');
					break;
				}
				catch(Api\Db\Exception $e) {
					if ($i++ > 3) throw $e;
					unset($e);
				}
			}
		}

		// if not already installed, install periodic cleanup of shares
		$async = new Asyncservice();
		if (!($job = $async->read(self::ASYNC_JOB_ID)) || $job[self::ASYNC_JOB_ID]['method'] === 'egw_sharing::tmp_cleanup')
		{
			if ($job) $async->delete(self::ASYNC_JOB_ID);	// update not working old class-name

			$async->set_timer(array('day' => 28), self::ASYNC_JOB_ID, 'EGroupware\\Api\\Vfs\\Sharing::tmp_cleanup',null);
		}

		return $share;
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
	/**
	 * How long to keep automatic created Wopi shares
	 */
	const WOPI_KEEP = '-3month';

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

			// delete automatic created and expired Collabora shares older then 3 month
			if (class_exists('EGroupware\\Collabora\\Wopi'))
			{
				self::$db->delete(self::TABLE, array(
					'share_expires < '.self::$db->quote(Api\DateTime::to(self::WOPI_KEEP, 'Y-m-d')),
					'share_writable IN ('.Wopi::WOPI_WRITABLE.','.Wopi::WOPI_READONLY.')',
				), __LINE__, __FILE__);
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
	 * @return string full Url incl. schema and host
	 */
	public static function share2link($share)
	{
		if (is_array($share)) $share = $share['share_token'];

		return Framework::getUrl(Framework::link('/share.php')).'/'.$share;
	}
}