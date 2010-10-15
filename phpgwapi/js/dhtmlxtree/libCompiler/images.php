<?php
function copyImgsFiles($source,$dest,$skin)
{ 
if (!is_dir($source)) return;
$folder = opendir($source);
while($file = readdir($folder))
{
if ($file == '.' || $file == '..') {
           continue;
       }
       if(is_dir($source.$file))
       {
       		if ($file!=".svn" && (strpos($file,"dhx")===false || preg_match("#(dhx|skin)_".$skin."#i",$file)==1)){
       			@mkdir($dest.$file);
       			copyImgsFiles($source.$file."/",$dest.$file."/",$skin);
   			}
       			
       }
       else 
       {
       //echo $source.$file;
        copy($source.$file,$dest.$file);
       }
}
closedir($folder);
return 1;
}

function export_images($component,$skin){
	$name=time();
	while (file_exists("./export/".$name)){
		$name+=1;
	}

	$name="./export/".$name;
	
	mkdir($name);
	mkdir($name."/imgs");
	mkdir($name."/types");
	$skin=explode("_",$skin);
	
	for ($i=0; $i < sizeof($component); $i++) { 
		copyImgsFiles("../".$component[$i]."/codebase/imgs/",$name."/imgs/",$skin[1]);
		copyImgsFiles("../".$component[$i]."/codebase/types/",$name."/types/",$skin[1]);
	}
	return $name;
};


	define("YUI",true);

$js_header="/*
Copyright DHTMLX LTD. http://www.dhtmlx.com
To use this library please contact sales@dhtmlx.com to obtain license
*/\n";
	
	function replaceComments($data){
		$data=str_replace('("//',"___XPATH_HEAD___",$data);
		$data=str_replace('("/*',"___XPATH_HEAD2___",$data);
		$data=str_replace('(".//',"___XPATH_HEAD3___",$data);
		$data=preg_replace("#\/\/[^\r\n]*(\r\n|\n)#","\n",$data);
		$data=preg_replace("#\/\*.*?\*\/#s","",$data);
		$data=str_replace("___XPATH_HEAD___",'("//',$data);
		$data=str_replace("___XPATH_HEAD2___",'("/*',$data);
		$data=str_replace("___XPATH_HEAD3___",'(".//',$data);
		return $data;
	}
	function replaceWhitespaces($data){ 
		$data=preg_replace("|[ \f\t]+|"," ",$data);
		$data=preg_replace("|\}[ \r\n]+|","};",$data);
		$data=preg_replace("#((if|for|while)[\s]*\([^\)]*\))[ \r\n]+#","\\1",$data);
		$data=preg_replace("|([;\{]{1})[ \r\n]+([^ \r\n]{1})|","\\1\\2",$data);
		$data=str_replace("};}","}}",$data); //object notation fix
		$data=str_replace("};}","}}",$data); //object notation fix
		$data=str_replace("};else","}else",$data); //object notation fix
		$data=str_replace("};catch","}catch",$data); //object notation fix
		$data=str_replace(";)",")",$data); //object notation fix
		$data=str_replace(";,",",",$data); //object notation fix
		$data=str_replace(";:",":",$data); //object notation fix
		$data=str_replace("};]","}]",$data); //object notation fix
		return $data;
	}
	
	function getTokens($data,$name){
	
		preg_match_all("|//#([^\n]*)|",$data,$chunks,PREG_OFFSET_CAPTURE);
			$ballans=0;
            $c_size=0;
			$temp=array();
		for ($i=0; $i<sizeof($chunks[0]); $i++){
		    $s_l=$chunks[1][$i][0];
            if (strpos($s_l,"{")!==false){
               //start of chunk
               $temps=explode(":",trim(str_replace("{","",str_replace("//#","",$s_l))));
               if (sizeof($temps)!=2)
                  die("Incorrect Injection found <br/>".$s_l."( position ".$chunks[0][$i][1].") at ".$name);
               $temp[$c_size]=array($temps[0],$temps[1],$chunks[0][$i][1],0);
               $c_size++;
            }
            else
            {
               if (!$temp[$ballans])
               {
               	echo "<pre>";print_r($temp);
                  die("Incorrect Injection layout,  <br/> ballans corrupted. <br/>".$s_l."( position ".$chunks[0][$i][1].") at ".$name);
                  return 0;
               }

               for ($ii=$c_size-1; $ii>=0; $ii--)
                  if ($temp[$ii][3]==0)
                  {
                     $temp[$ii][3]=$chunks[0][$i][1]+strlen($chunks[0][$i][0]);
                     break;
                  }

               $ballans++;
               //end of chunk
            }

         }

         if ($ballans!=(sizeof($chunks[0])/2))
         {
            die("Incorrect Injection layout,  <br/> ballans corrupted at ".$name);
            return 0;
         }

		$tokens=array();
		
        foreach ($temp as $k=>$v){
            $name=$v[0];
            if (!$tokens[$name])
               $tokens[$name]=array();

            $tokens[$name][]=$v;
         }
         return $tokens;
	}
	function clearAreas($data,$tokens,$preserve){
	     foreach($tokens as $k=>$v){  
	     	if ($v && array_search($k,$preserve)===FALSE){ 
				foreach($v as $kd=>$vd){
					$size=$vd[3]-$vd[2];
					$bik=str_repeat("#",$size);
					$data=substr($data,0,$vd[2]).$bik.substr($data,$vd[3]);
				}
            }
         }
         $data=preg_replace("|([#]{6,})|","",$data);
         return $data;
   }
   
	function process_request($files, $chunks, $skin, $yui=false){
		global $js_header;
		
		$files=explode(";",$files);
		$chunks=explode(";",$chunks);
		array_push($chunks,"__pro_feature");

		$js_list=array("./dhtmlxcommon.js");
		$css_list=array();
		$manifest = array("Skin: ".$skin);
		
		for ($i=0; $i<sizeof($files); $i++){
			if ($files[$i]=="") continue;
			if (preg_match("/.*\.js$/",$files[$i]))
				array_push($js_list,".".$files[$i]);
			else
				array_push($css_list,".".$files[$i]);
			}
					
		
		$js_list=array_values(array_unique($js_list));
		$css_list=array_values(array_unique($css_list));
		$chunks=array_values(array_unique($chunks));
		
		$components=array();
		for ($i=0; $i < sizeof($js_list); $i++) { 
			$temp=explode("/",$js_list[$i]);
			if (sizeof($temp)>2)
				array_push($components,$temp[1]);
		}
		$location=export_images(array_values(array_unique($components)),$skin);
		for ($i=0; $i < sizeof($components); $i++) { 
			$temp_name="../".$components[$i]."/codebase/skins/".strtolower($components[$i])."_".$skin.".css";
			
			if (file_exists($temp_name))
				array_push($css_list,$temp_name);
		}
		
		$css_list=array_values(array_unique($css_list));
		
		$js_code="";
		$css_code="";
		$jscore_code="";
			
		array_push($manifest,"\n======== JS CODE =========");
		for ($i=0; $i<sizeof($js_list); $i++){
			if (strpos($js_list[$i],"dhtmlxdataview.js")!==false || strpos($js_list[$i],"dhtmlxchart.js")!==false){
				if (sizeof($js_list) == 2){
					if (!is_file($js_list[$i])) continue;
					$js_code="";
					$jscore_code="\r\n".file_get_contents($js_list[$i]);
					if (strpos($js_list[$i],"dhtmlxchart.js")!==false)
						$jscore_code.="\r\n".file_get_contents(str_replace("dhtmlxchart.js","thirdparty/excanvas/excanvas.js",$js_list[$i]));
					continue;
				} else {
					$jscore_code.="\r\n".file_get_contents("./core.js");
					continue;
				}
			}
			
			$check_path=str_replace("codebase","sources",$js_list[$i]);
			if (is_file($check_path)) 
				$js_code.="\r\n".file_get_contents($check_path);
			else{
				if (!is_file($js_list[$i])) continue;
				$js_code.="\r\n".file_get_contents($js_list[$i]);
			}
			array_push($manifest,$js_list[$i]);
		}
		array_push($manifest,"\n======== CSS CODE ========");	
		for ($i=0; $i<sizeof($css_list); $i++){
			if (!is_file($css_list[$i])) continue;
			$css_code.="\r\n".file_get_contents($css_list[$i]);
			array_push($manifest,$css_list[$i]);
		}
		
		$js_code=clearAreas($js_code,getTokens($js_code,"some file"),$chunks);
		if ($js_code!="")
			$js_code.="\ndhtmlx.skin='".$skin."';";
		if (!$yui)
			$js_code=replaceWhitespaces(replaceComments($js_code));
		else{
			file_put_contents($location."/temp.js",$js_code);
			`java  -jar ./yui/yuicompressor-2.3.5/build/yuicompressor-2.3.5.jar {$location}/temp.js > {$location}/temp2.js`;
			$js_code=file_get_contents($location."/temp2.js");
			unlink($location."/temp.js");
			unlink($location."/temp2.js");
		}
			
	    $css_code=preg_replace('/"/',"'",$css_code);
	    $css_code=str_replace("../imgs","imgs/",$css_code);
	    $css_code=str_replace("../../codebase/","",$css_code);
	
	   if (!$yui){
	   		$css_code=preg_replace("#\/\*([^\*]|\*[^\/])*\*\/#","",$css_code);
			$css_code=preg_replace('/[ \t]+/'," ",$css_code);
			$css_code=preg_replace("/\{[\r\n]+/","{",$css_code);
			$css_code=preg_replace("/;[\r\n]+/",";",$css_code);
			$css_code=preg_replace("/[\r\n]+/","\n",$css_code);
			$css_code=preg_replace("/\/\*.*\n/","",$css_code);
			//$css_code=preg_replace("/\n/","\";str+=\"",$css_code);
			//$css_code=preg_replace("/\n/","\\n",$css_code);
		
			$css_code=preg_replace("/\{ /","{",$css_code);
			$css_code=preg_replace("/[; ]+\}/","}",$css_code);
	   }else{
	   		file_put_contents($location."/temp.css",$css_code);
			`java  -jar ./yui/yuicompressor-2.3.5/build/yuicompressor-2.3.5.jar {$location}/temp.css > {$location}/temp2.css`;
			$css_code=file_get_contents($location."/temp2.css");
			unlink($location."/temp.css");
			unlink($location."/temp2.css");
	   }
	    
	
		file_put_contents($location."/dhtmlx.js",$js_header.$js_code.$jscore_code);
		file_put_contents($location."/dhtmlx.css",$css_code);
		file_put_contents($location."/manifest.txt",implode("\n",$manifest));
		
		return $location;
}

?>