"use strict"

Object.size = function(obj) {
    var size = 0, key;
    for (key in obj) {
        if (obj.hasOwnProperty(key)) size++;
    }
    return size;
};

function submitNewTicket(){
  var form = $("#ticket_form");
  var type = form.find("[name='type']").val();
  var source = form.find("[name='source']").val();
  var proxy = form.find("[name='proxy']").val();
  var target = form.find("[name='target']").val();
  var info = form.find("[name='info']").val();
  var infoUrl = form.find("[name='infoUrl']").val();
  var intentional = form.find("[name='intentional']").val();

  $.ajax({
    type:"post",
    url:"#",
    data:JSON.stringify({
      'command':"submit_new_ticket",
      'type': type,
      'source': source,
      'proxy': proxy,
      'target': target,
      'info': info,
      'infoUrl': infoUrl,
      'intentional': intentional
    }),
    dataType: "json",
    contentType: "application/json; charset=utf-8",
    success:function(resp){
      console.log("success " + JSON.stringify(resp));
      var ticket = JSON.parse(resp.post_response);
      if(ticket.godaddyId != null){
        $("#edit-tickets").removeClass("popup");
        setTimeout(function(){
          $("#edit-tickets").removeClass("outfront");
          $("#edit-tickets form").remove();
        }, 300);
        applyEditListeners();
      }else{

      }
    },
    error:function(resp){
      console.log("ERROR " + JSON.stringify(resp));
    }
  });
}

function submitNewComment(){
  var form = $("#submit_new_comment_form");
  var text = form.find("textarea").val();
  var ticketId = form.find("[name='ticket-id']").val();

  $.ajax({
    type:"post",
    url:"#",
    data:JSON.stringify({
      'command':"update_comment",
      'ticketId': ticketId,
      'text': text
    }),
    dataType: "json",
    contentType: "application/json; charset=utf-8",
    success:function(resp){
      var comment = JSON.parse(resp.post_response);
      $("#edit-tickets").show().addClass("outfront popup").find(".ticket-comments").append(comment.comment);
      $("#submit_new_comment_form textarea").val("");
      applyEditListeners();
    },
    error:function(resp){
      //console.log("ERROR " + JSON.stringify(resp));
    }
  });

}
function getSubmitForm(){
  $.ajax({
    type:"post",
    url:"#",
    data:JSON.stringify({
      'command':"get_submit_new_ticket_form"
    }),
    dataType: "json",
    contentType: "application/json; charset=utf-8",
    success:function(resp){
      var formData = JSON.parse(resp.post_response);
      $("#edit-tickets").show().addClass("outfront popup").find(".ticket-card").html(formData.form);
      applyEditListeners();
    },
    error:function(resp){
      //console.log("ERROR " + JSON.stringify(resp));
    }
  });
}

function applyEditListeners(){

  $(".open-ticket").off('click').on('click', function(e){
    e.preventDefault();
    getSubmitForm();
  });

  $(".ticket").off('click').on('click', function(){
    var id = $(this).attr("ticket-id");
    getTicketById(id, createEditTicketFactory);
  });

  $(".close-classic").off('click').on('click',function(){
    $("#edit-tickets").removeClass("popup");
    setTimeout(function(){
      $("#edit-tickets").removeClass("outfront");
      $("#edit-tickets form").remove();
    }, 300);

  });

  $(".close-search").off('click').on('click',function(){
    $("#search-tickets").removeClass("popup");
    setTimeout(function(){
      $("#search-tickets").removeClass("outfront");
      $("#search-tickets table").remove();
    }, 300);

  });

  $("#submit_new_comment_form").off('submit').on('submit',function(e){
    e.preventDefault();
    submitNewComment();
  });

  $("#closed-tickets .load-more").off('click').on('click',function(e){
    e.preventDefault();
    getClosedTickets();
  });

  $("#ticket_form input[name='source'], #ticket_form  input[name='proxy']").off('change').on('change',function(e){
    if( !(isUrl($(this).val()) || isIp($(this).val())) ){
      $("#ticket_form").find("[type='submit']").prop("disabled","disabled");
      $(".invalid-url").css({"display":"inline-block"});
    }else{
      $("#ticket_form").find("[type='submit']").prop("disabled","");
      $(".invalid-url").css({"display":"none"});
    }
  });

  $("#ticket_form").off('submit').on('submit',function(e){
    e.preventDefault();
    submitNewTicket();
  });

  $("#search").off('submit').on('submit',function(e){
    e.preventDefault();
    searchByTerm($("#search [name='term']").val());
  });
}

function serializeTicket(ticket){
  var str = "";
  for (var key in ticket) {
      if (str != "") {
          str += "&";
      }
      str += ticket + "=" + encodeURIComponent(ticket[key]);
  }
}

function createOpenTicketFactory(ticket){
  $('#open-tickets .ticket-container').append (
    '<tr class="ticket" ticket-id="'+ ticket.id +'">'+
      '<td>'+ticket.createdAt+'</td>'+
      '<td>'+ticket.type+'</td>'+
      '<td>'+ticket.source+'</td>'+
      '<td>'+ticket.target+'</td>'+
    '</tr>'
  );
}

function createClosedTicketFactory(ticket){
  $('#closed-tickets table').append(
    '<tr class="ticket" ticket-id="'+ ticket.id +'">'+
      '<td>'+ticket.createdAt+'</td>'+
      '<td>'+ticket.type+'</td>'+
      '<td>'+ticket.source+'</td>'+
      '<td>'+ticket.target+'</td>'+
    '</tr>'
  );
}

function createEditTicketFactory(ticket){
 //console.log("sending " + ticket.id);
  $.ajax({
    type:"post",
    url:"#",
    data: JSON.stringify({
      'command' : 'get_edit_ticket_form',
      'ticketId' : ticket.id.toString()
    }),
    dataType: "json",
    contentType: "application/json; charset=utf-8",
    success:function(resp){
     //console.log(JSON.stringify(resp));
      var response = JSON.parse(resp.post_response);
     //console.log(JSON.stringify(response));
      $("#edit-tickets").show().addClass("outfront popup").find(".ticket-card").html(response.form);
      applyEditListeners();
    },
    error:function(resp){
   //console.log("error: " + JSON.stringify(resp));
      //console.log("ERROR " + JSON.stringify(resp));
    }
  });
  updateTicketCounts();
}

function getTicketById(id, callback){

  $.ajax({
    type:"post",
    url:"#",
    data: JSON.stringify({
      'command' : 'get_ticket_by_id',
      'ticketId' : id
    }),
    dataType: "json",
    contentType: "application/json; charset=utf-8",
    success:function(resp){
      var response = JSON.parse(resp.post_response);
     //console.log(JSON.stringify(response));
      callback(response.ticket);
    },
    error:function(resp){
   //console.log("error: " + JSON.stringify(resp));
      //console.log("ERROR " + JSON.stringify(resp));
    }
  });
  updateTicketCounts();
}

function updateTicketCounts(){
  countOpenTickets();
  getTotalCountOfTickets();
}

function isIp(value){
  if(value==""){
    return true;
  }
  return (/^(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$/.test(value));
}

function isUrl(value){
  if(value==""){
    return true;
  }
  return (/^(?:(?:(?:https?|ftp):)?\/\/)(?:\S+(?::\S*)?@)?(?:(?!(?:10|127)(?:\.\d{1,3}){3})(?!(?:169\.254|192\.168)(?:\.\d{1,3}){2})(?!172\.(?:1[6-9]|2\d|3[0-1])(?:\.\d{1,3}){2})(?:[1-9]\d?|1\d\d|2[01]\d|22[0-3])(?:\.(?:1?\d{1,2}|2[0-4]\d|25[0-5])){2}(?:\.(?:[1-9]\d?|1\d\d|2[0-4]\d|25[0-4]))|(?:(?:[a-z\u00a1-\uffff0-9]-*)*[a-z\u00a1-\uffff0-9]+)(?:\.(?:[a-z\u00a1-\uffff0-9]-*)*[a-z\u00a1-\uffff0-9]+)*(?:\.(?:[a-z\u00a1-\uffff]{2,})).?)(?::\d{2,5})?(?:[/?#]\S*)?$/i.test( value ));
}

function getTotalCountOfTickets(){
  $.ajax({
    type:"post",
    url:"#",
    data: JSON.stringify({
      'command' : 'count_all_tickets'
    }),
    dataType: "json",
    contentType: "application/json; charset=utf-8",
    success:function(resp){
      var response = JSON.parse(resp.post_response);
      $(".total-ticket-count").text(response.total);
      //console.log(JSON.stringify(resp));
      //console.log("SUCCESS " + JSON.stringify(resp));
    },
    error:function(resp){
      //console.log("ERROR " + JSON.stringify(resp));
    }
  });
}

function searchByTerm(term){
  $.ajax({
    type:"post",
    url:"#",
    data: JSON.stringify({
      'command' : 'search_by_term',
      'searchTerm' : term
    }),
    dataType: "json",
    contentType: "application/json; charset=utf-8",
    success:function(resp){
      var tickets = JSON.parse(resp.post_response);
     //console.log(JSON.stringify(tickets));
      $("#search-tickets").show().addClass("outfront popup").find(".ticket-card").html(tickets.table);
      applyEditListeners();
    },
    error:function(resp){
     //console.log("ERROR " + JSON.stringify(resp));
    }
  });
}

function countOpenTickets(){
  $.ajax({
    type:"post",
    url:"#",
    data: JSON.stringify({
      'command' : 'count_open_tickets'
    }),
    dataType: "json",
    contentType: "application/json; charset=utf-8",
    success:function(resp){
      var response = JSON.parse(resp.post_response);
      $(".open-ticket-count").text(response.total);
      //console.log(JSON.stringify(resp));
      //console.log("SUCCESS " + JSON.stringify(resp));
    },
    error:function(resp){
      //console.log("ERROR " + JSON.stringify(resp));
    }
  });
}

function updateCurrentGodaddyTickets(){
  //console.log("starting to update tickets");
  $.ajax({
    type:"post",
    url:"#",
    data: JSON.stringify({
      'command' : 'update_godaddy_tickets'
    }),
    dataType: "json",
    contentType: "application/json; charset=utf-8",
    success:function(resp){
      //console.log("updated tickets");
    },
    error:function(resp){
      //console.log("ERROR " + JSON.stringify(resp));
    }
  });
}

function getClosedTickets(){
  var page = $("#closed-tickets .ticket-container").attr("page");
  $.ajax({
    type:"post",
    url:"#",
    data: JSON.stringify({
      'command' : 'fetch_closed_tickets',
      'page' : page
    }),
    dataType: "json",
    contentType: "application/json; charset=utf-8",
    success:function(resp){
      var response = JSON.parse(resp.post_response);
      $("#closed-tickets .ticket-container").append(response['closed_tickets']['results']);
      $("#closed-tickets .ticket-container").attr("page",response['closed_tickets']['next']);
      //console.log(JSON.stringify(resp));
      //console.log("SUCCESS " + JSON.stringify(resp));
    },
    error:function(resp){
      //console.log("ERROR " + JSON.stringify(resp));
    }
  });
}

function getOpenTickets(){
  $.ajax({
    type:"post",
    url:"#",
    data: JSON.stringify({
      'command' : 'fetch_open_tickets'
    }),
    dataType: "json",
    contentType: "application/json; charset=utf-8",
    success:function(resp){
      var open_tickets = JSON.parse(resp.post_response);
      //console.log(JSON.stringify(resp));
      //console.log("SUCCESS " + JSON.stringify(resp));
    },
    error:function(resp){
      //console.log("ERROR " + JSON.stringify(resp));
    }
  });
}

function getTicketUpdateByGoDaddyId(goDaddyId){

  $.ajax({
    type:"post",
    url:"#",
    data:JSON.stringify({
      'command' : 'fetch_ticket_by_godaddy_id',
      'id' : goDaddyId
    }),
    dataType: "json",
    contentType: "application/json; charset=utf-8",
    success:function(resp){
      var ticket = JSON.parse(resp.post_response);
      if(ticket.closed == "true"){
        createClosedTicketFactory(ticket);
      }else{
        createOpenTicketFactory(ticket);
      }
      applyEditListeners();
      updateTicketCounts();
    },
    error:function(resp){
      //console.log("ERROR " + JSON.stringify(resp));
    }
  });
}

var servicePid = 0;

$('document').ready(function(){
  applyEditListeners();
  updateTicketCounts();

  $(document).keyup(function(e) {
    if (e.keyCode == 27) { // escape key maps to keycode `27`-tickets
      if($("#search-tickets.popup").length > 0 && $("#edit-tickets.popup").length > 0){
        $("#edit-tickets .close-classic").trigger('click');
        $("#edit-tickets form").remove();
      }else if($("#search-tickets.popup").length > 0){
        $("#search-tickets .close-search").trigger('click');
        $("#search-tickets table").remove();
      }else if($("#edit-tickets.popup").length > 0){
        $("#edit-tickets .close-classic").trigger('click');
        $("#edit-tickets form").remove();
      }
    }
  });

  updateCurrentGodaddyTickets();
  servicePid = setInterval(function(){
    updateCurrentGodaddyTickets();
  }, 600000);
});
