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

import java.io.ByteArrayInputStream;
import java.io.ByteArrayOutputStream;
import java.io.InputStream;
import java.net.InetAddress;
import java.net.NetworkInterface;
import java.net.UnknownHostException;
import java.util.logging.Level;
import java.util.logging.Logger;

/**
 * egwPasswordCrypt
 * 
 * @author Stefan Werfling <stefan.werfling@hw-softwareentwicklung.de>
 */
public class egwPasswordCrypt
{
	private String getMacAddress()
	{
		try
		{
			InetAddress ip = InetAddress.getLocalHost();
			
			NetworkInterface network = NetworkInterface.getByInetAddress(ip);
			
			byte[] mac = network.getHardwareAddress();
			
			StringBuilder sb = new StringBuilder();
			
			for( int i=0; i<mac.length; i++ )
			{
				sb.append(String.format("%02X%s", 
					mac[i], (i < mac.length - 1) ? "-" : ""));		
			}
			
			return sb.toString();
		}
		catch( Exception ex )
		{
			Logger.getLogger(egwPasswordCrypt.class.getName()).log(Level.SEVERE, null, ex);
		}
		
 
		return "00-00-00-00-00-00";
	}
	
    private String sysKey()
    {
        String systemkey = "";

        systemkey += System.getProperty("os.name");
        systemkey += System.getProperty("os.version");
        systemkey += System.getProperty("os.arch");
        systemkey += System.getProperty("user.name");
        systemkey += System.getProperty("user.home");
        systemkey += this.getMacAddress();

        return HexString.getMD5Hash(systemkey);
    }
    
    public String encode(String password) throws Exception
    {
        String systemkey = this.sysKey();
        ByteArrayOutputStream out = new ByteArrayOutputStream();

        CryptDES des = new CryptDES();
        des.encode(password.getBytes(), out, systemkey.substring(0, 8));

        String back = HexString.byteArrToHexString(out.toByteArray());

        return back;
    }

    public String decode(String password) throws Exception
    {
        String systemkey = this.sysKey();
        byte[] decode = HexString.hexStringToByteArray(password);

        CryptDES des = new CryptDES();
        InputStream is = new ByteArrayInputStream(decode);
        String back = new String(des.decode(is, systemkey.substring(0, 8)));

        return back;
    }
}
