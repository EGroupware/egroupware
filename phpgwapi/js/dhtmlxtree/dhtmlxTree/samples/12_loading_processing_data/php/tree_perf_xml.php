<?php
	error_reporting(E_ALL ^ E_NOTICE);
	
    header("Content-type:text/xml");
    $data=explode(",","5,10,20,30,50,80,100,200,500,1000");
    echo '<?xml version="1.0" ?><tree id="0">';

    for ($i=0; $i<sizeof($data); $i++){
        echo "<item id='arra_".$data[$i]."' text='arra ".$data[$i]."'>";
           for ($j=0; $j<$data[$i]; $j++)
                echo "<item id='arra_".$i."_".$j."' text='arra ".$i." ".$j."'></item>";
        echo "</item>";
    }
    echo '</tree>';
?>
