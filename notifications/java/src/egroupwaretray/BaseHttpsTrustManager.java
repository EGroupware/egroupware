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
import java.io.IOException;
import java.io.InputStream;
import java.security.Certificate;
import java.security.KeyStore;
import java.security.cert.CertificateEncodingException;
import java.security.cert.CertificateException;
import java.security.cert.CertificateFactory;
import java.security.cert.X509Certificate;
import java.util.ArrayList;
import javax.net.ssl.TrustManager;
import javax.net.ssl.TrustManagerFactory;
import javax.net.ssl.X509TrustManager;
import sun.misc.BASE64Encoder;
import sun.security.provider.X509Factory;

/**
 * BaseHttpsTrustManager
 * 
 * @author Stefan Werfling <stefan.werfling@hw-softwareentwicklung.de>
 */
public class BaseHttpsTrustManager implements javax.net.ssl.X509TrustManager
{
	private ArrayList<X509Certificate> acceptcerts = new ArrayList<X509Certificate>();
	
	public void checkClientTrusted(X509Certificate[] xcs, String string) throws CertificateException {
	}

	public void checkServerTrusted(X509Certificate[] xcs, String string) throws CertificateException {
		
		for( int i=0; i<xcs.length; i++ )
		{
			X509Certificate cs = xcs[i];
			
			if( this.acceptcerts.indexOf(cs) != -1 )
			{
				return;
			}
			
			try
			{
				cs.checkValidity();
			
				TrustManagerFactory tmf = TrustManagerFactory.getInstance(
					TrustManagerFactory.getDefaultAlgorithm());
				
				tmf.init((KeyStore)null);
				
				TrustManager[] tms = tmf.getTrustManagers();
				
				if( tms.length > 0 )
				{
					X509TrustManager x509TrustManager = (X509TrustManager) tms[0];
					x509TrustManager.checkServerTrusted(xcs, string);
				}
			}
			catch(Exception exp)
			{
				String certinfo = 
					jegwConst.getConstTag("egw_txt_tm_certinfo") + 
					"\r\n" + jegwConst.getConstTag("egw_txt_tm_issuer_dn") +
					" " + cs.getIssuerDN().toString() + "\r\n" +
					jegwConst.getConstTag("egw_txt_tm_subject_dn") +
					" " + cs.getSubjectDN().toString() + "\r\n";
				
				String info = jegwConst.getConstTag("egw_msg_tm_certerror") + 
					"\r\n" + certinfo +
					jegwConst.getConstTag("egw_msg_tm_connected");
				
				if( jegwMain.confirmDialog(info, 
					jegwConst.getConstTag("egw_msg_tm_title_errorssl")) != 0 )
				{
					throw new CertificateException(exp.getMessage());
				}
				
				this.acceptcerts.add(cs);
			}
		}
	}

	public X509Certificate[] getAcceptedIssuers() {
		
		return new java.security.cert.X509Certificate[] {};
	}
	
	/**
	 * getAcceptedCerts
	 * return all accepted Certs 
	 * 
	 * @return String Certs in PEM
	 * @throws CertificateEncodingException 
	 */
	public String getAcceptedCerts() throws CertificateEncodingException
	{
		String certs = "";
		
		for( int i=0; i<this.acceptcerts.size(); i++ )
		{
			X509Certificate cert = this.acceptcerts.get(i);
			
			BASE64Encoder encoder = new BASE64Encoder();
			
			certs += X509Factory.BEGIN_CERT;
			certs += encoder.encodeBuffer(cert.getEncoded());
			certs += X509Factory.END_CERT;
			certs += "\r\n\r\n";
		}
		
		return certs;
	}
	
	public void setAcceptedCerts(String strcerts) throws CertificateException
	{
		String[] tmp = strcerts.split("\r\n\r\n");
		
		for( int i=0; i<tmp.length; i++ )
		{
			CertificateFactory cf = CertificateFactory.getInstance("X.509");
			InputStream is = new ByteArrayInputStream(tmp[i].getBytes());
		
			X509Certificate cert = (X509Certificate) cf.generateCertificate(is);
			
			this.acceptcerts.add(cert);
		}
	}
}