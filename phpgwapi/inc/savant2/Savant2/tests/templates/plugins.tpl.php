<?php
/**
* 
* Tests the default and example plugins for Savant.
* 
* @version $Id$
*
*/
?>


<h1>ahref</h1>

<h2>from strings</h2>
<pre>
<?php
	$result = $this->splugin('ahref', 'http://example.com/index.html?this=this&that=that#something', 'Example Link', 'target="_blank"');
	echo '<p>' . $this->splugin('modify', $result, 'htmlentities nl2br') . '</p>';
	echo "<p>$result</p>";
?>
</pre>

<h2>from arrays</h2>
<pre>
<?php
	$result = $this->splugin(
		'ahref',
		parse_url('http://example.com/index.html?this=this&that=that#something'),
		'Example Link',
		array('target' => "_blank")
	);
	echo '<p>' . $this->splugin('modify', $result, 'htmlentities nl2br') . '</p>';
	echo "<p>$result</p>";
?>
</pre>


<h1>checkbox</h1>

<pre>
<?php
	$result = '';
	foreach ($this->set as $key => $val) {
		$result .= $this->splugin(
			'checkbox', // plugin 
			"xboxen[$key]", // checkbox name
			$key, // checkbox value
			'key1', // pre-checked
			'', // default value when not checked
			'dumb="dumber"' // attributes
		);
		$result .= $val . "<br /><br />\n";
	}
	
	$this->plugin('modify', $result, 'htmlentities nl2br');
 ?>
</pre>

<form><?php echo $result ?></form>


<h1>cycle</h1>


<h2>repeat 1 on array</h2>
<pre>
<?php for ($i = 0; $i < 9; $i++): ?>
	<?php $this->plugin('cycle', array('a', 'b', 'c'), $i) ?><br />
<?php endfor; ?>
</pre>





<h2>repeat 3 on preset</h2>


<pre>
<?php for ($i = 0; $i < 12; $i++): ?>
	<?php $this->plugin('cycle', 'lightdark', $i, 3) ?><br />
<?php endfor; ?>
</pre>






<h1>dateformat</h1>
<p><?php $this->plugin('dateformat', "Aug 8, 1970") ?></p>
<p><?php $this->plugin('dateformat', "Aug 8, 1970", 'mydate') ?></p>




<h1>javascript</h1>
<pre><?php echo htmlentities($this->splugin('javascript', 'path/to/file.js')) ?></pre>


<h1>image</h1>

<h2>local</h2>
<pre><?php echo htmlentities($this->splugin('image', 'savant.gif')) ?></pre>
<?php $this->plugin('image', 'savant.gif') ?>

<h2>nonexistent</h2>
<pre><?php echo htmlentities($this->splugin('image', 'savantx.gif', 'image does not exist')) ?></pre>
<?php $this->plugin('image', 'savantx.gif', 'image does not exist') ?>

<h2>stream</h2>
<pre><?php echo htmlentities($this->splugin('image', 'http://phpsavant.com/etc/fester.jpg')) ?></pre>
<?php $this->plugin('image', 'http://phpsavant.com/etc/fester.jpg') ?>


<h1>options</h1>

<h2>assoc</h2>
<pre>
<?php
	$result = $this->splugin('options', $this->set, 'key1', 'dumb="dumber"');
	$this->plugin('modify', $result, 'htmlentities nl2br');
?>
</pre>
<form><select name="test"><?php echo $result ?></select></form>


<h2>seq</h2>
<pre>
<?php
	$result = $this->splugin('options', $this->set, 'val2', array('attrib' => 'this & that'), true);
	$this->plugin('modify', $result, 'htmlentities nl2br');
?>
</pre>
<form><select name="test"><?php echo $result ?></select></form>

 
 
<h1>radios</h1>

<h2>assoc</h2>
<pre>
<?php
	$result = $this->splugin('radios', 'das_radio', $this->set, 'key1', 'nil', "<br /><br />\n", 'dumb="dumber"');
	$this->plugin('modify', $result, 'htmlentities nl2br');
?>
</pre>
<form><?php echo $result ?></form>
 
<h2>seq</h2>
<pre>
<?php
	$result = $this->splugin('radios', 'das_radio', $this->set, 'val2', 'nil', "<br /><br />\n", 'dumb="dumber"', true);
	$this->plugin('modify', $result, 'htmlentities nl2br');
?>
</pre>
<form><?php echo $result ?></form>
 


<h1>stylesheet</h1>
<pre><?php echo htmlentities($this->splugin('stylesheet', 'path/to/styles.css')) ?></pre>


<h1>textarea</h1>
<pre>
<?php
	$result = $this->splugin('textarea', 'longtext', "some really long text");
	$this->plugin('modify', $result, 'htmlentities nl2br');
?>
</pre>


<?php
// tests the plugin path and a call-by-instance plugin
?>

<h1>fester</h1>

<?php $this->plugin('fester', 'Gomez') ?><br />
<?php $this->plugin('fester', 'Morticia') ?><br />
<?php $this->plugin('fester', 'Cara Mia!') ?><br />


<h1>Plugin Objects</h1>

_resource[plugin]: <pre><?php print_r($this->_resource['plugin']) ?></pre>




<!-- end -->