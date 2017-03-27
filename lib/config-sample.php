<?php

$config = array(
  'servername' => "localhost",
  'username' => "{username}",
  'password' => "{password}",
  'database' => "{database_name}", //name of your database
  'api_key' => "{godaddy_api_key}", //GoDaddy API key
  'api_secret' => "{godaddy_api_secret}", //GoDaddy API secret
  'query_settings' => array(
    'test_env' => true, //uses test GoDaddy endpoints instead of production
    'pagination'=> 10, //default number of rows in sections for dashboard
  )

);

?>
