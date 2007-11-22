<?php
/**
 * eGroupWare  eTemplate Extension - Email addresses
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 * @version $Id$
 */

/**
 * eGroupWare  eTemplate Extension - Email addresses
 *
 * The value is an RFC822 email address or an interger account_id.
 * If the value is empty or 0, the content of the options field is used instead.
 * The address get displayed as name with the email as tooltip and a link to compose a mail.
 */
class email_widget
{
	/** 
	 * exported methods of this class
	 * 
	 * @var array
	 */
	var $public_functions = array(
		'pre_process' => True,
	);
	/**
	 * availible extensions and there names for the editor
	 * @var string
	 */
	var $human_name = array(
		'email-address' => 'email address',
	);

	/**
	 * Constructor of the extension
	 *
	 * @param string $ui '' for html
	 */
	function select_widget($ui)
	{
		$this->ui = $ui;
	}

	/**
	 * pre-processing of the extension
	 *
	 * This function is called before the extension gets rendered
	 *
	 * @param string $name form-name of the control
	 * @param mixed &$value value / existing content, can be modified
	 * @param array &$cell array with the widget, can be modified for ui-independent widgets 
	 * @param array &$readonlys names of widgets as key, to be made readonly
	 * @param mixed &$extension_data data the extension can store persisten between pre- and post-process
	 * @param object &$tmpl reference to the template we belong too
	 * @return boolean true if extra label is allowed, false otherwise
	 */
	function pre_process($name,&$value,&$cell,&$readonlys,&$extension_data,&$tmpl)
	{
		if (!$value) $value = $cell['size'];
		
		if (is_numeric($value))
		{
			$email = $GLOBALS['egw']->accounts->id2name($value,'account_email');
			$value = $GLOBALS['egw']->accounts->id2name($value,'account_fullname');
		}
		elseif (preg_match('/^(.*) ?<(.*)>/',$value,$matches))
		{
			$value = $matches[1];
			$email = $matches[2];
		}
		elseif (strpos($email=$value,'@') !== false)
		{
			list($value) = explode('@',$value);	// cut the domain off to get a shorter name, we show the complete email as tooltip
			$value .= '@...';
		}
		$link = $this->email2link($email);
		//echo "<p>value='$value', email='$email', link=".print_r($link,true)."</p>\n";
		
		$cell['type'] = 'label';
		$cell['size'] = ','.$link.',,,,'.($link[0]=='f'?'700x750':'').($value != $email ? ','.$email : '');
		$cell['no_lang'] = 1;

		return True;	// extra Label Ok
	}

	/**
	 * convert email-address in compose link
	 *
	 * @param string $email email-addresse
	 * @return array/string array with get-params or mailto:$email, or '' or no mail addresse
	 */
	function email2link($email)
	{
		if (strpos($email,'@') == false) return '';

		if($GLOBALS['egw_info']['user']['apps']['felamimail'])
		{
			return 'felamimail.uicompose.compose&send_to='.base64_encode($email);
		}
		if($GLOBALS['egw_info']['user']['apps']['email'])
		{
			return 'email.uicompose.compose&to='.$email;
		}
		return 'mailto:' . $email;
	}
}
