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
 * jegwconfig
 * 
 * @author Stefan Werfling <stefan.werfling@hw-softwareentwicklung.de>
 */
public class jegwconfig
{
    private jegwxmlconfig configmanager = null;

    public jegwconfig(String file) throws Exception
    {
        this.configmanager = new jegwxmlconfig(file);
    }

    public jegwxmlconfig getCXMLM()
    {
        return this.configmanager;
    }

    public void loadConfig() throws Exception
    {
        this.configmanager.read(false);
    }

    public void saveConfig() throws Exception
    {
        this.configmanager.write();
    }
}