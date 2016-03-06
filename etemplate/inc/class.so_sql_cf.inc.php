<?php
/**
 * EGroupware generalized SQL Storage Object with build in custom field support
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 * @copyright 2009-16 by RalfBecker@outdoor-training.de
 * @version $Id$
 */

use EGroupware\Api;

/**
 * Generalized SQL Storage Object with build in custom field support
 *
 * This class allows to display, search, order and filter by custom fields simply by replacing so_sql
 * by it and adding custom field widgets to the eTemplates of an applications.
 * It's inspired by the code from Klaus Leithoff, which does the same thing limited to addressbook.
 *
 * The schema of the custom fields table should be like (the lenght of the cf name is nowhere enfored and
 * varies throughout eGW from 40-255, the value column from varchar(255) to longtext!):
 *
 * 'egw_app_extra' => array(
 * 	'fd' => array(
 * 		'prefix_id' => array('type' => 'int','precision' => '4','nullable' => False),
 * 		'prefix_name' => array('type' => 'string','precision' => '64','nullable' => False),
 * 		'prefix_value' => array('type' => 'text'),
 * 	),
 *  'pk' => array('prefix_id','prefix_name'),
 *	'fk' => array(),
 *	'ix' => array(),
 *	'uc' => array()
 * )
 *
 * @deprecated use Api\Storage
 */
class so_sql_cf extends Api\Storage {}
