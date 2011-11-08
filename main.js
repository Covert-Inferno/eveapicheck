/*
	Copyright (c) 2011, Georg Großberger (georg@grossberger.at>
	All rights reserved.

	Licensed under the terms of the Modified BSD License
	http://www.opensource.org/licenses/BSD-3-Clause
*/

var Apicheck = {};

jQuery(function($) {

	var baseURL = 'request.php?action=';
	$("#sections, #check, #recentChecks, #login, #loadLogin, #loadCheck, #checkResult").hide();

	Apicheck.loadRecent = function(keyID, vCode) {
		setTab(0);
		$("#keyId").val(keyID);
		$("#vCode").val(vCode);
		doCheck();
	};

	function setTab(no) {
		$("#check")[no == 1 ? 'hide' : 'show']();
		$("#recentChecks")[no == 0 ? 'hide' : 'show']();
		$("#checkView, #recentView").toggleClass('important');
	}

	$("#checkView").click(function() {
		setTab(0);
	});

	$("#recentView").click(function() {
		setTab(1);
	});

	$.getJSON(baseURL + 'status', function(data) {
		console.log(data);
		if (data.error) {
			$("#sections").before('<h1>Error</h1><p>' + data.msg + '</p>');
		}
		else {
			if (!data.login === true) {
				$("#login").show();
			}
			else {
				loadRecent(function() {
					$("#sections, #check").show();
				});
			}
		}
	});

	function loadRecent(cb) {
		$.getJSON(baseURL + 'load', function(data) {
			var i = 0, set, container = $("#recentList").empty();
			if (data.recent && data.recent.length > 0) {
				for (i; i < data.recent.length; i++) {
					set = data.recent[i];
					container.append('<tr><td>' +
							set.date + '</td><td>' +
							set.keyId + '</td><td>' +
							set.vCode + '</td><td><a href="#" onclick="Apicheck.loadRecent(' +
							set.keyId + ', \'' +
							set.vCode + '\');return false;" class="button">Check again</a></td></tr>');
				}
			}
			else {
				container.append('<tr><td colspan="4">No recent entries found</td></tr>');
			}
			if (cb) cb();
		});
	}

	function doCheck() {
		var vCode = $("#vCode").val(),
			keyId = $("#keyId").val(),

			btn = $("#doCheck"),
			loader = $("#loadCheck"),
			container = $("#checkResult").empty().hide();

		if (/^([\d\w]+)$/.test(vCode) && /^([0-9]+)$/.test(keyId)) {
			btn.hide(); loader.show();
			$.post(baseURL + "check", {keyId: keyId, vCode: vCode}, function(data) {
				var html = "", e='', i=0;
				if (data.error) {
					html = '<h1>Error</h1><p>' + data.msg + '</p>';
				}
				else {

					html =
						'<h1>Check Result</h1>'
						+ '<p>This key is of type ' + data.type + ' ' + ( data.type == 'Character' ? ' <strong>Only one character</strong>' : ' All Characters') + '</p>'
						+ '<p>This key ' + (data.expires === false ? 'does not expire' : ' expires at ' + data.expires) + '</p>';

					html += '<div class="col"><h1>Access</h1><ul class="access-list">';
					for (e in data.access) {
						html += '<li class="' + (data.access[e] ? 'access' : 'no-access') + '">' + e + '</li>';
					}
					html += '</ul></div>';

					html += '<div class="col"><h1>Characters</h1>';

					for (i; i < data.characters.length; i++) {
						html += '<p class="char"><a href="https://gate.eveonline.com/Profile/'
								+ data.characters[i].name + '" target="_blank"><img src="http://image.eveonline.com/Character/' +
								data.characters[i].id + '_256.jpg" alt=""><br>' +
								data.characters[i].name + '</a><br><br><a href="https://gate.eveonline.com/Corporation/'
								+ data.characters[i].corp + '" target="_blank"><img src="http://image.eveonline.com/corporation/' +
								data.characters[i].corpId + '_256.png" alt=""><br>' +
								data.characters[i].corp + '</a></p>';
					}

					html += '</div>';
				}
				container.html(html).fadeIn();

			}, 'json').complete(function() {
				btn.show(); loader.hide();
				loadRecent();
			});
		}
	}
	$("#doCheck").click(doCheck);

	$("#doLogin").click(function() {

		var loader = $("#loadLogin").show(),
			button = $("#doLogin").hide();

		$.ajax({
			  type: 'POST',
			  url: baseURL + "status",
			  data: {login: $("#loginId").val()},
			  success: function(data) {
					if (data.login === true) {
						$("#login").hide();
						$("#sections, #check").show();
					}
					else {
						$("#login").append('<span class="error">Unable to login, please try again</span>');
					}
				},
			  dataType: 'json'
		 }).complete(function() {
				loader.hide();
				button.show();
		 });
	});
});