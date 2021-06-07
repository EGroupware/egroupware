/**
 * EGroupware eTemplate2 - JS Itempicker object
 * derived from et2_link_entry widget @copyright 2011 Nathan Gray
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Christian Binder
 * @author Nathan Gray
 * @copyright 2012 Christian Binder
 * @copyright 2011 Nathan Gray
 */

function itempickerDocumentAction(context, data)
{
	"use strict";

	let formid = "itempicker_action_form";
	let form = "<form id='" + formid + "' action='index.php?menuaction=" + data.app + "." + data.app + "_merge.download_by_request' method='POST'>"
		+ "<input type='hidden' name='data_document_name' value='" + data.value.name + "' />"
		+ "<input type='hidden' name='data_document_dir' value='" + data.value.dir + "' />"
		+ "<input type='hidden' name='data_checked' value='" + data.checked.join(',') + "' />"
		+ "</form>";
	jQuery("body").append(form);
	jQuery("#" + formid).submit().remove();
}

