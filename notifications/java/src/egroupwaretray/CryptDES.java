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

import java.io.ByteArrayOutputStream;
import java.io.InputStream;
import java.io.OutputStream;
import java.security.Key;
import javax.crypto.*;
import javax.crypto.spec.SecretKeySpec;

/**
 * CryptDES
 * 
 * @author Stefan Werfling <stefan.werfling@hw-softwareentwicklung.de>
 */
public class CryptDES
{
    public void encode( byte[] bytes, OutputStream out, String pass ) throws Exception
    {
        Cipher c = Cipher.getInstance( "DES" );
        Key k = new SecretKeySpec( pass.getBytes(), "DES" );
        c.init( Cipher.ENCRYPT_MODE, k );
        OutputStream cos = new CipherOutputStream( out, c );
        cos.write( bytes );
        cos.close();
    }

    public byte[] decode( InputStream is, String pass ) throws Exception
    {
        Cipher c = Cipher.getInstance( "DES" );
        Key k = new SecretKeySpec( pass.getBytes(), "DES" );
        c.init( Cipher.DECRYPT_MODE, k );

        ByteArrayOutputStream bos = new ByteArrayOutputStream();
        CipherInputStream cis = new CipherInputStream( is, c );

        for(int b; (b = cis.read()) != -1; )
        {
            bos.write( b );
        }

        cis.close();
        return bos.toByteArray();
    }
}
