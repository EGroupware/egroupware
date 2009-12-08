<?php
	/**************************************************************************\
	* eGroupWare - FeLaMiMail                                                  *
	* http://www.egroupware.org                                                *
	* --------------------------------------------                             *
	* This program is free software; you can redistribute it and/or modify it  *
	* under the terms of the GNU General Public License as published by the    *
	* Free Software Foundation; version 2 of the License                       *
	\**************************************************************************/

	/* $Id$ */
	ExecMethod('phpgwapi.hooks.register_all_hooks');
	error_log('hook_notifywindow.inc.php called from:'.function_backtrace());
