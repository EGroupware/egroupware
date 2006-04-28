<?php
  /* WARNING: EXPERIMENTAL CODE DO NOT USE FOR PRODUCTION  */
  /**
   * @file 
   *  IcalsrvNG: Export and Import Egw events and task as ICalendar over http using
   *  Virtual Calendars
   *
   * Possible clients include Mozilla Calendar/Sunbird, Korganizer, Apple Ical
   * and Evolution. 
   * @note <b> THIS IS STILL EXPERIMENTAL CODE </b> do not use in production.
   * @note this script is supposed to be at:  egw-root/icalsrv.php
   * 
   * @version 0.9.37-ng-a1 removed fixed default domain authentication
   * @date 20060427
   * @since 0.9.36-ng-a1 first version for NAPI-3.1 (write in non owner rscs)
   * @author Jan van Lieshout <jvl (at) xs4all.nl> Rewrite and extension for egw 1.2. 
   * (see: @url http://www.egroupware.org  )
   * $Id$
   * Based on some code from:
   * @author   RalfBecker@outdoor-training.de (some original code base)
   *
   * <b>license:</b><br>
   *  This program is free software; you can redistribute it and/or modify it
   *  under the terms of the GNU General Public License as published by the
   *  Free Software Foundation; either version 2 of the License, or (at your
   *  option) any later version.
   * 
   * @todo make this 'ical-service' enabled/disabled from the egw
   * admin interface
   * @todo make the definition of virtual calendars possible from a 'ical-service' web
   * user interface user
   * @bug if you dont have enough privilages to access a personal calendar of someone
   * icalsrv will not give you an access denied error, but will just return no events
   * from this calendar. (Needed otherwise you cannot collect events from multiple resources
   * into a single virtual calendar.
   *
   * @todo make code robust against xss attacke etc.
   */

  //-------- basic operation configuration variables ----------

$logdir = false; // set to false for no logging
#$logdir = '/tmp'; // set to a valid (writable) directory to get log file generation

// set to true for debug logging to errorlog
#$isdebug = True;
$isdebug = False;

/** Disallow users to import in non owned calendars and infologs
 * @var boolean $disable_nonowner_import
 */
$disable_nonowner_import = false;

// icalsrv variant with session setup modeled after xmlrpc.php

$GLOBALS['egw_info'] = array();
$GLOBALS['egw_info']['flags'] =
  array(
		'currentapp'            => 'login',
		'noheader'              => True,
		'disable_Template_class' => True
		);
include('header.inc.php');

// silly for now but who knows...
$GLOBALS['egw_info']['server']['icalsrv'] = true;

/** Control and status of the icalsrv session setup
 * @bug icalsrv enabled checking is not yet working...
 * @var array $icalsrv
 */
$icalsrv = array();

// Somehow check if icalsrv is enabled none of the 2 ways works yet..
// either via 1:
$icalsrv['enabled'] = isset($GLOBALS['egw_info']['user']['apps']['icalsrv']);
// or via 2: the configdata
$c =& CreateObject('phpgwapi.config','icalsrv');
$c->read_repository();
$config =& $c->config_data;
unset($c);
$icalsrv['enabled'] = $config['icalsrv_enabled'];

// or via 3: force it! Yes this works :-)
$icalsrv['enabled'] = true;

if(!$icalsrv['enabled']) {
  fail_exit('IcalSRV not enabled','403');
 }

// now check if we have a session there (according to cookie and auth)
// define this function ourselves if not there..
if(!function_exists('getallheaders')) {
	function getallheaders(){
	  settype($headers,'array');
	  foreach($_SERVER as $h => $v)	{
		if(ereg('HTTP_(.+)',$h,$hp)){
		  $headers[$hp[1]] = $v;
		}
	  }
	  return $headers;
	}
 }
$headers = getallheaders();
$auth_header = $headers['Authorization']
  ? $headers['Authorization'] : $headers['authorization'];
if(eregi('Basic *([^ ]*)',$auth_header,$auth))  {
  list($sessionid,$kp3) = explode(':',base64_decode($auth[1]));
  //	echo "auth='$auth[1]', sessionid='$sessionid', kp3='$kp3'\n";
 } else {
  $sessionid = get_var('sessionid',array('COOKIE','GET'));
  $kp3 = get_var('kp3',array('COOKIE','GET'));
 }

if($icalsrv['session_ok'] = $GLOBALS['egw']->session->verify($sessionid,$kp3)){
  $s_user_id = $GLOBALS['egw_info']['user']['account_id'];
  // check if the new user is the one from the session
  $a_user_id = $GLOBALS['egw']->accounts->name2id($_SERVER['PHP_AUTH_USER']);
  if( !($a_user_id == $s_user_id)){
	$icalsrv['session_ok'] = false;
  }
 } else {
  if($isdebug)
	error_log('NO OLD SESSION');
 }

if (!$icalsrv['session_ok'] and isset($_SERVER['PHP_AUTH_USER'])
	and isset($_SERVER['PHP_AUTH_PW'])) {
  //  $login = $_SERVER['PHP_AUTH_USER'];
  //  $domain = 'default';
  // check for a possible valid login domain present as parameter
  if(isset($_GET['domain'])){
	$domain = $_GET['domain'];	 
  }else{
	$domain = $GLOBALS['egw_info']['server']['default_domain'];
  }
  if(!array_key_exists($domain, $GLOBALS['egw_domain'])){
	error_log('icalsrv.php: login, invalid domain:' .$domain);
  } else {
	$userlogin = $_SERVER['PHP_AUTH_USER'] . '@' . $domain;
	if($isdebug)
	  error_log('TRY NEW SESSION FOR login:' . $userlogin);

	$sess_id = $GLOBALS['egw']->session->create($userlogin, $_SERVER['PHP_AUTH_PW'],
											  'text');
  }
  if ($sess_id)	{
	$icalsrv['session_ok'] = true;
	$GLOBALS['egw_info']['user']['account_id'] = $sess_id->account_id;
  } 
 }

if($icalsrv['session_ok']){
  $icalsrv['authed'] = $GLOBALS['egw']->auth->authenticate($_SERVER['PHP_AUTH_USER'],
														   $_SERVER['PHP_AUTH_PW']);
 }

// bad session or bad authentication so please re-authenticate..
if (!($icalsrv['session_ok'] && $icalsrv['authed'])) {
  header('WWW-Authenticate: Basic realm="ICal Server"');
  header('HTTP/1.1 401 Unauthorized');
  exit;
 }


// oke we have a session!

// now set the variables that will control the working mode of icalvircal
// the defines are in the egwical_resourcehandler sourcefile
require_once EGW_SERVER_ROOT. '/egwical/inc/class.egwical_resourcehandler.inc.php' ;

/** uid  mapping export  configuration switch
 * @var int
 * Parameter that determines, a the time of export from Egw (aka dowload by client), how 
 * ical elements (like VEVENT's) get their uid fields filled, from data in
 * the related Egroupware element.
 * See further in @ref secuidmapping in the egwical_resourcehandler documentation.
 */
$uid_export_mode = UMM_ID2UID;

/** uid  mapping import  configuration switch
 * @var int
 * Parameter that determines, at the time of import into Egw (aka publish by client), how
 * ical elements (like VEVENT's) will find, based on their uid fields,  related egw
 * elements, that are then updated with the ical info.
 * See further in @ref secuidmapping in the egwical_resourcehandler documentation.
 */
$uid_import_mode = UMM_UID2ID;

/** 
 * @section secisuidmapping Basic Possible settings of UID to ID mapping.
 *
 * @warning the default setting in icalsrv.php is one of the 3 basic uid mapping modes:
 * #The standard mode that allows a published client calendar to add new events and todos
 *  to the egw calendar, and allows to update already before published (to egw) and
 *  at least once downloaded (from egw) events and todos.
 *  .
 *  setting: <PRE>$uid_export_mode = UMM_ID2UID; $uid_import_mode = UMM_UID2ID; </PRE> (default)
 * #The fool proof mode that will prevent accidental change or deletion of existing
 *  egw events or todos. Note that the price to pay is <i>duplication</i> on republishing or
 *  re-download!
 *  .
 *  setting: <PRE>$uid_export_mode = UMM_NEWUID; $uid_import_mode = UMM_NEWID; </PRE> (discouraged)
 * #The flaky sync mode that in principle would make each event and todo recognizable by
 *  both the client and egw at each moment. In this mode a once given uid field is both used
 *  in the client and in egw. Unfortunately there are quite some problems with this, making it
 *  very unreliable to use!
 *  .
 *  setting: <PRE>$uid_export_mode = UMM_UID2UID; $uid_import_mode = UMM_UID2UID; </PRE> (discouraged!)
 */

/** allow elements gone(deleted) in egw to be imported again from client
 * @var boolean $reimport_missing_elements
 */
$reimport_missing_elements = true;


//-------- end of basic operation configuration variables ----------


#error_log('_SERVER:' . print_r($_SERVER, true));


// go parse our request uri
$requri = $_SERVER['REQUEST_URI'];
$reqpath= $_SERVER['PATH_INFO'];
$reqagent = $_SERVER['HTTP_USER_AGENT'];

# maybe later also do something with content_type?
# if (!empty($_SERVER['CONTENT_TYPE'])) {
#    if (strpos($_SERVER['CONTENT_TYPE'], 'application/vnd....+xml') !== false) {
# ical/ics ???


// ex1: $requri='egroupware/icalsrv.php/demouser/todos.ics'
//   then $reqpath='/demouser/todos.ics'
//        $rvc_owner='demouser'
//        $rvc_basename='/todos.ics'
// ex2:or $recuri ='egroupware/icalsrv.php/uk/holidays.ics'
//   then $reqpath='/uk/holidays.ics'
//        $rvc_owner = null;    // unset
//        $rvc_basename=null;   // unset
// ex3: $requri='egroupware/icalsrv.php/demouser/todos?pw=mypw01'
//   then $reqpath='/demouser/todos.ics'
//        $rvc_owner='demouser'
//        $rvc_basename='/todos.ics'
//        $_GET['pw'] = 'mypw01'

// S-- parse the $reqpath  to get $reqvircal names
unset($reqvircal_owner);
unset($reqvircal_owner_id);
unset($reqvircal_basename);

if(empty($_SERVER['PATH_INFO'])){
  // no specific calendar requested, so do default.ics
  $reqvircal_pathname = '/default.ics';

  // try owner + base for a personal vircal request 
 } elseif (preg_match('#^/([\w]+)(/[^<^>^?]+)$#', $_SERVER['PATH_INFO'], $matches)){
   $reqvircal_pathname = $matches[0];  	  
   $reqvircal_owner = $matches[1];
   $reqvircal_basename = $matches[2];

   if(!$reqvircal_owner_id = $GLOBALS['egw']->accounts->name2id($reqvircal_owner)){
	 // owner is unknown, so forget about personal calendar

	 unset($reqvircal_owner);
	 unset($reqvircal_basename);
   }

   // check for decent non personal path
 } elseif (preg_match('#^(/[^<^>]+)$#', $_SERVER['PATH_INFO'], $matches)){
   $reqvircal_pathname = $matches[0];  	  

   // just default to standard path
 } else {
  $reqvircal_pathname = 'default.ics';
 }


if($isdebug)		   
  error_log('http-user-agent:' . $reqagent .
			',pathinfo:' . $reqpath . ',rvc_pathname:' . $reqvircal_pathname .
			',rvc_owner:' . $reqvircal_owner . ',rvc_owner_id:' . $reqvircal_owner_id .
			',rvc_basename:' . $reqvircal_basename);

// S1A search for the requested calendar in the vircal_ardb's 
if(is_numeric($reqvircal_owner_id)){
  // check if the requested personal calender is provided by the owner..

  /**
   * @todo 1. create somehow the list of available personal vircal arstores
   * note: this should be done via preferences and read repository, but how....
   * I have to find out and write it...
   */

  // find personal database of (array stored) virtual calendars
  $cnmsg = 'calendar [' . $reqvircal_basename . '] for user [' . $reqvircal_owner . ']';
  $vo_personal_vircal_ardb =& CreateObject('icalsrv.personal_vircal_ardb', $reqvircal_owner_id);
  if(!(is_object($vo_personal_vircal_ardb))){
	   error_log('icalsrv.php: couldnot create personal vircal_ardb for user:' . $reqvircal_owner);
	   fail_exit('couldnot access' . $cnmsg, '403');
  }

  // check if a /<username>/list.html is requested
  if ($reqvircal_basename == '/list.html'){
	echo $vo_personal_vircal_ardb->listing(1);
	$GLOBALS['egw']->common->egw_exit();
  }

#  error_log('vo_personal_vircal_ardb:' . print_r($vo_personal_vircal_ardb->calendars, true));

  // search our calendar in personal vircal database
  if(!($vircal_arstore = $vo_personal_vircal_ardb->calendars[$reqvircal_basename])){
	   error_log('icalsrv.php: ' . $cnmsg . ' not found.');
	   fail_exit($cnmsg . ' not found.' , '404');
  }
  // oke we have a valid personal vircal in array_storage format!

 } else {
  // check if the requested system calender is provided by system
  $cnmsg = 'system calendar [' . $reqvircal_pathname . ']';  
  /**
   * @todo 1. create somehow the list of available system vircal
   * arstores note: this should be done via preferences and read
   * repository, but how.... I have to find out
   */

  // find system database of (array stored) virtual calendars
  $system_vircal_ardb = CreateObject('icalsrv.system_vircal_ardb');
  if(!(is_object($system_vircal_ardb))){
	   error_log('icalsrv.php: couldnot create system vircal_ardb');
	   fail_exit('couldnot access ' . $cnmsg, '403');
  }

  // check if a /list.html is requested
  if ($reqvircal_pathname == '/list.html'){
	echo $system_vircal_ardb->listing(1);
	$GLOBALS['egw']->common->egw_exit();
  }

  // search our calendar in system vircal database
  if(!($vircal_arstore = $system_vircal_ardb->calendars[$reqvircal_pathname])){
	fail_exit($cnmsg . ' not found', '404');
  }
  // oke we have a valid system vircal in array_storage format!

 }
if($isdebug)
  error_log('vircal_arstore:' . print_r($vircal_arstore, true));

// build a virtual calendar with ical facilities from the found vircal
// array_storage data
$icalvc =& CreateObject('icalsrv.icalvircal');
if(! $icalvc->fromArray($vircal_arstore)){
  error_log('icalsrv.php: ' . $cnmsg . ' couldnot restore from repository.' );
  fail_exit($cnmsg . ' internal problem ' , '403');
 }

// YES: $icalvc created ok! acces rights needs to be checked though!

// HACK: ATM basic auth is always needed!! (JVL) ,so we force icalvc into it
$icalvc->auth = ':basic';


// check if the virtual calendar demands authentication
if(strpos($icalvc->auth,'none') !== false){
  // no authentication demanded so continue

 } elseif(strpos($icalvc->auth,'basic') !== false){
   //basic http authentication demanded
   //so exit on non authenticated http request

   //-- As we atm only allow authenticated users the
   // actions in  the next lines are already done at the begining
   // of this file --
//    if ((!isset($_SERVER['PHP_AUTH_USER']))	||
//  	   (!$GLOBALS['egw']->auth->authenticate($_SERVER['PHP_AUTH_USER'],
//  											 $_SERVER['PHP_AUTH_PW']))) {
// 	 if($isdebug)
// 	   error_log('SESSION IS SETUP, BUT AUTHENTICATE FAILED'.$_SERVER['PHP_AUTH_USER'] );
//  	 header('WWW-Authenticate: Basic realm="ICal Server"');
//  	 header('HTTP/1.1 401 Unauthorized');
//  	 exit;
//    }

//    // else, use the active basic authentication to set preferences
//    $user_id = $GLOBALS['egw']->accounts->name2id($_SERVER['PHP_AUTH_USER']);
//    $GLOBALS['egw_info']['user']['account_id'] = $user_id;
//    error_log(' ACCOUNT SETUP FOR'
// 			 . $GLOBALS['egw_info']['user']['account_id']);


 } elseif(strpos($icalvc->auth,'ssl') !== false){
   // ssl demanded, check if we are in https authenticated connection
   // if not redirect to https
   error_log('icalsrv.php:' . $cnmsg . ' demands secure connection');
   fail_exit($cnmsg . ' demands secure connection: please use https', '403');

 } else {
  error_log('*** icalsrv.php:' . $cnmsg . ' requires unknown authentication method:'
			. $icalcv->auth);
  fail_exit($cnmsg . ' demands unavailable authentication method:'
			 . $icalcv->auth, '403');
 }


/** 
 * @todo this extra password checkin should, at least for logged-in users,
 * better be incorporated in the ACL checkings. At some time...
 */
// check if an extra password is needed too
if(strpos($icalvc->auth,'passw') !== false){
   //extra parameter password authentication demanded
   //so exit if pw parameter is not valid
   if ((!isset($_GET['password']))	||
	   (!$icalvc->pw !== $_GET['password']) ) {
	 error_log('icalsrv.php:' . $cnmsg . ' demands extra password parameter');
	 fail_exit($cnmsg . ' demands extra password parameter', '403');
   }
 }

// now we are authenticated  enough
// go setup import and export mode in our ical virtual calendar

$icalvc->uid_mapping_export = $uid_export_mode; 
$icalvc->uid_mapping_import = $uid_import_mode; 
$icalvc->reimport_missing_elements = $reimport_missing_elements;
$logmsg = "";

// oke now process the actual import or export to/from icalvc..
if ($_SERVER['REQUEST_METHOD'] == 'PUT')  {
  // *** PUT Request so do an Import *************
 
  if($isdebug)
	error_log('icalsrv.php: importing, by user:' .$GLOBALS['egw_info']['user']['account_id']
			  . ' for virtual calendar of: ' . $reqvircal_owner_id);
  // check if importing in not owned calendars is disabled
  if($reqvircal_owner_id
	 && ($GLOBALS['egw_info']['user']['account_id'] !== $reqvircal_owner_id)){
	if($disable_nonowner_import){
	  error_log('icalsrv.php: importing in non owner calendars currently disabled');
	  fail_exit('importing in non owner calendars currently disabled', '403');
	}
  }
  if(isset($reqvircal_owner_id) && ($reqvircal_owner_id < 0)){
	error_log('icalsrv.php: importing in group calendars not allowed');
	fail_exit('importing in groupcalendars is not allowed', '403');
  }

  // I0 read the payload
  $logmsg = 'IMPORTING in '. $importMode . ' mode';
  $fpput = fopen("php://input", "r");
  $vcalstr = "";
  while ($data = fread($fpput, 1024)){
	$vcalstr .= $data;
  }
  fclose($fpput);
  
  // import the icaldata into the virtual calendar
  // note: ProductType is auto derived from $vcalstr
  $import_table =& $icalvc->import_vcal($vcalstr);
  
  // count the successes..
  if ($import_table === false) {
	$msg = 'icalsrv.php:  importing '. $cnmsg . ' ERRORS';
	fail_exit($msg,'403');
  } else {
	$logmsg .= "\n imported " . $cnmsg . ' : ';
	foreach ($import_table as $rsc_class => $vids){
	  $logmsg .=  "\n   resource: " . $rsc_class . ' : ' . count($vids) .' elements OK';
	}

  } 
  // DONE importing
  if($logdir) log_ical($logmsg,"import",$vcalstr);

  // handle response ...
  $GLOBALS['egw']->common->egw_exit();

 } else  {

  // *** GET (or POST?) Request so do an export
  $logmsg = 'EXPORTING';
  // derive a ProductType from our http Agent and set it in icalvc
  $icalvc->deviceType = egwical_resourcehandler::httpUserAgent2deviceType($reqagent);

  // export the data from the virtual calendar
  $vcalstr = $icalvc->export_vcal();

  // handle response
  if ($vcalstr === false) {
	$msg = 'icalsrv.php:  exporting '. $cnmsg . ' ERRORS';
	fail_exit($msg,'403');
  } else {
	$logmsg .= "\n exported " . $cnmsg ." : OK ";
  } 
  // DONE exporting

  if($logdir) log_ical($logmsg,"export",$vcalstr);
  // handle response ...
  $content_type = egwical_resourcehandler::deviceType2contentType($icalvc->deviceType);
  if($content_type){
	header($content_type);
  }
  echo $vcalstr;
  $GLOBALS['egw']->common->egw_exit();
 
 }



// // --- SOME UTILITY FUNCTIONS -------

/**
 * Exit with an error message in html
 * @param $msg string
 *      message that gets return as html error description
 */
function fail_exit($msg, $errno = '403')
{
  // log the error in the http server error logging files
  error_log('resp: ' . $errno . ' ' . $msg);
  // return http error $errno can this be done this way?
  header('HTTP/1.1 '. $errno . ' ' . $msg);
#  header('HTTP/1.1 403 ' . $msg);
  $GLOBALS['egw']->common->egw_exit();
}




/*
 * Log info and data to logfiles if logging is set 
 *
 * @param $msg  string with loginfo
 * @param $data data to be logged
 * @param $icalmethod $string value can be import or export 
 * @global $logdir string/boolean log directory. Set to false to disab logging
 */
function log_ical($msg,$icalmethod="data",$data)
{
  global $logdir;
  if (!$logdir)	return; // loggin seems off

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