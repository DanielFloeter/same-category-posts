/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import {
	Disabled,
	PanelBody,
	SelectControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import ServerSideRender from '@wordpress/server-side-render';

/**
 * Options of the "Sort by" control.
 *
 * The values are the ones samePosts\Widget passes to WP_Query's orderby, and
 * they are validated against the same list in includes/block-attributes.php.
 */
const SORT_BY_OPTIONS = [
	{ label: __( 'Date', 'same-posts' ), value: 'date' },
	{ label: __( 'Title', 'same-posts' ), value: 'title' },
	{ label: __( 'Number of comments', 'same-posts' ), value: 'comment_count' },
	{ label: __( 'Random', 'same-posts' ), value: 'rand' },
];

/**
 * Reads a number field. An empty field means "no limit", which the widget
 * writes as 0.
 *
 * @param {string} value The raw field value.
 *
 * @return {number} The value as a non-negative integer.
 */
function toCount( value ) {
	const count = parseInt( value, 10 );
	return Number.isNaN( count ) || count < 0 ? 0 : count;
}

/**
 * Editor UI of the block.
 *
 * The panels mirror the sections of the classic widget's form (see
 * samePosts\Widget::form()), and the labels are taken from there so that both
 * are recognisably the same options. The "Post details" and "Thumbnails"
 * panels follow in phases 3 and 4 of plan.md, the taxonomy and term filters in
 * the second half of phase 2.
 *
 * @param {Object}   props               Block props.
 * @param {Object}   props.attributes    Block attributes.
 * @param {Function} props.setAttributes Attribute setter.
 *
 * @return {WPElement} Element to render.
 */
export default function Edit( { attributes, setAttributes } ) {
	const {
		hideTitle,
		title,
		titleLink,
		sortBy,
		ascSortOrder,
		num,
		separateCategories,
		numPerCate,
		excludeCurrentPost,
		excludeStickyPosts,
		excludeChildren,
		excludeNoChildren,
		disableCSS,
		disableFontStyles,
		disableThemeStyles,
	} = attributes;

	const blockProps = useBlockProps( {
		className: disableThemeStyles ? 'widget-title' : '',
	} );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Title', 'same-posts' ) }>
					<ToggleControl
						label={ __( 'Hide title', 'same-posts' ) }
						checked={ !! hideTitle }
						onChange={ () =>
							setAttributes( { hideTitle: ! hideTitle } )
						}
					/>
					<TextControl
						label={ __( 'Title', 'same-posts' ) }
						help={ __(
							'%cat% is replaced by the first term, %cat-all% by all of them.',
							'same-posts'
						) }
						value={ title }
						onChange={ ( value ) =>
							setAttributes( { title: value } )
						}
					/>
					<ToggleControl
						label={ __( 'Make widget title link', 'same-posts' ) }
						checked={ !! titleLink }
						onChange={ () =>
							setAttributes( { titleLink: ! titleLink } )
						}
					/>
				</PanelBody>
				<PanelBody title={ __( 'Filter', 'same-posts' ) }>
					<SelectControl
						label={ __( 'Sort by', 'same-posts' ) }
						value={ sortBy }
						options={ SORT_BY_OPTIONS }
						onChange={ ( value ) =>
							setAttributes( { sortBy: value } )
						}
					/>
					<ToggleControl
						label={ __(
							'Reverse sort order (ascending)',
							'same-posts'
						) }
						checked={ !! ascSortOrder }
						onChange={ () =>
							setAttributes( { ascSortOrder: ! ascSortOrder } )
						}
					/>
					<TextControl
						label={ __(
							'Number of posts to show (overall)',
							'same-posts'
						) }
						help={ __( '0 shows all of them.', 'same-posts' ) }
						type="number"
						min="0"
						value={ num }
						onChange={ ( value ) =>
							setAttributes( { num: toCount( value ) } )
						}
					/>
					<ToggleControl
						label={ __(
							'Separate terms (If more than one assigned)',
							'same-posts'
						) }
						checked={ !! separateCategories }
						onChange={ () =>
							setAttributes( {
								separateCategories: ! separateCategories,
							} )
						}
					/>
					{ separateCategories && (
						<TextControl
							label={ __(
								'Max. number of posts per separated categories',
								'same-posts'
							) }
							help={ __( '0 shows all of them.', 'same-posts' ) }
							type="number"
							min="0"
							value={ numPerCate }
							onChange={ ( value ) =>
								setAttributes( {
									numPerCate: toCount( value ),
								} )
							}
						/>
					) }
					<ToggleControl
						label={ __( 'Exclude current post', 'same-posts' ) }
						checked={ !! excludeCurrentPost }
						onChange={ () =>
							setAttributes( {
								excludeCurrentPost: ! excludeCurrentPost,
							} )
						}
					/>
					<ToggleControl
						label={ __( 'Exclude sticky posts', 'same-posts' ) }
						checked={ !! excludeStickyPosts }
						onChange={ () =>
							setAttributes( {
								excludeStickyPosts: ! excludeStickyPosts,
							} )
						}
					/>
					<ToggleControl
						label={ __( 'Exclude children', 'same-posts' ) }
						help={ __(
							"Look only at the post's own terms, not at their child terms.",
							'same-posts'
						) }
						checked={ !! excludeChildren }
						onChange={ () =>
							setAttributes( {
								excludeChildren: ! excludeChildren,
							} )
						}
					/>
					<ToggleControl
						label={ __(
							'Perform the exclusion without children',
							'same-posts'
						) }
						help={ __(
							'Applies to excluded terms, which can be chosen once the term filter lands.',
							'same-posts'
						) }
						checked={ !! excludeNoChildren }
						onChange={ () =>
							setAttributes( {
								excludeNoChildren: ! excludeNoChildren,
							} )
						}
					/>
				</PanelBody>
				<PanelBody title={ __( 'General', 'same-posts' ) }>
					<ToggleControl
						label={ __( 'Disable the built-in CSS', 'same-posts' ) }
						checked={ !! disableCSS }
						onChange={ () =>
							setAttributes( { disableCSS: ! disableCSS } )
						}
					/>
					<ToggleControl
						label={ __( 'Disable only font styles', 'same-posts' ) }
						checked={ !! disableFontStyles }
						onChange={ () =>
							setAttributes( {
								disableFontStyles: ! disableFontStyles,
							} )
						}
					/>
					<ToggleControl
						label={ __( "Disable Theme's styles", 'same-posts' ) }
						checked={ !! disableThemeStyles }
						onChange={ () =>
							setAttributes( {
								disableThemeStyles: ! disableThemeStyles,
							} )
						}
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<Disabled>
					<ServerSideRender
						block="tiptip/same-posts-block"
						attributes={ attributes }
					/>
				</Disabled>
			</div>
		</>
	);
}
