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

	require_once PHPGW_SERVER_ROOT.'/infolog/inc/class.boinfolog.inc.php';
	require_once PHPGW_SERVER_ROOT.'/phpgwapi/inc/horde/Horde/iCalendar.php';

	class vcalinfolog extends boinfolog
	{
		function exportVTODO($_taskID, $_version)
		{
			$taskData = $this->read($_taskID);

			$taskData = $GLOBALS['egw']->translation->convert($taskData,$GLOBALS['egw']->translation->charset(),'UTF-8');
			
			//_debug_array($taskData);
			
			$taskGUID = $GLOBALS['phpgw']->common->generate_uid('infolog_task',$_taskID);
			#print "<br>";
			#print $GLOBALS['phpgw']->contenthistory->getTSforAction($eventGUID,'add');
			#print "<br>";
			
			$vcal = &new Horde_iCalendar;
			$vcal->setAttribute('VERSION',$_version);
			$vcal->setAttribute('METHOD','PUBLISH');
			
			$vevent = Horde_iCalendar::newComponent('VTODO',$vcal);
			
			$options = array();
			
			$vevent->setAttribute('SUMMARY',$taskData['info_subject']);
			$vevent->setAttribute('DESCRIPTION',$taskData['info_des']);
			if($taskData['info_startdate'])
				$vevent->setAttribute('DTSTART',$taskData['info_startdate']);
			if($taskData['info_enddate'])
				$vevent->setAttribute('DUE',$taskData['info_enddate']);
			$vevent->setAttribute('DTSTAMP',time());
			$vevent->setAttribute('CREATED',$GLOBALS['phpgw']->contenthistory->getTSforAction($eventGUID,'add'));
			$vevent->setAttribute('LAST-MODIFIED',$GLOBALS['phpgw']->contenthistory->getTSforAction($eventGUID,'modify'));
			$vevent->setAttribute('UID',$taskGUID);
			$vevent->setAttribute('CLASS',(($taskData['info_access'] == 'public')?'PUBLIC':'PRIVATE'));
			$vevent->setAttribute('STATUS',(($taskData['info_status'] == 'completed')?'COMPLETED':'NEEDS-ACTION'));
			// 3=urgent => 1, 2=high => 2, 1=normal => 3, 0=low => 4
			$vevent->setAttribute('PRIORITY',4-$taskData['info_priority']);

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
			$botranslation  = CreateObject('phpgwapi.translation');
			
			$vcal = &new Horde_iCalendar;
			if(!$vcal->parsevCalendar($_vcalData))
			{
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
						#$attributes['value'] = $GLOBALS['egw']->translation->convert($attributes['value'],'UTF-8');
						switch($attributes['name'])
						{
							case 'CLASS':
								$taskData['info_access']		= strtolower($attributes['value']);
								break;
							case 'DESCRIPTION':
								$taskData['info_des']			= $attributes['value'];
								break;
							case 'DUE':
								$taskData['info_enddate']		= $attributes['value'];
								break;
							case 'DTSTART':
								$taskData['info_startdate']		= $attributes['value'];
								break;
							case 'PRIORITY':
								// 1 => 3=urgent, 2 => 2=high, 3 => 1=normal, 4 => 0=low
								if (1 <= $attributes['value'] && $attributes['value'] <= 4)
								{
									$taskData['info_priority']	= 4 - $attributes['value'];
								}
								else
								{
									$taskData['info_priority']	= 1;	// default = normal
								}
								break;
							case 'STATUS':
								$taskData['info_status']		= (strtolower($attributes['value']) == 'completed') ? 'done' : 'ongoing';
								break;
							case 'SUMMARY':
								$taskData['info_subject']		= $attributes['value'];
								break;
						}
					}
					#_debug_array($eventData);exit;
					return $this->write($taskData);
				}
			}
			
			return FALSE;
		}
		
		
	}
?>
