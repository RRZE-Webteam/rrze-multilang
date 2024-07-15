import { PluginDocumentSettingPanel } from "@wordpress/edit-post";
import {
    PanelRow,
    Button,
    SelectControl,
    Spinner,
} from "@wordpress/components";
import { useState } from "@wordpress/element";
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

    if (rrzeMultilang.localizablePostTypes.indexOf(currentPost.type) === -1) {
        return <></>;
    }

    if (currentPost.status === "auto-draft") {
        return <></>;
    }

    const [secondarySitesToLink, setSecondarySitesToLink] = useState(
        currentPost.secondarySitesToLink
    );
    const [secondarySitesToCopy, setSecondarySitesToCopy] = useState(
        currentPost.secondarySitesToCopy
    );

    const SecondarySitesToLink = () => {
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
                                // Check if the response is an error
                                dispatch("core/notices").createErrorNotice(
                                    __(response.message, "rrze-multilang"), // Use the error message from the response
                                    {
                                        isDismissible: true,
                                        type: "snackbar",
                                    }
                                );
                            } else {
                                const blogName = response[postId].blogName;
                                const postTitle = response[postId].postTitle;
                                dispatch("core/notices").createInfoNotice(
                                    __(
                                        `Linked to ${postTitle} on ${blogName}.`,
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
                                __(error.message, "rrze-multilang"), // Fallback error handling for network errors etc.
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

        return listItems.length ? (
            listItems
        ) : (
            <em>
                {__(
                    "There are no websites available for translations.",
                    "rrze-multilang"
                )}
            </em>
        );
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
                        // Check if the response is an error
                        dispatch("core/notices").createErrorNotice(
                            __(response.message, "rrze-multilang"), // Use the error message from the response
                            {
                                isDismissible: true,
                                type: "snackbar",
                            }
                        );
                        // Update the state to reflect the error and stop creating process
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
                        __(error.message, "rrze-multilang"), // Fallback error handling for network errors etc.
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
                const [blogId, setBlogId] = useState(key);

                const handleCopyChange = (blogId) => {
                    setBlogId(blogId);
                };

                return (
                    <div key={key}>
                        <PanelRow>
                            <SelectControl
                                label={__("Copy To:", "rrze-multilang")}
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
            <SecondarySitesToLink />
            <SecondarySitesToCopy />
        </PluginDocumentSettingPanel>
    );
}

const getLanguage = (locale) => {
    return rrzeMultilang.availableLanguages[locale]
        ? rrzeMultilang.availableLanguages[locale]
        : locale;
};
