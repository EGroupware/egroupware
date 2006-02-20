<?php
    /**
	 * @file
	 * eGroupWare API - eGroupWare  specific ICalendar component conversion,
	 * import and export package.
	 *
	 * @author Jan van Lieshout                                                *
	 * @package egwical
	 * ------------------------------------------------------------------------ *
	 * This library is free software; you can redistribute it and/or modify it  *
	 * under the terms of the GNU Lesser General Public License as published by *
	 * the Free Software Foundation; either version 2.1 of the License,         *
	 * or any later version.                                                    *
	 * This library is distributed in the hope that it will be useful, but      *
	 * WITHOUT ANY WARRANTY; without even the implied warranty of               *
	 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.                     *
	 * See the GNU Lesser General Public License for more details.              *
	 * You should have received a copy of the GNU Lesser General Public License *
	 * along with this library; if not, write to the Free Software Foundation,  *
	 * Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA            *
	 **************************************************************************/


     require_once EGW_SERVER_ROOT.'/phpgwapi/inc/horde/Horde/iCalendar.php';
     require_once EGW_SERVER_ROOT.'/calendar/inc/class.socal.inc.php';  // for MCAL defs
     require_once EGW_SERVER_ROOT.'/egwical/inc/class.bocalupdate_vevents.inc.php';
     require_once EGW_SERVER_ROOT.'/egwical/inc/class.boinfolog_vtodos.inc.php';

    //TODO VERSION 0.9.04
    //[ ] overwrite of myid partstatus in nonowned events/tasks 
    /* recur count implementation:
	  [+] daily with intervals
	  [-] daily with exdates
	  [+ ] weekly;byday withintervals
	  [ ] monthly;
	  [ ] yearly;
	*/


    /**
	 * 
	 * Common  routines to manipulate iCalendar components and fields in
	 * an EGroupware context.
	 *
	 * -Egwical provides    at the moment routines for easy filling and retrieving
	 * (property) field values and params from iCal objects (VEVENTS, VTODOS, VALARMS..).
	 * For thebasic iCalendar handling currently the Horde routines are  used.
	 * Most of thes routines are used in both the infolog.bovtodos and calendar.bovevents
	 * classes. To prevent code duplication they were factored out into this package.
	 *
	 * @section sectimezones TimeZone handling
	 *  Currently vtimezones are completely ignored when found in an
	 * ical file that is to be imported. All times that are not in utc format (end with Z)
	 * will be interpreted as set in the timezone of the logged in user (UI times).
	 * From this on the bocalendar and boinfolog classes will do the proper conversion to
	 * server times.
	 * On export (all|most) date-time values produced will be in utc format.
	 * Date values on the contrary (as for 
	 * whole day events etc) will be in UI time of the logged in user, thus in a likewise
	 * manner as on import.  The logic here is that wholeday events are to respect daylight
	 * and not exact time. There will be no VTIMEZONE written in the exported icalendar.
	 *
	 * @section secmethodnames Generic method names
	 * Most of the methods this provides follow a generic naming scheme based on their
	 * functionality. The generic prefixes are:
	 * <ul>
	 * <li><code>mki_</code>
	 *  This is for methods that MaKe a Ical thing like a component, field, fieldvalue or
	 *    fieldparameter. Thus these are subdived in:</li>
	 *  <ul>
	 *   <li><code>mki_c</code> to make ical Components like VEVENTS or VALARMS </li>
	 *   <li><code>mki_v</code> to make ical field Values like e.g. a ATTENDEE field value</li>
	 *   <li><code>mki_vp</code> to make both ical field Values and Parameters </li>
	 *  </ul>
	 * </li>
	 * <li><code>mke_</code>
	 *  This is for methods that MaKe a Egw things like a field of egw event or task.</li>
	 * <li><code>updi_</code>
	 *  This is for methods that UPDate an Ical component or field. Note that the Ical component
	 *  to be updated will be passed by reference to these routines</li>
	 * <li><code>upde_</code>
	 *  This is for methods that UPDate an Egw entity like an event or task or.... Note again
	 *  that the Egw entity will be passed by reference to these routines.</li>
	 * </ul>
	 *
	 * @section secworkersubclasses Worker Subclasses
	 *  Egwical is implemented following the WURH pattern (see @ref pageegwicalwurh).
	 * Currently there are two worker subclasses available:
	 * - <code>bocalupdate_vevents</code> to convert between egw calendar events and VEVENTS
	 *   and allow import and export of these. When added as a resource to an Egwical object,
	 * the representative member for this subclass is set in the attribute $this->buverpr
	 *   
	 * - <code>boinfolog_vtodos</code> to convert between egw infolog tasks events and VTODOS
	 *   and allow import and export of these. When added as a resource to an Egwical object,
	 * the representative member for this subclass is set in the attribute $this->ivtrpr
	 *   
	 * @note At this moment (V 0.9.01) there is still a little unpleasant piece of extra
	 * coding needed to get
	 * it working: when you develop a new workerssubclass you must  enter it manually in
	 * the egwical constructor,
	 * so that it can later on automatically be found and matched to the appropiat egw resource.
	 * (I couldnot find an easy way to search for existing subclasses so therefore it is needed)
	 *
	 * @note in future this package may become a full wrapper around the Horde_iCalendar
	 * libraries. This way we can keep the relevant egw and ical objects alive between
	 * calls so that code could be made faster and less memory consuming..
	 *
	 * @since 0.9.0 api is incompatible with lower versions
	 * @author Jan van Lieshout <jvl (at) xs4all.nl> (This version)
	 * @author Lars Kneschke <lkneschke@egroupware.org> (original code of reused parts)
	 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de> (original code of reused parts)
	 *
	 *
	 * @version 0.9.04 (First wurh pattern implementation, with RRULE count= impl.)
	 * @date 20060216
	 * @license http://opensource.org/licenses/gpl-license.php GPL -
	 *  GNU General Public License
	 */
    class egwical
    {

	  /**
	   * @var Horde_iCalendar
	   * Placeholder object used to access various Horde_iCalendar methods
	   * In here the constructor will create a Horde_iCalendar object that can be used
	   * by the various routines in the class
	   */
	  var $hi;

	  // ----------- first the implementation of the WURH pattern ------------


// 	  /** Description of the resource handling capacity this object provides
// 	   * @var array
// 	   * Decsription, in the form of an entry for the @ref $reg_rscworkers registry,
// 	   * of the worker capabilities this object provides.
// 	   * When an object of this (sub) class is instatiated and added to a egwical object as
// 	   * a socalled worker representative, this capability description is copied into the
// 	   * (compounds) reg_rscworkers registry.
// 	   * @note in genuine worker subclasses this variable must be initialized
// 	   * by the constructor. 
// 	   */
// 	  var $iprovide_work = null;


	  /** Registry for Resource Worker classes
	   *
	   * @var array
	   * The resource workers registry is an array that holds 
	   * an entry for each type of resource classs with the appropiate worker class and
	   * the currently set worker (representative) instantiated object of that class.
	   * @note the (instantiated) worker representative object in the registry entry is
	   * firstly set when an appropiate worker subclass is actually instantiated during
	   * runtime. (This setting then is done by the constructor of the worker class of course.
	   *  that uses the info from the workerclass its variable @ref $iprovide_rscwork) 
	   *
	   * Structure of the registry:
	   * <pre>
	   *  $reg_rscworkers = array($rsclass_name =>
	   *                          array('workerclass' =>$workerclass_name,
	   *                                'workerobj'   => $worker_obj,
	   *                                'vcsup'     =>array($velmc1,$velmc2,..)),
	   *                           ..)
	   *</pre>
	   * example of an entry:
	   * <pre>
	   *  $reg_rscworkers['boinfolog'] = array('workerclass' =>'boinfolog_vtodos',
	   *                                       'workerobj'   => null,
	   *                                       'icalsup'     =>array('VTODO'))
	   * </pre>
	   */
	  var $reg_rscworkers = array();


	  /** Registry for Resources
	   *
	   * @var array
	   *
	   * The resources registry is an array that holds an entry for
	   * types of icalendar component class ('VEVENT, VTODO etc.)
	   * with:
	   *- 1) an appropiate egw resource object for storing and
	   *  retrieving the info for these components (thus a calendar- or
	   * infolog- or .. etc. object) and
	   *- 2) an (instantiated) worker object to handle transport from
	   * and to the resource.
	   * The entries of this registry get filled when the
	   * resources are added to the egwical object by calls to the
	   * addRsc() method. 
	   * @note multiple entries for the same icalendar component are allowed.
	   * This can occur when multiple resources can provide e.g. VEVENT data.
	   *
	   * Structure of the registry:
	   * <pre>
	   *   reg_rscs = array($icalcompclass_name => array($rsc_obj, $worker_obj),..)
	   *</pre>
	   * example of an entry:
	   * <pre>
	   *  reg_rscs['VTODO'] = array(null, null)
	   * </pre>
	   */
	  var $reg_rscs = array();



	  /**
	   * Constructor, init the auxiliary object @ref $hi and @ref $TASKMAGIC
	   * and instantiate the @ref $reg_rscworkers workers registry and the
	   * @ref $reg_rscs resources
	   * registry.
	   * @note <b>At this moment (V 0.9.01): when you develop a new workerssubclass you must
	   * manually add its characteristics via a class call to its provides_work() function
	   * from within the constructor code.</b>
	   * (It is needed  because I couldnot find an easy way to search for existing subclasses
	   * yet)
	   *
	   */
	  function egwical()
	  {
		// actually this would only be needed by the abstract superclass?
		$this->hi = &new Horde_iCalendar;

		$this->TASKMAGIC = $GLOBALS['egw_info']['server']['install_id']
		  ? $GLOBALS['egw_info']['server']['install_id']
		  : 'local'; 
		// update following list when a new worker subclasses becomes available
		// (i.e. copy the info from its $iprovide_work var)
		$this->reg_rscworkers =
 		  array_merge(
 					  $this->reg_rscworkers,
 					  bocalupdate_vevents::provides_work(),	  
 					  boinfolog_vtodos::provides_work()
 				);
		//		error_log('**now reg_workers=' . print_r($this->reg_rscworkers,true));
		$this->reg_rscs = array();
	  }


	  /**
	   * Add a egw resource.
	   *
	   * The egw resource (calendar, infolog, ..) in $egw_rsc is added
	   * to egwical. This will be used for storing and retrieving
	   * types of iCalendar components (VEVENT, VTODO,..) for which it has corresponding
	   * egw data elements (like egw events or egw tasks.
	   *
	   * Following the WURH pattern, adding the egw resource $rsc will result in
	   * the creation of a dedicated representative "worker" subclass object to handle the
	   * transport to and from the resource. The appropiate worker class is looked up in the
	   * $reg_workers registry. When the worker is created, the resource and its worker will
	   * be entered as a resource worker pair in the egwical $reg_rscs registry.
	   * This representative worker object will also be
	   * returned by reference to the caller. Using this reference the caller can later on do
	   * specific settings for the worker, (like setSupportedFields()).
	   *
	   * @param object $egw_rsc egw resource (like calendar or infolog,..) object that will be
	   *  used by the egwical representative worker class to transport converted
	   *  ical components to and from.
	   * @return egwical|false $rpr_worker the representative worker class for handling the
	   * added egw resource. On error false is returned.
	   */
	  function addRsc($egw_rsc)
	  {
		//step 1: detect the egw_rsc class  
		if (!$rsc_classname = get_class($egw_rsc))
		  // bad value for $egw_rsc
		  return false;

		//step 1a check that the egwrsc is not already registered
		foreach($this->reg_rscs as $vc => $rscwkpair){
		  if($rscwkpair[0] === $egw_rcs){
			error_log('Warning: egwical.addRsc(): trying add resource multiple times,' .
					  ' for class: ' . $rsc_classname . ' : ignored');
			return $rscwkpair[1];
		  }
		}

		//step 1: detect the egw_rsc class  
		if (!$rsc_classname = get_class($egw_rsc))
		  // bad value for $egw_rsc
		  return false;

		// step 2a: look in reg_rscworkers for appropiate worker class
		if (!$wkprov = $this->reg_rscworkers[$rsc_classname]){
		  error_log('Error: egwical.addRsc(): no workerclass available yet for resourcetype:' .
					$class_name . ' sorry');
		  return false;
		}

		// step 2b create a workerobject of the correct type
		// note that we dont reuse an already available workerobj of the right type because
		// probably the workerclasses are not reentrantly coded yet...
		if(! $wkobj =& CreateObject('egwical.' . $wkprov['workerclass'])){
		  // workerclass object creation problem
		  return false;
		}
		// step 2c give the workerobj the egw_rsc to handle
		$wkobj->setRsc($egw_rsc);

		// step3: use the found worker info to register the egw_rsc and workerobj for
		// each of the supported type of ical elements
		foreach ($wkprov['icalsup'] as $icomptype ){
		  //error_log('adding reg_rscs['. $icomptype . '] entry');
		  $this->reg_rscs = array_merge($this->reg_rscs,
										array($icomptype => array($egw_rsc, $wkobj)));
		}
		// step 4: return the workerclass (representative obj)

		return $wkobj;
	  }



	  // ------------- second: below only generic conversion stuff --------------


	  // --- generic conversion auxilliary routines -------------
	  // --- note: this could be left out in the abstract baseclass instantiation
	  // but is not that much, so leave it get duplicated...

	  /**
	   * @private
	   * @var string 
	   * Magic unique number used for de/encoding our uids.
	   *
	   * This string that contains global unique magic number that is
	   *  unique for our current database installed etc. It is used to recognize
	   *  earlier exported VTODO or VEVENT UID fields as referring to their eGW counterparts.
	   */
	  var $TASKMAGIC='dummy';


	  /**
	   * @var array $status_ical2egw
	   * Conversion of the  egw used priority values(0..3) to corresponding ical values(0..9).
	   * @private
	   */
	  var $priority_egw2ical =
		array(
			  0 => 0,		// undefined
			  1 => 9,		// low
			  2 => 5,		// normal
			  3 => 1,		// high
			  );
	  /**
	   * @var array $status_ical2egw conversation of the priority ical => egw
	   * Conversion of the  icalendar used priority values(0..9) to corresponding egw values (0..3).
	   * @private
	   */
	  var $priority_ical2egw =
		array(
			  0 => 0,	       // undefined
			  9 => 1, 8 => 1, 7 => 1, 	// low
			  6 => 2, 5 => 2, 4 => 2,	// normal
			  3 => 3, 2 => 3, 1 => 3,	// high
			  );

	  
	  /**
	   * @var array $partstatus_egw2ical 
	   * Conversion of the egw used participant status values to the corresponding icalendar
	   * attendee status terminology.
	   * @private
	   */
	  var $partstatus_egw2ical =
		array(
			  'U' => 'NEEDS-ACTION',
			  'A' => 'ACCEPTED',
			  'R' => 'DECLINED',
			  'T' => 'TENTATIVE',
			  );
	  /**
	   * @var array 
	   * Conversion of the icalendar used attendee status values to the corresponding icalendar
	   * participants status terminology.
	   * @private
	   */
	  var $partstatus_ical2egw =
		array(
			  'NEEDS-ACTION' => 'U',
			  'ACCEPTED'     => 'A',
			  'DECLINED'     => 'R',
			  'TENTATIVE'    => 'T',
			  );
		

	  /**
	   * @var array $recur_egw2ical
	   * Conversion of egw recur-type to ical FREQ values for RRULE fields
	   * @private
	   */
	  var $recur_egw2ical =
		array(
			  MCAL_RECUR_DAILY        => 'DAILY',
			  MCAL_RECUR_WEEKLY       => 'WEEKLY',
			  MCAL_RECUR_MONTHLY_MDAY => 'MONTHLY',	
			  MCAL_RECUR_MONTHLY_WDAY => 'MONTHLY',	
			  MCAL_RECUR_YEARLY       => 'YEARLY',
			  );
	  // BYMONHTDAY={1..31}, BYDAY={1..5}{MO..SO}

	  /**
	   * @var array
	   * recur_days translates MCAL recur-days to verbose labels
	   * (copied from class.bocal.inc.php file
	   * @private
	   */
	  var $recur_days =
		array(
			  MCAL_M_MONDAY    => 'Monday',
			  MCAL_M_TUESDAY   => 'Tuesday',
			  MCAL_M_WEDNESDAY => 'Wednesday',
			  MCAL_M_THURSDAY  => 'Thursday',
			  MCAL_M_FRIDAY    => 'Friday',
			  MCAL_M_SATURDAY  => 'Saturday',
			  MCAL_M_SUNDAY    => 'Sunday',
			  );
	  
	  /**
	   * @var array
	   * Get sequential indexes for the daynames in a week. Used for recurrence count
	   * calculations.
	   */
	  var $dowseqid =
		array('SU' => 1, 'MO' => 2, 'TU' => 3, 'WE' => 4, 'TH' => 5, 'FR' => 6, 'SA' => 7);


	  // --- generic conversion auxilliary routines -------------
	  
	  /**
	   * Parse a vCalendar string into an Horde_iCalendar object.
	   *
	   * To actually parse the string, the Horde_iCalendar in member @ref $hi is used.
	   * @param string $vcalstr the icalendar input string
	   * @return boolean|Horde_iCalendar the resulting parsed elements collected in a
	   * horde ical object. On error: false
	   */
	  function parsevCalendar($vcalstr)
	  {
		// unfoldlines as this was removed from our horde stuff
		$vcalstr = preg_replace("/[\r\n]+ /",'',$vcalstr);

		//		$this->hi->clear();
 		if(!$this->hi->parsevCalendar($vcalstr)){
 		  error_log('egwical parsevCalendar:  ERROR- couldnot parse..');
 		  return false;
 		}
		
		return $this->hi;
	  }




	  /**
	   * Generate ical UID from egw id.
	   *
	   * generate a unique id, with the egw id encoded into it, which can be
	   * used for later synchronisation.
	   * @param string|int $egw_id  eGW id of the egw entity (event, task,..)
	   * @param string $app_prefix prefix to use in ecnoding the name
	   * 
	   * @return string|false  on success the global unique id. On error: false.
	   *                     
	   * Uses @ref $TASKMAGIC  string that holds our unique ID
	   */
	  function mki_v_guid($egw_id,$app_prefix='egw')
	  {
		if (empty($egw_id))
		  return false;
		return $app_prefix .'-' . $egw_id. '-' . $this->TASKMAGIC;
	  }



	  /**
	   * Try to decode an egw id from a ical UID
	   *
	   * @param string $guid the global Icalendar UID value
	   * @param string $app_prefix prefix to be found in the encoding
	   * @return false|int On error: false. 
	   *                   On success: local egw todo id.
	   */
	  function mke_guid2id($guid,$app_prefix='egw')
	  {
		//		error_log('mke_guid2id: trying to recover id from' . $guid);
		if (!preg_match('/^' . $app_prefix . '-(\d+)-' .
						$this->TASKMAGIC . '$/',$guid,$matches))
		  return false;

		//		error_log("mke_guid2id: found (" . $matches[1] . ")");		
		return $matches[1];
	  }



	  /**
	   * Get database add date of event or todo
	   * @private
	   * @param int $id id of event or todo
	   * @param string $appname  name of the application (='calendar' or 'infolog')
	   * @return int $createdate  of db insert or false on error
	   */
	  function get_TSdbAdd($id,$appname='calendar')
	  {
		if (!(($appname == 'calendar') || ($appname == 'infolog')))
		  return false;
		if (! $auid = $GLOBALS['egw']->common->generate_uid($appname,$id))
		  return false;
		
		return $GLOBALS['egw']->contenthistory->getTSforAction($auid,'add');
	  }

	   
	  /**
	   * Convert a egw prio into a value for the ical property PRIORITY
	   * @param int $eprio  priority in egw (0..3)
	   * @return int $iprio conversion of $eprio as value (0..9) for the ical PRIORITY prop
	   */
	  function mki_v_prio($eprio = 0)
	  {
		return $this->priority_egw2ical[$eprio];
	  }

	  /**
	   * Convert a ical prio into a value for egw
	   * @param int $iprio  priority in ical (0..9)
	   * @return int $eprio conversion of $iprio as value (0..3) for egw
	   */
	  function mke_prio($iprio = 0)
	  {
		return $this->priority_ical2egw[$iprio];
	  }



	  /** 
	   * Translate cat-ids to array with id-name pairs
	   *
	   * <i>JVLNOTE: boldly copied from class.xmlrpc_server.inc.php because I donot know how
	   * to instantiate $GLOBALS['server'] (that provides this method) atm.</i>
	   * @note THIS CODE SHOULD BE SOMEWHERE ELSE: IT HAS NOTHING TO DO WITH ICAL!!
	   * @param array $cids   the list with category ids 
	   * @return string|false $idnamescstr  commasep string with names for the category ids or
	   * on error false
	   */
	  function cats_ids2idnamescstr($cids)
	  {
		if(empty($cids))
		  return false;

		if (!is_object($GLOBALS['egw']->categories))
		  $GLOBALS['egw']->categories = CreateObject('phpgwapi.categories');

		$idnames = array();
		foreach($cids as $cid) {
		  if ($cid) 
			$idnames[$cid] = stripslashes($GLOBALS['egw']->categories->id2name($cid));
		}
		return implode(',',$idnames);
	  }


	  // ************ JVL CHECK THE CODE BENEATH *****************
	  // oke: seems to work for a single categorie (tested form bovtodos calls)

	  /** 
	   * Translate catnames back to cat-ids creating/modifying cats on the fly
	   *
	   * <i>JVLNOTE boldly copied from class.xmlrpc_server.inc.php because I donot know how
	   * to instantiate $GLOBALS['server'] (that provides this method) atm. </i>
	   * 
	   * @note THIS CODE SHOULD BE SOMEWHERE ELSE: IT HAS NOTHING TO DO WITH ICAL!!
	   * @param array $cnames  list with category names
	   * @return string $cidscstr   commasep string with ids generated or found for
	   * the category names.
	   */
	  function cats_names2idscstr($cnames,$owner_id,$app_name='infolog')
	  {
		if (empty($cnames))
		  return false;

		if (!is_object($catsys =& $GLOBALS['egw']->categories)) {
		  $GLOBALS['egw']->categories =& CreateObject('phpgwapi.categories',
													  $owner_id,$app_name);
		  $catsys =& $GLOBALS['egw']->categories;
		}
		$cids = array();
		foreach($cnames as $name) {
		  if (!($cid = $catsys->name2id($name))) {
			// existing cat-name use the id
			// new cat
			$cid = $catsys->add(array('name' => $name,'descr' => $name));
		  }
		  $cids[] = (int)$cid;
		}
		return implode(',',$cids); 
	  }


	  /**
	   * Convert and egw account id into a iCalendar CAL-ADDRESS type value string
	   * @param int $aid egw account(person) id
	   * @return string $cls cal_address format string (mailto:<emailadr>. On error
	   * the emailadr part will stay empty.
	   */
	  function mki_v_CAL_ADDRESS($aid)
	  {
		$mailtoAid = $GLOBALS['egw']->accounts->id2name($aid,'account_email');
		return $mailtoAid ? 'MAILTO:'.$mailtoAid : 'MAILTO:';
	  }


	  /**
	   * Convert and egw account id into a iCalendar CN type parameter string
	   * @param  int $account_id egw account(person) id
	   * @return string $cnparam CN param value format string. On erro this will be empty.
	   */
	  function mki_p_CN($account_id)
	  {
		$cns = trim($GLOBALS['egw']->accounts->id2name($account_id,'account_firstname')
					. ' '
					. $GLOBALS['egw']->accounts->id2name($account_id,'account_lastname'));
		
		return array('CN' => $cns ? $cns : '');
	  }


	  /** 
	   * Convert a horde_icalendar parsed attribute date- or date-time value 
	   * to a unix timestamp. 
	   * @note this is just a hack because horde_icalendar converts only date-times to utime
	   * @param array|string $ddtval DATE array or DATE-TIME utime string
	   * @return int $utime  unix time of the date or date time
	   */
	  function mke_DDT2utime($ddtval)
	  {
		if(!is_array($ddtval)){
		  // assume an already parsed(by Horde_iCalendar) date-time value
		  return $ddtval; 
		} else {
		  //assume a DATE, BUT WHERE DO I GET A POSSIBLE TIMEZONE FROM?
		  // assume user time zone (for utc use gmmktime()
		  return @mktime(0,0,0,$ddtval['month'],$ddtval['mday'],$ddtval['year']);
		}
	  }


	  /** 
 	   * Convert a unix timestamp to a 6 field hash array in the current active timezone
 	   * 
	   *  This is basically alike the php getdate() function but with different field names
	   * 
	   *  The a6date array has fields as in the php getdate() function:
	   * - <code>year</code> four digit year field
	   * - <code>month</code> integer month number
	   * - <code>mday</code> integer day of month number 
	   * - <code>hour</code> integer hour
	   * - <code>minute</code> integer minutes
	   * - <code>second</code> integer seconds
	   * 
	   * @param int  $utime   a unixtimestamp assumed in utc timezone
	   * @return array The date in a6date in local timezone format.
	   */
 	  function utimetoa6($utime)
 	  {
		$t=getdate($utime);
 		return array('hour' => $t['hours'], 'minute' => $t['minutes'],
					 'second' => $t['seconds'],'month' => $t['mon'],
					 'mday' => $t['mday'],'year' => $t['year']);
 	  }


	  /** 
 	   * Convert  a 6 field hash array in the current active timezone to a unix timestamp.
 	   * 
	   *  This is basically the inverseof php getdate() function.
	   * 
	   *  The a6date array has fields as in the php getdate() function:
	   * - <code>year</code> four digit year field
	   * - <code>month</code> integer month number <b> note: mon, not month!! </b> 
	   * - <code>mday</code> integer day of month number 
	   * - <code>hour</code> integer hour
	   * - <code>minute</code> integer minutes
	   * - <code>second</code> integer seconds
	   *
	   * @param array  $a6 The date in a6date in local timezone format.
	   * @return int  a unixtimestamp assumed in utc timezone
	   */
	  function a6toutime ($a6)
	  {
		return mktime($a6['hour'],$a6['minute'],$a6['second'],
					  $a6['month'],$a6['mday'],$a6['year']);
	  }


	  /**
	   * Convert the egw person id and its participant status into
	   * an ATTENDEE value and parameterslist
	   *
	   * The resulting value of the ATTENDEE field will be in CAL_ADDRESS type format.
	   * The  resulting parameterlist may contain  fields of the following:
	   *  - <code> ROLE={CHAIR|REQ-PARTICIPANT|OPT-PARTICIPANT|NON-PARTICIPANT} </code>
	   *     this parameter is NOT used by eGW atm.
	   *  - <code> RSVP={TRUE|FALSE} </code>
	   *    resonse is expected, not set in eGW then status will have value <code>U</code>.
	   *  - <code> PARTSTAT={NEEDS-ACTION|ACCEPTED|DECLINED|TENTATIVE|DELEGATED|
	   *           COMPLETED|IN-PROGRESS} </code> everything from delegated is NOT used by eGW atm.
	   *  - <code> CUTYPE={INDIVIDUAL|GROUP|RESOURCE|ROOM|UNKNOWN} </code> only GROUP or INDIVIDUAL
	   *    are produced atm.
	   *
	   * @param int $pid egw id of a participant
	   * @param array $partstat egw particpant status of person with $uid
	   * @param int $owner_id id of the owner of the todo or event (needed to set the CHAIR)
	   * @return array ($val,$params) list with value and parameter-array for ATTENDEE property
	   * @note no error handling atm
	   */
	  function mki_vp_4ATTENDEE($pid,$partstat,$owner_id)
	  {
		$atdval = $this->mki_v_CAL_ADDRESS($pid);
		// first parameter
		$atdpars = $this->mki_p_CN($pid);
		$atdpars['ROLE'] = ($pid == $owner_id) ? 'CHAIR' : 'REQ-PARTICIPANT';
		$atdpars['RSVP'] = $partstat == 'U' ? 'TRUE' : 'FALSE';
		$atdpars['CUTYPE'] = $GLOBALS['egw']->accounts->get_type($uid) == 'g'
		  ? 'GROUP' : 'INDIVIDUAL';
		$atdpars['PARTSTAT'] = $this->partstatus_egw2ical[$partstat];

		return array($atdval,$atdpars);
	  }


	  /**
	   * Make a value of type RECUR for a ical RRULE property
	   *
	   * A simple example: 
	   * <code> ( RRULE) : (FREQ=MONTHLY;COUNT=10;INTERVAL=2) </code>
	   * here the first part between parenthesis is property and the
	   * second is a value of type RECUR
	   *
	   * @param string $recur_type the type of recurrence frequence we have 
	   * @param mixed $recur_data Todo describe this parameter...
	   * @param int $recur_interval Todo describe this parameter...
	   * @param utime $recur_enddate the final date that the recurrence ends
	   * @return string ($recurval) a value format as RECUR for the RRULE property
	   * (if a time is set)
	   */
	  function mki_v_RECUR($recur_type,$recur_data,$recur_interval,$recur_start,$recur_enddate)
	  {
		$recur = array();
		$recurval ='FREQ=' . $this->recur_egw2ical[$recur_type];

		switch ($recur_type) {
		case MCAL_RECUR_WEEKLY:
		  $days = array();
		  foreach($this->recur_days as $did => $day)	{
			if ($recur_data & $did)
			  $days[] = strtoupper(substr($day,0,2));
		  }
		  $recur['BYDAY'] = implode(',',$days);
		  break;
		case MCAL_RECUR_MONTHLY_MDAY:	// date of the month: BYMONTDAY={1..31}
		  $recur['BYMONTHDAY'] = (int) date('d',$recur_start);
		  break;
		case MCAL_RECUR_MONTHLY_WDAY:	// weekday of the month: BDAY={1..5}{MO..SO}
		  $recur['BYDAY'] = (1 + (int) ((date('d',$recur_start)-1) / 7))
			. strtoupper(substr(date('l',$recur_start),0,2));
		  break;
		}

		if ($recur_interval > 1)
		  $recur['INTERVAL'] = $recur_interval;

		if ($recur_enddate){

// $expdt= $this->hi->_exportDateTime($recur_enddate);	
// error_log('EXPORT UNTIL=' . $recur_enddate . ' expdDT:' .  $expdt);

		  $recur['UNTIL'] = $this->hi->_exportDateTime($recur_enddate);	
		}
		foreach($recur as $parnam => $parval){
		  $recurval .= ';' . $parnam . '=' . $parval;
		}
		return $recurval;
	  }

	  /**
	   * Make a value (commasep string of dates) for the EXDATE property
	   *
	   * In the conversion you can chose between a commastring of DATES or DATE-TIMES
	   * @param array $recur_exceptions  list with utime exception dates
	   * @param boolean $dtmode if true generate DATE-TIME dates else DATES
	   * @return array ($exdval, $exdparams) a list with the value and parameters generated
	   */
	  function mki_vp_4EXDATE($recur_exceptions,$dtmode=false)
	  {
		$days = array();
		foreach($recur_exceptions as $day) {
		  $days[] = date('Ymd',$day);
		}

		$exdparams = array();
		if(!$dtmode)
		  $exdparams['VALUE'] = 'DATE';
		return array( implode(',',$days), $exdparams);
	  }

	  /**
	   * Convert DDT possible DATE|DATE-TIME params and a value commalist
	   * into an array of utime dates.
	   *
	   * Some examples
	   * <PRE>
	   * ex1: ...;VALUE=DATE:20060123,20060124
	   * ex2: ...:20060118T101500Z,20060119T1000Z
	   * ex3: ...:VALUE=DATE-TIME:20060118T101500Z,20060119T1000Z
	   * </PRE>
	   * @note unfortunately horde_icalendar will parse ex1 into an array of
	   *  array(month => .. , mday => .. ,  year=> )
	   * @param array $dvals list of dates
	   * @return array $udays list with the days from the input list in utime format
	   */
	  function mke_EXDATEpv2udays($params, $dvals)
	  {
		//$exdays = (!is_array($dvals)) ? $dvals : array($dvals);
		$exdays = $dvals;

		if (count($exdays) < 1)
		  return false;
//error_log('EXDAYS params=' . print_r($params,true));
//error_log('EXDAYS exploded=' . print_r($exdays,true));
		if($params['VALUE'] == 'DATE'){
		  // list is in awful horde DATE mode
		  // convert the date somehow to udays
		  $udays = array();
		  foreach ($exdays as $day){
			$udays[] = $this->mke_DDT2utime($day);
		  }
		} else {
		  // assume list is in DT mode
		  $udays = &$exdays;
		}

		return $udays;
	  }



	  /**
	   * Convert a RECUR value into the corresponding egw recur fields.
	   *
	   * A value of type RECUR (for a ical RRULE property) is parsed
	   * into the 4 related egw fields. Fields unfilled stay false A
	   * simple example: <code> ( RRULE) :
	   * (FREQ=MONTHLY;COUNT=10;INTERVAL=2) </code> here the first
	   * part between parenthesis is property and the second is a
	   * value of type RECUR
	   *
	   * @bug RECUR: MONTHLY;BYMONTHDAY,  only ok if startdate is also on this MONTHDAY
	   * egw problem. 
	   *
	   * @todo RECUR: COUNT=xx;WEEKLY;BYDAY, may miss the last occurence, if not started
	   * on a BYDAY day: to be fixed! prio=low
	   *
	   * @todo RECUR: YEARLY seems only to support the most basic variant?? To be checked!
	   *
	   * @author JVL (required some thinking..)
	   * @param string $recur RECUR type value of RRULE
	   * @param mixed $rstart start date in UTC format
	   * @return  array $rar a assoc array with keys: 'recur_type', 'recur_data', 'recur_interval' 
	   *  and 'recur_enddate'. On error:  false
	   * @note the class var @ref $hi is used as auxiliary Horde_iCalendar object  
	   */
	  function mke_RECUR2rar($recur,$rstart)
	  {
		$ustart = $this->mke_DDT2utime($rstart);

		// a6sd is in Icalsrv usertime
		$a6sd = $this->utimetoa6($ustart);

// error_log('IMPORT RECURVAL=' . $recur . 'ustart=' .$ustart);


		$r_data = 0; 
		$dow =array(); // for weekly count calc
		$r_type = $r_interval = $r_end = $r_count = false;

		$type = preg_match('/FREQ=([^;: ]+)/i',$recur,$matches)
		  ? $matches[1] : $recur[0];
		if ($type == false)
		  return false;

		// vCard 2.0 values for all types
		if (preg_match('/UNTIL=([0-9TZ]+)/',$recur,$matches))
		  $r_end = $this->hi->_parseDateTime($matches[1]);

		if (preg_match('/INTERVAL=([0-9]+)/',$recur,$matches))
		  $r_interval = (int) $matches[1];

		// with count given we must calculate r_end
		if (preg_match('/COUNT=([0-9]+)/',$recur,$matches)){
		  $r_count = (int) $matches[1];
		  // count calculation auxvars
		  $c_interval = ($r_interval) ? $r_interval : 1; //interval
		  $c_count = ($r_count - 1)*$c_interval;
		}

		switch($type) {

		case 'W':
		case 'WEEKLY':
		  $days = array();
		  if(preg_match('/W(\d+) (.*) (.*)/',$recur, $recurMatches)) {		// 1.0
			$r_interval = $recurMatches[1];
			$c_interval = $r_interval;
			$days = explode(' ',trim($recurMatches[2]));
		  }	elseif (preg_match('/BYDAY=([^;: ]+)/',$recur,$recurMatches)) {	// 2.0
			$days = explode(',',$recurMatches[1]);
		  }
		  if ($days) {
			foreach($this->recur_days as $mid => $day) {
			  if (in_array(strtoupper(substr($day,0,2)), $days)){ //WAS ERROR IN BOICAL!!
				$r_data |= $mid; 
			  }
			}
			$r_type = MCAL_RECUR_WEEKLY;
		  }
		  // --------- r_end calculation from COUNT and BYDAYs ---
		  if ($r_count) { 
			$c_count = ($r_count - 1)*$c_interval; 
			foreach($days as $wdd){
			  $dow[] = $this->dowseqid[$wdd];
			}
			sort($dow);
			$ustart_seqid = $this->dowseqid[strtoupper(substr(date('D',$ustart),0,2))];
             // find index of start day 0.. 
			$sdi = 0;                               //in case start is not on a byday
			foreach($dow as $i) {
			  if ($dow[$i] == $ustart_seqid){      // hope start is on byday
				$sdi = $i; break;
			  } elseif($dow[$i] >= $ustart_seqid){ // else next byday
				$sdi = $i; break;
			  }
			}
			$edi = $sdi + $c_count;        // end day index
			$dur = 0;                      // duration until end in days
			$wic = count($dow);            // week indexes count
			$dur = 7 * floor($edi / $wic) + $dow[($edi % $wic)];
			$dur -= $dow[$sdi];
			$a6sd['mday'] += intval($dur);
			$r_end = $this->a6toutime($a6sd);
			// destroy $a6sd here..

//error_log('count=' . $c_count .'sdi=' . $sdi . ' edi=' . $edi .
//		  ' wic=' .$wic . ' dur=' .$dur . ' r_end=' . $r_end);
		  }
		  break;

		case 'D':		// 1.0
		  if(!preg_match('/D(\d+) (.*)/',$recur, $recurMatches))
			break;
		  $c_interval = $r_interval = $recurMatches[1];
		  $r_end = $this->hi->_parseDateTime($recurMatches[2]);
		  // fall-through

		case 'DAILY':	// 2.0
		  $r_type = MCAL_RECUR_DAILY;
		  if ($r_count) { 		  // count calc is still experimental!
			$c_count = ($r_count - 1)*$c_interval; 
			$a6sd['mday'] += $c_count;  
			$r_end = $this->a6toutime($a6sd);
		  }
		  break;

		case 'MONTHLY':
		  $r_type = strstr($recur,'BYDAY') ? 
			MCAL_RECUR_MONTHLY_WDAY : MCAL_RECUR_MONTHLY_MDAY;
		  //		  break;										
		  //fall through

		case 'M':
		  if(preg_match('/MD(\d+) (.*)/',$recur, $recurMatches)) {
			$r_type = MCAL_RECUR_MONTHLY_MDAY;
			$c_interval = $r_interval = $recurMatches[1];
		  }	elseif(preg_match('/MP(\d+) (.*) (.*) (.*)/',$recur, $recurMatches)) {
			$r_type = MCAL_RECUR_MONTHLY_WDAY;
			$c_interval = $r_interval = $recurMatches[1];
		  }
		  // 
		  if ($r_count) { 		  // count calc is still experimental!
			switch ($r_type) {
			case MCAL_RECUR_MONTHLY_MDAY:
			  // error_log('DOING MOTNHLY MDAY'); Egw doesnot handle this special, see todo
			  $c_count = ($r_count - 1)*$c_interval; // maybe changed because 1.0 found
			  $a6sd['month'] += $c_count;  
			  $r_end = $this->a6toutime($a6sd);
			  break;

			case MCAL_RECUR_MONTHLY_WDAY:
			  $c_count = ($r_count - 1)*$c_interval; // maybe changed because 1.0 found
			  // startday 
			  $dowsd = date('w',$this->a6toutime($a6sd)); // day of week for sd 
			  //end day, first try
			  $a6ed = array_diff($a6sd,array());  $a6ed['month'] += $c_count;  
			  //day1 of startmonth
			  $a6smd1 = array_diff($a6sd,array()); $a6smd1['mday'] = 1;
			  $dowsmd1 = date('w',$this->a6toutime($a6smd1)); // day of week for smd1 

			  //startdate as day of 5week segment, anchored on and afer smd1
			  $do5wsegsd = $dowsmd1 + $a6sd['mday'];
			  if($dowsmd1 > $dowsd)
				$do5wsegsd -= 7;

			  $a6ed['mday'] =1;
			  $dowemd1 = date('w',$this->a6toutime($a6ed)); // endmonthday1 as day of week
			  $a6ed['mday'] = $do5wsegsd - $dowemd1;
			  if($dowemd1 > $dowsd)
				$a6ed['mday'] += 7;

//  error_log('dowsd='. $dowsd . ' dowsmd1='. $dowsmd1 . ' do5wsegsd=' . $do5wsegsd .
// 			' dowemd1='. $dowemd1 . ' edmday=' . $a6ed['mday'] );
			  $r_end = $this->a6toutime($a6ed);
			  break;
			}
		  }
		  break;


		case 'Y':		// 1.0
		  if(!preg_match('/YM(\d+) (.*)/',$recur, $recurMatches))
			break;
		  $c_interval = $r_interval = $recurMatches[1];
		  // fall-through

		case 'YEARLY':	// 2.0
		  $r_type = MCAL_RECUR_YEARLY;
		  if ($r_count) { 		  // count calc is still experimental!
			// is there only this BYMONTHDAY support?
			$c_count = ($r_count - 1)*$c_interval; // maybe changed because 1.0 found
			$a6sd['year'] += $c_count;  
			$r_end = $this->a6toutime($a6sd);
		  }
		  break;
		}

		return array('recur_type'     => $r_type,
					 'recur_data'     => $r_data,
					 'recur_interval' => $r_interval,
					 'recur_enddate'  => $r_end );
	  }


	  /**
	   * Parse a CAL_ADDRESS and try to find the associated egw person_id
	   * @param  string $attrval CAL_ADDRESS type value string
	   * @return int|false $pid  associated (by email) egw pid. On error: false.
	   */
	  function  mke_CAL_ADDRESS2pid($attrval)
	  {
		if (preg_match('/MAILTO:([@.a-z0-9_-]+)/i',$attrval,$matches) &&
			($pid = $GLOBALS['egw']->accounts->name2id(strtolower($matches[1]),'account_email'))){
		  return $pid;
		} else {
		  return false;
		}
	  }

	  /**
	   * Parse a CAL_ADDRESS and PARAMS to find the CN name and email
	   * @param string $aval CAL_ADDRESS type value string
	   * @param array  $aparams parameters for a ATTENDEE
	   * @return array $cneml  assoc array with 'cn' and 'mailto' field
	   */
	  function  mke_ATTENDEE2cneml($aval,$aparams)
	  {
		$cneml = array('cn' => '', 'mailto' => '');
		
		//		if (preg_match('/MAILTO:([@.a-z0-9_-]+)/i',$aval,$matches))
		// lets be a bit more relaxed here (rfc1378)..
		// try for "Fnam Lnam <a@b.c>" first
		if (preg_match('/MAILTO:([^<]+)<([@.a-z0-9_-]+)>/i',$aval,$matches)){
		  $cneml['cn'] = $matches[1];
		  $cneml['mailto'] = $matches[2];
		  // try for <a@b.c> second
		}elseif (preg_match('/MAILTO:([@.a-z0-9_-]+)/i',$aval,$matches)){
		  $cneml['mailto'] = $matches[1];
		}
		// a CN from the params overrules one from the mailto
		if(isset($aparams['CN']))
		  $cneml['cn'] = $aparams['CN'];

		return $cneml;
	  }

 	  /**
	   * Update an egw event description with a list of nonegw participants.
	   *
	   * note: this is a adhoc solution, preferably the nonegw participants
	   * should be added automatically to the addressbook
	   * @param string &$edescription the participants are append to this string as
	   * a string formatted ([cn: name:mailto: eml] [] ... )
	   * @param array &$ne_participants array of the non egw participants as
	   * ('cn' =>, 'mailto' =>) pairs
	   * @return true
	   */
	  function upde_nonegwParticipants2description(&$edescription,&$ne_participants)
	  {
		$edescription.= "\n - non egw participants:\n(";
		$neplist = array();
		foreach ($ne_participants as $nep){
//		  $li =  '[cn:' . $nep['cn'];
//		  $li .= ($nep['mailto']) ? ';mailto:' . $nep['mailto'] .']' : ']';
		  $li = '[' . $nep['cn'];
		  $li .= ($nep['mailto']) ? '<' . $nep['mailto'] .'>]' : ']';

		  $neplist[]= $li;
		}
		$edescription .= implode("\n",$neplist) . ')';

		return true;
	  }


	  /**
	   * Search a ical parameterlist for possible setting for a egw participant status.
	   *
	   * Parse the params array to find a PARTSTAT param, convert this to
	   * a egw partstatus (may occur e.g. in ATTENDEE params)
	   * @param array $params  params of e.g. an ical ATTENDEE field
	   * @return array|false $epartstatus egw term for particpant status if detected else false 
	   */
	  function mke_params2partstat($params)
	  {
		if (!isset($params['PARTSTAT']))
		  return false;

		return $this->partstatus_ical2egw[strtoupper($params['PARTSTAT'])];
	  }

	  /**
	   * Add (append) an new attribute (aka field) to the vevent.
	   *
	   * @param horde_iCalendar_vevent $vevent obj to which the attribute is added
	   * @param string $aname  name for the new attribute
	   * @param mixed $avalue  value for the new attribute
	   * @param array $aparams  parameters for the new attribute
	   * @return true
	   */
	  function addAttributeOntoVevent(&$vevent,$aname,&$avalue,&$aparams)
	  {
		
		// it appears that translation->convert() can translate an array
		// (that is: the values!, not the keys though)
		// so lets apply it to the avalue and aparams, that should be enough!
//		error_log('n:' . $aname . 'v:' . $avalue);
		$valueData = 
		  $GLOBALS['egw']->translation->convert($avalue,
												$GLOBALS['egw']->translation->charset(),
												'UTF-8');
		$paramData =
		  $GLOBALS['egw']->translation->convert( $aparams,
												 $GLOBALS['egw']->translation->charset(),
												 'UTF-8');
//		error_log('n:' . $aname . 'v:' . $valueData);
		$vevent->setAttribute($aname, $valueData, $paramData);
		$options = array();
		// JVL:is this really needed?
		if (is_string($valueData)){

// // JVL: TEMPORARY SWITCHED OFF... TURN ON AGAIN!!!
// 		  if(!(in_array($aname, array('RRULE')))
// 			 && preg_match('/([\000-\012\015\016\020-\037\075])/',$valueData)) {
// 			$options['ENCODING'] = 'QUOTED-PRINTABLE';
// 		  }

		  if(  (preg_match('/([\177-\377])/',$valueData))) {
			$options['CHARSET'] = 'UTF-8';
		  }
		}
		$vevent->setParameter($aname, $options);

		return true;
	  }


	  /**
	   * Convert egw alarm info to a ical VALARM object.
	   *
	   * Make a VALARM object form data in $alarms and $utstart (in utc)
	   * and with $vevent as container
	   * @param array &$alarm a single egw alarm array to be used
	   * @param horde_object &$vcomp  that will be the container for the valarm
	   *        mostly vevent or vtodo.
	   * @param array &$veExportFields  list with fields that may get imported
	   * @return horde_iCalendar_valarm|false valarm object or, on error, false.
	   */
	  function mki_c_VALARM(&$alarm, &$vcomp, $utstart,&$veExportFields){

//		error_log('export comp-alarm-field:' . print_r($alarm,true));

		$valarm = Horde_iCalendar::newComponent('VALARM',$vevent);

		//try first an offset
		if($durtime = -$alarm['offset']){
		  $valarm->setAttribute('TRIGGER',
								$durtime,
								array('VALUE' => 'DURATION',
									  'RELATED' => 'START'));
		  // no success then try a date-time
		} elseif($dtime = $alarm['time']){
		  $valarm->setAttribute('TRIGGER',
								$ddtime,
								array('VALUE' => 'DATE-TIME'));
		} else{
		  $valarm = null;
		  return false;
		}
		$vcomp->addComponent($valarm);

		return $valarm;
	  }

	  
 	  /**
	   * Update the egw alarms array with info from a VALARM
	   * @param array &$alarms  the the egw alarms array to be updated
	   * @param horde_iCalendar_valarm $valarm ref to the valarm  component to be updated
	   * @param int $user_id  the user that will own the alarms found
	   * @param array &$veImportFields  with fields that may get imported
	   * @return true
	   */
	  function upde_c_VALARM2alarms(&$alarms,&$valarm,$user_id,&$veImportFields){

		// lets see what supported veImportFields we can get from the valarm
		foreach($valarm->_attributes as $vattr) {
 //		  $vattrval = $GLOBALS['egw']->translation->convert($vattr['value'],'UTF-8');
		  // handle only supported fields
		  if(!in_array('VALARM/' . $vattr['name'], $veImportFields))
			continue;
		  
		  switch($vattr['name']) {
		  case 'TRIGGER':
			$vtype = (isset($vattr['params']['VALUE']))
			  ? $vattr['params']['VALUE'] : 'DURATION'; //default type
			  switch ($vtype) {
			  case 'DURATION':
				$alarms[] = array('offset' => -$vattr['value']); 					
				break;
			  case 'DATE-TIME':
				$alarms[] = array('time' => $vattr['value']); 					
				break;
			  default:
				// we should also do ;RELATED=START|END
				error_log('VALARM/TRIGGER: unsupported value type:' . $vtype);
			  }
			  break;
// 		  case 'ACTION':
// 				break;
// 		  case 'DISPLAY':
// 				break;

		  default:
			error_log('VALARM field:' .$vattr['name'] .':'
					  . print_r($vattrval,true) . ' HAS NO CONVERSION YET');
		  }
		}
//		error_log('updated alarms to:' . print_r($alarms,true));
		return true;
	  }






	}

?>