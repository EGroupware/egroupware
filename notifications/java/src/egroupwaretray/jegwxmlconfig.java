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

import java.util.ArrayList;
import java.util.logging.Level;
import java.util.logging.Logger;
import javax.xml.stream.XMLEventFactory;
import javax.xml.stream.XMLEventReader;
import javax.xml.stream.XMLEventWriter;
import javax.xml.stream.XMLStreamException;
import javax.xml.stream.events.XMLEvent;

/**
 * jegwxmlconfig
 * 
 * @author Stefan Werfling <stefan.werfling@hw-softwareentwicklung.de>
 */
public class jegwxmlconfig extends hwxml
{
    public static final String CONF_CONFIGURATIONS  = new String("configurations");
    public static final String CONF_CONFIG          = new String("config");
    public static final String CONF_ACTIVCONFIG     = new String("activconfig");

    public static final String[] CONF_STRUCT        = new String[]{
        "host", "user", "password", "subdir", "egwurl", "logindomain", 
        "sslcert", "port"};
    
    private ArrayList ConfigList    = new ArrayList();
    private KeyArray tmpConfig      = new KeyArray(jegwxmlconfig.CONF_STRUCT);
    private Boolean tagstatus       = false;
    private Integer activconfig     = 0;

    public jegwxmlconfig(String xmlfile) throws Exception
    {
        super(xmlfile);
    }

    public void addConf(KeyArray conf)
    {
        this.ConfigList.add(conf);
    }

    public void setActivConf(Integer index)
    {
        if( index < this.ConfigList.size() )
        {
            this.activconfig = index;
        }
    }

    public KeyArray getActivConf()
    {
        return this.getConf(this.activconfig);
    }
    
    public KeyArray getConf(Integer index)
    {
        if( index < this.ConfigList.size() )
        {
            return (KeyArray) this.ConfigList.get(index);
        }

        return null;
    }

    public Integer countConf()
    {
        Integer i = this.ConfigList.size();
        return i;
    }

    public void deleteConf(Integer index)
    {
        if( index < this.ConfigList.size() )
        {
            ArrayList newlist = new ArrayList();

            for(int i=0; i<this.ConfigList.size(); i++ )
            {
                if(i != index)
                {
                    newlist.add(this.ConfigList.get(i));
                }
            }

            this.ConfigList = newlist;
        }
    }

    @Override protected boolean mRead(XMLEvent event, XMLEventReader eventReader)
    {
        if( event.isStartElement() )
        {
            String tag = event.asStartElement().getName().getLocalPart();

            if(tag.compareTo(jegwxmlconfig.CONF_CONFIG) == 0)
            {
                this.tagstatus = true;
                // Config Leeren für neue Config
                this.tmpConfig = new KeyArray(this.tmpConfig.getKeys());
                return false;
            }

            // Aktive Configuration
            if(tag.compareTo(jegwxmlconfig.CONF_ACTIVCONFIG) == 0)
            {
                try
                {
                    event = eventReader.nextEvent();
                    String value = event.asCharacters().getData();
                    this.activconfig = Integer.parseInt(value);
                    return false;
                }
                catch (XMLStreamException ex) 
                {
                    Logger.getLogger(jegwxmlconfig.class.getName()).log(Level.SEVERE, null, ex);
                }
            }

            if(!this.tagstatus)
            {
                // Andere Elemente Interessieren nicht
                return false;
            }

            String[] keys = this.tmpConfig.getKeys();

            for(int i=0; i<keys.length; i++)
            {
                if(tag.compareTo(keys[i]) == 0)
                {
                    try
                    {
                        event = eventReader.nextEvent();
                        String value = "";

                        if((!event.isEndElement()) && (!event.isStartElement()))
                        {
                            value = event.asCharacters().getData();
                            
                        }

                        this.tmpConfig.add(keys[i], value);
                    }
                    catch (XMLStreamException ex)
                    {
                        Logger.getLogger(jegwxmlconfig.class.getName()).log(Level.SEVERE, null, ex);
                    }

                    return false;
                }
            }
        }
        else if(event.isEndElement())
        {
            String tag = event.asEndElement().getName().getLocalPart();
            
            if(tag.compareTo(jegwxmlconfig.CONF_CONFIG) == 0)
            {
                this.tagstatus = false;
                this.ConfigList.add(this.tmpConfig.clone());
                return false;
            }
        }

        return false;
    }

    @Override protected void mWrite(XMLEventFactory eventFactory, XMLEventWriter eventWriter)
    {
        XMLEvent end = eventFactory.createDTD("\r\n");
        XMLEvent tab = eventFactory.createDTD("\t");

        try 
        {
            eventWriter.add(this.makeStartTag(jegwxmlconfig.CONF_CONFIGURATIONS));
            eventWriter.add(end);

            for( int i=0; i<this.ConfigList.size(); i++ )
            {
                KeyArray conf = (KeyArray) this.ConfigList.get(i);

                String[] keys = conf.getKeys();

                eventWriter.add(tab);
                eventWriter.add(this.makeStartTag(jegwxmlconfig.CONF_CONFIG));
                eventWriter.add(end);

                for( int e=0; e<keys.length; e++ )
                {
                    eventWriter.add(tab);
                    eventWriter.add(tab);
                    eventWriter.add(this.makeStartTag(keys[e]));
                    eventWriter.add(this.makeValue(conf.getString(keys[e])));
                    eventWriter.add(this.makeEndTag(keys[e]));
                    eventWriter.add(end);
                }

                eventWriter.add(tab);
                eventWriter.add(this.makeEndTag(jegwxmlconfig.CONF_CONFIG));
                eventWriter.add(end);
            }

            eventWriter.add(this.makeEndTag(jegwxmlconfig.CONF_CONFIGURATIONS));
            eventWriter.add(end);

            // Aktiv Config
            eventWriter.add(this.makeStartTag(jegwxmlconfig.CONF_ACTIVCONFIG));
            eventWriter.add(this.makeValue(Integer.toString(this.activconfig)));
            eventWriter.add(this.makeEndTag(jegwxmlconfig.CONF_ACTIVCONFIG));
            eventWriter.add(end);
        }
        catch (XMLStreamException ex)
        {
            Logger.getLogger(jegwxmlconfig.class.getName()).log(Level.SEVERE, null, ex);
        }
    }
}
