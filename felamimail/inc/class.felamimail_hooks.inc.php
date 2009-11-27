<?php
/**
 * FelamiMail - admin, preferences and sidebox-menus and other hooks
 *
 * @link http://www.egroupware.org
 * @package felamimail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * Class containing admin, preferences and sidebox-menus and other hooks
 */
class felamimail_hooks
{
	/**
	 * Settings hook
	 *
	 * @param array|string $hook_data
	 */
	static function settings($hook_data)
	{
		if (!$hook_data['setup'])	// does not work on setup time
		{
			$folderList = array();

			$bofelamimail =& CreateObject('felamimail.bofelamimail',$GLOBALS['egw']->translation->charset());
			if($bofelamimail->openConnection()) {
				$folderObjects = $bofelamimail->getFolderObjects(true, false);
				foreach($folderObjects as $folderName => $folderInfo) {
					#_debug_array($folderData);
					$folderList[$folderName] = $folderInfo->displayName;
				}
				$bofelamimail->closeConnection();
			}

			$availableAutoFolders['none'] = lang('none, create all');
			foreach($bofelamimail->autoFolders as $aname) {
				$availableAutoFolders[$aname] = lang($aname);
			}

			$felamimailConfig = config::read('felamimail');
		}
		$refreshTime = array(
			'0' => lang('disabled'),
			'1' => '1',
			'2' => '2',
			'3' => '3',
			'4' => '4',
			'5' => '5',
			'6' => '6',
			'7' => '7',
			'8' => '8',
			'9' => '9',
			'10' => '10',
			'15' => '15',
			'20' => '20',
			'30' => '30'
		);

		$no_yes = array(
			'0' => lang('no'),
			'1' => lang('yes')
		);

		$prefAllowManageFolders = $no_yes;

		$forwardOptions = array(
			'asmail' => lang('forward as attachment'),
			'inline' => lang('forward inline'),
		);

		$sortOrder = array(
			'0' => lang('date(newest first)'),
			'1' => lang('date(oldest first)'),
			'3' => lang('from(A->Z)'),
			'2' => lang('from(Z->A)'),
			'5' => lang('subject(A->Z)'),
			'4' => lang('subject(Z->A)'),
			'7' => lang('size(0->...)'),
			'6' => lang('size(...->0)')
		);

		$selectOptions = array(
			'0' => lang('no'),
			'1' => lang('yes'),
			'2' => lang('yes') . ' - ' . lang('small view')
		);

		$newWindowOptions = array(
			'1' => lang('only one window'),
			'2' => lang('allways a new window'),
		);

		$deleteOptions = array(
			'move_to_trash'		=> lang('move to trash'),
			'mark_as_deleted'	=> lang('mark as deleted'),
			'remove_immediately'	=> lang('remove immediately')
		);

		$composeOptions = array(
			'html'     => lang('html'),
			'text'   => lang('text/plain'),
		);

		$htmlOptions = array(
			'never_display'		=> lang('never display html emails'),
			'only_if_no_text'	=> lang('display only when no plain text is available'),
			'always_display'	=> lang('always show html emails'),
		);

		$rowOrderStyle = array(
			'felamimail'	=> lang('FeLaMiMail'),
			'outlook'	=> 'Outlook',
		);

		$trashOptions = array_merge(
			array(
				'none' => lang("Don't use Trash")
			),
			$folderList
		);

		$sentOptions = array_merge(
			array(
				'none' => lang("Don't use Sent")
			),
			$folderList
		);

		$draftOptions = array_merge(
			array(
				'none' => lang("Don't use draft folder")
			),
			$folderList
		);

		$templateOptions = array_merge(
		    array(
		        'none' => lang("Don't use template folder")
		    ),
		    $folderList
		);

		// modify folderlist, add a none entry, to be able to force the regarding settings, if no folders apply
		$folderList['none'] = lang('no folders');

		/* Settings array for this app */
		return array(
			'refreshTime' => array(
				'type'   => 'select',
				'label'  => 'Refresh time in minutes',
				'name'   => 'refreshTime',
				'values' => $refreshTime,
				'xmlrpc' => True,
				'admin'  => False,
				'forced'=> 5,
			),
		   'prefaskformove' => array(
		        'type'   => 'select',
		        'label'  => 'Do you want to be asked for confirmation before moving selected messages to another folder?',
		        'name'   => 'prefaskformove',
		        'values' => $no_yes,
		        'xmlrpc' => True,
		        'admin'  => False,
		        'forced' => '1',
		    ),
		   'prefpreventmanagefolders' => array(
		        'type'   => 'select',
		        'label'  => 'Do you want to prevent the managing of folders (creation, accessrights AND subscribtion)?',
		        'name'   => 'prefpreventmanagefolders',
		        'values' => $prefAllowManageFolders,
		        'xmlrpc' => True,
		        'admin'  => False,
		        'forced' => '0',
		    ),
			'prefpreventforwarding' => array(
				'type'   => 'select',
				'label'  => 'Do you want to prevent the editing/setup for forwarding of mails via settings (, even if SIEVE is enabled)?',
				'name'   => 'prefpreventforwarding',
				'values' => $no_yes,
				'xmlrpc' => True,
				'admin'  => False,
				'forced' => '0',
			),
			'prefpreventnotificationformailviaemail' => array(
				'type'   => 'select',
				'label'  => 'Do you want to prevent the editing/setup of notification by mail to other emailadresses if emails arrive (, even if SIEVE is enabled)?',
				'name'   => 'prefpreventnotificationformailviaemail',
				'values' => $no_yes,
				'xmlrpc' => True,
				'admin'  => False,
				'forced' => '1',
			),
			'prefpreventeditfilterrules' => array(
				'type'   => 'select',
				'label'  => 'Do you want to prevent the editing/setup of filter rules (, even if SIEVE is enabled)?',
				'name'   => 'prefpreventeditfilterrules',
				'values' => $no_yes,
				'xmlrpc' => True,
				'admin'  => False,
				'forced' => '0',
			),
			'prefpreventabsentnotice' => array(
				'type'   => 'select',
				'label'  => 'Do you want to prevent the editing/setup of the absent/vacation notice (, even if SIEVE is enabled)?',
				'name'   => 'prefpreventabsentnotice',
				'values' => $no_yes,
				'xmlrpc' => True,
				'admin'  => False,
				'forced' => '0',
			),
		    'notavailableautofolders' => array(
		        'type'   => 'multiselect',
		        'label'  => 'which folders - in general - should NOT be automatically created, if not existing',
		        'name'   => 'notavailableautofolders',
		        'values' => $availableAutoFolders,
		        'xmlrpc' => True,
		        'admin'  => False,
				'forced' => 'none',
		    ),
			'sortOrder' => array(
				'type'   => 'select',
				'label'  => 'Default sorting order',
				'name'   => 'sortOrder',
				'values' => $sortOrder,
				'xmlrpc' => True,
				'admin'  => False,
				'default'=> '0',	// newest first
			),
			'rowOrderStyle' => array(
				'type'   => 'select',
				'label'  => 'row order style',
				'name'   => 'rowOrderStyle',
				'values' => $rowOrderStyle,
				'xmlrpc' => True,
				'admin'  => False,
				'default'=> 'felamimail',
			),
		    'message_forwarding' => array(
		        'type'   => 'select',
		        'label'  => 'how to forward messages',
		        'name'   => 'message_forwarding',
		        'values' => $forwardOptions,
		        'xmlrpc' => True,
		        'admin'  => False,
		        'default'=> 'asmail',
		    ),
			'mainscreen_showmail' => array(
				'type'   => 'select',
				'label'  => 'show new messages on main screen',
				'name'   => 'mainscreen_showmail',
				'values' => $selectOptions,
				'xmlrpc' => True,
				'admin'  => False,
			),
			'mainscreen_showfolders' => array(
				'type'   => 'multiselect',
				'label'  => 'if shown, which folders should appear on main screen',
				'name'   => 'mainscreen_showfolders',
				'values' => $folderList,
				'xmlrpc' => True,
				'admin'  => False,
			),
		    'messages_showassent_0' => array(
		        'type'   => 'multiselect',
		        'label'  => 'which folders (additional to the Sent Folder) should be displayed using the Sent Folder View Schema',
		        'name'   => 'messages_showassent_0',
		        'values' => $folderList,
		        'xmlrpc' => True,
		        'admin'  => False,
		        'forced' => 'none',
		    ),
		    'notify_folders' => array(
				'type'   => 'multiselect',
				'label'  => 'notify when new mails arrive on these folders',
				'name'   => 'notify_folders',
				'values' => $folderList,
				'xmlrpc' => True,
				'admin'  => False,
			),
			'message_newwindow' => array(
				'type'   => 'select',
				'label'  => 'display messages in multiple windows',
				'name'   => 'message_newwindow',
				'values' => $newWindowOptions,
				'xmlrpc' => True,
				'admin'  => False,
				'forced' => '1',
			),
			'deleteOptions' => array(
				'type'   => 'select',
				'label'  => 'when deleting messages',
				'name'   => 'deleteOptions',
				'values' => $deleteOptions,
				'xmlrpc' => True,
				'admin'  => False,
				'default'=> 'move_to_trash',
			),
		    'composeOptions' => array(
		        'type'   => 'select',
		        'label'  => 'start new messages with mime type plain/text or html?',
		        'name'   => 'composeOptions',
		        'values' => $composeOptions,
		        'xmlrpc' => True,
		        'admin'  => False,
		        'default'=> 'html',
		    ),
			'htmlOptions' => array(
				'type'   => 'select',
				'label'  => 'display of html emails',
				'name'   => 'htmlOptions',
				'values' => $htmlOptions,
				'xmlrpc' => True,
				'admin'  => False,
				'forced' => 'only_if_no_text',
			),
			'allowExternalIMGs' => array(
				'type'   => 'check',
				'label'  => 'allow images from external sources in html emails',
				'name'   => 'allowExternalIMGs',
				'xmlrpc' => True,
				'admin'  => True,
				'forced' => true,
			),
			'trashFolder' => array(
				'type'   => 'select',
				'label'  => 'trash folder',
				'name'   => 'trashFolder',
				'values' => $trashOptions,
				'xmlrpc' => True,
				'admin'  => False,
			),
			'sentFolder' => array(
				'type'   => 'select',
				'label'  => 'sent folder',
				'name'   => 'sentFolder',
				'values' => $sentOptions,
				'xmlrpc' => True,
				'admin'  => False,
			),
			'draftFolder' => array(
				'type'   => 'select',
				'label'  => 'draft folder',
				'name'   => 'draftFolder',
				'values' => $draftOptions,
				'xmlrpc' => True,
				'admin'  => False,
			),
		    'templateFolder' => array(
		        'type'   => 'select',
		        'label'  => 'template folder',
		        'name'   => 'templateFolder',
		        'values' => $templateOptions,
		        'xmlrpc' => True,
		        'admin'  => False,
		    ),
			'sieveScriptName' => array(
				'type'   => 'input',
				'label'  => 'sieve script name',
				'name'   => 'sieveScriptName',
				'xmlrpc' => True,
				'admin'  => False,
				'forced' => 'felamimail',
			),
		);
	}

	/**
	 * Preferences hook
	 *
	 * @param array|string $hook_data
	 */
	static function preferences($hook_data)
	{
		// Only Modify the $file and $title variables.....
		$title = $appname = 'felamimail';
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
				if(empty($mailPreferences->preferences['prefpreventeditfilterrules']) || $mailPreferences->preferences['prefpreventeditfilterrules'] == 0)
					$file['filter rules'] = $GLOBALS['egw']->link('/index.php', 'menuaction=felamimail.uisieve.listRules');
				if(empty($mailPreferences->preferences['prefpreventabsentnotice']) || $mailPreferences->preferences['prefpreventabsentnotice'] == 0)
					$file['vacation notice'] = $GLOBALS['egw']->link('/index.php','menuaction=felamimail.uisieve.editVacation');
			}
		}
		//Do not modify below this line
		display_section($appname,$title,$file);
	}

	/**
	 * Sidebox menu hook
	 *
	 * @param array|string $hook_data
	 */
	static function sidebox_menu($hook_data)
	{
		$appname = 'felamimail';
		$menu_title = $GLOBALS['egw_info']['apps'][$appname]['title'] . ' '. lang('Menu');
		$preferences = ExecMethod('felamimail.bopreferences.getPreferences');
		$linkData = array(
			'menuaction'    => 'felamimail.uicompose.compose'
		);

		$file = array(
			array(
				'text' => '<a class="textSidebox" href="'. htmlspecialchars($GLOBALS['egw']->link('/index.php', $linkData)).'" target="_blank" onclick="egw_openWindowCentered(\''.$GLOBALS['egw']->link('/index.php', $linkData).'\',\''.lang('compose').'\',700,750); return false;">'.lang('compose'),
	                        'no_lang' => true,
			),
		);
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
				$file['Manage Folders']	= $GLOBALS['egw']->link('/index.php',array('menuaction'=>'felamimail.uipreferences.listFolder'));
			}

			if (is_object($preferences)) $icServer = $preferences->getIncomingServer(0);
			if(is_a($icServer, 'defaultimap')) {
				if($icServer->enableSieve)
				{
					$linkData = array
					(
						'menuaction'	=> 'felamimail.uisieve.listRules',
					);
					if(empty($preferences->preferences['prefpreventeditfilterrules']) || $preferences->preferences['prefpreventeditfilterrules'] == 0)
						$file['filter rules']	= $GLOBALS['egw']->link('/index.php',$linkData);

					$linkData = array
					(
						'menuaction'	=> 'felamimail.uisieve.editVacation',
					);
					if(empty($preferences->preferences['prefpreventabsentnotice']) || $preferences->preferences['prefpreventabsentnotice'] == 0)
					{
						$file['vacation notice']	= $GLOBALS['egw']->link('/index.php',$linkData);
					}
					if((empty($preferences->preferences['prefpreventnotificationformailviaemail']) ||
						$preferences->preferences['prefpreventnotificationformailviaemail'] == 0) &&
						(empty($preferences->preferences['prefpreventforwarding']) ||
						$preferences->preferences['prefpreventforwarding'] == 0) )
					{
						$file['email notification'] = $GLOBALS['egw']->link('/index.php','menuaction=felamimail.uisieve.editEmailNotification'); //Added email notifications
					}
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
					if(empty($preferences->preferences['prefpreventforwarding']) || $preferences->preferences['prefpreventforwarding'] == 0)
						$file['Forwarding']	= $GLOBALS['egw']->link('/index.php',$linkData);
				}
			}
			display_sidebox($appname,$menu_title,$file);
		}
	}
}
