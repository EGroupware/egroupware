<?php
/**************************************************************************\
* eGroupWare - Setup                                                       *
* http://www.egroupware.org                                                *
* --------------------------------------------                             *
*  This program is free software; you can redistribute it and/or modify it *
*  under the terms of the GNU General Public License as published by the   *
*  Free Software Foundation; either version 2 of the License, or (at your  *
*  option) any later version.                                              *
\**************************************************************************/

/* $Id$ */

foreach(array(
	'aa' => 'Afar',
	'ab' => 'Abkhazian',
	'af' => 'Afrikaans',
	'am' => 'Amharic',
	'ar' => 'Arabic',
	'as' => 'Assamese',
	'ay' => 'Aymara',
	'az' => 'Azerbaijani',
	'ba' => 'Bashkir',
	'be' => 'Byelorussian',
	'bg' => 'Bulgarian',
	'bh' => 'Bihari',
	'bi' => 'Bislama',
	'bn' => 'Bengali / Bangla',
	'bo' => 'Tibetan',
	'br' => 'Breton',
	'ca' => 'Catalan',
	'co' => 'Corsican',
	'cs' => 'Czech',
	'cy' => 'Welsh',
	'da' => 'Danish',
	'de' => 'German',
	'dz' => 'Bhutani',
	'el' => 'Greek',
	'en' => 'English / US',
	'eo' => 'Esperanto',
	'es-es' => 'Espa&ntilde;ol',
	'et' => 'Estonian',
	'eu' => 'Basque',
	'fa' => 'Persian',
	'fi' => 'Finnish',
	'fj' => 'Fiji',
	'fo' => 'Faeroese',
	'fr' => 'French',
	'fy' => 'Frisian',
	'ga' => 'Irish',
	'gd' => 'Gaelic / Scots Gaelic',
	'gl' => 'Galician',
	'gn' => 'Guarani',
	'gu' => 'Gujarati',
	'ha' => 'Hausa',
	'hi' => 'Hindi',
	'hr' => 'Croatian',
	'hu' => 'Hungarian',
	'hy' => 'Armenian',
	'ia' => 'Interlingua',
	'ie' => 'Interlingue',
	'ik' => 'Inupiak',
	'in' => 'Indonesian',
	'is' => 'Icelandic',
	'it' => 'Italian',
	'iw' => 'Hebrew',
	'ja' => 'Japanese',
	'ji' => 'Yiddish',
	'jw' => 'Javanese',
	'ka' => 'Georgian',
	'kk' => 'Kazakh',
	'kl' => 'Greenlandic',
	'km' => 'Cambodian',
	'kn' => 'Kannada',
	'ko' => 'Korean',
	'ks' => 'Kashmiri',
	'ku' => 'Kurdish',
	'ky' => 'Kirghiz',
	'la' => 'Latin',
	'ln' => 'Lingala',
	'lo' => 'Laothian',
	'lt' => 'Lithuanian',
	'lv' => 'Latvian / Lettish',
	'mg' => 'Malagasy',
	'mi' => 'Maori',
	'mk' => 'Macedonian',
	'ml' => 'Malayalam',
	'mn' => 'Mongolian',
	'mo' => 'Moldavian',
	'mr' => 'Marathi',
	'ms' => 'Malay',
	'mt' => 'Maltese',
	'my' => 'Burmese',
	'na' => 'Nauru',
	'ne' => 'Nepali',
	'nl' => 'Dutch',
	'no' => 'Norwegian',
	'oc' => 'Occitan',
	'om' => 'Oromo / Afan',
	'or' => 'Oriya',
	'pa' => 'Punjabi',
	'pl' => 'Polish',
	'ps' => 'Pashto / Pushto',
	'pt' => 'Portuguese',
	'pt-br' => 'Brazil',
	'qu' => 'Quechua',
	'rm' => 'Rhaeto-Romance',
	'rn' => 'Kirundi',
	'ro' => 'Romanian',
	'ru' => 'Russian',
	'rw' => 'Kinyarwanda',
	'sa' => 'Sanskrit',
	'sd' => 'Sindhi',
	'sg' => 'Sangro',
	'sh' => 'Serbo-Croatian',
	'si' => 'Singhalese',
	'sk' => 'Slovak',
	'sl' => 'Slovenian',
	'sm' => 'Samoan',
	'sn' => 'Shona',
	'so' => 'Somali',
	'sq' => 'Albanian',
	'sr' => 'Serbian',
	'ss' => 'Siswati',
	'st' => 'Sesotho',
	'su' => 'Sudanese',
	'sv' => 'Swedish',
	'sw' => 'Swahili',
	'ta' => 'Tamil',
	'te' => 'Tegulu',
	'tg' => 'Tajik',
	'th' => 'Thai',
	'ti' => 'Tigrinya',
	'tk' => 'Turkmen',
	'tl' => 'Tagalog',
	'tn' => 'Setswana',
	'to' => 'Tonga',
	'tr' => 'Turkish',
	'ts' => 'Tsonga',
	'tt' => 'Tatar',
	'tw' => 'Twi',
	'uk' => 'Ukrainian',
	'ur' => 'Urdu',
	'uz' => 'Uzbek',
	'vi' => 'Vietnamese',
	'vo' => 'Volapuk',
	'wo' => 'Wolof',
	'xh' => 'Xhosa',
	'yo' => 'Yoruba',
	'zh' => 'Chinese(simplified)',
	'zt' => 'Chinese(Taiwan)',
	'zu' => 'Zulu',
) as $id => $name)
{
	$oProc->insert($GLOBALS['egw_setup']->languages_table,array('lang_name' => $name),array('lang_id' => $id),__LINE__,__FILLE_);
}
	
foreach(array(
	'sessions_checkip' => 'True',
	'image_type'       => '1',
	'asyncservice'     => 'fallback',
) as $name => $value)
{
	$oProc->insert($GLOBALS['egw_setup']->config_table,array(
		'config_value' => $value,
	),array(
		'config_app' => 'phpgwapi',
		'config_name' => $name,
	),__LINE__,__FILE__);
}

$oProc->query("INSERT INTO phpgw_interserv(server_name,server_host,server_url,trust_level,trust_rel,server_mode) VALUES ('eGW demo',NULL,'http://www.egroupware.org/egroupware/xmlrpc.php',99,0,'xmlrpc')");

// insert the VFS basedir /home
$oProc->query ("INSERT INTO egw_vfs (owner_id, createdby_id, modifiedby_id, created, modified, size, mime_type, deleteable, comment, app, directory, name, link_directory, link_name) VALUES (0,0,0,'1970-01-01',NULL,NULL,'Directory','Y',NULL,NULL,'/','', NULL, NULL)");
$oProc->query ("INSERT INTO egw_vfs (owner_id, createdby_id, modifiedby_id, created, modified, size, mime_type, deleteable, comment, app, directory, name, link_directory, link_name) VALUES (0,0,0,'1970-01-01',NULL,NULL,'Directory','Y',NULL,NULL,'/','home', NULL, NULL)");

/*************************************************************************\
 *                    Default Records for VFS v2                         *
\*************************************************************************/
if ($GLOBALS['DEBUG'])
{
	echo "<br>\n<b>initiating to create the default records for VFS SQL2...";
}

include EGW_INCLUDE_ROOT.'/phpgwapi/setup/default_records_mime.inc.php';

$oProc->query("INSERT INTO phpgw_vfs2_files (mime_id,owner_id,createdby_id,size,directory,name)
			   SELECT mime_id,0,0,4096,'/' => '' FROM phpgw_vfs2_mimetypes WHERE mime='Directory'");

if ($GLOBALS['DEBUG'])
{
	echo " DONE!</b>";
}
/*************************************************************************/
