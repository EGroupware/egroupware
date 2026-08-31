<?php
/**
 * EGroupware Mail: message-display ajax/menuaction handlers for mail_ui
 *
 * @link https://www.egroupware.org
 * @package mail
 * @license https://opensource.org/license/gpl-2-0 GPL 2.0+ - GNU General Public License 2.0 or any higher version of your choice
 */

namespace EGroupware\Mail\Ui;

use EGroupware\Api;
use EGroupware\Api\Egw;
use EGroupware\Api\Framework;
use EGroupware\Api\Mail;
use EGroupware\Api\Mail\BodyDecoding;
use EGroupware\Api\Vfs;
use EGroupware\Api\Mail\Jmap\Imap as JmapImap;
use InvalidArgumentException;
use addressbook_vcal;
use calendar_ical;
use mail_ui;

/**
 * Last (and largest) batch of the classic-fallback half of "Attachment/body-fetch ajax handlers"
 * (see doc/ai/projects/mail-bo-decoupling.md) - image/attachment/zip download plus the message-body
 * render pipeline, including the JMAP-native S/MIME/TNEF fast path and its classic IMAP fallback.
 * Constructor-injected with the owning `mail_ui`, same shape as ImportHandler/MessageActionHandler/
 * AttachmentHandler.
 *
 * `mail_ui` keeps thin wrappers for `displayImage()`, `getAttachment()`, `download_zip()` and
 * `loadEmailBody()` - all four are menuaction-dispatched by that exact name (`$public_functions`).
 * `get_load_email_data()` and `tryJmapNativeSpecialCase()` have no such requirement (no menuaction
 * entry, not in `$public_functions`) but `get_load_email_data()` does have one real in-repo caller
 * outside `mail_ui` itself - `mail/profile.php` (a standalone profiling script) calls it directly on
 * a `mail_ui` instance - so that call site was repointed to construct this class directly rather
 * than keeping a wrapper "just in case" (per this doc's extraction discipline: in-repo call sites
 * get updated, not wrapped). The other internal `mail_ui` call site (inside what's now
 * `ajax_spamAction()`'s per-item loop) was repointed to `$this->messageDisplayHandler()->
 * get_load_email_data(...)`.
 *
 * `get_email_header()`/`showBody()` moved here too - grepping the whole repo found no callers left
 * outside this group once it's extracted, so keeping them on `mail_ui` would just be dead weight.
 *
 * `getdisplayableBody()`'s `$this->mailbox`/`$this->uid`/`$this->partID` reads (now
 * `AttachmentHandler`, called here as `$this->ui->attachmentHandler()->getdisplayableBody(...)`)
 * are populated right here in `get_load_email_data()` before that call - `attachmentHandler()` was
 * widened from `private` to package-default on `mail_ui` for this cross-class call, same reasoning
 * as `get_actions()` earlier. See `AttachmentHandler`'s docblock for the correction to an earlier,
 * incorrect "these are always null" claim made before this method had been read.
 */
class MessageDisplayHandler
{
	private mail_ui $ui;

	public function __construct(mail_ui $ui)
	{
		$this->ui = $ui;
	}

	/**
	 * display image
	 *
	 * all params are passed as GET Parameters
	 *
	 * "profileID" is optional, for backwards compatibility with existing (server-rendered body)
	 * callers that rely on it defaulting to whatever profile this session's mail_bo already has
	 * active - but is required for correctness when called from the client-side JMAP body-fetch
	 * path (mail/js/jmap.ts's MailJmap.fetchBody()), which has no such session-affinity guarantee
	 * (same pattern as ProfileHandler::enablePush(), which takes an explicit icServerID for the
	 * same reason).
	 */
	public function displayImage() : void
	{
		$uid	= base64_decode($_GET['uid']);
		$cid	= base64_decode($_GET['cid']);
		$partID = urldecode($_GET['partID']);
		if (!empty($_GET['mailbox'])) $mailbox  = base64_decode($_GET['mailbox']);
		if (!empty($_GET['profileID']) && $_GET['profileID'] != $this->ui->mail_bo->profileID)
		{
			$this->ui->changeProfile($_GET['profileID']);
		}

		$this->ui->mail_bo->reopen($mailbox);

		$attachment = $this->ui->mail_bo->getAttachmentByCID($uid, $cid, $partID, true);	// true get contents as stream

		$this->ui->mail_bo->closeConnection();

		$GLOBALS['egw']->session->commit_session();

		if ($attachment)
		{
			header("Content-Type: ". $attachment->getType());
			header('Content-Disposition: inline; filename="'. $attachment->getDispositionParameter('filename') .'"');
			Api\Session::cache_control(true);
			echo $attachment->getContents();
		}
		else
		{
			// send a 404 Not found
			header("HTTP/1.1 404 Not found");
		}
		exit();
	}

	public function getAttachment() : void
	{
		if(!empty($_GET['id']))
		{
			$hA = Mail::splitRowID($_GET['id']);
			$uid = $hA['msgUID'] ?? null;
			$mailbox = $hA['folder'] ?? null;
			$icServerID = $hA['profileID'] ?? null;
		}
		else
		{
			$uid = $mailbox = $icServerID = null;
		}
		$rememberServerID = $this->ui->mail_bo->profileID;
		if ($icServerID && $icServerID != $this->ui->mail_bo->profileID)
		{
			$this->ui->changeProfile($icServerID);
		}
		$part		= $_GET['part'] ?? null;
		$is_winmail = $_GET['is_winmail'] ?? 0;

		if (!($this->ui->mail_bo->icServer instanceof Mail\Imap\Jmap))
		{
			$this->ui->mail_bo->reopen($mailbox);
		}
		$attachment = $this->ui->mail_bo->getAttachment($uid,$part,$is_winmail,false);
		$this->ui->mail_bo->closeConnection();
		if ($rememberServerID != $this->ui->mail_bo->profileID)
		{
			$this->ui->changeProfile($rememberServerID);
		}

		$GLOBALS['egw']->session->commit_session();
		if ($_GET['mode'] != "save")
		{
			if (strtoupper($attachment['type']) == 'TEXT/DIRECTORY' || empty($attachment['type']))
			{
				$sfxMimeType = $attachment['type'];
				$buff = explode('.',$attachment['filename']);
				$suffix = '';
				if (is_array($buff)) $suffix = array_pop($buff); // take the last extension to check with ext2mime
				if (!empty($suffix)) $sfxMimeType = Api\MimeMagic::ext2mime($suffix);
				$attachment['type'] = $sfxMimeType;
				if (strtoupper($sfxMimeType) == 'TEXT/VCARD' || strtoupper($sfxMimeType) == 'TEXT/X-VCARD') $attachment['type'] = strtoupper($sfxMimeType);
			}
			if (strtoupper($attachment['type']) == 'TEXT/CALENDAR' || strtoupper($attachment['type']) == 'TEXT/X-VCALENDAR')
			{
				$calendar_ical = new calendar_ical();
				$event = $calendar_ical->importVCal($attachment['attachment'],-1,null,true,0,'',null,$attachment['charset']);
				if ((int)$event > 0)
				{
					$vars = array(
						'menuaction'      => 'calendar.calendar_uiforms.edit',
						'cal_id'      => $event,
					);
					Egw::redirect_link('../index.php',$vars);
				}
				//Import failed, download content anyway
			}
			if (strtoupper($attachment['type']) == 'TEXT/X-VCARD' || strtoupper($attachment['type']) == 'TEXT/VCARD')
			{
				$addressbook_vcal = new addressbook_vcal();
				// double \r\r\n seems to end a vcard prematurely, so we set them to \r\n
				$attachment['attachment'] = str_replace("\r\r\n", "\r\n", $attachment['attachment']);
				$vcard = $addressbook_vcal->vcardtoegw($attachment['attachment'], $attachment['charset']);
				if ($vcard['uid'])
				{
					$vcard['uid'] = trim($vcard['uid']);
					$contact = $addressbook_vcal->find_contact($vcard,false);
				}
				if (!$contact) $contact = null;
				// if there are not enough fields in the vcard (or the parser was unable to correctly parse the vcard (as of VERSION:3.0 created by MSO))
				if ($contact || count($vcard)>2)
				{
					$contact = $addressbook_vcal->addVCard($attachment['attachment'],(is_array($contact)?array_shift($contact):$contact),true,$attachment['charset']);
				}
				if ((int)$contact > 0)
				{
					$vars = array(
						'menuaction'	=> 'addressbook.addressbook_ui.edit',
						'contact_id'	=> $contact,
					);
					Egw::redirect_link('../index.php',$vars);
				}
				//Import failed, download content anyway
			}
		}
		$filename = ($attachment['name']?$attachment['name']:($attachment['filename']?$attachment['filename']:$mailbox.'_uid'.$uid.'_part'.$part));
		$size = 0;
		Api\Header\Content::safe($attachment['attachment'], $filename, $attachment['type'], $size, True, $_GET['mode'] == "save");
		echo $attachment['attachment'];

		exit();
	}

	/**
	 * Zip all attachments and send to user
	 * @param string $message_id = null
	 */
	public function download_zip($message_id=null)
	{
		// First, get all attachment IDs
		if(isset($_GET['id'])) $message_id	= $_GET['id'];
		$rememberServerID = $this->ui->mail_bo->profileID;
		$emailID = $folderID = null;
		if(!is_numeric($message_id))
		{
			$hA = Mail::splitRowID($message_id);
			$emailID = $hA['emailID'] ?? null;
			$folderID = $hA['folderID'] ?? null;
			$message_id = $hA['msgUID'];
			$mailbox = $hA['folder'];
			$icServerID = $hA['profileID'];
			if ($icServerID && $icServerID != $this->ui->mail_bo->profileID)
			{
				$this->ui->changeProfile($icServerID);
			}
		}
		else
		{
			$mailbox = $this->ui->mail_bo->sessionData['mailbox'];
		}
		$icServerID = $icServerID ?? $this->ui->mail_bo->profileID;
		// generateRowID() always produces a classic-shaped (base64-folder/numeric-uid) row-id,
		// which would make resolveAttachmentsJmap() below always take its non-Stalwart branch
		// (Mail::splitRowID() can't tell it apart from a real classic row) - reconstruct the
		// original opaque-emailID shape instead when we have one, so the JMAP-native listing
		// (and thus the per-file blobId fetch further down) actually gets used for Stalwart rows
		$rowID = $emailID ? mail_ui::generateJmapRowID($icServerID, $folderID, $emailID) :
			mail_ui::generateRowID($icServerID, $mailbox, $message_id);
		// always fetch all, even inline (images)
		$fetchEmbeddedImages = true;
		$jmapAttachments = AttachmentJmap::resolveAttachmentsJmap($rowID, null, $fetchEmbeddedImages);
		// TNEF/winmail messages need the classic per-file unpacking below - resolveAttachmentsJmap()
		// only lists the opaque winmail.dat blob itself (matching resolveWinmailJmap()'s own
		// per-file-content gap, see Tier 1 notes), not its unpacked internal attachments, so discard
		// and fall back to the classic listing (which does unpack) for those
		if ($jmapAttachments && strtoupper($jmapAttachments[0]['type'] ?? '') === 'APPLICATION/MS-TNEF')
		{
			$jmapAttachments = null;
		}
		// note: JMAP-resolved entries (already shaped by createAttachmentBlock()) don't carry a
		// per-attachment 'charset' - the filename-transliteration below falls back to the system
		// charset for those, a minor accepted degradation (no functional loss, just a fallback)
		$attachments = $jmapAttachments ??
			$this->ui->mail_bo->getMessageAttachments($message_id,null, null, $fetchEmbeddedImages, true,true,$mailbox);
		// put them in VFS so they can be zipped
		$subject = AttachmentJmap::resolveSubjectJmap($icServerID, $emailID) ??
			($this->ui->mail_bo->getMessageHeader($message_id,'',true,false,$mailbox)['SUBJECT'] ?? null);
		//get_home_dir may fetch the users startfolder if set; if not writeable, action will fail. TODO: use temp_dir
		$homedir = '/home/'.$GLOBALS['egw_info']['user']['account_lid'];
		$temp_path = $homedir/*Vfs::get_home_dir()*/ . "/.mail_$message_id";
		if(Vfs::is_dir($temp_path)) Vfs::remove ($temp_path);

		// Add subject to path, so it gets used as the file name, replacing ':'
		// as it seems to cause an error
		$path = $temp_path . '/' . ($subject ? Vfs::encodePathComponent(Mail::clean_subject_for_filename(str_replace(':','-', $subject))) : lang('mail')) .'/';
		if(!Vfs::mkdir($path, 0700, true))
		{
			echo "Unable to open temp directory $path";
			return;
		}

		$file_list = array();
		$dupe_count = array();
		$this->ui->mail_bo->reopen($mailbox);
		if ($attachments[0]['is_winmail'] && $attachments[0]['is_winmail']!='null')
		{
			$tnefAttachments = $this->ui->mail_bo->getTnefAttachments($message_id, $attachments[0]['partID'],true, $mailbox);
		}
		foreach($attachments as $file)
		{
			// JMAP-native byte fetch when this part carries a blobId (Tier 1/2 listing) - avoids
			// the classic Api\Mail::getAttachment() real-IMAP FETCH, see fetchBlobBytes()
			$jmapBytes = $file['is_winmail'] || empty($file['blobId']) ? null :
				AttachmentJmap::fetchBlobBytes($icServerID, $file['blobId']);
			if ($jmapBytes !== null)
			{
				$attachment = ['attachment' => $jmapBytes];
			}
			elseif ($file['is_winmail'])
			{
				// Try to find the right content for file id
				foreach ($tnefAttachments as $key => $val)
				{
					error_log(__METHOD__.' winmail = '.$key);
					if ($key == $file['is_winmail']) $attachment = $val;
				}
			}
			else
			{
				$attachment = $this->ui->mail_bo->getAttachment($message_id,$file['partID'],$file['is_winmail'],false,true);
			}
			$success=true;
			if (empty($file['filename'])) $file['filename'] = $file['name'];
			if(in_array($path.$file['filename'], $file_list))
			{
				$dupe_count[$path.$file['filename']]++;
				$file['filename'] = pathinfo($file['filename'], PATHINFO_FILENAME) .
					' ('.($dupe_count[$path.$file['filename']] + 1).')' . '.' .
					pathinfo($file['filename'], PATHINFO_EXTENSION);
			}
			// Strip special characters to make sure the files are visible for all OS (windows has issues)
			$target_name = Mail::clean_subject_for_filename(iconv($file['charset'] ? $file['charset'] : $GLOBALS['egw_info']['server']['system_charset'], 'ASCII//IGNORE', $file['filename']));

			$fp = Vfs::fopen($path.$target_name,'wb');
			if (!$fp || (is_string($attachment['attachment']) ?
				!fwrite($fp,$attachment['attachment']) :
				!(!fseek($attachment['attachment'], 0, SEEK_SET) && stream_copy_to_stream($attachment['attachment'], $fp))))
			{
				$success=false;
				Framework::message("Unable to zip {$target_name}",'error');
			}
			if ($success) $file_list[] = $path.$target_name;
			if ($fp) fclose($fp);
		}
		$this->ui->mail_bo->closeConnection();
		if ($rememberServerID != $this->ui->mail_bo->profileID)
		{
			$this->ui->changeProfile($rememberServerID);
		}

		// Zip it up
		Vfs::download_zip($file_list);

		// Clean up
		Vfs::remove($temp_path);

		exit();
	}

	/**
	 * S/MIME passphrase-request form, shown by get_load_email_data() (both the classic and the new
	 * JMAP-native path, see tryJmapNativeSpecialCase()) when Mail\Smime\PassphraseMissing is thrown
	 *
	 * @param Mail\Smime\PassphraseMissing $e
	 * @return string
	 */
	private function smimePassphraseFormHtml(Mail\Smime\PassphraseMissing $e) : string
	{
		$acc_smime = Mail\Smime::get_acc_smime($this->ui->mail_bo->profileID);
		if (empty($acc_smime))
		{
			mail_ui::callWizard($e->getMessage().' '.lang('Please configure your S/MIME certificate in Encryption tab located at Edit Account dialog.'), true, 'error');
		}
		Framework::message($e->getMessage());
		$configs = Api\Config::read('mail');
		// do NOT include any default CSS
		return $this->get_email_header().
			'<div class="smime-message">'.lang("This message is smime encrypted and password protected.").'</div>'.
			'<form id="smimePasswordRequest" method="post">'.
					'<div class="bg-style"></div>'.
					'<div>'.
						'<input type="password" placeholder="'.lang("Please enter password").'" name="smime_passphrase"/>'.
						'<input type="submit" value="'.lang("submit").'"/>'.
						'<div style="margin-top:10px;position:relative;text-align:center;margin-left:-15px;">'.
							lang("Remember the password for ").
								'<input name="smime_pass_exp" type="number" max="480" min="1" placeholder="'.
								(is_array($configs) && $configs['smime_pass_exp'] ? $configs['smime_pass_exp'] : "10").
								'" value="'.$this->ui->mail_bo->mailPreferences['smime_pass_exp'].'"/> '.lang("minutes.").
						'</div>'.
					'</div>'.
			'</form>';
	}

	/**
	 * Try the new JMAP-native S/MIME/TNEF resolvers (see plan: "Mail: move PGP client-side, make
	 * S/MIME + TNEF server-side handling JMAP-native") for get_load_email_data()'s fallback
	 * body-render path, fetching bodyStructure/raw bytes via JMAP (Stalwart: Imap\Jmap's
	 * jmapClient(); local IMAP: JmapShim) instead of Mail::getStructure()/getMessageRawBody()'s
	 * IMAP FETCH chain. This is the primary path for both - the classic Mail::getStructure()
	 * fallback below only runs if this returns null (JMAP unreachable, or a case it doesn't cover).
	 *
	 * For S/MIME, the decrypt/verify itself is 100% server-side and never leaves the server -
	 * JmapImap::resolveSmime()/Imap\Jmap::resolveSmimeJmap() return the rendered body plus the
	 * decrypt/verify metadata (Api\Mail\Smime::resolveMessage()'s 'X-EGroupware-Smime' convention),
	 * and this method pushes just the display flags (verified/not-verified/unknown-signer - same
	 * shape the classic path pushes) to the client via Api\Json\Push, same as
	 * get_load_email_data()'s classic branch below does for its own (unrelated, only-reached-on-
	 * fallback) S/MIME handling.
	 *
	 * @param string $uid real IMAP UID (already resolved, see Api\Mail::splitRowID())
	 * @param string|null $partID
	 * @param string $mailbox
	 * @param string $htmlOptions
	 * @param string|null $smimePassphrase
	 * @param string|null $emailID JMAP opaque Email id - only available/meaningful for
	 *  Stalwart-backed rows (Api\Mail::splitRowID()'s 'emailID', see loadEmailBody())
	 * @return string|null final page HTML, or null to fall through to the classic path
	 */
	private function tryJmapNativeSpecialCase($uid, $partID, $mailbox, $htmlOptions, $smimePassphrase, $emailID)
	{
		unset($partID);	// not used by the S/MIME/TNEF resolvers (they always resolve the whole message)
		$icServer = $this->ui->mail_bo->icServer;
		$isStalwart = $icServer instanceof Mail\Imap\Jmap;

		try
		{
			if ($isStalwart)
			{
				if (!$emailID)
				{
					return null;
				}
				$email = $icServer->jmapClient()->emailGet($emailID, ['bodyStructure', 'from']);
				$bodyStructure = $email['bodyStructure'] ?? null;
				$from = $email['from'][0]['email'] ?? null;
			}
			else
			{
				$structure = JmapImap::structureGet($icServer, $mailbox, $uid);
				if (!$structure)
				{
					return null;
				}
				$bodyStructure = JmapImap::bodyPartToJmap($structure, $mailbox, $uid);
				$from = null;	// not needed: JmapImap::resolveSmime() only uses it for the
								// signer/sender cross-check, a nice-to-have, not a hard requirement
			}
			if (!$bodyStructure || !($type = JmapImap::specialCaseType($bodyStructure)))
			{
				return null;
			}

			if ($smimePassphrase)
			{
				if ($this->ui->mail_bo->mailPreferences['smime_pass_exp'] != $_POST['smime_pass_exp'])
				{
					$GLOBALS['egw']->preferences->add('mail', 'smime_pass_exp', $_POST['smime_pass_exp']);
					$GLOBALS['egw']->preferences->save_repository();
				}
				Api\Cache::setSession('mail', 'smime_passphrase', $smimePassphrase, (int)($_POST['smime_pass_exp']?:10) * 60);
			}

			if ($type === 'smime')
			{
				$result = $isStalwart ?
					$icServer->resolveSmimeJmap($emailID, $bodyStructure['type'], (string)$from, $htmlOptions, (string)$smimePassphrase) :
					JmapImap::resolveSmime((string)$this->ui->mail_bo->profileID, base64_encode($mailbox), $uid,
						$bodyStructure['type'], (string)$from, $htmlOptions, (string)$smimePassphrase);
				$body = $result['body'];
				if (($smime = $result['smime']))
				{
					$smime['msg'] = lang($smime['msg']);
					$push = new Api\Json\Push($GLOBALS['egw_info']['user']['account_id']);
					if (!empty($smime['addtocontact']) && !empty(Mail\Smime::get_acc_smime($this->ui->mail_bo->profileID)))
					{
						$push->call('app.mail.smimeCertAddToContact', $smime);
					}
					$push->call('app.mail.setSmimeFlags', $smime);
				}
			}
			else	// 'tnef'
			{
				$body = $isStalwart ?
					$icServer->resolveTnefJmap($emailID, $bodyStructure['partId'], $htmlOptions) :
					JmapImap::resolveTnef((string)$this->ui->mail_bo->profileID, base64_encode($mailbox), $uid, $bodyStructure['partId'], $htmlOptions);
			}

			Api\Session::cache_control(true);
			foreach (['frame-src', 'connect-src', 'manifest-src'] as $src)
			{
				Api\Header\ContentSecurityPolicy::add($src, 'none');
			}
			Api\Header\ContentSecurityPolicy::add('script-src', 'self', true);	// true = remove default 'unsafe-eval'
			Api\Header\ContentSecurityPolicy::add('img-src', 'http:');
			Api\Header\ContentSecurityPolicy::add('media-src', ['https:', 'http:']);

			return $this->get_email_header().$this->showBody($body, false);
		}
		catch (Mail\Smime\PassphraseMissing $e)
		{
			return $this->smimePassphraseFormHtml($e);
		}
		catch (\Throwable $e)
		{
			// any other failure (JMAP unreachable, part not found, TNEF decode failure, ...):
			// fall through to the classic IMAP-based path rather than showing an error
			_egw_log_exception($e);
			return null;
		}
	}

	/**
	 * Lean JSON-shaped counterpart to tryJmapNativeSpecialCase() above, for MailJmap.fetchBody()'s
	 * client-first fast path (mail_ui::ajax_resolveSpecialCaseBody()) - doc/ai/projects/
	 * mail-compose-jmap-migration.md's "read-side extraction" follow-up (2026-08-31). Same
	 * underlying resolveSmime()/resolveTnef() primitives as tryJmapNativeSpecialCase(), just
	 * self-contained (rowId in, JSON-shaped result out - no reliance on $this->ui->mail_bo already
	 * being switched to the right profile, same reasoning as AttachmentJmap::resolveWinmailJmap())
	 * and with no classic-page HTML wrapping/CSP headers/Push calls. Deliberately does NOT accept a
	 * fresh passphrase from the client - only ever uses an already session-cached one (see
	 * Smime::resolveMessage()'s own fallback). If decryption still needs one, this returns null and
	 * the caller falls back to the classic path, which has the actual passphrase-prompt UI - a
	 * dedicated fast-path passphrase dialog is a follow-up, not built here.
	 *
	 * @param string $rowId
	 * @param string $htmlOptions
	 * @return ?array {type: 'smime'|'tnef', body: string, smime: ?array} or null if not a
	 *  resolvable special case (JMAP unreachable, a passphrase is needed, TNEF decode failed, ...)
	 */
	public function resolveSpecialCaseBody(string $rowId, string $htmlOptions='') : ?array
	{
		$idParts = Mail::splitRowID($rowId);
		$uid = $idParts['msgUID'];
		$mailbox = $idParts['folder'];
		$profileID = $idParts['profileID'];
		if (!$uid || !$mailbox || !$profileID)
		{
			return null;
		}
		try
		{
			$icServer = Mail\Account::read((int)$profileID)->imapServer();
			$isStalwart = $icServer instanceof Mail\Imap\Jmap;

			if ($isStalwart)
			{
				if (empty($idParts['emailID']))
				{
					return null;
				}
				$email = $icServer->jmapClient()->emailGet($idParts['emailID'], ['bodyStructure', 'from']);
				$bodyStructure = $email['bodyStructure'] ?? null;
				$from = $email['from'][0]['email'] ?? null;
			}
			else
			{
				$structure = JmapImap::structureGet($icServer, $mailbox, $uid);
				if (!$structure)
				{
					return null;
				}
				$bodyStructure = JmapImap::bodyPartToJmap($structure, $mailbox, $uid);
				$from = null;	// nice-to-have signer/sender cross-check only, see tryJmapNativeSpecialCase()
			}
			if (!$bodyStructure || !($type = JmapImap::specialCaseType($bodyStructure)))
			{
				return null;
			}

			if ($type === 'smime')
			{
				$result = $isStalwart ?
					$icServer->resolveSmimeJmap($idParts['emailID'], $bodyStructure['type'], (string)$from, $htmlOptions) :
					JmapImap::resolveSmime((string)$profileID, base64_encode($mailbox), $uid, $bodyStructure['type'], (string)$from, $htmlOptions);
				return ['type' => 'smime', 'body' => $result['body'], 'smime' => $result['smime']];
			}
			// 'tnef'
			$body = $isStalwart ?
				$icServer->resolveTnefJmap($idParts['emailID'], $bodyStructure['partId'], $htmlOptions) :
				JmapImap::resolveTnef((string)$profileID, base64_encode($mailbox), $uid, $bodyStructure['partId'], $htmlOptions);
			return ['type' => 'tnef', 'body' => $body, 'smime' => null];
		}
		catch (\Throwable $e)
		{
			// PassphraseMissing (no cached passphrase yet - client falls back to the classic path's
			// actual prompt UI) or any other failure (JMAP unreachable, decode failure, ...)
			if (!($e instanceof Mail\Smime\PassphraseMissing))
			{
				_egw_log_exception($e);
			}
			return null;
		}
	}

	public function get_load_email_data($uid, $partID, $mailbox, $htmlOptions=null, $smimePassphrase='', $emailID=null)
	{
		// seems to be needed, as if we open a mail from notification popup that is
		// located in a different folder, we experience: could not parse message
		$this->ui->mail_bo->reopen($mailbox);
		$this->ui->mailbox = $mailbox;
		$this->ui->uid = $uid;
		$this->ui->partID = $partID;
		$bufferHtmlOptions = $this->ui->mail_bo->htmlOptions;
		if (empty($htmlOptions)) $htmlOptions = $this->ui->mail_bo->htmlOptions;

		// JMAP-native S/MIME/TNEF (see plan) - returns null for anything else (meeting invites,
		// no usable JMAP access, ...) to fall through to the classic IMAP-based path unchanged
		if (($jmapHtml = $this->tryJmapNativeSpecialCase($uid, $partID, $mailbox, $htmlOptions, $smimePassphrase, $emailID)) !== null)
		{
			$this->ui->mail_bo->htmlOptions = $bufferHtmlOptions;
			return $jmapHtml;
		}

		// fetching structure now, to supply it to getMessageBody and getMessageAttachment, so it does not get fetched twice
		try
		{
			if ($smimePassphrase)
			{
				if ($this->ui->mail_bo->mailPreferences['smime_pass_exp'] != $_POST['smime_pass_exp'])
				{
					$GLOBALS['egw']->preferences->add('mail', 'smime_pass_exp', $_POST['smime_pass_exp']);
					$GLOBALS['egw']->preferences->save_repository();
				}
				Api\Cache::setSession('mail', 'smime_passphrase', $smimePassphrase, (int)($_POST['smime_pass_exp']?:10) * 60);
			}
			$structure = $this->ui->mail_bo->getStructure($uid, $partID, $mailbox, false);
			if (($smime = $structure->getMetadata('X-EGroupware-Smime')))
			{
				$smime['msg'] = lang($smime['msg']);
				$acc_smime = Mail\Smime::get_acc_smime($this->ui->mail_bo->profileID);
				$attachments = $this->ui->mail_bo->getMessageAttachments($uid, $partID, $structure,false,true,true, $mailbox);
				$push = new Api\Json\Push($GLOBALS['egw_info']['user']['account_id']);
				if (!empty($acc_smime) && !empty($smime['addtocontact'])) $push->call('app.mail.smimeCertAddToContact', $smime);
				if (is_array($attachments))
				{
					$push->call('app.mail.setSmimeAttachments', AttachmentJmap::createAttachmentBlock($attachments, $_GET['_messageID'], $uid, $mailbox));
				}
				$push->call('app.mail.setSmimeFlags', $smime);
			}
		}
		catch(Mail\Smime\PassphraseMissing $e)
		{
			return $this->smimePassphraseFormHtml($e);
		}
		$calendar_part = null;
		$bodyParts	= $this->ui->mail_bo->getMessageBody($uid, ($htmlOptions?$htmlOptions:''), $partID, $structure, false, $mailbox, $calendar_part);

		// for meeting requests (multipart alternative with text/calendar part) let calendar render it
		if ($calendar_part && isset($GLOBALS['egw_info']['user']['apps']['calendar']))
		{
			$charset = $calendar_part->getContentTypeParameter('charset');
			// Do not try to fetch raw part content if it's smime signed message
			if (empty($smime)) $this->ui->mail_bo->fetchPartContents($uid, $calendar_part);
			$headers = $this->ui->mail_bo->getHeaders($mailbox, 0, 1, '', false, null, $uid);
			Api\Cache::setSession('calendar', 'ical', array(
				'charset' => $charset ?: 'utf-8',
				'attachment' => $calendar_part->getContents(),
				'method' => $calendar_part->getContentTypeParameter('method'),
				'sender' => empty($headers['header'][0]['sender_address']) ? null :
					(preg_match('/<([^>]+?)>$/', $sender = strtolower($headers['header'][0]['sender_address']), $matches) ?
						$matches[1] : $sender),
			));
			$this->ui->mail_bo->htmlOptions = $bufferHtmlOptions;
			Api\Translation::add_app('calendar');
			return ExecMethod('calendar.calendar_uiforms.meeting',
				array('event'=>null,'msg'=>'','useSession'=>true)
			);
		}
		if (!$smime)
		{
			Api\Session::cache_control(true);

			// more strict CSP for displaying mail
			foreach(['frame-src', 'connect-src', 'manifest-src'] as $src)
			{
				Api\Header\ContentSecurityPolicy::add($src, 'none');
			}
			Api\Header\ContentSecurityPolicy::add('script-src', 'self', true);	// true = remove default 'unsafe-eval'
			Api\Header\ContentSecurityPolicy::add('img-src', 'http:');
			Api\Header\ContentSecurityPolicy::add('media-src', ['https:','http:']);
		}
		// Compose the content of the frame
		$frameHtml =
			$this->get_email_header(BodyDecoding::getStyles($bodyParts)).
			$this->showBody($this->ui->attachmentHandler()->getdisplayableBody($bodyParts,true,false), false);
		//IE10 eats away linebreaks preceeded by a whitespace in PRE sections
		$frameHtml = str_replace(" \r\n","\r\n",$frameHtml);
		$this->ui->mail_bo->htmlOptions = $bufferHtmlOptions;

		return $frameHtml;
	}

	public static function get_email_header($additionalStyle='')
	{
		// egw_info[flags][css] already include <style> tags
		$GLOBALS['egw_info']['flags']['css'] = preg_replace('|</?style[^>]*>|i', '', $additionalStyle);
		$GLOBALS['egw_info']['flags']['nofooter']=true;
		$GLOBALS['egw_info']['flags']['nonavbar']=true;
		// do NOT include any default CSS
		Framework::includeCSS('mail', 'preview', true, true);

		// load preview.js to activate mailto links
		Framework::includeJS('/mail/js/preview.js');

		// send CSP and content-type header
		return $GLOBALS['egw']->framework->header();
	}

	public function showBody(&$body, $print=true, $fullPageTags=true)
	{
		$BeginBody = '<div class="mailDisplayBody">
<table width="100%" style="table-layout:fixed"><tr><td class="td_display">';

		$EndBody = '</td></tr></table></div>';
		if ($fullPageTags) $EndBody .= "</body></html>";
		if ($print)	{
			print $BeginBody. $body .$EndBody;
		} else {
			return $BeginBody. $body .$EndBody;
		}
	}

	/**
	 * loadEmailBody
	 *
	 * @param string _messageID UID
	 *
	 * @return xajax response
	 */
	public function loadEmailBody($_messageID=null, $_partID=null, $_htmloptions=null)
	{
		if (!$_messageID && !empty($_GET['_messageID'])) $_messageID = $_GET['_messageID'];
		// stop execution right here, if we have no (valid) messageID
		if (!$_messageID || !str_starts_with($_messageID, 'mail::'))
		{
			throw new InvalidArgumentException('missing, empty or invalid required _messageID GET parameter!');
		}
		if (!$_partID && !empty($_GET['_partID'])) $_partID = $_GET['_partID'];
		if (!$_htmloptions && !empty($_GET['_htmloptions'])) $_htmloptions = $_GET['_htmloptions'];
		if(Mail::$debug) error_log(__METHOD__."->".print_r($_messageID,true).",$_partID,$_htmloptions");
		if (empty($_messageID)) return "";
		$uidA = Mail::splitRowID($_messageID);
		$folder = $uidA['folder']; // all messages in one set are supposed to be within the same folder
		$messageID = $uidA['msgUID'];
		$icServerID = $uidA['profileID'];
		//something went wrong. there is a $_messageID but no $messageID: means $_messageID is crippeled
		if (empty($messageID)) return "";
		if ($icServerID && $icServerID != $this->ui->mail_bo->profileID)
		{
			$this->ui->changeProfile($icServerID);
		}

		$bodyResponse = $this->get_load_email_data($messageID,$_partID,$folder,$_htmloptions, $_POST['smime_passphrase'] ?? null, $uidA['emailID'] ?? null);
		echo $bodyResponse;
	}
}
