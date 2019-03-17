<?php
  /**
   * Plugin Name: Importa partite
   * Plugin URI: http://www.basketgardolo.it
   * Description: This plugin creates event posts with data fetched from Google Calendar.
   * Version: 1.0.0
   * Author: Cristian Stenico
   * Author URI: http://www.basketgardolo.it
   * License: MIT
   */
  include('CalFileParser.php');
  function get_lat_long($address)
  {
    $address = str_replace(" ", "+", $address);
    $json = file_get_contents("http://maps.google.com/maps/api/geocode/json?address=$address&sensor=false");
    $json = json_decode($json);
    $lat = $json->{'results'}[0]->{'geometry'}->{'location'}->{'lat'};
    $long = $json->{'results'}[0]->{'geometry'}->{'location'}->{'lng'};
    return array(
      'lat' => $lat,
      'long' => $long
    );
  }
  function importa_partite()
  {
    $cal = new CalFileParser();
?>
<div class="wrap">
  <h2>Welcome To My Plugin</h2>
  <?php
    $calendars = array(
      'https://calendar.google.com/calendar/ical/eksgn66cm4li0el0srbqhi474k%40group.calendar.google.com/public/basic.ics', // Aquilotti
      'https://calendar.google.com/calendar/ical/ofmjd4o0f17dfq4upnh17tqoek%40group.calendar.google.com/public/basic.ics', // Esordienti
      'https://calendar.google.com/calendar/ical/ld2ruqiqfbmkrcp55knd6q96qc%40group.calendar.google.com/public/basic.ics', // Under 13
      'https://calendar.google.com/calendar/ical/b1nhq8jca84lf9rc3fdlmhrlv0%40group.calendar.google.com/public/basic.ics', // Under 14
      'https://calendar.google.com/calendar/ical/fv40vb09f1cr706eu6ctefg8m0%40group.calendar.google.com/public/basic.ics', // Under 15
      'https://calendar.google.com/calendar/ical/mgsi7khipq97kc1bms0j2vptn4%40group.calendar.google.com/public/basic.ics', // Under 16
      'https://calendar.google.com/calendar/ical/op2uteefg5rehbr7kr0jfvbpcs%40group.calendar.google.com/public/basic.ics', // Under 18
      'https://calendar.google.com/calendar/ical/tcv8h89alv26tbqdrjnjt62gmg%40group.calendar.google.com/public/basic.ics', // Under 20
      'https://calendar.google.com/calendar/ical/m4qodt8k1acemg4q50g71jq8qo%40group.calendar.google.com/public/basic.ics', // Promozione
      'https://calendar.google.com/calendar/ical/i9mkhjsucjo06h5h8fa2obak1s%40group.calendar.google.com/public/basic.ics'  // Serie D
    );
    foreach ($calendars as $calendar) {
      $gardolo = null;
      $gardolo_u20 = null;
      date_default_timezone_set('UCT');
      $example = $cal->parse($calendar);
      foreach ($example as $event) {
        $title = $event['summary'];
        $title = str_replace('vs', '-', $title);
        $title = str_replace(' BC ', ' ', $title);
        $title = substr(explode(':', $title)[1], 1);
        $title = ucwords(strtolower($title));
        $startDate = $event['dtstart']->format('Y-m-d H:i:s');
        $league_name = $event['description'];
        $league_name = str_replace('Under 20', 'Promozione', $league_name);
          
        // Squadra A
        $title_a = str_replace('vs', '-', explode(':', $event['summary'])[1]);
        $title_a = trim(explode('-', $title_a)[0]);
        $title_a = ucwords(strtolower($title_a));
        $post_arr = array(
          'title' => $title_a,
          'post_type' => 'sp_team'
        );
        $teams = get_posts($post_arr);
        if (!$teams) {
          $post_arr = array(
            'post_title' => $title_a,
            'post_type' => 'sp_team',
            'post_status' => 'publish'
          );
          $team_ID_a = wp_insert_post($post_arr);
        } else {
          $team_ID_a = $teams[0]->ID;
        }
          
        // Squadra B
        $title_b = str_replace('vs', '-', explode(':', $event['summary'])[1]);
        $title_b = trim(explode('-', $title_b)[1]);
        $title_b = ucwords(strtolower($title_b));
        $post_arr = array(
            'title' => $title_b,
            'post_type' => 'sp_team'
        );
        $teams = get_posts($post_arr);
        if (!$teams) {
            $post_arr = array(
                'post_title' => $title_b,
                'post_type' => 'sp_team',
                'post_status' => 'publish'
            );
            $team_ID_b = wp_insert_post($post_arr);
        } else {
            $team_ID_b = $teams[0]->ID;
        }

        $post_arr = array(
          'post_type' => 'sp_event',
          'date_query' => array(
            array(
              'year' => intval($event['dtstart']->format('Y')),
              'month' => intval($event['dtstart']->format('m')),
              'day' => intval($event['dtstart']->format('d')),
              'hour' => intval($event['dtstart']->format('H')),
              'minute' => intval($event['dtstart']->format('i'))
            ),
          ),
          'post_status' => array('publish', 'future'),
          'tax_query' => array(
            'relation' => 'AND',
            array(
              'taxonomy' => 'sp_league',
              'field' => 'name',
              'terms' => array($league_name)
            )
          ),
          'meta_query' => array(
            array(
              'key' => 'sp_team',
              'value' => array($team_ID_a, $team_ID_b)
            )
         )
        );
        $presente = get_posts($post_arr);
        if ($presente) {
          printf('<p><b>Evento già presente:</b> %s - %s -> %s</p>', $league_name, $title, $event['dtstart']->format('d/m/Y'));		   
          $event_ID = $presente[0]->ID;
        }
        if (!$presente) {
          $post_arr = array(
            'post_title' => $title,
            'post_type' => 'sp_event',
            'post_date' => $event['dtstart']->format('Y-m-d H:i:s'),
            'post_status' => 'publish'
          );
          $event_ID = wp_insert_post($post_arr);
          // Partita di campionato (non amichevole)
          add_post_meta($event_ID, 'sp_format', 'league', true);
          printf('<p><b>Evento aggiunto:</b> %s - %s</p>', $league_name, $title);
        }
        
        // Categoria di campionato
        $league = get_term_by('name', $league_name, 'sp_league');
        if (!$league) {
          $league = wp_insert_term($league_name, 'sp_league')['term_id'];
        } else {
          $league = $league->term_id;
        }
        if (!$presente)
          wp_set_object_terms($event_ID, $league, 'sp_league');
  
        // Stagione
        $year = intval($event['dtstart']->format('Y'));
        if ($event['dtstart']->format('m') > 7) {
          $season_name = $year . '/' . ($year + 1);
        } else {
          $season_name = ($year - 1) . '/' . $year;
        }
        $season = get_term_by('name', $season_name, 'sp_season');
        if (!$season) {
          $season = wp_insert_term($season_name, 'sp_season')['term_id'];
        } else {
          $season = $season->term_id;
        }
        if (!$presente)
          wp_set_object_terms($event_ID, $season, 'sp_season');
    
        // Campo
        $venue = explode('-', $event['location']);
        $venue_name = trim($venue[0]);
        $venue_address = trim($venue[1]);
        $venue = get_term_by('name', $venue_name, 'sp_venue');
        if (!$venue) {
          $venue = wp_insert_term($venue_name, 'sp_venue')['term_id'];
          $lat_long = get_lat_long($venue_address);
          print_r($lat_long);
          $venue_meta = array(
            'sp_address' => $venue_address,
            'sp_latitude' => (string)$lat_long['lat'],
            'sp_longitude' => (string)$lat_long['long']
          );
          add_option('taxonomy_' . $venue, $venue_meta);
        } else {
          $venue = $venue->term_id;
        }
        if (!$presente)
          wp_set_object_terms($event_ID, $venue, 'sp_venue');
          
        $nome_squadra = '';
        // Squadra A
        if (strpos($title_a, 'Gardolo') !== false) {
          $nome_squadra = $league_name;
          if (strpos($title_a, 'Gardolo U20') !== false) {
            $nome_squadra = 'Promozione U20';
            $gardolo_u20 = $team_ID_a;
          } else {
            $gardolo = $team_ID_a;
          }
        }
        if (!has_term($venue, 'sp_venue', $team_ID_a)) {
          wp_set_object_terms($team_ID_a, $venue, 'sp_venue', true);
        }
        if (!has_term($season, 'sp_season', $team_ID_a)) {
          wp_add_object_terms($team_ID_a, $season, 'sp_season');
        }
        if (!has_term($league, 'sp_league', $team_ID_a)) {
          wp_add_object_terms($team_ID_a, $league, 'sp_league');
        }
        if (!$presente)
          add_post_meta($event_ID, 'sp_team', $team_ID_a);
        // Squadra B
        if (strpos($title_b, 'Gardolo') !== false) {
          $nome_squadra = $league_name;
          if (strpos($title_b, 'Gardolo U20') !== false) {
            $nome_squadra = 'Promozione U20';
            $gardolo_u20 = $team_ID_b;
          } else {
            $gardolo = $team_ID_b;
          }
        }
        if (!has_term($season, 'sp_season', $team_ID_b)) {
          wp_add_object_terms($team_ID_b, $season, 'sp_season');
        }
        if (!has_term($league, 'sp_league', $team_ID_b)) {
          wp_add_object_terms($team_ID_b, $league, 'sp_league');
        }
        if (!$presente)
          add_post_meta($event_ID, 'sp_team', $team_ID_b);

        // Lista giocatori
        $title = $nome_squadra . ' ' . $season_name;
        $post_arr = array(
          'title' => $title,
          'post_type' => 'sp_list'
        );
        $lists = get_posts($post_arr);
        if (!$lists) {
          $post_arr = array(
            'post_title' => $title,
            'post_type' => 'sp_list',
            'post_status' => 'publish'
          );
          $list_ID = wp_insert_post($post_arr);
        } else {
          $list_ID = $lists[0]->ID;
        }
        if (!has_term($season, 'sp_season', $list_ID)) {
          wp_add_object_terms($list_ID, $season, 'sp_season');
        }
        if (!has_term($league, 'sp_league', $list_ID)) {
          wp_add_object_terms($list_ID, $league, 'sp_league');
        }
        if (! get_post_meta($list_ID, 'sp_team', true)) {
          if (strpos($nome_squadra, 'Promozione U20') !== false) {
            var_dump($gardolo_u20);
            add_post_meta($list_ID, 'sp_team', $gardolo_u20, true);
          } else {
            add_post_meta($list_ID, 'sp_team', $gardolo, true);
          }
        }
        if (!get_post_meta($list_ID, 'sp_format', true)) {
          add_post_meta($list_ID, 'sp_format', 'list', true);
        }
        if (!get_post_meta($list_ID, 'sp_select', true)) {
          add_post_meta($list_ID, 'sp_select', 'auto', true);
        }
        if (!get_post_meta($list_ID, 'sp_caption', true)) {
          add_post_meta($list_ID, 'sp_caption', 'Formazione', true);
        }

        // Calendario partite
        $title = 'Partite ' . $league_name . ' ' . $season_name;
        $post_arr = array(
          'title' => $title,
          'post_type' => 'sp_calendar'
        );
        $calendars = get_posts($post_arr);
        if (!$calendars) {
          $post_arr = array(
            'post_title' => $title,
            'post_type' => 'sp_calendar',
            'post_status' => 'publish'
          );
          $calendar_ID = wp_insert_post($post_arr);
        } else {
          $calendar_ID = $calendars[0]->ID;
        }
        if (!has_term($league, 'sp_league', $calendar_ID)) {
          wp_add_object_terms($calendar_ID, $league, 'sp_league');
        }
        if (!has_term($season, 'sp_season', $calendar_ID)) {
          wp_add_object_terms($calendar_ID, $season, 'sp_season');
        }
        if (!get_post_meta($calendar_ID, 'sp_team', true)) {
          if (strpos($nome_squadra, 'Promozione U20')) {
            add_post_meta($calendar_ID, 'sp_team', $gardolo_u20, true);
          } else {
            add_post_meta($calendar_ID, 'sp_team', $gardolo, true);
          }
        }
        if (!get_post_meta($calendar_ID, 'sp_format', true)) {
          add_post_meta($calendar_ID, 'sp_format', 'blocks', true);
        }

        // Pagina squadra
        $title = $nome_squadra . ' ' . $season_name;
        $post_arr = array(
          'title' => $title,
          'post_type' => 'page'
        );
        $pages = get_posts($post_arr);
        if (!$pages) {
          $post_arr = array(
            'post_title' => $title,
            'post_type' => 'page',
            'post_status' => 'publish',
            'post_content' => sprintf('[player_list id="%d" title="" number="-1" columns="pts,g,ppg" orderby="number" order="" show_all_players_link="0" align="none"]<hr /><h3>Partite:</h3>[event_calendar %d]', $list_ID, $calendar_ID)
          );
          wp_insert_post($post_arr);
        }
      }
    }
  ?>
</div>
<?php
}
  // Menu entry
  function my_admin_menu_importa_partite()
  {
    add_menu_page('Importa partite', 'Importa partite', 'update_core', 'importa-partite/importa-partite', 'importa_partite');
  }
  add_action('admin_menu', 'my_admin_menu_importa_partite');

  // Add teams to users
  function register_team_taxonomy()
  {
    register_taxonomy('squadra', 'user', array(
      'public' => true,
      'single_value' => false,
      'show_admin_column' => true,
      'labels' => array(
        'name' => 'Squadra',
        'singular_name' => 'Squadre',
        'menu_name' => 'Squadre',
        'search_items' => 'Cerca squadre',
        'popular_items' => 'Squadre più cercate',
        'all_items' => 'Tutte le squadre',
        'edit_item' => 'Modifica squadra',
        'update_item' => 'Aggiorna squadra',
        'add_new_item' => 'Aggiorna nuova squadra',
        'new_item_name' => 'Nuova squadra',
        'separate_items_with_commas' => 'Separa le squadre con una virgola',
        'add_or_remove_items' => 'Aggiungi o cancella squadra',
        'choose_from_most_used' => 'Scegli tra le squadre più popolari',
      ),
      'rewrite' => array(
        'with_front' => true,
        'slug' => 'author/squadra',
      ),
      'capabilities' => array(
        'manage_terms' => 'edit_users',
        'edit_terms' => 'edit_users',
        'delete_terms' => 'edit_users',
        'assign_terms' => 'read',
      ),
    ));
  }
  add_action( 'init', 'register_team_taxonomy', 0 );

  // show only the leagues the user can view
  function manage_user_in_new_post_type($terms, $taxonomy, $query_vars, $term_query)
  {
    if (!is_admin())
      return $terms;
    if (count($taxonomy) == 1 && $taxonomy[0] == 'squadra')
      return $terms;
    if (is_array($taxonomy) && count($taxonomy) > 0) {
      $user_id = get_current_user_id();
      $user_groups = wp_get_object_terms($user_id, 'squadra');
      if (empty($user_groups))
        return $terms;
      $user_leagues = array();
      foreach ($user_groups as $user_group) {
        $user_leagues[] = $user_group->name;
      }
      if (in_array('Tutto' , $user_leagues)) {
        return $terms;
      }
      $terms = array_filter($terms,
        function ($league) use ($user_leagues) {
          if (is_object($league)) {
            if ($league->taxonomy != 'sp_league')
              return true;
            return in_array($league->name, $user_leagues);
          } else {
            $term = get_term($league);
            if (!$term || $term->taxonomy != 'sp_league')
              return true;
            return in_array($term->name , $user_leagues);
          }
        }
      );
    }
    return $terms;
  }
  add_filter('get_terms', 'manage_user_in_new_post_type', 10, 4);

  function set_homepage_post_types( $query ) {
    if ( $query->is_home() && $query->is_main_query() ) {
      $query->set( 'post_type', array('post', 'sp_event', 'minibasket') );
    }
  }
  add_action( 'pre_get_posts', 'set_homepage_post_types' );

  // filter the league depending on user's permissions
  function show_only_current_league( $query ) {
    if( is_admin() && !empty( $_GET['post_type'] )) {
      if ( ($_GET['post_type'] == 'sp_player' && $query->query['post_type'] == 'sp_player' ) ||
          ($_GET['post_type'] == 'sp_staff' && $query->query['post_type'] == 'sp_staff' ) ||
          ($_GET['post_type'] == 'sp_event' && $query->query['post_type'] == 'sp_event' ) ||
          ($_GET['post_type'] == 'sp_list' && $query->query['post_type'] == 'sp_list' )	) {
            $user_groups = wp_get_object_terms(get_current_user_id(), 'squadra');
            $user_leagues = array();
            foreach ((array)$user_groups as $user_group) {
              $user_leagues[] = $user_group->name;
            }
            if (in_array('Tutto', $user_leagues)) {
              return;
            }
            $query->set('tax_query', array(array(
              'taxonomy' => 'sp_league',
              'field' => 'slug',
              'terms' => $user_leagues,
              'operator' => 'IN'
            )));
      }
    }
  }
  add_action( 'pre_get_posts', 'show_only_current_league' );
  
  // Remove minibasket menu entry if the user can't view it
  function remove_minibasket_page() {
    $user_groups = wp_get_object_terms(get_current_user_id(), 'squadra');
    foreach((array)$user_groups as $user_group) {;
      if ($user_group->name == 'minibasket')
        return;
    }
    remove_menu_page('edit.php?post_type=minibasket');
  }
  add_action('admin_menu', 'remove_minibasket_page');
  
  // Add results to the game
  function add_score( $title, $post_id ) {
    $post = get_post($post_id);
    if ($post->post_type == 'sp_event') {
      $event = new SP_Event($post_id);
      if ($event->status() == 'results') {
        $teams = get_post_meta( $post_id, 'sp_team', false );
        $results = get_post_meta($post_id, 'sp_results', false);
          $title = get_post($teams[0])->post_title . ' ' . $results[0][$teams[0]]['points'] . ' - ' . get_post($teams[1])->post_title . ' ' . $results[0][$teams[1]]['points'];
      }
    }
    return $title;
  }
  add_filter( 'title_edit_pre', 'add_score', 10, 2 );
  
  function staff_list_shortcodes($atts) {
    $league = $atts['league'];
    $season = $atts['season'];
    $team = $atts['team'];
    $staff_list = array();
    $roles = array('Allenatore', 'Vice allenatore', 'Scorer');
    foreach($roles as $role) {
      $args = array(
        'post_type' => 'sp_staff',
        'tax_query' => array(
          'relation' => 'AND',
          array(
            'taxonomy' => 'sp_season',
            'field' => 'term_id',
            'terms' => $season
          ),
          array(
              'taxonomy' => 'sp_league',
              'field' => 'term_id',
              'terms' => $league
          ),
          array (
            'taxonomy' => 'sp_role',
            'field' => 'name',
            'terms' => $role
          )
        ),
        'meta_query' => array(
          array(
            'key' => 'sp_current_team',
            'value' => $team
          )
        )
      );
      $results = get_posts($args);
      if ($results) {
        foreach($results as $res) {
          $staff_list[] = $res;
        }
      }
    }
    if (count($staff_list) == 0) {
      return '';
    }
    $out =  '<table class="sp-player-list sp-data-table sp-sortable-table sp-scrollable-table sp-paginated-table dataTable no-footer">';
    $out .= '<thead><tr role="row"><th>Staff</th><th>Ruolo</th></tr></thead>';
    $out .= '<tbody>';
    foreach($staff_list as $staff) {
      $coach = new SP_Staff($staff);
      $role = $coach->role();
      $image = has_post_thumbnail($staff);
      $out .= '<tr class="even" role="row">';
      $out .= '<td class="data-name ' . ($image ? 'has-photo' : '') . '">';
      if ($image) {
        $out .= '<span class="player-photo">' . get_the_post_thumbnail($staff) . '</span>';
      }
      $out .=  '<a href="' . get_permalink($staff) . '">' . $staff->post_title . '</a></td>';
      if ($role) {
        $out .= '<td>' . $role->name . '</td>';
      }
      $out .= '</tr>';
    }
    $out .= '</tbody></table>';
    return $out;
  }
  add_shortcode('staff_list', 'staff_list_shortcodes');
  ?>
