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

	class felamimail_bofilter
	{
		var $public_functions = array
		(
			'getActiveFilter'	=> True,
			'flagMessages'		=> True
		);

		function __construct($_restoreSession=true)
		{
			$this->accountid	= $GLOBALS['egw_info']['user']['account_id'];
			
			$this->sofilter		= new felamimail_sofilter();
			
			$this->sessionData['activeFilter'] = "-1";
			
			if ($_restoreSession) $this->restoreSessionData();
			
			if(!is_array($this->sessionData['filter'])) {
				$this->sessionData['filter'][0]['filterName'] = lang('Quicksearch');
				$this->saveSessionData();
			}
			if(!isset($this->sessionData['activeFilter'])) {
				$this->sessionData['activeFilter'] = "-1";
			}
		}
		
		function deleteFilter($_filterID)
		{
			unset($this->sessionData['filter'][$_filterID]);
			$this->saveSessionData();
			$this->sofilter->saveFilter($this->sessionData['filter']);
		}
		
		function getActiveFilter()
		{
			return $this->sessionData['activeFilter'];
		}
		
		function getFilter($_filterID)
		{
			return $this->sessionData['filter'][$_filterID];
		}
		
		function getFilterList()
		{
			return $this->sessionData['filter'];
		}
		
		function restoreSessionData()
		{
			$arrayFunctions =& CreateObject('phpgwapi.arrayfunctions');

			$this->sessionData = $GLOBALS['egw']->session->appsession('filter_session_data');

			// sort the filter list
			$unsortedFilter = $this->sofilter->restoreFilter();
			
			// save the quicksearchfilter
			// must always have id=0
			if(is_array($unsortedFilter[0]))
			{
				$quickSearchFilter[0] = $unsortedFilter[0];
				unset($unsortedFilter[0]);
			}
			// or create the array
			else
			{
				$quickSearchFilter[0] = array('filterName' => lang('quicksearch'));
			}
			
			// _debug_array($this->sessionData['filter']);
			// the first one is always the quicksearch filter
			if(count($unsortedFilter) > 0)
			{
				$sortedFilter = $arrayFunctions->arfsort($unsortedFilter, array('filterName'));
				$sortedFilter = array_merge($quickSearchFilter, $sortedFilter);
			}
			else
			{
				$sortedFilter = $quickSearchFilter;
			}
			#_debug_array($sortedFilter);

			$this->sessionData['filter'] = $sortedFilter;
		}
		
		function saveFilter($_formData, $_filterID='')
		{
			if(!empty($_formData['filterName']))
				$data['filterName']	= $_formData['filterName'];
			if(!empty($_formData['from']))
				$data['from']	= $_formData['from'];
			if(!empty($_formData['to']))
				$data['to']	= $_formData['to'];
			if(!empty($_formData['subject']))
				$data['subject']= $_formData['subject'];
			
			if($_filterID === '')
			{
				$this->sessionData['filter'][] = $data;
			}
			else
			{
				$this->sessionData['filter'][$_filterID] = $data;
			}
			
			$this->saveSessionData();
			
			$this->sofilter->saveFilter($this->sessionData['filter']);
		}
		
		function saveSessionData()
		{
			$GLOBALS['egw']->session->appsession('filter_session_data','',$this->sessionData);
		}
		
		function setActiveFilter($_filter)
		{
			$this->sessionData['activeFilter'] = "$_filter";
			$this->saveSessionData();
		}
		
		function updateFilter($_data)
		{
			$filter = $this->getFilterList();
			$activeFilter = $this->getActiveFilter();
			
			// check for new quickfilter
			if($activeFilter == $_data['filter'] && isset($_data['quickSearch']))
			{
				#print "&nbsp;new Quickfilter $_quickSearch<br>";
				if($_data['quickSearch'] == '')
				{
					$this->setActiveFilter("-1");
				}
				else
				{
					$this->setActiveFilter("0");
					$data['filterName']	= lang('Quicksearch');
					$data['subject']	= $_data['quickSearch'];
					$data['from']		= $_data['quickSearch'];
					$this->saveFilter($data, '0');
				}
			}
			else
			{
				$this->setActiveFilter($_data['filter']);
			}
		}
	}

?>
