<?php
	/***************************************************************************\
	* EGroupWare - FeLaMiMail                                                   *
	* http://www.linux-at-work.de                                               *
	* http://www.phpgw.de                                                       *
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
			$template =& CreateObject('phpgwapi.Template',EGW_APP_TPL);
			$this->template = $template;
			$this->template->set_file(array("body" => 'uiwidgets.tpl'));
		}

		/**
		* create a folder tree
		*
		* this function will create a foldertree based on javascript
		* on click the sorounding form gets submitted
		*
		* @param _folders array containing the list of folders
		* @param _selected string containing the selected folder
		* @param _topFolderName string containing the top folder name
		* @param _topFolderDescription string containing the description for the top folder
		* @param _formName string name of the sorounding form
		* @param _hiddenVar string hidden form value, transports the selected folder
		*
		* @return string the html code, to be added into the template
		*/
		function createHTMLFolder($_folders, $_selected, $_topFolderName, $_topFolderDescription, $_divName, $_displayCheckBox)
		{
			$allFolders = array();
			
			// create a list of all folders, also the ones which are not subscribed
 			foreach($_folders as $key => $obj)
			{
				$folderParts = explode($obj->delimiter,$key);
				if(is_array($folderParts))
				{
					$partCount = count($folderParts);
					$string = '';
					for($i = 0; $i < $partCount-1; $i++)
					{
						if(!empty($string)) $string .= $obj->delimiter;
						$string .= $folderParts[$i];
						if(!$allFolders[$string])
						{	
							$allFolders[$string] = $obj;
							unset($allFolders[$string]->name);
							unset($allFolders[$string]->attributes);
							unset($allFolders[$string]->counter);
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
			if($_displayCheckBox)
			{
				$folder_tree_new .= "tree.enableCheckBoxes(1);";
				$folder_tree_new .= "tree.setOnCheckHandler('onCheckHandler');";
			}
			
			$folder_tree_new .= "tree.insertNewItem(0,'--topfolder--','$_topFolderName',onNodeSelect,'thunderbird.png','thunderbird.png','thunderbird.png','CHILD,TOP');\n";
			
			#foreach($_folders as $key => $obj)
			foreach($allFolders as $longName => $obj)
			{	
				$image1 = "'folderClosed.gif'";
				$image2 = "0";
				$image3 = "0";
				
				$folderParts = explode($obj->delimiter, $longName);
				
				//get rightmost folderpart
				$shortName = array_pop($folderParts);
				
				// the rest of the array is the name of the parent
				$parentName = implode((array)$folderParts,$obj->delimiter);
				if(empty($parentName)) $parentName = '--topfolder--';
				
 				if( @$obj->counter->unseen > 0 )
				{
 					$messageCount = "&nbsp;(".$obj->counter->unseen.")";
				}
 				else
 				{
					$messageCount = "";
				}

				$entryOptions = 'CHILD,CHECKED';

				// highlight currently selected mailbox
				if ($_selected == $longName)
				{
					$entryOptions .= ',SELECT';
				}
				
				$folder_name = $shortName.$messageCount;
				
				// give INBOX a special foldericon
				if ($longName == 'INBOX')
				{
					$image1 = "'kfm_home.png'";
					$image2 = "'kfm_home.png'";
					$image3 = "'kfm_home.png'";
				}

				$folder_tree_new .= "tree.insertNewItem('$parentName','$longName','$folder_name',onNodeSelect,$image1,$image2,$image3,'$entryOptions');\n";
				if($_displayCheckBox)
					$folder_tree_new .= "tree.setCheck('$longName','".(int)$obj->subscribed."');";
			}

			$folder_tree_new.= "tree.closeAllItems(0);tree.openItem('$_selected');</script>";
			
			return $folder_tree_new;
		}

		function messageTable($_headers, $_isSentFolder, $_readInNewWindow)
		{
			$this->t =& CreateObject('phpgwapi.Template',EGW_APP_TPL);
			$this->t->set_file(array("body" => 'mainscreen.tpl'));
			$this->t->set_block('body','header_row');
			$this->t->set_block('body','message_table');

			foreach((array)$_headers['header'] as $header)
			{
				// create the listing of subjects
				$maxSubjectLength = 60;
				$maxAddressLength = 20;
				$maxSubjectLengthBold = 50;
				$maxAddressLengthBold = 14;
					
				$flags = "";
				if(!empty($header['recent'])) $flags .= "R";
				if(!empty($header['flagged'])) $flags .= "F";
				if(!empty($header['answered'])) $flags .= "A";
				if(!empty($header['deleted'])) $flags .= "D";
				if(!empty($header['seen'])) $flags .= "S";

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
				if (!empty($header['subject']))
				{
					// filter out undisplayable characters
					$search = array('[\016]','[\017]',
						'[\020]','[\021]','[\022]','[\023]','[\024]','[\025]','[\026]','[\027]',
						'[\030]','[\031]','[\032]','[\033]','[\034]','[\035]','[\036]','[\037]');
					$replace = '';
			
					$header['subject'] = preg_replace($search,$replace,$header['subject']);

					// make the subject shorter if it is to long
					$fullSubject = $header['subject'];
					#if(strlen($header['subject']) > $maxSubjectLength)
					#{
					#	$header['subject'] = substr($header['subject'],0,$maxSubjectLength)."...";
					#}
					$header['subject'] = @htmlspecialchars($header['subject'],ENT_QUOTES,$this->displayCharset);
					if($header['attachments'] == "true")
					{
						$image = '<img src="'.$GLOBALS['egw']->common->image('felamimail','attach').'" border="0" style="width:12px;">';

						$header['attachment'] = $image;
					}
					$this->t->set_var('header_subject', $header['subject']);
					$this->t->set_var('attachments', $header['attachment']);
					$this->t->set_var('full_subject',@htmlspecialchars($fullSubject,ENT_QUOTES,$this->displayCharset));
				}
				else
				{
					$this->t->set_var('header_subject',@htmlentities("(".lang('no subject').")",ENT_QUOTES,$this->displayCharset));
				}
			
				if ($_isSentFolder)
				{
					if (!empty($header['to_name']))
					{
						$sender_name	= $header['to_name'];
						$full_address	= $header['to_name'].' <'.$header['to_address'].'>';
					}
					else
					{
						$sender_name	= $header['to_address'];
						$full_address	= $header['to_address'];
					}
					#$this->t->set_var('lang_from',lang("to"));
				}
				else
				{
					if (!empty($header['sender_name']))
					{
						$sender_name	= $header['sender_name'];
						$full_address	= $header['sender_name'].' <'.$header['sender_address'].'>';
					}
					else
					{
						$sender_name	= $header['sender_address'];
						$full_address	= $header['sender_address'];
					}
					#$this->t->set_var('lang_from',lang("from"));
				}
				#if(strlen($sender_name) > $maxAddressLength)
				#{
				#	$sender_name = substr($sender_name,0,$maxAddressLength)."...";
				#}
				$this->t->set_var('sender_name',$sender_name);
				$this->t->set_var('full_address',$full_address);
			
				$this->t->set_var('message_counter',$i);
				$this->t->set_var('message_uid',$header['uid']);

				$this->t->set_var('date',$header['date']);
				$this->t->set_var('size',$this->show_readable_size($header['size']));

				$linkData = array
				(
					'menuaction'    => 'felamimail.uidisplay.display',
					'showHeader'	=> 'false',
					'uid'			=> $header['uid']
				);
				$windowName = ($_readInNewWindow == 1 ? 'displayMessage' : 'displayMessage_'.$header['uid']);
				$this->t->set_var('url_read_message',"egw_openWindowCentered('".$GLOBALS['egw']->link('/index.php',$linkData)."','$windowName',700,egw_getWindowOuterHeight());");
			
				if($_isSentFolder)
				{
					if(!empty($header['to_name']))
					{
						list($mailbox, $host) = explode('@',$header['to_address']);
						$senderAddress  = imap_rfc822_write_address($mailbox,
								$host,
								$header['to_name']);
					}
					else
					{
						$senderAddress  = $header['to_address'];
					}
				}
				else
				{
					if(!empty($header['sender_name']))
					{
						list($mailbox, $host) = explode('@',$header['sender_address']);
						$senderAddress  = imap_rfc822_write_address($mailbox,
								$host,
								$header['sender_name']);
					}
					else
					{
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
					'menuaction'    => 'addressbook.uiaddressbook.add_email',
					'add_email'	=> urlencode($header['sender_address']),
					'name'		=> urlencode($header['sender_name']),
					'referer'	=> urlencode($_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING'])
				);
				//TODO: url_add_to_addressbook isn't in any of the templates. 
				//If you want to use it, you need to adopt syntax to the new addressbook (popup)
				$this->t->set_var('url_add_to_addressbook',$GLOBALS['egw']->link('/index.php',$linkData));
				$this->t->set_var('msg_icon_sm',$msg_icon_sm);
				
				$this->t->set_var('phpgw_images',EGW_IMAGES);
				$this->t->set_var('row_css_class','header_row_'.$flags);
		
				$this->t->parse('message_rows','header_row',True);
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
			
			return "<div class='navButton' style='float:$float;' onmousedown='this.className=\"navButtonActive\";' onmouseup='this.className=\"navButtonHover\";' onmouseout='this.className=\"navButton\";' onclick=\"$_imageAction\"><img style='width:16px; height:16px;' title='$_toolTip' src='$image' ></div>\n";
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
	}
?>