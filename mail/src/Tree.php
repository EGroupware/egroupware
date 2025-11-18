<?php

namespace EGroupware\Mail;

/**
 * All mail Tree server-side weirdness goes in here
 */
class Tree extends \EGroupware\Api\Etemplate\Widget\Tree
{
	const ID = 'id';
	const LABEL = 'text';
	const TOOLTIP = 'tooltip';
	const CHILDREN = 'item';
	const AUTOLOAD_CHILDREN = 'child';
}