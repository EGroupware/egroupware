<?php
  /**************************************************************************\
  * phpGroupWare API - legacy file                                           *
  * http://www.phpgroupware.org/api                                          *
  * This file written by Dan Kuykendall <seek3r@phpgroupware.org>            *
  * and Joseph Engo <jengo@phpgroupware.org>                                 *
  * This file will be deleted soon                                           *
  * Copyright (C) 2000, 2001 Dan Kuykendall                                  *
  * -------------------------------------------------------------------------*
  * This library is part of phpGroupWare (http://www.phpgroupware.org)       * 
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
  \**************************************************************************/

  /* $Id$ */
  
  $d1 = strtolower(substr($phpgw_info["server"]["api_inc"],0,3));
  $d2 = strtolower(substr($phpgw_info["server"]["server_root"],0,3));
  $d3 = strtolower(substr($phpgw_info["server"]["app_inc"],0,3));
  if($d1 == "htt" || $d1 == "ftp" || $d2 == "htt" || $d2 == "ftp" || $d3 == "htt" || $d3 == "ftp") {
    echo "Failed attempt to break in via an old Security Hole!<br>\n";
    exit;
  } unset($d1);unset($d2);unset($d3);
  /****************************************************************************\
  * This file is retired, but I left ut here for backward compatibility. Seek3r*
  \****************************************************************************/
  include($phpgw_info["server"]["api_inc"] . "/functions.inc.php");
?>