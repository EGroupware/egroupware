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