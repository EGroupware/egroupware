<?php
	/***************************************************************************\
	* eGroupWare - FeLaMiMail                                                   *
	* http://www.egroupware.org                                                 *
	* Written by : Lars Kneschke [lkneschke@linux-at-work.de]                   *
	* -------------------------------------------------                         *
	* Copyright (c) 2004, Lars Kneschke					    *
	* All rights reserved.							    *
	*									    *
	* Redistribution and use in source and binary forms, with or without	    *
	* modification, are permitted provided that the following conditions are    *
	* met:									    *
	*									    *
	*	* Redistributions of source code must retain the above copyright    *
	*	notice, this list of conditions and the following disclaimer.	    *
	*	* Redistributions in binary form must reproduce the above copyright *
	*	notice, this list of conditions and the following disclaimer in the *
	*	documentation and/or other materials provided with the distribution.*
	*	* Neither the name of the FeLaMiMail organization nor the names of  *
	*	its contributors may be used to endorse or promote products derived *
	*	from this software without specific prior written permission.	    *
	*									    *
	* THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS 	    *
	* "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED *
	* TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR*
	* PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR 	    *
	* CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL,	    *
	* EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, 	    *
	* PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR 	    *
	* PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF    *
	* LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING 	    *
	* NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS 	    *
	* SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.		    *
	\***************************************************************************/

	/* $Id$ */

	/**
	* a class containing javascript enhanced html widgets
	*
	* @package FeLaMiMail
	* @author Lars Kneschke
	* @version 1.35
	* @copyright Lars Kneschke 2004
	* @license http://www.opensource.org/licenses/bsd-license.php BSD
	*/
	class uiwidgets
	{
		/**
		* the contructor
		*
		*/
		function uiwidgets()
		{
			$template = CreateObject('phpgwapi.Template',common::get_tpl_dir('felamimail'));
			$this->template = $template;
			$this->template->set_file(array("body" => 'uiwidgets.tpl'));
			$this->charset = $GLOBALS['egw']->translation->charset();

			if (!is_object($GLOBALS['egw']->html)) {
				$GLOBALS['egw']->html = CreateObject('phpgwapi.html');
			}
		}

		function encodeFolderName($_folderName)
		{
			return $GLOBALS['egw']->translation->convert($_folderName, 'UTF7-IMAP', $this->charset);
		}

		/**
		* create a folder tree
		*
		* this function will create a foldertree based on javascript
		* on click the sorounding form gets submitted
		*
		* @param _folders array containing the list of folders
		* @param _selected string containing the selected folder
		* @param _selectedFolderCount integer contains the count of unread messages in the selected folder
		* @param _topFolderName string containing the top folder name
		* @param _topFolderDescription string containing the description for the top folder
		* @param _formName string name of the sorounding form
		* @param _hiddenVar string hidden form value, transports the selected folder
		* @param _useDisplayCharset bool use displaycharset for foldernames (used by uisieve only)
		*
		* @return string the html code, to be added into the template
		*/
		function createHTMLFolder($_folders, $_selected, $_selectedFolderCount, $_topFolderName, $_topFolderDescription, $_divName, $_displayCheckBox, $_useDisplayCharset = false) {
			// create a list of all folders, also the ones which are not subscribed
 			foreach($_folders as $key => $obj) {
				$folderParts = explode($obj->delimiter,$key);
				if(is_array($folderParts)) {
					$partCount = count($folderParts);
					$string = '';
					for($i = 0; $i < $partCount-1; $i++) {
						if(!empty($string)) $string .= $obj->delimiter;
						$string .= $folderParts[$i];
						if(!$allFolders[$string]) {
							$allFolders[$string] = clone($obj);
							$allFolders[$string]->folderName = $string;
							$allFolders[$string]->shortFolderName = array_pop(explode($obj->delimiter, $string));
							$allFolders[$string]->displayName = $this->encodeFolderName($allFolders[$string]->folderName);
							$allFolders[$string]->shortDisplayName = $this->encodeFolderName($allFolders[$string]->shortFolderName);
						}
					}
				}
				$allFolders[$key] = $obj;
			}

			$folderImageDir = $GLOBALS['egw_info']['server']['webserver_url'].'/phpgwapi/templates/default/images/';

			// careful! "d = new..." MUST be on a new line!!!
			$folder_tree_new  = '<link rel="STYLESHEET" type="text/css" href="'.$GLOBALS['egw_info']['server']['webserver_url'].'/phpgwapi/js/dhtmlxtree/css/dhtmlXTree.css">';
			$folder_tree_new .= "<script type='text/javascript'>";
			$folder_tree_new .= "tree=new dhtmlXTreeObject('$_divName','100%','100%',0);";
			$folder_tree_new .= "tree.setImagePath('$folderImageDir/dhtmlxtree/');";
			if($_displayCheckBox) {
				$folder_tree_new .= "tree.enableCheckBoxes(1);";
				$folder_tree_new .= "tree.setOnCheckHandler('onCheckHandler');";
			}
			// beware this is "old" dhtmlx Code
			$folder_tree_new .= "tree.openFuncHandler=1;";
			$folder_tree_new .= "tree.setOnOpenHandler(OnLoadingStart);";
			// this is code for their latest codebase, since "old" stuff is deprecated
			/*
			$folder_tree_new .='tree.attachEvent("onOpenStart", onOpenStartHandler);';
			$folder_tree_new .='tree.attachEvent("onOpenEnd", onOpenEndHandler);';
			*/
			#$topFolderBase64 = base64_encode('--topfolder--');
			$topFolderBase64 = '--topfolder--';
			$folder_tree_new .= "tree.insertNewItem(0,'$topFolderBase64','$_topFolderName',onNodeSelect,'thunderbird.png','thunderbird.png','thunderbird.png','CHILD,TOP');\n";

			#foreach($_folders as $key => $obj)
			#_debug_array($allFolders);
			foreach($allFolders as $longName => $obj) {
				$messageCount = '';
				$image1 = "'folderClosed.gif'";
				$image2 = "0";
				$image3 = "0";

				$folderParts = explode($obj->delimiter, $longName);

				//get rightmost folderpart
				$shortName = array_pop($folderParts);

				// the rest of the array is the name of the parent
				$parentName = implode((array)$folderParts,$obj->delimiter);
				if(empty($parentName)) $parentName = '--topfolder--';

				$entryOptions = 'CHILD,CHECKED';

				$displayName	= @htmlspecialchars($obj->shortDisplayName, ENT_QUOTES, $this->charset);
				$userData	= $displayName;

				$parentName	= @htmlspecialchars($parentName, ENT_QUOTES, $this->charset);

				// highlight currently selected mailbox
				if ($_selected == $longName) {
					$entryOptions .= ',SELECT';
					if($_selectedFolderCount > 0) {
						$messageCount = "&nbsp;($_selectedFolderCount)";
						$displayName = "<b>$displayName&nbsp;($_selectedFolderCount)</b>";
					}
				}

				if($_useDisplayCharset == true) {
					$folderName	= $GLOBALS['egw']->translation->convert($obj->folderName, 'UTF7-IMAP', $this->charset);
					$folderName	= @htmlspecialchars($folderName, ENT_QUOTES, $this->charset);
				} else {
					$folderName	= @htmlspecialchars($obj->folderName, ENT_QUOTES, $this->charset);
				}
				// give INBOX a special foldericon
				if ($folderName == 'INBOX') {
					$image1 = "'kfm_home.png'";
					$image2 = "'kfm_home.png'";
					$image3 = "'kfm_home.png'";
				}

				$search		= array('\\');
				$replace	= array('\\\\');
				$parentName	= str_replace($search, $replace, $parentName);
				$folderName	= str_replace($search, $replace, $folderName);

				$folder_tree_new .= "tree.insertNewItem('$parentName','$folderName','$displayName',onNodeSelect,$image1,$image2,$image3,'$entryOptions');";
				$folder_tree_new .= "tree.setUserData('$folderName','folderName', '$userData');";
				if($_displayCheckBox) {
					$folder_tree_new .= "tree.setCheck('$folderName','".(int)$obj->subscribed."');";
				}
			}

			$selected = @htmlspecialchars($_selected, ENT_QUOTES, $this->charset);
			#$selected = base64_encode($_selected);

			$folder_tree_new.= "tree.closeAllItems(0);tree.openItem('$selected');</script>";

			return $folder_tree_new;
		}

		function createSignatureTable($_signatureList)
		{
			$linkData = array
			(
				'menuaction'    => 'felamimail.uipreferences.editSignature'
			);
			$urlEditSignature = $GLOBALS['egw']->link('/index.php',$linkData);

			if(is_array($_signatureList) && !empty($_signatureList)) {
				foreach($_signatureList as $signature) {
					$description = ($signature['fm_defaultsignature'] == true) ? $signature['fm_description'] .' ('. lang('default') .')' : $signature['fm_description'];
					$tableRows[] = array(
						'1'	=> $signature['fm_signatureid'] != -1 ? html::checkbox('signatureID', false, $signature['fm_signatureid']) : '',
						'.1'	=> 'style="width:30px"',
						'2'	=> '<a href="" onclick="egw_openWindowCentered(\''. $urlEditSignature ."&signatureID=".$signature['fm_signatureid']. '\',\'felamiMailACL\',\'600\',\'230\'); return false;">'. @htmlspecialchars($description, ENT_QUOTES, $this->charset) .'</a>',
					);
				}

				return html::table($tableRows, 'style="width:100%;"');
			}

			return '';
		}

		function createAccountDataTable($_identities)
		{
			$linkData = array
			(
				'menuaction'    => 'felamimail.uipreferences.editAccountData'
			);
			$urlEditAccountData = $GLOBALS['egw']->link('/index.php',$linkData);

			if(is_array($_identities) && !empty($_identities)) {
				foreach($_identities as $identity) {
					$description = $identity['id'].":".$identity['realName']." ".$identity['organization']." <".$identity['emailAddress'].">";
					$description = ($identity['default'] == true) ? $description .' ('. lang('default') .')' : $description;
					$tableRows[] = array(
						'1'	=> $identity['id'] != -1 ? html::checkbox('accountID', false, $identity['id']) : '',
						'.1'	=> 'style="width:30px"',
						'2'	=> '<a href="'. $urlEditAccountData ."&accountID=".$identity['id'].'">'. @htmlspecialchars($description, ENT_QUOTES, $this->charset) .'</a>',
					);
				}

				return html::table($tableRows, 'style="width:100%;"');
			}

			return '';
		}

		// $_folderType 0: normal imap folder 1: sent folder 2: draft folder 3: template folder
		// $_rowStyle felamimail or outlook
		function messageTable($_headers, $_folderType, $_folderName, $_readInNewWindow, $_rowStyle='felamimail')
		{
			$this->t = CreateObject('phpgwapi.Template',EGW_APP_TPL);
			$this->t->set_file(array("body" => 'mainscreen.tpl'));
			$this->t->set_block('body','header_row_felamimail');
			$this->t->set_block('body','header_row_outlook');
			$this->t->set_block('body','message_table');
			$timestamp7DaysAgo =
				mktime(date("H"), date("i"), date("s"), date("m"), date("d")-7, date("Y"));
			$timestampNow =
				mktime(date("H"), date("i"), date("s"), date("m"), date("d"), date("Y"));
			$dateToday = date("Y-m-d");


			$i=0;
			foreach((array)$_headers['header'] as $header)
			{
				//_debug_array($header);
				#if($i<10) {$i++;continue;}
				#if($i>20) {continue;} $i++;
				// create the listing of subjects
				$maxSubjectLength = 60;
				$maxAddressLength = 20;
				$maxSubjectLengthBold = 50;
				$maxAddressLengthBold = 14;

				$flags = "";
				if(!empty($header['recent'])) $flags .= "R";
				if(!empty($header['flagged'])) $flags .= "F";
				if(!empty($header['answered'])) $flags .= "A";
				if(!empty($header['forwarded'])) $flags .= "W";
				if(!empty($header['deleted'])) $flags .= "D";
				if(!empty($header['seen'])) $flags .= "S";

				$this->t->set_var('row_text', '');

				// the status icon
				if($header['deleted']) {
					$this->t->set_var('image_url',html::image('felamimail','kmmsgdel'));
				} elseif($header['recent']) {
					$this->t->set_var('image_url',html::image('felamimail','kmmsgnew'));
				} elseif($header['forwarded']) {
					$this->t->set_var('image_url',html::image('felamimail','kmmsgforwarded'));
				} elseif($header['answered']) {
					$this->t->set_var('image_url',html::image('felamimail','kmmsgreplied'));
				} elseif($header['seen']) {
					$this->t->set_var('image_url',html::image('felamimail','kmmsgread'));
				} else {
					$this->t->set_var('image_url',html::image('felamimail','kmmsgunseen'));
				}

				// the css for this row
				if($header['deleted']) {
					$this->t->set_var('row_css_class','header_row_D');
				} elseif($header['recent'] && !$header['seen']) {
					$this->t->set_var('row_css_class','header_row_R');
				} elseif($header['flagged']) {
					if($header['seen']) {
						$this->t->set_var('row_css_class','header_row_FS');
					} else {
						$this->t->set_var('row_css_class','header_row_F');
					}
				} elseif($header['seen']) {
					$this->t->set_var('row_css_class','header_row_S');
				} else {
					$this->t->set_var('row_css_class','header_row_');
				}

				// filter out undisplayable characters
				$search = array('[\016]','[\017]',
					'[\020]','[\021]','[\022]','[\023]','[\024]','[\025]','[\026]','[\027]',
					'[\030]','[\031]','[\032]','[\033]','[\034]','[\035]','[\036]','[\037]');
				$replace = '';

				$header['subject'] = preg_replace($search,$replace,$header['subject']);
				$header['subject'] = @htmlspecialchars($header['subject'],ENT_QUOTES,$this->displayCharset);
				// curly brackets get messed up by the template!
				$header['subject'] = str_replace(array('{','}'),array('&#x7B;','&#x7D;'),$header['subject']);

				if (!empty($header['subject'])) {
					// make the subject shorter if it is to long
					$fullSubject = $header['subject'];
					#if(strlen($header['subject']) > $maxSubjectLength)
					#{
					#	$header['subject'] = substr($header['subject'],0,$maxSubjectLength)."...";
					#}
					$this->t->set_var('header_subject', $header['subject']);
					#$this->t->set_var('attachments', $header['attachment']);
					$this->t->set_var('full_subject', $fullSubject);
				} else {
					$this->t->set_var('header_subject', @htmlspecialchars('('. lang('no subject') .')', ENT_QUOTES, $this->displayCharset));
				}

				#_debug_array($header);
				if($header['mimetype'] == 'multipart/mixed' ||
				   $header['mimetype'] == 'multipart/related' ||
				   substr($header['mimetype'],0,11) == 'application' ||
				   substr($header['mimetype'],0,5) == 'audio') {
					$image = html::image('felamimail','attach');
					$this->t->set_var('attachment_image', $image);
				} else {
					$this->t->set_var('attachment_image', '&nbsp;');
				}
				// show priority flag
				if ($header['priority'] < 3) {
					 $image = html::image('felamimail','prio_high');
				} elseif ($header['priority'] > 3) {
					$image = html::image('felamimail','prio_low');
				} else {
					$image = '';
				}
				$this->t->set_var('prio_image', $image);

				if ($_folderType > 0) {
					// sent or drafts or template folder
					if (!empty($header['to_name'])) {
						$sender_name	= $header['to_name'];
						$full_address	= $header['to_name'].' <'.$header['to_address'].'>';
					} else {
						$sender_name	= $header['to_address'];
						$full_address	= $header['to_address'];
					}
				} else {
					if (!empty($header['sender_name'])) {
						$sender_name	= $header['sender_name'];
						$full_address	= $header['sender_name'].' <'.$header['sender_address'].'>';
					} else {
						$sender_name	= $header['sender_address'];
						$full_address	= $header['sender_address'];
					}
				}

				$this->t->set_var('sender_name', @htmlspecialchars($sender_name, ENT_QUOTES, $this->charset));
				$this->t->set_var('full_address', @htmlspecialchars($full_address, ENT_QUOTES, $this->charset));

				$this->t->set_var('message_counter', $i);
				$this->t->set_var('message_uid', $header['uid']);

				if ($dateToday == date('Y-m-d', $header['date'])) {
 				    $this->t->set_var('date', $GLOBALS['egw']->common->show_date($header['date'],'H:i:s'));
				} else {
					$this->t->set_var('date', $GLOBALS['egw']->common->show_date($header['date'],$GLOBALS['egw_info']['user']['preferences']['common']['dateformat']));
				}
				$this->t->set_var('datetime', $GLOBALS['egw']->common->show_date($header['date']/*,$GLOBALS['egw_info']['user']['preferences']['common']['dateformat']*/));

				$this->t->set_var('size', $this->show_readable_size($header['size']));

				if($_folderType == 2 || $_folderType == 3) {
					$linkData = array (
						'menuaction'    => 'felamimail.uicompose.composeFromDraft',
						'icServer'	=> 0,
						'folder' 	=> base64_encode($_folderName),
						'uid'		=> $header['uid'],
						'id'		=> $header['id'],
					);
					$this->t->set_var('url_read_message', $GLOBALS['egw']->link('/index.php',$linkData));

					$windowName = 'composeFromDraft_'.$header['uid'];
					$this->t->set_var('read_message_windowName', $windowName);
				} else {
				#	_debug_array($header);
					$linkData = array (
						'menuaction'    => 'felamimail.uidisplay.display',
						'showHeader'	=> 'false',
						'mailbox'    => base64_encode($_folderName),
						'uid'		=> $header['uid'],
						'id'		=> $header['id'],
					);
					$this->t->set_var('url_read_message', $GLOBALS['egw']->link('/index.php',$linkData));

					$windowName = ($_readInNewWindow == 1 ? 'displayMessage' : 'displayMessage_'.$header['uid']);
					$this->t->set_var('read_message_windowName', $windowName);
				}

				if($_folderType > 0) {
					// sent or draft or template folder
					if(!empty($header['to_name'])) {
						list($mailbox, $host) = explode('@',$header['to_address']);
						$senderAddress  = imap_rfc822_write_address($mailbox,
								$host,
								$header['to_name']);
					} else {
						$senderAddress  = $header['to_address'];
					}
				} else {
					if(!empty($header['sender_name'])) {
						list($mailbox, $host) = explode('@',$header['sender_address']);
						$senderAddress  = imap_rfc822_write_address($mailbox,
								$host,
								$header['sender_name']);
					} else {
						$senderAddress  = $header['sender_address'];
					}
				}

				$linkData = array
				(
					'menuaction'    => 'felamimail.uicompose.compose',
					'send_to'	=> base64_encode($senderAddress)
				);
				$windowName = 'compose'.$header['uid'];
				$this->t->set_var('url_compose',"egw_openWindowCentered('".$GLOBALS['egw']->link('/index.php',$linkData)."','$windowName',700,egw_getWindowOuterHeight());");

				$linkData = array
				(
					'menuaction'   		=> 'addressbook.addressbook_ui.edit',
					'presets[email]'	=> urlencode($header['sender_address']),
					'presets[n_given]'	=> urlencode($header['sender_name']),
					'referer'		=> urlencode($_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING'])
				);
				//TODO: url_add_to_addressbook isn't in any of the templates.
				//If you want to use it, you need to adopt syntax to the new addressbook (popup)
				$this->t->set_var('url_add_to_addressbook',$GLOBALS['egw']->link('/index.php',$linkData));
				$this->t->set_var('msg_icon_sm',$msg_icon_sm);

				$this->t->set_var('phpgw_images',EGW_IMAGES);

				switch($_rowStyle) {
					case 'outlook':
						$this->t->parse('message_rows','header_row_outlook',True);
						break;
					default:
						$this->t->parse('message_rows','header_row_felamimail',True);
						break;
				}
			}
			$this->t->parse("out","message_table");

			return $this->t->get('out','message_table');
		}

		/**
		* create multiselectbox
		*
		* this function will create a multiselect box. Hard to describe! :)
		*
		* @param _selectedValues Array of values for already selected values(the left selectbox)
		* @param _predefinedValues Array of values for predefined values(the right selectbox)
		* @param _valueName name for the variable containing the selected values
		* @param _boxWidth the width of the multiselectbox( example: 100px, 100%)
		*
		* @returns the html code, to be added into the template
		*/
		function multiSelectBox($_selectedValues, $_predefinedValues, $_valueName, $_boxWidth="100%")
		{
			$this->template->set_block('body','multiSelectBox');

			if(is_array($_selectedValues))
			{
				foreach($_selectedValues as $key => $value)
				{
					$options .= "<option value=\"$key\" selected=\"selected\">".@htmlspecialchars($value,ENT_QUOTES)."</option>";
				}
				$this->template->set_var('multiSelectBox_selected_options',$options);
			}

			$options = '';
			if(is_array($_predefinedValues))
			{
				foreach($_predefinedValues as $key => $value)
				{
					if($key != $_selectedValues["$key"])
					$options .= "<option value=\"$key\">".@htmlspecialchars($value,ENT_QUOTES)."</option>";
				}
				$this->template->set_var('multiSelectBox_predefinded_options',$options);
			}

			$this->template->set_var('multiSelectBox_valueName', $_valueName);
			$this->template->set_var('multiSelectBox_boxWidth', $_boxWidth);


			return $this->template->fp('out','multiSelectBox');
		}

		function navbarButton($_imageName, $_imageAction, $_toolTip='', $_float='left')
		{
			$image = $GLOBALS['egw']->common->image('felamimail',$_imageName);
			$float = $_float == 'right' ? 'right' : 'left';

			return "<div class='navButton' style='float:$float;' onmousedown='this.className=\"navButtonActive\";' onmouseup='this.className=\"navButtonHover\";' onmouseout='this.className=\"navButton\";' onclick=\"$_imageAction\"><img style='width:16px; height:16px;' title='$_toolTip' src='$image' ></div>";
		}

		function navbarSeparator()
		{
			return '<div class="navSeparator"></div>';
		}

		/* Returns a string showing the size of the message/attachment */
		function show_readable_size($bytes, $_mode='short')
		{
			$bytes /= 1024;
			$type = 'k';

			if ($bytes / 1024 > 1)
			{
				$bytes /= 1024;
				$type = 'M';
			}

			if ($bytes < 10)
			{
				$bytes *= 10;
				settype($bytes, 'integer');
				$bytes /= 10;
			}
			else
				settype($bytes, 'integer');

			return $bytes . '&nbsp;' . $type ;
		}

		function tableView($_headValues, $_tableWidth="100%")
		{
			$this->template->set_block('body','tableView');
			$this->template->set_block('body','tableViewHead');

			if(is_array($_headValues))
			{
				foreach($_headValues as $head)
				{
					$this->template->set_var('tableHeadContent',$head);
					$this->template->parse('tableView_Head','tableViewHead',True);
				}
			}

			if(is_array($this->tableViewRows))
			{
				foreach($this->tableViewRows as $tableRow)
				{
					$rowData .= "<tr>";
					foreach($tableRow as $tableData)
					{
						switch($tableData['type'])
						{
							default:
								$rowData .= '<td>'.$tableData['text'].'</td>';
								break;
						}
					}
					$rowData .= "</tr>";
				}
			}

			$this->template->set_var('tableView_width', $_tableWidth);
			$this->template->set_var('tableView_Rows', $rowData);

			return $this->template->fp('out','tableView');
		}

		function tableViewAddRow()
		{
			$this->tableViewRows[] = array();
			end($this->tableViewRows);
			return key($this->tableViewRows);
		}

		function tableViewAddTextCell($_rowID,$_text)
		{
			$this->tableViewRows[$_rowID][]= array
			(
				'type'	=> 'text',
				'text'	=> $_text
			);
		}

		function quotaDisplay($_usage, $_limit)
		{
			$this->t = CreateObject('phpgwapi.Template',EGW_APP_TPL);
			$this->t->set_file(array("body" => 'mainscreen.tpl'));
			$this->t->set_block('body','quota_block');

			if($_limit == 0) {
				$quotaPercent=100;
			} else {
				$quotaPercent=round(($_usage*100)/$_limit);
			}

			$quotaLimit=$this->show_readable_size($_limit*1024);
			$quotaUsage=$this->show_readable_size($_usage*1024);

			$this->t->set_var('leftWidth',$quotaPercent);

			if($quotaPercent > 90 && $_limit>0) {
				$this->t->set_var('quotaBG','red');
			} elseif($quotaPercent > 80 && $_limit>0) {
				$this->t->set_var('quotaBG','yellow');
			} else {
				$this->t->set_var('quotaBG','#66ff66');
			}

			if($_limit > 0) {
				$quotaText = $quotaUsage .'/'.$quotaLimit;
			} else {
				$quotaText = $quotaUsage;
			}

			if($quotaPercent > 50) {
				$this->t->set_var('quotaUsage_left', $quotaText);
				$this->t->set_var('quotaUsage_right','');
			} else {
				$this->t->set_var('quotaUsage_left','');
				$this->t->set_var('quotaUsage_right', $quotaText);
			}

			$this->t->parse('out','quota_block');
			return $this->t->get('out','quota_block');
		}
	}
?>
