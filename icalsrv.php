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
   * NEW RalfBecker Aug 2007
   * many modifications to improve the support of (at least) lightning
   * - changed default uid handling to UID2UID (means keep them unchanged), as the other
   *   modes created doublicates on client and server, as the client did not understand
   *   that the server changes his uid's (against the RFC specs).
   * - ability to delete events (not yet InfoLogs!), by tracking the id's of the GET request 
   *   of the client and deleting the ones NOT send back to the server in PUT requests
   * - added etag handling to allow to reject put requests if the calendar is not up to date
   *   (HTTP_IF header with etag in client PUT requests) and to report unmodified calendars
   *   to the client (HTTP_IF_NONE_MATCH header with etag gets 304 Not modified response)
   * - returning 501 Not implemented response, for WebDAV/CalDAV request (eg. PROPFIND), to 
   *   let the client know we dont support it
   * - ability to use contacts identified by their mail address as participants (mail addresses
   *   which are no contacts still get written to the description!)
   * - support uid for InfoLog (requires InfoLog version >= 1.5)
   * @version 0.9.37-ng-a2 added a todo plan for v0.9.40
   * @date 20060510
   * @since 0.9.37-ng-a1 removed fixed default domain authentication
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
   * @todo (for 0.9.40 versions) move much parsing of the vc to class.vcsrv.inc.php
   * and add the $vcpath var where pathinfo is parsed to communicate to vc_X class
   * @bug if you dont have enough privilages to access a personal calendar of someone
   * icalsrv will not give you an access denied error, but will just return no events
   * from this calendar. (Needed otherwise you cannot collect events from multiple resources
   * into a single virtual calendar.
   *
   * @todo make code robust against xss attacke etc.
   */

	//-------- basic operation configuration variables ----------

	$logdir = False; // set to false for no logging
	#$logdir = '/tmp'; // set to a valid (writable) directory to get log file generation

	// set to true for debug logging to errorlog
	//$isdebug = True;
	$isdebug = False;

	/** Disallow users to import in non owned calendars and infologs
	* @var boolean $disable_nonowner_import
	*/
	$disable_nonowner_import = false;

	// icalsrv variant with session setup modeled after xmlrpc.php

	//die(print_r($_COOKIE, true));
	$icalsrv = array();

	$GLOBALS['egw_info'] = array();
	$GLOBALS['egw_info']['flags'] = array(
		'currentapp' => 'login',
		'noheader'   => True,
		'nonavbar'   => True,
		'disable_Template_class' => True
	);
	include('header.inc.php');

	$ical_login = split('\@',$_SERVER['PHP_AUTH_USER']);
	if($ical_login[1])
	{
		$ical_user = $ical_login[0];
		$domain    = $ical_login[1];
		unset($ical_login);
	}
	else
	{
		$ical_user = $_SERVER['PHP_AUTH_USER'];
		$domain    = get_var('domain',array('COOKIE','GET'));
	}

	$sessionid = get_var('sessionid',array('COOKIE','GET'));
	$kp3 = get_var('kp3',array('COOKIE','GET'));
	$domain = $domain ? $domain : $GLOBALS['egw_info']['server']['default_domain'];

	$icalsrv['session_ok'] = $GLOBALS['egw']->session->verify($sessionid,$kp3);
	if($icalsrv['session_ok'])
	{
		$icalsrv['authed'] = True;
	}

	if(!$icalsrv['session_ok'] && isset($_SERVER['PHP_AUTH_USER']) && isset($_SERVER['PHP_AUTH_PW']))
	{
		$icalsrv['authed'] = $GLOBALS['egw']->session->create($ical_user . '@' . $domain, $_SERVER['PHP_AUTH_PW'], 'text');
	}
	if($icalsrv['authed'])
	{
		$icalsrv['session_ok'] = True;
		// This may not even be necessary:
		$GLOBALS['egw_info']['flags']['currentapp'] = 'icalsrv';
	}

	// bad session or bad authentication so please re-authenticate..
	if(!($icalsrv['session_ok'] && $icalsrv['authed']))
	{
		if($isdebug)
		{
			error_log('line ' . __LINE__ . ': Session: '. $icalsrv['session_ok'] . ', Authed: ' . $icalsrv['authed']);
		}
		header('WWW-Authenticate: Basic realm="ICal Server"');
		header('HTTP/1.1 401 Unauthorized');
		exit;
	}

	/* Moved after the auth header send.  It is normal to save this check until now, similar to how simple eGroupWare access control works for a browser login. (Milosch) */
	if(!@isset($GLOBALS['egw_info']['user']['apps']['icalsrv']))
	{
		fail_exit('IcalSRV not enabled','403');
	}

	// Ok! We have a session and access to icalsrv!

	// now set the variables that will control the working mode of icalvircal
	// the defines are in the icalsrv_resourcehandler sourcefile
	require_once EGW_SERVER_ROOT. '/icalsrv/inc/class.icalsrv_resourcehandler.inc.php' ;

	/** uid  mapping export  configuration switch
	* @var int
	* Parameter that determines, a the time of export from Egw (aka dowload by client), how 
	* ical elements (like VEVENT's) get their uid fields filled, from data in
	* the related Egroupware element.
	* See further in @ref secuidmapping in the icalsrv_resourcehandler documentation.
	*/
	// New RalfBecker Aug 2007
	// NOT using UID2UID creates doublicates on the iCal client, as it does NOT understand,
	// that posted events get their uid changed by the server.
	// I think uid's should be handled as specified in the RFC: the first clients assigns them
	// AND noone is supposted to change them after that!
	$uid_export_mode = UMM_UID2UID;

	/** uid  mapping import  configuration switch
	* @var int
	* Parameter that determines, at the time of import into Egw (aka publish by client), how
	* ical elements (like VEVENT's) will find, based on their uid fields,  related egw
	* elements, that are then updated with the ical info.
	* See further in @ref secuidmapping in the icalsrv_resourcehandler documentation.
	*/
	// New RalfBecker Aug 2007
	// NOT using UID2UID creates doublicates on the iCal client, see above
	$uid_import_mode = UMM_UID2UID;

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
	# if(!empty($_SERVER['CONTENT_TYPE'])) {
	#    if(strpos($_SERVER['CONTENT_TYPE'], 'application/vnd....+xml') !== false) {
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

	if(empty($_SERVER['PATH_INFO']))
	{
		// no specific calendar requested, so do default.ics
		$reqvircal_pathname = '/default.ics';

		// try owner + base for a personal vircal request 
	}
	elseif(preg_match('#^/([\w]+)(/[^<^>^?]+)$#', $_SERVER['PATH_INFO'], $matches))
	{
		$reqvircal_pathname = $matches[0];
		$reqvircal_owner = $matches[1];
		$reqvircal_basename = $matches[2];

		if(!$reqvircal_owner_id = $GLOBALS['egw']->accounts->name2id($reqvircal_owner))
		{
			// owner is unknown, so forget about personal calendar

			unset($reqvircal_owner);
			unset($reqvircal_basename);
		}

		// check for decent non personal path
	}
	elseif(preg_match('#^(/[^<^>]+)$#', $_SERVER['PATH_INFO'], $matches))
	{
		$reqvircal_pathname = $matches[0];

		// just default to standard path
	}
	else
	{
		$reqvircal_pathname = 'default.ics';
	}

	if($isdebug)
	{
		error_log('http-user-agent:' . $reqagent
			. ',pathinfo:' . $reqpath . ',rvc_pathname:' . $reqvircal_pathname
			. ',rvc_owner:' . $reqvircal_owner . ',rvc_owner_id:' . $reqvircal_owner_id
			. ',rvc_basename:' . $reqvircal_basename);
	}
	// S1A search for the requested calendar in the vircal_ardb's 
	if(is_numeric($reqvircal_owner_id))
	{
		// check if the requested personal calender is provided by the owner..

		/**
		* @todo 1. create somehow the list of available personal vircal arstores
		* note: this should be done via preferences and read repository, but how....
		* I have to find out and write it...
		*/

		// find personal database of (array stored) virtual calendars
		$cnmsg = 'calendar [' . $reqvircal_basename . '] for user [' . $reqvircal_owner . ']';
		$vo_personal_vircal_ardb =& CreateObject('icalsrv.personal_vircal_ardb', $reqvircal_owner_id);
		if(!(is_object($vo_personal_vircal_ardb)))
		{
			error_log('icalsrv.php: couldnot create personal vircal_ardb for user:' . $reqvircal_owner);
			fail_exit('could not access' . $cnmsg, '403');
		}

		// check if a /<username>/list.html is requested
		if($reqvircal_basename == '/list.html')
		{
			echo $vo_personal_vircal_ardb->listing(1);
			$GLOBALS['egw']->common->egw_exit();
		}

		error_log('vo_personal_vircal_ardb:' . print_r($vo_personal_vircal_ardb->calendars, true));

		// search our calendar in personal vircal database
		if(!($vircal_arstore = $vo_personal_vircal_ardb->calendars[$reqvircal_basename]))
		{
			error_log('icalsrv.php: ' . $cnmsg . ' not found.');
			fail_exit($cnmsg . ' not found.' , '404');
		}
		// oke we have a valid personal vircal in array_storage format!
	}
	else
	{
		// check if the requested system calender is provided by system
		$cnmsg = 'system calendar [' . $reqvircal_pathname . ']';  
		/**
		* @todo 1. create somehow the list of available system vircal
		* arstores note: this should be done via preferences and read
		* repository, but how.... I have to find out
		*/

		// find system database of (array stored) virtual calendars
		$system_vircal_ardb = CreateObject('icalsrv.system_vircal_ardb');
		if(!(is_object($system_vircal_ardb)))
		{
			error_log('icalsrv.php: couldnot create system vircal_ardb');
			fail_exit('couldnot access ' . $cnmsg, '403');
		}

		// check if a /list.html is requested
		if($reqvircal_pathname == '/list.html')
		{
			echo $system_vircal_ardb->listing(1);
			$GLOBALS['egw']->common->egw_exit();
		}

		// search our calendar in system vircal database
		if(!($vircal_arstore = $system_vircal_ardb->calendars[$reqvircal_pathname]))
		{
			fail_exit($cnmsg . ' not found', '404');
		}
		// oke we have a valid system vircal in array_storage format!
	}
	//die(print_r($_COOKIE,true). " in ". __FILE__.", line ".__LINE__);
	if($isdebug)
	{
		error_log('vircal_arstore:' . print_r($vircal_arstore, true));
	}

	// build a virtual calendar with ical facilities from the found vircal
	// array_storage data
	require_once(EGW_INCLUDE_ROOT.'/icalsrv/inc/class.icalvircal.inc.php');
	$icalvc =& new icalvircal;
	if(!$icalvc->fromArray($vircal_arstore))
	{
		error_log('icalsrv.php: ' . $cnmsg . ' couldnot restore from repository.' );
		fail_exit($cnmsg . ' internal problem ' , '403');
	}
	// YES: $icalvc created ok! acces rights needs to be checked though!

	// HACK: ATM basic auth is always needed!! (JVL) ,so we force icalvc into it
	$icalvc->auth = ':basic';


	// check if the virtual calendar demands authentication
	if(strpos($icalvc->auth,'none') !== false)
	{
		// no authentication demanded so continue
	}
	elseif(strpos($icalvc->auth,'basic') !== false)
	{
		//basic http authentication demanded
		//so exit on non authenticated http request

		//-- As we atm only allow authenticated users the
		// actions in  the next lines are already done at the begining
		// of this file --
		//    if((!isset($_SERVER['PHP_AUTH_USER']))	||
		//  	  (!$GLOBALS['egw']->auth->authenticate($_SERVER['PHP_AUTH_USER'],
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
	}
	elseif(strpos($icalvc->auth,'ssl') !== false)
	{
		// ssl demanded, check if we are in https authenticated connection
		// if not redirect to https
		error_log('icalsrv.php:' . $cnmsg . ' demands secure connection');
		fail_exit($cnmsg . ' demands secure connection: please use https', '403');
	}
	else
	{
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
	if(strpos($icalvc->auth,'passw') !== false)
	{
		//extra parameter password authentication demanded
		//so exit if pw parameter is not valid
		if((!isset($_GET['password']))	||
			(!$icalvc->pw !== $_GET['password']))
		{
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

	
	// NEW RalfBecker Aug 2007
	// We have to handle the request methods different, specially the WebDAV ones we dont support
	switch($_SERVER['REQUEST_METHOD'])
	{
	case 'PUT':
		// *** PUT Request so do an Import *************
		
		if($isdebug)
		{
			error_log('icalsrv.php: importing, by user:' .$GLOBALS['egw_info']['user']['account_id']
				. ' for virtual calendar of: ' . $reqvircal_owner_id);
		}
		// check if importing in not owned calendars is disabled
		if($reqvircal_owner_id
			&& ($GLOBALS['egw_info']['user']['account_id'] !== $reqvircal_owner_id))
		{
			if($disable_nonowner_import)
			{
				error_log('icalsrv.php: importing in non owner calendars currently disabled');
				fail_exit('importing in non owner calendars currently disabled', '403');
			}
		}
		if(isset($reqvircal_owner_id) && ($reqvircal_owner_id < 0))
		{
			error_log('icalsrv.php: importing in group calendars not allowed');
			fail_exit('importing in groupcalendars is not allowed', '403');
		}

		// NEW RalfBecker Aug 2007
		// for a PUT we have to check if the currently loaded calendar is still up to date
		// (not changed eg. by someone else or via the webfrontend).
		// This is done by comparing the ETAG given as HTTP_IF with the current ETAG (last modification date)
		// of the calendar --> on failure we return 412 Precondition failed, to not overwrite the modifications 
		if (isset($_SERVER['HTTP_IF']) && preg_match('/\(\[([0-9]+)\]\)/',$_SERVER['HTTP_IF'],$matches))
		{
			$etag = $icalvc->get_etag();
			//error_log("PUT: current etag=$etag, HTTP_IF=$_SERVER[HTTP_IF]");
			if ($matches[1] != $etag)
			{
				fail_exit('Precondition Failed',412);
			}
		}
	
		// I0 read the payload
		$logmsg = 'IMPORTING in '. $importMode . ' mode';
		$fpput = fopen("php://input", "r");
		$vcalstr = "";
		while($data = fread($fpput, 1024))
		{
			$vcalstr .= $data;
		}
		fclose($fpput);

		// import the icaldata into the virtual calendar
		// note: ProductType is auto derived from $vcalstr
		$import_table =& $icalvc->import_vcal($vcalstr);

		// count the successes..
		if($import_table === false)
		{
			$msg = 'icalsrv.php:  importing '. $cnmsg . ' ERRORS';
			fail_exit($msg,'403');
		}
		else
		{
			$logmsg .= "\n imported " . $cnmsg . ' : ';
			foreach($import_table as $rsc_class => $vids)
			{
				$logmsg .=  "\n   resource: " . $rsc_class . ' : ' . count($vids) .' elements OK';
			}
		} 
		// DONE importing
		if($logdir)
		{
			log_ical($logmsg,"import",$vcalstr);
		}

		// NEW RalfBecker Aug 2007
		// we have to send a new etag header, as otherwise the client (at least lightning) has a wrong etag,
		// if it's not requesting the calendar again via GET
		header("ETag: ". $icalvc->get_etag());

		// handle response ...
		$GLOBALS['egw']->common->egw_exit();
	
	case 'GET':
		// *** GET Request so do an export
		$logmsg = 'EXPORTING';
		// derive a ProductType from our http Agent and set it in icalvc
		$icalvc->deviceType = icalsrv_resourcehandler::httpUserAgent2deviceType($reqagent);

		// NEW RalfBecker Aug 2007
		// if an IF_NONE_MATCH is given, check if we need to send a new export, or the current one is still up-to-date
		if (isset($_SERVER['HTTP_IF_NONE_MATCH']))
		{
			$etag = $icalvc->get_etag();
			error_log("GET: current etag=$etag, HTTP_IF_NONE_MATCH=$_SERVER[HTTP_IF_NONE_MATCH]");
			if ($_SERVER['HTTP_IF_NONE_MATCH'] == $etag)
			{
				fail_exit('Not Modified',304);
			}
		}
		// export the data from the virtual calendar
		$vcalstr = $icalvc->export_vcal();

		// handle response
		if($vcalstr === false)
		{
			$msg = 'icalsrv.php:  exporting '. $cnmsg . ' ERRORS';
			fail_exit($msg,'403');
		}
		else
		{
			$logmsg .= "\n exported " . $cnmsg ." : OK ";
		} 
		// DONE exporting
		
		// NEW RalfBecker Aug 2007
		// returnung max modification date of events as etag header
		header("ETag: ". $icalvc->export_etag);

		if($logdir) log_ical($logmsg,"export",$vcalstr);
		// handle response ...
		// using fixed text/calendar as content-type, as deviceType2contentType always returns '', which cause php to use text/html
		//$content_type = icalsrv_resourcehandler::deviceType2contentType($icalvc->deviceType);
		$content_type = 'text/calendar';
		if($content_type)
		{
			header('Content-Type: '.$content_type);
		}
		echo $vcalstr;
		$GLOBALS['egw']->common->egw_exit();

	default:
	case 'PROPFIND':
		// tell the client we do NOT support full WebDAV/CalDAV
		fail_exit('Not Implemented',501);
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
		ob_flush();
		flush();
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
		if(!$logdir)	return; // loggin seems off

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
