<?php

/**
 * eGroupWare API: VFS - static methods to use the new eGW virtual file system
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage vfs
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @author Andreas Stöckel <as@stylite.de>
 * @version $Id$
 */

class egw_vfs_utils
{

	/**
	 * Returns the URL to the thumbnail of the given file. The thumbnail may simply
	 * be the mime-type icon, or - if activated - the preview with the given thsize.
	 *
	 * @param string $file name of the file
	 * @param int $thsize the size of the preview - false if the default should be used.
	 * @param string $mime if you already know the mime type of the file, you can supply
	 * 	it here. Otherwise supply "false".
	 */
	public static function thumbnail_url($file, $thsize = false, $mime = false)
	{
		// Retrive the mime-type of the file
		if (!$mime)
		{
			$mime = egw_vfs::mime_content_type($file);
		}

		$image = "";

		// Seperate the mime type into the primary and the secondary part
		list($mime_main, $mime_sub) = explode('/', $mime);

		if ($mime_main == 'egw')
		{
			$image = $GLOBALS['egw']->common->image($mime_sub, 'navbar');
		}
		else if ($file && $mime_main == 'image' && in_array($mime_sub, array('png','jpeg','jpg','gif','bmp')) &&
		         (string)$GLOBALS['egw_info']['server']['link_list_thumbnail'] != '0' &&
		         (string)$GLOBALS['egw_info']['user']['preferences']['common']['link_list_thumbnail'] != '0' &&
		         (!is_array($value) && ($stat = egw_vfs::stat($file)) ? $stat['size'] : $value['size']) < 1500000)
		{
			if (substr($file, 0, 6) == '/apps/')
			{
				$file = parse_url(egw_vfs::resolve_url_symlinks($path), PHP_URL_PATH);
			}

			//Assemble the thumbnail parameters
			$thparams = array();
			$thparams['path'] = $file;
			if ($thsize)
			{
				$thparams['thsize'] = $thsize;
			}
			$image = $GLOBALS['egw']->link('/etemplate/thumbnail.php', $thparams);
		}
		else
		{
			list($app, $name) = explode("/", egw_vfs::mime_icon($mime), 2);
			$image = $GLOBALS['egw']->common->image($app, $name);
		}

		return $image;
	}

}


