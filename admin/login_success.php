<?php

echo <<<EOD

<html>
<head>
    <meta charset="utf-8" />
    <title>inadmin</title>
    <link rel="icon" type="image/gif" href="http://www.freefavicon.com/freefavicons/misc/001-favicon.gif" />
    <link rel="stylesheet" href="style.css">
</head>
<body>
<h2>Enter Venue info:</h2>
<form action="http://wfsadmin.openciti.ca/coupon_submit.php/" method="post">
    <table>
    <tr><td>Name: </td><td><input type="text" name="name" ></td></tr>
    <tr><td>Value: </td><td><input type="text" name="value" ></td></tr>
    <tr><td>Description: </td><td><input type="text" name="desc" ></td></tr>
    <tr><td>Days valid: </td><td><input type="text" name="days" ></td></tr>
    <tr><td><input type="submit"></td></tr>
    </table>
</form>

</body>
</html>


</html>
EOD;


