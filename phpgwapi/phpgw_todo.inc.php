<?php
  /**************************************************************************\
  * phpGroupWare                                                             *
  * http://www.phpgroupware.org                                              *
  * This file written by Marc Logemann <logemann@marc-logemann.de>           *
  * --------------------------------------------------------------           *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */
class todo
{
    var $projectid;
    var $parent;
    var $owner;
    var $access;
    var $desc;
    var $priority;
    var $status;
    var $createdate;
    var $customernr;
    var $hours;
    var $deadline;
//    array $participants;

    var $db;				// database handle
    
    //========================================
    // CONSTRUCTOR
    //========================================
    function todo()
    {
    	// dont know yet
    }
    
    
    //========================================
    // adds a project into database (API)
    //========================================
    function addproject()
    {
    	global $phpgw_info, $phpgw;
    }

    //========================================
    // view project details (API)
    //========================================
    function viewproject()
    {
    	global $phpgw_info, $phpgw;
    }
    
    //========================================
    // delete project (API)
    //========================================
    function delproject()
    {
    	global $phpgw_info, $phpgw;
    }

    //========================================
    // add participant (API)
    //========================================
    function addparticipant()
    {
    	global $phpgw_info, $phpgw;
    }

    // ***************************************************
    // here we got helper methods for APIs
    // ***************************************************
    
    // ---------------------
    // helper 1
    //----------------------
    function helper1()
    {
    	global $phpgw_info, $phpgw;
    }
}
