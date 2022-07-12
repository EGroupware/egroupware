/**
 * EGroupware eTemplate2 - JS Select account widget
 *
 * Selecting accounts needs special UI, and displaying needs special consideration
 * to avoid sending the entire user list to the client.
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 * @copyright Nathan Gray 2012
 */

import type {Et2SelectAccountReadonly} from "./Et2Select/Et2SelectReadonly";
import type {Et2SelectAccount} from "./Et2Select/Et2SelectAccount";

/**
 * @deprecated use Et2SelectAccount
 */
export type et2_selectAccount = Et2SelectAccount;

/**
 * @deprecated use Et2SelectAccountReadonly
 */
export type et2_selectAccount_ro = Et2SelectAccountReadonly;