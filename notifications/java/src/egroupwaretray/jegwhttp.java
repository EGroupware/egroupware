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

import java.awt.event.ActionListener;
import java.net.InetAddress;
import java.util.*;
import org.json.simple.parser.ContainerFactory;
import org.json.simple.parser.JSONParser;
import org.json.simple.parser.ParseException;

/**
 * jegwhttp
 * 
 * @author Stefan Werfling <stefan.werfling@hw-softwareentwicklung.de>
 */
public class jegwhttp
{
    public static final String EGWHTTP_INDEXSCRIPT              = "index.php";
    public static final String EGWHTTP_LOGINSCRIPT              = "login.php";
    public static final String EGWHTTP_LOGOUTSCRIPT             = "logout.php";
    public static final String EGWHTTP_TRAYMODUL                = "egwnotifier/index.php";
    public static final String EGWHTTP_TRAYLOGIN                = "notifierlogin.php";
    public static final String EGWHTTP_LOGINLINK                = "login.php?cd=";
    
    public static final String EGWHTTP_POST_VAR_PASSWORD_TYPE   = "passwd_type";
    public static final String EGWHTTP_POST_VAR_ACCOUNT_TYPE    = "account_type";
    public static final String EGWHTTP_POST_VAR_LOGINDOMAIN     = "logindomain";
    
    public static final String EGWHTTP_POST_VAR_LOGIN           = "login";
    public static final String EGWHTTP_POST_VAR_PASSWD          = "passwd";
    public static final String EGWHTTP_POST_VAR_SUBMITIT        = "submitit";

    public static final String EGWHTTP_POST_PASSWORD_TYPE_TEXT  = "text";
    public static final String EGWHTTP_POST_ACCOUNT_TYPE_U      = "u";
    public static final String EGWHTTP_POST_LOGINDOMAIN_DEFAULT = "default";
    public static final String EGWHTTP_POST_SUBMITIT            = "++Anmelden++";

    public static final String EGWHTTP_TAG_SECURITYID           = "egwcheckid";

    public static final String EGWHTTP_GET_VAR_MAKE_SECURITYID  = "makecheck";
    public static final String EGWHTTP_GET_VAR_SECURITYID       = "checkid";
    public static final String EGWHTTP_GET_MAKE_SECURITYID      = "1";
    public static final String EGWHTTP_GET_PHPGW_FORWARD        = "phpgw_forward";
    
	// new EPL Notifications
	public static final String EGWHTTP_GET_NOTIFICATIONS_ACTION = 
		"json.php?menuaction=notifications.notifications_jdesk_ajax.get_notifications";
	
	// new EPL Notifications
	public static final String EGWHTTP_GET_NOTIFICATIONS_ACTION_CONFIRM = 
		"json.php?menuaction=notifications.notifications_jdesk_ajax.confirm_message";
	
    public static final String[] EGWHTTP_EGW_COOKIE             = new String[]{
        "sessionid", "kp3", "last_loginid", "last_domain", "domain"};

    public static final String[] EGWHTTP_EGW_APP                = new String[]{
        "name", "title", "infotext", "variables", "appdialogtext" };
    
	public static final String[] EGWHTTP_EGW_NOTIFY             = new String[]{
        "app", "title", "msghtml", "link", "notify_id" };
	
    public static final String[] EGWHTTP_EGW_APP_VAR            = new String[]{
        "vname", "value", "vtype" };
    
    private BaseHttp httpcon        = new BaseHttp();
    private Boolean loginstatus     = false;

    public jegwhttp(ActionListener lister) {
		
		this.httpcon.setSocketTimeOut(
			Integer.parseInt(
				jegwConst.getConstTag("egw_dc_timeout_socket")));
	}

    /**
     * CheckDir
     * Überprüft ob hinten ein String (wie bei Verzeichnisen mit "/" abschließt),
     * wenn nicht wird dieser hinten drangesetzt und der String zurück geben
     *
     * @param dir
     * @return
     */
    static public String checkDir(String dir)
    {
        if(dir.length() > 0)
        {
            if(dir.charAt(dir.length()-1) != "/".charAt(0) )
            {
                dir = dir + "/";
            }
        }

        return dir;
    }

    public void egwUrlLinkParser(KeyArray Config) throws Exception
    {
        String url = Config.getString("egwurl");

        boolean ssl = false;

        if(  url.indexOf("https://") > -1 )
        {
            ssl = true;
            url = url.replaceAll("https://", "");
        }
        else
        {
            url = url.replaceAll("http://", "");
        }

        if( url.length() == 0 )
        {
            throw new Exception("NOURL");
        }

        String[] tmp = url.split("/");

		String host = tmp[0];
		String port = "";
		String paht = "";
		
		if( tmp[0].indexOf(":") != -1 )
		{
			String[] ttmp = tmp[0].split(":");
			host = ttmp[0];
			port = ttmp[1];
		}
		
        /**
         * Host auflösen
         */
        try
        {
            InetAddress addr = InetAddress.getByName(host);
        }
        catch( Exception exp)
        {
            throw new Exception("HOSTNOTFOUND");
        }

        for( int i=1; i<tmp.length; i++ )
        {
            paht = paht + tmp[i] + "/";
        }

        /*if( paht.length() == 0 )
        {
            paht = "/";
        }*/

        Config.add("host", host);
		
		if( port.length() != 0 )
		{
			Config.add("port", port);
		}
		
        Config.add("subdir", paht);
        Config.add("ssl", ssl);

        /**
         * SSL Enable
         */
        this.httpcon.setIsSSL(ssl);

		if( port.length() != 0 )
		{
			host = host + ":" + port;
		}
		
        String moved = this.httpcon.isHostMoved(host, "/" + paht);

        if( !moved.equals("") )
        {
            Config.add("egwurl", moved);
            this.egwUrlLinkParser(Config);
        }
    }

	public String[] getEgwLoginDomains(KeyArray Config) throws Exception
    {
		this.egwUrlLinkParser(Config);
		
		String urlhost = Config.getString("host");

		if( Config.getString("port").length() > 0 )
		{
			urlhost = urlhost + ":" + Config.getString("port");
		}
		
        String urllink = "/" + jegwhttp.checkDir(Config.getString("subdir")) +
                EGWHTTP_LOGINSCRIPT + "?" + EGWHTTP_GET_PHPGW_FORWARD + "=";

        String buffer = this.httpcon.openHttpContentSite(urlhost + urllink);

		/**
		 * Hidden Logindomain
		 */
        int begin   = -1;
        int end     = -1;
        String search = "<input type=\"hidden\" name=\"logindomain\" value=\"";

        if( (begin = buffer.indexOf(search)) > -1 )
        {
            end = buffer.indexOf("\"", begin + search.length());

            if( (begin != -1) && (end != -1) )
            {
                String tmp = buffer.substring( begin +
                        search.length(), end);

				return new String[]{tmp};
            }
        }

		/**
		 * Select Logindomain
		 */
		
		begin  = -1;
        end    = -1;
        search = "<select name=\"logindomain\"";
		
		if( (begin = buffer.indexOf(search)) > -1 )
        {
			end = buffer.indexOf("</select>", begin + search.length());
			
			if( (begin != -1) && (end != -1) )
            {
				String tmp = buffer.substring( begin +
                        search.length(), end);
				
				tmp = tmp.trim();
				
				String ltmp[] = tmp.split("</option>");
				String treturn[] = new String[ltmp.length];
				
				for( int i=0; i<ltmp.length; i++ )
				{
					String tbuffer	= ltmp[i];
					String tsearch	= "value=\"";
					
					int tbegin		= -1;
					int tend		= -1;	
					
					if( (tbegin = tbuffer.indexOf(tsearch)) > -1 )
					{
						tend = tbuffer.indexOf("\"", tbegin + tsearch.length());
						
						if( (begin != -1) && (tend != -1) )
						{
							String ttmp = tbuffer.substring( tbegin +
									tsearch.length(), tend);
							
							treturn[i] = ttmp;
						}
					}
				}
				
				return treturn;
			}
		}
		
		return null;
	}
	
    public void egwCheckLoginDomains(KeyArray Config) throws Exception
    {
        String urlhost = Config.getString("host");

		if( Config.getString("port").length() > 0 )
		{
			urlhost = urlhost + ":" + Config.getString("port");
		}
		
        String urllink = "/" + jegwhttp.checkDir(Config.getString("subdir")) +
                EGWHTTP_LOGINSCRIPT + "?" + EGWHTTP_GET_PHPGW_FORWARD + "=";

        String buffer = this.httpcon.openHttpContentSite(urlhost + urllink);

		/**
		 * Hidden Logindomain
		 */
        int begin   = -1;
        int end     = -1;
        String search = "<input type=\"hidden\" name=\"logindomain\" value=\"";

        if( (begin = buffer.indexOf(search)) > -1 )
        {
            end = buffer.indexOf("\"", begin + search.length());

            if( (begin != -1) && (end != -1) )
            {
                String tmp = buffer.substring( begin +
                        search.length(), end);

				Config.add("logindomain", tmp);
				
                return;
            }
        }

		/**
		 * Select Logindomain
		 */
		
		begin  = -1;
        end    = -1;
        search = "<select name=\"logindomain\"";
		
		if( (begin = buffer.indexOf(search)) > -1 )
        {
			end = buffer.indexOf("</select>", begin + search.length());
			
			if( (begin != -1) && (end != -1) )
            {
				String tmp = buffer.substring( begin +
                        search.length(), end);
				
				System.out.println(tmp);
			}
		}
		
        //Config.Add("logindomain", EGWHTTP_POST_LOGINDOMAIN_DEFAULT);
		Config.add("logindomain", urlhost);
    }

    /**
     * egwLogin
     * logt sich im eGroupware ein mit den Benutzerdaten aus einer Config
     * und gibt den Cookieinhalt zurück
     *
     * @param Config Konfigurations Einstellungen
     * @return Cookieinhalt
     */
    public KeyArray egwLogin(KeyArray Config) throws Exception
    {
        this.egwUrlLinkParser(Config);
        //this.egwCheckLoginDomains(Config);
        
        String urlhost = Config.getString("host");

		if( Config.getString("port").length() > 0 )
		{
			urlhost = urlhost + ":" + Config.getString("port");
		}
		
        String urllink = "/" + jegwhttp.checkDir(Config.getString("subdir")) +
                EGWHTTP_LOGINSCRIPT + "?" + EGWHTTP_GET_PHPGW_FORWARD + "=";

        String urlpost = EGWHTTP_POST_VAR_PASSWORD_TYPE + "=" +
                EGWHTTP_POST_PASSWORD_TYPE_TEXT + "&" + EGWHTTP_POST_VAR_ACCOUNT_TYPE +
                "=" + EGWHTTP_POST_ACCOUNT_TYPE_U + "&" + EGWHTTP_POST_VAR_LOGINDOMAIN +
                "=" + Config.getString("logindomain") + "&" + EGWHTTP_POST_VAR_LOGIN +
                "=" + Config.getString("user") + "&" + EGWHTTP_POST_VAR_PASSWD + "=" +
                Config.getString("password") + "&" + EGWHTTP_POST_VAR_SUBMITIT + "=" +
                EGWHTTP_POST_SUBMITIT;

        String buffer = this.httpcon.openHttpContentSitePost(urlhost + urllink, urlpost);

        if( buffer.length() == 0 )
        {
            // Verbindungsfehler
            throw new Exception("NETERROR");
        }

        if( this.httpcon.isNotFound() )
        {
             throw new Exception("PAGENOTFOUND");
        }

        int status = this.egwCheckLoginStatus();

        if( status > -1 )
        {
            throw new Exception("LOGIN:" + Integer.toString(status));
        }

        KeyArray egwcookie = new KeyArray(EGWHTTP_EGW_COOKIE);
        String[] keys = egwcookie.getKeys();

        for( int i=0; i<keys.length; i++ )
        {
            String value = this.httpcon.getSocketHeaderField(this.httpcon.getCookie(), keys[i]);

            if( value.length() == 0 )
            {
                // Login fehlgeschlagen
                return null;
            }

            egwcookie.add(keys[i], value);
        }

        this.loginstatus = true;
        return egwcookie;
    }

    /**
     * egwIsLogin
     * Überprüft ob eine egw Benutzer noch angemeldet ist
     *
     * @param buffer Rückgabe eines HTTP aufrufes muss hier angeben werden,
     * der Inhalt würd dann drauf überprüft
     * @param cookie Cookie Informationen zum Überprüfen
     * @return true = noch Angemeldet, false = nicht mehr Angemeldet
     */
    private boolean egwIsLogin(KeyArray cookie)
    {
        String sess = this.httpcon.getCookieVariable(this.httpcon.getCookie(), "sessionid");
        String location = this.httpcon.getLocation();

        if( sess.length() > 0 )
        {
            if( sess.compareTo(cookie.getString("sessionid")) != 0 )
            {
                this.loginstatus = false;
                return false;
            }
        }
        else if( (location != null) && (location.indexOf("login.php") != -1) )
        {
            this.loginstatus = false;
            return false;
        }

        this.loginstatus = true;
        return true;
    }

    private int egwCheckLoginStatus()
    {
		String back = "";
        String buffer = this.httpcon.getLocation();

        if( buffer == null )
        {
            buffer = "";
        }

        int pos = buffer.indexOf(jegwhttp.EGWHTTP_LOGINLINK);
        int end = buffer.length();

        if( (pos != -1) && (end != -1) )
        {
            back = buffer.substring( pos +
                    jegwhttp.EGWHTTP_LOGINLINK.length(), end);
        }
        else if( (buffer.indexOf("http://") != -1) || (buffer.indexOf("https://") != -1) )
        {
            if( buffer.indexOf("index.php?cd=yes") == -1 )
            {
                return 999;
            }
            else
            {
                return -1;
            }
        }
        else
        {
            return -1;
        }
        
        return Integer.valueOf(back).intValue();
    }

    public boolean egwIsEGWLogin()
    {
        return this.loginstatus;
    }

    /**
     * egwGetTagValue
     * Sucht Inhalte erraus (nach der notation vom html/xml)
     *
     * @param buff HTTP Inhalt
     * @param tag Inhalt umschließer
     * @return Inhalt
     */
    private String egwGetTagValue(String buff, String tag)
    {
        String back = "";

        int pos = buff.indexOf("<" + tag + ">");
        int end = buff.indexOf("</" + tag + ">", pos);

        if( (pos != -1) && (end != -1) )
        {
            back = buff.substring( pos + tag.length() +2, end);
        }

        return back;
    }

    private String egwGetTagValueWithTag(String buff, String tag)
    {
        String back = "";

        int pos = buff.indexOf("<" + tag + ">");
        int end = buff.indexOf("</" + tag + ">", pos);

        if( (pos != -1) && (end != -1) )
        {
            back = buff.substring( pos, end + tag.length() +3);
        }

        return back;
    }

    private String egwGetNoneTagText(String buff, String tag)
    {
        String back = "";

        int pos = buff.indexOf("<" + tag + ">");
        int end = buff.indexOf("</" + tag + ">", pos);

        if( (pos != -1) && (end != -1) )
        {
            back = buff.substring( 0, pos);
            back = back + buff.substring( end + tag.length() +3, buff.length()-1);
        }

        return back;
    }

    /**
     * egwGetEGWCookieStr
     * erstellt den Cookie String aus dem cookie(Array)
     *
     * @param cookie
     * @return
     */
    private String egwGetEGWCookieStr(KeyArray cookie)
    {
        String cookiestr = "";

        String[] keys = cookie.getKeys();

        for( int i=0; i<cookie.size(); i++ )
        {
            String tmp = keys[i] + "=" + cookie.getString(keys[i]) + ";";

            if(cookiestr.length() == 0)
            {
                cookiestr = cookiestr + tmp;
            }
            else
            {
                cookiestr = cookiestr + " " + tmp;
            }
        }

        cookiestr = cookiestr + " storedlang=de; last_loginid=; last_domain=default; ConfigLang=de";

        return cookiestr;
    }
	
    public ArrayList egwLoadEGWData(KeyArray Config, KeyArray cookie) throws Exception
    {
        ArrayList msglist = new ArrayList();
        String urlhost = Config.getString("host");

		if( Config.getString("port").length() > 0 )
		{
			urlhost = urlhost + ":" + Config.getString("port");
		}
		
        String urllink = "/" + jegwhttp.checkDir(Config.getString("subdir")) +
                EGWHTTP_GET_NOTIFICATIONS_ACTION;

		this.httpcon.setIsAjax(true);
        this.httpcon.setCookie(this.egwGetEGWCookieStr(cookie));
        //String buffer = this.httpcon.openHttpContentSite(urlhost + urllink);
        String buffer = this.httpcon.openHttpContentSitePost(
			urlhost + urllink, 
			"json_data={\"request\":{\"parameters\":[null]}}"
			);
        
		this.httpcon.setIsAjax(false);
		
        /**
         * Fehler Behandlung
         */
        /*if( buffer.length() == 0 )
        {
            // Verbindungsfehler
            throw new Exception("NETERROR");
        }*/

        if( this.egwIsLogin(cookie) )
        {
            int status = this.egwCheckLoginStatus();

            if( status > -1 )
            {
                throw new Exception("LOGIN:" + Integer.toString(status));
            }


            /**
             * Check auf Rechte (Permission denied!)
             */
            String permission = "<title>Permission denied!</title>";

            if( buffer.indexOf(permission) > -1 )
            {
                throw new Exception("PERMISSIONDENIED");
            }

			
			/**
			 * JSON
			 */
			
			JSONParser parser = new JSONParser();
			ContainerFactory containerFactory = new ContainerFactory(){
					public List creatArrayContainer() {
						return new LinkedList();
					}

					public Map createObjectContainer() {
						return new LinkedHashMap();
					}
				};
			
			try 
			{
				Map json = (Map)parser.parse(buffer.trim(), containerFactory);
				Iterator iter = json.entrySet().iterator();
				
				while( iter.hasNext() )
				{
					Map.Entry entry = (Map.Entry)iter.next();
					
					if( entry.getKey().toString().compareTo("response") == 0 )
					{
						LinkedList response =  (LinkedList) entry.getValue();
						
						for( Integer i=0; i<response.size(); i++ )
						{
							Map jmsg = (Map) response.get(i);
							Iterator jmsgiter = jmsg.entrySet().iterator();
							
							while( jmsgiter.hasNext() )
							{
								Map.Entry jmsgentry = (Map.Entry)jmsgiter.next();
								
								if( (jmsgentry.getKey().toString().compareTo("type") == 0) &&
									(jmsgentry.getValue().toString().compareTo("data") == 0) && 
									jmsgiter.hasNext() )
								{
									jmsgentry = (Map.Entry)jmsgiter.next();

									if( jmsgentry.getKey().toString().compareTo("data") == 0 )
									{
										KeyArray notifymsg = new KeyArray(EGWHTTP_EGW_NOTIFY);
										
										Map msgdata = (Map) jmsgentry.getValue();
										Iterator dataiter = msgdata.entrySet().iterator();
										
										while( dataiter.hasNext() )
										{
											Map.Entry dataentry = (Map.Entry)dataiter.next();
											String tkey = dataentry.getKey().toString();
											Object tovalue = dataentry.getValue(); 
											
											String tvalue = "";
											
											if( tovalue != null )
											{
												tvalue = tovalue.toString();
											}
											
											if( notifymsg.existKey(tkey) )
											{
												notifymsg.add(tkey, tvalue);
											}
										}
										
										if( notifymsg.get("notify_id") != null )
										{
											msglist.add(notifymsg);
											
											this.egwRemoveEGWData(
												Config, cookie, notifymsg.getString("notify_id"));
										}
									}
								}
							}
						}
					}
				}
			}
			catch( ParseException pe )
			{
				throw new Exception("NOAPPS");
			}
        }

        return msglist;
    }

	public Boolean egwRemoveEGWData(KeyArray Config, KeyArray cookie, String notifiy_id) throws Exception
    {
		String urlhost = Config.getString("host");

		if( Config.getString("port").length() > 0 )
		{
			urlhost = urlhost + ":" + Config.getString("port");
		}
		
        String urllink = "/" + jegwhttp.checkDir(Config.getString("subdir")) +
                EGWHTTP_GET_NOTIFICATIONS_ACTION_CONFIRM;

		this.httpcon.setIsAjax(true);
        this.httpcon.setCookie(this.egwGetEGWCookieStr(cookie));
        //String buffer = this.httpcon.openHttpContentSite(urlhost + urllink);
        String buffer = this.httpcon.openHttpContentSitePost(
			urlhost + urllink, 
			"json_data={\"request\":{\"parameters\":[\"" + notifiy_id + "\"]}}"
			);
        
		this.httpcon.setIsAjax(false);
		
		return true;
	}
	
    /**
     * egwGetSecurityID
     * beantragt eine Sicherheitsid zum Einlogen
     *
     * @param Config Konfiguration eines Accounts
     * @param cookie Cookie vom einlogen
     * @return Sicherheitsid, war man nicht eingelogt so ist der String Leer
     */
    public String egwGetSecurityID(KeyArray Config, KeyArray cookie) throws Exception
    {
        String urlhost = Config.getString("host");

		if( Config.getString("port").length() > 0 )
		{
			urlhost = urlhost + ":" + Config.getString("port");
		}
		
        String urllink = "/" + jegwhttp.checkDir(Config.getString("subdir")) +
                EGWHTTP_TRAYMODUL + "?" + EGWHTTP_GET_VAR_MAKE_SECURITYID +
                "=" + EGWHTTP_GET_MAKE_SECURITYID;

        String securityid = "";

        this.httpcon.setCookie(this.egwGetEGWCookieStr(cookie));
        String buffer = this.httpcon.openHttpContentSite(urlhost + urllink);

        if( this.egwIsLogin(cookie) )
        {
           securityid = this.egwGetTagValue(buffer, EGWHTTP_TAG_SECURITYID);
        }

        return securityid;
    }

    public String egwGetOpenEGWLink(KeyArray Config, KeyArray cookie, String menuaction)
    {
        String urllink		= "";
        String urlhost		= Config.getString("host");
		String protocol		= "http://";
        
		if( Config.getString("egwurl").startsWith("https") )
		{
			protocol = "https://";
		}
		
		if( Config.getString("port").length() > 0 )
		{
			urlhost = urlhost + ":" + Config.getString("port");
		}
		
        urllink = urllink + protocol + jegwhttp.checkDir(urlhost) +
                jegwhttp.checkDir(Config.getString("subdir")) + EGWHTTP_INDEXSCRIPT;

        urllink = urllink + "?notifiy=1";

        if( (menuaction != null) && (menuaction.length() > 0) )
        {
            urllink = urllink + "&" + menuaction;
        }

        String[] keys = cookie.getKeys();

        for( int i=0; i<cookie.size(); i++ )
        {
            urllink = urllink + "&" + keys[i] + "=" + cookie.getString(keys[i]);
        }
		
        return urllink;
    }

    public boolean egwLogout(KeyArray Config, KeyArray cookie) throws Exception
    {
        String urlhost = Config.getString("host");

		if( Config.getString("port").length() > 0 )
		{
			urlhost = urlhost + ":" + Config.getString("port");
		}
		
        String urllink = jegwhttp.checkDir(urlhost) +
                jegwhttp.checkDir(Config.getString("subdir")) +
                EGWHTTP_LOGOUTSCRIPT;

        this.httpcon.setCookie(this.egwGetEGWCookieStr(cookie));
        String buffer = this.httpcon.openHttpContentSite(urllink);

        
        if( buffer.length() == 0 )
        {
            // Verbindungsfehler
            throw new Exception("NETERROR");
        }

        if( !this.egwIsLogin(cookie) )
        {
            return true;
        }

        return false;
    }
}
