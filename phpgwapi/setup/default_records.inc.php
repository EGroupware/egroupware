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

//$oProc->m_odb->Halt_On_Error = 'yes';

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
	'en' => 'English',
	'eo' => 'Esperanto',
	'es-es' => 'Español',
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
	'id' => 'Indonesian',
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
	'zh-tw' => 'Chinese(Taiwan)',
	'zu' => 'Zulu',
) as $id => $name)
{
	$oProc->insert($GLOBALS['egw_setup']->languages_table,array('lang_name' => $name),array('lang_id' => $id),__LINE__,__FILE__);
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

$oProc->query("INSERT INTO egw_interserv(server_name,server_host,server_url,trust_level,trust_rel,server_mode) VALUES ('eGW demo',NULL,'http://www.egroupware.org/egroupware/xmlrpc.php',99,0,'xmlrpc')");

// make sure the required sqlfs dirs are there and have the following id's
$dirs = array(
	'/' => 1,
	'/home' => 2,
	'/apps' => 3,
);
foreach($dirs as $path => $id)
{
	$nrow = array(
		'fs_id' => $id,
		'fs_dir'  => $path == '/' ? 0 : $dirs['/'],
		'fs_name' => substr($path,1),
		'fs_mode' => 05,
		'fs_uid' => 0,
		'fs_gid' => 0,
		'fs_created' => time(),
		'fs_modified' => time(),
		'fs_mime' => 'httpd/unix-directory',
		'fs_size' => 0,
		'fs_creator' => 0,
		'fs_modifier' => 0,
		'fs_comment' => null,
		'fs_content' => null,
	);
	$GLOBALS['egw_setup']->db->insert('egw_sqlfs',$nrow,false,__LINE__,__FILE__,'phpgwapi');
}
// PostgreSQL seems to require to update the sequenz, after manually inserting id's
$oProc->UpdateSequence('egw_sqlfs','fs_id');

// Create Addressbook for Default group, by setting a group ACL from the group to itself for all rights: add, read, edit and delete
$defaultgroup = $GLOBALS['egw_setup']->add_account('Default','Default','Group',False,False);
$GLOBALS['egw_setup']->add_acl('addressbook',$defaultgroup,$defaultgroup,1|2|4|8);

/**
 * Create template directory and set default document_dir preference of addressbook, calendar, infolog, tracker, timesheet and projectmanager
 */
$admins = $GLOBALS['egw_setup']->add_account('Admins','Admin','Group',False,False);

egw_vfs::$is_root = true;
$prefs = new preferences();
$prefs->read_repository(false);
foreach(array('','addressbook', 'calendar', 'infolog', 'tracker', 'timesheet', 'projectmanager') as $app)
{
	if ($app && !file_exists(EGW_SERVER_ROOT.'/'.$app)) continue;

	// create directory and set permissions: Admins writable and other readable
	$dir = '/templates'.($app ? '/'.$app : '');
	egw_vfs::mkdir($dir, 075, STREAM_MKDIR_RECURSIVE);
	egw_vfs::chgrp($dir, abs($admins));
	egw_vfs::chmod($dir, 075);
	if (!$app) continue;

	// set default preference for app (preserving a maybe already set document-directory)
	if ($prefs->default[$app]['document_dir']) $dir .= ' '.$prefs->default[$app]['document_dir'];
	$prefs->add($app, 'document_dir', $dir, 'default');
}
$prefs->save_repository(false, 'default');
egw_vfs::$is_root = false;
