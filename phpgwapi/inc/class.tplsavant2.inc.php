<?php
   /**************************************************************************\
   * eGroupWare API - Wrapper for the savant2 template engine                 *
   * Written by Pim Snel <pim@lingewoud.nl>                                   *
   *                                                                          *
   * Wrapper for the savant2 template engine www.phpsavant.com                *
   * Copyright (C) 2005 Lingewoud BV and Pim Snel                             *
   * -------------------------------------------------------------------------*
   * This library is part of the eGroupWare API                               *
   * http://www.egroupware.org                                                *
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

   if(is_file(EGW_INCLUDE_ROOT.'/phpgwapi/inc/savant2/Savant2.php'))
   {
	  include_once(EGW_INCLUDE_ROOT.'/phpgwapi/inc/savant2/Savant2.php');
   }

   /*!
   @class tplsavant2
   @abstract wrapper class for the Savant2 template engine
   */
   class tplsavant2 extends Savant2
   {
	  /*!
	  @var $version
	  @abstract the version this wrapper is testet against
	  */
	  var $version = '2.3.3';

	  /*!
	  @var $do_version_check
	  @abstract set this to true to halt when versions of this wrapper and savant2 itself differ
	  */
	  var $do_version_check = false;

	  /*!
	  @function tplsavant2
	  @abstract constructor function which calls the constructor of Savant2 and sets necesary things for eGroupware
	  */
	  function tplsavant2()
	  {
		 // run constructor of the Savant2 class
		 $this->Savant2();

		 if($this->do_version_check)
		 {
			$this->version_check();
		 }

		 $this->set_tpl_path();
	  }

	  /**
	  @function version_check
	  @abstract check version of this wrapper with installed savant2 version and halts when version differs
	  @return void
	  */
	  function version_check()
	  {
		 $Sav2Version = @file_get_contents(EGW_INCLUDE_ROOT.'/phpgwapi/inc/savant2/VERSION',"rb");

		 if(trim($Sav2Version) != trim($this->version))
		 {
			$this->halt(lang('Savant2 version differs from Savant2 wrapper. <br/>This version: %1 <br/>Savants version: %2',$this->version, $Sav2Version));
		 }
	  }

	  /**
	   * set_tpl_path sets the preferred and fallback template search paths
	   *
	   * @param string $man_dir custom manual given template path in filesystem
	   * @access public
	   * @return void
	   */
	  function set_tpl_path($man_dir=null)
	  {
		 $preferred_dir=$this->get_tpl_dir();
		 $fallback_dir=$this->get_tpl_dir(true);

		 if(!$preferred_dir && $man_dir && $fallback_dir)
		 {
			$this->halt(lang('No Savant2 template directories were found in:'.EGW_APP_ROOT));
		 }
		 else
		 {
			if($fallback_dir)
			{
			   $this->addPath('template',$fallback_dir);
			}
			// add preferred tpl dir last because savant set the last added first in the search array
			if($preferred_dir)
			{
			   $this->addPath('template',$preferred_dir);
			}

			if($man_dir)
			{
			   $this->addPath('template',$man_dir);
			}
		 }
	  }

	  /**
	   * get_tpl_dir get template dir of an application
	   *
	   * @param bool $fallback if true the default fallback template dir is returned
	   * @param string $appname appication name optional can be derived from $GLOBALS['egw_info']['flags']['currentapp'];
	   * @access public
	   * @return void
	   */
	  function get_tpl_dir($fallback=false,$appname = '')
	  {
		 if (! $appname)
		 {
			$appname = $GLOBALS['egw_info']['flags']['currentapp'];
		 }
		 if ($appname == 'api' || $appname == 'logout' || $appname == 'login')
		 {
			$appname = 'phpgwapi';
		 }

		 if (!isset($GLOBALS['egw_info']['server']['template_set']) && isset($GLOBALS['egw_info']['user']['preferences']['common']['template_set']))
		 {
			$GLOBALS['egw_info']['server']['template_set'] = $GLOBALS['egw_info']['user']['preferences']['common']['template_set'];
		 }

		 // Setting this for display of template choices in user preferences
		 if ($GLOBALS['egw_info']['server']['template_set'] == 'user_choice')
		 {
			$GLOBALS['egw_info']['server']['usrtplchoice'] = 'user_choice';
		 }

		 if (($GLOBALS['egw_info']['server']['template_set'] == 'user_choice' ||
		 !isset($GLOBALS['egw_info']['server']['template_set'])) &&
		 isset($GLOBALS['egw_info']['user']['preferences']['common']['template_set']))
		 {
			$GLOBALS['egw_info']['server']['template_set'] = $GLOBALS['egw_info']['user']['preferences']['common']['template_set'];
		 }
		 elseif ($GLOBALS['egw_info']['server']['template_set'] == 'user_choice' ||
		 !isset($GLOBALS['egw_info']['server']['template_set']))
		 {
			$GLOBALS['egw_info']['server']['template_set'] = 'default';
		 }

		 $tpldir         = EGW_SERVER_ROOT . '/' . $appname . '/templatesSavant2/' . $GLOBALS['egw_info']['server']['template_set'];
		 $tpldir_default = EGW_SERVER_ROOT . '/' . $appname . '/templatesSavant2/default';

		 if (!$fallback && @is_dir($tpldir))
		 {
			return $tpldir;
		 }
		 elseif (@is_dir($tpldir_default))
		 {
			return $tpldir_default;
		 }
		 else
		 {
			return False;
		 }
	  }

	  /***************************************************************************/
	  /* public: halt(string $msg)
	  * msg:    error message to show.
	  */
	  function halt($msg)
	  {
		 $this->last_error = $msg;

		 if ($this->halt_on_error != 'no')
		 {
			$this->haltmsg($msg);
		 }

		 if ($this->halt_on_error == 'yes')
		 {
			echo('<strong>Halted.</strong>');
		 }

		 $GLOBALS['phpgw']->common->phpgw_exit(True);
	  }

	  /* public, override: haltmsg($msg)
	  * msg: error message to show.
	  */
	  function haltmsg($msg)
	  {
		 printf("<strong>Savant Template Error:</strong> %s<br/>\n", $msg);
		 echo "<strong>Backtrace</strong>: ".function_backtrace(2)."<br/>\n";
	  }

	  function fetch_string($string)
	  {
		 $tmpfname = tempnam ("/tmp", "sav");
		 $fp = fopen($tmpfname, "w");
		 fwrite($fp, $string);
		 fclose($fp);
		 $this->addPath('template','/tmp');
		 $file_arr= explode('/',$tmpfname);
		 return $this->fetch($file_arr[2]);
		 unlink($tmpfname);
	  }

	  /**
	   * set_var the same as assign()
	   *
	   * @param mixed $tplvar
	   * @param string $val
	   * @access public
	   * @return void
	   */
	  function set_var($tplvar,$val='')
	  {
		 $this->assign($tplvar,$val);
	  }

   }
