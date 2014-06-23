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
		} else
		{
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
			$customfields =& $this->setElementAttribute(self::GLOBAL_VALS, 'customfields', egw_customfields::get($app));
		}

		// if we are in the etemplate editor or the app has no cf's, load the cf's from the app the tpl belongs too
		if ($app && $app != 'stylite' && $app != $GLOBALS['egw_info']['flags']['currentapp'] && (
			$GLOBALS['egw_info']['flags']['currentapp'] == 'etemplate' || !$this->attrs['customfields'] ||
			etemplate::$hooked
		))
		{
			// app changed
			$customfields =& egw_customfields::get($app);
		}

		// Filter fields
		if($this->attrs['field-names'])
		{
			if($this->attrs['field-names'][0] == '!')
			{
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
			if ((string)$use_private !== '' && (boolean)$field['private'] != (boolean)$use_private)
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
		$matches = null;
		if (($pos=strpos($form_name,self::$prefix)) !== false && // allow the prefixed name to be an array index too
			preg_match("/$this->prefix([^\]]+)/",$form_name,$matches) && isset($fields[$name=$matches[1]]))
		{
			$fields = array($name => $fields[$name]);
			$value = array($this->prefix.$name => $value);
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
				foreach($link_types as $lname => $label)
				{
					$sel_options[$lname] = '- '.$label;
				}
				self::$transformation['type'][$type]['sel_options'] = $sel_options;
				self::$transformation['type'][$type]['no_lang'] = true;
				return parent::beforeSendToClient($cname);
			case 'customfields-list':
				foreach(array_reverse($fields) as $lname => $field)
				{
					if (!empty($this->attrs['sub-type']) && !empty($field['type2']) &&
						strpos(','.$field['type2'].',',','.$field['type2'].',') === false) continue;    // not for our content type//
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
		if (true) $field_settings = array();
		$link_types = egw_link::app_list();
		foreach($fields as $fname => $field)
		{
			// Run beforeSendToClient for each field
			$widget = $this->_widget($fname, $field);
			if(method_exists($widget, 'beforeSendToClient'))
			{
				$widget->beforeSendToClient($this->id == self::GLOBAL_ID ? '' : $this->id);
			}
		}
	}

	/**
	 * Instanciate (server-side) widget used to implement custom-field, to run its beforeSendToClient or validate method
	 *
	 * @param string $fname custom field name
	 * @param array $field custom field data
	 * @return etemplate_widget
	 */
	protected function _widget($fname, array $field)
	{
		static $link_types = null;
		if (!isset($link_types)) $link_types = egw_link::app_list ();

		$type = $field['type'];
		// Link-tos needs to change from appname to link-to
		if($link_types[$field['type']])
		{
			$type = 'link-to';
		}
		$widget = self::factory($type, '<'.$type.' type="'.$type.'" id="'.self::$prefix.$fname.'"/>', self::$prefix.$fname);
		$widget->id = self::$prefix.$fname;
		$widget->attrs['type'] = $type;

		// some type-specific (default) attributes
		switch($type)
		{
			case 'date':
			case 'date-time':
				$widget->attrs['dataformat'] = $type == 'date' ? 'Y-m-d' : 'Y-m-d H:i:s';
				break;

			case 'link-to':
				$widget->attrs['only_app'] = $field['type'];
				break;

			case 'select':
				$this->attrs['multiple'] = $field['rows'] > 1;
				// fall through
			case 'radio':
				if (count($field['values']) == 1 && isset($field['values']['@']))
				{
					$field['values'] = self::_get_options_from_file($field['values']['@']);
				}
				self::$request->sel_options[self::$prefix.$fname] = $field['values'];
				//error_log(__METHOD__."('$fname', ".array2string($field).") request->sel_options['".self::$prefix.$fname."']=".array2string(self::$request->sel_options[$this->id]));
				break;
		}
		return $widget;
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
			//error_log(__METHOD__."($cname, ...) form_name=$form_name, use-private={$this->attrs['use-private']}, value_in=".array2string($value_in));
			$customfields =& $this->getElementAttribute(self::GLOBAL_VALS, 'customfields');
			if(is_array($value_in))
			{
				foreach($value_in as $field => $value)
				{
					$field_settings = $customfields[$fname=substr($field,1)];

					if ((string)$this->attrs['use-private'] !== '' &&	// are only (non-)private fields requested
						(boolean)$field_settings['private'] != ($this->attrs['use-private'] != '0'))
					{
						continue;
					}

					// check if single field is set readonly, used in apps as it was only way to make cfs readonly in old eT
					if ($this->is_readonly($form_name != self::GLOBAL_ID ? $form_name : $cname, $field))
					{
						continue;
					}
					// run validation method of widget implementing this custom field
					$widget = $this->_widget($fname, $field_settings);
					$widget->validate($form_name != self::GLOBAL_ID ? $form_name : $cname, $expand, $content, $validated);
					if ($field_settings['needed'] && (is_array($value) ? !$value : (string)$value === ''))
					{
						self::set_validation_error($field,lang('Field must not be empty !!!'),'');
					}
					$field_name = self::form_name($form_name != self::GLOBAL_ID ? $form_name : $cname, $field);
					$valid =& self::get_array($validated, $field_name, true);

					if (is_array($valid)) $valid = implode(',', $valid);
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

	/**
	 * Read the options of a 'select' or 'radio' custom field from a file
	 *
	 * For security reasons that file has to be relative to the eGW root
	 * (to not use that feature to explore arbitrary files on the server)
	 * and it has to be a php file setting one variable called options,
	 * (to not display it to anonymously by the webserver).
	 * The $options var has to be an array with value => label pairs, eg:
	 *
	 * <?php
	 * $options = array(
	 *      'a' => 'Option A',
	 *      'b' => 'Option B',
	 *      'c' => 'Option C',
	 * );
	 *
	 * @param string $file file name inside the eGW server root, either relative to it or absolute
	 * @return array in case of an error we return a single option with the message
	 */
	public static function _get_options_from_file($file)
	{
		if (!($path = realpath($file{0} == '/' ? $file : EGW_SERVER_ROOT.'/'.$file)) ||	// file does not exist
			substr($path,0,strlen(EGW_SERVER_ROOT)+1) != EGW_SERVER_ROOT.'/' ||	// we are NOT inside the eGW root
			basename($path,'.php').'.php' != basename($path) ||	// extension is NOT .php
			basename($path) == 'header.inc.php')	// dont allow to include our header again
		{
			return array(lang("'%1' is no php file in the eGW server root (%2)!".': '.$path,$file,EGW_SERVER_ROOT));
		}
		$options = array();
		include($path);

		return $options;
	}
}
