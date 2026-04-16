/**
 * PP Gallery Plus -- editor registration.
 *
 * Registers the block client-side with ServerSideRender preview
 * and Inspector sidebar controls.
 */
(function () {
    "use strict";

    var blocks = wp.blocks;
    var el = wp.element.createElement;
    var Fragment = wp.element.Fragment;
    var InspectorControls = wp.blockEditor.InspectorControls;
    var PanelBody = wp.components.PanelBody;
    var RangeControl = wp.components.RangeControl;
    var ToggleControl = wp.components.ToggleControl;

    // ServerSideRender can live in different places depending on WP version
    var ServerSideRender = wp.serverSideRender || (wp.components && wp.components.ServerSideRender);

    console.log('[ppgal2] editor.js loaded');

    // Guard: skip if already registered (avoids conflict with block.json auto-registration)
    if (blocks.getBlockType('ppgal2/gallery')) {
        console.log('[ppgal2] block already registered server-side, skipping JS registration');
        return;
    }

    blocks.registerBlockType('ppgal2/gallery', {
        title: 'PP Gallery Plus',
        icon: 'format-gallery',
        category: 'media',
        keywords: ['gallery', 'portfolio', 'grid'],
        description: 'Filterable image gallery with lightbox and infinite scroll.',

        attributes: {
            postsPerPage: { type: 'number', default: 20 },
            showAltThumbs: { type: 'boolean', default: true },
        },

        edit: function (props) {
            var attributes = props.attributes;

            // If ServerSideRender is available, show live preview
            var preview;
            if (ServerSideRender) {
                preview = el(ServerSideRender, {
                    block: 'ppgal2/gallery',
                    attributes: attributes,
                });
            } else {
                preview = el(
                    'div',
                    {
                        style: {
                            padding: '40px 20px',
                            background: '#f0f0f0',
                            textAlign: 'center',
                            border: '1px dashed #ccc',
                        },
                    },
                    el('p', { style: { margin: 0 } }, 'PP Gallery Plus'),
                    el(
                        'p',
                        { style: { color: '#666', fontSize: '13px', margin: '8px 0 0' } },
                        'Gallery preview not available in editor. Check the frontend.'
                    )
                );
            }

            return el(
                Fragment,
                null,
                el(
                    InspectorControls,
                    null,
                    el(
                        PanelBody,
                        { title: 'Gallery Settings', initialOpen: true },
                        el(RangeControl, {
                            label: 'Posts per page',
                            value: attributes.postsPerPage,
                            onChange: function (val) {
                                props.setAttributes({ postsPerPage: val });
                            },
                            min: 4,
                            max: 100,
                        }),
                        el(ToggleControl, {
                            label: 'Show alternate thumbnails',
                            checked: attributes.showAltThumbs,
                            onChange: function (val) {
                                props.setAttributes({ showAltThumbs: val });
                            },
                        })
                    )
                ),
                preview
            );
        },

        save: function () {
            return null;
        },
    });

    console.log('[ppgal2] block registered successfully');
})();
