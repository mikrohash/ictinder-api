<?php if(!function_exists("incl_rel_once")) include_once "../../src/include.php";

incl_rel_once("../../src/api.php", __FILE__);

// ***** PROCESSES *****

/**
 * @throws Exception If anything goes wrong
 */
function process_request() {
    global $db;

    $_POST['discord_id'] = "092348283430234";
    $_POST['password'] = "test";
    $_POST['address'] = "ict-example.org:1340";
    $_POST['stats'] = json_encode(array("ict-example.org:1340" => array("all" => 13)));

    $node = determine_node();
    $db->create_api_call($node);

    $timeout = $db->get_node_timeout($node);
    if($timeout > time())
        throw new Exception("Node is on timeout for ".($timeout-time())." more seconds.");

    process_stats($node);
    $peers = $db->get_peer_addresses($node);
    return success(array("neighbors" => $peers));
}
/**
 * @throws Exception If invalid $_POST argument.
 */
function process_stats($node) {
    global $db;

    $all_stats = json_decode(get_POST("stats"));

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
    $address = get_POST_address();
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
    $discord_id = get_POST_discord_id();
    $password = get_POST("password");

    $account = $db->authenticate_account($discord_id, $password);
    if($account == -1)
        throw new Exception("Authentication failed.");
    return $account;
}