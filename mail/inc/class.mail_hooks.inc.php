<?php
/**
 * Mail - admin, preferences and sidebox-menus and other hooks
 *
 * @link http://www.egroupware.org
 * @package mail
 * @author Klaus Leithoff [kl@stylite.de]
 * @copyright (c) 2013 by Klaus Leithoff <kl-AT-stylite.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * Class containing admin, preferences and sidebox-menus and other hooks
 */
class mail_hooks
{
/**
 * Several hooks calling an instanciated mail_bo, which need to use the mail_bo::getInstance() singelton
 *
 * @param string|array $hookData
 */
	static public function accountHooks($hookData)
	{
		if (($default_profile_id = emailadmin_bo::getDefaultProfileID()))
		{
			$mail_bo = mail_bo::forceEAProfileLoad($default_profile_id);

			switch(is_array($hookData) ? $hookData['location'] : $hookData)
			{
				case 'addaccount':
					$mail_bo->addAccount($hookData);
					break;
				case 'deleteaccount':
					$mail_bo->deleteAccount($hookData);
					break;
				case 'editaccount':
					$mail_bo->updateAccount($hookData);
					break;
			}
			emailadmin_bo::unsetCachedObjects($default_profile_id);
		}
	}

	/**
	 * Menu for Admin >> Edit accounts
	 */
	static public function adminMenu()
	{
		if (($default_profile_id = emailadmin_bo::getDefaultProfileID()))
		{
			$mail_bo = mail_bo::forceEAProfileLoad($default_profile_id);

			$ogServer = $mail_bo->mailPreferences->getOutgoingServer($default_profile_id);
			//error_log(__METHOD__."() default_profile_id = $default_profile_id, get_class(ogServer)=".get_class($ogServer));

			if (!in_array(get_class($ogServer), array('defaultsmtp', 'emailadmin_smtp')))
			{
				global $menuData;

				$menuData[] = Array
				(
					'description'   => 'email settings',
					'url'           => '/index.php',
					'extradata'     => 'menuaction=emailadmin.uiuserdata.editUserData'
				);
			}
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
//				'menuaction' => 'mail.uidisplay.display',
			),
			'view_popup' => '850xegw_getWindowOuterHeight()',
			'add'        => array(
//				'menuaction' => 'mail.uicompose.compose',
			),
			'add_popup'  => '850xegw_getWindowOuterHeight()',
			// register fmail as handler for .eml files
			'mime' => array(
				'message/rfc822' => array(
//					'menuaction' => 'mail.mail_ui.importMessageFromVFS2DraftAndDisplay',
					'mime_popup' => '850xegw_getWindowOuterHeight()',
					'mime_url'   => 'formData[file]',
				),
			),
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
			if (isset($GLOBALS['egw_info']['user']['preferences']['mail']['ActiveProfileID']))
				$profileID = (int)$GLOBALS['egw_info']['user']['preferences']['mail']['ActiveProfileID'];

			$mail_bo = mail_bo::getInstance(true,$profileID);
			$profileID = $GLOBALS['egw_info']['user']['preferences']['mail']['ActiveProfileID'] = $mail_bo->profileID;
			if($mail_bo->openConnection($profileID)) {
				$folderObjects = $mail_bo->getFolderObjects(true, false);
				foreach($folderObjects as $folderName => $folderInfo) {
					#_debug_array($folderData);
					$folderList[$folderName] = $folderInfo->displayName;
				}
				if ($GLOBALS['type'] === 'user')
				{
					$trashFolder    = $mail_bo->getTrashFolder();
					$draftFolder	= $mail_bo->getDraftFolder();
					$templateFolder = $mail_bo->getTemplateFolder();
					$sentFolder		= $mail_bo->getSentFolder();
					// use displaynames, if available
					if (isset($folderList[$trashFolder])) $trashFolder = $folderList[$trashFolder];
					if (isset($folderList[$draftFolder])) $draftFolder = $folderList[$draftFolder];
					if (isset($folderList[$templateFolder])) $templateFolder = $folderList[$templateFolder];
					if (isset($folderList[$sentFolder])) $sentFolder = $folderList[$sentFolder];
				}
				$mail_bo->closeConnection();
			}

			$availableAutoFolders['none'] = lang('none, create all');
			foreach(mail_bo::$autoFolders as $aname) {
				$availableAutoFolders[$aname] = lang($aname);
			}

			$mailConfig = config::read('mail');
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

		$connectionTimeout = array(
			'0' => lang('use default timeout (20 seconds)'),
			'10' => '10', // timeout used in SIEVE
			'20' => '20',
			'30' => '30',
			'40' => '40',
			'50' => '50',
			'60' => '60',
			'70' => '70',
			'80' => '80',
			'90' => '90',
		);

		$no_yes = array(
			'0' => lang('no'),
			'1' => lang('yes')
		);
		$no_yes_copy = array_merge($no_yes,array('2'=>lang('yes, offer copy option')));

 		$prefAllowManageFolders = $no_yes;

		$test_connection = array(
			'full' => lang('yes, show all debug information available for the user'),
			'nocredentials' => lang('yes, but mask all usernames and passwords'),
			'nopasswords' => lang('yes, but mask all passwords'),
			'basic' => lang('yes, show basic info only'),
			'reset' => lang('yes, only trigger connection reset'),
			'none' => lang('no'),
		);

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

		$trustServersUnseenOptions = array_merge(
			$no_yes,
			array('2' => lang('yes') . ' - ' . lang('but check shared folders'))
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
		$replyOptions = array(
			'none'	=> lang('use source as displayed, if applicable'),
			'html'  => lang('force html'),
			'text'  => lang('force plain text'),
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
		$toggle = false;
		if ($GLOBALS['egw_info']['user']['preferences']['common']['select_mode'] == 'EGW_SELECTMODE_TOGGLE') $toggle=true;
		$rowOrderStyle = array(
			'mail'	=> lang('mail'),
			'outlook'	=> 'Outlook',
			'mail_wCB' => lang('mail').' '.($toggle?lang('(select mails by clicking on the line, like a checkbox)'):lang('(with checkbox enforced)')),
			'outlook_wCB'	=> 'Outlook'.' '.($toggle?lang('(select mails by clicking on the line, like a checkbox)'):lang('(with checkbox enforced)')),
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
		$settingsArray = array(
			array(
				'type'  => 'section',
				'title' => lang('Mail settings'),
				'no_lang'=> true,
				'xmlrpc' => False,
				'admin'  => False
			),
			'message_forwarding' => array(
				'type'   => 'select',
				'label'  => 'how to forward messages',
				'help'   => 'Which method to use when forwarding a message',
				'name'   => 'message_forwarding',
				'values' => $forwardOptions,
				'xmlrpc' => True,
				'admin'  => False,
				'default'=> 'asmail',
			),
			'composeOptions' => array(
				'type'   => 'select',
				'label'  => 'New message type',
				'help'   => 'start new messages with mime type plain/text or html?',
				'name'   => 'composeOptions',
				'values' => $composeOptions,
				'xmlrpc' => True,
				'admin'  => False,
				'default'=> 'html',
			),
			'replyOptions' => array(
				'type'   => 'select',
				'label'  => 'Reply message type',
				'help'  => 'start reply messages with mime type plain/text or html or try to use the displayed format (default)?',
				'name'   => 'replyOptions',
				'values' => $replyOptions,
				'xmlrpc' => True,
				'admin'  => False,
				'default'=> 'none',
			),
			'disableRulerForSignatureSeparation' => array(
				'type'   => 'select',
				'label'  => 'disable Ruler for separation of mailbody and signature',
				'help'   => 'Turn off horizontal line between signature and composed message (this is not according to RFC).<br>If you use templates, this option is only applied to the text part of the message.',
				'name'   => 'disableRulerForSignatureSeparation',
				'values' => $no_yes,
				'xmlrpc' => True,
				'default'=> 0,
				'admin'  => False,
			),
			'insertSignatureAtTopOfMessage' => array(
				'type'   => 'select',
				'label'  => 'signature at top',
				'help'   => 'insert the signature at top of the new (or reply) message when opening compose dialog (you may not be able to switch signatures)',
				'name'   => 'insertSignatureAtTopOfMessage',
				'values' => $no_yes,
				'xmlrpc' => True,
				'default'=> 0,
				'admin'  => False,
			),
			'attachVCardAtCompose' => array(
				'type'   => 'select',
				'label'  => 'Attach vCard',
				'help'   => 'attach users VCard at compose to every new mail',
				'name'   => 'attachVCardAtCompose',
				'values' => $no_yes,
				'xmlrpc' => True,
				'default'=> 0,
				'admin'  => False,
			),
			array(
				'type'  => 'section',
				'title' => lang('General settings'),
				'no_lang'=> true,
				'xmlrpc' => False,
				'admin'  => False
			),
			'refreshTime' => array(
				'type'   => 'select',
				'label'  => 'Refresh time in minutes',
				'help'   => 'How often to check with the server for new mail',
				'name'   => 'refreshTime',
				'values' => $refreshTime,
				'xmlrpc' => True,
				'admin'  => False,
				'forced'=> 5,
			),
			'htmlOptions' => array(
				'type'   => 'select',
				'label'  => 'display of html emails',
				'help'   => 'What do do with html email',
				'name'   => 'htmlOptions',
				'values' => $htmlOptions,
				'xmlrpc' => True,
				'admin'  => False,
				'forced' => 'always_display',
			),
			'message_newwindow' => array(
				'type'   => 'select',
				'label'  => 'display messages in multiple windows',
				'help'   => 'When displaying messages in a popup, re-use the same popup for all or open a new popup for each message',
				'name'   => 'message_newwindow',
				'values' => $newWindowOptions,
				'xmlrpc' => True,
				'admin'  => False,
				'forced' => '1',
			),
			'allowExternalIMGs' => array(
				'type'   => 'check',
				'label'  => 'Allow external images',
				'help'   => 'allow images from external sources in html emails',
				'name'   => 'allowExternalIMGs',
				'xmlrpc' => True,
				'admin'  => True,
				'forced' => true,
			),
			'sortOrder' => array(
				'type'   => 'select',
				'label'  => 'Sort order',
				'help'   => 'Default sorting order',
				'name'   => 'sortOrder',
				'values' => $sortOrder,
				'xmlrpc' => True,
				'admin'  => False,
				'default'=> '0',	// newest first
			),
			'rowOrderStyle' => array(
				'type'   => 'select',
				'label'  => 'row order style',
				'help'   => 'What order the list columns are in',
				'name'   => 'rowOrderStyle',
				'values' => $rowOrderStyle,
				'xmlrpc' => True,
				'admin'  => False,
				'default'=> 'mail',
			),
			'prefMailGridBehavior' => array(
				'type'   => 'select',
				'label'  => 'how many messages should the mail list load',
				'help'   => 'If you select all messages there will be no pagination for mail message list. Beware, as some actions on all selected messages may be problematic depending on the amount of selected messages.',
				'name'   => 'prefMailGridBehavior',
				'values' =>	$gridViewBehavior,
				'xmlrpc' => True,
				'admin'  => False,
				'default'=> 50,
			),
			'PreViewFrameHeight' => array(
				'type'   => 'input',
				'label'  => 'Message preview size',
				'help'   => 'If you want to see a preview of a mail by single clicking onto the subject, set the height for the message-list and the preview area here. 300 seems to be a good working value. The preview will be displayed at the end of the message list when a message is selected.',
				'name'   => 'PreViewFrameHeight',
				'xmlrpc' => True,
				'admin'  => False,
				'forced' => '300',
			),
			'prefaskformove' => array(
				'type'   => 'select',
				'label'  => 'Confirm move to folder',
				'help'   => 'Do you want to be asked for confirmation before moving selected messages to another folder?',
				'name'   => 'prefaskformove',
				'values' => $no_yes_copy,
				'xmlrpc' => True,
				'admin'  => False,
				'forced' => '1',
			),
			'prefaskformultipleforward' => array(
				'type'   => 'select',
				'label'  => 'Confirm attach message',
				'help'  => 'Do you want to be asked for confirmation before attaching selected messages to new mail?',
				'name'   => 'prefaskformultipleforward',
				'values' => $no_yes,
				'xmlrpc' => True,
				'admin'  => False,
				'forced' => '1',
			),
			'mainscreen_showmail' => array(
				'type'   => 'select',
				'label'  => 'show new messages on home page',
				'help'   => 'Should new messages show up on the Home page',
				'name'   => 'mainscreen_showmail',
				'values' => $selectOptions,
				'xmlrpc' => True,
				'admin'  => False,
			),
			'mainscreen_showfolders' => array(
				'type'   => 'multiselect',
				'label'  => 'home page folders',
				'help'   => 'if shown, which folders should appear on the Home page',
				'name'   => 'mainscreen_showfolders',
				'values' => $folderList,
				'xmlrpc' => True,
				'admin'  => False,
			),
			'notify_folders' => array(
				'type'   => 'multiselect',
				'label'  => 'New mail notification',
				'help'   => 'notify when new mails arrive in these folders',
				'name'   => 'notify_folders',
				'values' => $folderList,
				'xmlrpc' => True,
				'admin'  => False,
			),
			array(
				'type'  => 'section',
				'title' => lang('Folder settings'),
				'no_lang'=> true,
				'xmlrpc' => False,
				'admin'  => False
			),
			'trashFolder' => array(
				'type'   => 'select',
				'label'  => lang('trash folder'),
				'help'   => (isset($trashFolder) && !empty($trashFolder)?lang('The folder <b>%1</b> will be used, if there is nothing set here, and no valid predefine given.',$trashFolder):''),
				'name'   => 'trashFolder',
				'values' => $trashOptions,
				'xmlrpc' => True,
				'admin'  => False,
			),
			'sentFolder' => array(
				'type'   => 'select',
				'label'  => lang('sent folder'),
				'help'   => (isset($sentFolder) && !empty($sentFolder)?lang('The folder <b>%1</b> will be used, if there is nothing set here, and no valid predefine given.',$sentFolder):''),
				'name'   => 'sentFolder',
				'values' => $sentOptions,
				'xmlrpc' => True,
				'admin'  => False,
			),
			'draftFolder' => array(
				'type'   => 'select',
				'label'  => lang('draft folder'),
				'help'   => (isset($draftFolder) && !empty($draftFolder)?lang('The folder <b>%1</b> will be used, if there is nothing set here, and no valid predefine given.',$draftFolder):''),
				'name'   => 'draftFolder',
				'values' => $draftOptions,
				'xmlrpc' => True,
				'admin'  => False,
			),
			'templateFolder' => array(
				'type'   => 'select',
				'label'  => lang('template folder'),
				'help'   => (isset($templateFolder) && !empty($templateFolder)?lang('The folder <b>%1</b> will be used, if there is nothing set here, and no valid predefine given.',$templateFolder):''),
				'name'   => 'templateFolder',
				'values' => $templateOptions,
				'xmlrpc' => True,
				'admin'  => False,
			),
			'deleteOptions' => array(
				'type'   => 'select',
				'label'  => 'when deleting messages',
				'help'   => 'what to do when you delete a message',
				'name'   => 'deleteOptions',
				'values' => $deleteOptions,
				'xmlrpc' => True,
				'admin'  => False,
				'default'=> 'move_to_trash',
			),
			'sendOptions' => array(
				'type'   => 'select',
				'label'  => 'when sending messages',
				'help'   => 'what to do when you send a message',
				'name'   => 'sendOptions',
				'values' => $sendOptions,
				'xmlrpc' => True,
				'admin'  => False,
				'default'=> 'move_to_sent',
			),
			'trustServersUnseenInfo' => array(
				'type'   => 'select',
				'label'  => 'trust servers SEEN / UNSEEN info',
				'help'   => 'Trust the server when retrieving the folder status. if you select no, we will search for the UNSEEN messages and count them ourselves',
				'name'   => 'trustServersUnseenInfo',
				'values' => $trustServersUnseenOptions,
				'xmlrpc' => True,
				'default'=> 2,
				'admin'  => False,
			),
			'showAllFoldersInFolderPane' => array(
				'type'   => 'select',
				'label'  => 'show all Folders',
				'help'   => 'show all folders, (subscribed AND unsubscribed) in Main Screen Folder Pane',
				'name'   => 'showAllFoldersInFolderPane',
				'values' => $no_yes,
				'xmlrpc' => True,
				'default'=> 0,
				'admin'  => False,
			),
			'messages_showassent_0' => array(
				'type'   => 'multiselect',
				'label'  => 'Extra sent folders',
				'help'   => 'which folders (additional to the Sent Folder) should be displayed using the Sent Folder View Schema',
				'name'   => 'messages_showassent_0',
				'values' => $folderList,
				'xmlrpc' => True,
				'admin'  => False,
				'forced' => 'none',
			),
			array(
				'type'  => 'section',
				'title' => lang('Configuration settings'),
				'no_lang'=> true,
				'xmlrpc' => False,
				'admin'  => False
			),
			'prefpreventmanagefolders' => array(
				'type'   => 'select',
				'label'  => 'Prevent managing folders',
				'help'   => 'Do you want to prevent the managing of folders (creation, accessrights AND subscribtion)?',
				'name'   => 'prefpreventmanagefolders',
				'values' => $prefAllowManageFolders,
				'xmlrpc' => True,
				'admin'  => False,
				'forced' => '0',
			),
			'prefpreventforwarding' => array(
				'type'   => 'select',
				'label'  => 'Prevent managing forwards',
				'help'   => 'Do you want to prevent the editing/setup for forwarding of mails via settings (, even if SIEVE is enabled)?',
				'name'   => 'prefpreventforwarding',
				'values' => $no_yes,
				'xmlrpc' => True,
				'admin'  => False,
				'forced' => '0',
			),
			'prefpreventnotificationformailviaemail' => array(
				'type'   => 'select',
				'label'  => 'Prevent managing notifications',
				'help'   => 'Do you want to prevent the editing/setup of notification by mail to other emailadresses if emails arrive (, even if SIEVE is enabled)?',
				'name'   => 'prefpreventnotificationformailviaemail',
				'values' => $no_yes,
				'xmlrpc' => True,
				'admin'  => False,
				'forced' => '1',
			),
			'prefpreventeditfilterrules' => array(
				'type'   => 'select',
				'label'  => 'Prevent managing filters',
				'help'   => 'Do you want to prevent the editing/setup of filter rules (, even if SIEVE is enabled)?',
				'name'   => 'prefpreventeditfilterrules',
				'values' => $no_yes,
				'xmlrpc' => True,
				'admin'  => False,
				'forced' => '0',
			),
			'prefpreventabsentnotice' => array(
				'type'   => 'select',
				'label'  => 'Prevent managing vacation notice',
				'help'   => 'Do you want to prevent the editing/setup of the absent/vacation notice (, even if SIEVE is enabled)?',
				'name'   => 'prefpreventabsentnotice',
				'values' => $no_yes,
				'xmlrpc' => True,
				'admin'  => False,
				'forced' => '0',
			),
			'connectionTimeout' => array(
				'type'   => 'select',
				'label'  => 'IMAP timeout',
				'help'   => 'Timeout on connections to your IMAP Server',
				'name'   => 'connectionTimeout',
				'values' => $connectionTimeout,
				'xmlrpc' => True,
				'admin'  => False,
			),
			'sieveScriptName' => array(
				'type'   => 'input',
				'label'  => 'sieve script name',
				'help'   => 'sieve script name',
				'name'   => 'sieveScriptName',
				'xmlrpc' => True,
				'admin'  => False,
				'forced' => 'mail',
			),
			'prefcontroltestconnection' => array(
				'type'   => 'select',
				'label'  => 'Test connection',
				'help'   => 'Show Test Connection section and control the level of info displayed?',
				'name'   => 'prefcontroltestconnection',
				'values' => $test_connection,
				'xmlrpc' => True,
				'admin'  => False,
				'forced' => '0',
			),
			'notavailableautofolders' => array(
				'type'   => 'multiselect',
				'label'  => 'do not auto create folders',
				'help'   => 'which folders - in general - should NOT be automatically created, if not existing',
				'name'   => 'notavailableautofolders',
				'values' => $availableAutoFolders,
				'xmlrpc' => True,
				'admin'  => False,
				'forced' => 'none',
			),
			'saveAsOptions' => array(
				'type'   => 'select',
				'label'  => 'Save as',
				'help'   => 'when saving messages as item of a different app',
				'name'   => 'saveAsOptions',
				'values' => $saveAsOptions,
				'xmlrpc' => True,
				'admin'  => False,
				'default'=> 'text',
			),
		);
		if (!$GLOBALS['egw_info']['apps']['stylite']) unset($settingsArray['attachVCardAtCompose']);
		return $settingsArray;
	}

	/**
	 * Admin hook
	 *
	 * @param array|string $hook_data
	 */
	static function admin($hook_data)
	{
		unset($GLOBALS['egw_info']['user']['preferences']['common']['auto_hide_sidebox']);
		// Only Modify the $file and $title variables.....
		$title = $appname = 'mail';
		$profileID = 0;
		if (isset($GLOBALS['egw_info']['user']['preferences']['mail']['ActiveProfileID']))
			$profileID = (int)$GLOBALS['egw_info']['user']['preferences']['mail']['ActiveProfileID'];

		$file = Array(
			'Site Configuration' => egw::link('/index.php',array('menuaction'=>'admin.uiconfig.index','appname'=>'mail')),
			'eMailAdmin: Profilemanagement' => egw::link('/index.php','menuaction=emailadmin.emailadmin_ui.index'),
		);
		display_section($appname,$title,$file);
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
		$title = $appname = 'mail';
		$profileID = 0;
		if (isset($GLOBALS['egw_info']['user']['preferences']['mail']['ActiveProfileID']))
			$profileID = (int)$GLOBALS['egw_info']['user']['preferences']['mail']['ActiveProfileID'];

		$mail_bo = mail_bo::getInstance(true,$profileID);
		$profileID = $GLOBALS['egw_info']['user']['preferences']['mail']['ActiveProfileID'] = $mail_bo->profileID;
		$mailPreferences =& $mail_bo->mailPreferences;

		$file['Preferences'] = egw::link('/index.php','menuaction=preferences.uisettings.index&appname=' . $appname);
/*
		if($mailPreferences->userDefinedAccounts) {
			$linkData = array
			(
				'menuaction' => 'mail.uipreferences.listAccountData',
			);
			$file['Manage eMail Accounts and Identities'] = egw::link('/index.php',$linkData);
		}
		if(empty($mailPreferences->preferences['prefpreventmanagefolders']) || $mailPreferences->preferences['prefpreventmanagefolders'] == 0) {
			$file['Manage Folders'] = egw::link('/index.php','menuaction=mail.uipreferences.listFolder');
		}
		if (is_object($mailPreferences))
		{
			$icServer = $mailPreferences->getIncomingServer($profileID);

			if($icServer->enableSieve) {
				if(empty($mailPreferences->preferences['prefpreventeditfilterrules']) || $mailPreferences->preferences['prefpreventeditfilterrules'] == 0)
					$file['filter rules'] = egw::link('/index.php', 'menuaction=mail.uisieve.listRules');
				if(empty($mailPreferences->preferences['prefpreventabsentnotice']) || $mailPreferences->preferences['prefpreventabsentnotice'] == 0)
					$file['vacation notice'] = egw::link('/index.php','menuaction=mail.uisieve.editVacation');
			}
		}
*/
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
		$appname = 'mail';
		$menu_title = $GLOBALS['egw_info']['apps'][$appname]['title'] . ' '. lang('Menu');
		$file = array();
		$profileID = 0;
		if (isset($GLOBALS['egw_info']['user']['preferences']['mail']['ActiveProfileID']))
			$profileID = (int)$GLOBALS['egw_info']['user']['preferences']['mail']['ActiveProfileID'];

		$mail_bo = mail_bo::getInstance(true,$profileID);
		$profileID = $GLOBALS['egw_info']['user']['preferences']['mail']['ActiveProfileID'] = $mail_bo->profileID;
		$preferences =& $mail_bo->mailPreferences;
		$showMainScreenStuff = false;
		if (!$showMainScreenStuff)
		{
			// action links that are mostly static and dont need any connection and additional classes ...
			$file += array(
				'mail'		=> egw::link('/index.php','menuaction=mail.mail_ui.index&ajax=true'),
			);

		}
		// empty trash (if available -> move to trash )
		if($preferences->preferences['deleteOptions'] == 'move_to_trash')
		{
			$file += array(
				'_NewLine_'	=> '', // give a newline
				'empty trash'	=> "javascript:egw_appWindow('".$appname."').emptyTrash();",
			);
		}
		if($preferences->preferences['deleteOptions'] == 'mark_as_deleted')
		{
			$file += array(
				'_NewLine_'		=> '', // give a newline
				'compress folder'	=> "javascript:egw_appWindow('".$appname."').compressFolder();",
			);
		}
		// import Message link - only when the required library is available
		if ((@include_once 'Mail/mimeDecode.php') !== false)
		{
			$linkData = array(
				'menuaction' => 'mail.mail_ui.importMessage',
			);

			$file += array(
				'import message' => "javascript:egw_openWindowCentered2('".egw::link('/index.php', $linkData,false)."','import',700,125,'no','$appname');",
			);

		}

		// display them all
		display_sidebox($appname,$menu_title,$file);

		if ($GLOBALS['egw_info']['user']['apps']['preferences'])
		{
			#$mailPreferences = ExecMethod('mail.bopreferences.getPreferences');
			$menu_title = lang('Preferences');
			$file = array(
				'Preferences'		=> egw::link('/index.php','menuaction=preferences.uisettings.index&appname=mail'),
			);
/*
			if($preferences->userDefinedAccounts || $preferences->userDefinedIdentities) {
				$linkData = array (
					'menuaction' => 'mail.uipreferences.listAccountData',
				);
				$file['Manage eMail Accounts and Identities'] = egw::link('/index.php',$linkData);

			}
*/
			if ($preferences->preferences['prefcontroltestconnection'] <> 'none') $file['Test Connection'] = egw::link('/index.php','menuaction=mail.mail_ui.TestConnection&appname=mail');
/*
			if($preferences->ea_user_defined_signatures) {
				$linkData = array (
					'menuaction' => 'mail.uipreferences.listSignatures',
				);
				$file['Manage Signatures'] = egw::link('/index.php',$linkData);
			}

			if(empty($preferences->preferences['prefpreventmanagefolders']) || $preferences->preferences['prefpreventmanagefolders'] == 0) {
				$file['Manage Folders']	= egw::link('/index.php',array('menuaction'=>'mail.uipreferences.listFolder'));
			}
			if (is_object($preferences)) $ogServer = $preferences->getOutgoingServer(0);
			if(($ogServer instanceof emailadmin_smtp)) {
				if($ogServer->editForwardingAddress)
				{
					$linkData = array
						(
							'menuaction'    => 'mail.uipreferences.editForwardingAddress',
						);
					//if(empty($preferences->preferences['prefpreventforwarding']) || $preferences->preferences['prefpreventforwarding'] == 0)
					$file['Forwarding']     = egw::link('/index.php',$linkData);
				}
			}
*/
			display_sidebox($appname,$menu_title,$file);
			unset($file);
/*
			$menu_title = lang('Sieve');
			if (is_object($preferences)) $icServer = $preferences->getIncomingServer($profileID);
			if(($icServer instanceof defaultimap)) {
				if($icServer->enableSieve)
				{
					$linkData = array
					(
						'menuaction'	=> 'mail.uisieve.listRules',
					);
					if(empty($preferences->preferences['prefpreventeditfilterrules']) || $preferences->preferences['prefpreventeditfilterrules'] == 0)
						$file['filter rules']	= egw::link('/index.php',$linkData);

					$linkData = array
					(
						'menuaction'	=> 'mail.uisieve.editVacation',
					);
					if(empty($preferences->preferences['prefpreventabsentnotice']) || $preferences->preferences['prefpreventabsentnotice'] == 0)
					{
						$file['vacation notice']	= egw::link('/index.php',$linkData);
					}
					if((empty($preferences->preferences['prefpreventnotificationformailviaemail']) ||
						$preferences->preferences['prefpreventnotificationformailviaemail'] == 0))
					{
						$file['email notification'] = egw::link('/index.php','menuaction=mail.uisieve.editEmailNotification'); //Added email notifications
					}
					if (count($file)) display_sidebox($appname,$menu_title,$file);
					unset($file);
				}
			}
*/
		}

		if ($GLOBALS['egw_info']['user']['apps']['admin'])
		{
			$file = Array(
				'Site Configuration' => egw::link('/index.php','menuaction=admin.uiconfig.index&appname=' . $appname),
				'eMailAdmin: Profilemanagement' => egw::link('/index.php','menuaction=emailadmin.emailadmin_ui.index'),
			);
			display_sidebox($appname,lang('Admin'),$file);
		}
	}
}
