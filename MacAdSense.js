/*
 *
 * Copyright (C) 2007 Kai 'Oswald' Seidler, http://oswaldism.de
 * Copyright (C) 2007 Janos Rusiczki, http://www.rusiczki.net
 *
 * This program is free software; you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by the Free
 * Software Foundation; either version 2 of the License, or (at your option)
 * any later version.
 * 
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
 * FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for
 * more details.
 * 
 * You should have received a copy of the GNU General Public License along with
 * this program; if not, write to the Free Software Foundation, Inc., 675 Mass
 * Ave, Cambridge, MA 02139, USA. 
 * 
 */

var version = "0.1";
var timeout = 60 * 20; // set update frequency to 20 minutes 
var now = 0;
var timeoutevent = 0;

// many parts of this Widged were based on Apple's Widget tutorial at
// http://developer.apple.com/documentation/AppleApplications/Conceptual/Dashboard_ProgTopics/

function showBack()
{
    var front = document.getElementById("front");
    var back = document.getElementById("back");

    if (window.widget)
        widget.prepareForTransition("ToBack");

    front.style.display = "none";
    back.style.display = "block";

    if (window.widget)
        setTimeout ('widget.performTransition();', 0);  
}

function hideBack()
{
    var front = document.getElementById("front");
    var back = document.getElementById("back");

    if (window.widget)
	{
		var form = document.getElementById("ff");

		if(form.username.value != "" && form.password.value != "")
		{
			widget.setPreferenceForKey(form.startOfWeek.value, "startOfWeek")
			widget.setPreferenceForKey(form.username.value, "username");

			now = 0;
			showDialog("Saving to keychain...");
			var command = widget.system("./MacAdSense.php setcredentials", onshow);
			command.write(form.username.value + "\n");
			command.write(form.password.value + "\n");
			command.close();

			form.password.value = "";
		}
    }

    if (window.widget)
        widget.prepareForTransition("ToFront");

    back.style.display="none";
    front.style.display="block";

    if (window.widget)
    {
        setTimeout ('widget.performTransition();', 0);
    }
}

function showDialog(message)
{
	dialog_content.innerHTML = message;
	dialog.style.display = "block";
	front_content.style.display = "none";
}

function hideDialog()
{
	front_content.style.display="block";
	dialog.style.display="none";
}

function endHandler()
{
}

function fetchData()
{
	var command = widget.system("./MacAdSense.php getdata", displayData);
	command.write(widget.preferenceForKey("username")+"\n");
	command.write(widget.preferenceForKey("startOfWeek")+"\n");
	command.close();
}

function displayData(data)
{
	output = data.outputString.split("#");

	// do we get real data?
	if(output[0] != 0) 
	{
		document.getElementById("updated").innerHTML = output[0];
		document.getElementById("clicksToday").innerHTML = 'Clicks: ' + output[1];
		document.getElementById("earningsToday").innerHTML = '$' + output[2];
		document.getElementById("clicksYesterday").innerHTML = 'Clicks: ' + output[3];
		document.getElementById("earningsYesterday").innerHTML = '$' + output[4];
		document.getElementById("clicksLastWeek").innerHTML = 'Clicks: ' + output[5];
		document.getElementById("earningsLastWeek").innerHTML = '$' + output[6];
		document.getElementById("clicksThisWeek").innerHTML = 'Clicks: ' + output[7];
		document.getElementById("earningsThisWeek").innerHTML = '$' + output[8];
		document.getElementById("clicksLastMonth").innerHTML = 'Clicks: ' + output[9];
		document.getElementById("earningsLastMonth").innerHTML = '$' + output[10];
		document.getElementById("clicksThisMonth").innerHTML = 'Clicks: ' + output[11];
		document.getElementById("earningsThisMonth").innerHTML = '$' + output[12];
		
		now = Math.round(new Date().getTime() / 1000);

		hideDialog();
	}
	else
	{
		showDialog("Can't fetch AdSense data.<br><span class=\"small\">Maybe wrong credentials or network problems?</span>");
	}

	if(timeoutevent!=0)
	{
		clearTimeout(timeoutevent);
	}
	timeoutevent=setTimeout('fetchData();',timeout*1000);
}

function onshow()
{
	if(widget.preferenceForKey("username")!=undefined && widget.preferenceForKey("username")!="")
	{
		if(now && Math.round(new Date().getTime()/1000)-now<timeout)
		{
			// still not the time to fetch new adsense data
		}
		else
		{
        		showDialog("Loading...");

			setTimeout('fetchData()',100);
		}
	}
	else
	{
  		showDialog("Please set username and<br>password on the back side.");
	}
}

function onhide()
{
}

function setup()
{
	if (window.widget) 
	{
		widget.onshow = onshow;
		widget.onhide = onhide;

		var form=document.getElementById("ff");
		form.username.value = widget.preferenceForKey("username");
		form.startOfWeek.selectedIndex = widget.preferenceForKey("startOfWeek");
	}
	var done_button = new AppleGlassButton(document.getElementById("done"), "Done", hideBack);
	i_button = new AppleInfoButton(document.getElementById("i"), document.getElementById("front"), "white", "white", showBack);
	document.getElementById("version").innerHTML = 'v' + version;

	var dialog = document.getElementById("dialog");
	var dialog_content = document.getElementById("dialog_content");
	var front_content = document.getElementById("front_content");

}
