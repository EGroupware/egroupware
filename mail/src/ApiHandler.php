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
	const JSON_RESPONSE_OPTIONS = JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES;

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
		}

		try {
			if (str_starts_with($path, '/mail/attachments/'))
			{
				$attachment_path = tempnam($GLOBALS['egw_info']['server']['temp_dir'], 'attach--'.
					(str_replace('/', '-', substr($options['path'], 18)) ?: 'no-name').'--');
				if (file_put_contents($attachment_path, $options['content']))
				{
					header('Location: '.($location = '/mail/attachments/'.substr(basename($attachment_path), 8)));
					echo json_encode([
						'status'   => 200,
						'message'  => 'Attachment stored',
						'location' => $location,
					], self::JSON_RESPONSE_OPTIONS);
					return '200 Ok';
				}
				throw new \Exception('Error storing attachment');
			}
			elseif (preg_match('#^/mail/((\d+):(\d+)/)?(compose)?#', $path, $matches))
			{
				$acc_id = $matches[2] ?? null;
				$ident_id = $matches[3] ?? null;
				$do_compose = (bool)($matches[4] ?? false);
				if (!($data = json_decode($options['content'], true)))
				{
					throw new \Exception('Error decoding JSON: '.json_last_error_msg(), 422);
				}
				// ToDo: check required attributes

				// for compose we need to construct a URL and push it to the client (or give an error if the client is not online)
				if ($do_compose)
				{
					if (!Api\Json\Push::isOnline($user))
					{
						$account_lid = Api\Accounts::id2name($user);
						throw new \Exception("User '$account_lid' (#$user) is NOT online", 404);
					}
					$extra = [
						//'menuaction' => 'mail.mail_compose.compose',
						'preset' => array_filter(array_intersect_key($data, array_flip(['to', 'cc', 'bcc', 'subject']))+[
							'body' => $data['bodyHtml'] ?? null ?: $data['body'] ?? '',
							'mimeType' => !empty($data['bodyHtml']) ? 'html' : 'plain',
							'identity' => $ident_id,
						]+self::prepareAttachments($data['attachments'] ?? [], $data['attachmentType'] ?? 'attach')),
					];
					$push = new Api\Json\Push($user);
					//$push->call('egw.open_link', $link, '_blank', '640x1024');
					$push->call('egw.open', '', 'mail', 'add', $extra, '_blank', 'mail');
					header('Content-Type: application/json');
					echo json_encode([
						'status' => 200,
						'message' => 'Request to open compose window sent',
						'extra' => $extra,
					], self::JSON_RESPONSE_OPTIONS);
					return true;
				}

				$compose = new mail_compose($acc_id);
				$compose->compose([
					'mailaccount' => $acc_id.':'.$ident_id,
					'mail_plaintext' => $data['body'] ?? null,
					'mail_htmltext'  => $data['bodyHtml'] ?? null,
					'mimeType' => !empty($data['bodyHtml']) ? 'html' : 'plain',
					'file' => array_map(__CLASS__.'::uploadsForAttachments', $data['attachments'] ?? []),
				]+array_diff_key($data+array_flip(['attachments', 'body', 'bodyHtml'])));
			}

			header('Content-Type: application/json');
			echo $options['content'];
			return true;
		}
		catch (\Throwable $e) {
			_egw_log_exception($e);
			header('Content-Type: application/json');
			echo json_encode([
				'error'   => $code = $e->getCode() ?: 500,
				'message' => $e->getMessage(),
			]+(empty($GLOBALS['egw_info']['server']['exception_show_trace']) ? [] : [
				'trace' => array_map(static function($trace)
				{
					$trace['file'] = str_replace(EGW_SERVER_ROOT.'/', '', $trace['file']);
					return $trace;
				}, $e->getTrace())
			]), self::JSON_RESPONSE_OPTIONS);
			return (400 <= $code && $code < 600 ? $code : 500).' '.$e->getMessage();
		}
	}

	/**
	 * Convert an attachment name into an upload array for mail_compose::compose
	 *
	 * @param string[]? $attachments either "/mail/attachments/<token>" / file in temp_dir or VFS path
	 * @param string? $attachmentType "attach" (default), "link", "share_ro", "share_rw"
	 * @return array with values for keys "file", "name" and "filemode"
	 * @throws Exception if file not found or unreadable
	 */
	protected static function prepareAttachments(array $attachments, string $attachmentType=null)
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
				$ret['file'][] = $path;
				$ret['name'][] = $matches[2];
				/*return [
					'name' => $matches[2],
					'type' => Api\Vfs::mime_content_type($path),
					'file' => $path,
					'size' => filesize($path),
				];*/
			}
			else
			{
				if (!Api\Vfs::is_readable($attachment))
				{
					throw new \Exception("Attachment $attachment NOT found", 400);
				}
				$ret['file'][] = Api\Vfs::PREFIX.$attachment;
				$ret['name'][] = Api\Vfs::basename($attachment);
				/*return [
					'name' => Api\Vfs::basename($attachment),
					'type' => Api\Vfs::mime_content_type($attachment),
					'file' => Api\Vfs::PREFIX.$attachment,
					'size' => filesize(Api\Vfs::PREFIX.$attachment),
				];*/
			}
		}
		if ($ret)
		{
			$ret['filemode'] = $attachmentType ?? 'attach';
			if (!in_array($ret['filemode'], $valid=['attach', 'link', 'share_ro', 'share_rw']))
			{
				throw new \Exception("Invalid value '$ret[filemode]' for attachmentType, must be one of: '".implode("', '", $valid)."'", 422);
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
						'props' => ['name' => ['val' => $identity]],
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
		echo json_encode($all=iterator_to_array(Api\Mail\Account::identities([], true, 'name',
			$user ?: $GLOBALS['egw_info']['user']['account_id'])), JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
		return true;
		return '501 Not Implemented';
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