<?php
/**
 * EGroupware API: Creating letter avatar
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage avatar
 * @author Hadi Nategh <hn@egroupware.de>
 */

namespace EGroupware\Api\Contacts;

/**
 * Creating letter avatar
 *
 * Lavatar class designed for creating avatar image from given
 * user firstname.lastname and outputs them as in image consist of
 * first letter of the firstname plus first letter of the lastname.
 */
class Lavatar
{
	// background colors
	private static $BG_COLORS = array(
		array(90,135,112), array(178,183,187), array(111,169,171), array(245,175,41),
		array(0,136,185), array(241,134,54), array(217,58,55), array(166,177,46),
		array(0,136,185), array(241,134,54), array(217,58,55), array(166,177,46),
		array(92,155,188), array(245,136,141), array(154,137,181), array(64,120,135),
		array(154,137,181),array(90,135,112), array(211,63,51),	array(162,176,31),
		array(240,177,38),array(0,135,191), array(241,134,54), array(0,135,191),
		array(178,183,187),array(114,172,174), array(156,138,180), array(90,135,112),
		array(238,180,36),array(64,120,135)
	);

	// ratio of font size to size
	private static $_FONT_SIZE_RATIO = 3.6;

	// ratio of text Y position to size
	private static $_TEXT_Y_RATIO = 1.6;

	private static $_TEXT_COLOR = array (255, 255, 255);
	// font path
	private static $_FONT_PATH = '/api/templates/default/fonts/arial.ttf';

	public function __construct() {
	}

	/**
	 * Function to generate letter avatar image out of given
	 * user firstname and lastname.
	 *
	 * @param array $_content = null an array of firstname lastname
	 *	example:
	 *		array (
	 *			'firstname' => [userFirstname],
	 *			'lastname'  => [userLastname],
	 *			'id'		=> [user id]
	 *		)
	 * @param array $_color = null an array of RGB color, default
	 * is nul to get a random color from color library.
	 *	example: white color
	 *		array (
	 *			0, // RED
	 *			0, // GREEN
	 *			0  // BLUE
	 *		)
	 * @param int $_size = 128 image size, default size is 128
	 */
	public static function generate($_content = null, $_color = null, $_size = 128)
	{
		// firstname
		$firstname = isset($_content['firstname'])? $_content['firstname'] : '';
		//lastname
		$lastname = isset($_content['lastname'])? $_content['lastname'] : '';
		// id
		$id = isset($_content['id'])? $_content['id']: '';

		// Array of RGB color as background color
		$bgcolor = $_color ? $_color : self::_getBgColor($firstname.$lastname.$id);

		// Letters to be shown
		$text = strtoupper(mb_substr($firstname, 0, 1).mb_substr($lastname, 0, 1));

		//create an image
		$image = imagecreatetruecolor($_size, $_size);

		// allocate an image color as background color
		$bg = imagecolorallocate($image, $bgcolor[0], $bgcolor[1], $bgcolor[2]);

		// allocate an image color as textcolor
		$textcolor = imagecolorallocate($image, self::$_TEXT_COLOR[0],self::$_TEXT_COLOR[1],self::$_TEXT_COLOR[2]);

		// create a rectangle
		imagefilledrectangle($image, 0, 0, $_size, $_size, $bg);

		$fontsize = $_size / self::$_FONT_SIZE_RATIO;

		$box = imagettfbbox($fontsize, 0, EGW_SERVER_ROOT.self::$_FONT_PATH, $text);

		$x = floor(($_size - ($box[2] - $box[0]))/2);

		$y = floor ($_size / self::$_TEXT_Y_RATIO);

		// write a ttf text
		$ttf = imagettftext($image,	$fontsize, 0, $x, $y, $textcolor, EGW_SERVER_ROOT.self::$_FONT_PATH, $text);
		if ($ttf)
		{
			ob_start();
			imagejpeg($image, null,50);
			$result = ob_get_contents();
			ob_clean();
			imagedestroy($image);
			return $result;
		}
		else
		{
			imagedestroy($image);
		}
	}

	/**
	 * Function to select a color code from background colors array
	 * base on given string phrase. (FirstName LastName Id)
	 *
	 * @param string $_str string to convert to a color code
	 *
	 * @return string color code
	 */
	private static function _getBgColor ($_str)
	{
		$hash = 0;
		for ($i=0; $i< strlen($_str); $i++)
		{
			$hash = ord($_str[$i]) + $hash;
		}
		$index = $hash % count(self::$BG_COLORS);
		return self::$BG_COLORS[$index];
	}
}