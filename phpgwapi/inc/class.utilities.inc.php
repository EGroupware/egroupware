<?php
  /**************************************************************************\
  * phpGroupWare                                                             *
  * http://www.phpgroupware.org                                              *
  * This file written by Dan Kuykendall <seek3r@phpgroupware.org>            *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

  $d1 = strtolower(substr($phpgw_info["server"]["api_inc"],0,3));
  if($d1 == "htt" || $d1 == "ftp") {
    echo "Failed attempt to break in via an old Security Hole!<br>\n";
    exit;
  } unset($d1);

  // Note: We should add a way to force the developer to say which ones to use. (jengo)
  class utilities
  {
    var $rssparser;
    var $clientsniffer;
    var $http;
    var $matrixview;
    var $menutree;
    var $sbox;

    function utilities_()
    {
      $phpgw->rssparser = CreateObject("phpgwapi.rssparser");
      $phpgw->clientsniffer = CreateObject("phpgwapi.clientsniffer");
      $phpgw->http = CreateObject("phpgwapi.http");
      $phpgw->matrixview = CreateObject("phpgwapi.matrixview");
      $phpgw->menutree = CreateObject("phpgwapi.menutree");
      $phpgw->sbox = CreateObject("phpgwapi.sbox");
    }
  }
?>
