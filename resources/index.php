<?php
	/**
	 * eGroupWare - resources
	 * http://www.egroupware.org 
	 *
	 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
	 * @package resources
	 * @author Cornelius Weiss <egw@von-und-zu-weiss.de>
	 * @author Lukas Weiss <wnz_gh05t@users.sourceforge.net>
	 * @version $Id$
	 */
	
	$GLOBALS['egw_info']['flags'] = array(
		'currentapp'	=> 'resources',
		'noheader'	=> True,
		'nonavbar'	=> True
	);
	include('../header.inc.php');

	$GLOBALS['egw']->redirect_link('/index.php','menuaction=resources.ui_resources.index');
	