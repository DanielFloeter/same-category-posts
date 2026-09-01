/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import {
	Disabled,
	PanelBody,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import ServerSideRender from '@wordpress/server-side-render';

/**
 * Editor UI of the block.
 *
 * The panels mirror the sections of the classic widget's form (see
 * samePosts\Widget::form()). Only "Title" and "General" exist so far; the
 * "Filter", "Post details" and "Thumbnails" panels follow in phases 2 to 4
 * of plan.md, once the shared PHP render core is in place.
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
