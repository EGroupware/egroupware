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
	/* $Id$ */

	class uicompose
	{

		var $public_functions = array
		(
			'compose'	=> 'True',
			'reply'		=> 'True',
			'replyAll'	=> 'True',
			'forward'	=> 'True',
			'action'	=> 'True'
		);

		function uicompose()
		{
			$this->displayCharset   = $GLOBALS['phpgw']->translation->charset();
			if (!isset($GLOBALS['HTTP_POST_VARS']['composeid']) && !isset($GLOBALS['HTTP_GET_VARS']['composeid']))
			{
				// create new compose session
				$this->bocompose   = CreateObject('felamimail.bocompose','',$this->displayCharset);
				$this->composeID = $this->bocompose->getComposeID();
			}
			else
			{
				// reuse existing compose session
				if (isset($GLOBALS['HTTP_POST_VARS']['composeid']))
					$this->composeID = $GLOBALS['HTTP_POST_VARS']['composeid'];
				else
					$this->composeID = $GLOBALS['HTTP_GET_VARS']['composeid'];
				$this->bocompose   = CreateObject('felamimail.bocompose',$this->composeID,$this->displayCharset);
			}			
			$this->t 		= CreateObject('phpgwapi.Template',PHPGW_APP_TPL);
			$this->bofelamimail	= CreateObject('felamimail.bofelamimail',$this->displayCharset);
			$this->mailPreferences  = ExecMethod('felamimail.bopreferences.getPreferences');

			$this->t->set_unknowns('remove');
			
			$this->rowColor[0] = $GLOBALS['phpgw_info']["theme"]["bg01"];
			$this->rowColor[1] = $GLOBALS['phpgw_info']["theme"]["bg02"];


		}
		
		function unhtmlentities ($string)
		{
			$trans_tbl = get_html_translation_table (HTML_ENTITIES);
			$trans_tbl = array_flip ($trans_tbl);
			return strtr ($string, $trans_tbl);
		}

		function action()
		{
			$formData['to'] 	= $this->bocompose->stripSlashes($GLOBALS['HTTP_POST_VARS']['to']);
			$formData['cc'] 	= $this->bocompose->stripSlashes($GLOBALS['HTTP_POST_VARS']['cc']);
			$formData['bcc'] 	= $this->bocompose->stripSlashes($GLOBALS['HTTP_POST_VARS']['bcc']);
			$formData['reply_to'] 	= $this->bocompose->stripSlashes($GLOBALS['HTTP_POST_VARS']['reply_to']);
			$formData['subject'] 	= $this->bocompose->stripSlashes($GLOBALS['HTTP_POST_VARS']['subject']);
			$formData['body'] 	= $this->bocompose->stripSlashes($GLOBALS['HTTP_POST_VARS']['body']);
			$formData['priority'] 	= $this->bocompose->stripSlashes($GLOBALS['HTTP_POST_VARS']['priority']);
			$formData['signature'] 	= $this->bocompose->stripSlashes($GLOBALS['HTTP_POST_VARS']['signature']);
			$formData['mailbox']	= $GLOBALS['HTTP_GET_VARS']['mailbox'];

			if (isset($GLOBALS['HTTP_POST_VARS']['send'])) 
			{
				$action="send";
			}
			elseif (isset($GLOBALS['HTTP_POST_VARS']['addfile'])) 
			{
				$action="addfile";
			}
			elseif (isset($GLOBALS['HTTP_POST_VARS']['removefile']))
			{
				$action="removefile";
			}
			
			switch ($action)
			{
				case "addfile":
					$formData['name']	= $GLOBALS['HTTP_POST_FILES']['attachfile']['name'];
					$formData['type']	= $GLOBALS['HTTP_POST_FILES']['attachfile']['type'];
					$formData['file']	= $GLOBALS['HTTP_POST_FILES']['attachfile']['tmp_name'];
					$formData['size']	= $GLOBALS['HTTP_POST_FILES']['attachfile']['size'];
					$this->bocompose->addAttachment($formData);
					$this->compose();
					break;

				case "removefile":
					$formData['removeAttachments']	= $GLOBALS['HTTP_POST_VARS']['attachment'];
					$this->bocompose->removeAttachment($formData);
					$this->compose();
					break;
					
				case "send":
					if(!$this->bocompose->send($formData))
					{
						$this->compose();
						return;
					}
					
					#$linkData = array
					#(
					#	'mailbox'	=> $GLOBALS['HTTP_GET_VARS']['mailbox'],
					#	'startMessage'	=> '1'
					#);
					#$link = $GLOBALS['phpgw']->link('/felamimail/index.php',$linkData);
					#$GLOBALS['phpgw']->redirect($link);
					#$GLOBALS['phpgw']->common->phpgw_exit();
					if($this->mailPreferences['messageNewWindow'])
					{
						print "<script type=\"text/javascript\">window.close();</script>";
					}
					else
					{       
						ExecMethod('felamimail.uifelamimail.viewMainScreen');
					}
					break;
			}
		}
		
		function compose($_focusElement="to")
		{
			// read the data from session
			// all values are empty for a new compose window
			$sessionData = $this->bocompose->getSessionData();
			$preferences = ExecMethod('felamimail.bopreferences.getPreferences');
			#_debug_array($preferences);
			
			// is the to address set already?
			if (!empty($GLOBALS['HTTP_GET_VARS']['send_to']))
			{
				$sessionData['to'] = base64_decode($_GET['send_to']);
			}
			
			$this->display_app_header();
			
			$this->t->set_file(array("composeForm" => "composeForm.tpl"));
			$this->t->set_block('composeForm','header','header');
			$this->t->set_block('composeForm','body_input');
			$this->t->set_block('composeForm','attachment','attachment');
			$this->t->set_block('composeForm','attachment_row','attachment_row');
			$this->t->set_block('composeForm','attachment_row_bold');
			
			$this->translate();
			
			$this->t->set_var("link_addressbook",$GLOBALS['phpgw']->link('/felamimail/addressbook.php'));
			$this->t->set_var("focusElement",$_focusElement);

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
				'menuaction'	=> 'felamimail.uicompose.action',
				'composeid'	=> $this->composeID
			);
			$this->t->set_var("link_action",$GLOBALS['phpgw']->link('/index.php',$linkData));
			$this->t->set_var('folder_name',$this->bofelamimail->sessionData['mailbox']);

			// check for some error messages from last posting attempt
			if($errorInfo = $this->bocompose->getErrorInfo())
			{
				$this->t->set_var('errorInfo',"<font color=\"red\"><b>$errorInfo</b></font>");
			}
			else
			{
				$this->t->set_var('errorInfo','&nbsp;');
			}
			
			// header
			$displayFrom = @htmlentities($preferences['emailAddress'][0][name].' <'.$preferences['emailAddress'][0][address].'>',ENT_QUOTES,$this->displayCharset);
			$this->t->set_var("from",$displayFrom);
			$this->t->set_var("to",@htmlentities($sessionData['to'],ENT_QUOTES,$this->displayCharset));
			$this->t->set_var("cc",@htmlentities($sessionData['cc'],ENT_QUOTES,$this->displayCharset));
			$this->t->set_var("bcc",@htmlentities($sessionData['bcc'],ENT_QUOTES,$this->displayCharset));
			$this->t->set_var("reply_to",@htmlentities($sessionData['reply_to'],ENT_QUOTES,$this->displayCharset));
			$this->t->set_var("subject",@htmlentities($sessionData['subject'],ENT_QUOTES,$this->displayCharset));
			$this->t->set_var('addressbookImage',$GLOBALS['phpgw']->common->image('phpgwapi/templates/phpgw_website','users'));
			$this->t->pparse("out","header");

			// body
			$this->t->set_var("body",$sessionData['body']);
			$this->t->set_var("signature",$sessionData['signature']);
			$this->t->pparse("out","body_input");

			// attachments
			if (is_array($sessionData['attachments']) && count($sessionData['attachments']) > 0)
			{
				$this->t->set_var('row_color',$this->rowColor[0]);
				$this->t->set_var('name',lang('name'));
				$this->t->set_var('type',lang('type'));
				$this->t->set_var('size',lang('size'));
				$this->t->parse('attachment_rows','attachment_row_bold',True);
				while (list($key,$value) = each($sessionData['attachments']))
				{
					#print "$key : $value<br>";
					$this->t->set_var('row_color',$this->rowColor[($key+1)%2]);
					$this->t->set_var('name',$value['name']);
					$this->t->set_var('type',$value['type']);
					$this->t->set_var('size',$value['size']);
					$this->t->set_var('attachment_number',$key);
					$this->t->parse('attachment_rows','attachment_row',True);
				}
			}
			else
			{
				$this->t->set_var('attachment_rows','');
			}
			
			$this->t->pparse("out","attachment");
		}

		function display_app_header()
		{
			$GLOBALS['phpgw']->common->phpgw_header();
			if(!$this->mailPreferences['messageNewWindow'])
				echo parse_navbar();
		}
		
		function forward()
		{
			$replyID = $GLOBALS['HTTP_GET_VARS']['reply_id'];
			$partID  = $_GET['part_id'];

			if (!empty($replyID))
			{
				// this fill the session data with the values from the original email
				$this->bocompose->getForwardData($replyID, $partID, 
					$this->bofelamimail->sessionData['mailbox']);
			}
			$this->compose();
		}

		function reply()
		{
			$replyID = $_GET['reply_id'];
			$partID	 = $_GET['part_id'];
			if (!empty($replyID))
			{
				// this fill the session data with the values from the original email
				$this->bocompose->getReplyData('single', $replyID, $partID);
			}
			$this->compose(@htmlentities('body'));
		}
		
		function replyAll()
		{
			$replyID = $GLOBALS['HTTP_GET_VARS']['reply_id'];
			$partID	 = $_GET['part_id'];
			if (!empty($replyID))
			{
				// this fill the session data with the values from the original email
				$this->bocompose->getReplyData('all', $replyID, $partID);
			}
			$this->compose('body');
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
			
			$this->t->set_var("th_bg",$GLOBALS['phpgw_info']["theme"]["th_bg"]);
			$this->t->set_var("bg01",$GLOBALS['phpgw_info']["theme"]["bg01"]);
			$this->t->set_var("bg02",$GLOBALS['phpgw_info']["theme"]["bg02"]);
			$this->t->set_var("bg03",$GLOBALS['phpgw_info']["theme"]["bg03"]);
		}
}

?>
