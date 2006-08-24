<?php
	/**************************************************************************\
	* eGroupWare - iCalendar Parser                                            *
	* http://www.egroupware.org                                                *
	* Written by Lars Kneschke <lkneschke@egroupware.org>                      *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License.              *
	\**************************************************************************/

	/* $Id$ */

	require_once EGW_SERVER_ROOT.'/infolog/inc/class.boinfolog.inc.php';
	require_once EGW_SERVER_ROOT.'/phpgwapi/inc/horde/Horde/iCalendar.php';

	class vcalinfolog extends boinfolog
	{
		var $status2vtodo = array(
			'offer'       => 'NEEDS-ACTION',
			'not-started' => 'NEEDS-ACTION',
			'ongoing'     => 'IN-PROCESS',
			'done'        => 'COMPLETED',
			'cancelled'   => 'CANCELLED',
			'billed'      => 'DONE',
			'call'        => 'NEEDS-ACTION',
			'will-call'   => 'IN-PROCESS',			
		);
		
		var $vtodo2status = array(
			'NEEDS-ACTION' => 'not-started',
			'IN-PROCESS'   => 'ongoing',
			'COMPLETED'    => 'done',
			'CANCELLED'    => 'cancelled',
		);
		
		var $egw_priority2vcal_priority = array(
			0	=> 3,
			1	=> 2,
			2	=> 1,
			3	=> 1,
			
		);

		var $vcal_priority2egw_priority = array(
			1       => 2,
			2       => 1,
			3       => 0,
		);

		function exportVTODO($_taskID, $_version)
		{
			$taskData = $this->read($_taskID);

			$taskData = $GLOBALS['egw']->translation->convert($taskData, $GLOBALS['egw']->translation->charset(), 'UTF-8');
			
			//_debug_array($taskData);
			
			$taskGUID = $GLOBALS['egw']->common->generate_uid('infolog_task',$_taskID);
			#print "<br>";
			#print $GLOBALS['egw']->contenthistory->getTSforAction($eventGUID,'add');
			#print "<br>";
			
			$vcal = &new Horde_iCalendar;
			$vcal->setAttribute('VERSION',$_version);
			$vcal->setAttribute('METHOD','PUBLISH');
			
			$vevent = Horde_iCalendar::newComponent('VTODO',$vcal);
			
			$options = array();
			
			$vevent->setAttribute('SUMMARY',$taskData['info_subject']);
			$vevent->setAttribute('DESCRIPTION',$taskData['info_des']);
			$vevent->setAttribute('LOCATION',$taskData['info_location']);
			if($taskData['info_startdate'])
				$vevent->setAttribute('DTSTART',$taskData['info_startdate']);
			if($taskData['info_enddate'])
				$vevent->setAttribute('DUE',$taskData['info_enddate']);
			if($taskData['info_datecompleted'])
				$vevent->setAttribute('COMPLETED',$taskData['info_datecompleted']);
			$vevent->setAttribute('DTSTAMP',time());
			$vevent->setAttribute('CREATED',$GLOBALS['egw']->contenthistory->getTSforAction($eventGUID,'add'));
			$vevent->setAttribute('LAST-MODIFIED',$GLOBALS['egw']->contenthistory->getTSforAction($eventGUID,'modify'));
			$vevent->setAttribute('UID',$taskGUID);
			$vevent->setAttribute('CLASS',$taskData['info_access'] == 'public' ? 'PUBLIC' : 'PRIVATE');
			$vevent->setAttribute('STATUS',isset($this->status2vtodo[$taskData['info_status']]) ?  
				$this->status2vtodo[$taskData['info_status']] : 'NEEDS-ACTION');
			$vevent->setAttribute('PRIORITY',$this->egw_priority2vcal_priority[$taskData['info_priority']]);

			#$vevent->setAttribute('TRANSP','OPAQUE');
			# status
			# ATTENDEE
			
			$options = array('CHARSET' => 'UTF-8','ENCODING' => 'QUOTED-PRINTABLE');
			$vevent->setParameter('SUMMARY', $options);
			$vevent->setParameter('DESCRIPTION', $options);
			
			$vcal->addComponent($vevent);
			
			#print "<pre>";
			#print $vcal->exportvCalendar();
			#print "</pre>";
			
			return $vcal->exportvCalendar();
		}
		
		function importVTODO(&$_vcalData, $_taskID=-1)
		{
			if(!$taskData = $this->vtodotoegw($_vcalData)) {
				return false;
			}
			
			if($_taskID > 0) {
				$taskData['info_id'] = $_taskID;
			}

			// we suppose that a not set status in a vtodo means that the task did not started yet
			if(empty($taskData['info_status'])) {
				$taskData['info_status'] = 'not-started';
			}
						
			#_debug_array($taskData);exit;
			return $this->write($taskData);
		}
		
		function searchVTODO($_vcalData) {
			if(!$egwData = $this->vtodotoegw($_vcalData)) {
				return false;
			}

			#unset($egwData['info_priority']);

			$filter = array('col_filter' => $egwData);
			if($foundItems = $this->search($filter)) {
				if(count($foundItems) > 0) {
					$itemIDs = array_keys($foundItems);
					return $itemIDs[0];
				}
			}
			
			return false;
		}
		
		function vtodotoegw($_vcalData) {
			$vcal = &new Horde_iCalendar;
			if(!$vcal->parsevCalendar($_vcalData)) {
				return FALSE;
			}

			$components = $vcal->getComponents();

			if(count($components) > 0)
			{
				$component = $components[0];
				if(is_a($component, 'Horde_iCalendar_vtodo'))
				{
					if($_taskID>0)
						$taskData['info_id'] = $_taskID;
						
					foreach($component->_attributes as $attributes)
					{
						#print $attributes['name'].' - '.$attributes['value'].'<br>';
						switch($attributes['name'])
						{
							case 'CLASS':
								$taskData['info_access']		= strtolower($attributes['value']);
								break;
							case 'DESCRIPTION':
								$taskData['info_des']			= $attributes['value'];
								break;
							case 'LOCATION':
								$taskData['info_location']		= $attributes['value'];
								break;
							case 'DUE':
								$taskData['info_enddate']		= $attributes['value'];
								break;
							case 'COMPLETED':
								$taskData['info_datecompleted']	= $attributes['value'];
								break;
							case 'DTSTART':
								$taskData['info_startdate']		= $attributes['value'];
								break;
							case 'PRIORITY':
								if (1 <= $attributes['value'] && $attributes['value'] <= 3)
								{
									$taskData['info_priority']	= $this->vcal_priority2egw_priority[$attributes['value']];
								}
								else
								{
									$taskData['info_priority']	= 1;	// default = normal
								}
								break;
							case 'STATUS':
								$taskData['info_status']		= isset($this->vtodo2status[strtoupper($attributes['value'])]) ?
									$this->vtodo2status[strtoupper($attributes['value'])] : 'ongoing';
								break;
							case 'SUMMARY':
								$taskData['info_subject']		= $attributes['value'];
								break;
						}
					}
					# the horde ical class does already convert in parsevCalendar
					# do NOT convert here
					#$taskData = $GLOBALS['egw']->translation->convert($taskData, 'UTF-8');

					return $taskData;
				}
			}
			return FALSE;
		}
	}
