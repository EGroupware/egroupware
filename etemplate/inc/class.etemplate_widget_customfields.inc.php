<?php
/**
 * EGroupware - eTemplate custom fields widget
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
 * Widgets for custom fields and listing custom fields
 *
 */
class etemplate_widget_customfields extends etemplate_widget_transformer
{

	/**
	 * Allowd types of customfields
	 *
	 * The additionally allowed app-names from the link-class, will be add by the edit-method only,
	 * as the link-class has to be called, which can NOT be instanciated by the constructor, as
	 * we get a loop in the instanciation.
	 *
	 * @var array
	 */
	protected static $cf_types = array(
		'text'     => 'Text',
		'float'    => 'Float',
		'label'    => 'Label',
		'select'   => 'Selectbox',
		'ajax_select' => 'Search',
		'radio'    => 'Radiobutton',
		'checkbox' => 'Checkbox',
		'date'     => 'Date',
		'date-time'=> 'Date+Time',
		'select-account' => 'Select account',
		'button'   => 'Button',         // button to execute javascript
		'url'      => 'Url',
		'url-email'=> 'EMail',
		'url-phone'=> 'Phone number',
		'htmlarea' => 'Formatted Text (HTML)',
		'link-entry' => 'Select entry',         // should be last type, as the individual apps get added behind
	);

	/**
	 * @var $prefix string Prefix for every custiomfield name returned in $content (# for general (admin) customfields)
	 */
	protected static $prefix = '#';

	// Make settings available globally
	const GLOBAL_VALS = '~custom_fields~';

	// Used if there's no ID provided
	const GLOBAL_ID = 'custom_fields';

	protected $legacy_options = 'sub-type,use-private,field-names';

	protected static $transformation = array(
		'type' => array(
			'customfields-types' => array(
				'type'	=>	'select',
				'sel_options'	=> array()
			),
			'customfields-list' => array(
				'readonly'	=> true
			)
		)
	);

	public function __construct($xml)
	{
		parent::__construct($xml);
	}

	/**
	 * Fill type options in self::$request->sel_options to be used on the client
	 *
	 * @param string $cname
	 */
	public function beforeSendToClient($cname)
	{
		// No name, no way to get parameters client-side.
		if(!$this->id) $this->id = self::GLOBAL_ID;

		$form_name = self::form_name($cname, $this->id);

		// Store properties at top level, so all customfield widgets can share
		$app =& $this->getElementAttribute(self::GLOBAL_VALS, 'app');
		if($this->getElementAttribute($form_name, 'app'))
		{
			$app =& $this->getElementAttribute($form_name, 'app');
		} else {
			// Checking creates it even if it wasn't there
			unset(self::$request->modifications[$form_name]['app']);
		}

		if($this->getElementAttribute($form_name, 'customfields'))
		{
			$customfields =& $this->getElementAttribute($form_name, 'customfields');
		}
		elseif($app)
		{
			// Checking creates it even if it wasn't there
			unset(self::$request->modifications[$form_name]['customfields']);
			$customfields =& $this->getElementAttribute(self::GLOBAL_VALS, 'customfields');
		}

		if(!$app)
		{
			$app =& $this->setElementAttribute(self::GLOBAL_VALS, 'app', $GLOBALS['egw_info']['flags']['currentapp']);
			$customfields =& $this->setElementAttribute(self::GLOBAL_VALS, 'customfields', config::get_customfields($app));
		}

		// if we are in the etemplate editor or the app has no cf's, load the cf's from the app the tpl belongs too
		if ($app && $app != 'stylite' && $app != $GLOBALS['egw_info']['flags']['currentapp'] && (
			$GLOBALS['egw_info']['flags']['currentapp'] == 'etemplate' || !$this->attrs['customfields'] ||
			etemplate::$hooked
		))
		{
			// app changed
			$customfields =& config::get_customfields($app);
		}

		// Filter fields
		if($this->attrs['field-names'])
		{
				if($this->attrs['field-names'][0] == '!') {
						$negate_field_filter = true;
						$this->attrs['field-names'] = substr($this->attrs['field_names'],1);
				}
				$field_filter = explode(',', $this->attrs['field_names']);
		}
		$fields = $customfields;

		$use_private = self::expand_name($this->attrs['use-private'],0,0,'','',self::$cont);
		$this->attrs['sub-type'] = self::expand_name($this->attrs['sub-type'],0,0,'','',self::$cont);

		foreach((array)$fields as $key => $field)
		{
			// remove private or non-private cf's, if only one kind should be displayed
			if ((string)$this->attrs['use-private'] !== '' && (boolean)$field['private'] != (boolean)$use_private)
			{
				unset($fields[$key]);
			}

			// Remove filtered fields
			if($field_filter && (!$negate_field_filter && !in_array($key, $field_filter) ||
				$negate_field_filter && in_array($key, $field_filter)))
			{
				unset($fields[$key]);
			}
		}
		// check if name refers to a single custom field --> show only that
		if (($pos=strpos($form_name,self::$prefix)) !== false && // allow the prefixed name to be an array index too
			preg_match("/$this->prefix([^\]]+)/",$form_name,$matches) && isset($fields[$name=$matches[1]]))
		{
			$fields = array($name => $fields[$name]);
			$value = array($this->prefix.$name => $value);
			$singlefield = true;
			$form_name = substr($form_name,0,-strlen("[$this->prefix$name]"));
		}

		if(!is_array($fields)) $fields = array();
		switch($type = $this->type)
		{
			case 'customfields-types':
				foreach(self::$cf_types as $lname => $label)
				{
					$sel_options[$lname] = lang($label);
					$fields_with_vals[]=$lname;
				}
				$link_types = egw_link::app_list();
				ksort($link_types);
				foreach($link_types as $lname => $label) $sel_options[$lname] = '- '.$label;

				self::$transformation['type'][$type]['sel_options'] = $sel_options;
				self::$transformation['type'][$type]['no_lang'] = true;
				return parent::beforeSendToClient($cname);
			case 'customfields-list':
				foreach(array_reverse($fields) as $lname => $field)
				{
					if (!empty($this->attrs['sub-type']) && !empty($field['type2']) && strpos(','.$field['type2'].',',','.$type2.',') === false) continue;    // not for our content type//
					if (isset($value[$this->prefix.$lname]) && $value[$this->prefix.$lname] !== '') //break;
					{
						$fields_with_vals[]=$lname;
					}
					//$stop_at_field = $name;
				}
				break;
			default:
				foreach(array_reverse($fields) as $lname => $field)
				{
					$fields_with_vals[]=$lname;
				}
		}
		if($fields != $customfields)
		{
			// This widget has different settings from global
			$this->setElementAttribute($form_name, 'customfields', $fields);
			$this->setElementAttribute($form_name, 'fields', array_merge(
				array_fill_keys(array_keys($customfields), false),
				array_fill_keys(array_keys($fields), true)
			));
		}
		parent::beforeSendToClient($cname);

		// Re-format date custom fields from Y-m-d
		$field_settings =& self::get_array(self::$request->modifications, "{$this->id}[customfields]",true);
		$field_settings = array();
		$link_types = egw_link::app_list();
		foreach($fields as $fname => $field)
		{
			if($field['type'] == 'date' && ($d_val = self::$request->content[self::$prefix.$fname]) && !is_numeric($d_val))
			{
				self::$request->content[self::$prefix.$fname] = strtotime($d_val);
			}

			// Run beforeSendToClient for each field
			$type = $field['type'];
			// Link-tos needs to change from appname to link-to
			if($link_types[$field['type']])
			{
				$type = 'link-to';
			}
			$widget = self::factory($type, '<'.$type.' type="'.$type.'" id="'.self::$prefix.$fname.'"/>', self::$prefix.$fname);
			if(method_exists($widget, 'beforeSendToClient'))
			{
				$widget->id = self::$prefix.$fname;
				$widget->attrs['type'] = $type;
				if($type == 'link-to')
				{
					$widget->attrs['only_app'] = $field['type'];
				}
				$widget->beforeSendToClient($this->id == self::GLOBAL_ID ? '':$this->id);
			}
		}
	}

	/**
	 * Validate input
	 *
	 * Following attributes get checked:
	 * - needed: value must NOT be empty
	 * - min, max: int and float widget only
	 * - maxlength: maximum length of string (longer strings get truncated to allowed size)
	 * - preg: perl regular expression incl. delimiters (set by default for int, float and colorpicker)
	 * - int and float get casted to their type
	 *
	 * @param string $cname current namespace
	 * @param array $expand values for keys 'c', 'row', 'c_', 'row_', 'cont'
	 * @param array $content
	 * @param array &$validated=array() validated content
	 */
	public function validate($cname, array $expand, array $content, &$validated=array())
	{
		if ($this->id)
		{
			$form_name = self::form_name($cname, $this->id, $expand);
		}
		else
		{
			$form_name = self::GLOBAL_ID;
		}

		if (!$this->is_readonly($cname, $form_name))
		{
			$value_in = self::get_array($content, $form_name);
			$app =& $this->getElementAttribute(self::GLOBAL_VALS, 'app');
			$customfields =& $this->getElementAttribute(self::GLOBAL_VALS, 'customfields');
			if(is_array($value_in))
			{
				foreach($value_in as $field => $value)
				{
					$field_settings = $customfields[substr($field,1)];
					if ((string)$value === '' && $field_settings['needed'])
					{
						self::set_validation_error($form_name,lang('Field must not be empty !!!'),'');
					}
					$valid =& self::get_array($validated, $this->id ? $form_name : $field, true);

					$valid = is_array($value) ? implode(',',$value) : $value;
					//error_log(__METHOD__."() $form_name $field: ".array2string($value).' --> '.array2string($value));
				}
			} elseif ($this->type == 'customfields-types') {
				// Transformation doesn't handle validation
				$valid =& self::get_array($validated, $this->id ? $form_name : $field, true);
				$valid = $value_in;
				//error_log(__METHOD__."() $form_name $field: ".array2string($value).' --> '.array2string($value));
			}
		}
	}
}
