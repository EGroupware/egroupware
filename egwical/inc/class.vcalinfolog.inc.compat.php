<?php
	/**
	 * @file
	* eGroupWare - compatibility replacement for file infolog/inc/class.vcalinfolog.inc.php
	* to start using the new egwical routines.
	*
	* http://www.egroupware.org                                                *
	* @author Jan van Lieshout                                              *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License.              *
	* @package egwical
	* @version 0.9.02
	\**************************************************************************/

  /* THIS CLASS IS JUST HERE FOR BACKWARD COMPATIBILITY  */
  /* in future you should rewrite ical handling using the egwical class */
 

  //require_once EGW_SERVER_ROOT.'/icalsrv/inc/infolog/class.bovtodos.inc.php';

	class vcalinfolog extends boinfolog
	{
	  // introduce auxilliary egwical object
	  var $ei; 

	  // introduce a worker obj (also accessible via $ei but this is shorter..)
	  var $wkobj;

	  function vcalinfolog()
	  {
		bovtodos::bovtodos(); // call superclass constructor
		error_log("class: infolog.vcalinfolog DEPRECATED," .
				  "\nplease use in future class: infolog.bovtodos \n now auto delegating ");
		error_log("warning class: vcalinfolog.vcalinfolog call DEPRECATED," .
				  "\nplease rewrite your code to use egwical class" .
				  "\n now temporary code fix used ");
		// The longer road, using egwicals cleverness:
		//		$this->ei =& CreateObject('egwical.egwical');
		//		$this->wkobj =& $this->ei->addRsc($this);
		// or the fast, shortcut, road for knowingly experts only:
		$this->wkobj =& CreateObject('egwical.boinfolog_vtodos');
		$this->wkobj->setRsc($this);

		if ($this->wkobj == false){
		  error_log('boical constructor: couldnot add bocal resource to egwical: FATAL');
		  return false;
		}
	  }


	  // now implement the compatibility methods, that are all moved to egwical!

	  function exportVTODO($_taskID, $_version)
		{
		  return $this->wkobj->exportVTODO($_taskID, $_version);
		}

	  function importVTODO(&$_vcalData, $_taskID=-1)
	  {
		return $this->wkobj->importVTODO($_vcalData, $_taskID);
	  }


// 	  function setSupportedFields($_productManufacturer='file', $_productName='')
// 	  {
// 		return $this->wkobj->setSupportedFields($_productManufacturer, $_productName);
// 	  }

	}
?>
