<?php
/**
 * EGroupware - FeLaMiMail - user interface
 *
 * @link http://www.egroupware.org
 * @package felamimail
 * @author Lars Kneschke [lkneschke@linux-at-work.de]
 * @author Klaus Leithoff [kl@stylite.de]
 * @copyright (c) 2009-10 by Klaus Leithoff <kl-AT-stylite.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * FeLaMiMail user interface class, provides UI functionality for mainview
 */
class uifelamimail
{
		var $public_functions = array
		(
			'addVcard'		=> True,
			'changeFilter'		=> True,
			'changeFolder'		=> True,
			'changeSorting'		=> True,
			'compressFolder'	=> True,
			'importMessage'		=> True,
			'deleteMessage'		=> True,
			'undeleteMessage'     => True,
			'hookAdmin'		=> True,
			'toggleFilter'		=> True,
			'viewMainScreen'	=> True,
			'redirectToPreferences' => True,
			'redirectToEmailadmin' => True,
		);

		var $mailbox;		// the current folder in use
		var $startMessage;	// the first message to show
		var $sort;		// how to sort the messages
		var $moveNeeded;	// do we need to move some messages?

		var $timeCounter;

		// the object storing the data about the incoming imap server
		static $icServerID;
		var $connectionStatus = false;
		var $bofelamimail;
		var $bofilter;
		var $bopreferences;

		function uifelamimail()
		{
			//error_log(__METHOD__);
			// no autohide of the sidebox, as we use it for folderlist now.
			unset($GLOBALS['egw_info']['user']['preferences']['common']['auto_hide_sidebox']);
			$this->timeCounter = microtime(true);
			if (!isset($GLOBALS['egw_info']['user']['preferences']['felamimail']['ActiveProfileID']))
			{
				// globals preferences add appname varname value
				$GLOBALS['egw']->preferences->add('felamimail','ActiveProfileID',0,'user');
				// save prefs
				$GLOBALS['egw']->preferences->save_repository(true);
			}
			if (is_null(self::$icServerID)) self::$icServerID =& egw_cache::getSession('felamimail','activeProfileID');

			$this->displayCharset	= translation::charset();

			if (isset($GLOBALS['egw_info']['user']['preferences']['felamimail']['ActiveProfileID']))
				self::$icServerID = (int)$GLOBALS['egw_info']['user']['preferences']['felamimail']['ActiveProfileID'];

			//error_log(__METHOD__.'->'.self::$icServerID);
			$this->bofelamimail     = felamimail_bo::getInstance(false,self::$icServerID);
			self::$icServerID = $GLOBALS['egw_info']['user']['preferences']['felamimail']['ActiveProfileID'] = $this->bofelamimail->profileID;
			$this->bofilter		= new felamimail_bofilter(false);
			$this->bopreferences=& $this->bofelamimail->bopreferences;
			$this->preferences	=& $this->bofelamimail->mailPreferences;

			if (is_object($this->preferences))
			{
				// account select box
				$selectedID = $this->bofelamimail->getIdentitiesWithAccounts($identities);
				// if nothing valid is found return to user defined account definition
				if (empty($this->bofelamimail->icServer->host) && count($identities)==0 && $this->preferences->userDefinedAccounts)
				{
					// redirect to new personal account
					egw::redirect_link('/index.php',array('menuaction'=>'felamimail.uipreferences.editAccountData',
						'accountID'=>"new",
						'msg'   => lang("There is no IMAP Server configured.")." - ".lang("Please configure access to an existing individual IMAP account."),
					));
				}
			}
			$this->bofelamimail->saveSessionData();

			$this->mailbox 		= $this->bofelamimail->sessionData['mailbox'];
			$this->startMessage 	= $this->bofelamimail->sessionData['startMessage'];
			$this->sort 		= $this->bofelamimail->sessionData['sort'];
			$this->sortReverse 	= $this->bofelamimail->sessionData['sortReverse'];
			#$this->filter 		= $this->bofelamimail->sessionData['activeFilter'];

			$this->t			= CreateObject('phpgwapi.Template',EGW_APP_TPL);
			#$this->grants[$this->account]	= EGW_ACL_READ + EGW_ACL_ADD + EGW_ACL_EDIT + EGW_ACL_DELETE;
			// this need to fixed
			// this does not belong to here

			if($_GET['menuaction'] != 'felamimail.uifelamimail.hookAdmin' &&
				 $_GET['menuaction'] != 'felamimail.uifelamimail.changeFolder') {
				$this->connectionStatus = $this->bofelamimail->openConnection(self::$icServerID);
			}

			$this->rowColor[0] = $GLOBALS['egw_info']["theme"]["row_on"];
			$this->rowColor[1] = $GLOBALS['egw_info']["theme"]["row_off"];

			$this->dataRowColor[0] = $GLOBALS['egw_info']["theme"]["bg01"];
			$this->dataRowColor[1] = $GLOBALS['egw_info']["theme"]["bg02"];
			#print __LINE__ . ': ' . (microtime(true) - $this->timeCounter) . '<br>';
		}

		function redirectToPreferences ()
		{
			$this->display_app_header(false);
			//appname is a $_GET parameter, so the passing as function parameter does not work
			ExecMethod('preferences.uisettings.index',array('appname'=>'felamimail'));
			exit;
		}

		function redirectToEmailadmin ()
		{
			//$GLOBALS['egw_info']['flags']['currentapp'] = 'emailadmin';
			$this->display_app_header(false);
			if (!file_exists(EGW_SERVER_ROOT.($et_css_file ='/etemplate/templates/'.$GLOBALS['egw_info']['user']['preferences']['common']['template_set'].'/app.css')))
			{
				$et_css_file = '/etemplate/templates/default/app.css';
			}
			echo '
<style type="text/css">
<!--
    @import url('.$GLOBALS['egw_info']['server']['webserver_url'].$et_css_file.');
-->
</style>';

			ExecMethod2('emailadmin.emailadmin_ui.index');
			exit;
		}

		function addVcard()
		{
			error_log(__METHOD__." called from:".function_backtrace());
			$messageID 	= $_GET['messageID'];
			$partID 	= $_GET['partID'];
			$attachment = $this->bofelamimail->getAttachment($messageID,$partID);

			$tmpfname = tempnam ($GLOBALS['egw_info']['server']['temp_dir'], "egw_");
			$fp = fopen($tmpfname, "w");
			fwrite($fp, $attachment['attachment']);
			fclose($fp);

			$vcard = CreateObject('phpgwapi.vcard');
			$entry = $vcard->in_file($tmpfname);
			$entry['owner'] = $GLOBALS['egw_info']['user']['account_id'];
			$entry['access'] = 'private';
			$entry['tid'] = 'n';

			print quoted_printable_decode($entry['fn'])."<br>";

			unlink($tmpfname);

			$GLOBALS['egw']->common->egw_exit();
		}

		function changeFilter()
		{
			error_log(__METHOD__." called from:".function_backtrace());
			if(isset($_POST["filter"]))
			{
				$data['quickSearch']	= $_POST["quickSearch"];
				$data['filter']		= $_POST["filter"];
				$this->bofilter->updateFilter($data);
			}
			elseif(isset($_GET["filter"]))
			{
				$data['filter']		= $_GET["filter"];
				$this->bofilter->updateFilter($data);
			}
			$this->viewMainScreen();
		}

		function changeFolder()
		{
			// change folder
			$this->bofelamimail->sessionData['mailbox']	= urldecode($_GET["mailbox"]);
			$this->bofelamimail->sessionData['startMessage']= 1;
			$this->bofelamimail->sessionData['sort']	= $this->preferences->preferences['sortOrder'];
			$this->bofelamimail->sessionData['activeFilter']= -1;

			$this->bofelamimail->saveSessionData();

			$this->mailbox 		= $this->bofelamimail->sessionData['mailbox'];
			$this->startMessage 	= $this->bofelamimail->sessionData['startMessage'];
			$this->sort 		= $this->bofelamimail->sessionData['sort'];

			$this->connectionStatus = $this->bofelamimail->openConnection();

			$this->viewMainScreen();
		}

		function changeSorting()
		{
			error_log(__METHOD__." called from:".function_backtrace());
			// change sorting
			if(isset($_GET["sort"]))
			{
				$this->bofelamimail->sessionData['sort']	= $_GET["sort"];
				$this->sort					= $_GET["sort"];

				$this->bofelamimail->saveSessionData();
			}

			$this->viewMainScreen();
		}

		/**
		 * importMessage
		 */
		function importMessage()
		{
			//error_log(array2string($_POST));
			if (empty($importtype)) $importtype = htmlspecialchars($_POST["importtype"]);
			if (empty($toggleFS)) $toggleFS = htmlspecialchars($_POST["toggleFS"]);
			if (empty($importID)) $importID = htmlspecialchars($_POST["importid"]);
			if (empty($addFileName)) $addFileName =html::purify($_POST['addFileName']);
			if (empty($importtype)) $importtype = 'file';
			if (empty($toggleFS)) $toggleFS= false;
			if (empty($addFileName)) $addFileName = false;
			if ($toggleFS == 'vfs' && $importtype=='file') $importtype='vfs';
			if (!$toggleFS && $importtype=='vfs') $importtype='file';

			// get passed messages
			if (!empty($_GET["msg"])) $alert_message[] = html::purify($_GET["msg"]);
			if (!empty($_POST["msg"])) $alert_message[] = html::purify($_POST["msg"]);
			unset($_GET["msg"]);
			unset($_POST["msg"]);
			//_debug_array($alert_message);
			//error_log(__METHOD__." called from:".function_backtrace());
			$proceed = false;
			if(is_array($_FILES["addFileName"]))
			{
				//phpinfo();
				//error_log(print_r($_FILES,true));
				if($_FILES['addFileName']['error'] == $UPLOAD_ERR_OK) {
					$proceed = true;
					$formData['name']	= $_FILES['addFileName']['name'];
					$formData['type']	= $_FILES['addFileName']['type'];
					$formData['file']	= $_FILES['addFileName']['tmp_name'];
					$formData['size']	= $_FILES['addFileName']['size'];
				}
			}
			if ($addFileName && $toggleFS == 'vfs' && $importtype == 'vfs' && $importID)
			{
				$sessionData = $GLOBALS['egw']->session->appsession('compose_session_data_'.$importID, 'felamimail');
				//error_log(__METHOD__.__LINE__.array2string($sessionData));
				foreach((array)$sessionData['attachments'] as $attachment) {
					//error_log(__METHOD__.__LINE__.array2string($attachment));
					if ($addFileName == $attachment['name'])
					{
						$proceed = true;
						$formData['name']	= $attachment['name'];
						$formData['type']	= $attachment['type'];
						$formData['file']	= $attachment['file'];
						$formData['size']	= $attachment['size'];
						break;
					}
				}
			}
			if ($proceed === true)
			{
				$destination = html::purify($_POST['newMailboxMoveName']?$_POST['newMailboxMoveName']:'');
				try
				{
					$messageUid = $this->importMessageToFolder($formData,$destination,$importID);
				    $linkData = array
				    (
				        'menuaction'    => 'felamimail.uidisplay.display',
						'uid'		=> $messageUid,
						'mailbox'    => base64_encode($destination),
				    );
				}
				catch (egw_exception_wrong_userinput $e)
				{
				    $linkData = array
				    (
				        'menuaction'    => 'felamimail.uifelamimail.importMessage',
						'msg'		=> htmlspecialchars($e->getMessage()),
				    );
				}
				egw::redirect_link('/index.php',$linkData);
				exit;
			}

			if(!@is_object($GLOBALS['egw']->js))
			{
				$GLOBALS['egw']->js = CreateObject('phpgwapi.javascript');
			}
			// this call loads js and css for the treeobject
			html::tree(false,false,false,null,'foldertree','','',false,'/',null,false);
			$GLOBALS['egw']->common->egw_header();

			#$uiwidgets		=& CreateObject('felamimail.uiwidgets');

			$this->t->set_file(array("importMessage" => "importMessage.tpl"));

			$this->t->set_block('importMessage','fileSelector','fileSelector');
			$importID =felamimail_bo::getRandomString();

			// prepare saving destination of imported message
			$linkData = array
			(
					'menuaction'    => 'felamimail.uipreferences.listSelectFolder',
			);
			$this->t->set_var('folder_select_url',$GLOBALS['egw']->link('/index.php',$linkData));

			// messages that may be passed to the Form
			if (isset($alert_message) && !empty($alert_message))
			{
				$this->t->set_var('messages', implode('; ',$alert_message));
			}
			else
			{
				$this->t->set_var('messages','');
			}

			// preset for saving destination, we use draftfolder
			$savingDestination = ($this->preferences->ic_server[0]->draftfolder ? $this->preferences->ic_server[0]->draftfolder : $GLOBALS['egw_info']['user']['preferences']['felamimail']['draftFolder']);

			$this->t->set_var('mailboxNameShort', $savingDestination);
			$this->t->set_var('importtype', $importtype);
			$this->t->set_var('importid', $importID);
			if ($toggleFS) $this->t->set_var('toggleFS_preset','checked'); else $this->t->set_var('toggleFS_preset','');

			$this->translate();

			$linkData = array
			(
				'menuaction'	=> 'felamimail.uifelamimail.importMessage',
			);
			$this->t->set_var('file_selector_url', $GLOBALS['egw']->link('/index.php',$linkData));

			$this->t->set_var('vfs_selector_url', egw::link('/index.php',array(
				'menuaction' => 'filemanager.filemanager_select.select',
				'mode' => 'open-multiple',
				'method' => 'felamimail.uifelamimail.selectFromVFS',
				'id'	=> $importID,
				'label' => lang('Attach'),
			)));
			if ($GLOBALS['egw_info']['user']['apps']['filemanager'] && $importtype == 'vfs')
			{
				$this->t->set_var('vfs_attach_button','
				&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<a onclick="fm_import_displayVfsSelector();" title="'.htmlspecialchars(lang('filemanager')).'">
					<img src="'.$GLOBALS['egw']->common->image('filemanager','navbar').'" height="18">
				</a>&nbsp;&nbsp;&nbsp;&nbsp;');
				$this->t->set_var('filebox_readonly','readonly="readonly"');
			}
			else
			{
				$this->t->set_var('vfs_attach_button','');
				$this->t->set_var('filebox_readonly','');
			}

			$maxUploadSize = ini_get('upload_max_filesize');
			$this->t->set_var('max_uploadsize', $maxUploadSize);

			$this->t->set_var('ajax-loader', $GLOBALS['egw']->common->image('felamimail','ajax-loader'));

			$this->t->pparse("out","fileSelector");
		}

		/**
		 * Callback for filemanagers select file dialog
		 *
		 * @param string|array $files path of file(s) in vfs (no egw_vfs::PREFIX, just the path)
		 * @return string javascript output by the file select dialog, usually to close it
		 */
		function selectFromVFS($importid,$files)
		{
			//error_log(__METHOD__.__LINE__.'->ImportID:'.$importid);
			$bocompose   = CreateObject('felamimail.bocompose',$importid,$this->displayCharset);
			$path = implode(' ',$files);

			foreach((array) $files as $path)
			{
				$formData = array(
					'name' => egw_vfs::basename($path),
					'type' => egw_vfs::mime_content_type($path),
					'file' => egw_vfs::PREFIX.$path,
					'size' => filesize(egw_vfs::PREFIX.$path),
				);
				$bocompose->addAttachment($formData);
			}

			//error_log(__METHOD__.__LINE__.$path);
			return 'window.close();';
		}

		/**
		 * importMessageToFolder
		 *
		 * @param array $_formData Array with information of name, type, file and size
		 * @param string $_folder (passed by reference) will set the folder used. must be set with a folder, but will hold modifications if
		 *					folder is modified
		 * @param string $importID ID for the imported message, used by attachments to identify them unambiguously
		 * @return mixed $messageUID or exception
		 */
		function importMessageToFolder($_formData,&$_folder,$importID='')
		{
			$importfailed = false;

			// check if formdata meets basic restrictions (in tmp dir, or vfs, mimetype, etc.)
			try
			{
				$tmpFileName = felamimail_bo::checkFileBasics($_formData,$importID);
			}
			catch (egw_exception_wrong_userinput $e)
			{
				$importfailed = true;
				$alert_msg .= $e->getMessage();
			}
			// -----------------------------------------------------------------------
			if ($importfailed === false)
			{
				$mailObject = new egw_mailer();
				try
				{
					$this->bofelamimail->parseFileIntoMailObject($mailObject,$tmpFileName,$Header,$Body);
				}
				catch (egw_exception_assertion_failed $e)
				{
					$importfailed = true;
					$alert_msg .= $e->getMessage();
				}
				//_debug_array($Body);
				$this->bofelamimail->openConnection();
				if (empty($_folder))
				{
					$importfailed = true;
					$alert_msg .= lang("Import of message %1 failed. Destination Folder not set.",$_formData['name']);
				}
				$delimiter = $this->bofelamimail->getHierarchyDelimiter();
				if($_folder=='INBOX'.$delimiter) $_folder='INBOX';
				if ($importfailed === false)
				{
					if ($this->bofelamimail->folderExists($_folder,true)) {
						try
						{
							$messageUid = $this->bofelamimail->appendMessage($_folder,
								$Header.$mailObject->LE.$mailObject->LE,
								$Body,
								$flags);
						}
						catch (egw_exception_wrong_userinput $e)
						{
							$importfailed = true;
							$alert_msg .= lang("Import of message %1 failed. Could not save message to folder %2 due to: %3",$_formData['name'],$_folder,$e->getMessage());
						}
					}
					else
					{
						$importfailed = true;
						$alert_msg .= lang("Import of message %1 failed. Destination Folder %2 does not exist.",$_formData['name'],$_folder);
					}
				}
			}
			// set the url to open when refreshing
			if ($importfailed == true)
			{
				throw new egw_exception_wrong_userinput($alert_msg);
			}
			else
			{
				return $messageUid;
			}
		}

		function deleteMessage()
		{
			//error_log(__METHOD__." called from:".function_backtrace());
			$message[] = $_GET["message"];
			$mailfolder = NULL;
			if (!empty($_GET['folder'])) $mailfolder  = base64_decode($_GET['folder']);

			$this->bofelamimail->deleteMessages($message,$mailfolder);

			// set the url to open when refreshing
			$linkData = array
			(
				'menuaction'	=> 'felamimail.uifelamimail.viewMainScreen'
			);
			$refreshURL = $GLOBALS['egw']->link('/index.php',$linkData);

			print "<script type=\"text/javascript\">
			opener.location.href = '" .$refreshURL. "';
			window.close();</script>";
		}

		function undeleteMessage()
		{	// only for messages marked as deleted
			$message[] = $_GET["message"];
			$mailfolder = NULL;
			if (!empty($_GET['folder'])) $mailfolder  = base64_decode($_GET['folder']);
			$this->bofelamimail->flagMessages('undelete',$message,$mailfolder);
			// set the url to open when refreshing
			$linkData = array
			(
				'menuaction'    => 'felamimail.uifelamimail.viewMainScreen'
			);
			$refreshURL = $GLOBALS['egw']->link('/index.php',$linkData);
			print "<script type=\"text/javascript\">
			opener.location.href = '" .$refreshURL. "';
			window.close();</script>";
		}

		function display_app_header($includeFMStuff=true)
		{
			if ($includeFMStuff)
			{
				// this call loads js and css for the treeobject
				html::tree(false,false,false,null,'foldertree','','',false,'/',null,false);
				egw_framework::validate_file('jquery','jquery-ui');
				egw_framework::validate_file('dhtmlxtree','dhtmlxMenu/codebase/dhtmlxcommon');
				egw_framework::validate_file('dhtmlxtree','dhtmlxMenu/codebase/dhtmlxmenu');
				egw_framework::validate_file('egw_action','egw_action');
				egw_framework::validate_file('egw_action','egw_keymanager');
				egw_framework::validate_file('egw_action','egw_action_common');
				egw_framework::validate_file('egw_action','egw_action_popup');
				egw_framework::validate_file('egw_action','egw_action_dragdrop');
				egw_framework::validate_file('egw_action','egw_dragdrop_dhtmlx_tree');
				egw_framework::validate_file('egw_action','egw_menu');
				egw_framework::validate_file('egw_action','egw_menu_dhtmlx');
				egw_framework::validate_file('egw_action','egw_grid');
				egw_framework::validate_file('egw_action','egw_grid_data');
				egw_framework::validate_file('egw_action','egw_grid_view');
				egw_framework::validate_file('egw_action','egw_grid_columns');
				egw_framework::validate_file('egw_action','egw_stylesheet');

				// The ext stuff has to be loaded at the end
				egw_framework::validate_file('dhtmlxtree','dhtmlxMenu/codebase/ext/dhtmlxmenu_ext');

				egw_framework::validate_file('jscode','viewMainScreen','felamimail');
				egw_framework::includeCSS('/phpgwapi/js/egw_action/test/skins/dhtmlxmenu_egw.css');
				$GLOBALS['egw_info']['flags']['include_xajax'] = True;
			}
			$GLOBALS['egw']->common->egw_header();

			echo $GLOBALS['egw']->framework->navbar();
		}

		function hookAdmin()
		{
			if(!$GLOBALS['egw']->acl->check('run',1,'admin'))
			{
				$GLOBALS['egw']->common->egw_header();
				echo $GLOBALS['egw']->framework->navbar();
				echo lang('access not permitted');
				$GLOBALS['egw']->log->message('F-Abort, Unauthorized access to felamimail.uifelamimail.hookAdmin');
				$GLOBALS['egw']->log->commit();
				$GLOBALS['egw']->common->egw_exit();
			}

			if(!empty($_POST['profileID']) && is_int(intval($_POST['profileID'])))
			{
				$profileID = intval($_POST['profileID']);
				$this->bofelamimail->setEMailProfile($profileID);
			}

			$boemailadmin = new emailadmin_bo();

			$profileList = $boemailadmin->getProfileList();
			$profileID = $this->bofelamimail->getEMailProfile();

			$this->display_app_header();

			$this->t->set_file(array("body" => "selectprofile.tpl"));
			$this->t->set_block('body','main');
			$this->t->set_block('body','select_option');

			$this->t->set_var('lang_select_email_profile',lang('select emailprofile'));
			$this->t->set_var('lang_site_configuration',lang('site configuration'));
			$this->t->set_var('lang_save',lang('save'));
			$this->t->set_var('lang_back',lang('back'));

			$linkData = array
			(
				'menuaction'	=> 'felamimail.uifelamimail.hookAdmin'
			);
			$this->t->set_var('action_url',$GLOBALS['egw']->link('/index.php',$linkData));

			$linkData = array
			(
				'menuaction'	=> 'emailadmin.emailadmin_ui.listProfiles'
			);
			$this->t->set_var('lang_go_emailadmin', lang('use <a href="%1">EmailAdmin</a> to create profiles', $GLOBALS['egw']->link('/index.php',$linkData)));

			$this->t->set_var('back_url',$GLOBALS['egw']->link('/admin/index.php'));

			if(isset($profileList) && is_array($profileList))
			{
				foreach($profileList as $key => $value)
				{
					#print "$key => $value<br>";
					#_debug_array($value);
					$this->t->set_var('profileID',$value['profileID']);
					$this->t->set_var('description',$value['description']);
					if(is_int($profileID) && $profileID == $value['profileID'])
					{
						$this->t->set_var('selected','selected');
					}
					else
					{
						$this->t->set_var('selected','');
					}
					$this->t->parse('select_options','select_option',True);
				}
			}

			$this->t->parse("out","main");
			print $this->t->get('out','main');

		}

		function viewMainScreen()
		{
			// get passed messages
			if (!empty($_GET["msg"])) $message[] = html::purify($_GET["msg"]);
			if (!empty($_GET["message"])) $message[] = html::purify($_GET["message"]);
			unset($_GET["msg"]);
			unset($_GET["message"]);
			#printf ("this->uifelamimail->viewMainScreen() start: %s<br>",date("H:i:s",mktime()));
			$bofilter		=& $this->bofilter;
			$uiwidgets		= CreateObject('felamimail.uiwidgets');
			// fetch the active account with prefs and identities
			$preferences	=& $this->preferences;
			$urlMailbox		=  urlencode($this->mailbox);
			//_debug_array($preferences->preferences);
			if (isset($GLOBALS['egw_info']['user']['preferences']['felamimail']['ActiveProfileID']))
				self::$icServerID = (int)$GLOBALS['egw_info']['user']['preferences']['felamimail']['ActiveProfileID'];
			//_debug_array(self::$icServerID);
			if (is_object($preferences)) $imapServer 	= $preferences->getIncomingServer(self::$icServerID);
			//_debug_array($imapServer);
			//_debug_array($preferences->preferences);
			//error_log(__METHOD__.__LINE__.' ImapServerId:'.$imapServer->ImapServerId.' Prefs:'.array2string($preferences->preferences));
			//error_log(__METHOD__.__LINE__.' ImapServerObject:'.array2string($imapServer));
			if (is_object($preferences)) $activeIdentity =& $preferences->getIdentity(self::$icServerID, true);
			//_debug_array($activeIdentity);
			$maxMessages	=  50;
			if (isset($GLOBALS['egw_info']['user']['preferences']['felamimail']['prefMailGridBehavior']) && (int)$GLOBALS['egw_info']['user']['preferences']['felamimail']['prefMailGridBehavior'] <> 0)
				$maxMessages = (int)$GLOBALS['egw_info']['user']['preferences']['felamimail']['prefMailGridBehavior'];
			$userPreferences	=&  $GLOBALS['egw_info']['user']['preferences']['felamimail'];

			// retrieve data for/from user defined accounts
			$selectedID = 0;
			if($this->preferences->userDefinedAccounts) $allAccountData = $this->bopreferences->getAllAccountData($this->preferences);
			if ($allAccountData) {
				foreach ($allAccountData as $tmpkey => $accountData)
				{
					$identity =& $accountData['identity'];
					$icServer =& $accountData['icServer'];
					//_debug_array($identity);
					//_debug_array($icServer);
					//error_log(__METHOD__.__LINE__.' Userdefined Profiles ImapServerId:'.$icServer->ImapServerId);
					if (empty($icServer->host)) continue;
					$identities[$identity->id]=$identity->realName.' '.$identity->organization.' <'.$identity->emailAddress.'>';
					if (!empty($identity->default)) $selectedID = $identity->id;
				}
			}

			if (empty($imapServer->host) && count($identities)==0 && $this->preferences->userDefinedAccounts)
			{
				// redirect to new personal account
				egw::redirect_link('/index.php',array('menuaction'=>'felamimail.uipreferences.editAccountData',
					'accountID'=>"new",
					'msg'	=> lang("There is no IMAP Server configured.")." - ".lang("Please configure access to an existing individual IMAP account."),
				));
			}
			$this->display_app_header();

			$this->t->set_file(array("body" => 'mainscreen.tpl'));
			$this->t->set_block('body','main');
			$this->t->set_block('body','status_row_tpl');
			$this->t->set_block('body','error_message');
			$this->t->set_block('body','quota_block');
			$this->t->set_block('body','subject_same_window');
			$this->t->set_block('body','subject_new_window');

			$this->translate();
			if (empty($imapServer->host) && count($identities)==0) {
				$errormessage = "<br>".lang("There is no IMAP Server configured.");
				if ($GLOBALS['egw_info']['user']['apps']['emailadmin']) {
					$errormessage .= "<br>".lang("Configure a valid IMAP Server in emailadmin for the profile you are using.");
				} else {
					$errormessage .= "<br>".lang('Please ask the administrator to correct the emailadmin IMAP Server Settings for you.');
				}
				if($this->preferences->userDefinedAccounts)
						$errormessage .= "<br>".lang('or configure an valid IMAP Server connection using the Manage Accounts/Identities preference in the Sidebox Menu.');

				$this->t->set_var('connection_error_message', $errormessage);
				$this->t->set_var('message', '&nbsp;');
				$this->t->parse('header_rows','error_message',True);

				$this->t->parse("out","main");
				print $this->t->get('out','main');
				echo $GLOBALS['egw']->framework->footer(false);
				exit;
			}
			$this->t->set_var('activeServerID',self::$icServerID);
			$this->t->set_var('activeFolder',$urlMailbox);
			$this->t->set_var('activeFolderB64',base64_encode($this->mailbox));
			$sentFolder = $this->bofelamimail->getSentFolder(false);
			$this->t->set_var('sentFolder',($sentFolder?$sentFolder:''));
			$this->t->set_var('sentFolderB64',($sentFolder?base64_encode($sentFolder):''));
			$draftFolder = $this->bofelamimail->getDraftFolder(false);
			$this->t->set_var('draftFolder',($draftFolder?$draftFolder:''));
			$this->t->set_var('draftFolderB64',($draftFolder?base64_encode($draftFolder):''));
			$templateFolder = $this->bofelamimail->getTemplateFolder(false);
			$this->t->set_var('templateFolder',($templateFolder?$templateFolder:''));
			$this->t->set_var('templateFolderB64',($templateFolder?base64_encode($templateFolder):''));
			$this->t->set_var('oldMailbox',$urlMailbox);
			$this->t->set_var('image_path',EGW_IMAGES);
			#printf ("this->uifelamimail->viewMainScreen() Line 272: %s<br>",date("H:i:s",mktime()));
			$linkData = array
			(
				'menuaction'    => 'felamimail.uifelamimail.viewMainScreen'
			);
			$refreshURL = $GLOBALS['egw']->link('/index.php',$linkData);
			$this->t->set_var('reloadView',$refreshURL);
			// display a warning if vacation notice is active
			if(is_a($imapServer,'defaultimap') && $imapServer->enableSieve) {
				$imapServer->retrieveRules($imapServer->scriptName);
				$vacation = $imapServer->getVacation($imapServer->scriptName);
				//_debug_array($vacation);
				//    [status] => can be: on, off, by_date
				//    [end_date] => 1247522400 (timestamp, use showdate for visualisation)
				//    [start_date] => 1247176800 (timestamp, use showdate for visualisation)
			}
			if(is_array($vacation) && ($vacation['status'] == 'on' || $vacation['status']=='by_date'))
			{
				$dtfrmt = $GLOBALS['egw_info']['user']['preferences']['common']['dateformat'];
				$this->t->set_var('vacation_warning',
					html::image('phpgwapi','dialog_warning',false,'style="vertical-align: middle; width: 16px;"').lang('Vacation notice is active').($vacation['status']=='by_date'? ' '.common::show_date($vacation['start_date'],$dtfrmt,true).'->'.common::show_date($vacation['end_date'],$dtfrmt,true):''));
			}
			else
			{
				$this->t->set_var('vacation_warning','&nbsp;');
			}
			//error_log(__METHOD__.__LINE__.'->'.$this->connectionStatus);
			// ui for the quotas
			if($this->connectionStatus !== false) {
				$quota = $this->bofelamimail->getQuotaRoot();
			} else {
				$quota['limit'] = 'NOT SET';
			}

			if($quota !== false && $quota['limit'] != 'NOT SET') {
				$quotaDisplay = $uiwidgets->quotaDisplay($quota['usage'], $quota['limit']);
				$this->t->set_var('quota_display', $quotaDisplay);
			} else {
				$this->t->set_var('quota_display','&nbsp;');
			}
			// navigation
			if ($maxMessages>0)
			{
				$navbarImages = array(
					'last'		=> array(
						'action'	=> "jumpEnd(); return false;",
						'tooltip'	=> '',
					),
					'right'			=> array(
						'action'	=> "skipForward(); return false;",
						'tooltip'	=> '',
					),
					'left'		=> array(
						'action'	=> "skipPrevious(); return false;",
						'tooltip'	=> '',
					),
					'first'			=> array(
						'action'	=> "jumpStart(); return false;",
						'tooltip'	=> '',
					),
				);
				$navbarButtons  = '';
				foreach($navbarImages as $buttonName => $buttonInfo) {
					$navbarButtons .= $uiwidgets->navbarButton($buttonName, $buttonInfo['action'], $buttonInfo['tooltip'],'right');
				}
			}
			else
			{
				$navbarButtons = '';
			}
			$this->t->set_var('navbarButtonsRight',$navbarButtons);
			$composeImage = $GLOBALS['egw']->common->image('phpgwapi','new');
			$this->t->set_var('composeBGImage',$composeImage);

			// set the images
			$listOfImages = array(
				'read_small',
				'unread_small',
				'unread_flagged_small',
				'read_flagged_small',
				'trash',
				'sm_envelope',
				'write_mail',
				'manage_filter',
				'msg_icon_sm',
				'mail_find',
				'new',
				'start_kde',
				'previous_kde',
				'next_kde',
				'finnish_kde',
				'ajax-loader',
			);

			foreach ($listOfImages as $image) {
				$this->t->set_var($image, $GLOBALS['egw']->common->image('felamimail', $image));
			}
			$this->t->set_var('img_clear_left', html::image('felamimail', 'clear_left', lang('clear search'), 'style="margin-left:5px; cursor: pointer;" onclick="fm_clearSearch()"'));
			// refresh settings
			$refreshTime = $userPreferences['refreshTime'];
			$this->t->set_var('refreshTime',$refreshTime*60*1000);
			// other settings
			$prefaskformove = intval($userPreferences['prefaskformove']) ? intval($userPreferences['prefaskformove']) : 0;
			$this->t->set_var('prefaskformove',$prefaskformove);
			$prefaskformultipleforward = intval($userPreferences['prefaskformultipleforward']) ? intval($userPreferences['prefaskformultipleforward']) : 0;
			$this->t->set_var('prefaskformultipleforward',$prefaskformultipleforward);
			#// set the url to open when refreshing
			#$linkData = array
			#(
			#	'menuaction'	=> 'felamimail.uifelamimail.viewMainScreen'
			#);
			#$this->t->set_var('refresh_url',$GLOBALS['egw']->link('/index.php',$linkData));

			// define the sort defaults
			$dateSort	= '0';
			$dateCSS	= 'text_small';
			$fromSort	= '3';
			$fromCSS	= 'text_small';
			$subjectSort	= '5';
			$subjectCSS	= 'text_small';
			$sizeSort	= '6';
			$sizeCSS	= 'text_small';

			// and no overwrite the defaults
			switch($this->sort)
			{
				// sort by date newest first
				case '0':
					$dateCSS	= 'text_small_bold';
					break;

				// sort by from z->a
				case '2':
					$fromCSS	= 'text_small_bold';
					break;
				// sort by from a->z
				case '3':
					$subjectCSS	= 'text_small_bold';
					break;
				// sort by size z->a
				case '6':
					$sizeCSS	= 'text_small_bold';
					break;
			}

			// sort by date
			$this->t->set_var('css_class_date', $dateCSS);

			// sort by from
			$this->t->set_var('css_class_from', $fromCSS);

			// sort by subject
			$this->t->set_var('css_class_subject', $subjectCSS);

			// sort by size
			$this->t->set_var('css_class_size', $sizeCSS);

			#_debug_array($this->bofelamimail->sessionData['messageFilter']);
			if(!empty($this->bofelamimail->sessionData['messageFilter']['string'])) {
				$this->t->set_var('quicksearch', $this->bofelamimail->sessionData['messageFilter']['string']);
			}

			$defaultSearchType = (isset($this->bofelamimail->sessionData['messageFilter']['type']) ? $this->bofelamimail->sessionData['messageFilter']['type'] : 'quick');
			$defaultSelectStatus = (isset($this->bofelamimail->sessionData['messageFilter']['status']) ? $this->bofelamimail->sessionData['messageFilter']['status'] : 'any');

			$searchTypes = array(
				'quick'		=> 'quicksearch',
				'subject'	=> 'subject',
				'body'		=> 'message',
				'from'		=> 'from',
				'to'		=> 'to',
				'cc'		=> 'cc',
			);
			$selectSearchType = html::select('searchType', $defaultSearchType, $searchTypes, false, "style='width:100%;' id='searchType' onchange='document.getElementById(\"quickSearch\").focus(); document.getElementById(\"quickSearch\").value=\"\" ;return false;'");
			$this->t->set_var('select_search', $selectSearchType);

			$statusTypes = array(
				'any'		=> 'any status',
				'flagged'	=> 'flagged',
				'unseen'	=> 'unread',
				'answered'	=> 'replied',
				'seen'		=> 'read',
				'deleted'	=> 'deleted',
			);
			$selectStatus = html::select('status', $defaultSelectStatus, $statusTypes, false, "style='width:100%;' onchange='javascript:quickSearch();' id='status'");
			$this->t->set_var('select_status', $selectStatus);

			if($this->connectionStatus === false) {
				$this->t->set_var('connection_error_message', lang($this->bofelamimail->getErrorMessage()));
				$this->t->set_var('message', '&nbsp;');
				$this->t->parse('header_rows','error_message',True);
			} else {
				$previewMessageId =($this->bofelamimail->sessionData['previewMessage']?$this->bofelamimail->sessionData['previewMessage']:0);
				if ($previewMessageId)
				{
					$headers = $this->bofelamimail->getHeaders($this->mailbox, $this->startMessage, $maxMessages, $this->sort, $this->sortReverse, $this->bofelamimail->sessionData['messageFilter'],($previewMessageId?$previewMessageId:null));
				}
				else
				{
					$headers = array('header'=>array(),'info'=>array());
				}
 				$headerCount = count($headers['header']);
				$folderStatus = $this->bofelamimail->getFolderStatus($this->mailbox);
				$headers['info']['total'] = $folderStatus['messages'];
				$headers['info']['first'] = $this->startMessage;
				$headers['info']['last'] = ($headers['info']['total']>$maxMessages?$maxMessages:$headers['info']['total']);

				//_debug_array($folderStatus);
 				// if there aren't any messages left (eg. after delete or move)
 				// adjust $this->startMessage
 				if ($maxMessages > 0 && $headerCount==0 && $this->startMessage > $maxMessages) {
 					$this->startMessage = $this->startMessage - $maxMessages;
				}

				$msg_icon_sm = $GLOBALS['egw']->common->image('felamimail','msg_icon_sm');
				// determine how to display the current folder: as sent folder (to address visible) or normal (from address visible)
				//$folderType = $this->bofelamimail->getFolderType($this->mailbox);

				//_debug_array($this->bofelamimail->sessionData['previewMessage']);
				$messageTable =	$uiwidgets->messageTable(
						$headers,
						$folderType,
						$this->mailbox,
						$userPreferences['message_newwindow'],
						$userPreferences['rowOrderStyle'],
						$previewMessageId);
				$this->t->set_var('header_rows', $messageTable);


				$firstMessage = $headers['info']['first'];
				$lastMessage = $headers['info']['last'];
				$totalMessage = $headers['info']['total'];
				$langTotal = lang("total");

				// set the select all/nothing link
				if($_GET["select_all"] == "select_all") {
					// link to unselect all messages
					$linkData = array
					(
						'menuaction'	=> 'felamimail.uifelamimail.viewMainScreen'
					);
					$selectLink = sprintf("<a class=\"body_link\" href=\"%s\">%s</a>",
								$GLOBALS['egw']->link('/index.php',$linkData),
								lang("Unselect All"));
					$this->t->set_var('change_folder_checked','');
					$this->t->set_var('move_message_checked','checked');
				} else {
					// link to select all messages
					$linkData = array
					(
						'select_all'	=> 'select_all',
						'menuaction'	=> 'felamimail.uifelamimail.viewMainScreen'
					);
					$selectLink = sprintf("<a class=\"body_link\" href=\"%s\">%s</a>",
								$GLOBALS['egw']->link('/index.php',$linkData),
								lang("Select all"));
					$this->t->set_var('change_folder_checked','checked');
					$this->t->set_var('move_message_checked','');
				}
				$this->t->set_var('select_all_link',$selectLink);
				$shortName='';
				//if ($folderStatus = $this->bofelamimail->getFolderStatus($this->mailbox)) $shortName =$folderStatus['shortDisplayName'];
				if ($folderStatus) $shortName =$folderStatus['shortDisplayName']; // already fetched folderStatus earlier.
				$addmessage = '';
				if ($message)  $addmessage = ' <font color="red">'.implode('; ',$message).'</font> ';
				$this->t->set_var('message','<b>'.$shortName.': </b>'.lang("Viewing messages").($maxMessages>0&&$lastMessage>0?" <b>$firstMessage</b> - <b>$lastMessage</b>":"")." ($totalMessage $langTotal)".$addmessage);
				if ($maxMessages>0)
				{
					if($firstMessage > 1) {
						$linkData = array
						(
							'menuaction'	=> 'felamimail.uifelamimail.viewMainScreen',
							'startMessage'	=> $this->startMessage - $maxMessages
						);
						$link = $GLOBALS['egw']->link('/index.php',$linkData);
						$this->t->set_var('link_previous',"<a class=\"body_link\" href=\"$link\">".lang("previous")."</a>");
					} else {
						$this->t->set_var('link_previous',lang("previous"));
					}

					if($totalMessage > $lastMessage) {
						$linkData = array (
							'menuaction'	=> 'felamimail.uifelamimail.viewMainScreen',
							'startMessage'	=> $this->startMessage + $maxMessages
						);
						$link = $GLOBALS['egw']->link('/index.php',$linkData);
						$this->t->set_var('link_next',"<a class=\"body_link\" href=\"$link\">".lang("next")."</a>");
					} else {
						$this->t->set_var('link_next',lang("next"));
					}
					$this->t->parse('status_row','status_row_tpl',True);
					//print __LINE__ . ': ' . (microtime(true) - $this->timeCounter) . '<br>';
				}
				else
				{
					$this->t->set_var('link_previous',lang("previous"));
					$this->t->set_var('link_next',lang("next"));
					$this->t->parse('status_row','status_row_tpl',True);
				}
			}

			//print __LINE__ . ': ' . (microtime(true) - $this->timeCounter) . '<br>';

			$this->t->parse("out","main");
			$neededSkript = "";
			if($this->connectionStatus !== false)
			{
				$neededSkript = "<div id='skriptGridOnFirstLoad' name='skriptGridOnFirstLoad'>".
									$uiwidgets->get_grid_js($folderType, $this->mailbox,$rowsFetched,$this->startMessage,false,($maxMessages>=0?false:true)).
								"</div>";
				$this->bofelamimail->closeConnection();
			}
			print $this->t->get('out','main').$neededSkript;
			echo $GLOBALS['egw']->framework->footer(false);
		}

		function array_merge_replace( $array, $newValues )
		{
			foreach ( $newValues as $key => $value )
			{
				if ( is_array( $value ) )
				{
					if ( !isset( $array[ $key ] ) )
					{
						$array[ $key ] = array();
					}
					$array[ $key ] = $this->array_merge_replace( $array[ $key ], $value );
				}
				else
				{
					if ( isset( $array[ $key ] ) && is_array( $array[ $key ] ) )
					{
						$array[ $key ][ 0 ] = $value;
					}
					else
					{
						if ( isset( $array ) && !is_array( $array ) )
						{
							$temp = $array;
							$array = array();
							$array[0] = $temp;
						}
						$array[ $key ] = $value;
					}
				}
			}
			return $array;
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

		function toggleFilter()
		{
			error_log(__METHOD__." called from:".function_backtrace());
			$this->bofelamimail->toggleFilter();
			$this->viewMainScreen();
		}

		function translate()
		{
			$this->t->set_var('th_bg',$GLOBALS['egw_info']["theme"]["th_bg"]);
			$this->t->set_var('bg_01',$GLOBALS['egw_info']["theme"]["bg01"]);
			$this->t->set_var('bg_02',$GLOBALS['egw_info']["theme"]["bg02"]);

			$this->t->set_var('lang_compose',lang('compose'));
			$this->t->set_var('lang_edit_filter',lang('edit filter'));
			$this->t->set_var('lang_move_selected_to',lang('move selected to'));
			$this->t->set_var('lang_doit',lang('do it!'));
			$this->t->set_var('lang_change_folder',lang('change folder'));
			$this->t->set_var('lang_move_message',lang('move messages'));
			$this->t->set_var('desc_read',lang("mark selected as read"));
			$this->t->set_var('desc_unread',lang("mark selected as unread"));
			$this->t->set_var('desc_important',lang("mark selected as flagged"));
			$this->t->set_var('desc_unimportant',lang("mark selected as unflagged"));
			$this->t->set_var('desc_deleted',lang("delete selected"));
			$this->t->set_var('lang_date',lang("date"));
			$this->t->set_var('lang_status',lang('status'));
			$this->t->set_var('lang_size',lang("size"));
			$this->t->set_var('lang_search',lang("search"));
			$this->t->set_var('lang_replied',lang("replied"));
			$this->t->set_var('lang_read',lang("read"));
			$this->t->set_var("lang_select",lang('select'));
			$this->t->set_var('lang_unread',lang("unread"));
			$this->t->set_var('lang_deleted',lang("deleted"));
			$this->t->set_var('lang_recent',lang("recent"));
			$this->t->set_var('lang_flagged',lang("flagged"));
			$this->t->set_var('lang_unflagged',lang("unflagged"));
			$this->t->set_var('lang_subject',lang("subject"));
			$this->t->set_var('lang_add_to_addressbook',lang("add to addressbook"));
			$this->t->set_var('lang_no_filter',lang("no filter"));
			$this->t->set_var('lang_connection_failed',lang("The connection to the IMAP Server failed!!"));
			$this->t->set_var('lang_select_target_folder',lang("Simply click the target-folder"));
			$this->t->set_var('lang_updating_message_status',lang("updating message status"));
			$this->t->set_var('lang_max_uploadsize',lang('max uploadsize'));
			$this->t->set_var('lang_loading',lang('loading'));
			$this->t->set_var('lang_deleting_messages',lang('deleting messages'));
			$this->t->set_var('lang_open_all',lang("open all"));
			$this->t->set_var('lang_close_all',lang("close all"));
			$this->t->set_var('lang_moving_messages_to',lang('moving messages to'));
			$this->t->set_var('lang_copying_messages_to',lang('copying messages to'));
			$this->t->set_var('lang_MoveCopyTitle',($GLOBALS['egw_info']['user']['preferences']['felamimail']['prefaskformove']==2?lang('Copy or Move Messages?'):lang('Move Messages?')));
			$this->t->set_var('lang_askformove',($GLOBALS['egw_info']['user']['preferences']['felamimail']['prefaskformove']==2?lang('Do you really want to move or copy the selected messages to folder:'):lang('Do you really want to move the selected messages to folder:')));
			$this->t->set_var('lang_move',lang("Move"));
			$this->t->set_var('lang_multipleforward',lang("Do you really want to attach the selected messages to the new mail?"));
			$this->t->set_var('lang_copy',lang("Copy"));
			$this->t->set_var('lang_cancel',lang("Cancel"));
			$this->t->set_var('lang_mark_all_messages',lang('all messages in folder'));
			$this->t->set_var('lang_confirm_all_messages',lang('The action will be applied to all messages of the current folder.\nDo you want to proceed?'));
			$this->t->set_var('lang_empty_trash',lang('empty trash'));
			$this->t->set_var('lang_compress_folder',lang('compress folder'));
			$this->t->set_var('lang_skipping_forward',lang('skipping forward'));
			$this->t->set_var('lang_skipping_previous',lang('skipping previous'));
			$this->t->set_var('lang_jumping_to_start',lang('jumping to start'));
			$this->t->set_var('lang_jumping_to_end',lang('jumping to end'));
			$this->t->set_var('lang_updating_view',lang('updating view'));
			$this->t->set_var('lang_toggleFS',lang('choose from VFS'));
			$this->t->set_var('lang_sendnotify',lang('The message sender has requested a response to indicate that you have read this message. Would you like to send a receipt?'));
		}
}
?>
