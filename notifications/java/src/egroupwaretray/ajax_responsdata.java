/**
 * EGroupware - Notifications Java Desktop App
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package notifications
 * @subpackage jdesk
 * @link http://www.egroupware.org
 * @author Stefan Werfling <stefan.werfling@hw-softwareentwicklung.de>
 * @author Maik Hüttner <maik.huettner@hw-softwareentwicklung.de>
 */

package egroupwaretray;

/**
 * ajax_responsdata
 * 
 * @author Stefan Werfling <stefan.werfling@hw-softwareentwicklung.de>
 */
public class ajax_responsdata
{
	private String _type;
	private Object _data;

	public String getType() { return this._type; }
	public Object getData() { return this._data; }

	public void setType(String n) { this._type = n; }
	public void setData(Object n) { this._data = n; }
}