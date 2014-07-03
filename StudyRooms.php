<?php

	$searchBuildings = array(
			'0022',
			'1048',
			'0045',
			'0026',
			'0024',
			'0032',
			'0015',
			'1042',
			'1084',
			'1085',
			'0028'
		);
	$url = 'http://e.tju.edu.cn/Education/toModule.do?prefix=/Education&page=/schedule.do?todo=displayWeekBuilding&schekind=6';
	$week_all = 26;

	foreach ($searchBuildings as $build) {

		$arr = array();

		for ($k = 1; $k <= $week_all; $k++) {
			$post_fields = 'todo=displayWeekBuilding&week='.$k.'&building_no='.$build;
			$ch = curl_init($url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
			$data = curl_exec($ch);

			$data = mb_convert_encoding($data, 'UTF-8', 'GBK');
			
			preg_match("/<table width=\"90%\"(.*)<\/table>/s", $data, $table_match);
			$is_success = preg_match_all("/<tr>[^<]*<td bgcolor(.*?)<\/tr>/s", $table_match[0], $room_matches);
			
			if ($is_success) {
				foreach ($room_matches[0] as $v) {
					$tmp = array();
					$tmp['build'] = $build;
					$tmp['week'] = $k;
					$tmp['is_seldom'] = '';
					preg_match_all("/<font[^\"]+\"([^\"]+)\"[^>]*>(.*?)<\/font>/i", $v, $matches);
					for ($i = 0; $i < count($matches[0]); $i++) {
						$str = trim($matches[1][$i]);
						if (strpos($str, 'hite') === 1) {
							$tmp['room'] = $matches[2][$i];
						} else if (strpos($str, 'lack') === 1) {
							$tmp['is_seldom'] .= '0';
						} else if (strpos($str, '#') === 0) {
							$tmp['is_seldom'] .= '1';
						}
					}
					$arr[] = $tmp;
				}
			}

		}

		var_dump($arr);

	}

?>
