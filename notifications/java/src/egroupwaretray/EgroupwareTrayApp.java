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

import org.jdesktop.application.Application;
import org.jdesktop.application.SingleFrameApplication;

/**
 * EgroupwareTrayApp
 * The main class of the application.
 * 
 * @author Stefan Werfling <stefan.werfling@hw-softwareentwicklung.de>
 */
public class EgroupwareTrayApp extends SingleFrameApplication {

    /**
     * At startup create and show the main frame of the application.
     */
    @Override protected void startup() {
        
    }

    /**
     * This method is to initialize the specified window by injecting resources.
     * Windows shown in our application come fully initialized from the GUI
     * builder, so this additional configuration is not needed.
     */
    @Override protected void configureWindow(java.awt.Window root) {
    }

    /**
     * A convenient static getter for the application instance.
     * @return the instance of EgroupwareTrayApp
     */
    public static EgroupwareTrayApp getApplication() {
        return Application.getInstance(EgroupwareTrayApp.class);
    }

    /**
     * Main method launching the application.
     */
    public static void main(String[] args) 
    {
        // Trayer Main Classe erstellen
		try
        {
			jegwMain jegwMain = new jegwMain();
		}
		catch (Throwable uncaught)
        {
        	egwDebuging.dumpUncaughtError(uncaught);
        }
    }
}
