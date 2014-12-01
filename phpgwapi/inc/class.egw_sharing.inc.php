<?php
/**
 * EGroupware API: VFS sharing
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @author Ralf Becker <rb@stylite.de>
 * @copyright (c) 2014 by Ralf Becker <rb@stylite.de>
 * @version $Id$
 */

/**
 * VFS sharing
 *
 * Token generation uses openssl_random_pseudo_bytes, if available, otherwise
 * mt_rand based auth::randomstring is used.
 *
 * @todo UI to create shares
 * @todo handle existing user sessions eg. by mounting share under it's token into vfs and redirect to regular filemanager
 * @todo handle mounts inside shared directory (they get currently lost)
 * @todo handle absolute symlinks (wont work as we use share as root)
 * @todo use sharing instead of attachments in mail app
 */
class egw_sharing
{
	/**
	 * Length of base64 encoded token (real length is only 3/4 of it)
	 */
	const TOKEN_LENGTH = 64;

	/**
	 * Name of table used for storing tokens
	 */
	const TABLE = 'egw_sharing';

	/**
	 * Reference to global db object
	 *
	 * @var egw_db
	 */
	protected static $db;

	/**
	 * Share we are instanciated for
	 *
	 * @var array
	 */
	protected $share;

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
	 * Init sharing by setting PHP_AUTH_USER from token in url
	 */
	public static function init()
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
		list(, $token/*, $path*/) = explode('/', $path_info, 3);

		$_SERVER['PHP_AUTH_USER'] = $token;
		if (!isset($_SERVER['PHP_AUTH_PW'])) $_SERVER['PHP_AUTH_PW'] = '';

		return $token;
	}

	/**
	 * Create sharing session
	 *
	 * @return string with sessionid, does NOT return if no session created
	 */
	public static function create_session()
	{
		self::$db = $GLOBALS['egw']->db;

		$token = $_SERVER['PHP_AUTH_USER'];

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
			common::egw_exit();
		}
		// ToDo: password check, if required

		// create session without checking auth: create(..., false, false)
		if (!($sessionid = $GLOBALS['egw']->session->create('anonymous', '', 'text', false, false)))
		{
			sleep(1);
			$status = '500 Internal Server Error';
			header("HTTP/1.1 $status");
			header("X-WebDAV-Status: $status", true);
			echo "Failed to create session: ".$GLOBALS['egw']->session->reason."\n";
			common::egw_exit();
		}
		// only allow filemanager app
		$GLOBALS['egw_info']['user']['apps'] = array(
			'filemanager' => $GLOBALS['egw_info']['apps']['filemanager']
		);

		$share['resolve_url'] = egw_vfs::resolve_url($share['share_path']);
		// if share not writable append ro=1 to mount url to make it readonly
		if (!self::$db->from_bool($share['share_writable']))
		{
			$share['resolve_url'] .= (strpos($share['resolve_url'], '?') ? '&' : '?').'ro=1';
		}
		//_debug_array($share);

		// arrange vfs to only contain shared url and use share-owner as user
		egw_vfs::$is_root = true;
		if (!egw_vfs::mount($share['resolve_url'], '/', false, false, true))
		{
			sleep(1);
			$status = '404 Not Found';
			header("HTTP/1.1 $status");
			header("X-WebDAV-Status: $status", true);
			echo "Requested resource '/".htmlspecialchars($token)."' does NOT exist!\n";
			common::egw_exit();
		}
		egw_vfs::$is_root = false;
		// need to store new fstab and vfs_user in session to allow GET requests / downloads via WebDAV
		$GLOBALS['egw_info']['server']['vfs_fstab'] = egw_vfs::mount();
		$GLOBALS['egw_info']['user']['vfs_user'] = egw_vfs::$user = $share['share_owner'];
		egw_vfs::clearstatcache();

		// update accessed timestamp
		self::$db->update(self::TABLE, array(
			'share_last_accessed' => $share['share_last_accessed']=time(),
		), array(
			'share_id' => $share['share_id'],
		), __LINE__, __FILE__);

		// store sharing object in egw object and therefore in session
		$GLOBALS['egw']->sharing = new egw_sharing($share);

		return $sessionid;
	}

	/**
	 * Server a request on a share specified in REQUEST_URI
	 */
	public function ServeRequest()
	{
		// use pure WebDAV for everything but GET requests to directories
		if (!egw_vfs::is_dir('/') || $_SERVER['REQUEST_METHOD'] != 'GET' ||
			// or unsupported browsers like ie < 10
			html::$user_agent == 'msie' && html::$ua_version < 10.0 ||
			// or if no filemanager installed (WebDAV has own autoindex)
			!file_exists(__DIR__.'/../../filemanager/inc/class.filemanager_ui.inc.php'))
		{
			//$GLOBALS['egw']->session->commit_session();
			$webdav_server = new vfs_webdav_server();
			$webdav_server->ServeRequest('/'.$this->share['share_token']);
			return;
		}
		// run full eTemplate2 UI for directories
		$_GET['path'] = '/';
		$ui = new egw_sharing_filemanager();
		$ui->index();
	}

	/**
	 * Generate a new token
	 *
	 * @return string
	 */
	public static function token()
	{
		// generate random token (using oppenssl if available otherwise mt_rand based auth::randomstring)
		$token = function_exists('openssl_random_pseudo_bytes') ?
			base64_encode(openssl_random_pseudo_bytes(3*self::TOKEN_LENGTH/4)) :
			auth::randomstring(self::TOKEN_LENGTH);

		return $token;
	}

	/**
	 * Create a new share
	 *
	 * @param string $path either path in temp_dir or vfs with optional vfs scheme
	 * @param string $mode 'link': copy file in users tmp-dir or 'share_ro' share given vfs file, if no vfs behave as 'link'
	 * @param string $name filename to use for $mode='link', default basename of $path
	 * @param string|array $recipients one or more recipient email addresses
	 * @param array $extra =array() extra data to store
	 * @throw egw_exception_not_found if $path not found
	 * @throw egw_excpetion_assertion_failed if user temp. directory does not exist and can not be created
	 * @return array with share data, eg. value for key 'share_token'
	 */
	public static function create($path, $mode, $name, $recipients, $extra=array())
	{
		if (!isset(self::$db)) self::$db = $GLOBALS['egw']->db;

		if (empty($name)) $name = $path;

		$path2tmp =& egw_cache::getSession(__CLASS__, 'path2tmp');

		// allow filesystem path only for temp_dir
		$temp_dir = $GLOBALS['egw_info']['server']['temp_dir'].'/';
		if (substr($path, 0, strlen($temp_dir)) == $temp_dir)
		{
			$mode = 'link';
		}
		elseif(parse_url($path, PHP_URL_SCHEME) !== 'vfs')
		{
			$path = 'vfs://default'.($path[0] == '/' ? '' : '/').$path;
		}
		// check if file exists and is readable
		if (!file_exists($path) || is_readable($path))
		{
			throw new egw_exception_not_found("'$path' NOT found!");
		}
		// check if file has been shared before
		if (($mode != 'link' || isset($path2tmp[$path])) &&
			($share = self::$db->select(self::TABLE, '*', array(
				'share_path' => $mode == 'link' ? $path2tmp[$path] : egw_vfs::parse_url($path, PHP_URL_PATH),
				'share_owner' => $GLOBALS['egw_info']['user']['account_id'],
			)+$extra, __LINE__, __FILE__)->fetch()))
		{
			// if yes, just add additional recipients
			$share['share_recipients'] = $share['share_recipients'] ? explode(',', $share['recipients']) : array();
			$need_save = false;
			foreach((array)$recipients as $recipient)
			{
				if (!in_array($recipient, $share['recipients']))
				{
					$share['recipients'][] = $recipient;
					$need_save = true;
				}
			}
			$share['share_recipients'] = implode(',', $share['recipients']);
			if ($need_save)
			{
				self::$db->update(self::TABLE, array(
					'share_recipients' => $share['share_recipients'],
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
				if (!egw_vfs::file_exists($user_tmp) && !egw_vfs::mkdir($user_tmp))
				{
					throw new egw_exception_assertion_failed("Could NOT create temp. directory '$user_tmp'!");
				}
				$n = 0;
				do {
					$tmp_file = egw_vfs::concat($user_tmp, ($n?$n.'.':'').egw_vfs::basename($name));
				} while(!($fp = egw_vfs::fopen($tmp_file, 'x')) && $n++ < 100);

				if ($n >= 100)
				{
					throw new egw_exception_assertion_failed("Could NOT create temp. file '$tmp_file'!");
				}
				fclose($fp);

				if (!copy($path, egw_vfs::PREFIX.$tmp_file))
				{
					throw new egw_exception_assertion_failed("Could NOT create temp. file '$tmp_file'!");
				}
				// store temp. path in session, to be able to add more recipients
				$path2tmp[$path] = $tmp_file;

				$path = $tmp_file;
			}

			$i = 0;
			while(true)	// self::token() can return an existing value
			{
				try {
					self::$db->insert(self::TABLE, $share = array(
						'share_token' => self::token(),
						'share_path' => egw_vfs::parse_url($path, PHP_URL_PATH),
						'share_owner' => $GLOBALS['egw_info']['user']['account_id'],
						'share_with' => implode(',', (array)$recipients),
						'share_created' => time(),
					)+$extra, false, __LINE__, __FILE__);

					$share['share_id'] = self::$db->get_last_insert_id(self::TABLE, 'share_id');
					break;
				}
				catch(egw_exception_db $e) {
					if ($i++ > 3) throw $e;
					unset($e);
				}
			}
		}
		return $share;
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

		$link = egw::link('/share.php').'/'.$share;
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

if (file_exists(__DIR__.'/../../filemanager/inc/class.filemanager_ui.inc.php'))
{
	require_once __DIR__.'/../../filemanager/inc/class.filemanager_ui.inc.php';

	class egw_sharing_filemanager extends filemanager_ui
	{
		/**
		 * Get the configured start directory for the current user
		 *
		 * @return string
		 */
		static function get_home_dir()
		{
			return '/';
		}
	}
}