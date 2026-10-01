<?php
/**
 * Header Template
 */
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo('charset'); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>

<body <?php body_class('is-header-fixed'); ?>>
	<?php wp_body_open(); ?>

	<div id="page" class="site-container">
		<a class="screen-reader-shortcut" href="#main" aria-label="<?php esc_attr_e('Skip to main content', CSWP); ?>"><?php esc_html_e('Skip to main content', CSWP); ?></a>
		<a class="screen-reader-shortcut" href="#footer" aria-label="<?php esc_attr_e('Skip to footer content', CSWP); ?>"><?php esc_html_e('Skip to footer content', CSWP); ?></a>

		<header id="masthead" class="site-header container" role="banner">
			<div class="site-header__inner alignwide">
				<div class="site-logo">
					<?php if ( has_custom_logo() ): ?>
						<?php the_custom_logo(); ?>
					<?php else: ?>
						<a href="<?= esc_url(home_url('/')); ?>" class="site-title"><?php bloginfo('name'); ?></a>
					<?php endif; ?>
				</div>

				<?php if ( has_nav_menu('primary') ): ?>
					<nav class="site-header__navigation" role="navigation" aria-label="<?php esc_attr_e('Main Menu', CSWP); ?>">
						<?php wp_nav_menu(array(
							'theme_location' => 'primary',
							'menu_class'     => 'primary-menu',
							'container'      => false,
							'depth'          => 2,
							'walker'         => new cs__primary_menu_walker(),
						)); ?>
					</nav>
				<?php endif; ?>

				<button class="nav-toggle" aria-controls="mobile-menu" aria-expanded="false" aria-label="<?php esc_attr_e('Toggle Navigation', CSWP); ?>">
					<span class="nav-toggle__bar" aria-hidden="true"></span>
					<span class="screen-reader-text"><?php esc_html_e('Menu', CSWP); ?></span>
				</button>
			</div>

			<?php if ( has_nav_menu('primary') ): ?>
				<nav id="mobile-menu" class="mobile-navigation" role="navigation" aria-label="<?php esc_attr_e('Mobile Menu', CSWP); ?>" hidden>
					<?php wp_nav_menu(array(
						'theme_location' => 'primary',
						'menu_class'     => 'primary-menu',
						'container'      => false,
						'depth'          => 2,
						'walker'         => new cs__primary_menu_walker(),
					)); ?>
				</nav>
			<?php endif; ?>
		</header>

		<main id="main" class="site-main">