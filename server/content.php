<?php

# We'll use a couple of service decorators to analyze the content
require_once( 'zemanta_service.php' );
require_once( 'freebase_service.php' );

/**
 * Content
 *
 * Represents the text that will be analyzed
 */
class Content {
  private $services     = null;
  private $text         = null;
  private $raw_analysis = null; # raw analysis returned from Zemanta
  private $behaviors    = array();

  /**
   * __construct()
   *
   * @param $text
   */
  public function __construct( $text ) {
    $this->text     = $text;
    $this->services = array(
      'Zemanta'    => new ZemantaService()
      , 'Freebase' => new FreebaseService()
    );
  }

  /**
   * analyze
   *
   * Calls the Zemanta API to analyze a text block. Freebase and DMOZ results
   * are processed independently.
   *
   * @param   $debug
   * @return  array
   */
  public function analyze( $debug = false ) {
    if( $debug ) error_log( 'Content::analyze() Begin analysis (Zemanta)' );
    if( $debug ) error_log( 'Content::analyze() Sending text to a ' . get_class( $this->services['Zemanta'] ) . ' class instance.' );

    $zemanta = $this->services['Zemanta']->send( $this->text, null, $debug );

    if( $debug ) error_log( 'Content::analyze() Analysis retrieved from Zemanta' );

    $this->raw_analysis = json_decode( $zemanta['body'], true );
    #
    # Delegate the processing of individual components of the analysis to discrete
    # methods.
    #
    $this->behaviors['DMOZ']     = $this->dmoz( $this->raw_analysis, $debug );
    $this->behaviors['Freebase'] = $this->freebase( $this->raw_analysis, $debug );

    # Apply a filter on the results. This may or may not be necessary, but some results
    # are just junk. Use the filter() method to weed those out if it can be done
    # systematically.
    return $this->filter( $debug );
  }

  /**
   * PRIVATE
   */

  /**
   * dmoz
   *
   * Extracts DMOZ data Zemanta results.
   *
   * @param 	$analysis   Raw analysis (JSON decoded) returned from Zemanta
   * @param   $debug
   * @return	array
   */
  private function dmoz( $analysis, $debug = false ) {
    if( $debug ) error_log( 'Content::dmoz() Extracting DMOZ results' );

    $behaviors = array();

    foreach( $analysis['categories'] as $category ) {
      $confidence = $category['confidence'];
      $behavior   = basename( $category['name'] );
      $categories = dirname( $category['name'] );

      if( !array_key_exists( $behavior, $behaviors ) ) {
        if( $debug ) error_log( 'Content::dmoz() Initializing the DMOZ data structures (' . $behavior . ')' );

        $behaviors[$behavior]               = array();
        $behaviors[$behavior]['categories'] = array();
        $behaviors[$behavior]['confidence'] = 0;
      }

      if( $debug ) error_log( 'Content::dmoz() Adding "' . $categories . '" to the DMOZ category list' );

      # Strip the explicit "root" category (Top) from the path in favor if the Unix "/"
      array_push( $behaviors[$behavior]['categories'], preg_replace( '/^Top/', '', $categories ) );

      # The same category could appear multiple times. If that's the case, only retain the
      # largest confidence value.
      if( $confidence > $behaviors[$behavior]['confidence'] ) {
        if( $debug ) error_log( 'Content::dmoz() Upgrading DMOZ confidence from ' . $behaviors[$behavior]['confidence'] . ' to ' . $confidence );

        $behaviors[$behavior]['confidence'] = $confidence;
      }
    }

    return $behaviors;
  }

  /**
   * freebase
   *
   * Extracts behaviors from the Zemanta results. We're interested in the Freebase "topics"
   * because these help us ensure that the result is disambiguated and can be placed in
   * some kind of context.
   *
   * @param   $analysis   Raw analysis (JSON decoded) returned from Zemanta
   * @param   $debug
   * @return  array
   */
  private function freebase( $analysis, $debug = false ) {
    if( $debug ) error_log( 'Content::freebase() Extracting Freebase topics' );

    $behaviors = array();

    foreach( $analysis['markup']['links'] as $disambiguated_link ) {
      $confidence = $disambiguated_link['confidence'];

      #
      # Freebase results are intermingled with other "rich markup" results.
      # Traverse in reverse because the Freebase link is almost always the second to last
      #
      for( $i = count( $disambiguated_link['target'] ) - 1; $i >= 0; $i-- ) {
        $link     = $disambiguated_link['target'][$i];
        $behavior = $link['title'];

        #
        # Freebase topics could be identified by their human readable "id" values or
        # by a less readable guid. The former is prefered by Zemanta when available.
        #
        if ( preg_match( '/^http:\/\/rdf.freebase.com\/ns\/en\//', $link['url'] ) ) {
          $freebase_id = '/en/' . basename( $link['url'] );
        }
        else if( preg_match( '/^http:\/\/rdf.freebase.com\/ns\/guid\//', $link['url'] ) ) {
          $freebase_id = '#' . basename( $link['url'] );
        }
        else {
          continue;
        }

        if( !array_key_exists( $behavior, $behaviors ) ) {
          if( $debug ) error_log( 'Content::freebase() Adding "' . $behavior . '"' );

          $behaviors[$behavior]               = array();
          $behaviors[$behavior]['id']         = $freebase_id;
          $behaviors[$behavior]['confidence'] = $confidence;
          $behaviors[$behavior]['categories'] = $this->freebase_categories( $freebase_id, $debug );

          if( $debug ) error_log( '--> Content::freebase() Categories: "' . json_encode( $behaviors[$behavior]['categories'] ) . '"' );
        }
      }
    }

    return $behaviors;
  }

  /**
   * freebase_categories
   *
   * Retrieves the Freebase taxonomy hierarchy for a given topic ID.
   * Category > Domain > Type > Topic
   *
   * @param 	$id
   * @param   $debug
   * @return	array
   */
  private function freebase_categories( $id, $debug = false ) {
    #
    # The categorization of a topic is determined by passing a MQL query
    # to the Freebase read API.
    #
    $categories = array();
    $id_key     = preg_match( '/^#[a-f0-9]{32}$/', $id ) ? 'guid' : 'id';
    $mql        = '{ "query": { ' .
      '"' . $id_key . '": "' . stripslashes( $id ) . '" ' .
      ', "type": [{ ' .
      '     "id": null ' .
      '     , "name": null ' .
      '     , "key": [{ "namespace": { "key": [{ "namespace": "/" }] } }] ' .
      '     , "d1:domain": { "id": "/common", "optional": "forbidden" } ' .
      '     , "d2:domain": { "id": "/freebase", "optional": "forbidden" } ' .
      '     , "d3:domain": { "id": "/type", "optional": "forbidden" } ' .
      '     , "domain": { ' .
      '         "id": null ' .
      '         , "name": null ' .
      '         , "/freebase/domain_profile/category": [{ ' .
      '             "*": null ' .
      '             , "id": null ' .
      '             , "key": [{ "namespace": { "key": [ { "namespace": "/" }] } }] ' .
      '             , "name": null ' .
      '             , "optional": true ' .
      '         }] ' .
      '     } ' .
      '}] ' .
    '} }';

    $response = $this->services['Freebase']->send( $id, $mql, $debug );
    $mqlread  = json_decode( $response['body'], true );

    if( array_key_exists( 'result', $mqlread ) && !is_null( $mqlread['result'] ) ) {
      #
      # A topic may have more than 1 type.
      # A type will have 1 and only 1 domain
      # A domain may have more than 1 domain
      #
      foreach( $mqlread['result']['type'] as $type ) {
        $stack = array();

        array_unshift( $stack, $type['name'] );
        array_unshift( $stack, $type['domain']['name'] );

        foreach( $type['domain']['/freebase/domain_profile/category'] as $category ) {
          array_unshift( $stack, $category['name'] );

          if( $debug ) error_log( 'Content::freebase_taxonomy() Adding Freebase category "' . '/' . join( '/', $stack ) . '"' );

          array_push( $categories, '/' . join( '/', $stack ) );
          #
          # This completes the current taxonomy position, so pop it off the stack
          # and prepare for the next, if any.
          #
          array_shift( $stack );
        }
      }
    }
    else {
      if( $debug ) error_log( 'Content::freebase_taxonomy() No MQL results. This may not be an error.' );
    }

    return $categories; # an array of /-delimited categories, e.g. /Path/To/Topic
  }

  /**
   * filter
   *
   * Filters the set of behaviors. NLP (Natural Language Processing) is hard and some of the
   * results are just crap. If there's a systematic means of identifying the crap, it can be
   * applied here.
   *
   * @param   $debug      If true, enables additional writes to the error log
   * @return  array
   */
  private function filter( $debug ) {
    if( $debug ) error_log( 'Content::filter() Filtering "' . join( ', ', array_keys( $this->behaviors['DMOZ'] ) ) . '" (DMOZ) and "' . join( ', ', array_keys( $this->behaviors['Freebase'] ) ) . '" (Freebase)' );

    /** No filtering is being done */

    return $this->behaviors;
  }
}
