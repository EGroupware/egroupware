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

use EGroupware\Api\Vfs;

/**
 * Shared base of Vfs class and Vfs-stream-wrapper
 */
class Base
{
	const PREFIX = 'vfs://default';
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
	 * Allow to call methods of the underlying stream wrapper: touch, chmod, chgrp, chown, ...
	 *
	 * We cant use a magic __call() method, as it does not work for static methods!
	 *
	 * @param string $name
	 * @param array $params first param has to be the path, otherwise we can not determine the correct wrapper
	 * @param boolean $fail_silent =false should only false be returned if function is not supported by the backend,
	 * 	or should an E_USER_WARNING error be triggered (default)
	 * @param int $path_param_key =0 key in params containing the path, default 0
	 * @param boolean $instanciate =false true: instanciate the class to call method $name, false: static call
	 * @return mixed return value of backend or false if function does not exist on backend
	 */
	protected static function _call_on_backend($name, array $params, $fail_silent=false, $path_param_key=0, $instanciate=false)
	{
		$pathes = $params[$path_param_key];

		$scheme2urls = array();
		foreach(is_array($pathes) ? $pathes : array($pathes) as $path)
		{
			if (!($url = Vfs::resolve_url_symlinks($path,false,false)))
			{
				return false;
			}
			$k=(string)Vfs::parse_url($url,PHP_URL_SCHEME);
			if (!(is_array($scheme2urls[$k]))) $scheme2urls[$k] = array();
			$scheme2urls[$k][$path] = $url;
		}
		$ret = array();
		foreach($scheme2urls as $scheme => $urls)
		{
			if ($scheme)
			{
				if (!class_exists($class = Vfs\StreamWrapper::scheme2class($scheme)) || !method_exists($class,$name))
				{
					if (!$fail_silent) trigger_error("Can't $name for scheme $scheme!\n",E_USER_WARNING);
					return false;
				}
				$callback = [$instanciate ? new $class($url) : $class, $name];
				if (!is_array($pathes))
				{
					$params[$path_param_key] = $url;

					return call_user_func_array($callback, $params);
				}
				$params[$path_param_key] = $urls;
				if (!is_array($r = call_user_func_array($callback, $params)))
				{
					return $r;
				}
				// we need to re-translate the urls to pathes, as they can eg. contain symlinks
				foreach($urls as $path => $url)
				{
					if (isset($r[$url]) || isset($r[$url=Vfs::parse_url($url,PHP_URL_PATH)]))
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
				return $name($url,$time);
			}
		}
		return $ret;
	}
}
