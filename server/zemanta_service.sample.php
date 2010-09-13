<?php

require_once( 'restful_service.php' );

/**
 * class ZemantaService
 *
 * Engages the Zemanta contextual intelligence engine.
 */
class ZemantaService extends RestfulService {
  const SERVICE_BASE_URI = 'http://api.zemanta.com/services/rest/0.0/';
  const API_KEY          = 'YOUR_ZEMANTA_API_KEY_HERE';
  const METHOD           = 'zemanta.suggest';

  private $params        = array();

  public $format         = 'json';

  public function send( $text, $format = null, $debug = false ) {
    $format = is_null( $format ) ? $this->format : $format;
    $params = $this->prepare( $text, $format, $debug );

    if( $debug ) error_log( 'Sending text to Zemanta for analysis in ' . $format . ' format' );

    return parent::send( self::SERVICE_BASE_URI, $params, $debug );
  }

  /**
   * PRIVATE
   */

  private function prepare( $text, $format, $debug = false ) {
    if( $debug ) error_log( 'Preparing cURL options for the Zemanta service call' );

    $params = array(
      'method'              => self::METHOD
      , 'api_key'           => self::API_KEY
      , 'format'            => $format
      , 'return_images'     => 0
      , 'return_articles'   => 0
      , 'return_rdf_links'  => 1
      , 'markup_limit'      => 0
      , 'return_categories' => 'dmoz'
      , 'text'              => $text
    );

    $encoded = array();
    foreach( $params as $key => $value ) {
      $encoded[urlencode( $key )] = urlencode( $value );
    }

    if( $debug ) {
      $log_params = $encoded;
      $log_params['text'] = 'Omitted to reduce noise';

      error_log( 'Zemanta params encoded: ' . json_encode( $log_params ) );
    }

    return array(
      CURLOPT_POST => 1
      , CURLOPT_POSTFIELDS => http_build_query( $encoded )
    );
  }
}
