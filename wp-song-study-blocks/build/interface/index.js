(function (blocks, blockEditor, components, element, i18n) {
  var registerBlockType = blocks.registerBlockType;
  var InspectorControls = blockEditor.InspectorControls;
  var useBlockProps = blockEditor.useBlockProps;
  var BaseControl = components.BaseControl;
  var Button = components.Button;
  var ColorPalette = components.ColorPalette;
  var PanelBody = components.PanelBody;
  var Placeholder = components.Placeholder;
  var RangeControl = components.RangeControl;
  var ToggleControl = components.ToggleControl;
  var Fragment = element.Fragment;
  var createElement = element.createElement;
  var __ = i18n.__;

  var DEFAULTS = {
    panelBackgroundColor: '#ffffff',
    panelBackgroundOpacity: 80,
    textColor: '#4b5563',
    headingColor: '#1f2937',
    buttonColor: '#1e3a8a',
    buttonTextColor: '#ffffff',
    buttonEmphasisColor: '#e7ecf6',
    buttonEmphasisTextColor: '#1e3a8a',
    buttonDangerColor: '#7f1d1d',
    buttonDangerTextColor: '#ffffff'
  };

  function normalizeOpacity(value) {
    var parsedValue = Number.isFinite(value) ? value : parseInt(value, 10);

    if (Number.isNaN(parsedValue)) {
      return DEFAULTS.panelBackgroundOpacity;
    }

    return Math.min(100, Math.max(0, parsedValue));
  }

  function hexToRgba(hex, opacity) {
    if (typeof hex !== 'string') {
      return 'rgba(255, 255, 255, ' + opacity + ')';
    }

    var normalized = hex.replace('#', '').trim();
    var value = normalized.length === 3
      ? normalized.split('').map(function (char) {
          return '' + char + char;
        }).join('')
      : normalized;

    if (!/^[\da-fA-F]{6}$/.test(value)) {
      return 'rgba(255, 255, 255, ' + opacity + ')';
    }

    var red = parseInt(value.slice(0, 2), 16);
    var green = parseInt(value.slice(2, 4), 16);
    var blue = parseInt(value.slice(4, 6), 16);

    return 'rgba(' + red + ', ' + green + ', ' + blue + ', ' + opacity + ')';
  }

  function ColorSetting(props) {
    return createElement(
      BaseControl,
      { label: props.label },
      createElement(ColorPalette, {
        value: props.value,
        onChange: function (nextValue) {
          props.onChange(nextValue || props.defaultValue);
        }
      }),
      createElement(
        'div',
        {
          style: {
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'space-between',
            gap: '8px',
            marginTop: '8px'
          }
        },
        createElement('code', null, props.value),
        createElement(
          Button,
          {
            isSmall: true,
            onClick: function () {
              props.onChange(props.defaultValue);
            }
          },
          __('Reset', 'wp-song-study-blocks')
        )
      )
    );
  }

  registerBlockType('wp-song-study/interface', {
    edit: function (props) {
      var attributes = props.attributes;
      var setAttributes = props.setAttributes;
      var blockProps = useBlockProps();
      var panelBackgroundOpacity = normalizeOpacity(attributes.panelBackgroundOpacity);
      var panelBackgroundColor = attributes.panelBackgroundColor || DEFAULTS.panelBackgroundColor;
      var textColor = attributes.textColor || DEFAULTS.textColor;
      var headingColor = attributes.headingColor || DEFAULTS.headingColor;
      var buttonColor = attributes.buttonColor || DEFAULTS.buttonColor;
      var buttonTextColor = attributes.buttonTextColor || DEFAULTS.buttonTextColor;
      var buttonEmphasisColor = attributes.buttonEmphasisColor || DEFAULTS.buttonEmphasisColor;
      var buttonEmphasisTextColor =
        attributes.buttonEmphasisTextColor || DEFAULTS.buttonEmphasisTextColor;
      var buttonDangerColor = attributes.buttonDangerColor || DEFAULTS.buttonDangerColor;
      var buttonDangerTextColor =
        attributes.buttonDangerTextColor || DEFAULTS.buttonDangerTextColor;
      var previewPanelStyle = {
        background: hexToRgba(panelBackgroundColor, panelBackgroundOpacity / 100),
        border: '1px solid rgba(15, 23, 42, 0.12)',
        borderRadius: '12px',
        padding: '16px',
        marginBottom: '16px',
        boxShadow: '0 12px 28px rgba(15, 23, 42, 0.08)'
      };
      var previewTextStyle = {
        color: textColor,
        margin: '4px 0 0'
      };
      var previewTitleStyle = {
        color: headingColor,
        display: 'block',
        fontSize: '16px',
        marginBottom: '4px'
      };
      var previewActionsStyle = {
        display: 'flex',
        gap: '8px',
        flexWrap: 'wrap',
        marginTop: '12px'
      };
      var previewPrimaryButtonStyle = {
        display: 'inline-flex',
        alignItems: 'center',
        justifyContent: 'center',
        minWidth: '112px',
        padding: '8px 14px',
        borderRadius: '999px',
        background: buttonColor,
        border: '1px solid ' + buttonColor,
        color: buttonTextColor,
        fontWeight: 600
      };
      var previewEmphasisButtonStyle = Object.assign({}, previewPrimaryButtonStyle, {
        background: buttonEmphasisColor,
        borderColor: buttonEmphasisColor,
        color: buttonEmphasisTextColor
      });
      var previewDangerButtonStyle = Object.assign({}, previewPrimaryButtonStyle, {
        background: buttonDangerColor,
        borderColor: buttonDangerColor,
        color: buttonDangerTextColor
      });

      return createElement(
        Fragment,
        null,
        createElement(
          InspectorControls,
          null,
          createElement(
            PanelBody,
            { title: __('Interface settings', 'wp-song-study-blocks') },
            createElement(ToggleControl, {
              label: __('Start in compact mode', 'wp-song-study-blocks'),
              checked: !!attributes.compactMode,
              onChange: function (compactMode) {
                setAttributes({ compactMode: compactMode });
              }
            })
          ),
          createElement(
            PanelBody,
            {
              title: __('Reader appearance', 'wp-song-study-blocks'),
              initialOpen: false
            },
            createElement(ColorSetting, {
              label: __('Panel background', 'wp-song-study-blocks'),
              value: panelBackgroundColor,
              defaultValue: DEFAULTS.panelBackgroundColor,
              onChange: function (nextValue) {
                setAttributes({ panelBackgroundColor: nextValue });
              }
            }),
            createElement(RangeControl, {
              label: __('Panel transparency', 'wp-song-study-blocks'),
              value: panelBackgroundOpacity,
              onChange: function (nextValue) {
                setAttributes({ panelBackgroundOpacity: normalizeOpacity(nextValue) });
              },
              min: 0,
              max: 100
            }),
            createElement(ColorSetting, {
              label: __('Body text color', 'wp-song-study-blocks'),
              value: textColor,
              defaultValue: DEFAULTS.textColor,
              onChange: function (nextValue) {
                setAttributes({ textColor: nextValue });
              }
            }),
            createElement(ColorSetting, {
              label: __('Title color', 'wp-song-study-blocks'),
              value: headingColor,
              defaultValue: DEFAULTS.headingColor,
              onChange: function (nextValue) {
                setAttributes({ headingColor: nextValue });
              }
            }),
            createElement(ColorSetting, {
              label: __('Primary button color', 'wp-song-study-blocks'),
              value: buttonColor,
              defaultValue: DEFAULTS.buttonColor,
              onChange: function (nextValue) {
                setAttributes({ buttonColor: nextValue });
              }
            }),
            createElement(ColorSetting, {
              label: __('Primary button text', 'wp-song-study-blocks'),
              value: buttonTextColor,
              defaultValue: DEFAULTS.buttonTextColor,
              onChange: function (nextValue) {
                setAttributes({ buttonTextColor: nextValue });
              }
            }),
            createElement(ColorSetting, {
              label: __('Emphasis button color', 'wp-song-study-blocks'),
              value: buttonEmphasisColor,
              defaultValue: DEFAULTS.buttonEmphasisColor,
              onChange: function (nextValue) {
                setAttributes({ buttonEmphasisColor: nextValue });
              }
            }),
            createElement(ColorSetting, {
              label: __('Emphasis button text', 'wp-song-study-blocks'),
              value: buttonEmphasisTextColor,
              defaultValue: DEFAULTS.buttonEmphasisTextColor,
              onChange: function (nextValue) {
                setAttributes({ buttonEmphasisTextColor: nextValue });
              }
            }),
            createElement(ColorSetting, {
              label: __('Delete button color', 'wp-song-study-blocks'),
              value: buttonDangerColor,
              defaultValue: DEFAULTS.buttonDangerColor,
              onChange: function (nextValue) {
                setAttributes({ buttonDangerColor: nextValue });
              }
            }),
            createElement(ColorSetting, {
              label: __('Delete button text', 'wp-song-study-blocks'),
              value: buttonDangerTextColor,
              defaultValue: DEFAULTS.buttonDangerTextColor,
              onChange: function (nextValue) {
                setAttributes({ buttonDangerTextColor: nextValue });
              }
            })
          )
        ),
        createElement(
          'div',
          blockProps,
          createElement(
            'div',
            { style: previewPanelStyle },
            createElement(
              'strong',
              { style: previewTitleStyle },
              __('Reader quick preview', 'wp-song-study-blocks')
            ),
            createElement(
              'p',
              { style: previewTextStyle },
              __('These colors will be applied to the public reader panel, texts, titles and buttons.', 'wp-song-study-blocks')
            ),
            createElement(
              'div',
              { style: previewActionsStyle },
              createElement(
                'span',
                { style: previewPrimaryButtonStyle },
                __('Primary button', 'wp-song-study-blocks')
              ),
              createElement(
                'span',
                { style: previewEmphasisButtonStyle },
                __('Emphasis button', 'wp-song-study-blocks')
              ),
              createElement(
                'span',
                { style: previewDangerButtonStyle },
                __('Delete button', 'wp-song-study-blocks')
              )
            )
          ),
          createElement(Placeholder, {
            label: __('Song Study Interface', 'wp-song-study-blocks'),
            instructions: __('Server-side rendered block. The interactive song browser/reader will appear on the frontend.', 'wp-song-study-blocks')
          })
        )
      );
    },
    save: function () {
      return null;
    }
  });
})(window.wp.blocks, window.wp.blockEditor, window.wp.components, window.wp.element, window.wp.i18n);
