/**
 * EGroupware eTemplate2 - Stubs for no longer existing legacy date-widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 * @copyright Nathan Gray 2011
 */

import {date} from "./lib/date.js";

import {Et2Date} from "./Et2Date/Et2Date";
import {Et2DateDuration} from "./Et2Date/Et2DateDuration";
import {Et2DateDurationReadonly} from "./Et2Date/Et2DateDurationReadonly";
import {Et2DateReadonly} from "./Et2Date/Et2DateReadonly";
import {Et2DateRange} from "./Et2Date/Et2DateRange";

/**
 * @deprecated use Et2Date
 */
export class et2_date extends Et2Date {}

/**
 * @deprecated use Et2Date
 */
export class et2_date_duration extends Et2DateDuration {}

/**
 * @deprecated use Et2Date
 */
export class et2_date_duration_ro extends Et2DateDurationReadonly {}

/**
 * @deprecated use Et2Date
 */
export class et2_date_ro extends Et2DateReadonly {}

/**
 * Widget for selecting a date range
 *
 * @todo port to web-component
 */
export class et2_date_range extends Et2DateRange
{
}