<?php
require('./common.php');

$opts = getOptions($QUERY);
extract($opts);

$sql_checks = $checks;
unset($sql_checks['city_id']);  // We want everything - because we need to calculate national avg as well.
unset($sql_checks['center_id']);
unset($opts['checks']);

$page_title = 'Child Attendance';

list($data, $cache_key) = getCacheAndKey('data', $opts);
$year = findYear($to);

$output_data_format = 'percentage';
if($format == 'csv') $output_data_format = 'attendance';
$output_total_format = 'total_class';
$output_unmarked_format = 'unmarked';

if(!$data) {
	$data = array();
	$cache_status = false;

	if($center_id == -1) $all_centers_in_city = $sql->getCol("SELECT id FROM Center WHERE city_id=$city_id AND status='1'");
	else $all_centers_in_city = array($center_id);

	$template_array = array('total_class' => 0, 'attendance' => 0, 'marked' => 0, 'unmarked' => 0, 'cancelled' => 0, 'percentage' => 0);
	$data_template = array($template_array, $template_array, $template_array, $template_array, $template_array);
	$center_data = $data_template;
	$national = $data_template;

	if(!$center_id) {
		$centers_to_check = $sql->getCol("SELECT Ctr.id FROM Center Ctr 
			INNER JOIN City C ON C.id=Ctr.city_id 
			WHERE Ctr.status='1' AND C.type='actual'");
	} else {
		$centers_to_check = $all_centers_in_city;
	}

	if(!$centers_to_check) die("No Shelters found.");

	$level_data = $sql->getById("SELECT L.id, COUNT(SL.id) as student_count 
		FROM Level L 
		INNER JOIN StudentLevel SL ON SL.level_id=L.id 
		WHERE L.center_id IN (" .implode(",", $centers_to_check). ") AND L.status='1' AND L.year='$year' AND L.project_id=$project_id
		GROUP BY SL.level_id");
	if(!$level_data) die("Didn't find any levels");

	$students = $sql->getById("SELECT SC.class_id, COUNT(SC.id) AS total_count, SUM(CASE WHEN SC.present='1' THEN 1 ELSE 0 END) AS present
		FROM StudentClass SC 
		INNER JOIN Class C ON C.id=SC.class_id 
		WHERE C.level_id IN (" . implode(",", array_keys($level_data)) . ") AND C.class_on >= '$from 00:00:00' AND C.class_on <= '$to 00:00:00'
		GROUP BY SC.class_id");

	$all_classes = $sql->getAll("SELECT C.id, C.status, C.level_id, C.class_on, Ctr.city_id, B.center_id
		FROM Class C
		INNER JOIN Batch B ON B.id=C.batch_id
		INNER JOIN Center Ctr ON B.center_id=Ctr.id
		WHERE B.status='1' AND  B.year='$year' AND "
		. implode(' AND ', $sql_checks) . " 
		ORDER BY class_on DESC");

	foreach ($all_classes as $c) {
		if($c['class_on'] > date("Y-m-d H:i:s")) continue; // Don't count classes not happened yet.

		$index = findWeekIndex($c['class_on'], $opts['to']);
		if(!isset($national[$index])) $national[$index] = $template_array;

		$class_id = $c['id'];
		if($c['status'] == 'cancelled') {
			if(isset($level_data[$c['level_id']])) {
				$national[$index]['cancelled'] += $level_data[$c['level_id']];
			}

		} elseif(isset($students[$class_id])) { // There were hits in the StudentClass table
			$national[$index]['total_class'] += $students[$class_id]['total_count'];
			$national[$index]['attendance'] += $students[$class_id]['present'];
			$national[$index]['marked'] += $students[$class_id]['total_count'];

		} else { // No coressponding rows in the StudentClass Table - meaning data not entered. So, we are going to get the data from the level table - students assigned to that level.
			if(isset($level_data[$c['level_id']])) {
				$national[$index]['total_class'] += $level_data[$c['level_id']];
				$national[$index]['unmarked'] += $level_data[$c['level_id']];
			}
			// Else - no kids assigned to this level, it seems.
		}
	}

	foreach($national as $index => $value) {
		if($national[$index]['total_class']) $national[$index]['percentage'] = round($national[$index]['attendance'] / $national[$index]['total_class'] * 100, 2);
	}

	foreach ($all_centers_in_city as $this_center_id) {
		$center_data = $data_template;
		$annual_data = $template_array;

		$data[$this_center_id]['adoption'] = getAdoptionDataPercentage($city_id, $this_center_id, $all_cities, $all_centers, 'student', $project_id);

		foreach ($all_classes as $c) {
			if($c['class_on'] > date("Y-m-d H:i:s")) continue; // Don't count classes not happened yet.
			if(($this_center_id and ($c['center_id'] != $this_center_id)) or ($city_id > 0 and ($c['city_id'] != $city_id))) continue;

			$index = findWeekIndex($c['class_on'], $to);

			if((!$this_center_id or ($c['center_id'] == $this_center_id)) and ($city_id <= 0 or ($c['city_id'] == $city_id))) {
				if(!isset($center_data[$index])) $center_data[$index] = $template_array;
				if(!isset($annual_data[$index])) $annual_data[$index] = $template_array;

				$class_id = $c['id'];
				$student_count_for_class = 0;
				if(isset($level_data[$c['level_id']])) $student_count_for_class = $level_data[$c['level_id']];

				if($c['status'] == 'cancelled') { // If class was cancelled, add the count of the number of kids in that class as cancelled.
					if(isset($level_data[$c['level_id']])) {
						$center_data[$index]['cancelled'] += $student_count_for_class;
						$center_data[$index]['total_class'] += $student_count_for_class;
						
						$annual_data[$index]['cancelled'] += $student_count_for_class;
						$annual_data[$index]['total_class'] += $student_count_for_class;
					}

				} elseif(isset($students[$class_id])) { // There were hits in the StudentClass table
					$center_data[$index]['total_class'] += $students[$class_id]['total_count'];
					$center_data[$index]['attendance'] += $students[$class_id]['present'];
					$center_data[$index]['marked'] += $students[$class_id]['total_count'];

					$annual_data['total_class'] += $students[$class_id]['total_count'];
					$annual_data['attendance'] += $students[$class_id]['present'];
					$annual_data['marked'] += $students[$class_id]['total_count'];

				} else { // No coressponding rows in the StudentClass Table - meaning data not entered. So, we are going to get the data from the level table - students assigned to that level.
					if(isset($level_data[$c['level_id']])) {
						$center_data[$index]['total_class'] += $student_count_for_class;
						$center_data[$index]['unmarked'] += $student_count_for_class;

						$annual_data[$index]['total_class'] += $student_count_for_class;
						$annual_data[$index]['unmarked'] += $student_count_for_class;
					} else { // Else - no kids assigned to this level, it seems.
					}
				}
			}
		}

		foreach($center_data as $index => $value) {
			if($center_data[$index]['total_class']) $center_data[$index]['percentage'] = round($center_data[$index]['attendance'] / $center_data[$index]['total_class'] * 100, 2);
		}
		if($annual_data['total_class']) $annual_data['percentage'] = round($annual_data['attendance'] / $annual_data['total_class'] * 100, 2);

		$weekly_graph_data = array(
			array('Week', 'Weekly Child Attendance', 'National Average'),
			array(date('j M Y', strtotime($week_dates[3])), $center_data[3][$output_data_format], $national[3][$output_data_format]),
			array(date('j M Y', strtotime($week_dates[2])),$center_data[2][$output_data_format], $national[2][$output_data_format]),
			array(date('j M Y', strtotime($week_dates[1])),	$center_data[1][$output_data_format], $national[1][$output_data_format]),
			array(date('j M Y', strtotime($week_dates[0])),		$center_data[0][$output_data_format], $national[0][$output_data_format])
		);

		$annual_graph_data = array(
			array('Year', 'Attendance'),
			array('Attended',	$annual_data['attendance']),
			array('Absent',		$annual_data['marked'] - $annual_data['attendance']),
		);

		$data[$this_center_id]['weekly_graph_data'] = $weekly_graph_data;
		$data[$this_center_id]['annual_graph_data'] = $annual_graph_data;
		krsort($week_dates);
		$data[$this_center_id]['week_dates'] = $week_dates;
		$data[$this_center_id]['center_data'] = $center_data;

		$data[$this_center_id]['city_id'] = $city_id;
		$data[$this_center_id]['center_id'] = $this_center_id;
		$data[$this_center_id]['center_name'] = ($this_center_id) ? $sql->getOne("SELECT name FROM Center WHERE id=$this_center_id") : '';
	}
	setCache($cache_key, $data);
}

$colors = array('#16a085', '#e74c3c');

$csv_format = array(
		'city_name'		=> 'City',
		'center_name'	=> 'Center',
		'week'			=> 'Week',
		'cancelled'		=> 'Cancelled',
		'attendance'	=> 'Child Attendance',
		'unmarked'		=> 'Unmarked',
		'total_class'	=> 'Total',
	);

if($format == 'csv') render('csv.php', false);
else render('multi_graph.php');
