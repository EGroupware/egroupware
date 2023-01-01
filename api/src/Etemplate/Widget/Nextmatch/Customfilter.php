<?php
/**
 * EGroupware - eTemplate serverside implementation of the nextmatch custom filter header widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage etemplate
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 * @copyright 2002-16 by RalfBecker@outdoor-training.de
 * @version $Id$
 */

namespace EGroupware\Api\Etemplate\Widget\Nextmatch;

use EGroupware\Api\Etemplate\Widget;

/**
 * A filter widget that fakes another (select) widget and turns it into a nextmatch filter widget.
 */
class Customfilter extends Widget\Transformer
{

	protected $legacy_options = 'type,widget_options';

	/**
	 * Fill type options in self::$request->sel_options to be used on the client
	 *
	 * @param string $cname
	 * @param array $expand values for keys 'c', 'row', 'c_', 'row_', 'cont'
	 */
	public function beforeSendToClient($cname, array $expand=null)
	{
		parent::beforeSendToClient($cname, $expand);
		switch($this->attrs['type'])
		{
			case "link-entry":
				self::$transformation['type'] = $this->attrs['type'] = 'et2-nextmatch-header-entry';
				break;
			default:
				list($type) = explode('-', $this->attrs['type']);
				if($type == 'select')
				{
					if(in_array($this->attrs['type'], Widget\Select::$cached_types))
					{
						$widget_type = $this->attrs['type'];
					}
				}
				else
				{
					// Run the new widget type's beforeSendToClient
					$expanded_child = self::factory($this->attrs['type'], false, $this->id);
					$expanded_child->id = $this->id;
					$expanded_child->type = $this->attrs['type'];
					$expanded_child->attrs = $this->attrs;
					$expanded_child->run('beforeSendToClient', array($cname, $expand));
					$widget_type = $expanded_child->attrs['type'];
				}
				$this->attrs['type'] = 'et2-nextmatch-header-custom';
		}
		$form_name = self::form_name($cname, $this->id, $expand);

		self::setElementAttribute($form_name, 'options', trim($this->attrs['widgetOptions'] ?? $this->attrs['widget_options']) != '' ? ($this->attrs['widgetOptions'] ?? $this->attrs['widget_options']) : '');

		self::setElementAttribute($form_name, 'type', $this->attrs['type']);
		if($widget_type)
		{
			self::setElementAttribute($form_name, 'widgetType', $widget_type);
		}
	}


	/**
	 * Validate input
	 *
	 * @param string $cname current namespace
	 * @param array $expand values for keys 'c', 'row', 'c_', 'row_', 'cont'
	 * @param array $content
	 * @param array &$validated=array() validated content
	 */
	public function validate($cname, array $expand, array $content, &$validated=array())
	{
		$form_name = self::form_name($cname, $this->id, $expand);
		$value = $value_in = self::get_array($content, $form_name);

		$valid =& self::get_array($validated, $form_name, true);
		// returning null instead of array(), as array() will be overwritten by etemplate_new::complete_array_merge()
		// with preserved old content and therefore user can not empty a taglist
		$valid = $value ? $value : null;
	}
}

Widget::registerWidget(__NAMESPACE__ . '\\Customfilter', array('nextmatch-customfilter',
															   'et2-nextmatch-header-custom'));