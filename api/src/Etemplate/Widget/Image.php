<?php
/**
 * EGroupware - eTemplate serverside image widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage etemplate
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright 2011 Nathan Gray
 * @version $Id$
 */

namespace EGroupware\Api\Etemplate\Widget;

use EGroupware\Api\Etemplate;
use EGroupware\Api;

/**
 * eTemplate image widget
 *
 * Displays image from URL, vfs, or finds by name
 */
class Image extends Etemplate\Widget
{
	/**
	 * Fill type options in self::$request->sel_options to be used on the client
	 *
	 * @param string $cname
	 * @param array $expand values for keys 'c', 'row', 'c_', 'row_', 'cont'
	 */
	public function beforeSendToClient($cname, array $expand=null)
	{
		$form_name = self::form_name($cname, $this->id, $expand);
		$value =& self::get_array(self::$request->content, $form_name);

		$image = $value != '' ? $value : $this->attrs['src'];

		if (is_string($image)) list($app,$img) = explode('/',$image,2)+[null,null];
		if (empty($app) || empty($img) || !is_dir(EGW_SERVER_ROOT.'/'.$app) || strpos($img,'/')!==false)
		{
			$img = $image;
			list($app) = explode('.',$form_name);
		}
		$src = Api\Image::find($app, $img);
		/*if(!$this->id)
		{
//			self::setElementAttribute($this->attrs['src'], 'id', $this->attrs['src']);
		}*/
		self::setElementAttribute($this->attrs['src'], 'src', $src);
	}
}
