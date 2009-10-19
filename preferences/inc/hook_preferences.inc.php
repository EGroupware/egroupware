<?php
/**
 * Preferences - easing migration to new hooks
 *
 * @link http://www.egroupware.org
 * @package preferences
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

ExecMethod('phpgwapi.hooks.register_all_hooks');
preferences_hooks::preferences(array('location' => 'preferences'));
