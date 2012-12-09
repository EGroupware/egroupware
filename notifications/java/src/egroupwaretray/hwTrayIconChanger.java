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

import java.util.ArrayList;

/**
 * hwTrayIconChanger
 * 
 * @author Stefan Werfling <stefan.werfling@hw-softwareentwicklung.de>
 */
public class hwTrayIconChanger
{
    private hwTrayIcon hwtray   = null;
    private int index           = 0;
    private ArrayList iconlist  = new ArrayList();

    public hwTrayIconChanger(hwTrayIcon hwtray)
    {
        this.hwtray = hwtray;
    }

    public void addIcon(String icon)
    {
        this.iconlist.add(icon);
    }

    public void clearIconList()
    {
        this.iconlist.clear();
    }

    public int getIconCount()
    {
        return this.iconlist.size();
    }

    public void changeIcon()
    {
        if( this.iconlist.size() == 0 )
        {
            return;
        }

        if( this.index >= this.iconlist.size() )
        {
            this.index = 0;
        }

        String icon = (String) this.iconlist.get(this.index);
		
        this.hwtray.changeIcon(icon);
        this.index++;
    }
}
