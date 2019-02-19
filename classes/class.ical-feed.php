<?php 
class FlowUserEventFeed {

  protected $user_id;

  /**
   * Constructor 
   * 
   * @param Mixed $events_for    User ID or special string 'all'
   */
  public function __construct($events_for) {

    global $wpdb;
    $this->wpdb = $wpdb;

    if( is_numeric( $events_for ) ) {

      $this->user_id = $uid;
      $this->fetch_all = false;

    }
    elseif ( $events_for == 'all' ) {

      $this->user_id = null;
      $this->fetch_all = true;

    }
    else {
      return;
    }

    // Autoloader for Eluceo\iCal
    spl_autoload_register( array($this, 'ical_autoload') );
    
    $this->vcalendar = new \Eluceo\iCal\Component\Calendar('Silk Street Flow');
    $this->make_entries();

  }


  /**
   * 
   */
  private function make_entries() {

    $events = $this->fetch_all ? $this->get_events() : $this->get_user_events();

    foreach($events as $event_group) {

      foreach($event_group as $row) {

        $post_id    = $row->ID;
        $event_from = new \DateTime( get_field('event_details_date_from', $post_id ), new DateTimeZone('Europe/London') );
        $event_to   = new \DateTime( get_field('event_details_date_to', $post_id ), new DateTimeZone('Europe/London') );
        
        $event = new \Eluceo\iCal\Component\Event();
        $event
          ->setDtStart( $event_from )
          ->setDtEnd( $event_to )
          ->setNoTime( false )
          ->setSummary( $this->make_summary( $post_id ) )
          ->setDescription( $this->make_description( $post_id ) )
          ->setUseUtc( true );

        $map = get_field( 'venue_address_map_venue_name', $post_id );

        if( !empty($map) ) {

          // Venue address, venue name (title), long/lat
          $event->setLocation( 
            $this->get_venue_address( $post_id, "\n" ), 
            get_field( 'venue_address_map_venue_name', $post_id ),
            implode( ',', array( $map['lat'], $map['lng'] ) )
          );

        }

        $this->vcalendar->addComponent($event);

      }

    }

  }

  
  /**
   * Getter for the calendar object
   */
  public function get_vcal() {

    return $this->vcalendar;

  }


  /**
   * 
   */
  private function make_summary($post_id) {

    $job_number = get_post_meta( $post_id, 'job_sheet_auto_id', true );
    $town_city  = get_field('venue_address_map_town_city', $post_id);

    return 'Job ' . $job_number . ' (' . $town_city . ')';

  }


  /**
   * 
   */
  private function get_venue_address($post_id, $separator) {

    $address = array();

    if( get_field( 'venue_address_map_venue_name', $post_id ) ) $address[] = get_field( 'venue_address_map_venue_name', $post_id );
    if( get_field( 'venue_address_map_address_line_1', $post_id ) ) $address[] = get_field( 'venue_address_map_address_line_1', $post_id );
    if( get_field( 'venue_address_map_address_line_2', $post_id ) ) $address[] = get_field( 'venue_address_map_address_line_2', $post_id );
    if( get_field( 'venue_address_map_town_city', $post_id ) ) $address[] = get_field( 'venue_address_map_town_city', $post_id );
    if( get_field( 'venue_address_map_county', $post_id ) ) $address[] = get_field( 'venue_address_map_county', $post_id );
    if( get_field( 'venue_address_map_post_code', $post_id ) ) $address[] = get_field( 'venue_address_map_post_code', $post_id );

    return implode($separator, $address);

  }


  /**
   * 
   */
  private function make_description($post_id) {

    $description = array();
    $workflow = get_field('booking_confirmation_workflow', $post_id);

    // Chosen band/act
    if($workflow == 'band') {
      $chosen_act = get_field('chosen_band_details_band_name', $post_id)->post_title;
    }
    else if($workflow == 'act') {
      $chosen_act = get_field('act_details_act_chosen_act', $post_id)->post_title;
    }

    // Act, arrival time, map link
    $description[] = "Chosen Act:\n" . $chosen_act;
    $description[] = "Band Arrival Time:\n" . get_field('contract_data_band_arrival_time', $post_id);
    $description[] = "Map:\n" . 'https://maps.google.co.uk?q=' . urlencode( get_field( 'venue_address_map_post_code', $post_id ) );
    
    // Personnel
    if( $workflow == 'band' ) $description[] = "Personnel:\n" . $this->get_personnel($post_id, ', ');

    // Venue address
    $description[] = "Venue Address:\n" . $this->get_venue_address($post_id, "\n");

    // Return
    return implode("\n\n", $description);

  }


  /**
   * 
   */
  private function get_personnel($post_id, $separator) {

    $personnel = array();

    if( get_field( 'chosen_band_details_personnel', $post_id ) ) {

      foreach( get_field( 'chosen_band_details_personnel', $post_id ) as $person ) {

        $personnel[] = $person['member_user']['display_name'];

      }

    }

    return implode($separator, $personnel);

  }
  


  /**
   * Get user's events 
   * 
   * @param int $user_id   The User ID to fetch events for
   * @return array
   */
  public function get_user_events() {

    // Query for booking_confirmation_workflow = band

    $sql_band = "SELECT {$this->wpdb->prefix}posts.*
      FROM {$this->wpdb->prefix}posts 
      INNER JOIN {$this->wpdb->prefix}postmeta
      ON {$this->wpdb->prefix}posts.ID = {$this->wpdb->prefix}postmeta.post_id
      INNER JOIN {$this->wpdb->prefix}postmeta AS mt1
      ON {$this->wpdb->prefix}posts.ID = mt1.post_id
      INNER JOIN {$this->wpdb->prefix}postmeta AS mt2
      ON {$this->wpdb->prefix}posts.ID = mt2.post_id
      WHERE {$this->wpdb->prefix}postmeta.meta_key = 'flow_state'
      AND {$this->wpdb->prefix}postmeta.meta_value = 'customer_confirmed'
      AND mt1.meta_key = 'booking_confirmation_workflow'
      AND mt1.meta_value = 'band'
      AND mt2.meta_key = 'flow_bm_personnel_uid'
      AND mt2.meta_value = {$this->user_id}
      AND {$this->wpdb->prefix}posts.post_type = 'job_sheet'
      AND {$this->wpdb->prefix}posts.post_status = 'publish'
      GROUP BY {$this->wpdb->prefix}posts.ID
      ORDER BY {$this->wpdb->prefix}posts.post_date DESC";

    // Query for booking_confirmation_workflow = act

    $sql_act = "SELECT wp_posts.*
      FROM wp_posts 
      INNER JOIN wp_postmeta
      ON ( {$this->wpdb->prefix}posts.ID = {$this->wpdb->prefix}postmeta.post_id ) 
      INNER JOIN {$this->wpdb->prefix}postmeta AS mt1
      ON ( {$this->wpdb->prefix}posts.ID = mt1.post_id ) 
      INNER JOIN {$this->wpdb->prefix}postmeta AS mt2
      ON ( {$this->wpdb->prefix}posts.ID = mt2.post_id )
      WHERE ( ( {$this->wpdb->prefix}postmeta.meta_key = 'flow_state'
      AND {$this->wpdb->prefix}postmeta.meta_value = 'customer_confirmed' ) 
      AND ( ( mt1.meta_key = 'booking_confirmation_workflow'
      AND mt1.meta_value = 'act' ) 
      AND ( mt2.meta_key = 'act_details_act_manager'
      AND mt2.meta_value = {$this->user_id} ) ) )
      AND {$this->wpdb->prefix}posts.post_type = 'job_sheet'
      AND (({$this->wpdb->prefix}posts.post_status = 'publish'))
      GROUP BY {$this->wpdb->prefix}posts.ID
      ORDER BY {$this->wpdb->prefix}posts.post_date DESC";

    // Get the results 

    $results_band = $this->wpdb->get_results( $sql_band );
    $results_act  = $this->wpdb->get_results( $sql_act );

    // Return assoc array 

    return array(
      'band' => $results_band,
      'act'  => $results_act,
    );

  }


  /**
   * Get all events, regardless of personnel 
   *
   * @return array
   */
  public function get_events() {

    $sql = "SELECT {$this->wpdb->prefix}posts.*
      FROM {$this->wpdb->prefix}posts 
      INNER JOIN {$this->wpdb->prefix}postmeta
      ON {$this->wpdb->prefix}posts.ID = {$this->wpdb->prefix}postmeta.post_id
      WHERE {$this->wpdb->prefix}postmeta.meta_key = 'flow_state'
      AND {$this->wpdb->prefix}postmeta.meta_value = 'customer_confirmed'
      AND {$this->wpdb->prefix}posts.post_type = 'job_sheet'
      AND {$this->wpdb->prefix}posts.post_status = 'publish'
      GROUP BY {$this->wpdb->prefix}posts.ID
      ORDER BY {$this->wpdb->prefix}posts.post_date DESC";


    // Get the results 

    $results = $this->wpdb->get_results( $sql );

    // Return assoc array 

    return array(
      'all' => $results,
    );

  }


  /**
   * Auto-loader for Eluceo iCal lib
   * https://github.com/markuspoerschke/iCal
   * 
   * @param  string $class class name.
   * @return mixed  false if not plugin's class or void
   */
  private function ical_autoload($class) {

    $parts = explode('\\', $class);

    if (array_shift($parts) != 'Eluceo') {
        return false;
    }

    $file = trailingslashit(dirname(__FILE__)) . trailingslashit('../lib') . implode('/', $parts) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }

  }

}