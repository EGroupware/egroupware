<?php
/**************************************************************************\
* eGroupWare - Country Codes                                               *
* http://www.egroupware.org                                                *
* Written by Mark Peters <skeeter@phpgroupware.org>                        *
* --------------------------------------------                             *
*  This program is free software; you can redistribute it and/or modify it *
*  under the terms of the GNU General Public License as published by the   *
*  Free Software Foundation; either version 2 of the License, or (at your  *
*  option) any later version.                                              *
\**************************************************************************/

/* $Id$ */

/**
 * 2-digit ISO 3166 Country codes
 *
 * http://www.iso.ch/iso/en/prods-services/iso3166ma/02iso-3166-code-lists/list-en1.html
 */
class country
{
	/**
	 * array with 2-letter iso-3166 country-code => country-name pairs
	 *
	 * @var array
	 */
	var $country_array = array(
		'AX' => 'AALAND ISLANDS',
		'AF' => 'AFGHANISTAN',
		'AL' => 'ALBANIA',
		'DZ' => 'ALGERIA',
		'AS' => 'AMERICAN SAMOA',
		'AD' => 'ANDORRA',
		'AO' => 'ANGOLA',
		'AI' => 'ANGUILLA',
		'AQ' => 'ANTARCTICA',
		'AG' => 'ANTIGUA AND BARBUDA',
		'AR' => 'ARGENTINA',
		'AM' => 'ARMENIA',
		'AW' => 'ARUBA',
		'AU' => 'AUSTRALIA',
		'AT' => 'AUSTRIA',
		'AZ' => 'AZERBAIJAN',
		'BS' => 'BAHAMAS',
		'BH' => 'BAHRAIN',
		'BD' => 'BANGLADESH',
		'BB' => 'BARBADOS',
		'BY' => 'BELARUS',
		'BE' => 'BELGIUM',
		'BZ' => 'BELIZE',
		'BJ' => 'BENIN',
		'BM' => 'BERMUDA',
		'BT' => 'BHUTAN',
		'BO' => 'BOLIVIA',
		'BA' => 'BOSNIA AND HERZEGOVINA',
		'BW' => 'BOTSWANA',
		'BV' => 'BOUVET ISLAND',
		'BR' => 'BRAZIL',
		'IO' => 'BRITISH INDIAN OCEAN TERRITORY',
		'BN' => 'BRUNEI DARUSSALAM',
		'BG' => 'BULGARIA',
		'BF' => 'BURKINA FASO',
		'BI' => 'BURUNDI',
		'KH' => 'CAMBODIA',
		'CM' => 'CAMEROON',
		'CA' => 'CANADA',
		'CV' => 'CAPE VERDE',
		'KY' => 'CAYMAN ISLANDS',
		'CF' => 'CENTRAL AFRICAN REPUBLIC',
		'TD' => 'CHAD',
		'CL' => 'CHILE',
		'CN' => 'CHINA',
		'CX' => 'CHRISTMAS ISLAND',
		'CC' => 'COCOS (KEELING) ISLANDS',
		'CO' => 'COLOMBIA',
		'KM' => 'COMOROS',
		'CG' => 'CONGO',
		'CD' => 'CONGO, THE DEMOCRATIC REPUBLIC OF THE',
		'CK' => 'COOK ISLANDS',
		'CR' => 'COSTA RICA',
		'CI' => 'COTE D IVOIRE',
		'HR' => 'CROATIA',
		'CU' => 'CUBA',
		'CY' => 'CYPRUS',
		'CZ' => 'CZECH REPUBLIC',
		'DK' => 'DENMARK',
		'DJ' => 'DJIBOUTI',
		'DM' => 'DOMINICA',
		'DO' => 'DOMINICAN REPUBLIC',
		'TP' => 'FORMER EAST TIMOR',
		'EC' => 'ECUADOR',
		'EG' => 'EGYPT',
		'SV' => 'EL SALVADOR',
		'GQ' => 'EQUATORIAL GUINEA',
		'ER' => 'ERITREA',
		'EE' => 'ESTONIA',
		'ET' => 'ETHIOPIA',
		'FK' => 'FALKLAND ISLANDS (MALVINAS)',
		'FO' => 'FAROE ISLANDS',
		'FJ' => 'FIJI',
		'FI' => 'FINLAND',
		'FR' => 'FRANCE',
		'GF' => 'FRENCH GUIANA',
		'PF' => 'FRENCH POLYNESIA',
		'TF' => 'FRENCH SOUTHERN TERRITORIES',
		'GA' => 'GABON',
		'GM' => 'GAMBIA',
		'GE' => 'GEORGIA',
		'DE' => 'GERMANY',
		'GH' => 'GHANA',
		'GI' => 'GIBRALTAR',
		'GR' => 'GREECE',
		'GL' => 'GREENLAND',
		'GD' => 'GRENADA',
		'GP' => 'GUADELOUPE',
		'GU' => 'GUAM',
		'GT' => 'GUATEMALA',
		'GN' => 'GUINEA',
		'GW' => 'GUINEA-BISSAU',
		'GY' => 'GUYANA',
		'HT' => 'HAITI',
		'HM' => 'HEARD ISLAND AND MCDONALD ISLANDS',
		'VA' => 'HOLY SEE (VATICAN CITY STATE)',
		'HN' => 'HONDURAS',
		'HK' => 'HONG KONG',
		'HU' => 'HUNGARY',
		'IS' => 'ICELAND',
		'IN' => 'INDIA',
		'ID' => 'INDONESIA',
		'IR' => 'IRAN, ISLAMIC REPUBLIC OF',
		'IQ' => 'IRAQ',
		'IE' => 'IRELAND',
		'IL' => 'ISRAEL',
		'IT' => 'ITALY',
		'JM' => 'JAMAICA',
		'JP' => 'JAPAN',
		'JO' => 'JORDAN',
		'KZ' => 'KAZAKSTAN',
		'KE' => 'KENYA',
		'KI' => 'KIRIBATI',
		'KP' => 'KOREA DEMOCRATIC PEOPLES REPUBLIC OF',
		'KR' => 'KOREA REPUBLIC OF',
		'KW' => 'KUWAIT',
		'KG' => 'KYRGYZSTAN',
		'LA' => 'LAO PEOPLES DEMOCRATIC REPUBLIC',
		'LV' => 'LATVIA',
		'LB' => 'LEBANON',
		'LS' => 'LESOTHO',
		'LR' => 'LIBERIA',
		'LY' => 'LIBYAN ARAB JAMAHIRIYA',
		'LI' => 'LIECHTENSTEIN',
		'LT' => 'LITHUANIA',
		'LU' => 'LUXEMBOURG',
		'MO' => 'MACAU',
		'MK' => 'MACEDONIA, THE FORMER YUGOSLAV REPUBLIC OF',
		'MG' => 'MADAGASCAR',
		'MW' => 'MALAWI',
		'MY' => 'MALAYSIA',
		'MV' => 'MALDIVES',
		'ML' => 'MALI',
		'MT' => 'MALTA',
		'MH' => 'MARSHALL ISLANDS',
		'MQ' => 'MARTINIQUE',
		'MR' => 'MAURITANIA',
		'MU' => 'MAURITIUS',
		'YT' => 'MAYOTTE',
		'MX' => 'MEXICO',
		'FM' => 'MICRONESIA, FEDERATED STATES OF',
		'MD' => 'MOLDOVA, REPUBLIC OF',
		'MC' => 'MONACO',
		'ME' => 'MONTENEGRO',
		'MN' => 'MONGOLIA',
		'MS' => 'MONTSERRAT',
		'MA' => 'MOROCCO',
		'MZ' => 'MOZAMBIQUE',
		'MM' => 'MYANMAR',
		'NA' => 'NAMIBIA',
		'NR' => 'NAURU',
		'NP' => 'NEPAL',
		'NL' => 'NETHERLANDS',
		'AN' => 'NETHERLANDS ANTILLES',
		'NC' => 'NEW CALEDONIA',
		'NZ' => 'NEW ZEALAND',
		'NI' => 'NICARAGUA',
		'NE' => 'NIGER',
		'NG' => 'NIGERIA',
		'NU' => 'NIUE',
		'NF' => 'NORFOLK ISLAND',
		'MP' => 'NORTHERN MARIANA ISLANDS',
		'NO' => 'NORWAY',
		'OM' => 'OMAN',
		'PK' => 'PAKISTAN',
		'PW' => 'PALAU',
		'PS' => 'PALESTINIAN TERRITORY, OCCUPIED',
		'PA' => 'PANAMA',
		'PG' => 'PAPUA NEW GUINEA',
		'PY' => 'PARAGUAY',
		'PE' => 'PERU',
		'PH' => 'PHILIPPINES',
		'PN' => 'PITCAIRN',
		'PL' => 'POLAND',
		'PT' => 'PORTUGAL',
		'PR' => 'PUERTO RICO',
		'QA' => 'QATAR',
		'RE' => 'REUNION',
		'RO' => 'ROMANIA',
		'RU' => 'RUSSIAN FEDERATION',
		'RW' => 'RWANDA',
		'SH' => 'SAINT HELENA',
		'KN' => 'SAINT KITTS AND NEVIS',
		'LC' => 'SAINT LUCIA',
		'PM' => 'SAINT PIERRE AND MIQUELON',
		'VC' => 'SAINT VINCENT AND THE GRENADINES',
		'WS' => 'SAMOA',
		'SM' => 'SAN MARINO',
		'ST' => 'SAO TOME AND PRINCIPE',
		'SA' => 'SAUDI ARABIA',
		'SN' => 'SENEGAL',
		'CS' =>	'FORMER SERBIA AND MONTENEGRO',
		'RS' => 'SERBIA',
		'SC' => 'SEYCHELLES',
		'SL' => 'SIERRA LEONE',
		'SG' => 'SINGAPORE',
		'SK' => 'SLOVAKIA',
		'SI' => 'SLOVENIA',
		'SB' => 'SOLOMON ISLANDS',
		'SO' => 'SOMALIA',
		'ZA' => 'SOUTH AFRICA',
		'GS' => 'SOUTH GEORGIA AND THE SOUTH SANDWICH ISLANDS',
		'ES' => 'SPAIN',
		'LK' => 'SRI LANKA',
		'SD' => 'SUDAN',
		'SR' => 'SURINAME',
		'SJ' => 'SVALBARD AND JAN MAYEN',
		'SZ' => 'SWAZILAND',
		'SE' => 'SWEDEN',
		'CH' => 'SWITZERLAND',
		'SY' => 'SYRIAN ARAB REPUBLIC',
		'TW' => 'TAIWAN',
		'TJ' => 'TAJIKISTAN',
		'TZ' => 'TANZANIA, UNITED REPUBLIC OF',
		'TH' => 'THAILAND',
		'TL' =>	'TIMOR-LESTE',
		'TG' => 'TOGO',
		'TK' => 'TOKELAU',
		'TO' => 'TONGA',
		'TT' => 'TRINIDAD AND TOBAGO',
		'TN' => 'TUNISIA',
		'TR' => 'TURKEY',
		'TM' => 'TURKMENISTAN',
		'TC' => 'TURKS AND CAICOS ISLANDS',
		'TV' => 'TUVALU',
		'UG' => 'UGANDA',
		'UA' => 'UKRAINE',
		'AE' => 'UNITED ARAB EMIRATES',
		'GB' => 'UNITED KINGDOM',
		'US' => 'UNITED STATES',
		'UM' => 'UNITED STATES MINOR OUTLYING ISLANDS',
		'UY' => 'URUGUAY',
		'UZ' => 'UZBEKISTAN',
		'VU' => 'VANUATU',
		'VE' => 'VENEZUELA',
		'VN' => 'VIET NAM',
		'VG' => 'VIRGIN ISLANDS, BRITISH',
		'VI' => 'VIRGIN ISLANDS, U.S.',
		'WF' => 'WALLIS AND FUTUNA',
		'EH' => 'WESTERN SAHARA',
		'YE' => 'YEMEN',
		'YU' => 'FORMER YUGOSLAVIA',
		'ZM' => 'ZAMBIA',
		'ZW' => 'ZIMBABWE'
	);
	/**
	 * translated list, set by country::_translate
	 *
	 * @var array
	 */
	var $countries_translated;
	/**
	 * List of US states as 2-letter code => name pairs
	 *
	 * @var array
	 */
	var $us_states_array = array(
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

	/**
	 * Get list of US states
	 *
	 * @return array with code => name pairs
	 */
	function us_states()
	{
		return $this->us_states_array;
	}

	/**
	 * Selectbox for country-selection
	 *
	 * @deprecated use html::select with country_array
	 * @param string $selected 2-letter iso country-code
	 * @param string $name='country'
	 * @return string
	 */
	function form_select($code,$name='country')
	{
		return html::select($name,strtoupper($code),$this->country_array);
	}

	/**
	 * Get country-name from the 2-letter iso code
	 *
	 * @param string $selected 2-letter iso country-code
	 * @param boolean $translated=true use translated name or english
	 * @return string
	 */
	function get_full_name($code,$translated=true)
	{
		if ($translated)
		{
			if (!$this->countries_translated) $this->_translate_countries();

			return $this->countries_translated[strtoupper($code)];
		}
		return $this->country_array[strtoupper($code)];
	}

	/**
	 * Get the 2-letter code for a given country name
	 *
	 * @param string $name
	 * @return string 2-letter code or $name if no code found
	 */
	function country_code($name)
	{
		if (!$name) return '';	// nothing to do

		if (strlen($name) == 2 && isset($this->country_array[$name]))
		{
			return $name;	// $name is already a country-code
		}

		if (($code = array_search(strtoupper($name),$this->country_array)) !== false)
		{
			return $code;
		}
		if (!$this->countries_translated) $this->_translate_countries();

		if (($code = array_search(strtoupper($name),$this->countries_translated)) !== false ||
			($code = array_search($name,$this->countries_translated)) !== false)
		{
			return $code;
		}
		// search case-insensitive all translations for the english phrase of given country $name
		// we do that to catch all possible cases of translations
		static $en_names = array();	// we do some caching to minimize db-accesses
		if (isset($en_names[$name]))
		{
			$name = $en_names[$name];
		}
		elseif (($name_en = $GLOBALS['egw']->translation->get_message_id($name,'common')))
		{
			$name = $en_names[$name] = strtoupper($name_en);
		}
		if (($code = array_search(strtoupper($name),$this->country_array)) !== false)
		{
			return $code;
		}
		return $name;
	}

	/**
	 * Get list of country names
	 *
	 * @param boolean $translated=true use translated names or english
	 * @return array with 2-letter code => name pairs
	 */
	function countries($translated=true)
	{
		if ($translated)
		{
			$this->_translate_countries();

			return $this->countries_translated;
		}
		return $this->country_array;
	}

	/**
	 * Fill and sort the translated countries array
	 *
	 * @internal
	 */
	function _translate_countries()
	{
		if ($this->countries_translated) return;

		$this->countries_translated = $this->country_array;
		// try to translate them and sort alphabetic
		foreach($this->countries_translated as $k => $name)
		{
			if (($translated = lang($name)) != $name.'*')
			{
				$this->countries_translated[$k] = $translated;
			}
		}
		asort($this->countries_translated);
	}
}
