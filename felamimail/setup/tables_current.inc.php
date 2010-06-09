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
				'fmail_filter_accountid' => array('type' => 'int','precision' => '4','nullable' => False),
				'fmail_filter_data' => array('type' => 'text')
			),
			'pk' => array('fmail_filter_accountid'),
			'fk' => array(),
			'ix' => array(),
			'uc' => array()
		),
		'egw_felamimail_accounts' => array(
			'fd' => array(
				'fm_owner' => array('type' => 'int','precision' => '4','nullable' => False),
				'fm_id' => array('type' => 'auto'),
				'fm_realname' => array('type' => 'varchar','precision' => '128'),
				'fm_organization' => array('type' => 'varchar','precision' => '128'),
				'fm_emailaddress' => array('type' => 'varchar','precision' => '128','nullable' => False),
				'fm_signatureid' =>  array('type' => 'int','precision' => '4'),
				'fm_ic_hostname' => array('type' => 'varchar','precision' => '128'),
				'fm_ic_port' => array('type' => 'int','precision' => '4'),
				'fm_ic_username' => array('type' => 'varchar','precision' => '128'),
				'fm_ic_password' => array('type' => 'varchar','precision' => '128'),
				'fm_ic_encryption' => array('type' => 'int','precision' => '4'),
				'fm_og_hostname' => array('type' => 'varchar','precision' => '128'),
				'fm_og_port' => array('type' => 'int','precision' => '4'),
				'fm_og_smtpauth' => array('type' => 'bool'),
				'fm_og_username' => array('type' => 'varchar','precision' => '128'),
				'fm_og_password' => array('type' => 'varchar','precision' => '128'),
				'fm_active' => array('type' => 'bool','nullable' => False),
				'fm_ic_validatecertificate' => array('type' => 'bool'),
				'fm_ic_enable_sieve' => array('type' => 'bool','precision' => '255'),
				'fm_ic_sieve_server' => array('type' => 'varchar','precision' => '128'),
				'fm_ic_sieve_port' => array('type' => 'int','precision' => '4'),
				'fm_ic_folderstoshowinhome'	=>	array('type' => 'text'),
				'fm_ic_sentfolder' =>  array('type' => 'varchar','precision' => '128'),
				'fm_ic_trashfolder' =>  array('type' => 'varchar','precision' => '128'),
				'fm_ic_draftfolder' =>  array('type' => 'varchar','precision' => '128'),
				'fm_ic_templatefolder' =>  array('type' => 'varchar','precision' => '128'),
			),
			'pk' => array('fm_id'),
			'fk' => array(),
			'ix' => array('fm_owner'),
			'uc' => array()
		),
		'egw_felamimail_signatures' => array(
			'fd' => array(
				'fm_signatureid' => array('type' => 'auto'),
				'fm_accountid' => array('type' => 'int','precision' => '11'),
				'fm_signature' => array('type' => 'text'),
				'fm_description' => array('type' => 'varchar','precision' => '255'),
				'fm_defaultsignature' => array('type' => 'bool')
			),
			'pk' => array('fm_signatureid'),
			'fk' => array(),
			'ix' => array(),
			'uc' => array(array('fm_signatureid','fm_accountid'))
		)
	);
?>
