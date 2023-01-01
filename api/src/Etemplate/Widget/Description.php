<?php
/**
 * EGroupware - eTemplate widget baseclass
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage etemplate
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 * @copyright 2002-16 by RalfBecker@outdoor-training.de
 * @version $Id$
 */

namespace EGroupware\Api\Etemplate\Widget;

use EGroupware\Api\Etemplate;

/**
 * Description widget
 *
 * Reimplemented to set legacy options
 */
class Description extends Etemplate\Widget
{
	/**
	 * (Array of) comma-separated list of legacy options to automatically replace when parsing with set_attrs
	 *
	 * @var string|array
	 */
	protected $legacy_options = 'bold-italic,link,activate_links,label_for,link_target,link_popup_size,link_title';

	/**
	 * Set up what we know on the server side.
	 *
	 * Encode html specialchars (eg. < to &lt;) because client-side core
	 * widget runs decoding for the value causes elimination of none
	 * encoded html chars. This will help included links inside description
	 * get displayed if activate_links = ture for description widget is set.
	 *
	 * @param string $cname
	 * @param array $expand values for keys 'c', 'row', 'c_', 'row_', 'cont'
	 */
	public function beforeSendToClient($cname, array $expand=null)
	{
		if (!empty($this->attrs['activate_links']) && !empty($this->attrs['activateLinks']))
		{
			$form_name = self::form_name($cname, $this->id, $expand);
			$value =& self::get_array(self::$request->content, $form_name);
			if (!empty($value))
			{
				$value = htmlspecialchars($value);
			}
		}
	}
}