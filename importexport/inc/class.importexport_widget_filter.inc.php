<?php
/**
 * Widget for setting filters
 *
 * @author Nathan Gray
 * @version $Id$
 */

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
class importexport_widget_filter extends etemplate_widget_transformer
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
	public function beforeSendToClient($cname)
	{
		$form_name = self::form_name($cname, $this->id);
		if($this->getElementAttribute($form_name, 'customfields'))
		{
			// Already done?
			return;
		}
		$value =& self::get_array(self::$request->content, $form_name, true);
		$fields = $value['fields'];
		unset($value['fields']);
		$relative_dates = $this->attrs['relative_dates'];

		$this->setElementAttribute($form_name, 'prefix', self::$prefix);
		
		// Fallback, so there's something there...
		if(!is_array($fields))
		{
			error_log("$this has no fields");
			self::$transformation = array(
				'type' => 'label',
				'label' => 'No fields'
			);
			return parent::beforeSendToClient($cname);
		}
		
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
					$options = '';
					if($relative_dates)
					{
						$type = 'select';
						$field['values'] = array('', lang('all'));
						foreach(importexport_helper_functions::$relative_dates as $label => $values)
						{
							$field['values'][$label] = lang($label);
						}
						$this->setElementAttribute($form_name.'['.self::$prefix.$lname.']', 'tags', TRUE);
					}
					else
					{
						$input = self::do_absolute_date($lname, $value, $options, $readonly);
					}
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
						$options['values'] = $this->_get_options_from_file($field['values']['@']);
						unset($field['values']['@']);
					} else {
						$options['values'] = array_diff_key($field['values'], array_flip(ajax_select_widget::$known_options));
					}
					$options = array_merge($options, array_intersect_key($field['values'], array_flip(ajax_select_widget::$known_options)));

					
					break;
				case 'select':
				default:
					if(strpos($field['type'],'select') === 0)
					{
						if (count($field['values']) == 1 && isset($field['values']['@']))
						{
							$field['values'] = $this->_get_options_from_file($field['values']['@']);
						}
						foreach((array)$field['values'] as $key => $val)
						{
							if (substr($val = lang($val),-1) != '*')
							{
								$field['values'][$key] = $val;
							}
						}

						// We don't want the 'All' or 'Select...' if it's there
						unset($field['values']['']);
						$this->setElementAttribute($form_name.'['.self::$prefix.$lname.']', 'empty_label', '');
						$this->setElementAttribute($form_name.'['.self::$prefix.$lname.']', 'tags', TRUE);
						$this->setElementAttribute($form_name.'['.self::$prefix.$lname.']', 'multiple', TRUE);
					}
					else
					{
error_log('Trying to filter with unsupported field type: ' . $field['type']);
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
		$this->setElementAttribute($form_name, 'customfields', $fields);
		$this->setElementAttribute($form_name, 'fields',array_fill_keys(array_keys($fields), true));
		
		parent::beforeSendToClient($cname);

		return false;
	}

	/**
	 * Create widgets to select a relative date range
	 *
	 * @param $lname Field name
	 * @param $options
	 * @param $readonly
	 *
	 * @return Array of widget info
	 */
	protected static function do_relative_date($lname, Array &$value, $options, $readonly)
	{
		// Maybe this could be moved to date widget
		$input = boetemplate::empty_cell('select',$lname, array(
			'readonly'	=> $readonly,
			'no_lang'	=> true,
			'options'	=> $options,
			'sel_options'	=> array('' => lang('all'))
		));
		foreach(importexport_helper_functions::$relative_dates as $label => $values)
		{
			$input['sel_options'][$label] = lang($label);
		}
		
		return $input;
	}

	/**
	 * Create widgets to select an absolute date range
	 *
	 * @param $lname Field name
	 * @param $options
	 * @param $readonly
	 *
	 * @return Array of widget info
	 */
	protected static function do_absolute_date($lname, Array &$value, $options, $readonly)
	{
		$input = boetemplate::empty_cell('hbox',$lname);
		
		$type = 'date';
		$from = boetemplate::empty_cell($type, $lname.'[from]',array(
			'readonly'      => $readonly,
			'no_lang'       => True,
			'size'          => $options
		));
		
		$to = boetemplate::empty_cell($type, $lname.'[to]', array(
			'readonly'      => $readonly,
			'no_lang'       => True,
			'size'          => $options
		));
		boetemplate::add_child($input, $from);
		boetemplate::add_child($input,boetemplate::empty_cell('label','',array(
			'label' => lang('to'),
			'no_lang' => true
		)));
		boetemplate::add_child($input, $to);
		return $input;
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
etemplate_widget::registerWidget('importexport_widget_filter', array('filter'));
