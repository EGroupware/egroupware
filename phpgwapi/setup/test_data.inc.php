<?php
  /**************************************************************************\
  * eGroupWare - Setup                                                       *
  * http://www.egroupware.org                                                *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

	$oProc->query("INSERT INTO addressbook (
	ab_owner,ab_access,ab_firstname,ab_lastname,
	ab_email,ab_hphone,ab_wphone,ab_fax,ab_pager,
	ab_mphone,ab_ophone,ab_street,ab_city,ab_state,ab_zip,
	ab_bday,ab_notes,ab_company)
	VALUES(500,'private','John','Doe',
	'jdoe@yahoo.com','212-555-5555','212-555-4444','212-555-4445','212-555-5556',
	'212-555-5557','212-555-5558','1234 Elm','Anytown','New York','01010',
	'12/25/1970','This is a test note\nfor verification','ClearRiver Tech.')");

	$oProc->query("INSERT INTO addressbook (
	ab_owner,ab_access,ab_firstname,ab_lastname,
	ab_email,ab_hphone,ab_wphone,ab_fax,ab_pager,
	ab_mphone,ab_ophone,ab_street,ab_city,ab_state,ab_zip,
	ab_bday,ab_notes,ab_company)
	VALUES(502,'public','Jane','Smith',
	'jane.smith@motherearth.org','212-555-5555','212-555-4444','212-555-4445','212-555-5556',
	'212-555-5557','212-555-5558','1313 Mockingbird Ln.','Hooterville','Kentucky','54874',
	'01/01/1983','This is a test note\nfor verification','Jasper Automotive')");

	$oProc->query("INSERT INTO addressbook (
	ab_owner,ab_access,ab_firstname,ab_lastname,
	ab_email,ab_hphone,ab_wphone,ab_fax,ab_pager,
	ab_mphone,ab_ophone,ab_street,ab_city,ab_state,ab_zip,
	ab_bday,ab_notes,ab_company)
	VALUES(1,'private','Steven','Wright',
	'jdoe@yahoo.com','212-555-5555','212-555-4444','212-555-4445','212-555-5556',
	'212-555-5557','212-555-5558','321 Contact','Oiforgot','North Dakota','15421',
	'10/05/1955','This is a test note\nfor verification','Baubles and Beads')");

?>
