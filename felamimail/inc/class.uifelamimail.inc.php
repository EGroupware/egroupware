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
	* Free Software Foundation; version 2 of the License.                       *
	\***************************************************************************/
	/* $Id$ */

	class uifelamimail
	{
		var $public_functions = array
		(
			'addVcard'		=> True,
			'changeFilter'		=> True,
			'changeFolder'		=> True,
			'changeSorting'		=> True,
			'compressFolder'	=> True,
			'deleteMessage'		=> True,
			'handleButtons'		=> True,
			'hookAdmin'		=> True,
			'toggleFilter'		=> True,
			'viewMainScreen'	=> True
		);
		
		var $mailbox;		// the current folder in use
		var $startMessage;	// the first message to show
		var $sort;		// how to sort the messages
		var $moveNeeded;	// do we need to move some messages?

		function uifelamimail()
		{
			if(isset($GLOBALS['HTTP_POST_VARS']["mark_unread_x"])) 
				$GLOBALS['HTTP_POST_VARS']["mark_unread"] = "true";
			if(isset($GLOBALS['HTTP_POST_VARS']["mark_read_x"])) 
				$GLOBALS['HTTP_POST_VARS']["mark_read"] = "true";
			if(isset($GLOBALS['HTTP_POST_VARS']["mark_unflagged_x"])) 
				$GLOBALS['HTTP_POST_VARS']["mark_unflagged"] = "true";
			if(isset($GLOBALS['HTTP_POST_VARS']["mark_flagged_x"])) 
				$GLOBALS['HTTP_POST_VARS']["mark_flagged"] = "true";
			if(isset($GLOBALS['HTTP_POST_VARS']["mark_deleted_x"])) 
				$GLOBALS['HTTP_POST_VARS']["mark_deleted"] = "true";

			$this->displayCharset	= $GLOBALS['phpgw']->translation->charset();
			$this->bofelamimail     = CreateObject('felamimail.bofelamimail',$this->displayCharset);
			$this->bofilter		= CreateObject('felamimail.bofilter');
			$this->bopreferences	= CreateObject('felamimail.bopreferences');
			$this->preferences	= $this->bopreferences->getPreferences();
			$this->botranslation	= CreateObject('phpgwapi.translation');

			if(isset($GLOBALS['HTTP_POST_VARS']["mailbox"]) && 
				$GLOBALS['HTTP_GET_VARS']["menuaction"] == "felamimail.uifelamimail.handleButtons" &&
				empty($GLOBALS['HTTP_POST_VARS']["mark_unread"]) &&
				empty($GLOBALS['HTTP_POST_VARS']["mark_read"]) &&
				empty($GLOBALS['HTTP_POST_VARS']["mark_unflagged"]) &&
				empty($GLOBALS['HTTP_POST_VARS']["mark_flagged"]) &&
				empty($GLOBALS['HTTP_POST_VARS']["mark_deleted"]))
			{
				if ($GLOBALS['HTTP_POST_VARS']["folderAction"] == "changeFolder")
				{
					// change folder
					$this->bofelamimail->sessionData['mailbox']	= $GLOBALS['HTTP_POST_VARS']["mailbox"];
					$this->bofelamimail->sessionData['startMessage']= 1;
					$this->bofelamimail->sessionData['sort']	= $this->preferences['sortOrder'];
					$this->bofelamimail->sessionData['activeFilter']= -1;
				}
				elseif($GLOBALS['HTTP_POST_VARS']["folderAction"] == "moveMessage")
				{
					//print "move messages<br>";
					$this->bofelamimail->sessionData['mailbox'] 	= urldecode($GLOBALS['HTTP_POST_VARS']["oldMailbox"]);
					$this->bofelamimail->sessionData['startMessage']= 1;
					if (is_array($GLOBALS['HTTP_POST_VARS']["msg"]))
					{
						// we need to initialize the classes first
						$this->moveNeeded = "1";
					}
				}
			}
			elseif(isset($GLOBALS['HTTP_POST_VARS']["mailbox"]) &&
				$GLOBALS['HTTP_GET_VARS']["menuaction"] == "felamimail.uifelamimail.handleButtons" &&
				!empty($GLOBALS['HTTP_POST_VARS']["mark_deleted"]))
			{
				// delete messages
				$this->bofelamimail->sessionData['startMessage']= 1;
			}
			elseif($GLOBALS['HTTP_GET_VARS']["menuaction"] == "felamimail.uifelamimail.deleteMessage")
			{
				// delete 1 message from the mail reading window
				$this->bofelamimail->sessionData['startMessage']= 1;
			}
			elseif(isset($GLOBALS['HTTP_POST_VARS']["filter"]) || isset($GLOBALS['HTTP_GET_VARS']["filter"]))
			{
				// new search filter defined, lets start with message 1
				$this->bofelamimail->sessionData['startMessage']= 1;
			}

			// navigate for and back
			if(isset($GLOBALS['HTTP_GET_VARS']["startMessage"]))
			{
				$this->bofelamimail->sessionData['startMessage'] = $GLOBALS['HTTP_GET_VARS']["startMessage"];
			}
			
			$this->bofelamimail->saveSessionData();
			
			$this->mailbox 		= $this->bofelamimail->sessionData['mailbox'];
			$this->startMessage 	= $this->bofelamimail->sessionData['startMessage'];
			$this->sort 		= $this->bofelamimail->sessionData['sort'];
			#$this->filter 		= $this->bofelamimail->sessionData['activeFilter'];

			#$this->cats			= CreateObject('phpgwapi.categories');
			#$this->nextmatchs		= CreateObject('phpgwapi.nextmatchs');
			$this->t			= CreateObject('phpgwapi.Template',PHPGW_APP_TPL);
			#$this->grants[$this->account]	= PHPGW_ACL_READ + PHPGW_ACL_ADD + PHPGW_ACL_EDIT + PHPGW_ACL_DELETE;
			// this need to fixed
			// this does not belong to here
			if($_GET['menuaction'] != 'felamimail.uifelamimail.hookAdmin' &&
			   $_GET['menuaction'] != 'felamimail.uifelamimail.changeFolder')
			{
				$this->connectionStatus = $this->bofelamimail->openConnection();
			}

			$this->rowColor[0] = $GLOBALS['phpgw_info']["theme"]["row_on"];
			$this->rowColor[1] = $GLOBALS['phpgw_info']["theme"]["row_off"];

			$this->dataRowColor[0] = $GLOBALS['phpgw_info']["theme"]["bg01"];
			$this->dataRowColor[1] = $GLOBALS['phpgw_info']["theme"]["bg02"];
		}

		function addVcard()
		{
			$messageID 	= $GLOBALS['HTTP_GET_VARS']['messageID'];
			$partID 	= $GLOBALS['HTTP_GET_VARS']['partID'];
			$attachment = $this->bofelamimail->getAttachment($messageID,$partID);
			
			$tmpfname = tempnam ($GLOBALS['phpgw_info']['server']['temp_dir'], "phpgw_");
			$fp = fopen($tmpfname, "w");
			fwrite($fp, $attachment['attachment']);
			fclose($fp);
			
			$vcard = CreateObject('phpgwapi.vcard');
			$entry = $vcard->in_file($tmpfname);
			$entry['owner'] = $GLOBALS['phpgw_info']['user']['account_id'];
			$entry['access'] = 'private';
			$entry['tid'] = 'n';
			
			#_debug_array($entry);
			#print "<br><br>";
			
			print quoted_printable_decode($entry['fn'])."<br>";
			
			#$boaddressbook = CreateObject('addressbook.boaddressbook');
			#$soaddressbook = CreateObject('addressbook.soaddressbook');
			#$soaddressbook->add_entry($entry);
			#$ab_id = $boaddressbook->get_lastid();
			
			unlink($tmpfname);
			
			$GLOBALS['phpgw']->common->phpgw_exit();
		}
		
		function changeFilter()
		{
			if(isset($GLOBALS['HTTP_POST_VARS']["filter"]))
			{
				$data['quickSearch']	= $GLOBALS['HTTP_POST_VARS']["quickSearch"];
				$data['filter']		= $GLOBALS['HTTP_POST_VARS']["filter"];
				$this->bofilter->updateFilter($data);
			}
			elseif(isset($GLOBALS['HTTP_GET_VARS']["filter"]))
			{
				$data['filter']		= $GLOBALS['HTTP_GET_VARS']["filter"];
				$this->bofilter->updateFilter($data);
			}
			$this->viewMainScreen();
		}
		
		function changeFolder()
		{
			// change folder
			$this->bofelamimail->sessionData['mailbox']	= urldecode($_GET["mailbox"]);
			$this->bofelamimail->sessionData['startMessage']= 1;
			$this->bofelamimail->sessionData['sort']	= $this->preferences['sortOrder'];
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
			// change sorting
			if(isset($_GET["sort"]))
			{
				$this->bofelamimail->sessionData['sort']	= $_GET["sort"];
				$this->sort					= $_GET["sort"];
	
				$this->bofelamimail->saveSessionData();
			}
			
			$this->viewMainScreen();
		}

		function compressFolder()
		{
			$this->bofelamimail->compressFolder();
			$this->viewMainScreen();
		}

		function deleteMessage()
		{
			$preferences		= ExecMethod('felamimail.bopreferences.getPreferences');

			$message[] = $GLOBALS['HTTP_GET_VARS']["message"];
			
			$this->bofelamimail->deleteMessages($message);

			// set the url to open when refreshing
			$linkData = array
			(
				'menuaction'	=> 'felamimail.uifelamimail.viewMainScreen'
			);
			$refreshURL = $GLOBALS['phpgw']->link('/index.php',$linkData);

			if($preferences['messageNewWindow'])
			{
				print "<script type=\"text/javascript\">
				opener.location.href = '".$refreshURL."';
				window.close();</script>";
			}
			else
			{
				$this->viewMainScreen();
			}
		}
		
		function display_app_header()
		{
			if(!@is_object($GLOBALS['phpgw']->js))
			{
				$GLOBALS['phpgw']->js = CreateObject('phpgwapi.javascript');
			}
			$GLOBALS['phpgw']->js->validate_file('foldertree','foldertree');
			$GLOBALS['phpgw']->common->phpgw_header();
			echo parse_navbar();
		}
	
		function handleButtons()
		{
			if($this->moveNeeded == "1")
			{
				$this->bofelamimail->moveMessages($GLOBALS['HTTP_POST_VARS']["mailbox"],
									$GLOBALS['HTTP_POST_VARS']["msg"]);
			}
			
			elseif(!empty($GLOBALS['HTTP_POST_VARS']["mark_deleted"]) &&
				is_array($GLOBALS['HTTP_POST_VARS']["msg"]))
			{
				$this->bofelamimail->deleteMessages($GLOBALS['HTTP_POST_VARS']["msg"]);
			}
			
			elseif(!empty($GLOBALS['HTTP_POST_VARS']["mark_unread"]) &&
				is_array($GLOBALS['HTTP_POST_VARS']["msg"]))
			{
				$this->bofelamimail->flagMessages("unread",$GLOBALS['HTTP_POST_VARS']["msg"]);
			}
			
			elseif(!empty($GLOBALS['HTTP_POST_VARS']["mark_read"]) &&
				is_array($GLOBALS['HTTP_POST_VARS']["msg"]))
			{
				$this->bofelamimail->flagMessages("read",$GLOBALS['HTTP_POST_VARS']["msg"]);
			}
			
			elseif(!empty($GLOBALS['HTTP_POST_VARS']["mark_unflagged"]) &&
				is_array($GLOBALS['HTTP_POST_VARS']["msg"]))
			{
				$this->bofelamimail->flagMessages("unflagged",$GLOBALS['HTTP_POST_VARS']["msg"]);
			}
			
			elseif(!empty($GLOBALS['HTTP_POST_VARS']["mark_flagged"]) &&
				is_array($GLOBALS['HTTP_POST_VARS']["msg"]))
			{
				$this->bofelamimail->flagMessages("flagged",$GLOBALS['HTTP_POST_VARS']["msg"]);
			}
			

			$this->viewMainScreen();
		}

		function hookAdmin()
		{
			if(!$GLOBALS['phpgw']->acl->check('run',1,'admin'))
			{
				$GLOBALS['phpgw']->common->phpgw_header();
				echo parse_navbar();
				echo lang('access not permitted');
				$GLOBALS['phpgw']->log->message('F-Abort, Unauthorized access to felamimail.uifelamimail.hookAdmin');
				$GLOBALS['phpgw']->log->commit();
				$GLOBALS['phpgw']->common->phpgw_exit();
			}
			
			if(!empty($_POST['profileID']) && is_int(intval($_POST['profileID'])))
			{
				$profileID = intval($_POST['profileID']);
				$this->bofelamimail->setEMailProfile($profileID);
			}
			
			$boemailadmin = CreateObject('emailadmin.bo');
			
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
			$this->t->set_var('action_url',$GLOBALS['phpgw']->link('/index.php',$linkData));
			
			$linkData = array
			(
				'menuaction'	=> 'emailadmin.ui.listProfiles'
			);
			$this->t->set_var('emailadmin_url',$GLOBALS['phpgw']->link('/index.php',$linkData));
			
			$this->t->set_var('back_url',$GLOBALS['phpgw']->link('/admin/index.php'));
			
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
			#printf ("this->uifelamimail->viewMainScreen() start: %s<br>",date("H:i:s",mktime()));
			$bopreferences		= CreateObject('felamimail.bopreferences');
			$preferences		= $bopreferences->getPreferences();
			$bofilter		= CreateObject('felamimail.bofilter');
			$mailPreferences	= $bopreferences->getPreferences();

			$urlMailbox = urlencode($this->mailbox);
			
			$maxMessages = $GLOBALS['phpgw_info']["user"]["preferences"]["common"]["maxmatchs"];
			
		
			$this->display_app_header();
			
			$this->t->set_file(array("body" => 'mainscreen.tpl'));
			$this->t->set_block('body','main');
			$this->t->set_block('body','status_row_tpl');
			$this->t->set_block('body','header_row');
			$this->t->set_block('body','error_message');
			$this->t->set_block('body','quota_block');
			$this->t->set_block('body','subject_same_window');
			$this->t->set_block('body','subject_new_window');

			$this->translate();
			
			$this->t->set_var('oldMailbox',$urlMailbox);
			$this->t->set_var('image_path',PHPGW_IMAGES);
			#printf ("this->uifelamimail->viewMainScreen() Line 272: %s<br>",date("H:i:s",mktime()));
			// ui for the quotas
			if($quota = $this->bofelamimail->getQuotaRoot())
			{
				if($quota['limit'] == 0)
				{
					$quotaPercent=100;
				}
				else
				{
					$quotaPercent=round(($quota['usage']*100)/$quota['limit']);
				}
				$quotaLimit=$this->show_readable_size($quota['limit']*1024);
				$quotaUsage=$this->show_readable_size($quota['usage']*1024);

				$this->t->set_var('leftWidth',$quotaPercent);
				if($quotaPercent > 90)
				{
					$this->t->set_var('quotaBG','red');
				}
				elseif($quotaPercent > 80)
				{
					$this->t->set_var('quotaBG','yellow');
				}
				else
				{
					$this->t->set_var('quotaBG','#66ff66');
				}
				
				if($quotaPercent > 50)
				{
					$this->t->set_var('quotaUsage_right','&nbsp;');
					$this->t->set_var('quotaUsage_left',$quotaUsage .'/'.$quotaLimit);
				}
				else
				{
					$this->t->set_var('quotaUsage_left','&nbsp;');
					$this->t->set_var('quotaUsage_right',$quotaUsage .'/'.$quotaLimit);
				}
				
				$this->t->parse('quota_display','quota_block',True);
			}
			else
			{
				$this->t->set_var('quota_display','&nbsp;');
			}
			
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
				'new'
			);

			foreach ($listOfImages as $image) 
			{
				$this->t->set_var($image,$GLOBALS['phpgw']->common->image('felamimail',$image));
			}
			// refresh settings
			$refreshTime = $preferences['refreshTime'];
			if($refreshTime > 0)
			{
				$this->t->set_var('refreshTime',sprintf("aktiv = window.setTimeout( \"refresh()\", %s );",$refreshTime*60*1000));
			}
			else
			{
				$this->t->set_var('refreshTime','');
			}
			// set the url to open when refreshing
			$linkData = array
			(
				'menuaction'	=> 'felamimail.uifelamimail.viewMainScreen'
			);
			$this->t->set_var('refresh_url',$GLOBALS['phpgw']->link('/index.php',$linkData));
			
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
					$dateSort	= '1';
					$dateCSS	= 'text_small_bold';
					break;
				// sort by date oldest first
				case '1':
					$dateSort	= '0';
					$dateCSS	= 'text_small_bold';
					break;

				// sort by from z->a
				case '2':
					$fromSort	= '3';
					$fromCSS	= 'text_small_bold';
					break;
				// sort by from a->z
				case '3':
					$fromSort	= '2';
					$fromCSS	= 'text_small_bold';
					break;

				// sort by subject z->a
				case '4':
					$subjectSort	= '5';
					$subjectCSS	= 'text_small_bold';
					break;
				// sort by subject a->z
				case '5':
					$subjectSort	= '4';
					$subjectCSS	= 'text_small_bold';
					break;

				// sort by size z->a
				case '6':
					$sizeSort	= '7';
					$sizeCSS	= 'text_small_bold';
					break;
				// sort by subject a->z
				case '7':
					$sizeSort	= '6';
					$sizeCSS	= 'text_small_bold';
					break;
			}

			// sort by date
			$linkData = array
			(
				'menuaction'	=> 'felamimail.uifelamimail.changeSorting',
				'startMessage'	=> 1,
				'sort'		=> $dateSort
			);
			$this->t->set_var('url_sort_date',$GLOBALS['phpgw']->link('/index.php',$linkData));
			$this->t->set_var('css_class_date',$dateCSS);
		
			// sort by from
			$linkData = array
			(
				'menuaction'	=> 'felamimail.uifelamimail.changeSorting',
				'startMessage'	=> 1,
				'sort'		=> $fromSort
			);
			$this->t->set_var('url_sort_from',$GLOBALS['phpgw']->link('/index.php',$linkData));
			$this->t->set_var('css_class_from',$fromCSS);
		
			// sort by subject
			$linkData = array
			(
				'menuaction'	=> 'felamimail.uifelamimail.changeSorting',
				'startMessage'	=> 1,
				'sort'		=> $subjectSort
			);
			$this->t->set_var('url_sort_subject',$GLOBALS['phpgw']->link('/index.php',$linkData));
			$this->t->set_var('css_class_subject',$subjectCSS);
			
			// sort by size
			$linkData = array
			(
				'menuaction'	=> 'felamimail.uifelamimail.changeSorting',
				'startMessage'	=> 1,
				'sort'		=> $sizeSort
			);
			$this->t->set_var('url_sort_size',$GLOBALS['phpgw']->link('/index.php',$linkData));
			$this->t->set_var('css_class_size',$sizeCSS);
			
			// create the filter ui
			$filterList = $bofilter->getFilterList();
			$activeFilter = $bofilter->getActiveFilter();
			// -1 == no filter selected
			if($activeFilter == -1)
				$filterUI .= "<option value=\"-1\" selected>".lang('no filter')."</option>";
			else
				$filterUI .= "<option value=\"-1\">".lang('no filter')."</option>";
			while(list($key,$value) = @each($filterList))
			{
				$selected="";
				if($activeFilter == $key) $selected="selected";
				$filterUI .= "<option value=".$key." $selected>".$value['filterName']."</option>";
			}
			$this->t->set_var('filter_options',$filterUI);
			// 0 == quicksearch
			if($activeFilter == '0')
				$this->t->set_var('quicksearch',$filterList[0]['subject']);
			
			if($this->connectionStatus != 'True')
			{
				$this->t->set_var('message',$this->connectionStatus);
				$this->t->parse('header_rows','error_message',True);
			}
			else
			{
				$folders = $this->bofelamimail->getFolderList('true');
			
				$headers = $this->bofelamimail->getHeaders($this->startMessage, $maxMessages, $this->sort);
			
				
				$headerCount = count($headers['header']);
				
				if ($mailPreferences['sent_folder'] == $this->mailbox)
				{
					$this->t->set_var('lang_from',lang("to"));
				}
				else
				{
					$this->t->set_var('lang_from',lang("from"));
				}
				$msg_icon_sm = $GLOBALS['phpgw']->common->image('felamimail','msg_icon_sm');
				for($i=0; $i<$headerCount; $i++)
				{
					// create the listing of subjects
					$maxSubjectLength = 60;
					$maxAddressLength = 20;
					$maxSubjectLengthBold = 50;
					$maxAddressLengthBold = 14;
					
					$flags = "";
					if(!empty($headers['header'][$i]['recent'])) $flags .= "R";
					if(!empty($headers['header'][$i]['flagged'])) $flags .= "F";
					if(!empty($headers['header'][$i]['answered'])) $flags .= "A";
					if(!empty($headers['header'][$i]['deleted'])) $flags .= "D";
					if(!empty($headers['header'][$i]['seen'])) $flags .= "S";

					switch($flags)
					{
						case "":
							$this->t->set_var('imageName','unread_small.png');
							$this->t->set_var('row_text',lang('new'));
							$maxAddressLength = $maxAddressLengthBold;
							$maxSubjectLength = $maxSubjectLengthBold;
							break;
						case "D":
						case "DS":
						case "ADS":
							$this->t->set_var('imageName','unread_small.png');
							$this->t->set_var('row_text',lang('deleted'));
							break;
						case "F":
							$this->t->set_var('imageName','unread_flagged_small.png');
							$this->t->set_var('row_text',lang('new'));
							$maxAddressLength = $maxAddressLengthBold;
							break;
						case "FS":
							$this->t->set_var('imageName','read_flagged_small.png');
							$this->t->set_var('row_text',lang('replied'));
							break;
						case "FAS":
							$this->t->set_var('imageName','read_answered_flagged_small.png');
							$this->t->set_var('row_text',lang('replied'));
							break;
						case "S":
						case "RS":
							$this->t->set_var('imageName','read_small.png');
							$this->t->set_var('row_text',lang('read'));
							break;
						case "R":
							$this->t->set_var('imageName','recent_small.gif');
							$this->t->set_var('row_text','*'.lang('recent').'*');
							$maxAddressLength = $maxAddressLengthBold;
							break;
						case "RAS":
						case "AS":
							$this->t->set_var('imageName','read_answered_small.png');
							$this->t->set_var('row_text',lang('replied'));
							#$maxAddressLength = $maxAddressLengthBold;
							break;
						default:
							$this->t->set_var('row_text',$flags);
							break;
					}
					#_debug_array($GLOBALS[phpgw_info]);
					if (!empty($headers['header'][$i]['subject']))
					{
						// make the subject shorter if it is to long
						$fullSubject = $headers['header'][$i]['subject'];
						#if(strlen($headers['header'][$i]['subject']) > $maxSubjectLength)
						#{
						#	$headers['header'][$i]['subject'] = substr($headers['header'][$i]['subject'],0,$maxSubjectLength)."...";
						#}
						$headers['header'][$i]['subject'] = @htmlspecialchars($headers['header'][$i]['subject'],ENT_QUOTES,$this->displayCharset);
						if($headers['header'][$i]['attachments'] == "true")
						{
							$image = '<img src="'.$GLOBALS['phpgw']->common->image('felamimail','attach').'" border="0">';
//modified NDEE 29-12-03 for 
//separate attachment icon
							//$headers['header'][$i]['subject'] = "$image&nbsp;".$headers['header'][$i]['subject'];
							$headers['header'][$i]['attachment'] = $image;
						}
						$this->t->set_var('header_subject', $headers['header'][$i]['subject']);
// added
						$this->t->set_var('attachments', $headers['header'][$i]['attachment']);
						$this->t->set_var('full_subject',@htmlspecialchars($fullSubject,ENT_QUOTES,$this->displayCharset));
					}
					else
					{
						$this->t->set_var('header_subject',@htmlentities("(".lang('no subject').")",ENT_QUOTES,$this->displayCharset));
					}
				
					if ($mailPreferences['sent_folder'] == $this->mailbox)
					{
						if (!empty($headers['header'][$i]['to_name']))
						{
							$sender_name	= $headers['header'][$i]['to_name'];
							$full_address	=
								$headers['header'][$i]['to_name'].
								" <".
								$headers['header'][$i]['to_address'].
								">";
						}
						else
						{
							$sender_name	= $headers['header'][$i]['to_address'];
							$full_address	= $headers['header'][$i]['to_address'];
						}
						#$this->t->set_var('lang_from',lang("to"));
					}
					else
					{
						if (!empty($headers['header'][$i]['sender_name']))
						{
							$sender_name	= $headers['header'][$i]['sender_name'];
							$full_address	= @htmlentities(
								$headers['header'][$i]['sender_name'].
								" <".
								$headers['header'][$i]['sender_address'].
								">",ENT_QUOTES,$this->displayCharset);
						}
						else
						{
							$sender_name	= $headers['header'][$i]['sender_address'];
							$full_address	= $headers['header'][$i]['sender_address'];
						}
						#$this->t->set_var('lang_from',lang("from"));
					}
					#if(strlen($sender_name) > $maxAddressLength)
					#{
					#	$sender_name = substr($sender_name,0,$maxAddressLength)."...";
					#}
					$this->t->set_var('sender_name',@htmlentities($sender_name,
											 ENT_QUOTES,$this->displayCharset));
					$this->t->set_var('full_address',$full_address);
				
					if($GLOBALS['HTTP_GET_VARS']["select_all"] == "select_all")
					{
						$this->t->set_var('row_selected',"checked");
					}

					$this->t->set_var('message_counter',$i);
					$this->t->set_var('message_uid',$headers['header'][$i]['uid']);
// HINT: date style should be set according to preferences!
					$this->t->set_var('date',$headers['header'][$i]['date']);
					$this->t->set_var('size',$this->show_readable_size($headers['header'][$i]['size']));

					$linkData = array
					(
						'menuaction'    => 'felamimail.uidisplay.display',
						'showHeader'	=> 'false',
						'uid'		=> $headers['header'][$i]['uid']
					);
					if($preferences['messageNewWindow'])
					{
						$this->t->set_var('url_read_message',"javascript:displayMessage('".$GLOBALS['phpgw']->link('/index.php',$linkData)."');");
					}
					else
					{
						$this->t->set_var('url_read_message',$GLOBALS['phpgw']->link('/index.php',$linkData));
					}
				
					if(!empty($headers['header'][$i]['sender_name']))
					{
						list($mailbox, $host) = explode('@',$headers['header'][$i]['sender_address']);
						$senderAddress  = imap_rfc822_write_address($mailbox,
									$host,
									$headers['header'][$i]['sender_name']);
						$linkData = array
						(
							'menuaction'    => 'felamimail.uicompose.compose',
							'send_to'	=> base64_encode($senderAddress)
						);
					}
					else
					{
						$linkData = array
						(
							'menuaction'    => 'felamimail.uicompose.compose',
							'send_to'	=> base64_encode($headers['header'][$i]['sender_address'])
						);
					}
					if($preferences['messageNewWindow'])
					{
						$this->t->set_var('url_compose',"javascript:displayMessage('".$GLOBALS['phpgw']->link('/index.php',$linkData)."');");
					}
					else
					{
						$this->t->set_var('url_compose',$GLOBALS['phpgw']->link('/index.php',$linkData));
					}
					
					$linkData = array
					(
						'menuaction'    => 'addressbook.uiaddressbook.add_email',
						'add_email'	=> urlencode($headers['header'][$i]['sender_address']),
						'name'		=> urlencode($headers['header'][$i]['sender_name']),
						'referer'	=> urlencode($_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING'])
					);
					$this->t->set_var('url_add_to_addressbook',$GLOBALS['phpgw']->link('/index.php',$linkData));
					$this->t->set_var('msg_icon_sm',$msg_icon_sm);
					
					$this->t->set_var('phpgw_images',PHPGW_IMAGES);
					$this->t->set_var('row_css_class','header_row_'.$flags);
			
					$this->t->parse('header_rows','header_row',True);
				}
				$firstMessage = $headers['info']['first'];
				$lastMessage = $headers['info']['last'];
				$totalMessage = $headers['info']['total'];
				$langTotal = lang("total");		
			}
			
			$this->t->set_var('maxMessages',$i);
			if($GLOBALS['HTTP_GET_VARS']["select_all"] == "select_all")
			{
				$this->t->set_var('checkedCounter',$i);
			}
			else
			{
				$this->t->set_var('checkedCounter','0');
			}
			
			// set the select all/nothing link
			if($GLOBALS['HTTP_GET_VARS']["select_all"] == "select_all")
			{
				// link to unselect all messages
				$linkData = array
				(
					'menuaction'	=> 'felamimail.uifelamimail.viewMainScreen'
				);
				$selectLink = sprintf("<a class=\"body_link\" href=\"%s\">%s</a>",
							$GLOBALS['phpgw']->link('/index.php',$linkData),
							lang("Unselect All"));
				$this->t->set_var('change_folder_checked','');
				$this->t->set_var('move_message_checked','checked');
			}
			else
			{
				// link to select all messages
				$linkData = array
				(
					'select_all'	=> 'select_all',
					'menuaction'	=> 'felamimail.uifelamimail.viewMainScreen'
				);
				$selectLink = sprintf("<a class=\"body_link\" href=\"%s\">%s</a>",
							$GLOBALS['phpgw']->link('/index.php',$linkData),
							lang("Select all"));
				$this->t->set_var('change_folder_checked','checked');
				$this->t->set_var('move_message_checked','');
			}
			$this->t->set_var('select_all_link',$selectLink);
			

			// create the links for the delete options
			// "delete all" in the trash folder
			// "compress folder" in normal folders
			if ($mailPreferences['trash_folder'] == $this->mailbox &&
			    $mailPreferences['deleteOptions'] == "move_to_trash")
			{
				$linkData = array
				(
					'menuaction'	=> 'felamimail.uifelamimail.compressFolder'
				);
				$trashLink = sprintf("<a class=\"body_link\" href=\"%s\">%s</a>",
							$GLOBALS['phpgw']->link('/index.php',$linkData),
							lang("delete all"));
				
				$this->t->set_var('trash_link',$trashLink);
			}
			elseif($mailPreferences['deleteOptions'] == "mark_as_deleted")
			{
				$linkData = array
				(
					'menuaction'	=> 'felamimail.uifelamimail.compressFolder'
				);
				$trashLink = sprintf("<a class=\"body_link\" href=\"%s\">%s</a>",
							$GLOBALS['phpgw']->link('/index.php',$linkData),
							lang("compress folder"));
				$this->t->set_var('trash_link',$trashLink);
			}
			
			
			$this->t->set_var('message',lang("Viewing messages")." <b>$firstMessage</b> - <b>$lastMessage</b> ($totalMessage $langTotal)");
			if($firstMessage > 1)
			{
				$linkData = array
				(
					'menuaction'	=> 'felamimail.uifelamimail.viewMainScreen',
					'startMessage'	=> $this->startMessage - $maxMessages
				);
				$link = $GLOBALS['phpgw']->link('/index.php',$linkData);
				$this->t->set_var('link_previous',"<a class=\"body_link\" href=\"$link\">".lang("previous")."</a>");
			}
			else
			{
				$this->t->set_var('link_previous',lang("previous"));
			}
			
			if($totalMessage > $lastMessage)
			{
				$linkData = array
				(
					'menuaction'	=> 'felamimail.uifelamimail.viewMainScreen',
					'startMessage'	=> $this->startMessage + $maxMessages
				);
				$link = $GLOBALS['phpgw']->link('/index.php',$linkData);
				$this->t->set_var('link_next',"<a class=\"body_link\" href=\"$link\">".lang("next")."</a>");
			}
			else
			{
				$this->t->set_var('link_next',lang("next"));
			}
			$this->t->parse('status_row','status_row_tpl',True);
			
			@reset($folders);
			
// Start of the new folder tree system
// 29-12-2003 NDEE
// ToDo
// check how many mails in folder
// different style of parsing folders into file
// open to active folder on reload

			$folderImageDir = substr($GLOBALS['phpgw']->common->image('phpgwapi','foldertree_line.gif'),0,-19);
			
			// careful! "d = new..." MUST be on a new line!!!
			$folder_tree_new = "<script type='text/javascript'>d = new dTree('d','".$folderImageDir."');d.config.inOrder=true;d.config.closeSameLevel=true;";
			
			$allFolders = array();

			// create a list of all folders, also the ones which are not subscribed
			if (isset($folders) && is_array($folders))
			{
 			   foreach($folders as $key => $value)
			   {
				$folderParts = explode('.',$key);
				$partCount = count($folderParts);
				$string = '';
				for($i = 0; $i < $partCount; $i++)
				{
					if(!empty($string)) $string .= '.';
					$string .= $folderParts[$i];
					$allFolders[$string] = $folderParts[$i];
				}
			    }
			}

			// keep track of the last parent id
			$parentStack = array();
			$counter = 0;
			$folder_name = 'IMAP Server';
			$folder_title = $mailPreferences['username'].'@'.$mailPreferences['imapServerAddress'];
			$folder_icon = $folderImageDir."foldertree_base.gif";
			// and put the current counter on top
			array_push($parentStack, 0);
			$parent = -1;
			#$folder_tree_new .= "d.add('0','-1','$folder_name','#','','','$folder_title','','$folder_icon');";
			$folder_tree_new .= "d.add(0,-1,'$folder_name','javascript:void(0);','','','$folder_title');";
			$counter++;
			
			foreach($allFolders as $key => $value)
			{
				$countedDots = substr_count($key,".");
				#print "$value => $counted_dots<br>";
				

				// hihglight currently selected mailbox
				if ($this->mailbox == $key)
				{
					$folder_name = "<font style=\"background-color: #dddddd\">$value</font>";
					$openTo = $counter;
				}
				else
				{
					$folder_name = $value;
				}

				$folder_title = $value;
				if ($key == 'INBOX')
				{
					$folder_icon = $folderImageDir."foldertree_felamimail_sm.png";
					$folderOpen_icon = $folderImageDir."foldertree_felamimail_sm.png";
				}
				else
				{
					$folder_icon = $folderImageDir."foldertree_folder.gif";
					$folderOpen_icon = '';
				}

				// we are on the same level
				if($countedDots == count($parentStack) -1)
				{
					// remove the last entry
					array_pop($parentStack);
					// get the parent
					$parent = end($parentStack);
					// and put the current counter on top
					array_push($parentStack, $counter);
				}
				// we go one level deeper
				elseif($countedDots > count($parentStack) -1)
				{
					// get the parent
					$parent = end($parentStack);
					array_push($parentStack, $counter);
				}
				// we go some levels up
				elseif($countedDots < count($parentStack))
				{
					$stackCounter = count($parentStack);
					while(count($parentStack) > $countedDots)
					{
						array_pop($parentStack);
					}
					$parent = end($parentStack);
					// and put the current counter on top
					array_push($parentStack, $counter);
				}

				// some special handling for the root icon
				// the first icon requires $parent to be -1
				#if($counter==0)
				#{
				#	$parent = -1;
				#	$folder_icon = $folderImageDir."/foldertree_felamimail_sm.png";
				#}
				if($parent == '')
					$parent = 0;
				
				// Node(id, pid, name, url, urlClick, urlOut, title, target, icon, iconOpen, open) {
				$folder_tree_new .= "d.add($counter,$parent,'$folder_name','#','document.messageList.mailbox.value=\'$key\'; document.messageList.submit();','','$folder_title $key','','$folder_icon','$folderOpen_icon');\n";
				$counter++;
			}

			$folder_tree_new.= "document.write(d);
			d.openTo('$openTo','true');
			</script>";

			$this->t->set_var('current_mailbox',$current_mailbox);
			$this->t->set_var('folder_tree',$folder_tree_new);
			$this->t->set_var('foldertree_image_path',PHPGW_IMAGES_DIR.'/foldertree/');
			
// Finish of the new folder tree system			

			$this->t->set_var('options_folder',$options_folder);
			
			$linkData = array
			(
				'menuaction'    => 'felamimail.uicompose.compose'
			);
			if($preferences['messageNewWindow'])
			{
				$this->t->set_var('url_compose_empty',"javascript:displayMessage('".$GLOBALS['phpgw']->link('/index.php',$linkData)."');");
			}
			else
			{
				$this->t->set_var('url_compose_empty',$GLOBALS['phpgw']->link('/index.php',$linkData));
			}


			$linkData = array
			(
				'menuaction'    => 'felamimail.uifilter.mainScreen'
			);
			$this->t->set_var('url_filter',$GLOBALS['phpgw']->link('/index.php',$linkData));

			$linkData = array
			(
				'menuaction'    => 'felamimail.uifelamimail.handleButtons'
			);
			$this->t->set_var('url_change_folder',$GLOBALS['phpgw']->link('/index.php',$linkData));

			$linkData = array
			(
				'menuaction'    => 'felamimail.uifelamimail.changeFilter'
			);
			$this->t->set_var('url_search_settings',$GLOBALS['phpgw']->link('/index.php',$linkData));

			$this->t->set_var('lang_mark_messages_as',lang('mark messages as'));
			$this->t->set_var('lang_delete',lang('delete'));
			                                                                                                                                                                        
			$this->t->parse("out","main");
			print $this->t->get('out','main');
			
			if($this->connectionStatus == 'True')
			{
				$this->bofelamimail->closeConnection();
			}
			$GLOBALS['phpgw']->common->phpgw_footer();
			
		}

function array_merge_replace( $array, $newValues ) {
   foreach ( $newValues as $key => $value ) {
       if ( is_array( $value ) ) {
               if ( !isset( $array[ $key ] ) ) {
               $array[ $key ] = array();
           }
           $array[ $key ] = $this->array_merge_replace( $array[ $key ], $value );
       } else {
           if ( isset( $array[ $key ] ) && is_array( $array[ $key ] ) ) {
               $array[ $key ][ 0 ] = $value;
           } else {
               if ( isset( $array ) && !is_array( $array ) ) {
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
			$this->bofelamimail->toggleFilter();
			$this->viewMainScreen();
		}

		function translate()
		{
			$this->t->set_var('th_bg',$GLOBALS['phpgw_info']["theme"]["th_bg"]);
			$this->t->set_var('bg_01',$GLOBALS['phpgw_info']["theme"]["bg01"]);
			$this->t->set_var('bg_02',$GLOBALS['phpgw_info']["theme"]["bg02"]);

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
			$this->t->set_var('lang_size',lang("size"));
			$this->t->set_var('lang_quicksearch',lang("Quicksearch"));
			$this->t->set_var('lang_replied',lang("replied"));
			$this->t->set_var('lang_read',lang("read"));
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
			$this->t->set_var('lang_open_all',lang("open all"));
			$this->t->set_var('lang_close_all',lang("close all"));
		}
	}
?>
