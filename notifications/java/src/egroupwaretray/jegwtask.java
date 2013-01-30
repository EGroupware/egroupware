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

import java.awt.event.ActionEvent;
import java.awt.event.ActionListener;
import java.util.TimerTask;
import javax.swing.event.EventListenerList;

/**
 * jegwtask
 * 
 * @author Stefan Werfling <stefan.werfling@hw-softwareentwicklung.de>
 */
public class jegwtask extends TimerTask
{
    protected EventListenerList listenerList = new EventListenerList();

    public static final String EGW_TASK_RUN_UPDATE  = new String("egwrunupdate");

    public void addActionListener(ActionListener l)
    {
        listenerList.add(ActionListener.class, l);
    }

    protected void action(Object o, String command)
    {
        Object[] listeners = listenerList.getListenerList();

        ActionEvent e = new ActionEvent(o, ActionEvent.ACTION_PERFORMED, command);

        // Process the listeners last to first, notifying
        // those that are interested in this event
        for (int i = listeners.length-2; i>=0; i-=2) {
            if (listeners[i]==ActionListener.class) {
                ((ActionListener)listeners[i+1]).actionPerformed(e);
            }
        }
    }

    @Override
    public void run()
    {
        this.action(this, EGW_TASK_RUN_UPDATE);
    }
    
}
