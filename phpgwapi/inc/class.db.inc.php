<?php
	/**************************************************************************\
	* eGroupWare API - database support via ADOdb                              *
	* ------------------------------------------------------------------------ *
	* This program is free software; you can redistribute it and/or modify it  *
	* under the terms of the GNU Lesser General Public License as published    *
	* by the Free Software Foundation; either version 2.1 of the License, or   *
	* any later version.                                                       *
	\**************************************************************************/

	/* $Id$ */

	require_once(EGW_API_INC.'/class.egw_db.inc.php');

	/*
	 * Database abstraction library
	 *
	 * This is only for compatibility with old code, the DB class is now called egw_db.
	 *
	 * @package phpgwapi
	 * @subpackage db
	 * @author RalfBecker@outdoor-training.de
	 * @license LGPL
	 */

	class db extends egw_db
	{
	}
