(function ($) {

    'use strict';

    if (typeof rrzeMultilang === 'undefined' || rrzeMultilang === null) {
        return;
    }

    $(function () {
        $('#rrze-multilang-update-links').click(function () {
            if (!rrzeMultilang.currentPost.postId) {
                return;
            }
            $('select.rrze-multilang-links').each(function () {
                var select = $(this);
                var value = select.val().split('::');
                var blogId = value[0];
                var postId = value[1];
                var restUrl = rrzeMultilang.apiSettings.getRoute(
                    '/link/' + rrzeMultilang.currentPost.postId + '/blog/' + blogId + '/post/' + postId);

                $('#rrze-multilang-update-links').next('.spinner')
                    .css('visibility', 'visible');

                $.ajax({
                    type: 'POST',
                    url: restUrl,
                    beforeSend: function (xhr) {
                        xhr.setRequestHeader('X-WP-Nonce', rrzeMultilang.apiSettings.nonce);
                    }
                }).done(function (response) {
                    var post = response[postId];

                    if (!post) {
                        return;
                    }

                    location.reload();
                    return;
                }).always(function () {
                    $('#rrze-multilang-update-links').next('.spinner').css('visibility', 'hidden');
                });
            });

        });
    });

    $(function () {
        $('#rrze-multilang-add-copy').click(function () {
            if (!rrzeMultilang.currentPost.postId) {
                return;
            }

            var blogId = $('#rrze-multilang-copy-to-add').val();
            var restUrl = rrzeMultilang.apiSettings.getRoute(
                '/copy/' + rrzeMultilang.currentPost.postId + '/blog/' + blogId);
            $('#rrze-multilang-add-copy').next('.spinner')
                .css('visibility', 'visible');

            $.ajax({
                type: 'POST',
                url: restUrl,
                beforeSend: function (xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', rrzeMultilang.apiSettings.nonce);
                }
            }).done(function (response) {
                var post = response[blogId];

                if (!post) {
                    return;
                }

                location.reload();
                return;
            }).always(function () {
                $('#rrze-multilang-add-copy').next('.spinner').css('visibility', 'hidden');
            });
        });
    });

    rrzeMultilang.apiSettings.getRoute = function (path) {
        var url = rrzeMultilang.apiSettings.root;

        url = url.replace(
            rrzeMultilang.apiSettings.namespace,
            rrzeMultilang.apiSettings.namespace + path);

        return url;
    };

})(jQuery);
