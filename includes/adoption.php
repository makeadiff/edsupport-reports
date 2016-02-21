<?php
function getAdoptionDataPercentage($city_id, $center_id, $all_cities, $all_centers, $data_type) {
	list($adoption_data, $cache_key) = getCacheAndKey('adoption_data', array($city_id, $center_id, $data_type));

	$adoption_data = array();
	if(!$adoption_data) {
		$adoption_data = getAdoptionData($city_id, $center_id, $all_cities, $all_centers);
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
function getAdoptionData($city_id, $center_id, $all_cities, $all_centers) {
	global $sql, $year;

	$days = array("Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday");
	$year_start = $year . '-04-01 00:00:00';
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
			WHERE B.year=$year AND B.status='1' AND C.status='1' $city_check $center_check ");
	
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

		$all_classes = $sql->getAll("SELECT C.id AS class_id, C.level_id, C.status, C.class_on, student_id, participation
				FROM Class C
				LEFT JOIN StudentClass SC ON C.id=SC.class_id 
				WHERE C.class_on>'$year_start' AND C.class_on<'$year_end' AND C.batch_id=$batch_id");

		$class_done = array();
		foreach ($all_classes as $c) {
			if(!isset($all_cities_data[$b['city_id']])) continue;
			if($c['class_on'] > date("Y-m-d H:i:s")) continue; // Don't count classes not happened yet.
			if(isset($class_done[$c['class_id']])) continue; // If data is already marked, skip.
			$class_done[$c['class_id']] = true;

			$all_batches[$batch_id]['classes_total']++;
			$all_centers_data[$b['center_id']]['classes_total']++;
			$all_cities_data[$b['city_id']]['classes_total']++;

			if($c['status'] != 'projected') {
				$all_batches[$batch_id]['volunteer_attendance']++;
				$all_centers_data[$b['center_id']]['volunteer_attendance']++;
				$all_cities_data[$b['city_id']]['volunteer_attendance']++;
			}
			if($c['student_id'] or $c['status'] == 'cancelled') {
				$all_batches[$batch_id]['student_attendance']++;
				$all_centers_data[$b['center_id']]['student_attendance']++;
				$all_cities_data[$b['city_id']]['student_attendance']++;
			}
		}
	}

	return array('all_cities_data' => $all_cities_data, 'all_centers_data' => $all_centers_data, 'all_batches' => $all_batches);
}
