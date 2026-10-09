<?php
/**
 * Footer Template
 */

$logo_id   = absint(get_option('cs_footer_logo'));
$copyright = get_option('cs_footer_copyright'); ?>

		</main>

		<footer id="footer" class="site-footer container" role="contentinfo">
			<div class="site-footer__inner alignwide">
				<?php if ( $logo_id ): ?>
					<div class="site-footer__branding">
						<a class="site-footer__logo" href="<?= esc_url(home_url('/')); ?>">
							<?= wp_get_attachment_image($logo_id, 'medium', false, array(
								'class' => 'site-footer__logo-image',
								'alt'   => get_bloginfo('name'),
							)); ?>
						</a>
					</div>
				<?php endif; ?>

				<?php if ( has_nav_menu('footer') ): ?>
					<nav class="site-footer__navigation" role="navigation" aria-label="<?php esc_attr_e('Footer Menu', CSWP); ?>">
						<?php wp_nav_menu(array(
							'theme_location' => 'footer',
							'menu_class'     => 'footer-menu',
							'container'      => false,
							'depth'          => 1,
						)); ?>
					</nav>
				<?php endif; ?>

				<p class="site-footer__copyright">
					<?php if ( $copyright!='' ): ?>
						<?= wp_kses_post($copyright); ?>
					<?php else: ?>
						<?php printf(
							/* translators: 1: year, 2: site name */
							esc_html__('© %1$s %2$s', CSWP),
							esc_html(date_i18n('Y')),
							esc_html(get_bloginfo('name'))
						); ?>
					<?php endif; ?>
				</p>
			</div>
		</footer>
	</div>

	<?php wp_footer(); ?>
</body>
</html>