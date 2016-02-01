<?php
	/**************************************************************************\
	* eGroupWare API - Commononly used functions                               *
	* This file written by Dan Kuykendall <seek3r@phpgroupware.org>            *
	* and Joseph Engo <jengo@phpgroupware.org>                                 *
	* and Mark Peters <skeeter@phpgroupware.org>                               *
	* Commononly used functions by phpGroupWare developers                     *
	* Copyright (C) 2000, 2001 Dan Kuykendall                                  *
	* ------------------------------------------------------------------------ *
	* This library is part of the eGroupWare API                               *
	* http://www.egroupware.org                                                *
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

	$d1 = strtolower(@substr(EGW_API_INC,0,3));
	$d2 = strtolower(@substr(EGW_SERVER_ROOT,0,3));
	$d3 = strtolower(@substr(EGW_APP_INC,0,3));
	if($d1 == 'htt' || $d1 == 'ftp' || $d2 == 'htt' || $d2 == 'ftp' || $d3 == 'htt' || $d3 == 'ftp')
	{
		echo 'Failed attempt to break in via an old Security Hole!<br>'."\n";
		exit;
	}
	unset($d1);
	unset($d2);
	unset($d3);

	/**
	* eGroupWare datetime class that contains common date/time functions
	*
	* renamed to egw_datetime to support php5.2
	*
	* @deprecated use egw_time class!!
	*/
	class egw_datetime
	{
		var $zone_offset_list = array(
			'ACT' => '+9:30',
			'AET' => '+10:00',
			'Africa/Abidjan' => '+0.0',
			'Africa/Accra' => '+0.0',
			'Africa/Addis_Ababa' => '+3:00',
			'Africa/Algiers' => '-11:00',
			'Africa/Asmera' => '+3:00',
			'Africa/Bamako' => '+0.0',
			'Africa/Bangui' => '-11:00',
			'Africa/Banjul' => '+0.0',
			'Africa/Bissau' => '+0.0',
			'Africa/Blantyre' => '+2:00',
			'Africa/Brazzaville' => '-11:00',
			'Africa/Bujumbura' => '+2:00',
			'Africa/Cairo' => '+2:00',
			'Africa/Casablanca' => '+0.0',
			'Africa/Ceuta' => '-11:00',
			'Africa/Conakry' => '+0.0',
			'Africa/Dakar' => '+0.0',
			'Africa/Dar_es_Salaam' => '+3:00',
			'Africa/Djibouti' => '+3:00',
			'Africa/Douala' => '-11:00',
			'Africa/El_Aaiun' => '+0.0',
			'Africa/Freetown' => '+0.0',
			'Africa/Gaborone' => '+2:00',
			'Africa/Harare' => '+2:00',
			'Africa/Johannesburg' => '+2:00',
			'Africa/Kampala' => '+3:00',
			'Africa/Khartoum' => '+3:00',
			'Africa/Kigali' => '+2:00',
			'Africa/Kinshasa' => '-11:00',
			'Africa/Lagos' => '-11:00',
			'Africa/Libreville' => '-11:00',
			'Africa/Lome' => '+0.0',
			'Africa/Luanda' => '-11:00',
			'Africa/Lubumbashi' => '+2:00',
			'Africa/Lusaka' => '+2:00',
			'Africa/Malabo' => '-11:00',
			'Africa/Maputo' => '+2:00',
			'Africa/Maseru' => '+2:00',
			'Africa/Mbabane' => '+2:00',
			'Africa/Mogadishu' => '+3:00',
			'Africa/Monrovia' => '+0.0',
			'Africa/Nairobi' => '+3:00',
			'Africa/Ndjamena' => '-11:00',
			'Africa/Niamey' => '-11:00',
			'Africa/Nouakchott' => '+0.0',
			'Africa/Ouagadougou' => '+0.0',
			'Africa/Porto-Novo' => '-11:00',
			'Africa/Sao_Tome' => '+0.0',
			'Africa/Timbuktu' => '+0.0',
			'Africa/Tripoli' => '+2:00',
			'Africa/Tunis' => '-11:00',
			'Africa/Windhoek' => '-11:00',
			'AGT' => '-3:00',
			'America/Adak' => '-10:00',
			'America/Anchorage' => '-8:00',
			'America/Anguilla' => '-3:30',
			'America/Antigua' => '-3:30',
			'America/Araguaina' => '-3:00',
			'America/Aruba' => '-3:30',
			'America/Asuncion' => '-3:30',
			'America/Atka' => '-10:00',
			'America/Barbados' => '-3:30',
			'America/Belem' => '-3:00',
			'America/Belize' => '-6:00',
			'America/Boa_Vista' => '-3:30',
			'America/Bogota' => '-5:00',
			'America/Boise' => '-7:00',
			'America/Buenos_Aires' => '-3:00',
			'America/Cambridge_Bay' => '-7:00',
			'America/Cancun' => '-6:00',
			'America/Caracas' => '-3:30',
			'America/Catamarca' => '-3:00',
			'America/Cayenne' => '-3:00',
			'America/Cayman' => '-5:00',
			'America/Chicago' => '-6:00',
			'America/Chihuahua' => '-7:00',
			'America/Cordoba' => '-3:00',
			'America/Costa_Rica' => '-6:00',
			'America/Cuiaba' => '-3:30',
			'America/Curacao' => '-3:30',
			'America/Danmarkshavn' => '+0.0',
			'America/Dawson' => '-8:00',
			'America/Dawson_Creek' => '-7:00',
			'America/Denver' => '-7:00',
			'America/Detroit' => '-5:00',
			'America/Dominica' => '-3:30',
			'America/Edmonton' => '-7:00',
			'America/Eirunepe' => '-5:00',
			'America/El_Salvador' => '-6:00',
			'America/Ensenada' => '-8:00',
			'America/Fortaleza' => '-3:00',
			'America/Fort_Wayne' => '-5:00',
			'America/Glace_Bay' => '-3:30',
			'America/Godthab' => '-3:00',
			'America/Goose_Bay' => '-3:30',
			'America/Grand_Turk' => '-5:00',
			'America/Grenada' => '-3:30',
			'America/Guadeloupe' => '-3:30',
			'America/Guatemala' => '-6:00',
			'America/Guayaquil' => '-5:00',
			'America/Guyana' => '-3:30',
			'America/Halifax' => '-3:30',
			'America/Havana' => '-5:00',
			'America/Hermosillo' => '-7:00',
			'America/Indiana/Indianapolis' => '-5:00',
			'America/Indiana/Knox' => '-5:00',
			'America/Indiana/Marengo' => '-5:00',
			'America/Indianapolis' => '-5:00',
			'America/Indiana/Vevay' => '-5:00',
			'America/Inuvik' => '-7:00',
			'America/Iqaluit' => '-5:00',
			'America/Jamaica' => '-5:00',
			'America/Jujuy' => '-3:00',
			'America/Juneau' => '-8:00',
			'America/Kentucky/Louisville' => '-5:00',
			'America/Kentucky/Monticello' => '-5:00',
			'America/Knox_IN' => '-5:00',
			'America/La_Paz' => '-3:30',
			'America/Lima' => '-5:00',
			'America/Los_Angeles' => '-8:00',
			'America/Louisville' => '-5:00',
			'America/Maceio' => '-3:00',
			'America/Managua' => '-6:00',
			'America/Manaus' => '-3:30',
			'America/Martinique' => '-3:30',
			'America/Mazatlan' => '-7:00',
			'America/Mendoza' => '-3:00',
			'America/Menominee' => '-6:00',
			'America/Merida' => '-6:00',
			'America/Mexico_City' => '-6:00',
			'America/Miquelon' => '-3:00',
			'America/Monterrey' => '-6:00',
			'America/Montevideo' => '-3:00',
			'America/Montreal' => '-5:00',
			'America/Montserrat' => '-3:30',
			'America/Nassau' => '-5:00',
			'America/New_York' => '-5:00',
			'America/Nipigon' => '-5:00',
			'America/Nome' => '-8:00',
			'America/Noronha' => '-2:00',
			'America/North_Dakota/Center' => '-6:00',
			'America/Panama' => '-5:00',
			'America/Pangnirtung' => '-5:00',
			'America/Paramaribo' => '-3:00',
			'America/Phoenix' => '-7:00',
			'America/Port-au-Prince' => '-5:00',
			'America/Porto_Acre' => '-5:00',
			'America/Port_of_Spain' => '-3:30',
			'America/Porto_Velho' => '-3:30',
			'America/Puerto_Rico' => '-3:30',
			'America/Rainy_River' => '-6:00',
			'America/Rankin_Inlet' => '-6:00',
			'America/Recife' => '-3:00',
			'America/Regina' => '-6:00',
			'America/Rio_Branco' => '-5:00',
			'America/Rosario' => '-3:00',
			'America/Santiago' => '-3:30',
			'America/Santo_Domingo' => '-3:30',
			'America/Sao_Paulo' => '-3:00',
			'America/Scoresbysund' => '-1:00',
			'America/Shiprock' => '-7:00',
			'America/St_Johns' => '-3:30',
			'America/St_Kitts' => '-3:30',
			'America/St_Lucia' => '-3:30',
			'America/St_Thomas' => '-3:30',
			'America/St_Vincent' => '-3:30',
			'America/Swift_Current' => '-6:00',
			'America/Tegucigalpa' => '-6:00',
			'America/Thule' => '-3:30',
			'America/Thunder_Bay' => '-5:00',
			'America/Tijuana' => '-8:00',
			'America/Tortola' => '-3:30',
			'America/Vancouver' => '-8:00',
			'America/Virgin' => '-3:30',
			'America/Whitehorse' => '-8:00',
			'America/Winnipeg' => '-6:00',
			'America/Yakutat' => '-8:00',
			'America/Yellowknife' => '-7:00',
			'Antarctica/Casey' => '+8:00',
			'Antarctica/Davis' => '+7:00',
			'Antarctica/DumontDUrville' => '+10:00',
			'Antarctica/Mawson' => '+6:00',
			'Antarctica/Palmer' => '-3:30',
			'Antarctica/Syowa' => '+3:00',
			'Antarctica/Vostok' => '+6:00',
			'Arctic/Longyearbyen' => '-11:00',
			'ART' => '+2:00',
			'Asia/Aden' => '+3:00',
			'Asia/Almaty' => '+6:00',
			'Asia/Amman' => '+2:00',
			'Asia/Aqtau' => '+4:00',
			'Asia/Aqtobe' => '+5:00',
			'Asia/Ashgabat' => '+5:00',
			'Asia/Ashkhabad' => '+5:00',
			'Asia/Baghdad' => '+3:00',
			'Asia/Bahrain' => '+3:00',
			'Asia/Baku' => '+4:00',
			'Asia/Bangkok' => '+7:00',
			'Asia/Beirut' => '+2:00',
			'Asia/Bishkek' => '+5:00',
			'Asia/Brunei' => '+8:00',
			'Asia/Calcutta' => '+5:30:',
			'Asia/Choibalsan' => '+9:00',
			'Asia/Chongqing' => '+8:00',
			'Asia/Chungking' => '+8:00',
			'Asia/Colombo' => '+6:00',
			'Asia/Dacca' => '+6:00',
			'Asia/Damascus' => '+2:00',
			'Asia/Dhaka' => '+6:00',
			'Asia/Dili' => '+9:00',
			'Asia/Dubai' => '+4:00',
			'Asia/Dushanbe' => '+5:00',
			'Asia/Gaza' => '+2:00',
			'Asia/Harbin' => '+8:00',
			'Asia/Hong_Kong' => '+8:00',
			'Asia/Hovd' => '+7:00',
			'Asia/Irkutsk' => '+8:00',
			'Asia/Istanbul' => '+2:00',
			'Asia/Jakarta' => '+7:00',
			'Asia/Jayapura' => '+9:00',
			'Asia/Jerusalem' => '+2:00',
			'Asia/Kabul' => '+4:30',
			'Asia/Karachi' => '+5:00',
			'Asia/Kashgar' => '+8:00',
			'Asia/Katmandu' => '+5:45',
			'Asia/Krasnoyarsk' => '+7:00',
			'Asia/Kuala_Lumpur' => '+8:00',
			'Asia/Kuching' => '+8:00',
			'Asia/Kuwait' => '+3:00',
			'Asia/Macao' => '+8:00',
			'Asia/Macau' => '+8:00',
			'Asia/Magadan' => '+11:00',
			'Asia/Makassar' => '+8:00',
			'Asia/Manila' => '+8:00',
			'Asia/Muscat' => '+4:00',
			'Asia/Nicosia' => '+2:00',
			'Asia/Novosibirsk' => '+6:00',
			'Asia/Omsk' => '+6:00',
			'Asia/Oral' => '+4:00',
			'Asia/Phnom_Penh' => '+7:00',
			'Asia/Pontianak' => '+7:00',
			'Asia/Pyongyang' => '+9:00',
			'Asia/Qatar' => '+3:00',
			'Asia/Qyzylorda' => '+6:00',
			'Asia/Rangoon' => '+6:30',
			'Asia/Riyadh' => '+3:00',
			'Asia/Saigon' => '+7:00',
			'Asia/Sakhalin' => '+10:00',
			'Asia/Samarkand' => '+5:00',
			'Asia/Seoul' => '+9:00',
			'Asia/Shanghai' => '+8:00',
			'Asia/Singapore' => '+8:00',
			'Asia/Taipei' => '+8:00',
			'Asia/Tashkent' => '+5:00',
			'Asia/Tbilisi' => '+4:00',
			'Asia/Tehran' => '+3:30',
			'Asia/Tel_Aviv' => '+2:00',
			'Asia/Thimbu' => '+6:00',
			'Asia/Thimphu' => '+6:00',
			'Asia/Tokyo' => '+9:00',
			'Asia/Ujung_Pandang' => '+8:00',
			'Asia/Ulaanbaatar' => '+8:00',
			'Asia/Ulan_Bator' => '+8:00',
			'Asia/Urumqi' => '+8:00',
			'Asia/Vientiane' => '+7:00',
			'Asia/Vladivostok' => '+10:00',
			'Asia/Yakutsk' => '+9:00',
			'Asia/Yekaterinburg' => '+5:00',
			'Asia/Yerevan' => '+4:00',
			'AST' => '-8:00',
			'Atlantic/Azores' => '-1:00',
			'Atlantic/Bermuda' => '-3:30',
			'Atlantic/Canary' => '+0.0',
			'Atlantic/Cape_Verde' => '-1:00',
			'Atlantic/Faeroe' => '+0.0',
			'Atlantic/Jan_Mayen' => '-11:00',
			'Atlantic/Madeira' => '+0.0',
			'Atlantic/Reykjavik' => '+0.0',
			'Atlantic/South_Georgia' => '-2:00',
			'Atlantic/Stanley' => '-3:30',
			'Atlantic/St_Helena' => '+0.0',
			'Australia/ACT' => '+10:00',
			'Australia/Adelaide' => '+9:30',
			'Australia/Brisbane' => '+10:00',
			'Australia/Broken_Hill' => '+9:30',
			'Australia/Canberra' => '+10:00',
			'Australia/Darwin' => '+9:30',
			'Australia/Hobart' => '+10:00',
			'Australia/LHI' => '+10:30',
			'Australia/Lindeman' => '+10:00',
			'Australia/Lord_Howe' => '+10:30',
			'Australia/Melbourne' => '+10:00',
			'Australia/North' => '+9:30',
			'Australia/NSW' => '+10:00',
			'Australia/Perth' => '+8:00',
			'Australia/Queensland' => '+10:00',
			'Australia/South' => '+9:30',
			'Australia/Sydney' => '+10:00',
			'Australia/Tasmania' => '+10:00',
			'Australia/Victoria' => '+10:00',
			'Australia/West' => '+8:00',
			'Australia/Yancowinna' => '+9:30',
			'BET' => '-3:00',
			'Brazil/Acre' => '-5:00',
			'Brazil/DeNoronha' => '-2:00',
			'Brazil/East' => '-3:00',
			'Brazil/West' => '-3:30',
			'BST' => '+6:00',
			'Canada/Atlantic' => '-3:30',
			'Canada/Central' => '-6:00',
			'Canada/Eastern' => '-5:00',
			'Canada/East-Saskatchewan' => '-6:00',
			'Canada/Mountain' => '-7:00',
			'Canada/Newfoundland' => '-3:30',
			'Canada/Pacific' => '-8:00',
			'Canada/Saskatchewan' => '-6:00',
			'Canada/Yukon' => '-8:00',
			'CAT' => '+2:00',
			'CET' => '-11:00',
			'Chile/Continental' => '-3:30',
			'Chile/EasterIsland' => '-6:00',
			'CNT' => '-3:30',
			'CST' => '-6:00',
			'CST6CDT' => '-6:00',
			'CTT' => '+8:00',
			'Cuba' => '-5:00',
			'EAT' => '+3:00',
			'ECT' => '-11:00',
			'EET' => '+2:00',
			'Egypt' => '+2:00',
			'Eire' => '+0.0',
			'EST' => '-5:00',
			'EST5EDT' => '-5:00',
			'Etc/GMT' => '+0.0',
			'Etc/-0' => '+0.0',
			'Etc/+0' => '+0.0',
			'Etc/GMT0' => '+0.0',
			'Etc/Greenwich' => '+0.0',
			'Etc/UCT' => '+0.0',
			'Etc/Universal' => '+0.0',
			'Etc/UTC' => '+0.0',
			'Etc/Zulu' => '+0.0',
			'Europe/Amsterdam' => '-11:00',
			'Europe/Andorra' => '-11:00',
			'Europe/Athens' => '+2:00',
			'Europe/Belfast' => '+0.0',
			'Europe/Belgrade' => '-11:00',
			'Europe/Berlin' => '-11:00',
			'Europe/Bratislava' => '-11:00',
			'Europe/Brussels' => '-11:00',
			'Europe/Bucharest' => '+2:00',
			'Europe/Budapest' => '-11:00',
			'Europe/Chisinau' => '+2:00',
			'Europe/Copenhagen' => '-11:00',
			'Europe/Dublin' => '+0.0',
			'Europe/Gibraltar' => '-11:00',
			'Europe/Helsinki' => '+2:00',
			'Europe/Istanbul' => '+2:00',
			'Europe/Kaliningrad' => '+2:00',
			'Europe/Kiev' => '+2:00',
			'Europe/Lisbon' => '+0.0',
			'Europe/Ljubljana' => '-11:00',
			'Europe/London' => '+0.0',
			'Europe/Luxembourg' => '-11:00',
			'Europe/Madrid' => '-11:00',
			'Europe/Malta' => '-11:00',
			'Europe/Minsk' => '+2:00',
			'Europe/Monaco' => '-11:00',
			'Europe/Moscow' => '+3:00',
			'Europe/Nicosia' => '+2:00',
			'Europe/Oslo' => '-11:00',
			'Europe/Paris' => '-11:00',
			'Europe/Prague' => '-11:00',
			'Europe/Riga' => '+2:00',
			'Europe/Rome' => '-11:00',
			'Europe/Samara' => '+4:00',
			'Europe/San_Marino' => '-11:00',
			'Europe/Sarajevo' => '-11:00',
			'Europe/Simferopol' => '+2:00',
			'Europe/Skopje' => '-11:00',
			'Europe/Sofia' => '+2:00',
			'Europe/Stockholm' => '-11:00',
			'Europe/Tallinn' => '+2:00',
			'Europe/Tirane' => '-11:00',
			'Europe/Tiraspol' => '+2:00',
			'Europe/Uzhgorod' => '+2:00',
			'Europe/Vaduz' => '-11:00',
			'Europe/Vatican' => '-11:00',
			'Europe/Vienna' => '-11:00',
			'Europe/Vilnius' => '+2:00',
			'Europe/Warsaw' => '-11:00',
			'Europe/Zagreb' => '-11:00',
			'Europe/Zaporozhye' => '+2:00',
			'Europe/Zurich' => '-11:00',
			'GB' => '+0.0',
			'GB-Eire' => '+0.0',
			'GMT' => '+0.0',
			'GMT0' => '+0.0',
			'+1:00' => '-11:00',
			'-4:00' => '-3:30',
			'-9:00' => '-8:00',
			'Greenwich' => '+0.0',
			'Hongkong' => '+8:00',
			'HST' => '-10:00',
			'Iceland' => '+0.0',
			'IET' => '-5:00',
			'Indian/Antananarivo' => '+3:00',
			'Indian/Chagos' => '+6:00',
			'Indian/Christmas' => '+7:00',
			'Indian/Cocos' => '+6:30',
			'Indian/Comoro' => '+3:00',
			'Indian/Kerguelen' => '+5:00',
			'Indian/Mahe' => '+4:00',
			'Indian/Maldives' => '+5:00',
			'Indian/Mauritius' => '+4:00',
			'Indian/Mayotte' => '+3:00',
			'Indian/Reunion' => '+4:00',
			'Iran' => '+3:30',
			'Israel' => '+2:00',
			'IST' => '+5:30:',
			'Jamaica' => '-5:00',
			'Japan' => '+9:00',
			'JST' => '+9:00',
			'Libya' => '+2:00',
			'MET' => '-11:00',
			'Mexico/BajaNorte' => '-8:00',
			'Mexico/BajaSur' => '-7:00',
			'Mexico/General' => '-6:00',
			'MIT' => '-11:00',
			'MST' => '-7:00',
			'MST7MDT' => '-7:00',
			'Navajo' => '-7:00',
			'NET' => '+4:00',
			'Pacific/Apia' => '-11:00',
			'Pacific/Easter' => '-6:00',
			'Pacific/Efate' => '+11:00',
			'Pacific/Fakaofo' => '-10:00',
			'Pacific/Galapagos' => '-6:00',
			'Pacific/Gambier' => '-8:00',
			'Pacific/Guadalcanal' => '+11:00',
			'Pacific/Guam' => '+10:00',
			'Pacific/Honolulu' => '-10:00',
			'Pacific/Johnston' => '-10:00',
			'Pacific/Kosrae' => '+11:00',
			'Pacific/Marquesas' => '-9:30',
			'Pacific/Midway' => '-11:00',
			'Pacific/Niue' => '-11:00',
			'Pacific/Norfolk' => '+11:30',
			'Pacific/Noumea' => '+11:00',
			'Pacific/Pago_Pago' => '-11:00',
			'Pacific/Palau' => '+9:00',
			'Pacific/Pitcairn' => '-8:00',
			'Pacific/Ponape' => '+11:00',
			'Pacific/Port_Moresby' => '+10:00',
			'Pacific/Rarotonga' => '-10:00',
			'Pacific/Saipan' => '+10:00',
			'Pacific/Samoa' => '-11:00',
			'Pacific/Tahiti' => '-10:00',
			'Pacific/Truk' => '+10:00',
			'Pacific/Yap' => '+10:00',
			'PLT' => '+5:00',
			'PNT' => '-7:00',
			'Poland' => '-11:00',
			'Portugal' => '+0.0',
			'PRC' => '+8:00',
			'PRT' => '-3:30',
			'PST' => '-8:00',
			'PST8PDT' => '-8:00',
			'ROK' => '+9:00',
			'Singapore' => '+8:00',
			'SST' => '+11:00',
			'SystemV/AST4' => '-3:30',
			'SystemV/AST4ADT' => '-3:30',
			'SystemV/CST6' => '-6:00',
			'SystemV/CST6CDT' => '-6:00',
			'SystemV/EST5' => '-5:00',
			'SystemV/EST5EDT' => '-5:00',
			'SystemV/HST10' => '-10:00',
			'SystemV/MST7' => '-7:00',
			'SystemV/MST7MDT' => '-7:00',
			'SystemV/PST8' => '-8:00',
			'SystemV/PST8PDT' => '-8:00',
			'SystemV/YST9' => '-8:00',
			'SystemV/YST9YDT' => '-8:00',
			'Turkey' => '+2:00',
			'UCT' => '+0.0',
			'Universal' => '+0.0',
			'US/Alaska' => '-8:00',
			'US/Aleutian' => '-10:00',
			'US/Arizona' => '-7:00',
			'US/Central' => '-6:00',
			'US/Eastern' => '-5:00',
			'US/East-Indiana' => '-5:00',
			'US/Hawaii' => '-10:00',
			'US/Indiana-Starke' => '-5:00',
			'US/Michigan' => '-5:00',
			'US/Mountain' => '-7:00',
			'US/Pacific' => '-8:00',
			'US/Pacific-New' => '-8:00',
			'US/Samoa' => '-11:00',
			'UTC' => '+0.0',
			'VST' => '+7:00',
			'WET' => '+0.0',
			'W-SU' => '+3:00',
			'Zulu' => '+0.0',
			'GMT-11:00' => '-11:00',
			'GMT-3:30' => '-3:30',
			'GMT-3:30' => '-3:30',
			'GMT-5:00' => '-5:00',
			'GMT-7:00' => '-7:00',
			'GMT-8:00' => '-8:00'
		);
		var $tz_offset;
		var $days = Array();
		var $days_short = Array();
		var $gmtnow = 0;
		var $users_localtime;
		var $cv_gmtdate;

		/**
		 * Constructor of the renamed class
		 */
		function __construct()
		{
			$this->tz_offset = 3600 * @$GLOBALS['egw_info']['user']['preferences']['common']['tz_offset'];
			print_debug('datetime::datetime::gmtnow',$this->gmtnow,'api');

			// If we already have a GMT time, no need to do this again.
			if(!$this->gmtnow)
			{
				if(isset($GLOBALS['egw_info']['server']['tz_offset']))
				{
					$this->gmtnow = time() - ((int)$GLOBALS['egw_info']['server']['tz_offset'] * 3600);
					print_debug('datetime::datetime::tz_offset',"set via tz_offset=".$GLOBALS['egw_info']['server']['tz_offset'].": gmtnow=".date('Y/m/d H:i',$this->gmtnow),'api');
				}
				else
				{
					$this->gmtnow = time() - date('Z');
					print_debug('datetime::datetime::bestguess',"set via date('Z')=".date('Z').": gmtnow=".date('Y/m/d H:i',$this->gmtnow),'api');
				}
			}
			$this->users_localtime = time() + $this->tz_offset;
		}

		/**
		 * Calling the constructor of the renamed class
		 *
		 * @return egw_datetime
		 */
		function datetime()
		{
			return $this->__construct();
		}

		function convert_rfc_to_epoch($date_str)
		{
			$comma_pos = strpos($date_str,',');
			if($comma_pos)
			{
				$date_str = substr($date_str,$comma_pos+1);
			}

			// This may need to be a reference to the different months in native tongue....
			$month = array(
				'Jan' => 1,
				'Feb' => 2,
				'Mar' => 3,
				'Apr' => 4,
				'May' => 5,
				'Jun' => 6,
				'Jul' => 7,
				'Aug' => 8,
				'Sep' => 9,
				'Oct' => 10,
				'Nov' => 11,
				'Dec' => 12
			);
			$dta = array();
			$ta = array();

			// Convert "15 Jul 2000 20:50:22 +0200" to unixtime
			$dta = explode(' ',$date_str);
			$ta = explode(':',$dta[4]);

			if(substr($dta[5],0,3) <> 'GMT')
			{
				$tzoffset = substr($dta[5],0,1);
				$tzhours = (int)substr($dta[5],1,2);
				$tzmins = (int)substr($dta[5],3,2);
				switch ($tzoffset)
				{
					case '-':
						(int)$ta[0] += $tzhours;
						(int)$ta[1] += $tzmins;
						break;
					case '+':
						(int)$ta[0] -= $tzhours;
						(int)$ta[1] -= $tzmins;
						break;
				}
			}
			return mktime($ta[0],$ta[1],$ta[2],$month[$dta[2]],$dta[1],$dta[3]);
		}

		function get_weekday_start($year,$month,$day)
		{
			$weekday = $this->day_of_week($year,$month,$day);
			switch($GLOBALS['egw_info']['user']['preferences']['calendar']['weekdaystarts'])
			{
				// Saturday is for arabic support
				case 'Saturday':
					$this->days = Array(
						0 => 'Sat',
						1 => 'Sun',
						2 => 'Mon',
						3 => 'Tue',
						4 => 'Wed',
						5 => 'Thu',
						6 => 'Fri'
					);
					$this->days_short = Array(
						0 => 'Sa',
						1 => 'Su',
						2 => 'Mo',
						3 => 'Tu',
						4 => 'We',
						5 => 'Th',
						6 => 'Fr'
					);
					switch($weekday)
					{
						case 0:
							$sday = mktime(2,0,0,$month,$day - 1,$year);
							break;
						case 6:
							$sday = mktime(2,0,0,$month,$day,$year);
							break;
						default:
							$sday = mktime(2,0,0,$month,$day - ($weekday + 1),$year);
							break;
					}
					break;
				case 'Monday':
					$this->days = Array(
						0 => 'Mon',
						1 => 'Tue',
						2 => 'Wed',
						3 => 'Thu',
						4 => 'Fri',
						5 => 'Sat',
						6 => 'Sun'
					);
					$this->days_short = Array(
						0 => 'Mo',
						1 => 'Tu',
						2 => 'We',
						3 => 'Th',
						4 => 'Fr',
						5 => 'Sa',
						6 => 'Su'
					);
					switch($weekday)
					{
						case 0:
							$sday = mktime(2,0,0,$month,$day - 6,$year);
							break;
						case 1:
							$sday = mktime(2,0,0,$month,$day,$year);
							break;
						default:
							$sday = mktime(2,0,0,$month,$day - ($weekday - 1),$year);
							break;
					}
					break;
				case 'Sunday':
				default:
					$this->days = Array(
						0 => 'Sun',
						1 => 'Mon',
						2 => 'Tue',
						3 => 'Wed',
						4 => 'Thu',
						5 => 'Fri',
						6 => 'Sat'
					);
					$this->days_short = Array(
						0 => 'Su',
						1 => 'Mo',
						2 => 'Tu',
						3 => 'We',
						4 => 'Th',
						5 => 'Fr',
						6 => 'Sa'
					);
					$sday = mktime(2,0,0,$month,$day - $weekday,$year);
					break;
			}
			return $sday - 7200;
		}

		function is_leap_year($year)
		{
			if (((int)$year % 4 == 0) && ((int)$year % 100 != 0) || ((int)$year % 400 == 0))
			{
				return 1;
			}
			else
			{
				return 0;
			}
		}

		function days_in_month($month,$year)
		{
			$days = Array(
				1  => 31,
				2  => 28 + $this->is_leap_year((int)$year),
				3  => 31,
				4  => 30,
				5  => 31,
				6  => 30,
				7  => 31,
				8  => 31,
				9  => 30,
				10 => 31,
				11 => 30,
				12 => 31
			);
			return $days[(int)$month];
		}

		function date_valid($year,$month,$day)
		{
			return checkdate((int)$month,(int)$day,(int)$year);
		}

		function time_valid($hour,$minutes,$seconds)
		{
			if((int)$hour < 0 || (int)$hour > 24)
			{
				return False;
			}
			if((int)$minutes < 0 || (int)$minutes > 59)
			{
				return False;
			}
			if((int)$seconds < 0 || (int)$seconds > 59)
			{
				return False;
			}

			return True;
		}

		function day_of_week($year,$month,$day)
		{
			if($month > 2)
			{
				$month -= 2;
			}
			else
			{
				$month += 10;
				$year--;
			}
			$day = (floor((13 * $month - 1) / 5) + $day + ($year % 100) + floor(($year % 100) / 4) + floor(($year / 100) / 4) - 2 * floor($year / 100) + 77);
			return (($day - 7 * floor($day / 7)));
		}

		function day_of_year($year,$month,$day)
		{
			$days = array(0,31,59,90,120,151,181,212,243,273,304,334);

			$julian = ($days[$month - 1] + $day);

			if($month > 2 && $this->is_leap_year($year))
			{
				$julian++;
			}
			return($julian);
		}

		/*!
		@function days_between
		@abstract Get the number of days between two dates
		@author Steven Cramer/Ralf Becker
		@param $m1 - Month_1, $d1 - Day_1, $y1 - Year_1, $m2 - Month_2, $d2 - Day_2, $y2 - Year_2
		@note the last param == 0, ensures that the calculation is always done without daylight-saveing
		*/
		function days_between($m1,$d1,$y1,$m2,$d2,$y2)
		{
			return (int)((mktime(0,0,0,$m2,$d2,$y2,0) - mktime(0,0,0,$m1,$d1,$y1,0)) / 86400);
		}

		function date_compare($a_year,$a_month,$a_day,$b_year,$b_month,$b_day)
		{
			$a_date = mktime(0,0,0,(int)$a_month,(int)$a_day,(int)$a_year);
			$b_date = mktime(0,0,0,(int)$b_month,(int)$b_day,(int)$b_year);
			if($a_date == $b_date)
			{
				return 0;
			}
			elseif($a_date > $b_date)
			{
				return 1;
			}
			elseif($a_date < $b_date)
			{
				return -1;
			}
		}

		function time_compare($a_hour,$a_minute,$a_second,$b_hour,$b_minute,$b_second)
		{
			// I use the 1970/1/2 to compare the times, as the 1. can get via TZ-offest still
			// before 1970/1/1, which is the earliest date allowed on windows
			$a_time = mktime((int)$a_hour,(int)$a_minute,(int)$a_second,1,2,1970);
			$b_time = mktime((int)$b_hour,(int)$b_minute,(int)$b_second,1,2,1970);
			if($a_time == $b_time)
			{
				return 0;
			}
			elseif($a_time > $b_time)
			{
				return 1;
			}
			elseif($a_time < $b_time)
			{
				return -1;
			}
		}

		function makegmttime($hour,$minute,$second,$month,$day,$year)
		{
			return $this->gmtdate(mktime($hour, $minute, $second, $month, $day, $year));
		}

		// Note common:show_date converts server- to user-time, before it returns the requested format !!!
		function localdates($localtime)
		{
			$date = Array('raw','day','month','year','full','dow','dm','bd');
			$date['raw'] = $localtime;
			$date['year'] = (int)$GLOBALS['egw']->common->show_date($date['raw'],'Y');
			$date['month'] = (int)$GLOBALS['egw']->common->show_date($date['raw'],'m');
			$date['day'] = (int)$GLOBALS['egw']->common->show_date($date['raw'],'d');
			$date['full'] = (int)$GLOBALS['egw']->common->show_date($date['raw'],'Ymd');
			$date['bd'] = mktime(0,0,0,$date['month'],$date['day'],$date['year']);
			$date['dm'] = (int)$GLOBALS['egw']->common->show_date($date['raw'],'dm');
			$date['dow'] = $this->day_of_week($date['year'],$date['month'],$date['day']);
			$date['hour'] = (int)$GLOBALS['egw']->common->show_date($date['raw'],'H');
			$date['minute'] = (int)$GLOBALS['egw']->common->show_date($date['raw'],'i');
			$date['second'] = (int)$GLOBALS['egw']->common->show_date($date['raw'],'s');

			return $date;
		}

		function gmtdate($localtime)
		{
			return $this->localdates($localtime - $this->tz_offset);
		}
	}
