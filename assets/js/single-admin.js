(function ($) {

    'use strict';

    if (typeof rrzeMultilang === 'undefined' || rrzeMultilang === null) {
        return;
    }

    $(function () {
        $('#rrze-multilang-add-translation').click(function () {
            if (!rrzeMultilang.currentPost.postId) {
                return;
            }

            var locale = $('#rrze-multilang-translations-to-add').val();
            var restUrl = rrzeMultilang.apiSettings.getRoute(
                '/posts/' + rrzeMultilang.currentPost.postId + '/translations/' + locale);
            $('#rrze-multilang-add-translation').next('.spinner')
                .css('visibility', 'visible');

            $.ajax({
                type: 'POST',
                url: restUrl,
                beforeSend: function (xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', rrzeMultilang.apiSettings.nonce);
                }
            }).done(function (response) {
                var post = response[locale];

                if (!post) {
                    return;
                }
                
                location.reload();
                return;
            }).always(function () {
                $('#rrze-multilang-add-translation').next('.spinner').css('visibility', 'hidden');
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
