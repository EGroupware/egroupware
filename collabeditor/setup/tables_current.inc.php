<?php
/**
 * EGroupware - Collabeditor - Setup
 *
 * @link http://www.egroupware.org
 * @package collabeditor
 * @author Hadi Nategh <hn-AT-egroupware.de>
 * @copyright (c) 2016 by Hadi Nategh <hn-AT-egroupware.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

$phpgw_baseline = array(
	'egw_collab_member' => array(
		'fd' => array(
			'collab_member_id' => array('type' => 'auto','nullable' => False, 'comment' => 'Unique per user and session'),
			'collab_es_id' => array('type' => 'varchar','precision' => '64','nullable' => False, 'comment' => 'Related editing session id'),
			'collab_uid' => array('type' => 'varchar','precision' => '64'),
			'collab_color' => array('type' => 'varchar','precision' => '32'),
			'collab_is_active' => array('type' => 'int','precision' => '2', 'default'=>'0','nullable' => False),
			'collab_is_guest' => array('type' => 'int','precision' => '2','default' => '0','nullable' => False),
			'collab_token' => array('type' => 'varchar','precision' => '32'),
			'collab_status' => array('type' => 'int','precision' => '2','default' => '1','nullable' => False)
		),
		'pk' => array('collab_member_id'),
		'fk' => array(),
		'ix' => array(),
		'uc' => array()
	),
	'egw_collab_op' => array(
		'fd' => array(
			'collab_seq' => array('type' => 'auto','nullable' => False, 'comment' => 'Sequence number'),
			'collab_es_id' => array('type' => 'varchar','precision' => '64','nullable' => False, 'comment' => 'Editing session id'),
			'collab_member' => array('type' => 'int','precision' => '4','default' => '1','nullable' => False, 'comment' => 'User and time specific'),
			'collab_optype' => array('type' => 'varchar','precision' => '64', 'comment' => 'Operation type'),
			'collab_opspec' => array('type' => 'longtext', 'comment' => 'json-string')
		),
		'pk' => array('collab_seq'),
		'fk' => array(),
		'ix' => array(),
		'uc' => array()
	),
	'egw_collab_session' => array(
		'fd' => array(
			'collab_es_id' => array('type' => 'varchar','precision' => '64','nullable' => False, 'comment' => 'Editing session id'),
			'collab_genesis_url' => array('type' => 'varchar','precision' => '512', 'comment' => 'Relative to owner documents storage /template.odt'),
			'account_id' => array('type' => 'int','meta' => 'user','precision' => '4','nullable' => False, 'comment' => 'user who created the session'),
			'collab_last_save' => array('type' => 'int','meta' => 'timestamp','precision' => '8','comment' => 'timestamp of the last save')
		),
		'pk' => array('collab_es_id'),
		'fk' => array(),
		'ix' => array(),
		'uc' => array()
	)
);
