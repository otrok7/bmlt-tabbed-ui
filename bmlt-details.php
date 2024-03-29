<?php
/*
Plugin Name: BMLT Details
Description: Adds a shortcode so that we can create a meeting details page
Version: 0.1
*/
/* Disallow direct access to the plugin file */
if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {
	// die('Sorry, but you cannot access this page directly.');
}

require_once plugin_dir_path(__FILE__).'vendor/autoload.php';
if (!class_exists("BMLTMeetingDetails")) {
	require_once 'MeetingHelper.php';
	class BMLTMeetingDetails {
		var $optionsName = 'bmlt_tabs_options';
		var $options = array();
		
		function __construct() {
			$this->getOptions();		
			if (is_admin()) {
			} else {
				// Front end				
				add_action("wp_enqueue_scripts", array(&$this, "enqueue_frontend_files"));			
				add_shortcode('bmlt_details', array(
					&$this,
					"bmlt_details"
				));
			}
		}
		function has_meeting_id_query() {
			return isset($_GET['meeting-id']);
		}
		/**
		 * @desc Adds JS/CSS to the header
		 */
		function enqueue_frontend_files() {
			if ( $this->has_meeting_id_query() ) {
				$this->query_db_and_cache_in_session();
				wp_enqueue_style("bmlt-tabs-bootstrap", plugin_dir_url(__FILE__) . "css/bootstrap.min.css", false, filemtime( plugin_dir_path(__FILE__) . "css/bootstrap.min.css"), false);
				wp_enqueue_style("bmlt-tabs", plugin_dir_url(__FILE__) . "css/bmlt_tabs.css", false, filemtime( plugin_dir_path(__FILE__) . "css/bmlt_tabs.css"), false);
			}
		}
		function getTheMeeting($root_server, $id) {
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, "$root_server/client_interface/json/?switcher=GetSearchResults&meeting_key=id_bigint&meeting_key_value=$id");
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
				return null;
			}
			return $result[0];
		}
		function query_db_and_cache_in_session() {
			$root_server = $this->options['root_server'];
			if ($root_server == '') {
				echo '<div id="message" class="error"><p>Missing BMLT Root Server in settings for BMLT Tabs.</p>';
				$url = admin_url('options-general.php?page=bmlt-tabbed-ui.php');
				echo "<p><a href='$url'>BMLT_Tabs Settings</a></p>";
				echo '</div>';
			}
			$_SESSION['bmlt_formats'] = MeetingHelper::getTheFormats($root_server,'de');
			$_SESSION['bmlt_format_lang'] = 'de';
			$id = $_GET['meeting-id'];
			$value = $this->getTheMeeting($root_server, $id);
			$_SESSION['bmlt_details'] = $value;
			$_SESSION['bmlt_area'] = $this->get_area($root_server, $value);

			add_filter( 'pll_the_language_link', array($this,'url_query_string') );

		}
		function url_query_string( $url ) {
			if ( ! empty( $_SERVER['QUERY_STRING'] ) ) {
				return $url . '?' . $_SERVER['QUERY_STRING'];
			}
			return $url;
		}
		function bmlt_details($atts, $content) {
			$ret = $this->bmlt_field($atts);
			if (empty($ret)) return '';
			$prefix = empty($atts['prefix'])?'':htmlspecialchars_decode($atts['prefix'],ENT_QUOTES);
			return $prefix.$ret;
		}
		function bmlt_field($atts, $content = null) {
			extract(shortcode_atts(array(
				"time_format" => 'G:i',
				"lang_enum" => '',
				"field" => 'name',
			), $atts));
			if (!empty($lang_enum) && $lang_enum != $_SESSION['bmlt_format_lang']) {
				$_SESSION['bmlt_formats'] = MeetingHelper::getTheFormats($this->options['root_server'],$lang_enum);
				$_SESSION['bmlt_format_lang'] = $lang_enum;
			}	
			$formats = $_SESSION['bmlt_formats'];		
			$value = $_SESSION['bmlt_details'];
			if ($value == null) {
				return "not found";
			}
			$area = $_SESSION['bmlt_area'];
			if (empty($lang_enum)) $lang_enum = 'de';
			include(dirname(__FILE__)."/lang/translate_".$lang_enum.".php");
			switch($field) {
				case 'name':
					return empty($value['meeting_name']) ? 'NA Meeting' :  $value['meeting_name'];
				case 'day':
					return MeetingHelper::getDay($value['weekday_tinyint'],$translate);
				case 'times':
					return MeetingHelper::calcTimes($value, $time_format, $lang_enum);
				case 'comments':
					return isset($value['comments']) ? $value['comments'] : '';
				case 'alert':
					return isset($value['alert']) ? $value['alert'] : '';
				case 'area':
					return '<a href="'.$area['url'].'">'.$area['name'].'</a>';
				case 'mailto':
					return $this->getMailTo($value, $area, $translate);
				case 'location':
					return (MeetingHelper::isHybrid($value) || !MeetingHelper::isVirtual($value)) ? MeetingHelper::calcLocation($value,$translate) : '';
				case 'virtual':
					$phone 					= '';
					if (isset($this->options['phone']))
						$phone				= $this->options['phone'];
					return (MeetingHelper::isHybrid($value) || MeetingHelper::isVirtual($value))
						? '<div class="bootstrap-bmlt">'.MeetingHelper::virtualMtg($value,$translate,$phone,false,'').'</div>' : '';
				case 'formats':
					return $this->listFormats($value, $formats, $translate);
				case 'languages':
					MeetingHelper::seperateFormats($value, $formats);
					if (!isset($value['lang_enum'])) return '';
					$ret = htmlspecialchars($formats[$value['lang_enum']]['description_string'], ENT_QUOTES);
					if (!isset($value['lang_enum2'])) return $ret;
					return $ret.'<br/>'.htmlspecialchars($formats[$value['lang_enum2']]['description_string'], ENT_QUOTES);
				default:
					if (isset($value[$field])) return $value[$field];
					return '';
				}
		}
		function listFormats($value, $formats, $translate) {
			extract(MeetingHelper::seperateFormats($value, $formats));
		    $weeks = array("1","2","3","4","5","L",'*');
			$ret = '';
			if (count($covid)>0) {
				$ret .= '<br/><h4 class="formats_header" id="headline_second_level"'.$translate['style:align'].' style="white-space:normal;word-break:break-word">'.$translate['Covid-Responsibility'].'</h4><ul>';
				foreach ($covid as $f) {
					$ret .= '<li class="formats_description">'.$f['description_string'].'</li>';
				}
				$ret .= '</ul>';
			}
			if (count($fc2)+count($fc3)+count($o) > 0) {
				$ret .= '<br/><h4 class="formats_header" id="headline_second_level"'.$translate['style:align'].'>'.$translate['Info'].'</h4><ul>';
				foreach ($fc2 as $f) {
					$ret .= '<li class="formats_description">'.$f['description_string'].'</li>';
				}
				foreach ($fc3 as $f) {
					$ret .= '<li class="formats_description">'.$f['description_string'].'</li>';
				}
				$special_weeks = false;
				foreach ($weeks as $week) {
					if (isSet($o[$week])) {
						$f = $o[$week];
						$ret .= '<li class="formats_description">'.$f['description_string'].'</li>';
					}
				}
				$ret .= '</ul>';
			}
			$text = MeetingHelper::getField('format_comments',$value);
			if (count($fc1) or $text!='') {
				$ret .= '<br/><h4 class="formats_header" id="headline_second_level"'.$translate['style:align'].'>'.$translate['Format'].'</h4><ul>';
				$special_weeks = false;
				foreach ($weeks as $week) {
					if (isSet($fc1[$week])) {
						$f = $fc1[$week];
						$descr = (($special_weeks and $week=='*')?$translate['week*']:'')
								 .$f['description_string'];
						$ret .= '<li class="formats_description">'.$descr.'</li>';
						$special_weeks = true;
					}
				}
				if ($text!='') {
					$ret .= '<li class="formats_description">'.$text.'</li>';
				}
				$ret .= '</ul>';
			}
			return $ret;
		}
		function getMailTo($value, $area, $translate) {
			$ret = 'mailto:';
			$ret .= $area['contact_email'];
			if (empty($value['meeting_name']) || $value['meeting_name']=="NA Meeting") {
				if (MeetingHelper::isVirtual($value) && !MeetingHelper::isHybrid($value)) {
					$name = 'Online';
				} else {
					$name = $value['location_street'];
				}
			} else {
				$name = $value['meeting_name'];
			}
			$ret .= '?subject=Änderung Meeting '.MeetingHelper::getDay($value['weekday_tinyint'],$translate).' '.date('G:i', strtotime($value['start_time'])).' - '.$name;

			return $ret;

		}
		/**
		 * @desc Adds the options sub-panel
		 */
		function get_area($root_server, $value) {
			$resource = curl_init();
			curl_setopt($resource, CURLOPT_URL, "$root_server/client_interface/json/?switcher=GetServiceBodies");
			curl_setopt($resource, CURLOPT_USERAGENT, "cURL Mozilla/5.0 (Windows NT 5.1; rv:21.0) Gecko/20130401 Firefox/21.0");
			curl_setopt($resource, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($resource, CURLOPT_TIMEOUT, 10);
			curl_setopt($resource, CURLOPT_MAXREDIRS, 3 );
			curl_setopt($resource, CURLOPT_ENCODING, 'gzip,deflate' );
			curl_setopt($resource, CURLOPT_SSL_VERIFYPEER, 0);
			$results  = curl_exec($resource);
			$areas   = json_decode($results, true);
			$httpcode = curl_getinfo($resource, CURLINFO_HTTP_CODE);
			$c_error  = curl_error($resource);
			$c_errno  = curl_errno($resource);
			curl_close($resource);
			foreach($areas as $area) {
				if ($area['id']==$value['service_body_bigint']) return $area;
			}
			return null;
		}
		function getOptions() {
			// Don't forget to set up the default options
			if (!$theOptions = get_option($this->optionsName)) {
				$theOptions = array(
					'cache_time' => '3600',
					'root_server' => '',
					'service_body_1' => ''
				);
				update_option($this->optionsName, $theOptions);
			}
			$this->options = $theOptions;
		}
	}


}
	//End Class BMLTMeetingDetails
// end if
// instantiate the class
if (class_exists("BMLTMeetingDetails")) {
$BMLTTabs_instance = new BMLTMeetingDetails();
}
?>