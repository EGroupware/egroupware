<?php

$phpgw_info["flags"] = array("currentapp" => "filemanager",
                                "noheader" => False,
                                "noappheader" => False,
                                "enable_vfs_class" => True);

include("../header.inc.php");

/*
	General format for output is:
	sequence number - function - current directory - input[...] - what output should be - what output was
*/

html_break (1);
html_text_italic (PHP_OS . " - " . $phpgw_info["server"]["db_type"] . " - " . PHP_VERSION . " - " . $phpgw->vfs->basedir);
html_break (1);

$sep = SEP;
$user = $phpgw->vfs->working_lid;
$homedir = $phpgw->vfs->fakebase . "/" . $user;
$realhomedir = preg_replace ("|/|", $sep, $homedir);
$filesdir = $phpgw->vfs->basedir;
$currentapp = $phpgw_info["flags"]["currentapp"];

###
# start of getabsolutepath tests

$sequence_num = 1;
$phpgw->vfs->cd ();
$io = array ("" => "$homedir", "dir" => "$homedir/dir", "dir/file" => "$homedir/dir/file", "dir/dir2" => "$homedir/dir/dir2", "dir/dir2/file" => "$homedir/dir/dir2/file", "`~!@#$%^&*()-_=+/|[{]};:'\",<.>?" => "$homedir/`~!@#$%^&*()-_=+/|[{]};:'\",<.>?");

while (list ($i, $o) = each ($io))
{
	if (($ao = $phpgw->vfs->getabsolutepath (array ('string' => $i))) != $o)
	{
		echo "<br>$sequence_num - getabsolutepath - $cd - $i - $o - $ao";
	}
}

$sequence_num = 2;
$cd = "test";
$phpgw->vfs->cd (array(
		'string'	=> $cd,
		'relative'	=> False,
		'relatives'	=> array (RELATIVE_NONE)
	)
);
$io = array ("" => "/test", "dir" => "/test/dir", "dir/file" => "/test/dir/file", "dir/dir2" => "/test/dir/dir2", "dir/dir2/file" => "/test/dir/dir2/file", "`~!@#$%^&*()-_=+/|[{]};:'\",<.>?" => "/test/`~!@#$%^&*()-_=+/|[{]};:'\",<.>?");

while (list ($i, $o) = each ($io))
{
	if (($ao = $phpgw->vfs->getabsolutepath (array ('string' => $i))) != $o)
	{
		echo "<br>$sequence_num - getabsolutepath - $cd - $i - $o - $ao";
	}
}

$sequence_num = 3;
$cd = "test/test2/test3";
$phpgw->vfs->cd (array(
		'string'	=> $cd,
		'relative'	=> False,
		'relatives'	=> array (RELATIVE_NONE)
	)
);
$io = array ("" => "/test/test2/test3", "dir" => "/test/test2/test3/dir", "dir/file" => "/test/test2/test3/dir/file", "dir/dir2" => "/test/test2/test3/dir/dir2", "dir/dir2/file" => "/test/test2/test3/dir/dir2/file", "`~!@#$%^&*()-_=+/|[{]};:'\",<.>?" => "/test/test2/test3/`~!@#$%^&*()-_=+/|[{]};:'\",<.>?");

while (list ($i, $o) = each ($io))
{
	if (($ao = $phpgw->vfs->getabsolutepath (array ('string' => $i))) != $o)
	{
		echo "<br>$sequence_num - getabsolutepath - $cd - $i - $o - $ao";
	}
}

/* Actually means cd to home directory */
$sequence_num = 4;
$cd = "";
$phpgw->vfs->cd (array(
		'string'	=> $cd
	)
);
$relatives = array (RELATIVE_USER);
$io = array ("" => "$homedir", "dir" => "$homedir/dir", "dir/file" => "$homedir/dir/file", "`~!@#$%^&*()-_=+/|[{]};:'\",<.>?" => "$homedir/`~!@#$%^&*()-_=+/|[{]};:'\",<.>?");

while (list ($i, $o) = each ($io))
{
	if (($ao = $phpgw->vfs->getabsolutepath (array ('string' => $i, 'mask' => $relatives))) != $o)
	{
		echo '<br><b>HERE</b>';
		echo "<br>$sequence_num - getabsolutepath - $cd - $i - $relatives[0] - $o - $ao";
	}
}

/* $cd shouldn't affect this test, but we'll set it anyways */
$sequence_num = 5;
$cd = "test2/test4";
$phpgw->vfs->cd (array(
		'string'	=> $cd,
		'relative'	=> False,
		'relatives'	=> array (RELATIVE_NONE)
	)
);
$relatives = array (RELATIVE_NONE);
$io = array ("" => "", "dir" => "dir", "dir/file" => "dir/file", "`~!@#$%^&*()-_=+/|[{]};:'\",<.>?" => "`~!@#$%^&*()-_=+/|[{]};:'\",<.>?");

while (list ($i, $o) = each ($io))
{
	if (($ao = $phpgw->vfs->getabsolutepath (array ('string' => $i, 'mask' => $relatives))) != $o)
	{
		echo "<br>$sequence_num - getabsolutepath - $cd (shouldn't matter) - $i - $relatives[0] - $o - $ao";
	}
}

/* $cd shouldn't affect this test, but we'll set it anyways */
$sequence_num = 6;
$cd = "test3/test5";
$phpgw->vfs->cd (array(
		'string'	=> $cd,
		'relative'	=> False,
		'relatives'	=> array (RELATIVE_NONE)
	)
);
$relatives = array (RELATIVE_USER_APP);
$io = array ("" => "$homedir/.$currentapp", "dir" => "$homedir/.$currentapp/dir", "dir/dir2" => "$homedir/.$currentapp/dir/dir2", "dir/file" => "$homedir/.$currentapp/dir/file", "dir/dir2/dir3/file" => "$homedir/.$currentapp/dir/dir2/dir3/file", "`~!@#$%^&*()-_=+/|[{]};:'\",<.>?" => "$homedir/.$currentapp/`~!@#$%^&*()-_=+/|[{]};:'\",<.>?");

while (list ($i, $o) = each ($io))
{
	if (($ao = $phpgw->vfs->getabsolutepath (array ('string' => $i, 'mask' => $relatives))) != $o)
	{
		echo "<br>$sequence_num - getabsolutepath - $cd (shouldn't matter) - $i - $relatives[0] - $o - $ao";
	}
}

/* $cd shouldn't affect this test, but we'll set it anyways */
$sequence_num = 7;
$cd = "test4/test6";
$phpgw->vfs->cd (array(
		'string'	=> $cd,
		'relative'	=> False,
		'relatives'	=> array (RELATIVE_NONE)
	)
);
$relatives = array (RELATIVE_ROOT);
$io = array ("" => "", "dir" => "/dir", "/dir/dir2/dir3" => "/dir/dir2/dir3", "dir/file" => "/dir/file", "dir/dir2/dir3" => "/dir/dir2/dir3", "`~!@#$%^&*()-_=+/|[{]};:'\",<.>?" => "/`~!@#$%^&*()-_=+/|[{]};:'\",<.>?");

while (list ($i, $o) = each ($io))
{
	if (($ao = $phpgw->vfs->getabsolutepath (array ('string' => $i, 'mask' => $relatives))) != $o)
	{
		echo "<br>$sequence_num - getabsolutepath - $cd (shouldn't matter) - $i - $relatives[0] - $o - $ao";
	}
}

/* Now a few to test the VFS_REAL capabilities */
$sequence_num = 8;
$cd = "";
$phpgw->vfs->cd (array(
		'string'	=> $cd,
		'relative'	=> False,
		'relatives'	=> array (RELATIVE_NONE)
	)
);
$relatives = array (RELATIVE_ROOT|VFS_REAL);
$io = array ("" => "$filesdir", "dir" => "$filesdir$sep" . "dir", "dir/dir2/dir3" => "$filesdir$sep" . "dir$sep" . "dir2$sep" . "dir3", "dir/file" => "$filesdir$sep" . "dir$sep" . "file", "dir/dir2/dir3/file" => "$filesdir$sep" . "dir$sep" . "dir2$sep" . "dir3$sep" . "file", "`~!@#$%^&*()-_=+/|[{]};:'\",<.>?" => "$filesdir$sep" . "`~!@#$%^&*()-_=+$sep" . "|[{]};:'\",<.>?");

while (list ($i, $o) = each ($io))
{
	if (($ao = $phpgw->vfs->getabsolutepath (array ('string' => $i, 'mask' => $relatives, 'fake' =>False))) != $o)
	{
		echo "<br>$sequence_num - getabsolutepath - $cd (shouldn't matter) - $i - $relatives[0] - $o - $ao";
	}
}

$sequence_num = 9;
$cd = "test5/test7";
$phpgw->vfs->cd (array(
		'string'	=> $cd,
		'relative'	=> False,
		'relatives'	=> array (RELATIVE_NONE)
	)
);
$relatives = array (RELATIVE_USER|VFS_REAL);
$io = array ("" => "$filesdir$realhomedir", "dir" => "$filesdir$realhomedir$sep" . "dir", "dir/dir2/dir3" => "$filesdir$realhomedir$sep" . "dir$sep" . "dir2$sep" . "dir3", "dir/file" => "$filesdir$realhomedir$sep" . "dir$sep" . "file", "dir/dir2/dir3/file" => "$filesdir$realhomedir$sep" . "dir$sep" . "dir2$sep" . "dir3$sep" . "file", "`~!@#$%^&*()-_=+/|[{]};:'\",<.>?" => "$filesdir$realhomedir$sep" . "`~!@#$%^&*()-_=+$sep" . "|[{]};:'\",<.>?");

while (list ($i, $o) = each ($io))
{
	if (($ao = $phpgw->vfs->getabsolutepath (array ('string' => $i, 'mask' => $relatives, 'fake' => False))) != $o)
	{
		echo "<br>$sequence_num - getabsolutepath - $cd (shouldn't matter) - $i - $relatives[0] - $o - $ao";
	}
}

$sequence_num = 10;
$cd = "test6/test8";
$phpgw->vfs->cd (array(
		'string'	=> $cd,
		'relative'	=> False,
		'relatives'	=> array (RELATIVE_USER)
	)
);
/* RELATIVE_CURRENT should be implied */
$relatives = array (VFS_REAL);
$io = array ("" => "$filesdir$realhomedir$sep$cd", "dir" => "$filesdir$realhomedir$sep$cd$sep" . "dir", "dir/dir2/dir3" => "$filesdir$realhomedir$sep$cd$sep" . "dir$sep" . "dir2$sep" . "dir3", "dir/file" => "$filesdir$realhomedir$sep$cd$sep" . "dir$sep" . "file", "dir/dir2/dir3/file" => "$filesdir$realhomedir$sep$cd$sep" . "dir$sep" . "dir2$sep" . "dir3$sep" . "file", "`~!@#$%^&*()-_=+/|[{]};:'\",<.>?" => "$filesdir$realhomedir$sep$cd$sep" . "`~!@#$%^&*()-_=+$sep" . "|[{]};:'\",<.>?");

while (list ($i, $o) = each ($io))
{
	if (($ao = $phpgw->vfs->getabsolutepath (array ('string' => $i, 'mask' => $relatives, 'fake' => False))) != $o)
	{
		echo "<br>$sequence_num - getabsolutepath - $cd - $i - $relatives[0] - $o - $ao";
	}
}

$sequence_num = 11;
$cd = "test7/test9";
$phpgw->vfs->cd (array(
		'string'	=> $cd,
		'relative'	=> False,
		'relatives'	=> array (RELATIVE_USER)
	)
);
$relatives = array (RELATIVE_USER_APP|VFS_REAL);
$io = array ("" => "$filesdir$realhomedir$sep.$currentapp", "dir" => "$filesdir$realhomedir$sep.$currentapp$sep" . "dir", "dir/dir2/dir3" => "$filesdir$realhomedir$sep.$currentapp$sep" . "dir$sep" . "dir2$sep" . "dir3", "dir/file" => "$filesdir$realhomedir$sep.$currentapp$sep" . "dir$sep" . "file", "dir/dir2/dir3/file" => "$filesdir$realhomedir$sep.$currentapp$sep" . "dir$sep" . "dir2$sep" . "dir3$sep" . "file", "`~!@#$%^&*()-_=+/|[{]};:'\",<.>?" => "$filesdir$realhomedir$sep.$currentapp$sep`~!@#$%^&*()-_=+$sep" . "|[{]};:'\",<.>?");

while (list ($i, $o) = each ($io))
{
	if (($ao = $phpgw->vfs->getabsolutepath (array ('string' => $i, 'mask' => $relatives, 'fake' => False))) != $o)
	{
		echo "<br>$sequence_num - getabsolutepath - $cd (shouldn't matter) - $i - $relatives[0] - $o - $ao";
	}
}

# end of getabsolutepath tests
###

###
# start of path_parts tests

/* Just for convienience
$io = array ("" => array ("fake_full_path" => "", "fake_leading_dirs" => "", "fake_extra_path" => "", "fake_name" => "", "real_full_path" => "", "real_leading_dirs" => "", "real_extra_path" => "", "real_name" => ""));
`~!@#$%^&*()-_=+/|[{]};:'\",<.>?
*/

$sequence_num = 12;
$cd = "test8/test10";
$relatives[0] = RELATIVE_USER;
$phpgw->vfs->cd (array(
		'string'	=> $cd,
		'relative'	=> False,
		'relatives'	=> array ($relatives[0])
	)
);
$subhome = substr ($homedir, 1);
$io = array ("" => array ("fake_full_path" => "$homedir/$cd", "fake_leading_dirs" => "$homedir/test8", "fake_extra_path" => "$subhome/test8", "fake_name" => "test10", "real_full_path" => "$filesdir$homedir/$cd", "real_leading_dirs" => "$filesdir$homedir/test8", "real_extra_path" => "$subhome/test8", "real_name" => "test10"), "dir2/file" => array ("fake_full_path" => "$homedir/$cd/dir2/file", "fake_leading_dirs" => "$homedir/$cd/dir2", "fake_extra_path" => "$subhome/$cd/dir2", "fake_name" => "file", "real_full_path" => "$filesdir$homedir/$cd/dir2/file", "real_leading_dirs" => "$filesdir$homedir/$cd/dir2", "real_extra_path" => "$subhome/$cd/dir2", "real_name" => "file"), "`~!@#$%^&*()-_=+/|[{]};:'\",<.>?" => array ("fake_full_path" => "$homedir/$cd/`~!@#$%^&*()-_=+/|[{]};:'\",<.>?", "fake_leading_dirs" => "$homedir/$cd/`~!@#$%^&*()-_=+", "fake_extra_path" => "$subhome/$cd/`~!@#$%^&*()-_=+", "fake_name" => "|[{]};:'\",<.>?", "real_full_path" => "$filesdir$homedir/$cd/`~!@#$%^&*()-_=+/|[{]};:'\",<.>?", "real_leading_dirs" => "$filesdir$homedir/$cd/`~!@#$%^&*()-_=+", "real_extra_path" => "$subhome/$cd/`~!@#$%^&*()-_=+", "real_name" => "|[{]};:'\",<.>?"));

while (list ($i, $o) = each ($io))
{
	$ao = $phpgw->vfs->path_parts (array ('string' => $i));
	while (list ($key, $value) = each ($o))
	{
		if ($ao->$key != $o[$key])
		{
			echo "<br>$sequence_num - path_parts - $cd - $i - $relatives[0] - $key - $o[$key] - $ao[$key]";
		}
	}
}

# end of path_parts tests
###

$phpgw->vfs->cd ();

html_break (2);
html_text_bold ("The less output, the better.  Please file errors as a " . html_link ("https://sourceforge.net/tracker/?group_id=7305&atid=107305", "bug report", True, False) .  ". Be sure to include the system information line at the top, and anything special about your setup.  Thanks!");

html_page_close ();

?>
