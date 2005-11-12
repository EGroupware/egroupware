<?php
	/**************************************************************************\
	* eGroupWare - InfoLog                                                     *
	* http://www.egroupware.org                                                *
	* Written by Ralf Becker <RalfBecker@outdoor-training.de>                  *
	* originaly based on todo written by Joseph Engo <jengo@phpgroupware.org>  *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */
	
	include_once(EGW_INCLUDE_ROOT.'/infolog/inc/class.boinfolog.inc.php');

	/**
	 * This class returns the link-registry for infolog
	 *
	 * To prevent an invinit recursion, it has to be outside the boinfolog class, 
	 * which itself instanciats the link class by default.
	 *
	 * @package infolog
	 * @author Ralf Becker <RalfBecker@outdoor-training.de>
	 * @copyright (c) by RalfBecker-At-outdoor-training.de
	 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
	 */
	class infolog_link_registry
	{
		function search_link($location)
		{
			$bo =& new boinfolog(0,false);	// false = dont instanciate the link class
			
			return $bo->search_link($location);
		}
	}