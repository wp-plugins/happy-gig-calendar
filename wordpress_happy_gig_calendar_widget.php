<?php

/*

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

class GigCalendarWidget extends WP_Widget {

	function GigCalendarWidget() {
		parent::WP_Widget( false, $name = 'Wordpress Happy Gig Calendar', array( 'description' => __('Wordpress Happy Gig Calendar Widget displays a list of upcoming gigs.', 'wpgigs') ) );
	}
	 
	function widget( $args, $instance ) {
		extract( $args );
		$title = apply_filters( 'widget_title', $instance['title'] );
		$gigs_to_display = apply_filters( 'gigs_to_display', $instance['gigs_to_display'] );
		$display_when_empty = apply_filters( 'display_when_empty', $instance['display_when_empty'] );
		$widget_date_format = apply_filters( 'widget_date_format', $instance['widget_date_format'] );
		$calendar_link = apply_filters( 'calendar_link', $instance['calendar_link'] );
		$link_text = apply_filters( 'link_text', $instance['link_text'] );
		if ($widget_date_format == "") $widget_date_format = 'D j M';
			
		global $wpdb;
		$wpgigs_table = $wpdb->prefix . "wpgigs";
		
		date_default_timezone_set(get_option('timezone_string'));
	
		// get the dates
		$today = date("Y-m-d");
		$sql = "SELECT * FROM $wpgigs_table WHERE end_date >= '$today' ORDER BY start_date ASC LIMIT " . $gigs_to_display;
		$wpgigs_gigs = $wpdb->get_results($sql);
		
		// here are the gigs
		
		if (!empty($wpgigs_gigs) || $display_when_empty) {
		
			$wpgigs_settings = get_option('wpgigs_settings');
		
			echo $before_widget;
			
			if ($title) {
				echo $before_title . $title . $after_title;
			}
			
			if (!empty($wpgigs_gigs)) {
				echo "\n<ul id=\"wpgigs-widget\">";
				foreach ($wpgigs_gigs as $wpgigs_gig) {
					echo "\n<li>";
					$startArray = explode("-", $wpgigs_gig->start_date);
					$start_date = mktime(0,0,0,$startArray[1],$startArray[2],$startArray[0]);
					
					$endArray = explode("-", $wpgigs_gig->end_date);
					$end_date = mktime(0,0,0,$endArray[1],$endArray[2],$endArray[0]);
					
					if ($start_date == $end_date) { // single date format
						echo "<div class=\"date\">" . date_i18n($widget_date_format, $start_date) . " </div>";
					}
					else { // multi-day format
						echo "<div class=\"date\">" . date_i18n($widget_date_format, $start_date) . "&nbsp;&ndash;&nbsp;" . date_i18n($widget_date_format, $end_date) . " </div>";
					}
					
					if ($wpgigs_settings['calendar_url']) {
						echo "\n<h4><a href=\"" . $wpgigs_settings['calendar_url'] . "?gig_id=" . $wpgigs_gig->id . "\">$wpgigs_gig->title</a></h4>";
					}
					else {
						echo "\n<a href=\"" . $query_prefix . "?gig_id=$wpgigs_gig->id\"><h4>$wpgigs_gig->title</h4></a>";
					}
					echo $wpgigs_gig->location;
					echo "</li>";
				}
			
				echo "\n</ul>\n\n";
			}
			else if ($display_when_empty) {
				echo "<p>" . $wpgigs_settings['message'] . "</p>";			
			}
			if ($calendar_link != "" && $wpgigs_settings['calendar_url']) {
				echo "<p><a href=\"" . $wpgigs_settings['calendar_url'] . "\" class=\"calendar_url\">$link_text</a></p>";
			}
			echo $after_widget;
		}
		
	}

	function update( $new_instance, $old_instance ) {
		return $new_instance;
	}
	 
	function form( $instance ) {
		$title = esc_attr( $instance['title'] );
		$gigs_to_display = esc_attr( $instance['gigs_to_display']);
		$display_when_empty = esc_attr( $instance['display_when_empty']);
		$widget_date_format = esc_attr( $instance['widget_date_format']);
		$calendar_link = esc_attr( $instance['calendar_link']);
		$link_text = esc_attr( $instance['link_text']);
		if ($link_text == "") $link_text = __('View My gig Calendar', 'wpgigs');
		if ($widget_date_format == "") $widget_date_format = 'D j M';
		?>
		
		<p>
			<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e('Widget Title', 'wpgigs'); ?>:
			<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo $title; ?>" />
			</label>
		</p>
		<p>
			<label><?php _e('How many gigs to display?', 'wpgigs'); ?>
			<input style="width:30px;" id="<?php echo $this->get_field_id( 'gigs_to_display' ); ?>" name="<?php echo $this->get_field_name( 'gigs_to_display' ); ?>" type="text" value="<?php echo $gigs_to_display; ?>" />
			</label>
		</p>
		<p>
			<label><input class="link_detail_switch" id="<?php echo $this->get_field_id( 'display_when_empty' ); ?>" name="<?php echo $this->get_field_name( 'display_when_empty' ); ?>" type="checkbox" value="1" <?php checked( '1', $display_when_empty ); ?> />
			<?php _e('Display the widget even when there are no upcoming gigs', 'wpgigs'); ?>
			</label>
		</p>
		<p>
			<label><?php _e('Date Format:', 'wpgigs'); ?>
			<input style="width:70px;" id="<?php echo $this->get_field_id( 'widget_date_format' ); ?>" name="<?php echo $this->get_field_name( 'widget_date_format' ); ?>" type="text" value="<?php echo $widget_date_format; ?>" />
			</label> <a href="http://codex.wordpress.org/Formatting_Date_and_Time" title="http://codex.wordpress.org/Formatting_Date_and_Time" target="_blank"><?php _e('Help'); ?>?</a>
		</p>
	
		
		<?php
	
	}
}
 
add_action( 'widgets_init', 'GigCalendarWidgetInit' );
function GigCalendarWidgetInit() {
	register_widget( 'GigCalendarWidget' );
}

?>