<?php

require_once dirname(__FILE__) . '/../MailUp.php';

echo "MailUp example.\n\n";

try {
	$mailup = new MailUp(
		'username', // Your MailUp login for web services (should start with an 'a')
		'password', // Your MailUp password for web services
		'console url', // The url of your console (well, it's enough its host name, 
		'console id', // The id of the console. This should be the numbers after the 'a' in the username. If left blank we'll try to detect it.
		'', // An optional folder where the class can use for caching some data.
		true // Debug enabled?
	);
}
catch(Exception $x) {
	echo "Please upate the file\n" . __FILE__ . "\nto specify your access data (login, password, ...).\n\n";
	die(1);
}