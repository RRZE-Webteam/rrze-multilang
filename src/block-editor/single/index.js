import { registerPlugin } from "@wordpress/plugins";
import { addQueryArgs, hasQueryArg } from "@wordpress/url";
import apiFetch from "@wordpress/api-fetch";

import render from "./render";

registerPlugin("rrze-multilang-language-panel", {
    render,
    icon: "translation",
});

apiFetch.use((options, next) => {
    const postLang = rrzeMultilang ? rrzeMultilang.currentPost.lang : null;

    if (postLang) {
        if (
            typeof options.url === "string" &&
            !hasQueryArg(options.url, "lang")
        ) {
            options.url = addQueryArgs(options.url, { lang: postLang });
        }

        if (
            typeof options.path === "string" &&
            !hasQueryArg(options.path, "lang")
        ) {
            options.path = addQueryArgs(options.path, { lang: postLang });
        }
    }

    return next(options, next);
});
