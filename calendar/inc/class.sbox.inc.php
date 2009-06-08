<?php
	/**************************************************************************\
	* eGroupWare API - Select Box                                              *
	* This file written by Marc Logemann <loge@phpgroupware.org>               *
	* Class for creating predefines select boxes                               *
	* Copyright (C) 2000, 2001 Dan Kuykendall                                  *
	* -------------------------------------------------------------------------*
	* This library is part of the eGroupWare API                               *
	* ------------------------------------------------------------------------ *
	* This library is free software; you can redistribute it and/or modify it  *
	* under the terms of the GNU Lesser General Public License as published by *
	* the Free Software Foundation; either version 2.1 of the License,         *
	* or any later version.                                                    *
	* This library is distributed in the hope that it will be useful, but      *
	* WITHOUT ANY WARRANTY; without even the implied warranty of               *
	* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.                     *
	* See the GNU Lesser General Public License for more details.              *
	* You should have received a copy of the GNU Lesser General Public License *
	* along with this library; if not, write to the Free Software Foundation,  *
	* Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA            *
	\**************************************************************************/
	/* $Id: class.sbox.inc.php 15449 2004-06-15 08:16:07Z ralfbecker $ */

	class sbox
	{
		var $monthnames = array(
			'',
			'January',
			'February',
			'March',
			'April',
			'May',
			'June',
			'July',
			'August',
			'September',
			'October',
			'November',
			'December'
		);

		var $weekdays = array(
			'',
			'Monday',
			'Tuesday',
			'Wednesday',
			'Thursday',
			'Friday',
			'Saturday',
			'Sunday'
		);

		function sbox()
		{
			if (!$this->country_array)
			{
				$country = CreateObject('phpgwapi.country');
				$this->country_array = &$country->country_array;
				unset($country);
				unset($this->country_array['  ']);
				// try to translate them and sort alphabetic
				foreach($this->country_array as $k => $name)
				{
					if (($translated = lang($name)) != $name.'*')
					{
						$this->country_array[$k] = $translated;
					}
				}
				asort($this->country_array);
			}
		}

		function hour_formated_text($name, $selected = 0)
		{
			$s = '<select name="' . $name . '">';
			$t_s[$selected] = ' selected';

			for ($i=0; $i<24; $i++)
			{
				$s .= '<option value="' . $i . '"' . $t_s[$i] . '>'
					. $GLOBALS['phpgw']->common->formattime($i+1,"00") . '</option>' . "\n";
			}
			$s .= "</select>";

			return $s;
		}

		function hour_text($name, $selected = 0)
		{
			$s = '<select name="' . $name . '">';
			$t_s[$selected] = " selected";
			for ($i=1; $i<13; $i++)
			{
				$s .= '<option value="' . $i . '"' . $t_s[$i] . '>'
					. $i . '</option>';
				$s .= "\n";
			}
			$s .= "</select>";

			return $s;
		}

		// I would like to add a increment feature
		function sec_minute_text($name, $selected = 0)
		{
			$s = '<select name="' . $name . '">';
			$t_s[$selected] = " selected";

			for ($i=0; $i<60; $i++)
			{
				$s .= '<option value="' . $i . '"' . $t_s[sprintf("%02d",$i)] . '>' . sprintf("%02d",$i) . '</option>';
				$s .= "\n";
			}
			$s .= "</select>";
			return $s;
		}

		function ap_text($name,$selected)
		{
			$selected = strtolower($selected);
			$t[$selected] = " selected";
			$s = '<select name="' . $name . '">'
				. ' <option value="am"' . $t['am'] . '>am</option>'
				. ' <option value="pm"' . $t['pm'] . '>pm</option>';
			$s .= '</select>';
			return $s;
		}

		function full_time($hour_name,$hour_selected,$min_name,$min_selected,$sec_name,$sec_selected,$ap_name,$ap_selected)
		{
			// This needs to be changed to support there time format preferences
			$s = $this->hour_text($hour_name,$hour_selected)
				. $this->sec_minute_text($min_name,$min_selected)
				. $this->sec_minute_text($sec_name,$sec_selected)
				. $this->ap_text($ap_name,$ap_selected);
			return $s;
		}

		function getWeekdays($name, $selected=0)
		{
			$out = '';
			for($i=0;$i<count($this->weekdays);$i++)
			{
				$out .= '<option value="'.$i.'"'.($selected!=$i?'':' selected').'>'.($this->weekdays[$i]!=''?lang($this->weekdays[$i]):'').'</option>'."\n";
			}
			return '<select name="'.$name.'">'."\n".$out.'</select>'."\n";
		}

		function nr2weekday($selected = 0)
		{
			for($i=0;$i<count($this->weekdays);$i++)
			{
				if ($selected > 0 && $selected == $i)
				{
					return lang($this->weekdays[$i]);
				}
			}
		}

		function getMonthText($name, $selected=0)
		{
			$out = '';
			$c_monthnames = count($this->monthnames);
			for($i=0;$i<$c_monthnames;$i++)
			{
				$out .= '<option value="'.$i.'"'.($selected!=$i?'':' selected').'>'.($this->monthnames[$i]!=''?lang($this->monthnames[$i]):'').'</option>'."\n";
			}
			return '<select name="'.$name.'">'."\n".$out.'</select>'."\n";
		}

		function getDays($name, $selected=0)
		{
			$out = '';

			for($i=0;$i<32;$i++)
			{
				$out .= '<option value="'.($i?$i:'').'"'.($selected!=$i?'':' selected').'>'.($i?$i:'').'</option>'."\n";
			}
			return '<select name="'.$name.'">'."\n".$out.'</select>'."\n";
		}

		function getYears($name, $selected = 0, $startYear = 0, $endyear = 0)
		{
			if (!$startYear)
			{
				$startYear = date('Y') - 5;
			}
			if ($selected && $startYear > $selected) $startYear = $selected;

			if (!$endyear)
			{
				$endyear = date('Y') + 6;
			}
			if ($selected && $endYear < $selected) $endYear = $selected;

			$out = '<select name="'.$name.'">'."\n";

			$out .= '<option value=""';
			if ($selected == 0 OR $selected == '')
			{
				$out .= ' SELECTED';
			}
			$out .= '></option>'."\n";

			// We need to add some good error checking here.
			for ($i=$startYear;$i<$endyear; $i++)
			{
				$out .= '<option value="'.$i.'"';
				if ($selected==$i)
				{
					$out .= ' SELECTED';
				}
				$out .= '>'.$i.'</option>'."\n";
			}
			$out .= '</select>'."\n";

			return $out;
		}

		function getPercentage($name, $selected=0)
		{
			$out = "<select name=\"$name\">\n";

			for($i=0;$i<101;$i=$i+10)
			{
				$out .= "<option value=\"$i\"";
				if($selected==$i)
				{
					$out .= " SELECTED";
				}
				$out .= ">$i%</option>\n";
			}
			$out .= "</select>\n";
			// echo $out;
			return $out;
		}

		function getPriority($name, $selected=2)
		{
			$arr = array('','low','normal','high');
			$out = '<select name="' . $name . '">';

			for($i=1;$i<count($arr);$i++)
			{
				$out .= "<option value=\"";
				$out .= $i;
				$out .= "\"";
				if ($selected==$i)
				{
					$out .= ' SELECTED';
				}
				$out .= ">";
				$out .= lang($arr[$i]);
				$out .= "</option>\n";
			}
			$out .= "</select>\n";
			return $out;
		}

		function getAccessList($name, $selected="private")
		{
			$arr = array(
				"private" => "Private",
				"public" => "Global public",
				"group" => "Group public"
			);

			if (strpos($selected,",") !== false)
			{
				$selected = "group";
			}

			$out = "<select name=\"$name\">\n";

			for(reset($arr);current($arr);next($arr))
			{
				$out .= '<option value="' . key($arr) . '"';
				if($selected==key($arr))
				{
					$out .= " SELECTED";
				}
				$out .= ">" . pos($arr) . "</option>\n";
			}
			$out .= "</select>\n";
			return $out;
		}

		function getGroups($groups, $selected="", $name="n_groups[]")
		{
			$out = '<select name="' . $name . '" multiple>';
			while (list($null,$group) = each($groups))
			{
				$out .= '<option value="' . $group['account_id'] . '"';
				if(@is_array($selected))
				{
					for($i=0;$i<count($selected);$i++)
					{
						if ($group['account_id'] == $selected[$i])
						{
							$out .= " SELECTED";
							break;
						}
					}
				}
				elseif (ereg("," . $group['account_id'] . ",", $selected))
				{
					$out .= " SELECTED";
				}
				$out .= ">" . $group['account_name'] . "</option>\n";
			}
			$out .= "</select>\n";

			return $out;
		}

		function list_states($name, $selected = '')
		{
			$states = array(
				''		=> lang('Select one'),
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

			while (list($sn,$ln) = each($states))
			{
				$s .= '<option value="' . $sn . '"';
				if ($selected == $sn)
				{
					$s .= ' selected';
				}
				$s .= '>' . $ln . '</option>';
			}
			return '<select name="' . $name . '">' . $s . '</select>';
		}

		function form_select($selected,$name='')
		{
			if($name=='')
			{
				$name = 'country';
			}
			$str = '<select name="'.$name.'">'."\n"
				. ' <option value="  "'.($selected == '  '?' selected':'').'>'.lang('Select One').'</option>'."\n";
			foreach($this->country_array as $key => $value)
			{
				$str .= ' <option value="'.$key.'"'.($selected == $key?' selected':'') . '>'.$value.'</option>'."\n";
			}
			$str .= '</select>'."\n";
			return $str;
		}

		function get_full_name($selected)
		{
			return($this->country_array[$selected]);
		}
	}
?>
