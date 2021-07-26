<?php
ini_set("display_errors", 1);
error_reporting(E_ALL);

use Custome\Actions;
require_once("vendor/autoload.php");

$name = !empty($_REQUEST["name"]) ? $_REQUEST["name"] : "";
$email = !empty($_REQUEST["email"]) ? $_REQUEST["email"] : "";
$notes = !empty($_REQUEST["notes"]) ? $_REQUEST["notes"] : "";
$check = !empty($_REQUEST["check"]) ? true : false;

$actions = new Actions();
$actions->receiveDataSubscriber($name, $email, $check, $notes);

header('Content-type: application/json');
echo json_encode(true);

die();