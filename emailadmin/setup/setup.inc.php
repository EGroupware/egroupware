<?php
	/**************************************************************************\
	* EGroupWare - EMailAdmin                                                  *
	* http://www.egroupware.org                                                *
	* http://www.phpgw.de                                                      *
	* Author: lkneschke@egroupware.org                                         *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/
	/* $Id$ */

	$setup_info['emailadmin']['name']      = 'emailadmin';
	$setup_info['emailadmin']['title']     = 'EMailAdmin';
	$setup_info['emailadmin']['version']   = '1.4';
	$setup_info['emailadmin']['app_order'] = 10;
	$setup_info['emailadmin']['enable']    = 2;

	$setup_info['emailadmin']['author'] = 'Lars Kneschke';
	$setup_info['emailadmin']['license']  = 'GPL';
	$setup_info['emailadmin']['description'] =
		'A central Mailserver management application for EGroupWare.';
	$setup_info['emailadmin']['note'] =
		'';
	$setup_info['emailadmin']['maintainer'] = array(
		'name'  => 'Lars Kneschke',
		'email' => 'lkneschke@linux-at-work.de'
	);

	$setup_info['emailadmin']['tables'][]	= 'egw_emailadmin';
	
	/* The hooks this app includes, needed for hooks registration */
	#$setup_info['emailadmin']['hooks'][] = 'preferences';
	$setup_info['emailadmin']['hooks'][] = 'admin';

	/* Dependencies for this app to work */
	$setup_info['emailadmin']['depends'][] = array(
		'appname'  => 'phpgwapi',
		'versions' => Array('1.3','1.4','1.5')
	);
	$setup_info['felamimail']['depends'][] = array(
		'appname'  => 'egw-pear',
		'versions' => Array('1.4.000','1.5')
	);
	// installation checks for felamimail
	$setup_info['emailadmin']['check_install'] = array(
		'' => array(
			'func' => 'pear_check',
			'from' => 'EMailAdmin',
		),
		'Auth_SASL' => array(
			'func' => 'pear_check',
			'from' => 'EMailAdmin',
		),
		'Net_IMAP' => array(
			'func' => 'pear_check',
			'from' => 'FeLaMiMail',
		),
		'imap' => array(
			'func' => 'extension_check',
			'from' => 'EMailAdmin',
		),
	);	


