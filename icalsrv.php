<?php
  /* WARNING: EXPERIMENTAL CODE DO NOT USE FOR PRODUCTION  */
  /**
   * @file 
   *  Icalsrv: Export and Import Egw events and task as ICalendar over http
   *
   * @desc purp egw remote ical-over-http server interface that can import and export vevents,
   * and vtodos. Possible clients include Mozilla Calendar/Sunbird, Korganizer, Apple Ical
   * and Evolution. 
   * @note <b> THIS IS STILL EXPERIMENTAL CODE </b> do not use in production.
   * @note this script is supposed to be at:  egw-root/icalsrv.php
   *
   * @version 0.9.02 
   * @date 20060214
   * @author Jan van Lieshout <jvl (at) xs4all.nl> Rewrite and extension for egw 1.2. 
   * (see: http://www.egroupware.org )
   *
   * Based on some code from:
   * @author   RalfBecker@outdoor-training.de (some original code base)
   * @author   bbeckmann (at) optaros.com (some modificationas)
   * @author   l.tulipan (at) mpwi.at (Additional Modifications for egw 1.2).
   *
   * @license
   *  This program is free software; you can redistribute it and/or modify it
   *  under the terms of the GNU General Public License as published by the
   *  Free Software Foundation; either version 2 of the License, or (at your
   *  option) any later version.
   * 
   * @since 0.9.00 use of the new egwical class for iCalendar handling with WURH
   * according to WURH pattern
   */

  // TODO list:
  // - incorporate this icalsrv.php script in standard egroupware access url
  // - make this 'ical-service' enabled/disabled from  the egw admin interface
  // - make some parameters of this 'ical-service' interface user specific configurable
  //  for example the vevent and vtodo export period (define search filters..)
  // overwrite or duplicate mode etc.
  //  Build a html interface for this. I guess this goes into $GLOBALS....['preferences']...
  // CAN SOMEBODY HELP WITH THIS? (not my piece of pudding ....)


#$logdir = false; // set to false for no logging
$logdir = '/tmp'; // set to a valid (writable) directory to get log file generation

#  WHY THIS? IS IT NEEDED?
$GLOBALS['phpgw_info'] =
  array('flags' => array('currentapp' => 'calendar',
							 'noheader'   => True,
							 'nofooter'   => True,
							 ),);

$GLOBALS['phpgw_info']['flags']['currentapp'] = 'login';
$GLOBALS['phpgw_info']['flags']['noapi'] = True;
include ('./header.inc.php');

include ('./phpgwapi/inc/functions.inc.php');

$GLOBALS['egw_info']['flags']['currentapp'] = 'calendar';


// oke there we go .....

// exit on non authenticated http request
if ((!isset($_SERVER['PHP_AUTH_USER']))	||
	(!$GLOBALS['egw']->auth->authenticate($_SERVER['PHP_AUTH_USER'],
											$_SERVER['PHP_AUTH_PW']))) {
  header('WWW-Authenticate: Basic realm="ICal Server"');
  header('HTTP/1.1 401 Unauthorized');
  exit;
 }

$user = $GLOBALS['egw']->accounts->name2id($_SERVER['PHP_AUTH_USER']);
$pw   = $GLOBALS['egw']->preferences->account_id = $user;

#  WHY THIS? IS IT NEEDED?
$GLOBALS['egw_info']['user']['preferences'] =
  $GLOBALS['egw']->preferences->read_repository();

$GLOBALS['egw_info']['user']['account_id'] = $user;
$GLOBALS['egw_info']['user']['account_lid'] = $_SERVER['PHP_AUTH_USER'];


/* WARNING:
 * SET $euid_export ONLY TO TRUE IF YOU NEED IT TO USE THE EXPORTED ICAL INFO
 * SEPARATED FROM EGW, IN A CONTEXT WHERE IT ORIGINATED FROM. SO NORMALLY LEAVE IT
 * TO FALSE IF WANT TO USE YOUR CLIENT REGULARY (TO SUBSCRIBE/PUBLISH TO) WITH EGW
 */
$euid_export = false;


/* select mode for importing the ical components,
 * safeMode=-1 -> allows new creation and deletion (in combi with $importMode = 'OVERWRITE')
 *          0 -> allow new creation and change, but no deletion?
* WARNING:
 * USE safeMode = -1 WILL DELETE EXISTING events WHEN NO VALID ATTENDEES ARE
 * GIVEN IN THE  RELATED ICAL IMPORTED VEVENT !!
 *   ---!!! USE WITH CARE OR YOUR DATA GETS LOST!! ---------
 */
$safeMode = 0;

$importMode = 'OVERWRITE';  // OVERWRITE or DUPLICATE

$logmsg = "";


// IO-0 setup an Egwical object, and add a Calendar and a Infolog Resource
// these will do all the work
$ei =& CreateObject('egwical.egwical');
// for free an Horde_iCalendar object that we may use to collect or convert ical elements
$hIcal = $ei->hi; 
$boc =& CreateObject('calendar.bocalupdate');
$binf =& CreateObject('infolog.boinfolog');

//calendar_vevents_handler: this will allow $ei to convert events<->vevents and
// store and retrieve them from the egw db
$cvehnd = $ei->addRsc($boc);  
//infolog_vtodos_handler: this will allow $ei to convert tasks<->vtodos and
// store and retrieve them from the egw db
$ivthnd = $ei->addRsc($binf); 



// oke now process the http request...

if ($_SERVER['REQUEST_METHOD'] == 'PUT') {

  // *** PUT Request so do an Import *************

  // I0 read the payload
  $logmsg = 'IMPORTING in '. $importMode . ' mode';
  $fpput = fopen("php://input", "r");
  $putData = "";
  while ($data = fread($fpput, 1024))
	$putData .= $data;
  fclose($fpput);


  // I1: parse $putData using the egwical builtin ical parser
  $hIcal =& $ei->parsevCalendar($putData);

  if(!$hIcal){
	$msg ="icalsrv.php: error parsing iCal import data";
	fail_exit($msg);
  }
  $logmsg .= "\n parsed iCalendar data:" . count($hIcal->_components) . " components found";
  

  // I2: now import possibly found VEVENTS into eGW calendar
  $vcnt = $cvehnd->importVEventsFromIcal($hIcal, $importMode, $safeMode); 

  if($vcnt === false){
	$msg = 'icalsrv.php:  VEVENTS import: ERRORS';
	fail_exit($msg);
  } else{
	$logmsg .= "\n imported " . $vcnt ." VEVENTS: OK";
  }

  // I3: now import possibly found VTODOS into eGW calendar
  $tcnt = $ivthnd->importVTodosFromIcal($hIcal, $importMode); 
  if ($tcnt === false){
	$msg = 'icalsrv.php:  VTODOS import: ERRORS';
	fail_exit($msg);
  }
  $logmsg .= "\n imported " . $tcnt . " VTODOS: OK ";
  
  if($logdir) log_ical($logmsg,"import",$putData);
  

 } else {

  // *** GET (or POST?) Request
  $logmsg = 'EXPORTING';

  //   Get events from 3 years (last year, current, next), rather silly..  */
  $last_year = date("Y")-1;
  $next_year = date("Y")+1;

  // E1.1: get period to be exported for events 
  // For productivity this should be user configurable e.g. to be set via some sort of user
  // preferences to be set via eGW. (config remote-iCalendar...)
  $events_query = array('start' => $last_year . "-01-01",
				 'end'   => $next_year . "-12-31",
				 'enum_recuring' => false,
				 'daywise'       => false,
				 'owner'         => $GLOBALS['egw_info']['user']['account_id'],
				 // timestamp in server time for boical class
				 'date_format'   => 'server'
				);
  $todos_query = array('col_filter' => array('type' => 'task'),
                       'filter' => 'none',
					   'order' => 'id_parent'
					   );

  // E2.1: search eGW events based on the $events_query 
  $events =  & $boc->search($events_query);
  if (!$events){
    $logmsg .="\n no eGW events found to export";
    // nevertheless fall through to further ical components export
  } else {
    $logmsg .=  "\n found " . count($events) . " eGW events to export";

    //E2.2 convert the found eGW events and add them to the iCal container
    $vcnt = $cvehnd->exportEventsOntoIcal($hIcal, $events, $euid_export);
	if($vcnt === false){
	  $msg = 'icalsrv.php:  eGW to VEVENTS conversion: ERRORS';
	  fail_exit($msg);
	} else {
	  $logmsg .= "\n exported the eGW events as " . $vcnt . " VEvents: OK ";
    }
    $bove = null; //destroy object
  }

  // E3.1: search eGW todos based on the $todos_query 

  $todos =& $binf->search($todos_query);
  if (!$todos){
    $logmsg .="\n no eGW todos found to export";
    // nevertheless fall through to further ical components export
  } else {
    $logmsg .=  "\n found " . count($todos) . " eGW todos to export";

    //E3.2 convert the found eGW todos and add them to the iCal container
    $tcnt = $ivthnd->exportTodosOntoIcal($hIcal, $todos, $euid_export);
	if ($tcnt === false){
	  $msg = 'icalsrv.php:  eGW to VTODOS conversion: ERRORS';
	  fail_exit($msg);
    } else {
	  $logmsg .= "\n exported the eGW todos as " . $tcnt . " VTODOS: OK ";
     } 
	  $bovt = null; //destroy object
  }


  // E4.1: add good vcal header info to iCal object
  $hIcal->setAttribute('PRODID', '-//eGroupWare//NONSGML eGroupWare Calendar '
			     . $GLOBALS['egw_info']['apps']['calendar']['version'].'//'
			     . strtoupper($GLOBALS['egw_info']['user']['preferences']['common']['lang']));
  $hIcal->setAttribute('VERSION','2.0');
  $hIcal->setAttribute('METHOD','PUBLISH');
  
  // now let Horde stringify it and deliver as result
  $content = $hIcal->exportvCalendar();
  echo $content;
  // DONE exporting
  if($logdir) log_ical($logmsg,"export",$content);
 
 }

$GLOBALS['egw']->common->egw_exit();



// --- SOME UTILITY FUNCTIONS -------

/**
 * Exit with an error message in html
 * @param $msg string
 *      message that gets return as html error description
 */
function fail_exit($msg){
  // log the error in the http server error logging files
  error_log($msg);
  // return http error 403 can this be done this way?
  header('HTTP/1.1 403' . $msg);
  $GLOBALS['egw']->common->egw_exit();
}

/* dummy fail_exit */
function fail_exit($msg){
  // log the error in the http server error logging files
  error_log($msg);
  return;
}



/*
 * Log info and data to logfiles if logging is set 
 *
 * @param $msg  string with loginfo
 * @param $data data to be logged
 * @param $icalmethod $string value can be import or export 
 * @global $logdir string/boolean log directory. Set to false to disab logging
 */
function log_ical($msg,$icalmethod="data",$data){

  global $logdir;
  if (!$logdir)
	return; // loggin seems off

  // some info used for logging
  $logstamp = date("U");
  $loguser = $_SERVER['PHP_AUTH_USER'];
  $logdate = date("Ymd:His");
  // filename for log info, only used when logging is on
  $fnloginfo = "$logdir/ical.log"; 

  // log info
  $fnlogdata = $logdir . "/ical." . $icalmethod . '.' . $logstamp . ".ics";
  $fp = fopen("$fnloginfo",'a+');
  fwrite($fp,"\n\n$loguser on $logdate : $msg, \n data in $fnlogdata ");
  fclose($fp);
  // log data
  $fp = fopen("$fnlogdata", "w");
  fputs($fp, $data);
  fclose($fp);
}




?>