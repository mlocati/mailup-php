<?php

require_once dirname(__FILE__) . '/example-init.php';

echo ucfirst(str_replace('-', ' ', basename(__FILE__, '.php'))), "\n";

try {
	print_r($mailup->GetMessages(1));
}
catch(Exception $x) {
	echo "ERROR!\n\n", $x->getMessage();
	die(1);
}
