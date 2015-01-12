<?php
/*
Plugin Name: Wordpress Happy Gig Calendar
Plugin URI: http://ifyoubuildit.com.au
Description: A gig calendar plugin designed for artists to easily promote shows. Supports styled content, images and a link to purchase tickets.
Author: If You Build It
Version: 0.1
Author URI: http://ifyoubuildit.com.au
Text Domain: wordpress-gig-calendar

Copyright (C) 2014 If You Build It

Wordpress Happy Gig Calendar is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

Wordpress Happy Gig Calendar is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.

*/

// Stylesheet for display

function wpgigs_load_stylesheet() {
    $url = plugins_url('/css/wordpress_happy_gig_calendar.css', __FILE__);
    wp_register_style('wpgigs_css', $url);
    wp_enqueue_style( 'wpgigs_css');
}
add_action('wp_print_styles', 'wpgigs_load_stylesheet');


// [wpgigs] - SHORT CODE functions

function wpgigs_func( $atts ){
	return wpgigs_getrows($atts);
}
add_shortcode( 'wpgigs', 'wpgigs_func' );

function wpgigs_getrows($atts) {
	extract( shortcode_atts( array( 
		'id' => '', 
		'date' => '',
		'range' => '',
		'sort' => '', 
		'offset' => '0',
		'limit' => '18576385629384657', 
		'days' => '',
		'dayskip' => '',
		'link' => 'true', 
		'title' => 'false'
	), $atts ) );
	
	$sort = strtoupper($sort);
	if ($sort != 'DESC') { $sort = 'ASC'; } // we only allow ASC (default) and DESC for the sort order
	
	$wpgigs_settings = get_option('wpgigs_settings');
	
	$archive_sort = $wpgigs_settings['sort_order'];
	if ($archive_sort != 'DESC') $archive_sort = 'ASC';
	
	
	global $wpdb;
	$wpgigs_table = $wpdb->prefix . "wpgigs";
	
	date_default_timezone_set(get_option('timezone_string'));
	
	// get the dates
	$today = date("Y-m-d");
	
	$sql = "SELECT * FROM $wpgigs_table ";
	
	// clean the variables
	$ytd = wpgigs_Clean($_GET[ytd]);
	$gig_id = wpgigs_Clean($_GET[gig_id]);
	
	if ($ytd == date("Y") && empty($atts)) {
		$sql .= "WHERE (end_date >= '" . $ytd . "-01-01' AND end_date < '$today') ";
		$sort = $archive_sort;
	}
	else if ($ytd && empty($atts)) {
		$sql .= "WHERE (end_date >= '" . $ytd . "-01-01' AND start_date <= '" . $ytd . "-12-31') ";
		$sort = $archive_sort;
	}
	else if ($gig_id) {
		$sql .= "WHERE id = '" . $gig_id . "' ";
	}
	
	
	
	// handle the shortcode variables
	else if ($id) {
		$sql .= "WHERE id = '" . $id . "' ";
	}
	else if ($date) {
		$sql .= "WHERE (end_date >= '" . $date . "' AND start_date <= '" . $date . "') OR end_date = '" . $date . "' ";
	}
	else if ($range) {
		$start_date = substr($range, 0, strpos($range, ':'));
		if ($start_date == 'today') { $start_date = $today; }
		if (preg_match("/^[0-9][0-9]-[0-9][0-9]$/", $start_date)) { $start_date = date(Y) . "-" . $start_date;  } // add this year if they didn't include
		
		$end_date = substr($range, strpos($range, ':') + 1);
		if ($end_date == 'today') { $end_date = $today; }
		if (preg_match("/^[0-9][0-9]-[0-9][0-9]$/", $end_date)) { $end_date = date(Y) . "-" . $end_date;  } // add this year if they didn't include
		
		$sql .= "WHERE (end_date >= '" . $start_date . "' AND start_date <= '" . $end_date . "') ";
	}
	else if ($days) {
		$days = $days - 1; // days originally includes today. we only want to know how many future days
		$end_date = date('Y-m-d', strtotime("+" . $days . "days"));	
		$sql .= "WHERE (end_date >= '" . $today . "' AND start_date <= '" . $end_date . "') ";
	}
	else if ($dayskip) {
		$end_date = date('Y-m-d', strtotime("+" . $dayskip . "days"));
		$sql .= "WHERE end_date >= '$end_date' ";
	}
	
	
	else { // default view displays upcoming gigs
		$sql .= "WHERE end_date >= '$today' ";
	}	
	
	
	$sql .= "ORDER BY start_date " . $sort;
	$sql .= " LIMIT " . $limit . " OFFSET " . $offset;
	
	$wpgigs_gigs = $wpdb->get_results($sql);
	
	$wpgigs_data = "";
	
	
	if (get_option('permalink_structure')) {
		global $post;
		$query_prefix = get_permalink(get_post( $post )->id) . "?";
	}
	else {
		$existing = "?";
		foreach ($_GET as  $k => $v) {
			if ($k != "ytd" && $k != "gig_id") $existing .= $k . "=" . $v . "&";
		}
		$query_prefix = $existing;
	}
	
	if (empty($atts)) { // don't show the nav if we're working with shortcode display
		$wpgigs_data .= wpgigs_CalendarNav();
	}
	
	
	
	if (empty($wpgigs_gigs) && $wpgigs_settings['no-gigs'] == "text") {
		$wpgigs_data .= "<p>" . $wpgigs_settings['message'] . "</p>";
		return $wpgigs_data;
	}
	else if (empty($wpgigs_gigs) && !empty($atts)) {
		$wpgigs_data .= "<p>" . $wpgigs_settings['message'] . "</p>";
		return $wpgigs_data;
	}
	else if (empty($wpgigs_gigs)) {
		$this_year = date("Y");
		// show the current year
		$sql = "SELECT * FROM $wpgigs_table WHERE (end_date >= '$this_year-01-01' AND start_date <= '$this_year-12-31') ORDER BY start_date ASC";
		$wpgigs_gigs = $wpdb->get_results($sql);
		if (empty($wpgigs_gigs)) { 
			$wpgigs_data .= "<p>" . $wpgigs_settings['message'] . "</p>";
			return $wpgigs_data;
		}
	}
	
	$wpgigs_data .= "<ul id=\"cal\">\n";
	
	foreach ($wpgigs_gigs as $wpgigs_gig) { 
	
		$wpgigs_data .= "\n<li class=\"gig\">\n<div class=\"date\">\n\t";
		$wpgigs_data .= wpgigs_FormatDate($wpgigs_gig->start_date, $wpgigs_gig->end_date);
		$wpgigs_data .= "\n</div>\n";
			
		$wpgigs_data .= "<div class=\"info_block\">\n\t<h3>";
		if (!$_GET[gig_id] && ( ($link == 'true' && !empty($atts) ) || (!$wpgigs_settings['gig_links'] && empty($atts) ) )) {
			$wpgigs_data .= "<a href=\"" . $query_prefix . "gig_id=$wpgigs_gig->id\">" . $wpgigs_gig->title . "</a>";
		}	
		else {
			$wpgigs_data .= $wpgigs_gig->title;
		}
		$wpgigs_data .= "</h3>\n";
		$wpgigs_data .= "\t<span class=\"time\">" . $wpgigs_gig->time . "</span>\n";
		$wpgigs_data .= "\t<span class=\"location\">" . $wpgigs_gig->location . "</span>\n";
		$wpgigs_data .= "\t<span class=\"details\">" . do_shortcode( $wpgigs_gig->details ) . "</span>\n";
		$wpgigs_data .= "<a class=\"tickets-button\" target=\"_blank\" href=\"" . $wpgigs_gig->tickets . "\">BUY TICKETS</a>";
		
		$wpgigs_data .= "</div>\n</li>\n";
	
	}
	$wpgigs_data .= "</ul>";
	

	return $wpgigs_data;
	
}

function wpgigs_FormatDate($start_date, $end_date) { // FUNCTION ///////////

	$startArray = explode("-", $start_date);
	$start_date = mktime(0,0,0,$startArray[1],$startArray[2],$startArray[0]);
	
	$endArray = explode("-", $end_date);
	$end_date = mktime(0,0,0,$endArray[1],$endArray[2],$endArray[0]);
	
	if ($start_date == $end_date) { 
		//print date("M j, Y", $start_date); // one day gig
		$wpgigs_date = "<div class=\"end-date\">";
			$wpgigs_date .= "<div class=\"weekday\">" . date_i18n("D", $start_date) . "</div>";
			$wpgigs_date .= "<div class=\"day\">" . date_i18n("d", $start_date) . "</div>";
			$wpgigs_date .= "<div class=\"month\">" . date_i18n("M", $start_date) . "</div>";
			$wpgigs_date .= "<div class=\"year\">" . date_i18n("Y", $start_date) . "</div>";
		$wpgigs_date .= "</div>";
		return $wpgigs_date;
	}
	else {
		//print date("M j, Y", $start_date); // multi-day gig
		$wpgigs_date = "<div class=\"start-date\">";
			$wpgigs_date .= "<div class=\"weekday\">" . date_i18n("D", $start_date) . "</div>";
			$wpgigs_date .= "<div class=\"day\">" . date_i18n("d", $start_date) . "</div>";
			$wpgigs_date .= "<div class=\"month\">" . date_i18n("M", $start_date) . "</div>";
			$wpgigs_date .= "<div class=\"year\">" . date_i18n("Y", $start_date) . "</div>";
		$wpgigs_date .= "</div>";
		
		$wpgigs_date .= "<div class=\"end-date\">";
			$wpgigs_date .= "<div class=\"weekday\">" . date_i18n("D", $end_date) . "</div>";
			$wpgigs_date .= "<div class=\"day\">" . date_i18n("d", $end_date) . "</div>";
			$wpgigs_date .= "<div class=\"month\">" .  date_i18n("M", $end_date) . "</div>";
			$wpgigs_date .= "<div class=\"year\">" . date_i18n("Y", $end_date) . "</div>";
		$wpgigs_date .= "</div>";
		return $wpgigs_date;
	}
	
} // END OF FORMAT DATE FUNCTION!! ///////////////////////////


// INTERNATIONAL ======================================

load_plugin_textdomain('wpgigs', false, basename( dirname( __FILE__ ) ) . '/languages/' );

// ADMIN ==============================================

add_action('admin_menu', 'wpgigs_admin_menu');
function wpgigs_admin_menu() {
	$page = add_menu_page( __('Wordpress Happy Gig Calendar', 'wpgigs'), __('Wordpress Happy Gig Calendar', 'wpgigs'), 'edit_posts', 'wordpress_gig_calendar', 'wpgigs_admin');
	add_action('admin_head-' . $page, 'wpgigs_admin_register_head');
	
	$page = add_submenu_page( 'wordpress_gig_calendar', __('Wordpress Happy Gig Calendar Settings', 'wpgigs'), __('Settings', 'wpgigs'), 'edit_posts', 'wordpress_gig_calendar_settings', 'wpgigs_settings_page');
	add_action('admin_head-' . $page, 'wpgigs_admin_register_head');
	
	$page = add_submenu_page( 'wordpress_gig_calendar', __('About Wordpress Happy Gig Calendar', 'wpgigs'), __('Documentation', 'wpgigs'), 'edit_posts', 'wordpress_gig_calendar_about', 'wpgigs_about_page');
	add_action('admin_head-' . $page, 'wpgigs_admin_register_head');
	
	//call register settings function
	add_action( 'admin_init', 'wpgigs_register_settings' );
	
}

// Stylesheet for admin

function wpgigs_admin_register_head() {
	global $post, $wp_locale;
	date_default_timezone_set(get_option('timezone_string'));
	
    // add the jQuery UI elements shipped with WP
    wp_enqueue_script( 'jquery' );
    wp_enqueue_script( 'jquery-ui-datepicker' );

	// add the style
	wp_enqueue_style( 'jquery.ui.theme', plugins_url( '/css/jquery-ui-1.9.2.custom.css', __FILE__ ) );
 	wp_enqueue_style( 'wpgigs-css', plugins_url( '/css/wordpress_happy_gig_calendar_admin.css', __FILE__ ) );  
 	
    // add wpgigs js
	wp_enqueue_script( 'wpgigs-admin', $siteurl . '/wp-content/plugins/' . basename(dirname(__FILE__)) . '/wordpress_happy_gig_calendar_admin.js', array( 'jquery-ui-datepicker' ) );
 
    // localize our js for datepicker
    $aryArgs = array(
        'closeText'         => __( 'Done', 'wpgigs' ),
        'currentText'       => __( 'Today', 'wpgigs' ),
        'monthNames'        => strip_array_indices( $wp_locale->month ),
        'monthNamesShort'   => strip_array_indices( $wp_locale->month_abbrev ),
        'monthStatus'       => __( 'Show a different month', 'wpgigs' ),
        'dayNames'          => strip_array_indices( $wp_locale->weekday ),
        'dayNamesShort'     => strip_array_indices( $wp_locale->weekday_abbrev ),
        'dayNamesMin'       => strip_array_indices( $wp_locale->weekday_initial ),
        // get the start of week from WP general setting
        'firstDay'          => get_option( 'start_of_week' ),
        // is Right to left language? default is false
        'isRTL'             => $wp_locale->is_rtl,
    );
 
    // Pass the translation array to the enqueued JS for datepicker
    wp_localize_script( 'wpgigs-admin', 'objectL10n', $aryArgs );
}


// Settings Page

function wpgigs_register_settings() {
	//register our settings
	register_setting( 'wpgigs_settings', 'wpgigs_settings', 'wpgigs_settings_validate' );
	
	add_settings_section('wpgigs_settings_setup', __('INSTRUCTIONS', 'wpgigs'), 'wpgigs_settings_display_setup', 'wpgigs');
	

	add_settings_section('wpgigs_settings_display', __('SETTINGS', 'wpgigs'), 'wpgigs_settings_display_text', 'wpgigs');
	
	add_settings_field('sort_order', __('Order', 'wpgigs'), 'wpgigs_settings_display_sort_field', 'wpgigs', 'wpgigs_settings_display');
	add_settings_field('gig_links', __('Individual Gigs', 'wpgigs'), 'wpgigs_settings_display_link_field', 'wpgigs', 'wpgigs_settings_display');
	add_settings_field('display', __('No Upcoming Gigs?', 'wpgigs'), 'wpgigs_settings_display_display_field', 'wpgigs', 'wpgigs_settings_display');
}

function wpgigs_settings_validate($input) {
	return $input;
}

function wpgigs_settings_display_setup() {

	echo "<p>" . __('Simple! Place this shortcode on any Page or Post where you would like your gigs to appear', 'wpgigs') . ": </p>
	
	<p><strong>[wpgigs]</strong></p>
	
	<p><a href=\"admin.php?page=wordpress_gig_calendar&action=edit\" class=\"button-primary\">" . __('CREATE A GIG', 'wpgigs') . "</a></p>";
}


function wpgigs_settings_display_text() {
	echo "<p>" . __('Once you have gigs displaying on your site you can change the default Wordpress Happy Gig Calendar with the following settings.', 'wpgigs') . "</p>";
}

function wpgigs_settings_display_display_field() {
    $siteurl = get_option('siteurl');
	$options = get_option('wpgigs_settings');
	?>
	<p><?php _e('What do you want your calendar to display if you don\'t have any upcoming gigs?', 'wpgigs'); ?></p>
	<p><label><input type="radio" name="wpgigs_settings[no-gigs]" value="archive" checked> <?php _e('Display the archive of gigs', 'wpgigs'); ?> <i>(<?php _e('default', 'wpgigs'); ?>)</i></label><br />
	<label><input type="radio" name="wpgigs_settings[no-gigs]" value="text" <?php checked( 'text', $options['no-gigs'] ); ?>> <?php _e('Display this message:', 'wpgigs'); ?></label></p>
	<p><textarea id="message" name="wpgigs_settings[message]" style="width:300px;"><?=$options['message']?></textarea></p>
	
	<?php
}

function wpgigs_settings_display_sort_field() {
	$options = get_option('wpgigs_settings');
	($options['sort_order'] == "") ? $sort_order = 'ASC' : $sort_order = $options['sort_order']
	?>
	<p><?php _e('How do you want to order your past gigs? ', 'wpgigs'); ?>
	<select id="sort_order" name="wpgigs_settings[sort_order]">
	<option value="ASC" <?php if ($options['sort_order'] == "ASC") echo "selected"; ?>>Ascending  </option>
	<option value="DESC" <?php if ($options['sort_order'] == "DESC") echo "selected"; ?>>Descending  </option>
	</select>
	<br>	
	</p><?php
}

function wpgigs_settings_display_link_field() {
	$options = get_option('wpgigs_settings');
	?>
	<p><?php _e('Your gig title will link to a single page displaying only that gig. You can disable this feature here.', 'wpgigs');?></p>
	<p><label><input id="rss" name="wpgigs_settings[gig_links]" type="checkbox" value="1" <?php checked( '1', $options['gig_links'] ); ?> />
	<?php _e('Do not link my gig titles to individual gig pages', 'wpgigs'); ?></label><br>
	</p><?php
}

function wpgigs_settings_page () {
	if (!current_user_can('edit_posts'))  {
		wp_die( __('You do not have sufficient permissions to access this page.') );
	}
	
	if( isset($_GET['settings-updated']) ) { ?>
		<div id="message" class="updated">
			<p><strong><?php _e('Settings saved.', 'wpgigs') ?></strong></p>
		</div>
	<?php } ?>
	
	<div class="wrap">
	<h2 class="wpgigs-heading"><?php _e('Wordpress Happy Gig Calendar Settings', 'wpgigs'); ?></h2>
	
	<form method="post" action="options.php">    
	<?php settings_fields( 'wpgigs_settings' ); ?>
    <?php do_settings_sections('wpgigs'); ?>

	<input name="Submit" type="submit" value="<?php _e('Save Changes', 'wpgigs'); ?>" />
	</form>
	</div>
	
	<?php
}

function wpgigs_about_page() {

    $siteurl = get_option('siteurl');
    
	echo '<div class="wrap">';
	echo '<h2 class="wpgigs-heading">' . __('Wordpress Happy Gig Calendar', 'wpgigs') . '</h2>';
	
	?>
	
	
	<h3><?php _e('SHORTCODE INSTRUCTIONS', 'wpgigs'); ?></h3>
	<p><?php _e('Simple! Place this shortcode on any Page or Post where you would like your gigs to appear', 'wpgigs'); ?>: </p>
	
	<blockquote><strong>[wpgigs]</strong></blockquote>
	</br>
	<h3><?php _e('WIDGET INSTRUCTIONS', 'wpgigs'); ?></h3>
	<p><?php _e('The plugin includes a widget you can place in your sidebar to display upcoming gigs. Drag it into your sidebar to display a list of upcoming gigs.', 'wpgigs'); ?> </br>
	<?php _e('Be sure to also check out the Gig Calendar Settings.', 'wpgigs'); ?>: <a href="admin.php?page=wordpress_gig_calendar_settings"><?php _e('Gig Calendar Settings', 'wpgigs'); ?></a></p>
    
   
	</br>
	<h3><?php _e('ADVANCED', 'wpgigs'); ?></h3>
	
	<p><?php _e('You can display more specific gig information by including variables in your short code.', 'wpgigs'); ?></p>
	
	<blockquote>
	<p>
	<b><?php _e('SPECIFIC GIG', 'wpgigs'); ?></b><br>
	[wpgigs id=gig_id] - <?php _e('display only one specific gig', 'wpgigs'); ?>
	</p>
	<p>
	<b><?php _e('BY DATE', 'wpgigs'); ?></b><br>
	[wpgigs date=YYYY-MM-DD] - <?php _e('(Year-Month-Day) display gigs that are happening on a particular date', 'wpgigs'); ?>
	</p>
	<p>
	<b><?php _e('BY DATE RANGE', 'wpgigs'); ?></b><br>
	[wpgigs range=START:END] - <?php _e('display gig within a particular date range using these accepted date-range formats', 'wpgigs'); ?>:
	</p>
	<p>
	YYYY-MM-DD - <?php _e('(Year-Month-Day) display specific dates', 'wpgigs'); ?><br>
	MM-DD - <?php _e('(Month-Day) - display specific dates in the current year', 'wpgigs'); ?><br>
	today - <?php _e('display specific dates in relation to the current date', 'wpgigs'); ?><br>
	</p>
	<p><?php _e('EXAMPLE: [wpgigs range=01-01:today] would show a year-to-date list of gigs', 'wpgigs'); ?></p>
	</blockquote>
	
	 <p><a href="?page=wordpress_gig_calendar&action=edit" class="button-primary"><?php _e('CREATE A GIG', 'wpgigs'); ?></a></p>

	<?php
	
	echo '</div>';
	
}

function wpgigs_admin() {
	if (!current_user_can('edit_posts'))  {
		wp_die( __('You do not have sufficient permissions to access this page.') );
	}
	
	// is there POST data to deal with?
	if ($_POST) {
		wpgigs_save_record();
	}

	$today = date('Y-m-d');
	$expire = "2013-12-09";
	if ($today <= $expire) {
		echo '';
	}

	switch ($_GET[action]) {

		case "edit" :
			echo '<div class="wrap">';
			echo wpgigs_edit_gig();
			echo '</div>';
			break;
			
		case "delete" :
			wpgigs_delete_gig();
			echo '<div class="wrap">';
			echo wpgigs_list_gigs();
			echo '</div>';
			break;
			
		case "copy" :
			echo '<div class="wrap">';
			echo wpgigs_edit_gig();
			echo '</div>';
			break;
			
		default :
			echo '<div class="wrap">';
			echo wpgigs_list_gigs();
			echo '</div>';
	}

}

function wpgigs_delete_gig() {
	global $wpdb;
	$wpgigs_table = $wpdb->prefix . "wpgigs";
	$wpdb->query(
		"
		DELETE FROM $wpgigs_table 
		WHERE id = '$_GET[id]'
		"
	);
}

function wpgigs_save_record() {
	global $wpdb;
	$wpgigs_table = $wpdb->prefix . "wpgigs";
	
	remove_wp_magic_quotes();
	
	if (empty($_POST['start_date']) || empty($_POST['title'])) {
		echo '<div class="updated"><p>' . _e('Oops! Required information was not provided. gig could not be saved.') . '</p></div>';
		return;
	}
	
	// catch bad submissions that might get lost in the database...
	if (empty($_POST['end_date'])) {
		$end_date = $_POST['start_date'];
	}
	else {
		$end_date = $_POST['end_date'];
	}
	
	if ($_POST[id]) {  // update record
		$wpdb->update( 
			$wpgigs_table, 
			array( 
				'start_date' => $_POST[start_date], 
				'end_date' => $end_date,
				'pub_date' => date("Y-m-d H:i:s"),
				'time' => $_POST[time],
				'title' => $_POST[title],
				'tickets' => $_POST[tickets],
				'location' => $_POST[location],
				'details' => $_POST[details]
			), 
			array ( 'id' => $_POST[id]),
			array( 
				'%s', 
				'%s', 
				'%s', 
				'%s', 
				'%s',
				'%s', 
				'%s' 
			) 
		);
	}
	else { // new record
		$wpdb->insert( 
			$wpgigs_table, 
			array( 
				'start_date' => $_POST[start_date], 
				'end_date' => $end_date,
				'pub_date' => date("Y-m-d H:i:s"),
				'time' => $_POST[time],
				'title' => $_POST[title],
				'tickets' => $_POST[tickets],
				'location' => $_POST[location],
				'details' => $_POST[details]
			), 
			array( 
				'%s', 
				'%s', 
				'%s', 
				'%s', 
				'%s',
				'%s', 
				'%s' 
			) 
		);
	}
}

function wpgigs_edit_gig() {
	global $wpdb;
	$wpgigs_table = $wpdb->prefix . "wpgigs";
	
	$sql = "SELECT *
			FROM $wpgigs_table
			WHERE id = $_GET[id]
			LIMIT 1";
			
	$wpgigs_gig = $wpdb->get_row($sql);
	
	if ($_GET[action] == "copy") {
		$start_date = date('Y-m-d');
		$end_date = date('Y-m-d');
	}
	else {
		$start_date = $wpgigs_gig->start_date;
		$end_date = $wpgigs_gig->end_date;
	}
	
	if ($start_date == "") {
		$start_date = date('Y-m-d');
		$end_date = date('Y-m-d');
	}
	
	echo "<h2 class=\"wpgigs-heading\">" . __('Add/Edit gig', 'wpgigs') . "</h2>";
	
	echo "<form id=\"edit_gig_form\" method=\"POST\" action=\"?page=wordpress_gig_calendar\">";
	if ($_GET[action] == "edit") echo "<input type=\"hidden\" name=\"id\" value=\"$_GET[id]\" />";
		echo "<table class=\"form-table\">";
			/*echo "<th><label class=\"required\">" . __('Start Date', 'wpgigs') . " (" . __('required', 'wpgigs') . ") </label></th>";
			echo "<td><input type=\"hidden\" class=\"text form-required\" name=\"start_date\" id=\"start_date\" value=\"$start_date\" /> <label><input type=\"checkbox\" id=\"multi\" /> Multiple Day gig</label></td>";
		echo "</tr>";
		echo "<tr id=\"end_date_row\">";
			echo "<th><label>" . __('End Date', 'wpgigs') . "</label></th>";
			echo "<td><input type=\"hidden\" class=\"text\" name=\"end_date\" id=\"end_date\" value=\"$end_date\" /></td>";
		echo "</tr>";*/
		
		echo "<tr><th><label class=\"required\">" . __('Date', 'wpgigs') . " <i>(" . __('required', 'wpgigs') . ")</i></label></th>
		<td><div class=\"wpgigs-datepicker\"></div>
		
		<div style=\"padding:1em;font-size:.8em;\">FROM <input type=\"text\" class=\"text\" name=\"start_date\" id=\"start_date\" value=\"$start_date\" /> TO 
		<input type=\"text\" class=\"text\" name=\"end_date\" id=\"end_date\" value=\"$end_date\" /></div>
		
		
		</td></tr>";
		
		
		echo "<tr>";
			echo "<th><label class=\"required\">" . __('Gig Title', 'wpgigs') . " <i>(" . __('required', 'wpgigs') . ")</i></label></th>";
			echo "<td><input type=\"text\" class=\"text form-required\" style=\"width:400px;font-size:1.5em;\" name=\"title\" id=\"title\" value=\"" . str_replace('"', "&quot;", $wpgigs_gig->title) . "\" /><br>&nbsp;</td>";
		echo "</tr>";
		
		echo "<tr>";
			echo "<th><label>" . __('Gig Time', 'wpgigs') . "</label></th>";
			echo "<td><input type=\"text\" class=\"text\" name=\"time\" id=\"time\" value=\"" . str_replace('"', "&quot;", $wpgigs_gig->time) . "\" /></td>";
		echo "</tr>";
		
		echo "<tr>";
			echo "<th><label class=\"required\">" . __('Tickets Link', 'wpgigs') . " <i>(" . __('Required: Where can people buy tickets? include full url http://...', 'wpgigs') . ")</i></label></th>";
			echo "<td><input type=\"text\" class=\"text form-required\" style=\"width:400px;\" name=\"tickets\" id=\"tickets\" value=\"" . str_replace('"', "&quot;", $wpgigs_gig->tickets) . "\" /><br>&nbsp;</td>";
		echo "</tr>";
		
		echo "<tr>";
			echo "<th><label>" . __('Gig Location', 'wpgigs') . "</label></th>";
			echo "<td>";
			
			$settings = array(
				'media_buttons' => false,
				'wpautop' => false,
				'tinymce' => array(
					'theme_advanced_buttons1' => 'bold,italic,underline,|,undo,redo,|,link,unlink,|,fullscreen',
					'theme_advanced_buttons2' => '',
					'height' => '200',
					'forced_root_block' => 'p'
				),
				'quicktags' => true
			);
			wp_editor($wpgigs_gig->location, "location", $settings);
			echo "</td>";
		echo "</tr>";
		
		echo "<tr>";
			echo "<th><label>" . __('Gig Details', 'wpgigs') . "</label></th>";
			echo "<td>";
			
			$settings = array(
				'media_buttons' => true,
				'wpautop' => false,
				'tinymce' => array(
					'theme_advanced_buttons1' => 'bold,italic,strikethrough,|,bullist,numlist,blockquote,justifyleft,justifycenter,justifyright,link,unlink,spellchecker,fullscreen',
					'theme_advanced_buttons2' => 'formatselect,underline,justifyfull,forecolor,pastetext,pasteword,removeformat,charmap,outdent,indent,undo,redo,help',
					'height' => '400',
					'forced_root_block' => 'p'
				),
				'quicktags' => true
			);
			wp_editor($wpgigs_gig->details, "details", $settings);
			echo "</td>";
		echo "</tr>";
		
	echo "</table>";
    
    echo '<p class="submit"><input type="submit" class="button-primary" name="save" value="' . __('SAVE GIG', 'wpgigs') . '" id="submitbutton"> <a href="?page=wordpress_gig_calendar" class="button-secondary">' . __('CANCEL', 'wpgigs') . '</a>';
	echo '&nbsp;&nbsp;<span id="wpgigs_error_message">' . __('Please fill in all the required information to save your gig.', 'wpgigs') . '</span></p></form>';
}

function wpgigs_list_gigs() {
	global $wpdb;
	$wpgigs_table = $wpdb->prefix . "wpgigs";

	// get the dates
	$today = date("Y-m-d");
	
	$ytd = wpgigs_Clean($_GET[ytd]);
	
	$sql = "SELECT * FROM $wpgigs_table ";
	
	if ($ytd == date("Y")) {
		$sql .= "WHERE (end_date >= '" . $ytd . "-01-01' AND end_date < '$today') ";
	}
	else if ($ytd) {
		$sql .= "WHERE (end_date >= '" . $ytd . "-01-01' AND start_date <= '" . $ytd . "-12-31') ";
	}
	else {
		$sql .= "WHERE end_date >= '$today' ";
	}	
	$sql .= "ORDER BY start_date";
	
	$wpgigs_gigs = $wpdb->get_results($sql);
	
	if (!empty($wpgigs_gigs)) {
	
		$wpgigs_data = wpgigs_CalendarNav();
		$wpgigs_data .= '<p style="text-align:right"><a href="?page=wordpress_gig_calendar&action=edit" class="button-primary">' . __('CREATE A GIG', 'wpgigs') . '</a></p>';
		$wpgigs_data .= "<table class=\"widefat\" style=\"margin-top:10px;\">";
		$wpgigs_data .= "<thead>";
		$wpgigs_data .= "<tr class=\"header-row\"><th>" . __('Gig Shortcode', 'wpgigs') . "</th><th class=\"gig_date\">" . __('Date', 'wpgigs') . "</th><th class=\"gig_location\">" . __('Gig', 'wpgigs') . "</th><th class=\"gig_details\" >" . __('Gig Details', 'wpgigs') . "</th><th class=\"gig_tickets\" >" . __('Tickets', 'wpgigs') . "</th><th></th></tr>";
		$wpgigs_data .= "</thead>";
	
		foreach ($wpgigs_gigs as $wpgigs_gig) { 
		
			$wpgigs_data .= "<tr><td style='white-space:nowrap;'>[wpgigs gig_id=$wpgigs_gig->id]</td>";
			$wpgigs_data .= "<td class=\"gig_date\">";
			$wpgigs_data .= wpgigs_admin_FormatDate($wpgigs_gig->start_date, $wpgigs_gig->end_date) . "<br />";
			$wpgigs_data .= $wpgigs_gig->time;
			$wpgigs_data .= "</td>";
			$wpgigs_data .= "<td class=\"gig_location\"><div class=\"gig_title\">" . $wpgigs_gig->title . "</div>" . wpgigs_admin_PrintTruncated(80, $wpgigs_gig->location) . "</td>";
			$wpgigs_data .= "<td class=\"gig_details\">" . wpgigs_admin_PrintTruncated(100, $wpgigs_gig->details) . "</td>";
			$wpgigs_data .= "<td class=\"gig_tickets\">" . wpgigs_admin_PrintTruncated(100, $wpgigs_gig->tickets) . "</td>";
			
			$wpgigs_data .= "<td class=\"buttons\" style=\"white-space:nowrap;\">";
			$wpgigs_data .= "<a href=\"?page=wordpress_gig_calendar&id=$wpgigs_gig->id&action=edit\" class=\"button-secondary\" title=\"" . __('Edit this gig', 'wpgigs') . "\">" . __('Edit', 'wpgigs') . "</a> ";
			$wpgigs_data .= "<a href=\"?page=wordpress_gig_calendar&id=$wpgigs_gig->id&action=copy\" class=\"button-secondary\" title=\"" . __('Create a new gig based on this gig', 'wpgigs') . "\">" . __('Duplicate', 'wpgigs') . "</a> ";
			$wpgigs_data .= "<a href=\"#\" onClick=\"wpgigs_Deletegig($wpgigs_gig->id);return false;\" class=\"button-secondary\" title=\"" . __('Delete this gig', 'wpgigs') . "\">" . __('Delete', 'wpgigs') . "</a>";
			$wpgigs_data .= "</td></tr>";

		}
	}
	
	else {	
		$wpgigs_data = wpgigs_CalendarNav();
		$wpgigs_data .= '<p style="text-align:right"><a href="?page=wordpress_gig_calendar&action=edit" class="button-primary">' . __('CREATE A GIG', 'wpgigs') . '</a></p>';
		$wpgigs_data .= "<table class=\"widefat\" style=\"margin-top:10px;\">";
		$wpgigs_data .= "<thead>";
		$wpgigs_data .= "<tr class=\"header-row\"><th>" . __('Gig Shortcode', 'wpgigs') . "</th><th class=\"gig_date\">" . __('Date', 'wpgigs') . "</th><th class=\"gig_location\">" . __('Gig', 'wpgigs') . "</th><th class=\"gig_details\" >" . __('Gig Details', 'wpgigs') . "</th><th class=\"gig_tickets\" >" . __('Tickets', 'wpgigs') . "</th></tr>";
		$wpgigs_data .= "</thead>";
		$wpgigs_data .=  "<tr>
				<td colspan=\"10\" style=\"text-align:center;\">" . __('No gigs found in this range.', 'wpgigs') . "</td>
			</tr>";
	}
	
	$wpgigs_data .= "</table>";
	return $wpgigs_data;
}

function wpgigs_admin_FormatDate($start_date, $end_date) {

	$startArray = explode("-", $start_date);
	$start_date = mktime(0,0,0,$startArray[1],$startArray[2],$startArray[0]);
	
	$endArray = explode("-", $end_date);
	$end_date = mktime(0,0,0,$endArray[1],$endArray[2],$endArray[0]);
	
	$wpgigs_date;
	
	if ($start_date == $end_date) {
		if ($startArray[2] == "00") {
			$start_date = mktime(0,0,0,$startArray[1],15,$startArray[0]);			
			$wpgigs_date .= '<span style="white-space:nowrap;">' . date_i18n("F, Y", $start_date) . "</span>";
			return $wpgigs_date;
		}
		$wpgigs_date .= '<span style="white-space:nowrap;">' . date_i18n("M j, Y", $start_date) . "</span>";
		return $wpgigs_date;
	}
	
	if ($startArray[0] == $endArray[0]) {
		if ($startArray[1] == $endArray[1]) {
			$wpgigs_date .= '<span style="white-space:nowrap;">' . date_i18n("M j", $start_date) . "-" . date_i18n("j, Y", $end_date) . "</span>";
			return $wpgigs_date;
		}
		$wpgigs_date .= '<span style="white-space:nowrap;">' . date_i18n("M j", $start_date) . "-" . date_i18n("M j, Y", $end_date) . "</span>";
		return $wpgigs_date;
	
	}
	
	$wpgigs_date .= '<span style="white-space:nowrap;">' . date_i18n("M j, Y", $start_date) . "-" . date_i18n("M j, Y", $end_date) . "</span>";
	return $wpgigs_date;
}


// FEED
include_once('wordpress_happy_gig_calendar_feed.php');


// WIDGET
include_once('wordpress_happy_gig_calendar_widget.php');



// UTILITIES 

function remove_wp_magic_quotes() {
	$_GET    = stripslashes_deep($_GET);
	$_POST   = stripslashes_deep($_POST);
	$_COOKIE = stripslashes_deep($_COOKIE);
	$_REQUEST = stripslashes_deep($_REQUEST);
}

function strip_array_indices( $ArrayToStrip ) {
    foreach( $ArrayToStrip as $objArrayItem) {
        $NewArray[] =  $objArrayItem;
    }
 
    return( $NewArray );
}

function date_format_php_to_js( $sFormat ) {
    switch( $sFormat ) {
        //Predefined WP date formats
        case 'F j, Y':
            return( 'MM dd, yy' );
            break;
        case 'Y/m/d':
            return( 'yy/mm/dd' );
            break;
        case 'm/d/Y':
            return( 'mm/dd/yy' );
            break;
        case 'd/m/Y':
            return( 'dd/mm/yy' );
            break;
     }
}

function wpgigs_ExtractDate($date, $format) {
	
	if ($date == "0000-00-00" || !$date) return false;
	
	$dateArray = explode("-", $date);
	$date = mktime(0,0,0,$dateArray[1],$dateArray[2],$dateArray[0]);
	
	// special for day set to "00"
	if ($dateArray[2] == "00") {
		if ($format == "d") {
			return "00";
		}
		else {
			$date = mktime(0,0,0,$dateArray[1],15,$dateArray[0]);
		}
	}
	
	return date_i18n($format, $date);
}

function wpgigs_CalendarNav($show_title = true) {
	global $wpdb;
	$wpgigs_table = $wpdb->prefix . "wpgigs";
	
	$sql = "SELECT DISTINCT *
			FROM $wpgigs_table 
			WHERE start_date != '0000-00-00'
			ORDER BY start_date
			ASC
			LIMIT 1";
	$first_year = $wpdb->get_results($sql, ARRAY_A);
	
	(!empty($first_year)) ? $first_year = wpgigs_ExtractDate($first_year[0][start_date],'Y') : $first_year = date("Y");
	
	$sql = "SELECT DISTINCT *
			FROM $wpgigs_table 
			WHERE end_date != '0000-00-00'
			ORDER BY end_date
			DESC
			LIMIT 1";
	$last_year = $wpdb->get_results($sql, ARRAY_A);
	
	(!empty($last_year)) ? $last_year = wpgigs_ExtractDate($last_year[0][end_date],'Y') : $last_year = date("Y");
	
	if ( is_admin() ) {
		$query_prefix = "?page=wordpress_gig_calendar&";
	}
	else if (get_option('permalink_structure')) {
		global $post;
		$query_prefix = get_permalink(get_post( $post )->id) . "?";
	}
	else {
		$existing = "?";
		foreach ($_GET as  $k => $v) {
			if ($k != "ytd" && $k != "gig_id") $existing .= $k . "=" . $v . "&";
		}
		$query_prefix = $existing;
	}
	
	($query_prefix == get_permalink(get_post( $post )->id) . "?") ? $reset_link = get_permalink(get_post( $post )->id) : $reset_link = $query_prefix;
	
	$ytd = wpgigs_Clean($_GET[ytd]);
	$gig_id = wpgigs_Clean($_GET[gig_id]);
	
	$wpgigs_settings = get_option('wpgigs_settings');
	if ($wpgigs_settings['always_use_url'] && $wpgigs_settings['calendar_url'] && !is_admin()) {
		$link_prefix = $wpgigs_settings['calendar_url'];
	}
	
	if ($ytd) {
		if ($show_title) $wpgigs_nav = "<h2 id=\"cal_title\">" . $ytd . "</h2>";
		$wpgigs_nav .= "<div id=\"cal_nav\"><a href=\"" . $reset_link . "\">" . __('Upcoming', 'wpgigs') . "</a> ";
	}
	else if ($gig_id) {
		if ($show_title) {
			($wpgigs_settings['gig_title'] == "") ? $gig_title = __('', 'wpgigs') : $gig_title = $wpgigs_settings['gig_title'];
			$wpgigs_nav = "<h2 id=\"cal_title\">$gig_title</h2>";
		}
		$wpgigs_nav .= "<div id=\"cal_nav\"><a href=\"" . $reset_link . "\">" . __('Upcoming', 'wpgigs') . "</a> ";
	}
	else {
		if ($show_title) {
			($wpgigs_settings['upcoming_title'] == "") ? $upcoming_title = __('Upcoming Gigs', 'wpgigs') : $upcoming_title = $wpgigs_settings['upcoming_title'];
			$wpgigs_nav = "<h2 id=\"cal_title\">$upcoming_title</h2>";
		}
		$wpgigs_nav .= "<div id=\"cal_nav\"><strong>" . __('Upcoming', 'wpgigs') . "</strong>  ";
	}
	
	for ($i=$last_year;$i>=$first_year;$i--) {
		($i == $ytd) ? $wpgigs_nav .= "<strong>$i</strong> " : $wpgigs_nav .= "<a href=\"" . $query_prefix . "ytd=$i\">$i</a> ";
	}
	$wpgigs_nav .= "</div>";
	return $wpgigs_nav;

}


function wpgigs_admin_PrintTruncated($maxLength, $html) {
    $printedLength = 0;
    $position = 0;
    $tags = array();
	
	$wpgigs_html;

    while ($printedLength < $maxLength && preg_match('{</?([a-z]+)[^>]*>|&#?[a-zA-Z0-9]+;}', $html, $match, PREG_OFFSET_CAPTURE, $position)) {
        list($tag, $tagPosition) = $match[0];

        // Print text leading up to the tag.
        $str = substr($html, $position, $tagPosition - $position);
        if ($printedLength + strlen($str) > $maxLength) {
            $wpgigs_html .= substr($str, 0, $maxLength - $printedLength);
            $printedLength = $maxLength;
            break;
        }

        $wpgigs_html .= $str;
        $printedLength += strlen($str);

        if ($tag[0] == '&') {
            // Handle the entity.
            $wpgigs_html .= $tag;
            $printedLength++;
        }
        else {
            // Handle the tag.
            $tagName = $match[1][0];
            if ($tag[1] == '/')
            {
                // This is a closing tag.

                $openingTag = array_pop($tags);
                assert($openingTag == $tagName); // check that tags are properly nested.

                $wpgigs_html .= $tag;
            }
            else if ($tag[strlen($tag) - 2] == '/') {
                // Self-closing tag.
                $wpgigs_html .= $tag;
            }
            else {
                // Opening tag.
                $wpgigs_html .= $tag;
                $tags[] = $tagName;
            }
        }

        // Continue after the tag.
        $position = $tagPosition + strlen($tag);
    }

    // Print any remaining text.
    if ($printedLength < $maxLength && $position < strlen($html)) {
        $wpgigs_html .= substr($html, $position, $maxLength - $printedLength);
    }
    
    if ($maxLength < strlen($html)) { 
    	$wpgigs_html .= "...";
    }

    // Close any open tags.
    while (!empty($tags))
        $wpgigs_html .= "</" . array_pop($tags) . ">";
        
    return $wpgigs_html;
}

function wpgigs_Clean($var) {
	if (strval(intval($var)) == strval($var)) { // we're only using numbers. Nothing else is allowed.
		return $var;
	}
	else {
		return false;
	}
}








// ACTIVATION - create the database table to store the information

global $wpgigs_db_version;
$wpgigs_db_version = "1.1";

function wpgigs_install() {
	global $wpdb;
	global $wpgigs_db_version;

	$table_name = $wpdb->prefix . "wpgigs";
      
	$sql = "CREATE TABLE " . $table_name . " (
		id int(11) NOT NULL AUTO_INCREMENT,
		pub_date datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
		start_date date NOT NULL DEFAULT '0000-00-00',
		end_date date DEFAULT NULL,
		time text,
		title text NOT NULL,
		tickets text NOT NULL,
		location text,
		details text,
		PRIMARY KEY  (id)
	);";

	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	dbDelta($sql);
 
	add_option("wpgigs_db_version", $wpgigs_db_version);
	
}

register_activation_hook(__FILE__,'wpgigs_install');


// UPDATE DB 
function wpgigs_update_db_check() {
    global $wpgigs_db_version;
    if (get_site_option('wpgigs_db_version') != $wpgigs_db_version) {
        wpgigs_install();
		update_option( "wpgigs_db_version", $wpgigs_db_version);
    }
}
add_action('plugins_loaded', 'wpgigs_update_db_check');

?>