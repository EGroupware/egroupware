<?php
class etemplate_table_test
{
	var $public_functions = array(
		'index' => true,
	);

	function index(array $content=null, $msg='')
	{
		$tmpl = new etemplate_new('etemplate.table_test');
		$content = array();
		$tmpl->exec('etemplate.etemplate_table_test.index', $content);
	}
}
