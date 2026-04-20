(function (blocks, blockEditor, components, element, i18n) {
  var registerBlockType = blocks.registerBlockType;
  var useBlockProps = blockEditor.useBlockProps;
  var InspectorControls = blockEditor.InspectorControls;
  var PanelColorSettings = blockEditor.PanelColorSettings;
  var PanelBody = components.PanelBody;
  var Placeholder = components.Placeholder;
  var RangeControl = components.RangeControl;
  var SelectControl = components.SelectControl;
  var TextControl = components.TextControl;
  var ToggleControl = components.ToggleControl;
  var Fragment = element.Fragment;
  var createElement = element.createElement;
  var __ = i18n.__;

  registerBlockType('wp-song-study/current-rehearsals', {
    edit: function (props) {
      var attributes = props.attributes || {};
      var setAttributes = props.setAttributes;
      var style = {};

      if (attributes.panelBackgroundColor) {
        style['--pd-rehearsal-custom-card-background-solid'] = attributes.panelBackgroundColor;
      }

      if (attributes.panelBorderColor) {
        style['--pd-rehearsal-custom-card-border'] = attributes.panelBorderColor;
      }

      if (attributes.panelGradientAccentColor) {
        style['--pd-rehearsal-custom-accent'] = attributes.panelGradientAccentColor;
      }

      if (attributes.panelGradientHighlightColor) {
        style['--pd-rehearsal-custom-highlight'] = attributes.panelGradientHighlightColor;
      }

      if (typeof attributes.panelGradientAccentOpacity === 'number') {
        style['--pd-rehearsal-custom-accent-opacity'] = attributes.panelGradientAccentOpacity + '%';
      }

      if (typeof attributes.panelGradientHighlightOpacity === 'number') {
        style['--pd-rehearsal-custom-highlight-opacity'] = attributes.panelGradientHighlightOpacity + '%';
      }

      if (attributes.headerBackgroundColor) {
        style['--pd-rehearsal-custom-header-background-solid'] = attributes.headerBackgroundColor;
      }

      if (attributes.headerBorderColor) {
        style['--pd-rehearsal-custom-header-border'] = attributes.headerBorderColor;
      }

      if (attributes.headerTextColor) {
        style['--pd-rehearsal-custom-header-text'] = attributes.headerTextColor;
      }

      if (attributes.headerTitleColor) {
        style['--pd-rehearsal-custom-header-title'] = attributes.headerTitleColor;
      }

      if (attributes.headerMutedColor) {
        style['--pd-rehearsal-custom-header-muted'] = attributes.headerMutedColor;
      }

      if (attributes.panelTextColor) {
        style['--pd-rehearsal-custom-panel-text'] = attributes.panelTextColor;
      }

      if (attributes.panelTitleColor) {
        style['--pd-rehearsal-custom-panel-title'] = attributes.panelTitleColor;
      }

      if (attributes.panelMutedColor) {
        style['--pd-rehearsal-custom-panel-muted'] = attributes.panelMutedColor;
      }

      if (typeof attributes.formPanelMinWidth === 'number' && attributes.formPanelMinWidth > 0) {
        style['--pd-rehearsal-form-panel-min'] = attributes.formPanelMinWidth + 'px';
      }

      var blockProps = useBlockProps({
        className:
          'is-layout-' +
          (attributes.layoutWidth || 'immersive') +
          ' ' +
          (attributes.usePanelGradient === false ? 'has-flat-panels' : 'has-panel-gradient'),
        style: style,
      });

      return createElement(
        Fragment,
        null,
        createElement(
          InspectorControls,
          null,
          createElement(
            PanelBody,
            { title: __('Current rehearsals settings', 'wp-song-study-blocks') },
            createElement(SelectControl, {
              label: __('Layout width', 'wp-song-study-blocks'),
              value: attributes.layoutWidth || 'immersive',
              options: [
                { label: __('Default', 'wp-song-study-blocks'), value: 'default' },
                { label: __('Wide', 'wp-song-study-blocks'), value: 'wide' },
                { label: __('Immersive', 'wp-song-study-blocks'), value: 'immersive' },
              ],
              onChange: function (layoutWidth) {
                setAttributes({ layoutWidth: layoutWidth });
              },
            }),
            createElement(ToggleControl, {
              label: __('Show admin link', 'wp-song-study-blocks'),
              checked: !!attributes.showAdminLink,
              onChange: function (showAdminLink) {
                setAttributes({ showAdminLink: showAdminLink });
              },
            }),
            createElement(ToggleControl, {
              label: __('Use panel gradient', 'wp-song-study-blocks'),
              checked: attributes.usePanelGradient !== false,
              onChange: function (usePanelGradient) {
                setAttributes({ usePanelGradient: usePanelGradient });
              },
            }),
            createElement(TextControl, {
              label: __('Login message', 'wp-song-study-blocks'),
              value: attributes.loginMessage || '',
              onChange: function (loginMessage) {
                setAttributes({ loginMessage: loginMessage });
              },
            }),
            createElement(RangeControl, {
              label: __('Main form minimum width', 'wp-song-study-blocks'),
              value: typeof attributes.formPanelMinWidth === 'number' ? attributes.formPanelMinWidth : 720,
              onChange: function (formPanelMinWidth) {
                setAttributes({ formPanelMinWidth: formPanelMinWidth == null ? 720 : formPanelMinWidth });
              },
              min: 480,
              max: 1400,
              step: 20,
              allowReset: true,
              resetFallbackValue: 720,
            }),
            createElement(RangeControl, {
              label: __('Gradient accent opacity', 'wp-song-study-blocks'),
              value: typeof attributes.panelGradientAccentOpacity === 'number' ? attributes.panelGradientAccentOpacity : 10,
              onChange: function (panelGradientAccentOpacity) {
                setAttributes({ panelGradientAccentOpacity: panelGradientAccentOpacity == null ? 10 : panelGradientAccentOpacity });
              },
              min: 0,
              max: 100,
              step: 1,
              allowReset: true,
              resetFallbackValue: 10,
            }),
            createElement(RangeControl, {
              label: __('Gradient secondary opacity', 'wp-song-study-blocks'),
              value: typeof attributes.panelGradientHighlightOpacity === 'number' ? attributes.panelGradientHighlightOpacity : 12,
              onChange: function (panelGradientHighlightOpacity) {
                setAttributes({ panelGradientHighlightOpacity: panelGradientHighlightOpacity == null ? 12 : panelGradientHighlightOpacity });
              },
              min: 0,
              max: 100,
              step: 1,
              allowReset: true,
              resetFallbackValue: 12,
            })
          ),
          createElement(PanelColorSettings, {
            title: __('Rehearsal panel colors', 'wp-song-study-blocks'),
            colorSettings: [
              {
                value: attributes.panelBackgroundColor || '',
                onChange: function (panelBackgroundColor) {
                  setAttributes({ panelBackgroundColor: panelBackgroundColor || '' });
                },
                label: __('Panel background', 'wp-song-study-blocks'),
              },
              {
                value: attributes.panelBorderColor || '',
                onChange: function (panelBorderColor) {
                  setAttributes({ panelBorderColor: panelBorderColor || '' });
                },
                label: __('Panel border', 'wp-song-study-blocks'),
              },
              {
                value: attributes.panelGradientAccentColor || '',
                onChange: function (panelGradientAccentColor) {
                  setAttributes({ panelGradientAccentColor: panelGradientAccentColor || '' });
                },
                label: __('Gradient accent', 'wp-song-study-blocks'),
              },
              {
                value: attributes.panelGradientHighlightColor || '',
                onChange: function (panelGradientHighlightColor) {
                  setAttributes({ panelGradientHighlightColor: panelGradientHighlightColor || '' });
                },
                label: __('Gradient secondary', 'wp-song-study-blocks'),
              },
              {
                value: attributes.panelTextColor || '',
                onChange: function (panelTextColor) {
                  setAttributes({ panelTextColor: panelTextColor || '' });
                },
                label: __('Panel text', 'wp-song-study-blocks'),
              },
              {
                value: attributes.panelTitleColor || '',
                onChange: function (panelTitleColor) {
                  setAttributes({ panelTitleColor: panelTitleColor || '' });
                },
                label: __('Panel titles', 'wp-song-study-blocks'),
              },
              {
                value: attributes.panelMutedColor || '',
                onChange: function (panelMutedColor) {
                  setAttributes({ panelMutedColor: panelMutedColor || '' });
                },
                label: __('Secondary text', 'wp-song-study-blocks'),
              },
            ],
          }),
          createElement(PanelColorSettings, {
            title: __('Rehearsal header colors', 'wp-song-study-blocks'),
            colorSettings: [
              {
                value: attributes.headerBackgroundColor || '',
                onChange: function (headerBackgroundColor) {
                  setAttributes({ headerBackgroundColor: headerBackgroundColor || '' });
                },
                label: __('Header background', 'wp-song-study-blocks'),
              },
              {
                value: attributes.headerBorderColor || '',
                onChange: function (headerBorderColor) {
                  setAttributes({ headerBorderColor: headerBorderColor || '' });
                },
                label: __('Header border', 'wp-song-study-blocks'),
              },
              {
                value: attributes.headerTextColor || '',
                onChange: function (headerTextColor) {
                  setAttributes({ headerTextColor: headerTextColor || '' });
                },
                label: __('Header text', 'wp-song-study-blocks'),
              },
              {
                value: attributes.headerTitleColor || '',
                onChange: function (headerTitleColor) {
                  setAttributes({ headerTitleColor: headerTitleColor || '' });
                },
                label: __('Header title', 'wp-song-study-blocks'),
              },
              {
                value: attributes.headerMutedColor || '',
                onChange: function (headerMutedColor) {
                  setAttributes({ headerMutedColor: headerMutedColor || '' });
                },
                label: __('Header secondary text', 'wp-song-study-blocks'),
              },
            ],
          })
        ),
        createElement(
          'div',
          blockProps,
          createElement(
            Placeholder,
            {
              label: __('Current Rehearsals', 'wp-song-study-blocks'),
              instructions: __(
                'Displays a frontend rehearsal workspace where each collaborator can define availability, vote for sessions, and review the rehearsal logbook.',
                'wp-song-study-blocks'
              ),
            },
            createElement(
              'p',
              null,
              __(
                'Use this block inside the dedicated Ensayos page template so the logged-in musician can manage their own rehearsal data without entering wp-admin.',
                'wp-song-study-blocks'
              )
            )
          )
        )
      );
    },
    save: function () {
      return null;
    },
  });
})(window.wp.blocks, window.wp.blockEditor, window.wp.components, window.wp.element, window.wp.i18n);
