<?php
/**************************************************************************\
* eGroupWare SiteMgr - Baseclass for SiteMgr Modules written with eTemplate*
* http://www.egroupware.org                                                *
* --------------------------------------------                             *
*  This program is free software; you can redistribute it and/or modify it *
*  under the terms of the GNU General Public License as published by the   *
*  Free Software Foundation; either version 2 of the License, or (at your  *
*  option) any later version.                                              *
\**************************************************************************/

/* $Id$ */

/**
 * Baseclass for SiteMgr Modules written with eTemplate
 *
 * To create a SiteMgr module from an eTemplate app, you need to:
 *	- extend this class and set the $etemplate_method class-var to a method/menuaction of the app
 *	- the app need to return etemplate::exec (otherwise the content is empty)!!!
 *	- the app need to avoid redirects or links, as this would leave sitemgr!!!
 *
 * @package etemplate
 * @subpackage api
 * @author RalfBecker-AT-outdoor-training.de
 * @license GPL
 */
class sitemgr_module extends Module // the Module class get automatic included by SiteMgr
{
	/**
	 * @var string $etemplate_method Method/menuaction of an eTemplate app to be used as module
	 */
	var $etemplate_method;

	/**
	 * generate the module content AND process submitted forms
	 *
	 * @param array &$arguments
	 * @param array $properties
	 * @return string the html content
	 */
	function get_content(&$arguments,$properties) 
	{
		list($app) = explode('.',$this->etemplate_method);
		$GLOBALS['egw']->translation->add_app($app);
		
		$css = '';
		if (file_exists(EGW_SERVER_ROOT.'/'.$app.'/templates/default/app.css'))
		{
			$css = "<style type=\"text/css\">\n<!--\n@import url(".
				$GLOBALS['egw_info']['server']['webserver_url'].'/'.$app."/templates/default/app.css);\n-->\n</style>";
		}
		$ret = false;
		if($_POST['etemplate_exec_id'])
		{
			$ret = ExecMethod('etemplate.etemplate.process_exec');
		}
		return $css.($ret ? $ret : ExecMethod($this->etemplate_method));
	}
}
