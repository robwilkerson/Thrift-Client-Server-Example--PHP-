<?php
/**
 * This client demonstrates Thrift's PHP connectivity.
 * Client connects to server host:port/server_path.
 */

# Copy and edit config.sample.php as required
include( 'config.php' );

# Thrift internals
$GLOBALS['THRIFT_ROOT'] = '../lib/php/src';

require_once $GLOBALS['THRIFT_ROOT'] . '/Thrift.php';
require_once $GLOBALS['THRIFT_ROOT'] . '/protocol/TBinaryProtocol.php';
require_once $GLOBALS['THRIFT_ROOT'] . '/transport/TSocket.php';
require_once $GLOBALS['THRIFT_ROOT'] . '/transport/THttpClient.php';
require_once $GLOBALS['THRIFT_ROOT'] . '/transport/TBufferedTransport.php';

/**
 * Include bindings generated from the IDL file
 * `thrift -r --gen php:server zemanta.thrift`
 */
$GEN_DIR = '../gen-php';

require_once $GEN_DIR . '/zemanta/zemanta.php';

try {
  # Create a socket (boilerplate stuff)
  # Connect to http://$serverHost:$serverPort/$phpServerPath
  #   - Create a socket
  #   - Buffer it (raw sockets are slow, slow, slow)
  #   - Roll it all up in a protocol, binary-style
  $socket    = new THttpClient( $server_host, $server_port, $directory_index );
  $transport = new TBufferedTransport( $socket, 1024, 1024 );
  $protocol  = new TBinaryProtocol( $transport );

  # Instantiate a client
  $client = new zemantaClient( $protocol );
  $transport->open();

  # BEGIN the heavy lifting
  # Randomly pull a content file from the articles/ directory
  $article_prefix = 'articles/article-';
  if( !empty( $_GET['article'] ) && file_exists( $article_prefix . $_GET['article'] . '.txt' ) ) {
    $article = $_GET['article'];
  }
  else {
    $files = glob( $article_prefix . '*' );
    $article = rand( 1, count( $files ) );
  }

  # Extract the file content and do the analysis
  echo '<h1>Sending the content of ' . $article_prefix . $article . '.txt off for analysis</h1>';

  $content = file_get_contents( $article_prefix . $article . '.txt' );

  echo '<h2>Content</h2>';
  echo '<div style="padding: 20px; background: #ffc;">' . $content . '</div>';

  $analysis = $client->analyze( $content );
  # END the heavy lifting

  #
  # Dump the result
  #
  echo '<h2>That\'s it. All done.</h2>';
  echo '<pre>';
  var_dump( $analysis );
  echo '</pre>';
}
catch( TException $tx ) {
  # Catch a generic Thrift exception. More boilerplate stuff.
  # Something like a missing server would toss this back
  echo '<p><strong>Something went horribly, horribly wrong: ' . $tx->getMessage() . '</strong></p>';
}
