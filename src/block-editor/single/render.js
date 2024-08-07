import { PluginDocumentSettingPanel } from "@wordpress/edit-post";
import { PanelRow, Button, Spinner } from "@wordpress/components";
import { useState } from "@wordpress/element";
import { dispatch, useSelect } from "@wordpress/data";
import { sprintf, __ } from "@wordpress/i18n";
import apiFetch from "@wordpress/api-fetch";

export default function LanguagePanel() {
    if (typeof rrzeMultilang === "undefined") {
        return <></>;
    }

    const currentPost = useSelect((select) => {
        return Object.assign(
            {},
            select("core/editor").getCurrentPost(),
            rrzeMultilang.currentPost
        );
    });

    if (rrzeMultilang.localizablePostTypes.indexOf(currentPost.type) == -1) {
        return <></>;
    }

    const [translations, setTranslations] = useState(currentPost.translations);

    const PostLanguage = () => {
        return (
            <PanelRow>
                <span>{__("Language", "rrze-multilang")}</span>
                <div>{getLanguage(currentPost.locale)}</div>
            </PanelRow>
        );
    };

    const Translations = () => {
        const listItems = [];

        Object.entries(translations).forEach(([key, value]) => {
            if (value.editLink && value.postTitle) {
                listItems.push(
                    <li key={key}>
                        <a href={value.editLink} rel="noopener noreferrer">
                            {value.postTitle}
                        </a>
                        <br />
                        <em>{getLanguage(key)}</em>
                    </li>
                );
            } else if (value.postTitle) {
                listItems.push(
                    <li key={key}>
                        {value.postTitle}
                        <br />
                        <em>{getLanguage(key)}</em>
                    </li>
                );
            }
        });

        const ListItems = (props) => {
            if (props.listItems.length) {
                return <ul>{props.listItems}</ul>;
            } else {
                return <em>{__("None", "rrze-multilang")}</em>;
            }
        };

        return (
            <PanelRow>
                <span>{__("Translations", "rrze-multilang")}</span>
                <ListItems listItems={listItems} />
            </PanelRow>
        );
    };

    const AddTranslation = () => {
        const addTranslation = (locale) => {
            const translationsAlt = { ...translations };

            translationsAlt[locale] = {
                creating: true,
            };

            setTranslations(translationsAlt);

            apiFetch({
                path: `/rrze-multilang/v1/posts/${currentPost.id}/translations/${locale}`,
                method: "POST",
            }).then((response) => {
                const translationsAlt = { ...translations };

                translationsAlt[locale] = {
                    postId: response[locale].id,
                    postTitle: response[locale].title.raw,
                    editLink: response[locale].edit_link,
                    creating: false,
                };

                setTranslations(translationsAlt);

                dispatch("core/notices").createInfoNotice(
                    __("Translation created.", "rrze-multilang"),
                    {
                        isDismissible: true,
                        type: "snackbar",
                        speak: true,
                        actions: [
                            {
                                url: translationsAlt[locale].editLink,
                                label: __("Edit Post", "rrze-multilang"),
                            },
                        ],
                    }
                );
            });
        };

        const listItems = [];

        Object.entries(translations).forEach(([key, value]) => {
            if (value.postId) {
                return;
            }

            listItems.push(
                <li key={key}>
                    <Button
                        isSecondary
                        onClick={() => {
                            addTranslation(key);
                        }}
                    >
                        {sprintf(
                            /* translators: %s: Language name. */
                            __("Add %s", "rrze-multilang"),
                            getLanguage(key)
                        )}
                    </Button>
                    {value.creating && <Spinner />}
                </li>
            );
        });

        if (listItems.length < 1 || "auto-draft" == currentPost.status) {
            return <></>;
        }

        return (
            <PanelRow>
                <ul>{listItems}</ul>
            </PanelRow>
        );
    };

    return (
        <PluginDocumentSettingPanel
            name="rrze-multilang-language-panel"
            title={__("Language", "rrze-multilang")}
            className="rrze-multilang-language-panel"
        >
            <PostLanguage />
            <Translations />
            <AddTranslation />
        </PluginDocumentSettingPanel>
    );
}

const getLanguage = (locale) => {
    return rrzeMultilang.availableLanguages[locale]
        ? rrzeMultilang.availableLanguages[locale]
        : locale;
};
