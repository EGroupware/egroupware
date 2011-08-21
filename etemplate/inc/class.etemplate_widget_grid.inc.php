<?php
/**
 * EGroupware - eTemplate serverside grid widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 * @copyright 2002-11 by RalfBecker@outdoor-training.de
 * @version $Id$
 */

/**
 * eTemplate grid widget
 */
class etemplate_widget_grid extends etemplate_widget_box
{
	/**
	 * (Array of) comma-separated list of legacy options to automatically replace when parsing with set_attrs
	 *
	 * Not used for grid, just need to be set to unset array of etemplate_widget_box
	 *
	 * @var string|array
	 */
	protected $legacy_options = null;
}
