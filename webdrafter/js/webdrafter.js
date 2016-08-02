function selectAll(box)
{
	box.focus();
	box.select();
};

function toUrlName(str){
	str = str.replace(/^\s+|\s+$/g, ''); // trim
	str = str.toLowerCase();
	
	str = str.replace('æ','ae');
	
	// remove accents, swap ñ for n, etc
	var from = "àáäâèéëêìíïîòóöôùúüûñç·/_,:;";
	var to   = "aaaaeeeeiiiioooouuuunc------";
	for (var i=0, l=from.length ; i<l ; i++) {
		str = str.replace(new RegExp(from.charAt(i), 'g'), to.charAt(i));
	}
	
	str = str.replace(/[^a-z0-9 -]/g, '') // remove invalid chars
		.replace(/\s+/g, '-') // collapse whitespace and replace by -
		.replace(/-+/g, '-'); // collapse dashes
	
	return str;
}

var currentTab = null;
function makeTabs(element)
{
	element.tabs();

	/*$.address.change(function(event){
		//console.log(event);
		console.log("$.address.change " + window.location.hash);
		if(window.location.hash != "" && window.location.hash != null){
			element.tabs("option", "active", element.find(window.location.hash.substring(1)).index()-1 );			
		}
    })*/

    // when the tab is selected update the url with the hash
    element.bind("tabsactivate", function(event, ui) {
    	//alert("bb");
    	//console.log("pushState " + ui.newTab.children().attr("href"))
    	//currentTab = ui.newTab.children().attr("href");
    	history.pushState(null,null,ui.newTab.children().attr("href"));
    	
    	//$.address.value(ui.newTab.children().attr("href"));
    })
}

function showCopyable (str) {
    $("<div class='show-copyable-dialog'><textarea>" + str + "</textarea></div>").dialog({ modal: true, resizable: true, width: 800, height: 500, title: "Copy text" });

    $(".show-copyable-dialog textarea").select();
}