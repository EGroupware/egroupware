<?php
	/**************************************************************************\
	* phpGroupWare API - Arrayfunctions                                        *
	* This file written by Lars Kneschke <lkneschke@phpgw.de>                  *
	* Copyright (C) 2002 Lars Kneschke                                         *
	* ------------------------------------------------------------------------ *
	* This library is part of the phpGroupWare API                             *
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
	/* $Id$ */

	class arrayfunctions
	{
		function arrayfunctions($vars='')
		{
		}

		/*
		* arfsort() - (AR)ray (F)ield Sort.
		* Sort a multi-dimensional array according
		* to a list of fields.
		* @param $a The array to sort
		* @param $fl Field list (in order of importance)
		*/
		
		function arfsort( $a, $fl )
		{
			$GLOBALS['__ARFSORT_LIST__'] = $fl;

			if (is_array($a))
			{
				usort( $a, array($this,'arfsort_func') );
				return $a;
			}
			return False;
		}

		/*
		* Internal sorting function for arfsort()
		*/
		function arfsort_func( $a, $b )
		{
			foreach( $GLOBALS['__ARFSORT_LIST__'] as $f )
			{
				$strc = strcmp( $a[$f], $b[$f] );
				if ( $strc != 0 )
				{
					return $strc;
				}
			}
			return 0;
		}

	} // end class arrayfunctions
?>
