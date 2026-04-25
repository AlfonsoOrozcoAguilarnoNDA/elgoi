<?php
// License GPL 3.0
// Alfonso Orozco Aguilar
// 24 apr 2026
//die ("installing now");
//ini_set('session.save_path',realpath(dirname($_SERVER['DOCUMENT_ROOT']) . '/../session'));
session_start();

header('Expires: Thu, 1 Jan 1970 00:00:00 GMT');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0',false);
header('Pragma: no-cache');

//date_default_timezone_set('UTC'); // Potential for mistakes
date_default_timezone_set('America/Mexico_City');

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
//require "../verbose_error.php";
//session_id(uniqid("User--"));

if ($_SESSION['number']==""){
  // redirige a fleet_login.php
   header('Location: fleet_login.php');
   exit;
}
