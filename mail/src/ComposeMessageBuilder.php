<?php
/**
 * EGroupware Mail: shared message-building logic for Compose and Send
 *
 * @link https://www.egroupware.org
 * @package mail
 * @license https://opensource.org/license/gpl-2-0 GPL 2.0+ - GNU General Public License 2.0 or any higher version of your choice
 */

namespace EGroupware\Mail;

use EGroupware\Api;
use EGroupware\Api\Mail;
use EGroupware\Api\Vfs;
use EGroupware\Mail\Ui\AttachmentJmap;

/**
 * Shared between Compose (classic compose UI - saveAsDraft()/ajax_saveAsDraft()) and Send
 * (REST API - send()): both build an Api\Mailer from posted form-data + identity the same way.
 * Extracted 2026-09-08 when Send was split out of Compose, to avoid either duplicating this
 * ~350-line MIME-building logic or making Send depend on a full Compose instance.
 *
 * A using class needs its own $mail_bo/$mailPreferences/$displayCharset properties (declared
 * here) and must call initMailAccount() from its own constructor.
 */
trait ComposeMessageBuilder
{
	/**
	 * Instance of Mail
	 *
	 * @var Mail
	 */
	var $mail_bo;

	/**
	 * Active preferences, reference to $this->mail_bo->mailPreferences
	 *
	 * @var array
	 */
	var $mailPreferences;

	var $displayCharset;

	/**
	 * Connect to the given (or user's currently active) mail account and make sure mail config is
	 * loaded - identical setup Compose's own constructor already did, extracted so Send's
	 * constructor can share it without depending on a Compose instance.
	 *
	 * @param ?int $_acc_id
	 */
	protected function initMailAccount(?int $_acc_id=null) : void
	{
		$this->displayCharset   = Api\Translation::charset();

		$profileID = $_acc_id ?: (int)$GLOBALS['egw_info']['user']['preferences']['mail']['ActiveProfileID'];
		$this->mail_bo	= Mail::getInstance(true,$profileID);
		$GLOBALS['egw_info']['user']['preferences']['mail']['ActiveProfileID'] = $this->mail_bo->profileID;

		$this->mailPreferences	=& $this->mail_bo->mailPreferences;
		//force the default for the forwarding -> asmail
		if (!is_array($this->mailPreferences) || empty($this->mailPreferences['message_forwarding']))
		{
			$this->mailPreferences['message_forwarding'] = 'asmail';
		}
		if (is_null(Mail::$mailConfig)) Mail::$mailConfig = Api\Config::read('mail');

		$this->mailPreferences  =& $this->mail_bo->mailPreferences;
	}

	/**
	 * changeProfile
	 *
	 * @param int $_icServerID
	 */
	function changeProfile($_icServerID)
	{
		if ($this->mail_bo->profileID!=$_icServerID)
		{
			if (Mail::$debug) error_log(__METHOD__.__LINE__.'->'.$this->mail_bo->profileID.'<->'.$_icServerID);
			$this->mail_bo = Mail::getInstance(false,$_icServerID);
			if (Mail::$debug) error_log(__METHOD__.__LINE__.' Fetched IC Server:'.$this->mail_bo->profileID.':'.function_backtrace());
			// no icServer Object: something failed big time
			if (!isset($this->mail_bo->icServer)) exit; // ToDo: Exception or the dialog for setting up a server config
			$this->mail_bo->openConnection($this->mail_bo->profileID);
			$this->mailPreferences  =& $this->mail_bo->mailPreferences;
		}
	}

	function convertHTMLToText(&$_html,$sourceishtml = true, $stripcrl=false, $noRepEmailAddr = false)
	{
		$stripalltags = true;
		// third param is stripalltags, we may not need that, if the source is already in ascii
		if (!$sourceishtml) $stripalltags=false;
		return Api\Mail\Html::convertHTMLToText($_html,$this->displayCharset,$stripcrl,$stripalltags, $noRepEmailAddr);
	}

	/**
	 * Wrap html block in given tag with preferred font and -size set
	 *
	 * @param string $content
	 * @param string $legend
	 * @param ?string $class
	 * @return string
	 */
	static function wrapBlockWithPreferredFont($content, $legend, $class=null)
	{
		if (!empty($class)) $options = ' class="'.htmlspecialchars($class).'"';

		return Api\Html::fieldset($content, $legend, $options ?? '');
	}

	/**
	 * Create a message from given data and identity
	 *
	 * @param Api\Mailer $_mailObject
	 * @param array $_formData
	 * @param array $_identity
	 * @param boolean $_autosaving =false true: autosaving, false: save-as-draft or send
	 *
	 * @return array returns found inline images as attachment structure
	 */
	function createMessage(Api\Mailer $_mailObject, array $_formData, array $_identity, $_autosaving=false)
	{
		// fail-safe: the filemode select can be missing from the postback (eg. an id-namespacing
		// bug in an older compose.xet - fixed 2026-09-03 - or a mail_compose_prepare hook that never
		// touches it) - missing must mean "attach", never "share link" (found via a 3rd-party
		// (achelper) patch, 2026-09-03)
		if (empty($_formData['filemode']))
		{
			$_formData['filemode'] = Vfs\Sharing::ATTACH;
		}
		if (substr($_formData['body'], 0, 27) == '-----BEGIN PGP MESSAGE-----')
		{
			$_formData['mimeType'] = 'openpgp';
		}
		$mail_bo	= $this->mail_bo;
		$activeMailProfile = Mail\Account::read($this->mail_bo->profileID);

		// you need to set the sender, if you work with different identities, since most smtp servers, dont allow
		// sending in the name of someone else
		if ($_identity['ident_id'] != $activeMailProfile['ident_id'] && !empty($_identity['ident_email']) && strtolower($activeMailProfile['ident_email']) != strtolower($_identity['ident_email']))
		{
			error_log(__METHOD__.__LINE__.' Faking From/SenderInfo for '.$activeMailProfile['ident_email'].' with ID:'.$activeMailProfile['ident_id'].'. Identitiy to use for sending:'.array2string($_identity));
		}
		$email_From =  $_identity['ident_email'] ? $_identity['ident_email'] : $activeMailProfile['ident_email'];
		// Try to fix identity email with no domain part set
		$_mailObject->setFrom(Mail::fixInvalidAliasAddress(Api\Accounts::id2name($_identity['account_id'], 'account_email'), $email_From),
			\mail_tree::getIdentityName($_identity, false));

		$_mailObject->addHeader('X-Priority', $_formData['priority']);
		$_mailObject->addHeader('X-Mailer', 'EGroupware-Mail');
		if(!empty($_formData['in-reply-to'])) {
			if (stripos($_formData['in-reply-to'],'<')===false) $_formData['in-reply-to']='<'.trim($_formData['in-reply-to']).'>';
			$_mailObject->addHeader('In-Reply-To', $_formData['in-reply-to']);
		}
		if(!empty($_formData['references'])) {
			if (stripos($_formData['references'],'<')===false)
			{
				$_formData['references']='<'.trim($_formData['references']).'>';
			}
			$_mailObject->addHeader('References', $_formData['references']);
		}

		if(!empty($_formData['thread-index'])) {
			$_mailObject->addHeader('Thread-Index', $_formData['thread-index']);
		}
		if(!empty($_formData['list-id'])) {
			$_mailObject->addHeader('List-Id', $_formData['list-id']);
		}
		if(isset($_formData['disposition']) && $_formData['disposition'] === 'on') {
			$_mailObject->addHeader('Disposition-Notification-To', $_identity['ident_email']);
		}

		// Expand any mailing lists
		foreach(array('to', 'cc', 'bcc', 'replyto')  as $field)
		{
			if ($field != 'replyto') $_formData[$field] = self::resolveEmailAddressList($_formData[$field]);

			if ($_formData[$field]) $_mailObject->addAddress($_formData[$field], '', $field);
		}

		$_mailObject->addHeader('Subject', $_formData['subject']);

		$disableRuler = false;
		$signature = $_identity['ident_signature'];
		$sigAlreadyThere = $this->mailPreferences['insertSignatureAtTopOfMessage']!='no_belowaftersend'?1:0;
		if ($sigAlreadyThere && empty($_formData['add_signature']))
		{
			// note: if you use stationery ' s the insert signatures at the top does not apply here anymore, as the signature
			// is already part of the body, so the signature part of the template will not be applied.
			$signature = null; // note: no signature, no ruler!!!!
		}
		if ((isset($this->mailPreferences['disableRulerForSignatureSeparation']) &&
			$this->mailPreferences['disableRulerForSignatureSeparation']) ||
			empty($signature) || trim($this->convertHTMLToText($signature)) =='')
		{
			$disableRuler = true;
		}
		if ($_formData['attachments'] && $_formData['filemode'] != Vfs\Sharing::ATTACH && !$_autosaving)
		{
			$attachment_links = $this->_getAttachmentLinks($_formData['attachments'], $_formData['filemode'],
				// @TODO: $content['mimeType'] could type string/boolean. At the moment we can't strictly check them :(.
				// @TODO: This needs to be fixed in compose function to get the right type from the content.
				$_formData['mimeType'] == 'html',
				array_unique(array_merge((array)$_formData['to'], (array)$_formData['cc'], (array)$_formData['bcc'])),
				$_formData['expiration'], $_formData['password']);
		}
		switch ($_formData['mimeType'])
		{
			case 'html':
				$body = $_formData['body'];

				static $attachment_block = '<fieldset class="attachments mceNonEditable"';
				if (!empty($attachment_links))
				{
					// if we have a placeholder, replace it with the attachment block
					if(strpos($body, $attachment_block) !== false)
					{
						$body = preg_replace('#' . $attachment_block . '[^>]*>.*</fieldset>#', $attachment_links, $body);
					}
					// else place it before the signature
					elseif (strpos($body, '<!-- HTMLSIGBEGIN -->') !== false)
					{
						$body = str_replace('<!-- HTMLSIGBEGIN -->', $attachment_links.'<!-- HTMLSIGBEGIN -->', $body);
					}
					else
					{
						$body .= $attachment_links;
					}
				}
				static $ruler = '<hr class="ruler"';
				$body = str_replace($ruler, '<hr', $body);  // remove id from ruler, to not replace in cited mails

				if(!empty($signature))
				{
					$_mailObject->setBody($this->convertHTMLToText($body, true, true).
						($disableRuler ? "\r\n" : "\r\n-- \r\n").
						$this->convertHTMLToText($signature, true, true));

					$body .= ($disableRuler ?'<p><br/></p>':'<hr style="border:1px dotted silver; width:90%;">').$signature;
				}
				else
				{
					$_mailObject->setBody($this->convertHTMLToText($body, true, true));
				}
				// convert URL Images to inline images - if possible
				if (!$_autosaving) $inline_images = Mail::processURL2InlineImages($_mailObject, $body, $mail_bo);
				if (strpos($body,"<!-- HTMLSIGBEGIN -->")!==false)
				{
					$body = str_replace(array('<!-- HTMLSIGBEGIN -->','<!-- HTMLSIGEND -->'),'',$body);
				}
				$_mailObject->setHtmlBody($body, null, false);	// false = no automatic alternative, we called setBody()
				break;
			case 'openpgp':
				$_mailObject->setOpenPgpBody($_formData['body'].$attachment_links);
				break;
			default:
				$body = $this->convertHTMLToText($_formData['body'],false, false, true, true);

				if (!empty($attachment_links)) $body .= $attachment_links;

				#$_mailObject->Body = $_formData['body'];
				if(!empty($signature)) {
					$body .= ($disableRuler ?"\r\n":"\r\n-- \r\n").
						$this->convertHTMLToText($signature,true,true);
				}
				$_mailObject->setBody($body);
		}
		// add the attachments
		if (is_array($_formData) && isset($_formData['attachments']))
		{
			$connection_opened = false;
			$tnfattachments = null;
			foreach((array)$_formData['attachments'] as $attachment) {
				if(is_array($attachment))
				{
					if (!empty($attachment['uid']) && !empty($attachment['folder'])) {
						/* Example:
						Array([0] => Array(
						[uid] => 21178
						[partID] => 2
						[name] => [Untitled].pdf
						[type] => application/pdf
						[size] => 622379
						[folder] => INBOX))
						*/
						if (!$connection_opened)
						{
							$mail_bo->openConnection($mail_bo->profileID);
							$connection_opened = true;
						}
						$mail_bo->reopen($attachment['folder']);
						try
						{
							switch(strtoupper($attachment['type'])) {
								case 'MESSAGE/RFC':
								case 'MESSAGE/RFC822':
									$rawBody='';
									if (isset($attachment['partID'])) {
										$eml = $mail_bo->getAttachment($attachment['uid'],$attachment['partID'],0,false,true,$attachment['folder']);
										$rawBody=$eml['attachment'];
									} else {
										$rawBody        = $mail_bo->getMessageRawBody($attachment['uid'], $attachment['partID'],$attachment['folder']);
									}
									$_mailObject->addStringAttachment($rawBody, $attachment['name'], 'message/rfc822');
									break;
								default:
									$attachmentData	= $mail_bo->getAttachment($attachment['uid'], $attachment['partID'],0,false);
									if ($attachmentData['type'] == 'APPLICATION/MS-TNEF')
									{
										// mail_bo has no decode_winmail() method (never existed - this whole
										// branch fataled before this fix) - Mail::tnef_decoder() is the real,
										// already-existing static TNEF decoder (see api/src/Mail.php's own
										// getMessageAttachments()/getAttachment() for the same pattern), given
										// the raw winmail.dat bytes already fetched above
										if (!is_array($tnfattachments)) $tnfattachments = Mail::tnef_decoder($attachmentData['attachment'])?->getParts() ?? [];
										foreach ($tnfattachments as $part)
										{
											if (Mail::attachmentName($part) == $attachment['name'])
											{
												$attachmentData['attachment'] = $part->getContents();
												$attachmentData['type'] = $part->getType();
												break;
											}
										}
									}
									$_mailObject->addStringAttachment($attachmentData['attachment'], $attachment['name'], $attachment['type']);
									break;
							}
						}
						catch (\Exception $e)
						{
							// the message the attachment was taken from is gone (deleted or moved) since it was attached
							throw new Api\Exception\WrongUserinput(lang(
								"Could not attach '%1': the original message is no longer available (deleted or moved).",
								$attachment['name']
							), 0, $e);
						}
					}
					// attach files not for autosaving, if size-limit is configured and attachment is bigger
					elseif ($_formData['filemode'] == Vfs\Sharing::ATTACH)
					{
						// exclude attachments greater configured size from autosaving
						if ($_autosaving && !empty(Mail::$mailConfig['autosave_attachment_limit_mb']) &&
							$attachment['size'] > 1024*1024*Mail::$mailConfig['autosave_attachment_limit_mb'])
						{
							continue;
						}
						// JMAP-mode reply/forward attachment carry-forward (mail/js/compose.ts's
						// carryForwardAttachments()/mergeAttachmentEntries()) - a bare {jmapBlobId,
						// jmapProfileID} reference, no uid/folder (not addressed via classic IMAP)
						// and no real local `file` (never downloaded client-side at all, see
						// MailJmap.fetchForReply()'s own docblock for why) - reached whenever
						// trySendViaJmap() (compose.ts) itself couldn't complete the send (a
						// blocking cross-app toggle, or an account whose backend doesn't support
						// JMAP-native send yet) and falls through to this classic postback instead.
						// Found live 2026-09-02 investigating a "attachments are always sent as
						// sharing links, not real attachments" bug report: without this branch,
						// isset($attachment['file']) is false, parse_url(null, ...) warns, and
						// addAttachment() is handed a bogus temp_dir/'' path - silently dropping the
						// attachment (or erroring) instead of sending it. Fetched via the same
						// backend-uniform blob-fetch primitive the TNEF-as-attachment fix uses.
						if (!empty($attachment['jmapBlobId']))
						{
							$bytes = AttachmentJmap::fetchBlobBytes(
								(string)($attachment['jmapProfileID'] ?? $mail_bo->profileID),
								$attachment['jmapBlobId'], $attachment['name'] ?? 'attachment',
								$attachment['type'] ?? 'application/octet-stream');
							if ($bytes === null)
							{
								throw new Api\Exception\WrongUserinput(lang(
									"Could not attach '%1': the original message is no longer available (deleted or moved).",
									$attachment['name']
								));
							}
							$_mailObject->addStringAttachment($bytes, $attachment['name'], $attachment['type']);
							continue;
						}
						if (isset($attachment['file']) && parse_url($attachment['file'],PHP_URL_SCHEME) == 'vfs')
						{
							Vfs::load_wrapper('vfs');
							$tmp_path = $attachment['file'];
						}
						// mail/js/compose.ts's vfsUpload() (JMAP mode, VFS-selected file) - a bare
						// {jmapVfsPath} reference, no `file` set at all (nothing uploaded/fetched
						// client-side, see vfsUpload()'s own docblock) - same fallback-to-classic
						// reachability as the jmapBlobId case above, but the file already IS a real
						// VFS path, so it just needs the same vfs:// prefix the branch above expects.
						elseif (!empty($attachment['jmapVfsPath']))
						{
							Vfs::load_wrapper('vfs');
							$tmp_path = Vfs::PREFIX.$attachment['jmapVfsPath'];
						}
						else	// non-vfs file has to be in temp_dir
						{
							$tmp_path = $GLOBALS['egw_info']['server']['temp_dir'].'/'.basename($attachment['file']);
						}
						$_mailObject->addAttachment (
							$tmp_path,
							$attachment['name'],
							$attachment['type']
						);
					}
				}
			}
			if ($connection_opened) $mail_bo->closeConnection();
		}
		return $inline_images ?? [];
	}

	/**
	 * Get html or text containing links to attachments
	 *
	 * We only care about file attachments, not forwarded messages or parts
	 *
	 * @param array $attachments
	 * @param string $filemode Vfs\Sharing::(ATTACH|LINK|READONL|WRITABLE)
	 * @param boolean $html
	 * @param array $recipients =array()
	 * @param string $expiration =null
	 * @param string $password =null
	 * @return string might be empty if no file attachments found
	 */
	protected function _getAttachmentLinks(array $attachments, $filemode, $html, $recipients=array(), $expiration=null, $password=null)
	{
		if ($filemode == Vfs\Sharing::ATTACH) return '';

		$links = array();
		foreach($attachments as $attachment)
		{
			$path = $attachment['file'];
			if (empty($path)) continue;	// we only care about file attachments, not forwarded messages or parts
			if (parse_url($attachment['file'],PHP_URL_SCHEME) != 'vfs')
			{
				$path = $GLOBALS['egw_info']['server']['temp_dir'].'/'.basename($path);
			}
			// create share
			if ($filemode == Vfs\Sharing::WRITABLE || $expiration || $password)
			{
				$share = \stylite_sharing::create($path, $filemode, $attachment['name'], $recipients, $expiration, $password);
			}
			else
			{
				$share = Vfs\Sharing::create('', $path, $filemode, $attachment['name'], $recipients);
			}
			$link = Vfs\Sharing::share2link($share);

			$name = Vfs::basename($attachment['name'] ? $attachment['name'] : $attachment['file']);

			if ($html)
			{
				$links[] = Api\Html::a_href($name, $link).' '.
					(is_dir($path) ? lang('Directory') : Vfs::hsize($attachment['size']));
			}
			else
			{
				$links[] = $name.' '.Vfs::hsize($attachment['size']).': '.
					(is_dir($path) ? lang('Directory') : $link);
			}
		}
		if (!$links)
		{
			return null;	// no file attachments found
		}
		elseif ($html)
		{
			return self::wrapBlockWithPreferredFont("<ul><li>".implode("</li>\n<li>", $links)."</li></ul>\n", lang('Download attachments'), 'attachmentLinks');
		}
		return lang('Download attachments').":\n- ".implode("\n- ", $links)."\n";
	}

	/**
	 * resolveEmailAddressList
	 * @param array $_emailAddressList list of emailaddresses, may contain distributionlists
	 * @return array return the list of emailaddresses with distributionlists resolved
	 */
	static function resolveEmailAddressList($_emailAddressList)
	{
		static $contacts_obs = null;
		$addrFromList=array();
		foreach((array)$_emailAddressList as $ak => $address)
		{
			if(is_numeric($address) && $address > 0 || preg_match('/ <(-?\d+)@lists.egroupware.org>$/', $address, $matches))
			{
				if(!isset($contacts_obs))
				{
					$contacts_obj = new Api\Contacts();
				}
				// List was selected, expand to addresses
				unset($_emailAddressList[$ak]);
				foreach($contacts_obj->search('',array('n_fn','n_prefix','n_given','n_family','org_name','email','email_home'),
					'','','',False,'AND',false,
					['list' => (int)($matches[1] ?? $address)]) as $email)
				{
					$addrFromList[] = $email['email'] ?: $email['email_home'];
				}
			}
		}
		return array_values(array_merge((array)$_emailAddressList, $addrFromList));
	}

	/**
	 * Method to do encryption on given mail object
	 *
	 * @param Api\Mailer $mail
	 * @param string $type encryption type
	 * @param array|string $recipients list of recipients
	 * @param string $sender email of sender
	 * @param string $passphrase = '', SMIME Private key passphrase
	 *
	 * @return boolean returns true if successful and false if passphrase required
	 * @throws Api\Exception\WrongUserinput if no certificate found
	 */
	protected function _encrypt($mail, $type, $recipients, $sender, $passphrase='')
	{
		$AB = new \addressbook_bo();
		 // passphrase of sender private key
		$params['passphrase'] = $passphrase;

		try
		{
			$sender_cert = $AB->get_smime_keys($sender);
			if (!$sender_cert)	throw new \Exception(lang("S/MIME Encryption failed because no certificate has been found for sender address: %1", $sender));
			$params['senderPubKey'] = $sender_cert[strtolower($sender)];

			if (isset($sender) && ($type == Mail\Smime::TYPE_SIGN || $type == Mail\Smime::TYPE_SIGN_ENCRYPT))
			{
				$acc_smime = Mail\Smime::get_acc_smime($this->mail_bo->profileID, $params['passphrase']);
				$params['senderPrivKey'] = $acc_smime['pkey'] ?? null;
				// extracerts also holds retired own certificates kept around to still decrypt old
				// mail (see Smime::decryptWithCandidates()) - only actual CA/intermediate
				// certificates (not belonging to our own key) belong in the chain sent with
				// outgoing signed mail
				$params['extracerts'] = !empty($acc_smime['extracerts']) ?
					array_values(array_filter($acc_smime['extracerts'],
						fn($c) => !Mail\Smime::isOwnCertificate($c, $acc_smime['pkey'], $params['passphrase']))) : null;
			}

			if (isset($recipients) && ($type == Mail\Smime::TYPE_ENCRYPT || $type == Mail\Smime::TYPE_SIGN_ENCRYPT))
			{
				$params['recipientsCerts'] = $AB->get_smime_keys($recipients);
				foreach ($recipients as &$recipient)
				{
					if (empty($params['recipientsCerts'][strtolower($recipient)])) $missingCerts[] = $recipient;
				}
				if (!empty($missingCerts)) throw new \Exception ('S/MIME Encryption failed because no certificate has been found for following addresses: '. implode ('|', $missingCerts));
			}

			return $mail->smimeEncrypt($type, $params);
		}
		catch(Api\Exception\WrongUserinput $e)
		{
			throw new $e;
		}
	}
}
