<?php
/**
 * EGroupware - FeLaMiMail - user interface widgets
 *
 * @link http://www.egroupware.org
 * @package felamimail
 * @author Lars Kneschke [lkneschke@linux-at-work.de]
 * @author Klaus Leithoff [kl@stylite.de]
 * @copyright (c) 2004 by Lars Kneschke <lkneschke-AT-linux-at-work.de>
 * @copyright (c) 2009-10 by Klaus Leithoff <kl-AT-stylite.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * a class containing javascript enhanced html widgets
 */
class uiwidgets
{
		var $charset;
		/**
		 * Reference to felamimail_bo
		 *
		 * @var felamimail_bo
		 */
		var $bofelamimail;
		/**
		 * Instance of template class for felamimail
		 *
		 * @var Template
		 */
		var $template;

		/**
		 * Constructor
		 */
		function uiwidgets()
		{
			$this->template = new Template(common::get_tpl_dir('felamimail'));
			$this->template->set_file(array("body" => 'uiwidgets.tpl'));
			$this->charset = $GLOBALS['egw']->translation->charset();
			$this->bofelamimail =& CreateObject('felamimail.bofelamimail',$GLOBALS['egw']->translation->charset());
		}

		function encodeFolderName($_folderName)
		{
			return translation::convert($_folderName, 'UTF7-IMAP', $this->charset);
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
			$preferences = $this->bofelamimail->mailPreferences;
			//_debug_array(bofelamimail::$autoFolders);
			$userDefinedFunctionFolders = array();
			if (isset($preferences->preferences['trashFolder']) && 
				$preferences->preferences['trashFolder'] != 'none') $userDefinedFunctionFolders['Trash'] = $preferences->preferences['trashFolder'];
			if (isset($preferences->preferences['sentFolder']) && 
				$preferences->preferences['sentFolder'] != 'none') $userDefinedFunctionFolders['Sent'] = $preferences->preferences['sentFolder'];
			if (isset($preferences->preferences['draftFolder']) &&
				$preferences->preferences['draftFolder'] != 'none') $userDefinedFunctionFolders['Drafts'] = $preferences->preferences['draftFolder'];
			if (isset($preferences->preferences['templateFolder']) &&
				$preferences->preferences['templateFolder'] != 'none') $userDefinedFunctionFolders['Templates'] = $preferences->preferences['templateFolder'];
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

			$folderImageDir = $GLOBALS['egw_info']['server']['webserver_url'].'/phpgwapi/templates/default/images';

			// careful! "d = new..." MUST be on a new line!!!
			$folder_tree_new  = '<link rel="STYLESHEET" type="text/css" href="'.$GLOBALS['egw_info']['server']['webserver_url'].'/phpgwapi/js/dhtmlxtree/css/dhtmlXTree.css">';
			$folder_tree_new .= "<script type='text/javascript'>";
			$folder_tree_new .= "function nodeHandler()
{
	var wnd = egw_appWindow('felamimail');
	if (wnd && (typeof wnd.onNodeSelect == 'function'))
	{
		egw_appWindow('felamimail').onNodeSelect.apply(this, arguments);
	}
}";
			$folder_tree_new .= "var tree=new dhtmlXTreeObject('$_divName','100%','100%',0);";
			$folder_tree_new .= "tree.parentObject.style.overflow=\"auto\";";
			$folder_tree_new .= "tree.setImagePath('$folderImageDir/dhtmlxtree/');";
			if($_displayCheckBox) {
				$folder_tree_new .= "tree.enableCheckBoxes(1);";
				$folder_tree_new .= "tree.setOnCheckHandler(egw_appWindow('felamimail').onCheckHandler);";
			}
			// beware this is "old" dhtmlx Code
			$folder_tree_new .= "tree.openFuncHandler=1;";
			$folder_tree_new .= "tree.setOnOpenHandler(egw_appWindow('felamimail').OnLoadingStart);";
			// this is code for their latest codebase, since "old" stuff is deprecated
			/*
			$folder_tree_new .='tree.attachEvent("onOpenStart", onOpenStartHandler);';
			$folder_tree_new .='tree.attachEvent("onOpenEnd", onOpenEndHandler);';
			*/
			#$topFolderBase64 = base64_encode('--topfolder--');
			$topFolderBase64 = '--topfolder--';
			$folder_tree_new .= "tree.insertNewItem(0,'$topFolderBase64','$_topFolderName',nodeHandler,'thunderbird.png','thunderbird.png','thunderbird.png','CHILD,TOP');\n";

			#foreach($_folders as $key => $obj)
			#_debug_array($allFolders);
			foreach($allFolders as $longName => $obj) {
				$messageCount = '';
				if (in_array($obj->shortFolderName,bofelamimail::$autoFolders)) 
				{
					//echo $obj->shortFolderName.'<br>';
					$image1 = $image2 = $image3 = "'MailFolder".$obj->shortFolderName.".png'";
					//$image2 = "'MailFolderPlain.png'";
					//$image3 = "'MailFolderPlain.png'";
				}
				elseif (in_array($longName,$userDefinedFunctionFolders))
				{
					$key = array_search($longName,$userDefinedFunctionFolders);
					$image1 = $image2 = $image3 = "'MailFolder".$key.".png'";
				}
				else
				{
					$image1 = "'MailFolderPlain.png'";
					$image2 = "'folderOpen.gif'";
					$image3 = "'MailFolderClosed.png'";
				}
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
					$folderName	= translation::convert($obj->folderName, 'UTF7-IMAP', $this->charset);
					$folderName	= @htmlspecialchars($folderName, ENT_QUOTES, $this->charset,false);
				} else {
					$folderName	= @htmlspecialchars($obj->folderName, ENT_QUOTES, $this->charset,false);
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

				$folder_tree_new .= "tree.insertNewItem('$parentName','$folderName','$displayName',nodeHandler,$image1,$image2,$image3,'$entryOptions');";
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
						'2'	=> '<a href="" onclick="egw_openWindowCentered(\''. $urlEditSignature ."&signatureID=".$signature['fm_signatureid']. '\',\'felamiMailACL\',\'750\',egw_getWindowOuterHeight()/2); return false;">'. @htmlspecialchars($description, ENT_QUOTES, $this->charset) .'</a>',
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
		function messageTable($_headers, $_folderType, $_folderName, $_readInNewWindow, $_rowStyle='felamimail',$messageToBePreviewed=0)
		{
			//error_log(__METHOD__.' preview Message:'.$messageToBePreviewed);
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
			$firstuid = null;
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
				$headerSubject = @htmlentities($header['subject'],ENT_QUOTES,$this->charset,false);
				if (empty($headerSubject)) $headerSubject = @htmlentities($GLOBALS['egw']->translation->convert($header['subject'], bofelamimail::detect_encoding($header['subject']), $this->charset),ENT_QUOTES | ENT_IGNORE,$this->charset,false);
				$header['subject'] = $headerSubject;
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
					$this->t->set_var('header_subject', @htmlspecialchars('('. lang('no subject') .')', ENT_QUOTES, $this->charset));
				}

				//_debug_array($header);
				if($header['mimetype'] == 'multipart/mixed' ||
					$header['mimetype'] == 'multipart/signed' ||
					$header['mimetype'] == 'multipart/related' ||
					$header['mimetype'] == 'multipart/report' ||
					$header['mimetype'] == 'text/calendar' ||
					substr($header['mimetype'],0,11) == 'application' ||
					substr($header['mimetype'],0,5) == 'audio' ||
					substr($header['mimetype'],0,5) == 'video') 
				{
					$image = html::image('felamimail','attach');
					if (//$header['mimetype'] != 'multipart/mixed' &&
						$header['mimetype'] != 'multipart/signed'
					)
					{
						if ($this->bofelamimail->icServer->_connected != 1) 
						{
							$this->bofelamimail->openConnection(0); // connect to the current server
							$this->bofelamimail->reopen($_folderName);
						}
						$attachments = $this->bofelamimail->getMessageAttachments($header['uid']);
						if (count($attachments)<1) $image = '&nbsp;';
					}
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
					$header2add = @htmlentities($header['to_address'],ENT_QUOTES,$this->charset,false);
					if (empty($header2add)) $header2add = @htmlentities($GLOBALS['egw']->translation->convert($header['to_address'], bofelamimail::detect_encoding($header['to_address']), $this->charset),ENT_QUOTES | ENT_IGNORE,$this->charset,false);
					$header['to_address'] = $header2add;
					if (!empty($header['to_name'])) {
						$header2name = @htmlentities($header['to_name'],ENT_QUOTES,$this->charset,false);
						if (empty($header2name)) $header2name = @htmlentities($GLOBALS['egw']->translation->convert($header['to_name'], bofelamimail::detect_encoding($header['to_name']), $this->charset),ENT_QUOTES | ENT_IGNORE,$this->charset,false);
						$header['to_name'] = $header2name;

						$sender_name	= $header['to_name'];
						$full_address	= $header['to_name'].' <'.$header['to_address'].'>';
					} else {
						$sender_name	= $header['to_address'];
						$full_address	= $header['to_address'];
					}
				} else {
					$header2add = @htmlentities($header['sender_address'],ENT_QUOTES,$this->charset,false);
					if (empty($header2add)) $header2add = @htmlentities($GLOBALS['egw']->translation->convert($header['sender_address'], bofelamimail::detect_encoding($header['sender_address']), $this->charset),ENT_QUOTES | ENT_IGNORE,$this->charset,false);
					$header['sender_address'] = $header2add;
					if (!empty($header['sender_name'])) {
						$header2name = @htmlentities($header['sender_name'],ENT_QUOTES,$this->charset,false);
						if (empty($header2name)) $header2name = @htmlentities($GLOBALS['egw']->translation->convert($header['sender_name'], bofelamimail::detect_encoding($header['sender_name']), $this->charset),ENT_QUOTES | ENT_IGNORE,$this->charset,false);
						$header['sender_name'] = $header2name;

						$sender_name	= $header['sender_name'];
						$full_address	= $header['sender_name'].' <'.$header['sender_address'].'>';
					} else {
						$sender_name	= $header['sender_address'];
						$full_address	= $header['sender_address'];
					}
				}

				$this->t->set_var('sender_name', @htmlspecialchars($sender_name, ENT_QUOTES | ENT_IGNORE, $this->charset,false));
				$this->t->set_var('full_address', @htmlspecialchars($full_address, ENT_QUOTES | ENT_IGNORE, $this->charset,false));

				$this->t->set_var('message_counter', $i);
				$this->t->set_var('message_uid', $header['uid']);
 
				if ($dateToday == bofelamimail::_strtotime($header['date'],'Y-m-d')) {
 				    $this->t->set_var('date', bofelamimail::_strtotime($header['date'],($GLOBALS['egw_info']['user']['preferences']['common']['timeformat']==12?'h:i:s a':'H:i:s'))); //$GLOBALS['egw']->common->show_date($header['date'],'H:i:s'));
				} else {
					$this->t->set_var('date', bofelamimail::_strtotime($header['date'],$GLOBALS['egw_info']['user']['preferences']['common']['dateformat']));
				}
				$this->t->set_var('datetime', bofelamimail::_strtotime($header['date'],$GLOBALS['egw_info']['user']['preferences']['common']['dateformat']).
												' - '.bofelamimail::_strtotime($header['date'],($GLOBALS['egw_info']['user']['preferences']['common']['timeformat']==12?'h:i:s a':'H:i:s'))); 

				$this->t->set_var('size', $this->show_readable_size($header['size']));
				// selecting the first message by default for preview
				if ($firstuid === null) // only use preview if there is a message selected, so selecting the first message by default is not used anymore
				{
					//_debug_array($header);
					//$firstuid = $selecteduid = $header['uid'];
					//$firstheader = $header;
				}
				// preview the message with the requested (messageToBePreviewed) uid
				if ($messageToBePreviewed>0 
					&& $GLOBALS['egw_info']['user']['preferences']['felamimail']['PreViewFrameHeight']>0 
					&& $messageToBePreviewed == $header['uid']) 
				{
					//error_log(__METHOD__.$header['uid']);
					$selecteduid = $header['uid'];
					$firstheader = $header;
				}
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
					$this->t->set_var('preview_message_windowName', $windowName);
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

					if ($GLOBALS['egw_info']['user']['preferences']['felamimail']['PreViewFrameHeight']>0) $windowName = 'MessagePreview_'.$header['uid'].'_'.$_folderType;

					$this->t->set_var('preview_message_windowName', $windowName);
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
				/*
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
				*/
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
					
			if ($GLOBALS['egw_info']['user']['preferences']['felamimail']['PreViewFrameHeight']>0)
			{
				$this->t->set_var('selected_style'.$selecteduid,'style="background-color:#ddddFF;"');
			} else {
				$this->t->set_var('selected_style'.$selecteduid,'');
			}
			if ($firstheader && 
				$GLOBALS['egw_info']['user']['preferences']['felamimail']['PreViewFrameHeight']>0 &&
				($_folderType==0 || $_folderType==1)) // only if not  drafts or template folder
			{
				$IFRAMEBody =  $this->updateMessagePreview($firstheader,$_folderType,$_folderName);
			}
			else
			{
				if ($GLOBALS['egw_info']['user']['preferences']['felamimail']['PreViewFrameHeight']>0)
				{
					$IFRAMEBody = "<TABLE BORDER=\"1\" rules=\"rows\" style=\"table-layout:fixed;width:100%;\">
								<TR class=\"th\" style=\"width:100%;\">
									<TD nowrap valign=\"top\">
										".'<b><br> '.
										"<center><font color='red'>".(!($_folderType == 2 || $_folderType == 3)?lang("Select a message to switch on its preview (click on subject)"):lang("Preview disabled for Folder: ").$_folderName)."</font></center><br>
										</b>"."
									</TD>
								</TR>
								<TR>
									<TD nowrap id=\"tdmessageIFRAME\" valign=\"top\" height=\"".$GLOBALS['egw_info']['user']['preferences']['felamimail']['PreViewFrameHeight']."\">
										&nbsp;
									</TD>
								</TR>
							   </TABLE>";
				}
				else
				{
					$IFRAMEBody = '';
				}
			}

			$this->t->set_var('IFrameForPreview',$IFRAMEBody);
			$this->t->set_var('messagelist_height',($GLOBALS['egw_info']['user']['preferences']['felamimail']['PreViewFrameHeight']>0 ? ($GLOBALS['egw_info']['user']['preferences']['felamimail']['PreViewFrameHeight']).'px':'auto'));

			$this->t->parse("out","message_table");

			return $this->t->get('out','message_table');
		}

		function updateMessagePreview($headerData,$_folderType,$_folderName,$_icServer=0)
		{
			// IFrame for Preview ....
			if ($headerData['uid'] && $GLOBALS['egw_info']['user']['preferences']['felamimail']['PreViewFrameHeight']>0)
			{
				$jscall ='';
				$this->bofelamimail->openConnection($_icServer);
				$this->bofelamimail->reopen($_folderName);
				$flags = $this->bofelamimail->getFlags($headerData['uid']);
				if ($this->bofelamimail->getNotifyFlags($headerData['uid']) === null)
				{
					$headers    = $this->bofelamimail->getMessageHeader($headerData['uid']); 
					if ( isset($headers['DISPOSITION-NOTIFICATION-TO']) ) {
						$sent_not = $this->bofelamimail->decode_header(trim($headers['DISPOSITION-NOTIFICATION-TO']));
					} else if ( isset($headers['RETURN-RECEIPT-TO']) ) {
						$sent_not = $this->bofelamimail->decode_header(trim($headers['RETURN-RECEIPT-TO']));
					} else if ( isset($headers['X-CONFIRM-READING-TO']) ) {
						$sent_not = $this->bofelamimail->decode_header(trim($headers['X-CONFIRM-READING-TO']));
					} else $sent_not = "";
					if ( $sent_not != "" && strpos( array2string($flags),'Seen')===false) 
					{
						$jscall= " onload='javascript:sendNotifyMS(".$headerData['uid'].")'";
					}
				}
				//if (strpos( array2string($flags),'Seen')===false) $this->bofelamimail->flagMessages('read', $headerData['uid']);
				if ($_folderType > 0) {
					// sent or drafts or template folder
					if (!empty($headerData['to_name'])) {
						$sender_name	= $headerData['to_name'];
						$sender_address = $headerData['to_address'];
						$full_address	= $headerData['to_name'].' &lt;'.$headerData['to_address'].'&gt;';
					} else {
						$sender_name	= $headerData['to_address'];
						$sender_address = $headerData['to_address'];
						$full_address	= $headerData['to_address'];
					}
				} else {
					if (!empty($headerData['sender_name'])) {
						$sender_name	= $headerData['sender_name'];
						$sender_address = $headerData['sender_address'];
						$full_address	= $headerData['sender_name'].' &lt;'.$headerData['sender_address'].'&gt;';
					} else {
						$sender_name	= $headerData['sender_address'];
						$sender_address = $headerData['sender_address'];
						$full_address	= $headerData['sender_address'];
					}
				}

				//$fromAddress   = uidisplay::emailAddressToHTML(array('PERSONAL_NAME'=>$sender_name,'EMAIL'=>$sender_address,'RFC822_EMAIL'=>$full_address),'');
				if ($GLOBALS['egw_info']['user']['apps']['addressbook']) {
					$addresslinkData = array (
						'menuaction'		=> 'addressbook.addressbook_ui.edit',
						'presets[email]'	=> $sender_address,
						'referer'		=> $_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']
					);
					$decodedPersonalName = $sender_name;
					if (!empty($decodedPersonalName)) {
						if($spacePos = strrpos($decodedPersonalName, ' ')) {
							$addresslinkData['presets[n_family]']	= substr($decodedPersonalName, $spacePos+1);
							$addresslinkData['presets[n_given]'] 	= substr($decodedPersonalName, 0, $spacePos);
						} else {
							$addresslinkData['presets[n_family]']	= $decodedPersonalName;
						}
						$addresslinkData['presets[n_fn]']	= $decodedPersonalName;
					}

					$urlAddToAddressbook = $GLOBALS['egw']->link('/index.php',$addresslinkData);
					$onClick = "window.open(this,this.target,'dependent=yes,width=850,height=440,location=no,menubar=no,toolbar=no,scrollbars=yes,status=yes'); return false;";
					$image = $GLOBALS['egw']->common->image('felamimail','sm_envelope');
					$fromAddress .= sprintf('<a href="%s" onClick="%s">
						<img src="%s" width="10" height="8" border="0"
						align="absmiddle" alt="%s"
						title="%s"></a>',
						$urlAddToAddressbook,
						$onClick,
						$image,
						lang('add to addressbook'),
						lang('add to addressbook'));
				}
				
				$linkData = array (
					'menuaction'    => 'felamimail.uidisplay.display',
					'showHeader'	=> 'false',
					'mailbox'    => base64_encode($_folderName),
					'uid'		=> $headerData['uid'],
					'id'		=> $headerData['id'],
				);
				$linkDataAttachments = array (
					'menuaction'    => 'felamimail.uidisplay.displayAttachments',
					'showHeader'	=> 'false',
					'mailbox'    => base64_encode($_folderName),
					'uid'		=> $headerData['uid'],
					'id'		=> $headerData['id'],
				);
				$windowName =  'displayMessage_'.$headerData['uid'];

				if($headerData['mimetype'] == 'multipart/mixed' ||
					$headerData['mimetype'] == 'multipart/signed' ||
					$headerData['mimetype'] == 'multipart/related' ||
					$headerData['mimetype'] == 'multipart/report' ||
					$headerData['mimetype'] == 'text/calendar' ||
					substr($headerData['mimetype'],0,11) == 'application' ||
					substr($headerData['mimetype'],0,5) == 'audio' ||
					substr($headerData['mimetype'],0,5) == 'video') 
				{
					$image = html::image('felamimail','attach');

					$image = "<a name=\"subject_url\" href=\"#\" 
						onclick=\"fm_readAttachments('".$GLOBALS['egw']->link('/index.php',$linkDataAttachments)."', '".$windowName."', this); return false;\" 
						title=\"".$headerData['subject']."\">".$image."</a>";
					if (//$headerData['mimetype'] != 'multipart/mixed' &&
						$header['mimetype'] != 'multipart/signed'
					)
					{
						$attachments = $this->bofelamimail->getMessageAttachments($headerData['uid']);
						if (count($attachments)<1) $image = '&nbsp;';
					}

					$windowName = ($_readInNewWindow == 1 ? 'displayMessage' : 'displayMessage_'.$header['uid']);
				} else {
					$image = '';
				}
				$subject = "<a name=\"subject_url\" href=\"#\" 
						onclick=\"fm_readMessage('".$GLOBALS['egw']->link('/index.php',$linkData)."', '".$windowName."', this); return false;\" 
						title=\"".$headerData['subject']."\">".$headerData['subject']."</a>";
				$IFrameHeight = $GLOBALS['egw_info']['user']['preferences']['felamimail']['PreViewFrameHeight'];
				$linkData = array (
						'menuaction'	=> 'felamimail.uidisplay.displayBody',
						'uid'		=> $headerData['uid'],
						'mailbox'	=>  base64_encode($_folderName)
					);

				$iframe_url = $GLOBALS['egw']->link('/index.php',$linkData);
				// if browser supports data uri: ie<8 does NOT and ie>=8 does NOT support html as content :-(
				// --> use it to send the mail as data uri
				if (!isset($_GET['printable']))
				{
					$bodyParts	= $this->bofelamimail->getMessageBody($headerData['uid'],'',$partID);
					$uidisplay = CreateObject('felamimail.uidisplay');

					$frameHtml = base64_encode(
						$uidisplay->get_email_header().
						$uidisplay->showBody($uidisplay->getdisplayableBody($bodyParts), false));
					$iframe_url = egw::link('/phpgwapi/js/egw_instant_load.html').'" onload="if (this.contentWindow && typeof this.contentWindow.egw_instant_load != \'undefined\') this.contentWindow.egw_instant_load(\''.$frameHtml.'\', true);';
				}

				//_debug_array($GLOBALS['egw']->link('/index.php',$linkData));
				$IFRAMEBody = "<TABLE BORDER=\"1\" rules=\"rows\" style=\"table-layout:fixed;width:100%;\">
								<TR class=\"th\" style=\"width:100%;\">
									<TD nowrap valign=\"top\" style=\"overflow:hidden;\">
										".($_folderType > 0?lang('to'):lang('from')).':<b>'.$full_address.' '.($fromAddress?$fromAddress:'') .'</b><br> '.
										lang('date').':<b>'.bofelamimail::_strtotime($headerData['date'],$GLOBALS['egw_info']['user']['preferences']['common']['dateformat']).
                                                ' - '.bofelamimail::_strtotime($headerData['date'],($GLOBALS['egw_info']['user']['preferences']['common']['timeformat']==12?'h:i:s a':'H:i:s'))."</b><br>
										".lang('subject').":<b>".$subject."</b>
									</TD>
									<td style=\"width:20px;\" align=\"right\">
										$image
									</td>
									<td style=\"width:275px;\" align=\"right\">
										<nobr>
											".$this->navbarSeparator().$this->displayMessageActions($headerData, $_folderName, $_icServer,true)."
										</nobr>
									</td>
								</TR>
								<TR>
									<TD nowrap id=\"tdmessageIFRAME\" valign=\"top\" colspan=\"3\" height=\"".$IFrameHeight."\">
										<iframe ".(!empty($jscall) ? $jscall:"")." id=\"messageIFRAME\" frameborder=\"1\" height=\"".$IFrameHeight."\" scrolling=\"auto\" src=\"".$iframe_url."\">
										</iframe>
									</TD>
								</TR>
							   </TABLE>";
			}
			else
			{
				$IFRAMEBody = "&nbsp;";
			}
			return $IFRAMEBody;
		}

		function displayMessageActions($_headerData, $_folderName, $_icServer, $_forceNewWindow=false)
		{
			if ($_forceNewWindow)
			{
				list($fm_width,$fm_height) = explode('x',egw_link::get_registry('felamimail','view_popup'));
			}
			// navbar start
			// compose as new URL
			$linkData = array (
				'menuaction'    => 'felamimail.uicompose.composeAsNew',
				'icServer'  => $_icServer,
				'folder'    => base64_encode($_folderName),
				'reply_id'  => $_headerData['uid'],
			);
			if($_headerData['partid'] != '') {
				$linkData['part_id'] = $_headerData['partid'];
			}
			$asnewURL = $GLOBALS['egw']->link('/index.php',$linkData);

			// reply url
			$linkData = array (
				'menuaction'	=> 'felamimail.uicompose.reply',
				'icServer'	=> $_icServer,
				'folder'	=> base64_encode($_folderName),
				'reply_id'	=> $_headerData['uid'],
			);
			if($_headerData['partid'] != '') {
				$linkData['part_id'] = $_headerData['partid'];
			}
			$replyURL = $GLOBALS['egw']->link('/index.php',$linkData);

			// reply all url
			$linkData = array (
				'menuaction'	=> 'felamimail.uicompose.replyAll',
				'icServer'	=> $_icServer,
				'folder'	=> base64_encode($_folderName),
				'reply_id'	=> $_headerData['uid'],
			);
			if($_headerData['partid'] != '') {
				$linkData['part_id'] = $_headerData['partid'];
			}
			$replyAllURL = $GLOBALS['egw']->link('/index.php',$linkData);

			// forward url
			$linkData = array (
				'menuaction'	=> 'felamimail.uicompose.forward',
				'reply_id'	=> $_headerData['uid'],
				'folder'	=> base64_encode($_folderName),
			);
			if($_headerData['partid'] != '') {
				$linkData['part_id'] = $_headerData['partid'];
			}
			$forwardURL = $GLOBALS['egw']->link('/index.php',$linkData);

			//delete url
			$linkData = array (
				'menuaction'	=> 'felamimail.uifelamimail.deleteMessage',
				'icServer'	=> $_icServer,
				'folder'	=> base64_encode($_folderName),
				'message'	=> $_headerData['uid'],
			);
			$deleteURL = $GLOBALS['egw']->link('/index.php',$linkData);

			$navbarImages = array(
				'new'	=> array(
					'action'	=> ($_forceNewWindow ? "egw_openWindowCentered('$asnewURL','composeasnew_".$_headerData['uid']."',".$fm_width.",".$fm_height.");": "window.location.href = '$asnewURL'"),
					'tooltip'   => lang('compose as new'),
				),
				'mail_reply'	=> array(
					'action'	=> ($_forceNewWindow ? "egw_openWindowCentered('$replyURL','reply_".$_headerData['uid']."',".$fm_width.",".$fm_height.");": "window.location.href = '$replyURL'"),
					'tooltip'	=> lang('reply'),
				),
				'mail_replyall'	=> array(
					'action'	=> ($_forceNewWindow ? "egw_openWindowCentered('$replyAllURL','replyAll_".$_headerData['uid']."',".$fm_width.",".$fm_height.");": "window.location.href = '$replyAllURL'"),
					'tooltip'	=> lang('reply all'),
				),
				'mail_forward'	=> array(
					'action'	=> ($_forceNewWindow ? "egw_openWindowCentered('$forwardURL','forward_".$_headerData['uid']."',".$fm_width.",".$fm_height.");": "window.location.href = '$forwardURL'"),
					'tooltip'	=> lang('forward'),
				),
				'delete'	=> array(
					'action'	=> ($_forceNewWindow ? "window.open('$deleteURL','_blank','dependent=yes,width=100,height=100,toolbar=no,scrollbars=no,status=no')": "window.location.href = '$deleteURL'"),
					'tooltip'	=> lang('delete'),
				),
			);
			foreach($navbarImages as $buttonName => $buttonInfo) {
				$navbarButtons .= $this->navbarButton($buttonName, $buttonInfo['action'], $buttonInfo['tooltip']);
			}
			$navbarButtons .= $this->navbarSeparator();

			// print url
			$linkData = array (
				'menuaction'	=> 'felamimail.uidisplay.printMessage',
				'uid'		=> $_headerData['uid'],
				'folder'    => base64_encode($_folderName),
			);
			if($_headerData['partid'] != '') {
				$linkData['part'] = $_headerData['partid'];
			}
			$printURL = $GLOBALS['egw']->link('/index.php',$linkData);

			// infolog URL
			$linkData = array(
				'menuaction' => 'infolog.infolog_ui.import_mail',
				'uid'    => $_headerData['uid'],
				'mailbox' =>  base64_encode($_folderName)
			);
			if($_headerData['partid'] != '') {
				$linkData['part'] = $_headerData['partid'];
			}
			$to_infologURL = $GLOBALS['egw']->link('/index.php',$linkData);

			$linkData = array(
				'menuaction' => 'tracker.tracker_ui.import_mail',
				'uid'    => $_headerData['uid'],
				'mailbox' =>  base64_encode($_folderName)
			);
			if($_headerData['partid'] != '') {
				$linkData['part'] = $_headerData['partid'];
			}
			$to_trackerURL = $GLOBALS['egw']->link('/index.php',$linkData);

			// viewheader url
			$linkData = array (
				'menuaction'	=> 'felamimail.uidisplay.displayHeader',
				'uid'		=> $_headerData['uid'],
				'mailbox'	=> base64_encode($_folderName)
			);
			if($_headerData['partid'] != '') {
				$linkData['part'] = $_headerData['partid'];
			}
			$viewHeaderURL = $GLOBALS['egw']->link('/index.php',$linkData);

			$navbarImages = array();

			// save message url
			$linkData = array (
				'menuaction'	=> 'felamimail.uidisplay.saveMessage',
				'uid'		=> $_headerData['uid'],
				'mailbox'	=> base64_encode($_folderName)
			);
			if($_headerData['partid'] != '') {
				$linkData['part'] = $_headerData['partid'];
			}
			$saveMessageURL = $GLOBALS['egw']->link('/index.php',$linkData);

			$navbarImages = array();

			//print email
			$navbarImages = array(
				'fileprint' => array(
					'action'	=> ($_forceNewWindow ? "egw_openWindowCentered('$printURL','forward_".$_headerData['uid']."',".$fm_width.",".$fm_height.");": "window.location.href = '$printURL'"),
					'tooltip'	=> lang('print it'),
				),
			);
			if ($GLOBALS['egw_info']['user']['apps']['infolog']) 
			{
				list($i_width,$i_height) = explode('x',egw_link::get_registry('infolog','add_popup'));
				$navbarImages['to_infolog'] = array(
					'action'	=> "window.open('$to_infologURL','_blank','dependent=yes,width=".$i_width.",height=".$i_height.",scrollbars=yes,status=yes')",
					'tooltip'	=> lang('save as infolog'));
			}
			if ($GLOBALS['egw_info']['user']['apps']['tracker'])
			{
				list($i_width,$i_height) = explode('x',egw_link::get_registry('tracker','add_popup'));
				$navbarImages['to_tracker'] = array(
					'action'    => "egw_openWindowCentered('$to_trackerURL','_blank',".$i_width.",".$i_height.")",
					'tooltip'   => lang('save as tracker'));
			}
			// save email as
			$navbarImages['fileexport'] = array(
				'action'	=> ($_forceNewWindow ? "window.open('$saveMessageURL','_blank','dependent=yes,width=100,height=100,scrollbars=yes,status=yes')": "window.location.href = '$saveMessageURL'"),
				'tooltip'	=> lang('save message to disk'),
			);

			// view header lines
			$navbarImages['kmmsgread'] = array(
				'action'	=> "fm_displayHeaderLines('$viewHeaderURL')",
				'tooltip'	=> lang('view header lines'),
			);

			foreach($navbarImages as $buttonName => $buttonData) {
				$navbarButtons .= $this->navbarButton($buttonName, $buttonData['action'], $buttonData['tooltip']);
			}
			return $navbarButtons;
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
			$_toolTip = bofelamimail::htmlentities($_toolTip);

			return "<div class='navButton' style='float:$float;' onmousedown='this.className=\"navButtonActive\";' onmouseup='this.className=\"navButtonHover\";' onmouseout='this.className=\"navButton\";'".( $_imageAction=='#'?"":"onclick=\"$_imageAction\"")."><img style='width:16px; height:16px;' class=\"sideboxstar\" title='$_toolTip' src='$image' ></div>";
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
