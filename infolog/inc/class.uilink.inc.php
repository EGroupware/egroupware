<?php
	/**************************************************************************\
	* phpGroupWare - InfoLog Links                                             *
	* http://www.phpgroupware.org                                              *
	* Written by Ralf Becker <RalfBecker@outdoor-training.de>                  *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	if(!isset($GLOBALS['phpgw_info']['flags']['included_classes']['bolink']))
	{
		include(PHPGW_API_INC . '/../../infolog/inc/class.bolink.inc.php');
		$GLOBALS['phpgw_info']['flags']['included_classes']['bolink'] = True;
	}

	/*!
	@class uilink
	@author ralfbecker
	@abstract generalized linking between entries of phpGroupware apps - HTML UI layer
	@discussion This class is the UI to show/modify the links
	@discussion Links have to ends each pointing to an entry, an entry is a double:
	@discussion app   app-name or directory-name of an phpgw application, eg. 'infolog'
	@discussion id    this is the id, eg. an integer or a tupple like '0:INBOX:1234'
	*/
	class uilink extends bolink
	{
		function uilink( )
		{
			bolink( );									// call constructor of derived class
			$this->public_functions += array(	// extend public_functions
				'getEntry' => True,
				'show'     => True
			);
		}

		/*!
		@function getEntry
		@syntax getEntry( $name )
		@author ralfbecker
		@abstract HTML UI to query user for one side of a link: an entry of a supported app
		*/
		function getEntry()
		{
      }

		/*!
		@function show
		@syntax show( $app,$id )
		@author ralfbecker
		@abstract HTML UI to show & delete existing links to $app,$id and to make new links
		@discussion this should be called by each link-supporting app at the bottom of its view-entry-page
		*/
		function show($app,$id)
		{
		}
	}




