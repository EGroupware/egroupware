/**
 * EGroupware eTemplate2 - JS Tag list object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link: https://www.egroupware.org
 * @author Nathan Gray
 * @copyright Nathan Gray 2013
 */

import type {Et2Select, Et2SelectState} from "./Et2Select/Et2Select";
import type {Et2SelectAccount} from "./Et2Select/Et2SelectAccount";
import type {Et2SelectEmail} from "./Et2Select/Et2SelectEmail";
import type {Et2SelectCategory} from "./Et2Select/Et2SelectCategory";
import type {Et2SelectThumbnail} from "./Et2Select/Et2SelectThumbnail";

/**
 * @deprecated use Et2Select
 */
export type et2_taglist = Et2Select;

/**
 * @deprecated use Et2SelectAccount
 */
export type et2_taglist_account = Et2SelectAccount;

/**
 * @deprecated use et2_SelectEmail
 */
export type et2_taglist_email = Et2SelectEmail;

/**
 * @deprecated use Et2SelectCatgory
 */
export type et2_taglist_category = Et2SelectCategory;

/**
 * @deprecated use Et2SelectThumbnail
 */
export type et2_taglist_thumbnail = Et2SelectThumbnail;

/**
 * @deprecated use Et2SelectState
 */
export type et2_taglist_state = Et2SelectState;