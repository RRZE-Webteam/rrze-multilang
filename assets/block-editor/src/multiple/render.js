import { PluginDocumentSettingPanel } from '@wordpress/edit-post';
import { PanelRow, Button, SelectControl, Spinner } from '@wordpress/components';
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

    if ('auto-draft' == currentPost.status) {
        return (<></>);
    }

    const [secondarySitesToLink, setSecondarySitesToLink]
        = useState(currentPost.secondarySitesToLink);

    const [secondarySitesToCopy, setSecondarySitesToCopy]
        = useState(currentPost.secondarySitesToCopy);

    const SecondarySitesToLink = () => {
        const listItems = [];
        Object.entries(secondarySitesToLink).forEach(([key, value]) => {
            let mainLabel = value.name + ' \u2014 ' + value.language;
            let selected = value.selected;
            const LinkSelectControl = withState({
                link: selected,
            })(({ link, setState }) => (
                <SelectControl
                    label={mainLabel}
                    value={link}
                    options={value.options}
                    onChange={(link, value) => {
                        setState({ link });
                        let val = link.split(':');
                        let blogId = val[0];
                        let postId = val[1];
                        apiFetch({
                            path: '/rrze-multilang/v1/link/' + currentPost.id +
                                '/blog/' + blogId + '/post/' + postId,
                            method: 'POST',
                        }).then((response) => {
                            let blogName = response[postId].blogName;
                            let postTitle = response[postId].postTitle;
                            dispatch('core/notices').createInfoNotice(
                                __(`Linked to ${postTitle} on ${blogName}.`, 'rrze-multilang'),
                                {
                                    isDismissible: true,
                                    type: 'snackbar',
                                    speak: true
                                }
                            );
                        });
                    }}
                />
            ));

            listItems.push(
                <PanelRow>
                    <LinkSelectControl />
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

        const addSecondarySitesToCopy = (blogId) => {
            const secondarySitesToCopyAlt = Object.assign({}, secondarySitesToCopy);

            secondarySitesToCopyAlt[blogId] = {
                creating: true,
            };

            setSecondarySitesToCopy(secondarySitesToCopyAlt);

            apiFetch({
                path: '/rrze-multilang/v1/copy/' + currentPost.id +
                    '/blog/' + blogId,
                method: 'POST',
            }).then((response) => {
                const secondarySitesToCopyAlt = Object.assign({}, secondarySitesToCopy);

                secondarySitesToCopyAlt[blogId] = {
                    blogId: response[blogId].blogId,
                    blogName: response[blogId].blogName,
                    creating: false,
                };

                setSecondarySitesToCopy(secondarySitesToCopyAlt);

                let blogName = secondarySitesToCopyAlt[blogId].blogName;

                dispatch('core/notices').createInfoNotice(
                    __(`A copy has been added to ${blogName}.`, 'rrze-multilang'),
                    {
                        isDismissible: true,
                        type: 'snackbar',
                        speak: true
                    }
                );
            });
        }

        const listItems = [];
        let blogIdVal = 0;

        Object.entries(secondarySitesToCopy).forEach(([key, value]) => {
            const CopySelectControl = withState({
                blogId: key,
            })(({ blogId, setState }) => (
                <SelectControl
                    label={__('Copy To:', 'rrze-multilang')}
                    value={blogId}
                    options={value.options}
                    onChange={(blogId) => {
                        setState({ blogId });
                        blogIdVal = blogId;
                    }}
                />
            ));

            if (value.creating == undefined) {
                listItems.push(
                    <PanelRow>
                        <CopySelectControl />
                    </PanelRow>
                );

                listItems.push(
                    <Button
                        isDefault
                        onClick={() => { addSecondarySitesToCopy(blogIdVal) }}
                    >
                        {__('Add Copy', 'rrze-multilang')}
                    </Button>
                );
            } else if (value.creating) {
                listItems.push(
                    <Spinner />
                );
            }
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

    return (
        <PluginDocumentSettingPanel
            name="rrze-multilang-language-panel"
            title={__('Language', 'rrze-multilang')}
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
}