<?php
/*
Plugin Name: BMLT Tabbed UI 
Description: Adds a jQuery Tabbed UI for BMLT.
Author: Jack S Florida Region Modified for German Region by Ron
Version: 2.0
*/
/* Disallow direct access to the plugin file */
if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {
	// die('Sorry, but you cannot access this page directly.');
}

use Jaybizzle\CrawlerDetect\CrawlerDetect;
require_once plugin_dir_path(__FILE__).'vendor/autoload.php';
require_once 'MeetingHelper.php';
if (!class_exists("BMLTTabs")) {
	class BMLTTabs {
		var $optionsName = 'bmlt_tabs_options';
		var $options = array();
		var $exclude_zip_codes = Null;
		var $crawlerDetected = null;
		
		function __construct() {
			$this->crawlerDetected = (new CrawlerDetect)->isCrawler();
			$this->getOptions();		
			if (is_admin()) {
				// Back end
				add_action("admin_notices", array(&$this, "is_root_server_missing"));
				add_action("admin_enqueue_scripts", array(&$this, "enqueue_backend_files"),500);
				add_action("admin_menu", array(&$this, "admin_menu_link"));
			} else {
				// Front end				
				add_action("wp_enqueue_scripts", array(&$this, "enqueue_frontend_files"));			
				add_shortcode('bmlt_tabs', array(
					&$this,
					"tabbed_ui"
				));
				add_shortcode('bmlt_count', array(
					&$this,
					"meeting_count"
				));
				add_shortcode('meeting_count', array(
					&$this,
					"meeting_count"
				));
				add_shortcode('group_count', array(
					&$this,
					"bmlt_group_count"
				));
			}
			// Content filter
			add_filter('the_content', array(
				&$this,
				'filter_content'
			), 0);
		}
		function has_shortcode() {
			$post_to_check = get_post(get_the_ID());
			if ($post_to_check==null) return false;
			// check the post content for the short code
			if (stripos($post_to_check->post_content, '[bmlt_tabs') !== false) {
				return true;
			}
			if (stripos($post_to_check->post_content, '[bmlt_count') !== false) {
				return true;
			}
			if (stripos($post_to_check->post_content, '[meeting_count') !== false) {
				return true;
			}
			if (stripos($post_to_check->post_content, '[group_count') !== false) {
				return true;
			}
			return false;
		}
		function is_root_server_missing() {
			$root_server = $this->options['root_server'];
			if ($root_server == '') {
				echo '<div id="message" class="error"><p>Missing BMLT Root Server in settings for BMLT Tabs.</p>';
				$url = admin_url('options-general.php?page=bmlt-tabbed-ui.php');
				echo "<p><a href='$url'>BMLT_Tabs Settings</a></p>";
				echo '</div>';
			}
			add_action("admin_notices", array(
				&$this,
				"clear_admin_message"
			));
		}
		function clear_admin_message() {
			remove_action("admin_notices", array(
				&$this,
				"is_root_server_missing"
			));
		}
		function clear_admin_message2() {
			echo '<div id="message" class="error"><p>what</p></div>';
		}
		function BMLTTabs() {
			$this->__construct();
		}
		function filter_content($content) {
			return $content;
		}
        /**
         * @param $hook
         */
		function enqueue_backend_files($hook) {
			if( $hook == 'settings_page_bmlt-tabbed-ui' ) {
				wp_enqueue_style('bmlt-tabs-admin-ui-css','https://ajax.googleapis.com/ajax/libs/jqueryui/1.11.4/themes/smoothness/jquery-ui.css',false,'1.11.4', false);
				wp_register_script('bmlt-tabs-admin', plugins_url('js/bmlt_tabs_admin.js', __FILE__), array('jquery'),'6.0', false);
				wp_enqueue_script('bmlt-tabs-admin');
				wp_enqueue_script('common');
				wp_enqueue_script('jquery-ui-accordion');
			}
		}
		/**
		 * @desc Adds JS/CSS to the header
		 */
		function enqueue_frontend_files() {
			if ( $this->has_shortcode() ) {
				wp_enqueue_style("bmlt-tabs-select2", plugin_dir_url(__FILE__) . "css/select2.min.css", false, filemtime( plugin_dir_path(__FILE__) . "css/select2.min.css"), false);
				wp_enqueue_style("bmlt-tabs-bootstrap", plugin_dir_url(__FILE__) . "css/bootstrap.min.css", false, filemtime( plugin_dir_path(__FILE__) . "css/bootstrap.min.css"), false);
				wp_enqueue_style("bmlt-tabs", plugin_dir_url(__FILE__) . "css/bmlt_tabs.css", false, filemtime( plugin_dir_path(__FILE__) . "css/bmlt_tabs.css"), false);
				wp_enqueue_script("bmlt-tabs-bootstrap", plugin_dir_url(__FILE__) . "js/bootstrap.min.js", array('jquery'), filemtime( plugin_dir_path(__FILE__) . "js/bootstrap.min.js"), true);
				wp_enqueue_script("bmlt-tabs-select2", plugin_dir_url(__FILE__) . "js/select2.full.min.js", array('jquery'), filemtime( plugin_dir_path(__FILE__) . "js/select2.full.min.js"), true);
				wp_enqueue_script("bmlt-tabs", plugin_dir_url(__FILE__) . "js/bmlt_tabs.js", array('jquery'), filemtime( plugin_dir_path(__FILE__) . "js/bmlt_tabs.js"), true);
			}
		}
		function sortBySubkey(&$array, $subkey, $sortType = SORT_ASC) {
			foreach ($array as $subarray) {
				$keys[] = $subarray[$subkey];
			}
			array_multisort($keys, $sortType, $array);
		}
		function getAllMeetings($root_server, $services, $format_id, $query_string) {
		    if (isset($_POST["meetings"]) && !empty($_POST["meetings"])) {
		        return json_decode(htmlspecialchars_decode($_POST["meetings"]),true);
		    }
			if (isset($query_string) && $query_string != '' ) {
				$query_string = "&$query_string";
				$query_string = str_replace("()","[]",$query_string);
			} else {
			    $query_string = '';
			}
			if (isset($format_id) && $format_id != '' ) {
				$ids = explode(',',$format_id);
				$format_id = '';
				foreach($ids as $id) {
					$format_id .= "&formats[]=$id";
				}
			} else {
			    $format_id = '';
			}
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, "$root_server/client_interface/json/?switcher=GetSearchResults$format_id$services$query_string&sort_key=time");
			//curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible; MSIE 5.01; Windows NT 5.0)");
			curl_setopt($ch, CURLOPT_USERAGENT, "cURL Mozilla/5.0 (Windows NT 5.1; rv:21.0) Gecko/20130401 Firefox/21.0");
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_TIMEOUT, 10);
			curl_setopt($ch, CURLOPT_MAXREDIRS, 3 );
			curl_setopt($ch, CURLOPT_ENCODING, 'gzip,deflate' );
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
			
			$results  = curl_exec($ch);
			// echo curl_error($ch);
			$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			$c_error  = curl_error($ch);
			$c_errno  = curl_errno($ch);
			curl_close($ch);
			if ($httpcode != 200 && $httpcode != 302 && $httpcode != 304) {
				echo "<p style='color: #FF0000;'>Problem Connecting to BMLT Root Server: $root_server ( $httpcode )</p>";
				return 0;
			}
			$result = json_decode($results, true);
			If (count($result) == 0 || $result == null) {
				echo "<p style='color: #FF0000;'>No Meetings were Found: $root_server/client_interface/json/?switcher=GetSearchResults$format_id$services&sort_key=time</p>";
				return 0;
			}
			return $result;
		}
		function testRootServer($root_server) {
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, "$root_server/client_interface/serverInfo.xml");
			curl_setopt($ch, CURLOPT_USERAGENT, "cURL Mozilla/5.0 (Windows NT 5.1; rv:21.0) Gecko/20130401 Firefox/21.0");
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_TIMEOUT, 10);
			curl_setopt($ch, CURLOPT_MAXREDIRS, 3 );
			curl_setopt($ch, CURLOPT_ENCODING, 'gzip,deflate' );
			$results  = curl_exec($ch);
			$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			$c_error  = curl_error($ch);
			$c_errno  = curl_errno($ch);
			curl_close($ch);
			if ($httpcode != 200 && $httpcode != 302 && $httpcode != 304) {
			    
				return false;
			}
			return $results;
		}
		function tabbed_ui($atts, $content = null) {
			ini_set('memory_limit', '-1');
			global $unique_areas;
			extract(shortcode_atts(array(
				"root_server" => '',
				"service_body" => '',
				"service_body_parent" => '',
				"has_tabs" => '1',
				"has_groups" => '1',
				"has_cities" => '1',
				"has_meetings" => '1',
				"has_formats" => '1',
				"has_locations" => '1',
				"include_city_button" => '1',
				"include_weekday_button" => '1',
				"view_by" => 'weekday',
				"dropdown_width" => 'auto',
				"has_zip_codes" => '1',
				"header" => '1',
				"format_key" => '',
                "query_string" => '',
				"time_format" => '',
				"lang_enum" => 'de',
				'online_only' => 0,
				"exclude_zip_codes" => Null,
				"cache_time" => -1,
			    "field_name" => $this->options['field_name'],
			    "column3_contents" => $this->options['column3_contents']
			), $atts));
			
			$root_server            = ($root_server != '' ? $root_server : $this->options['root_server']);
			$root_server            = (!isSet($_GET['root_server']) ? $root_server : $_GET['root_server']);
			$service_body			= (!isSet($_GET['service_body']) ? $service_body : $_GET['service_body']);
			$phone 					= '';
			if (isset($this->options['phone']))
				$phone				= $this->options['phone'];
			$service_body_parent	= (!isSet($_GET['service_body_parent']) ? $service_body_parent : $_GET['service_body_parent']);
			$has_tabs               = ($has_meetings == '0' ? '0' : $has_tabs);
			// $has_tabs = ($include_weekday_button == '0' ? '1' : $has_tabs);
			$include_city_button    = ($view_by == 'city' ? '1' : $include_city_button);
			$include_weekday_button = ($view_by == 'weekday' ? '1' : $include_weekday_button);
			$include_city_button    = ($has_meetings == '0' ? '0' : $include_city_button);
			$include_weekday_button = ($has_meetings == '0' ? '0' : $include_weekday_button);
			//$format_key          	= ($format_key != '' ? strtoupper($format_key) : '');

			$time_format          	= ($time_format == '' ? 'g:i a' : $time_format);

			if ($root_server == '') {
				Return '<p><strong>BMLT Tabs Error: Root Server missing.<br/><br/>Please go to Settings -> BMLT_Tabs and verify Root Server</strong></p>';
			}

			// $has_tabs = ($view_by == 'city' ? '0' : $has_tabs);
			if ($view_by != 'city' && $view_by != 'weekday' && $view_by!='week' && $view_by!='lang' && $view_by != 'special_interest') {
				Return '<p>BMLT Tabs Error: view_by must = "city" or "weekday".</p>';
			}
			if ($include_city_button != '0' && $include_city_button != '1') {
				Return '<p>BMLT Tabs Error: include_city_button must = "0" or "1".</p>';
			}
			if ($include_weekday_button != '0' && $include_weekday_button != '1') {
				Return '<p>BMLT Tabs Error: include_weekday_button must = "0" or "1".</p>';
			}
			include(dirname(__FILE__)."/lang/translate_".$lang_enum.".php");
			if ($service_body_parent == Null && $service_body == Null) {
				$area_data       = explode(',', $this->options['service_body_1']);
				$area            = $area_data[0];
				$service_body_id = $area_data[1];
				$parent_body_id  = $area_data[2];
				if ($parent_body_id == '0') {
					$service_body_parent = $service_body_id;
				} else {
					$service_body = $service_body_id;
				}
			}
			$services = '';
			if ($service_body_parent != Null && $service_body != Null) {
				Return '<p>BMLT Tabs Error: Cannot use service_body_parent and service_body at the same time.</p>';
			}
			if ($service_body == '' && $service_body_parent == '') {
				Return '<p>BMLT Tabs Error: Service body missing from shortcode.</p>';
			}
			if ($service_body != Null) {
				$service_body = array_map('trim', explode(",", $service_body));
				foreach ($service_body as $key) {
					$services .= '&services[]=' . $key;
				}
			}
			if ($service_body_parent != Null) {
				$service_body = array_map('trim', explode(",", $service_body_parent));
				foreach ($service_body as $key) {
					$services .= '&recursive=1&services[]=' . $key;
				}
			}
			$cache_time = intval($cache_time);
			if ($cache_time < 0) {
				$cache_time = intval($this->options['cache_time']);
			}
			$transient_key = $this->transient_key();
			if ($cache_time > 0 && !isset($_GET['nocache'])) {
				$output = get_transient($transient_key);
				if ($output != '') {
					return $output;
				}
			}
			ob_flush();
			flush();
			$formats = MeetingHelper::getTheFormats($root_server,$lang_enum);
			$formats_online = array();
			foreach ($formats as $k=>$f) {
				if ($f['online']) {
					$formats_online[$k] = $f;
				}
			}
			$format_id = '';
			if ( $format_key != '' ) {
				$ids = array();
				$keys = explode(',',$format_key);
				foreach ($keys as $key) {
					$neg = false;
					if (substr($key,0,1)=='-') {
						$neg = true;
						$key = substr($key,1);
					}
					$ids[] = ($neg?'-':'').$formats[$key]['id'];
				}
				$format_id = implode(',',$ids);
			}
			$the_meetings = $this->getAllMeetings($root_server, $services, $format_id, $query_string);
			if ($the_meetings == 0) {
				return "";
			}
			if ($online_only != 0) {
				$temp = array();
				foreach ($the_meetings as $meeting) {
					$link = MeetingHelper::getLink($meeting);
					if ($link) {
						if ($online_only > 0) 
							$temp[] = $meeting;
						elseif (MeetingHelper::isHybrid($meeting))
							$temp[] = $meeting;
					} elseif (($online_only < 0)) {
						$temp[] = $meeting;	
					}
				}
				$the_meetings = $temp;
			}
			if ($format_key == 'BTW') {
				$unique_areas = $this->get_areas($root_server, 'BTW');
			}
			$unique_zip = $unique_city = $unique_group = 
			       $unique_location = $unique_format = $unique_weekday = $unique_format_name_string = array();
			$unique_week = array();
			foreach ($the_meetings as $value) {
				if ($exclude_zip_codes !== Null && $value['location_postal_code_1']) {
					if ( strpos($exclude_zip_codes, $value['location_postal_code_1']) !== false ) {
						continue;
					}
				}
				$tvalue = explode(',', $value['formats']);
				foreach ($tvalue as $t_value) {
					if (!isset($formats_online[$t_value])) continue;
					$unique_format[] = $t_value;
					$unique_format_name_string[] = $formats_online[$t_value]['name_string'];

				}
				if ($value['location_municipality']) {
					$unique_city[] = $value['location_municipality'];
				}
				if ($value['meeting_name']) {
					$unique_group[] = $value['meeting_name'];
				}
				if ($value['location_text']) {
					$unique_location[] = $value['location_text'];
				}
				if ($value['location_postal_code_1']) {
					$unique_zip[] = $value['location_postal_code_1'];
				}
			}
			if (count($unique_group) == 0) {
				return 'No Meetings Found';
			}
			$unique_zip                = array_unique($unique_zip);
			$unique_city               = array_unique($unique_city);
			$unique_group              = array_unique($unique_group);
			$unique_location           = array_unique($unique_location);
			$unique_format             = array_unique($unique_format);
			$unique_format_name_string = array_unique($unique_format_name_string);
			asort($unique_zip);
			asort($unique_city);
			asort($unique_group);
			asort($unique_location);
			asort($unique_format);
			asort($unique_format_name_string);
			$unique_langs 			   = $this->getLangFormats($unique_format, $formats);
			$unique_special		   = $this->getSpecialFormats($unique_format, $formats);
			array_push($unique_weekday, "1", "2", "3", "4", "5", "6", "7");
			array_push($unique_week, "1", "2", "3", "4", "L");
			$meetings_cities = $meetings_days = $meeting_header = $meetings_tab = $meetings_langs = "";
			for ($x = 0; $x <= 3; $x++) {
				if ($x == 0) {
				    if ($header==0 && $view_by!='city') {
				        continue;
				    }
					$unique_values = $unique_city;
				} elseif ($x == 1) {
				    if ($header==0 && $view_by!='weekday') {
				        continue;
				    }
					$unique_values = $unique_weekday;
				} elseif ($x == 2) {
				    if ($view_by!='week') {
				        continue;
				    }
					$unique_values = $unique_week;
				} else {
				    if ($view_by=='lang') {
						$unique_values = $unique_langs;
					} else if ($view_by=='special_interest') {
						$unique_values = $unique_special;
					} else continue;
				}
				foreach ($unique_values as $this_value) {
					$this_meeting = $meeting_header = $meeting_tab_header = "";							
					foreach ($the_meetings as $value) {
						if ($exclude_zip_codes !== Null && $value['location_postal_code_1']) {
							if ( strpos($exclude_zip_codes, $value['location_postal_code_1']) !== false ) {
								continue;
							}
						}
						if ($x == 0) {
							if (!isset($value['location_municipality']) || $this_value != $value['location_municipality']) {
								continue;
							}
						} elseif ($x == 1) {
							if ($this_value != $value['weekday_tinyint']) {
								continue;
							}
						} elseif ($x == 2) {
						    $tvalue = explode(',', $value['formats']);
						    $weekOpenFmt = 'O'.$this_value;
						    if (!in_array($weekOpenFmt, $tvalue) && !in_array('O', $tvalue)) {
						        continue;
							}
						} elseif ($x == 3) {
						    $tvalue = explode(',', $value['formats']);
						    if (!in_array($this_value, $tvalue)) {
						        continue;
						    }
						}
						$this_meeting .= '<tr>';
						$this_meeting .= $this->calcColumn1($value,$formats,$translate,$time_format,$x!=1,$lang_enum);
						$this_meeting .= $this->getLocation($value, $format_key, $formats, $translate, $lang_enum);
						$this_meeting .= $this->calcColumn3($value,$translate,$column3_contents,$field_name,$phone);
						$this_meeting .= "</tr>";
					}
					if ( $this_meeting != "" ) {
						$meeting_header = '<div id="bmlt-table-div">';
						$meeting_header .= "<table class='bmlt-table table table-striped table-hover table-bordered tablesaw tablesaw-stack'>";
						if ($x == 0) {
							if ($this_value) {
								$meeting_header .= "<tr class='meeting-header'><td colspan='3'>" . $this_value . "</td></tr>";
							} else {
								$meeting_header .= "<tr class='meeting-header'><td colspan='3'>NO CITY IN BMLT</td></tr>";
							}
						} elseif ($x == 3) {
							$meeting_header .= "<tr class='meeting-header'><td colspan='3'>" . $formats[$this_value]['description_string'] . "</td></tr>";
						} else {
							$meeting_tab_header = "<div id='tab" . $this_value . "' class='tab-pane'>";
							$meeting_tab_header .= "<table class='table table-striped table-hover table-bordered tablesaw tablesaw-stack'>";
							if ($x==1) {
							    $meeting_header .= "<tr class='meeting-header'><td colspan='3' ".$translate['style:align'].">" . MeetingHelper::getDay($this_value,$translate) . "</td></tr>";
							} else {
							    $meeting_header .= "<tr class='meeting-header'><td colspan='3' ".$translate['style:align'].">" . MeetingHelper::getWeek($this_value,$translate) . "</td></tr>";
							}
						}
						$this_meeting .= '</table>';
						$this_meeting .= '</div>';
						if ($x == 0) {
							$meetings_cities .= $meeting_header;
							$meetings_cities .= $this_meeting;
						} elseif ($x == 3) {
							$meetings_langs .= $meeting_header;
							$meetings_langs .= $this_meeting;
						} else {
							$meetings_days .= $meeting_header;
							$meetings_days .= $this_meeting;
							$meetings_tab .= $meeting_tab_header;
							$meetings_tab .= $this_meeting;
						}
					}
					if ( $x != 0 && $x != 3 && $meeting_tab_header == '' ) {
						$meetings_tab .= "<div id='tab" . $this_value . "' class='tab-pane'>";
						$meetings_tab .= "</div>";
					}
				}
			}
			$this_meeting = "";
			$output = '';
			/*
			$output.= '<script type="text/javascript">';
			$output.= 'jQuery( "body" ).addClass( "bmlt-tabs");';
			$output.= '</script>';
			*/
			if ($header == '1') {
				$output .= '<div class="hide bmlt-header" '.$translate['style:align'].'>';
				if ($view_by == 'weekday') {
					if ($include_weekday_button == '1') {
						$output .= '<div class="bmlt-button-container"><a id="day" class="btn btn-primary btn-sm">'.$translate['Weekday'].'</a></div>';
					}
					if ($include_city_button == '1') {
						$output .= '<div class="bmlt-button-container"><a id="city" class="btn btn-primary btn-sm">'.$translate['City'].'</a></div>';
					}
				} else {
					if ($include_weekday_button == '1') {
						$output .= '<div class="bmlt-button-container"><a id="day" class="btn btn-primary btn-sm">'.$translate['Weekday'].'</a></div>';
					}
					if ($include_city_button == '1') {
						$output .= '<div class="bmlt-button-container"><a id="city" class="btn btn-primary btn-sm">'.$translate['City'].'</a></div>';
					}
				}
				if ($has_cities == '1') {
					$output .= '<div class="bmlt-dropdown-container">';
					$output .= '<select style="height: 26px; width:' . $dropdown_width . ';" data-placeholder="'.$translate['City'].'" id="e2">';
					$output .= '<option></option>';
					foreach ($unique_city as $city_value) {
						$output .= "<option value=a-" . strtolower(preg_replace("/\W|_/", '-', $city_value)) . ">".$city_value."</option>";
					}				
					$output .= '</select>';
					$output .= '</div>';
				}
				if ($has_groups == '1') {
					$output .= '<div class="bmlt-dropdown-container">';
					$output .= '<select style="width:' . $dropdown_width . ';" data-placeholder="Groups" id="e3">';
					$output .= '<option></option>';
					foreach ($unique_group as $group_value) {
						$output .= "<option value=a-" . strtolower(preg_replace("/\W|_/", '-', $group_value)) . ">$group_value</option>";
					}
					$output .= '</select>';
					$output .= '</div>';
				}
				if ($has_locations == '1') {
					$output .= '<div class="bmlt-dropdown-container">';
					$output .= '<select style="width:' . $dropdown_width . ';" data-placeholder="Locations" id="e4">';
					$output .= '<option></option>';
					foreach ($unique_location as $location_value) {
						$output .= "<option value=a-" . strtolower(preg_replace("/\W|_/", '-', $location_value)) . ">$location_value</option>";
					}
					$output .= '</select>';
					$output .= '</div>';
				}
				if ($has_zip_codes == '1') {
					$output .= '<div class="bmlt-dropdown-container">';
					$output .= '<select style="width:' . $dropdown_width . ';" data-placeholder="Zips" id="e5">';
					$output .= '<option></option>';
					foreach ($unique_zip as $zip_value) {
						$output .= "<option value=a-" . strtolower(preg_replace("/\W|_/", '-', $zip_value)) . ">$zip_value</option>";
					}		
					$output .= '</select>';
					$output .= '</div>';
				}
				if ($has_formats == '1') {
					$output .= '<div class="bmlt-dropdown-container">';
					$output .= '<select style="width:' . $dropdown_width . ';" data-placeholder="'.$translate['Format'].'" id="e6">';
					$output .= '<option></option>';
					foreach ($unique_format_name_string as $format_value) {
						$output .= "<option value=a-" . strtolower(preg_replace("/\W|_/", '-', $format_value)) . ">$format_value</option>";
					}
					$output .= '</select>';
					$output .= '</div>';
				}
				$output .= '</div>';
			}
			if ($has_tabs == '1' && $has_meetings == '1' && ($header==1 || $view_by=='weekday')) {
				if ($view_by == 'weekday') {
					$output .= '<div class="bmlt-page show" id="nav-days">';
				} else {
					$output .= '<div class="bmlt-page hide" id="nav-days">';
				}
				$selected = getdate()['wday'] + 1;
				$output .= '<ul class="nav nav-tabs">';
				for ($i=1; $i<=7; $i++) {
					$output .= '<li '.$translate['float-dir'].'><a href="#tab'.$i.'" data-toggle="tab">'.$translate['Weekdays'][$i].'</a></li>';
				}
				$output .= '</ul>
                <script type="text/javascript">var g_selected="'.$selected.'";</script>
				</div>
				<div class="bmlt-page" id="tabs-content">
				<div class="tab-content">
				' . $meetings_tab . '
				</div>
				</div>';
			}
			elseif ($has_tabs == '1' && $has_meetings == '1' && $view_by=='week') {
			    if ($view_by == 'week') {
			        $output .= '<div class="bmlt-page show" id="nav-days">';
			    }
			    $mday = getdate()['mday'];
			    $selected = 'L';
			    if ($mday<8) {
			        $selected = '1';
			    } elseif ($mday<15) {
			        $selected = '2';
			    } elseif ($mday<22) {
			        $selected = '3';
			    } elseif ($mday<29) {
			        $selected = '4';
			    }
			    $output .= '
				<ul class="nav nav-tabs">
					<li><a href="#tab1" data-toggle="tab">'.MeetingHelper::getWeek('1',$translate).'</a></li>
                    <li><a href="#tab2" data-toggle="tab">'.MeetingHelper::getWeek('2',$translate).'</a></li>
                    <li><a href="#tab3" data-toggle="tab">'.MeetingHelper::getWeek('3',$translate).'</a></li>
                    <li><a href="#tab4" data-toggle="tab">'.MeetingHelper::getWeek('4',$translate).'</a></li>
                    <li><a href="#tabL" data-toggle="tab">'.MeetingHelper::getWeek('L',$translate).'</a></li>
				</ul>
                <script type="text/javascript">var g_selected="'.$selected.'";</script>
				</div>
				<div class="bmlt-page" id="tabs-content">
				<div class="tab-content">
				' . $meetings_tab . '
				</div>
				</div>';
			}
			elseif ($has_tabs == '0' && $has_meetings == '1' && ($header==1 || $view_by=='weekday')) {
				// if ( $include_weekday_button == '1' ) {
				if ($view_by == 'weekday') {
					$output .= '<div class="bmlt-page show" id="days">';
				} else {
					$output .= '<div class="bmlt-page hide" id="days">';
				}
				$output .= $meetings_days;
				$output .= '</div>';
				$output .= '<script type="text/javascript">var g_selected="0";</script>;';
				// }
			}
			if ($has_meetings == '1' && ($header==1 || $view_by=='city')) {
				// if ( $include_city_button == '1' ) {
				if ($view_by == 'city') {
					$output .= '<div class="bmlt-page show" id="cities">';
				} else {
					$output .= '<div class="bmlt-page hide" id="cities">';
				}
				$output .= $meetings_cities;
				$output .= '</div>';
				$meetings_cities = '';
				// }
			}
			if ($view_by=='lang' || $view_by=='special_interest') {
				
				$output .= '<div class="bmlt-page show" id="cities">';
				
				$output .= $meetings_langs;
				$output .= '</div>';
				$meetings_langs = '';
				// }
			}
			if ($has_cities == '1' && $header=='1') {
				$output .= $this->get_the_meetings($the_meetings, $unique_city, "location_municipality", $formats, $format_key, "City", $translate, $time_format, $column3_contents, $field_name, $lang_enum, $phone);
			}
			if ($has_groups == '1' && $header=='1') {
			    $output .= $this->get_the_meetings($the_meetings, $unique_group, "meeting_name", $formats, $format_key, "Group", $translate, $time_format, $column3_contents, $field_name, $lang_enum, $phone);
			}
			if ($has_locations == '1' && $header=='1') {
			    $output .= $this->get_the_meetings($the_meetings, $unique_location, "location_text", $formats, $format_key, "Location", $translate, $time_format, $column3_contents, $field_name, $lang_enum, $phone);
			}
			if ($has_zip_codes == '1' && $header=='1') {
			    $output .= $this->get_the_meetings($the_meetings, $unique_zip, "location_postal_code_1", $formats, $format_key, "Zip Code", $translate, $time_format, $column3_contents, $field_name, $lang_enum, $phone);
			}
			if ($has_formats == '1' && $header=='1') {
			    $output .= $this->get_the_meetings($the_meetings, $unique_format_name_string, "name_string", $formats, $format_key, "Format", $translate, $time_format, $column3_contents, $field_name, $lang_enum, $phone);
			}
			$this_title = $sub_title = $meeting_count = $group_count= '';
			if ( isSet($_GET['this_title'])) {
				$this_title = '<div class="bmlt_tabs_title">' . $_GET['this_title'] . '</div>';
			}
			if ( isSet($_GET['sub_title']) ) {
				$sub_title = '<div class="bmlt_tabs_sub_title">' . $_GET['sub_title'] . '</div>';
			}
			if ( isSet($_GET['meeting_count']) ) {
				$meeting_count = '<span class="bmlt_tabs_meeting_count">Meeting Weekly: ' . $this->meeting_count('', Null) . '</span>';
			}
			if ( isSet($_GET['group_count']) ) {
				$group_count = '<span class="bmlt_tabs_group_count">Groups: ' . $this->bmlt_group_count('', Null) . '</span>';
			}
			$output = $this_title . $sub_title . $meeting_count. $group_count . $output;
			$output = '<div class="bootstrap-bmlt"><div id="bmlt-tabs" class="bmlt-tabs hide">' . $output . '</div></div>';
			//$output .= '<div id="divId" class="bmlt-tabs" title="Dialog Title"></div>';
			if ($cache_time > 0 && !isset($_GET['nocache'])) {
				set_transient($this->transient_key(), $output, intval($this->options['cache_time']) * HOUR_IN_SECONDS);
			}
			return $output;
		}
		function transient_key() {
			return 'bmlt_tabs_' . md5($_SERVER['HTTP_HOST'].'//'.$_SERVER['REQUEST_URI']);
		}
		function printCityAndSubsection($value) {
		    $this_meeting = '<b>'.$value['location_municipality'];
		    if (isSet($value['location_city_subsection']) and $value['location_city_subsection'] != '') {
		        $this_meeting .= ' ('.$value['location_city_subsection'].')';
		    }
		    $this_meeting .= '</b><br>';
		    return $this_meeting;
		}
		function calcColumn3($value,$translate,$contents,$fieldName,$phone) {
		    $this_meeting = "<td class='bmlt-column3' ".$translate['style:align'].">";
			if ($this->startsWith($contents,"neighborhoodsIn")) {
                $city = explode(":",$contents)[1];
                if (isSet($value['location_municipality']) and $value['location_municipality'] != '' and $value['location_municipality'] != $city) {
                    $this_meeting .= $this->printCityAndSubsection($value);
                } else {
                    $this_meeting .= '<b>'.$value['location_city_subsection'].'</b><br>';
				}
			} elseif ($contents !== "blank") {
		        $this_meeting .= $this->printCityAndSubsection($value);
			}
			if (!isSet($value['VG']) || !$value['VG'] ) {
		    if ($fieldName!=null && $fieldName!='' && !$value['is_virtual']) {
		       $public_trans_long = $value[$fieldName];
		
	           if (isSet($public_trans_long) and $public_trans_long != '' ) {
		           $public_trans = explode("#@-@#", $public_trans_long);
		           if (count($public_trans) > 1 and $public_trans[1] != '')
		               $public_trans_long = $public_trans[1];
		           $this_meeting .= htmlspecialchars($public_trans_long)."<br>";
	           }
	        }
			if ($value['is_virtual']) {
				$this_meeting .= MeetingHelper::virtualMtg($value,$translate,$phone,$this->crawlerDetected,' btn-xs');
			} else {
				$this_meeting .= MeetingHelper::getMap($value).'</br>';
				if (isset($value['HY']) && $value['HY']) {
					$this_meeting .= MeetingHelper::virtualMtg($value,$translate,$phone,$this->crawlerDetected,' btn-xs');
				}
				
				$reservation_link = MeetingHelper::getReservationList($value);
				if ($reservation_link)
					$this_meeting .= "<br/><a target='_blank' id='bmlt-formats' class='btn btn-primary btn-xs' href=\"".$reservation_link.'" style=\'white-space:normal;word-break:break-word\' >'.$translate['reservation'].'</a>';

			}
				                }	else {
									$this_meeting = $this_meeting;
								}	                ;
		    return $this_meeting."</td>";
		}
		function get_the_meetings($result_data, $unique_data, $unique_value, $formats, $format_key, $where, $translate, $time_format, $column3_contents, $field_name, $lang_enum, $phone) {
			global $unique_areas;
			$this_output = '';
			foreach ($unique_data as $this_value) {
				$this_output .= "<div class='hide bmlt-page' id='a-" . strtolower(preg_replace("/\W|_/", '-', $this_value)) . "'>";
				$day_init = [0,0,0,0,0,0,0,0];
				$day_data = ['','','','','','','',''];
				foreach ($result_data as $value) {
					if ($unique_value == 'name_string') {
						$good = False;
						foreach ($formats as $value1) {
							$key_string  = $value1['key_string'];
							$name_string = $value1['name_string'];
							if ($name_string == $this_value) {
								$tvalue = explode(',', $value['formats']);
								foreach ($tvalue as $t_value) {
									if ($t_value == $key_string) {
										$good = True;
									}
								}
							}
						}
						if ($good == False) {
							continue;
						}
						if ($format_key != '' && substr($format_key,0,1) != '-'
						 && !in_array($format_key, $tvalue)) {
							continue;
						}
					}
					elseif (!isset($value[$unique_value])) {
						continue;
					} elseif ($this_value != $value[$unique_value]) {
						continue;
					}
					$this_meeting = '';
					$this_meeting .= '<tr>';
					$this_meeting .= $this->calcColumn1($value,$formats,$translate,$time_format,false,$lang_enum);
					$this_meeting .= $this->getLocation($value, $format_key, $formats,$translate, $lang_enum);
					$this_meeting .= $this->calcColumn3($value,$translate,$column3_contents,$field_name,$phone);
					$this_meeting .= "</tr>";
					
					$i = $value['weekday_tinyint'];
					if ($day_init[$i]==0) {
					    $day_data[$i] = "<table class='bmlt-table table table-striped table-hover table-bordered tablesaw tablesaw-stack'>";
					    $day_data[$i] .= "<tr class='meeting-header'><td colspan='3' ".$translate['style:align'].">".$translate['Weekdays'][$i]."</td></tr>";
					    $day_init[$i] = 1;
					}
					$day_data[$i] .= $this_meeting;
				}
				for ($i=1; $i<=7; $i++) {
				    if ($day_init[$i] == 1) {
					   $this_output .= "<div id='bmlt-table-dropdown-div'>$day_data[$i]</table></div>";
				    }
				}
				$this_output .= "</div>";
			}
			return $this_output;
		}

		function startsWith($haystack, $needle)
		{
		    $length = strlen($needle);
		    return (substr($haystack, 0, $length) === $needle);
		}
		function getLangFormats($used, $formats) {
			$ret = array();
			foreach ($used as $f) {
				if ($f=='de') continue;
				if (isSet($formats[$f])) {
		            $t_format = $formats[$f];
		            $type = $t_format['format_type_enum'];
		            if ($type=='LANG') {
						$ret[] = $f;
					}
				}
			}
			return $ret;
		}
		function getSpecialFormats($used, $formats) {
			$ret = array();
			foreach ($used as $f) {
				if (isSet($formats[$f])) {
		            $t_format = $formats[$f];
		            $type = $t_format['format_type_enum'];
		            if ($type=='FC3') {
						$ret[] = $f;
					}
				}
			}
			return $ret;
		}
		function getMeetingFormats(&$value, $formats, $translate) {
			extract(MeetingHelper::seperateFormats($value, $formats));
		    $weeks = array("1","2","3","4","5","L",'*');
		    $value['formats'] = '';
		    $meeting_formats = '<table class="bmlt_a_format table-bordered">';
		    if (count($fc2)+count($fc3)+count($o) > 0) {
		      $meeting_formats .= '<tr><td class="formats_header" colspan="2" '.$translate['style:align'].'>'.$translate['Info'].'</td></tr>';
		      $first = true;
		      foreach ($fc2 as $f) {
		          $meeting_formats .= '<tr><td class="formats_key">'.$f['key_string'].'</td>'
				    .'<td class="formats_description">'.htmlspecialchars($f['description_string'], ENT_QUOTES).'</td></tr>';
		          $value['formats'] .= ($first?'':',') . $f['key_string'];
		          $first = false;
		      }
		      foreach ($fc3 as $f) {
		          $meeting_formats .= '<tr><td class="formats_key">'.$f['key_string'].'</td>'
			        .'<td class="formats_description">'.htmlspecialchars($f['description_string'], ENT_QUOTES).'</td></tr>';
		          $value['formats'] .= ($first?'':',') . $f['key_string'];
		          $first = false;
		      }
		      $special_weeks = false;
		      foreach ($weeks as $week) {
		          if (isSet($o[$week])) {
		              $f = $o[$week];
		              $meeting_formats .= '<tr><td class="formats_key">'.$f['key_string'].'</td>'
			             .'<td class="formats_description">'.htmlspecialchars($f['description_string'], ENT_QUOTES).'</td></tr>';
			          $value['formats'] .= ($first?'':',') . $f['key_string'];
			          $first = false;
		          }
		      }
		      if (!$first) {
		          $value['formats'] .= '/';
		      }
		    }
		    $text = MeetingHelper::getField('format_comments',$value);
		    $first = true;
		    if (count($fc1) or $text!='') {
		      $meeting_formats .= '<tr><td class="formats_header" colspan="2" '.$translate['style:align'].'>'.$translate['Format'].'</td></tr>';
		      $special_weeks = false;
		      foreach ($weeks as $week) {
		        if (isSet($fc1[$week])) {
		                $f = $fc1[$week];
		                $descr = (($special_weeks and $week=='*')?htmlspecialchars($translate['week*']):'')
		                     .$f['description_string'];
		                $meeting_formats .= '<tr><td class="formats_key">'.$f['key_string'].'</td>'
				             .'<td class="formats_description">'.htmlspecialchars($descr, ENT_QUOTES).'</td></tr>';
		                $value['formats'] .= ($first?'':',') . $f['key_string'];
		                $first = false;
		                $special_weeks = true;
		        }
		      }
		      if ($text!='') {
		          $meeting_formats .= '<tr><td class="formats_key">*</td>'
			         .'<td class="formats_description">'.htmlspecialchars($text, ENT_QUOTES).'</td></tr>';
			      $value['formats'] .= ($first?'*':',*');
		      }
		    }
			$meeting_formats .= '</table>';
			if (count($covid)>0) {
				$first = true;
				$value['covid'] = '';
				$value['covid-table'] = '<table class="bmlt_a_format table-bordered">';
				$value['covid-table'] .= '<tr><td class="formats_header" colspan="2" '.$translate['style:align'].' style="white-space:normal;word-break:break-word">'.$translate['Covid-Responsibility'].'</td></tr>';
				foreach ($covid as $f) {
					$value['covid-table'] .= '<tr><td class="formats_key">'.$f['name_string'].'</td>'
				    .'<td class="formats_description">'.htmlspecialchars($f['description_string'], ENT_QUOTES).'</td></tr>';
		          $value['covid'] .= ($first ? '': ', ') . $f['name_string'];
				  $first = false;
				}
				$value['covid-table'] .= '</table>';
			}
		    return $meeting_formats;
		}
		function getDetailsLink($value,$lang_enum) {
			$id = $value['id_bigint'];
			$name = $value['meeting_name'];
			if ($value['is_virtual'] && !MeetingHelper::isHybrid($value) && !empty($this->options['virtual_details_page'])) {
				return '<a href="'.$this->addLang($this->options['virtual_details_page'],$lang_enum).'?meeting-id='.$id.'">'.$name.'</a>';
			}
			if (empty($this->options['details_page'])) return $name;
			if (isset($value['VG']) && $value['VG']) return $name;
			return '<a href="'.$this->addLang($this->options['details_page'],$lang_enum).'?meeting-id='.$id.'">'.$name.'</a>';
		}
		function addLang($url,$lang) {
			if (empty($lang) || $lang=='de') return str_replace('{lang}/','',$url);
			if (str_ends_with($url,'/')) $url = substr($url,0,strlen($url)-1);
			return str_replace('{lang}',$lang,$url.'-'.$lang);
		}
		function getLocation($value, $format_key, $formats, $translate, $lang_enum) {
			$location = $address = '';
			if (isset($value['meeting_name'])) {
				//$location .= "<div class='meeting-name'>" . $value['meeting_name'] . "</div>";
				$location .= "<div class='meeting-name'>" . $this->getDetailsLink($value,$lang_enum);
				if (isset($value['lang_enum']) && (empty($format_key) || $value['lang_enum']!=$format_key )) {
				    $lang_enum = $value['lang_enum'];
				    $lang_format = $formats[$lang_enum];
					$location .= ' <img src="' . plugin_dir_url(__FILE__) . "lang/".$lang_enum.'.png" '
				    . 'title="'.htmlspecialchars($lang_format['description_string']) . '" '
				    . 'alt="'.htmlspecialchars($lang_format['name_string']) . '">';
				}
				if (isset($value['lang_enum2']) ) {
				    $lang_enum = $value['lang_enum2'];
				    $lang_format = $formats[$lang_enum];
				    $location .= ' <img src="' . plugin_dir_url(__FILE__) . "lang/".$lang_enum.'.png" '
				    . 'title="'.htmlspecialchars($lang_format['description_string']) . '" '
				    . 'alt="'.htmlspecialchars($lang_format['name_string']) . '">';
				}
				$location .= "</div>";
			} else {
				$value['meeting_name'] = '';
			}
			$location .= MeetingHelper::calcLocation($value, $translate);
			return "<td class='bmlt-column2' ".$translate['style:align'].">".$location."</td>";
		}
		function calcColumn1(&$value,$formats,$translate,$time_format,$has_day,$lang_enum) {
		    $duration            = explode(':', $value['duration_time']);
		    $minutes             = intval($duration[0]) * 60 + intval((isset($duration[1]) ? $duration[1] : '0'));
		    $addtime             = '+ ' . $minutes . ' minutes';
		    $end_time            = date($time_format, strtotime($value['start_time'] . ' ' . $addtime));
		    $value['start_time'] = date($time_format, strtotime($value['start_time']));
		    $value['start_time'] = $value['start_time'] . "&nbsp;-&nbsp;" . $end_time;
		    $meeting_formats = $this->getMeetingFormats($value, $formats, $translate);
		    if ($lang_enum == 'fa') {
		        $value['start_time'] = MeetingHelper::toPersianNum($value['start_time']);
		    }
		    if (isset($value['comments'])) {
		        $comment_class = "bmlt-comments";
		        $value['comments'] = "<div class='".$comment_class."'>" . htmlspecialchars($value['comments']) . "</div>";
		    } else {
		        $value['comments'] = '';
			}

		    if (isset($value['alert'])) {
		        $comment_class = "bmlt-alert";
		        $value['alert'] = "<div class='".$comment_class."'>" . htmlspecialchars($value['alert']) . "</div>";
		    } else {
		        $value['alert'] = '';
			}
		    $today = '';
		    if ($has_day) {
		        $today = "<div class='bmlt-day'>" . MeetingHelper::getDay($value['weekday_tinyint'],$translate) . "</div>";
		        $class = 'bmlt-time';
		    } else {
		        $class = 'bmlt-time-2';
		    }
		    if ($value['formats']) {
		        $column1 = $today."<div class=".$class.">" . $value['start_time'] . "</div><a id='bmlt-formats' class='btn btn-primary btn-xs' title='' data-html='true' tabindex='0' data-trigger='focus' role='button' data-content='".$meeting_formats."' data-toggle='popover' data-placement='auto'><span class='glyphicon glyphicon-info-sign' aria-hidden='true'></span>"
							    . $value['formats'] . "</a>" . $value['comments'] .$value['alert'];
		    } else {
		        $column1 = $today."<div class=".$class.">" . $value['start_time'] . "</div>" . $value['comments'] . $value['alert'];
			}
			if (isset($value['covid']) && strlen($value['covid'])>0) {
				$column1 .= "<a id='bmlt-formats' class='btn btn-primary btn-xs' title='' data-html='true' tabindex='0' data-trigger='focus' role='button' data-content='".$value['covid-table']."' data-toggle='popover' data-placement='auto' style='white-space:normal;word-break:break-word'><span class='glyphicon glyphicon-info-sign' aria-hidden='true'></span>". $value['covid'] . "</a>";
			}
		    return "<td class='bmlt-column1' ".$translate['style:align'].">".$column1."</td>";
		}
		/**
		 * @desc BMLT Meeting Count
		 */
		function meeting_count($atts, $content = null) {
			extract(shortcode_atts(array(
				"service_body" => '',
				"root_server" => '',
				"subtract" => '',
				"exclude_zip_codes" => Null,
				"service_body_parent" => ''
			), $atts));
			$root_server = ($root_server != '' ? $root_server : $this->options['root_server']);
			$root_server = ($_GET['root_server'] == Null ? $root_server : $_GET['root_server']);
			$service_body = ($_GET['service_body'] == Null ? $service_body : $_GET['service_body']);
			$service_body_parent	= ($_GET['service_body_parent'] == Null ? $service_body_parent : $_GET['service_body_parent']);
			if ($service_body_parent == Null && $service_body == Null) {
				$area_data       = explode(',', $this->options['service_body_1']);
				$area            = $area_data[0];
				$service_body_id = $area_data[1];
				$parent_body_id  = $area_data[2];
				if ($parent_body_id == '0') {
					$service_body_parent = $service_body_id;
				} else {
					$service_body = $service_body_id;
				}
			}
			$services = '';
			$subtract = intval($subtract);
			if ($service_body_parent != Null && $service_body != Null) {
				Return '<p>BMLT Tabs Error: Cannot use service_body_parent and service_body at the same time.</p>';
			}
			$t_services = '';
			if ($service_body != Null && $service_body != 'btw') {
				$service_body = array_map('trim', explode(",", $service_body));
				foreach ($service_body as $key) {
					$services .= '&services[]=' . $key;
				}
			}
			elseif ($service_body_parent != Null && $service_body != 'btw') {
				$service_body = array_map('trim', explode(",", $service_body_parent));
				$services .= '&recursive=1';
				foreach ($service_body as $key) {
					$services .= '&services[]=' . $key;
				}
			}
			if ($service_body == 'btw') {
				$the_query = $root_server . "/client_interface/json/index.php?switcher=GetSearchResults&formats[]=46";
			} else {
				$the_query = $root_server . "/client_interface/json/index.php?switcher=GetSearchResults&formats[]=-47" . $services;
			}
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $the_query);
			curl_setopt($ch, CURLOPT_USERAGENT, "cURL Mozilla/5.0 (Windows NT 5.1; rv:21.0) Gecko/20130401 Firefox/21.0");
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_TIMEOUT, 10);
			curl_setopt($ch, CURLOPT_MAXREDIRS, 3 );
			curl_setopt($ch, CURLOPT_ENCODING, 'gzip,deflate' );
			$results  = curl_exec($ch);
			$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			curl_close($ch);
			if ($httpcode != 200 && $httpcode != 302 && $httpcode != 304) {
				return '[connect error]';
			}
			$result = json_decode($results, true);
			if ($exclude_zip_codes !== Null) {
				foreach ($result as $value) {
					if ($value['location_postal_code_1']) {
						if ( strpos($exclude_zip_codes, $value['location_postal_code_1']) !== false ) {
							continue;
						}
					}
					$unique_group[] = $value['id_bigint'];
				}
				$result = array_unique($unique_group);
			}
			$results = count($result) - $subtract;
			return $results;
		}
		/**
		 * @desc BMLT Group Count
		 */
		function bmlt_group_count($atts, $content = null) {
			extract(shortcode_atts(array(
				"service_body" => '',
				"subtract" => '',
				"root_server" => '',
				"exclude_zip_codes" => Null,
				"service_body_parent" => ''
			), $atts));
			if ($atts == "") {
				// return;
			}
			$root_server = ($root_server != '' ? $root_server : $this->options['root_server']);
			$root_server = ($_GET['root_server'] == Null ? $root_server : $_GET['root_server']);
			$service_body = ($_GET['service_body'] == Null ? $service_body : $_GET['service_body']);
			$service_body_parent	= ($_GET['service_body_parent'] == Null ? $service_body_parent : $_GET['service_body_parent']);
			if ($service_body_parent == Null && $service_body == Null) {
				$area_data       = explode(',', $this->options['service_body_1']);
				$area            = $area_data[0];
				$service_body_id = $area_data[1];
				$parent_body_id  = $area_data[2];
				if ($parent_body_id == '0') {
					$service_body_parent = $service_body_id;
				} else {
					$service_body = $service_body_id;
				}
			}
			$services = '';
			$subtract = intval($subtract);
			if ($service_body_parent != Null && $service_body != Null) {
				Return '<p>BMLT Tabs Error: Cannot use service_body_parent and service_body at the same time.</p>';
			}
			if ($service_body != Null && $service_body != 'btw') {
				$service_body = array_map('trim', explode(",", $service_body));
				foreach ($service_body as $key) {
					$services .= '&services[]=' . $key;
				}
			}
			if ($service_body_parent != Null && $service_body != 'btw') {
				$service_body = array_map('trim', explode(",", $service_body_parent));
				$services .= '&recursive=1';
				foreach ($service_body as $key) {
					$services .= '&services[]=' . $key;
				}
			}
			if ($exclude_zip_codes != Null) {
				$the_query = "$root_server/client_interface/json/index.php?switcher=GetSearchResults,location_postal_code_1&formats[]=-47" . $services;				
			} elseif ($service_body == 'btw') {
				$the_query = "$root_server/client_interface/json/index.php?switcher=GetSearchResults&formats[]=46" . $services;
			} else {
				$the_query = "$root_server/client_interface/json/index.php?switcher=GetSearchResults&formats[]=-47" . $services;
			}
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $the_query);
			curl_setopt($ch, CURLOPT_USERAGENT, "cURL Mozilla/5.0 (Windows NT 5.1; rv:21.0) Gecko/20130401 Firefox/21.0");
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_TIMEOUT, 10);
			curl_setopt($ch, CURLOPT_MAXREDIRS, 3 );
			curl_setopt($ch, CURLOPT_ENCODING, 'gzip,deflate' );
			$results  = curl_exec($ch);
			$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			curl_close($ch);
			if ($httpcode != 200 && $httpcode != 302 && $httpcode != 304) {
				return '[connect error]';
			}
			$result = json_decode($results, true);
			$unique_group = array();
			foreach ($result as $value) {
				if ($exclude_zip_codes !== Null && $value['location_postal_code_1']) {
					if ( strpos($exclude_zip_codes, $value['location_postal_code_1']) !== false ) {
						continue;
					}
				}
				$unique_group[] = $value['meeting_name'];
			}
			$result = array_unique($unique_group);
			return count($result);
		}
		/**
		 * @desc Adds the options sub-panel
		 */
		function get_areas($root_server, $source) {
			$resource = curl_init();
			curl_setopt($resource, CURLOPT_URL, "$root_server/client_interface/json/?switcher=GetServiceBodies");
			curl_setopt($resource, CURLOPT_USERAGENT, "cURL Mozilla/5.0 (Windows NT 5.1; rv:21.0) Gecko/20130401 Firefox/21.0");
			curl_setopt($resource, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($resource, CURLOPT_TIMEOUT, 10);
			curl_setopt($resource, CURLOPT_MAXREDIRS, 3 );
			curl_setopt($resource, CURLOPT_ENCODING, 'gzip,deflate' );
			$results  = curl_exec($resource);
			$result   = json_decode($results, true);
			$httpcode = curl_getinfo($resource, CURLINFO_HTTP_CODE);
			$c_error  = curl_error($resource);
			$c_errno  = curl_errno($resource);
			curl_close($resource);
			if ($results == False) {
				echo '<div style="font-size: 20px;text-align:center;font-weight:normal;color:#F00;margin:0 auto;margin-top: 30px;"><p>Problem Connecting to BMLT Root Server</p><p>' . $root_server . '</p><p>Error: ' . $c_errno . ', ' . $c_error . '</p><p>Please try again later</p></div>';
				return 0;
			}
			if ($source == 'dropdown') {
				$unique_areas = array();
				foreach ($result as $value) {
					$parent_name = 'None';
					foreach ($result as $parent) {
						if ( $value['parent_id'] == $parent['id'] ) {
							$parent_name = $parent['name'];
						}
					}
					$unique_areas[] = $value['name'] . ',' . $value['id'] . ',' . $value['parent_id'] . ',' . $parent_name;
				}
			} else {
				$unique_areas = array();
				foreach ($result as $value) {
					$unique_areas[$value['id']] = $value['name'];
				}
			}
			return $unique_areas;
		}
		function admin_menu_link() {
			// If you change this from add_options_page, MAKE SURE you change the filter_plugin_actions function (below) to
			// reflect the page file name (i.e. - options-general.php) of the page your plugin is under!
			add_options_page('BMLT Tabs', 'BMLT Tabs', 'activate_plugins', basename(__FILE__), array(
				&$this,
				'admin_options_page'
			));
			add_filter('plugin_action_links_' . plugin_basename(__FILE__), array(
				&$this,
				'filter_plugin_actions'
			), 10, 2);
		}
		/**
		 * Adds settings/options page
		 */
		function admin_options_page() {
			if (!isset($_POST['bmlttabssave'])) {
				$_POST['bmlttabssave'] = false;
			}
			if (!isset($_POST['delete_cache_action'])) {
				$_POST['delete_cache_action'] = false;
			}
			if ($_POST['bmlttabssave']) {
				if (!wp_verify_nonce($_POST['_wpnonce'], 'bmlttabsupdate-options'))
					die('Whoops! There was a problem with the data you posted. Please go back and try again.');
				$this->options['cache_time']     = $_POST['cache_time'];
				$this->options['root_server']    = $_POST['root_server'];
				$this->options['service_body_1'] = $_POST['service_body_1'];
				$this->options['field_name'] = $_POST['field_name'];
				$this->options['column3_contents'] = $_POST['column3_contents'];
				$this->options['phone']			= $_POST['phone'];
				$this->options['details_page']	= $_POST['details_page'];
				$this->options['virtual_details_page']	= $_POST['virtual_details_page'];
				$this->save_admin_options();
				set_transient('admin_notice', 'Please put down your weapon. You have 20 seconds to comply.');
				echo '<div class="updated"><p>Success! Your changes were successfully saved!</p></div>';
				if (intval($this->options['cache_time']) == 0) {
					$num = $this->delete_transient_cache();
					if ($num > 0) {
						echo "<div class='updated'><p>Success! BMLT Cache Deleted! ($num entries found and deleted)</p></div>";
					}
				} else {
					echo "<div class='updated'><p>Note: consider Deleting Cache (unless you know what you're doing)</p></div>";
				}
			}
			if ($_POST['delete_cache_action']) {
				if (!wp_verify_nonce($_POST['_wpnonce'], 'delete_cache_nonce'))
					die('Whoops! There was a problem with the data you posted. Please go back and try again.');
				$num = $this->delete_transient_cache();
				set_transient('admin_notice', 'Please put down your weapon. You have 20 seconds to comply.');
				if ($num > 0) {
					echo "<div class='updated'><p>Success! BMLT Cache Deleted! ($num entries found and deleted)</p></div>";
				} else {
					echo "<div class='updated'><p>Success! BMLT Cache - Nothing Deleted! ($num entries found)</p></div>";
				}
			}
?>
			<div class="wrap">
				<h2>BMLT Tabs</h2>
				<form style="display:inline!important;" method="POST" id="bmlt_tabs_options" name="bmlt_tabs_options">
					<?php wp_nonce_field('bmlttabsupdate-options'); ?>
					<?php $this_connected = $this->testRootServer($this->options['root_server']); ?>
					<?php $connect = "<p><div style='color: #f00;font-size: 16px;vertical-align: text-top;' class='dashicons dashicons-no'></div><span style='color: #f00;'>Connection to Root Server Failed.  Check spelling or try again.  If you are certain spelling is correct, Root Server could be down.</span></p>"; ?>
					<?php if ( $this_connected != False) { ?>
						<?php $connect = "<span style='color: #00AD00;'><div style='font-size: 16px;vertical-align: text-top;' class='dashicons dashicons-smiley'></div>Version ".$this_connected."</span>"?>
						<?php $this_connected = true; ?>
					<?php } ?>
					<div style="margin-top: 20px; padding: 0 15px;" class="postbox">
						<h3>BMLT Root Server URL</h3>
						<p>Example: http://naflorida.org/bmlt_server</p>
						<ul>
							<li>
								<label for="root_server">Default Root Server: </label>
								<input id="root_server" type="text" size="40" name="root_server" value="<?php echo $this->options['root_server']; ?>" /> <?php echo $connect; ?>
							</li>
						</ul>
					</div>
					<div style="padding: 0 15px;" class="postbox">
						<h3>Service Body</h3>
						<p>This service body will be used when no service body is defined in the shortcode.</p>
						<ul>
							<li>
								<label for="service_body_1">Default Service Body: </label>
								<select style="display:inline;" onchange="getValueSelected()" id="service_body_1" name="service_body_1">
								<?php if ($this_connected) { ?>
									<?php $unique_areas = $this->get_areas($this->options['root_server'], 'dropdown'); ?>
									<?php asort($unique_areas); ?>
									<?php foreach ($unique_areas as $key => $unique_area) { ?>
										<?php $area_data = explode(',', $unique_area); ?>
										<?php $area_name = $area_data[0]; ?>
										<?php $area_id = $area_data[1]; ?>
										<?php $area_parent = $area_data[2]; ?>
										<?php $area_parent_name = $area_data[3]; ?>
										<?php if ($unique_area == $this->options['service_body_1']) { ?>
											<option selected="selected" value="<?php echo $unique_area; ?>"><?php echo $area_name; ?></option>
										<?php } else { ?>
											<option value="<?php echo $unique_area; ?>"><?php echo $area_name; ?></option>
										<?php } ?>
									<?php } ?>
								<?php } else { ?>
									<option selected="selected" value="<?php echo $this->options['service_body_1']; ?>"><?php echo 'Not Connected - Can not get Service Bodies'; ?></option>
								<?php } ?>
								</select>							
								<div style="display:inline; margin-left:15px;" id="txtSelectedValues1"></div>
								<p id="txtSelectedValues2"></p>
							</li> 
						</ul>
					</div>
					<div style="padding: 0 15px;" class="postbox">
						<h3>Contents of Third Column</h3>
						<p>The TabbedUI allows the admin to control the contents of the 3rd column in the meetings list</p>
						<p>Leave this blank if you want to see both the city and neighborhod highlighted in the column</p>
						<p>If this is the meetings list of a single city, it is probably not helpful to see the city name over and over again, you 
						might want just the neighborhood.  In that case, neighborhoodsIn:<em>city-name</em> can be used...it will leave off the city
						name for all meetings in that city.
						<ul>
							<li>
								<label for="column3_contents">Contents of Column3: </label>
								<input id="column3_contents" type="text" maxlength="50" name="column3_contents" value="<?php echo $this->options['column3_contents']; ?>" />
							</li>
							<li>
								<label for="field_name">Additional Field: </label>
								<input id="field_name" type="text" maxlength="50" name="field_name" value="<?php echo $this->options['field_name']; ?>" />
							</li>
							<li>
								<label for="phone">Zoom Telefon: </label>
								<input id="phone" type="text" maxlength="50" name="phone" value="<?php echo $this->options['phone']; ?>" />
							</li>
						</ul>
					</div>
					<div style="padding: 0 15px;" class="postbox">
						<h3>Meeting Details Page</h3>
						<p>Clicking on the meeting name brings you to a page dedicated to this meeting</p>
						<ul>
							<li>
								<label for="details_page">URL for F2F Meetings: </label>
								<input id="details_page" type="text" maxlength="100" size=40 name="details_page" value="<?php echo $this->options['details_page']; ?>" />
							</li>
							<li>
								<label for="virtual_details_page">URL for Virtual Meetings: </label>
								<input id="virtual_details_page" type="text" maxlength="100" size=40 name="virtual_details_page" value="<?php echo $this->options['virtual_details_page']; ?>" />
							</li>
						</ul>
					</div>
					<div style="padding: 0 15px;" class="postbox">
						<h3>Meeting Cache (<?php echo $this->count_transient_cache(); ?> Cached Entries)</h3>
						<?php global $_wp_using_ext_object_cache; ?>
						<?php if ($_wp_using_ext_object_cache) { ?>
							<p>This site is using an external object cache.</p>
						<?php } ?>
						<p>Meeting data is cached (as database transient) to load BMLT Tabs faster.</p>
						<ul>
							<li>
								<label for="cache_time">Cache Time: </label>
								<input id="cache_time" onKeyPress="return numbersonly(this, event)" type="number" min="0" max="999" size="3" maxlength="3" name="cache_time" value="<?php echo $this->options['cache_time']; ?>" />&nbsp;&nbsp;<em>0 - 999 Hours (0 = disable and delete cache)</em>&nbsp;&nbsp;
							</li>
						</ul>
						<p><em>The DELETE CACHE button is useful for the following:
						<ol>
						<li>After updating meetings in BMLT.</li>
						<li>Meeting information is not correct on the website.</li>
						<li>Changing the Cache Time value.</li>
						</ol>
						</em>
						</p>
					</div>
					<input type="submit" value="SAVE CHANGES" name="bmlttabssave" class="button-primary" />					
				</form>
				<form style="display:inline!important;" method="post">
					<?php wp_nonce_field('delete_cache_nonce'); ?>
					<input style="color: #000;" type="submit" value="DELETE CACHE" name="delete_cache_action" class="button-primary" />					
				</form>
				<br/><br/>
				<h2>Instructions</h2>
				<p> Please contact <a href="mailto:admin@narcotics-anonymous.de?Subject=BMLT%20Tabs" target="_top">webmaster@nameetinglist.org</a> with problems, questions or comments.</p>
				<div id="accordion">
					<h3 class="help-accordian"><strong>URL Parameters</strong></h3>
					<div>
						<p>This feature will provide the capabity to re-use one page to generate a Tabbed UI for unlimited service bodies.</p>
						<p>Example: A Region would have seperate pages for each Area with a Tabbed UI.</p>
						<p>Instead: One page can be used to display a Tabbed UI for all Areas.</p>
						<p>1. Insert the [bmlt_tabs] into a page.</p>
						<p>2. Link to that page using parameters as described below.</p>
						<p>Accepted Parameters: root_server, service_body, service_body_parent, this_title, meeting_count, group_count.
						<p><em>"service_body" parameter required - all others optional</em></p>
						<p><em>"service_body_parent" parameter for regional meetings</em></p>
						<p>Please study the following URLs to get acquainted with the URL parameter structure.</p>
						<p><strong>Meetings for One Area.</strong></p>
						<p><a target="_blank" href="http://nameetinglist.org/bmlt-tabs/?root_server=http://naflorida.org/bmlt_server&service_body=2&this_title=Greater%20Orlando%20Area%20Meetings&meeting_count=1&group_count=1">http://nameetinglist.org/bmlt-tabs/?<span style="color:red;">root_server</span>=http://naflorida.org/bmlt_server&<span style="color:red;">service_body</span>=2&<span style="color:red;">this_title</span>=Greater%20Orlando%20Area%20Meetings&<span style="color:red;">meeting_count</span>=1&<span style="color:red;">group_count</span>=1</a></p>
						<p><strong>Meetings for Two (or more) Areas.</strong></p>
						<p><a target="_blank" href="http://nameetinglist.org/bmlt-tabs/?root_server=http://naflorida.org/bmlt_server&service_body=2,18&this_title=Greater%20Orlando%20Area%20and%20Central%20Florda%20Area&meeting_count=1&group_count=1">http://nameetinglist.org/bmlt-tabs/?<span style="color:red;">root_server</span>=http://naflorida.org/bmlt_server&<span style="color:red;">service_body</span>=2,18&<span style="color:red;">this_title</span>=Greater%20Orlando%20Area%20and%20Central%20Florda%20Area%20Meetings&<span style="color:red;">meeting_count</span>=1&<span style="color:red;">group_count</span>=1</a></p>
						<p><strong>Meetings for One Region.</strong></p>
						<p><a target="_blank" href="http://nameetinglist.org/bmlt-tabs/?root_server=http://naflorida.org/bmlt_server&service_body_parent=1&this_title=Florida%20Region%20Meetings&meeting_count=1&group_count=1">http://nameetinglist.org/bmlt-tabs/?<span style="color:red;">root_server</span>=http://naflorida.org/bmlt_server&<span style="color:red;">service_body_parent</span>=1&<span style="color:red;">this_title</span>=Florida%20Region%20Meetings&<span style="color:red;">meeting_count</span>=1&<span style="color:red;">group_count</span>=1</a></p>
						<p><em>Title, meeting and group count have unique CSS classes that can be used for custom styling.</em></p>
					</div>
					<h3 class="help-accordian"><strong>Time Format</strong></h3>

					<div>

						<p>With this parameter you can configure the time format.</p>

						<p><strong>[bmlt_tabs time_format="G:i"]</strong></p>

						<p>"G:i" = 24 Hour Time Format (14:00)</p>

						<p>"g:i A" = 12 Hour Time Format (2:00 PM) (Default)</p>

						<p><em>Default is 12 Hour Time Fomat</em></p>
						
						<p>Refer to the <a style='color:#0073aa;' target='_blank' href='http://php.net/manual/en/function.date.php'>PHP Date</a> function for other ways to configure the time.

					</div>

					<h3 class="help-accordian"><strong>BMLT Tabs Shortcode Usage</strong></h3>

					<div>

						<p>Insert the following shortcodes into a page.</p>

						<p><strong>[bmlt_tabs]</strong></p>

						<p><strong>[meeting_count]</strong></p>

						<p><strong>[group_count]</strong></p>

						<p><strong>Example: We now have [group_count] groups with [meeting_count] per week.</strong></p>

						<p><em>Detailed instructions for each shortcode are provided as follows.</em></p>

					</div>

					<h3 class="help-accordian"><strong>Service Body Parameter</strong></h3>
					<div>
						<p>For all shortcodes the service_body parameter is optional.</p>
						<p>When no service_body is specified the default service body will be used.</p>
						<p><strong>[bmlt_tabs service_body="2,3,4"]</strong></p>
						<p>service_body = one or more BMLT service body IDs.</p>
						<p>Using multiple IDs will combine meetings from each service body into the BMLT Tabs interface.</p>
						<p><strong>[bmlt_tabs service_body_parent="1,2,3"]</strong></p>
						<p>service_body_parent = one or more BMLT parent service body IDs.</p>
						<p>An example parent service body is a Region.  This would be useful to get all meetings from a specific Region.</p>
						<p>You can find the service body ID (with shortcode) next to the Default Service Body dropdown above.</p>
						<p><em>You cannot combine the service_body and parent_service_body parameters.</em></p>
					</div>
					<h3 class="help-accordian"><strong>Root Server</strong></h3>
					<div>
						<p>Use a different Root Server.</p>
						<p><strong>[bmlt_tabs service_body="2" root_server="http://naflorida.org/bmlt_server"]</strong></p>
						<p>Useful for displaying meetings from a different root server.</p>
						<em><p>Hint: To find service body IDs enter the different root server into the "BMLT Root Server URL" box and save.</p>
						<p>Remember to enter your current Root Server back into the "BMLT Root Server URL".</p></em>
					</div>
					<h3 class="help-accordian"><strong>View By City, Weekday, Language</strong></h3>
					<div>
						<p>With this parameter you can initially view meetings by City or Weekday.</p>
						<p><strong>[bmlt_tabs view_by="city|weekday|week|lang"]</strong></p>
						<p>city = view meetings by City</p>
						<p>weekday = view meetings by Weekdays (default)</p>
						<p>week = view meetings by week of the month: especially for open meetings</p>
						<p>lang = view meetings by language (other than German)</p>
					</div>
					<h3 class="help-accordian"><strong>Open Meetings by Week</strong></h3>
					<div>
						<p>To present a list of open meetings organized by week</p>
						<p><strong>[bmlt_tabs view_by="week"]</strong></p>
					</div>
					<h3 class="help-accordian"><strong>Exclude City Button</strong></h3>
					<div>
						<p>With this parameter you can exclude the City button.</p>

						<p><strong>[bmlt_tabs include_city_button="0|1"]</strong></p>

						<p>0 = exclude City button</p>

						<p>1 = include City button (default)</p>

						<p><em>City button will be included when view_by = "city" (include_city_button will be set to "1").</em></p>
					</div>
					<h3 class="help-accordian"><strong>Exclude Weekday Button</strong></h3>
					<div>
						<p>With this parameter you can exclude the Weekday button.</p>
						<p><strong>[bmlt_tabs include_weekday_button="0|1"]</strong></p>
						<p>0 = exclude Weekday button</p>
						<p>1 = include Weekday button (default)</p>
						<p><em>Weekday button will be included when view_by = "weekday" (include_weekday_button will be set to "1").</em></p>
					</div>
					<h3 class="help-accordian"><strong>Tabs or No Tabs</strong></h3>
					<div>
						<p>With this parameter you can display meetings without weekday tabs.</p>
						<p><strong>[bmlt_tabs has_tabs="0|1"]</strong></p>
						<p>0 = display meetings without tabs</p>
						<p>1 = display meetings with tabs (default)</p>
						<p><em>Hiding weekday tabs is useful for smaller service bodies.</em></p>
					</div>
					<h3 class="help-accordian"><strong>Header or No Header</strong></h3>
					<div>
						<p>The header will show dropdowns.</p>
						<p><strong>[bmlt_tabs header="0|1"]</strong></p>
						<p>0 = do not display the header</p>
						<p>1 = display the header (default)</p>
					</div>
					<h3 class="help-accordian"><strong>Dropdowns</strong></h3>
					<div>
						<p>With this parameter you can show or hide the dropdowns.</p>
						<p><strong>[bmlt_tabs has_cities='0|1' has_groups='0|1' has_locations='0|1' has_zip_codes='0|1' has_formats='0|1']</strong></p>
						<p>0 = hide dropdown<p>
						<p>1 = show dropdown (default)<p>
					</div>
					<h3 class="help-accordian"><strong>Dropdown Width</strong></h3>
					<div>
						<p>With this parameter you can change the width of the dropdowns.</p>
						<p><strong>[bmlt_tabs service_body="2" dropdown_width="auto|130px|20%"]</strong></p>
						<p>auto = width will be calculated automatically (default)</p>
						<p>130px = width will be calculated in pixels</p>
						<p>20%" = width will be calculated as a percent of the container width</p>
					</div>
					<h3 class="help-accordian"><strong>Exclude Zip Codes</strong></h3>
					<div>
						<p>With this parameter you can exclude meetings in one or more zip codes.</p>
						<p><strong>[bmlt_tabs exclude_zip_codes="32750,32801,32714,etc"]</strong></p>
						<p><em>Warning: Meetings without zip codes will not be excluded.</em></p>
						<p><em><strong>Note: Be sure to "use exclude_zip_codes" in Group and Meeting Count shortcodes (below).</strong></em></p>
					</div>
					<h3 class="help-accordian"><strong>Meeting Count</strong></h3>
					<div>
						<p>Will return the number of meetings for one or more BMLT service bodies.</p>
						<p><strong>[meeting_count]</strong> <em>Will use the default service body (above).</em></p>					
						<p><strong>[meeting_count service_body="2,3,4"]</strong></p>
						<p><strong>[meeting_count service_body_parent="1,2,3"]</strong></p>
						<p>Will return the number of meetings in one or more BMLT parent service bodies.</p>
						<p><strong>[meeting_count service_body="2" subtract="3"]</strong></p>
						<p>subtract = number of meetings to subtract from total meetings (optional)</p>
						<p><em>Subtract is useful when you are using BMLT for subcommittee meetings and do want to count those meetings.</em></p>
					</div>
					<h3 class="help-accordian"><strong>Group Count</strong></h3>
					<div>
						<p>Will return the number of Groups for one or more BMLT service bodies.</p>
						<p><strong>[group_count]</strong> <em>Will use the default service body (above).</em></p>					
						<p><strong>[group_count service_body="2,3,4"]</strong></p>
						<p><strong>[group_count service_body_parent="1,2,3"]</strong></p>
						<p>Will return the number of Groups in one or more BMLT parent service bodies.</p>
					</div>
					<h3 class="help-accordian"><strong>Search for Format</strong></h3>
					<div>
						<p>Returns only meetings having the indicated format.  Example:</p>
						<p><strong>[bmlt_tabs format_key="en"]</strong>returns only meetings in English</p>
						<p>To search for multiple formats, the list may be comma seperated.  To search for meetings that do not have a certain format, use a minus sign (-) in front of the format.
					</div>
					<h3 class="help-accordian"><strong>Complex Search</strong></h3>
					<div>
						<p>Returns only meetings having the matching the query string. This may be quite complex.  Example:</p>
						<p><strong>[bmlt_tabs query_sting="services=3&formats=-212"]</strong>returns meetings in Berlin excluding hybrid meetings</p>
						<p>Use the <a href="https://www.narcotics-anonymous.de/bmlt/semantic/">Semantic Workshop</a>to determine the query string.
					</div>
					<h3 class="help-accordian"><strong>Meetinglist Language</strong></h3>
					<div>
						<p>Generates a meeting list with days and format descriptions in the indicated language. Example:</p>
						<p><strong>[bmlt_tabs lang_enum="en"]</strong></p>
						<p>Supported values: en, fa.  "de" is the default.</p>
						<p>Note that this is independent of the language that might be used in the query string: it is possible to generate a list of a Farsi meetings, but the meeting list itself is in German.</p>
					</div>
					<h3 class="help-accordian"><strong>Online Meetings/ Face-to-Face Meetings </strong></h3>
					<div>
						<p>Include only online meetings or only face-to-face meetings. </p>
						<p><strong>[bmlt_tabs online_only="1"]</strong></p>
						<p>Value = 1: only online meetings.  Value = -1: only face-to-face meetings.</p>
					</div>
					<h3 class="help-accordian"><strong>Third Column Contents</strong></h3>
					<div>
						<p>The TabbedUI allows the admin to control the contents of the 3rd column in the meetings list</p>
						<p>The default contents are set in the Backend, under "Einstellungen->BMLT Tabs", but may be overriden in the shortcode</p>		
						<p>When setting "Column3 Contents", ff this is the meetings list of a single city, it is probably not helpful to see the city name over and over again, you might want just the neighborhood. In that case, neighborhoodsIn:city-name can be used...it will leave off the city name for all meetings in that city.
						<p><strong>[bmlt_tabs field_name="public_transport" column3_contents="neighborhoodsIn:Berlin"]</strong></p>
					</p>

					</div>


				</div>
			</div>
			<script>
			getValueSelected();
			</script>
			<?php
		}
		/**
		 * Deletes transient cache
		 */
		function delete_transient_cache() {
			global $wpdb, $_wp_using_ext_object_cache;
			;
			wp_cache_flush();
			$num1 = $wpdb->query($wpdb->prepare("DELETE FROM $wpdb->options WHERE option_name LIKE %s ", '_transient_bmlt_tabs_%'));
			$num2 = $wpdb->query($wpdb->prepare("DELETE FROM $wpdb->options WHERE option_name LIKE %s ", '_transient_timeout_bmlt_tabs_%'));
			wp_cache_flush();
			return $num1 + $num2;
		}
		/**
		 * count transient cache
		 */
		function count_transient_cache() {
			global $wpdb, $_wp_using_ext_object_cache;
			wp_cache_flush();
			$num1 = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $wpdb->options WHERE option_name LIKE %s ", '_transient_bmlt_tabs_%'));
			$num2 = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $wpdb->options WHERE option_name LIKE %s ", '_transient_timeout_bmlt_tabs_%'));
			wp_cache_flush();
			return $num1;
		}
		/**
		 * @desc Adds the Settings link to the plugin activate/deactivate page
		 */
		function filter_plugin_actions($links, $file) {
			// If your plugin is under a different top-level menu than Settings (IE - you changed the function above to something other than add_options_page)
			// Then you're going to want to change options-general.php below to the name of your top-level page
			$settings_link = '<a href="options-general.php?page=' . basename(__FILE__) . '">' . __('Settings') . '</a>';
			array_unshift($links, $settings_link);
			// before other links
			return $links;
		}
		/**
		 * Retrieves the plugin options from the database.
		 * @return array
		 */
		function getOptions() {
			// Don't forget to set up the default options
			if (!$theOptions = get_option($this->optionsName)) {
				$theOptions = array(
					'cache_time' => '3600',
					'root_server' => '',
					'service_body_1' => '',
					'details_page' => '',
					'virtual_details_page' => ''
				);
				update_option($this->optionsName, $theOptions);
			}
			$this->options = $theOptions;
		}
		/**
		 * Saves the admin options to the database.
		 */
		function save_admin_options() {
			update_option($this->optionsName, $this->options);
			return;
		}
	}
	//End Class BMLTTabs
}
// end if
// instantiate the class
if (class_exists("BMLTTabs")) {
	$BMLTTabs_instance = new BMLTTabs();
}
?>