<?php

	$phpgw_baseline = array(
		'phpgw_et_media' => array(
			'fd' => array(
				'id' => array('type' => 'auto','nullable' => False),
				'name' => array('type' => 'varchar','precision' => '100','nullable' => False),
				'author' => array('type' => 'varchar','precision' => '100','nullable' => False),
				'descr' => array('type' => 'text','nullable' => False),
				'type' => array('type' => 'varchar','precision' => '20','nullable' => False)
			),
			'pk' => array('id'),
			'fk' => array(),
			'ix' => array(),
			'uc' => array()
		)
	);
