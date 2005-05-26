<?php

/**
* The base Savant2_Error class.
*/
require_once 'Savant2/Error.php';

/**
* The PEAR_Error class.
*/
require_once 'PEAR.php';

/**
* 
* Provides an interface to PEAR_ErrorStack class for Savant.
*
* $Id$
* 
* @author Paul M. Jones <pmjones@ciaweb.net>
* 
* @package Savant2
* 
* @license http://www.gnu.org/copyleft/lesser.html LGPL
* 
* This program is free software; you can redistribute it and/or modify
* it under the terms of the GNU Lesser General Public License as
* published by the Free Software Foundation; either version 2.1 of the
* License, or (at your option) any later version.
*
* This program is distributed in the hope that it will be useful, but
* WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
* Lesser General Public License for more details.
* 
*/

class Savant2_Error_pear extends Savant2_Error {
	
	
	/**
	* 
	* Extended behavior for PEAR_Error.
	*
	* @access public
	*
	* @return void
	*
	*/
	
	function error()
	{
		// throw a PEAR_Error
		PEAR::throwError($this->text, $this->code, $this->info);
	}
}
?>