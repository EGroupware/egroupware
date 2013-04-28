<?php
/**
 * EGroupware - eTemplate serverside select widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 * @copyright 2002-13 by RalfBecker@outdoor-training.de
 * @version $Id$
 */

/**
 * eTemplate select widget
 *
 */
class etemplate_widget_menupopup extends etemplate_widget
{

	/**
	 * If the selectbox has this many rows, give it a search box automatically
	 */
	const SEARCH_ROW_LIMIT = 20;

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
	 * @return etemplate_widget_template current object or clone, if any attribute was set
	 * @todo Use legacy_attributes instead of leaving it to typeOptions method to parse them
	 */
	public function set_attrs($xml)
	{
		parent::set_attrs($xml);

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

		$ok = true;
		if (!$this->is_readonly($cname, $form_name))
		{
			$value = $value_in = self::get_array($content, $form_name);

			$allowed = $this->attrs['multiple'] ? array() : array('' => $this->attrs['options']);
			/* if beforeSendToClient is used, we dont need to call it again here
			if ($this->attrs['type'])
			{
				$allowed += self::typeOptions($form_name, $this->attrs['type'], $this->attrs['no_lang']);
				// current eTemplate uses sel_options too, not sure if we want/need to keep that
				//$allowed += self::selOptions($form_name, $this->attrs['no_lang']);
			}
			else*/
			{
				$allowed += self::selOptions($form_name);
			}
			foreach((array) $value as $val)
			{
				// array_key_exists() (used below) is inconsistent in how it handles empty/false
				// It needs a string or integer.
				if(!$val && $val !== 0) $val = '';

				// Special for select-account - selOptions doesn't always load all accounts
				if($this->attrs['type'] == 'select-account' && !$GLOBALS['egw']->accounts->visible($val) && !isset($allowed[$val]) ||
					$this->attrs['type'] != 'select-account' && !array_key_exists($val,$allowed))
				{
					self::set_validation_error($form_name,lang("'%1' is NOT allowed ('%2')!",$val,implode("','",array_keys($allowed))),'');
					$value = '';
					break;
				}
			}
			if ($ok && $value === '' && $this->attrs['needed'])
			{
				self::set_validation_error($form_name,lang('Field must not be empty !!!',$value),'');
			}
			$valid =& self::get_array($validated, $form_name, true);
			$valid = $value;
			error_log(__METHOD__."() $form_name: ".array2string($value_in).' --> '.array2string($value).', allowed='.array2string($allowed));
		}
	}

	/**
	 * Fill type options in self::$request->sel_options to be used on the client
	 *
	 * @param string $cname
	 */
	public function beforeSendToClient($cname)
	{
		$form_name = self::form_name($cname, $this->id);
		if (!is_array(self::$request->sel_options[$form_name])) self::$request->sel_options[$form_name] = array();
		if ($this->attrs['type'])
		{
			// Check selection preference, we may be able to skip reading some data
			$select_pref = $GLOBALS['egw_info']['user']['preferences']['common']['account_selection'];
			if($this->attrs['type'] == 'select-account' && !$GLOBALS['egw_info']['apps']['admin'] && $select_pref == 'none')
			{
				$this->attrs['readonly'] = true;
			}

			// += to keep further options set by app code
			self::$request->sel_options[$form_name] += self::typeOptions($this->attrs['type'],
				// typeOptions thinks # of rows is the first thing in options
				($this->attrs['rows'] && strpos($this->attrs['options'], $this->attrs['rows']) !== 0 ? $this->attrs['rows'].','.$this->attrs['options'] : $this->attrs['options']),
				$no_lang, $this->attrs['readonly'], self::get_array(self::$request->content, $form_name));


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
			self::fix_encoded_options(self::$request->sel_options[$options]);

			// Turn on search, if there's a lot of rows
			if(count(self::$request->sel_options[$options]) >= self::SEARCH_ROW_LIMIT)
			{
				self::setElementAttribute($form_name, "search", true);
			}
		}
	}

	/**
	 * Fix already html-encoded options, eg. "&nbps"
	 *
	 * @param array $options
	 */
	public static function fix_encoded_options(array &$options)
	{
		foreach($options as &$label)
		{
			// optgroup or values for keys "label" and "title"
			if(is_array($label))
			{
				self::fix_encoded_options($label);
			}
			else
			{
				$label = html_entity_decode($label, ENT_NOQUOTES, 'utf-8');
			}
		}
	}

	/**
	 * Get options from $sel_options array for a given selectbox name
	 *
	 * @param string $name
	 * @param boolean $no_lang=false value of no_lang attribute
	 * @return array
	 */
	public static function selOptions($name)
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
		//error_log(__METHOD__."('$name') returning ".array2string($options));
		return $options;
	}

	/**
	 * Fetch options for certain select-box types
	 *
	 * @param string $widget_type
	 * @param string $legacy_options options string of widget
	 * @param boolean $no_lang=false initial value of no_lang attribute (some types set it to true)
	 * @param boolean $readonly=false for readonly we dont need to fetch all options, only the one for value
	 * @param mixed $value=null value for readonly
	 * @return array with value => label pairs
	 */
	public static function typeOptions($widget_type, $legacy_options, &$no_lang=false, $readonly=false, $value=null)
	{
		list($rows,$type,$type2,$type3,$type4,$type5,$type6) = explode(',',$legacy_options);

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
				if (($extension_data['country_use_name'] = $type) && $value)
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


			case 'select-account':	// options: #rows,{accounts(default)|both|groups|owngroups},{0(=lid)|1(default=name)|2(=lid+name),expand-multiselect-rows,not-to-show-accounts,...)}

				// Get preference for selection display
				$select_pref = $GLOBALS['egw_info']['user']['preferences']['common']['account_selection'];

				// in case of readonly, we read/create only the needed entries, as reading accounts is expensive
				if (!is_array($value) && strpos($value,',') !== false) $value = explode(',',$value);
				if ($readonly || $select_pref == 'popup')
				{
					$no_lang = True;
					foreach(is_array($value) ? $value : array($value) as $id)
					{
						$options[$id] = !$id && !is_numeric($rows) ? lang($rows) :
							self::accountInfo($id,$acc,$type2,$type=='both');
					}
					break;
				}
				if($type == 'owngroups' || $select_pref == 'groupmembers')
				{
					$type = 'groups';
					$owngroups = true;
					foreach($GLOBALS['egw']->accounts->membership() as $group) $mygroups[] = $group['account_id'];
				}
				elseif (in_array($type, array('accounts', 'both')) && $select_pref == 'primary_group')
				{
					$owngroups = true;
					$mygroups[] = $GLOBALS['egw_info']['user']['primary_group'];
				}

				// Popup is the only preference option that doesn't need more select options
				if($select_pref == 'popup') break;

				$no_lang = True;
				$accs = $GLOBALS['egw']->accounts->get_list(empty($type) ? 'accounts' : $type); // default is accounts
				foreach($accs as $acc)
				{
					if ($acc['account_type'] == 'u')
					{
						$options[$acc['account_id']] = self::accountInfo($acc['account_id'],$acc,$type2,$type=='both');
					}
				}
				foreach($accs as $acc)
				{
					if ($acc['account_type'] == 'g' && (!$owngroups || ($owngroups && in_array($acc['account_id'],(array)$mygroups))))
					{
						$options[$acc['account_id']] = self::accountInfo($acc['account_id'],$acc,$type2,$type=='both');
					}
				}

				// Make sure all values are present, even if not normally sent (according to preferences)
				if(is_array($value))
				{
					$remaining = array_diff_key($value, $options);
				}
				if(is_array($remaining))
				{
					foreach($remaining as $id)
					{
						$options[$id] = !$id && !is_numeric($rows) ? lang($rows) : self::accountInfo($id,null,$type2,$type=='both');
					}
				}
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
				foreach($options as $val => $lable)
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
				if (!$readonly)
				{
					etemplate::$request->set_to_process($name,'ext-select-dow');
				}
				$cell['size'] = $rows.($type2 ? ','.$type2 : '');
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

			case 'select-app':	// type2: ''=users enabled apps, 'installed', 'all' = not installed ones too
				$apps = array();
				foreach ($GLOBALS['egw_info']['apps'] as $app => $data)
				{
					if (!$type2 || $GLOBALS['egw_info']['user']['apps'][$app])
					{
						$apps[$app] = $data['title'] ? $data['title'] : lang($app);
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
				$apps_lower = $apps;	// case-in-sensitve sort
				foreach ($apps_lower as $app => $title)
				{
					$apps_lower[$app] = strtolower($title);
				}
				asort($apps_lower);
				foreach ($apps_lower as $app => $title)
				{
					$options[$app] = $apps[$app];
				}
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
		if ($rows > 1)
		{
			unset($options['']);
		}

		//error_log(__METHOD__."('$widget_type', '$legacy_options', no_lang=".array2string($no_lang).', readonly='.array2string($readonly).", value=$value) returning ".array2string($options));
		return $options;
	}

	/**
	 * internal function to format account-data
	 */
	private static function accountInfo($id,$acc=0,$longnames=0,$show_type=0)
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
				'icon' => $acc['account_type'] == 'g' ? 'addressbook/group' : 'users'
			);
		}
		return $info;
	}
}

etemplate_widget::registerWidget('etemplate_widget_menupopup', array('selectbox','listbox','select'));
