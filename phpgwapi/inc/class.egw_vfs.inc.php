<?php
/**
 * eGroupWare API: VFS - static methods to use the new eGW virtual file system
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage vfs
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2008-16 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @version $Id$
 */

use EGroupware\Api\Vfs;
use EGroupware\Api;

/**
 * @deprecated use EGroupware\Api\Vfs
 */
class egw_vfs extends Vfs
{
	/**
	 * Get the closest mime icon
	 *
	 * @param string $mime_type
	 * @param boolean $et_image =true return $app/$icon string for etemplate (default),
	 *	'url' for 'url' or false for an html image tag (deprecated)
	 * @param int $size =128
	 * @return string
	 */
	static function mime_icon($mime_type, $et_image=true, $size=128)
	{
		$img = parent::mime_icon($mime_type, $et_image && $et_image !== 'url', $size);

		if (!$et_image)
		{
			list(,$img) = explode('/', $img);
			return html::image('etemplate', $img, Api\MimeMagic::mime2label($mime_type));
		}
		return $img;
	}
}
