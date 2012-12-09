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

/**
 * KeyArray
 * 
 * @author Stefan Werfling <stefan.werfling@hw-softwareentwicklung.de>
 */
public class KeyArray
{
    private String[] keys       = {};
    private ArrayList lkey      = new ArrayList();
    private ArrayList lvalue    = new ArrayList();

    public KeyArray(String[] keys)
    {
        this.keys = keys;
    }

    public void add(String key, Object o)
    {
        for(int i=0; i<this.lkey.size(); i++ )
        {
            String tkey = (String) this.lkey.get(i);

            if(tkey.compareTo(key) == 0)
            {
                this.lvalue.set(i, o);
                return;
            }
        }

        this.lkey.add(key);
        this.lvalue.add(o);
    }

    public Object get(String key)
    {
        for(int i=0; i<this.lkey.size(); i++ )
        {
            String tkey = (String) this.lkey.get(i);

            if(tkey.compareTo(key) == 0)
            {
                return this.lvalue.get(i);
            }
        }

        return null;
    }

    public String getString(String key)
    {
        String tmp = "";

        try
        {
			Object tob = this.get(key);
			
			if( tob != null )
			{
				tmp = tob.toString();
			}
        }
        catch(Exception e)
        {
            Logger.getLogger(KeyArray.class.getName()).log(Level.SEVERE, null, e);
            //Keine Meldung
        }

        return tmp;
    }

    public String[] getKeys()
    {
        return this.keys;
    }

    @Override
    public Object clone() 
    {
        /*try
        {*/
            KeyArray tmp = new KeyArray(this.keys);

            for(int i=0; i<this.keys.length; i++)
            {
                tmp.add(this.keys[i], this.get(this.keys[i]));
            }

            return tmp;
        /*}
        catch (CloneNotSupportedException e)
        {
            throw new InternalError();
        }*/
    }

    public Integer size()
    {
        Integer size = 0;

        if(this.lkey.size() == this.lvalue.size())
        {
            size = this.lkey.size();
        }

        return size;
    }
	
	public Boolean existKey(String key)
	{
		for(int i=0; i<this.keys.length; i++ )
        {
            String tkey = (String) this.keys[i];

            if(tkey.compareTo(key) == 0)
            {
				return true;
			}
		}
		
		return false;
	}
}
