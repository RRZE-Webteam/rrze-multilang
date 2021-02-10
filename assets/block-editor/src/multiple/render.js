import { PluginDocumentSettingPanel } from '@wordpress/edit-post';
import { PanelRow, Button, ExternalLink, SelectControl, Spinner } from '@wordpress/components';
import { withState } from '@wordpress/compose';
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

    if (rrzeMultilang.localizablePostTypes.indexOf(currentPost.type) == -1) {
        return (<></>);
    }

    const [secondarySitesToLink, setSecondarySitesToLink]
        = useState(currentPost.secondarySitesToLink);

    const [secondarySitesToCopy, setSecondarySitesToCopy]
        = useState(currentPost.secondarySitesToCopy);

    const SecondarySitesToLink = () => {
        const listItems = [];
        Object.entries(secondarySitesToLink).forEach(([key, value]) => {
            listItems.push(
                <PanelRow>
                    <SelectControl
                        label={value.name + ' \u2014 ' + value.language}
                        value={value.selected}
                        options={value.options}
                    />
                </PanelRow>
            );
        });

        const ListItems = (props) => {
            if (props.listItems.length) {
                return (
                    props.listItems
                );
            } else {
                return (
                    <em>{__('There are no websites available for translations.', 'rrze-multilang')}</em>
                );
            }
        }

        return (
            <ListItems listItems={listItems} />
        );
    }

    const SecondarySitesToCopy = () => {
        const listItems = [];
        Object.entries(secondarySitesToCopy).forEach(([key, value]) => {
            listItems.push(
                <PanelRow>
                    <SelectControl
                        label={__('Copy To:', 'rrze-multilang')}
                        value='0'
                        options={value.options}
                    />
                </PanelRow>
            );
        });

        const ListItems = (props) => {
            if (props.listItems.length) {
                return (
                    props.listItems
                );
            } else {
                return (<></>);
            }
        }

        return (
            <ListItems listItems={listItems} />
        );
    }

    const AddSecondarySitesToCopy = () => {
        const addSecondarySitesToCopy = (blogId) => {
            const secondarySitesToCopyAlt = Object.assign({}, secondarySitesToCopy);

            secondarySitesToCopyAlt[blogId] = {
                creating: true,
            };

            setSecondarySitesToCopy(secondarySitesToCopyAlt);

            apiFetch({
                path: '/rrze-multilang/v1/posts/' + currentPost.id +
                    '/copy/' + blogId,
                method: 'POST',
            }).then((response) => {
                const secondarySitesToCopyAlt = Object.assign({}, secondarySitesToCopy);

                dispatch('core/notices').createInfoNotice(
                    __('Translation created.', 'rrze-multilang'),
                    {
                        isDismissible: true,
                        type: 'snackbar',
                        speak: true,
                        actions: [
                            {
                                url: secondarySitesToCopyAlt[blogId].editLink,
                                label: __('Edit Post', 'rrze-multilang'),
                            }
                        ]
                    }
                );
            });
        }

        if ('auto-draft' == currentPost.status) {
            return (<></>);
        }

        return (
            <PanelRow>
                <Button
                    isDefault
                >
                    {__('Add Copy', 'rrze-multilang')}
                </Button>
            </PanelRow>
        );
    }

    return (
        <PluginDocumentSettingPanel
            name="rrze-multilang-language-panel"
            title={__('Language', 'rrze-multilang')}
            className="rrze-multilang-language-panel"
        >
            <SecondarySitesToLink />
            <SecondarySitesToCopy />
            <AddSecondarySitesToCopy />
        </PluginDocumentSettingPanel>
    );
}

const getLanguage = (locale) => {
    return rrzeMultilang.availableLanguages[locale]
        ? rrzeMultilang.availableLanguages[locale]
        : locale;
}