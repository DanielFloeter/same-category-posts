/**
 * WordPress dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import { Fragment, useEffect, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import {
	Disabled,
	FormTokenField,
	PanelBody,
	RadioControl,
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
		includeTax,
		excludeTerms,
		excerpt,
		excerptLength,
		excerptMoreText,
		commentNum,
		date,
		useWpDateFormat,
		dateFormat,
		dateLink,
		author,
		thumb,
		thumbTop,
		thumbW,
		thumbH,
		useCssCropping,
	} = attributes;

	const blockProps = useBlockProps();

	// Post types with their taxonomies and terms, grouped the same way the
	// widget's form groups them. See same-posts-rest.php.
	const [ taxonomyGroups, setTaxonomyGroups ] = useState( [] );

	useEffect( () => {
		let stillMounted = true;

		apiFetch( { path: '/same-posts/v1/taxonomies' } )
			.then( ( data ) => {
				if ( stillMounted ) {
					setTaxonomyGroups( Array.isArray( data ) ? data : [] );
				}
			} )
			.catch( () => {
				if ( stillMounted ) {
					setTaxonomyGroups( [] );
				}
			} );

		return () => {
			stillMounted = false;
		};
	}, [] );

	/**
	 * The taxonomy chosen for a post type, or the widget's default for it.
	 *
	 * @param {Object} group One entry of taxonomyGroups.
	 *
	 * @return {string} Taxonomy name.
	 */
	const chosenTaxonomy = ( group ) =>
		( includeTax && includeTax[ group.postType ] ) || group.defaultTaxonomy;

	/**
	 * Records the taxonomy a post type is matched by.
	 *
	 * @param {Object} group    One entry of taxonomyGroups.
	 * @param {string} taxonomy Taxonomy name.
	 */
	const setChosenTaxonomy = ( group, taxonomy ) =>
		setAttributes( {
			includeTax: { ...includeTax, [ group.postType ]: taxonomy },
		} );

	/**
	 * Records the excluded terms of one taxonomy. An empty selection drops the
	 * taxonomy from the attribute rather than storing an empty list.
	 *
	 * @param {string}   taxonomy Taxonomy name.
	 * @param {number[]} termIds  Term ids to exclude.
	 */
	const setExcludedTerms = ( taxonomy, termIds ) => {
		const next = { ...excludeTerms };

		if ( termIds.length ) {
			next[ taxonomy ] = termIds;
		} else {
			delete next[ taxonomy ];
		}

		setAttributes( { excludeTerms: next } );
	};

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Title', 'same-posts' ) } initialOpen={false}>
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
				<PanelBody title={ __( 'Filter', 'same-posts' ) } initialOpen={false}>
					{ taxonomyGroups.map( ( group ) => {
						const chosen = chosenTaxonomy( group );
						const taxonomy =
							group.taxonomies.find(
								( entry ) => entry.name === chosen
							) || group.taxonomies[ 0 ];

						if ( ! taxonomy ) {
							return null;
						}

						const excluded = ( excludeTerms &&
							excludeTerms[ taxonomy.name ] ) || [];

						// Terms travel through FormTokenField by name, so a
						// taxonomy with two identically named terms can only
						// offer the first of them. The widget's multi-select
						// has the same blind spot.
						const nameOfTerm = ( id ) => {
							const term = taxonomy.terms.find(
								( entry ) => entry.id === id
							);
							return term ? term.name : null;
						};
						const idOfTerm = ( name ) => {
							const term = taxonomy.terms.find(
								( entry ) => entry.name === name
							);
							return term ? term.id : null;
						};

						return (
							<Fragment key={ group.postType }>
								<RadioControl
									label={ sprintf(
										/* translators: %s: post type label, e.g. Posts. */
										__( 'Show %s with:', 'same-posts' ),
										group.label
									) }
									selected={ chosen }
									options={ group.taxonomies.map(
										( entry ) => ( {
											label: sprintf(
												/* translators: %s: taxonomy label, e.g. Categories. */
												__(
													'Same "%s" and exclude:',
													'same-posts'
												),
												entry.label
											),
											value: entry.name,
										} )
									) }
									onChange={ ( value ) =>
										setChosenTaxonomy( group, value )
									}
								/>
								<FormTokenField
									label={ sprintf(
										/* translators: %s: taxonomy label, e.g. Categories. */
										__(
											'Excluded terms (%s)',
											'same-posts'
										),
										taxonomy.label
									) }
									value={ excluded
										.map( nameOfTerm )
										.filter( Boolean ) }
									suggestions={ taxonomy.terms.map(
										( entry ) => entry.name
									) }
									__experimentalExpandOnFocus
									onChange={ ( names ) =>
										setExcludedTerms(
											taxonomy.name,
											names
												.map( idOfTerm )
												.filter(
													( id ) => id !== null
												)
										)
									}
								/>
							</Fragment>
						);
					} ) }
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
							'Applies to the excluded terms above.',
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
				<PanelBody title={ __( 'Thumbnails', 'same-posts' ) } initialOpen={false}>
					<ToggleControl
						label={ __( 'Show post thumbnail', 'same-posts' ) }
						checked={ !! thumb }
						onChange={ () => setAttributes( { thumb: ! thumb } ) }
					/>
					{ thumb && (
						<>
							<ToggleControl
								label={ __( 'Thumbnail to top', 'same-posts' ) }
								help={ __(
									'Above the title instead of below it.',
									'same-posts'
								) }
								checked={ !! thumbTop }
								onChange={ () =>
									setAttributes( { thumbTop: ! thumbTop } )
								}
							/>
							<TextControl
								label={ __( 'Width (in pixels)', 'same-posts' ) }
								help={ __(
									"0 uses the theme's thumbnail size.",
									'same-posts'
								) }
								type="number"
								min="0"
								value={ thumbW }
								onChange={ ( value ) =>
									setAttributes( { thumbW: toCount( value ) } )
								}
							/>
							<TextControl
								label={ __(
									'Height (in pixels)',
									'same-posts'
								) }
								type="number"
								min="0"
								value={ thumbH }
								onChange={ ( value ) =>
									setAttributes( { thumbH: toCount( value ) } )
								}
							/>
							<ToggleControl
								label={ __(
									'CSS crop to requested size',
									'same-posts'
								) }
								help={ __(
									'Needs both dimensions.',
									'same-posts'
								) }
								checked={ !! useCssCropping }
								onChange={ () =>
									setAttributes( {
										useCssCropping: ! useCssCropping,
									} )
								}
							/>
						</>
					) }
				</PanelBody>
				<PanelBody title={ __( 'Post details', 'same-posts' ) } initialOpen={false}>
					<ToggleControl
						label={ __( 'Show post excerpt', 'same-posts' ) }
						checked={ !! excerpt }
						onChange={ () => setAttributes( { excerpt: ! excerpt } ) }
					/>
					{ excerpt && (
						<>
							<TextControl
								label={ __(
									'Excerpt length (in words)',
									'same-posts'
								) }
								help={ __(
									"0 keeps WordPress's own length.",
									'same-posts'
								) }
								type="number"
								min="0"
								value={ excerptLength }
								onChange={ ( value ) =>
									setAttributes( {
										excerptLength: toCount( value ),
									} )
								}
							/>
							<TextControl
								label={ __(
									"Excerpt 'more' text",
									'same-posts'
								) }
								placeholder={ __( '... more', 'same-posts' ) }
								value={ excerptMoreText }
								onChange={ ( value ) =>
									setAttributes( { excerptMoreText: value } )
								}
							/>
						</>
					) }
					<ToggleControl
						label={ __( 'Show number of comments', 'same-posts' ) }
						checked={ !! commentNum }
						onChange={ () =>
							setAttributes( { commentNum: ! commentNum } )
						}
					/>
					<ToggleControl
						label={ __( 'Show post date', 'same-posts' ) }
						checked={ !! date }
						onChange={ () => setAttributes( { date: ! date } ) }
					/>
					{ date && (
						<>
							<ToggleControl
								label={ __(
									'Use the WordPress Settings > General for the date format',
									'same-posts'
								) }
								checked={ !! useWpDateFormat }
								onChange={ () =>
									// An own format wins over this setting in
									// itemHTML(), so clear it when switching
									// over -- otherwise the toggle would look
									// active without doing anything.
									setAttributes( {
										useWpDateFormat: ! useWpDateFormat,
										dateFormat: useWpDateFormat
											? dateFormat
											: '',
									} )
								}
							/>
							{ ! useWpDateFormat && (
								<TextControl
									label={ __( 'Date format', 'same-posts' ) }
									placeholder="j M Y"
									value={ dateFormat }
									onChange={ ( value ) =>
										setAttributes( { dateFormat: value } )
									}
								/>
							) }
							<ToggleControl
								label={ __(
									'Make widget date link',
									'same-posts'
								) }
								checked={ !! dateLink }
								onChange={ () =>
									setAttributes( { dateLink: ! dateLink } )
								}
							/>
						</>
					) }
					<ToggleControl
						label={ __( 'Show post author', 'same-posts' ) }
						checked={ !! author }
						onChange={ () => setAttributes( { author: ! author } ) }
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
