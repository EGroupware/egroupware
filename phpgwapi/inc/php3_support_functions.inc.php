<?php
	//$debugme = "on";
	 /**************************************************************************\
	 * phpGroupWare API - PHP3 Compatibility layer                              *
	 * This file written by Dan Kuykendall <seek3r@phpgroupware.org>            *
	 * Has repliations of PHP4 only functions to allow for transparent PHP3     *
	 * compatibility                                                            *
	 * Copyright (C) 2000, 2001 Dan Kuykendall                                  *
	 * -------------------------------------------------------------------------*
	 * This library is part of the phpGroupWare API                             *
	 * http://www.phpgroupware.org/api                                          * 
	 * ------------------------------------------------------------------------ *
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

	function is_bool($var)
	{
		$retval = gettype($var) ;
		if ( strcmp( $retval, "unknown type") == 0 )
		{
			/* Chances are that we have a boolean */
			if ($var == True || $var == False)
			{
				return True;
			}
			else
			{
				return False;
			}
		}
		else
		{
			return False ;
		}
	}
?>