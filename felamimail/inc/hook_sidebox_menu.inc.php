<?php
{
	/**************************************************************************\
	* eGroupWare - Calendar's Sidebox-Menu for idots-template                  *
	* http://www.egroupware.org                                                *
	* Written by Pim Snel <pim@lingewoud.nl>                                   *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/
	
	/* $Id$ */

 /*
	This hookfile is for generating an app-specific side menu used in the idots
	template set.

	$menu_title speaks for itself
	$file is the array with link to app functions

	display_sidebox can be called as much as you like
 */

	$menu_title = $GLOBALS['egw_info']['apps'][$appname]['title'] . ' '. lang('Menu');
	$preferences = ExecMethod('felamimail.bopreferences.getPreferences');
	$linkData = array
	(
		'menuaction'    => 'felamimail.uicompose.compose'
	);

	$file = array(
		array(
			'text' => '<a class="textSidebox" href="'. htmlspecialchars($GLOBALS['egw']->link('/index.php', $linkData)).'" target="_blank" onclick="egw_openWindowCentered(\''.$GLOBALS['egw']->link('/index.php', $linkData).'\',\''.lang('compose').'\',700,750); return false;">'.lang('compose'),
                        'no_lang' => true,
                    ),

	);
#	$linkData = array
#	(
#		'menuaction'    => 'felamimail.uifelamimail.importMessage'
#	);
#	$file += array(
#		'import' => array(
#			'text' => '<a class="textSidebox" href="'. htmlspecialchars($GLOBALS['egw']->link('/index.php', $linkData)).'" target="_blank" onclick="egw_openWindowCentered(\''.$GLOBALS['egw']->link('/index.php', $linkData).'\',\''.lang('import').'\',550,100); return false;">'.lang('Import Mail'),
#			'no_lang' => true,
#		),
#	);
			

	if($preferences->preferences['deleteOptions'] == 'move_to_trash')
	{
		$file += Array(
			'_NewLine_'	=> '', // give a newline
			'empty trash'	=> "javascript:emptyTrash();",
		);
	}
	
	if($preferences->preferences['deleteOptions'] == 'mark_as_deleted')
	{
		$file += Array(
			'_NewLine_'		=> '', // give a newline
			'compress folder'	=> "javascript:compressFolder();",
		);
	}
	
	display_sidebox($appname,$menu_title,$file);

	if ($GLOBALS['egw_info']['user']['apps']['preferences'])
	{
		#$mailPreferences = ExecMethod('felamimail.bopreferences.getPreferences');
		$menu_title = lang('Preferences');
		$file = array(
			'Preferences'		=> $GLOBALS['egw']->link('/index.php','menuaction=preferences.uisettings.index&appname=felamimail'),
		);

		if($preferences->userDefinedAccounts || $preferences->userDefinedIdentities) {
			$linkData = array (
				'menuaction' => 'felamimail.uipreferences.listAccountData',
			);
			$file['Manage eMail Accounts and Identities'] = $GLOBALS['egw']->link('/index.php',$linkData);

		}

		if($preferences->ea_user_defined_signatures) {
			$linkData = array (
				'menuaction' => 'felamimail.uipreferences.listSignatures',
			);
			$file['Manage Signatures'] = $GLOBALS['egw']->link('/index.php',$linkData);
		}

		if(empty($preferences->preferences['prefpreventmanagefolders']) || $preferences->preferences['prefpreventmanagefolders'] == 0) {		
			$file['Manage Folders']	= $GLOBALS['egw']->link('/index.php','menuaction=felamimail.uipreferences.listFolder');
		}

		if (is_object($preferences)) $icServer = $preferences->getIncomingServer(0);
		if(is_a($icServer, 'defaultimap')) {
			if($icServer->enableSieve) 
			{
				$linkData = array
				(
					'menuaction'	=> 'felamimail.uisieve.listRules',
				);
				$file['filter rules']	= $GLOBALS['egw']->link('/index.php',$linkData);

				$linkData = array
				(
					'menuaction'	=> 'felamimail.uisieve.editVacation',
				);
				$file['vacation notice']	= $GLOBALS['egw']->link('/index.php',$linkData);
				$file['email notification'] = $GLOBALS['egw']->link('/index.php','menuaction=felamimail.uisieve.editEmailNotification'); //Added email notifications
			}
		}

		if (is_object($preferences)) $ogServer = $preferences->getOutgoingServer(0);
		if(is_a($ogServer, 'defaultsmtp')) {
			if($ogServer->editForwardingAddress)
			{
				$linkData = array
				(
					'menuaction'	=> 'felamimail.uipreferences.editForwardingAddress',
				);
				$file['Forwarding']	= $GLOBALS['egw']->link('/index.php',$linkData);
			}
		}

		display_sidebox($appname,$menu_title,$file);
	}

/*	if ($GLOBALS['egw_info']['user']['apps']['admin'])
	{
		$menu_title = lang('Administration');
		$file = Array(
			'Configuration' => $GLOBALS['egw']->link('/index.php','menuaction=felamimail.uifelamimail.hookAdmin')
		);
		display_sidebox($appname,$menu_title,$file);
	} */
}
?>
