<?php
/*
Plugin Name: Flow: Calendar feeds
Description: Calendar feeds for band members and administrator
Version: 0.1
Author: Huw Roberts
Author URI: http://www.rootsy.co.uk
Copyright: Huw Roberts
Text Domain: flow-calendar-feeds
*/

if( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class FlowCalendar {

  protected $wp;
  protected $wpdb;

  /**
     * Returns the instance.
     *
     * @access public
     * @return object
     */
    public static function get_instance()
    {

        static $instance = null;

        if (is_null($instance)) {
            $instance = new self;
            $instance->setup();
        }

        return $instance;
    }

    /**
     * Constructor method.
     *
     * @access private
     * @return void
     */
    private function __construct()
    {}


  /**
   * Class constructor
   */
  public function setup() {

    add_action( 'init', array( $this, 'init' ), 1, 0 );

  }

  /**
   * Initialise
   */
  function init() {

    global $wp, $wpdb;

    $this->wp = $wp;
    $this->wpdb = $wpdb;

    // Include ical feed wrapper class 
    require_once('classes/class.ical-feed.php');
    
    // Rewrites and tags
    add_action( 'init', array( $this, 'rewrite_rules' ), 10, 0 );
    add_action( 'init', array( $this, 'rewrite_tags' ), 10, 0 );

    // Add the feed
    add_action('init', array( $this, 'ical_feed_init' ) );

    // Add a feed link for all events to bottom of job_sheet post list 
    add_action('manage_posts_extra_tablenav', array($this, 'put_all_events_calendar_link'), 10, 1);

  }


  /**
   * Initialise the calendar feed
   */
  function ical_feed_init() {

    add_feed('ical', array( $this, 'ical_feed_prepare' ) );

  }


  /**
   * Prepare calendar feed
   */
  function ical_feed_prepare() {

    // A string, either user token or 'all'
    $events_for = $this->wp->query_vars['user_id_token'];

    if( $events_for !== 'all' ) {

      $events_for = flow_rand_tokens()->get_user_id_from_token($events_for);

    } 

    if( $events_for ) {

      $ical = new FlowUserEventFeed($events_for);
      $vcal = $ical->get_vcal();

      $this->ical_render( $vcal );

    }

  }

  /**
   * Render calendar feed
   */
  function ical_render( $vcal ) {

    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: attachment; filename="cal.ics"');

    echo $vcal->render();

  }


  /**
   * Register rewrites
   */
  function rewrite_rules() {

      // BM Feeds
      add_rewrite_rule(
        '^feed/ical/([^/]*)/?',
        'index.php?feed=ical&user_id_token=$matches[1]',
        'top'
      );

  }


  /**
   * Rewrite tags
   */
  function rewrite_tags() {

    add_rewrite_tag('%user_id_token%', '([^&]+)');

  }


  /**
   * Display calendar feed link for all events beneath 
   * job_sheet post list - visible only to administrators
   * and editors.
   * 
   * @param String   $which   Top or bottom
   * @return String
   */
  function put_all_events_calendar_link($which) {

    $screen = get_current_screen();
    
    if( $screen->parent_file !== 'edit.php?post_type=job_sheet' ) return;

    if( $which === 'top' ) return;

    $icon = '<span class="dashicons dashicons-calendar-alt"></span>';

    echo '<div class="job-sheet-ical-all">';

    printf
    (
      '%s&nbsp;<a href="%s" title="iCal Feed">%s</a>', $icon, get_site_url( null, 'feed/ical/all' ), 'iCal Feed'
    );

    echo '</div>';

  }

}



/**
 * Gets the instance of the `FlowTCPDF` class.
 *
 * @access public
 * @return object
 */
function flow_calendar()
{
    return FlowCalendar::get_instance();
}

// Let's roll!
flow_calendar();