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

use EGroupware\Api\Vfs\HiddenUploadSharing;

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
	 * @var Db
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
			'title' => 'Link is generated allowing recipients to view entries',
		),
		self::WRITABLE => array(
			'label' => 'Writable share',
			'title' => 'Link is generated allowing recipients to view and modify entries'
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
	public static function get_token($path=null)
	{
		if (!empty($path) && preg_match('|/share.php/([^/]+)|', $path, $matches))
		{
			return $matches[1];
		}
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
		list(, $token/*, $path*/) = preg_split('|[/?]|', $path_info, 3)+[null,null,null];

		list($token) = explode(':', $token);
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
	 * Get share path
	 */
	public function get_path()
	{
		return $this->share['share_path'];
	}

	/**
	 * Get share with email addresses
	 */
	public function get_share_with()
	{
		return $this->share['share_with'];
	}

	/**
	 * Create sharing session
	 *
	 * There are two cases:
	 *
	 * 1) there is no session $keep_session === null
	 *    --> create new anon session with just filemanager rights and resolved share incl. sharee as only fstab entry
	 *
	 * 2) there is a (non-anonymous) session $keep_session === true
	 *    --> mount share with sharing stream-wrapper into users "shares" subdirectory of home directory
	 *        and ask user if he wants the share permanently mounted there
	 *
	 * Even with sharing stream-wrapper a) and b) need to be different, as sharing SW needs an intact fstab!
	 *
	 * Not yet sure if this still needs extra handling:
	 *
	 * 2a) single file or WebDAV
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

	/**
	 * Check sharing token
	 *
	 * @param boolean $keep_session false: does NOT check/fidle with session, true: return if session belongs to token
	 * @param array& $share on return information about the share
	 * @param ?string $token default call self::get_token() to get it from the URL
	 * @param ?string $password default $_SERVER['PHP_AUTH_PW']
	 * @throws Exception
	 * @throws Exception\NoPermission
	 * @throws Exception\NotFound
	 */
	public static function check_token($keep_session, &$share, $token=null, $password=null)
	{
		self::$db = $GLOBALS['egw']->db;

		if (!isset($token)) $token = static::get_token();

		// are we called from header include, because session did not verify
		// --> check if it verifys for our token
		if ($token && !$keep_session)
		{
			$_SERVER['PHP_AUTH_USER'] = $token;
			if (!isset($_SERVER['PHP_AUTH_PW'])) $_SERVER['PHP_AUTH_PW'] = '';

			unset($GLOBALS['egw_info']['flags']['autocreate_session_callback']);
			if (isset($GLOBALS['egw']->session) && $GLOBALS['egw']->session->verify()
				&& isset($GLOBALS['egw']->sharing) && array_key_exists($token, $GLOBALS['egw']->sharing))
			{
				return $GLOBALS['egw']->session->sessionid;
			}
		}

		if (empty($token) || !($share = self::$db->select(self::TABLE, '*', array(
			'share_token' => $token,
			'(share_expires IS NULL OR share_expires > '.self::$db->quote(time(), 'date').')',
		), __LINE__, __FILE__,false,'',Db::API_APPNAME)->fetch()) ||
			!$GLOBALS['egw']->accounts->exists($share['share_owner']))
		{
			sleep(1);

			return static::share_fail(
				'404 Not Found',
				"Requested resource '/".htmlspecialchars($token)."' does NOT exist!\n"
			);
		}
		// check password, if required
		if(!static::check_password($share, $password))
		{
			$realm = 'EGroupware share '.$share['share_token'];
			header('WWW-Authenticate: Basic realm="'.$realm.'"');
			return static::share_fail(
				'401 Unauthorized',
				"Authorization failed."
			);
		}

	}

	/**
	 * Check to see if the share needs a password, and if it does that the password
	 * provided matches.
	 *
	 * @param array $share
	 * @param ?string $password default $_SERVER['PHP_AUTH_PW']
	 * @return boolean Password OK (or not needed)
	 */
	protected static function check_password(Array $share, $password=null)
	{
		if (!isset($password)) $password = $_SERVER['PHP_AUTH_PW'];

		if ($share['share_passwd'] && (empty($password) ||
			!(Auth::compare_password($password, $share['share_passwd'], 'crypt') ||
				Header\Authenticate::decode_password($password) &&
					Auth::compare_password($password, $share['share_passwd'], 'crypt'))))
		{
			return false;
		}
		return true;
	}

	/**
	 * Sub-class specific things needed to be done to the share before we try
	 * to login
	 *
	 * @param boolean $keep_session
	 * @param array $share
	 */
	protected static function setup_share($keep_session, &$share) {}
	/**
	 * Sub-class specific things needed to be done to the share (or session)
	 * after we login but before we start actually doing anything
	 */
	protected static function after_login(array $share) {}


	protected static function login($keep_session, &$share)
	{
		// update accessed timestamp
		self::$db->update(self::TABLE, array(
			'share_last_accessed' => $share['share_last_accessed']=time(),
		), array(
			'share_id' => $share['share_id'],
		), __LINE__, __FILE__);

		// store sharing object in egw object and therefore in session
		if(!isset($GLOBALS['egw']->sharing))
		{
			$GLOBALS['egw']->sharing = Array();
		}
		$GLOBALS['egw']->sharing[$share['share_token']] = static::factory($share);

		// we have a session we want to keep, but share owner is different from current user and we need filemanager UI, or no session
		// --> create a new anon session
		if ($keep_session === false && $GLOBALS['egw']->sharing[$share['share_token']]->need_session() || is_null($keep_session))
		{
			$sessionid = static::create_new_session();

			static::after_login($share);
		}
		// we have a session we want to keep, but share owner is different from current user and we dont need filemanager UI
		// --> we dont need session and close it, to not modifiy it
		elseif ($keep_session === false)
		{
			$GLOBALS['egw']->session->commit_session();
		}
		// need to store new fstab in session to allow GET requests / downloads via WebDAV
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

	public static function create_new_session()
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
		return $sessionid;
	}

	/**
	 * Factory method to instanciate a share
	 *
	 * @param array $share
	 *
	 * @return Sharing
	 */
	public static function factory($share)
	{
		$class = static::get_share_class($share);

		return new $class($share);
	}

	/**
	 * Get the namespaced class for the given share
	 *
	 * @param array $share
	 * @return string
	 * @throws Exception
	 */
	protected static function get_share_class(array $share)
	{
		try
		{
			if(self::is_entry($share))
			{
				list($app, $id) = explode('::', $share['share_path']);
				if($app && class_exists('\EGroupware\\'. ucfirst($app) . '\Sharing'))
				{
					return '\EGroupware\\'. ucfirst($app) . '\Sharing';
				}
				else if(class_exists('\EGroupware\Stylite\Link\Sharing'))
				{
					return '\\EGroupware\\Stylite\\Link\\Sharing';
				}
			}
			else if (class_exists ('\EGroupware\Collabora\Wopi') && (int)$share['share_writable'] === \EGroupware\Collabora\Wopi::WOPI_SHARED)
			{
				return '\\EGroupware\\Collabora\\Wopi';
			}
			else if ((int)$share['share_writable'] == HiddenUploadSharing::HIDDEN_UPLOAD)
			{
				return '\\'.__NAMESPACE__ . '\\'. 'Vfs\\HiddenUploadSharing';
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
			(strpos($status, '401') === 0 ? 'EGroupware\Api\Exception\NoPermission' :
				'EGroupware\Api\Exception');
		throw new $class($message, (int)$status);
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

	public static function is_entry($share = false)
	{
		list($app, $id) = explode('::', $share['share_path']);
		return $share && $share['share_path'] &&
				$app && $id && !in_array($app, array('filemanager', 'vfs')) ;//&& array_key_exists($app, $GLOBALS['egw_info']['apps']);
	}

	public function need_session()
	{
		return $this->use_filemanager() || static::is_entry($this->session);
	}

	/**
	 * Get actions for sharing an entry from the given app
	 *
	 * @param string $appname
	 * @param int $group Current menu group
	 */
	public static function get_actions($appname, $group = 6)
	{
		Translation::add_app('api');
		$actions = array(
		'share' => array(
				'caption' => lang('Share'),
				'icon' => 'api/share',
				'group' => $group,
				'allowOnMultiple' => false,
				'children' => array(
					'shareReadonlyLink' => array(
						'caption' => lang('Share link'),
						'group' => 1,
						'icon' => 'link',
						'order' => 11,
						'enabled' => "javaScript:app.$appname.is_share_enabled",
						'onExecute' => "javaScript:app.$appname.share_link",
						'hint' => lang("Share this %1 via URL", Link::get_registry($appname, 'entry'))
					),
					'shareWritable' => array(
						'caption' => lang('Writable'),
						'group' => 2,
						'icon' => 'edit',
						'allowOnMultiple' => true,
						'enabled' => "javaScript:app.$appname.is_share_enabled",
						'checkbox' => true,
						'hint' => lang("Allow editing the %1", Link::get_registry($appname, 'entry'))
					),
					'shareFiles' => array(
						'caption' => lang('Share files'),
						'group' => 2,
						'allowOnMultiple' => true,
						'enabled' => "javaScript:app.$appname.is_share_enabled",
						'checkbox' => true,
						'hint' => lang('Include access to any linked files (Links tab)')
					),
					'shareFilemanager' => array(
						'caption' => lang('share filemanager directory'),
						'group' => 10,
						'icon' => 'link',
						'order' => 20,
						'enabled' => "javaScript:app.$appname.is_share_enabled",
						'onExecute' => "javaScript:app.$appname.share_link",
						'hint' => lang('Share just the associated filemanager directory, not the %1', Link::get_registry($appname, 'entry'))
					),
				),
		));
		if(!$GLOBALS['egw_info']['user']['apps']['filemanager'])
		{
			unset($actions['share']['children']['shareFilemanager']);
		}
		if(!$GLOBALS['egw_info']['user']['apps']['stylite'])
		{
			array_unshift($actions['share']['children'], array(
				'caption' => lang('EPL Only'),
				'group' => 0
			));
			foreach($actions['share']['children'] as &$child)
			{
				$child['enabled'] = false;
			}
		}
		return $actions;
	}

	/**
	 * Serve a request on a share specified in REQUEST_URI
	 */
	public function ServeRequest()
	{
		// sharing is for a different share, change to current share
		if (empty($this->share['skip_validate_token']) && self::get_token() && $this->share['share_token'] !== self::get_token())
		{
			// to keep the session we require the regular user flag "N" AND a user-name not equal to "anonymous"
			self::create_session($GLOBALS['egw']->session->session_flags === 'N' &&
				$GLOBALS['egw_info']['user']['account_lid'] !== 'anonymous');

			return $GLOBALS['egw']->sharing[static::get_token()]->ServeRequest();
		}

		/* No extended ACL for readonly shares, disable eacl by setting session cache
		if(!($this->share['share_writable'] & 1))
		{
			Cache::setSession(Vfs\Sqlfs\StreamWrapper::EACL_APPNAME, 'extended_acl', array(
				'/' => 1,
				$this->share['share_path'] => 1
			));
		}*/
		if($this->use_collabora())
		{
			$ui = new \EGroupware\Collabora\Ui();
			return $ui->editor($this->share['share_path']);
		}
		// use pure WebDAV for everything but GET requests to directories
		else if (!$this->use_filemanager() && !static::is_entry($this->share))
		{
			// send a content-disposition header, so browser knows how to name downloaded file
			if (!Vfs::is_dir($this->share['share_root']))
			{
				Header\Content::disposition(Vfs::basename($this->share['share_path']), false);
			}
			$GLOBALS['egw']->session->commit_session();

			// WebDAV always looks at the original request for a single file so make sure the file is found at the root
			Vfs::$is_root = true;
			unset($GLOBALS['egw_info']['server']['vfs_fstab']);

			// Make SURE resolve_url is set, otherwise webdav will give full access to /
			if(!$this->share['resolve_url'])
			{
				$this->share['resolve_url'] = Vfs::resolve_url($this->share['share_path'], true, true, true, true);
			}
			Vfs::mount($this->share['resolve_url'], '/', false, false, true);
			Vfs::clearstatcache();

			$webdav_server = new Vfs\WebDAV();
			$webdav_server->ServeRequest('/');
			return;
		}
		return $this->get_ui();
	}

	/**
	 * Get the user interface for this share
	 *
	 */
	public function get_ui()
	{
		echo 'Error: missing subclass';
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
				Auth::randomstring(self::TOKEN_LENGTH);
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
	 * Only for shares with identical attributes AND recipients an existing share-token is returned.
	 *
	 * @param string $action_id Specific type of share being created, default ''
	 * @param string $path either path in temp_dir or vfs with optional vfs scheme
	 * @param string $mode self::LINK: copy file in users tmp-dir or self::READABLE share given vfs file,
	 *  if no vfs behave as self::LINK
	 * @param string $name filename to use for $mode==self::LINK, default basename of $path
	 * @param string|array $recipients one or more recipient email addresses
	 * @param array $extra =array() extra data to store
	 * @return array with share data, eg. value for key 'share_token'
	 * @throw Api\Exception\NotFound if $path not found
	 * @throw Api\Exception\AssertionFailed if user temp. directory does not exist and can not be created
	 */
	public static function create(string $action_id, $path, $mode, $name, $recipients, $extra = array())
	{
		if (!isset(static::$db)) static::$db = $GLOBALS['egw']->db;

		if (empty($name)) $name = $path;

		$table_def = static::$db->get_table_definitions(Db::API_APPNAME,static::TABLE);
		$extra = array_intersect_key($extra, $table_def['fd']);

		// Check if path is mounted somewhere that needs a password
		static::path_needs_password($path);

		// check if file has been shared before, with identical attributes AND recipients
		if (($share = static::$db->select(static::TABLE, '*', $extra+array(
				'share_path' => $path,
				'share_owner' => Vfs::$user,
				'share_expires' => null,
				'share_passwd'  => null,
				'share_writable'=> false,
				'share_with'    => implode(',', (array)$recipients),
			), __LINE__, __FILE__, Db::API_APPNAME)->fetch()))
		{
			// if yes, nothing to do
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
					)+$extra, false, __LINE__, __FILE__, Db::API_APPNAME);

					$share['share_id'] = static::$db->get_last_insert_id(static::TABLE, 'share_id');
					break;
				}
				catch(Db\Exception $e) {
					if ($i++ > 3) throw $e;
					unset($e);
				}
			}
		}

		// if not already installed, install periodic cleanup of shares
		$async = new Asyncservice();
		$method = 'EGroupware\\Api\\Sharing::tmp_cleanup';
		if (!($job = $async->read(self::ASYNC_JOB_ID)) || $job[self::ASYNC_JOB_ID]['method'] !== $method)
		{
			if ($job) $async->delete(self::ASYNC_JOB_ID);	// update not working old class-name

			$async->set_timer(array('day' => 28), self::ASYNC_JOB_ID, $method ,null);
		}

		return $share;
	}

	/**
	 * Create a share via AJAX
	 *
	 * @param String $action
	 * @param String $path
	 * @param boolean $writable Allow editing the shared entry / folder / file
	 * @param boolean $files For sharing an application entry, allow access to the linked files
	 * @param array $extra Additional extra parameters
	 */
	public static function ajax_create($action, $path, $writable = false, $files = false, $extra = array())
	{
		if(!$path)
		{
			throw new Exception\WrongParameter('Missing share path.  Unable to create share.');
		}
		$extra = (array)$extra + array(
			'share_writable' => $writable,
			'include_files'  => $files
		);
		$class = self::get_share_class(array('share_path' => $path) + $extra);
		$share = $class::create(
			$action,
			$path,
			$writable ? Sharing::WRITABLE : Sharing::READONLY,
			basename($path),
			array(),
			$extra
		);

		// Store share in session so Merge can find this one and not create a read-only one
		Cache::setSession(__CLASS__, $path, $share);
		$arr = array(
			'action'		=> $action,
			'writable'      => $writable,
			'share_link'	=> $class::share2link($share),
			'template'		=> Etemplate\Widget\Template::rel2url('/filemanager/templates/default/share_dialog.xet')
		);
		switch($action)
		{
			case 'shareFilemanager':
				$arr['title'] = lang('Filemanager directory');
				break;
			case 'shareUploadDir':
			case 'mail_shareUploadDir':
				$arr['title'] = lang('Upload directory');
				break;
		}
		$response = Json\Response::get();
		$response->data($arr);
	}

	/**
	 * Api\Storage\Base instance for egw_sharing table
	 *
	 * @var Storage\Base
	 */
	protected static $so;

	/**
	 * Get a so_sql instance initialised for shares
	 */
	public static function so()
	{
		if (!isset(self::$so))
		{
			self::$so = new Storage\Base('phpgwapi', self::TABLE, null, '', true);
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

		// delete specified shares
		self::$db->delete(self::TABLE, $keys, __LINE__, __FILE__, Db::API_APPNAME);
		$deleted = self::$db->affected_rows();

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
					foreach(self::$db->select(self::TABLE, 'share_id', array(
						'share_path' => $row['share_path'],
					), __LINE__, __FILE__) as $id)
					{
						$share_ids[] = $id['share_id'];
					}
				}
				if ($share_ids)
				{
					$class = self::get_share_class($row);
					$class::delete(['share_id' => $share_ids]);
				}
			}

			// delete automatic created and expired Collabora shares older then 3 month
			if (class_exists('EGroupware\\Collabora\\Wopi'))
			{
				self::$db->delete(self::TABLE, array(
					'share_expires < '.self::$db->quote(DateTime::to(self::WOPI_KEEP, 'Y-m-d')),
					'share_writable IN ('.\EGroupware\Collabora\Wopi::WOPI_WRITABLE.','.\EGroupware\Collabora\Wopi::WOPI_READONLY.')',
				), __LINE__, __FILE__);
			}

			// Now check the remaining shares
			static::cleanup_missing_paths();
		}
		catch (\Exception $e) {
			_egw_log_exception($e);
		}
		Vfs::$is_root = false;
	}

	/**
	 * Check share paths and if the path is no longer there / valid, remove the share
	 */
	public static function cleanup_missing_paths()
	{
		if (!isset(self::$db)) self::$db = $GLOBALS['egw']->db;

		foreach(self::$db->select(self::TABLE, array(
			'share_id','share_path', 'share_writable'
			), array(), __LINE__, __FILE__, false) as $share)
		{
			$class = self::get_share_class($share);

			if(!$class::check_path($share))
			{
				$class::delete($share);
			}
		}
	}

	/**
	 * Check that the share path is still valid, and if not, delete it.
	 * This should be overridden.
	 *
	 * @param array share
	 *
	 * @return boolean Is the share still valid
	 */
	protected static function check_path($share)
	{
		return true;
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

	/**
	 * Check to see if the path has a password required for it's mounting (eg: Samba)
	 * we need to deal with it specially.  In general, we just throw an exception
	 * if the mount has $pass in it.
	 *
	 * @param string $path
	 *
	 * @throws Exception\WrongParameter if you try to share a path that needs a password
	 */
	public static function path_needs_password($path)
	{
		$mounts = array_reverse(Vfs::mount());
		$parts = Vfs::parse_url($path);

		foreach($mounts as $mounted => $url)
		{
			if(($mounted == $parts['path'] || $mounted.'/' == substr($parts['path'],0,strlen($mounted)+1)) && strpos($url, '$pass') !== FALSE)
			{
				throw new Exception\WrongParameter(
					'Cannot share a file that needs a password. (' .
					$path . ' mounted from '. $url . ')'
				);
			}
		}

		return false;
	}
}