<?php
/**
 * InfoLog - iCalendar Parser
 *
 * @link http://www.egroupware.org
 * @author Lars Kneschke <lkneschke@egroupware.org>
 * @package infolog
 * @subpackage syncml
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

	require_once EGW_SERVER_ROOT.'/infolog/inc/class.boinfolog.inc.php';
	require_once EGW_SERVER_ROOT.'/phpgwapi/inc/horde/Horde/iCalendar.php';

	class vcalinfolog extends boinfolog
	{
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
			$vevent->setAttribute('STATUS',$this->status2vtodo($taskData['info_status']));
			// we try to preserv the original infolog status as X-INFOLOG-STATUS, so we can restore it, if the user does not modify STATUS
			$vevent->setAttribute('X-INFOLOG-STATUS',$taskData['info_status']);
			$vevent->setAttribute('PRIORITY',$this->egw_priority2vcal_priority[$taskData['info_priority']]);

			if (!empty($taskData['info_cat']))
			{
				$cats = $this->get_categories(array($taskData['info_cat']));
				$vevent->setAttribute('CATEGORIES', $cats[0]);
			}

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
		
		function searchVTODO($_vcalData)
		{
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
		
		function vtodotoegw($_vcalData)
		{
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
								// check if we (still) have X-INFOLOG-STATUS set AND it would give an unchanged status (no change by the user)
								foreach($component->_attributes as $attr)
								{
									if ($attr['name'] == 'X-INFOLOG-STATUS') break;
								}
								$taskData['info_status'] = $this->vtodo2status($attributes['value'],
									$attr['name'] == 'X-INFOLOG-STATUS' ? $attr['value'] : null);
								break;
							case 'SUMMARY':
								$taskData['info_subject']		= $attributes['value'];
								break;

							case 'CATEGORIES':
								{
									$cats = $this->find_or_add_categories(explode(',', $attributes['value']));
									$taskData['info_cat'] = $cats[0];
								}
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

		function exportVNOTE($_noteID, $_type)
		{
			$note = $this->read($_noteID);
			$note = $GLOBALS['egw']->translation->convert($note, $GLOBALS['egw']->translation->charset(), 'UTF-8');

			switch($_type)
			{
				case 'text/plain':
					$txt = $note['info_subject']."\n\n".$note['info_des'];
					return $txt;
					break;

				case 'text/x-vnote':
					$noteGUID = $GLOBALS['egw']->common->generate_uid('infolog_note',$_noteID);
					$vnote = &new Horde_iCalendar_vnote();
					$vNote->setAttribute('VERSION', '1.1');
					$vnote->setAttribute('SUMMARY',$note['info_subject']);
					$vnote->setAttribute('BODY',$note['info_des']);
					if($note['info_startdate'])
						$vnote->setAttribute('DCREATED',$note['info_startdate']);
					$vnote->setAttribute('DCREATED',$GLOBALS['egw']->contenthistory->getTSforAction($eventGUID,'add'));
					$vnote->setAttribute('LAST-MODIFIED',$GLOBALS['egw']->contenthistory->getTSforAction($eventGUID,'modify'));
					if (!empty($note['info_cat']))
					{
						$cats = $this->get_categories(array($note['info_cat']));
						$vnote->setAttribute('CATEGORIES', $cats[0]);
					}

					#$vnote->setAttribute('UID',$noteGUID);
					#$vnote->setAttribute('CLASS',$taskData['info_access'] == 'public' ? 'PUBLIC' : 'PRIVATE');
		
					#$options = array('CHARSET' => 'UTF-8','ENCODING' => 'QUOTED-PRINTABLE');
					#$vnote->setParameter('SUMMARY', $options);
					#$vnote->setParameter('DESCRIPTION', $options);
			
					return $vnote->exportvCalendar();
					break;
			}
			return false;
		}
		
		function importVNOTE(&$_vcalData, $_type, $_noteID = -1)
		{
			if(!$note = $this->vnotetoegw($_vcalData, $_type))
			{
				return false;
			}
			
			if($_noteID > 0)
			{
				$note['info_id'] = $_noteID;
			}

			if(empty($note['info_status'])) {
				$note['info_status'] = 'done';
			}
						
			#_debug_array($taskData);exit;
			return $this->write($note);
		}
		
		function searchVNOTE($_vcalData, $_type)
		{
			if(!$note = $this->vnotetoegw($_vcalData)) {
				return false;
			}

			$filter = array('col_filter' => $egwData);
			if($foundItems = $this->search($filter)) {
				if(count($foundItems) > 0) {
					$itemIDs = array_keys($foundItems);
					return $itemIDs[0];
				}
			}
			
			return false;
		}
		
		function vnotetoegw($_data, $_type)
		{
			switch($_type)
			{
				case 'text/plain':
					$note = array();
					$note['info_type'] = 'note';
					$botranslation  =& CreateObject('phpgwapi.translation');
					$txt = $botranslation->convert($_data, 'utf-8');
					$txt = str_replace("\r\n", "\n", $txt);

					if (preg_match("/^(^\n)\n\n(.*)$/", $txt, $match))
					{
						$note['info_subject'] = $match[0];
						$note['info_des'] = $match[1];
					}
					else
					{
						$note['info_des'] = $txt;
					}

					return $note;
					break;
					
				case 'text/x-vnote':
					$vnote = &new Horde_iCalendar;
					if (!$vcal->parsevCalendar($_data))
					{
						return FALSE;
					}
					$components = $vnote->getComponent();
					if(count($components) > 0)
					{
						$component = $components[0];
						if(is_a($component, 'Horde_iCalendar_vnote'))
						{
							$note = array();
							$note['info_type'] = 'note';

							foreach($component->_attributes as $attribute)
							{
								switch ($attribute['name'])
								{
									case 'BODY':
										$note['info_des'] = $attribute['value'];
										break;
									case 'SUMMARY':
										$note['info_subject'] = $attribute['value'];
										break;
									case 'CATEGORIES':
										{
											$cats = $this->find_or_add_categories(explode(',', $attribute['value']));
											$note['info_cat'] = $cats[0];
										}
										break;
								}
							}
						}
						return $note;
					}
			}
			return FALSE;
		}
	}

