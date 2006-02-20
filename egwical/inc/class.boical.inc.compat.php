<?php
	/**
	* @file
	* eGroupWare - compatibility replacement for file calendar/inc/class.boical.inc.php
	* to start using the new egwical routines.
	*
	* http://www.egroupware.org                                                *
	* @author Jan van Lieshout                                              *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License.              *
	* @version 0.9.00
	\**************************************************************************/

  /* THIS CLASS IS JUST HERE FOR BACKWARD COMPATIBILITY  */
  /* in future you should rewrite ical handling using the egwical class */

//	require_once EGW_SERVER_ROOT.'/egwicalsrv/inc/calendar/class.bovevents.inc.php';


	class boical extends bocalupdate
	{
	  // introduce auxilliary egwical object
	  var $ei; 

	  // introduce a worker obj (also accessible via $ei but this is shorter..)
	  var $wkobj;

	  function boical()
	  {
		bocalupdate::bocalupdate(); // call superclass constructor
		error_log("warning class: calendar.boical call DEPRECATED," .
				  "\nplease rewrite your code to use egwical class" .
				  "\n now temporary code fix used ");
		// The longer road, using egwicals cleverness:
				$this->ei =& CreateObject('egwical.egwical');
				$this->wkobj =& $this->ei->addRsc($this);
		// alternatively the fast, shortcut, road for knowingly experts only:
		//$this->wkobj =& CreateObject('egwical.bocalupdate_vevents');
		//$this->wkobj->setRsc($this);

		if ($this->wkobj == false){
		  error_log('boical constructor: couldnot add boical resource to egwical: FATAL');
		  return false;
		}
	  }


	  // now implement the compatibility methods, that are all moved to egwical!

	  function &exportVCal($events,$version='1.0',$method='PUBLISH')
		{
		  return $this->wkobj->exportVCal($events,$version,$method);
		}

	  function importVCal($_vcalData, $cal_id=-1)
	  {
		return $this->wkobj->importVCal($_vcalData, $cal_id);
	  }


	  function setSupportedFields($_productManufacturer='file', $_productName='')
	  {
		return $this->wkobj->setSupportedFields($_productManufacturer, $_productName);
	  }

	}
?>
