<?php
	/**************************************************************************\
	* eGroupWare - eTemplate Extension - Select Widgets                        *
	* http://www.egroupware.org                                                *
	* Written by Ralf Becker <RalfBecker@outdoor-training.de>                  *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	/*!
	@class select_widget
	@author ralfbecker
	@abstract Several select-boxes with predefined phpgw specific content.
	@discussion This widget replaces the old sbox class
	@discussion This widget is independent of the UI as it only uses etemplate-widgets and has therefor no render-function
	*/
	class select_widget
	{
		var $public_functions = array(
			'pre_process' => True
		);
		var $human_name = array(	// this are the names for the editor
			'select-percent'  => 'Select Percentage',
			'select-priority' => 'Select Priority',
			'select-access'   => 'Select Access',
			'select-country'  => 'Select Country',
			'select-state'    => 'Select State',	// US-states
			'select-cat'      => 'Select Category',// Category-Selection, size: -1=Single+All, 0=Single, >0=Multiple with size lines
			'select-account'  => 'Select Account',	// label=accounts(default),groups,both
																// size: -1=Single+not assigned, 0=Single, >0=Multiple
			'select-year'     => 'Select Year',
			'select-month'    => 'Select Month',
			'select-day'      => 'Select Day',
			'select-number'   => 'Select Number',
			'select-app'      => 'Select Application'
		);
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

		var $states = array(
			''		=> '',
			'--'	=> 'non US',
			'AL'	=>	'Alabama',
			'AK'	=>	'Alaska',
			'AZ'	=>	'Arizona',
			'AR'	=>	'Arkansas',
			'CA'	=>	'California',
			'CO'	=>	'Colorado',
			'CT'	=>	'Connecticut',
			'DE'	=>	'Delaware',
			'DC'	=>	'District of Columbia',
			'FL'	=>	'Florida',
			'GA'	=>	'Georgia',
			'HI'	=>	'Hawaii',
			'ID'	=>	'Idaho',
			'IL'	=>	'Illinois',
			'IN'	=>	'Indiana',
			'IA'	=>	'Iowa',
			'KS'	=>	'Kansas',
			'KY'	=>	'Kentucky',
			'LA'	=>	'Louisiana',
			'ME'	=>	'Maine',
			'MD'	=>	'Maryland',
			'MA'	=>	'Massachusetts',
			'MI'	=>	'Michigan',
			'MN'	=>	'Minnesota',
			'MO'	=>	'Missouri',
			'MS'	=>	'Mississippi',
			'MT'	=>	'Montana',
			'NC'	=>	'North Carolina',
			'ND'	=>	'Noth Dakota',
			'NE'	=>	'Nebraska',
			'NH'	=>	'New Hampshire',
			'NJ'	=>	'New Jersey',
			'NM'	=>	'New Mexico',
			'NV'	=>	'Nevada',
			'NY'	=>	'New York',
			'OH'	=>	'Ohio',
			'OK'	=>	'Oklahoma',
			'OR'	=>	'Oregon',
			'PA'	=>	'Pennsylvania',
			'RI'	=>	'Rhode Island',
			'SC'	=>	'South Carolina',
			'SD'	=>	'South Dakota',
			'TN'	=>	'Tennessee',
			'TX'	=>	'Texas',
			'UT'	=>	'Utah',
			'VA'	=>	'Virginia',
			'VT'	=>	'Vermont',
			'WA'	=>	'Washington',
			'WI'	=>	'Wisconsin',
			'WV'	=>	'West Virginia',
			'WY'	=>	'Wyoming'
		);

		function select_widget($ui)
		{
			foreach($this->monthnames as $k => $name)
			{
				if ($name)
				{
					$this->monthnames[$k] = lang($name);
				}
			}
		}

		function pre_process($name,&$value,&$cell,&$readonlys,&$extension_data,&$tmpl)
		{
			list($rows,$type,$type2,$type3) = explode(',',$cell['size']);

			switch ($cell['type'])
			{
				case 'select-percent':	// options: #row,decrement(default=10)
					$decr = $type > 0 ? $type : 10;
					for ($i=0; $i <= 100; $i += $decr)
					{
						$cell['sel_options'][intval($i)] = intval($i).'%';
					}
					$cell['sel_options'][100] = '100%';
					$value = intval(($value+($decr/2)) / $decr) * $decr;
					$cell['no_lang'] = True;
					break;

				case 'select-priority':
					$cell['sel_options'] = array('','low','normal','high');
					break;

				case 'select-access':
					$cell['sel_options'] = array(
						'private' => 'Private',
						'public' => 'Global public',
						'group' => 'Group public'
					);
					break;

				case 'select-country':
					if (!$this->countrys)
					{
						$country = CreateObject('phpgwapi.country');
						$this->countrys = &$country->country_array;
						unset($country);
						unset($this->countrys['  ']);
						$this->countrys[''] = '';
						// try to translate them and sort alphabetic
						foreach($this->countrys as $k => $name)
						{
							if (($translated = lang($name)) != $name.'*')
							{
								$this->countrys[$k] = $translated;
							}
						}
						asort($this->countrys);
					}
					$cell['sel_options'] = $this->countrys;
					$cell['no_lang'] = True;
					break;

				case 'select-state':
					$cell['sel_options'] = $this->states;
					$cell['no_lang'] = True;
					break;

				case 'select-cat':	// !$type == globals cats too
					if (!is_object($GLOBALS['phpgw']->categories))
					{
						$GLOBALS['phpgw']->categories = CreateObject('phpgwapi.categories');
					}
					$cats = $GLOBALS['phpgw']->categories->return_sorted_array(0,False,'','','',!$type);

					while (list(,$cat) = @each($cats))
					{
						for ($j=0,$s=''; $j < $cat['level']; $j++)
						{
							$s .= '&nbsp;';
						}
						$s .= $GLOBALS['phpgw']->strip_html($cat['name']);
						if ($cat['app_name'] == 'phpgw')
						{
							$s .= '&nbsp;&lt;' . lang('Global') . '&gt;';
						}
						if ($cat['owner'] == '-1')
						{
							$s .= '&nbsp;&lt;' . lang('Global') . '&nbsp;' . lang($cat['app_name']) . '&gt;';
						}
						if ($tmpl->stable)
						{
							$cell['sel_options'][$cat['id']] = $s;	// 0.9.14 only
						}
						else
						{
							$cell['sel_options'][$cat['cat_id']] = $s;
						}
					}
					$cell['no_lang'] = True;
					break;

				case 'select-account':	// options: #rows,{accounts(default)|both|groups},{0(=lid)|1(default=name)|2(=lid+name))}
					$cell['no_lang'] = True;
					// in case of readonly, we read/create only the needed entries, as reading accounts is expensive
					if ($cell['readonly'] || $readonlys)
					{
						foreach(is_array($value) ? $value : array($value) as $id)
						{
							$cell['sel_options'][$id] = $this->accountInfo($id,$acc,$type2,$type=='both');
						}
						break;
					}
					$accs = $GLOBALS['phpgw']->accounts->get_list(empty($type) ? 'accounts' : $type); // default is accounts
					foreach($accs as $acc)
					{
						if ($acc['account_type'] == 'g')
						{
							$cell['sel_options'][$acc['account_id']] = $this->accountInfo($acc['account_id'],$acc,$type2,$type=='both');
						}
					}
					foreach($accs as $acc)
					{
						if ($acc['account_type'] == 'u')
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

				case 'select-day':
					$type = 1;
					$type2 = 31;
					$type3 = 1;
					// fall-through
					
				case 'select-number':	// options: rows,min,max,decrement
					$type = $type === '' ? 1 : intval($type);		// min
					$type2 = $type2 === '' ? 10 : intval($type2);	// max
					$format = '%d';
					if (!empty($type3) && $type3[0] == '0')			// leading zero
					{
						$format = '%0'.strlen($type3).'d';
					}
					$type3 = !$type3 ? 1 : intval($type3);			// decrement
					if (($type < $type2) != ($type3 > 0))
					{
						$type3 = -$type3;	// void infinite loop
					}
					for ($i=0,$n=$type; $n <= $type2 && $i <= 100; $n += $type3)
					{
						$cell['sel_options'][$n] = sprintf($format,$n);
					}
					$cell['no_lang'] = True;
					break;
					
				case 'select-app':	// type2: ''=users enabled apps, 'installed', 'all' = not installed ones too
					$apps = array();
					foreach ($GLOBALS['phpgw_info']['apps'] as $app => $data)
					{
						if (!$type2 || $GLOBALS['phpgw_info']['user']['apps'][$app])
						{
							$apps[$app] = $data['title'] ? $data['title'] : lang($app);
						}
					}
					if ($type2 == 'all')
					{
						$dir = opendir(PHPGW_SERVER_ROOT);
						while ($file = readdir($dir))
						{
							if (@is_dir(PHPGW_SERVER_ROOT."/$file/setup") && $file[0] != '.' &&
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
			}
			if ($rows > 1)
			{
				unset($cell['sel_options']['']);
			}
			return True;	// extra Label Ok
		}

		function accountInfo($id,$acc=0,$longnames=0,$show_type=0)
		{
			if (!$id)
			{
				return '&nbsp;';
			}

			if (!is_array($acc))
			{
				$data = $GLOBALS['phpgw']->accounts->get_account_data($id);
				foreach(array('type','lid','firstname','lastname') as $name)
				{
					$acc['account_'.$name] = $data[$id][$name];
				}
			}
			$info = $show_type ? '('.$acc['account_type'].') ' : '';

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
					$info = $GLOBALS['phpgw']->common->display_fullname($acc['account_lid'],
						$acc['account_firstname'],$acc['account_lastname']);
					break;
			}
			return $info;
		}
	}
