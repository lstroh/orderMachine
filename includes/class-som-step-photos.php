<?php
/**
 * Required photos on a workflow step's current progress (UP16-S1).
 *
 * Make rows use order_item_step_progress_id. Pack and Post-ship rows use
 * order_step_progress_id. Exactly one of those ids is set.
 *
 * @package OrderMachine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Store and render JPEG/PNG step photos.
 */
class SOM_Step_Photos {

	/**
	 * 5 MB.
	 */
	const MAX_BYTES = 5242880;

	/**
	 * photo_label column width.
	 */
	const LABEL_MAX = 191;

	/**
	 * Allowed attachment mime types.
	 *
	 * @return string[]
	 */
	public static function mimes() {
		return array( 'image/jpeg', 'image/png' );
	}

	/**
	 * Whether this progress row has at least one photo.
	 *
	 * Item progress rows carry order_item_id. Order-level rows do not.
	 *
	 * @param object|null $progress Progress row.
	 * @return bool
	 */
	public static function progress_has_photo( $progress ) {
		if ( ! $progress || empty( $progress->id ) ) {
			return false;
		}

		global $wpdb;

		$column = self::progress_column( $progress );
		$table  = SOM_DB::table( 'step_photos' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- column is a fixed allowlist.
		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE {$column} = %d",
				(int) $progress->id
			)
		);

		return $count > 0;
	}

	/**
	 * Photos for one make-line progress row.
	 *
	 * @param int $progress_id order_item_step_progress id.
	 * @return array<int, object>
	 */
	public static function list_for_item_progress( $progress_id ) {
		return self::list_for_column( 'order_item_step_progress_id', (int) $progress_id );
	}

	/**
	 * Photos for one pack or post-ship progress row.
	 *
	 * @param int $progress_id order_step_progress id.
	 * @return array<int, object>
	 */
	public static function list_for_order_progress( $progress_id ) {
		return self::list_for_column( 'order_step_progress_id', (int) $progress_id );
	}

	/**
	 * Save an uploaded JPEG or PNG on the current step.
	 *
	 * @param int    $order_id      Order PK.
	 * @param int    $order_item_id Make line PK. 0 for pack and post-ship.
	 * @param string $file_key      $_FILES key.
	 * @return true|WP_Error
	 */
	public static function add_from_upload( $order_id, $order_item_id = 0, $file_key = 'som_step_photo_file' ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'som_forbidden', __( 'You cannot add a step photo.', 'order-machine' ) );
		}

		$target = self::editable_target( $order_id, $order_item_id );
		if ( is_wp_error( $target ) ) {
			return $target;
		}

		if ( empty( $_FILES[ $file_key ] ) || empty( $_FILES[ $file_key ]['name'] ) ) {
			return new WP_Error( 'som_step_photo', __( 'No photo file selected.', 'order-machine' ) );
		}

		$file = $_FILES[ $file_key ]; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- validated below / by media_handle_upload.
		$size = isset( $file['size'] ) ? (int) $file['size'] : 0;
		if ( $size > self::MAX_BYTES ) {
			return new WP_Error( 'som_step_photo', __( 'Photo must be 5 MB or smaller.', 'order-machine' ) );
		}

		$tmp   = isset( $file['tmp_name'] ) ? (string) $file['tmp_name'] : '';
		$name  = isset( $file['name'] ) ? (string) $file['name'] : '';
		$check = wp_check_filetype_and_ext( ( $tmp && is_readable( $tmp ) ) ? $tmp : '', $name );
		$mime  = ! empty( $check['type'] ) ? (string) $check['type'] : '';
		if ( ! in_array( $mime, self::mimes(), true ) ) {
			return new WP_Error( 'som_step_photo', __( 'Photo must be a JPEG or PNG.', 'order-machine' ) );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		add_filter( 'upload_dir', array( __CLASS__, 'filter_upload_dir' ) );
		try {
			if ( is_uploaded_file( $tmp ) ) {
				$attachment_id = media_handle_upload( $file_key, 0 );
			} elseif ( defined( 'WP_CLI' ) && WP_CLI && '' !== $tmp && is_readable( $tmp ) ) {
				// wp eval-file cannot pass is_uploaded_file(). Browser posts still use media_handle_upload.
				$attachment_id = media_handle_sideload( $file, 0 );
			} else {
				$attachment_id = new WP_Error( 'som_step_photo', __( 'Photo must be a JPEG or PNG.', 'order-machine' ) );
			}
		} finally {
			remove_filter( 'upload_dir', array( __CLASS__, 'filter_upload_dir' ) );
		}

		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}

		$attachment_id = (int) $attachment_id;
		$att_mime      = (string) get_post_mime_type( $attachment_id );
		if ( ! in_array( $att_mime, self::mimes(), true ) ) {
			wp_delete_attachment( $attachment_id, true );
			return new WP_Error( 'som_step_photo', __( 'Photo must be a JPEG or PNG.', 'order-machine' ) );
		}

		$item_progress_id  = 'order_item_step_progress_id' === $target['column'] ? (int) $target['progress']->id : null;
		$order_progress_id = 'order_step_progress_id' === $target['column'] ? (int) $target['progress']->id : null;
		if ( ( null === $item_progress_id ) === ( null === $order_progress_id ) ) {
			wp_delete_attachment( $attachment_id, true );
			return new WP_Error( 'som_step_photo', __( 'Could not save the step photo.', 'order-machine' ) );
		}

		global $wpdb;

		$user_id = get_current_user_id();
		$ok      = $wpdb->insert(
			SOM_DB::table( 'step_photos' ),
			array(
				'order_step_progress_id'      => $order_progress_id,
				'order_item_step_progress_id' => $item_progress_id,
				'attachment_id'               => $attachment_id,
				'uploaded_by'                 => $user_id > 0 ? $user_id : null,
				'created_at'                  => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%d', '%d', '%s' )
		);
		if ( ! $ok ) {
			wp_delete_attachment( $attachment_id, true );
			return new WP_Error( 'som_step_photo', __( 'Could not save the step photo.', 'order-machine' ) );
		}

		return true;
	}

	/**
	 * Delete one photo from the current step and its attachment.
	 *
	 * @param int $order_id      Order PK.
	 * @param int $order_item_id Make line PK. 0 for pack and post-ship.
	 * @param int $photo_id      som_step_photos id.
	 * @return true|WP_Error
	 */
	public static function remove( $order_id, $order_item_id, $photo_id ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'som_forbidden', __( 'You cannot remove a step photo.', 'order-machine' ) );
		}

		$target = self::editable_target( $order_id, $order_item_id );
		if ( is_wp_error( $target ) ) {
			return $target;
		}

		$photo_id = (int) $photo_id;
		if ( $photo_id < 1 ) {
			return new WP_Error( 'som_step_photo', __( 'Photo not found.', 'order-machine' ) );
		}

		global $wpdb;

		$column = $target['column'];
		$table  = SOM_DB::table( 'step_photos' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- column is a fixed allowlist.
		$photo = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, attachment_id FROM {$table} WHERE id = %d AND {$column} = %d LIMIT 1",
				$photo_id,
				(int) $target['progress']->id
			)
		);
		if ( ! $photo ) {
			return new WP_Error( 'som_step_photo', __( 'Photo not found.', 'order-machine' ) );
		}

		$deleted = $wpdb->delete(
			$table,
			array( 'id' => $photo_id ),
			array( '%d' )
		);
		if ( false === $deleted ) {
			return new WP_Error( 'som_step_photo', __( 'Could not remove the step photo.', 'order-machine' ) );
		}

		$attachment_id = (int) $photo->attachment_id;
		if ( $attachment_id > 0 ) {
			wp_delete_attachment( $attachment_id, true );
		}

		return true;
	}

	/**
	 * Thumbnails, and Add / Remove while this step is current and not done.
	 *
	 * @param object $order         Order row.
	 * @param object $progress      Progress row joined with photo_required and photo_label.
	 * @param bool   $is_current    Whether this row is the current step.
	 * @param int    $order_item_id Make line PK. 0 for pack and post-ship.
	 * @return void
	 */
	public static function render_panel( $order, $progress, $is_current, $order_item_id = 0 ) {
		if ( ! $progress || empty( $progress->photo_required ) ) {
			return;
		}

		$order_item_id = (int) $order_item_id;
		$photos        = $order_item_id > 0
			? self::list_for_item_progress( (int) $progress->id )
			: self::list_for_order_progress( (int) $progress->id );
		$status        = isset( $progress->status ) ? (string) $progress->status : '';
		$editable      = $is_current
			&& $order
			&& empty( $order->is_cancelled )
			&& ! in_array( $status, array( 'pending', 'done' ), true )
			&& current_user_can( 'manage_options' );

		if ( ! $editable && ! $photos ) {
			return;
		}

		$label = isset( $progress->photo_label ) ? trim( (string) $progress->photo_label ) : '';
		if ( '' === $label ) {
			$label = __( 'Photo', 'order-machine' );
		}

		$action = SOM_Orders::detail_url( (int) $order->id );
		?>
		<div class="som-step-photos">
			<strong class="som-step-photos-label"><?php echo esc_html( $label ); ?></strong>
			<?php if ( $photos ) : ?>
				<ul class="som-step-photo-list">
					<?php foreach ( $photos as $photo ) : ?>
						<li class="som-step-photo">
							<?php
							$url = wp_get_attachment_url( (int) $photo->attachment_id );
							if ( $url ) {
								echo '<a class="som-step-photo-link" href="' . esc_url( $url ) . '">';
								echo wp_get_attachment_image(
									(int) $photo->attachment_id,
									'thumbnail',
									false,
									array( 'class' => 'som-step-photo-thumb' )
								);
								echo '</a>';
							}
							?>
							<?php if ( $editable ) : ?>
								<form method="post" action="<?php echo esc_url( $action ); ?>" class="som-step-photo-remove-form">
									<?php wp_nonce_field( 'som_step_photo', 'som_step_photo_nonce' ); ?>
									<input type="hidden" name="som_order_id" value="<?php echo esc_attr( (string) (int) $order->id ); ?>" />
									<?php if ( $order_item_id > 0 ) : ?>
										<input type="hidden" name="som_order_item_id" value="<?php echo esc_attr( (string) $order_item_id ); ?>" />
									<?php endif; ?>
									<input type="hidden" name="som_step_photo_id" value="<?php echo esc_attr( (string) (int) $photo->id ); ?>" />
									<input type="hidden" name="som_remove_step_photo" value="1" />
									<?php submit_button( __( 'Remove', 'order-machine' ), 'secondary', 'submit', false ); ?>
								</form>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
			<?php if ( $editable ) : ?>
				<form method="post" action="<?php echo esc_url( $action ); ?>" enctype="multipart/form-data" class="som-step-photo-form">
					<?php wp_nonce_field( 'som_step_photo', 'som_step_photo_nonce' ); ?>
					<input type="hidden" name="som_order_id" value="<?php echo esc_attr( (string) (int) $order->id ); ?>" />
					<?php if ( $order_item_id > 0 ) : ?>
						<input type="hidden" name="som_order_item_id" value="<?php echo esc_attr( (string) $order_item_id ); ?>" />
					<?php endif; ?>
					<input type="hidden" name="som_step_photo" value="1" />
					<input type="file" name="som_step_photo_file" accept="image/jpeg,image/png" />
					<?php submit_button( __( 'Add photo', 'order-machine' ), 'secondary', 'submit', false ); ?>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Limit this upload to uploads/order-machine/step-photos/YYYY/MM/.
	 *
	 * @param array<string, string> $dirs upload_dir result.
	 * @return array<string, string>
	 */
	public static function filter_upload_dir( $dirs ) {
		$subdir = '/order-machine/step-photos';
		if ( ! empty( $dirs['subdir'] ) ) {
			$subdir .= $dirs['subdir'];
		}
		$dirs['subdir'] = $subdir;
		$dirs['path']   = $dirs['basedir'] . $subdir;
		$dirs['url']    = $dirs['baseurl'] . $subdir;
		return $dirs;
	}

	/**
	 * @param object $progress Progress row.
	 * @return string
	 */
	private static function progress_column( $progress ) {
		if ( isset( $progress->order_item_id ) && (int) $progress->order_item_id > 0 ) {
			return 'order_item_step_progress_id';
		}
		return 'order_step_progress_id';
	}

	/**
	 * @param string $column      Fixed column name.
	 * @param int    $progress_id Progress PK.
	 * @return array<int, object>
	 */
	private static function list_for_column( $column, $progress_id ) {
		if ( $progress_id < 1 || ! in_array( $column, array( 'order_step_progress_id', 'order_item_step_progress_id' ), true ) ) {
			return array();
		}

		global $wpdb;

		$table = SOM_DB::table( 'step_photos' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- column is a fixed allowlist.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, attachment_id, uploaded_by, created_at FROM {$table} WHERE {$column} = %d ORDER BY id ASC",
				$progress_id
			)
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Current progress that may accept a photo.
	 *
	 * @param int $order_id      Order PK.
	 * @param int $order_item_id Make line PK. 0 for pack and post-ship.
	 * @return array{progress: object, column: string}|WP_Error
	 */
	private static function editable_target( $order_id, $order_item_id ) {
		$order_id      = (int) $order_id;
		$order_item_id = (int) $order_item_id;
		$order         = SOM_Orders::get( $order_id );
		if ( ! $order ) {
			return new WP_Error( 'som_order_missing', __( 'Order not found.', 'order-machine' ) );
		}
		if ( ! empty( $order->is_cancelled ) ) {
			return new WP_Error( 'som_order_cancelled', __( 'Cancelled orders cannot be changed.', 'order-machine' ) );
		}

		if ( $order_item_id > 0 ) {
			$progress = SOM_Item_Make::current_item_progress( $order_item_id );
			if ( ! $progress || (int) $progress->order_id !== $order_id ) {
				return new WP_Error( 'som_step_photo', __( 'Photo can only be changed on the current step.', 'order-machine' ) );
			}
			$column = 'order_item_step_progress_id';
		} else {
			$current_step_id = (int) $order->current_step_id;
			$progress        = null;
			foreach ( SOM_Workflow_Engine::get_progress( $order_id ) as $row ) {
				if ( (int) $row->workflow_step_id === $current_step_id ) {
					$progress = $row;
					break;
				}
			}
			if ( ! $progress ) {
				return new WP_Error( 'som_step_photo', __( 'Photo can only be changed on the current step.', 'order-machine' ) );
			}
			$column = 'order_step_progress_id';
		}

		if ( empty( $progress->photo_required ) ) {
			return new WP_Error( 'som_step_photo', __( 'This step does not require a photo.', 'order-machine' ) );
		}

		$status = (string) $progress->status;
		if ( in_array( $status, array( 'pending', 'done' ), true ) ) {
			return new WP_Error( 'som_step_photo', __( 'Photo can only be changed on the current step.', 'order-machine' ) );
		}

		return array(
			'progress' => $progress,
			'column'   => $column,
		);
	}
}
