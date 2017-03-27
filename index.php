<?php

require  __DIR__ .'/lib/config.php';
require  __DIR__ .'/lib/utils.php';
require __DIR__ . '/lib/vendor/autoload.php';

use \Curl\Curl;

ob_start();
//run database table creation scripts
if(!is_database_schema_created($config)){
  create_database_schema($config);
  update_from_godaddy_tickets($config);
}
ob_clean();

//read contents for POST data
$input = file_get_contents('php://input');
$data_input = json_decode($input);

//control block
if (($_SERVER['REQUEST_METHOD'] === 'POST') && isset($data_input)  && (!empty($data_input->command)) ):

  ob_start();

  $data_input = sanitize_fields($data_input);

  $reponse = array();

  switch ($data_input['command']){
    case 'submit_new_ticket': //posts new ticket to GoDaddy and saves into local database
      //ensure default values for new ticket properties
      $include = array();

      if(isset($data_input['type']) && $data_input['type'] != ""){
        $include["type"] = $data_input["type"];
      }
      if(isset($data_input['source']) && $data_input['source'] != ""){
        $include["source"] = $data_input["source"];
      }
      if(isset($data_input['proxy']) && $data_input['proxy'] != ""){
        $include["proxy"] = $data_input["proxy"];
      }
      if(isset($data_input['target']) && $data_input['target'] != ""){
        $include["target"] = $data_input["target"];
      }
      if(isset($data_input['info']) && $data_input['info'] != ""){
        $include["info"] = $data_input["info"];
      }
      if(isset($data_input['infoUrl']) && $data_input['infoUrl'] != ""){
        $include["infoUrl"] = $data_input["infoUrl"];
      }
      if(isset($data_input['intentional']) && $data_input['intentional'] != ""){
        $include["intentional"] = $data_input["intentional"];
      }

      post_new_ticket($config, $include);
      break;
    case 'update_comment': //create and update comments
      //require data from comment post
      if(!(isset($data_input['text']) || isset($data_input['attachment']) || !isset($data_input['ticketId']))){
        die();
      }
      $include = array();
      $include['ticket_id'] = $data_input['ticketId'];
      if (isset($data_input['id'])){
        $include['id'] = $data_input['id'];
      }
      if (isset($data_input['text'])){
        $include['text'] = $data_input['text'];
      }
      if (isset($data_input['attachment'])){
        $include['attachment'] = $data_input['attachment'];
      }
      $last_index = update_comment($config, $include);
      if($last_index != null){
        $comment = get_comment_by_id($config, $last_index);
        echo json_encode(array("comment" => get_comment_list_item($comment)));
      }
      break;
    case 'search_by_term':
      fetch_search_tickets_with_term($config, $data_input['searchTerm']);
      break;
    case 'update_godaddy_tickets':
      update_from_godaddy_tickets($config);
      break;
    case 'count_all_tickets':
      fetch_count_all_tickets($config);
      break;
    case 'count_closed_tickets':
      fetch_count_closed_tickets($config);
      break;
    case 'count_open_tickets':
      fetch_count_open_tickets($config);
      break;
    case 'fetch_ticket_by_godaddy_id':
      fetch_ticket_by_godaddy_id($config, $data_input['id']);
      break;
    case 'fetch_open_tickets':
      if(isset($data_input['page'])){
        fetch_open_tickets($config, $data_input['page']);
      }else{
        fetch_open_tickets($config);
      }
      break;
    case 'fetch_closed_tickets':
      if(isset($data_input['page'])){
        fetch_closed_tickets($config, $data_input['page']);
      }else{
        fetch_closed_tickets($config);
      }
      break;
    case 'get_edit_ticket_form':
      if(!is_numeric($data_input['ticketId'])){
        die();
      }
      fetch_edit_ticket_form($config, $data_input['ticketId']);
      break;
    case 'get_ticket_by_id':
      fetch_ticket_by_id($config, $data_input['ticketId']);
      break;
    case 'get_submit_new_ticket_form':
      fetch_submit_new_ticket_form();
      break;
    default:
  }

  $message = ob_get_clean();

  header('Content-Type: application/json');
  echo json_encode(array("post_response" => $message));

else:
ob_start();

//GET for client
?>
<!DOCTYPE html>
<html>
<head>
  <title>GoDaddy Abuse Ticket Dashboard</title>
  <link rel="stylesheet" type="text/css" href="lib/index.css"></script>
  <link href="https://fonts.googleapis.com/css?family=Roboto+Slab:300,400" rel="stylesheet">
  <script src="lib/vendor/jquery/jquery.min.js"></script>
  <script src="lib/tickets.js"></script>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>
  <div>
    <div id="ticket-dashboard" >
      <div class="application-header">
        <h1 class="application-header-title">Abuse Ticket Dashboard</h1>
        <img class="godaddy_logo" src="img/godaddy_logo.png" />
      </div>
      <div class="search-bar">
        <div class="search-container">
          <form id ="search" method="post" action="#">
            <input name="term" type="text" />
            <button type="submit">Search</button>
          </form>
        </div>
      </div>
      <div id="open-tickets" class="section">
        <input id="display_section_open" class="section_collapse" type="checkbox" checked="checked" />
        <label for="display_section_open">
          <div class="section-header">
            <div class="section-header-content">
              <div>
                <h2 class="section-header-title">Open Tickets</h2>
                <div class="ticket-count">
                  <span class="open-ticket-count"></span> of <span class="total-ticket-count"></span> open
                </div>
              </div>
              <a href="#" class="open-ticket">+</a>
            </div>
          </div>
        </label>
        <table class="ticket-container">
          <tr>
            <th>Date</th>
            <th>Type</th>
            <th>Source</th>
            <th>Target</th>
          </tr>
            <?php
            $open_tickets = get_open_tickets($config, 0);
            echo get_table_output($open_tickets['results']);
            ?>
        </table>
      </div>
      <div id="closed-tickets" class="section">
        <input id="display_section_closed" class="section_collapse" type="checkbox" checked="checked" />
        <label for="display_section_closed">
        <div class="section-header">
          <div class="section-header-content">
              <h2 class="section-header-title">Closed Tickets</h2>
          </div>
        </div>
      </label>
        <table class="ticket-container" page="1">
          <tr>
            <th>Date</th>
            <th>Type</th>
            <th>Source</th>
            <th>Target</th>
          </tr>
            <?php
            $closed_tickets = get_closed_tickets($config, 0);
            echo get_table_output($closed_tickets['results']);
            ?>
        </table>
        <div class="load-more">Load Older</div>
      <!-- tickets in list format -->
      </div>
      <div id="application-footer">
      <!-- Author & copyright -->
      </div>

    </div>

      <div id="edit-tickets">
        <div class="ticket-card">
        </div>
      </div>
      <div id="search-tickets">
        <div class="ticket-card">

        </div>
      </div>
  </div>


</body>
<html>

<?php
$message = ob_get_clean();
echo $message;

endif;

?>
