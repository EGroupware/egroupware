<?php
    $types = array('abstract','param','result','description','discussion', 'author', 'copyright', 'package');

    if ($fn)
    {
        $files[] = $fn;
    }
    else
    {
        $d = dir('../phpgwapi/inc/');
        while ($x = $d->read())
        {
            if (ereg('class',$x) && !ereg('#',$x) && ereg('php',$x))
            {
                $files[] = $x;
            }
        }
        $d->close;
        reset($files);
    }

    while (list($p,$fn) = each($files))
    {
        $matches = $elements = $data = array();
        $string = $t = $out = $class = $xkey = $new = '';
        $file = '../phpgwapi/inc/' . $fn;
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
                    $elements[$class][$t][$xkey] = $out;
                }
            }
        }
        echo '<br><pre>';
//        print_r($elements);
        var_dump($elements);
        echo '</pre>' . "\n";
    }
?>