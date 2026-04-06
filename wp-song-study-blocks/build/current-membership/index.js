(function (blocks, blockEditor, components, element, i18n) {
  var registerBlockType = blocks.registerBlockType
  var InspectorControls = blockEditor.InspectorControls
  var useBlockProps = blockEditor.useBlockProps
  var PanelBody = components.PanelBody
  var Placeholder = components.Placeholder
  var TextControl = components.TextControl
  var ToggleControl = components.ToggleControl
  var Fragment = element.Fragment
  var createElement = element.createElement
  var __ = i18n.__

  registerBlockType('wp-song-study/current-membership', {
    edit: function (props) {
      var attributes = props.attributes
      var setAttributes = props.setAttributes
      var blockProps = useBlockProps()

      return createElement(
        Fragment,
        null,
        createElement(
          InspectorControls,
          null,
            createElement(
              PanelBody,
              { title: __('Current membership settings', 'wp-song-study-blocks') },
            createElement(TextControl, {
              label: __('Default target user ID', 'wp-song-study-blocks'),
              help: __(
                'Optional. Administrators can still switch to another user from the frontend view.',
                'wp-song-study-blocks'
              ),
              type: 'number',
              value: attributes.targetUserId || '',
              onChange: function (targetUserId) {
                setAttributes({ targetUserId: parseInt(targetUserId || '0', 10) || 0 })
              },
            }),
            createElement(ToggleControl, {
              label: __('Show projects', 'wp-song-study-blocks'),
              checked: !!attributes.showProjects,
              onChange: function (showProjects) {
                setAttributes({ showProjects: showProjects })
              },
            }),
            createElement(ToggleControl, {
              label: __('Show public preview', 'wp-song-study-blocks'),
              checked: !!attributes.showPreview,
              onChange: function (showPreview) {
                setAttributes({ showPreview: showPreview })
              },
            }),
            createElement(ToggleControl, {
              label: __('Show WordPress profile link', 'wp-song-study-blocks'),
              checked: !!attributes.showAdminLink,
              onChange: function (showAdminLink) {
                setAttributes({ showAdminLink: showAdminLink })
              },
            }),
            createElement(TextControl, {
              label: __('Login message', 'wp-song-study-blocks'),
              value: attributes.loginMessage || '',
              onChange: function (loginMessage) {
                setAttributes({ loginMessage: loginMessage })
              },
            })
          )
        ),
        createElement(
          'div',
          blockProps,
          createElement(Placeholder, {
            label: __('Current Membership', 'wp-song-study-blocks'),
            instructions: __(
              'Displays a frontend space where the logged-in user can edit their own presskit and review their projects.',
              'wp-song-study-blocks'
            ),
          })
        )
      )
    },
    save: function () {
      return null
    },
  })
})(window.wp.blocks, window.wp.blockEditor, window.wp.components, window.wp.element, window.wp.i18n)
