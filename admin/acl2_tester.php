<?php
	$GLOBALS['phpgw_info']['flags']['currentapp'] = 'admin';
	include('../header.inc.php');
	include(PHPGW_API_INC . '/class.acl2.inc.php');
	//$sec = New acl(array('account_id'=>1));
	$sec = New acl2(1,'##DEFAULT##');
//echo 'phpgw:<pre>'; print_r($GLOBALS['phpgw']); echo '</pre>';
	$sec->get_memberships();
//echo 'memberships_sql: '.$sec->memberships_sql.'<br>';
//echo 'memberships:<pre>'; print_r($sec->memberships); echo '</pre>';
	function ttt($location, $rights)
	{
		GLOBAL $sec;
		if ($sec->check($location, $rights))
		{
			echo $rights.' is valid<br>';
		}
		else
		{
			echo $rights.' is invalid<br>';
		}
	}

	echo 'This test is going to delete all your phpgw_acl2 records to ensure that the tests run as expected.<br>';
	$GLOBALS['phpgw']->db->query('DELETE FROM phpgw_acl2',__LINE__,__FILE__);
	echo 'Action: DELETE FROM phpgw_acl2<br><br>';
	echo 'Running checks on .one.two.three after changing directly granted rights as well as ones it will inherit from<br>';
	
	echo '<br>1: check rights for .one.two which will get inherited by .one.two.three<br>';
	ttt('.one.two', 1);
	ttt('.one.two', 2);
	ttt('.one.two', 4);
	ttt('.one.two', 8);
	echo 'You can see that no rights are set, so none will be inherited<br>';

	echo '<br>2: checking .one.two.three<br>';
	ttt('.one.two.three', 1);
	ttt('.one.two.three', 2);
	ttt('.one.two.three', 4);
	ttt('.one.two.three', 8);
	echo 'You can see that no rights are set directly as well<br>';

	echo '<br>3: add rights 4 to .one.two.three<br>';
	echo 'Action: $acl2->add(\'.one.two.three\',4,0);<br>';
	$sec->add('.one.two.three',4,0);
	ttt('.one.two.three', 1);
	ttt('.one.two.three', 2);
	ttt('.one.two.three', 4);
	ttt('.one.two.three', 8);
	
	echo '<br>4: add rights 8 to .one.two.three<br>';
	echo 'Action: $acl2->add(\'.one.two.three\',8,0);<br>';
	$sec->add('.one.two.three',8,0);
	ttt('.one.two.three', 1);
	ttt('.one.two.three', 2);
	ttt('.one.two.three', 4);
	ttt('.one.two.three', 8);

	echo '<br>5: remove rights 4 from .one.two.three<br>';
	echo 'Action: $acl2->remove(\'.one.two.three\',4,0);<br>';
	$sec->remove('.one.two.three',4,0);
	ttt('.one.two.three', 1);
	ttt('.one.two.three', 2);
	ttt('.one.two.three', 4);
	ttt('.one.two.three', 8);

	echo '<br>5: set rights to 2 on .one.two.three<br>';
	echo 'Action: $acl2->set(\'.one.two.three\', 2,0);<br>';
	$sec->set('.one.two.three', 2,0);
	ttt('.one.two.three', 1);
	ttt('.one.two.three', 2);
	ttt('.one.two.three', 4);
	ttt('.one.two.three', 8);

	echo '<br>Now to see inheritance in action...<br>';
	echo '6: add rights 8 to .one.two<br>';
	echo 'Action: $acl2->add(\'.one.two\',8,0);<br>';
	$sec->add('.one.two',8,0);
	ttt('.one.two.three', 1);
	ttt('.one.two.three', 2);
	ttt('.one.two.three', 4);
	ttt('.one.two.three', 8);
	echo 'You can see here that it has inherited rights 8 from .one.two<br>';

	echo '<br>7: add rights 4 to .one.two<br>';
	echo 'Action: $acl2->add(\'.one.two\',4,0);<br>';
	$sec->add('.one.two',4,0);
	ttt('.one.two.three', 1);
	ttt('.one.two.three', 2);
	ttt('.one.two.three', 4);
	ttt('.one.two.three', 8);
	echo 'You can see here that it has also inherited rights 4 from .one.two<br>';

	echo '<br>Now to see inherited rights masks in action...<br>';
	echo '8: add rights mask for 8 to .one.two<br>';
	echo 'Action: $acl2->add(\'.one.two\',8,1);<br>';
	$sec->add('.one.two',8,1);
	ttt('.one.two.three', 1);
	ttt('.one.two.three', 2);
	ttt('.one.two.three', 4);
	ttt('.one.two.three', 8);
	echo 'You can see here that it no longer inherited rights 8 from .one.two<br>';
	
	echo '<br>It will help to see the rights for .one.two at this point to clearly see the rights mask doing its work<br>';
	echo '9: display rights for .one.two<br>';
	ttt('.one.two', 1);
	ttt('.one.two', 2);
	ttt('.one.two', 4);
	ttt('.one.two', 8);
	echo 'You can see here that it has rights for 4 and 8, and yet above you saw that .one.two.three did not inherited rights 4 from it<br>';

	//echo 'rights_cache:<pre>'; print_r($sec->rights_cache); echo '</pre>';
?>
