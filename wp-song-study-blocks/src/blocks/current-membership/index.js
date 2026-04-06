import metadata from '../../../build/current-membership/block.json'
import edit from './edit'
import save from '../server-save'
import { registerBlockType } from '@wordpress/blocks'

registerBlockType(metadata.name, {
  edit,
  save,
})
