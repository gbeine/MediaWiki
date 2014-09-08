<?php
/**
 * PDFCreator.php 
 * 
 * @author  Gerrit Beine
 * @date    23.07.2006
 */

require_once( 'global_functions.php' ) ;
require_once( 'wiki2xml.php' ) ;
require_once( 'content_provider.php' ) ;
require_once( 'Export/Content.php' );
require_once( 'Export/Latex.php' );
require_once( 'Export/Title.php' );
#require_once( 'Output_Screen.php' );
require_once( 'Output.php' );
require_once( 'XML/Parser/Links.php' );
require_once( 'XML/Parser/Images.php' );
require_once( 'XML/Parser/Lite/Tree.php' );

class PDFCreator
{
	private $_depth = 0;
	private $_article;
	private $_contents = array ();
	private $_images = array ();
	private $_articles = array ();
	private $_outputDirectory = null;
	private $_projectDirectory = null;
	private $_pdflatex = "/usr/bin/pdflatex";
	private $_maxdepth;
	private $_internal;
	private $_secret;
	private $_verbose;
	private $_notitle = false;
	private $_notoc = false;
	private $_documentclass = "";
	private $_usepackage = array ();
	private $_layout = array ();
	private $_templates = "./main";
	private $_workingDirectory;
	
   /**
    * Konstruktor für den PDFCreator
    * 
    * @global array   $wpdf
    */
	function __construct() {
		global $wpdf;
		$this->_outputDirectory = $wpdf['output_directory'];
		$this->_projectDirectory = date( "YmdHi" );
		$this->_pdflatex = $wpdf['pdflatex'];
		$this->_templates = $wpdf['templates'];
		$this->_verbose = $wpdf['verbose'];
    		$this->_maxdepth = $wpdf['depth'];
	        $this->_internal = $wpdf['internal'];
		$this->_documentclass = $wpdf['documentclass'];
		$this->_usepackage = $wpdf['usepackage'];
		$this->_layout = $wpdf['layout'];
		$this->_workingDirectory = getcwd();
	}
	
	function create ( $properties ) {
        $this->_article = $properties['article'];
        $this->_verbose = $properties['verbose'];
        $this->_maxdepth = $properties['depth'];
        $this->_internal = $properties['internal'];
        $this->_secret = $properties['secret'];
        $this->_notitle = $properties['title'];
        $this->_notoc = $properties['toc'];
        $this->fetchArticles();
	#$this->dumpArticles();
        $this->fetchImages();
    	$this->createOutputDirectory();
        $this->writeArticles();
        $this->writeContents();
        $this->prepareLatex();
        #$this->runLatex();
	exit;
	}
	
	function getLink() {
		global $wgScriptPath;
		$file = $this->getDirectory() . "/"
		      . "main.pdf";
		if ( file_exists( $file ) ) {
			return "<a href='$wgScriptPath/$file'>PDF</a>";
		}
	}

   /**
    * Holt die Artikel aus dem Wiki
    * 
    * @access private
    * @global ContentProvider $content_provider
    * @global wiki2xml $wiki2xml
    */	
	private function fetchArticles() {
	    global $content_provider, $wiki2xml;
        $content_provider = new ContentProviderMySQL();
        $wiki2xml = new wiki2xml();
        
		$this->fetchArticle( array ( 'target' => $this->_article ) );
		for ( $i = 0; $i < count ( $this->_contents ); $i++ ) {
			$article = $this->_contents[$i];
			if ( ! isset ( $this->_articles[$article['target']] ) ) {
				if ( ! isset ( $article['title'] ) ) {
					$article['title'] = $article['target'];
				}
				unset( $article['target'] );
				$this->_contents[$i] = $article;
			}
		}
	}
	
   /**
    * Holt einen Artikel aus dem Wiki
    * 
    * @access private
    * @param  array    Der Artikel
    * @global ContentProvider $content_provider
    * @global wiki2xml $wiki2xml
    */	
	private function fetchArticle( $article ) {
	    global $content_provider, $wiki2xml;
        $content = $content_provider->get_wiki_text( $article['target'] );
        if ( 0 == strlen( $content ) ) {
        	return; // Artikel ist leer, also abbrechen 
        }
       	$this->_articles[$article['target']] = "<article>" . $wiki2xml->parse( $content ) . "</article>";
	    $article['level'] = $this->_depth;
	    if ( $this->_article != $article['target'] ) {
			array_push( $this->_contents, $article );
	    }
		$this->_depth++;
        if ( $this->_maxdepth > $this->_depth ) {
        	$this->parseLinks( $article );
        }
		$this->_depth--;
	}
	
   /**
    * Sucht die Links aus einem Artikel heraus
    * 
    * @access private
    * @param  string   Der Name des Artikels
    */
	private function parseLinks( $article ) {
		$xplinks = new XML_Parser_Links();
		$xplinks->setInputString( $this->_articles[$article['target']] );
		$xplinks->parse();
		$links = $xplinks->getLinks();
		foreach ( $links['children'] as $link ) {
		    $this->fetchArticle( $link );
		}
		if ( 0 != count( $links['children'] ) &&           // Wenn es Kinder gibt und
		     ( $this->_article != $article['target'] ||    // es nicht der Startartikel ist 
		       true == $this->_notitle ) ) {              // oder kein Titel generiert werden soll
			unset( $this->_articles[$article['target']] ); // keinen Inhalt speichern
		}
	}
	
   /**
    * Liest die Bilder aus den Artikelm
    * 
    * @access private
    * @global array    $images
    */
    private function fetchImages() {
    	global $images;
    	foreach ( $this->_articles as $article ) {
	    	$xpltree = new XML_Parser_Images();
    		$xpltree->setInputString( $article );
    		$xpltree->parse();
    		foreach ( $xpltree->getImages() as $image ) {
    			$this->fetchImage( $image );
    		}
    	}
    	$images = $this->_images;
    	
    }
    
   /**
    * Liest Bildinformationen aus dem Wiki
    * 
    * @access private
    * @param  string   Der Name des Bildes
    */
    private function fetchImage( $name ) {
    	$image = Image::newFromName( $name );
    	$image->load();
    	if ( $image->exists() ) {
    		$this->_images[$name] = array (
    				'width' => $image->getWidth(),
    				'height' => $image->getHeight(),
    				'path' => $image->getImagePath()
    			);
    	}
    	
    }

   /**
    * Schreibt die Artikel in LaTeX
    * 
    * @access private
    */
    private function writeArticles() {
    	if ( isset ( $this->_articles[$this->_article] ) ) {
    		$this->writeTitle( $this->_article );
    		unset( $this->_articles[$this->_article] );
    	}
    	foreach ( array_keys( $this->_articles ) as $article ) {
    		$this->writeArticle( $article );
    	}
    }
    
    private function dumpArticles() {
	header("Content-type: text/plain");
    	foreach ( array_keys( $this->_articles ) as $article ) {
    		$xpltree = new XML_Parser_Lite_Tree();
    		$xpltree->setInputString( $this->_articles[$article] );
	    	$xpltree->parse();
    		print_r( $xpltree->getTree() );
    	}
    }

   /**
    * Schreibt einen Artikel
    * 
    * @access private
    * @param  string   Der Name des Artikels
    */
    private function writeArticle( $article ) {
    	$xpltree = new XML_Parser_Lite_Tree();
    	$xpltree->setInputString( $this->_articles[$article] );
    	$xpltree->parse();
    	$file = $this->getFilename( $article );
    	$output = new Output( $file, $this->_verbose );
    	$export = new Export_Latex( $xpltree->getTree(), $output, $this->_internal, $this->_secret );
    	$export->parse();
    }

   /**
    * Schreibt die Titelseite
    * 
    * @access private
    * @param  string   Der Name des Artikels
    */
    private function writeTitle( $article ) {
    	$xpltree = new XML_Parser_Lite_Tree();
    	$xpltree->setInputString( $this->_articles[$article] );
    	$xpltree->parse();
    	$file = $this->getDirectory()
    	      . "/title.tex";
    	$output = new Output( $file, $this->_verbose );
    	$export = new Export_Title( $xpltree->getTree(), $output );
    	$export->parse();
    }

   /**
    * Schreibt die Artikel in LaTeX
    * 
    * @access private
    */
    private function writeContents() {
    	$file = $this->getDirectory()
    	      . "/contents.tex";
    	$output = new Output( $file, $this->_verbose );
    	$export = new Export_Content( $this->_contents, $output );
    	$export->parse();
    }

   /**
    * Bereitet das Starten von LaTeX vor
    * 
    * @access private
    */
    private function prepareLatex() {
    	$sourcedir = getcwd() . "/" . $this->_templates;
    	$targetdir = $this->getDirectory();
    	$files = scandir( $sourcedir );
    	foreach ( $files as $file ) {
    		if ( "." == $file || ".." == $file ) {
    			continue;
    		}
    		copy( $sourcedir . "/" . $file, $targetdir . "/" . $file );
    	}
	$this->writeMain();
    } 
    
   /**
    * Schreibt die Datei main.tex
    *
    * @access private
    */
    private function writeMain() {
    	$targetdir = $this->getDirectory();
	$file = $targetdir . "/main.tex";
    	$output = new Output( $file, $this->_verbose );
	$output->append( "\\documentclass" );
	$output->append( $this->_documentclass );
	$output->append( "\n" );
	foreach ($this->_usepackage as $package) {
		$output->append( "\\usepackage" );
		$output->append( $package );
		$output->append( "\n" );
	}
	foreach ($this->_layout as $layout) {
		$output->append( $layout );
		$output->append( "\n" );
	}
	$output->append( "\\begin{document}\n" );
	if ( false == $this->_notitle ) {
		$output->append( "\\include{title}\n" );
	}
	if ( false == $this->_notoc ) {
		$output->append( "\\tableofcontents\n" );
	}
	$output->append( "\\include{contents}\n" );
	$output->append( "\\end{document}\n" );
    }

   /**
    * Startet LaTeX
    * 
    * @access private
    */
    private function runLatex() {
    	$directory = $this->getDirectory();
    	$mainfile = "main.tex";
    	chdir( $directory );
    	$command = $this->_pdflatex . " " . $mainfile;
	if ($this->_verbose) {
        	system( $command, $return ); // TODO: Warum muß das dreimal laufen?
		system( $command, $return ); // Sonst wird die ToC nicht generiert,
    		system( $command, $return ); // das ist so nicht i.O.
	} else {
        	exec( $command ); // TODO: Warum muß das dreimal laufen?
		exec( $command ); // Sonst wird die ToC nicht generiert,
    		exec( $command, $output, $return ); // das ist so nicht i.O.	
		if ( $return ) {
		    echo $output;
		}
	}
    	chdir( $this->_workingDirectory );
    }
    
   /**
    * Legt das Zielverzeichnis an
    * 
    * @access private
    */
    private function createOutputDirectory() {
    	mkdir( $this->_outputDirectory );
    	mkdir( $this->_outputDirectory . "/" . $this->_article );
    	mkdir( $this->_outputDirectory . "/" . $this->_article . "/" . $this->_projectDirectory );
    }
    
   /**
    * Liefert das Zielverzeichnis für das Projekt
    * 
    * @access private
    */
    private function getDirectory() {
    	$directory = $this->_outputDirectory . "/"
    	           . $this->_article . "/"
    	           . $this->_projectDirectory;
    	return $directory;
    }
   
   /**
    * Liefert den für Latex aufbereiteten Dateinamen
    *
    * @access private
    * @param  string   Der Artikelname
    * @return string 
    */ 
    private function getFilename( $article ) {
	    $file = $this->getDirectory() . "/"
     	      . preg_replace( "/\s/", "_", $article )
     	      . ".tex";
    	return $file;
    }
}

?>
