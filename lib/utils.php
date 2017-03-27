<?php
date_default_timezone_set ( "America/New_York" );


/**********************************************
* sanitize_fields($data)
* summary: run a filter to prevent harmful data
* from entering database
*
**********************************************/
function sanitize_fields($data){

  $data = (array)$data;

  $sanitized_data = array();

  foreach ($data as $key=>$value){
    if(!(is_string($value) || is_array($value) )){
      return null;
    }
    if(is_string($value)){
      $sanitized_key = filter_var($key, FILTER_SANITIZE_STRING);
      $sanitized_val = filter_var($value, FILTER_SANITIZE_STRING);
    }else if(is_array($value)){
      $sanitized_key = filter_var($key, FILTER_SANITIZE_STRING);
      $sanitized_val = sanitize_fields($value);
    }

    $sanitized_data[$sanitized_key] = $sanitized_val;
  }

  return $sanitized_data;
}


/**********************************************
* get_query_endpoint($config)
* summary: return GoDaddy API test or
* production endoint using the config
*
**********************************************/
function get_query_endpoint($config){
  return (($config['query_settings']['test_env']) ? "https://api.ote-godaddy.com/v1/abuse/tickets" : "https://api.godaddy.com/v1/abuse/tickets" );
}


/**********************************************
* get_ticket_by_godaddy_id($config, $godaddy_id)
* summary: fetches ticket from GoDaddy using
* GoDaddy's assigned ticket ID
*
**********************************************/
function get_ticket_by_godaddy_id($config, $godaddy_id){
  $ticket_endpoint = get_query_endpoint($config);
  $single_ticket_get_endpoint = $ticket_endpoint."/".$godaddy_id;

  //get all open tickets first
  $godaddy_single_ticket_response = curl_get_goddady_resource($config, $single_ticket_get_endpoint);
  $ticket = convert_godaddy_to_local_ticket($godaddy_single_ticket_response);

  $ticket['id'] = update_ticket($config, $ticket);

  return $ticket;
}


/**********************************************
* fetch_ticket_by_godaddy_id($config, $godaddy_id)
* summary: prints output from
* get_ticket_by_godaddy_id
*
**********************************************/
function fetch_ticket_by_godaddy_id($config, $godaddy_id){
  echo json_encode(get_ticket_by_godaddy_id($config, $godaddy_id));
}


/**********************************************
* post_new_ticket($config, $include)
* summary: POSTs ticket to GoDaddy then adds
* the ticket to the local database
*
**********************************************/
function post_new_ticket($config, $include){
  $endpoint = get_query_endpoint($config);
  $curl = new Curl\Curl();
  $curl->setHeader('Content-Type', 'application/json');
  $curl->setHeader('Authorization', 'sso-key '.$config['api_key'].":".$config['api_secret']);
  $curl->post($endpoint, json_encode($include, JSON_FORCE_OBJECT));
  $response = (array)json_decode($curl->response);
  $curl->close();

  get_ticket_by_godaddy_id($config, $response['u_number']); //save ticket to local database

  echo json_encode(array("godaddyId" => $response['u_number']));

}


/**********************************************
* get_tickets_from_godaddy_ids($config, $godaddy_ids)
* summary: returns tickets in local database format
* from given array of GoDaddy-format ids
*
**********************************************/
function get_tickets_from_godaddy_ids($config, $godaddy_ids){
  $tickets = array();

  foreach($godaddy_ids as $ticket_id){
    $ticket = get_ticket_by_godaddy_id($config, $ticket_id);
    $tickets[] = $ticket;
  }

  return $tickets;
}


/**********************************************
* fetch_ticket_by_id($config, $ticket_id)
* summary: prints output from get_ticket_by_id
*
**********************************************/
function fetch_ticket_by_id($config, $ticket_id){
  echo json_encode(array("ticket" => get_ticket_by_id($config, $ticket_id)));
}


/**********************************************
* count_all_tickets($config)
* summary: returns amount of total tickets in
* local database
*
**********************************************/
function count_all_tickets($config){
  // Create connection
  $conn = new mysqli($config['servername'], $config['username'], $config['password'], $config['database']);
  // Check connection
  if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
  }

  $sql = "SELECT COUNT(*) AS NUM_TICKETS FROM tickets;";

  $num_tickets_query = $conn->query($sql);

  $row = $num_tickets_query->fetch_assoc();

  $conn->close();
  return $row['NUM_TICKETS'];
}


/**********************************************
* count_open_tickets($config)
* summary: returns all open tickets in local
* database
*
**********************************************/
function count_open_tickets($config){
  // Create connection
  $conn = new mysqli($config['servername'], $config['username'], $config['password'], $config['database']);
  // Check connection
  if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
  }

  $sql = "SELECT COUNT(*) AS NUM_TICKETS FROM tickets WHERE closed='false';";

  $num_tickets_query = $conn->query($sql);

  $row = $num_tickets_query->fetch_assoc();

  $conn->close();
  return $row['NUM_TICKETS'];
}


/**********************************************
* count_closed_tickets($config)
* summary: returns all closed tickets in local
* database
*
**********************************************/
function count_closed_tickets($config){
  // Create connection
  $conn = new mysqli($config['servername'], $config['username'], $config['password'], $config['database']);

  if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
  }

  $sql = "SELECT COUNT(*) AS NUM_TICKETS FROM tickets WHERE closed=true;";

  $num_tickets_query = $conn->query($sql);

  $row = $num_tickets_query->fetch_assoc();

  $conn->close();
  return $row['NUM_TICKETS'];
}


/**********************************************
* fetch_count_all_tickets($config)
* summary: prints output from count_all_tickets
*
**********************************************/
function fetch_count_all_tickets($config){
  echo json_encode(array("total" => count_all_tickets($config)));
}


/**********************************************
* fetch_count_open_tickets($config)
* summary: prints output from count_open_tickets
*
**********************************************/
function fetch_count_open_tickets($config){
  echo json_encode(array("total" => count_open_tickets($config)));
}


/**********************************************
* fetch_count_closed_tickets($config)
* summary: prints output from count_closed_tickets
*
**********************************************/
function fetch_count_closed_tickets($config){
  echo json_encode(array("total" => count_closed_tickets($config)));
}


/**********************************************
* get_ticket_by_id($config, $ticket_id)
* summary: returns ticket properties from given
* local-format ticket ID
*
**********************************************/
function get_ticket_by_id($config, $ticket_id){

  $ticket_id = (string)$ticket_id;
  // Create connection
  $conn = new mysqli($config['servername'], $config['username'], $config['password'], $config['database']);
  // Check connection
  if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
  }

  $sql = "SELECT * FROM tickets WHERE id=".$ticket_id.";";

  $ticket_data = $conn->query($sql);

  $ticket = array();
  while($ticket_properties = $ticket_data->fetch_assoc()){
    foreach($ticket_properties as $key => $value){
      $ticket[$key] = $value;
    }
  }

  $conn->close();
  return $ticket;
}


/**********************************************
* get_open_tickets($config, $pagination = 0)
* summary: return either all or paginated
* open tickets
*
**********************************************/
function get_open_tickets($config, $pagination = 0){
  // Create connection
  $conn = new mysqli($config['servername'], $config['username'], $config['password'], $config['database']);
  // Check connection
  if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
  }

  $pagination_offset = ($pagination*$config['query_settings']['pagination'])+1;

  $sql = "SELECT * FROM tickets WHERE closed='false' ORDER BY createdAt DESC limit $pagination_offset, ".$config['query_settings']['pagination'].";";

  $ticket_data = $conn->query($sql);
  $next_pagination = (($ticket_data->num_rows > 0) ? ($pagination + 1) : $pagination);

  $tickets = array();
  while($ticket_properties = $ticket_data->fetch_assoc()){
    $ticket = array();
    foreach($ticket_properties as $key => $value){
      $ticket[$key] = $value;
    }
    $tickets[] = $ticket;
  }

  $conn->close();


  $open_tickets_paginated = array(
    "results" => $tickets,
    "next" => $next_pagination
  );
  return $open_tickets_paginated;
}


/**********************************************
* get_closed_tickets($config, $pagination = 0)
* summary: return either all or paginated
* closed tickets
*
**********************************************/
function get_closed_tickets($config, $pagination = 0){
  // Create connection
  $conn = new mysqli($config['servername'], $config['username'], $config['password'], $config['database']);
  // Check connection
  if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
  }

  $pagination_offset = ($pagination*$config['query_settings']['pagination'])+1;

  $sql = "SELECT * FROM tickets WHERE closed='true' ORDER BY createdAt DESC LIMIT $pagination_offset, ".$config['query_settings']['pagination'].";";

  $ticket_data = $conn->query($sql);
  $next_pagination = (($ticket_data->num_rows > 0) ? ($pagination + 1) : $pagination);

  $tickets = array();
  while($ticket_properties = $ticket_data->fetch_assoc()){
    $ticket = array();
    foreach($ticket_properties as $key => $value){
      $ticket[$key] = $value;
    }
    $tickets[] = $ticket;
  }

  $conn->close();

  $closed_tickets_paginated = array(
    "results" => $tickets,
    "next" => $next_pagination
  );
  return $closed_tickets_paginated;
}


/**********************************************
* get_table_output($tickets)
* summary: return HTML formatted ticket data
* for an HTML table row
*
**********************************************/
function get_table_output($tickets){
  ob_start();
  foreach($tickets as $ticket):
  ?>
  <tr class="ticket" ticket-id="<?php echo $ticket['id']; ?>">
    <td><?php echo $ticket['createdAt']; ?></td>
    <td><?php echo $ticket['type']; ?></td>
    <td><?php echo $ticket['source']; ?></td>
    <td><?php echo $ticket['target']; ?></td>
  </tr>
  <?php
  endforeach;
  $table_rows = ob_get_clean();
  return $table_rows;
}


/**********************************************
* fetch_open_tickets($config, $pagination = 0)
* summary: returns open ticket data in HTML
* table row format
*
**********************************************/
function fetch_open_tickets($config, $pagination = 0){
  $paginated_tickets = get_open_tickets($config, $pagination);
  $paginated_tickets['results'] = get_table_output($paginated_tickets['results']);
  echo json_encode(array("open_tickets" => $paginated_tickets));
}


/**********************************************
* fetch_closed_tickets($config, $pagination = 0)
* summary: returns closed ticket data in HTML
* table row format
*
**********************************************/
function fetch_closed_tickets($config, $pagination = 0){
  $paginated_tickets = get_closed_tickets($config, $pagination);
  $paginated_tickets['results'] = get_table_output($paginated_tickets['results']);
  echo json_encode(array("closed_tickets" => $paginated_tickets));
}


/**********************************************
* get_ticket_form_by_id($config, $ticket_id)
* summary: returns an HTML form to review ticket
* information
*
**********************************************/
function get_ticket_form_by_id($config, $ticket_id){

  $ticket_id = (string)$ticket_id;
  // Create connection
  $conn = new mysqli($config['servername'], $config['username'], $config['password'], $config['database']);

  if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
  }

  $sql = "SELECT * FROM tickets WHERE id=".$ticket_id.";";

  $ticket_data = $conn->query($sql);
  ob_start();

  if($ticket_data->num_rows > 0):
    $row = $ticket_data->fetch_assoc();
    ?>
    <form id="ticket_form" method="post" action="" ticket-id="<?php echo $ticket_id; ?>">
     <a href="#" class="close-classic"></a>
    <label>
      <span>Date created:</span>
      <div class="ticket-detail">
        <?php echo $row['createdAt']; ?>
      </div>
    </label>
     <label>
       <span>GoDaddy ID:</span>
       <div class="ticket-detail">
         <?php echo $row['godaddyTicketId']; ?>
       </div>
     </label>
     <label>
       <span>Abuse Type:</span>
       <div class="ticket-detail">
         <?php echo $row['type']; ?>
       </div>
     </label>
      <label>
        <span>Abuse Source or IP:</span>
        <div class="ticket-detail">
          <?php echo $row['sourceDomainorIp']; ?>
        </div>
      </label>
      <label>
        <span>Abuse Proxy:</span>
        <div class="ticket-detail">
          <?php echo ($row['proxy'] != "" ? $row['proxy'] : "None" ); ?>
        </div>
      </label>
      <label>
        <span>Target of Abuse:</span>
        <div class="ticket-detail">
          <?php echo ($row['target'] != "" ? $row['target'] : "None" ); ?>
        </div>
      </label>
      <label>
        <span>Additional Info Given to GoDaddy:</span>
        <div class="ticket-detail">
          <textarea disabled><?php echo $row['info']; ?></textarea>
        </div>
      </label>
      <label>
        <span>Reference URL for GoDaddy Reporter:</span>
        <div class="ticket-detail">
          <?php echo ($row['infoUrl'] != "" ? $row['infoUrl'] : "None" ); ?>
        </div>
      </label>
      <label>
        <span>Is Abuse Intentional?</span>
        <div class="ticket-detail">
          <?php echo ($row['intentional'] != "" ? $row['intentional'] : "Not Specified" ); ?>
        </div>
      </label>
      </form>

      <label><span>Display Comments</span></label>

    <?php

    $sql = "SELECT * FROM ticketComments WHERE ticketId=".$ticket_id.";";
    $comment_data = $conn->query($sql);
    ?>
    <div class="comment_area">
      <ul class="ticket-comments">
      <?php
      if($comment_data->num_rows > 0):
        while($row = $comment_data->fetch_assoc()):
          echo get_comment_list_item($row);
        endwhile;
      endif;
      ?>
      </ul>
      <?php
      ?>
      <form id="submit_new_comment_form" method="post" action="#">
        <label>New comment:</label>
        <textarea name="body"></textarea>
        <input type="hidden" name="ticket-id" value="<?php echo $ticket_id; ?>"/>
        <br/>
        <br/>
        <button type="submit">Submit</button>
      </form>
    </div>
    <?php

  endif;
  $conn->close();
  $form = ob_get_clean();
  return $form;
}


/**********************************************
* get_comment_list_item($comment_data)
* summary: returns ticket comment data
* in HTML list item format
*
**********************************************/
function get_comment_list_item($comment_data){
  ob_start();
  ?>
    <li comment-id="<?php echo $comment_data['id']; ?>">
      <div class="comment-date"><?php echo ((strtotime($comment_data['created']) > strtotime($comment_data['lastEdited'])) ? $comment_data['created'] : $comment_data['lastEdited']) ; ?></div>
      <div class="comment-body"><?php echo $comment_data['body']; ?></div>
    </li>
  <?php
  return ob_get_clean();
}


/**********************************************
* fetch_edit_ticket_form($config, $ticket_id)
* summary: prints output from
* get_ticket_form_by_id
*
**********************************************/
function fetch_edit_ticket_form($config, $ticket_id){
  $form = get_ticket_form_by_id($config, $ticket_id);
  echo json_encode(array("form" => $form));
}


/**********************************************
* fetch_submit_new_ticket_form()
* summary: returns an HTML form to submit a
* new ticket to GoDaddy information
*
**********************************************/
function fetch_submit_new_ticket_form(){
  ob_start(); ?>
  <form id="ticket_form" method="post" action="">
   <a href="#" class="close-classic"></a>
  <label for="">Abuse Type:
    <select class="parameter" name="type" required>
      <option selected="" value=""></option>
      <option value="A_RECORD">A_RECORD</option>
      <option value="CHILD_ABUSE">CHILD_ABUSE</option>
      <option value="CONTENT">CONTENT</option>
      <option value="FRAUD_WIRE">FRAUD_WIRE</option>
      <option value="IP_BLOCK">IP_BLOCK</option>
      <option value="MALWARE">MALWARE</option>
      <option value="NETWORK_ABUSE">NETWORK_ABUSE</option>
      <option value="PHISHING">PHISHING</option>
      <option value="SPAM">SPAM</option>
    </select>
  </label>
  <label for="editTicketSource">Abuse Source or IP: *<span class="invalid-url">Please enter a full URL with http:// or https://</span>
    <input id="editTicketSource" name="source" type="text" />
  </label>
  <label for="editTicketProxy">Abuse Proxy:
    <input id="editTicketProxy" name="proxy" type="text" />
  </label>
  <label for="editTicketTarget">Target of Abuse:
    <input id="editTicketTarget" name="target" type="text" />
  </label>
  <label for="editTicketInfo">Any Additional Info for Reporter?
    <textarea id="editTicketInfo" name="info" type="text"></textarea>
  </label>
  <label for="editTicketInfoUrl">Reference URL for GoDaddy Reporter:
    <input id="editTicketInfoUrl" name="infoUrl" type="text" />
  </label>
  <label>Is Abuse Intentional?
    <label>Yes
      <input id="intentional_yes" type="radio" name="intentional" value="true" />
    </label>
    <label>No
      <input id="intentional_no" type="radio" name="intentional" value="false" />
    </label>
  </label>
  <button type="submit">Save</button>
</form>
  <?php
  $form = json_encode(array("form" => ob_get_clean()));

  echo $form;
}


/**********************************************
* get_comment_by_id($config, $id)
* summary: returns comment data given
* comment ID format
*
**********************************************/
function get_comment_by_id($config, $id){
  // Create connection
  $conn = new mysqli($config['servername'], $config['username'], $config['password'], $config['database']);
  // Check connection
  if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
  }

  $sql = "SELECT * FROM ticketComments WHERE id=".$id.";";

  $result = $conn->query($sql);

  if (isset($result->num_rows) && $result->num_rows > 0) {
    $comment = $result->fetch_assoc();
  }

  $conn->close();

  return $comment;

}


/**********************************************
* update_comment($config, $include)
* summary: Creates/updates comment with given
* information. Attachments have not been fully
* implemented
*
**********************************************/
function update_comment($config, $include){
  // Create connection
  $conn = new mysqli($config['servername'], $config['username'], $config['password'], $config['database']);
  // Check connection
  if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
  }

  $include['id'] = isset($include['id']) ? $include['id'] : "";
  $include['lastEdited'] = date("U");  //get mysql timstamp
  $include['attachment'] = isset($include['attachment'])  ? $include['attachment'] : "";//processattachment
  $include['text'] = isset($include['text']) ? $include['text'] : "";

  $sql = "INSERT INTO ticketComments ".
  "(id, lastEdited, ticketId, attachmentLocation, body) ".
  "VALUES (?,FROM_UNIXTIME(?),?,?,?) ".
  "ON DUPLICATE KEY UPDATE ".
    "lastEdited = VALUES(lastEdited),".
    "body = VALUES(body);";

  $stmt = $conn->prepare($sql);

  $stmt->bind_param("sssss", $include['id'], $include['lastEdited'], $include['ticket_id'], $include['attachment'], $include['text']);

  $stmt->execute();

  $conn->close();

  return $stmt->insert_id;
}


/**********************************************
* convert_godaddy_to_local_ticket($ticket)
* summary: returns a key-converted array
* from GoDaddy keys to local keys
*
**********************************************/
function convert_godaddy_to_local_ticket($ticket){
  $data = array();

  $data['title'] = (isset($ticket['title']) ? $ticket['title'] : "" );
  $data['godaddyTicketId'] = (isset($ticket['ticketId']) ? $ticket['ticketId'] : "" );
  $data['type'] = (isset($ticket['type']) ? $ticket['type'] : "" );
  $data['closed'] = (isset($ticket['closed']) ? $ticket['closed'] : "" );
  $data['source'] = (isset($ticket['source']) ? $ticket['source'] : "" );
  $data['sourceDomainorIp'] = (isset($ticket['sourceDomainOrIp']) ? $ticket['sourceDomainOrIp'] : "" );
  $data['proxy'] = (isset($ticket['proxy']) ? $ticket['proxy'] : "" );
  $data['target'] = (isset($ticket['target']) ? $ticket['target'] : "" );
  $data['reporter'] = (isset($ticket['reporter']) ? $ticket['reporter'] : "" );
  $data['createdAt'] = (isset($ticket['createdAt']) ? $ticket['createdAt'] : "" );
  $data['closedAt'] = (isset($ticket['closedAt']) ? $ticket['closedAt'] : "" );
  $data['intentional'] = (isset($ticket['intentional']) ? $ticket['intentional'] : "" );
  $data['info'] = (isset($ticket['info']) ? $ticket['info'] : "" );
  $data['infoUrl'] = (isset($ticket['infoUrl']) ? $ticket['infoUrl'] : "" );

  return $data;
}


/**********************************************
* is_database_schema_created($config)
* summary: checks for local database schema existance.
*
**********************************************/
function is_database_schema_created($config){
    $conn = new mysqli($config['servername'], $config['username'], $config['password']);

    // Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    $sql = "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '". $config['database']."'" ;

    $result = $conn->query($sql);

    if (isset($result->num_rows) && $result->num_rows > 0) {

      $sql = "SHOW TABLES FROM ".$config['database'];

      $result = $conn->query($sql);

      if (isset($result->num_rows) && $result->num_rows > 1) {
        return true;
      }else{
        return false;
      }
    } else {
      return false;
    }
    $conn->close();
}


/**********************************************
* create_database_schema($config)
* summary: Creates local database schema
*
**********************************************/
function create_database_schema($config){
  // Create connection
  $conn = new mysqli($config['servername'], $config['username'], $config['password'], $config['database']);
  // Check connection
  if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
  }

  $sql ="CREATE TABLE IF NOT EXISTS `tickets` (
    `id` mediumint(8) unsigned NOT NULL auto_increment,
    `title` varchar(255),
    `godaddyTicketId` varchar(255) NOT NULL UNIQUE,
    `type` varchar(255),
    `closed` varchar(255),
    `source` varchar(255),
    `sourceDomainorIp` varchar(255),
    `proxy` varchar(255),
    `target` varchar(255),
    `reporter` varchar(255),
    `createdAt` varchar(255),
    `closedAt` varchar(255),
    `intentional` varchar(255),
    `info` varchar(255),
    `infoUrl` varchar(255),
    PRIMARY KEY (`id`)
  ) AUTO_INCREMENT=1;";

  $result = $conn->query($sql);

  $sql = "CREATE TABLE IF NOT EXISTS `ticketComments` (
    `id` mediumint(8) unsigned NOT NULL auto_increment,
    `created` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `lastEdited` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `ticketId` varchar(255) NOT NULL,
    `attachmentLocation` varchar(255),
    `body` varchar(255),
    PRIMARY KEY (`id`)
  ) AUTO_INCREMENT=1;";

  $result = $conn->query($sql);

  $conn->close();
}


/**********************************************
* fetch_search_tickets_with_term($config, $search_term)
* summary: prints HTML table of tickets that have
* similarity with the search term
*
**********************************************/
function fetch_search_tickets_with_term($config, $search_term){
  $tickets = search_tickets_with_term($config, $search_term);
  ob_start();
  ?>
  <a href="#" class="close-search"></a>
  <table>
    <tr>
      <th>Date</th>
      <th>Type</th>
      <th>Source</th>
      <th>Target</th>
    </tr>
    <?php
    foreach($tickets as $ticket):
      ?>
      <tr class="ticket" ticket-id="<?php echo $ticket['id']; ?>">
        <td><?php echo $ticket['createdAt']; ?></td>
        <td><?php echo $ticket['type']; ?></td>
        <td><?php echo $ticket['source']; ?></td>
        <td><?php echo $ticket['target']; ?></td>
      </tr>
      <?php
    endforeach;
    ?>
  </table>
  <?php
  $table = ob_get_clean();
  echo json_encode(array("table" => $table));
}


/**********************************************
* search_tickets_with_term($config, $search_term)
* summary: returns tickets with any data that
* contain the search term
*
**********************************************/
function search_tickets_with_term($config, $search_term){
  // Create connection
  $conn = new mysqli($config['servername'], $config['username'], $config['password'], $config['database']);
  // Check connection
  if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
  }

  $sql = "SELECT DISTINCT(tickets.id) FROM tickets LEFT JOIN ticketComments on tickets.id = ticketComments.ticketId WHERE (ticketComments.body LIKE '%{$search_term}%') OR (tickets.godaddyTicketId  LIKE '%{$search_term}%') OR (tickets.type  LIKE '%{$search_term}%') OR (tickets.source  LIKE '%{$search_term}%') OR (tickets.sourceDomainorIp  LIKE '%{$search_term}%') OR (tickets.proxy  LIKE '%{$search_term}%') OR (tickets.target  LIKE '%{$search_term}%') OR (tickets.createdAt  LIKE '%{$search_term}%') OR (tickets.closedAt  LIKE '%{$search_term}%') OR (tickets.info  LIKE '%{$search_term}%') OR (tickets.infoUrl  LIKE '%{$search_term}%');";

  $result = $conn->query($sql);

  $tickets = array();
  while($row = $result->fetch_assoc()){
    $tickets[] = get_ticket_by_id($config,$row['id']);
  }

  $conn->close();

  return $tickets;

}


/**********************************************
* update_ticket($config,$data)
* summary: Creates/updates ticket in local database
*
**********************************************/
function update_ticket($config,$data){
  // Create connection
  $conn = new mysqli($config['servername'], $config['username'], $config['password'], $config['database']);
  // Check connection
  if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
  }

  $sql = "INSERT INTO tickets ".
    "(godaddyTicketId, type, closed, source, sourceDomainorIp, proxy, target, reporter, createdAt, closedAt, intentional, info, infoUrl) ".
  "VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?) ".
  "ON DUPLICATE KEY UPDATE ".
    "type = VALUES(type),".
    "closed = VALUES(closed),".
    "source = VALUES(source),".
    "sourceDomainorIp = VALUES(sourceDomainorIp),".
    "proxy = VALUES(proxy),".
    "target = VALUES(target),".
    "reporter = VALUES(reporter),".
    "createdAt = VALUES(createdAt),".
    "closedAt = VALUES(closedAt),".
    "intentional = VALUES(intentional),".
    "info = VALUES(info),".
    "infoUrl = VALUES(infoUrl);";

    $stmt = $conn->prepare($sql);

    $stmt->bind_param("sssssssssssss",$data['godaddyTicketId'],$data['type'],$data['closed'],$data['source'],$data['sourceDomainorIp'],$data['proxy'],$data['target'],$data['reporter'],$data['createdAt'],$data['closedAt'],$data['intentional'],$data['info'],$data['infoUrl']);

    $stmt->execute();

    $conn->close();

    return $stmt->insert_id;
}


/**********************************************
* update_from_godaddy_tickets($config)
* summary: find updates to GoDaddy tickets and
* save to local database (interval based on client)
*
**********************************************/
function update_from_godaddy_tickets($config){
  $date = new DateTime();
  $date->modify('-14 days');
  $date->format("Y-m-d H:i:s");
  $ticket_list_endpoint = get_query_endpoint($config)."?createdStart=".$date->format("Y-m-d H:i:s");
  $godaddy_ticket_ids_response = curl_get_goddady_resource($config, $ticket_list_endpoint);
  $godaddy_ticket_ids = $godaddy_ticket_ids_response['ticket_ids'];

  $all_godaddy_tickets = get_tickets_from_godaddy_ids($config, $godaddy_ticket_ids);
  echo json_encode(array("message" => "tickets updated"));
}


/**********************************************
* curl_get_goddady_resource($config, $endpoint)
* summary: returns a GET request to the GoDaddy
* API with given endpoint
*
**********************************************/
function curl_get_goddady_resource($config, $endpoint){
  $curl = new Curl\Curl();
  $curl->setHeader('Content-Type', 'application/json');
  $curl->setHeader('Authorization', 'sso-key '.$config['api_key'].":".$config['api_secret']);
  $curl->get($endpoint);
  $response = (array)json_decode($curl->response);
  $curl->close();
  return $response;
}
?>
