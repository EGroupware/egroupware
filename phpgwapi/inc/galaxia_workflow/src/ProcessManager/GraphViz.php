<?php
//
// +----------------------------------------------------------------------+
// | PEAR :: Image :: GraphViz                                            |
// +----------------------------------------------------------------------+
// | Copyright (c) 2002 Sebastian Bergmann <sb@sebastian-bergmann.de> and |
// |                    Dr. Volker Göbbels <vmg@arachnion.de>.            |
// +----------------------------------------------------------------------+
// | This source file is subject to version 3.00 of the PHP License,      |
// | that is available at http://www.php.net/license/3_0.txt.             |
// | If you did not receive a copy of the PHP license and are unable to   |
// | obtain it through the world-wide-web, please send a note to          |
// | license@php.net so we can mail you a copy immediately.               |
// +----------------------------------------------------------------------+
//
// $Id$
//

/**
* PEAR::Image_GraphViz
*
* Purpose
*
*     Allows for the creation of and the work with directed
*     and undirected graphs and their visualization with
*     AT&T's GraphViz tools. These can be found at
*     http://www.research.att.com/sw/tools/graphviz/
*
* Example
*
*     require_once 'Image/GraphViz.php';
*     $graph = new Image_GraphViz();
*
*     $graph->addNode('Node1', array('URL'      => 'http://link1',
*                                    'label'    => 'This is a label',
*                                    'shape'    => 'box'
*                                    )
*                     );
*     $graph->addNode('Node2', array('URL'      => 'http://link2',
*                                    'fontsize' => '14'
*                                    )
*                     );
*     $graph->addNode('Node3', array('URL'      => 'http://link3',
*                                    'fontsize' => '20'
*                                    )
*                     );
*
*     $graph->addEdge(array('Node1' => 'Node2'), array('label' => 'Edge Label'));
*     $graph->addEdge(array('Node1' => 'Node2'), array('color' => 'red'));
*
*     $graph->image();
*
* @author  Sebastian Bergmann <sb@sebastian-bergmann.de>
*          Dr. Volker Göbbels <vmg@arachnion.de>
* @package Image
*/
class Process_GraphViz {
    /**
    * Path to GraphViz/dot command
    *
    * @var  string
    */
    var $dotCommand = 'dot';
    
    var $pid;

    /**
    * Path to GraphViz/neato command
    *
    * @var  string
    */
    var $neatoCommand = 'neato';

    /**
    * Representation of the graph
    *
    * @var  array
    */
    var $graph;

    /**
    * Constructor
    *
    * @param  boolean Directed (true) or undirected (false) graph.
    * @param  array   Attributes of the graph
    * @access public
    */
    function Process_GraphViz($directed = true, $attributes = array()) {
        $this->setDirected($directed);
        $this->setAttributes($attributes);
        if (defined('GRAPHVIZ_BIN_DIR') && GRAPHVIZ_BIN_DIR) {
            $this->dotCommand = GRAPHVIZ_BIN_DIR.'/'.$this->dotCommand;
            $this->neatoCommand = GRAPHVIZ_BIN_DIR.'/'.$this->neatoCommand;
        }
    }
    
    function set_pid($pid) 
    {
      $this->pid = $pid;
    }

    /**
    * Output image of the graph in a given format.
    *
    * @param  string  Format of the output image.
    *                 This may be one of the formats supported by GraphViz.
    * @access public
    */
    function image($format = 'png') {
        if ($file = $this->saveParsedGraph()) {
            $outputfile = $file . '.' . $format;
            $outputfile2 = $file . '.' . 'map';
            $command  = $this->graph['directed'] ? $this->dotCommand : $this->neatoCommand;
            $command .= " -T$format -o$outputfile $file";

            @`$command`;
            $command = $this->dotCommand;
            $command.= " -Tcmap -o$outputfile2 $file";
            @`$command`;
            $fr = fopen($outputfile2,"r");
            $map = fread($fr,filesize($outputfile2));
            fclose($fr);
            @unlink($file);

            switch ($format) {
                case 'gif':
                case 'jpg':
                case 'png':
                case 'svg':
                case 'wbmp': {
                    header('Content-Type: image/' . $format);
                }
                break;

                case 'pdf': {
                    header('Content-Type: application/pdf');
                }
                break;
            }

            header('Content-Length: ' . filesize($outputfile));

            $fp = fopen($outputfile, 'rb');

            if ($fp) {
                echo fread($fp, filesize($outputfile));
                fclose($fp);
                @unlink($outputfile);
            }
            @unlink($outputfile2);
            return $map;
        }
    }
    
    function image_and_map($format = 'png') {
        if ($file = $this->saveParsedGraph()) {
            $outputfile = $file . '.' . $format;
            $outputfile2 = $file . '.' . 'map';
            if(!isset($this->graph['directed'])) $this->graph['directed']=true;
            $command  = $this->graph['directed'] ? $this->dotCommand : $this->neatoCommand;
            $command .= " -T$format -o $outputfile $file";
            @`$command`;

            $command = $this->dotCommand;
            $command.= " -Tcmap -o $outputfile2 $file";
            @`$command`;
            @unlink($file);
            return true;
        }
    }

    
    function map() {
        if ($file = $this->saveParsedGraph()) {
            
            $outputfile2 = $file . '.' . 'map';
            
            $command = $this->dotCommand;
            $command.= " -Tcmap -o$outputfile2 $file";
            @`$command`;
            $fr = fopen($outputfile2,"r");
            $map = fread($fr,filesize($outputfile2));
            fclose($fr);
            
            @unlink($outputfile2);
            @unlink($file);
            return $map;
        }
    }

    /**
    * Add a cluster to the graph.
    *
    * @param  string  ID.
    * @param  array   Title.
    * @access public
    */
    function addCluster($id, $title) {
        $this->graph['clusters'][$id] = $title;
    }

    /**
    * Add a note to the graph.
    *
    * @param  string  Name of the node.
    * @param  array   Attributes of the node.
    * @param  string  Group of the node.
    * @access public
    */
    function addNode($name, $attributes = array(), $group = 'default') {
        $this->graph['nodes'][$group][$name] = $attributes;
    }

    /**
    * Remove a node from the graph.
    *
    * @param  Name of the node to be removed.
    * @access public
    */
    function removeNode($name, $group = 'default') {
        if (isset($this->graph['nodes'][$group][$name])) {
            unset($this->graph['nodes'][$group][$name]);
        }
    }

    /**
    * Add an edge to the graph.
    *
    * @param  array Start and End node of the edge.
    * @param  array Attributes of the edge.
    * @access public
    */
    function addEdge($edge, $attributes = array()) {
        if (is_array($edge)) {
            $from = key($edge);
            $to   = $edge[$from];
            $id   = $from . '_' . $to;

            if (!isset($this->graph['edges'][$id])) {
                $this->graph['edges'][$id] = $edge;
            } else {
                $this->graph['edges'][$id] = array_merge(
                  $this->graph['edges'][$id],
                  $edge
                );
            }

            if (is_array($attributes)) {
                if (!isset($this->graph['edgeAttributes'][$id])) {
                    $this->graph['edgeAttributes'][$id] = $attributes;
                } else {
                    $this->graph['edgeAttributes'][$id] = array_merge(
                      $this->graph['edgeAttributes'][$id],
                      $attributes
                    );
                }
            }
        }
    }

    /**
    * Remove an edge from the graph.
    *
    * @param  array Start and End node of the edge to be removed.
    * @access public
    */
    function removeEdge($edge) {
        if (is_array($edge)) {
              $from = key($edge);
              $to   = $edge[$from];
              $id   = $from . '_' . $to;

            if (isset($this->graph['edges'][$id])) {
                unset($this->graph['edges'][$id]);
            }

            if (isset($this->graph['edgeAttributes'][$id])) {
                unset($this->graph['edgeAttributes'][$id]);
            }
        }
    }

    /**
    * Add attributes to the graph.
    *
    * @param  array Attributes to be added to the graph.
    * @access public
    */
    function addAttributes($attributes) {
        if (is_array($attributes)) {
            $this->graph['attributes'] = array_merge(
              $this->graph['attributes'],
              $attributes
            );
        }
    }

    /**
    * Set attributes of the graph.
    *
    * @param  array Attributes to be set for the graph.
    * @access public
    */
    function setAttributes($attributes) {
        if (is_array($attributes)) {
            $this->graph['attributes'] = $attributes;
        }
    }

    /**
    * Set directed/undirected flag for the graph.
    *
    * @param  boolean Directed (true) or undirected (false) graph.
    * @access public
    */
    function setDirected($directed) {
        if (is_bool($directed)) {
            $this->graph['directed'] = $directed;
        }
    }

    /**
    * Load graph from file.
    *
    * @param  string  File to load graph from.
    * @access public
    */
    function load($file) {
        if ($serialized_graph = implode('', @file($file))) {
            $this->graph = unserialize($serialized_graph);
        }
    }

    /**
    * Save graph to file.
    *
    * @param  string  File to save the graph to.
    * @return mixed   File the graph was saved to, false on failure.
    * @access public
    */
    function save($file = '') {
        $serialized_graph = serialize($this->graph);

        if (empty($file)) {
            $file = tempnam('temp', 'graph_');
        }

        if ($fp = @fopen($file, 'w')) {
            @fputs($fp, $serialized_graph);
            @fclose($fp);

            return $file;
        }

        return false;
    }

    /**
    * Parse the graph into GraphViz markup.
    *
    * @return string  GraphViz markup
    * @access public
    */
    function parse() {
        $parsedGraph = "digraph G {\n";

        if (isset($this->graph['attributes'])) {
            foreach ($this->graph['attributes'] as $key => $value) {
                $attributeList[] = $key . '="' . $value . '"';
            }

            if (!empty($attributeList)) {
              $parsedGraph .= implode(',', $attributeList) . ";\n";
            }
        }

        if (isset($this->graph['nodes'])) {
            foreach($this->graph['nodes'] as $group => $nodes) {
                if ($group != 'default') {
                  $parsedGraph .= sprintf(
                    "subgraph \"cluster_%s\" {\nlabel=\"%s\";\n",

                    $group,
                    isset($this->graph['clusters'][$group]) ? $this->graph['clusters'][$group] : ''
                  );
                }

                foreach($nodes as $node => $attributes) {
                    unset($attributeList);

                    foreach($attributes as $key => $value) {
                        $attributeList[] = $key . '="' . $value . '"';
                    }

                    if (!empty($attributeList)) {
                        $parsedGraph .= sprintf(
                          "\"%s\" [ %s ];\n",
                          addslashes(stripslashes($node)),
                          implode(',', $attributeList)
                        );
                    }
                }

                if ($group != 'default') {
                  $parsedGraph .= "}\n";
                }
            }
        }

        if (isset($this->graph['edges'])) {
            foreach($this->graph['edges'] as $label => $node) {
                unset($attributeList);

                $from = key($node);
                $to   = $node[$from];

                foreach($this->graph['edgeAttributes'][$label] as $key => $value) {
                    $attributeList[] = $key . '="' . $value . '"';
                }

                $parsedGraph .= sprintf(
                  '"%s" -> "%s"',
                  addslashes(stripslashes($from)),
                  addslashes(stripslashes($to))
                );
                
                if (!empty($attributeList)) {
                    $parsedGraph .= sprintf(
                      ' [ %s ]',
                      implode(',', $attributeList)
                    );
                }

                $parsedGraph .= ";\n";
            }
        }

        return $parsedGraph . "}\n";
    }

    /**
    * Save GraphViz markup to file.
    *
    * @param  string  File to write the GraphViz markup to.
    * @return mixed   File to which the GraphViz markup was
    *                 written, false on failure.
    * @access public
    */
    function saveParsedGraph($file = '') {
        $parsedGraph = $this->parse();
        if (!empty($parsedGraph)) {

                $file = GALAXIA_PROCESSES.'/'.$this->pid.'/graph/'.$this->pid;
            if ($fp = @fopen($file, 'w')) {
                @fputs($fp, $parsedGraph);
                @fclose($fp);

                return $file;
            }
        }

        return false;
    }
}
?>
