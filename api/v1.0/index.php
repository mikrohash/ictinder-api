<?php if(!function_exists("incl_rel_once")) include_once "../../src/include.php";

incl_rel_once("../../src/DataBase.php", __FILE__);

try {
    $db = new DataBase();
    $res = process_request();
} catch(Exception $exception) {
    $res = error($exception->getMessage());
}

header('Content-Type: application/json');
echo $res;

// ***** PROCESSES *****

/**
 * @throws Exception If anything goes wrong
 */
function process_request() {
    global $db;

    $_POST['discord_id'] = "092348283430234";
    $_POST['password'] = "test";
    $_POST['address'] = "ict-example.org:1340";
    $_POST['stats'] = json_encode(array("ict-example.org:1339" => array("all" => 13)));

    $node = determine_node();
    $db->create_api_call($node);
    process_stats($node);
    $peers = $db->get_peer_addresses($node);
    return success(array("neighbors" => $peers));
}
/**
 * @throws Exception If invalid $_POST argument.
 */
function process_stats($node) {
    global $db;

    $all_stats = json_decode(get_POST("stats", '/^.*$/'));

    foreach ($all_stats AS $nb_address => $nb_stats) {
        $nb_stats = json_decode(json_encode($nb_stats), JSON_NUMERIC_CHECK);
        $nb = $db->find_node_by_address($nb_address);
        $db->create_stats($node, $nb, $nb_stats);
    }
}

// ***** OPERATIONS *****

/**
 * @throws Exception If account authentication fails or node cannot be registered.
 */
function determine_node() {
    global $db;
    $account = determine_account();
    $address = get_POST("address", '/^[a-zA-Z0-9.\-:]*:\d{1,5}$/');
    $node = $db->find_node_by_address($address);
    if($node == -1)
        $node = register_node($account, $address);
    return $node;
}


/**
 * @throws Exception If node cannot be registered.
 */
function register_node($account, $address) {
    global $db;
    if($db->count_free_slots($account) <= 0)
        throw new Exception("You cannot register more nodes. Please delete others first.");
    $db->create_node($account, $address);
    $node = $db->find_node_by_address($address);
    if($node == -1)
        throw new Exception("Unexpected exception: could not create node.");
    $db->peering_insert_middleman($node);
    $db->make_peers($node);
    return $node;
}

/**
 * @throws Exception If account authentication fails.
 */
function determine_account() {
    global $db;
    $discord_id = get_POST("discord_id", '/^[0-9]{14,20}$/');
    $password = get_POST("password", '/^.+$/');

    $account = $db->authenticate_account($discord_id, $password);
    if($account == -1)
        throw new Exception("Authentication failed.");
    return $account;
}

// ***** HELPERS *****

/**
 * @throws Exception If POST argument value does not match expected pattern.
 */
function get_POST($name, $pattern) {
    $value = $_POST[$name];
    if(!preg_match($pattern, $value))
        throw new Exception("POST parameter '$name' does not match pattern '$pattern'.");
    return $value;
}

function success($data) {
    $data['success'] = true;
    return json_encode($data);
}

function error($message) {
    $data['success'] = false;
    $data['error'] = $message;
    return json_encode($data);
}