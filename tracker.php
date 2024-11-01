<?php
/*
Plugin Name: Track site traffic 
Plugin URI: http://unifiedsoftwareservices.com
Description: Tracking Inbound and outbound traffic!
Version: 1.0
Author: Deepakchauhan
Author URI: http://unifiedsoftwareservices.com
*/

function lt_install () 
{
	global $wpdb;
	
	// Create the tracking table
	$table_name = lt_get_table_name();
	if($wpdb->get_var("show tables like '$table_name'") != $table_name) {
		$sql = "CREATE TABLE " . $table_name . " (
		ip_address varchar(255) NOT NULL,
		referer varchar(255) NOT NULL,
		id mediumint(16) NOT NULL AUTO_INCREMENT,
		longdatetime datetime NOT NULL,
		qcount mediumint(16) NOT NULL,
		qmemory float NOT NULL,
		qtime float NOT NULL,
		qpage varchar(255) NOT NULL,
		useragent varchar(255) NOT NULL,
		post_tag varchar(255) NOT NULL,
		UNIQUE KEY id (id)
		);";
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
	}
	
	// Insert default plugin options

	$lt_options = array(
		'lt_recent_requests' => '5000',
		'lt_max_records' => 5000
	);
	add_option('plugin_ntracker_settings', $lt_options);
	
	// Register the cron job to limit max records 
	wp_schedule_event(time(), 'hourly', 'lt_clear_max');
}

// Run on plugin deactivation
function lt_uninstall () 
{
	global $wpdb;
	
	//Drop the table that we created
	$table_name = lt_get_table_name();
	if($wpdb->get_var("show tables like '$table_name'") == $table_name) {
		$wpdb->query("DROP TABLE $table_name");
	}
	
	// Remove the plugin options
	delete_option('plugin_ntracker_settings');
	
	// Remove the cron job to limit max records 
	wp_clear_scheduled_hook('lt_clear_max');
}

// This is the function that stores your tracking data to mySQL
// Bound to wp_footer() function. Won't work unless the theme calls wp_footer

function getRealIpAddr()
{
    if (!empty($_SERVER['HTTP_CLIENT_IP']))   //check ip from share internet
    {
      $ip=$_SERVER['HTTP_CLIENT_IP'];
    }
    elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR']))   //to check ip is pass from proxy
    {
      $ip=$_SERVER['HTTP_X_FORWARDED_FOR'];
    }
    else
    {
      $ip=$_SERVER['REMOTE_ADDR'];
    }
    return $ip;
}

function store_timer_data()
{
	global $wpdb;
	$postid = get_the_ID();
	$pagePermitted=get_post_meta($postid, 'trackpage_checkbox',true); 
	$postTag=get_post_meta($postid, 'trackpagetag_textbox',true); 
	if($pagePermitted==1)
	{
		
	$table_name = lt_get_table_name();
	$lt_data = array(
		'ip_address' =>getRealIpAddr(),
		'referer' =>'Direct Hit',
		'longdatetime' => date('Y-m-d H:i:s', time()),
		'qcount' => get_num_queries(),
		'qtime' => timer_stop(0,3),
		'qpage' => "http://".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'],
		'qmemory' => round(memory_get_peak_usage() / 1024 / 1024, 3),
		'useragent' => $_SERVER['HTTP_USER_AGENT'],
		'post_tag' => $postTag
	);	
	$wpdb->insert($table_name, $lt_data);
	}
}

// Displays the panel at WP-Admin >> Manage >> Trackit
function lt_manage_panel() 
{
	global $wpdb;
	$table_name = lt_get_table_name();
	$to_average = 0;

	// Run any optional commands
	if ( $_REQUEST['doClearOverage'] == 'yes' ) {
		do_action('lt_clear_max');
		$message = '<div id="message" class="updated fade"><p><strong>The records overage has been cleared</strong></p></div>';
	}
	if ( $_REQUEST['doClearAll'] == 'yes' ) {
		$query = "TRUNCATE TABLE " .$table_name;
		$query_result = $wpdb->query($query);
		$message = '<div id="message" class="updated fade"><p><strong>All of the records have been cleared</strong></p></div>';
	}	
	
	// Get the options array
	$options = get_option('plugin_ntracker_settings');
	$lt_recent_requests = $options['lt_recent_requests'];
	
	// Get the record count
	$record_count = lt_get_record_count();
	
	// Get most recent requests
	$recent_results = $wpdb->get_results("SELECT * FROM $table_name ORDER BY longdatetime DESC LIMIT 0, $lt_recent_requests");

	// Get min, max, and average for qtime, qcount, and qmemory
	$statistic = $wpdb->get_row("SELECT max(qtime) as max_time,min(qtime) as min_time,avg(qtime) as avg_time,max(qcount) as max_queries,min(qcount) as min_queries,avg(qcount) as avg_queries,max(qmemory) as max_memory,min(qmemory) as min_memory,avg(qmemory) as avg_memory FROM $table_name");

	// Start the page content
	echo '<div id="divTrackerContent" class="wrap">';
	echo '<h2>Trackit</h2>';	
	echo $message;
	if($record_count == 0){		
		echo '<p>Not enough records yet, give it some time.</p>';		
		echo '</div>';
		return;	
	}
	echo '<p>Result of tracked post/pages.</p>';
	echo '<div class="tabmenu">';
	echo '<ul>';
	echo '<li><a class="selected" href="#tab1">Tracking List</a></li> ';
	
	echo '</ul>';
	echo '</div>';
	echo '<div class="tabmenuline"></div>';
	
	// Data tab
	
	
	// Graph tab
	
	// Recent requests tab
	echo '<div id="tab1">';
		// Recent Requests Table
		echo "<table cellpadding='1' cellspacing='0' border='1' id='tblRecentRequests' class='tablesorter'>";
		echo "<thead><tr><th>Date / Time</th><th>IP Address</th><th>Referer</th><th>Page</th><th>Tag</th><th>Queries</th><th>Memory</th><th>Time</th></tr></thead>";
		echo '<tbody>';
		foreach ($recent_results as $recent_result) {
			$class = 'odd' == $class ? '' : 'odd';
			echo "<tr class='$class'>";
			echo "<td>".$recent_result->longdatetime."</td>";
			echo "<td>".$recent_result->ip_address."</td>";
			echo "<td>".$recent_result->referer."</td>";
			
			echo "<td><a href='".$recent_result->qpage."'>".$recent_result->qpage."</a><br>".$recent_result->useragent."</td>";
			echo "<td>".$recent_result->post_tag."</td>";
			echo "<td>".$recent_result->qcount."</td>";
			echo "<td>".$recent_result->qmemory." MB</td>";			
			echo "<td>".$recent_result->qtime."</td>";
			echo "</tr>";
		} 
		echo '</tbody>';
		echo "</table>";	
	echo '</div>';
	
	//End the page content
	echo '</div>';
	if ($record_count > $options['lt_max_records'])
	{
		echo '<p style="color: red"><i>Records: '. $record_count .'</i></p>';
		echo '<form method="post">';                                            
		echo '<input type="hidden" name="doClearOverage" value="yes">';
		echo '<p class="submit"><input type="submit" class="button-primary" value="Clear records overage" /></p>';
		echo '</form>';			
	}
	else 
	{
		echo '<p><i>Records: '. $record_count .'</i></p>';
	}
	echo '<form method="post">';                                            
	echo '<input type="hidden" name="doClearAll" value="yes">';
	echo '<p class="submit"><input type="submit" class="button-primary" value="Clear ALL records" /></p>';
	echo '</form>';			
	echo '<hr />';
}

// Displays the panel at WP-Admin >> Settings >> LT Settings
function lt_settings_panel() 
{
	$message = '';
	
	// Save the options
	if( isset($_POST['info_update']) ) 
	{
		check_admin_referer('lt_settings_panel_update_options');
		$new_options = $_POST['ntracker'];
		update_option( 'plugin_ntracker_settings', $new_options);
		$message = '<div id="message" class="updated fade"><p><strong>' . __('Settings saved.') . '</strong></p></div>';
	}
	else
	{
		check_admin_referer();
	}
	
	// Get the options array
	$options = get_option('plugin_ntracker_settings');	
	echo '<div class="wrap">';
	echo '<h2>Trackit Settings</h2>';
	echo $message;
	echo '<form method="post">';
		wp_nonce_field('lt_settings_panel_update_options');
		echo '<table class="form-table">';
		
		echo '<tr valign="top">';
		echo '<th scope="row">Recent Requests <br/> (How many records want to show?)</th>';
		echo '<td><input type="text" name="ntracker[lt_recent_requests]" value="'. $options['lt_recent_requests'] .'" /></td>';
		echo '</tr>';
		echo '<tr valign="top">';
		echo '<th scope="row">Max Records</th>';
		echo '<td><input type="text" name="ntracker[lt_max_records]" value="'. $options['lt_max_records'] .'" /></td>';
		echo '</tr>';					
		echo '</table>';
		echo '<p class="submit">';
		echo '<input type="submit" name="info_update" value="Save Changes" />';
		echo '</p>';
	echo '</form>';
	echo '</div>';
}

function lt_clear_max_run() 
{
	global $wpdb;
	
	$table_name = lt_get_table_name();
	
	// Get the options array
	$options = get_option('plugin_ntracker_settings');
	
	// Get the record count 
	$record_count = lt_get_record_count();
	
	if ($record_count > $options['lt_max_records'])
	{
		// Delete the overage
		$record_overage = $record_count - $options['lt_max_records'];
		$query = "DELETE FROM " .$table_name ." ORDER BY ID ASC LIMIT ".$record_overage;
		$query_result = $wpdb->query($query);
	}	
}

function lt_get_table_name() 
{
	global $wpdb;
	return $wpdb->prefix."ntracker";
}

function lt_get_record_count() 
{
	global $wpdb;
	$table_name = lt_get_table_name();	
	$record_count_query = $wpdb->get_row("SELECT count(*) AS record_count FROM $table_name");
	return $record_count_query->record_count;
}

// Event and Hook binding \\

// Add new admin panels
function lt_add_admin_panels() {
	// WP-Admin >> Tools >> Trackit
	$page = add_menu_page('Tracker','Tracker', 8,'Trackits','lt_manage_panel');
//	$page = add_management_page('Trackit', 'Trackit', 8,  basename(__FILE__), 'lt_manage_panel');

	// WP-Admin >> Settings >> Settings
	add_options_page('Trackit', 'Trackit', 8,  basename(__FILE__), 'lt_settings_panel');	

	// Load styles and scripts for our page, and our page only
	add_action('admin_print_styles-' . $page, 'lt_admin_styles');
	add_action('admin_print_scripts-' . $page, 'lt_admin_scripts');	
}

function lt_admin_styles() {
	wp_enqueue_style('lt_tabmenu');
	wp_enqueue_style('lt_tablesorter');
}

function lt_admin_scripts() {
	wp_enqueue_script('lt_idtabs');
	wp_enqueue_script('lt_tablesorter');
	wp_enqueue_script('lt_js');
}

function lt_admin_init() {
	wp_register_script('lt_idtabs', plugins_url( 'js/jquery.idtabs.js', __FILE__ ), array('jquery'));
	wp_register_script('lt_tablesorter', plugins_url( 'js/jquery.tablesorter.min.js', __FILE__ ), array('jquery'));
	wp_register_script('lt_js', plugins_url( 'js/n.tracker.js', __FILE__ ), array('jquery'));
	wp_register_style('lt_tabmenu', plugins_url( 'css/tabmenu.css', __FILE__ ));
	wp_register_style('lt_tablesorter', plugins_url( 'css/jquery.tablesorter.css', __FILE__ ));
}


  
 /****************************Outbond links tracking code **************************************************/
function wpct_validate_mod_link($data){

	$errors = array();
	if(!isset($data['link_name']) || $data['link_name'] == ''){
		$errors['link_name'] = TRUE;
	}

	if(!isset($data['link_destination']) || $data['link_destination'] == ''){
		$errors['link_destination'] = TRUE;
	}

	return $errors;
}

function get_link_data($BuildWhere){
	global $wpdb;

	$sql = "SELECT * FROM ".$wpdb->prefix."tracking_links ".$BuildWhere;
	$result = $wpdb->get_results($sql);
	return $result;
}

/********* Format of add/update links page ******************/
function wpct_admin_table($cols, $rows, $msg = FALSE, $tfoot = TRUE, $add_break = FALSE){

	$total_cols = count($cols);
	$total_rows = count($rows);

	$table = '<table class="widefat comments-box " cellspacing="0"><thead><tr>';
	$table_cols = '';
	for($i=0;$i<$total_cols;$i++){
		$table_cols .='<th nowrap>'.$cols[$i].'</th>';
	}

	$table .= $table_cols.'</tr></thead>';
	if($tfoot){
		$table .= '<tfoot><tr>'.$table_cols.'</tr></tfoot>';
	}

	if($total_rows == 0){
		$table .= '<tr><td colspan="'.$total_cols.'" align="center">'.__('Nothing Found','wp-click-track').'</td></tr>';
	} else {

		for($i=0;$i<$total_rows;$i++){
			
			$table .= '<tr>';
			$total_cols = count($rows[$i]);
			for($k=0;$k<$total_cols;$k++){
				$table .='<td>'.$rows[$i][$k].'</td>';
			}
			$table .= '<tr>';
		}
	}

	$table .= '<tbody id="the-comment-list" class="list:comment"></tbody></table>';

	if($add_break){
		$table .= '<br />';
	}
	return $table;
}
/********* Format of add/update links page ******************/

  
 


/****************************Ended Outbond links tracking code **************************************************/
function track_add_custom_box() {
add_meta_box(
'trackpage_sectionid',
__( 'Track Traffic', 'trackpageTextbox' ),
'trackpage_custom_box',
'post',
'side',
'default'
);
add_meta_box(
'trackpage_sectionid',
__( 'Track Traffic', 'trackpageTextbox' ),
'trackpage_custom_box',
'page',
'side',
'default'
);

}

function trackpage_custom_box() {

wp_nonce_field( plugin_basename( __FILE__ ), 'trackpage_noncename' );

$checked = "";
if(isset($_GET['post']) && get_post_meta($_GET['post'], 'trackpage_checkbox') != false) $checked = ' checked="checked" ';

if(isset($_GET['post'])):
$trackTagVal=get_post_meta($_GET['post'], 'trackpagetag_textbox',true);

endif;
echo '<input type="checkbox" id="trackpage_checkbox" name="trackpage_checkbox" '.$checked.'/>';
echo '<label for="disableView_checkbox">';
_e(" Enable traffic", 'trackpage_textbox' );
echo '</label> <br/><br/>';

echo '<input type="text" id="trackpagetag_textbox" name="trackpagetag_textbox" value='.$trackTagVal.'>';
echo '<label for="trackpagetag_textbox">';
_e(" Tag", 'trackpagetag_textbox' );
echo '</label> ';
}

function track_save_post( $post_id ) {
if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
return;

if ( !wp_verify_nonce( $_POST['trackpage_noncename'], plugin_basename( __FILE__ ) ) )
return;

if ( 'page' == $_POST['post_type'] )
{
if ( !current_user_can( 'edit_page', $post_id ) )
return;
}
else
{
if ( !current_user_can( 'edit_post', $post_id ) )
return;
}

if(isset($_POST['trackpage_checkbox'])){
update_post_meta($post_id, 'trackpage_checkbox', 1);
}else{
delete_post_meta($post_id, 'trackpage_checkbox');
}

if(isset($_POST['trackpagetag_textbox'])){
update_post_meta($post_id, 'trackpagetag_textbox', $_POST['trackpagetag_textbox']);

}
}

// Add admin menu hook
add_action('admin_init', 'lt_admin_init');
add_action('admin_menu', 'lt_add_admin_panels');

add_action( 'admin_init', 'track_add_custom_box', 1 );
add_action( 'save_post', 'track_save_post' );

// Bind to wp_footer() function to track trackit and store results
add_action('wp_footer', 'store_timer_data');

// Bind scheduled event to a function
add_action('lt_clear_max', 'lt_clear_max_run');

// On plugin activation
register_activation_hook(__FILE__,'lt_install');

// On plugin deactivation
register_deactivation_hook(__FILE__, 'lt_uninstall' );
?>