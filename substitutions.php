<?php
require('../common.php');

$opts = getOptions($QUERY);
extract($opts);

$sql_checks = $checks;
unset($sql_checks['city_id']);  // We want everything - because we need to calculate national avg as well.
unset($sql_checks['center_id']);
$all_classes = $sql->getAll("SELECT UC.id, UC.substitute_id, UC.class_id, C.class_on, Ctr.city_id, B.center_id
		FROM Class C
		INNER JOIN Batch B ON B.id=C.batch_id
		INNER JOIN Center Ctr ON B.center_id=Ctr.id
		INNER JOIN UserClass UC ON UC.class_id=C.id
		WHERE C.status='happened' AND B.year=$year AND "
		. implode(' AND ', $sql_checks));
$adoption = getAdoptionData('volunteer', $checks);

$template_array = array('total_class' => 0, 'substitution' => 0, 'percentage' => 0);
$data = array($template_array, $template_array, $template_array, $template_array);
$national = $data;
$annual_data = $template_array;

$class_done = array();
$count = 0;
foreach ($all_classes as $c) {
	if(isset($class_done[$c['id']])) continue; // If data is already marked, skip.
	$class_done[$c['id']] = true;
	if($c['class_on'] > date("Y-m-d H:i:s")) continue; // Don't count classes not happened yet.

	$index = findWeekIndex($c['class_on']);

	if((!$center_id or ($c['center_id'] == $center_id)) and (!$city_id or ($c['city_id'] == $city_id))) {
		$annual_data['total_class']++;
		if($index <= 3 and $index >= 0) $data[$index]['total_class']++;

		if($c['substitute_id']) {
			$annual_data['substitution']++;
			if($index <= 3 and $index >= 0) $data[$index]['substitution']++;
		}
	}

	if($index <= 3 and $index >= 0) $national[$index]['total_class']++;
	if($c['substitute_id']) {
		if($index <= 3 and $index >= 0) $national[$index]['substitution']++;
	}


	$count++;
	// if($count > 100) break;
}

foreach($data as $index => $value) {
	if($data[$index]['total_class']) $data[$index]['percentage'] = round($data[$index]['substitution'] / $data[$index]['total_class'] * 100, 2);
	if($national[$index]['total_class']) $national[$index]['percentage'] = round($national[$index]['substitution'] / $national[$index]['total_class'] * 100, 2);
}
if($annual_data['total_class']) $annual_data['percentage'] = round($annual_data['substitution'] / $annual_data['total_class'] * 100, 2);

$page_title = 'Substitutions';
$weekly_graph_data = array(
		array('Weekly ' . $page_title, '% of Substitutions', 'National Average'),
		array('Four week Back', $data[3]['percentage'], $national[3]['percentage']),
		array('Three Week Back',$data[2]['percentage'], $national[2]['percentage']),
		array('Two Week Back', 	$data[1]['percentage'], $national[1]['percentage']),
		array('Last Week',   	$data[0]['percentage'], $national[0]['percentage']),
	);
$annual_graph_data = array(
		array('Year', '% of Substitutions'),
		array('Regular Classes',	100 - $annual_data['percentage']),
		array('Substituted Classes',$annual_data['percentage']),
	);
$colors = array('green', 'red');

unset($opts['checks']);
$listing_link = getLink('substitutions_listing.php', $opts);

render('graph.php');
