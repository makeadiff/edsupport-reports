<h1><?php echo $page_title ?></h1>

<?php if($data) { 

if($config['current_page'] == '/class_cancellation_listing.php') { ?>
<form action="" method="post" class="form-area">
<?php
$html->buildInput("cancel_option", 'Cancellation Type', 'select', $cancel_option, array('options' => array('in' => 'Internal', 'ext' => 'External', '' => 'All')));
$html->buildInput("action", '&nbsp;', 'submit', 'Filter', array('class' => 'btn btn-primary'));
?>
</form>
<?php } ?>

<table class="table table-striped">
<tr><th>Count</th><?php $first_row = reset($data);
$header = array_keys($first_row);
foreach ($header as $label) {
	print "<th>" . format($label) . "</th>";
}
?></tr>

<?php $count=0; foreach ($data as $row) { $count++; ?>
<tr>
	<td><?php echo $count ?></td><td><?php echo implode("</td><td>", array_values($row)) ?></td>
</tr>
<?php } ?>
</table>
<?php } ?>