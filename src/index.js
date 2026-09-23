import { createBlock, registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';

import './style.scss';
import Edit from './edit';
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
        __('related posts', 'same-posts'),
        __('similar posts', 'same-posts'),
        __('same category', 'same-posts'),
        __('more posts', 'same-posts'),
        __('read more', 'same-posts'),
        __('custom post type', 'same-posts'),
        __('posts widget', 'same-posts'),
    ],
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
    // No save function: the block is rendered on the server, see
    // render_same_posts_block() in same-posts-block.php.
    edit: Edit,
});