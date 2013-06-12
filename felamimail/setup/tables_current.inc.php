<?php
  /**************************************************************************\
  * eGroupWare                                                               *
  * http://www.egroupware.org                                                *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

	$phpgw_baseline = array(
		'egw_felamimail_displayfilter' => array(
			'fd' => array(
				'fmail_filter_accountid' => array('type' => 'int','precision' => '4','nullable' => False,'comment'=>'account id of filter owner','meta'=>'user'),
				'fmail_filter_data' => array('type' => 'text','comment'=>'','meta'=>'','comment'=>'serialized filter')
			),
			'pk' => array('fmail_filter_accountid'),
			'fk' => array(),
			'ix' => array(),
			'uc' => array()
		),
		'egw_felamimail_accounts' => array(
			'fd' => array(
				'fm_owner' => array('type' => 'int','precision' => '4','nullable' => False,'comment'=>'account id of profile owner','meta'=>'user'),
				'fm_id' => array('type' => 'auto','comment'=>'id of the profile'),
				'fm_realname' => array('type' => 'varchar','precision' => '128','comment'=>'textual descriptor for the profile'),
				'fm_organization' => array('type' => 'varchar','precision' => '128','comment'=>'additional textual descriptor for the profile, used for organization in mail-header fields'),
				'fm_emailaddress' => array('type' => 'varchar','precision' => '128','nullable' => False,'comment'=>'email address'),
				'fm_signatureid' =>  array('type' => 'int','precision' => '4','comment'=>'reference to egw_felamimail_signatures'),
				'fm_ic_hostname' => array('type' => 'varchar','precision' => '128','comment'=>'incomming server hostname or ip'),
				'fm_ic_port' => array('type' => 'int','precision' => '4','comment'=>'incomming server port'),
				'fm_ic_username' => array('type' => 'varchar','precision' => '128','comment'=>'incomming server username to use in authentication process'),
				'fm_ic_password' => array('type' => 'varchar','precision' => '128','comment'=>'incomming server password to be used for authentication'),
				'fm_ic_encryption' => array('type' => 'int','precision' => '4','comment'=>'wether to use encryption 0=none, 1=STARTTLS, 2=TLS, 3=SSL'),
				'fm_og_hostname' => array('type' => 'varchar','precision' => '128','comment'=>'outgoing server hostname or ip'),
				'fm_og_port' => array('type' => 'int','precision' => '4', 'comment'=>'outgoing server port'),
				'fm_og_smtpauth' => array('type' => 'bool','comment'=>'flag to indicate that authentication is required for sending emails'),
				'fm_og_username' => array('type' => 'varchar','precision' => '128','comment'=>'outgoing server username to use for authentication'),
				'fm_og_password' => array('type' => 'varchar','precision' => '128','comment'=>'outgoing server password to be used to authenticate'),
				'fm_active' => array('type' => 'bool','nullable' => False,'comment'=>'flag to indicate that the profile is active; may be set to active even for identity profiles'),
				'fm_ic_validatecertificate' => array('type' => 'bool','comment'=>'flag to indicate wether to use certificate validation; only affects secure connections'),
				'fm_ic_enable_sieve' => array('type' => 'bool','precision' => '255','comment'=>'tell the program, that you expect SIEVE to be implemented SERVER side'),
				'fm_ic_sieve_server' => array('type' => 'varchar','precision' => '128','comment'=>'sieve server name or ip address; program simply stores a copy of incomming server'),
				'fm_ic_sieve_port' => array('type' => 'int','precision' => '4','comment'=>'Sieve server port'),
				'fm_ic_folderstoshowinhome'	=>	array('type' => 'text','comment'=>'serialized array of a folderselection to be shown in home area (folder with counters)'),
				'fm_ic_sentfolder' =>  array('type' => 'varchar','precision' => '128','comment'=>'configuration option for ic server specific sent folder'),
				'fm_ic_trashfolder' =>  array('type' => 'varchar','precision' => '128','comment'=>'configuration option for ic server specific trash folder'),
				'fm_ic_draftfolder' =>  array('type' => 'varchar','precision' => '128','comment'=>'configuration option for ic server specific drafts folder'),
				'fm_ic_templatefolder' =>  array('type' => 'varchar','precision' => '128','comment'=>'configuration option for ic server specific templates folder'),
			),
			'pk' => array('fm_id'),
			'fk' => array(),
			'ix' => array('fm_owner'),
			'uc' => array()
		),
		'egw_felamimail_signatures' => array(
			'fd' => array(
				'fm_signatureid' => array('type' => 'auto','comment'=>'primary key; id of a signature'),
				'fm_accountid' => array('type' => 'int','precision' => '11','comment'=>'owner of the signature','meta'=>'user'),
				'fm_signature' => array('type' => 'text','comment'=>'signature in html or plain text format'),
				'fm_description' => array('type' => 'varchar','precision' => '255','comment'=>'textual description for the signature given'),
				'fm_defaultsignature' => array('type' => 'bool','comment'=>'flag to indicate wether this signature is to be used as default for the owner specified')
			),
			'pk' => array('fm_signatureid'),
			'fk' => array(),
			'ix' => array(),
			'uc' => array(array('fm_signatureid','fm_accountid'))
		)
	);
?>
