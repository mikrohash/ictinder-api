<?php

incl_rel_once("DataBase.php", __FILE__);

global $db;
try {
    $db = new DataBase();
    $res = process_request();
} catch(Exception $exception) {
    $res = error($exception->getMessage());
}

header('Content-Type: application/json');
echo $res;


// ***** HELPERS *****

function get_POST_discord_id() {
    return get_POST("discord_id", '/^[0-9]{14,20}$/');
}

function get_POST_address() {
    return get_POST("address", '/^[a-zA-Z0-9.\-:]*:\d{1,5}$/');
}

/**
 * @throws Exception If POST argument value does not match expected pattern.
 */
function get_POST($name, $pattern = '//') {
    $value = $_POST[$name];
    if(!preg_match($pattern, $value))
        throw new Exception("POST parameter '$name' does not match pattern '$pattern'.");
    return $value;
}

function success($data = array()) {
    $data['success'] = true;
    return json_encode($data);
}

function error($message) {
    $data['success'] = false;
    $data['error'] = $message;
    return json_encode($data);
}