<?php
/**
 * EGroupware - eTemplate serverside select widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage etemplate
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 * @copyright 2002-18 by RalfBecker@outdoor-training.de
 */

namespace EGroupware\Api\Etemplate\Widget;

use EGroupware\Api\Acl;
use EGroupware\Api\Etemplate;
use EGroupware\Api;

// explicitly import old not yet ported classes
use calendar_timezones;

/**
 * eTemplate select widget
 *
 * @todo unavailable cats (eg. private cats of an other user) need to be preserved!
 * @todo fully implement attr[multiple] === "dynamic" to render widget with a button to switch to multiple
 *	as it is used in account_id selection in admin >> mailaccount (app.admin.edit_multiple method client-side)
 */
class Select extends Etemplate\Widget
{
	/**
	 * If the selectbox has this many rows, give it a search box automatically
	 */
	const SEARCH_ROW_LIMIT = PHP_INT_MAX; // Automatic disabled, only explicit

	/**
	 * These types are either set or cached on the client side, so we don't send
	 * their options unless asked via AJAX
	 */
	public static $cached_types = array(
		'select-account',
		'select-app',
		'select-tab',
		'select-bool',
		'select-cat',
		'select-country',
		// DOW needs some server-side pre-processing to unpack the options,
		// so can't be skipped.
		//'select-dow',
		'select-number',
		'select-priority',
		'select-percent',
		'select-year',
		'select-month',
		'select-day',
		'select-hour',
		'select-lang',
		'select-timezone'
	);

	/**
	 * @var array
	 */
	protected static $monthnames = array(
		0  => '',
		1  => 'January',
		2  => 'February',
		3  => 'March',
		4  => 'April',
		5  => 'May',
		6  => 'June',
		7  => 'July',
		8  => 'August',
		9  => 'September',
		10 => 'October',
		11 => 'November',
		12 => 'December'
	);

	/**
	 * Constructor
	 *
	 * @param string|\XMLReader $xml string with xml or XMLReader positioned on the element to construct
	 * @throws Api\Exception\WrongParameter
	 */
	public function __construct($xml = '')
	{
		$this->bool_attr_default += array(
			//'multiple' => false,	// handeled in set_attrs, as we additional allow "dynamic"
			'selected_first'        => true,
			'search'                => false,
			'tags'                  => false,
			'allow_single_deselect' => true,
		);

		if($xml) {
			parent::__construct($xml);
		}
	}

	/**
	 * Parse and set extra attributes from xml in template object
	 *
	 * Reimplemented to parse our differnt attributes
	 *
	 * @param string|\XMLReader $xml
	 * @param boolean $cloned =true true: object does NOT need to be cloned, false: to set attribute, set them in cloned object
	 * @return Template current object or clone, if any attribute was set
	 * @todo Use legacy_attributes instead of leaving it to typeOptions method to parse them
	 */
	public function set_attrs($xml, $cloned=true)
	{
		parent::set_attrs($xml, $cloned);

		if (!isset($this->attrs['multiple']) || $this->attrs['multiple'] !== 'dynamic')
		{
			$this->attrs['multiple'] = !isset($this->attrs['multiple']) ? false :
				!(!$this->attrs['multiple'] || $this->attrs['multiple'] === 'false');
		}

		// set attrs[multiple] from attrs[options], unset options only if it just contains number or rows
		if (isset($this->attrs['options']) && $this->attrs['options'] > 1)
		{
			$this->attrs['multiple'] = (int)$this->attrs['options'];
			if ((string)$this->attrs['multiple'] == $this->attrs['options'])
			{
				unset($this->attrs['options']);
			}
		}
		elseif(isset($this->attrs['rows']) && $this->attrs['rows'] > 1)
		{
			$this->attrs['multiple'] = true;
		}
	}

	const UNAVAILABLE_CAT_POSTFIX = '-unavailable';

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
		$widget_type = $this->attrs['type'] ?? $this->type;
		if (substr($widget_type, 0, 4) === 'et2-')
		{
			$widget_type = substr($widget_type, 4);
		}
		$multiple = $this->attrs['multiple'] || $this->getElementAttribute($form_name, 'multiple') || $this->getElementAttribute($form_name, 'rows') > 1;
		$allowFreeEntries = $this->attrs['allowFreeEntries'] || $this->getElementAttribute($form_name, 'allowFreeEntries');

		$ok = true;
		if (!$this->is_readonly($cname, $form_name))
		{
			$value = $value_in = self::get_array($content, $form_name);

			$allowed2 = self::selOptions($form_name, true);    // true = return array of option-values
			$false = false;
			$type_options = self::typeOptions(
				$this,
				// typeOptions thinks # of rows is the first thing in options
				($this->attrs['rows'] && strpos($this->attrs['options'], $this->attrs['rows']) !== 0 ? $this->attrs['rows'] . ',' . $this->attrs['options'] : $this->attrs['options']),
				$false, $false, $value_in
			);
			$allowed = array_merge($allowed2, array_keys($type_options));

			// add option children's values too, "" is not read, therefore we cast to string
			foreach($this->children as $child)
			{
				if ($child->type == 'option') $allowed[] = (string)$child->attrs['value'];
			}
			// Make sure values are strings so they match, also handling option groups
			$stringified = array();
			array_walk_recursive($allowed, function ($a, $b) use (&$stringified) {
				if($b == 'value') $stringified[] = $a;
				else if (is_int($b)) $stringified[] = strval($a);
			});
			$allowed = $stringified;

			// Add empty as an option, we check for required value later
			$allowed[] = '';

			switch($widget_type)
			{
				case 'select-cat':
					// unavailable cats need to be merged in again
					// Needs to be done before validation or it will fail as unavailable
					$unavailable_name = $form_name . self::UNAVAILABLE_CAT_POSTFIX;
					if(isset(self::$request->preserv[$unavailable_name]))
					{
						$allowed = array_merge($allowed, self::$request->preserv[$unavailable_name]);
						if($this->attrs['multiple'])
						{
							$value = array_unique(array_merge((array)$value, (array)self::$request->preserv[$unavailable_name]));
						}
						elseif(!$value)    // for single cat, we only restore unavailable one, if no other was selected
						{
							$value = self::$request->preserv[$unavailable_name];
						}
					}
					break;
			}

			foreach((array)$value as $val)
			{
				// handle empty-label for all widget types
				if((string)$val === '' && in_array('', $allowed))
				{
					continue;
				}

				// no validation, for allowFreeEntries="true"
				if ($allowFreeEntries)
				{
					continue;
				}

				switch ($widget_type)
				{
					case 'select-account':
						// If in allowed options, skip account check to support app-specific options
						if(count($allowed) > 0 && in_array($val, $allowed)) continue 2;	// +1 for switch

						// validate accounts independent of options know to server
						$account_type = $this->attrs['accountType'] ?? $this->attrs['account_type'] ?? 'accounts';
						$type = $GLOBALS['egw']->accounts->exists($val);
						//error_log(__METHOD__."($cname,...) form_name=$form_name, widget_type=$widget_type, account_type=$account_type, type=$type");
						if (!$type || $type == 1 && in_array($account_type, array('groups', 'owngroups', 'memberships')) ||
							$type == 2 && $account_type == 'users' ||
							in_array($account_type, array('owngroups', 'memberships')) &&
								!in_array($val, $GLOBALS['egw']->accounts->memberships(
									$GLOBALS['egw_info']['user']['account_id'], true))
						)
						{
							self::set_validation_error($form_name, lang("'%1' is NOT allowed ('%2')!", $val,
								!$type?'not found' : ($type == 1 ? 'user' : 'group')),'');
							$value = '';
							break 2;
						}
						break;

					case 'select-timezone':
						if (!calendar_timezones::tz2id($val))
						{
							self::set_validation_error($form_name, lang("'%1' is NOT a valid timezone!", $val));
						}
						break;

					case 'select-app':
					case 'select-tab':
						if (!in_array($val, $allowed) &&
							!($widget_type === 'select-tab' && preg_match('/^[a-z0-9]+\-[a-z0-9]+$/i', $val, $matches) && in_array($matches[1], $allowed)))
						{
							self::set_validation_error($form_name, lang("'%1' is NOT a valid app-name ('%2')!", $val, implode("', '",$allowed)),'');
							$value = '';
							break 2;
						}
						break;

					default:
						if(!in_array($val, $allowed))
						{
							self::set_validation_error($form_name,lang("'%1' is NOT allowed ('%2')!", $val, implode("','",$allowed)),'');
							$value = '';
							break 2;
						}
				}
			}
			if ($ok && $value === '' && $this->required)
			{
				self::set_validation_error($form_name,lang('Field must not be empty !!!',$value),'');
			}
			if (!$multiple && is_array($value) && count($value) > 1)
			{
				$value = array_shift($value);
			}
			// some widgets sub-types need some post-processing
			// ToDo: move it together with preprocessing to clientside
			switch ($widget_type)
			{
				case 'select-dow':
					$dow = 0;
					foreach((array)$value as $val)
					{
						$dow |= $val;
					}
					$value = $dow;
					break;

				case 'select-country':
					$legacy_options = $this->attrs['rows'] && strpos($this->attrs['options'], $this->attrs['rows']) !== 0 ?
						$this->attrs['rows'].','.$this->attrs['options'] : $this->attrs['options'];
					list(,$country_use_name) = explode(',', $legacy_options);
					if ($country_use_name && $value)
					{
						$value = Api\Country::get_full_name($value);
					}
					break;


				case 'select-bitwise':
					// Sum up into a single value
					$sum = 0;
					foreach((array) $value as $val)
					{
						$sum += $val;
					}
					$value = $sum;
					break;
			}
			if (isset($value))
			{
				self::set_array($validated, $form_name, $value);
				//error_log(__METHOD__."() $form_name: ".array2string($value_in).' --> '.array2string($value).', allowed='.array2string($allowed));
			}
		}
		else
		{
			//error_log($this . "($form_name) is read-only, skipping validate");
		}
	}

	/**
	 * Fill type options in self::$request->sel_options to be used on the client
	 *
	 * @param string $cname
	 * @param array $expand values for keys 'c', 'row', 'c_', 'row_', 'cont'
	 */
	public function beforeSendToClient($cname, array $expand=null)
	{
		//error_log(__METHOD__."('$cname') this->id=$this->id, this->type=$this->type, this->attrs=".array2string($this->attrs));
		$matches = null;
		if ($cname == '$row')	// happens eg. with custom-fields: $cname='$row', this->id='#something'
		{
			$form_name = $this->id;
		}
		// happens with fields in nm-header: $cname='nm', this->id='${row}[something]' or '{$row}[something]'
		elseif (preg_match('/(\${row}|{\$row})\[([^]]+)\]$/', $this->id, $matches))
		{
			$form_name = $matches[2];
		}
		// happens in auto-repeat grids: $cname='', this->id='something[{$row}]'
		elseif (preg_match('/([^[]+)\[({\$row})\]$/', $this->id, $matches))
		{
			$form_name = $matches[1];
		}
		// happens with autorepeated grids: this->id='some[$row_cont[thing]][else]' --> just use 'else'
		elseif (preg_match('/\$row.*\[([^]]+)\]$/', $this->id, $matches))
		{
			$form_name = $matches[1];
		}
		else
		{
			$form_name = self::form_name($cname, $this->id, $expand);
		}
		if (empty(self::$request->sel_options[$form_name]) || !is_array(self::$request->sel_options[$form_name])) self::$request->sel_options[$form_name] = [];
		$type = $this->attrs['type'] ?? $this->type;
		if ($type != 'select' && $type != 'menupopup')
		{
			// Check selection preference, we may be able to skip reading some data
			$select_pref = $GLOBALS['egw_info']['user']['preferences']['common']['account_selection'];
			if(!empty($this->attrs['type']) && $this->attrs['type'] === 'select-account' &&
				empty($GLOBALS['egw_info']['user']['apps']['admin']) && $select_pref === 'none')
			{
				// Preserve but do not send the value if preference is 'none'
				self::$request->preserv[$this->id] = self::$request->content[$this->id] ?? null;
				unset(self::$request->content[$this->id]);
				$this->attrs['readonly'] = true;
			}
			// Check for type not cached, or type less et2- prefix not a cached type
			if(!in_array($type, self::$cached_types) && !in_array(substr($type, 4), self::$cached_types))
			{
				// adding type specific options here, while keep further options set by app code
				// we need to make sure to run only once for auto-repeated rows, because
				// array_merge used to keep options from app would otherwise add
				// type-specific ones multiple time (and of cause better performance)
				$no_lang = null;
				static $form_names_done = array();
				if(!isset($form_names_done[$form_name]) &&
					($type_options = self::typeOptions($this,
						// typeOptions thinks # of rows is the first thing in options
						(!empty($this->attrs['rows']) && !empty($this->attrs['options']) && strpos($this->attrs['options'], $this->attrs['rows']) !== 0 ? $this->attrs['rows'] . ',' . $this->attrs['options'] : ($this->attrs['options'] ?? null)),
													   $no_lang, $this->attrs['readonly'] ?? false, self::get_array(self::$request->content, $form_name), $form_name
					)))
				{
					self::fix_encoded_options($type_options);

					self::$request->sel_options[$form_name] = array_merge(self::$request->sel_options[$form_name], $type_options);

					// if no_lang was modified, forward modification to the client
					if ($no_lang != ($this->attrs['noLang'] ?? $this->attrs['no_lang']))
					{
						self::setElementAttribute($form_name, 'no_lang', $no_lang);
						self::setElementAttribute($form_name, 'noLang', $no_lang);
					}
				}
				$form_names_done[$form_name] = true;
			}
		}

		// Make sure &nbsp;s, etc.  are properly encoded when sent, and not double-encoded
		$options = (isset(self::$request->sel_options[$form_name]) ? $form_name : $this->id);
		if(is_array(self::$request->sel_options[$options]))
		{
			if (isset($this->attrs['type']) && in_array($this->attrs['type'], self::$cached_types) && !isset($form_names_done[$options]))
			{
				// Fix any custom options from application
				self::fix_encoded_options(self::$request->sel_options[$options],true);
				$form_names_done[$options] = true;
			}
			// Turn on search, if there's a lot of rows (unless explicitly set)
			if(!array_key_exists('search',$this->attrs) && count(self::$request->sel_options[$options]) >= self::SEARCH_ROW_LIMIT)
			{
				self::setElementAttribute($form_name, "search", true);
			}
			if(!self::$request->sel_options[$options])
			{
				unset(self::$request->sel_options[$options]);
			}
		}
		switch($type)
		{
			case 'et2-select-cat':
			case 'select-cat':
				// preserve unavailable cats (eg. private user-cats)
				$type_options = self::typeOptions(
					$this,
					'',
					$no_lang, $this->attrs['readonly'] ?? false, self::get_array(self::$request->content, $form_name), $form_name
				);
				// Remove unavailable from value
				$application = self::expand_name($this->attrs['application'], 0, 0, '', '', self::$cont);
				if(!$application || $application === $GLOBALS['egw']->categories->app_name)
				{
					$categories = $GLOBALS['egw']->categories;
				}
				else    // we need to instanciate a new cat object for the correct application
				{
					$categories = new Api\Categories('', $application);
				}
				$value =& self::get_array(self::$request->content, $form_name, true);
				$value = $categories->check_list(Acl::READ, $value);
				break;
		}
	}

	/**
	 * Fix already html-encoded options, eg. "&nbps" AND optinal re-index array to keep order
	 *
	 * Get run automatic for everything in $sel_options by etemplate_new::exec / etemplate_new::fix_sel_options
	 *
	 * @param array $options
	 * @param boolean $use_array_of_objects Re-indexes options, making everything more complicated
	 */
	public static function fix_encoded_options(array &$options, $use_array_of_objects=null)
	{
		$backup_options = $options;

		$values = array();
		foreach($options as $value => &$label)
		{
			// Of course once we re-index the options, we can't detect duplicates
			// so check here, as we re-index
			// Duplicates might happen if app programmer isn't paying attention and
			// either uses the same ID in the template, or adds the options twice
			$check_value = (string)(is_array($label) && array_key_exists('value', $label) && !is_array($label['value']) ? $label['value'] : $value);
			if (isset($values[$check_value]))
			{
				unset($options[$value]);
				continue;
			}
			$values[$check_value] = $label;

			if (is_null($use_array_of_objects) && is_numeric($value) && (!is_array($label) || !isset($label['value'])))
			{
				$options = $backup_options;
				return self::fix_encoded_options($options, true);
			}
			// optgroup or values for keys "label" and "title"
			if(is_array($label))
			{
				self::fix_encoded_options($label, false);
				if ($use_array_of_objects && !array_key_exists('value', $label)) $label['value'] = $value;
			}
			else
			{
				$label = html_entity_decode($label, ENT_NOQUOTES, 'utf-8');

				if ($use_array_of_objects)
				{
					$label = array(
						'value' => $value,
						'label' => $label,
					);
				}
			}
		}
		if ($use_array_of_objects)
		{
			$options = array_values($options);
		}
	}

	/**
	 * Get options from $sel_options array for a given selectbox name
	 *
	 * @param string $name
	 * @param boolean $return_values =false true: return array with option values, instead of value => label pairs
	 * @return array
	 */
	public static function selOptions($name, $return_values=false)
	{
		$options = array();

		// Check for exact match on name
		if (isset(self::$request->sel_options[$name]) && is_array(self::$request->sel_options[$name]))
		{
			$options += self::$request->sel_options[$name];
		}

		// Check for non-trivial name like a[b]
		$name_parts = explode('[',str_replace(array('&#x5B;','&#x5D;',']'),array('['),$name));
		if(!$options)
		{
			$options = (array)self::get_array(self::$request->sel_options,$name);
			if(is_numeric(end($name_parts)) && $options['label'] && $options['value'])
			{
				// Too deep, we got a single option
				$options = array();
			}
		}

		// Check for base of name in root of sel_options
		if(!$options)
		{
			if (count($name_parts))
			{
				$org_name = $name_parts[count($name_parts)-1];
				if (isset(self::$request->sel_options[$org_name]) && is_array(self::$request->sel_options[$org_name]))
				{
					$options += self::$request->sel_options[$org_name];
				}
				elseif (isset(self::$request->sel_options[$name_parts[0]]) && is_array(self::$request->sel_options[$name_parts[0]]))
				{
					$options += self::$request->sel_options[$name_parts[0]];
				}
			}
		}

		// Check for options-$name in content
		if (isset(self::$request->content['options-'.$name]) && is_array(self::$request->content['options-'.$name]))
		{
			$options += self::$request->content['options-'.$name];
		}

		// check modifications
		if (isset(self::$request->modifications[$name]['select_options']) && is_array(self::$request->modifications[$name]['select_options']))
		{
			$options += self::$request->modifications[$name]['select_options'];
		}

		if ($return_values)
		{
			$values = array();
			foreach($options as $key => $val)
			{
				if (is_array($val))
				{
					if (isset($val['value']) && count(array_filter(array_keys($val), 'is_int')) == 0)
					{
						$values[] = $val['value'];
					}
					elseif ((isset($val['label']) || isset($val['title'])) && count($val) == 1 ||
						isset($val['title']) && isset($val['label']) && count($val) == 2)
					{
						// key => {label, title}
						$values[] = $key;
					}
					// support "id" used instead of "value" by old taglist
					elseif (isset($val['id']))
					{
						$values[] = $val['id'];
					}
					else	// optgroup
					{
						foreach($val as $k => $v)
						{
							$values[] = is_array($v) && isset($v['value']) ? $v['value'] : $k;
						}
					}
				}
				else
				{
					$values[] = $key;
				}
			}
			//error_log(__METHOD__."('$name', TRUE) options=".array2string($options).' --> values='.array2string($values));
			$options = $values;
		}
		else if (end($options) && is_array(end($options)) && isset(end($options)['value']))
		{
			$values = array();
			foreach($options as $index => $option)
			{
				if(is_array($option) && isset($option['value']))
				{
					$values[$option['value']] = $option['label'];
				}
				else
				{
					$values[$index] = $option;
				}
			}
			$options = $values;
		}
		//error_log(__METHOD__."('$name') returning ".array2string($options));
		return $options;
	}

	/**
	 * Fetch options for certain select-box types
	 *
	 * @param string|Select $widget_type Type of widget, or actual widget to get attributes since $legacy_options are legacy
	 * @param string $_legacy_options options string of widget
	 * @param boolean $no_lang =false initial value of no_lang attribute (some types set it to true)
	 * @param boolean $readonly =false for readonly we dont need to fetch all options, only the one for value
	 * @param mixed $value =null value for readonly
	 * @param string $form_name =null
	 * @return array with value => label pairs
	 */
	public static function typeOptions($widget_type, $_legacy_options, &$no_lang=false, $readonly=false, &$value=null, $form_name=null)
	{
		if($widget_type && is_object($widget_type))
		{
			$widget = $widget_type;
			$widget_type = $widget->attrs['type'] ? $widget->attrs['type'] : $widget->type;
			if(substr($widget_type, 0, 4) == 'et2-')
			{
				$widget_type = str_replace('et2-', '', $widget_type);
			}
		}
		// Legacy / static support
		// Have to do this explicitly, since legacy options is not defined on class level
		$legacy_options = explode(',',$_legacy_options);
		foreach($legacy_options as &$field)
		{
			$field = self::expand_name($field, 0, 0,'','',self::$cont);
		}

		list($rows,$type,$type2,$type3,$type4,$type5) = $legacy_options+[null,null,null,null,null,null];
		$no_lang = false;
		$options = array();
		switch ($widget_type)
		{
			case 'select-percent':
				$decr = self::expand_name($widget->attrs['interval'], 0, 0, '', '', self::$cont) ?? 10;
				for($i = 0; $i <= 100; $i += $decr)
				{
					$options[(int)$i] = (int)$i . '%';
				}
				$options[100] = '100%';
				if(!empty($value))
				{
					$value = intval(($value + ($decr / 2)) / $decr) * $decr;
				}
				$no_lang = True;
				break;

			case 'select-priority':
				$options = array('','low','normal','high');
				break;

			case 'select-bool':	// equal to checkbox, can be used with nextmatch-customfilter to filter a boolean column
				$options = array(0 => 'no',1 => 'yes');
				break;

			case 'select-country':	// #Row|Extralabel,1=use country name, 0=use 2 letter-code,custom country field name
				if($type == 0 && $type2)
				{
					$custom_label = is_numeric($type2) ? 'Custom' : $type2;
					$options = array('-custom-' => lang($custom_label)) + Api\Country::countries();
				}
				else
				{
					$options = Api\Country::countries();
				}
				if ($type && $value)
				{
					$value = Api\Country::country_code($value);
					if (!isset($options[$value]))
					{
						if($type2)
						{
							$options[$value] = $value;
						}
					}
				}
				$no_lang = True;
				break;

			case 'select-state':
				$options = (array)Api\Country::get_states($field);
				$no_lang = True;
				break;

			case 'select-cat':
				// !$type == globals cats too, $type2: extraStyleMultiselect, $type3: application, if not current-app, $type4: parent-id, $type5=owner (-1=global),$type6=show missing
				$application = self::expand_name($widget->attrs['application'], 0, 0, '', '', self::$cont) ?? $type3;
				$globalCategories = self::expand_name($widget->attrs['globalCategories'], 0, 0, '', '', self::$cont) ?? $type;
				$parentCat = self::expand_name($widget->attrs['parentCat'], 0, 0, '', '', self::$cont) ?? $type4;

				if((!$application || $application === $GLOBALS['egw']->categories->app_name) &&
					(!$type5 || $type5 == $GLOBALS['egw']->categories->account_id))
				{
					$categories = $GLOBALS['egw']->categories;
				}
				else    // we need to instanciate a new cat object for the correct application
				{
					$categories = new Api\Categories($type5, $application);
				}
				// Allow text for global
				$globalCategories = ($globalCategories && strlen($globalCategories) > 1 ? $globalCategories : !$globalCategories);
				// we cast $type4 (parent) to int, to get default of 0 if omitted
				foreach((array)$categories->return_sorted_array(0, False, '', '', '', $globalCategories, (int)$parentCat, true) as $cat)
				{
					$s = str_repeat('&nbsp;', $cat['level']) . stripslashes($cat['name']);

					if(Api\Categories::is_global($cat))
					{
						$s .= Api\Categories::$global_marker;
					}
					$options[$cat['id']] = array(
						'label'    => $s,
						'title'    => $cat['description'],
						// These are extra info for easy dealing with categories
						// client side, without extra loading
						'main'     => (int)$cat['main'],
						'children' => $cat['children'] ?? null,
						//add different class per level to allow different styling for each category level:
						'class'    => "cat_level" . $cat['level'] . " cat_{$cat['id']}"
					);
					// Send data too
					if(is_array($cat['data']))
					{
						$options[$cat['id']] += $cat['data'];
						if($cat['data']['icon'])
						{
							$options[$cat['id']]['icon'] = \admin_categories::icon_url($cat['data']['icon']);
						}
					}
				}
				// preserve unavailable cats (eg. private user-cats)
				if (isset(self::$request) && $value && ($unavailable = array_diff(is_array($value) ? $value : explode(',',$value),array_keys((array)$options))))
				{
					// unavailable cats need to be merged in again
					$unavailable_name = $form_name . self::UNAVAILABLE_CAT_POSTFIX;
					self::$request->preserv[$unavailable_name] = $unavailable;
				}
				$no_lang = True;
				break;

			case 'select-year':    // options: #rows,#before(default=3),#after(default=2)
				$before = self::expand_name($widget->attrs['min'], 0, 0, '', '', self::$cont) ?? 3;
				$after = self::expand_name($widget->attrs['max'], 0, 0, '', '', self::$cont) ?? 2;

				$options[''] = '';
				if($before <= 0)
				{
					$before = 3;
				}
				if($after <= 0)
				{
					$after = 2;
				}
				if($before > 100 && $after > 100 && $before > $after)
				{
					$y = $before;
					$before = $after;
					$after = $y;
				}
				if($value && $value - $before < $y || $before > 100)
				{
					$y = $before > 100 ? $before : $value - $before;
				}
				else
				{
					$y = (int)date('Y') - $before;
				}
				$to = date('Y') + $after;
				if($value && $value + $after > $to || $after > 100)
				{
					$to = $after > 100 ? $after : $value + $after;
				}
				for($n = 0; $y <= $to && $n < 200; ++$n)
				{
					$options[$y] = $y++;
				}
				$no_lang = True;
				break;

			case 'select-month':
				$options = self::$monthnames;
				$value = (int)$value;
				break;

			case 'select-dow':	// options: rows[,0=summaries befor days, 1=summaries after days, 2=no summaries[,extraStyleMultiselect]]
				if (!defined('MCAL_M_SUNDAY'))
				{
					define('MCAL_M_SUNDAY',1);
					define('MCAL_M_MONDAY',2);
					define('MCAL_M_TUESDAY',4);
					define('MCAL_M_WEDNESDAY',8);
					define('MCAL_M_THURSDAY',16);
					define('MCAL_M_FRIDAY',32);
					define('MCAL_M_SATURDAY',64);

					define('MCAL_M_WEEKDAYS',62);
					define('MCAL_M_WEEKEND',65);
					define('MCAL_M_ALLDAYS',127);
				}
				$weekstart = $GLOBALS['egw_info']['user']['preferences']['calendar']['weekdaystarts'];
				$options = array();
				if ($rows >= 2 && !$type)
				{
					$options = array(
						MCAL_M_ALLDAYS	=> 'all days',
						MCAL_M_WEEKDAYS	=> 'working days',
						MCAL_M_WEEKEND	=> 'weekend',
					);
				}
				if ($weekstart == 'Saturday') $options[MCAL_M_SATURDAY] = 'saturday';
				if ($weekstart != 'Monday') $options[MCAL_M_SUNDAY] = 'sunday';
				$options += array(
					MCAL_M_MONDAY	=> 'monday',
					MCAL_M_TUESDAY	=> 'tuesday',
					MCAL_M_WEDNESDAY=> 'wednesday',
					MCAL_M_THURSDAY	=> 'thursday',
					MCAL_M_FRIDAY	=> 'friday',
				);
				if ($weekstart != 'Saturday') $options[MCAL_M_SATURDAY] = 'saturday';
				if ($weekstart == 'Monday') $options[MCAL_M_SUNDAY] = 'sunday';
				if ($rows >= 2 && $type == 1)
				{
					$options += array(
						MCAL_M_ALLDAYS	=> 'all days',
						MCAL_M_WEEKDAYS	=> 'working days',
						MCAL_M_WEEKEND	=> 'weekend',
					);
				}
				if (!is_array($value))
				{
					$value_in = (int)$value;
					$value = array();
					foreach (array_keys($options) as $val)
					{
						if (($value_in & $val) == $val)
						{
							$value[] = $val;

							if ($val == MCAL_M_ALLDAYS ||
								$val == MCAL_M_WEEKDAYS && $value_in == MCAL_M_WEEKDAYS ||
								$val == MCAL_M_WEEKEND && $value_in == MCAL_M_WEEKEND)
							{
								break;    // dont set the others
							}
						}
					}
				}
				break;

			case 'select-day':
				$type = 1;
				$type2 = 31;
				$type3 = 1;
			// fall-through

			case 'select-number':    // options: rows,min,max,decrement,suffix
				$min = (int)(self::expand_name($widget->attrs['min'], 0, 0, '', '', self::$cont) ?? $type ?? 1);
				$max = (int)(self::expand_name($widget->attrs['max'], 0, 0, '', '', self::$cont) ?? $type2 ?? 10);
				$format = self::expand_name($widget->attrs['format'], 0, 0, '', '', self::$cont) ?? '%d';
				$decrement = (int)(self::expand_name($widget->attrs['interval'], 0, 0, '', '', self::$cont) ?? 1);
				if(!empty($type3) && $type3[0] == '0')            // leading zero
				{
					$format = '%0' . strlen($type3) . 'd';
				}
				if(($min <= $max) != ($decrement > 0))
				{
					$decrement = -$decrement;    // void infinite loop
				}
				if(!empty($type4))
				{
					$format .= lang($type4);
				}
				for($i = 0, $n = $min; $n <= $max && $i <= 100; $n += $decrement, ++$i)
				{
					$options[$n] = sprintf($format, $n);
				}
				$no_lang = True;
				break;

			case 'select-hour':
				$minutes = !$type2 ? ':00' : '';
				for ($h = 0; $h <= 23; ++$h)
				{
					$options[$h] = $GLOBALS['egw_info']['user']['preferences']['common']['timeformat'] == 12 ?
						(($h % 12 ? $h % 12 : 12).$minutes.' '.($h < 12 ? lang('am') : lang('pm'))) :
						sprintf('%02d',$h).$minutes;
				}
				$no_lang = True;
				break;

			case 'select-tab':
			case 'select-app':    // type2: 'user'=apps of current user, 'enabled', 'installed' (default), 'all' = not installed ones too
				$apps = self::app_options($type2);
				$options = is_array($options) ? $options + $apps : $apps;
				break;

			case 'select-lang':
				$options = Api\Translation::list_langs();
				$no_lang = True;
				break;

			case 'select-timezone':	// options: #rows,$type
				if (is_numeric($value))
				{
					$value = calendar_timezones::id2tz($value);
				}
				if ($readonly)	// for readonly we dont need to fetch all TZ's
				{
					$options[$value] = calendar_timezones::tz2id($value,'name');
				}
				else
				{
					$options = $type ? Api\DateTime::getTimezones() : Api\DateTime::getUserTimezones($value);
				}
				break;
			case 'select-bitwise':
				// type = app name
				$options = $form_name ? self::selOptions($form_name) : array();
				$new_value = array();
				$appname = $type ? $type : ($widget && $widget->attrs['appname'] ?
					self::expand_name($widget->attrs['appname'], 0, 0,'','',self::$cont) : '');
				if($appname)
				{
					$options += (array)Api\Hooks::single(array('location' => 'acl_rights'), $appname);
				}
				else
				{
					$options += array(
						'run' => 'Run',
						Api\Acl::READ => 'Read',
						Api\Acl::ADD => 'Add',
						Api\Acl::EDIT => 'Edit',
						Api\Acl::DELETE => 'Delete',
						Api\Acl::PRIVAT => 'Private',
						Api\Acl::GROUPMGRS => 'Group managers',
						Api\Acl::CUSTOM1 => 'Custom 1',
						Api\Acl::CUSTOM2 => 'Custom 2',
						Api\Acl::CUSTOM3 => 'Custom 3',
					);
				}
				foreach((array)$options as $right => $name)
				{
					if (!!((int)$value & (int)$right))
					{
						$new_value[] = $right;
					}
				}
				$value = $new_value;
		}
		if ($rows > 1 || $readonly)
		{
			unset($options['']);
		}

		//error_log(__METHOD__."('$widget_type', '$_legacy_options', no_lang=".array2string($no_lang).', readonly='.array2string($readonly).", value=$value) returning ".array2string($options));
		return $options;
	}

	/**
	 * Get available apps as options
	 *
	 * @param string $type2 ='installed[:home;groupdav; ...]' 'user'=apps of current user,
	 * 'enabled', 'installed' (default), 'all' = not installed ones too. In order to
	 * exclude apps explicitly we can list them (app name separator is ';') in front of the type.
	 *
	 * @return array app => label pairs sorted by label
	 */
	public static function app_options($type2)
	{
		$apps = array();
		$parts = explode(":", $type2);
		$exceptions = explode(";", $parts[1]);
		$type2 = $parts[0];

		foreach ($GLOBALS['egw_info']['apps'] as $app => $data)
		{
			if ($type2 == 'enabled' && (!$data['enabled'] || !$data['status'] || $data['status'] == 3 || in_array($app, $exceptions)))
			{
				continue;	// app not enabled (user can not have run rights for these apps)
			}
			if (($type2 != 'user' || $GLOBALS['egw_info']['user']['apps'][$app]) && !in_array($app, $exceptions))
			{
				$apps[$app] = lang($app);
			}
		}
		if ($type2 == 'all')
		{
			$dir = opendir(EGW_SERVER_ROOT);
			while ($file = readdir($dir))
			{
				if (@is_dir(EGW_SERVER_ROOT."/$file/setup") && $file[0] != '.' &&
				!isset($apps[$app = basename($file)]))
				{
					$apps[$app] = $app . ' (*)';
				}
			}
			closedir($dir);
		}
		natcasesort($apps);
		return $apps;
	}

	/**
	 * internal function to format account-data
	 *
	 * @param int $id
	 * @param array $acc =null optional values for keys account_(type|lid|lastname|firstname) to not read them again
	 * @param int $longnames =0
	 * @param boolean $show_type =false true: return array with values for keys label and icon, false: only label
	 * @return string|array
	 */
	public static function accountInfo($id,$acc=null,$longnames=0,$show_type=false)
	{
		if (!$id)
		{
			return '&nbsp;';
		}

		if (!is_array($acc))
		{
			$data = $GLOBALS['egw']->accounts->get_account_data($id);
			if (!isset($data[$id])) return '#'.$id;
			foreach(array('type','lid','firstname','lastname') as $name)
			{
				$acc['account_'.$name] = $data[$id][$name];
			}
		}

		if ($acc['account_type'] == 'g')
		{
			$longnames = 1;
		}
		$info = '';
		switch ($longnames)
		{
			case 2:
				$info .= '&lt;'.$acc['account_lid'].'&gt; ';
				// fall-through
			case 1:
				$info .= $acc['account_type'] == 'g' ? lang('group').' '.$acc['account_lid'] :
					$acc['account_firstname'].' '.$acc['account_lastname'];
				break;
			case '0':
				$info .= $acc['account_lid'];
				break;
			default:			// use the phpgw default
				$info = Api\Accounts::format_username($acc['account_lid'],
					$acc['account_firstname'],$acc['account_lastname']);
				break;
		}
		if($show_type) {
			$info = array(
				'label'	=> $info,
				'icon' => $acc['account_type'] == 'g' ? 'addressbook/group' : 'user'
			);
		}
		return $info;
	}

	/**
	 * Some select options are fairly static, but can only be generated on the server
	 * so we generate them here, then cache them client-side
	 *
	 * @param string $type
	 * @param Array|String $attributes
	 * @param string $value Optional current value, to make sure it's included
	 */
	public static function ajax_get_options($type, $attributes, $value = null)
	{
		// close session now, to not block other user actions
		$GLOBALS['egw']->session->commit_session();

		$no_lang = false;
		if(is_array($attributes))
		{
			$attributes = implode(',',$attributes);
		}
		$options = self::typeOptions($type, $attributes,$no_lang,false,$value);
		self::fix_encoded_options($options,true);
		$response = Api\Json\Response::get();
		$response->data($options);
	}
}

Etemplate\Widget::registerWidget(__NAMESPACE__ . '\\Select', array('et2-select', 'selectbox', 'listbox', 'select',
																   'menupopup'));