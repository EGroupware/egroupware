<?

$phpgw_info["flags"] = array("currentapp" => "phpwebhosting",
                                "noheader" => False,
                                "noappheader" => False,
                                "enable_vfs_class" => True);

include("../header.inc.php");

/*
	General format for output is:
	function - current directory - input[...] - what output should be - what output was
*/

html_break (1);
html_text_italic (PHP_OS . " - " . $phpgw_info["server"]["db_type"] . " - " . PHP_VERSION);
html_break (1);

###
# start of getabsolutepath tests

$sep = $phpgw_info["server"]["dir_separator"];
$user = $phpgw->vfs->working_lid;
$homedir = $phpgw->vfs->fakebase . "/" . $user;
$filesdir = $phpgw->vfs->basedir;
$currentapp = $phpgw_info["flags"]["currentapp"];

$phpgw->vfs->cd ();
$io = array ("" => "$homedir", "dir" => "$homedir/dir", "dir/file" => "$homedir/dir/file", "dir/dir2" => "$homedir/dir/dir2", "dir/dir2/file" => "$homedir/dir/dir2/file", "`~!@#$%^&*()-_=+/|[{]};:'\",<.>?" => "$homedir/`~!@#$%^&*()-_=+/|[{]};:'\",<.>?");

while (list ($i, $o) = each ($io))
{
	if (($ao = $phpgw->vfs->getabsolutepath ($i)) != $o)
	{
		echo "<br>getabsolutepath - $cd - $i - $o - $ao";
	}
}

$cd = "test";
$phpgw->vfs->cd ($cd, False, array (RELATIVE_NONE));
$io = array ("" => "/test", "dir" => "/test/dir", "dir/file" => "/test/dir/file", "dir/dir2" => "/test/dir/dir2", "dir/dir2/file" => "/test/dir/dir2/file", "`~!@#$%^&*()-_=+/|[{]};:'\",<.>?" => "/test/`~!@#$%^&*()-_=+/|[{]};:'\",<.>?");

while (list ($i, $o) = each ($io))
{
	if (($ao = $phpgw->vfs->getabsolutepath ($i)) != $o)
	{
		echo "<br>getabsolutepath - $cd - $i - $o - $ao";
	}
}

$cd = "test/test2/test3";
$phpgw->vfs->cd ($cd, False, array (RELATIVE_NONE));
$io = array ("" => "/test/test2/test3", "dir" => "/test/test2/test3/dir", "dir/file" => "/test/test2/test3/dir/file", "dir/dir2" => "/test/test2/test3/dir/dir2", "dir/dir2/file" => "/test/test2/test3/dir/dir2/file", "`~!@#$%^&*()-_=+/|[{]};:'\",<.>?" => "/test/test2/test3/`~!@#$%^&*()-_=+/|[{]};:'\",<.>?");

while (list ($i, $o) = each ($io))
{
	if (($ao = $phpgw->vfs->getabsolutepath ($i)) != $o)
	{
		echo "<br>getabsolutepath - $cd - $i - $o - $ao";
	}
}

/* Actually means cd to home directory */
$cd = "";
$phpgw->vfs->cd ($cd);
$relatives = array (RELATIVE_USER);
$io = array ("" => "$homedir", "dir" => "$homedir/dir", "dir/file" => "$homedir/dir/file", "`~!@#$%^&*()-_=+/|[{]};:'\",<.>?" => "$homedir/`~!@#$%^&*()-_=+/|[{]};:'\",<.>?");

while (list ($i, $o) = each ($io))
{
	if (($ao = $phpgw->vfs->getabsolutepath ($i, $relatives)) != $o)
	{
		echo "<br>getabsolutepath - $cd - $i - $relatives[0] - $o - $ao";
	}
}

/* $cd shouldn't affect this test, but we'll set it anyways */
$cd = "test2/test4";
$phpgw->vfs->cd ($cd, False, array (RELATIVE_NONE));
$relatives = array (RELATIVE_NONE);
$io = array ("" => "", "dir" => "dir", "dir/file" => "dir/file", "`~!@#$%^&*()-_=+/|[{]};:'\",<.>?" => "`~!@#$%^&*()-_=+/|[{]};:'\",<.>?");

while (list ($i, $o) = each ($io))
{
	if (($ao = $phpgw->vfs->getabsolutepath ($i, $relatives)) != $o)
	{
		echo "<br>getabsolutepath - $cd (shouldn't matter) - $i - $relatives[0] - $o - $ao";
	}
}

/* $cd shouldn't affect this test, but we'll set it anyways */
$cd = "test3/test5";
$phpgw->vfs->cd ($cd, False, array (RELATIVE_NONE));
$relatives = array (RELATIVE_USER_APP);
$io = array ("" => "$homedir/.$currentapp", "dir" => "$homedir/.$currentapp/dir", "dir/dir2" => "$homedir/.$currentapp/dir/dir2", "dir/file" => "$homedir/.$currentapp/dir/file", "dir/dir2/dir3/file" => "$homedir/.$currentapp/dir/dir2/dir3/file", "`~!@#$%^&*()-_=+/|[{]};:'\",<.>?" => "$homedir/.$currentapp/`~!@#$%^&*()-_=+/|[{]};:'\",<.>?");

while (list ($i, $o) = each ($io))
{
	if (($ao = $phpgw->vfs->getabsolutepath ($i, $relatives)) != $o)
	{
		echo "<br>getabsolutepath - $cd (shouldn't matter) - $i - $relatives[0] - $o - $ao";
	}
}

/* $cd shouldn't affect this test, but we'll set it anyways */
$cd = "test4/test6";
$phpgw->vfs->cd ($cd, False, array (RELATIVE_NONE));
$relatives = array (RELATIVE_ROOT);
$io = array ("" => "", "dir" => "/dir", "/dir/dir2/dir3" => "/dir/dir2/dir3", "dir/file" => "/dir/file", "dir/dir2/dir3" => "/dir/dir2/dir3", "`~!@#$%^&*()-_=+/|[{]};:'\",<.>?" => "/`~!@#$%^&*()-_=+/|[{]};:'\",<.>?");

while (list ($i, $o) = each ($io))
{
	if (($ao = $phpgw->vfs->getabsolutepath ($i, $relatives)) != $o)
	{
		echo "<br>getabsolutepath - $cd (shouldn't matter) - $i - $relatives[0] - $o - $ao";
	}
}

/* Now a few to test the VFS_REAL capabilities */
$cd = "";
$phpgw->vfs->cd ($cd, False, array (RELATIVE_NONE));
$relatives = array (RELATIVE_ROOT|VFS_REAL);
$io = array ("" => "$filesdir", "dir" => "$filesdir/dir", "dir/dir2/dir3" => "$filesdir/dir/dir2/dir3", "dir/file" => "$filesdir/dir/file", "dir/dir2/dir3/file" => "$filesdir/dir/dir2/dir3/file", "`~!@#$%^&*()-_=+/|[{]};:'\",<.>?" => "$filesdir/`~!@#$%^&*()-_=+/|[{]};:'\",<.>?");

while (list ($i, $o) = each ($io))
{
	if (($ao = $phpgw->vfs->getabsolutepath ($i, $relatives, False)) == $o)
	{
		echo "<br>getabsolutepath - $cd (shouldn't matter) - $i - $relatives[0] - $o - $ao";
	}
}

$cd = "test5/test7";
$phpgw->vfs->cd ($cd, False, array (RELATIVE_NONE));
$relatives = array (RELATIVE_USER|VFS_REAL);
$io = array ("" => "$filesdir$homedir", "dir" => "$filesdir$homedir/dir", "dir/dir2/dir3" => "$filesdir$homedir/dir/dir2/dir3", "dir/file" => "$filesdir$homedir/dir/file", "dir/dir2/dir3/file" => "$filesdir$homedir/dir/dir2/dir3/file", "`~!@#$%^&*()-_=+/|[{]};:'\",<.>?" => "$filesdir$homedir/`~!@#$%^&*()-_=+/|[{]};:'\",<.>?");

while (list ($i, $o) = each ($io))
{
	if (($ao = $phpgw->vfs->getabsolutepath ($i, $relatives, False)) != $o)
	{
		echo "<br>getabsolutepath - $cd (shouldn't matter) - $i - $relatives[0] - $o - $ao";
	}
}

$cd = "test6/test8";
$phpgw->vfs->cd ($cd, False, array (RELATIVE_USER));
/* RELATIVE_CURRENT should be implied */
$relatives = array (VFS_REAL);
$io = array ("" => "$filesdir$homedir/$cd", "dir" => "$filesdir$homedir/$cd/dir", "dir/dir2/dir3" => "$filesdir$homedir/$cd/dir/dir2/dir3", "dir/file" => "$filesdir$homedir/$cd/dir/file", "dir/dir2/dir3/file" => "$filesdir$homedir/$cd/dir/dir2/dir3/file", "`~!@#$%^&*()-_=+/|[{]};:'\",<.>?" => "$filesdir$homedir/$cd/`~!@#$%^&*()-_=+/|[{]};:'\",<.>?");

while (list ($i, $o) = each ($io))
{
	if (($ao = $phpgw->vfs->getabsolutepath ($i, $relatives, False)) != $o)
	{
		echo "<br>getabsolutepath - $cd - $i - $relatives[0] - $o - $ao";
	}
}

$cd = "test7/test9";
$phpgw->vfs->cd ($cd, False, array (RELATIVE_USER));
$relatives = array (RELATIVE_USER_APP|VFS_REAL);
$io = array ("" => "$filesdir$homedir/.$currentapp", "dir" => "$filesdir$homedir/.$currentapp/dir", "dir/dir2/dir3" => "$filesdir$homedir/.$currentapp/dir/dir2/dir3", "dir/file" => "$filesdir$homedir/.$currentapp/dir/file", "dir/dir2/dir3/file" => "$filesdir$homedir/.$currentapp/dir/dir2/dir3/file", "`~!@#$%^&*()-_=+/|[{]};:'\",<.>?" => "$filesdir$homedir/.$currentapp/`~!@#$%^&*()-_=+/|[{]};:'\",<.>?");

while (list ($i, $o) = each ($io))
{
	if (($ao = $phpgw->vfs->getabsolutepath ($i, $relatives, False)) != $o)
	{
		echo "<br>getabsolutepath - $cd (shouldn't matter) - $i - $relatives[0] - $o - $ao";
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

$cd = "test8/test10";
$relatives[0] = RELATIVE_USER;
$phpgw->vfs->cd ($cd, False, array ($relatives[0]));
$subhome = substr ($homedir, 1);
$io = array ("" => array ("fake_full_path" => "$homedir/$cd", "fake_leading_dirs" => "$homedir/test8", "fake_extra_path" => "$subhome/test8", "fake_name" => "test10", "real_full_path" => "$filesdir$homedir/$cd", "real_leading_dirs" => "$filesdir$homedir/test8", "real_extra_path" => "$subhome/test8", "real_name" => "test10"), "dir2/file" => array ("fake_full_path" => "$homedir/$cd/dir2/file", "fake_leading_dirs" => "$homedir/$cd/dir2", "fake_extra_path" => "$subhome/$cd/dir2", "fake_name" => "file", "real_full_path" => "$filesdir$homedir/$cd/dir2/file", "real_leading_dirs" => "$filesdir$homedir/$cd/dir2", "real_extra_path" => "$subhome/$cd/dir2", "real_name" => "file"), "`~!@#$%^&*()-_=+/|[{]};:'\",<.>?" => array ("fake_full_path" => "$homedir/$cd/`~!@#$%^&*()-_=+/|[{]};:'\",<.>?", "fake_leading_dirs" => "$homedir/$cd/`~!@#$%^&*()-_=+", "fake_extra_path" => "$subhome/$cd/`~!@#$%^&*()-_=+", "fake_name" => "|[{]};:'\",<.>?", "real_full_path" => "$filesdir$homedir/$cd/`~!@#$%^&*()-_=+/|[{]};:'\",<.>?", "real_leading_dirs" => "$filesdir$homedir/$cd/`~!@#$%^&*()-_=+", "real_extra_path" => "$subhome/$cd/`~!@#$%^&*()-_=+", "real_name" => "|[{]};:'\",<.>?"));

while (list ($i, $o) = each ($io))
{
	$ao = $phpgw->vfs->path_parts ($i);
	while (list ($key, $value) = each ($o))
	{
		if ($ao->$key != $o[$key])
		{
			echo "<br>path_parts - $cd - $i - $relatives[0] - $key - $o[$key] - $ao[$key]";
		}
	}
}

# end of path_parts tests
###

html_break (2);
html_text_bold ("The less output, the better.  Please send errors to the current VFS maintainer (NOT the PHPWebHosting maintainer (although they may be one in the same)).  Thanks!");

html_page_close ();

?>
