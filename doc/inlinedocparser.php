<?php
	/**************************************************************************\
	* phpGroupWare                                                             *
	* http://www.phpgroupware.org                                              *
	* The file written by Miles Lott <milosch@phpgroupware.org>                *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

    $types = array('abstract','param','example','syntax','result','description','discussion','author','copyright','package','access');

		if(!$app)
		{
				$app = 'phpgwapi';
		}
		
    if ($fn)
    {
				if (preg_match("/^class\.[a-zA-Z0-9]\.inc\.php+$/i",$fn)){
						$files[] = $fn;
				}
				else
				{
						echo 'No valid file selected';
						exit;
				}
    }
    else
    {
        $d = dir('../'.$app.'/inc/');
        while ($x = $d->read())
        {
            if (ereg('class',$x) && !ereg('#',$x) && ereg('php',$x))
            {
                $files[] = $x;
            }
        }
        $d->close;

				reset($files);

				while(list($key, $value) = each($files))
				{
						//echo '$key = '.$key.' and $value = '.$value.'<br>';
						if (!preg_match("/^class\.(.*)\.inc\.php+$/",$value))
						{
								unset($files[$key]);
								//echo '#'.$key.' is bad, and should be unset<br>';
						}
				}
 
				reset($files);
    }

    while (list($p,$fn) = each($files))
    {
        $matches = $elements = $data = array();
        $string = $t = $out = $class = $xkey = $new = '';
        $file = '../'.$app.'/inc/' . $fn;
        echo '<br>Looking at: ' . $file . "\n";

        $f = fopen($file,'r');
        while (!feof($f))
        {
            $string .= fgets($f,8000);
        }
        fclose($f);

        preg_match_all("#\*\!(.*)\*/#sUi",$string,$matches,PREG_SET_ORDER);

        while (list($key,$val) = each($matches))
        {
            preg_match_all("#@(.*)$#sUi",$val[1],$data);
            $new = explode("@",$data[1][0]);

            while (list($x,$y) = each($new))
            {
                $t = trim($new[0]);
                if(!$key)
                {
                    $class = $t;
                }
                $t = trim(ereg_replace('function','',$t));

                reset($types);
                while(list($z,$type) = each($types))
                {
                    if(ereg($type,$y))
                    {
                        $xkey = $type;
                        $out = $y;
                        $out = ereg_replace($type,'',$out);
                        break;
                    }
                    else
                    {
                        $xkey = 'unknown';
                        $out = $y;
                    }
                }

                if($out != $new[0])
                {
                    $elements[$class][$t][$xkey][] = $out;
                }
            }
        }
        echo '<br><pre>';
        print_r($elements);
//        var_dump($elements);
        echo '</pre>' . "\n";
    }
?>
