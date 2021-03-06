<?php
require('./common.php');

$opts = getOptions($QUERY);
extract($opts);

$sql_checks = $checks;
unset($sql_checks['city_id']);  // We want everything - because we need to calculate national avg as well.
unset($sql_checks['center_id']);
unset($opts['checks']);

$page_title = 'Volunteers Marked';
list($data, $cache_key) = getCacheAndKey('data', $opts); // $data = array();
$year = findYear($opts['to']);

if(!$data) {
	$cache_status = false;
	$data = array();

	if($center_id == -1) $all_centers_in_city = $sql->getCol("SELECT id FROM Center WHERE city_id=$city_id AND status='1'");
	else $all_centers_in_city = array($center_id);

	$template_array = array('total_class' => 0, 'present' => 0, 'absent' => 0, 'cancelled' => 0, 'marked' => 0, 'unmarked' => 0, 'percentage' => 0);
	$data_template = array($template_array, $template_array, $template_array, $template_array, $template_array);
	$center_data = $data_template;
	$national = $data_template;

	if($format == 'csv') {
		$sql_checks['city_id'] = "Ctr.city_id=$city_id";
	}

	$all_classes = $sql->getAll("SELECT UC.user_id, UC.status, C.status AS class_status, C.class_on, Ctr.city_id, B.center_id
			FROM UserClass UC
			INNER JOIN Class C ON C.id=UC.class_id
			INNER JOIN Batch B ON B.id=C.batch_id
			INNER JOIN Center Ctr ON B.center_id=Ctr.id
			WHERE B.year=$year AND "
			. implode(' AND ', $sql_checks) . " ORDER BY C.class_on DESC");

	foreach ($all_centers_in_city as $this_center_id) {
		if($format != 'csv') $data[$this_center_id]['adoption'] = getAdoptionDataPercentage($city_id, $this_center_id, $all_cities, $all_centers, 'volunteer', $project_id);
		$center_data = $data_template;
		$annual_data = $template_array;

		foreach ($all_classes as $c) {
			if($c['class_on'] > date("Y-m-d H:i:s")) continue; // Don't count classes not happened yet.
			$index = findWeekIndex($c['class_on'], $opts['to']);

			if((!$this_center_id or ($c['center_id'] == $this_center_id)) and (!$city_id or ($c['city_id'] == $city_id))) {
				if(!isset($center_data[$index])) $center_data[$index] = $template_array;

				$center_data[$index]['total_class']++;
				if($c['class_status'] == 'projected' or $c['status'] == 'projected') {
					$center_data[$index]['unmarked']++;
				} else {
					if($c['status'] == 'attended') $center_data[$index]['present']++;
					elseif($c['status'] == 'absent') $center_data[$index]['absent']++;
					elseif($c['status'] == 'cancelled') $center_data[$index]['cancelled']++;

					$center_data[$index]['marked']++;
				}
			}
		}

		$data[$this_center_id]['week_dates'] = $week_dates;
		$data[$this_center_id]['center_data'] = $center_data;

		$opts['center_id'] = $this_center_id;

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
		'marked'		=> 'Marked',
		'unmarked'		=> 'Unmarked',
		'present'		=> 'Present',
		'absent'		=> 'Absent',
		'cancelled'		=> 'Cancelled',
		'total_class'	=> 'Total',
	);

if($format == 'csv') render('csv.php', false);
else render('multi_graph.php');
