<?php
	/***************************************************************************\
	* eGroupWare - FeLaMiMail                                                   *
	* http://www.linux-at-work.de                                               *
	* http://www.phpgw.de                                                       *
	* http://www.egroupware.org                                                 *
	* Written by : Lars Kneschke [lkneschke@linux-at-work.de]                   *
	* -------------------------------------------------                         *
	* This program is free software; you can redistribute it and/or modify it   *
	* under the terms of the GNU General Public License as published by the     *
	* Free Software Foundation; version 2 of the License.                       *
	\***************************************************************************/
	/* $Id$ */

	class uicompose
	{

		var $public_functions = array
		(
			'action'		=> True,
			'compose'		=> True,
			'composeFromDraft'	=> True,
			'getAttachment'		=> True,
			'fileSelector'		=> True,
			'forward'		=> True,
			'reply'			=> True,
			'replyAll'		=> True,
			'selectFolder'		=> True,
		);
		
		var $destinations = array(
			'to' 		=> 'to',
			'cc'		=> 'cc',
			'bcc'		=> 'bcc',
			'replyto'	=> 'replyto',
			'folder'	=> 'folder'
		);

		function uicompose()
		{
			$this->displayCharset   = $GLOBALS['egw']->translation->charset();
			if (!isset($_POST['composeid']) && !isset($_GET['composeid']))
			{
				// create new compose session
				$this->bocompose   =& CreateObject('felamimail.bocompose','',$this->displayCharset);
				$this->composeID = $this->bocompose->getComposeID();
			}
			else
			{
				// reuse existing compose session
				if (isset($_POST['composeid']))
					$this->composeID = $_POST['composeid'];
				else
					$this->composeID = $_GET['composeid'];
				$this->bocompose   =& CreateObject('felamimail.bocompose',$this->composeID,$this->displayCharset);
			}			
			$this->t 		=& CreateObject('phpgwapi.Template',EGW_APP_TPL);
			$this->bofelamimail	=& CreateObject('felamimail.bofelamimail',$this->displayCharset);
			$this->mailPreferences  = ExecMethod('felamimail.bopreferences.getPreferences');

			$this->t->set_unknowns('remove');
			
			$this->rowColor[0] = $GLOBALS['egw_info']["theme"]["bg01"];
			$this->rowColor[1] = $GLOBALS['egw_info']["theme"]["bg02"];


		}
		
		function unhtmlentities ($string)
		{
			$trans_tbl = get_html_translation_table (HTML_ENTITIES);
			$trans_tbl = array_flip ($trans_tbl);
			return strtr ($string, $trans_tbl);
		}

		function action()
		{
			$formData['identity']	= (int)$_POST['identity'];

			foreach($_POST['destination'] as $key => $destination) {
				if(!empty($_POST['address'][$key])) {
					if($destination == 'folder') {
						$formData[$destination][] = $GLOBALS['egw']->translation->convert($_POST['address'][$key], $this->charset, 'UTF7-IMAP');
					} else {
						$formData[$destination][] = $_POST['address'][$key];
					}
				}
			}

			$formData['reply_to'] 	= $this->bocompose->stripSlashes($_POST['reply_to']);
			$formData['subject'] 	= $this->bocompose->stripSlashes($_POST['subject']);
			$formData['body'] 	= $this->bocompose->stripSlashes($_POST['body']);
			$formData['priority'] 	= $this->bocompose->stripSlashes($_POST['priority']);
			$formData['signatureID'] = (int)$_POST['signatureID'];
			$formData['mimeType']	= $this->bocompose->stripSlashes($_POST['mimeType']);
			$formData['disposition'] = (bool)$_POST['disposition'];
			$formData['to_infolog'] = $_POST['to_infolog'];
			//$formData['mailbox']	= $_GET['mailbox'];

			if((bool)$_POST['saveAsDraft'] == true) {
				// save as draft
				$this->bocompose->saveAsDraft($formData);
			} else {
				if(!$this->bocompose->send($formData)) {
					$this->compose();
					return;
				}
			}
					
			#$GLOBALS['egw']->common->egw_exit();
			print "<script type=\"text/javascript\">window.close();</script>";
		}

		function compose($_focusElement='to')
		{
			// read the data from session
			// all values are empty for a new compose window
			$sessionData = $this->bocompose->getSessionData();
			if (is_array($_GET['preset']))
			{
				if ($_GET['preset']['file'] && is_readable($_GET['preset']['file']))
				{
					$this->bocompose->addAttachment(array_merge($sessionData,$_GET['preset']));
					$sessionData = $this->bocompose->getSessionData();
				}
				foreach(array('to','cc','bcc','subject','body') as $name)
				{
					if ($_GET['preset'][$name]) $sessionData[$name] = $_GET['preset'][$name];
				}
			}
			$preferences = ExecMethod('felamimail.bopreferences.getPreferences');
			#_debug_array($preferences);
			
			// is the to address set already?
			if (!empty($_GET['send_to']))
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
			$this->t->set_block('composeForm','destination_row');
			$this->t->set_block('composeForm','simple_text');
			
			$this->translate();
			
			$this->t->set_var("link_addressbook",$GLOBALS['egw']->link('/index.php',array(
				'menuaction' => 'addressbook.uicontacts.emailpopup',
			)));
			$this->t->set_var("focusElement",$_focusElement);

			$linkData = array
			(
				'menuaction'	=> 'felamimail.uicompose.selectFolder',
			);
			$this->t->set_var('folder_select_url',$GLOBALS['egw']->link('/index.php',$linkData));

			$linkData = array
			(
				'menuaction'	=> 'felamimail.uicompose.fileSelector',
				'composeid'	=> $this->composeID
			);
			$this->t->set_var('file_selector_url',$GLOBALS['egw']->link('/index.php',$linkData));

			$linkData = array
			(
				'menuaction'	=> 'felamimail.uicompose.action',
				'composeid'	=> $this->composeID
			);
			$this->t->set_var("link_action",$GLOBALS['egw']->link('/index.php',$linkData));
			$this->t->set_var('folder_name',$this->bofelamimail->sessionData['mailbox']);
			$this->t->set_var('compose_id',$this->composeID);

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
			$allIdentities = $preferences->getIdentity();
			#_debug_array($allIdentities);
			$defaultIdentity = 0;
			foreach($allIdentities as $key => $singleIdentity) {
				$identities[$key] = $singleIdentity->realName.' <'.$singleIdentity->emailAddress.'>';
				if($singleIdentity->default)
					$defaultIdentity = $key;
			}
			$selectFrom = $GLOBALS['egw']->html->select('identity', $defaultIdentity, $identities, true, "style='width:100%;'");			
			$this->t->set_var('select_from', $selectFrom);

			// from, to, cc
			$this->t->set_var('img_clear_left', $GLOBALS['egw']->common->image('felamimail','clear_left'));
			$this->t->set_var('img_fileopen', $GLOBALS['egw']->common->image('phpgwapi','fileopen'));
			$this->t->set_var('img_mail_send', $GLOBALS['egw']->common->image('felamimail','mail_send'));
			$this->t->set_var('img_attach_file', $GLOBALS['egw']->common->image('felamimail','attach'));
			$this->t->set_var('ajax-loader', $GLOBALS['egw']->common->image('felamimail','ajax-loader'));
			$this->t->set_var('img_fileexport', $GLOBALS['egw']->common->image('felamimail','fileexport'));
			
			$destinationRows = 0;
			foreach(array('to','cc','bcc') as $destination) {
				foreach((array)$sessionData[$destination] as $key => $value) {
					$selectDestination = $GLOBALS['egw']->html->select('destination[]', $destination, $this->destinations, false, "style='width: 100%;' onchange='fm_compose_changeInputType(this)'");
					$this->t->set_var('select_destination', $selectDestination);
					$this->t->set_var('address', @htmlentities($value, ENT_QUOTES, $this->displayCharset));
					$this->t->parse('destinationRows','destination_row',True);
					$destinationRows++;
				}
			}
			while($destinationRows < 3) {
				// and always add one empty row
				$selectDestination = $GLOBALS['egw']->html->select('destination[]', 'to', $this->destinations, false, "style='width: 100%;' onchange='fm_compose_changeInputType(this)'");
				$this->t->set_var('select_destination', $selectDestination);
				$this->t->set_var('address', '');
				$this->t->parse('destinationRows','destination_row',True);
				$destinationRows++;
			}
			// and always add one empty row
			$selectDestination = $GLOBALS['egw']->html->select('destination[]', 'to', $this->destinations, false, "style='width: 100%;' onchange='fm_compose_changeInputType(this)'");
			$this->t->set_var('select_destination', $selectDestination);
			$this->t->set_var('address', '');
			$this->t->parse('destinationRows','destination_row',True);

			$this->t->set_var("cc",@htmlentities($sessionData['cc'],ENT_QUOTES,$this->displayCharset));
			$this->t->set_var("bcc",@htmlentities($sessionData['bcc'],ENT_QUOTES,$this->displayCharset));
			$this->t->set_var("reply_to",@htmlentities($sessionData['reply_to'],ENT_QUOTES,$this->displayCharset));
			$this->t->set_var("subject",@htmlentities($sessionData['subject'],ENT_QUOTES,$this->displayCharset));
			$this->t->set_var('addressbookImage',$GLOBALS['egw']->common->image('phpgwapi/templates/phpgw_website','users'));
			$this->t->set_var('infologImage',$GLOBALS['egw']->html->image('felamimail','to_infolog',lang('Save as infolog'),'width="17px" height="17px" valign="middle"' ));
			$this->t->set_var('lang_save_as_infolog',lang('Save as infolog'));
			$this->t->pparse("out","header");
			

			// body
			if($sessionData['mimeType'] == 'html') {
				$style="border:0px; width:100%; height:400px;";
			#	$this->t->set_var('tinymce', $GLOBALS['egw']->html->tinymceQuick('body', 'simple', $sessionData['body'], $style));
				$cleanHTMLBody = $this->getCleanHTML($sessionData['body']);
				$this->t->set_var('tinymce', $GLOBALS['egw']->html->fckEditorQuick('body', 'simple', $cleanHTMLBody));
				$this->t->set_var('mimeType', 'html');
			} else {
				$style="border:0px; width:100%; height:400px;";
			#	$this->t->set_var('tinymce', $GLOBALS['egw']->html->tinymceQuick('body', 'ascii', $sessionData['body'], $style));
				$this->t->set_var('tinymce', $GLOBALS['egw']->html->fckEditorQuick('body', 'ascii', $sessionData['body']));
				$this->t->set_var('mimeType', 'text');
			}
			
			$signatures = ExecMethod('felamimail.bopreferences.getListOfSignatures');

			$selectSignatures = array(
				'-1' => lang('no signature')
			);
			foreach($signatures as $signature) {
				$selectSignatures[$signature['signatureid']] = $signature['description'];
			}
			$selectBox = $GLOBALS['egw']->html->select('signatureID', $sessionData['signatureID'], $selectSignatures, true, "style='width: 100%;' onchange='fm_compose_changeInputType(this)'");
			$this->t->set_var("select_signature", $selectBox);
			$this->t->pparse("out","body_input");

			// attachments
			if (is_array($sessionData['attachments']) && count($sessionData['attachments']) > 0)
			{
				$imgClearLeft	=  $GLOBALS['egw']->common->image('felamimail','clear_left');
	
				foreach((array)$sessionData['attachments'] as $id => $attachment) {
					$tempArray = array (
						'1' => $attachment['name'],
						'2' => $attachment['type'], '.2' => "style='text-align:center;'",
						'3' => $attachment['size'],
						'4' => "<img src='$imgClearLeft' onclick=\"fm_compose_deleteAttachmentRow(this,'$_composeID','$id')\">"
					);
					$tableRows[] = $tempArray;
				}
				
				if(count($tableRows) > 0) {
					if(!is_object($GLOBALS['egw']->html)) {
						$GLOBALS['egw']->html =& CreateObject('phpgwapi.html');
					}
					$table = $GLOBALS['egw']->html->table($tableRows, "style='width:100%'");
				}
				$this->t->set_var('attachment_rows',$table);
			}
			else
			{
				$this->t->set_var('attachment_rows','');
			}
			
			$this->t->pparse("out","attachment");
		}

		function composeFromDraft() {
			$icServer = (int)$_GET['icServer'];
			$folder = base64_decode($_GET['folder']);
			$replyID = $_GET['uid'];

			if (!empty($folder) && !empty($replyID) ) {
				// this fill the session data with the values from the original email
				$this->bocompose->getDraftData($icServer, $folder, $replyID);
			}
			$this->compose('body');
		}
		

		function display_app_header()
		{
			if(!@is_object($GLOBALS['egw']->js))
			{
				$GLOBALS['egw']->js =& CreateObject('phpgwapi.javascript');
			}
			$GLOBALS['egw']->js->validate_file('jscode','composeMessage','felamimail');
			$GLOBALS['egw']->js->set_onload('javascript:initAll();');
			$GLOBALS['egw_info']['flags']['include_xajax'] = True;
			
			$GLOBALS['egw']->common->egw_header();
		}
		
		function fileSelector()
		{
			if(is_array($_FILES["addFileName"])) {
				#phpinfo();
				#_debug_array($_FILES);
				if($_FILES['addFileName']['error'] == $UPLOAD_ERR_OK) {
					$formData['name']	= $_FILES['addFileName']['name'];
					$formData['type']	= $_FILES['addFileName']['type'];
					$formData['file']	= $_FILES['addFileName']['tmp_name'];
					$formData['size']	= $_FILES['addFileName']['size'];
					$this->bocompose->addAttachment($formData);
					print "<script type='text/javascript'>window.close();</script>";
				} else {
					print "<script type='text/javascript'>document.getElementById('fileSelectorDIV1').style.display = 'inline';document.getElementById('fileSelectorDIV2').style.display = 'none';</script>";
				}
			}
			
			if(!@is_object($GLOBALS['egw']->js))
			{
				$GLOBALS['egw']->js =& CreateObject('phpgwapi.javascript');
			}
			$GLOBALS['egw']->js->validate_file('dhtmlxtree','js/dhtmlXCommon');
			$GLOBALS['egw']->js->validate_file('dhtmlxtree','js/dhtmlXTree');                        
			$GLOBALS['egw']->js->validate_file('jscode','composeMessage','felamimail');
			$GLOBALS['egw']->common->egw_header();

			#$uiwidgets		=& CreateObject('felamimail.uiwidgets');
			
			$this->t->set_file(array("composeForm" => "composeForm.tpl"));
			$this->t->set_block('composeForm','fileSelector','fileSelector');
			
			$this->translate();

			$linkData = array
			(
				'menuaction'	=> 'felamimail.uicompose.fileSelector',
				'composeid'	=> $this->composeID
			);
			$this->t->set_var('file_selector_url', $GLOBALS['egw']->link('/index.php',$linkData));

			$maxUploadSize = ini_get('upload_max_filesize');
			$this->t->set_var('max_uploadsize', $maxUploadSize);

			$this->t->set_var('ajax-loader', $GLOBALS['egw']->common->image('felamimail','ajax-loader'));

			$this->t->pparse("out","fileSelector");
		}

		function forward() {
			$icServer = (int)$_GET['icServer'];
			$folder = base64_decode($_GET['folder']);
			$replyID = $_GET['reply_id'];
			$partID  = $_GET['part_id'];

			if (!empty($replyID))
			{
				// this fill the session data with the values from the original email
				$this->bocompose->getForwardData($icServer, $folder, $replyID, $partID);
			}
			$this->compose();
		}

		function getAttachment()
		{	
			$bocompose  =& CreateObject('felamimail.bocompose', $_GET['_composeID']);			
			$attachment =  $bocompose->sessionData['attachments'][$_GET['attID']] ;
			header ("Content-Type: ".$attachment['type']."; name=\"". $this->bofelamimail->decode_header($attachment['name']) ."\"");
			header ("Content-Disposition: inline; filename=\"". $this->bofelamimail->decode_header($attachment['name']) ."\"");
			header("Expires: 0");
			header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
			header("Pragma: public"); 
			$fp = fopen($attachment['file'], 'rb');
			fpassthru($fp);
			$GLOBALS['egw']->common->egw_exit();
			exit;
																			
		}

		function getCleanHTML($_body)
		{
			$nonDisplayAbleCharacters = array('[\016]','[\017]',
					'[\020]','[\021]','[\022]','[\023]','[\024]','[\025]','[\026]','[\027]',
					'[\030]','[\031]','[\032]','[\033]','[\034]','[\035]','[\036]','[\037]');

			$kses =& CreateObject('phpgwapi.kses');
			$kses->AddHTML('p', array(
					'align'	=> array('minlen' =>   1, 'maxlen' =>  10)
				)
			);
			$kses->AddHTML('div', array(
					'align'	=> array('minlen' =>   1, 'maxlen' =>  10)
				)
			);
			$kses->AddHTML('br');
			$kses->AddHTML('b');
			$kses->AddHTML('center');
			$kses->AddHTML('em');
			$kses->AddHTML('font', array(
					'color'	=> array('minlen' =>   1, 'maxlen' =>  10)
				)
			);
			$kses->AddHTML('i');
			$kses->AddHTML("s");
			$kses->AddHTML('strike');
			$kses->AddHTML('strong');
			$kses->AddHTML('u');
			$kses->AddHTML('hr', array(
					"class"		=> array('maxlen' => 20),
					"style"		=> array('minlen' => 1),
				)
			);
			$kses->AddHTML("ul");
			$kses->AddHTML('ol', array(
					"type"	=> array('maxlen' => 20)
				)
			);
			$kses->AddHTML("li");
			$kses->AddHTML("h1");
			$kses->AddHTML("h2");
			$kses->AddHTML('a', array(
					"href" 		=> array('maxlen' => 145, 'minlen' => 10),
					"name" 		=> array('minlen' => 2),
					'target'	=> array('maxlen' => 10)
				)
			);
			$kses->AddHTML('pre', array(
					"wrap" => array('maxlen' => 10)
				)
			);
			
			$kses->AddHTML(
				"blockquote",array(
					"class"	=> array("minlen" =>   1, 'maxlen' =>  20),
					"style"	=> array("minlen" =>   1),
					"cite"	=> array('maxlen' => 30),
					"type"	=> array('maxlen' => 10),
					"dir"	=> array("minlen" =>   1, 'maxlen' =>  10)
				)
			);

			$body	= $kses->Parse($_body);

			$body	= preg_replace($nonDisplayAbleCharacters, '', $body);
			
			return $body;
		}
		
		function selectFolder()
		{
			if(!@is_object($GLOBALS['egw']->js))
			{
				$GLOBALS['egw']->js =& CreateObject('phpgwapi.javascript');
			}
			$GLOBALS['egw']->js->validate_file('dhtmlxtree','js/dhtmlXCommon');
			$GLOBALS['egw']->js->validate_file('dhtmlxtree','js/dhtmlXTree');                        
			$GLOBALS['egw']->js->validate_file('jscode','composeMessage','felamimail');
			$GLOBALS['egw']->common->egw_header();

			$bofelamimail		=& CreateObject('felamimail.bofelamimail',$this->displayCharset);
			$uiwidgets		=& CreateObject('felamimail.uiwidgets');
			$connectionStatus	= $bofelamimail->openConnection();

			$folderObjects = $bofelamimail->getFolderObjects(false);
			$folderTree = $uiwidgets->createHTMLFolder
			(
				$folderObjects,
				'INBOX',
				0,
				lang('IMAP Server'),
				$mailPreferences['username'].'@'.$mailPreferences['imapServerAddress'],
				'divFolderTree',
				false,
				true
			);
			print '<div id="divFolderTree" style="overflow:auto; width:320px; height:450px; margin-bottom: 0px;padding-left: 0px; padding-top:0px; z-index:100; border : 1px solid Silver;"></div>';
			print $folderTree;
		}

		function reply() {
			$icServer = (int)$_GET['icServer'];
			$folder = base64_decode($_GET['folder']);
			$replyID = $_GET['reply_id'];
			$partID	 = $_GET['part_id'];
			if (!empty($folder) && !empty($replyID) ) {
				// this fill the session data with the values from the original email
				$this->bocompose->getReplyData('single', $icServer, $folder, $replyID, $partID);
			}
			$this->compose('body');
		}
		
		function replyAll() {
			$icServer = (int)$_GET['icServer'];
			$folder = base64_decode($_GET['folder']);
			$replyID = $_GET['reply_id'];
			$partID	 = $_GET['part_id'];
			if (!empty($folder) && !empty($replyID) ) {
				// this fill the session data with the values from the original email
				$this->bocompose->getReplyData('all', $icServer, $folder, $replyID, $partID);
			}
			$this->compose('body');
		}
		
		function translate() {
			$this->t->set_var("lang_message_list",lang('Message List'));
			$this->t->set_var("lang_to",lang('to'));
			$this->t->set_var("lang_cc",lang('cc'));
			$this->t->set_var("lang_bcc",lang('bcc'));
			$this->t->set_var("lang_identity",lang('identity'));
			$this->t->set_var("lang_reply_to",lang('reply to'));
			$this->t->set_var("lang_subject",lang('subject'));
			$this->t->set_var("lang_addressbook",lang('addressbook'));
			$this->t->set_var("lang_search",lang('search'));
			$this->t->set_var("lang_send",lang('send'));
			$this->t->set_var('lang_save_as_draft',lang('save as draft'));
			$this->t->set_var("lang_back_to_folder",lang('back to folder'));
			$this->t->set_var("lang_attachments",lang('attachments'));
			$this->t->set_var("lang_add",lang('add'));
			$this->t->set_var("lang_remove",lang('remove'));
			$this->t->set_var("lang_priority",lang('priority'));
			$this->t->set_var("lang_normal",lang('normal'));
			$this->t->set_var("lang_high",lang('high'));
			$this->t->set_var("lang_low",lang('low'));
			$this->t->set_var("lang_signature",lang('signature'));
			$this->t->set_var("lang_select_folder",lang('select folder'));
			$this->t->set_var('lang_max_uploadsize',lang('max uploadsize'));
			$this->t->set_var('lang_adding_file_please_wait',lang('Adding file to message. Please wait!'));
			$this->t->set_var('lang_receive_notification',lang('Receive notification'));
			$this->t->set_var('lang_no_address_set',lang('can not send message. no recipient defined!'));
			
			$this->t->set_var("th_bg",$GLOBALS['egw_info']["theme"]["th_bg"]);
			$this->t->set_var("bg01",$GLOBALS['egw_info']["theme"]["bg01"]);
			$this->t->set_var("bg02",$GLOBALS['egw_info']["theme"]["bg02"]);
			$this->t->set_var("bg03",$GLOBALS['egw_info']["theme"]["bg03"]);
		}
}

?>
