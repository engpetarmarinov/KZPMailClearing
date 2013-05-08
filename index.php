<?php
/**
 * class.ccpcheck.php - example of usage
 */
set_time_limit(0);
require 'class.ccpcheck.php';

$emails = array(
	1=>'gmilenkov@yahoo.com',
	2=>'deita@dir.bg',
	99=>'mamamia@abv.bg',
	3=>'b.dimitrov@gmail.com',
	4=>'lazarov@telenova.bg',
	5=>'l.finance@sphold.com',
	6=>'leasing@sphold.com',
	7=>'vv@sphold.com',
	8=>'marieta.stantscheva@intergest.com',
	9=>'wildalmighty@abv.bg',
	10=>'test@mail.bg'
);

try{
	$kzp = new KZPMailClearing();
	echo 'Filename downloaded from kzp can be used to check the last check: '.$kzp->get_filename_with_hashes()."<br/>";	
	//do the clearing
	$kzp->check($emails);
	echo '<h2>Report</h2>';
	//get report
	echo $kzp->report();
	var_dump($kzp->get_matched_domains());
	var_dump($kzp->get_matched_emails());
	var_dump($kzp->get_matched_emails_by_domain());
	echo '<h2>Incorrect</h2>';
	var_dump($kzp->get_incorrect());
}
catch(Exception $e){
	echo 'Error: '. $e->getMessage();
}
