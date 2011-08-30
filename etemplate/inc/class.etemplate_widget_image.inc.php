<?php
/**
 * EGroupware - eTemplate serverside image widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright 2011 Nathan Gray
 * @version $Id$
 */

/**
 * eTemplate image widget
 * Displays image from URL, vfs, or finds by name
 */
class etemplate_widget_image extends etemplate_widget
{
	/**
	 * Fill type options in self::$request->sel_options to be used on the client
	 *
	 * @param string $cname
	 */
	public function beforeSendToClient($cname)
	{
		$attrs = $this->attrs;
		$form_name = self::form_name($cname, $this->id);
		$value =& self::get_array(self::$request->content, $form_name);

		$image = $value != '' ? $value : $this->attrs['src'];

		if (is_string($image)) list($app,$img) = explode('/',$image,2);
		if (!$app || !$img || !is_dir(EGW_SERVER_ROOT.'/'.$app) || strpos($img,'/')!==false)
		{
			$img = $image;
			list($app) = explode('.',$form_name);
		}
		$src = common::find_image($app, $img);
		if(!$this->id)
		{
//			self::setElementAttribute($this->attrs['src'], 'id', $this->attrs['src']);
		}
		self::setElementAttribute($this->attrs['src'], 'src', $src);
	}
}
etemplate_widget::registerWidget('etemplate_widget_image', array('image'));
