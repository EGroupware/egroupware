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
			$template = CreateObject('phpgwapi.Template',PHPGW_APP_TPL);
			$this->template = $template;
			$this->template->set_file(array("body" => 'uiwidgets.tpl'));
		}

		/**
		* create a folder tree
		*
		* this function will create a foldertree based on javascript
		*
		* @param _folders array containing the list of folders
		* @param _selected string containing the selected folder
		* @param _topFolderName string containing the top folder name
		* @param _topFolderDescription string containing the description for the top folder
		*
		* @returns the html code, to be added into the template
		*/
		function createHTMLFolder($_folders, $_selected, $_formName, $_valueName, $_topFolderName, $_topFolderDescription)
		{
			$folderImageDir = substr($GLOBALS['phpgw']->common->image('phpgwapi','foldertree_line.gif'),0,-19);
			
			// careful! "d = new..." MUST be on a new line!!!
			$folder_tree_new = "<script type='text/javascript'>d = new dTree('d','".$folderImageDir."');d.config.inOrder=true;d.config.closeSameLevel=true;";
			
			$allFolders = array();

			// create a list of all folders, also the ones which are not subscribed
 			foreach($_folders as $key => $value)
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

			// keep track of the last parent id
			$parentStack	= array();
			$counter	= 0;
			$folder_name	= $_topFolderName;
			$folder_title	= $_topFolderDescription;
			$folder_icon = $folderImageDir."foldertree_base.gif";
			// and put the current counter on top
			array_push($parentStack, 0);
			$parent = -1;
			#$folder_tree_new .= "d.add(0,-1,'$folder_name','javascript:void(0);','','','$folder_title');";
			if($_selected == '--topfolderselected--')
			{
				$folder_name = "<font style=\"background-color: #dddddd\">$folder_name</font>";
			}
			$folder_tree_new .= "d.add(0,-1,'$folder_name','#','document.$_formName.$_valueName.value=\'--topfolderselected--\'; document.$_formName.submit();','','$folder_title');\n";

			$counter++;
			
			foreach($allFolders as $key => $value)
			{
				$countedDots = substr_count($key,".");
				#print "$value => $counted_dots<br>";
				

				// hihglight currently selected mailbox
				if ($_selected == $key)
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
				if($parent == '')
					$parent = 0;
				
				// Node(id, pid, name, url, urlClick, urlOut, title, target, icon, iconOpen, open) {
				$folder_tree_new .= "d.add($counter,$parent,'$folder_name','#','document.$_formName.$_valueName.value=\'$key\'; document.$_formName.submit();','','$key','','$folder_icon','$folderOpen_icon');\n";
				$counter++;
			}

			$folder_tree_new.= "document.write(d);
			d.openTo('$openTo','true');
			</script>";
			
			return $folder_tree_new;
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