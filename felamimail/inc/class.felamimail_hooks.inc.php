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
 * Several hooks calling an instanciated felamimail_bo, which need to use the felamimail_bo::getInstance() singelton
 *
 * @param string|array $hookData
 */
	static public function accountHooks($hookData)
	{
		$profileID = 0;
		if (isset($GLOBALS['egw_info']['user']['preferences']['felamimail']['ActiveProfileID']))
			$profileID = (int)$GLOBALS['egw_info']['user']['preferences']['felamimail']['ActiveProfileID'];

		$bofelamimail = felamimail_bo::getInstance(true,$profileID);
		$profileID = $GLOBALS['egw_info']['user']['preferences']['felamimail']['ActiveProfileID'] = $bofelamimail->profileID;

		switch(is_array($hookData) ? $hookData['location'] : $hookData)
		{
			case 'addaccount':
				$bofelamimail->addAccount($hookData);
				break;
			case 'deleteaccount':
				$bofelamimail->deleteAccount($hookData);
				break;
			case 'editaccount':
				$bofelamimail->updateAccount($hookData);
				break;
		}
	}

	/**
	 * Menu for Admin >> Edit accounts
	 */
	static public function adminMenu()
	{
		if ($GLOBALS['egw_info']['server']['account_repository'] == "ldap")
		{
			$data = Array
			(
				'description'   => 'email settings',
				'url'           => '/index.php',
				'extradata'     => 'menuaction=emailadmin.uiuserdata.editUserData'
			);

			//Do not modify below this line
			global $menuData;

			$menuData[] = $data;
		}
	}

	/**
     * Hook called by link-class to include calendar in the appregistry of the linkage
     *
     * @param array/string $location location and other parameters (not used)
     * @return array with method-names
     */
    static function search_link($location)
    {
        return array(
            'view'  => array(
                'menuaction' => 'felamimail.uidisplay.display',
            ),
            'view_popup' => '850xegw_getWindowOuterHeight()',
            'add'        => array(
                'menuaction' => 'felamimail.uicompose.compose',
            ),
            'add_popup'  => '850xegw_getWindowOuterHeight()',
        );
    }

	/**
	 * Settings hook
	 *
	 * @param array|string $hook_data
	 */
	static function settings($hook_data)
	{
		unset($GLOBALS['egw_info']['user']['preferences']['common']['auto_hide_sidebox']);
		if (!$hook_data['setup'])	// does not work on setup time
		{
			$folderList = array();

			$profileID = 0;
			if (isset($GLOBALS['egw_info']['user']['preferences']['felamimail']['ActiveProfileID']))
				$profileID = (int)$GLOBALS['egw_info']['user']['preferences']['felamimail']['ActiveProfileID'];

			$bofelamimail = felamimail_bo::getInstance(true,$profileID);
			$profileID = $GLOBALS['egw_info']['user']['preferences']['felamimail']['ActiveProfileID'] = $bofelamimail->profileID;
			if($bofelamimail->openConnection($profileID)) {
				$folderObjects = $bofelamimail->getFolderObjects(true, false);
				foreach($folderObjects as $folderName => $folderInfo) {
					#_debug_array($folderData);
					$folderList[$folderName] = $folderInfo->displayName;
				}
				$bofelamimail->closeConnection();
			}

			$availableAutoFolders['none'] = lang('none, create all');
			foreach(felamimail_bo::$autoFolders as $aname) {
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
		$no_yes_copy = array_merge($no_yes,array('2'=>lang('yes, offer copy option')));

 		$prefAllowManageFolders = $no_yes;

		$forwardOptions = array(
			'asmail' => lang('forward as attachment'),
			'inline' => lang('forward inline'),
		);
		$gridViewBehavior = array(
			'0' => lang('use common preferences max. messages'),
			'5'	=> 5,
			'10'=> 10,
			'15'=> 15,
			'20'=> 20,
			'25'=> 25,
			'50'=> 50,
			'75'=> 75,
			'100'=> 100,
			'200'=> 200,
			'250'=> 250,
			'500'=> 500,
			'999'=> 999,
			'-1' => lang('show all messages'),
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

		$selectOptions = array_merge(
			$no_yes,
			array('2' => lang('yes') . ' - ' . lang('small view'))
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

		$sendOptions = array(
			'move_to_sent'		=> lang('send message and move to send folder (if configured)'),
			'send_only'	=> lang('only send message, do not copy a version of the message to the configured sent folder')
		);

		$composeOptions = array(
			'html'     => lang('html'),
			'text'   => lang('text/plain'),
		);

		$saveAsOptions = array(
			'text'   	=> lang('convert Mail to item and attach its attachments to this item (standard)'),
			'add_raw'   => lang('convert Mail to item, attach its attachments and add raw message (message/rfc822 (.eml)) as attachment'),
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

		// otherwise we get warnings during setup
		if (!is_array($folderList)) $folderList = array();

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
		        'values' => $no_yes_copy,
		        'xmlrpc' => True,
		        'admin'  => False,
		        'forced' => '1',
		    ),
		   'prefaskformultipleforward' => array(
		        'type'   => 'select',
		        'label'  => 'Do you want to be asked for confirmation before attaching selected messages to new mail?',
		        'name'   => 'prefaskformultipleforward',
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
			'prefMailGridBehavior' => array(
				'type'   => 'select',
				'label'  => 'Mail Grid Behavior: how many messages should the mailgrid load? If you select all messages there will be no pagination for mail message list. (Beware, as some actions on all selected messages may be problematic depending on the amount of selected messages.)',
				'name'   => 'prefMailGridBehavior',
				'values' =>	$gridViewBehavior,
				'xmlrpc' => True,
				'admin'  => False,
				'default'=> 50,
			),
			'PreViewFrameHeight' => array(
				'type'   => 'input',
				'label'  => '3PaneView: If you want to see a preview of a mail by single clicking onto the subject, set the height for the message-list and the preview area here (300 seems to be a good working value). The preview will be displayed at the end of the message list on demand (click).',
				'name'   => 'PreViewFrameHeight',
				'xmlrpc' => True,
				'admin'  => False,
				'forced' => '300',
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
			'sendOptions' => array(
				'type'   => 'select',
				'label'  => 'when sending messages',
				'name'   => 'sendOptions',
				'values' => $sendOptions,
				'xmlrpc' => True,
				'admin'  => False,
				'default'=> 'move_to_sent',
			),
			'saveAsOptions' => array(
				'type'   => 'select',
				'label'  => 'when saving messages as item of a different app (if app supports the desired option)',
				'name'   => 'saveAsOptions',
				'values' => $saveAsOptions,
				'xmlrpc' => True,
				'admin'  => False,
				'default'=> 'text',
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
			'trustServersUnseenInfo' => array(
				'type'   => 'select',
				'label'  => 'trust servers SEEN / UNSEEN info when retrieving the folder status. (if you select no, we will search for the UNSEEN messages and count them ourselves)',
				'name'   => 'trustServersUnseenInfo',
				'values' => $no_yes,
				'xmlrpc' => True,
				'default'=> 1,
				'admin'  => False,
			),
			'showAllFoldersInFolderPane' => array(
				'type'   => 'select',
				'label'  => 'show all Folders (subscribed AND unsubscribed) in Main Screen Folder Pane',
				'name'   => 'showAllFoldersInFolderPane',
				'values' => $no_yes,
				'xmlrpc' => True,
				'default'=> 0,
				'admin'  => False,
			),
			'disableRulerForSignatureSeparation' => array(
				'type'   => 'select',
				'label'  => 'disable Ruler for separation of mailbody and signature when adding signature to composed message (this is not according to RFC).<br>If you use templates, this option is only applied to the text part of the message.',
				'name'   => 'disableRulerForSignatureSeparation',
				'values' => $no_yes,
				'xmlrpc' => True,
				'default'=> 0,
				'admin'  => False,
			),
			'insertSignatureAtTopOfMessage' => array(
				'type'   => 'select',
				'label'  => 'insert the signature at top of the new (or reply) message when opening compose dialog (you may not be able to switch signatures)',
				'name'   => 'insertSignatureAtTopOfMessage',
				'values' => $no_yes,
				'xmlrpc' => True,
				'default'=> 0,
				'admin'  => False,
			),
			'attachVCardAtCompose' => array(
				'type'   => 'select',
				'label'  => 'attach users VCard at compose to every new mail',
				'name'   => 'attachVCardAtCompose',
				'values' => $no_yes,
				'xmlrpc' => True,
				'default'=> 0,
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
		unset($GLOBALS['egw_info']['user']['preferences']['common']['auto_hide_sidebox']);
		// Only Modify the $file and $title variables.....
		$title = $appname = 'felamimail';
		$profileID = 0;
		if (isset($GLOBALS['egw_info']['user']['preferences']['felamimail']['ActiveProfileID']))
			$profileID = (int)$GLOBALS['egw_info']['user']['preferences']['felamimail']['ActiveProfileID'];

		$bofelamimail = felamimail_bo::getInstance(true,$profileID);
		$profileID = $GLOBALS['egw_info']['user']['preferences']['felamimail']['ActiveProfileID'] = $bofelamimail->profileID;
		$mailPreferences =& $bofelamimail->mailPreferences;

		$file['Preferences'] = egw::link('/index.php','menuaction=preferences.uisettings.index&appname=' . $appname);

		if($mailPreferences->userDefinedAccounts) {
			$linkData = array
			(
				'menuaction' => 'felamimail.uipreferences.listAccountData',
			);
			$file['Manage eMail Accounts and Identities'] = egw::link('/index.php',$linkData);
		}
		if(empty($mailPreferences->preferences['prefpreventmanagefolders']) || $mailPreferences->preferences['prefpreventmanagefolders'] == 0) {
			$file['Manage Folders'] = egw::link('/index.php','menuaction=felamimail.uipreferences.listFolder');
		}
		if (is_object($mailPreferences))
		{
			$icServer = $mailPreferences->getIncomingServer($profileID);

			if($icServer->enableSieve) {
				if(empty($mailPreferences->preferences['prefpreventeditfilterrules']) || $mailPreferences->preferences['prefpreventeditfilterrules'] == 0)
					$file['filter rules'] = egw::link('/index.php', 'menuaction=felamimail.uisieve.listRules');
				if(empty($mailPreferences->preferences['prefpreventabsentnotice']) || $mailPreferences->preferences['prefpreventabsentnotice'] == 0)
					$file['vacation notice'] = egw::link('/index.php','menuaction=felamimail.uisieve.editVacation');
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
		//error_log(__METHOD__);
		// always show the side bar
		unset($GLOBALS['egw_info']['user']['preferences']['common']['auto_hide_sidebox']);
		$appname = 'felamimail';
		$menu_title = $GLOBALS['egw_info']['apps'][$appname]['title'] . ' '. lang('Menu');
		$file = array();
		$profileID = 0;
		if (isset($GLOBALS['egw_info']['user']['preferences']['felamimail']['ActiveProfileID']))
			$profileID = (int)$GLOBALS['egw_info']['user']['preferences']['felamimail']['ActiveProfileID'];

		$bofelamimail = felamimail_bo::getInstance(true,$profileID);
		$profileID = $GLOBALS['egw_info']['user']['preferences']['felamimail']['ActiveProfileID'] = $bofelamimail->profileID;
		$preferences =& $bofelamimail->mailPreferences;
		$showMainScreenStuff = false;
		if(($_GET['menuaction'] == 'felamimail.uifelamimail.viewMainScreen' ||
			$_GET['menuaction'] == 'felamimail.uifelamimail.changeFolder' ||
			stripos($_GET['menuaction'],'ajax_sidebox') !== false) &&
			$_GET['menuaction'] != 'felamimail.uipreferences.editAccountData' &&
			$_GET['menuaction'] != 'felamimail.uifelamimail.redirectToPreferences' &&
			$_GET['menuaction'] != 'felamimail.uifelamimail.redirectToEmailadmin') {
			if (isset($_GET["mailbox"]))
			{
				$bofelamimail->sessionData['mailbox'] = urldecode($_GET["mailbox"]);
				$bofelamimail->sessionData['startMessage']= 1;
				$bofelamimail->sessionData['sort']    = $preferences->preferences['sortOrder'];
				$bofelamimail->sessionData['activeFilter']= -1;
				$bofelamimail->saveSessionData();
			}
			$uiwidgets		= CreateObject('felamimail.uiwidgets');
			$showMainScreenStuff = true;
		}
		if (!$showMainScreenStuff)
		{
			// action links that are mostly static and dont need any connection and additional classes ...
			$file += array(
				'felamimail'		=> egw::link('/index.php','menuaction=felamimail.uifelamimail.viewMainScreen&ajax=true'),
			);

			// standard compose link
			$linkData = array(
				'menuaction'    => 'felamimail.uicompose.compose'
			);
			$file += array(
				'Compose' => "javascript:egw_openWindowCentered2('".egw::link('/index.php', $linkData,false)."','compose',700,750,'no','$appname');",
			);
		}
		// select account box, treeview, we use a whileloop as we may want to break out
		while($showMainScreenStuff) {
			$bofelamimail->restoreSessionData();
			$mailbox 		= $bofelamimail->sessionData['mailbox'];
			//_debug_array($mailbox);

			$icServerID = (int)$bofelamimail->profileID;
			if (is_object($preferences))
			{
				// gather profile data
				$imapServer =& $bofelamimail->icServer;
				//error_log(__METHOD__.__LINE__.array2string($imapServer));
				// account select box
				$selectedID = $bofelamimail->getIdentitiesWithAccounts($identities);

				if (empty($selectedID) && isset($imapServer->ImapServerId)) $selectedID = $imapServer->ImapServerId;
				//error_log(__METHOD__.__LINE__.' SelectedID:'.$selectedID.' IcServerID:'.$imapServer->ImapServerId);
				// if nothing valid is found return to user defined account definition
				if (empty($imapServer->host) && count($identities)==0 && $preferences->userDefinedAccounts)
				{
					$showMainScreenStuff= false;
					break;
				}
				//error_log(__METHOD__.__LINE__.array2string($preferences->identities));
				$activeIdentity =& $preferences->getIdentity($icServerID, true);
				//error_log(__METHOD__.__LINE__.' ActiveIdentity for profileID'.$icServerID.'->'.array2string($activeIdentity));
				if ($imapServer->_connected != 1) $connectionStatus = $bofelamimail->openConnection($icServerID);
				$folderObjects = $bofelamimail->getFolderObjects(true, false);
				$folderStatus = $bofelamimail->getFolderStatus($mailbox);

				// the data needed here are collected at the start of this function
				if (!isset($activeIdentity->id) && $selectedID == $icServerID) {
					$identities[$icServerID] = $activeIdentity->realName.' '.$activeIdentity->organization.' <'.$activeIdentity->emailAddress.'>';
				}
				// if you use user defined accounts you may want to access the profile defined with the emailadmin available to the user
				if ($activeIdentity->id) {
					$boemailadmin = new emailadmin_bo();
					$defaultProfile = $boemailadmin->getUserProfile() ;
					//error_log(__METHOD__.__LINE__.array2string($defaultProfile));
					$identitys =& $defaultProfile->identities;
					$icServers =& $defaultProfile->ic_server;
					foreach ($identitys as $tmpkey => $identity)
					{
						if (empty($icServers[$tmpkey]->host)) continue;
						$identities[$identity->id] = $identity->realName.' '.$identity->organization.' <'.$identity->emailAddress.'>';
					}
					//$identities[0] = $defaultIdentity->realName.' '.$defaultIdentity->organization.' <'.$defaultIdentity->emailAddress.'>';
				}

				$selectAccount = html::select('accountSelect', $selectedID, $identities, true, 'style="width:100%;" onchange="var appWindow=egw_appWindow(\''.$appname.'\');appWindow.changeActiveAccount(this);"');
				//error_log(__METHOD__.__LINE__.$selectAccount);
				$file[] = array(
					'text' => "<div id=\"divAccountSelect\" style=\" width:100%;\">".$selectAccount."</div>",
					'no_lang' => True,
					'link' => False,
					'icon' => False,
				);
				// show foldertree
				//_debug_array($folderObjects);
				$folderTree = $uiwidgets->createHTMLFolder
				(
					$folderObjects,
					$mailbox,
					$folderStatus['unseen'],
					lang('IMAP Server'),
					$imapServer->username.'@'.$imapServer->host,
					'divFolderTree',
					FALSE
				);
				//$bofelamimail->closeConnection();
		        $file[] =  array(
	        	    'text' => "<div id=\"divFolderTree\" class=\"dtree\" style=\"overflow:auto; max-width:400px; width:100%; max-height:450px; margin-bottom: 0px;padding-left: 0px; padding-right: 0px; padding-top:0px; z-index:100; \">
					$folderTree
					</div>
					<script>
						var wnd = egw_appWindow('".$appname."');
						if (wnd && typeof wnd.refreshFolderStatus != 'undefined')
						{
							wnd.refreshFolderStatus();
						}
					</script>",
					'no_lang' => True,
					'link' => False,
					'icon' => False,
				);
			}
			break; // kill the while loop as we need only one go
		}
		// buttons
		if($showMainScreenStuff) {

			// some buttons
			$linkData = array (
				'menuaction'    => 'felamimail.uicompose.compose'
			);
			$urlCompose = "egw_appWindow('".$appname."').openComposeWindow('".egw::link('/index.php',$linkData,false)."');";

			$navbarImages = array(
				'new'			=> array(
					'action'	=> $urlCompose,
					'tooltip'	=> lang('compose'),
				),
				'read_small'		=> array(
					'action'	=> "egw_appWindow('".$appname."').mail_flagMessages('read')",
					'tooltip'	=> lang('mark selected as read'),
				),
				'unread_small'		=> array(
					'action'	=> "egw_appWindow('".$appname."').mail_flagMessages('unread')",
					'tooltip'	=> lang('mark selected as unread'),
				),
				'unread_flagged_small'	=> array(
					'action'	=> "egw_appWindow('".$appname."').mail_flagMessages('flagged')",
					'tooltip'	=> lang('mark selected as flagged'),
				),
				'read_flagged_small'	=> array(
					'action'	=> "egw_appWindow('".$appname."').mail_flagMessages('unflagged')",
					'tooltip'	=> lang('mark selected as unflagged'),
				),
				'delete'		=> array(
					'action'	=> "egw_appWindow('".$appname."').mail_deleteMessages(egw_appWindow('".$appname."').mailGridGetSelected())",
					'tooltip'	=> lang('mark as deleted'),
				),
			);

			foreach($navbarImages as $buttonName => $buttonInfo) {
				$navbarButtons .= $uiwidgets->navbarButton($buttonName, $buttonInfo['action'], $buttonInfo['tooltip']);
			}
			/*$file[] = array(
				'text' => "<TABLE WIDTH=\"100%\" CELLPADDING=\"0\" CELLSPACING=\"0\" style=\"border: solid #aaaaaa 1px; border-right: solid black 1px; \">
							<tr class=\"navbarBackground\">
								<td align=\"right\" width=\"100%\">".$navbarButtons."</td>
							</tr>
						   </table>",
				'no_lang' => True,
				'link' => False,
				'icon' => False,
			);*/
		}

		// empty trash (if available -> move to trash )
		if($preferences->preferences['deleteOptions'] == 'move_to_trash')
		{
			$file += Array(
				'_NewLine_'	=> '', // give a newline
				'empty trash'	=> "javascript:egw_appWindow('".$appname."').emptyTrash();",
			);
		}
		if($preferences->preferences['deleteOptions'] == 'mark_as_deleted')
		{
			$file += Array(
				'_NewLine_'		=> '', // give a newline
				'compress folder'	=> "javascript:egw_appWindow('".$appname."').compressFolder();",
			);
		}
		// import Message link - only when the required library is available
		if ((@include_once 'Mail/mimeDecode.php') !== false)
		{
			$linkData = array(
				'menuaction' => 'felamimail.uifelamimail.importMessage',
			);

			$file += array(
				'import message' => "javascript:egw_openWindowCentered2('".egw::link('/index.php', $linkData,false)."','import',700,125,'no','$appname');",
			);

		}
		// display them all
		display_sidebox($appname,$menu_title,$file);

		if ($GLOBALS['egw_info']['user']['apps']['preferences'])
		{
			#$mailPreferences = ExecMethod('felamimail.bopreferences.getPreferences');
			$menu_title = lang('Preferences');
			$file = array(
				//'Preferences'		=> egw::link('/index.php','menuaction=preferences.uisettings.index&appname=felamimail'),
				'Preferences'	=> egw::link('/index.php','menuaction=felamimail.uifelamimail.redirectToPreferences&appname=felamimail'),
			);

			if($preferences->userDefinedAccounts || $preferences->userDefinedIdentities) {
				$linkData = array (
					'menuaction' => 'felamimail.uipreferences.listAccountData',
				);
				$file['Manage eMail Accounts and Identities'] = egw::link('/index.php',$linkData);

			}

			if($preferences->ea_user_defined_signatures) {
				$linkData = array (
					'menuaction' => 'felamimail.uipreferences.listSignatures',
				);
				$file['Manage Signatures'] = egw::link('/index.php',$linkData);
			}

			if(empty($preferences->preferences['prefpreventmanagefolders']) || $preferences->preferences['prefpreventmanagefolders'] == 0) {
				$file['Manage Folders']	= egw::link('/index.php',array('menuaction'=>'felamimail.uipreferences.listFolder'));
			}

			if (is_object($preferences)) $icServer = $preferences->getIncomingServer($profileID);
			if(($icServer instanceof defaultimap)) {
				if($icServer->enableSieve)
				{
					$linkData = array
					(
						'menuaction'	=> 'felamimail.uisieve.listRules',
					);
					if(empty($preferences->preferences['prefpreventeditfilterrules']) || $preferences->preferences['prefpreventeditfilterrules'] == 0)
						$file['filter rules']	= egw::link('/index.php',$linkData);

					$linkData = array
					(
						'menuaction'	=> 'felamimail.uisieve.editVacation',
					);
					if(empty($preferences->preferences['prefpreventabsentnotice']) || $preferences->preferences['prefpreventabsentnotice'] == 0)
					{
						$file['vacation notice']	= egw::link('/index.php',$linkData);
					}
					if((empty($preferences->preferences['prefpreventnotificationformailviaemail']) ||
						$preferences->preferences['prefpreventnotificationformailviaemail'] == 0) &&
						(empty($preferences->preferences['prefpreventforwarding']) ||
						$preferences->preferences['prefpreventforwarding'] == 0) )
					{
						$file['email notification'] = egw::link('/index.php','menuaction=felamimail.uisieve.editEmailNotification'); //Added email notifications
					}
				}
			}

			if (is_object($preferences)) $ogServer = $preferences->getOutgoingServer(0);
			if(($ogServer instanceof defaultsmtp)) {
				if($ogServer->editForwardingAddress)
				{
					$linkData = array
					(
						'menuaction'	=> 'felamimail.uipreferences.editForwardingAddress',
					);
					if(empty($preferences->preferences['prefpreventforwarding']) || $preferences->preferences['prefpreventforwarding'] == 0)
						$file['Forwarding']	= egw::link('/index.php',$linkData);
				}
			}
			display_sidebox($appname,$menu_title,$file);
		}
		if ($GLOBALS['egw_info']['user']['apps']['admin'])
		{
			$file = Array(
				//'Site Configuration' => egw::link('/index.php','menuaction=emailadmin.emailadmin_ui.index'),
				'Site Configuration' => egw::link('/index.php','menuaction=felamimail.uifelamimail.redirectToEmailadmin'),
			);
			display_sidebox($appname,lang('Admin'),$file);
		}
	}
}
