<?php
declare(strict_types=1);

namespace CiWoocommerce\Templates;

final class SettingsTemplate {
	public static function render(array $args = []): void {
		extract($args);
		?>
		<div class="wrap">
			<h1><?php echo esc_html__('Commerce Intelligence', 'ci-woocommerce'); ?></h1>
			<?php if (!empty($saved)) : ?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html__('Settings saved.', 'ci-woocommerce'); ?></p></div>
			<?php endif; ?>
			<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr($action); ?>">
				<?php wp_nonce_field($action, 'security'); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="ci-endpoint"><?php echo esc_html__('Collector URL', 'ci-woocommerce'); ?></label></th>
						<td><input type="url" id="ci-endpoint" name="endpoint" class="regular-text" value="<?php echo esc_attr($endpoint); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="ci-key-id"><?php echo esc_html__('Key id', 'ci-woocommerce'); ?></label></th>
						<td><input type="text" id="ci-key-id" name="key_id" class="regular-text" autocomplete="off" value="<?php echo esc_attr($keyId); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="ci-secret"><?php echo esc_html__('Secret', 'ci-woocommerce'); ?></label></th>
						<td>
							<input type="password" id="ci-secret" name="secret" class="regular-text" autocomplete="new-password" value="" placeholder="<?php echo esc_attr($secretIsSet ? __('Saved, leave empty to keep', 'ci-woocommerce') : ''); ?>">
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__('Last error', 'ci-woocommerce'); ?></th>
						<td>
							<?php if ($lastError === null) : ?>
								<?php echo esc_html__('None', 'ci-woocommerce'); ?>
							<?php else : ?>
								<code><?php echo esc_html($lastError['message']); ?></code>
								<br><?php echo esc_html($lastError['time']); ?>
							<?php endif; ?>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
