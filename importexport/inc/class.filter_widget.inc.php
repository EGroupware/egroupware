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
class filter_widget extends customfields_widget
{

	public $prefix = '';
	public $human_name = array(
		'filter' => 'Import|Export filter'
	);

	public function __construct($ui, $appname = null)
	{
		$this->advanced_search = true;
		parent::__construct($ui, $appname);
	}

	/**
         * pre-processing of the extension
         *
         * This function is called before the extension gets rendered
         *
         * @param string $form_name form-name of the control
         * @param mixed &$value value / existing content, can be modified
         * @param array &$cell array with the widget, can be modified for ui-independent widgets
         * @param array &$readonlys names of widgets as key, to be made readonly
         * @param mixed &$extension_data data the extension can store persisten between pre- and post-process
         * @param etemplate &$tmpl reference to the template we belong too
         * @return boolean true if extra label is allowed, false otherwise
         */
        public function pre_process($form_name,&$value,&$cell,&$readonlys,&$extension_data,$tmpl)
        {
		$fields = $value['fields'];
		unset($value['fields']);

		list($relative_dates) = explode(',',$cell['size']);
		if($cell['relative_dates']) $relative_dates = true;

		// Fallback, so there's something there...
		if(!is_array($fields))
                {
                        $cell['type'] = 'label';
			$cell['label'] = 'No fields';
                        return True;
                }


		// making the cell an empty grid
                $cell['type'] = 'grid';
                $cell['data'] = array(array());
                $cell['rows'] = $cell['cols'] = 0;
                $cell['size'] = '';

                $n = 1;
                foreach($fields as $lname => $field)
                {
			$new_row = null; boetemplate::add_child($cell,$new_row);
			$row_class = 'row';
			boetemplate::add_child($cell,$label =& boetemplate::empty_cell('label','',array(
				'label' => $field['label'],
				'no_lang' => substr(lang($field['label']),-1) == '*' ? 2 : 0,
				'span' => $field['type'] === 'label' ? '2' : '',
			)));

			switch($field['type'])
			{
				case 'date':
				case 'date-time':
					// Need a range here
					$options = '';
					if($relative_dates)
					{
						$input = self::do_relative_date($lname, $value, $options, $readonly);
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

					$input = boetemplate::empty_cell('ajax_select', $lname, array(
						'readonly'      => $readonly,
						'no_lang'       => True,
						'size'          => $options
					));
					break;

				case 'link-entry':
					$input =& boetemplate::empty_cell('link-entry',$this->prefix.$lname,array(
						'size' => $field['type'] == 'link-entry' ? '' : $field['type'],
					));
					// register post-processing of link widget to get eg. needed/required validation
					etemplate_old::$request->set_to_process(etemplate_old::form_name($form_name,$this->prefix.$lname), 'ext-link');
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

						$input =& boetemplate::empty_cell($field['type'],$lname,array(
							'sel_options' => $field['values'],
							'size'        => $field['rows'],
							'enhance'     => true,
							'no_lang'     => True,
						));
					}
					elseif (in_array($field['type'], array_keys(egw_link::app_list())))
					{
						// Link entry to a specific app
						$input =& boetemplate::empty_cell('link-entry',$lname,array(
							'size' => $field['type'] == 'link-entry' ? '' : $field['type'],
						));
						// register post-processing of link widget to get eg. needed/required validation
						etemplate_old::$request->set_to_process(etemplate_old::form_name($form_name,$lname), 'ext-link');
		
					} else {
error_log('Trying to filter with unsupported field type: ' . $field['type']);
						$input =& boetemplate::empty_cell($field['type'],$lname,array(
							'sel_options' => $field['values'],
							'size'        => $field['rows'],
							'no_lang'     => True,
						));
					}
	
			}
			$cell['data'][0]['c'.$n++] = $row_class.',top';

			if (!is_null($input))
			{
				if ($readonly) $input['readonly'] = true;

				$input['needed'] = $cell['needed'] || $field['needed'];

				if (!empty($field['help']) && $row_class != 'th')
				{
					$input['help'] = $field['help'];
					$input['no_lang'] = substr(lang($help),-1) == '*' ? 2 : 0;
				}
				boetemplate::add_child($cell,$input);
				unset($input);
			}
			unset($label);
		}
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
}
