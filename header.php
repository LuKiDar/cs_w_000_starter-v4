<?php
/**
 * Header Template
 */

$mobile_logo_id = absint(get_option('cs_header_mobile_logo'));
$button_text    = get_option('cs_header_button_text');
$button_url     = get_option('cs_header_button_url');
$button_new_tab = get_option('cs_header_button_new_tab');
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
				<div class="site-logo<?= $mobile_logo_id ? ' has-mobile-logo' : ''; ?>">
					<div class="site-logo__desktop">
						<?php if ( has_custom_logo() ): ?>
							<?php the_custom_logo(); ?>
						<?php else: ?>
							<a href="<?= esc_url(home_url('/')); ?>" class="site-title"><?php bloginfo('name'); ?></a>
						<?php endif; ?>
					</div>

					<?php if ( $mobile_logo_id ): ?>
						<div class="site-logo__mobile">
							<a href="<?= esc_url(home_url('/')); ?>" class="logo" rel="home"<?= ( is_front_page() && ! is_paged() ) ? ' aria-current="page"' : ''; ?>>
								<?php
								$mobile_logo_attr = array(
									'class'   => 'logo__image',
									'loading' => false,
								);
								if ( get_post_meta($mobile_logo_id, '_wp_attachment_image_alt', true)=='' ){
									$mobile_logo_attr['alt'] = get_bloginfo('name', 'display');
								}
								echo wp_get_attachment_image($mobile_logo_id, 'full', false, $mobile_logo_attr);
								?>
							</a>
						</div>
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

				<?php if ( $button_text!='' && $button_url!='' ): ?>
					<a class="site-header__button button is-outlined"
					   href="<?= esc_url($button_url); ?>"
					   <?php if ( $button_new_tab ): ?>target="_blank" rel="noopener noreferrer"<?php endif; ?>
					><?= esc_html($button_text); ?></a>
				<?php endif; ?>

				<button class="nav-toggle" aria-controls="mobile-menu" aria-expanded="false" aria-label="<?php esc_attr_e('Toggle Navigation', CSWP); ?>">
					<span class="nav-toggle__bar" aria-hidden="true"></span>
					<span class="screen-reader-text"><?php esc_html_e('Menu', CSWP); ?></span>
				</button>
			</div>

			<?php if ( has_nav_menu('primary') || ( $button_text!='' && $button_url!='' ) ): ?>
				<nav id="mobile-menu" class="mobile-navigation" role="navigation" aria-label="<?php esc_attr_e('Mobile Menu', CSWP); ?>" hidden>
					<?php if ( has_nav_menu('primary') ): ?>
						<?php wp_nav_menu(array(
							'theme_location' => 'primary',
							'menu_class'     => 'primary-menu',
							'container'      => false,
							'depth'          => 2,
							'walker'         => new cs__primary_menu_walker(),
						)); ?>
					<?php endif; ?>

					<?php if ( $button_text!='' && $button_url!='' ): ?>
						<a class="site-header__button button is-outlined"
						   href="<?= esc_url($button_url); ?>"
						   <?php if ( $button_new_tab ): ?>target="_blank" rel="noopener noreferrer"<?php endif; ?>
						><?= esc_html($button_text); ?></a>
					<?php endif; ?>
				</nav>
			<?php endif; ?>
		</header>

		<main id="main" class="site-main">
		<?php global $template; echo basename($template); ?>