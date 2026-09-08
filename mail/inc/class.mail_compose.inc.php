<?php
/**
 * EGroupware - Mail - interface class for compose mails in popup
 *
 * @link http://www.egroupware.org
 * @package mail
 * @author EGroupware GmbH [info@egroupware.org]
 * @copyright (c) 2013-2016 by EGroupware GmbH <info-AT-egroupware.org>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

use EGroupware\Api;
use EGroupware\Api\Acl;
use EGroupware\Api\Egw;
use EGroupware\Api\Etemplate;
use EGroupware\Api\Framework;
use EGroupware\Api\Link;
use EGroupware\Api\Mail;
use EGroupware\Api\Mail\AddressList;
use EGroupware\Api\Mail\BodyDecoding;
use EGroupware\Api\Vfs;
use EGroupware\Mail\ComposeMessageBuilder;
use EGroupware\Mail\Ui\AttachmentJmap;
use EGroupware\Mail\Ui\BodyHandler;

/**
 * Mail interface class for compose mails in popup
 */
class mail_compose
{
	use ComposeMessageBuilder;

	var $public_functions = array
	(
		'getAttachment'		=> True,
	);

	/**
	 * class vars for destination, priorities, mimeTypes
	 */
	static $destinations = array(
		'to' 		=> 'to',  // lang('to')
		'cc'		=> 'cc',  // lang('cc')
		'bcc'		=> 'bcc', // lang('bcc')
		'replyto'	=> 'replyto', // lang('replyto')
		'folder'	=> 'folder'  // lang('folder')
	);
	static $priorities = array(
		1=>"high", // lang('high')
		3=>"normal", // lang('normal')
		5=>"low"  // lang('low')
	);
	static $mimeTypes = array(
		"plain"=>"plain",
		"html"=>"html"
	);
	/**
	 * our Dovecot limits Mails to 39MB overall,
	 * so we assume a max attachment size of 38MB if nothing is set in Mail app config
	 **/
	static int $maxAttachmentSizeDefault = 26;

	// $mail_bo/$mailPreferences/$displayCharset are declared by the ComposeMessageBuilder trait
	var $attachments;	// Array of attachments
	var $composeID;
	var $sessionData;
	private bool $preventAttachFilemode = false;

	function __construct(?int $_acc_id=null)
	{
		$this->initMailAccount($_acc_id);
	}

	/**
	 * Provide toolbar actions used for compose toolbar
	 * @param array $content content of compose temp
	 *
	 * @return array an array of actions
	 */
	static function getToolbarActions($content)
	{
		$group = 0;
		$actions = array(
			'send' => array(
				'caption' => 'Send',
				'icon'	=> 'mail_send',
				'group' => ++$group,
				'onExecute' => 'javaScript:app.mail.compose.submitAction',
				'hint' => 'Send',
				'shortcut' => Etemplate\KeyManager::shortcut(Etemplate\KeyManager::S,false,true),
				'toolbarDefault' => true
			),
			'button[saveAsDraft]' => array(
				'caption' => 'Save',
				'icon' => 'apply',
				'group' => ++$group,
				'onExecute' => 'javaScript:app.mail.compose.saveAsDraft',
				'hint' => 'Save as Draft',
				'toolbarDefault' => true
			),
			'button[saveAsDraftAndPrint]' => array(
				'caption' => 'Print',
				'icon' => 'print',
				'group' => $group,
				'onExecute' => 'javaScript:app.mail.compose.saveAsDraft',
				'hint' => 'Save as Draft and Print'
			),
			'save2vfs' => array (
				'caption' => 'Save to filemanager',
				'icon' => 'filesave',
				'group' => $group,
				'onExecute' => 'javaScript:app.mail.compose.saveDraft2fm',
				'hint' => 'Save the drafted message as eml file into VFS'
			),
			'selectFromVFSForCompose' => array(
				'caption' => 'VFS',
				'icon' => 'filemanager/navbar',
				'group' => ++$group,
				'onExecute' => 'javaScript:app.mail.compose.triggerWidget',
				'hint' => 'Select file(s) from VFS',
				'toolbarDefault' => true
			),
			'uploadForCompose' => array(
				'caption' => 'Upload files...',
				'icon' => 'attach',
				'group' => $group,
				'onExecute' => 'javaScript:app.mail.compose.triggerWidget',
				'hint' => 'Select files to upload',
				'toolbarDefault' => true
			),
			'to_infolog' => array(
				'caption' => 'Infolog',
				'icon' => 'infolog/navbar',
				'group' => ++$group,
				'checkbox' => true,
				'hint' => 'check to save as infolog on send',
				'toolbarDefault' => true,
				'onExecute' => 'javaScript:app.mail.compose.setToggle'
			),
			'to_tracker' => array(
				'caption' => 'Tracker',
				'icon' => 'tracker/navbar',
				'group' => $group,
				'checkbox' => true,
				'hint' => 'check to save as tracker entry on send',
				'onExecute' => 'javaScript:app.mail.compose.setToggle',
				'mail_import' => Api\Hooks::single(array('location' => 'mail_import'),'tracker'),
			),
			'to_calendar' => array(
				'caption' => 'Calendar',
				'icon' => 'calendar/navbar',
				'group' => $group,
				'checkbox' => true,
				'hint' => 'check to save as calendar event on send',
				'onExecute' => 'javaScript:app.mail.compose.setToggle'
			),
			'addressbook' => array(
				'caption' => 'Addressbook',
				'icon' => 'addressbook/navbar',
				'group' => $group,
				'hint' => 'Select mail addresses from addressbook',
				'onExecute' => 'javaScript:app.mail.addressbookSelect',
				'toolbarDefault' => false,
			),
			'disposition' => array(
				'caption' => 'Notification',
				'icon' => 'notification',
				'group' => ++$group,
				'checkbox' => true,
				'hint' => 'check to receive a notification when the message is read (note: not all clients support this and/or the receiver may not authorize the notification)',
				'onExecute' => 'javaScript:app.mail.compose.setToggle'
			),
			'prty' => array(
				'caption' => 'Priority',
				'group' => $group,
				'icon' => 'priority',
				'children' => array(),
				'hint' => 'Select the message priority tag',
			),
			'pgp' => array(
				'caption' => 'Encrypt',
				'icon' => 'lock',
				'group' => ++$group,
				'onExecute' => 'javaScript:app.mail.togglePgpEncrypt',
				'hint' => 'Send message PGP encrypted: requires keys from all recipients!',
				'checkbox' => true,
				'toolbarDefault' => true
			),

		);
		$acc_smime = Mail\Smime::get_acc_smime($content['mailaccount']);
		if ($acc_smime && !empty($acc_smime['acc_smime_password']))
		{
			$actions = array_merge($actions, array(
				'smime_sign' => array (
					'caption' => 'SMIME Sign',
					'icon' => 'smime_sign',
					'group' => $group,
					'onExecute' => 'javaScript:app.mail.compose.setToggle',
					'checkbox' => true,
					'hint' => 'Sign your message with smime certificate'
				),
				'smime_encrypt' => array (
					'caption' => 'SMIME Encryption',
					'icon' => 'smime_encrypt',
					'group' => $group,
					'onExecute' => 'javaScript:app.mail.compose.setToggle',
					'checkbox' => true,
					'hint' => 'Encrypt your message with smime certificate'
			)));
		}
		foreach (self::$priorities as $key => $priority)
		{
			$actions['prty']['children'][$key] = array(
						'caption' => $priority,
						'icon' => 'prio_high',
						'default' => false,
						'onExecute' => 'javaScript:app.mail.compose.priorityChange'
			);
			switch ($priority)
			{
				case 'high':
					$actions['prty']['children'][$key]['icon'] = 'prio_high';
					break;
				case 'normal':
					$actions['prty']['children'][$key]['icon'] = 'priority';
					break;
				case 'low':
					$actions['prty']['children'][$key]['icon'] = 'prio_low';
			}
		}
		// Set the priority action its current state
		if ($content['priority'])
		{
			$actions['prty']['children'][$content['priority']]['default'] = true;
		}
		if (Api\Header\UserAgent::mobile())
		{
			foreach (array_keys($actions) as $key)
			{
				if (!in_array($key, array('send','button[saveAsDraft]','uploadForCompose' ))) {
					$actions[$key]['toolbarDefault'] = false;
				}
			}
			unset($actions['pgp']);
		}
		$toggledOnActions = is_array($GLOBALS['egw_info']['user']['preferences']['mail']['toggledOnActions']) ?
			$GLOBALS['egw_info']['user']['preferences']['mail']['toggledOnActions'] :
			explode(',', $GLOBALS['egw_info']['user']['preferences']['mail']['toggledOnActions']);
		foreach($toggledOnActions as $action)
		{
			if(!empty($actions[$action]['checkbox']))
			{
				$actions[$action]['checked'] = true;
			}
		}
		if (!empty($GLOBALS['egw_info']['server']['disable_pgp_encryption'])) unset($actions['pgp']);
		// remove vfs actions if the user has no run access to filemanager
		if (empty($GLOBALS['egw_info']['user']['apps']['filemanager']))
		{
			unset($actions['save2vfs']);
			unset($actions['selectFromVFSForCompose']);
		}
		if (!isset($GLOBALS['egw_info']['user']['apps']['infolog']))
		{
			unset($actions['to_infolog']);
		}
		if (!isset($GLOBALS['egw_info']['user']['apps']['tracker']))
		{
			unset($actions['to_tracker']);
		}
		if (!isset($GLOBALS['egw_info']['user']['apps']['calendar']))
		{
			unset($actions['to_calendar']);
		}
		return $actions;
	}

	/**
	 * Toolbar-action-tree + sel_options needed to bootstrap a client-side compose popup
	 *
	 * getToolbarActions()'s own output (captions/icons/onExecute strings/shortcuts) plus the
	 * mailaccount/mimeType/priority/filemode sel_options - everything compose()'s classic
	 * postback normally computes server-side EXCEPT the mail_compose_prepare hook's own
	 * content/readonlys (a separate endpoint, gated by Api\Hooks::count() - doc/ai/projects/
	 * mail-compose-jmap-migration.md, Step 10, Phase B/2a).
	 *
	 * Mostly static per account for a session - mail/js/app.ts's MailApp.getComposeToolbarData()
	 * caches this client-side keyed by account id, on the main window's own instance, so a
	 * clientSidePopup() compose only calls this once per account for the life of that window,
	 * not once per popup open.
	 *
	 * @param int|string|null $_acc_id account/profile id, defaults to the user's active account
	 */
	function ajax_getComposeToolbarData($_acc_id=null)
	{
		if ($_acc_id && $this->mail_bo->profileID != (int)$_acc_id)
		{
			$this->changeProfile((int)$_acc_id);
		}

		// same identities/sel_options computation as compose(), lines ~1381-1393 and 1484-1489
		$sel_options = array('mailaccount' => array());
		foreach (Mail\Account::search(true, false) as $acc_id => $account)
		{
			// do NOT add SMTP only accounts as identities
			if (!$account->is_imap(false)) continue;

			foreach ($account->identities($acc_id) as $ident_id => $identity)
			{
				$sel_options['mailaccount'][$acc_id.':'.$ident_id] = $identity;
			}
		}
		$sel_options['mimeType'] = self::$mimeTypes;
		$sel_options['priority'] = self::$priorities;
		$sel_options['filemode'] = Vfs\Sharing::$modes;
		if ($this->preventAttachFilemode)
		{
			unset($sel_options['filemode'][Vfs\Sharing::ATTACH]);
		}

		// baseline content for a BLANK new compose - setDefaults() picks the same
		// identity/mimeType a classic blank compose() call would (LastSignatureIDUsed pref, or
		// the first identity with a non-empty signature); everything else here mirrors compose()'s
		// own static (not reply/attachment-dependent) content keys, lines ~1500-1533. A real
		// reply/forward/composeasnew overwrites to/cc/subject/body/mailaccount/mimeType itself
		// (MailCompose.bootstrapReply()/bootstrapComposeAsNew(), already JMAP-native) once the
		// popup's own bootstrap fetches the source message - this is only ever the STARTING point.
		$content = $this->setDefaults(array('mailaccount' => $this->mail_bo->profileID));
		$content['mailaccount'] = $this->mail_bo->profileID.':'.$content['mailidentity'];
		if (!in_array($content['mailaccount'], array_keys($sel_options['mailaccount'])))
		{
			foreach ($sel_options['mailaccount'] as $ident => $value)
			{
				$idnt_acc_parts = explode(':', $ident);
				if ($content['mailidentity'] == $idnt_acc_parts[1])
				{
					$content['mailaccount'] = $ident;
					break;
				}
			}
		}
		$content['is_html'] = ($content['mimeType'] == 'html' ? true : '');
		$content['is_plain'] = ($content['mimeType'] == 'html' ? '' : true);
		$content['priority'] = 3;
		$content['filemode'] = $this->preventAttachFilemode ? Vfs\Sharing::READONLY : Vfs\Sharing::ATTACH;
		$content['no_griddata'] = true;
		$content['expiration_blur'] = $GLOBALS['egw_info']['user']['apps']['stylite'] ? lang('Select a date') : lang('EPL only');
		if (empty($GLOBALS['egw_info']['user']['apps']['filemanager']))
		{
			$content['vfsNotAvailable'] = "mail_DisplayNone";
		}
		if (empty($GLOBALS['egw_info']['user']['apps']['infolog']))
		{
			$content['noInfologAvailable'] = "mail_DisplayNone";
		}
		if (empty($GLOBALS['egw_info']['user']['apps']['tracker']))
		{
			$content['noTrackerAvailable'] = "mail_DisplayNone";
		}
		if (empty($GLOBALS['egw_info']['user']['apps']['infolog']) && empty($GLOBALS['egw_info']['user']['apps']['tracker']))
		{
			$content['noSaveAsAvailable'] = "mail_DisplayNone";
		}
		$content['html_toolbar'] = empty(Mail::$mailConfig['html_toolbar']) ?
			implode(',', Etemplate\Widget\HtmlArea::$toolbar_default_list) : implode(',', Mail::$mailConfig['html_toolbar']);
		$content['attachmentLimitMb'] = Api\Config::read('mail')['attachment_limit_mb'] ?: self::$maxAttachmentSizeDefault;

		// "predefined compose addresses" account preference (set via mail's account-settings UI,
		// mail/js/app.ts:8035's own pref_id convention) - compose()'s own equivalent merge, lines
		// ~1109-1119, runs unconditionally there (not just for a blank compose), so a classic reply
		// gets these appended alongside its own to/cc too; here they only ever survive into a
		// genuinely blank new compose - MailCompose.bootstrapReply()/bootstrapComposeAsNew()
		// OVERWRITE (not append to) to/cc/bcc via set_value() once their own JMAP fetch resolves,
		// same as any other baseline content key those methods touch (found live 2026-09-06,
		// checking off doc/ai/projects/mail-compose-jmap-migration.md Step 10's own "predefined
		// compose addresses" gap - ralf: appending onto an already-JMAP-fetched reply's own
		// recipients is a separate, more invasive change than closing the common blank-compose case).
		$preferencePreset = $GLOBALS['egw_info']['user']['preferences']['mail'][$this->mail_bo->profileID.'_predefined_compose_addresses'] ?? [];
		foreach ($preferencePreset as $pref => $values)
		{
			if (!empty($values))
			{
				$content[$pref] = array_merge((array)($content[$pref] ?? []), (array)$values);
			}
		}

		$actions = self::getToolbarActions($content);

		Api\Json\Response::get()->data(array(
			'actions' => $actions,
			'sel_options' => $sel_options,
			'content' => $content,
		));
	}

	/**
	 * Run the mail_compose_prepare hook, merging any registrant's returned content/readonlys/
	 * preserv/sel_options on top of the given ones - extracted out of compose() so a fully
	 * client-driven bootstrap (ajax_prepareCompose()) can run the exact same hook contract without
	 * any of compose()'s own (expensive) content-preparation/Etemplate rendering. A registrant's
	 * own implementation (eg. achelper's) is unaffected either way - it still receives and returns
	 * the exact same {content, readonlys, sel_options} shape it always has.
	 *
	 * @param array $content
	 * @param array $readonlys
	 * @param array $sel_options
	 * @param array $preserv
	 * @return array [$content, $readonlys, $sel_options, $preserv] all merged
	 */
	private static function runComposePrepareHook(array $content, array $readonlys, array $sel_options, array $preserv) : array
	{
		$temp = Api\Hooks::process(array(
			'location' => 'mail_compose_prepare',
			'content' => $content,
			'readonlys' => $readonlys,
			'sel_options' => $sel_options,
		));

		foreach ($temp as $hook)
		{
			if ($hook)
			{
				// merge INTO the existing arrays: this used to merge $hook['preserv']/['sel_options']
				// on top of $readonlys instead of $preserv/$sel_options themselves - a copy-paste bug
				// that silently discarded everything compose() had already preserved (attachments,
				// mode, mimeType, ...) and every sel_option the hook itself didn't echo back, for
				// EVERY compose with any mail_compose_prepare hook registered - found via a 3rd-party
				// (achelper) patch, 2026-09-03
				$content = array_merge($content, $hook['content'] ?? []);
				$readonlys = array_merge($readonlys, $hook['readonlys'] ?? []);
				$preserv = array_merge($preserv, $hook['preserv'] ?? []);
				$sel_options = array_merge($sel_options, $hook['sel_options'] ?? []);
			}
		}
		return [$content, $readonlys, $sel_options, $preserv];
	}

	/**
	 * Standalone JSON endpoint for the mail_compose_prepare hook, for a fully client-driven compose
	 * bootstrap (doc/ai/projects/mail-compose-jmap-migration.md, Step 10 - "mail_compose_prepare
	 * hook survival" design sketch). A registrant is a normal Api\Hooks::process() handler reading
	 * whatever $_GET/$_REQUEST params it defines itself (eg. achelper's own mode/template/info_id -
	 * never from/id, so this never collides with MailApp.bootstrapComposePopup()'s own dispatch),
	 * given the untouched (empty) $content/readonlys/sel_options a genuinely-new blank compose would
	 * start from, returning modified copies via runComposePrepareHook() above - the exact same
	 * contract compose() itself still uses, so an existing hook implementation needs zero code
	 * changes.
	 *
	 * Only called from the client when JmapToken's own hasComposePrepareHook flag is true
	 * (Api\Hooks::count('mail_compose_prepare') > 0, see ProfileHandler::jmapBootstrap()) - most
	 * installs have nothing registered here at all, so this round trip is opt-in per install, not a
	 * cost every compose pays for.
	 *
	 * @return void writes {content, readonlys, sel_options, preserv} via Api\Json\Response
	 */
	function ajax_prepareCompose()
	{
		[$content, $readonlys, $sel_options, $preserv] = self::runComposePrepareHook([], [], [], []);

		Api\Json\Response::get()->data(compact('content', 'readonlys', 'sel_options', 'preserv'));
	}

	function getAttachment()
	{
		// read attachment data from etemplate request, use tmpname only to identify it
		if (($request = Etemplate\Request::read($_GET['etemplate_exec_id'])))
		{
			foreach($request->preserv['attachments'] as $attachment)
			{
				if ($_GET['tmpname'] === $attachment['tmp_name']) break;
			}
		}
		if (!$request || $_GET['tmpname'] !== $attachment['tmp_name'])
		{
			header('HTTP/1.1 404 Not found');
			die('Attachment '.htmlspecialchars($_GET['tmpname']).' NOT found!');
		}

		//error_log(__METHOD__.__LINE__.array2string($_GET));
		if (parse_url($attachment['tmp_name'],PHP_URL_SCHEME) == 'vfs')
		{
			Vfs::load_wrapper('vfs');
		}
		// attachment data in temp_dir, only use basename of given name, to not allow path traversal
		else
		{
			$attachment['tmp_name'] = $GLOBALS['egw_info']['server']['temp_dir'].'/'.basename($attachment['tmp_name']);
		}
		if(!file_exists($attachment['tmp_name']))
		{
			header('HTTP/1.1 404 Not found');
			die('Attachment '.htmlspecialchars($attachment['tmp_name']).' NOT found!');
		}
		$attachment['attachment'] = file_get_contents($attachment['tmp_name']);

		//error_log(__METHOD__.__LINE__.' FileSize:'.filesize($attachment['tmp_name']));
		if ($_GET['mode'] != "save")
		{
			if (strtoupper($attachment['type']) == 'TEXT/DIRECTORY')
			{
				$sfxMimeType = $attachment['type'];
				$buff = explode('.',$attachment['tmp_name']);
				$suffix = '';
				if (is_array($buff)) $suffix = array_pop($buff); // take the last extension to check with ext2mime
				if (!empty($suffix)) $sfxMimeType = Api\MimeMagic::ext2mime($suffix);
				$attachment['type'] = $sfxMimeType;
				if (strtoupper($sfxMimeType) == 'TEXT/VCARD' || strtoupper($sfxMimeType) == 'TEXT/X-VCARD') $attachment['type'] = strtoupper($sfxMimeType);
			}
			//error_log(__METHOD__.print_r($attachment,true));
			if (strtoupper($attachment['type']) == 'TEXT/CALENDAR' || strtoupper($attachment['type']) == 'TEXT/X-VCALENDAR')
			{
				//error_log(__METHOD__."about to call calendar_ical");
				$calendar_ical = new calendar_ical();
				$eventid = $calendar_ical->iCalSearch($attachment['attachment'],-1);
				//error_log(__METHOD__.array2string($eventid));
				if (!$eventid) $eventid = -1;
				$event = $calendar_ical->importVCal($attachment['attachment'],(is_array($eventid)?$eventid[0]:$eventid),null,true);
				//error_log(__METHOD__.$event);
				if ((int)$event > 0)
				{
					$vars = array(
						'menuaction'      => 'calendar.calendar_uiforms.edit',
						'cal_id'      => $event,
					);
					$GLOBALS['egw']->redirect_link('../index.php',$vars);
				}
				//Import failed, download content anyway
			}
			if (strtoupper($attachment['type']) == 'TEXT/X-VCARD' || strtoupper($attachment['type']) == 'TEXT/VCARD')
			{
				$addressbook_vcal = new addressbook_vcal();
				// double \r\r\n seems to end a vcard prematurely, so we set them to \r\n
				//error_log(__METHOD__.__LINE__.$attachment['attachment']);
				$attachment['attachment'] = str_replace("\r\r\n", "\r\n", $attachment['attachment']);
				$vcard = $addressbook_vcal->vcardtoegw($attachment['attachment']);
				if ($vcard['uid'])
				{
					$vcard['uid'] = trim($vcard['uid']);
					//error_log(__METHOD__.__LINE__.print_r($vcard,true));
					$contact = $addressbook_vcal->find_contact($vcard,false);
				}
				if (!$contact) $contact = null;
				// if there are not enough fields in the vcard (or the parser was unable to correctly parse the vcard (as of VERSION:3.0 created by MSO))
				if ($contact || count($vcard)>2)
				{
					$contact = $addressbook_vcal->addVCard($attachment['attachment'],(is_array($contact)?array_shift($contact):$contact),true);
				}
				if ((int)$contact > 0)
				{
					$vars = array(
						'menuaction'	=> 'addressbook.addressbook_ui.edit',
						'contact_id'	=> $contact,
					);
					$GLOBALS['egw']->redirect_link('../index.php',$vars);
				}
				//Import failed, download content anyway
			}
		}
		//error_log(__METHOD__.__LINE__.'->'.array2string($attachment));
		$size = 0;
		Api\Header\Content::safe($attachment['attachment'], $attachment['name'], $attachment['type'], $size, true, $_GET['mode'] == "save");
		echo $attachment['attachment'];

		exit();
	}

	/**
	 * JMAP-native send's own equivalent of sendMessage()'s to_infolog/to_tracker/to_calendar
	 * cross-app-integration popup (mail/js/compose.ts's trySendViaJmap()/integrateSentMessage(),
	 * 2026-09-02 - previously a jmapEligible() blocker, see its own docblock in compose.ts).
	 * sendMessage()'s own version of this block (below, `if($_formData['composeToolbar']
	 * ['to_infolog'] || ...)`) runs right after building a classic Api\Mailer and reads
	 * $this->sessionData - neither exists for a JMAP-native send at all, so every input here is
	 * client-supplied instead.
	 *
	 * Attachments are pre-resolved to real temp files HERE, the one genuinely new piece - by the
	 * time get_integrate_data() (mail_integration.inc.php, called from mail_integration::integrate()
	 * once the popup this method opens actually loads) runs, every attachment already looks exactly
	 * like a classic upload (a real, already-existing `file` path), so that method needs no
	 * JMAP-awareness of its own at all.
	 *
	 * @param array $params {
	 *   appKeys: string[] one or more of 'to_infolog','to_tracker','to_calendar' (composeToolbar's
	 *     own checkbox ids - matches sendMessage()'s substr($app_key,3) convention for the app name),
	 *   entryId: ?string 'app:id' of an existing entry to link into (to_integrate_ids's first tag),
	 *   mailaddresses: array{to?:array,cc?:array,bcc?:array} - 'from' is added here, server-side,
	 *     from the sending account's own identity (matching sendMessage()'s $activeMailProfile
	 *     fallback - compose.ts has no separate "from identity" field to read one from at all),
	 *   subject: string,
	 *   body: string - isHtml decides whether this needs convertHTMLToText() first,
	 *   isHtml: bool,
	 *   attachments: array[] {name,type,size,blobId?,vfsPath?} - MailCompose.currentEmailFields()'s
	 *     own already-uploaded-to-THIS-account shape (uploadAttachmentsViaJmap()'s output, the exact
	 *     same array the message itself was actually sent with) - no jmapProfileID per row needed,
	 *     unlike createMessage()'s own jmapBlobId/jmapVfsPath branches, since re-upload-to-the-
	 *     right-account already happened client-side before send,
	 *   eml: string raw RFC 5322 source of the just-sent message (MailJmap.fetchRawSource()),
	 *   profileID: string acc_id (mail account, not EGroupware account_id) the message was sent from,
	 * }
	 */
	/**
	 * Resolve MailCompose.uploadAttachmentsViaJmap()'s output shape ({blobId,name,type,size} an
	 * already-uploaded JMAP blob, or {vfsPath,name,type,size} a bare VFS path never downloaded
	 * client-side) into a REAL local `file` path each - the one thing every classic-style consumer
	 * below (mail_integration::get_integrate_data(), _getAttachmentLinks()) actually needs, neither
	 * of which knows anything about JMAP blobs/VFS paths. Shared by ajax_integrateSent() and
	 * ajax_getAttachmentLinksBody() (2026-09-03).
	 *
	 * @param array $attachments {blobId?,vfsPath?,name?,type?,size?}[]
	 * @param int $profileID acc_id (for resolving a blobId - blobs are per-account)
	 * @return array same shape, `file` added; entries that couldn't be resolved (eg. an
	 *  expired/deleted blob) are dropped, best-effort
	 */
	private function resolveJmapAttachmentsToFiles(array $attachments, int $profileID) : array
	{
		$resolved = [];
		foreach ($attachments as $attachment)
		{
			if (!empty($attachment['blobId']))
			{
				$bytes = AttachmentJmap::fetchBlobBytes((string)$profileID, $attachment['blobId'],
					$attachment['name'] ?? 'attachment', $attachment['type'] ?? 'application/octet-stream');
				if ($bytes === null) continue;	// gone/expired - best-effort, skip
				$file = tempnam($GLOBALS['egw_info']['server']['temp_dir'], 'mail_link_');
				file_put_contents($file, $bytes);
				$attachment['file'] = $file;
			}
			elseif (!empty($attachment['vfsPath']))
			{
				Vfs::load_wrapper('vfs');
				$attachment['file'] = Vfs::PREFIX.$attachment['vfsPath'];
			}
			else
			{
				continue;
			}
			$resolved[] = $attachment;
		}
		return $resolved;
	}

	/**
	 * JMAP-native equivalent of createMessage()'s own filemode!=='attach' handling (the
	 * `_getAttachmentLinks()` call + html/plain splice logic right after it) -
	 * MailCompose.jmapEligible() (compose.ts) used to treat any filemode other than "attach" as a
	 * blocker, forcing a classic-postback fallback purely to get this share-link generation
	 * (doc/ai/projects/mail-compose-jmap-migration.md, "Share-as-link attachments" backlog item,
	 * 2026-09-03). compose.ts's own uploadAttachmentsViaJmap() already normalizes every attachment
	 * shape (a locally-staged upload, an already-uploaded JMAP blob, a bare VFS path) into
	 * {blobId|vfsPath,...} BEFORE calling this - resolveJmapAttachmentsToFiles() turns those back
	 * into real files, then the resolved attachment is discarded from the actual JMAP Email
	 * (MailCompose.currentEmailFields() never includes it once this ran) - matching classic's own
	 * "shared as a link INSTEAD of an attachment" semantics exactly, not "both".
	 *
	 * Unlike classic's createMessage(), there is no `$_autosaving` guard here - JMAP-mode compose
	 * already keeps draft-save and send on separate code paths (currentEmailFields()'s own
	 * `forSend` param), so this is only ever called for an actual send, never an autosave/manual
	 * draft save (which still uploads the real blob normally, same as classic's own stored-draft
	 * copy does - a link is only ever generated once, for the message that's actually transmitted).
	 *
	 * @param array $params {
	 *   profileID: string acc_id (mail account, for resolving a blobId attachment),
	 *   body: string current, already-final (signature/quote already inserted client-side) body,
	 *   isHtml: bool,
	 *   filemode: string one of Vfs\Sharing::{LINK,READONLY,WRITABLE} ('attach' is a no-op below),
	 *   attachments: array[] {name,type,size,blobId?,vfsPath?} - MailCompose.uploadAttachmentsViaJmap()'s
	 *     own output shape,
	 *   to/cc/bcc: string[] recipient addresses - passed to Vfs\Sharing::create() as share recipients,
	 *   expiration: ?string, password: ?string,
	 * }
	 * @return string $params['body'] with the attachment-links block spliced in (or appended, since
	 *  a JMAP-mode body never contains classic's own placeholder markup - kept for parity anyway) -
	 *  unchanged if filemode is 'attach' or nothing could be resolved
	 */
	public function ajax_getAttachmentLinksBody(array $params) : string
	{
		$body = (string)($params['body'] ?? '');
		$filemode = (string)($params['filemode'] ?? Vfs\Sharing::ATTACH);
		if ($filemode === Vfs\Sharing::ATTACH || empty($params['attachments']))
		{
			return $body;
		}
		$profileID = (int)($params['profileID'] ?? 0);
		$attachments = $this->resolveJmapAttachmentsToFiles((array)$params['attachments'], $profileID);
		if (!$attachments)
		{
			return $body;
		}
		$isHtml = !empty($params['isHtml']);
		$recipients = array_unique(array_merge((array)($params['to'] ?? []), (array)($params['cc'] ?? []),
			(array)($params['bcc'] ?? [])));
		$attachment_links = $this->_getAttachmentLinks($attachments, $filemode, $isHtml, $recipients,
			$params['expiration'] ?? null, $params['password'] ?? null);

		if (empty($attachment_links))
		{
			return $body;
		}
		// same placeholder-matching createMessage() uses, kept for parity though a JMAP-mode body
		// (TinyMCE-composed, no classic etemplate markup) realistically only ever hits the final
		// plain-append fallback below
		static $attachment_block = '<fieldset class="attachments mceNonEditable"';
		if ($isHtml && strpos($body, $attachment_block) !== false)
		{
			$body = preg_replace('#'.$attachment_block.'[^>]*>.*</fieldset>#', $attachment_links, $body);
		}
		elseif ($isHtml && strpos($body, '<!-- HTMLSIGBEGIN -->') !== false)
		{
			$body = str_replace('<!-- HTMLSIGBEGIN -->', $attachment_links.'<!-- HTMLSIGBEGIN -->', $body);
		}
		else
		{
			$body .= $attachment_links;
		}
		return $body;
	}

	function ajax_integrateSent(array $params)
	{
		$profileID = (int)($params['profileID'] ?? 0);
		$activeMailProfile = Mail\Account::read($profileID);

		$attachments = $this->resolveJmapAttachmentsToFiles((array)($params['attachments'] ?? []), $profileID);

		$eml = tempnam($GLOBALS['egw_info']['server']['temp_dir'], 'mail_integrate_');
		file_put_contents($eml, (string)($params['eml'] ?? ''));

		$mailaddresses = (array)($params['mailaddresses'] ?? []);
		if (!empty($activeMailProfile['ident_email']))
		{
			$mailaddresses['from'] = Mail\Html::decodeMailHeader($activeMailProfile['ident_email']);
		}

		$body = (string)($params['body'] ?? '');
		if (!empty($params['isHtml']))
		{
			$body = $this->convertHTMLToText($body);
		}

		$entryId = null;
		if (!empty($params['entryId']))
		{
			[, $entryId] = explode(':', (string)$params['entryId']) + [null, null];
		}

		foreach ((array)($params['appKeys'] ?? []) as $app_key)
		{
			if (!in_array($app_key, ['to_infolog', 'to_tracker', 'to_calendar'], true)) continue;

			$app_name = substr($app_key, 3);
			$hook = Api\Hooks::single(['location' => 'mail_import'], $app_name);
			if (empty($hook['menuaction'])) continue;

			$target = [
				'menuaction' => $hook['menuaction'],
				'egw_data' => Link::set_data(null, 'mail_integration::integrate', [
					$mailaddresses,
					(string)($params['subject'] ?? ''),
					$body,
					$attachments,
					false,	// date
					$eml,
					(string)$profileID,
				], true),
				'app' => $app_name,
			];
			if ($entryId) $target['entry_id'] = $entryId;

			Framework::popup(Egw::link('/index.php', $target), '_blank', $hook['popup'] ?? '640x480');
		}
	}

	/**
	 * Save compose mail as draft
	 *
	 * @param array $content content sent from client-side
	 * @param string $action ='button[saveAsDraft]' 'autosaving', 'button[saveAsDraft]' or 'button[saveAsDraftAndPrint]'
	 */
	public function ajax_saveAsDraft ($content, $action='button[saveAsDraft]')
	{
		// release session, as we don't need it and it blocks parallel requests
		$GLOBALS['egw']->session->commit_session();

		//error_log(__METHOD__.__LINE__.array2string($content)."(, action=$action)");
		$response = Api\Json\Response::get();
		$success = true;

		// check if default account is changed then we need to change profile
		if (!empty($content['serverID']) && $content['serverID'] != $this->mail_bo->profileID)
		{
			$this->changeProfile($content['serverID']);
		}

		$formData = array_merge($content, array(
			'isDrafted' => 1,
			'body' => $content['mail_'.($content['mimeType']?'htmltext':'plaintext')],
			'mimeType' => $content['mimeType']?'html':'plain' // checkbox has only true|false value
		));

		//Saving draft procedure
		try
		{
			$folder = $this->mail_bo->getDraftFolder();
			$this->mail_bo->reopen($folder);
			$status = $this->mail_bo->getFolderStatus($folder);
			if (($messageUid = $this->saveAsDraft($formData, $folder, $action)))
			{
				// saving as draft, does not mean closing the message
				$messageUid = ($messageUid===true ? $status['uidnext'] : $messageUid);
				if (is_array($this->mail_bo->getMessageHeader($messageUid, '',false, false, $folder)))
				{
					$draft_id = mail_ui::generateRowID($this->mail_bo->profileID, $folder, $messageUid);
					if ($content['lastDrafted'] != $draft_id && isset($content['lastDrafted']))
					{
						$dhA = Mail::splitRowID($content['lastDrafted']);
						$duid = $dhA['msgUID'];
						$dmailbox = $dhA['folder'];
						// beware: do not delete the original mail as found in processedmail_id
						$pMuid='';
						if (!empty($content['processedmail_id']))
						{
							$pMhA = Mail::splitRowID($content['processedmail_id']);
							$pMuid = $pMhA['msgUID'];
						}
						//error_log(__METHOD__.__LINE__."#$pMuid#$pMuid!=$duid#".array2string($content['attachments']));
						// do not delete the original message if attachments are present
						if (empty($pMuid) || $pMuid!=$duid || empty($content['attachments']))
						{
							try
							{
								$this->mail_bo->deleteMessages($duid,$dmailbox,'remove_immediately');
							}
							catch (Api\Exception $e)
							{
								$msg = str_replace('"',"'",$e->getMessage());
								$success = false;
								error_log(__METHOD__.__LINE__.$msg);
							}
						} else {
							error_log(__METHOD__.__LINE__.': original message ('.$pMuid.') has attachments and lastDrafted ID ('.$duid.') equals the former');
						}
					} else {
						error_log(__METHOD__.__LINE__." No current draftID (".$draft_id."), or no lastDrafted Info (".$content['lastDrafted'].") or the former being equal:".array2string($content)."(, action=$action)");
					}
				} else {
					error_log(__METHOD__.__LINE__.' No headerdata found for messageUID='.$messageUid.' in Folder:'.$folder.':'.array2string($content)."(, action=$action)");
				}
			}
			else
			{
				throw new Api\Exception\WrongUserinput(lang("Error: Could not save Message as Draft"));
			}
		}
		catch (Api\Exception\WrongUserinput $e)
		{
			$msg = str_replace('"',"'",$e->getMessage());
			error_log(__METHOD__.__LINE__.$msg);
			$success = false;
		}

		if ($success) $msg = lang('Message saved successfully.');

		// Include new information to json respose, because we need them in client-side callback
		$response->data(array(
			'draftedId' => $draft_id,
			'message' => $msg,
			'success' => $success,
			'draftfolder' => $this->mail_bo->profileID.mail_ui::$delimiter.$this->mail_bo->getDraftFolder()
		));
	}

	/**
	 * Save message as draft to specific folder
	 *
	 * @param array $_formData content
	 * @param string &$savingDestination ='' destination folder
	 * @param string $action ='button[saveAsDraft]' 'autosaving', 'button[saveAsDraft]' or 'button[saveAsDraftAndPrint]'
	 * @return boolean return messageUID| false due to an error
	 */
	function saveAsDraft($_formData, &$savingDestination='', $action='button[saveAsDraft]')
	{
		//error_log(__METHOD__."(..., $savingDestination, action=$action)");
		$mail_bo	= $this->mail_bo;
		$mail		= new Api\Mailer($this->mail_bo->profileID);

		// preserve the bcc and if possible the save to folder information
		$this->sessionData['folder']    = $_formData['folder'];
		$this->sessionData['bcc']   = $_formData['bcc'];
		$this->sessionData['mailidentity'] = $_formData['mailidentity'];
		//$this->sessionData['stationeryID'] = $_formData['stationeryID'];
		$this->sessionData['mailaccount']  = $_formData['mailaccount'];
		$this->sessionData['attachments']  = $_formData['attachments'];
		try
		{
			$acc = Mail\Account::read($this->sessionData['mailaccount']);
			//error_log(__METHOD__.__LINE__.array2string($acc));
			$identity = Mail\Account::read_identity($acc['ident_id'],true);
		}
		catch (Exception $e)
		{
			$identity=array();
		}

		$flags = '\\Seen \\Draft';

		$this->createMessage($mail, $_formData, $identity, $action === 'autosaving');

		// folder list as Customheader
		if (!empty($this->sessionData['folder']))
		{
			$folders = implode('|',array_unique($this->sessionData['folder']));
			$mail->addHeader('X-Mailfolder', $folders);
		}
		$mail->addHeader('X-Mailidentity', $this->sessionData['mailidentity']);
		//$mail->addHeader('X-Stationery', $this->sessionData['stationeryID']);
		$mail->addHeader('X-Mailaccount', (int)$this->sessionData['mailaccount']);
		// decide where to save the message (default to draft folder, if we find nothing else)
		// if the current folder is in draft or template folder save it there
		// if it is called from printview then save it with the draft folder
		if (empty($savingDestination)) $savingDestination = $mail_bo->getDraftFolder();
		if (empty($this->sessionData['messageFolder']) && !empty($this->sessionData['mailbox']))
		{
			$this->sessionData['messageFolder'] = $this->sessionData['mailbox'];
		}
		if (!empty($this->sessionData['messageFolder']) && ($mail_bo->isDraftFolder($this->sessionData['messageFolder'])
			|| $mail_bo->isTemplateFolder($this->sessionData['messageFolder'])))
		{
			$savingDestination = $this->sessionData['messageFolder'];
			//error_log(__METHOD__.__LINE__.' SavingDestination:'.$savingDestination);
		}
		if (  !empty($_formData['printit']) && $_formData['printit'] == 0 ) $savingDestination = $mail_bo->getDraftFolder();

		// normaly Bcc is only added to recipients, but not as header visible to all recipients
		$mail->forceBccHeader();

		$mail_bo->openConnection();
		if ($mail_bo->folderExists($savingDestination,true)) {
			try
			{
				$messageUid = $mail_bo->appendMessage($savingDestination, $mail->getRaw(), null, $flags);
			}
			catch (Api\Exception\WrongUserinput $e)
			{
				error_log(__METHOD__.__LINE__.lang("Save of message %1 failed. Could not save message to folder %2 due to: %3",__METHOD__,$savingDestination,$e->getMessage()));
				return false;
			}

		} else {
			error_log(__METHOD__.__LINE__."->".lang("folder")." ". $savingDestination." ".lang("does not exist on IMAP Server."));
			return false;
		}
		$mail_bo->closeConnection();
		return $messageUid;
	}

	/**
	 * setDefaults, sets some defaults
	 *
	 * @param array $content
	 * @return array - the input, enriched with some not set attributes
	 */
	function setDefaults($content=array())
	{
		// if there's not already an identity selected for current account
		if (empty($content['mailidentity']))
		{
			// check if there a preference / previous selection of identity for current account
			if (!empty($GLOBALS['egw_info']['user']['preferences']['mail']['LastSignatureIDUsed']))
			{
				$sigPref = $GLOBALS['egw_info']['user']['preferences']['mail']['LastSignatureIDUsed'];
				if (!empty($sigPref[$this->mail_bo->profileID]) && $sigPref[$this->mail_bo->profileID]>0)
				{
					$content['mailidentity'] = $sigPref[$this->mail_bo->profileID];
				}
			}
			// if we have no preference search for first identity with non-empty signature
			if (empty($content['mailidentity']))
			{
				$default_identity = null;
				foreach(Mail\Account::identities($this->mail_bo->profileID, true, 'params') as $identity)
				{
					if (!isset($default_identity)) $default_identity = $identity['ident_id'];
					if (!empty($identity['ident_signature']))
					{
						$content['mailidentity'] = $identity['ident_id'];
						break;
					}
				}
			}
			if (empty($content['mailidentity'])) $content['mailidentity'] = $default_identity;
		}
		if (!isset($content['mimeType']) || empty($content['mimeType']))
		{
			$content['mimeType'] = 'html';
			if (!empty($this->mailPreferences['composeOptions']) && $this->mailPreferences['composeOptions']=="text") $content['mimeType']  = 'plain';
		}
		return $content;

	}

	/**
	 * Callback function to search mail folders
	 *
	 * New et2-select(-*) widget sends query string and option array as first to parameters
	 *
	 * @param int $_searchStringLength
	 * @param boolean $_returnList
	 * @param int $_mailaccountToSearch
	 * @param boolean $_noPrefixId = false, if set to true folders name does not get prefixed by account id
	 * @return type
	 */
	function ajax_searchFolder($_searchStringLength=2, $_returnList=false, $_mailaccountToSearch=null, $_noPrefixId=false)
	{
		//error_log(__METHOD__.__LINE__.':'.array2string($_REQUEST));
		static $useCacheIfPossible = null;
		if (is_null($useCacheIfPossible)) $useCacheIfPossible = true;
		// new et2-select(-*) widget sends query string and option array as first to parameters
		if (!is_int($_searchStringLength)) $_searchStringLength = 2;
		if (!is_bool($_returnList)) $_returnList = false;
		$_searchString = trim($_REQUEST['query']);
        if ($_REQUEST['noPrefixId'] == "true") $_noPrefixId = true;
		$results = array();
		$rememberServerID = $this->mail_bo->icServer->ImapServerId;
		if (is_null($_mailaccountToSearch) && !empty($_REQUEST['mailaccount'])) $_mailaccountToSearch = $_REQUEST['mailaccount'];
		if (empty($_mailaccountToSearch)) $_mailaccountToSearch = $this->mail_bo->icServer->ImapServerId;
		if ($this->mail_bo->icServer && $_mailaccountToSearch && $this->mail_bo->icServer->ImapServerId != $_mailaccountToSearch)
		{
			$this->changeProfile($_mailaccountToSearch);
		}
		if (strlen($_searchString)>=$_searchStringLength && isset($this->mail_bo->icServer))
		{
			//error_log(__METHOD__.__LINE__.':'.$this->mail_bo->icServer->ImapServerId);
			$this->mail_bo->openConnection($this->mail_bo->icServer->ImapServerId);
			//error_log(__METHOD__.__LINE__.array2string($_searchString).'<->'.$searchString);
			$folderObjects = $this->mail_bo->getFolderObjects(true,false,true,$useCacheIfPossible);
			if (count($folderObjects)<=1) {
				$useCacheIfPossible = false;
			}
			else
			{
				$useCacheIfPossible = true;
			}
			$searchString = Api\Translation::convert($_searchString, Mail::$displayCharset,'UTF7-IMAP');
			foreach ($folderObjects as $k =>$fA)
			{
				//error_log(__METHOD__.__LINE__.$_searchString.'/'.$searchString.' in '.$k.'->'.$fA->displayName);
				$f=false;
				$key = $_noPrefixId?$k:$_mailaccountToSearch.'::'.$k;
				if ($_searchStringLength<=0)
				{
					$f=true;
					$results[] = array('id'=>$key, 'label' => htmlspecialchars($fA->displayName));
				}
				if ($f==false && stripos($fA->displayName,$_searchString)!==false)
				{
					$f=true;
					$results[] = array('id'=>$key, 'label' => htmlspecialchars($fA->displayName));
				}
				if ($f==false && stripos($k,$searchString)!==false)
				{
					$results[] = array('id'=>$key, 'label' => htmlspecialchars($fA->displayName));
				}
			}
		}
		if ($this->mail_bo->icServer && $rememberServerID != $this->mail_bo->icServer->ImapServerId)
		{
			$this->changeProfile($rememberServerID);
		}
		//error_log(__METHOD__.__LINE__.' IcServer:'.$this->mail_bo->icServer->ImapServerId.':'.array2string($results));
		if ($_returnList)
		{
			foreach ((array)$results as $k => $_result)
			{
				$rL[$_result['id']] = $_result['label'];
			}
			return $rL;
		}
		// switch regular JSON response handling off
		Api\Json\Request::isJSONRequest(false);

		header('Content-Type: application/json; charset=utf-8');
		//error_log(__METHOD__.__LINE__);
		echo json_encode($results);
		exit();
	}

	public static function ajax_searchAddress($_searchStringLength=2)
	{
		//error_log(__METHOD__. "request from seachAddress " . $_REQUEST['query']);
		if (!is_int($_searchStringLength)) $_searchStringLength = 2;
		$_searchString = trim($_REQUEST['query']);
		$include_lists = (bool)$_REQUEST['include_lists'];

		$contacts_obj = new Api\Contacts();
		$results = array();
		$mailPrefs = $GLOBALS['egw_info']['user']['preferences']['mail'];
		$contactLabelPref = !is_array($mailPrefs['contactLabel']) && !empty($mailPrefs['contactLabel']) ?
			explode(',', $mailPrefs['contactLabel']) : $mailPrefs['contactLabel'];

		// Add some matching mailing lists, and some groups, limited by config
		if($include_lists)
		{
			$results += static::get_lists($_searchString, $contacts_obj);
		}

		if ($GLOBALS['egw_info']['user']['apps']['addressbook'] && strlen($_searchString)>=$_searchStringLength)
		{
			//error_log(__METHOD__.__LINE__.array2string($_searchString));
			$showAccounts = $GLOBALS['egw_info']['user']['preferences']['addressbook']['hide_accounts'] !== '1';
			$search = explode(' ', $_searchString);
			foreach ($search as $k => $v)
			{
				if (mb_strlen($v) < 3) unset($search[$k]);
			}
			$search_str = implode(' +', $search);	// tell contacts/so_sql to AND search patterns
			//error_log(__METHOD__.__LINE__.$_searchString);
			$filter = $showAccounts ? array() : array('account_id' => null);
			$filter['cols_to_search'] = array('n_prefix','n_given','n_family','org_name','email','email_home', 'contact_id', 'search_cfs' => false);
			$cols = array('n_fn','n_prefix','n_given','n_family','org_name','email','email_home', 'contact_id', 'modified', 'files');
			$contacts = $contacts_obj->search($search_str, $cols, 'n_fn', '', '%', false, 'OR', array(0,100), $filter);
			$cfs_type_email = Api\Storage\Customfields::get_email_cfs('addressbook');
			// additionally search the accounts, if the contact storage is not the account storage
			if ($showAccounts && $contacts_obj->so_accounts)
			{
				$filter['owner'] = 0;
				$accounts = $contacts_obj->search($search_str, $cols, 'n_fn', '', '%', false,'OR', array(0,100), $filter);

				if ($contacts && $accounts)
				{
					$contacts = array_merge($contacts,$accounts);
					usort($contacts,function($a, $b)
					{
						return strcasecmp($a['n_fn'], $b['n_fn']);
					});
				}
				elseif($accounts)
				{
					$contacts =& $accounts;
				}
				unset($accounts);
			}
		}

		if (is_array($contacts))
		{
			$cf_emails = [];
			// if we have email type custom-fields, query them all in one query
			if (!empty($cfs_type_email))
			{
				$cf_emails = $contacts_obj->read_customfields(array_map(static function(array $contact)
				{
					return $contact['id'];
				}, $contacts), $cfs_type_email);
			}
			foreach($contacts as $contact)
			{
				foreach(array_merge([$contact['email'], $contact['email_home']], $cf_emails[$contact['id']] ?? []) as $email)
				{
					// avoid wrong addresses, if a rfc822 encoded address is in addressbook
					$rfcAddr = Mail::parseAddressList($email);
					$_rfcAddr=$rfcAddr->first();
					if (!$_rfcAddr->valid)
					{
						continue; // skip address if we encounter an error here
					}
					$email = $_rfcAddr->mailbox.'@'.$_rfcAddr->host;

					if (method_exists($contacts_obj,'search'))
					{
						$contact['n_fn']='';
						if (!empty($contact['n_prefix']) && (empty($contactLabelPref) || in_array('n_prefix', $contactLabelPref))) $contact['n_fn'] = $contact['n_prefix'];
						if (!empty($contact['n_given']) && (empty($contactLabelPref) || in_array('n_given', $contactLabelPref))) $contact['n_fn'] .= ($contact['n_fn']?' ':'').$contact['n_given'];
						if (!empty($contact['n_family']) && (empty($contactLabelPref) || in_array('n_family', $contactLabelPref))) $contact['n_fn'] .= ($contact['n_fn']?' ':'').$contact['n_family'];
						if (!empty($contact['org_name']) && (empty($contactLabelPref) || in_array('org_name', $contactLabelPref))) $contact['n_fn'] .= ($contact['n_fn']?' ':'').'('.$contact['org_name'].')';
						$contact['n_fn'] = str_replace(array(',','@'),' ',$contact['n_fn']);
					}
					else
					{
						$contact['n_fn'] = str_replace(array(',','@'),' ',$contact['n_fn']);
					}
					$args = explode('@', trim($email));
					$args[] = trim($contact['n_fn'] ? $contact['n_fn'] : $contact['fn']);
					$completeMailString = call_user_func_array('imap_rfc822_write_address', $args);
					if(!empty($email) && in_array($completeMailString ,$results) === false) {
						$result = array(
							'value' => $completeMailString,
							'label' => $completeMailString,
							// Add just name for nice display, with title for hover
							'name'  => $contact['n_fn'],
							'title' => $email,
							'lname' => $contact['n_family'],
							'fname' => $contact['n_given']
						);
						// if we have a real photo, add avatar.php URL
						if (Api\Contacts::hasPhoto($contact))
						{
							$result['icon'] = Framework::link('/api/avatar.php', [
								'contact_id' => $contact['id'],
								'modified'   => $contact['modified'],
							]);
						}
						$results[] = $result;
					}
				}
			}
		}

		// Add groups
		$group_options = array('account_type' => 'groups');
		$groups = $GLOBALS['egw']->accounts->link_query($_searchString, $group_options);
		foreach($groups as $g_id => $name)
		{
			$group = $GLOBALS['egw']->accounts->read($g_id);
			if(!$group['account_email']) continue;
			$args = explode('@', trim($group['account_email']));
			$args[] = $name;
			$completeMailString = call_user_func_array('imap_rfc822_write_address', $args);
			$results[] = array(
				'value' => $completeMailString,
				'label' => $completeMailString,
				'name'	=> $name,
				'title' => $group['account_email']
			);
		}

		 // switch regular JSON response handling off
		Api\Json\Request::isJSONRequest(false);

		$results = array_reduce($results, function ($result, $option)
		{
			$value = $option['value'];
			if(!array_key_exists($value, $result))
			{
				$result[$value] = $option;
			}
			return $result;
		},                      []);

		//error_log(__METHOD__.__LINE__.array2string($jsArray));
		header('Content-Type: application/json; charset=utf-8');
		echo json_encode(array_values($results));
		exit();
	}

	/**
	 * Get list of matching distribution lists when searching for email addresses
	 *
	 * The results are limited by config setting.  Default 10 each of group lists and normal lists
	 *
	 * @param String $_searchString
	 * @param Contacts $contacts_obj
	 * @return array
	 */
	protected static function get_lists($_searchString, &$contacts_obj)
	{
		$group_lists = array();
		$manual_lists = array();
		$lists = array_filter(
			$contacts_obj->get_lists(Acl::READ),
			function($element) use($_searchString) {
				return (stripos($element, $_searchString) !== false);
			}
		);

		foreach($lists as $key => $list_name)
		{
			$type = $key > 0 ? 'manual' : 'group';
			$list = array(
				'value'	=> '"'.str_replace('"', '', $list_name).'" <'.$key.'@lists.egroupware.org>',
				'label'	=> $list_name,
				'title' => lang('Mailinglist'),
				'icon' => Api\Image::find('api', 'email'),
			);
			${"${type}_lists"}[] = $list;
		}
		$config = Api\Config::read('mail');
		$limit = $config['address_list_limit'] ?: 10;
		$trim = function($list) use ($limit) {
			if(count($list) <= $limit) return $list;
			$list[$limit-1]['class'].= ' more_results';
			$list[$limit-1]['title'] .= '  (' . lang('%1 more', count($list) - $limit) . ')';
			return array_slice($list, 0, $limit);
		};
		return array_merge($trim($group_lists), $trim($manual_lists));
	}

}