/**
 * EGroupware eTemplate2 - JS Link object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 * @copyright 2011 Nathan Gray
 */

import {Et2LinkList} from "./Et2Link/Et2LinkList";
import type {Et2LinkString} from "./Et2Link/Et2LinkString";
import {Et2Link} from "./Et2Link/Et2Link";
import type {Et2LinkTo} from "./Et2Link/Et2LinkTo";
import type {Et2LinkAppSelect} from "./Et2Link/Et2LinkAppSelect";
import type {Et2LinkEntry, Et2LinkEntryReadonly} from "./Et2Link/Et2LinkEntry";
import {Et2LinkAdd} from "./Et2Link/Et2LinkAdd";

/**
 * @deprecated use Et2LinkTo
 */
export type et2_link_to = Et2LinkTo;

/**
 * @deprecated use Et2LinkAppSelect
 */
export type et2_link_apps = Et2LinkAppSelect;

/**
 * @deprecated use Et2LinkEntry
 */
export type et2_link_entry = Et2LinkEntry;

/**
 * @deprecated use Et2Link
 */
export type et2_link = Et2Link;

/**
 * @deprecated use Et2LinkEntryReadonly
 */
export type et2_link_entry_ro = Et2LinkEntryReadonly;

/**
 * @deprecated use Et2LinkString
 */
export type et2_link_string = Et2LinkString;

/**
 * @deprecated use Et2LinkList
 */
// can't just define as type, as tracker/app.ts uses it with iterateOver()!
// export type et2_link_list = Et2LinkList;
export class et2_link_list extends Et2LinkList {}

/**
 * @deprecated use Et2LinkAdd
 */
export type et2_link_add = Et2LinkAdd;