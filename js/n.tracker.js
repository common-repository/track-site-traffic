jQuery(document).ready(function($) {
	$("#divTrackerContent ul").idTabs();
	$("#tblRecentRequests").tablesorter({
		sortList: [[0,1]],
		widgets: ['zebra']
	});
});