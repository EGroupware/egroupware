<?php
  /**************************************************************************\
  * phpGroupWare API - Utilies loader                                        *
  * This file written by Miles Lott <milosch@phpgroupware.org>               *
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

	/* Test for various php extensions and optionally load them */
	class php_ext
	{
		var $os_ext = '';

		var $test_function = array(
			'xml' => array(
				'function' => 'xml_parser_create',
				'extfile'  => array('xml','php3_xml')
			),
			'gd' => array(
				'function' => 'imagecreate'
			)
		);
		/*
		 * $ext  php extension to test
		 * $load try to load the extension via dl()
		 */
		function php_ext($ext='',$load=True)
		{
			//phpinfo();exit;
			if (PHP_OS == 'Windows' || PHP_OS == 'OS/2')
			{
				$this->os_ext = '.dll';
			}
			else
			{
				$this->os_ext = '.so';
			}

			if(!$ext)
			{
				return False;
			}
			return $this->check($ext,$load);
		}

		function check($ext='',$load=True)
		{
			if(extension_loaded($ext))
			{
				return True;
			}
			if(function_exists($this->test_function[$ext]['function']))
			{
				return True;
			}

			if($load)
			{
				return $this->load($ext);
			}
			return False;
		}

		function load($ext='')
		{
			if($ext)
			{
				if(is_array($this->test_function[$ext]['extfile']))
				{
					while(list(,$tf) = each($this->test_function[$ext]['extfile']))
					{
						eval('dl(' . $tf . '.' . $this->os_ext . ');');
						$this->check($ext,True);
					}
				}
				else
				{
					//dl($ext. $this->os_ext);
					eval('dl(' . $ext . '.' . $this->os_ext . ');');
					return $this->check($ext,False);
				}
			}
			return False;
		}
	}
?>
