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

use EGroupware\Api;
use EGroupware\Api\Vfs;

/*
  Set global flag to indicate for which config settings we have equally named validation methods
*/
$GLOBALS['egw_info']['server']['found_validation_hook'] = array('vfs_image_dir','fw_mobile_app_list');

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
		if (!Vfs::file_exists($vfs_image_dir) || !Vfs::is_dir($vfs_image_dir))
		{
			$GLOBALS['config_error'] = lang('VFS directory "%1" NOT found!',$vfs_image_dir);
			return;
		}
	}
	if ($vfs_image_dir != (string)$GLOBALS['egw_info']['server']['vfs_image_dir'])
	{
		Api\Image::invalidate();

		// Set the global now, or the old value will get re-loaded
		$GLOBALS['egw_info']['server']['vfs_image_dir'] = $vfs_image_dir;
	}
}

/**
 * Do NOT store the default to allow changing it if more apps become available
 *
 * @param type $app_list
 * @param Api\Config $c
 */
function fw_mobile_app_list($app_list, Api\Config $c)
{
	// normalize lists
	sort($app_list);
	$default_list = explode(',', Api\Framework\Ajax::DEFAULT_MOBILE_APPS);
	sort($default_list);

	if ($app_list == $default_list)
	{
		$c->config_data['fw_mobile_app_list'] = null;
	}
}