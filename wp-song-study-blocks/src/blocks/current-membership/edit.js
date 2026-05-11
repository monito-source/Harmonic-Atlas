import { InspectorControls, useBlockProps, useSettings } from '@wordpress/block-editor'
import {
  BaseControl,
  ColorPalette,
  PanelBody,
  Placeholder,
  RangeControl,
  SelectControl,
  TextControl,
  ToggleControl,
} from '@wordpress/components'
import { Fragment } from '@wordpress/element'
import { __ } from '@wordpress/i18n'

export default function Edit({ attributes, setAttributes }) {
  const blockProps = useBlockProps({
    className: `is-layout-${attributes.layoutWidth || 'immersive'}`,
    style: {
      '--pd-membership-custom-shell-text': attributes.shellTextColor || undefined,
      '--pd-membership-custom-header-background': attributes.headerBackgroundColor || undefined,
      '--pd-membership-custom-header-text': attributes.headerTextColor || undefined,
      '--pd-membership-custom-panel-background': attributes.panelBackgroundColor || undefined,
      '--pd-membership-custom-panel-text': attributes.panelTextColor || undefined,
      '--pd-membership-custom-panel-border': attributes.panelBorderColor || undefined,
      '--pd-membership-custom-field-background': attributes.fieldBackgroundColor || undefined,
      '--pd-membership-custom-field-text': attributes.fieldTextColor || undefined,
      '--pd-membership-custom-link': attributes.linkColor || undefined,
      '--pd-membership-editor-min-height': attributes.editorMinHeight
        ? `${attributes.editorMinHeight}px`
        : undefined,
    },
  })
  const [themePalette = []] = useSettings('color.palette')

  const renderColorControl = (label, attributeName) => (
    <BaseControl label={label}>
      <ColorPalette
        colors={themePalette}
        value={attributes[attributeName] || ''}
        onChange={(value) => setAttributes({ [attributeName]: value || '' })}
        clearable
      />
    </BaseControl>
  )

  return (
    <Fragment>
      <InspectorControls>
        <PanelBody title={__('Current membership settings', 'wp-song-study-blocks')}>
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
          <RangeControl
            label={__('Editor minimum height', 'wp-song-study-blocks')}
            value={attributes.editorMinHeight || 720}
            onChange={(editorMinHeight) =>
              setAttributes({ editorMinHeight: Number(editorMinHeight) || 720 })
            }
            min={560}
            max={1200}
            step={20}
          />
          <TextControl
            label={__('Default target user ID', 'wp-song-study-blocks')}
            help={__(
              'Optional. Administrators can still switch to another user from the frontend view.',
              'wp-song-study-blocks'
            )}
            type="number"
            value={attributes.targetUserId || ''}
            onChange={(targetUserId) =>
              setAttributes({ targetUserId: Number.parseInt(targetUserId || '0', 10) || 0 })
            }
          />
          <ToggleControl
            label={__('Show projects', 'wp-song-study-blocks')}
            checked={!!attributes.showProjects}
            onChange={(showProjects) => setAttributes({ showProjects })}
          />
          <ToggleControl
            label={__('Show public preview', 'wp-song-study-blocks')}
            checked={!!attributes.showPreview}
            onChange={(showPreview) => setAttributes({ showPreview })}
          />
          <ToggleControl
            label={__('Show WordPress profile link', 'wp-song-study-blocks')}
            checked={!!attributes.showAdminLink}
            onChange={(showAdminLink) => setAttributes({ showAdminLink })}
          />
          <TextControl
            label={__('Login message', 'wp-song-study-blocks')}
            value={attributes.loginMessage || ''}
            onChange={(loginMessage) => setAttributes({ loginMessage })}
          />
        </PanelBody>
        <PanelBody title={__('Membership colors', 'wp-song-study-blocks')} initialOpen={false}>
          {renderColorControl(__('Shell text', 'wp-song-study-blocks'), 'shellTextColor')}
          {renderColorControl(
            __('Header background', 'wp-song-study-blocks'),
            'headerBackgroundColor'
          )}
          {renderColorControl(__('Header text', 'wp-song-study-blocks'), 'headerTextColor')}
          {renderColorControl(
            __('Panel background', 'wp-song-study-blocks'),
            'panelBackgroundColor'
          )}
          {renderColorControl(__('Panel text', 'wp-song-study-blocks'), 'panelTextColor')}
          {renderColorControl(__('Panel border', 'wp-song-study-blocks'), 'panelBorderColor')}
          {renderColorControl(
            __('Field background', 'wp-song-study-blocks'),
            'fieldBackgroundColor'
          )}
          {renderColorControl(__('Field text', 'wp-song-study-blocks'), 'fieldTextColor')}
          {renderColorControl(__('Accent / links', 'wp-song-study-blocks'), 'linkColor')}
        </PanelBody>
      </InspectorControls>
      <div {...blockProps}>
        <Placeholder
          label={__('Current Membership', 'wp-song-study-blocks')}
          instructions={__(
            'Displays a frontend space where the logged-in user can edit their own presskit and review their projects.',
            'wp-song-study-blocks'
          )}
        >
          <p>
            {__(
              'Use the inspector to widen the workbench and tune shell, panel, and field colors. If you clear those colors, the frontend view falls back to the active theme.',
              'wp-song-study-blocks'
            )}
          </p>
        </Placeholder>
      </div>
    </Fragment>
  )
}
