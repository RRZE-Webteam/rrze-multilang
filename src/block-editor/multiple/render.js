import { PluginDocumentSettingPanel } from "@wordpress/editor";
import {
    PanelRow,
    Button,
    SelectControl,
    Spinner,
} from "@wordpress/components";
import { useState } from "@wordpress/element";
import { dispatch, useSelect } from "@wordpress/data";
import { __, sprintf } from "@wordpress/i18n";
import apiFetch from "@wordpress/api-fetch";

export default function LanguagePanel() {
    const currentPost = useSelect((select) => {
        return Object.assign(
            {},
            select("core/editor").getCurrentPost(),
            rrzeMultilang.currentPost
        );
    });

    // Ensure hooks are called in the same order
    const [secondarySitesToLink, setSecondarySitesToLink] = useState(
        currentPost.secondarySitesToLink || {}
    );

    const [secondarySitesToCopy, setSecondarySitesToCopy] = useState(
        currentPost.secondarySitesToCopy || {}
    );

    // Early return if conditions are not met
    if (rrzeMultilang.localizablePostTypes.indexOf(currentPost.type) === -1) {
        return null;
    }

    if (currentPost.status === "auto-draft") {
        return null;
    }

    const SecondarySitesToLink = () => {
        if (Object.keys(secondarySitesToLink).length === 0) {
            return (
                <em>
                    {__(
                        "There are no websites available for translations.",
                        "rrze-multilang"
                    )}
                </em>
            );
        }

        const listItems = Object.entries(secondarySitesToLink).map(
            ([key, site]) => {
                const {
                    name,
                    language,
                    url: siteUrl,
                    options,
                    selected,
                } = site;
                const labelText = `${name} \u2014 ${language}`;
                const [selBlogId, selPostId] = (selected || "").split(":");
                const labelElement =
                    selBlogId && selPostId && siteUrl ? (
                        <a
                            href={`${siteUrl}/wp-admin/post.php?post=${selPostId}&action=edit`}
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            {labelText}
                        </a>
                    ) : (
                        <span>{labelText}</span>
                    );
                const handleLinkChange = (newSelection) => {
                    const [newBlogId, newPostId] = newSelection.split(":");
                    apiFetch({
                        path: `/rrze-multilang/v1/link/${currentPost.id}/blog/${newBlogId}/post/${newPostId}`,
                        method: "POST",
                    })
                        .then((response) => {
                            if (response.code && response.message) {
                                dispatch("core/notices").createErrorNotice(
                                    response.message,
                                    {
                                        isDismissible: true,
                                        type: "snackbar",
                                    }
                                );
                                return;
                            }

                            setSecondarySitesToLink((prev) => ({
                                ...prev,
                                [key]: {
                                    ...prev[key],
                                    selected: newSelection,
                                },
                            }));

                            const remotePostId = Object.keys(response)[0];
                            const postTitle = response[remotePostId].postTitle;
                            const blogName = response[remotePostId].blogName;

                            let notice;
                            if (postTitle) {
                                notice = sprintf(
                                    /* translators: 1: the post title, 2: the blog name */
                                    __(
                                        "Linked to %1$s on %2$s.",
                                        "rrze-multilang"
                                    ),
                                    postTitle,
                                    blogName
                                );
                            } else {
                                notice = sprintf(
                                    /* translators: %s: the blog name */
                                    __("Unlinked from %s.", "rrze-multilang"),
                                    blogName
                                );
                            }

                            dispatch("core/notices").createInfoNotice(notice, {
                                isDismissible: true,
                                type: "snackbar",
                                speak: true,
                            });
                        })
                        .catch((error) => {
                            dispatch("core/notices").createErrorNotice(
                                error.message,
                                {
                                    isDismissible: true,
                                    type: "snackbar",
                                }
                            );
                        });
                };
                return (
                    <PanelRow key={key}>
                        <SelectControl
                            label={labelElement}
                            value={selected}
                            options={options}
                            onChange={handleLinkChange}
                        />
                    </PanelRow>
                );
            }
        );
        return listItems;
    };

    const SecondarySitesToCopy = () => {
        const addSecondarySitesToCopy = (blogId) => {
            const prevSite = secondarySitesToLink[blogId];
            const hadSelection =
                prevSite.selected &&
                Number(prevSite.selected.split(":")[1]) > 0;

            apiFetch({
                path: `/rrze-multilang/v1/copy/${currentPost.id}/blog/${blogId}`,
                method: "POST",
            })
                .then((response) => {
                    const updatedSecondarySitesToCopy = {
                        ...secondarySitesToCopy,
                        [blogId]: {
                            blogId: response[blogId].blogId,
                            blogName: response[blogId].blogName,
                            creating: false,
                        },
                    };
                    setSecondarySitesToCopy(updatedSecondarySitesToCopy);
                    dispatch("core/notices").createInfoNotice(
                        sprintf(
                            /* translators: %s: the blog name */
                            __(
                                "A copy has been added to %s.",
                                "rrze-multilang"
                            ),
                            updatedSecondarySitesToCopy[blogId].blogName
                        ),
                        {
                            isDismissible: true,
                            type: "snackbar",
                            speak: true,
                        }
                    );

                    const { postId, postTitle } = response[blogId];
                    const newValue = `${blogId}:${postId}`;
                    const alreadyHasOption = prevSite.options.some(
                        (opt) => opt.value === newValue
                    );
                    const newOptions = alreadyHasOption
                        ? prevSite.options
                        : [
                              ...prevSite.options,
                              { value: newValue, label: postTitle },
                          ];

                    setSecondarySitesToLink((prev) => ({
                        ...prev,
                        [blogId]: {
                            ...prevSite,
                            options: newOptions,
                            selected: hadSelection
                                ? prevSite.selected
                                : newValue,
                        },
                    }));

                    if (!hadSelection) {
                        apiFetch({
                            path: `/rrze-multilang/v1/link/${currentPost.id}/blog/${blogId}/post/${postId}`,
                            method: "POST",
                        }).catch((err) => {
                            dispatch("core/notices").createErrorNotice(
                                err.message,
                                { isDismissible: true, type: "snackbar" }
                            );
                        });
                    }
                })
                .catch((error) => {
                    dispatch("core/notices").createErrorNotice(error.message, {
                        isDismissible: true,
                        type: "snackbar",
                    });

                    setSecondarySitesToCopy(currentPost.secondarySitesToCopy);
                });
        };

        const listItems = Object.entries(secondarySitesToCopy).map(
            ([key, value]) => {
                const mainLabel = __("Copy To", "rrze-multilang");
                const [blogId, setBlogId] = useState(key);
                const handleCopyChange = (blogId) => {
                    setBlogId(blogId);
                };
                return (
                    <div key={key}>
                        <PanelRow>
                            <SelectControl
                                label={mainLabel}
                                value={blogId}
                                options={value.options}
                                onChange={handleCopyChange}
                            />
                        </PanelRow>
                        {value.creating === undefined ? (
                            <Button
                                isSecondary
                                onClick={() => addSecondarySitesToCopy(blogId)}
                            >
                                {__("Add Copy", "rrze-multilang")}
                            </Button>
                        ) : value.creating ? (
                            <Spinner />
                        ) : null}
                    </div>
                );
            }
        );
        return listItems.length ? listItems : <></>;
    };

    return (
        <PluginDocumentSettingPanel
            name="rrze-multilang-language-panel"
            title={__("Language", "rrze-multilang")}
            className="rrze-multilang-language-panel"
        >
            <h3 class="rrze-multilang-setting-panel__h3">
                {__("Secondary Websites to Link", "rrze-multilang")}
            </h3>
            <p class="rrze-multilang-setting-panel__help">
                {__(
                    "Select the websites where you want to link the current post.",
                    "rrze-multilang"
                )}
            </p>
            <SecondarySitesToLink />
            <hr />
            <h3 class="rrze-multilang-setting-panel__h3">
                {__("Secondary Websites to Copy", "rrze-multilang")}
            </h3>
            <p class="rrze-multilang-setting-panel__help">
                {__(
                    "Select the websites where you want to create copies of the current post.",
                    "rrze-multilang"
                )}
            </p>
            <SecondarySitesToCopy />
        </PluginDocumentSettingPanel>
    );
}
