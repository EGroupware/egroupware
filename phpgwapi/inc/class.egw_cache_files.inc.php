<?php
/**
 * eGroupWare API: Caching provider storing data to files
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage cache
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2009 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @version $Id$
 */

/**
 * Caching provider storing data in files
 *
 * The provider creates subdirs under a given path
 * for each values in $key
 */
class egw_cache_files implements egw_cache_provider
{
	/**
	 * Extension of file used to store expiration > 0
	 */
	const EXPIRATION_EXTENSION = '.expiration';

	/**
	 * Path of base-directory for the cache, set via parameter to the constructor or defaults to temp_dir
	 *
	 * @var string
	 */
	protected $base_path;

	/**
	 * Constructor, eg. opens the connection to the backend
	 *
	 * @throws Exception if connection to backend could not be established
	 * @param array $params eg. array(host,port) or array(directory) depending on the provider
	 */
	function __construct(array $params)
	{
		if ($params)
		{
			$this->base_path = $params[0];
		}
		else
		{
			if(!isset($GLOBALS['egw_info']['server']['temp_dir']))
			{
				if (isset($GLOBALS['egw_setup']) && isset($GLOBALS['egw_setup']->db))
				{
					$GLOBALS['egw_info']['server']['temp_dir'] = $GLOBALS['egw_setup']->db->select(config::TABLE,'config_value',array(
						'config_app'	=> 'phpgwapi',
						'config_name'	=> 'temp_dir',
					),__LINE__,__FILE__)->fetchColumn();
				}
				if (!$GLOBALS['egw_info']['server']['temp_dir'])
				{
					throw new Exception (__METHOD__."() server/temp_dir is NOT set!");
				}
			}
			$this->base_path = $GLOBALS['egw_info']['server']['temp_dir'].'/egw_cache';
		}
		if (!isset($this->base_path) || !file_exists($this->base_path) && !mkdir($this->base_path,0700,true))
		{
			throw new Exception (__METHOD__."() can't create basepath $this->base_path!");
		}
	}

	/**
	 * Stores some data in the cache
	 *
	 * @param array $keys eg. array($level,$app,$location)
	 * @param mixed $data
	 * @param int $expiration=0
	 * @return boolean true on success, false on error
	 */
	function set(array $keys,$data,$expiration=0)
	{
		if ($ret = @file_put_contents($fname=$this->filename($keys,true),serialize($data),LOCK_EX) > 0)
		{
			if ((int)$expiration > 0) file_put_contents($fname.self::EXPIRATION_EXTENSION,(string)$expiration);
		}
		return $ret;
	}

	/**
	 * Get some data from the cache
	 *
	 * @param array $keys eg. array($level,$app,$location)
	 * @return mixed data stored or NULL if not found in cache
	 */
	function get(array $keys)
	{
		if (!file_exists($fname = $this->filename($keys)))
		{
			return null;
		}
		if (file_exists($fname_expiration=$fname.self::EXPIRATION_EXTENSION) &&
			($expiration = (int)file_get_contents($fname_expiration)) &&
			filemtime($fname) < time()-$expiration)
		{
			unlink($fname);
			unlink($fname_expiration);
			return null;
		}
		return unserialize(file_get_contents($fname));
	}

	/**
	 * Delete some data from the cache
	 *
	 * @param array $keys eg. array($level,$app,$location)
	 * @return boolean true on success, false on error (eg. $key not set)
	 */
	function delete(array $keys)
	{
		if (!file_exists($fname = $this->filename($keys)))
		{
			//error_log(__METHOD__.'('.array2string($keys).") file_exists('$fname') == FALSE!");
			return false;
		}
		if (file_exists($$fname_expiration=$fname.self::EXPIRATION_EXTENSION))
		{
			unlink($fname_expiration);
		}
		//error_log(__METHOD__.'('.array2string($keys).") calling unlink('$fname')");
		return unlink($fname);
	}

	/**
	 * Create a path from $keys and $basepath
	 *
	 * @param array $keys
	 * @param boolean $mkdir=false should we create the directory
	 * @return string
	 */
	function filename(array $keys,$mkdir=false)
	{
		$fname = $this->base_path.'/'.str_replace(array(':','*'),'-',implode('/',$keys));

		if ($mkdir && !file_exists($dirname=dirname($fname)))
		{
			@mkdir($dirname,0700,true);
		}
		return $fname;
	}
}
