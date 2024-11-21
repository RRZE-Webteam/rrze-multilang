import { PluginDocumentSettingPanel } from "@wordpress/edit-post";
import {
    PanelRow,
    Button,
    SelectControl,
    Spinner,
} from "@wordpress/components";
import { useState, useEffect } from "@wordpress/element";
import { dispatch, useSelect } from "@wordpress/data";
import { __ } from "@wordpress/i18n";
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
            ([key, value]) => {
                const mainLabel = value.name + " \u2014 " + value.language;
                const [link, setLink] = useState(value.selected);
                const handleLinkChange = (link) => {
                    setLink(link);
                    const [blogId, postId] = link.split(":");
                    apiFetch({
                        path: `/rrze-multilang/v1/link/${currentPost.id}/blog/${blogId}/post/${postId}`,
                        method: "POST",
                    })
                        .then((response) => {
                            if (response.code && response.message) {
                                dispatch("core/notices").createErrorNotice(
                                    __(response.message, "rrze-multilang"),
                                    {
                                        isDismissible: true,
                                        type: "snackbar",
                                    }
                                );
                            } else {
                                const updatedSecondarySitesToLink = {
                                    ...secondarySitesToLink,
                                    [key]: {
                                        ...value,
                                        selected: link,
                                    },
                                };
                                setSecondarySitesToLink(updatedSecondarySitesToLink);

                                const remotePostId = Object.keys(response)[0];
                                const postTitle = response[remotePostId].postTitle;
                                const blogName = response[remotePostId].blogName;

                                let notice;
                                if (postTitle) {
                                    notice = __(
                                        `Linked to ${postTitle} on ${blogName}.`,
                                        "rrze-multilang"
                                    );
                                } else {
                                    notice = __(
                                        `Unlinked from ${blogName}.`,
                                        "rrze-multilang"
                                    );
                                }
                                                                
                                dispatch("core/notices").createInfoNotice(
                                    notice,
                                    {
                                        isDismissible: true,
                                        type: "snackbar",
                                        speak: true,
                                    }
                                );
                            }
                        })
                        .catch((error) => {
                            dispatch("core/notices").createErrorNotice(
                                __(error.message, "rrze-multilang"),
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
                            label={mainLabel}
                            value={link}
                            options={value.options}
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
            const updatedSecondarySitesToCopy = {
                ...secondarySitesToCopy,
                [blogId]: { creating: true },
            };
            setSecondarySitesToCopy(updatedSecondarySitesToCopy);
            apiFetch({
                path: `/rrze-multilang/v1/copy/${currentPost.id}/blog/${blogId}`,
                method: "POST",
            })
                .then((response) => {
                    if (response.code && response.message) {
                        dispatch("core/notices").createErrorNotice(
                            __(response.message, "rrze-multilang"),
                            {
                                isDismissible: true,
                                type: "snackbar",
                            }
                        );
                        const updatedWithError = {
                            ...secondarySitesToCopy,
                            [blogId]: {
                                creating: false,
                                error: response.message,
                            },
                        };
                        setSecondarySitesToCopy(updatedWithError);
                    } else {
                        const updatedSecondarySitesToCopy = {
                            ...secondarySitesToCopy,
                            [blogId]: {
                                blogId: response[blogId].blogId,
                                blogName: response[blogId].blogName,
                                creating: false,
                            },
                        };
                        setSecondarySitesToCopy(updatedSecondarySitesToCopy);
                        const blogName =
                            updatedSecondarySitesToCopy[blogId].blogName;
                        dispatch("core/notices").createInfoNotice(
                            __(
                                `A copy has been added to ${blogName}.`,
                                "rrze-multilang"
                            ),
                            {
                                isDismissible: true,
                                type: "snackbar",
                                speak: true,
                            }
                        );
                    }
                })
                .catch((error) => {
                    dispatch("core/notices").createErrorNotice(
                        __(error.message, "rrze-multilang"),
                        {
                            isDismissible: true,
                            type: "snackbar",
                        }
                    );
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
            <h3 class="rrze-multilang-setting-panel__h3">{__("Secondary Sites to Link", "rrze-multilang")}</h3>
            <p class="rrze-multilang-setting-panel__help">{__("Select the sites where you want to link the current post.", "rrze-multilang")}</p>
            <SecondarySitesToLink />
            <hr />
            <h3 class="rrze-multilang-setting-panel__h3">{__("Secondary Sites to Copy", "rrze-multilang")}</h3>
            <p class="rrze-multilang-setting-panel__help">{__("Select the sites where you want to create copies of the current post.", "rrze-multilang")}</p>
            <SecondarySitesToCopy />
        </PluginDocumentSettingPanel>
    );
}