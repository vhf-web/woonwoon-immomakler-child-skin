<?php
/**
 * Child skin override: move map to top.
 *
 * @package ImmoMakler Child Skin
 */
?>

<div class="row woonwoon-single-map-top">
	<div class="col-xs-12">
		<?php immomakler_get_template_part( 'single/map', 'property' ); ?>
	</div>
</div>

<div class="row">
	<div class="col-xs-12">
		<?php immomakler_get_template_part( 'single/actions', 'property' ); ?>
	</div>
</div>

<?php if ( immomakler_number_of_columns( 'single' ) > 1 ) : ?>

<div class="row">
	<?php if ( ImmoMakler_Options::get( 'gallery_full_width' ) ) : ?>
	<div class="col-xs-12">
		<?php immomakler_get_template_part( 'single/gallery', 'property' ); ?>
	</div>
	<?php endif; ?>
	<div class="col-xs-12 col-sm-7 col-sm-push-5">
		<?php if ( ! ImmoMakler_Options::get( 'gallery_full_width' ) ) : ?>
		<?php immomakler_get_template_part( 'single/gallery', 'property' ); ?>
		<?php endif; ?>
		<?php immomakler_get_template_part( 'single/videos', 'property' ); ?>
		<?php immomakler_get_template_part( 'single/virtualtours', 'property' ); ?>
		<?php immomakler_get_template_part( 'single/attachments', 'property' ); ?>
		<?php immomakler_get_template_part( 'single/contact', 'property' ); ?>
	</div>
	<div class="col-xs-12 col-sm-5 col-sm-pull-7">
		<?php do_action( 'immomakler_before_single_details' ); ?>
		<?php immomakler_get_template_part( 'single/status', 'property' ); ?>
		<?php immomakler_get_template_part( 'single/data', 'property' ); ?>
		<?php immomakler_get_template_part( 'single/features', 'property' ); ?>
		<?php immomakler_get_template_part( 'single/hrsds-features', 'property' ); ?>
		<?php immomakler_get_template_part( 'single/epass', 'property' ); ?>
		<?php do_action( 'immomakler_after_single_details' ); ?>
	</div>
	<div class="col-xs-12">
		<?php immomakler_get_template_part( 'single/description', 'property' ); ?>
	</div>
	<div class="col-xs-12 col-sm-6">
		<?php immomakler_get_template_part( 'single/contactform', 'property' ); ?>
	</div>
</div>

<?php else : ?>

<div class="row">
	<div class="col-xs-12">
		<?php immomakler_get_template_part( 'single/gallery', 'property' ); ?>
		<?php immomakler_get_template_part( 'single/videos', 'property' ); ?>
		<?php immomakler_get_template_part( 'single/virtualtours', 'property' ); ?>
		<?php immomakler_get_template_part( 'single/attachments', 'property' ); ?>
	</div>
	<div class="col-xs-12">
		<?php do_action( 'immomakler_before_single_details' ); ?>
		<?php immomakler_get_template_part( 'single/status', 'property' ); ?>
		<?php immomakler_get_template_part( 'single/data', 'property' ); ?>
		<?php immomakler_get_template_part( 'single/features', 'property' ); ?>
		<?php immomakler_get_template_part( 'single/hrsds-features', 'property' ); ?>
		<?php immomakler_get_template_part( 'single/epass', 'property' ); ?>
		<?php do_action( 'immomakler_after_single_details' ); ?>
	</div>
	<div class="col-xs-12">
		<?php immomakler_get_template_part( 'single/description', 'property' ); ?>
	</div>
	<div class="col-xs-12">
		<?php immomakler_get_template_part( 'single/contact', 'property' ); ?>
	</div>
	<div class="col-xs-12">
		<?php immomakler_get_template_part( 'single/contactform', 'property' ); ?>
	</div>
</div>

<?php endif; ?>

<?php echo apply_filters( 'the_content', '' ); ?>

