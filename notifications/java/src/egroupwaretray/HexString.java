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

import java.security.MessageDigest;
import java.security.NoSuchAlgorithmException;

/**
 * HexString
 * 
 * @author Stefan Werfling <stefan.werfling@hw-softwareentwicklung.de>
 */
public class HexString
{
    public static boolean isTwoHexDigitsEncodable(String s)
    {
      for(int i=0; i<s.length(); i++)
      {
         if(s.charAt(i)>(char)0xFF)
         {
            return false;
         }
      }

      return true;
    }

   public static String toHexString(String text)
   {
      StringBuilder builder=new StringBuilder(text.length()*2);
      char c;

      for(int i=0; i<text.length(); i++)
      {
         builder.append( ((c=text.charAt(i))<(char)0x10 ? "0" : "")+Integer.toHexString(c));
      }

      return builder.toString();
   }

   public static String fromHexString(String hex)
   {
      StringBuilder b=new StringBuilder(hex.length()/2);

      for(int i=0; i<hex.length(); i+=2)
      {
         b.append((char)Integer.parseInt(hex.substring(i, i+2) , 16));
      }

      return b.toString();
   }

   public static String getMD5Hash(String string)
   {
      try
      {
         MessageDigest md5 = MessageDigest.getInstance("MD5");
         md5.update(string.getBytes());
         byte[] digest = md5.digest();

         string = byteArrToHexString(digest);
      }
      catch (NoSuchAlgorithmException e1)
      {
         e1.printStackTrace();
      }

      return string;
   }

   public static String byteArrToHexString(byte[] bArr)
   {
      /*StringBuffer sb = new StringBuffer();

      for (int i = 0; i < bArr.length; i++)
      {
            int unsigned = bArr[i] & 0xff;
            sb.append(Integer.toHexString((unsigned)));
      }

      return sb.toString();*/

       StringBuffer buf = new StringBuffer();

        for( int i = 0; i < bArr.length; i++ )
        {
            int halfbyte = (bArr[i] >>> 4) & 0x0F;
            int two_halfs = 0;

            do
            {
                if ((0 <= halfbyte) && (halfbyte <= 9))
                {
                    buf.append((char) ('0' + halfbyte));
                }
                else
                {
                    buf.append((char) ('a' + (halfbyte - 10)));
                }

                halfbyte = bArr[i] & 0x0F;

            }
            while(two_halfs++ < 1);
        }

        return buf.toString();
   }

   public static byte[] hexStringToByteArray(String s)
   {
       int len = s.length();
       byte[] data = new byte[len / 2];

       for (int i = 0; i < len; i += 2)
       {
           data[i / 2] = (byte) ((Character.digit(s.charAt(i), 16) << 4)
                             + Character.digit(s.charAt(i+1), 16));
       }

       return data;
   }

   public static String IntToHexStr(int e, int length)
   {
       String tmp = Integer.toHexString(e);

       for(int i = tmp.length(); i<length; i++)
       {
           tmp = "0" + tmp;
       }

       return tmp;
   }

   public static String IntToShortStr(int e)
   {
       String back = "";
       String tmp = HexString.IntToHexStr(e, 4);

       back = tmp.substring(2, 4);
       back = back + tmp.substring(0, 2);

       return back;
   }

   public static String IntToDWordStr(int e)
   {
       String back = "";
       String tmp = HexString.IntToHexStr(e, 8);

       back = tmp.substring(6, 8);
       back = back + tmp.substring(4, 6);
       back = back + tmp.substring(2, 4);
       back = back + tmp.substring(0, 2);

       return back;
   }

   public static Integer hexStrDWordToInt(String hex)
   {
       String tmp = "";
       tmp = hex.substring(6, 8);
       tmp = tmp + hex.substring(4, 6);
       tmp = tmp + hex.substring(2, 4);
       tmp = tmp + hex.substring(0, 2);

       return Integer.parseInt(tmp, 16);
   }

   public static Integer hexStrWordToInt(String hex)
   {
       String tmp = "";
       tmp = hex.substring(2, 4);
       tmp = tmp + hex.substring(0, 2);

       return Integer.parseInt(tmp, 16);
   }

   public static String IntToHexByteStr(Integer e)
   {
       return HexString.IntToHexStr(e, 2);
   }

   public static Integer byteHexStrToInt(String hex)
   {
       return Integer.parseInt(hex, 16);
   }

   public static String FillHexLengthRigth(String str, int slength)
   {
       String back = str;

       for(int i = str.length(); i < slength; i++)
       {
           back = back + HexString.fromHexString("00");
       }

       return back;
   }
}
