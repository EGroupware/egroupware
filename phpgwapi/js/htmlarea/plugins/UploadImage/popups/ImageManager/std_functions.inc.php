<?php
   /**************************************************************************\
   * eGroupWare - UploadImage-plugin for htmlArea                             *
   * http://www.eGroupWare.org                                                *
   * Written and (c) by Xiang Wei ZHUO <wei@zhuo.org>                         *
   * Modified for eGW by and (c) by Pim Snel <pim@lingewoud.nl>               *
   * --------------------------------------------                             *
   * This program is free software; you can redistribute it and/or modify it  *
   * under the terms of the GNU General Public License as published by the    *
   * Free Software Foundation; version 2 of the License.                      *
   \**************************************************************************/

   function percent($p, $w) 
   { 
	  return (real)(100 * ($p / $w)); 
   } 

   function unpercent($percent, $whole) 
   { 
	  return (real)(($percent * $whole) / 100); 
   } 



?>
