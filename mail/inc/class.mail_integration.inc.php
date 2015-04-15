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
	 *					'egw_data'=> string),	// hash md5 id of an stored attachment in session
	 *			'message' => string
	 *			'date' => string
	 *			'subject' => string
	 *	)
	 *
	 * @param string $_app Integrated app name
	 * @param string $_to_emailAddress
	 * @param string $_subject mail subject
	 * @param string $_body mail message
	 * @param array $_attachments attachments
	 * @param string $_date
	 * @param string $_rawMail
	 * @throws egw_exception_assertion_failed
	 */
	public static function integrate ($_app='',$_to_emailAddress=false,$_subject=false,$_body=false,$_attachments=false,$_date=false,$_rawMail=null,$_icServerID=null)
	{
		// App name which is called for integration
		$app = !empty($_GET['app'])? $_GET['app']:$_app;
		
		// Set the date
		if (!$_date)
		{
			$time = time();
			$_date = egw_time::server2user($time->now,'ts');
		}
		
		// Integrate not yet saved mail
		if (empty($_GET['rowid']) && $_to_emailAddress && $app)
		{
			$sessionLocation = 'mail';
			$mailbox = base64_decode($_GET['mailbox']);
			
			$GLOBALS['egw_info']['flags']['currentapp'] = $_app;

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
			if (is_resource($_rawMail) && $GLOBALS['egw_info']['user']['preferences'][$sessionLocation]['saveAsOptions']==='add_raw')
			{
				$subject = mail_bo::adaptSubjectForImport($_subject);
				$attachment_file =tempnam($GLOBALS['egw_info']['server']['temp_dir'],$GLOBALS['egw_info']['flags']['currentapp']."_");
				$tmpfile = fopen($attachment_file,'w');
				fseek($_rawMail, 0, SEEK_SET);
				stream_copy_to_stream($_rawMail, $tmpfile);
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
		else if ($app)
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

				$mailcontent = mail_bo::get_mailcontent($mo,$uid,$partid,$mailbox,false,true,(!($GLOBALS['egw_info']['user']['preferences'][$sessionLocation]['saveAsOptions']==='text_only')));
				// this one adds the mail itself (as message/rfc822 (.eml) file) to the app as additional attachment
				// this is done to have a simple archive functionality (ToDo: opening .eml in email module)
				if ($GLOBALS['egw_info']['user']['preferences'][$sessionLocation]['saveAsOptions']==='add_raw')
				{
					$message = $mo->getMessageRawBody($uid, $partid,$mailbox);
					$headers = $mo->getMessageHeader($uid, $partid,true,false,$mailbox);
					$subject = mail_bo::adaptSubjectForImport($headers['SUBJECT']);
					$attachment_file =tempnam($GLOBALS['egw_info']['server']['temp_dir'],$GLOBALS['egw_info']['flags']['currentapp']."_");
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
					);
				}
				$mailcontent['date'] = strtotime($mailcontent['headers']['DATE']);
			}
		}
		else
		{
			egw_framework::window_close(lang('No app for integration is registered!'));
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
					'name' => $mailcontent['attachments'][$key]['filename'],
					'type' => $mailcontent['attachments'][$key]['type'],
					'egw_data' => egw_link::set_data($mailcontent['attachments'][$key]['mimeType'],'emailadmin_imapbase::getAttachmentAccount',
						array($icServerID, $mailbox, $uid, $attachment['partID'], $is_winmail, true), true)
				);
			}
		}
		
		
		// Get the registered hook method of requested app for integration
		$hook = $GLOBALS['egw']->hooks->single(array('location' => 'mail_import'),$app);
		
		// Execute import mail with provided content
		ExecMethod($hook['menuaction'],array (
			'addresses' => $data_addresses,
			'attachments' => $data_attachments,
			'message' => $data_message,
			'date' => $mailcontent['date'],
			'subject' => $mailcontent['subject']
		));
	}
}

