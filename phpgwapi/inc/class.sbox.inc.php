<?php
	/**************************************************************************\
	* phpGroupWare API - Select Box                                            *
	* This file written by Marc Logemann <loge@phpgroupware.org>               *
	* Class for creating predefines select boxes                               *
	* Copyright (C) 2000, 2001 Dan Kuykendall                                  *
	* -------------------------------------------------------------------------*
	* This library is part of the phpGroupWare API                             *
	* http://www.phpgroupware.org/api                                          * 
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

	/* $Id$ */

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

		var $country_array = array(
			'AF'=>'AFGHANISTAN',
			'AL'=>'ALBANIA',
			'DZ'=>'ALGERIA', 
			'AS'=>'AMERICAN SAMOA', 
			'AD'=>'ANDORRA',
			'AO'=>'ANGOLA', 
			'AI'=>'ANGUILLA', 
			'AQ'=>'ANTARCTICA', 
			'AG'=>'ANTIGUA AND BARBUDA', 
			'AR'=>'ARGENTINA', 
			'AM'=>'ARMENIA', 
			'AW'=>'ARUBA', 
			'AU'=>'AUSTRALIA', 
			'AT'=>'AUSTRIA', 
			'AZ'=>'AZERBAIJAN', 
			'BS'=>'BAHAMAS', 
			'BH'=>'BAHRAIN', 
			'BD'=>'BANGLADESH', 
			'BB'=>'BARBADOS', 
			'BY'=>'BELARUS', 
			'BE'=>'BELGIUM', 
			'BZ'=>'BELIZE', 
			'BJ'=>'BENIN', 
			'BM'=>'BERMUDA', 
			'BT'=>'BHUTAN', 
			'BO'=>'BOLIVIA', 
			'BA'=>'BOSNIA AND HERZEGOVINA', 
			'BW'=>'BOTSWANA', 
			'BV'=>'BOUVET ISLAND', 
			'BR'=>'BRAZIL', 
			'IO'=>'BRITISH INDIAN OCEAN TERRITORY', 
			'BN'=>'BRUNEI DARUSSALAM', 
			'BG'=>'BULGARIA', 
			'BF'=>'BURKINA FASO', 
			'BI'=>'BURUNDI', 
			'KH'=>'CAMBODIA', 
			'CM'=>'CAMEROON', 
			'CA'=>'CANADA', 
			'CV'=>'CAPE VERDE', 
			'KY'=>'CAYMAN ISLANDS', 
			'CF'=>'CENTRAL AFRICAN REPUBLIC', 
			'TD'=>'CHAD', 
			'CL'=>'CHILE', 
			'CN'=>'CHINA', 
			'CX'=>'CHRISTMAS ISLAND', 
			'CC'=>'COCOS (KEELING) ISLANDS', 
			'CO'=>'COLOMBIA', 
			'KM'=>'COMOROS', 
			'CG'=>'CONGO', 
			'CD'=>'CONGO, THE DEMOCRATIC REPUBLIC OF THE', 
			'CK'=>'COOK ISLANDS', 
			'CR'=>'COSTA RICA', 
			'CI'=>'COTE D IVOIRE', 
			'HR'=>'CROATIA', 
			'CU'=>'CUBA', 
			'CY'=>'CYPRUS', 
			'CZ'=>'CZECH REPUBLIC', 
			'DK'=>'DENMARK', 
			'DJ'=>'DJIBOUTI', 
			'DM'=>'DOMINICA', 
			'DO'=>'DOMINICAN REPUBLIC', 
			'TP'=>'EAST TIMOR', 
			'EC'=>'ECUADOR', 
			'EG'=>'EGYPT', 
			'SV'=>'EL SALVADOR', 
			'GQ'=>'EQUATORIAL GUINEA', 
			'ER'=>'ERITREA', 
			'EE'=>'ESTONIA', 
			'ET'=>'ETHIOPIA', 
			'FK'=>'FALKLAND ISLANDS (MALVINAS)', 
			'FO'=>'FAROE ISLANDS', 
			'FJ'=>'FIJI', 
			'FI'=>'FINLAND', 
			'FR'=>'FRANCE', 
			'GF'=>'FRENCH GUIANA', 
			'PF'=>'FRENCH POLYNESIA', 
			'TF'=>'FRENCH SOUTHERN TERRITORIES', 
			'GA'=>'GABON', 
			'GM'=>'GAMBIA', 
			'GE'=>'GEORGIA', 
			'DE'=>'GERMANY', 
			'GH'=>'GHANA', 
			'GI'=>'GIBRALTAR', 
			'GR'=>'GREECE', 
			'GL'=>'GREENLAND', 
			'GD'=>'GRENADA', 
			'GP'=>'GUADELOUPE', 
			'GU'=>'GUAM', 
			'GT'=>'GUATEMALA', 
			'GN'=>'GUINEA', 
			'GW'=>'GUINEA-BISSAU', 
			'GY'=>'GUYANA', 
			'HT'=>'HAITI', 
			'HM'=>'HEARD ISLAND AND MCDONALD ISLANDS', 
			'VA'=>'HOLY SEE (VATICAN CITY STATE)', 
			'HN'=>'HONDURAS', 
			'HK'=>'HONG KONG', 
			'HU'=>'HUNGARY', 
			'IS'=>'ICELAND', 
			'IN'=>'INDIA', 
			'ID'=>'INDONESIA', 
			'IR'=>'IRAN, ISLAMIC REPUBLIC OF', 
			'IQ'=>'IRAQ', 
			'IE'=>'IRELAND', 
			'IL'=>'ISRAEL', 
			'IT'=>'ITALY', 
			'JM'=>'JAMAICA', 
			'JP'=>'JAPAN', 
			'JO'=>'JORDAN', 
			'KZ'=>'KAZAKSTAN', 
			'KE'=>'KENYA', 
			'KI'=>'KIRIBATI', 
			'KP'=>'KOREA, DEMOCRATIC PEOPLES REPUBLIC OF', 
			'KR'=>'KOREA, REPUBLIC OF', 
			'KW'=>'KUWAIT', 
			'KG'=>'KYRGYZSTAN', 
			'LA'=>'LAO PEOPLES DEMOCRATIC REPUBLIC', 
			'LV'=>'LATVIA', 
			'LB'=>'LEBANON', 
			'LS'=>'LESOTHO', 
			'LR'=>'LIBERIA', 
			'LY'=>'LIBYAN ARAB JAMAHIRIYA', 
			'LI'=>'LIECHTENSTEIN', 
			'LT'=>'LITHUANIA', 
			'LU'=>'LUXEMBOURG', 
			'MO'=>'MACAU', 
			'MK'=>'MACEDONIA, THE FORMER YUGOSLAV REPUBLIC OF', 
			'MG'=>'MADAGASCAR', 
			'MW'=>'MALAWI', 
			'MY'=>'MALAYSIA', 
			'MV'=>'MALDIVES', 
			'ML'=>'MALI', 
			'MT'=>'MALTA', 
			'MH'=>'MARSHALL ISLANDS', 
			'MQ'=>'MARTINIQUE', 
			'MR'=>'MAURITANIA', 
			'MU'=>'MAURITIUS', 
			'YT'=>'MAYOTTE', 
			'MX'=>'MEXICO', 
			'FM'=>'MICRONESIA, FEDERATED STATES OF', 
			'MD'=>'MOLDOVA, REPUBLIC OF', 
			'MC'=>'MONACO', 
			'MN'=>'MONGOLIA', 
			'MS'=>'MONTSERRAT', 
			'MA'=>'MOROCCO', 
			'MZ'=>'MOZAMBIQUE', 
			'MM'=>'MYANMAR', 
			'NA'=>'NAMIBIA', 
			'NR'=>'NAURU', 
			'NP'=>'NEPAL', 
			'NL'=>'NETHERLANDS', 
			'AN'=>'NETHERLANDS ANTILLES', 
			'NC'=>'NEW CALEDONIA', 
			'NZ'=>'NEW ZEALAND', 
			'NI'=>'NICARAGUA', 
			'NE'=>'NIGER', 
			'NG'=>'NIGERIA', 
			'NU'=>'NIUE', 
			'NF'=>'NORFOLK ISLAND', 
			'MP'=>'NORTHERN MARIANA ISLANDS', 
			'NO'=>'NORWAY', 
			'OM'=>'OMAN', 
			'PK'=>'PAKISTAN', 
			'PW'=>'PALAU', 
			'PS'=>'PALESTINIAN TERRITORY, OCCUPIED', 
			'PA'=>'PANAMA', 
			'PG'=>'PAPUA NEW GUINEA', 
			'PY'=>'PARAGUAY', 
			'PE'=>'PERU', 
			'PH'=>'PHILIPPINES', 
			'PN'=>'PITCAIRN', 
			'PL'=>'POLAND', 
			'PT'=>'PORTUGAL', 
			'PR'=>'PUERTO RICO', 
			'QA'=>'QATAR', 
			'RE'=>'REUNION', 
			'RO'=>'ROMANIA', 
			'RU'=>'RUSSIAN FEDERATION', 
			'RW'=>'RWANDA', 
			'SH'=>'SAINT HELENA', 
			'KN'=>'SAINT KITTS AND NEVIS', 
			'LC'=>'SAINT LUCIA', 
			'PM'=>'SAINT PIERRE AND MIQUELON', 
			'VC'=>'SAINT VINCENT AND THE GRENADINES', 
			'WS'=>'SAMOA', 
			'SM'=>'SAN MARINO', 
			'ST'=>'SAO TOME AND PRINCIPE', 
			'SA'=>'SAUDI ARABIA', 
			'SN'=>'SENEGAL', 
			'SC'=>'SEYCHELLES', 
			'SL'=>'SIERRA LEONE', 
			'SG'=>'SINGAPORE', 
			'SK'=>'SLOVAKIA', 
			'SI'=>'SLOVENIA', 
			'SB'=>'SOLOMON ISLANDS', 
			'SO'=>'SOMALIA', 
			'ZA'=>'SOUTH AFRICA', 
			'GS'=>'SOUTH GEORGIA AND THE SOUTH SANDWICH ISLANDS', 
			'ES'=>'SPAIN', 
			'LK'=>'SRI LANKA', 
			'SD'=>'SUDAN', 
			'SR'=>'SURINAME', 
			'SJ'=>'SVALBARD AND JAN MAYEN', 
			'SZ'=>'SWAZILAND', 
			'SE'=>'SWEDEN', 
			'CH'=>'SWITZERLAND', 
			'SY'=>'SYRIAN ARAB REPUBLIC', 
			'TW'=>'TAIWAN, PROVINCE OF CHINA', 
			'TJ'=>'TAJIKISTAN', 
			'TZ'=>'TANZANIA, UNITED REPUBLIC OF', 
			'TH'=>'THAILAND', 
			'TG'=>'TOGO', 
			'TK'=>'TOKELAU', 
			'TO'=>'TONGA', 
			'TT'=>'TRINIDAD AND TOBAGO', 
			'TN'=>'TUNISIA', 
			'TR'=>'TURKEY', 
			'TM'=>'TURKMENISTAN', 
			'TC'=>'TURKS AND CAICOS ISLANDS', 
			'TV'=>'TUVALU', 
			'UG'=>'UGANDA', 
			'UA'=>'UKRAINE',
			'AE'=>'UNITED ARAB EMIRATES',
			'GB'=>'UNITED KINGDOM',
			'US'=>'UNITED STATES',
			'UM'=>'UNITED STATES MINOR OUTLYING ISLANDS',
			'UY'=>'URUGUAY',
			'UZ'=>'UZBEKISTAN',
			'VU'=>'VANUATU',
			'VE'=>'VENEZUELA',
			'VN'=>'VIET NAM',
			'VG'=>'VIRGIN ISLANDS, BRITISH',
			'VI'=>'VIRGIN ISLANDS, U.S.',
			'WF'=>'WALLIS AND FUTUNA',
			'EH'=>'WESTERN SAHARA',
			'YE'=>'YEMEN',
			'YU'=>'YUGOSLAVIA',
			'ZM'=>'ZAMBIA',
			'ZW'=>'ZIMBABWE'
		);

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

			if (ereg(",", $selected))
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
				if (strtolower(gettype($selected)) == strtolower("array"))
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
				. ' <option value="  "'.($selected == '  '?' selected':'').'>Select One</option>'."\n";
			reset($this->country_array);
			while(list($key,$value) = each($this->country_array))
			{
				$str .= ' <option value="'.$key.'"'.($selected == $key?' selected':'') . '>'.lang($value).'</option>'."\n";
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
