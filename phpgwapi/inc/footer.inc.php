<?php
  /**************************************************************************\
  * phpGroupWare API - phpgwapi footer                                       *
  * This file written by Dan Kuykendall <seek3r@phpgroupware.org>            *
  * and Joseph Engo <jengo@phpgroupware.org>                                 *
  * Closes out interface and db connections                                  *
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

  /* $Id$ */

	$d1 = strtolower(substr(PHPGW_APP_INC,0,3));
	if($d1 == 'htt' || $d1 == 'ftp')
	{
		echo "Failed attempt to break in via an old Security Hole!<br>\n";
		exit;
	} unset($d1);

	/**************************************************************************\
	* Include the apps footer files if it exists                               *
	\**************************************************************************/
	if (file_exists (PHPGW_APP_INC . '/footer.inc.php') &&
		$GLOBALS['phpgw_info']['flags']['currentapp'] != 'home' &&
		$GLOBALS['phpgw_info']['flags']['currentapp'] != 'login' &&
		$GLOBALS['phpgw_info']['flags']['currentapp'] != 'logout' &&
		!@$GLOBALS['phpgw_info']['flags']['noappfooter'])
	{
		if ($GLOBALS['HTTP_GET_VARS']['menuaction'])
		{
//			list($app,$class,$method) = explode('.',$menuaction);
//			if ($app && $class && $method)
//			{
//				$GLOBALS['obj'] = CreateObject(sprintf('%s.%s',$GLOBALS['app'],$GLOBALS['class']));
				if (is_array($GLOBALS['obj']->public_functions) && $GLOBALS['obj']->public_functions['footer'])
				{
					eval("\$GLOBALS['obj']->footer();");
				}
				elseif(file_exists(PHPGW_APP_INC.'/footer.inc.php'))
				{
					include(PHPGW_APP_INC . '/footer.inc.php');
				}
//			}
//			elseif(file_exists(PHPGW_APP_INC.'/footer.inc.php'))
//			{
//				include(PHPGW_APP_INC . '/footer.inc.php');
//			}
		}
		elseif(file_exists(PHPGW_APP_INC.'/footer.inc.php'))
		{
			include(PHPGW_APP_INC . '/footer.inc.php');
		}
	}

	parse_navbar_end();
	$GLOBALS['phpgw']->db->disconnect();
  
?>
</BODY>
</HTML>
