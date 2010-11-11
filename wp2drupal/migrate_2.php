<?php
// $Id: migrate_2.php,v 1.1.2.15 2009/04/17 01:53:25 damienmckenna Exp $

/**
 * @file
 * Migrates posts and comments from Wordpress to Drupal. Configuration variables
 * must be stored in the $_SESSION['wp2drupal'] array.
 *
 * Hackers guide: Wordpress SQL statements should be prepared using
 * string _wp2d_prepare_wp_query(string query) function - every {table}
 * (with curly braces) will be converted to [prefix]table.
 */

$config;        // holds user-submitted configuration information
$wp;            // associative array that mirrors the Wordpress database
$wp_connection; // connection to WP database
$output;        // string that holds migration details (what succeeded, what fails etc.)
$results;       // array holding results of migration, i.e. number of errors, warnings, infos etc.
$vocabulary_id; // vocabulary id to which belong newly created terms
$wp_permalink_structure; // structure of Wordpress permalinks

// mapping arrays: keys are always Wordpress-related, values are Drupal-related
$map_catid_termid = array();    // conversion of WP's category ids to Drupal's term ids
$map_user_id = array();         // conversion of WP's users to Drupal user ids


/**
 * Initializes global variables.
 */
function _wp2d_init_variables() {
  global $config, $wp_connection, $output, $results, $map_catid_termid, $map_user_id, $wp, $vocabulary_id, $wp_permalink_structure;
  $config = $_SESSION['wp2drupal'];
  $wp_connection = NULL;
  $wp = array();
  $output = '';
  $results = array(
    "info" => 0,
    "ok" => 0,
    "warning" => 0,
    "error" => 0
  );
  $vocabulary_id = NULL;

  $map_user_id = array();
  $user_mappings = explode(",", $config['drupal']['user_mappings']);
  foreach ($user_mappings as $user_mapping) {
    list($wp_user, $drupal_user) = explode("=>", $user_mapping);
    $map_user_id[trim($wp_user)] = trim($drupal_user);
  }
  $wp_permalink_structure = NULL;
}



/**
 * Main function that actually processes the migration.
 *
 * @param $process_details
 *   After migration process, this variable will contain all the details. This variable is passed by reference.
 *
 * @return
 *   associative array of structure array("info" => [number of infos], "ok" => X, "warning" => Y, "error" => Z)
 */
function wp2drupal_process(&$process_details) {
  global $config, $wp, $wp_connection, $output, $results, $map_catid_termid, $map_user_id;

  set_time_limit(500);
  $map_postid_nodeid = array();
  _wp2d_init_variables();
  $output .= '<table id="Wp2DrupalResults">';

  // *************** First check if everything is ready **************** //
  // this check is also made on wp2drupal settings page but we must be absolutely sure
  if (!_check_prerequisites($message)) {
    $process_details = '<p class="error">'. t('Prerequisites not fulfilled.') . $message .'</p>';
    return $results;
  }
  if (empty($config)) {
    _wp2d_add_row('error', 'Configuration not set properly - $_SESSION["wp2drupal"]["values"] array is empty. Please check that your browser has cookies enabled.');
    $output .= '</table>';
    $process_details = $output;
    return $results;
  }

  foreach ($map_user_id as $wp_user => $drupal_user) {
    _wp2d_add_row('info', 'User mapping: <strong>'. $wp_user .'=>'. $drupal_user .'</strong> (posts and comments from Wordpress user '. $wp_user .' will be assigned to Drupal user '. $drupal_user .').');
  }

  //*********************** Create user function ***************************//

  $user_function_file = drupal_get_path('module', 'wp2drupal') .'/user-function.php';
  file_put_contents($user_function_file, $config['string_handling']['user_function']);


  // ********************** Copy Wordpress DB into array ************************ //
  // This step is needed because Wordpress' connection can interfere with Drupal's connection
  // We must therefore operate on internal array.


  $wp_connection = mysql_connect($config['db_host'], $config['db_user'], $config['db_pass']);
  if (!$wp_connection) {
    _wp2d_add_row('error', 'Could not connect to Wordpress. Please check connection settings and try again.');
    $output .= '</table>';
    $process_details = $output;
    return $results;
  }

  if (!mysql_select_db($config['db_name'])) {
    _wp2d_add_row('error', 'Could not find database called \''. $config['db_name'] .'\'.');
    $output .= '</table>';
    $process_details = $output;
    return $results;
  }


  if (!empty($config['db_encoding'])) {
    mysql_query('SET CHARACTER SET '. $config['db_encoding'], $wp_connection);
  }


  // categories & tags
  $query = _wp2d_prepare_wp_query("SELECT * FROM {term_taxonomy} INNER JOIN {terms} ON {terms}.term_id = {term_taxonomy}.term_id WHERE {term_taxonomy}.taxonomy IN ('category', 'post_tag');");
  $result = mysql_query($query, $wp_connection);
  while ($category = mysql_fetch_assoc($result)) {
    $wp['categories'][] = $category;
  }
  mysql_free_result($result);

  // post2cat
  $query = _wp2d_prepare_wp_query("SELECT * FROM {term_taxonomy} INNER JOIN {terms} ON {terms}.term_id = {term_taxonomy}.term_id INNER JOIN {term_relationships} ON {term_relationships}.term_taxonomy_id = {term_taxonomy}.term_taxonomy_id WHERE {term_taxonomy}.taxonomy = 'category';");
  $result = mysql_query($query, $wp_connection);
  while ($post2cat = mysql_fetch_assoc($result)) {
    $wp['post2cat'][] = $post2cat;
  }
  mysql_free_result($result);

  // post2term
  $query = _wp2d_prepare_wp_query("SELECT * FROM {term_taxonomy} INNER JOIN {terms} ON {terms}.term_id = {term_taxonomy}.term_id INNER JOIN {term_relationships} ON {term_relationships}.term_taxonomy_id = {term_taxonomy}.term_taxonomy_id WHERE {term_taxonomy}.taxonomy = 'post_tag';");
  $result = mysql_query($query, $wp_connection);
  while ($post2term = mysql_fetch_assoc($result)) {
    $wp['post2term'][] = $post2term;
  }
  mysql_free_result($result);

  // posts
  $query = _wp2d_prepare_wp_query("SELECT * FROM {posts} WHERE post_type IN ('page', 'post') ORDER BY post_date;");
  $result = mysql_query($query, $wp_connection);
  while ($post = mysql_fetch_assoc($result)) {
    // for now don't import the WP2.6+ revisions
    if (!array_key_exists('post_parent', $post) || $post['post_parent'] == 0) {
      $wp['posts'][] = $post;
    }
  }
  mysql_free_result($result);

  // comments (we don't import comments marked as spam)
  $query = _wp2d_prepare_wp_query("SELECT * FROM {comments} WHERE comment_approved <> 'spam';");
  $result = mysql_query($query, $wp_connection);
  while ($comment = mysql_fetch_assoc($result)) {
    $wp['comments'][] = $comment;
  }
  mysql_free_result($result);

  // users
  $query = _wp2d_prepare_wp_query("SELECT * FROM {users};");
  $result = mysql_query($query, $wp_connection);
  while ($wpuser = mysql_fetch_assoc($result)) {
    $wp['users'][] = $wpuser;
  }
  mysql_free_result($result);

  // options
  $query = _wp2d_prepare_wp_query("SELECT * FROM {options};");
  $result = mysql_query($query, $wp_connection);
  while ($option = mysql_fetch_assoc($result)) {
    $wp['options'][] = $option;
  }
  mysql_free_result($result);

  mysql_close($wp_connection);

  db_set_active();
  global $db_url, $active_db;
  if (is_array($db_url)) {
    $connect_url = array_key_exists($name, $db_url) ? $db_url[$name] : $db_url['default'];
  }
  else {
    $connect_url = $db_url;
  }


  $active_db = db_connect($connect_url);
  //db_query("SET CHARACTER SET utf8"); // !!! don't do it, don't ask me why but it simply screw things up

  // ******************* detect WP site encoding ********************* //
  if ($config['site_encoding'] == "") {
    $config['site_encoding'] = _wp2d_get_wp_encoding();
  }
  if ($config['site_encoding'] != "UTF-8") {
    _wp2d_add_row('info', 'Wordpress site encoding differs from Drupal site encoding, all strings will be "iconved".');
  }


  // ************ Truncate database tables if specified ************** //
  if (($config['cleanup']['empty_database'] == 1) &&
      ($config['cleanup']['empty_database_areyousure'] == 1)) {
    _wp2d_truncate_database();
  }
  else {
    _wp2d_add_row('info', 'Database will not be emptied before migration.');
  }


  // *************** prepare URL redirection table and script ****************** //
  if (!module_exists('path_redirect') && $config['url_rewrite']['enable'] == 1) {

    $query = "CREATE TABLE IF NOT EXISTS {$config[url_rewrite][table_name]} (".
    $query .= "wp_path varchar(255) NOT NULL PRIMARY KEY, ";
    $query .= "drupal_path varchar(255) NOT NULL";
    $query .= ") DEFAULT CHARACTER SET utf8;";

    db_query($query);
    db_query("TRUNCATE TABLE {$config[url_rewrite][table_name]}");

    // create not-found.php file
    file_put_contents(drupal_get_path('module', 'wp2drupal') .'/not-found.php', $config['url_rewrite']['not_found_page']);

    // create redirection script
    $redirection_script = file_get_contents(drupal_get_path('module', 'wp2drupal') .'/moved-permanently.php.template');
    $connection_settings = parse_url($connect_url);
    $wp_site_url = "";
    foreach ($wp['options'] as $option) {
      if ($option['option_name'] == "siteurl") {
        $wp_site_url = $option['option_value'];
        break;
      }
    }
    $substitutions = array(
      '%db_host%' => $connection_settings['host'],
      '%db_user%' => $connection_settings['user'],
      '%db_pass%' => $connection_settings['pass'],
      '%db_name%' => trim($connection_settings['path'], "/"),
      '%db_table%' => $GLOBALS['db_prefix'] . $config['url_rewrite']['table_name'],
      '%wp_url%' => $wp_site_url,
      '%drupal_url%' => $GLOBALS['base_url'],
      '%new_blog_path%' => $config['url_rewrite']['new_blog_url'],
      '\'%requested_resource%\'' => $config['url_rewrite']['wp_server_variable'],
      '\'%drupal_cool_urls%\'' => (variable_get('clean_url', 0) == 0) ? 'FALSE' : 'TRUE'
    );
    $redirection_script = str_replace(array_keys($substitutions), array_values($substitutions), $redirection_script);
    file_put_contents(drupal_get_path('module', 'wp2drupal') .'/moved-permanently.php', $redirection_script);
  }


  // ******************** Create vocabulary and taxonomy terms **********//
  // create/assign the category
  $cats_id = _wp2d_create_vocabulary($config['drupal']['categories']['category_name'], $config['drupal']['categories']['category_desc'], $config['drupal']['blog_nodetype']);
  // by default use the same vocabulary for tags as categories
  $tags_id = $cats_id;
  // only WP 2.3+ have tags
  if (floatval($config['version']) >= 2.3) {
    // only continue if a vocabulary name was given and it's different than
    // the one used for categories
    if (strlen($config['drupal']['tags']['tags_name']) > 0 && $config['drupal']['tags']['tags_name'] != $config['drupal']['categories']['category_name']) {
      $tags_id = _wp2d_create_vocabulary($config['drupal']['tags']['tags_name'], $config['drupal']['tags']['tags_desc'], $config['drupal']['blog_nodetype'], TRUE);
    }
  }
  // import the categories
  $map_catid_termid = _wp2d_import_wp_categories($wp, $cats_id, $tags_id);



  // ******************** Process each Wordpress post ****************** //

  foreach ($wp['posts'] as $post) {
    // ************ create new Drupal user if needed *********************//
    if (!array_key_exists($post['post_author'], $map_user_id)) {

      // first, get WP user
      //$wp_user_query = _wp2d_prepare_wp_query("SELECT * FROM {users} WHERE ID=" . $post['post_author']);
      //$wp_user = mysql_fetch_assoc(mysql_query($wp_user_query, $wp_connection));

      $i = 0;
      $found = FALSE;
      for ($i = 0; $i < count($wp['users']); $i++) {
        if (array_search($post['post_author'], $wp['users'][$i]) == 'ID') {
          $found = TRUE;
          break;
        }
      }
      if (!$found) {
        _wp2d_add_row('error', 'User with ID '. $post['post_author'] .' was not found in WP database. This user will not be migrated. This error should never occur (if it does, there must be some inconsistency in WP database).');
      }
      else {

        $wp_user = $wp['users'][$i];

        // we must check if some user with the same name does not exist - if yes,
        // we will create a new user with randomized name
        $query = "SELECT name FROM {users} WHERE LOWER(name) = '%s'";
        $testname = $wp_user['user_login'];
        $suffix = 1;
        while ($suffix < 20) { // better than while(TRUE)
          $result = db_query($query, $testname);
          if (db_fetch_object($result) === FALSE) {
            break;
          }
          else {
            $testname = $wp_user['user_login'] . $suffix;
            $suffix++;
          }
        }
        $corrected_name = $testname;
        if ($corrected_name != $wp_user['user_login']) {
          _wp2d_add_row('warning', 'User '. $wp_user['user_login'] .' already exists. User with corrected name <strong>'. $corrected_name .'</strong> will be created instead.');
        }

        $new_user = array(
          'name' => $corrected_name,
          // 'pass' => password must be specified manually after migration ends
          'mail' => $wp_user['user_email'],
          'mode' => 0,
          'sort' => 0,
          'threshold' => 0,
          'created' => time(),
          'status' => 1,
          'init' => $wp_user['user_email']
        );
        $created_user = user_save('', $new_user);
        _wp2d_add_row('ok', 'User '. $user->name .' (UID '. $user->uid .') was added. You must set new password for this user manually (go to admin/user).');
        //watchdog('user', t('New user imported from Wordpress: %user', array('%user', $user->uid)));
        $map_user_id[$post['post_author']] = $created_user->uid;
      }
    } // end of user creation


    // ************************ create and save node ********************* //
    // these values are expected from node_save() function:
/* $revisions_table_values = array('nid' => $node->nid, 'vid' => $node->vid,
                     'title' => $node->title, 'body' => $node->body,
                     'teaser' => $node->teaser, 'log' => $node->log, 'timestamp' => $node->changed,
                     'uid' => $user->uid, 'format' => $node->format);
  $revisions_table_types = array('nid' => '%d', 'vid' => '%d',
                     'title' => "'%s'", 'body' => "'%s'",
                     'teaser' => "'%s'", 'log' => "'%s'", 'timestamp' => '%d',
                     'uid' => '%d', 'format' => '%d');
  $node_table_values = array('nid' => $node->nid, 'vid' => $node->vid,
                    'title' => $node->title, 'type' => $node->type, 'uid' => $node->uid,
                    'status' => $node->status, 'created' => $node->created,
                    'changed' => $node->changed, 'comment' => $node->comment,
                    'promote' => $node->promote, 'moderate' => $node->moderate,
                    'sticky' => $node->sticky);
  $node_table_types = array('nid' => '%d', 'vid' => '%d',
                    'title' => "'%s'", 'type' => "'%s'", 'uid' => '%d',
                    'status' => '%d', 'created' => '%d',
                    'changed' => '%d', 'comment' => '%d',
                    'promote' => '%d', 'moderate' => '%d',
                    'sticky' => '%d');*/

    // ******************** first, fill in properties for node table ******************//
    $node = new stdClass();
    $node->title = _wp2d_process_text($post['post_title']);

    $node->body = _wp2d_process_text(str_replace('<!--more-->', '<!--break-->', $post['post_content']));
    $post['post_excerpt'] = _wp2d_process_text($post['post_excerpt']);
    $node->teaser = (
      empty($post['post_excerpt']) ? node_teaser($node->body) : $post['post_excerpt']
    );
    $node->log = '';

    // **** Fix dates ****
    // Sometimes (for example after import from yet another blogging software),
    // only post_date is filled so we have to update another date fields

    if (strpos($post['post_date_gmt'], '0000') === 0) {
      $gmt_offset = (int)_wp2d_get_wp_option('gmt_offset');
      // minus is the right operator because if timezone is GMT+1, GMT time must be one hour less
      $gmt_time = _wp2d_wpdate_2_drupaltimestamp($post['post_date']) - ($gmt_offset * 60 * 60);
      $post['post_date_gmt'] = date('Y-m-d H:i:s', $gmt_time);
    }

    if (strpos($post['post_modified'], '0000') === 0) {
      $post['post_modified'] = $post['post_date'];
    }

    if (strpos($post['post_modified_gmt'], '0000') === 0) {
      $post['post_modified_gmt'] = $post['post_date_gmt'];
    }

    $node->created = _wp2d_wpdate_2_drupaltimestamp($post['post_date_gmt']);
    $node->changed = _wp2d_wpdate_2_drupaltimestamp($post['post_modified_gmt']);

    $node->format = $config['drupal']['filter'];

    // work out what content type this is, a page or a blog
    // post_status=static was the old way of doing it, post_type is newer
    $node->type = (
      ($post['post_status'] == 'static' || $post['post_type'] == 'page')
      ? $config['drupal']['page_nodetype']
      : $config['drupal']['blog_nodetype']
    );
    $node->uid = $map_user_id[$post['post_author']];

    // Node status should be 1 if published, 0 if not published.
    // WP's post_status is MySQL type enum('publish', 'draft', 'private', 'static', 'object') - I don't know
    // what 'object' means but this type is usually not used at all
    $node->status = (
      (($post['post_status'] == 'static') || ($post['post_status'] == 'publish')) ? 1 : 0
    );

    // Comments status is 0 if comments should not be displayed (even if there are some),
    // 1 for read only comments (view comments if there are some but don't allow new ones)
    // and 2 for enabled comments.
    // Wordpress options are enum('open', 'closed', 'registered_only'), while 'registered_only' is
    // handled equally as 'open'.
    $node->comment = (
      ($post['comment_status'] == 'closed') ? 1 : 2
    );

    $node->promote = 1;
    $node->moderate = 0;
    $node->sticky = 0;
    $node->is_new = 1;

    // we need to do a little trick because revisions uid is baset on $user->uid
    // and not on $node->uid
    global $user;
    $previous_uid = $user->uid;
    $user->uid = $node->uid;

    node_save($node);
    $user->uid = $previous_uid;

    $map_postid_nodeid[$post['ID']] = $node->nid;

    _wp2d_add_row('ok', 'Post <em>'. $node->title .'</em> was sucessfully migrated.');

    // if path_redirect.module is installed,
    if (module_exists('path_redirect')) {
      // add a redirect line
      _wp2d_add_redirection_rule(_wp_path($post), 'node/'. $node->nid);
    }
    // or if the the rewrite option was selected..
    elseif (($config['url_rewrite']['enable'] == 1) && ($node->status == 1)) {
      // add a redirect line
      _wp2d_add_redirection_rule(_wp_path($post), _wp2d_drupal_path($node->nid));
    }
  }

  // **************************** import comments ********************************** //
  // here we can't user comment_save() function because it for example sets comment time strictly to time()
  // so the following code is just based on comment_save() function
  foreach ($wp['comments'] as $comment) {

    if (($config['drupal']['import_trackbacks'] == 0) && ($comment['comment_type'] == 'pingback')) {
      continue;
    }

    // to correctly redirect comments, they need to have same numbers both in Drupal and in Wordpress
    if (is_NULL(_comment_load($comment['comment_ID']))) {
      $new_comment['cid'] = $comment['comment_ID'];
    }
    else {
      $new_comment['cid'] = db_last_insert_id('{comments}', 'cid');
    }

    // TODO: Standard Wordpress installation does not support threaded comments
    // so 'comment_parent' field always contains 0. I don't know how do some WP plugins
    // work so it's not sure that this method is 100% right in all scenarios (but it surely works
    // in WP without any threaded comments plugins).
    $new_comment['pid'] = $comment['comment_parent'];
    $new_comment['nid'] = $map_postid_nodeid[$comment['comment_post_ID']];
    if ($comment['user_id'] == 0) {
      $new_comment['uid'] = 0;
    }
    else {
      $new_comment['uid'] = $map_user_id[$comment['user_id']];
    }

    // WP doesn't support subject field so we do the same thing as in comment.module's
    // _comment_form_submit() function
    $new_comment['subject'] = truncate_utf8(decode_entities(strip_tags(check_markup(_wp2d_process_text($comment['comment_content']), $config['drupal']['filter']))), 29, TRUE);
    $new_comment['comment'] = _wp2d_process_text($comment['comment_content']);
    $new_comment['hostname'] = $comment['comment_author_IP'];
    $new_comment['timestamp'] = _wp2d_wpdate_2_drupaltimestamp($comment['comment_date_gmt']);
    $new_comment['score'] = 0; // TODO: I don't have good understanding for what can this be useful
    $new_comment['status'] = ($comment['comment_approved'] == 1) ? 0 : 1; // 0 means published in Drupal as oposed to WP
    $new_comment['format'] = $config['drupal']['filter'];

    // now the hard thing: thread field; the following code is almost-exact copy from comment.module
    // copied code ends with "// *** ENDCOPY *** //"

    // Here we are building the thread field.  See the comment
    // in comment_render().
    if ($new_comment['pid'] == 0) {
      // This is a comment with no parent comment (depth 0): we start
      // by retrieving the maximum thread level.
      $max = db_result(db_query('SELECT MAX(thread) FROM {comments} WHERE nid = %d', $new_comment['nid']));

      // Strip the "/" from the end of the thread.
      $max = rtrim($max, '/');

      // Finally, build the thread field for this new comment.
      $thread = int2vancode(vancode2int($max) + 1) .'/';
    }
    else {
      // This is comment with a parent comment: we increase
      // the part of the thread value at the proper depth.

      // Get the parent comment:
      $parent = _comment_load($new_comment['pid']);

      // Strip the "/" from the end of the parent thread.
      $parent->thread = (string) rtrim((string) $parent->thread, '/');

      // Get the max value in _this_ thread.
      $max = db_result(db_query("SELECT MAX(thread) FROM {comments} WHERE thread LIKE '%s.%%' AND nid = %d", $parent->thread, $new_comment['nid']));

      if ($max == '') {
        // First child of this parent.
        $thread = $parent->thread .'.'. int2vancode(0) .'/';
      }
      else {
        // Strip the "/" at the end of the thread.
        $max = rtrim($max, '/');

        // We need to get the value at the correct depth.
        $parts = explode('.', $max);
        $parent_depth = count(explode('.', $parent->thread));
        $last = $parts[$parent_depth];

        // Finally, build the thread field for this new comment.
        $thread = $parent->thread .'.'. int2vancode(vancode2int($last) + 1) .'/';
      }
    }

    // *** ENDCOPY *** //


    $new_comment['users'] = serialize(array(0 => $score)); // copied from comment.module
    $new_comment['name'] = $comment['comment_author'];
    $new_comment['mail'] = $comment['comment_author_email'];
    $new_comment['homepage'] = $comment['comment_author_url'];

    db_query("INSERT INTO {comments} (nid, pid, uid, subject, comment, format, hostname, timestamp, status, thread, name, mail, homepage) VALUES (%d, %d, %d, '%s', '%s', %d, '%s', %d, %d, %d, '%s', '%s', '%s')", $new_comment['nid'], $new_comment['pid'], $new_comment['uid'], $new_comment['subject'], $new_comment['comment'], $new_comment['format'], $new_comment['hostname'], $new_comment['timestamp'], $new_comment['status'], $thread, $new_comment['name'], $new_comment['mail'], $new_comment['homepage']);

    _comment_update_node_statistics($new_comment['nid']);

    // Tell the other modules a new comment has been submitted.
    comment_invoke_comment($new_comment, 'insert');

  }
  _wp2d_add_row('ok', 'Comments migrated successfully');


  // ************************ connect nodes with taxonomy terms ********************* //
  $query = "INSERT INTO {term_node} (nid, vid, tid) VALUES (%d, %d, %d);";
  foreach ($wp['post2cat'] as $binding) {
    // Verify that this post or page was transferred.
    // Some WP content types are skipped, e.g. attachments, revisions
    if (array_key_exists($binding['object_id'], $map_postid_nodeid)) {
      db_query($query, $map_postid_nodeid[$binding['object_id']], $map_postid_nodeid[$binding['object_id']], $map_catid_termid[$binding['term_taxonomy_id']]);
    }
  }
  foreach ($wp['post2term'] as $binding) {
    // Verify that this post or page was transferred.
    // Some WP content types are skipped, e.g. attachments, revisions
    if (array_key_exists($binding['object_id'], $map_postid_nodeid)) {
      db_query($query, $map_postid_nodeid[$binding['object_id']], $map_postid_nodeid[$binding['object_id']], $map_catid_termid[$binding['term_taxonomy_id']]);
    }
  }
  _wp2d_add_row('ok', 'Nodes were sucessfully assigned to taxonomy terms according to Wordpress posts-to-category mappings.');



  // ******************** Finalization stuff ****************** //

  cache_clear_all();
  $output .= '</table>';

  $process_details = $output;
  return $results;
}


/**
 * Returns Wordpress option value.
 *
 * @param string $option_name
 *   See data of wp_options table for possible values
 * @return string
 *   Option value
 */
function _wp2d_get_wp_option($option_name) {
  global $wp;
  static $wp_options;

  if (!isset($wp_options[$option_name])) {
    foreach ($wp['options'] as $option) {
      if ($option['option_name'] == $option_name) {
        $wp_options[$option_name] = $option['option_value'];
        break;
      }
    }
  }
  // verify that the field exists
  if (array_key_exists($option_name, $wp_options)) {
    return $wp_options[$option_name];
  }
  else {
    return FALSE;
  }
}

/**
 * Adds a rule to the redirection table.
 *
 * @param string $wp_path
 *   Wordpress path, e.g. "?p=123", "index.php/2006/06/08/sample-post/" etc. This path should not contain
 *   domain name and WP installation directory, it should containt everything after "Blog address (URI)" and the slash
 *   (this setting can be found on /wp-admin/options-general.php WP page).
 * @param string $drupal_path
 *   Drupal path. Same as in WP path - this string should not contain domain name or Drupal installation directory
 *   so "node/1" or "blog/example-post" are valid examples (don't even include "?q=" part).
 */
function _wp2d_add_redirection_rule($wp_path, $drupal_path) {
  global $config;
  // if path_redirect is installed, use it
  if (module_exists('path_redirect')) {
    $path = array(
      'path' => preg_replace('/^\/|\/\?|\/$/', '', $wp_path),
      'redirect' => $drupal_path,
      'query' => '',
      'fragment' => '',
      'language' => '',
      'type' => '301',
    );
    drupal_write_record('path_redirect', $path, array());
  }
  // otherwise install into the internal thingy
  else {
    $query = "INSERT INTO {$config[url_rewrite][table_name]} (wp_path, drupal_path) VALUES ('%s', '%s')";
    db_query($query, $wp_path, $drupal_path);
  }
}

/**
 * For a given node, it returns a path, e.g. "node/1" or "blog/example-post". If an URL alias exists, it wil be returned
 * instead of system path (which is not so "beautiful").
 *
 * @param unknown_type $node
 *   Node for which the path should be returned.
 */
function _wp2d_drupal_path($node_id) {
  return drupal_get_path_alias("node/" . $node_id);
}

/**
 * Returns Wordpress permalink structure (can be empty string) without leading slash
 *
 * @return string
 *   Wordpress permalink structure (empty string is a valid value) always without leading slash
 */
function _wp2d_get_wp_permalink_structure() {
  global $wp, $wp_permalink_structure;

  if (is_NULL($wp_permalink_structure)) {
    foreach ($wp['options'] as $option) {
      if ($option['option_name'] == 'permalink_structure') {
        $wp_permalink_structure = trim($option['option_value'], " /");
        break;
      }
    }
  }
  return $wp_permalink_structure;
}

/**
 * Given a Wordpress post, returns a path like this http://domain.com/wordpress-installation-folder/[[ ! this will be returned ! ]]
 *
 * @param array $post
 *   Associative array with Wordpress post.
 */
function _wp_path(&$post) {
  global $wp;

  // the new way of handling this
  // remove the siteurl from the post['guid']
  $siteurl = _wp2d_get_wp_option('siteurl');
  if (isset($post['guid']) && $siteurl) {
    $path = str_replace($siteurl, '', $post['guid']);
    $path = ltrim($path, "/");
  }

  // the old way of doing it
  // no permalink structure, i.e. all URLs show as ?p=123
  elseif (_wp2d_get_wp_permalink_structure() == "") {
    $path = "?p=" . $post['ID'];
  }
  
  // handle the permalinks
  else {
    // get parts of the date
    $temp_wp_date = str_replace(" ", ":", $post['post_date_gmt']);
    list($Y, $M, $D, $h, $m, $s) = split('[-:]', $temp_wp_date);

    // get category name
    // this best imitates Wordpress behavior, see http://codex.wordpress.org/Using_Permalinks, comments to %category%
    /* this would be SQL approach but we are limited to arrays

    $query =
    "SELECT {categories}.category_nicename " .
    "FROM {categories} LEFT JOIN {post2cat} on ({categories}.cat_ID = {post2cat}.category_id) " .
    "WHERE {post2cat}.post_id = %postid% " .
    "ORDER BY {categories}.cat_ID " .
    "LIMIT 1;";

    $query = str_replace("%postid%", $post['ID'], $query);
    _wp2d_prepare_wp_query($query);*/

    // 1. get the right category id
    $assigned_categories = array();
    foreach ($wp['post2cat'] as $post2cat) {
      if ($post2cat['object_id'] == $post['ID']) {
        $assigned_categories[] = $post2cat['term_taxonomy_id'];
      }
    }
    sort($assigned_categories);
    $assigned_terms = array();
    foreach ($wp['post2term'] as $post2term) {
      if ($post2term['object_id'] == $post['ID']) {
        $assigned_terms[] = $post2term['term_taxonomy_id'];
      }
    }
    sort($assigned_categories);
    $cateogory_id = $assigned_categories[0];

    // 2. get the category "nicename"
    $category_nicename = "";
    foreach ($wp['categories'] as $category) {
      if ($category['cat_ID'] == $cateogory_id) {
        $category_nicename = $category['category_nicename'];
        break;
      }
    }

    // get user name
    $user_nicename = "";
    foreach ($wp['users'] as $wp_user) {
      if ($wp_user['ID'] == $post['post_author']) {
        $user_nicename = $wp_user['user_nicename'];
        break;
      }
    }

    $wp_tags = array(
      '%year%' => $Y,
      '%monthnum%' => $M,
      '%day%' => $D,
      '%hour%' => $h,
      '%minute%' => $m,
      '%second%' => $s,
      '%postname%' => $post['post_name'],
      '%post_id%' => $post['ID'],
      '%category%' => $category_nicename,
      '%author%' => $user_nicename
    );

    $path = str_replace(array_keys($wp_tags), array_values($wp_tags), _wp2d_get_wp_permalink_structure());
    $path = ltrim($path, "/");

    // get rid of "index.php" part
    if (strpos($path, "index.php") === 0) {
      $path = drupal_substr($path, drupal_strlen("index.php"));
      $path = ltrim($path, "/");
    }
  }
  return $path;
}


/**
 * If Wordpress and Drupal site encodings differ, this function returns iconved string.
 * If the encodings are the same, untouched string is returned.
 *
 *
 * @param string $string
 *   String to be iconved if needed.
 * @return string
 *   Iconved string if needed, otherwise untouched string.
 */
function _wp2d_iconv_if_needed($string) {
  global $config;
  $iconv_needed = ($config['site_encoding'] != 'UTF-8');
  if ($iconv_needed) {
    return iconv($config['site_encoding'], 'UTF-8', $string);
  }
  else {
    return $string;
  }
}


/**
 * Creates Drupal vocabulary in which the taxonomy terms created from Wordpress categories
 * will be stored. Vocabulary will be created only if the name is unique. In any case,
 * global variable $vocabulary_id will be filled with the right value.
 */

function _wp2d_create_vocabulary($vocabulary_name, $vocabulary_desc, $nodetype, $freetagging = FALSE) {
  // check if the name of the vocabulary is unique - if not, we will merge new terms into
  // existing vocabulary
  $query = "SELECT vid FROM {vocabulary} WHERE name = '%s';";
  $vocab = db_fetch_object(db_query($query, $vocabulary_name));

  // if the vocabulary doesn't exist already, add it
  if ($vocab === FALSE) {
    $edit = array();
    $edit['name'] = $vocabulary_name;
    $edit['description'] = $vocabulary_desc;
//    $edit['hierarchy'] = 1; // 0=no hierarchy, 1=single hierarchy, 2=multiple hierarchies
//    $edit['relations'] = 0;
    $edit['tags'] = $freetagging ? 1 : 0;
    $edit['multiple'] = 1;
    $edit['required'] = 0;
    $edit['weight'] = 0;
    if (is_array($nodetype)) {
      foreach ($nodetype as $x => $type) {
        $edit['nodes'][$type] = 1;
      }
    }
    else {
      $edit['nodes'][$nodetype] = 1;
    }

    taxonomy_save_vocabulary($edit);

    $vocabulary_id = $edit['vid'];

    _wp2d_add_row('ok', 'New vocabulary <em>'. $edit['name'] .'</em> was sucessfully created.');
  }
  // it already existed, get the ID
  else {
    $vocabulary_id = $vocab->vid;
    _wp2d_add_row('warning', 'The <em>'. $vocabulary_name .'</em> vocabulary already exists. New terms will be merged into it.');
  }
  return $vocabulary_id;
}

/**
 * Imports categories from Wordpress to Drupal's taxonomy terms
 *
 */
function _wp2d_import_wp_categories($wp, $cats_id, $tags_id) {
  // insert each WP category into Drupal if it does not already exist;
  // during the process, we must build mapping array that pairs WP cats_ids and Drupal term ids
  // (indexes are WP categories, values are Drupal term ids)

  // build an array to match categories/tags against terms
  $map_catid_termid = array();
  // automatically handle the distinction between categories and tags
  $vocabs = array('category' => $cats_id, 'post_tag' => $tags_id);
  // base query used to see if the term exists
  $query = "SELECT tid FROM {term_data} WHERE (vid = %d) AND (name = '%s');";
  // return string
  $output = '';
  // the base path for categories & tags
  static $base_paths = array();
  if (!array_key_exists('category', $base_paths)) {
    $base_paths['category'] = ltrim(_wp2d_get_wp_option('category_base'), '/');
  }
  if (!array_key_exists('post_tag', $base_paths)) {
    $base_paths['post_tag'] = ltrim(_wp2d_get_wp_option('tag_base'), '/');
  }

  // loop through each category
  foreach ($wp['categories'] as $category) {
    // load the term
    $term = db_fetch_object(db_query($query, $vocabs[$category['taxonomy']], _wp2d_process_text($category['name'])));
    // if the term doesn't exist, add it
    if ($term === FALSE) {
      $edit = array();
      $edit['vid'] = $vocabs[$category['taxonomy']];
      $edit['parent'] = array_key_exists($category['parent'], $map_catid_termid) ? $map_catid_termid[$category['parent']] : 0;
      $edit['name'] = _wp2d_process_text($category['name']);
      $edit['description'] = _wp2d_process_text($category['slug']);
      $edit['synonyms'] = '';
      $edit['weight'] = 0;
      taxonomy_save_term($edit);
      $map_catid_termid[$category['term_taxonomy_id']] = $edit['tid'];
      _wp2d_add_row('ok', $category['taxonomy'] .' '. _wp2d_process_text($category['name']) .' sucessfully imported.');
    }
    // the term already existed
    else {
      $map_catid_termid[$category['term_taxonomy_id']] = $term->tid;
      _wp2d_add_row('warning', 'Term with name '. _wp2d_process_text($category['name']) .' already exists in the appropriate vocabulary. It will be skipped.');
    }

    // if path_redirect is instaled add the old category/tag slug
    if (module_exists('path_redirect')) {
      _wp2d_add_redirection_rule($base_paths[$category['taxonomy']] .'/'. $category['slug'], 'taxonomy/term/'. $map_catid_termid[$category['term_taxonomy_id']]);
    }
  }
  _wp2d_add_row('ok', 'Wordpress categories successfully transformed into taxonomy terms.');
  return $map_catid_termid;
}

/**
 *  Returns Wordpress site encoding ("UTF-8" is the default value if there is some error)
 */
function _wp2d_get_wp_encoding() {
  global $wp;

  $encoding = "";
  foreach ($wp['options'] as $option) {
    if ($option['option_name'] == 'blog_charset') {
      $encoding = $option['option_value'];
      break;
    }
  }
  _wp2d_add_row('info', 'Wordpress encoding detected as <em>'. $encoding .'</em>');

  return $encoding;
}

/**
 * Takes query containing {tablename} and returns query containing corretly prefixed SQL
 */
function _wp2d_prepare_wp_query($sql) {
  global $config;
  $new_query = strtr($sql, array(
    '{' => $config['db_prefix'],
    '}' => ''
    )
  );
  return $new_query;
}

/**
 * Output is returned in form of big one-column table. This function adds one row.
 *
 * @param $type
 *   Type of message - can be "info", "ok", "warning", "error"
 *
 * @param $content
 *   The actual content of the message
 *
 * @return
 *   This function directly adds a HTML row into global $output variable and it returns nothing.
 */
function _wp2d_add_row($type, $content) {
  global $output, $results;
  static $odd = TRUE;
  $output .= '<tr class="'. ($odd ? 'odd' : 'even') .' '. $type .'">';
  $output .= '<td>'. $type .'</td>';
  $output .= '<td>'. $content .'</td></tr>';
  $odd = !$odd;
  $results[$type] += 1;
}

/**
 * Truncates Drupal's content-oriented tables. This function is potentially dangerous!
 */
function _wp2d_truncate_database() {
  global $config;
  _wp2d_add_row('info', 'These database tables will be truncated: '. $config['cleanup']['tables_to_truncate']);
  $tables_to_truncate = explode(",", $config['cleanup']['tables_to_truncate']);
  for ($i = 0; $i < count($tables_to_truncate); $i++) {
    $tables_to_truncate[$i] = trim($tables_to_truncate[$i]);
  }
  $tables_truncated_successfully = TRUE;
  foreach ($tables_to_truncate as $table) {
    if (!db_query("TRUNCATE TABLE {$table}")) {
      _wp2d_add_row('warning', 'Database table \''. $table .'\' was not truncated.');
      $tables_truncated_successfully = FALSE;
    }
  }

  if ($tables_truncated_successfully) {
    _wp2d_add_row('ok', 'Specified database tables truncated successfully.');
  }
  else {
    _wp2d_add_row('warning', 'Some tables were not truncated.');
  }
}

/**
 * Takes Wordpress-foramatted date string and returns timestamp as used in Drupal
 *
 * @param string $wp_date
 *   Wordpress-formatted date string
 * @return int
 *   Drupal's timestamp.
 */
function _wp2d_wpdate_2_drupaltimestamp($wp_date) {
  $temp_wp_date = str_replace(" ", ":", $wp_date);
  list($Y, $M, $D, $h, $m, $s) = split('[-:]', $temp_wp_date);
  return mktime($h, $m, $s, $M, $D, $Y);
}

/**
 * Processes standard and user-defined string operations and returns the modified string.
 *
 * @param string $string
 *   String to process
 * @return string
 *   Processed string
 */
function _wp2d_process_text($string) {
  include_once(drupal_get_path('module', 'wp2drupal') .'/user-function.php');
  $string = _wp2d_custom_process_text($string);
  return _wp2d_iconv_if_needed($string);
}
