mailup-php
==========

A PHP class to manage communications with MailUp servers.

Remarks
-------
You have to enable web services in your MailUp console to use this class.

Example
-------
Access parameters are those for the web service (you can get them in your MailUp console).

```php
$mailup = new MailUp(
	'username', // Your MailUp login for web services (should start with an 'a')
	'password', // Your MailUp password for web services
	'console url', // The url of your console (well, it's enough its host name, 
	'console id', // The id of the console. This should be the numbers after the 'a' in the username. If left blank we'll try to detect it.
	'', // An optional folder where the class can use for caching some data.
	true // Debug enabled?
);

var_dump($mailup->GetLists());
var_dump($mailup->GetMessages(1));

`