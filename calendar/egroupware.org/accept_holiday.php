<?php
/**
 * Calendar - Accepting holiday files on egroupware.org
 *
 * @link http://www.egroupware.org
 * @author Mark Peters <skeeter@phpgroupware.org>
 * @package calendar
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

die('This file is only used on http://www.egroupware.org/ and therefore disabled on local installations!');

if(empty($_POST['locale']) || !preg_match('/^[A-Z]{2}$/',$_POST['locale']))
{
	die('Missing or wrong value for required _POST[local]!');
}
$send_back_to = str_replace('submit','admin',$_SERVER['HTTP_REFERER']);

function _holiday_cmp($a,$b)
{
	if (($year_diff = ($a['occurence'] <= 0 ? 0 : $a['occurence']) - ($b['occurence'] <= 0 ? 0 : $b['occurence'])))
	{
		return $year_diff;
	}
	return $a['month'] - $b['month'] ? $a['month'] - $b['month'] : $a['day'] - $b['day'];
}

$send_back_to = str_replace('&locale='.$_POST['locale'],'',$send_back_to);
$file = './holidays.'.$_POST['locale'].'.csv';
if(!file_exists($file) || filesize($file) < 300)	// treat very small files as not existent
{
	if (count($_POST['name']))
	{
		$fp = fopen($file,'w');
		if ($_POST['charset']) fwrite($fp,"charset\t".$_POST['charset']."\n");

		$holidays = array();
		foreach($_POST['name'] as $i => $name)
		{
			$holidays[] = array(
				'locale' => $_POST['locale'],
				'name'   => str_replace('\\','',$name),
				'day'    => $_POST['day'][$i],
				'month'  => $_POST['month'][$i],
				'occurence' => $_POST['occurence'][$i],
				'dow'    => $_POST['dow'][$i],
				'observance_rule' => $_POST['observance'][$i],
			);
		}
		// sort holidays by year / occurence:
		usort($holidays,'_holiday_cmp');

		$last_year = -1;
		foreach($holidays as $holiday)
		{
			$year = $holiday['occurence'] <= 0 ? 0 : $holiday['occurence'];
			if ($year != $last_year)
			{
				fwrite($fp,"\n".($year ? $year : 'regular (year=0)').":\n");
				$last_year = $year;
			}
			fwrite($fp,"$holiday[locale]\t$holiday[name]\t$holiday[day]\t$holiday[month]\t$holiday[occurence]\t$holiday[dow]\t$holiday[observance_rule]\n");
		}
		fclose($fp);
	}
	Header('Location: '.$send_back_to);
	exit;
}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>
<head>
	<title>eGroupWare.org: There is already a holiday-file for '<?php echo $_POST['locale']; ?>' !!!</title>
</head>
<body>
	<h1>There is already a holiday-file for '<?php echo $_POST['locale']; ?>' !!!</h1>

	<p>If you think your version of the holidays for '<?php echo $_POST['locale']; ?>' should replace
	the existing one, please <a href="<?php echo $_SERVER['HTTP_REFERER']; ?>&download=1">download</a> the file
	and <a href="mailto:egroupware-developers@lists.sourceforge.net">mail it</a> to us.</p>

	<p>To get back to your own eGroupWare-install <a href="<?php echo $send_back_to; ?>">click here</a>.</p>
</body>
</html>
