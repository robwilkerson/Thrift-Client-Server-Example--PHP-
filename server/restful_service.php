<?php

abstract class RestfulService {
  public function send( $uri, $options = array(), $debug = false ) {
    $default_options = array(
      CURLOPT_RETURNTRANSFER => true
    );

    $req = curl_init( $uri );

    if( !empty( $options ) ) {
      foreach ( $default_options as $key => $value ) {
        if( !array_key_exists( $key, $options ) ) {
          $options[$key] = $value;
        }
      }
    }
    else {
      $options = $default_options;
    }
    curl_setopt_array( $req, $options );

    if( $debug ) error_log( 'Sending call to ' . $uri );

    $response = curl_exec( $req );
    $headers  = curl_getinfo( $req );

    curl_close( $req );

    return array( 'body' => $response, 'headers' => $headers );
  }
}
