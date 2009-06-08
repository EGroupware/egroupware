<?php
error_reporting(E_ALL);

require_once 'Savant2.php';
$Savant2 = new Savant2();
$Savant2->addPath('template', 'templates/');
$Savant2->addPath('resource', 'resources/');

$defaults = array(
	'hideme' => null,
	'mytext' => null,
	'xbox' => null,
	'picker' => null,
	'picker2' => null,
	'chooser' => null,
	'myarea' => null
);

$values = array_merge($defaults, $_POST);

$tmp = array();

if ($values['mytext'] == '') {
	// required
	$tmp[] = 'required';
}

if (strlen($values['mytext']) > 5) {
	// max 5 chars
	$tmp[] = 'maxlen';
}

if (preg_match('/[0-9]+/', $values['mytext'])) {
	// no digits
	$tmp[] = 'no_digits';
}

if (count($tmp) == 0) {
	$valid = array('mytext' => true);
} else {
	$valid = array('mytext' => $tmp);
}

$Savant2->assign('opts', array('one', 'two', 'three', 'four', 'five'));
$Savant2->assign($values);
$Savant2->assign('valid', $valid);

$Savant2->display('form2.tpl.php');
?>