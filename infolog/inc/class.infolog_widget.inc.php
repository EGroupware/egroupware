<?php
/**
 * EGroupware  eTemplate extension - InfoLog widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage extensions
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 * @version $Id$
 */

/**
 * eTemplate extension: InfoLog widget
 *
 * This widget can be used to display data from an InfoLog specified by it's id
 *
 * The infolog-value widget takes 3 comma-separated arguments (beside the name) in the options/size field:
 * 1) name of the field (as provided by the infolog-fields widget)
 * 2) an optional compare value: if given the selected field is compared with its value and an X is printed on equality, nothing otherwise
 * 3) colon (:) separted list of alternative fields: the first non-empty one is used if the selected value is empty
 * There's a special field "sum" in 1), which sums up all fields given in alternatives.
 */
class infolog_widget extends etemplate_widget_entry
{
	/**
	 * exported methods of this class
	 *
	 * @var array $public_functions
	 */
	var $public_functions = array(
		'pre_process' => True,
	);
	/**
	 * availible extensions and there names for the editor
	 *
	 * @var string/array $human_name
	 */
	var $human_name = array(
		'infolog-value'  => 'InfoLog',
		'infolog-fields' => 'InfoLog fields',
	);
	/**
	 * Instance of the infolog_bo class
	 *
	 * @var infolog_bo
	 */
	var $infolog;
	/**
	 * Cached infolog
	 *
	 * @var array
	 */
	var $data;

	/**
	 * Constructor of the extension
	 *
	 */
	function __construct($xml)
	{
		parent::__construct($xml);
		$this->infolog = new infolog_bo();
	}

	public function get_entry($value, array $attrs)
	{
		// Already done
		if (is_array($value) && !(array_key_exists('app',$value) && array_key_exists('id', $value))) return $value;

		// Link entry, already in array format
		if(is_array($value) && array_key_exists('app', $value) && array_key_exists('id', $value)) $value = $value['id'];

		// Link entry, in string format
		if (substr($value,0,8) == 'infolog:') $value = substr($value,8);
		if($value)
		{
			return $this->infolog->read($value);
		}
		return array();
	}
	/**
	 * pre-processing of the extension
	 *
	 * This function is called before the extension gets rendered
	 *
	 * @param string $name form-name of the control
	 * @param mixed &$value value / existing content, can be modified
	 * @param array &$cell array with the widget, can be modified for ui-independent widgets
	 * @param array &$readonlys names of widgets as key, to be made readonly
	 * @param mixed &$extension_data data the extension can store persisten between pre- and post-process
	 * @param etemplate &$tmpl reference to the template we belong too
	 * @return boolean true if extra label is allowed, false otherwise
	 */
	function pre_process($name,&$value,&$cell,&$readonlys,&$extension_data,&$tmpl)
	{
		switch($cell['type'])
		{
			case 'infolog-fields':
				translation::add_app('addressbook');
				$cell['sel_options'] = $this->_get_fields();
				$cell['type'] = 'select';
				$cell['no_lang'] = 1;
				break;

			case 'infolog-value':
			default:
				if (substr($value,0,8) == 'infolog:') $value = substr($value,8);	// link-entry syntax
				if (!$value || !$cell['size'] || (!is_array($this->data) || $this->data['info_id'] != $value) &&
					!($this->data = $this->infolog->read($value)))
				{
					$cell = $tmpl->empty_cell();
					$value = '';
					break;
				}
				list($type,$compare,$alternatives,$contactfield,$regex,$replace) = explode(',',$cell['size'],6);
				$value = $this->data[$type];
				$cell['size'] = '';
				$cell['no_lang'] = 1;
				$cell['readonly'] = true;

				switch($type)
				{
					case '':	// Sum of the alternatives, field-name can be prefixed with a minus to substract it's value
						$cell['type'] = 'float';
						$cell['size'] = ',,,%0.2lf';
						$value = 0.0;
						foreach(explode(':',$alternatives) as $name)
						{
							if ($name[0] === '-')
							{
								$val = '-'.$this->data[substr($name, 1)];
							}
							else
							{
								$val = $this->data[$name];
							}
							$value += str_replace(array(' ',','), array('','.'), $val);
						}
						$alternatives = '';
						break;

					case 'info_startdate':
					case 'info_datemodified':
					case 'info_datecompleted':
						$cell['type'] = 'date-time';
						break;

					case 'info_enddate':
						$cell['type'] = 'date';
						break;

					case 'info_owner':
					case 'info_responsible':
						$cell['type'] = 'select-owner';
						break;

					case 'info_cat':
						$cell['type'] = 'select-cat';
						break;

					case 'info_access':
						$cell['type'] = 'select-access';
						break;

					case 'info_type':
					case 'info_priority':
					case 'info_confirm':
						$cell['sel_options'] = $this->infolog->enums[$type];
						$cell['type'] = 'select';
						break;

					case 'info_status':
						$cell['sel_options'] = $this->infolog->status[$this->data['info_type']];
						$cell['type'] = 'select';
						break;

					default:
						if ($type{0} == '#')	// custom field --> use field-type itself
						{
							$field = $this->infolog->customfields[substr($type,1)];
							if (($cell['type'] = $field['type']))
							{
								if ($field['type'] == 'select')
								{
									$cell['sel_options'] = $field['values'];
								}
								break;
							}
						}
						$cell['type'] = 'label';
						break;
				}
				if ($alternatives && empty($value))	// use first non-empty alternative if value is empty
				{
					foreach(explode(':',$alternatives) as $name)
					{
						if (($value = $this->data[$name])) break;
					}
				}
				if (!empty($compare))				// compare with value and print a X is equal and nothing otherwise
				{
					$value = $value == $compare ? 'X' : '';
					$cell['type'] = 'label';
				}
				// modify the value with a regular expression
				if (!empty($regex))
				{
					$parts = explode('/',$regex);
					if (strchr(array_pop($parts),'e') === false)	// dont allow e modifier, which would execute arbitrary php code
					{
						$value = preg_replace($regex,$replace,$value);
					}
					$cell['type'] = 'label';
					$cell['size'] = '';
				}
				// use a contact widget to render the value, eg. to fetch contact data from an linked infolog
				if (!empty($contactfield))
				{
					$cell['type'] = 'contact-value';
					$cell['size'] = $contactfield;
				}
				break;
		}
		$cell['id'] = ($cell['id'] ? $cell['id'] : $cell['name'])."[$type]";

		return True;	// extra label ok
	}

	function _get_fields()
	{
		static $fields;

		if (!is_null($fields)) return $fields;

		$fields = array(
			'' => lang('Sum'),
			'info_type' => lang('Type'),
			'info_subject' => lang('Subject'),
			'info_des' => lang('Description'),
			'info_cat' => lang('Category'),
			'info_from' => lang('Contact'),
			'info_addr' => lang('Phone/Email'),
			'info_responsible' => lang('Responsible'),
			'info_startdate' => lang('Startdate'),
			'info_enddate' => lang('Enddate'),
			'info_status' => lang('Status'),
			'info_priority' => lang('Priority'),
			'info_location' => lang('Location'),
			'info_percent' => lang('Completed'),
			'info_datecompleted' => lang('Date completed'),
			// meta data
			// PM fields
			'info_planned_time' => lang('planned time'),
			'info_used_time' => lang('used time'),
			'pl_id' => lang('Pricelist'),
			'info_price' => lang('Price'),
			// other
			'info_owner' => lang('Owner'),
			'info_access' => lang('Access'),
			'info_id' => lang('Id#'),
			'info_link_id' => lang('primary link'),
			'info_modifier' => lang('Modifierer'),
			'info_datemodified' => lang('Last modified'),
//			'info_id_parent' => lang('Parent'),
//			'info_confirm' => lang('Confirm'),
//			'info_custom_from' => lang('Custom from'),
		);
		foreach(config::get_customfields('infolog') as $name => $data)
		{
			$fields['#'.$name] = lang($data['label']);
		}
		return $fields;
	}
}

// register widgets for etemplate2
etemplate_widget::registerWidget('infolog_widget',array('infolog-value', 'infolog-fields'));