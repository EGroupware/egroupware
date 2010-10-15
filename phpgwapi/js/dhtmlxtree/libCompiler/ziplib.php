<?php
// **************************
// *      Ziplib 0.3.1      *
// *    Created by: Seven   *
// **************************
//
// Simple set of functions to create a zipfile
// according to the PKWare specifications, which
// can be found on:
// http://www.pkware.com/products/enterprise/white_papers/
// ©1989-2003, PKWARE Inc.

// functions in here start with zl, which comes from ZipLib ;)

// requires dostime.php
$settings['offset'] = 1; // Holland ;)

class Ziplib {

var $archive;
var $archive_fileinfo = array();
var $archive_filecount;
var $compr_lvl_last;

private function zl_compress($data,$level = "",$type = "") {
	if($type != "g" && $type != "b" && $type != "n") {
		// Darnit, they forgot to set the type. Assuming gZip if any compression
		if($level >= 1 && $level <= 9) $type = "g";
		elseif($level > 9) die("Compression level set too high");
		else $type = "n";
	}
		
	if($type == "g") {
		$this->compr_lvl_last = 8;
		RETURN gzdeflate($data,$level);
	} elseif($type == "b") {
		$this->compr_lvl_last = 12;
		RETURN bzcompress($data,$level);
	} else {
		$this->compr_lvl_last = 0;
		RETURN $data;
	}
}

public function zl_add_file($data,$filename,$comp = "") {
	global $settings;
	// if we already created a file, we'll make sure it'll be there in the coming zipfile ;)

	// first, checking some data
	if(strlen($filename) > pow(2,16)-1) die("Filename $filename too long"); // ooh, dirty... dieing, will change later
	if(strlen($data) > pow(2,32)-1) die("File $filename larger then 2GB, cannot continue"); // another one, naughty me ;)

	// $comp has a special format. the first character tells me about the compression, the second one represents the level
	if(strlen($comp) == 1) {
		// they still use the old method, assuming gzip
		
		$comp_type = "n";
		$comp_lvl = 0;
		if($comp >= 1 && $comp <= 9) {
			$comp_type = "g";
			$comp_lvl = $comp;
		}
	} else {
		$comp_lvl = 5;
		$comp_type = "n";
		// hmmm, the new method. Is it valid?
		if ($comp[0] == "b" OR $comp[0] == "g" OR $comp[0] == "n") $comp_type = $comp[0];
		if (preg_match("/[0-9]/i",$comp[1])) $comp_lvl = $comp[1];
	}

	// ok, let's get this bitch tidy:
	// first adding a file
	$compressed = $this->zl_compress($data,$comp_lvl,$comp_type);
	$uncompressed = strlen($data);

	$newfile = "\x50\x4b\x03\x04";				// Header
	$newfile .="\x00\x00";					// Version needed to extract
	$newfile .="\x00\x00";					// general purpose bit flag
	$newfile .=pack("v",$this->compr_lvl_last);		// compression method
	$newfile .=pack("v",dostime_get($settings['offset']));			// last mod file time
	$newfile .=pack("v",dosdate_get($settings['offset']));			// last mod file date
	$newfile .=pack("V",crc32($data));			// CRC32
	$newfile .=pack("V",strlen($compressed));		// compressed filesize
	$newfile .=pack("V",$uncompressed);			// uncompressed filesize
	$newfile .=pack("v",strlen($filename));			// length of filename
	$newfile .="\x00\x00";					// some sort of extra field ;)
	$newfile .=$filename;
	$newfile .=$compressed;

	$this->archive .= $newfile;


	// some 'statistics' for this file ;)
	$this->archive_filecount++;
	$idf = $this->archive_filecount - 1;
	$this->archive_fileinfo[$this->archive_filecount]['comp_type'] = $this->compr_lvl_last;
	$this->archive_fileinfo[$this->archive_filecount]['size'] = strlen($data);
	$this->archive_fileinfo[$this->archive_filecount]['size_comp'] = strlen($compressed);
	$this->archive_fileinfo[$this->archive_filecount]['pkg_size'] = strlen($newfile);
	if(!empty($this->archive_fileinfo[$idf]['local_stats_pointer'])) {
		$this->archive_fileinfo[$this->archive_filecount]['local_stats_pointer'] = 
		$this->archive_fileinfo[$idf]['local_stats_pointer'] +
		$this->archive_fileinfo[$idf]['pkg_size'] + 1; // HACKERDIEHACK! only way to get local_stats_pointer to '0' (for now) in zl_pack
	} else {
		$this->archive_fileinfo[$this->archive_filecount]['local_stats_pointer'] = 1;
	}
	$this->archive_fileinfo[$this->archive_filecount]['name'] = $filename;
	$this->archive_fileinfo[$this->archive_filecount]['crc32'] = crc32($data);
	unset($file,$compressed); // to avoid having data in our memory double ;)
	RETURN TRUE;
}

public function zl_pack($comment) {
	global $settings;
	if(strlen($comment) > pow(2,16)-1) die("Comment too long"); // that's 3

	// now the central directory structure start
	for($x=1;$x <= $this->archive_filecount;$x++) {
		$file_stats = $this->archive_fileinfo[$x];
		$cdss .= "\x50\x4b\x01\x02";			// Header
		$cdss .="\x00\x00";				// version made by
		$cdss .="\x00\x00";				// version needed to extract
		$cdss .="\x00\x00";				// general purpose bit flag
		$cdss .=pack("v",$file_stats['comp_type']);	// compression method
		$cdss .=pack("v",dostime_get($settings['offset']));		// last mod file time
		$cdss .=pack("v",dosdate_get($settings['offset']));		// last mod file date
		$cdss .=pack("V",$file_stats['crc32']);		// CRC32
		$cdss .=pack("V",$file_stats['size_comp']);	// compressed size
		$cdss .=pack("V",$file_stats['size']);		// uncompressed size
		$cdss .=pack("v",strlen($file_stats['name']));	// file name length
		$cdss .="\x00\x00";				// extra field length
		$cdss .="\x00\x00";				// file comment length
		$cdss .="\x00\x00";				// disk number start
		$cdss .="\x00\x00";				// internal file attributes
		$cdss .="\x00\x00\x00\x00";			// external file attributes
		$cdss .=pack("V",$file_stats['local_stats_pointer']-$x);	// relative offset of local header
										// aka: The local_stats_pointer hack: part 2, see above
		$cdss .=$file_stats['name'];
	}

	// and final, the ending central directory structure! "WHOO HOOW!" (©Blur, 1998)
	$cdse = "\x50\x4b\x05\x06";			// Header
	$cdse .="\x00\x00";				// number of this disk
	$cdse .="\x00\x00";				// number of the disk with the start of the central directory
	$cdse .=pack("v",$this->archive_filecount);	// total number of entries in the central directory on this disk
	$cdse .=pack("v",$this->archive_filecount);	// total number of entries in the central directory
	$cdse .=pack("V",strlen($cdss));		// size of the central directory
	$cdse .=pack("V",strlen($this->archive));	// offset of start of central directory with respect to the starting disk number
	$cdse .=pack("v",strlen($comment));		// .ZIP file comment length
	$cdse .=$comment;
	
	return $this->archive.$cdss.$cdse;
}

public function zl_index_file($file) {
	$fp = @fopen($file,"rb");
	// ok, as we don't know what the exact position of everything is, we'll have to "guess" using the default sizes
	//and set values in the zipfile. Basicly this means I have to go through the entire file, could take some time.
	//Let's see if I can create an algorithm powerful enough.
	if(!$fp) die("File empty");
	$continue = 1;
	$file_count = 0;

	while($continue) {
		$content = fread($fp,30);
		$id = substr($content,0,4);
		if ($id == "\x50\x4b\x03\x04") {
			// the method used is quite simple, load a file in the memory, and walk through several parts of it using substr
			// As the PKZip format uses mostly fixed sizes for information, this isn't too hard to implement
			// First I want everything tested, before I start giving this function a nice place in the class
			$temp[$file_count]['file-size'] = ascii2dec(substr($content,18,4));
			$temp[$file_count]['filename-size'] = ascii2dec(substr($content,26,2));
			$temp[$file_count]['compression-type'] = ascii2dec(substr($content,8,2));
			$temp[$file_count]['crc'] = ascii2dec(substr($content,14,4));
			$temp[$file_count]['dostime'] = dostime_return(substr($content,10,2));
			$temp[$file_count]['dosdate'] = dosdate_return(substr($content,12,2));

			$temp[$file_count]['filename'] = fread($fp,$temp[$file_count]['filename-size']);

			// As the Zip format does not include Content type headers, I'll create a nice little array with 
			// extension/content type, and a small function to retreive it
			$temp[$file_count]['file-type'] = ext2cth($temp[$file_count]['filename']);
			$temp[$file_count]['content'] = fread($fp,$temp[$file_count]['file-size']);

			if ($temp[$file_count]['compression-type'] != 0 AND $temp[$file_count]['compression-type'] != 8 AND $temp[$file_count]['compression-type'] != 12) {
				$temp[$file_count]['lasterror'] = "Compression type not supported";
			} else {
				if($temp[$file_count]['compression-type'] == 8) {
					$temp[$file_count]['content'] = gzinflate($temp[$file_count]['content']);
				} elseif ($temp[$file_count]['compression-type'] == 12) {
					$temp[$file_count]['content'] = bzdecompress($temp[$file_count]['content']);
				}
				$verify = crc32($temp[$file_count]['content']);
				if ($verify != $temp[$file_count]['crc']) {
					$temp[$file_count]['lasterror'] = "CRC did not match, possibly this zipfile is damaged";
				}
			}
			$file_count++;
		} else {
			$continue = 0;	
		}

	}
	fclose($fp);
	unset($fp,$content,$file_count);
	return $temp;
}
}

	function zipImgsFiles($source,$zip)
	{ 
		
		$folder = opendir($source==""?"./":$source);
		while($file = readdir($folder))
		{
		if ($file == '.' || $file == '..') {
		           continue;
		       }
		       
		       if(is_dir($source.$file))
		       {
	                zipImgsFiles($source.$file."/",$zip);
		       }
		       else 
		       {
		        $zip->zl_add_file(file_get_contents($source.$file),$source.$file,"g0");
		       }
		}
		closedir($folder);
		return 1;
	}

	function zipFromLocation($location,$name="dhtmlx"){
		//echo $location;
		chdir($location);		
		$zip = new Ziplib;
		$zip->zl_add_file(file_get_contents("dhtmlx.js"),$name.'.js',"g9");
		$zip->zl_add_file(file_get_contents("dhtmlx.css"),$name.'.css',"g9");
		$zip->zl_add_file(file_get_contents("manifest.txt"),'manifest.txt',"g9");
		zipImgsFiles("imgs/",$zip);
		$files = @scandir("./types");
		if (count($files) > 2)
			zipImgsFiles("types/",$zip);
			
		$outZip = $zip->zl_pack("");
		
		return $outZip;
	}	
?>
