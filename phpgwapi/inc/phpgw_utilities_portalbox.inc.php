<?php

class baseportalbox {

    //Set up the Object, reserving memory space for variables

    var $outerwidth;
    var $outerbordercolor;
    var $outerborderwidth;
    var $titlebgcolor;
    var $width;
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
        $this->setvar("outerwidth",300);
        $this->setvar("innerwidth",300);
        $this->setvar("width",300);
    } 
    /* 
        This is the only method within the class. Quite simply, as you can see  
        it draws the table(s), placing the required data in the appropriate place. 
    */ 
    function draw() { 
		global $phpgw, $phpgw_info;

		$p = new Template($phpgw->common->get_tpl_dir('home'));
		$p->set_file(array('portal_main' => 'portal_main.tpl',
      					 'portal_linkbox_header' => 'portal_linkbox_header.tpl',
      					 'portal_linkbox' => 'portal_linkbox.tpl',
      					 'portal_linkbox_footer' => 'portal_linkbox_footer.tpl'));
        $p->set_block('portal_main','portal_linkbox_header','portal_linkbox','portal_linkbox_footer');

        $p->set_var('outer_border',$this->getvar('outerborderwidth'));
        $p->set_var('outer_width',$this->getvar('width'));
        $p->set_var('outer_bordercolor',$this->getvar('outerbordercolor'));
        $p->set_var('outer_bgcolor',$this->getvar('titlebgcolor'));
        $p->set_var('title',$this->getvar('title'));
        $p->set_var('inner_width',$this->getvar('width'));
        $p->set_var('inner_bgcolor',$this->getvar('innerbgcolor'));
        $p->parse('output','portal_linkbox_header',True);
        
        for ($x = 0; $x < count($this->data); $x++) {
            $p->set_var('link',$this->data[$x][1]);
            $p->set_var('text',$this->data[$x][0]);
            $p->parse('output','portal_linkbox',True);
        }
        $p->parse('output','portal_linkbox_footer',True);
        return $p->parse('out','portal_main');
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
        $this->setvar("outerwidth",400);
        $this->setvar("innerwidth",400);
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
