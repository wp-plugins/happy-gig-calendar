//$().ready(function() {
jQuery(document).ready(function( $ ) {	

/*
	$(".datepicker").datepicker({ // Show the 'close' and 'today' buttons
		showButtonPanel: true,
		closeText: objectL10n.closeText,
		currentText: objectL10n.currentText,
		monthNames: objectL10n.monthNames,
		monthNamesShort: objectL10n.monthNamesShort,
		dayNames: objectL10n.dayNames,
		dayNamesShort: objectL10n.dayNamesShort,
		dayNamesMin: objectL10n.dayNamesMin,
		firstDay: objectL10n.firstDay,
		isRTL: objectL10n.isRTL,
		dateFormat: 'yy-mm-dd',
		onSelect: function(dates) { 
			if ($("#multi").is(':checked')) {
				// check the end day is greater
				if ($("#start_date").val() > $("#end_date").val()) {
					$("#end_date").val($("#start_date").val());
				}
			}
			else {
				// single day! make em match
				$("#end_date").val($("#start_date").val());
			}
		}
	});
	
	if ($("#start_date").val() == $("#end_date").val()) {
		$("#end_date_row").hide();
	}
	else {
		$("#multi").attr('checked', true);
	}
	
	$("#multi").click(function() {
		if (this.checked) {
			$("#end_date").val($("#start_date").val());
			$("#end_date_row").fadeIn();
		}
		else {
			$("#end_date_row").fadeOut();
			$("#end_date").val($("#start_date").val());
		}
	});
	
	*/
	
	$(".wpgigs-datepicker").datepicker({
		currentText: objectL10n.currentText,
		monthNames: objectL10n.monthNames,
		monthNamesShort: objectL10n.monthNamesShort,
		dayNames: objectL10n.dayNames,
		dayNamesShort: objectL10n.dayNamesShort,
		dayNamesMin: objectL10n.dayNamesMin,
		firstDay: objectL10n.firstDay,
		isRTL: objectL10n.isRTL,
		dateFormat: 'yy-mm-dd',
		defaultDate: $("#start_date").val(),
		
		numberOfMonths: 2,
		beforeShowDay: function(date) {
			var date1 = $.datepicker.parseDate('yy-mm-dd', $("#start_date").val());
			var date2 = $.datepicker.parseDate('yy-mm-dd', $("#end_date").val());
			return [true, date1 && ((date.getTime() == date1.getTime()) || (date2 && date >= date1 && date <= date2)) ? "dp-highlight" : ""];
		},
		onSelect: function(dateText, inst) {
			var date1 = $.datepicker.parseDate('yy-mm-dd', $("#start_date").val());
			var date2 = $.datepicker.parseDate('yy-mm-dd', $("#end_date").val());
			var selectedDate = $.datepicker.parseDate('yy-mm-dd', dateText);

			
			if (!date1 || date2) {
				$("#start_date").val(dateText);
				$("#end_date").val("");
				$(this).datepicker();
			} else if( selectedDate < date1 ) {
				$("#end_date").val( $("#start_date").val() );
				$("#start_date").val( dateText );
				$(this).datepicker();
			} else {
				$("#end_date").val(dateText);
				$(this).datepicker();
			}
		}
	});
	
	
	$("#start_date, #end_date").blur(function() {
		$(".wpgigs-datepicker").datepicker( "setDate", $("#start_date").val() );
		$(".wpgigs-datepicker").datepicker( "setDate", $("#end_date").val() );
	});
	
	
	// form validation
	required = ["title", "start_date", "tickets"];
	
	// If using an ID other than #email or #error then replace it here
	errornotice = $("#wpgigs_error_message");

	$("#edit_gig_form").submit(function(){
		//Validate required fields
		for (i=0;i<required.length;i++) {
			var input = $('#'+required[i]);
			if (input.val() == "") {
				input.addClass("needsfilled");
				$(".required").css({'color' : 'red', 'font-weight' : 'bold'});
				errornotice.fadeIn(750);
			} else {
				input.removeClass("needsfilled");
			}
		}
	
		//if any inputs on the page have the class 'needsfilled' the form will not submit
		if ($(":input").hasClass("needsfilled")) {
			return false;
		} else {
			errornotice.hide();
			return true;
		}
	});
	
	// Clears any fields in the form when the user clicks on them
	$(":input").focus(function(){
	   if ($(this).hasClass("needsfilled") ) {
			$(this).val("");
			$(this).removeClass("needsfilled");
	   }
	});

	
	

});

function wpgigs_Deletegig(id) {
	if (confirm("Are you sure you want to delete this gig from you the database? This is a permanent action.")) {
		document.location.href = "?page=wpgigs_gig_calendar&id=" + id + "&action=delete";
	}
}