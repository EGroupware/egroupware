<?php
/**
 * EGroupware Mail: JMAP-native attachment listing/fetch helpers for mail_ui
 *
 * @link https://www.egroupware.org
 * @package mail
 * @license https://opensource.org/license/gpl-2-0 GPL 2.0+ - GNU General Public License 2.0 or any higher version of your choice
 */

namespace EGroupware\Mail\Ui;

use EGroupware\Api;
use EGroupware\Api\Mail;
use EGroupware\Mail\JmapShim;

/**
 * The "JMAP fast path" slice of mail_ui's "Attachment/body-fetch ajax handlers" group - unlike the
 * classic-fallback methods that stayed in mail_ui (resolveAttachmentsBlock(), getAttachment(),
 * download_zip(), ...), every method here was already written to take an explicit account id and
 * talk directly to Mail\Account::read()->imapServer()/JmapShim, deliberately bypassing
 * mail_ui's connected mail_bo entirely (same "backend-agnostic, no $icServer coupling" shape as
 * Api\Mail's own jmap<MethodName>() dispatch helpers - see doc/ai/projects/mail-jmap-modernization.md).
 * That's what makes this a genuinely zero-mail_ui-instance-dependency extraction, unlike
 * ImportHandler/MessageActionHandler - see doc/ai/projects/mail-bo-decoupling.md.
 *
 * The classic methods that stay in mail_ui call into these as their JMAP-native fast path, falling
 * back to their own classic IMAP fetch when a method here returns null (not applicable, or the
 * account isn't JMAP-native) - the same `$jmapResult ?? $classicResult` pattern used throughout.
 */
class AttachmentJmap
{
	/**
	 * Build the presentable attachment-block array (or full HTML table) for a message's attachments
	 *
	 * @param array $attachments
	 * @param string $rowID rowid of the message
	 * @param ?int $uid uid of the message
	 * @param ?string $mailbox mailbox identifier
	 * @param boolean $_returnFullHTML flag wether to return HTML or data array
	 * @return array|string data array or html or empty string
	 */
	public static function createAttachmentBlock($attachments, $rowID, $uid, $mailbox, $_returnFullHTML=false)
	{
		$attachmentHTMLBlock = '';
		$attachmentHTML = [];
		// profileID is cheap to resolve (pure string-parsing, no IMAP) even for a lazy-resolving
		// row-id - done once here, not per-attachment (see below), and without touching the
		// msgUID/folder keys (which for a Stalwart opaque-id row DO cost a real IMAP search) since
		// $uid/$mailbox are already given as parameters
		$acc_id = Mail::splitRowID($rowID)['profileID'];

		// skip message/delivery-status and set a title for original eml file
		if (($attachments[0]['mimeType'] === 'message/delivery-status'))
		{
			unset($attachments[0]);
			if (is_array($attachments))
			{
				$attachments = array_values($attachments);
				$attachments[0]['name'] = lang('Original Email Content');
			}
		}

		if (is_array($attachments) && count($attachments) > 0)
		{
			foreach ($attachments as $key => $value)
			{
				if (Mail\Smime::isSmime($value['mimeType']))
				{
					continue;
				}
				$attachmentHTML[$key]['filename'] = ($value['name'] ? ($value['filename'] ?: $value['name']) : lang('(no subject)'));
				$attachmentHTML[$key]['filename'] = Api\Translation::convert_jsonsafe($attachmentHTML[$key]['filename'], 'utf-8');
				if (strtoupper($value['mimeType']) == 'APPLICATION/OCTET-STREAM')
				{
					$value['mimeType'] = Api\MimeMagic::filename2mime($attachmentHTML[$key]['filename']);
				}
				$attachmentHTML[$key]['type'] = $value['mimeType'];
				$attachmentHTML[$key]['mimetype'] = Api\MimeMagic::mime2label($value['mimeType']);
				$onlyOwnHandlers = preg_match(\mail_ui::$mimeTypesHandledOnlyByMail, $value['mimeType']) ? 'mail' : null;
				// JMAP-native attachment content fetch (blobId set by jmapAttachmentsToLegacy()/
				// resolveWinmailJmap() for a JMAP-listed row - Stalwart real JMAP *or* the local
				// shim for a plain-IMAP account, see fetchBlobBytes()'s own docblock for the two
				// blobId shapes) instead of the classic Api\Mail::getAttachmentAccount() IMAP fetch -
				// same target for both mime_data and invoice_data below, and for link_save/
				// downloadOneAsFile client-side (blobId is passed through to the browser via
				// $attachmentHTML[$key]['blobId']). Must go through fetchBlobBytes() (backend-uniform
				// dispatch), not Imap\Jmap::downloadBlobAccount() directly - that one assumes every
				// blobId is a real, opaque Stalwart id and fatals via __call() for a local-shim
				// self-describing one (a plain-IMAP account's JMAP-listed row).
				if (!empty($value['blobId']))
				{
					$attachmentTarget = ['EGroupware\\Mail\\Ui\\AttachmentJmap::fetchBlobBytes',
						[$acc_id, $value['blobId'], $attachmentHTML[$key]['filename'], $value['mimeType']]];
				}
				else
				{
					// $uid/$mailbox are null when the caller resolved a blobId for every part and
					// intentionally skipped their (possibly IMAP-expensive) resolution - only
					// derived here, from $rowID, if this classic fallback is actually reached
					$fallbackUid = $uid ?? Mail::splitRowID($rowID)['msgUID'];
					$fallbackMailbox = $mailbox ?? Mail::splitRowID($rowID)['folder'];
					$attachmentTarget = ['EGroupware\\Api\\Mail::getAttachmentAccount', [$acc_id, $fallbackMailbox, $fallbackUid, $value['partID'], $value['is_winmail'] ?? false, true]];
				}
				$attachmentHTML[$key]['mime_data'] = Api\Link::set_data($value['mimeType'], $attachmentTarget[0], $attachmentTarget[1],
					false, $onlyOwnHandlers);
				$attachmentHTML[$key]['size'] = Api\Vfs::hsize($value['size']);
				$attachmentHTML[$key]['attachment_number'] = $key;
				$attachmentHTML[$key]['partID'] = $value['partID'];
				$attachmentHTML[$key]['blobId'] = $value['blobId'] ?? null;
				$attachmentHTML[$key]['mail_id'] = $rowID;
				$attachmentHTML[$key]['winmailFlag'] = $value['is_winmail'];
				$attachmentHTML[$key]['smime_type'] = $value['smime_type'];

				if ($GLOBALS['egw_info']['apps']['collabora']
					&& $GLOBALS['egw_info']['user']['preferences']['filemanager']['document_doubleclick_action'] === 'collabora'
					&& array_key_exists($value['mimeType'], \filemanager_hooks::getEditorPrefMimes() ?: []))
				{
					$attachmentHTML[$key]['actions'] = 'collabora';
					$attachmentHTML[$key]['actionsDefaultLabel'] = 'Open with Collabora';
				}
				else
				{
					$attachmentHTML[$key]['actions'] = 'downloadOneAsFile';
					$attachmentHTML[$key]['actionsDefaultLabel'] = 'Download';
				}

				// reset mode array as it should be considered differently for each attachment
				$mode = [];
				switch (strtoupper($value['mimeType']))
				{
					case 'MESSAGE/RFC822':
						$linkData = [
							'menuaction' => 'mail.mail_ui.displayMessage',
							'mode' => 'display', //message/rfc822 attachments should be opened in display mode
							'id' => $rowID,
							'part' => $value['partID'],
							'is_winmail' => $value['is_winmail'],
						];
						$windowName = 'displayMessage_'.$rowID.'_'.$value['partID'];
						$linkView = "egw_openWindowCentered('".Api\Egw::link('/index.php', $linkData)."','$windowName',700,egw_getWindowOuterHeight());";
						break;
					case 'IMAGE/JPEG':
					case 'IMAGE/PNG':
					case 'IMAGE/GIF':
					case 'IMAGE/BMP':
						// set mode for media mimetypes because we need
						// to structure a download url to be used maybe in expose.
						$mode = [
							'mode' => 'save',
						];
					case 'APPLICATION/PDF':
					case 'TEXT/PLAIN':
					case 'TEXT/HTML':
					case 'TEXT/DIRECTORY':
						$sfxMimeType = $value['mimeType'];
						$buff = explode('.', $value['name']);
						$suffix = '';
						if (is_array($buff))
						{
							$suffix = array_pop($buff); // take the last extension to check with ext2mime
						}
						if (!empty($suffix))
						{
							$sfxMimeType = Api\MimeMagic::ext2mime($suffix);
						}
						if (strtoupper($sfxMimeType) == 'TEXT/VCARD' || strtoupper($sfxMimeType) == 'TEXT/X-VCARD')
						{
							$attachments[$key]['mimeType'] = $sfxMimeType;
							$value['mimeType'] = strtoupper($sfxMimeType);
						}
					case 'TEXT/X-VCARD':
					case 'TEXT/VCARD':
					case 'TEXT/CALENDAR':
					case 'TEXT/X-VCALENDAR':
						$linkData = array_merge([
							'menuaction' => 'mail.mail_ui.getAttachment',
							'id' => $rowID,
							'part' => $value['partID'],
							'is_winmail' => $value['is_winmail'],
							// not read by getAttachment() (which re-derives folder from 'id' via
							// Mail::splitRowID() instead) - kept for URL-shape compatibility, but
							// degrades to '' rather than forcing $mailbox's (possibly IMAP-expensive
							// for a Stalwart opaque-id row) resolution when the caller didn't need it
							'mailbox' => base64_encode($mailbox ?? ''),
							'smime_type' => $value['smime_type'],
						], $mode);
						$windowName = 'displayAttachment_'.($uid ?? $rowID);
						$reg = '800x600';
						// handle calendar/vcard
						if (strtoupper($value['mimeType']) == 'TEXT/CALENDAR')
						{
							$windowName = 'displayEvent_'.$rowID;
							$reg2 = Api\Link::get_registry('calendar', 'view_popup');
							$attachmentHTML[$key]['popup'] = (!empty($reg2) ? $reg2 : $reg);
						}
						if (strtoupper($value['mimeType']) == 'TEXT/X-VCARD' || strtoupper($value['mimeType']) == 'TEXT/VCARD')
						{
							$windowName = 'displayContact_'.$rowID;
							$reg2 = Api\Link::get_registry('addressbook', 'add_popup');
							$attachmentHTML[$key]['popup'] = (!empty($reg2) ? $reg2 : $reg);
						}
						// apply to action
						[$width, $height] = explode('x', (!empty($reg2) ? $reg2 : $reg));
						$linkView = "egw_openWindowCentered('".Api\Egw::link('/index.php', $linkData)."','$windowName',$width,$height);";
						break;
					default:
						$linkData = [
							'menuaction' => 'mail.mail_ui.getAttachment',
							'id' => $rowID,
							'part' => $value['partID'],
							'is_winmail' => $value['is_winmail'],
							// see the TEXT/VCARD case above for why this degrades to '' instead of
							// forcing $mailbox's resolution
							'mailbox' => base64_encode($mailbox ?? ''),
							'smime_type' => $value['smime_type'],
						];
						$linkView = "window.location.href = '".Api\Egw::link('/index.php', $linkData)."';";
						break;
				}
				// we either use mime_data for server-side supported mime-types or mime_url for client-side or download
				if (empty($attachmentHTML[$key]['mime_data']) || preg_match('#^(application|text)/xml$#i', $attachmentHTML[$key]['type']))
				{
					$attachmentHTML[$key]['mime_url'] = Api\Egw::link('/index.php', $linkData);

					// always check invoices (or it's EPL viewer) too and then add mime_data unconditionally
					if (Api\Link::get_mime_info($attachmentHTML[$key]['type'],
						!empty($GLOBALS['egw_info']['user']['apps']['invoices']) ? 'invoices' : 'stylite'))
					{
						$attachmentHTML[$key]['invoice_data'] = Api\Link::set_data($value['mimeType'], $attachmentTarget[0], $attachmentTarget[1], true);
					}
					unset($attachmentHTML[$key]['mime_data']);
				}
				$attachmentHTML[$key]['windowName'] = $windowName;

				$attachmentHTML[$key]['link_view'] = '<a href="#" ." title="'.$attachmentHTML[$key]['filename'].'" onclick="'.$linkView.' return false;"><b>'.
					($value['name'] ?: lang('(no subject)')).
					'</b></a>';

				$linkData = [
					'menuaction' => 'mail.mail_ui.getAttachment',
					'mode' => 'save',
					'id' => $rowID,
					'part' => $value['partID'],
					'is_winmail' => $value['is_winmail'],
					'mailbox' => base64_encode($mailbox),
					'smime_type' => $value['smime_type'],
				];
				$attachmentHTML[$key]['link_save'] = "<a href='".Api\Egw::link('/index.php', $linkData)."' title='".$attachmentHTML[$key]['filename']."'><et2-image src='fileexport'></et2-image></a>";

				if (!$GLOBALS['egw_info']['user']['apps']['filemanager'])
				{
					$attachmentHTML[$key]['no_vfs'] = true;
				}
			}
			$attachmentHTMLBlock = "<table width='100%'>";
			foreach ((array)$attachmentHTML as $row)
			{
				$attachmentHTMLBlock .= "<tr><td><div class='useEllipsis'>".$row['link_view'].'</div></td>';
				$attachmentHTMLBlock .= "<td>".$row['mimetype'].'</td>';
				$attachmentHTMLBlock .= "<td>".$row['size'].'</td>';
				$attachmentHTMLBlock .= "<td>".$row['link_save'].'</td></tr>';
			}
			$attachmentHTMLBlock .= "</table>";
		}
		if (!$_returnFullHTML)
		{
			foreach ((array)$attachmentHTML as $ikey => $value)
			{
				unset($attachmentHTML[$ikey]['link_view']);
				unset($attachmentHTML[$ikey]['link_save']);
			}
		}
		return ($_returnFullHTML ? $attachmentHTMLBlock : $attachmentHTML);
	}

	/**
	 * JMAP-native winmail.dat unpacking for ajax_resolveWinmail() - fetches the winmail.dat part's
	 * raw bytes via JMAP (Stalwart: Imap\Jmap's jmapClient(); local IMAP: JmapShim) instead of
	 * Mail::getMessageAttachments()'s IMAP-based enumeration + Mail::getAttachment(), decodes via
	 * the existing (now static, transport-agnostic) Mail::tnef_decoder(), and builds the same
	 * attachment-array shape via the new JmapShim::tnefAttachments() (ported, not a call into
	 * Mail::getMessageAttachments()'s equivalent loop) before handing off to the existing, generic
	 * createAttachmentBlock() (download-link/token UI plumbing, not IMAP-specific - kept, see plan).
	 *
	 * Scope note: only the *listing* is JMAP-native here - createAttachmentBlock()'s per-file
	 * Link::set_data() download tokens still point at Api\Mail::getAttachmentAccount(), an IMAP
	 * fetch, when the user actually clicks to download one of these files. Making that JMAP-native
	 * too is a further step, not done here.
	 *
	 * @param string $rowID
	 * @return array|null null if not applicable/failed - caller falls through to the classic
	 *  resolveAttachmentsBlock() path
	 */
	public static function resolveWinmailJmap($rowID) : ?array
	{
		$idParts = Mail::splitRowID($rowID);
		$uid = $idParts['msgUID'];
		$mailbox = $idParts['folder'];
		$acc_id = $idParts['profileID'];
		if (!$uid || !$mailbox || !$acc_id)
		{
			return null;
		}

		try
		{
			$icServer = Mail\Account::read((int)$acc_id)->imapServer();
			$isStalwart = $icServer instanceof Mail\Imap\Jmap;

			if ($isStalwart)
			{
				if (empty($idParts['emailID']))
				{
					return null;
				}
				$email = $icServer->jmapClient()->emailGet($idParts['emailID'], ['attachments']);
				$winmailPart = current(array_filter($email['attachments'] ?? [], static function ($a)
				{
					return strtolower($a['type'] ?? '') === 'application/ms-tnef' || strtolower($a['name'] ?? '') === 'winmail.dat';
				})) ?: null;
				if (!$winmailPart)
				{
					return null;
				}
				$partID = $winmailPart['partId'];
				$raw = $icServer->jmapClient()->downloadBlob($winmailPart['blobId'], 'winmail.dat', 'application/ms-tnef');
			}
			else
			{
				$structure = JmapShim::structureGet($icServer, $mailbox, $uid);
				if (!$structure)
				{
					return null;
				}
				$attachments = JmapShim::emailBodyFields($icServer, $mailbox, $uid, $structure)['attachments'];
				$winmailPart = current(array_filter($attachments, static function ($a)
				{
					return strtolower($a['type'] ?? '') === 'application/ms-tnef' || strtolower($a['name'] ?? '') === 'winmail.dat';
				})) ?: null;
				if (!$winmailPart)
				{
					return null;
				}
				$partID = $winmailPart['partId'];
				$raw = JmapShim::fetchRawPart($icServer, $mailbox, $uid, $partID);
			}
			if ($raw === null)
			{
				return null;
			}

			$decoded = Mail::tnef_decoder($raw);
			if (!$decoded)
			{
				return null;
			}

			$attachments = JmapShim::tnefAttachments($uid, $partID, $decoded);
			return self::createAttachmentBlock($attachments, $rowID, $uid, $mailbox);
		}
		catch (\Throwable $e)
		{
			_egw_log_exception($e);
			return null;	// fall through to the classic path rather than showing an error
		}
	}

	/**
	 * JMAP-native attachment listing for resolveAttachmentsBlock()'s general (non-winmail) case -
	 * backend-uniform, mirroring resolveWinmailJmap()'s shape exactly (splitRowID()/Account::read()/
	 * instanceof-check header, catch-and-return-null fall-through).
	 *
	 * @param string $rowID
	 * @param string|null $partID null: message itself; non-null (nested message/rfc822 attachments)
	 *  not supported here - falls through to the classic path, see caller
	 * @param bool $fetchEmbeddedImages true: also include inline/cid parts, matching
	 *  Mail::getMessageAttachments()'s parameter of the same name
	 * @return array|null null if not applicable/failed - caller falls through to
	 *  resolveAttachmentsBlock()
	 */
	public static function resolveAttachmentsJmap(string $rowID, ?string $partID=null, bool $fetchEmbeddedImages=false) : ?array
	{
		if ($partID !== null)
		{
			return null;	// nested message/rfc822 attachments - not implemented here
		}
		$idParts = Mail::splitRowID($rowID);
		$acc_id = $idParts['profileID'];
		if (!$acc_id)
		{
			return null;
		}

		try
		{
			$icServer = Mail\Account::read((int)$acc_id)->imapServer();
			$isStalwart = $icServer instanceof Mail\Imap\Jmap;

			if ($isStalwart)
			{
				if (empty($idParts['emailID']))
				{
					return null;
				}
				$attachments = $icServer->jmapClient()->emailGet($idParts['emailID'], ['attachments'])['attachments'] ?? [];
				// $uid/$mailbox deliberately left unresolved here - a Stalwart opaque-id row's
				// msgUID/folder cost a real IMAP EMAILID search (Mail\Imap\Jmap::emailId2uid()) to
				// resolve, and createAttachmentBlock() only actually needs them for its
				// classic-fallback branch (blobId missing) or dead/unused URL params - see there
				$uid = $mailbox = null;
			}
			else
			{
				// no such win for the local shim - it's real IMAP either way, so resolve normally
				$uid = $idParts['msgUID'];
				$mailbox = $idParts['folder'];
				if (!$uid || !$mailbox)
				{
					return null;
				}
				$structure = JmapShim::structureGet($icServer, $mailbox, $uid);
				if (!$structure)
				{
					return null;
				}
				$attachments = JmapShim::emailBodyFields($icServer, $mailbox, $uid, $structure)['attachments'];
			}
			$legacy = self::jmapAttachmentsToLegacy($attachments, $fetchEmbeddedImages);
			return self::createAttachmentBlock($legacy, $rowID, $uid, $mailbox);
		}
		catch (\Throwable $e)
		{
			_egw_log_exception($e);
			return null;	// fall through to the classic path rather than showing an error
		}
	}

	/**
	 * Translate RFC 8621 EmailBodyPart shapes (Stalwart's real Email/get "attachments", or the local
	 * shim's JmapShim::emailBodyFields()) into the flat shape createAttachmentBlock() expects
	 */
	private static function jmapAttachmentsToLegacy(array $jmapAttachments, bool $fetchEmbeddedImages) : array
	{
		$legacy = [];
		foreach ($jmapAttachments as $attachment)
		{
			if (!empty($attachment['cid']) && !$fetchEmbeddedImages && $attachment['disposition'] !== 'attachment')
			{
				continue;
			}
			$name = $attachment['name'] ?: '';
			if ($name === '')
			{
				$ext = Api\MimeMagic::mime2ext($attachment['type'] ?? 'application/octet-stream');
				$name = (!empty($attachment['cid']) ? trim($attachment['cid'], '<>') :
					lang('unknown').'_Part'.$attachment['partId']).($ext ? '.'.$ext : '');
			}
			$legacy[] = [
				'partID' => $attachment['partId'],
				'mimeType' => $attachment['type'] ?? 'application/octet-stream',
				'name' => $name,
				'size' => $attachment['size'] ?? 0,
				'cid' => $attachment['cid'] ?? null,
				'disposition' => $attachment['disposition'] ?? null,
				'blobId' => $attachment['blobId'] ?? null,
			];
		}
		return $legacy;
	}

	/**
	 * Byte-returning blob fetch, backend-uniform dispatch by blobId shape - opaque (Stalwart real
	 * JMAP Email.blobId, needs $icServer->jmapClient()->downloadBlob()) vs self-describing
	 * base64(mailbox):uid:partId (local shim, see JmapShim::bodyPartToJmap()/download() - empty
	 * partId means the whole raw message, not one part).
	 *
	 * $filename/$mimeType are only ever substituted into the Stalwart download URL (Mail\Jmap::
	 * downloadBlob()'s own docblock) - harmless, generic defaults for callers (fetchMessageBytesJmap(),
	 * the winmail resolver below) that don't have a real filename/mimetype to hand; createAttachmentBlock()
	 * passes the real ones through for a proper download filename.
	 *
	 * @param string $acc_id
	 * @param string $blobId
	 * @param string $filename
	 * @param string $mimeType
	 * @return ?string null on any failure - caller falls back to its classic fetch
	 */
	public static function fetchBlobBytes(string $acc_id, string $blobId, string $filename='blob', string $mimeType='application/octet-stream') : ?string
	{
		try
		{
			$icServer = JmapShim::imapServer($acc_id);
			if (!$icServer)
			{
				return null;
			}
			if ($icServer instanceof Mail\Imap\Jmap)
			{
				return $icServer->jmapClient()->downloadBlob($blobId, $filename, $mimeType);
			}
			[$mailboxB64, $uid, $partId] = array_pad(explode(':', $blobId, 3), 3, null);
			if ($mailboxB64 === null || !$uid)
			{
				return null;
			}
			$mailbox = JmapShim::urlsafeB64Decode($mailboxB64);
			return $partId !== '' ? JmapShim::fetchRawPart($icServer, $mailbox, $uid, $partId) :
				JmapShim::fetchRawMessage($icServer, $mailbox, $uid);
		}
		catch (\Throwable $e)
		{
			_egw_log_exception($e);
			return null;	// fall through to the classic path rather than showing an error
		}
	}

	/**
	 * Resolve and fetch a message's WHOLE raw body via fetchBlobBytes() - Stalwart needs one
	 * Email/get(['blobId']) call first (opaque, server-assigned blobId); the local shim's blobId is
	 * self-describing and directly constructible (see JmapShim::download()).
	 *
	 * @param string $acc_id
	 * @param string $mailbox
	 * @param string $uid
	 * @param ?string $emailID Stalwart's opaque Email.id (Mail::splitRowID()'s 'emailID'), null for
	 *  local-shim rows
	 * @return ?string raw bytes, or null on any failure - caller falls back to its classic fetch
	 */
	public static function fetchMessageBytesJmap(string $acc_id, string $mailbox, string $uid, ?string $emailID) : ?string
	{
		try
		{
			$icServer = JmapShim::imapServer($acc_id);
			if (!$icServer)
			{
				return null;
			}
			if ($icServer instanceof Mail\Imap\Jmap)
			{
				if (!$emailID)
				{
					return null;
				}
				$blobId = $icServer->jmapClient()->emailGet($emailID, ['blobId'])['blobId'] ?? null;
			}
			else
			{
				$blobId = JmapShim::urlsafeB64Encode($mailbox).':'.$uid.':';
			}
			return $blobId ? self::fetchBlobBytes($acc_id, $blobId) : null;
		}
		catch (\Throwable $e)
		{
			_egw_log_exception($e);
			return null;
		}
	}

	/**
	 * JMAP-native subject fetch for download_zip()'s temp-folder path name - Stalwart only, same
	 * scoping as replaceMessageJmap(): the local shim is a thin IMAP wrapper, no protocol-level win
	 * possible there, so shim rows keep the classic Api\Mail::getMessageHeader() fetch.
	 *
	 * @param string $acc_id
	 * @param ?string $emailID Stalwart's opaque Email.id, null for local-shim rows (always fails
	 *  fast for those, caller falls back to the classic fetch)
	 * @return ?string null on any failure or non-Stalwart row
	 */
	public static function resolveSubjectJmap(string $acc_id, ?string $emailID) : ?string
	{
		if (!$emailID)
		{
			return null;
		}
		try
		{
			$icServer = JmapShim::imapServer($acc_id);
			if (!$icServer instanceof Mail\Imap\Jmap)
			{
				return null;
			}
			return $icServer->jmapClient()->emailGet($emailID, ['subject'])['subject'] ?? null;
		}
		catch (\Throwable $e)
		{
			_egw_log_exception($e);
			return null;
		}
	}

	/**
	 * JMAP-native transport for ajax_saveModifiedMessageSubject()'s "create the modified copy,
	 * delete the original" operation - Stalwart only. Real JMAP has no primitive for editing an
	 * existing message's headers in place (Email objects are immutable once stored), so the actual
	 * fetch-raw + edit-in-Api\Mailer logic stays unchanged (already correct, protocol-agnostic) -
	 * only the append (real IMAP APPEND) and delete-original (real IMAP STORE+EXPUNGE) transport
	 * swap to genuine Email/import + Email/set(destroy) where a real JMAP server exists.
	 *
	 * Deliberately NOT extended to the local IMAP shim: JmapShim is a thin wrapper that would just
	 * turn around and call the exact same Horde_Imap_Client_Socket::append()/store()+expunge()
	 * primitives Api\Mail::appendMessage()/deleteMessages() already call directly - no protocol-level
	 * win is possible there, only added indirection, so shim rows keep using the classic path.
	 *
	 * @param string $acc_id
	 * @param string $folder
	 * @param string $uid original message's uid, to be destroyed on success
	 * @param ?string $emailID Stalwart's opaque Email.id, null for local-shim rows (always fails
	 *  fast for those, caller falls back to the classic path)
	 * @param string $raw new (subject-edited) raw RFC822 bytes
	 * @return bool true on success
	 */
	public static function replaceMessageJmap(string $acc_id, string $folder, string $uid, ?string $emailID, string $raw) : bool
	{
		if (!$emailID)
		{
			return false;
		}
		try
		{
			$icServer = JmapShim::imapServer($acc_id);
			if (!$icServer instanceof Mail\Imap\Jmap)
			{
				return false;
			}
			$client = $icServer->jmapClient();
			$blobId = $client->uploadBlob($raw, 'message/rfc822');
			$client->emailImport($blobId, $folder, ['$seen' => true]);
			$client->emailDestroy([$emailID]);
			return true;
		}
		catch (\Throwable $e)
		{
			_egw_log_exception($e);
			return false;
		}
	}

	/**
	 * JMAP-native attachment-bytes fetch for vfsSaveAttachments()/download_zip() - looks up the
	 * attachment's blobId via resolveAttachmentsJmap() (whole-message block, matched by partID) and
	 * fetches bytes via fetchBlobBytes() when found, else null so the caller falls back to its
	 * classic Api\Mail::getAttachment() real-IMAP FETCH.
	 *
	 * @param string $rowID message row id (no part/winmail/name suffix)
	 * @param string $partID
	 * @param string $acc_id
	 * @param array &$cache keyed by rowID, reused across multiple attachments of the same message
	 * @return array|null ['filename'=>string, 'attachment'=>string] or null
	 */
	public static function fetchAttachmentJmap(string $rowID, string $partID, string $acc_id, array &$cache) : ?array
	{
		$cache[$rowID] ??= (self::resolveAttachmentsJmap($rowID) ?: []);
		foreach ($cache[$rowID] as $jmapAttachment)
		{
			if ((string)($jmapAttachment['partID'] ?? '') === (string)$partID && !empty($jmapAttachment['blobId']))
			{
				$bytes = self::fetchBlobBytes($acc_id, $jmapAttachment['blobId']);
				return $bytes === null ? null : ['filename' => $jmapAttachment['filename'], 'attachment' => $bytes];
			}
		}
		return null;
	}

	/**
	 * Re-parse a raw From/To/Cc/Bcc header via Api\Mail::parseAddressList(), for a real JMAP
	 * server's (eg. Stalwart's) own address-list parsing to fall back to, on-demand, when its
	 * result looks broken - see mail_ui::ajax_parseAddressList()'s docblock for the full story.
	 *
	 * @param string $header raw (still RFC 2047-encoded, un-decoded) header value
	 * @return array
	 */
	public static function parseAddressList(string $header) : array
	{
		// generous but bounded - no legitimate address-list header gets anywhere near this,
		// just a defensive cap against a client sending something absurd
		return JmapShim::addressList(Mail::parseAddressList(substr($header, 0, 8000)));
	}
}
