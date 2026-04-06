(function (blocks, blockEditor, components, element, i18n) {
  var registerBlockType = blocks.registerBlockType;
  var InspectorControls = blockEditor.InspectorControls;
  var useBlockProps = blockEditor.useBlockProps;
  var PanelBody = components.PanelBody;
  var Placeholder = components.Placeholder;
  var ToggleControl = components.ToggleControl;
  var Fragment = element.Fragment;
  var createElement = element.createElement;
  var __ = i18n.__;

  registerBlockType('wp-song-study/project-collaborators', {
    edit: function (props) {
      var attributes = props.attributes;
      var setAttributes = props.setAttributes;
      var blockProps = useBlockProps();

      return createElement(
        Fragment,
        null,
        createElement(
          InspectorControls,
          null,
          createElement(
            PanelBody,
            { title: __('Collaborator block settings', 'wp-song-study-blocks') },
            createElement(ToggleControl, {
              label: __('Show avatar', 'wp-song-study-blocks'),
              checked: !!attributes.showAvatar,
              onChange: function (showAvatar) { setAttributes({ showAvatar: showAvatar }); }
            }),
            createElement(ToggleControl, {
              label: __('Show bio', 'wp-song-study-blocks'),
              checked: !!attributes.showBio,
              onChange: function (showBio) { setAttributes({ showBio: showBio }); }
            }),
            createElement(ToggleControl, {
              label: __('Show portfolio link', 'wp-song-study-blocks'),
              checked: !!attributes.showLink,
              onChange: function (showLink) { setAttributes({ showLink: showLink }); }
            })
          )
        ),
        createElement(
          'div',
          blockProps,
          createElement(Placeholder, {
            label: __('Project Collaborators', 'wp-song-study-blocks'),
            instructions: __('Shows the users related to the current project. Place it inside a proyecto template or single view.', 'wp-song-study-blocks')
          })
        )
      );
    },
    save: function () {
      return null;
    }
  });
})(window.wp.blocks, window.wp.blockEditor, window.wp.components, window.wp.element, window.wp.i18n);
