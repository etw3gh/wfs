<?php

session_start();

if (! isset($_SESSION['greasy']))
{
    header("location:index.php");
}
?>

<html>
<head>
    <meta charset="utf-8" />
    <title>inadmin</title>
    <link rel="icon" type="image/gif" href="http://www.freefavicon.com/freefavicons/misc/001-favicon.gif" />
    <link rel="stylesheet" href="style.css">
</head>
<body>

<form action="add_coupon.php" method="post">
    Admin Password: <input type="text" name="pwd" value=$filler><br />
    <input type="submit">
</form>

</body>
</html>


</html>