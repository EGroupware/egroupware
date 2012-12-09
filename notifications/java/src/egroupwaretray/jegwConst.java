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

import java.io.InputStream;
import java.net.URL;
import java.util.logging.Level;
import java.util.logging.Logger;
import javax.swing.JOptionPane;
import javax.xml.stream.XMLEventReader;
import javax.xml.stream.events.XMLEvent;
import sun.misc.Launcher;

/**
 * jegwConst
 * 
 * @author Stefan Werfling <stefan.werfling@hw-softwareentwicklung.de>
 */
public class jegwConst extends hwxml
{
    private String tag      = "";
    private String value    = "";
    
	public jegwConst(InputStream xmlfile, String tag) throws Exception
    {
        super(xmlfile);
        this.tag = tag;
    }
	
	public jegwConst(URL xmlfile, String tag) throws Exception
    {
        super(xmlfile);
        this.tag = tag;
    }
	
    public jegwConst(String xmlfile, String tag) throws Exception
    {
        super(xmlfile);
        this.tag = tag;
    }

    public String getValue()
    {
        return this.value;
    }

    @Override protected boolean mRead(XMLEvent event, XMLEventReader eventReader)
    {
        if(this.value.length() > 0)
        {
            return false;
        }
        
        if(event.isStartElement())
        {
            String stag = event.asStartElement().getName().getLocalPart();

            try
            {
                event = eventReader.nextEvent();

                if( (!event.isEndElement()) && (!event.isStartElement()) )
                {
                    if( this.tag.compareTo(stag) == 0 )
                    {
                        String svalue = event.asCharacters().getData();
                        this.value = svalue;
                        return true;
                    }
                }
            }
            catch(Exception exp)
            {
                Logger.getLogger(jegwConst.class.getName()).log(Level.SEVERE, null, exp);
            }
        }

        return false;
    }

    static public String getConstTag(String tag)
    {
        String re = "";

        try
        {
			InputStream iConst =
				ClassLoader.getSystemClassLoader().getResourceAsStream(
					"lib/conf/egwnotifier.const.xml"
				);
			
            jegwConst sconst = new jegwConst(iConst, tag);
            sconst.read(true);
            re = sconst.getValue();
        }
        catch (Exception ex)
        {
            Logger.getLogger(jegwConst.class.getName()).log(Level.SEVERE, null, ex);
        }

        return re;
    }

    static public String getEGWStatus(String code)
    {
        String tag = "egw_sc_" + code;

        return jegwConst.getConstTag(tag);
    }
}
