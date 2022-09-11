<?php
    class MeetingHelper {
		public static function seperateFormats(&$value, $formats) {
			$tvalue          = explode(',', $value['formats']);
		    $fc1 = array();
		    $fc2 = array();
		    $fc3 = array();
			$o = array();
			$covid = array();
			unset($value['lang_enum']);
			$value['VM'] = false;
			$value['HY'] = false;
			if (in_array('VM',$tvalue) && !in_array('HY',$tvalue)) {
				$value['is_virtual'] = true;
				if (!in_array('TC',$tvalue)) {
					$value['VM'] = true;
				}
			} else {
				$value['is_virtual'] = false;
			}
		    foreach ($tvalue as $t_value) {
		        if (isSet($formats[$t_value])) {
					$t_format = $formats[$t_value];
		            if (isset($t_format['format_type_enum'])) 
						$type = $t_format['format_type_enum'];
					else $type = '';
					if ($value['is_virtual'] && !$value['VM'] &&
						!(($t_format['online']==true) || (substr($type, 0, 1)=='O'))) {
							continue;
						}
		            if ($type=='LANG') {
		                if (isSet($value['lang_enum'])) {
		                    if ($value['lang_enum'] != $t_value) {
		                        $value['lang_enum2'] = $t_value;
		                    }
		                } else {
		                    $value['lang_enum'] = $t_value;
						}

					} elseif ($t_format['key_string']=='VG') {
						$value['VG'] = true;
						$value['alert'] = $t_format['description_string'];
					} elseif ($t_format['key_string']=='HY') {
						$value['HY'] = true;
					} elseif ($t_format['key_string']=='VM') {
		            } elseif ($type=='ALERT') {
						$value['alert'] = $t_format['description_string'];
					} elseif ($type=='Covid') {
						$covid[] = $t_format;
		            } elseif ($type=='FC3') {
		                $fc3[] = $t_format;
		            } elseif ($type=='FC2') {
		                $fc2[] = $t_format;
		            } elseif (substr($type, 0, 1) == 'O') {
		                $week = substr($type, 2, 1);
		                $o[$week] = $t_format;
		            } elseif (substr($type, 0, 3) == 'FC1') {
		                $week = substr($type, 4, 1);
		                $fc1[$week] = $t_format;
		            }
		        }
			}
			return array(
				'fc1' => $fc1,
				'fc2' => $fc2,
				'fc3' => $fc3,
				'o'   => $o,
				'covid' => $covid,
			);
		}
		public static function isHybrid($value) {
			return in_array('HY',explode(',', $value['formats']));
		}
		public static function isVirtual($value) {
			return in_array('VM',explode(',', $value['formats']));
		}
		public static function getWeek($week,$translate) {
		    if ($week != 'L') {
		      return $week.". Woche im Monat";
		    }
		    return 'Letzte Woche im Monat';
		}
		public static function getday($day,$translate) {
			return $translate['Weekdays'][$day];
		}
		public static function calcTimes($value, $time_format, $lang_enum ) {
			$duration            = explode(':', $value['duration_time']);
		    $minutes             = intval($duration[0]) * 60 + intval((isset($duration[1]) ? $duration[1] : '0'));
		    $addtime             = '+ ' . $minutes . ' minutes';
		    $end_time            = date($time_format, strtotime($value['start_time'] . ' ' . $addtime));
		    $ret 				 = date($time_format, strtotime($value['start_time']));
		    $ret				 = $ret . "&nbsp;-&nbsp;" . $end_time;
			if ($lang_enum=='fa')
				$ret = MeetingHelper::toPersianNum($ret);
			return $ret;
		}
		public static function getTheFormats($root_server,$lang_enum) {
		    if (isset($_POST["formats"]) && !empty($_POST["formats"])) {
		        return $_POST["formats"];
		    }
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, "$root_server/client_interface/json/?switcher=GetFormats&lang_enum=$lang_enum");
			curl_setopt($ch, CURLOPT_USERAGENT, "cURL Mozilla/5.0 (Windows NT 5.1; rv:21.0) Gecko/20130401 Firefox/21.0");
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_TIMEOUT, 10);
			curl_setopt($ch, CURLOPT_MAXREDIRS, 3 );
			curl_setopt($ch, CURLOPT_ENCODING, 'gzip,deflate' );
			$formats = curl_exec($ch);
			curl_close($ch);
			$format_arr = json_decode($formats, true);
			$format = array();
			foreach ($format_arr as $f) {
				if ((isset($f['format_type_enum']) && $f['format_type_enum']=='LANG')
				||  (isset($f['format_type_enum']) && ($f['format_type_enum']=='Alert'&&$f['key_string']!='inst'))
				||  $f['world_id']=='M'
				||  $f['world_id']=='W'
				||  $f['key_string']=='HY'
				||  $f['world_id']=='GL') {
					$f['online'] = true;
				} else {
					$f['online'] = false;
				}
				$format[$f['key_string']] = $f;
			}
			return $format;
		}
		public static function calcLocation(&$value, $translate) {
			$location = '';
			if ($value['VM'] || ($value['service_body_bigint'] == 12)) {
				unset($value['location_street']);
				unset($value['location_postal_code_1']);
				unset($value['location_municipality']);
				unset($value['location_province']);
			} elseif ($value['is_virtual']) {
				$location .= "<div class='location-text'>";
				if (!empty($value['location_text'])) {
					$location .= $translate['Normally'].' @'.$value['location_text'].'<br/>';
				}
				$location .= $translate['corona'];
				unset($value['location_text']);
				unset($value['location_street']);
				unset($value['location_postal_code_1']);
				unset($value['location_municipality']);
				unset($value['location_province']);
				unset($value['location_info']);
			}
			if (isset($value['location_text']) && $value['location_text'] != '') {
				$location .= "<div class='location-text'>" . $value['location_text'] . '</div>';
			} else {
				$value['location_text'] = '';
			}
			$address = '';
			if (isset($value['location_street'])) {
				$address .= $value['location_street'];
			} else {
				$value['location_street'] = '';
			}
			if (isset($value['location_postal_code_1'])) {
			    if ($address != '' && $value['location_postal_code_1'] != '') {
			        $address .= ', ' . $value['location_postal_code_1'];
			    } else {
			        $address .= $value['location_postal_code_1'];
			    }
			} else {
			    $value['location_postal_code_1'] = '';
			}
			if (isset($value['location_municipality'])) {
				if ($address != '' && $value['location_municipality'] != '') {
				    if (isset($value['location_postal_code_1']) && $value['location_postal_code_1'] != '') {
				       $address .= ' ' . $value['location_municipality'];
				    } else {
				        $address .= ', ' . $value['location_municipality'];
				    }
				} else {
					$address .= $value['location_municipality'];
				}
			} else {
				$value['location_municipality'] = '';
			}
			if (isset($value['location_province'])) {
				if ($address != '' && $value['location_province'] != '') {
				    if ($value['location_municipality'] != $value['location_province']) {
					   $address .= ', ' . $value['location_province'];
				    }
				} else {
					$address .= $value['location_province'];
				}
			} else {
				$value['location_province'] = '';
			}
			$additional_class = "";
			if (isset($value['VG']) && $value['VG']) $additional_class = " meeting-closed";
			$location .= "<div class='meeting-address".$additional_class."'>" . $address . '</div>';
			if (isset($value['location_info'])) {
				$location .= "<div class='location-information".$additional_class."'>" . preg_replace('/(https?):\/\/([A-Za-z0-9\._\-\/\?=&;%,]+)/i', '<a href="$1://$2" target="_blank">$1://$2</a>', $value['location_info']) . '</i/></div>';
				//	$location .= "<div class='location-information'>" .                 preg_replace('/(https?):\/\/([A-Za-z0-9\._\-\/\?=&;%,]+)/i', '<a href="$1://$2" target="_blank">$1://$2</a>', $value['location_info']) . '</i/></div>';
			} else {
				$value['location_info'] = '';
			}
			return $location;
		}
		public static function virtualMtg($value,$translate,$phone,$crawler=false) {
			if ($crawler) return '';
			$link = MeetingHelper::getLink($value);
			$info = MeetingHelper::getLinkInfo($value);
			$id = $value['id_bigint'];
			if (substr($link, 0, 4) === "tel:") {
				$parts = explode('/',$link);
				if (count($parts)==1) {
					return "<a href='" .$link. "' id='map-button' class='btn btn-primary btn-xs'>".$link."</a>";
				}
				$phone = $parts[0];
				$message = "<a href='" .$phone. "' id='map-button' class='btn btn-primary btn-xs'>".$phone;
				if (count($parts)>1) {
					$code = $parts[count($parts)-1];
					$parts = explode("?pin=",$code);
					$code = $parts[0];
					$message .= "<br/>Code: ".$code;
					if (count($parts)>1) {
						$message .= "<br/>PIN: ".$parts[1];
					}
				}
				if ($info) {
					$message .= "<br>".$info;
				}
				$message .= "</a>";
				return $message;
			}
			if (substr($link, 0, 12) === "teamspeak://") {
				$parts = explode('/',substr($link,12));
				$server_port = $parts[0];
				$server_port_arr = explode(':',$server_port);
				$server = $server_port_arr[0];
				$port = '';
				if (count($server_port_arr) > 1) {
					$port = $server_port_arr[1];
				}
				$ts_name = '"ts-name'.trim($id).'"';
				$password = '';
				if (count($parts) > 1) {
					$password = $parts[1];
				}
				$message = '<b>TeamSpeak</b><br/><form target="_blank" action="ts3server://'.$server.'" method="get" style="padding:5px">';
				$message .= '<label for='.$ts_name.'>Dein Name: </label>';
				$message .= '<input id='.$ts_name.' name="nickname" maxlength="8" size="8" required />';
				$message .= '<input type="hidden" name=port value="'.$port.'" />';
				$message .= '<input type="hidden" name=password value="'.$password.'" />';
				$message .= '<p style="margin:0;"><button style="background:#3689db; border: none; color: white; border-radius: 5px; padding: 5px 5px; margin-top:5px; margin-bottom:0;">Teamspeak beitreten</button></p></form>';
				$message .= "<a target='_blank' href='http://www.na-onlinemeetings.de/anleitung' id='map-button' class='btn btn-primary btn-xs'>Anleitung</a>";
				return $message;
			}
			if ($link=="http://na-telefonmeeting.de/") {
				return "<a target='_blank' href='" . $link . "' id='map-button' class='btn btn-primary btn-xs'>Mehr Info</a>";
			}
			$map = "<a target='_blank' href='" . $link . "' id='map-button' class='btn btn-primary btn-xs'>".$translate['zoom']."</a>";
			if (!empty($phone) && strpos($link,"zoom")>0) {
				$map .= '<br/>'.$translate['or'].'<br/>';
				$parts = explode('/',$link);
				$code = $parts[count($parts)-1];
				$parts = explode('?pwd=',$code);
				$code = $parts[0];
				$map .= "<a href='tel:" . $phone . "' id='map-button' class='btn btn-primary btn-xs'>".$phone."<br/>".$translate['code'].": ".$code;
				if ($info) {
					$map .= "<br>".$info;
				}
				$map .= "</a>";
			}
			return $map;
		}
		public static function getMap($value) {
			$map = "<a target='_blank' href='https://maps.google.com/maps?q=" . $value['latitude'] . "," . $value['longitude'] . "' id='map-button' class='btn btn-primary btn-xs'><span class='glyphicon glyphicon-map-marker'></span>Google Map</a>"; 
			return $map;
		}
		public static function getOSM($value) {
			return "<a target='_blank' href='http://www.openstreetmap.org/?mlat=" . $value['latitude'] . "&mlon=" . $value['longitude'] . "&zoom=16' id='map-button' class='btn btn-primary btn-xs'><span class='glyphicon glyphicon-map-marker'></span>OpenStreet Map</a>"; 
		}
		public static function getLink($value) {
			$ret = MeetingHelper::getField('virtual_meeting_link',$value);
			if (!$ret) {
				$ret = MeetingHelper::getField('phone_meeting_number',$value);
				if ($ret) $ret = 'tel:'.$ret;
			}
			return $ret;
		}
		public static function getLinkInfo($value) {
			return MeetingHelper::getField('virtual_meeting_additional_info',$value);
		}
		public static function getReservationList($value) {
			return MeetingHelper::getField('seat_reservation',$value);
		}
		static function getField($field,$value) {
			$link = false;
			if (isset($value[$field]) && !empty($value[$field])) {
				$arr = explode("#@-@#", $value[$field]);
				if (count($arr) > 1 and $arr[1] != '')
					$link = $arr[1];
				else $link = $arr[0];
			}
			return $link;
		}
		public static function toPersianNum($number)
		{
		    $number = str_replace("1","۱",$number);
		    $number = str_replace("2","۲",$number);
		    $number = str_replace("3","۳",$number);
		    $number = str_replace("4","۴",$number);
		    $number = str_replace("5","۵",$number);
		    $number = str_replace("6","۶",$number);
		    $number = str_replace("7","۷",$number);
		    $number = str_replace("8","۸",$number);
		    $number = str_replace("9","۹",$number);
		    $number = str_replace("0","۰",$number);
		    return $number;
		}
    }