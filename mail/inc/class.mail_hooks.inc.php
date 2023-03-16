<?php
/**
 * Mail - admin, preferences and sidebox-menus and other hooks
 *
 * @link http://www.egroupware.org
 * @package mail
 * @author EGroupware GmbH [info@egroupware.org]
 * @copyright (c) 2013-16 by EGroupware GmbH <info-AT-egroupware.org>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

use EGroupware\Api;
use EGroupware\Api\Egw;
use EGroupware\Api\Mail;

/**
 * Class containing admin, preferences and sidebox-menus and other hooks
 */
class mail_hooks
{
	/**
	 * Hook to add context menu entries to user list
	 *
	 * @param array $data values for keys account_id and acc_id
	 */
	static function emailadmin_edit($data)
	{
		$actions = array();

		$account = Mail\Account::read($data['acc_id'], $data['account_id']);
		if (Mail\Account::is_multiple($account) && $account['acc_imap_admin_username'] ||
			$account['acc_imap_type'] == 'managementserver_imap')
		{
			Api\Translation::add_app('mail');

			if (true /* ToDo check ACL available */ || $account['acc_imap_type'] == 'managementserver_imap')
			{
				$actions[] = array (
					'id' => 'mail_acl',
					'caption' => 'Folder ACL',
					'icon' => 'lock',
					'popup' => '750x420',
					'url' => Egw::link('/index.php', array(
						'menuaction' => 'mail.mail_acl.edit',
						'acc_id' => $data['acc_id'],
						'account_id' => $data['account_id'],
					)),
					'toolbarDefault' => true,
				);
			}
			if ($account['acc_sieve_enabled'] || $account['acc_imap_type'] == 'managementserver_imap')
			{
				$actions[] = array (
					'id' => 'mail_vacation',
					'caption' => 'Vacation notice',
					'icon' => 'mail/navbar',
					'popup' => '750x420',
					'url' => Egw::link('/index.php', array(
						'menuaction' => 'mail.mail_sieve.editVacation',
						'acc_id' => $data['acc_id'],
						'account_id' => $data['account_id'],
					)),
					'toolbarDefault' => true,
				);
			}
		}
		return $actions;
	}

	/**
     * Hook called by link-class to include mail in the appregistry of the linkage
     *
     * @param array|string $location location and other parameters (not used)
     * @return array with method-names
     */
    static function search_link($location)
    {
		unset($location);	// not used, but required by function signature

        return array(
			'view'  => array(
				'menuaction' => 'mail.mail_ui.displayMessage',
			),
			'view_id'    => 'id',
			'view_popup' => '870xavailHeight',
			'view_list'	=>	'mail.mail_ui.index',
			'add'        => array(
				'menuaction' => 'mail.mail_compose.compose',
			),
			'add_popup'  => '900xavailHeight',
			'edit'        => array(
				'menuaction' => 'mail.mail_compose.compose',
			),
			'edit_id'    => 'id',
			'edit_popup'  => '900xavailHeight',
			// register mail as handler for .eml files
			'mime' => array(
				'message/rfc822' => array(
					'menuaction' => 'mail.mail_ui.importMessageFromVFS2DraftAndDisplay',
					'mime_url'   => 'formData[file]',
					'mime_data'  => 'formData[data]',
					'formData[type]' => 'message/rfc822',
					'mime_popup' => '900xavailHeight',
					'mime_target' => '_blank'
				),
			),
			'entry' => 'Mail',
			'entries' => 'Mails',
			'additional' => [
				'mail-account' => []
 			]
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
		}

		$no_yes = array(
			'0' => lang('no'),
			'1' => lang('yes')
		);
		$no_yes_copy = array_merge($no_yes,array('2'=>lang('yes, offer copy option')));

		$forwardOptions = array(
			'asmail' => lang('forward as attachment'),
			'inline' => lang('forward inline'),
		);
		$trustServersUnseenOptions = array_merge(
			$no_yes,
			array('2' => lang('yes') . ' - ' . lang('but check shared folders'))
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
			'no_attachments' => lang('add raw message, ignore mail attachments'),
			'text'   	=> lang('convert Mail to item and attach its attachments to this item (standard)'),
			'add_raw'   => lang('convert Mail to item, attach its attachments and add raw message (message/rfc822 (.eml)) as attachment'),
		);

		$htmlOptions = array(
			'never_display'		=> lang('never display html emails'),
			'only_if_no_text'	=> lang('display only when no plain text is available'),
			'always_display'	=> lang('always show html emails'),
		);

		$contactLabelOptions = array (
			'n_prefix' => array (
				'id' => 'n_prefix',
				'label' => lang('Prefix'),
			),
			'n_given' => array (
				'id' => 'n_given',
				'label' => lang('First name')
			),
			'n_family' => array(
				'id' => 'n_family',
				'label' => lang('Last name')
			),
			'org_name' => array(
				'id' => 'org_name',
				'label' => lang('Organisation')
			)
		);
		// otherwise we get warnings during setup
		if (!is_array($folderList)) $folderList = array();

		// modify folderlist, add a none entry, to be able to force the regarding settings, if no folders apply
		$folderList['none'] = lang('no folders');

		// Build toogled on actions sel options
		$allActions = mail_compose::getToolbarActions(array(
			'priority' => true,
			'mailaccount' => $GLOBALS['egw_info']['user']['preferences']['mail']['ActiveProfileID']
		));
		$toggledOnActions = array (
			'cc_expander' => array (
				'id' => 'cc_expander',
				'label' => lang('Cc'),
			),
			'bcc_expander' => array (
				'id' => 'bcc_expander',
				'label' => lang('Bcc')
			),
			'folder_expander' => array(
				'id' => 'folder_expander',
				'label' => lang('Folder')
			),
			'replyto_expander' => array(
				'id' => 'replyto_expander',
				'label' => lang('Reply to')
			)
		);

		foreach($allActions as $name => $action)
		{
			if ($action['checkbox'])
			{
				$toggledOnActions[$name] = array(
					'id' => $name,
					'label' => lang($action['caption']),
					'title' => lang($action['hint']),
					'icon' => $action['icon'],
					'app' => 'mail'
				);
			}
		}

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
				'label'  => 'disable horizontal rule for separation of mail body from signature',
				'help'   => 'Turn off horizontal line between signature and composed message (this is not according to RFC).<br>If you use templates, this option is only applied to the text part of the message.',
				'name'   => 'disableRulerForSignatureSeparation',
				'values' => $no_yes,
				'xmlrpc' => True,
				'default'=> 0,
				'admin'  => False,
			),
			'insertSignatureAtTopOfMessage' => array(
				'type'   => 'select',
				'label'  => 'Signature position and visibility',
				'help'   => 'Should signature be inserted after (standard) or before a reply or inline forward, and should signature be visible and changeable during compose.',
				'name'   => 'insertSignatureAtTopOfMessage',
				'values' => array(
					'0' => lang('after reply, visible during compose'),
					'1' => lang('before reply, visible during compose'),
					'no_belowaftersend'  => lang('appended after reply before sending'),
				),
				'xmlrpc' => True,
				'default'=> '0',
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
			'allowExternalIMGs' => array(
				'type'   => 'select',
				'label'  => 'Allow external images',
				'help'   => 'allow images from external sources in html emails',
				'name'   => 'allowExternalIMGs',
				'values' => array(
					0 => lang('Never'),
					1 => lang('Always'),
					2 => lang('Ask for permission')
				),
				'xmlrpc' => True,
				'admin'  => True,
				'default' => 2,
				'forced' => 1,
			),
			'allowExternalDomains' => array(
				'type'   => 'taglist',
				'label'  => 'Allow external images from listed domains',
				'help'   => 'allow images from listed domains in html emails',
				'name'   => 'allowExternalDomains',
				'values' => $GLOBALS['egw_info']['user']['preferences']['mail']['allowExternalDomains'],
				'no_sel_options' => true
			),
			'new_mail_notification' => [
				'type' => 'select',
				'label' => 'Show notification for new arriving mails',
				'help' => 'Shows a temporary visible notification including from address, subject and a snippet of the mail',
				'name' => 'new_mail_notification',
				'values' => [
					'always' => lang('Always show notifiction'),
					'not-mail' => lang('Only if currently in an other app'),
					'never' => lang('Never show notification'),
				],
				'xmlrpc' => True,
				'admin'  => False,
				'default'=> 'always',
			],
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
			'previewPane' => array(
				'type' => 'select',
				'label' => 'Preview pane',
				'help' => 'Show/Hide preview pane in mail list view',
				'name' => 'previewPane',
				'values' => array(
					'vertical' => lang('show vertical'),
					'fixed' => lang('show horizontal'),
					'expand' => lang('show horizontal, hide if none selected'),
					'hide' => lang('never show'),
				),
				'default' => 'vertical',
				'reload' => true
			),
			'toggledOnActions' => array(
				'type' => 'taglist',
				'label' => 'Toggled on actions',
				'help' => 'List of actions to be switched/activated on by default (eg. Mail compose save as infolog action)',
				'name' => 'toggledOnActions',
				'values' => '',
				'attributes' => array(
					'allowFreeEntries' => false,
					//'multiple' => 'toggle',
					'editModeEnabled' => false,
					'autocomplete_url' => ' ',
					'select_options' => $toggledOnActions
				)
			),
			'identLabel' => array(
				'type' => 'select',
				'label' => 'Identity label',
				'help' => 'Defines what to show as identity label on mail folder header',
				'name' => 'identLabel',
				'values' => array(
					7 => lang('Real name Organization Email'),
					3 => lang('Real name Email'),
					14 => lang('Ident name Organization Email'),
					10 => lang('Ident name Email'),
					1 => lang('Real name'),
					2 => lang('Email'),
					4 => lang('Organization'),
					8 => lang('Ident name'),
					16 => lang('Organization | Real name Email')
				),
				'default' => 7
			),
			'contactLabel' => array(
				'type' => 'taglist',
				'label' => 'Contact label',
				'help' => 'Defines what to show as contact label for added contact into To/Cc/Bcc when composing an email. Default is firstname lastname and empty means include eveything available.',
				'name' => 'contactLabel',
				'values' => $contactLabelOptions,
				'attributes' => array(
					'allowFreeEntries' => false,
					'editModeEnabled' => false,
					'autocomplete_url' => ' ',
					'select_options' => $contactLabelOptions
				),
				'default' => ['n_given','n_family']
			)
		);
		if (!$GLOBALS['egw_info']['apps']['stylite']) unset($settingsArray['attachVCardAtCompose']);

		// if no push configured, do NOT show new-mail-notification preference
		$config = Api\Config::read('mail');
		if (empty($GLOBALS['egw_info']['server']['imap_hosts_with_push']) && empty($config['imap_hosts_with_push']))
		{
			unset($settingsArray['new_mail_notification']);
		}
		return $settingsArray;
	}

	/**
	 * Admin hook
	 *
	 * @param array|string $hook_data
	 */
	static function admin($hook_data)
	{
		unset($hook_data);	// not used, but required by function signature

		unset($GLOBALS['egw_info']['user']['preferences']['common']['auto_hide_sidebox']);
		// Only Modify the $file and $title variables.....
		$title = $appname = 'mail';
		$profileID = 0;
		if (isset($GLOBALS['egw_info']['user']['preferences']['mail']['ActiveProfileID']))
			$profileID = (int)$GLOBALS['egw_info']['user']['preferences']['mail']['ActiveProfileID'];

		$file = Array(
			'Site Configuration' => Egw::link('/index.php',array('menuaction'=>'admin.uiconfig.index','appname'=>'mail','ajax'=>'true')),
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
		unset($hook_data);	// not used, but required by function signature

		//error_log(__METHOD__);
		// always show the side bar
		unset($GLOBALS['egw_info']['user']['preferences']['common']['auto_hide_sidebox']);
		$appname = 'mail';
		$menu_title = $GLOBALS['egw_info']['apps'][$appname]['title'];

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
		// display Mail Tree
		display_sidebox($appname,$menu_title,$file);

		$linkData = array(
			'menuaction' => 'mail.mail_ui.importMessage',
		);

		$file = array(
			'import message' => "javascript:egw_openWindowCentered2('" . Egw::link('/index.php', $linkData, false) . "','importMessageDialog',600,180,'no','$appname');",
		);

		// create account wizard
		if (self::access('createaccount'))
		{
			$file += array(
				'create new account' => "javascript:egw_openWindowCentered2('" .
					Egw::link('/index.php', array('menuaction' => 'mail.mail_wizard.add'), '').
					"','_blank',640,480,'yes')",
			);
		}
		// display Mail Menu
		display_sidebox($appname,$GLOBALS['egw_info']['apps'][$appname]['title'].' '.lang('Menu'),$file);

		if ($GLOBALS['egw_info']['user']['apps']['admin'] && !Api\Header\UserAgent::mobile())
		{
			$file = Array(
				'Site Configuration' => Egw::link('/index.php','menuaction=admin.uiconfig.index&appname=' . $appname),
			);
			display_sidebox($appname,lang('Admin'),$file);
		}

		// add pgp encryption menu at the end
		Api\Hooks::pgp_encryption_menu('mail');

	}

	/**
	 * checks users mailbox and sends a notification if new mails have arrived
	 *
	 * @return boolean true or false
	 */
	static function notification_check_mailbox()
	{
		// should not run more often then every 3 minutes;
		$lastRun = Api\Cache::getCache(Api\Cache::INSTANCE,'email','mailNotifyLastRun'.trim($GLOBALS['egw_info']['user']['account_id']),null,array(),$expiration=60*60*24*2);
		$currentTime = time();
		if (!empty($lastRun) && $lastRun>$currentTime-3*60)
		{
			return true;
		}
		$accountsToSearchObj = Mail\Account::search(true, true);

		foreach($accountsToSearchObj as $acc_id => $identity_name)
		{
				$folders2notify[$acc_id] = Mail\Notifications::read($acc_id);// read all, even those set for acc_id 0 (folders for all acounts?)
			$accountsToSearchArray[$acc_id] = str_replace(array('<','>'),array('[',']'),$identity_name);
		}
		$notified_mail_uidsCache = Api\Cache::getCache(Api\Cache::INSTANCE,'email','notified_mail_uids'.trim($GLOBALS['egw_info']['user']['account_id']),null,array(),$expiration=60*60*24*2);
		if (!is_array($folders2notify)) return true;
		foreach ($folders2notify as $nFKey =>$notifyfolders)
		{
			try
			{
				$currentRecipient = (object)$GLOBALS['egw']->accounts->read(($notifyfolders['notify_account_id']?$notifyfolders['notify_account_id']:$GLOBALS['egw_info']['user']['account_id']));
				$notify_folders = $notifyfolders['notify_folders'];
				if(count($notify_folders) == 0) {
					continue; //no folders configured for notifying
				}
				$activeProfile = $nFKey;
				try
				{
					$bomail = Mail::getInstance(false, $activeProfile,false);
				} catch (Exception $e)
				{
					error_log(__METHOD__.__LINE__.' (user: '.$currentRecipient->account_lid.') notification for Profile:'.$activeProfile.' failed.'.$e->getMessage());
					continue; //fail silently
				}
				$icServerParams=$bomail->icServer->params;
				if (empty($icServerParams['acc_imap_host']))
				{
					error_log(__METHOD__.__LINE__.' (user: '.$currentRecipient->account_lid.') notification for Profile:'.$activeProfile.' failed: NO IMAP HOST configured!');
					continue; //fail silently
				}
				try
				{
					$bomail->openConnection($activeProfile);
				} catch (Exception $e) {
					// TODO: This is ugly. Log a bit nicer!
					$error = $e->getMessage();
					error_log(__METHOD__.__LINE__.' # '.' (user: '.$currentRecipient->account_lid.'): cannot connect to mailbox with Profile:'.$activeProfile.'. Please check your prefs!');
					if (!empty($error)) error_log(__METHOD__.__LINE__.' # '.$error);
					error_log(__METHOD__.__LINE__.' # Instance='.$GLOBALS['egw_info']['user']['domain'].', User='.$GLOBALS['egw_info']['user']['account_lid']);
					return false; // cannot connect to mailbox
				}
				//$notified_mail_uidsCache = array();
				$recent_messages = array();
				$folder_status = array();
				foreach($notify_folders as $id=>$notify_folder) {
					// Allow folder notification on INBOX for popup_only chain
					if (empty($notify_folder) ||
							($notify_folder == 'INBOX' &&
							$GLOBALS['egw_info']['user']['preferences']['notifications']['notification_chain'] != 'popup_only')) continue;
					if(!is_array($notified_mail_uidsCache[$activeProfile][$notify_folder])) {
						$notified_mail_uidsCache[$activeProfile][$notify_folder] = array();
					}
					$folder_status[$notify_folder] = $bomail->getFolderStatus($notify_folder);
					$cutoffdate = time() - (60*60*24*14); // last 14 days
					$_filter = array('status'=>array('UNSEEN','UNDELETED'),'type'=>"SINCE",'string'=> date("d-M-Y", $cutoffdate));
					// $_folderName, $_startMessage, $_numberOfMessages, $_sort, $_reverse, $_filter, $_thisUIDOnly=null, $_cacheResult=true
					$headers = $bomail->getHeaders($notify_folder, 1, 999, 0, true, $_filter,null,false);
					if(is_array($headers['header']) && count($headers['header']) > 0) {
						foreach($headers['header'] as $header) {
							// check if unseen mail has already been notified
							$headerrowid = mail_ui::generateRowID($activeProfile, $notify_folder, $header['uid'], $_prependApp=false);
						 	if(!in_array($headerrowid, $notified_mail_uidsCache[$activeProfile][$notify_folder])) {
						 		// got a REAL recent message
						 		$header['folder'] = $notify_folder;
						 		$header['folder_display_name'] = $folder_status[$notify_folder]['displayName'];
						 		$header['folder_base64'] =  base64_encode($notify_folder);
						 		$recent_messages[] = $header;
						 	}
						}
					}
				}
				if(count($recent_messages) > 0) {
					// create notify message
					$notification_subject = lang("You've got new mail").':'.$accountsToSearchArray[$activeProfile];
					$values = array();
					$values[] = array(); // content array starts at index 1
					foreach($recent_messages as $id=>$recent_message) {
						$values[] =	array(
							'mail_uid'				=> $recent_message['uid'],
							'mail_folder' 			=> $recent_message['folder_display_name'],
							'mail_folder_base64' 	=> $recent_message['folder_base64'],
							'mail_subject'			=> Mail::adaptSubjectForImport($recent_message['subject']),
							'mail_from'				=> !empty($recent_message['sender_name']) ? $recent_message['sender_name'] : $recent_message['sender_address'],
							'mail_received'			=> $recent_message['date'],
							'mail_to'			=> $recent_message['to_address'],
						);
						// save notification status
						$notified_mail_uidsCache[$activeProfile][$recent_message['folder']][] = mail_ui::generateRowID($activeProfile, $recent_message['folder'], $recent_message['uid'], $_prependApp=false);
					}
					foreach ($values as &$mail)
					{
						if ($mail['mail_from'])
						{
							$notification_message .=	"<br/><strong>".lang("From").':</strong>'. $mail["mail_from"].
														"<br/><strong>".lang("To").':</strong>'. $mail["mail_to"].
														"<br/><strong>".lang ("subject"). ':</strong>' . $mail["mail_subject"].
														"<br/><br/> -------------------------------------------------------- </br>";
						}
					}
					// send notification
					$notification = new notifications();
					$notification->set_receivers(array($currentRecipient->account_id));
					$notification->set_message($notification_message);
					$notification->set_sender($currentRecipient->account_id);
					$notification->set_subject($notification_subject);
					$notification->set_skip_backends(array('email'));
					$notification->send();
				}
				Api\Cache::setCache(Api\Cache::INSTANCE,'email','notified_mail_uids'.trim($GLOBALS['egw_info']['user']['account_id']),$notified_mail_uidsCache, $expiration=60*60*24*2);
			} catch (Exception $e) {
				// fail silently per server, if possible
				error_log(__METHOD__.__LINE__.' Notification on new messages for Profile '.$activeProfile.' ('.$accountsToSearchArray[$activeProfile].') failed:'.$e->getMessage());
			}
		}
		Api\Cache::setCache(Api\Cache::INSTANCE,'email','mailNotifyLastRun'.trim($GLOBALS['egw_info']['user']['account_id']),time(), $expiration=60*60*24*2);
		return true;
	}

	/**
	 * Check if current user has access to a specific feature
	 *
	 * Example: if (!mail_hooks::access("managerfolders")) return;
	 *
	 * @param string $feature "createaccounts", "managefolders", "notifications", "filters",
	 *		"notificationformailviaemail", "editfilterrules", "absentnotice", "aclmanagement"
	 * @return boolean true if user has access, false if not
	 */
	public static function access($feature)
	{
		static $config=null;
		if ($GLOBALS['egw_info']['user']['apps']['admin'])
		{
			return true;	// allways give admins or emailadmins all rights, even if they are in a denied group
		}
		if (!isset($config)) $config = (array)Api\Config::read('mail');
		//error_log(__METHOD__.__LINE__.' '.$feature.':'.array2string($config['deny_'.$feature]));
		if (!empty($config['deny_'.$feature]))
		{
			//error_log(__METHOD__.__LINE__.' feature:'.$feature.':'.array2string($config['deny_'.$feature]));
			$denied_groups = (is_array($config['deny_'.$feature])?$config['deny_'.$feature]:explode(',', $config['deny_'.$feature]));
			//error_log(__METHOD__.__LINE__.array2string($GLOBALS['egw']->accounts->memberships($GLOBALS['egw_info']['user']['account_id'], true)));
			//error_log(__METHOD__.__LINE__.array2string(array_intersect($denied_groups, $GLOBALS['egw']->accounts->memberships($GLOBALS['egw_info']['user']['account_id'], true))));
			// since access asks positively, the stored deny_$feature must return false if we find the denied group in the users membership-list
			return (array_intersect($denied_groups, $GLOBALS['egw']->accounts->memberships($GLOBALS['egw_info']['user']['account_id'], true))?false:true);
		}
		return true;
	}

	/**
	 * Add mail related actions into status action
	 *
	 * @return array returns array of actions
	 */
	public static function get_status_actions ()
	{
		return [
			'mail' => [
				'caption' => 'Mail',
				'icon' => 'mail/navbar',
				'allowOnMultiple' => false,
				'group' => 1,
				'onExecute' => 'javaScript:app.status.handle_actions',
			]
		];
	}

	/**
	 * Called before displaying site configuration
	 *
	 * @param array $config
	 * @return array with additional config to merge and "sel_options" values
	 */
	public static function config(array $config)
	{
		return [
			'html_toolbar' => empty($config['html_toolbar']) ? join(',', Api\Etemplate\Widget\HtmlArea::$toolbar_default_list) : $config['html_toolbar'],
			'sel_options' => [
				'html_toolbar' => Api\Etemplate\Widget\HtmlArea::get_toolbar_as_selOptions()
			]
		];
	}

	/**
	 * options for attachments block actions
	 * @return string[][]
	 */
	public static function attachmentsBlockActions()
	{
		$actions = [
			'downloadOneAsFile' => [
				'id'    => 'downloadOneAsFile',
				'label' => 'Download',
				'icon'  => 'fileexport',
				'value' => 'downloadOneAsFile'
			],
			'saveOneToVfs'      => [
				'id'    => 'saveOneToVfs',
				'label' => 'Save in Filemanager',
				'icon'  => 'filemanager/navbar',
				'value' => 'saveOneToVfs'
			],
			'saveAllToVfs'      => [
				'id'    => 'saveAllToVfs',
				'label' => 'Save all to Filemanager',
				'icon'  => 'mail/save_all',
				'value' => 'saveAllToVfs'
			],
			'downloadAllToZip'  => [
				'id'    => 'downloadAllToZip',
				'label' => 'Save as ZIP',
				'icon'  => 'mail/save_zip',
				'value' => 'downloadAllToZip'
			]
		];
		if (!empty($GLOBALS['egw_info']['user']['apps']['collabora']))
		{
			$actions['collabora'] = [
				'id'    => 'collabora',
				'label' => 'Open with Collabora',
				'icon'  => 'open',
				'value' => 'collabora'
			];
		}
		return $actions;
	}
}
