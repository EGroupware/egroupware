<?php
/**
 * Mail - admin, preferences and sidebox-menus and other hooks
 *
 * @link http://www.egroupware.org
 * @package mail
 * @author Stylite AG [info@stylite.de]
 * @copyright (c) 2013 by Stylite AG <info-AT-stylite.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * Class containing admin, preferences and sidebox-menus and other hooks
 */
class mail_hooks
{
	/**
     * Hook called by link-class to include mail in the appregistry of the linkage
     *
     * @param array/string $location location and other parameters (not used)
     * @return array with method-names
     */
    static function search_link($location)
    {
        return array(
			'view'  => array(
				'menuaction' => 'mail.mail_ui.displayMessage',
			),
			'view_id'    => 'id',
			'view_popup' => '870xegw_getWindowOuterHeight()',
			//'view_popup' => '870x800',
			'view_list'	=>	'mail.mail_ui.index',
			'add'        => array(
				'menuaction' => 'mail.mail_compose.compose',
			),
			//'add_popup'  => '870xegw_getWindowOuterHeight()',
			'add_popup'  => '870x800',
			'edit'        => array(
				'menuaction' => 'mail.mail_compose.compose',
			),
			'edit_id'    => 'id',
			//'edit_popup'  => '870xegw_getWindowOuterHeight()',
			'edit_popup'  => '870x800',
			// register mail as handler for .eml files
			'mime' => array(
				'message/rfc822' => array(
					'menuaction' => 'mail.mail_ui.importMessageFromVFS2DraftAndDisplay',
					'mime_url'   => 'formData[file]',
					'mime_popup' => '870xegw_getWindowOuterHeight()',
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
			try
			{
				$mail_bo = mail_bo::getInstance(true,$profileID);
				$profileID = $GLOBALS['egw_info']['user']['preferences']['mail']['ActiveProfileID'] = $mail_bo->profileID;
			} catch (Exception $ex) {
				error_log(__METHOD__."()" . $ex->getMessage());
				$profileID = null;
			}
/*
			if($profileID && $mail_bo->openConnection($profileID)) {
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
*/

			$mailConfig = config::read('mail');
		}

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
			'text_only' => lang('convert only Mail to item (ignore possible attachments)'),
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
			'allowExternalIMGs' => array(
				'type'   => 'check',
				'label'  => 'Allow external images',
				'help'   => 'allow images from external sources in html emails',
				'name'   => 'allowExternalIMGs',
				'xmlrpc' => True,
				'admin'  => True,
				'forced' => true,
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
/*
			// option requested by customer, removed for the new client
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
*/
/*
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
*/
			array(
				'type'  => 'section',
				'title' => lang('Configuration settings'),
				'no_lang'=> true,
				'xmlrpc' => False,
				'admin'  => False
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
			/*'prefpreventmanagefolders' => array(
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
			'prefcontroltestconnection' => array(
				'type'   => 'select',
				'label'  => 'Test connection',
				'help'   => 'Show Test Connection section and control the level of info displayed?',
				'name'   => 'prefcontroltestconnection',
				'values' => $test_connection,
				'xmlrpc' => True,
				'admin'  => False,
				'forced' => '0',
			),*/
			'prefaskformove' => array(
				'type'   => 'select',
				'label'  => 'Confirm move to folder',
				'help'   => 'Do you want to be asked for confirmation before moving selected messages to another folder?',
				'name'   => 'prefaskformove',
				'values' => $no_yes_copy,
				'xmlrpc' => True,
				'default'=> 2,
				'admin'  => False,
				'forced' => '1',
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
			'add_popup' => '870x800',
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
		);
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
		try
		{
			$mail_bo = mail_bo::getInstance(true,$profileID);
			$profileID = $GLOBALS['egw_info']['user']['preferences']['mail']['ActiveProfileID'] = $mail_bo->profileID;
		} catch (Exception $ex) {
			error_log(__METHOD__."()" . $ex->getMessage());
			$profileID = null;
		}

		$preferences =& $mail_bo->mailPreferences;
		$serverCounter = $sieveEnabledServerCounter = 0;
		// account select box
		$selectedID = $profileID;
		$allAccountData = emailadmin_account::search($only_current_user=true, $just_name=false, $order_by=null);
		if ($allAccountData) {
			$rememberFirst=$selectedFound=null;
			foreach ($allAccountData as $tmpkey => $icServers)
			{
				if (is_null($rememberFirst)) $rememberFirst = $tmpkey;
				if ($tmpkey == $selectedID) $selectedFound=true;
				//error_log(__METHOD__.__LINE__.' Key:'.$tmpkey.'->'.array2string($icServers->acc_imap_host));
				$host = $icServers->acc_sieve_host;
				if (empty($host)) continue;
				if ($icServers->acc_sieve_enabled && $icServers->acc_sieve_port) $sieveEnabledServerCounter++;
				$serverCounter++;
				//error_log(__METHOD__.__LINE__.' Key:'.$tmpkey.'->'.array2string($identities[$icServers->acc_id]));
			}
		}
		$file=array();
		// Destination div for folder tree
		$file[] = array(
			'no_lang' => true,
			'text'=>'<span id="mail-index_buttonmailcreate" class="button" />',
			'link'=>false,
			'icon' => false
		);
		$file[] = array(
			'no_lang' => true,
			'text'=>'<span id="mail-tree_target" class="dtree" />',
			'link'=>false,
			'icon' => false
		);
		$showMainScreenStuff = false;
		// import Message link - only when the required library is available
		if ((@include_once 'Mail/mimeDecode.php') !== false)
		{
			$linkData = array(
				'menuaction' => 'mail.mail_ui.importMessage',
			);

			$file += array(
				'import message' => "javascript:egw_openWindowCentered2('".egw::link('/index.php', $linkData,false)."','importMessageDialog',870,125,'no','$appname');",
			);

		}

		// create account wizard
		if (self::access('createaccount'))
		{
			$file += array(
				'create new account' => "javascript:egw_openWindowCentered2('" .
					egw::link('/index.php', array('menuaction' => 'mail.mail_wizard.add'), '').
					"','_blank',640,480,'yes')",
			);
		}
		if (self::access('testconnection'))
		{
			$file['Test Connection'] = egw::link('/index.php','menuaction=mail.mail_ui.TestConnection&appname=mail');
		}
		// display them all
		display_sidebox($appname,$menu_title,$file);
/*
		unset($file);
		if ($preferences && $sieveEnabledServerCounter)
		{
			$menu_title = lang('Sieve');
			$linkData = array
			(
				'menuaction'	=> 'mail.mail_sieve.index',
				'ajax'			=> 'true'
			);
			if(empty($preferences['prefpreventeditfilterrules']) || $preferences['prefpreventeditfilterrules'] == 0)
				$file['filter rules']	= egw::link('/index.php',$linkData);

			$linkData = array
			(
				'menuaction'	=> 'mail.mail_sieve.editVacation',
				'ajax'			=> 'true'
			);
			if(empty($preferences['prefpreventabsentnotice']) || $preferences['prefpreventabsentnotice'] == 0)
			{
				$file['vacation notice']	= egw::link('/index.php',$linkData);
			}
			if((empty($preferences['prefpreventnotificationformailviaemail']) ||
				$preferences['prefpreventnotificationformailviaemail'] == 0))
			{
				$file['email notification'] = egw::link('/index.php','menuaction=mail.mail_sieve.editEmailNotification&ajax=true'); //Added email notifications
			}
			if ($sieveEnabledServerCounter>=1)
			{
				if($sieveEnabledServerCounter==1 && ($icServer instanceof defaultimap)) {
					if($icServer->enableSieve)
					{
						if (count($file)) display_sidebox($appname,$menu_title,$file);
						unset($file);
					}
				}
				else
				{
					if (count($file)) display_sidebox($appname,$menu_title,$file);
					unset($file);
				}
			}

		}
*/
		if ($GLOBALS['egw_info']['user']['apps']['admin'])
		{
			$file = Array(
				'Site Configuration' => egw::link('/index.php','menuaction=admin.uiconfig.index&appname=' . $appname),
			);
			display_sidebox($appname,lang('Admin'),$file);
		}
	}

	/**
	 * checks users mailbox and sends a notification if new mails have arrived
	 *
	 * @return boolean true or false
	 */
	static function notification_check_mailbox()
	{
		$recipient = (object)$GLOBALS['egw']->accounts->read($GLOBALS['egw_info']['user']['account_id']);

		$prefs = new preferences($recipient->account_id);
		$preferences = $prefs->read();
		// TODO: no possibility to set that at this time; always use INBOX
		if (empty($preferences['mail']['notify_folders'])) return true;//$preferences['mail']['notify_folders']='INBOX';
		//error_log(__METHOD__.__LINE__.array2string($preferences['mail']['notify_folders']));
		if(!isset($preferences['mail']['notify_folders'])||empty($preferences['mail']['notify_folders'])||$preferences['mail']['notify_folders']=='none') {
			return true; //no pref set for notifying - exit
		}
		$notify_folders = explode(',', $preferences['mail']['notify_folders']);
		if(count($notify_folders) == 0) {
			return true; //no folders configured for notifying - exit
		}

		$activeProfile = (int)$GLOBALS['egw_info']['user']['preferences']['mail']['ActiveProfileID'];
		//error_log(__METHOD__.__LINE__.' (user: '.$recipient->account_lid.') Active Profile:'.$activeProfile);
		$bomail = mail_bo::getInstance(false, $activeProfile);
		$activeProfile = $GLOBALS['egw_info']['user']['preferences']['mail']['ActiveProfileID'] = $bomail->profileID;
 		// buffer mail sessiondata, as they are needed for information exchange by the app itself
 		//$bufferFMailSession = $bomail->sessionData;
		try
		{
			$bomail->openConnection($activeProfile);
		} catch (Exception $e) {
			// TODO: This is ugly. Log a bit nicer!
			$error = $e->getMessage();
			error_log(__METHOD__.__LINE__.' # '.' (user: '.$recipient->account_lid.'): cannot connect to mailbox with Profile:'.$activeProfile.'. Please check your prefs!');
			if (!empty($error)) error_log(__METHOD__.__LINE__.' # '.$error);
			error_log(__METHOD__.__LINE__.' # Instance='.$GLOBALS['egw_info']['user']['domain'].', User='.$GLOBALS['egw_info']['user']['account_lid']);
			return false; // cannot connect to mailbox
		}

		$notified_mail_uidsCache = egw_cache::getCache(egw_cache::INSTANCE,'email','notified_mail_uids'.trim($GLOBALS['egw_info']['user']['account_id']),null,array(),$expiration=60*60*24*2);
		//$notified_mail_uidsCache = array();
		$recent_messages = array();
		$folder_status = array();
		foreach($notify_folders as $id=>$notify_folder) {
			if (empty($notify_folder)) continue;
			if(!is_array($notified_mail_uidsCache[$activeProfile][$notify_folder])) {
				$notified_mail_uidsCache[$activeProfile][$notify_folder] = array();
			}
			$folder_status[$notify_folder] = $bomail->getFolderStatus($notify_folder);
			$cutoffdate = time();
			$cutoffdate = $cutoffdate - (60*60*24*14); // last 14 days
			$_filter = array('status'=>array('UNSEEN','UNDELETED'),'type'=>"SINCE",'string'=> date("d-M-Y", $cutoffdate));
			//error_log(__METHOD__.__LINE__.' (user: '.$recipient->account_lid.') Mailbox:'.$notify_folder.' filter:'.array2string($_filter));
			// $_folderName, $_startMessage, $_numberOfMessages, $_sort, $_reverse, $_filter, $_thisUIDOnly=null, $_cacheResult=true
			$headers = $bomail->getHeaders($notify_folder, 1, 999, 0, true, $_filter,null,false);
			if(is_array($headers['header']) && count($headers['header']) > 0) {
				foreach($headers['header'] as $id=>$header) {
					//error_log(__METHOD__.__LINE__.' Found Message:'.$header['uid'].' Subject:'.$header['subject']);
					// check if unseen mail has already been notified
				 	if(!in_array($header['uid'], $notified_mail_uidsCache[$activeProfile][$notify_folder])) {
				 		// got a REAL recent message
				 		$header['folder'] = $notify_folder;
				 		$header['folder_display_name'] = $folder_status[$notify_folder]['displayName'];
				 		$header['folder_base64'] =  base64_encode($notify_folder);
				 		$recent_messages[] = $header;
				 	}
				}
			}
		}
		//error_log(__METHOD__.__LINE__.' Found Messages for Profile'.$activeProfile.':'.array2string($recent_messages).'<->'.array2string($notified_mail_uidsCache[$activeProfile]));
		// restore the mail session data, as they are needed by the app itself
		if(count($recent_messages) > 0) {
			// create notify message
			$notification_subject = lang("You've got new mail");
			$values = array();
			$values[] = array(); // content array starts at index 1
			foreach($recent_messages as $id=>$recent_message) {
				error_log(__METHOD__.__LINE__.' Found Message for Profile '.$activeProfile.':'.array2string($recent_message));
				$values[] =	array(
					'mail_uid'				=> $recent_message['uid'],
					'mail_folder' 			=> $recent_message['folder_display_name'],
					'mail_folder_base64' 	=> $recent_message['folder_base64'],
					'mail_subject'			=> $recent_message['subject'],
					'mail_from'				=> !empty($recent_message['sender_name']) ? $recent_message['sender_name'] : $recent_message['sender_address'],
					'mail_received'			=> $recent_message['date'],
				);
				// save notification status
				$notified_mail_uidsCache[$activeProfile][$recent_message['folder']][] = $recent_message['uid'];
			}

			// create etemplate
			$tpl = new etemplate('mail.checkmailbox');
			$notification_message = $tpl->exec(false, $values, array(), array(), array(), 1);
			//error_log(__METHOD__.__LINE__.array2string($notification_message));
			// send notification
			$notification = new notifications();
			$notification->set_receivers(array($recipient->account_id));
			$notification->set_message($notification_message);
			//$notification->set_popupmessage($notification_message);
			$notification->set_sender($recipient->account_id);
			$notification->set_subject($notification_subject);
			$notification->set_skip_backends(array('email'));
			$notification->send();
		}
		egw_cache::setCache(egw_cache::INSTANCE,'email','notified_mail_uids'.trim($GLOBALS['egw_info']['user']['account_id']),$notified_mail_uidsCache, $expiration=60*60*24*2);
		return true;
	}

	/**
	 * Hook returning options for deny_* groups
	 *
	 * @param string $name function name
	 * @param array $arguments
	 * @return string html
	 */
	public static function __callStatic($name, $arguments)
	{
		if (substr($name, 0, 5) != 'deny_')
		{
			throw new egw_exception_wrong_parameter("No method $name!");
		}
		$accountsel = new uiaccountsel();

		return '<input type="hidden" value="" name="newsettings['.$name.']" />'.
			$accountsel->selection('newsettings['.$name.']', 'deny_prefs', $arguments[0][$name], 'groups', 4);
	}

	/**
	 * Check if current user has access to a specific feature
	 *
	 * Example: if (!mail_hooks::access("managerfolders")) return;
	 *
	 * @param string $feature "createaccounts", "managefolders", "forwards", "notifications", "filters",
	 *		"notificationformailviaemail", "editfilterrules", "absentnotice", "testconnection", "aclmanagement"
	 * @return boolean true if user has access, false if not
	 */
	public static function access($feature)
	{
		static $config=null;
		if (!isset($config)) $config = (array)config::read('mail');
		//error_log(__METHOD__.__LINE__.' '.$feature.':'.array2string($config['deny_'.$feature]));
		if (!empty($config['deny_'.$feature]))
		{
			$denied_groups = explode(',', $config['deny_'.$feature]);
			return array_intersect($denied_groups, $GLOBALS['egw']->accounts->memberships($GLOBALS['egw_info']['user']['account_id'], true));
		}
		return true;
	}
}
