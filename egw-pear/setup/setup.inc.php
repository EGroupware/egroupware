<?php
	/**************************************************************************\
	* eGroupWare - egw-pear                                                    *
	* http://www.egroupware.org                                                *
	* Author: lkneschke@egroupware.org                                         *
	* --------------------------------------------                             *
	* This library is free software; you can redistribute it and/or modify it  *
	* under the terms of the GNU Lesser General Public License as published by *
	* the Free Software Foundation; either version 2.1 of the License, or (at  *
	* your option) any later version.                                          *
	*                                                                          *
	* This library is distributed in the hope that it will be useful, but      *
	* WITHOUT ANY WARRANTY; without even the implied warranty of               *
	* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU Lesser  *
	* General Public License for more details.                                 *
	*                                                                          *
	* You should have received a copy of the GNU Lesser General Public License *
	* along with this library; if not, write to the Free Software Foundation,  *
	* Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA              *
	\**************************************************************************/
	/* $Id: setup.inc.php 21904 2006-06-20 23:07:03Z ralfbecker $ */

	$setup_info['egw-pear']['name']		= 'egw-pear';
	$setup_info['egw-pear']['title']	= 'egw-pear';
	$setup_info['egw-pear']['version']	= '1.4.000';
	$setup_info['egw-pear']['app_order']	= 99;
	$setup_info['egw-pear']['enable']	= 2;

	$setup_info['egw-pear']['author']	= 'Lars Kneschke';
	$setup_info['egw-pear']['license']	= 'LGPL';
	$setup_info['egw-pear']['description']	=
		'A place for PEAR modules modified for eGroupWare.';

	$setup_info['egw-pear']['note'] 	=
		'This application is a place for PEAR modules used by eGroupWare, which are NOT YET available from pear, 
		because we patched them somehow and the PEAR modules are not released upstream.
		This application is under the LGPL license because the GPL is not compatible with the PHP license.
		If the modules are available from PEAR they do NOT belong here anymore.';
	
	$setup_info['egw-pear']['maintainer']	= array(
		'name'  => 'Lars Kneschke',
		'email' => 'l.kneschke@metaways.de'
	);
	
	// installation checks for egw-pear
	$setup_info['egw-pear']['check_install'] = array(
		// we need pear itself to be installed
		'' => array(
			'func' => 'pear_check',
			'from' => 'FeLaMiMail',
		),
		// Net_Socket is required from Net_IMAP & Net_Sieve
		'Net_Socket' => array(
			'func' => 'pear_check',
			'from' => 'FeLaMiMail',
		),
	);
?>