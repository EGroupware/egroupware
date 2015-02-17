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
  Set global flag to indicate for which config settings we have equally named validation methods
*/
$GLOBALS['egw_info']['server']['found_validation_hook'] = array('vfs_image_dir');

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

		// Set the global now, or the old value will get re-loaded
		$GLOBALS['egw_info']['server']['vfs_image_dir'] = $vfs_image_dir;
	}
}