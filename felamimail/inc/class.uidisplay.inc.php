<?php
	/***************************************************************************\
	* phpGroupWare - FeLaMiMail                                                 *
	* http://www.linux-at-work.de                                               *
	* http://www.phpgw.de                                                       *
	* http://www.phpgroupware.org                                               *
	* Written by : Lars Kneschke [lkneschke@linux-at-work.de]                   *
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
			'display'	=> 'True',
			'showHeader'	=> 'True',
			'getAttachment'	=> 'True'
		);

		function uidisplay()
		{
			$this->t 		= CreateObject('phpgwapi.Template',PHPGW_APP_TPL);
			$this->displayCharset   = $GLOBALS['phpgw']->translation->charset();
			$this->bofelamimail	= CreateObject('felamimail.bofelamimail',$this->displayCharset);
			$this->bofilter 	= CreateObject('felamimail.bofilter');
			$this->bopreferences	= CreateObject('felamimail.bopreferences');
			$this->kses		= CreateObject('phpgwapi.kses');
			$this->botranslation	= CreateObject('phpgwapi.translation');
			
			$this->mailPreferences	= $this->bopreferences->getPreferences();
			
			$this->bofelamimail->openConnection();
			
			$this->mailbox		= $this->bofelamimail->sessionData['mailbox'];
			$this->sort		= $this->bofelamimail->sessionData['sort'];
			
			$this->uid		= $GLOBALS['HTTP_GET_VARS']['uid'];
			
			if(isset($GLOBALS['HTTP_GET_VARS']['part']) &&
				is_numeric($GLOBALS['HTTP_GET_VARS']['part']))
			{
				$this->partID = $GLOBALS['HTTP_GET_VARS']['part'];
			}
			else
			{
				$this->partID = 0;
			}

			$this->bocaching	= CreateObject('felamimail.bocaching',
							$this->mailPreferences['imapServerAddress'],
							$this->mailPreferences['username'],
							$this->mailbox);

			$this->rowColor[0] = $GLOBALS['phpgw_info']["theme"]["bg01"];
			$this->rowColor[1] = $GLOBALS['phpgw_info']["theme"]["bg02"];

			if($GLOBALS['HTTP_GET_VARS']['showHeader'] == "false")
			{
				$this->bofelamimail->sessionData['showHeader'] = 'False';
				$this->bofelamimail->saveSessionData();
			}
			
		}
		
		function createLinks($_data)
		{
			
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
			$transformdate	= CreateObject('felamimail.transformdate');
			$htmlFilter	= CreateObject('felamimail.htmlfilter');

			$headers	= $this->bofelamimail->getMessageHeader($this->uid, $partID);
			$rawheaders	= $this->bofelamimail->getMessageRawHeader($this->uid, $partID);
			$bodyParts	= $this->bofelamimail->getMessageBody($this->uid,'',$partID);
			$attachments	= $this->bofelamimail->getMessageAttachments($this->uid,$partID);
			$filterList 	= $this->bofilter->getFilterList();
			$activeFilter 	= $this->bofilter->getActiveFilter();
			$filter 	= $filterList[$activeFilter];
			$nextMessage	= $this->bocaching->getNextMessage($this->uid, $this->sort, $filter);

			$webserverURL	= $GLOBALS['phpgw_info']['server']['webserver_url'];

			#print "<pre>";print_r($rawheaders);print"</pre>";exit;

			// add line breaks to $rawheaders
			$newRawHeaders = explode("\n",$rawheaders);
			reset($newRawHeaders);
			// find the Organization header
			// the header can also span multiple rows
			while(is_array($newRawHeaders) && list($key,$value) = each($newRawHeaders))
			{
				#print $value."<br>";
				if(preg_match("/Organization: (.*)/",$value,$matches))
				{
					$organization = $this->bofelamimail->decode_header(chop($matches[1]));
					#$organization = chop($matches[1]);
					continue;
				}
				if(!empty($organization) && preg_match("/^\s+(.*)/",$value,$matches))
				{
					$organization .= $this->bofelamimail->decode_header(chop($matches[1]));
					break;
				}
				elseif(!empty($organization))
				{
					break;
				}
			}
			
			// reset $rawheaders
			$rawheaders 	= "";
			// create it new, with good line breaks
			reset($newRawHeaders);
			while(list($key,$value) = @each($newRawHeaders))
			{
				$rawheaders .= wordwrap($value,90,"\n     ");
			}
			
			$this->bofelamimail->closeConnection();
			
			if(!isset($_GET['printable']))
			{
				$this->display_app_header();
				$this->t->set_file(array("displayMsg" => "view_message.tpl"));
			}
			else
			{
				$this->t->set_file(array("displayMsg" => "view_message_printable.tpl"));
				$this->t->set_var('charset',$GLOBALS['phpgw']->translation->charset());
			}

			$this->t->set_block('displayMsg','message_main');
			$this->t->set_block('displayMsg','message_header');
			$this->t->set_block('displayMsg','message_raw_header');
			$this->t->set_block('displayMsg','message_navbar');
			$this->t->set_block('displayMsg','message_onbehalfof');
			$this->t->set_block('displayMsg','message_cc');
			$this->t->set_block('displayMsg','message_attachement_row');
			$this->t->set_block('displayMsg','previous_message_block');
			$this->t->set_block('displayMsg','next_message_block');
			$this->t->set_block('displayMsg','message_org');

			$this->t->egroupware_hack = False;
			
			$this->translate();
			
//			if(!isset($GLOBALS['HTTP_GET_VARS']['printable']))
//			{
				// navbar
				$linkData = array
				(
					'menuaction'	=> 'felamimail.uifelamimail.viewMainScreen'
				);
				if($this->mailPreferences['messageNewWindow'])
				{
					$this->t->set_var("link_message_list","javascript:window.close();");
				}
				else
				{
					$this->t->set_var("link_message_list",$GLOBALS['phpgw']->link('/felamimail/index.php',$linkData));
				}
	
				$linkData = array
				(
					'menuaction'	=> 'felamimail.uicompose.compose'
				);
				$this->t->set_var("link_compose",$GLOBALS['phpgw']->link('/index.php',$linkData));
				$this->t->set_var('folder_name',$this->bofelamimail->sessionData['mailbox']);
				
				// return link to main message
				if($partID != '')
				{
					$linkData = array
					(
						'menuaction'	=> 'felamimail.uidisplay.display',
						'showHeader'	=> 'false',
						'uid'		=> $this->uid
					);
					$this->t->set_var('link_mainmessage','<a href="'.$GLOBALS['phpgw']->link('/index.php',$linkData).'">'.lang('mainmessage').'</a>');
				}
				else
				{
					$this->t->set_var('link_mainmessage','');
				}

				$linkData = array
				(
					'menuaction'	=> 'felamimail.uicompose.reply',
					'reply_id'	=> $this->uid,
				);
				if($partID != '')
					$linkData['part_id'] = $partID;
				$this->t->set_var("link_reply",$GLOBALS['phpgw']->link('/index.php',$linkData));

				$linkData = array
				(
					'menuaction'	=> 'felamimail.uicompose.replyAll',
					'reply_id'	=> $this->uid,
				);
				if($partID != '')
					$linkData['part_id'] = $partID;
				$this->t->set_var("link_reply_all",$GLOBALS['phpgw']->link('/index.php',$linkData));

				$linkData = array
				(
					'menuaction'	=> 'felamimail.uicompose.forward',
					'reply_id'	=> $this->uid
				);
				if($partID != '')
					$linkData['part_id'] = $partID;
				$this->t->set_var("link_forward",$GLOBALS['phpgw']->link('/index.php',$linkData));	

				$linkData = array
				(
					'menuaction'	=> 'felamimail.uifelamimail.deleteMessage',
					'message'	=> $this->uid
				);
				$this->t->set_var("link_delete",$GLOBALS['phpgw']->link('/index.php',$linkData));

				$linkData = array
				(
					'menuaction'	=> 'felamimail.uidisplay.showHeader',
					'uid'		=> $this->uid
				);
				$this->t->set_var("link_header",$GLOBALS['phpgw']->link('/index.php',$linkData));

				$linkData = array
				(
					'menuaction'	=> 'felamimail.uidisplay.display',
					'printable'	=> 1,
					'uid'		=> $this->uid
				);
				if($partID != '')
					$linkData['part'] = $partID;
				$this->t->set_var("link_printable",$GLOBALS['phpgw']->link('/index.php',$linkData));
				
				if($nextMessage['previous'])
				{
					$linkData = array
					(
						'menuaction'	=> 'felamimail.uidisplay.display',
						'showHeader'	=> 'false',
						'uid'		=> $nextMessage['previous']
					);
					$this->t->set_var('previous_url',$GLOBALS['phpgw']->link('/index.php',$linkData));
					$this->t->parse('previous_message','previous_message_block',True);
				}
				else
				{
					$this->t->set_var('previous_message',lang('previous message'));
				}
	
				if($nextMessage['next'])
				{
					$linkData = array
					(
						'menuaction'	=> 'felamimail.uidisplay.display',
						'showHeader'	=> 'false',
						'uid'		=> $nextMessage['next']
					);
					$this->t->set_var('next_url',$GLOBALS['phpgw']->link('/index.php',$linkData));
					$this->t->parse('next_message','next_message_block',True);
				}
				else
				{
					$this->t->set_var('next_message',lang('next message'));
				}
	
				$langArray = array
				(
					'lang_messagelist'      => lang('Message List'),
					'lang_compose'          => lang('Compose'),
					'lang_delete'           => lang('Delete'),
					'lang_forward'          => lang('Forward'),
					'lang_reply'            => lang('Reply'),
					'lang_reply_all'        => lang('Reply All'),
					'lang_back_to_folder'   => lang('back to folder'),
					'print_navbar'		=> '',
					'app_image_path'        => PHPGW_IMAGES
				);
				$this->t->set_var($langArray);
				$this->t->parse('navbar','message_navbar',True);
/*			}
			else
			{	
				$langArray = array
				(
					'lang_print_this_page'  => lang('print this page'),
					'lang_close_this_page'  => lang('close this page'),
					'lang_printable'        => '',
					'lang_reply'            => lang('Reply'),
					'lang_reply_all'        => lang('Reply All'),
					'lang_back_to_folder'   => lang('back to folder'),
					'navbar'		=> '',
					'app_image_path'        => PHPGW_IMAGES
				);
				$this->t->set_var($langArray);
				$this->t->parse('print_navbar','message_navbar_print',True);
			}*/
			
			// header
			// sent by a mailinglist??
			// parse the from header
			if($headers->senderaddress != $headers->fromaddress)
			{
				$senderAddress = $this->emailAddressToHTML($headers->senderaddress);
				$fromAddress   = $this->emailAddressToHTML($headers->fromaddress);
				$this->t->set_var("from_data",$senderAddress);
				#	"&nbsp;".lang('on behalf of')."&nbsp;".
				#	$fromAddress);
				$this->t->set_var("onbehalfof_data",$fromAddress);
				$this->t->parse('on_behalf_of_part','message_onbehalfof',True);
			}
			else
			{
				$fromAddress   = $this->emailAddressToHTML($headers->fromaddress);
				$this->t->set_var("from_data", $fromAddress);
				$this->t->set_var('on_behalf_of_part','');
			}
			
			// parse the to header
			$toAddress = $this->emailAddressToHTML($headers->toaddress);
			$this->t->set_var("to_data",$toAddress);
			
			// parse the cc header
			if($headers->ccaddress)
			{
				$ccAddress = $this->emailAddressToHTML($headers->ccaddress);
				$this->t->set_var("cc_data",$ccAddress);
				$this->t->parse('cc_data_part','message_cc',True);
			}
			else
			{
				$this->t->set_var("cc_data_part",'');
			}

			// parse the cc header
			if(!empty($organization))
			{
				$this->t->set_var("organization_data",$organization);
				$this->t->parse('org_part','message_org',True);
			}
			else
			{
				$this->t->set_var("org_part",'');
			}

			if (isset($headers->date))
			{
				$headers->date = ereg_replace('  ', ' ', $headers->date);
				$tmpdate = explode(' ', trim($headers->date));
			}
			else
			{
				$tmpdate = $date = array("","","","","","");
			}
                                                                                                                                                                                                                                                                                                                
			$this->t->set_var("date_data",
				@htmlspecialchars($GLOBALS['phpgw']->common->show_date($transformdate->getTimeStamp($tmpdate)),
				ENT_QUOTES,$this->displayCharset));
			$this->t->set_var("subject_data",
				@htmlspecialchars($this->bofelamimail->decode_header($headers->subject),
				ENT_QUOTES,$this->displayCharset));
			//if(isset($organization)) exit;
			$this->t->parse("header","message_header",True);

			$this->t->set_var("rawheader",@htmlentities($rawheaders,ENT_QUOTES,$this->displayCharset));

			#$this->kses->AddProtocol("http");
			$this->kses->AddHTML(
				"p",array(
					'align'	=> array("minlen" =>   1, 'maxlen' =>  10)
				)
			);
			$this->kses->AddHTML("tbody");
			$this->kses->AddHTML("tt");
			$this->kses->AddHTML("br");
			$this->kses->AddHTML("b");
			$this->kses->AddHTML("i");
			$this->kses->AddHTML("strike");
			$this->kses->AddHTML("center");
			$this->kses->AddHTML(
				"font",array(
					"color"	=> array('maxlen' => 10)
				)
			);
			$this->kses->AddHTML(
				"hr",array(
					"class"	=> array('maxlen' => 20)
				)
			);
			$this->kses->AddHTML("div");
			$this->kses->AddHTML("ul");
			$this->kses->AddHTML(
				"ol",array(
					"type"	=> array('maxlen' => 20)
				)
			);
			$this->kses->AddHTML("li");
			$this->kses->AddHTML("h1");
			$this->kses->AddHTML("h2");
			$this->kses->AddHTML(
				"style",array(
					"type"	=> array('maxlen' => 20)
				)
			);
			$this->kses->AddHTML("select");
			$this->kses->AddHTML(
				"option",array(
					"value" => array('maxlen' => 45),
					"selected" => array()
				)
			);

			$this->kses->AddHTML(
				"a", array(
					"href" 		=> array('maxlen' => 145, 'minlen' => 10),
					"name" 		=> array('minlen' => 2),
					'target'	=> array('maxlen' => 10)
				)
			);

			$this->kses->AddHTML(
				"pre", array(
					"wrap" => array('maxlen' => 10)
				)
			);
			
			//      Allows 'td' tag with colspan|rowspan|class|style|width|nowrap attributes,
			//              colspan has minval of   2       and maxval of 5
			//              rowspan has minval of   3       and maxval of 6
			//              class   has minlen of   1 char  and maxlen of   10 chars
			//              style   has minlen of  10 chars and maxlen of 100 chars
			//              width   has maxval of 100
			//              nowrap  is valueless
			$this->kses->AddHTML(
				"table",array(
					"class"   => array("minlen" =>   1, 'maxlen' =>  20),
					"border"   => array("minlen" =>   1, 'maxlen' =>  10),
					"cellpadding"   => array("minlen" =>   0, 'maxlen' =>  10),
					"cellspacing"   => array("minlen" =>   0, 'maxlen' =>  10),
					"width"   => array("maxlen" => 5),
					"style"   => array('minlen' =>  10, 'maxlen' => 100),
					"bgcolor"   => array('maxlen' =>  10),
					"align"   => array('maxlen' =>  10),
					"valign"   => array('maxlen' =>  10),
					"bordercolor"   => array('maxlen' =>  10)
				)
			);
			$this->kses->AddHTML(
				"tr",array(
					"colspan"	=> array('minval' =>   2, 'maxval' =>   5),
					"rowspan"	=> array('minval' =>   3, 'maxval' =>   6),
					"class"		=> array("minlen" =>   1, 'maxlen' =>  20),
					"width"		=> array("maxlen" => 5),
					"style"		=> array('minlen' =>  10, 'maxlen' => 100),
					"align"		=> array('maxlen' =>  10),
					'bgcolor'	=> array('maxlen' => 10),
					"valign"	=> array('maxlen' =>  10),
					"nowrap"	=> array('valueless' => 'y')
				)
			);
			$this->kses->AddHTML(
				"td",array(
					"colspan" => array('minval' =>   2, 'maxval' =>   5),
					"rowspan" => array('minval' =>   3, 'maxval' =>   6),
					"class"   => array("minlen" =>   1, 'maxlen' =>  20),
					"width"   => array("maxlen" => 5),
					"style"   => array('minlen' =>  10, 'maxlen' => 100),
					"align"   => array('maxlen' =>  10),
					'bgcolor' => array('maxlen' => 10),
					"valign"   => array('maxlen' =>  10),
					"nowrap"  => array('valueless' => 'y')
				)
			);
			$this->kses->AddHTML(
				"th",array(
					"colspan" => array('minval' =>   2, 'maxval' =>   5),
					"rowspan" => array('minval' =>   3, 'maxval' =>   6),
					"class"   => array("minlen" =>   1, 'maxlen' =>  20),
					"width"   => array("maxlen" => 5),
					"style"   => array('minlen' =>  10, 'maxlen' => 100),
					"align"   => array('maxlen' =>  10),
					"valign"   => array('maxlen' =>  10),
					"nowrap"  => array('valueless' => 'y')
				)
			);
			$this->kses->AddHTML(
				"span",array(
					"class"   => array("minlen" =>   1, 'maxlen' =>  20)
				)
			);
			$this->kses->AddHTML(
				"blockquote",array(
					"class"	=> array("minlen" =>   1, 'maxlen' =>  20),
					"style"	=> array("minlen" =>   1),
					"cite"	=> array('maxlen' => 30),
					"type"	=> array('maxlen' => 10),
					"dir"	=> array("minlen" =>   1, 'maxlen' =>  10)
				)
			);



			for($i=0; $i<count($bodyParts); $i++)
			{
				$bodyParts[$i]['body']= 
					$this->botranslation->convert($bodyParts[$i]['body'],
								      strtolower($bodyParts[$i]['charSet']));

				if($bodyParts[$i]['mimeType'] == 'text/plain')
				{
					$newBody	= $bodyParts[$i]['body'];

					$newBody	= @htmlentities($bodyParts[$i]['body'],ENT_QUOTES,$this->displayCharset);
					$newBody	= $this->bofelamimail->wordwrap($newBody,90,"\n");
					
					// search http[s] links and make them as links available again
					// to understand what's going on here, have a look at 
					// http://www.php.net/manual/en/function.preg-replace.php

					// create links for websites
					$newBody = preg_replace("/((http(s?):\/\/)|(www\.))([\w,\-,\/,\?,\=,\.,&amp;,!\n,!&gt;,\%,@,\*,#,:,~,\+]+)/ie", 
						"'<a href=\"$webserverURL/redirect.php?go='.@htmlentities(urlencode('http$3://$4$5'),ENT_QUOTES,\"$this->displayCharset\").'\" target=\"_blank\"><font color=\"blue\">$2$4$5</font></a>'", $newBody);
			
					// create links for ftp sites
					$newBody = preg_replace("/((ftp:\/\/)|(ftp\.))([\w\.,-.,\/.,\?.,\=.,&amp;]+)/i", 
						"<a href=\"ftp://$3$4\" target=\"_blank\"><font color=\"blue\">$1$3$4</font></a>", $newBody);

					// create links for email addresses
					$linkData = array
					(
						'menuaction'    => 'felamimail.uicompose.compose'
					);
					$link = $GLOBALS['phpgw']->link('/index.php',$linkData);
					$newBody = preg_replace("/(?<=\s{1}|&lt;)(([\w\.,-.,_.,0-9.]+)(@)([\w\.,-.,_.,0-9.]+))/ie", 
						"'<a href=\"$link&send_to='.base64_encode('$0').'\"><font color=\"blue\">$0</font></a>'", $newBody);

					$newBody	= $this->highlightQuotes($newBody);
					$newBody	= "<pre>".$newBody."</pre>";
				}
				else
				{
					$newBody	= $bodyParts[$i]['body'];
					$newBody	= $this->highlightQuotes($newBody);
					$newBody 	= $this->kses->Parse($newBody);

					// create links for websites
					#$newBody = preg_replace("/(?<!\>)((http(s?):\/\/)|(www\.))([\w,\-,\/,\?,\=,\.,&amp;,!\n,\%,@,\*,#,:,~,\+]+)/ie", 
					#	"'<a href=\"$webserverURL/redirect.php?go='.htmlentities(urlencode('http$3://$4$5'),ENT_QUOTES,\"$this->displayCharset\").'\" target=\"_blank\"><font color=\"blue\">$2$4$5</font></a>'", $newBody);
					$newBody = preg_replace("/(?<!>|\/|\")((http(s?):\/\/)|(www\.))([\w,\-,\/,\?,\=,\.,&amp;,!\n,\%,@,\*,#,:,~,\+]+)/ie", 
						"'<a href=\"$webserverURL/redirect.php?go='.@htmlentities(urlencode('http$3://$4$5'),ENT_QUOTES,\"$this->displayCharset\").'\" target=\"_blank\"><font color=\"blue\">$2$4$5</font></a>'", $newBody);

					// create links for websites
					$newBody = preg_replace("/href=(\"|\')((http(s?):\/\/)|(www\.))([\w,\-,\/,\?,\=,\.,&amp;,!\n,\%,@,\(,\),\*,#,:,~,\+]+)(\"|\')/ie", 
						"'href=\"$webserverURL/redirect.php?go='.@htmlentities(urlencode('http$4://$5$6'),ENT_QUOTES,\"$this->displayCharset\").'\" target=\"_blank\"'", $newBody);

					// create links for ftp sites
					$newBody = preg_replace("/href=(\"|\')((ftp:\/\/)|(ftp\.))([\w\.,-.,\/.,\?.,\=.,&amp;]+)(\"|\')/i", 
						"href=\"ftp://$4$5\" target=\"_blank\"", $newBody);

					// create links for email addresses
					$linkData = array
					(
						'menuaction'    => 'felamimail.uicompose.compose'
					);
					$link = $GLOBALS['phpgw']->link('/index.php',$linkData);
					$newBody = preg_replace("/href=(\"|\')mailto:([\w,\-,\/,\?,\=,\.,&amp;,!\n,\%,@,\*,#,:,~,\+]+)(\"|\')/ie", 
						"'href=\"$link&send_to='.base64_encode('$2').'\"'", $newBody);
					#print "<pre>".htmlentities($newBody)."</pre><hr>";

					$link = $GLOBALS['phpgw']->link('/index.php',$linkData);
					#$newBody = preg_replace("/(?<!:)(?<=\s{1}|&lt;)(([\w\.,-.,_.,0-9.]+)(@)([\w\.,-.,_.,0-9.]+))/ie", 
					$newBody = preg_replace("/(?<!:)(([\w\.,-.,_.,0-9.]+)(@)([\w\.,-.,_.,0-9.]+))/ie", 
						"'<a href=\"$link&send_to='.base64_encode('$0').'\"><font color=\"blue\">$0</font></a>'", $newBody);
				}
				$body .= $newBody;
				#print "<hr><pre>$body</pre><hr>";
			}
			
			// create links for windows shares
			// \\\\\\\\ == '\\' in real life!! :)
			$body = preg_replace("/(\\\\\\\\)([\w,\\\\,-]+)/i", 
				"<a href=\"file:$1$2\" target=\"_blank\"><font color=\"blue\">$1$2</font></a>", $body);
			
				
			$this->t->set_var("body",$body);
			$this->t->set_var("signature",$sessionData['signature']);

			// attachments
			if(is_array($attachments))
				$this->t->set_var('attachment_count',count($attachments));
			else
				$this->t->set_var('attachment_count','0');

			if (is_array($attachments) && count($attachments) > 0)
			{
				$this->t->set_var('row_color',$this->rowColor[0]);
				$this->t->set_var('name',lang('name'));
				$this->t->set_var('type',lang('type'));
				$this->t->set_var('size',lang('size'));
				#$this->t->parse('attachment_rows','attachment_row_bold',True);
				foreach ($attachments as $key => $value)
				{
					$this->t->set_var('row_color',$this->rowColor[($key+1)%2]);
					$this->t->set_var('filename',@htmlentities($this->bofelamimail->decode_header($value['name']),ENT_QUOTES,$this->displayCharset));
					$this->t->set_var('mimetype',$value['mimeType']);
					$this->t->set_var('size',$value['size']);
					$this->t->set_var('attachment_number',$key);

					switch($value['mimeType'])
					{
						case 'message/rfc822':
							$linkData = array
							(
								'menuaction'	=> 'felamimail.uidisplay.display',
								'uid'		=> $this->uid,
								'part'		=> $value['partID']
							);
							$target = '';
							break;
						case 'image/jpeg':
						case 'image/png':
						case 'image/gif':
						case 'application/pdf':
							$linkData = array
							(
								'menuaction'	=> 'felamimail.uidisplay.getAttachment',
								'uid'		=> $this->uid,
								'part'		=> $value['partID']
							);
							$target = '_blank';
							break;
						default:
							$linkData = array
							(
								'menuaction'	=> 'felamimail.uidisplay.getAttachment',
								'uid'		=> $this->uid,
								'part'		=> $value['partID']
							);
							$target = '';
							break;
					}
					$this->t->set_var("link_view",$GLOBALS['phpgw']->link('/index.php',$linkData));
					$this->t->set_var("target",$target);

					$linkData = array
					(
						'menuaction'	=> 'felamimail.uidisplay.getAttachment',
						'mode'		=> 'save',
						'uid'		=> $this->uid,
						'part'		=> $value['partID']
					);
					$this->t->set_var("link_save",$GLOBALS['phpgw']->link('/index.php',$linkData));
					
					$this->t->parse('attachment_rows','message_attachement_row',True);
				}
			}
			else
			{
				$this->t->set_var('attachment_rows','');
			}
			
			#$this->t->pparse("out","message_attachment_rows");

			// print it out
			$this->t->pparse("out","message_main");

		}

		function display_app_header()
		{
			if(!@is_object($GLOBALS['phpgw']->js))
			{
				$GLOBALS['phpgw']->js = CreateObject('phpgwapi.javascript');
			}
			$GLOBALS['phpgw']->js->validate_file('tabs','tabs');
			$GLOBALS['phpgw']->js->validate_file('jscode','view_message','felamimail');
			$GLOBALS['phpgw']->js->set_onload('javascript:initAll();');
			
			$GLOBALS['phpgw']->common->phpgw_header();
			if(!$this->mailPreferences['messageNewWindow'])
			{
				echo parse_navbar();
			}
		}

		function emailAddressToHTML($_emailAddress)
		{		
			// create some nice formated HTML for senderaddress
			if($_emailAddress == 'undisclosed-recipients: ;')
				return $_emailAddress;
				
			$addressData = imap_rfc822_parse_adrlist
					($this->bofelamimail->decode_header($_emailAddress),'');
			if(is_array($addressData))
			{
				$senderAddress = '';
				while(list($key,$val)=each($addressData))
				{
					if(!empty($senderAddress)) $senderAddress .= ", ";
					if(!empty($val->personal))
					{
						$tempSenderAddress = $val->mailbox."@".$val->host;
						$newSenderAddress  = imap_rfc822_write_address($val->mailbox,
									$val->host,
									$val->personal);
						$linkData = array
						(
							'menuaction'	=> 'felamimail.uicompose.compose',
							'send_to'	=> base64_encode($newSenderAddress)
						);
						$link = $GLOBALS['phpgw']->link('/index.php',$linkData);
						$senderAddress .= sprintf('<a href="%s" title="%s">%s</a>',
									$link,
									@htmlentities($newSenderAddress,ENT_QUOTES,$this->displayCharset),
									@htmlentities($val->personal,ENT_QUOTES,$this->displayCharset));
						$linkData = array
						(
							'menuaction'	=> 'addressbook.uiaddressbook.add_email',
							'add_email'	=> $tempSenderAddress,
							'name'		=> $val->personal,
							'referer'	=> $_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']
						);
						$urlAddToAddressbook = $GLOBALS['phpgw']->link('/index.php',$linkData);
						$image = $GLOBALS['phpgw']->common->image('felamimail','sm_envelope');
						$senderAddress .= sprintf('<a href="%s">
							<img src="%s" width="10" height="8" border="0" 
							align="absmiddle" alt="%s" 
							title="%s"></a>',
							$urlAddToAddressbook,
							$image,
							lang('add to addressbook'),
							lang('add to addressbook'));
					}
					else
					{
						$tempSenderAddress = $val->mailbox."@".$val->host;
						$linkData = array
						(
							'menuaction'	=> 'felamimail.uicompose.compose',
							'send_to'	=> base64_encode($tempSenderAddress)
						);
						$link = $GLOBALS['phpgw']->link('/index.php',$linkData);
						$senderAddress .= sprintf('<a href="%s">%s</a>',
									$link,@htmlentities($tempSenderAddress,ENT_QUOTES,$this->displayCharset));
						$linkData = array
						(
							'menuaction'	=> 'addressbook.uiaddressbook.add_email',
							'add_email'	=> $tempSenderAddress,
							'referer'	=> $_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']
						);
						$urlAddToAddressbook = $GLOBALS['phpgw']->link('/index.php',$linkData);
						$image = $GLOBALS['phpgw']->common->image('felamimail','sm_envelope');
						$senderAddress .= sprintf('<a href="%s">
							<img src="%s" width="10" height="8" border="0" 
							align="absmiddle" alt="%s" 
							title="%s"></a>',
							$urlAddToAddressbook,
							$image,
							lang('add to addressbook'),
							lang('add to addressbook'));
					}
				}
				return $senderAddress;
			}
			
			// if something goes wrong, just return the original address
			return $_emailAddress;
		}
		
		function getAttachment()
		{
			
			$part		= $GLOBALS['HTTP_GET_VARS']['part'];
			
			$attachment 	= $this->bofelamimail->getAttachment($this->uid,$part);
			
			$this->bofelamimail->closeConnection();
			
			header ("Content-Type: ".$attachment['type']."; name=\"".$attachment['filename']."\"");
			if($GLOBALS['HTTP_GET_VARS']['mode'] == "save")
			{
				// ask for download
				header ("Content-Disposition: attachment; filename=\"".$attachment['filename']."\"");
			}
			else
			{
				// display it
				header ("Content-Disposition: inline; filename=\"".$attachment['filename']."\"");
			}
			header("Expires: 0");
			// the next headers are for IE and SSL
			header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
			header("Pragma: public"); 

			echo $attachment['attachment'];
			
			$GLOBALS['phpgw']->common->phpgw_exit();
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
			
			$this->t->set_var("th_bg",$GLOBALS['phpgw_info']["theme"]["th_bg"]);
			$this->t->set_var("bg01",$GLOBALS['phpgw_info']["theme"]["bg01"]);
			$this->t->set_var("bg02",$GLOBALS['phpgw_info']["theme"]["bg02"]);
			$this->t->set_var("bg03",$GLOBALS['phpgw_info']["theme"]["bg03"]);
		}
}

?>
