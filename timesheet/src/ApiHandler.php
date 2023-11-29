<?php
/**
 * EGroupware Timesheet: REST API
 *
 * @link https://www.egroupware.org
 * @package mail
 * @author Ralf Becker <rb@egroupware.org>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Timesheet;

use EGroupware\Api;

/**
 * REST API for Timesheet
 */
class ApiHandler extends Api\CalDAV\Handler
{
	/**
	 * @var \timesheet_bo
	 */
	protected \timesheet_bo $bo;

	/**
	 * Extension to append to url/path
	 *
	 * @var string
	 */
	static $path_extension = '';

	/**
	 * Constructor
	 *
	 * @param string $app 'calendar', 'addressbook' or 'infolog'
	 * @param Api\CalDAV $caldav calling class
	 */
	function __construct($app, Api\CalDAV $caldav)
	{
		parent::__construct($app, $caldav);
		self::$path_extension = '';

		$this->bo = new \timesheet_bo();
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
				$ident_id = $matches[2] ?? self::defaultIdentity($user);
				$do_compose = (bool)($matches[3] ?? false);
				if (!($data = json_decode($options['content'], true)))
				{
					throw new \Exception('Error decoding JSON: '.json_last_error_msg(), 422);
				}
				// ToDo: check required attributes

				$preset = array_filter(array_intersect_key($data, array_flip(['to', 'cc', 'bcc', 'replyto', 'subject', 'priority']))+[
					'body' => $data['bodyHtml'] ?? null ?: $data['body'] ?? '',
					'mimeType' => !empty($data['bodyHtml']) ? 'html' : 'plain',
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
					$push->call('egw.open', '', 'mail', 'add', ['preset' => $preset], '_blank', 'mail');
					echo json_encode([
						'status' => 200,
						'message' => 'Request to open compose window sent',
						//'data' => $preset,
					], self::JSON_RESPONSE_OPTIONS);
					return true;
				}
				$acc_id = Api\Mail\Account::read_identity($ident_id)['acc_id'];
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
	 * Handle propfind in the timesheet folder / get request on the collection itself
	 *
	 * @param string $path
	 * @param array &$options
	 * @param array &$files
	 * @param int $user account_id
	 * @param string $id =''
	 * @return mixed boolean true on success, false on failure or string with http status (eg. '404 Not Found')
	 */
	function propfind($path,&$options,&$files,$user,$id='')
	{
		$filter = [
			'ts_owner' => $user ?: null,
		];

		// process REPORT filters or multiget href's
		$nresults = null;
		if (($id || $options['root']['name'] != 'propfind') && !$this->_report_filters($options,$filter,$id, $nresults))
		{
			return false;
		}
		if ($id) $path = dirname($path).'/';	// carddav_name get's added anyway in the callback

		if ($this->debug) error_log(__METHOD__."($path,".array2string($options).",,$user,$id) filter=".array2string($filter));

		// rfc 6578 sync-collection report: filter for sync-token is already set in _report_filters
		if ($options['root']['name'] == 'sync-collection')
		{
			// callback to query sync-token, after propfind_callbacks / iterator is run and
			// stored max. modification-time in $this->sync_collection_token
			$files['sync-token'] = array($this, 'get_sync_collection_token');
			$files['sync-token-params'] = array($path, $user);

			$this->sync_collection_token = null;

			$filter['order'] = 'ts_modified ASC';	// return oldest modifications first
			$filter['sync-collection'] = true;
		}

		if (isset($nresults))
		{
			$files['files'] = $this->propfind_generator($path, $filter, $files['files'], (int)$nresults);

			// hack to support limit with sync-collection report: contacts are returned in modified ASC order (oldest first)
			// if limit is smaller than full result, return modified-1 as sync-token, so client requests next chunk incl. modified
			// (which might contain further entries with identical modification time)
			if ($options['root']['name'] == 'sync-collection' && $this->bo->total > $nresults)
			{
				--$this->sync_collection_token;
				$files['sync-token-params'][] = true;	// tell get_sync_collection_token that we have more entries
			}
		}
		else
		{
			// return iterator, calling ourselves to return result in chunks
			$files['files'] = $this->propfind_generator($path,$filter, $files['files']);
		}
		return true;
	}

	/**
	 * Chunk-size for DB queries of profind_generator
	 */
	const CHUNK_SIZE = 500;

	/**
	 * Generator for propfind with ability to skip reporting not found ids
	 *
	 * @param string $path
	 * @param array& $filter
	 * @param array $extra extra resources like the collection itself
	 * @param int|null $nresults option limit of number of results to report
	 * @param boolean $report_not_found_multiget_ids=true
	 * @return Generator<array with values for keys path and props>
	 */
	function propfind_generator($path, array &$filter, array $extra=[], $nresults=null, $report_not_found_multiget_ids=true)
	{
		//error_log(__METHOD__."('$path', ".array2string($filter).", ".array2string($start).", $report_not_found_multiget_ids)");
		$starttime = microtime(true);
		$filter_in = $filter;

		// yield extra resources like the root itself
		$yielded = 0;
		foreach($extra as $resource)
		{
			if (++$yielded && isset($nresults) && $yielded > $nresults)
			{
				return;
			}
			yield $resource;
		}

		if (isset($filter['order']))
		{
			$order = $filter['order'];
			unset($filter['order']);
		}
		else
		{
			$order = 'egw_timesheet.ts_id';
		}
		// detect sync-collection report
		$sync_collection_report = $filter['sync-collection'];
		unset($filter['sync-collection']);

		// stop output buffering switched on to log the response, if we should return more than 200 entries
		if (!empty($this->requested_multiget_ids) && ob_get_level() && count($this->requested_multiget_ids) > 200)
		{
			$this->caldav->log("### ".count($this->requested_multiget_ids)." resources requested in multiget REPORT --> turning logging off to allow streaming of the response");
			ob_end_flush();
		}

		$search = $filter['search'] ?? [];
		unset($filter['search']);
		for($chunk=0; ($timesheets =& $this->bo->search($search, '*', $order, '', '', False, 'AND',
			[$chunk*self::CHUNK_SIZE, self::CHUNK_SIZE], $filter)); ++$chunk)
		{
			// read custom-fields
			if ($this->bo->customfields)
			{
				$id2keys = array();
				foreach($timesheets as $key => &$timesheet)
				{
					$id2keys[$timesheet['ts_id']] = $key;
				}
				if (($cfs = $this->bo->read_customfields(array_keys($id2keys))))
				{
					foreach($cfs as $id => $data)
					{
						$timesheets[$id2keys[$id]] += $data;
					}
				}
			}
			foreach($timesheets as &$timesheet)
			{
				$content = JsTimesheet::JsTimesheet($timesheet, false);
				$timesheet = Api\Db::strip_array_keys($timesheet, 'ts_');

				// remove contact from requested multiget ids, to be able to report not found urls
				if (!empty($this->requested_multiget_ids) && ($k = array_search($timesheet[self::$path_attr], $this->requested_multiget_ids)) !== false)
				{
					unset($this->requested_multiget_ids[$k]);
				}
				// sync-collection report: deleted entry need to be reported without properties
				if ($timesheet['ts_status'] == \timesheet_bo::DELETED_STATUS)
				{
					if (++$yielded && isset($nresults) && $yielded > $nresults)
					{
						return;
					}
					yield ['path' => $path.urldecode($this->get_path($timesheet))];
					continue;
				}
				$props = array(
					'getcontenttype' => Api\CalDAV::mkprop('getcontenttype', 'application/json'),
					'getlastmodified' => Api\DateTime::user2server($timesheet['modified']),
					'displayname' => $timesheet['title'],
				);
				if (true)
				{
					$props['getcontentlength'] = bytes(is_array($content) ? json_encode($content) : $content);
					$props['data'] = Api\CalDAV::mkprop(Api\CalDAV::CARDDAV, 'data', $content);
				}
				if (++$yielded && isset($nresults) && $yielded > $nresults)
				{
					return;
				}
				yield $this->add_resource($path, $timesheet, $props);
			}
			// sync-collection report --> return modified of last contact as sync-token
			if ($sync_collection_report)
			{
				$this->sync_collection_token = $timesheet['modified'];
			}
		}

		// report not found multiget urls
		if ($report_not_found_multiget_ids && !empty($this->requested_multiget_ids))
		{
			foreach($this->requested_multiget_ids as $id)
			{
				if (++$yielded && isset($nresults) && $yielded > $nresults)
				{
					return;
				}
				yield ['path' => $path.$id.self::$path_extension];
			}
		}

		if ($this->debug)
		{
			error_log(__METHOD__."($path, filter=".json_encode($filter).', extra='.json_encode($extra).
				", nresults=$nresults, report_not_found=$report_not_found_multiget_ids) took ".
				(microtime(true) - $starttime)." to return $yielded resources");
		}
	}

	/**
	 * Process the filters from the CalDAV REPORT request
	 *
	 * @param array $options
	 * @param array &$filters
	 * @param string $id
	 * @param int &$nresult on return limit for number or results or unchanged/null
	 * @return boolean true if filter could be processed
	 */
	function _report_filters($options, &$filters, $id, &$nresults)
	{
		// in case of JSON/REST API pass filters to report
		if (Api\CalDAV::isJSON() && !empty($options['filters']) && is_array($options['filters']))
		{
			$filters += $options['filters'];    // using += to no allow overwriting existing filters
		}
		elseif (!empty($options['filters']))
		{
			/* Example of a complex filter used by Mac Addressbook
			  <B:filter test="anyof">
			    <B:prop-filter name="FN" test="allof">
			      <B:text-match collation="i;unicode-casemap" match-type="contains">becker</B:text-match>
			      <B:text-match collation="i;unicode-casemap" match-type="contains">ralf</B:text-match>
			    </B:prop-filter>
			    <B:prop-filter name="EMAIL" test="allof">
			      <B:text-match collation="i;unicode-casemap" match-type="contains">becker</B:text-match>
			      <B:text-match collation="i;unicode-casemap" match-type="contains">ralf</B:text-match>
			    </B:prop-filter>
			    <B:prop-filter name="NICKNAME" test="allof">
			      <B:text-match collation="i;unicode-casemap" match-type="contains">becker</B:text-match>
			      <B:text-match collation="i;unicode-casemap" match-type="contains">ralf</B:text-match>
			    </B:prop-filter>
			  </B:filter>
			*/
			$filter_test = isset($options['filters']['attrs']) && isset($options['filters']['attrs']['test']) ?
				$options['filters']['attrs']['test'] : 'anyof';
			$prop_filters = array();

			$matches = $prop_test = $column = null;
			foreach($options['filters'] as $n => $filter)
			{
				if (!is_int($n)) continue;	// eg. attributes of filter xml element

				switch((string)$filter['name'])
				{
					case 'param-filter':
						$this->caldav->log(__METHOD__."(...) param-filter='{$filter['attrs']['name']}' not (yet) implemented!");
						break;
					case 'prop-filter':	// can be multiple prop-filter, see example
						if ($matches) $prop_filters[] = implode($prop_test=='allof'?' AND ':' OR ',$matches);
						$matches = array();
						$prop_filter = strtoupper($filter['attrs']['name']);
						$prop_test = isset($filter['attrs']['test']) ? $filter['attrs']['test'] : 'anyof';
						if ($this->debug > 1) error_log(__METHOD__."(...) prop-filter='$prop_filter', test='$prop_test'");
						break;
					case 'is-not-defined':
						$matches[] = '('.$column."='' OR ".$column.' IS NULL)';
						break;
					case 'text-match':	// prop-filter can have multiple text-match, see example
						if (!isset($this->filter_prop2cal[$prop_filter]))	// eg. not existing NICKNAME in EGroupware
						{
							if ($this->debug || $prop_filter != 'NICKNAME') error_log(__METHOD__."(...) text-match: $prop_filter {$filter['attrs']['match-type']} '{$filter['data']}' unknown property '$prop_filter' --> ignored");
							$column = false;	// to ignore following data too
						}
						else
						{
							switch($filter['attrs']['collation'])	// todo: which other collations allowed, we are always unicode
							{
								case 'i;unicode-casemap':
								default:
									$comp = ' '.$GLOBALS['egw']->db->capabilities[Api\Db::CAPABILITY_CASE_INSENSITIV_LIKE].' ';
									break;
							}
							$column = $this->filter_prop2cal[strtoupper($prop_filter)];
							if (strpos($column, '_') === false) $column = 'contact_'.$column;
							if (!isset($filters['order'])) $filters['order'] = $column;
							$match_type = $filter['attrs']['match-type'];
							$negate_condition = isset($filter['attrs']['negate-condition']) && $filter['attrs']['negate-condition'] == 'yes';
						}
						break;
					case '':	// data of text-match element
						if (isset($filter['data']) && isset($column))
						{
							if ($column)	// false for properties not known to EGroupware
							{
								$value = str_replace(array('%', '_'), array('\\%', '\\_'), $filter['data']);
								switch($match_type)
								{
									case 'equals':
										$sql_filter = $column . $comp . $GLOBALS['egw']->db->quote($value);
										break;
									default:
									case 'contains':
										$sql_filter = $column . $comp . $GLOBALS['egw']->db->quote('%'.$value.'%');
										break;
									case 'starts-with':
										$sql_filter = $column . $comp . $GLOBALS['egw']->db->quote($value.'%');
										break;
									case 'ends-with':
										$sql_filter = $column . $comp . $GLOBALS['egw']->db->quote('%'.$value);
										break;
								}
								$matches[] = ($negate_condition ? 'NOT ' : '').$sql_filter;

								if ($this->debug > 1) error_log(__METHOD__."(...) text-match: $prop_filter $match_type' '{$filter['data']}'");
							}
							unset($column);
							break;
						}
					// fall through
					default:
						$this->caldav->log(__METHOD__."(".array2string($options).",,$id) unknown filter=".array2string($filter).' --> ignored');
						break;
				}
			}
			if ($matches) $prop_filters[] = implode($prop_test=='allof'?' AND ':' OR ',$matches);
			if ($prop_filters)
			{
				$filters[] = $filter = '(('.implode($filter_test=='allof'?') AND (':') OR (', $prop_filters).'))';
				if ($this->debug) error_log(__METHOD__."(path=$options[path], ...) sql-filter: $filter");
			}
		}
		// parse limit from $options['other']
		/* Example limit
		  <B:limit>
		    <B:nresults>10</B:nresults>
		  </B:limit>
		*/
		foreach((array)$options['other'] as $option)
		{
			switch($option['name'])
			{
				case 'nresults':
					$nresults = (int)$option['data'];
					//error_log(__METHOD__."(...) options[other]=".array2string($options['other'])." --> nresults=$nresults");
					break;
				case 'limit':
					break;
				case 'href':
					break;	// from addressbook-multiget, handled below
				// rfc 6578 sync-report
				case 'sync-token':
					if (!empty($option['data']))
					{
						$parts = explode('/', $option['data']);
						$sync_token = array_pop($parts);
						$filters[] = 'contact_modified>'.(int)$sync_token;
						$filters['tid'] = null;	// to return deleted entries too
					}
					break;
				case 'sync-level':
					if ($option['data'] != '1')
					{
						$this->caldav->log(__METHOD__."(...) only sync-level {$option['data']} requested, but only 1 supported! options[other]=".array2string($options['other']));
					}
					break;
				default:
					$this->caldav->log(__METHOD__."(...) unknown xml tag '{$option['name']}': options[other]=".array2string($options['other']));
					break;
			}
		}
		// multiget --> fetch the url's
		$this->requested_multiget_ids = null;
		if ($options['root']['name'] == 'addressbook-multiget')
		{
			$this->requested_multiget_ids = [];
			foreach($options['other'] as $option)
			{
				if ($option['name'] == 'href')
				{
					$parts = explode('/',$option['data']);
					if (($id = urldecode(array_pop($parts))))
					{
						$this->requested_multiget_ids[] = self::$path_extension ? basename($id,self::$path_extension) : $id;
					}
				}
			}
			if ($this->requested_multiget_ids) $filters[self::$path_attr] = $this->requested_multiget_ids;
			if ($this->debug) error_log(__METHOD__."(...) addressbook-multiget: ids=".implode(',', $this->requested_multiget_ids));
		}
		elseif ($id)
		{
			$filters[self::$path_attr] = self::$path_extension ? basename($id,self::$path_extension) : $id;
		}
		//error_log(__METHOD__."() options[other]=".array2string($options['other'])." --> filters=".array2string($filters));
		return true;
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

		if (!is_array($timesheet = $this->_common_get_put_delete('GET',$options,$id)))
		{
			return $timesheet;
		}

		try
		{
			// jsContact or vCard
			if (($type=Api\CalDAV::isJSON()))
			{
				$options['data'] = JsTimesheet::JsTimesheet($timesheet, $type);
				$options['mimetype'] = 'application/json';

				header('Content-Encoding: identity');
				header('ETag: "'.$this->get_etag($timesheet).'"');
				return true;
			}
		}
		catch (\Throwable $e) {
			return self::handleException($e);
		}
		return '501 Not Implemented';
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
	 * Handle put request for a contact
	 *
	 * @param array &$options
	 * @param int $id
	 * @param int $user =null account_id of owner, default null
	 * @param string $prefix =null user prefix from path (eg. /ralf from /ralf/addressbook)
	 * @param string $method='PUT' also called for POST and PATCH
	 * @param string $content_type=null
	 * @return mixed boolean true on success, false on failure or string with http status (eg. '404 Not Found')
	 */
	function put(&$options, $id, $user=null, $prefix=null, string $method='PUT', string $content_type=null)
	{
		$old = $this->_common_get_put_delete($method,$options,$id);
		if (!is_null($old) && !is_array($old))
		{
			if ($this->debug) error_log(__METHOD__."(,'$id', $user, '$prefix') returning ".array2string($old));
			return $old;
		}

		$type = null;
		$timesheet = JsTimesheet::parseJsTimesheet($options['content'], $old ?: [], $content_type, $method);

		/* uncomment to return parsed data for testing
		header('Content-Type: application/json');
		echo json_encode($timesheet, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
		return "200 Ok";
		*/

		if (is_array($old))
		{
			$id = $old['id'];
			$retval = true;
		}
		else
		{
			// new entry
			$id = -1;
			$retval = '201 Created';
		}

		if (is_array($old))
		{
			$timesheet['ts_id'] = $old['id'];
			// don't allow the client to overwrite certain values
			$timesheet['ts_owner'] = $old['owner'];
			$timesheet['ts_created'] = $old['created'];
		}
		else
		{
			// only set owner, if user is explicitly specified in URL (check via prefix, NOT for /addressbook/) or sync-all-in-one!)
			if ($prefix && $user)
			{
				$timesheet['ts_owner'] = $user;
			}
			else
			{
				$timesheet['ts_owner'] = $GLOBALS['egw_info']['user']['account_id'];
			}
		}
		if ($this->http_if_match) $timesheet['etag'] = self::etag2value($this->http_if_match);

		if (!($save_ok = $this->bo->save($timesheet)))
		{
			if ($this->debug) error_log(__METHOD__."(,$id) save(".array2string($timesheet).") failed, Ok=$save_ok");
			if ($save_ok === 0)
			{
				// honor Prefer: return=representation for 412 too (no need for client to explicitly reload)
				$this->check_return_representation($options, $id, $user);
				return '412 Precondition Failed';
			}
			return '403 Forbidden';	// happens when writing new entries in AB's without ADD rights
		}

		// send evtl. necessary response headers: Location, etag, ...
		$this->put_response_headers($timesheet, $options['path'], $retval);

		if ($this->debug > 1) error_log(__METHOD__."(,'$id', $user, '$prefix') returning ".array2string($retval));
		return $retval;
	}

	/**
	 * Handle delete request for an applications entry
	 *
	 * @param array &$options
	 * @param int $id
	 * @param int $user account_id of collection owner
	 * @return mixed boolean true on success, false on failure or string with http status (eg. '404 Not Found')
	 */
	function delete(&$options,$id,$user)
	{
		if (!is_array($timesheet = $this->_common_get_put_delete('DELETE',$options,$id)))
		{
			return $timesheet;
		}
		if (($ok = $this->bo->delete($timesheet['id'],self::etag2value($this->http_if_match))) === 0)
		{
			return '412 Precondition Failed';
		}
		return $ok;
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
		if (($ret = $this->bo->read($id)))
		{
			$ret = Api\Db::strip_array_keys($ret, 'ts_');
		}
		return $ret;
	}

	/**
	 * Check if user has the necessary rights on an entry
	 *
	 * @param int $acl Api\Acl::READ, Api\Acl::EDIT or Api\Acl::DELETE
	 * @param array|int $entry entry-array or id
	 * @return boolean null if entry does not exist, false if no access, true if access permitted
	 */
	function check_access($acl, $entry)
	{
		return $this->bo->check_acl($acl, is_array($entry) ? $entry+['ts_onwer' => $entry['owner']] : $entry);
	}
}