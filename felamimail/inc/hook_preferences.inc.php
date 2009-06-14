<?php
/**************************************************************************\
* FeLaMiMail                                                               *
* http://www.egroupware.org                                                *
* Written by Lars Kneschke <lars@kneschke.de>                              *
* --------------------------------------------                             *
*  This program is free software; you can redistribute it and/or modify it *
*  under the terms of the GNU General Public License as published by the   *
*  Free Software Foundation; version 2 of the License. 			   *
\**************************************************************************/

/* $Id$ */

{
	// Only Modify the $file and $title variables.....
	$title = $appname;
	$mailPreferences = ExecMethod('felamimail.bopreferences.getPreferences');

	$file['Preferences'] = $GLOBALS['egw']->link('/index.php','menuaction=preferences.uisettings.index&appname=' . $appname);

	if($mailPreferences->userDefinedAccounts) {
		$linkData = array
		(
			'menuaction' => 'felamimail.uipreferences.listAccountData',
		);
		$file['Manage eMail Accounts and Identities'] = $GLOBALS['egw']->link('/index.php',$linkData);
	}
	if(empty($mailPreferences->preferences['prefpreventmanagefolders']) || $mailPreferences->preferences['prefpreventmanagefolders'] == 0) {
		$file['Manage Folders'] = $GLOBALS['egw']->link('/index.php','menuaction=felamimail.uipreferences.listFolder');
	}
	if (is_object($mailPreferences))
	{
		$icServer = $mailPreferences->getIncomingServer(0);

		if($icServer->enableSieve) {
			$file['filter rules'] = $GLOBALS['egw']->link('/index.php', 'menuaction=felamimail.uisieve.listRules');
			$file['vacation notice'] = $GLOBALS['egw']->link('/index.php','menuaction=felamimail.uisieve.editVacation');
		}
	}
	//Do not modify below this line
	display_section($appname,$title,$file);
}
