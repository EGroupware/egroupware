<?php

class baseportalbox {

    //Set up the Object, reserving memory space for variables

    var $outerwidth;
    var $outerbordercolor;
    var $outerborderwidth;
    var $titlebgcolor;
    var $innerwidth;
    var $innerbgcolor;

    // Textual variables
    var $title;

    /*
        Use these functions to get and set the values of this
        object's variables. This is good OO practice, as it means
        that datatype checking can be completed and errors raised accordingly.
    */
    function setvar($var,$value="") {
      if ($value=="") {
		global $$var;
		$value = $$var;
      }
      $this->$var = $value;
//      echo $var." = ".$this->$var."<br>\n";
    }

    function getvar($var="") {
	  if ($var=="" || !isset($this->$var)) {
		global $phpgw;
		echo 'Programming Error: '.$this->classname().'->getvar('.$var.')!<br>\n';
		$phpgw->common->phpgw_exit();
	  }
//echo "Var = ".$var."<br>\n";
//echo $var." = ".$this->$var."<br>\n";
      return $this->$var;
    }

    /*
        This is the constructor for the object.
    */
    function baseportalbox($title="", $primary="", $secondary="", $tertiary="") {
        $this->setvar("title",$title);
//        echo "After SetVar Title = ".$this->getvar("title")."<br>\n";
        $this->setvar("outerwidth",220);
        $this->setvar("innerwidth",220);
        $this->setvar("outerborderwidth",1);
        $this->setvar("titlebgcolor",$primary);
        $this->setvar("innerbgcolor",$secondary);
        $this->setvar("outerbordercolor",$tertiary);
    }
    // Methods 
} 

class linkbox extends baseportalbox { 
    /* 
         Set up the Object. You will notice, we have not reserved  
        memory space for variables. In this circumstance it is not necessary. 
    */ 

    /* 
        This is the constructor for the linkbox. The only thing this does  
        is to call the constructor of the parent class. Why? Well, whilst  
        PHP manages a certain part of OO, one of the bits it falls down on  
        (at the moment) is constructors within sub-classes. So, to  
        be sure that the sub-class is instantiated with the constructor of  
        the parent class, I simply call the parent constructor. Of course,  
        if I then wanted to override any of the values, I could easily do so. 
    */ 
    function linkbox($title="", $primary="", $secondary="", $tertiary="") { 
        $this->baseportalbox($title, $primary, $secondary, $tertiary);
    } 
    /* 
        This is the only method within the class. Quite simply, as you can see  
        it draws the table(s), placing the required data in the appropriate place. 
    */ 
    function draw() { 
		global $phpgw, $phpgw_info;
		
        echo '<table border="'.$this->getvar("outerborderwidth").'" cellpadding="0" cellspacing="0" width="'.$this->getvar("outerwidth").'" bordercolor="'.$this->getvar("outerbordercolor").'" bgcolor="'.$this->getvar("titlebgcolor").'">'; 
        echo '<tr><td align="center">'.$this->getvar("title").'</td></tr>';
        echo '<tr><td>';
        echo '<table border="1" cellpadding="0" cellspacing="0" width="'.$this->getvar("innerwidth").'" bgcolor="'.$this->getvar("innerbgcolor").'">';
        echo '<tr><td><ul>';
        for ($x = 0; $x < count($this->data); $x++) { 
            echo '<li><a href="'.$this->data[$x][1].'">'.$this->data[$x][0].'</a></li>';
        } 
        echo '</ul></td></tr>';
        echo '</table>';
        echo '</td></tr>';
        echo '</table>';
    } 
} 

class resultbox extends baseportalbox { 
    /* 
        Set up the Object. You will notice, we have not reserved memory  
        space for variables. In this circumstance it is not necessary. 
    */ 

    //constructor 
    function resultbox($title="", $primary="", $secondary="", $tertiary="") { 
        $this->baseportalbox($title, $primary, $secondary, $tertiary);
    } 
    /* 
        This is the only method within the class. Quite simply, as you can see  
        it draws the table(s), placing the required data in the appropriate place. 
    */     
    function draw() { 
        echo '<table border="'.$this->getvar("outerborderwidth").'" cellpadding="0" cellspacing="0" width="'.$this->getvar("outerwidth").'" bordercolor="'.$this->getvar("outerbordercolor").'" bgcolor="'.$this->getvar("titlebgcolor").'">';
        echo '<tr><td align="center">'.$this->getvar("title").'</td></tr>';
        echo '<tr><td>';
        echo '<table border="0" cellpadding="0" cellspacing="0" width="'.$this->getvar("innerwidth").'" bgcolor="'.$this->getvar("innerbgcolor").'">';
        for ($x = 0; $x < count($this->data); $x++) { 
            echo '<tr>';
            echo '<td width="50%">'.$this->data[$x][0].'</td>';
            echo '<td width="50%">'.$this->data[$x][1].'</td>'; 
            echo '</tr>';
        } 
        echo '</table>';
        echo '</td></tr>';
        echo '</table>';
    } 
} 
?>
