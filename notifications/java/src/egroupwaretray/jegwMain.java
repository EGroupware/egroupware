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

import edu.stanford.ejalbert.BrowserLauncher;
import egroupwaretray.settings.egwSettingUrl;
import java.awt.MenuItem;
import java.awt.SystemTray;
import java.awt.TrayIcon;
import java.awt.event.ActionEvent;
import java.awt.event.ActionListener;
import java.io.IOException;
import java.util.ArrayList;
import java.util.Locale;
import java.util.Timer;
import java.util.logging.Level;
import java.util.logging.Logger;
import javax.swing.JDialog;
import javax.swing.JFrame;
import javax.swing.JOptionPane;

/**
 * jegwMain
 * 
 * @author Stefan Werfling <stefan.werfling@hw-softwareentwicklung.de>
 */
public class jegwMain implements ActionListener
{
    private hwTrayIcon hwtray   = new hwTrayIcon(
            jegwConst.getConstTag("egwtitle"),
            jegwConst.getConstTag("egwiconoff"));

    private jegwconfig egwconfig  = null;
    private KeyArray activconf  = null;
    private jegwhttp egwhttp    = new jegwhttp(this);
    private KeyArray egwcookie  = null;
    private Timer egwttask      = new Timer();
    private Timer egwtcd        = new Timer();

    private jegwapplications applications = new jegwapplications(this);

    private hwTrayIconChanger _iconchanger = null;

    public jegwMain()
    {
        try
        {
			if( !SystemTray.isSupported() )
			{
				jegwMain.infoDialog("The system tray isnt supported!", "Error");
                System.exit(0);
			}
			
			
            /**
             * Einstellungen Laden
             */
            try
            {
                this.egwconfig = new jegwconfig(
					jegwConst.getConstTag("egwaccountfile"));
				
                this.egwconfig.loadConfig();
            }
            catch( Exception exp )
            {
                jegwMain.infoDialog(
					"Error on load const file: no such file.",
                    "Warning");

                System.exit(0);
            }

			// Debuging
			Boolean _debuging = ( jegwConst.getConstTag("egw_debuging").trim() 
				== "1" ? true : false);
			
			egwDebuging.setDebuging(_debuging);
			egwDebuging.setLevel(Level.parse(
				jegwConst.getConstTag("egw_debuging_level").trim()));
			// END 
			
            if( this.egwconfig.getCXMLM().countConf() < 1 )
            {
				String turl = jegwConst.getConstTag("egw_dc_url").trim();
				String tlogindomain = jegwConst.getConstTag("egw_dc_logindomain").trim();
				String tusername = jegwConst.getConstTag("egw_dc_username").trim();
				
				if( (!turl.isEmpty()) && 
					(!tlogindomain.isEmpty()) &&
					(!tusername.isEmpty()) )
				{
					KeyArray config = new KeyArray(jegwxmlconfig.CONF_STRUCT);
					
					config.add("host", "oneconfig");
					config.add("user", tusername);
					config.add("logindomain", tlogindomain);
					config.add("egwurl", turl);
					
					this.egwconfig.getCXMLM().addConf(config);
					this.egwconfig.saveConfig();
				}
				else
				{
					jegwMain.infoDialog(
						jegwConst.getConstTag("egw_msg_config_create"), 
						jegwConst.getConstTag("info_info")
						);

					JFrame jf = EgroupwareTrayApp.getApplication().getMainFrame();
					JDialog dialog = new egwSettingUrl(jf, true, this.egwconfig);

					try {
						dialog.setIconImage(hwTrayIcon.getImage(jegwConst.getConstTag("egwicon")));
					} catch (IOException ex) {
						Logger.getLogger(hwTrayIcon.class.getName()).log(Level.SEVERE, null, ex);
					}

					dialog.setAlwaysOnTop(true);
					dialog.setVisible(true);

					if( this.egwconfig.getCXMLM().countConf() < 1 )
					{
						jegwMain.infoDialog(
							jegwConst.getConstTag("egw_msg_setting_aborting"),
							jegwConst.getConstTag("info_info"));

						System.exit(0);
					}
				}
            }
			else
			{
				// Certificates load and set
				String sslcert = this.egwconfig.getCXMLM().getActivConf().getString("sslcert");
				
				if( !sslcert.trim().isEmpty() )
				{
					BaseHttp.getTrustManager().setAcceptedCerts(sslcert);
				}
			}

            // Tray Icon erstellen
            //this.hwtray.AddMenuItem("InfoDialog", this);

            this.hwtray.addMenuItem(jegwConst.getConstTag("MI_browser"), this);
            this.hwtray.addMenuItem(jegwConst.getConstTag("MI_login"), this);
            this.hwtray.addMenuItem(jegwConst.getConstTag("MI_logout"), this);
            this.hwtray.addMenuItem(jegwConst.getConstTag("MI_settings"), this);
            this.hwtray.addMenuItem(jegwConst.getConstTag("MI_exit"), this);
            this.hwtray.addTrayAction(this);
            this.hwtray.createTrayIcon();

            this.hwtray.setDisableMenuItem(jegwConst.getConstTag("MI_login"), true);
            this.hwtray.setDisableMenuItem(jegwConst.getConstTag("MI_logout"), false);

            this._iconchanger = new hwTrayIconChanger(this.hwtray);

            this.egwstart();
        }
        catch (Exception ex)
        {
            Logger.getLogger(jegwMain.class.getName()).log(Level.SEVERE, null, ex);
            jegwMain.debugDialog(ex.getMessage());
			egwDebuging.log.log(Level.SEVERE, null, ex);
        }
    }

	private Locale getSystemLang()
	{
		return Locale.getDefault();
	}
	
    private void egwstart()
    {
        this.egwttask      = new Timer();
        this.egwtcd        = new Timer();

        // Task erstellen
        jegwtask task = new jegwtask();
        task.addActionListener(this);

        // Automatisches Login
        this.egwAutologin();

		long period = Long.parseLong(jegwConst.getConstTag("egw_dc_timeout_notify"));
		
        // Automatische Task starten
        this.egwttask.schedule(task, 5000, period);

        /**
         * Info Time out task
         */
        jegwTaskCoundownViewer tviewer = new jegwTaskCoundownViewer();
        tviewer.addActionListener(this);
        tviewer.setCounDown(4000);

        this.egwtcd.schedule(tviewer, 0, 1000);
    }

    private void egwAutologin()
    {
        this.activconf = (KeyArray) this.egwconfig.getCXMLM().getActivConf().clone();

        egwPasswordCrypt egwcp = new egwPasswordCrypt();

        if( this.activconf.getString("password").length() != 0 )
        {
            try
            {
                this.activconf.add("password",
                        egwcp.decode(this.activconf.getString("password")));
            }
            catch(Exception exp)
            {
                this.activconf.add("password", "");
                this.exceptionMsg(exp.getMessage(), exp);
            }
        }

        if( this.activconf.getString("password").length() == 0 )
        {
            JFrame jf = EgroupwareTrayApp.getApplication().getMainFrame();
            JDialog dialog = new jegwPasswordDialog(jf, true);
			
			try {
				dialog.setIconImage(hwTrayIcon.getImage(jegwConst.getConstTag("egwicon")));
			} catch (IOException ex) {
				Logger.getLogger(hwTrayIcon.class.getName()).log(Level.SEVERE, null, ex);
			}
	
			((jegwPasswordDialog) dialog).setUH(
				this.activconf.getString("user"),
                this.activconf.getString("host")
				);
			
			dialog.setAlwaysOnTop(true);
            dialog.setVisible(true);
            String password = ((jegwPasswordDialog) dialog).getPassword();

            if( password.length() != 0 )
            {
                this.activconf.add("password", password);

                // Passwort Speichern
                if( ((jegwPasswordDialog) dialog).isSavePassword() )
                {
                    for(int i=0; i<this.egwconfig.getCXMLM().countConf(); i++)
                    {
                        KeyArray conf = this.egwconfig.getCXMLM().getConf(i);

                        if( (conf.getString("user").compareTo(
                                this.activconf.getString("user")) == 0) &&
                                (conf.getString("host").compareTo(
                                this.activconf.getString("host")) == 0) )
                        {
                            this.egwconfig.getCXMLM().deleteConf(i);

                            try
                            {
                                conf.add("password", egwcp.encode(password));
                            }
                            catch(Exception exp)
                            {
                                // passwort konnte nicht gespeichert werden
                                this.exceptionMsg(exp.getMessage(), exp);
                            }

                            this.egwconfig.getCXMLM().addConf(conf);
                            
                            try
                            {
                                this.egwconfig.saveConfig();
                            }
                            catch(Exception exp)
                            {
                                this.exceptionMsg(exp.getMessage(), exp);
                            }
                        }
                    }
                }
            }
            else
            {
                // Benutzerabbruch kein Passwort
                if( jegwMain.confirmDialog(
					jegwConst.getConstTag("egw_msg_login_aborting"), 
					jegwConst.getConstTag("info_info")) == 0 )
                {
                    System.exit(0);
                }

                return;
            }
        }


        try
        {
            this.hwtray.showBallon(jegwConst.getConstTag("info_info"),
                    jegwConst.getConstTag("egw_msg_login_start"),
                    TrayIcon.MessageType.INFO);

            this.egwcookie = this.egwhttp.egwLogin(this.activconf);
        }
        catch( Exception ex)
        {
            this.exceptionMsg(ex.getMessage(), ex);
        }
        
        if( this.egwcookie != null )
        {
            this.hwtray.showBallon(
				jegwConst.getConstTag("info_login"), 
				jegwConst.getConstTag("egw_txt_account") + " " +
				this.activconf.getString("user") + " " + 
				jegwConst.getConstTag("egw_txt_login") + " " +
				this.activconf.getString("host") + ".",
				TrayIcon.MessageType.INFO);

            // Icon ändern
            this.hwtray.changeIcon(jegwConst.getConstTag("egwicon"));

            this.hwtray.setDisableMenuItem(jegwConst.getConstTag("MI_login"), false);
            this.hwtray.setDisableMenuItem(jegwConst.getConstTag("MI_logout"), true);

            // Webdav mount
            //this.mountEgwWebdav();
        }
    }

    private void mountEgwWebdav()
    {
        KeyArray conf = this.activconf;
        
        String apps = (String) conf.get("webdav_apps");
        
        if( !apps.equals("") )
        {
            
        }
    }

    static public void debugDialog(String str)
    {
        JOptionPane.showMessageDialog(
			EgroupwareTrayApp.getApplication().getMainFrame(),
			str, "Debug", JOptionPane.ERROR_MESSAGE);
    }
    
    static public void infoDialog(String str, String title)
    {
        JOptionPane.showMessageDialog(
			EgroupwareTrayApp.getApplication().getMainFrame(),
			str, title, JOptionPane.INFORMATION_MESSAGE);
    }

    static public int confirmDialog(String str, String title)
    {
        return JOptionPane.showConfirmDialog(
			EgroupwareTrayApp.getApplication().getMainFrame(),
			str, title, JOptionPane.YES_NO_OPTION);
    }

    private void logout()
    {
        this.egwttask.cancel();
        this.egwtcd.cancel();

        this.hwtray.setDisableMenuItem(jegwConst.getConstTag("MI_login"), true);
        this.hwtray.setDisableMenuItem(jegwConst.getConstTag("MI_logout"), false);

        this.applications.clearApps();

        try
        {
            this.egwhttp.egwLogout(this.activconf, this.egwcookie);
        }
        catch( Exception ex)
        {
            this.exceptionMsg(ex.getMessage(), ex);
        }
    }

	/**
	 * openBrowser
	 * open Browser with address to Egroupware
	 */
    public void openBrowser()
    {
        if( (this.egwcookie != null) && (this.egwhttp.egwIsEGWLogin()) )
        {
			String openlink = this.egwhttp.egwGetOpenEGWLink(
				this.activconf,
				this.egwcookie,
				""
				);

			try
			{
				BrowserLauncher launcher = new BrowserLauncher();
				launcher.openURLinBrowser(openlink);

				this.hwtray.showBallon(
					jegwConst.getConstTag("info_info"),
					jegwConst.getConstTag("egw_msg_start_browser"),
					TrayIcon.MessageType.INFO);
			}
			catch( Exception exp )
			{
				// Error
				this.hwtray.showBallon(
					jegwConst.getConstTag("info_info"),
					jegwConst.getConstTag("egw_msg_start_browser_error"),
					TrayIcon.MessageType.ERROR);
				
				egwDebuging.log.log(Level.SEVERE, null, exp);
			}
		}
    }

	/**
	 * checkIconChangeInfo
	 * check is icon change for information
	 * 
	 */
    private void checkIconChangeInfo()
    {
        if( this.applications.getDBufferCount() > 0 )
        {
            if( this._iconchanger.getIconCount() == 0 )
            {
                this._iconchanger.addIcon(jegwConst.getConstTag("egwicon"));
                this._iconchanger.addIcon(jegwConst.getConstTag("egwiconinfo"));
            }

            this._iconchanger.changeIcon();
        }
        else
        {
            this._iconchanger.clearIconList();
            this.hwtray.changeIcon(jegwConst.getConstTag("egwicon"));
        }
    }

    public void actionPerformed(ActionEvent e) 
    {
        String sclass   = e.getSource().getClass().getName();
        Object cclass   = e.getSource();
        String cmd      = e.getActionCommand();

        /**
         * Action Menu
         */
        if( sclass.compareTo("java.awt.MenuItem") == 0 )
        {
            MenuItem item = (MenuItem) cclass;

            /**
             * Action Exit
             */
            if(item.getLabel().compareTo(jegwConst.getConstTag("MI_exit")) == 0)
            {
                if( this.egwhttp.egwIsEGWLogin() )
                {
                    this.logout();
                }

                System.exit(0);
            }

            /**
             * Action Settings
             */
            if( item.getLabel().compareTo(
				jegwConst.getConstTag("MI_settings")) == 0 )
            {
                JFrame jf = EgroupwareTrayApp.getApplication().getMainFrame();
                JDialog dialog = new egwSettingUrl(jf, true, this.egwconfig);
				
                try {
					dialog.setIconImage(hwTrayIcon.getImage(jegwConst.getConstTag("egwicon")));
				} catch (IOException ex) {
					Logger.getLogger(hwTrayIcon.class.getName()).log(Level.SEVERE, null, ex);
					egwDebuging.log.log(Level.SEVERE, null, ex);
				}
				
				dialog.setAlwaysOnTop(true);
                dialog.setVisible(true);
                // Aktuelle Config benutzen
                this.egwAutologin();
            }

            /**
             * Action Login
             */
            if( item.getLabel().compareTo(jegwConst.getConstTag("MI_login") ) == 0)
            {
                this.egwstart();
            }

            /**
             * Action Logout
             */
            if( item.getLabel().compareTo(jegwConst.getConstTag("MI_logout") ) == 0)
            {
                this.logout();
            }

            /**
             * Action Browser Open
             */
            if( item.getLabel().compareTo(jegwConst.getConstTag("MI_browser") ) == 0)
            {
                this.openBrowser();
            }

            /**
             * Action Info Dialog
             */
            if( item.getLabel().compareTo("InfoDialog") == 0 )
            {
                JFrame jf = EgroupwareTrayApp.getApplication().getMainFrame();
                JDialog dialog = new jegwInfoDialog(jf, true);
                dialog.setVisible(true);
            }

        }

        if( sclass.compareTo("egroupwaretray.hwTrayIcon") == 0 )
        {
            if( (cmd != null) && (cmd.compareTo("clicked") == 0) )
            {
                this.applications.showDialog(this);
            }
        }

        if( sclass.compareTo("java.awt.TrayIcon") == 0 )
        {
            TrayIcon ticon = (TrayIcon) cclass;

            this.openBrowser();
        }

        if( sclass.compareTo("egroupwaretray.jegwtask") == 0 )
        {
            if( this.egwhttp.egwIsEGWLogin() )
            {
                try
                {
                    /**
                     * Info Time out task
                     */
                    jegwTaskCoundownViewer tviewer = new jegwTaskCoundownViewer();
                    tviewer.addActionListener(this);
					
					long period = Long.parseLong(jegwConst.getConstTag("egw_dc_timeout_notify"));
					
                    tviewer.setCounDown((int)period);

                    this.egwtcd.schedule(tviewer, 0, 1000);

                    /**
                     * Daten laden
                     */
                    ArrayList data = this.egwhttp.egwLoadEGWData(this.activconf, this.egwcookie);
                    //System.out.print(data);
                    this.applications.setNewApplicationDatas(data, this);
                }
                catch( Exception ex)
                {
                    this.exceptionMsg(ex.getMessage(), ex);
                }
            }
            else
            {
                //
                if( jegwMain.confirmDialog(
					jegwConst.getConstTag("egw_msg_repeat_login"),
                    jegwConst.getConstTag("egw_txt_title_login")) == 0 )
                {
                    this.egwAutologin();
                }
            }
        }

        /**
         * Zeit anzeige updaten
         */
        if( sclass.compareTo("egroupwaretray.jegwTaskCoundownViewer") == 0 )
        {
            this.hwtray.setTooltip(jegwConst.getConstTag("egwtitle") + " " +
				String.format(jegwConst.getConstTag("egw_txt_update"), 
				(Object[]) new String[]{e.getActionCommand().toString()})
				);
			
            this.checkIconChangeInfo();
        }

        if( sclass.compareTo("egroupwaretray.jegwInfoDialog") == 0 )
        {
            String[] tmp = e.getActionCommand().toString().split(":");

            if( tmp[0].compareTo("OPENAPP") == 0 )
            {
                if( (this.egwcookie != null) && (this.egwhttp.egwIsEGWLogin()) )
                {
					String menuaction = "";
					
					KeyArray tmsg = this.applications.searchMsgById(tmp[1]);
					
					if( tmsg != null )
					{
						this.applications.removeMsgById(tmp[1]);
						menuaction = tmsg.getString("link");
					}
					
					String openlink = this.egwhttp.egwGetOpenEGWLink(
							this.activconf,
							this.egwcookie,
							menuaction
							);

					try
					{
						BrowserLauncher launcher = new BrowserLauncher();
						launcher.openURLinBrowser(openlink);

						this.hwtray.showBallon(
							jegwConst.getConstTag("info_info"),
							jegwConst.getConstTag("egw_msg_start_browser"),
							TrayIcon.MessageType.INFO);
					}
					catch(Exception exp)
					{
						egwDebuging.log.log(Level.SEVERE, null, exp);
						
						// Fehler
						this.hwtray.showBallon(
							jegwConst.getConstTag("info_info"),
							jegwConst.getConstTag("egw_msg_start_browser_error"),
							TrayIcon.MessageType.ERROR);
					}
                }
            }
        }
    }

    private void exceptionMsg(String emsg, Exception ex)
    {
        String[] msg = emsg.split(":");

        this.hwtray.changeIcon(jegwConst.getConstTag("egwiconerror"));

        if( msg[0].compareTo("NETERROR") == 0 )
        {
            jegwMain.infoDialog(
				jegwConst.getConstTag("egw_msg_connection_error"), 
				jegwConst.getConstTag("egw_txt_connection_error")
				);
			
            return;
        }
        else if( msg[0].compareTo("LOGIN") == 0 )
        {
            jegwMain.infoDialog(
				jegwConst.getEGWStatus(msg[1]), 
				jegwConst.getConstTag("egw_txt_title_login")
				);

            if( jegwMain.confirmDialog(
					jegwConst.getConstTag("egw_msg_repeat_login"),
                    jegwConst.getConstTag("egw_txt_title_login")) == 0 )
			{
                this.egwAutologin();
            }

            return;
        }
        else if( msg[0].compareTo("PERMISSIONDENIED") == 0 )
        {
            jegwMain.infoDialog(
				jegwConst.getConstTag("egw_msg_premission_denied") +
				"\r\n" +
				jegwConst.getConstTag("egw_msg_contact_admin"), 
				jegwConst.getConstTag("egw_txt_title_premission"));
			
            return;
        }
        else if( msg[0].compareTo("PAGENOTFOUND") == 0 )
        {
             jegwMain.infoDialog(
				jegwConst.getConstTag("egw_msg_page_not_found"),
				jegwConst.getConstTag("egw_txt_title_notifier")
				);
			 
             return;
        }
        else if( msg[0].compareTo("HOSTNOTFOUND") == 0 )
        {
            jegwMain.infoDialog(
				jegwConst.getConstTag("egw_msg_domain_not_found"),
				jegwConst.getConstTag("egw_txt_title_notifier")
				);
			
            return;
        }
        else
        {
            jegwMain.debugDialog(msg[0]);
        }

        Logger.getLogger(jegwMain.class.getName()).log(Level.SEVERE, null, ex);
		egwDebuging.log.log(Level.SEVERE, null, ex);
    }
}