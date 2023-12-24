<?php

error_reporting( E_ALL );

// Set higher memory limit
ini_set( 'memory_limit', '256M' ); // Adjust the value as needed

// Set higher post_max_size
ini_set( 'post_max_size', '256M' ); // Adjust the value as needed

// Set longer max_execution_time
ini_set( 'max_execution_time', 10 ); // Adjust the value as needed

/*
Stacksize   pcre.recursion_limit
 64 MB      134217
 32 MB      67108
 16 MB      33554
  8 MB      16777
  4 MB      8388
  2 MB      4194
  1 MB      2097
512 KB      1048
256 KB      524
*/
// PHP default is 100,000.
ini_set( "pcre.recursion_limit", "1048" ); // Check for more details https://stackoverflow.com/questions/7620910/regexp-in-preg-match-function-returning-browser-error/7627962#7627962

require 'vendor/autoload.php';

// Step 1: Read book.json
$jsonData = file_get_contents( 'book.json' );
try {
    $book_data = json_decode( $jsonData, true, 512, JSON_THROW_ON_ERROR );
} catch ( JsonException $e ) {
    echo 'Error: ' . $e->getMessage();
    die;
}

// Step 2: Get values from the JSON
$src_folder = $book_data['src'] ?? null;
$dst_folder = $book_data['dst'] ?? null;
$book_title = $book_data['title'] ?? null;
$author     = $book_data['author'] ?? null;
$publisher  = $book_data['publisher'] ?? null;

// Step 2a: Check for missing variables and throw error
if ( empty( $src_folder ) ) {
    echo 'Error: "src" folder is missing in the JSON.';
    die;
} elseif ( ! is_dir( $src_folder ) ) {
    echo 'Error: "src" folder does not exist or is not a directory.';
    die;
}

if ( empty( $dst_folder ) ) {
    echo 'Error: "dst" folder is missing in the JSON.';
    die;
}

// Create the destination folder if it doesn't exist.
if ( ! file_exists( $dst_folder )
     && ! mkdir( $dst_folder, 0777, true ) && ! is_dir( $dst_folder ) ) {
    throw new RuntimeException( sprintf( 'Directory "%s" was not created', $dst_folder ) );
    die;
}

if ( empty( $book_title ) ) {
    echo 'Error: "title" is missing in the JSON.';
    die;
}

if ( empty( $author ) ) {
    echo 'Error: "author" is missing in the JSON.';
    die;
}

if ( empty( $publisher ) ) {
    echo 'Error: "publisher" is missing in the JSON.';
    die;
}

// Step 3: Read all HTML files in the "src" directory
$html_files = glob( $src_folder . '/*.{htm,html}', GLOB_BRACE );

// Filter files using regular expression
$html_files = preg_grep( '/\.(htm|html)$/i', $html_files );

// Step 4: Loop through each HTML file, change content, and save under the same name
foreach ( $html_files as $file_name ) {
    echo 'Processing: ' . $file_name . PHP_EOL;

    // Read the content of the HTML file
    $html_content = file_get_contents( $file_name );

    $body_content = extract_book_content( $html_content );

    // Clean for unnessary tags.
    $body_content = clean_tags_1( $body_content );

    $body_content = prepare_footnotes( $body_content );

    $body_content = clean_tags_2( $body_content );

    $file_content = wrap_content( $body_content );

    echo 'Saving: ' . $file_name . PHP_EOL;

    $new_file_name = $file_name;

    // Check if the original file had a .htm extension, then replace it with .html
    if ( pathinfo( $new_file_name, PATHINFO_EXTENSION ) === 'htm' ) {
        $new_file_name = substr( $new_file_name, 0, - 3 ) . 'html';

        echo 'Renamed: ' . $file_name . ' to ' . $new_file_name . PHP_EOL;

        // Unlink the original file with .htm.
//        if ( unlink( $file_name ) ) {
//            echo 'Deleted: ' . $file_name . PHP_EOL;
//        } else {
//            echo 'Error deleting: ' . $file_name . PHP_EOL;
//        }
    }

    // Save the content to the file.
    file_put_contents( $new_file_name, $file_content );
}

// Step 5: Check if "muqadima" file exists and save it under the name "0.html"
$muqadima_file = $src_folder . '/المقدمة.html';
if ( file_exists( $muqadima_file ) ) {
    $muqadima_content = file_get_contents( $muqadima_file );
    file_put_contents( $src_folder . '/000.html', $muqadima_content );
    if ( unlink( $muqadima_file ) ) {
        echo 'Renamed "المقدمة" to : 000.html' . PHP_EOL;
    } else {
        echo 'Error renaming "المقدمة" file. Rename it manually to 000.html' . PHP_EOL;
    }
}

function extract_book_content( $html_content ) {
    // Extract content within <body> tags
    // Because preg_* can't handle long strings, we need a workaround.
/*    if ( preg_match( '/<body.*?>(.*?)<\/body>/si', $html_content, $matches ) ) {*/
//        $body_content = $matches[1];
//    } else {
//        $body_content = ''; // Handle the case where <body> tags are not found
//    }

    // This is workaround.
    // Remove everything before first <div> tag.
    $tag_pos = mb_strpos($html_content, '<div');
    if( $tag_pos === false ){
        return null;
    }

    $html_content = mb_substr($html_content, $tag_pos);

    // Get everything until </body> tag.
    $tag_pos = mb_strpos($html_content, '</body');
    if( $tag_pos === false ){
        return $html_content;
    }

    return mb_substr($html_content, 0, $tag_pos);
}

function clean_tags_1( $body_content ) {
    // Remove <div> elements with class="PageHead"
    $body_content = preg_replace( '/<div\s+class=[\'"]PageHead[\'"][^>]*>.*?<\/div>/i', '', $body_content );

    // Remove <span> elements with class="symbol"
    $body_content = preg_replace( '/<span\s+class=[\'"]symbol[\'"][^>]*>(\s*)<\/span>/im', '$1', $body_content );
    $body_content = preg_replace( '/<span\s+class=[\'"]symbol[\'"][^>]*>(.*?)<\/span>/ism', '$1', $body_content );

    // Replaces some artificial stuff.
    //$body_content = preg_replace('/<font[^>]*>([،:\[\]\.,"\'\(\)\*\/\-=]{1,2})<\/font>/ium', '$1', $body_content);
    $body_content = preg_replace( '/<font[^>]*>(.{1,2}?)<\/font>/ium', '$1', $body_content );

    // Replace <p> and </p> with <br/>
    $body_content = preg_replace( '/<p>/im', '<br/>', $body_content );
    $body_content = preg_replace( '/<\/p>/im', '<br/>', $body_content );

    // Replaces deprecated <font> with span.
    $body_content = preg_replace( '/<font[^>]*color=[\'"]?[^\'"]+[\'"]?[^>]*>(.+)<\/font>/iUm', '<span class="highlighted">$1</span>', $body_content );

    // Remove <hr>.
    $body_content = preg_replace( '/<hr[^>]*>/im', '', $body_content );

    return $body_content;
}

function clean_tags_2( $body_content ) {
    // Remove all divs.
    $body_content = preg_replace( '/<div[^>]*>/im', '', $body_content );
    $body_content = preg_replace( '/<\/div>/', ' ', $body_content );

    // Replaces white spaces.
    $body_content = preg_replace( '/&nbsp;/im', ' ', $body_content );
    $body_content = preg_replace( '/[ ]+/im', ' ', $body_content );

    return $body_content;
}

function prepare_footnotes( $body_content ) {
    // Regular expression to parse each page separately.
    return preg_replace_callback( '/<div class=[\'"]?PageText[\'"]?[^>]*>(.+?)<\/div>/us', 'replace_footnotes', $body_content );
}

function wrap_content( $body_content ) {
    return '<!DOCTYPE html><html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<link rel="stylesheet" type="text/css" href="../css/styles.css">
</head><body>' . "\n" . $body_content . "\n" . '</body></html>';
}

function replace_footnotes( $html ) {
    global $footnotes;
    $out = $html[0];
    $out = preg_replace_callback( '/<div class=[\'"]?footnote[\'"]?[^>]*>(.+?)<\/div>/us', 'collect_footnotes', $out );
    $out = preg_replace_callback( '/<sup[^>]*><span[^>]*>\((\d+)\)<\/span><\/sup>/u', 'replace_footnotes_in_text', $out );

    return $out;
}

/**
 * Collects footnotes from the bottom of each page.
 *
 * @param        $html
 *
 * @return string
 * @global array $footnotes
 */
function collect_footnotes( $html ) : string {
    global $footnotes;
    $footnotes_html = $html[0];
    // Initialize an array to store the footnotes
    $footnotes = [];

    // Find all matches
    preg_match_all( '/<span class="highlighted">\((\d+)\)<\/span>(.*?)(?:<br\/>|$)/isum', $footnotes_html, $matches, PREG_SET_ORDER );

    // Iterate through matches and populate the $footnotes array
    foreach ( $matches as $match ) {
        $fn_number               = arab2latin( $match[1] );
        $fn_text                 = trim( strip_tags( $match[2] ), " \n\r\t\v\0." );
        $footnotes[ $fn_number ] = $fn_text;
    }

    return '';
}

/**
 * Replaces footnote references in the text with the actual footnotes.
 * Actual footnotes collected from bottom of each page.
 *
 * @param        $html
 *
 * @return string
 * @global array $footnotes
 */
function replace_footnotes_in_text( $html ) : string {
    global $footnotes;

    $fn_num = arab2latin( $html[1] );

    if ( isset( $footnotes[ $fn_num ] ) ) {
        return ' <span class="footnote-inline">(' . $footnotes[ $fn_num ] . ')</span> ';
    }

    // Return as it was.
    return $html[0];
}

function arab2latin( $input ) {
    $arabicNumbers = [ '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩' ];
    $latinNumbers  = [ '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' ];

    // Replace Arabic numbers with Latin numbers.
    return str_replace( $arabicNumbers, $latinNumbers, $input );
}
