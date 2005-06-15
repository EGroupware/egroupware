<?php
   /**************************************************************************\
   * eGroupWare                                                               *
   * http://www.egroupware.org                                                *
   * The file written by Edo van Bruggen and Rob van Kraanen                  *
   * <info@idots2.org>*                                                       *
   * --------------------------------------------                             *
   *  This program is free software; you can redistribute it and/or modify it *
   *  under the terms of the GNU General Public License as published by the   *
   *  Free Software Foundation; either version 2 of the License.              *
   \**************************************************************************/

   /* $Id$ */

   $phpgw_info = array();
   $GLOBALS['phpgw_info']['flags'] = array(
	  'noheader' => True,
	  'nonavbar' => True,
	  'disable_Template_class' => True,
	  'currentapp' => 'notifywindow'
   );
   include('header.inc.php');
   header("Content-type: text/xml");

   header( "Expires: Mon, 26 Jul 1997 05:00:00 GMT" ); 
   header( "Last-Modified: " . gmdate( "D, d M Y H:i:s" ) . "GMT" ); 
   header( "Cache-Control: no-cache, must-revalidate" ); 
   header( "Pragma: no-cache" );

   echo '<?xml version="1.0" encoding="UTF-8"
   standalone="yes"?>';
   $apps = $GLOBALS['phpgw']->hooks->process('notify');
   echo "\r\n<response>\r\n";
   foreach($apps as $app => $message) {

	  if($message != "") 
	  {	
		 $title = $GLOBALS['phpgw_info']['apps'][$app]['title'];

		 echo "  <title>".$title."</title>
		 <url>".$GLOBALS['phpgw']->link("/".$app."/index.php")."</url>
		 <message>".$message." </message>\r\n";

	  }
   }
   echo "</response>"; 

?>

