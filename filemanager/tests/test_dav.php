<?php
function ok($string){
/*	html_table_row_begin();
	html_table_col_begin();*/
	echo "<h3>$string ";
/*	html_table_col_end();
	html_table_col_begin();*/
	echo  " OK</h3>";
/*	html_table_col_end();
	html_table_row_end();*/
}
function fail($string){
/*	html_table_row_begin();
	html_table_col_begin();*/
	echo "<h3>$string ";
/*	html_table_col_end();
	html_table_col_begin();*/
	echo " <b>FAILED!</b></h3>";
/*	html_table_col_end();
	html_table_row_end(); */
}

$phpgw_info["flags"] = array("currentapp" => "filemanager",
                                "noheader" => False,
                                "noappheader" => False,
                                "enable_vfs_class" => True);

include("../../header.inc.php");

html_text('VFS_DAV tests:');
html_break (1);
html_text_italic (PHP_OS . " - " . $phpgw_info["server"]["db_type"] . " - " . PHP_VERSION . " - " . $phpgw->vfs->basedir);
html_break (1);
//html_table_begin();


$sep = SEP;
$user = $phpgw->vfs->working_lid;
$homedir = $phpgw->vfs->fakebase . "/" . $user;
$realhomedir = preg_replace ("|/|", $sep, $homedir);
$filesdir = $phpgw->vfs->basedir;
$currentapp = $phpgw_info["flags"]["currentapp"];
$time1 = time();

echo ' override locks : ';print_r($phpgw->vfs->override_locks);
	###
	# write test

	$phpgw->vfs->cd ();
	$testfile = 'sdhdsjjkldggfsbhgbnirooaqojsdkljajklvagbytoi-test';
	$teststring = 'delete me' ;
	if (!$result = $phpgw->vfs->write (array ('string' => $testfile,
		'content' => $teststring
		))) 
	{
		fail( __LINE__." failed writing file!");
	}
	else
	{
	ok("write");
	}

#read
	$phpgw->vfs->cd ();
	$result = $phpgw->vfs->read (array ('string' => $testfile, 'noview' => true	));
	if (!$result==$teststring) 
	{
		fail( __LINE__." failed reading file!");
	}
	else
	{
	ok(" read");
	}
	
	###
	# ls test

	$result1 = $phpgw->vfs->ls (array ('string' => $testfile	));

	if (!count($result1)) 
	{
		fail(__LINE__." failed listing file!");
	}
	else
	{
	ok(" ls : known file");
	}
//list the parent dir

	$result = $phpgw->vfs->ls (array ('string' => ''));
	foreach ($result as $file)
	{
		if ($testfile == $file['name'])
		{
			$found = true;
			break;
		}
	}
	if (!$found) 
	{
		fail(__LINE__." failed listing file!");
	}
	else
	{
		ok(" ls : parent");
	}
	$found = false;
	foreach ($result as $file)
	{
		if ($result1[0]['directory'] == $file['full_name'])
		{
			$found = true;
			break;
		}
	}
	if ($found) 
	{
		fail(__LINE__." parent is present in its own listing!");
	}
	else
	{
		ok(" ls : parent self reference");
	}
# getsize 
	$phpgw->vfs->cd ();
	$result = $phpgw->vfs->get_size (array ('string' => $testfile	));
	$len = strlen($teststring);
	if (!($result== $len)) 
	{
		fail(__LINE__." failed getting size of file result $result strlen $len");
	}
	else
	{
	ok("get_size");
	}
	
#filetype
	$phpgw->vfs->cd ();
	$result = $phpgw->vfs->file_type(array ('string' => $testfile	));
	$len = strlen($teststring);
	if (!($result== 'application/octet-stream')) 
	{
		fail(__LINE__." failed getting file type $result");
	}
	else
	{
	ok("file_type");
	}


#file_exists
	$phpgw->vfs->cd ();
	$result = $phpgw->vfs->file_exists(array ('string' => $testfile	));
	if (!$result) 
	{
		fail(__LINE__." file_exist failed: $result");
	}
	else
	{
	ok("file_exists");
	}

#lock
	$phpgw->vfs->cd ();
	$result = $phpgw->vfs->lock (array ('string' => $testfile	));
	if (!$result) 
	{
		fail(__LINE__."failed locking file!");
	}
	else
	{
	ok(" lock");
	}
	
	
		$ls_array = $GLOBALS['phpgw']->vfs->ls (array (
				'string'	=> $testfile,
				'relatives'	=> array (RELATIVE_ALL),
				'checksubdirs'	=> False
			)
		);
	if (!count($ls_array[0]['locks'])) 
	{
		fail(__LINE__."after locking file no locks exist!");
	}
	else
	{
	ok(" lock: after locking lock exists.");
	}
		$lock = end($ls_array[0]['locks']);
		$tokens = end($lock['lock_tokens']);
		$token = $tokens['name'];
	//write should now fail
	$result = $phpgw->vfs->write (array ('string' => $testfile,
		'content' => 'delete me' 
		));
	if ($result) 
	{
		fail(__LINE__."I can write a supposidly locked file!");
	}
	else
	{
	ok("lock: after locking write fails");
	}
	
	$phpgw->vfs->add_lock_override(array ('string' => $testfile));
	$result = $phpgw->vfs->write (array ('string' => $testfile,
		'content' => 'delete me' 
		));
	if (!$result) 
	{
		fail(__LINE__."I cant write a locked file after overriding the lock!");
	}
	else
	{
	ok("lock: after lock override write succeeds");
	}
###
# unlock test
	
	$phpgw->vfs->cd ();
	$result = $phpgw->vfs->unlock (array ('string' => $testfile	), $token);
	if (!$result) 
	{
		fail( __LINE__."failed unlocking file!");
	}
	else
	{
		OK("unlock");
	}

#server side copy
	
	$phpgw->vfs->cd ();
	$result = $phpgw->vfs->cp(array ('from' => $testfile,
				'to' => $testfile.'2',
				'relatives'	=> array (RELATIVE_ALL, RELATIVE_ALL)
				));
	if (!$result) 
	{
		fail(__LINE__." failed copying! returned: $result");
	}
	else
	{
		ok("server-side copy");
	}
	$result = $phpgw->vfs->file_exists(array ('string' => $testfile.'2'
				));
	if (!$result) 
	{
		fail(__LINE__." after copy, target doesnt exist!");
	}
	else
	{
		ok("server-side copy : test for target");
	}
	$result = $phpgw->vfs->read (array ('string' => "$testfile".'2', 
		'noview' => true,
		'relatives'	=> array (RELATIVE_ALL)
			));
	if (!$result==$teststring) 
	{
		fail( __LINE__."after copy, read returned '$result' not '$teststring' ");
	}
	else
	{
	ok(" server-side copy: read target");
	}
	$result = $phpgw->vfs->delete(array ('string' => $testfile.'2'
				));

	if (!$result) 
	{
		fail(__LINE__." failed copying! delete copied file returned: $result");
	}
	else
	{
		ok("server-side copy : delete target");
	}

#remote -> local copy	
	$phpgw->vfs->cd ();
	echo "<pre>";
	$result = $phpgw->vfs->cp(array ('from' => $testfile,
				'to' => "/tmp/$testfile".'2',
				'relatives'	=> array (RELATIVE_ALL, RELATIVE_NONE | VFS_REAL)
				));
	echo "</pre>";
	if (!$result) 
	{
		fail(__LINE__." failed remote->local copying! returned: $result");
	}
	else
	{
		ok("remote->local copy");
	}
	echo "<pre>";
	$result = $phpgw->vfs->file_exists(array ('string' => "/tmp/$testfile".'2',
	'relatives'	=> array (RELATIVE_NONE | VFS_REAL)
				));
	echo "</pre>";
	if (!$result) 
	{
		fail(__LINE__." after remote->local copy, target doesnt exist!");
	}
	else
	{
		ok("remote->local copy : test for target");
	}
	$phpgw->vfs->cd ();
	echo "<pre>";
	$result = $phpgw->vfs->read (array ('string' => "/tmp/$testfile".'2', 
		'noview' => true,
		'relatives'	=> array (RELATIVE_NONE | VFS_REAL)
			));
	echo "</pre>";
	if (!$result==$teststring) 
	{
		fail( __LINE__."after remote->local copy, returned $result");
	}
	else
	{
	ok(" remote->local copy: read target");
	}
	echo "<pre>";	
	$result = $phpgw->vfs->delete(array ('string' => "/tmp/$testfile".'2',
	'relatives'	=> array (RELATIVE_NONE | VFS_REAL)
				));
	echo "</pre>";
	if (!$result) 
	{
		fail(__LINE__." failed copying! delete copied file returned: $result");
	}
	else
	{
		ok("remote->local copy : delete target");
	}
	
#move
	$phpgw->vfs->cd ();
	echo "<pre>";
	$result = $phpgw->vfs->mv(array ('from' => $testfile,
				'to' => $testfile.'2',
				'relatives'	=> array (RELATIVE_ALL, RELATIVE_ALL)
				));
	echo "</pre>";
	if (!$result) 
	{
		fail(__LINE__." failed moving! returned: $result");
	}
	else
	{
		ok("server-side move");
	}
	$result = $phpgw->vfs->file_exists(array ('string' => $testfile,
				));
	if ($result) 
	{
		fail(__LINE__." failed moving! returned: $result");
	}
	else
	{
		ok("server-side move : test for source");
	}
	$result = $phpgw->vfs->file_exists(array ('string' => $testfile.'2',
				));
	if (!$result) 
	{
		fail(__LINE__." failed moving! returned: $result");
	}
	else
	{
		ok("server-side move : test for target");
	}
	echo "<pre>";
	$result = $phpgw->vfs->read (array ('string' => $testfile.'2', 
		'noview' => true,
		'relatives'	=> array (RELATIVE_ALL)
			));
	echo "</pre>";
	if (!$result==$teststring) 
	{
		fail( __LINE__."after move, read returned '$result' not '$teststring' ");
	}
	else
	{
	ok(" server-side move: read target");
	}	
	
	echo "<pre>";	
	$result = $phpgw->vfs->mv(array ('from' => $testfile.'2',
				'to' => $testfile,
				'relatives'	=> array (RELATIVE_ALL, RELATIVE_ALL)
				));
	echo "</pre>";
	if (!$result) 
	{
		fail(__LINE__." failed moving! returned: $result");
	}
	else
	{
		ok("server-side move : move back");
	}

#remote->local move
	echo "<pre>";
	$phpgw->vfs->cd ();
	$result = $phpgw->vfs->mv(array ('from' => $testfile,
				'to' => '/tmp/'.$testfile.'2',
				'relatives'	=> array (RELATIVE_ALL, RELATIVE_NONE | VFS_REAL)
				));
	echo "</pre>";
	if (!$result) 
	{
		fail(__LINE__." failed moving! returned: $result");
	}
	else
	{
		ok("remote->local move");
	}
	$result = $phpgw->vfs->file_exists(array ('string' => $testfile,
				));
	if ($result) 
	{
		fail(__LINE__." failed moving! returned: $result");
	}
	else
	{
		ok("remote->local move : test for source");
	}
	$result = $phpgw->vfs->file_exists(array ('string' => '/tmp/'.$testfile.'2',
				 'relatives' => array(RELATIVE_NONE | VFS_REAL)
				 ));
	if (!$result) 
	{
		fail(__LINE__." failed moving! returned: $result");
	}
	else
	{
		ok("remote->local move : test for target");
	}
	
	$result = $phpgw->vfs->read (array ('string' => '/tmp/'.$testfile.'2', 
		'noview' => true,
		'relatives' => array(RELATIVE_NONE | VFS_REAL)
			));
	if (!$result==$teststring) 
	{
		fail( __LINE__."after move, read returned '$result' not '$teststring' ");
	}
	else
	{
	ok("remote->local move: read target");
	}	
	
	echo "<pre>";
	$result = $phpgw->vfs->mv(array ('from' => '/tmp/'.$testfile.'2',
				'to' => $testfile,
				'relatives'	=> array (RELATIVE_NONE | VFS_REAL, RELATIVE_ALL)
				));
	echo "</pre>";
	if (!$result) 
	{
		fail(__LINE__." failed moving! returned: $result");
	}
	else
	{
		ok("server-side move : move back");
	}


###
# delete test
	
	$phpgw->vfs->cd ();
	$result = $phpgw->vfs->delete(array ('string' => $testfile	));
	if (!$result) 
	{
		fail(__LINE__."failed deleting file! returned: $result");
	}
	else
	{
		ok("delete");
	}

###
# mkdir test
	
	$phpgw->vfs->cd ();
	$result = $phpgw->vfs->mkdir(array ('string' => $testfile	));
	if (!$result) 
	{
		fail(__LINE__."failed creating collection! returned: $result");
	}
	else
	{
		ok("mkdir");
	}

	$result = $phpgw->vfs->write (array ('string' => $testfile.'/'.$testfile.'2',
		'content' => $teststring
		));
		
	if (!$result) 
	{
		fail( __LINE__." failed writing file into new dir!");
	}
	else
	{
	ok("mkdir : write into dir");
	}
###
# rm dir test
	
	$phpgw->vfs->cd ();
	$result = $phpgw->vfs->rm(array ('string' => $testfile	));
	if (!$result) 
	{
		fail(__LINE__." failed deleting collection! returned: $result");
	}
	else
	{
		ok("delete dir");
	}
	
	//html_table_end();
	$time = time() - $time1;
	html_text("Done in $time s");
	html_page_close ();

?>
