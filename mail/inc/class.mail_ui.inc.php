<?php
/**
 * EGroupware - Mail - deprecated compat stub for the renamed mail_ui class
 *
 * @link http://www.egroupware.org
 * @package mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

/**
 * @deprecated since 2026-09-08 - the real implementation moved to mail/src/Ui.php as
 * EGroupware\Mail\Ui (matching the mail/src/Ui/*Handler classes it already delegates to). Kept as
 * a thin compat stub, not removed outright like mail_compose/mail_tree's own renames, since this
 * particular class has a much larger external surface (menuaction dispatch from any not-yet-rebuilt
 * cached JS bundle, third-party/EPL app code, saved bookmarks/links) - this stub means any of those
 * still keep working via plain class inheritance (static properties/methods are inherited, not
 * duplicated) instead of failing hard with "class not found" until everything referencing the old
 * bare name is updated/rebuilt. Safe to remove once nothing external still needs it.
 */
class mail_ui extends EGroupware\Mail\Ui
{
}
