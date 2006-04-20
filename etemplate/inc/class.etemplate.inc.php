<?php
	/**
	 * eGroupWare - EditableTemplates
	 *
	 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
	 * @package etemplate
	 * @link http://www.egroupware.org
	 * @author Ralf Becker <RalfBecker@outdoor-training.de>
	 * @version $Id$
	 */

	$ui = ''; // html UI, which UI to use, should come from api and be in $GLOBALS['egw']???
	if ($_ENV['DISPLAY'] && isset($_SERVER['_']))
	{
		$ui = '_gtk';
	}
	include_once(EGW_INCLUDE_ROOT . "/etemplate/inc/class.uietemplate$ui.inc.php");
