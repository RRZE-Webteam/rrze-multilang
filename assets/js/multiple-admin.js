!function(i){"use strict";"undefined"!=typeof rrzeMultilang&&null!==rrzeMultilang&&(i(function(){i("#rrze-multilang-update-links").click(function(){rrzeMultilang.currentPost.postId&&i("select.rrze-multilang-links").each(function(){var t=i(this).val().split(":"),n=t[0],e=t[1],n=rrzeMultilang.apiSettings.getRoute("/link/"+rrzeMultilang.currentPost.postId+"/blog/"+n+"/post/"+e);i("#rrze-multilang-update-links").next(".spinner").css("visibility","visible"),i.ajax({type:"POST",url:n,beforeSend:function(t){t.setRequestHeader("X-WP-Nonce",rrzeMultilang.apiSettings.nonce)}}).done(function(t){t[e]&&location.reload()}).always(function(){i("#rrze-multilang-update-links").next(".spinner").css("visibility","hidden")})})})}),i(function(){i("#rrze-multilang-add-copy").click(function(){var n,t;rrzeMultilang.currentPost.postId&&(n=i("#rrze-multilang-copy-to-add").val(),t=rrzeMultilang.apiSettings.getRoute("/copy/"+rrzeMultilang.currentPost.postId+"/blog/"+n),i("#rrze-multilang-add-copy").next(".spinner").css("visibility","visible"),i.ajax({type:"POST",url:t,beforeSend:function(t){t.setRequestHeader("X-WP-Nonce",rrzeMultilang.apiSettings.nonce)}}).done(function(t){t[n]&&location.reload()}).always(function(){i("#rrze-multilang-add-copy").next(".spinner").css("visibility","hidden")}))})}),rrzeMultilang.apiSettings.getRoute=function(t){return rrzeMultilang.apiSettings.root.replace(rrzeMultilang.apiSettings.namespace,rrzeMultilang.apiSettings.namespace+t)})}(jQuery);