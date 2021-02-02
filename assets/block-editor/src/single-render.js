import { PluginDocumentSettingPanel } from '@wordpress/edit-post';
import { PanelRow, Button, ExternalLink, Spinner } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { dispatch, useSelect } from '@wordpress/data';
import { sprintf, __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

export default function LanguagePanel() {
    const currentPost = useSelect((select) => {
        return Object.assign({},
            select('core/editor').getCurrentPost(),
            rrzeMultilang.currentPost
        );
    });

    if (-1 == rrzeMultilang.localizablePostTypes.indexOf(currentPost.type)) {
        return (<></>);
    }

    const [translations, setTranslations]
        = useState(currentPost.translations);

    const PostLanguage = () => {
        return (
            <PanelRow>
                <span>{__('Language', 'rrze-multilang')}</span>
                <div>{getLanguage(currentPost.locale)}</div>
            </PanelRow>
        );
    }

    const Translations = () => {
        const listItems = [];

        Object.entries(translations).forEach(([key, value]) => {
            if (value.editLink && value.postTitle) {
                listItems.push(
                    <li key={key}>
                        <ExternalLink href={value.editLink}>
                            {value.postTitle}
                        </ExternalLink>
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
                return (
                    <ul>{props.listItems}</ul>
                );
            } else {
                return (
                    <em>{__('None', 'rrze-multilang')}</em>
                );
            }
        }

        return (
            <PanelRow>
                <span>{__('Translations', 'rrze-multilang')}</span>
                <ListItems listItems={listItems} />
            </PanelRow>
        );
    }

    const AddTranslation = () => {
        const addTranslation = (locale) => {
            const translationsAlt = Object.assign({}, translations);

            translationsAlt[locale] = {
                creating: true,
            };

            setTranslations(translationsAlt);

            apiFetch({
                path: '/rrze-multilang/v1/posts/' + currentPost.id +
                    '/translations/' + locale,
                method: 'POST',
            }).then((response) => {
                const translationsAlt = Object.assign({}, translations);

                translationsAlt[locale] = {
                    postId: response[locale].id,
                    postTitle: response[locale].title.raw,
                    editLink: response[locale].edit_link,
                    creating: false,
                };

                setTranslations(translationsAlt);

                dispatch('core/notices').createInfoNotice(
                    __('Translation created.', 'rrze-multilang'),
                    {
                        isDismissible: true,
                        type: 'snackbar',
                        speak: true,
                        actions: [
                            {
                                url: translationsAlt[locale].editLink,
                                label: __('Edit Post', 'rrze-multilang'),
                            }
                        ]
                    }
                );
            });
        }

        const listItems = [];

        Object.entries(translations).forEach(([key, value]) => {
            if (value.postId) {
                return;
            }

            listItems.push(
                <li key={key}>
                    <Button
                        isDefault
                        onClick={() => { addTranslation(key) }}
                    >
                        {
                            sprintf(
                                /* translators: %s: Language name. */
                                __('Add %s translation', 'rrze-multilang'),
                                getLanguage(key)
                            )
                        }
                    </Button>
                    {value.creating && <Spinner />}
                </li>
            );
        });

        if (listItems.length < 1 || 'auto-draft' == currentPost.status) {
            return (<></>);
        }

        return (
            <PanelRow>
                <ul>{listItems}</ul>
            </PanelRow>
        );
    }

    return (
        <PluginDocumentSettingPanel
            name="rrze-multilang-language-panel"
            title={__('Language', 'rrze-multilang')}
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
}