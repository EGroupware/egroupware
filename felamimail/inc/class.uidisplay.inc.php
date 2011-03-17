<?php
	/***************************************************************************\
	* eGroupWare - FeLaMiMail                                                   *
	* http://www.linux-at-work.de                                               *
	* http://www.phpgw.de                                                       *
	* http://www.egroupware.org                                                 *
	* Written by : Lars Kneschke [lkneschke@linux-at-work.de]                   *
	* maintained by Klaus Leithoff												*
	* -------------------------------------------------                         *
	* This program is free software; you can redistribute it and/or modify it   *
	* under the terms of the GNU General Public License as published by the     *
	* Free Software Foundation; either version 2 of the License, or (at your    *
	* option) any later version.                                                *
	\***************************************************************************/

	/**
	* copyright notice for the functions highlightQuotes and _countQuoteChars
	*
	* The Text:: class provides common methods for manipulating text.
	*
	* $Horde: horde/lib/Text.php,v 1.80 2003/09/16 23:06:15 jan Exp $
	*
	* Copyright 1999-2003 Jon Parise <jon@horde.org>
	*
	* See the enclosed file COPYING for license information (LGPL). If you
	* did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
	*
	*/

	/* $Id$ */

	class uidisplay
	{

		var $public_functions = array
		(
			'display'	=> True,
			'displayBody'	=> True,
			'displayHeader'	=> True,
			'displayImage'	=> True,
			'displayAttachments' => True,
			'printMessage'	=> True,
			'saveMessage'	=> True,
			'showHeader'	=> True,
			'getAttachment'	=> True,
		);

		var $icServerID=0;

		// the object storing the data about the incoming imap server
		var $icServer=0;

		// the non permanent id of the message
		var $id;

		// the permanent id of the message
		var $uid;
		var $bofelamimail;
		var $bopreferences;

		function uidisplay()
		{
			/* Having this defined in just one spot could help when changes need
			 * to be made to the pattern
			 * Make sure that the expression is evaluated case insensitively
			 *
			 * RFC2822 (and RFC822) defines the left side of an email address as (roughly):
			 *  1*atext *("." 1*atext)
			 * where atext is: a-zA-Z0-9!#$%&'*+-/=?^_`{|}~
			 *
			 * Here's pretty sophisticated IP matching:
			 * $IPMatch = '(2[0-5][0-9]|1?[0-9]{1,2})';
			 * $IPMatch = '\[?' . $IPMatch . '(\.' . $IPMatch . '){3}\]?';
			 */
			/* Here's enough: */
			global $IP_RegExp_Match, $Host_RegExp_Match, $Email_RegExp_Match;
			$IP_RegExp_Match = '\\[?[0-9]{1,3}(\\.[0-9]{1,3}){3}\\]?';
			$Host_RegExp_Match = '('.$IP_RegExp_Match.'|[0-9a-z]([-.]?[0-9a-z])*\\.[a-z][a-z]+)';
			#$atext = '([a-z0-9!#$&%*+/=?^_`{|}~-]|&amp;)';
			$atext = '([a-zA-Z0-9_\-\.])';
			$dot_atom = $atext.'+(\.'.$atext.'+)*';
			$Email_RegExp_Match = '~'.$dot_atom.'(%'.$Host_RegExp_Match.')?@'.$Host_RegExp_Match.'~i';

			$this->t 		= CreateObject('phpgwapi.Template',EGW_APP_TPL);
			$this->displayCharset   = $GLOBALS['egw']->translation->charset();
			$this->bofelamimail		= CreateObject('felamimail.bofelamimail',$this->displayCharset);
			$this->bopreferences	= $this->bofelamimail->bopreferences; //CreateObject('felamimail.bopreferences');

			$this->mailPreferences	= $this->bopreferences->getPreferences();

			$this->bofelamimail->openConnection($this->icServerID);

			$this->mailbox		= $this->bofelamimail->sessionData['mailbox'];
			if (!empty($_GET['mailbox'])) $this->mailbox  = base64_decode($_GET['mailbox']);

			$this->sort		= $this->bofelamimail->sessionData['sort'];

			if(isset($_GET['uid'])) {
				$this->uid	= (int)$_GET['uid'];
			}

			if(isset($_GET['id'])) {
				$this->id	= (int)$_GET['id'];
			}

			if(isset($this->id) && !isset($this->uid)) {
				if($uid = $this->bofelamimail->idToUid($this->mailbox, $this->id)) {
					$this->uid = $uid;
				}
			}

			if(isset($_GET['part'])) {
				$this->partID = (int)$_GET['part'];
			}

			$this->rowColor[0] = $GLOBALS['egw_info']["theme"]["bg01"];
			$this->rowColor[1] = $GLOBALS['egw_info']["theme"]["bg02"];
		}

		/**
		 * Parses a body and converts all found email addresses to clickable links.
		 *
		 * @param string body the body to process, by ref
		 * @return int the number of unique addresses found
		 */
		function parseEmail (&$body) {
			global $Email_RegExp_Match;
			$sbody     = $body;
			$addresses = array();
			$i = 0;
			/* Find all the email addresses in the body */
			// stop cold after 100 adresses, as this is very time consuming
			while(preg_match($Email_RegExp_Match, $sbody, $regs) && $i<=100) {
				//_debug_array($regs);
				$addresses[$regs[0]] = strtr($regs[0], array('&amp;' => '&'));
				$start = strpos($sbody, $regs[0]) + strlen($regs[0]);
				$sbody = substr($sbody, $start);
				$i++;
			}

			/* Replace each email address with a compose URL */
			$lmail='';
			if (is_array($addresses)) ksort($addresses);
			foreach ($addresses as $text => $email) {
				if ($lmail == $email) next($addresses);
				//echo __METHOD__.' Text:'.$text."#<br>";
				//echo $email."#<br>";
				$comp_uri = $this->makeComposeLink($email, $text);
				//echo __METHOD__.' Uri:'.$comp_uri.'#<br>';
				$body = str_replace($text, $comp_uri, $body);
				$lmail=$email;
			}

			/* Return number of unique addresses found */
			return count($addresses);
		}
		function parseHREF (&$body) {
			#echo __METHOD__."called<br>";
			$webserverURL   = $GLOBALS['egw_info']['server']['webserver_url'];
			$alnum            = 'a-z0-9';
			#$domain = "(http(s?):\/\/)*";
			#$domain            .= "([$alnum]([-$alnum]*[$alnum]+)?)";
			#$domain = "^(http|https|ftp)\://[a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,3}(:[a-zA-Z0-9]*)?/?([a-zA-Z0-9\-\._\?\,\'/\\\+&%\$#\=~])*[^\.\,\)\(\s]$ ";
			$domain = "(http(s?):\/\/)+([[:alpha:]][-[:alnum:]]*[[:alnum:]])(\.[[:alpha:]][-[:alnum:]]*[[:alpha:]])*(\.[[:alpha:]][-[:alnum:]]*[[:alpha:]])+";	
			#$dir = "(/[[:alpha:]][-[:alnum:]]*[[:alnum:]])*";
			#$trailingslash  = "(\/?)";
			#$page = "(/[[:alpha:]][-[:alnum:]]*\.[[:alpha:]]{3,5})?";
			#$getstring = "(\?([[:alnum:]][-_%[:alnum:]]*=[-_%[:alnum:]]+)
			#    (&([[:alnum:]][-_%[:alnum:]]*=[-_%[:alnum:]]+))*)?";
			#$pattern = "^".$domain.$dir.$trailingslash.$page.$getstring."$";
			$pattern = "~\<a href=\"".$domain.".*?\"~i";
			$sbody = $body;
			$i = 0;
			while(@preg_match($pattern, $sbody, $regs) && $i <=100) {
				//_debug_array($regs);
				$key=$regs[1].$regs[3].$regs[4].$regs[5];
				$addresses[$key] = $regs[1].$regs[3].$regs[4].$regs[5];
				$start = strpos($sbody, $regs[0]) + strlen($regs[0]);
				$sbody = substr($sbody, $start);
				$i++;
			}
			$llink='';
			//_debug_array($addresses);
			if (is_array($addresses)) ksort($addresses);
			foreach ((array)$addresses as $text => $link) {
				if (empty($link)) continue;
				if ($llink == $link) next($addresses);
				#echo $text."#<br>";
				#echo $link."#<br>\n";
				$comp_uri = "<a href=\"$webserverURL/redirect.php?go=".$link;
				$body = str_replace('<a href="'.$link, $comp_uri, $body);
				$llink=$link;
			}
			return count($addresses);
		}

		function makeComposeLink($email,$text)
		{
			if (!$email || $email == '' || $email == ' ') return '';
			if (!$text) $text = $email;
			//error_log( __METHOD__." email:".$email.'#<br>');
			//error_log( __METHOD__." text:".$text.'#<br>');
			// create links for email addresses
			$linkData = array
			(
				'menuaction'    => 'felamimail.uicompose.compose',
				'send_to'	=> base64_encode($email)
			);
			$link = $GLOBALS['egw']->link('/index.php',$linkData);
			//error_log(__METHOD__." link:".$link.'#<br>');
			return "<a href='#' onclick='egw_openWindowCentered(\"$link\",\"compose\",700,egw_getWindowOuterHeight());' ><font color=\"blue\">".$text."</font></a>";
		}

		function highlightQuotes($text, $level = 5)
		{
			// Use a global var since the class is called statically.
			$GLOBALS['_tmp_maxQuoteChars'] = 0;

			// Tack a newline onto the beginning of the string so that we
			// correctly highlight when the first character in the string
			// is a quote character.
			$text = "\n$text";

			preg_replace_callback("/^\s*((&gt;\s?)+)/m", array(&$this, '_countQuoteChars'), $text);

			// Go through each level of quote block and put the
			// appropriate style around it. Important to work downwards so
			// blocks with fewer quote chars aren't matched until their
			// turn.
			for ($i = $GLOBALS['_tmp_maxQuoteChars']; $i > 0; $i--)
			{
				$text = preg_replace(
				// Finds a quote block across multiple newlines.
				"/(\n)( *(&gt;\s?)\{$i}(?! ?&gt;).*?)(\n|$)(?! *(&gt; ?)\{$i})/s",
				'\1<span class="quoted' . ((($i - 1) % $level) + 1) . '">\2</span>\4',$text);
			}

			/* Unset the global variable. */
			unset($GLOBALS['_tmp_maxQuoteChars']);

			/* Remove the leading newline we added above. */
			return substr($text, 1);
		}

		function _countQuoteChars($matches)
		{
			$num = count(preg_split('/&gt;\s?/', $matches[1])) - 1;
			if ($num > $GLOBALS['_tmp_maxQuoteChars'])
			{
				$GLOBALS['_tmp_maxQuoteChars'] = $num;
			}
		}

		function display()
		{
			$partID		= $_GET['part'];
			if (!empty($_GET['mailbox'])) $this->mailbox  = base64_decode($_GET['mailbox']);

			//$transformdate	=& CreateObject('felamimail.transformdate');
			//$htmlFilter	=& CreateObject('felamimail.htmlfilter');
			$uiWidgets	= CreateObject('felamimail.uiwidgets');
			// (regis) seems to be necessary to reopen...
			$this->bofelamimail->reopen($this->mailbox);
			// retrieve the flags of the message, before touching it.
			if (!empty($this->uid)) $flags = $this->bofelamimail->getFlags($this->uid);
			#print "$this->mailbox, $this->uid, $partID<br>";
			$headers	= $this->bofelamimail->getMessageHeader($this->uid, $partID);
			if (PEAR::isError($headers)) {
				print lang("ERROR: Message could not be displayed.")."<br>";
				print "In Mailbox: $this->mailbox, with ID: $this->uid, and PartID: $partID<br>";
				print $headers->message."<br>";
				_debug_array($headers->backtrace[0]);
				exit;
			}
			#_debug_array($headers);exit;
			$rawheaders	= $this->bofelamimail->getMessageRawHeader($this->uid, $partID);
			#_debug_array($rawheaders);exit;
			$attachments	= $this->bofelamimail->getMessageAttachments($this->uid, $partID);
			#_debug_array($attachments); exit;
			$envelope	= $this->bofelamimail->getMessageEnvelope($this->uid, $partID,true);
			#_debug_array($envelope); exit;
			// if not using iFrames, we need to retrieve the messageBody here
			// by now this is a fixed value and controls the use/loading of the template and how the vars are set.
			// Problem is: the iFrame Layout provides the scrollbars.
			#$bodyParts  = $this->bofelamimail->getMessageBody($this->uid,'',$partID);
			#_debug_array($bodyParts); exit;
			#_debug_array($this->uid);
			#_debug_array($this->bofelamimail->getFlags($this->uid)); #exit;
			// flag the message as read/seen (if not already flagged)
			if (!empty($this->uid) && strpos( array2string($flags),'Seen')===false) $this->bofelamimail->flagMessages('read', $this->uid);

			$nextMessage	= $this->bofelamimail->getNextMessage($this->mailbox, $this->uid);
			$webserverURL	= $GLOBALS['egw_info']['server']['webserver_url'];

			$nonDisplayAbleCharacters = array('[\016]','[\017]',
					'[\020]','[\021]','[\022]','[\023]','[\024]','[\025]','[\026]','[\027]',
					'[\030]','[\031]','[\032]','[\033]','[\034]','[\035]','[\036]','[\037]');

			#print "<pre>";print_r($rawheaders);print"</pre>";exit;

			// add line breaks to $rawheaders
			$newRawHeaders = explode("\n",$rawheaders);
			reset($newRawHeaders);

			if(isset($headers['ORGANIZATION'])) {
				$organization = $this->bofelamimail->decode_header(trim($headers['ORGANIZATION']));
			}

			if ( isset($headers['DISPOSITION-NOTIFICATION-TO']) ) {
				$sent_not = $this->bofelamimail->decode_header(trim($headers['DISPOSITION-NOTIFICATION-TO']));
			} else if ( isset($headers['RETURN-RECEIPT-TO']) ) {
				$sent_not = $this->bofelamimail->decode_header(trim($headers['RETURN-RECEIPT-TO']));
			} else if ( isset($headers['X-CONFIRM-READING-TO']) ) {
				$sent_not = $this->bofelamimail->decode_header(trim($headers['X-CONFIRM-READING-TO']));
			} else $sent_not = "";

			// reset $rawheaders
			$rawheaders 	= "";
			// create it new, with good line breaks
			reset($newRawHeaders);
			while(list($key,$value) = @each($newRawHeaders)) {
				$rawheaders .= wordwrap($value, 90, "\n     ");
			}

			$this->display_app_header();
			if(!isset($_GET['printable'])) {
				$this->t->set_file(array("displayMsg" => "view_message.tpl"));
			} else {
				$this->t->set_file(array("displayMsg" => "view_message_printable.tpl"));
				$this->t->set_var('charset',$GLOBALS['egw']->translation->charset());
			}
			// only notify when requested, notify flag (MDNSent/MDNnotSent) not set, and message not already seen (some servers do not support the MDNSent/MDNnotSent flag)
			if ( $sent_not != "" && $this->bofelamimail->getNotifyFlags($this->uid) === null && strpos( array2string($flags),'Seen')===false) {
				$this->t->set_var('sentNotify','sendNotify("'.$this->uid.'");');
				$this->t->set_var('lang_sendnotify',lang('The message sender has requested a response to indicate that you have read this message. Would you like to send a receipt?'));
			} else {
				$this->t->set_var('sentNotify','');
				$this->t->set_var('lang_sendnotify','');
			}

			$this->t->set_block('displayMsg','message_main');
			$this->t->set_block('displayMsg','message_main_attachment');
			$this->t->set_block('displayMsg','message_header');
			$this->t->set_block('displayMsg','message_raw_header');
			$this->t->set_block('displayMsg','message_navbar');
			$this->t->set_block('displayMsg','message_onbehalfof');
			$this->t->set_block('displayMsg','message_cc');
			$this->t->set_block('displayMsg','message_bcc');
			$this->t->set_block('displayMsg','message_attachement_row');
			$this->t->set_block('displayMsg','previous_message_block');
			$this->t->set_block('displayMsg','next_message_block');
			//$this->t->set_block('displayMsg','message_org');

			$this->t->egroupware_hack = False;

			$this->translate();

			// navBar buttons
			$headerData = array('uid'=>$this->uid);
			if($partID != '') {
				$headerData['partid'] = $partID;
			}
			$this->t->set_var('navbarButtonsLeft',$uiWidgets->displayMessageActions($headerData, $this->mailbox, $this->icServer));

			$navbarButtons = '';
			$navbarImages  = array();
			#_debug_array($nextMessage); exit;

			if($nextMessage['previous']) {
				$linkData = array (
					'menuaction'	=> 'felamimail.uidisplay.display',
					'showHeader'	=> 'false',
					'uid'		=> $nextMessage['previous'],
					'mailbox'	=> base64_encode($this->mailbox)
				);
				$previousURL = $GLOBALS['egw']->link('/index.php',$linkData);
				$previousURL = "goToMessage('$previousURL')";
				$navbarImages['up.button']	= array(
					'action'	=> $previousURL,
					'tooltip'	=> lang('previous message'),
				);
			} else {
				$previousURL = '#';
				$navbarImages['up.grey']  = array(
					'action'    => $previousURL,
					'tooltip'   => lang('previous message'),
				);
			}

			if($nextMessage['next']) {
				$linkData = array (
					'menuaction'	=> 'felamimail.uidisplay.display',
					'showHeader'	=> 'false',
					'uid'		=> $nextMessage['next'],
					'mailbox'	=> base64_encode($this->mailbox)
				);
				$nextURL = $GLOBALS['egw']->link('/index.php',$linkData);
				$nextURL = "goToMessage('$nextURL')";
				$navbarImages['down.button']	= array(
					'action'	=> $nextURL,
					'tooltip'	=> lang('next message'),
				);
			} else {
				$nextURL = '#';
				$navbarImages['down.grey']    = array(
					#'action'    => $nextURL,
					'tooltip'   => lang('next message'),
				);
			}


			foreach($navbarImages as $buttonName => $buttonData) {
				$navbarButtons .= $uiWidgets->navbarButton($buttonName, $buttonData['action'], $buttonData['tooltip'], 'right');
			}

			$this->t->set_var('navbarButtonsRight',$navbarButtons);

			$this->t->parse('navbar','message_navbar',True);

			// navbar end
			// header
			// sent by a mailinglist??
			// parse the from header
			if($envelope['FROM'][0] != $envelope['SENDER'][0]) {
				$senderAddress = self::emailAddressToHTML($envelope['SENDER'],'',false,true,false);
				$fromAddress   = self::emailAddressToHTML($envelope['FROM'], $organization,false,true,false);
				$this->t->set_var("from_data",$senderAddress);
				$this->t->set_var("onbehalfof_data",$fromAddress);
				$this->t->parse('on_behalf_of_part','message_onbehalfof',True);
			} else {
				$fromAddress   = self::emailAddressToHTML($envelope['FROM'], $organization,false,true,false);
				$this->t->set_var("from_data", $fromAddress);
				$this->t->set_var('on_behalf_of_part','');
			}

			// parse the to header
			$toAddress = self::emailAddressToHTML($envelope['TO'],'',false,true,false);
			$this->t->set_var("to_data",$toAddress);

			// parse the cc header
			if(count($envelope['CC'])) {
				$ccAddress = self::emailAddressToHTML($envelope['CC'],'',false,true,false);
				$this->t->set_var("cc_data",$ccAddress);
				$this->t->parse('cc_data_part','message_cc',True);
			} else {
				$this->t->set_var("cc_data_part",'');
			}

			// parse the bcc header
			if(count($envelope['BCC'])) {
				$bccAddress = self::emailAddressToHTML($envelope['BCC'],'',false,true,false);
				$this->t->set_var("bcc_data",$bccAddress);
				$this->t->parse('bcc_data_part','message_bcc',True);
			} else {
				$this->t->set_var("bcc_data_part",'');
			}
			$this->t->set_var("date_received",
				@htmlspecialchars(bofelamimail::_strtotime($headers['DATE'],$GLOBALS['egw_info']['user']['preferences']['common']['dateformat'],true).' - '.bofelamimail::_strtotime($headers['DATE'],($GLOBALS['egw_info']['user']['preferences']['common']['timeformat']==12?'h:i:s a':'H:i:s'),true),
				ENT_QUOTES,$this->displayCharset));
			//echo 'Envelope:'.preg_replace($nonDisplayAbleCharacters,'',$envelope['SUBJECT']).'#0<br>';
			$subject = bofelamimail::htmlspecialchars($this->bofelamimail->decode_subject(preg_replace($nonDisplayAbleCharacters,'',$envelope['SUBJECT']),false),
                $this->displayCharset);
			$this->t->set_var("subject_data",$subject);

			$this->t->parse("header","message_header",True);

			$this->t->set_var("rawheader",@htmlentities(preg_replace($nonDisplayAbleCharacters,'',$rawheaders),ENT_QUOTES,$this->displayCharset));

			$linkData = array (
					'menuaction'	=> 'felamimail.uidisplay.displayBody',
					'uid'		=> $this->uid,
					'part'		=> $partID,
					'mailbox'	=>  base64_encode($this->mailbox)
				);
			$this->t->set_var('url_displayBody', $GLOBALS['egw']->link('/index.php',$linkData));

			// if browser supports data uri: ie<8 does NOT and ie>=8 does NOT support html as content :-(
			// --> use it to send the mail as data uri
			if (!isset($_GET['printable']))
			{
				$mailData = $this->get_load_email_data($this->uid, $partID);

				$this->t->set_var('url_displayBody', $mailData['src']."\" onload=\"".$mailData['onload']);
				$this->t->set_var('mail_dataScript', $mailData['script']);
			}

			// attachments
			if(is_array($attachments) && count($attachments) > 0) {
				$this->t->set_var('attachment_count',count($attachments));
			} else {
				$this->t->set_var('attachment_count','0');
			}

			if (is_array($attachments) && count($attachments) > 0) {
				$this->t->set_var('row_color',$this->rowColor[0]);
				$this->t->set_var('name',lang('name'));
				$this->t->set_var('type',lang('type'));
				$this->t->set_var('size',lang('size'));
				$this->t->set_var('url_img_save',html::image('felamimail','fileexport', lang('save')));
				$url_img_vfs = html::image('filemanager','navbar', lang('Filemanager'), ' height="16"');
				$url_img_vfs_save_all = html::image('felamimail','save_all', lang('Save all'));
				#$this->t->parse('attachment_rows','attachment_row_bold',True);

				$detectedCharSet=$charset2use=$this->displayCharset;
				foreach ($attachments as $key => $value)
				{
					#$detectedCharSet = mb_detect_encoding($value['name'].'a',strtoupper($this->displayCharset).",UTF-8, ISO-8559-1");
					if (function_exists('mb_convert_variables')) mb_convert_variables("UTF-8","ISO-8559-1",$value['name']); # iso 2 UTF8
					//if (mb_convert_variables("ISO-8859-1","UTF-8",$value['name'])){echo "Juhu utf8 2 ISO\n";};
					//echo $value['name']."\n";
					$filename=htmlentities($value['name'], ENT_QUOTES, $detectedCharSet);

					$this->t->set_var('row_color',$this->rowColor[($key+1)%2]);
					$this->t->set_var('filename',($value['name'] ? ( $filename ? $filename : $value['name'] ) : lang('(no subject)')));
					$this->t->set_var('mimetype',mime_magic::mime2label($value['mimeType']));
					$this->t->set_var('size',egw_vfs::hsize($value['size']));
					$this->t->set_var('attachment_number',$key);

					switch(strtoupper($value['mimeType']))
					{
						case 'MESSAGE/RFC822':
							$linkData = array
							(
								'menuaction'	=> 'felamimail.uidisplay.display',
								'uid'		=> $this->uid,
								'part'		=> $value['partID'],
								'mailbox'	=> base64_encode($this->mailbox),
								'is_winmail'    => $value['is_winmail']
							);
							$windowName = 'displayMessage_'. $this->uid.'_'.$value['partID'];
							$linkView = "egw_openWindowCentered('".$GLOBALS['egw']->link('/index.php',$linkData)."','$windowName',700,egw_getWindowOuterHeight());";
							break;
						case 'IMAGE/JPEG':
						case 'IMAGE/PNG':
						case 'IMAGE/GIF':
						case 'IMAGE/BMP':
						case 'APPLICATION/PDF':
						case 'TEXT/PLAIN':
						case 'TEXT/HTML':
						case 'TEXT/CALENDAR':
						case 'TEXT/X-VCARD':
							$linkData = array
							(
								'menuaction'	=> 'felamimail.uidisplay.getAttachment',
								'uid'		=> $this->uid,
								'part'		=> $value['partID'],
								'is_winmail'    => $value['is_winmail'],
								'mailbox'   => base64_encode($this->mailbox),
							);
							$windowName = 'displayAttachment_'. $this->uid;
							$reg = '800x600';
							// handle calendar/vcard
							if (strtoupper($value['mimeType'])=='TEXT/CALENDAR') 
							{
								$windowName = 'displayEvent_'. $this->uid;
								$reg2 = egw_link::get_registry('calendar','view_popup');
							}
							if (strtoupper($value['mimeType'])=='TEXT/X-VCARD')
							{
								$windowName = 'displayContact_'. $this->uid;
								$reg2 = egw_link::get_registry('addressbook','add_popup');
							}
							// apply to action
							list($width,$height) = explode('x',(!empty($reg2) ? $reg2 : $reg));
							$linkView = "egw_openWindowCentered('".$GLOBALS['egw']->link('/index.php',$linkData)."','$windowName',$width,$height);";
							break;
						default:
							$linkData = array
							(
								'menuaction'	=> 'felamimail.uidisplay.getAttachment',
								'uid'		=> $this->uid,
								'part'		=> $value['partID'],
								'is_winmail'    => $value['is_winmail'],
								'mailbox'   => base64_encode($this->mailbox),
							);
							$linkView = "window.location.href = '".$GLOBALS['egw']->link('/index.php',$linkData)."';";
							break;
					}
					$this->t->set_var("link_view",$linkView);
					$this->t->set_var("target",$target);

					$linkData = array
					(
						'menuaction'	=> 'felamimail.uidisplay.getAttachment',
						'mode'		=> 'save',
						'uid'		=> $this->uid,
						'part'		=> $value['partID'],
						'is_winmail'    => $value['is_winmail'],
						'mailbox'   => base64_encode($this->mailbox),
					);
					$this->t->set_var("link_save",$GLOBALS['egw']->link('/index.php',$linkData));

					if ($GLOBALS['egw_info']['user']['apps']['filemanager'])
					{
						$link_vfs_save = egw::link('/index.php',array(
							'menuaction' => 'filemanager.filemanager_select.select',
							'mode' => 'saveas',
							'name' => $value['name'],
							'mime' => strtolower($value['mimeType']),
							'method' => 'felamimail.uidisplay.vfsSaveAttachment',
							'id' => $this->mailbox.'::'.$this->uid.'::'.$value['partID'].'::'.$value['is_winmail'],
							'label' => lang('Save'),
						));
						$vfs_save = "<a href='#' onclick=\"egw_openWindowCentered('$link_vfs_save','vfs_save_attachment','640','570',window.outerWidth/2,window.outerHeight/2); return false;\">$url_img_vfs</a>";
						// add save-all icon for first attachment
						if (!$key && count($attachments) > 1)
						{
							foreach ($attachments as $key => $value)
							{
								$ids["id[$key]"] = $this->mailbox.'::'.$this->uid.'::'.$value['partID'].'::'.$value['is_winmail'].'::'.$value['name'];
							}
							$link_vfs_save = egw::link('/index.php',array(
								'menuaction' => 'filemanager.filemanager_select.select',
								'mode' => 'select-dir',
								'method' => 'felamimail.uidisplay.vfsSaveAttachment',
								'label' => lang('Save all'),
							)+$ids);
							$vfs_save .= "\n<a href='#' onclick=\"egw_openWindowCentered('$link_vfs_save','vfs_save_attachment','640','530',window.outerWidth/2,window.outerHeight/2); return false;\">$url_img_vfs_save_all</a>";
						}
						$this->t->set_var('vfs_save',$vfs_save);
					}
					else
					{
						$this->t->set_var('vfs_save','');
					}
					$this->t->parse('attachment_rows','message_attachement_row',True);
				}
			} else {
				$this->t->set_var('attachment_rows','');
			}

			#$this->t->pparse("out","message_attachment_rows");

			// print it out
			if(is_array($attachments) && count($attachments) > 0) {
				$this->t->pparse('out','message_main_attachment');
			} else {
				$this->t->pparse('out','message_main');
			}

		}

		function displayBody()
		{
			$partID		= $_GET['part'];
			if (empty($this->uid) && !empty($_GET['uid']) ) $this->uid = 9247;//$_GET['uid'];
			if (!empty($_GET['mailbox'])) $this->mailbox  = base64_decode($_GET['mailbox']);

			$this->bofelamimail->reopen($this->mailbox);
			$bodyParts	= $this->bofelamimail->getMessageBody($this->uid,'',$partID);
			$this->bofelamimail->closeConnection();

			$this->display_app_header();
			$this->showBody($this->getdisplayableBody($bodyParts), true);
		}

		function showBody(&$body, $print=true)
		{
			$BeginBody = '<style type="text/css">
				body,html {
			        height:100%;
			        width:100%;
			        padding:0px;
			        margin:0px;
			}
			.td_display {
			        font-family: Verdana, Arial, Helvetica, sans-serif;
			        font-size: 110%;
			        background-color: #FFFFFF;
			}
			</style>
			<div style="height:100%;width:100%; background-color:white; padding:0px; margin:0px;"><table width="100%" style="table-layout:fixed"><tr><td class="td_display">';

            $EndBody = '</td></tr></table>';
			$EndBody .= "</body></html>";
			if ($print)	{
				print $BeginBody. $body .$EndBody;
			} else {
				return $BeginBody. $body .$EndBody;
			}
		}

		function displayHeader()
		{
			$partID		= $_GET['part'];
			if (!empty($_GET['mailbox'])) $this->mailbox  = base64_decode($_GET['mailbox']);

			//$transformdate	=& CreateObject('felamimail.transformdate');
			//$htmlFilter	=& CreateObject('felamimail.htmlfilter');
			//$uiWidgets	=& CreateObject('felamimail.uiwidgets');
			// (regis) seems to be necessary to reopen...
			$this->bofelamimail->reopen($this->mailbox);
			#$headers	= $this->bofelamimail->getMessageHeader($this->mailbox, $this->uid, $partID);
			$rawheaders	= $this->bofelamimail->getMessageRawHeader($this->uid, $partID);

			$webserverURL	= $GLOBALS['egw_info']['server']['webserver_url'];

			#$nonDisplayAbleCharacters = array('[\016]','[\017]',
			#		'[\020]','[\021]','[\022]','[\023]','[\024]','[\025]','[\026]','[\027]',
			#		'[\030]','[\031]','[\032]','[\033]','[\034]','[\035]','[\036]','[\037]');

			#print "<pre>";print_r($rawheaders);print"</pre>";exit;

			// add line breaks to $rawheaders
			$newRawHeaders = explode("\n",$rawheaders);
			reset($newRawHeaders);

			// reset $rawheaders
			$rawheaders 	= "";
			// create it new, with good line breaks
			reset($newRawHeaders);
			while(list($key,$value) = @each($newRawHeaders)) {
				$rawheaders .= wordwrap($value, 90, "\n     ");
			}

			$this->bofelamimail->closeConnection();

			header('Content-type: text/html; charset=iso-8859-1');
			print '<pre>'. htmlspecialchars($rawheaders, ENT_NOQUOTES, 'iso-8859-1') .'</pre>';

		}

		function displayAttachments()
		{
			$partID		= $_GET['part'];
			if (!empty($_GET['mailbox'])) $this->mailbox  = base64_decode($_GET['mailbox']);
            $nonDisplayAbleCharacters = array('[\016]','[\017]',
                    '[\020]','[\021]','[\022]','[\023]','[\024]','[\025]','[\026]','[\027]',
                    '[\030]','[\031]','[\032]','[\033]','[\034]','[\035]','[\036]','[\037]');

			//$transformdate	=& CreateObject('felamimail.transformdate');
			//$htmlFilter	=& CreateObject('felamimail.htmlfilter');
			// (regis) seems to be necessary to reopen...
			$this->bofelamimail->reopen($this->mailbox);
			$headers	= $this->bofelamimail->getMessageHeader($this->uid, $partID);
			$envelope   = $this->bofelamimail->getMessageEnvelope($this->uid, $partID,true);
			if (PEAR::isError($headers)) {
				print lang("ERROR: Message could not be displayed.")."<br>";
				print "In Mailbox: $this->mailbox, with ID: $this->uid, and PartID: $partID<br>";
				print $headers->message."<br>";
				_debug_array($headers->backtrace[0]);
				exit;
			}
			$attachments	= $this->bofelamimail->getMessageAttachments($this->uid, $partID);
			#_debug_array($attachments); exit;

			$this->display_app_header();
			$this->t->set_file(array("displayMsg" => "view_attachments.tpl"));
			$this->t->set_var('charset',$GLOBALS['egw']->translation->charset());
			$this->t->set_block('displayMsg','message_main_attachment');
			$this->t->set_block('displayMsg','message_attachement_row');
			$this->bofelamimail->closeConnection();

			$this->t->egroupware_hack = False;

			$this->translate();
			$subject = bofelamimail::htmlspecialchars($this->bofelamimail->decode_subject(preg_replace($nonDisplayAbleCharacters,'',$envelope['SUBJECT']),false),
                $this->displayCharset);
            $this->t->set_var("subject_data",$subject);

			// attachments
			if(is_array($attachments) && count($attachments) > 0) {
				$this->t->set_var('attachment_count',count($attachments));
			} else {
				$this->t->set_var('attachment_count','0');
			}

			if (is_array($attachments) && count($attachments) > 0) {
				$this->t->set_var('row_color',$this->rowColor[0]);
				$this->t->set_var('name',lang('name'));
				$this->t->set_var('type',lang('type'));
				$this->t->set_var('size',lang('size'));
				$this->t->set_var('url_img_save',html::image('felamimail','fileexport', lang('save')));
				$url_img_vfs = html::image('filemanager','navbar', lang('Filemanager'), ' height="16"');
				$url_img_vfs_save_all = html::image('felamimail','save_all', lang('Save all'));
				#$this->t->parse('attachment_rows','attachment_row_bold',True);

				$detectedCharSet=$charset2use=$this->displayCharset;
				foreach ($attachments as $key => $value)
				{
					#$detectedCharSet = mb_detect_encoding($value['name'].'a',strtoupper($this->displayCharset).",UTF-8, ISO-8559-1");
					if (function_exists('mb_convert_variables')) mb_convert_variables("UTF-8","ISO-8559-1",$value['name']); # iso 2 UTF8
					//if (mb_convert_variables("ISO-8859-1","UTF-8",$value['name'])){echo "Juhu utf8 2 ISO\n";};
					//echo $value['name']."\n";
					$filename=htmlentities($value['name'], ENT_QUOTES, $detectedCharSet);

					$this->t->set_var('row_color',$this->rowColor[($key+1)%2]);
					$this->t->set_var('filename',($value['name'] ? ( $filename ? $filename : $value['name'] ) : lang('(no subject)')));
					$this->t->set_var('mimetype',mime_magic::mime2label($value['mimeType']));
					$this->t->set_var('size',egw_vfs::hsize($value['size']));
					$this->t->set_var('attachment_number',$key);

					switch(strtoupper($value['mimeType']))
					{
						case 'MESSAGE/RFC822':
							$linkData = array
							(
								'menuaction'	=> 'felamimail.uidisplay.display',
								'uid'		=> $this->uid,
								'part'		=> $value['partID'],
								'mailbox'	=> base64_encode($this->mailbox),
								'is_winmail'    => $value['is_winmail']
							);
							$windowName = 'displayMessage_'. $this->uid.'_'.$value['partID'];
							$linkView = "egw_openWindowCentered('".$GLOBALS['egw']->link('/index.php',$linkData)."','$windowName',700,screen.availHeight-50);";
							break;
						case 'IMAGE/JPEG':
						case 'IMAGE/PNG':
						case 'IMAGE/GIF':
						case 'IMAGE/BMP':
						case 'APPLICATION/PDF':
						case 'TEXT/PLAIN':
						case 'TEXT/HTML':
						case 'TEXT/CALENDAR':
						case 'TEXT/X-VCALENDAR':
						case 'TEXT/X-VCARD':
							$linkData = array
							(
								'menuaction'	=> 'felamimail.uidisplay.getAttachment',
								'uid'		=> $this->uid,
								'part'		=> $value['partID'],
								'is_winmail'    => $value['is_winmail'],
								'mailbox'   => base64_encode($this->mailbox),
							);
							$windowName = 'displayAttachment_'. $this->uid;
							$reg = '800x600';
							// handle calendar/vcard
							if (strtoupper($value['mimeType'])=='TEXT/CALENDAR' || strtoupper($value['mimeType'])=='TEXT/X-VCALENDAR') 
							{
								$windowName = 'displayEvent_'. $this->uid;
								$reg2 = egw_link::get_registry('calendar','view_popup');
							}
							if (strtoupper($value['mimeType'])=='TEXT/X-VCARD')
							{
								$windowName = 'displayContact_'. $this->uid;
								$reg2 = egw_link::get_registry('addressbook','add_popup');
							}
							// apply to action
							list($width,$height) = explode('x',(!empty($reg2) ? $reg2 : $reg));
							$linkView = "egw_openWindowCentered('".$GLOBALS['egw']->link('/index.php',$linkData)."','$windowName',$width,$height);";
							break;
						default:
							$linkData = array
							(
								'menuaction'	=> 'felamimail.uidisplay.getAttachment',
								'uid'		=> $this->uid,
								'part'		=> $value['partID'],
								'is_winmail'    => $value['is_winmail'],
								'mailbox'   => base64_encode($this->mailbox),
							);
							$linkView = "window.location.href = '".$GLOBALS['egw']->link('/index.php',$linkData)."';";
							break;
					}
					$this->t->set_var("link_view",$linkView);
					$this->t->set_var("target",$target);

					$linkData = array
					(
						'menuaction'	=> 'felamimail.uidisplay.getAttachment',
						'mode'		=> 'save',
						'uid'		=> $this->uid,
						'part'		=> $value['partID'],
						'is_winmail'    => $value['is_winmail'],
						'mailbox'   => base64_encode($this->mailbox),
					);
					$this->t->set_var("link_save",$GLOBALS['egw']->link('/index.php',$linkData));

					if ($GLOBALS['egw_info']['user']['apps']['filemanager'])
					{
						$link_vfs_save = egw::link('/index.php',array(
							'menuaction' => 'filemanager.filemanager_select.select',
							'mode' => 'saveas',
							'name' => $value['name'],
							'mime' => strtolower($value['mimeType']),
							'method' => 'felamimail.uidisplay.vfsSaveAttachment',
							'id' => $this->mailbox.'::'.$this->uid.'::'.$value['partID'].'::'.$value['is_winmail'],
							'label' => lang('Save'),
						));
						$vfs_save = "<a href='#' onclick=\"egw_openWindowCentered('$link_vfs_save','vfs_save_attachment','640','570',window.outerWidth/2,window.outerHeight/2); return false;\">$url_img_vfs</a>";
						// add save-all icon for first attachment
						if (!$key && count($attachments) > 1)
						{
							foreach ($attachments as $key => $value)
							{
								$ids["id[$key]"] = $this->mailbox.'::'.$this->uid.'::'.$value['partID'].'::'.$value['is_winmail'].'::'.$value['name'];
							}
							$link_vfs_save = egw::link('/index.php',array(
								'menuaction' => 'filemanager.filemanager_select.select',
								'mode' => 'select-dir',
								'method' => 'felamimail.uidisplay.vfsSaveAttachment',
								'label' => lang('Save all'),
							)+$ids);
							$vfs_save .= "\n<a href='#' onclick=\"egw_openWindowCentered('$link_vfs_save','vfs_save_attachment','640','530',window.outerWidth/2,window.outerHeight/2); return false;\">$url_img_vfs_save_all</a>";
						}
						$this->t->set_var('vfs_save',$vfs_save);
					}
					else
					{
						$this->t->set_var('vfs_save','');
					}
					$this->t->parse('attachment_rows','message_attachement_row',True);
				}
			} else {
				$this->t->set_var('attachment_rows','');
			}

			$this->t->pparse('out','message_main_attachment');

		}

		function displayImage()
		{
			$cid	= base64_decode($_GET['cid']);
			$partID = urldecode($_GET['partID']);
			if (!empty($_GET['mailbox'])) $this->mailbox  = base64_decode($_GET['mailbox']);

			$this->bofelamimail->reopen($this->mailbox);

			$attachment 	= $this->bofelamimail->getAttachmentByCID($this->uid, $cid, $partID);

			$this->bofelamimail->closeConnection();

			$GLOBALS['egw']->session->commit_session();

			if(is_array($attachment)) {
				//error_log("Content-Type: ".$attachment['type']."; name=\"". $attachment['filename'] ."\"");
				header ("Content-Type: ". strtolower($attachment['type']) ."; name=\"". $attachment['filename'] ."\"");
				header ('Content-Disposition: inline; filename="'. $attachment['filename'] .'"');
				header("Expires: 0");
				// the next headers are for IE and SSL
				header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
				header("Pragma: public");

				echo trim($attachment['attachment']);
				exit;
			}

			$GLOBALS['egw']->common->egw_exit();

			exit;
		}

		function display_app_header($printing = NULL)
		{
			if ($_GET['menuaction'] != 'felamimail.uidisplay.printMessage' &&
				$_GET['menuaction'] != 'felamimail.uidisplay.displayBody' && 
				$_GET['menuaction'] != 'felamimail.uidisplay.displayAttachments' &&
				empty($printing)) 
			{
				$GLOBALS['egw']->js->validate_file('tabs','tabs');
				$GLOBALS['egw']->js->validate_file('jscode','view_message','felamimail');
				$GLOBALS['egw']->js->set_onload('javascript:initAll();');
			}

			if(($_GET['menuaction'] == 'felamimail.uidisplay.printMessage') || (!empty($printing) && $printing == 1)) {
				$GLOBALS['egw']->js->set_onload('javascript:updateTitle();javascript:window.print();');
			}

			if($_GET['menuaction'] == 'felamimail.uidisplay.printMessage' || (!empty($printing) && $printing == 1) ||
				$_GET['menuaction'] == 'felamimail.uidisplay.displayBody' ||
				$_GET['menuaction'] == 'felamimail.uidisplay.displayAttachments' ) {
				$GLOBALS['egw_info']['flags']['nofooter'] = true;
			}
			$GLOBALS['egw_info']['flags']['include_xajax'] = True;
			common::egw_header();
		}

		static function get_email_header()
		{
			return '
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>
	<head>
		<meta http-equiv="Content-type" content="text/html;charset=UTF-8" />
		<style>
			body, td, textarea {
				font-family: Verdana, Arial, Helvetica,sans-serif;
				font-size: 11px;
			}
		</style>
	</head>
	<body>
';
		}

		function get_load_email_data($uid, $partID)
		{
			$bodyParts	= $this->bofelamimail->getMessageBody($uid, '', $partID);

			// Compose the content of the frame
			$frameHtml =
				$this->get_email_header().
				$this->showBody($this->getdisplayableBody($bodyParts), false);

			// Calculate the hash of that E-Mail for function identification
			$hash = md5($frameHtml);

			// The JS function name consists of a prefix and the hash suffix
			$funcname = "load_email_$hash";

			// Compose the script code
			$script =
"<script>
	var email_content_$hash = ".json_encode($frameHtml).";
	function $funcname(_tar)
	{
		if (_tar && typeof _tar.contentWindow != \"undefined\" &&
		    typeof _tar.contentWindow.egw_instant_load != \"undefined\")
		{
			_tar.setAttribute(\"scrolling\", \"no\"); // Workaround for FF 3.5
			_tar.contentWindow.egw_instant_load(email_content_$hash);
			_tar.setAttribute(\"scrolling\", \"auto\");
		}
	}
</script>";

			// Compose the code for the onload event
			$onload = "if (typeof $funcname != 'undefined'){ $funcname(this); this.onload = function() {return false;}}";

			// Return all the stuff
			return array(
				"script" => $script,
				"onload" => $onload,
				"src" => egw::link("/phpgwapi/js/egw_instant_load.html")
			);
		}

		static function emailAddressToHTML($_emailAddress, $_organisation='', $allwaysShowMailAddress=false, $showAddToAdrdessbookLink=true, $decode=true) {
			//_debug_array($_emailAddress);
			// create some nice formated HTML for senderaddress
			#if($_emailAddress['EMAIL'] == 'undisclosed-recipients: ;')
			#	return $_emailAddress['EMAIL'];

			#$addressData = imap_rfc822_parse_adrlist
			#		($this->bofelamimail->decode_header($_emailAddress),'');
			if(is_array($_emailAddress)) {
				$senderAddress = '';
				foreach($_emailAddress as $addressData) {
					#_debug_array($addressData);
					if($addressData['MAILBOX_NAME'] == 'NIL') {
						continue;
					}

					if(!empty($senderAddress)) $senderAddress .= ', ';

					if(strtolower($addressData['MAILBOX_NAME']) == 'undisclosed-recipients') {
						$senderAddress .= 'undisclosed-recipients';
						continue;
					}
					if($addressData['PERSONAL_NAME'] != 'NIL') {
						$newSenderAddressORG = $newSenderAddress = $addressData['RFC822_EMAIL'] != 'NIL' ? $addressData['RFC822_EMAIL'] : $addressData['EMAIL'];
						$decodedPersonalNameORG = $decodedPersonalName = $addressData['PERSONAL_NAME'];
						if ($decode) 
						{
							$newSenderAddress = bofelamimail::decode_header($newSenderAddressORG);
							$decodedPersonalName = bofelamimail::decode_header($decodedPersonalName);
							$addressData['EMAIL'] = bofelamimail::decode_header($addressData['EMAIL']);
						}
						$realName =  $decodedPersonalName;
						// add mailaddress
						if ($allwaysShowMailAddress) {
							$realName .= ' <'.$addressData['EMAIL'].'>';
							$decodedPersonalNameORG .= ' <'.$addressData['EMAIL'].'>';
						}
						// add organization
						if(!empty($_organisation)) {
							$realName .= ' ('. $_organisation . ')';
							$decodedPersonalNameORG .= ' ('. $_organisation . ')';
						}

						$linkData = array (
							'menuaction'	=> 'felamimail.uicompose.compose',
							'send_to'	=> base64_encode($newSenderAddress)
						);
						$link = $GLOBALS['egw']->link('/index.php',$linkData);

						$newSenderAddress = bofelamimail::htmlentities($newSenderAddress);
						$realName = bofelamimail::htmlentities($realName);

						$senderAddress .= sprintf('<a href="%s" title="%s">%s</a>',
									$link,
									$newSenderAddress,
									$realName);

						$linkData = array (
							'menuaction'		=> 'addressbook.addressbook_ui.edit',
							'presets[email]'	=> $addressData['EMAIL'],
							'presets[org_name]'	=> $_organisation,
							'referer'		=> $_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']
						);

						$decodedPersonalName = $realName;
						if (!empty($decodedPersonalName)) {
							if($spacePos = strrpos($decodedPersonalName, ' ')) {
								$linkData['presets[n_family]']	= substr($decodedPersonalName, $spacePos+1);
								$linkData['presets[n_given]'] 	= substr($decodedPersonalName, 0, $spacePos);
							} else {
								$linkData['presets[n_family]']	= $decodedPersonalName;
							}
							$linkData['presets[n_fn]']	= $decodedPersonalName;
						}

						if ($showAddToAdrdessbookLink && $GLOBALS['egw_info']['user']['apps']['addressbook']) {
							$urlAddToAddressbook = $GLOBALS['egw']->link('/index.php',$linkData);
							$onClick = "window.open(this,this.target,'dependent=yes,width=850,height=440,location=no,menubar=no,toolbar=no,scrollbars=yes,status=yes'); return false;";
							$image = $GLOBALS['egw']->common->image('felamimail','sm_envelope');
							$senderAddress .= sprintf('<a href="%s" onClick="%s">
								<img src="%s" width="10" height="8" border="0"
								align="absmiddle" alt="%s"
								title="%s"></a>',
								$urlAddToAddressbook,
								$onClick,
								$image,
								lang('add to addressbook'),
								lang('add to addressbook'));
						}
					} else {
						$addrEMailORG = $addrEMail = $addressData['EMAIL'];
						if ($decode) $addrEMail = bofelamimail::decode_header($addrEMail);
						$linkData = array (
							'menuaction'	=> 'felamimail.uicompose.compose',
							'send_to'	=> base64_encode($addressData['EMAIL'])
						);
						$link = $GLOBALS['egw']->link('/index.php',$linkData);
						$senderEMail = bofelamimail::htmlentities($addrEMail);
						$senderAddress .= sprintf('<a href="%s">%s</a>',
									$link,$senderEMail);
						//TODO: This uses old addressbook code, which should be removed in Version 1.4
						//Please use addressbook.addressbook_ui.edit with proper paramenters
						$linkData = array
						(
							'menuaction'		=> 'addressbook.addressbook_ui.edit',
							'presets[email]'	=> $senderEMail, //$addressData['EMAIL'],
							'presets[org_name]'	=> $_organisation,
							'referer'		=> $_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']
						);

						if ($showAddToAdrdessbookLink && $GLOBALS['egw_info']['user']['apps']['addressbook']) {
							$urlAddToAddressbook = $GLOBALS['egw']->link('/index.php',$linkData);
							$onClick = "window.open(this,this.target,'dependent=yes,width=850,height=440,location=no,menubar=no,toolbar=no,scrollbars=yes,status=yes'); return false;";
							$image = $GLOBALS['egw']->common->image('felamimail','sm_envelope');
							$senderAddress .= sprintf('<a href="%s" onClick="%s">
								<img src="%s" width="10" height="8" border="0"
								align="absmiddle" alt="%s"
								title="%s"></a>',
								$urlAddToAddressbook,
								$onClick,
								$image,
								lang('add to addressbook'),
								lang('add to addressbook'));
						}
					}
				}
				return $senderAddress;
			}

			// if something goes wrong, just return the original address
			return $_emailAddress;
		}

		/**
		 * Save an attachment in the vfs
		 *
		 * @param string|array $ids '::' delemited mailbox::uid::part-id::is_winmail::name (::name for multiple id's)
		 * @param string $path path in vfs (no egw_vfs::PREFIX!), only directory for multiple id's ($ids is an array)
		 * @return string javascript eg. to close the selector window
		 */
		function vfsSaveAttachment($ids,$path)
		{
			//return "alert('".__METHOD__.'("'.array2string($id).'","'.$path."\")'); window.close();";

			if (is_array($ids) && !egw_vfs::is_writable($path) || !is_array($ids) && !egw_vfs::is_writable(dirname($path)))
			{
				return 'alert("'.addslashes(lang('%1 is NOT writable by you!',$path)).'"); window.close();';
			}
			foreach((array)$ids as $id)
			{
				list($this->mailbox,$this->uid,$part,$is_winmail,$name) = explode('::',$id,5);
				if ($mb != $this->mailbox) $this->bofelamimail->reopen($mb = $this->mailbox);
				$attachment = $this->bofelamimail->getAttachment($this->uid,$part,$is_winmail);

				if (!($fp = egw_vfs::fopen($file=$path.($name ? '/'.$name : ''),'wb')) ||
					!fwrite($fp,$attachment['attachment']))
				{
					$err .= 'alert("'.addslashes(lang('Error saving %1!',$file)).'");';
				}
				if ($fp) fclose($fp);
			}
			$this->bofelamimail->closeConnection();

			return $err.'window.close();';
		}

		function getAttachment()
		{

			$part		= $_GET['part'];
			$is_winmail = $_GET['is_winmail'] ? $_GET['is_winmail'] : 0;
			if (!empty($_GET['mailbox'])) $this->mailbox  = base64_decode($_GET['mailbox']);

			$this->bofelamimail->reopen($this->mailbox);
			#$attachment 	= $this->bofelamimail->getAttachment($this->uid,$part);
			$attachment = $this->bofelamimail->getAttachment($this->uid,$part,$is_winmail);
			$this->bofelamimail->closeConnection();

			$GLOBALS['egw']->session->commit_session();
			if ($_GET['mode'] != "save")
			{
				//error_log(__METHOD__.print_r($attachment,true));
				if (strtoupper($attachment['type']) == 'TEXT/CALENDAR' || strtoupper($attachment['type']) == 'TEXT/X-VCALENDAR')
				{
					//error_log(__METHOD__."about to call calendar_ical");
					$calendar_ical = new calendar_ical();
					$eventid = $calendar_ical->search($attachment['attachment'],-1);
					//error_log(__METHOD__.array2string($eventid));
					if (!$eventid) $eventid = -1;
					$event = $calendar_ical->importVCal($attachment['attachment'],(is_array($eventid)?$eventid[0]:$eventid),null,true);
					//error_log(__METHOD__.$event);
					if ((int)$event > 0)
					{
						$vars = array(
							'menuaction'      => 'calendar.calendar_uiforms.edit',
							'cal_id'      => $event,
						);
						$GLOBALS['egw']->redirect_link('../index.php',$vars);
					}
					//Import failed, download content anyway
				}
				if (strtoupper($attachment['type']) == 'TEXT/X-VCARD')
				{
					$addressbook_vcal = new addressbook_vcal();
					$vcard = $addressbook_vcal->vcardtoegw($attachment['attachment']);
					//error_log(print_r($vcard,true));
					if ($vcard['uid']) $contact = $addressbook_vcal->find_contact($vcard,false);
					if (!$contact) $contact = null;
					// if there are not enough fields in the vcard (or the parser was unable to correctly parse the vcard (as of VERSION:3.0 created by MSO))
					if ($contact || count($vcard)>2) $contact = $addressbook_vcal->addVCard($attachment['attachment'],$contact,true);
					//error_log(__METHOD__.$contact);
					if ((int)$contact > 0) 
					{
						$vars = array(
							'menuaction'	=> 'addressbook.addressbook_ui.edit',
							'contact_id'	=> $contact,
						);
						$GLOBALS['egw']->redirect_link('../index.php',$vars);
					}
					//Import failed, download content anyway
				}
			}
			header ("Content-Type: ".$attachment['type']."; name=\"". $attachment['filename'] ."\"");
			if($_GET['mode'] == "save") {
				// ask for download
				header ("Content-Disposition: attachment; filename=\"". $attachment['filename'] ."\"");
			} else {
				// display it
				header ("Content-Disposition: inline; filename=\"". $attachment['filename'] ."\"");
			}
			header("Expires: 0");
			// the next headers are for IE and SSL
			header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
			header("Pragma: public");

			echo $attachment['attachment'];

			$GLOBALS['egw']->common->egw_exit();
			exit;
		}

		function &getdisplayableBody($_bodyParts)
		{
			$bodyParts	= $_bodyParts;

			$webserverURL	= $GLOBALS['egw_info']['server']['webserver_url'];

			$nonDisplayAbleCharacters = array('[\016]','[\017]',
					'[\020]','[\021]','[\022]','[\023]','[\024]','[\025]','[\026]','[\027]',
					'[\030]','[\031]','[\032]','[\033]','[\034]','[\035]','[\036]','[\037]');

			$body = '';

			//error_log(__METHOD__.array2string($bodyParts)); //exit;
			if (empty($bodyParts)) return "";
			foreach((array)$bodyParts as $singleBodyPart) {
				if (!isset($singleBodyPart['body'])) {
					$singleBodyPart['body'] = $this->getdisplayableBody($singleBodyPart);
					$body .= $singleBodyPart['body'];
					continue;
				}
				if(!empty($body)) {
					$body .= '<hr style="border:dotted 1px silver;">';
				}
				#_debug_array($singleBodyPart['charSet']);
				#_debug_array($singleBodyPart['mimeType']);
				//error_log($singleBodyPart['body']);
				// some characterreplacements, as they fail to translate
				$sar = array(
					'@(\x84|\x93|\x94)@',
					'@(\x96|\x97)@',
					'@(\x82|\x91|\x92)@',
					'@(\x85)@',
					'@(\x86)@',
					'@(\x99)@',
					'@(\xae)@',
				);
				$rar = array(
					'"',
					'-',
					'\'',
					'...',
					'&',
					'(TM)',
					'(R)',
				);
				if(($singleBodyPart['mimeType'] == 'text/html' || $singleBodyPart['mimeType'] == 'text/plain') && 
					strtoupper($singleBodyPart['charSet']) != 'UTF-8') 
				{
					$singleBodyPart['body'] = preg_replace($sar,$rar,$singleBodyPart['body']);
				}
				if ($singleBodyPart['charSet']===false) $singleBodyPart['charSet'] = bofelamimail::detect_encoding($singleBodyPart['body']);
				$singleBodyPart['body'] = $GLOBALS['egw']->translation->convert(
					$singleBodyPart['body'],
					strtolower($singleBodyPart['charSet'])
				);
				//error_log($singleBodyPart['body']);
				#$CharSetUsed = mb_detect_encoding($singleBodyPart['body'] . 'a' , strtoupper($singleBodyPart['charSet']).','.strtoupper($this->displayCharset).',UTF-8, ISO-8859-1');

				if($singleBodyPart['mimeType'] == 'text/plain')
				{
					//$newBody	= $singleBodyPart['body'];

					$newBody	= @htmlentities($singleBodyPart['body'],ENT_QUOTES, strtoupper($this->displayCharset));
					// if empty and charset is utf8 try sanitizing the string in question
					if (empty($newBody) && strtolower($singleBodyPart['charSet'])=='utf-8') $newBody = @htmlentities(iconv('utf-8', 'utf-8', $singleBodyPart['body']),ENT_QUOTES, strtoupper($this->displayCharset));
					// if the conversion to htmlentities fails somehow, try without specifying the charset, which defaults to iso-
					if (empty($newBody)) $newBody    = htmlentities($singleBodyPart['body'],ENT_QUOTES);
					#$newBody	= $this->bofelamimail->wordwrap($newBody, 90, "\n");

					// search http[s] links and make them as links available again
					// to understand what's going on here, have a look at
					// http://www.php.net/manual/en/function.preg-replace.php

					// create links for websites
					$newBody = html::activate_links($newBody);
					// redirect links for websites if you use no cookies
					#if (!($GLOBALS['egw_info']['server']['usecookies'])) 
					#	$newBody = preg_replace("/href=(\"|\')((http(s?):\/\/)|(www\.))([\w,\-,\/,\?,\=,\.,&amp;,!\n,\%,@,\(,\),\*,#,:,~,\+]+)(\"|\')/ie",
					#		"'href=\"$webserverURL/redirect.php?go='.@htmlentities(urlencode('http$4://$5$6'),ENT_QUOTES,\"$this->displayCharset\").'\"'", $newBody);	
					
					// create links for email addresses
					$this->parseEmail($newBody);
					$newBody	= $this->highlightQuotes($newBody);
					// to display a mailpart of mimetype plain/text, may be better taged as preformatted
					#$newBody	= nl2br($newBody);
					// since we do not display the message as HTML anymore we may want to insert good linebreaking (for visibility).
					//error_log($newBody);
					// dont break lines that start with > (&gt; as the text was processed with htmlentities before)
					$newBody	= "<pre>".bofelamimail::wordwrap($newBody,90,"\n",'&gt;')."</pre>";
					//$newBody   = "<pre>".$newBody."</pre>";
				}
				else
				{
					$newBody	= $singleBodyPart['body'];
					$newBody	= $this->highlightQuotes($newBody);
					#error_log(print_r($newBody,true));

					// do the cleanup, set for the use of purifier
					$usepurifier = true;
					bofelamimail::getCleanHTML($newBody,$usepurifier);
					// removes stuff between http and ?http
					$Protocol = '(http:\/\/|(ftp:\/\/|https:\/\/))';    // only http:// gets removed, other protocolls are shown
					$newBody = preg_replace('~'.$Protocol.'[^>]*\?'.$Protocol.'~sim','$1',$newBody); // removes stuff between http:// and ?http://
					// TRANSFORM MAILTO LINKS TO EMAILADDRESS ONLY, WILL BE SUBSTITUTED BY parseEmail TO CLICKABLE LINK
					$newBody = preg_replace('/(?<!"|href=|href\s=\s|href=\s|href\s=)'.'mailto:([a-z0-9._-]+)@([a-z0-9_-]+)\.([a-z0-9._-]+)/i',
						"\\1@\\2.\\3",
						$newBody);

					// redirect links for websites if you use no cookies
					#if (!($GLOBALS['egw_info']['server']['usecookies'])) { //do it all the time, since it does mask the mailadresses in urls
						$this->parseHREF($newBody);
					#}
					// create links for inline images
					$linkData = array (
						'menuaction'    => 'felamimail.uidisplay.displayImage',
						'uid'		=> $this->uid,
						'mailbox'	=> base64_encode($this->mailbox)
					);
					$imageURL = $GLOBALS['egw']->link('/index.php', $linkData);
					if ($this->partID) {
						$newBody = preg_replace("/src=(\"|\')cid:(.*)(\"|\')/iUe",
							"'src=\"$imageURL&cid='.base64_encode('$2').'&partID='.urlencode($this->partID).'\"'", $newBody);
					} else {
						$newBody = preg_replace("/src=(\"|\')cid:(.*)(\"|\')/iUe",
							"'src=\"$imageURL&cid='.base64_encode('$2').'&partID='.'\"'", $newBody);
					}

					// create links for email addresses
					$link = $GLOBALS['egw']->link('/index.php',array('menuaction'    => 'felamimail.uicompose.compose'));
					$newBody = preg_replace("/href=(\"|\')mailto:([\w,\-,\/,\?,\=,\.,&amp;,!\n,\%,@,\*,#,:,~,\+]+)(\"|\')/ie",
						"'href=\"#\"'.' onclick=\"egw_openWindowCentered(\'$link&send_to='.base64_encode('$2').'\',\'compose\',700,egw_getWindowOuterHeight());\"'", $newBody);
//						"'href=\"$link&send_to='.base64_encode('$2').'\"'", $newBody);
					//print "<pre>".htmlentities($newBody)."</pre><hr>";
					
					// replace emails within the text with clickable links.
					$this->parseEmail($newBody);
				}
				$body .= $newBody;
				#print "<hr><pre>$body</pre><hr>";
			}

			// create links for windows shares
			// \\\\\\\\ == '\\' in real life!! :)
			$body = preg_replace("/(\\\\\\\\)([\w,\\\\,-]+)/i",
				"<a href=\"file:$1$2\" target=\"_blank\"><font color=\"blue\">$1$2</font></a>", $body);

			$body = preg_replace($nonDisplayAbleCharacters,'',$body);

			return $body;
		}

		function printMessage($messageId = NULL, $callfromcompose = NULL)
		{
			if (!empty($messageId) && empty($this->uid)) $this->uid = $messageId;
			$partID		= $_GET['part'];
			if (!empty($_GET['folder'])) $this->mailbox  = base64_decode($_GET['folder']);

			//$transformdate	=& CreateObject('felamimail.transformdate');
			//$htmlFilter	=& CreateObject('felamimail.htmlfilter');
			//$uiWidgets	=& CreateObject('felamimail.uiwidgets');
			// (regis) seems to be necessary to reopen...
			$folder = $this->mailbox;
			// the folder for callfromcompose is hardcoded, because the message to be printed from the compose window is saved as draft, and can be
			// reopened for composing (only) from there
			if ($callfromcompose)
			{
				if (isset($this->mailPreferences->preferences['draftFolder']) &&
					$this->mailPreferences->preferences['draftFolder'] != 'none')
				{
					$folder = $this->mailPreferences->preferences['draftFolder']; 
				}
				else
				{
					$folder = $GLOBALS['egw_info']['user']['preferences']['felamimail']['draftFolder'];
				}
			}
			$this->bofelamimail->reopen($folder);
#			print "$this->mailbox, $this->uid, $partID<br>";
			$headers	= $this->bofelamimail->getMessageHeader($this->uid, $partID);
			$envelope   = $this->bofelamimail->getMessageEnvelope($this->uid, $partID,true);
#			_debug_array($headers);exit;
			$rawheaders	= $this->bofelamimail->getMessageRawHeader($this->uid, $partID);
			$bodyParts	= $this->bofelamimail->getMessageBody($this->uid,'',$partID);
			$attachments	= $this->bofelamimail->getMessageAttachments($this->uid,$partID);
#			_debug_array($nextMessage); exit;

			$webserverURL	= $GLOBALS['egw_info']['server']['webserver_url'];

			$nonDisplayAbleCharacters = array('[\016]','[\017]',
					'[\020]','[\021]','[\022]','[\023]','[\024]','[\025]','[\026]','[\027]',
					'[\030]','[\031]','[\032]','[\033]','[\034]','[\035]','[\036]','[\037]');

			#print "<pre>";print_r($rawheaders);print"</pre>";exit;

			// add line breaks to $rawheaders
			$newRawHeaders = explode("\n",$rawheaders);
			reset($newRawHeaders);

			// find the Organization header
			// the header can also span multiple rows
			while(is_array($newRawHeaders) && list($key,$value) = each($newRawHeaders)) {
				#print $value."<br>";
				if(preg_match("/Organization: (.*)/",$value,$matches)) {
					$organization = $this->bofelamimail->decode_header(chop($matches[1]));
					continue;
				}
				if(!empty($organization) && preg_match("/^\s+(.*)/",$value,$matches)) {
					$organization .= $this->bofelamimail->decode_header(chop($matches[1]));
					break;
				} elseif(!empty($organization)) {
					break;
				}
			}

			$this->bofelamimail->closeConnection();

			$this->display_app_header($callfromcompose);
			$this->t->set_file(array("displayMsg" => "view_message_printable.tpl"));
		#	$this->t->set_var('charset',$GLOBALS['egw']->translation->charset());

			$this->t->set_block('displayMsg','message_main');
		#	$this->t->set_block('displayMsg','message_main_attachment');
			$this->t->set_block('displayMsg','message_header');
		#	$this->t->set_block('displayMsg','message_raw_header');
		#	$this->t->set_block('displayMsg','message_navbar');
			$this->t->set_block('displayMsg','message_onbehalfof');
			$this->t->set_block('displayMsg','message_cc');
			$this->t->set_block('displayMsg','message_attachement_row');
		#	$this->t->set_block('displayMsg','previous_message_block');
		#	$this->t->set_block('displayMsg','next_message_block');
			$this->t->set_block('displayMsg','message_org');

		#	$this->t->egroupware_hack = False;

			$this->translate();

			if($envelope['FROM'][0] != $envelope['SENDER'][0]) {
				$senderAddress = self::emailAddressToHTML($envelope['SENDER'], '', true, false,false);
				$fromAddress   = self::emailAddressToHTML($envelope['FROM'], $organization, true, false,false);
				$this->t->set_var("from_data",$senderAddress);
				$this->t->set_var("onbehalfof_data",$fromAddress);
				$this->t->parse('on_behalf_of_part','message_onbehalfof',True);
			} else {
				$fromAddress   = self::emailAddressToHTML($envelope['FROM'], $organization, true, false,false);
				$this->t->set_var("from_data", $fromAddress);
				$this->t->set_var('on_behalf_of_part','');
			}

			// parse the to header
			$toAddress = self::emailAddressToHTML($envelope['TO'], '', true, false,false);
			$this->t->set_var("to_data",$toAddress);

			// parse the cc header
			if(count($envelope['CC'])) {
				$ccAddress = self::emailAddressToHTML($envelope['CC'], '', true, false,false);
				$this->t->set_var("cc_data",$ccAddress);
				$this->t->parse('cc_data_part','message_cc',True);
			} else {
				$this->t->set_var("cc_data_part",'');
			}
			$this->t->set_var("date_data",
				@htmlspecialchars(bofelamimail::_strtotime($headers['DATE'],$GLOBALS['egw_info']['user']['preferences']['common']['dateformat'],true).' - '.bofelamimail::_strtotime($headers['DATE'],($GLOBALS['egw_info']['user']['preferences']['common']['timeformat']==12?'h:i:s a':'H:i:s'),true), ENT_QUOTES,$this->displayCharset));
			// link to go back to the message view. the link differs if the print was called from a normal viewing window, or from compose
			$subject = bofelamimail::htmlspecialchars($this->bofelamimail->decode_subject(preg_replace($nonDisplayAbleCharacters, '', $envelope['SUBJECT']),false), $this->displayCharset);
			$this->t->set_var("subject_data", $subject);
			$this->t->set_var("full_subject_data", $subject);
			$linkData = array (
				'menuaction'    => 'felamimail.uidisplay.display',
				'showHeader'    => 'false',
				'mailbox'	=> base64_encode($folder),
				'uid'       => $this->uid,
				'id'        => $this->id,
			);
			if ($callfromcompose) {
				$linkData['menuaction'] = 'felamimail.uicompose.composeFromDraft';
				$linkData['folder'] = base64_encode($folder);
			}
			$_readInNewWindow = $this->mailPreferences->preferences['message_newwindow'];
			$this->t->set_var('url_read_message', $GLOBALS['egw']->link('/index.php',$linkData));

			$target = 'displayMessage';
			$windowName = ($_readInNewWindow == 1 ? $target : $target.'_'.$this->uid);
			#if ($callfromcompose) $target = 'composeFromDraft';
			if ($callfromcompose) $windowName = '_top';
			$this->t->set_var('read_message_windowName', $windowName);

			//if(isset($organization)) exit;
			$this->t->parse("header","message_header",True);

			$this->t->set_var('body', $this->getdisplayableBody($bodyParts));

			// attachments
			if(is_array($attachments))
				$this->t->set_var('attachment_count',count($attachments));
			else
				$this->t->set_var('attachment_count','0');

			if (is_array($attachments) && count($attachments) > 0) {
				$this->t->set_var('row_color',$this->rowColor[0]);
				$this->t->set_var('name',lang('name'));
				$this->t->set_var('type',lang('type'));
				$this->t->set_var('size',lang('size'));
				$this->t->set_var('url_img_save',$GLOBALS['egw']->common->image('felamimail','fileexport'));
				#$this->t->parse('attachment_rows','attachment_row_bold',True);
				foreach ($attachments as $key => $value) {
					$this->t->set_var('row_color',$this->rowColor[($key+1)%2]);
					$this->t->set_var('filename',@htmlentities($this->bofelamimail->decode_header($value['name']),ENT_QUOTES,$this->displayCharset));
					$this->t->set_var('mimetype',$value['mimeType']);
					$this->t->set_var('size',$value['size']);
					$this->t->set_var('attachment_number',$key);

					switch(strtolower($value['mimeType']))
					{
						case 'message/rfc822':
							$linkData = array
							(
								'menuaction'	=> 'felamimail.uidisplay.display',
								'uid'		=> $this->uid,
								'part'		=> $value['partID'],
								'mailbox'   => base64_encode($folder),
							);
							$windowName = 'displayMessage_'.$this->uid;
							$linkView = "egw_openWindowCentered('".$GLOBALS['egw']->link('/index.php',$linkData)."','$windowName',700,egw_getWindowOuterHeight());";
							break;
						case 'image/jpeg':
						case 'image/png':
						case 'image/gif':
						case 'image/bmp':
						#case 'application/pdf':
							$linkData = array
							(
								'menuaction'	=> 'felamimail.uidisplay.getAttachment',
								'uid'		=> $this->uid,
								'part'		=> $value['partID'],
								'mailbox'   => base64_encode($folder),
							);
							$windowName = 'displayAttachment_'.$this->uid;
							$linkView = "egw_openWindowCentered('".$GLOBALS['egw']->link('/index.php',$linkData)."','$windowName',800,600);";
							break;
						default:
							$linkData = array
							(
								'menuaction'	=> 'felamimail.uidisplay.getAttachment',
								'uid'		=> $this->uid,
								'part'		=> $value['partID'],
								'mailbox'   => base64_encode($folder),
							);
							$linkView = "window.location.href = '".$GLOBALS['egw']->link('/index.php',$linkData)."';";
							break;
					}
					$this->t->set_var("link_view",$linkView);
					$this->t->set_var("target",$target);

					$linkData = array
					(
						'menuaction'	=> 'felamimail.uidisplay.getAttachment',
						'mode'		=> 'save',
						'uid'		=> $this->uid,
						'part'		=> $value['partID'],
						'mailbox'   => base64_encode($folder),
					);
					$this->t->set_var("link_save",$GLOBALS['egw']->link('/index.php',$linkData));

					$this->t->parse('attachment_rows','message_attachement_row',True);
				}
			}
			else
			{
				$this->t->set_var('attachment_rows','');
			}

			#$this->t->pparse("out","message_attachment_rows");

			// print it out
		#	if(is_array($attachments)) {
		#		$this->t->pparse('out','message_main_attachment');
		#	} else {
				$this->t->pparse('out','message_main');
		#	}
			print "</body></html>";

		}

		function saveMessage()
		{
			$partID		= $_GET['part'];
			if (!empty($_GET['mailbox'])) $this->mailbox  = base64_decode($_GET['mailbox']);

			// (regis) seems to be necessary to reopen...
			$this->bofelamimail->reopen($this->mailbox);

			$message = $this->bofelamimail->getMessageRawBody($this->uid, $partID);
			$headers = $this->bofelamimail->getMessageHeader($this->uid, $partID);

			$this->bofelamimail->closeConnection();

			$GLOBALS['egw']->session->commit_session();

			header ("Content-Type: message/rfc822; name=\"". $headers['SUBJECT'] .".eml\"");
			header ("Content-Disposition: attachment; filename=\"". $headers['SUBJECT'] .".eml\"");
			header("Expires: 0");
			// the next headers are for IE and SSL
			header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
			header("Pragma: public");

			echo $message;

			$GLOBALS['egw']->common->egw_exit();
			exit;
		}

		function showHeader()
		{
			if($this->bofelamimail->sessionData['showHeader'] == 'True')
			{
				$this->bofelamimail->sessionData['showHeader'] = 'False';
			}
			else
			{
				$this->bofelamimail->sessionData['showHeader'] = 'True';
			}
			$this->bofelamimail->saveSessionData();

			$this->display();
		}

		function translate()
		{
			$this->t->set_var("lang_message_list",lang('Message List'));
			$this->t->set_var("lang_to",lang('to'));
			$this->t->set_var("lang_cc",lang('cc'));
			$this->t->set_var("lang_bcc",lang('bcc'));
			$this->t->set_var("lang_from",lang('from'));
			$this->t->set_var("lang_reply_to",lang('reply to'));
			$this->t->set_var("lang_subject",lang('subject'));
			$this->t->set_var("lang_addressbook",lang('addressbook'));
			$this->t->set_var("lang_search",lang('search'));
			$this->t->set_var("lang_send",lang('send'));
			$this->t->set_var("lang_back_to_folder",lang('back to folder'));
			$this->t->set_var("lang_attachments",lang('attachments'));
			$this->t->set_var("lang_add",lang('add'));
			$this->t->set_var("lang_remove",lang('remove'));
			$this->t->set_var("lang_priority",lang('priority'));
			$this->t->set_var("lang_normal",lang('normal'));
			$this->t->set_var("lang_high",lang('high'));
			$this->t->set_var("lang_low",lang('low'));
			$this->t->set_var("lang_signature",lang('signature'));
			$this->t->set_var("lang_compose",lang('compose'));
			$this->t->set_var("lang_date",lang('date'));
			$this->t->set_var("lang_view",lang('view'));
			$this->t->set_var("lang_organization",lang('organization'));
			$this->t->set_var("lang_save",lang('save'));
			$this->t->set_var("lang_printable",lang('print it'));
			$this->t->set_var("lang_reply",lang('reply'));
			$this->t->set_var("lang_reply_all",lang('reply all'));
			$this->t->set_var("lang_forward",lang('forward'));
			$this->t->set_var("lang_delete",lang('delete'));
			$this->t->set_var("lang_previous_message",lang('previous message'));
			$this->t->set_var("lang_next_message",lang('next message'));
			$this->t->set_var("lang_organisation",lang('organisation'));
			$this->t->set_var("lang_on_behalf_of",lang('on behalf of'));
			$this->t->set_var("lang_Message", lang('Message'));
			$this->t->set_var("lang_Attachment", lang('attachments'));
			$this->t->set_var("lang_Header_Lines", lang('Header Lines'));

			$this->t->set_var("th_bg",$GLOBALS['egw_info']["theme"]["th_bg"]);
			$this->t->set_var("bg01",$GLOBALS['egw_info']["theme"]["bg01"]);
			$this->t->set_var("bg02",$GLOBALS['egw_info']["theme"]["bg02"]);
			$this->t->set_var("bg03",$GLOBALS['egw_info']["theme"]["bg03"]);
		}
}

?>
