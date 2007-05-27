<?php
/**
 * eGroupWare: About informations
 * 
 * rewrite of the old PHPLib based about page
 * it now uses eTemplate
 * new class about ist stored at phpgwapi/inc/class.about.inc.php
 *
 * This is NO typical eTemplate application as it is not stored in the
 * correct namespace
 *
 * LICENSE:  GPL.
 *
 * @package     api
 * @subpackage  about
 * @author      Sebastian Ebling <hudeldudel@php.net>
 * @author		Ralf Becker <RalfBecker@outdoor-training.de>
 * @license     http://www.gnu.org/copyleft/gpl.html
 * @link        http://www.egroupware.org
 * @version     SVN: $Id$ 
 */
 
$GLOBALS['egw_info'] = array(
	'flags' => array(
		'currentapp' => 'home', //'about',
		'disable_Template_class' => true,
		'noheader' => true,
		'nonavbar' => true
	)
);

include('header.inc.php');
 
// create the about page
require_once(EGW_API_INC.'/class.about.inc.php');

$aboutPage = new about();
