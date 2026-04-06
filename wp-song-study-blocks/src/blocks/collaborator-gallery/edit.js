import { Fragment } from '@wordpress/element'
import { __ } from '@wordpress/i18n'
import { BlockPlaceholder, CollaboratorSourceControls } from '../shared'

export default function Edit({ attributes, setAttributes }) {
  return (
    <Fragment>
      <CollaboratorSourceControls
        attributes={attributes}
        setAttributes={setAttributes}
        panelTitle={__('Collaborator source', 'wp-song-study-blocks')}
      />
      <BlockPlaceholder
        label={__('Collaborator Gallery', 'wp-song-study-blocks')}
        instructions={__(
          'Displays the press photo gallery of a collaborator.',
          'wp-song-study-blocks'
        )}
      />
    </Fragment>
  )
}
