<?php
require_once('../../../../wfs_secret.php');

$password = stripslashes( $_POST["pwd"]);

if (strcmp(ADMIN_SECRET, $password) == 0)
{
    header("Location: http://wfsadmin.openciti.ca/login_success.php/", TRUE, 302);
}
else
{
    echo "Invalid Password";
}