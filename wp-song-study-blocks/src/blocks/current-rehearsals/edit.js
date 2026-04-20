import { InspectorControls, PanelColorSettings, useBlockProps } from '@wordpress/block-editor'
import { PanelBody, Placeholder, RangeControl, SelectControl, TextControl, ToggleControl } from '@wordpress/components'
import { Fragment } from '@wordpress/element'
import { __ } from '@wordpress/i18n'

export default function Edit({ attributes, setAttributes }) {
  const style = {}

  if (attributes.panelBackgroundColor) {
    style['--pd-rehearsal-custom-card-background-solid'] = attributes.panelBackgroundColor
  }

  if (attributes.panelBorderColor) {
    style['--pd-rehearsal-custom-card-border'] = attributes.panelBorderColor
  }

  if (attributes.panelGradientAccentColor) {
    style['--pd-rehearsal-custom-accent'] = attributes.panelGradientAccentColor
  }

  if (attributes.panelGradientHighlightColor) {
    style['--pd-rehearsal-custom-highlight'] = attributes.panelGradientHighlightColor
  }

  if (typeof attributes.panelGradientAccentOpacity === 'number') {
    style['--pd-rehearsal-custom-accent-opacity'] = `${attributes.panelGradientAccentOpacity}%`
  }

  if (typeof attributes.panelGradientHighlightOpacity === 'number') {
    style['--pd-rehearsal-custom-highlight-opacity'] = `${attributes.panelGradientHighlightOpacity}%`
  }

  if (attributes.headerBackgroundColor) {
    style['--pd-rehearsal-custom-header-background-solid'] = attributes.headerBackgroundColor
  }

  if (attributes.headerBorderColor) {
    style['--pd-rehearsal-custom-header-border'] = attributes.headerBorderColor
  }

  if (attributes.headerTextColor) {
    style['--pd-rehearsal-custom-header-text'] = attributes.headerTextColor
  }

  if (attributes.headerTitleColor) {
    style['--pd-rehearsal-custom-header-title'] = attributes.headerTitleColor
  }

  if (attributes.headerMutedColor) {
    style['--pd-rehearsal-custom-header-muted'] = attributes.headerMutedColor
  }

  if (attributes.panelTextColor) {
    style['--pd-rehearsal-custom-panel-text'] = attributes.panelTextColor
  }

  if (attributes.panelTitleColor) {
    style['--pd-rehearsal-custom-panel-title'] = attributes.panelTitleColor
  }

  if (attributes.panelMutedColor) {
    style['--pd-rehearsal-custom-panel-muted'] = attributes.panelMutedColor
  }

  if (typeof attributes.formPanelMinWidth === 'number' && attributes.formPanelMinWidth > 0) {
    style['--pd-rehearsal-form-panel-min'] = `${attributes.formPanelMinWidth}px`
  }

  const blockProps = useBlockProps({
    className: `is-layout-${attributes.layoutWidth || 'immersive'} ${attributes.usePanelGradient === false ? 'has-flat-panels' : 'has-panel-gradient'}`,
    style,
  })

  return (
    <Fragment>
      <InspectorControls>
        <PanelBody title={__('Current rehearsals settings', 'wp-song-study-blocks')}>
          <SelectControl
            label={__('Layout width', 'wp-song-study-blocks')}
            value={attributes.layoutWidth || 'immersive'}
            options={[
              { label: __('Default', 'wp-song-study-blocks'), value: 'default' },
              { label: __('Wide', 'wp-song-study-blocks'), value: 'wide' },
              { label: __('Immersive', 'wp-song-study-blocks'), value: 'immersive' },
            ]}
            onChange={(layoutWidth) => setAttributes({ layoutWidth })}
          />
          <ToggleControl
            label={__('Show admin link', 'wp-song-study-blocks')}
            checked={!!attributes.showAdminLink}
            onChange={(showAdminLink) => setAttributes({ showAdminLink })}
          />
          <ToggleControl
            label={__('Use panel gradient', 'wp-song-study-blocks')}
            checked={attributes.usePanelGradient !== false}
            onChange={(usePanelGradient) => setAttributes({ usePanelGradient })}
          />
          <TextControl
            label={__('Login message', 'wp-song-study-blocks')}
            value={attributes.loginMessage || ''}
            onChange={(loginMessage) => setAttributes({ loginMessage })}
          />
          <RangeControl
            label={__('Main form minimum width', 'wp-song-study-blocks')}
            value={typeof attributes.formPanelMinWidth === 'number' ? attributes.formPanelMinWidth : 720}
            onChange={(formPanelMinWidth) => setAttributes({ formPanelMinWidth: formPanelMinWidth ?? 720 })}
            min={480}
            max={1400}
            step={20}
            allowReset
            resetFallbackValue={720}
          />
          <RangeControl
            label={__('Gradient accent opacity', 'wp-song-study-blocks')}
            value={typeof attributes.panelGradientAccentOpacity === 'number' ? attributes.panelGradientAccentOpacity : 10}
            onChange={(panelGradientAccentOpacity) => setAttributes({ panelGradientAccentOpacity: panelGradientAccentOpacity ?? 10 })}
            min={0}
            max={100}
            step={1}
            allowReset
            resetFallbackValue={10}
          />
          <RangeControl
            label={__('Gradient secondary opacity', 'wp-song-study-blocks')}
            value={typeof attributes.panelGradientHighlightOpacity === 'number' ? attributes.panelGradientHighlightOpacity : 12}
            onChange={(panelGradientHighlightOpacity) => setAttributes({ panelGradientHighlightOpacity: panelGradientHighlightOpacity ?? 12 })}
            min={0}
            max={100}
            step={1}
            allowReset
            resetFallbackValue={12}
          />
        </PanelBody>
        <PanelColorSettings
          title={__('Rehearsal panel colors', 'wp-song-study-blocks')}
          colorSettings={[
            {
              value: attributes.panelBackgroundColor || '',
              onChange: (panelBackgroundColor) => setAttributes({ panelBackgroundColor: panelBackgroundColor || '' }),
              label: __('Panel background', 'wp-song-study-blocks'),
            },
            {
              value: attributes.panelBorderColor || '',
              onChange: (panelBorderColor) => setAttributes({ panelBorderColor: panelBorderColor || '' }),
              label: __('Panel border', 'wp-song-study-blocks'),
            },
            {
              value: attributes.panelGradientAccentColor || '',
              onChange: (panelGradientAccentColor) => setAttributes({ panelGradientAccentColor: panelGradientAccentColor || '' }),
              label: __('Gradient accent', 'wp-song-study-blocks'),
            },
            {
              value: attributes.panelGradientHighlightColor || '',
              onChange: (panelGradientHighlightColor) => setAttributes({ panelGradientHighlightColor: panelGradientHighlightColor || '' }),
              label: __('Gradient secondary', 'wp-song-study-blocks'),
            },
            {
              value: attributes.panelTextColor || '',
              onChange: (panelTextColor) => setAttributes({ panelTextColor: panelTextColor || '' }),
              label: __('Panel text', 'wp-song-study-blocks'),
            },
            {
              value: attributes.panelTitleColor || '',
              onChange: (panelTitleColor) => setAttributes({ panelTitleColor: panelTitleColor || '' }),
              label: __('Panel titles', 'wp-song-study-blocks'),
            },
            {
              value: attributes.panelMutedColor || '',
              onChange: (panelMutedColor) => setAttributes({ panelMutedColor: panelMutedColor || '' }),
              label: __('Secondary text', 'wp-song-study-blocks'),
            },
          ]}
        />
        <PanelColorSettings
          title={__('Rehearsal header colors', 'wp-song-study-blocks')}
          colorSettings={[
            {
              value: attributes.headerBackgroundColor || '',
              onChange: (headerBackgroundColor) => setAttributes({ headerBackgroundColor: headerBackgroundColor || '' }),
              label: __('Header background', 'wp-song-study-blocks'),
            },
            {
              value: attributes.headerBorderColor || '',
              onChange: (headerBorderColor) => setAttributes({ headerBorderColor: headerBorderColor || '' }),
              label: __('Header border', 'wp-song-study-blocks'),
            },
            {
              value: attributes.headerTextColor || '',
              onChange: (headerTextColor) => setAttributes({ headerTextColor: headerTextColor || '' }),
              label: __('Header text', 'wp-song-study-blocks'),
            },
            {
              value: attributes.headerTitleColor || '',
              onChange: (headerTitleColor) => setAttributes({ headerTitleColor: headerTitleColor || '' }),
              label: __('Header title', 'wp-song-study-blocks'),
            },
            {
              value: attributes.headerMutedColor || '',
              onChange: (headerMutedColor) => setAttributes({ headerMutedColor: headerMutedColor || '' }),
              label: __('Header secondary text', 'wp-song-study-blocks'),
            },
          ]}
        />
      </InspectorControls>
      <div {...blockProps}>
        <Placeholder
          label={__('Current Rehearsals', 'wp-song-study-blocks')}
          instructions={__(
            'Displays a frontend rehearsal workspace where each collaborator can define availability, vote for sessions, and review the rehearsal logbook.',
            'wp-song-study-blocks'
          )}
        >
          <p>
            {__(
              'Use this block inside the dedicated Ensayos page template so the logged-in musician can manage their own rehearsal data without entering wp-admin.',
              'wp-song-study-blocks'
            )}
          </p>
        </Placeholder>
      </div>
    </Fragment>
  )
}
