<?php
/**
 * EGroupware - InfoLog - Storage object (compatibility shim)
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package infolog
 * @copyright (c) 2003-17 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

/**
 * Zero-logic compatibility shim, kept only so that a hook registration still pointing at
 * the pre-migration dotted string 'infolog.infolog_so.change_delete_owner' (cached in an
 * installation's hooks table from before its next setup/upgrade run - upgrades don't
 * re-register hooks automatically for an already-running instance) keeps resolving
 * correctly, rather than fataling on a missing class.
 *
 * All real logic moved to \EGroupware\Infolog\Storage (infolog/src/Storage.php) as part of
 * doc/ai/projects/infolog-storage-migration.md - new code should use that class directly
 * (via infolog_bo::$so, which already does), not this one. The 'deleteaccount' hook itself
 * is now registered directly against \EGroupware\Infolog\Storage::change_delete_owner (see
 * infolog/setup/setup.inc.php) for any installation whose hooks get freshly registered;
 * this class exists purely for the transition period on installations that haven't re-run
 * setup yet.
 *
 * Do not add any logic here - add it to \EGroupware\Infolog\Storage instead.
 */
class infolog_so extends \EGroupware\Infolog\Storage
{
}
