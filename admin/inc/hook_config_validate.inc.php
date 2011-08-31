<?php
/**
 * EGroupware administration
 *
 * @link http://www.egroupware.org
 * @package setup
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/*
  Set a global flag to indicate this file was found by setup/config.php.
  config.php will unset it after parsing the form values.
*/
$GLOBALS['egw_info']['server']['found_validation_hook'] = True;

/**
 * Check VFS dir exists and delete image map to recreate it, if vfs-image-dir changes
 *
 * @param string
 */
function vfs_image_dir($vfs_image_dir)
{
	//error_log(__FUNCTION__.'() vfs_image_dir='.array2string($vfs_image_dir).' was '.array2string($GLOBALS['egw_info']['server']['vfs_image_dir']));
	if (!empty($vfs_image_dir))
	{
		if (!egw_vfs::file_exists($vfs_image_dir) || !egw_vfs::is_dir($vfs_image_dir))
		{
			$GLOBALS['config_error'] = lang('VFS directory "%1" NOT found!',$vfs_image_dir);
			return;
		}
	}
	if ($vfs_image_dir != (string)$GLOBALS['egw_info']['server']['vfs_image_dir'])
	{
		common::delete_image_map();
	}
}