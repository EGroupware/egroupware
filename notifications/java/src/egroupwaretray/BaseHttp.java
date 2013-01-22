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

import java.io.*;
import java.net.HttpURLConnection;
import java.net.URL;
import java.util.List;
import java.util.Map;
import java.util.logging.Level;
import java.util.logging.Logger;
import javax.net.ssl.HttpsURLConnection;
import javax.net.ssl.SSLContext;

/**
 * BaseHttp
 * 
 * @author Stefan Werfling <stefan.werfling@hw-softwareentwicklung.de>
 */
public class BaseHttp
{
    private HttpURLConnection _con  = null;
    private boolean _isSSL          = false;
    private boolean _isAjax         = false;
    private String _cookie          = "";
	private int _sockettimeout		= 10000;
	
	static private BaseHttpsTrustManager _bhtm = null;
	
	static public BaseHttpsTrustManager getTrustManager()
	{
		return BaseHttp._bhtm;
	}
	
	/**
	 * setIsSSL
	 * 
	 * @param enable boolean
	 */
    public void setIsSSL(boolean enable)
    {
        this._isSSL = enable;
    }
	
	/**
	 * setIsAjax
	 * setzt das Request als Ajax
	 * 
	 * @param enable boolean
	 */
	public void setIsAjax(boolean enable)
	{
		this._isAjax = enable;
	}
	
	/**
	 * setSocketTimeOut
	 * set den Socket connection timeout
	 * 
	 * @param timeout int 
	 */
	public void setSocketTimeOut(int timeout)
	{
		this._sockettimeout = timeout;
	}
	
	/**
	 * openHttpSite
	 * open/load HTTP Site
	 * 
	 * @param URL
	 * @return String
	 * @throws IOException 
	 */
    public String openHttpSite(String URL) throws IOException
    {
        URL jtUrl = new URL(URL);
        this._con = (HttpURLConnection) jtUrl.openConnection();
		this._con.setConnectTimeout(this._sockettimeout);
		
        DataInputStream dis = new DataInputStream(this._con.getInputStream());
        String inputLine;
        String buff = "";

        while ((inputLine = dis.readLine()) != null)
        {
            buff = buff + "\r\n" + inputLine;
        }

        dis.close();

        /**
         * Schließen
         */
        this._con.disconnect();

        return buff;
    }

	/**
	 * openHttpContentSitePost
	 * open/load Site content (POST)
	 * 
	 * @param URL
	 * @param post String
	 * @return String
	 * @throws Exception 
	 */
    public String openHttpContentSitePost(String URL, String post) throws Exception
    {
        if( this._isSSL )
        {
            URL = "https://" + URL;
        }
        else
        {
            URL = "http://" + URL;
        }

        HttpURLConnection.setFollowRedirects(false);

        URL jtUrl = new URL(URL);
		
		if( this._isSSL )
        {
			SSLContext sslContext = SSLContext.getInstance("SSL");

			if( BaseHttp._bhtm == null )
			{
				BaseHttp._bhtm = new BaseHttpsTrustManager();
			}
			
			sslContext.init(
				null, 
				new javax.net.ssl.TrustManager[] { BaseHttp._bhtm }, 
				new java.security.SecureRandom()
				);
			
			HttpsURLConnection.setDefaultSSLSocketFactory(sslContext.getSocketFactory());
		}
        
		this._con = (HttpURLConnection) jtUrl.openConnection();
		this._con.setConnectTimeout(this._sockettimeout);
		
        if( !this._cookie.equals("") )
        {
            this._con.addRequestProperty("Cookie", this._cookie);
        }

		if( this._isAjax )
		{
			this._con.addRequestProperty("X-Requested-With", "XMLHttpRequest");
		}
		
        this._con.setDoOutput(true);

        OutputStreamWriter wr = new OutputStreamWriter(this._con.getOutputStream());
        wr.write(post);
        wr.flush();
        wr.close();

        if( this._con != null )
        {
            Map<String, List<String>> headers = this._con.getHeaderFields();
            List<String> values = headers.get("Set-Cookie");

            this._cookie = "";

            if( values != null )
            {
                for(int i=0; i<values.size(); i++)
                {
                    //this._con.setRequestProperty("Cookie", values.get(i));
                    if( !this._cookie.equals("") )
                    {
                        this._cookie += ";";
                    }

                    this._cookie += values.get(i);
                }
            }
        }

        String buff = "";

        try
        {
            BufferedReader bufferedReader = new BufferedReader(
				new InputStreamReader(this._con.getInputStream()));
			
			String line = bufferedReader.readLine();
			
			while( line != null )
			{
				buff = buff + line + "\r\n";
				
				line = bufferedReader.readLine();
			}
			
			bufferedReader.close();
            
            /**
             * close
             */
            this._con.disconnect();
        }
        catch( Exception exp )
        {
			egwDebuging.log.log(Level.SEVERE, null, exp);
            throw new Exception("NETERROR");
        }

        return buff;
    }

	/**
	 * openHttpContentSite
	 * open load Site content (GET)
	 * 
	 * @param URL
	 * @return
	 * @throws Exception 
	 */
    public String openHttpContentSite(String URL) throws Exception
    {
        if( this._isSSL )
        {
            URL = "https://" + URL;
        }
        else
        {
            URL = "http://" + URL;
        }

        HttpURLConnection.setFollowRedirects(false);
        
        URL jtUrl = new URL(URL);
		
		if( this._isSSL )
        {
			SSLContext sslContext = SSLContext.getInstance("SSL");

			if( BaseHttp._bhtm == null )
			{
				BaseHttp._bhtm = new BaseHttpsTrustManager();
			}
			
			sslContext.init(
				null, 
				new javax.net.ssl.TrustManager[] { BaseHttp._bhtm }, 
				new java.security.SecureRandom()
				);
			
			HttpsURLConnection.setDefaultSSLSocketFactory(sslContext.getSocketFactory());
		}
		
        this._con = (HttpURLConnection) jtUrl.openConnection();
		this._con.setConnectTimeout(this._sockettimeout);
		
        if( !this._cookie.equals("") )
        {
            this._con.addRequestProperty("Cookie", this._cookie);
        }

		if( this._isAjax )
		{
			this._con.addRequestProperty("X-Requested-With", "XMLHttpRequest");
		}
		
        if( this._con != null )
        {
            Map<String, List<String>> headers = this._con.getHeaderFields();
            List<String> values = headers.get("Set-Cookie");

            this._cookie = "";

            if( values != null )
            {
                for(int i=0; i<values.size(); i++)
                {
                    //this._con.setRequestProperty("Cookie", values.get(i));
                    if( !this._cookie.equals("") )
                    {
                        this._cookie += ";";
                    }

                    this._cookie += values.get(i);
                }
            }
        }

        String buff = "";

        try
        {
			BufferedReader bufferedReader = new BufferedReader(
				new InputStreamReader(this._con.getInputStream()));
			
			String line = bufferedReader.readLine();
			
			while( line != null )
			{
				buff = buff + line + "\r\n";
				
				line = bufferedReader.readLine();
			}
			
			bufferedReader.close();

            /**
             * close
             */
            this._con.disconnect();
        }
        catch(Exception exp)
        {
			egwDebuging.log.log(Level.SEVERE, null, exp);
            throw new Exception("NETERROR");
        }


        return buff;
    }

	/**
	 * getHeaderField
	 * get HTTP Header by name
	 * 
	 * @param name String
	 * @return String
	 */
    public String getHeaderField(String name)
    {
        Map<String, List<String>> headers = this._con.getHeaderFields();
        List<String> values = headers.get("Set-Cookie");

        String v = "";

        for(int i=0; i<values.size(); i++)
        {
            v = v + values.get(i);
        }
        
        int pos = v.indexOf(name + "=");
        int end = v.indexOf(";", pos);

        String back = v.substring( pos + name.length()+1, end);

        return back;
    }

	/**
	 * getLinkVariable
	 * 
	 * 
	 * @param content
	 * @param field
	 * @return 
	 */
    public String getLinkVariable(String content, String field)
    {
        String back = "";

        int pos = content.indexOf(field + "=");

        if(pos > -1)
        {
            int end = content.indexOf("&", pos);
            int end2 = content.indexOf("\r\n", pos);

            if( (end == -1) || (end2 < end))
            {
                end = end2;
            }

            back = content.substring( pos + field.length() +1, end);
        }
        
        return back;
    }

	/**
	 * getCookieVariable
	 * 
	 * 
	 * @param content
	 * @param field
	 * @return 
	 */
    public String getCookieVariable(String content, String field)
    {
        String back = "";

        int pos = content.indexOf(field + "=");

        if(pos > -1)
        {
            int end = content.indexOf(";", pos);
            int end2 = content.indexOf("\r\n", pos);

            if( (end == -1) || ( ( end2 != -1 ) && (end2 < end) ))
            {
                end = end2;
            }

            if( end == -1 )
            {
                end = content.length();
            }

            back = content.substring( pos + field.length() +1, end);
        }
        
        return back;
    }

	/**
	 * getSocketHeaderField
	 * 
	 * @param content
	 * @param field
	 * @return 
	 */
    public String getSocketHeaderField(String content, String field)
    {
        String back = "";

        int pos = content.indexOf(field + "=");

        if(pos > -1)
        {
            int end = content.indexOf(";", pos);
            //int end2 = content.indexOf("\r\n", pos);
            int end2 = content.length();

            if( (end == -1) || (end2 < end))
            {
                end = end2;
            }

            back = content.substring( pos + field.length() +1, end);
        }
        
        return back;
    }

	/**
	 * isHostMoved
	 * is server send host moved
	 * 
	 * @param host 
	 * @param path
	 * @return
	 * @throws Exception 
	 */
    public String isHostMoved(String host, String path) throws Exception
    {
        if( path == null )
        {
            path = "/";
        }

        String back = "";
       
        String buffer = this.openHttpContentSite(host + path);

        HttpURLConnection tmp = (HttpURLConnection) this._con;
        int httpcode = tmp.getResponseCode();

        if( httpcode == HttpURLConnection.HTTP_MOVED_PERM )
        {
            back = tmp.getHeaderField("Location");
        }
        
        return back;
    }

	/**
	 * isNotFound
	 * server send site not found
	 * 
	 * @return boolean
	 */
    public boolean isNotFound()
    {
        try 
        {
            if( this._con.getResponseCode() == HttpURLConnection.HTTP_NOT_FOUND )
            {
                return true;
            }
        }
        catch( IOException ex)
        {
			egwDebuging.log.log(Level.SEVERE, null, ex);
        }

        return false;
    }

	/**
	 * getLocation
	 * server send location (move)
	 * 
	 * @return String
	 */
    public String getLocation()
    {
        return this._con.getHeaderField("Location");
    }

	/**
	 * getCookie
	 * get cookie in String
	 * 
	 * @return String
	 */
    public String getCookie()
    {
        return this._cookie;
    }

	/**
	 * setCookie
	 * set cookie
	 * 
	 * @param cookie String
	 */
    public void setCookie(String cookie)
    {
        this._cookie = cookie;
    }
}
