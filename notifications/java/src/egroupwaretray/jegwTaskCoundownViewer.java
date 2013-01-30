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
 * jegwTaskCoundownViewer
 * 
 * @author Stefan Werfling <stefan.werfling@hw-softwareentwicklung.de>
 */
public class jegwTaskCoundownViewer extends TimerTask
{
    protected EventListenerList listenerList = new EventListenerList();
    
    //verbleibende Millisekunden
    private int timeInMs = 0;
    //countdown Zeit in Millisekunden
    private int countDownMs = 10000;

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

    public void setCounDown(int ms)
    {
        this.countDownMs = ms;
    }

    @Override
    public void run()
    {
        //Falls Gesamtzeit - vergangener Zeit > 0
        if( this.countDownMs - this.timeInMs > 0 )
        {
            //erhöhe Zeitcounter um 1000 ms
            this.timeInMs += 1000;
            //Zeit in Sekunden Updaten

            this.action(this, " " + ( (countDownMs - timeInMs) / 1000 ) );
        }
        else
        {
            //Falls Gesamtzeit - vergangener Zeit <= 0
            this.cancel();
        }
    }
}