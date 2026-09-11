<?php
/**
 * EGroupware Mail: REST API
 *
 * @link https://www.egroupware.org
 * @package mail
 * @author Ralf Becker <rb@egroupware.org>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Mail;

use EGroupware\Api;
use OpenAI\Exceptions\InvalidArgumentException;

/**
 * REST API for mail
 */
class ApiHandler extends Api\CalDAV\Handler
{
	/**
	 * Constructor
	 *
	 * @param string $app 'calendar', 'addressbook' or 'infolog'
	 * @param Api\CalDAV $caldav calling class
	 */
	function __construct($app, Api\CalDAV $caldav)
	{
		parent::__construct($app, $caldav);
	}

	/**
	 * Options for json_encode of responses
	 */
	const JSON_RESPONSE_OPTIONS = JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR;

	/**
	 * Handle post request for mail (send or compose mail and upload attachments)
	 *
	 * @param array &$options
	 * @param int $id
	 * @param int $user =null account_id of owner, default null
	 * @return mixed boolean true on success, false on failure or string with http status (eg. '404 Not Found')
	 */
	function post(&$options,$id,$user=null)
	{
		if ($this->debug) error_log(__METHOD__."($id, $user)".print_r($options,true));
		// remove the optional id-parameter put in literally as "/mail/{id}"
		$path = str_replace('/{id}', '', $options['path']);
		if (empty($user))
		{
			$user = $GLOBALS['egw_info']['user']['account_id'];
		}
		else
		{
			$prefix = '/'.Api\Accounts::id2name($user);
			if (str_starts_with($path, $prefix)) $path = substr($path, strlen($prefix));
			if ($user  != $GLOBALS['egw_info']['user']['account_id'])
			{
				throw new \Exception("/mail is NOT available for users other than the one you authenticated!", 403);
			}
		}
		header('Content-Type: application/json');

		try {
			if (str_starts_with($path, '/mail/attachments/'))
			{
				return self::storeAttachment($path, $options['stream'] ?? $options['content']);
			}
			elseif (preg_match('#^/mail(/(\d+))?/vacation/?$#', $path, $matches))
			{
				return self::updateVacation($user, $options['content'], $matches[2]);
			}
			elseif (preg_match('#^/mail(/(\d+))?/view/?$#', $path, $matches))
			{
				return self::viewEml($user, $options['stream'] ?? $options['content'], $matches[2]);
			}
			elseif (preg_match('#^/mail(/(\d+))?(/compose)?#', $path, $matches))
			{
				$ident_id = $matches[2] ?? null ?: self::defaultIdentity($user);
				$do_compose = (bool)($matches[3] ?? false);
				// check if sending mail is allowed for the current user-agent
				if (!Api\CalDAV\OpenAPI::checkOperationId(($do_compose ? 'launchMailCompose' : 'sendMail').($matches[2]??null ? 'For' : ''), $_SERVER['HTTP_USER_AGENT']))
				{
					throw new \Exception("$path is NOT available for this user-agent ($_SERVER[HTTP_USER_AGENT])!", 403);
				}
				if (!($data = json_decode($options['content'], true)))
				{
					throw new \Exception('Error decoding JSON: '.json_last_error_msg(), 422);
				}
				// ToDo: check required attributes

				$params = [];

				// should we reply to an eml file
				if (!empty($data['replyEml']))
				{
					if (preg_match('#^/mail/attachments/(([^/]+)--[^/.-]{6,})$#', $data['replyEml'], $matches) &&
						file_exists($eml=$GLOBALS['egw_info']['server']['temp_dir'].'/attach--'.$matches[1]))
					{
						// import mail into drafts folder
						$acc_id = Api\Mail\Account::read_identity($ident_id)['acc_id'];
						$mail = Api\Mail::getInstance(false, $acc_id);
						$folder = $mail->getDraftFolder();
						$mailer = new Api\Mailer();
						$mail->parseFileIntoMailObject($mailer, $eml);
						$mail->openConnection();
						$uid = $mail->appendMessage($folder, $mailer->getRaw(), null, '\\Seen');
						// and generate row-id from it to pass as reply_id to compose
						$params['reply_id'] = Ui::generateRowID($acc_id, $folder, $uid, true);
						$params['from'] = 'reply';
					}
					else
					{
						throw new \Exception("Reply message eml '{$data['reply_eml']}' NOT found", 400);
					}
				}

				// determine to use html or plain-text based on user preference and what's supplied in REST API call
				$type = $GLOBALS['egw_info']['user']['preferences']['mail']['composeOptions'] === 'html' ||
					!empty($data['bodyHtml']) ? 'html' : 'plain';
				$body = $data['bodyHtml'] ?? null ?: $data['body'] ?? '';
				// if user wants html, but REST API caller supplied plain --> convert to html
				if (!empty($body) && empty($data['bodyHtml']))
				{
					$body = Api\Mail\Html::convertTextToHtml($body);
				}

				$preset = array_filter(array_intersect_key($data, array_flip(['to', 'cc', 'bcc', 'replyto', 'subject', 'priority', 'reply_id']))+[
					'body' => $body,
					'mimeType' => $type,
					'identity' => $ident_id,
				]+self::prepareAttachments($data['attachments'] ?? [], $data['attachmentType'] ?? 'attach',
					$data['shareExpiration'], $data['sharePassword'], $do_compose));

				// for compose we need to construct a URL and push it to the client (or give an error if the client is not online)
				if ($do_compose)
				{
					if (!Api\Json\Push::isOnline($user))
					{
						$account_lid = Api\Accounts::id2name($user);
						throw new \Exception("User '$account_lid' (#$user) is NOT online", 404);
					}
					$push = new Api\Json\Push($user);
					$push->call('egw.open', '', 'mail', 'add', $params+['preset' => $preset], '_blank', 'mail');
					echo json_encode([
						'status' => 200,
						'message' => 'Request to open compose window sent',
						//'data' => $preset,
					], self::JSON_RESPONSE_OPTIONS);
					return true;
				}
				$acc_id = $acc_id ?? Api\Mail\Account::read_identity($ident_id)['acc_id'];
				$mail_account = Api\Mail\Account::read($acc_id);

				// JMAP-native send path (real-JMAP/Stalwart accounts only) - see doc/ai/projects/
				// mail-compose-jmap-migration.md. Attachments ARE supported (sendViaJmap()
				// consolidated onto Api\Mailer + Api\Mail\Jmap\Transport 2026-09-02); a reply
				// still falls through to the classic Send path below (importing the
				// replied-to .eml into Drafts first isn't wired into this path yet) -
				// $preset['file'] is realistically never set here anyway (prepareAttachments()
				// only populates it in $do_compose mode, already returned above), kept as a
				// defensive guard rather than a real exclusion.
				if (is_a($mail_account->acc_imap_type, Api\Mail\Imap\Jmap::class, true) &&
					empty($preset['reply_id']) && empty($preset['file']))
				{
					self::sendViaJmap($mail_account, $ident_id, $preset);
					echo json_encode([
						'status' => 200,
						'message' => 'Mail successful sent',
					], self::JSON_RESPONSE_OPTIONS);
					return true;
				}
				// check if the mail-account requires a user-context / password and then just send the mail with an smtp-only account NOT saving to Sent folder
				if (empty($mail_account->acc_imap_password) || $mail_account->acc_smtp_auth_session && empty($mail_account->acc_smtp_password))
				{
					$acc_id = Api\Mail\Account::get_default(true, true, true, false);
					$send = new Send($acc_id);
					$send->mailPreferences['sendOptions'] = 'send_only';
					$warning = 'Mail NOT saved to Sent folder, as no user password';
				}
				else
				{
					$send = new Send($acc_id);
				}
				$preset = array_filter([
					'mailaccount' => $acc_id,
					'mailidentity' => $ident_id,
					'identity' => null,
					'add_signature' => true,    // add signature in send, independent what preference says
				]+$preset);
				if ($send->send($preset, $acc_id))
				{
					echo json_encode(array_filter([
						'status' => 200,
						'warning' => $warning ?? null,
						'message' => 'Mail successful sent',
						//'data' => $preset,
					]), self::JSON_RESPONSE_OPTIONS);
					return true;
				}
				throw new \Exception($send->errorInfo);
			}

			throw new \Exception('Not Found', 404);
		}
		catch (\Throwable $e) {
			return self::handleException($e);
		}
	}

	/**
	 * Get vacation array from server
	 *
	 * @param Api\Mail\Imap $imap
	 * @param ?int $user
	 * @return array
	 */
	protected static function getVacation(Api\Mail\Imap $imap, ?int $user=null)
	{
		if ($GLOBALS['egw']->session->token_auth)
		{
			return $imap->getVacationUser($user ?: $GLOBALS['egw_info']['user']['account_id']);
		}
		return $imap->getVacation()+['script' => $imap->scriptName];
	}

	/**
	 * Update vacation message/handling with JSON data given in $content
	 *
	 * @param int $user
	 * @param string $content
	 * @param int|null $identity
	 * @return bool
	 * @throws Api\Exception\AssertionFailed
	 * @throws Api\Exception\NotFound
	 */
	protected static function updateVacation(int $user, string $content, ?int $identity=null)
	{
		$account = self::getMailAccount($user, $identity);
		$vacation = $account->imapServer()->getVacationUser($user);
		if (!($update = json_decode($content, true, 3, JSON_THROW_ON_ERROR)))
		{
			throw new \Exeception('Invalid request: no content', 400);
		}
		// Sieve class stores them as timestamps
		foreach(['start', 'end'] as $name)
		{
			if (isset($update[$name]))
			{
				$vacation[$name.'_date'] = (new Api\DateTime($update[$name]))->format('ts');
				if (empty($update['status'])) $update['status'] = 'by_date';
			}
			elseif (array_key_exists($name, $update))
			{
				$vacation[$name.'_date'] = null;
				if (empty($update['status'])) $update['status'] = 'off';
			}
			unset($update[$name]);
		}
		// Sieve class stores them as comma-separated string
		if (array_key_exists('forwards', $update))
		{
			$vacation['forwards'] = implode(',', self::parseAddressList($update['forwards'] ?? [], 'forwards'));
			unset($update['forwards']);
		}
		if (array_key_exists('addresses', $update))
		{
			$update['addresses'] = self::parseAddressList($update['addresses'] ?? [], 'addresses');
		}
		static $modi = ['notice+store', 'notice', 'store'];
		if (isset($update['modus']) && !in_array($update['modus'], $modi))
		{
			throw new \Exception("Invalid value '$update[modus]' for attribute modus, allowed values are: '".implode("', '", $modi)."'", 400);
		}
		if (($invalid=array_diff(array_keys($update), ['start','end','status','modus','text','addresses','forwards','days'])))
		{
			throw new \Exception("Invalid attribute: ".implode(', ', $invalid), 400);
		}
		$vacation_rule = null;
		$vacation = array_merge([   // some defaults
			'status' => 'on',
			'addresses' => [Api\Accounts::id2name($user, 'account_email')],
			'days' => 3,
		], $vacation, $update);
		// for token-auth we have to use the admin connection
		if ($GLOBALS['egw']->session->token_auth)
		{
			if (!$account->imapServer()->setVacationUser($user, $vacation))
			{
				throw new \Exception($account->imapServer()->error ?: 'Error updating sieve-script');
			}
		}
		else
		{
			$account->imapServer()->setVacation($vacation, null, $vacation_rule, true);
		}
		echo json_encode(array_filter([
			'status' => 200,
			'message' => 'Vacation handling updated',
			'vacation_rule' => $vacation_rule,
			'vacation' => self::returnVacation(self::getVacation($account->imapServer(), $user)),
		]), self::JSON_RESPONSE_OPTIONS);
		return true;
	}

	/**
	 * Parse array of email addresses
	 *
	 * @param string[] $_addresses
	 * @param string $name attribute name for exception
	 * @return string[]
	 * @throws \Exception if there is an invalid email address
	 */
	protected static function parseAddressList(array $_addresses, $name=null)
	{
		$parsed = iterator_to_array(Api\Mail::parseAddressList($_addresses));

		if (count($parsed) !== count($_addresses) ||
			array_filter($parsed, static function ($addr)
			{
				return !$addr->valid;
			}))
		{
			throw new \Exception("Error parsing email-addresses in attribute $name: ".json_encode($_addresses));
		}
		return array_map(static function($addr)
		{
			return $addr->mailbox.'@'.$addr->host;
		}, $parsed);
	}

	/**
	 * Store uploaded attachment and return token
	 *
	 * @param string $path
	 * @param string|stream $content
	 * @return string HTTP status
	 * @throws \Exception on error
	 */
	protected static function storeAttachment(string $path, $content)
	{
		$attachment_path = tempnam($GLOBALS['egw_info']['server']['temp_dir'], 'attach--'.
			(str_replace('/', '-', substr($path, 18)) ?: 'no-name').'--');
		if (is_resource($content) ?
			stream_copy_to_stream($content, $fp=fopen($attachment_path, 'w')) :
			file_put_contents($attachment_path, $content))
		{
			if (isset($fp)) fclose($fp);
			$location = '/mail/attachments/'.substr(basename($attachment_path), 8);
			// allow to suppress location header with an "X-No-Location: true" header
			if (($location_header = empty($_SERVER['HTTP_X_NO_LOCATION'])))
			{
				header('Location: '.Api\Framework::getUrl(Api\Framework::link('/groupdav.php'.$location)));
			}
			$ret = $location_header ? '201 Created' : '200 Ok';
			echo json_encode([
				'status'   => (int)$ret,
				'message'  => 'Attachment stored',
				'location' => $location,
			], self::JSON_RESPONSE_OPTIONS);
			return $ret;
		}
		throw new \Exception('Error storing attachment');
	}

	/**
	 * View posted eml file
	 *
	 * @param int $user
	 * @param string|stream $content
	 * @param ?int $acc_id mail account to import in Drafts folder
	 * @return string HTTP status
	 * @throws \Exception on error
	 */
	protected static function viewEml(int $user, $content, ?int $acc_id=null)
	{
		if (empty($acc_id))
		{
			$acc_id = self::defaultIdentity($user);
		}

		// check and bail, if user is not online
		if (!Api\Json\Push::isOnline($user))
		{
			$account_lid = Api\Accounts::id2name($user);
			throw new \Exception("User '$account_lid' (#$user) is NOT online", 404);
		}

		// save posted eml to a temp-dir
		$eml = tempnam($GLOBALS['egw_info']['server']['temp_dir'], 'view-eml-');
		if ((is_resource($content) ?
			stream_copy_to_stream($content, $fp = fopen($eml, 'w')) :
			file_put_contents($eml, $content)) === false)
		{
			throw new \Exception('Error storing eml file');
		}
		if (isset($fp)) fclose($fp);

		// import mail into drafts folder
		$mail = Api\Mail::getInstance(false, $acc_id);
		$folder = $mail->getDraftFolder();
		$mailer = new Api\Mailer();
		$mail->parseFileIntoMailObject($mailer, $eml);
		$mail->openConnection();
		$message_uid = $mail->appendMessage($folder, $mailer->getRaw(), null, '\\Seen');

		// tell browser to view eml from drafts folder
		$push = new Api\Json\Push($user);
		$push->call('egw.open', Ui::generateRowID($acc_id, $folder, $message_uid, true),
			'mail', 'view', ['mode' => 'display'], '_blank', 'mail');

		// respond with success message
		echo json_encode([
			'status' => 200,
			'message' => 'Request to open view window sent',
		], self::JSON_RESPONSE_OPTIONS);

		return true;
	}

	/**
	 * Send a mail via Api\Mailer, for the lean/direct REST-API send path (see this method's only
	 * call site - a reply, which needs an existing-.eml-into-Drafts import first, still falls
	 * through to the full Send path instead)
	 *
	 * Consolidated 2026-09-02 onto plain Api\Mailer, letting Mail\Account::smtpTransport() pick
	 * the right transport itself (Api\Mail\Jmap\Transport, RFC 8621 §7 EmailSubmission, whenever
	 * acc_smtp_ssl is configured for JMAP submission - same as any other Api\Mailer caller for
	 * this account, see smtpTransport()'s own docblock) - this method used to hand-build the JMAP
	 * Email/set + EmailSubmission/set calls itself, unconditionally, which meant attachments were
	 * silently dropped (never read from $preset at all) and only ever a single body type was sent
	 * (no multipart/alternative plain-text fallback for an HTML body). Api\Mailer/Horde_Mime_Mail
	 * already builds a real, correct MIME message from structured calls (setBody()/setHtmlBody()/
	 * addAttachment()); Api\Mail\Jmap\Transport::send() parses that back into the JMAP Email shape
	 * (plain+html bodyValues, attachments as blobs) and does the same Drafts-create/submit/
	 * move-to-Sent dance this method used to do by hand, when that transport is the one selected.
	 *
	 * @param Api\Mail\Account $mail_account
	 * @param int $ident_id
	 * @param array $preset 'to'/'cc'/'bcc' (comma-separated or array of addresses), 'subject',
	 *  'body', 'mimeType' ('html'|'plain'), optional 'attachments' (prepareAttachments()'s
	 *  non-compose shape: [{name, type, file (a real readable path or vfs:// URI), size}, ...])
	 * @throws \Exception on failure
	 */
	protected static function sendViaJmap(Api\Mail\Account $mail_account, int $ident_id, array $preset)
	{
		$identity = Api\Mail\Account::read_identity($ident_id, true, null, $mail_account);
		if (empty($identity['ident_email']))
		{
			throw new \Exception('Identity #'.$ident_id.' not found', 404);
		}

		$mailer = new Api\Mailer($mail_account);
		// overrides whatever default identity the constructor resolved - the caller may have
		// explicitly selected a DIFFERENT (non-default/"further") identity than the account's own
		$mailer->setFrom($identity['ident_email'], $identity['ident_realname'] ?? '');
		foreach (['to', 'cc', 'bcc'] as $field)
		{
			if (!empty($preset[$field]))
			{
				$mailer->addAddress($preset[$field], '', $field);
			}
		}
		$mailer->addHeader('Subject', $preset['subject'] ?? '');
		if (($preset['mimeType'] ?? 'plain') === 'html')
		{
			// $alternative=true (default): also generates a plain-text alternative
			// automatically - the hand-built version this replaces never sent one at all
			$mailer->setHtmlBody($preset['body'] ?? '');
		}
		else
		{
			$mailer->setBody($preset['body'] ?? '');
		}
		foreach ($preset['attachments'] ?? [] as $attachment)
		{
			$mailer->addAttachment($attachment['file'], $attachment['name'] ?? null, $attachment['type'] ?? null);
		}

		try {
			// no explicit transport - $mail_account->smtpTransport() already returns
			// Api\Mail\Jmap\Transport whenever acc_smtp_ssl is configured for JMAP submission
			// (same selection IMAP/Sieve already use for their own protocols), same as any other
			// Api\Mailer caller for this account. An account that predates the wizard's JMAP
			// submission choice keeps whatever classic SMTP config it was already using - it
			// being JMAP-native on the IMAP side alone does NOT force JMAP submission here.
			$mailer->send();
		}
		catch (\Horde_Mail_Exception $e) {
			throw new \Exception('JMAP send failed: '.$e->getMessage(), 500, $e);
		}
	}

	/**
	 * Get default identity of user
	 *
	 * @param int $user
	 * @return int ident_id
	 * @throws Api\Exception\WrongParameter
	 * @throws \Exception (404) if user has no IMAP account
	 */
	protected static function defaultIdentity(int $user)
	{
		foreach(Api\Mail\Account::search($user,false) as $acc_id => $account)
		{
			// do NOT add SMTP only accounts as identities
			if (!$account->is_imap(false)) continue;

			foreach($account->identities($acc_id) as $ident_id => $identity)
			{
				return $ident_id;
			}
		}
		throw new \Exception("No IMAP account found for user #$user", 404);
	}

	/**
	 * Convert an attachment name into an upload array for compose/send
	 *
	 * @param string[] $attachments either "/mail/attachments/<token>" / file in temp_dir or VFS path
	 * @param ?string $attachmentType "attach" (default), "link", "share_ro", "share_rw"
	 * @param ?string $expiration "YYYY-mm-dd" or e.g. "+2days"
	 * @param ?string $password optional password for the share
	 * @param bool $compose true: for compose window, false: to send
	 * @return array with values for keys "file", "name", "filemode", "expiration" and "password"
	 * @throws Exception if file not found or unreadable
	 */
	/**
	 * @param array $attachments
	 * @param string|null $attachmentType
	 * @param string|null $expiration
	 * @param string|null $password
	 * @param bool $compose
	 * @return array
	 * @throws \Exception
	 */
	protected static function prepareAttachments(array $attachments, ?string $attachmentType=null, ?string $expiration=null, ?string $password=null, bool $compose=true)
	{
		$ret = [];
		foreach($attachments as $attachment)
		{
			if (preg_match('#^/mail/attachments/(([^/]+)--[^/.-]{6,})$#', $attachment, $matches))
			{
				if (!file_exists($path=$GLOBALS['egw_info']['server']['temp_dir'].'/attach--'.$matches[1]))
				{
					throw new \Exception("Attachment $attachment NOT found", 400);
				}
				if ($compose)
				{
					$ret['file'][] = $path;
					$ret['name'][] = $matches[2];
				}
				else
				{
					$ret['attachments'][] = [
						'name' => $matches[2],
						'type' => Api\Vfs::mime_content_type($path),
						'file' => $path,
						'size' => filesize($path),
					];
				}
			}
			else
			{
				if (!Api\Vfs::is_readable($attachment))
				{
					throw new \Exception("Attachment $attachment NOT found", 400);
				}
				if ($compose)
				{
					$ret['file'][] = Api\Vfs::PREFIX.$attachment;
					$ret['name'][] = Api\Vfs::basename($attachment);
				}
				else
				{
					$ret['attachments'][] = [
						'name' => Api\Vfs::basename($attachment),
						'type' => Api\Vfs::mime_content_type($attachment),
						'file' => Api\Vfs::PREFIX.$attachment,
						'size' => filesize(Api\Vfs::PREFIX.$attachment),
					];
				}
			}
		}
		if ($ret)
		{
			$ret['filemode'] = $attachmentType ?? 'attach';
			if (!in_array($ret['filemode'], $valid=['attach', 'link', 'share_ro', 'share_rw']))
			{
				throw new \Exception("Invalid value '$ret[filemode]' for attachmentType, must be one of: '".implode("', '", $valid)."'", 422);
			}
			// EPL share password and expiration
			$ret['password'] = $password ?: null;
			if (!empty($expiration))
			{
				$ret['expiration'] = (new Api\DateTime($expiration))->format('Y-m-d');
			}
		}
		return $ret;
	}

	/**
	 * Handle propfind request for an application folder
	 *
	 * @param string $path
	 * @param array &$options
	 * @param array &$files
	 * @param int $user account_id
	 * @return mixed boolean true on success, false on failure or string with http status (eg. '404 Not Found')
	 */
	function propfind($path,&$options,&$files,$user)
	{
		if ($path === '/mail/' || $user && $path === '/'.Api\Accounts::id2name($user).'/mail/')
		{
			foreach(Api\Mail\Account::search($user ?? true,false) as $acc_id => $account)
			{
				// do NOT add SMTP only accounts as identities
				if (!$account->is_imap(false)) continue;

				foreach($account->identities($acc_id) as $ident_id => $identity)
				{
					$files['files'][] = [
						'path' => $path.$ident_id,
						'props' => [
							'data' => ['val' => $identity],
							'displayname' => Api\CalDAV::mkprop('displayname', $identity),
						],
					];
				}
			}
			return true;
		}
		return '501 Not Implemented';
	}

	/**
	 * Handle get request for an applications entry
	 *
	 * @param array &$options
	 * @param int $id
	 * @param int $user =null account_id
	 * @return mixed boolean true on success, false on failure or string with http status (eg. '404 Not Found')
	 */
	function get(&$options,$id,$user=null)
	{
		header('Content-Type: application/json');
		try
		{
			$path = rtrim($options['path'], '/');
			if (empty($user))
			{
				$user = $GLOBALS['egw_info']['user']['account_id'];
			}
			else
			{
				$prefix = '/'.Api\Accounts::id2name($user);
				if (str_starts_with($path, $prefix)) $path = substr($path, strlen($prefix));
				if ($user != $GLOBALS['egw_info']['user']['account_id'] &&
					empty($GLOBALS['egw_info']['user']['apps']['admin']))
				{
					throw new \Exception("/mail is NOT available for users other than the one you authenticated!", 403);
				}
			}
			switch ($path)
			{
				case '/mail':
					echo json_encode(iterator_to_array(Api\Mail\Account::identities([], true, 'name', $user)),
						self::JSON_RESPONSE_OPTIONS);
					return '200 Ok';

				case preg_match('#^/mail/(\d+)$#', $path, $matches) === 1:
					$account = self::getMailAccount($user, $matches[1] ?? null);
					$account->getUserData();    // read user data too
					echo json_encode(self::JsMailAccount($account), self::JSON_RESPONSE_OPTIONS);
					return '200 Ok';

				case preg_match('#^/mail(/(\d+))?/vacation$#', $path, $matches) === 1:
					$account = self::getMailAccount($user, $matches[2] ?? null);
					echo json_encode(self::returnVacation(self::getVacation($account->imapServer(), $user)), self::JSON_RESPONSE_OPTIONS);
					return '200 Ok';

				case preg_match('#^/mail/attachments/(([^/]+)--[^/.-]{6,})$#', $path, $matches) === 1:
					if (!file_exists($tmp=$GLOBALS['egw_info']['server']['temp_dir'].'/attach--'.$matches[1]))
					{
						throw new \Exception("Attachment $path NOT found", 404);
					}
					Api\Header\Content::type($matches[2], '', filesize($tmp));
					readfile($tmp);
					exit;

				// JMAP-lite (RFC 8620/8621-shaped, NOT full JMAP) read-only folders/emails - see
				// doc/ai/projects/mail-rest-jmap-lite.md. All 5 proxy the account's real JMAP
				// session (Account::jmapSession()) rather than reshaping a hand-picked subset.
				case preg_match('#^/mail(/(\d+))?/folders$#', $path, $matches) === 1:
					return self::listFolders($user, isset($matches[2]) ? (int)$matches[2] : null);

				case preg_match('#^/mail(/(\d+))?/folders/([^/]+)$#', $path, $matches) === 1:
					return self::getFolder($user, isset($matches[2]) ? (int)$matches[2] : null, $matches[3]);

				case preg_match('#^/mail(/(\d+))?/folders/([^/]+)/emails$#', $path, $matches) === 1:
					return self::listEmails($user, isset($matches[2]) ? (int)$matches[2] : null, $matches[3]);

				case preg_match('#^/mail(/(\d+))?/folders/([^/]+)/emails/([^/]+)$#', $path, $matches) === 1:
					return self::getEmail($user, isset($matches[2]) ? (int)$matches[2] : null, $matches[3], $matches[4]);

				case preg_match('#^/mail(/(\d+))?/folders/([^/]+)/emails/([^/]+)/attachments/([^/]+)$#', $path, $matches) === 1:
					return self::getAttachment($user, isset($matches[2]) ? (int)$matches[2] : null, $matches[3], $matches[4], $matches[5]);
			}
		}
		catch (\Throwable $e) {
			return self::handleException($e);
		}
		return '501 Not Implemented';
	}

	protected static function returnVacation(array $vacation)
	{
		return array_filter([
			'status' => $vacation['status'] ?? 'off',
			'start' => isset($vacation['start_date']) ? Api\DateTime::to($vacation['start_date'], 'Y-m-d') : null,
			'end' => $vacation['end_date'] ? Api\DateTime::to($vacation['end_date'], 'Y-m-d') : null,
			'text' => $vacation['text'] ?? null,
			'modus' => $vacation['modus'] ?? "notice+store",
			'days' => (int)($vacation['days'] ?? 0),
			'addresses' => $vacation['addresses'] ?? null,
			'forwards' => empty($vacation['forwards']) ? [] : preg_split('/, ?/', $vacation['forwards']),
			'script' => $vacation['script'] ?? null,
		]);
	}

	/**
	 * Get mail account specified by identity or users default one
	 *
	 * @param int $user
	 * @param int|null $ident_id
	 * @return Api\Mail\Account
	 * @throws Api\Exception\NotFound
	 */
	protected static function getMailAccount(int $user, ?int $ident_id=null, bool $replace_placeholders=true) : Api\Mail\Account
	{
		if (empty($ident_id))
		{
			return Api\Mail\Account::get_default();
		}
		$identity = Api\Mail\Account::read_identity($ident_id, false, $user);
		return Api\Mail\Account::read($identity['acc_id'],
			!empty($GLOBALS['egw_info']['user']['apps']['admin']) && $user != $GLOBALS['egw_info']['user']['account_id'] ? $user : null,
			$replace_placeholders);
	}

	// --- JMAP-lite (RFC 8620/8621-shaped, NOT full JMAP) read-only folders/emails ----------------
	//
	// See doc/ai/projects/mail-rest-jmap-lite.md for the full design. Design mandate: proxy the
	// account's real JMAP session (Account::jmapSession() - real JMAP-over-HTTP for Stalwart, or
	// the local plain-IMAP JmapShim otherwise) rather than reshape a hand-picked field subset -
	// a `properties` query param is forwarded verbatim to Mailbox/get or Email/get, exactly like
	// real JMAP's own `properties` argument, so this file never needs its own fixed allow-list.
	// The two small transforms below (urlSafeId()/jsonMailbox()/jsonEmail()) are pure REST/HTTP
	// transport plumbing (URL-path-segment safety, response-envelope wrapping) - never a change to
	// the underlying object's fields/semantics.

	/**
	 * Default Email/get properties for the emails-list endpoint, when the client doesn't ask for
	 * specific ones via ?properties= - a reasonable list-view default, not a restriction (a client
	 * can always ask for more, or fewer, via ?properties=).
	 */
	const DEFAULT_EMAIL_LIST_PROPERTIES = ['id', 'mailboxIds', 'keywords', 'size', 'receivedAt',
		'sentAt', 'subject', 'from', 'to', 'cc', 'bcc', 'hasAttachment', 'preview'];

	/**
	 * Added to DEFAULT_EMAIL_LIST_PROPERTIES for the single-email endpoint's default - genuine JMAP
	 * body shape (bodyStructure/bodyValues by partId), not a flattened simplification. `blobId` is
	 * the top-level RFC 8621 §4.1.1 property (the whole raw RFC 5322 message, NOT one of
	 * attachments[]'s own blobIds) - doubles as this API's raw .eml download, since it's just
	 * another blobId for the existing attachment-download endpoint (see getAttachment()'s own
	 * doc/ai/projects/mail-rest-jmap-lite.md note) - no separate "download raw" endpoint needed.
	 */
	const DEFAULT_EMAIL_BODY_PROPERTIES = ['bodyStructure', 'textBody', 'htmlBody', 'attachments', 'bodyValues', 'blobId'];

	/**
	 * Make an opaque JMAP-ish id safe to use as a URL path segment.
	 *
	 * A no-op for an id that already only uses the url-safe base64 alphabet or plain digits (a
	 * real JMAP id - RFC 8620 §1.2 requires the url-safe alphabet - or an IMAP UID), since neither
	 * ever contains a literal '+' or '/' to begin with - only the local JmapShim's own Mailbox
	 * id/parentId (plain base64 of a folder path, predating that RFC check, see Api\Mail\Jmap\
	 * Imap::mailboxNode()) actually needs the substitution. Safe to call UNCONDITIONALLY on
	 * anything this API emits, regardless of backend - see fromUrlSafeId()'s docblock for why the
	 * reverse direction is NOT equally safe to call unconditionally.
	 *
	 * Deliberately NOT fixed at the shim's own source (Mailbox::getMailboxId()/mailboxNode()) -
	 * that id scheme is shared with mail_ui's own row-id encoding across the whole mail app;
	 * changing it there would be a much larger, unrelated refactor. This is purely this REST
	 * API's own transport boundary.
	 *
	 * @param string $id
	 * @return string
	 */
	protected static function urlSafeId(string $id) : string
	{
		return rtrim(strtr($id, '+/', '-_'), '=');
	}

	/**
	 * Reverse of urlSafeId() - NOT safe to call unconditionally, unlike urlSafeId() itself.
	 *
	 * A real JMAP id (RFC 8620 §1.2) is legitimately allowed to contain '-'/'_' as ordinary
	 * characters (they're part of the url-safe alphabet) - urlSafeId() never touches such an id
	 * (nothing to substitute, no '+'/'/' present), so blindly reversing '-'/'_' back to '+'/'/'
	 * here would CORRUPT a real id that happens to contain either character, even though it was
	 * never actually transformed. Only the local JmapShim's own plain-base64 ids are safe to
	 * decode this way (base64_encode()'s alphabet never produces '-'/'_' on its own, so any '-'/
	 * '_' found in one of ITS ids is unambiguously something urlSafeId() introduced by
	 * substituting a '+'/'/').
	 *
	 * Callers MUST only invoke this for a session that is NOT real-JMAP-over-HTTP (i.e. only for
	 * a JmapShim-backed account) - see this method's call sites in getFolder()/listEmails() for
	 * the guard. Real-JMAP folder ids are passed straight through unchanged instead.
	 *
	 * @param string $id
	 * @return string
	 */
	protected static function fromUrlSafeId(string $id) : string
	{
		return strtr($id, '-_', '+/');
	}

	/**
	 * Is $session real JMAP-over-HTTP (Stalwart), as opposed to the local plain-IMAP JmapShim?
	 * Only concrete Api\Jmap\Base subclass Account::jmapSession() ever returns - see
	 * fromUrlSafeId()'s docblock for why this distinction matters for folder-id decoding.
	 *
	 * @param Api\Jmap\Base $session
	 * @return bool
	 */
	protected static function isRealJmapSession(Api\Jmap\Base $session) : bool
	{
		return $session instanceof Api\Mail\Jmap\Http;
	}

	/**
	 * A friendlier, human-readable ALTERNATIVE to the opaque encoded folder id, for the
	 * `{folderId}` REST URL segment: a "::"-joined literal folder path (eg. "INBOX::Sent" for
	 * INBOX/Sent), matching this codebase's own EGroupware-canonical "/"-joined path convention
	 * but substituting "::" for "/" - "/" can't appear literally inside a single URL path segment
	 * without percent-encoding it, which defeats the whole point of this being easy to type/read.
	 *
	 * Purely additive: the real, opaque JMAP id (or the shim's own url-safe-encoded id) keeps
	 * working exactly as before and is still the only thing this doc's own "Response envelope"
	 * section returns - "::" is just an alternative way for a caller to name the SAME resource on
	 * the way in, resolved to the real id before anything else happens (resolveFolderId()/
	 * mailboxIdForEmailGet() below).
	 *
	 * Detection is unambiguous and needs no new route/prefix: a real JMAP id (RFC 8620 §1.2's
	 * url-safe base64 alphabet) and the shim's own urlSafeId()-encoded id can NEVER contain a
	 * colon at all, so the mere PRESENCE of "::" anywhere in the segment already proves it isn't
	 * a valid encoded id of either kind - it's always safe to treat it as this literal-path syntax
	 * instead, no ambiguity with a real id ever possible.
	 *
	 * A single, isolated colon INSIDE one path segment (eg. "INBOX::Foo:Bar" for INBOX/Foo:Bar) is
	 * preserved correctly - only a segment that itself starts or ends with ':' right at a "::"
	 * join is genuinely ambiguous (two different real paths could produce the identical joined
	 * string, eg. ["a:", "b"] and ["a", ":b"] both join to "a:::b") - not specially handled here,
	 * an extremely rare edge case (documented, not fixed): such a folder isn't reachable through
	 * this shorthand, only via its real encoded id.
	 *
	 * @param string $folderIdUrlSafe
	 * @return string|null the canonical "/"-joined path (its first segment normalized to
	 *  uppercase "INBOX" if it case-insensitively matches - IMAP's own INBOX special-casing,
	 *  RFC 3501 §5.1, applies only to that literal top-level mailbox name, never to a
	 *  sub-mailbox that merely happens to be named "inbox" too) - null if $folderIdUrlSafe
	 *  doesn't contain "::" at all (not this syntax; caller falls back to the normal encoded-id
	 *  handling)
	 */
	protected static function parseDoubleColonFolderPath(string $folderIdUrlSafe) : ?string
	{
		if (!str_contains($folderIdUrlSafe, '::'))
		{
			return null;
		}
		$segments = explode('::', $folderIdUrlSafe);
		$segments[0] = self::normalizeInboxCasing($segments[0]);
		return implode('/', $segments);
	}

	/**
	 * IMAP's own INBOX case-insensitivity (RFC 3501 §5.1) - normalizes to this codebase's own
	 * canonical, always-uppercase "INBOX" spelling if $segment case-insensitively matches, otherwise
	 * returns it unchanged. Shared by parseDoubleColonFolderPath() (first segment of a "::"-path)
	 * and resolveBareFolderName() below (a bare, single-segment name has nothing else to normalize).
	 *
	 * @param string $segment
	 * @return string
	 */
	protected static function normalizeInboxCasing(string $segment) : string
	{
		return strcasecmp($segment, 'INBOX') === 0 ? 'INBOX' : $segment;
	}

	/**
	 * Last-resort fallback for getFolder()/listEmails(): a bare $folderIdUrlSafe (no "::" - single
	 * segment, so there was nothing to join in the first place, eg. "INBOX" typed directly rather
	 * than copied from a listing's own `id`) isn't syntactically distinguishable from a real/encoded
	 * id up front - unlike the "::" syntax, which can never collide with an id (see
	 * parseDoubleColonFolderPath()'s own docblock). So this is only ever tried on the FAILURE path,
	 * once the caller's own fetch using resolveFolderId()'s id-decode result already came back empty
	 * - a normal request that already passes back a real, previously-returned id never reaches this
	 * (found live 2026-09-11: ralf hand-typed `GET .../folders/INBOX`, got a 404 - a single top-level
	 * name has no "/" to justify requiring the "::" syntax at all).
	 *
	 * @param Api\Jmap\Base $session
	 * @param string $folderIdUrlSafe
	 * @return string|null null if this isn't a real folder name either - caller should 404
	 */
	protected static function resolveBareFolderName(Api\Jmap\Base $session, string $folderIdUrlSafe) : ?string
	{
		return $session->mailbox->getMailboxId(self::normalizeInboxCasing($folderIdUrlSafe));
	}

	/**
	 * The inverse of parseDoubleColonFolderPath(): the canonical "/"-joined path (eg. "INBOX/Sent",
	 * as already carried by every Mailbox object's own `path` field) turned into the same
	 * "::"-joined REST URL form (eg. "INBOX::Sent") a caller could use to address this same folder.
	 * Used for listFolders()'s own response-envelope resource-path keys, so a listing response is
	 * consistently human-readable, not just each object's own `path` field (ralf, 2026-09-11: "if
	 * the folder listing then you use the :: notation as it's attribute name, for consistency").
	 *
	 * @param string $path
	 * @return string
	 */
	protected static function pathToDoubleColonFolderId(string $path) : string
	{
		return str_replace('/', '::', $path);
	}

	/**
	 * Resolve $folderIdUrlSafe (either syntax - see parseDoubleColonFolderPath()'s own docblock)
	 * into the real folder/mailbox id Type::get()/query() expect - used by getFolder()/
	 * listEmails(), which (unlike getEmail()/getAttachment()) need the id itself, not just a
	 * shim-only $mailboxId argument (see mailboxIdForEmailGet() below).
	 *
	 * @param Api\Jmap\Base $session
	 * @param string $folderIdUrlSafe
	 * @return string
	 * @throws \Exception (404) a "::"-path was given but doesn't resolve to a real folder
	 */
	protected static function resolveFolderId(Api\Jmap\Base $session, string $folderIdUrlSafe) : string
	{
		if (($path = self::parseDoubleColonFolderPath($folderIdUrlSafe)) !== null)
		{
			$folderId = $session->mailbox->getMailboxId($path);
			if ($folderId === null)
			{
				throw new \Exception("Folder '$path' not found", 404);
			}
			return $folderId;
		}
		// only the local JmapShim's own ids need decoding back - see fromUrlSafeId()'s docblock
		return self::isRealJmapSession($session) ? $folderIdUrlSafe : self::fromUrlSafeId($folderIdUrlSafe);
	}

	/**
	 * The $mailboxId argument to pass to Type::get() (see its own docblock) for a standalone
	 * Email/get (getEmail()/getAttachment() - both have no preceding Email/query in the same
	 * request, unlike listEmails()) - null for a real JMAP server (which would reject an argument
	 * Email/get does not define, RFC 8620 §3.6.1), the folder id decoded back for the shim
	 * (which needs it to know which mailbox to FETCH from at all).
	 *
	 * Shared by getEmail()/getAttachment() (found live 2026-09-10 in both at once - the exact same
	 * gap, just discovered via two different symptoms: a hard 500, and attachment downloads
	 * silently losing their filename/type).
	 *
	 * @param Api\Jmap\Base $session
	 * @param string $folderIdUrlSafe
	 * @return string|null
	 */
	protected static function mailboxIdForEmailGet(Api\Jmap\Base $session, string $folderIdUrlSafe) : ?string
	{
		if (self::isRealJmapSession($session))
		{
			return null;
		}
		if (($path = self::parseDoubleColonFolderPath($folderIdUrlSafe)) !== null)
		{
			return $session->mailbox->getMailboxId($path);
		}
		return self::fromUrlSafeId($folderIdUrlSafe);
	}

	/**
	 * ?properties=a,b,c query param, forwarded verbatim to Mailbox/get or Email/get - null (not an
	 * empty array) when absent, so the session's own "null = server default" behaviour applies.
	 *
	 * @return string[]|null
	 */
	protected static function queryProperties() : ?array
	{
		return isset($_GET['properties']) && $_GET['properties'] !== '' ?
			array_map('trim', explode(',', $_GET['properties'])) : null;
	}

	/**
	 * ?sort=<property>[ asc|desc] query param -> a JMAP Comparator array, default "receivedAt desc"
	 *
	 * @return array
	 */
	protected static function queryEmailSort() : array
	{
		[$property, $order] = array_pad(preg_split('/\s+/', trim((string)($_GET['sort'] ?? 'receivedAt desc'))), 2, 'desc');
		return [['property' => $property, 'isAscending' => strtolower($order) !== 'desc']];
	}

	/**
	 * ?filter[before]=...&filter[hasAttachment]=... query params -> JMAP Email FilterCondition
	 * leaf conditions, using exactly RFC 8621 §4.4.1's own property names (not this file's own
	 * naming) - passed straight through, no reinterpretation. Note: the local JmapShim's own
	 * filterToQuery()/applyCondition() (Api\Mail\Jmap\Imap.php) doesn't implement "hasAttachment"
	 * at all yet (silently ignored, not an error) - a known, documented backend-parity gap (see
	 * doc/ai/projects/mail-rest-jmap-lite.md), not something to special-case or reject here.
	 *
	 * @return array<string,mixed>
	 * @throws \Exception (400) on an unsupported filter attribute
	 */
	protected static function queryEmailFilter() : array
	{
		static $allowed = ['before', 'after', 'hasAttachment', 'text', 'hasKeyword', 'notKeyword'];
		$filter = [];
		foreach ((array)($_GET['filter'] ?? []) as $key => $value)
		{
			if (!in_array($key, $allowed, true))
			{
				throw new \Exception("Invalid filter attribute '$key', must be one of: '".implode("', '", $allowed)."'", 400);
			}
			$filter[$key] = $key === 'hasAttachment' ? filter_var($value, FILTER_VALIDATE_BOOLEAN) : $value;
		}
		return $filter;
	}

	/**
	 * Re-key a Mailbox object's id-shaped fields through urlSafeId() - the only transform applied,
	 * every other field is proxied exactly as the session returned it.
	 *
	 * @param array $mailbox
	 * @return array
	 */
	protected static function jsonMailbox(array $mailbox) : array
	{
		if (isset($mailbox['id'])) $mailbox['id'] = self::urlSafeId($mailbox['id']);
		if (isset($mailbox['parentId'])) $mailbox['parentId'] = self::urlSafeId($mailbox['parentId']);
		return $mailbox;
	}

	/**
	 * Re-key an Email object's id-shaped fields (mailboxIds keys) through urlSafeId() - same as
	 * jsonMailbox(), the only transform applied.
	 *
	 * @param array $email
	 * @return array
	 */
	protected static function jsonEmail(array $email) : array
	{
		if (isset($email['mailboxIds']) && is_array($email['mailboxIds']))
		{
			$email['mailboxIds'] = array_combine(
				array_map([self::class, 'urlSafeId'], array_keys($email['mailboxIds'])),
				array_values($email['mailboxIds']));
		}
		return $email;
	}

	/**
	 * Recursively walk the account's folder tree (Mailbox/query filtered by parentId, one level at
	 * a time - both backends' Mailbox/query only support exactly this shape, see
	 * doc/ai/projects/mail-rest-jmap-lite.md's "Backend parity" section) into one flat list - a
	 * REST client asking for "the folders" naturally wants all of them, unlike the interactive
	 * tree UI's own lazy per-level loading (a performance optimization this simpler REST consumer
	 * doesn't need).
	 *
	 * filter:{parentId: null} (top level) vs filter:{parentId: <id>} (children of <id>) always
	 * uses an explicit key, even at the top (never omitted) - an OMITTED parentId means something
	 * different per backend: real JMAP then applies no parentId constraint at all (=> every
	 * mailbox, defeating "list only this level"), while the shim's own mailboxQuery() specifically
	 * treats an absent/empty parentId as "top level only". Explicitly passing null keeps both
	 * backends aligned on "top level", matching RFC 8621's own null-parentId-means-top-level
	 * Mailbox semantics - needs a live check against real Stalwart (not just spec reading) before
	 * this is considered fully verified there, same as other JMAP-native features in this codebase.
	 *
	 * isSubscribed is only ever added to the filter (as literal `true`) when actually wanted -
	 * never sent as `false`: for real JMAP that's an equality filter ("only UNSUBSCRIBED"), the
	 * opposite of "no subscription constraint at all" a false $subscribedOnly here means.
	 *
	 * @param Api\Jmap\Base $session
	 * @param bool $subscribedOnly
	 * @param string[]|null $properties forwarded to Mailbox/get
	 * @return array[] flat list of Mailbox objects (still with the session's own raw ids - not
	 *  yet run through jsonMailbox()), each with a new REST-only `path` field (the
	 *  "/"-joined canonical folder path, eg. "INBOX/Sent" - see parseDoubleColonFolderPath()'s own
	 *  docblock for the URL syntax this is the display-side counterpart of) computed for free
	 *  during this method's own top-down walk (each level already knows its own parent's path),
	 *  no extra JMAP/IMAP round trip needed on either backend
	 */
	protected static function listAllFolders(Api\Jmap\Base $session, bool $subscribedOnly, ?array $properties) : array
	{
		// 'name' is needed internally to build each entry's own 'path' below - like 'id' (RFC 8620
		// §5.1: always returned regardless of the requested properties), 'name'/'path' are treated
		// as always-present structural companions too, not filtered by the caller's own
		// ?properties=, matching how the shim's own Imap\Mailbox::get() already ignores the
		// properties filter for Mailbox objects entirely
		$fetchProperties = $properties === null ? null : array_unique(array_merge($properties, ['name']));

		$folders = [];
		$walk = function(?string $parentId, string $parentPath) use (&$walk, &$folders, $session, $subscribedOnly, $fetchProperties)
		{
			$filter = ['parentId' => $parentId];
			if ($subscribedOnly)
			{
				$filter['isSubscribed'] = true;
			}
			$ids = $session->mailbox->query($filter)['ids'] ?? [];
			if (!$ids)
			{
				return;
			}
			foreach ($session->mailbox->get($ids, $fetchProperties)['list'] ?? [] as $mailbox)
			{
				$path = $parentPath === '' ? $mailbox['name'] : $parentPath.'/'.$mailbox['name'];
				$mailbox['path'] = $path;
				$folders[] = $mailbox;
				$walk($mailbox['id'], $path);
			}
		};
		$walk(null, '');
		return $folders;
	}

	/**
	 * GET /mail[/<id>]/folders
	 *
	 * @param int $user
	 * @param int|null $ident_id
	 * @return string HTTP status - NOT true: HTTP_WebDAV_Server only keeps the Content-Type
	 *  this method's own caller already set (application/json, see get()'s own header() call)
	 *  when the GET handler returns a status string; returning true makes it default the whole
	 *  response body to application/octet-stream instead (found live 2026-09-10: a real REST
	 *  client, n8n's own HTTP node, filed the JSON response as a binary file download and read no
	 *  mail at all - see HTTP_WebDAV_Server::http_GET()/CalDAV.php's own "as not mimetype is set"
	 *  precedent for the exact same idiom).
	 */
	protected static function listFolders(int $user, ?int $ident_id) : string
	{
		$account = self::getMailAccount($user, $ident_id);
		$session = $account->jmapSession();
		$subscribedOnly = !isset($_GET['subscribedOnly']) || filter_var($_GET['subscribedOnly'], FILTER_VALIDATE_BOOLEAN);

		$prefix = '/mail'.($ident_id ? '/'.$ident_id : '').'/folders/';
		$responses = [];
		foreach (self::listAllFolders($session, $subscribedOnly, self::queryProperties()) as $mailbox)
		{
			$mailbox = self::jsonMailbox($mailbox);
			$responses[$prefix.self::pathToDoubleColonFolderId($mailbox['path'])] = $mailbox;
		}
		echo json_encode(['responses' => $responses], self::JSON_RESPONSE_OPTIONS);
		return '200 Ok';
	}

	/**
	 * GET /mail[/<id>]/folders/<folderId>
	 *
	 * @param int $user
	 * @param int|null $ident_id
	 * @param string $folderIdUrlSafe the real encoded folder id, a "::"-joined literal path (eg.
	 *  "INBOX::Sent" - see parseDoubleColonFolderPath()'s own docblock), or a bare single-segment
	 *  literal name (eg. "INBOX" - see resolveBareFolderName()'s own docblock for why this needs a
	 *  failure-path retry rather than being detected up front like the "::" syntax)
	 * @return string HTTP status - NOT true, see listFolders()'s own docblock for why
	 * @throws \Exception (404) if not found
	 */
	protected static function getFolder(int $user, ?int $ident_id, string $folderIdUrlSafe) : string
	{
		$account = self::getMailAccount($user, $ident_id);
		$session = $account->jmapSession();
		$folderId = self::resolveFolderId($session, $folderIdUrlSafe);

		$list = $session->mailbox->get([$folderId], self::queryProperties())['list'] ?? [];
		// bare name fallback (eg. "INBOX", no "::") - see resolveBareFolderName()'s own docblock
		if (!$list && self::parseDoubleColonFolderPath($folderIdUrlSafe) === null &&
			($folderId = self::resolveBareFolderName($session, $folderIdUrlSafe)) !== null)
		{
			$list = $session->mailbox->get([$folderId], self::queryProperties())['list'] ?? [];
		}
		if (!$list)
		{
			throw new \Exception("Folder '$folderIdUrlSafe' not found", 404);
		}
		// see listAllFolders()'s own docblock for what this REST-only field is - a single-folder
		// fetch has no cheap way to derive it from an already-known parent path (unlike the
		// recursive listing there), so this is folderId2path()'s own one real lookup per call
		$mailbox = $list[0] + ['path' => $session->mailbox->folderId2path($folderId)];
		echo json_encode(self::jsonMailbox($mailbox), self::JSON_RESPONSE_OPTIONS);
		return '200 Ok';
	}

	/**
	 * GET /mail[/<id>]/folders/<folderId>/emails
	 *
	 * @param int $user
	 * @param int|null $ident_id
	 * @param string $folderIdUrlSafe the real encoded folder id, a "::"-joined literal path (eg.
	 *  "INBOX::Sent"), or a bare single-segment literal name (eg. "INBOX") - see getFolder()'s own
	 *  docblock
	 * @return string HTTP status - NOT true, see listFolders()'s own docblock for why
	 */
	protected static function listEmails(int $user, ?int $ident_id, string $folderIdUrlSafe) : string
	{
		$account = self::getMailAccount($user, $ident_id);
		$session = $account->jmapSession();
		$folderId = self::resolveFolderId($session, $folderIdUrlSafe);

		$filter = ['inMailbox' => $folderId]+self::queryEmailFilter();
		$position = max(0, (int)($_GET['position'] ?? 0));
		$limit = min(200, max(1, (int)($_GET['limit'] ?? 50)));

		try
		{
			$query = $session->email->query($filter, self::queryEmailSort(), $position, $limit, true);
		}
		catch (\Throwable $e)
		{
			// bare name fallback (eg. "INBOX", no "::") - an invalid inMailbox id is rejected deep
			// inside Email/query itself (a real JMAP server's own 400, or the shim's IMAP SELECT
			// failure), not just an empty result - see resolveBareFolderName()'s own docblock. Only
			// retried once; if this ALSO fails (or isn't applicable), the original error is the
			// more meaningful one to surface, not this fallback attempt's own failure.
			if (self::parseDoubleColonFolderPath($folderIdUrlSafe) !== null ||
				($folderId = self::resolveBareFolderName($session, $folderIdUrlSafe)) === null)
			{
				throw $e;
			}
			$filter = ['inMailbox' => $folderId]+self::queryEmailFilter();
			$query = $session->email->query($filter, self::queryEmailSort(), $position, $limit, true);
		}
		$ids = $query['ids'] ?? [];

		$byId = [];
		if ($ids)
		{
			foreach ($session->email->get($ids, self::queryProperties() ?? self::DEFAULT_EMAIL_LIST_PROPERTIES)['list'] ?? [] as $email)
			{
				$byId[$email['id']] = $email;
			}
		}
		// rebuild in Email/query's own order - Email/get responses are not guaranteed to preserve
		// the requested ids' order (see Api\Mail\Jmap\Imap::emailGet()'s own docblock on why it
		// has to do the same reordering internally for the shim's IMAP FETCH responses)
		$prefix = '/mail'.($ident_id ? '/'.$ident_id : '').'/folders/'.$folderIdUrlSafe.'/emails/';
		$responses = [];
		foreach ($ids as $id)
		{
			if (isset($byId[$id]))
			{
				$responses[$prefix.$id] = self::jsonEmail($byId[$id]);
			}
		}
		echo json_encode([
			'responses' => $responses,
			'position' => $query['position'] ?? $position,
			'total' => $query['total'] ?? count($ids),
		], self::JSON_RESPONSE_OPTIONS);
		return '200 Ok';
	}

	/**
	 * GET /mail[/<id>]/folders/<folderId>/emails/<emailId>
	 *
	 * @param int $user
	 * @param int|null $ident_id
	 * @param string $folderIdUrlSafe the mailbox to read the email from - the real encoded folder
	 *  id, OR a "::"-joined literal path (eg. "INBOX::Sent", see parseDoubleColonFolderPath()'s
	 *  own docblock). A real JMAP server identifies an Email by id alone, but the JmapShim has to
	 *  FETCH it out of some IMAP mailbox: Api\Mail\Jmap\Imap::emailGet() otherwise relies on the
	 *  context a preceding Email/query left behind, and this endpoint has no listing in front of
	 *  it, so it must pass the mailbox explicitly or the shim throws (found live 2026-09-10 - see
	 *  Api\Jmap\Type::get()'s own $mailboxId docblock). Only for shim sessions - RFC 8620 §3.6.1
	 *  lets a real JMAP server reject an argument Email/get does not define.
	 * @param string $emailId
	 * @return string HTTP status - NOT true, see listFolders()'s own docblock for why
	 * @throws \Exception (404) if not found
	 */
	protected static function getEmail(int $user, ?int $ident_id, string $folderIdUrlSafe, string $emailId) : string
	{
		$account = self::getMailAccount($user, $ident_id);
		$session = $account->jmapSession();
		$mailboxId = self::mailboxIdForEmailGet($session, $folderIdUrlSafe);

		$properties = self::queryProperties() ?? array_merge(self::DEFAULT_EMAIL_LIST_PROPERTIES, self::DEFAULT_EMAIL_BODY_PROPERTIES);
		try
		{
			$list = $session->email->get([$emailId], $properties, true, $mailboxId)['list'] ?? [];
		}
		catch (\Throwable $e)
		{
			// bare name fallback (eg. "INBOX", no "::") - see resolveBareFolderName()'s own
			// docblock and listEmails()'s identical pattern above; $mailboxId===null means this is
			// a real JMAP session (isRealJmapSession() short-circuits mailboxIdForEmailGet() there),
			// which never needed a mailboxId at all - nothing to retry
			if ($mailboxId === null || self::parseDoubleColonFolderPath($folderIdUrlSafe) !== null ||
				($mailboxId = self::resolveBareFolderName($session, $folderIdUrlSafe)) === null)
			{
				throw $e;
			}
			$list = $session->email->get([$emailId], $properties, true, $mailboxId)['list'] ?? [];
		}
		if (!$list)
		{
			throw new \Exception("Email '$emailId' not found", 404);
		}
		echo json_encode(self::jsonEmail($list[0]), self::JSON_RESPONSE_OPTIONS);
		return '200 Ok';
	}

	/**
	 * GET /mail[/<id>]/folders/<folderId>/emails/<emailId>/attachments/<blobId>
	 *
	 * $folderIdUrlSafe is unused - AttachmentJmap::fetchBlobBytes() only needs the account and the
	 * (already self-describing/opaque, already url-safe) blobId itself; it's in the URL purely
	 * for discoverability/consistency with how the client found the blobId (in an email's own
	 * attachments[] list, or the email's own top-level `blobId` for a raw .eml download - see
	 * DEFAULT_EMAIL_BODY_PROPERTIES's own note; no separate "download raw" endpoint exists or is
	 * needed, this same endpoint already covers it). $emailId IS used, but only to look up that
	 * same blob's real name/type for the Content-Disposition/Content-Type headers - a proper
	 * download filename, not fetching the actual bytes a second, different way.
	 *
	 * @param int $user
	 * @param int|null $ident_id
	 * @param string $folderIdUrlSafe the mailbox $emailId lives in - see getEmail()'s own
	 *  docblock for why the shim needs this and a real JMAP server must never receive it
	 * @param string $emailId
	 * @param string $blobId
	 * @return string HTTP status - NOT true, see listFolders()'s own docblock for why
	 * @throws \Exception (404) if not found
	 */
	protected static function getAttachment(int $user, ?int $ident_id, string $folderIdUrlSafe, string $emailId, string $blobId) : string
	{
		$account = self::getMailAccount($user, $ident_id);
		$bytes = Ui\AttachmentJmap::fetchBlobBytes((string)$account->acc_id, $blobId);
		if ($bytes === null)
		{
			throw new \Exception("Attachment '$blobId' not found", 404);
		}
		$name = $blobId;
		$type = '';
		try {
			$session = $account->jmapSession();
			// same mailbox-context requirement as getEmail() - without it the shim throws and the
			// catch below silently degrades every download to blobId/octet-stream (found live
			// 2026-09-10 alongside the identical getEmail() gap)
			$mailboxId = self::mailboxIdForEmailGet($session, $folderIdUrlSafe);
			try
			{
				$email = $session->email->get([$emailId], ['attachments', 'blobId', 'subject'], false, $mailboxId)['list'][0] ?? null;
			}
			catch (\Throwable $e)
			{
				// bare name fallback (eg. "INBOX", no "::") - see getEmail()'s identical pattern
				if ($mailboxId === null || self::parseDoubleColonFolderPath($folderIdUrlSafe) !== null ||
					($mailboxId = self::resolveBareFolderName($session, $folderIdUrlSafe)) === null)
				{
					throw $e;
				}
				$email = $session->email->get([$emailId], ['attachments', 'blobId', 'subject'], false, $mailboxId)['list'][0] ?? null;
			}
			if (($email['blobId'] ?? null) === $blobId)
			{
				// the email's own top-level blobId (RFC 8621 §4.1.1) - the whole raw message, not
				// one of attachments[]'s own parts
				$name = ($email['subject'] ?? 'message').'.eml';
				$type = 'message/rfc822';
			}
			else foreach ((array)($email['attachments'] ?? []) as $attachment)
			{
				if (($attachment['blobId'] ?? null) === $blobId)
				{
					$name = $attachment['name'] ?? $name;
					$type = $attachment['type'] ?? '';
					break;
				}
			}
		}
		catch (\Throwable $e) {
			unset($e);	// fall back to the generic name/type below rather than failing the download
		}
		Api\Header\Content::type($name, $type, strlen($bytes));
		echo $bytes;
		return '200 Ok';
	}

	const PASSWORD_DUMMY = '********';
	static function JsMailAccount(Api\Mail\Account $account) : array
	{
		$data = array_combine(
			array_map(fn($name) => lcfirst(implode('', array_map('ucfirst', explode('_', $name)))), array_keys($account->params)),
			array_map(fn($value, $key) => str_ends_with($key, '_password') ? self::PASSWORD_DUMMY :
				($value === (string)(int)$value ? (int)$value : $value), $account->params, array_keys($account->params)));

		$data['accFurtherIdentities'] = (bool)$data['AccFurtherIdentities'];

		// unset some internal user-data
		unset($data['stalwart'], $data['mailingLists'], $data['uid']);
		if (isset($data['accountStatus']))
		{
			$data['accountStatus'] = !empty($data['accountStatus']);
		}
		ksort($data);

		return $data;
	}

	static function parseJsMailAccount(array $data) : array
	{
		$account = array_combine(
			array_map(fn($key) => !preg_match('/^(mail|quota|accountStatus|delivery)/', $key) && preg_match_all('/[A-Z]*[a-z]+/', $key, $matches) ?
				implode('_', array_map(fn($name) => strtolower($name), $matches[0])) : $key, array_keys($data)),
			array_values($data));

		// remove "xxxxxxxx" used to replace passwords
		$data = array_filter($account,
			fn($value, $key) => !str_ends_with($key, '_password') || $value !== self::PASSWORD_DUMMY,
			ARRAY_FILTER_USE_BOTH);

		foreach($data as $name => &$value)
		{
			switch ($name)
			{
				case 'acc_further_identities':
				case 'acc_user_editable':
				case 'acc_user_forward':
					is_bool($value) || throw new \InvalidArgumentException("AccFurtherIdentities must be true or false, $value given!");
					break;
				case str_ends_with($name, '_port'):
					if ($value !== '')
					{
						$value = preg_match('/^[1-9]+[0-9]*$/', $value) && 0 < $value && $value <= 65536 ? (int)$value :
							throw new \InvalidArgumentException("$name must be either be empty of a number between 0 and 65536, $value given!");
					}
					break;
				case 'quotaLimit':
					if ($value !== '')
					{
						$value = preg_match('/^[1-9]+[0-9]*$/', $value) && 0 < $value ? (int)$value :
							throw new \InvalidArgumentException("$name must be either be empty of a number between bigger than 0, $value given!");
					}
					break;
				case 'mailLocalAddress':
				case 'mailAlternateAddress':
				case 'mailForwardAddress':
					$value = self::parseEmail($value, $name === 'mailLocalAddress');
					break;
				case str_ends_with($name, '_id'):
				case 'quotaUsed':   // readonly
					// do NOT allow changing them that way
					unset($account[$name]);
					break;
				case 'acc_imap_login_type':
					in_array($value, \admin_mail::$login_types, true) ||
						throw new \InvalidArgumentException("Invalid login type value '$value'! Allowed values are '".implode("', '", \admin_mail::$login_types)."'");
					break;
			}
		}
		return $data;
	}

	/**
	 * Parse / validate a single or multiple email addresses
	 *
	 * @param string|string[] $values
	 * @param bool $multiple
	 * @return string|string[]
	 */
	public static function parseEmail($values, bool $multiple=true)
	{
		if (!$multiple && is_array($values))
		{
			throw new \InvalidArgumentException('Only a single email address allowed!');
		}
		foreach((array)$values as $email)
		{
			if (!preg_match(Api\Etemplate\Widget\Url::EMAIL_PREG, $email) || substr($email, -1) === '>')
			{
				throw new \InvalidArgumentException("Invalid email address '$email'!");
			}
		}
		return $values;
	}

	/**
	 * Handle exception by returning an appropriate HTTP status and JSON content with an error message
	 *
	 * The exception's own code is only ever a real HTTP status for SOME exceptions (eg. plain
	 * `\Exception($msg, 404)`, this file's own convention for REST-facing errors) - others use
	 * non-HTTP internal codes (eg. `Api\Exception\NotFound`'s default of `2`). Both the JSON body's
	 * `error` field and the actual returned HTTP status line must agree on the SAME, already-clamped
	 * value - otherwise a client sees eg. HTTP 500 but `"error": 2` in the body, which doesn't match
	 * any status this API actually sends.
	 *
	 * @param \Throwable $e
	 * @return string
	 */
	protected function handleException(\Throwable $e) : string
	{
		_egw_log_exception($e);
		$code = $e->getCode() ?: 500;
		$httpCode = 400 <= $code && $code < 600 ? $code : 500;
		header('Content-Type: application/json');
		echo json_encode([
				'error'   => $httpCode,
				'message' => $e->getMessage(),
				'details' => $e->details ?? null,
				'script'  => $e->script ?? null,
			]+(empty($GLOBALS['egw_info']['server']['exception_show_trace']) ? [] : [
				'trace' => array_map(static function($trace)
				{
					$trace['file'] = str_replace(EGW_SERVER_ROOT.'/', '', $trace['file']);
					return $trace;
				}, $e->getTrace())
			]), self::JSON_RESPONSE_OPTIONS);
		return $httpCode.' '.$e->getMessage();
	}

	/**
	 * Handle put or patch request for an applications entry
	 *
	 * Currently only patching of mail accounts is implemented
	 *
	 * @param array &$options
	 * @param int $id
	 * @param int $user =null account_id of owner, default null
	 * @param string $prefix =null user prefix from path (eg. /ralf from /ralf/addressbook)
	 * @param string $method='PUT' also called for POST and PATCH
	 * @param ?string $content_type=null
	 * @return mixed boolean true on success, false on failure or string with http status (eg. '404 Not Found')
	 */
	function put(&$options, $id, $user=null, $prefix=null, string $method='PUT', ?string $content_type=null)
	{
		if ($method !== 'PATCH' || !Api\CalDAV::isJSON() ||
			($id = (int)$id) <= 0)
		{
			return '501 Not Implemented';
		}
		try {
			// replace_placeholders=false: this account object becomes the merge-base for a partial
			// PATCH, written straight back afterwards - if ident_realname/ident_email are empty here
			// they must STAY empty when merged/written, not get silently replaced with the
			// patching user's own name/email (which would permanently corrupt a shared/multi-user
			// identity's per-viewer placeholder display for everyone else)
			$account = $this->getMailAccount($user, $id, false);
		}
		catch (Api\Exception\NotFound $e) {
			unset($e);
			return '404 Not Found';
		}
		if (empty($GLOBALS['egw_info']['user']['apps']['admin']) &&
			!($account->acc_user_editable && array_intersect($account->account_id, [0, $GLOBALS['egw_info']['user']['account_id']]) &&
				$user == $GLOBALS['egw_info']['user']['account_id']))
		{
			return '403 Not Authorized';
		}
		try {
			$account->getUserData();
			$data = self::parseJsMailAccount(json_decode($options['content'], true, 2, JSON_THROW_ON_ERROR));
			$data = Api\CalDAV\JsBase::patch($data, $account->params);

			// save user data only, if used as an admin
			if (empty($GLOBALS['egw_info']['user']['apps']['admin']))
			{
				$user = null;
			}
			Api\Mail\Account::write($data, $user);

			return '204 No Content';
		}
		catch (\Throwable $e) {
			return self::handleException($e);
		}
	}

	/**
	 * Handle get request for an applications entry
	 *
	 * @param array &$options
	 * @param int $id
	 * @param int $user account_id of collection owner
	 * @return mixed boolean true on success, false on failure or string with http status (eg. '404 Not Found')
	 */
	function delete(&$options,$id,$user)
	{
		return '501 Not Implemented';
	}

	/**
	 * Read an entry
	 *
	 * @param string|int $id
	 * @param string $path =null implementation can use it, used in call from _common_get_put_delete
	 * @return array|boolean array with entry, false if no read rights, null if $id does not exist
	 */
	function read($id /*,$path=null*/)
	{
		return '501 Not Implemented';
	}

	/**
	 * Check if user has the necessary rights on an entry
	 *
	 * @param int $acl Api\Acl::READ, Api\Acl::EDIT or Api\Acl::DELETE
	 * @param array|int $entry entry-array or id
	 * @return boolean null if entry does not exist, false if no access, true if access permitted
	 */
	function check_access($acl,$entry)
	{
		return true;
	}
}