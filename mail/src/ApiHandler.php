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
		$path = $options['path'];
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
						$params['reply_id'] = \mail_ui::generateRowID($acc_id, $folder, $uid, true);
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
				// check if the mail-account requires a user-context / password and then just send the mail with an smtp-only account NOT saving to Sent folder
				if (empty($mail_account->acc_imap_password) || $mail_account->acc_smtp_auth_session && empty($mail_account->acc_smtp_password))
				{
					$acc_id = Api\Mail\Account::get_default(true, true, true, false);
					$compose = new \mail_compose($acc_id);
					$compose->mailPreferences['sendOptions'] = 'send_only';
					$warning = 'Mail NOT saved to Sent folder, as no user password';
				}
				else
				{
					$compose = new \mail_compose($acc_id);
				}
				$preset = array_filter([
					'mailaccount' => $acc_id,
					'mailidentity' => $ident_id,
					'identity' => null,
					'add_signature' => true,    // add signature in send, independent what preference says
				]+$preset);
				if ($compose->send($preset, $acc_id))
				{
					echo json_encode(array_filter([
						'status' => 200,
						'warning' => $warning ?? null,
						'message' => 'Mail successful sent',
						//'data' => $preset,
					]), self::JSON_RESPONSE_OPTIONS);
					return true;
				}
				throw new \Exception($compose->error_info);
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
	protected static function getVacation(Api\Mail\Imap $imap, int $user=null)
	{
		if ($GLOBALS['egw']->session->token_auth)
		{
			return $imap->getVacationUser($user ?: $GLOBALS['egw_info']['user']['account_id']);
		}
		$sieve = new Api\Mail\Sieve($imap);
		return $sieve->getVacation()+['script' => $sieve->script];
	}

	/**
	 * Update vacation message/handling with JSON data given in $content
	 *
	 * @param int $user
	 * @param array $content
	 * @param int|null $identity
	 * @return bool
	 * @throws Api\Exception\AssertionFailed
	 * @throws Api\Exception\NotFound
	 */
	protected static function updateVacation(int $user, string $content, int $identity=null)
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
			$sieve = new Api\Mail\Sieve($account->imapServer());
			$sieve->setVacation($vacation, null, $vacation_rule, true);
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
	protected static function viewEml(int $user, $content, int $acc_id=null)
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
		if (!(is_resource($content) ?
			stream_copy_to_stream($content, $fp = fopen($eml, 'w')) :
			file_put_contents($eml, $content)))
		{
			throw new \Exception('Error storing attachment');
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
		$push->call('egw.open', \mail_ui::generateRowID($acc_id, $folder, $message_uid, true),
			'mail', 'view', ['mode' => 'display'], '_blank', 'mail');

		// respond with success message
		echo json_encode([
			'status' => 200,
			'message' => 'Request to open view window sent',
		], self::JSON_RESPONSE_OPTIONS);

		return true;
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
	 * Convert an attachment name into an upload array for mail_compose::compose
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
	 * @param bool $compose
	 * @return array
	 * @throws Api\Exception
	 */
	protected static function prepareAttachments(array $attachments, string $attachmentType=null, string $expiration=null, string $password=null, bool $compose=true)
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
						'props' => ['data' => ['val' => $identity]],
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
				if ($user  != $GLOBALS['egw_info']['user']['account_id'])
				{
					throw new \Exception("/mail is NOT available for users other than the one you authenticated!", 403);
				}
			}
			switch ($path)
			{
				case '/mail':
					echo json_encode(iterator_to_array(Api\Mail\Account::identities([], true, 'name', $user)),
						self::JSON_RESPONSE_OPTIONS);
					return true;

				case preg_match('#^/mail(/(\d+))?/vacation$#', $path, $matches) === 1:
					$account = self::getMailAccount($user, $matches[2] ?? null);
					echo json_encode(self::returnVacation(self::getVacation($account->imapServer(), $user)), self::JSON_RESPONSE_OPTIONS);
					return true;

				case preg_match('#^/mail/attachments/(([^/]+)--[^/.-]{6,})$#', $path, $matches) === 1:
					if (!file_exists($tmp=$GLOBALS['egw_info']['server']['temp_dir'].'/attach--'.$matches[1]))
					{
						throw new \Exception("Attachment $path NOT found", 404);
					}
					Api\Header\Content::type($matches[2], '', filesize($tmp));
					readfile($tmp);
					exit;
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
	protected static function getMailAccount(int $user, int $ident_id=null) : Api\Mail\Account
	{
		if (empty($ident_id))
		{
			return Api\Mail\Account::get_default();
		}
		$identity = Api\Mail\Account::read_identity($ident_id, false, $user);
		return Api\Mail\Account::read($identity['acc_id']);
	}

	/**
	 * Handle exception by returning an appropriate HTTP status and JSON content with an error message
	 *
	 * @param \Throwable $e
	 * @return string
	 */
	protected function handleException(\Throwable $e) : string
	{
		_egw_log_exception($e);
		header('Content-Type: application/json');
		echo json_encode([
				'error'   => $code = $e->getCode() ?: 500,
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
		return (400 <= $code && $code < 600 ? $code : 500).' '.$e->getMessage();
	}

	/**
	 * Handle get request for an applications entry
	 *
	 * @param array &$options
	 * @param int $id
	 * @param int $user =null account_id of owner, default null
	 * @return mixed boolean true on success, false on failure or string with http status (eg. '404 Not Found')
	 */
	function put(&$options,$id,$user=null)
	{
		return '501 Not Implemented';
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