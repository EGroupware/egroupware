<?php
	/**
	 * eGroupWare - eTemplate Extension - XSLT Widget
	 * Originaly from the phpGW XSLT API
	 *
	 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
	 * @package etemplate
	 * @link http://www.egroupware.org
	 * @author Ralf Becker <RalfBecker@outdoor-training.de>
	 * @version $Id$
	 */

	/**
	 * @author ralfbecker
	 * widget that generates its html-output via a xslt file with its in $options and the content as xml
	 *
	 * The following data is placed in the xml: value,name,label(translated),statustext(translated),readonly
	 * and all widget-attributes as descript in the referenz, using there xml-names.
	 * This widget is generating html, so it does not work (without an extra implementation) in an other UI
	 */
	class xslt_widget
	{
		var $public_functions = array(
			'pre_process' => True,
			'render' => True,
			'post_process' => True
		);
		var $human_name = 'XSLT Template';	// this is the name for the editor

		function xslt_widget($ui='')
		{
			$this->xslttemplates =& CreateObject('phpgwapi.xslttemplates',EGW_INCLUDE_ROOT);

			switch($ui)
			{
				case '':
				case 'html':
					$this->ui = 'html';
					break;
				default:
					echo "UI='$ui' not implemented";
			}
			return 0;
		}

		function pre_process($name,&$value,&$cell,&$readonlys,&$extension_data,&$tmpl)
		{
			return False;	// no extra label
		}

		function render(&$cell,$form_name,&$value,$readonly,&$extension_data,&$tmpl)
		{
			//echo "<p>xslt_widget::render: name='$cell[name]', name='$form_name', value='$value'</p>";
			$func = 'render_'.$this->ui;

			if (!method_exists($this,$func))
				return False;

			return $this->$func($cell,$form_name,$value,$readonly,$tmpl);
		}

		function render_html($cell,$form_name,$value,$readonly,&$tmpl)
		{
			list($app,$file) = split('\\.',$cell['size'],2);
			$pref_templ = $GLOBALS['egw_info']['server']['template_set'];
			$path = "$app/templates/$pref_templ/$file";
			if (!file_exists(EGW_SERVER_ROOT.'/'.$path.'.xsl'))
			{
				$path = "$app/templates/default/$file";
			}
			$this->xslttemplates->add_file($path);

			$this->xslttemplates->set_var('value',$value);
			$this->xslttemplates->set_var('name',$form_name);
			$this->xslttemplates->set_var('readonly',$readonly);
			$this->xslttemplates->set_var('label',$cell['no_lang'] ? $cell['label'] : lang($cell['label']));
			$this->xslttemplates->set_var('statustext',lang($cell['help']));
			list($span,$class) = explode(',',$cell['span']);
			list($src,$options) = explode(',',$cell['size']);
			$this->xslttemplates->set_var('attr',array(
				'id'       => $cell['name'],
				'label'    => $cell['label'],
				'statustext' => $cell['help'],
				'no_lang'  => $cell['no_lang'],
				'needed'   => $cell['needed'],
				'readonly' => $cell['readonly'],
				'onchange' => $cell['onchange'],
				'span'     => $span,
				'class'    => $class,
				'src'      => $src,
				'options'  => $options,
				'align'  => $cell['align']
			));
			return $this->xslttemplates->parse();
		}

		function post_process($name,&$value,&$extension_data,&$loop,&$tmpl,$value_in)
		{
			//echo "<p>xslt_widget.post_process: $name = "; _debug_array($value_in);

			$value = $value_in;
		}
	}
