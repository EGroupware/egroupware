<?php
	/*******************************************************************\
	* phpGroupWare API - Arrayfunctions                                 *
	* Written by Lars Kneschke <lkneschke@phpgw.de>                     *
	* Copyright (C) 2002, 2003 Lars Kneschke                            *
	* ----------------------------------------------------------------- *
	* This library is part of the phpGroupWare API                      *
	* ----------------------------------------------------------------- *
	* This program is free software; you can redistribute it and/or     *
	* modify it under the terms of the GNU General Public License as    *
	* published by the Free Software Foundation; either version 2 of    *
	* the License, or (at your option) any later version.               *
	*                                                                   *
	* This program is distributed in the hope that it will be useful,   *
	* but WITHOUT ANY WARRANTY; without even the implied warranty of    *
	* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU  *
	* General Public License for more details.                          *
	*                                                                   *
	* You should have received a copy of the GNU General Public License *
	* along with this program; if not, write to the Free Software       *
	* Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.         *
	\*******************************************************************/
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
