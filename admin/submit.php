<?php
require_once('../../../../wfs_secret.php');

$password = stripslashes( $_POST["pwd"]);

echo ADMIN_SECRET . "<br />";
echo $password . "<br />";

if (strcmp(ADMIN_SECRET, $password) == 0)
{
    $_SESSION['greasy'] = $password;
    header("location:login_success.php");
}
else
{
    echo "Invalid Password";

}