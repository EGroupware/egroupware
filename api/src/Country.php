<?php
/**
 * EGroupware API - Country codes
 *
 * @link http://www.egroupware.org
 * @author Mark Peters <skeeter@phpgroupware.org>
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage country
 * @access public
 * @version $Id$
 */

namespace EGroupware\Api;

/**
 * 2-digit ISO 3166 Country codes
 *
 * All methods are static now, no need to instanciate it via $GLOBALS['egw']->country->method(),
 * just use Api\Country::method().
 *
 * @see http://www.iso.ch/iso/en/prods-services/iso3166ma/02iso-3166-code-lists/list-en1.html
 * @see https://github.com/datasets/country-list
 */
class Country
{
	/**
	 * array with 2-letter iso-3166 country-code => country-name pairs
	 *
	 * @var array
	 */
	protected static $country_array = array(
		'AF' => 'Afghanistan',
		'AX' => 'Åland Islands',
		'AL' => 'Albania',
		'DZ' => 'Algeria',
		'AS' => 'American Samoa',
		'AD' => 'Andorra',
		'AO' => 'Angola',
		'AI' => 'Anguilla',
		'AQ' => 'Antarctica',
		'AG' => 'Antigua and Barbuda',
		'AR' => 'Argentina',
		'AM' => 'Armenia',
		'AW' => 'Aruba',
		'AU' => 'Australia',
		'AT' => 'Austria',
		'AZ' => 'Azerbaijan',
		'BS' => 'Bahamas',
		'BH' => 'Bahrain',
		'BD' => 'Bangladesh',
		'BB' => 'Barbados',
		'BY' => 'Belarus',
		'BE' => 'Belgium',
		'BZ' => 'Belize',
		'BJ' => 'Benin',
		'BM' => 'Bermuda',
		'BT' => 'Bhutan',
		'BO' => 'Bolivia, Plurinational State of',
		'BQ' => 'Bonaire, Sint Eustatius and Saba',
		'BA' => 'Bosnia and Herzegovina',
		'BW' => 'Botswana',
		'BV' => 'Bouvet Island',
		'BR' => 'Brazil',
		'IO' => 'British Indian Ocean Territory',
		'BN' => 'Brunei Darussalam',
		'BG' => 'Bulgaria',
		'BF' => 'Burkina Faso',
		'BI' => 'Burundi',
		'KH' => 'Cambodia',
		'CM' => 'Cameroon',
		'CA' => 'Canada',
		'CV' => 'Cape Verde',
		'KY' => 'Cayman Islands',
		'CF' => 'Central African Republic',
		'TD' => 'Chad',
		'CL' => 'Chile',
		'CN' => 'China',
		'CX' => 'Christmas Island',
		'CC' => 'Cocos (Keeling) Islands',
		'CO' => 'Colombia',
		'KM' => 'Comoros',
		'CG' => 'Congo',
		'CD' => 'Congo, the Democratic Republic of the',
		'CK' => 'Cook Islands',
		'CR' => 'Costa Rica',
		'CI' => "Côte d'Ivoire",
		'HR' => 'Croatia',
		'CU' => 'Cuba',
		'CW' => 'Curaçao',
		'CY' => 'Cyprus',
		'CZ' => 'Czech Republic',
		'DK' => 'Denmark',
		'DJ' => 'Djibouti',
		'DM' => 'Dominica',
		'DO' => 'Dominican Republic',
		'EC' => 'Ecuador',
		'EG' => 'Egypt',
		'SV' => 'El Salvador',
		'GQ' => 'Equatorial Guinea',
		'ER' => 'Eritrea',
		'EE' => 'Estonia',
		'ET' => 'Ethiopia',
		'FK' => 'Falkland Islands (Malvinas)',
		'FO' => 'Faroe Islands',
		'FJ' => 'Fiji',
		'FI' => 'Finland',
		'FR' => 'France',
		'GF' => 'French Guiana',
		'PF' => 'French Polynesia',
		'TF' => 'French Southern Territories',
		'GA' => 'Gabon',
		'GM' => 'Gambia',
		'GE' => 'Georgia',
		'DE' => 'Germany',
		'GH' => 'Ghana',
		'GI' => 'Gibraltar',
		'GR' => 'Greece',
		'GL' => 'Greenland',
		'GD' => 'Grenada',
		'GP' => 'Guadeloupe',
		'GU' => 'Guam',
		'GT' => 'Guatemala',
		'GG' => 'Guernsey',
		'GN' => 'Guinea',
		'GW' => 'Guinea-Bissau',
		'GY' => 'Guyana',
		'HT' => 'Haiti',
		'HM' => 'Heard Island and McDonald Islands',
		'VA' => 'Holy See (Vatican City State)',
		'HN' => 'Honduras',
		'HK' => 'Hong Kong',
		'HU' => 'Hungary',
		'IS' => 'Iceland',
		'IN' => 'India',
		'ID' => 'Indonesia',
		'IR' => 'Iran, Islamic Republic of',
		'IQ' => 'Iraq',
		'IE' => 'Ireland',
		'IM' => 'Isle of Man',
		'IL' => 'Israel',
		'IT' => 'Italy',
		'JM' => 'Jamaica',
		'JP' => 'Japan',
		'JE' => 'Jersey',
		'JO' => 'Jordan',
		'KZ' => 'Kazakhstan',
		'KE' => 'Kenya',
		'KI' => 'Kiribati',
		'KP' => "Korea, Democratic People's Republic of",
		'KR' => 'Korea, Republic of',
		'KW' => 'Kuwait',
		'KG' => 'Kyrgyzstan',
		'LA' => "Lao People's Democratic Republic",
		'LV' => 'Latvia',
		'LB' => 'Lebanon',
		'LS' => 'Lesotho',
		'LR' => 'Liberia',
		'LY' => 'Libya',
		'LI' => 'Liechtenstein',
		'LT' => 'Lithuania',
		'LU' => 'Luxembourg',
		'MO' => 'Macao',
		'MK' => 'Macedonia, the Former Yugoslav Republic of',
		'MG' => 'Madagascar',
		'MW' => 'Malawi',
		'MY' => 'Malaysia',
		'MV' => 'Maldives',
		'ML' => 'Mali',
		'MT' => 'Malta',
		'MH' => 'Marshall Islands',
		'MQ' => 'Martinique',
		'MR' => 'Mauritania',
		'MU' => 'Mauritius',
		'YT' => 'Mayotte',
		'MX' => 'Mexico',
		'FM' => 'Micronesia, Federated States of',
		'MD' => 'Moldova, Republic of',
		'MC' => 'Monaco',
		'MN' => 'Mongolia',
		'ME' => 'Montenegro',
		'MS' => 'Montserrat',
		'MA' => 'Morocco',
		'MZ' => 'Mozambique',
		'MM' => 'Myanmar',
		'NA' => 'Namibia',
		'NR' => 'Nauru',
		'NP' => 'Nepal',
		'NL' => 'Netherlands',
		'NC' => 'New Caledonia',
		'NZ' => 'New Zealand',
		'NI' => 'Nicaragua',
		'NE' => 'Niger',
		'NG' => 'Nigeria',
		'NU' => 'Niue',
		'NF' => 'Norfolk Island',
		'MP' => 'Northern Mariana Islands',
		'NO' => 'Norway',
		'OM' => 'Oman',
		'PK' => 'Pakistan',
		'PW' => 'Palau',
		'PS' => 'Palestine, State of',
		'PA' => 'Panama',
		'PG' => 'Papua New Guinea',
		'PY' => 'Paraguay',
		'PE' => 'Peru',
		'PH' => 'Philippines',
		'PN' => 'Pitcairn',
		'PL' => 'Poland',
		'PT' => 'Portugal',
		'PR' => 'Puerto Rico',
		'QA' => 'Qatar',
		'RE' => 'Réunion',
		'RO' => 'Romania',
		'RU' => 'Russian Federation',
		'RW' => 'Rwanda',
		'BL' => 'Saint Barthélemy',
		'SH' => 'Saint Helena, Ascension and Tristan da Cunha',
		'KN' => 'Saint Kitts and Nevis',
		'LC' => 'Saint Lucia',
		'MF' => 'Saint Martin (French part)',
		'PM' => 'Saint Pierre and Miquelon',
		'VC' => 'Saint Vincent and the Grenadines',
		'WS' => 'Samoa',
		'SM' => 'San Marino',
		'ST' => 'Sao Tome and Principe',
		'SA' => 'Saudi Arabia',
		'SN' => 'Senegal',
		'RS' => 'Serbia',
		'SC' => 'Seychelles',
		'SL' => 'Sierra Leone',
		'SG' => 'Singapore',
		'SX' => 'Sint Maarten (Dutch part)',
		'SK' => 'Slovakia',
		'SI' => 'Slovenia',
		'SB' => 'Solomon Islands',
		'SO' => 'Somalia',
		'ZA' => 'South Africa',
		'GS' => 'South Georgia and the South Sandwich Islands',
		'SS' => 'South Sudan',
		'ES' => 'Spain',
		'LK' => 'Sri Lanka',
		'SD' => 'Sudan',
		'SR' => 'Suriname',
		'SJ' => 'Svalbard and Jan Mayen',
		'SZ' => 'Swaziland',
		'SE' => 'Sweden',
		'CH' => 'Switzerland',
		'SY' => 'Syrian Arab Republic',
		'TW' => 'Taiwan, Province of China',
		'TJ' => 'Tajikistan',
		'TZ' => 'Tanzania, United Republic of',
		'TH' => 'Thailand',
		'TL' => 'Timor-Leste',
		'TG' => 'Togo',
		'TK' => 'Tokelau',
		'TO' => 'Tonga',
		'TT' => 'Trinidad and Tobago',
		'TN' => 'Tunisia',
		'TR' => 'Turkey',
		'TM' => 'Turkmenistan',
		'TC' => 'Turks and Caicos Islands',
		'TV' => 'Tuvalu',
		'UG' => 'Uganda',
		'UA' => 'Ukraine',
		'AE' => 'United Arab Emirates',
		'GB' => 'United Kingdom',
		'US' => 'United States',
		'UM' => 'United States Minor Outlying Islands',
		'UY' => 'Uruguay',
		'UZ' => 'Uzbekistan',
		'VU' => 'Vanuatu',
		'VE' => 'Venezuela, Bolivarian Republic of',
		'VN' => 'Viet Nam',
		'VG' => 'Virgin Islands, British',
		'VI' => 'Virgin Islands, U.S.',
		'WF' => 'Wallis and Futuna',
		'EH' => 'Western Sahara',
		'YE' => 'Yemen',
		'ZM' => 'Zambia',
		'ZW' => 'Zimbabwe',
	);
	/**
	 * translated list, set by country::_translate
	 *
	 * @var array
	 */
	protected static $countries_translated;
	/**
	 * List of US states as 2-letter code => name pairs
	 *
	 * @var array
	 */
	protected static $us_states_array = array(
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
	 * List of DE states as 2-letter code => name pairs
	 *
	 * @var array
	 */
	protected static $de_states_array = array(
		'BW' =>	'Baden-Württemberg',
		'BY' =>	'Bayern',
		'BE' =>	'Berlin',
		'BB' =>	'Brandenburg',
		'HB' =>	'Bremen',
		'HH' =>	'Hamburg',
		'HE' =>	'Hessen',
		'MV' =>	'Mecklenburg-Vorpommern',
		'NI' =>	'Niedersachsen',
		'NW' =>	'Nordrhein-Westfalen',
		'RP' =>	'Rheinland-Pfalz',
		'SL' =>	'Saarland',
		'SN' =>	'Sachsen',
		'ST' =>	'Sachsen-Anhalt',
		'SH' =>	'Schleswig-Holstein',
		'TH' =>	'Thüringen'
	);

	/**
	 * List of CH states as 2-letter code => name pairs
	 *
	 * @var array
	 */
	protected static $ch_states_array = array(
		'AG' =>	'Aargau',
		'AR' =>	'Appenzell Ausserrhoden',
		'AI' =>	'Appenzell Innerrhoden',
		'BL' =>	'Basel-Landschaft',
		'BS' =>	'Basel-Stadt',
		'BE' =>	'Bern',
		'FR' =>	'Freiburg',
		'GE' =>	'Genève',
		'GL' =>	'Glarus',
		'GR' =>	'Graubünden',
		'JU' =>	'Jura',
		'LU' =>	'Luzern',
		'NE' =>	'Neuchâtel',
		'NW' =>	'Nidwalden',
		'OW' =>	'Obwalden',
		'SG' =>	'Sankt Gallen',
		'SH' =>	'Schaffhausen',
		'SZ' =>	'Schwyz',
		'SO' =>	'Solothurn',
		'TG' =>	'Thurgau',
		'TI' =>	'Ticino',
		'UR' =>	'Uri',
		'VS' =>	'Wallis',
		'VD' =>	'Vaud',
		'ZG' =>	'Zug',
		'ZH' =>	'Zürich',
	);

	/**
	 * List of AT states as 2-letter code => name pairs
	 *
	 * @var array
	 */
	protected static $at_states_array = array(
		'1' =>	'Burgenland',
		'2' =>	'Kärnten',
		'3' =>	'Niederösterreich',
		'4' =>	'Oberösterreich',
		'5'	=>	'Salzburg',
		'6'	=>	'Steiermark',
		'7'	=>	'Tirol',
		'8'	=>	'Vorarlberg',
		'9'	=>	'Wien'
	);

	/**
	 * Get list of US states
	 * @param string $country = de selected country code to fetch its states
	 *
	 * @return array with code => name pairs
	 */
	public static function get_states($country='de')
	{
		switch(strtolower($country))
		{
			case 'us':
				return self::$us_states_array;
			case 'de':
				return self::$de_states_array;
			case 'at':
				return self::$at_states_array;
			case 'ch':
				return self::$ch_states_array;
		}
	}

	/**
	 * Get list of US states
	 *
	 * @return array with code => name pairs
	 */
	public static function us_states()
	{
		return self::$us_states_array;
	}

	/**
	 * Get country-name from the 2-letter iso code
	 *
	 * @param string $code 2-letter iso country-code
	 * @param boolean $translated =true use translated name or english
	 * @return string
	 */
	public static function get_full_name($code,$translated=true)
	{
		if ($translated)
		{
			if (!self::$countries_translated) self::_translate_countries();

			return self::$countries_translated[strtoupper($code)];
		}
		return self::$country_array[strtoupper($code)];
	}

	/**
	 * Get the 2-letter code for a given country name
	 *
	 * @param string $name
	 * @return string 2-letter code or $name if no code found
	 */
	public static function country_code($name)
	{
		if (!$name) return '';	// nothing to do

		if (strlen($name) == 2 && isset(self::$country_array[$name]))
		{
			return $name;	// $name is already a country-code
		}

		if (($code = array_search(strtoupper($name),self::$country_array)) !== false)
		{
			return $code;
		}
		if (!self::$countries_translated) self::_translate_countries();

		if (($code = array_search(strtoupper($name),self::$countries_translated)) !== false ||
			($code = array_search($name,self::$countries_translated)) !== false)
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
		elseif (($name_en = Translation::get_message_id($name,'common')))
		{
			$name = $en_names[$name] = strtoupper($name_en);
		}
		if (($code = array_search(strtoupper($name),self::$country_array)) !== false)
		{
			return $code;
		}
		return $name;
	}

	/**
	 * Get list of country names
	 *
	 * @param boolean $translated =true use translated names or english
	 * @return array with 2-letter code => name pairs
	 */
	public static function countries($translated=true)
	{
		if ($translated)
		{
			if (!self::$countries_translated) self::_translate_countries();

			return self::$countries_translated;
		}
		return self::$country_array;
	}

	/**
	 * Fill and sort the translated countries array
	 *
	 * @internal
	 */
	protected static function _translate_countries()
	{
		if (self::$countries_translated) return;

		self::$countries_translated = self::$country_array;
		// try to translate them and sort alphabetic
		foreach(self::$countries_translated as $k => $name)
		{
			self::$countries_translated[$k] = lang($name);
		}

		if(class_exists('Collator') && class_exists('Locale'))
		{
			$col = new \Collator(Preferences::setlocale());
			$col->asort(self::$countries_translated);
		}
		else
		{
			natcasesort(self::$countries_translated);
		}
	}
}
