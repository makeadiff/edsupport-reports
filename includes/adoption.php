<?php
function getAdoptionDataPercentage($city_id, $center_id, $all_cities, $all_centers, $data_type, $project_id) {
	list($adoption_data, $cache_key) = getCacheAndKey('adoption_data', array($city_id, $center_id, $data_type, $project_id));

	if(!$adoption_data) {
		$adoption_data = getAdoptionData($city_id, $center_id, $all_cities, $all_centers, $project_id);
		setCache($cache_key, $adoption_data);
	}

	if(!$city_id and !$center_id) {
		$info = array(
			'classes_total'	=> 0,
			'volunteer_attendance' => 0,
            'student_attendance' => 0
		);

		foreach ($adoption_data['all_cities_data'] as $city_id => $city_info) {
			$info['classes_total'] += $city_info['classes_total'];
			$info['volunteer_attendance'] += $city_info['volunteer_attendance'];
			$info['student_attendance'] += $city_info['student_attendance'];
		}

	} else if($city_id and !$center_id) {
		$info = $adoption_data['all_cities_data'][$city_id];
	} else if($center_id and isset($adoption_data['all_centers_data'][$center_id])) {
		$info = $adoption_data['all_centers_data'][$center_id];
	}

	$percent = 0;
	if(isset($info['classes_total']) and $info['classes_total']) $percent = ceil ($info[$data_type.'_attendance'] / $info['classes_total'] * 100);

	return $percent;
}
function getAdoptionData($city_id, $center_id, $all_cities, $all_centers, $project_id) {
	global $sql, $year;

	$days = array("Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday");
	$year_start = $year . '-06-01 00:00:00';
	$year_end = intval($year+1) . '-03-31 00:00:00';

	$all_batches = array();
	$all_centers_data = array();
	$all_cities_data = array();

	$center_check = '';
	$city_check = ''; 
	if($center_id) $center_check = " AND center_id=$center_id";
	if($city_id) $city_check = " AND C.city_id=$city_id";
	$batches = $sql->getAll("SELECT B.id, B.day, B.class_time, B.center_id, C.city_id 
			FROM Batch B INNER JOIN Center C ON C.id=B.center_id 
			WHERE B.year=$year AND B.status='1' AND C.status='1' AND B.project_id=$project_id $city_check $center_check ");
	
	foreach ($batches as $b) {
		$batch_id = $b['id'];
		$all_batches[$batch_id] = array(
			'name'					=> $days[$b['day']] . ' ' . $b['class_time'],
			'classes_total'			=> 0,
			'volunteer_attendance'	=> 0,
			'student_attendance'	=> 0,
		);

		if(!isset($all_centers_data[$b['center_id']])) {
			$all_centers_data[$b['center_id']] = array(
				'name'					=> $all_centers[$b['center_id']]['name'],
				'classes_total'			=> 0,
				'volunteer_attendance'	=> 0,
				'student_attendance'	=> 0,
			);
		}

		if(!isset($all_cities_data[$b['city_id']])) {
			if(isset($all_cities[$b['city_id']]))
				$all_cities_data[$b['city_id']] = array(
					'name'					=> $all_cities[$b['city_id']],
					'classes_total'			=> 0,
					'volunteer_attendance'	=> 0,
					'student_attendance'	=> 0,
				);
		}

		$all_classes = $sql->getAll("SELECT C.id, C.status, C.level_id, C.class_on, SC.student_id,
											UC.id AS user_class_id, UC.user_id, GROUP_CONCAT(UC.status SEPARATOR ',') AS user_status, UC.substitute_id
				FROM Class C
				INNER JOIN UserClass UC ON C.id=UC.class_id 
				LEFT JOIN StudentClass SC ON C.id=SC.class_id 
				INNER JOIN Level L ON L.id=C.level_id
				WHERE C.class_on>'$year_start' AND C.class_on<'$year_end' AND L.year=$year AND L.status='1' AND C.batch_id=$batch_id
				GROUP BY C.id");

		$class_done = array();
		foreach ($all_classes as $c) {
			if(isset($class_done[$c['user_class_id']])) continue; // If data is already marked, skip.
			if(!isset($all_cities_data[$b['city_id']])) continue;
			$class_done[$c['user_class_id']] = true;
			if($c['class_on'] > date("Y-m-d H:i:s")) continue; // Don't count classes not happened yet.

			$is_projected = false;
			if($c['status'] == 'projected') { // In some cases, Class.status is projected - but internal UserClass.status might have 'cancelled'.
				$is_projected = true;

				$class_status_parts = explode(",", $c['user_status']);
				foreach ($class_status_parts as $ind_status) {
					if($ind_status != 'projected') $is_projected = false;
				}
			}

			$all_batches[$batch_id]['classes_total']++;
			$all_centers_data[$b['center_id']]['classes_total']++;
			$all_cities_data[$b['city_id']]['classes_total']++;

			if(!$is_projected) {
				$all_batches[$batch_id]['volunteer_attendance']++;
				$all_centers_data[$b['center_id']]['volunteer_attendance']++;
				$all_cities_data[$b['city_id']]['volunteer_attendance']++;
			}
			if($c['student_id'] or $c['status'] == 'cancelled' or strpos($c['user_status'], 'cancelled') !== false)  {
				$all_batches[$batch_id]['student_attendance']++;
				$all_centers_data[$b['center_id']]['student_attendance']++;
				$all_cities_data[$b['city_id']]['student_attendance']++;
			}
		}
	}

	return array('all_cities_data' => $all_cities_data, 'all_centers_data' => $all_centers_data, 'all_batches' => $all_batches);
}
