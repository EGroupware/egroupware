<?php
	/**
	 *@file 
	* eGroupWare - iCalendar VTODOS conversion, import and export for egw infolog
	* application.
	*
	* http://www.egroupware.org                                                *
	* @author Jan van Lieshout                                         *  
	* based on class.boical.inc.php and on class.vcalinfolog.inc.php
	* originals written by Lars Kneschke <lkneschke@egroupware.org>            *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License.              *
	**************************************************************************/

  /* JVL Todo V0.7:
   * - add structure and API terminology from class.bovevents.inc.php:DONE
   * - rewrite all vevent to vtodo strings:  DONE..
   * - Maybe add a supportFields system as done in calendar.bovevents, to allow for
   *   handling vtodos for various devices
   * - if done document the supportFields method and show how it can be used 
   * - find out how to do deletion based on imported VTODOS ? Can that be done?
   * - check the usage and conversions of user time and server times
   * - add compatibility API for the class infolog.vcalinfolog: DONE but UNTESTED
   * - add ORGANIZER export: DONE (V0.51) removed (dont know map field)
   * - add ORGANIZER import: ... maybe map t info_responsible
   * - add CATEGORIES export: DONE (V0.52)
   * - add CATEGORIE import: DONE (V0.7.01)
   * - add "subtask" export: DONE (v0.52)
   * - add "subtask" import: PARTLY
   * - rewrite PRIORITY export: DONE (V0.52) 
   * - rewrite PRIORITY import:DONE (V0.7.01)
   * - repair datecreated: DONT know map field
   * - repair date modified export: PARTLY done
   * - repair startdate or enddate without time details: DONE (V0.7.02)
   */


  //     require_once EGW_SERVER_ROOT.'/infolog/inc/class.boinfolog.inc.php';
     require_once EGW_SERVER_ROOT.'/phpgwapi/inc/horde/Horde/iCalendar.php';
//     require_once EGW_SERVER_ROOT.'/icalsrv/inc/class.egwical.inc.php';

    /**
	 *
	 * iCal vtodos import and export via Horde iCalendar classes
	 * @note the routines in this package should be used OO only so that de constructor
	 *        can initialize the data common to the import and export routines
	 * @note this package provides compatibilty routines for class infolog.vcalinfolog
	 *        this can e.g. be used by making infolog.vcalinfolog a simple extension of
	 *        infolog.bovtodos
	 *
	 * @todo move the compatibility functions for vcalinfolog completely to the compat class.
	 *  There is no need to have them here anymore.
	 * @todo rewrite bovtodos to use a ical2egw and supportedFields system
	 * @todo <b>IMPORTANT</b> rewrite bovtodos to handle uid_matching analogous to bovevents
 	 *
	 * @package egwical
	 * @author Jan van Lieshout <jvl (at)xs4all.nl> This version.
	 * @author Lars Kneschke <lkneschke@egroupware.org> (parts of reused code)
	 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de> (parts of reused code)
	 * @version 0.9.02 First for use with new WURH egwical class
	 * @license http://opensource.org/licenses/gpl-license.php GPL -
	 *  GNU General Public License
	  */

    class boinfolog_vtodos extends egwical
	{

	  /**
	   * @var object
	   * The egw infolog object that will be used to transport events from and to
	   * This is set by setRsc()
	   */
	  var $myinf = null;


	  /**
	   * Describe the provided work capabilities of the class.
	   * @return string The description as entries for the @ref $reg_workers registry
	   * table.
	   */
	  function provides_work()
	  {
		return 
		  array('boinfolog' => array('workerclass' => 'boinfolog_vtodos',
									 'workerobj'   =>  $null,
									 'icalsup'   => array('VTODO')),
				'vcalinfolog' => array('workerclass' => 'boinfolog_vtodos',
									   'workerobj'   =>  $null,
									   'icalsup'   => array('VTODO'))
				);
		
	  }


	  /*
	   * Our Constructor, fills the basic class members
	   * and set the description of our worker capabilities.
	   */
	  function bovtodos() {

		// call superclass constructor by hand
		boinfolog::boinfolog();

		$this->TASKMAGIC = $GLOBALS['egw_info']['server']['install_id']
		  ? $GLOBALS['egw_info']['server']['install_id']
		  : 'local'; 

		// $this->setSupportedFields(); //not implemented yet
		return true;
	  }


	  /** 
	   * Set the egw resource  that this worker will handle.
	   * This worker is only capable of handling  boinfolog  objects, so it should
	   * be of that class. This method is mostly called indirectly from a egwical compound
	   * addRsc() call. But you can call it also directly (if you know what your doing ..)
	   * @return boolean false on error, true  else
	  */
	  function setRsc($egw_rsc)
	  {
		if(!is_a($egw_rsc,'boinfolog'))
		  return false;
		$this->myinf = $egw_rsc;
		return true;
	  }


	  // --- conversion and import code --

	  /**
	   * @private
	   * @var $TASKMAGIC
	   * Magic unique number used for de/encoding our uids.
	   *
	   * This string that contains global unique magic number that is
	   *  unique for our current database installed etc. It is used to recognize
	   *  earlier exported VTODO or VEVENT UID fields as referring to their eGW counterparts.
	   */
	  var $TASKMAGIC='dummy';




	  // Some helper functions first


	  /**
	   * generate a unique id, with the todo id encoded into it, which can be
	   * used for later synchronisation.
	   *
	   * @param $todo_id string|int eGW id of the content
	   * @use  $TASKMAGIC  string that holds our unique ID
	   * @return false|string on error: false
	   *                      on success the global unique id
	   */
	  function _id2guid($todo_id)
	  {
		if (empty($todo_id))
		  return false;

		return 'infolog_task'.'-'.$todo_id.'-'. $this->TASKMAGIC;
	  }



	  /**
	   * get the local content id from a global UID
	   *
	   * @param string $globalUid the global UID
	   * @return false|int on error: false
	   *                   on success: local egw todo id
	   */
	  function _guid2id($VTodoUID)
	  {
		//		error_log('_guid2id: trying to recover id from' . $VTodoUID);
		if (!preg_match('/^infolog_task-(\d+)-' .
						$this->TASKMAGIC . '$/',$VTodoUID,$matches))
		  return false;

		//		error_log("_guid2id: found (" . $matches[1] . ")");		
		return $matches[1];
	  }



	  /**
	   * export the  eGW todos in $todos to iCalendar VTODOS and add these to
	   * the Horde_iCalendar object &$hIcal
	   * Note: that because eGW does not store uid fields for tasks in its db we
	   *       are in general not able to recoginize VTODOS by their uid-field.
	   *      Because of this it is only possible to have a VTODO overwrite an internal
	   *      eGW todo (task) when this VTODO was in an earlier fase build as export of an 
	   *      internal eGW todo. In other words to later on change your imported VTODO,
	   *      you first have export it and in the client make your changes on this exemplar.
	   *
	   * @param &$hIcal      Horde_iCalendar   object to wich the produced VTodos are added
	   * @param $todos      array     with either id s (tids) for a eGW  todoData structs
	   *                     or an array of such todoData structs, that will be exported
	   * @param boolean $euid_export if true export the uid field (Note: Currently not available!)
	   * else generate a uid from with the task id encoded (Default setting) 
	   * @return $ok/$vcnt boolean/int   on error: false / on success: nof vtodos exported 
	   * @use members supportedFields(), _id2guid()
	   */
	  function exportTodosOntoIcal(&$hIcal, $todos, $euid_export=false)
	  {
		//NOTE: $euid_export has currently no effect
#		  error_log("ical_export_add_Todos here, for " . count($todos) . "todos");

		$todo = array();  // container for each todo to be exported
		$tid = null;      // id of the todo to be exported
		$vexpcnt =0; // number of vtodos exported
#		$options = array('CHARSET' => 'UTF-8','ENCODING' => 'QUOTED-PRINTABLE');


		if (!is_array($todos)) $todos = array($todos);
		  
		foreach($todos as $todo) {
		  // some hocuspocus to handle the polymorphy of the $todos arg
		  if (!is_array($todo)   
			  && !($todo = $this->myinf->read($todo))){
			
			return false;	// no permission to read $tid
		  }
		  $tid = $todo['info_id'];
		  // oke, now sure $todo is a todoData array and $tid its info_id field..
		  //_debug_array($todo);
		  
		  $todo = $GLOBALS['egw']->translation->
			convert($todo,$GLOBALS['egw']->translation->charset(),'UTF-8');

#		  error_log('todo to export=' . print_r($todo,true));

		  //someday: $this->newComponent() ???
		  $vtodo = Horde_iCalendar::newComponent('VTODO',$hIcal);

		  $vGUID = $this->_id2guid($tid);

		  //		  if (!$euid_export)
		  // append Non Recoverable so _guid2id() wont recognize it later
		  //			$vGUID .= 'NR';  
		  $vtodo->setAttribute('UID',$vGUID);
		  // for subtasks set the parent
		  // egw2vtodo: info_id_parent => pid  -> RELATED-TO:parent_uid
		  if ($parid = $todo['info_id_parent'])
			$vtodo->setAttribute('RELATED-TO', $this->_id2guid($parid));

		  $vtodo->setAttribute('SUMMARY', $todo['info_subject']);
		  $vtodo->setParameter('SUMMARY', $options);
		  $vtodo->setAttribute('DESCRIPTION', $todo['info_des']);
		  $vtodo->setParameter('DESCRIPTION', $options);
		  if($todo['info_startdate'])
			$vtodo->setAttribute('DTSTART', $todo['info_startdate']);
		  if($todo['info_enddate'])
			$vtodo->setAttribute('DUE', $todo['info_enddate']);
		  $vtodo->setAttribute('DTSTAMP',time());

		  $lastmodDate = $todo['info_datemodified'];
		  $vtodo->setAttribute('LAST-MODIFIED', $lastmodDate );

		  if ($createDate = $this->get_TSdbAdd($tid,'infolog')){
			$vtodo->setAttribute( 'CREATED', $createDate);
		  } else {
			$vtodo->setAttribute( 'CREATED', $lastmodDate);
		  }

		  // egw2VTOD: owner -> ORGANIZER field 
		  if ($tfrom_id = $todo['info_owner']){
			$mailtoOrganizer = $GLOBALS['egw']->accounts->id2name($tfrom_id,'account_email');
			$vtodo->setAttribute('ORGANIZER', $this->mki_v_CAL_ADDRESS($tfrom_id));
			$vtodo->setParameter('ORGANIZER', $this->mki_p_CN($tfrom_id));
		  }

		  $vtodo->setAttribute('CLASS',
							   ($todo['info_access'] == 'public')?'PUBLIC':'PRIVATE');
		  // CATEGORIES, value= all category names from info_cat field  comma-separated list
		  // n.b. dont mind catid ==0 (this is none categorie, I think)
		  if ($catids = $todo['info_cat']){ 
			$catnamescstr = $this->cats_ids2idnamescstr(explode(',',$catids));
			$vtodo->setAttribute('CATEGORIES',$catnamescstr);
		  }


		  // egw2vtodo status trafo:
		  //    done -> COMPLETE:lastmoddate, PERCENT-COMPLETE:100, STATUS:COMPLETED 
		  //    ongoing -> STATUS: IN-PROCESS
		  //    offer ->  STATUS: NEEDS-ACTION, PERCENT-COMPLETE:0
		  switch ($todo['info_status']){
		  case 'done':
			$vtodo->setAttribute('COMPLETED',$lastmodDate); // for ko35, lastmod?
			$vtodo->setAttribute('PERCENT-COMPLETE','100');
			$vtodo->setAttribute('STATUS','COMPLETED');
			break;
		  case 'ongoing':
			$vtodo->setAttribute('STATUS','IN-PROCESS');
			break;
		  case 'offer':
			$vtodo->setAttribute('STATUS','NEEDS-ACTION');
#			$vtodo->setAttribute('PERCENT-COMPLETE',"0");
			break;
		  default:
			// check for percentages
			if (ereg('([0-9]+)%',$todo['info_status'],$matches)){
			  $vtodo->setAttribute('PERCENT-COMPLETE',$matches[1]);
			  $vtodo->setAttribute('STATUS','IN-PROCESS');
			}else{
			  $vtodo->setAttribute('STATUS','NEEDS-ACTION');			
			}
		  }

		  if (is_numeric($eprio = $todo['info_priority']) && ($eprio >0) )
			$vtodo->setAttribute('PRIORITY',
								 $this->mki_v_prio($eprio) );

#		  $vtodo->setAttribute('TRANSP','OPAQUE');
			
		  $hIcal->addComponent($vtodo);
		  $vexpcnt += 1;
		}

		return $vexpcnt; //return nof vtodos exported
	  }



	  /* @note PART OF COMPATIBILITY API for INFOLOG.VCALINFOLOG
	   * @note UNTESTED
	   * Export a single eGW task as a VTODO string
	   *
	   * @param $_taskID int/string id of the eGW task to be exported
	   * @param $_version string   version the produced iCalendar content should get
	   * @return false|string    on error | content of the resulting VTODO iCal element
	   */
	  function exportVTODO($_taskID, $_version)
	  {
		$hIcal = &new Horde_iCalendar;
		$hIcal->setAttribute('VERSION',$_version);
		$hIcal->setAttribute('METHOD','PUBLISH');
			
		if(! $tcnt = $this->exportTodosOntoIcal(&$hIcal, array($_taskID), true))
		  return false;

		return $hIcal->exportvCalendar();
	  }



	   /**
	   * Convert the ical VTODOS components that are contained in de $hIcal Horde_iCalendar
	   * to eGW todos and import these into the eGW calendar.
	   * Depending on the value of $importMode, the conversion will generate either eGW
	   * todos with completely new id s (DUPLICATE mode) or try to recover an egw id from
	   * the  VTODO;UID field (so called OVERWRITE mode). Note that because eGW currently
	   * does not store todo uid field info in its database, such recovering is only
	   * possible for previously exported todos.
	   *
	   * @param  &$hIcal  Horde_iCalendar   object with ical VTODO objects 
	   * @param  $importMode string         toggle for duplicate (ICAL_IMODE_DUPLICATE)
	   *                                    or overwrite (ICAL_IMODE_OVERWRITE) import mode
	   * @return $false|$timpcnt    on error: false | on success: nof imported elms
	   * @use .supportedFields()       to steer the VTODOS to eGW todos conversion
	   * @use members  _guid2id()
	   */
	  function importVTodosFromIcal(&$hIcal, $importMode='DUPLICATE')
	  {

		$overwritemode = stristr($importMode,'overwrite') ? true : false;
#		$ftid = $fixed_taskId;
		$timpcnt = 0;    // nof todos imported
		$tidOk = true;	 // return true, if hIcal contains no vtodo components

		foreach($hIcal->getComponents() as $component) {
		  // ($ftid < 0) => recover id (overwritemode) or use no id
		  // ($ftid > 0) => use this value to set the id (compatibility mode) 

		  if(is_a($component, 'Horde_iCalendar_vtodo')){
			$tidOk = $this->_importVTodoIcalComponent(&$component, $overwritemode, -1);
			if (!$tidOk){
			  error_log('infolog.bovtodos.importVTodosFromIcal(): '
						. ' ERROR importing VTODO ');
			  break;  // stop at first error
			} 


			$timpcnt += 1; // nof imported ok vtodos
		  }
		}
		return (!$tidOk) ? false : $timpcnt;
	  }


	  /* convert a single vtodo horde icalendar component to a eGW todo and write it to
	   * the infolog system.
	   *
	   * @note this routine should better not be exported
	   * @param &$hIcalComponent Horde_iCalendar_vtodo element that contains the VTODO
	   *                          that is to be converted and imported
	   * @param $overwriteMode boolean  generate a new eGW todo (when false) or allow 
	   *               overwrite of an existing one (when true)
	   * @param $newtask_id   int/string  if >0 : the id of the eGW todo that must be
	   *       overwritten. if <0 : generation of new task or recover taskId from UID field
	   * @return false | int    on error: false | on success: the id of the eGW todo
	   *                that was produced/changed
	   */
	  function _importVTodoIcalComponent(&$hIcalComponent, $overwriteMode, $newtask_id)
	  {
		$ftid = $newtask_id;
		$todo = array(); //container for eGW todo
		$user_id = $this->owner; // we logged in?

		if(!is_a($hIcalComponent, 'Horde_iCalendar_vtodo'))
		  return false;

		if($ftid > 0) {
		  // just go for a change of the content of the eGW task with id=$ftid
		  $todo['info_id'] = $ftid;
		  // we will now ignore a UID field later in this VTodo
		} 

		// now process all the fields found
		foreach($hIcalComponent->_attributes as $attributes) {
#         error_log( $attributes['name'].' - '.$attributes['value']);
		  //$attributes['value'] =
		  //	$GLOBALS['egw']->translation->convert($attributes['value'],'UTF-8');
		  switch($attributes['name']){
		  case 'UID':
			if ($ftid > 0)  // fixed id mode so we got id from $newtask_id
			  break;
			$vguid = $attributes['value'];
			if( $overwriteMode && $tid = $this->_guid2id($vguid)){
			  // an old id was recovered from the UID field, we will use it
			  $todo['info_id'] = $tid;
			  #error_log('import: using existing id:'.$tid);
			} // else we leave the info_id empty so automatically a new todo gets created
			break;
			// rfc s4.8.1.3.egw2vtodo: public|private|confidential 
		  case 'CLASS':
			$todo['info_access'] = strtolower($attributes['value']);
			break;
#		  case 'ORGANIZER':
#			$todo['info_from'] = $attributes['value'];
#           may be put it in info_responsible field ?
#			// full name + mailto see bovevents on a method to handle this
			break;

		  case 'DESCRIPTION':
			$todo['info_des'] = $attributes['value'];
			break;
		  case 'DUE':
			$todo['info_enddate'] = $this->mke_DDT2utime($attributes['value']);
			break;
		  case 'DTSTART':
			$todo['info_startdate']	= $this->mke_DDT2utime($attributes['value']);
			break;
		  case 'PRIORITY':
			$todo['info_priority'] = $this->mke_prio($attributes['value']);
			break;
		  // rfc s4.8.1.11 egw2vtodo status trafo: (now use it backwards)
		  //    done -> COMPLETE:lastmoddate, PERCENT-COMPLETE:100, STATUS:COMPLETED 
		  //    ongoing -> STATUS: IN-PROCESS
		  //    offer ->  STATUS: NEEDS-ACTION, PERCENT-COMPLETE:0
		  case 'STATUS':
			switch (strtolower($attributes['value'])){
			case 'completed':
			  $todo['info_status'] = 'done';
			  break;
			case 'cancelled':
			  $todo['info_status'] = 'done';
			  break;
			case 'in-process':
			  $todo['info_status'] = 'ongoing';
			   break;
			default:
			  // probably == 'needs-action'
			  $todo['info_status'] = 'offer';
			}
			break;
			// date and  time when completed
		  case 'COMPLETED':
			  $todo['info_status'] = 'done';
			  break;
		  case 'PERCENT-COMPLETE':
			$pcnt = (int) $attributes['value'];
			if ($pcnt < 1) {
			  $todo['info_status'] = 'offer';
			}elseif($pcnt > 99) {
			  $todo['info_status'] = 'done';
			}else{
			  $todo['info_status'] = $pcnt . '%'; // better should do rounded to 10s
			}
			break;
		  case 'SUMMARY':
			$todo['info_subject'] = $attributes['value'];
			break;
		  case 'RELATED-TO':
			$todo['info_id_parent'] = $this->_guid2id($attributes['value']);
			break;
			// unfortunately infolog can  handle only one cat atm
		  case 'CATEGORIES':
			$catnames = explode(',',$attributes['value']);
			$catids = $this->cats_names2idscstr($catnames,$user_id,'infolog');
			$todo['info_cat'] = $catids;
			break;

		  case 'LAST-MODIFIED':
			$todo['info_datemodified'] = $attributes['value'];
			break;

		  default:
//			error_log('VTODO field:' .$attributes['name'] .':'
//					  . $attributes['value'] . 'HAS NO CONVERSION YET');
		  }
		}
		//		error_log('todo=' . print_r($todo,true));
		
		if($todo['info_subject'] == 'X-DELETE' && $tid){
		  // delete the todo (secret HACK, donot use...)
		  return $this->myinf->delete($tid);
		}else{
		  $tidOk = $this->myinf->write($todo,true,false);
		  //  error_log('ok import id:'. $tidOk .' VTODO UID:' . $vguid);
		  return $tidOk;
		}
	  }





	  /* @note PART OF COMPATIBILITY API for INFOLOG.VCALINFOLOG
	   * @note UNTESTED
	   * Import a single  iCalendar VTODO string as eGW task(aka: todo)
	   *
	   * @param $_vcalData string   content of an VTODO iCalender element
	   * @param $_taskID int/string id of the eGW task to be overwritten
	   *                          when < 0 a new task will get produced    
	   * @return false | int    on error: false | on success: the id of the eGW todo
	   *                that was produced/changed
	   */
	  function importVTODO(&$_vcalData, $_taskID=-1)
	  {
		$hIcal = &new Horde_iCalendar;
		if(!$hIcal->parsevCalendar($_vcalData))
		  return FALSE;

		$components = $hIcal->getComponents();
		if(count($components) < 1)
		  return false;

		return $this->_importVTodoIcalComponent(&$components[0],
												true, $_taskID);
	  }


		
 	}


?>
