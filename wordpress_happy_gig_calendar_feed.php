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

function create_my_customfeed() {
	
	header('Content-Type: ' . feed_content_type('rss-http') . '; charset=' . get_option('blog_charset'), true);
	$more = 1;
	
	echo '<?xml version="1.0" encoding="'.get_option('blog_charset').'"?'.'>'; 
	
	$wpgigs_options = get_option('wpgigs_settings');
	
	if (WPLANG == "") { 
		$language = "en-us"; 
	} else {
		$language = WPLANG; 
	}
	
	?>
	
	<rss version="2.0"
		xmlns:content="http://purl.org/rss/1.0/modules/content/"
		xmlns:wfw="http://wellformedweb.org/CommentAPI/"
		xmlns:dc="http://purl.org/dc/elements/1.1/"
		xmlns:atom="http://www.w3.org/2005/Atom"
		xmlns:sy="http://purl.org/rss/1.0/modules/syndication/"
		xmlns:slash="http://purl.org/rss/1.0/modules/slash/"
		xmlns:wpgigs="http://wordpress.org/extend/plugins/mf-gig-calendar/"
		<?php do_action('rss2_ns'); ?>
	>
	
	<channel>
		<title><?php bloginfo_rss('name'); wp_title_rss(); ?></title>
		<atom:link href="<?php self_link(); ?>" rel="self" type="application/rss+xml" />
		<link><?php bloginfo_rss('url') ?></link>
		<description>MF Gig Cal gigs</description>
		<lastBuildDate><?php echo mysql2date('D, d M Y H:i:s +0000', get_lastpostmodified('GMT'), false); ?></lastBuildDate>
		<language><?php echo $language; ?></language>
		<sy:updatePeriod><?php echo apply_filters( 'rss_update_period', 'hourly' ); ?></sy:updatePeriod>
		<sy:updateFrequency><?php echo apply_filters( 'rss_update_frequency', '1' ); ?></sy:updateFrequency>
		<?php do_action('rss2_head'); ?>
		
		
		<?php //get gigs from db
		
		global $wpdb; 
		$wpgigs_table = $wpdb->prefix . "wpgigs";
		$wpgigs_settings = get_option('wpgigs_settings');
		
		// get the dates
		$today = date("Y-m-d");
		
		$sql = "SELECT * FROM $wpgigs_table WHERE end_date >= '$today' ORDER BY start_date";
		
		$wpgigs_gigs = $wpdb->get_results($sql);
		
		if (!empty($wpgigs_gigs)) {
			foreach ($wpgigs_gigs as $wpgigs_gig) { 
			?>
			
			<item>
				<guid isPermaLink="false"><?=$wpgigs_settings['calendar_url']?>?gig_id=<?=$wpgigs_gig->id?></guid>
				<title><![CDATA[<?=$wpgigs_gig->title?>]]></title>
				<link><?=$wpgigs_settings['calendar_url']?>?gig_id=<?=$wpgigs_gig->id?></link>
				<pubDate><?php echo mysql2date('D, d M Y H:i:s +0000', $wpgigs_gig->pub_date, false); ?></pubDate>
				<description><![CDATA[<p><?php echo wpgigs_feed_FormatDate($wpgigs_gig->start_date, $wpgigs_gig->end_date); ?> <?=$wpgigs_gig->time?></p>
				<? echo $wpgigs_gig->location;
				if ($wpgigs_settings['rss_details']) { echo $wpgigs_gig->details; } 
				?>]]></description>
				<wpgigs:gig-date><?php echo wpgigs_feed_FormatDate($wpgigs_gig->start_date, $wpgigs_gig->end_date); ?></wpgigs:gig-date>
				<wpgigs:gig-time><![CDATA[<?=$wpgigs_gig->time?>]]></wpgigs:gig-time>
				<wpgigs:location><![CDATA[<?=$wpgigs_gig->location?>]]></wpgigs:location>
				<wpgigs:content><![CDATA[<?=$wpgigs_gig->details?>]]></wpgigs:content>
			</item>
		
			<?php 
			} //end gig loop 
		} //end check for gigs
		?>
		
	</channel>
	</rss>

	<?php
}
add_action('do_feed_wpgigs', 'create_my_customfeed', 10, 1); // Make sure to have 'do_feed_customfeed'


function custom_feed_rewrite() {
	global $wp_rewrite;
	$feed_rules = array(
	'feed/wpgigs' => 'index.php?feed=wpgigs',
	'wpgigs.xml' => 'index.php?feed=wpgigs'
	);
	$wp_rewrite->rules = $feed_rules + $wp_rewrite->rules;
	return $wp_rewrite->rules;
}
add_filter('generate_rewrite_rules', 'custom_feed_rewrite');



function wpgigs_feed_FormatDate($start_date, $end_date) {

	$wpgigs_options = get_option('wpgigs_settings');

	$startArray = explode("-", $start_date);
	$start_date = mktime(0,0,0,$startArray[1],$startArray[2],$startArray[0]);
	
	$endArray = explode("-", $end_date);
	$end_date = mktime(0,0,0,$endArray[1],$endArray[2],$endArray[0]);
	
	$wpgigs_date;
	
	if ($start_date == $end_date) {
		if ($startArray[2] == "00") {
			$start_date = mktime(0,0,0,$startArray[1],15,$startArray[0]);			
			$wpgigs_date .= date_i18n("F, Y", $start_date);
			return $wpgigs_date;
		}
		if ($wpgigs_options['rss_date_format'] == "mdy") $wpgigs_date .= date_i18n("M j, Y", $start_date);
		if ($wpgigs_options['rss_date_format'] == "dmy") $wpgigs_date .= date_i18n("j M, Y", $start_date);
		return $wpgigs_date;
	}
	
	if ($startArray[0] == $endArray[0]) {
		if ($startArray[1] == $endArray[1]) {
			if ($wpgigs_options['rss_date_format'] == "mdy") $wpgigs_date .= date_i18n("M j", $start_date) . "-" . date_i18n("j, Y", $end_date);
			if ($wpgigs_options['rss_date_format'] == "dmy") $wpgigs_date .= date_i18n("j", $start_date) . "-" . date_i18n("j M, Y", $end_date);
			return $wpgigs_date;
		}
		if ($wpgigs_options['rss_date_format'] == "mdy") $wpgigs_date .= date_i18n("M j", $start_date) . "-" . date_i18n("M j, Y", $end_date);
		if ($wpgigs_options['rss_date_format'] == "dmy") $wpgigs_date .= date_i18n("j M", $start_date) . "-" . date_i18n("j M, Y", $end_date);
		return $wpgigs_date;
	
	}
	if ($wpgigs_options['rss_date_format'] == "mdy") $wpgigs_date .= date_i18n("M j, Y", $start_date) . "-" . date_i18n("M j, Y", $end_date);
	if ($wpgigs_options['rss_date_format'] == "dmy") $wpgigs_date .= date_i18n("j M, Y", $start_date) . "-" . date_i18n("j M, Y", $end_date);
	return $wpgigs_date;
}




?>