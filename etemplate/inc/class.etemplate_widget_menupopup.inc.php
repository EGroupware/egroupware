<?php
/**
 * EGroupware - eTemplate serverside select widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 * @copyright 2002-14 by RalfBecker@outdoor-training.de
 * @version $Id$
 */

/**
 * eTemplate select widget
 *
 * @todo unavailable cats (eg. private cats of an other user) need to be preserved!
 */
class etemplate_widget_menupopup extends etemplate_widget
{
	/**
	 * If the selectbox has this many rows, give it a search box automatically
	 */
	const SEARCH_ROW_LIMIT = PHP_INT_MAX; // Automatic disabled, only explicit

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
	 * Parse and set extra attributes from xml in template object
	 *
	 * Reimplemented to parse our differnt attributes
	 *
	 * @param string|XMLReader $xml
	 * @param boolean $cloned =true true: object does NOT need to be cloned, false: to set attribute, set them in cloned object
	 * @return etemplate_widget_template current object or clone, if any attribute was set
	 * @todo Use legacy_attributes instead of leaving it to typeOptions method to parse them
	 */
	public function set_attrs($xml, $cloned=true)
	{
		parent::set_attrs($xml, $cloned);

		// set attrs[multiple] from attrs[options], unset options only if it just contains number or rows
		if ($this->attrs['options'] > 1)
		{
			$this->attrs['multiple'] = (int)$this->attrs['options'];
			if ((string)$this->attrs['multiple'] == $this->attrs['options'])
			{
				unset($this->attrs['options']);
			}
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
		$widget_type = $this->attrs['type'] ? $this->attrs['type'] : $this->type;

		$ok = true;
		if (!$this->is_readonly($cname, $form_name))
		{
			$value = $value_in = self::get_array($content, $form_name);

			$allowed = self::selOptions($form_name, true);	// true = return array of option-values
			if (!$this->attrs['multiple'] || !($this->attrs['options'] > 1)) $allowed[] = '';

			foreach((array) $value as $val)
			{
				// handle empty-label for all widget types
				if ((string)$val === '' && in_array('', $allowed)) continue;

				switch ($widget_type)
				{
					case 'select-account':
						// If in allowed options, skip account check to support app-specific options
						if(count($allowed) > 0 && in_array($val,$allowed)) continue;

						// validate accounts independent of options know to server
						$account_type = $this->attrs['account_type'] ? $this->attrs['account_type'] : 'accounts';
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

					default:
						if(!in_array($val, $allowed))
						{
							self::set_validation_error($form_name,lang("'%1' is NOT allowed ('%2')!", $val, implode("','",$allowed)),'');
							$value = '';
							break 2;
						}
				}
			}
			if ($ok && $value === '' && $this->attrs['needed'])
			{
				self::set_validation_error($form_name,lang('Field must not be empty !!!',$value),'');
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
						$value = $GLOBALS['egw']->country->get_full_name($value);
					}
					break;

				case 'select-cat':
					// ToDo: unavailable cats need to be merged in again
			}
			$valid =& self::get_array($validated, $form_name, true);
			if (true) $valid = $value;
			//error_log(__METHOD__."() $form_name: ".array2string($value_in).' --> '.array2string($value).', allowed='.array2string($allowed));
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
		else
		{
			$form_name = self::form_name($cname, $this->id, $expand);
		}
		if (!is_array(self::$request->sel_options[$form_name])) self::$request->sel_options[$form_name] = array();
		$type = $this->attrs['type'] ? $this->attrs['type'] : $this->type;
		if ($type != 'select')
		{
			// Check selection preference, we may be able to skip reading some data
			$select_pref = $GLOBALS['egw_info']['user']['preferences']['common']['account_selection'];
			if($this->attrs['type'] == 'select-account' && !$GLOBALS['egw_info']['user']['apps']['admin'] && $select_pref == 'none')
			{
				self::$request->preserv[$this->id] = self::$request->content[$this->id];
				unset(self::$request->content[$this->id]);
				$this->attrs['readonly'] = true;
			}

			// += to keep further options set by app code
			$no_lang = null;
			self::$request->sel_options[$form_name] += self::typeOptions($this,
				// typeOptions thinks # of rows is the first thing in options
				($this->attrs['rows'] && strpos($this->attrs['options'], $this->attrs['rows']) !== 0 ? $this->attrs['rows'].','.$this->attrs['options'] : $this->attrs['options']),
				$no_lang, $this->attrs['readonly'], self::get_array(self::$request->content, $form_name));
			// need to run that here manually, automatic run through etemplate_new::exec() already happend
			self::fix_encoded_options(self::$request->sel_options[$form_name]);

			// if no_lang was modified, forward modification to the client
			if ($no_lang != $this->attr['no_lang'])
			{
				self::setElementAttribute($form_name, 'no_lang', $no_lang);
			}
		}

		// Make sure &nbsp;s, etc.  are properly encoded when sent, and not double-encoded
		$options = (self::$request->sel_options[$form_name] ? $form_name : $this->id);
		if(is_array(self::$request->sel_options[$options]))
		{
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

		foreach($options as $value => &$label)
		{
			// Of course once we re-index the options, we can't detect duplicates
			// so check here, as we re-index
			// Duplicates might happen if app programmer isn't paying attention and
			// either uses the same ID in the template, or adds the options twice
			if(is_numeric($value) && (!is_array($label) || is_array($label) && !array_key_exists('value',$label)))
			{
				$check_value = (string)(is_array($label) && array_key_exists('value', $label) ? $label['value'] : $value);
				if((string)$value === $check_value)
				{
					foreach($options as $key => $existing)
					{
						if(is_array($existing) && isset($existing['value']) && (string)$existing['value'] === $check_value && $key != $value)
						{
							unset($options[$value]);
							continue 2;
						}
					}
				}
			}
			if (is_null($use_array_of_objects) && is_numeric($value))
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

		// Check for base of name in root of sel_options
		if(!$options)
		{
			$name_parts = explode('[',str_replace(array('&#x5B;','&#x5D;',']'),array('['),$name));
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
		if (is_array(self::$request->content['options-'.$name]))
		{
			$options += self::$request->content['options-'.$name];
		}
		if ($return_values)
		{
			$values = array();
			foreach($options as $key => $val)
			{
				if (is_array($val))
				{
					if (isset($val['value']))
					{
						$values[] = $val['value'];
					}
					else if ((isset($val['label']) || isset($val['title'])) && count($val) == 1 ||
						isset($val['title']) && isset($val['label']) && count($val) == 2)
					{
						// key => {label, title}
						$values[] = $key;
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
		//error_log(__METHOD__."('$name') returning ".array2string($options));
		return $options;
	}

	/**
	 * Fetch options for certain select-box types
	 *
	 * @param string|etemplate_widget_menupopup $widget_type Type of widget, or actual widget to get attributes since $legacy_options are legacy
	 * @param string $_legacy_options options string of widget
	 * @param boolean $no_lang=false initial value of no_lang attribute (some types set it to true)
	 * @param boolean $readonly=false for readonly we dont need to fetch all options, only the one for value
	 * @param mixed $value=null value for readonly
	 * @return array with value => label pairs
	 */
	public static function typeOptions($widget_type, $_legacy_options, &$no_lang=false, $readonly=false, &$value=null)
	{
		if($widget_type && is_object($widget_type))
		{
			$widget = $widget_type;
			$widget_type = $widget->attrs['type'] ? $widget->attrs['type'] : $widget->type;
			// Legacy / static support
			// Have to do this explicitly, since legacy options is not defined on class level
			$legacy_options = explode(',',$_legacy_options);
			foreach($legacy_options as &$field)
			{
				$field = self::expand_name($field, 0, 0,'','',self::$cont);
			}
			list($rows,$type,$type2,$type3,$type4,$type5) = $legacy_options;
		}
		$no_lang = false;
		$options = array();
		switch ($widget_type)
		{
			case 'select-percent':	// options: #row,decrement(default=10)
				$decr = $type > 0 ? $type : 10;
				for ($i=0; $i <= 100; $i += $decr)
				{
					$options[intval($i)] = intval($i).'%';
				}
				$options[100] = '100%';
				if (!$rows || !empty($value))
				{
					$value = intval(($value+($decr/2)) / $decr) * $decr;
				}
				$no_lang = True;
				break;

			case 'select-priority':
				$options = array('','low','normal','high');
				break;

			case 'select-bool':	// equal to checkbox, can be used with nextmatch-customfilter to filter a boolean column
				$options = array(0 => 'no',1 => 'yes');
				break;

			case 'select-access':
				$options = array(
					'private' => 'Private',
					'public' => 'Global public',
					'group' => 'Group public'
				);
				break;

			case 'select-country':	// #Row|Extralabel,1=use country name, 0=use 2 letter-code,custom country field name
				if($type == 0 && $type2)
				{
					$custom_label = is_numeric($type2) ? 'Custom' : $type2;
					$options = array('-custom-' => lang($custom_label)) + $GLOBALS['egw']->country->countries();
				}
				else
				{
					$options = $GLOBALS['egw']->country->countries();
				}
				if ($type && $value)
				{
					$value = $GLOBALS['egw']->country->country_code($value);
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
				$options = $GLOBALS['egw']->country->us_states();
				$no_lang = True;
				break;

			case 'select-cat':	// !$type == globals cats too, $type2: extraStyleMultiselect, $type3: application, if not current-app, $type4: parent-id, $type5=owner (-1=global),$type6=show missing
				if ((!$type3 || $type3 === $GLOBALS['egw']->categories->app_name) &&
					(!$type5 || $type5 ==  $GLOBALS['egw']->categories->account_id))
				{
					$categories = $GLOBALS['egw']->categories;
				}
				else	// we need to instanciate a new cat object for the correct application
				{
					$categories = new categories($type5,$type3);
				}
				// Allow text for global
				$type = ($type && strlen($type) > 1 ? $type : !$type);
				// we cast $type4 (parent) to int, to get default of 0 if omitted
				foreach((array)$categories->return_sorted_array(0,False,'','','',$type,(int)$type4,true) as $cat)
				{
					$s = str_repeat('&nbsp;',$cat['level']) . stripslashes($cat['name']);

					if (categories::is_global($cat))
					{
						$s .= categories::$global_marker;
					}
					$options[$cat['id']] = array(
						'label' => $s,
						'title' => $cat['description'],
					);
					// Send data too
					if(is_array($cat['data']))
					{
						$options[$cat['id']] += $cat['data'];
					}
				}
				// preserv unavailible cats (eg. private user-cats)
				/* TODO
				if ($value && ($unavailible = array_diff(is_array($value) ? $value : explode(',',$value),array_keys((array)$options))))
				{
					$extension_data['unavailible'] = $unavailible;
				}
				$cell['size'] = $rows.($type2 ? ','.$type2 : '');
				*/
				$no_lang = True;
				break;

			case 'select-year':	// options: #rows,#before(default=3),#after(default=2)
				$options[''] = '';
				if ($type <= 0)  $type  = 3;
				if ($type2 <= 0) $type2 = 2;
				if ($type > 100 && $type2 > 100 && $type > $type) { $y = $type; $type=$type2; $type2=$y; }
				$y = date('Y')-$type;
				if ($value && $value-$type < $y || $type > 100) $y = $type > 100 ? $type : $value-$type;
				$to = date('Y')+$type2;
				if ($value && $value+$type2 > $to || $type2 > 100) $to = $type2 > 100 ? $type2 : $value+$type2;
				for ($n = 0; $y <= $to && $n < 200; ++$n)
				{
					$options[$y] = $y++;
				}
				$no_lang = True;
				break;

			case 'select-month':
				$options = self::$monthnames;
				$value = intval($value);
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
				$value_in = $value;
				$value = array();
				foreach(array_keys($options) as $val)
				{
					if (($value_in & $val) == $val)
					{
						$value[] = $val;

						if ($val == MCAL_M_ALLDAYS ||
							$val == MCAL_M_WEEKDAYS && $value_in == MCAL_M_WEEKDAYS ||
							$val == MCAL_M_WEEKEND && $value_in == MCAL_M_WEEKEND)
						{
							break;	// dont set the others
						}
					}
				}
				break;

			case 'select-day':
				$type = 1;
				$type2 = 31;
				$type3 = 1;
				// fall-through

			case 'select-number':	// options: rows,min,max,decrement,suffix
				$type = $type === '' ? 1 : intval($type);		// min
				$type2 = $type2 === '' ? 10 : intval($type2);	// max
				$format = '%d';
				if (!empty($type3) && $type3[0] == '0')			// leading zero
				{
					$format = '%0'.strlen($type3).'d';
				}
				$type3 = !$type3 ? 1 : intval($type3);			// decrement
				if (($type <= $type2) != ($type3 > 0))
				{
					$type3 = -$type3;	// void infinite loop
				}
				if (!empty($type4)) $format .= lang($type4);
				for ($i=0,$n=$type; $n <= $type2 && $i <= 100; $n += $type3,++$i)
				{
					$options[$n] = sprintf($format,$n);
				}
				$no_lang = True;
				break;

			case 'select-hour':
				for ($h = 0; $h <= 23; ++$h)
				{
					$options[$h] = $GLOBALS['egw_info']['user']['preferences']['common']['timeformat'] == 12 ?
						(($h % 12 ? $h % 12 : 12).' '.($h < 12 ? lang('am') : lang('pm'))) :
						sprintf('%02d',$h);
				}
				$no_lang = True;
				break;

			case 'select-app':	// type2: 'user'=apps of current user, 'enabled', 'installed' (default), 'all' = not installed ones too
				$apps = self::app_options($type2);
				$options = is_array($options) ? $options+$apps : $apps;
				break;

			case 'select-lang':
				$options = translation::list_langs();
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
					$options = $type ? egw_time::getTimezones() : egw_time::getUserTimezones($value);
				}
				break;
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
	 * @param string $type2 ='installed' 'user'=apps of current user, 'enabled', 'installed' (default), 'all' = not installed ones too
	 * @return array app => label pairs sorted by label
	 */
	public static function app_options($type2)
	{
		$apps = array();
		foreach ($GLOBALS['egw_info']['apps'] as $app => $data)
		{
			if ($type2 == 'enabled' && (!$data['enabled'] || !$data['status'] || $data['status'] == 3))
			{
				continue;	// app not enabled (user can not have run rights for these apps)
			}
			if ($type2 != 'user' || $GLOBALS['egw_info']['user']['apps'][$app])
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
	 * @param int $longnames
	 * @param boolean $show_type true: return array with values for keys label and icon, false: only label
	 * @return string|array
	 */
	private static function accountInfo($id,$acc=null,$longnames=0,$show_type=false)
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
				$info = $GLOBALS['egw']->common->display_fullname($acc['account_lid'],
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
}

etemplate_widget::registerWidget('etemplate_widget_menupopup', array('selectbox','listbox','select','menupopup'));
