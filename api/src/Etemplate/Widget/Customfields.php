<?php
/**
 * EGroupware - eTemplate custom fields widget
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

use EGroupware\Api;

/**
 * Widgets for custom fields and listing custom fields
 *
 * @todo: fix the two undefined variables ($value and $field)
 */
class Customfields extends Transformer
{

	/**
	 * Allowed types of customfields
	 *
	 * The additionally allowed app-names from the link-class, will be added by the edit-method only,
	 * as the link-class has to be called, which can NOT be instantiated by the constructor, as
	 * we get a loop in the instantiation.
	 *
	 * @var array
	 */
	protected static $cf_types = array(
		'text'     => 'Text',
		'passwd'   => 'Password',
		'int'      => 'Integer',
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

	protected $legacy_options = 'type_filter,private,fields';

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
		$this->attrs['prefix'] = self::$prefix;
		parent::__construct($xml);
	}

	/**
	 * Fill type options in self::$request->sel_options to be used on the client
	 *
	 * @param string $cname
	 * @param array $expand values for keys 'c', 'row', 'c_', 'row_', 'cont'
	 */
	public function beforeSendToClient($cname, array $expand=null)
	{
		// No name, no way to get parameters client-side.
		if(!$this->id) $this->id = self::GLOBAL_ID;

		$form_name = self::form_name($cname, $this->id, $expand);

		// Store properties at top level, so all customfield widgets can share
		if (!empty($this->attrs['app']))
		{
			$app = $this->attrs['app'];
		}
		else
		{
			$app =& $this->getElementAttribute(self::GLOBAL_VALS, 'app');
			if($this->getElementAttribute($form_name, 'app'))
			{
				$app =& $this->getElementAttribute($form_name, 'app');
			}
			else
			{
				// Checking creates it even if it wasn't there
				unset(self::$request->modifications[$form_name]['app']);
			}
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

		if(!$app && !$customfields)
		{
			$app =& self::setElementAttribute(self::GLOBAL_VALS, 'app', $GLOBALS['egw_info']['flags']['currentapp']);
			if ($this->attrs['sub-app']) $app .= '-'.$this->attrs['sub-app'];
			$customfields =& self::setElementAttribute(self::GLOBAL_VALS, 'customfields', Api\Storage\Customfields::get($app));
		}

		// if we are in the etemplate editor or the app has no cf's, load the cf's from the app the tpl belongs too
		if ($app && $app != 'stylite' && $app != $GLOBALS['egw_info']['flags']['currentapp'] && !isset($customfields) &&
			($GLOBALS['egw_info']['flags']['currentapp'] == 'etemplate' || !$this->attrs['customfields']) || !isset($customfields))
		{
			// app changed
			$customfields = Api\Storage\Customfields::get($app);
		}
		if (!empty($this->attrs['customfields']))
		{
			$customfields = $this->attrs['customfields'];
		}
		// Filter fields
		$field_filters = $negate_fields = [];
		if (!empty($this->attrs['fields']))
		{
			$fields_name = is_array($this->attrs['fields']) ? $this->attrs['fields'] :
				explode(',', $this->attrs['fields']);
			foreach($fields_name as &$f)
			{
				if ($f[0] == "!")
				{
					$f= substr($f,1);
					$negate_fields[]= $f;
				}
				$field_filters []= $f;
			}
		}

		$fields = $customfields;

		$use_private = self::expand_name($this->attrs['private'] ?? null,0,0,'','',self::$cont);
		$this->attrs['type_filter'] = self::expand_name($this->attrs['type_filter'] ?? null,0,0,'','',self::$cont);

		foreach((array)$fields as $key => $field)
		{
			// remove private or non-private cf's, if only one kind should be displayed
			if ((string)$use_private !== '' && (boolean)$field['private'] != (boolean)$use_private)
			{
				unset($fields[$key]);
			}

			// Remove filtered fields
			if (!empty($field_filters) && in_array($key, $negate_fields) && in_array($key, $field_filters))
			{
				unset($fields[$key]);
			}

			// Rmove fields for none private cutomfields when name refers to a single custom field
			$matches = null;
			if (($pos=strpos($form_name,$this->attrs['prefix'])) !== false &&
			preg_match($preg = '/'.$this->attrs['prefix'].'([^\]]+)/',$form_name,$matches) && !isset($fields[$name=$matches[1]]))
			{
				unset($fields[$key]);
			}
		}
		// check if name refers to a single custom field --> show only that
		$matches = null;
		if (($pos=strpos($form_name,$this->attrs['prefix'])) !== false && // allow the prefixed name to be an array index too
			preg_match($preg = '/'.$this->attrs['prefix'].'([^\]]+)/',$form_name,$matches) && isset($fields[$name=$matches[1]]))
		{
			$fields = array($name => $fields[$name]);
			$value = array($this->attrs['prefix'].$name => $value);
			$form_name = $this->attrs['prefix'].$name;
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
				$link_types = array_intersect_key(Api\Link::app_list('query'), Api\Link::app_list('title'));
				// Explicitly add in filemanager, which does not support query or title
				$link_types['filemanager'] = lang('filemanager');

				ksort($link_types);
				foreach($link_types as $lname => $label)
				{
					$sel_options[$lname] = '- '.$label;
				}
				self::$transformation['type'][$type]['sel_options'] = $sel_options;
				self::$transformation['type'][$type]['no_lang'] = true;
				return parent::beforeSendToClient($cname, $expand);
			case 'customfields-list':
				foreach(array_reverse($fields) as $lname => $field)
				{
					if (!empty($this->attrs['type_filter']) && !empty($field['type2']) &&
						strpos(','.$field['type2'].',',','.$field['type2'].',') === false) continue;    // not for our content type//
					if (isset($value[$this->attrs['prefix'].$lname]) && $value[$this->attrs['prefix'].$lname] !== '') //break;
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
		// need to encode values/select-options to keep their order
		foreach($customfields as &$data)
		{
			if (!empty($data['values']))
			{
				Select::fix_encoded_options($data['values']);
			}
		}
		if($fields != $customfields)
		{
			// This widget has different settings from global
			self::setElementAttribute($form_name, 'customfields', $fields);
			self::setElementAttribute($form_name, 'fields', array_merge(
				array_fill_keys(array_keys($customfields), false),
				array_fill_keys(array_keys($fields), true)
			));
		}
		parent::beforeSendToClient($cname, $expand);

		// Re-format date custom fields from Y-m-d
		$field_settings =& self::get_array(self::$request->modifications, "{$this->id}[customfields]",true);
		if (true) $field_settings = array();
		$link_types = Api\Link::app_list();
		foreach($fields as $fname => $field)
		{
			// Run beforeSendToClient for each field
			$widget = $this->_widget($fname, $field);
			if(method_exists($widget, 'beforeSendToClient'))
			{
				$widget->beforeSendToClient($this->id == self::GLOBAL_ID ? '' : $this->id, $expand);
			}
		}
	}

	/**
	 * Instanciate (server-side) widget used to implement custom-field, to run its beforeSendToClient or validate method
	 *
	 * @param string $fname custom field name
	 * @param array $field custom field data
	 * @return Etemplate\Widget
	 */
	protected function _widget($fname, array $field)
	{
		static $link_types = null;
		if (!isset($link_types)) $link_types = Api\Link::app_list ();

		$type = $field['type'];
		// Link-tos needs to change from appname to link-to
		if (!empty($link_types[$field['type']]))
		{
			if($type == 'filemanager')
			{
				$type = 'vfs-upload';
			}
			else
			{
				$type = 'link-to';
			}
		}
		$xml = '<' . $type . ' type="' . $type . '" id="' . self::$prefix . $fname . '" required="' . $field['needed'] . '"/>';
		$widget = self::factory($type, $xml, self::$prefix . $fname);
		$widget->id = self::$prefix.$fname;
		$widget->attrs['type'] = $type;
		$widget->set_attrs($xml);

		// some type-specific (default) attributes
		switch($type)
		{
			case 'date':
			case 'date-time':
				if (!empty($field['values']['format']))
				{
					$widget->attrs['data_format'] = $field['values']['format'];
				}
				else
				{
					$widget->attrs['data_format'] = $type == 'date' ? 'Y-m-d' : 'Y-m-d H:i:s';
				}
				if (isset($field['values']['min'])) $widget->attrs['min'] = $field['values']['min'];
				if (isset($field['values']['max'])) $widget->attrs['min'] = $field['values']['max'];
				break;

			case 'vfs-upload':
				$widget->attrs['path'] = $field['app'] . ':' .
					self::expand_name('$cont['.Api\Link::get_registry($field['app'],'view_id').']',0,0,0,0,self::$request->content).
					':'.$field['label'];
				break;

			case 'link-to':
				$widget->attrs['only_app'] = $field['type'];
				break;

			case 'text':
				break;
			case 'passwd':
				// Defaults for a customfield are different than a regular password field
				$defaults = Array(
					'viewable' => true,
					'plaintext' => false,
					'suggest' => 16
				);
				foreach($defaults as $f => $d_value)
				{
					$widget->attrs[$f] = is_array($field['values']) && array_key_exists($f, $field['values']) ? $field['values'][$f] : $d_value;
				}
				break;

			default:
				if (substr($type, 0, 7) !== 'select-' && $type != 'ajax_select') break;
				// fall-through for all select-* widgets
			case 'select':
				$widget->attrs['multiple'] = $field['rows'] > 1;
				// fall through
			case 'radio':
				if (!empty($field['values']) && count($field['values']) == 1 && isset($field['values']['@']))
				{
					$field['values'] = Api\Storage\Customfields::get_options_from_file($field['values']['@']);
				}
				// keep extra values set by app code, eg. addressbook advanced search
				if (!empty(self::$request->sel_options[self::$prefix.$fname]) && is_array(self::$request->sel_options[self::$prefix.$fname]))
				{
					self::$request->sel_options[self::$prefix.$fname] += (array)$field['values'];
				}
				else
				{
					self::$request->sel_options[self::$prefix.$fname] = $field['values'];
				}
				//error_log(__METHOD__."('$fname', ".array2string($field).") request->sel_options['".self::$prefix.$fname."']=".array2string(self::$request->sel_options[$this->id]));
				// to keep order of numeric values, we have to explicit run fix_encoded_options, as sel_options are already encoded
				$options = self::$request->sel_options[self::$prefix.$fname];
				if (is_array($options))
				{
					Select::fix_encoded_options($options);
					self::$request->sel_options[self::$prefix . $fname] = $options;
				}
				break;
		}
		return $widget;
	}

	/**
	 * Perform any needed data manipulation on each row
	 * before sending it to client.
	 *
	 * This is used by Nextmatch on each row to do any needed
	 * adjustments.  Used here to make sure sub-widgets get their set_row_value method called
	 *
	 * @param string $cname
	 * @param array $expand
	 * @param array $data Row data
	 */
	public function set_row_value($cname, array $expand, array &$data)
	{
		$form_name = self::form_name($cname, $this->id, $expand);
		$value =& $this->get_array($data, $form_name, true);
		$customfields = Api\Storage\Customfields::get($this->attrs['app'] ?? $GLOBALS['egw_info']['flags']['currentapp']);

		foreach($customfields as $field_name => $field)
		{
			if(array_key_exists(self::$prefix . $field_name, $value))
			{
				$widget = $this->_widget($field_name, $field);
				if(method_exists($widget, 'set_row_value'))
				{
					$widget->set_row_value('', $expand, $data[$form_name]);
				}
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

		$all_readonly = $this->is_readonly($cname, $form_name);
		$value_in = self::get_array($content, $form_name, false, true);
		$use_private = self::expand_name($this->attrs['private'] ?? null, 0, 0, '', '', self::$cont);
		// if we have no id / use self::GLOBAL_ID, we have to set $value_in in global namespace for regular widgets validation to find
		if (!$this->id) $content = array_merge($content, (array)$value_in);
		//error_log(__METHOD__."($cname, ...) form_name=$form_name, private={$this->attrs['private']}, value_in=".array2string($value_in));
		if($this->getElementAttribute($form_name, 'customfields'))
		{
			$customfields =& $this->getElementAttribute($form_name, 'customfields');
		}
		else
		{
			$customfields =& $this->getElementAttribute(self::GLOBAL_VALS, 'customfields');
		}
		if(is_array($value_in))
		{
			foreach(array_keys($value_in) as $field)
			{
				$field_settings = $customfields[$fname = substr($field, strlen($this->attrs['prefix']))];

				if((string)$use_private !== '' &&    // are only (non-)private fields requested
					(boolean)$field_settings['private'] != ($use_private != '0'))
				{
					continue;
				}

				// check if single field is set readonly, used in apps as it was only way to make cfs readonly in old eT
				// single fields set to false in $readonly overwrite a global __ALL__
				$cf_readonly = $this->is_readonly($form_name != self::GLOBAL_ID ? $form_name : $cname, $field);
				if($cf_readonly || $all_readonly && $cf_readonly !== false)
				{
					continue;
				}
				// run validation method of widget implementing this custom field
				$widget = $this->_widget($fname, $field_settings);
				// widget has no validate method, eg. is only displaying stuff --> nothing to validate
				if (!method_exists($widget, 'validate')) continue;
				$widget->validate($form_name != self::GLOBAL_ID ? $form_name : $cname, $expand, $content, $validated);
				$field_name = $this->id[0] == self::$prefix && $customfields[substr($this->id,strlen($this->attrs['prefix']))] ? $this->id : self::form_name($form_name != self::GLOBAL_ID ? $form_name : $cname, $field);
				$valid =& self::get_array($validated, $field_name, true);

				// Arrays are not valid, but leave filemanager alone, we'll catch it
				// when saving.  This allows files for new entries.
				if (is_array($valid) && $field_settings['type'] !== 'filemanager') $valid = implode(',', $valid);

				// NULL is valid for most fields, but not custom fields due to backend handling
				// See so_sql_cf->save()
				if (is_null($valid)) $valid = false;
				//error_log(__METHOD__."() $field_name: ".array2string($value).' --> '.array2string($valid));
			}
		}
		elseif ($this->type == 'customfields-types')
		{
			// Transformation doesn't handle validation
			$valid =& self::get_array($validated, $this->id ? $form_name : $field, true);
			if (true) $valid = $value_in;
			//error_log(__METHOD__."() $form_name $field: ".array2string($value).' --> '.array2string($value));
		}
	}
}