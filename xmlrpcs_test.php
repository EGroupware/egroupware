<?php
  /**************************************************************************\
  * phpGroupWare module (File Manager)                                       *
  * http://www.phpgroupware.org                                              *
  * Written by Dan Kuykendall <dan@kuykendall.org>                           *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  $phpgw_info["flags"] = array("currentapp" => "login", "noheader" => True);
  include("header.inc.php");
  CreateObject("phpgwapi.xmlrpc");
  CreateObject("phpgwapi.xmlrpcs");
   
  function hello($params) {
    return new xmlrpcresp(new xmlrpcval("Hello World", "string"));
  }
  $s=new xmlrpc_server(array("examples.hello" => array("function" => "hello")));
?>