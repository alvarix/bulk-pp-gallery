/**
 * PP Gallery Plus -- editor registration.
 *
 * Registers the block in the editor using ServerSideRender
 * so the live preview matches the frontend output.
 */
(function () {
    var registerBlockType = wp.blocks.registerBlockType;
    var ServerSideRender = wp.serverSideRender;
    var el = wp.element.createElement;
    var InspectorControls = wp.blockEditor.InspectorControls;
    var PanelBody = wp.components.PanelBody;
    var RangeControl = wp.components.RangeControl;
    var ToggleControl = wp.components.ToggleControl;

    registerBlockType('ppgal2/gallery', {
        edit: function (props) {
            var attributes = props.attributes;

            return el(
                wp.element.Fragment,
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
                el(ServerSideRender, {
                    block: 'ppgal2/gallery',
                    attributes: attributes,
                })
            );
        },

        save: function () {
            // Server-rendered block, no save output
            return null;
        },
    });
})();
