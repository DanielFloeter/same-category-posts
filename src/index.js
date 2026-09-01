import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';
import { createBlock } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';

import './style.scss';
import Edit from './edit';
import Save from './save';
import Icon from './icon';

import metadata from './../block.json';

const { name } = metadata;

/**
 * Every block starts by registering a new block type definition.
 *
 * @see https://developer.wordpress.org/block-editor/developers/block-api/#registering-a-block
 */
registerBlockType(name, {
    ...metadata,
    icon: Icon,
    description: __(
        'Show posts related to the current category or other custom post types.',
        'same-posts'
    ),
    keywords: [
        __('related'),
        __('posts'),
        __('posts widget'),
        __('custom post type'),
        __('products'),
        __('events'),
        __('tiptoppress'),
    ],
    example: {
        attributes: {
            values: '<ul>' +
                '<li>' +
                '<div><a class="cat-post-title">dolorem eum fugiat quo voluptas</a><div>' +
                '<p class="cpwp-excerpt-text">But who has any right to find fault with a man who chooses to enjoy a pleasure that has no annoying consequences, or one who avoids a pain that produces no resultant pleasure?</p>' +
                '</li>' +
                '<li>' +
                '<div><a class="cat-post-title">gummies tootsie roll</a><div>' +
                '<p class="cpwp-excerpt-text">Cake jujubes jelly beans. Marzipan soufflé gummies gummi bears oat cake chocolate jelly icing. Cotton candy croissant wafer cake apple pie juj.</p>' +
                '</li>' +
                '</ul>',
        },
    },
    transforms: {
        from: [{
            type: 'block',
            blocks: ['core/legacy-widget', 'core/paragraph'],
            isMatch: ({ idBase, instance }) => {
                if (!instance?.raw) {
                    // Can't transform if raw instance is not shown in REST API.
                    return false;
                }
                return idBase === 'same-posts';
            },
            transform: ({ instance }) => {
                return createBlock('tiptip/same-posts-block', {
                    ascSortOrder: !!instance.raw.asc_sort_order,
                });
            },

        }, ]
    },
    edit: Edit,

    /**
     * @see ./save.js
     */
    //save: Save,
    // save: () => {
    // 	const blockProps = useBlockProps.save();
    // 	return <div { ...blockProps }> Hello in Save.</div>;
    // },
});