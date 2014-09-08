<?php
/*
To enable this extension, put all files in this directory into a "wiki2pdf"
subdirectory of your MediaWiki extensions directory.
Also, add
    require_once ( "extensions/wiki2pdf/extension.php" ) ;
to your LocalSettings.php
The extension will then be accessed as [[Special:Wiki2PDF]].
*/

if( ! defined( 'MEDIAWIKI' ) ) die();

# Integrating into the MediaWiki environment

$wgExtensionCredits['Wiki2PDF'][] = array(
        'name' => 'Wiki2PDF',
        'description' => 'An extension to convert a set of wiki articles into PDF.',
        'author' => 'Gerrit Beine'
);

$wgExtensionFunctions[] = 'wfWiki2PDFExtension';

# for Special::Version:
$wgExtensionCredits['parserhook'][] = array(
        'name' => 'wiki2pdf extension',
        'author' => 'Gerrit Beine',
        'url' => 'http://www.gerritbeine.de',
        'version' => 'v0.01',
);


/**
 * The special page
 */
function wfWiki2PDFExtension() { # Checked for HTML and MySQL insertion attacks
    global $IP, $wgMessageCache;

    $wgMessageCache->addMessage( 'wiki2pdf', 'Wiki2PDF' );

    require_once($IP.'/includes/SpecialPage.php');

    class SpecialWiki2PDF extends SpecialPage {

        /**
        * Constructor
        */
        function SpecialWiki2PDF() { # Checked for HTML and MySQL insertion attacks
            SpecialPage::SpecialPage( 'Wiki2PDF' );
            $this->includable( true );
        }

        /**
        * Special page main function
        */
        function execute( $par = null ) { # Checked for HTML and MySQL insertion attacks
            global $wgOut, $wgScriptPath, $IP;
            global $wpdf;
            include_once ( "default.php" ) ; 
            include_once ( "wiki2pdf.php" ) ;

            $this->setHeaders();
            $wgOut->addHtml( $out );
        }

    } # end of class

    SpecialPage::addPage( new SpecialWiki2PDF()  );
}

?>
