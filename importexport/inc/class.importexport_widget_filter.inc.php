<?php
/**
 * Widget for setting filters
 *
 * @author Nathan Gray
 * @version $Id$
 */

use EGroupware\Api;
use EGroupware\Api\Etemplate;

/**
 * Extend custom fields and make an general advanced filter
 *
 * Much of this is copied from customfields_widget and adapted.
 * We assume that the app can handle pretty much any field => value combination
 * we send via col_filter, but the plugin gets a chance to interpret the filter
 * settings.
 *
 * Selectboxes are easy, we just turn them into multi-selects.
 *
 * For dates we either use a relative date selection, or a literal date selection.
 * Relative date options are in importexport_helper_functions::$relative_dates
 *
 * Most text fields are ignored.
 */
class importexport_widget_filter extends Etemplate\Widget\Transformer
{

	protected static $prefix = '';

	protected static $transformation = array(
		'type' => 'customfields'
	 );

	/**
	 * Adapt the settings to custom fields widget
	 *
	 * @param string $cname
	 */
	public function beforeSendToClient($cname, Array $expand = Array())
	{
		$form_name = self::form_name($cname, $this->id);
		if($this->getElementAttribute($form_name, 'customfields'))
		{
			// Already done?  Still need to process, or sel_options may be missing
			unset(self::$request->modifications[$form_name]);
		}
		$value =& self::get_array(self::$request->content, $form_name, true);
		$fields = $value['fields'];
		unset($value['fields']);
		$relative_dates = $this->attrs['relative_dates'];

		// Fallback, so there's something there...
		if(!is_array($fields))
		{
			error_log("$this has no fields");
			self::$transformation = array(
				'type' => 'label',
				'value' => 'No fields'
			);
			return parent::beforeSendToClient($cname);
		}

		// Need to clear this if it's not needed.  It causes Chosen selectboxes
		// to bind to this label.
		self::$transformation['label'] = '';

		$this->setElementAttribute($form_name, 'prefix', self::$prefix);

		$n = 1;
		foreach($fields as $lname => &$field)
		{
			$type =& $field['type'];

			// No filters are required
			$field['needed'] = false;

			switch($type)
			{
				case 'date':
				case 'date-time':
					// Need a range here
					$type = $field['type'] = 'date-range';
					$options = '';
					$this->setElementAttribute($form_name.'['.self::$prefix.$lname.']', 'relative', $relative_dates);
					$this->setElementAttribute($form_name.'['.self::$prefix.$lname.']', 'blur', $field['empty_label'] ? $field['empty_label'] : lang('All...'));
					break;
				case 'ajax_select' :
					// Set some reasonable defaults for the widget
					$options = array(
						'get_title'     => 'etemplate.ajax_select_widget.array_title',
						'get_rows'      => 'etemplate.ajax_select_widget.array_rows',
						'id_field'      => ajax_select_widget::ARRAY_KEY,
					);
					if($field['rows']) {
						$options['num_rows'] = $field['rows'];
					}

					// If you specify an option known to the AJAX Select widget, it will be pulled from the list of values
					// and used as such.  All unknown values will be used for selection, not passed through to the query
					if (isset($field['values']['@']))
					{
						$options['values'] = Api\Storage\Customfields::get_options_from_file($field['values']['@']);
						unset($field['values']['@']);
					} else {
						$options['values'] = array_diff_key($field['values'], array_flip(ajax_select_widget::$known_options));
					}
					$options = array_merge($options, array_intersect_key($field['values'], array_flip(ajax_select_widget::$known_options)));


					break;
				case 'select-cat':
					$this->setElementAttribute($form_name.'['.self::$prefix.$lname.']', 'other', $field['rows']);
					// fall through
				case 'select':
				default:
					if(strpos($field['type'],'select') === 0)
					{
						if($field['values'] && count($field['values']) == 1 && isset($field['values']['@']))
						{
							$field['values'] = Api\Storage\Customfields::get_options_from_file($field['values']['@']);
						}
						foreach((array)$field['values'] as $key => $val)
						{
							if (substr($val = lang($val),-1) != '*')
							{
								$field['values'][$key] = $val;
							}
						}

						// We don't want the 'All' or 'Select...' if it's there
						if(is_array($field['values']) && $field['values'][''])
						{
							unset($field['values']['']);
						}
						$this->setElementAttribute($form_name.'['.self::$prefix.$lname.']', 'empty_label', array_key_exists('empty_label', $field ) ? $field['empty_label'] : '');
						$this->setElementAttribute($form_name.'['.self::$prefix.$lname.']', 'tags', array_key_exists('tags', $field ) ? $field['tags'] : TRUE);
						$this->setElementAttribute($form_name.'['.self::$prefix.$lname.']', 'multiple', array_key_exists('multiple', $field ) ? $field['multiple'] : TRUE);
					}
					else if( $GLOBALS['egw_info']['apps'][$field['type']])
					{
						// Links
					}
					else
					{
						error_log('Trying to filter with unsupported field type ' . $lname . ': ' . $field['type']);
					}
			}

			// Send select options
			if($field['values'])
			{
				self::$request->sel_options[self::$prefix.$lname] = $field['values'];
			}
			$widget = self::factory($type, '<'.$type.' type="'.$type.'" id="'.self::$prefix.$lname.'"/>', self::$prefix.$lname);
			if(method_exists($widget, 'beforeSendToClient'))
			{
				$widget->id = self::$prefix.$lname;
				$widget->attrs['type'] = $type;
				if($type == 'link-to')
				{
					$widget->attrs['only_app'] = $field['type'];
				}
				$widget->beforeSendToClient($cname);
			}
			unset($widget);
		}

		parent::beforeSendToClient($cname, $expand);

		$this->setElementAttribute($form_name, 'customfields', $fields);
		$this->setElementAttribute($form_name, 'fields',array_fill_keys(array_keys($fields), true));
		return false;
	}

	public function validate($cname, array $expand, array $content, &$validated=array())
	{
		$form_name = self::form_name($cname, $this->id, $expand);
		if (!$this->is_readonly($cname, $form_name))
		{
			$value_in = (array)self::get_array($content, $form_name);
			$valid =& self::get_array($validated, $this->id ? $form_name : $field, true);

			foreach($value_in as $key => $value)
			{
				// Client side cf widget automatically prefixes #
				$valid[substr($key,strlen(self::$prefix))] = $value;
			}
		}
	}
}
// Register, or it won't be found
Etemplate\Widget::registerWidget('importexport_widget_filter', array('filter'));