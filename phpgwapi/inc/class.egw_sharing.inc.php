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
	protected $db;

	/**
	 * Constructor
	 */
	public function __construct()
	{
		$this->db = $GLOBALS['egw']->db;
	}

	/**
	 * Server a request on a share specified in REQUEST_URI
	 */
	public function ServeRequest()
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

		if (empty($token) || !($share = $this->db->select(self::TABLE, '*', array(
			'share_token' => $token,
			'(share_expires IS NULL OR share_expires > '.$this->db->quote(time(), 'date').')',
		), __LINE__, __FILE__)->fetch()))
		{
			sleep(1);
			$status = '404 Not Found';
			header("HTTP/1.1 $status");
			header("X-WebDAV-Status: $status", true);
			echo "Requested resource '".htmlspecialchars($path_info)."' does NOT exist!\n";
			common::egw_exit();
		}
		$share['resolve_url'] = egw_vfs::resolve_url($share['share_path']);
		//_debug_array($share);

		// arrange vfs to only contain shared url
		egw_vfs::$is_root = true;
		if (!egw_vfs::mount($share['resolve_url'], '/', false, false, true))
		{
			sleep(1);
			$status = '404 Not Found';
			header("HTTP/1.1 $status");
			header("X-WebDAV-Status: $status", true);
			echo "Requested resource '".htmlspecialchars($path_info)."' does NOT exist!\n";
			common::egw_exit();
		}
		egw_vfs::$is_root = false;
		egw_vfs::$user = $GLOBALS['egw_info']['user']['account_id'] = $share['share_owner'];
		egw_vfs::clearstatcache();
		// ToDo: password and write protection

		//$GLOBALS['egw']->session->commit_session();
		$webdav_server = new vfs_webdav_server();
		$webdav_server->ServeRequest('/'.$token);
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