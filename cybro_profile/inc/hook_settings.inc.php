<?php
   /**************************************************************************\
   * eGroupWare - Jinn Preferences                                            *
   * http://egroupware.org                                                    *
   * Written by Pim Snel <pim@egroupware.org>                                 *
   * --------------------------------------------                             *
   *  This program is free software; you can redistribute it and/or modify it *
   *  under the terms of the GNU General Public License as published by the   *
   *  Free Software Foundation; version 2 of the License.                     *
   \**************************************************************************/

   // In the future these settings go to the plugin file 

   /* $Id: hook_settings.inc.php 21614 2006-05-22 18:49:52Z mipmip $ */

   if(function_exists('create_section')) 
   {
	  create_section('Homepage linker');
   }

   $yes_no = Array(
	  'False' => lang('No'),
	  'True' => lang('Yes')
   );

   if(function_exists('create_section'))
   {
	  create_select_box('Link OnePage to the homepage?','homepage_display',$yes_no,"Show the status of your credits on the homepage");
   }



