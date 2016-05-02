<?php
/**
 * EGroupware - eTemplate serverside itempicker widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage etemplate
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 * @author Christian Binder <christian@jaytraxx.de>
 * @copyright 2002-16 by RalfBecker@outdoor-training.de
 * @copyright 2012 by Christian Binder <christian@jaytraxx.de>
 * @version $Id: class.etemplate_widget_itempicker.inc.php 36221 2011-08-20 10:27:38Z jaytraxx $
 */

namespace EGroupware\Api\Etemplate\Widget;

use EGroupware\Api\Etemplate;
use EGroupware\Api;

/**
 * eTemplate itempicker widget
 */
class ItemPicker extends Etemplate\Widget
{
	/**
	 * Constructor
	 *
	 * @param string|XMLReader $xml string with xml or XMLReader positioned on the element to construct
	 * @throws Api\Exception\WrongParameter
	 */
	public function __construct($xml = '')
	{
		if($xml) {
			parent::__construct($xml);
		}
	}

	/**
	 * Find items that match the given parameters
	 * using the egw_link class
	 */
	public static function ajax_item_search($app, $type, $pattern, $options=array())
	{
		$options['type'] = $type ? $type : $options['type'];
		$items = Api\Link::query($app, $pattern, $options);

		$response = Api\Json\Response::get();
		$response->data($items);
	}
}
Etemplate\Widget::registerWidget(__NAMESPACE__.'\\ItemPicker', 'itempicker');