<?php
/**
 * EGroupware - Mail - integration interface class
 *
 * @link http://www.egroupware.org
 * @package mail
 * @author Hadi Nategh [hn@stylite.de]
 * @copyright (c) 2015 by Stylite AG <info-AT-stylite.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id:$
 */

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
	 * @throws egw_exception_assertion_failed
	 */
	public static function integrate ($_to_emailAddress=false,$_subject=false,$_body=false,$_attachments=false,$_date=false,$_rawMail=null,$_icServerID=null)
	{
		// App name which is called for integration
		$app = isset($GLOBALS['egw_info']['user']['apps'][$_GET['app']])? $_GET['app'] : null;
		
		// preset app entry id, selected by user from app_entry_dialog
		$app_entry_id = $_GET['entry_id'];
		
		// Set the date
		if (!$_date)
		{
			$time = time();
			$_date = egw_time::server2user($time->now,'ts');
		}
		$GLOBALS['egw_info']['flags']['currentapp'] = $app;
		
		// Integrate not yet saved mail
		if (empty($_GET['rowid']) && $_to_emailAddress && $app)
		{
			$sessionLocation = 'mail';
			$mailbox = base64_decode($_GET['mailbox']);

			if (!($GLOBALS['egw_info']['user']['preferences'][$sessionLocation]['saveAsOptions']==='text_only')&&is_array($_attachments))
			{
				// initialize mail open connection requirements
				if (!isset($_icServerID)) $_icServerID =& egw_cache::getSession($sessionLocation,'activeProfileID');
				$mo = mail_bo::getInstance(true,$_icServerID);
				$mo->openConnection();

				foreach ($_attachments as $attachment)
				{
					if (trim(strtoupper($attachment['type'])) == 'MESSAGE/RFC822' && !empty($attachment['uid']) && !empty($attachment['folder']))
					{
						$mo->reopen(($attachment['folder']?$attachment['folder']:$mailbox));

						// get the message itself, and attach it, as we are able to display it in egw
						// instead of fetching only the attachments attached files (as we did previously)
						$message = $mo->getMessageRawBody($attachment['uid'],$attachment['partID'],($attachment['folder']?$attachment['folder']:$mailbox));
						$headers = $mo->getMessageHeader($attachment['uid'],$attachment['partID'],true,false,($attachment['folder']?$attachment['folder']:$mailbox));
						$subject = mail_bo::adaptSubjectForImport($headers['SUBJECT']);
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
							$mo->reopen($attachment['folder']);
							$attachmentData = $mo->getAttachment($attachment['uid'],$attachment['partID'],$is_winmail);
							$attachment['file'] =tempnam($GLOBALS['egw_info']['server']['temp_dir'],$GLOBALS['egw_info']['flags']['currentapp']."_");
							$tmpfile = fopen($attachment['file'],'w');
							fwrite($tmpfile,$attachmentData['attachment']);
							fclose($tmpfile);
						}
						//make sure we search for our attached file in our configured temp_dir
						if (isset($attachment['file']) && parse_url($attachment['file'],PHP_URL_SCHEME) != 'vfs' &&
							file_exists($GLOBALS['egw_info']['server']['temp_dir'].SEP.basename($attachment['file'])))
						{
							$attachment['file'] = $GLOBALS['egw_info']['server']['temp_dir'].SEP.basename($attachment['file']);
						}
						$attachments[] = array(
							'name' => $attachment['name'],
							'mimeType' => $attachment['type'],
							'type' => $attachment['type'],
							'tmp_name' => $attachment['file'],
							'size' => $attachment['size'],
						);
					}
				}
				$mo->closeConnection();
			}
			// this one adds the mail itself (as message/rfc822 (.eml) file) to the app as additional attachment
			// this is done to have a simple archive functionality (ToDo: opening .eml in email module)
			if ($GLOBALS['egw_info']['user']['preferences'][$sessionLocation]['saveAsOptions']==='add_raw' &&
				$_rawMail && file_exists($_rawMail))
			{
				$subject = mail_bo::adaptSubjectForImport($_subject);
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
			$body = mail_bo::createHeaderInfoSection(array('FROM'=>$_to_emailAddress['from'],
				'TO'=>(!empty($_to_emailAddress['to'])?implode(',',$_to_emailAddress['to']):null),
				'CC'=>(!empty($_to_emailAddress['cc'])?implode(',',$_to_emailAddress['cc']):null),
				'BCC'=>(!empty($_to_emailAddress['bcc'])?implode(',',$_to_emailAddress['bcc']):null),
				'SUBJECT'=>$_subject,
				'DATE'=>mail_bo::_strtotime($_date))).$body_decoded;

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
			$hA = mail_ui::splitRowID($_GET['rowid']);
			$sessionLocation = $hA['app']; // THIS is part of the row ID, we may use this for validation
			// Check the mail app
			if ($sessionLocation != 'mail') throw new egw_exception_assertion_failed(lang('Application mail expected but got: %1',$sessionLocation));
			$uid = $hA['msgUID'];
			$mailbox = $hA['folder'];
			$icServerID = $hA['profileID'];

			if ($uid && $mailbox)
			{
				if (!isset($icServerID)) $icServerID =& egw_cache::getSession($sessionLocation,'activeProfileID');
				$mo	= mail_bo::getInstance(true,$icServerID);
				$mo->openConnection();
				$mo->reopen($mailbox);
				$mailcontent = mail_bo::get_mailcontent($mo,$uid,'',$mailbox,false,true,(!($GLOBALS['egw_info']['user']['preferences'][$sessionLocation]['saveAsOptions']==='text_only')));
				// this one adds the mail itself (as message/rfc822 (.eml) file) to the app as additional attachment
				// this is done to have a simple archive functionality (ToDo: opening .eml in email module)
				if ($GLOBALS['egw_info']['user']['preferences'][$sessionLocation]['saveAsOptions']==='add_raw')
				{
					$message = $mo->getMessageRawBody($uid, '',$mailbox);
					$headers = $mo->getMessageHeader($uid, '',true,false,$mailbox);
					$subject = mail_bo::adaptSubjectForImport($headers['SUBJECT']);
					$attachment_file =tempnam($GLOBALS['egw_info']['server']['temp_dir'],$GLOBALS['egw_info']['flags']['currentapp']."mail_integrate");
					$tmpfile = fopen($attachment_file,'w');
					fwrite($tmpfile,$message);
					fclose($tmpfile);
					$size = filesize($attachment_file);
					$mailcontent['attachments'][] = array(
							'name' => trim($subject).'.eml',
							'mimeType' => 'message/rfc822',
							'type' => 'message/rfc822',
							'tmp_name' => $attachment_file,
							'size' => $size,
							'add_raw' => true
					);
				}
				$mailcontent['date'] = strtotime($mailcontent['headers']['DATE']);
			}
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
					$data_attachments[$key]['egw_data'] = egw_link::set_data($mailcontent['attachments'][$key]['mimeType'],
						'emailadmin_imapbase::getAttachmentAccount',array($icServerID, $mailbox, $uid, $attachment['partID'], $is_winmail, true),true);
				}
				unset($mailcontent['attachments'][$key]['add_raw']);
			}
		}
		
		// Check if the hook is registered
		if ($GLOBALS['egw']->hooks->hook_exists('mail_import',$app) == 0)
		{
			// Try to register hook
			if(!$GLOBALS['egw']->hooks->register_single_app_hook($app,'mail_import'))
			{
				throw new egw_exception_assertion_failed('Hook import_mail registration faild for '.$app.' app! Please, contact your system admin in order to clear cache and register hooks.');
			}
		}
		
		// Get the registered hook method of requested app for integration
		$hook = $GLOBALS['egw']->hooks->single(array('location' => 'mail_import'),$app);
		
		// Load translation for the app since the original URL
		// is from mail integration and only loads mail translation
		translation::add_app($app);
		
		// Execute import mail with provided content
		ExecMethod($hook['menuaction'],array (
			'addresses' => $data_addresses,
			'attachments' => $data_attachments,
			'message' => $data_message,
			'date' => $mailcontent['date'],
			'subject' => $mailcontent['subject'],
			'entry_id' => $app_entry_id
		));
	}
}

