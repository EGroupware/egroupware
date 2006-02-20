<?php
   /** 
	* @file 
	* eGroupWare - iCalendar VEVENTS conversion, import and export for egw calendar.
	*
	* http://www.egroupware.org                                                *
	* @author Jan van Lieshout   
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License.              *
	**************************************************************************/


  /* JVL Todo V0.7.82
   * TODO:
   * [+] check multiple ATTENDEE import
   * [+] check multiple ATTENDEE export
   * [+] add and check 'whole day event' support
   * [+] check multiple CATEGORY export
   * [+?] check multiple category import (do they get duplicated?
   * [+]check recur EXPORT stuff
   *    [-/+] ENDDATE egw has other definition of 'end on' than KO3.5
   *    [+] by DAY, [+]UNTIL [+]INTERVAL 
   *        NOTE BYDAY was bugged in boical!!!
   *    [ ] @todo test RRULE export for by MONTH, [ ]YEAR,[+] WEEKLY (probably works?)
   *    [+] EXDATE fields export
   *    [-] @todo inform people on COUNT fields import problem: not present in egw yet
   * [ ]check recur IMPORT stuff
   *    [+] by day, recur_interval 
   *    [ ] @todo test RRULE import by month, interval (probably works?)
   *    [+] recur_exception (content gets into event correctly, but..)
   *    [-] COUNT (not supported in egw i think?)
   * [+basic] check EXPORT of VALARMS   (only time, no action selectable)
   * [+basic] check IMPORT of VALARMS     (only time, no action selectable)
   * [+/-] @todo add switch to control import of non egw known attendees
   * [+] X-DELETED import
   * [ ] find a nicer way to provide a safe importmode parameter usage (now $cal_id==0)
   * [ ] @todo test the usage and conversions of user time and server times and timezones in
   *     exported and imported ical files.
   */

  //	require_once EGW_SERVER_ROOT.'/calendar/inc/class.bocalupdate.inc.php';
  //    require_once EGW_SERVER_ROOT.'/icalsrv/inc/class.egwical.inc.php';

    // these constants should be moved to somewhere global (not used at the moment)
   define("ICAL_IMODE_DUPLICATE",'DUPLICATE');
   define("ICAL_IMODE_OVERWRITE",'OVERWRITE');

    /**
	 * Workers class for iCal vevents import and export via Egw bocalupdate calendar objects
	 *
	 * @todo Here should come some text about the workings of this class
	 * (esp. the uid2id mechanisme the handling of ACL failures and....) and its role
	 * in the WURH pattern
	 *
	 * @package egwical
	 * @todo move the compatibility functions for vcalinfolog completely to the compat class.
	 *  There is no need to have them here anymore.
	 *
	 * @since V0.7.82 on import the egw event uid field is no longer used unless
	 *               the switch @ref $uid_matching is set. Default this is off.
	 * @author Jan van Lieshout <jvl-AT-xs4all.nl> (This version. new api rewrite,
	 * refactoring, and extension).
	 * @author Lars Kneschke <lkneschke@egroupware.org> (parts from boical that are reused here)
	 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de> (parts from boical that are
	 * reused here)
	 * @version 0.9.03 (First WURH version, most stuff used from old bovevents class)
	 * @since 0.9.03 changed mke_RECUR2rar() api
	 * @license  http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
	 */
    class bocalupdate_vevents extends egwical
    {
	  
	  /**
	   * @private
	   * @var boolean
	   * Switch to print extra debugging about imported and exported events to the httpd errorlog
	   * stream.
	   */
	  var $evdebug = true;

	  /**
	   * @private
	   * @var object
	   * The egw bocal calendar that will be used to transport events from and to
	   * This is set by setRsc()
	   */
	  var $mycal = null;

	  /**
	   * Describe the provided work capabilities of the class.
	   * @return string The description as entries for the @ref $reg_workers registry
	   * table.
	   */
	  function provides_work()
	  {
		return 
		  array('bocalupdate' => array('workerclass' => 'bocalupdate_vevents',
									   'workerobj'   =>  null,
									   'icalsup'   => array('VEVENT')),
				// next one is a subclass of bocalupdate, we can work for that too
				'boical'      => array('workerclass' => 'bocalupdate_vevents',
									   'workerobj'   =>  null,
									   'icalsup'   => array('VEVENT')),
				);
	  }

	  /**
	   * Our Constructor, sets the basic class members @ref $ei , @ref supportedFields
	   * @ref $ical2egwFields and @ref $iprovide_work
	   */
	  function bocalupdate_vevents($prodid='all')
	  {
		// call our abstract superclass constructor
		egwical::egwical();
		//@todo rewrite supportedFields setting to distribute it over the egwical
		// baseclass and the subclasses cleverly
		$this->_set_ical2egwFields(); // add VEVENT and event pairs only
		$this->setSupportedFields($prodid);

		return true;		
	  }

	  /** 
	   * Set the egw resource  that this worker will handle.
	   * This worker is only capable of handling  bocalupdate calendar objects, so it should
	   * be of that class. This method is mostly called indirectly from a egwical compound
	   * addRsc() call. But you can call it also directly (if you know what your doing ..)
	   * @return boolean false on error, true  else
	  */
	  function setRsc($egw_rsc)
	  {
		if(!is_a($egw_rsc,'bocalupdate'))
		  return false;
		$this->mycal = $egw_rsc;
		return true;
	  }

	  // -------- below only conversion and import/export stuff -----

	  /**
	   * @private
	   * @var array $ical2egwFields
	   * An array containing roughly the mapping from iCalendar
	   * to egw fields. Set by constructor.
	   */
	  var $ical2egwFields;

	  /**
	   * @private
	   * @var array $supportedFields
	   * An array with the current supported fields of the
	   * importing/exporting device.
	   * To detect if a certain ical property (eg ORGANIZER)  is supported in the current
	   * data import/export do a  <code>isset($this->supportedFields['ORGANIZER'])</code>.
	   * To detect if a certain egw field (eg <code>status</code>)  is supported in the current
	   * data import/export do a
	   * <code>in_array(array_flatten(array_values($this->supportedFields)),'status')</code>
	   * or something like that (not tested, implemented, or needed yet..) Maybe should
	   * implement a method for this..
	   * @note This table should probably better be in class @ref egwical
	   */
	  var $supportedFields;

	  


	  /**
	   * @var boolean
	   * Switch that determines if uid matching is tried.
	   *
	   * For a more on <i>uidmatching</i> @see \secimpumatch 
	   *
	   * If $uid_matching is <b>true</b> then:
	   * - on import of a vevent the update routines will first try to
	   *  find an existing egw event with the same uid value as present
	   *  in the UID field of the newly to be imported vevent. If this
	   *  succeeds this egw event will get updated with the info from
	   *  the vevent. If this fails a new event will be generated and
	   *  the uid taken from the vevent will be stored in its uid
	   *  field.
	   *
	   * if $uid_matching is <b>false</b> then:
	   * - On import the VEVENT UID field will be checked, if it
	   *  appears to be a previously exported uid value then the
	   *  encoded egw id of the old egw event is retrieved and used for
	   *  update.  If it doesnot have a uid value with a valid egw id
	   *  encoding, then the its is handled as being a new VEVENT to be
	   *  imported, and a new egw id will be generated. The old vevent
	   *  uid will though be saved for possible later use, (just as
	   *  with uid_matching on).
	   */
	  var $uid_matching = false;

	  /**
	   * @var boolean
	   * Switch that determines if events not anymore in egw are allowed to be reimported
	   *
	   * Default this is on
	   */
	  var $reimport_missing_events = true;



	  /**
	   * Export Egw events and add them to a Horde_iCalendar.
	   *
	   * The eGW events in $events are exported to iCalendar VEVENTS and then these are added to
	   * the Horde_iCalendar object &$hIcal.
	   * Note that only supported Fields are exported as VEVENTS to the iCalendar.
	   *
	   * @section secexpeuid Egw uid export switch 
	   * If $euid_export is set, then for each exported event, the current value of the event uid
	   * as stored in Egw, will be used to produce a value for the vevent its UID field. When off
	   * a new UID value will generated with the egw event id encoded. 
	   * 
	   * @param Horde_iCalendar &$hIcal
	   *  object to wich the produced VEvents are added.
	   * @param array $events the array with eGW events (or event id's)  that will be exported
	   * @param boolean $euid_export switch to enable export of the egw uid fields, when off
	   * default) the vevents uid fields get a value generated with the egw id encoded.
	   * @return boolean|int $ok/$vcnt    on error: false / on success: nof vevents exported 
	   * @ref $supportedFields determines which fields of VEVENT will be exported
	   */
	  function exportEventsOntoIcal(&$hIcal, $events, $euid_export=false,
									$reimport_missing_events=false)
	  {
		$vexpcnt =0; // number of vevents exported

		$veExportFields =& $this->supportedFields;
		
		if (!is_array($events)) $events = array($events);
		  
		foreach($events as $event) {
		  // event was passed as an event id
		  if (!is_array($event)){
			$eid = $event;
			if( !$event = $this->mycal->read($eid,null,false,'server')){
			  // server = timestamp in server-time(!)
			  return false;	// no permission to read $cal_id
			}
			// event was passed as an array of fields
		  } else {
			$eid = $event['id'];
			// now read it again to get all fields (including our alarms)
			$event = $this->mycal->read($eid);
		  }

 //		  error_log('>>>>>>>>>>>' .'event to export=' . print_r($event,true));

		  // now create a UID value
		  if ($euid_export)	{
			// put egw uid into VEVENT, to allow client to sync with his uids
			$eventGUID = $event['uid'];
		  } else {
			$eventGUID = $this->mki_v_guid($eid,'calendar');
		  }

		  $vevent = Horde_iCalendar::newComponent('VEVENT',$hIcal);
		  $parameters = $attributes = array();
		  // to important to let supportedFields decide on this
		  $attributes['UID'] = $eventGUID;				

		  foreach($veExportFields as $veFieldName) {

			  switch($veFieldName) {
			  case 'UID':
				// already set
				break;

			  case 'ATTENDEE':
				foreach((array)$event['participants'] as $pid => $partstat) {
				  if (!is_numeric($pid)) continue;

				  list($propval,$propparams) =
					$this->mki_vp_4ATTENDEE($pid,$partstat,$event['owner']);
				  // NOTE: we need to add it already: multiple ATTENDEE fields may be occur 
				  $this->addAttributeOntoVevent($vevent,'ATTENDEE',$propval,$propparams);
				}
				break;

			  case 'CLASS':
				$attributes['CLASS'] = $event['public'] ? 'PUBLIC' : 'PRIVATE';
				break;

				// according to rfc, ORGANIZER not used for events in the own calendar
			  case 'ORGANIZER':	
				if (!isset($event['participants'][$event['owner']])
					|| count($event['participants']) > 1) {
				  $attributes['ORGANIZER']  = $this->mki_v_CAL_ADDRESS($event['owner']);
				  $parameters['ORGANIZER']  = $this->mki_p_CN($event['owner']);
				}
				break;

				// Note; wholeday detection may change the DTEND value later! 
			  case 'DTEND':
				//				if(date('H:i:s',$event['end']) == '23:59:59')
				// $event['end']++;
				$attributes[$veFieldName]	= $event['end'];
				break;

			  case 'RRULE':
				if ($event['recur_type'] == MCAL_RECUR_NONE)
				  break;		// no recuring event
				$attributes['RRULE'] = $this->mki_v_RECUR($event['recur_type'],
															  $event['recur_data'],
															  $event['recur_interval'],
															  $event['start'],
															  $event['recur_enddate']);
				break;

			  case 'EXDATE':
				if ($event['recur_exception'])	{
				  list(	$attributes['EXDATE'], $parameters['EXDATE'])=
					$this->mki_vp_4EXDATE($event['recur_exception'],false);
				}
				break;

			  case 'PRIORITY':
				if (is_numeric($eprio = $event['priority']) && ($eprio >0) )
				  $attributes['PRIORITY'] =  $this->mki_v_prio($eprio);
				break;

			  case 'TRANSP':
				$attributes['TRANSP'] = $event['non_blocking'] ? 'TRANSPARENT' : 'OPAQUE';
				break;

			  case 'CATEGORIES':
				if ($catids = $event['category']){ 
				  $catnamescstr = $this->cats_ids2idnamescstr(explode(',',$catids));
				  $attributes['CATEGORIES'] = $catnamescstr;
				}
				break;

				// @todo find out about AALARM, DALARM, Is this in the RFC !?
			  case 'AALARM':
				foreach($event['alarm'] as $alarmID => $alarmData) {
				  $attributes['AALARM'] = $hIcal->_exportDateTime($alarmData['time']);
				  // lets take only the first alarm
				  break;
				}
				break;

			  case 'DALARM':
				foreach($event['alarm'] as $alarmID => $alarmData) {
				  $attributes['DALARM'] = $hIcal->_exportDateTime($alarmData['time']);
				  // lets take only the first alarm
				  break;
				}
				break;

			  case 'VALARM':
				foreach($event['alarm'] as $alarmID => $alarmData) {
				  $this->mki_c_VALARM($alarmData, $vevent,
										  $event['start'], $veExportFields);
				}
				break;

			  case 'STATUS':	// note: custom field in event
				if (! $evstat = strtoupper($event['status']))
				  $evstat = 'CONFIRMED'; //default..
				$attributes['STATUS'] = $evstat; 
				break;

			  default:
				// only use default for level1 VEVENT fields
				if(strpos($veFieldName, '/') !== false)
				  break;
				// use first related field only for the simple conversion
				$efield = $this->ical2egwFields[$veFieldName][0];
				if ($event[$efield]) {	// dont write empty fields
					$attributes[$veFieldName]	= $event[$efield];
				}
				break;
			  }

		  } //end foreach

		  // wholeday detector (DTEND =23:59:59 && DTSTART = 00:00)
		  // if detected the times will be exported in VALUE=DATE format
		  if(((date('H:i:s',$event['end']) == '23:59:59') ||
			  (date('H:i:s',$event['end']) == '00:00:00')) 
			 && (date('H:i',$event['start'] == '00:00'))){
			$attributes['DTSTART'] =
			  $this->hi->_parseDate(date('Ymd',$event['start']));
			$attributes['DTEND'] =
			  $this->hi->_parseDate(date('Ymd',$event['end']+1));
			$parameters['DTEND']['VALUE'] = 'DATE';
			$parameters['DTSTART']['VALUE'] = 'DATE';
			//	error_log('WHOLE DAY DETECTED');
		  }

		  // handle created and modified field setting
		  $created = $this->get_TSdbAdd($event['id'],'calendar');
		  if (!$created && !$modified)
			$created = $event['modified'];
		  if ($created)
			$attributes['CREATED'] = $created;
		  if (!$modified)
			$modified = $event['modified'];
		  if ($modified)
			$attributes['LAST-MODIFIED'] = $modified;

		  // add all collected attributes (not yet added) to the vevent
		  foreach($attributes as $aname => $avalue) {
			$this->addAttributeOntoVevent($vevent,
											  $aname,
											  $avalue,
											  $parameters[$aname]);
		  }
		  $hIcal->addComponent($vevent);
		  $vexpcnt += 1;
		}
		
		return $vexpcnt; //return nof vevents exported
	  }




	  /**
	   * Import all VEVENTS from a Horde_iCalendar into Egw
	   *
	   * The ical VEVENTS components that are contained in de $hIcal Horde_iCalendar
	   * are converted to eGW events and imported  into the eGW calendar.
	   * Depending on the value of $importMode, the conversion will generate either eGW
	   * events with completely new id s (DUPLICATE mode) or generate ids created after
	   * the VEVENT;UID field so that VEVENTS that refer to already existing eGW events
	   * will be used to update these (OVERWRITE mode).	
	   *
	   * @section secimpumatch Uidmatching
	   * When $uid_matching is not set, the default situation, the uid field of each vevent
	   * to be imported will examined to check if it has a valid egw id encoded. If so the import
	   * will try to update the egw event indicated by this id with the contents of the vevent.
	   * When this  doesnot succeed an appropiate error or skip (if you had not enough write rights)
	   * will be the result. If there can be no valid egw id be decoded, the vevent will be considered
	   * as a new one and an hence a new egw id will automatically be produced.
	   *
	   * When $uid_matching is enabled, the value of the uid field of the vevent will matched against
	   * all the uid fields of existing egw events. If a matching egw event with id is found,
	   * the import
	   * routine will try to update this event. If no success an appropiate error will be generated.
	   * If no match is found, the import proceeds, just as without uidmatching, by generating a
	   * new egw event with a new id. The events uid field will be filled with the vevents uid,
	   * for possible later re-use.
	   * 
	   * @note  <b>Mostly it is best to disable uidmatching.</b> It prevents that multiple duplicates
	   * of a event will be created in Egw, that may not be accessible anymore via the Ical-Service
	   * interface. Only use it when you really need to reimport an already once imported calendar
	   * because you accidentally deleted parts of it in Egw. Better still would be copy these lost 
	   * events into a downloaded version of your original calendar and then update this one without
	   * the uid_matching enabled. (It has namely no effect for <i>new</i> events and the old (i.e. 
	   * already downloaded to the client) events will be recognized without uidmatching.
	   *
	   * @param    Horde_iCalendar &$hIcal   object with ical VEVENT objects 
	   * @param  string $importMode          toggle for duplicate (ICAL_IMODE_DUPLICATE)
	   *                                    or overwrite (ICAL_IMODE_OVERWRITE) import mode
	   * @param  int $cal_id           strange parameter, at least for -1  create new events
	   *                                and if 0 then always add user to participants
	   *                                JVL: THIS NEEDS TO BE CLARIFIED!
	   * @param boolean $reimport_missing_events enable the import of previously exported events
	   * that are now gone in egw (probably deleted by someone else) Default false.
	   * @return boolean| int $false|$evcnt    on error: false | on success: nof imported elms
	   * @ref $supportedFields    determins the VEVENTS that will be used for import
	   */
	  function importVEventsFromIcal(&$hIcal, $importMode='OVERWRITE', $cal_id=0,
									 $reimport_missing_events=false)
	  {
		$overwritemode = stristr($importMode,'overwrite') ? true : false;
		$evokcnt = 0;   // nof events imported ok
		$everrcnt = 0;  // nof events imported erroneous
		$evskipcnt = 0; // nof events imported skipped (user !== owner)
		$evdelcnt = 0;  // nof events deleted ok
		$evmisskipcnt =0; // nof missing event updates skipped

		$veImportFields =& $this->supportedFields;

//		error_log('veImportFields::'. print_r($veImportFields,true));

		$eidOk   = false;	// returning false, if file contains no components
		$user_id = $GLOBALS['egw_info']['user']['account_id'];

		foreach($hIcal->getComponents() as $vevent) {
		  // HANDLE ONLY VEVENTS HERE
		  if(!is_a($vevent, 'Horde_iCalendar_vevent'))
			continue; 

//		  $event = array('participants' => array());
		  $event = array('title' => 'Untitled');
		  $alarms = array();
		  unset($owner_id);
		  $evduration = false;
		  $nonegw_participants = array();

		  // handle UID field always first according to uid_matching algorithm
		  $cur_eid      = false;  // current egw event id
		  $cur_owner_id = false;  // current egw event owner id
		  $cur_event    = false;  // and the whole array of possibly correspond egw event
		  // import action description (just for fun and debug) : 
		  // NEW|NEW-NONUID|NEW-FOR-MISSING
		  // DEL-MISSING|DEL-READ|DEL-READ-UID|
		  // UPD-MISSING|UPD-READ|UPD-READ-UID 
		  $imp_action    = 'NEW-NONUID';    

		  if($uidval = $vevent->getAttribute('UID')){
			// ad hoc hack: egw hates slashes in a uid so we replace these anyhow with -
			$vuid = strtr($uidval,'/','-');
			$event['uid'] = $vuid;
			
			if(!$this->uid_matching){
			  
			  // UID_MATCHING DISABLED, try to decode cur_eid from uid
			  if ($cur_eid = $this->mke_guid2id($vuid,'calendar')){
				// yes a request to import a previously exported event!
				if ($cur_event = $this->mycal->read($cur_eid)){
				  // oke we can read the old event
				  $cur_owner_id = $cur_event['owner'];
				  $imp_action  = 'UPD-READ';
				  $event['id'] = $cur_eid;
				} elseif($reimport_missing_events){
				  // else: a pity couldnot read the corresponding cur_event,
				  // maybe it was deleted in egw already..
				  $imp_action = 'UPD-MISSING'; 
				  unset($event['id']); // import as a new one
				} else{
				  // go on with next vevent
				  $evmisskipcnt += 1;
				  continue; 
				}
			  }else{
				// no decodable egw id there, so per definition no corresponding egw event
				// so will just import the vevent as a new event
				$imp_action = 'NEW';
			  }

			  //UID_MATCHING ENABLED
			} elseif($overwritemode && $cal_id <= 0 && !empty($vuid)){
			  // go do uidmatching, search for a egw event with the vuid as uid field 
			  if ($cur_event = $this->mycal->read($vuid))	{
				$cur_eid      = $uidmatch_event['id'];
				$cur_owner_id = $uidmatch_event['owner'];
				$imp_action = 'UPD-READ-UID';
				$event['id'] = $cur_eid;
			  }else{
				// uidmatch failed, insert as new
				$imp_action = 'NEW';
			  }
			}
			
		  }

		  // lets see what other supported veImportFields we can get from the vevent
		  foreach($vevent->_attributes as $attr) {
			$attrval = $GLOBALS['egw']->translation->convert($attr['value'],'UTF-8');


			// SKIP  UNSUPPORTED VEVENT FIELDS
			if(!in_array($attr['name'],$veImportFields))
			  continue;
			
//			error_log('cnv field:' . $attr['name'] . ' val:' . $attrval);

			switch($attr['name']) {
			  // oke again these strange ALARM properties...
			case 'AALARM':
			case 'DALARM':
			  if (preg_match('/.*Z$/',$attrval,$matches))	{
				$alarmTime = $hIcal->_parseDateTime($attrval);
				$alarms[$alarmTime] = array('time' => $alarmTime);
			  }
			  break;

			case 'CLASS':
			  $event['public']		= (int)(strtolower($attrval) == 'public');
			  break;

			case 'DESCRIPTION':
			  $event['description']	= $attrval;
			  break;

			case 'DTEND':
			  // will be reviewed after all fields are collected
			  $event['end']		= $attrval;
			  break;

			  // note: DURATION and DTEND are mutually exclusive
			case 'DURATION':
			  // duration after eventstart in secs
			  $evduration = $attrval;
			  break;

			case 'DTSTART':
			  // will be reviewed after all fields are collected
			  $event['start']		= $attrval;
			  break;

			case 'LOCATION':
			  $event['location']	= $attrval;
			  break;

			case 'RRULE':
			  // we may need to find a startdate first so delegate to later
			  // by putting it in event['RECUR']
			  $event['RECUR'] = $attrval;
			  break;
			case 'EXDATE': 
			  if (($exdays = $this->mke_EXDATEpv2udays($attr['params'], $attrval))
				  !== false ){
				foreach ($exdays as $day){
				  $event['recur_exception'][] = $day;
				}
			  }
			  break;

			case 'SUMMARY':
			  $event['title']		= $attrval;
			  break;

			case 'TRANSP':
			  $event['non_blocking'] = $attrval == 'TRANSPARENT';
			  break;
			  // JVL: rewrite!
			case 'PRIORITY':
			  $event['priority'] = $this->mke_prio($attrval);
			  break;

			case 'CATEGORIES':
			  $catnames = explode(',',$attrval);
			  $catidcstr = $this->cats_names2idscstr($catnames,$user_id,'calendar');
			  $event['category'] .= (!empty($event['category']))
				? ',' . $catidcstr 	: $catidcstr;
			  break;

			  // when we encounter an new valid cal_address but not yet in egw db
			  // should we import it?
			case 'ATTENDEE':
			  if ($pid = $this->mke_CAL_ADDRESS2pid($attrval)){
				if( $epartstat = $this->mke_params2partstat($attr['params'])){
				  $event['participants'][$pid] = $epartstat;
				} elseif ($pid == $event['owner']){
				  $event['participants'][$pid] = 'A';
				} else {
				  $event['participants'][$pid] = 'U';
				}
				// egw unknown participant, add to nonegw_participants list
			  } else {
				$nonegw_participants[] =
				  $this->mke_ATTENDEE2cneml($attrval,$attr['params']);
			  }
			  break;

			  // make organizer into a accepting participant
			case 'ORGANIZER':	// make him 
			  if ($pid = $this->mke_CAL_ADDRESS2pid($attrval))
				  $event['participants'][$pid] = 'A';
			      //$event['owner'] = $pid;
			  break;

			case 'CREATED':		// will be written direct to the event
			  if ($event['modified']) break;
			  // fall through

			case 'LAST-MODIFIED':	// will be written direct to the event
			  $event['modified'] = $attrval;
			  break;

			case 'STATUS':	// note: custom field in event
			  $event['status'] = strtoupper($attrval);
			  break;

			default:
			error_log('VEVENT field:' .$attr['name'] .':'
					  . $attrval . 'HAS NO CONVERSION YET');
			}
		  } // end of fields loop
	
		  // now all fields are gathered do some checking and combinations
		  
		  // we may have a RECUR value set? Then convert to egw recur def
		  if ($recurval = $event['RECUR']){
//error_log('recurval=' . $recurval . '=');
			if(!($recur = $this->mke_RECUR2rar($recurval,$event['start'])) == false){
			  foreach($recur as $rf => $rfval){
				$event[$rf] = $rfval;
			  }
			}
			unset($event['RECUR']);
		  }

		  // build endtime from duration if dtend was not set
		  if (!isset($event['end']) && ($evduration !== false)){
			$event['end'] = $this->mke_DDT2utime($event['start']) + $evduration;
		  } 
		  
		  // a trick for whole day handling or ...??
		  if(date('H:i:s',$event['end']) == '00:00:00')
			$event['end']--;

		  // check vevent for subcomponents (VALARM only at the moment)
		  // maybe some day  do it recursively... (would be better..)
		  foreach($vevent->getComponents() as $valarm) {
			// SKIP anything but a VALARM
			if(!is_a($valarm, 'Horde_iCalendar_valarm'))
			  continue; 
			$this->upde_c_VALARM2alarms($alarms,$valarm,$user_id,$veImportFields);
		  }

		  // AD HOC solution: add nonegw participants to the description
		  // should be controlable by class member switch
		  if (count($nonegw_participants) > 0)
			$this->upde_nonegwParticipants2description($event['description'],
														   $nonegw_participants);

		  // handle fixed id call (for boical compatibility)
		  // @todo test boical compatibility (esp. with $cal_id>0 case) 
		  if($cal_id > 0)	{
			$event['id'] = $cal_id;
		  }

		  // SORRY THE PARTICPANTS HANDLING OF EGW IS NOT YET CLEAR TO ME (JVL)
		  // so I do the bold solution to add ourself to participants list if we are not on yet
		  if(!isset($event['participants'][$user_id]))
			$event['participants'][$user_id] =  'A';

 // error_log('<< ok <<<<' . 'event read for import=' . print_r($event,true));


		  // -- finally we come to the import into egw ---

		  if (($event['title'] == 'X-DELETE') || ($event['title'] == '_DELETED_')){


			// -------- DELETION --------------------
			//			error_log('delete event=' . print_r($event,true));
			$imp_action = 'DEL-' . $imp_action;
			if(! $cur_eid) {
			  $this->_errorlog_evupd('ERROR: ' . $imp_action,
									 $user_id, $event, false);
			  $everrcnt += 1; 
			  continue;
			} else {
			  // event to delete is found readable
			  if($eidOk = $this->mycal->delete($cur_eid)){
				// DELETE OK
				$evdelcnt += 1;

				// ASSUME Alarms are deleted by egw on delete of the event...
				// otherwise we should use this code:
				//  delete the old alarms
				//foreach($cur_event['alarm'] as $alarmID => $alarmData)	{
				//  $this->delete_alarm($alarmID);
				//}
				continue;
			  } elseif ($user_id != $cur_owner_id){
				// DELETE BAD  but it wasnt ours anyway so skip it
				if ($this->evdebug)
				  $this->_errorlog_evupd('SKIPPED: ' . $imp_action . ' (INSUFFICIENT RIGHTS)',
										 $user_id, $event, $cur_event);
				$evskipcnt += 1; 
				continue;
			  } else {
				// DELETE BAD and it was ours
				$this->_errorlog_evupd('ERROR: ' . $imp_action . '(** INTERNAL ERROR ? **)', 
									   $user_id, $event, $cur_event);
				$everrcnt += 1; 
				continue;
			  }

			}

			  // -------- UPDATE --------------------
		  } elseif ($eidOk = $this->mycal->update($event, TRUE)){
			// UPDATE OKE ,now update alarms
			$evokcnt += 1; // nof imported ok vevents
			// handle the found alarms
			if(in_array('VALARM',$veImportFields)){
			  // delete the old alarms for the event, note: we could also have used $cur_event
			  // but jus to be sure
			  if(!$updatedEvent = $this->mycal->read($eidOk)){
				error_log('ERROR reading event for Alarm update, will skip update..');
				continue;
			  }

			  // ******** for serious debugging only.. **************
			  //			  if ($this->evdebug){
			  //				$this->_errorlog_evupd('OK: ' . $imp_action, 
			  //									   $user_id, $event, $cur_event);
			  //error_log('event readback dump:' . print_r($updatedEvent,true));
			  //			  }
			  // ******** eof serious debugging only.. **************

			  foreach($updatedEvent['alarm'] as $alarmID => $alarmData)	{
				$this->delete_alarm($alarmID);
			  }
			  //  set new alarms 						
			  foreach($alarms as $alarm) {
				if(!isset($alarm['offset'])){
				  $alarm['offset'] = $event['start'] - $alarm['time'];
				} elseif (!isset($alarm['time'])){
				  $alarm['time'] = $event['start'] - $alarm['offset'];
				}
				$alarm['owner'] = $user_id;
//				error_log('setting egw alarm as:' . print_r($alarm,true));
				$this->save_alarm($eidOk, $alarm);
			  }
			}
			continue;

			//  ---UPDATE BAD --------
		  } elseif ($user_id != $cur_owner_id){
			// UPDATE BAD, but other ones event, so skip
			  if ($this->evdebug)
				$this->_errorlog_evupd('SKIPPED: ' . $imp_action . ' (INSUFFICIENT RIGHTS)',
									   $user_id, $event, $cur_event);
			  $evskipcnt += 1; 
			  continue;
		  } else {
			// UPDATE BAD and we own it or it was a new one
			$this->_errorlog_evupd('ERROR: ' . $imp_action . '(** INTERNAL ERROR ? **)', 
								   $user_id, $event, $cur_event);
			$everrcnt += 1; 
			continue;
		  }
		  error_log('CODING ERROR: SHOULDNOT GET HERE');
		} // for each

		if (($everrcnt > 0) || $this->evdebug)
		  error_log('** user[' . $user_id . '] vevents imports: ' . $everrcnt . ' BAD,' .
					$evskipcnt . ' skip-(insufficient rights), ' . $evmisskipcnt .
					' skip-(ignore reimport missings), ' . 
					 $evokcnt . ' upd-ok, ' . $evdelcnt . ' del-ok');
		return ($everrcnt > 0) ? false : $evokcnt+ $evdelcnt;
	  }


	  /**
	   * @private
	   * Log event update problems to http errorlog
	   * @param string $fault description of the fault type
	   * @param ind $user_id the id of the logged in user
	   * @param array $new_event the info converted from the vevent to be imported
	   * @param array|false $cur_event_ids settings of owner, id and uid field of a possibly found
	   * corresponding egw event. When no such event found: false.
	   */
	  function _errorlog_evupd($fault='ERROR', $user_id, &$new_event, $cur_event)
	  {
		// ex output:
		// ** bovevents import for user(12 [pietje]): ERROR
		// current egw event: id=24, owner=34, uid='adaafa'\n
		// vevent info event: id=24, owner=--, uid='dfafasdf'\n

		$uname =(is_numeric($user_id))
		  ? $user_id . '[' . $GLOBALS['egw']->accounts->id2name($user_id) . ']'
		  : '--';
		if ($cur_event === false){
		  $cid = $cown = $cuid = '--';
		}else{
		  $cid  = $cur_event['id'];
		  $cown = $cur_event['owner'];
		  $cuid = $cur_event['uid'];
		}
		$nid  = ($vi = $new_event['id']) ? $vi : '--';
		$nown = ($vi = $new_event['owner']) ? $vi : '--';
 		$nuid = ($vi = $new_event['uid']) ? $vi : '--';

		error_log('** bovevents import for user (' . $cur_eid .
				  '['. $uname . ']):' . $fault . '\n' .
				  'current egw event: id=' . $cid . ',owner=' . $cown . ',uid=' . $cuid .'\n' .
				  'vevent info event: id=' . $nid . ',owner=' . $nown . ',uid=' . $nuid .'\n' );
//		error_log('vevent info event dump:' . print_r($new_event,true) . '\n <<-----------<<\n');
	  }


	  /**
	   * @private
	   *
	   * Fill member var that holds the iCalendar property to Egw fields mapping.
	   *
	   * Copy keys from this var to the supportedFields member var to allow import/export
	   * of the field refered to by the key.
	   * @todo Maybe someday rethink the ical2egwFields trafo system by rewriting it in paths
	   *       starting from iCalendar/Component=>Field or iCalendar/Comp/SubComp etc.
	   * @see $ical2egwFields member var that holds the mapping
	   */
	  function _set_ical2egwFields()
	  {
		$this->ical2egwFields =
		  array(
				'UID'		=> array('uid'),
				'CLASS'		=> array('public'),
				'SUMMARY'	=> array('title'),
				'DESCRIPTION'	=> array('description'),
				'LOCATION'	=> array('location'),
				'DTSTART'	=> array('start'),
				'DTEND'		=> array('end'),
				'DURATION'  => array('end-duration'),
				'ORGANIZER'	=> array('owner'),
				'ATTENDEE'	=> array('participants'),
				'RRULE'     => array('recur_type','recur_interval','recur_data','recur_enddate'),
				'EXDATE'    => array('recur_exception'),
 				'PRIORITY'  => array('priority'),
 				'TRANSP'    => array('non_blocking'),
				'CATEGORIES'=> array('category'),
				'URL'       => array(''),
				'CONTACT'   => array(''),
				'GEO'       => array(''),
				'CREATED'   => array(''),
				'AALARM'     => array('alarms'), // NON RFC2445!!
				'DALARM'     => array('alarms'), // NON RFC2445!!
				'VALARM'     => array('alarms'),
				'VALARM/TRIGGER'     => array('alarms/time')
				);
		return true;
	  }


	  /**
	   * Set the list of ical fields that are supported during the next imports and exports.
	   *
	   * The list of iCal fields that should be converted during the following imports and exports
	   * of VEVENTS is set. This is done by providing a <i>productmanufacturer</i> name and
	   * (optionally) a <i>prductname</i>. In a small lookup table the set of currently supported
	   * fields for this is searched and then set thus in the class member @ref $supportedFields.
	   *
	   * @note <i> JVL: I can only  see sense in defining supported fields in iCal fields as
	   * these are the fields (terminology) that the devices have in common.
	   * in addressbook this approach is also --correctly-- taken. Why not here?</i>
	   * @param string $_productManufacturer a string indicating the device manufacturer
	   * @param string $_productName a further specification of the current device that is used
	   * for import or export.
	   */
	  function setSupportedFields($_productManufacturer='file', $_productName='')
	  {
		  $defaultFields =  array('CLASS','SUMMARY','DESCRIPTION','LOCATION','DTSTART',
								  'DTEND','RRULE','EXDATE','PRIORITY');
		  // not: 'TRANSP','ATTENDEE','ORGANIZER','CATEGORIES','URL','CONTACT'
		  
		  switch(strtolower($_productManufacturer))	{
		  case 'nexthaus corporation':
			switch(strtolower($_productName)){
			default:
			  // participants disabled until working correctly
			  // $this->supportedFields = array_merge($defaultFields,array('ATTENDEE'));
			  $this->supportedFields = $defaultFields;
			  break;
			}
			break;
			
			// multisync does not provide anymore information then the manufacturer
			// we suppose multisync with evolution
		  case 'the multisync project':
			switch(strtolower($_productName)) {
			case 'd750i':
			default:
			  $this->supportedFields = $defaultFields;
			  break;
			}
			break;
		  case 'sonyericsson':
			switch(strtolower($_productName)){
			default:
			  $this->supportedFields = $defaultFields;
			  break;
			}
			break;
			
		  case 'synthesis ag':
			switch(strtolower($_productName)){
			default:
			  $this->supportedFields = $defaultFields;
			  break;
			}
			break;
			// used outside of SyncML, eg. by the calendar itself ==> all possible fields
		  case 'file':	
		  case 'all':
			$this->supportedFields =
			  array_merge($defaultFields,
						  array('ATTENDEE','ORGANIZER','TRANSP','CATEGORIES',
								'DURATION','VALARM','VALARM/TRIGGER'));
//			error_log('OKE setsupportedFields (all)to:'. print_r($this->supportedFields,true));
			break;
			
			// the fallback for SyncML
		  default:
			error_log("Client not found: $_productManufacturer $_productName");
			$this->supportedFields = $defaultFields;
			break;
		  }
	  }


	  /**
	   * 
	   * Exports calendar events as an iCalendar string
	   *
	   * @note -- PART OF  calendar.boical API COMPATIBILITY INTERFACE -----------
	   * @param int/array $events (array of) cal_id or array of the events
	   * @param string $method='PUBLISH'
	   * @return string|boolean string with vCal or false on error
	   * (eg. no permission to read the event)
	   *
	   * @see _hiCal  class member to hold a temporary Horde_iCalendar object 
	   */
	  function &exportVCal($events,$version='1.0',$method='PUBLISH')
		{
		  $hIcal = &new Horde_iCalendar;
		  $euid_export = false;

		  // set some header values of the Horde_iCalendar object
		  $hIcal->setAttribute('PRODID', '-//eGroupWare//NONSGML eGroupWare Calendar '
							   . $GLOBALS['egw_info']['apps']['calendar']['version'].'//'
							   . strtoupper($GLOBALS['egw_info']['user']['preferences']['common']['lang']));
		  $hIcal->setAttribute('VERSION',$version);
		  $hIcal->setAttribute('METHOD',$method);
			
		  // convert the eGW events to VEVENTS and add them to hIcal
		  if(!$this->exportEventsOntoIcal($hIcal, $events,$euid_export))
			return false;
		  
		  // conversion oke, now let Horde stringify it and deliver as result
		  $vcal = $hIcal->exportvCalendar();
		  // JVL:  destroy the object by hand  or does automagic this in php ?
		  $hIcal = null;

		  return $vcal;

		}



	  /** 
	   * Convert VEVENT components from an iCalendar string into eGW calendar events
	   * and write these to the eGW calendar as new events or changes of existing events
	   *
	   * @note -- PART OF  calendar.boical API COMPATIBILITY INTERFACE -----------
	   * @param string $_vcalData     ical data string to be imported 
	   * @param int $cal_id      id of the eGW event to fill with the VEvent data      
	   *    when -1 import the VEvent content to new EGW  events
	   *  (JVL HACK  when 0 allow change but no deletion user is added to participants
	   *    if needed) 
	   * @return boolean $ok  false on failure | true on success
	   */
	  function importVCal($_vcalData, $cal_id=-1)
	  {

		$hIcal = &new Horde_iCalendar;
		// our (patched) horde classes, do NOT unfold folded lines, 
		// which causes a lot trouble in the import, so we do it here
		$_vcalData = preg_replace("/[\r\n]+ /",'',$_vcalData);
		
		// let the Horde_iCalendar object parse the Vcal string into its components
		if(!$hIcal->parsevCalendar($_vcalData)){
		  return FALSE;
		}
		$importMode = 'OVERWRITE';
		
		// now import the found VEVENTS into eGW calendar
		if(!$this->importVEventsFromIcal($hIcal, $importMode, $cal_id))
		  {
			//error_log('importVCal(): errors in importVEventsFromIcal');
			$hIcal = null;
			return false;
		  }

		$hIcal = null;
		return true;
	  }


	}


?>
