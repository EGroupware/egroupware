<?php
/**
 * EGroupware Mail: classic (mail_bo-coupled) attachment/body-fetch handlers for mail_ui
 *
 * @link https://www.egroupware.org
 * @package mail
 * @license https://opensource.org/license/gpl-2-0 GPL 2.0+ - GNU General Public License 2.0 or any higher version of your choice
 */

namespace EGroupware\Mail\Ui;

use EGroupware\Api;
use EGroupware\Api\Mail;
use EGroupware\Api\Mail\BodyDecoding;
use EGroupware\Api\Vfs;
use mail_ui;
use tidy;

/**
 * First batch of the classic-fallback half of "Attachment/body-fetch ajax handlers" (see
 * doc/ai/projects/mail-bo-decoupling.md) - the methods that genuinely need `mail_ui`'s connected
 * mail_bo, unlike the zero-dependency JMAP-native cluster already in Mail\Ui\AttachmentJmap.
 * Constructor-injected with the owning `mail_ui`, same shape as ImportHandler/MessageActionHandler.
 *
 * `mail_ui` keeps thin wrappers for `vfsSaveMessages()` (menuaction-dispatched directly, and also
 * called via `ExecMethod2()` from filemanager_ui.inc.php) and the `ajax_*`-prefixed methods
 * (`ajax_vfsOpen`, `ajax_vfsSave`, `ajax_saveModifiedMessageSubject`, `ajax_fetchMessageDetails`).
 * `resolveAttachmentsBlock()`/`vfsSaveAttachments()`/`getdisplayableBody()` had no external callers
 * and were removed from `mail_ui` outright.
 *
 * `getdisplayableBody()`'s inline-image resolution reads `$this->mailbox`/`$this->uid`/
 * `$this->partID` (now `$this->ui->mailbox`/etc.) - **correction**: an earlier version of this
 * docblock claimed these were never assigned and thus always null. That was wrong - a grep limited
 * to this file (before `Mail\Ui\MessageDisplayHandler` was extracted) missed the real assignment,
 * which lives in `MessageDisplayHandler::get_load_email_data()` (`$this->ui->mailbox = $mailbox`
 * etc.), the one caller of this method - see that class for the actual (correctly working) data
 * flow. Lesson: "grep this file" isn't the same as "grep the method's actual callers".
 */
class AttachmentHandler
{
	private mail_ui $ui;

	public function __construct(mail_ui $ui)
	{
		$this->ui = $ui;
	}

	/**
	 * Ajax function to save message(s)/attachment(s) in the vfs
	 *
	 * @param string $attachment_id
	 * @param string $filename
	 */
	public function vfsOpen($attachment_id, $filename) : void
	{
		// Use a sub-dir so we can give a nice filename
		$temp_path = '/home/'.$GLOBALS['egw_info']['user']['account_lid']."/.mail/";
		if (!Vfs::is_dir($temp_path))
		{
			Vfs::mkdir($temp_path);
		}

		$result = $this->vfsSaveAttachments([$attachment_id], $temp_path.$filename, 'rename');

		Api\Json\Response::get()->data($result['savepath'][$attachment_id] ?? "");
	}

	/**
	 * Ajax function to save message(s)/attachment(s) in the vfs
	 *
	 * @param array $params array of mail ids and action name
	 *            params = array (
	 *                ids => array of string
	 *                action => string
	 *            )
	 * @param string $path path to save the emails
	 * @param string $savemode save mode: 'overwrite' or 'rename'
	 */
	public function vfsSave($params, $path, $savemode='rename') : void
	{
		$result = null;
		switch ($params['action'])
		{
			case 'message':
				$result = $this->ui->vfsSaveMessages($params['ids'], $path, $savemode);
				break;
			case 'attachment':
				$result = $this->vfsSaveAttachments($params['ids'], $path, $savemode);
				break;
		}
		Api\Json\Response::get()->call('app.mail.vfsSaveCallback', $result);
	}

	/**
	 * Save Message(s) in the vfs
	 *
	 * @param string|array $ids use splitRowID, to separate values
	 * @param string $path path in vfs (no Vfs::PREFIX!), only directory for multiple id's ($ids is an array)
	 * @param string $savemode save mode: 'overwrite' or 'rename'
	 *
	 * @return array returns an array including message and success result
	 *		array (
	 *			'msg' => STRING,
	 *			'success' => BOOLEAN
	 *		)
	 */
	public function vfsSaveMessages($ids, $path, $savemode='rename') : array
	{
		Api\Translation::add_app('mail');
		$res = [];

		// extract dir from the path
		$dir = Vfs::is_dir($path) ? $path : Vfs::dirname($path);

		// exit if user has no right to the dir
		if (!Vfs::is_writable($dir))
		{
			return [
				'msg' => lang('%1 is NOT writable by you!', $path),
				'success' => false,
			];
		}

		$preservedServerID = $this->ui->mail_bo->profileID;
		foreach ((array)$ids as $id)
		{
			$hA = Mail::splitRowID($id);
			$uid = $hA['msgUID'];
			$mailbox = $hA['folder'];
			$icServerID = $hA['profileID'];
			if ($icServerID && $icServerID != $this->ui->mail_bo->profileID)
			{
				$this->ui->changeProfile($icServerID);
			}
			$message = $this->ui->mail_bo->getMessageRawBody($uid, $partID = '', $mailbox);

			// is multiple messages
			if (Vfs::is_dir($path))
			{
				$headers = $this->ui->mail_bo->getMessageHeader($uid, $partID, true, false, $mailbox);
				$file = $dir.'/'.Mail::clean_subject_for_filename($headers['SUBJECT']).'.eml';
			}
			else
			{
				$file = $dir.'/'.Mail::clean_subject_for_filename(str_replace($dir.'/', '', $path));
			}

			if ($savemode != 'overwrite')
			{
				// Check if file already exists, then try to assign a none existance filename
				$counter = 1;
				$tmp_file = $file;
				while (Vfs::file_exists($tmp_file))
				{
					$tmp_file = $file;
					$pathinfo = pathinfo(Vfs::basename($tmp_file));
					$tmp_file = $dir.'/'.$pathinfo['filename'].'('.$counter.')'.'.'.$pathinfo['extension'];
					$counter++;
				}
				$file = $tmp_file;
			}

			if (!is_string($message) || !($fp = Vfs::fopen($file, 'wb')) || !fwrite($fp, $message))
			{
				$res['msg'] = lang('Error saving %1!', $file);
				$res['success'] = false;
			}
			else
			{
				$res['success'] = true;
			}
			if ($fp)
			{
				fclose($fp);
			}
			if ($res['success'])
			{
				unset($headers['SUBJECT']);//already in filename
				$infoSection = Mail::createHeaderInfoSection($headers, 'SUPPRESS', false);
				$props = [['name' => 'comment', 'val' => $infoSection]];
				Vfs::proppatch($file, $props);
			}
		}
		if ($preservedServerID != $this->ui->mail_bo->profileID)
		{
			//change Profile back to where we came from
			$this->ui->changeProfile($preservedServerID);
		}
		return $res;
	}

	/**
	 * Save attachment(s) in the vfs
	 *
	 * @param string|array $ids '::' delimited mailbox::uid::part-id::is_winmail::name (::name for multiple id's)
	 * @param string $path path in vfs (no Vfs::PREFIX!), only directory for multiple id's ($ids is an array)
	 * @param string $savemode save mode: 'overwrite' or 'rename'
	 *
	 * @return array returns an array including message and success result
	 *		array (
	 *			'msg' => STRING,
	 *			'success' => BOOLEAN
	 *		)
	 */
	public function vfsSaveAttachments($ids, $path, $savemode='rename') : array
	{
		$res = [
			'msg' => lang('Attachment has been saved successfully.'),
			'success' => true,
		];

		if (Vfs::is_dir($path))
		{
			$dir = $path;
		}
		else
		{
			$dir = Vfs::dirname($path);
			// Need to deal with any ? here, or basename will truncate
			$filename = Mail::clean_subject_for_filename(str_replace('?', '_', Vfs::basename($path)));
		}

		if (!Vfs::is_writable($dir))
		{
			return [
				'msg' => lang('%1 is NOT writable by you!', $path),
				'success' => false,
			];
		}

		$preservedServerID = $this->ui->mail_bo->profileID;

		/**
		 * Extract all parameteres from the given id
		 * @param int $id message id ('::' delimited mailbox::uid::part-id::is_winmail::name)
		 *
		 * @return array an array of parameters - 'idParts' is Mail::splitRowID()'s lazy
		 *  RowIdParts result, deliberately NOT pre-extracted into 'uid'/'mailbox' keys here: for a
		 *  Stalwart opaque-id row those cost a real IMAP EMAILID search to resolve, and the JMAP
		 *  fast path below (fetchAttachmentJmap()) never needs them at all - only read
		 *  $idParts['msgUID']/['folder'] where the classic fallback is actually reached
		 */
		$getParams = function ($id)
		{
			[$app, $user, $serverID, $mailbox, $uid, $part, $is_winmail, $name] = explode('::', $id, 8);
			$lId = implode('::', [$app, $user, $serverID, $mailbox, $uid]);
			$hA = Mail::splitRowID($lId);
			return [
				'is_winmail' => $is_winmail == "null" || !$is_winmail ? false : $is_winmail,
				'user' => $user,
				'name' => $name,
				'part' => $part,
				'idParts' => $hA,
				'icServer' => $hA['profileID'],
				'rowID' => $lId,
			];
		};
		$jmapCache = [];
		// only needed for the classic per-attachment fallback - fetchAttachmentJmap() talks to the
		// account's IMAP/JMAP connection directly (Mail\Account::read()->imapServer()), bypassing
		// mail_bo/changeProfile()/reopen() entirely, so this is never called for the JMAP fast path
		$classicFetch = function (array $params)
		{
			if ($params['icServer'] && $params['icServer'] != $this->ui->mail_bo->profileID)
			{
				$this->ui->changeProfile($params['icServer']);
			}
			$this->ui->mail_bo->reopen($params['idParts']['folder']);
			return $this->ui->mail_bo->getAttachment($params['idParts']['msgUID'], $params['part'], $params['is_winmail'], false);
		};

		//Examine the first attachment to see if attachment
		//is winmail.dat embedded attachments.
		$p = $getParams((is_array($ids) ? $ids[0] : $ids));
		if ($p['is_winmail'])
		{
			// winmail/TNEF internal attachments always need the classic path regardless of
			// backend (see resolveWinmailJmap()'s docblock) - eager resolution here is expected
			if ($p['icServer'] && $p['icServer'] != $this->ui->mail_bo->profileID)
			{
				$this->ui->changeProfile($p['icServer']);
			}
			$this->ui->mail_bo->reopen($p['idParts']['folder']);
			// retrieve all embedded attachments at once
			// avoids to fetch heavy winmail.dat content
			// for each file.
			$attachments = $this->ui->mail_bo->getTnefAttachments($p['idParts']['msgUID'], $p['part'], false, $p['idParts']['folder']);
		}

		foreach ((array)$ids as $id)
		{
			$params = $getParams($id);

			// is multiple attachments
			if (Vfs::is_dir($path) || $params['is_winmail'])
			{
				if ($params['is_winmail'])
				{
					// winmail/TNEF internal attachments already resolved above (classic-only,
					// mail_bo already positioned by the pre-check block)
					foreach ($attachments as $key => $val)
					{
						if ($key == $params['is_winmail'])
						{
							$attachment = $val;
						}
					}
				}
				else
				{
					$attachment = AttachmentJmap::fetchAttachmentJmap($params['rowID'], $params['part'], $params['icServer'], $jmapCache)
						?? $classicFetch($params);
				}
			}
			else
			{
				$attachment = AttachmentJmap::fetchAttachmentJmap($params['rowID'], $params['part'], $params['icServer'], $jmapCache)
					?? $classicFetch($params);
			}

			$file = $dir.'/'.($filename ?: Mail::clean_subject_for_filename($attachment['filename']));

			if ($savemode != 'overwrite')
			{
				$counter = 1;
				$tmp_file = $file;
				while (Vfs::file_exists($tmp_file))
				{
					$tmp_file = $file;
					$pathinfo = pathinfo(Vfs::basename($tmp_file));
					$tmp_file = $dir.'/'.$pathinfo['filename'].'('.$counter.')'.'.'.$pathinfo['extension'];
					$counter++;
				}
				$file = $tmp_file;
			}

			if (!($fp = Vfs::fopen($file, 'wb')) ||
				!fwrite($fp, $attachment['attachment']))
			{
				$res['msg'] = lang('Error saving %1!', $file);
				$res['success'] = false;
			}
			if ($fp)
			{
				fclose($fp);
			}
			$res['savepath'][$id] = $file;
		}

		$this->ui->mail_bo->closeConnection();

		if ($preservedServerID != $this->ui->mail_bo->profileID)
		{
			//change Profile back to where we came from
			$this->ui->changeProfile($preservedServerID);
		}
		return $res;
	}

	/**
	 * Resolve a row-id to its attachmentsBlock
	 *
	 * Shared by displayMessage() (the "view" popup), ajax_resolveWinmail() and
	 * ajax_fetchAttachments() (both used by the preview panel) - the three places that
	 * independently used to run splitRowID()+getMessageAttachments()+createAttachmentBlock()
	 * themselves. Switches to the row's own profile if it differs from the currently active
	 * one, and always switches back afterwards, so it's safe to call regardless of which
	 * account is currently active.
	 *
	 * @param string $rowID row id from nm
	 * @param string|null $partID part to get attachments for, if message is eg. a forwarded/attached message
	 * @param bool $fetchEmbeddedImages true: also return embedded images as attachments
	 * @param bool $returnFullHTML false (default): return data array, true: HTML
	 * @return array attachmentsBlock, see AttachmentJmap::createAttachmentBlock()
	 */
	public function resolveAttachmentsBlock(string $rowID, ?string $partID=null, bool $fetchEmbeddedImages=false, bool $returnFullHTML=false)
	{
		$idParts = Mail::splitRowID($rowID);
		$uid = $idParts['msgUID'];
		$mailbox = $idParts['folder'];
		if (!$uid || !$mailbox)
		{
			return [];
		}

		$rememberServerID = $this->ui->mail_bo->profileID;
		$switchedProfile = $idParts['profileID'] && $idParts['profileID'] != $rememberServerID;
		if ($switchedProfile)
		{
			$this->ui->changeProfile($idParts['profileID']);
		}
		try
		{
			$attachments = $this->ui->mail_bo->getMessageAttachments($uid, $partID, null, $fetchEmbeddedImages, true, true, $mailbox);
		}
		catch (Mail\Smime\PassphraseMissing $e)
		{
			$attachments = [];
		}
		finally
		{
			if ($switchedProfile)
			{
				$this->ui->changeProfile($rememberServerID);
			}
		}
		return is_array($attachments) ? AttachmentJmap::createAttachmentBlock($attachments, $rowID, $uid, $mailbox, $returnFullHTML) : [];
	}

	/**
	 * Create a new message from modified message then sends the original one to the trash.
	 *
	 * @param string $_rowID row id
	 * @param string $_subject subject to be replaced with old subject
	 * @return array array('success' => boolean, 'msg' => string)
	 */
	public function saveModifiedMessageSubject($_rowID, $_subject) : array
	{
		$idData = Mail::splitRowID($_rowID);
		$folder = $idData['folder'];
		try
		{
			$raw = AttachmentJmap::fetchMessageBytesJmap($idData['profileID'], $folder, $idData['msgUID'], $idData['emailID'] ?? null)
				?? $this->ui->mail_bo->getMessageRawBody($idData['msgUID'], '', $folder);
			$result = ['success' => true, 'msg' => ''];
			if ($raw && $_subject)
			{
				$mailer = new Api\Mailer();
				$this->ui->mail_bo->parseRawMessageIntoMailObject($mailer, $raw);
				$mailer->removeHeader('subject');
				$mailer->addHeader('subject', $_subject);
				$this->ui->mail_bo->openConnection();
				$delimiter = $this->ui->mail_bo->getHierarchyDelimiter();
				if ($folder == 'INBOX'.$delimiter)
				{
					$folder = 'INBOX';
				}
				if ($this->ui->mail_bo->folderExists($folder, true))
				{
					// JMAP-native transport (Stalwart only, see replaceMessageJmap()'s docblock) -
					// falls back to the classic IMAP APPEND+STORE+EXPUNGE round trip on any failure
					// or for local-shim rows (no protocol-level win possible there). getRaw(false)
					// returns a plain string - the default (true) returns a stream, which
					// Api\Mail\Jmap::uploadBlob() (string-typed) can't accept
					if (!AttachmentJmap::replaceMessageJmap($idData['profileID'], $folder, $idData['msgUID'], $idData['emailID'] ?? null, $mailer->getRaw(false)))
					{
						$this->ui->mail_bo->appendMessage($folder, $mailer->getRaw(), null, '\\Seen');
						$this->ui->mail_bo->deleteMessages($idData['msgUID'], $folder);
					}
				}
				else
				{
					$result['success'] = false;
					$result['msg'] = lang('Changing subject failed folder %1 does not exist', $folder);
				}
			}
		}
		catch (\Exception $e)
		{
			$result['success'] = false;
			$result['msg'] = lang('Changing subject failed because of %1 ', $e->getMessage());
		}
		return $result;
	}

	/**
	 * Fetch a single row's full header/address/attachment detail, shaped exactly like
	 * preview() / MailApp.renderMessageInto() (mail/js/app.ts) expect - the same fields
	 * email2row() (mail/js/jmap.ts) produces for list rows.
	 *
	 * Fallback for the "view" popup (mail_ui::displayMessage()) when window.opener's row cache
	 * isn't available - a bookmarked/direct link, or the opener window was closed. The normal,
	 * zero-extra-round-trip case reuses the opener's already-fetched row instead of calling this.
	 *
	 * @param string $_rowid row id from nm
	 * @return array|null
	 */
	public function fetchMessageDetails($_rowid) : ?array
	{
		$idParts = Mail::splitRowID($_rowid);
		$uid = $idParts['msgUID'];
		$mailbox = $idParts['folder'];
		if (!$uid || !$mailbox)
		{
			return null;
		}
		$rememberServerID = $this->ui->mail_bo->profileID;
		$switchedProfile = $idParts['profileID'] && $idParts['profileID'] != $rememberServerID;
		if ($switchedProfile)
		{
			$this->ui->changeProfile($idParts['profileID']);
		}

		try
		{
			$headers = $this->ui->mail_bo->getMessageHeader($uid, null, true, true, $mailbox);
			$envelope = $this->ui->mail_bo->getMessageEnvelope($uid, null, true, $mailbox);
		}
		catch (Api\Exception $e)
		{
			if ($switchedProfile)
			{
				$this->ui->changeProfile($rememberServerID);
			}
			return null;
		}
		$attachmentsBlock = AttachmentJmap::resolveAttachmentsJmap($_rowid) ?? $this->resolveAttachmentsBlock($_rowid);

		if ($switchedProfile)
		{
			$this->ui->changeProfile($rememberServerID);
		}

		$nonDisplayAbleCharacters = ['[\016]', '[\017]',
			'[\020]', '[\021]', '[\022]', '[\023]', '[\024]', '[\025]', '[\026]', '[\027]',
			'[\030]', '[\031]', '[\032]', '[\033]', '[\034]', '[\035]', '[\036]', '[\037]'];
		$subject = $this->ui->mail_bo->decode_subject(preg_replace($nonDisplayAbleCharacters, '', $envelope['SUBJECT'] ?? ''), false);

		$data = [
			'uid' => $uid,
			'subject' => $subject !== '' ? $subject : lang('no subject'),
			'date' => Mail::_strtotime($headers['DATE'] ?? ($envelope['DATE'] ?? ''), 'ts', true),
			'fromaddress' => $envelope['FROM'][0] ?? '',
			'additionalfromaddress' => array_slice($envelope['FROM'] ?? [], 1),
			'toaddress' => $envelope['TO'][0] ?? '',
			'additionaltoaddress' => array_slice($envelope['TO'] ?? [], 1),
			'ccaddress' => $envelope['CC'] ?? [],
			'bccaddress' => $envelope['BCC'] ?? [],
			'attachmentsBlock' => $attachmentsBlock,
			'attachments' => $attachmentsBlock ? "<et2-image src='attach'></et2-image>" : '&nbsp;',
		];
		// MDN (read-receipt) prompt trigger - same 3-header priority Api\Mail::getHeaders() uses
		$mdnHeader = $headers['DISPOSITION-NOTIFICATION-TO'] ?? $headers['RETURN-RECEIPT-TO'] ??
			$headers['X-CONFIRM-READING-TO'] ?? '';
		$data['dispositionnotificationto'] = is_array($mdnHeader) ? (string)reset($mdnHeader) : (string)$mdnHeader;
		if (!empty($headers['SMIMETYPE']))
		{
			$data['smime'] = Mail\Smime::isSmimeSignatureOnly($headers['SMIMETYPE']) ?
				Mail\Smime::TYPE_SIGN : Mail\Smime::TYPE_ENCRYPT;
		}
		return $data;
	}

	/**
	 * Create the bodypart of the email as textual representation
	 *
	 * @param array $_bodyParts with the bodyparts
	 * @param boolean $modifyURI switch to activate links/resolve inline images
	 * @param boolean $useTidy switch to use tidy
	 * @return string a preformatted string with the mails converted to text
	 */
	public function &getdisplayableBody($_bodyParts, $modifyURI=true, $useTidy=true)
	{
		$bodyParts = $_bodyParts;

		$nonDisplayAbleCharacters = ['[\016]', '[\017]',
			'[\020]', '[\021]', '[\022]', '[\023]', '[\024]', '[\025]', '[\026]', '[\027]',
			'[\030]', '[\031]', '[\032]', '[\033]', '[\034]', '[\035]', '[\036]', '[\037]'];

		$body = '';

		if (empty($bodyParts))
		{
			$ret = '';
			return $ret;
		}
		foreach ((array)$bodyParts as $singleBodyPart)
		{
			if (!isset($singleBodyPart['body']))
			{
				$singleBodyPart['body'] = $this->getdisplayableBody($singleBodyPart, $modifyURI, $useTidy);
				$body .= $singleBodyPart['body'];
				continue;
			}
			$bodyPartIsSet = strlen(trim($singleBodyPart['body']));
			if (!$bodyPartIsSet)
			{
				$body .= '';
				continue;
			}
			if (!empty($body))
			{
				$body .= '<hr style="border:dotted 1px silver;">';
			}
			// some characterreplacements, as they fail to translate
			$sar = [
				'@(\x84|\x93|\x94)@',
				'@(\x96|\x97|\x1a)@',
				'@(\x82|\x91|\x92)@',
				'@(\x85)@',
				'@(\x86)@',
				'@(\x99)@',
				'@(\xae)@',
			];
			$rar = [
				'"',
				'-',
				'\'',
				'...',
				'&',
				'(TM)',
				'(R)',
			];

			if (($singleBodyPart['mimeType'] == 'text/html' || $singleBodyPart['mimeType'] == 'text/plain') &&
				strtoupper($singleBodyPart['charSet']) != 'UTF-8')
			{
				// check if client set a wrong charset and content is utf-8 --> use utf-8
				if (preg_match('//u', $singleBodyPart['body']))
				{
					$singleBodyPart['charSet'] = 'UTF-8';
				}
				else
				{
					$singleBodyPart['body'] = preg_replace($sar, $rar, $singleBodyPart['body']);
				}
			}
			if ($singleBodyPart['charSet'] == 'us-ascii')
			{
				$singleBodyPart['charSet'] = Api\Translation::detect_encoding($singleBodyPart['body']);
			}
			$singleBodyPart['body'] = Api\Translation::convert_jsonsafe($singleBodyPart['body'], $singleBodyPart['charSet']);
			if ($singleBodyPart['mimeType'] == 'text/plain')
			{
				$newBody = @htmlentities($singleBodyPart['body'], ENT_QUOTES, strtoupper(Mail::$displayCharset));
				// if empty and charset is utf8 try sanitizing the string in question
				if (empty($newBody) && strtolower($singleBodyPart['charSet']) == 'utf-8')
				{
					$newBody = @htmlentities(iconv('utf-8', 'utf-8', $singleBodyPart['body']), ENT_QUOTES, strtoupper(Mail::$displayCharset));
				}
				// if the conversion to htmlentities fails somehow, try without specifying the charset, which defaults to iso-
				if (empty($newBody))
				{
					$newBody = htmlentities($singleBodyPart['body'], ENT_QUOTES);
				}

				// create links for websites
				if ($modifyURI)
				{
					$newBody = Api\Html::activate_links($newBody);
				}

				// create links for inline images
				if ($modifyURI)
				{
					$newBody = BodyHandler::resolveInlineImages($newBody, $this->ui->mailbox, $this->ui->uid, $this->ui->partID, 'plain');
				}

				// to display a mailpart of mimetype plain/text, may be better taged as preformatted
				$newBody = "<pre>".BodyDecoding::wordwrap($newBody, 90, "\n", '&gt;')."</pre>";
			}
			else
			{
				$newBody = $singleBodyPart['body'];

				// remove script tags incl. their content, includes e.g. <script type="application/ld+json">
				// before HtmLawed below only removes the script-tags but leaves the content
				Mail\Html::replaceTagsCompletley($newBody, 'script');

				if ($useTidy && extension_loaded('tidy'))
				{
					$tidy = new tidy();
					$cleaned = $tidy->repairString($newBody, Mail::$tidy_config, 'utf8');
					// Found errors. Strip it all so there's some output
					if ($tidy->getStatus() == 2)
					{
						error_log(__METHOD__.' ('.__LINE__.') '.' ->'.$tidy->errorBuffer);
					}
					else
					{
						$newBody = $cleaned;
					}
					// filter only the 'body', as we only want that part, if we throw away the html
					if (preg_match('`(<htm.+?<body[^>]*>)(.+?)(</body>.*?</html>)`ims', $newBody, $matches) && !empty($matches[2]))
					{
						$hasOther = true;
						$newBody = $matches[2];
					}
				}
				else
				{
					$htmLawed = new Api\Html\HtmLawed();
					// the next line should not be needed, but produces better results on HTML 2 Text conversion,
					// as we switched off HTMLaweds tidy functionality
					$newBody = str_replace(['&amp;amp;', '<DIV><BR></DIV>', "<DIV>&nbsp;</DIV>", '<div>&nbsp;</div>'], ['&amp;', '<BR>', '<BR>', '<BR>'], $newBody);
					$newBody = $htmLawed->run($newBody, Mail::$htmLawed_config);
				}
				// do the cleanup, set for the use of purifier
				BodyDecoding::getCleanHTML($newBody);

				// removes stuff between http and ?http
				$Protocol = '(http:\/\/|(ftp:\/\/|https:\/\/))';    // only http:// gets removed, other protocolls are shown
				$newBody = preg_replace('~'.$Protocol.'[^>]*\?'.$Protocol.'~sim', '$1', $newBody); // removes stuff between http:// and ?http://
				// TRANSFORM MAILTO LINKS TO EMAILADDRESS ONLY, WILL BE SUBSTITUTED BY parseEmail TO CLICKABLE LINK
				$newBody = preg_replace('/(?<!"|href=|href\s=\s|href=\s|href\s=)'.'mailto:([a-z0-9._-]+)@([a-z0-9_-]+)\.([a-z0-9._-]+)/i',
					"\\1@\\2.\\3",
					$newBody);

				// create links for inline images
				if ($modifyURI)
				{
					$newBody = BodyHandler::resolveInlineImages($newBody, $this->ui->mailbox, $this->ui->uid, $this->ui->partID);
				}
				// email addresses / mailto links get now activated on client-side
			}

			$body .= $newBody;
		}
		// create links for windows shares
		// \\\\\\\\ == '\\' in real life!! :)
		$body = preg_replace("/(\\\\\\\\)([\w,\\\\,-]+)/i",
			"<a href=\"file:$1$2\" target=\"_blank\"><font color=\"blue\">$1$2</font></a>", $body);

		$body = preg_replace($nonDisplayAbleCharacters, '', $body);

		return $body;
	}
}
