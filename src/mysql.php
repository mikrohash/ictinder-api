<?php

function connect_to_db() {

    // replace with your data
    $username = "username";
    $password = "password";
    $database = "database";

    $mysqli = new mysqli("localhost", $username, $password);
    if ($mysqli->connect_error) {
        throw new Exception("Connection failed: " . $mysqli->connect_error);
    }
    $mysqli->select_db($database);
    return $mysqli;
}