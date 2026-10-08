<?php
/**
 * Footer Template
 */
?>

		</main>

		<footer id="footer" class="site-footer container" role="contentinfo">
			<div class="site-footer__inner alignwide">
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
					<?php printf(
						/* translators: %s: site name */
						esc_html__('© %1$s %2$s', CSWP),
						esc_html(date_i18n('Y')),
						esc_html(get_bloginfo('name'))
					); ?>
				</p>
			</div>
		</footer>
	</div>

	<?php wp_footer(); ?>
</body>
</html>