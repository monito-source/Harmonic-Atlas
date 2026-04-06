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
        label={__('Collaborator Presskit', 'wp-song-study-blocks')}
        instructions={__(
          'Displays the presskit of a collaborator resolved from the author archive, current post author or an optional user ID.',
          'wp-song-study-blocks'
        )}
      />
    </Fragment>
  )
}
