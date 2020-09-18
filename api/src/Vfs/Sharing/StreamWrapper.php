<?php
/**
 * EGroupware API: VFS - sharing stream wrapper
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage vfs
 * @author Ralf Becker <rb@egroupware.org>
 * @copyright (c) 2020 by Ralf Becker <rb@egroupware.org>
 */

namespace EGroupware\Api\Vfs\Sharing;

use EGroupware\Api\Vfs;
use EGroupware\Api;

/**
 * VFS - sharing stream wrapper
 *
 * Sharing stream wrapper allows to mount a share represented by it's hash and optional password to be mounted
 * into EGroupware's VFS: sharing://<hash>[:<password>]@default/ --> vfs://<sharee>@default/<shared-path>
 */
class StreamWrapper extends Vfs\StreamWrapper
{
	const SCHEME = 'sharing';
	const PREFIX = 'sharing://default';

	/**
	 * Resolve the given path according to our fstab
	 *
	 * @param string $url
	 * @param boolean $do_symlink =true is a direct match allowed, default yes (must be false for a lstat or readlink!)
	 * @param boolean $use_symlinkcache =true
	 * @param boolean $replace_user_pass_host =true replace $user,$pass,$host in url, default true, if false result is not cached
	 * @param boolean $fix_url_query =false true append relativ path to url query parameter, default not
	 * @return string|boolean false if the url cant be resolved, should not happen if fstab has a root entry
	 */
	static function resolve_url($url, $do_symlink = true, $use_symlinkcache = true, $replace_user_pass_host = true, $fix_url_query = false)
	{
		$parts = Vfs::parse_url($url);

		$hash = $parts['user'] ?: explode('/', $parts['path'])[1];
		$rel_path = empty($parts['user']) ? preg_replace('|^/[^/]+|', '', $parts['path']) : $parts['path'];

		try
		{
			if (empty($hash)) throw new Api\Exception\NotFound('Hash must not be empty', 404);

			Api\Sharing::check_token(false, $share, $hash, $parts['pass'] ?? '');

			if (empty($share['share_owner']) || !($account_lid = Api\Accounts::id2name($share['share_owner'])))
			{
				throw new Api\Exception\NotFound('Share owner not found', 404);
			}
			return Vfs::concat('vfs://'.$account_lid.'@default'.Vfs::parse_url($share['share_path'], PHP_URL_PATH), $rel_path).
				($share['share_writable'] ? '' : '?ro=1');
		}
		catch (Api\Exception $e) {
			_egw_log_exception($e);
			return false;
		}
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
	 * @param boolean $try_create_home =false should a user home-directory be created automatic, if it does not exist
	 * @param boolean $check_symlink_components =true check if path contains symlinks in path components other then the last one
	 * @return array
	 */
	function url_stat ( $path, $flags, $try_create_home=false, $check_symlink_components=true, $check_symlink_depth=self::MAX_SYMLINK_DEPTH, $try_reconnect=true )
	{
		if (($stat = parent::url_stat($path, $flags, $try_create_home, $check_symlink_components, $check_symlink_depth, $try_reconnect)))
		{
			$this->check_set_context($stat['url'], true);
		}
		return $stat;
	}

	/**
	 * The stream_wrapper interface checks is_{readable|writable|executable} against the webservers uid,
	 * which is wrong in case of our vfs, as we use the current users id and memberships
	 *
	 * @param string $path path
	 * @param int $check mode to check: one or more or'ed together of: 4 = Vfs::READABLE,
	 * 	2 = Vfs::WRITABLE, 1 = Vfs::EXECUTABLE
	 * @param array|boolean $stat =null stat array or false, to not query it again
	 * @return boolean
	 */
	function check_access($path, $check, $stat=null)
	{
		if (!isset($stat)) $stat = $this->url_stat($path, 0);

		return $this->parent_check_access($path, $check, $stat);
	}

	/**
	 * Store properties for a single ressource (file or dir)
	 *
	 * @param string $path string with path
	 * @param array $props array of array with values for keys 'name', 'ns', 'val' (null to delete the prop)
	 * @return boolean true if props are updated, false otherwise (eg. ressource not found)
	 */
	function proppatch($path,array $props)
	{
		if (!($url = self::resolve_url($path)))
		{
			return false;
		}
		return Vfs::proppatch($url, $props);
	}

	/**
	 * Read properties for a ressource (file, dir or all files of a dir)
	 *
	 * @param array|string $path (array of) string with path
	 * @param string $ns ='http://egroupware.org/' namespace if propfind should be limited to a single one, otherwise use null
	 * @return array|boolean array with props (values for keys 'name', 'ns', 'val'), or path => array of props for is_array($path)
	 * 	false if $path does not exist
	 */
	function propfind($path,$ns=self::DEFAULT_PROP_NAMESPACE)
	{
		if (!($url = self::resolve_url($path)))
		{
			return false;
		}
		return Vfs::propfind($url, $ns);
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
