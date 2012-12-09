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

import java.awt.Toolkit;
import java.awt.event.ActionListener;
import java.io.IOException;
import java.util.ArrayList;
import java.util.logging.Level;
import java.util.logging.Logger;
import javax.swing.JDialog;
import javax.swing.JFrame;

/**
 * jegwapplications
 * 
 * @author Stefan Werfling <stefan.werfling@hw-softwareentwicklung.de>
 */
public class jegwapplications
{
    private ArrayList dialogbuff    = new ArrayList();

    public jegwapplications(ActionListener action){}

    public void clearApps()
    {
        this.dialogbuff.clear();
    }

	public KeyArray searchMsgById(String id)
    {
		for(int i=0; i<this.dialogbuff.size(); i++ )
        {
            Object tob = this.dialogbuff.get(i);
            String classname = tob.getClass().getName();
            
            if(classname.compareTo("egroupwaretray.KeyArray")==0)
            {
                KeyArray app = (KeyArray) tob;
                
                if( app.getString("notify_id").compareTo(id) == 0 )
                {
                    return app;
                }
            }
        }
        
        return null;
	}

    public void updateApp(KeyArray app)
    {
        for(int i=0; i<this.dialogbuff.size(); i++ )
        {
            Object tob = this.dialogbuff.get(i);
            String classname = tob.getClass().getName();

            if(classname.compareTo("egroupwaretray.KeyArray")==0)
            {
                KeyArray appl = (KeyArray) tob;

                if(appl.getString("notify_id").compareTo(app.getString("notify_id")) == 0)
                {
                    this.dialogbuff.remove(i);
                    this.dialogbuff.add(app);
                }
            }
        }
    }

	public void removeMsgById(String id)
	{
		for(int i=0; i<this.dialogbuff.size(); i++ )
        {
            Object tob = this.dialogbuff.get(i);
            String classname = tob.getClass().getName();

            if(classname.compareTo("egroupwaretray.KeyArray")==0)
            {
                KeyArray appl = (KeyArray) tob;

                if(appl.getString("notify_id").compareTo(id) == 0)
                {
					this.dialogbuff.remove(i);
				}
			}
		}
	}
	
    public void setNewApplicationDatas(ArrayList data, jegwMain mainobj)
    {
        for( int i=0; i<data.size(); i++ )
        {
            Object tob = data.get(i);

            String classname = tob.getClass().getName();

            if(  classname.compareTo("egroupwaretray.KeyArray")==0 )
            {
                KeyArray app = (KeyArray) tob;
                String notifyid = app.getString("notify_id");

                KeyArray buffmsg = this.searchMsgById(notifyid);

                if( buffmsg == null )
                {
                    //this.appbuff.add(app);
                    this.dialogbuff.add(app);
                    continue;
                }
                else
                {
					this.dialogbuff.add(app);
					this.updateApp(app);
                }
            }
        }

        this.showDialog(mainobj);
    }

    public int getDBufferCount()
    {
        return this.dialogbuff.size();
    }

    public void showDialog(jegwMain mainobj)
    {
        if( this.dialogbuff.size() > 0 )
        {
            ArrayList tmp = new ArrayList();

            for( int i=0; i<this.dialogbuff.size(); i++ )
            {
                KeyArray app = (KeyArray) this.dialogbuff.get(i);

                JFrame jf = EgroupwareTrayApp.getApplication().getMainFrame();
                JDialog dialog = new jegwInfoDialog(jf, true);
				
				try {
					dialog.setIconImage(hwTrayIcon.getImage(jegwConst.getConstTag("egwicon")));
				} catch (IOException ex) {
					Logger.getLogger(hwTrayIcon.class.getName()).log(Level.SEVERE, null, ex);
				}
				
                ((jegwInfoDialog)dialog).setNotifiyId(app.getString("notify_id"));

                if( mainobj != null )
                {
                    ((jegwInfoDialog)dialog).addActionListener(mainobj);
                }
                
                ((jegwInfoDialog)dialog).setInfoDialog(app.getString("msghtml"));
				
                dialog.setAlwaysOnTop(true);
                dialog.setFocusableWindowState(false);
                dialog.setVisible(true); // Warten bis Ok dann nächsten anzeigen
              
                /**
                 * wurde die Info gelesen dann raus damit
                 */
                if( !((jegwInfoDialog) dialog).isInfoRead() )
                {
                    tmp.add(this.dialogbuff.get(i));
                }
            }

            // Wenn fertig dann Leeren
            this.dialogbuff.clear();
            this.dialogbuff = tmp;
        }
    }
}