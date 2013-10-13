<?php
/**
 *
 */

ini_set("error_reporting", E_ERROR);

echo "Version 1.1\n";
echo "=======================\n";

echo "index.php [start] [stop] [pageid] [index] [overwrite]";

if( empty( $argv[1] ) || empty( $argv[2] ) ) return;

require 'vendor/autoload.php';

require 'Sherlock/Sherlock.php';
\Sherlock\Sherlock::registerAutoloader();


include 'includes/functions.core.php';
include 'includes/class.wp-error.php';
include 'includes/functions.bp-options.php';
include 'includes/functions.plugin-api.php';
include 'includes/class.wp-http.php';

function decode( $input ){

    $result = $input;

    $result = str_replace( '&nbsp;', ' ', $result );
    $result = str_replace( '&#229;', 'å', $result );
    $result = str_replace( '&#228;', 'ä', $result );
    $result = str_replace( '&#246;', 'ö', $result );

    $result = str_replace( '&#196;', 'Ä', $result );
    $result = str_replace( '&#197;', 'Å', $result );
    $result = str_replace( '&#214;', 'Ö', $result );

    return $result;

}

$sherlock = new \Sherlock\Sherlock();
$sherlock->addNode("elastic.flowcom.se", "80");

echo "Sherlock started to elastic.flowcom.se...\n";

$index_name = $argv[4];

if( empty( $index_name ) ) $index_name = "mo-iuware";

echo "Index created...\n";

$result = "";
$headline = "";
$paper = "";

$pageid = $argv[3];

if( empty( $pageid ) ) $pageid = 395;

for( $i=$argv[1]; $i<$argv[2]; $i++ ){

    $the_body = wp_remote_retrieve_body( wp_remote_get( "http://papernet.se/iuware.aspx?pageid=" . $pageid . "&ssoid=" . $i ) );

    $matches = array();
    preg_match_all('/<div\s*class="container_centermain_article">(.*)<div>/s', $the_body, $matches);

    if( isset( $matches[1][0] ) ){

        $content = $matches[1][0];

        preg_match_all('/<p\s*class="paper">\((.*)\)<\/p>/', $content, $matches);
        $paper = isset($matches[1][0]) ? decode( $matches[1][0] ) : '';

        preg_match_all('/<h1>(.*)<\/h1>/', $content, $matches);
        $headline = decode( $matches[1][0] );

        preg_match_all('/<p\s*class="date">(.*)<\/p>/', $content, $matches);
        $date = decode( $matches[1][0] );
        $date = date( 'Y-m-d', strtotime( $date ) );

        preg_match_all('/<p\s*class="preamble">(.*)<\/p>/', $content, $matches);
        $preamble = decode( $matches[1][0] );

        preg_match_all('/<p\s*class="body">(.*)<\/p>/', $content, $matches);
        $body = decode( $matches[1][0] );

        if( $headline == $preamble && $preamble == $body ){
            echo $i . ". No article\n";
        }
        else if( $date < '1980-01-01' ){
            echo $i . ". No date in article\n";
        }
        else if( empty( $headline ) ){
            echo $i . ". No headline in article\n";
        }
        else{

            //check if already exists
            //Build a new search request
            $request = $sherlock->search();

            //populate a Term query to start
            $termQuery = \Sherlock\Sherlock::queryBuilder()->Term()->field("ssoid")
                ->term($i);

            //Set the index, type and from/to parameters of the request.
            $request->index("mo-iuware")
                ->type("article")
                ->from(0)
                ->to(10)
                ->query($termQuery);

            //Execute the search and return results
            $response = $request->execute();

            if( count($response) && $argv[5]!=1 ){
                echo $i . ". " . $headline . " [" . $date . "]\n" . " - already in index, no save!\n";
            }
            else{
                $doc = array(
                    "ssoid" => $i,
                    "paper" => $paper,
                    "headline" => $headline,
                    "date" => $date,
                    "preamble" => $preamble,
                    "body" => $body
                );
                echo $i . ". " . $headline . " [" . $date . "]\n";
                $doc = $sherlock->document()->index( $index_name )->type( 'article' )->document( $doc );
                $doc->execute();
            }

        }

    }

}
