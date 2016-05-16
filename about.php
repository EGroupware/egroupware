<?php
/**
 * EGroupware: About informations
 *
 * rewrite of the old PHPLib based about page
 * it now uses eTemplate2
 * new class about ist stored at api/src/Framework/About.php
 *
 * LICENSE:  GPL
 *
 * @package     api
 * @subpackage  about
 * @author      Sebastian Ebling <hudeldudel@php.net>
 * @author		Ralf Becker <RalfBecker@outdoor-training.de>
 * @license     http://www.gnu.org/copyleft/gpl.html
 * @link        http://www.egroupware.org
 * @version     SVN: $Id$
 */

header('Location: index.php?menuaction=api.EGroupware\\Api\\Framework\\About.index'.
	(isset($_GET['sessionid']) ? '&sessionid='.$_GET['sessionid'].'&kp3='.$_GET['kp3'] : ''));
