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

import javax.swing.* ;
import java.awt.* ;
import java.awt.event.* ;
import java.awt.image.ImageObserver;
import java.awt.image.ImageProducer;
import java.io.IOException;
import java.io.InputStream;
import java.net.URL;
import java.util.ArrayList;
import java.util.logging.Level;
import java.util.logging.Logger;
import javax.imageio.ImageIO;

/**
 * hwTrayIcon
 * 
 * http://stackoverflow.com/questions/5057639/systemtray-based-application-without-window-on-mac-os-x
 * 
 * @author Stefan Werfling <stefan.werfling@hw-softwareentwicklung.de>
 */
public class hwTrayIcon extends JFrame implements MouseMotionListener, MouseListener
{
    private SystemTray stray;
    private TrayIcon trayicon;

    private Image imgicon;
    private String tolltip;
    private ArrayList popupitems = new ArrayList();
    private ActionListener trayaction = null;
    
    public hwTrayIcon(String tolltipe, String icon)
    {
        this.stray = SystemTray.getSystemTray();
        this.tolltip = tolltipe;
		
		try {
			this.imgicon = hwTrayIcon.getImage(icon);
		} catch (IOException ex) {
			Logger.getLogger(hwTrayIcon.class.getName()).log(Level.SEVERE, null, ex);
		}
    }

	public static Image getImage(String file) throws IOException
	{
		InputStream iImg =
				ClassLoader.getSystemClassLoader().getResourceAsStream(
					file
				);
		
		return ImageIO.read(iImg);
	}
	
    public void createTrayIcon()
    {
        PopupMenu popup = new PopupMenu();

        for(int i=0; i<this.popupitems.size(); i++)
        {
            popup.add((MenuItem)this.popupitems.get(i));
        }

        this.trayicon = new TrayIcon(this.imgicon, this.tolltip, popup);

        if(this.trayaction != null)
        {
            this.trayicon.addActionListener(this.trayaction);
            this.trayicon.addMouseListener(this);
            this.trayicon.addMouseMotionListener(this);
        }

        try
        {
            this.stray.add(this.trayicon);
        }
        catch (AWTException e)
        {
            System.err.println(
				jegwConst.getConstTag("egw_msg_trayicon_init_error"));
        }
    }

    public void setDisableMenuItem(String name, boolean enable)
    {
        for(int i=0; i<this.trayicon.getPopupMenu().getItemCount();  i++ )
        {
            MenuItem tmp = this.trayicon.getPopupMenu().getItem(i);
            
            if( tmp.getLabel().compareTo(name) == 0 )
            {
                tmp.setEnabled(enable);
            }
        }
    }

    public void addTrayAction(ActionListener action)
    {
        this.trayaction = action;
    }

    public void addMenuItem(String title, ActionListener lister)
    {
        MenuItem mItem = new MenuItem(title);
        mItem.addActionListener(lister);
		
        this.popupitems.add(mItem);
    }

    public void showBallon(String title, String text, TrayIcon.MessageType type)
    {
        this.trayicon.displayMessage(title, text, type);
    }

    public void changeIcon(String icon)
    {
        try {
			this.imgicon = hwTrayIcon.getImage(icon);
		} catch (IOException ex) {
			Logger.getLogger(hwTrayIcon.class.getName()).log(Level.SEVERE, null, ex);
		}
		
        this.trayicon.setImage(this.imgicon);
        this.trayicon.setImageAutoSize(true);
    }

    public String getTooltip()
    {
        return this.trayicon.getToolTip();
    }

    public void setTooltip(String text)
    {
        this.trayicon.setToolTip(text);
    }

    protected void action(String command)
    {
        ActionEvent e = new ActionEvent(
                this, 
                ActionEvent.ACTION_PERFORMED, 
                command
                );
        
        this.trayaction.actionPerformed(e);
    }

    public void mouseDragged(MouseEvent e)
    {
        // nix
    }

    public void mouseMoved(MouseEvent e)
    {
    }

    public void mouseClicked(MouseEvent e)
    {
        this.action("clicked");
    }

    public void mousePressed(MouseEvent e)
    {
    }

    public void mouseReleased(MouseEvent e)
    {
    }

    public void mouseEntered(MouseEvent e)
    {
    }

    public void mouseExited(MouseEvent e)
    {
    }
}