<?php
/**************************************************************************\
* eGroupWare - Adressbook - default records                                *
* http://www.egroupware.org                                                *
* Written and (c) 2006 by  Ralf Becker <RalfBecker-AT-outdoor-training.de> *
* ------------------------------------------------------------------------ *
*  This program is free software; you can redistribute it and/or modify it *
*  under the terms of the GNU General Public License as published by the   *
*  Free Software Foundation; either version 2 of the License, or (at your  *
*  option) any later version.                                              *
\**************************************************************************/

/* $Id$ */

// Create Addressbook for Default group, by setting a group ACL from the group to itself for all rights: add, read, edit and delete	
$defaultgroup = $GLOBALS['egw_setup']->add_account('Default','Default','Group',False,False);
$GLOBALS['egw_setup']->add_acl('addressbook',$defaultgroup,$defaultgroup,1|2|4|8);
