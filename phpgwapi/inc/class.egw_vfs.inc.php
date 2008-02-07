<?php
/**
 * eGroupWare API: VFS - static methods to use the new eGW virtual file system
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage vfs
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2008 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @version $Id$
 */

/**
 * Class containing static methods to use the new eGW virtual file system
 *
 * This extension of the vfs stream-wrapper allows to use the following static functions, 
 * which only allow access to the eGW VFS and need no 'vfs://default' prefix for filenames:
 * 
 * - resource egw_vfs::fopen($path,$mode) like fopen, returned resource can be used with fwrite etc.
 * - resource egw_vfs::opendir($path) like opendir, returned resource can be used with readdir etc.
 * - boolean egw_vfs::copy($from,$to) like copy
 * - boolean egw_vfs::rename($old,$new) renaming or moving a file in the vfs
 * - boolean egw_vfs::mkdir($path) creating a new dir in the vfs
 * - boolean egw_vfs::rmdir($path) removing (an empty) directory
 * - boolean egw_vfs::unlink($path) removing a file
 * - boolean egw_vfs::touch($path,$mtime=null) touch a file
 * - boolean egw_vfs::stat($path) returning status of file like stat(), but only with string keys (no numerical indexes)! 
 * 
 * With the exception of egw_vfs::touch() (not yet part of the stream_wrapper interface) 
 * you can always use the standard php functions, if you add a 'vfs://default' prefix
 * to every filename or path. Be sure to always add the prefix, as the user otherwise gains
 * access to the real filesystem of the server!
 * 
 * The two following methods can be used to persitently mount further filesystems (without editing the code):
 * 
 * - boolean/array egw_vfs::mount($url,$path) to mount $ur on $path or to return the fstab when called without argument
 * - boolean egw_vfs::umount($path) to unmount a path or url
 * 
 * The stream wrapper interface allows to access hugh files in junks to not be limited by the 
 * memory_limit setting of php. To do you should path a resource to the opened file and not the content:
 * 
 * 		$file = egw_vfs::fopen('/home/user/somefile','r');
 * 		$content = fread($file,1024);
 * 
 * You can also attach stream filters, to eg. base64 encode or compress it on the fly, 
 * without the need to hold the content of the whole file in memmory. 
 * 
 * If you want to copy a file, you can use stream_copy_to_stream to do a copy of a file far bigger then 
 * php's memory_limit:
 * 
 * 		$from = egw_vfs::fopen('/home/user/fromfile','r');
 * 		$to = egw_vfs::fopen('/home/user/tofile','w');
 * 
 * 		stream_copy_to_stream($from,$to);
 * 
 * The static egw_vfs::copy() method does exactly that, but you have to do it eg. on your own, if
 * you want to copy eg. an uploaded file into the vfs.
 */
class egw_vfs extends vfs_stream_wrapper
{
	/**
	 * fopen working on just the eGW VFS
	 *
	 * @param string $path filename with absolute path in the eGW VFS
	 * @param string $mode 'r', 'w', ... like fopen
	 * @return resource
	 */
	static function fopen($path,$mode)
	{
		if ($path[0] != '/')
		{
			throw new egw_exception_assertion_failed("Filename '$path' is not an absolute path!");
		}
		return fopen(self::SCHEME.'://default'.$path);
	}
	
	/**
	 * opendir working on just the eGW VFS
	 *
	 * @param string $path filename with absolute path in the eGW VFS
	 * @return resource
	 */
	static function opendir($path)
	{
		if ($path[0] != '/')
		{
			throw new egw_exception_assertion_failed("Directory '$path' is not an absolute path!");
		}
		return opendir(self::SCHEME.'://default'.$path);
	}

	/**
	 * copy working on just the eGW VFS
	 *
	 * @param string $from
	 * @param string $to
	 * @return boolean
	 */
	static function copy($from,$to)
	{
		$ret = false;

		if (($from_fp = self::fopen($from,'r')) && 
			($to_fp = self::fopen($to,'w')))
		{
			$ret = stream_copy_to_stream($from,$to) !== false;
		}
 		if ($from_fp)
 		{
 			fclose($from_fp);
 		}
 		if ($to_fp)
 		{
 			fclose($to_fp);
 		}
 		return $ret;		
	}

	/**
	 * stat working on just the eGW VFS (alias of url_stat)
	 *
	 * @param string $path filename with absolute path in the eGW VFS
	 * @return resource
	 */
	static function stat($path)
	{
		if ($path[0] != '/')
		{
			throw new egw_exception_assertion_failed("File '$path' is not an absolute path!");
		}
		return self::url_stat($path);
	}
	
	
	/**
	 * Mounts $url under $path in the vfs, called without parameter it returns the fstab
	 * 
	 * The fstab is stored in the eGW configuration and used for all eGW users.
	 *
	 * @param string $url=null url of the filesystem to mount, eg. oldvfs://default/
	 * @param string $path=null path to mount the filesystem in the vfs, eg. /
	 * @return array/boolean array with fstab, if called without parameter or true on successful mount
	 */
	static function mount($url=null,$path=null)
	{
		if (is_null($url) || is_null($path))
		{
			return self::$fstab;
		}
		if (isset(self::$fstab[$path]))
		{
			return true;	// already mounted
		}
		if (stat($url) === false && opendir($url) === false)
		{
			return false;	// url does not exist
		}
		self::$fstab[$path] = $url;
		
		uksort(self::$fstab,create_function('$a,$b','return strlen($a)-strlen($b);'));
		
		config::save_value('vfs_fstab',self::$fstab,'phpgwapi');
		$GLOBALS['egw_info']['server']['vfs_fstab'] = self::$fstab;
		
		return true;
	}
	
	/**
	 * Unmounts a filesystem part of the vfs
	 *
	 * @param string $path url or path of the filesystem to unmount
	 */
	static function umount($path)
	{
		if (!isset(self::$fstab[$path]) && ($path = array_search($path,self::$fstab)) === false)
		{
			return false;	// $path not mounted
		}
		unset(self::$fstab[$path]);

		config::save_value('vfs_fstab',self::$fstab,'phpgwapi');
		$GLOBALS['egw_info']['server']['vfs_fstab'] = self::$fstab;
		
		return true;
	}
	
	/**
	 * Private constructor to prevent instanciating this class, only it's static methods should be used
	 */
	private function __construct()
	{
		
	}
}
