<?php
/**
 * eGroupWare  eTemplate Extension - Select Widgets
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage extensions
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 * @copyright 2002-9 by RalfBecker@outdoor-training.de
 * @version $Id$
 */

/**
 * eTemplate Extension: several select-boxes with predefined eGW specific content
 *
 * This widgets replaces the not longer exiting phpgwapi.sbox class. The widgets are independent of the UI,
 * as they only uses etemplate-widgets and therefore have no render-function.
 */
class select_widget
{
	/**
	 * exported methods of this class
	 * @var array
	 */
	var $public_functions = array(
		'pre_process' => True,
		'post_process' => True,
	);
	/**
	 * availible extensions and their names for the editor
	 * @var array
	 */
	var $human_name = array(
		'select-percent'  => 'Select Percentage',
		'select-priority' => 'Select Priority',
		'select-access'   => 'Select Access',
		'select-country'  => 'Select Country',
		'select-state'    => 'Select State',	// US-states
		'select-cat'      => 'Select Category',	// Category-Selection, size: -1=Single+All, 0=Single, >0=Multiple with size lines
		'select-erole'	  => 'Select Element role',
		'select-account'  => 'Select Account',	// label=accounts(default),groups,both
												// size: -1=Single+not assigned, 0=Single, >0=Multiple
		'select-year'     => 'Select Year',
		'select-month'    => 'Select Month',
		'select-day'      => 'Select Day',
		'select-dow'      => 'Select Day of week',
		'select-hour'     => 'Select Hour',		// either 0-23 or 12am,1am-11am,12pm,1pm-11pm
		'select-number'   => 'Select Number',
		'select-app'      => 'Select Application',
		'select-lang'     => 'Select Language',
		'select-bool'     => 'Select yes or no',
		'select-timezone' => 'Select timezone',	// select timezone
	);
	/**
	 * @var array
	 */
	var $monthnames = array(
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
	 * Constructor of the extension
	 *
	 * @param string $ui '' for html
	 */
	function select_widget($ui)
	{
		foreach($this->monthnames as $k => $name)
		{
			if ($name)
			{
				$this->monthnames[$k] = lang($name);
			}
		}
		$this->ui = $ui;
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
	 * @param object &$tmpl reference to the template we belong too
	 * @return boolean true if extra label is allowed, false otherwise
	 */
	function pre_process($name,&$value,&$cell,&$readonlys,&$extension_data,&$tmpl)
	{
		list($rows,$type,$type2,$type3,$type4,$type5) = explode(',',$cell['size']);

		$extension_data['type'] = $cell['type'];

		$readonly = $cell['readonly'] || $readonlys;
		switch ($cell['type'])
		{
			case 'select-percent':	// options: #row,decrement(default=10)
				$decr = $type > 0 ? $type : 10;
				for ($i=0; $i <= 100; $i += $decr)
				{
					$cell['sel_options'][intval($i)] = intval($i).'%';
				}
				$cell['sel_options'][100] = '100%';
				if (!$rows || !empty($value))
				{
					$value = intval(($value+($decr/2)) / $decr) * $decr;
				}
				$cell['no_lang'] = True;
				break;

			case 'select-priority':
				$cell['sel_options'] = array('','low','normal','high');
				break;

			case 'select-bool':	// equal to checkbox, can be used with nextmatch-customfilter to filter a boolean column
				$cell['sel_options'] = array(0 => 'no',1 => 'yes');
				break;

			case 'select-access':
				$cell['sel_options'] = array(
					'private' => 'Private',
					'public' => 'Global public',
					'group' => 'Group public'
				);
				break;

			case 'select-country':	// #Row|Extralabel,1=use country name, 0=use 2 letter-code,custom country field name
				if($type == 0 && $type2)
				{
					$custom_label = is_numeric($type2) ? 'Custom' : $type2;
					$cell['sel_options'] = array('-custom-' => lang($custom_label)) + $GLOBALS['egw']->country->countries();
				}
				else
				{
					$cell['sel_options'] = $GLOBALS['egw']->country->countries();
				}
				if (($extension_data['country_use_name'] = $type) && $value)
				{
					$value = $GLOBALS['egw']->country->country_code($value);
					if (!isset($cell['sel_options'][$value]))
					{
						if($type2)
						{
							$cell['sel_options'][$value] = $value;
						}
					}
				}
				$cell['no_lang'] = True;
				break;

			case 'select-state':
				$cell['sel_options'] = $GLOBALS['egw']->country->us_states();
				$cell['no_lang'] = True;
				break;

			case 'select-cat':	// !$type == globals cats too, $type2: extraStyleMultiselect, $type3: application, if not current-app, $type4: parent-id, $type5=owner (-1=global)
				if ($readonly)	// for readonly we dont need to fetch all cat's, nor do we need to indent them by level
				{
					$cell['no_lang'] = True;
					if ($value)
					{
						if (!is_array($value)) $value = explode(',',$value);
						foreach($value as $key => $id)
						{
							if ($id && ($name = stripslashes($GLOBALS['egw']->categories->id2name($id))) && $name != '--')
							{
								$cell['sel_options'][$id] = $name;
							}
							else
							{
								unset($value[$key]);	// remove not (longer) existing or inaccessible cats
							}
						}
					}
					break;
				}
				if ((!$type3 || $type3 === $GLOBALS['egw']->categories->app_name) &&
					(!$type5 || $type5 ==  $GLOBALS['egw']->categories->account_id))
				{
					$categories = $GLOBALS['egw']->categories;
				}
				else	// we need to instanciate a new cat object for the correct application
				{
					$categories = new categories($type5,$type3);
				}
				// we cast $type4 (parent) to int, to get default of 0 if omitted
				foreach((array)$categories->return_sorted_array(0,False,'','','',!$type,(int)$type4,true) as $cat)
				{
					$s = str_repeat('&nbsp;',$cat['level']) . stripslashes($cat['name']);

					if (categories::is_global($cat))
					{
						$s .= ' &#9830;';
					}
					$cell['sel_options'][$cat['id']] = empty($cat['description']) ? $s : array(
						'label' => $s,
						'title' => $cat['description'],
					);
				}
				// preserv unavailible cats (eg. private user-cats)
				if ($value && ($unavailible = array_diff(is_array($value) ? $value : explode(',',$value),array_keys((array)$cell['sel_options']))))
				{
					$extension_data['unavailible'] = $unavailible;
				}
				$cell['size'] = $rows.($type2 ? ','.$type2 : '');
				$cell['no_lang'] = True;
				break;
				
			case 'select-erole': // $type2: extraStyleMultiselect
				$eroles = new projectmanager_eroles_so();
				if ($readonly)
				{
					$cell['no_lang'] = True;
					if ($value)
					{
						if (!is_array($value)) $value = explode(',',$value);
						foreach($value as $key => $id)
						{
							if ($id && ($name = $eroles->id2title($id)))
							{
								$cell['sel_options'][$id] = $name.($eroles->is_global($id) ? ' ('.lang('Global').')' : '');
							}
							else
							{
								unset($value[$key]);	// remove not (longer) existing or inaccessible eroles
							}
						}
					}
					break;
				}
				
				foreach($eroles->get_free_eroles() as $id => $data)
				{
					$cell['sel_options'][$data['role_id']] = array(
						'label' => $data['role_title'].($eroles->is_global($data['role_id']) ? ' ('.lang('Global').')' : ''),
						'title' => $data['role_description'],
					);
				}
				
				$cell['size'] = $rows.($type2 ? ','.$type2 : '');
				$cell['no_lang'] = True;
				break;


			case 'select-account':	// options: #rows,{accounts(default)|both|groups|owngroups},{0(=lid)|1(default=name)|2(=lid+name),expand-multiselect-rows,not-to-show-accounts,...)}
				//echo "<p>select-account widget: name=$cell[name], type='$type', rows=$rows, readonly=".(int)($cell['readonly'] || $readonlys)."</p>\n";
				if($type == 'owngroups')
				{
					$type = 'groups';
					$owngroups = true;
					foreach($GLOBALS['egw']->accounts->membership() as $group) $mygroups[] = $group['account_id'];
				}
				// in case of readonly, we read/create only the needed entries, as reading accounts is expensive
				if ($readonly)
				{
					$cell['no_lang'] = True;
					foreach(is_array($value) ? $value : (strpos($value,',') !== false ? explode(',',$value) : array($value)) as $id)
					{
						$cell['sel_options'][$id] = !$id && !is_numeric($rows) ? lang($rows) :
							$this->accountInfo($id,$acc,$type2,$type=='both');
					}
					break;
				}
				if ($this->ui == 'html' && $type != 'groups')	// use eGW's new account-selection (html only)
				{
					$not = array_slice(explode(',',$cell['size']),4);
					$help = (int)$cell['no_lang'] < 2 ? lang($cell['help']) : $cell['help'];
					$onFocus = "self.status='".addslashes(htmlspecialchars($help))."'; return true;";
					$onBlur  = "self.status=''; return true;";
					if ($cell['noprint'])
					{
						foreach(is_array($value) ? $value : (strpos($value,',') !== false ? explode(',',$value) : array($value)) as $id)
						{
							if ($id) $onlyPrint[] = $this->accountInfo($id,$acc,$type2,$type=='both');
						}
						$onlyPrint = $onlyPrint ? implode('<br />',$onlyPrint) : lang((int)$rows < 0 ? 'all' : $rows);
						$noPrint_class = ' class="noPrint"';
					}
					if (($rows > 0 || $type3) && substr($name,-2) != '[]') $name .= '[]';
					$value = $GLOBALS['egw']->uiaccountsel->selection($name,'eT_accountsel_'.str_replace(array('[','][',']'),array('_','_',''),$name),
						$value,$type,$rows > 0 ? $rows : ($type3 ? -$type3 : 0),$not,' onfocus="'.$onFocus.'" onblur="'.$onBlur.'"'.$noPrint_class,
						$cell['onchange'] == '1' ? 'this.form.submit();' : $cell['onchange'],
						!empty($rows) && 0+$rows <= 0 ? lang($rows < 0 ? 'all' : $rows) : False);
					if ($cell['noprint'])
					{
						$value = '<span class="onlyPrint">'.$onlyPrint.'</span>'.$value;
					}
					$cell['type'] = 'html';
					$cell['size'] = '';	// is interpreted as link otherwise
					etemplate::$request->set_to_process($name,'select');
					if ($cell['needed']) etemplate::$request->set_to_process_attribute($name,'needed',$cell['needed']);
					break;
				}
				$cell['no_lang'] = True;
				$accs = $GLOBALS['egw']->accounts->get_list(empty($type) ? 'accounts' : $type); // default is accounts
				foreach($accs as $acc)
				{
					if ($acc['account_type'] == 'u')
					{
						$cell['sel_options'][$acc['account_id']] = $this->accountInfo($acc['account_id'],$acc,$type2,$type=='both');
					}
				}
				foreach($accs as $acc)
				{
					if ($acc['account_type'] == 'g' && (!$owngroups || ($owngroups && in_array($acc['account_id'],(array)$mygroups))))
					{
						$cell['sel_options'][$acc['account_id']] = $this->accountInfo($acc['account_id'],$acc,$type2,$type=='both');
					}
				}
				break;

			case 'select-year':	// options: #rows,#before(default=3),#after(default=2)
				$cell['sel_options'][''] = '';
				if ($type <= 0)  $type  = 3;
				if ($type2 <= 0) $type2 = 2;
				if ($type > 100 && $type2 > 100 && $type > $type) { $y = $type; $type=$type2; $type2=$y; }
				$y = date('Y')-$type;
				if ($value && $value-$type < $y || $type > 100) $y = $type > 100 ? $type : $value-$type;
				$to = date('Y')+$type2;
				if ($value && $value+$type2 > $to || $type2 > 100) $to = $type2 > 100 ? $type2 : $value+$type2;
				for ($n = 0; $y <= $to && $n < 200; ++$n)
				{
					$cell['sel_options'][$y] = $y++;
				}
				$cell['no_lang'] = True;
				break;

			case 'select-month':
				$cell['sel_options'] = $this->monthnames;
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
				$cell['sel_options'] = array();
				if ($rows >= 2 && !$type)
				{
					$cell['sel_options'] = array(
						MCAL_M_ALLDAYS	=> 'all days',
						MCAL_M_WEEKDAYS	=> 'working days',
						MCAL_M_WEEKEND	=> 'weekend',
					);
				}
				if ($weekstart == 'Saturday') $cell['sel_options'][MCAL_M_SATURDAY] = 'saturday';
				if ($weekstart != 'Monday') $cell['sel_options'][MCAL_M_SUNDAY] = 'sunday';
				$cell['sel_options'] += array(
					MCAL_M_MONDAY	=> 'monday',
					MCAL_M_TUESDAY	=> 'tuesday',
					MCAL_M_WEDNESDAY=> 'wednesday',
					MCAL_M_THURSDAY	=> 'thursday',
					MCAL_M_FRIDAY	=> 'friday',
				);
				if ($weekstart != 'Saturday') $cell['sel_options'][MCAL_M_SATURDAY] = 'saturday';
				if ($weekstart == 'Monday') $cell['sel_options'][MCAL_M_SUNDAY] = 'sunday';
				if ($rows >= 2 && $type == 1)
				{
					$cell['sel_options'] += array(
						MCAL_M_ALLDAYS	=> 'all days',
						MCAL_M_WEEKDAYS	=> 'working days',
						MCAL_M_WEEKEND	=> 'weekend',
					);
				}
				$value_in = $value;
				$value = array();
				foreach($cell['sel_options'] as $val => $lable)
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
					$cell['sel_options'][$n] = sprintf($format,$n);
				}
				$cell['no_lang'] = True;
				break;

			case 'select-hour':
				for ($h = 0; $h <= 23; ++$h)
				{
					$cell['sel_options'][$h] = $GLOBALS['egw_info']['user']['preferences']['common']['timeformat'] == 12 ?
						(($h % 12 ? $h % 12 : 12).' '.($h < 12 ? lang('am') : lang('pm'))) :
						sprintf('%02d',$h);
				}
				$cell['no_lang'] = True;
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
					$cell['sel_options'][$app] = $apps[$app];
				}
				break;

			case 'select-lang':
				$cell['sel_options'] = translation::list_langs();
				$cell['no_lang'] = True;
				break;

			case 'select-timezone':	// options: #rows,$type
				$cell['sel_options'] = $type ? egw_time::getTimezones() : egw_time::getUserTimezones($value);
				break;
		}
		if ($rows > 1)
		{
			unset($cell['sel_options']['']);
		}
		return True;	// extra Label Ok
	}

	/**
	 * internal function to format account-data
	 */
	function accountInfo($id,$acc=0,$longnames=0,$show_type=0)
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
		$info = $show_type ? '('.$acc['account_type'].') ' : '';

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
		return $info;
	}

	/**
	 * postprocessing method, called after the submission of the form
	 *
	 * It has to copy the allowed/valid data from $value_in to $value, otherwise the widget
	 * will return no data (if it has a preprocessing method). The framework insures that
	 * the post-processing of all contained widget has been done before.
	 *
	 * @param string $name form-name of the widget
	 * @param mixed &$value the extension returns here it's input, if there's any
	 * @param mixed &$extension_data persistent storage between calls or pre- and post-process
	 * @param boolean &$loop can be set to true to request a re-submision of the form/dialog
	 * @param object &$tmpl the eTemplate the widget belongs too
	 * @param mixed &value_in the posted values (already striped of magic-quotes)
	 * @return boolean true if $value has valid content, on false no content will be returned!
	 */
	function post_process($name,&$value,&$extension_data,&$loop,&$tmpl,$value_in)
	{
		switch ($extension_data['type'])
		{
			case 'select-cat':
				$value = $value_in;
				// check if we have some unavailible cats and add them again
				if (is_array($extension_data['unavailible']) && $extension_data['unavailible'])
				{
					if (is_array($value))	// multiselection
					{
						$value = array_merge($value,$extension_data['unavailible']);
					}
					elseif (!$value)		// non-multiselection and nothing selected by the user
					{
						$value = $extension_data['unavailible'][0];
					}
				}
				break;
				
			case 'select-erole':
				$value = null;
				if(is_array($value_in)) $value = implode(',',$value_in);
				break;

			case 'select-dow':
				$value = 0;
				if (!is_array($value_in)) $value_in = explode(',',$value_in);
				foreach($value_in as $val)
				{
					$value |= $val;
				}
				//echo "<p>select_widget::post_process('$name',...,'$value_in'): value='$value'</p>\n";
				break;
			case 'select-country':
				if ($extension_data['country_use_name'] && $value_in)
				{
					if (($value = $GLOBALS['egw']->country->get_full_name($value_in)))
					{
						break;
					}
				}
				// fall through
			default:
				$value = $value_in;
				break;
		}
		//echo "<p>select_widget::post_process('$name',,'$extension_data',,,'$value_in'): value='$value', is_null(value)=".(int)is_null($value)."</p>\n";
		return true;
	}
}
