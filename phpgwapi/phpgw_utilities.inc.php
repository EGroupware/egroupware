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

  $d1 = strtolower(substr($phpgw_info["server"]["api_dir"],0,3));
  if($d1 == "htt" || $d1 == "ftp") {
    echo "Failed attempt to break in via an old Security Hole!<br>\n";
    exit;
  } unset($d1);

  include($phpgw_info["server"]["api_dir"] . "/phpgw_utilities_rssparse.inc.php");
  include($phpgw_info["server"]["api_dir"] . "/phpgw_utilities_clientsniffer.inc.php");
  include($phpgw_info["server"]["api_dir"] . "/phpgw_utilities_http.inc.php");
  include($phpgw_info["server"]["api_dir"] . "/phpgw_utilities_matrixview.inc.php");
  include($phpgw_info["server"]["api_dir"] . "/phpgw_utilities_menutree.inc.php");

  class utilities
  {
    var $rssparser;
    var $clientsniffer;
    var $http;
    var $matrixview;
    var $menutree;

    function utilities_()
    {
      $this->rssparser        = new rssparser;
      $this->clientsniffer    = new clientsniffer;
      $this->http             = new http;
      $this->matrixview       = new matrixview;
      $this->menutree         = new menutree;
    }
  }
?>
