<?php

$name = stripslashes( $_POST["name"]);
$value = stripslashes( $_POST["value"]);
$description = stripslashes( $_POST["desc"]);
$days_to_expiry = stripslashes( $_POST["days"]);
echo $name;
echo <<<EOD
<html>
<head>
    <meta charset="utf-8" />
    <title>inadmin</title>
    <link rel="icon" type="image/gif" href="http://www.freefavicon.com/freefavicons/misc/001-favicon.gif" />
</head>
<body>

{$name} <br />
{$value} <br />
{$description} <br />
{$days_to_expiry} <br />

searching for venue...
</body>
</html>


EOD;
