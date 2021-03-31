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
	 * Method to replace sharing url with sharee and shared path, and to shortcut Vfs\StreamWrapper::resolve_url()
	 *
	 * @param $url
	 * @return bool|string
	 */
	static function replace($url)
	{
		$parts = Vfs::parse_url($url);

		$hash = $parts['user'] ?: explode('/', $parts['path'])[1];
		$rel_path = empty($parts['user']) ? preg_replace('|^/[^/]+|', '', $parts['path']) : $parts['path'];

		try
		{
			if (empty($hash)) throw new Api\Exception\NotFound('Hash must not be empty', 404);

			Api\Sharing::check_token(false, $share, $hash, $parts['pass'] ?? '');

			return self::share2url($share, $rel_path);
		}
		catch (Api\Exception $e) {
			_egw_log_exception($e);
			return false;
		}
	}

	/**
	 * Generate sharing URL from share
	 *
	 * @param array $share as returned eg. by Api\Sharing::check_token()
	 * @return string
	 * @throws Api\Exception\NotFound if sharee was not found
	 */
	static function share2url(array $share, $rel_path='')
	{
		if (empty($share['share_owner']) || !($account_lid = Api\Accounts::id2name($share['share_owner'])))
		{
			throw new Api\Exception\NotFound('Share owner not found', 404);
		}
		return Vfs::concat('vfs://'.$account_lid.'@default'.Vfs::parse_url($share['share_path'], PHP_URL_PATH), $rel_path).
			($share['share_writable'] & 1 ? '' : '?ro=1');
	}

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
	static function resolve_url($url,$do_symlink=true,$use_symlinkcache=true,$replace_user_pass_host=true,$fix_url_query=false, &$mounted=null)
	{
		return self::replace($url);
	}

	/**
	 * This method is called in response to stat() calls on the URL paths associated with the wrapper.
	 *
	 * Overwritten to set sharee as user in context for ACL checks.
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
			$this->check_set_context($stat['url']);
		}
		return $stat;
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
