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

	require_once EGW_SERVER_ROOT.'/calendar/inc/class.bocalupdate.inc.php';
	require_once EGW_SERVER_ROOT.'/phpgwapi/inc/horde/Horde/iCalendar.php';

	/**
	 * iCal import and export via Horde iCalendar classes
	 *
	 * @package calendar
	 * @author Lars Kneschke <lkneschke@egroupware.org>
	 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
	 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
	 */
	class boical extends bocalupdate
	{
		/**
		 * @var array $supportedFields array containing the supported fields of the importing device
		 */
		var $supportedFields;
		
		/**
		 * @var array $status_egw2ical conversation of the participant status egw => ical
		 */
		var $status_egw2ical = array(
			'U' => 'NEEDS-ACTION',
			'A' => 'ACCEPTED',
			'R' => 'DECLINED',
			'T' => 'TENTATIVE',
		);
		/**
		 * @var array conversation of the participant status ical => egw
		 */
		var $status_ical2egw = array(
			'NEEDS-ACTION' => 'U',
			'ACCEPTED'     => 'A',
			'DECLINED'     => 'R',
			'TENTATIVE'    => 'T',
		);
		
		/**
		 * @var array $status_ical2egw conversation of the priority egw => ical
		 */
		var $priority_egw2ical = array(
			0 => 0,		// undefined
			1 => 9,		// low
			2 => 5,		// normal
			3 => 1,		// high
		);
		/**
		 * @var array $status_ical2egw conversation of the priority ical => egw
		 */
		var $priority_ical2egw = array(
			0 => 0,		// undefined
			9 => 1,	8 => 1, 7 => 1, 6 => 1,	// low
			5 => 2,		// normal
			4 => 3, 3 => 3, 2 => 3, 1 => 3,	// high
		);
		
		/**
		 * @var array $recur_egw2ical converstaion of egw recur-type => ical FREQ
		 */
		var $recur_egw2ical = array(
			MCAL_RECUR_DAILY        => 'DAILY',
			MCAL_RECUR_WEEKLY       => 'WEEKLY',
			MCAL_RECUR_MONTHLY_MDAY => 'MONTHLY',	// BYMONHTDAY={1..31}
			MCAL_RECUR_MONTHLY_WDAY => 'MONTHLY',	// BYDAY={1..5}{MO..SO}
			MCAL_RECUR_YEARLY       => 'YEARLY',
		);

		/**
		 * Exports one calendar event to an iCalendar item
		 *
		 * @param int/array $events (array of) cal_id or array of the events
		 * @param string $method='PUBLISH'
		 * @return string/boolean string with vCal or false on error (eg. no permission to read the event)
		 */
		function &exportVCal($events,$method='PUBLISH')
		{
			$egwSupportedFields = array(
				'CLASS'		=> array('dbName' => 'public'),
				'SUMMARY'	=> array('dbName' => 'title'),
				'DESCRIPTION'	=> array('dbName' => 'description'),
				'LOCATION'	=> array('dbName' => 'location'),
				'DTSTART'	=> array('dbName' => 'start'),
				'DTEND'		=> array('dbName' => 'end'),
				'ORGANIZER'	=> array('dbName' => 'owner'),
				'ATTENDEE'	=> array('dbName' => 'participants'),
				'RRULE'     => array('dbName' => 'recur_type'),
				'EXDATE'    => array('dbName' => 'recur_exception'),
 				'PRIORITY'  => array('dbName' => 'priority'),
 				'TRANSP'    => array('dbName' => 'non_blocking'),
				'CATEGORIES'=> array('dbName' => 'category'),
			);
			if(!is_array($this->supportedFields))
			{
				$this->setSupportedFields();
			}
			$vcal = &new Horde_iCalendar;
			$vcal->setAttribute('PRODID','-//eGroupWare//NONSGML eGroupWare Calendar '.$GLOBALS['egw_info']['apps']['calendar']['version'].'//'.
				strtoupper($GLOBALS['egw_info']['user']['preferences']['common']['lang']));
			$vcal->setAttribute('VERSION','2.0');
			$vcal->setAttribute('METHOD',$method);

			if (!is_array($events)) $events = array($events);
			
			foreach($events as $event)
			{
				if (!is_array($event) && !($event = $this->read($event,null,false,'server')))	// server = timestamp in server-time(!)
				{
					return false;	// no permission to read $cal_id
				}
				//_debug_array($event);
				
				$eventGUID = $GLOBALS['egw']->common->generate_uid('calendar',$event['id']);
				
				$vevent = Horde_iCalendar::newComponent('VEVENT',$vcal);
				$parameters = $attributes = array();
				
				foreach($egwSupportedFields as $icalFieldName => $egwFieldInfo)
				{
					if($this->supportedFields[$egwFieldInfo['dbName']])
					{
						switch($icalFieldName)
						{
							case 'ATTENDEE':
								foreach((array)$event['participants'] as $uid => $status)
								{
									// ToDo, this needs to deal with resources too!!!
									if (!is_numeric($uid)) continue;
	
									$mailto = $GLOBALS['egw']->accounts->id2name($uid,'account_email');
									$cn = trim($GLOBALS['egw']->accounts->id2name($uid,'account_firstname'). ' ' .
										$GLOBALS['egw']->accounts->id2name($uid,'account_lastname'));
									$attributes['ATTENDEE'][]	= $mailto ? 'MAILTO:'.$mailto : '';
									// ROLE={CHAIR|REQ-PARTICIPANT|OPT-PARTICIPANT|NON-PARTICIPANT} NOT used by eGW atm.
									$role = $uid == $event['owner'] ? 'CHAIR' : 'REQ-PARTICIPANT';
									// RSVP={TRUE|FALSE}	// resonse expected, not set in eGW => status=U
									$rsvp = $status == 'U' ? 'TRUE' : 'FALSE';
									// PARTSTAT={NEEDS-ACTION|ACCEPTED|DECLINED|TENTATIVE|DELEGATED|COMPLETED|IN-PROGRESS} everything from delegated is NOT used by eGW atm.
									$status = $this->status_egw2ical[$status];
									// CUTYPE={INDIVIDUAL|GROUP|RESOURCE|ROOM|UNKNOWN}
									$cutype = $GLOBALS['egw']->accounts->get_type($uid) == 'g' ? 'GROUP' : 'INDIVIDUAL';
									$parameters['ATTENDEE'][] = array(
										'CN'       => $cn, 
										'ROLE'     => $role, 
										'PARTSTAT' => $status, 
										'CUTYPE'   => $cutype,
										'RSVP'     => $rsvp,
									);
								}
								break;
	            			case 'CLASS':
	            				$attributes['CLASS'] = $event['public'] ? 'PUBLIC' : 'PRIVATE';
	            				break;
	            			case 'ORGANIZER':	// according to iCalendar standard, ORGANIZER not used for events in the own calendar
	            				if (!isset($event['participants'][$event['owner']]) || count($event['participants']) > 1)
	            				{
									$mailtoOrganizer = $GLOBALS['egw']->accounts->id2name($event['owner'],'account_email');
									$attributes['ORGANIZER'] = $mailtoOrganizer ? 'MAILTO:'.$mailtoOrganizer : '';
									$parameters['ORGANIZER']['CN'] = trim($GLOBALS['egw']->accounts->id2name($event['owner'],'account_firstname').' '.
										$GLOBALS['egw']->accounts->id2name($event['owner'],'account_lastname'));
	            				}
								break;
							case 'DTEND':
								if(date('H:i:s',$event['end']) == '23:59:59') $event['end']++;
	            				$attributes[$icalFieldName]	= $event['end'];
	            				break;
	            			case 'RRULE':
	            				if ($event['recur_type'] == MCAL_RECUR_NONE) break;		// no recuring event
	            				$rrule = array('FREQ' => $this->recur_egw2ical[$event['recur_type']]);
	            				switch ($event['recur_type'])
	            				{
	            					case MCAL_RECUR_WEEKLY:
	            						$days = array();
	            						foreach($this->recur_days as $id => $day)
	            						{
	            							if ($event['recur_data'] & $id) $days[] = strtoupper(substr($day,0,2));
	            						}
	            						$rrule['BYDAY'] = implode(',',$days);
	            						break;
	            					case MCAL_RECUR_MONTHLY_MDAY:	// date of the month: BYMONTDAY={1..31}
	            						$rrule['BYMONTHDAY'] = (int) date('d',$event['start']);
	            						break;
	             					case MCAL_RECUR_MONTHLY_WDAY:	// weekday of the month: BDAY={1..5}{MO..SO}
	             						$rrule['BYDAY'] = (1 + (int) ((date('d',$event['start'])-1) / 7)).
	             							strtoupper(substr(date('l',$event['start']),0,2));
	            						break;
	            				}
	             				if ($event['recur_interval'] > 1) $rrule['INTERVAL'] = $event['recur_interval'];
	            				if ($event['recur_enddate']) $rrule['UNTIL'] = date('Ymd',$event['recur_enddate']);	// only day is set in eGW
	            				// no idea how to get the Horde parser to produce a standard conformant
	            				// RRULE:FREQ=... (note the double colon after RRULE, we cant use the $parameter array)
	            				// so we create one value manual ;-)
	            				foreach($rrule as $name => $value)
	            				{
	             					$attributes['RRULE'][] = $name . '=' . $value;
	            				}
	            				$attributes['RRULE'] = implode(';',$attributes['RRULE']);
	             				break;
	             			case 'EXDATE':
	             				if ($event['recur_exception'])
	             				{
	             					$days = array();
	             					foreach($event['recur_exception'] as $day)
	             					{
	             						$days[] = date('Ymd',$day);
	             					}
	             					$attributes['EXDATE'] = implode(',',$days);
	             					$parameters['EXDATE']['VALUE'] = 'DATE';
	             				}
	           					break;
	           				case 'PRIORITY':
	 							$attributes['PRIORITY'] = (int) $this->priority_egw2ical[$event['priority']];
	 							break;
	 						case 'TRANSP':
								$attributes['TRANSP'] = $event['non_blocking'] ? 'TRANSPARENT' : 'OPAQUE';
								break;
							case 'CATEGORIES':
								if ($event['category'])
								{
									$attributes['CATEGORIES'] = implode(',',$this->categories($event['category'],$nul));
								}
								break;
	           				default:
	           					if ($event[$egwFieldInfo['dbName']])	// dont write empty fields
	           					{
	            					$attributes[$icalFieldName]	= $event[$egwFieldInfo['dbName']];
	           					}
	            				break;
	            		}
	            	}
	            }
				$modified = $GLOBALS['egw']->contenthistory->getTSforAction($eventGUID,'modify');
				$created = $GLOBALS['egw']->contenthistory->getTSforAction($eventGUID,'add');
				if (!$created && !$modified) $created = $event['modified'];
				if ($created) $attributes['CREATED'] = $created;
				if (!$modified) $modified = $event['modified'];
				if ($modified) $attributes['LAST-MODIFIED'] = $modified;
	
				$attributes['UID'] = $eventGUID;
	
				foreach($attributes as $key => $value)
				{
					foreach(is_array($value) ? $value : array($value) as $valueID => $valueData)
					{
						$valueData = $GLOBALS['egw']->translation->convert($valueData,$GLOBALS['egw']->translation->charset(),'UTF-8');
						$paramData = (array) $GLOBALS['egw']->translation->convert(is_array($value) ? $parameters[$key][$valueID] : $parameters[$key],
							$GLOBALS['egw']->translation->charset(),'UTF-8');
						//echo "$key:$valueID: value=$valueData, param=".print_r($paramDate,true)."\n";
						$vevent->setAttribute($key, $valueData, $paramData);
						$options = array();
						if($key != 'RRULE' && preg_match('/([\000-\012\015\016\020-\037\075])/',$valueData))
						{
							$options['ENCODING'] = 'QUOTED-PRINTABLE';
						}
						if(preg_match('/([\177-\377])/',$valueData))
						{
							$options['CHARSET'] = 'UTF-8';
						}
						$vevent->setParameter($key, $options);
					}
				}
				$vcal->addComponent($vevent);
			}
			//_debug_array($vcal->exportvCalendar());
			
			return $vcal->exportvCalendar();
		}
		
		function importVCal(&$_vcalData, $cal_id=-1)
		{
			$vcal = &new Horde_iCalendar;
			if(!$vcal->parsevCalendar($_vcalData))
			{
				return FALSE;
			}
			
			if(!is_array($this->supportedFields))
			{
				$this->setSupportedFields();
			}
			//echo "supportedFields="; _debug_array($this->supportedFields);

			$Ok = false;	// returning false, if file contains no components
			foreach($vcal->getComponents() as $component)
			{
				if(is_a($component, 'Horde_iCalendar_vevent'))
				{
					$supportedFields = $this->supportedFields;
					$event = array();
					$vcardData = array('recur_type' => 0);
					
					// lets see what we can get from the vcard
					foreach($component->_attributes as $attributes)
					{
						$attributes['value'] = $GLOBALS['egw']->translation->convert($attributes['value'],'UTF-8');
						//echo "$attributes[name] = '$attributes[value]'<br />\n";

						switch($attributes['name'])
						{
							case 'CLASS':
								$vcardData['public']		= (int)(strtolower($attributes['value']) == 'public');
								break;
							case 'DESCRIPTION':
								$vcardData['description']	= $attributes['value'];
								break;
							case 'DTEND':
								if(date('H:i:s',$attributes['value']) == '00:00:00')
									$attributes['value']--;
								$vcardData['end']		= $attributes['value'];
								break;
							case 'DTSTART':
								$vcardData['start']		= $attributes['value'];
								break;
							case 'LOCATION':
								$vcardData['location']	= $attributes['value'];
								break;
							case 'RRULE':
								$recurence 			= $attributes['value'];
								switch(substr($recurence,0,1))
								{
									case 'W':
										if(preg_match('/W(\d+) (.*) (.*)/',$recurence, $recurenceMatches))
										{
											$vcardData['recur_type'] = MCAL_RECUR_WEEKLY;
											$vcardData['recur_interval'] = $recurenceMatches[1];
											foreach(explode(' ',trim($recurenceMatches[2])) as $recurenceDay)
											{
												switch($recurenceDay)
												{
													case 'SU':
														$vcardData['recur_data'][] = MCAL_M_SUNDAY;
														break;
													case 'MO':
														$vcardData['recur_data'][] = MCAL_M_MONDAY;
														break;
													case 'TU':
														$vcardData['recur_data'][] = MCAL_M_TUESDAY;
														break;
													case 'WE':
														$vcardData['recur_data'][] = MCAL_M_WEDNESDAY;
														break;
													case 'TH':
														$vcardData['recur_data'][] = MCAL_M_THURSDAY;
														break;
													case 'FR':
														$vcardData['recur_data'][] = MCAL_M_FRIDAY;
														break;
													case 'SA':
														$vcardData['recur_data'][] = MCAL_M_SATURDAY;
														break;
												}
											}
										}
										break;
									
									case 'D':
										if(preg_match('/D(\d+) (.*)/',$recurence, $recurenceMatches))
										{
											$vcardData['recur_type'] = MCAL_RECUR_DAILY;
											$vcardData['recur_interval'] = $recurenceMatches[1];
											$vcardData['recur_enddate'] = $vcal->_parseDateTime($recurenceMatches[2]);
								
										}
										break;
									case 'M':
										if(preg_match('/MD(\d+) (.*)/',$recurence, $recurenceMatches))
										{
											$vcardData['recur_type'] = MCAL_RECUR_MONTHLY_MDAY;
											$vcardData['recur_interval'] = $recurenceMatches[1];
										}
										elseif(preg_match('/MP(\d+) (.*) (.*) (.*)/',$recurence, $recurenceMatches))
										{
											$vcardData['recur_type'] = MCAL_RECUR_MONTHLY_WDAY;
											$vcardData['recur_interval'] = $recurenceMatches[1];
										}
										
										break;
									case 'Y':
										if(preg_match('/YM(\d+) (.*)/',$recurence, $recurenceMatches))
										{
											$vcardData['recur_type'] = MCAL_RECUR_YEARLY;
											$vcardData['recur_interval'] = $recurenceMatches[1];
										}
										break;
								}
								break;
	             			case 'EXDATE':
	             				// ToDo: $vcardData['recur_exception'] = ...
	           					break;
							case 'SUMMARY':
								$vcardData['title']		= $attributes['value'];
								break;
							case 'UID':
								$event['uid'] = $vcardData['uid'] = $attributes['value'];
								if ($cal_id <= 0 && !empty($vcardData['uid']) && ($uid_event = $this->read($vcardData['uid'])))
								{
									$event['id'] = $uid_event['id'];
									unset($uid_event);
								}
								break;
	 						case 'TRANSP':
								$vcardData['non_blocking'] = $attributes['value'] == 'TRANSPARENT';
								break;
	           				case 'PRIORITY':
	 							$vcardData['priority'] = (int) $this->priority_ical2egw[$attributes['value']];
	 							break;
	 						case 'CATEGORIES':
	 							$vcardData['category'] = array();
	 							if ($attributes['value'])
	 							{
									if (!is_object($this->cat))
									{
										if (!is_object($GLOBALS['egw']->categories))
										{
											$GLOBALS['egw']->categories =& CreateObject('phpgwapi.categories',$this->owner,'calendar');
										}
										$this->cat =& $GLOBALS['egw']->categories;
									}
	 								foreach(explode(',',$attributes['value']) as $cat_name)
	 								{
	 									if (!($cat_id = $this->cat->name2id($cat_name)))
	 									{
	 										$cat_id = $this->cat->add( array('name' => $cat,'descr' => $cat ));
	 									}
 										$vcardData['category'][] = $cat_id;
	 								}
	 							}
	 							break;	
	 						case 'ATTENDEE':
	 							if (preg_match('/MAILTO:([@.a-z0-9_-]+)/i',$attributes['value'],$matches) &&
	 								($uid = $GLOBALS['egw']->accounts->name2id($matches[1])))
	 							{
	 								$event['participants'][$uid] = preg_match('/PARTSTAT=([a-z-]+)/i',$attributes['value'],$matches) ?
	 									$this->status_ical2egw[strtoupper($matches[1])] : ($uid == $event['owner'] ? 'A' : 'U');
	 							}
	 							break;
	 						case 'ORGANIZER':	// will be written direct to the event
	 							if (preg_match('/MAILTO:([@.a-z0-9_-]+)/i',$attributes['value'],$matches) &&
	 								($uid = $GLOBALS['egw']->accounts->name2id($matches[1])))
	 							{
	 								$event['owner'] = $uid;
	 							}
	 							break;
	 						case 'CREATED':		// will be written direct to the event
	 							if ($event['modified']) break;
	 							// fall through
	 						case 'LAST-MODIFIED':	// will be written direct to the event
								$event['modified'] = $attributes['value'];
								break;
						}
					}
					#_debug_array($vcardData);exit;
					
					// now that we know what the vard provides, we merge that data with the information we have about the device
					$event['priority']		= 2;
					if($cal_id > 0)
					{
						$event['id'] = $cal_id;
					}
					while($fieldName = array_shift($supportedFields))
					{
						switch($fieldName)
						{
							case 'recur_type':
								$event['recur_type'] = $vcardData['recur_type'];
								if ($event['recur_type'] != MCAL_RECUR_NONE)
								{
									foreach(array('recur_interval','recur_enddate','recur_data','recur_exception') as $r)
									{
										if(isset($vcardData[$f]))
										{
											$event[$r] = $vcardData[$r];
										}
									}
								}
								unset($supportedFields['recur_type']);
								unset($supportedFields['recur_interval']);
								unset($supportedFields['recur_enddate']);
								unset($supportedFields['recur_data']);
								break;
							default:
								if (isset($vcardData[$fieldName]))
								{
									$event[$fieldName] = $vcardData[$fieldName];
								}
								unset($supportedFields[$fieldName]);
								break;
						}
					}
					
					// add ourself to new events as participant
	 				if($cal_id == -1 && !isset($this->supportedFields['participants']))
	 				{
						$event['participants'] = array($GLOBALS['egw_info']['user']['account_id'] => 'A');
	 				}
					#foreach($event as $key => $value)
					#{
					#	error_log("KEY: $key  VALUE: $value");
					#}
					//_debug_array($event); exit;
					
					if (!($Ok = $this->update($event, TRUE))) break;	// stop with the first error
				}
			}
			return $Ok;
		}

		function setSupportedFields(&$_productManufacturer='file', &$_productName='')
		{
			$defaultFields = array('public' => 'public', 'description' => 'description', 'end' => 'end',
				'start' => 'start', 'location' => 'location', 'recur_type' => 'recur_type',
				'recur_interval' => 'recur_interval', 'recur_data' => 'recur_data', 'recur_enddate' => 'recur_enddate',
				'title' => 'title',	'priority' => 'priority',

			);
			
			switch(strtolower($_productManufacturer))
			{
				case 'nexthaus corporation':
					switch(strtolower($_productName))
					{
						default:
							$this->supportedFields = $defaultFields + array('participants' => 'participants');
							break;
					}
					break;

				// multisync does not provide anymore information then the manufacturer
				// we suppose multisync with evolution
				case 'the multisync project':
					switch(strtolower($_productName))
					{
						default:
							$this->supportedFields = $defaultFields;
							break;
					}
					break;

				case 'synthesis ag':
					switch(strtolower($_productName))
					{
						default:
							$this->supportedFields = $defaultFields;
							break;
					}
					break;
					
				case 'file':	// used outside of SyncML, eg. by the calendar itself ==> all possible fields
					$this->supportedFields = $defaultFields + array(
						'participants' => 'participants',
						'owner'        => 'owner',
						'non_blocking' => 'non_blocking',
						'category'     => 'category',
					);
					break;

				// the fallback for SyncML
				default:
					error_log("Client not found: $_productManufacturer $_productName");
					$this->supportedFields = $defaultFields;
					break;
			}
		}
	}
?>
