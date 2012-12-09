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

import java.io.File;
import java.io.FileInputStream;
import java.io.FileOutputStream;
import java.io.InputStream;
import java.net.URL;
import javax.xml.stream.XMLEventFactory;
import javax.xml.stream.XMLEventReader;
import javax.xml.stream.XMLEventWriter;
import javax.xml.stream.XMLInputFactory;
import javax.xml.stream.XMLOutputFactory;
import javax.xml.stream.events.Characters;
import javax.xml.stream.events.EndElement;
import javax.xml.stream.events.StartDocument;
import javax.xml.stream.events.StartElement;
import javax.xml.stream.events.XMLEvent;

/**
 * hwxml
 * 
 * @author Stefan Werfling <stefan.werfling@hw-softwareentwicklung.de>
 */
public class hwxml
{
	private InputStream istream = null;
    private String file			= "";
    private boolean newfile		= false;

	public hwxml(InputStream xmlfile) throws Exception
    {
		this.istream = xmlfile;
    }
	
	public hwxml(URL xmlfile) throws Exception
    {
        this.file = xmlfile.getFile();

        File f = new File(xmlfile.toURI());

        if(!f.exists())
        {
            f.createNewFile();
            this.newfile = true;
        }

        if(f.length() == 0 )
        {
            this.newfile = true;
        }
    }
	
    public hwxml(String xmlfile) throws Exception
    {
        this.file = xmlfile;

        File f = new File(xmlfile);

        if(!f.exists())
        {
            f.createNewFile();
            this.newfile = true;
        }

        if(f.length() == 0 )
        {
            this.newfile = true;
        }
    }

    public void read(boolean oneVarModus) throws Exception
    {
        if( !this.newfile )
        {
            XMLInputFactory inputFactory = XMLInputFactory.newInstance();
			
			InputStream in = null;
			
			if( this.istream != null )
			{
				in = this.istream;
			}
			else
			{
				in = new FileInputStream(this.file);
			}

            XMLEventReader eventReader = inputFactory.createXMLEventReader(in);

            while( eventReader.hasNext() )
            {
                XMLEvent event = eventReader.nextEvent();

                if( this.mRead(event, eventReader) )
                {
                    if( oneVarModus )
                    {
                        break;
                    }
                }
            }
        }
    }


    protected boolean mRead(XMLEvent event, XMLEventReader eventReader)
    {
        return false;
        // Override
    }

    public void write() throws Exception
    {
        XMLOutputFactory outputFactory = XMLOutputFactory.newInstance();
        FileOutputStream out = new FileOutputStream(this.file);
        XMLEventWriter eventWriter = outputFactory.createXMLEventWriter(out);

        XMLEventFactory eventFactory = XMLEventFactory.newInstance();
        XMLEvent end = eventFactory.createDTD("\r\n");

        StartDocument startDocument = eventFactory.createStartDocument();
        eventWriter.add(startDocument);
        eventWriter.add(end);

        eventWriter.add(this.makeStartTag("root"));
        eventWriter.add(end);

        this.mWrite(eventFactory, eventWriter);

        eventWriter.add(this.makeEndTag("root"));
        eventWriter.add(end);

        eventWriter.close();

        this.newfile = false;
    }

    protected void mWrite(XMLEventFactory eventFactory, XMLEventWriter eventWriter)
    {
        // Override
    }

    protected StartElement makeStartTag(String name)
    {
        XMLEventFactory eventFactory = XMLEventFactory.newInstance();
        return eventFactory.createStartElement("", "", name);
    }

    protected EndElement makeEndTag(String name)
    {
        XMLEventFactory eventFactory = XMLEventFactory.newInstance();
        return eventFactory.createEndElement("", "", name);
    }

    protected Characters makeValue(String value)
    {
		if( value == null )
		{
			value = "";
		}
		
        XMLEventFactory eventFactory = XMLEventFactory.newInstance();
        return eventFactory.createCharacters(value);
    }
}
