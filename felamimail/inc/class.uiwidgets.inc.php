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
		var $_connectionStatus;
		var $sessionData;
		var $profileID = 0;
		/**
		 * Instance of template class for felamimail
		 *
		 * @var Template
		 */
		var $template;
		/**
		 * Use a preview pane: depends on preference and NOT using a mobile browser
		 *
		 * @var boolean
		 */
		var $use_preview = false;
		var $messageListMinHeight = 100;

		/**
		 * Constructor
		 */
		function uiwidgets()
		{
			$this->template = new Template(common::get_tpl_dir('felamimail'));
			$this->template->set_file(array("body" => 'uiwidgets.tpl'));
			$this->charset = translation::charset();
			if (isset($GLOBALS['egw_info']['user']['preferences']['felamimail']['ActiveProfileID']))
				$this->profileID = (int)$GLOBALS['egw_info']['user']['preferences']['felamimail']['ActiveProfileID'];

			$this->bofelamimail = felamimail_bo::getInstance(true,$this->profileID);
			$this->_connectionStatus = $this->bofelamimail->openConnection($this->profileID);
			$this->sessionData	=& $GLOBALS['egw']->session->appsession('session_data','felamimail');
			$previewFrameHeight = -1;
			if ($GLOBALS['egw_info']['user']['preferences']['felamimail']['PreViewFrameHeight'] &&
				stripos($GLOBALS['egw_info']['user']['preferences']['felamimail']['PreViewFrameHeight'],',') !== false)
			{
				list($previewFrameHeight, $messageListHeight) = explode(',',$GLOBALS['egw_info']['user']['preferences']['felamimail']['PreViewFrameHeight']);
				$previewFrameHeight = trim($previewFrameHeight);
				$messageListHeight = trim($messageListHeight);
				if (!empty($messageListHeight) && $messageListHeight>$this->messageListMinHeight) $this->messageListMinHeight = $messageListHeight;
			}
			$this->use_preview = !html::$ua_mobile && ($GLOBALS['egw_info']['user']['preferences']['felamimail']['PreViewFrameHeight'] > 0 || $previewFrameHeight > 0);
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
			//_debug_array(felamimail_bo::$autoFolders);
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
			$folder_tree_new .= "var felamimail_folders=[];";
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
				if (in_array($obj->shortFolderName,felamimail_bo::$autoFolders))
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
				$folder_tree_new .= "felamimail_folders.push('$folderName');";

			}

			$selected = @htmlspecialchars($_selected, ENT_QUOTES, $this->charset);
			#$selected = base64_encode($_selected);

			$folder_tree_new.= "tree.closeAllItems(0);tree.openItem('$selected');";

			$folder_tree_new .= "if (typeof felamimail_transform_foldertree == 'function') {
				felamimail_transform_foldertree();
			}</script>";

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

		private function get_actions(array &$action_links=array())
		{
			// dublicated from felamimail_hooks
			static $deleteOptions = array(
				'move_to_trash'		=> 'move to trash',
				'mark_as_deleted'	=> 'mark as deleted',
				'remove_immediately' =>	'remove immediately',
			);
			// todo: real hierarchical folder list
			$folders = array(
				'INBOX' => 'INBOX',
				'Drafts' => 'Drafts',
				'Sent' => 'Sent',
			);

			$actions =  array(
				'open' => array(
					'caption' => lang('Open'),
					'group' => ++$group,
					'onExecute' => 'javaScript:mail_open',
					'allowOnMultiple' => false,
					'default' => true,
				),
				/* not necessary, as it's now a prominent button
				'compose' => array(
					'caption' => 'Compose',
					'icon' => 'new',
					'group' => $group,
					'onExecute' => 'javaScript:mail_compose',
					'allowOnMultiple' => false,
				),*/
				'reply' => array(
					'caption' => 'Reply',
					'icon' => 'mail_reply',
					'group' => ++$group,
					'onExecute' => 'javaScript:mail_compose',
					'allowOnMultiple' => false,
				),
				'reply_all' => array(
					'caption' => 'Reply All',
					'icon' => 'mail_replyall',
					'group' => $group,
					'onExecute' => 'javaScript:mail_compose',
					'allowOnMultiple' => false,
				),
				'forward' => array(
					'caption' => 'Forward',
					'icon' => 'mail_forward',
					'group' => $group,
					'onExecute' => 'javaScript:mail_compose',
				),
				'composeasnew' => array(
					'caption' => 'Compose as new',
					'icon' => 'new',
					'group' => $group,
					'onExecute' => 'javaScript:mail_compose',
					'allowOnMultiple' => false,
				),
				'infolog' => array(
					'caption' => 'InfoLog',
					'hint' => 'Save as InfoLog',
					'icon' => 'infolog/navbar',
					'group' => ++$group,
					'onExecute' => 'javaScript:mail_infolog',
					'url' => 'menuaction=infolog.infolog_ui.import_mail',
					'popup' => egw_link::get_registry('infolog', 'add_popup'),
					'allowOnMultiple' => false,
				),
				'tracker' => array(
					'caption' => 'Tracker',
					'hint' => 'Save as ticket',
					'group' => $group,
					'icon' => 'tracker/navbar',
					'onExecute' => 'javaScript:mail_tracker',
					'url' => 'menuaction=tracker.tracker_ui.import_mail',
					'popup' => egw_link::get_registry('tracker', 'add_popup'),
					'allowOnMultiple' => false,
				),
				'print' => array(
					'caption' => 'Print',
					'group' => ++$group,
					'onExecute' => 'javaScript:mail_print',
					'allowOnMultiple' => false,
				),
				'save' => array(
					'caption' => 'Save',
					'hint' => 'Save message to disk',
					'group' => $group,
					'icon' => 'fileexport',
					'onExecute' => 'javaScript:mail_save',
					'allowOnMultiple' => false,
				),
				'header' => array(
					'caption' => 'Header',
					'hint' => 'View header lines',
					'group' => $group,
					'icon' => 'kmmsgread',
					'onExecute' => 'javaScript:mail_header',
					'allowOnMultiple' => false,
				),
/*
				'select_all' => array(
					'caption' => 'Select all',
					'checkbox' => true,
					'hint' => 'All messages in folder',
					'group' => ++$group,
				),
*/
				'mark' => array(
					'caption' => 'Mark as',
					'icon' => 'read_small',
					'group' => ++$group,
					'children' => array(
						'flagged' => array(
							'caption' => 'Flagged',
							'icon' => 'unread_flagged_small',
							'onExecute' => 'javaScript:mail_flag',
							//'disableClass' => 'flagged',
							//'enabled' => "javaScript:mail_disabledByClass",
							'shortcut' => egw_keymanager::shortcut(egw_keymanager::F, true, true),
						),
						'unflagged' => array(
							'caption' => 'Unflagged',
							'icon' => 'read_flagged_small',
							'onExecute' => 'javaScript:mail_flag',
							//'enableClass' => 'flagged',
							//'enabled' => "javaScript:mail_enabledByClass",
							'shortcut' => egw_keymanager::shortcut(egw_keymanager::U, true, true),
						),
						'read' => array(
							'caption' => 'Read',
							'icon' => 'read_small',
							'onExecute' => 'javaScript:mail_flag',
							//'enableClass' => 'unseen',
							//'enabled' => "javaScript:mail_enabledByClass",
						),
						'unread' => array(
							'caption' => 'Unread',
							'icon' => 'unread_small',
							'onExecute' => 'javaScript:mail_flag',
							//'disableClass' => 'unseen',
							//'enabled' => "javaScript:mail_disabledByClass",
						),
						'undelete' => array(
							'caption' => 'Undelete',
							'icon' => 'revert',
							'onExecute' => 'javaScript:mail_flag',
							'enableClass' => 'deleted',
							'enabled' => "javaScript:mail_enabledByClass",
						),
					),
				),
/*
				'move' => array(
					'caption' => 'Move to',
					'group' => $group,
					'icon' => 'move',
					'children' => $folders,
					'prefix' => 'move_',
					'onExecute' => 'javaScript:mail_move',
				),
				'copy' => array(
					'caption' => 'Copy to',
					'group' => $group,
					'icon' => 'copy',
					'children' => $folders,
					'prefix' => 'copy_',
					'onExecute' => 'javaScript:mail_copy',
				),
*/
				'delete' => array(
					'caption' => 'Delete',
					'hint' => $deleteOptions[$this->bofelamimail->mailPreferences->preferences['deleteOptions']],
					'group' => ++$group,
					'onExecute' => 'javaScript:mail_delete',
				),
				'drag_mail' => array(
					'dragType' => 'mail',
					'type' => 'drag',
					'onExecute' => 'javaScript:mail_dragStart',
				),
				'drop_move_mail' => array(
					'type' => 'drop',
					'acceptedTypes' => 'mail',
					'icon' => 'move',
					'caption' => 'Move to',
					'onExecute' => 'javaScript:mail_move'
				),
				'drop_copy_mail' => array(
					'type' => 'drop',
					'acceptedTypes' => 'mail',
					'icon' => 'copy',
					'caption' => 'Copy to',
					'onExecute' => 'javaScript:mail_copy'
				),
				'drop_cancel' => array(
					'caption' => 'Cancel',
					'acceptedTypes' => 'mail',
					'type' => 'drop',
				),
			);
			// save as tracker, save as infolog, as this are actions that are either available for all, or not, we do that for all and not via css-class disabling
			if (!isset($GLOBALS['egw_info']['user']['apps']['infolog']))
			{
				unset($actions['infolog']);
			}
			if (!isset($GLOBALS['egw_info']['user']['apps']['tracker']))
			{
				unset($actions['tracker']);
			}

			return nextmatch_widget::egw_actions($actions, 'felamimail', '', $action_links);
		}

		function get_grid_js($foldertype,$_folderName,&$rowsFetched,$offset=0,$headers=false,$getAllIds=false)
		{
			//error_log(__METHOD__.__LINE__.array2string(array('Foldertype'=>$foldertype,'Foldername'=>$_folderName,'Offset'=>$offset,'getAllIds'=>$getAllIds)));
			$action_links=array();
			$js = '<script type="text/javascript">
if (typeof mailGrid == "undefined") var mailGrid = null;
$j(document).ready(function() {
	// Cleanup any old instance
	mail_cleanup();

	// Create the base objects and feed them with data
	var actionManager = egw_getActionManager("felamimail");
	var objectManager = egw_getObjectManager("felamimail");

	// Load the felamimail actions
	actionManager.updateActions('.json_encode($actions=$this->get_actions($action_links)).');

	mailGrid = new egwGrid(document.getElementById("divMessageTableList"),
		'.json_encode($this->get_columns_obj(true,$foldertype,$_folderName)->get_assoc()).', objectManager, egw_email_fetchDataProc,
		egw_email_columnChangeProc, window);
	mailGrid.setActionLinkGroups('.json_encode(array('mail' => $action_links)).');
	mailGrid.selectedChangeCallback = selectedGridChange;
	mailGrid.setSelectmode('.
		($GLOBALS['egw_info']['user']['preferences']['common']['select_mode'] ?
			$GLOBALS['egw_info']['user']['preferences']['common']['select_mode']
			:'EGW_SELECTMODE_DEFAULT').');
	// get_all_ids is to retrieve all message uids. no pagination needed anymore
';
//error_log(array2string($actions));
//error_log(array2string($action_links));
				if ($getAllIds === true)
				{
					$js .= '
	mailGrid.dataRoot.loadData('.json_encode($this->get_all_ids($_folderName,$foldertype,$rowsFetched)).');
';
				}
				else
				{
					$js .= '
	mailGrid.dataRoot.loadData('.json_encode($this->get_range($_folderName,$foldertype,$rowsFetched,$offset,$uidOnly=false,$headers)).');
';
				}
				$js .= '
	mailGrid.reload();
	var wnd = egw_appWindow("felamimail");
	if (wnd && typeof wnd.handleResize != "undefined")
	{
		wnd.handleResize();
		$j(window).resize(handleResize);
	}

	var allSelected = mailGrid.dataRoot.actionObject.getSelectedObjects();
	for (var i=0; i<allSelected.length; i++)
	{
		if (allSelected[i].id.length>0)
		{
			allSelected[i].setSelected(false);
			allSelected[i].setFocused(true);
		}
	}

	// Enable drag_drop for the folder tree (is also called whenever the foldertree gets refreshed)
	felamimail_transform_foldertree();
});
</script>	';
			//error_log(__METHOD__.__LINE__.' Rows fetched:'.$rowsFetched);
			return $js;
		}

		/**
		 * not used, as it fetches all data forv the given mailbox
		 */
		function get_all_ids($_folderName,$folderType,&$rowsFetched,$headers=false)
		{
			//error_log(__METHOD__.__LINE__.' Data:'.array2string($_folderName));
			$this->bofelamimail->restoreSessionData();
			$previewMessage = $this->sessionData['previewMessage'];
			if ($headers)
			{
				$sortResult = $headers;
			}
			else
			{
				$reverse = (bool)$this->sessionData['sortReverse'];
				$sR = $this->bofelamimail->getSortedList(
					$_folderName,
					$this->sessionData['sort'],
					$reverse,
					(array)$this->sessionData['messageFilter'],
					$rByUid=true
				);
				if($reverse === true) $sR = array_reverse((array)$sR);
				$sortResult = array();
				if (is_array($sR) && count($sR)>0)
				{
					// fetch first 50 headers
					$sortResultwH = $this->bofelamimail->getHeaders(
						$_folderName,
						$offset=0,
						$maxMessages=50,
						$this->sessionData['sort'],
						$reverse,
						(array)$this->sessionData['messageFilter'],
						array_slice($sR,0,50)
					);
				}

				foreach ((array)$sR as $key => $v)
				{
					if (array_key_exists($key,(array)$sortResultwH['header'])==true)
					{
						$sortResult['header'][] = $sortResultwH['header'][$key];
					}
					else
					{
						$sortResult['header'][] = array('uid'=>$v);
					}
				}
			}
			$rowsFetched['rowsFetched'] = count($sortResult['header']);
			$rowsFetched['messages'] = count($sR);
			//error_log(__METHOD__.__LINE__.' Data:'.array2string($sortResult));
			$cols = array('check','status','attachments','subject','toaddress','fromaddress','date','size');
			if ($GLOBALS['egw_info']['user']['preferences']['common']['select_mode']=='EGW_SELECTMODE_TOGGLE') unset($cols[0]);
			return $this->header2gridelements($sortResult['header'],$cols, $_folderName, $uidOnly=false,$folderType,$dataForXMails=50,$previewMessage);
		}

		function get_range($_folderName,$folderType,&$rowsFetched,$offset,$uidOnly=false,$headers=false)
		{
			//error_log(__METHOD__.__LINE__.' Folder:'.array2string($_folderName).' FolderType:'.$folderType.' RowsFetched:'.array2string($rowsFetched)." these Uids:".array2string($uidOnly).' Headers passed:'.array2string($headers));
			$this->bofelamimail->restoreSessionData();
			$maxMessages = 50; // match the hardcoded setting for data retrieval as inital value
			if (isset($this->bofelamimail->mailPreferences->preferences['prefMailGridBehavior']) && (int)$this->bofelamimail->mailPreferences->preferences['prefMailGridBehavior'] <> 0)
				$maxMessages = (int)$this->bofelamimail->mailPreferences->preferences['prefMailGridBehavior'];
			$previewMessage = $this->sessionData['previewMessage'];
			if ($headers)
			{
				$sortResult = $headers;
			}
			else
			{
				if ($maxMessages < 0)
				{
					// we should never end up here, but ...
					error_log(__METHOD__.__LINE__.' Data:'.array2string($_folderName));
					return $this->get_all_ids($_folderName,$folderType,$headers=false);
				}
				else
				{
					$sRToFetch = null;
					$rowsFetched['messages'] = null;
					$reverse = (bool)$this->sessionData['sortReverse'];
					//error_log(__METHOD__.__LINE__.' maxMessages:'.$maxMessages.' Offset:'.$offset.' Filter:'.array2string($this->sessionData['messageFilter']));
					if ($maxMessages > 75)
					{
						$sR = $this->bofelamimail->getSortedList(
							$_folderName,
							$this->sessionData['sort'],
							$reverse,
							(array)$this->sessionData['messageFilter'],
							$rByUid=true
						);
						$rowsFetched['messages'] = count($sR);

						if($reverse === true) $sR = array_reverse((array)$sR);
						$sR = array_slice($sR,($offset==0?0:$offset-1),$maxMessages); // we need only $maxMessages of uids
						$sRToFetch = array_slice($sR,0,50); // we fetch only the headers of a subset of the fetched uids
						//error_log(__METHOD__.__LINE__.' Rows fetched (UID only):'.count($sR).' Data:'.array2string($sR));
						$maxMessages = 50;
						$sortResultwH['header'] = array();
						if (count($sRToFetch)>0)
						{
							//error_log(__METHOD__.__LINE__.' Headers to fetch with UIDs:'.count($sRToFetch).' Data:'.array2string($sRToFetch));
							$sortResult = array();
							// fetch headers
							$sortResultwH = $this->bofelamimail->getHeaders(
								$_folderName,
								$offset,
								$maxMessages,
								$this->sessionData['sort'],
								$reverse,
								(array)$this->sessionData['messageFilter'],
								$sRToFetch
							);
						}
					}
					else
					{
						$sortResult = array();
						// fetch headers
						$sortResultwH = $this->bofelamimail->getHeaders(
							$_folderName,
							$offset,
							$maxMessages,
							$this->sessionData['sort'],
							$reverse,
							(array)$this->sessionData['messageFilter']
						);
						$rowsFetched['messages'] = $sortResultwH['info']['total'];
					}
					if (is_array($sR) && count($sR)>0)
					{
						foreach ((array)$sR as $key => $v)
						{
							if (array_key_exists($key,(array)$sortResultwH['header'])==true)
							{
								$sortResult['header'][] = $sortResultwH['header'][$key];
							}
							else
							{
								$sortResult['header'][] = array('uid'=>$v);
							}
						}
					}
					else
					{
						$sortResult = $sortResultwH;
					}
				}
			}
			$rowsFetched['rowsFetched'] = count($sortResult['header']);
			if (empty($rowsFetched['messages'])) $rowsFetched['messages'] = $rowsFetched['rowsFetched'];

			//error_log(__METHOD__.__LINE__.' Rows fetched:'.$rowsFetched.' Data:'.array2string($sortResult));
			$cols = array('check','status','attachments','subject','toaddress','fromaddress','date','size');
			if ($GLOBALS['egw_info']['user']['preferences']['common']['select_mode']=='EGW_SELECTMODE_TOGGLE') unset($cols[0]);
			return $this->header2gridelements($sortResult['header'],$cols, $_folderName, $uidOnly,$folderType,$dataForXMails=50,$previewMessage);
		}

		private static function get_columns_obj($load_userdata = true, $foldertype=0,$_folderName='')
		{
			$f_md5='';
			if (!empty($_folderName)) $f_md5=md5($_folderName);
			if (empty($_folderName)) error_log(__METHOD__.__LINE__.' Empty FolderName:'.$_folderName.' ->'.$f_md5.' backtrace:'.function_backtrace());
			$obj = new egw_grid_columns('felamimail', 'mainview'.$f_md5); //app- and grid-name
			//error_log(__METHOD__.__LINE__.'SelectMode:'.$GLOBALS['egw_info']['user']['preferences']['common']['select_mode']);
			switch($GLOBALS['egw_info']['user']['preferences']['felamimail']['rowOrderStyle']) {
				case 'outlook':
					$default_data = array(
						array(
							"id" => "check",
							"caption" => lang('Selection'),
							"type" => EGW_COL_TYPE_CHECKBOX,
							//"width" => "20px",
							"visibility" => EGW_COL_VISIBILITY_INVISIBLE,
						),
						array(
							"id" => "status",
							"caption" => '',
							"width" => "20px",
							"visibility" => EGW_COL_VISIBILITY_ALWAYS_NOSELECT,
						),
						array(
							"id" => "attachments",
							"caption" => '',
							"width" => "26px",
							"visibility" => EGW_COL_VISIBILITY_ALWAYS_NOSELECT,
						),
						array(
							"id" => "toaddress", // sent or drafts or template folder means foldertype > 0, use to address instead of from
							"caption" => '<a id="gridHeaderTo" href="#" onclick="mail_changeSorting(\'to\', this); return false;">'.lang("to").'</a>',
							"width" => "120px",
							"visibility" =>  ($foldertype>0?EGW_COL_VISIBILITY_VISIBLE:EGW_COL_VISIBILITY_INVISIBLE)
						),
						array(
							"id" => "fromaddress",// sent or drafts or template folder means foldertype > 0, use to address instead of from
							"caption" => '<a id="gridHeaderFrom" href="#" onclick="mail_changeSorting(\'from\', this); return false;">'.lang("from").'</a>',
							"width" => "120px",
							"visibility" =>  ($foldertype>0?EGW_COL_VISIBILITY_INVISIBLE:EGW_COL_VISIBILITY_VISIBLE)
						),
						array(
							"id" => "subject",
							"caption" => '<a id="gridHeaderSubject" href="#" onclick="mail_changeSorting(\'subject\', this); return false;">'.lang("subject").'</a>',
							"visibility" => EGW_COL_VISIBILITY_ALWAYS
						),
						array(
							"id" => "date",
							"width" => "95px",
							"caption" => '<a id="gridHeaderDate" href="#" onclick="mail_changeSorting(\'date\', this); return false;" title="'.lang("Date Received").'">'.lang("date").'</a>',
						),
						array(
							"id" => "size",
							"caption" => '<a id="gridHeaderSize" href="#" onclick="mail_changeSorting(\'size\', this); return false;">'.lang("size").'</a>',
							"width" => "40px",
						),
					);
					break;
				default:
					$default_data = array(
						array(
							"id" => "check",
							"caption" => lang('Selection'),
							"type" => EGW_COL_TYPE_CHECKBOX,
							//"width" => "20px",
							"visibility" => EGW_COL_VISIBILITY_INVISIBLE,
						),
						array(
							"id" => "status",
							"caption" => '',
							"width" => "20px",
							"visibility" => EGW_COL_VISIBILITY_ALWAYS_NOSELECT,
						),
						array(
							"id" => "attachments",
							"caption" => '',
							"width" => "26px",
							"visibility" => EGW_COL_VISIBILITY_ALWAYS_NOSELECT,
						),
						array(
							"id" => "subject",
							"caption" => '<a id="gridHeaderSubject" href="#" onclick="mail_changeSorting(\'subject\', this); return false;">'.lang("subject").'</a>',
							"visibility" => EGW_COL_VISIBILITY_ALWAYS,
						),
						array(
							"id" => "date",
							"width" => "105px",
							"caption" => '<a id="gridHeaderDate" href="#" onclick="mail_changeSorting(\'date\', this); return false;" title="'.lang("Date Received").'">'.lang("date").'</a>',
						),
						array(
							"id" => "toaddress",// sent or drafts or template folder means foldertype > 0, use to address instead of from
							"caption" => '<a id="gridHeaderTo" href="#" onclick="mail_changeSorting(\'to\', this); return false;">'.lang("to").'</a>',
							"width" => "120px",
							"visibility" =>  ($foldertype>0?EGW_COL_VISIBILITY_VISIBLE:EGW_COL_VISIBILITY_INVISIBLE)
						),
						array(
							"id" => "fromaddress",// sent or drafts or template folder means foldertype > 0, use to address instead of from
							"caption" => '<a id="gridHeaderFrom" href="#" onclick="mail_changeSorting(\'from\', this); return false;">'.lang("from").'</a>',
							"width" => "120px",
							"visibility" =>  ($foldertype>0?EGW_COL_VISIBILITY_INVISIBLE:EGW_COL_VISIBILITY_VISIBLE)
						),
						array(
							"id" => "size",
							"caption" => '<a id="gridHeaderSize" href="#" onclick="mail_changeSorting(\'size\', this); return false;">'.lang("size").'</a>',
							"width" => "40px",
						),
					);
					break;
			}
			// unset the check column if not needed for usability e.g.: TOGGLE mode
			if ($GLOBALS['egw_info']['user']['preferences']['common']['select_mode']=='EGW_SELECTMODE_TOGGLE') unset($default_data[0]);
			// Store the generated data structure inside the object
			$obj->load_grid_data($default_data);

			if ($load_userdata)
			{
				// Load the userdata
				$obj->load_userdata();
			}
			//error_log(__METHOD__.__LINE__.array2string($obj));
			return $obj;
		}

		/**
		 * AJAX function for storing the column data
		 */
		public function ajax_store_coldata($data)
		{
			$this->bofelamimail->restoreSessionData();
			$_folderName = $this->bofelamimail->sessionData['mailbox'];
			$folderType = $this->bofelamimail->getFolderType($_folderName);
			$obj = self::get_columns_obj(true,$folderType,$_folderName);
			$obj->store_userdata($data);
		}

		/**
		 * Interface function for fetching the data requested by the grid view
		 */
		public function ajax_fetch_data($elements, $columns)
		{
			$response = egw_json_response::get();
			if (count($elements)>0)
			{
				//error_log(__METHOD__.__LINE__.' Uids to fetch:'.count($elements).'->'.array2string($elements).' Columns to fetch:'.array2string($columns));
				$this->bofelamimail->restoreSessionData();
				//$maxMessages = $GLOBALS['egw_info']["user"]["preferences"]["common"]["maxmatchs"];
				$maxMessages = count($elements);
				//if (empty($maxMessages)) $maxMessages = 50;
				$_folderName = $this->bofelamimail->sessionData['mailbox'];
				$folderType = $this->bofelamimail->getFolderType($_folderName);
				$sortResult = $this->bofelamimail->getHeaders(
					$_folderName,
					$offset=0,
					$maxMessages,
					$this->sessionData['sort'],
					$this->sessionData['sortReverse'],
					(array)$this->sessionData['messageFilter'],
					$elements
				);
				//error_log(__METHOD__.__LINE__.' Data:'.array2string($rvs));
				$response->data($this->header2gridelements($sortResult['header'],$columns,$_folderName, false, $folderType, false));
			}
			else
			{
				$response->data(array());
			}
		}

		public function header2gridelements($_headers, $cols, $_folderName, $uidOnly=false, $_folderType=0, $dataForXMails=false, $previewMessage=0)
		{
			$timestamp7DaysAgo =
				mktime(date("H"), date("i"), date("s"), date("m"), date("d")-7, date("Y"));
			$timestampNow =
				mktime(date("H"), date("i"), date("s"), date("m"), date("d"), date("Y"));
			$dateToday = date("Y-m-d");
			$rv = array();
			$i=0;
			$firstuid = null;
			foreach((array)$_headers as $header)
			{
				$i++;

				//error_log(__METHOD__.array2string($header));
				$result = array(
					"id" => $header['uid'],
					"group" => "mail", // activate the action links for mail objects
				);
				$message_uid = $header['uid'];

				if ($dataForXMails && $i>$dataForXMails) $uidOnly=true; // only fetch the data for the first X Mails
				if ($uidOnly)
				{
					$rv[] = $result;
					continue;
				}
				$data = array();

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


				$data["status"] = "<span class=\"status_img\"></span>";
				//error_log(__METHOD__.array2string($header).' Flags:'.$flags);

				// the css for this row
				$is_recent=false;
				$css_styles = array("mail");
				if ($header['deleted']) {
					$css_styles[] = 'deleted';
				}
				if ($header['recent'] && !($header['deleted'] || $header['seen'] || $header['answered'] || $header['forwarded'])) {
					$css_styles[] = 'recent';
					$is_recent=true;
				}
				if ($header['flagged']) {
					$css_styles[] = 'flagged';
				}
				if (!$header['seen']) {
					$css_styles[] = 'unseen'; // different status image for recent // solved via css !important
				}
				if ($header['answered']) {
					$css_styles[] = 'replied';
				}
				if ($header['forwarded']) {
					$css_styles[] = 'forwarded';
				}
				//error_log(__METHOD__.array2string($css_styles));
				//if (in_array("check", $cols))
				// don't overwrite check with "false" as this forces the grid to
				// deselect the row - sending "0" doesn't do that
				if (in_array("check", $cols)) $data["check"] = $previewMessage == $header['uid'] ? true : 0;// $row_selected; //TODO:checkbox true or false
				//$data["check"] ='<input  style="width:12px; height:12px; border: none; margin: 1px;" class="{row_css_class}" type="checkbox" id="msgSelectInput" name="msg[]" value="'.$message_uid.'"
				//	onclick="toggleFolderRadio(this, refreshTimeOut)">';

				if (in_array("subject", $cols))
				{
					// filter out undisplayable characters
					$search = array('[\016]','[\017]',
						'[\020]','[\021]','[\022]','[\023]','[\024]','[\025]','[\026]','[\027]',
						'[\030]','[\031]','[\032]','[\033]','[\034]','[\035]','[\036]','[\037]');
					$replace = '';

					$header['subject'] = preg_replace($search,$replace,$header['subject']);
					$headerSubject = felamimail_bo::htmlentities($header['subject'],$this->charset);
					$header['subject'] = $headerSubject;
					// curly brackets get messed up by the template!
					$header['subject'] = str_replace(array('{','}'),array('&#x7B;','&#x7D;'),$header['subject']);

					if (!empty($header['subject'])) {
						// make the subject shorter if it is to long
						$fullSubject = $header['subject'];
						$subject = $header['subject'];
						#$this->t->set_var('attachments', $header['attachment']);
					} else {
						$subject = @htmlspecialchars('('. lang('no subject') .')', ENT_QUOTES, $this->charset);
					}

					if($_folderType == 2 || $_folderType == 3) {
						$linkData = array (
							'menuaction'    => 'felamimail.uicompose.composeFromDraft',
							'icServer'	=> 0,
							'folder' 	=> base64_encode($_folderName),
							'uid'		=> $header['uid'],
							'id'		=> $header['id'],
						);
						$url_read_message = $GLOBALS['egw']->link('/index.php',$linkData);

						$windowName = 'composeFromDraft_'.$header['uid'];
						$read_message_windowName = $windowName;
						$preview_message_windowName = $windowName;
					} else {
					#	_debug_array($header);
						$linkData = array (
							'menuaction'    => 'felamimail.uidisplay.display',
							'showHeader'	=> 'false',
							'mailbox'    => base64_encode($_folderName),
							'uid'		=> $header['uid'],
							'id'		=> $header['id'],
						);
						$url_read_message = $GLOBALS['egw']->link('/index.php',$linkData);

						$windowName = ($_readInNewWindow == 1 ? 'displayMessage' : 'displayMessage_'.$header['uid']);

						if ($this->use_preview) $windowName = 'MessagePreview_'.$header['uid'].'_'.$_folderType;

						$preview_message_windowName = $windowName;
					}

					$data["subject"] = /*'<a class="'.$css_style.'" name="subject_url" href="#"
						onclick="fm_handleMessageClick(false, \''.$url_read_message.'\', \''.$preview_message_windowName.'\', this); return false;"
						ondblclick="fm_handleMessageClick(true, \''.$url_read_message.'\', \''.$read_message_windowName.'\', this); return false;"
						title="'.$fullSubject.'">'.$subject.'</a>';//*/ $subject; // the mailsubject
				}

				//_debug_array($header);
				if (in_array("attachments", $cols))
				{
					if($header['mimetype'] == 'multipart/mixed' ||
						$header['mimetype'] == 'multipart/signed' ||
						$header['mimetype'] == 'multipart/related' ||
						$header['mimetype'] == 'multipart/report' ||
						$header['mimetype'] == 'text/calendar' ||
						substr($header['mimetype'],0,11) == 'application' ||
						substr($header['mimetype'],0,5) == 'audio' ||
						substr($header['mimetype'],0,5) == 'video')
					{
						$linkDataAttachments = array (
							'menuaction'    => 'felamimail.uidisplay.displayAttachments',
							'showHeader'	=> 'false',
							'mailbox'    => base64_encode($_folderName),
							'uid'		=> $header['uid'],
							'id'		=> $header['id'],
						);
						$windowName =  'displayMessage_'.$header['uid'];

						$image = html::image('felamimail','attach');
						if (//$header['mimetype'] != 'multipart/mixed' &&
							$header['mimetype'] != 'multipart/signed'
						)
						{
							if ($this->bofelamimail->icServer->_connected != 1)
							{
								$this->bofelamimail->openConnection($this->profileID); // connect to the current server
								$this->bofelamimail->reopen($_folderName);
							}
							$attachments = $this->bofelamimail->getMessageAttachments($header['uid'],$_partID='', $_structure='', $fetchEmbeddedImages=true, $fetchTextCalendar=false, $resolveTNEF=false);
							if (count($attachments)<1) $image = '&nbsp;';
						}
						if (count($attachments)>0) $image = "<a name=\"subject_url\" href=\"#\"
							onclick=\"fm_handleAttachmentClick(false,'".$GLOBALS['egw']->link('/index.php',$linkDataAttachments)."', '".$windowName."', this); return false;\"
							title=\"".$header['subject']."\">".$image."</a>";

						$attachmentFlag = $image;
					} else {
						$attachmentFlag ='&nbsp;';
					}
					// show priority flag
					if ($header['priority'] < 3) {
						 $image = html::image('felamimail','prio_high');
					} elseif ($header['priority'] > 3) {
						$image = html::image('felamimail','prio_low');
					} else {
						$image = '';
					}

					$data["attachments"] = $image.$attachmentFlag; // icon for attachments available
				}

				// sent or draft or template folder -> to address
				if (in_array("toaddress", $cols))
				{
					if(!empty($header['to_name'])) {
						list($mailbox, $host) = explode('@',$header['to_address']);
						$senderAddress  = imap_rfc822_write_address($mailbox,
								$host,
								$header['to_name']);
					} else {
						$senderAddress  = $header['to_address'];
					}
					$linkData = array
					(
						'menuaction'    => 'felamimail.uicompose.compose',
						'send_to'	=> base64_encode($senderAddress)
					);
					$windowName = 'compose_'.$header['uid'];

					// sent or drafts or template folder means foldertype > 0, use to address instead of from
					$header2add = felamimail_bo::htmlentities($header['to_address'],$this->charset);
					$header['to_address'] = $header2add;
					if (!empty($header['to_name'])) {
						$header2name = felamimail_bo::htmlentities($header['to_name'],$this->charset);
						$header['to_name'] = $header2name;

						$sender_name	= $header['to_name'];
						$full_address	= $header['to_name'].' <'.$header['to_address'].'>';
					} else {
						$sender_name	= $header['to_address'];
						$full_address	= $header['to_address'];
					}
					$data["toaddress"] = "<nobr><a href=\"#\" onclick=\"fm_handleComposeClick(false,'".$GLOBALS['egw']->link('/index.php',$linkData)."', '".$windowName."', this); return false;\" title=\"".@htmlspecialchars($full_address, ENT_QUOTES | ENT_IGNORE, $this->charset,false)."\">".@htmlspecialchars($sender_name, ENT_QUOTES | ENT_IGNORE, $this->charset,false)."</a></nobr>";
				}

				//fromaddress
				if (in_array("fromaddress", $cols))
				{
					$header2add = felamimail_bo::htmlentities($header['sender_address'],$this->charset);
					$header['sender_address'] = $header2add;
					if (!empty($header['sender_name'])) {
						$header2name = felamimail_bo::htmlentities($header['sender_name'],$this->charset);
						$header['sender_name'] = $header2name;

						$sender_name	= $header['sender_name'];
						$full_address	= $header['sender_name'].' <'.$header['sender_address'].'>';
					} else {
						$sender_name	= $header['sender_address'];
						$full_address	= $header['sender_address'];
					}
					if(!empty($header['sender_name'])) {
						list($mailbox, $host) = explode('@',$header['sender_address']);
						$senderAddress  = imap_rfc822_write_address($mailbox,
								$host,
								$header['sender_name']);
					} else {
						$senderAddress  = $header['sender_address'];
					}

					$linkData = array
					(
						'menuaction'    => 'felamimail.uicompose.compose',
						'send_to'	=> base64_encode($senderAddress)
					);
					$windowName = 'compose_'.$header['uid'];

					$data["fromaddress"] = "<nobr><a href=\"#\" onclick=\"fm_handleComposeClick(false,'".$GLOBALS['egw']->link('/index.php',$linkData)."', '".$windowName."', this); return false;\" title=\"".@htmlspecialchars($full_address, ENT_QUOTES | ENT_IGNORE, $this->charset,false)."\">".@htmlspecialchars($sender_name, ENT_QUOTES | ENT_IGNORE, $this->charset,false)."</a></nobr>";
				}
				if (in_array("date", $cols))
				{
					if ($dateToday == felamimail_bo::_strtotime($header['date'],'Y-m-d')) {
	 				    $dateShort = felamimail_bo::_strtotime($header['date'],($GLOBALS['egw_info']['user']['preferences']['common']['timeformat']==12?'h:i:s a':'H:i:s'));
					} else {
						$dateShort = felamimail_bo::_strtotime($header['date'],str_replace('Y','y',$GLOBALS['egw_info']['user']['preferences']['common']['dateformat']).' '.
							($GLOBALS['egw_info']['user']['preferences']['common']['timeformat'] == 12 ? 'h:i a' : 'H:i'));
					}
					$data["date"] = $dateShort;//'<nobr><span style="font-size:10px" title="'.$dateLong.'">'.$dateShort.'</span></nobr>';
				}

				if (in_array("size", $cols))
					$data["size"] = $this->show_readable_size($header['size']); /// size


				/*
				//TODO: url_add_to_addressbook isn't in any of the templates.
				//If you want to use it, you need to adopt syntax to the new addressbook (popup)
				$this->t->set_var('url_add_to_addressbook',$GLOBALS['egw']->link('/index.php',$linkData));
				*/
				//$this->t->set_var('msg_icon_sm',$msg_icon_sm);

				//$this->t->set_var('phpgw_images',EGW_IMAGES);
				$result["data"] = $data;
				$result["rowClass"] = implode(' ', $css_styles);
				$rv[] = $result;
				//error_log(__METHOD__.__LINE__.array2string($result));
			}
			return $rv;
		}

		// $_folderType 0: normal imap folder 1: sent folder 2: draft folder 3: template folder
		// $_rowStyle felamimail or outlook
		function messageTable($_headers, $_folderType, $_folderName, $_readInNewWindow, $_rowStyle='felamimail',$messageToBePreviewed=0)
		{
			//error_log(__METHOD__.' preview Message:'.$messageToBePreviewed);
			$this->t = CreateObject('phpgwapi.Template',EGW_APP_TPL);
			$this->t->set_file(array("body" => 'mainscreen.tpl'));

			$this->t->set_block('body','message_table');

			$i=0;
			$firstuid = null;
			foreach((array)$_headers['header'] as $header)
			{
				//error_log(__METHOD__.__LINE__.array2string($header));
				// preview the message with the requested (messageToBePreviewed) uid
				if ($messageToBePreviewed>0
					&& $this->use_preview
					&& $messageToBePreviewed == $header['uid'])
				{
					//error_log(__METHOD__.$header['uid']);
					$selecteduid = $header['uid'];
					$firstheader = $header;
				}
				$this->t->set_var('msg_icon_sm',$msg_icon_sm);

				$this->t->set_var('phpgw_images',EGW_IMAGES);


			}

			if ($this->use_preview)
			{
				$this->t->set_var('selected_style'.$selecteduid,'style="background-color:#ddddFF;"');
			} else {
				$this->t->set_var('selected_style'.$selecteduid,'');
			}
			//error_log(__METHOD__.__LINE__.' FolderType:'.$_folderType);
			if ($this->use_preview)
			{
				if ($GLOBALS['egw_info']['user']['preferences']['felamimail']['PreViewFrameHeight'] &&
					stripos($GLOBALS['egw_info']['user']['preferences']['felamimail']['PreViewFrameHeight'],',') !== false)
				{
					list($previewFrameHeight, $messageListHeight) = explode(',',$GLOBALS['egw_info']['user']['preferences']['felamimail']['PreViewFrameHeight']);
					$previewFrameHeight = trim($previewFrameHeight);
					$messageListHeight = trim($messageListHeight);
					if (empty($messageListHeight)) $messageListHeight = $this->messageListMinHeight;
				}
				else
				{
					$previewFrameHeight = $GLOBALS['egw_info']['user']['preferences']['felamimail']['PreViewFrameHeight'];
					$messageListHeight = 100; // old default for minimum height
				}
			}

			if ($firstheader &&
				$this->use_preview &&
				($_folderType==0 || $_folderType==1)) // only if not drafts or template folder
			{
				$IFRAMEBody =  $this->updateMessagePreview($firstheader,$_folderType,$_folderName,$this->profileID);
			}
			else
			{
				if ($this->use_preview)
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
									<TD nowrap id=\"tdmessageIFRAME\" valign=\"top\" height=\"".$previewFrameHeight."\">
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
			//$this->t->set_var('messagelist_height',($this->use_preview ? ($messageListHeight).'px':'auto'));
			$this->t->set_var('messagelist_height',($this->use_preview ? ($messageListHeight).'px':$this->messageListMinHeight.'px'));
			$this->t->set_var('previewiframe_height',($this->use_preview ? ($previewFrameHeight).'px':'0px'));

			$this->t->parse("out","message_table");

			return $this->t->get('out','message_table');
		}

		function updateMessagePreview($headerData,$_folderType,$_folderName,$_icServer=0)
		{
			// IFrame for Preview ....
			if ($headerData['uid'] && $this->use_preview)
			{
				//error_log(__METHOD__.__LINE__.array2string($headerData).function_backtrace());
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
						$jscall= "sendNotifyMS(".$headerData['uid']."); ";
					}
				}

				//if (strpos( array2string($flags),'Seen')===false) $this->bofelamimail->flagMessages('read', $headerData['uid']);
				if ($_folderType > 0) {
					$addtoaddresses='';
					// sent or drafts or template folder
					if (!empty($headerData['to_name'])) {
						$sender_names[0]	= $headerData['to_name'];
						$sender_addresses[0] = $headerData['to_address'];
						$full_addresses[0]	= $headerData['to_name'].' &lt;'.$headerData['to_address'].'&gt;';
					} else {
						$sender_names[0]	= $headerData['to_address'];
						$sender_addresses[0] = $headerData['to_address'];
						$full_addresses[0]	= $headerData['to_address'];
					}
					if (!empty($headerData['additional_to_addresses']))
					{
						foreach ($headerData['additional_to_addresses'] as $k => $addset)
						{
							$sender_names[]	= (!empty($headerData['additional_to_addresses'][$k]['name'])?$headerData['additional_to_addresses'][$k]['name']:$headerData['additional_to_addresses'][$k]['address']);
							$sender_addresses[] = $headerData['additional_to_addresses'][$k]['address'];
							$full_addresses[]	= (!empty($headerData['additional_to_addresses'][$k]['name'])?$headerData['additional_to_addresses'][$k]['name'].' &lt;':'').$headerData['additional_to_addresses'][$k]['address'].(!empty($headerData['additional_to_addresses'][$k]['name'])?'&gt;':'');
						}
					}
				} else {
					if (!empty($headerData['sender_name'])) {
						$sender_names[0]	= $headerData['sender_name'];
						$sender_addresses[0] = $headerData['sender_address'];
						$full_addresses[0]	= $headerData['sender_name'].' &lt;'.$headerData['sender_address'].'&gt;';
					} else {
						$sender_names[0]	= $headerData['sender_address'];
						$sender_addresses[0] = $headerData['sender_address'];
						$full_addresses[0]	= $headerData['sender_address'];
					}
				}

				//$fromAddress   = uidisplay::emailAddressToHTML(array('PERSONAL_NAME'=>$sender_name,'EMAIL'=>$sender_address,'RFC822_EMAIL'=>$full_address),'');
				if ($GLOBALS['egw_info']['user']['apps']['addressbook']) {
					foreach ($sender_names as $k => $sender_name)
					{
						//error_log(__METHOD__.__LINE__.' '.$k.'->'.$sender_name);
						$sender_address = $sender_addresses[$k];
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

						$urlAddToAddressbook =  $GLOBALS['egw']->link('/index.php',$addresslinkData);
						$onClick = "window.open(this,this.target,'dependent=yes,width=850,height=440,location=no,menubar=no,toolbar=no,scrollbars=yes,status=yes'); return false;";
						$image = $GLOBALS['egw']->common->image('felamimail','sm_envelope');
						$fromAddress .= ($k>0?', ':''). $full_addresses[$k].''. sprintf('<a href="%s" onClick="%s">
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
						$attachments = $this->bofelamimail->getMessageAttachments($headerData['uid'],$_partID='', $_structure='', $fetchEmbeddedImages=true, $fetchTextCalendar=false, $resolveTNEF=false);
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
				$script = "";
				// if browser supports data uri: ie<8 does NOT and ie>=8 does NOT support html as content :-(
				// --> use it to send the mail as data uri
				if (!isset($_GET['printable']))
				{
					$uidisplay = CreateObject('felamimail.uidisplay');
					$uidisplay->uid = $headerData['uid'];
					$uidisplay->mailbox = $_folderName;
					$mailData = $uidisplay->get_load_email_data($headerData['uid'], $partID);
					//error_log(__METHOD__.__LINE__.array2string($mailData));
					$iframe_url = $mailData['src'];
					$jscall .= $mailData['onload'];
					$script = $mailData['script'];
					unset($uidisplay);
				}
				//_debug_array($GLOBALS['egw']->link('/index.php',$linkData));
				$IFRAMEBody = "<TABLE BORDER=\"1\" rules=\"rows\" style=\"table-layout:fixed;width:100%;\">
								<TR class=\"th\" style=\"width:100%;\">
									<TD nowrap valign=\"top\" style=\"overflow:hidden;\">
										".($_folderType > 0?lang('to'):lang('from')).':'.'<b>'.($fromAddress?$fromAddress:'') .'</b><br> '.
										lang('date').':<b>'.felamimail_bo::_strtotime($headerData['date'],$GLOBALS['egw_info']['user']['preferences']['common']['dateformat']).
                                                ' - '.felamimail_bo::_strtotime($headerData['date'],($GLOBALS['egw_info']['user']['preferences']['common']['timeformat']==12?'h:i:s a':'H:i:s'))."</b><br>
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
										$script
										<iframe ".(!empty($jscall) ? "onload=\"$jscall\"" :"")." id=\"messageIFRAME\" frameborder=\"1\" height=\"".$IFrameHeight."\" scrolling=\"auto\" src=\"".$iframe_url."\">
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
				'menuaction'	=> 'felamimail.uifelamimail.'.($_headerData['deleted']?'un':'').'deleteMessage',
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
				'revert'	=> array(
					'action'	=> ($_forceNewWindow ? "window.open('$deleteURL','_blank','dependent=yes,width=100,height=100,toolbar=no,scrollbars=no,status=no')": "window.location.href = '$deleteURL'"),
					'tooltip'	=> ($_headerData['deleted']?lang('undelete'):lang('delete')),
				),
				'delete'	=> array(
					'action'	=> ($_forceNewWindow ? "window.open('$deleteURL','_blank','dependent=yes,width=100,height=100,toolbar=no,scrollbars=no,status=no')": "window.location.href = '$deleteURL'"),
					'tooltip'	=> ($_headerData['deleted']?lang('undelete'):lang('delete')),
				),
			);
			// display only the correct icon: revert on deleted messages, delete on all others
			if ($_headerData['deleted']) 
			{
				unset($navbarImages['delete']);
			} else {
				unset($navbarImages['revert']);
			}
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
				'print' => array(
					'action'	=> ($_forceNewWindow ? "egw_openWindowCentered('$printURL','print_".$_headerData['uid']."',".$fm_width.",".$fm_height.");": "window.location.href = '$printURL'"),
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
					'tooltip'   => lang('Save as ticket'));
			}
			// save email as
			$navbarImages['fileexport'] = array(
				'action'	=> "document.location='$saveMessageURL';",//"($_forceNewWindow ? "window.open('$saveMessageURL','_blank','dependent=yes,width=100,height=100,scrollbars=yes,status=yes')": "window.location.href = '$saveMessageURL'"),
				'tooltip'	=> lang('Save message to disk'),
			);

			// view header lines
			$navbarImages['kmmsgread'] = array(
				'action'	=> "mail_displayHeaderLines('$viewHeaderURL')",
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
			$_toolTip = felamimail_bo::htmlentities($_toolTip);
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
