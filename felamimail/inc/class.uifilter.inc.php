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
	* Free Software Foundation; either version 2 of the License, or (at your    *
	* option) any later version.                                                *
	\***************************************************************************/
	/* $Id$ */

	class uifilter
	{

		var $public_functions = array
		(
			'mainScreen'	=> 'True',
			'saveFilter'	=> 'True'
		);

		function uifilter()
		{
			
			// get posted variables
			$this->urlMailbox	= urldecode($_GET['mailbox']);
			$this->startMessage	= $_GET['startMessage'];
			$this->sort		= $_GET['sort'];
			
			$this->bofelamimail	=& CreateObject('felamimail.bofelamimail',$this->urlMailbox);
			$this->bofilter		=& CreateObject('felamimail.bofilter');
			$this->sessionData	= $this->bofelamimail->sessionData;

			$this->t =& CreateObject('phpgwapi.Template',EGW_APP_TPL);
			$this->t->set_unknowns('remove');
			
			$this->rowColor[0] = $GLOBALS['egw_info']["theme"]["bg01"];
			$this->rowColor[1] = $GLOBALS['egw_info']["theme"]["bg02"];

		}
		
		function display_app_header()
		{
			$GLOBALS['egw_info']['flags']['include_xajax'] = True;
			$GLOBALS['egw']->common->egw_header();
			echo parse_navbar();
		}

		function mainScreen()
		{
			// display the header
			$this->display_app_header();
			
			// initialize the template
			$this->t->set_file(array("filterForm" => "filterForm.tpl"));
			$this->t->set_block('filterForm','header');
			$this->t->set_block('filterForm','filterrow');
			
			// translate most of the parts
			$this->translate();
			
			switch($_GET['action'])
			{
				case "deleteFilter":
					$filterID = $_GET['filterID'];
					$this->bofilter->deleteFilter($filterID);
					$filterList = $this->bofilter->getFilterList();
														$linkData = array
														(
																		'menuaction'    => 'felamimail.uifilter.mainScreen',
																		'action'	=> 'updateFilter'
														);
					$this->t->set_var('link_action',$GLOBALS['egw']->link('/index.php',$linkData));

					$this->t->set_var("filterName",'');
					$this->t->set_var("from",'');
					$this->t->set_var("to",'');
					$this->t->set_var("subject",'');
					$this->t->set_var("filter_checked",'');
					break;
					
				case "editFilter":
					$filterID = $_GET['filterID'];
					$filterList = $this->bofilter->getFilterList();
					
														// set the default values for the sort links (sort by url)
														$linkData = array
														(
																		'menuaction'    => 'felamimail.uifilter.mainScreen',
																		'action'	=> 'updateFilter',
																		'filterID'	=> $filterID
														);
					$this->t->set_var('link_action',$GLOBALS['egw']->link('/index.php',$linkData));

					$this->t->set_var("filterName",$filterList[$filterID]['filterName']);
					$this->t->set_var("from",$filterList[$filterID]['from']);
					$this->t->set_var("to",$filterList[$filterID]['to']);
					$this->t->set_var("subject",$filterList[$filterID]['subject']);
					if($filterList[$filterID]['filterActive'] == "true")
					{
						$this->t->set_var("filter_checked","checked");
					}
					break;
				case "updateFilter":
					$filterID = $_GET['filterID'];
					$formData['from']		= $_POST['from'];
					$formData['to']			= $_POST['to'];
					$formData['subject']		= $_POST['subject'];
					$formData['filterName']		= $_POST['filterName'];
					if($_POST['filter_active'] == "on")
					{
						$formData['filterActive']	= "true";
					}
					$this->bofilter->saveFilter($formData, $filterID);
					$filterList = $this->bofilter->getFilterList();
														// set the default values for the sort links (sort by url)
														$linkData = array
														(
																		'menuaction'    => 'felamimail.uifilter.mainScreen',
																		'action'	=> 'updateFilter',
																		'filterID'	=> $filterID
														);
					$this->t->set_var('link_action',$GLOBALS['egw']->link('/index.php',$linkData));

					$this->t->set_var("filterName",$filterList[$filterID]['filterName']);
					$this->t->set_var("from",$filterList[$filterID]['from']);
					$this->t->set_var("to",$filterList[$filterID]['to']);
					$this->t->set_var("subject",$filterList[$filterID]['subject']);
					if($filterList[$filterID]['filterActive'] == "true")
					{
						$this->t->set_var("filter_checked","checked");
					}
					break;
				default:
														$linkData = array
														(
																		'menuaction'    => 'felamimail.uifilter.mainScreen',
																		'action'	=> 'updateFilter'
														);
					$this->t->set_var('link_action',$GLOBALS['egw']->link('/index.php',$linkData));

					$this->t->set_var("filterName",'');
					$this->t->set_var("from",'');
					$this->t->set_var("to",'');
					$this->t->set_var("subject",'');
					$this->t->set_var("filter_checked",'');
					break;
					
			}
									$linkData = array
									(
													'menuaction'    => 'felamimail.uifilter.mainScreen'
									);
			$this->t->set_var('link_newFilter',$GLOBALS['egw']->link('/index.php',$linkData));
			$this->t->set_var("filterrows",'');
			$linkData = array
			(
				'menuaction'	=> 'felamimail.uifelamimail.viewMainScreen',
				'filter'	=> -1
			);
			$link = $GLOBALS['egw']->link('/index.php',$linkData);
			$this->t->set_var("link_noFilter",$link);
			
			$filterList = $this->bofilter->getFilterList();
			
			while(list($key,$value)=@each($filterList))
			{
				$this->t->set_var("id",$key);
				$this->t->set_var("filtername",$value['filterName']);

				$linkData = array
				(
					'menuaction'	=> 'felamimail.uifilter.mainScreen',
					'action'	=> 'editFilter',
					'filterID'	=> $key
				);
				$link = $GLOBALS['egw']->link('/index.php',$linkData);
				$this->t->set_var("link_editFilter",$link);

				$linkData = array
				(
					'menuaction'	=> 'felamimail.uifilter.mainScreen',
					'action'	=> 'deleteFilter',
					'filterID'	=> $key
				);
				$link = $GLOBALS['egw']->link('/index.php',$linkData);
				$this->t->set_var("link_deleteFilter",$link);

				$linkData = array
				(
					'menuaction'	=> 'felamimail.uifelamimail.changeFilter',
					'filter'	=> $key
				);
				$link = $GLOBALS['egw']->link('/index.php',$linkData);
				$this->t->set_var("link_activateFilter",$link);

				$this->t->parse("filterrows","filterrow",true);
			}
			$this->t->pparse("out","header");
		}

		function saveFilter()
		{
			
		}
		
		function translate()
		{
			$this->t->set_var("lang_message_list",lang('Message List'));
			$this->t->set_var("lang_from",lang('from'));
			$this->t->set_var("lang_to",lang('to'));
			$this->t->set_var("lang_edit",lang('edit'));
			$this->t->set_var("lang_delete",lang('delete'));
			$this->t->set_var("lang_subject",lang('subject'));
			$this->t->set_var("lang_filter_active",lang('filter active'));
			$this->t->set_var("lang_filter_name",lang('filter name'));
			$this->t->set_var("lang_new_filter",lang('new filter'));
			$this->t->set_var("lang_no_filter",lang('no filter'));
			$this->t->set_var("lang_activate",lang('activate'));
			$this->t->set_var("lang_save",lang('save'));

			$this->t->set_var("bg01",$GLOBALS['egw_info']["theme"]["bg01"]);
			$this->t->set_var("bg02",$GLOBALS['egw_info']["theme"]["bg02"]);
			$this->t->set_var("bg03",$GLOBALS['egw_info']["theme"]["bg03"]);
		}
}
