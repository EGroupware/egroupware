<html>
	<head>
		<title>Form Test</title>
		<style>
			
			body, table, tr, th, td {
				font-family: Verdana;
				font-size: 9pt;
				background-color: aliceblue;
			}
			
			div.Savant-Form {
				margin: 8px;
			}
			
			fieldset.Savant-Form {
				margin: 8px;
				border-top:    1px solid silver;
				border-left:   1px solid silver;
				border-bottom: 1px solid gray;
				border-right:  1px solid gray;
				padding: 4px;
			}
			
			legend.Savant-Form{
				padding: 2px 4px;
				color: #036;
				font-weight: bold;
				font-size: 120%;
			}
			
			table.Savant-Form {
				border-spacing: 0px;
				margin: 0px;
				spacing: 0px;
				padding: 0px;
			}
			
			tr.Savant-Form {
			}
			
			th.Savant-Form {
				padding: 4px;
				spacing: 0px;
				border: 0px;
				text-align: right;
				vertical-align: top;
			}
			
			td.Savant-Form {
				padding: 4px;
				spacing: 0px;
				border: 0px;
				text-align: left;
				vertical-align: top;
			}
			
			label.Savant-Form {
				font-weight: bold;
			}
			
			input[type="text"] {
				font-family: monospace;
				font-size: 9pt;
			}
			
			textarea {
				font-family: monospace;
				font-size: 9pt;
			}
			
		</style>
	</head>
	<body>
		<?php
		
			// start a form and set a property
			$this->form('start');
			$this->form('set', 'class', 'Savant-Form');
			
			// add a hidden value before the layout
			$this->form('hidden', 'hideme', 'hidden & valued');
			
			// NEW BLOCK
			$this->form('block', 'start', "First Section", 'row');
			
			// text field
			$this->form('text', 'mytext', $this->mytext, 'Enter some text here:', null);
			
			// messages for the text field
			$this->form('note', null, null, $this->valid['mytext'],
				array('required' => 'This field is required.', 'maxlen' => 'No more than 5 letters.', 'no_digits' => 'No digits allowed.'));
			
			$this->form('block', 'split');
			
			// checkbox with default value (array(checked, not-checked))
			$this->form('checkbox', 'xbox', $this->xbox, 'Check this:', array(1,0), 'style="text-align: center;"');
			
			// single select
			$this->form('select', 'picker', $this->picker, 'Pick one:', $this->opts);
			
			// END THE BLOCK and put in some custom stuff.
			$this->form('block', 'end');
		?>
		
		<!-- the "clear: both;" is very important when you have floating elements -->
		<h1 style="clear: both; background-color: silver; border: 1px solid black; margin: 4px; padding: 4px;">Custom HTML Between Fieldset Blocks</h1>
		
		<?php
			// NEW BLOCK
			$this->form('block', 'start', "Second Section", 'col');
			
			// multi-select with note
			$this->form('group', 'start', 'Pick many:');
			$this->form('select', 'picker2[]', $this->picker2, 'Pick many:', $this->opts, 'multiple="multiple"');
			$this->form('note', "<br />Pick as many as you like; use the Ctrl key on Windows, or the Cmd key on Macintosh.");
			$this->form('group', 'end');
			
			// radio buttons
			$this->form('radio', 'chooser', $this->chooser, 'Choose one:', $this->opts);
			
			// NEW BLOCK
			$this->form('block', 'start', null, 'row');
			
			// text area
			$this->form('textarea', 'myarea', $this->myarea, 'Long text:', array('rows'=>12,'cols'=>40));
			
			// NEW BLOCK (clears floats)
			$this->form('block', 'start', null, 'row', null, 'both');
			$this->form('submit', 'op', 'Save');
			$this->form('reset', 'op', 'Reset');
			$this->form('button', '', 'Click Me!', null, array('onClick' => 'return alert("hello!")'));
			
			
			// end the form
			$this->form('end');
		?>
		
	</body>
</html>