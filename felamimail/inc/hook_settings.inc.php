<?php
	/**************************************************************************\
	* eGroupWare - Preferences                                                 *
	* http://www.egroupware.org                                                *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */
	$folderList = array();
	
	$this->bofelamimail =& CreateObject('felamimail.bofelamimail',$GLOBALS['egw']->translation->charset());
	if($this->bofelamimail->openConnection()) {
		$folderObjects = $this->bofelamimail->getFolderObjects(true, false);
		foreach($folderObjects as $folderName => $folderInfo) {
			#_debug_array($folderData);
			$folderList[$folderName] = $folderInfo->displayName;
		}
		$this->bofelamimail->closeConnection();
	}

	foreach($this->bofelamimail->autoFolders as $aname) {
		$availableAutoFolders[$aname] = lang($aname);
	}

	$config =& CreateObject('phpgwapi.config','felamimail');
	$config->read_repository();
	$felamimailConfig = $config->config_data;
	unset($config);

	$refreshTime = array(
		'0' => lang('disabled'),
		'1' => '1',
		'2' => '2',
		'3' => '3',
		'4' => '4',
		'5' => '5',
		'6' => '6',
		'7' => '7',
		'8' => '8',
		'9' => '9',
		'10' => '10',
		'15' => '15',
		'20' => '20',
		'30' => '30'
	);

	$prefAskForMove = array(
		'0' => lang('no'),
		'1' => lang('yes')
	);

	$prefAllowManageFolders = $prefAskForMove;

	$forwardOptions = array(
		'asmail' => lang('forward as attachment'),
		'inline' => lang('forward inline'),
	);

	$sortOrder = array(
		'0' => lang('date(newest first)'),
		'1' => lang('date(oldest first)'),
		'3' => lang('from(A->Z)'),
		'2' => lang('from(Z->A)'),
		'5' => lang('subject(A->Z)'),
		'4' => lang('subject(Z->A)'),
		'7' => lang('size(0->...)'),
		'6' => lang('size(...->0)')
	);

	$selectOptions = array(
		'0' => lang('no'),
		'1' => lang('yes'),
		'2' => lang('yes') . ' - ' . lang('small view')
	);

	$newWindowOptions = array(
		'1' => lang('only one window'),
		'2' => lang('allways a new window'),
	);

	$deleteOptions = array(
		'move_to_trash'		=> lang('move to trash'),
		'mark_as_deleted'	=> lang('mark as deleted'),
		'remove_immediately'	=> lang('remove immediately')
	);

	$composeOptions = array(
		'html'     => lang('html'),
		'text'   => lang('text/plain'),
	);

	$htmlOptions = array(
		'never_display'		=> lang('never display html emails'),
		'only_if_no_text'	=> lang('display only when no plain text is available'),
		'always_display'	=> lang('always show html emails'),
	);

	$rowOrderStyle = array(
		'felamimail'	=> lang('FeLaMiMail'),
		'outlook'	=> 'Outlook',
	);

	$trashOptions = array_merge(
		array(
			'none' => lang("Don't use Trash")
		),
		$folderList
	);

	$sentOptions = array_merge(
		array(
			'none' => lang("Don't use Sent")
		),
		$folderList
	);

	$draftOptions = array_merge(
		array(
			'none' => lang("Don't use draft folder")
		),
		$folderList
	);

    $templateOptions = array_merge(
        array(
            'none' => lang("Don't use template folder")
        ),
        $folderList
    );


	/* Settings array for this app */
	$GLOBALS['settings'] = array(
		'refreshTime' => array(
			'type'   => 'select',
			'label'  => 'Refresh time in minutes',
			'name'   => 'refreshTime',
			'values' => $refreshTime,
			'xmlrpc' => True,
			'admin'  => False
		),
       'prefaskformove' => array(
            'type'   => 'select',
            'label'  => 'Do you want to be asked for confirmation before moving selected messages to another folder?',
            'name'   => 'prefaskformove',
            'values' => $prefAskForMove,
            'xmlrpc' => True,
            'admin'  => False
        ),
       'prefpreventmanagefolders' => array(
            'type'   => 'select',
            'label'  => 'Do you want to prevent the managing of folders (creation, accessrights AND subscribtion)?',
            'name'   => 'prefpreventmanagefolders',
            'values' => $prefAllowManageFolders,
            'xmlrpc' => True,
            'admin'  => False
        ),
        'notavailableautofolders' => array(
            'type'   => 'multiselect',
            'label'  => 'which folders - in general - should NOT be automatically created, if not existing',
            'name'   => 'notavailableautofolders',
            'values' => $availableAutoFolders,
            'xmlrpc' => True,
            'admin'  => False
        ),		
		'sortOrder' => array(
			'type'   => 'select',
			'label'  => 'Default sorting order',
			'name'   => 'sortOrder',
			'values' => $sortOrder,
			'xmlrpc' => True,
			'admin'  => False
		),
		'rowOrderStyle' => array(
			'type'   => 'select',
			'label'  => 'row order style',
			'name'   => 'rowOrderStyle',
			'values' => $rowOrderStyle,
			'xmlrpc' => True,
			'admin'  => False
		),
        'message_forwarding' => array(
            'type'   => 'select',
            'label'  => 'how to forward messages',
            'name'   => 'message_forwarding',
            'values' => $forwardOptions,
            'xmlrpc' => True,
            'admin'  => False
        ),
		'mainscreen_showmail' => array(
			'type'   => 'select',
			'label'  => 'show new messages on main screen',
			'name'   => 'mainscreen_showmail',
			'values' => $selectOptions,
			'xmlrpc' => True,
			'admin'  => False
		),
		'mainscreen_showfolders' => array(
			'type'   => 'multiselect',
			'label'  => 'if shown, which folders should appear on main screen',
			'name'   => 'mainscreen_showfolders',
			'values' => $folderList,
			'xmlrpc' => True,
			'admin'  => False
		),
        'messages_showassent_0' => array(
            'type'   => 'multiselect',
            'label'  => 'which folders (additional to the Sent Folder) should be displayed using the Sent Folder View Schema',
            'name'   => 'messages_showassent_0',
            'values' => $folderList,
            'xmlrpc' => True,
            'admin'  => False
        ),
        'notify_folders' => array(
			'type'   => 'multiselect',
			'label'  => 'notify when new mails arrive on these folders',
			'name'   => 'notify_folders',
			'values' => $folderList,
			'xmlrpc' => True,
			'admin'  => False
		),
		'message_newwindow' => array(
			'type'   => 'select',
			'label'  => 'display messages in multiple windows',
			'name'   => 'message_newwindow',
			'values' => $newWindowOptions,
			'xmlrpc' => True,
			'admin'  => False
		),
		'deleteOptions' => array(
			'type'   => 'select',
			'label'  => 'when deleting messages',
			'name'   => 'deleteOptions',
			'values' => $deleteOptions,
			'xmlrpc' => True,
			'admin'  => False
		),
        'composeOptions' => array(
            'type'   => 'select',
            'label'  => 'start new messages with mime type plain/text or html?',
            'name'   => 'composeOptions',
            'values' => $composeOptions,
            'xmlrpc' => True,
            'admin'  => False
        ),
		'htmlOptions' => array(
			'type'   => 'select',
			'label'  => 'display of html emails',
			'name'   => 'htmlOptions',
			'values' => $htmlOptions,
			'xmlrpc' => True,
			'admin'  => False
		),
		'allowExternalIMGs' => array(
			'type'   => 'check',
			'label'  => 'allow images from external sources in html emails',
			'name'   => 'allowExternalIMGs',
			'xmlrpc' => True,
			'admin'  => True
		),
		'trashFolder' => array(
			'type'   => 'select',
			'label'  => 'trash folder',
			'name'   => 'trashFolder',
			'values' => $trashOptions,
			'xmlrpc' => True,
			'admin'  => False
		),
		'sentFolder' => array(
			'type'   => 'select',
			'label'  => 'sent folder',
			'name'   => 'sentFolder',
			'values' => $sentOptions,
			'xmlrpc' => True,
			'admin'  => False
		),
		'draftFolder' => array(
			'type'   => 'select',
			'label'  => 'draft folder',
			'name'   => 'draftFolder',
			'values' => $draftOptions,
			'xmlrpc' => True,
			'admin'  => False
		),
        'templateFolder' => array(
            'type'   => 'select',
            'label'  => 'template folder',
            'name'   => 'templateFolder',
            'values' => $templateOptions,
            'xmlrpc' => True,
            'admin'  => False
        ),
		'sieveScriptName' => array(
			'type'   => 'input',
			'label'  => 'sieve script name',
			'name'   => 'sieveScriptName',
			'xmlrpc' => True,
			'admin'  => False
		)
	);

?>
