<?php
/**
 * EGroupware API: VFS - shared base of Vfs class and Vfs-stream-wrapper
 *
 * @link https://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage vfs
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2008-20 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 */

namespace EGroupware\Api\Vfs;

use EGroupware\Api\Config;
use EGroupware\Api\Vfs;
use EGroupware\Api;

/**
 * Shared base of Vfs class and Vfs-stream-wrapper
 */
class Base
{
	/**
	 * Scheme / protocol used for this stream-wrapper
	 */
	const SCHEME = 'vfs';
	/**
	 * Mime type of directories, the old vfs used 'Directory', while eg. WebDAV uses 'httpd/unix-directory'
	 */
	const DIR_MIME_TYPE = 'httpd/unix-directory';
	/**
	 * Readable bit, for dirs traversable
	 */
	const READABLE = 4;
	/**
	 * Writable bit, for dirs delete or create files in that dir
	 */
	const WRITABLE = 2;
	/**
	 * Excecutable bit, here only use to check if user is allowed to search dirs
	 */
	const EXECUTABLE = 1;
	/**
	 * mode-bits, which have to be set for links
	 */
	const MODE_LINK = 0120000;

	/**
	 * How much should be logged to the apache error-log
	 *
	 * 0 = Nothing
	 * 1 = only errors
	 * 2 = all function calls and errors (contains passwords too!)
	 */
	const LOG_LEVEL = 1;

	/**
	 * Our fstab in the form mount-point => url
	 *
	 * The entry for root has to be the first, or more general if you mount into subdirs the parent has to be before!
	 *
	 * @var array
	 */
	protected static $fstab = array(
		'/'     => 'sqlfs://$host/',
		'/apps' => 'links://$host/apps',
	);

	/**
	 * Mounts $url under $path in the vfs, called without parameter it returns the fstab
	 *
	 * The fstab is stored in the eGW configuration and used for all eGW users.
	 *
	 * @param string $url =null url of the filesystem to mount, eg. oldvfs://default/
	 * @param string $path =null path to mount the filesystem in the vfs, eg. /
	 * @param boolean $check_url =null check if url is an existing directory, before mounting it
	 *    default null only checks if url does not contain a $ as used in $user or $pass
	 * @param boolean|int $persistent_mount =true create a persitent mount, or only a temprary for current request,
	 *    or integer account_id to mount persistent for a given user or group
	 * @param boolean $clear_fstab =false true clear current fstab, false (default) only add given mount
	 * @return array|boolean array with fstab, if called without parameter or true on successful mount
	 */
	static function mount($url = null, $path = null, $check_url = null, $persistent_mount = true, $clear_fstab = false)
	{
		if ($check_url === true || !isset($check_url) && strpos($url, '$') === false)
		{
			$check_url = strtr($url, [
				'$user' => $GLOBALS['egw_info']['user']['account_lid'],
				'$pass' => urlencode($GLOBALS['egw_info']['user']['passwd']),
				'$host' => $GLOBALS['egw_info']['user']['domain'],
				'$home' => str_replace(array('\\\\', '\\'), array('', '/'), $GLOBALS['egw_info']['user']['homedirectory']),
			]);
		}

		if(!isset($GLOBALS['egw_info']['server']['vfs_fstab']))    // happens eg. in setup
		{
			$api_config = Config::read('phpgwapi');
			if(isset($api_config['vfs_fstab']) && is_array($api_config['vfs_fstab']))
			{
				self::$fstab = $api_config['vfs_fstab'];
			}
			else
			{
				self::$fstab = array(
					'/'     => 'sqlfs://$host/',
					'/apps' => 'links://$host/apps',
				);
			}
			unset($api_config);
		}
		if(is_null($url) || is_null($path))
		{
			if(self::LOG_LEVEL > 1)
			{
				error_log(__METHOD__ . '(' . array2string($url) . ',' . array2string($path) . ') returns ' . array2string(self::$fstab));
			}
			return self::$fstab;
		}
		if(!Vfs::$is_root)
		{
			if(self::LOG_LEVEL > 0)
			{
				error_log(__METHOD__ . '(' . array2string($url) . ',' . array2string($path) . ') permission denied, you are NOT root!');
			}
			return false;    // only root can mount
		}
		if($clear_fstab)
		{
			self::$fstab = array();
		}
		if(isset(self::$fstab[$path]) && self::$fstab[$path] === $url)
		{
			if(self::LOG_LEVEL > 0)
			{
				error_log(__METHOD__ . '(' . array2string($url) . ',' . array2string($path) . ') already mounted.');
			}
			return true;    // already mounted
		}
		self::load_wrapper(Vfs::parse_url($url, PHP_URL_SCHEME));

		if ($check_url && (!file_exists($check_url) || opendir($check_url) === false))
		{
			if(self::LOG_LEVEL > 0)
			{
				error_log(__METHOD__ . '(' . array2string($url) . ',' . array2string($path) . ') url does NOT exist!');
			}
			return false;    // url does not exist
		}
		self::$fstab[$path] = $url;

		uksort(self::$fstab, function ($a, $b)
		{
			return strlen($a) - strlen($b);
		});

		if($persistent_mount)
		{
			if($persistent_mount === true)
			{
				Config::save_value('vfs_fstab', self::$fstab, 'phpgwapi');
				$GLOBALS['egw_info']['server']['vfs_fstab'] = self::$fstab;
				// invalidate session cache
				if(method_exists($GLOBALS['egw'], 'invalidate_session_cache'))    // egw object in setup is limited
				{
					$GLOBALS['egw']->invalidate_session_cache();
				}
			}
			else
			{
				$prefs = new Api\Preferences($persistent_mount);
				$prefs->read_repository();
				$prefs->user['common']['vfs_fstab'][$path] = $url;
				$prefs->save_repository();
				// also save for current session
				$GLOBALS['egw_info']['user']['preferences']['common']['vfs_fstab'][$path] =
				$_SESSION[Api\Session::EGW_INFO_CACHE]['user']['preferences']['common']['vfs_fstab'][$path] = $url;
			}
		}
		if(self::LOG_LEVEL > 1)
		{
			error_log(__METHOD__ . '(' . array2string($url) . ',' . array2string($path) . ') returns true (successful new mount).');
		}
		return true;
	}

	/**
	 * Unmounts a filesystem part of the vfs
	 *
	 * @param string $path url or path of the filesystem to unmount
	 */
	static function umount($path)
	{
		if(!Vfs::$is_root)
		{
			if(self::LOG_LEVEL > 0)
			{
				error_log(__METHOD__ . '(' . array2string($path) . ',' . array2string($path) . ') permission denied, you are NOT root!');
			}
			return false;    // only root can mount
		}
		if(!isset(self::$fstab[$path]) && ($path = array_search($path, self::$fstab)) === false)
		{
			if(self::LOG_LEVEL > 0)
			{
				error_log(__METHOD__ . '(' . array2string($path) . ') NOT mounted!');
			}
			return false;    // $path not mounted
		}
		unset(self::$fstab[$path], $GLOBALS['egw_info']['server']['vfs_fstab'][$path]);
		Config::save_value('vfs_fstab', $GLOBALS['egw_info']['server']['vfs_fstab'], 'phpgwapi');

		unset($GLOBALS['egw_info']['user']['preferences']['common']['vfs_fstab'][$path]);
		unset($_SESSION[Api\Session::EGW_INFO_CACHE]['server']['vfs_fstab'][$path]);
		unset($_SESSION[Api\Session::EGW_INFO_CACHE]['user']['preferences']['common']['vfs_fstab'][$path]);
		$prefs = new Api\Preferences();
		$prefs->read_repository();
		unset($prefs->user['common']['vfs_fstab'][$path]);
		$prefs->save_repository();

		// invalidate session cache
		if(method_exists($GLOBALS['egw'], 'invalidate_session_cache'))    // egw object in setup is limited
		{
			$GLOBALS['egw']->invalidate_session_cache();
		}
		if(self::LOG_LEVEL > 1)
		{
			error_log(__METHOD__ . '(' . array2string($path) . ') returns true (successful unmount).');
		}
		return true;
	}

	/**
	 * Returns mount url of a full url returned by resolve_url
	 *
	 * @param string $fullurl full url returned by resolve_url
	 * @return string|NULL mount url or null if not found
	 */
	static function mount_url($fullurl, &$mounted = null)
	{
		foreach(array_reverse(self::$fstab) as $mounted => $url)
		{
			list($url_no_query) = explode('?', $url);
			if(substr($fullurl, 0, 1 + strlen($url_no_query)) === $url_no_query . '/')
			{
				return $url;
			}
		}
		return null;
	}

	/**
	 * Cache of already resolved urls
	 *
	 * @var array with path => target
	 */
	private static $resolve_url_cache = array();

	private static $wrappers;

	/**
	 * Resolve the given path according to our fstab
	 *
	 * @param string $_path
	 * @param boolean $do_symlink =true is a direct match allowed, default yes (must be false for a lstat or readlink!)
	 * @param boolean $use_symlinkcache =true
	 * @param boolean $replace_user_pass_host =true replace $user,$pass,$host in url, default true, if false result is not cached
	 * @param boolean $fix_url_query =false true append relativ path to url query parameter, default not
	 * @param ?string &$mounted =null on return mount-point of resolved url, IF $_path is a path or vfs-url, other urls return NULL!
	 * @return string|boolean false if the url cant be resolved, should not happen if fstab has a root entry
	 */
	static function resolve_url($_path, $do_symlink = true, $use_symlinkcache = true, $replace_user_pass_host = true, $fix_url_query = false, &$mounted = null)
	{
		$path = self::get_path($_path);

		// we do some caching here
		if(isset(self::$resolve_url_cache[$path]) && $replace_user_pass_host)
		{
			if(self::LOG_LEVEL > 1)
			{
				error_log(__METHOD__ . "('$path') = '" . print_r(self::$resolve_url_cache[$path], true) . "' (from cache)");
			}
			$mounted = self::$resolve_url_cache[$path]['mounted'];
			return self::$resolve_url_cache[$path]['url'];
		}
		// check if we can already resolve path (or a part of it) with a known symlinks
		if($use_symlinkcache)
		{
			$path = self::symlinkCache_resolve($path, $do_symlink);
		}
		// setting default user, passwd and domain, if it's not contained int the url
		$defaults = array(
			'user' => $GLOBALS['egw_info']['user']['account_lid'],
			'pass' => urlencode($GLOBALS['egw_info']['user']['passwd']),
			'host' => $GLOBALS['egw_info']['user']['domain'],
			'home' => str_replace(array('\\\\', '\\'), array('', '/'), $GLOBALS['egw_info']['user']['homedirectory']),
		);
		$parts = array_merge(Vfs::parse_url($_path), Vfs::parse_url($path) ?: [], $defaults);
		if(!$parts['host'])
		{
			// otherwise we get an invalid url (scheme:///path/to/something)!
			$parts['host'] = 'default';
		}

		if(!empty($parts['scheme']) && $parts['scheme'] != self::SCHEME)
		{
			if(self::LOG_LEVEL > 1)
			{
				error_log(__METHOD__ . "('$path') = '$path' (path is already an url)");
			}
			return $path;    // path is already a non-vfs url --> nothing to do
		}
		if(empty($parts['path']))
		{
			$parts['path'] = '/';
		}

		foreach(array_reverse(self::$fstab) as $mounted => $url)
		{
			if($mounted == '/' || $mounted == $parts['path'] || $mounted . '/' == substr($parts['path'], 0, strlen($mounted) + 1))
			{
				$scheme = Vfs::parse_url($url, PHP_URL_SCHEME);
				if(is_null(self::$wrappers) || !in_array($scheme, self::$wrappers))
				{
					self::load_wrapper($scheme);
				}
				if(($relative = substr($parts['path'], strlen($mounted))))
				{
					$url = Vfs::concat($url, $relative);
				}
				// if url contains url parameter, eg. from filesystem streamwrapper, we need to append relative path here too
				$matches = null;
				if($fix_url_query && preg_match('|([?&]url=)([^&]+)|', $url, $matches))
				{
					$url = str_replace($matches[0], $matches[1] . Vfs::concat($matches[2], substr($parts['path'], strlen($mounted))), $url);
				}

				if ($replace_user_pass_host)
				{
					$url = strtr($url, [
						'$user' => $parts['user'],
						'$pass' => $parts['pass'],
						'$host' => $parts['host'],
						'$home' => $parts['home'],
					]);
				}
				if (isset($parts['query']))
				{
					$url .= (parse_url($url, PHP_URL_QUERY) ? '&' : '?') . $parts['query'];
				}
				if (isset($parts['fragment']))
				{
					$url .= '#' . $parts['fragment'];
				}

				if(self::LOG_LEVEL > 1)
				{
					error_log(__METHOD__ . "('$path') = '$url'");
				}

				if(($class = self::scheme2class($scheme)) && is_callable([$class, 'replace']))
				{
					if(!($replace = call_user_func([$class, 'replace'], $url)))
					{
						return false;
					}
					$url = $replace;
				}

				// Make sure we don't cache anything with a link anywhere in the url, since it fails with eg: /apps/InfoLog/Open$/2021$.
				// is_link() is not always right here
				$is_link = is_link($url) || (self::symlinkCache_resolve(Vfs::parse_url($url, PHP_URL_PATH)) !== Vfs::parse_url($url, PHP_URL_PATH));
				if($is_link && $do_symlink)
				{
					$old_url = $url;
					$_url = self::symlinkCache_resolve($url);
					$url = @readlink($url) ?: (Vfs::parse_url($_url, PHP_URL_PATH) != $parts['path'] ?
						str_replace([$parts['path'], Vfs::parse_url($old_url, PHP_URL_SCHEME)], [$_url,
																								 Vfs::parse_url(Vfs::resolve_url($_url), PHP_URL_SCHEME)], $url) : null) ?: $url;
					$is_link = $old_url == $url;
				}
				if($replace_user_pass_host && !$is_link)
				{
					self::$resolve_url_cache[$path] = ['url' => $url, 'mounted' => $mounted];
				}

				return $url;
			}
		}
		if(self::LOG_LEVEL > 0)
		{
			error_log(__METHOD__ . "('$path') can't resolve path!\n");
		}
		trigger_error(__METHOD__ . "($path) can't resolve path!\n", E_USER_WARNING);
		return false;
	}

	/**
	 * Cache of already resolved symlinks
	 *
	 * @var array with path => target
	 */
	private static $symlink_cache = array();

	/**
	 * Add a resolved symlink to cache
	 *
	 * @param string $_path vfs path
	 * @param string $target target path
	 */
	static protected function symlinkCache_add($_path, $target)
	{
		$path = self::get_path($_path);

		if(isset(self::$symlink_cache[$path]))
		{
			// nothing to do
			return;
		}
		if($target[0] != '/')
		{
			$query = Vfs::parse_url($target, PHP_URL_QUERY);
			$target = Vfs::parse_url($target, PHP_URL_PATH);
			if($query)
			{
				// Don't cache without query, some StreamWrappers need those parameters
				$target = "?$query";
			}
		}

		self::$symlink_cache[$path] = $target;

		// sort longest path first
		uksort(self::$symlink_cache, function ($b, $a)
		{
			return strlen($a) - strlen($b);
		});
		if(self::LOG_LEVEL > 1)
		{
			error_log(__METHOD__ . "($path,$target) cache now " . array2string(self::$symlink_cache));
		}
	}

	/**
	 * Remove a resolved symlink from cache
	 *
	 * @param string $_path vfs path
	 */
	static public function symlinkCache_remove($_path)
	{
		$path = self::get_path($_path);

		unset(self::$symlink_cache[$path]);
		if(self::LOG_LEVEL > 1)
		{
			error_log(__METHOD__ . "($path) cache now " . array2string(self::$symlink_cache));
		}
	}

	/**
	 * Resolve a path from our symlink cache
	 *
	 * The cache is sorted from longer to shorter pathes.
	 *
	 * @param string $_path
	 * @param boolean $do_symlink =true is a direct match allowed, default yes (must be false for a lstat or readlink!)
	 * @return string target or path, if path not found
	 */
	public static function symlinkCache_resolve($_path, $do_symlink = true)
	{
		// remove vfs scheme, but no other schemes (eg. filesystem!)
		$path = self::get_path($_path);

		$strlen_path = strlen($path);

		foreach(self::$symlink_cache as $p => $t)
		{
			if (($strlen_p = strlen($p)) > $strlen_path) continue;	// $path can NOT start with $p

			if($path == $p)
			{
				if($do_symlink)
				{
					$target = $t;
				}
				break;
			}
			elseif(substr($path, 0, $strlen_p + 1) == $p . '/')
			{
				$target = $t . substr($path, $strlen_p);
				break;
			}
		}
		if(self::LOG_LEVEL > 1 && isset($target))
		{
			error_log(__METHOD__ . "($path) = $target");
		}
		return isset($target) ? $target : $path;
	}

	/**
	 * Clears our internal stat and symlink cache
	 *
	 * Normaly not necessary, as it is automatically cleared/updated, UNLESS Vfs::$user changes!
	 */
	static function clearstatcache()
	{
		self::$symlink_cache = self::$resolve_url_cache = array();
	}

	/**
	 * Load stream wrapper for a given schema
	 *
	 * @param string $scheme
	 * @return boolean
	 */
	static function load_wrapper($scheme)
	{
		if(!in_array($scheme, self::get_wrappers()))
		{
			switch($scheme)
			{
				case 'webdav':
				case 'webdavs':
					\Grale\WebDav\StreamWrapper::register();
					self::$wrappers[] = 'webdav';
					self::$wrappers[] = 'webdavs';
					break;
				case '':
					break;    // default file, always loaded
				default:
					// check if scheme is buildin in php or one of our own stream wrappers
					if(in_array($scheme, stream_get_wrappers()) || class_exists(self::scheme2class($scheme)))
					{
						self::$wrappers[] = $scheme;
					}
					else
					{
						trigger_error("Can't load stream-wrapper for scheme '$scheme'!", E_USER_WARNING);
						return false;
					}
			}
		}
		return true;
	}

	/**
	 * Return already loaded stream wrappers
	 *
	 * @return array
	 */
	static function get_wrappers()
	{
		if(is_null(self::$wrappers))
		{
			self::$wrappers = stream_get_wrappers();
		}
		return self::$wrappers;
	}

	/**
	 * Get the class-name for a scheme
	 *
	 * A scheme is not allowed to contain an underscore, but allows a dot and a class names only allow or need underscores, but no dots
	 * --> we replace dots in scheme with underscored to get the class-name
	 *
	 * @param string $scheme eg. vfs
	 * @return string
	 */
	static function scheme2class($scheme)
	{
		if($scheme === self::SCHEME)
		{
			return __CLASS__;
		}
		list($app, $app_scheme) = explode('.', $scheme)+[null,null];
		foreach(array(
					empty($app_scheme) ? 'EGroupware\\Api\\Vfs\\' . ucfirst($scheme) . '\\StreamWrapper' :    // streamwrapper in Api\Vfs
						'EGroupware\\' . ucfirst($app) . '\\Vfs\\' . ucfirst($app_scheme) . '\\StreamWrapper',
					// streamwrapper in $app\Vfs
					str_replace('.', '_', $scheme) . '_stream_wrapper',    // old (flat) name
				) as $class)
		{
			//error_log(__METHOD__."('$scheme') class_exists('$class')=".array2string(class_exists($class)));
			if(class_exists($class))
			{
				return $class;
			}
		}
	}

	/**
	 * Getting the path from an url (or path) AND removing trailing slashes
	 *
	 * @param string $path url or path (might contain trailing slash from WebDAV!)
	 * @param string $only_remove_scheme =self::SCHEME if given only that scheme get's removed
	 * @return string path without training slash
	 */
	static protected function get_path($path, $only_remove_scheme = self::SCHEME)
	{
		if($path[0] != '/' && (!$only_remove_scheme || Vfs::parse_url($path, PHP_URL_SCHEME) == $only_remove_scheme))
		{
			$path = Vfs::parse_url($path, PHP_URL_PATH);
		}
		// remove trailing slashes eg. added by WebDAV, but do NOT remove / from "sqlfs://default/"!
		if($path != '/')
		{
			while(mb_substr($path, -1) == '/' && $path != '/' && ($path[0] == '/' || Vfs::parse_url($path, PHP_URL_PATH) != '/'))
			{
				$path = mb_substr($path, 0, -1);
			}
		}
		return $path;
	}

	/**
	 * Check if url contains ro=1 parameter to mark mount readonly
	 *
	 * @param string $url
	 * @return boolean
	 */
	static function url_is_readonly($url)
	{
		static $cache = array();
		$ret =& $cache[$url];
		if(!isset($ret))
		{
			$matches = null;
			$ret = preg_match('/[?&]ro=([^&]+)/', $url, $matches) && $matches[1];
		}
		return $ret;
	}

	/**
	 * Allow to call methods of the underlying stream wrapper: touch, chmod, chgrp, chown, ...
	 *
	 * We cant use a magic __call() method, as it does not work for static methods!
	 *
	 * @param string $name
	 * @param array $params first param has to be the path, otherwise we can not determine the correct wrapper
	 * @param boolean|"null" $fail_silent =false should only false be returned if function is not supported by the backend,
	 *    or should an E_USER_WARNING error be triggered (default), or "null": return NULL
	 * @param int $path_param_key =0 key in params containing the path, default 0
	 * @param boolean $instanciate =false true: instanciate the class to call method $name, false: static call
	 * @return mixed return value of backend or false if function does not exist on backend
	 */
	protected static function _call_on_backend($name, array $params, $fail_silent = false, $path_param_key = 0, $instanciate = false)
	{
		$pathes = $params[$path_param_key];

		$scheme2urls = array();
		foreach(is_array($pathes) ? $pathes : array($pathes) as $path)
		{
			if(!($url = Vfs::resolve_url_symlinks($path, false, false)))
			{
				return false;
			}
			$k = (string)Vfs::parse_url($url, PHP_URL_SCHEME);
			if (!isset($scheme2urls[$k]))
			{
				$scheme2urls[$k] = array();
			}
			$scheme2urls[$k][$path] = $url;
		}
		$ret = array();
		foreach($scheme2urls as $scheme => $urls)
		{
			if($scheme)
			{
				if(!class_exists($class = Vfs\StreamWrapper::scheme2class($scheme)) || !method_exists($class, $name))
				{
					if(!$fail_silent)
					{
						trigger_error("Can't $name for scheme $scheme!\n", E_USER_WARNING);
					}
					return $fail_silent === 'null' ? null : false;
				}
				$callback = [$instanciate ? new $class($url) : $class, $name];
				if(!is_array($pathes))
				{
					$params[$path_param_key] = $url;

					return call_user_func_array($callback, $params);
				}
				$params[$path_param_key] = $urls;
				if(!is_array($r = call_user_func_array($callback, $params)))
				{
					return $r;
				}
				// we need to re-translate the urls to pathes, as they can eg. contain symlinks
				foreach($urls as $path => $url)
				{
					if(isset($r[$url]) || isset($r[$url = Vfs::parse_url($url, PHP_URL_PATH)]))
					{
						$ret[$path] = $r[$url];
					}
				}
			}
			// call the filesystem specific function (dont allow to use arrays!)
			elseif(!function_exists($name) || is_array($pathes))
			{
				return false;
			}
			else
			{
				$time = null;
				if (in_array($name, ['readlink']))
				{
					return $name($url);
				}
				return $name($url, $time);
			}
		}
		return $ret;
	}
}