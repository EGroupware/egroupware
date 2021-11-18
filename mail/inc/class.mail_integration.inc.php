<?php
/**
 * EGroupware - Mail - integration interface class
 *
 * @link http://www.egroupware.org
 * @package mail
 * @author Hadi Nategh [hn@egroupware.org]
 * @copyright (c) 2015-16 by EGroupware GmbH <info-AT-egroupware.org>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id:$
 */

use EGroupware\Api;
use EGroupware\Api\Link;
use EGroupware\Api\Mail;

/**
 * Class cotains methods and functions
 * to be used to integrate mail's message into other applications
 *
 */
class mail_integration {

	/**
	 * Public functions
	 * @var type
	 */
	var $public_functions = array(
		'integrate' => true
	);

	/**
	 * Maximum number of line characters (-_+=~) allowed in a mail, to not stall the layout.
	 * Longer lines / biger number of these chars are truncated to that max. number or chars.
	 *
	 * @var int
	 */
	const MAX_LINE_CHARS = 40;

	/**
	 * Used to flag inline images so they can be found & urls fixed when in their
	 * final destination.
	 */
	const INLINE_PREFIX = 'mail-';

	/**
	 * Gets requested mail information and sets them as data link
	 * -Execute registered hook method from the requested app for integration
	 * -with provided content from mail:
	 *
	 * -array(	'addresses' => array (
	 *					'email'=> stirng,
	 *					'personel' => string),
	 *			'attachments' => array (
	 *					'name' => string,		// file name
	 *					'type' => string,		// mime type
	 *					'egw_data'=> string,	// hash md5 id of an stored attachment in session (attachment which is in IMAP server)
	 *											// NOTE: the attachmet either have egw_data OR tmp_name (e.g. raw mail eml file stores in tmp)
	 *					'tmp_name' => string),	// tmp dir path
	 *			'message' => string,
	 *			'date' => string,
	 *			'subject' => string,
	 *			'entry_id => string				// Id of the app entry which mail content will append to
	 *	)
	 *
	 * @param string $_to_emailAddress
	 * @param string $_subject mail subject
	 * @param string $_body mail message
	 * @param array $_attachments attachments
	 * @param string $_date
	 * @param string $_rawMail path to file with raw mail
	 * @param int $_icServerID mail profile id
	 */
	public static function integrate ($_to_emailAddress=false,$_subject=false,$_body=false,$_attachments=false,$_date=false,$_rawMail=null,$_icServerID=null)
	{
		// App name which is called for integration
		$app = isset($GLOBALS['egw_info']['user']['apps'][$_GET['app']])? $_GET['app'] : null;

		// preset app entry id, selected by user from app_entry_dialog
		$app_entry_id = $_GET['entry_id'];

		$GLOBALS['egw_info']['flags']['currentapp'] = $app;

		// Set the date
		if (!$_date)
		{
			$time = time();
			$_date = Api\DateTime::server2user($time->now,'ts');
		}

		$data = static::get_integrate_data($_GET['rowid'], $_to_emailAddress, $_subject, $_body, $_attachments, $_date, $_rawMail, $_icServerID);
		$data['entry_id'] = $app_entry_id;

		// Check if the hook is registered
		if (Api\Hooks::exists('mail_import',$app) == 0)
		{
			// Try to register hook
			Api\Hooks::read(true);
		}

		// Get the registered hook method of requested app for integration
		$hook = Api\Hooks::single(array('location' => 'mail_import'),$app);

		// Load Api\Translation for the app since the original URL
		// is from mail integration and only loads mail Api\Translation
		Api\Translation::add_app($app);

		// Execute import mail with provided content
		ExecMethod($hook['menuaction'],$data);
	}

	/**
	 * Gets requested mail information and sets them as data link
	 * -with provided content from mail:
	 *
	 * -array(	'addresses' => array (
	 *					'email'=> stirng,
	 *					'personel' => string),
	 *			'attachments' => array (
	 *					'name' => string,		// file name
	 *					'type' => string,		// mime type
	 *					'egw_data'=> string,	// hash md5 id of an stored attachment in session (attachment which is in IMAP server)
	 *											// NOTE: the attachmet either have egw_data OR tmp_name (e.g. raw mail eml file stores in tmp)
	 *					'tmp_name' => string),	// tmp dir path
	 *			'message' => string,
	 *			'date' => string,
	 *			'subject' => string
	 *	)
	 *
	 * @param string $_rowid
	 * @param string $_to_emailAddress
	 * @param string $_subject mail subject
	 * @param string $_body mail message
	 * @param array $_attachments attachments
	 * @param string $_date
	 * @param string $_rawMail path to file with raw mail
	 * @param int $_icServerID mail profile id
	 * @throws Api\Exception\AssertionFailed
	 */
	public static function get_integrate_data($_rowid=false, $_to_emailAddress=false,$_subject=false,$_body=false,$_attachments=false,$_date=false,$_rawMail=null,$_icServerID=null)
	{
		// For dealing with multiple files of the same name
		$dupe_count = $file_list = array();

		//error_log(__METHOD__.__LINE__.': RowID:'.$_rowid.': emailAddress:'. array2string($_to_emailAddress));
		// Integrate not yet saved mail
		if (empty($_rowid) && $_to_emailAddress)
		{
			$sessionLocation = 'mail';
			$mailbox = base64_decode($_GET['mailbox']);

			if (!(in_array($GLOBALS['egw_info']['user']['preferences'][$sessionLocation]['saveAsOptions'],['text_only','no_attachments']))&&is_array($_attachments))
			{
				// initialize mail open connection requirements
				if (!isset($_icServerID)) $_icServerID =& Api\Cache::getSession($sessionLocation,'activeProfileID');
				$mo = Mail::getInstance(true,$_icServerID);
				$mo->openConnection();
				$messagePartId = $messageFolder = null;
				foreach ($_attachments as $attachment)
				{
					//error_log(__METHOD__.__LINE__.array2string($attachment));
					if (trim(strtoupper($attachment['type'])) == 'MESSAGE/RFC822' && !empty($attachment['uid']) && !empty($attachment['folder']))
					{
						$mo->reopen(($attachment['folder']?$attachment['folder']:$mailbox));

						// get the message itself, and attach it, as we are able to display it in egw
						// instead of fetching only the attachments attached files (as we did previously)
						$message = $mo->getMessageRawBody($attachment['uid'],$attachment['partID'],($attachment['folder']?$attachment['folder']:$mailbox));
						$headers = $mo->getMessageHeader($attachment['uid'],$attachment['partID'],true,false,($attachment['folder']?$attachment['folder']:$mailbox));
						$subject = Mail::clean_subject_for_filename($headers['SUBJECT']);
						$attachment_file =tempnam($GLOBALS['egw_info']['server']['temp_dir'],$GLOBALS['egw_info']['flags']['currentapp']."_");
						$tmpfile = fopen($attachment_file,'w');
						fwrite($tmpfile,$message);
						fclose($tmpfile);
						$size = filesize($attachment_file);
						$attachments[] = array(
								'name' => trim($subject).'.eml',
								'mimeType' => 'message/rfc822',
								'type' => 'message/rfc822',
								'tmp_name' => $attachment_file,
								'size' => $size,
							);
					}
					else
					{
						if (!empty($attachment['folder']))
						{
							$is_winmail = $_GET['is_winmail'] ? $_GET['is_winmail'] : 0;
							$messageFolder = $attachment['folder'];
							$messageUid = $attachment['uid'];
							$messagePartId = $attachment['partID'];
							$mo->reopen($attachment['folder']);
							$attachmentData = $mo->getAttachment($attachment['uid'],$attachment['partID'],$is_winmail,false,false,$attachment['folder']);
							$attachment['file'] =tempnam($GLOBALS['egw_info']['server']['temp_dir'],$GLOBALS['egw_info']['flags']['currentapp']."_");
							$tmpfile = fopen($attachment['file'],'w');
							fwrite($tmpfile,$attachmentData['attachment']);
							fclose($tmpfile);
						}
						//make sure we search for our attached file in our configured temp_dir
						if (isset($attachment['file']) && parse_url($attachment['file'],PHP_URL_SCHEME) != 'vfs' &&
							file_exists($GLOBALS['egw_info']['server']['temp_dir'].'/'.basename($attachment['file'])))
						{
							$attachment['file'] = $GLOBALS['egw_info']['server']['temp_dir'].'/'.basename($attachment['file']);
						}
						if(in_array($attachment['name'], $file_list))
						{
							$dupe_count[$attachment['name']]++;
							$attachment['name'] = pathinfo($attachment['name'], PATHINFO_FILENAME) .
								' ('.($dupe_count[$attachment['name']] + 1).')' . '.' .
								pathinfo($attachment['name'], PATHINFO_EXTENSION);
						}
						$attachments[] = array(
							'name' => $attachment['name'],
							'mimeType' => $attachment['type'],
							'type' => $attachment['type'],
							'tmp_name' => $attachment['file'],
							'size' => $attachment['size'],
						);
						$file_list[] = $attachment['name'];
					}
				}
				if ($messageFolder && $messageUid && $messagePartId && $mo->isDraftFolder($messageFolder) && !$mo->isTemplateFolder($messageFolder))
				{
					//error_log(__METHOD__.__LINE__."#".$messageUid.'#'.$messageFolder);
					try // message may be deleted already, as it maybe done by autosave
					{
						$mo->deleteMessages(array($messageUid),$messageFolder);
					}
					catch (Api\Exception $e)
					{
						//error_log(__METHOD__.__LINE__." ". str_replace('"',"'",$e->getMessage()));
						unset($e);
					}
				}
				$mo->closeConnection();
			}
			// this one adds the mail itself (as message/rfc822 (.eml) file) to the app as additional attachment
			// this is done to have a simple archive functionality (ToDo: opening .eml in email module)
			if (in_array($GLOBALS['egw_info']['user']['preferences'][$sessionLocation]['saveAsOptions'],['add_raw','no_attachments']) &&
				$_rawMail && file_exists($_rawMail))
			{
				$subject = Mail::clean_subject_for_filename($_subject);
				$attachments[] = array(
						'name' => trim($subject).'.eml',
						'mimeType' => 'message/rfc822',
						'type' => 'message/rfc822',
						'tmp_name' => $_rawMail,
						'size' => filesize($_rawMail),
						'add_raw' => true
					);
			}

			$toaddr = array();
			foreach(array('to','cc','bcc') as $x)
			{
				if (is_array($_to_emailAddress[$x]) && !empty($_to_emailAddress[$x]))
				{
					$toaddr = array_merge($toaddr,$_to_emailAddress[$x]);
				}
			}
			$body_striped = strip_tags($_body); //we need to fix broken tags (or just stuff like "<800 USD/p" )
			$body_decoded = htmlspecialchars_decode($body_striped,ENT_QUOTES);
			$body = Mail::createHeaderInfoSection(array('FROM'=>$_to_emailAddress['from'],
				'TO'=>(!empty($_to_emailAddress['to'])?implode(',',$_to_emailAddress['to']):null),
				'CC'=>(!empty($_to_emailAddress['cc'])?implode(',',$_to_emailAddress['cc']):null),
				'BCC'=>(!empty($_to_emailAddress['bcc'])?implode(',',$_to_emailAddress['bcc']):null),
				'SUBJECT'=>$_subject,
				'DATE'=>Mail::_strtotime($_date))).$body_decoded;

			$mailcontent = array(
				'mailaddress' => implode(',',$toaddr),
				'subject' => $_subject,
				'message' => $body,
				'attachments' => $attachments,
				'date' => $_date
			);

		}
		// Integrate already saved mail with ID
		else
		{
			// Initializing mail connection requirements
			$hA = mail_ui::splitRowID($_rowid);
			$sessionLocation = $hA['app']; // THIS is part of the row ID, we may use this for validation
			// Check the mail app
			if ($sessionLocation != 'mail') throw new Api\Exception\AssertionFailed(lang('Application mail expected but got: %1',$sessionLocation));
			$uid = $hA['msgUID'];
			$mailbox = $hA['folder'];
			$icServerID = $hA['profileID'];

			if ($uid && $mailbox)
			{
				if (!isset($icServerID)) $icServerID =& Api\Cache::getSession($sessionLocation,'activeProfileID');
				$mo	= Mail::getInstance(true,$icServerID);
				$mo->openConnection();
				$mo->reopen($mailbox);
				try {
					$mailcontent = Mail::get_mailcontent($mo,$uid,'',$mailbox,null,true,(!(in_array($GLOBALS['egw_info']['user']['preferences'][$sessionLocation]['saveAsOptions'],['text_only','no_attachments']))));
					// this one adds the mail itself (as message/rfc822 (.eml) file) to the app as additional attachment
					// this is done to have a simple archive functionality (ToDo: opening .eml in email module)
					if (in_array($GLOBALS['egw_info']['user']['preferences'][$sessionLocation]['saveAsOptions'],['add_raw','no_attachments']))
					{
						$message = $mo->getMessageRawBody($uid, '',$mailbox);
						$headers = $mo->getMessageHeader($uid, '',true,false,$mailbox);
						$attachment_file =tempnam($GLOBALS['egw_info']['server']['temp_dir'],$GLOBALS['egw_info']['flags']['currentapp']."mail_integrate");
						$tmpfile = fopen($attachment_file,'w');
						fwrite($tmpfile,$message);
						fclose($tmpfile);
						$size = filesize($attachment_file);
						$mailcontent['attachments'][] = array(
								'name' => Mail::clean_subject_for_filename($headers['SUBJECT']).'.eml',
								'mimeType' => 'message/rfc822',
								'type' => 'message/rfc822',
								'tmp_name' => $attachment_file,
								'size' => $size,
								'add_raw' => true
						);
					}
					$mailcontent['date'] = strtotime($mailcontent['headers']['DATE']);
				}
				catch (Mail\Smime\PassphraseMissing $ex) {
					EGroupware\Api\Framework::message(lang('Fetching content of this message failed'.
						' because the content of this message seems to be encrypted'.
						' and can not be decrypted properly.'),'error');
				}
			}
		}

		//consider all addresses in the header
		foreach (['TO','CC','BCC', 'FROM'] as $h)
		{
			$mailcontent['mailaddress'] .= ','.$headers[$h];
		}
		// Convert addresses to email and personal
		$addresses = imap_rfc822_parse_adrlist($mailcontent['mailaddress'],'');
		foreach ($addresses as $address)
		{
			$email = sprintf('%s@%s',trim($address->mailbox),trim($address->host));
			$data_addresses[] = array (
				'email' => $email,
				'name' => !empty($address->personal) ? $address->personal : $email
			);
		}

		// shorten long (> self::max_line_chars) lines of "line" chars (-_+=~) in mails
		$data_message = preg_replace_callback(
			'/[-_+=~\.]{'.self::MAX_LINE_CHARS.',}/m',
			function($matches) {
				return substr($matches[0],0,self::MAX_LINE_CHARS);
			},
			$mailcontent['message']
		);

		// Get attachments ready for integration as link
		if (is_array($mailcontent['attachments']))
		{
			foreach($mailcontent['attachments'] as $key => $attachment)
			{
				$data_attachments[$key] = array(
					'name' => $mailcontent['attachments'][$key]['name'],
					'type' => $mailcontent['attachments'][$key]['type'],
					'size' => $mailcontent['attachments'][$key]['size'],
					'tmp_name' => $mailcontent['attachments'][$key]['tmp_name']
				);
				if ($uid && !$mailcontent['attachments'][$key]['add_raw'])
				{
					$data_attachments[$key]['egw_data'] = Link::set_data($mailcontent['attachments'][$key]['mimeType'],
						'EGroupware\\Api\\Mail::getAttachmentAccount',array($icServerID, $mailbox, $uid, $attachment['partID'], $is_winmail, true),true);
				}
				unset($mailcontent['attachments'][$key]['add_raw']);

				// Fix inline images
				if($mailcontent['html_message'] && $attachment['cid'] && $data_attachments[$key]['egw_data'])
				{
					$link_callback = function($cid) use($data_attachments, $attachment, $key)
					{
						if ($attachment['cid'] == $cid)
						{
							return self::INLINE_PREFIX.$data_attachments[$key]['egw_data'].'" title="['.$data_attachments[$key]['name'].']';
						}
						else
						{
							return "cid:".$cid;
						}
					};
					foreach(array('src','url','background') as $type)
					{
						$mailcontent['html_message'] = mail_ui::resolve_inline_image_byType(
								$mailcontent['html_message'],
								$mailbox,
								$attachment['uid'],
								$attachment['partID'],
								$type,
								$link_callback
						);
					}
				}
			}
		}

		return array (
			'addresses' => $data_addresses,
			'attachments' => $data_attachments,
			'message' => $data_message,
			'html_message' => $mailcontent['html_message'],
			'date' => $mailcontent['date'],
			'subject' => $mailcontent['subject'],
			'entry_id' => null,
		);
	}

	public static function fix_inline_images($app, $id, array $links, &$html)
	{
		$replace = array();
		foreach($links as $link)
		{
			if (is_array($link) && is_array($link['id']) && !empty($link['id']['egw_data']) && strpos($html, self::INLINE_PREFIX . $link['id']['egw_data']) !== false)
			{
				$replace[self::INLINE_PREFIX. $link['id']['egw_data']] =
					Api\Egw::link(Api\Vfs::download_url(Api\Link::vfs_path($app, $id, Api\Vfs::basename($link['id']['name']))));
			}
		}
		if ($replace)
		{
			$html = strtr($old = $html, $replace);
		}
		return isset($old) && $old != $html;
	}
}

