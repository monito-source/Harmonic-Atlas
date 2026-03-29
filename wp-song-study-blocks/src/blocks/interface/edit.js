import { __ } from '@wordpress/i18n';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
  BaseControl,
  Button,
  ColorPalette,
  PanelBody,
  Placeholder,
  RangeControl,
  ToggleControl,
} from '@wordpress/components';

const DEFAULTS = {
  panelBackgroundColor: '#ffffff',
  panelBackgroundOpacity: 80,
  textColor: '#4b5563',
  headingColor: '#1f2937',
  buttonColor: '#1e3a8a',
  buttonTextColor: '#ffffff',
  buttonEmphasisColor: '#e7ecf6',
  buttonEmphasisTextColor: '#1e3a8a',
  buttonDangerColor: '#7f1d1d',
  buttonDangerTextColor: '#ffffff',
};

function normalizeOpacity(value) {
  const parsedValue = Number.isFinite(value) ? value : parseInt(value, 10);

  if (Number.isNaN(parsedValue)) {
    return DEFAULTS.panelBackgroundOpacity;
  }

  return Math.min(100, Math.max(0, parsedValue));
}

function hexToRgba(hex, opacity) {
  if (typeof hex !== 'string') {
    return `rgba(255, 255, 255, ${opacity})`;
  }

  const normalized = hex.replace('#', '').trim();
  const value = normalized.length === 3
    ? normalized
        .split('')
        .map((char) => `${char}${char}`)
        .join('')
    : normalized;

  if (!/^[\da-fA-F]{6}$/.test(value)) {
    return `rgba(255, 255, 255, ${opacity})`;
  }

  const red = parseInt(value.slice(0, 2), 16);
  const green = parseInt(value.slice(2, 4), 16);
  const blue = parseInt(value.slice(4, 6), 16);

  return `rgba(${red}, ${green}, ${blue}, ${opacity})`;
}

function ColorSetting({ label, value, defaultValue, onChange }) {
  return (
    <BaseControl label={label}>
      <ColorPalette value={value} onChange={(nextValue) => onChange(nextValue || defaultValue)} />
      <div
        style={{
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'space-between',
          gap: '8px',
          marginTop: '8px',
        }}
      >
        <code>{value}</code>
        <Button isSmall onClick={() => onChange(defaultValue)}>
          {__('Reset', 'wp-song-study-blocks')}
        </Button>
      </div>
    </BaseControl>
  );
}

export default function Edit({ attributes, setAttributes }) {
  const panelBackgroundOpacity = normalizeOpacity(attributes.panelBackgroundOpacity);
  const panelBackgroundColor = attributes.panelBackgroundColor || DEFAULTS.panelBackgroundColor;
  const textColor = attributes.textColor || DEFAULTS.textColor;
  const headingColor = attributes.headingColor || DEFAULTS.headingColor;
  const buttonColor = attributes.buttonColor || DEFAULTS.buttonColor;
  const buttonTextColor = attributes.buttonTextColor || DEFAULTS.buttonTextColor;
  const buttonEmphasisColor = attributes.buttonEmphasisColor || DEFAULTS.buttonEmphasisColor;
  const buttonEmphasisTextColor =
    attributes.buttonEmphasisTextColor || DEFAULTS.buttonEmphasisTextColor;
  const buttonDangerColor = attributes.buttonDangerColor || DEFAULTS.buttonDangerColor;
  const buttonDangerTextColor =
    attributes.buttonDangerTextColor || DEFAULTS.buttonDangerTextColor;

  const blockProps = useBlockProps();
  const previewPanelStyle = {
    background: hexToRgba(panelBackgroundColor, panelBackgroundOpacity / 100),
    border: '1px solid rgba(15, 23, 42, 0.12)',
    borderRadius: '12px',
    padding: '16px',
    marginBottom: '16px',
    boxShadow: '0 12px 28px rgba(15, 23, 42, 0.08)',
  };
  const previewTextStyle = {
    color: textColor,
    margin: '4px 0 0',
  };
  const previewTitleStyle = {
    color: headingColor,
    display: 'block',
    fontSize: '16px',
    marginBottom: '4px',
  };
  const previewActionsStyle = {
    display: 'flex',
    gap: '8px',
    flexWrap: 'wrap',
    marginTop: '12px',
  };
  const previewPrimaryButtonStyle = {
    display: 'inline-flex',
    alignItems: 'center',
    justifyContent: 'center',
    minWidth: '112px',
    padding: '8px 14px',
    borderRadius: '999px',
    background: buttonColor,
    border: `1px solid ${buttonColor}`,
    color: buttonTextColor,
    fontWeight: 600,
  };
  const previewEmphasisButtonStyle = {
    ...previewPrimaryButtonStyle,
    background: buttonEmphasisColor,
    borderColor: buttonEmphasisColor,
    color: buttonEmphasisTextColor,
  };
  const previewDangerButtonStyle = {
    ...previewPrimaryButtonStyle,
    background: buttonDangerColor,
    borderColor: buttonDangerColor,
    color: buttonDangerTextColor,
  };

  return (
    <>
      <InspectorControls>
        <PanelBody title={__('Interface settings', 'wp-song-study-blocks')}>
          <ToggleControl
            label={__('Start in compact mode', 'wp-song-study-blocks')}
            checked={!!attributes.compactMode}
            onChange={(compactMode) => setAttributes({ compactMode })}
          />
        </PanelBody>
        <PanelBody title={__('Reader appearance', 'wp-song-study-blocks')} initialOpen={false}>
          <ColorSetting
            label={__('Panel background', 'wp-song-study-blocks')}
            value={panelBackgroundColor}
            defaultValue={DEFAULTS.panelBackgroundColor}
            onChange={(nextValue) => setAttributes({ panelBackgroundColor: nextValue })}
          />
          <RangeControl
            label={__('Panel transparency', 'wp-song-study-blocks')}
            value={panelBackgroundOpacity}
            onChange={(nextValue) =>
              setAttributes({ panelBackgroundOpacity: normalizeOpacity(nextValue) })
            }
            min={0}
            max={100}
          />
          <ColorSetting
            label={__('Body text color', 'wp-song-study-blocks')}
            value={textColor}
            defaultValue={DEFAULTS.textColor}
            onChange={(nextValue) => setAttributes({ textColor: nextValue })}
          />
          <ColorSetting
            label={__('Title color', 'wp-song-study-blocks')}
            value={headingColor}
            defaultValue={DEFAULTS.headingColor}
            onChange={(nextValue) => setAttributes({ headingColor: nextValue })}
          />
          <ColorSetting
            label={__('Primary button color', 'wp-song-study-blocks')}
            value={buttonColor}
            defaultValue={DEFAULTS.buttonColor}
            onChange={(nextValue) => setAttributes({ buttonColor: nextValue })}
          />
          <ColorSetting
            label={__('Primary button text', 'wp-song-study-blocks')}
            value={buttonTextColor}
            defaultValue={DEFAULTS.buttonTextColor}
            onChange={(nextValue) => setAttributes({ buttonTextColor: nextValue })}
          />
          <ColorSetting
            label={__('Emphasis button color', 'wp-song-study-blocks')}
            value={buttonEmphasisColor}
            defaultValue={DEFAULTS.buttonEmphasisColor}
            onChange={(nextValue) => setAttributes({ buttonEmphasisColor: nextValue })}
          />
          <ColorSetting
            label={__('Emphasis button text', 'wp-song-study-blocks')}
            value={buttonEmphasisTextColor}
            defaultValue={DEFAULTS.buttonEmphasisTextColor}
            onChange={(nextValue) => setAttributes({ buttonEmphasisTextColor: nextValue })}
          />
          <ColorSetting
            label={__('Delete button color', 'wp-song-study-blocks')}
            value={buttonDangerColor}
            defaultValue={DEFAULTS.buttonDangerColor}
            onChange={(nextValue) => setAttributes({ buttonDangerColor: nextValue })}
          />
          <ColorSetting
            label={__('Delete button text', 'wp-song-study-blocks')}
            value={buttonDangerTextColor}
            defaultValue={DEFAULTS.buttonDangerTextColor}
            onChange={(nextValue) => setAttributes({ buttonDangerTextColor: nextValue })}
          />
        </PanelBody>
      </InspectorControls>
      <div {...blockProps}>
        <div style={previewPanelStyle}>
          <strong style={previewTitleStyle}>{__('Reader quick preview', 'wp-song-study-blocks')}</strong>
          <p style={previewTextStyle}>
            {__(
              'These colors will be applied to the public reader panel, texts, titles and buttons.',
              'wp-song-study-blocks',
            )}
          </p>
          <div style={previewActionsStyle}>
            <span style={previewPrimaryButtonStyle}>
              {__('Primary button', 'wp-song-study-blocks')}
            </span>
            <span style={previewEmphasisButtonStyle}>
              {__('Emphasis button', 'wp-song-study-blocks')}
            </span>
            <span style={previewDangerButtonStyle}>
              {__('Delete button', 'wp-song-study-blocks')}
            </span>
          </div>
        </div>
        <Placeholder
          label={__('Song Study Interface', 'wp-song-study-blocks')}
          instructions={__('Server-side rendered block. The interactive song browser/reader will appear on the frontend.', 'wp-song-study-blocks')}
        />
      </div>
    </>
  );
}
