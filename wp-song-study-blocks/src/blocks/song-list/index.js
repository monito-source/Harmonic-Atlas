import metadata from '../../../build/song-list/block.json';
import edit from './edit';
import save from './save';
import { registerBlockType } from '@wordpress/blocks';

registerBlockType(metadata.name, {
  edit,
  save,
});
