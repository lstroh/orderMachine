<?php
/**
 * Copy-command workflow step: template render + placeholders (display only).
 *
 * @package OrderMachine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Renders editable step command templates from order / line fields.
 * WordPress never executes the resulting command.
 */
class SOM_Copy_Command {

	/**
	 * Max length for stored command templates.
	 */
	const MAX_TEMPLATE_LENGTH = 4000;

	/**
	 * Seed / example template for small sticker make (operator runs locally).
	 */
	const EXAMPLE_MAKE_STICKER_TEMPLATE = "/make-sticker-order Order number: {{order_number}}\nHouse number: {{house_number}}\nStreet: {{street_name}}\neBay design: {{style}}\nBuyer name: {{buyer_name}}";

	/**
	 * Placeholder keys supported in v1 (without braces).
	 *
	 * @return string[]
	 */
	public static function placeholder_keys() {
		return array(
			'order_number',
			'house_number',
			'street_name',
			'style',
			'buyer_name',
			'buyer_full_name',
			'product_name',
			'personalisation',
		);
	}

	/**
	 * Friendly names for {@see placeholder_keys()}, in that order.
	 *
	 * Single source for the workflow editor Insert placeholder dropdown.
	 * A new key needs a label here before it appears in the control.
	 *
	 * @return array<string, string>
	 */
	public static function placeholder_labels() {
		$catalog = array(
			'order_number'    => __( 'Order number', 'order-machine' ),
			'house_number'    => __( 'House number', 'order-machine' ),
			'street_name'     => __( 'Street name', 'order-machine' ),
			'style'           => __( 'Style / design', 'order-machine' ),
			'buyer_name'      => __( 'Buyer first name', 'order-machine' ),
			'buyer_full_name' => __( 'Buyer full name', 'order-machine' ),
			'product_name'    => __( 'Product name', 'order-machine' ),
			'personalisation' => __( 'Personalisation', 'order-machine' ),
		);
		$labels  = array();
		foreach ( self::placeholder_keys() as $key ) {
			if ( isset( $catalog[ $key ] ) && '' !== (string) $catalog[ $key ] ) {
				$labels[ $key ] = $catalog[ $key ];
			}
		}
		return $labels;
	}

	/**
	 * Dropdown that inserts `{{key}}` into the sibling Copy command textarea.
	 *
	 * @return void
	 */
	public static function render_placeholder_picker() {
		?>
		<select class="som-copy-placeholder-picker" data-som-copy-placeholder aria-label="<?php echo esc_attr__( 'Insert placeholder', 'order-machine' ); ?>">
			<option value=""><?php echo esc_html__( 'Insert placeholder…', 'order-machine' ); ?></option>
			<?php foreach ( self::placeholder_labels() as $key => $label ) : ?>
				<?php $token = '{{' . $key . '}}'; ?>
				<option value="<?php echo esc_attr( $token ); ?>" title="<?php echo esc_attr( $token ); ?>"><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/**
	 * Whether a step has a non-empty command template.
	 *
	 * @param object|null $step Step row.
	 * @return bool
	 */
	public static function has_template( $step ) {
		if ( ! $step || ! isset( $step->command_template ) ) {
			return false;
		}
		return '' !== trim( (string) $step->command_template );
	}

	/**
	 * Sanitize a command template from the editor.
	 *
	 * @param mixed $raw Raw POST value.
	 * @return string|null|WP_Error Null when empty; WP_Error when too long.
	 */
	public static function sanitize_template( $raw ) {
		if ( null === $raw ) {
			return null;
		}
		$text = sanitize_textarea_field( (string) $raw );
		$text = trim( $text );
		if ( '' === $text ) {
			return null;
		}
		if ( strlen( $text ) > self::MAX_TEMPLATE_LENGTH ) {
			return new WP_Error(
				'som_command_template_long',
				sprintf(
					/* translators: %d: max characters */
					__( 'Copy command template must be at most %d characters.', 'order-machine' ),
					self::MAX_TEMPLATE_LENGTH
				)
			);
		}
		return $text;
	}

	/**
	 * First whitespace token of a buyer name (O2).
	 *
	 * @param string $full_name Full buyer name.
	 * @return string
	 */
	public static function buyer_first_name( $full_name ) {
		$full_name = trim( (string) $full_name );
		if ( '' === $full_name ) {
			return '';
		}
		$parts = preg_split( '/\s+/u', $full_name, 2 );
		return is_array( $parts ) && isset( $parts[0] ) ? (string) $parts[0] : $full_name;
	}

	/**
	 * Build placeholder values from an order + make line.
	 *
	 * @param object      $order Order row (needs external_order_id, buyer_name).
	 * @param object|null $item  Order item row (structured fields + personalisation).
	 * @return array<string, string>
	 */
	public static function values_from_order_item( $order, $item = null ) {
		$buyer_full = $order && isset( $order->buyer_name ) ? (string) $order->buyer_name : '';
		$values     = array(
			'order_number'     => $order && isset( $order->external_order_id ) ? (string) $order->external_order_id : '',
			'house_number'     => '',
			'street_name'      => '',
			'style'            => '',
			'buyer_name'       => self::buyer_first_name( $buyer_full ),
			'buyer_full_name'  => $buyer_full,
			'product_name'     => '',
			'personalisation'  => '',
		);

		if ( $item ) {
			if ( isset( $item->house_number ) && null !== $item->house_number && '' !== (string) $item->house_number ) {
				$values['house_number'] = (string) $item->house_number;
			}
			if ( isset( $item->street_name ) && null !== $item->street_name && '' !== (string) $item->street_name ) {
				$values['street_name'] = (string) $item->street_name;
			}
			if ( isset( $item->style ) && null !== $item->style && '' !== (string) $item->style ) {
				$values['style'] = (string) $item->style;
			}
			if ( isset( $item->personalisation_text ) && null !== $item->personalisation_text ) {
				$values['personalisation'] = (string) $item->personalisation_text;
			}
			if ( isset( $item->product_name ) && '' !== (string) $item->product_name ) {
				$values['product_name'] = (string) $item->product_name;
			} elseif ( isset( $item->product_id ) && (int) $item->product_id > 0 && class_exists( 'SOM_Products' ) ) {
				$product = SOM_Products::get( (int) $item->product_id );
				if ( $product && ! empty( $product->name ) ) {
					$values['product_name'] = (string) $product->name;
				}
			}
		}

		return $values;
	}

	/**
	 * Render a template. Unknown {{…}} left literal. Missing known keys → empty string.
	 *
	 * @param string                $template Template with {{placeholders}}.
	 * @param array<string, string> $values   Placeholder map.
	 * @return array{command: string, missing: string[]}
	 */
	public static function render( $template, array $values ) {
		$template = (string) $template;
		$missing  = array();
		$known    = array_fill_keys( self::placeholder_keys(), true );

		foreach ( self::placeholder_keys() as $key ) {
			if ( ! array_key_exists( $key, $values ) || '' === (string) $values[ $key ] ) {
				if ( false !== strpos( $template, '{{' . $key . '}}' ) ) {
					$missing[] = $key;
				}
			}
		}

		$command = preg_replace_callback(
			'/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/',
			static function ( $m ) use ( $values, $known ) {
				$key = $m[1];
				if ( ! isset( $known[ $key ] ) ) {
					return $m[0];
				}
				return array_key_exists( $key, $values ) ? (string) $values[ $key ] : '';
			},
			$template
		);

		return array(
			'command' => is_string( $command ) ? $command : $template,
			'missing' => $missing,
		);
	}

	/**
	 * Human notice for missing placeholder fields.
	 *
	 * @param string[] $missing Missing keys.
	 * @return string Empty when none missing.
	 */
	public static function missing_notice( array $missing ) {
		$missing = array_values( array_filter( array_map( 'strval', $missing ) ) );
		if ( ! $missing ) {
			return '';
		}
		return sprintf(
			/* translators: %s: comma-separated placeholder names */
			__( 'Missing: %s', 'order-machine' ),
			implode( ', ', $missing )
		);
	}
}
