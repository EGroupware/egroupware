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
import java.util.Date;
import java.util.logging.FileHandler;
import java.util.logging.Handler;
import java.util.logging.Level;
import java.util.logging.Logger;

/**
 * egwDebuging
 * 
 * @author Stefan Werfling <stefan.werfling@hw-softwareentwicklung.de>
 */
public class egwDebuging {
	
	public static final Logger log	= Logger.getLogger( egwDebuging.class.getName() );
	public static Handler handler	= null;
	
	/**
	 * setLevel
	 * set Logging Level
	 * 
	 * @param uselevel 
	 */
	public static void setLevel(Level uselevel)
	{
		egwDebuging.log.setLevel(uselevel);
	}
	
	/**
	 * setDebuging
	 * enable/disable Debuging
	 * 
	 * @param enable 
	 */
	public static void setDebuging(Boolean enable)
	{
		if( enable && (handler == null) )
		{
			long now = System.currentTimeMillis();
			
			try
			{
				egwDebuging.handler = new FileHandler( 
					String.format("egroupwarenotifier_log_%d.txt", now) );
				
				egwDebuging.log.addHandler(egwDebuging.handler);
			}
			catch( IOException ex )
			{
				Logger.getLogger(egwDebuging.class.getName()).log(
					Level.SEVERE, null, ex);
			}
			catch (SecurityException ex)
			{
				Logger.getLogger(egwDebuging.class.getName()).log(
					Level.SEVERE, null, ex);
			}
		}
		else if( !enable && (handler != null) )
		{
			egwDebuging.log.removeHandler(egwDebuging.handler);
			egwDebuging.handler = null;
		}
	}
	
	/**
	 * 
	 * @param err 
	 */
	protected static void dumpUncaughtError(Throwable err)
    {
		err.printStackTrace(System.err);
		
		try
        {
        	long now = System.currentTimeMillis();
        	
            String report = String.format("%s\n%s\n%s\n", 
                    new Date(now),
                    err.getLocalizedMessage(),
                    egwDebuging.stackTraceToStr(err));
            
            egwDebuging.writeFile(
            		new File(System.getProperty("java.io.tmpdir"),	 		
            				 String.format("egroupwarenotifier_uncaught_%d.txt", now)),	
                    report.getBytes());
        }
        catch (Throwable ignored)
        {
        }
	}
	
	public static String stackTraceToStr(Throwable err)
    {
    	StringWriter result = new StringWriter();
    	PrintWriter pwr		= new PrintWriter(result);
		
    	err.printStackTrace(pwr);
		
    	pwr.flush();
    	pwr.close();
		
    	return result.toString();
    }
	
	public static void writeFile(File fl, byte[] data) throws IOException
	{
        FileOutputStream fos = new FileOutputStream(fl);
		
        try
        {
            fos.write(data);
        }
        finally
        {
            fos.close();
        }
	}
}