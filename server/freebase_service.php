<?php

require_once( 'restful_service.php' );

/**
 * class FreebaseService
 *
 * Accesses the Freebase API to build a shallow taxonomy for a given topic.
 */
class FreebaseService extends RestfulService {
  const SERVICE_BASE_URI = 'http://api.freebase.com/api/service/mqlread?query=';

  public function send( $id, $mql, $debug = false ) {
    if( $debug ) error_log( 'Sending MQL to Freebase for id ' . $id );

    return parent::send( self::SERVICE_BASE_URI . $mql, null, $debug );
  }
}
